<?php
namespace Ouinpo\Suite\Core;

final class Installer
{
    public static function maybeUpgrade(): void
    {
        $installed = (string) get_option('ouinpo_suite_version', '0.2.0');
        if (version_compare($installed, OUINPO_SUITE_VERSION, '>=')) {
            return;
        }

        self::installOrUpgradeSharedSchema();
        AiSettings::migrate_public_access_for_existing_site($installed);
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
            PRIMARY KEY  (id),
            UNIQUE KEY user_page (user_id, page_slug)
        ) {$schema_suffix};");

        self::ensureGateSignatureUniqueIndex($tSign);

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
        error_log('[ouinpo suite] SegFault FK failed: ' . $name . ' | ' . $wpdb->last_error);
    }
}

private static function ensureGateSignatureUniqueIndex(string $table): void
{
    global $wpdb;

    $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($tableExists !== $table) {
        return;
    }

    if (self::gateSignatureIndexIsValid($table)) {
        return;
    }

    /*
     * Migration defensive : si une ancienne installation contient plusieurs
     * signatures pour un meme utilisateur et une meme page, on conserve la
     * plus ancienne (id minimal) et on retire les doublons avant d'ajouter
     * l'unicite SQL.
     */
    $wpdb->query(
        "DELETE s2 FROM {$table} s1
         INNER JOIN {$table} s2
            ON s1.user_id = s2.user_id
           AND s1.page_slug = s2.page_slug
           AND s1.id < s2.id"
    );

    if (!empty($wpdb->last_error)) {
        error_log('[ouinpo suite] Gate signatures deduplication failed: ' . $wpdb->last_error);
        return;
    }

    $existingUserPage = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'user_page'");
    if (!empty($existingUserPage)) {
        $wpdb->query("ALTER TABLE {$table} DROP INDEX user_page");
        if (!empty($wpdb->last_error)) {
            error_log('[ouinpo suite] Gate signatures user_page index drop failed: ' . $wpdb->last_error);
            return;
        }
    }

    $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY user_page (user_id, page_slug)");

    if (!empty($wpdb->last_error)) {
        error_log('[ouinpo suite] Gate signatures unique index failed: ' . $wpdb->last_error);
    }
}

private static function gateSignatureIndexIsValid(string $table): bool
{
    global $wpdb;

    $rows = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'user_page'");
    if (empty($rows)) {
        return false;
    }

    usort($rows, static function ($a, $b): int {
        return (int) $a->Seq_in_index <=> (int) $b->Seq_in_index;
    });

    $columns = array_map(static fn($row): string => (string) $row->Column_name, $rows);
    $nonUniqueValues = array_map(static fn($row): int => (int) $row->Non_unique, $rows);

    return $columns === ['user_id', 'page_slug']
        && count(array_filter($nonUniqueValues)) === 0;
}
}
