<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Pure state transitions; persistence and WordPress permissions live outside this class. */
final class SimulationEngine
{
    private TestEngineInterface $tests;
    public function __construct(?TestEngineInterface $tests = null) { $this->tests = $tests ?? new SimulatedTestEngine(); }
    public function available(array $action, array $state, ?array $ticket = null): bool
    {
        $to = $this->target($action);
        if ($ticket !== null && $to !== '' && $to !== $state['status'] && !in_array($to, $ticket['transitions'][$state['status']] ?? [], true)) { return false; }
        return empty($state['pending'])
            && (empty($action['code_resource']) || in_array($action['code_resource'], $state['visible_resources'], true))
            && (empty($action['states']) || in_array($state['status'], $action['states'], true))
            && !array_diff($action['requires'] ?? [], $state['done'])
            && (!in_array($action['id'], $state['done'], true) || !empty($action['repeatable']))
            && ($state['status'] !== 'closed');
    }
    public function perform(array $ticket, array $state, string $actionId, array $input): array
    {
        $events = [];
        if ($actionId === '__reply') {
            $pending = $state['pending'] ?? null;
            if (!$pending) { throw new \DomainException('Aucune réponse en attente.'); }
            $this->transition($ticket, $state, $pending['return_status'], $events);
            $events[] = ['type' => $pending['type'], 'text' => $pending['text'], 'speaker' => $pending['speaker']];
            $state['visible_resources'] = array_values(array_unique(array_merge($state['visible_resources'], $pending['reveal'])));
            $state['pending'] = null;
            if (!empty($pending['restore_assignee'])) { $state['fields']['assignee'] = 'Vous'; }
            return [$state, $events];
        }
        $actions = TicketScenario::index($ticket['actions']);
        $action = $actions[$actionId] ?? null;
        if (!$action || !$this->available($action, $state, $ticket)) { throw new \DomainException('Action indisponible ou prérequis non remplis.'); }
        $type = $action['type'];
        $message = trim((string) ($input['message'] ?? ''));
        if ((in_array($type, ['specialist','transfer','escalate','reassign','communication'], true) || !empty($action['requires_message'])) && $message === '') {
            throw new \DomainException('Rédigez votre demande ou votre message.');
        }
        $result = $this->tests->run($action, $state);
        $state['awarded_actions'] ??= $state['done'];
        $first = !in_array($actionId, $state['awarded_actions'], true);
        $state['awarded_actions'] = array_values(array_unique(array_merge($state['awarded_actions'], [$actionId])));
        $state['done'] = array_values(array_unique(array_merge($state['done'], [$actionId])));
        $state['minutes'] += $action['cost'] ?? 0;
        if ($first) { $state['score'] += $action['score'] ?? 0; }
        $events[] = ['type' => 'action', 'action_id' => $actionId, 'action_type' => $type, 'text' => $action['label'], 'cost' => $action['cost'] ?? 0];
        if ($message !== '') { $events[] = ['type' => in_array($type, ['specialist','transfer','escalate','reassign'], true) ? 'specialist_request' : 'user_message', 'text' => $message]; }
        $to = $this->target($action);
        if ($type === 'take') { $to = 'accepted'; $state['fields']['assignee'] = 'Vous'; }
        if ($type === 'resolve') { $to = 'resolved'; }
        if ($type === 'close') { $to = 'closed'; }
        if ($to !== '') { $this->transition($ticket, $state, $to, $events); }
        if (in_array($type, ['question','specialist','transfer','escalate','reassign'], true)) {
            $return = !empty($action['return_status']) ? $action['return_status'] : ($type === 'reassign' ? $state['status'] : 'diagnosing');
            // Validate the return before committing the outgoing request.
            $check = $state; $ignore = [];
            $this->transition($ticket, $check, $return, $ignore);
            $state['pending'] = ['text' => $result['text'], 'type' => $type === 'question' ? 'user_reply' : 'specialist_reply',
                'speaker' => $action['specialist_id'] ?? 'requester', 'return_status' => $return, 'reveal' => $action['reveal'] ?? [],
                'restore_assignee' => in_array($type, ['transfer','escalate'], true)];
            if (in_array($type, ['transfer','escalate','reassign'], true)) { $state['fields']['assignee'] = $action['specialist_id'] ?? 'Spécialiste'; }
        } else {
            $state['visible_resources'] = array_values(array_unique(array_merge($state['visible_resources'], $action['reveal'] ?? [])));
            if ($result['text'] !== '') { $events[] = ['type' => $type === 'test' ? 'test' : 'result', 'text' => $result['text'], 'outcome' => $result['outcome']]; }
        }
        if ($type === 'test') { $state['tests'][$actionId] = $result; }
        if ($type === 'resolve') {
            $resolution = [];
            foreach (['cause','solution','tests','result','message'] as $field) {
                $value = trim((string) ($input[$field] ?? ''));
                if ($value === '') { throw new \DomainException('Complétez les cinq champs du compte rendu de résolution.'); }
                $resolution[$field] = $value;
            }
            $met = !array_diff($ticket['resolution_requires'] ?? [], $state['done']);
            foreach ($ticket['resolution_tests'] ?? [] as $testId) {
                $met = $met && (($state['tests'][$testId]['outcome'] ?? '') === 'success');
            }
            $state['resolution'] = $resolution;
            // Evidence checks are teacher-only; free prose is deliberately not auto-graded.
            $state['review'] = ['required_actions_met' => $met, 'expected_fields_match' => !array_diff_assoc($ticket['expected'], $state['fields'])];
            $events[] = ['type' => 'resolution', 'text' => $resolution['cause'] . "\n" . $resolution['solution'] . "\nTests : " . $resolution['tests'] . "\nRésultat : " . $resolution['result']];
            $events[] = ['type' => 'user_message', 'text' => $resolution['message']];
            if (!$met && ($ticket['bad_resolution'] ?? 'accept') === 'reopen') {
                $this->transition($ticket, $state, 'reopened', $events);
                $events[] = ['type' => 'user_reply', 'text' => $ticket['bad_resolution_message'] ?? 'Le problème persiste.'];
            }
        }
        return [$state, $events];
    }
    private function transition(array $ticket, array &$state, string $to, array &$events): void
    {
        $from = $state['status'];
        if ($to === $from) { return; }
        if (!in_array($to, $ticket['transitions'][$from] ?? [], true)) { throw new \DomainException('Transition non autorisée : ' . $from . ' → ' . $to); }
        $state['status'] = $to;
        $events[] = ['type' => 'status', 'text' => $from . ' → ' . $to];
    }
    private function target(array $action): string
    {
        $fixed = ['take'=>'accepted','resolve'=>'resolved','close'=>'closed'];
        $defaults = ['question'=>'waiting_user','specialist'=>'waiting_specialist','transfer'=>'waiting_specialist','escalate'=>'escalated','reassign'=>'escalated'];
        return $fixed[$action['type']] ?? (!empty($action['to_status']) ? $action['to_status'] : ($defaults[$action['type']] ?? ''));
    }
}
