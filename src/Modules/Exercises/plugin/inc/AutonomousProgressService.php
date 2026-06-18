<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class AutonomousProgressService
{
    public static function reset_user_progress(int $user_id): int
    {
        return self::delete_user_training_data($user_id);
    }

    public static function delete_user_training_data(int $user_id): int
    {
        global $wpdb;

        if ($user_id <= 0 || !class_exists(PathsService::class)) {
            return 0;
        }

        $deleted = 0;
        $exercise_ids = [];
        foreach (PathsService::list_user_training_paths($user_id) as $path) {
            foreach ((array) ($path['exercise_ids'] ?? []) as $exercise_id) {
                $exercise_id = (int) $exercise_id;
                if ($exercise_id > 0) {
                    $exercise_ids[$exercise_id] = true;
                }
            }
        }

        if (!empty($exercise_ids)) {
            $ids = array_keys($exercise_ids);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $args = array_merge([$user_id], $ids);
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}ouin_exo_user_status
                 WHERE user_id = %d
                   AND exercise_id IN ({$placeholders})",
                ...$args
            ));
            $deleted += max(0, (int) $result);
        }

        $badge_result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ouin_exo_user_badges
             WHERE user_id = %d
               AND source = 'path'",
            $user_id
        ));

        $deleted += max(0, (int) $badge_result);

        return $deleted;
    }
}
