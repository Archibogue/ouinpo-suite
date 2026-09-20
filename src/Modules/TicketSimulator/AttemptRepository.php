<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class AttemptRepository
{
    public function get(int $id, bool $lock = false): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . ScenarioRepository::table('attempts') . ' WHERE id=%d' . ($lock ? ' FOR UPDATE' : ''), $id), ARRAY_A);
        if (!$row) { throw new \RuntimeException('Tentative introuvable.', 404); }
        $row['number'] = AttemptNumber::display((int) $row['id']);
        return $row;
    }
    public function listing(): array
    {
        global $wpdb;
        $uid = get_current_user_id();
        $where = $wpdb->prepare('student_id=%d', $uid);
        if (\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::TICKET_OBSERVE)) { $where .= $wpdb->prepare(' OR teacher_id=%d', $uid); }
        if (PermissionService::all()) { $where = '1=1'; }
        $rows = $wpdb->get_results('SELECT * FROM ' . ScenarioRepository::table('attempts') . " WHERE $where ORDER BY id DESC LIMIT 200", ARRAY_A) ?: [];
        $offset = AttemptNumber::offset();
        foreach ($rows as &$row) {
            $public= array_intersect_key($row,array_flip(['id','assignment_id','scenario_id','student_id','teacher_id','status','started_at','ended_at']));
            $public += Presentation::metadata($row);
            $data=Assessment::data($row);
            $public['assessment_state']=($data['settings']['mode'] ?? '')==='graded' ? $data['state'] : null;
            $public['number']=max(1,(int)$row['id']-$offset);
            $row=$public;
        }
        unset($row);
        return $rows;
    }
    public function start(int $assignmentId, bool $next = false): int
    {
        global $wpdb;
        PermissionService::require(PermissionService::practice());
        $assignment = (new AssignmentService())->get($assignmentId);
        return $this->transaction(function () use ($assignmentId, $assignment, $next, $wpdb) {
            // Always lock scenario before assignment/attempt, matching deletion.
            $s = (new ScenarioRepository())->get((int) $assignment['scenario_id'], true);
            $a = (new AssignmentService())->get($assignmentId, true);
            PermissionService::require((new AssignmentService())->allows($a, get_current_user_id()));
            PermissionService::require($s['status'] === 'published');
            $settings = Assessment::settings($a);
            if (!Assessment::open($settings)) { throw new \DomainException('Activité non ouverte ou échéance dépassée.'); }
            $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . ScenarioRepository::table('attempts') . " WHERE assignment_id=%d AND student_id=%d AND status<>'archived' ORDER BY id DESC LIMIT 1 FOR UPDATE", $assignmentId, get_current_user_id()));
            if ($existing) {
                if (!$next) { return (int) $existing; }
                $previous = $this->get((int)$existing, true);
                if (!Assessment::data($previous) || (Assessment::graded($previous) ? (Assessment::data($previous)['state'] ?? 'working') === 'working' : $previous['status'] !== 'completed')) { throw new \DomainException('Terminez l’entraînement ou remettez l’évaluation avant une nouvelle tentative.'); }
            }
            if (!empty($a['settings'])) {
                $count = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.ScenarioRepository::table('attempts').' WHERE assignment_id=%d AND student_id=%d', $assignmentId, get_current_user_id()));
                if ($count >= $settings['attempts']) { throw new \DomainException('Nombre de tentatives autorisées atteint.'); }
            }
            return $this->create($s, $assignmentId, get_current_user_id(), !empty($a['settings']) ? $settings : null);
        });
    }
    private function create(array $s, int $assignmentId, int $student, ?array $settings = null): int
    {
        global $wpdb;
        $data = ['scenario_id' => $s['id'], 'assignment_id' => $assignmentId, 'student_id' => $student,
            'teacher_id' => $s['owner_id'], 'snapshot' => wp_json_encode($s['definition']), 'started_at' => current_time('mysql', true)];
        if ($settings !== null) { $data['assessment']=wp_json_encode(['settings'=>$settings,'state'=>'working','submissions'=>[],'history'=>[]]); }
        ScenarioRepository::check($wpdb->insert(ScenarioRepository::table('attempts'), $data));
        $id = (int) $wpdb->insert_id;
        foreach ($s['definition']['tickets'] as $ticket) {
            ScenarioRepository::check($wpdb->insert(ScenarioRepository::table('attempt_tickets'), ['attempt_id' => $id, 'ticket_key' => $ticket['id'], 'state' => wp_json_encode(ScenarioAttempt::initial($ticket))]));
            (new EventRepository())->add(['id' => $id, 'student_id' => $student], $ticket['id'], ['type' => 'created', 'text' => TicketIntake::enabled($ticket) ? "Message utilisateur original :\n" . $ticket['raw_request'] : 'Ticket reçu.']);
        }
        return $id;
    }
    public function states(int $id): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT ticket_key,state FROM ' . ScenarioRepository::table('attempt_tickets') . ' WHERE attempt_id=%d', $id), ARRAY_A) ?: [];
        $states = [];
        foreach ($rows as $r) { $states[$r['ticket_key']] = json_decode($r['state'], true); }
        return $states;
    }
    public function mutate(int $id, string $ticketId, int $revision, string $operation, array $input): void
    {
        global $wpdb;
        $this->transaction(function () use ($id, $ticketId, $revision, $operation, $input, $wpdb) {
            $a = $this->get($id, true);
            PermissionService::require(PermissionService::edit($a));
            if ($operation === 'dialogue') { PermissionService::require(Assessment::aid($a,'ai_dialogue')); }
            if ($operation === 'reset_ticket' && Assessment::graded($a)) { throw new \DomainException('Remise à zéro interdite en évaluation.'); }
            if ((int) $a['revision'] !== $revision) { throw new \RuntimeException('La tentative a changé. Rechargez-la avant de réessayer.', 409); }
            $snapshot = json_decode($a['snapshot'], true);
            $ticket = TicketScenario::index($snapshot['tickets'])[$ticketId] ?? null;
            $state = $this->states($id)[$ticketId] ?? null;
            if (!$ticket || !$state) { throw new \RuntimeException('Ticket introuvable.', 404); }
            $events = [];
            if ($operation === 'evidence') {
                $state['evidence'] = array_intersect_key($input, array_flip(Pedagogy::TRACES));
                $events[] = ['type'=>'technical_note','text'=>'Traces déclarées par l’élève (à vérifier) : ' . wp_json_encode($state['evidence'])];
            } elseif ($operation === 'finish_exercise') {
                if (!in_array($snapshot['completion_status'] ?? '', ['qualified','oriented'], true)) { throw new \DomainException('Utilisez le parcours de résolution de ce scénario.'); }
                if (Pedagogy::missing($ticket,$state) || Pedagogy::traceMissing($snapshot,$ticket,$state)) { throw new \DomainException('Complétez les traces exigées avant de terminer cet exercice.'); }
                $state['exercise_completed']=true;
                $events[]=['type'=>'technical_note','text'=>'Exercice terminé ; statut technique conservé. Présence des traces seulement, pertinence à apprécier.'];
            } elseif ($operation === 'dialogue') {
                if ($state['status'] === 'closed') { throw new \DomainException('Ticket clôturé.'); }
                $events = [
                    ['type'=>'ai_question','recipient'=>$input['recipient'],'text'=>'Vous → ' . $input['label'] . " (IA)\n" . $input['message']],
                    ['type'=>'ai_reply','recipient'=>$input['recipient'],'text'=>$input['label'] . " (IA) → Vous\n" . $input['reply']],
                ];
                $state['dialogue_history'][] = ['recipient'=>$input['recipient'],'question'=>$input['message'],'reply'=>$input['reply']];
                $state['dialogue_history'] = array_slice($state['dialogue_history'], -40);
            } elseif ($operation === 'intake') {
                [$state, $events] = TicketIntake::save($ticket, $state, $input);
            } elseif ($operation === 'reset_ticket') {
                if (($input['confirm_reset'] ?? false) !== true) { throw new \InvalidArgumentException('Confirmation de remise à zéro requise.'); }
                $state = ScenarioAttempt::initial($ticket);
                $events[] = ['type'=>'ticket_reset', 'text'=>'Ticket remis à son état initial. Les traces précédentes appartiennent au traitement antérieur ; qualification, code, tests, temps et résolution ont été réinitialisés.'];
            } elseif ($operation === 'action') {
                $action = TicketScenario::index($ticket['actions'])[$input['action_id']] ?? [];
                if (!empty($action['hint'])) { PermissionService::require(Assessment::aid($a,'hints')); }
                [$state, $events] = (new SimulationEngine(new SimulatedTestEngine($snapshot['resources'])))->perform($ticket, $state, $input['action_id'], $input);
            } elseif ($operation === 'code') {
                $resource = TicketScenario::index($snapshot['resources'])[$input['resource_id']] ?? null;
                if (!$resource) { throw new \RuntimeException('Ressource introuvable.', 404); }
                [$state, $events] = CodeWorkspace::save($ticket, $state, $resource, $input['content']);
            } elseif ($operation === 'qualify') {
                if (in_array($state['status'], ['resolved','closed'], true)) { throw new \DomainException('Ticket terminé.'); }
                if (isset($input['nature']) && !in_array($input['nature'], array_merge(['','À qualifier'], Pedagogy::NATURES), true)) { throw new \InvalidArgumentException('Nature de demande invalide.'); }
                foreach (['nature','category','subcategory','impact','urgency','priority','priority_justification','it_service','assignee'] as $key) {
                    if (isset($input[$key])) {
                        $events[] = ['type' => 'qualification', 'text' => $key . ' : ' . ($state['fields'][$key] ?? '—') . ' → ' . $input[$key]];
                        $state['fields'][$key] = $input[$key];
                    }
                }
            } elseif ($operation === 'resource') {
                $rid = $input['resource_id'];
                PermissionService::require(in_array($rid, $state['visible_resources'], true));
                $events[] = ['type' => 'resource', 'text' => 'Consultation : ' . (TicketScenario::index($snapshot['resources'])[$rid]['label'] ?? $rid)];
            } else {
                if (trim($input['message'] ?? '') === '') { throw new \InvalidArgumentException('La note est vide.'); }
                $events[] = ['type' => 'technical_note', 'text' => $input['message']];
            }
            ScenarioRepository::check($wpdb->update(ScenarioRepository::table('attempt_tickets'), ['state' => wp_json_encode($state)], ['attempt_id' => $id, 'ticket_key' => $ticketId]));
            foreach ($events as $event) { (new EventRepository())->add($a, $ticketId, $event); }
            AttemptDrafts::consume($a, $ticketId, $operation, $input);
            $states = $this->states($id);
            $finished = Pedagogy::finished($snapshot, $states);
            ScenarioRepository::check($wpdb->update(ScenarioRepository::table('attempts'), ['revision' => $revision + 1, 'status' => $finished ? 'completed' : 'active', 'ended_at' => $finished ? ($a['ended_at'] ?: current_time('mysql', true)) : null], ['id' => $id]));
        });
    }
    public function archive(int $id, bool $reset): int
    {
        global $wpdb;
        $initial = $this->get($id);
        return $this->transaction(function () use ($id, $reset, $initial, $wpdb) {
            $scenario = (new ScenarioRepository())->get((int) $initial['scenario_id'], true);
            $a = $this->get($id, true);
            PermissionService::require(PermissionService::observe($a));
            PermissionService::require(PermissionService::scenario($scenario));
            if (Assessment::data($a)) { throw new \DomainException('Utilisez une nouvelle tentative autorisée ou la réouverture de l’évaluation.'); }
            if ($a['status'] === 'archived') { throw new \DomainException('Tentative déjà archivée.'); }
            ScenarioRepository::check($wpdb->update(ScenarioRepository::table('attempts'), ['status' => 'archived', 'revision' => (int) $a['revision'] + 1], ['id' => $id]));
            (new EventRepository())->add($a, '', ['type' => 'archived', 'text' => $reset ? 'Archivée pour recommencer.' : 'Archivée.', 'actor_id' => get_current_user_id()]);
            if (!$reset) { return $id; }
            return $this->create(['id' => $a['scenario_id'], 'owner_id' => $a['teacher_id'], 'definition' => json_decode($a['snapshot'], true)], (int) $a['assignment_id'], (int) $a['student_id']);
        });
    }
    private function transaction(callable $callback)
    {
        global $wpdb;
        ScenarioRepository::check($wpdb->query('START TRANSACTION'));
        try { $result = $callback(); ScenarioRepository::check($wpdb->query('COMMIT')); return $result; }
        catch (\Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
    }
}
