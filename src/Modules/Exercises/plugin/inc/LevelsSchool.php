<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

class LevelsSchool {
  public static function init(){}

  // retourne une liste d'IDs de niveaux scolaires autorisés pour l'élève
    public static function effective_for_user(int $user_id, ?int $year_id): array {
      global $wpdb;
      $p = $wpdb->prefix.'ouin_exo_';
    
      $expand = function(array $ids) use ($wpdb, $p): array {

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids) {
          return [];
        }

        $cumulative = (bool) apply_filters(
          'ouinpo_exercises_cumulative_school_levels',
          (bool) get_option('ouinpo_exercises_cumulative_school_levels', false)
        );

        if (!$cumulative) {
          return $ids;
        }

        $levels_table = $p . 'school_levels';
        $cols = (array) $wpdb->get_col("SHOW COLUMNS FROM {$levels_table}", 0);

        if (!in_array('sort_order', $cols, true)) {
          return $ids;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $max_order = (int) $wpdb->get_var($wpdb->prepare(
          "SELECT MAX(sort_order) FROM {$levels_table} WHERE id IN ({$placeholders})",
          $ids
        ));

        if ($max_order <= 0) {
          return $ids;
        }

        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
          "SELECT id FROM {$levels_table} WHERE sort_order <= %d ORDER BY sort_order ASC, id ASC",
          $max_order
        )));

      };
    
      // overrides individuels
      $over = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT gm.school_level_id_override
        FROM {$p}group_members gm
        JOIN {$p}groups g ON g.id = gm.group_id
        WHERE gm.user_id = %d
          AND (%d IS NULL OR g.year_id = %d)
          AND gm.school_level_id_override IS NOT NULL",
        $user_id, $year_id, $year_id
      ));
    
      if ($over) {
        return $expand($over);
      }
    
      // niveaux des groupes
      $levels = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT g.school_level_id
        FROM {$p}group_members gm
        JOIN {$p}groups g ON g.id = gm.group_id
        WHERE gm.user_id = %d
          AND (%d IS NULL OR g.year_id = %d)
          AND g.school_level_id IS NOT NULL",
        $user_id, $year_id, $year_id
      ));
    
      return $expand($levels);
    }
    
}
