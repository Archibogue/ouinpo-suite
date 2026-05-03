<?php
namespace Ouinpo\Flashcards;

defined('ABSPATH') || exit;

final class ModuleInstaller
{
    public const DB_VERSION = '1.0.1';
    public const OPTION_KEY = 'ouinpo_flashcards_db_version';

    public static function activate(): void
    {
        self::maybe_upgrade();
    }

    public static function maybe_upgrade(): void
    {
        $current = get_option(self::OPTION_KEY);
        if (version_compare((string) $current, self::DB_VERSION, '>=')) {
            return;
        }

        self::upgrade_schema();
        update_option(self::OPTION_KEY, self::DB_VERSION, false);
    }

    public static function upgrade_schema(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $charset_innodb = "ENGINE=InnoDB " . $charset;

        $p = $wpdb->prefix . 'ouin_fc_';
        $px = $wpdb->prefix . 'ouin_exo_';

        $sqlDecks = "CREATE TABLE {$p}decks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(200) NOT NULL,
            description TEXT NULL,
            track ENUM('SNT','NSI') NOT NULL DEFAULT 'NSI',
            level ENUM('Seconde','Première','Terminale','Transversal') NOT NULL DEFAULT 'Première',
            source_post_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_slug (slug),
            KEY idx_track_level (track, level),
            KEY idx_source_post (source_post_id)
        ) {$charset_innodb};";

        $sqlCards = "CREATE TABLE {$p}cards (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            deck_id BIGINT UNSIGNED NOT NULL,
            card_type ENUM('definition','distinction','repere','syntaxe','vocabulaire') NOT NULL DEFAULT 'definition',
            front_html MEDIUMTEXT NOT NULL,
            back_html MEDIUMTEXT NOT NULL,
            note_teacher TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_deck (deck_id),
            KEY idx_deck_active (deck_id, is_active)
        ) {$charset_innodb};";

        $sqlCardCompetency = "CREATE TABLE {$p}card_competency (
            card_id BIGINT UNSIGNED NOT NULL,
            competency_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (card_id, competency_id),
            KEY idx_competency (competency_id)
        ) {$charset_innodb};";

        $sqlUserCards = "CREATE TABLE {$p}user_cards (
            user_id BIGINT UNSIGNED NOT NULL,
            card_id BIGINT UNSIGNED NOT NULL,
            status ENUM('new','learning','review','mastered','suspended') NOT NULL DEFAULT 'new',
            box TINYINT UNSIGNED NOT NULL DEFAULT 1,
            success_streak INT UNSIGNED NOT NULL DEFAULT 0,
            lapse_count INT UNSIGNED NOT NULL DEFAULT 0,
            seen_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_review_at DATETIME NULL,
            next_review_at DATETIME NULL,
            last_grade ENUM('again','hard','good') NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (user_id, card_id),
            KEY idx_user_due (user_id, next_review_at),
            KEY idx_card (card_id)
        ) {$charset_innodb};";

        $sqlReviews = "CREATE TABLE {$p}reviews (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            card_id BIGINT UNSIGNED NOT NULL,
            grade ENUM('again','hard','good') NOT NULL,
            old_box TINYINT UNSIGNED NOT NULL,
            new_box TINYINT UNSIGNED NOT NULL,
            reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_date (user_id, reviewed_at),
            KEY idx_card_date (card_id, reviewed_at)
        ) {$charset_innodb};";

        dbDelta($sqlDecks);
        dbDelta($sqlCards);
        dbDelta($sqlCardCompetency);
        dbDelta($sqlUserCards);
        dbDelta($sqlReviews);

        foreach ([
            $p . 'decks',
            $p . 'cards',
            $p . 'card_competency',
            $p . 'user_cards',
            $p . 'reviews',
        ] as $table) {
            self::forceInnoDB($table);
        }

        self::maybeAddFk(
            $p . 'cards',
            'fk_ouin_fc_cards_deck',
            "ALTER TABLE {$p}cards
             ADD CONSTRAINT fk_ouin_fc_cards_deck
             FOREIGN KEY (deck_id) REFERENCES {$p}decks(id)
             ON DELETE CASCADE"
        );

        self::maybeAddFk(
            $p . 'card_competency',
            'fk_ouin_fc_card_comp_card',
            "ALTER TABLE {$p}card_competency
             ADD CONSTRAINT fk_ouin_fc_card_comp_card
             FOREIGN KEY (card_id) REFERENCES {$p}cards(id)
             ON DELETE CASCADE"
        );

        if (self::tableExists($px . 'competencies')) {
            self::maybeAddFk(
                $p . 'card_competency',
                'fk_ouin_fc_card_comp_comp',
                "ALTER TABLE {$p}card_competency
                 ADD CONSTRAINT fk_ouin_fc_card_comp_comp
                 FOREIGN KEY (competency_id) REFERENCES {$px}competencies(id)
                 ON DELETE CASCADE"
            );
        }

        self::maybeAddFk(
            $p . 'user_cards',
            'fk_ouin_fc_user_cards_card',
            "ALTER TABLE {$p}user_cards
             ADD CONSTRAINT fk_ouin_fc_user_cards_card
             FOREIGN KEY (card_id) REFERENCES {$p}cards(id)
             ON DELETE CASCADE"
        );

        self::maybeAddFk(
            $p . 'reviews',
            'fk_ouin_fc_reviews_card',
            "ALTER TABLE {$p}reviews
             ADD CONSTRAINT fk_ouin_fc_reviews_card
             FOREIGN KEY (card_id) REFERENCES {$p}cards(id)
             ON DELETE CASCADE"
        );
    }

    private static function tableExists(string $table): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    private static function forceInnoDB(string $table): void
    {
        global $wpdb;

        if (!self::tableExists($table)) {
            return;
        }

        $wpdb->query("ALTER TABLE {$table} ENGINE=InnoDB");

        if (!empty($wpdb->last_error)) {
            error_log('[ouinpo flashcards] force InnoDB failed for ' . $table . ' | ' . $wpdb->last_error);
        }
    }

    private static function maybeAddFk(string $table, string $constraint, string $sql): void
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
            $table,
            $constraint
        ));

        if ($exists) {
            return;
        }

        try {
            $wpdb->query($sql);
        } catch (\Throwable $e) {
            // Silence: on évite de bloquer l'activation pour une FK récalcitrante.
        }
    }
}
