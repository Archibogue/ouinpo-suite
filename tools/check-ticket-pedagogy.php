<?php
declare(strict_types=1);
require __DIR__ . '/check-ticket-simulator.php';

use Ouinpo\Suite\Modules\TicketSimulator\{Pedagogy,ScenarioValidator,ScenarioAttempt,SimulationEngine,SimulatedTestEngine,CodeWorkspace,StudentView,AttemptRepository,AttemptMarkdown};

$modern = require dirname(__DIR__) . '/src/Modules/TicketSimulator/demo.php';
$t = $modern['tickets'][0];
$engine = new SimulationEngine(new SimulatedTestEngine($modern['resources']));
$s = ScenarioAttempt::initial($t);
$run = static function ($id, $input = []) use (&$s, &$t, $engine) { [$s, $events] = $engine->perform($t, $s, $id, $input); return $events; };
$run('take'); $run('diagnose');
rejects(fn() => $engine->perform($t, $s, '__validate_requester', []), 'Cannot validate before resolution');
rejects(fn() => $engine->perform($t, $s, 'resolve', $report), 'Guided resolution rejects unqualified request');
$qualified = ['nature'=>'incident','impact'=>'Moyen','urgency'=>'Élevée','priority'=>'Haute','priority_justification'=>'Comptabilité bloquée avant 14 h.'];
$s['fields'] = array_merge($s['fields'], $qualified);
foreach (['take','diagnose','since','__reply','logs','code','dba','__reply'] as $id) {
    if (in_array($id, ['take','diagnose'], true)) { continue; }
    $run($id, ['message'=>'Le schéma a-t-il changé ?']);
}
$run('verify');
rejects(fn() => $engine->perform($t, $s, 'resolve', $report), 'Failed test blocks guided resolution');
$code = array_column($modern['resources'], null, 'id')['code'];
[$s] = CodeWorkspace::save($t, $s, $code, $code['expected_content']);
$run('verify'); $ready = $s;
$withoutAction = $ready; $withoutAction['done'] = array_values(array_diff($withoutAction['done'], ['code']));
rejects(fn() => $engine->perform($t, $withoutAction, 'resolve', $report), 'Successful test alone cannot bypass required investigation action');
foreach (Pedagogy::QUALIFICATION as $field) {
    foreach (['', ' À QUALIFIER '] as $value) {
        $invalid = $ready; $invalid['fields'][$field] = $value;
        rejects(fn() => $engine->perform($t, $invalid, 'resolve', $report), 'Reject missing qualification ' . $field . ':' . $value);
    }
}
$invalid = $ready; $invalid['fields']['nature'] = 'applicatif';
rejects(fn() => $engine->perform($t, $invalid, 'resolve', $report), 'Nature cannot be a technical category');
$invalid = $ready; $invalid['fields']['priority_justification'] = 'x';
[$presenceOnly] = $engine->perform($t, $invalid, 'resolve', $report);
check($presenceOnly['status'] === 'resolved', 'Presence checks do not grade free-text relevance');
[$s] = CodeWorkspace::save($t, $s, $code, $code['content']);
check(!isset($s['tests']['verify']), 'Code edit invalidates previous successful proof');
rejects(fn() => $engine->perform($t, $s, 'resolve', $report), 'Invalidated proof prevents resolution');
$s = $ready;
$run('resolve', $report);
check(!Pedagogy::finished($modern, [$t['id']=>$s]), 'Resolved ticket does not finish closure-based scenario');
check(Pedagogy::finished(array_merge($modern, ['completion_status'=>'resolved']), [$t['id']=>$s]), 'Resolution-based scenario ends at resolution');
rejects(fn() => $engine->perform($t, $s, 'close', []), 'Closure requires requester confirmation');
$modernAttempt = array_merge($attempt, ['snapshot'=>json_encode($modern)]);
$v = StudentView::build($modernAttempt, [$t['id']=>$s], false);
check(in_array('__validate_requester', array_column($v['tickets'][0]['actions'], 'id'), true), 'Student receives validation action');
check(!str_contains(json_encode($v), '128 lignes attendues') && !str_contains(json_encode($v), 'requester_validation_count'), 'Future replies and sequencing stay private');
$run('__validate_requester');
rejects(fn() => $engine->perform($t, $s, '__validate_requester', []), 'Confirmation cannot be replayed');
$run('close');
check(Pedagogy::finished($modern, [$t['id']=>$s]), 'Full guided workflow completes after confirmation and closure');
$md = AttemptMarkdown::render($modernAttempt, [$t['id']=>$s], []);
foreach (['Demande initiale','Justification de priorité','Parcours terminé : oui','Contrôles automatiques : satisfaits','Décision de clôture : ticket clôturé','Appréciation pédagogique','Retour simulé : confirmation'] as $text) {
    check(str_contains($md, $text), 'Export includes ' . $text);
}
check(!str_contains($md, 'expected_content') && !str_contains($md, 'expected_solution'), 'Export hides correction metadata');

