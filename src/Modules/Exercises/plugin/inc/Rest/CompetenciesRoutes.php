<?php
namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\CompetencyLevels;
use Ouinpo\Exercises\TeachingState;

defined('ABSPATH') || exit;

class CompetenciesRoutes {
    const NS = 'ouinpo/v1';

    public static function register() {
        register_rest_route(self::NS, '/competencies', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'index'],
                'permission_callback' => [__CLASS__, 'can_edit'],
            ],
            [
                'methods'  => 'POST',
                'callback' => [__CLASS__, 'bulkUpdate'],
                'permission_callback' => [__CLASS__, 'can_edit'],
                'args' => [
                    'items' => ['required' => true],
                ]
            ],
        ]);

        register_rest_route(self::NS, '/competencies/seed-group', [
            [
                'methods'  => 'POST',
                'callback' => [__CLASS__, 'seedGroup'],
                'permission_callback' => [__CLASS__, 'can_edit'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/teaching-state', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'teachingStateIndex'],
                'permission_callback' => [__CLASS__, 'can_edit'],
            ],
            [
                'methods'  => 'POST',
                'callback' => [__CLASS__, 'teachingStateUpdate'],
                'permission_callback' => [__CLASS__, 'can_edit'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/options', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'options'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::NS, '/competencies/assessments-progress', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'assessmentsProgress'],
                'permission_callback' => [__CLASS__, 'can_edit'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/assessments-by-ds', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'assessmentsByDs'],
                'permission_callback' => [__CLASS__, 'can_edit'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/exercises-progress', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'exercisesProgress'],
                'permission_callback' => [__CLASS__, 'can_edit'],
            ],
        ]);

    }

    public static function can_edit() {
        return current_user_can('edit_users');
    }

    private static function sanitize_school_level($raw): string {
        $raw = sanitize_key((string)$raw);
        return substr($raw, 0, 20);
    }

    /**
     * Retourne 'Seconde' | 'Première' | 'Terminale'
     * pour une classe (group_id) donnée et une année (year_id).
     */
    private static function find_group_level_label(int $group_id, int $year_id): ?string {
        global $wpdb;
        $p = $wpdb->prefix . 'ouin_exo_';

        $label = $wpdb->get_var($wpdb->prepare(
            "SELECT sl.label
               FROM {$p}groups g
               JOIN {$p}school_levels sl ON sl.id = g.school_level_id
              WHERE g.id = %d AND g.year_id = %d
              LIMIT 1",
            $group_id, $year_id
        ));
        $label = trim((string) $label);

        return $label !== '' ? $label : null;

    }

    public static function index(\WP_REST_Request $req) {
        global $wpdb;
        $tblUC   = $wpdb->prefix . 'ouin_exo_user_competencies';
        $tblComp = $wpdb->prefix . 'ouin_exo_competencies';
        $tblU    = $wpdb->users;

        $year_id   = (int) $req->get_param('year_id');
        $group_id  = (int) $req->get_param('group_id');
        $domain    = sanitize_text_field($req->get_param('domain'));
        $user_id   = (int) $req->get_param('user_id');
        $viewParam = $req->get_param('view');

        if ($viewParam === 'domain') {
            $view = 'domain';
        } else {
            $view = 'detail';
        }

        if (!$year_id) {
            return new \WP_Error('bad_request', 'year_id requis', ['status' => 400]);
        }

        $where = ["uc.year_id = %d"];
        $args  = [$year_id];

        if ($group_id) {
            $where[] = "uc.group_id = %d";
            $args[]  = $group_id;

            $school_level_id = CompetencyLevels::group_school_level_id($group_id, $year_id);
            if ($school_level_id > 0) {
                $where[] = CompetencyLevels::level_filter_sql('c');
                $args[]  = $school_level_id;
            }
        }

        if ($user_id) {
            $where[] = "uc.user_id = %d";
            $args[]  = $user_id;
        }

        if ($domain) {
            $where[] = "c.domain_slug = %s";
            $args[]  = $domain;
        }

        $sqlDetail = "
          SELECT uc.user_id,
                 u.display_name,
                 uc.group_id,
                 uc.competency_id,
                 c.domain,
                 c.competency AS label,
                 c.capacity,
                 c.example,
                 uc.status,
                 uc.updated_at
            FROM $tblUC uc
            JOIN $tblComp c ON c.id = uc.competency_id
            JOIN $tblU u ON u.ID = uc.user_id
           WHERE " . implode(' AND ', $where) . "
           ORDER BY
             CASE
               WHEN c.track = 'NSI' THEN 1
               WHEN c.track = 'SNT' THEN 2
               ELSE 3
             END,
             u.display_name ASC, c.domain, c.slug
        ";

        if ($view === 'detail') {
            $rows = $wpdb->get_results($wpdb->prepare($sqlDetail, $args));
            return rest_ensure_response(['view' => 'detail', 'rows' => $rows]);
        }

        $sqlDomain = "
          SELECT uc.group_id, uc.user_id, u.display_name,
                 c.track, c.level, c.domain,
                 COUNT(*) total,
                 SUM(uc.status='acquired')        acquired,
                 SUM(uc.status='in_progress')     in_progress,
                 SUM(uc.status='consolidating')   consolidating,
                 SUM(uc.status='not_acquired')    not_acquired
            FROM $tblUC uc
            JOIN $tblComp c ON c.id = uc.competency_id
            JOIN $tblU u ON u.ID = uc.user_id
           WHERE " . implode(' AND ', $where) . "
           GROUP BY uc.group_id, uc.user_id, c.track, c.level, c.domain, u.display_name
           ORDER BY
             CASE
               WHEN c.track = 'NSI' THEN 1
               WHEN c.track = 'SNT' THEN 2
               ELSE 3
             END,
             u.display_name ASC, c.domain
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sqlDomain, $args));
        return rest_ensure_response(['view' => 'domain', 'rows' => $rows]);
    }

    public static function bulkUpdate(\WP_REST_Request $req) {
        global $wpdb;
        $tblUC = $wpdb->prefix . 'ouin_exo_user_competencies';

        $items = $req->get_param('items');
        if (!is_array($items) || empty($items)) {
            return new \WP_Error('bad_request', 'items[] requis', ['status' => 400]);
        }

        $ok  = 0;
        $now = current_time('mysql');
        $me  = get_current_user_id();

        foreach ($items as $it) {
            $user_id = (int) ($it['user_id'] ?? 0);
            $comp_id = (int) ($it['competency_id'] ?? 0);
            $year_id = (int) ($it['year_id'] ?? 0);

            $group_id = array_key_exists('group_id', $it) && $it['group_id'] !== null && $it['group_id'] !== ''
                ? (int) $it['group_id']
                : null;

            $rawStatus = $it['status'] ?? 'not_acquired';
            $allowed   = ['not_acquired','in_progress','consolidating','acquired'];
            $status    = in_array($rawStatus, $allowed, true) ? $rawStatus : 'not_acquired';

            if (!$user_id || !$comp_id || !$year_id) {
                continue;
            }

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM $tblUC WHERE user_id=%d AND competency_id=%d AND year_id=%d",
                $user_id,
                $comp_id,
                $year_id
            ));

            if ($exists) {
                $wpdb->update(
                    $tblUC,
                    [
                        'group_id'   => $group_id,
                        'status'     => $status,
                        'updated_at' => $now,
                        'updated_by' => $me,
                        'source'     => 'manual',
                    ],
                    [
                        'user_id'       => $user_id,
                        'competency_id' => $comp_id,
                        'year_id'       => $year_id,
                    ],
                    ['%d','%s','%s','%d','%s'],
                    ['%d','%d','%d']
                );
            } else {
                $data = [
                    'user_id'       => $user_id,
                    'competency_id' => $comp_id,
                    'year_id'       => $year_id,
                    'group_id'      => $group_id,
                    'status'        => $status,
                    'updated_at'    => $now,
                    'updated_by'    => $me,
                    'source'        => 'manual',
                ];

                $format = ['%d','%d','%d', ($group_id === null ? '%s' : '%d'), '%s','%s','%d','%s'];

                $wpdb->insert($tblUC, $data, $format);
            }

            $ok++;
        }

        return rest_ensure_response(['updated' => $ok]);
    }

