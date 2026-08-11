<?php
namespace Ouinpo\Suite\Core;

final class Installer
{
    public static function maybeUpgrade(): void
    {
        $installed = (string) get_option('ouinpo_suite_version', '0.2.0');
        $current = (string) OUINPO_SUITE_VERSION;

        if (version_compare($installed, $current, '>=')) {
            return;
        }

        self::installOrUpgradeSharedSchema();
        AiSettings::migrate_public_access_for_existing_site($installed);
        self::ensureProjectsStudentAiSchema();
        self::ensureYearClosureSchema();
        self::ensureTrainingSchema();
        Capabilities::install();
        update_option('ouinpo_suite_version', $current, false);
    }

    public static function installOrUpgradeSharedSchema(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        Compat::ensureUploadsLayout();

        $charset = $wpdb->get_charset_collate();
        $schema_suffix = "ENGINE=InnoDB {$charset}";
        // Gate
        $tProg = $wpdb->prefix . 'ouinpo_progress';
        $tLogs = $wpdb->prefix . 'ouinpo_logs';
        $tSign = $wpdb->prefix . 'ouinpo_signatures';

        dbDelta("CREATE TABLE {$tProg} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            page_slug VARCHAR(200) NOT NULL,
            solved_json LONGTEXT NOT NULL,
            progress INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_page (user_id, page_slug)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tLogs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            page_slug VARCHAR(200) NOT NULL,
            riddle_index INT NOT NULL,
            ok TINYINT(1) NOT NULL DEFAULT 1,
            answer_norm TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_page (user_id, page_slug)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tSign} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            page_slug VARCHAR(200) NOT NULL,
            nom VARCHAR(200) NOT NULL,
            pseudo VARCHAR(200) NULL,
            message TEXT NULL,
            ip VARCHAR(45) NULL,
            ua TEXT NULL,
            date_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$schema_suffix};");

        // SegFault / parcours prof
        $tPaths   = $wpdb->prefix . 'ouin_sf_paths';
        $tItems   = $wpdb->prefix . 'ouin_sf_path_items';
        $tTargets = $wpdb->prefix . 'ouin_sf_path_targets';
        $tSug     = $wpdb->prefix . 'ouin_sf_suggestions';

