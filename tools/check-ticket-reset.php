<?php
require __DIR__ . '/check-ticket-simulator.php';

use Ouinpo\Suite\Modules\TicketSimulator\ScenarioAttempt;

$current = 11;
$wpdb = new FakeTicketDb();
$second = $ticket;
$second['id'] = 'OTHER';
$snapshot = $scenario;
$snapshot['tickets'] = [$ticket, $second];
$dirty = $state;
$dirty['code_edits'] = ['code' => 'modified'];
$dirty['pending'] = ['text' => 'Pending reply'];
$other = $dirty;
$wpdb->attempt = array_merge($attempt, ['snapshot'=>json_encode($snapshot), 'status'=>'completed', 'ended_at'=>'2026-09-17 10:00:00', 'revision'=>8]);
$wpdb->states = [
    ['ticket_key'=>$ticket['id'], 'state'=>json_encode($dirty)],
    ['ticket_key'=>'OTHER', 'state'=>json_encode($other)],
];
$before = $wpdb->states;
$route = $routes['POST /attempts/(?P<id>\d+)/tickets/(?P<ticket>[a-zA-Z0-9_-]+)/reset']['callback'];
$params = ['id'=>1, 'ticket'=>$ticket['id'], 'revision'=>8, 'confirm_reset'=>true];
$response = $route(new WP_REST_Request(array_merge($params, ['confirm_reset'=>false])));
check($response instanceof WP_Error && $response->data['status'] === 400, 'Ticket reset requires explicit confirmation');
$current = 12;
check(isDenied($route(new WP_REST_Request($params))), 'Another student cannot reset a ticket');
$current = 20;
check(isDenied($route(new WP_REST_Request($params))), 'Teacher observation remains read-only');
$current = 11;
$response = $route(new WP_REST_Request(array_merge($params, ['revision'=>7])));
check($response instanceof WP_Error && $response->data['status'] === 409, 'Stale reset revision rejected');
$wpdb->attempt['status'] = 'archived';
check(isDenied($route(new WP_REST_Request($params))), 'Archived ticket cannot be reset');
$wpdb->attempt['status'] = 'completed';
check($wpdb->states === $before, 'Rejected resets preserve both tickets');
$wpdb->failEvents = true;
check($route(new WP_REST_Request($params)) instanceof WP_Error, 'Journal failure aborts reset');
check($wpdb->states === $before && $wpdb->attempt['revision'] === 8, 'Failed reset rolls back state and revision');
$wpdb->failEvents = false;
check($route(new WP_REST_Request($params)) instanceof WP_REST_Response, 'Owner can reset completed ticket');
check(json_decode($wpdb->states[0]['state'], true) === ScenarioAttempt::initial($ticket), 'Reset restores exact initial snapshot state');
check($wpdb->states[1] === $before[1], 'Other ticket remains unchanged');
check($wpdb->attempt['status'] === 'active' && $wpdb->attempt['ended_at'] === null && $wpdb->attempt['revision'] === 9, 'Reset reopens scenario and advances revision');
echo "Ticket reset checks passed.\n";
