<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

class Years {
  public static function init(){}

  private static function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'ouin_exo_academic_years';
  }

  public static function active_row(): ?object {
    global $wpdb;

    $table = self::table();
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) {
      return null;
    }

    $row = $wpdb->get_row("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY starts_on DESC, id DESC LIMIT 1");
    if (!$row) {
      return null;
    }

    $activeId = (int) $row->id;
    if ((int) get_option('ouin_exo_active_year_id') !== $activeId) {
      update_option('ouin_exo_active_year_id', $activeId);
    }

    return $row;
  }

  public static function active_id(): ?int {
    $row = self::active_row();
    return $row ? (int) $row->id : null;
  }

  public static function set_active(int $yearId): bool {
    global $wpdb;

    if ($yearId <= 0) {
      return false;
    }

    $table = self::table();
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) {
      return false;
    }

    $found = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $yearId));
    if ($found < 1) {
      return false;
    }

    $wpdb->query("UPDATE {$table} SET is_active = 0 WHERE is_active <> 0");
    $result = $wpdb->update($table, ['is_active' => 1], ['id' => $yearId], ['%d'], ['%d']);

    if ($result === false) {
      return false;
    }

    update_option('ouin_exo_active_year_id', $yearId);
    return true;
  }
}