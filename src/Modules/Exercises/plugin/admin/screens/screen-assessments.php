<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Exercises\AssessmentsService;

defined('ABSPATH') || exit;

class Screen_Assessments {
    private const PAGE_SLUG = 'ouinpo-assessments';
    private const NONCE_ACTION = 'ouinpo_assessments_form';
    private const NONCE_NAME = 'ouinpo_assessments_nonce';

    public static function render() {
        if (!current_user_can('edit_posts') && !current_user_can('edit_users')) {
            wp_die('Accès refusé');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handle_post();
        }

        self::render_notices_from_query();
        settings_errors('ouinpo_assessments');

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($action === 'grade' && $id > 0) {
            self::render_grade($id);
            return;
        }
        
        if ($action === 'items' && $id > 0) {
            self::render_items($id);
            return;
        }
        
        if ($action === 'replace_item' && $id > 0) {
            $exerciseId = isset($_GET['exercise_id']) ? (int) $_GET['exercise_id'] : 0;
            self::render_replace_item($id, $exerciseId);
            return;
        }
        
        if ($action === 'subject' && $id > 0) {
            self::render_subject($id);
            return;
        }
        
        if ($action === 'correction' && $id > 0) {
            self::render_correction($id);
            return;
        }
        
        self::render_overview($action, $id);
    }

