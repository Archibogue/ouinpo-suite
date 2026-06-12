<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectCompetencyService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function getCompetencyLinks(int $projectId, string $objectType = '', int $objectId = 0): array
    {
        global $wpdb;

        $competencies = $wpdb->prefix . 'ouin_exo_competencies';
        $where = ['l.project_id = %d'];
        $args = [$projectId];

        if ($objectType !== '') {
            $where[] = 'l.object_type = %s';
            $args[] = Repository::cleanCompetencyObjectType($objectType);
        }

        if ($objectId > 0) {
            $where[] = 'l.object_id = %d';
            $args[] = $objectId;
        }

        $join = $this->repository->tableExists($competencies)
            ? "LEFT JOIN {$competencies} c ON c.id = l.competency_id"
            : '';
        $selectCompetency = $join !== ''
            ? "c.domain, c.domain_slug, c.competency, c.label"
            : "'' AS domain, '' AS domain_slug, '' AS competency, '' AS label";

        $sql = "SELECT l.*, {$selectCompetency}
             FROM {$this->repository->table('competency_links')} l
             {$join}
             WHERE " . implode(' AND ', $where) . '
             ORDER BY l.object_type ASC, l.object_id ASC, l.id ASC';

        return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
    }

    public function getCompetencyLink(int $linkId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('competency_links')} WHERE id = %d LIMIT 1",
            $linkId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function addCompetencyLink(int $projectId, string $objectType, int $objectId, int $competencyId, int $userId): int
    {
        global $wpdb;

        $objectType = Repository::cleanCompetencyObjectType($objectType);
        if (
            $projectId <= 0
            || $objectId <= 0
            || $competencyId <= 0
            || $userId <= 0
            || !$this->objectBelongsToProject($projectId, $objectType, $objectId)
            || !$this->competencyExists($competencyId)
        ) {
            return 0;
        }

        $sql = $wpdb->prepare(
            "INSERT IGNORE INTO {$this->repository->table('competency_links')}
             (project_id, object_type, object_id, competency_id, created_by, created_at)
             VALUES (%d, %s, %d, %d, %d, %s)",
            $projectId,
            $objectType,
            $objectId,
            $competencyId,
            $userId,
            current_time('mysql')
        );

        $wpdb->query($sql);

        if ((int) $wpdb->insert_id > 0) {
            return (int) $wpdb->insert_id;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->repository->table('competency_links')}
             WHERE object_type = %s AND object_id = %d AND competency_id = %d
             LIMIT 1",
            $objectType,
            $objectId,
            $competencyId
        ));
    }

    public function deleteCompetencyLink(int $linkId): bool
    {
        global $wpdb;

        return false !== $wpdb->delete($this->repository->table('competency_links'), ['id' => $linkId], ['%d']);
    }

    public function getAvailableCompetencies(int $limit = 500): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_competencies';
        if (!$this->repository->tableExists($table)) {
            return [];
        }

        $limit = max(1, min(1000, $limit));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, domain, domain_slug, competency, label, slug
             FROM {$table}
             WHERE active = %d OR active IS NULL
             ORDER BY domain ASC, id ASC
             LIMIT %d",
            1,
            $limit
        ), ARRAY_A) ?: [];
    }

    public function objectBelongsToProject(int $projectId, string $objectType, int $objectId): bool
    {
        if ($projectId <= 0 || $objectId <= 0) {
            return false;
        }

        $objectType = Repository::cleanCompetencyObjectType($objectType);

        if ($objectType === 'project') {
            return $objectId === $projectId && null !== $this->repository->getProject($projectId);
        }

        if ($objectType === 'task') {
            $task = $this->repository->getTask($objectId);
            return $task && (int) $task['project_id'] === $projectId;
        }

        if ($objectType === 'deliverable') {
            $deliverable = $this->repository->getDeliverable($objectId);
            return $deliverable && (int) $deliverable['project_id'] === $projectId;
        }

        if ($objectType === 'evidence') {
            $evidence = $this->repository->getEvidenceItem($objectId);
            return $evidence && (int) $evidence['project_id'] === $projectId;
        }

        return false;
    }

    public function competencyExists(int $competencyId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_competencies';
        if ($competencyId <= 0 || !$this->repository->tableExists($table)) {
            return false;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = %d",
            $competencyId
        )) > 0;
    }
}
