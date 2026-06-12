<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectEvidenceService
{
    private Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function getEvidence(int $projectId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, u.display_name, d.title AS deliverable_title, t.title AS task_title
             FROM {$this->repository->table('evidence')} e
             LEFT JOIN {$wpdb->users} u ON u.ID = e.user_id
             LEFT JOIN {$this->repository->table('deliverables')} d ON d.id = e.deliverable_id
             LEFT JOIN {$this->repository->table('tasks')} t ON t.id = e.task_id
             WHERE e.project_id = %d
             ORDER BY e.created_at DESC, e.id DESC",
            $projectId
        ), ARRAY_A) ?: [];

        return array_map([self::class, 'decorateEvidenceAttachment'], $rows);
    }

    public function getEvidenceItem(int $evidenceId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->repository->table('evidence')} WHERE id = %d LIMIT 1",
            $evidenceId
        ), ARRAY_A);

        return is_array($row) ? self::decorateEvidenceAttachment($row) : null;
    }

    public function createEvidence(int $projectId, array $data, int $userId): int
    {
        global $wpdb;

        $title = Repository::cleanTitle($data['title'] ?? '');
        if ($projectId <= 0 || $userId <= 0 || $title === '') {
            return 0;
        }

        $deliverableId = $this->validDeliverableId($projectId, $data['deliverable_id'] ?? null);
        $taskId = $this->validTaskId($projectId, $data['task_id'] ?? null);

        $inserted = $wpdb->insert(
            $this->repository->table('evidence'),
            [
                'project_id' => $projectId,
                'deliverable_id' => $deliverableId,
                'task_id' => $taskId,
                'user_id' => $userId,
                'title' => $title,
                'description' => Repository::cleanLongText($data['description'] ?? ''),
                'evidence_type' => Repository::cleanEvidenceType($data['evidence_type'] ?? 'link'),
                'url' => Repository::cleanUrl($data['url'] ?? ''),
                'attachment_id' => !empty($data['_allow_attachment']) ? Repository::cleanNullableId($data['attachment_id'] ?? null) : null,
                'created_at' => current_time('mysql'),
                'updated_at' => null,
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public function updateEvidence(int $evidenceId, array $data): bool
    {
        global $wpdb;

        $evidence = $this->getEvidenceItem($evidenceId);
        if (!$evidence) {
            return false;
        }

        $projectId = (int) $evidence['project_id'];
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

        if (array_key_exists('evidence_type', $data)) {
            $updates['evidence_type'] = Repository::cleanEvidenceType($data['evidence_type']);
            $formats[] = '%s';
        }

        if (array_key_exists('url', $data)) {
            $updates['url'] = Repository::cleanUrl($data['url']);
            $formats[] = '%s';
        }

        if (array_key_exists('deliverable_id', $data)) {
            $updates['deliverable_id'] = $this->validDeliverableId($projectId, $data['deliverable_id']);
            $formats[] = '%d';
        }

        if (array_key_exists('task_id', $data)) {
            $updates['task_id'] = $this->validTaskId($projectId, $data['task_id']);
            $formats[] = '%d';
        }

        if (!$updates) {
            return true;
        }

        $updates['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        return false !== $wpdb->update(
            $this->repository->table('evidence'),
            $updates,
            ['id' => $evidenceId],
            $formats,
            ['%d']
        );
    }

    public function deleteEvidence(int $evidenceId): bool
    {
        global $wpdb;

        $wpdb->delete(
            $this->repository->table('competency_links'),
            ['object_type' => 'evidence', 'object_id' => $evidenceId],
            ['%s', '%d']
        );

        return false !== $wpdb->delete($this->repository->table('evidence'), ['id' => $evidenceId], ['%d']);
    }

    public static function decorateEvidenceAttachment(array $row): array
    {
        $attachmentId = (int) ($row['attachment_id'] ?? 0);
        $row['attachment_id'] = $attachmentId > 0 ? $attachmentId : null;
        $row['attachment_filename'] = '';
        $row['attachment_mime'] = '';
        $row['attachment_size'] = null;
        $row['attachment_url'] = '';
        $row['attachment_exists'] = false;
        $row['attachment_is_private'] = false;

        if ($attachmentId <= 0) {
            return $row;
        }

        $post = get_post($attachmentId);
        if (!$post || $post->post_type !== 'attachment') {
            return $row;
        }

        $file = get_attached_file($attachmentId);
        $isPrivate = PrivateFiles::isPrivateAttachment($attachmentId);
        $privatePath = $isPrivate ? PrivateFiles::absolutePath(PrivateFiles::attachmentRelativePath($attachmentId)) : null;
        $url = $isPrivate ? PrivateFiles::downloadUrl((int) ($row['id'] ?? 0)) : wp_get_attachment_url($attachmentId);

        $row['attachment_exists'] = true;
        $row['attachment_is_private'] = $isPrivate;
        $row['attachment_filename'] = is_string($privatePath)
            ? wp_basename($privatePath)
            : ($file ? wp_basename((string) $file) : sanitize_file_name((string) $post->post_title));
        $row['attachment_mime'] = (string) get_post_mime_type($attachmentId);
        $row['attachment_url'] = $url ? (string) $url : (string) ($row['url'] ?? '');

        if (is_string($privatePath) && file_exists($privatePath)) {
            $row['attachment_size'] = (int) filesize($privatePath);
        } elseif ($file && file_exists((string) $file)) {
            $row['attachment_size'] = (int) filesize((string) $file);
        }

        return $row;
    }

    private function validDeliverableId(int $projectId, $value): ?int
    {
        $id = Repository::cleanNullableId($value);
        if ($id === null) {
            return null;
        }

        return $this->repository->deliverableBelongsToProject($id, $projectId) ? $id : null;
    }

    private function validTaskId(int $projectId, $value): ?int
    {
        $id = Repository::cleanNullableId($value);
        if ($id === null) {
            return null;
        }

        return $this->repository->taskBelongsToProject($id, $projectId) ? $id : null;
    }
}