        dbDelta("CREATE TABLE {$tPaths} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            teacher_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            student_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(190) NOT NULL DEFAULT '',
            mode VARCHAR(20) NOT NULL DEFAULT 'free',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_template TINYINT(1) NOT NULL DEFAULT 0,
            template_source_id BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            student_note TEXT NULL,
            year_id BIGINT UNSIGNED NULL DEFAULT NULL,
            level_slug VARCHAR(30) NULL DEFAULT NULL,
            domain_slug VARCHAR(120) NULL DEFAULT NULL,
            goal_slug VARCHAR(40) NULL DEFAULT NULL,
            path_scope VARCHAR(30) NOT NULL DEFAULT 'teacher_assigned',
            PRIMARY KEY  (id),
            KEY student_id (student_id),
            KEY teacher_id (teacher_id),
            KEY is_active (is_active),
            KEY mode (mode),
            KEY idx_is_template (is_template),
            KEY idx_template_source_id (template_source_id),
            KEY idx_year_id (year_id),
            KEY idx_level_slug (level_slug),
            KEY idx_domain_slug (domain_slug),
            KEY idx_goal_slug (goal_slug),
            KEY idx_path_scope (path_scope),
            KEY idx_template_active_level_domain_goal (is_template, is_active, level_slug, domain_slug, goal_slug),
            KEY idx_active_updated (is_active, updated_at, id)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tItems} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            path_id BIGINT UNSIGNED NOT NULL,
            position INT NOT NULL DEFAULT 0,
            exercise_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            note TEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_sf_path_items_path_position (path_id, position),
            KEY path_id (path_id),
            KEY exercise_id (exercise_id)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tTargets} (
            path_id BIGINT UNSIGNED NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id BIGINT UNSIGNED NOT NULL,
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assigned_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (path_id, target_type, target_id),
            KEY idx_target (target_type, target_id),
            KEY idx_path (path_id)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tSug} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            exercise_id BIGINT UNSIGNED NOT NULL,
            first_suggested_at DATETIME NOT NULL,
            last_suggested_at DATETIME NOT NULL,
            shown_count INT NOT NULL DEFAULT 1,
            last_session VARCHAR(128) NOT NULL DEFAULT '',
            last_page_url TEXT NULL,
            last_query TEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_exo (user_id, exercise_id),
            KEY user_id (user_id),
            KEY last_suggested_at (last_suggested_at),
            KEY exercise_id (exercise_id)
        ) {$schema_suffix};");

        $tPathBadges = $wpdb->prefix . 'ouinpo_path_badges';
        dbDelta("CREATE TABLE {$tPathBadges} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            path_id BIGINT(20) UNSIGNED NOT NULL,
            badge_id BIGINT(20) UNSIGNED NOT NULL,
            rule_type VARCHAR(40) NOT NULL DEFAULT 'all_mandatory',
            min_percent DECIMAL(5,2) NOT NULL DEFAULT 100.00,
            require_all_mandatory TINYINT(1) NOT NULL DEFAULT 1,
            require_final_exercise TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY path_badge (path_id, badge_id),
            KEY path_id (path_id),
            KEY badge_id (badge_id),
            KEY rule_type (rule_type)
        ) {$schema_suffix};");

        // Projects / suivi pedagogique BTS SIO.
        $tProjects   = $wpdb->prefix . 'ouinpo_projects';
        $tMembers    = $wpdb->prefix . 'ouinpo_project_members';
        $tColumns    = $wpdb->prefix . 'ouinpo_project_columns';
        $tTasks      = $wpdb->prefix . 'ouinpo_project_tasks';
        $tComments   = $wpdb->prefix . 'ouinpo_project_task_comments';
        $tChecklist  = $wpdb->prefix . 'ouinpo_project_checklist_items';
        $tProjectLog = $wpdb->prefix . 'ouinpo_project_logs';
        $tDeliverables = $wpdb->prefix . 'ouinpo_project_deliverables';
        $tEvidence = $wpdb->prefix . 'ouinpo_project_evidence';
        $tCompetencyLinks = $wpdb->prefix . 'ouinpo_project_competency_links';

        dbDelta("CREATE TABLE {$tProjects} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            description LONGTEXT NULL,
            level VARCHAR(100) NULL,
            class_slug VARCHAR(100) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            student_ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
            teacher_id BIGINT UNSIGNED NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY teacher_id (teacher_id),
            KEY class_slug (class_slug),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tMembers} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'member',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY project_user (project_id, user_id),
            KEY project_id (project_id),
            KEY user_id (user_id),
            KEY role (role),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tColumns} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            position INT NOT NULL DEFAULT 0,
            status_key VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY project_position (project_id, position),
            KEY status_key (status_key),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tTasks} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            column_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            description LONGTEXT NULL,
            assigned_user_id BIGINT UNSIGNED NULL,
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            due_date DATE NULL,
            position INT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY column_id (column_id),
            KEY column_position (column_id, position),
            KEY assigned_user_id (assigned_user_id),
            KEY created_by (created_by),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tComments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            comment LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tChecklist} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(190) NOT NULL,
            is_done TINYINT(1) NOT NULL DEFAULT 0,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY task_id (task_id),
            KEY task_position (task_id, position),
            KEY is_done (is_done),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tProjectLog} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            work_done LONGTEXT NOT NULL,
            blockers LONGTEXT NULL,
            decision_taken LONGTEXT NULL,
            next_step LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tDeliverables} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            description LONGTEXT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'other',
            status VARCHAR(50) NOT NULL DEFAULT 'expected',
            due_date DATE NULL,
            validated_by BIGINT UNSIGNED NULL,
            validated_at DATETIME NULL,
            position INT NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY project_position (project_id, position),
            KEY status (status),
            KEY type (type),
            KEY due_date (due_date),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tEvidence} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            deliverable_id BIGINT UNSIGNED NULL,
            task_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            description LONGTEXT NULL,
            evidence_type VARCHAR(50) NOT NULL DEFAULT 'link',
            url TEXT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY deliverable_id (deliverable_id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$schema_suffix};");

        dbDelta("CREATE TABLE {$tCompetencyLinks} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(30) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            competency_id BIGINT UNSIGNED NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY object_competency (object_type, object_id, competency_id),
            KEY project_id (project_id),
            KEY object_lookup (object_type, object_id),
            KEY competency_id (competency_id),
            KEY created_at (created_at)
        ) {$schema_suffix};");
        
    }

    public static function ensureSegFaultConstraints(): void
{
    global $wpdb;

    $pExo = $wpdb->prefix . 'ouin_exo_';
    $pSf  = $wpdb->prefix . 'ouin_sf_';

    self::addForeignKeyIfMissing(
        $pSf . 'path_items',
        'fk_sf_path_items_exercise',
        "ALTER TABLE {$pSf}path_items
        ADD CONSTRAINT fk_sf_path_items_exercise
        FOREIGN KEY (exercise_id)
        REFERENCES {$pExo}exercises (id)"
    );

    self::addForeignKeyIfMissing(
        $pSf . 'path_items',
        'fk_sf_path_items_path',
        "ALTER TABLE {$pSf}path_items
        ADD CONSTRAINT fk_sf_path_items_path
        FOREIGN KEY (path_id)
        REFERENCES {$pSf}paths (id)
        ON DELETE CASCADE"
    );

    self::addForeignKeyIfMissing(
        $pSf . 'path_targets',
        'fk_sf_path_targets_path',
        "ALTER TABLE {$pSf}path_targets
        ADD CONSTRAINT fk_sf_path_targets_path
        FOREIGN KEY (path_id)
        REFERENCES {$pSf}paths (id)
        ON DELETE CASCADE"
    );

    self::addForeignKeyIfMissing(
        $pSf . 'paths',
        'fk_sf_paths_template_source',
        "ALTER TABLE {$pSf}paths
        ADD CONSTRAINT fk_sf_paths_template_source
        FOREIGN KEY (template_source_id)
        REFERENCES {$pSf}paths (id)
        ON DELETE SET NULL"
    );

    self::addForeignKeyIfMissing(
        $pSf . 'suggestions',
        'fk_sf_suggestions_exercise',
        "ALTER TABLE {$pSf}suggestions
        ADD CONSTRAINT fk_sf_suggestions_exercise
        FOREIGN KEY (exercise_id)
        REFERENCES {$pExo}exercises (id)
        ON DELETE CASCADE"
    );
}

