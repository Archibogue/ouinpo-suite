<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Private scratch work, separate from events, assessment copies and simulation state. */
final class AttemptDrafts
{
    public static function data(array $attempt): array
    {
        $data = json_decode($attempt['drafts'] ?? '', true) ?: [];
        return ['revision' => (int) ($data['revision'] ?? 0), 'values' => $data['values'] ?? []];
    }

    public static function save(int $id, array $input): array
    {
        return ScenarioRepository::transaction(static function () use ($id, $input) {
            $a = (new AttemptRepository())->get($id, true);
            PermissionService::require(PermissionService::edit($a));
            $current = self::data($a);
            if (($input['revision'] ?? null) !== $current['revision']) {
                throw new \RuntimeException('Les brouillons ont changé dans une autre fenêtre. Copiez vos saisies avant de recharger la page.', 409);
            }
            $values = $input['values'] ?? null;
            if (!is_array($values) || count($values) > 500) { throw new \InvalidArgumentException('Brouillons invalides.'); }
            $tickets = TicketScenario::index(json_decode($a['snapshot'], true)['tickets']);
            foreach ($values as $key => $value) {
                $parts = explode(':', (string) $key);
                if (strlen((string) $key) > 240 || !isset($tickets[$parts[0]]) ||
                    !preg_match('/^[a-zA-Z0-9_-]+:(?:intake|qualify|notes|dialogue|evidence|code|action):[a-zA-Z0-9_:-]+$/D', (string) $key) ||
                    !is_string($value) || str_contains($value, "\0") || strlen($value) > ($parts[1] === 'code' ? 100000 : 10000)) {
                    throw new \InvalidArgumentException('Champ de brouillon invalide ou trop volumineux.');
                }
            }
            if (strlen(wp_json_encode($values)) > 1000000) { throw new \InvalidArgumentException('Brouillons trop volumineux (1 Mo maximum).'); }
            $next = ['revision' => $current['revision'] + 1, 'values' => $values];
            self::write($id, $next);
            return $next;
        });
    }

    /** Called under the attempt lock, in the same transaction as the validated action. */
    public static function consume(array $a, string $ticket, string $operation, array $input): void
    {
        $d = self::data($a);
        $original = $d['values'];
        $prefix = ['intake'=>'intake:', 'qualify'=>'qualify:', 'note'=>'notes:', 'dialogue'=>'dialogue:', 'evidence'=>'evidence:'][$operation] ?? null;
        if ($operation === 'action') { $prefix = 'action:'.$input['action_id'].':'; }
        foreach ($d['values'] as $key => $value) {
            if (!str_starts_with($key, $ticket.':')) { continue; }
            if ($operation === 'reset_ticket') { unset($d['values'][$key]); continue; }
            if ($operation === 'code' && $key === $ticket.':code:'.$input['resource_id'] && $value === $input['content']) { unset($d['values'][$key]); }
            if ($prefix !== null && str_starts_with($key, $ticket.':'.$prefix)) {
                $field = substr($key, strlen($ticket.':'.$prefix));
                $normalized = sanitize_textarea_field($value);
                if ($operation === 'dialogue' && $field === 'message') { $normalized = trim($normalized); }
                if (isset($input[$field]) && $normalized === $input[$field]) { unset($d['values'][$key]); }
            }
        }
        if ($d['values'] !== $original) { $d['revision']++; self::write((int) $a['id'], $d); }
    }

    private static function write(int $id, array $data): void
    {
        global $wpdb;
        ScenarioRepository::check($wpdb->update(ScenarioRepository::table('attempts'), ['drafts' => wp_json_encode($data)], ['id' => $id]));
    }
}
