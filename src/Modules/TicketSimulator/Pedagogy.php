<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Optional rules are read from the attempt snapshot, never from the live model. */
final class Pedagogy
{
    public const NATURES = ['incident','service','evolution'];
    public const QUALIFICATION = ['nature','impact','urgency','priority','priority_justification'];
    public const TRACES = ['context','information','initial_response','orientation','symptom','hypothesis','observed','correction','verification','proof','reflection'];
    public static function traceMissing(array $scenario, array $ticket, array $state): array
    {
        $objective = $scenario['completion_status'] ?? 'resolved';
        $required = array_unique(array_merge($ticket['trace_required'] ?? [], in_array($objective,['qualified','oriented'],true) ? ['context','information','initial_response'] : []));
        if ($objective === 'oriented') { $required[]='orientation'; }
        $missing=array_values(array_filter($required, static fn($key)=>trim($state['evidence'][$key] ?? '') === ''));
        if (TicketIntake::enabled($ticket) && empty($state['intake'])) { $missing[]='Fiche de demande'; }
        return $missing;
    }
    public static function missing(array $ticket, array $state): array
    {
        return array_values(array_filter($ticket['qualification_required'] ?? [], static function ($key) use ($state): bool {
            $value = trim((string) ($state['fields'][$key] ?? ''));
            return $value === '' || preg_match('/^(?:à\s+qualifier|a\s+qualifier|choisir(?:…|\.{3})?)$/iu', $value)
                || ($key === 'nature' && !in_array($value, self::NATURES, true));
        }));
    }
    public static function evidence(array $ticket, array $state): bool
    {
        if (array_diff($ticket['resolution_requires'] ?? [], $state['done'])) { return false; }
        foreach ($ticket['resolution_tests'] ?? [] as $id) {
            if (($state['tests'][$id]['outcome'] ?? '') !== 'success') { return false; }
        }
        return true;
    }
    public static function validation(array $ticket): bool { return !empty($ticket['requester_validation']['enabled']); }
    public static function finished(array $scenario, array $states): bool
    {
        $allowed = ($scenario['completion_status'] ?? 'resolved') === 'closed' ? ['closed'] : ['resolved','closed'];
        foreach ($scenario['tickets'] as $ticket) {
            if (!empty($ticket['optional'])) { continue; }
            if (in_array($scenario['completion_status'] ?? '', ['qualified','oriented'], true)) {
                $state=$states[$ticket['id']] ?? [];
                if (empty($state['exercise_completed']) || self::missing($ticket,$state) || self::traceMissing($scenario,$ticket,$state)) { return false; }
                continue;
            }
            if (!in_array($states[$ticket['id']]['status'] ?? '', $allowed, true)) { return false; }
            if (self::traceMissing($scenario,$ticket,$states[$ticket['id']] ?? [])) { return false; }
        }
        return true;
    }
}