private static function addForeignKeyIfMissing(string $table, string $name, string $sql): void
{
    global $wpdb;

    $tableExists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $table)
    );

    if ($tableExists !== $table) {
        return;
    }

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
        FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE()
            AND table_name = %s
            AND constraint_name = %s
            AND constraint_type = 'FOREIGN KEY'",
        $table,
        $name
    ));

    if ((int) $exists > 0) {
        return;
    }

    $wpdb->query($sql);

    if (!empty($wpdb->last_error)) {
        $message = '[ouinpo suite] SegFault FK failed: ' . $name . ' | ' . $wpdb->last_error;
        error_log($message);

        $failures = get_option('ouinpo_suite_fk_failures', []);
        if (!is_array($failures)) {
            $failures = [];
        }

        $failures[$name] = [
            'table' => $table,
            'error' => sanitize_text_field($wpdb->last_error),
            'date' => current_time('mysql'),
        ];

        update_option('ouinpo_suite_fk_failures', $failures, false);
    }
}

public static function ensureProjectsStudentAiSchema(): void
{
    global $wpdb;

    $projects = $wpdb->prefix . 'ouinpo_projects';
    $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects));
    if ((string) $tableExists === $projects) {
        $columnExists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$projects} LIKE %s", 'student_ai_enabled'));
        if (!$columnExists) {
            $wpdb->query("ALTER TABLE {$projects} ADD student_ai_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }
    }

        self::ensureProjectsStudentAiDefaults();
        self::ensureYearClosureSchema();
    }

