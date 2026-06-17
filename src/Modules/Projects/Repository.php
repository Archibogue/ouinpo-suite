<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\Storage\PrivateUploadValidator;

defined('ABSPATH') || exit;

final class Repository
{
    public const STATUS = ['draft', 'active', 'finished', 'completed', 'frozen', 'archived', 'portfolio_archive', 'pending_deletion', 'deleted'];
    public const LIFECYCLE_STATUS = ['draft', 'active', 'completed', 'frozen', 'archived', 'portfolio_archive', 'pending_deletion', 'deleted'];
    public const CLOSURE_POLICY = ['auto', 'carry_over', 'freeze_for_portfolio', 'archive_readonly', 'purge_if_no_evidence', 'never_purge_automatically'];
    public const MEMBER_ROLES = ['member', 'leader', 'observer'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const TASK_STATUS = ['open', 'done', 'archived'];
    public const DELIVERABLE_TYPES = [
        'specification',
        'model',
        'mockup',
        'source_code',
        'database',
        'test_plan',
        'user_doc',
        'technical_doc',
        'presentation',
        'portfolio_sheet',
        'other',
    ];
    public const DELIVERABLE_STATUS = ['expected', 'submitted', 'needs_revision', 'validated', 'rejected'];
    public const EVIDENCE_TYPES = ['link', 'attachment', 'text', 'repository', 'screenshot', 'document', 'other'];
    public const COMPETENCY_OBJECT_TYPES = ['project', 'task', 'deliverable', 'evidence'];
    public const EVIDENCE_UPLOAD_MAX_BYTES = 10485760;
    public const EVIDENCE_UPLOAD_ALLOWED_EXTENSIONS = [
        'pdf', 'txt', 'md', 'csv', 'json', 'sql', 'py',
        'html.txt', 'css.txt', 'js.txt',
        'png', 'jpg', 'jpeg', 'webp', 'zip',
    ];
    public const EVIDENCE_UPLOAD_BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
        'exe', 'bat', 'cmd', 'sh', 'com', 'msi',
        'svg', 'html', 'htm', 'js', 'mjs', 'css', 'htaccess', 'env',
    ];

    public static function defaultColumns(): array
    {
        return ProjectColumnService::defaultColumns();
    }

    public static function defaultDeliverables(): array
    {
        return [
            ['title' => 'Cahier des charges', 'type' => 'specification'],
            ['title' => 'Modele de donnees', 'type' => 'model'],
            ['title' => 'Maquettes / ecrans', 'type' => 'mockup'],
            ['title' => 'Code source ou depot', 'type' => 'source_code'],
            ['title' => 'Plan de tests', 'type' => 'test_plan'],
            ['title' => 'Documentation utilisateur', 'type' => 'user_doc'],
            ['title' => 'Documentation technique', 'type' => 'technical_doc'],
            ['title' => 'Support de presentation', 'type' => 'presentation'],
            ['title' => 'Fiche portfolio / situation professionnelle', 'type' => 'portfolio_sheet'],
        ];
    }

