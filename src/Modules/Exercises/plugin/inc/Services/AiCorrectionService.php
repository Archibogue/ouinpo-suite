<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class AiCorrectionService
{
    private const COMPETENCY_STATUSES = ['acquis', 'à renforcer', 'non acquis', 'non évalué'];

    public function analyze(int $copy_id): array|\WP_Error
    {
        global $wpdb;
        $copy = CorrectionBatchService::get_copy($copy_id);
        if (!$copy) {
            return new \WP_Error('copy_not_found', 'Copie introuvable.');
        }
        if ((string) ($copy['source_type'] ?? 'scan') !== 'scan') {
            return new \WP_Error('invalid_correction_source', 'Ce rendu relÃ¨ve du workflow fichiers.');
        }

        $batch = CorrectionBatchService::get_batch((int) $copy['batch_id']);
        if (!$batch) {
            return new \WP_Error('batch_not_found', 'Lot introuvable.');
        }

        $text = trim((string) ($copy['ocr_text'] ?? ''));
        if ($text === '') {
            return new \WP_Error('ocr_missing', CopyOcrService::unavailable_message());
        }

        if (!AiSettings::enabled_for_usage('pedagogical_suggestions') || (int) AiSettings::get('ouinpo_ai_correction_scans_enabled') !== 1) {
            return new \WP_Error('ai_correction_disabled', 'Correction de scans par IA désactivée.');
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

        $context = CorrectionBatchService::assessment_context((int) $batch['assessment_id']);
        if (is_wp_error($context)) {
            return $context;
        }
        $context['_batch_group_id'] = (int) ($batch['group_id'] ?? 0);

        if (!class_exists('\OuInPo\SegFault\OpenAI') || !method_exists('\OuInPo\SegFault\OpenAI', 'respond')) {
            return new \WP_Error('ai_unavailable', 'Moteur IA indisponible.');
        }

        $wpdb->update($this->table('correction_copies'), ['status' => 'analyzing', 'error_message' => null], ['id' => $copy_id], ['%s', '%s'], ['%d']);
        CorrectionBatchService::update_batch_status((int) $copy['batch_id'], 'analyzing');

        $answer = \OuInPo\SegFault\OpenAI::respond($this->messages($context, $copy, $text), [
            'temperature' => 0.15,
            'max_tokens' => max(1800, AiSettings::maxTokens('ouinpo_ai_practical_ai_max_tokens')),
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ]);

        $json = $this->decode_json($answer);
        if (is_wp_error($json)) {
            $wpdb->update($this->table('correction_copies'), ['status' => 'error', 'error_message' => $json->get_error_message()], ['id' => $copy_id], ['%s', '%s'], ['%d']);
            CorrectionBatchService::update_batch_status((int) $copy['batch_id'], 'error');
            return $json;
        }

        $proposal = $this->validate($json, $context, (string) $copy['student_ref']);
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

    private function messages(array $context, array $copy, string $copy_text): array
    {
        $batch_group_id = (int) ($context['_batch_group_id'] ?? ($context['assessment']['group_id'] ?? 0));
        $student_user_id = (int) ($copy['student_user_id'] ?? 0);
        $items = array_map(static function (array $item) use ($batch_group_id, $student_user_id): array {
            $pedagogical_context = AiPedagogicalContextService::forCorrectionItem($item, [
                'user_id' => $student_user_id,
                'group_id' => $batch_group_id,
            ]);
            $course_debug = [];
            $course_context = AiPedagogicalContextService::courseContext(
                implode("\n", array_values(array_filter([
                    (string) ($item['title'] ?? ''),
                    wp_strip_all_tags((string) ($item['statement'] ?? '')),
                    implode("\n", array_map(static function ($competency): string {
                        return is_array($competency) ? (string) ($competency['label'] ?? '') : '';
                    }, (array) ($item['competencies'] ?? []))),
                ]))),
                $pedagogical_context,
                2,
                650,
                $course_debug
            );

            return [
                'exercise_id' => (int) $item['exercise_id'],
                'title' => (string) $item['title'],
                'max_points' => (float) ($item['points'] ?? 0),
                'statement' => wp_strip_all_tags((string) ($item['statement'] ?? '')),
                'solution' => wp_strip_all_tags((string) ($item['solution_html'] ?? '')),
                'competencies' => $item['competencies'] ?? [],
                'pedagogical_context' => AiPedagogicalContextService::promptPayload($pedagogical_context),
                'course_context' => $course_context,
                'program_guardrail' => AiPedagogicalContextService::programGuardrail($pedagogical_context),
            ];
        }, $context['items']);

        $schema = [
            'student_ref' => (string) $copy['student_ref'],
            'copy_quality' => ['readable' => true, 'confidence' => 0.8, 'warnings' => []],
            'total' => ['suggested_points' => 0, 'max_points' => (float) $context['max_points'], 'confidence' => 0.7],
            'items' => [],
            'global_feedback' => '...',
            'teacher_review_required' => true,
        ];

        $system = AiSettings::persona('copy_correction', 'ouinpo_ai_persona_teacher')
            . "\n\nTache metier : proposer une correction de copie a un enseignant, qui valide ensuite. Réponds uniquement avec un JSON strict. N’invente pas ce qui est illisible : baisse la confiance et signale les passages à vérifier.";

        return [
            ['role' => 'system', 'content' => $system . "\n\nContexte pedagogique : chaque item peut contenir pedagogical_context, course_context et program_guardrail. Corrige selon le niveau attendu configure par cycle et ordre de niveau ; utilise le niveau eleve ou classe pour adapter le feedback, pas pour modifier les attendus de l enonce."],
            ['role' => 'user', 'content' =>
                "Devoir : " . (string) ($context['assessment']['title'] ?? '') . "\n"
                . "Barème et exercices :\n" . wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
                . "Référence anonymisée de la copie : " . (string) $copy['student_ref'] . "\n"
                . "Texte OCR ou saisi manuellement :\n" . $copy_text . "\n\n"
                . "Statuts compétences autorisés : acquis, à renforcer, non acquis, non évalué.\n"
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
        return is_array($data) ? $data : new \WP_Error('invalid_json', 'Réponse IA JSON invalide.');
    }

    public function validate(array $data, array $context, string $student_ref): array|\WP_Error
    {
        $exercise_max = [];
        foreach ($context['items'] as $item) {
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
                $status = (string) ($comp['status'] ?? 'non évalué');
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

        $max_total = max(0.0, (float) $context['max_points']);
        $declared_total = min($max_total, max(0.0, (float) ($data['total']['suggested_points'] ?? $sum)));
        if (abs($declared_total - $sum) > 0.25) {
            $declared_total = $sum;
        }

        return [
            'student_ref' => sanitize_text_field($student_ref),
            'copy_quality' => [
                'readable' => !empty($data['copy_quality']['readable']),
                'confidence' => max(0.0, min(1.0, (float) ($data['copy_quality']['confidence'] ?? 0))),
                'warnings' => array_values(array_map('sanitize_text_field', (array) ($data['copy_quality']['warnings'] ?? []))),
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

    private function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }
}
