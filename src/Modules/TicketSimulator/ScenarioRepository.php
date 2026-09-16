<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class ScenarioRepository
{
    public static function table(string $suffix): string
    {
        global $wpdb;
        if (!in_array($suffix, ['scenarios','assignments','attempts','attempt_tickets','events'], true)) { throw new \LogicException('Unknown table'); }
        return $wpdb->prefix . 'ouinpo_ticket_' . $suffix;
    }
    public function get(int $id, bool $lock = false): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('scenarios') . ' WHERE id=%d' . ($lock ? ' FOR UPDATE' : ''), $id), ARRAY_A);
        if (!$row) { throw new \RuntimeException('Scénario introuvable.', 404); }
        $row['definition'] = json_decode($row['definition'], true);
        return $row;
    }
    public function listing(): array
    {
        global $wpdb;
        PermissionService::require(PermissionService::manage());
        $where = PermissionService::all() ? '1=1' : $wpdb->prepare('owner_id=%d', get_current_user_id());
        return $wpdb->get_results('SELECT id,title,status,revision FROM ' . self::table('scenarios') . " WHERE $where ORDER BY id DESC LIMIT 200", ARRAY_A) ?: [];
    }
    public function save(int $id, array $definition, string $status, int $revision): int
    {
        global $wpdb;
        PermissionService::require(PermissionService::manage());
        ScenarioValidator::validate($definition);
        if (!in_array($status, ['draft','published','archived'], true)) { throw new \InvalidArgumentException('Statut invalide.'); }
        $data = ['title' => $definition['title'], 'status' => $status, 'definition' => wp_json_encode($definition), 'updated_at' => current_time('mysql', true)];
        if ($id) {
            $row = $this->get($id); PermissionService::require(PermissionService::scenario($row));
            $data['revision'] = $revision + 1;
            $ok = $wpdb->update(self::table('scenarios'), $data, ['id' => $id, 'revision' => $revision]);
            if ($ok !== 1) { throw new \RuntimeException('Scénario modifié ailleurs. Rechargez avant de sauvegarder.', 409); }
        } else {
            $data['owner_id'] = get_current_user_id();
            self::check($wpdb->insert(self::table('scenarios'), $data)); $id = (int) $wpdb->insert_id;
        }
        return $id;
    }
    /** Explicit permanent deletion of a scenario and all of its execution data. */
    public function delete(int $id, int $revision): array
    {
        global $wpdb;
        return self::transaction(function () use ($id, $revision, $wpdb): array {
            $scenario = $this->get($id, true);
            PermissionService::require(PermissionService::scenario($scenario));
            if ((int) $scenario['revision'] !== $revision) {
                throw new \RuntimeException('Le scénario a changé. Rechargez-le avant de le supprimer.', 409);
            }

            // Lock attempts before their events: in-flight student writes finish first.
            self::check($wpdb->query($wpdb->prepare(
                'SELECT id FROM ' . self::table('attempts') . ' WHERE scenario_id=%d FOR UPDATE', $id
            )));
            $counts = [];
            foreach (['events', 'attempt_tickets'] as $table) {
                $counts[$table] = $wpdb->query($wpdb->prepare(
                    'DELETE FROM ' . self::table($table) . ' WHERE attempt_id IN (SELECT id FROM '
                    . self::table('attempts') . ' WHERE scenario_id=%d)', $id
                ));
                self::check($counts[$table]);
            }
            foreach (['attempts', 'assignments'] as $table) {
                $counts[$table] = $wpdb->delete(self::table($table), ['scenario_id' => $id], ['%d']);
                self::check($counts[$table]);
            }
            self::check($wpdb->delete(self::table('scenarios'), ['id' => $id], ['%d']));
            return ['deleted' => true, 'id' => $id, 'counts' => $counts];
        });
    }

    public static function transaction(callable $callback)
    {
        global $wpdb;
        self::check($wpdb->query('START TRANSACTION'));
        try {
            $result = $callback();
            self::check($wpdb->query('COMMIT'));
            return $result;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
    public static function check($result): void { if ($result === false) { throw new \RuntimeException('Échec de l’enregistrement.', 500); } }
}
