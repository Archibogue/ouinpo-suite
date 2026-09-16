<?php
/** Standalone regression tests: php tools/check-ticket-simulator.php */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/src/Core/Autoloader.php';
Ouinpo\Suite\Core\Autoloader::init(dirname(__DIR__) . '/src');

use Ouinpo\Suite\Modules\TicketSimulator\{ScenarioValidator,ScenarioAttempt,SimulationEngine,StudentView,PermissionService,RestController,AttemptRepository,CodeWorkspace,SimulatedTestEngine};
use Ouinpo\Suite\Core\Capabilities as Caps;

$checks = 0;
function check(bool $value, string $label): void { global $checks; $checks++; if (!$value) { throw new RuntimeException('FAIL: ' . $label); } echo "OK: $label\n"; }
function rejects(callable $fn, string $label): void { try { $fn(); } catch (Throwable $e) { check(true, $label); return; } check(false, $label); }
$current = 11; $caps = [11 => [Caps::TICKET_PRACTICE, Caps::TRACK_LEARNING_DATA], 12 => [Caps::TICKET_PRACTICE, Caps::TRACK_LEARNING_DATA], 20 => [Caps::TICKET_MANAGE, Caps::TICKET_OBSERVE], 21 => [Caps::TICKET_OBSERVE], 1 => ['manage_options']];
function get_current_user_id() { global $current; return $current; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function user_can($id, $cap) { global $caps; return in_array($cap, $caps[$id] ?? [], true); }
function current_user_can($cap) { return user_can(get_current_user_id(), $cap); }
function get_user_by($key, $id) { return (object) ['roles' => $id === 11 || $id === 12 ? ['ouinpo_student'] : []]; }
$trackingDisabled = false;
function get_user_meta($id, $key, $single) { global $trackingDisabled; return $trackingDisabled && $key === 'ouinpo_tracking_disabled' ? '1' : ''; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid' && $action === 'wp_rest'; }
function wp_json_encode($data) { return json_encode($data); }
function sanitize_textarea_field($s) { return strip_tags($s); }
function sanitize_text_field($s) { return strip_tags($s); }
function current_time($format, $gmt = false) { return '2026-09-15 10:00:00'; }
function register_rest_route($namespace, $path, $args) { global $routes; $routes[$args['methods'] . ' ' . $path] = $args; }
function add_action($hook, $callback) { global $hooks; $hooks[] = $hook; }
class WP_Error { public function __construct(public $code, public $message, public $data) {} }
class WP_REST_Response { public function __construct(public $data) {} public function header($key, $value) {} }
class WP_REST_Request implements ArrayAccess {
    public function __construct(public array $params = [], public string $nonce = 'valid') {}
    public function get_header($name) { return $this->nonce; }
    public function get_body() { return json_encode($this->params); }
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function offsetExists(mixed $key): bool { return isset($this->params[$key]); }
    public function offsetGet(mixed $key): mixed { return $this->params[$key] ?? null; }
    public function offsetSet(mixed $key, mixed $value): void { $this->params[$key] = $value; }
    public function offsetUnset(mixed $key): void { unset($this->params[$key]); }
}
define('ARRAY_A', 'ARRAY_A');
final class FakeTicketDb {
    public string $prefix = 'custom_'; public int $writes = 0; public array $attempt; public array $scenario; public array $states;
    public bool $failEvents = false; private array $backup = [];
    public function prepare($sql, ...$params) { foreach ($params as $p) { $sql = preg_replace('/%[ds]/', is_numeric($p) ? (string) $p : "'" . addslashes((string) $p) . "'", $sql, 1); } return $sql; }
    public function get_row($sql, $format) { return str_contains($sql, 'ticket_scenarios') ? $this->scenario : $this->attempt; }
    public function get_results($sql, $format) { return str_contains($sql, 'attempt_tickets') ? $this->states : []; }
    public function query($sql) {
        if ($sql === 'START TRANSACTION') { $this->backup = [$this->attempt, $this->states]; }
        elseif ($sql === 'ROLLBACK') { [$this->attempt, $this->states] = $this->backup; }
        elseif ($sql !== 'COMMIT') { $this->writes++; }
        return 1;
    }
    public function update($table, $data, $where) {
        $this->writes++;
        if (str_ends_with($table, 'attempt_tickets')) { $this->states[0]['state'] = $data['state']; }
        if (str_ends_with($table, 'attempts')) { $this->attempt = array_merge($this->attempt, $data); }
        return 1;
    }
    public function insert($table, $data) { $this->writes++; return $this->failEvents && str_ends_with($table, 'events') ? false : 1; }
}

$scenario = require dirname(__DIR__) . '/src/Modules/TicketSimulator/demo.php';
check(ScenarioValidator::validate($scenario) === $scenario, 'Demo validates');
$ticket = $scenario['tickets'][0]; $engine = new SimulationEngine(new SimulatedTestEngine($scenario['resources'])); $state = ScenarioAttempt::initial($ticket); $initial = $state;
$run = function (string $id, array $input = []) use ($engine, $ticket, &$state): array { [$state, $events] = $engine->perform($ticket, $state, $id, $input); return $events; };
rejects(fn() => $engine->perform($ticket, $state, 'fix', []), 'Direct correction cannot bypass prerequisites');
rejects(fn() => $engine->perform($ticket, $state, '__reply', []), 'No invented pending reply');
$run('take'); check($state['status'] === 'accepted', 'Take ownership');
rejects(fn() => $engine->perform($ticket, $state, 'take', []), 'Non-repeatable action cannot replay');
$run('diagnose'); $run('since'); check($state['status'] === 'waiting_user', 'Question enters waiting state');
rejects(fn() => $engine->perform($ticket, $state, 'logs', []), 'Pending reply blocks other actions');
$run('__reply'); check($state['status'] === 'diagnosing', 'Reply returns to diagnosis');
$run('test_app'); check(str_contains($state['tests']['test_app']['text'], 'ÉCHEC'), 'Test fails before correction');
$run('logs'); $run('code'); check(in_array('code', $state['visible_resources'], true), 'Code revealed after investigation');
rejects(fn() => $engine->perform($ticket, $state, 'dba', []), 'Specialist requires written request');
$run('dba', ['message' => 'Pouvez-vous vérifier le schéma après migration ?']);
check($state['status'] === 'waiting_specialist', 'Specialist enters waiting state');
$events = $run('__reply'); check(str_contains(json_encode($events), 'created_at'), 'Prepared specialist response is journalled');
$run('transfer', ['message' => 'Vérifiez le schéma.']); check($state['fields']['assignee'] === 'dba', 'Temporary transfer assigns specialist');
$run('__reply'); check($state['fields']['assignee'] === 'Vous', 'Temporary transfer returns ownership');
$code = array_column($scenario['resources'], null, 'id')['code'];
$run('verify'); check($state['tests']['verify']['outcome'] === 'failure', 'Original code fails validation');
[$state] = CodeWorkspace::save($ticket, $state, $code, $code['expected_content']);
$run('verify'); check($state['tests']['verify']['outcome'] === 'success', 'Edited code passes validation');
$passed = $state;
[$state] = CodeWorkspace::save($ticket, $state, $code, $code['content']);
check(!isset($state['tests']['verify']) && !in_array('verify', $state['done'], true), 'Editing invalidates previous test success');
$priorScore = $state['score'];
$run('verify'); check($state['score'] === $priorScore, 'Retesting after an edit cannot award duplicate points');
$state = $passed;
check(!isset(CodeWorkspace::resource($code, $state)['expected_content']), 'Expected code never included in resource response');
rejects(fn() => CodeWorkspace::save($ticket, $initial, $code, 'malicious'), 'Hidden code cannot be edited');
$readonly = $code; $readonly['editable'] = false;
rejects(fn() => CodeWorkspace::save($ticket, $state, $readonly, 'malicious'), 'Read-only resource cannot be edited');
$report = ['cause' => 'Colonne renommée', 'solution' => 'Utiliser created_at', 'tests' => 'Export mensuel', 'result' => '128 lignes', 'message' => 'L’export fonctionne à nouveau.'];
rejects(fn() => $engine->perform($ticket, $state, 'resolve', ['cause' => 'x']), 'Resolution requires complete report');
$run('resolve', $report); check($state['status'] === 'resolved' && $state['review']['required_actions_met'], 'Documented resolution succeeds');
$run('close'); check($state['status'] === 'closed', 'Closure succeeds');
rejects(fn() => $engine->perform($ticket, $state, 'resolve', $report), 'Closed ticket cannot be mutated by an action');
check($scenario['tickets'][0] === $ticket && ScenarioAttempt::initial($ticket) === $initial, 'Template and other students remain unchanged');
$bad = $initial;
foreach (['take','diagnose'] as $id) { [$bad] = $engine->perform($ticket, $bad, $id, []); }
[$bad,$badEvents] = $engine->perform($ticket, $bad, 'resolve', $report);
check($bad['status'] === 'reopened' && !$bad['review']['required_actions_met'], 'Incomplete investigation reopens ticket without losing report');
check(count(array_filter($badEvents, fn($e) => $e['type'] === 'user_reply')) === 1, 'Negative user feedback preserved');
$variant = $scenario; $variant['tickets'][0]['actions'][0]['requires'] = ['diagnose']; $variant['tickets'][0]['actions'][1]['requires'] = ['take'];
rejects(fn() => ScenarioValidator::validate($variant), 'Circular prerequisites rejected');
$variant = $scenario; $variant['tickets'][0]['actions'][0]['reveal'] = ['unknown'];
rejects(fn() => ScenarioValidator::validate($variant), 'Unknown resource reference rejected');
$variant = $scenario; $variant['tickets'][0]['actions'][0]['id'] = '__reply';
rejects(fn() => ScenarioValidator::validate($variant), 'Reserved action id rejected');
$variant = $scenario; $variant['tickets'][0]['transitions']['new'] = [];
rejects(fn() => $engine->perform($variant['tickets'][0], $initial, 'take', []), 'Teacher transition restrictions enforced');
$attempt = ['id'=>1,'scenario_id'=>1,'assignment_id'=>1,'student_id'=>11,'teacher_id'=>20,'snapshot'=>json_encode($scenario),'status'=>'active','revision'=>5,'started_at'=>'2026-09-15 10:00:00','ended_at'=>null];
$view=StudentView::build($attempt,[$ticket['id']=>$initial],false);$json=json_encode($view);
foreach (['expected_solution','resolution_requires','date_creation','created_at','snapshot','score','bad_resolution_message'] as $secret) { check(!str_contains($json,$secret), 'Student payload excludes ' . $secret); }
check(!isset($view['tickets'][0]['resources'][0]['content']), 'Resource bodies require explicit authorized access');
check(isset(StudentView::build($attempt,[$ticket['id']=>$initial],true)['tickets'][0]['teacher']), 'Teacher receives review data');
check(PermissionService::view($attempt) && PermissionService::edit($attempt), 'Student can access own attempt');
$current=12; check(!PermissionService::view($attempt) && !PermissionService::edit($attempt), 'Other student denied');
$current=20; check(PermissionService::observe($attempt) && !PermissionService::edit($attempt), 'Teacher observation cannot perform student actions');
$current=21; check(!PermissionService::observe($attempt), 'Unrelated teacher denied');
$current=1; check(PermissionService::observe($attempt), 'Administrator can observe');
$current=11; $archived=$attempt;$archived['status']='archived';check(PermissionService::view($archived)&&!PermissionService::edit($archived),'Archive is read-only');
check(RestController::permission(new WP_REST_Request()) === true, 'Valid nonce accepted');
check(RestController::permission(new WP_REST_Request([], 'wrong')) instanceof WP_Error, 'Invalid nonce rejected');
$current=0;check(RestController::permission(new WP_REST_Request()) instanceof WP_Error,'Anonymous denied even with nonce');$current=11;
$routes=[];RestController::register();check(count($routes)>=18,'All REST operations registered');
foreach($routes as $route){check(is_callable($route['permission_callback']),'REST permission callback present');}
$wpdb=new FakeTicketDb();$wpdb->attempt=$attempt;$wpdb->scenario=['id'=>1,'owner_id'=>20,'definition'=>json_encode($scenario)];$wpdb->states=[['ticket_key'=>$ticket['id'],'state'=>json_encode($initial)]];
$get=$routes['GET /scenarios/(?P<id>\d+)']['callback'];
function isDenied($response): bool { return $response instanceof WP_Error && $response->data['status'] === 403; }
check(isDenied($get(new WP_REST_Request(['id'=>1]))),'Direct scenario API denied to student');
check(isDenied($routes['GET /demo']['callback'](new WP_REST_Request())),'Direct demo answers denied to student');
$current=12;$getAttempt=$routes['GET /attempts/(?P<id>\d+)']['callback'];check(isDenied($getAttempt(new WP_REST_Request(['id'=>1]))),'Direct attempt API denies another student');
$current=11;$resource=$routes['POST /attempts/(?P<id>\d+)/tickets/(?P<ticket>[a-zA-Z0-9_-]+)/resources/(?P<resource>[a-zA-Z0-9_-]+)']['callback'];
check(isDenied($resource(new WP_REST_Request(['id'=>1,'ticket'=>'INC-0001','resource'=>'code','revision'=>5]))),'Direct hidden resource access denied');
rejects(fn()=>(new AttemptRepository())->mutate(1,'INC-0001',4,'action',['action_id'=>'take']),'Stale revision rejected');
$current=12;rejects(fn()=>(new AttemptRepository())->mutate(1,'INC-0001',5,'note',['message'=>'intrusion']),'Other student cannot write a note');
check($wpdb->writes===0,'Denied requests perform no database writes');
$current=11;$trackingDisabled=true;
check(PermissionService::view($attempt) && !PermissionService::edit($attempt), 'Disabled tracking preserves reading but forbids writing');
$trackingDisabled=false;
(new AttemptRepository())->mutate(1,'INC-0001',5,'action',['action_id'=>'take']);
check((int)$wpdb->attempt['revision']===6 && json_decode($wpdb->states[0]['state'],true)['status']==='accepted','Authorized action commits state and revision');
$writes=$wpdb->writes;
rejects(fn()=>(new AttemptRepository())->mutate(1,'INC-0001',5,'action',['action_id'=>'take']),'Second request with same revision is rejected');
check($wpdb->writes===$writes,'Duplicate request does not append events');
$wpdb->failEvents=true;
rejects(fn()=>(new AttemptRepository())->mutate(1,'INC-0001',6,'action',['action_id'=>'diagnose']),'Journal failure aborts mutation');
check((int)$wpdb->attempt['revision']===6 && json_decode($wpdb->states[0]['state'],true)['status']==='accepted','Failed mutation rolls back state');
check(Ouinpo\Suite\Modules\TicketSimulator\ScenarioRepository::table('events')==='custom_ouinpo_ticket_events','Custom WordPress table prefix respected');
check(in_array('ticket_simulator', Ouinpo\Suite\Core\ModuleSettings::availableModules(), true),'Module is available in settings');
check(!in_array('ticket_simulator', Ouinpo\Suite\Core\ModuleSettings::defaultEnabledModules(), true),'Module disabled by default');
$module=new Ouinpo\Suite\Modules\TicketSimulator\Module();$writes=$wpdb->writes;$module->deactivate();
check($wpdb->writes===$writes,'Deactivation preserves data');
$wpdb->failEvents = false;
$editState = $initial; $editState['status'] = 'diagnosing'; $editState['visible_resources'][] = 'code';
$wpdb->states[0]['state'] = json_encode($editState);
$editRoute = $routes['PATCH /attempts/(?P<id>\d+)/tickets/(?P<ticket>[a-zA-Z0-9_-]+)/resources/(?P<resource>[a-zA-Z0-9_-]+)/code']['callback'];
$rawCode = "<?php\n\$sql = '<script>example</script>';\n";
$request = new WP_REST_Request(['id'=>1,'ticket'=>'INC-0001','resource'=>'code','revision'=>6,'content'=>$rawCode]);
$current = 12;
check(isDenied($editRoute($request)), 'Code endpoint rejects another student');
$current = 11;
check($editRoute($request) instanceof WP_REST_Response, 'Code endpoint accepts owned visible snippet');
check(json_decode($wpdb->states[0]['state'], true)['code_edits']['code'] === $rawCode, 'PHP and HTML code stored verbatim as data');
check($scenario['resources'] === (require dirname(__DIR__) . '/src/Modules/TicketSimulator/demo.php')['resources'], 'Original scenario code remains unchanged');
$tooLarge = new WP_REST_Request(['id'=>1,'ticket'=>'INC-0001','resource'=>'code','revision'=>7,'content'=>str_repeat('x',100001)]);
check($editRoute($tooLarge) instanceof WP_Error, 'Oversized code rejected');
echo "\n$checks checks passed. WordPress/MySQL integration and browser QA are separate.\n";
