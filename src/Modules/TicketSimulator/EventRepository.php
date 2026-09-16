<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class EventRepository
{
    public function add(array $attempt, string $ticket, array $event): void
    {
        global $wpdb;
        ScenarioRepository::check($wpdb->insert(ScenarioRepository::table('events'), [
            'attempt_id' => $attempt['id'], 'ticket_key' => $ticket, 'student_id' => $attempt['student_id'],
            'event_type' => $event['type'], 'payload' => wp_json_encode($event), 'created_at' => current_time('mysql', true),
        ]));
    }
    public function listing(int $attemptId, int $after = 0): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . ScenarioRepository::table('events') . ' WHERE attempt_id=%d AND id>%d ORDER BY id ASC LIMIT 500', $attemptId, $after), ARRAY_A) ?: [];
        foreach ($rows as &$row) { $row['payload'] = json_decode($row['payload'], true); }
        return $rows;
    }
}
