<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\Storage\PrivateUploadValidator;

defined('ABSPATH') || exit;

final class Repository
{
    public const STATUS = ['draft', 'active', 'finished', 'archived'];
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

        $inserted = $wpdb->insert(
            $this->projectsTable(),
            [
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
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
        );

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
        global $wpdb;

        if ($projectId <= 0) {
            return;
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table('columns')} WHERE project_id = %d",
            $projectId
        ));

        if ($exists > 0) {
            return;
        }

        $now = current_time('mysql');
        foreach (self::defaultColumns() as $position => $column) {
            $wpdb->insert(
                $this->table('columns'),
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

    public function getBoard(int $projectId): array
    {
        return (new ProjectBoardService($this))->getBoard($projectId);
    }

    public function getFirstColumnId(int $projectId): int
    {
        global $wpdb;

        $this->ensureDefaultColumns($projectId);

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table('columns')} WHERE project_id = %d ORDER BY position ASC, id ASC LIMIT 1",
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
            "SELECT id FROM {$this->table('columns')} WHERE project_id = %d AND status_key = %s ORDER BY position ASC, id ASC LIMIT 1",
            $projectId,
            $statusKey
        ));

        return $columnId > 0 ? $columnId : $this->getFirstColumnId($projectId);
    }

    public function columnBelongsToProject(int $columnId, int $projectId): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table('columns')} WHERE id = %d AND project_id = %d",
            $columnId,
            $projectId
        )) > 0;
    }

    public function createTask(int $projectId, array $data, int $userId): int
    {
        global $wpdb;

        $title = self::cleanTitle($data['title'] ?? '');
        if ($title === '') {
            return 0;
        }

        $columnId = (int) ($data['column_id'] ?? 0);
        if ($columnId <= 0 || !$this->columnBelongsToProject($columnId, $projectId)) {
            $columnId = $this->getFirstColumnId($projectId);
        }

        $position = $this->nextTaskPosition($columnId);

        $assignedUserId = self::cleanNullableId($data['assigned_user_id'] ?? null);
        if ($assignedUserId !== null && !$this->isProjectMember($projectId, $assignedUserId)) {
            $assignedUserId = null;
        }

        $inserted = $wpdb->insert(
            $this->table('tasks'),
            [
                'project_id' => $projectId,
                'column_id' => $columnId,
                'title' => $title,
                'description' => self::cleanLongText($data['description'] ?? ''),
                'assigned_user_id' => $assignedUserId,
                'priority' => self::cleanPriority($data['priority'] ?? 'normal'),
                'due_date' => self::cleanDate($data['due_date'] ?? ''),
                'position' => $position,
                'status' => self::cleanTaskStatus($data['status'] ?? 'open'),
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
            $title = self::cleanTitle($data['title']);
            if ($title !== '') {
                $updates['title'] = $title;
                $formats[] = '%s';
            }
        }

        if (array_key_exists('description', $data)) {
            $updates['description'] = self::cleanLongText($data['description']);
            $formats[] = '%s';
        }

        if (array_key_exists('assigned_user_id', $data)) {
            $assignedUserId = self::cleanNullableId($data['assigned_user_id']);
            if ($assignedUserId !== null && !$this->isProjectMember((int) $task['project_id'], $assignedUserId)) {
                $assignedUserId = null;
            }
            $updates['assigned_user_id'] = $assignedUserId;
            $formats[] = '%d';
        }

        if (array_key_exists('priority', $data)) {
            $updates['priority'] = self::cleanPriority($data['priority']);
            $formats[] = '%s';
        }

        if (array_key_exists('due_date', $data)) {
            $updates['due_date'] = self::cleanDate($data['due_date']);
            $formats[] = '%s';
        }

        if (array_key_exists('status', $data)) {
            $updates['status'] = self::cleanTaskStatus($data['status']);
            $formats[] = '%s';
        }

        if (!$updates) {
            return true;
        }

        $updates['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        return false !== $wpdb->update(
            $this->table('tasks'),
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
        if (!$task || !$this->columnBelongsToProject($columnId, (int) $task['project_id'])) {
            return false;
        }

        return false !== $wpdb->update(
            $this->table('tasks'),
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
            $this->table('tasks'),
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
            "SELECT * FROM {$this->table('tasks')} WHERE id = %d LIMIT 1",
            $taskId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function getMembers(int $projectId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.*, u.display_name, u.user_email
             FROM {$this->table('members')} pm
             LEFT JOIN {$wpdb->users} u ON u.ID = pm.user_id
             WHERE pm.project_id = %d
             ORDER BY pm.role ASC, u.display_name ASC, pm.user_id ASC",
            $projectId
        ), ARRAY_A);

        return $rows ?: [];
    }

    public function addMember(int $projectId, int $userId, string $role = 'member'): bool
    {
        global $wpdb;

        if ($projectId <= 0 || $userId <= 0) {
            return false;
        }

        $role = self::cleanMemberRole($role);
        $now = current_time('mysql');

        $sql = $wpdb->prepare(
            "INSERT INTO {$this->table('members')} (project_id, user_id, role, created_at)
             VALUES (%d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE role = VALUES(role)",
            $projectId,
            $userId,
            $role,
            $now
        );

        return false !== $wpdb->query($sql);
    }

    public function removeMember(int $projectId, int $userId): bool
    {
        global $wpdb;

        return false !== $wpdb->delete(
            $this->table('members'),
            ['project_id' => $projectId, 'user_id' => $userId],
            ['%d', '%d']
        );
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
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name
             FROM {$this->table('task_comments')} c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id
             WHERE c.task_id = %d
             ORDER BY c.created_at ASC, c.id ASC",
            $taskId
        ), ARRAY_A) ?: [];
    }

    public function addComment(int $taskId, int $userId, string $comment): int
    {
        global $wpdb;

        $comment = self::cleanLongText($comment);
        if ($taskId <= 0 || $userId <= 0 || $comment === '') {
            return 0;
        }

        $inserted = $wpdb->insert(
            $this->table('task_comments'),
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

    public function getChecklistForTask(int $taskId): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table('checklist_items')} WHERE task_id = %d ORDER BY position ASC, id ASC",
            $taskId
        ), ARRAY_A) ?: [];
    }

    public function getChecklistItem(int $itemId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table('checklist_items')} WHERE id = %d LIMIT 1",
            $itemId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function addChecklistItem(int $taskId, string $label): int
    {
        global $wpdb;

        $label = self::cleanTitle($label);
        if ($taskId <= 0 || $label === '') {
            return 0;
        }

        $position = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->table('checklist_items')} WHERE task_id = %d",
            $taskId
        ));

        $inserted = $wpdb->insert(
            $this->table('checklist_items'),
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
            $label = self::cleanTitle($data['label']);
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
            $this->table('checklist_items'),
            $updates,
            ['id' => $itemId],
            $formats,
            ['%d']
        );
    }

    public function deleteChecklistItem(int $itemId): bool
    {
        global $wpdb;

        return false !== $wpdb->delete($this->table('checklist_items'), ['id' => $itemId], ['%d']);
    }

    public function getLogs(int $projectId): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name
             FROM {$this->table('logs')} l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
             WHERE l.project_id = %d
             ORDER BY l.created_at DESC, l.id DESC",
            $projectId
        ), ARRAY_A) ?: [];
    }

    public function addLog(int $projectId, int $userId, array $data): int
    {
        global $wpdb;

        $workDone = self::cleanLongText($data['work_done'] ?? '');
        if ($projectId <= 0 || $userId <= 0 || $workDone === '') {
            return 0;
        }

        $inserted = $wpdb->insert(
            $this->table('logs'),
            [
                'project_id' => $projectId,
                'user_id' => $userId,
                'work_done' => $workDone,
                'blockers' => self::cleanLongText($data['blockers'] ?? ''),
                'decision_taken' => self::cleanLongText($data['decision_taken'] ?? ''),
                'next_step' => self::cleanLongText($data['next_step'] ?? ''),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
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
        $task = $this->getTask($taskId);

        return $task && (int) $task['project_id'] === $projectId;
    }

    public function getCompetencyLinks(int $projectId, string $objectType = '', int $objectId = 0): array
    {
        global $wpdb;

        $competencies = $wpdb->prefix . 'ouin_exo_competencies';
        $where = ['l.project_id = %d'];
        $args = [$projectId];

        if ($objectType !== '') {
            $where[] = 'l.object_type = %s';
            $args[] = self::cleanCompetencyObjectType($objectType);
        }

        if ($objectId > 0) {
            $where[] = 'l.object_id = %d';
            $args[] = $objectId;
        }

        $join = $this->tableExists($competencies)
            ? "LEFT JOIN {$competencies} c ON c.id = l.competency_id"
            : '';
        $selectCompetency = $join !== ''
            ? "c.domain, c.domain_slug, c.competency, c.label"
            : "'' AS domain, '' AS domain_slug, '' AS competency, '' AS label";

        $sql = "SELECT l.*, {$selectCompetency}
             FROM {$this->table('competency_links')} l
             {$join}
             WHERE " . implode(' AND ', $where) . '
             ORDER BY l.object_type ASC, l.object_id ASC, l.id ASC';

        return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
    }

    public function getCompetencyLink(int $linkId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table('competency_links')} WHERE id = %d LIMIT 1",
            $linkId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function addCompetencyLink(int $projectId, string $objectType, int $objectId, int $competencyId, int $userId): int
    {
        global $wpdb;

        $objectType = self::cleanCompetencyObjectType($objectType);
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
            "INSERT IGNORE INTO {$this->table('competency_links')}
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
            "SELECT id FROM {$this->table('competency_links')}
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

        return false !== $wpdb->delete($this->table('competency_links'), ['id' => $linkId], ['%d']);
    }

    public function getAvailableCompetencies(int $limit = 500): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_competencies';
        if (!$this->tableExists($table)) {
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

    public function getMainTasks(int $projectId, int $limit = 12): array
    {
        global $wpdb;

        $limit = max(1, min(50, $limit));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table('tasks')}
             WHERE project_id = %d AND status <> 'archived'
             ORDER BY status ASC, priority DESC, due_date ASC, id ASC
             LIMIT %d",
            $projectId,
            $limit
        ), ARRAY_A) ?: [];
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

    public function nextTaskPosition(int $columnId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$this->table('tasks')} WHERE column_id = %d",
            $columnId
        ));
    }

    public function userCanSubmitProjectItem(int $projectId, int $userId): bool
    {
        return $this->permissions()->canSubmitProjectItem($projectId, $userId);
    }

    public function userCanManageEvidenceItem(array $evidence, int $userId): bool
    {
        return $this->permissions()->canManageEvidenceItem($evidence, $userId);
    }

    public function objectBelongsToProject(int $projectId, string $objectType, int $objectId): bool
    {
        if ($projectId <= 0 || $objectId <= 0) {
            return false;
        }

        $objectType = self::cleanCompetencyObjectType($objectType);

        if ($objectType === 'project') {
            return $objectId === $projectId && null !== $this->getProject($projectId);
        }

        if ($objectType === 'task') {
            $task = $this->getTask($objectId);
            return $task && (int) $task['project_id'] === $projectId;
        }

        if ($objectType === 'deliverable') {
            $deliverable = $this->getDeliverable($objectId);
            return $deliverable && (int) $deliverable['project_id'] === $projectId;
        }

        if ($objectType === 'evidence') {
            $evidence = $this->getEvidenceItem($objectId);
            return $evidence && (int) $evidence['project_id'] === $projectId;
        }

        return false;
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

    private function competencyExists(int $competencyId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_competencies';
        if ($competencyId <= 0 || !$this->tableExists($table)) {
            return false;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = %d",
            $competencyId
        )) > 0;
    }

    private function tableExists(string $table): bool
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

    private function deliverables(): ProjectDeliverableService
    {
        return new ProjectDeliverableService($this);
    }

    private function evidence(): ProjectEvidenceService
    {
        return new ProjectEvidenceService($this);
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

    public static function cleanProjectStatus($value): string
    {
        $status = sanitize_key((string) $value);

        return in_array($status, self::STATUS, true) ? $status : 'draft';
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
