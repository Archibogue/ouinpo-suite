<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class TeachingState {
    public const STATE_NOT_STARTED = 'not_started';
    public const STATE_SEEN = 'seen';

    public static function allowed_states(): array {
        return [
            self::STATE_NOT_STARTED,
            self::STATE_SEEN,
        ];
    }

    public static function normalize_state($state): string {
        $state = sanitize_key((string) $state);
        return in_array($state, self::allowed_states(), true)
            ? $state
            : self::STATE_NOT_STARTED;
    }

    public static function get_state(int $year_id, int $group_id, int $competency_id): string {
        global $wpdb;

        if (!$year_id || !$group_id || !$competency_id) {
            return self::STATE_NOT_STARTED;
        }

        $table = $wpdb->prefix . 'ouin_exo_competency_teaching';

        $state = $wpdb->get_var($wpdb->prepare(
            "SELECT teaching_state
               FROM {$table}
              WHERE year_id = %d
                AND group_id = %d
                AND competency_id = %d
              LIMIT 1",
            $year_id,
            $group_id,
            $competency_id
        ));

        return $state ? self::normalize_state($state) : self::STATE_NOT_STARTED;
    }

    public static function is_seen(int $year_id, int $group_id, int $competency_id): bool {
        return self::get_state($year_id, $group_id, $competency_id) === self::STATE_SEEN;
    }

    public static function set_state(
        int $year_id,
        int $group_id,
        int $competency_id,
        string $state,
        ?int $updated_by = null
    ): bool {
        global $wpdb;

        if (!$year_id || !$group_id || !$competency_id) {
            return false;
        }

        $state = self::normalize_state($state);
        $updated_by = $updated_by ?: get_current_user_id();
        $table = $wpdb->prefix . 'ouin_exo_competency_teaching';
        $now = current_time('mysql');

        if ($state === self::STATE_NOT_STARTED) {
            $wpdb->delete(
                $table,
                [
                    'year_id' => $year_id,
                    'group_id' => $group_id,
                    'competency_id' => $competency_id,
                ],
                ['%d', '%d', '%d']
            );

            return true;
        }

        $exists = $wpdb->get_row($wpdb->prepare(
            "SELECT year_id, group_id, competency_id, first_seen_at
               FROM {$table}
              WHERE year_id = %d
                AND group_id = %d
                AND competency_id = %d
              LIMIT 1",
            $year_id,
            $group_id,
            $competency_id
        ), ARRAY_A);

        if ($exists) {
            $wpdb->update(
                $table,
                [
                    'teaching_state'   => self::STATE_SEEN,
                    'state_changed_at' => $now,
                    'updated_at'       => $now,
                    'updated_by'       => $updated_by,
                ],
                [
                    'year_id'       => $year_id,
                    'group_id'      => $group_id,
                    'competency_id' => $competency_id,
                ],
                ['%s', '%s', '%s', '%d'],
                ['%d', '%d', '%d']
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'year_id'          => $year_id,
                    'group_id'         => $group_id,
                    'competency_id'    => $competency_id,
                    'teaching_state'   => self::STATE_SEEN,
                    'first_seen_at'    => $now,
                    'state_changed_at' => $now,
                    'updated_at'       => $now,
                    'updated_by'       => $updated_by,
                ],
                ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d']
            );
        }

        self::seed_user_competency_for_group($year_id, $group_id, $competency_id, $updated_by);

        return true;
    }

    public static function set_seen(
        int $year_id,
        int $group_id,
        int $competency_id,
        ?int $updated_by = null
    ): bool {
        return self::set_state($year_id, $group_id, $competency_id, self::STATE_SEEN, $updated_by);
    }

    public static function set_not_started(
        int $year_id,
        int $group_id,
        int $competency_id
    ): bool {
        return self::set_state($year_id, $group_id, $competency_id, self::STATE_NOT_STARTED, get_current_user_id());
    }

    public static function seed_user_competency_for_group(
        int $year_id,
        int $group_id,
        int $competency_id,
        ?int $updated_by = null
    ): int {
        global $wpdb;

        if (!$year_id || !$group_id || !$competency_id) {
            return 0;
        }

        $updated_by = $updated_by ?: get_current_user_id();

        $tblUC = $wpdb->prefix . 'ouin_exo_user_competencies';
        $tblGM = $wpdb->prefix . 'ouin_exo_group_members';

        $sql = "
            INSERT IGNORE INTO {$tblUC}
                (user_id, competency_id, year_id, group_id, status, updated_at, updated_by, source)
            SELECT
                gm.user_id,
                %d,
                %d,
                gm.group_id,
                'not_acquired',
                %s,
                %d,
                'import'
            FROM {$tblGM} gm
            WHERE gm.group_id = %d
              AND gm.role = 'student'
        ";

        $result = $wpdb->query($wpdb->prepare(
            $sql,
            $competency_id,
            $year_id,
            current_time('mysql'),
            $updated_by,
            $group_id
        ));

        return is_numeric($result) ? (int) $result : 0;
    }

    public static function get_seen_competency_ids(int $year_id, int $group_id): array {
        global $wpdb;

        if (!$year_id || !$group_id) {
            return [];
        }

        $table = $wpdb->prefix . 'ouin_exo_competency_teaching';

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT competency_id
               FROM {$table}
              WHERE year_id = %d
                AND group_id = %d
                AND teaching_state = %s
              ORDER BY competency_id ASC",
            $year_id,
            $group_id,
            self::STATE_SEEN
        ));

        return array_map('intval', $ids ?: []);
    }

    public static function get_map_for_group(int $year_id, int $group_id): array {
        global $wpdb;

        if (!$year_id || !$group_id) {
            return [];
        }

        $table = $wpdb->prefix . 'ouin_exo_competency_teaching';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT competency_id, teaching_state, first_seen_at, state_changed_at, updated_at, updated_by
               FROM {$table}
              WHERE year_id = %d
                AND group_id = %d",
            $year_id,
            $group_id
        ), ARRAY_A);

        $out = [];
        foreach ($rows as $row) {
            $cid = (int) $row['competency_id'];
            $out[$cid] = [
                'teaching_state'   => self::normalize_state($row['teaching_state'] ?? self::STATE_NOT_STARTED),
                'first_seen_at'    => $row['first_seen_at'] ?? null,
                'state_changed_at' => $row['state_changed_at'] ?? null,
                'updated_at'       => $row['updated_at'] ?? null,
                'updated_by'       => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            ];
        }

        return $out;
    }
}