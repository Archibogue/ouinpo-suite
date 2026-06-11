<?php
namespace Ouinpo\Suite\Core;

final class Installer
{
    public static function maybeUpgrade(): void
    {
        $installed = (string) get_option('ouinpo_suite_version', '0.2.0');
        self::ensureProjectsStudentAiSchema();

        if (version_compare($installed, OUINPO_SUITE_VERSION, '>=')) {
            return;
        }

        self::installOrUpgradeSharedSchema();
        AiSettings::migrate_public_access_for_existing_site($installed);
        self::ensureProjectsStudentAiSchema();
        Capabilities::install();
        update_option('ouinpo_suite_version', OUINPO_SUITE_VERSION, false);
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
