<?php
require __DIR__ . '/check-ticket-simulator.php';
use Ouinpo\Suite\Modules\TicketSimulator\{ScenarioValidator,ScenarioAttempt,TicketIntake,SimulationEngine,SimulatedTestEngine,CodeWorkspace,Pedagogy};
$example = json_decode(file_get_contents(__DIR__ . '/templates/patadesk/ComptaNova-demande-dialogues-ia.json'), true, 512, JSON_THROW_ON_ERROR);
ScenarioValidator::validate($example);
$t = $example['tickets'][0];
$s = ScenarioAttempt::initial($t);
[$s] = TicketIntake::save($t, $s, ['title'=>'Export mensuel indisponible','requester'=>'Camille Martin','service'=>'Comptabilité','application'=>'ComptaNova','symptoms'=>'Export impossible depuis ce matin','missing_information'=>'Périmètre et changement récent à confirmer','questions'=>'Les collègues sont-ils touchés ? Une mise à jour a-t-elle eu lieu ?']);
$engine = new SimulationEngine(new SimulatedTestEngine($example['resources']));
$run = function ($id, $input = []) use (&$s, $t, $engine) { [$s] = $engine->perform($t, $s, $id, $input); };
$s['fields'] = array_merge($s['fields'], ['nature'=>'incident','category'=>'Applicatif','impact'=>'Moyen','urgency'=>'Élevée','priority'=>'Haute','priority_justification'=>'Trois personnes bloquées sur cet export attendu avant 14 h.']);
foreach (['take','diagnose','since','__reply','logs','code','dba','__reply'] as $id) { $run($id, ['message'=>'Erreur date_creation : pouvez-vous préciser le changement de schéma ?']); }
$run('verify');
rejects(fn() => $engine->perform($t, $s, 'resolve', $report), 'ComptaNova failed test blocks resolution');
$resource = array_column($example['resources'], null, 'id')['code'];
[$s] = CodeWorkspace::save($t, $s, $resource, $resource['expected_content']);
$run('verify'); $run('resolve', $report);
check(!Pedagogy::finished($example, [$t['id']=>$s]), 'ComptaNova requires closure');
$run('__validate_requester'); $run('close');
check(Pedagogy::finished($example, [$t['id']=>$s]), 'ComptaNova complete workflow succeeds');