public static function seedGroup(\WP_REST_Request $req) {
    global $wpdb;

    $group_id = (int) $req->get_param('group_id');
    $year_id  = (int) $req->get_param('year_id');

    if (!$group_id || !$year_id) {
        return new \WP_Error('bad_request', 'group_id et year_id requis', ['status' => 400]);
    }

    $seen_ids = TeachingState::get_seen_competency_ids($year_id, $group_id);
    if (empty($seen_ids)) {
        return rest_ensure_response([
            'seeded' => true,
            'count'  => 0,
            'message'=> 'Aucune compétence vue pour cette classe.'
        ]);
    }

    $count = 0;
    foreach ($seen_ids as $comp_id) {
        $count += TeachingState::seed_user_competency_for_group(
            $year_id,
            $group_id,
            (int) $comp_id,
            get_current_user_id()
        );
    }

    return rest_ensure_response([
        'seeded' => true,
        'count'  => $count,
        'seen_competencies' => count($seen_ids),
    ]);
}

public static function teachingStateIndex(\WP_REST_Request $req) {
    global $wpdb;

    $year_id  = (int) $req->get_param('year_id');
    $group_id = (int) $req->get_param('group_id');
    $domain   = sanitize_text_field((string) $req->get_param('domain'));

    if (!$year_id || !$group_id) {
        return new \WP_Error('bad_request', 'year_id et group_id requis', ['status' => 400]);
    }

    $tblComp = $wpdb->prefix . 'ouin_exo_competencies';

    $school_level_id = CompetencyLevels::group_school_level_id($group_id, $year_id);
    if (!$school_level_id) {
        return new \WP_Error('bad_request', 'Niveau du groupe introuvable', ['status' => 400]);
    }

    $where = [
        "IFNULL(c.active,1)=1",
        CompetencyLevels::level_filter_sql('c'),
    ];
    $args = [$school_level_id];

    if ($domain !== '') {
        $where[] = "c.domain_slug = %s";
        $args[] = $domain;
    }

    $sql = "
        SELECT c.id, c.track, c.level, c.domain, c.domain_slug, c.slug,
               c.competency AS label, c.capacity, c.example
          FROM {$tblComp} c
         WHERE " . implode(' AND ', $where) . "
         ORDER BY
           CASE
             WHEN c.track = 'NSI' THEN 1
             WHEN c.track = 'SNT' THEN 2
             ELSE 3
           END,
           c.domain, c.slug
    ";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
    $map  = TeachingState::get_map_for_group($year_id, $group_id);

    foreach ($rows as &$row) {
        $cid = (int) $row['id'];
        $row['teaching_state']   = $map[$cid]['teaching_state']   ?? TeachingState::STATE_NOT_STARTED;
        $row['first_seen_at']    = $map[$cid]['first_seen_at']    ?? null;
        $row['state_changed_at'] = $map[$cid]['state_changed_at'] ?? null;
        $row['updated_at']       = $map[$cid]['updated_at']       ?? null;
        $row['updated_by']       = $map[$cid]['updated_by']       ?? null;
    }
    unset($row);

    return rest_ensure_response([
        'rows' => $rows,
    ]);
}

