<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class FileCorrectionPersistService
{
    private static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    public static function validate_copy(int $copy_id, array $correction): array|\WP_Error
    {
        global $wpdb;
        $copy = CorrectionBatchService::get_copy($copy_id);
        if (!$copy || (string) ($copy['source_type'] ?? '') !== 'file') {
            return new \WP_Error('copy_not_found', 'Rendu fichier introuvable.');
        }
        $batch = FileCorrectionBatchService::get_batch((int) $copy['batch_id']);
        if (!$batch) {
            return new \WP_Error('batch_not_found', 'Lot fichiers introuvable.');
        }
        $context = FileCorrectionBatchService::context_for_batch($batch);
        if (is_wp_error($context)) {
            return $context;
        }

        $validated = (new AiFileCorrectionService())->validate($correction, $context, (string) $copy['student_ref']);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $wpdb->update(self::table('correction_copies'), [
            'status' => 'validated',
            'validated_correction' => wp_json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], ['id' => $copy_id], ['%s', '%s'], ['%d']);

        $wpdb->delete(self::table('correction_items'), ['copy_id' => $copy_id], ['%d']);
        foreach ($validated['items'] as $item) {
            $wpdb->insert(self::table('correction_items'), [
                'copy_id' => $copy_id,
                'exercise_id' => (int) $item['exercise_id'],
                'suggested_points' => (float) $item['suggested_points'],
                'max_points' => (float) $item['max_points'],
                'confidence' => (float) $item['confidence'],
                'feedback' => wp_kses_post((string) $item['feedback']),
                'competencies' => wp_json_encode($item['competencies'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s', '%s']);
        }

        if ((int) AiSettings::get('ouinpo_ai_file_correction_keep_files') !== 1 && self::can_delete_source_file($copy) && is_file((string) $copy['file_path'])) {
            @unlink((string) $copy['file_path']);
        }

        return $validated;
    }

    public static function reject_copy(int $copy_id): bool
    {
        global $wpdb;
        return false !== $wpdb->update(self::table('correction_copies'), ['status' => 'rejected'], ['id' => $copy_id], ['%s'], ['%d']);
    }

    private static function can_delete_source_file(array $copy): bool
    {
        global $wpdb;
        $path = (string) ($copy['file_path'] ?? '');
        if ($path === '') {
            return false;
        }

        $open = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM " . self::table('correction_copies') . "
             WHERE file_path = %s
               AND id <> %d
               AND status NOT IN ('validated','rejected')",
            $path,
            (int) ($copy['id'] ?? 0)
        ));

        return $open <= 0;
    }
}