    private static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $name;
    }

    private static function redirect(array $args = []): void {
        $base = admin_url('admin.php?page=' . self::PAGE_SLUG);
        wp_safe_redirect(add_query_arg($args, $base));
        exit;
    }

    private static function render_notices_from_query(): void {
        $map = [
            'created'    => 'DS créé.',
            'updated'    => 'DS mis à jour.',
            'deleted'    => 'DS supprimé.',
            'duplicated' => 'DS dupliqué.',
            'items_updated' => 'Exercices du DS mis à jour.',
            'item_replaced' => 'Exercice remplacé dans le DS.',
            'version_b_created' => 'Version B créée.',
            'graded'     => 'Résultats enregistrés.',
        ];

        foreach ($map as $key => $message) {
            if (!empty($_GET[$key])) {
                printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
            }
        }
    }

    private static function status_options(): array {
        return [
            ''               => '— Non renseigné —',
            'not_acquired'   => 'Non acquis',
            'in_progress'    => 'En cours',
            'consolidating'  => 'À consolider',
            'acquired'       => 'Acquis',
        ];
    }

    private static function status_rank(string $status): int {
        return AssessmentsService::status_rank($status);
    }

    private static function status_badge(string $status): string {
        $labels = self::status_options();
        if (!isset($labels[$status]) || $status === '') {
            return '<span class="ouinpo-assessment-muted">—</span>';
        }

        return sprintf(
            '<span class="ouinpo-assessment-status ouinpo-assessment-status--%s">%s</span>',
            esc_attr($status),
            esc_html($labels[$status])
        );
    }

    private static function get_active_year_id(): int {
        global $wpdb;
        $tblYears = self::table('academic_years');
        $id = (int) $wpdb->get_var("SELECT id FROM {$tblYears} WHERE is_active = 1 ORDER BY starts_on DESC, id DESC LIMIT 1");
        return $id > 0 ? $id : 0;
    }

    private static function get_groups(): array {
        global $wpdb;
        $tblGroups = self::table('groups');
        $tblYears  = self::table('academic_years');
        $tblLevels = self::table('school_levels');

        return $wpdb->get_results(
            "SELECT g.id, g.label, g.year_id, y.slug AS year_slug, l.label AS level_label
             FROM {$tblGroups} g
             LEFT JOIN {$tblYears} y ON y.id = g.year_id
             LEFT JOIN {$tblLevels} l ON l.id = g.school_level_id
             ORDER BY y.starts_on DESC, g.label ASC"
        ) ?: [];
    }

    private static function get_group(int $groupId): ?object {
        global $wpdb;
        if ($groupId <= 0) {
            return null;
        }

        $tblGroups = self::table('groups');
        $tblYears  = self::table('academic_years');
        $tblLevels = self::table('school_levels');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT g.*, y.slug AS year_slug, l.label AS level_label
             FROM {$tblGroups} g
             LEFT JOIN {$tblYears} y ON y.id = g.year_id
             LEFT JOIN {$tblLevels} l ON l.id = g.school_level_id
             WHERE g.id = %d
             LIMIT 1",
            $groupId
        ));

        return $row ?: null;
    }

    private static function get_students_for_group(int $groupId): array {
        global $wpdb;
        if ($groupId <= 0) {
            return [];
        }

        $tblGM = self::table('group_members');
        $tblU  = $wpdb->users;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID AS id, u.display_name, u.user_email
             FROM {$tblGM} gm
             JOIN {$tblU} u ON u.ID = gm.user_id
             WHERE gm.group_id = %d
               AND gm.role = 'student'
             ORDER BY u.display_name ASC, u.ID ASC",
            $groupId
        )) ?: [];
    }

    private static function get_assessment(int $assessmentId): ?object {
        $data = AssessmentsService::get_competency_assessment($assessmentId);
        if (!$data || empty($data['assessment'])) {
            return null;
        }
        return (object) $data['assessment'];
    }

    private static function get_available_competencies(?int $groupId): array {
        global $wpdb;
        $tblC = self::table('competencies');

        $group = $groupId ? self::get_group((int) $groupId) : null;
        if ($group && !empty($group->level_label)) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT id, domain, competency, track, level, slug
                 FROM {$tblC}
                 WHERE active = 1
                   AND (level = %s OR level = 'Transversal')
                 ORDER BY
                   CASE WHEN track = 'NSI' THEN 1 WHEN track = 'SNT' THEN 2 ELSE 3 END,
                   domain,
                   id",
                $group->level_label
            )) ?: [];
        }

        return $wpdb->get_results(
            "SELECT id, domain, competency, track, level, slug
             FROM {$tblC}
             WHERE active = 1
             ORDER BY
               CASE WHEN track = 'NSI' THEN 1 WHEN track = 'SNT' THEN 2 ELSE 3 END,
               level,
               domain,
               id"
        ) ?: [];
    }

    private static function get_assessment_competency_ids(int $assessmentId): array {
        global $wpdb;
        if ($assessmentId <= 0) {
            return [];
        }

        $tblAC = self::table('assessment_competencies');
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT competency_id FROM {$tblAC} WHERE assessment_id = %d ORDER BY competency_id ASC",
            $assessmentId
        )) ?: []);
    }

    private static function get_assessment_competencies(int $assessmentId): array {
        $data = AssessmentsService::get_competency_assessment($assessmentId);
        if (!$data || empty($data['competencies'])) {
            return [];
        }

        return array_map(
            static fn(array $row) => (object) $row,
            $data['competencies']
        );
    }

    private static function get_result_map(int $assessmentId): array {
        $data = AssessmentsService::get_competency_assessment($assessmentId);
        $rows = $data['results'] ?? [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']][(int) $row['competency_id']] = (string) $row['observed_status'];
        }
        return $map;
    }

    private static function get_attendance_map(int $assessmentId): array {
        return AssessmentsService::get_assessment_attendance_map($assessmentId);
    }

    private static function get_current_status_map(array $userIds, array $competencyIds, int $yearId): array {
        global $wpdb;
        if (empty($userIds) || empty($competencyIds) || $yearId <= 0) {
            return [];
        }

        $tblUC = self::table('user_competencies');
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $competencyIds = array_values(array_unique(array_map('intval', $competencyIds)));
        $inUsers = implode(',', $userIds);
        $inComps = implode(',', $competencyIds);

        $rows = $wpdb->get_results(
            "SELECT user_id, competency_id, status
             FROM {$tblUC}
             WHERE year_id = " . (int) $yearId . "
               AND user_id IN ({$inUsers})
               AND competency_id IN ({$inComps})"
        );

        $map = [];
        foreach ($rows ?: [] as $row) {
            $map[(int) $row->user_id][(int) $row->competency_id] = (string) $row->status;
        }
        return $map;
    }

    private static function group_competencies(array $competencies): array {
        $grouped = [];
        foreach ($competencies as $c) {
            $bucket = trim(($c->track ?? '') . ' · ' . ($c->level ?? ''));
            if ($bucket === '·') {
                $bucket = 'Autres';
            }
            $domain = (string) ($c->domain ?? 'Sans domaine');
            $grouped[$bucket][$domain][] = $c;
        }
        return $grouped;
    }

    private static function handle_post(): void {
        if (empty($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            add_settings_error('ouinpo_assessments', 'nonce', 'Nonce invalide.', 'error');
            return;
        }

        $op = isset($_POST['op']) ? sanitize_key($_POST['op']) : '';
        switch ($op) {
            case 'save_assessment':
                self::handle_save_assessment();
                return;
        
            case 'duplicate_assessment':
                self::handle_duplicate_assessment();
                return;

            case 'create_version_b':
                self::handle_create_version_b();
                return;

            case 'save_assessment_items':
                self::handle_save_assessment_items();
                return;

            case 'replace_assessment_item':
                self::handle_replace_assessment_item();
                return;
        
            case 'delete_assessment':
                self::handle_delete_assessment();
                return;
        
            case 'save_results':
                self::handle_save_results();
                return;
        }
    }

    private static function handle_save_assessment(): void {
        $assessmentId = isset($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : 0;

        $result = AssessmentsService::save_competency_assessment([
            'title'          => $_POST['title'] ?? '',
            'group_id'       => $_POST['group_id'] ?? 0,
            'due_on'         => $_POST['due_on'] ?? '',
            'notes'          => $_POST['notes'] ?? '',
            'competency_ids' => (array) ($_POST['competency_ids'] ?? []),
        ], $assessmentId);

        if (is_wp_error($result)) {
            add_settings_error(
                'ouinpo_assessments',
                'save_assessment',
                $result->get_error_message(),
                'error'
            );
            return;
        }

        self::redirect([
            'action' => 'grade',
            'id'     => (int) $result,
            $assessmentId > 0 ? 'updated' : 'created' => 1,
        ]);
    }

    private static function table_columns(string $table): array {
        global $wpdb;
    
        return $wpdb->get_col($wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s",
            DB_NAME,
            $table
        )) ?: [];
    }
    
    private static function handle_duplicate_assessment(): void {
        global $wpdb;
    
        $sourceId = isset($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : 0;
    
        if ($sourceId <= 0) {
            add_settings_error('ouinpo_assessments', 'duplicate_missing', 'DS source introuvable.', 'error');
            return;
        }
    
        $source = self::get_assessment($sourceId);
    
        if (!$source) {
            add_settings_error('ouinpo_assessments', 'duplicate_not_found', 'DS source introuvable.', 'error');
            return;
        }
    
        $competencyIds = self::get_assessment_competency_ids($sourceId);
    
        if (empty($competencyIds)) {
            add_settings_error('ouinpo_assessments', 'duplicate_no_competencies', 'Impossible de dupliquer ce DS : aucune compétence associée.', 'error');
            return;
        }
    
        $newTitle = (string) $source->title;
    
        if (!str_contains($newTitle, '— copie')) {
            $newTitle .= ' — copie';
        } else {
            $newTitle .= ' 2';
        }
    
        $newId = AssessmentsService::save_competency_assessment([
            'title'          => $newTitle,
            'group_id'       => (int) $source->group_id,
            'due_on'         => (string) $source->due_on,
            'notes'          => (string) ($source->notes ?? ''),
            'competency_ids' => $competencyIds,
        ], 0);
    
        if (is_wp_error($newId)) {
            add_settings_error(
                'ouinpo_assessments',
                'duplicate_failed',
                $newId->get_error_message(),
                'error'
            );
            return;
        }
    
        self::duplicate_assessment_items($sourceId, (int) $newId);
    
        self::redirect([
            'action'     => 'edit',
            'id'         => (int) $newId,
            'duplicated' => 1,
        ]);
    }
    
    private static function duplicate_assessment_items(int $sourceId, int $targetId): void {
        global $wpdb;
    
        if ($sourceId <= 0 || $targetId <= 0) {
            return;
        }
    
        $tblAI = self::table('assessment_items');
        $columns = self::table_columns($tblAI);
    
        $hasSortOrder = in_array('sort_order', $columns, true);
        $hasPoints = in_array('points', $columns, true);
    
        $select = ['id', 'exercise_id'];
    
        if ($hasSortOrder) {
            $select[] = 'sort_order';
        }
    
        if ($hasPoints) {
            $select[] = 'points';
        }
    
        $orderBy = $hasSortOrder
            ? 'sort_order ASC, id ASC'
            : 'id ASC';
    
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT " . implode(', ', $select) . "
             FROM {$tblAI}
             WHERE assessment_id = %d
             ORDER BY {$orderBy}",
            $sourceId
        )) ?: [];
    
        if (empty($items)) {
            return;
        }
    
        $position = 1;
    
        foreach ($items as $item) {
            $data = [
                'assessment_id' => $targetId,
                'exercise_id'   => (int) $item->exercise_id,
            ];
    
            $formats = ['%d', '%d'];
    
            if ($hasSortOrder) {
                $data['sort_order'] = isset($item->sort_order)
                    ? (int) $item->sort_order
                    : $position;
    
                $formats[] = '%d';
            }
    
            if ($hasPoints) {
                $data['points'] = isset($item->points)
                    ? $item->points
                    : null;
    
                $formats[] = '%f';
            }
    
            $wpdb->insert($tblAI, $data, $formats);
    
            $position++;
        }
    }

    private static function ensure_assessment_item_edit_columns(): void {
        global $wpdb;
    
        $table = self::table('assessment_items');
        $columns = self::table_columns($table);
    
        if (!in_array('sort_order', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER exercise_id");
        }
    
        if (!in_array('points', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN points DECIMAL(5,2) NULL AFTER sort_order");
        }
    }
    
    private static function get_competency_ids_from_assessment_items(int $assessmentId): array {
        global $wpdb;
    
        if ($assessmentId <= 0) {
            return [];
        }
    
        $tblAI = self::table('assessment_items');
        $tblEC = self::table('exercise_competency');
    
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT ec.competency_id
             FROM {$tblAI} ai
             JOIN {$tblEC} ec ON ec.exercise_id = ai.exercise_id
             WHERE ai.assessment_id = %d
             ORDER BY ec.competency_id ASC",
            $assessmentId
        )) ?: []);
    }
    
    private static function handle_save_assessment_items(): void {
        global $wpdb;
    
        self::ensure_assessment_item_edit_columns();
    
        $assessmentId = isset($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : 0;
    
        if ($assessmentId <= 0) {
            add_settings_error('ouinpo_assessments', 'items_missing', 'DS introuvable.', 'error');
            return;
        }
    
        $assessment = self::get_assessment($assessmentId);
    
        if (!$assessment) {
            add_settings_error('ouinpo_assessments', 'items_not_found', 'DS introuvable.', 'error');
            return;
        }
    
        $tblAI = self::table('assessment_items');
    
        $deleteIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($_POST['delete_exercise_ids'] ?? [])
        ))));
    
        if (!empty($deleteIds)) {
            $inDelete = implode(',', $deleteIds);
    
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tblAI}
                 WHERE assessment_id = %d
                   AND exercise_id IN ({$inDelete})",
                $assessmentId
            ));
        }
    
        $sortOrders = (array) ($_POST['sort_order'] ?? []);
        $pointsRows = (array) ($_POST['points'] ?? []);
    
        foreach ($sortOrders as $exerciseId => $sortOrder) {
            $exerciseId = (int) $exerciseId;
    
            if ($exerciseId <= 0 || in_array($exerciseId, $deleteIds, true)) {
                continue;
            }
    
            $sortOrder = max(1, (int) $sortOrder);
    
            $rawPoints = isset($pointsRows[$exerciseId])
                ? str_replace(',', '.', (string) $pointsRows[$exerciseId])
                : '';
    
            $points = is_numeric($rawPoints) ? (float) $rawPoints : null;
    
            $wpdb->update(
                $tblAI,
                [
                    'sort_order' => $sortOrder,
                    'points'     => $points,
                ],
                [
                    'assessment_id' => $assessmentId,
                    'exercise_id'   => $exerciseId,
                ],
                ['%d', '%f'],
                ['%d', '%d']
            );
        }
    
        if (!empty($_POST['sync_competencies'])) {
            $competencyIds = self::get_competency_ids_from_assessment_items($assessmentId);
    
            if (!empty($competencyIds)) {
                $result = AssessmentsService::save_competency_assessment([
                    'title'          => (string) $assessment->title,
                    'group_id'       => (int) $assessment->group_id,
                    'due_on'         => (string) $assessment->due_on,
                    'notes'          => (string) ($assessment->notes ?? ''),
                    'competency_ids' => $competencyIds,
                ], $assessmentId);
    
                if (is_wp_error($result)) {
                    add_settings_error(
                        'ouinpo_assessments',
                        'items_sync_failed',
                        $result->get_error_message(),
                        'error'
                    );
                    return;
                }
            }
        }
    
        self::redirect([
            'action'        => 'items',
            'id'            => $assessmentId,
            'items_updated' => 1,
        ]);
    }

    private static function version_b_title(string $title): string {
        $title = trim($title);
    
        if ($title === '') {
            return 'Devoir surveillé — version B';
        }
    
        if (stripos($title, 'version B') !== false) {
            return $title . ' — copie';
        }
    
        if (stripos($title, 'version A') !== false) {
            return str_ireplace('version A', 'version B', $title);
        }
    
        return $title . ' — version B';
    }
    
    private static function handle_create_version_b(): void {
        global $wpdb;
    
        self::ensure_assessment_item_edit_columns();
    
        $sourceId = isset($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : 0;
    
        if ($sourceId <= 0) {
            add_settings_error('ouinpo_assessments', 'version_b_missing', 'DS source introuvable.', 'error');
            return;
        }
    
        $source = self::get_assessment($sourceId);
    
        if (!$source) {
            add_settings_error('ouinpo_assessments', 'version_b_not_found', 'DS source introuvable.', 'error');
            return;
        }
    
        $competencyIds = self::get_assessment_competency_ids($sourceId);
    
        if (empty($competencyIds)) {
            add_settings_error('ouinpo_assessments', 'version_b_no_competencies', 'Impossible de créer une version B : aucune compétence associée au DS source.', 'error');
            return;
        }
    
        $sourceItems = self::get_assessment_items($sourceId);
    
        if (empty($sourceItems)) {
            add_settings_error('ouinpo_assessments', 'version_b_no_items', 'Impossible de créer une version B : ce DS ne contient aucun exercice.', 'error');
            return;
        }
    
        $newTitle = self::version_b_title((string) $source->title);
    
        $newId = AssessmentsService::save_competency_assessment([
            'title'          => $newTitle,
            'group_id'       => (int) $source->group_id,
            'due_on'         => (string) $source->due_on,
            'notes'          => (string) ($source->notes ?? ''),
            'competency_ids' => $competencyIds,
        ], 0);
    
        if (is_wp_error($newId)) {
            add_settings_error(
                'ouinpo_assessments',
                'version_b_failed',
                $newId->get_error_message(),
                'error'
            );
            return;
        }
    
        $newId = (int) $newId;
    
        // On duplique le modèle du DS : exercices, ordre, points.
        // Les résultats élèves et absences ne sont pas copiés.
        self::duplicate_assessment_items($sourceId, $newId);
    
        $groupId = !empty($source->group_id) ? (int) $source->group_id : 0;
    
        $tblAI = self::table('assessment_items');
    
        $items = self::get_assessment_items($newId);
    
        $report = [
            'replaced' => [],
            'kept'     => [],
            'failed'   => [],
        ];
    
        foreach ($items as $item) {
            $oldExerciseId = (int) $item->exercise_id;
    
            if ($oldExerciseId <= 0) {
                continue;
            }
    
            $seenCount = self::get_class_seen_count($groupId, $oldExerciseId);
            $solvedCount = self::get_class_solved_count($groupId, $oldExerciseId);
    
            // Version prudente : on ne remplace que les exercices déjà vus par la classe.
            if ($groupId <= 0 || $seenCount <= 0) {
                $report['kept'][] = [
                    'old_id'   => $oldExerciseId,
                    'old_title'=> (string) $item->title,
                    'reason'   => $groupId <= 0 ? 'Aucune classe associée.' : 'Non vu par la classe.',
                ];
                continue;
            }
    
            $candidates = self::get_replacement_candidates($newId, $oldExerciseId);
    
            if (empty($candidates)) {
                $report['failed'][] = [
                    'old_id'      => $oldExerciseId,
                    'old_title'   => (string) $item->title,
                    'seen_count'  => $seenCount,
                    'solved_count'=> $solvedCount,
                    'reason'      => 'Aucun remplaçant non vu trouvé.',
                ];
                continue;
            }
    
            $candidate = $candidates[0];
            $newExerciseId = (int) $candidate->id;
    
            if ($newExerciseId <= 0 || $newExerciseId === $oldExerciseId) {
                $report['failed'][] = [
                    'old_id'      => $oldExerciseId,
                    'old_title'   => (string) $item->title,
                    'seen_count'  => $seenCount,
                    'solved_count'=> $solvedCount,
                    'reason'      => 'Candidat invalide.',
                ];
                continue;
            }
    
            $updated = $wpdb->update(
                $tblAI,
                [
                    'exercise_id' => $newExerciseId,
                ],
                [
                    'assessment_id' => $newId,
                    'exercise_id'   => $oldExerciseId,
                ],
                ['%d'],
                ['%d', '%d']
            );
    
            if ($updated === false) {
                $report['failed'][] = [
                    'old_id'      => $oldExerciseId,
                    'old_title'   => (string) $item->title,
                    'seen_count'  => $seenCount,
                    'solved_count'=> $solvedCount,
                    'reason'      => 'Erreur SQL pendant le remplacement.',
                ];
                continue;
            }
    
            $report['replaced'][] = [
                'old_id'       => $oldExerciseId,
                'old_title'    => (string) $item->title,
                'new_id'       => $newExerciseId,
                'new_title'    => (string) $candidate->title,
                'seen_count'   => $seenCount,
                'solved_count' => $solvedCount,
            ];
        }
    
        self::sync_assessment_competencies_from_items($newId);
    
        $reportKey = 'ouinpo_version_b_report_' . get_current_user_id() . '_' . $newId;
        set_transient($reportKey, $report, 10 * MINUTE_IN_SECONDS);
    
        self::redirect([
            'action'            => 'items',
            'id'                => $newId,
            'version_b_created' => 1,
        ]);
    }
    
    private static function render_version_b_report(int $assessmentId): void {
        $reportKey = 'ouinpo_version_b_report_' . get_current_user_id() . '_' . $assessmentId;
        $report = get_transient($reportKey);
    
        if (empty($report) || !is_array($report)) {
            return;
        }
    
        delete_transient($reportKey);
    
        $replaced = isset($report['replaced']) && is_array($report['replaced']) ? $report['replaced'] : [];
        $kept = isset($report['kept']) && is_array($report['kept']) ? $report['kept'] : [];
        $failed = isset($report['failed']) && is_array($report['failed']) ? $report['failed'] : [];
    
        ?>
        <div class="notice notice-info ouinpo-version-report">
            <p class="ouinpo-version-report__title">
                <strong>Bilan de création de la version B</strong>
            </p>
    
            <ul class="ouinpo-version-report__list">
                <li><?php echo (int) count($replaced); ?> exercice(s) remplacé(s).</li>
                <li><?php echo (int) count($kept); ?> exercice(s) conservé(s).</li>
                <li><?php echo (int) count($failed); ?> exercice(s) déjà vu(s) sans remplaçant trouvé.</li>
            </ul>
    
            <?php if (!empty($replaced)): ?>
                <details class="ouinpo-version-report__details">
                    <summary><strong>Voir les remplacements effectués</strong></summary>
                    <ul class="ouinpo-version-report__list">
                        <?php foreach ($replaced as $row): ?>
                            <li>
                                #<?php echo (int) $row['old_id']; ?>
                                — <?php echo esc_html($row['old_title']); ?>
                                remplacé par
                                #<?php echo (int) $row['new_id']; ?>
                                — <?php echo esc_html($row['new_title']); ?>
                                <br>
                                <span class="ouinpo-assessment-muted">
                                    Vu par <?php echo (int) $row['seen_count']; ?> élève(s),
                                    réussi par <?php echo (int) $row['solved_count']; ?>.
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
    
            <?php if (!empty($failed)): ?>
                <details class="ouinpo-version-report__details">
                    <summary><strong>Voir les exercices conservés faute de remplaçant</strong></summary>
                    <ul class="ouinpo-version-report__list">
                        <?php foreach ($failed as $row): ?>
                            <li>
                                #<?php echo (int) $row['old_id']; ?>
                                — <?php echo esc_html($row['old_title']); ?>
                                <br>
                                <span class="ouinpo-assessment-muted">
                                    Vu par <?php echo (int) $row['seen_count']; ?> élève(s),
                                    réussi par <?php echo (int) $row['solved_count']; ?>.
                                    Raison : <?php echo esc_html($row['reason']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
    
            <?php if (!empty($kept)): ?>
                <details class="ouinpo-version-report__details">
                    <summary><strong>Voir les exercices conservés</strong></summary>
                    <ul class="ouinpo-version-report__list">
                        <?php foreach ($kept as $row): ?>
                            <li>
                                #<?php echo (int) $row['old_id']; ?>
                                — <?php echo esc_html($row['old_title']); ?>
                                <br>
                                <span class="ouinpo-assessment-muted">
                                    <?php echo esc_html($row['reason']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function handle_delete_assessment(): void {
        global $wpdb;

        $assessmentId = isset($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : 0;
        if ($assessmentId <= 0) {
            add_settings_error('ouinpo_assessments', 'delete_missing', 'DS introuvable.', 'error');
            return;
        }

        $wpdb->delete(self::table('assessment_results'), ['assessment_id' => $assessmentId], ['%d']);
        $wpdb->delete(self::table('assessment_attendance'), ['assessment_id' => $assessmentId], ['%d']);
        $wpdb->delete(self::table('assessment_competencies'), ['assessment_id' => $assessmentId], ['%d']);
        $wpdb->delete(self::table('assessment_items'), ['assessment_id' => $assessmentId], ['%d']);
        $wpdb->delete(self::table('assessments'), ['id' => $assessmentId], ['%d']);

        self::redirect(['deleted' => 1]);
    }

    private static function handle_save_results(): void {
        $assessmentId   = isset($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : 0;
        $applyProgress  = !empty($_POST['apply_progression']);
        $rawResults     = (array) ($_POST['results'] ?? []);
        $attendance     = (array) ($_POST['attendance'] ?? []);
        $absentUserIds  = [];

        foreach ($attendance as $userId => $row) {
            if (!empty($row['is_absent'])) {
                $absentUserIds[(int) $userId] = true;
            }
        }

        $saveAttendance = AssessmentsService::save_assessment_attendance(
            $assessmentId,
            $attendance,
            get_current_user_id()
        );

        if (is_wp_error($saveAttendance)) {
            add_settings_error(
                'ouinpo_assessments',
                'save_attendance',
                $saveAttendance->get_error_message(),
                'error'
            );
            return;
        }

        $results = [];
        foreach ($rawResults as $userId => $competencies) {
            $userId = (int) $userId;
            if ($userId <= 0 || isset($absentUserIds[$userId]) || !is_array($competencies)) {
                continue;
            }

            foreach ($competencies as $competencyId => $status) {
                $results[] = [
                    'user_id'         => $userId,
                    'competency_id'   => (int) $competencyId,
                    'observed_status' => (string) $status,
                    'note'            => null,
                ];
            }
        }

        $ok = AssessmentsService::save_competency_results(
            $assessmentId,
            $results,
            $applyProgress,
            get_current_user_id()
        );

        if (is_wp_error($ok)) {
            add_settings_error(
                'ouinpo_assessments',
                'save_results',
                $ok->get_error_message(),
                'error'
            );
            return;
        }

        self::redirect([
            'action' => 'grade',
            'id'     => $assessmentId,
            'graded' => 1,
        ]);
    }

    private static function get_list_rows(int $groupId = 0): array {
        $rows = AssessmentsService::list_competency_assessments($groupId);

        return array_map(
            static fn(array $row) => (object) $row,
            $rows
        );
    }

    private static function resolve_current(string $action, int $id): array {
        $activeYearId = self::get_active_year_id();
        $groups = self::get_groups();

        $current = (object) [
            'id'       => 0,
            'title'    => '',
            'group_id' => 0,
            'due_on'   => current_time('Y-m-d'),
            'notes'    => '',
        ];
        $selectedCompetencyIds = [];

        if ($action === 'edit' && $id > 0) {
            $assessment = self::get_assessment($id);
            if ($assessment) {
                $current = $assessment;
                $selectedCompetencyIds = self::get_assessment_competency_ids($id);
            }
        }

        if (!empty($_GET['group_id'])) {
            $current->group_id = (int) $_GET['group_id'];
        }

        if (!empty($_POST['op']) && $_POST['op'] === 'save_assessment') {
            $current = (object) [
                'id'       => isset($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : 0,
                'title'    => sanitize_text_field($_POST['title'] ?? ''),
                'group_id' => isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0,
                'due_on'   => sanitize_text_field($_POST['due_on'] ?? current_time('Y-m-d')),
                'notes'    => wp_kses_post($_POST['notes'] ?? ''),
            ];
            $selectedCompetencyIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['competency_ids'] ?? [])))));
        }

        if ((int) $current->group_id <= 0 && $activeYearId > 0) {
            foreach ($groups as $group) {
                if ((int) $group->year_id === $activeYearId) {
                    $current->group_id = (int) $group->id;
                    break;
                }
            }
        }

        return [$current, $selectedCompetencyIds, $groups];
    }

    private static function get_assessment_items(int $assessmentId): array {
        global $wpdb;
    
        if ($assessmentId <= 0) {
            return [];
        }
    
        $tblAI = self::table('assessment_items');
        $tblE  = self::table('exercises');
        $tblD  = self::table('difficulties');
        $tblEM = self::table('exam_meta');
        $tblEC = self::table('exercise_competency');
        $tblC  = self::table('competencies');
    
        return $wpdb->get_results($wpdb->prepare(
            "SELECT
                ai.exercise_id,
                ai.sort_order,
                ai.points,
                e.title,
                e.slug,
                e.statement,
                d.label AS difficulty_label,
                d.slug AS difficulty_slug,
                em.source_type,
                em.session_label,
                em.year_label,
                em.center_label,
                em.theme_bac,
                em.bac_format,
                em.estimated_minutes,
                GROUP_CONCAT(
                    DISTINCT CONCAT(c.domain, ' — ', LEFT(c.competency, 140))
                    ORDER BY c.domain ASC
                    SEPARATOR '||'
                ) AS competency_labels
             FROM {$tblAI} ai
             JOIN {$tblE} e ON e.id = ai.exercise_id
             LEFT JOIN {$tblD} d ON d.id = e.difficulty_id
             LEFT JOIN {$tblEM} em ON em.exercise_id = e.id
             LEFT JOIN {$tblEC} ec ON ec.exercise_id = e.id
             LEFT JOIN {$tblC} c ON c.id = ec.competency_id
             WHERE ai.assessment_id = %d
             GROUP BY
                ai.exercise_id,
                ai.sort_order,
                ai.points,
                e.title,
                e.slug,
                e.statement,
                d.label,
                d.slug,
                em.source_type,
                em.session_label,
                em.year_label,
                em.center_label,
                em.theme_bac,
                em.bac_format,
                em.estimated_minutes
             ORDER BY ai.sort_order ASC, ai.id ASC",
            $assessmentId
        )) ?: [];
    }

    private static function get_exercise_hints(int $exerciseId): array {
        global $wpdb;
    
        if ($exerciseId <= 0) {
            return [];
        }
    
        $tblH = self::table('hints');
    
        return $wpdb->get_results($wpdb->prepare(
            "SELECT hint_order, content
             FROM {$tblH}
             WHERE exercise_id = %d
             ORDER BY hint_order ASC, id ASC",
            $exerciseId
        )) ?: [];
    }

    private static function get_exercise_solutions(int $exerciseId): array {
        global $wpdb;
    
        if ($exerciseId <= 0) {
            return [];
        }
    
        $tblS = self::table('solutions');
    
        return $wpdb->get_results($wpdb->prepare(
            "SELECT title, content, solution_order, is_official
             FROM {$tblS}
             WHERE exercise_id = %d
             ORDER BY is_official DESC, solution_order ASC, id ASC",
            $exerciseId
        )) ?: [];
    }

    private static function parse_competency_labels(?string $raw): array {
        $raw = trim((string) $raw);
    
        if ($raw === '') {
            return [];
        }
    
        return array_values(array_filter(array_map('trim', explode('||', $raw))));
    }

    private static function format_points($points): string {
        if ($points === null || $points === '') {
            return '—';
        }
    
        $value = (float) $points;
        $txt = number_format($value, 2, ',', ' ');
        $txt = rtrim(rtrim($txt, '0'), ',');
    
        return $txt;
    }

    private static function source_label_for_print(?string $source): string {
        return match ((string) $source) {
            'annale'   => 'Annale',
            'inspired' => 'Inspiré d’annales',
            'type_bac' => 'Type bac',
            default    => '',
        };
    }

    private static function get_assessment_totals(array $items): array {
        $exerciseCount = count($items);
        $totalMinutes = 0;
        $totalPoints = 0.0;
        $hasPoints = false;
    
        foreach ($items as $item) {
            if (!empty($item->estimated_minutes)) {
                $totalMinutes += (int) $item->estimated_minutes;
            }
    
            if (isset($item->points) && $item->points !== null && $item->points !== '' && is_numeric($item->points)) {
                $totalPoints += (float) $item->points;
                $hasPoints = true;
            }
        }
    
        return [
            'exercise_count' => $exerciseCount,
            'total_minutes'  => $totalMinutes,
            'total_points'   => $totalPoints,
            'has_points'     => $hasPoints,
        ];
    }
    
    private static function format_minutes(int $minutes): string {
        if ($minutes <= 0) {
            return '—';
        }
    
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
    
        if ($hours > 0 && $mins > 0) {
            return $hours . ' h ' . str_pad((string) $mins, 2, '0', STR_PAD_LEFT);
        }
    
        if ($hours > 0) {
            return $hours . ' h';
        }
    
        return $minutes . ' min';
    }
    
    private static function points_label_from_totals(array $totals): string {
        if (empty($totals['has_points'])) {
            return '—';
        }
    
        return self::format_points($totals['total_points']) . ' points';
    }

    private static function render_print_styles(): void {
    }
    private static function get_assessment_item_for_replace(int $assessmentId, int $exerciseId): ?object {
        global $wpdb;
    
        if ($assessmentId <= 0 || $exerciseId <= 0) {
            return null;
        }
    
        $tblAI = self::table('assessment_items');
        $tblE  = self::table('exercises');
        $tblD  = self::table('difficulties');
        $tblEM = self::table('exam_meta');
    
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                ai.assessment_id,
                ai.exercise_id,
                ai.sort_order,
                ai.points,
                e.title,
                e.slug,
                e.difficulty_id,
                e.level_id,
                d.label AS difficulty_label,
                d.slug AS difficulty_slug,
                em.estimated_minutes,
                em.source_type,
                em.exam_type
             FROM {$tblAI} ai
             JOIN {$tblE} e ON e.id = ai.exercise_id
             LEFT JOIN {$tblD} d ON d.id = e.difficulty_id
             LEFT JOIN {$tblEM} em ON em.exercise_id = e.id
             WHERE ai.assessment_id = %d
               AND ai.exercise_id = %d
             LIMIT 1",
            $assessmentId,
            $exerciseId
        ));
    
        return $row ?: null;
    }
    
    private static function get_exercise_competency_ids(int $exerciseId): array {
        global $wpdb;
    
        if ($exerciseId <= 0) {
            return [];
        }
    
        $tblEC = self::table('exercise_competency');
    
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT competency_id
             FROM {$tblEC}
             WHERE exercise_id = %d
             ORDER BY competency_id ASC",
            $exerciseId
        )) ?: []);
    }
    
    private static function get_existing_exercise_ids_for_assessment(int $assessmentId): array {
        global $wpdb;
    
        if ($assessmentId <= 0) {
            return [];
        }
    
        $tblAI = self::table('assessment_items');
    
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT exercise_id
             FROM {$tblAI}
             WHERE assessment_id = %d",
            $assessmentId
        )) ?: []);
    }

    private static function get_class_seen_count(int $groupId, int $exerciseId): int {
        global $wpdb;
    
        if ($groupId <= 0 || $exerciseId <= 0) {
            return 0;
        }
    
        $tblGM = self::table('group_members');
        $tblUS = self::table('user_status');
    
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT us.user_id)
             FROM {$tblUS} us
             JOIN {$tblGM} gm ON gm.user_id = us.user_id
             WHERE gm.group_id = %d
               AND gm.role = 'student'
               AND us.exercise_id = %d
               AND us.status IN ('attempted', 'solved')",
            $groupId,
            $exerciseId
        ));
    }
    
    private static function get_class_solved_count(int $groupId, int $exerciseId): int {
        global $wpdb;
    
        if ($groupId <= 0 || $exerciseId <= 0) {
            return 0;
        }
    
        $tblGM = self::table('group_members');
        $tblUS = self::table('user_status');
    
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT us.user_id)
             FROM {$tblUS} us
             JOIN {$tblGM} gm ON gm.user_id = us.user_id
             WHERE gm.group_id = %d
               AND gm.role = 'student'
               AND us.exercise_id = %d
               AND us.status = 'solved'",
            $groupId,
            $exerciseId
        ));
    }
    
    private static function get_replacement_candidates(int $assessmentId, int $sourceExerciseId): array {
        global $wpdb;
    
        $source = self::get_assessment_item_for_replace($assessmentId, $sourceExerciseId);
    
        if (!$source) {
            return [];
        }

        $assessment = self::get_assessment($assessmentId);
        $groupId = $assessment && !empty($assessment->group_id)
            ? (int) $assessment->group_id
            : 0;
    
        $competencyIds = self::get_exercise_competency_ids($sourceExerciseId);
    
        if (empty($competencyIds)) {
            return [];
        }
    
        $existingIds = self::get_existing_exercise_ids_for_assessment($assessmentId);
    
        if (empty($existingIds)) {
            $existingIds = [$sourceExerciseId];
        }
    
        $tblE   = self::table('exercises');
        $tblD   = self::table('difficulties');
        $tblEM  = self::table('exam_meta');
        $tblEC  = self::table('exercise_competency');
        $tblC   = self::table('competencies');
        $tblESL = self::table('exercise_school_level');
        $tblGM  = self::table('group_members');
        $tblUS  = self::table('user_status');
    
        $compIn = implode(',', array_map('intval', $competencyIds));
        $existingIn = implode(',', array_map('intval', $existingIds));
    
        $where = [];
        $args = [];
    
        $where[] = "e.is_active = 1";
        $where[] = "e.id NOT IN ({$existingIn})";
        $where[] = "(em.exam_type IS NULL OR em.exam_type <> 'practical_subject')";
        $where[] = "EXISTS (
            SELECT 1
            FROM {$tblEC} ecx
            WHERE ecx.exercise_id = e.id
              AND ecx.competency_id IN ({$compIn})
        )";
        
        if ($groupId > 0) {
            $where[] = "NOT EXISTS (
                SELECT 1
                FROM {$tblUS} us_seen
                JOIN {$tblGM} gm_seen ON gm_seen.user_id = us_seen.user_id
                WHERE gm_seen.group_id = %d
                  AND gm_seen.role = 'student'
                  AND us_seen.exercise_id = e.id
                  AND us_seen.status IN ('attempted', 'solved')
            )";
            $args[] = $groupId;
        }        
    
        if (!empty($source->level_id)) {
            $where[] = "(e.level_id = %d OR EXISTS (
                SELECT 1
                FROM {$tblESL} esl
                WHERE esl.exercise_id = e.id
                  AND esl.school_level_id = %d
            ))";
            $args[] = (int) $source->level_id;
            $args[] = (int) $source->level_id;
        }
    
        $whereSql = implode(' AND ', $where);
    
        $difficultyId = !empty($source->difficulty_id) ? (int) $source->difficulty_id : 0;
    
        $sql = "
            SELECT
                e.id,
                e.title,
                e.slug,
                e.difficulty_id,
                d.label AS difficulty_label,
                d.slug AS difficulty_slug,
                em.estimated_minutes,
                em.source_type,
                em.session_label,
                em.year_label,
                em.center_label,
                COUNT(DISTINCT ec.competency_id) AS nb_common_competencies,
                GROUP_CONCAT(
                    DISTINCT CONCAT(c.domain, ' — ', LEFT(c.competency, 120))
                    ORDER BY c.domain ASC
                    SEPARATOR '||'
                ) AS competency_labels
            FROM {$tblE} e
            LEFT JOIN {$tblD} d ON d.id = e.difficulty_id
            LEFT JOIN {$tblEM} em ON em.exercise_id = e.id
            JOIN {$tblEC} ec ON ec.exercise_id = e.id AND ec.competency_id IN ({$compIn})
            JOIN {$tblC} c ON c.id = ec.competency_id
            WHERE {$whereSql}
            GROUP BY
                e.id,
                e.title,
                e.slug,
                e.difficulty_id,
                d.label,
                d.slug,
                em.estimated_minutes,
                em.source_type,
                em.session_label,
                em.year_label,
                em.center_label
            ORDER BY
                nb_common_competencies DESC,
                CASE
                    WHEN %d > 0 AND e.difficulty_id = %d THEN 0
                    WHEN %d > 0 THEN ABS(e.difficulty_id - %d)
                    ELSE 9
                END ASC,
                COALESCE(em.estimated_minutes, 999) ASC,
                e.title ASC
            LIMIT 25
        ";
    
        $args[] = $difficultyId;
        $args[] = $difficultyId;
        $args[] = $difficultyId;
        $args[] = $difficultyId;
    
        return $wpdb->get_results($wpdb->prepare($sql, $args)) ?: [];
    }
    
    private static function sync_assessment_competencies_from_items(int $assessmentId): void {
        if ($assessmentId <= 0) {
            return;
        }
    
        $assessment = self::get_assessment($assessmentId);
    
        if (!$assessment) {
            return;
        }
    
        $competencyIds = self::get_competency_ids_from_assessment_items($assessmentId);
    
        if (empty($competencyIds)) {
            return;
        }
    
        AssessmentsService::save_competency_assessment([
            'title'          => (string) $assessment->title,
            'group_id'       => (int) $assessment->group_id,
            'due_on'         => (string) $assessment->due_on,
            'notes'          => (string) ($assessment->notes ?? ''),
            'competency_ids' => $competencyIds,
        ], $assessmentId);
    }
    
    private static function handle_replace_assessment_item(): void {
        global $wpdb;
    
        self::ensure_assessment_item_edit_columns();
    
        $assessmentId = isset($_POST['assessment_id']) ? (int) $_POST['assessment_id'] : 0;
        $oldExerciseId = isset($_POST['old_exercise_id']) ? (int) $_POST['old_exercise_id'] : 0;
        $newExerciseId = isset($_POST['new_exercise_id']) ? (int) $_POST['new_exercise_id'] : 0;
    
        if ($assessmentId <= 0 || $oldExerciseId <= 0 || $newExerciseId <= 0) {
            add_settings_error('ouinpo_assessments', 'replace_missing', 'Remplacement impossible : données manquantes.', 'error');
            return;
        }
    
        if ($oldExerciseId === $newExerciseId) {
            add_settings_error('ouinpo_assessments', 'replace_same', 'Le remplaçant est identique à l’exercice actuel.', 'error');
            return;
        }
    
        $currentItem = self::get_assessment_item_for_replace($assessmentId, $oldExerciseId);
    
        if (!$currentItem) {
            add_settings_error('ouinpo_assessments', 'replace_not_found', 'Exercice source introuvable dans ce DS.', 'error');
            return;
        }
    
        $existingIds = self::get_existing_exercise_ids_for_assessment($assessmentId);
    
        if (in_array($newExerciseId, $existingIds, true)) {
            add_settings_error('ouinpo_assessments', 'replace_duplicate', 'Cet exercice est déjà présent dans le DS.', 'error');
            return;
        }
    
        $tblAI = self::table('assessment_items');
        $tblE = self::table('exercises');
        $tblEM = self::table('exam_meta');
    
        $newExists = $wpdb->get_var($wpdb->prepare(
            "SELECT e.id
             FROM {$tblE} e
             LEFT JOIN {$tblEM} em ON em.exercise_id = e.id
             WHERE e.id = %d
               AND e.is_active = 1
               AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')
             LIMIT 1",
            $newExerciseId
        ));
    
        if (!$newExists) {
            add_settings_error('ouinpo_assessments', 'replace_bad_target', 'Exercice remplaçant invalide ou sujet pratique complet.', 'error');
            return;
        }
    
        $wpdb->update(
            $tblAI,
            [
                'exercise_id' => $newExerciseId,
            ],
            [
                'assessment_id' => $assessmentId,
                'exercise_id'   => $oldExerciseId,
            ],
            ['%d'],
            ['%d', '%d']
        );
    
        self::sync_assessment_competencies_from_items($assessmentId);
    
        self::redirect([
            'action'        => 'items',
            'id'            => $assessmentId,
            'item_replaced' => 1,
        ]);
    }

    private static function get_assessment_item_competency_labels(int $exerciseId): string {
        global $wpdb;
    
        if ($exerciseId <= 0) {
            return '';
        }
    
        $tblEC = self::table('exercise_competency');
        $tblC  = self::table('competencies');
    
        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT GROUP_CONCAT(
                DISTINCT CONCAT(c.domain, ' — ', LEFT(c.competency, 140))
                ORDER BY c.domain ASC
                SEPARATOR '||'
            )
             FROM {$tblEC} ec
             JOIN {$tblC} c ON c.id = ec.competency_id
             WHERE ec.exercise_id = %d",
            $exerciseId
        ));
    }

    private static function render_replace_item(int $assessmentId, int $exerciseId): void {
        $assessment = self::get_assessment($assessmentId);
    
        if (!$assessment) {
            echo '<div class="notice notice-error"><p>DS introuvable.</p></div>';
            return;
        }
    
        $source = self::get_assessment_item_for_replace($assessmentId, $exerciseId);
    
        if (!$source) {
            echo '<div class="notice notice-error"><p>Exercice introuvable dans ce DS.</p></div>';
            return;
        }
    
        $candidates = self::get_replacement_candidates($assessmentId, $exerciseId);
        $sourceCompetencies = self::parse_competency_labels(self::get_assessment_item_competency_labels($exerciseId));
        
        $groupId = !empty($assessment->group_id) ? (int) $assessment->group_id : 0;
        $sourceSeenCount = self::get_class_seen_count($groupId, $exerciseId);
        $sourceSolvedCount = self::get_class_solved_count($groupId, $exerciseId);
        ?>
        <h1 class="wp-heading-inline">Remplaçants possibles</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=items&id=' . (int) $assessmentId)); ?>" class="page-title-action">Retour aux exercices du DS</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=subject&id=' . (int) $assessmentId)); ?>" class="page-title-action">Sujet</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=correction&id=' . (int) $assessmentId)); ?>" class="page-title-action">Corrigé</a>
        <hr class="wp-header-end">
    
        <div class="card" style="max-width:none; padding:16px; margin-top:12px;">
            <p style="margin:0 0 8px;">
                <strong>DS :</strong> <?php echo esc_html($assessment->title); ?>
            </p>
    
            <p style="margin:0 0 8px;">
                <strong>Exercice à remplacer :</strong>
                #<?php echo (int) $source->exercise_id; ?> — <?php echo esc_html($source->title); ?>
            </p>
    
            <p style="margin:0;">
                <strong>Difficulté :</strong> <?php echo esc_html($source->difficulty_label ?: '—'); ?>
                <?php if (!empty($source->estimated_minutes)): ?>
                    &nbsp;|&nbsp; <strong>Durée :</strong> <?php echo (int) $source->estimated_minutes; ?> min
                <?php endif; ?>
                <?php if ($source->points !== null && $source->points !== ''): ?>
                    &nbsp;|&nbsp; <strong>Points conservés :</strong> <?php echo esc_html(self::format_points($source->points)); ?>
                <?php endif; ?>
            </p>
    
            <?php if (!empty($sourceCompetencies)): ?>
                <div style="margin-top:10px;">
                    <strong>Compétences de l’exercice source :</strong><br>
                    <?php foreach ($sourceCompetencies as $label): ?>
                        <span style="display:inline-block; margin:4px 4px 0 0; padding:2px 7px; border:1px solid #dcdcde; border-radius:999px; background:#f6f7f7; font-size:12px;">
                            <?php echo esc_html($label); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($groupId > 0): ?>
                <?php if ($sourceSeenCount > 0): ?>
                    <div style="margin-top:12px; padding:10px; border-left:4px solid #dba617; background:#fff8e5;">
                        <strong>Attention :</strong>
                        cet exercice a déjà été tenté ou réussi par
                        <?php echo (int) $sourceSeenCount; ?> élève(s) de cette classe
                        <?php if ($sourceSolvedCount > 0): ?>
                            , dont <?php echo (int) $sourceSolvedCount; ?> l’ont déclaré réussi
                        <?php endif; ?>.
                        Les remplaçants proposés ci-dessous excluent les exercices déjà vus par la classe.
                    </div>
                <?php else: ?>
                    <div style="margin-top:12px; padding:10px; border-left:4px solid #00a32a; background:#edfaef;">
                        Cet exercice ne semble pas avoir été tenté par les élèves de cette classe.
                        Les remplaçants proposés excluent tout de même les exercices déjà vus par la classe.
                    </div>
                <?php endif; ?>
            <?php endif; ?>            
            
        </div>
    
        <?php if (empty($candidates)): ?>
            <div class="notice notice-warning" style="margin-top:16px;">
                <p>Aucun remplaçant trouvé avec les critères actuels. Il faudra soit élargir la banque d’exercices, soit remplacer manuellement depuis le concepteur.</p>
            </div>
            <?php return; ?>
        <?php endif; ?>
    
        <table class="widefat striped" style="margin-top:16px;">
            <thead>
                <tr>
                    <th>Exercice proposé</th>
                    <th style="width:120px;">Difficulté</th>
                    <th style="width:120px;">Durée</th>
                    <th style="width:160px;">Action</th>
                </tr>
            </thead>
            <tbody>
                    <?php foreach ($candidates as $candidate): ?>
                        <?php
                        $candidateCompetencies = self::parse_competency_labels($candidate->competency_labels ?? '');
                        $candidateSeenCount = self::get_class_seen_count($groupId, (int) $candidate->id);
                        ?>
                    <tr>
                        <td>
                            <strong>#<?php echo (int) $candidate->id; ?> — <?php echo esc_html($candidate->title); ?></strong>
    
                            <div style="color:#646970; font-size:12px; margin-top:4px;">
                                Compétences communes : <?php echo (int) $candidate->nb_common_competencies; ?>
                                · Déjà vu par la classe : <?php echo (int) $candidateSeenCount; ?>
    
                                <?php if (!empty($candidate->source_type)): ?>
                                    · <?php echo esc_html(self::source_label_for_print($candidate->source_type)); ?>
                                <?php endif; ?>
    
                                <?php if (!empty($candidate->center_label) || !empty($candidate->session_label) || !empty($candidate->year_label)): ?>
                                    · <?php echo esc_html(trim(($candidate->center_label ?: '') . ' ' . ($candidate->session_label ?: '') . ' ' . ($candidate->year_label ?: ''))); ?>
                                <?php endif; ?>
                            </div>
    
                            <?php if (!empty($candidateCompetencies)): ?>
                                <div style="margin-top:8px;">
                                    <?php foreach (array_slice($candidateCompetencies, 0, 4) as $label): ?>
                                        <span style="display:inline-block; margin:2px 4px 2px 0; padding:2px 7px; border:1px solid #dcdcde; border-radius:999px; background:#f6f7f7; font-size:12px;">
                                            <?php echo esc_html($label); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
    
                        <td>
                            <?php echo esc_html($candidate->difficulty_label ?: '—'); ?>
                        </td>
    
                        <td>
                            <?php if (!empty($candidate->estimated_minutes)): ?>
                                <?php echo (int) $candidate->estimated_minutes; ?> min
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
    
                        <td>
                            <form method="post">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="op" value="replace_assessment_item">
                                <input type="hidden" name="assessment_id" value="<?php echo (int) $assessmentId; ?>">
                                <input type="hidden" name="old_exercise_id" value="<?php echo (int) $exerciseId; ?>">
                                <input type="hidden" name="new_exercise_id" value="<?php echo (int) $candidate->id; ?>">
                                <button type="submit" class="button button-primary button-small">
                                    Remplacer par celui-ci
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_assessment_admin_styles(): void {
    }

    private static function render_items(int $assessmentId): void {
        self::ensure_assessment_item_edit_columns();
    
        $assessment = self::get_assessment($assessmentId);
    
        if (!$assessment) {
            echo '<div class="notice notice-error"><p>DS introuvable.</p></div>';
            return;
        }
    
        $items = self::get_assessment_items($assessmentId);
        $totals = self::get_assessment_totals($items);
        $competencyCount = count(self::get_assessment_competency_ids($assessmentId));
        ?>
        <?php self::render_assessment_admin_styles(); ?>
        
        <h1 class="wp-heading-inline">Exercices du DS</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="page-title-action">Retour à la liste</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=grade&id=' . $assessmentId)); ?>" class="page-title-action">Saisie DS</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=subject&id=' . $assessmentId)); ?>" class="page-title-action">Sujet</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=correction&id=' . $assessmentId)); ?>" class="page-title-action">Corrigé</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=edit&id=' . $assessmentId)); ?>" class="page-title-action">Modifier le DS</a>
        <form method="post" style="display:inline;" onsubmit="return confirm('Créer une version B prudente de ce DS ? Les exercices déjà vus par la classe seront remplacés si un remplaçant non vu existe.');">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="op" value="create_version_b">
            <input type="hidden" name="assessment_id" value="<?php echo (int) $assessmentId; ?>">
            <button type="submit" class="page-title-action" style="cursor:pointer;">Créer une version B</button>
        </form>        
        <hr class="wp-header-end">
    
        <div class="ouinpo-ds-hero">
            <h2><?php echo esc_html($assessment->title); ?></h2>
            <p>
                Classe : <strong><?php echo esc_html($assessment->group_label ?: '—'); ?></strong>
                &nbsp;|&nbsp;
                Date : <strong><?php echo esc_html($assessment->due_on ?: '—'); ?></strong>
                &nbsp;|&nbsp;
                Année : <strong><?php echo esc_html($assessment->year_slug ?: '—'); ?></strong>
            </p>
        </div>
        
        <div class="ouinpo-ds-kpis">
            <div class="ouinpo-ds-kpi">
                <span>Exercices</span>
                <strong><?php echo (int) $totals['exercise_count']; ?></strong>
            </div>
        
            <div class="ouinpo-ds-kpi">
                <span>Durée indicative</span>
                <strong><?php echo esc_html(self::format_minutes((int) $totals['total_minutes'])); ?></strong>
            </div>
        
            <div class="ouinpo-ds-kpi">
                <span>Total</span>
                <strong><?php echo esc_html(self::points_label_from_totals($totals)); ?></strong>
            </div>
        
            <div class="ouinpo-ds-kpi">
                <span>Compétences</span>
                <strong><?php echo (int) $competencyCount; ?></strong>
            </div>
        </div>
        
        <?php self::render_version_b_report($assessmentId); ?>
    
        <?php if (empty($items)): ?>
            <div class="notice notice-warning" style="margin-top:16px;">
                <p>Ce DS ne contient aucun exercice dans <code>assessment_items</code>. Il a peut-être été créé avec l’ancien système uniquement par compétences.</p>
            </div>
            <?php return; ?>
        <?php endif; ?>
    
        <form method="post" style="margin-top:16px;">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="op" value="save_assessment_items">
            <input type="hidden" name="assessment_id" value="<?php echo (int) $assessmentId; ?>">
    
            <table class="widefat striped ouinpo-ds-table">
                <thead>
                    <tr>
                        <th style="width:80px;">Ordre</th>
                        <th>Exercice</th>
                        <th style="width:110px;">Points</th>
                        <th style="width:120px;">Durée</th>
                        <th style="width:130px;">Remplacer</th>
                        <th style="width:110px;">Retirer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <?php
                        $exerciseId = (int) $item->exercise_id;
                        $competencyLabels = self::parse_competency_labels($item->competency_labels ?? '');
                        $points = self::format_points($item->points ?? null);
                        $groupId = !empty($assessment->group_id) ? (int) $assessment->group_id : 0;
                        $seenCount = self::get_class_seen_count($groupId, $exerciseId);
                        $solvedCount = self::get_class_solved_count($groupId, $exerciseId);
                        ?>
                        <tr>
                            <td>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    name="sort_order[<?php echo $exerciseId; ?>]"
                                    value="<?php echo esc_attr((string) ((int) ($item->sort_order ?? ($index + 1)))); ?>"
                                    class="ouinpo-assessment-input-order"
                                >
                            </td>
    
                            <td>
                                <strong>#<?php echo $exerciseId; ?> — <?php echo esc_html($item->title); ?></strong>
    
                                <div class="ouinpo-assessment-item-meta">
                                    <?php if (!empty($item->difficulty_label)): ?>
                                        Difficulté : <?php echo esc_html($item->difficulty_label); ?>
                                    <?php else: ?>
                                        Difficulté : —
                                    <?php endif; ?>
    
                                    <?php if (!empty($item->source_type)): ?>
                                        · <?php echo esc_html(self::source_label_for_print($item->source_type)); ?>
                                    <?php endif; ?>
    
                                    <?php if (!empty($item->center_label) || !empty($item->session_label) || !empty($item->year_label)): ?>
                                        · <?php echo esc_html(trim(($item->center_label ?: '') . ' ' . ($item->session_label ?: '') . ' ' . ($item->year_label ?: ''))); ?>
                                    <?php endif; ?>
                                </div>
    
                                <?php if (!empty($competencyLabels)): ?>
                                    <div class="ouinpo-assessment-tags">
                                        <?php foreach (array_slice($competencyLabels, 0, 4) as $label): ?>
                                            <span class="ouinpo-assessment-tag">
                                                <?php echo esc_html($label); ?>
                                            </span>
                                        <?php endforeach; ?>
    
                                        <?php if (count($competencyLabels) > 4): ?>
                                            <span class="ouinpo-assessment-tag">
                                                +<?php echo count($competencyLabels) - 4; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($groupId > 0 && $seenCount > 0): ?>
                                    <div class="ouinpo-ds-seen-warning">
                                        Déjà tenté/réussi par <?php echo (int) $seenCount; ?> élève(s)
                                        <?php if ($solvedCount > 0): ?>
                                            — réussi par <?php echo (int) $solvedCount; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($groupId > 0): ?>
                                    <div class="ouinpo-ds-seen-ok">
                                        Non vu par la classe
                                    </div>
                                <?php endif; ?>                                
                                
                            </td>
    
                            <td>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.25"
                                    name="points[<?php echo $exerciseId; ?>]"
                                    value="<?php echo esc_attr($points !== '—' ? str_replace(',', '.', $points) : ''); ?>"
                                    class="ouinpo-assessment-input-points"
                                >
                            </td>
    
                            <td>
                                <?php if (!empty($item->estimated_minutes)): ?>
                                    <?php echo (int) $item->estimated_minutes; ?> min
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
    
                            <td>
                                <a
                                    class="button button-small"
                                    href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=replace_item&id=' . (int) $assessmentId . '&exercise_id=' . (int) $exerciseId)); ?>"
                                >
                                    Remplaçants
                                </a>
                            </td>
                            
                            <td>
                                <label class="ouinpo-assessment-delete-label">
                                    <input
                                        type="checkbox"
                                        name="delete_exercise_ids[]"
                                        value="<?php echo $exerciseId; ?>"
                                    >
                                    Retirer
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    
            <p class="ouinpo-assessment-sync-row">
                <label>
                    <input type="checkbox" name="sync_competencies" value="1" checked>
                    Resynchroniser les compétences BO du DS avec les exercices restants
                </label>
            </p>
    
            <p class="description">
                Si cette case est cochée, les compétences évaluées seront recalculées à partir des exercices encore présents dans le DS.
                Les résultats déjà saisis pour une compétence retirée du DS seront supprimés.
            </p>
    
            <?php submit_button('Enregistrer les exercices du DS'); ?>
        </form>
        <?php
    }

    private static function render_subject(int $assessmentId): void {
        $assessment = self::get_assessment($assessmentId);
    
        if (!$assessment) {
            echo '<div class="notice notice-error"><p>DS introuvable.</p></div>';
            return;
        }
    
        $items = self::get_assessment_items($assessmentId);
        $totals = self::get_assessment_totals($items);
    
        ?>
        <h1 class="wp-heading-inline">Sujet imprimable</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="page-title-action">Retour à la liste</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=grade&id=' . $assessmentId)); ?>" class="page-title-action">Saisie DS</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=items&id=' . $assessmentId)); ?>" class="page-title-action">Exercices du DS</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=correction&id=' . $assessmentId)); ?>" class="page-title-action">Corrigé professeur</a>
        <hr class="wp-header-end">
    
        <?php self::render_print_styles(); ?>
    
        <div class="ouinpo-print-toolbar">
            <button type="button" class="button button-primary" onclick="window.print()">Imprimer le sujet</button>
            <span class="description">Utilise aussi “Enregistrer au format PDF” dans la fenêtre d’impression du navigateur.</span>
        </div>
    
        <div class="ouinpo-print-header">
            <div class="ouinpo-print-eyebrow">Sujet élève</div>
            <h1><?php echo esc_html($assessment->title); ?></h1>
        
            <div class="ouinpo-print-summary">
                <div>
                    <span>Classe</span>
                    <strong><?php echo esc_html($assessment->group_label ?: '—'); ?></strong>
                </div>
        
                <div>
                    <span>Date</span>
                    <strong><?php echo esc_html($assessment->due_on ?: '—'); ?></strong>
                </div>
        
                <div>
                    <span>Durée indicative</span>
                    <strong><?php echo esc_html(self::format_minutes((int) $totals['total_minutes'])); ?></strong>
                </div>
        
                <div>
                    <span>Total</span>
                    <strong><?php echo esc_html(self::points_label_from_totals($totals)); ?></strong>
                </div>
        
                <div>
                    <span>Nom</span>
                    <strong class="ouinpo-print-blank">....................</strong>
                </div>
            </div>
        
            <?php if (!empty($assessment->notes)): ?>
                <div class="ouinpo-print-instructions">
                    <?php echo wp_kses_post(wpautop((string) $assessment->notes)); ?>
                </div>
            <?php endif; ?>
        </div>
    
            <?php if (empty($items)): ?>
                <div class="notice notice-warning">
                    <p>Ce DS ne contient aucun exercice dans <code>assessment_items</code>. Il a peut-être été créé avec l’ancien système uniquement par compétences.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $sourceLabel = self::source_label_for_print($item->source_type ?? '');
                    $points = self::format_points($item->points ?? null);
                    ?>
                    <section class="ouinpo-print-exercise">
                        <h2>
                            Exercice <?php echo (int) ($index + 1); ?>
                            — <?php echo esc_html($item->title); ?>
                            <?php if ($points !== '—'): ?>
                                <span style="font-weight:normal;">— <?php echo esc_html($points); ?> point<?php echo ((float) str_replace(',', '.', $points) > 1 ? 's' : ''); ?></span>
                            <?php endif; ?>
                        </h2>
    
                        <div class="ouinpo-print-exercise-meta">
                            <?php if (!empty($item->difficulty_label)): ?>
                                <span class="ouinpo-print-pill">Difficulté : <?php echo esc_html($item->difficulty_label); ?></span>
                            <?php endif; ?>
                        
                            <?php if (!empty($item->estimated_minutes)): ?>
                                <span class="ouinpo-print-pill">Durée : <?php echo (int) $item->estimated_minutes; ?> min</span>
                            <?php endif; ?>
                        
                            <?php if ($sourceLabel !== ''): ?>
                                <span class="ouinpo-print-pill"><?php echo esc_html($sourceLabel); ?></span>
                            <?php endif; ?>
                        
                            <?php if (!empty($item->center_label) || !empty($item->session_label) || !empty($item->year_label)): ?>
                                <span class="ouinpo-print-pill">
                                    <?php echo esc_html(trim(($item->center_label ?: '') . ' ' . ($item->session_label ?: '') . ' ' . ($item->year_label ?: ''))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
    
                        <div class="ouinpo-print-statement">
                            <?php echo wp_kses_post((string) $item->statement); ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function clean_hint_content_for_print(string $html, int $hintOrder): string {
        $html = trim($html);
    
        if ($html === '') {
            return '';
        }
    
        $n = preg_quote((string) $hintOrder, '/');
    
        /*
         * Cas rencontrés :
         * Indice 1 : texte
         * <p>Indice 1 : texte</p>
         * <p><strong>Indice 1</strong> : texte</p>
         * <strong>Indice 1</strong> : texte
         */
        $patterns = [
            // Avec paragraphe ouvrant éventuel, et strong/b éventuel.
            '/^(\s*<p\b[^>]*>\s*)(?:<(?:strong|b)[^>]*>\s*)?Indice\s*' . $n . '\s*(?:<\/(?:strong|b)>\s*)?[:：\-–—]\s*/iu',
    
            // Sans paragraphe.
            '/^\s*(?:<(?:strong|b)[^>]*>\s*)?Indice\s*' . $n . '\s*(?:<\/(?:strong|b)>\s*)?[:：\-–—]\s*/iu',
        ];
    
        $replacements = [
            '$1',
            '',
        ];
    
        $html = preg_replace($patterns, $replacements, $html, 1);
    
        return trim((string) $html);
    }
    
    private static function render_correction(int $assessmentId): void {
        $assessment = self::get_assessment($assessmentId);
    
        if (!$assessment) {
            echo '<div class="notice notice-error"><p>DS introuvable.</p></div>';
            return;
        }
    
        $items = self::get_assessment_items($assessmentId);
        $totals = self::get_assessment_totals($items);
        $competencyCount = count(self::get_assessment_competency_ids($assessmentId));
    
        ?>
        <h1 class="wp-heading-inline">Corrigé professeur</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="page-title-action">Retour à la liste</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=grade&id=' . $assessmentId)); ?>" class="page-title-action">Saisie DS</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=subject&id=' . $assessmentId)); ?>" class="page-title-action">Sujet imprimable</a>
        <hr class="wp-header-end">
    
        <?php self::render_print_styles(); ?>
    
        <div class="ouinpo-print-toolbar">
            <button type="button" class="button button-primary" onclick="window.print()">Imprimer le corrigé</button>
            <span class="description">Ce document est destiné au professeur : il inclut solutions, indices et compétences BO.</span>
        </div>
    
        <div class="ouinpo-print-page">
            <div class="ouinpo-print-header">
                <div class="ouinpo-print-eyebrow">Corrigé professeur</div>
                <h1>Corrigé — <?php echo esc_html($assessment->title); ?></h1>
            
                <div class="ouinpo-print-summary">
                    <div>
                        <span>Classe</span>
                        <strong><?php echo esc_html($assessment->group_label ?: '—'); ?></strong>
                    </div>
            
                    <div>
                        <span>Date</span>
                        <strong><?php echo esc_html($assessment->due_on ?: '—'); ?></strong>
                    </div>
            
                    <div>
                        <span>Durée indicative</span>
                        <strong><?php echo esc_html(self::format_minutes((int) $totals['total_minutes'])); ?></strong>
                    </div>
            
                    <div>
                        <span>Barème total</span>
                        <strong><?php echo esc_html(self::points_label_from_totals($totals)); ?></strong>
                    </div>
            
                    <div>
                        <span>Compétences</span>
                        <strong><?php echo (int) $competencyCount; ?></strong>
                    </div>
                </div>
            </div>
    
            <?php if (empty($items)): ?>
                <div class="notice notice-warning">
                    <p>Ce DS ne contient aucun exercice dans <code>assessment_items</code>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $points = self::format_points($item->points ?? null);
                    $competencyLabels = self::parse_competency_labels($item->competency_labels ?? '');
                    $hints = self::get_exercise_hints((int) $item->exercise_id);
                    $solutions = self::get_exercise_solutions((int) $item->exercise_id);
                    ?>
                    <section class="ouinpo-print-exercise">
                        <h2>
                            Exercice <?php echo (int) ($index + 1); ?>
                            — <?php echo esc_html($item->title); ?>
                            <?php if ($points !== '—'): ?>
                                <span style="font-weight:normal;">— <?php echo esc_html($points); ?> point<?php echo ((float) str_replace(',', '.', $points) > 1 ? 's' : ''); ?></span>
                            <?php endif; ?>
                        </h2>
    
                        <?php if (!empty($competencyLabels)): ?>
                            <div class="ouinpo-print-competencies">
                                <strong>Compétences BO évaluées</strong>
                                <ul>
                                    <?php foreach ($competencyLabels as $label): ?>
                                        <li><?php echo esc_html($label); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
    
                        <h3>Énoncé</h3>
                        <div class="ouinpo-print-statement">
                            <?php echo wp_kses_post((string) $item->statement); ?>
                        </div>
    
                        <?php if (!empty($hints)): ?>
                            <div class="ouinpo-print-hints">
                                <h3>Indices</h3>
                                <?php foreach ($hints as $hint): ?>
                                    <?php
                                    $hintOrder = (int) $hint->hint_order;
                                    $hintContent = self::clean_hint_content_for_print((string) $hint->content, $hintOrder);
                                    ?>
                                    <div style="margin:8px 0;">
                                        <strong>Indice <?php echo $hintOrder; ?></strong>
                                        <div><?php echo wp_kses_post($hintContent); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
    
                        <h3>Solution</h3>
    
                        <?php if (empty($solutions)): ?>
                            <p><em>Aucune solution enregistrée pour cet exercice.</em></p>
                        <?php else: ?>
                            <?php foreach ($solutions as $solution): ?>
                                <div class="ouinpo-print-solution">
                                    <h3>
                                        <?php echo esc_html($solution->title ?: 'Solution'); ?>
                                    </h3>
                                    <div>
                                        <?php echo wp_kses_post((string) $solution->content); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_overview(string $action, int $id): void {
        [$current, $selectedCompetencyIds, $groups] = self::resolve_current($action, $id);
        $competencies = self::get_available_competencies((int) $current->group_id);
        $selectedGroup = self::get_group((int) $current->group_id);
        $groupedCompetencies = self::group_competencies($competencies);
        $listGroupId = isset($_GET['list_group_id']) ? (int) $_GET['list_group_id'] : 0;
        $rows = self::get_list_rows($listGroupId);
        ?>
        <h1 class="wp-heading-inline">Devoirs surveillés</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=new')); ?>" class="page-title-action">Nouveau DS</a>
        <hr class="wp-header-end">

        <div style="display:flex; gap:24px; align-items:flex-start; margin-top:12px;">
            <div style="flex:1 1 56%; min-width:520px;">
                <h2 class="title">Liste des DS</h2>
                <form method="get" style="margin:0 0 12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
                    <label for="list-group-id"><strong>Filtrer par classe</strong></label>
                    <select name="list_group_id" id="list-group-id">
                        <option value="0">Toutes les classes</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo (int) $group->id; ?>" <?php selected($listGroupId, (int) $group->id); ?>>
                                <?php echo esc_html($group->label . (!empty($group->year_slug) ? ' — ' . $group->year_slug : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button">Filtrer</button>
                    <?php if ($listGroupId > 0): ?>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>">Réinitialiser</a>
                    <?php endif; ?>
                </form>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Titre</th>
                            <th>Classe</th>
                            <th>Année</th>
                            <th>Comp.</th>
                            <th>Saisies</th>
                            <th>Absents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8">Aucun DS pour le moment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->due_on ?: '—'); ?></td>
                                <td>
                                    <strong><?php echo esc_html($row->title); ?></strong>
                                    <?php if (!empty($row->notes)): ?>
                                        <div style="color:#666; font-size:12px; margin-top:4px;">
                                            <?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $row->notes), 14)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($row->group_label ?: '—'); ?></td>
                                <td><?php echo esc_html($row->year_slug ?: '—'); ?></td>
                                <td><?php echo (int) $row->competencies_count; ?></td>
                                <td><?php echo (int) $row->graded_students; ?></td>
                                <td><?php echo (int) ($row->absent_students ?? 0); ?></td>
                                <td>
                                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=grade&id=' . (int) $row->id)); ?>">Saisir</a>
                                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=items&id=' . (int) $row->id)); ?>">Exercices</a>
                                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=subject&id=' . (int) $row->id)); ?>">Sujet</a>
                                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=correction&id=' . (int) $row->id)); ?>">Corrigé</a>
                                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=edit&id=' . (int) $row->id)); ?>">Modifier</a>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Créer une version B prudente de ce DS ? Les exercices déjà vus par la classe seront remplacés si un remplaçant non vu existe.');">
                                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                        <input type="hidden" name="op" value="create_version_b">
                                        <input type="hidden" name="assessment_id" value="<?php echo (int) $row->id; ?>">
                                        <button type="submit" class="button button-small">Version B</button>
                                    </form>
                                        
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                        <input type="hidden" name="op" value="duplicate_assessment">
                                        <input type="hidden" name="assessment_id" value="<?php echo (int) $row->id; ?>">
                                        <button type="submit" class="button button-small">Dupliquer</button>
                                    </form>                                        
                                        
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce DS ?');">
                                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                        <input type="hidden" name="op" value="delete_assessment">
                                        <input type="hidden" name="assessment_id" value="<?php echo (int) $row->id; ?>">
                                        <button type="submit" class="button button-small button-link-delete">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="flex:1 1 44%; min-width:420px; position:sticky; top:32px;">
                <h2 class="title"><?php echo !empty($current->id) ? 'Modifier le DS' : 'Créer un DS'; ?></h2>
                <form method="post">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                    <input type="hidden" name="op" value="save_assessment">
                    <input type="hidden" name="assessment_id" value="<?php echo (int) $current->id; ?>">

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ds-title">Titre</label></th>
                            <td><input type="text" name="title" id="ds-title" class="regular-text" required value="<?php echo esc_attr((string) $current->title); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ds-date">Date</label></th>
                            <td><input type="date" name="due_on" id="ds-date" required value="<?php echo esc_attr((string) $current->due_on); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ds-group">Classe</label></th>
                            <td>
                                <?php
                                $reloadBase = admin_url('admin.php?page=' . self::PAGE_SLUG);
                                $reloadArgs = ['action' => !empty($current->id) ? 'edit' : 'new'];
                                if (!empty($current->id)) {
                                    $reloadArgs['id'] = (int) $current->id;
                                }
                                $reloadUrlBase = add_query_arg($reloadArgs, $reloadBase);
                                ?>
                                <select
                                    name="group_id"
                                    id="ds-group"
                                    required
                                    onchange="window.location.href='<?php echo esc_js($reloadUrlBase); ?>&group_id=' + encodeURIComponent(this.value);"
                                >
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo (int) $group->id; ?>" <?php selected((int) $current->group_id, (int) $group->id); ?>>
                                            <?php echo esc_html($group->label . (!empty($group->year_slug) ? ' — ' . $group->year_slug : '') . (!empty($group->level_label) ? ' — ' . $group->level_label : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Le choix de la classe sert aussi à filtrer les compétences pertinentes.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ds-notes">Notes</label></th>
                            <td><textarea name="notes" id="ds-notes" rows="4" class="large-text"><?php echo esc_textarea((string) $current->notes); ?></textarea></td>
                        </tr>
                    </table>

                    <?php if ($selectedGroup): ?>
                        <p style="margin:8px 0 12px; color:#555;">
                            Filtre actuel :
                            <strong><?php echo esc_html($selectedGroup->label); ?></strong>
                            <?php if (!empty($selectedGroup->level_label)): ?>
                                — niveau <strong><?php echo esc_html($selectedGroup->level_label); ?></strong>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <h3 style="margin-top:18px;">Compétences BO associées</h3>
                    <div style="max-height:420px; overflow:auto; border:1px solid #dcdcde; background:#fff; padding:12px;">
                        <?php if (empty($groupedCompetencies)): ?>
                            <p>Aucune compétence disponible.</p>
                        <?php else: ?>
                            <?php foreach ($groupedCompetencies as $bucket => $domains): ?>
                                <details open style="margin-bottom:10px;">
                                    <summary style="font-weight:600;"><?php echo esc_html($bucket); ?></summary>
                                    <?php foreach ($domains as $domain => $items): ?>
                                        <div style="margin:8px 0 12px 14px;">
                                            <div style="font-weight:600; margin-bottom:6px;"><?php echo esc_html($domain); ?></div>
                                            <?php foreach ($items as $comp): ?>
                                                <label style="display:block; margin:4px 0; line-height:1.35;">
                                                    <input type="checkbox" name="competency_ids[]" value="<?php echo (int) $comp->id; ?>" <?php checked(in_array((int) $comp->id, $selectedCompetencyIds, true)); ?>>
                                                    <?php echo esc_html($comp->competency); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </details>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php submit_button(!empty($current->id) ? 'Enregistrer le DS' : 'Créer le DS'); ?>
                    <?php if (!empty($current->id)): ?>
                        <p><a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=grade&id=' . (int) $current->id)); ?>">Aller à la saisie des résultats</a></p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php
    }

    private static function render_grade(int $assessmentId): void {
        $assessment = self::get_assessment($assessmentId);
        $attendanceMap = self::get_attendance_map($assessmentId);

        if (!$assessment) {
            echo '<div class="notice notice-error"><p>DS introuvable.</p></div>';
            return;
        }

        $students = self::get_students_for_group((int) $assessment->group_id);
        $competencies = self::get_assessment_competencies($assessmentId);
        $resultMap = self::get_result_map($assessmentId);
        $currentStatusMap = self::get_current_status_map(
            array_map(fn($s) => (int) $s->id, $students),
            array_map(fn($c) => (int) $c->id, $competencies),
            (int) ($assessment->year_id ?? 0)
        );
        ?>
        <h1 class="wp-heading-inline">Saisie DS</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="page-title-action">Retour à la liste</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=items&id=' . $assessmentId)); ?>" class="page-title-action">Exercices du DS</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=subject&id=' . $assessmentId)); ?>" class="page-title-action">Sujet imprimable</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=correction&id=' . $assessmentId)); ?>" class="page-title-action">Corrigé professeur</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&action=edit&id=' . $assessmentId)); ?>" class="page-title-action">Modifier le DS</a>

        <form method="post" style="display:inline;" onsubmit="return confirm('Créer une version B prudente de ce DS ? Les exercices déjà vus par la classe seront remplacés si un remplaçant non vu existe.');">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="op" value="create_version_b">
            <input type="hidden" name="assessment_id" value="<?php echo (int) $assessmentId; ?>">
            <button type="submit" class="page-title-action" style="cursor:pointer;">Créer une version B</button>
        </form>
        
        <form method="post" style="display:inline;">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="op" value="duplicate_assessment">
            <input type="hidden" name="assessment_id" value="<?php echo (int) $assessmentId; ?>">
            <button type="submit" class="page-title-action" style="cursor:pointer;">Dupliquer le DS</button>
        </form>        
        
        <hr class="wp-header-end">

        <div class="card" style="max-width:none; padding:16px; margin-top:12px;">
            <p style="margin:0 0 6px;"><strong><?php echo esc_html($assessment->title); ?></strong></p>
            <p style="margin:0;">
                Date : <strong><?php echo esc_html($assessment->due_on ?: '—'); ?></strong>
                &nbsp;|&nbsp; Classe : <strong><?php echo esc_html($assessment->group_label ?: '—'); ?></strong>
                &nbsp;|&nbsp; Année : <strong><?php echo esc_html($assessment->year_slug ?: '—'); ?></strong>
            </p>
            <?php if (!empty($assessment->notes)): ?>
                <p style="margin:10px 0 0;"><?php echo wp_kses_post(wpautop($assessment->notes)); ?></p>
            <?php endif; ?>
        </div>

        <?php if (empty($competencies)): ?>
            <div class="notice notice-warning"><p>Ce DS n’a encore aucune compétence associée.</p></div>
            <?php return; ?>
        <?php endif; ?>

        <?php if (empty($students)): ?>
            <div class="notice notice-warning"><p>Aucun élève n’est affecté à cette classe.</p></div>
            <?php return; ?>
        <?php endif; ?>

        <form method="post" style="margin-top:16px;">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="op" value="save_results">
            <input type="hidden" name="assessment_id" value="<?php echo (int) $assessmentId; ?>">

            <p>
                <label>
                    <input type="checkbox" name="apply_progression" value="1" checked>
                    Mettre à jour le niveau actuel de l’élève pour cette compétence, mais uniquement si l’observation du DS fait progresser son niveau.
                </label>
            </p>

            <?php foreach ($students as $student): ?>
                <?php
                $uid = (int) $student->id;
                $isAbsent = !empty($attendanceMap[$uid]['is_absent']);
                ?>
                <div class="card" style="max-width:none; margin:14px 0; padding:14px;">
                    <div style="display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap;">
                        <div>
                            <strong><?php echo esc_html($student->display_name); ?></strong>
                            <div style="color:#666; font-size:12px; margin-top:2px;">ID <?php echo $uid; ?></div>
                        </div>
                        <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
                            <input
                                type="checkbox"
                                name="attendance[<?php echo $uid; ?>][is_absent]"
                                value="1"
                                <?php checked($isAbsent); ?>
                                class="js-absent-toggle"
                                data-student="student-<?php echo $uid; ?>"
                            >
                            Absent
                        </label>
                    </div>

                    <div id="student-<?php echo $uid; ?>" style="margin-top:12px; <?php echo $isAbsent ? 'opacity:.55;' : ''; ?>">
                        <?php if ($isAbsent): ?>
                            <p style="margin:0 0 10px; color:#8a2424; font-weight:600;">Élève marqué absent pour ce devoir.</p>
                        <?php endif; ?>

                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Compétence</th>
                                    <th>Niveau actuel</th>
                                    <th>Observation sur ce DS</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($competencies as $comp): ?>
                                <?php
                                $cid = (int) $comp->id;
                                $currentStatus = $currentStatusMap[$uid][$cid] ?? '';
                                $observedStatus = $resultMap[$uid][$cid] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($comp->domain); ?></strong><br>
                                        <?php echo esc_html($comp->competency); ?>
                                    </td>
                                    <td><?php echo self::status_badge((string) $currentStatus); ?></td>
                                    <td>
                                        <select
                                            name="results[<?php echo $uid; ?>][<?php echo $cid; ?>]"
                                            <?php disabled($isAbsent); ?>
                                        >
                                            <?php foreach (self::status_options() as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected((string) $observedStatus, (string) $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <script>
            document.addEventListener('change', function (e) {
                if (!e.target.classList.contains('js-absent-toggle')) return;

                const box = e.target;
                const target = document.getElementById(box.dataset.student || '');
                if (!target) return;

                const disabled = box.checked;
                target.style.opacity = disabled ? '.55' : '1';

                target.querySelectorAll('select').forEach(function (el) {
                    el.disabled = disabled;
                });
            });
            </script>

            <?php submit_button('Enregistrer les résultats'); ?>
        </form>
        <?php
    }
}
