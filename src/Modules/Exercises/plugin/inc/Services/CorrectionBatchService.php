<?php

namespace Ouinpo\Exercises\Services;

defined('ABSPATH') || exit;

final class CorrectionBatchService
{
    private static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    public static function ensure_schema(): void
    {
        if (class_exists(\Ouinpo\Exercises\InstallV2::class)) {
            \Ouinpo\Exercises\InstallV2::maybe_upgrade();
        }
    }

    public static function assessments(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT a.id, a.title, a.group_id, a.due_on, g.label AS group_label
             FROM " . self::table('assessments') . " a
             LEFT JOIN " . self::table('groups') . " g ON g.id = a.group_id
             ORDER BY a.due_on DESC, a.id DESC
             LIMIT 100",
            ARRAY_A
        ) ?: [];
    }

    public static function assessment_context(int $assessment_id): array|\WP_Error
    {
        global $wpdb;
        if ($assessment_id <= 0) {
            return new \WP_Error('invalid_assessment', 'Devoir invalide.');
        }

        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, g.label AS group_label
             FROM " . self::table('assessments') . " a
             LEFT JOIN " . self::table('groups') . " g ON g.id = a.group_id
             WHERE a.id = %d
             LIMIT 1",
            $assessment_id
        ), ARRAY_A);

        if (!$assessment) {
            return new \WP_Error('assessment_not_found', 'Devoir introuvable.');
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT ai.exercise_id, ai.sort_order, ai.points,
                    e.title, e.statement,
                    d.label AS difficulty_label,
                    s.content AS solution_html
             FROM " . self::table('assessment_items') . " ai
             INNER JOIN " . self::table('exercises') . " e ON e.id = ai.exercise_id
             LEFT JOIN " . self::table('difficulties') . " d ON d.id = e.difficulty_id
             LEFT JOIN " . self::table('solutions') . " s ON s.exercise_id = e.id AND s.is_official = 1
             WHERE ai.assessment_id = %d
             ORDER BY ai.sort_order ASC, ai.id ASC",
            $assessment_id
        ), ARRAY_A) ?: [];

        foreach ($items as &$item) {
            $item['competencies'] = $wpdb->get_results($wpdb->prepare(
                "SELECT c.id, COALESCE(NULLIF(c.label, ''), c.competency) AS label
                 FROM " . self::table('exercise_competency') . " ec
                 INNER JOIN " . self::table('competencies') . " c ON c.id = ec.competency_id
                 WHERE ec.exercise_id = %d
                 ORDER BY c.domain ASC, c.id ASC",
                (int) $item['exercise_id']
            ), ARRAY_A) ?: [];
        }
        unset($item);

        return [
            'assessment' => $assessment,
            'items' => $items,
            'max_points' => array_sum(array_map(static fn(array $item): float => (float) ($item['points'] ?? 0), $items)),
        ];
    }

    public static function create_batch(int $assessment_id, int $group_id = 0, string $title = ''): int|\WP_Error
    {
        global $wpdb;
        self::ensure_schema();

        $context = self::assessment_context($assessment_id);
        if (is_wp_error($context)) {
            return $context;
        }

        $assessment = $context['assessment'];
        if ($group_id <= 0) {
            $group_id = (int) ($assessment['group_id'] ?? 0);
        }
        if ($title === '') {
            $title = 'Correction IA - ' . (string) ($assessment['title'] ?? ('DS #' . $assessment_id));
        }

        $ok = $wpdb->insert(self::table('correction_batches'), [
            'assessment_id' => $assessment_id,
            'group_id' => $group_id ?: null,
            'teacher_id' => get_current_user_id(),
            'source_type' => 'scan',
            'context_type' => 'assessment',
            'context_id' => $assessment_id,
            'status' => 'draft',
            'title' => sanitize_text_field($title),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);

        return $ok ? (int) $wpdb->insert_id : new \WP_Error('batch_create_failed', 'Impossible de créer le lot.');
    }

    public static function get_batch(int $batch_id): ?array
    {
        global $wpdb;
        if ($batch_id <= 0) {
            return null;
        }

        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, a.title AS assessment_title, g.label AS group_label
             FROM " . self::table('correction_batches') . " b
             LEFT JOIN " . self::table('assessments') . " a ON a.id = b.assessment_id
             LEFT JOIN " . self::table('groups') . " g ON g.id = b.group_id
             WHERE b.id = %d
             LIMIT 1",
            $batch_id
        ), ARRAY_A);

        if (!$batch) {
            return null;
        }

        $batch['copies'] = self::copies($batch_id);
        return $batch;
    }

    public static function copies(int $batch_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, batch_id, student_user_id, student_ref, source_type, file_name, file_path, file_url, mime_type, file_size, pages_count, ocr_text,
                    extraction_type, file_manifest, extracted_content, extraction_warnings,
                    status, error_message, ai_proposal, validated_correction, created_at, updated_at
             FROM " . self::table('correction_copies') . "
             WHERE batch_id = %d
             ORDER BY id ASC",
            $batch_id
        ), ARRAY_A) ?: [];
    }

    public static function delete_batch(int $batch_id): bool
    {
        global $wpdb;
        $batch = self::get_batch($batch_id);
        if (!$batch) {
            return false;
        }

        foreach ((array) ($batch['copies'] ?? []) as $copy) {
            if (!empty($copy['file_path']) && is_file((string) $copy['file_path'])) {
                @unlink((string) $copy['file_path']);
            }
            $wpdb->delete(self::table('correction_items'), ['copy_id' => (int) $copy['id']], ['%d']);
        }

        $wpdb->delete(self::table('correction_copies'), ['batch_id' => $batch_id], ['%d']);
        $wpdb->delete(self::table('correction_batches'), ['id' => $batch_id], ['%d']);

        $dir = CopyUploadService::batch_dir($batch_id);
        if (!empty($dir['path']) && is_dir($dir['path'])) {
            foreach (glob(trailingslashit($dir['path']) . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            foreach (['.htaccess', 'index.html'] as $file) {
                $path = trailingslashit($dir['path']) . $file;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($dir['path']);
        }

        return true;
    }

    public static function get_copy(int $copy_id): ?array
    {
        global $wpdb;
        if ($copy_id <= 0) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table('correction_copies') . " WHERE id = %d LIMIT 1",
            $copy_id
        ), ARRAY_A) ?: null;
    }

    public static function update_batch_status(int $batch_id, string $status): void
    {
        global $wpdb;
        if (!in_array($status, ['draft', 'analyzing', 'review', 'validated', 'error'], true)) {
            return;
        }
        $wpdb->update(self::table('correction_batches'), ['status' => $status], ['id' => $batch_id], ['%s'], ['%d']);
    }
}
