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

        $answer = \OuInPo\SegFault\OpenAI::respond($this->messages($context), [
            'temperature' => 0.25,
            'max_tokens' => max(2200, AiSettings::maxTokens('ouinpo_ai_practical_ai_max_tokens')),
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ]);

        $json = $this->decode_json($answer);
        if (is_wp_error($json)) {
            return $json;
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
                    'kind' => 'new_ai_exercise',
                    'exercise_draft' => [
                        'title' => '...',
                        'slug' => '...',
                        'level' => $context['level']['label'],
                        'difficulty' => $context['difficulty']['label'],
                        'estimated_minutes' => 20,
                        'domains' => [],
                        'competencies' => [],
                        'statement_html' => '<p>...</p>',
                        'hints' => [['rank' => 1, 'html' => '<p>...</p>'], ['rank' => 2, 'html' => '<p>...</p>'], ['rank' => 3, 'html' => '<p>...</p>']],
                        'solution_html' => '<p>...</p>',
                        'pedagogical_rationale' => '...',
                    ],
                    'suggested_points' => 5,
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

        $system = "Tu aides un enseignant NSI à composer un devoir. Réponds uniquement avec un JSON strict valide. N'utilise aucun nom d'élève. N'invente aucun ID d'exercice : pour kind=existing_exercise, choisis uniquement parmi les IDs candidats transmis. Pour kind=new_ai_exercise, fournis un brouillon compatible avec le schéma d'exercice IA.";
        $user = "Contexte non nominatif : groupe #{$context['group_id']}, niveau {$context['level']['label']}.\n"
            . "Durée cible : {$context['target_minutes']} min. Nombre d'exercices souhaité : {$context['items_count']}.\n"
            . "Difficulté globale : {$context['difficulty']['label']} ({$context['difficulty']['slug']}).\n"
            . "Part souhaitée existants/nouveaux IA : {$context['existing_ratio']}% / {$context['new_ratio']}%.\n"
            . "Compétences sélectionnées :\n" . implode("\n", $competency_lines) . "\n\n"
            . "Exercices existants candidats :\n" . (empty($candidate_lines) ? 'Aucun candidat transmis.' : implode("\n", $candidate_lines)) . "\n\n"
            . "KPI agrégés de classe, sans données nominatives :\n" . wp_json_encode($context['kpi'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
            . "Contraintes libres : " . ($context['free_constraints'] !== '' ? $context['free_constraints'] : 'Aucune') . "\n\n"
            . "Format JSON attendu :\n" . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    private function decode_json(string $answer): array|\WP_Error
    {
        $answer = trim($answer);
        $data = $answer !== '' ? json_decode($answer, true) : null;
        if (!is_array($data) && preg_match('/\{.*\}/s', $answer, $m)) {
            $data = json_decode($m[0], true);
        }
        return is_array($data) ? $data : new \WP_Error('invalid_json', 'La réponse IA ne contient pas de JSON valide.');
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

            if ($kind === 'new_ai_exercise' && is_array($item['exercise_draft'] ?? null)) {
                $draft = $this->normalize_draft($item['exercise_draft'], $context);
                $valid = ExerciseInsertService::validate_payload($draft);
                if (is_wp_error($valid)) {
                    return $valid;
                }
                $items[] = [
                    'kind' => 'new_ai_exercise',
                    'exercise_draft' => array_merge($draft, $valid),
                    'suggested_points' => $points,
                    'rationale' => sanitize_textarea_field((string) ($item['rationale'] ?? '')),
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

    private function normalize_draft(array $draft, array $context): array
    {
        return [
            'title' => sanitize_text_field((string) ($draft['title'] ?? 'Exercice IA')),
            'slug' => sanitize_title((string) ($draft['slug'] ?? '')),
            'level_id' => (int) $context['level']['id'],
            'difficulty_id' => (int) $context['difficulty']['id'],
            'estimated_minutes' => max(1, min(240, (int) ($draft['estimated_minutes'] ?? 20))),
            'exercise_type' => 'classic',
            'competency_ids' => array_map('intval', array_column($context['competencies'], 'id')),
            'statement_html' => ExerciseInsertService::clean_html((string) ($draft['statement_html'] ?? '')),
            'hints' => (array) ($draft['hints'] ?? []),
            'solution_html' => ExerciseInsertService::clean_html((string) ($draft['solution_html'] ?? '')),
            'pedagogical_rationale' => sanitize_textarea_field((string) ($draft['pedagogical_rationale'] ?? '')),
            'program_guardrails' => ['in_program' => true, 'warnings' => []],
        ];
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
        $sql = "SELECT id, domain, domain_slug, competency, label, level FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY domain, id LIMIT 40";
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
