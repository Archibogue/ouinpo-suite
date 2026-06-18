<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\Privacy\LearningAudiencePolicy;

defined('ABSPATH') || exit;

final class ClassKpiService
{
    private static function table(string $suffix): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    public static function build(int $group_id, int $level_id = 0, array $competency_ids = [], array $domain_slugs = []): array
    {
        global $wpdb;

        $group = self::group_row($group_id);
        $level = self::level_label($level_id, $group);
        $competency_ids = array_values(array_unique(array_filter(array_map('intval', $competency_ids))));
        $domain_slugs = array_values(array_unique(array_filter(array_map('sanitize_title', $domain_slugs))));

        $assessment_count = self::assessment_count($group_id);
        $exercise_rows = self::assessment_exercise_rows($group_id);
        $status_rows = self::status_rows($group_id);
        $competency_rows = self::competency_rows($competency_ids, $domain_slugs, $level_id);
        $result_stats = self::assessment_result_stats($group_id);

        $domains = [];
        $competencies = [];
        $tested_exercise_ids = [];
        $difficulty_counts = [];

        foreach ($exercise_rows as $row) {
            $tested_exercise_ids[(int) $row['exercise_id']] = true;
            $difficulty = (string) ($row['difficulty_label'] ?? '');
            if ($difficulty !== '') {
                $difficulty_counts[$difficulty] = ($difficulty_counts[$difficulty] ?? 0) + 1;
            }

            $domain_label = (string) ($row['domain'] ?? '');
            if ($domain_label !== '') {
                $key = sanitize_title($domain_label);
                $domains[$key] ??= [
                    'label' => $domain_label,
                    'tested_count' => 0,
                    'success_rate' => null,
                    'last_tested_at' => null,
                ];
                $domains[$key]['tested_count']++;
                $domains[$key]['last_tested_at'] = self::max_date($domains[$key]['last_tested_at'], $row['due_on'] ?? null);
            }

            $cid = (int) ($row['competency_id'] ?? 0);
            if ($cid > 0) {
                $competencies[$cid] ??= [
                    'id' => $cid,
                    'label' => (string) ($row['competency_label'] ?: $row['competency']),
                    'tested_count' => 0,
                    'success_rate' => null,
                    'last_tested_at' => null,
                ];
                $competencies[$cid]['tested_count']++;
                $competencies[$cid]['last_tested_at'] = self::max_date($competencies[$cid]['last_tested_at'], $row['due_on'] ?? null);
            }
        }

        foreach ($result_stats as $cid => $stats) {
            if (isset($competencies[$cid])) {
                $total = max(0, (int) ($stats['total'] ?? 0));
                $positive = max(0, (int) ($stats['positive'] ?? 0));
                $competencies[$cid]['success_rate'] = $total > 0 ? round($positive / $total, 2) : null;
            }
        }

        foreach ($competency_rows as $row) {
            $cid = (int) $row['id'];
            $competencies[$cid] ??= [
                'id' => $cid,
                'label' => (string) ($row['label'] ?: $row['competency']),
                'tested_count' => 0,
                'success_rate' => null,
                'last_tested_at' => null,
            ];

            $domain_label = (string) ($row['domain'] ?? '');
            if ($domain_label !== '') {
                $key = sanitize_title($domain_label);
                $domains[$key] ??= [
                    'label' => $domain_label,
                    'tested_count' => 0,
                    'success_rate' => null,
                    'last_tested_at' => null,
                ];
            }
        }

        $alerts = self::alerts($domains, $competencies, $assessment_count, $status_rows);

        return [
            'class_id' => $group_id,
            'level' => $level,
            'availability' => $assessment_count > 0 || !empty($status_rows) ? 'KPI partiel' : 'aucune donnée disponible',
            'summary' => [
                'total_assessments_found' => $assessment_count,
                'total_exercises_seen' => count($tested_exercise_ids),
                'total_attempts' => array_sum(array_column($status_rows, 'attempted_count')),
                'total_solved' => array_sum(array_column($status_rows, 'solved_count')),
            ],
            'domains' => array_values($domains),
            'competencies' => array_values($competencies),
            'exercises_seen' => array_map('intval', array_keys($tested_exercise_ids)),
            'difficulties' => array_map(
                static fn(string $label, int $count): array => ['label' => $label, 'seen_count' => $count],
                array_keys($difficulty_counts),
                array_values($difficulty_counts)
            ),
            'alerts' => $alerts,
            'notes' => [
                'Les agrégats ne contiennent aucun nom d’élève.',
                empty($status_rows) ? 'Tentatives/réussites : donnée non suivie actuellement ou absente.' : 'Tentatives/réussites agrégées depuis user_status.',
                'Soumissions : donnée non agrégée ici, car le module Submissions repose sur des contenus WordPress séparés et peut contenir des données nominatives.',
            ],
        ];
    }

