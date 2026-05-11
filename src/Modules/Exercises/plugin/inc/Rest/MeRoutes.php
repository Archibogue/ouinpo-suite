<?php

namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\CompetencyLevels;
use Ouinpo\Exercises\TeachingState;

use WP_REST_Request;

use WP_REST_Response;



defined('ABSPATH') || exit;



class MeRoutes {

    const NS = 'ouinpo/v1';



    public static function register() {



        register_rest_route(self::NS, '/me/overview', [

            [

                'methods'             => 'GET',

                'callback'            => [__CLASS__, 'overview'],

                'permission_callback' => 'is_user_logged_in',

            ],

        ]);



        register_rest_route(self::NS, '/me/competencies', [

            [

                'methods'             => 'GET',

                'callback'            => [__CLASS__, 'byDomain'],

                'permission_callback' => 'is_user_logged_in',

            ],

        ]);



        register_rest_route(self::NS, '/me/competencies/detail', [

            [

                'methods'             => 'GET',

                'callback'            => [__CLASS__, 'detail'],

                'permission_callback' => 'is_user_logged_in',

            ],

        ]);



        // Choisir son titre via un badge

        register_rest_route(self::NS, '/me/title', [

            [

                'methods'             => 'POST',

                'callback'            => [__CLASS__, 'set_title'],

                'permission_callback' => 'is_user_logged_in',

            ],

        ]);



        // Badges de l'élève

        register_rest_route(self::NS, '/me/badges', [

            [

                'methods'             => 'GET',

                'callback'            => [__CLASS__, 'badges'],

                'permission_callback' => 'is_user_logged_in',

            ],

        ]);

        

        register_rest_route(self::NS, '/me/assessments/progress', [

            [

                'methods'             => 'GET',

                'callback'            => [__CLASS__, 'assessments_progress'],

                'permission_callback' => 'is_user_logged_in',

            ],

        ]);

        

        register_rest_route(self::NS, '/me/competencies/kpi', [

            [

                'methods'             => 'GET',

                'callback'            => [__CLASS__, 'competencies_kpi'],

                'permission_callback' => 'is_user_logged_in',

            ],

        ]);

        

    }



    /* ------------ Helpers ------------- */
    /** Convertit un slug de niveau scolaire en libelle courant. */

    private static function levelSlugToLabel(string $slug): string {

        global $wpdb;

        $slug = sanitize_key($slug);

        if ($slug === '') {

            return '';

        }

        $p = $wpdb->prefix . 'ouin_exo_';

        $label = $wpdb->get_var($wpdb->prepare(

            "SELECT label FROM {$p}school_levels WHERE slug = %s LIMIT 1",

            $slug

        ));

        $label = trim((string) $label);

        return $label !== '' ? $label : $slug;

    }



    /** Récupère le slug de niveau de l'élève via le groupe (ou le groupe passé en paramètre) pour l'année donnée */

    private static function find_user_level_slug(int $user_id, int $year_id, ?int $explicit_group_id = null) {

        global $wpdb;

        $p = $wpdb->prefix.'ouin_exo_';



        if ($explicit_group_id) {

            return $wpdb->get_var($wpdb->prepare(

            "SELECT sl.slug
            FROM {$p}groups g
            LEFT JOIN {$p}group_members gm
                ON gm.group_id = g.id
                AND gm.user_id = %d
            JOIN {$p}school_levels sl
                ON sl.id = COALESCE(gm.school_level_id_override, g.school_level_id)
            WHERE g.id = %d
                AND g.year_id = %d
            LIMIT 1",

            $user_id,
            $explicit_group_id,
            $year_id


            ));

        }



        // Groupe (classe) de l'élève pour l'année

        return $wpdb->get_var($wpdb->prepare(

            "SELECT sl.slug
            FROM {$p}group_members gm
            JOIN {$p}groups g
                ON g.id = gm.group_id
            JOIN {$p}school_levels sl
                ON sl.id = COALESCE(gm.school_level_id_override, g.school_level_id)
            WHERE gm.user_id = %d
                AND g.year_id = %d
            ORDER BY gm.group_id DESC
            LIMIT 1",

            $user_id, $year_id

        ));

    }

