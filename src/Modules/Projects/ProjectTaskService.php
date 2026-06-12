<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectTaskService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function createTask(int $projectId, array $data, int $userId): int
    {
        global $wpdb;

        $title = Repository::cleanTitle($data['title'] ?? '');
        if ($title === '') {
            return 0;
        }

        $columnId = (int) ($data['column_id'] ?? 0);
        if ($columnId <= 0 || !$this->repository->columnBelongsToProject($columnId, $projectId)) {
            $columnId = $this->repository->getFirstColumnId($projectId);
        }

        $position = $this->nextTaskPosition($columnId);

        $assignedUserId = Repository::cleanNullableId($data['assigned_user_id'] ?? null);
        if ($assignedUserId !== null && !$this->repository->isProjectMember($projectId, $assignedUserId)) {
            $assignedUserId = null;
        }

        $inserted = $wpdb->insert(
            $this->repository->table('tasks'),
            [
                'project_id' => $projectId,
                'column_id' => $columnId,
                'title' => $title,
                'description' => Repository::cleanLongText($data['description'] ?? ''),
                'assigned_user_id' => $assignedUserId,
                'priority' => Repository::cleanPriority($data['priority'] ?? 'normal'),
                'due_date' => Repository::cleanDate($data['due_date'] ?? ''),
                'position' => $position,
                'status' => Repository::cleanTaskStatus($data['status'] ?? 'open'),
                'created_by' => $userId,
                'created_at' => current_time('mysql'),
                'updated_at' => null,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public function updateTask(int $taskId, array $data): bool
    {
        global $wpdb;

        $task = $this->getTask($taskId);
        if (!$task) {
            return false;
        }

        $updates = [];
        $formats = [];

        if (array_key_exists('title', $data)) {
            $title = Repository::cleanTitle($data['title']);
            if ($title !== '') {
                $updates['title'] = $title;
                $formats[] = '%s';
            }
        }

        if (array_key_exists('description', $data)) {
            $updates['description'] = Repository::cleanLongText($data['description']);
            $formats[] = '%s';
        }

        if (array_key_exists('assigned_user_id', $data)) {
            $assignedUserId = Repository::cleanNullableId($data['assigned_user_id']);
            if ($assignedUserId !== null && !$this->repository->isProjectMember((int) $task['project_id'], $assignedUserId)) {
                $assignedUserId = null;
            }
            $updates['assigned_user_id'] = $assignedUserId;
            $formats[] = '%d';
        }

        if (array_key_exists('priority', $data)) {
            $updates['priority'] = Repository::cleanPriority($data['priority']);
            $formats[] = '%s';
        }

        if (array_key_exists('due_date', $data)) {
            $updates['due_date'] = Repository::cleanDate($data['due_date']);
            $formats[] = '%s';
        }

        if (array_key_exists('status', $data)) {
            $updates['status'] = Repository::cleanTaskStatus($data['status']);
            $formats[] = '%s';
        }

        if (!$updates) {
            return true;
        }

        $updates['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        return false !== $wpdb->update(
            $this->repository->table('tasks'),
            $updates,
            ['id' => $taskId],
            $formats,
            ['%d']
        );
    }

    public function moveTask(int $taskId, int $columnId, int $position): bool
    {
        global $wpdb;

        $task = $this->getTask($taskId);
        if (!$task || !$this->repository->columnBelongsToProject($columnId, (int) $task['project_id'])) {
            return false;
        }

        return false !== $wpdb->update(
            $this->repository->table('tasks'),
            [
                'column_id' => $columnId,
                'position' => max(0, $position),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $taskId],
            ['%d', '%d', '%s'],
            ['%d']
        );
    }

    public function deleteTask(int $taskId): bool
    {
        global $wpdb;

        return false !== $wpdb->update(
            $this->repository->table('tasks'),
            ['status' => 'archived', 'updated_at' => current_time('mysql')],
            ['id' => $taskId],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function getTask(int $taskId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('tasks')} WHERE id = %d LIMIT 1",
            $taskId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function getMainTasks(int $projectId, int $limit = 12): array
    {
        global $wpdb;

        $limit = max(1, min(50, $limit));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('tasks')}
             WHERE project_id = %d AND status <> 'archived'
             ORDER BY status ASC, priority DESC, due_date ASC, id ASC
             LIMIT %d",
            $projectId,
            $limit
        ), ARRAY_A) ?: [];
    }

    public function nextTaskPosition(int $columnId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->repository->table('tasks')} WHERE column_id = %d",
            $columnId
        ));
    }

    public function taskBelongsToProject(int $taskId, int $projectId): bool
    {
        $task = $this->getTask($taskId);

        return $task && (int) $task['project_id'] === $projectId;
    }
}
