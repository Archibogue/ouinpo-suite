<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class AiExerciseGenerator
{
    private const ACTION_INSTRUCTIONS = [
        'generate' => 'Crée une proposition nouvelle.',
        'regenerate' => 'Régénère entièrement une proposition différente.',
        'variant' => 'Propose une variante du même objectif pédagogique.',
        'simplify' => 'Simplifie la tâche tout en gardant la compétence visée.',
        'harder' => 'Rends la tâche plus difficile sans sortir du programme du niveau.',
    ];

    public function generate(array $request): array|\WP_Error
    {
        if (!AiSettings::enabled_for_usage('pedagogical_suggestions')) {
            return new \WP_Error('ai_disabled', 'Usage IA désactivé dans les réglages.');
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

        $context = $this->build_context($request);
        if (is_wp_error($context)) {
            return $context;
        }

        if (!class_exists('\OuInPo\SegFault\OpenAI') || !method_exists('\OuInPo\SegFault\OpenAI', 'respond')) {
            return new \WP_Error('ai_unavailable', 'Moteur IA indisponible.');
        }

        $messages = $this->build_messages($context);
        $answer = $this->call_ai($messages, [
            'temperature' => 0.35,
            'max_tokens' => max(2600, AiSettings::maxTokens('ouinpo_ai_practical_ai_max_tokens')),
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ], 'exercise_generation');

        $json = $this->decode_json_answer($answer);
        if (is_wp_error($json)) {
            AiSettings::debug_log('AI JSON parse failed', [
                'stage' => 'exercise_generation',
                'provider' => (string) AiSettings::get('ouinpo_ai_logged_provider'),
                'raw_length' => strlen($answer),
                'truncated' => $this->looks_truncated($answer) ? 'yes' : 'no',
                'json_error' => $json->get_error_message(),
                'excerpt' => AiJsonResponseParser::excerpt($answer, 1000),
            ]);
            $json = $this->repair_json($answer, 'exercise_generation');
            if (is_wp_error($json)) {
                return $json;
            }
        }

        return $this->validate_ai_json($json, $context);
    }

    private function build_context(array $request): array|\WP_Error
    {
        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';
        $level_id = (int) ($request['level_id'] ?? 0);
        $domain_slug = sanitize_key((string) ($request['domain_slug'] ?? ''));
        $competency_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($request['competency_ids'] ?? [])))));
        $difficulty_slug = sanitize_key((string) ($request['difficulty_slug'] ?? ''));
        $exercise_type = sanitize_key((string) ($request['exercise_type'] ?? 'classic'));
        $estimated = max(1, min(240, (int) ($request['estimated_minutes'] ?? 20)));
        $free_prompt = sanitize_textarea_field((string) ($request['free_prompt'] ?? ''));
        $action = sanitize_key((string) ($request['action'] ?? 'generate'));
        $previous = is_array($request['previous'] ?? null) ? $request['previous'] : [];

        if (!isset(self::ACTION_INSTRUCTIONS[$action])) {
            $action = 'generate';
        }

        if (!in_array($exercise_type, array_keys(ExerciseInsertService::exercise_types()), true)) {
            $exercise_type = 'classic';
        }

        $level = $wpdb->get_row($wpdb->prepare(
            "SELECT id, slug, label FROM {$p}school_levels WHERE id = %d LIMIT 1",
            $level_id
        ), ARRAY_A);
        if (!$level) {
            return new \WP_Error('invalid_level', 'Niveau invalide.');
        }

        $difficulty = $wpdb->get_row($wpdb->prepare(
            "SELECT id, slug, label FROM {$p}difficulties WHERE slug = %s LIMIT 1",
            $difficulty_slug
        ), ARRAY_A);
        if (!$difficulty) {
            return new \WP_Error('invalid_difficulty', 'Difficulté invalide.');
        }

        if (empty($competency_ids)) {
            return new \WP_Error('missing_competencies', 'Sélectionne au moins une compétence.');
        }

        $placeholders = implode(',', array_fill(0, count($competency_ids), '%d'));
        $competencies = $wpdb->get_results($wpdb->prepare(
            "SELECT id, domain_id, domain, domain_slug, competency, label, level, track, slug
             FROM {$p}competencies
             WHERE active = 1 AND id IN ({$placeholders})
             ORDER BY domain, id",
            $competency_ids
        ), ARRAY_A);

        if (count($competencies) !== count($competency_ids)) {
            return new \WP_Error('invalid_competencies', 'Compétences invalides.');
        }

        if ($domain_slug !== '') {
            foreach ($competencies as $competency) {
                $competency_domain_slug = (string) ($competency['domain_slug'] ?? '');
                if ($competency_domain_slug === '') {
                    $competency_domain_slug = sanitize_title((string) ($competency['domain'] ?? ''));
                }
                if ($competency_domain_slug !== $domain_slug) {
                    return new \WP_Error('domain_mismatch', 'Les compétences doivent appartenir au domaine choisi.');
                }
            }
        }

        $domain_label = (string) ($competencies[0]['domain'] ?? $domain_slug);

        return [
            'level' => $level,
            'difficulty' => $difficulty,
            'domain_slug' => $domain_slug,
            'domain_label' => $domain_label,
            'competencies' => $competencies,
            'exercise_type' => $exercise_type,
            'estimated_minutes' => $estimated,
            'free_prompt' => $free_prompt,
            'action' => $action,
            'previous' => $previous,
        ];
    }

    private function build_messages(array $context): array
    {
        $competency_lines = array_map(static function (array $competency): string {
            $label = trim((string) ($competency['label'] ?: $competency['competency']));
            return '- ID ' . (int) $competency['id'] . ' : ' . $label;
        }, $context['competencies']);

        $previous = '';
        if (!empty($context['previous'])) {
            $previous = "\nProposition précédente à transformer, si utile :\n"
                . wp_json_encode($context['previous'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $schema = [
            'title' => '...',
            'slug' => '...',
            'level' => (string) $context['level']['label'],
            'difficulty' => (string) $context['difficulty']['label'],
            'estimated_minutes' => (int) $context['estimated_minutes'],
            'domains' => [['id' => 0, 'label' => '...']],
            'competencies' => [['id' => 0, 'label' => '...']],
            'statement_html' => '<p>...</p>',
            'hints' => [
                ['rank' => 1, 'html' => '<p>...</p>'],
                ['rank' => 2, 'html' => '<p>...</p>'],
                ['rank' => 3, 'html' => '<p>...</p>'],
            ],
            'solution_html' => '<p>...</p>',
            'pedagogical_rationale' => '...',
            'program_guardrails' => ['in_program' => true, 'warnings' => []],
        ];

        $system = "Tu aides un enseignant NSI à créer un exercice. Réponds uniquement avec un objet JSON valide. Aucun Markdown. Aucun bloc ```json. Aucune explication hors JSON. N'invente pas d'ID : réutilise seulement les IDs fournis. Ne demande ni n'utilise aucune donnée personnelle d'élève. Le contenu doit rester dans le programme du niveau choisi. Toutes les chaînes HTML et tout code doivent être des chaînes JSON valides avec guillemets correctement échappés.";

        $user = "Action demandée : " . self::ACTION_INSTRUCTIONS[$context['action']] . "\n"
            . "Niveau : {$context['level']['label']} ({$context['level']['slug']})\n"
            . "Domaine BO : {$context['domain_label']} ({$context['domain_slug']})\n"
            . "Compétences BO exactes :\n" . implode("\n", $competency_lines) . "\n"
            . "Difficulté : {$context['difficulty']['label']} ({$context['difficulty']['slug']})\n"
            . "Type d'exercice : {$context['exercise_type']}\n"
            . "Durée estimée : {$context['estimated_minutes']} minutes\n"
            . "Consigne libre de l'enseignant : " . ($context['free_prompt'] !== '' ? $context['free_prompt'] : 'Aucune') . "\n"
            . "Contraintes : produire un énoncé HTML clair, trois indices progressifs, une solution détaillée HTML, et une justification pédagogique courte. Les notions hors programme doivent être signalées dans program_guardrails.warnings et in_program doit être false si nécessaire. Interdiction de retourner une liste Markdown, du texte avant/après le JSON, ou plusieurs objets JSON.\n"
            . "Réponds uniquement avec un objet JSON valide. Aucun Markdown. Aucun bloc ```json. Aucune explication hors JSON.\n"
            . "Format JSON attendu :\n"
            . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . $previous;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    private function decode_json_answer(string $answer): array|\WP_Error
    {
        return AiJsonResponseParser::parse($answer, 'object');
    }

    private function repair_json(string $raw_answer, string $stage): array|\WP_Error
    {
        AiSettings::debug_log('AI JSON repair requested', [
            'stage' => $stage,
            'provider' => (string) AiSettings::get('ouinpo_ai_logged_provider'),
            'raw_length' => strlen($raw_answer),
            'truncated' => $this->looks_truncated($raw_answer) ? 'yes' : 'no',
        ]);

        $schema = [
            'title' => '...',
            'slug' => '...',
            'level' => '...',
            'difficulty' => '...',
            'estimated_minutes' => 20,
            'domains' => [['id' => 0, 'label' => '...']],
            'competencies' => [['id' => 0, 'label' => '...']],
            'statement_html' => '<p>...</p>',
            'hints' => [
                ['rank' => 1, 'html' => '<p>...</p>'],
                ['rank' => 2, 'html' => '<p>...</p>'],
                ['rank' => 3, 'html' => '<p>...</p>'],
            ],
            'solution_html' => '<p>...</p>',
            'pedagogical_rationale' => '...',
            'program_guardrails' => ['in_program' => true, 'warnings' => []],
        ];

        $repaired = $this->call_ai([
            ['role' => 'system', 'content' => 'Répare ce contenu en JSON strict conforme au schéma. Ne renvoie aucun texte autour. Aucun Markdown. Aucun bloc ```json.'],
            ['role' => 'user', 'content' => "Schéma attendu :\n" . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\nContenu invalide :\n" . $raw_answer],
        ], [
            'temperature' => 0,
            'max_tokens' => max(2600, AiSettings::maxTokens('ouinpo_ai_practical_ai_max_tokens')),
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ], 'json_repair');

        $json = $this->decode_json_answer($repaired);
        if (is_wp_error($json)) {
            AiSettings::debug_log('AI JSON repair failed', [
                'stage' => 'json_repair',
                'provider' => (string) AiSettings::get('ouinpo_ai_logged_provider'),
                'raw_length' => strlen($raw_answer),
                'truncated' => $this->looks_truncated($raw_answer) ? 'yes' : 'no',
                'json_error' => $json->get_error_message(),
                'excerpt' => AiJsonResponseParser::excerpt($raw_answer, 1000),
            ]);

            return new \WP_Error('invalid_json', 'La réponse IA n’a pas pu être interprétée. Une tentative de réparation automatique a été effectuée. La génération a échoué. Essayez de réduire le nombre d’exercices générés ou de générer les nouveaux exercices un par un.');
        }

        return $json;
    }

    private function call_ai(array $messages, array $options, string $stage): string
    {
        $answer = \OuInPo\SegFault\OpenAI::respond($messages, $options);
        AiSettings::debug_log('AI response received', [
            'stage' => $stage,
            'provider' => (string) AiSettings::get('ouinpo_ai_logged_provider'),
            'raw_length' => strlen($answer),
            'truncated' => $this->looks_truncated($answer) ? 'yes' : 'no',
        ]);

        return $answer;
    }

    private function looks_truncated(string $answer): bool
    {
        $trimmed = rtrim($answer);
        return $trimmed !== '' && !str_ends_with($trimmed, '}') && !str_ends_with($trimmed, ']') && strlen($trimmed) > 1000;
    }

    private function validate_ai_json(array $data, array $context): array|\WP_Error
    {
        $required = ['title', 'statement_html', 'hints', 'solution_html', 'program_guardrails'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                return new \WP_Error('missing_ai_field', 'Champ IA manquant : ' . $key);
            }
        }

        $warnings = (array) ($data['program_guardrails']['warnings'] ?? []);
        if (empty($data['program_guardrails']['in_program'])) {
            return new \WP_Error('out_of_program', 'La proposition IA signale un contenu hors programme : ' . implode(' ', array_map('sanitize_text_field', $warnings)));
        }

        $competency_ids = array_map(static fn(array $c): int => (int) $c['id'], $context['competencies']);

        $hints = [];
        foreach ((array) $data['hints'] as $index => $hint) {
            if (!is_array($hint)) {
                continue;
            }
            $rank = (int) ($hint['rank'] ?? ($index + 1));
            $html = ExerciseInsertService::clean_html((string) ($hint['html'] ?? ''));
            if ($rank >= 1 && $rank <= 3 && $html !== '') {
                $hints[$rank] = ['rank' => $rank, 'html' => $html];
            }
        }

        if (count($hints) < 3) {
            return new \WP_Error('invalid_hints', 'La proposition IA doit contenir trois indices.');
        }
        ksort($hints);

        return [
            'title' => sanitize_text_field((string) $data['title']),
            'slug' => sanitize_title((string) ($data['slug'] ?? '')),
            'level' => (string) $context['level']['label'],
            'level_id' => (int) $context['level']['id'],
            'difficulty' => (string) $context['difficulty']['label'],
            'difficulty_id' => (int) $context['difficulty']['id'],
            'difficulty_slug' => (string) $context['difficulty']['slug'],
            'estimated_minutes' => max(1, min(240, (int) ($data['estimated_minutes'] ?? $context['estimated_minutes']))),
            'exercise_type' => $context['exercise_type'],
            'domains' => [
                [
                    'id' => 0,
                    'slug' => $context['domain_slug'],
                    'label' => $context['domain_label'],
                ],
            ],
            'competencies' => array_map(static function (array $competency): array {
                return [
                    'id' => (int) $competency['id'],
                    'label' => trim((string) ($competency['label'] ?: $competency['competency'])),
                ];
            }, $context['competencies']),
            'competency_ids' => $competency_ids,
            'statement_html' => ExerciseInsertService::clean_html((string) $data['statement_html']),
            'hints' => array_values($hints),
            'solution_html' => ExerciseInsertService::clean_html((string) $data['solution_html']),
            'pedagogical_rationale' => sanitize_textarea_field((string) ($data['pedagogical_rationale'] ?? '')),
            'program_guardrails' => [
                'in_program' => true,
                'warnings' => array_values(array_map('sanitize_text_field', $warnings)),
            ],
        ];
    }
}
