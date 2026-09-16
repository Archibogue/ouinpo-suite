<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Display sequence only. API identifiers are never recycled. */
final class AttemptNumber
{
    public const OPTION = 'ouinpo_ticket_attempt_number_offset';
    public static function offset(): int
    {
        global $wpdb;
        // The isolated preview has no WordPress options table.
        if (!isset($wpdb->options)) { return 0; }
        // Read directly: this non-autoloaded metadata is updated in the cleanup
        // transaction and must not use WordPress's non-transactional option cache.
        return (int) $wpdb->get_var($wpdb->prepare('SELECT option_value FROM ' . $wpdb->options . ' WHERE option_name=%s', self::OPTION));
    }
    public static function display(int $id): int { return max(1, $id - self::offset()); }
    public static function reset(): void
    {
        global $wpdb;
        $table = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s', ScenarioRepository::table('attempts')), ARRAY_A);
        if (!$table || !isset($table['Auto_increment'])) { throw new \RuntimeException('Impossible de réinitialiser la numérotation.', 500); }
        $offset = max(0, (int) $table['Auto_increment'] - 1);
        ScenarioRepository::check($wpdb->query($wpdb->prepare(
            'INSERT INTO ' . $wpdb->options . " (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)",
            self::OPTION, (string) $offset
        )));
    }
}
