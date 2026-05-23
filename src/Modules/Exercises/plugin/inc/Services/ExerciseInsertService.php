<?php

namespace Ouinpo\Exercises\Services;

defined('ABSPATH') || exit;

final class ExerciseInsertService
{
    private static function table(string $suffix): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    public static function create_from_ai(array $proposal): int|\WP_Error
    {
        global $wpdb;

        $validated = self::validate_payload($proposal);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $tables = [
            'exercises' => self::table('exercises'),
            'levels' => self::table('exercise_school_level'),
            'competencies' => self::table('exercise_competency'),
            'hints' => self::table('hints'),
            'solutions' => self::table('solutions'),
            'exam_meta' => self::table('exam_meta'),
        ];

        $inserted = $wpdb->insert(
            $tables['exercises'],
            [
                'level_id' => $validated['level_id'],
                'difficulty_id' => $validated['difficulty_id'],
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'statement' => $validated['statement_html'],
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s']
        );

        if (!$inserted) {
            return new \WP_Error('insert_failed', 'Impossible de créer l’exercice.');
        }

        $exercise_id = (int) $wpdb->insert_id;

        $wpdb->insert(
            $tables['levels'],
            [
                'exercise_id' => $exercise_id,
                'school_level_id' => $validated['level_id'],
            ],
            ['%d', '%d']
        );

        foreach ($validated['competency_ids'] as $competency_id) {
            $wpdb->insert(
                $tables['competencies'],
                [
                    'exercise_id' => $exercise_id,
                    'competency_id' => $competency_id,
                ],
                ['%d', '%d']
            );
        }

        foreach ($validated['hints'] as $hint) {
            $wpdb->insert(
                $tables['hints'],
                [
                    'exercise_id' => $exercise_id,
                    'hint_order' => (int) $hint['rank'],
                    'content' => $hint['html'],
                ],
                ['%d', '%d', '%s']
            );
        }

        $wpdb->insert(
            $tables['solutions'],
            [
                'exercise_id' => $exercise_id,
                'title' => 'Solution',
                'content' => $validated['solution_html'],
                'solution_order' => 1,
                'is_official' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        $source_type = $validated['exercise_type'] === 'type_bac' ? 'type_bac' : 'classic';
        $is_exam_like = $validated['exercise_type'] === 'type_bac' ? 1 : 0;
        $bac_format = self::bac_format_for_type($validated['exercise_type']);

        $wpdb->replace(
            $tables['exam_meta'],
            [
                'exercise_id' => $exercise_id,
                'exam_type' => 'written',
                'source_type' => $source_type,
                'session_label' => null,
                'year_label' => null,
                'center_label' => null,
                'theme_bac' => null,
                'bac_format' => $bac_format,
                'estimated_minutes' => $validated['estimated_minutes'],
                'is_exam_like' => $is_exam_like,
                'subject_group' => null,
                'sort_in_subject' => null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s']
        );

        return $exercise_id;
    }

    public static function validate_payload(array $proposal): array|\WP_Error
    {
        $title = sanitize_text_field((string) ($proposal['title'] ?? ''));
        $statement = self::clean_html((string) ($proposal['statement_html'] ?? ''));
        $solution = self::clean_html((string) ($proposal['solution_html'] ?? ''));
        $level_id = (int) ($proposal['level_id'] ?? 0);
        $difficulty_id = (int) ($proposal['difficulty_id'] ?? 0);
        $estimated = max(1, min(240, (int) ($proposal['estimated_minutes'] ?? 20)));
        $exercise_type = sanitize_key((string) ($proposal['exercise_type'] ?? 'classic'));

        if ($title === '' || $statement === '' || $solution === '') {
            return new \WP_Error('missing_required_fields', 'Titre, énoncé et solution sont obligatoires.');
        }

        if (!self::level_exists($level_id)) {
            return new \WP_Error('invalid_level', 'Niveau invalide.');
        }

        if (!self::difficulty_exists($difficulty_id)) {
            return new \WP_Error('invalid_difficulty', 'Difficulté invalide.');
        }

        $competency_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($proposal['competency_ids'] ?? [])))));
        if (empty($competency_ids) || !self::competencies_exist($competency_ids)) {
            return new \WP_Error('invalid_competencies', 'Compétences invalides.');
        }

        $hints = [];
        foreach ((array) ($proposal['hints'] ?? []) as $index => $hint) {
            if (!is_array($hint)) {
                continue;
            }

            $rank = (int) ($hint['rank'] ?? ($index + 1));
            $html = self::clean_html((string) ($hint['html'] ?? ''));
            if ($rank >= 1 && $rank <= 3 && $html !== '') {
                $hints[$rank] = ['rank' => $rank, 'html' => $html];
            }
        }

        if (count($hints) < 3) {
            return new \WP_Error('invalid_hints', 'Trois indices sont requis.');
        }

        ksort($hints);

        $slug = sanitize_title((string) ($proposal['slug'] ?? ''));
        if ($slug === '') {
            $slug = sanitize_title($title);
        }

        $slug = self::unique_slug($slug);

        if (!in_array($exercise_type, array_keys(self::exercise_types()), true)) {
            $exercise_type = 'classic';
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'level_id' => $level_id,
            'difficulty_id' => $difficulty_id,
            'competency_ids' => $competency_ids,
            'statement_html' => $statement,
            'hints' => array_values($hints),
            'solution_html' => $solution,
            'estimated_minutes' => $estimated,
            'exercise_type' => $exercise_type,
        ];
    }

    public static function clean_html(string $html): string
    {
        return wp_kses($html, self::allowed_html());
    }

    public static function exercise_types(): array
    {
        return [
            'classic' => 'Entraînement classique',
            'type_bac' => 'Type bac',
            'lecture_code' => 'Lecture de code',
            'ecriture_fonction' => 'Écriture de fonction',
            'sql' => 'SQL',
            'reseau' => 'Réseau',
            'architecture' => 'Architecture',
        ];
    }

    private static function allowed_html(): array
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['pre'] = ['class' => true];
        $allowed['code'] = ['class' => true];
        $allowed['table'] = ['class' => true];
        $allowed['thead'] = [];
        $allowed['tbody'] = [];
        $allowed['tr'] = [];
        $allowed['th'] = ['scope' => true];
        $allowed['td'] = [];

        return $allowed;
    }

    private static function level_exists(int $level_id): bool
    {
        global $wpdb;
        $table = self::table('school_levels');

        return $level_id > 0 && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $level_id)) > 0;
    }

    private static function difficulty_exists(int $difficulty_id): bool
    {
        global $wpdb;
        $table = self::table('difficulties');

        return $difficulty_id > 0 && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $difficulty_id)) > 0;
    }

    private static function competencies_exist(array $competency_ids): bool
    {
        global $wpdb;
        $table = self::table('competencies');
        $ids = array_values(array_unique(array_filter(array_map('intval', $competency_ids))));
        if (empty($ids)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $found = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE active = 1 AND id IN ({$placeholders})",
            $ids
        ));

        return $found === count($ids);
    }

    private static function unique_slug(string $slug): string
    {
        global $wpdb;
        $table = self::table('exercises');
        $base = $slug !== '' ? $slug : 'exercice-ia';
        $candidate = $base;
        $suffix = 2;

        while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s", $candidate)) > 0) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private static function bac_format_for_type(string $exercise_type): ?string
    {
        return [
            'lecture_code' => 'lecture_code',
            'ecriture_fonction' => 'ecriture_complete',
            'type_bac' => 'raisonnement',
        ][$exercise_type] ?? null;
    }
}
