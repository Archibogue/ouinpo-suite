<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectBoardService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function getBoard(int $projectId): array
    {
        global $wpdb;

        $this->repository->ensureDefaultColumns($projectId);

        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('columns')} WHERE project_id = %d ORDER BY position ASC, id ASC",
            $projectId
        ), ARRAY_A) ?: [];

        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*
             FROM {$this->repository->table('tasks')} t
             WHERE t.project_id = %d AND t.status <> 'archived'
             ORDER BY t.column_id ASC, t.position ASC, t.id ASC",
            $projectId
        ), ARRAY_A) ?: [];

        $checklists = $this->getChecklistForTasks(array_column($tasks, 'id'));
        $byColumn = [];
        foreach ($tasks as $task) {
            $task['id'] = (int) $task['id'];
            $task['project_id'] = (int) $task['project_id'];
            $task['column_id'] = (int) $task['column_id'];
            $task['assigned_user_id'] = $task['assigned_user_id'] !== null ? (int) $task['assigned_user_id'] : null;
            $task['created_by'] = (int) $task['created_by'];
            $task['checklist'] = $checklists[(int) $task['id']] ?? [];
            $byColumn[(int) $task['column_id']][] = $task;
        }

        foreach ($columns as &$column) {
            $column['id'] = (int) $column['id'];
            $column['project_id'] = (int) $column['project_id'];
            $column['position'] = (int) $column['position'];
            $column['tasks'] = $byColumn[(int) $column['id']] ?? [];
        }
        unset($column);

        return $columns;
    }

    private function getChecklistForTasks(array $taskIds): array
    {
        global $wpdb;

        $taskIds = array_values(array_unique(array_filter(
            array_map('intval', $taskIds),
            static fn(int $taskId): bool => $taskId > 0
        )));
        if (!$taskIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($taskIds), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('checklist_items')} WHERE task_id IN ({$placeholders}) ORDER BY task_id ASC, position ASC, id ASC",
            $taskIds
        ), ARRAY_A) ?: [];

        $byTask = [];
        foreach ($rows as $row) {
            $byTask[(int) $row['task_id']][] = $row;
        }

        return $byTask;
    }
}
