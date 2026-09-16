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
        $out['tickets'] = [];
        foreach ($s['tickets'] as $t) {
            $state = $states[$t['id']];
            $public = array_intersect_key($state, array_flip(['status','fields','done','minutes','tests','resolution']));
            $public += ['id' => $t['id'], 'title' => $t['title'], 'description' => $t['description']];
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
            if ($teacher) { $public['teacher'] = ['expected' => $t['expected'], 'expected_solution' => $t['expected_solution'] ?? '', 'review' => $state['review'] ?? [], 'score' => $state['score']]; }
            $out['tickets'][] = $public;
        }
        return $out;
    }
}
