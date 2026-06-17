<?php
namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\CompetencyLevels;
use Ouinpo\Exercises\Services\AiJsonResponseParser;
use Ouinpo\Exercises\TeachingState;
use Ouinpo\Suite\Core\AiSettings;
use Ouinpo\Suite\Core\Capabilities;
use Ouinpo\Suite\Core\Privacy\LearningDataPolicy;

defined('ABSPATH') || exit;

class CompetenciesRoutes {
    const NS = 'ouinpo/v1';

    public static function register() {
        register_rest_route(self::NS, '/competencies', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'index'],
                'permission_callback' => [__CLASS__, 'can_view'],
            ],
            [
                'methods'  => 'POST',
                'callback' => [__CLASS__, 'bulkUpdate'],
                'permission_callback' => [__CLASS__, 'can_manage'],
                'args' => [
                    'items' => ['required' => true],
                ]
            ],
        ]);

        register_rest_route(self::NS, '/competencies/seed-group', [
            [
                'methods'  => 'POST',
                'callback' => [__CLASS__, 'seedGroup'],
                'permission_callback' => [__CLASS__, 'can_manage'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/teaching-state', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'teachingStateIndex'],
                'permission_callback' => [__CLASS__, 'can_view'],
            ],
            [
                'methods'  => 'POST',
                'callback' => [__CLASS__, 'teachingStateUpdate'],
                'permission_callback' => [__CLASS__, 'can_manage'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/options', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'options'],
                // Public: options de filtrage utilisées par les interfaces publiques.
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::NS, '/competencies/assessments-progress', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'assessmentsProgress'],
                'permission_callback' => [__CLASS__, 'can_view'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/assessments-by-ds', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'assessmentsByDs'],
                'permission_callback' => [__CLASS__, 'can_view'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/exercises-progress', [
            [
                'methods'  => 'GET',
                'callback' => [__CLASS__, 'exercisesProgress'],
                'permission_callback' => [__CLASS__, 'can_view'],
            ],
        ]);

        register_rest_route(self::NS, '/competencies/student-summary', [
            [
                'methods'  => 'POST',
                'callback' => [__CLASS__, 'studentSummary'],
                'permission_callback' => [__CLASS__, 'can_view'],
            ],
        ]);

    }

    public static function can_manage() {
        return Capabilities::can(Capabilities::MANAGE_COMPETENCIES);
    }

    public static function can_view() {
        return Capabilities::can(Capabilities::MANAGE_COMPETENCIES)
            || Capabilities::can(Capabilities::VIEW_STUDENT_DATA);
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
        $learningPolicy = new LearningDataPolicy();

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

            if (!$learningPolicy->canStoreLearningData($user_id)) {
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

public static function studentSummary(\WP_REST_Request $req) {
    if (!class_exists(AiSettings::class) || !AiSettings::enabled_for_usage('pedagogical_suggestions')) {
        return new \WP_Error('ai_disabled', 'La synthese IA enseignant est desactivee.', ['status' => 503]);
    }

    if (!class_exists('\OuInPo\SegFault\OpenAI')) {
        return new \WP_Error('ai_unavailable', 'Aucun moteur IA n est disponible.', ['status' => 503]);
    }

    $year_id  = (int) $req->get_param('year_id');
    $group_id = (int) $req->get_param('group_id');
    $user_id  = (int) $req->get_param('user_id');
    $domain   = sanitize_text_field((string) $req->get_param('domain'));

    if ($year_id <= 0 || $user_id <= 0) {
        return new \WP_Error('bad_request', 'year_id et user_id requis.', ['status' => 400]);
    }

    $quota = AiSettings::consumeUserRateLimit(
        'teacher_ai',
        get_current_user_id(),
        AiSettings::quota('ouinpo_ai_teacher_per_minute'),
        AiSettings::quota('ouinpo_ai_teacher_per_day')
    );

    if (is_wp_error($quota)) {
        return $quota;
    }

    $context = self::studentSummaryContext($year_id, $group_id, $user_id, $domain);
    if (is_wp_error($context)) {
        return $context;
    }

    $messages = [[
        'role' => 'system',
        'content' => AiSettings::persona('teacher', 'ouinpo_ai_persona_teacher')
            . "\n\nTache metier : rediger un commentaire court pour un enseignant avant edition PDF du suivi de competences d un eleve. Base-toi uniquement sur les donnees fournies. Ne donne pas de note chiffree. Reste prudent, concret, bienveillant et actionnable. Reponds uniquement avec un objet JSON valide, compact, sans Markdown, sans balise de code et sans texte avant ou apres le JSON.",
    ], [
        'role' => 'user',
        'content' => wp_json_encode([
            'schema_attendu' => [
                'summary' => 'Une phrase courte.',
                'strengths' => ['maximum 2 points forts, 8 mots chacun'],
                'priorities' => ['maximum 2 priorites, 8 mots chacune'],
                'next_steps' => ['maximum 2 actions, 10 mots chacune'],
                'teacher_comment' => 'Un commentaire lisible de 4 phrases maximum, sans liste.',
            ],
            'contexte' => $context,
            'consignes' => [
                'Ne mentionne pas de donnees absentes.',
                'Si peu de competences sont renseignees, signale que la synthese est partielle.',
                'Utilise les statuts acquis, en consolidation, en progression et non acquis.',
                'Formule teacher_comment comme un commentaire professionnel reutilisable dans un bilan eleve.',
                'N enumere pas toutes les competences.',
                'N ecris jamais plus de 900 caracteres au total.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]];

    try {
        $raw = \OuInPo\SegFault\OpenAI::respond($messages, [
            'temperature' => 0.25,
            'max_tokens' => 450,
            'stage' => 'competency_student_summary',
            'response_format' => ['type' => 'json_object'],
        ]);
    } catch (\Throwable $e) {
        AiSettings::debug_log('Competency student summary AI error', ['error' => $e->getMessage()]);
        return new \WP_Error('ai_error', 'La synthese IA n a pas pu etre generee.', ['status' => 502]);
    }

    $json = AiJsonResponseParser::parse((string) $raw, 'object');
    if (is_wp_error($json)) {
        AiSettings::debug_log('Competency student summary invalid JSON', [
            'error' => $json->get_error_message(),
            'raw_excerpt' => AiJsonResponseParser::excerpt((string) $raw, 500),
        ]);

        $fallback = self::fallbackStudentSummaryFromText((string) $raw, $context);
        if ($fallback !== null) {
            return rest_ensure_response($fallback);
        }

        return new \WP_Error('invalid_ai_summary', 'La synthese IA n a pas pu etre lue.', ['status' => 502]);
    }

    return rest_ensure_response(self::sanitizeStudentSummary($json));
}

private static function studentSummaryContext(int $year_id, int $group_id, int $user_id, string $domain = '') {
    global $wpdb;

    $p = $wpdb->prefix . 'ouin_exo_';
    $tblUC = $p . 'user_competencies';
    $tblC = $p . 'competencies';
    $tblU = $wpdb->users;
    $tblG = $p . 'groups';
    $tblY = $p . 'academic_years';
    $tblGM = $p . 'group_members';

    if ($group_id > 0) {
        $member = $wpdb->get_var($wpdb->prepare(
            "SELECT 1
               FROM {$tblGM} gm
               JOIN {$tblG} g ON g.id = gm.group_id
              WHERE gm.user_id = %d
                AND gm.group_id = %d
                AND g.year_id = %d
                AND gm.role = %s
              LIMIT 1",
            $user_id,
            $group_id,
            $year_id,
            'student'
        ));

        if (!$member) {
            return new \WP_Error('student_not_in_group', 'Cet eleve n appartient pas a cette classe.', ['status' => 404]);
        }
    }

    $where = ['uc.year_id = %d', 'uc.user_id = %d'];
    $args = [$year_id, $user_id];

    if ($group_id > 0) {
        $where[] = 'uc.group_id = %d';
        $args[] = $group_id;
    }

    if ($domain !== '') {
        $where[] = 'c.domain_slug = %s';
        $args[] = $domain;
    }

    $sql = "
        SELECT
            uc.user_id,
            u.display_name,
            uc.group_id,
            g.label AS group_label,
            y.slug AS year_label,
            c.id AS competency_id,
            c.domain,
            c.domain_slug,
            c.competency AS label,
            c.capacity,
            c.example,
            uc.status,
            uc.updated_at
        FROM {$tblUC} uc
        JOIN {$tblC} c ON c.id = uc.competency_id
        JOIN {$tblU} u ON u.ID = uc.user_id
        LEFT JOIN {$tblG} g ON g.id = uc.group_id
        LEFT JOIN {$tblY} y ON y.id = uc.year_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.domain ASC, c.slug ASC
    ";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];

    if (!$rows) {
        return new \WP_Error('no_competency_data', 'Aucune competence suivie pour cet eleve.', ['status' => 404]);
    }

    $counts = [
        'acquired' => 0,
        'consolidating' => 0,
        'in_progress' => 0,
        'not_acquired' => 0,
    ];

    $domains = [];
    $strengths = [];
    $priorities = [];
    $details = [];

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? 'not_acquired');
        if (!isset($counts[$status])) {
            $status = 'not_acquired';
        }

        $counts[$status]++;
        $domain_label = (string) ($row['domain'] ?? 'Sans domaine');

        if (!isset($domains[$domain_label])) {
            $domains[$domain_label] = [
                'total' => 0,
                'acquired' => 0,
                'consolidating' => 0,
                'in_progress' => 0,
                'not_acquired' => 0,
            ];
        }

        $domains[$domain_label]['total']++;
        $domains[$domain_label][$status]++;

        $item = [
            'domain' => $domain_label,
            'label' => AiJsonResponseParser::excerpt((string) ($row['label'] ?? ''), 180),
            'status' => $status,
        ];

        if (in_array($status, ['acquired', 'consolidating'], true) && count($strengths) < 12) {
            $strengths[] = $item;
        }

        if (in_array($status, ['not_acquired', 'in_progress'], true) && count($priorities) < 12) {
            $priorities[] = $item;
        }

        if (count($details) < 80) {
            $details[] = $item;
        }
    }

    $first = $rows[0];

    return [
        'student' => [
            'user_id' => (int) ($first['user_id'] ?? $user_id),
            'display_name' => (string) ($first['display_name'] ?? ''),
        ],
        'year' => [
            'id' => $year_id,
            'label' => (string) ($first['year_label'] ?? ''),
        ],
        'group' => [
            'id' => (int) ($first['group_id'] ?? $group_id),
            'label' => (string) ($first['group_label'] ?? ''),
        ],
        'filters' => [
            'domain' => $domain,
        ],
        'counts' => $counts + ['total' => count($rows)],
        'domains' => $domains,
        'points_solides_possibles' => $strengths,
        'priorites_possibles' => $priorities,
        'competences' => $details,
    ];
}

private static function sanitizeStudentSummary(array $raw): array {
    $list = static function($value): array {
        $out = [];
        foreach ((array) $value as $item) {
            if (is_array($item)) {
                $text = (string) ($item['label'] ?? $item['text'] ?? $item['title'] ?? $item['summary'] ?? '');
            } else {
                $text = (string) $item;
            }

            $text = trim(wp_strip_all_tags($text));
            if ($text !== '') {
                $out[] = AiJsonResponseParser::excerpt($text, 120);
            }
            if (count($out) >= 2) {
                break;
            }
        }
        return $out;
    };

    $summary = trim(wp_strip_all_tags((string) ($raw['summary'] ?? '')));
    $teacher_comment = trim(wp_strip_all_tags((string) ($raw['teacher_comment'] ?? '')));

    return [
        'summary' => AiJsonResponseParser::excerpt($summary, 260),
        'strengths' => $list($raw['strengths'] ?? []),
        'priorities' => $list($raw['priorities'] ?? []),
        'next_steps' => $list($raw['next_steps'] ?? []),
        'teacher_comment' => AiJsonResponseParser::excerpt($teacher_comment !== '' ? $teacher_comment : $summary, 700),
    ];
}

private static function fallbackStudentSummaryFromText(string $raw, array $context): ?array {
    $text = trim(wp_strip_all_tags($raw));
    $text = preg_replace('/^\s*```(?:json|JSON)?\s*/', '', $text) ?? $text;
    $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;
    $text = trim($text);

    if ($text === '' || str_starts_with($text, '{') || str_starts_with($text, '[')) {
        return self::deterministicStudentSummary($context);
    }

    return [
        'summary' => AiJsonResponseParser::excerpt($text, 260),
        'strengths' => [],
        'priorities' => [],
        'next_steps' => [],
        'teacher_comment' => AiJsonResponseParser::excerpt($text, 700),
        'format_warning' => 'La reponse IA a ete reprise en texte libre car elle ne contenait pas de JSON valide.',
    ];
}

private static function deterministicStudentSummary(array $context): array {
    $student = (array) ($context['student'] ?? []);
    $counts = (array) ($context['counts'] ?? []);
    $name = trim((string) ($student['display_name'] ?? 'L eleve'));

    $total = (int) ($counts['total'] ?? 0);
    $acquired = (int) ($counts['acquired'] ?? 0);
    $consolidating = (int) ($counts['consolidating'] ?? 0);
    $in_progress = (int) ($counts['in_progress'] ?? 0);
    $not_acquired = (int) ($counts['not_acquired'] ?? 0);

    $strengths = self::summaryLabels((array) ($context['points_solides_possibles'] ?? []), 2);
    $priorities = self::summaryLabels((array) ($context['priorites_possibles'] ?? []), 2);

    $summary = sprintf(
        '%s a %d competence(s) suivie(s) : %d acquise(s), %d en consolidation, %d en progression et %d non acquise(s).',
        $name,
        $total,
        $acquired,
        $consolidating,
        $in_progress,
        $not_acquired
    );

    $comment = $summary;

    if (!empty($strengths)) {
        $comment .= ' Points d appui : ' . implode(' ; ', $strengths) . '.';
    }

    if (!empty($priorities)) {
        $comment .= ' Priorites : reprendre ' . implode(' ; ', $priorities) . '.';
    } elseif ($in_progress > 0 || $not_acquired > 0) {
        $comment .= ' Priorite : consolider les competences encore fragiles avec des exercices courts et cibles.';
    } else {
        $comment .= ' La progression est solide ; proposer des situations de reinvestissement pour maintenir les acquis.';
    }

    return [
        'summary' => AiJsonResponseParser::excerpt($summary, 260),
        'strengths' => $strengths,
        'priorities' => $priorities,
        'next_steps' => ['Exercices cibles', 'Relecture avec feedback'],
        'teacher_comment' => AiJsonResponseParser::excerpt($comment, 700),
        'format_warning' => 'Synthese automatique locale utilisee car la reponse IA etait tronquee.',
    ];
}

private static function summaryLabels(array $items, int $limit): array {
    $out = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $domain = trim((string) ($item['domain'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        $text = trim($domain !== '' ? $domain . ' - ' . $label : $label);

        if ($text !== '') {
            $out[] = AiJsonResponseParser::excerpt($text, 110);
        }

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
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
        $table_dom  = $p . 'domains';

        $uid  = get_current_user_id();
        $rows = [];

        $comp_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_comp));
        $ec_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_ec));
        $exo_exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_exo));
        $esl_exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_esl));
        $sl_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_sl));
        $em_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_em));
        $dom_exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_dom));
        $domain_join = $dom_exists ? "LEFT JOIN {$table_dom} d ON d.id = c.domain_id" : "";
        $domain_select = $dom_exists
            ? "COALESCE(NULLIF(d.label, ''), c.domain) AS domain, COALESCE(NULLIF(d.slug, ''), c.domain_slug) AS domain_slug"
            : "c.domain, c.domain_slug";
        $domain_active = $dom_exists ? "AND COALESCE(d.active, 1) = 1" : "";

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
                SELECT DISTINCT c.id, {$domain_select}, c.competency, c.track, c.level
                  FROM {$table_comp} c
                  {$domain_join}
                  JOIN {$table_ec}  ec  ON ec.competency_id = c.id
                  JOIN {$table_exo} e   ON e.id = ec.exercise_id
                    {$exam_join}
                    JOIN {$table_esl} esl ON esl.exercise_id = e.id
                    JOIN {$table_sl}  sl  ON sl.id = esl.school_level_id
                    WHERE {$where_active}
                      {$where_not_practical}
                      AND IFNULL(c.active,1)=1
                      {$domain_active}
                      AND sl.slug = %s
                 ORDER BY domain, c.competency
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
                    SELECT DISTINCT c.id, {$domain_select}, c.competency, c.track, c.level
                      FROM {$table_comp} c
                      {$domain_join}
                      JOIN {$table_ec}  ec  ON ec.competency_id = c.id
                        JOIN {$table_exo} e   ON e.id = ec.exercise_id
                        {$exam_join}
                        JOIN {$table_esl} esl ON esl.exercise_id = e.id
                        WHERE {$where_active}
                          {$where_not_practical}
                          AND IFNULL(c.active,1)=1
                          {$domain_active}
                          AND esl.school_level_id IN ({$in})
                     ORDER BY domain, c.competency
                ";
                $rows = $wpdb->get_results($sql, ARRAY_A);
            }
        }

        if (!$rows) {
            $sql = "
                SELECT DISTINCT c.id, {$domain_select}, c.competency, c.track, c.level
                  FROM {$table_comp} c
                  {$domain_join}
                  JOIN {$table_ec}  ec ON ec.competency_id = c.id
                    JOIN {$table_exo} e  ON e.id = ec.exercise_id
                    {$exam_join}
                    WHERE {$where_active}
                      {$where_not_practical}
                      AND IFNULL(c.active,1)=1
                      {$domain_active}
                 ORDER BY domain, c.competency
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
