<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectChecklistService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function getChecklistForTask(int $taskId): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('checklist_items')} WHERE task_id = %d ORDER BY position ASC, id ASC",
            $taskId
        ), ARRAY_A) ?: [];
    }

    public function getChecklistItem(int $itemId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('checklist_items')} WHERE id = %d LIMIT 1",
            $itemId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function addChecklistItem(int $taskId, string $label): int
    {
        global $wpdb;

        $label = Repository::cleanTitle($label);
        if ($taskId <= 0 || $label === '') {
            return 0;
        }

        $position = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->repository->table('checklist_items')} WHERE task_id = %d",
            $taskId
        ));

        $inserted = $wpdb->insert(
            $this->repository->table('checklist_items'),
            [
                'task_id' => $taskId,
                'label' => $label,
                'is_done' => 0,
                'position' => $position,
                'created_at' => current_time('mysql'),
                'updated_at' => null,
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public function updateChecklistItem(int $itemId, array $data): bool
    {
        global $wpdb;

        $updates = [];
        $formats = [];

        if (array_key_exists('label', $data)) {
            $label = Repository::cleanTitle($data['label']);
            if ($label !== '') {
                $updates['label'] = $label;
                $formats[] = '%s';
            }
        }

        if (array_key_exists('is_done', $data)) {
            $updates['is_done'] = !empty($data['is_done']) ? 1 : 0;
            $formats[] = '%d';
        }

        if (array_key_exists('position', $data)) {
            $updates['position'] = max(0, (int) $data['position']);
            $formats[] = '%d';
        }

        if (!$updates) {
            return true;
        }

        $updates['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        return false !== $wpdb->update(
            $this->repository->table('checklist_items'),
            $updates,
            ['id' => $itemId],
            $formats,
            ['%d']
        );
    }

    public function deleteChecklistItem(int $itemId): bool
    {
        global $wpdb;

        return false !== $wpdb->delete($this->repository->table('checklist_items'), ['id' => $itemId], ['%d']);
    }
}
