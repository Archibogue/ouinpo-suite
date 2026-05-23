<?php



namespace Ouinpo\Exercises\Rest;



use Ouinpo\Suite\Core\Capabilities;
use WP_REST_Request;

use WP_REST_Response;



defined('ABSPATH') || exit;



class BadgesRoutes {



    const NS = 'ouinpo/v1';



    public static function register() {



        // Liste / création des badges

        register_rest_route(self::NS, '/badges', [

            [

                'methods'             => 'GET',

                'callback'            => [__CLASS__, 'list'],

                // Public: les badges affichables peuvent être consultés côté élève.
                'permission_callback' => '__return_true'

            ],

            [

                'methods'             => 'POST',

                'callback'            => [__CLASS__, 'create'],

                'permission_callback' => function () {

                    return Capabilities::can(Capabilities::MANAGE_BADGES);

                }

            ],

        ]);

    }



    /* ============================================================

     * LISTE / CRÉATION

     * ========================================================== */



    public static function list(): WP_REST_Response {

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';

        $rows = $wpdb->get_results("SELECT id, slug, title, description, theme, image_url FROM {$p}badges ORDER BY title");

        return new WP_REST_Response($rows, 200);

    }



    public static function create(WP_REST_Request $r): WP_REST_Response {

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';



        $data = [

            'slug'        => sanitize_title($r['slug']),

            'title'       => sanitize_text_field($r['title']),

            'description' => wp_kses_post($r['description']),

            'theme'       => sanitize_text_field($r['theme']),

            'image_url'   => esc_url_raw($r['image_url']),

        ];



        $wpdb->insert("{$p}badges", $data);

        return new WP_REST_Response(['id' => (int) $wpdb->insert_id], 201);

    }



    /* ============================================================

     * HELPER : Récupération du niveau d'élève via les groupes

     * ========================================================== */



    private static function get_student_level(int $user_id): string {

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';



        $row = $wpdb->get_row($wpdb->prepare(

            "SELECT sl.label
             FROM {$p}group_members gm

             JOIN {$p}groups g ON g.id = gm.group_id

             JOIN {$p}school_levels sl ON sl.id = COALESCE(gm.school_level_id_override, g.school_level_id)
             WHERE gm.user_id = %d AND gm.role = 'student'

             ORDER BY gm.group_id DESC

             LIMIT 1",

             $user_id

        ));



        if (!$row || empty($row->label)) return '';


        return (string) $row->label;
    }



    /* ============================================================

     * BADGES DE L’ÉLÈVE (sans badge_requirements)

     * ========================================================== */



