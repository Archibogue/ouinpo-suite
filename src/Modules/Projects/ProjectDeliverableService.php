<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectDeliverableService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function ensureDefaultDeliverables(int $projectId, int $userId): int
    {
        global $wpdb;

        if ($projectId <= 0 || $userId <= 0) {
            return 0;
        }

        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->repository->table('deliverables')} WHERE project_id = %d",
            $projectId
        ));

        if ($existing > 0) {
            return 0;
        }

        $created = 0;
        foreach (Repository::defaultDeliverables() as $position => $deliverable) {
            $id = $this->createDeliverable($projectId, [
                'title' => $deliverable['title'],
                'type' => $deliverable['type'],
                'position' => $position + 1,
            ], $userId);
            if ($id > 0) {
                $created++;
            }
        }

        return $created;
    }

    public function getDeliverables(int $projectId): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, u.display_name AS creator_name, validator.display_name AS validator_name
             FROM {$this->repository->table('deliverables')} d
             LEFT JOIN {$wpdb->users} u ON u.ID = d.created_by
             LEFT JOIN {$wpdb->users} validator ON validator.ID = d.validated_by
             WHERE d.project_id = %d
             ORDER BY d.position ASC, d.due_date ASC, d.id ASC",
            $projectId
        ), ARRAY_A) ?: [];
    }

    public function getDeliverable(int $deliverableId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('deliverables')} WHERE id = %d LIMIT 1",
            $deliverableId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function createDeliverable(int $projectId, array $data, int $userId): int
    {
        global $wpdb;

        $title = Repository::cleanTitle($data['title'] ?? '');
        if ($projectId <= 0 || $userId <= 0 || $title === '') {
            return 0;
        }

        $position = isset($data['position'])
            ? max(0, (int) $data['position'])
            : $this->nextDeliverablePosition($projectId);

        $inserted = $wpdb->insert(
            $this->repository->table('deliverables'),
            [
                'project_id' => $projectId,
                'title' => $title,
                'description' => Repository::cleanLongText($data['description'] ?? ''),
                'type' => Repository::cleanDeliverableType($data['type'] ?? 'other'),
                'status' => Repository::cleanDeliverableStatus($data['status'] ?? 'expected'),
                'due_date' => Repository::cleanDate($data['due_date'] ?? ''),
                'validated_by' => null,
                'validated_at' => null,
                'position' => $position,
                'created_by' => $userId,
                'created_at' => current_time('mysql'),
                'updated_at' => null,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public function updateDeliverable(int $deliverableId, array $data): bool
    {
        global $wpdb;

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

        if (array_key_exists('type', $data)) {
            $updates['type'] = Repository::cleanDeliverableType($data['type']);
            $formats[] = '%s';
        }

        if (array_key_exists('status', $data)) {
            $updates['status'] = Repository::cleanDeliverableStatus($data['status']);
            $formats[] = '%s';
        }

        if (array_key_exists('due_date', $data)) {
            $updates['due_date'] = Repository::cleanDate($data['due_date']);
            $formats[] = '%s';
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
            $this->repository->table('deliverables'),
            $updates,
            ['id' => $deliverableId],
            $formats,
            ['%d']
        );
    }

    public function updateDeliverableStatus(int $deliverableId, string $status, int $userId): bool
    {
        global $wpdb;

        $status = Repository::cleanDeliverableStatus($status);
        $updates = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s'];

        if ($status === 'validated') {
            $updates['validated_by'] = $userId;
            $updates['validated_at'] = current_time('mysql');
            $formats[] = '%d';
            $formats[] = '%s';
        } else {
            $updates['validated_by'] = null;
            $updates['validated_at'] = null;
            $formats[] = '%d';
            $formats[] = '%s';
        }

        return false !== $wpdb->update(
            $this->repository->table('deliverables'),
            $updates,
            ['id' => $deliverableId],
            $formats,
            ['%d']
        );
    }

    public function deleteDeliverable(int $deliverableId): bool
    {
        global $wpdb;

        $deliverable = $this->getDeliverable($deliverableId);
        if (!$deliverable) {
            return false;
        }

        $wpdb->update(
            $this->repository->table('evidence'),
            ['deliverable_id' => null, 'updated_at' => current_time('mysql')],
            ['deliverable_id' => $deliverableId],
            ['%d', '%s'],
            ['%d']
        );

        $wpdb->delete(
            $this->repository->table('competency_links'),
            ['object_type' => 'deliverable', 'object_id' => $deliverableId],
            ['%s', '%d']
        );

        return false !== $wpdb->delete($this->repository->table('deliverables'), ['id' => $deliverableId], ['%d']);
    }

    public function nextDeliverablePosition(int $projectId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->repository->table('deliverables')} WHERE project_id = %d",
            $projectId
        ));
    }

    public function deliverableBelongsToProject(int $deliverableId, int $projectId): bool
    {
        $deliverable = $this->getDeliverable($deliverableId);

        return $deliverable && (int) $deliverable['project_id'] === $projectId;
    }
}
