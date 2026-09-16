<?php
/** Exercises each supplied SPOPI case with the real engine, without WordPress writes. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/src/Core/Autoloader.php';
Ouinpo\Suite\Core\Autoloader::init(dirname(__DIR__) . '/src');
use Ouinpo\Suite\Modules\TicketSimulator\{ScenarioValidator,ScenarioAttempt,SimulationEngine,SimulatedTestEngine,CodeWorkspace,TicketScenario};

$scenario = json_decode(file_get_contents($argv[1] ?? __DIR__ . '/templates/patadesk/PataDesk_AP_Portail_SPOPI-corrige.json'), true, 512, JSON_THROW_ON_ERROR);
ScenarioValidator::validate($scenario);
$checks = 0;
function verify(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) { throw new RuntimeException($message); } }
$plans = [
    'N2-01'=>['reproduce','inspect_dom','inspect_css'],
    'N2-02'=>['read_report','inspect_html'],
    'N2-03'=>['measure','read_criteria','inspect_css'],
    'N2-04'=>['keyboard_test','inspect_html','inspect_css'],
    'T01'=>['reproduce','inspect_html','inspect_tree'],
    'T02'=>['compare','inspect_html','inspect_css','selector'],
    'T03'=>['reproduce','inspect_html','inspect_js'],
    'T04'=>['reproduce','inspect_html','inspect_js'],
    'T05'=>['reproduce','check_ticket','inspect_need','inspect_js'],
    'E06'=>['inspect_html','inspect_js'], 'E07'=>['inspect_meta','inspect_css'],
    'P01'=>['scope','__reply','status','refill','verify'],
    'P02'=>['scope','__reply','tests','escalate_network','__reply','verify'],
    'P03'=>['identity','__reply','procedure','reset','verify','__reply'],
];
$input = ['cause'=>'Analyse documentée dans la simulation','solution'=>'Procédure et correction appliquées','tests'=>'Vérifications prévues par le scénario','result'=>'Résultat simulé confirmé','message'=>'Votre demande a été traitée, voici le résultat des vérifications.'];
$resources = TicketScenario::index($scenario['resources']);
$engine = new SimulationEngine(new SimulatedTestEngine($scenario['resources']));
foreach ($scenario['tickets'] as $ticket) {
    $id = $ticket['id']; verify(isset($plans[$id]), 'No test plan for ' . $id);
    $initial = ScenarioAttempt::initial($ticket); $state = $initial;
    $visited = []; $covered = [];
    $run = static function ($action) use (&$state, &$visited, &$covered, $engine, $ticket, $input): void {
        $visited[] = $state;
        [$state] = $engine->perform($ticket, $state, $action, $input);
        $covered[$action] = true;
    };
    $run('take'); $run('diagnose');
    [$premature] = $engine->perform($ticket, $state, 'resolve', $input);
    verify($premature['status'] === 'reopened', $id . ': premature resolution must reopen');
    $visited[] = $premature;
    foreach ($plans[$id] as $action) { $run($action); }
    foreach ($ticket['actions'] as $action) {
        if (empty($action['code_resource'])) { continue; }
        $run($action['id']);
        verify($state['tests'][$action['id']]['outcome'] === 'failure', $id . ': original code must fail');
        $resource = $resources[$action['code_resource']];
        [$state] = CodeWorkspace::save($ticket, $state, $resource, $resource['expected_content']);
        $run($action['id']);
        verify($state['tests'][$action['id']]['outcome'] === 'success', $id . ': expected correction must pass');
        [$invalidated] = CodeWorkspace::save($ticket, $state, $resource, $resource['content']);
        verify(!isset($invalidated['tests'][$action['id']]), $id . ': edit must invalidate test');
        [$reopened] = $engine->perform($ticket, $invalidated, 'resolve', $input);
        verify($reopened['status'] === 'reopened', $id . ': invalidated test must prevent valid resolution');
    }
    $run('resolve');
    verify($state['status'] === 'resolved' && $state['review']['required_actions_met'], $id . ': happy path must satisfy evidence');
    $run('close');
    verify($state['status'] === 'closed', $id . ': closure must succeed');
    // Execute every alternative action reachable along the main path, in a separate copy.
    foreach ($ticket['actions'] as $action) {
        if (isset($covered[$action['id']])) { continue; }
        foreach ($visited as $candidate) {
            if (!$engine->available($action, $candidate, $ticket)) { continue; }
            [$branch] = $engine->perform($ticket, $candidate, $action['id'], $input);
            if ($branch['pending']) { [$branch] = $engine->perform($ticket, $branch, '__reply', []); }
            $covered[$action['id']] = true;
            break;
        }
        verify(isset($covered[$action['id']]), $id . ': untested/unreachable action ' . $action['id']);
    }
    verify(ScenarioAttempt::initial($ticket) === $initial, $id . ': scenario and other attempts unchanged');
    echo $id . ' OK — ' . count($ticket['actions']) . " actions covered; early reopening, happy path and closure verified.\n";
}
echo "$checks checks passed; " . count($scenario['tickets']) . " tickets verified. Tests are simulated, not real code execution.\n";