    public function table(string $suffix): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouinpo_project' . ($suffix === 'projects' ? 's' : '_' . $suffix);
    }

    public function projectsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouinpo_projects';
    }

    public function listVisibleProjects(int $userId): array
    {
        global $wpdb;

        $projects = $this->projectsTable();
        $members = $this->table('members');

        if ($this->userCanManageAll($userId)) {
            $sql = $wpdb->prepare(
                "SELECT p.*,
                   (SELECT COUNT(*) FROM {$members} pm WHERE pm.project_id = p.id) AS members_count,
                   (SELECT COUNT(*) FROM {$this->table('tasks')} t WHERE t.project_id = p.id AND t.status <> 'archived') AS tasks_count,
                   (SELECT COUNT(*) FROM {$this->table('tasks')} t WHERE t.project_id = p.id AND t.status = 'open') AS open_tasks_count
                FROM {$projects} p
                WHERE %d = 1
                ORDER BY p.created_at DESC, p.id DESC",
                1
            );

            $rows = $wpdb->get_results(
                $sql,
                ARRAY_A
            );

            return $rows ?: [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT p.*,
                (SELECT COUNT(*) FROM {$members} pm2 WHERE pm2.project_id = p.id) AS members_count,
                (SELECT COUNT(*) FROM {$this->table('tasks')} t WHERE t.project_id = p.id AND t.status <> 'archived') AS tasks_count,
                (SELECT COUNT(*) FROM {$this->table('tasks')} t WHERE t.project_id = p.id AND t.status = 'open') AS open_tasks_count
             FROM {$projects} p
             LEFT JOIN {$members} pm ON pm.project_id = p.id
             WHERE p.teacher_id = %d
                OR p.created_by = %d
                OR pm.user_id = %d
             ORDER BY p.created_at DESC, p.id DESC",
            $userId,
            $userId,
            $userId
        ), ARRAY_A);

        return $rows ?: [];
    }

    public function createProject(array $data, int $userId): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $title = self::cleanTitle($data['title'] ?? '');
        $slug = self::cleanSlug($data['slug'] ?? '', $title);
        $teacherId = max(1, (int) ($data['teacher_id'] ?? $userId));
        $status = self::cleanProjectStatus($data['status'] ?? 'draft');

        if ($title === '') {
            return 0;
        }

        $slug = $this->uniqueProjectSlug($slug);

        $payload = [
            'title' => $title,
            'slug' => $slug,
            'description' => self::cleanLongText($data['description'] ?? ''),
            'level' => self::cleanNullableText($data['level'] ?? '', 100),
            'class_slug' => self::cleanNullableKey($data['class_slug'] ?? '', 100),
            'status' => $status,
            'student_ai_enabled' => !empty($data['student_ai_enabled']) ? 1 : 0,
            'teacher_id' => $teacherId,
            'start_date' => self::cleanDate($data['start_date'] ?? ''),
            'end_date' => self::cleanDate($data['end_date'] ?? ''),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => null,
        ];
        $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s'];
        $this->appendProjectClosureFields($payload, $formats, $data);

        $inserted = $wpdb->insert($this->projectsTable(), $payload, $formats);

        if (!$inserted) {
            return 0;
        }

        $projectId = (int) $wpdb->insert_id;
        $this->ensureDefaultColumns($projectId);

        return $projectId;
    }

    public function updateProject(int $projectId, array $data): bool
    {
        global $wpdb;

        $updates = [];
        $formats = [];

        if (array_key_exists('title', $data)) {
            $title = self::cleanTitle($data['title']);
            if ($title !== '') {
                $updates['title'] = $title;
                $formats[] = '%s';
            }
        }

        if (array_key_exists('slug', $data)) {
            $slug = self::cleanSlug($data['slug'], $updates['title'] ?? '');
            if ($slug !== '') {
                $updates['slug'] = $this->uniqueProjectSlug($slug, $projectId);
                $formats[] = '%s';
            }
        }

        foreach (['description', 'level', 'class_slug', 'start_date', 'end_date', 'status'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'description') {
                $updates[$field] = self::cleanLongText($data[$field]);
            } elseif ($field === 'class_slug') {
                $updates[$field] = self::cleanNullableKey($data[$field], 100);
            } elseif ($field === 'start_date' || $field === 'end_date') {
                $updates[$field] = self::cleanDate($data[$field]);
            } elseif ($field === 'status') {
                $updates[$field] = self::cleanProjectStatus($data[$field]);
            } else {
                $updates[$field] = self::cleanNullableText($data[$field], 100);
            }
            $formats[] = '%s';
        }

        if (array_key_exists('teacher_id', $data)) {
            $updates['teacher_id'] = max(1, (int) $data['teacher_id']);
            $formats[] = '%d';
        }

        if (array_key_exists('student_ai_enabled', $data)) {
            $updates['student_ai_enabled'] = !empty($data['student_ai_enabled']) ? 1 : 0;
            $formats[] = '%d';
        }

        $this->appendProjectClosureFields($updates, $formats, $data);

        if (!$updates) {
            return true;
        }

        $updates['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        return false !== $wpdb->update(
            $this->projectsTable(),
            $updates,
            ['id' => $projectId],
            $formats,
            ['%d']
        );
    }

    public function getProject(int $projectId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->projectsTable()} WHERE id = %d LIMIT 1",
            $projectId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function getProjectSummary(int $projectId): ?array
    {
        return (new ProjectStatsService($this))->getProjectSummary($projectId);
    }

    public function ensureDefaultColumns(int $projectId): void
    {
        $this->columns()->ensureDefaultColumns($projectId);
    }

    public function getBoard(int $projectId): array
    {
        return (new ProjectBoardService($this))->getBoard($projectId);
    }

    public function getFirstColumnId(int $projectId): int
    {
        return $this->columns()->getFirstColumnId($projectId);
    }

    public function getColumnIdForStatusKey(int $projectId, string $statusKey): int
    {
        return $this->columns()->getColumnIdForStatusKey($projectId, $statusKey);
    }

    public function columnBelongsToProject(int $columnId, int $projectId): bool
    {
        return $this->columns()->columnBelongsToProject($columnId, $projectId);
    }

    public function createTask(int $projectId, array $data, int $userId): int
    {
        return $this->tasks()->createTask($projectId, $data, $userId);
    }

    public function updateTask(int $taskId, array $data): bool
    {
        return $this->tasks()->updateTask($taskId, $data);
    }

    public function moveTask(int $taskId, int $columnId, int $position): bool
    {
        return $this->tasks()->moveTask($taskId, $columnId, $position);
    }

    public function deleteTask(int $taskId): bool
    {
        return $this->tasks()->deleteTask($taskId);
    }

    public function getTask(int $taskId): ?array
    {
        return $this->tasks()->getTask($taskId);
    }

    public function getMembers(int $projectId): array
    {
        return $this->members()->getMembers($projectId);
    }

    public function addMember(int $projectId, int $userId, string $role = 'member'): bool
    {
        return $this->members()->addMember($projectId, $userId, $role);
    }

    public function removeMember(int $projectId, int $userId): bool
    {
        return $this->members()->removeMember($projectId, $userId);
    }

    public function isProjectMember(int $projectId, int $userId): bool
    {
        return $this->permissions()->isProjectMember($projectId, $userId);
    }

    public function userCanViewProject(int $projectId, int $userId): bool
    {
        return $this->permissions()->canViewProject($projectId, $userId);
    }

    public function userCanManageProject(int $projectId, int $userId): bool
    {
        return $this->permissions()->canManageProject($projectId, $userId);
    }

    public function userCanManageProjectRow(array $project, int $userId): bool
    {
        return $this->permissions()->canManageProjectRow($project, $userId);
    }

    public function userCanManageAll(int $userId): bool
    {
        return $this->permissions()->canManageAllProjects($userId);
    }

    public function userCanEditTask(array $task, int $userId): bool
    {
        return $this->permissions()->canEditTask($task, $userId);
    }

    public function getComments(int $taskId): array
    {
        return $this->journal()->getComments($taskId);
    }

    public function addComment(int $taskId, int $userId, string $comment): int
    {
        return $this->journal()->addComment($taskId, $userId, $comment);
    }

    public function getChecklistForTask(int $taskId): array
    {
        return $this->checklist()->getChecklistForTask($taskId);
    }

    public function getChecklistItem(int $itemId): ?array
    {
        return $this->checklist()->getChecklistItem($itemId);
    }

    public function addChecklistItem(int $taskId, string $label): int
    {
        return $this->checklist()->addChecklistItem($taskId, $label);
    }

    public function updateChecklistItem(int $itemId, array $data): bool
    {
        return $this->checklist()->updateChecklistItem($itemId, $data);
    }

    public function deleteChecklistItem(int $itemId): bool
    {
        return $this->checklist()->deleteChecklistItem($itemId);
    }

    public function getLogs(int $projectId): array
    {
        return $this->journal()->getLogs($projectId);
    }

    public function addLog(int $projectId, int $userId, array $data): int
    {
        return $this->journal()->addLog($projectId, $userId, $data);
    }

    public function ensureDefaultDeliverables(int $projectId, int $userId): int
    {
        return $this->deliverables()->ensureDefaultDeliverables($projectId, $userId);
    }

    public function getDeliverables(int $projectId): array
    {
        return $this->deliverables()->getDeliverables($projectId);
    }

    public function getDeliverable(int $deliverableId): ?array
    {
        return $this->deliverables()->getDeliverable($deliverableId);
    }

    public function createDeliverable(int $projectId, array $data, int $userId): int
    {
        return $this->deliverables()->createDeliverable($projectId, $data, $userId);
    }

    public function updateDeliverable(int $deliverableId, array $data): bool
    {
        return $this->deliverables()->updateDeliverable($deliverableId, $data);
    }

    public function updateDeliverableStatus(int $deliverableId, string $status, int $userId): bool
    {
        return $this->deliverables()->updateDeliverableStatus($deliverableId, $status, $userId);
    }

    public function deleteDeliverable(int $deliverableId): bool
    {
        return $this->deliverables()->deleteDeliverable($deliverableId);
    }

    public function nextDeliverablePosition(int $projectId): int
    {
        return $this->deliverables()->nextDeliverablePosition($projectId);
    }

    public function getEvidence(int $projectId): array
    {
        return $this->evidence()->getEvidence($projectId);
    }

    public function getEvidenceItem(int $evidenceId): ?array
    {
        return $this->evidence()->getEvidenceItem($evidenceId);
    }

    public function createEvidence(int $projectId, array $data, int $userId): int
    {
        return $this->evidence()->createEvidence($projectId, $data, $userId);
    }

    public function updateEvidence(int $evidenceId, array $data): bool
    {
        return $this->evidence()->updateEvidence($evidenceId, $data);
    }

    public function deleteEvidence(int $evidenceId): bool
    {
        return $this->evidence()->deleteEvidence($evidenceId);
    }

    public function deliverableBelongsToProject(int $deliverableId, int $projectId): bool
    {
        return $this->deliverables()->deliverableBelongsToProject($deliverableId, $projectId);
    }

    public function taskBelongsToProject(int $taskId, int $projectId): bool
    {
        return $this->tasks()->taskBelongsToProject($taskId, $projectId);
    }

    public function getCompetencyLinks(int $projectId, string $objectType = '', int $objectId = 0): array
    {
        return $this->competencies()->getCompetencyLinks($projectId, $objectType, $objectId);
    }

    public function getCompetencyLink(int $linkId): ?array
    {
        return $this->competencies()->getCompetencyLink($linkId);
    }

    public function addCompetencyLink(int $projectId, string $objectType, int $objectId, int $competencyId, int $userId): int
    {
        return $this->competencies()->addCompetencyLink($projectId, $objectType, $objectId, $competencyId, $userId);
    }

    public function deleteCompetencyLink(int $linkId): bool
    {
        return $this->competencies()->deleteCompetencyLink($linkId);
    }

    public function getAvailableCompetencies(int $limit = 500): array
    {
        return $this->competencies()->getAvailableCompetencies($limit);
    }

    public function getMainTasks(int $projectId, int $limit = 12): array
    {
        return $this->tasks()->getMainTasks($projectId, $limit);
    }

    public function projectAlerts(array $summary): array
    {
        $alerts = [];

        if ((int) ($summary['overdue_deliverables_count'] ?? 0) > 0) {
            $alerts[] = 'Livrables en retard';
        }

        if ((int) ($summary['open_tasks_count'] ?? 0) > 0 && empty($summary['last_log_at'])) {
            $alerts[] = 'Journal non renseigne';
        }

        if ((int) ($summary['deliverables_count'] ?? 0) > 0 && (int) ($summary['validated_deliverables_count'] ?? 0) === 0) {
            $alerts[] = 'Aucun livrable valide';
        }

        return $alerts;
    }

    public function isPortfolioRelevantProject(array $project): bool
    {
        return !empty($project['is_portfolio_relevant'])
            || (string) ($project['closure_policy'] ?? '') === 'never_purge_automatically';
    }

    public function shouldNeverPurgeAutomatically(array $project): bool
    {
        return $this->isPortfolioRelevantProject($project)
            || in_array((string) ($project['closure_policy'] ?? ''), ['never_purge_automatically', 'freeze_for_portfolio'], true);
    }

    public function markProjectCarriedOver(int $projectId, int $toYearId, ?int $toGroupId): bool
    {
        $updates = [
            'current_year_id' => $toYearId,
            'current_group_id' => $toGroupId,
            'lifecycle_status' => 'active',
            'updated_at' => current_time('mysql'),
        ];

        return $this->updateProjectClosureState($projectId, $updates);
    }

    public function archiveProjectReadonly(int $projectId, int $userId): bool
    {
        $updated = $this->updateProjectClosureState($projectId, [
            'lifecycle_status' => 'archived',
            'closure_policy' => 'archive_readonly',
            'archived_at' => current_time('mysql'),
            'archived_by' => $userId,
            'updated_at' => current_time('mysql'),
        ]);

        if ($updated) {
            $this->markMembersArchiveViewers($projectId);
        }

        return $updated;
    }

    public function freezeProjectForPortfolio(int $projectId, int $userId): bool
    {
        $updated = $this->updateProjectClosureState($projectId, [
            'lifecycle_status' => 'portfolio_archive',
            'closure_policy' => 'freeze_for_portfolio',
            'is_portfolio_relevant' => 1,
            'archived_at' => current_time('mysql'),
            'archived_by' => $userId,
            'updated_at' => current_time('mysql'),
        ]);

        if ($updated) {
            $this->markMembersArchiveViewers($projectId);
        }

        return $updated;
    }

    public function findProjectsForClosureGroup(int $fromGroupId, ?int $fromYearId = null): array
    {
        global $wpdb;

        $projects = $this->projectsTable();
        if ($fromGroupId <= 0 || !$this->tableExists($projects)) {
            return [];
        }

        $found = [];
        $register = static function (array $rows, string $source) use (&$found): void {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0 || isset($found[$id])) {
                    continue;
                }
                $row['closure_detection_source'] = $source;
                $found[$id] = $row;
            }
        };

        $currentGroupColumn = $this->columnExists($projects, 'current_group_id');
        $originGroupColumn = $this->columnExists($projects, 'origin_group_id');
        $currentYearColumn = $this->columnExists($projects, 'current_year_id');

        if ($currentGroupColumn) {
            $args = [$fromGroupId];
            $where = 'current_group_id = %d';
            if ($fromYearId && $currentYearColumn) {
                $where .= ' AND (current_year_id = %d OR current_year_id IS NULL)';
                $args[] = $fromYearId;
            }
            $sql = "SELECT * FROM {$projects} WHERE {$where} " . $this->carriableProjectSql();
            $register($wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [], 'current_group_id');
        }

        if ($originGroupColumn) {
            $sql = "SELECT * FROM {$projects}
                    WHERE origin_group_id = %d
                      " . ($currentGroupColumn ? 'AND current_group_id IS NULL' : '') . "
                      {$this->carriableProjectSql()}";
            $register($wpdb->get_results($wpdb->prepare($sql, $fromGroupId), ARRAY_A) ?: [], 'origin_group_id');
        }

        $classSlugCandidates = $this->classSlugCandidatesForGroup($fromGroupId);
        if ($classSlugCandidates && $this->columnExists($projects, 'class_slug')) {
            $placeholders = implode(', ', array_fill(0, count($classSlugCandidates), '%s'));
            $sql = "SELECT * FROM {$projects}
                    WHERE class_slug IN ({$placeholders})
                      " . ($currentGroupColumn ? 'AND current_group_id IS NULL' : '') . "
                      {$this->carriableProjectSql()}";
            $register($wpdb->get_results($wpdb->prepare($sql, $classSlugCandidates), ARRAY_A) ?: [], 'class_slug');
        }

        $members = $this->table('members');
        $groupMembers = $wpdb->prefix . 'ouin_exo_group_members';
        if ($this->tableExists($members) && $this->tableExists($groupMembers)) {
            $sql = "SELECT DISTINCT p.*
                    FROM {$projects} p
                    INNER JOIN {$members} pm ON pm.project_id = p.id
                    INNER JOIN {$groupMembers} gm ON gm.user_id = pm.user_id
                    WHERE gm.group_id = %d
                      AND gm.role = 'student'
                      " . ($currentGroupColumn ? 'AND p.current_group_id IS NULL' : '') . "
                      {$this->carriableProjectSql('p')}";
            $register($wpdb->get_results($wpdb->prepare($sql, $fromGroupId), ARRAY_A) ?: [], 'project_members');
        }

        return array_values($found);
    }

    public function backfillProjectClosureColumnsForGroup(int $fromGroupId, ?int $fromYearId = null): int
    {
        global $wpdb;

        $projects = $this->projectsTable();
        if ($fromGroupId <= 0 || !$this->tableExists($projects) || !$this->columnExists($projects, 'class_slug')) {
            return 0;
        }

        $candidates = $this->classSlugCandidatesForGroup($fromGroupId);
        if (!$candidates) {
            return 0;
        }

        $updates = [];
        $formats = [];
        if ($this->columnExists($projects, 'origin_group_id')) {
            $updates['origin_group_id'] = $fromGroupId;
            $formats[] = '%d';
        }
        if ($this->columnExists($projects, 'current_group_id')) {
            $updates['current_group_id'] = $fromGroupId;
            $formats[] = '%d';
        }
        if ($fromYearId && $this->columnExists($projects, 'origin_year_id')) {
            $updates['origin_year_id'] = $fromYearId;
            $formats[] = '%d';
        }
        if ($fromYearId && $this->columnExists($projects, 'current_year_id')) {
            $updates['current_year_id'] = $fromYearId;
            $formats[] = '%d';
        }
        if (!$updates) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($candidates), '%s'));
        $where = "class_slug IN ({$placeholders})";
        if ($this->columnExists($projects, 'current_group_id')) {
            $where .= ' AND current_group_id IS NULL';
        }

        $setClauses = [];
        foreach (array_keys($updates) as $index => $field) {
            $setClauses[] = "{$field} = COALESCE({$field}, " . ($formats[$index] ?? '%s') . ')';
        }

        $sql = "UPDATE {$projects} SET " . implode(', ', $setClauses) . " WHERE {$where}";

        $args = array_merge(array_values($updates), $candidates);
        $prepared = $wpdb->prepare($sql, $args);
        $result = $wpdb->query($prepared);

        return $result === false ? 0 : (int) $result;
    }

    public function markProjectsCarriedOverForGroup(int $fromGroupId, int $toGroupId, int $toYearId, ?int $fromYearId = null, ?int $cycleId = null): int
    {
        $projects = $this->findProjectsForClosureGroup($fromGroupId, $fromYearId);
        $updated = 0;

        foreach ($projects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $updates = [
                'current_year_id' => $toYearId,
                'current_group_id' => $toGroupId,
                'updated_at' => current_time('mysql'),
            ];
            if (empty($project['origin_group_id'])) {
                $updates['origin_group_id'] = $fromGroupId;
            }
            if ($fromYearId && empty($project['origin_year_id'])) {
                $updates['origin_year_id'] = $fromYearId;
            }
            if ($cycleId && empty($project['cycle_id'])) {
                $updates['cycle_id'] = $cycleId;
            }

            $lifecycle = sanitize_key((string) ($project['lifecycle_status'] ?? $project['status'] ?? 'active'));
            if ($this->columnExists($this->projectsTable(), 'lifecycle_status') && !in_array($lifecycle, ['frozen', 'archived', 'portfolio_archive'], true)) {
                $updates['lifecycle_status'] = 'active';
            }

            $portfolioRelevant = !empty($project['is_portfolio_relevant']);
            $policy = sanitize_key((string) ($project['closure_policy'] ?? ''));
            if ($this->columnExists($this->projectsTable(), 'closure_policy')) {
                if ($portfolioRelevant && $policy !== 'never_purge_automatically' && $policy !== 'freeze_for_portfolio') {
                    $updates['closure_policy'] = 'never_purge_automatically';
                } elseif ($policy === '') {
                    $updates['closure_policy'] = 'auto';
                }
            }

            if ($this->updateProjectClosureState($projectId, $updates)) {
                $updated++;
            }
        }

        return $updated;
    }

    public function preserveProjectsForAlumniExit(int $userId, ?int $fromYearId = null, ?int $fromCycleId = null, array $fromGroupIds = []): array
    {
        global $wpdb;

        unset($fromYearId, $fromCycleId, $fromGroupIds);

        $members = $this->table('members');
        if ($userId <= 0 || !$this->tableExists($members)) {
            return ['projects' => 0, 'members_archived' => 0, 'projects_frozen' => 0, 'projects_archived' => 0, 'project_ids' => []];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*
             FROM {$this->projectsTable()} p
             INNER JOIN {$members} pm ON pm.project_id = p.id
             WHERE pm.user_id = %d",
            $userId
        ), ARRAY_A) ?: [];

        $summary = ['projects' => 0, 'members_archived' => 0, 'projects_frozen' => 0, 'projects_archived' => 0, 'project_ids' => []];
        foreach ($rows as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0 || !$this->isUsefulAlumniProject($project)) {
                continue;
            }

            $summary['projects']++;
            $summary['project_ids'][] = $projectId;
            if (!empty($project['is_portfolio_relevant']) && $this->columnExists($this->projectsTable(), 'closure_policy')) {
                $policy = sanitize_key((string) ($project['closure_policy'] ?? ''));
                if ($policy !== 'never_purge_automatically' && $policy !== 'freeze_for_portfolio') {
                    $this->updateProjectClosureState($projectId, [
                        'closure_policy' => 'never_purge_automatically',
                        'updated_at' => current_time('mysql'),
                    ]);
                }
            }
            if ($this->markProjectMemberArchiveViewer($projectId, $userId)) {
                $summary['members_archived']++;
            }

            if ($this->projectHasActiveNonAlumniMembers($projectId, $userId)) {
                continue;
            }

            $lifecycle = sanitize_key((string) ($project['lifecycle_status'] ?? $project['status'] ?? 'active'));
            if ($this->isPortfolioRelevantProject($project)) {
                if ($this->freezeProjectForPortfolio($projectId, $userId)) {
                    $summary['projects_frozen']++;
                }
            } elseif (in_array($lifecycle, ['completed', 'finished', 'archived'], true)) {
                if ($this->archiveProjectReadonly($projectId, $userId)) {
                    $summary['projects_archived']++;
                }
            }
        }

        return $summary;
    }

    public function nextTaskPosition(int $columnId): int
    {
        return $this->tasks()->nextTaskPosition($columnId);
    }

    public function userCanSubmitProjectItem(int $projectId, int $userId): bool
    {
        return $this->permissions()->canSubmitProjectItem($projectId, $userId);
    }

    public function userCanCommentProjectItem(int $projectId, int $userId): bool
    {
        return $this->permissions()->canCommentProjectItem($projectId, $userId);
    }

    public function userCanManageEvidenceItem(array $evidence, int $userId): bool
    {
        return $this->permissions()->canManageEvidenceItem($evidence, $userId);
    }

    public function objectBelongsToProject(int $projectId, string $objectType, int $objectId): bool
    {
        return $this->competencies()->objectBelongsToProject($projectId, $objectType, $objectId);
    }

    public static function evidenceUploadMimes(): array
    {
        return [
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'sql' => 'text/plain',
            'py' => 'text/plain',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'zip' => 'application/zip',
        ];
    }

    public static function normalizedEvidenceUploadExtension(string $filename): string
    {
        return PrivateUploadValidator::normalizedExtension($filename, self::EVIDENCE_UPLOAD_ALLOWED_EXTENSIONS);
    }

    public static function validateEvidenceUploadFile(array $file)
    {
        $textLikeDeclaredMimes = ['', 'text/plain', 'application/octet-stream', 'text/csv', 'application/json', 'text/markdown', 'application/sql', 'text/x-python'];

        return PrivateUploadValidator::validateUploadedFile($file, [
            'allowed_mimes' => self::evidenceUploadMimes(),
            'allowed_extensions' => self::EVIDENCE_UPLOAD_ALLOWED_EXTENSIONS,
            'blocked_extensions' => self::EVIDENCE_UPLOAD_BLOCKED_EXTENSIONS,
            'max_size' => self::EVIDENCE_UPLOAD_MAX_BYTES,
            'reject_raw_dotfiles' => true,
            'require_uploaded_file' => true,
            'allowed_blocked_parts' => [
                'html.txt' => ['html'],
                'css.txt' => ['css'],
                'js.txt' => ['js'],
            ],
            'fallback_declared_mimes' => [
                'txt' => $textLikeDeclaredMimes,
                'md' => $textLikeDeclaredMimes,
                'csv' => $textLikeDeclaredMimes,
                'json' => $textLikeDeclaredMimes,
                'sql' => $textLikeDeclaredMimes,
                'py' => $textLikeDeclaredMimes,
                'html.txt' => $textLikeDeclaredMimes,
                'css.txt' => $textLikeDeclaredMimes,
                'js.txt' => $textLikeDeclaredMimes,
            ],
            'codes' => [
                'invalid_filename' => 'ouinpo_projects_bad_filename',
                'upload_error' => 'ouinpo_projects_upload_error',
                'empty_file' => 'ouinpo_projects_upload_size',
                'file_too_large' => 'ouinpo_projects_upload_size',
                'unsupported_file_type' => 'ouinpo_projects_upload_type',
                'dangerous_extension' => 'ouinpo_projects_upload_dangerous',
                'missing_tmp_file' => 'ouinpo_projects_upload_missing',
                'mime_mismatch' => 'ouinpo_projects_upload_mime',
                'mime_not_allowed' => 'ouinpo_projects_upload_mime',
            ],
            'messages' => [
                'invalid_filename' => 'Nom de fichier invalide.',
                'upload_error' => 'Transfert de fichier incomplet.',
                'empty_file' => 'Fichier vide ou trop volumineux.',
                'file_too_large' => 'Fichier vide ou trop volumineux.',
                'unsupported_file_type' => 'Extension de fichier non autorisee.',
                'dangerous_extension' => 'Extension dangereuse refusee.',
                'missing_tmp_file' => 'Fichier temporaire manquant.',
                'mime_mismatch' => 'Le type du fichier ne correspond pas a son extension.',
                'mime_not_allowed' => 'Type MIME non autorise.',
            ],
        ]);
    }

    public static function decorateEvidenceAttachment(array $row): array
    {
        return ProjectEvidenceService::decorateEvidenceAttachment($row);
    }

    private function appendProjectClosureFields(array &$payload, array &$formats, array $data): void
    {
        $table = $this->projectsTable();
        $map = [
            'origin_year_id' => ['%d', static fn($value) => self::cleanNullableId($value)],
            'current_year_id' => ['%d', static fn($value) => self::cleanNullableId($value)],
            'origin_group_id' => ['%d', static fn($value) => self::cleanNullableId($value)],
            'current_group_id' => ['%d', static fn($value) => self::cleanNullableId($value)],
            'cycle_id' => ['%d', static fn($value) => self::cleanNullableId($value)],
            'lifecycle_status' => ['%s', static fn($value) => self::cleanProjectLifecycleStatus($value)],
            'closure_policy' => ['%s', static fn($value) => self::cleanClosurePolicy($value)],
            'is_portfolio_relevant' => ['%d', static fn($value) => !empty($value) ? 1 : 0],
            'preserve_until' => ['%s', static fn($value) => self::cleanDate($value)],
            'archived_at' => ['%s', static fn($value) => self::cleanDateTime($value)],
            'archived_by' => ['%d', static fn($value) => self::cleanNullableId($value)],
        ];

        foreach ($map as $field => [$format, $cleaner]) {
            if (!array_key_exists($field, $data) || !$this->columnExists($table, $field)) {
                continue;
            }
            $payload[$field] = $cleaner($data[$field]);
            $formats[] = $format;
        }
    }

    private function updateProjectClosureState(int $projectId, array $updates): bool
    {
        global $wpdb;

        $table = $this->projectsTable();
        $clean = [];
        $formats = [];

        foreach ($updates as $field => $value) {
            if (!$this->columnExists($table, $field) && $field !== 'updated_at') {
                continue;
            }
            if (in_array($field, ['origin_year_id', 'current_year_id', 'origin_group_id', 'current_group_id', 'cycle_id', 'archived_by', 'is_portfolio_relevant'], true)) {
                $clean[$field] = $value === null ? null : (int) $value;
                $formats[] = '%d';
            } else {
                $clean[$field] = $value;
                $formats[] = '%s';
            }
        }

        if (!$clean) {
            return true;
        }

        return false !== $wpdb->update($table, $clean, ['id' => $projectId], $formats, ['%d']);
    }

    private function markMembersArchiveViewers(int $projectId): void
    {
        global $wpdb;

        $table = $this->table('members');
        if (!$this->columnExists($table, 'access_level')) {
            return;
        }

        $updates = [
            'access_level' => 'archive_viewer',
            'can_edit' => 0,
            'can_comment' => 0,
            'can_export' => 1,
            'archived_at' => current_time('mysql'),
        ];
        $formatMap = [
            'access_level' => '%s',
            'can_edit' => '%d',
            'can_comment' => '%d',
            'can_export' => '%d',
            'archived_at' => '%s',
        ];
        $formats = [];

        foreach (array_keys($updates) as $field) {
            if (!$this->columnExists($table, $field)) {
                unset($updates[$field]);
                continue;
            }
            $formats[] = $formatMap[$field];
        }

        if ($updates) {
            $wpdb->update($table, $updates, ['project_id' => $projectId], $formats, ['%d']);
        }
    }

    private function markProjectMemberArchiveViewer(int $projectId, int $userId): bool
    {
        global $wpdb;

        $table = $this->table('members');
        if ($projectId <= 0 || $userId <= 0 || !$this->tableExists($table)) {
            return false;
        }

        $updates = [
            'access_level' => 'archive_viewer',
            'can_edit' => 0,
            'can_comment' => 0,
            'can_export' => 1,
            'archived_at' => current_time('mysql'),
        ];
        $formatMap = [
            'access_level' => '%s',
            'can_edit' => '%d',
            'can_comment' => '%d',
            'can_export' => '%d',
            'archived_at' => '%s',
        ];
        $formats = [];

        foreach (array_keys($updates) as $field) {
            if (!$this->columnExists($table, $field)) {
                unset($updates[$field]);
                continue;
            }
            $formats[] = $formatMap[$field];
        }

        if (!$updates) {
            return true;
        }

        return false !== $wpdb->update(
            $table,
            $updates,
            ['project_id' => $projectId, 'user_id' => $userId],
            $formats,
            ['%d', '%d']
        );
    }

    private function projectHasActiveNonAlumniMembers(int $projectId, int $exitingUserId): bool
    {
        global $wpdb;

        $table = $this->table('members');
        if ($projectId <= 0 || !$this->tableExists($table)) {
            return false;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE project_id = %d AND user_id <> %d",
            $projectId,
            $exitingUserId
        ), ARRAY_A) ?: [];

        foreach ($rows as $row) {
            $memberUserId = (int) ($row['user_id'] ?? 0);
            if ($memberUserId <= 0) {
                continue;
            }
            $accessLevel = sanitize_key((string) ($row['access_level'] ?? $row['role'] ?? 'member'));
            if (in_array($accessLevel, ['archive_viewer', 'former_member', 'viewer'], true)) {
                continue;
            }
            if (array_key_exists('can_edit', $row) && (int) $row['can_edit'] !== 1 && array_key_exists('can_comment', $row) && (int) $row['can_comment'] !== 1) {
                continue;
            }
            $user = get_user_by('id', $memberUserId);
            if ($user && !in_array('ouinpo_alumni', (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }

    private function isUsefulAlumniProject(array $project): bool
    {
        $projectId = (int) ($project['id'] ?? 0);
        if ($projectId <= 0) {
            return false;
        }

        $lifecycle = sanitize_key((string) ($project['lifecycle_status'] ?? $project['status'] ?? 'active'));
        if ($this->isPortfolioRelevantProject($project) || in_array($lifecycle, ['active', 'completed', 'finished', 'frozen', 'archived', 'portfolio_archive'], true)) {
            return true;
        }

        return $this->projectHasRows($this->table('deliverables'), $projectId, 'project_id', "is_portfolio_evidence = 1 OR status = 'validated'")
            || $this->projectHasRows($this->table('evidence'), $projectId, 'project_id', 'is_portfolio_evidence = 1')
            || $this->projectHasRows($this->table('competency_links'), $projectId, 'project_id');
    }

    private function projectHasRows(string $table, int $projectId, string $projectColumn, string $extraWhere = ''): bool
    {
        global $wpdb;

        if ($projectId <= 0 || !$this->tableExists($table) || !$this->columnExists($table, $projectColumn)) {
            return false;
        }

        if ($extraWhere !== '') {
            foreach (['is_portfolio_evidence', 'status'] as $column) {
                if (str_contains($extraWhere, $column) && !$this->columnExists($table, $column)) {
                    return false;
                }
            }
        }

        $where = "{$projectColumn} = %d";
        if ($extraWhere !== '') {
            $where .= " AND ({$extraWhere})";
        }

        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", $projectId)) > 0;
    }

    private function classSlugCandidatesForGroup(int $groupId): array
    {
        global $wpdb;

        $groups = $wpdb->prefix . 'ouin_exo_groups';
        if ($groupId <= 0 || !$this->tableExists($groups)) {
            return [];
        }

        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$groups} WHERE id = %d LIMIT 1", $groupId), ARRAY_A);
        if (!$group) {
            return [(string) $groupId];
        }

        $label = (string) ($group['label'] ?? '');
        $candidates = [
            (string) $groupId,
            sanitize_key($label),
            sanitize_title($label),
        ];

        foreach (['slug', 'class_slug'] as $field) {
            if (!empty($group[$field])) {
                $candidates[] = sanitize_key((string) $group[$field]);
                $candidates[] = sanitize_title((string) $group[$field]);
            }
        }

        return array_values(array_unique(array_filter($candidates, static fn($value) => (string) $value !== '')));
    }

    private function carriableProjectSql(string $alias = ''): string
    {
        $table = $this->projectsTable();
        $prefix = $alias !== '' ? $alias . '.' : '';
        $clauses = [];

        if ($this->columnExists($table, 'lifecycle_status')) {
            $clauses[] = "{$prefix}lifecycle_status NOT IN ('archived', 'portfolio_archive', 'pending_deletion', 'deleted')";
        }
        if ($this->columnExists($table, 'status')) {
            $clauses[] = "{$prefix}status NOT IN ('archived', 'portfolio_archive', 'pending_deletion', 'deleted')";
        }

        return $clauses ? ' AND ' . implode(' AND ', $clauses) : '';
    }

    public function columnExists(string $table, string $column): bool
    {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }

    private function competencyExists(int $competencyId): bool
    {
        return $this->competencies()->competencyExists($competencyId);
    }

    public function tableExists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function uniqueProjectSlug(string $slug, int $ignoreProjectId = 0): string
    {
        global $wpdb;

        $slug = $slug !== '' ? $slug : 'projet';
        $base = $slug;
        $i = 2;

        while (true) {
            if ($ignoreProjectId > 0) {
                $exists = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->projectsTable()} WHERE slug = %s AND id <> %d",
                    $slug,
                    $ignoreProjectId
                ));
            } else {
                $exists = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->projectsTable()} WHERE slug = %s",
                    $slug
                ));
            }

            if ($exists === 0) {
                return $slug;
            }

            $slug = substr($base, 0, 180) . '-' . $i;
            $i++;
        }
    }

    private function permissions(): ProjectPermissionService
    {
        return new ProjectPermissionService($this);
    }

    private function columns(): ProjectColumnService
    {
        return new ProjectColumnService($this);
    }

    private function competencies(): ProjectCompetencyService
    {
        return new ProjectCompetencyService($this);
    }

    private function deliverables(): ProjectDeliverableService
    {
        return new ProjectDeliverableService($this);
    }

    private function evidence(): ProjectEvidenceService
    {
        return new ProjectEvidenceService($this);
    }

    private function journal(): ProjectJournalService
    {
        return new ProjectJournalService($this);
    }

    private function checklist(): ProjectChecklistService
    {
        return new ProjectChecklistService($this);
    }

    private function members(): ProjectMemberService
    {
        return new ProjectMemberService($this);
    }

    private function tasks(): ProjectTaskService
    {
        return new ProjectTaskService($this);
    }

    public static function cleanTitle($value): string
    {
        return substr(sanitize_text_field((string) $value), 0, 190);
    }

    public static function cleanSlug($value, string $fallbackTitle = ''): string
    {
        $slug = sanitize_title((string) $value);

        if ($slug === '' && $fallbackTitle !== '') {
            $slug = sanitize_title($fallbackTitle);
        }

        return substr($slug, 0, 190);
    }

    public static function cleanNullableText($value, int $max = 100): ?string
    {
        $clean = substr(sanitize_text_field((string) $value), 0, $max);

        return $clean === '' ? null : $clean;
    }

    public static function cleanNullableKey($value, int $max = 100): ?string
    {
        $clean = substr(sanitize_key((string) $value), 0, $max);

        return $clean === '' ? null : $clean;
    }

    public static function cleanLongText($value): string
    {
        return wp_kses_post((string) $value);
    }

    public static function cleanDate($value): ?string
    {
        $value = sanitize_text_field((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    public static function cleanDateTime($value): ?string
    {
        $value = sanitize_text_field((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) ? $value : null;
    }

    public static function cleanProjectStatus($value): string
    {
        $status = sanitize_key((string) $value);

        return in_array($status, self::STATUS, true) ? $status : 'draft';
    }

    public static function cleanProjectLifecycleStatus($value): string
    {
        $status = sanitize_key((string) $value);

        return in_array($status, self::LIFECYCLE_STATUS, true) ? $status : 'active';
    }

    public static function cleanClosurePolicy($value): string
    {
        $policy = sanitize_key((string) $value);

        return in_array($policy, self::CLOSURE_POLICY, true) ? $policy : 'auto';
    }

    public static function cleanMemberRole($value): string
    {
        $role = sanitize_key((string) $value);

        return in_array($role, self::MEMBER_ROLES, true) ? $role : 'member';
    }

    public static function cleanPriority($value): string
    {
        $priority = sanitize_key((string) $value);

        return in_array($priority, self::PRIORITIES, true) ? $priority : 'normal';
    }

    public static function cleanTaskStatus($value): string
    {
        $status = sanitize_key((string) $value);

        return in_array($status, self::TASK_STATUS, true) ? $status : 'open';
    }

    public static function cleanDeliverableType($value): string
    {
        $type = sanitize_key((string) $value);

        return in_array($type, self::DELIVERABLE_TYPES, true) ? $type : 'other';
    }

    public static function cleanDeliverableStatus($value): string
    {
        $status = sanitize_key((string) $value);

        return in_array($status, self::DELIVERABLE_STATUS, true) ? $status : 'expected';
    }

    public static function cleanEvidenceType($value): string
    {
        $type = sanitize_key((string) $value);

        return in_array($type, self::EVIDENCE_TYPES, true) ? $type : 'link';
    }

    public static function cleanCompetencyObjectType($value): string
    {
        $type = sanitize_key((string) $value);

        return in_array($type, self::COMPETENCY_OBJECT_TYPES, true) ? $type : 'project';
    }

    public static function cleanUrl($value): ?string
    {
        $url = esc_url_raw((string) $value);

        return $url === '' ? null : $url;
    }

    public static function cleanNullableId($value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
