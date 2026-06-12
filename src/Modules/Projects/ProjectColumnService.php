<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectColumnService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public static function defaultColumns(): array
    {
        return [
            ['title' => 'À cadrer', 'status_key' => 'a_cadrer'],
            ['title' => 'À faire', 'status_key' => 'a_faire'],
            ['title' => 'En cours', 'status_key' => 'en_cours'],
            ['title' => 'À tester', 'status_key' => 'a_tester'],
            ['title' => 'À documenter', 'status_key' => 'a_documenter'],
            ['title' => 'À valider', 'status_key' => 'a_valider'],
            ['title' => 'Terminé', 'status_key' => 'termine'],
        ];
    }

    public function ensureDefaultColumns(int $projectId): void
    {
        global $wpdb;

        if ($projectId <= 0) {
            return;
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->repository->table('columns')} WHERE project_id = %d",
            $projectId
        ));

        if ($exists > 0) {
            return;
        }

        $now = current_time('mysql');
        foreach (self::defaultColumns() as $position => $column) {
            $wpdb->insert(
                $this->repository->table('columns'),
                [
                    'project_id' => $projectId,
                    'title' => $column['title'],
                    'position' => $position + 1,
                    'status_key' => sanitize_key((string) $column['status_key']),
                    'created_at' => $now,
                ],
                ['%d', '%s', '%d', '%s', '%s']
            );
        }
    }

    public function getFirstColumnId(int $projectId): int
    {
        global $wpdb;

        $this->ensureDefaultColumns($projectId);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->repository->table('columns')} WHERE project_id = %d ORDER BY position ASC, id ASC LIMIT 1",
            $projectId
        ));
    }

    public function getColumnIdForStatusKey(int $projectId, string $statusKey): int
    {
        global $wpdb;

        $this->ensureDefaultColumns($projectId);
        $statusKey = sanitize_key($statusKey);
        if ($statusKey === '') {
            return $this->getFirstColumnId($projectId);
        }

        $columnId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->repository->table('columns')} WHERE project_id = %d AND status_key = %s ORDER BY position ASC, id ASC LIMIT 1",
            $projectId,
            $statusKey
        ));

        return $columnId > 0 ? $columnId : $this->getFirstColumnId($projectId);
    }

    public function columnBelongsToProject(int $columnId, int $projectId): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->repository->table('columns')} WHERE id = %d AND project_id = %d",
            $columnId,
            $projectId
        )) > 0;
    }
}
