<?php

namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class CompetencyLevels {

    public static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    public static function relation_table(): string {
        return self::table('competency_school_level');
    }

    public static function relation_exists(): bool {
        global $wpdb;
        $table = self::relation_table();
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    public static function level_filter_sql(string $competency_alias = 'c'): string {
        $table = self::relation_table();
        return "EXISTS (
            SELECT 1
              FROM {$table} csl_filter
             WHERE csl_filter.competency_id = {$competency_alias}.id
               AND csl_filter.school_level_id = %d
        )";
    }

    public static function group_school_level_id(int $group_id, int $year_id): int {
        if ($group_id <= 0 || $year_id <= 0) {
            return 0;
        }

        global $wpdb;
        $groups = self::table('groups');

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT school_level_id
               FROM {$groups}
              WHERE id = %d
                AND year_id = %d
              LIMIT 1",
            $group_id,
            $year_id
        ));
    }

    public static function ids_for_competency(int $competency_id): array {
        if ($competency_id <= 0 || !self::relation_exists()) {
            return [];
        }

        global $wpdb;
        $table = self::relation_table();

        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT school_level_id
               FROM {$table}
              WHERE competency_id = %d
              ORDER BY school_level_id ASC",
            $competency_id
        )));
    }

    public static function sync_competency_levels(int $competency_id, array $level_ids): void {
        if ($competency_id <= 0 || !self::relation_exists()) {
            return;
        }

        global $wpdb;
        $table = self::relation_table();
        $levels = self::table('school_levels');

        $clean_ids = [];
        foreach ($level_ids as $level_id) {
            $level_id = (int) $level_id;
            if ($level_id > 0) {
                $clean_ids[$level_id] = true;
            }
        }

        $valid_ids = [];
        if ($clean_ids) {
            $ids = array_keys($clean_ids);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $valid_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$levels} WHERE id IN ({$placeholders})",
                $ids
            )));
        }

        $wpdb->delete($table, ['competency_id' => $competency_id], ['%d']);

        foreach ($valid_ids as $level_id) {
            $wpdb->insert(
                $table,
                [
                    'competency_id'   => $competency_id,
                    'school_level_id' => $level_id,
                ],
                ['%d', '%d']
            );
        }
    }

    public static function migrate_legacy_links(): void {
        if (!self::relation_exists()) {
            return;
        }

        global $wpdb;
        $competencies = self::table('competencies');
        $levels = self::table('school_levels');
        $table = self::relation_table();

        $wpdb->query("
            INSERT IGNORE INTO {$table} (competency_id, school_level_id)
            SELECT c.id, sl.id
              FROM {$competencies} c
              JOIN {$levels} sl
                ON sl.label = c.level
                OR (c.level = 'Seconde' AND sl.slug = 'seconde')
                OR (c.level = 'Première' AND sl.slug = 'premiere')
                OR (c.level = 'Terminale' AND sl.slug = 'terminale')
             WHERE c.level <> 'Transversal'
        ");

        $wpdb->query("
            INSERT IGNORE INTO {$table} (competency_id, school_level_id)
            SELECT c.id, sl.id
              FROM {$competencies} c
              CROSS JOIN {$levels} sl
             WHERE c.level = 'Transversal'
        ");
    }
}
