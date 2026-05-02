<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

class PathsService
{
    private static bool $tables_ready = false;

    private static function ensure_path_tables(): void
    {
        if (self::$tables_ready) {
            return;
        }
    
        /*
         * Important :
         * Les migrations de schéma sont gérées à l’activation du plugin
         * et lors des montées de version par le bootstrap de la suite.
         *
         * On ne lance jamais dbDelta() / ALTER TABLE pendant le rendu
         * d’une page publique ou élève.
         */
        self::$tables_ready = true;
    }

    private static function t(string $key): string
    {
        global $wpdb;

        return match ($key) {
            'paths'         => $wpdb->prefix . 'ouin_sf_paths',
            'items'         => $wpdb->prefix . 'ouin_sf_path_items',
            'targets'       => $wpdb->prefix . 'ouin_sf_path_targets',
            'groups'        => $wpdb->prefix . 'ouin_exo_groups',
            'group_members' => $wpdb->prefix . 'ouin_exo_group_members',
            'exercises'     => $wpdb->prefix . 'ouin_exo_exercises',
            'status'        => $wpdb->prefix . 'ouin_exo_user_status',
            'years'         => $wpdb->prefix . 'ouin_exo_academic_years',
            default         => '',
        };
    }

    public static function get_template_level_options(): array
    {
        return [
            'seconde'   => 'Seconde',
            'premiere'  => 'Première',
            'terminale' => 'Terminale',
        ];
    }

    public static function get_template_goal_options(): array
    {
        return [
            'revision'          => 'Révision',
            'remediation'       => 'Remédiation',
            'entrainement'      => 'Entraînement',
            'approfondissement' => 'Approfondissement',
        ];
    }

    public static function get_template_domain_options(): array
    {
        self::ensure_path_tables();
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT DISTINCT domain_slug, domain
             FROM " . $wpdb->prefix . "ouin_exo_competencies
             WHERE active = 1
               AND domain_slug IS NOT NULL
               AND domain_slug <> ''
               AND domain IS NOT NULL
               AND domain <> ''
             ORDER BY domain ASC, domain_slug ASC",
            ARRAY_A
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $slug = sanitize_key((string) ($row['domain_slug'] ?? ''));
            $label = trim((string) ($row['domain'] ?? ''));
            if ($slug === '' || $label === '') {
                continue;
            }
            $out[$slug] = $label;
        }

