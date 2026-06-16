<?php

namespace Ouinpo\Suite\Core\School;

use Ouinpo\Suite\Core\Privacy\LearningDataPolicy;

defined('ABSPATH') || exit;

final class YearClosureExecutor
{
    private const LOCK_KEY = 'ouinpo_year_closure_lock';

    public function execute(int $fromYearId, array $options = []): array
    {
        global $wpdb;

        if (get_transient(self::LOCK_KEY)) {
            return ['ok' => false, 'error' => 'closure_already_running'];
        }

        set_transient(self::LOCK_KEY, '1', 15 * MINUTE_IN_SECONDS);

        $logger = new YearClosureLogger();
        $planner = new YearClosurePlanner();
        $plan = $planner->plan($fromYearId, $options, false);
        $summary = (array) ($plan['summary'] ?? []);
        $fromYear = (array) ($summary['from_year'] ?? []);
        $toYear = (array) ($summary['to_year'] ?? []);
        $runId = $logger->startRun((int) ($fromYear['id'] ?? $fromYearId), !empty($toYear['id']) ? (int) $toYear['id'] : null, 'execute', $options, $summary);
        $inTransaction = false;

        try {
            $wpdb->query('START TRANSACTION');
            $inTransaction = true;

            $tables = $this->tables();
            $toYearId = $this->ensureTargetYear($toYear);
            $this->activateYear((int) $fromYear['id'], $toYearId);
            $logger->addItem($runId, 'year', 'academic_year', $toYearId, 'activate', 'done', 'Annee cible activee.');

            foreach ((array) ($summary['class_plans'] ?? []) as $classPlan) {
                $sourceGroupId = (int) ($classPlan['source_group_id'] ?? 0);

                if ($sourceGroupId > 0) {
                    $source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['groups']} WHERE id = %d", $sourceGroupId), ARRAY_A);
                    if ($source) {
                        $targetLevelIds = $this->targetLevelIdsForSourceGroup($sourceGroupId, (int) ($source['school_level_id'] ?? 0));
                        foreach ($targetLevelIds as $toLevelId) {
                            $targetGroupId = $this->ensureTargetGroup($source, $toYearId, $toLevelId);
                            $this->copyStudentMembers($sourceGroupId, $targetGroupId, $toLevelId, (int) ($source['school_level_id'] ?? 0));
                            $this->carryProjects((int) ($fromYear['id'] ?? 0), $toYearId, $sourceGroupId, $targetGroupId);
                            $logger->addItem($runId, 'groups', 'group', $targetGroupId, 'create_or_reuse', 'done', 'Classe cible preparee.');
                        }
                    }
                }

                if ($sourceGroupId > 0) {
                    $wpdb->update(
                        $tables['groups'],
                        ['status' => 'archived', 'closed_at' => current_time('mysql'), 'closed_by' => get_current_user_id()],
                        ['id' => $sourceGroupId],
                        ['%s', '%s', '%d'],
                        ['%d']
                    );
                    $logger->addItem($runId, 'groups', 'group', $sourceGroupId, 'archive', 'done', 'Classe source archivee logiquement.');
                }
            }

            $this->convertAlumniWithoutTarget((int) ($fromYear['id'] ?? 0), $runId, $logger);
            $logger->addItem($runId, 'gdpr', 'learning_data', null, 'purge', 'planned', 'Non execute dans cette version : purge RGPD a implementer apres validation.');
            $wpdb->query('COMMIT');
            $inTransaction = false;
            $logger->finishRun($runId, 'done', $summary);
            delete_transient(self::LOCK_KEY);

            return ['ok' => true, 'run_id' => $runId, 'summary' => $summary];
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $wpdb->query('ROLLBACK');
            }
            delete_transient(self::LOCK_KEY);
            $logger->finishRun($runId, 'failed', $summary, $e->getMessage());

