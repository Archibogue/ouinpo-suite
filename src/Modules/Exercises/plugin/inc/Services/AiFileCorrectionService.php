<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class AiFileCorrectionService
{
    private const COMPETENCY_STATUSES = ['acquis', 'à renforcer', 'non acquis', 'non évalué'];

    public function analyze(int $copy_id): array|\WP_Error
    {
        global $wpdb;
        $copy = CorrectionBatchService::get_copy($copy_id);
        if (!$copy || (string) ($copy['source_type'] ?? '') !== 'file') {
            return new \WP_Error('copy_not_found', 'Rendu fichier introuvable.');
        }

        $batch = FileCorrectionBatchService::get_batch((int) $copy['batch_id']);
        if (!$batch) {
            return new \WP_Error('batch_not_found', 'Lot fichiers introuvable.');
        }

        $content = trim((string) (($copy['extracted_content'] ?? '') ?: ($copy['ocr_text'] ?? '')));
        if ($content === '') {
            return new \WP_Error('content_missing', 'Aucun contenu extrait pour ce rendu.');
        }

        if (!AiSettings::enabled_for_usage('pedagogical_suggestions') || (int) AiSettings::get('ouinpo_ai_file_correction_enabled') !== 1) {
            return new \WP_Error('ai_file_correction_disabled', 'Correction de fichiers par IA dÃ©sactivÃ©e.');
        }

        $quota = AiSettings::consumeUserRateLimit(
            'teacher_ai',
            (int) get_current_user_id(),
            AiSettings::quota('ouinpo_ai_teacher_per_minute'),
            AiSettings::quota('ouinpo_ai_teacher_per_day')
        );
        if (is_wp_error($quota)) {
            return $quota;
        }

        $context = FileCorrectionBatchService::context_for_batch($batch);
        if (is_wp_error($context)) {
            return $context;
        }

        if (!class_exists('\OuInPo\SegFault\OpenAI') || !method_exists('\OuInPo\SegFault\OpenAI', 'respond')) {
            return new \WP_Error('ai_unavailable', 'Moteur IA indisponible.');
        }

        $wpdb->update($this->table('correction_copies'), ['status' => 'analyzing', 'error_message' => null], ['id' => $copy_id], ['%s', '%s'], ['%d']);
        CorrectionBatchService::update_batch_status((int) $copy['batch_id'], 'analyzing');

        $answer = \OuInPo\SegFault\OpenAI::respond($this->messages($context, $copy, $content), [
            'temperature' => 0.1,
            'max_tokens' => max(2200, AiSettings::maxTokens('ouinpo_ai_practical_ai_max_tokens')),
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ]);

        $json = $this->decode_json($answer);
        if (is_wp_error($json)) {
            $wpdb->update($this->table('correction_copies'), ['status' => 'error', 'error_message' => $json->get_error_message()], ['id' => $copy_id], ['%s', '%s'], ['%d']);
            CorrectionBatchService::update_batch_status((int) $copy['batch_id'], 'error');
            return $json;
        }

        $proposal = $this->validate($json, $context, self::ai_student_ref($copy));
        if (is_wp_error($proposal)) {
            $wpdb->update($this->table('correction_copies'), ['status' => 'error', 'error_message' => $proposal->get_error_message()], ['id' => $copy_id], ['%s', '%s'], ['%d']);
            CorrectionBatchService::update_batch_status((int) $copy['batch_id'], 'error');
            return $proposal;
        }

        $wpdb->update($this->table('correction_copies'), [
            'status' => 'proposal',
            'ai_proposal' => wp_json_encode($proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_message' => null,
        ], ['id' => $copy_id], ['%s', '%s', '%s'], ['%d']);
        CorrectionBatchService::update_batch_status((int) $copy['batch_id'], 'review');

        return $proposal;
    }

    public function validate(array $data, array $context, string $student_ref): array|\WP_Error
    {
        $exercise_max = [];
        foreach ((array) ($context['items'] ?? []) as $item) {
            $exercise_max[(int) $item['exercise_id']] = max(0.0, (float) ($item['points'] ?? 0));
        }

        $items = [];
        $sum = 0.0;
        foreach ((array) ($data['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $exercise_id = (int) ($item['exercise_id'] ?? 0);
            if (!isset($exercise_max[$exercise_id])) {
                return new \WP_Error('invalid_exercise_id', 'Exercice inconnu dans la proposition IA.');
            }
            $max = $exercise_max[$exercise_id];
            $points = min($max, max(0.0, (float) ($item['suggested_points'] ?? 0)));
            $sum += $points;

            $competencies = [];
            foreach ((array) ($item['competencies'] ?? []) as $comp) {
                if (!is_array($comp)) {
                    continue;
                }
                $status = sanitize_text_field((string) ($comp['status'] ?? 'non évalué'));
                if (!in_array($status, self::COMPETENCY_STATUSES, true)) {
                    $status = 'non évalué';
                }
                $competencies[] = [
                    'id' => (int) ($comp['id'] ?? 0),
                    'label' => sanitize_text_field((string) ($comp['label'] ?? '')),
                    'status' => $status,
                    'comment' => sanitize_textarea_field((string) ($comp['comment'] ?? '')),
                ];
            }

            $items[] = [
                'exercise_id' => $exercise_id,
                'exercise_title' => sanitize_text_field((string) ($item['exercise_title'] ?? '')),
                'suggested_points' => round($points, 2),
                'max_points' => round($max, 2),
                'confidence' => max(0.0, min(1.0, (float) ($item['confidence'] ?? 0))),
                'positive_elements' => array_values(array_map('sanitize_text_field', (array) ($item['positive_elements'] ?? []))),
                'errors' => array_values(array_map('sanitize_text_field', (array) ($item['errors'] ?? []))),
                'feedback' => wp_kses_post((string) ($item['feedback'] ?? '')),
                'competencies' => $competencies,
            ];
        }

        if (empty($items)) {
            return new \WP_Error('empty_correction', 'Aucun item de correction valide.');
        }

        $max_total = max(0.0, (float) ($context['max_points'] ?? 0));
        $declared_total = min($max_total, max(0.0, (float) ($data['total']['suggested_points'] ?? $sum)));
        if (abs($declared_total - $sum) > 0.25) {
            $declared_total = $sum;
        }

        return [
            'student_ref' => sanitize_text_field($student_ref),
            'submission_quality' => [
                'readable' => !empty($data['submission_quality']['readable']),
                'complete' => !empty($data['submission_quality']['complete']),
                'confidence' => max(0.0, min(1.0, (float) ($data['submission_quality']['confidence'] ?? 0))),
                'warnings' => array_values(array_map('sanitize_text_field', (array) ($data['submission_quality']['warnings'] ?? []))),
            ],
            'total' => [
                'suggested_points' => round($declared_total, 2),
                'max_points' => round($max_total, 2),
                'confidence' => max(0.0, min(1.0, (float) ($data['total']['confidence'] ?? 0))),
            ],
            'items' => $items,
            'global_feedback' => wp_kses_post((string) ($data['global_feedback'] ?? '')),
            'teacher_review_required' => true,
        ];
    }

    private function messages(array $context, array $copy, string $content): array
    {
        $items = array_map(static function (array $item): array {
            return [
                'exercise_id' => (int) $item['exercise_id'],
                'title' => (string) $item['title'],
                'max_points' => (float) ($item['points'] ?? 0),
                'statement' => wp_strip_all_tags((string) ($item['statement'] ?? '')),
                'solution' => wp_strip_all_tags((string) ($item['solution_html'] ?? '')),
                'competencies' => $item['competencies'] ?? [],
            ];
        }, (array) ($context['items'] ?? []));

        $student_ref = self::ai_student_ref($copy);

        $schema = [
            'student_ref' => $student_ref,
            'submission_quality' => ['readable' => true, 'complete' => true, 'confidence' => 0.8, 'warnings' => []],
            'total' => ['suggested_points' => 0, 'max_points' => (float) ($context['max_points'] ?? 0), 'confidence' => 0.7],
            'items' => [],
            'global_feedback' => '...',
            'teacher_review_required' => true,
        ];

        $warnings = json_decode((string) ($copy['extraction_warnings'] ?? '[]'), true);
        $manifest = json_decode((string) ($copy['file_manifest'] ?? '[]'), true);

        $system = AiSettings::persona('copy_correction', 'ouinpo_ai_persona_teacher')
            . "\n\nTache metier : proposer une correction de rendu numerique a un enseignant, qui valide ensuite. RÃ©ponds uniquement avec un JSON strict. Analyse statique uniquement : ne prÃ©tends jamais avoir exÃ©cutÃ© le code, nâ€™invente pas de tests ni de fichiers absents.";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' =>
                "Contexte : " . (string) ($context['title'] ?? '') . "\n"
                . "BarÃ¨me, Ã©noncÃ©s, solutions et compÃ©tences :\n" . wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
                . "Avertissements de contexte :\n" . wp_json_encode((array) ($context['warnings'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
                . "RÃ©fÃ©rence anonymisÃ©e du rendu : " . $student_ref . "\n"
                . "Manifeste fichiers :\n" . wp_json_encode(is_array($manifest) ? $manifest : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
                . "Avertissements extraction :\n" . wp_json_encode(is_array($warnings) ? $warnings : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
                . "Contenu extrait des fichiers :\n" . $content . "\n\n"
                . "Consignes : signale les fichiers manquants ou incomplets, les incertitudes et les troncatures. Si une exÃ©cution de tests serait nÃ©cessaire, indique-le comme suggestion sans inventer le rÃ©sultat. Statuts compÃ©tences autorisÃ©s : acquis, Ã  renforcer, non acquis, non Ã©valuÃ©.\n"
                . "Format JSON attendu :\n" . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ],
        ];
    }

    private function decode_json(string $answer): array|\WP_Error
    {
        $answer = trim($answer);
        $data = $answer !== '' ? json_decode($answer, true) : null;
        if (!is_array($data) && preg_match('/\{.*\}/s', $answer, $m)) {
            $data = json_decode($m[0], true);
        }
        return is_array($data) ? $data : new \WP_Error('invalid_json', 'RÃ©ponse IA JSON invalide.');
    }

    private function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    private static function ai_student_ref(array $copy): string
    {
        $id = max(1, (int) ($copy['id'] ?? 1));
        return 'anonyme-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }
}