public static function ensureYearClosureSchema(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $schema_suffix = "ENGINE=InnoDB {$charset}";

    $cycles = $wpdb->prefix . 'ouinpo_cycles';
    $transitions = $wpdb->prefix . 'ouinpo_level_transitions';
    $cohorts = $wpdb->prefix . 'ouinpo_cycle_cohorts';
    $members = $wpdb->prefix . 'ouinpo_cycle_members';
    $policies = $wpdb->prefix . 'ouinpo_cycle_data_policies';
    $runs = $wpdb->prefix . 'ouinpo_year_closure_runs';
    $items = $wpdb->prefix . 'ouinpo_year_closure_items';

    dbDelta("CREATE TABLE {$cycles} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(120) NOT NULL,
        label VARCHAR(190) NOT NULL,
        description TEXT NULL,
        duration_years TINYINT UNSIGNED NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        portfolio_enabled TINYINT(1) NOT NULL DEFAULT 0,
        default_policy_json LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY status (status)
    ) {$schema_suffix};");

    dbDelta("CREATE TABLE {$transitions} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        from_level_id TINYINT UNSIGNED NOT NULL,
        to_level_id TINYINT UNSIGNED NULL,
        transition_type VARCHAR(40) NOT NULL DEFAULT 'promotion',
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        preserve_cycle_data TINYINT(1) NULL,
        label VARCHAR(190) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY  (id),
        KEY from_level_id (from_level_id),
        KEY to_level_id (to_level_id),
        KEY is_default (is_default)
    ) {$schema_suffix};");

    dbDelta("CREATE TABLE {$cohorts} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cycle_id BIGINT UNSIGNED NOT NULL,
        slug VARCHAR(120) NOT NULL,
        label VARCHAR(190) NOT NULL,
        starts_year_id SMALLINT UNSIGNED NOT NULL,
        ends_year_id SMALLINT UNSIGNED NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY cycle_id (cycle_id),
        KEY starts_year_id (starts_year_id),
        KEY status (status)
    ) {$schema_suffix};");

    dbDelta("CREATE TABLE {$members} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cohort_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        joined_year_id SMALLINT UNSIGNED NULL,
        left_year_id SMALLINT UNSIGNED NULL,
        joined_at DATETIME NOT NULL,
        left_at DATETIME NULL,
        exit_reason VARCHAR(80) NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY cohort_user (cohort_id, user_id),
        KEY user_id (user_id),
        KEY status (status)
    ) {$schema_suffix};");

    dbDelta("CREATE TABLE {$policies} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cycle_id BIGINT UNSIGNED NOT NULL,
        data_domain VARCHAR(80) NOT NULL,
        scope VARCHAR(30) NOT NULL DEFAULT 'year',
        action_same_cycle VARCHAR(40) NOT NULL DEFAULT 'reset',
        action_cycle_exit VARCHAR(40) NOT NULL DEFAULT 'purge',
        alumni_access VARCHAR(40) NOT NULL DEFAULT 'none',
        retention_months_after_exit SMALLINT UNSIGNED NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY cycle_domain (cycle_id, data_domain),
        KEY data_domain (data_domain)
    ) {$schema_suffix};");

    dbDelta("CREATE TABLE {$runs} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        from_year_id SMALLINT UNSIGNED NOT NULL,
        to_year_id SMALLINT UNSIGNED NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'draft',
        mode VARCHAR(20) NOT NULL DEFAULT 'dry_run',
        options_json LONGTEXT NULL,
        summary_json LONGTEXT NULL,
        started_by BIGINT UNSIGNED NOT NULL,
        started_at DATETIME NOT NULL,
        finished_at DATETIME NULL,
        error_message TEXT NULL,
        PRIMARY KEY  (id),
        KEY from_year_id (from_year_id),
        KEY to_year_id (to_year_id),
        KEY status (status),
        KEY mode (mode)
    ) {$schema_suffix};");

    dbDelta("CREATE TABLE {$items} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        run_id BIGINT UNSIGNED NOT NULL,
        step VARCHAR(80) NOT NULL,
        object_type VARCHAR(80) NOT NULL,
        object_id BIGINT UNSIGNED NULL,
        action VARCHAR(80) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'planned',
        message TEXT NULL,
        count_before BIGINT UNSIGNED NULL,
        count_after BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY run_id (run_id),
        KEY step (step),
        KEY status (status)
    ) {$schema_suffix};");

    $p = $wpdb->prefix . 'ouin_exo_';
    self::addColumnIfMissing($p . 'school_levels', 'cycle_id', 'cycle_id BIGINT UNSIGNED NULL AFTER label');
    self::addColumnIfMissing($p . 'school_levels', 'cycle_rank', 'cycle_rank TINYINT UNSIGNED NULL AFTER cycle_id');
    self::addColumnIfMissing($p . 'school_levels', 'is_cycle_terminal', "is_cycle_terminal TINYINT(1) NOT NULL DEFAULT 0 AFTER cycle_rank");
    self::addColumnIfMissing($p . 'school_levels', 'default_next_level_id', 'default_next_level_id TINYINT UNSIGNED NULL AFTER is_cycle_terminal');

    self::addColumnIfMissing($p . 'academic_years', 'status', "status VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER is_active");
    self::addColumnIfMissing($p . 'academic_years', 'closed_at', 'closed_at DATETIME NULL AFTER status');
    self::addColumnIfMissing($p . 'academic_years', 'closed_by', 'closed_by BIGINT UNSIGNED NULL AFTER closed_at');
    self::addColumnIfMissing($p . 'academic_years', 'archived_at', 'archived_at DATETIME NULL AFTER closed_by');
    self::addColumnIfMissing($p . 'academic_years', 'archive_policy_json', 'archive_policy_json LONGTEXT NULL AFTER archived_at');

    self::addColumnIfMissing($p . 'groups', 'status', "status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER created_at");
    self::addColumnIfMissing($p . 'groups', 'source_group_id', 'source_group_id BIGINT UNSIGNED NULL AFTER status');
    self::addColumnIfMissing($p . 'groups', 'closed_at', 'closed_at DATETIME NULL AFTER source_group_id');
    self::addColumnIfMissing($p . 'groups', 'closed_by', 'closed_by BIGINT UNSIGNED NULL AFTER closed_at');

    $projects = $wpdb->prefix . 'ouinpo_projects';
    self::addColumnIfMissing($projects, 'origin_year_id', 'origin_year_id SMALLINT UNSIGNED NULL AFTER class_slug');
    self::addColumnIfMissing($projects, 'current_year_id', 'current_year_id SMALLINT UNSIGNED NULL AFTER origin_year_id');
    self::addColumnIfMissing($projects, 'origin_group_id', 'origin_group_id BIGINT UNSIGNED NULL AFTER current_year_id');
    self::addColumnIfMissing($projects, 'current_group_id', 'current_group_id BIGINT UNSIGNED NULL AFTER origin_group_id');
    self::addColumnIfMissing($projects, 'cycle_id', 'cycle_id BIGINT UNSIGNED NULL AFTER current_group_id');
    self::addColumnIfMissing($projects, 'lifecycle_status', "lifecycle_status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER cycle_id");
    self::addColumnIfMissing($projects, 'closure_policy', "closure_policy VARCHAR(50) NOT NULL DEFAULT 'auto' AFTER lifecycle_status");
    self::addColumnIfMissing($projects, 'is_portfolio_relevant', "is_portfolio_relevant TINYINT(1) NOT NULL DEFAULT 0 AFTER closure_policy");
    self::addColumnIfMissing($projects, 'preserve_until', 'preserve_until DATE NULL AFTER is_portfolio_relevant');
    self::addColumnIfMissing($projects, 'archived_at', 'archived_at DATETIME NULL AFTER preserve_until');
    self::addColumnIfMissing($projects, 'archived_by', 'archived_by BIGINT UNSIGNED NULL AFTER archived_at');

    $projectMembers = $wpdb->prefix . 'ouinpo_project_members';
    self::addColumnIfMissing($projectMembers, 'access_level', "access_level VARCHAR(30) NOT NULL DEFAULT 'member' AFTER role");
    self::addColumnIfMissing($projectMembers, 'can_edit', "can_edit TINYINT(1) NOT NULL DEFAULT 1 AFTER access_level");
    self::addColumnIfMissing($projectMembers, 'can_comment', "can_comment TINYINT(1) NOT NULL DEFAULT 1 AFTER can_edit");
    self::addColumnIfMissing($projectMembers, 'can_export', "can_export TINYINT(1) NOT NULL DEFAULT 1 AFTER can_comment");
    self::addColumnIfMissing($projectMembers, 'active_until', 'active_until DATE NULL AFTER can_export');
    self::addColumnIfMissing($projectMembers, 'archived_at', 'archived_at DATETIME NULL AFTER active_until');

    $deliverables = $wpdb->prefix . 'ouinpo_project_deliverables';
    self::addColumnIfMissing($deliverables, 'is_portfolio_evidence', "is_portfolio_evidence TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    self::addColumnIfMissing($deliverables, 'preserve_until', 'preserve_until DATE NULL AFTER is_portfolio_evidence');

    $evidence = $wpdb->prefix . 'ouinpo_project_evidence';
    self::addColumnIfMissing($evidence, 'is_portfolio_evidence', "is_portfolio_evidence TINYINT(1) NOT NULL DEFAULT 0 AFTER evidence_type");
    self::addColumnIfMissing($evidence, 'preserve_until', 'preserve_until DATE NULL AFTER is_portfolio_evidence');

    self::backfillProjectClosureColumnsFromClassSlug();
}

public static function ensureTrainingSchema(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $schema_suffix = "ENGINE=InnoDB {$charset}";
    $paths = $wpdb->prefix . 'ouin_sf_paths';
    $pathBadges = $wpdb->prefix . 'ouinpo_path_badges';
    $userBadges = $wpdb->prefix . 'ouin_exo_user_badges';

    self::addColumnIfMissing($paths, 'path_scope', "path_scope VARCHAR(30) NOT NULL DEFAULT 'teacher_assigned' AFTER goal_slug");

    dbDelta("CREATE TABLE {$pathBadges} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        path_id BIGINT(20) UNSIGNED NOT NULL,
        badge_id BIGINT(20) UNSIGNED NOT NULL,
        rule_type VARCHAR(40) NOT NULL DEFAULT 'all_mandatory',
        min_percent DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        require_all_mandatory TINYINT(1) NOT NULL DEFAULT 1,
        require_final_exercise TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY path_badge (path_id, badge_id),
        KEY path_id (path_id),
        KEY badge_id (badge_id),
        KEY rule_type (rule_type)
    ) {$schema_suffix};");

    if (self::columnExists($userBadges, 'source')) {
        $columnType = (string) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$userBadges} LIKE %s", 'source'));
        $row = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$userBadges} LIKE %s", 'source'), ARRAY_A);
        $type = strtolower((string) ($row['Type'] ?? $columnType));
        if (str_contains($type, 'enum') && !str_contains($type, "'path'")) {
            $wpdb->query("ALTER TABLE {$userBadges} MODIFY source ENUM('auto','manual','path') NOT NULL DEFAULT 'auto'");
        }
    }
}

