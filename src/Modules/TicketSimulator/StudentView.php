<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Explicit allowlists are the boundary between the private snapshot and the browser. */
final class StudentView
{
    public static function build(array $attempt, array $states, bool $teacher): array
    {
        $s = json_decode($attempt['snapshot'], true);
        $out = array_intersect_key($attempt, array_flip(['id','scenario_id','student_id','status','revision','started_at','ended_at']));
        $out['title'] = $s['title']; $out['description'] = $s['description'] ?? ''; $out['teacher_view'] = $teacher;
        $out['read_only'] = $teacher || $attempt['status'] === 'archived';
        $out['completion_status'] = $s['completion_status'] ?? 'resolved';
        $out['number'] = $attempt['number'] ?? $attempt['id'];
        $out['path_completed'] = Pedagogy::finished($s, $states);
        $out['tickets'] = [];
        foreach ($s['tickets'] as $t) {
            $state = $states[$t['id']];
            $public = array_intersect_key($state, array_flip(['status','fields','done','minutes','tests','resolution']));
            $public += ['id' => $t['id'], 'title' => $t['title'], 'description' => $t['description']];
            $public['guided'] = !empty($t['guided']);
            $public['qualification_required'] = $t['qualification_required'] ?? [];
            $public['qualification_missing'] = Pedagogy::missing($t, $state);
            $public['requester_validation_required'] = Pedagogy::validation($t);
            $public['requester_validation'] = isset($state['requester_validation']) ? array_intersect_key($state['requester_validation'], array_flip(['outcome','message'])) : null;
            $public['automatic_checks'] = isset($state['review']) ? (Pedagogy::evidence($t, $state) && !Pedagogy::missing($t, $state)) : null;
            $public['requester'] = TicketScenario::index($s['users'])[$t['requester_id']]['label'];
            $public['resources'] = [];
            foreach ($s['resources'] as $r) {
                if (in_array($r['id'], $state['visible_resources'], true)) {
                    $public['resources'][] = array_intersect_key($r, array_flip(['id','label','type','filename','language']));
                }
            }
            $public['actions'] = [];
            foreach ($t['actions'] as $action) {
                if ((new SimulationEngine())->available($action, $state, $t)) {
                    $a = array_intersect_key($action, array_flip(['id','label','type','description','requires_message','cost']));
                    if (!empty($action['specialist_id'])) { $a['specialist'] = TicketScenario::index($s['specialists'])[$action['specialist_id']]['label']; }
                    $public['actions'][] = $a;
                }
            }
            if ($state['pending']) { $public['actions'] = [['id' => '__reply', 'type' => 'reply', 'label' => 'Recevoir la réponse et reprendre']]; }
            elseif (Pedagogy::validation($t) && $state['status'] === 'resolved' && ($state['requester_validation']['outcome'] ?? '') !== 'confirmed') {
                $public['actions'][] = ['id'=>'__validate_requester', 'type'=>'reply', 'label'=>'Recevoir la validation simulée du demandeur'];
            }
            if ($teacher) { $public['teacher'] = ['expected' => $t['expected'], 'expected_solution' => $t['expected_solution'] ?? '', 'review' => $state['review'] ?? [], 'score' => $state['score']]; }
            $out['tickets'][] = $public;
        }
        return $out;
    }
}
