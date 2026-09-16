<?php
declare(strict_types=1);
require __DIR__ . '/check-ticket-simulator.php';

final class CleanupDb
{
    public string $prefix = 'custom_';
    public string $options = 'custom_options';
    public int $offset = 0;
    public int $nextId = 31;
    private int $backupOffset = 0;
    public array $tables = ['scenarios'=>2, 'assignments'=>3, 'attempts'=>5, 'attempt_tickets'=>10, 'events'=>30];
    public array $queries = [];
    public ?string $fail = null;
    private array $backup = [];
    public function prepare($sql, ...$args) {
        foreach ($args as $arg) { $sql = preg_replace('/%s/', "'" . $arg . "'", $sql, 1); }
        return $sql;
    }
    public function get_row($sql, $format) { return ['Auto_increment'=>$this->nextId]; }
    public function get_var($sql) { return $this->offset; }
    public function query(string $sql) {
        $this->queries[] = $sql;
        if ($sql === 'START TRANSACTION') { $this->backup = $this->tables; $this->backupOffset = $this->offset; return 0; }
        if ($sql === 'ROLLBACK') { $this->tables = $this->backup; $this->offset = $this->backupOffset; return 0; }
        if ($sql === 'COMMIT' || str_starts_with($sql, 'SELECT id FROM ')) { return 0; }
        if (str_starts_with($sql, 'INSERT INTO custom_options')) {
            if ($this->fail === 'numbering') { return false; }
            $this->offset = $this->nextId - 1; return 1;
        }
        if (!preg_match('/^DELETE FROM custom_ouinpo_ticket_(events|attempt_tickets|attempts)$/', $sql, $m)) { throw new LogicException($sql); }
        if ($this->fail === $m[1]) { return false; }
        $count = $this->tables[$m[1]]; $this->tables[$m[1]] = 0; return $count;
    }
}
$wpdb = new CleanupDb();
$route = $routes['DELETE /attempts']['callback'];
$request = new WP_REST_Request(['confirm_delete_all'=>true]);
foreach ([11, 20, 21] as $current) {
    check(isDenied($route($request)), 'Global cleanup rejects user ' . $current);
}
check(!$wpdb->queries, 'Denied requests never touch the database');
$current = 1;
check($route(new WP_REST_Request()) instanceof WP_Error, 'Explicit cleanup confirmation required');
check($route(new WP_REST_Request(['confirm_delete_all'=>'true'])) instanceof WP_Error, 'Confirmation must be boolean');
foreach (['events', 'attempt_tickets', 'attempts', 'numbering'] as $fail) {
    $wpdb = new CleanupDb(); $before = $wpdb->tables; $wpdb->fail = $fail;
    check($route($request) instanceof WP_Error, 'Failure reported for ' . $fail);
    check($wpdb->tables === $before, 'All data rolled back after failure in ' . $fail);
}
$wpdb = new CleanupDb();
$result = $route($request);
check($result instanceof WP_REST_Response && $result->data['counts']['attempts'] === 5, 'Cleanup returns deleted attempt count');
check($wpdb->tables === ['scenarios'=>2, 'assignments'=>3, 'attempts'=>0, 'attempt_tickets'=>0, 'events'=>0], 'Only execution data is deleted');
check($wpdb->queries[1] === 'SELECT id FROM custom_ouinpo_ticket_scenarios ORDER BY id FOR UPDATE' && $wpdb->queries[2] === 'SELECT id FROM custom_ouinpo_ticket_attempts ORDER BY id FOR UPDATE', 'Scenario locks precede attempt locks');
check($route($request)->data['counts']['attempts'] === 0, 'Repeated cleanup is harmless');
check(\Ouinpo\Suite\Modules\TicketSimulator\AttemptNumber::display(31) === 1, 'Next displayed attempt starts at one even after a previous empty cleanup');
check(\Ouinpo\Suite\Modules\TicketSimulator\AttemptNumber::display(32) === 2, 'Following attempt has display number two');
check($wpdb->nextId === 31, 'Technical identifiers are never reset');
echo "Total: $checks checks passed.\n";