$t['requester_validation']['replies'] = [['outcome'=>'persists','message'=>'Le problème persiste. Merci de reprendre les vérifications.','private'=>'SECRET_REPLY_CRITERIA'], ['outcome'=>'confirmed','message'=>'SECRET_FUTURE_CONFIRMATION']];
$reopenScenario = $modern; $reopenScenario['tickets'][0] = $t;
ScenarioValidator::validate($reopenScenario);
$s = $ready; $run('resolve', $report); $events = $run('__validate_requester');
check($s['status'] === 'reopened' && !isset($s['tests']['verify']), 'Negative reply reopens and invalidates required tests');
check($events[0]['type'] === 'requester_validation', 'Requester decision is journalled');
check(!Pedagogy::finished($reopenScenario, [$t['id']=>$s]), 'Reopened scenario is not complete');
$a = array_merge($attempt, ['snapshot'=>json_encode($reopenScenario)]);
check(!str_contains(json_encode(StudentView::build($a, [$t['id']=>$s], false)), 'SECRET_FUTURE_CONFIRMATION'), 'Next reply stays hidden after reopening');
check(!str_contains(AttemptMarkdown::render($a, [$t['id']=>$s], []), 'SECRET_REPLY_CRITERIA'), 'Private reply metadata is never exported');
rejects(fn() => $engine->perform($t, $s, 'resolve', $report), 'Reopening requires fresh tests');
$run('verify'); $run('resolve', $report); $run('__validate_requester'); $run('close');
check($s['status'] === 'closed', 'Negative reply then fresh resolution and confirmation can finish');
foreach (['service','evolution'] as $nature) {
    $variant = $ready; $variant['fields']['nature'] = $nature;
    [$result] = $engine->perform($t, $variant, 'resolve', array_merge($report, ['cause'=>'Analyse du besoin utilisateur']));
    check($result['status'] === 'resolved', 'Analysis accepted for request nature ' . $nature);
}

