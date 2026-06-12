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

        return $this->canManageProjectRow($project, $userId)
            || ($this->userHasCap($userId, Capabilities::PROJECTS_VIEW_OWN) && $this->isProjectMember($projectId, $userId));
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

        return $this->canViewProject((int) $task['project_id'], $userId)
            && (
                (int) ($task['created_by'] ?? 0) === $userId
                || (int) ($task['assigned_user_id'] ?? 0) === $userId
            );
    }

    public function canCreateTask(int $projectId, int $userId = 0): bool
    {
        $userId = $this->resolveUserId($userId);

        return $this->canManageProject($projectId, $userId)
            || $this->userHasCap($userId, Capabilities::PROJECTS_EDIT_OWN_TASKS);
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

        return $this->userHasCap($userId, Capabilities::PROJECTS_COMMENT)
            && $this->canViewProject($projectId, $userId);
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
            && $this->isProjectMember((int) ($project['id'] ?? 0), $userId)
            && $this->userHasCap($userId, Capabilities::PROJECTS_AI_STUDENT_USE);
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
