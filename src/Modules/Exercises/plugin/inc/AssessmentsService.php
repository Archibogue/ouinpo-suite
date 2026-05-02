<?php
namespace Ouinpo\Exercises;

use WP_Error;

defined('ABSPATH') || exit;

class AssessmentsService
{
    private static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $name;
    }

    public static function allowed_statuses(): array
    {
        return ['not_acquired', 'in_progress', 'consolidating', 'acquired'];
    }

    public static function status_rank(string $status): int
    {
        return match ($status) {
            'in_progress'   => 1,
            'consolidating' => 2,
            'acquired'      => 3,
            default         => 0,
        };
    }

    public static function list_competency_assessments(int $group_id = 0): array
    {
        global $wpdb;

        $tblA    = self::table('assessments');
        $tblG    = self::table('groups');
        $tblY    = self::table('academic_years');
        $tblAC   = self::table('assessment_competencies');
        $tblR    = self::table('assessment_results');
        $tblAtt  = self::table('assessment_attendance');

        $where = '';
        $args  = [];

        if ($group_id > 0) {
            $where = 'WHERE a.group_id = %d';
            $args[] = $group_id;
        }

        $sql = "
            SELECT
                a.id,
                a.title,
                a.group_id,
                a.due_on,
                a.notes,
                g.label AS group_label,
                y.slug AS year_slug,
                COUNT(DISTINCT ac.competency_id) AS competencies_count,
                COUNT(DISTINCT CASE WHEN COALESCE(att_r.is_absent, 0) = 0 THEN r.user_id END) AS graded_students,
                COUNT(DISTINCT CASE WHEN att.is_absent = 1 THEN att.user_id END) AS absent_students
            FROM {$tblA} a
            LEFT JOIN {$tblG} g
                ON g.id = a.group_id
            LEFT JOIN {$tblY} y
                ON y.id = g.year_id
            LEFT JOIN {$tblAC} ac
                ON ac.assessment_id = a.id
            LEFT JOIN {$tblR} r
                ON r.assessment_id = a.id
            LEFT JOIN {$tblAtt} att_r
                ON att_r.assessment_id = r.assessment_id
               AND att_r.user_id = r.user_id
            LEFT JOIN {$tblAtt} att
                ON att.assessment_id = a.id
            {$where}
            GROUP BY a.id, a.title, a.group_id, a.due_on, a.notes, g.label, y.slug
            ORDER BY a.due_on DESC, a.id DESC
        ";

        if ($args) {
            return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function get_competency_assessment(int $assessment_id): ?array
    {
        global $wpdb;

        if ($assessment_id <= 0) {
            return null;
        }

        $tblA  = self::table('assessments');
        $tblG  = self::table('groups');
        $tblY  = self::table('academic_years');
        $tblL  = self::table('school_levels');
        $tblAC = self::table('assessment_competencies');
        $tblC  = self::table('competencies');
        $tblR  = self::table('assessment_results');

        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT
                a.*,
                g.label AS group_label,
                g.year_id,
                y.slug AS year_slug,
                l.label AS level_label
             FROM {$tblA} a
             LEFT JOIN {$tblG} g ON g.id = a.group_id
             LEFT JOIN {$tblY} y ON y.id = g.year_id
             LEFT JOIN {$tblL} l ON l.id = g.school_level_id
             WHERE a.id = %d
             LIMIT 1",
            $assessment_id
        ), ARRAY_A);

        if (!$assessment) {
            return null;
        }

        $competencies = $wpdb->get_results($wpdb->prepare(
            "SELECT
                c.id,
                c.domain,
                c.competency,
                c.track,
                c.level,
                c.slug
             FROM {$tblAC} ac
             JOIN {$tblC} c ON c.id = ac.competency_id
             WHERE ac.assessment_id = %d
             ORDER BY
               CASE WHEN c.track = 'NSI' THEN 1 WHEN c.track = 'SNT' THEN 2 ELSE 3 END,
               c.domain ASC,
               c.id ASC",
            $assessment_id
        ), ARRAY_A) ?: [];

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                assessment_id,
                user_id,
                competency_id,
                observed_status,
                note,
                updated_at,
                updated_by
             FROM {$tblR}
             WHERE assessment_id = %d
             ORDER BY user_id ASC, competency_id ASC",
            $assessment_id
        ), ARRAY_A) ?: [];

        return [
            'assessment'   => $assessment,
            'competencies' => $competencies,
            'results'      => $results,
            'attendance'   => self::get_assessment_attendance_map($assessment_id),
        ];
    }

    public static function save_competency_assessment(array $payload, int $assessment_id = 0): int|WP_Error
    {
        global $wpdb;
    
        $tblA  = self::table('assessments');
        $tblAC = self::table('assessment_competencies');
        $tblR  = self::table('assessment_results');
    
        $title = sanitize_text_field($payload['title'] ?? '');
        $group_id = (int)($payload['group_id'] ?? 0);
        $due_on = sanitize_text_field($payload['due_on'] ?? '');
        $notes = wp_kses_post($payload['notes'] ?? '');
        $competency_ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array)($payload['competency_ids'] ?? [])
        ))));
    
        if ($title === '') {
            return new WP_Error('missing_title', 'Le titre du DS est obligatoire.', ['status' => 400]);
        }
        if ($group_id <= 0) {
            return new WP_Error('missing_group', 'La classe est obligatoire.', ['status' => 400]);
        }
        if ($due_on === '') {
            return new WP_Error('missing_due_on', 'La date du DS est obligatoire.', ['status' => 400]);
        }
        if (empty($competency_ids)) {
            return new WP_Error('missing_competencies', 'Sélectionne au moins une compétence BO.', ['status' => 400]);
        }
    
        $data = [
            'title'    => $title,
            'group_id' => $group_id,
            'due_on'   => $due_on,
            'notes'    => $notes,
        ];
    
        $old_competency_ids = [];
    
        if ($assessment_id > 0) {
            $old_competency_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
                "SELECT competency_id
                 FROM {$tblAC}
                 WHERE assessment_id = %d",
                $assessment_id
            )) ?: []);
    
            $ok = $wpdb->update($tblA, $data, ['id' => $assessment_id], ['%s', '%d', '%s', '%s'], ['%d']);
            if ($ok === false) {
                return new WP_Error('db_update_failed', 'Impossible de mettre à jour le DS.', ['status' => 500]);
            }
        } else {
            $ok = $wpdb->insert($tblA, $data, ['%s', '%d', '%s', '%s']);
            if (!$ok) {
                return new WP_Error('db_insert_failed', 'Impossible de créer le DS.', ['status' => 500]);
            }
            $assessment_id = (int)$wpdb->insert_id;
        }
    
        // Si on modifie un DS existant, on supprime les résultats liés
        // aux compétences qui ont été retirées du DS.
        if ($assessment_id > 0 && !empty($old_competency_ids)) {
            $removed_competency_ids = array_values(array_diff($old_competency_ids, $competency_ids));
    
            if (!empty($removed_competency_ids)) {
                $in_removed = implode(',', array_map('intval', $removed_competency_ids));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$tblR}
                     WHERE assessment_id = %d
                       AND competency_id IN ({$in_removed})",
                    $assessment_id
                ));
            }
        }
    
        $wpdb->delete($tblAC, ['assessment_id' => $assessment_id], ['%d']);
    
        foreach ($competency_ids as $competency_id) {
            $wpdb->insert($tblAC, [
                'assessment_id' => $assessment_id,
                'competency_id' => $competency_id,
            ], ['%d', '%d']);
        }
    
        return $assessment_id;
    }

    public static function get_assessment_attendance_map(int $assessment_id): array
    {
        global $wpdb;

        if ($assessment_id <= 0) {
            return [];
        }

        $tbl = self::table('assessment_attendance');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, is_absent, note, updated_at, updated_by
             FROM {$tbl}
             WHERE assessment_id = %d",
            $assessment_id
        ), ARRAY_A) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']] = [
                'is_absent'  => !empty($row['is_absent']),
                'note'       => (string) ($row['note'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'updated_by' => (int) ($row['updated_by'] ?? 0),
            ];
        }

        return $map;
    }

    public static function save_assessment_attendance(int $assessment_id, array $attendance, ?int $updated_by = null): true|WP_Error
    {
        global $wpdb;

        if ($assessment_id <= 0) {
            return new WP_Error('assessment_not_found', 'DS introuvable.', ['status' => 404]);
        }

        $tblAtt = self::table('assessment_attendance');
        $tblR   = self::table('assessment_results');
        $updated_by = $updated_by ?: get_current_user_id();

        foreach ($attendance as $user_id => $row) {
            $user_id = (int) $user_id;
            if ($user_id <= 0) {
                continue;
            }

            $is_absent = !empty($row['is_absent']) ? 1 : 0;
            $note      = isset($row['note']) ? wp_kses_post((string) $row['note']) : null;

            if ($is_absent) {
                $exists = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tblAtt} WHERE assessment_id = %d AND user_id = %d",
                    $assessment_id,
                    $user_id
                ));

                $payload = [
                    'assessment_id' => $assessment_id,
                    'user_id'       => $user_id,
                    'is_absent'     => 1,
                    'note'          => $note,
                    'updated_at'    => current_time('mysql'),
                    'updated_by'    => $updated_by,
                ];

                if ($exists) {
                    $wpdb->update(
                        $tblAtt,
                        $payload,
                        ['assessment_id' => $assessment_id, 'user_id' => $user_id],
                        ['%d', '%d', '%d', '%s', '%s', '%d'],
                        ['%d', '%d']
                    );
                } else {
                    $wpdb->insert(
                        $tblAtt,
                        $payload,
                        ['%d', '%d', '%d', '%s', '%s', '%d']
                    );
                }

                // Un absent ne doit avoir aucun résultat de compétence sur ce DS.
                $wpdb->delete($tblR, [
                    'assessment_id' => $assessment_id,
                    'user_id'       => $user_id,
                ], ['%d', '%d']);
            } else {
                $wpdb->delete($tblAtt, [
                    'assessment_id' => $assessment_id,
                    'user_id'       => $user_id,
                ], ['%d', '%d']);
            }
        }

        return true;
    }

    public static function save_competency_results(int $assessment_id, array $results, bool $apply_progression = false, ?int $updated_by = null): true|WP_Error
    {
        global $wpdb;

        $assessment_bundle = self::get_competency_assessment($assessment_id);
        if (!$assessment_bundle) {
            return new WP_Error('assessment_not_found', 'DS introuvable.', ['status' => 404]);
        }

        $assessment = $assessment_bundle['assessment'];
        $year_id = (int)($assessment['year_id'] ?? 0);
        $group_id = (int)($assessment['group_id'] ?? 0);

        $tblR  = self::table('assessment_results');
        $tblUC = self::table('user_competencies');

        $allowed_statuses = array_flip(self::allowed_statuses());
        $updated_by = $updated_by ?: get_current_user_id();

        $attendance_map = $assessment_bundle['attendance'] ?? [];
        $allowed_competency_ids = [];
        foreach (($assessment_bundle['competencies'] ?? []) as $comp) {
            $allowed_competency_ids[(int)$comp['id']] = true;
        }

        foreach ($results as $row) {
            $user_id = (int)($row['user_id'] ?? 0);
            $competency_id = (int)($row['competency_id'] ?? 0);
            $status = sanitize_key((string)($row['observed_status'] ?? ''));
            $note = isset($row['note']) ? wp_kses_post((string)$row['note']) : null;

            if ($user_id <= 0 || $competency_id <= 0) {
                continue;
            }

            // Ignore toute compétence qui n'appartient pas au DS.
            if (!isset($allowed_competency_ids[$competency_id])) {
                continue;
            }

            // Un élève absent ne doit jamais recevoir de résultat sur ce DS.
            if (!empty($attendance_map[$user_id]['is_absent'])) {
                $wpdb->delete($tblR, [
                    'assessment_id' => $assessment_id,
                    'user_id'       => $user_id,
                    'competency_id' => $competency_id,
                ], ['%d', '%d', '%d']);
                continue;
            }

            if ($status === '') {
                $wpdb->delete($tblR, [
                    'assessment_id' => $assessment_id,
                    'user_id'       => $user_id,
                    'competency_id' => $competency_id,
                ], ['%d', '%d', '%d']);
                continue;
            }

            if (!isset($allowed_statuses[$status])) {
                return new WP_Error('invalid_status', 'Statut invalide.', ['status' => 400]);
            }

            if ($year_id > 0 && $group_id > 0) {
                TeachingState::set_seen(
                    $year_id,
                    $group_id,
                    $competency_id,
                    $updated_by
                );
            }

            $exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tblR}
                 WHERE assessment_id = %d AND user_id = %d AND competency_id = %d",
                $assessment_id,
                $user_id,
                $competency_id
            ));

            $payload = [
                'assessment_id'   => $assessment_id,
                'user_id'         => $user_id,
                'competency_id'   => $competency_id,
                'observed_status' => $status,
                'note'            => $note,
                'updated_at'      => current_time('mysql'),
                'updated_by'      => $updated_by ?: null,
            ];

            if ($exists) {
                $ok = $wpdb->update(
                    $tblR,
                    $payload,
                    [
                        'assessment_id' => $assessment_id,
                        'user_id'       => $user_id,
                        'competency_id' => $competency_id,
                    ],
                    ['%d', '%d', '%d', '%s', '%s', '%s', '%d'],
                    ['%d', '%d', '%d']
                );

                if ($ok === false) {
                    return new WP_Error('db_update_failed', 'Impossible de mettre à jour un résultat de compétence.', ['status' => 500]);
                }
            } else {
                $ok = $wpdb->insert(
                    $tblR,
                    $payload,
                    ['%d', '%d', '%d', '%s', '%s', '%s', '%d']
                );

                if (!$ok) {
                    return new WP_Error('db_insert_failed', 'Impossible d’enregistrer un résultat de compétence.', ['status' => 500]);
                }
            }

            if (!$apply_progression || $year_id <= 0) {
                continue;
            }

            $current = $wpdb->get_row($wpdb->prepare(
                "SELECT status
                 FROM {$tblUC}
                 WHERE user_id = %d AND competency_id = %d AND year_id = %d
                 LIMIT 1",
                $user_id,
                $competency_id,
                $year_id
            ));

            $current_status = $current ? (string)$current->status : '';

            if (self::status_rank($status) < self::status_rank($current_status)) {
                continue;
            }

            $uc_payload = [
                'user_id'       => $user_id,
                'competency_id' => $competency_id,
                'year_id'       => $year_id,
                'group_id'      => $group_id ?: null,
                'status'        => $status,
                'updated_at'    => current_time('mysql'),
                'updated_by'    => $updated_by ?: null,
                'source'        => 'assessment',
            ];

            if ($current) {
                $ok = $wpdb->update(
                    $tblUC,
                    $uc_payload,
                    [
                        'user_id'       => $user_id,
                        'competency_id' => $competency_id,
                        'year_id'       => $year_id,
                    ],
                    ['%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s'],
                    ['%d', '%d', '%d']
                );

                if ($ok === false) {
                    return new WP_Error('db_update_failed', 'Impossible de mettre à jour la progression de compétence.', ['status' => 500]);
                }
            } else {
                $ok = $wpdb->insert(
                    $tblUC,
                    $uc_payload,
                    ['%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s']
                );

                if (!$ok) {
                    return new WP_Error('db_insert_failed', 'Impossible d’enregistrer la progression de compétence.', ['status' => 500]);
                }
            }
        }

        return true;
    }
}