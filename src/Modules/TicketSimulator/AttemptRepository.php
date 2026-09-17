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
        $rows = $wpdb->get_results('SELECT id,scenario_id,student_id,teacher_id,status,started_at,ended_at FROM ' . ScenarioRepository::table('attempts') . " WHERE $where ORDER BY id DESC LIMIT 200", ARRAY_A) ?: [];
        $offset = AttemptNumber::offset();
        foreach ($rows as &$row) { $row['number'] = max(1, (int) $row['id'] - $offset); }
        unset($row);
        return $rows;
    }
    public function start(int $assignmentId): int
    {
        global $wpdb;
        PermissionService::require(PermissionService::practice());
        $assignment = (new AssignmentService())->get($assignmentId);
        return $this->transaction(function () use ($assignmentId, $assignment, $wpdb) {
            // Always lock scenario before assignment/attempt, matching deletion.
            $s = (new ScenarioRepository())->get((int) $assignment['scenario_id'], true);
            $a = (new AssignmentService())->get($assignmentId, true);
            PermissionService::require((new AssignmentService())->allows($a, get_current_user_id()));
            PermissionService::require($s['status'] === 'published');
            $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . ScenarioRepository::table('attempts') . " WHERE scenario_id=%d AND student_id=%d AND status<>'archived' ORDER BY id DESC LIMIT 1 FOR UPDATE", $s['id'], get_current_user_id()));
            if ($existing) { return (int) $existing; }
            return $this->create($s, $assignmentId, get_current_user_id());
        });
    }
    private function create(array $s, int $assignmentId, int $student): int
    {
        global $wpdb;
        $data = ['scenario_id' => $s['id'], 'assignment_id' => $assignmentId, 'student_id' => $student,
            'teacher_id' => $s['owner_id'], 'snapshot' => wp_json_encode($s['definition']), 'started_at' => current_time('mysql', true)];
        ScenarioRepository::check($wpdb->insert(ScenarioRepository::table('attempts'), $data));
        $id = (int) $wpdb->insert_id;
        foreach ($s['definition']['tickets'] as $ticket) {
            ScenarioRepository::check($wpdb->insert(ScenarioRepository::table('attempt_tickets'), ['attempt_id' => $id, 'ticket_key' => $ticket['id'], 'state' => wp_json_encode(ScenarioAttempt::initial($ticket))]));
            (new EventRepository())->add(['id' => $id, 'student_id' => $student], $ticket['id'], ['type' => 'created', 'text' => 'Ticket reçu.']);
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
            if ((int) $a['revision'] !== $revision) { throw new \RuntimeException('La tentative a changé. Rechargez-la avant de réessayer.', 409); }
            $snapshot = json_decode($a['snapshot'], true);
            $ticket = TicketScenario::index($snapshot['tickets'])[$ticketId] ?? null;
            $state = $this->states($id)[$ticketId] ?? null;
            if (!$ticket || !$state) { throw new \RuntimeException('Ticket introuvable.', 404); }
            $events = [];
            if ($operation === 'reset_ticket') {
                if (($input['confirm_reset'] ?? false) !== true) { throw new \InvalidArgumentException('Confirmation de remise à zéro requise.'); }
                $state = ScenarioAttempt::initial($ticket);
                $events[] = ['type'=>'ticket_reset', 'text'=>'Ticket remis à son état initial. Les traces précédentes appartiennent au traitement antérieur ; qualification, code, tests, temps et résolution ont été réinitialisés.'];
            } elseif ($operation === 'action') {
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