public static function teachingStateUpdate(\WP_REST_Request $req) {
    $year_id        = (int) $req->get_param('year_id');
    $group_id       = (int) $req->get_param('group_id');
    $competency_id  = (int) $req->get_param('competency_id');
    $teaching_state = TeachingState::normalize_state($req->get_param('teaching_state'));

    if (!$year_id || !$group_id || !$competency_id) {
        return new \WP_Error('bad_request', 'year_id, group_id et competency_id requis', ['status' => 400]);
    }

    $ok = TeachingState::set_state(
        $year_id,
        $group_id,
        $competency_id,
        $teaching_state,
        get_current_user_id()
    );

    if (!$ok) {
        return new \WP_Error('update_failed', 'Impossible de mettre à jour l’état du cours.', ['status' => 500]);
    }

    return rest_ensure_response([
        'ok'             => true,
        'teaching_state' => $teaching_state,
    ]);
}

public static function assessmentsProgress(\WP_REST_Request $req) {
    global $wpdb;

    $tblR   = $wpdb->prefix . 'ouin_exo_assessment_results';
    $tblA   = $wpdb->prefix . 'ouin_exo_assessments';
    $tblC   = $wpdb->prefix . 'ouin_exo_competencies';
    $tblGrp = $wpdb->prefix . 'ouin_exo_groups';
    $tblU   = $wpdb->users;

    $year_id  = (int) $req->get_param('year_id');
    $group_id = (int) $req->get_param('group_id');
    $domain   = sanitize_text_field((string) $req->get_param('domain'));
    $user_id  = (int) $req->get_param('user_id');

    if (!$year_id) {
        return new \WP_Error('bad_request', 'year_id requis', ['status' => 400]);
    }

    $where = ['g.year_id = %d'];
    $args  = [$year_id];

    if ($group_id) {
        $where[] = 'a.group_id = %d';
        $args[]  = $group_id;

        $school_level_id = CompetencyLevels::group_school_level_id($group_id, $year_id);
        if ($school_level_id > 0) {
            $where[] = CompetencyLevels::level_filter_sql('c');
            $args[]  = $school_level_id;
        }
    }

    if ($user_id) {
        $where[] = 'r.user_id = %d';
        $args[]  = $user_id;
    }

    if ($domain !== '') {
        $where[] = 'c.domain_slug = %s';
        $args[]  = $domain;
    }

    $sql = "
        SELECT
            r.user_id,
            u.display_name,
            r.assessment_id,
            a.title AS assessment_title,
            a.due_on,
            c.id AS competency_id,
            c.domain,
            c.domain_slug,
            c.competency AS label,
            c.capacity,
            c.example,
            c.slug,
            r.observed_status,
            r.note,
            r.updated_at
        FROM {$tblR} r
        JOIN {$tblA} a   ON a.id = r.assessment_id
        JOIN {$tblGrp} g ON g.id = a.group_id
        JOIN {$tblC} c   ON c.id = r.competency_id
        JOIN {$tblU} u   ON u.ID = r.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.display_name ASC, c.domain ASC, c.id ASC, a.due_on DESC, r.assessment_id DESC
    ";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];

    if (!$rows) {
        return rest_ensure_response([
            'summary' => [
                'students'       => 0,
                'evaluated'      => 0,
                'acquired'       => 0,
                'consolidating'  => 0,
                'in_progress'    => 0,
                'not_acquired'   => 0,
                'assessments'    => 0,
            ],
            'competencies' => [],
            'priorities'   => [],
        ]);
    }

    $rank = static function(string $status): int {
        return match ($status) {
            'in_progress'   => 1,
            'consolidating' => 2,
            'acquired'      => 3,
            default         => 0,
        };
    };

    $byPair = [];
    $assessment_ids = [];
    $student_ids = [];

    foreach ($rows as $row) {
        $uid = (int)$row['user_id'];
        $cid = (int)$row['competency_id'];
        $key = $uid . ':' . $cid;

        $student_ids[$uid] = true;
        $assessment_ids[(int)$row['assessment_id']] = true;

        if (!isset($byPair[$key])) {
            $byPair[$key] = [
                'user_id'       => $uid,
                'display_name'  => (string)$row['display_name'],
                'competency_id' => $cid,
                'domain'        => (string)$row['domain'],
                'domain_slug'   => (string)$row['domain_slug'],
                'label'         => (string)$row['label'],
                'capacity'      => (string)($row['capacity'] ?? ''),
                'example'       => (string)($row['example'] ?? ''),
                'slug'          => (string)($row['slug'] ?? ''),
                'history'       => [],
            ];
        }

        $byPair[$key]['history'][] = [
            'assessment_id'    => (int)$row['assessment_id'],
            'assessment_title' => (string)$row['assessment_title'],
            'due_on'           => (string)$row['due_on'],
            'status'           => (string)$row['observed_status'],
            'note'             => (string)($row['note'] ?? ''),
            'updated_at'       => (string)$row['updated_at'],
        ];
    }

    $summary = [
        'students'       => count($student_ids),
        'evaluated'      => 0,
        'acquired'       => 0,
        'consolidating'  => 0,
        'in_progress'    => 0,
        'not_acquired'   => 0,
        'assessments'    => count($assessment_ids),
    ];

    $competencies = [];

    foreach ($byPair as $item) {
        $history = $item['history'];

        usort($history, static function(array $a, array $b): int {
            $ka = ($a['due_on'] ?: '0000-00-00') . ' ' . $a['updated_at'];
            $kb = ($b['due_on'] ?: '0000-00-00') . ' ' . $b['updated_at'];
            return strcmp($kb, $ka);
        });

        $current = $history[0]['status'] ?? 'not_acquired';
        $prev    = $history[1]['status'] ?? null;

        $trend = 'new';
        if ($prev !== null) {
            $delta = $rank($current) - $rank($prev);
            if ($delta > 0) $trend = 'up';
            elseif ($delta < 0) $trend = 'down';
            else $trend = ($current === 'acquired') ? 'confirmed' : 'stable';
        }

        $summary['evaluated']++;
        if (isset($summary[$current])) {
            $summary[$current]++;
        }

        $competencies[] = [
            'user_id'              => $item['user_id'],
            'display_name'         => $item['display_name'],
            'competency_id'        => $item['competency_id'],
            'domain'               => $item['domain'],
            'domain_slug'          => $item['domain_slug'],
            'label'                => $item['label'],
            'capacity'             => $item['capacity'],
            'example'              => $item['example'],
            'slug'                 => $item['slug'],
            'current_status'       => $current,
            'trend'                => $trend,
            'last_assessment'      => $history[0]['assessment_title'] ?? '',
            'last_assessment_date' => $history[0]['due_on'] ?? '',
            'history'              => array_slice($history, 0, 5),
        ];
    }

    usort($competencies, static function(array $a, array $b): int {
        if ($a['display_name'] !== $b['display_name']) {
            return strcmp($a['display_name'], $b['display_name']);
        }
        if ($a['domain'] !== $b['domain']) {
            return strcmp($a['domain'], $b['domain']);
        }
        return strcmp($a['label'], $b['label']);
    });

    $priorities = [];
    if ($user_id > 0) {
        $priorities = array_values(array_filter($competencies, static function(array $row): bool {
            return in_array($row['current_status'], ['not_acquired', 'in_progress'], true);
        }));
        $priorities = array_slice($priorities, 0, 5);
    }

    return rest_ensure_response([
        'summary'      => $summary,
        'competencies' => $competencies,
        'priorities'   => $priorities,
    ]);
}