            return ['ok' => false, 'run_id' => $runId, 'error' => $e->getMessage()];
        }
    }

    private function ensureTargetYear(array $toYear): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_academic_years';
        if (!empty($toYear['id'])) {
            return (int) $toYear['id'];
        }

        $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", (string) $toYear['slug']));
        if ($existing > 0) {
            return $existing;
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'slug' => (string) $toYear['slug'],
                'starts_on' => (string) $toYear['starts_on'],
                'ends_on' => (string) $toYear['ends_on'],
                'is_active' => 0,
                'status' => 'draft',
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );

        if (!$inserted || (int) $wpdb->insert_id <= 0) {
            throw new \RuntimeException('Creation de l annee cible impossible.');
        }

        return (int) $wpdb->insert_id;
    }

    private function activateYear(int $fromYearId, int $toYearId): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_academic_years';
        $wpdb->query("UPDATE {$table} SET is_active = 0 WHERE is_active <> 0");
        $wpdb->update($table, ['is_active' => 1, 'status' => 'active'], ['id' => $toYearId], ['%d', '%s'], ['%d']);
        if ($fromYearId > 0) {
            $wpdb->update(
                $table,
                ['status' => 'closed', 'closed_at' => current_time('mysql'), 'closed_by' => get_current_user_id()],
                ['id' => $fromYearId],
                ['%s', '%s', '%d'],
                ['%d']
            );
        }
        update_option('ouin_exo_active_year_id', $toYearId, false);
    }

    private function ensureTargetGroup(array $source, int $toYearId, int $toLevelId): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_groups';
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE year_id = %d AND source_group_id = %d AND school_level_id = %d LIMIT 1",
            $toYearId,
            (int) $source['id'],
            $toLevelId
        ));
        if ($existing > 0) {
            return $existing;
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'label' => (string) $source['label'],
                'year_id' => $toYearId,
                'school_level_id' => $toLevelId,
                'created_at' => current_time('mysql'),
                'status' => 'active',
                'source_group_id' => (int) $source['id'],
            ],
            ['%s', '%d', '%d', '%s', '%s', '%d']
        );

        if (!$inserted || (int) $wpdb->insert_id <= 0) {
            throw new \RuntimeException('Creation de la classe cible impossible.');
        }

        return (int) $wpdb->insert_id;
    }

    private function copyStudentMembers(int $sourceGroupId, int $targetGroupId, int $targetLevelId, int $defaultLevelId): void
    {
        global $wpdb;

        if ($sourceGroupId <= 0 || $targetGroupId <= 0) {
            return;
        }

        $table = $wpdb->prefix . 'ouin_exo_group_members';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, school_level_id_override FROM {$table} WHERE group_id = %d AND role = 'student'",
            $sourceGroupId
        ), ARRAY_A) ?: [];

        $policy = new LearningDataPolicy();
        $resolver = new CycleTransitionResolver(new CycleRepository());
        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            if (!$policy->canBeAssignedToClass($userId)) {
                continue;
            }
            $effectiveLevelId = !empty($row['school_level_id_override']) ? (int) $row['school_level_id_override'] : $defaultLevelId;
            $transition = $effectiveLevelId > 0 ? $resolver->resolveDefaultNextLevel($effectiveLevelId) : ['to_level_id' => null];
            if ((int) ($transition['to_level_id'] ?? 0) !== $targetLevelId) {
                continue;
            }
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table} (group_id, user_id, role, school_level_id_override) VALUES (%d, %d, 'student', NULL)",
                $targetGroupId,
                $userId
            ));
        }
    }

    private function targetLevelIdsForSourceGroup(int $sourceGroupId, int $defaultLevelId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_group_members';
        $resolver = new CycleTransitionResolver(new CycleRepository());
        $ids = [];

        if ($defaultLevelId > 0) {
            $transition = $resolver->resolveDefaultNextLevel($defaultLevelId);
            if (!empty($transition['to_level_id'])) {
                $ids[(int) $transition['to_level_id']] = true;
            }
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT school_level_id_override FROM {$table} WHERE group_id = %d AND role = 'student'",
            $sourceGroupId
        ), ARRAY_A) ?: [];

        foreach ($rows as $row) {
            $effectiveLevelId = !empty($row['school_level_id_override']) ? (int) $row['school_level_id_override'] : $defaultLevelId;
            if ($effectiveLevelId <= 0) {
                continue;
            }
            $transition = $resolver->resolveDefaultNextLevel($effectiveLevelId);
            if (!empty($transition['to_level_id'])) {
                $ids[(int) $transition['to_level_id']] = true;
            }
        }

        return array_keys($ids);
    }

    private function convertAlumniWithoutTarget(int $fromYearId, int $runId, YearClosureLogger $logger): void
    {
        global $wpdb;

        if ($fromYearId <= 0) {
            return;
        }

        $tables = $this->tables();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT gm.user_id, COALESCE(gm.school_level_id_override, g.school_level_id) AS effective_level_id
             FROM {$tables['members']} gm
             JOIN {$tables['groups']} g ON g.id = gm.group_id
             WHERE g.year_id = %d
               AND gm.role = 'student'",
            $fromYearId
        ), ARRAY_A) ?: [];

        $policy = new LearningDataPolicy();
        $resolver = new CycleTransitionResolver(new CycleRepository());
        foreach ($rows as $row) {
            $effectiveLevelId = (int) ($row['effective_level_id'] ?? 0);
            $transition = $effectiveLevelId > 0 ? $resolver->resolveDefaultNextLevel($effectiveLevelId) : ['to_level_id' => null];
            if (!empty($transition['to_level_id'])) {
                continue;
            }

            $userId = (int) $row['user_id'];
            $user = get_user_by('id', $userId);
            if (!$user) {
                continue;
            }
            $user->remove_role('ouinpo_student');
            $user->add_role('ouinpo_alumni');
            $policy->disableTrackingForAlumni($userId);
            $logger->addItem($runId, 'alumni', 'user', $userId, 'convert', 'done', 'Conversion alumni sans suppression de donnees.');
        }
    }

    private function carryProjects(int $fromYearId, int $toYearId, int $fromGroupId, int $toGroupId): void
    {
        $repository = new \Ouinpo\Suite\Modules\Projects\Repository();
        if (method_exists($repository, 'markProjectsCarriedOverForGroup')) {
            $repository->markProjectsCarriedOverForGroup($fromYearId, $toYearId, $fromGroupId, $toGroupId);
        }
    }

    private function tables(): array
    {
        global $wpdb;

        return [
            'years' => $wpdb->prefix . 'ouin_exo_academic_years',
            'groups' => $wpdb->prefix . 'ouin_exo_groups',
            'members' => $wpdb->prefix . 'ouin_exo_group_members',
            'levels' => $wpdb->prefix . 'ouin_exo_school_levels',
        ];
    }
}
