<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectStatsService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function getProjectSummary(int $projectId): ?array
    {
        global $wpdb;

        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*,
                COALESCE(m.members_count, 0) AS members_count,
                COALESCE(t.tasks_count, 0) AS tasks_count,
                COALESCE(t.open_tasks_count, 0) AS open_tasks_count,
                COALESCE(t.done_tasks_count, 0) AS done_tasks_count,
                COALESCE(d.deliverables_count, 0) AS deliverables_count,
                COALESCE(d.validated_deliverables_count, 0) AS validated_deliverables_count,
                COALESCE(d.overdue_deliverables_count, 0) AS overdue_deliverables_count,
                l.last_log_at AS last_log_at,
                e.last_evidence_at AS last_evidence_at
             FROM {$this->repository->projectsTable()} p
             LEFT JOIN (
                SELECT COUNT(*) AS members_count
                FROM {$this->repository->table('members')}
                WHERE project_id = %d
             ) m ON 1 = 1
             LEFT JOIN (
                SELECT
                    SUM(CASE WHEN status <> 'archived' THEN 1 ELSE 0 END) AS tasks_count,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_tasks_count,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done_tasks_count
                FROM {$this->repository->table('tasks')}
                WHERE project_id = %d
             ) t ON 1 = 1
             LEFT JOIN (
                SELECT
                    COUNT(*) AS deliverables_count,
                    SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) AS validated_deliverables_count,
                    SUM(CASE WHEN status NOT IN ('validated', 'rejected') AND due_date IS NOT NULL AND due_date < %s THEN 1 ELSE 0 END) AS overdue_deliverables_count
                FROM {$this->repository->table('deliverables')}
                WHERE project_id = %d
             ) d ON 1 = 1
             LEFT JOIN (
                SELECT MAX(created_at) AS last_log_at
                FROM {$this->repository->table('logs')}
                WHERE project_id = %d
             ) l ON 1 = 1
             LEFT JOIN (
                SELECT MAX(created_at) AS last_evidence_at
                FROM {$this->repository->table('evidence')}
                WHERE project_id = %d
             ) e ON 1 = 1
             WHERE p.id = %d
             LIMIT 1",
            $projectId,
            $projectId,
            current_time('Y-m-d'),
            $projectId,
            $projectId,
            $projectId,
            $projectId
        ), ARRAY_A);

        if (!is_array($project)) {
            return null;
        }

        foreach ([
            'members_count',
            'tasks_count',
            'open_tasks_count',
            'done_tasks_count',
            'deliverables_count',
            'validated_deliverables_count',
            'overdue_deliverables_count',
        ] as $key) {
            $project[$key] = (int) $project[$key];
        }

        $project['last_log_at'] = (string) $project['last_log_at'];
        $project['last_evidence_at'] = (string) $project['last_evidence_at'];

        return $project;
    }
}
