<?php
/** Isolated persistence-contract tests, not a live MySQL integration test. */
declare(strict_types=1);
require __DIR__ . '/check-ticket-simulator.php';

use Ouinpo\Suite\Modules\TicketSimulator\ScenarioRepository;
use Ouinpo\Suite\Modules\TicketSimulator\ScenarioValidator;
use Ouinpo\Suite\Modules\TicketSimulator\ScenarioAttempt;
use Ouinpo\Suite\Modules\TicketSimulator\SimulationEngine;
use Ouinpo\Suite\Modules\TicketSimulator\SimulatedTestEngine;
use Ouinpo\Suite\Modules\TicketSimulator\CodeWorkspace;

final class DeletionDb
{
    public string $prefix = 'custom_';
    public array $tables;
    public ?string $failTable = null;
    private array $backup = [];
    public function __construct(array $definition)
    {
        $this->tables = [
            'scenarios' => [
                ['id'=>1,'owner_id'=>20,'revision'=>1,'definition'=>json_encode($definition)],
                ['id'=>2,'owner_id'=>30,'revision'=>1,'definition'=>json_encode($definition)],
            ],
            'attempts' => [
                ['id'=>11,'scenario_id'=>1,'status'=>'active'],
                ['id'=>12,'scenario_id'=>1,'status'=>'archived'],
                ['id'=>21,'scenario_id'=>2,'status'=>'active'],
            ],
            'assignments' => [['id'=>1,'scenario_id'=>1],['id'=>2,'scenario_id'=>2]],
            'attempt_tickets' => [['attempt_id'=>11],['attempt_id'=>12],['attempt_id'=>21]],
            'events' => [['attempt_id'=>11],['attempt_id'=>12],['attempt_id'=>21]],
        ];
    }
    public function prepare(string $sql, ...$params): string
    {
        foreach ($params as $p) { $sql = preg_replace('/%d/', (string) (int) $p, $sql, 1); }
        return $sql;
    }
    public function get_row(string $sql, $format): ?array
    {
        preg_match('/WHERE id=(\d+)/', $sql, $m);
        foreach ($this->tables['scenarios'] as $row) { if ($row['id'] === (int) $m[1]) { return $row; } }
        return null;
    }
    public function query(string $sql)
    {
        if ($sql === 'START TRANSACTION') { $this->backup = $this->tables; return 0; }
        if ($sql === 'ROLLBACK') { $this->tables = $this->backup; return 0; }
        if ($sql === 'COMMIT' || str_starts_with($sql, 'SELECT')) { return 0; }
        if (!preg_match('/^DELETE FROM custom_ouinpo_ticket_(events|attempt_tickets) WHERE attempt_id IN \(SELECT id FROM custom_ouinpo_ticket_attempts WHERE scenario_id=(\d+)\)$/', $sql, $m)) {
            throw new LogicException('Unexpected SQL: ' . $sql);
        }
        if ($this->failTable === $m[1]) { return false; }
        $ids = array_column(array_filter($this->tables['attempts'], static fn($a) => $a['scenario_id'] === (int) $m[2]), 'id');
        $before = count($this->tables[$m[1]]);
        $this->tables[$m[1]] = array_values(array_filter($this->tables[$m[1]], static fn($a) => !in_array($a['attempt_id'], $ids, true)));
        return $before - count($this->tables[$m[1]]);
    }
    public function delete(string $table, array $where, array $formats)
    {
        $suffix = substr($table, strlen('custom_ouinpo_ticket_'));
        if ($this->failTable === $suffix) { return false; }
        $before = count($this->tables[$suffix]);
        $this->tables[$suffix] = array_values(array_filter($this->tables[$suffix], static fn($row) => (bool) array_diff_assoc($where, $row)));
        return $before - count($this->tables[$suffix]);
    }
}

$wpdb = new DeletionDb($scenario);
$original = $wpdb->tables;
$delete = $routes['DELETE /scenarios/(?P<id>\d+)']['callback'];
$request = new WP_REST_Request(['id'=>1,'revision'=>1,'confirm_delete'=>true]);
$current = 11;
check(isDenied($delete($request)), 'Student cannot delete scenario');
$current = 21;
check(isDenied($delete($request)), 'Unrelated teacher cannot delete scenario');
check($wpdb->tables === $original, 'Denied deletion leaves every table intact');
$current = 20;
$response = $delete(new WP_REST_Request(['id'=>1,'revision'=>1]));
check($response instanceof WP_Error && $response->data['status'] === 400, 'Deletion needs explicit confirmation');
$response = $delete(new WP_REST_Request(['id'=>1,'revision'=>2,'confirm_delete'=>true]));
check($response instanceof WP_Error && $response->data['status'] === 409, 'Stale scenario revision prevents deletion');
foreach (['events','attempt_tickets','attempts','assignments','scenarios'] as $table) {
    $wpdb->failTable = $table;
    $response = $delete($request);
    check($response instanceof WP_Error && $response->data['status'] === 500, 'Failure reported for ' . $table);
    check($wpdb->tables === $original, 'Whole deletion rolled back after failure in ' . $table);
}
$wpdb->failTable = null;
$response = $delete($request);
check($response instanceof WP_REST_Response && $response->data['deleted'], 'Owner can delete confirmed scenario');
check($response->data['counts']['attempts'] === 2, 'Active and archived attempts removed');
foreach ($wpdb->tables as $name => $rows) {
    check(count($rows) === 1, 'Only other scenario data remains in ' . $name);
}
check($wpdb->tables['events'][0]['attempt_id'] === 21, 'Other students/scenarios keep their traces');
check($wpdb->tables['scenarios'][0]['id'] === 2, 'Other scenario preserved');
$response = $delete($request);
check($response instanceof WP_Error && $response->data['status'] === 404, 'Repeated deletion cannot target a different scenario');
$current = 1;
check($delete(new WP_REST_Request(['id'=>2,'revision'=>1,'confirm_delete'=>true])) instanceof WP_REST_Response, 'Administrator can delete another owner scenario');

$template = json_decode(file_get_contents(__DIR__ . '/templates/patadesk/modele-scenario.json'), true, 512, JSON_THROW_ON_ERROR);
check(ScenarioValidator::validate($template) === $template, 'Import template validates');
$engine = new SimulationEngine(new SimulatedTestEngine($template['resources']));
$ticket = $template['tickets'][0]; $state = ScenarioAttempt::initial($ticket);
foreach (['take','diagnose','since','__reply','logs','code','dba','__reply'] as $action) {
    [$state] = $engine->perform($ticket, $state, $action, ['message'=>'Merci de vérifier le schéma.']);
}
$resource = array_column($template['resources'], null, 'id')['code'];
[$state] = CodeWorkspace::save($ticket, $state, $resource, $resource['expected_content']);
[$state] = $engine->perform($ticket, $state, 'verify', []);
[$state] = $engine->perform($ticket, $state, 'resolve', ['cause'=>'Migration','solution'=>'Colonne corrigée','tests'=>'Export','result'=>'128 lignes','message'=>'Export rétabli.']);
[$state] = $engine->perform($ticket, $state, 'close', []);
check($state['status'] === 'closed', 'Import template supports a complete resolved workflow');
echo "\n$checks total checks passed, including deletion and the import template.\n";
