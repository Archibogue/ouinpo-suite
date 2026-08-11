<?php

namespace Ouinpo\Exercises;



defined('ABSPATH') || exit;



class InstallV2 {

    const DB_VERSION = '2.8.0';

    const OPTION_KEY = 'ouinpo_exo_db_version';

    const ASSESSMENT_SCHEMA_CHECK_OPTION = 'ouinpo_exo_assessment_schema_checked';

    const SCHEMA_LOCK_OPTION = 'ouinpo_exo_schema_migration_lock';

    const SCHEMA_LOCK_TTL = 120;

    private static array $schema_errors = [];



    public static function maybe_upgrade() {

        $current = get_option(self::OPTION_KEY);

        if (version_compare((string) $current, self::DB_VERSION, '>=')) {

            self::maybe_repair_assessment_tables();

            return;

        }

        if (!self::acquire_schema_lock()) {

            return;

        }

        try {

            if (!self::upgrade_schema()) {

                return;

            }

            if (class_exists('\Ouinpo\Suite\Core\Installer')) {
                \Ouinpo\Suite\Core\Installer::ensureTrainingSchema();
            }

            update_option(self::OPTION_KEY, self::DB_VERSION, false);

        } finally {

            self::release_schema_lock();

        }

    }

    private static function maybe_repair_assessment_tables(): bool {

        if ((string) get_option(self::ASSESSMENT_SCHEMA_CHECK_OPTION) === self::DB_VERSION) {

            return true;

        }

        if (!self::acquire_schema_lock()) {

            return false;

        }

        try {

            if ((string) get_option(self::ASSESSMENT_SCHEMA_CHECK_OPTION) === self::DB_VERSION) {

                return true;

            }

            global $wpdb;

            self::reset_schema_errors();

            $charset = $wpdb->get_charset_collate();

            $charset_innodb = "ENGINE=InnoDB " . $charset;

            $p = "{$wpdb->prefix}ouin_exo_";

            $ok = self::ensure_assessment_results_schema($p, $charset_innodb);

            $ok = self::ensure_assessment_attendance_schema($p, $charset_innodb) && $ok;

            if ($ok && empty(self::$schema_errors)) {

                update_option(self::ASSESSMENT_SCHEMA_CHECK_OPTION, self::DB_VERSION, false);

                return true;

            }

            return false;

        } finally {

            self::release_schema_lock();

        }

    }



    public static function upgrade_schema(): bool {

        global $wpdb;

        self::reset_schema_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';



        $charset = $wpdb->get_charset_collate();

        $charset_innodb = "ENGINE=InnoDB " . $charset;

        $p = "{$wpdb->prefix}ouin_exo_";



        $sql_academic_years = "CREATE TABLE {$p}academic_years (

            id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,

            slug VARCHAR(20) NOT NULL,

            starts_on DATE NOT NULL,

            ends_on DATE NOT NULL,

            is_active TINYINT(1) NOT NULL DEFAULT 0,

            PRIMARY KEY  (id),

            UNIQUE KEY slug (slug)

        ) $charset_innodb;";

        

        $sql_school_levels = "CREATE TABLE {$p}school_levels (

            id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,

            slug VARCHAR(20) NOT NULL,

            label VARCHAR(50) NOT NULL,

            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,

            PRIMARY KEY  (id),

