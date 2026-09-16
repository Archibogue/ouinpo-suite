<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;

use Ouinpo\Suite\Core\ClassSubgroups;
use Ouinpo\Suite\Core\Capabilities;
use Ouinpo\Suite\Core\Privacy\LearningAudiencePolicy;
use Ouinpo\Suite\Core\Privacy\LearningDataPolicy;
defined('ABSPATH') || exit;

final class AssignmentService
{
    public function get(int $id, bool $lock = false): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . ScenarioRepository::table('assignments') . ' WHERE id=%d' . ($lock ? ' FOR UPDATE' : ''), $id), ARRAY_A);
        if (!$row) { throw new \RuntimeException('Affectation introuvable.', 404); }
        return $row;
    }
    public function allows(array $a, int $user): bool
    {
        if (!(int) $a['active']) { return false; }
        if ($a['target_type'] === 'user') { return (int) $a['target_id'] === $user; }
        $class = (int) explode(':', $a['target_id'])[0];
        if (!LearningAudiencePolicy::isRosteredClassStudent($user, $class)) { return false; }
        return $a['target_type'] === 'class' || ClassSubgroups::allows($user, [$class], [$a['target_id']]);
    }
    public function save(int $scenarioId, string $type, string $target, bool $active): void
    {
        // Sharing the scenario lock with deletion prevents orphan assignments.
        ScenarioRepository::transaction(fn() => $this->saveLocked($scenarioId, $type, $target, $active));
    }
    private function saveLocked(int $scenarioId, string $type, string $target, bool $active): void
    {
        global $wpdb;
        $scenario = (new ScenarioRepository())->get($scenarioId, true);
        PermissionService::require(PermissionService::scenario($scenario));
        PermissionService::require(Capabilities::can(Capabilities::MANAGE_CLASSES) || PermissionService::all());
        if (!in_array($type, ['user','class','subgroup'], true)) { throw new \InvalidArgumentException('Cible invalide.'); }
        if (!$active) {
            // Revocation must still work if the target account or group was removed.
            ScenarioRepository::check($wpdb->update(ScenarioRepository::table('assignments'), ['active' => 0], ['scenario_id' => $scenarioId, 'target_type' => $type, 'target_id' => $target]));
            return;
        }
        if ($type === 'user') {
            $user = (int) $target;
            if (!ctype_digit($target) || !user_can($user, Capabilities::TICKET_PRACTICE) || !(new LearningDataPolicy())->canStoreLearningData($user)) { throw new \InvalidArgumentException('Étudiant non éligible au suivi.'); }
            $target = (string) $user;
        } else {
            $class = (int) explode(':', $target)[0];
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ouin_exo_groups WHERE id=%d", $class));
            if (!$exists) { throw new \InvalidArgumentException('Classe introuvable.'); }
            if ($type === 'subgroup') {
                $sub = explode(':', $target, 2)[1] ?? '';
                if (!isset(ClassSubgroups::all($class)[$sub])) { throw new \InvalidArgumentException('Sous-groupe introuvable.'); }
                $target = $class . ':' . $sub;
            } else { $target = (string) $class; }
        }
        ScenarioRepository::check($wpdb->query($wpdb->prepare('INSERT INTO ' . ScenarioRepository::table('assignments') .
            ' (scenario_id,target_type,target_id,created_by,active) VALUES (%d,%s,%s,%d,%d) ON DUPLICATE KEY UPDATE active=VALUES(active)',
            $scenarioId, $type, $target, get_current_user_id(), $active ? 1 : 0)));
    }
    public function listing(?int $scenarioId = null): array
    {
        global $wpdb;
        if ($scenarioId !== null) { PermissionService::require(PermissionService::scenario((new ScenarioRepository())->get($scenarioId))); }
        $where = $scenarioId !== null ? $wpdb->prepare('a.scenario_id=%d', $scenarioId) : "a.active=1 AND s.status='published'";
        $rows = $wpdb->get_results('SELECT a.*,s.title FROM ' . ScenarioRepository::table('assignments') . ' a JOIN ' . ScenarioRepository::table('scenarios') . " s ON s.id=a.scenario_id WHERE $where ORDER BY a.id DESC", ARRAY_A) ?: [];
        if ($scenarioId !== null) { return $rows; }
        return array_values(array_map(static fn($a) => array_intersect_key($a, array_flip(['id','scenario_id','title'])),
            array_filter($rows, fn($a) => $this->allows($a, get_current_user_id()))));
    }
    public function targets(string $search = ''): array
    {
        global $wpdb;
        PermissionService::require(PermissionService::manage() && (Capabilities::can(Capabilities::MANAGE_CLASSES) || PermissionService::all()));
        $args = ['number' => 100, 'capability' => Capabilities::TICKET_PRACTICE, 'fields' => ['ID','display_name']];
        if ($search !== '') { $args['search'] = '*' . $search . '*'; $args['search_columns'] = ['display_name','user_login']; }
        $users = get_users($args);
        $result = [];
        foreach ($users as $u) {
            if ((new LearningDataPolicy())->canStoreLearningData((int) $u->ID)) { $result[] = ['type' => 'user', 'id' => (string) $u->ID, 'label' => $u->display_name . ' (#' . $u->ID . ')']; }
        }
        $classes = $wpdb->get_results("SELECT id,label AS name FROM {$wpdb->prefix}ouin_exo_groups ORDER BY id DESC LIMIT 200", ARRAY_A) ?: [];
        foreach ($classes as $c) {
            $result[] = ['type' => 'class', 'id' => (string) $c['id'], 'label' => 'Classe : ' . $c['name']];
            foreach (ClassSubgroups::all((int) $c['id']) as $id => $group) { $result[] = ['type' => 'subgroup', 'id' => $c['id'] . ':' . $id, 'label' => $c['name'] . ' / ' . $group['label']]; }
        }
        return $result;
    }
}