public static function assessmentsByDs(\WP_REST_Request $req) {
    global $wpdb;

    $tblA   = $wpdb->prefix . 'ouin_exo_assessments';
    $tblG   = $wpdb->prefix . 'ouin_exo_groups';
    $tblY   = $wpdb->prefix . 'ouin_exo_academic_years';
    $tblR   = $wpdb->prefix . 'ouin_exo_assessment_results';
    $tblAtt = $wpdb->prefix . 'ouin_exo_assessment_attendance';
    $tblC   = $wpdb->prefix . 'ouin_exo_competencies';
    $tblU   = $wpdb->users;

    $year_id  = (int) $req->get_param('year_id');
    $group_id = (int) $req->get_param('group_id');
    $domain   = sanitize_text_field((string) $req->get_param('domain'));
    $user_id  = (int) $req->get_param('user_id');

    if (!$year_id) {
        return new \WP_Error('bad_request', 'year_id requis', ['status' => 400]);
    }

    $whereAssess = ['g.year_id = %d'];
    $argsAssess  = [$year_id];

    if ($group_id > 0) {
        $whereAssess[] = 'a.group_id = %d';
        $argsAssess[]  = $group_id;
    }

    $sqlAssess = "
        SELECT
            a.id,
            a.title,
            a.due_on,
            a.group_id,
            g.label AS group_label,
            y.slug AS year_slug
        FROM {$tblA} a
        JOIN {$tblG} g ON g.id = a.group_id
        LEFT JOIN {$tblY} y ON y.id = g.year_id
        WHERE " . implode(' AND ', $whereAssess) . "
        ORDER BY a.due_on DESC, a.id DESC
    ";

    $assessmentRows = $wpdb->get_results($wpdb->prepare($sqlAssess, $argsAssess), ARRAY_A) ?: [];

    if (!$assessmentRows) {
        return rest_ensure_response([
            'summary' => [
                'assessments'   => 0,
                'students'      => 0,
                'absences'      => 0,
                'evaluated'     => 0,
                'acquired'      => 0,
                'consolidating' => 0,
                'in_progress'   => 0,
                'not_acquired'  => 0,
            ],
            'assessments' => [],
        ]);
    }

    $assessments = [];
    $assessmentIds = [];

    foreach ($assessmentRows as $row) {
        $aid = (int) $row['id'];
        $assessmentIds[] = $aid;

        $assessments[$aid] = [
            'assessment_id' => $aid,
            'title'         => (string) $row['title'],
            'due_on'        => (string) ($row['due_on'] ?? ''),
            'group_id'      => (int) ($row['group_id'] ?? 0),
            'group_label'   => (string) ($row['group_label'] ?? ''),
            'year_slug'     => (string) ($row['year_slug'] ?? ''),
            'students'      => [],
            'totals'        => [
                'evaluated_students' => 0,
                'absent_students'    => 0,
                'evaluated'          => 0,
                'acquired'           => 0,
                'consolidating'      => 0,
                'in_progress'        => 0,
                'not_acquired'       => 0,
            ],
        ];
    }

    $inAssessments = implode(',', array_map('intval', $assessmentIds));

    $attWhere = [
        "att.assessment_id IN ({$inAssessments})",
        'att.is_absent = 1',
    ];
    $attArgs = [];

    if ($user_id > 0) {
        $attWhere[] = 'att.user_id = %d';
        $attArgs[] = $user_id;
    }

    $sqlAtt = "
        SELECT
            att.assessment_id,
            att.user_id,
            u.display_name,
            att.note,
            att.updated_at
        FROM {$tblAtt} att
        JOIN {$tblU} u ON u.ID = att.user_id
        WHERE " . implode(' AND ', $attWhere) . "
        ORDER BY att.assessment_id DESC, u.display_name ASC
    ";

    $attendanceRows = $attArgs
        ? ($wpdb->get_results($wpdb->prepare($sqlAtt, $attArgs), ARRAY_A) ?: [])
        : ($wpdb->get_results($sqlAtt, ARRAY_A) ?: []);

    foreach ($attendanceRows as $row) {
        $aid = (int) $row['assessment_id'];
        $uid = (int) $row['user_id'];

        if (!isset($assessments[$aid])) {
            continue;
        }

        if (!isset($assessments[$aid]['students'][$uid])) {
            $assessments[$aid]['students'][$uid] = [
                'user_id'      => $uid,
                'display_name' => (string) ($row['display_name'] ?? ''),
                'is_absent'    => true,
                'absence_note' => (string) ($row['note'] ?? ''),
                'updated_at'   => (string) ($row['updated_at'] ?? ''),
                'evaluated'    => 0,
                'counts'       => [
                    'acquired'      => 0,
                    'consolidating' => 0,
                    'in_progress'   => 0,
                    'not_acquired'  => 0,
                ],
                'competencies' => [],
            ];
        } else {
            $assessments[$aid]['students'][$uid]['is_absent'] = true;
            $assessments[$aid]['students'][$uid]['absence_note'] = (string) ($row['note'] ?? '');
        }
    }

    $resWhere = [
        "r.assessment_id IN ({$inAssessments})",
    ];
    $resArgs = [];

    if ($user_id > 0) {
        $resWhere[] = 'r.user_id = %d';
        $resArgs[] = $user_id;
    }

    if ($domain !== '') {
        $resWhere[] = 'c.domain_slug = %s';
        $resArgs[] = $domain;
    }

    $sqlRes = "
        SELECT
            r.assessment_id,
            r.user_id,
            u.display_name,
            c.id AS competency_id,
            c.domain,
            c.domain_slug,
            c.competency AS label,
            r.observed_status,
            r.note,
            r.updated_at
        FROM {$tblR} r
        JOIN {$tblU} u ON u.ID = r.user_id
        JOIN {$tblC} c ON c.id = r.competency_id
        WHERE " . implode(' AND ', $resWhere) . "
        ORDER BY r.assessment_id DESC, u.display_name ASC, c.domain ASC, c.id ASC
    ";

    $resultRows = $resArgs
        ? ($wpdb->get_results($wpdb->prepare($sqlRes, $resArgs), ARRAY_A) ?: [])
        : ($wpdb->get_results($sqlRes, ARRAY_A) ?: []);

    foreach ($resultRows as $row) {
        $aid = (int) $row['assessment_id'];
        $uid = (int) $row['user_id'];
        $status = (string) ($row['observed_status'] ?? 'not_acquired');

        if (!isset($assessments[$aid])) {
            continue;
        }

        if (!isset($assessments[$aid]['students'][$uid])) {
            $assessments[$aid]['students'][$uid] = [
                'user_id'      => $uid,
                'display_name' => (string) ($row['display_name'] ?? ''),
                'is_absent'    => false,
                'absence_note' => '',
                'updated_at'   => (string) ($row['updated_at'] ?? ''),
                'evaluated'    => 0,
                'counts'       => [
                    'acquired'      => 0,
                    'consolidating' => 0,
                    'in_progress'   => 0,
                    'not_acquired'  => 0,
                ],
                'competencies' => [],
            ];
        }

        // Sécurité : si un élève est marqué absent, on n’affiche pas ses résultats éventuels.
        if (!empty($assessments[$aid]['students'][$uid]['is_absent'])) {
            continue;
        }

        if (!in_array($status, ['acquired', 'consolidating', 'in_progress', 'not_acquired'], true)) {
            $status = 'not_acquired';
        }

        $assessments[$aid]['students'][$uid]['evaluated']++;
        $assessments[$aid]['students'][$uid]['counts'][$status]++;

        $assessments[$aid]['students'][$uid]['competencies'][] = [
            'competency_id' => (int) ($row['competency_id'] ?? 0),
            'domain'        => (string) ($row['domain'] ?? ''),
            'domain_slug'   => (string) ($row['domain_slug'] ?? ''),
            'label'         => (string) ($row['label'] ?? ''),
            'status'        => $status,
            'note'          => (string) ($row['note'] ?? ''),
            'updated_at'    => (string) ($row['updated_at'] ?? ''),
        ];

        $assessments[$aid]['totals']['evaluated']++;
        $assessments[$aid]['totals'][$status]++;
    }

    $filtered = [];
    $studentIds = [];

    foreach ($assessments as $aid => $assessment) {
        $students = array_values($assessment['students']);

        usort($students, static function(array $a, array $b): int {
            return strcmp((string) $a['display_name'], (string) $b['display_name']);
        });

        $evaluatedStudents = 0;
        $absentStudents = 0;

        foreach ($students as $student) {
            if (!empty($student['is_absent'])) {
                $absentStudents++;
            } elseif (!empty($student['evaluated'])) {
                $evaluatedStudents++;
            }

            $studentIds[(int) $student['user_id']] = true;
        }

        $assessment['students'] = $students;
        $assessment['totals']['evaluated_students'] = $evaluatedStudents;
        $assessment['totals']['absent_students'] = $absentStudents;

        if ($user_id > 0 && $evaluatedStudents === 0 && $absentStudents === 0) {
            continue;
        }

        $filtered[] = $assessment;
    }

    $summary = [
        'assessments'   => count($filtered),
        'students'      => count($studentIds),
        'absences'      => 0,
        'evaluated'     => 0,
        'acquired'      => 0,
        'consolidating' => 0,
        'in_progress'   => 0,
        'not_acquired'  => 0,
    ];

    foreach ($filtered as $assessment) {
        $summary['absences']      += (int) ($assessment['totals']['absent_students'] ?? 0);
        $summary['evaluated']     += (int) ($assessment['totals']['evaluated'] ?? 0);
        $summary['acquired']      += (int) ($assessment['totals']['acquired'] ?? 0);
        $summary['consolidating'] += (int) ($assessment['totals']['consolidating'] ?? 0);
        $summary['in_progress']   += (int) ($assessment['totals']['in_progress'] ?? 0);
        $summary['not_acquired']  += (int) ($assessment['totals']['not_acquired'] ?? 0);
    }

    return rest_ensure_response([
        'summary'     => $summary,
        'assessments' => $filtered,
    ]);
}

