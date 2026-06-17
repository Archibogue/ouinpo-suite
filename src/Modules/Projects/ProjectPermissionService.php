<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class ProjectPermissionService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function isProjectMember(int $projectId, int $userId = 0): bool
    {
        global $wpdb;

        $userId = $this->resolveUserId($userId);
        if ($projectId <= 0 || $userId <= 0) {
            return false;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->repository->table('members')} WHERE project_id = %d AND user_id = %d",
            $projectId,
            $userId
        )) > 0;
    }

    public function canViewProject(int $projectId, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);
        $project = $this->repository->getProject($projectId);
        if (!$project || $userId <= 0) {
            return false;
        }

        if ($this->canManageProjectRow($project, $userId)) {
            return true;
        }

        $member = $this->getProjectMemberRow($projectId, $userId);
        if (!$member) {
            return false;
        }

        $lifecycle = sanitize_key((string) ($project['lifecycle_status'] ?? $project['status'] ?? 'active'));
        $archiveStatus = in_array($lifecycle, ['archived', 'portfolio_archive', 'frozen'], true);
        $accessLevel = sanitize_key((string) ($member['access_level'] ?? $member['role'] ?? 'member'));

        if ($this->isArchiveMemberAccess($accessLevel)) {
            return $this->userHasCap($userId, Capabilities::PORTFOLIO_VIEW_OWN_ARCHIVE);
        }

        if ($archiveStatus) {
            return $this->userHasCap($userId, Capabilities::PORTFOLIO_VIEW_OWN_ARCHIVE)
                || $this->userHasCap($userId, Capabilities::PROJECTS_VIEW_OWN);
        }

        return $this->userHasCap($userId, Capabilities::PROJECTS_VIEW_OWN);
    }

    public function canManageProject(int $projectId, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);
        $project = $this->repository->getProject($projectId);

        return $project ? $this->canManageProjectRow($project, $userId) : false;
    }

    public function canManageProjectRow(array $project, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);
        if ($this->canManageAllProjects($userId)) {
            return true;
        }

        if (!$this->userHasCap($userId, Capabilities::PROJECTS_MANAGE_CLASS) && !$this->userHasCap($userId, Capabilities::PROJECTS_CREATE)) {
            return false;
        }

        return (int) ($project['teacher_id'] ?? 0) === $userId
            || (int) ($project['created_by'] ?? 0) === $userId;
    }

    public function canManageAllProjects(int $userId = 0): bool
    {
        return $this->userHasCap($this->resolveUserId($userId), Capabilities::PROJECTS_MANAGE_ALL);
    }

    public function canCreateOrManageProjects(int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);

        return $this->userHasCap($userId, Capabilities::PROJECTS_CREATE)
            || $this->userHasCap($userId, Capabilities::PROJECTS_MANAGE_CLASS)
            || $this->userHasCap($userId, Capabilities::PROJECTS_MANAGE_ALL);
    }

    public function canEditTask(array $task, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);
        if ($this->canManageProject((int) $task['project_id'], $userId)) {
            return true;
        }

        if (!$this->userHasCap($userId, Capabilities::PROJECTS_EDIT_OWN_TASKS)) {
            return false;
        }

        if (!$this->memberAllows($userId, (int) $task['project_id'], 'can_edit')) {
            return false;
        }

        return $this->canViewProject((int) $task['project_id'], $userId)
            && (
                (int) ($task['created_by'] ?? 0) === $userId
                || (int) ($task['assigned_user_id'] ?? 0) === $userId
            );
    }

    public function canCreateTask(int $projectId, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);

        if ($this->canManageProject($projectId, $userId)) {
            return true;
        }

        return $this->memberAllows($userId, $projectId, 'can_edit')
            && $this->userHasCap($userId, Capabilities::PROJECTS_EDIT_OWN_TASKS);
    }

    public function canCommentOrLog(int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);

        return $this->userHasCap($userId, Capabilities::PROJECTS_COMMENT)
            || $this->userHasCap($userId, Capabilities::PROJECTS_MANAGE_CLASS)
            || $this->userHasCap($userId, Capabilities::PROJECTS_MANAGE_ALL);
    }

    public function canSubmitProjectItem(int $projectId, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);
        if ($this->canManageProject($projectId, $userId)) {
            return true;
        }

        return ($this->memberAllows($userId, $projectId, 'can_comment') || $this->memberAllows($userId, $projectId, 'can_edit'))
            && ($this->userHasCap($userId, Capabilities::PROJECTS_COMMENT) || $this->userHasCap($userId, Capabilities::PROJECTS_EDIT_OWN_TASKS))
            && $this->canViewProject($projectId, $userId);
    }

    public function canCommentProjectItem(int $projectId, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);
        if ($this->canManageProject($projectId, $userId)) {
            return true;
        }

        return $this->memberAllows($userId, $projectId, 'can_comment')
            && $this->userHasCap($userId, Capabilities::PROJECTS_COMMENT)
            && $this->canViewProject($projectId, $userId);
    }

    public function canExportProject(int $projectId, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);
        if ($this->canManageProject($projectId, $userId)) {
            return true;
        }

        if (!$this->canViewProject($projectId, $userId)) {
            return false;
        }

        $member = $this->getProjectMemberRow($projectId, $userId);
        if (!$member) {
            return false;
        }

        if (array_key_exists('can_export', $member) && (int) $member['can_export'] !== 1) {
            return false;
        }

        $accessLevel = sanitize_key((string) ($member['access_level'] ?? $member['role'] ?? 'member'));
        if ($this->isArchiveMemberAccess($accessLevel)) {
            return $this->userHasCap($userId, Capabilities::PORTFOLIO_EXPORT_OWN);
        }

        return $this->userHasCap($userId, Capabilities::PORTFOLIO_EXPORT_OWN)
            || $this->userHasCap($userId, Capabilities::PROJECTS_VIEW_OWN);
    }

    public function canManageEvidenceItem(array $evidence, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);
        if ($this->canManageProject((int) $evidence['project_id'], $userId)) {
            return true;
        }

        return (int) ($evidence['user_id'] ?? 0) === $userId
            && $this->canSubmitProjectItem((int) $evidence['project_id'], $userId);
    }

    public function canValidateDeliverables(int $userId = 0): bool
    {
        return $this->userHasCap($this->resolveUserId($userId), Capabilities::PROJECTS_VALIDATE);
    }

    public function canUseProjectAi(int $projectId, int $userId = 0, string $capability = Capabilities::PROJECTS_AI_USE, bool $requireUseCapability = false): bool
    {
        $userId = $this->resolveUserId($userId);
        $hasCapability = $this->userHasCap($userId, $capability);
        if ($requireUseCapability) {
            $hasCapability = $hasCapability && $this->userHasCap($userId, Capabilities::PROJECTS_AI_USE);
        }

        return $hasCapability && $this->canManageProject($projectId, $userId);
    }

    public function canUseStudentAi(array $project, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);

        return ProjectsStudentAiAssistant::globalEnabled()
            && ProjectsStudentAiAssistant::projectStudentAiEnabled($project)
            && !in_array(sanitize_key((string) ($project['lifecycle_status'] ?? $project['status'] ?? '')), ['archived', 'portfolio_archive', 'frozen'], true)
            && $this->isProjectMember((int) ($project['id'] ?? 0), $userId)
            && $this->userHasCap($userId, Capabilities::PROJECTS_AI_STUDENT_USE);
    }

    public function getProjectMemberRow(int $projectId, int $userId): ?array
    {
        global $wpdb;

        if ($projectId <= 0 || $userId <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('members')} WHERE project_id = %d AND user_id = %d LIMIT 1",
            $projectId,
            $userId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private function memberAllows(int $userId, int $projectId, string $flag): bool
    {
        $member = $this->getProjectMemberRow($projectId, $userId);
        if (!$member) {
            return false;
        }

        if (!array_key_exists($flag, $member)) {
            return true;
        }

        return (int) $member[$flag] === 1;
    }

    private function isArchiveMemberAccess(string $accessLevel): bool
    {
        return in_array($accessLevel, ['archive_viewer', 'former_member', 'viewer'], true);
    }

    private function userHasCap(int $userId, string $capability): bool
    {
        return $userId > 0 && (user_can($userId, $capability) || user_can($userId, 'manage_options'));
    }

    private function resolveUserId(int $userId): int
    {
        if ($userId > 0) {
            return $userId;
        }

        return function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    }
}