private static function backfillProjectClosureColumnsFromClassSlug(): void
{
    global $wpdb;

    $projects = $wpdb->prefix . 'ouinpo_projects';
    $groups = $wpdb->prefix . 'ouin_exo_groups';
    if (!self::tableExists($projects) || !self::tableExists($groups) || !self::columnExists($projects, 'class_slug')) {
        return;
    }

    $required = ['origin_group_id', 'current_group_id', 'origin_year_id', 'current_year_id'];
    foreach ($required as $column) {
        if (!self::columnExists($projects, $column)) {
            return;
        }
    }

    $rows = $wpdb->get_results("SELECT id, label, year_id FROM {$groups} WHERE label <> ''", ARRAY_A) ?: [];
    foreach ($rows as $group) {
        $groupId = (int) ($group['id'] ?? 0);
        if ($groupId <= 0) {
            continue;
        }

        $candidates = array_values(array_unique(array_filter([
            (string) $groupId,
            sanitize_key((string) ($group['label'] ?? '')),
            sanitize_title((string) ($group['label'] ?? '')),
        ], static fn($value) => (string) $value !== '')));
        if (!$candidates) {
            continue;
        }

        $placeholders = implode(', ', array_fill(0, count($candidates), '%s'));
        $yearId = !empty($group['year_id']) ? (int) $group['year_id'] : 0;
        $sets = [
            'origin_group_id = COALESCE(origin_group_id, %d)',
            'current_group_id = COALESCE(current_group_id, %d)',
        ];
        $args = [$groupId, $groupId];
        if ($yearId > 0) {
            $sets[] = 'origin_year_id = COALESCE(origin_year_id, %d)';
            $sets[] = 'current_year_id = COALESCE(current_year_id, %d)';
            $args[] = $yearId;
            $args[] = $yearId;
        }

        $args = array_merge($args, $candidates);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$projects}
             SET " . implode(', ', $sets) . "
             WHERE current_group_id IS NULL
               AND class_slug IN ({$placeholders})",
            $args
        ));
    }
}

private static function tableExists(string $table): bool
{
    global $wpdb;

    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

private static function columnExists(string $table, string $column): bool
{
    global $wpdb;

    return self::tableExists($table) && (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
}

private static function addColumnIfMissing(string $table, string $column, string $definition): void
{
    global $wpdb;

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }

    $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    if ($exists) {
        return;
    }

    $wpdb->query("ALTER TABLE {$table} ADD {$definition}");
    if (!empty($wpdb->last_error)) {
        error_log('[ouinpo suite] add column failed: ' . $table . '.' . $column . ' | ' . $wpdb->last_error);
    }
}

private static function ensureProjectsStudentAiDefaults(): void
{
    $defaults = AiSettings::defaults();
    foreach ([
        'ouinpo_projects_student_ai_enabled',
        'ouinpo_ai_projects_student_per_minute',
        'ouinpo_ai_projects_student_per_day',
        'ouinpo_ai_projects_student_max_tokens',
    ] as $option) {
        if (get_option($option, null) === null && array_key_exists($option, $defaults)) {
            update_option($option, $defaults[$option], false);
        }
    }
}
}