    private static function find_user_level_id(int $user_id, int $year_id, ?int $explicit_group_id = null): int {
        global $wpdb;
        $p = $wpdb->prefix.'ouin_exo_';

        if ($explicit_group_id) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(gm.school_level_id_override, g.school_level_id)
                   FROM {$p}groups g
                   LEFT JOIN {$p}group_members gm
                     ON gm.group_id = g.id
                    AND gm.user_id = %d
                  WHERE g.id = %d
                    AND g.year_id = %d
                  LIMIT 1",
                $user_id,
                $explicit_group_id,
                $year_id
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(gm.school_level_id_override, g.school_level_id)
               FROM {$p}group_members gm
               JOIN {$p}groups g ON g.id = gm.group_id
              WHERE gm.user_id = %d
                AND g.year_id = %d
              ORDER BY gm.group_id DESC
              LIMIT 1",
            $user_id,
            $year_id
        ));
    }



    /** Renvoie (uid, year_id, group_id|null, levelLabel) */

    private static function guards(\WP_REST_Request $req) {

        $uid = get_current_user_id();

        if (!$uid) return new \WP_Error('auth','Connexion requise',['status'=>401]);



        $year_id = (int)$req->get_param('year_id');

        if (!$year_id) return new \WP_Error('bad_request','year_id requis',['status'=>400]);



        $group_id = (int)$req->get_param('group_id') ?: null;



        $level_slug = self::find_user_level_slug($uid, $year_id, $group_id);
        $level_label = $level_slug ? self::levelSlugToLabel($level_slug) : null;

        $school_level_id = self::find_user_level_id($uid, $year_id, $group_id);

        return [$uid, $year_id, $group_id, $level_label, $school_level_id];

    }



    /* --------- Routes de progression --------- */



    public static function byDomain(\WP_REST_Request $req) {

        $g = self::guards($req);

        if ($g instanceof \WP_Error) return $g;

    

        [$uid, $year_id, $group_id, $levelLabel, $school_level_id] = $g;

    

        if (!$group_id || !$school_level_id) {

            return rest_ensure_response([

                'summary' => [

                    'total'         => 0,

                    'acquired'      => 0,

                    'consolidating' => 0,

                    'in_progress'   => 0,

                    'not_acquired'  => 0,

                ],

                'domains' => [],

            ]);

        }

    

        global $wpdb;

        $uc = $wpdb->prefix . 'ouin_exo_user_competencies';

        $c  = $wpdb->prefix . 'ouin_exo_competencies';

        $t  = $wpdb->prefix . 'ouin_exo_competency_teaching';

    

        $sql = "

          SELECT c.domain,

                 COUNT(*) AS total,

                 SUM(COALESCE(uc.status, 'not_acquired')='acquired')      AS acquired,

                 SUM(COALESCE(uc.status, 'not_acquired')='consolidating') AS consolidating,

                 SUM(COALESCE(uc.status, 'not_acquired')='in_progress')   AS in_progress,

                 SUM(COALESCE(uc.status, 'not_acquired')='not_acquired')  AS not_acquired

            FROM {$t} t

            JOIN {$c} c

              ON c.id = t.competency_id

            LEFT JOIN {$uc} uc

              ON uc.user_id = %d

             AND uc.year_id = %d

             AND uc.group_id = %d

             AND uc.competency_id = t.competency_id

           WHERE t.year_id = %d

             AND t.group_id = %d

             AND t.teaching_state = 'seen'

             AND " . CompetencyLevels::level_filter_sql('c') . "

           GROUP BY c.domain

           ORDER BY 

             CASE

               WHEN c.track = 'NSI' THEN 1

               WHEN c.track = 'SNT' THEN 2

               ELSE 3

             END,

             c.domain

        ";

    

        $rows = $wpdb->get_results($wpdb->prepare(

            $sql,

            $uid,

            $year_id,

            $group_id,

            $year_id,

            $group_id,

            $school_level_id

        ));

    

        $total = 0; $A = 0; $C = 0; $P = 0; $N = 0;

        foreach ($rows as $r) {

            $total += (int) $r->total;

            $A     += (int) $r->acquired;

            $C     += (int) $r->consolidating;

            $P     += (int) $r->in_progress;

            $N     += (int) $r->not_acquired;

        }

    

        $domains = array_map(function($r) {

            return [

                'domain'        => $r->domain,

                'total'         => (int) $r->total,

                'acquired'      => (int) $r->acquired,

                'consolidating' => (int) $r->consolidating,

                'in_progress'   => (int) $r->in_progress,

                'not_acquired'  => (int) $r->not_acquired,

            ];

        }, $rows ?: []);

    

        return rest_ensure_response([

            'summary' => [

                'total'         => $total,

                'acquired'      => $A,

                'consolidating' => $C,

                'in_progress'   => $P,

                'not_acquired'  => $N,

            ],

            'domains' => $domains,

        ]);

    }

    

    public static function detail(\WP_REST_Request $req) {

        $g = self::guards($req);

        if ($g instanceof \WP_Error) return $g;

    

        [$uid, $year_id, $group_id, $levelLabel, $school_level_id] = $g;

    

        if (!$group_id) {

            return rest_ensure_response(['rows' => []]);

        }

    

        global $wpdb;

        $uc = $wpdb->prefix . 'ouin_exo_user_competencies';

        $c  = $wpdb->prefix . 'ouin_exo_competencies';

        $t  = $wpdb->prefix . 'ouin_exo_competency_teaching';

    

        $sql = "

          SELECT c.domain,

                 c.competency AS label,

                 c.capacity,

                 c.example,

                 COALESCE(uc.status, 'not_acquired') AS status

            FROM {$t} t

            JOIN {$c} c

              ON c.id = t.competency_id

            LEFT JOIN {$uc} uc

              ON uc.user_id = %d

             AND uc.year_id = %d

             AND uc.group_id = %d

             AND uc.competency_id = t.competency_id

           WHERE t.year_id = %d

             AND t.group_id = %d

             AND t.teaching_state = 'seen'

             AND " . CompetencyLevels::level_filter_sql('c') . "

           ORDER BY 

             CASE

               WHEN c.track = 'NSI' THEN 1

               WHEN c.track = 'SNT' THEN 2

               ELSE 3

             END,

             c.domain,

             c.slug

        ";

    

        $rows = $wpdb->get_results($wpdb->prepare(

            $sql,

            $uid,

            $year_id,

            $group_id,

            $year_id,

            $group_id,

            $school_level_id

        ));

    

        return rest_ensure_response(['rows' => $rows]);

    }



    private static function current_student_level_label(int $user_id): string {

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';

    

        if ($user_id <= 0) {

            return '';

        }

    

        $year_id = 0;

    

        if (class_exists('\\Ouinpo\\Exercises\\Years')) {

            $active_id = \Ouinpo\Exercises\Years::active_id();

            $year_id = $active_id ? (int) $active_id : 0;

        }

    

        if ($year_id <= 0) {

            $year_id = (int) get_option('ouin_exo_active_year_id', 0);

        }

    

        $sql = "

            SELECT sl.label

            FROM {$p}group_members gm

            JOIN {$p}groups g

              ON g.id = gm.group_id

            JOIN {$p}school_levels sl

              ON sl.id = COALESCE(gm.school_level_id_override, g.school_level_id)

            WHERE gm.user_id = %d

              AND gm.role = 'student'

        ";

    

        $params = [$user_id];

    

        if ($year_id > 0) {

            $sql .= " AND (g.year_id = %d OR g.year_id IS NULL)";

            $params[] = $year_id;

        }

    

        $sql .= " ORDER BY gm.group_id DESC LIMIT 1";

    

        $level = (string) $wpdb->get_var($wpdb->prepare($sql, $params));

    

        return $level;

    }

    

    /* --------- Badges de l’élève --------- */



    public static function badges(\WP_REST_Request $req) {

        $uid = get_current_user_id();

        if (!$uid) {

            return new \WP_Error('auth', 'Connexion requise', ['status' => 401]);

        }

        

        $student_level = self::current_student_level_label($uid);

    

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';
        $cycleLevels = array_map('strval', (array) $wpdb->get_col("
            SELECT label
            FROM {$p}school_levels
            ORDER BY sort_order ASC, id ASC
        "));

    

        $rows = $wpdb->get_results($wpdb->prepare("

            SELECT

                ub.badge_id,

                ub.awarded_at,

                ub.source,

                b.slug,

                b.title,

                b.description,

                b.theme,

                b.image_url

            FROM {$p}user_badges ub

            JOIN {$p}badges b ON b.id = ub.badge_id

            WHERE ub.user_id = %d

            ORDER BY ub.awarded_at DESC, b.title ASC

        ", $uid), ARRAY_A);
        $levels_order = ['Spécial'];
        $school_level_labels = (array) $wpdb->get_col("
            SELECT label
            FROM {$p}school_levels
            ORDER BY sort_order DESC, id DESC
        ");
        $levels_order = array_merge($levels_order, array_map('strval', $school_level_labels));
        if ($student_level !== '' && !in_array($student_level, $levels_order, true)) {
            array_splice($levels_order, 1, 0, [$student_level]);
        }

        $current_title_badge_id = (int) get_user_meta($uid, 'ouinpo_title_badge_id', true);

    

        if (!$rows) {

            return rest_ensure_response([

                'meta'                   => [],

                'special'                => [],

                'domain'                 => [],

                'competency'             => [],

                'levels_order'           => $levels_order,

                'current_title_badge_id' => $current_title_badge_id,

            ]);

        }

    

        $domain_rows = $wpdb->get_results("

            SELECT DISTINCT c.domain_slug, c.domain, sl.label AS level

            FROM {$p}competencies c

            INNER JOIN {$p}competency_school_level csl ON csl.competency_id = c.id

            INNER JOIN {$p}school_levels sl ON sl.id = csl.school_level_id

            WHERE c.domain_slug IS NOT NULL

              AND c.domain_slug <> ''

        ", ARRAY_A);

    

        $domains_by_slug = [];

        foreach ($domain_rows as $dr) {

            $key = strtolower(trim((string)($dr['domain_slug'] ?? '')));

            if ($key === '') continue;

    

            if (!isset($domains_by_slug[$key])) {

                $domains_by_slug[$key] = [

                    'slug'   => (string) $dr['domain_slug'],

                    'domain' => (string) $dr['domain'],

                    'levels' => [],

                ];

            }

    

            if (!empty($dr['level'])) {

                $domains_by_slug[$key]['levels'][(string) $dr['level']] = true;

            }

        }

    

        $infer_level = static function(array $badge): string {

            $slug  = strtolower((string)($badge['slug'] ?? ''));

            $theme = strtolower((string)($badge['theme'] ?? ''));

            if ($theme === 'special' || strpos($slug, 'special-') === 0) {

                return 'SpÃ©cial';

            }

            return '';

        };

        $pick_domain_level = static function(array $levels, string $student_level = '') use ($cycleLevels): string {

            if ($student_level !== '' && !empty($levels[$student_level])) {

                return $student_level;

            }

            foreach ($cycleLevels as $candidate) {

                if (!empty($levels[$candidate])) {

                    return $candidate;

                }

            }

            $labels = array_keys(array_filter($levels));

            return $labels ? (string) reset($labels) : '';

        };

    

        $out = [

            'meta'                   => [],

            'special'                => [],

            'domain'                 => [],

            'competency'             => [],

            'levels_order'           => $levels_order,

            'current_title_badge_id' => $current_title_badge_id,

        ];

    

        foreach ($rows as $row) {

            $slug  = strtolower((string)($row['slug'] ?? ''));

            $theme = strtolower((string)($row['theme'] ?? ''));

    

            $is_meta    = (strpos($theme, 'meta') === 0) || (strpos($slug, 'meta-') === 0);

            $is_special = ($theme === 'special') || (strpos($slug, 'special-') === 0);

    

            $domain_slug   = null;

            $domain_label  = null;

            $domain_levels = [];

    

            if (!$is_meta && !$is_special && $theme !== '' && isset($domains_by_slug[$theme])) {

                $domain_slug   = $domains_by_slug[$theme]['slug'];

                $domain_label  = $domains_by_slug[$theme]['domain'];

                $domain_levels = $domains_by_slug[$theme]['levels'];

            }

    

            $level = $infer_level($row);
            if ($is_special) {

                $level = 'SpÃ©cial';

            } elseif ($domain_slug) {

                $level = $pick_domain_level($domain_levels, $student_level);

            }



            // Sécurité d'affichage : un élève ne voit, dans ses badges de domaine,

            // que les badges transversaux ou les badges correspondant à son niveau courant.

            // Les badges spéciaux et méta restent gérés à part.

            if ($student_level) {

                if (!$is_meta && !$is_special && $domain_slug) {

                    $allowed_for_student = ($level === $student_level);

            

                    if (!$allowed_for_student) {

                        continue;

                    }

                }

            }

    

            $badge = [

                'id'          => (int) $row['badge_id'],

                'slug'        => (string) $row['slug'],

                'title'       => (string) $row['title'],

                'description' => (string) ($row['description'] ?? ''),

                'theme'       => (string) $row['theme'],

                'image_url'   => (string) ($row['image_url'] ?? ''),

                'awarded_at'  => (string) $row['awarded_at'],

                'source'      => (string) ($row['source'] ?? ''),

                'domain_slug' => $domain_slug,

                'domain'      => $domain_label,

                'level'       => $level ?: 'SpÃ©cial',

            ];

    

            if ($is_meta) {

                $out['meta'][] = $badge;

            } elseif ($is_special) {

                $out['special'][] = $badge;

            } elseif ($domain_slug) {

                $out['domain'][] = $badge;

            } else {

                $out['competency'][] = $badge;

            }

        }

    

        return rest_ensure_response($out);

    }

    

public static function competencies_kpi(\WP_REST_Request $req) {

    $g = self::guards($req);

    if ($g instanceof \WP_Error) return $g;



    [$uid, $year_id, $group_id, $levelLabel, $school_level_id] = $g;



    $empty = [

        'summary' => [

            'total'    => 0,

            'worked'   => 0,

            'solid'    => 0,

            'priority' => 0,

        ],

        'rows' => [],

    ];



    if (!$group_id || !$school_level_id) {
        return rest_ensure_response($empty);
    }



    global $wpdb;

    $p = $wpdb->prefix . 'ouin_exo_';



    $tblGroups = $p . 'groups';

    $tblTeach  = $p . 'competency_teaching';

    $tblComp   = $p . 'competencies';

    $tblUC     = $p . 'user_competencies';

    $tblEC     = $p . 'exercise_competency';

    $tblESL    = $p . 'exercise_school_level';

    $tblExo    = $p . 'exercises';

    $tblUS     = $p . 'user_status';

    $tblEM     = $p . 'exam_meta';



    $school_level_id = (int) $wpdb->get_var($wpdb->prepare(

        "SELECT school_level_id

           FROM {$tblGroups}

          WHERE id = %d

          LIMIT 1",

        $group_id

    ));



    if (!$school_level_id) {

        return rest_ensure_response($empty);

    }



    $sql = "

        SELECT

            c.id AS competency_id,

            c.domain,

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

                 AND us.status IN ('attempted', 'solved')

                 AND em.exercise_id IS NULL

                THEN ec.exercise_id

            END) AS attempted_count,



            COUNT(DISTINCT CASE

                WHEN esl.school_level_id IS NOT NULL

                 AND us.status = 'solved'

                 AND em.exercise_id IS NULL

                THEN ec.exercise_id

            END) AS solved_count,



            COUNT(DISTINCT CASE

                WHEN esl.school_level_id IS NOT NULL

                 AND us.status = 'solved'

                 AND e.difficulty_id = 1

                 AND em.exercise_id IS NULL

                THEN ec.exercise_id

            END) AS solved_beginner_count,



            COUNT(DISTINCT CASE

                WHEN esl.school_level_id IS NOT NULL

                 AND us.status = 'solved'

                 AND e.difficulty_id = 2

                 AND em.exercise_id IS NULL

                THEN ec.exercise_id

            END) AS solved_confirmed_count,



            COUNT(DISTINCT CASE

                WHEN esl.school_level_id IS NOT NULL

                 AND us.status = 'solved'

                 AND e.difficulty_id = 3

                 AND em.exercise_id IS NULL

                THEN ec.exercise_id

            END) AS solved_expert_count



        FROM {$tblTeach} t

        JOIN {$tblComp} c

          ON c.id = t.competency_id



        LEFT JOIN {$tblUC} uc

          ON uc.user_id = %d

         AND uc.year_id = %d

         AND uc.group_id = %d

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

          ON us.user_id = %d

         AND us.exercise_id = ec.exercise_id



        WHERE t.year_id = %d

          AND t.group_id = %d

          AND t.teaching_state = 'seen'

          AND " . CompetencyLevels::level_filter_sql('c') . "



        GROUP BY

            c.id, c.domain, c.competency, c.slug, uc.status, c.track, c.level



        ORDER BY

            CASE

              WHEN c.track = 'NSI' THEN 1

              WHEN c.track = 'SNT' THEN 2

              ELSE 3

            END,

            c.domain ASC,

            c.slug ASC

    ";



    $rows = $wpdb->get_results($wpdb->prepare(

        $sql,

        $uid,

        $year_id,

        $group_id,

        $school_level_id,

        $uid,

        $year_id,

        $group_id,

        $school_level_id

    ), ARRAY_A) ?: [];



    $summary = [

        'total'    => 0,

        'worked'   => 0,

        'solid'    => 0,

        'priority' => 0,

    ];



    foreach ($rows as &$row) {

        $row['competency_id']          = (int) $row['competency_id'];

        $row['available_count']        = (int) $row['available_count'];

        $row['attempted_count']        = (int) $row['attempted_count'];

        $row['solved_count']           = (int) $row['solved_count'];

        $row['solved_beginner_count']  = (int) $row['solved_beginner_count'];

        $row['solved_confirmed_count'] = (int) $row['solved_confirmed_count'];

        $row['solved_expert_count']    = (int) $row['solved_expert_count'];



        $available = $row['available_count'];

        $attempted = $row['attempted_count'];

        $solved    = $row['solved_count'];



        $row['coverage_pct'] = $available > 0 ? (int) round(100 * $attempted / $available) : 0;

        $row['success_pct']  = $attempted > 0 ? (int) round(100 * $solved / $attempted) : 0;



        $summary['total']++;



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



    return rest_ensure_response([

        'summary' => $summary,

        'rows'    => $rows,

    ]);

}    

    

    

    /* --------- Route overview d’origine --------- */

    public static function overview(): WP_REST_Response {

        global $wpdb;

        $p   = $wpdb->prefix . 'ouin_exo_';

        $uid = get_current_user_id();

    

        $total = (int) $wpdb->get_var($wpdb->prepare("

            SELECT COUNT(*)

            FROM {$p}user_status us

            JOIN {$p}exercises e

              ON e.id = us.exercise_id

            LEFT JOIN {$p}exam_meta em

              ON em.exercise_id = e.id

             AND em.exam_type = 'practical_subject'

            WHERE us.user_id = %d

              AND em.exercise_id IS NULL

        ", $uid));

    

        $solved = (int) $wpdb->get_var($wpdb->prepare("

            SELECT COUNT(*)

            FROM {$p}user_status us

            JOIN {$p}exercises e

              ON e.id = us.exercise_id

            LEFT JOIN {$p}exam_meta em

              ON em.exercise_id = e.id

             AND em.exam_type = 'practical_subject'

            WHERE us.user_id = %d

              AND us.status = 'solved'

              AND em.exercise_id IS NULL

        ", $uid));

    

        $badges = (int) $wpdb->get_var($wpdb->prepare("

            SELECT COUNT(*)

            FROM {$p}user_badges

            WHERE user_id = %d

        ", $uid));

    

        $reco = $wpdb->get_results("

            SELECT e.*

            FROM {$p}exercises e

            LEFT JOIN {$p}difficulties d

              ON d.id = e.difficulty_id

            LEFT JOIN {$p}exam_meta em

              ON em.exercise_id = e.id

             AND em.exam_type = 'practical_subject'

            WHERE e.is_active = 1

              AND em.exercise_id IS NULL

              AND (e.difficulty_id IS NULL OR d.slug = 'debutant')

            GROUP BY e.id

            ORDER BY e.id DESC

            LIMIT 6

        ");

    

        return new WP_REST_Response([

            'counts' => [

                'total'     => $total,

                'solved'    => $solved,

                'succeeded' => $solved,

                'badges'    => $badges,

            ],

            'recommendations' => $reco,

        ], 200);

    }

public static function assessments_progress(\WP_REST_Request $req) {

    $g = self::guards($req);

    if ($g instanceof \WP_Error) return $g;



    [$uid, $year_id, $group_id, $levelLabel, $school_level_id] = $g;

    if (!$group_id || !$school_level_id) {
        return rest_ensure_response([
            'summary' => [
                'evaluated'      => 0,
                'acquired'       => 0,
                'consolidating'  => 0,
                'in_progress'    => 0,
                'not_acquired'   => 0,
                'assessments'    => 0,
            ],
            'assessment_options'     => [],
            'selected_assessment_id' => 0,
            'priorities'             => [],
            'competencies'           => [],
        ]);
    }

    global $wpdb;

    $tblR   = $wpdb->prefix . 'ouin_exo_assessment_results';

    $tblA   = $wpdb->prefix . 'ouin_exo_assessments';

    $tblC   = $wpdb->prefix . 'ouin_exo_competencies';

    $tblGrp = $wpdb->prefix . 'ouin_exo_groups';



    $where = [

        'r.user_id = %d',

        'g.year_id = %d',

        CompetencyLevels::level_filter_sql('c')

    ];

    $args = [$uid, $year_id, $school_level_id];



    if ($group_id) {

        $where[] = 'a.group_id = %d';

        $args[] = $group_id;

    }



    $assessmentFilter = (int) $req->get_param('assessment_id');



    $sqlAssessmentOptions = "

        SELECT DISTINCT

            a.id,

            a.title,

            a.due_on

        FROM {$tblR} r

        JOIN {$tblA} a   ON a.id = r.assessment_id

        JOIN {$tblGrp} g ON g.id = a.group_id

        JOIN {$tblC} c   ON c.id = r.competency_id

        WHERE " . implode(' AND ', $where) . "

        ORDER BY a.due_on DESC, a.id DESC

    ";



    $assessmentOptions = $wpdb->get_results(

        $wpdb->prepare($sqlAssessmentOptions, $args),

        ARRAY_A

    ) ?: [];



    if ($assessmentFilter > 0) {

        $where[] = 'r.assessment_id = %d';

        $args[] = $assessmentFilter;

    }



    $sql = "

        SELECT

            r.assessment_id,

            a.title AS assessment_title,

            a.due_on,

            c.id AS competency_id,

            c.domain,

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

        WHERE " . implode(' AND ', $where) . "

        ORDER BY a.due_on DESC, r.assessment_id DESC, c.domain ASC, c.id ASC

    ";



    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];



    if (!$rows) {

        return rest_ensure_response([

            'summary' => [

                'evaluated'      => 0,

                'acquired'       => 0,

                'consolidating'  => 0,

                'in_progress'    => 0,

                'not_acquired'   => 0,

                'assessments'    => 0,

            ],

            'assessment_options'     => array_map(static function(array $row): array {

                return [

                    'id'     => (int) $row['id'],

                    'title'  => (string) $row['title'],

                    'due_on' => (string) ($row['due_on'] ?? ''),

                ];

            }, $assessmentOptions),

            'selected_assessment_id' => $assessmentFilter,

            'priorities'             => [],

            'competencies'           => [],

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



    $by_comp = [];

    $assessment_ids = [];



    foreach ($rows as $row) {

        $assessment_ids[(int)$row['assessment_id']] = true;

        $cid = (int)$row['competency_id'];



        if (!isset($by_comp[$cid])) {

            $by_comp[$cid] = [

                'competency_id' => $cid,

                'domain'        => (string)$row['domain'],

                'label'         => (string)$row['label'],

                'capacity'      => (string)($row['capacity'] ?? ''),

                'example'       => (string)($row['example'] ?? ''),

                'slug'          => (string)($row['slug'] ?? ''),

                'history'       => [],

            ];

        }



        $by_comp[$cid]['history'][] = [

            'assessment_id'    => (int)$row['assessment_id'],

            'assessment_title' => (string)$row['assessment_title'],

            'due_on'           => (string)$row['due_on'],

            'status'           => (string)$row['observed_status'],

            'note'             => (string)($row['note'] ?? ''),

            'updated_at'       => (string)$row['updated_at'],

        ];

    }



    $summary = [

        'evaluated'      => 0,

        'acquired'       => 0,

        'consolidating'  => 0,

        'in_progress'    => 0,

        'not_acquired'   => 0,

        'assessments'    => count($assessment_ids),

    ];



    $competencies = [];



    foreach ($by_comp as $cid => $item) {

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

            if ($delta > 0) {

                $trend = 'up';

            } elseif ($delta < 0) {

                $trend = 'down';

            } else {

                $trend = ($current === 'acquired') ? 'confirmed' : 'stable';

            }

        }



        $summary['evaluated']++;

        if (isset($summary[$current])) {

            $summary[$current]++;

        }



        $competencies[] = [

            'competency_id'       => $cid,

            'domain'              => $item['domain'],

            'label'               => $item['label'],

            'capacity'            => $item['capacity'],

            'example'             => $item['example'],

            'slug'                => $item['slug'],

            'current_status'      => $current,

            'trend'               => $trend,

            'last_assessment'     => $history[0]['assessment_title'] ?? '',

            'last_assessment_date'=> $history[0]['due_on'] ?? '',

            'history'             => array_slice($history, 0, 3),

        ];

    }



    usort($competencies, static function(array $a, array $b) use ($rank): int {

        $ra = $rank($a['current_status']);

        $rb = $rank($b['current_status']);



        if ($ra !== $rb) return $ra <=> $rb;

        if ($a['domain'] !== $b['domain']) return strcmp($a['domain'], $b['domain']);

        return strcmp($a['label'], $b['label']);

    });



    $priorities = array_values(array_filter($competencies, static function(array $row): bool {

        return in_array($row['current_status'], ['not_acquired', 'in_progress'], true);

    }));



    $priorities = array_slice($priorities, 0, 3);



    return rest_ensure_response([

        'summary'                => $summary,

        'assessment_options'     => array_map(static function(array $row): array {

            return [

                'id'     => (int) $row['id'],

                'title'  => (string) $row['title'],

                'due_on' => (string) ($row['due_on'] ?? ''),

            ];

        }, $assessmentOptions),

        'selected_assessment_id' => $assessmentFilter,

        'priorities'             => $priorities,

        'competencies'           => $competencies,

    ]);

}



    /**

     * POST /ouinpo/v1/me/title

     * Body JSON : { "badge_id": 55 }

     * Met à jour le titre affiché de l'utilisateur à partir d'un badge qu'il possède.

     */

    public static function set_title(\WP_REST_Request $request): \WP_REST_Response {

        if (!is_user_logged_in()) {

            return new \WP_REST_Response(['error' => 'not_logged_in'], 401);

        }



        $user_id  = get_current_user_id();

        $badge_id = (int)$request->get_param('badge_id');



        if ($badge_id <= 0) {

            return new \WP_REST_Response(['error' => 'invalid_badge'], 400);

        }



        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';



        // Vérifier que l'utilisateur possède bien ce badge

        $has = (int)$wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) 

             FROM {$p}user_badges 

             WHERE user_id = %d AND badge_id = %d",

            $user_id,

            $badge_id

        ));



        if ($has <= 0) {

            return new \WP_REST_Response(['error' => 'forbidden'], 403);

        }



        // On stocke l'id du badge comme titre choisi

        update_user_meta($user_id, 'ouinpo_title_badge_id', $badge_id);



        // Optionnel : on renvoie le texte du titre

        $title = $wpdb->get_var($wpdb->prepare(

            "SELECT title FROM {$p}badges WHERE id = %d",

            $badge_id

        ));



        return new \WP_REST_Response([

            'ok'       => true,

            'badge_id' => $badge_id,

            'title'    => $title,

        ], 200);

    }



}