    private static function group_row(int $group_id): ?array
    {
        global $wpdb;
        if ($group_id <= 0) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT g.id, g.label, g.school_level_id, sl.label AS level_label
             FROM " . self::table('groups') . " g
             LEFT JOIN " . self::table('school_levels') . " sl ON sl.id = g.school_level_id
             WHERE g.id = %d
             LIMIT 1",
            $group_id
        ), ARRAY_A) ?: null;
    }

    private static function level_label(int $level_id, ?array $group): string
    {
        global $wpdb;
        if ($level_id <= 0 && $group) {
            $level_id = (int) ($group['school_level_id'] ?? 0);
        }
        if ($level_id <= 0) {
            return '';
        }

        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT label FROM " . self::table('school_levels') . " WHERE id = %d",
            $level_id
        ));
    }

    private static function assessment_count(int $group_id): int
    {
        global $wpdb;
        if ($group_id <= 0) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table('assessments') . " WHERE group_id = %d",
            $group_id
        ));
    }

    private static function class_student_ids(int $group_id): array
    {
        global $wpdb;
        if ($group_id <= 0) {
            return [];
        }

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id
             FROM " . self::table('group_members') . "
             WHERE group_id = %d
               AND role = 'student'",
            $group_id
        )) ?: [];

        return LearningAudiencePolicy::filterClassStudentIds($ids);
    }

    private static function assessment_exercise_rows(int $group_id): array
    {
        global $wpdb;
        if ($group_id <= 0) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT ai.exercise_id, a.due_on, d.label AS difficulty_label,
                    c.id AS competency_id, c.domain, c.competency, c.label AS competency_label
             FROM " . self::table('assessments') . " a
             INNER JOIN " . self::table('assessment_items') . " ai ON ai.assessment_id = a.id
             LEFT JOIN " . self::table('exercises') . " e ON e.id = ai.exercise_id
             LEFT JOIN " . self::table('difficulties') . " d ON d.id = e.difficulty_id
             LEFT JOIN " . self::table('exercise_competency') . " ec ON ec.exercise_id = ai.exercise_id
             LEFT JOIN " . self::table('competencies') . " c ON c.id = ec.competency_id
             WHERE a.group_id = %d",
            $group_id
        ), ARRAY_A) ?: [];
    }

    private static function status_rows(int $group_id): array
    {
        global $wpdb;
        if ($group_id <= 0) {
            return [];
        }

        $student_ids = self::class_student_ids($group_id);
        if (empty($student_ids)) {
            return [];
        }

        $student_in = implode(',', array_fill(0, count($student_ids), '%d'));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT us.exercise_id,
                    COUNT(DISTINCT CASE WHEN us.status IN ('attempted','solved') THEN us.user_id END) AS attempted_count,
                    COUNT(DISTINCT CASE WHEN us.status = 'solved' THEN us.user_id END) AS solved_count
             FROM " . self::table('user_status') . " us
             WHERE us.user_id IN ({$student_in})
             GROUP BY us.exercise_id",
            ...$student_ids
        ), ARRAY_A) ?: [];
    }

    private static function competency_rows(array $competency_ids, array $domain_slugs, int $level_id): array
    {
        global $wpdb;
        $where = ['active = 1'];
        $args = [];
        $table = self::table('competencies');

        if (!empty($competency_ids)) {
            $where[] = 'id IN (' . implode(',', array_fill(0, count($competency_ids), '%d')) . ')';
            array_push($args, ...$competency_ids);
        }

        if (!empty($domain_slugs)) {
            $where[] = 'domain_slug IN (' . implode(',', array_fill(0, count($domain_slugs), '%s')) . ')';
            array_push($args, ...$domain_slugs);
        }

        $sql = "SELECT id, domain, domain_slug, competency, label, level FROM {$table} WHERE " . implode(' AND ', $where);

        return $args ? ($wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
    }

    private static function assessment_result_stats(int $group_id): array
    {
        global $wpdb;
        if ($group_id <= 0) {
            return [];
        }

        $student_ids = self::class_student_ids($group_id);
        if (empty($student_ids)) {
            return [];
        }

        $student_in = implode(',', array_fill(0, count($student_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.competency_id,
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN r.observed_status IN ('consolidating','acquired') THEN 1 ELSE 0 END) AS positive_count
             FROM " . self::table('assessment_results') . " r
             INNER JOIN " . self::table('assessments') . " a ON a.id = r.assessment_id
             WHERE a.group_id = %d
               AND r.user_id IN ({$student_in})
             GROUP BY r.competency_id",
            ...array_merge([$group_id], $student_ids)
        ), ARRAY_A) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['competency_id']] = [
                'total' => (int) $row['total_count'],
                'positive' => (int) $row['positive_count'],
            ];
        }

        return $out;
    }

    private static function alerts(array $domains, array $competencies, int $assessment_count, array $status_rows): array
    {
        $alerts = [];
        foreach ($competencies as $competency) {
            if ((int) $competency['tested_count'] === 0) {
                $alerts[] = [
                    'type' => 'under_tested',
                    'label' => (string) $competency['label'],
                    'message' => 'Compétence jamais évaluée dans les données disponibles.',
                ];
            } elseif ((int) $competency['tested_count'] >= 4) {
                $alerts[] = [
                    'type' => 'over_tested',
                    'label' => (string) $competency['label'],
                    'message' => 'Compétence déjà souvent évaluée.',
                ];
            }
        }

        foreach ($domains as $domain) {
            if ($assessment_count > 0 && (int) $domain['tested_count'] === 0) {
                $alerts[] = [
                    'type' => 'under_represented_domain',
                    'label' => (string) $domain['label'],
                    'message' => 'Domaine sous-représenté dans les devoirs trouvés.',
                ];
            }
        }

        if (empty($status_rows)) {
            $alerts[] = [
                'type' => 'partial_data',
                'label' => 'KPI',
                'message' => 'Aucune tentative/réussite agrégée disponible.',
            ];
        }

        return $alerts;
    }

    private static function max_date(?string $current, ?string $candidate): ?string
    {
        if (!$candidate) {
            return $current;
        }
        if (!$current || strcmp($candidate, $current) > 0) {
            return $candidate;
        }

        return $current;
    }
}