public static function exercisesProgress(\WP_REST_Request $req) {
    global $wpdb;

    $year_id  = (int) $req->get_param('year_id');
    $group_id = (int) $req->get_param('group_id');
    $domain   = sanitize_text_field((string) $req->get_param('domain'));
    $user_id  = (int) $req->get_param('user_id');

    $empty = [
        'summary' => [
            'students' => 0,
            'total'    => 0,
            'worked'   => 0,
            'solid'    => 0,
            'priority' => 0,
        ],
        'rows' => [],
    ];

    if (!$year_id) {
        return new \WP_Error('bad_request', 'year_id requis', ['status' => 400]);
    }

    if (!$group_id) {
        return rest_ensure_response($empty);
    }

    $school_level_id = CompetencyLevels::group_school_level_id($group_id, $year_id);
    if (!$school_level_id) {
        return rest_ensure_response($empty);
    }

    $p = $wpdb->prefix . 'ouin_exo_';

    $tblGroups = $p . 'groups';
    $tblGM     = $p . 'group_members';
    $tblTeach  = $p . 'competency_teaching';
    $tblComp   = $p . 'competencies';
    $tblUC     = $p . 'user_competencies';
    $tblEC     = $p . 'exercise_competency';
    $tblESL    = $p . 'exercise_school_level';
    $tblExo    = $p . 'exercises';
    $tblUS     = $p . 'user_status';
    $tblU      = $wpdb->users;
    $tblEM     = $p . 'exam_meta';

    $school_level_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT school_level_id
           FROM {$tblGroups}
          WHERE id = %d
            AND year_id = %d
          LIMIT 1",
        $group_id,
        $year_id
    ));

    if (!$school_level_id) {
        return rest_ensure_response($empty);
    }

    $where = [
        'gm.group_id = %d',
        'gm.role = %s',
        't.year_id = %d',
        't.group_id = %d',
        "t.teaching_state = 'seen'",
        CompetencyLevels::level_filter_sql('c'),
        'IFNULL(c.active,1)=1',
        'IFNULL(e.is_active,1)=1',
    ];
    $args = [
        $group_id,
        'student',
        $year_id,
        $group_id,
        $school_level_id,
    ];

    if ($domain !== '') {
        $where[] = 'c.domain_slug = %s';
        $args[] = $domain;
    }

    if ($user_id > 0) {
        $where[] = 'u.ID = %d';
        $args[] = $user_id;
    }

    $sql = "
        SELECT
            u.ID AS user_id,
            u.display_name,
            c.id AS competency_id,
            c.domain,
            c.domain_slug,
            c.competency AS label,
            c.slug,
            COALESCE(uc.status, 'not_acquired') AS current_status,

        COUNT(DISTINCT CASE
            WHEN esl.school_level_id IS NOT NULL
             AND em.exercise_id IS NULL
            THEN ec.exercise_id
        END) AS available_count,

        COUNT(DISTINCT CASE
            WHEN esl.school_level_id IS NOT NULL
             AND em.exercise_id IS NULL
             AND us.status IN ('attempted', 'solved')
            THEN ec.exercise_id
        END) AS attempted_count,

        COUNT(DISTINCT CASE
            WHEN esl.school_level_id IS NOT NULL
             AND em.exercise_id IS NULL
             AND us.status = 'solved'
            THEN ec.exercise_id
        END) AS solved_count,

        COUNT(DISTINCT CASE
            WHEN esl.school_level_id IS NOT NULL
             AND em.exercise_id IS NULL
             AND us.status = 'solved'
             AND e.difficulty_id = 1
            THEN ec.exercise_id
        END) AS solved_beginner_count,

        COUNT(DISTINCT CASE
            WHEN esl.school_level_id IS NOT NULL
             AND em.exercise_id IS NULL
             AND us.status = 'solved'
             AND e.difficulty_id = 2
            THEN ec.exercise_id
        END) AS solved_confirmed_count,

        COUNT(DISTINCT CASE
            WHEN esl.school_level_id IS NOT NULL
             AND em.exercise_id IS NULL
             AND us.status = 'solved'
             AND e.difficulty_id = 3
            THEN ec.exercise_id
        END) AS solved_expert_count

        FROM {$tblGM} gm
        JOIN {$tblU} u
          ON u.ID = gm.user_id

        JOIN {$tblTeach} t
          ON t.group_id = gm.group_id
         AND t.year_id = %d

        JOIN {$tblComp} c
          ON c.id = t.competency_id

        LEFT JOIN {$tblUC} uc
          ON uc.user_id = u.ID
         AND uc.year_id = %d
         AND uc.group_id = gm.group_id
         AND uc.competency_id = c.id

        LEFT JOIN {$tblEC} ec
          ON ec.competency_id = c.id

        LEFT JOIN {$tblESL} esl
          ON esl.exercise_id = ec.exercise_id
         AND esl.school_level_id = %d

        LEFT JOIN {$tblExo} e
          ON e.id = ec.exercise_id

        LEFT JOIN {$tblEM} em
          ON em.exercise_id = ec.exercise_id
         AND em.exam_type = 'practical_subject'

        LEFT JOIN {$tblUS} us
          ON us.user_id = u.ID
         AND us.exercise_id = ec.exercise_id

        WHERE " . implode(' AND ', $where) . "

        GROUP BY
            u.ID, u.display_name,
            c.id, c.domain, c.domain_slug, c.competency, c.slug,
            uc.status, c.track, c.level

        ORDER BY
            u.display_name ASC,
            CASE
              WHEN c.track = 'NSI' THEN 1
              WHEN c.track = 'SNT' THEN 2
              ELSE 3
            END,
            c.domain ASC,
            c.slug ASC
    ";

    $query_args = array_merge(
        [$year_id, $year_id, $school_level_id],
        $args
    );

    $rows = $wpdb->get_results($wpdb->prepare($sql, $query_args), ARRAY_A) ?: [];

    $summary = [
        'students' => 0,
        'total'    => 0,
        'worked'   => 0,
        'solid'    => 0,
        'priority' => 0,
    ];

    $student_ids = [];

    foreach ($rows as &$row) {
        $row['user_id']                 = (int) $row['user_id'];
        $row['competency_id']           = (int) $row['competency_id'];
        $row['available_count']         = (int) $row['available_count'];
        $row['attempted_count']         = (int) $row['attempted_count'];
        $row['solved_count']            = (int) $row['solved_count'];
        $row['solved_beginner_count']   = (int) $row['solved_beginner_count'];
        $row['solved_confirmed_count']  = (int) $row['solved_confirmed_count'];
        $row['solved_expert_count']     = (int) $row['solved_expert_count'];

        $available = $row['available_count'];
        $attempted = $row['attempted_count'];
        $solved    = $row['solved_count'];

        $row['coverage_pct'] = $available > 0 ? (int) round(100 * $attempted / $available) : 0;
        $row['success_pct']  = $attempted > 0 ? (int) round(100 * $solved / $attempted) : 0;

        $summary['total']++;
        $student_ids[$row['user_id']] = true;

        if ($attempted > 0) {
            $summary['worked']++;
        }

        if ($available > 0 && $attempted >= 3 && $row['success_pct'] >= 70) {
            $summary['solid']++;
        }

        if ($available > 0 && ($attempted === 0 || $row['success_pct'] < 50)) {
            $summary['priority']++;
        }
    }
    unset($row);

    $summary['students'] = count($student_ids);

    return rest_ensure_response([
        'summary' => $summary,
        'rows'    => $rows,
    ]);
}

    public static function options(\WP_REST_Request $request) {
        global $wpdb;
        $p = $wpdb->prefix . 'ouin_exo_';

        $table_comp = $p . 'competencies';
        $table_ec   = $p . 'exercise_competency';
        $table_exo  = $p . 'exercises';
        $table_esl  = $p . 'exercise_school_level';
        $table_sl   = $p . 'school_levels';
        $table_em   = $p . 'exam_meta';

        $uid  = get_current_user_id();
        $rows = [];

        $comp_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_comp));
        $ec_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_ec));
        $exo_exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_exo));
        $esl_exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_esl));
        $sl_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_sl));
        $em_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_em));

        if (!$comp_exists || !$ec_exists || !$exo_exists) {
            return rest_ensure_response(['domains' => [], 'competencies' => []]);
        }

        $exo_cols = $wpdb->get_col("SHOW COLUMNS FROM {$table_exo}");
        $has_is_active = in_array('is_active', $exo_cols, true);
        $where_active = $has_is_active ? "e.is_active = 1" : "1=1";

        $exam_join = $em_exists
            ? "LEFT JOIN {$table_em} em ON em.exercise_id = e.id AND em.exam_type = 'practical_subject'"
            : "";

        $where_not_practical = $em_exists
            ? "AND em.exercise_id IS NULL"
            : "";

        $school_level = self::sanitize_school_level($request->get_param('school_level'));

        if ($school_level !== '' && $esl_exists && $sl_exists) {
            $sql = "
                SELECT DISTINCT c.id, c.domain, c.domain_slug, c.competency, c.track, c.level
                  FROM {$table_comp} c
                  JOIN {$table_ec}  ec  ON ec.competency_id = c.id
                  JOIN {$table_exo} e   ON e.id = ec.exercise_id
                    {$exam_join}
                    JOIN {$table_esl} esl ON esl.exercise_id = e.id
                    JOIN {$table_sl}  sl  ON sl.id = esl.school_level_id
                    WHERE {$where_active}
                      {$where_not_practical}
                      AND IFNULL(c.active,1)=1
                      AND sl.slug = %s
                 ORDER BY c.domain, c.competency
            ";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $school_level), ARRAY_A);
        }

        if (!$rows && $uid && $comp_exists && $ec_exists && $exo_exists && $esl_exists
            && class_exists('\Ouinpo\Exercises\Years')
            && class_exists('\Ouinpo\Exercises\LevelsSchool')) {

            $year   = \Ouinpo\Exercises\Years::active_id();
            $levels = (array) \Ouinpo\Exercises\LevelsSchool::effective_for_user($uid, $year);
            $levels = array_filter(array_map('intval', $levels));

            if (!empty($levels)) {
                $in = implode(',', $levels);

                $sql = "
                    SELECT DISTINCT c.id, c.domain, c.domain_slug, c.competency, c.track, c.level
                      FROM {$table_comp} c
                      JOIN {$table_ec}  ec  ON ec.competency_id = c.id
                        JOIN {$table_exo} e   ON e.id = ec.exercise_id
                        {$exam_join}
                        JOIN {$table_esl} esl ON esl.exercise_id = e.id
                        WHERE {$where_active}
                          {$where_not_practical}
                          AND IFNULL(c.active,1)=1
                          AND esl.school_level_id IN ({$in})
                     ORDER BY c.domain, c.competency
                ";
                $rows = $wpdb->get_results($sql, ARRAY_A);
            }
        }

        if (!$rows) {
            $sql = "
                SELECT DISTINCT c.id, c.domain, c.domain_slug, c.competency, c.track, c.level
                  FROM {$table_comp} c
                  JOIN {$table_ec}  ec ON ec.competency_id = c.id
                    JOIN {$table_exo} e  ON e.id = ec.exercise_id
                    {$exam_join}
                    WHERE {$where_active}
                      {$where_not_practical}
                      AND IFNULL(c.active,1)=1
                 ORDER BY c.domain, c.competency
            ";
            $rows = $wpdb->get_results($sql, ARRAY_A);
        }

        if (!$rows) {
            $rows = [];
        }

        $domains = [];
        foreach ($rows as $r) {
            $slug = $r['domain_slug'] ?: sanitize_title($r['domain']);
            if (!isset($domains[$slug])) {
                $domains[$slug] = [
                    'slug'  => $slug,
                    'label' => $r['domain'],
                ];
            }
        }

        return rest_ensure_response([
            'domains' => array_values($domains),
            'competencies' => array_map(function($r) {
                return [
                    'id'          => (int) $r['id'],
                    'domain'      => $r['domain'],
                    'domain_slug' => $r['domain_slug'],
                    'label'       => $r['competency'],
                    'track'       => $r['track'],
                    'level'       => $r['level'],
                ];
            }, $rows),
        ]);
    }
}