    public static function my_badges(): WP_REST_Response {

        global $wpdb;

        $p   = $wpdb->prefix . 'ouin_exo_';

        $uid = get_current_user_id();



        if (!$uid) {

            return new WP_REST_Response(['error' => 'not_logged_in'], 401);

        }



        $student_level = self::get_student_level($uid);

        $cycleLevels = array_map('strval', (array) $wpdb->get_col("
            SELECT label
            FROM {$p}school_levels
            ORDER BY sort_order ASC, id ASC
        "));



        /* -------- 1) Badges obtenus -------- */

        $base = $wpdb->get_results($wpdb->prepare(

            "SELECT b.*, ub.awarded_at, ub.source

             FROM {$p}user_badges ub

             JOIN {$p}badges b ON b.id = ub.badge_id

             WHERE ub.user_id = %d

             ORDER BY ub.awarded_at DESC",

             $uid

        ));



        if (!$base) {

            return new WP_REST_Response([

                'meta'                 => [],

                'special'              => [],

                'domain'               => [],

                'competency'           => [],

                'levels_order'         => [],

                'current_title_badge_id' => 0,

            ], 200);

        }



        /* -------- 2) Domaines connus à partir des compétences -------- */

        $domains = $wpdb->get_results("

            SELECT DISTINCT
                COALESCE(NULLIF(d.slug, ''), c.domain_slug) AS domain_slug,
                COALESCE(NULLIF(d.label, ''), c.domain) AS domain,
                sl.label AS level
            FROM {$p}competencies c
            LEFT JOIN {$p}domains d ON d.id = c.domain_id
            INNER JOIN {$p}competency_school_level csl ON csl.competency_id = c.id

            INNER JOIN {$p}school_levels sl ON sl.id = csl.school_level_id

            WHERE COALESCE(NULLIF(d.slug, ''), c.domain_slug) IS NOT NULL
              AND COALESCE(NULLIF(d.slug, ''), c.domain_slug) <> ''
              AND COALESCE(d.active, 1) = 1
        ");



        // slug minuscule -> ['slug' => original, 'domain' => label, 'levels' => [level => true]]

        $domainsBySlug = [];

        foreach ($domains as $d) {

            $key = strtolower($d->domain_slug);

            if (!isset($domainsBySlug[$key])) {

                $domainsBySlug[$key] = [

                    'slug'   => $d->domain_slug,

                    'domain' => $d->domain,

                    'levels' => [],

                ];

            }

            if (!empty($d->level)) {

                $domainsBySlug[$key]['levels'][$d->level] = true;

            }

        }



        /* -------- 3) Construction & classement -------- */

        $meta        = [];

        $special     = [];

        $domainBadges= [];

        $competency  = [];



        foreach ($base as $row) {



            $id    = (int) $row->id;

            $slug  = strtolower($row->slug ?? '');

            $theme = strtolower($row->theme ?? '');



            // META / SPÉCIAUX

            $isMeta    = str_starts_with($theme, 'meta') || str_starts_with($slug, 'meta-');

            $isSpecial = ($theme === 'special') || str_starts_with($slug, 'special-');



            // Domaine & niveaux déduits depuis theme -> domain_slug

            $domainSlug  = null;

            $domainLabel = null;

            $levels_raw  = [];



            if ($theme && !$isMeta && !$isSpecial) {

                $key = $theme;

                if (isset($domainsBySlug[$key])) {

                    $info        = $domainsBySlug[$key];

                    $domainSlug  = $info['slug'];

                    $domainLabel = $info['domain'];

                    $levels_raw  = array_keys($info['levels']);

                }

            }



            // Calcul des niveaux

            $levels = [];

            if ($levels_raw) {

                // On privilégie les niveaux de cycle (Seconde/Première/Terminale)

                $cycleHits = array_intersect($cycleLevels, $levels_raw);

                if ($cycleHits) {

                    foreach ($cycleLevels as $c) {

                        if (in_array($c, $levels_raw, true)) {

                            $levels[] = $c;

                        }

                    }

                } else {

                    $levels = $levels_raw;

                }

            } else {

                // Fallback : comme avant, basé sur le theme (utile surtout pour les méta-badges)

                if (str_starts_with($theme,'meta-seconde')) {

                    $levels = ['Seconde'];

                } elseif (str_starts_with($theme,'meta-premiere') || str_starts_with($theme,'meta-première')) {

                    $levels = ['Première'];

                } elseif (str_starts_with($theme,'meta-terminale')) {

                    $levels = ['Terminale'];

                } else {

                    $levels = ['Spécial'];

                }

            }



            // Niveau principal pour affichage

            if ($student_level && in_array($student_level, $levels, true)) {

                $main = $student_level;

            } else {

                $main = $levels[0];

            }



            // VISIBILITÉ SELON LE NIVEAU (même logique qu'avant)

            if ($student_level) {
                if (!$isMeta && !$isSpecial) {

                    if (!in_array($student_level, $levels, true)) {

                        continue;

                    }

                }

            }



            $badgeArr = [

                'id'          => $id,

                'slug'        => $row->slug,

                'title'       => $row->title,

                'description' => $row->description,

                'theme'       => $row->theme,

                'image_url'   => $row->image_url,

                'awarded_at'  => $row->awarded_at,

                'source'      => $row->source,

                'domain_slug' => $domainSlug,

                'domain'      => $domainLabel,

                'level'       => $main,

                'levels'      => $levels,

            ];



            if ($isMeta) {

                $meta[] = $badgeArr;

            } elseif ($isSpecial) {

                $special[] = $badgeArr;

            } elseif ($domainSlug) {

                // Badge de domaine : theme a matché un domain_slug BO

                $domainBadges[] = $badgeArr;

            } else {

                // Badge de compétence (ou autre non rattaché à un domaine BO)

                $competency[] = $badgeArr;

            }

        }



        /* -------- 4) Ordre des niveaux -------- */

        $preferred     = $cycleLevels ?: [];

        $levels_order  = [];



        if ($student_level) {
            $allowed = [$student_level];

            foreach ($preferred as $lbl) {

                if (in_array($lbl, $allowed, true)) {

                    $levels_order[] = $lbl;

                }

            }

            if (!in_array($student_level, $levels_order, true)) {

                $levels_order[] = $student_level;

            }

        } else {
            foreach ($preferred as $lbl) {

                $levels_order[] = $lbl;

            }

        }



        /* -------- 5) Badge titre actuel -------- */

        $current_title_badge_id = (int) get_user_meta($uid, 'ouinpo_title_badge_id', true);



        return new WP_REST_Response([

            'meta'                  => array_values($meta),

            'special'               => array_values($special),

            'domain'                => array_values($domainBadges),

            'competency'            => array_values($competency),

            'levels_order'          => $levels_order,

            'current_title_badge_id'=> $current_title_badge_id,

        ], 200);

    }



}
