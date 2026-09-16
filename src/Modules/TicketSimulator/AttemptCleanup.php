<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class AttemptCleanup
{
    public static function deleteStudent(int $studentId): array
    {
        PermissionService::require(PermissionService::all());
        if ($studentId < 1) { throw new \InvalidArgumentException('Élève invalide.'); }
        return ScenarioRepository::transaction(static function () use ($studentId): array {
            global $wpdb;
            ScenarioRepository::check($wpdb->query('SELECT id FROM ' . ScenarioRepository::table('scenarios') . ' ORDER BY id FOR UPDATE'));
            ScenarioRepository::check($wpdb->query($wpdb->prepare('SELECT id FROM ' . ScenarioRepository::table('attempts') . ' WHERE student_id=%d ORDER BY id FOR UPDATE', $studentId)));
            $counts = [];
            foreach (['events','attempt_tickets'] as $table) {
                $counts[$table] = $wpdb->query($wpdb->prepare('DELETE FROM ' . ScenarioRepository::table($table) . ' WHERE attempt_id IN (SELECT id FROM ' . ScenarioRepository::table('attempts') . ' WHERE student_id=%d)', $studentId));
                ScenarioRepository::check($counts[$table]);
            }
            $counts['attempts'] = $wpdb->delete(ScenarioRepository::table('attempts'), ['student_id'=>$studentId], ['%d']);
            ScenarioRepository::check($counts['attempts']);
            return ['deleted'=>true, 'student_id'=>$studentId, 'counts'=>$counts];
        });
    }
    public static function deleteAll(): array
    {
        PermissionService::require(PermissionService::all());
        return ScenarioRepository::transaction(static function (): array {
            global $wpdb;
            // Match start/reset/delete lock order so concurrent writes finish safely.
            foreach (['scenarios', 'attempts'] as $table) {
                ScenarioRepository::check($wpdb->query('SELECT id FROM ' . ScenarioRepository::table($table) . ' ORDER BY id FOR UPDATE'));
            }
            $counts = [];
            foreach (['events', 'attempt_tickets', 'attempts'] as $table) {
                $counts[$table] = $wpdb->query('DELETE FROM ' . ScenarioRepository::table($table));
                ScenarioRepository::check($counts[$table]);
            }
            // Do not reset identifiers: stale tabs must never address a new attempt.
            AttemptNumber::reset();
            return ['deleted'=>true, 'counts'=>$counts];
        });
    }
}
