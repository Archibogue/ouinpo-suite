<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectJournalService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function getComments(int $taskId): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name
             FROM {$this->repository->table('task_comments')} c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id
             WHERE c.task_id = %d
             ORDER BY c.created_at ASC, c.id ASC",
            $taskId
        ), ARRAY_A) ?: [];
    }

    public function addComment(int $taskId, int $userId, string $comment): int
    {
        global $wpdb;

        $comment = Repository::cleanLongText($comment);
        if ($taskId <= 0 || $userId <= 0 || $comment === '') {
            return 0;
        }

        $inserted = $wpdb->insert(
            $this->repository->table('task_comments'),
            [
                'task_id' => $taskId,
                'user_id' => $userId,
                'comment' => $comment,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public function getLogs(int $projectId): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name
             FROM {$this->repository->table('logs')} l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
             WHERE l.project_id = %d
             ORDER BY l.created_at DESC, l.id DESC",
            $projectId
        ), ARRAY_A) ?: [];
    }

    public function addLog(int $projectId, int $userId, array $data): int
    {
        global $wpdb;

        $workDone = Repository::cleanLongText($data['work_done'] ?? '');
        if ($projectId <= 0 || $userId <= 0 || $workDone === '') {
            return 0;
        }

        $inserted = $wpdb->insert(
            $this->repository->table('logs'),
            [
                'project_id' => $projectId,
                'user_id' => $userId,
                'work_done' => $workDone,
                'blockers' => Repository::cleanLongText($data['blockers'] ?? ''),
                'decision_taken' => Repository::cleanLongText($data['decision_taken'] ?? ''),
                'next_step' => Repository::cleanLongText($data['next_step'] ?? ''),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }
}
