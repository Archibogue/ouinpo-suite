<?php
declare(strict_types=1);
require __DIR__ . '/check-ticket-simulator.php';

final class StudentCleanupDb
{
    public string $prefix = 'custom_';
    public array $attempts = [1=>11, 2=>11, 3=>12];
    public array $events = [1,2,3];
    public array $states = [1,2,3];
    public ?string $fail = null;
    public int $writes = 0;
    private array $backup;
    public function prepare($sql, ...$args) { foreach ($args as $arg) { $sql = preg_replace('/%d/', (string)(int)$arg, $sql, 1); } return $sql; }
    public function query($sql) {
        if ($sql === 'START TRANSACTION') { $this->backup = [$this->attempts,$this->events,$this->states]; return 0; }
        if ($sql === 'ROLLBACK') { [$this->attempts,$this->events,$this->states] = $this->backup; return 0; }
        if ($sql === 'COMMIT' || str_starts_with($sql, 'SELECT id FROM ')) { return 0; }
        if (!preg_match('/^DELETE FROM custom_ouinpo_ticket_(events|attempt_tickets) WHERE attempt_id IN \(SELECT id FROM custom_ouinpo_ticket_attempts WHERE student_id=(\d+)\)$/', $sql, $m)) { throw new LogicException('Unexpected query: ' . $sql); }
        if ($this->fail === $m[1]) { return false; }
        $property = $m[1] === 'events' ? 'events' : 'states';
        $before = count($this->$property);
        $this->$property = array_values(array_filter($this->$property, fn($id) => $this->attempts[$id] !== (int)$m[2]));
        $this->writes++;
        return $before - count($this->$property);
    }
    public function delete($table, $where, $formats) {
        if ($table !== 'custom_ouinpo_ticket_attempts') { throw new LogicException('Forbidden table'); }
        if ($this->fail === 'attempts') { return false; }
        $before = count($this->attempts);
        $this->attempts = array_filter($this->attempts, fn($student) => $student !== $where['student_id']);
        $this->writes++;
        return $before - count($this->attempts);
    }
}
$route = $routes['DELETE /students/(?P<id>\d+)/attempts']['callback'];
$request = new WP_REST_Request(['id'=>11,'confirm_delete_student'=>true]);
$wpdb = new StudentCleanupDb();
foreach ([11,12,20,21] as $current) { check(isDenied($route($request)), 'Student cleanup denied to user ' . $current); }
check($wpdb->writes === 0, 'Unauthorized requests do not write');
$current = 1;
check($route(new WP_REST_Request(['id'=>11])) instanceof WP_Error, 'Specific student confirmation required');
check($route(new WP_REST_Request(['id'=>0,'confirm_delete_student'=>true])) instanceof WP_Error, 'Invalid student identifier rejected');
foreach (['events','attempt_tickets','attempts'] as $table) {
    $wpdb = new StudentCleanupDb(); $wpdb->fail = $table;
    check($route($request) instanceof WP_Error, 'Failure reported for ' . $table);
    check($wpdb->attempts === [1=>11,2=>11,3=>12] && $wpdb->events === [1,2,3] && $wpdb->states === [1,2,3], 'Student cleanup is rolled back on failure in ' . $table);
}
$wpdb = new StudentCleanupDb();
$result = $route($request);
check($result instanceof WP_REST_Response && $result->data['counts']['attempts'] === 2, 'All matching student attempts removed');
check($wpdb->attempts === [3=>12] && $wpdb->events === [3] && $wpdb->states === [3], 'Other student and all their traces preserved');
check($route($request)->data['counts']['attempts'] === 0, 'Repeated student cleanup is harmless');
echo "Total: $checks checks passed including student cleanup.\n";
