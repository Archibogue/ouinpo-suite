<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

class LevelsSchool {
  public static function init(){}

  // retourne une liste d'IDs de niveaux scolaires autorisés pour l'élève
    public static function effective_for_user(int $user_id, ?int $year_id): array {
      global $wpdb;
      $p = $wpdb->prefix.'ouin_exo_';
    
      $expand = function(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    
        $out = [];
    
        foreach ($ids as $id) {
          if ($id === 1) {
            $out[] = 1; // Seconde
          } elseif ($id === 2) {
            $out[] = 1; // Seconde
            $out[] = 2; // Première
          } elseif ($id === 3) {
            $out[] = 1; // Seconde
            $out[] = 2; // Première
            $out[] = 3; // Terminale
          }
        }
    
        return array_values(array_unique($out));
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