// Persistence uses the frozen scenario, not edits in the live scenario repository.
$current = 11; $wpdb = new FakeTicketDb();
$wpdb->attempt = array_merge($modernAttempt, ['revision'=>1]);
$wpdb->scenario = ['id'=>1,'owner_id'=>20,'definition'=>json_encode(array_merge($modern, ['completion_status'=>'resolved']))];
$wpdb->states = [['ticket_key'=>$t['id'],'state'=>json_encode($ready)]];
$repo = new AttemptRepository();
$repo->mutate(1, $t['id'], 1, 'action', ['action_id'=>'resolve'] + $report);
check($wpdb->attempt['status'] === 'active' && $wpdb->attempt['ended_at'] === null, 'Database completion uses immutable closure snapshot');
$repo->mutate(1, $t['id'], 2, 'action', ['action_id'=>'__validate_requester']);
$repo->mutate(1, $t['id'], 3, 'action', ['action_id'=>'close']);
check($wpdb->attempt['status'] === 'completed' && $wpdb->attempt['ended_at'] !== null, 'Closure stores completed state and end date');
$resolutionScenario = $reopenScenario; $resolutionScenario['completion_status'] = 'resolved';
$wpdb->attempt = array_merge($attempt, ['snapshot'=>json_encode($resolutionScenario), 'revision'=>1]);
$wpdb->states[0]['state'] = json_encode($ready);
$repo->mutate(1, $t['id'], 1, 'action', ['action_id'=>'resolve'] + $report);
check($wpdb->attempt['status'] === 'completed', 'Resolution criterion completes before requester decision');
$repo->mutate(1, $t['id'], 2, 'action', ['action_id'=>'__validate_requester']);
check($wpdb->attempt['status'] === 'active' && $wpdb->attempt['ended_at'] === null, 'Reopening clears completion and end date in persistence');
$wpdb->attempt = array_merge($attempt, ['snapshot'=>json_encode($scenario), 'revision'=>1]);
$wpdb->states[0]['state'] = json_encode($ready);
$repo->mutate(1, $t['id'], 1, 'action', ['action_id'=>'resolve'] + $report);
check($wpdb->attempt['status'] === 'completed', 'Old snapshot still finishes at resolution without new rules');

foreach ([['completion_status'=>'invalid'], ['completion_status'=>true]] as $change) {
    rejects(fn() => ScenarioValidator::validate(array_merge($modern, $change)), 'Invalid completion criterion rejected');
}
foreach ([['guided'=>'yes'], ['qualification_required'=>['expected_solution']], ['requester_validation'=>['enabled'=>true,'replies'=>[]]], ['requester_validation'=>['enabled'=>true,'replies'=>[['outcome'=>'persists','message'=>'No']]]]] as $change) {
    $invalid = $modern; $invalid['tickets'][0] = array_merge($invalid['tickets'][0], $change);
    rejects(fn() => ScenarioValidator::validate($invalid), 'Invalid pedagogy options rejected');
}
$invalid = $modern; $invalid['tickets'][0]['actions'][1]['to_status'] = 'resolved';
rejects(fn() => ScenarioValidator::validate($invalid), 'Action cannot bypass guided resolution');
$partial = $modern; $partial['tickets'][0]['guided'] = false; $partial['tickets'][0]['requester_validation']['enabled'] = false; $partial['completion_status'] = 'resolved';
check(ScenarioValidator::validate($partial) === $partial, 'Teacher can select a partial unguided pathway');
$multi = $modern; $other = $modern['tickets'][0]; $other['id'] = 'INC-OTHER'; $multi['tickets'][] = $other;
check(!Pedagogy::finished($multi, [$t['id']=>['status'=>'closed'], 'INC-OTHER'=>['status'=>'resolved']]), 'All tickets must satisfy the completion criterion');
$wpdb->attempt = array_merge($modernAttempt, ['revision'=>1]);
$wpdb->states[0]['state'] = json_encode(ScenarioAttempt::initial($modern['tickets'][0]));
$qualification = $routes['PATCH /attempts/(?P<id>\d+)/tickets/(?P<ticket>[a-zA-Z0-9_-]+)']['callback'];
$request = new WP_REST_Request(['id'=>1,'ticket'=>$t['id'],'revision'=>1] + $qualified);
check($qualification($request) instanceof WP_REST_Response, 'REST accepts nature and priority justification');
$saved = json_decode($wpdb->states[0]['state'], true);
check($saved['fields']['priority_justification'] === $qualified['priority_justification'] && $saved['fields']['nature'] === 'incident', 'REST persists new qualification fields');
check($qualification(new WP_REST_Request(['id'=>1,'ticket'=>$t['id'],'revision'=>2,'nature'=>'réseau'])) instanceof WP_Error, 'REST rejects technical category as request nature');
echo "Total: $checks checks passed including B1.2 pedagogy.\n";
