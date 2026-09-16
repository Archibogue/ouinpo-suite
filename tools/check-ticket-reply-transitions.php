<?php
declare(strict_types=1);
require __DIR__ . '/check-ticket-simulator.php';
use Ouinpo\Suite\Modules\TicketSimulator\{ScenarioValidator,SimulationEngine,ScenarioAttempt};

$fixture = $scenario;
foreach ($fixture['tickets'][0]['actions'] as &$action) {
    if ($action['id'] === 'since') {
        $action['label'] = 'Faire confirmer la reconnexion';
        $action['return_status'] = 'resolving';
    }
}
unset($action);
try {
    ScenarioValidator::validate($fixture);
    check(false, 'Invalid response return rejected');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), 'Faire confirmer la reconnexion') && str_contains($e->getMessage(), 'waiting_user → resolving'), 'Import error identifies the action and missing return transition');
}
$engine = new SimulationEngine(); $ticket = $fixture['tickets'][0];
$state = ScenarioAttempt::initial($ticket);
foreach (['take','diagnose'] as $id) { [$state] = $engine->perform($ticket, $state, $id, []); }
$before = $state;
try {
    $engine->perform($ticket, $state, 'since', []);
    check(false, 'Old snapshot with invalid return fails safely');
} catch (DomainException $e) {
    check(str_contains($e->getMessage(), 'Retour de réponse mal configuré'), 'Old snapshot explains the configuration error');
}
check($state === $before, 'Invalid outgoing request leaves the attempt state unchanged');
$fixture['tickets'][0]['transitions']['waiting_user'][] = 'resolving';
check(ScenarioValidator::validate($fixture) === $fixture, 'Explicitly authorized return validates');
$ticket = $fixture['tickets'][0];
[$state] = $engine->perform($ticket, $state, 'since', []);
check($state['status'] === 'waiting_user' && $state['pending']['return_status'] === 'resolving', 'Confirmation request enters user waiting state');
[$state] = $engine->perform($ticket, $state, '__reply', []);
check($state['status'] === 'resolving' && $state['pending'] === null, 'Receiving confirmation resumes resolution');
echo "Total: $checks checks passed including response transitions.\n";
