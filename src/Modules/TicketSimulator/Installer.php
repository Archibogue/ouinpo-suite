<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class Installer
{
    public const VERSION = '3';
    public static function maybeUpgrade(): void
    {
        if (get_option('ouinpo_ticket_schema_version') !== self::VERSION) { self::install(); }
    }
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $p = $wpdb->prefix . 'ouinpo_ticket_';
        $charset = $wpdb->get_charset_collate();
        $definitions = [
            'scenarios' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                owner_id bigint unsigned NOT NULL,
                title varchar(200) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'draft',
                revision int unsigned NOT NULL DEFAULT 1,
                definition longtext NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY owner_status (owner_id,status)",
            'assignments' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                scenario_id bigint unsigned NOT NULL,
                target_type varchar(20) NOT NULL,
                target_id varchar(100) NOT NULL,
                created_by bigint unsigned NOT NULL,
                active tinyint NOT NULL DEFAULT 1,
                activity_key varchar(40) NOT NULL DEFAULT '',
                settings longtext NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY target_activity (scenario_id,target_type,target_id,activity_key)",
            'attempts' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                scenario_id bigint unsigned NOT NULL,
                assignment_id bigint unsigned NOT NULL,
                student_id bigint unsigned NOT NULL,
                teacher_id bigint unsigned NOT NULL,
                snapshot longtext NOT NULL,
                assessment longtext NULL,
                drafts longtext NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                revision int unsigned NOT NULL DEFAULT 0,
                started_at datetime NOT NULL,
                ended_at datetime NULL,
                PRIMARY KEY  (id),
                KEY student_status (student_id,status),
                KEY teacher (teacher_id)",
            'attempt_tickets' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                attempt_id bigint unsigned NOT NULL,
                ticket_key varchar(80) NOT NULL,
                state longtext NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY attempt_ticket (attempt_id,ticket_key)",
            'events' => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                attempt_id bigint unsigned NOT NULL,
                ticket_key varchar(80) NOT NULL,
                student_id bigint unsigned NOT NULL,
                event_type varchar(40) NOT NULL,
                payload longtext NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY timeline (attempt_id,id)"
        ];
        foreach ($definitions as $suffix => $definition) {
            dbDelta("CREATE TABLE {$p}{$suffix} ($definition) ENGINE=InnoDB $charset;");
        }
        // Replace the old unique key only after the new key exists; existing rows keep activity_key=''.
        if ($wpdb->get_row("SHOW INDEX FROM {$p}assignments WHERE Key_name='target_activity'")) {
            if ($wpdb->get_row("SHOW INDEX FROM {$p}assignments WHERE Key_name='target'")) {
                if ($wpdb->query("ALTER TABLE {$p}assignments DROP INDEX target") === false) { return; }
            }
        } else { return; }
        foreach (['assignments'=>['settings','activity_key'],'attempts'=>['assessment','drafts']] as $table=>$columns) {
            foreach ($columns as $column) { if (!$wpdb->get_row("SHOW COLUMNS FROM {$p}{$table} LIKE '$column'")) { return; } }
        }
        // Version is only marked installed once all tables exist.
        foreach (array_keys($definitions) as $suffix) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($p . $suffix))) !== $p . $suffix) { return; }
        }
        Capabilities::install();
        update_option('ouinpo_ticket_schema_version', self::VERSION, false);
    }
}
