<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectMemberService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function getMembers(int $projectId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.*, u.display_name, u.user_email
             FROM {$this->repository->table('members')} pm
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

        $role = Repository::cleanMemberRole($role);
        $now = current_time('mysql');

        $sql = $wpdb->prepare(
            "INSERT INTO {$this->repository->table('members')} (project_id, user_id, role, created_at)
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
            $this->repository->table('members'),
            ['project_id' => $projectId, 'user_id' => $userId],
            ['%d', '%d']
        );
    }
}
