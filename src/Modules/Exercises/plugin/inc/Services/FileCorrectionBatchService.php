<?php

namespace Ouinpo\Exercises\Services;

defined('ABSPATH') || exit;

final class FileCorrectionBatchService
{
    private static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    public static function ensure_schema(): void
    {
        CorrectionBatchService::ensure_schema();
    }

    public static function sources(): array
    {
        return [
            'assessments' => CorrectionBatchService::assessments(),
            'exercises' => self::exercise_options(false),
            'practical_subjects' => self::exercise_options(true),
            'groups' => self::groups(),
        ];
    }

    public static function create_batch(string $context_type, int $context_id, int $group_id = 0, string $title = ''): int|\WP_Error
    {
        global $wpdb;
        self::ensure_schema();

        $context_type = self::normalize_context_type($context_type);
        if ($context_type === 'free') {
            return new \WP_Error('free_context_unavailable', 'La correction libre sera ajoutÃ©e dans un lot ultÃ©rieur.');
        }
        if ($context_id <= 0) {
            return new \WP_Error('invalid_context', 'Contexte de correction invalide.');
        }

        $context = self::context($context_type, $context_id);
        if (is_wp_error($context)) {
            return $context;
        }

        $assessment_id = $context_type === 'assessment' ? $context_id : 0;
        if ($group_id <= 0 && $context_type === 'assessment') {
            $group_id = (int) ($context['assessment']['group_id'] ?? 0);
        }
        if ($title === '') {
            $title = 'Correction IA fichiers - ' . (string) ($context['title'] ?? ('#' . $context_id));
        }

        $ok = $wpdb->insert(self::table('correction_batches'), [
            'assessment_id' => $assessment_id,
            'group_id' => $group_id ?: null,
            'teacher_id' => get_current_user_id(),
            'source_type' => 'file',
            'context_type' => $context_type,
            'context_id' => $context_id,
            'status' => 'draft',
            'title' => sanitize_text_field($title),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);

        return $ok ? (int) $wpdb->insert_id : new \WP_Error('batch_create_failed', 'Impossible de crÃ©er le lot fichiers.');
    }

    public static function get_batch(int $batch_id): ?array
    {
        $batch = CorrectionBatchService::get_batch($batch_id);
        if (!$batch || (string) ($batch['source_type'] ?? '') !== 'file') {
            return null;
        }
        return $batch;
    }

    public static function context_for_batch(array $batch): array|\WP_Error
    {
        $type = self::normalize_context_type((string) ($batch['context_type'] ?? 'assessment'));
        $id = (int) ($batch['context_id'] ?? 0);
        if ($id <= 0 && $type === 'assessment') {
            $id = (int) ($batch['assessment_id'] ?? 0);
        }
        return self::context($type, $id);
    }

    public static function context(string $context_type, int $context_id): array|\WP_Error
    {
        $context_type = self::normalize_context_type($context_type);
        if ($context_type === 'assessment') {
            $context = CorrectionBatchService::assessment_context($context_id);
            if (is_wp_error($context)) {
                return $context;
            }
            $context['context_type'] = 'assessment';
            $context['context_id'] = $context_id;
            $context['title'] = (string) ($context['assessment']['title'] ?? ('Devoir #' . $context_id));
            return $context;
        }

        if ($context_type === 'exercise' || $context_type === 'practical') {
            $item = self::exercise_context_item($context_id, $context_type === 'practical');
            if (is_wp_error($item)) {
                return $item;
            }
            return [
                'context_type' => $context_type,
                'context_id' => $context_id,
                'title' => (string) $item['title'],
                'assessment' => ['id' => 0, 'title' => (string) $item['title'], 'group_label' => ''],
                'items' => [$item],
                'max_points' => 20.0,
                'warnings' => ['BarÃ¨me non structurÃ© : la note proposÃ©e reste indicative jusquâ€™Ã  validation professeur.'],
            ];
        }

        return new \WP_Error('invalid_context', 'Contexte de correction invalide.');
    }

    private static function exercise_options(bool $practical): array
    {
        global $wpdb;
        $join = $practical
            ? "INNER JOIN " . self::table('exam_meta') . " em ON em.exercise_id = e.id AND em.exam_type = 'practical_subject'"
            : "LEFT JOIN " . self::table('exam_meta') . " em ON em.exercise_id = e.id AND em.exam_type = 'practical_subject'";
        $where = $practical ? '1=1' : 'em.exercise_id IS NULL';

        return $wpdb->get_results(
            "SELECT e.id, e.title, d.label AS difficulty_label
             FROM " . self::table('exercises') . " e
             {$join}
             LEFT JOIN " . self::table('difficulties') . " d ON d.id = e.difficulty_id
             WHERE {$where}
             ORDER BY e.id DESC
             LIMIT 150",
            ARRAY_A
        ) ?: [];
    }

    private static function groups(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, label FROM " . self::table('groups') . " ORDER BY label ASC, id ASC LIMIT 200",
            ARRAY_A
        ) ?: [];
    }

    private static function exercise_context_item(int $exercise_id, bool $must_be_practical): array|\WP_Error
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT e.id AS exercise_id, e.title, e.statement, d.label AS difficulty_label,
                    s.content AS solution_html,
                    em.exam_type
             FROM " . self::table('exercises') . " e
             LEFT JOIN " . self::table('difficulties') . " d ON d.id = e.difficulty_id
             LEFT JOIN " . self::table('solutions') . " s ON s.exercise_id = e.id AND s.is_official = 1
             LEFT JOIN " . self::table('exam_meta') . " em ON em.exercise_id = e.id
             WHERE e.id = %d
             LIMIT 1",
            $exercise_id
        ), ARRAY_A);

        if (!$row) {
            return new \WP_Error('exercise_not_found', 'Exercice introuvable.');
        }
        $is_practical = (string) ($row['exam_type'] ?? '') === 'practical_subject';
        if ($must_be_practical !== $is_practical) {
            return new \WP_Error('invalid_context_type', 'Le type de contexte ne correspond pas Ã  lâ€™exercice.');
        }

        $row['points'] = 20.0;
        $row['sort_order'] = 1;
        $row['competencies'] = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, COALESCE(NULLIF(c.label, ''), c.competency) AS label
             FROM " . self::table('exercise_competency') . " ec
             INNER JOIN " . self::table('competencies') . " c ON c.id = ec.competency_id
             WHERE ec.exercise_id = %d
             ORDER BY c.domain ASC, c.id ASC",
            $exercise_id
        ), ARRAY_A) ?: [];

        return $row;
    }

    private static function normalize_context_type(string $context_type): string
    {
        return in_array($context_type, ['assessment', 'exercise', 'practical', 'free'], true) ? $context_type : 'assessment';
    }
}