            UNIQUE KEY slug (slug)

        ) $charset_innodb;";

        

        $sql_groups = "CREATE TABLE {$p}groups (

            id INT UNSIGNED NOT NULL AUTO_INCREMENT,

            label VARCHAR(150) NOT NULL,

            year_id SMALLINT UNSIGNED NULL,

            school_level_id TINYINT UNSIGNED NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),

            KEY year_id (year_id),

            KEY school_level_id (school_level_id)

        ) $charset_innodb;";

        

        $sql_group_members = "CREATE TABLE {$p}group_members (
            group_id INT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role ENUM('student','teacher') NOT NULL DEFAULT 'student',
            school_level_id_override TINYINT UNSIGNED NULL,
            PRIMARY KEY  (group_id, user_id),
            KEY school_level_id_override (school_level_id_override),
            KEY idx_user_role_group (user_id, role, group_id)
        ) $charset_innodb;";

        

        $sql_exercises = "CREATE TABLE {$p}exercises (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level_id TINYINT UNSIGNED NULL,
            difficulty_id TINYINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            statement MEDIUMTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY level_id (level_id),
            KEY difficulty_id (difficulty_id),
            KEY idx_active_created (is_active, created_at, id)
        ) $charset_innodb;";

        

        $sql_exercise_school_level = "CREATE TABLE {$p}exercise_school_level (

            exercise_id BIGINT UNSIGNED NOT NULL,

            school_level_id TINYINT UNSIGNED NOT NULL,

            PRIMARY KEY  (exercise_id, school_level_id),

            KEY school_level_id (school_level_id)

        ) $charset_innodb;";

        

        $sql_hints = "CREATE TABLE {$p}hints (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            exercise_id BIGINT UNSIGNED NOT NULL,

            hint_order TINYINT UNSIGNED NOT NULL,

            content MEDIUMTEXT NOT NULL,

            PRIMARY KEY  (id),

            UNIQUE KEY exo_order (exercise_id, hint_order),

            KEY exo_id (exercise_id)

        ) $charset_innodb;";

        

        $sql_solutions = "CREATE TABLE {$p}solutions (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            exercise_id BIGINT UNSIGNED NOT NULL,

            title VARCHAR(150) NOT NULL,

            content MEDIUMTEXT NOT NULL,

            solution_order TINYINT UNSIGNED NOT NULL DEFAULT 1,

            is_official TINYINT(1) NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NULL,

            PRIMARY KEY  (id),

            UNIQUE KEY exo_order (exercise_id, solution_order),

            KEY exo_id (exercise_id)

        ) $charset_innodb;";

        

        $sql_user_reveals = "CREATE TABLE {$p}user_reveals (

            user_id BIGINT UNSIGNED NOT NULL,

            exercise_id BIGINT UNSIGNED NOT NULL,

            kind ENUM('hint','solution') NOT NULL,

            ref VARCHAR(20) NOT NULL,

            revealed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY  (user_id, exercise_id, kind, ref),

            KEY exo_kind (exercise_id, kind)

        ) $charset_innodb;";

        

        $sql_user_competencies = "CREATE TABLE {$p}user_competencies (

            user_id BIGINT UNSIGNED NOT NULL,

            competency_id BIGINT UNSIGNED NOT NULL,

            year_id SMALLINT UNSIGNED NOT NULL,

            group_id INT UNSIGNED NULL,

            status ENUM('not_acquired','in_progress','consolidating','acquired') NOT NULL DEFAULT 'not_acquired',

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            updated_by BIGINT UNSIGNED NULL,

            source ENUM('manual','exercise','assessment','import') NOT NULL DEFAULT 'manual',

            PRIMARY KEY  (user_id, competency_id, year_id),

            KEY competency_id (competency_id),

            KEY year_id (year_id),

            KEY group_id (group_id),

            KEY status (status)

        ) $charset_innodb;";



        $sql_competency_teaching = "CREATE TABLE {$p}competency_teaching (

            year_id SMALLINT UNSIGNED NOT NULL,

            group_id INT UNSIGNED NOT NULL,

            competency_id BIGINT UNSIGNED NOT NULL,

            teaching_state VARCHAR(20) NOT NULL DEFAULT 'seen',

            first_seen_at DATETIME NULL,

            state_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            updated_by BIGINT UNSIGNED NULL,

            PRIMARY KEY  (year_id, group_id, competency_id),

            KEY competency_id (competency_id),

            KEY group_id (group_id),

            KEY year_id (year_id),

            KEY teaching_state (teaching_state)

        ) $charset_innodb;";

        

        $sql_post_competency = "CREATE TABLE {$p}post_competency (

            post_id BIGINT UNSIGNED NOT NULL,

            competency_id BIGINT UNSIGNED NOT NULL,

            PRIMARY KEY  (post_id, competency_id),

            KEY competency_id (competency_id)

        ) $charset_innodb;";



        $sql_difficulties = "CREATE TABLE {$p}difficulties (

            id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,

            slug VARCHAR(50) NOT NULL UNIQUE,

            label VARCHAR(100) NOT NULL,

            PRIMARY KEY  (id)

        ) $charset_innodb;";



        $sql_user_status = "CREATE TABLE {$p}user_status (

            user_id BIGINT UNSIGNED NOT NULL,

            exercise_id BIGINT UNSIGNED NOT NULL,

            status ENUM('none','attempted','solved') NOT NULL DEFAULT 'none',

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            declared_at DATETIME NULL,
          
            PRIMARY KEY  (user_id, exercise_id),

            KEY exercise_id (exercise_id),

            KEY status (status)

        ) $charset_innodb;";

        $sql_domains = "CREATE TABLE {$p}domains (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            slug VARCHAR(128) NOT NULL,

            label VARCHAR(191) NOT NULL,

            track VARCHAR(50) NOT NULL DEFAULT '',

            description TEXT NULL,

            sort_order INT UNSIGNED NOT NULL DEFAULT 0,

            active TINYINT(1) NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),

            UNIQUE KEY uk_slug_track (slug, track),

            KEY idx_track (track),

            KEY idx_active (active),

            KEY idx_sort_order (sort_order)

        ) $charset_innodb;";



        $sql_competencies = "CREATE TABLE {$p}competencies (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            domain_id BIGINT UNSIGNED NULL,

            domain VARCHAR(120) NOT NULL,

            domain_slug VARCHAR(64) DEFAULT NULL,

            competency TEXT NOT NULL,

            capacity TEXT DEFAULT NULL,

            example TEXT DEFAULT NULL,

            track VARCHAR(50) NOT NULL,

            level VARCHAR(50) NOT NULL,

            reference_url VARCHAR(255) DEFAULT NULL,

            slug VARCHAR(128) DEFAULT NULL,

            active TINYINT(1) NOT NULL DEFAULT 1,

            label TEXT NOT NULL,

            cycle VARCHAR(20) DEFAULT NULL,

            PRIMARY KEY  (id),

            UNIQUE KEY uk_slug (slug),

            KEY domain_id (domain_id),

            KEY idx_track_level (track, level),

            KEY idx_domain (domain),

            KEY idx_domain_slug (domain_slug),

            KEY track (track)

        ) $charset_innodb;";

        $sql_competency_school_level = "CREATE TABLE {$p}competency_school_level (
            competency_id BIGINT UNSIGNED NOT NULL,
            school_level_id TINYINT UNSIGNED NOT NULL,
            PRIMARY KEY  (competency_id, school_level_id),
            KEY school_level_id (school_level_id)
        ) $charset_innodb;";

        $sql_competencies_import = "CREATE TABLE {$p}competencies_import (
            id INT UNSIGNED NOT NULL,
            domain VARCHAR(191) NOT NULL,
            domain_slug VARCHAR(191) NOT NULL,
            competency TEXT NOT NULL,
            capacity TEXT NULL,
            example TEXT NULL,
            track VARCHAR(50) NOT NULL,
            level VARCHAR(50) NOT NULL,
            reference_url VARCHAR(255) NULL,
            slug VARCHAR(191) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id)
        ) $charset_innodb;";

        $sql_exo_comp = "CREATE TABLE {$p}exercise_competency (

            exercise_id BIGINT UNSIGNED NOT NULL,

            competency_id BIGINT UNSIGNED NOT NULL,

            PRIMARY KEY  (exercise_id, competency_id),

            KEY competency_id (competency_id)

        ) $charset_innodb;";

        

        $sql_exam_meta = "CREATE TABLE {$p}exam_meta (

            exercise_id BIGINT UNSIGNED NOT NULL,

            exam_type ENUM('written','practical_subject') NOT NULL DEFAULT 'written',

            source_type ENUM('annale','inspired','type_bac','classic') NOT NULL DEFAULT 'type_bac',

            session_label VARCHAR(120) NULL,

            year_label VARCHAR(20) NULL,

            center_label VARCHAR(80) NULL,

            theme_bac VARCHAR(80) NULL,

            bac_format ENUM('question_courte','lecture_code','code_a_completer','ecriture_complete','raisonnement') NULL,

            estimated_minutes SMALLINT UNSIGNED NULL,

            is_exam_like TINYINT(1) NOT NULL DEFAULT 1,

            subject_group VARCHAR(80) NULL,

            sort_in_subject TINYINT UNSIGNED NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (exercise_id),

            KEY exam_type (exam_type),

            KEY source_type (source_type),

            KEY session_label (session_label),

            KEY theme_bac (theme_bac),

            KEY is_exam_like (is_exam_like),

            KEY subject_group (subject_group)

        ) $charset_innodb;";      



        $sql_practical_calls = "CREATE TABLE {$p}practical_calls (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            exercise_id BIGINT UNSIGNED NOT NULL,

            call_order TINYINT UNSIGNED NOT NULL,

            title VARCHAR(150) NULL,

            prompt_html MEDIUMTEXT NOT NULL,

            ai_rubric LONGTEXT NULL,

            answer_mode ENUM('text','code','mixed') NOT NULL DEFAULT 'code',

            max_points DECIMAL(5,2) NULL,

            is_active TINYINT(1) NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),

            UNIQUE KEY exo_call_order (exercise_id, call_order),

            KEY exercise_id (exercise_id),

            KEY is_active (is_active)

        ) $charset_innodb;";



        $sql_practical_files = "CREATE TABLE {$p}practical_files (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            exercise_id BIGINT UNSIGNED NOT NULL,

            practical_call_id BIGINT UNSIGNED NULL,

            wp_attachment_id BIGINT UNSIGNED NULL,

            label VARCHAR(150) NOT NULL,

            file_url VARCHAR(1000) NULL,

            file_kind ENUM('starter','resource','subject') NOT NULL DEFAULT 'starter',

            file_order TINYINT UNSIGNED NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),

            KEY exercise_id (exercise_id),

            KEY practical_call_id (practical_call_id),

            KEY wp_attachment_id (wp_attachment_id),

            KEY file_kind (file_kind)

        ) $charset_innodb;";

        

        $sql_practical_call_attempts = "CREATE TABLE {$p}practical_call_attempts (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT UNSIGNED NOT NULL,

            exercise_id BIGINT UNSIGNED NOT NULL,

            practical_call_id BIGINT UNSIGNED NOT NULL,

            answer_text LONGTEXT NOT NULL,

            verdict ENUM('correct','partial','incorrect') NOT NULL DEFAULT 'incorrect',

            feedback LONGTEXT NULL,

            confidence DECIMAL(4,3) NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),

            KEY idx_user_call (user_id, practical_call_id),

            KEY idx_exercise_call (exercise_id, practical_call_id),

            KEY idx_created (created_at)

        ) $charset_innodb;";

        

        $sql_practical_call_status = "CREATE TABLE {$p}practical_call_status (

            user_id BIGINT UNSIGNED NOT NULL,

            exercise_id BIGINT UNSIGNED NOT NULL,

            practical_call_id BIGINT UNSIGNED NOT NULL,

            status ENUM('none','attempted','solved') NOT NULL DEFAULT 'none',

            declared_at DATETIME NULL,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (user_id, exercise_id, practical_call_id),

            KEY exercise_id (exercise_id),

            KEY practical_call_id (practical_call_id),

            KEY status (status)

        ) $charset_innodb;";



        $sql_badges = "CREATE TABLE {$p}badges (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            slug VARCHAR(100) NOT NULL UNIQUE,

            title VARCHAR(200) NOT NULL,

            description TEXT NULL,

            theme VARCHAR(64) NULL,

            image_url VARCHAR(255) NULL,

            PRIMARY KEY  (id)

        ) $charset_innodb;";

        

        $sql_user_badges = "CREATE TABLE {$p}user_badges (

            user_id BIGINT UNSIGNED NOT NULL,

            badge_id BIGINT UNSIGNED NOT NULL,

            awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            source ENUM('auto','manual','path') NOT NULL DEFAULT 'auto',

            PRIMARY KEY  (user_id, badge_id),

            KEY badge_id (badge_id)

        ) $charset_innodb;";



        $sql_assessments = "CREATE TABLE {$p}assessments (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            title VARCHAR(200) NOT NULL,

            group_id INT UNSIGNED NULL,

            due_on DATE NULL,

            weight DECIMAL(5,2) NULL,

            notes TEXT NULL,

            PRIMARY KEY  (id),

            KEY group_id (group_id),

            KEY due_on (due_on)

        ) $charset_innodb;";



        $sql_assessment_items = "CREATE TABLE {$p}assessment_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessment_id BIGINT UNSIGNED NOT NULL,
            exercise_id BIGINT UNSIGNED NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            points DECIMAL(5,2) NULL,
            PRIMARY KEY  (id),
            KEY assessment_id (assessment_id),
            KEY exercise_id (exercise_id)
        ) $charset_innodb;";



        $sql_assessment_competencies = "CREATE TABLE {$p}assessment_competencies (

            assessment_id BIGINT UNSIGNED NOT NULL,

            competency_id BIGINT UNSIGNED NOT NULL,

            PRIMARY KEY  (assessment_id, competency_id),

            KEY competency_id (competency_id)

        ) $charset_innodb;";



        $sql_written_subjects = "CREATE TABLE {$p}written_subjects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            statement MEDIUMTEXT NULL,
            source_type ENUM('annale','inspired','type_bac') NOT NULL DEFAULT 'annale',
            session_label VARCHAR(120) NULL,
            year_label VARCHAR(20) NULL,
            center_label VARCHAR(120) NULL,
            subject_group VARCHAR(120) NULL,
            estimated_minutes SMALLINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY source_type (source_type),
            KEY session_label (session_label),
            KEY year_label (year_label),
            KEY center_label (center_label),
            KEY subject_group (subject_group),
            KEY is_active (is_active)
        ) $charset_innodb;";

        $sql_written_subject_school_level = "CREATE TABLE {$p}written_subject_school_level (
            subject_id BIGINT UNSIGNED NOT NULL,
            school_level_id TINYINT UNSIGNED NOT NULL,
            PRIMARY KEY  (subject_id, school_level_id),
            KEY school_level_id (school_level_id)
        ) $charset_innodb;";

        $sql_written_exercises = "CREATE TABLE {$p}written_exercises (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subject_id BIGINT UNSIGNED NOT NULL,
            exercise_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            title VARCHAR(255) NULL,
            intro_html MEDIUMTEXT NULL,
            max_points DECIMAL(5,2) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY subject_order (subject_id, exercise_order),
            KEY subject_id (subject_id),
            KEY is_active (is_active)
        ) $charset_innodb;";

        $sql_written_questions = "CREATE TABLE {$p}written_questions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            exercise_id BIGINT UNSIGNED NOT NULL,
            question_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            question_label VARCHAR(40) NOT NULL,
            prompt_html MEDIUMTEXT NOT NULL,
            answer_type ENUM('text','code','sql','mixed') NOT NULL DEFAULT 'text',
            max_points DECIMAL(5,2) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY exercise_order (exercise_id, question_order),
            KEY exercise_id (exercise_id),
            KEY is_active (is_active)
        ) $charset_innodb;";

        $sql_written_question_competency = "CREATE TABLE {$p}written_question_competency (
            question_id BIGINT UNSIGNED NOT NULL,
            competency_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (question_id, competency_id),
            KEY competency_id (competency_id)
        ) $charset_innodb;";

        $sql_written_question_hints = "CREATE TABLE {$p}written_question_hints (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT UNSIGNED NOT NULL,
            hint_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            title VARCHAR(150) NULL,
            content MEDIUMTEXT NOT NULL,
            is_ai TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY question_order (question_id, hint_order),
            KEY question_id (question_id),
            KEY is_ai (is_ai)
        ) $charset_innodb;";

        $sql_written_question_status = "CREATE TABLE {$p}written_question_status (
            user_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            status ENUM('none','attempted','solved') NOT NULL DEFAULT 'none',
            attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_attempt_at DATETIME NULL,
            declared_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (user_id, question_id),
            KEY question_id (question_id),
            KEY status (status)
        ) $charset_innodb;";

        $sql_written_question_answers = "CREATE TABLE {$p}written_question_answers (
            user_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            answer_text LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (user_id, question_id),
            KEY question_id (question_id),
            KEY updated_at (updated_at)
        ) $charset_innodb;";

        $sql_written_hint_usage = "CREATE TABLE {$p}written_hint_usage (
            user_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            hint_id BIGINT UNSIGNED NOT NULL,
            used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (user_id, question_id, hint_id),
            KEY question_id (question_id),
            KEY hint_id (hint_id)
        ) $charset_innodb;";

        $sql_subject_files = "CREATE TABLE {$p}subject_files (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subject_type ENUM('written','practical') NOT NULL DEFAULT 'written',
            subject_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(150) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_file_name VARCHAR(255) NULL,
            file_url VARCHAR(1000) NOT NULL,
            file_size BIGINT UNSIGNED NULL,
            file_hash CHAR(64) NULL,
            file_kind ENUM('subject','resource','correction') NOT NULL DEFAULT 'subject',
            file_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY subject_lookup (subject_type, subject_id),
            KEY file_identity (subject_type, file_kind, original_file_name(120), file_size),
            KEY file_kind (file_kind)
        ) $charset_innodb;";

        $sql_correction_batches = "CREATE TABLE {$p}correction_batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assessment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            group_id INT UNSIGNED NULL,
            teacher_id BIGINT UNSIGNED NOT NULL,
            source_type ENUM('scan','file','manual') NOT NULL DEFAULT 'scan',
            context_type ENUM('assessment','exercise','practical','free') NOT NULL DEFAULT 'assessment',
            context_id BIGINT UNSIGNED NULL,
            status ENUM('draft','analyzing','review','validated','error') NOT NULL DEFAULT 'draft',
            title VARCHAR(200) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY assessment_id (assessment_id),
            KEY group_id (group_id),
            KEY teacher_id (teacher_id),
            KEY source_type (source_type),
            KEY context_type (context_type),
            KEY context_id (context_id),
            KEY status (status)
        ) $charset_innodb;";

        $sql_correction_copies = "CREATE TABLE {$p}correction_copies (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id BIGINT UNSIGNED NOT NULL,
            student_user_id BIGINT UNSIGNED NULL,
            student_ref VARCHAR(120) NOT NULL,
            source_type ENUM('scan','file','manual') NOT NULL DEFAULT 'scan',
            file_name VARCHAR(255) NULL,
            file_path TEXT NULL,
            file_url TEXT NULL,
            mime_type VARCHAR(120) NULL,
            file_size BIGINT UNSIGNED NULL,
            pages_count SMALLINT UNSIGNED NULL,
            ocr_text LONGTEXT NULL,
            extraction_type VARCHAR(40) NULL,
            file_manifest LONGTEXT NULL,
            extracted_content LONGTEXT NULL,
            extraction_warnings LONGTEXT NULL,
            status ENUM('uploaded','ocr_needed','ready','analyzing','proposal','validated','rejected','error') NOT NULL DEFAULT 'uploaded',
            ai_proposal LONGTEXT NULL,
            validated_correction LONGTEXT NULL,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY batch_id (batch_id),
            KEY student_user_id (student_user_id),
            KEY source_type (source_type),
            KEY status (status)
        ) $charset_innodb;";

        $sql_correction_items = "CREATE TABLE {$p}correction_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            copy_id BIGINT UNSIGNED NOT NULL,
            exercise_id BIGINT UNSIGNED NOT NULL,
            suggested_points DECIMAL(6,2) NULL,
            max_points DECIMAL(6,2) NULL,
            confidence DECIMAL(4,3) NULL,
            feedback LONGTEXT NULL,
            competencies LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY copy_id (copy_id),
            KEY exercise_id (exercise_id)
        ) $charset_innodb;";

        

        $sql_ai_attempts = "CREATE TABLE {$p}ai_attempts (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            user_id BIGINT UNSIGNED NOT NULL,

            exercise_id BIGINT UNSIGNED NOT NULL,

            answer_text LONGTEXT NOT NULL,

            verdict ENUM('correct','partial','incorrect') NOT NULL DEFAULT 'incorrect',

            feedback LONGTEXT NULL,

            confidence DECIMAL(4,3) NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),

            KEY idx_user_exo (user_id, exercise_id),

            KEY idx_exercise (exercise_id),

            KEY idx_created (created_at)

        ) $charset_innodb;"; 



        $schema_ok = true;

        $schema_ok = self::run_db_delta($sql_academic_years, 'academic_years') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_school_levels, 'school_levels') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_groups, 'groups') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_group_members, 'group_members') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_exercises, 'exercises') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_exercise_school_level, 'exercise_school_level') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_hints, 'hints') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_solutions, 'solutions') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_user_reveals, 'user_reveals') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_user_competencies, 'user_competencies') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_competency_teaching, 'competency_teaching') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_post_competency, 'post_competency') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_difficulties, 'difficulties') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_ai_attempts, 'ai_attempts') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_user_status, 'user_status') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_domains, 'domains') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_competencies, 'competencies') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_competency_school_level, 'competency_school_level') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_competencies_import, 'competencies_import') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_exo_comp, 'exo_comp') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_exam_meta, 'exam_meta') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_practical_calls, 'practical_calls') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_practical_files, 'practical_files') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_subjects, 'written_subjects') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_subject_school_level, 'written_subject_school_level') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_exercises, 'written_exercises') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_questions, 'written_questions') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_question_competency, 'written_question_competency') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_question_hints, 'written_question_hints') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_question_status, 'written_question_status') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_question_answers, 'written_question_answers') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_written_hint_usage, 'written_hint_usage') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_subject_files, 'subject_files') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_practical_call_attempts, 'practical_call_attempts') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_practical_call_status, 'practical_call_status') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_badges, 'badges') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_user_badges, 'user_badges') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_assessments, 'assessments') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_assessment_items, 'assessment_items') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_assessment_competencies, 'assessment_competencies') && $schema_ok;
        $schema_ok = self::ensure_assessment_results_schema($p, $charset_innodb) && $schema_ok;
        $schema_ok = self::ensure_assessment_attendance_schema($p, $charset_innodb) && $schema_ok;
        $schema_ok = self::run_db_delta($sql_correction_batches, 'correction_batches') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_correction_copies, 'correction_copies') && $schema_ok;
        $schema_ok = self::run_db_delta($sql_correction_items, 'correction_items') && $schema_ok;



        $table_exo = $p . 'exercises';

        $has_col = $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*)

             FROM INFORMATION_SCHEMA.COLUMNS

             WHERE TABLE_SCHEMA = %s

               AND TABLE_NAME = %s

               AND COLUMN_NAME = 'difficulty_id'",

            DB_NAME,

            $table_exo

        ));



        if (!$has_col) {

            $wpdb->query("ALTER TABLE {$table_exo} ADD COLUMN difficulty_id TINYINT UNSIGNED NULL AFTER level_id");

            $wpdb->query("ALTER TABLE {$table_exo} ADD KEY difficulty_id (difficulty_id)");

        }



        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}difficulties") === 0) {

            $wpdb->insert($p . 'difficulties', ['slug' => 'debutant', 'label' => 'Débutant']);

            $wpdb->insert($p . 'difficulties', ['slug' => 'confirme', 'label' => 'Confirmé']);

            $wpdb->insert($p . 'difficulties', ['slug' => 'expert', 'label' => 'Expert']);

        }

        

        self::ensure_school_level_sort_order();
        self::seed_school_levels();
        self::migrate_competency_track_column();
        self::migrate_competency_level_column();
        self::migrate_domains();
        self::migrate_competency_school_levels();
        self::ensure_assessment_item_edit_columns();
        self::ensure_written_question_attempt_columns();
        if (class_exists('\Ouinpo\Suite\Core\Installer')) {
            \Ouinpo\Suite\Core\Installer::ensureYearClosureSchema();
        }

        self::seed_year_if_missing();

        self::ensure_constraints();

        $ok = $schema_ok && empty(self::$schema_errors);

        if ($ok) {

            update_option(self::ASSESSMENT_SCHEMA_CHECK_OPTION, self::DB_VERSION, false);

        }

        return $ok;

        

    }

    

    private static function seed_school_levels() {

    global $wpdb;

    $t = $wpdb->prefix . 'ouin_exo_school_levels';

    if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t))) {
        return;
    }

    if (get_option('ouinpo_exo_default_school_levels_seeded')) {
        return;
    }

    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");
    if ($count > 0) {
        update_option('ouinpo_exo_default_school_levels_seeded', '1', false);
        return;
    }

    $wpdb->query("INSERT IGNORE INTO {$t} (slug,label,sort_order) VALUES

        ('seconde','Seconde',10),

        ('premiere','Première',20),

        ('terminale','Terminale',30)");

    update_option('ouinpo_exo_default_school_levels_seeded', '1', false);

    }

    private static function ensure_school_level_sort_order(): void {

        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_school_levels';

        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return;
        }

        $column = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
               AND COLUMN_NAME = 'sort_order'
             LIMIT 1",
            DB_NAME,
            $table
        ));

        if ($column === '') {
            $wpdb->query("ALTER TABLE {$table} ADD sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER label");
        }

        $rows = $wpdb->get_results("SELECT id, sort_order FROM {$table} ORDER BY id ASC");
        $position = 10;

        foreach ((array) $rows as $row) {
            if ((int) $row->sort_order > 0) {
                continue;
            }

            $wpdb->update(
                $table,
                ['sort_order' => $position],
                ['id' => (int) $row->id],
                ['%d'],
                ['%d']
            );

            $position += 10;
        }
    }

    public static function ensure_assessment_item_edit_columns(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_assessment_items';

        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return;
        }

        $columns = $wpdb->get_col($wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s",
            DB_NAME,
            $table
        )) ?: [];

        if (!in_array('sort_order', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER exercise_id");
        }

        if (!in_array('points', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN points DECIMAL(5,2) NULL AFTER sort_order");
        }
    }

    private static function migrate_competency_track_column(): void {

        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_competencies';

        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return;
        }

        $column_type = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
               AND COLUMN_NAME = 'track'
             LIMIT 1",
            DB_NAME,
            $table
        ));

        if ($column_type !== '' && stripos($column_type, 'enum(') === 0) {
            $wpdb->query("ALTER TABLE {$table} MODIFY track VARCHAR(50) NOT NULL");
        }

    }

    private static function migrate_competency_level_column() {

        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_competencies';

        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return;
        }

        $column_type = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
               AND COLUMN_NAME = 'level'
             LIMIT 1",
            DB_NAME,
            $table
        ));

        if ($column_type !== '' && stripos($column_type, 'enum(') === 0) {
            $wpdb->query("ALTER TABLE {$table} MODIFY level VARCHAR(50) NOT NULL");
        }

    }

    private static function migrate_domains(): void {

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';
        $tbl_domains = $p . 'domains';
        $tbl_comp = $p . 'competencies';

        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl_domains))
            || !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl_comp))) {
            return;
        }

        $has_domain_id = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
               AND COLUMN_NAME = 'domain_id'
             LIMIT 1",
            DB_NAME,
            $tbl_comp
        ));

        if ($has_domain_id === '') {
            $wpdb->query("ALTER TABLE {$tbl_comp} ADD domain_id BIGINT UNSIGNED NULL AFTER id");
            $wpdb->query("ALTER TABLE {$tbl_comp} ADD KEY domain_id (domain_id)");
        }

        $wpdb->query("
            INSERT IGNORE INTO {$tbl_domains} (slug, label, track, sort_order, active)
            SELECT
                c.domain_slug,
                MAX(c.domain) AS label,
                COALESCE(NULLIF(c.track, ''), '') AS track,
                0 AS sort_order,
                1 AS active
            FROM {$tbl_comp} c
            WHERE c.domain_slug IS NOT NULL
              AND c.domain_slug <> ''
              AND c.domain IS NOT NULL
              AND c.domain <> ''
            GROUP BY c.domain_slug, COALESCE(NULLIF(c.track, ''), '')
        ");

        $wpdb->query("
            UPDATE {$tbl_domains} d
            JOIN (
                SELECT id
                FROM {$tbl_domains}
                WHERE sort_order = 0
                ORDER BY track ASC, label ASC, id ASC
            ) ordered ON ordered.id = d.id
            SET d.sort_order = d.id * 10
            WHERE d.sort_order = 0
        ");

        $wpdb->query("
            UPDATE {$tbl_comp} c
            JOIN {$tbl_domains} d
              ON d.slug = c.domain_slug
             AND d.track = COALESCE(NULLIF(c.track, ''), '')
            SET c.domain_id = d.id
            WHERE c.domain_id IS NULL
              AND c.domain_slug IS NOT NULL
              AND c.domain_slug <> ''
        ");

    }

    private static function migrate_competency_school_levels() {

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';
        $tbl_comp = $p . 'competencies';
        $tbl_levels = $p . 'school_levels';
        $tbl_link = $p . 'competency_school_level';

        $tables_ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl_link));
        if (!$tables_ready) {
            return;
        }

        $wpdb->query("
            INSERT IGNORE INTO {$tbl_link} (competency_id, school_level_id)
            SELECT c.id, sl.id
              FROM {$tbl_comp} c
              JOIN {$tbl_levels} sl
                ON sl.label = c.level
                OR sl.slug = c.level
             WHERE c.level <> 'Transversal'
        ");

        $wpdb->query("
            INSERT IGNORE INTO {$tbl_link} (competency_id, school_level_id)
            SELECT c.id, sl.id
              FROM {$tbl_comp} c
              CROSS JOIN {$tbl_levels} sl
             WHERE c.level = 'Transversal'
        ");

    }



    private static function seed_year_if_missing() {

        global $wpdb;

        $t = $wpdb->prefix . 'ouin_exo_academic_years';



        $exists = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}");

        if (!$exists) {

            $wpdb->query($wpdb->prepare(

                "INSERT INTO {$t} (slug,starts_on,ends_on,is_active) VALUES (%s,%s,%s,1)",

                self::default_academic_year_slug(),

                self::default_academic_year_start(),

                self::default_academic_year_end()

            ));

            update_option('ouin_exo_active_year_id', (int)$wpdb->insert_id);

        }

    }

    private static function default_academic_year_slug(): string {
        $year = (int) gmdate('Y');
        $month = (int) gmdate('n');
        $start = $month >= 9 ? $year : $year - 1;

        return $start . '-' . ($start + 1);
    }

    private static function default_academic_year_start(): string {
        $start = (int) substr(self::default_academic_year_slug(), 0, 4);

        return $start . '-09-01';
    }

    private static function default_academic_year_end(): string {
        $start = (int) substr(self::default_academic_year_slug(), 0, 4);

        return ($start + 1) . '-08-31';
    }

    

    private static function ensure_constraints(): void {

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';

    

        // Unique déjà présent en prod

        self::add_unique_if_missing(

            $p . 'assessment_items',

            'uq_assessment_items_assessment_exercise',

            "ALTER TABLE {$p}assessment_items

             ADD CONSTRAINT uq_assessment_items_assessment_exercise

             UNIQUE (assessment_id, exercise_id)"

        );

    

        // Foreign keys alignées sur la prod actuelle

        self::add_fk_if_missing(

            $p . 'ai_attempts',

            'fk_ai_attempts_exercise',

            "ALTER TABLE {$p}ai_attempts

             ADD CONSTRAINT fk_ai_attempts_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'assessments',

            'fk_assessments_group',

            "ALTER TABLE {$p}assessments

             ADD CONSTRAINT fk_assessments_group

             FOREIGN KEY (group_id) REFERENCES {$p}groups(id)

             ON DELETE SET NULL"

        );

    

        self::add_fk_if_missing(

            $p . 'assessment_competencies',

            'fk_assessment_competencies_assessment',

            "ALTER TABLE {$p}assessment_competencies

             ADD CONSTRAINT fk_assessment_competencies_assessment

             FOREIGN KEY (assessment_id) REFERENCES {$p}assessments(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'assessment_competencies',

            'fk_assessment_competencies_competency',

            "ALTER TABLE {$p}assessment_competencies

             ADD CONSTRAINT fk_assessment_competencies_competency

             FOREIGN KEY (competency_id) REFERENCES {$p}competencies(id)"

        );

    

        self::add_fk_if_missing(

            $p . 'assessment_items',

            'fk_assessment_items_assessment',

            "ALTER TABLE {$p}assessment_items

             ADD CONSTRAINT fk_assessment_items_assessment

             FOREIGN KEY (assessment_id) REFERENCES {$p}assessments(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'assessment_items',

            'fk_assessment_items_exercise',

            "ALTER TABLE {$p}assessment_items

             ADD CONSTRAINT fk_assessment_items_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)"

        );

    

        self::add_fk_if_missing(

            $p . 'assessment_results',

            'fk_assessment_results_assessment',

            "ALTER TABLE {$p}assessment_results

             ADD CONSTRAINT fk_assessment_results_assessment

             FOREIGN KEY (assessment_id) REFERENCES {$p}assessments(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'assessment_results',

            'fk_assessment_results_competency',

            "ALTER TABLE {$p}assessment_results

             ADD CONSTRAINT fk_assessment_results_competency

             FOREIGN KEY (competency_id) REFERENCES {$p}competencies(id)"

        );

    

        self::add_fk_if_missing(

            $p . 'exercises',

            'fk_exercises_difficulty',

            "ALTER TABLE {$p}exercises

             ADD CONSTRAINT fk_exercises_difficulty

             FOREIGN KEY (difficulty_id) REFERENCES {$p}difficulties(id)

             ON DELETE SET NULL"

        );

    

        self::add_fk_if_missing(

            $p . 'exercises',

            'fk_exercises_level',

            "ALTER TABLE {$p}exercises

             ADD CONSTRAINT fk_exercises_level

             FOREIGN KEY (level_id) REFERENCES {$p}school_levels(id)

             ON DELETE SET NULL"

        );

    

        self::add_fk_if_missing(

            $p . 'exercise_competency',

            'fk_exercise_competency_competency',

            "ALTER TABLE {$p}exercise_competency

             ADD CONSTRAINT fk_exercise_competency_competency

             FOREIGN KEY (competency_id) REFERENCES {$p}competencies(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'exercise_competency',

            'fk_exercise_competency_exercise',

            "ALTER TABLE {$p}exercise_competency

             ADD CONSTRAINT fk_exercise_competency_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'exercise_school_level',

            'fk_exercise_school_level_exercise',

            "ALTER TABLE {$p}exercise_school_level

             ADD CONSTRAINT fk_exercise_school_level_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

        self::add_fk_if_missing(

            $p . 'competency_school_level',

            'fk_competency_school_level_competency',

            "ALTER TABLE {$p}competency_school_level

             ADD CONSTRAINT fk_competency_school_level_competency

             FOREIGN KEY (competency_id) REFERENCES {$p}competencies(id)

             ON DELETE CASCADE"

        );

        self::add_fk_if_missing(

            $p . 'competency_school_level',

            'fk_competency_school_level_level',

            "ALTER TABLE {$p}competency_school_level

             ADD CONSTRAINT fk_competency_school_level_level

             FOREIGN KEY (school_level_id) REFERENCES {$p}school_levels(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'exercise_school_level',

            'fk_exercise_school_level_level',

            "ALTER TABLE {$p}exercise_school_level

             ADD CONSTRAINT fk_exercise_school_level_level

             FOREIGN KEY (school_level_id) REFERENCES {$p}school_levels(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'groups',

            'fk_groups_school_level',

            "ALTER TABLE {$p}groups

             ADD CONSTRAINT fk_groups_school_level

             FOREIGN KEY (school_level_id) REFERENCES {$p}school_levels(id)

             ON DELETE SET NULL"

        );

    

        self::add_fk_if_missing(

            $p . 'groups',

            'fk_groups_year',

            "ALTER TABLE {$p}groups

             ADD CONSTRAINT fk_groups_year

             FOREIGN KEY (year_id) REFERENCES {$p}academic_years(id)

             ON DELETE SET NULL"

        );

    

        self::add_fk_if_missing(

            $p . 'group_members',

            'fk_group_members_group',

            "ALTER TABLE {$p}group_members

             ADD CONSTRAINT fk_group_members_group

             FOREIGN KEY (group_id) REFERENCES {$p}groups(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'group_members',

            'fk_group_members_school_level_override',

            "ALTER TABLE {$p}group_members

             ADD CONSTRAINT fk_group_members_school_level_override

             FOREIGN KEY (school_level_id_override) REFERENCES {$p}school_levels(id)

             ON DELETE SET NULL"

        );

    

        self::add_fk_if_missing(

            $p . 'hints',

            'fk_hints_exercise',

            "ALTER TABLE {$p}hints

             ADD CONSTRAINT fk_hints_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'post_competency',

            'fk_post_competency_competency',

            "ALTER TABLE {$p}post_competency

             ADD CONSTRAINT fk_post_competency_competency

             FOREIGN KEY (competency_id) REFERENCES {$p}competencies(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'solutions',

            'fk_solutions_exercise',

            "ALTER TABLE {$p}solutions

             ADD CONSTRAINT fk_solutions_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'user_competencies',

            'fk_uc_comp',

            "ALTER TABLE {$p}user_competencies

             ADD CONSTRAINT fk_uc_comp

             FOREIGN KEY (competency_id) REFERENCES {$p}competencies(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'user_competencies',

            'fk_user_competencies_group',

            "ALTER TABLE {$p}user_competencies

             ADD CONSTRAINT fk_user_competencies_group

             FOREIGN KEY (group_id) REFERENCES {$p}groups(id)

             ON DELETE SET NULL"

        );

    

        self::add_fk_if_missing(

            $p . 'user_competencies',

            'fk_user_competencies_year',

            "ALTER TABLE {$p}user_competencies

             ADD CONSTRAINT fk_user_competencies_year

             FOREIGN KEY (year_id) REFERENCES {$p}academic_years(id)"

        );

    

        self::add_fk_if_missing(

            $p . 'user_reveals',

            'fk_user_reveals_exercise',

            "ALTER TABLE {$p}user_reveals

             ADD CONSTRAINT fk_user_reveals_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

    

        self::add_fk_if_missing(

            $p . 'user_status',

            'fk_user_status_exercise',

            "ALTER TABLE {$p}user_status

             ADD CONSTRAINT fk_user_status_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

        

        self::add_fk_if_missing(

            $p . 'competency_teaching',

            'fk_competency_teaching_comp',

            "ALTER TABLE {$p}competency_teaching

             ADD CONSTRAINT fk_competency_teaching_comp

             FOREIGN KEY (competency_id) REFERENCES {$p}competencies(id)

             ON DELETE CASCADE"

        );        



        self::add_fk_if_missing(

            $p . 'competency_teaching',

            'fk_competency_teaching_group',

            "ALTER TABLE {$p}competency_teaching

             ADD CONSTRAINT fk_competency_teaching_group

             FOREIGN KEY (group_id) REFERENCES {$p}groups(id)

             ON DELETE CASCADE"

        );       



        self::add_fk_if_missing(

            $p . 'competency_teaching',

            'fk_competency_teaching_year',

            "ALTER TABLE {$p}competency_teaching

             ADD CONSTRAINT fk_competency_teaching_year

             FOREIGN KEY (year_id) REFERENCES {$p}academic_years(id)

             ON DELETE CASCADE"

        );

        

        self::add_fk_if_missing(

            $p . 'assessment_attendance',

            'fk_assessment_attendance_assessment',

            "ALTER TABLE {$p}assessment_attendance

             ADD CONSTRAINT fk_assessment_attendance_assessment

             FOREIGN KEY (assessment_id) REFERENCES {$p}assessments(id)

             ON DELETE CASCADE"

        );

        

        self::add_fk_if_missing(

            $p . 'exam_meta',

            'fk_exam_meta_exercise',

            "ALTER TABLE {$p}exam_meta

             ADD CONSTRAINT fk_exam_meta_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );        



        self::add_fk_if_missing(

            $p . 'practical_calls',

            'fk_practical_calls_exercise',

            "ALTER TABLE {$p}practical_calls

             ADD CONSTRAINT fk_practical_calls_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

        

        self::add_fk_if_missing(

            $p . 'practical_files',

            'fk_practical_files_exercise',

            "ALTER TABLE {$p}practical_files

             ADD CONSTRAINT fk_practical_files_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

        

        self::add_fk_if_missing(

            $p . 'practical_files',

            'fk_practical_files_call',

            "ALTER TABLE {$p}practical_files

             ADD CONSTRAINT fk_practical_files_call

             FOREIGN KEY (practical_call_id) REFERENCES {$p}practical_calls(id)

             ON DELETE SET NULL"

        );

        

        self::add_fk_if_missing(

            $p . 'practical_call_attempts',

            'fk_practical_call_attempts_exercise',

            "ALTER TABLE {$p}practical_call_attempts

             ADD CONSTRAINT fk_practical_call_attempts_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

        

        self::add_fk_if_missing(

            $p . 'practical_call_attempts',

            'fk_practical_call_attempts_call',

            "ALTER TABLE {$p}practical_call_attempts

             ADD CONSTRAINT fk_practical_call_attempts_call

             FOREIGN KEY (practical_call_id) REFERENCES {$p}practical_calls(id)

             ON DELETE CASCADE"

        );

        

        self::add_fk_if_missing(

            $p . 'practical_call_status',

            'fk_practical_call_status_exercise',

            "ALTER TABLE {$p}practical_call_status

             ADD CONSTRAINT fk_practical_call_status_exercise

             FOREIGN KEY (exercise_id) REFERENCES {$p}exercises(id)

             ON DELETE CASCADE"

        );

        

        self::add_fk_if_missing(

            $p . 'practical_call_status',

            'fk_practical_call_status_call',

            "ALTER TABLE {$p}practical_call_status

             ADD CONSTRAINT fk_practical_call_status_call

             FOREIGN KEY (practical_call_id) REFERENCES {$p}practical_calls(id)

             ON DELETE CASCADE"

        );

        self::add_fk_if_missing(
            $p . 'written_subject_school_level',
            'fk_written_subject_level_subject',
            "ALTER TABLE {$p}written_subject_school_level
             ADD CONSTRAINT fk_written_subject_level_subject
             FOREIGN KEY (subject_id) REFERENCES {$p}written_subjects(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_subject_school_level',
            'fk_written_subject_level_level',
            "ALTER TABLE {$p}written_subject_school_level
             ADD CONSTRAINT fk_written_subject_level_level
             FOREIGN KEY (school_level_id) REFERENCES {$p}school_levels(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_exercises',
            'fk_written_exercises_subject',
            "ALTER TABLE {$p}written_exercises
             ADD CONSTRAINT fk_written_exercises_subject
             FOREIGN KEY (subject_id) REFERENCES {$p}written_subjects(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_questions',
            'fk_written_questions_exercise',
            "ALTER TABLE {$p}written_questions
             ADD CONSTRAINT fk_written_questions_exercise
             FOREIGN KEY (exercise_id) REFERENCES {$p}written_exercises(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_question_competency',
            'fk_written_question_competency_question',
            "ALTER TABLE {$p}written_question_competency
             ADD CONSTRAINT fk_written_question_competency_question
             FOREIGN KEY (question_id) REFERENCES {$p}written_questions(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_question_competency',
            'fk_written_question_competency_competency',
            "ALTER TABLE {$p}written_question_competency
             ADD CONSTRAINT fk_written_question_competency_competency
             FOREIGN KEY (competency_id) REFERENCES {$p}competencies(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_question_hints',
            'fk_written_question_hints_question',
            "ALTER TABLE {$p}written_question_hints
             ADD CONSTRAINT fk_written_question_hints_question
             FOREIGN KEY (question_id) REFERENCES {$p}written_questions(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_question_status',
            'fk_written_question_status_question',
            "ALTER TABLE {$p}written_question_status
             ADD CONSTRAINT fk_written_question_status_question
             FOREIGN KEY (question_id) REFERENCES {$p}written_questions(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_question_answers',
            'fk_written_question_answers_question',
            "ALTER TABLE {$p}written_question_answers
             ADD CONSTRAINT fk_written_question_answers_question
             FOREIGN KEY (question_id) REFERENCES {$p}written_questions(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_hint_usage',
            'fk_written_hint_usage_question',
            "ALTER TABLE {$p}written_hint_usage
             ADD CONSTRAINT fk_written_hint_usage_question
             FOREIGN KEY (question_id) REFERENCES {$p}written_questions(id)
             ON DELETE CASCADE"
        );

        self::add_fk_if_missing(
            $p . 'written_hint_usage',
            'fk_written_hint_usage_hint',
            "ALTER TABLE {$p}written_hint_usage
             ADD CONSTRAINT fk_written_hint_usage_hint
             FOREIGN KEY (hint_id) REFERENCES {$p}written_question_hints(id)
             ON DELETE CASCADE"
        );

    }

    private static function reset_schema_errors(): void {
        self::$schema_errors = [];
    }

    private static function acquire_schema_lock(): bool {
        $now = time();
        $current = (int) get_option(self::SCHEMA_LOCK_OPTION, 0);

        if ($current > 0 && ($now - $current) > self::SCHEMA_LOCK_TTL) {
            delete_option(self::SCHEMA_LOCK_OPTION);
        }

        return add_option(self::SCHEMA_LOCK_OPTION, (string) $now, '', false);
    }

    private static function release_schema_lock(): void {
        delete_option(self::SCHEMA_LOCK_OPTION);
    }

    private static function normalize_db_delta_sql(string $sql): string {
        $lines = preg_split('/\R/', $sql);
        if (!is_array($lines)) {
            return trim($sql);
        }

        $normalized = [];
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                continue;
            }

            $normalized[] = $line;
        }

        return implode("\n", $normalized);
    }

    private static function run_db_delta(string $sql, string $context): bool {
        global $wpdb;

        $wpdb->last_error = '';
        dbDelta(self::normalize_db_delta_sql($sql));

        if (!empty($wpdb->last_error)) {
            self::record_schema_error('dbDelta ' . $context, (string) $wpdb->last_error);
            return false;
        }

        return true;
    }

    private static function record_schema_error(string $context, string $message = ''): void {
        $message = trim($message);
        self::$schema_errors[] = $context . ($message !== '' ? ' | ' . $message : '');
        error_log('[ouinpo] exercises schema migration failed: ' . $context . ($message !== '' ? ' | ' . $message : ''));
    }

    private static function is_safe_identifier(string $identifier): bool {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $identifier);
    }

    private static function all_safe_identifiers(array $identifiers): bool {
        if (empty($identifiers)) {
            return false;
        }

        foreach ($identifiers as $identifier) {
            if (!is_string($identifier) || !self::is_safe_identifier($identifier)) {
                return false;
            }
        }

        return true;
    }

    private static function run_schema_query(string $sql, string $context): bool {
        global $wpdb;

        $wpdb->last_error = '';
        $result = $wpdb->query($sql);
        if ($result === false || !empty($wpdb->last_error)) {
            self::record_schema_error($context, (string) $wpdb->last_error);
            return false;
        }

        return true;
    }

    private static function table_exists(string $table): bool {
        global $wpdb;

        if (!self::is_safe_identifier($table)) {
            self::record_schema_error('invalid table identifier: ' . $table);
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    private static function column_exists(string $table, string $column): bool {
        global $wpdb;

        if (!self::is_safe_identifier($table) || !self::is_safe_identifier($column)) {
            self::record_schema_error('invalid column lookup: ' . $table . '.' . $column);
            return false;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = %s
                AND column_name = %s",
            $table,
            $column
        ));

        return (int) $exists > 0;
    }

    private static function index_exists(string $table, string $index): bool {
        global $wpdb;

        if (!self::is_safe_identifier($table) || !self::is_safe_identifier($index)) {
            self::record_schema_error('invalid index lookup: ' . $table . '.' . $index);
            return false;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
               FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = %s
                AND index_name = %s",
            $table,
            $index
        ));

        return (int) $exists > 0;
    }

    private static function add_column_if_missing(string $table, string $column, string $definition): bool {
        if (!self::is_safe_identifier($table) || !self::is_safe_identifier($column) || strpos($definition, ';') !== false) {
            self::record_schema_error('invalid column definition: ' . $table . '.' . $column);
            return false;
        }

        if (!self::table_exists($table)) {
            self::record_schema_error('table missing before column check: ' . $table);
            return false;
        }

        if (self::column_exists($table, $column)) {
            return true;
        }

        return self::run_schema_query("ALTER TABLE {$table} ADD COLUMN {$definition}", 'add column ' . $table . '.' . $column);
    }

    private static function add_primary_key_if_missing(string $table, array $columns): bool {
        if (!self::is_safe_identifier($table) || !self::all_safe_identifiers($columns)) {
            self::record_schema_error('invalid primary key definition: ' . $table);
            return false;
        }

        if (!self::table_exists($table)) {
            self::record_schema_error('table missing before primary key check: ' . $table);
            return false;
        }

        if (self::index_exists($table, 'PRIMARY')) {
            return true;
        }

        return self::run_schema_query(
            "ALTER TABLE {$table} ADD PRIMARY KEY  (" . implode(', ', $columns) . ')',
            'add primary key ' . $table
        );
    }

    private static function add_index_if_missing(string $table, string $index, array $columns): bool {
        if (!self::is_safe_identifier($table) || !self::is_safe_identifier($index) || !self::all_safe_identifiers($columns)) {
            self::record_schema_error('invalid index definition: ' . $table . '.' . $index);
            return false;
        }

        if (!self::table_exists($table)) {
            self::record_schema_error('table missing before index check: ' . $table);
            return false;
        }

        if (self::index_exists($table, $index)) {
            return true;
        }

        return self::run_schema_query(
            "ALTER TABLE {$table} ADD KEY {$index} (" . implode(', ', $columns) . ')',
            'add index ' . $table . '.' . $index
        );
    }

    private static function ensure_assessment_results_schema(string $p, string $charset_innodb): bool {
        $table = $p . 'assessment_results';
        if (!self::is_safe_identifier($table)) {
            self::record_schema_error('invalid assessment_results table identifier: ' . $table);
            return false;
        }

        $ok = self::run_schema_query(
            "CREATE TABLE IF NOT EXISTS {$table} (
                assessment_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                competency_id BIGINT UNSIGNED NOT NULL,
                observed_status ENUM('not_acquired','in_progress','consolidating','acquired') NOT NULL DEFAULT 'not_acquired',
                note TEXT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by BIGINT UNSIGNED NULL,
                PRIMARY KEY  (assessment_id, user_id, competency_id),
                KEY user_id (user_id),
                KEY competency_id (competency_id),
                KEY observed_status (observed_status)
            ) {$charset_innodb}",
            'create table ' . $table
        );

        $columns = [
            'assessment_id' => 'assessment_id BIGINT UNSIGNED NOT NULL',
            'user_id' => 'user_id BIGINT UNSIGNED NOT NULL',
            'competency_id' => 'competency_id BIGINT UNSIGNED NOT NULL',
            'observed_status' => "observed_status ENUM('not_acquired','in_progress','consolidating','acquired') NOT NULL DEFAULT 'not_acquired'",
            'note' => 'note TEXT NULL',
            'updated_at' => 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'updated_by' => 'updated_by BIGINT UNSIGNED NULL',
        ];

        foreach ($columns as $column => $definition) {
            $ok = self::add_column_if_missing($table, $column, $definition) && $ok;
        }

        $ok = self::add_primary_key_if_missing($table, ['assessment_id', 'user_id', 'competency_id']) && $ok;
        $ok = self::add_index_if_missing($table, 'user_id', ['user_id']) && $ok;
        $ok = self::add_index_if_missing($table, 'competency_id', ['competency_id']) && $ok;
        $ok = self::add_index_if_missing($table, 'observed_status', ['observed_status']) && $ok;

        return $ok;
    }

    private static function ensure_assessment_attendance_schema(string $p, string $charset_innodb): bool {
        $table = $p . 'assessment_attendance';
        if (!self::is_safe_identifier($table)) {
            self::record_schema_error('invalid assessment_attendance table identifier: ' . $table);
            return false;
        }

        $ok = self::run_schema_query(
            "CREATE TABLE IF NOT EXISTS {$table} (
                assessment_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                is_absent TINYINT(1) NOT NULL DEFAULT 0,
                note TEXT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by BIGINT UNSIGNED NULL,
                PRIMARY KEY  (assessment_id, user_id),
                KEY user_id (user_id),
                KEY is_absent (is_absent)
            ) {$charset_innodb}",
            'create table ' . $table
        );

        $columns = [
            'assessment_id' => 'assessment_id BIGINT UNSIGNED NOT NULL',
            'user_id' => 'user_id BIGINT UNSIGNED NOT NULL',
            'is_absent' => 'is_absent TINYINT(1) NOT NULL DEFAULT 0',
            'note' => 'note TEXT NULL',
            'updated_at' => 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'updated_by' => 'updated_by BIGINT UNSIGNED NULL',
        ];

        foreach ($columns as $column => $definition) {
            $ok = self::add_column_if_missing($table, $column, $definition) && $ok;
        }

        $ok = self::add_primary_key_if_missing($table, ['assessment_id', 'user_id']) && $ok;
        $ok = self::add_index_if_missing($table, 'user_id', ['user_id']) && $ok;
        $ok = self::add_index_if_missing($table, 'is_absent', ['is_absent']) && $ok;

        return $ok;
    }

    private static function ensure_written_question_attempt_columns(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_written_question_status';
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return;
        }

        $has_attempt_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
               AND COLUMN_NAME = 'attempt_count'",
            DB_NAME,
            $table
        ));

        if ($has_attempt_count <= 0) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status");
        }

        $has_last_attempt = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
               AND COLUMN_NAME = 'last_attempt_at'",
            DB_NAME,
            $table
        ));

        if ($has_last_attempt <= 0) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN last_attempt_at DATETIME NULL AFTER attempt_count");
        }
    }

    

    private static function add_unique_if_missing(string $table, string $name, string $sql): void {

        global $wpdb;

    

        $exists = $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*)

               FROM information_schema.statistics

              WHERE table_schema = DATABASE()

                AND table_name = %s

                AND index_name = %s",

            $table,

            $name

        ));

    

        if ((int) $exists > 0) {

            return;

        }

    

        $wpdb->query($sql);

    

        if (!empty($wpdb->last_error)) {

            error_log('[ouinpo] ensure_constraints unique failed: ' . $name . ' | ' . $wpdb->last_error);

        }

    }

    

    private static function add_fk_if_missing(string $table, string $name, string $sql): void {

        global $wpdb;

    

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

            error_log('[ouinpo] ensure_constraints fk failed: ' . $name . ' | ' . $wpdb->last_error);

        }

    }    

    

}