        return $out;
    }

    public static function get_student_level_slug(int $user_id): string
    {
        $level = '';

        if ($user_id > 0 && function_exists('\OuInPo\SegFault\ouinpo_sf_student_level_from_group')) {
            $level = (string) \OuInPo\SegFault\ouinpo_sf_student_level_from_group($user_id);
        }

        if ($level === '' && $user_id > 0 && function_exists('\ouinpo_sf_user_nsi_level')) {
            $level = (string) \ouinpo_sf_user_nsi_level($user_id, null);
        }

        $level = sanitize_key($level);
        return array_key_exists($level, self::get_template_level_options()) ? $level : '';
    }

    public static function get_active_year_id(): int
    {
        global $wpdb;

        $option_id = (int) get_option('ouin_exo_active_year_id', 0);
        if ($option_id > 0) {
            return $option_id;
        }

        $year_id = (int) $wpdb->get_var(
            "SELECT id
             FROM " . self::t('years') . "
             WHERE is_active = 1
             ORDER BY id DESC
             LIMIT 1"
        );

        return max(0, $year_id);
    }

    public static function get_groups(): array
    {
        self::ensure_path_tables();
        global $wpdb;

        return $wpdb->get_results(
            "SELECT id, label
             FROM " . self::t('groups') . "
             ORDER BY label ASC, id ASC",
            ARRAY_A
        ) ?: [];
    }

    public static function get_students(): array
    {
        self::ensure_path_tables();
        global $wpdb;

        $t_members = self::t('group_members');

        $rows = $wpdb->get_results("
            SELECT DISTINCT u.ID AS id, u.display_name, u.user_login
            FROM {$wpdb->users} u
            INNER JOIN {$t_members} gm ON gm.user_id = u.ID
            WHERE gm.role = 'student'
            ORDER BY u.display_name ASC, u.ID ASC
        ", ARRAY_A) ?: [];

        if (!empty($rows)) {
            return $rows;
        }

        $users = get_users([
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 2000,
            'fields'  => ['ID', 'display_name', 'user_login'],
        ]);

        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id'           => (int) $u->ID,
                'display_name' => (string) $u->display_name,
                'user_login'   => (string) $u->user_login,
            ];
        }

        return $out;
    }

    public static function get_exercises(): array
    {
        self::ensure_path_tables();
        global $wpdb;
    
        $t_exo  = self::t('exercises');
        $t_exam = $wpdb->prefix . 'ouin_exo_exam_meta';
    
        return $wpdb->get_results(
            "SELECT
                e.id,
                e.title,
                e.slug,
                e.is_active
             FROM {$t_exo} e
             LEFT JOIN {$t_exam} em ON em.exercise_id = e.id
             WHERE (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')
             ORDER BY e.title ASC, e.id ASC",
            ARRAY_A
        ) ?: [];
    }

    public static function get_path(int $path_id): ?array
    {
        self::ensure_path_tables();
        global $wpdb;

        $path = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . self::t('paths') . "
                 WHERE id = %d
                 LIMIT 1",
                $path_id
            ),
            ARRAY_A
        );

        if (!$path) {
            return null;
        }

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.id, i.position, i.exercise_id, i.note, e.title AS exercise_title
                 FROM " . self::t('items') . " i
                 LEFT JOIN " . self::t('exercises') . " e ON e.id = i.exercise_id
                 WHERE i.path_id = %d
                 ORDER BY i.position ASC, i.id ASC",
                $path_id
            ),
            ARRAY_A
        ) ?: [];

        $exercise_ids = [];
        foreach ($items as $item) {
            $eid = (int) ($item['exercise_id'] ?? 0);
            if ($eid > 0) {
                $exercise_ids[] = $eid;
            }
        }

        $targets = self::get_path_targets($path_id);

        $direct_user_ids   = $targets['user_ids'];
        $direct_group_ids  = $targets['group_ids'];
        $assigned_user_ids = $targets['assigned_user_ids'];

        if (empty($direct_user_ids) && !empty($path['student_id'])) {
            $legacy_student_id   = (int) $path['student_id'];
            $direct_user_ids[]   = $legacy_student_id;
            $assigned_user_ids[] = $legacy_student_id;
        }

        $direct_user_ids   = array_values(array_unique(array_map('intval', $direct_user_ids)));
        $direct_group_ids  = array_values(array_unique(array_map('intval', $direct_group_ids)));
        $assigned_user_ids = array_values(array_unique(array_map('intval', $assigned_user_ids)));

        $template_source_id = isset($path['template_source_id']) && $path['template_source_id'] !== null
            ? (int) $path['template_source_id']
            : null;

        if ($template_source_id !== null && $template_source_id <= 0) {
            $template_source_id = null;
        }

        return [
            'id'                => (int) $path['id'],
            'title'             => (string) $path['title'],
            'student_note'      => isset($path['student_note']) ? (string) $path['student_note'] : '',
            'mode'              => in_array((string) ($path['mode'] ?? 'free'), ['free', 'sequential'], true)
                ? (string) $path['mode']
                : 'free',
            'is_active'         => (int) $path['is_active'],
            'is_template'       => (int) ($path['is_template'] ?? 0),
            'template_source_id'=> $template_source_id,
            'year_id'           => isset($path['year_id']) && $path['year_id'] !== null ? (int) $path['year_id'] : null,
            'level_slug'        => sanitize_key((string) ($path['level_slug'] ?? '')),
            'domain_slug'       => sanitize_key((string) ($path['domain_slug'] ?? '')),
            'goal_slug'         => sanitize_key((string) ($path['goal_slug'] ?? '')),
            'teacher_id'        => (int) $path['teacher_id'],
            'student_id'        => (int) $path['student_id'],
            'created_at'        => (string) $path['created_at'],
            'updated_at'        => (string) $path['updated_at'],
            'items'             => $items,
            'exercise_ids'      => $exercise_ids,
            'user_ids'          => $direct_user_ids,
            'group_ids'         => $direct_group_ids,
            'assigned_user_ids' => $assigned_user_ids,
            'targets_label'     => $targets['targets_label'],
            'progress'          => self::get_progress_summary($path_id),
        ];
    }

    public static function list_paths(): array
    {
        self::ensure_path_tables();
        global $wpdb;

        $paths = $wpdb->get_results(
            "SELECT *
             FROM " . self::t('paths') . "
             ORDER BY updated_at DESC, id DESC",
            ARRAY_A
        ) ?: [];

        $out = [];

        foreach ($paths as $path) {
            $full = self::get_path((int) $path['id']);
            if (!$full) {
                continue;
            }

            $titles = [];
            foreach (array_slice($full['items'], 0, 4) as $item) {
                $titles[] = $item['exercise_title'] ?: ('Exercice #' . (int) $item['exercise_id']);
            }

            $preview = implode(' → ', $titles);
            if (count($full['items']) > 4) {
                $preview .= ' → …';
            }

            $out[] = [
                'id'                => $full['id'],
                'title'             => $full['title'],
                'student_note'      => $full['student_note'],
                'mode'              => $full['mode'],
                'is_active'         => $full['is_active'],
                'is_template'       => $full['is_template'],
                'template_source_id'=> $full['template_source_id'],
                'year_id'           => $full['year_id'],
                'level_slug'        => $full['level_slug'],
                'domain_slug'       => $full['domain_slug'],
                'goal_slug'         => $full['goal_slug'],
                'created_at'        => $full['created_at'],
                'updated_at'        => $full['updated_at'],
                'items_count'       => count($full['items']),
                'exercise_ids'      => $full['exercise_ids'],
                'user_ids'          => $full['user_ids'],
                'group_ids'         => $full['group_ids'],
                'assigned_user_ids' => $full['assigned_user_ids'],
                'targets_label'     => $full['targets_label'],
                'items_preview'     => $preview,
                'progress'          => $full['progress'],
            ];
        }

        return $out;
    }

    public static function save_path(array $data)
    {
        self::ensure_path_tables();
        global $wpdb;

        $path_id   = isset($data['path_id']) ? (int) $data['path_id'] : 0;
        $title        = trim((string) ($data['title'] ?? ''));
        $student_note = trim((string) ($data['student_note'] ?? ''));
        $student_note = $student_note !== '' ? wp_kses_post($student_note) : null;
        $is_active    = !empty($data['is_active']) ? 1 : 0;
        $is_template  = !empty($data['is_template']) ? 1 : 0;

        $mode = sanitize_key((string) ($data['mode'] ?? 'free'));
        if (!in_array($mode, ['free', 'sequential'], true)) {
            $mode = 'free';
        }

        $user_ids = array_values(array_unique(array_filter(
            array_map('intval', is_array($data['user_ids'] ?? null) ? $data['user_ids'] : []),
            fn($v) => $v > 0
        )));

        $group_ids = array_values(array_unique(array_filter(
            array_map('intval', is_array($data['group_ids'] ?? null) ? $data['group_ids'] : []),
            fn($v) => $v > 0
        )));

        $exercise_ids = is_array($data['exercise_ids'] ?? null) ? $data['exercise_ids'] : [];
        $exercise_ids = array_values(array_unique(array_filter(
            array_map('intval', $exercise_ids),
            fn($v) => $v > 0
        )));

        $template_source_id = null;
        if (array_key_exists('template_source_id', $data) && $data['template_source_id'] !== null && $data['template_source_id'] !== '') {
            $template_source_id = (int) $data['template_source_id'];
            if ($template_source_id <= 0) {
                $template_source_id = null;
            }
        }

        $year_id = null;
        if (array_key_exists('year_id', $data) && $data['year_id'] !== null && $data['year_id'] !== '') {
            $year_id = (int) $data['year_id'];
            if ($year_id <= 0) {
                $year_id = null;
            }
        }

        $level_slug = sanitize_key((string) ($data['level_slug'] ?? ''));
        if (!array_key_exists($level_slug, self::get_template_level_options())) {
            $level_slug = '';
        }

        $domain_slug = sanitize_key((string) ($data['domain_slug'] ?? ''));
        $domain_options = self::get_template_domain_options();
        if ($domain_slug !== '' && !array_key_exists($domain_slug, $domain_options)) {
            $domain_slug = '';
        }

        $goal_slug = sanitize_key((string) ($data['goal_slug'] ?? ''));
        if (!array_key_exists($goal_slug, self::get_template_goal_options())) {
            $goal_slug = '';
        }

        if ($title === '') {
            return new \WP_Error('missing_title', 'Le titre du parcours est obligatoire.');
        }

        if (empty($exercise_ids)) {
            return new \WP_Error('missing_exercises', 'Tu dois choisir au moins un exercice.');
        }

        if (!$is_template && empty($user_ids) && empty($group_ids)) {
            return new \WP_Error('missing_targets', 'Tu dois affecter le parcours à au moins un élève ou une classe.');
        }

        if ($is_template) {
            $year_id = null;
        } elseif ($year_id === null) {
            $year_id = self::get_active_year_id();
        }

        if (!$is_template) {
            $level_slug = '';
            $domain_slug = '';
            $goal_slug = '';
        }

        $now             = current_time('mysql');
        $current_user_id = (int) get_current_user_id();

        if ($path_id > 0) {
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM " . self::t('paths') . " WHERE id = %d LIMIT 1",
                    $path_id
                ),
                ARRAY_A
            );

            if (!$existing) {
                return new \WP_Error('missing_path', 'Parcours introuvable.');
            }

            if (!array_key_exists('template_source_id', $data)) {
                $template_source_id = isset($existing['template_source_id']) && $existing['template_source_id'] !== null
                    ? (int) $existing['template_source_id']
                    : null;

                if ($template_source_id !== null && $template_source_id <= 0) {
                    $template_source_id = null;
                }
            }

            if (!array_key_exists('year_id', $data)) {
                $year_id = isset($existing['year_id']) && $existing['year_id'] !== null
                    ? (int) $existing['year_id']
                    : null;

                if ($year_id !== null && $year_id <= 0) {
                    $year_id = null;
                }

                if (empty($existing['is_template']) && $year_id === null) {
                    $year_id = self::get_active_year_id();
                }
            }

            if (!array_key_exists('level_slug', $data)) {
                $level_slug = sanitize_key((string) ($existing['level_slug'] ?? ''));
            }
            if (!array_key_exists('domain_slug', $data)) {
                $domain_slug = sanitize_key((string) ($existing['domain_slug'] ?? ''));
            }
            if (!array_key_exists('goal_slug', $data)) {
                $goal_slug = sanitize_key((string) ($existing['goal_slug'] ?? ''));
            }

            if ($template_source_id !== null && $template_source_id === $path_id) {
                $template_source_id = null;
            }

            $updated = $wpdb->update(
                self::t('paths'),
                [
                    'title'              => $title,
                    'student_note'       => $student_note,
                    'mode'               => $mode,
                    'is_active'          => $is_active,
                    'is_template'        => $is_template,
                    'template_source_id' => $template_source_id,
                    'year_id'            => $year_id,
                    'level_slug'         => ($level_slug !== '' ? $level_slug : null),
                    'domain_slug'        => ($domain_slug !== '' ? $domain_slug : null),
                    'goal_slug'          => ($goal_slug !== '' ? $goal_slug : null),
                    'student_id'         => 0,
                    'updated_at'         => $now,
                ],
                ['id' => $path_id],
                ['%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );

            if ($updated === false) {
                return new \WP_Error('update_failed', 'Impossible de mettre à jour le parcours.');
            }
        } else {
            $inserted = $wpdb->insert(
                self::t('paths'),
                [
                    'teacher_id'         => $current_user_id,
                    'student_id'         => 0,
                    'title'              => $title,
                    'student_note'       => $student_note,
                    'mode'               => $mode,
                    'is_active'          => $is_active,
                    'is_template'        => $is_template,
                    'template_source_id' => $template_source_id,
                    'year_id'            => $year_id,
                    'level_slug'         => ($level_slug !== '' ? $level_slug : null),
                    'domain_slug'        => ($domain_slug !== '' ? $domain_slug : null),
                    'goal_slug'          => ($goal_slug !== '' ? $goal_slug : null),
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ],
                ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );

            if ($inserted === false) {
                return new \WP_Error('insert_failed', 'Impossible de créer le parcours.');
            }

            $path_id = (int) $wpdb->insert_id;
            if ($path_id <= 0) {
                return new \WP_Error('insert_failed', 'Impossible de créer le parcours.');
            }
        }

        self::replace_items($path_id, $exercise_ids);

        if ($is_template) {
            $wpdb->delete(self::t('targets'), ['path_id' => $path_id], ['%d']);
        } else {
            self::replace_targets($path_id, $user_ids, $group_ids, $current_user_id, $now);
        }

        return $path_id;
    }

    public static function instantiate_template(int $template_id, array $user_ids = [], array $group_ids = [], ?string $title = null)
    {
        self::ensure_path_tables();

        $template = self::get_path($template_id);
        if (!$template) {
            return new \WP_Error('missing_template', 'Modèle introuvable.');
        }

        if (empty($template['is_template'])) {
            return new \WP_Error('not_a_template', 'Le parcours sélectionné n’est pas un modèle.');
        }

        $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), fn($v) => $v > 0)));
        $group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids), fn($v) => $v > 0)));

        if (empty($user_ids) && empty($group_ids)) {
            return new \WP_Error('missing_targets', 'Tu dois choisir au moins un élève ou une classe.');
        }

        $new_title = trim((string) $title);
        if ($new_title === '') {
            $new_title = self::build_instance_title($template['title'], $group_ids, $user_ids);
        }

        return self::save_path([
            'title'              => $new_title,
            'student_note'       => $template['student_note'] ?? '',
            'mode'               => $template['mode'] ?? 'free',
            'is_active'          => (int) ($template['is_active'] ?? 1),
            'is_template'        => 0,
            'template_source_id' => $template_id,
            'exercise_ids'       => $template['exercise_ids'] ?? [],
            'group_ids'          => $group_ids,
            'user_ids'           => $user_ids,
        ]);
    }

    private static function build_instance_title(string $base_title, array $group_ids = [], array $user_ids = []): string
    {
        $base_title = trim($base_title);
        if ($base_title === '') {
            $base_title = 'Parcours';
        }

        $suffix = current_time('Y-m-d');

        if (!empty($group_ids)) {
            $groups = self::get_groups();
            $labels = [];

            foreach ($groups as $g) {
                if (in_array((int) $g['id'], $group_ids, true)) {
                    $labels[] = (string) $g['label'];
                }
            }

            if (!empty($labels)) {
                return $base_title . ' — ' . implode(', ', $labels) . ' — ' . $suffix;
            }
        }

        if (count($user_ids) === 1) {
            $u = get_userdata((int) $user_ids[0]);
            if ($u) {
                return $base_title . ' — ' . $u->display_name . ' — ' . $suffix;
            }
        }

        return $base_title . ' — affectation ' . $suffix;
    }

    public static function delete_path(int $path_id): void
    {
        self::ensure_path_tables();
        global $wpdb;

        if ($path_id <= 0) {
            return;
        }

        $wpdb->delete(self::t('targets'), ['path_id' => $path_id], ['%d']);
        $wpdb->delete(self::t('items'), ['path_id' => $path_id], ['%d']);
        $wpdb->delete(self::t('paths'), ['id' => $path_id], ['%d']);
    }


    public static function can_user_self_remove_path(int $path_id, int $user_id): bool
    {
        self::ensure_path_tables();
        global $wpdb;
    
        if ($path_id <= 0 || $user_id <= 0) {
            return false;
        }
    
        $path = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, teacher_id, is_template
                 FROM " . self::t('paths') . "
                 WHERE id = %d
                 LIMIT 1",
                $path_id
            ),
            ARRAY_A
        );
    
        if (!$path || !empty($path['is_template'])) {
            return false;
        }
    
        $targets = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT target_type, target_id, assigned_by
                 FROM " . self::t('targets') . "
                 WHERE path_id = %d
                 ORDER BY target_type ASC, target_id ASC",
                $path_id
            ),
            ARRAY_A
        ) ?: [];
    
        if (count($targets) !== 1) {
            return false;
        }
    
        $target = $targets[0];
    
        $target_type = (string) ($target['target_type'] ?? '');
        $target_id   = (int) ($target['target_id'] ?? 0);
        $assigned_by = (int) ($target['assigned_by'] ?? 0);
        $teacher_id  = (int) ($path['teacher_id'] ?? 0);
    
        if ($target_type !== 'user' || $target_id !== $user_id) {
            return false;
        }
    
        if ($assigned_by === $user_id) {
            return true;
        }
    
        return $assigned_by === 0 && $teacher_id === 0;
    }

    public static function delete_user_self_path(int $path_id, int $user_id): bool
    {
        if (!self::can_user_self_remove_path($path_id, $user_id)) {
            return false;
        }

        self::delete_path($path_id);
        return true;
    }

    public static function list_active_templates(array $filters = []): array
    {
        self::ensure_path_tables();
        global $wpdb;
    
        $level_slug  = sanitize_key((string) ($filters['level_slug'] ?? ''));
        $domain_slug = sanitize_key((string) ($filters['domain_slug'] ?? ''));
        $goal_slug   = sanitize_key((string) ($filters['goal_slug'] ?? ''));
        $student_id  = isset($filters['student_id']) ? (int) $filters['student_id'] : 0;
    
        if ($level_slug === '' && $student_id > 0) {
            $level_slug = self::get_student_level_slug($student_id);
        }
    
        $where = [
            'is_template = 1',
            'is_active = 1',
        ];
        $params = [];
    
        if ($level_slug !== '') {
            $where[] = 'level_slug = %s';
            $params[] = $level_slug;
        }
    
        if ($domain_slug !== '') {
            $where[] = 'domain_slug = %s';
            $params[] = $domain_slug;
        }
    
        if ($goal_slug !== '') {
            $where[] = 'goal_slug = %s';
            $params[] = $goal_slug;
        }
    
        $sql = "
            SELECT
                id,
                title,
                student_note,
                mode,
                is_active,
                is_template,
                template_source_id,
                year_id,
                level_slug,
                domain_slug,
                goal_slug,
                teacher_id,
                student_id,
                created_at,
                updated_at
            FROM " . self::t('paths') . "
            WHERE " . implode(' AND ', $where) . "
            ORDER BY level_slug ASC, domain_slug ASC, goal_slug ASC, title ASC, id ASC
        ";
    
        if (!empty($params)) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
        } else {
            $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        }
    
        $out = [];
    
        foreach ($rows as $row) {
            $out[] = [
                'id'                 => (int) ($row['id'] ?? 0),
                'title'              => (string) ($row['title'] ?? ''),
                'student_note'       => (string) ($row['student_note'] ?? ''),
                'mode'               => in_array((string) ($row['mode'] ?? 'free'), ['free', 'sequential'], true)
                    ? (string) ($row['mode'] ?? 'free')
                    : 'free',
                'is_active'          => (int) ($row['is_active'] ?? 0),
                'is_template'        => (int) ($row['is_template'] ?? 0),
                'template_source_id' => isset($row['template_source_id']) && $row['template_source_id'] !== null
                    ? (int) $row['template_source_id']
                    : null,
                'year_id'            => isset($row['year_id']) && $row['year_id'] !== null
                    ? (int) $row['year_id']
                    : null,
                'level_slug'         => sanitize_key((string) ($row['level_slug'] ?? '')),
                'domain_slug'        => sanitize_key((string) ($row['domain_slug'] ?? '')),
                'goal_slug'          => sanitize_key((string) ($row['goal_slug'] ?? '')),
                'teacher_id'         => (int) ($row['teacher_id'] ?? 0),
                'student_id'         => (int) ($row['student_id'] ?? 0),
                'created_at'         => (string) ($row['created_at'] ?? ''),
                'updated_at'         => (string) ($row['updated_at'] ?? ''),
                'items_count'        => 0,
                'exercise_ids'       => [],
                'user_ids'           => [],
                'group_ids'          => [],
                'assigned_user_ids'  => [],
                'targets_label'      => '',
                'items_preview'      => '',
                'progress'           => [
                    'targets_count'   => 0,
                    'items_count'     => 0,
                    'completed_users' => 0,
                    'avg_percent'     => 0.0,
                ],
            ];
        }
    
        return $out;
    }

    public static function purge_non_template_paths_for_new_active_year(int $active_year_id): int
    {
        self::ensure_path_tables();
        global $wpdb;

        if ($active_year_id <= 0) {
            return 0;
        }

        $path_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM " . self::t('paths') . "
                 WHERE is_template = 0
                   AND (year_id IS NULL OR year_id <> %d)",
                $active_year_id
            )
        ) ?: [];

        $path_ids = array_values(array_unique(array_filter(array_map('intval', $path_ids), fn($v) => $v > 0)));
        if (empty($path_ids)) {
            return 0;
        }

        foreach ($path_ids as $path_id) {
            self::delete_path($path_id);
        }

        return count($path_ids);
    }

    public static function duplicate_path(int $path_id)
    {
        self::ensure_path_tables();

        $path = self::get_path($path_id);
        if (!$path) {
            return new \WP_Error('missing_path', 'Parcours introuvable.');
        }

        $base_title = trim((string) $path['title']);
        if ($base_title === '') {
            $base_title = 'Parcours sans titre';
        }

        $new_title = $base_title . ' (copie)';
        $i = 2;

        global $wpdb;
        while ((int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::t('paths') . " WHERE title = %s",
                $new_title
            )
        ) > 0) {
            $new_title = $base_title . ' (copie ' . $i . ')';
            $i++;
        }

        $is_template = (int) ($path['is_template'] ?? 0);

        $template_source_id = $path['template_source_id'] ?? null;
        if ($is_template) {
            $template_source_id = $template_source_id ?: $path_id;
        }

        return self::save_path([
            'title'              => $new_title,
            'student_note'       => $path['student_note'] ?? '',
            'mode'               => $path['mode'] ?? 'free',
            'is_active'          => (int) ($path['is_active'] ?? 1),
            'is_template'        => $is_template,
            'template_source_id' => $template_source_id,
            'year_id'            => $path['year_id'] ?? null,
            'level_slug'         => $path['level_slug'] ?? '',
            'domain_slug'        => $path['domain_slug'] ?? '',
            'goal_slug'          => $path['goal_slug'] ?? '',
            'exercise_ids'       => $path['exercise_ids'] ?? [],
            'group_ids'          => $is_template ? [] : ($path['group_ids'] ?? []),
            'user_ids'           => $is_template ? [] : ($path['user_ids'] ?? []),
        ]);
    }

    public static function get_progress_details(int $path_id): array
    {
        self::ensure_path_tables();

        $path = self::get_path($path_id);
        if (!$path) {
            return [];
        }

        $exercise_ids = array_values(array_unique(array_map('intval', $path['exercise_ids'] ?? [])));
        $user_ids     = array_values(array_unique(array_map('intval', $path['assigned_user_ids'] ?? [])));

        $items_count = count($exercise_ids);
        if ($items_count === 0 || empty($user_ids)) {
            return [];
        }

        global $wpdb;

        $user_ph = implode(',', array_fill(0, count($user_ids), '%d'));
        $exo_ph  = implode(',', array_fill(0, count($exercise_ids), '%d'));
        $args    = array_merge($user_ids, $exercise_ids);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, exercise_id, status
                 FROM " . self::t('status') . "
                 WHERE user_id IN ({$user_ph})
                   AND exercise_id IN ({$exo_ph})",
                ...$args
            ),
            ARRAY_A
        ) ?: [];

        $stats = [];
        foreach ($user_ids as $uid) {
            $stats[$uid] = [
                'solved'    => 0,
                'attempted' => 0,
            ];
        }

        foreach ($rows as $row) {
            $uid    = (int) ($row['user_id'] ?? 0);
            $status = (string) ($row['status'] ?? '');

            if (!isset($stats[$uid])) {
                continue;
            }

            if ($status === 'solved') {
                $stats[$uid]['solved']++;
            } elseif ($status === 'attempted') {
                $stats[$uid]['attempted']++;
            }
        }

        $group_labels_by_user = [];
        $target_group_ids = array_values(array_unique(array_map('intval', $path['group_ids'] ?? [])));

        if (!empty($target_group_ids)) {
            $gph = implode(',', array_fill(0, count($target_group_ids), '%d'));
            $uph = implode(',', array_fill(0, count($user_ids), '%d'));

            $group_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT gm.user_id, g.label
                     FROM " . self::t('group_members') . " gm
                     INNER JOIN " . self::t('groups') . " g ON g.id = gm.group_id
                     WHERE gm.group_id IN ({$gph})
                       AND gm.user_id IN ({$uph})
                       AND gm.role = 'student'",
                    ...array_merge($target_group_ids, $user_ids)
                ),
                ARRAY_A
            ) ?: [];

            foreach ($group_rows as $gr) {
                $uid   = (int) ($gr['user_id'] ?? 0);
                $label = trim((string) ($gr['label'] ?? ''));

                if ($uid > 0 && $label !== '') {
                    if (!isset($group_labels_by_user[$uid])) {
                        $group_labels_by_user[$uid] = [];
                    }
                    $group_labels_by_user[$uid][] = $label;
                }
            }
        }

        $details = [];
        foreach ($user_ids as $uid) {
            $u = get_userdata($uid);

            $solved    = (int) $stats[$uid]['solved'];
            $attempted = (int) $stats[$uid]['attempted'];
            $none      = max(0, $items_count - $solved - $attempted);
            $percent   = round(($solved / $items_count) * 100, 1);

            $details[] = [
                'user_id'      => $uid,
                'display_name' => $u ? (string) $u->display_name : ('User #' . $uid),
                'user_login'   => $u ? (string) $u->user_login : '',
                'groups_label' => !empty($group_labels_by_user[$uid])
                    ? implode(', ', array_unique($group_labels_by_user[$uid]))
                    : '—',
                'solved'       => $solved,
                'attempted'    => $attempted,
                'none'         => $none,
                'items_count'  => $items_count,
                'percent'      => $percent,
            ];
        }

        usort($details, function ($a, $b) {
            return strcasecmp((string) $a['display_name'], (string) $b['display_name']);
        });

        return $details;
    }

    public static function is_path_assigned_to_user(int $path_id, int $user_id): bool
    {
        $path = self::get_path($path_id);
        if (!$path || $user_id <= 0) {
            return false;
        }

        return in_array($user_id, $path['assigned_user_ids'] ?? [], true);
    }

    public static function get_ordered_exercise_ids(int $path_id): array
    {
        self::ensure_path_tables();
        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT exercise_id
                 FROM " . self::t('items') . "
                 WHERE path_id = %d
                 ORDER BY position ASC, id ASC",
                $path_id
            )
        ) ?: [];

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    }

    public static function get_user_status_map_for_path(int $path_id, int $user_id): array
    {
        self::ensure_path_tables();

        $exercise_ids = self::get_ordered_exercise_ids($path_id);
        if ($user_id <= 0 || empty($exercise_ids)) {
            return [];
        }

        global $wpdb;

        $ph = implode(',', array_fill(0, count($exercise_ids), '%d'));
        $args = array_merge([$user_id], $exercise_ids);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT exercise_id, status
                 FROM " . self::t('status') . "
                 WHERE user_id = %d
                   AND exercise_id IN ({$ph})",
                ...$args
            ),
            ARRAY_A
        ) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['exercise_id']] = (string) ($row['status'] ?? 'none');
        }

        return $map;
    }

    public static function get_unlocked_exercise_ids_for_user(int $path_id, int $user_id): array
    {
        $path = self::get_path($path_id);
        if (!$path || $user_id <= 0) {
            return [];
        }

        if (!self::is_path_assigned_to_user($path_id, $user_id)) {
            return [];
        }

        $exercise_ids = self::get_ordered_exercise_ids($path_id);
        if (empty($exercise_ids)) {
            return [];
        }

        $mode = $path['mode'] ?? 'free';
        if ($mode !== 'sequential') {
            return $exercise_ids;
        }

        $status_map = self::get_user_status_map_for_path($path_id, $user_id);

        $allowed = [];
        $previous_all_solved = true;

        foreach ($exercise_ids as $eid) {
            if ($previous_all_solved) {
                $allowed[] = $eid;
            }

            $status = $status_map[$eid] ?? 'none';
            if ($status !== 'solved') {
                $previous_all_solved = false;
            }
        }

        return $allowed;
    }

    public static function is_exercise_unlocked_for_user(int $path_id, int $user_id, int $exercise_id): bool
    {
        if ($exercise_id <= 0) {
            return false;
        }

        $allowed = self::get_unlocked_exercise_ids_for_user($path_id, $user_id);
        return in_array($exercise_id, $allowed, true);
    }

    private static function replace_items(int $path_id, array $exercise_ids): void
    {
        global $wpdb;

        $wpdb->delete(self::t('items'), ['path_id' => $path_id], ['%d']);

        $pos = 1;
        foreach ($exercise_ids as $eid) {
            $wpdb->insert(
                self::t('items'),
                [
                    'path_id'     => $path_id,
                    'position'    => $pos++,
                    'exercise_id' => (int) $eid,
                    'note'        => null,
                ],
                ['%d', '%d', '%d', '%s']
            );
        }
    }

    private static function replace_targets(int $path_id, array $user_ids, array $group_ids, int $assigned_by, string $now): void
    {
        global $wpdb;

        $wpdb->delete(self::t('targets'), ['path_id' => $path_id], ['%d']);

        foreach ($user_ids as $uid) {
            $wpdb->replace(
                self::t('targets'),
                [
                    'path_id'     => $path_id,
                    'target_type' => 'user',
                    'target_id'   => (int) $uid,
                    'assigned_at' => $now,
                    'assigned_by' => $assigned_by,
                    'created_at'  => $now,
                ],
                ['%d', '%s', '%d', '%s', '%d', '%s']
            );
        }

        foreach ($group_ids as $gid) {
            $wpdb->replace(
                self::t('targets'),
                [
                    'path_id'     => $path_id,
                    'target_type' => 'group',
                    'target_id'   => (int) $gid,
                    'assigned_at' => $now,
                    'assigned_by' => $assigned_by,
                    'created_at'  => $now,
                ],
                ['%d', '%s', '%d', '%s', '%d', '%s']
            );
        }
    }

    public static function get_path_targets(int $path_id): array
    {
        self::ensure_path_tables();
        global $wpdb;

        $targets = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.target_type, t.target_id, g.label AS group_label
                 FROM " . self::t('targets') . " t
                 LEFT JOIN " . self::t('groups') . " g
                   ON t.target_type = 'group' AND g.id = t.target_id
                 WHERE t.path_id = %d
                 ORDER BY t.target_type ASC, t.target_id ASC",
                $path_id
            ),
            ARRAY_A
        ) ?: [];

        $direct_user_ids   = [];
        $direct_group_ids  = [];
        $assigned_user_ids = [];
        $labels            = [];

        foreach ($targets as $t) {
            $type = (string) ($t['target_type'] ?? '');
            $id   = (int) ($t['target_id'] ?? 0);

            if ($type === 'user' && $id > 0) {
                $direct_user_ids[]   = $id;
                $assigned_user_ids[] = $id;

                $u = get_userdata($id);
                $labels[] = $u ? $u->display_name : ('User #' . $id);
            }

            if ($type === 'group' && $id > 0) {
                $direct_group_ids[] = $id;
                $labels[] = !empty($t['group_label']) ? ('Classe : ' . $t['group_label']) : ('Classe #' . $id);

                $member_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT user_id
                         FROM " . self::t('group_members') . "
                         WHERE group_id = %d
                           AND role = 'student'",
                        $id
                    )
                ) ?: [];

                foreach ($member_ids as $uid) {
                    $uid = (int) $uid;
                    if ($uid > 0) {
                        $assigned_user_ids[] = $uid;
                    }
                }
            }
        }

        return [
            'targets'           => $targets,
            'user_ids'          => array_values(array_unique($direct_user_ids)),
            'group_ids'         => array_values(array_unique($direct_group_ids)),
            'assigned_user_ids' => array_values(array_unique($assigned_user_ids)),
            'targets_label'     => implode(', ', array_values(array_unique($labels))),
        ];
    }

    public static function get_progress_summary(int $path_id): array
    {
        self::ensure_path_tables();
        global $wpdb;

        $path = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::t('paths') . " WHERE id = %d",
                $path_id
            ),
            ARRAY_A
        );

        if (!$path) {
            return [
                'targets_count'   => 0,
                'items_count'     => 0,
                'completed_users' => 0,
                'avg_percent'     => 0.0,
            ];
        }

        $exercise_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT exercise_id
                 FROM " . self::t('items') . "
                 WHERE path_id = %d
                 ORDER BY position ASC, id ASC",
                $path_id
            )
        ) ?: [];

        $exercise_ids = array_values(array_unique(array_filter(array_map('intval', $exercise_ids), fn($v) => $v > 0)));

        $targets  = self::get_path_targets($path_id);
        $user_ids = $targets['assigned_user_ids'];

        if (empty($user_ids) && !empty($path['student_id'])) {
            $user_ids[] = (int) $path['student_id'];
        }

        $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), fn($v) => $v > 0)));

        $items_count   = count($exercise_ids);
        $targets_count = count($user_ids);

        if ($items_count === 0 || $targets_count === 0) {
            return [
                'targets_count'   => $targets_count,
                'items_count'     => $items_count,
                'completed_users' => 0,
                'avg_percent'     => 0.0,
            ];
        }

        $user_ph = implode(',', array_fill(0, count($user_ids), '%d'));
        $exo_ph  = implode(',', array_fill(0, count($exercise_ids), '%d'));
        $args    = array_merge($user_ids, $exercise_ids);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, exercise_id, status
                 FROM " . self::t('status') . "
                 WHERE user_id IN ({$user_ph})
                   AND exercise_id IN ({$exo_ph})",
                ...$args
            ),
            ARRAY_A
        ) ?: [];

        $solved_by_user = [];
        foreach ($user_ids as $uid) {
            $solved_by_user[$uid] = 0;
        }

        foreach ($rows as $row) {
            if (($row['status'] ?? '') === 'solved') {
                $uid = (int) $row['user_id'];
                if (isset($solved_by_user[$uid])) {
                    $solved_by_user[$uid]++;
                }
            }
        }

        $percentages     = [];
        $completed_users = 0;

        foreach ($solved_by_user as $solved_count) {
            $pct = round(($solved_count / $items_count) * 100, 1);
            $percentages[] = $pct;

            if ($solved_count >= $items_count) {
                $completed_users++;
            }
        }

        $avg = !empty($percentages) ? round(array_sum($percentages) / count($percentages), 1) : 0.0;

        return [
            'targets_count'   => $targets_count,
            'items_count'     => $items_count,
            'completed_users' => $completed_users,
            'avg_percent'     => $avg,
        ];
    }
}