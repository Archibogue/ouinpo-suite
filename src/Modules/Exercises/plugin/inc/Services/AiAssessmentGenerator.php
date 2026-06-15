<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class AiAssessmentGenerator
{
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

        $answer = $this->call_ai($this->messages($context), [
            'temperature' => 0.25,
            'max_tokens' => max(2600, AiSettings::maxTokens('ouinpo_ai_practical_ai_max_tokens')),
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ], 'assessment_generation');

        $json = $this->decode_json($answer);
        if (is_wp_error($json)) {
            AiSettings::debug_log('AI JSON parse failed', [
                'stage' => 'assessment_generation',
                'provider' => (string) AiSettings::get('ouinpo_ai_logged_provider'),
                'raw_length' => strlen($answer),
                'truncated' => $this->looks_truncated($answer) ? 'yes' : 'no',
                'json_error' => $json->get_error_message(),
                'excerpt' => AiJsonResponseParser::excerpt($answer, 1000),
            ]);
            $json = $this->repair_json($answer, $context);
            if (is_wp_error($json)) {
                return $json;
            }
        }

        return $this->validate($json, $context);
    }

    private function build_context(array $request): array|\WP_Error
    {
        global $wpdb;

        $level_id = (int) ($request['level_id'] ?? 0);
        $group_id = (int) ($request['group_id'] ?? 0);
        $target_minutes = max(10, min(240, (int) ($request['target_minutes'] ?? 90)));
        $items_count = max(1, min(12, (int) ($request['items_count'] ?? 4)));
        $difficulty_slug = sanitize_key((string) ($request['difficulty_slug'] ?? ''));
        $existing_ratio = max(0, min(100, (int) ($request['existing_ratio'] ?? 70)));
        $new_ratio = max(0, min(100, (int) ($request['new_ratio'] ?? (100 - $existing_ratio))));
        $domain_slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) ($request['domain_slugs'] ?? [])))));
        $competency_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($request['competency_ids'] ?? [])))));
        $free_constraints = sanitize_textarea_field((string) ($request['free_constraints'] ?? ''));

        if ($group_id <= 0) {
            return new \WP_Error('missing_group', 'Classe requise.');
        }

        $level = $this->level($level_id, $group_id);
        if (!$level) {
            return new \WP_Error('missing_level', 'Niveau requis.');
        }

        $difficulty = $this->difficulty($difficulty_slug);
        if (!$difficulty) {
            return new \WP_Error('invalid_difficulty', 'Difficulté invalide.');
        }

        $competencies = $this->competencies($competency_ids, $domain_slugs, (int) $level['id']);
        if (empty($competencies)) {
            return new \WP_Error('missing_competencies', 'Sélectionne au moins une compétence ou un domaine avec compétences.');
        }

        $candidate_exercises = $this->candidate_exercises((int) $level['id'], $domain_slugs, array_column($competencies, 'id'), $difficulty_slug);
        if (empty($candidate_exercises) && $existing_ratio > 0) {
            $existing_ratio = 0;
            $new_ratio = 100;
        }

        $kpi = ClassKpiService::build($group_id, (int) $level['id'], array_column($competencies, 'id'), $domain_slugs);

        return compact(
            'group_id',
            'level',
            'target_minutes',
            'items_count',
            'difficulty',
            'existing_ratio',
            'new_ratio',
            'domain_slugs',
            'competencies',
            'candidate_exercises',
            'kpi',
            'free_constraints'
        );
    }

    private function messages(array $context): array
    {
        $candidate_lines = array_map(static function (array $row): string {
            return '- ID ' . (int) $row['id'] . ' | ' . $row['title'] . ' | ' . ($row['difficulty'] ?: 'difficulté inconnue') . ' | ' . (int) $row['estimated_minutes'] . ' min | compétences: ' . implode(', ', $row['competency_ids']);
        }, array_slice($context['candidate_exercises'], 0, 80));

        $competency_lines = array_map(static function (array $row): string {
            return '- ID ' . (int) $row['id'] . ' : ' . ($row['label'] ?: $row['competency']);
        }, $context['competencies']);

        $schema = [
            'title' => '...',
            'target_minutes' => $context['target_minutes'],
            'estimated_minutes' => $context['target_minutes'],
            'items' => [
                [
                    'kind' => 'existing_exercise',
                    'exercise_id' => 123,
                    'title' => '...',
                    'domain_labels' => ['...'],
                    'competency_labels' => ['...'],
                    'difficulty' => 'confirmé',
                    'estimated_minutes' => 20,
                    'suggested_points' => 5,
                    'rationale' => '...',
                ],
                [
                    'kind' => 'new_ai_exercise_request',
                    'title_hint' => '...',
                    'level' => $context['level']['label'],
                    'difficulty' => $context['difficulty']['label'],
                    'difficulty_slug' => $context['difficulty']['slug'],
                    'estimated_minutes' => 20,
                    'domain_ids' => [],
                    'domain_slugs' => $context['domain_slugs'],
                    'competency_ids' => [123],
                    'suggested_points' => 5,
                    'teacher_prompt' => 'Créer un exercice sur ...',
                    'rationale' => '...',
                ],
            ],
            'global_rationale' => '...',
            'kpi_summary_used' => [
                'under_tested_competencies' => [],
                'recently_tested_competencies' => [],
                'warnings' => [],
            ],
        ];

        $system = "Tache metier : composer un devoir pour un enseignant NSI. Réponds uniquement avec un objet JSON valide. Aucun Markdown. Aucun bloc ```json. Aucune explication hors JSON. N'utilise aucun nom d'élève. N'invente aucun ID d'exercice : pour kind=existing_exercise, choisis uniquement parmi les IDs candidats transmis. Pour kind=new_ai_exercise_request, fournis uniquement une demande légère de nouvel exercice, jamais l'énoncé complet, jamais les indices, jamais la solution.";
        $system = AiSettings::persona('assessment_generation', 'ouinpo_ai_persona_teacher') . "\n\n" . $system;

        $user = "Contexte non nominatif : groupe #{$context['group_id']}, niveau {$context['level']['label']}.\n"
            . "Durée cible : {$context['target_minutes']} min. Nombre d'exercices souhaité : {$context['items_count']}.\n"
            . "Difficulté globale : {$context['difficulty']['label']} ({$context['difficulty']['slug']}).\n"
            . "Part souhaitée existants/nouveaux IA : {$context['existing_ratio']}% / {$context['new_ratio']}%.\n"
            . "Compétences sélectionnées :\n" . implode("\n", $competency_lines) . "\n\n"
            . "Exercices existants candidats :\n" . (empty($candidate_lines) ? 'Aucun candidat transmis.' : implode("\n", $candidate_lines)) . "\n\n"
            . "KPI agrégés de classe, sans données nominatives :\n" . wp_json_encode($context['kpi'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
            . "Contraintes libres : " . ($context['free_constraints'] !== '' ? $context['free_constraints'] : 'Aucune') . "\n\n"
            . "Pour les nouveaux exercices, ne génère pas de contenu complet. Crée seulement des items kind=new_ai_exercise_request. Interdiction de retourner une liste Markdown, du texte avant/après le JSON, ou plusieurs objets JSON. Les chaînes doivent être du JSON valide avec guillemets échappés si nécessaire.\n"
            . "Réponds uniquement avec un objet JSON valide. Aucun Markdown. Aucun bloc ```json. Aucune explication hors JSON.\n"
            . "Format JSON attendu :\n" . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    private function decode_json(string $answer): array|\WP_Error
    {
        return AiJsonResponseParser::parse($answer, 'object');
    }

    private function repair_json(string $raw_answer, array $context): array|\WP_Error
    {
        AiSettings::debug_log('AI JSON repair requested', [
            'stage' => 'assessment_generation',
            'provider' => (string) AiSettings::get('ouinpo_ai_logged_provider'),
            'raw_length' => strlen($raw_answer),
            'truncated' => $this->looks_truncated($raw_answer) ? 'yes' : 'no',
        ]);

        $schema = [
            'title' => '...',
            'target_minutes' => $context['target_minutes'],
            'estimated_minutes' => $context['target_minutes'],
            'items' => [
                ['kind' => 'existing_exercise', 'exercise_id' => 123, 'suggested_points' => 5, 'estimated_minutes' => 20, 'rationale' => '...'],
                [
                    'kind' => 'new_ai_exercise_request',
                    'title_hint' => '...',
                    'level' => $context['level']['label'],
                    'difficulty' => $context['difficulty']['label'],
                    'difficulty_slug' => $context['difficulty']['slug'],
                    'estimated_minutes' => 20,
                    'domain_ids' => [],
                    'domain_slugs' => $context['domain_slugs'],
                    'competency_ids' => [123],
                    'suggested_points' => 5,
                    'teacher_prompt' => 'Créer un exercice sur ...',
                    'rationale' => '...',
                ],
            ],
            'global_rationale' => '...',
            'kpi_summary_used' => ['under_tested_competencies' => [], 'recently_tested_competencies' => [], 'warnings' => []],
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

        $json = $this->decode_json($repaired);
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

    private function validate(array $data, array $context): array|\WP_Error
    {
        $candidate_ids = array_map('intval', array_column($context['candidate_exercises'], 'id'));
        $items = [];

        foreach ((array) ($data['items'] ?? []) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $kind = sanitize_key((string) ($item['kind'] ?? ''));
            $points = max(0.25, min(40, (float) ($item['suggested_points'] ?? 5)));

            if ($kind === 'existing_exercise') {
                $exercise_id = (int) ($item['exercise_id'] ?? 0);
                if (!in_array($exercise_id, $candidate_ids, true)) {
                    return new \WP_Error('invalid_exercise_id', 'L’IA a proposé un ID d’exercice non autorisé.');
                }
                $candidate = $this->candidate_by_id($context['candidate_exercises'], $exercise_id);
                $items[] = [
                    'kind' => 'existing_exercise',
                    'exercise_id' => $exercise_id,
                    'title' => $candidate['title'] ?? sanitize_text_field((string) ($item['title'] ?? '')),
                    'domain_labels' => $candidate['domain_labels'] ?? [],
                    'competency_labels' => $candidate['competency_labels'] ?? [],
                    'difficulty' => $candidate['difficulty'] ?? sanitize_text_field((string) ($item['difficulty'] ?? '')),
                    'estimated_minutes' => (int) ($candidate['estimated_minutes'] ?? $item['estimated_minutes'] ?? 20),
                    'suggested_points' => $points,
                    'rationale' => sanitize_textarea_field((string) ($item['rationale'] ?? '')),
                ];
                continue;
            }

            if ($kind === 'new_ai_exercise_request' || ($kind === 'new_ai_exercise' && is_array($item['exercise_draft'] ?? null))) {
                $request = $kind === 'new_ai_exercise_request'
                    ? $this->normalize_new_request($item, $context, $points)
                    : $this->new_request_from_legacy_draft($item, $context, $points);
                if (is_wp_error($request)) {
                    return $request;
                }
                $items[] = [
                    'kind' => 'new_ai_exercise_request',
                    'exercise_request' => $request,
                    'estimated_minutes' => (int) $request['estimated_minutes'],
                    'suggested_points' => $points,
                    'rationale' => (string) ($request['rationale'] ?? ''),
                ];
            }
        }

        if (empty($items)) {
            return new \WP_Error('empty_items', 'La proposition IA ne contient aucun exercice valide.');
        }

        $estimated = max(1, (int) ($data['estimated_minutes'] ?? array_sum(array_column($items, 'estimated_minutes'))));
        $target = (int) $context['target_minutes'];
        $warnings = [];
        if (abs($estimated - $target) > max(15, (int) round($target * 0.25))) {
            $warnings[] = 'Durée estimée éloignée de la durée cible.';
        }

        return [
            'title' => sanitize_text_field((string) ($data['title'] ?? 'Devoir NSI')),
            'target_minutes' => $target,
            'estimated_minutes' => $estimated,
            'items' => $items,
            'global_rationale' => sanitize_textarea_field((string) ($data['global_rationale'] ?? '')),
            'kpi_summary_used' => is_array($data['kpi_summary_used'] ?? null) ? $data['kpi_summary_used'] : [],
            'warnings' => $warnings,
            'kpi' => $context['kpi'],
        ];
    }

    private function normalize_new_request(array $item, array $context, float $points): array|\WP_Error
    {
        $allowed_competencies = array_map('intval', array_column($context['competencies'], 'id'));
        $competency_ids = array_values(array_intersect(
            array_map('intval', (array) ($item['competency_ids'] ?? $allowed_competencies)),
            $allowed_competencies
        ));
        if (empty($competency_ids)) {
            return new \WP_Error('invalid_competencies', 'La demande de nouvel exercice contient des compétences invalides.');
        }

        $allowed_domain_slugs = array_values(array_unique(array_filter(array_map(static function (array $competency): string {
            return sanitize_title((string) ($competency['domain_slug'] ?? $competency['domain'] ?? ''));
        }, $context['competencies']))));
        $domain_slugs = array_values(array_intersect(
            array_map('sanitize_title', (array) ($item['domain_slugs'] ?? $context['domain_slugs'] ?? [])),
            $allowed_domain_slugs
        ));
        if (empty($domain_slugs) && !empty($allowed_domain_slugs)) {
            $domain_slugs = [$allowed_domain_slugs[0]];
        }

        $allowed_domain_ids = array_values(array_unique(array_filter(array_map('intval', array_column($context['competencies'], 'domain_id')))));
        $domain_ids = array_values(array_intersect(array_map('intval', (array) ($item['domain_ids'] ?? [])), $allowed_domain_ids));

        $minutes = max(1, min(240, (int) ($item['estimated_minutes'] ?? 20)));
        $teacher_prompt = sanitize_textarea_field((string) ($item['teacher_prompt'] ?? ''));
        $title_hint = sanitize_text_field((string) ($item['title_hint'] ?? 'Exercice IA'));
        if ($teacher_prompt === '') {
            $teacher_prompt = 'Créer un exercice : ' . $title_hint;
        }

        return [
            'kind' => 'new_ai_exercise_request',
            'title_hint' => $title_hint,
            'level' => (string) $context['level']['label'],
            'level_id' => (int) $context['level']['id'],
            'difficulty' => (string) $context['difficulty']['label'],
            'difficulty_slug' => (string) $context['difficulty']['slug'],
            'estimated_minutes' => $minutes,
            'domain_ids' => $domain_ids,
            'domain_slugs' => $domain_slugs,
            'competency_ids' => $competency_ids,
            'suggested_points' => max(0.25, min(40, $points)),
            'teacher_prompt' => $teacher_prompt,
            'rationale' => sanitize_textarea_field((string) ($item['rationale'] ?? '')),
        ];
    }

    private function new_request_from_legacy_draft(array $item, array $context, float $points): array|\WP_Error
    {
        $draft = (array) ($item['exercise_draft'] ?? []);
        return $this->normalize_new_request([
            'title_hint' => (string) ($draft['title'] ?? 'Exercice IA'),
            'estimated_minutes' => (int) ($draft['estimated_minutes'] ?? 20),
            'domain_slugs' => $context['domain_slugs'],
            'competency_ids' => array_map('intval', array_column($context['competencies'], 'id')),
            'suggested_points' => $points,
            'teacher_prompt' => trim((string) ($draft['pedagogical_rationale'] ?? '')) ?: (string) ($draft['title'] ?? 'Créer un exercice'),
            'rationale' => sanitize_textarea_field((string) ($item['rationale'] ?? '')),
        ], $context, $points);
    }

    private function level(int $level_id, int $group_id): ?array
    {
        global $wpdb;
        if ($level_id <= 0) {
            $level_id = (int) $wpdb->get_var($wpdb->prepare("SELECT school_level_id FROM " . $wpdb->prefix . "ouin_exo_groups WHERE id = %d", $group_id));
        }
        return $level_id > 0 ? ($wpdb->get_row($wpdb->prepare("SELECT id, slug, label FROM " . $wpdb->prefix . "ouin_exo_school_levels WHERE id = %d", $level_id), ARRAY_A) ?: null) : null;
    }

    private function difficulty(string $slug): ?array
    {
        global $wpdb;
        $slug = $slug ?: 'confirme';
        return $wpdb->get_row($wpdb->prepare("SELECT id, slug, label FROM " . $wpdb->prefix . "ouin_exo_difficulties WHERE slug = %s", $slug), ARRAY_A) ?: null;
    }

    private function competencies(array $ids, array $domains, int $level_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ouin_exo_competencies';
        $where = ['active = 1'];
        $args = [];
        if ($ids) {
            $where[] = 'id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';
            array_push($args, ...$ids);
        }
        if ($domains) {
            $where[] = 'domain_slug IN (' . implode(',', array_fill(0, count($domains), '%s')) . ')';
            array_push($args, ...$domains);
        }
        $sql = "SELECT id, domain_id, domain, domain_slug, competency, label, level FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY domain, id LIMIT 40";
        return $args ? ($wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: []) : [];
    }

    private function candidate_exercises(int $level_id, array $domains, array $competency_ids, string $difficulty_slug): array
    {
        global $wpdb;
        $p = $wpdb->prefix . 'ouin_exo_';
        $where = ["e.is_active = 1", "(em.exam_type IS NULL OR em.exam_type <> 'practical_subject')"];
        $args = [];
        if ($level_id > 0) {
            $where[] = "esl.school_level_id = %d";
            $args[] = $level_id;
        }
        if ($difficulty_slug !== '') {
            $where[] = "d.slug = %s";
            $args[] = $difficulty_slug;
        }
        if ($competency_ids) {
            $where[] = 'ec.competency_id IN (' . implode(',', array_fill(0, count($competency_ids), '%d')) . ')';
            array_push($args, ...$competency_ids);
        }
        if ($domains) {
            $where[] = 'c.domain_slug IN (' . implode(',', array_fill(0, count($domains), '%s')) . ')';
            array_push($args, ...$domains);
        }
        $sql = "SELECT e.id, e.title, d.label AS difficulty, d.slug AS difficulty_slug, em.estimated_minutes,
                       GROUP_CONCAT(DISTINCT c.id) AS competency_ids,
                       GROUP_CONCAT(DISTINCT c.domain SEPARATOR '||') AS domains,
                       GROUP_CONCAT(DISTINCT COALESCE(NULLIF(c.label,''), c.competency) SEPARATOR '||') AS competency_labels
                FROM {$p}exercises e
                LEFT JOIN {$p}exam_meta em ON em.exercise_id = e.id
                LEFT JOIN {$p}difficulties d ON d.id = e.difficulty_id
                LEFT JOIN {$p}exercise_school_level esl ON esl.exercise_id = e.id
                LEFT JOIN {$p}exercise_competency ec ON ec.exercise_id = e.id
                LEFT JOIN {$p}competencies c ON c.id = ec.competency_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY e.id, e.title, d.label, d.slug, em.estimated_minutes
                ORDER BY e.created_at DESC
                LIMIT 120";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
        return array_map(static function (array $row): array {
            $minutes = !empty($row['estimated_minutes']) ? (int) $row['estimated_minutes'] : 20;
            return [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'difficulty' => (string) ($row['difficulty'] ?? ''),
                'estimated_minutes' => $minutes,
                'competency_ids' => array_values(array_filter(array_map('intval', explode(',', (string) ($row['competency_ids'] ?? ''))))),
                'domain_labels' => array_values(array_filter(explode('||', (string) ($row['domains'] ?? '')))),
                'competency_labels' => array_values(array_filter(explode('||', (string) ($row['competency_labels'] ?? '')))),
            ];
        }, $rows);
    }

    private function candidate_by_id(array $candidates, int $id): array
    {
        foreach ($candidates as $candidate) {
            if ((int) $candidate['id'] === $id) {
                return $candidate;
            }
        }
        return [];
    }
}
