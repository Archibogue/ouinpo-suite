<?php
namespace Ouinpo\Exercises\Rest;

defined('ABSPATH') || exit;

class ExercisesRoutes {
    const NS = 'ouinpo/v1';

    public static function register() {
        // Liste
        register_rest_route(self::NS, '/exercises', array(
            array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'index'),
                'permission_callback' => '__return_true',
            ),
        ));

        // Détail
        register_rest_route(self::NS, '/exercises/(?P<id>\d+)', array(
            array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'show'),
                'permission_callback' => '__return_true',
            ),
        ));

        // Solutions (métadonnées)
        register_rest_route(self::NS, '/exercises/(?P<id>\d+)/solutions', array(
            array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'solutions'),
                'permission_callback' => '__return_true',
            ),
        ));

        // ✅ Reveal solution (contenu public, trace seulement si connecté)
        register_rest_route(self::NS, '/exercises/(?P<id>\d+)/solutions/(?P<solution_id>\d+)/reveal', array(
            array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'reveal_solution'),
                'permission_callback' => '__return_true', // ✅ anonymes OK
            ),
        ));

        // ✅ Reveal hint (contenu public, trace seulement si connecté)
        register_rest_route(self::NS, '/exercises/(?P<id>\d+)/hints/(?P<order>\d+)/reveal', array(
            array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'reveal_hint'),
                'permission_callback' => '__return_true', // ✅ anonymes OK
            ),
        ));


        // 👉 Révélations déjà vues (indices + solutions) : reste réservé aux connectés
        register_rest_route(self::NS, '/exercises/(?P<id>\d+)/reveals', array(
            array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'reveals'),
                'permission_callback' => function(){ return is_user_logged_in(); },
            ),
        ));
    }

    // ------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------
    private static function sanitize_school_level($raw) {
        $raw = sanitize_key((string)$raw);
        return substr($raw, 0, 20);
    }

    /** Liste des exercices — garde-fous (colonnes/tables optionnelles) */
    public static function index($r) {
        global $wpdb;
    
        $p           = $wpdb->prefix . 'ouin_exo_';
        $table_exo   = $p . 'exercises';
        $table_esl   = $p . 'exercise_school_level';
        $table_lvl   = $p . 'school_levels';
        $table_diff  = $p . 'difficulties';
        $table_ec    = $p . 'exercise_competency';
        $table_comp  = $p . 'competencies';
        $table_exam  = $p . 'exam_meta';
    
        $include_status = ($r instanceof \WP_REST_Request) && $r->get_param('include_status');
        $uid = get_current_user_id();
    
        // 🔎 Filtres REST
        $difficulty    = '';
        $domain_slug   = '';
        $competency_id = 0;
        $competency    = '';
        $school_level  = '';
        $exam_only     = 0;
        $source_type   = '';
        $theme_bac     = '';
        $bac_format    = '';
        $session_label = '';

        if ($r instanceof \WP_REST_Request) {
            $difficulty    = sanitize_text_field($r->get_param('difficulty'));
            $domain_slug   = sanitize_text_field($r->get_param('domain_slug'));
            $competency_id = (int) $r->get_param('competency_id');
            $competency    = sanitize_text_field($r->get_param('competency'));
            $school_level  = self::sanitize_school_level($r->get_param('school_level'));
            $exam_only     = (int) $r->get_param('exam_only');
            $source_type   = mb_strtolower(sanitize_text_field($r->get_param('source_type')));
            $theme_bac     = sanitize_text_field($r->get_param('theme_bac'));
            $bac_format    = sanitize_text_field($r->get_param('bac_format'));
            $session_label = sanitize_text_field($r->get_param('session_label'));
        }
    
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_exo));
        if (!$exists) {
            return new \WP_REST_Response(array(), 200);
        }
    
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table_exo}");
        $has_is_active  = in_array('is_active', $cols, true);
        $has_created_at = in_array('created_at', $cols, true);
        $has_difficulty = in_array('difficulty_id', $cols, true);
    
        $diff_exists = $has_difficulty && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_diff));
        $ec_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_ec));
        $comp_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_comp));
        $exam_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_exam));
    
        $esl_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_esl));
        $lvl_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_lvl));
        $has_esl_rows = $esl_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_esl}") : 0;
    
        $table_status  = $p . 'user_status';
        $status_exists = $include_status && $uid && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_status));
        $status_sql    = $status_exists
            ? "(SELECT us.status FROM {$table_status} us WHERE us.user_id = {$uid} AND us.exercise_id = e.id) AS status"
            : "NULL AS status";
    
        $exam_select = $exam_exists ? ",
            em.source_type,
            em.session_label,
            em.year_label,
            em.center_label,
            em.theme_bac,
            em.bac_format,
            em.estimated_minutes,
            em.is_exam_like
        " : "";
    
        $rows = null;
    
        // 0) Filtre explicite school_level=... (visiteur ou connecté)
        if ($rows === null && $school_level !== '' && $esl_exists && $lvl_exists && $has_esl_rows > 0) {
    
            $select = "e.id, e.title, e.slug, {$status_sql}{$exam_select}";
            $joins  = "
                JOIN {$table_esl} esl ON esl.exercise_id = e.id
                JOIN {$table_lvl} sl  ON sl.id = esl.school_level_id
            ";
            if ($exam_exists) {
                $joins .= " LEFT JOIN {$table_exam} em ON em.exercise_id = e.id";
            }
            
            $where = array();

            if ($exam_exists) {
                $where[] = "(em.exam_type IS NULL OR em.exam_type <> 'practical_subject')";
            }
    
            
    
            if ($has_is_active) {
                $where[] = "e.is_active = 1";
            }
    
            $where[] = $wpdb->prepare("sl.slug = %s", $school_level);
    
            if ($exam_only && $exam_exists) {
                $where[] = "em.is_exam_like = 1";
            }
            if ($source_type !== '' && $exam_exists) {
                $where[] = $wpdb->prepare("em.source_type = %s", $source_type);
            }
            if ($theme_bac !== '' && $exam_exists) {
                $where[] = $wpdb->prepare("em.theme_bac = %s", $theme_bac);
            }
            if ($bac_format !== '' && $exam_exists) {
                $where[] = $wpdb->prepare("em.bac_format = %s", $bac_format);
            }            
            if ($session_label !== '' && $exam_exists) {
                $where[] = $wpdb->prepare("em.session_label = %s", $session_label);
            }
    
            if ($difficulty !== '' && $diff_exists) {
                $joins   .= " LEFT JOIN {$table_diff} d ON d.id = e.difficulty_id";
                $where[]  = $wpdb->prepare("d.slug = %s", $difficulty);
            }
    
            if ($ec_exists) {
                if ($competency_id > 0) {
                    $joins   .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id";
                    $where[]  = $wpdb->prepare("ec.competency_id = %d", $competency_id);
                } elseif ($domain_slug !== '' && $comp_exists) {
                    $joins   .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id
                                 JOIN {$table_comp} c ON c.id = ec.competency_id";
                    $where[]  = $wpdb->prepare("c.domain_slug = %s", $domain_slug);
                } elseif ($competency !== '' && $comp_exists) {
                    $joins   .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id
                                 JOIN {$table_comp} c ON c.id = ec.competency_id";
                    $where[]  = $wpdb->prepare("c.slug = %s", $competency);
                }
            }
    
            $where_sql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
            $order     = $has_created_at ? "ORDER BY e.created_at DESC" : "ORDER BY e.id DESC";
    
            $sql = "
                SELECT DISTINCT {$select}
                FROM {$table_exo} e
                {$joins}
                {$where_sql}
                {$order}
                LIMIT 200
            ";
    
            $rows = $wpdb->get_results($sql);
        }
    
        // 1) Connecté : filtre auto niveaux effectifs élève
        if ($rows === null && $uid && $esl_exists && $has_esl_rows > 0
            && class_exists('\Ouinpo\Exercises\Years') && class_exists('\Ouinpo\Exercises\LevelsSchool')) {
    
            $year   = \Ouinpo\Exercises\Years::active_id();
            $levels = (array) \Ouinpo\Exercises\LevelsSchool::effective_for_user($uid, $year);
            $levels = array_filter(array_map('intval', $levels));
    
            if (!empty($levels)) {
                $in = implode(',', $levels);
    
                $select = "e.id, e.title, e.slug, {$status_sql}{$exam_select}";
                $joins  = "JOIN {$table_esl} esl ON esl.exercise_id = e.id";
                if ($exam_exists) {
                    $joins .= " LEFT JOIN {$table_exam} em ON em.exercise_id = e.id";
                }
                
                $where = array();

                if ($exam_exists) {
                    $where[] = "(em.exam_type IS NULL OR em.exam_type <> 'practical_subject')";
                }
    
                
    
                if ($has_is_active) {
                    $where[] = "e.is_active = 1";
                }
    
                $where[] = "esl.school_level_id IN ({$in})";
    
                if ($exam_only && $exam_exists) {
                    $where[] = "em.is_exam_like = 1";
                }
                if ($source_type !== '' && $exam_exists) {
                    $where[] = $wpdb->prepare("em.source_type = %s", $source_type);
                }
                if ($theme_bac !== '' && $exam_exists) {
                    $where[] = $wpdb->prepare("em.theme_bac = %s", $theme_bac);
                }
                if ($bac_format !== '' && $exam_exists) {
                    $where[] = $wpdb->prepare("em.bac_format = %s", $bac_format);
                }                
                if ($session_label !== '' && $exam_exists) {
                    $where[] = $wpdb->prepare("em.session_label = %s", $session_label);
                }
    
                if ($difficulty !== '' && $diff_exists) {
                    $joins   .= " LEFT JOIN {$table_diff} d ON d.id = e.difficulty_id";
                    $where[]  = $wpdb->prepare("d.slug = %s", $difficulty);
                }
    
                if ($ec_exists) {
                    if ($competency_id > 0) {
                        $joins   .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id";
                        $where[]  = $wpdb->prepare("ec.competency_id = %d", $competency_id);
                    } elseif ($domain_slug !== '' && $comp_exists) {
                        $joins   .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id
                                     JOIN {$table_comp} c ON c.id = ec.competency_id";
                        $where[]  = $wpdb->prepare("c.domain_slug = %s", $domain_slug);
                    } elseif ($competency !== '' && $comp_exists) {
                        $joins   .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id
                                     JOIN {$table_comp} c ON c.id = ec.competency_id";
                        $where[]  = $wpdb->prepare("c.slug = %s", $competency);
                    }
                }
    
                $where_sql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
                $order     = $has_created_at ? "ORDER BY e.created_at DESC" : "ORDER BY e.id DESC";
    
                $sql = "
                    SELECT DISTINCT {$select}
                    FROM {$table_exo} e
                    {$joins}
                    {$where_sql}
                    {$order}
                    LIMIT 200
                ";
    
                $rows = $wpdb->get_results($sql);
            }
        }
    
        // 2) Fallback générique
        if ($rows === null) {
            $select = "e.*, {$status_sql}{$exam_select}";
            $sql    = "SELECT DISTINCT {$select} FROM {$table_exo} e";
            $args   = array();
            $where  = array();
    
            if ($exam_exists) {
                $sql .= " LEFT JOIN {$table_exam} em ON em.exercise_id = e.id";
            }

            if ($exam_exists) {
                $where[] = "(em.exam_type IS NULL OR em.exam_type <> 'practical_subject')";
            }
    
            if ($exam_only && $exam_exists) {
                $where[] = "em.is_exam_like = 1";
            }
    
            if ($source_type !== '' && $exam_exists) {
                $where[] = "em.source_type = %s";
                $args[]  = $source_type;
            }
    
            if ($theme_bac !== '' && $exam_exists) {
                $where[] = "em.theme_bac = %s";
                $args[]  = $theme_bac;
            }

            if ($bac_format !== '' && $exam_exists) {
                $where[] = $wpdb->prepare("em.bac_format = %s", $bac_format);
            }
            
            if ($session_label !== '' && $exam_exists) {
                $where[] = "em.session_label = %s";
                $args[]  = $session_label;
            }
    
            if ($difficulty !== '' && $diff_exists) {
                $sql    .= " LEFT JOIN {$table_diff} d ON d.id = e.difficulty_id";
                $where[] = "d.slug = %s";
                $args[]  = $difficulty;
            }
    
            if ($ec_exists) {
                if ($competency_id > 0) {
                    $sql    .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id";
                    $where[] = "ec.competency_id = %d";
                    $args[]  = $competency_id;
                } elseif ($domain_slug !== '' && $comp_exists) {
                    $sql    .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id
                                JOIN {$table_comp} c ON c.id = ec.competency_id";
                    $where[] = "c.domain_slug = %s";
                    $args[]  = $domain_slug;
                } elseif ($competency !== '' && $comp_exists) {
                    $sql    .= " JOIN {$table_ec} ec ON ec.exercise_id = e.id
                                JOIN {$table_comp} c ON c.id = ec.competency_id";
                    $where[] = "c.slug = %s";
                    $args[]  = $competency;
                }
            }
    
            if ($has_is_active) {
                $where[] = "e.is_active = 1";
            }
    
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
    
            $sql .= $has_created_at ? " ORDER BY e.created_at DESC" : " ORDER BY e.id DESC";
            $sql .= " LIMIT 200";
    
            $rows = !empty($args)
                ? $wpdb->get_results($wpdb->prepare($sql, $args))
                : $wpdb->get_results($sql);
        }
    
        return new \WP_REST_Response(is_array($rows) ? $rows : array(), 200);
    }
    /** Détail (énoncé) — respecte l'existence des colonnes */
    public static function show($r) {
        global $wpdb;
    
        $id = isset($r['id']) ? intval($r['id']) : 0;
        if ($id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide', array('status' => 400));
        }
    
        $p          = $wpdb->prefix . 'ouin_exo_';
        $table_exo  = $p . 'exercises';
        $table_diff = $p . 'difficulties';
        $table_esl  = $p . 'exercise_school_level';
        $table_lvl  = $p . 'school_levels';
        $table_ec   = $p . 'exercise_competency';
        $table_comp = $p . 'competencies';

        $table_exam = $p . 'exam_meta';
        
        $exam_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_exam));
        
        $exam_join = $exam_exists
            ? "LEFT JOIN {$table_exam} em ON em.exercise_id = e.id"
            : "";
        
        $where_not_practical = $exam_exists
            ? "AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')"
            : "";
    
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table_exo}");
        $has_is_active = in_array('is_active', $cols, true);
        $has_level_id  = in_array('level_id', $cols, true);
    
        $where_active = $has_is_active ? "AND e.is_active = 1" : "";
    
        $sql = "
            SELECT 
                e.id,
                e.title,
                e.slug,
                e.statement,
                d.label AS difficulty_label
                " . ($has_level_id ? ", sl_legacy.label AS legacy_level_label" : "") . "
                FROM {$table_exo} e
                LEFT JOIN {$table_diff} d ON d.id = e.difficulty_id
                " . ($has_level_id ? "LEFT JOIN {$table_lvl} sl_legacy ON sl_legacy.id = e.level_id" : "") . "
                {$exam_join}
                WHERE e.id = %d
                {$where_active}
                {$where_not_practical}
                LIMIT 1
        ";
    
        $row = $wpdb->get_row($wpdb->prepare($sql, $id), ARRAY_A);
    
        if (!$row) {
            return new \WP_Error('not_found', 'Exercice introuvable', array('status' => 404));
        }
    
        // Niveaux associés
        $school_levels = array();
    
        $esl_exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_esl));
        $lvl_exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_lvl));
        $ec_exists   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_ec));
        $comp_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_comp));
    
        if ($esl_exists && $lvl_exists) {
            $school_levels = $wpdb->get_col($wpdb->prepare("
                SELECT sl.label
                FROM {$table_esl} esl
                INNER JOIN {$table_lvl} sl ON sl.id = esl.school_level_id
                WHERE esl.exercise_id = %d
                ORDER BY FIELD(sl.slug, 'seconde', 'premiere', 'terminale') = 0,
                         FIELD(sl.slug, 'seconde', 'premiere', 'terminale'),
                         sl.label
            ", $id));
        }
    
        // Fallback ancien champ level_id si jamais la table pivot est vide
        if (empty($school_levels) && !empty($row['legacy_level_label'])) {
            $school_levels = array($row['legacy_level_label']);
        }
    
        // Compétences BO associées
        $competencies = array();
    
        if ($ec_exists && $comp_exists) {
            $competencies = $wpdb->get_col($wpdb->prepare("
                SELECT COALESCE(NULLIF(TRIM(c.label), ''), NULLIF(TRIM(c.competency), ''), CONCAT(c.domain, ' - ', c.slug)) AS comp_label
                FROM {$table_ec} ec
                INNER JOIN {$table_comp} c ON c.id = ec.competency_id
                WHERE ec.exercise_id = %d
                ORDER BY c.domain ASC, comp_label ASC
            ", $id));
        }
    
        $school_levels = array_values(array_unique(array_filter(array_map('strval', (array) $school_levels))));
        $competencies  = array_values(array_unique(array_filter(array_map('strval', (array) $competencies))));
    
        return rest_ensure_response(array(
            'id'               => intval($row['id']),
            'title'            => $row['title'],
            'slug'             => $row['slug'],
            'statement'        => $row['statement'],
            'difficulty_label' => $row['difficulty_label'],
            'school_levels'    => $school_levels,
            'competencies'     => $competencies,
        ));
    }
    /** Solutions (métadonnées) */
    public static function solutions($r) {
        global $wpdb;
        $p  = $wpdb->prefix . 'ouin_exo_';
        $id = isset($r['id']) ? (int)$r['id'] : 0;

        $rows = $wpdb->get_results($wpdb->prepare("
          SELECT id, title, is_official, solution_order
          FROM {$p}solutions
          WHERE exercise_id = %d
          ORDER BY is_official DESC, solution_order ASC
        ", $id));

        if (is_array($rows) || is_object($rows)) {
            foreach ((array)$rows as $row) {
                if (isset($row->is_official)) {
                    $row->is_official = (int) $row->is_official;
                }
            }
        }

        return new \WP_REST_Response($rows ?: array(), 200);
    }

    /** ✅ Reveal hint : public, trace seulement si connecté */
    public static function reveal_hint($r) {
        global $wpdb;
        $p     = $wpdb->prefix . 'ouin_exo_';
        $exo   = isset($r['id']) ? (int)$r['id'] : 0;
        $order = isset($r['order']) ? (int)$r['order'] : (int)$r->get_param('order');

        $content = $wpdb->get_var($wpdb->prepare(
            "SELECT content FROM {$p}hints WHERE exercise_id = %d AND hint_order = %d",
            $exo, $order
        ));
        if ($content !== null) {
            $content = stripslashes($content);
        }

        if ($content === null) {
            return new \WP_REST_Response(new \WP_Error('not_found', 'Indice introuvable', array('status'=>404)), 404);
        }

        // ✅ trace uniquement si connecté
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$p}user_reveals (user_id, exercise_id, kind, ref)
                 VALUES (%d, %d, 'hint', %s)",
                $uid, $exo, (string) $order
            ));
        }

        return new \WP_REST_Response(array('content' => $content), 200);
    }

    /** ✅ Reveal solution : public, trace seulement si connecté */
    public static function reveal_solution($r) {
        global $wpdb;
        $p   = $wpdb->prefix . 'ouin_exo_';
        $exo = isset($r['id']) ? (int)$r['id'] : 0;
        $sid = isset($r['solution_id']) ? (int)$r['solution_id'] : (int)$r->get_param('solution_id');

        $content = $wpdb->get_var($wpdb->prepare(
            "SELECT content FROM {$p}solutions WHERE id = %d AND exercise_id = %d",
            $sid, $exo
        ));
        if ($content !== null) {
            $content = stripslashes($content);
        }

        if ($content === null) {
            return new \WP_REST_Response(new \WP_Error('not_found', 'Corrigé introuvable', array('status'=>404)), 404);
        }

        // ✅ trace uniquement si connecté
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$p}user_reveals (user_id, exercise_id, kind, ref)
                 VALUES (%d, %d, 'solution', %s)",
                $uid, $exo, (string) $sid
            ));
        }

        return new \WP_REST_Response(array('content' => $content), 200);
    }


    /** Révélations déjà vues (indices + solutions) */
    public static function reveals($r) {
        global $wpdb;
        $p   = $wpdb->prefix . 'ouin_exo_';
        $eid = isset($r['id']) ? (int)$r['id'] : 0;
        $uid = get_current_user_id();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT kind, ref FROM {$p}user_reveals WHERE user_id=%d AND exercise_id=%d",
            $uid, $eid
        ));

        $hintOrders  = array();
        $solutionIds = array();
        foreach ((array)$rows as $row) {
            if ($row->kind === 'hint')     $hintOrders[]  = (int)$row->ref;
            if ($row->kind === 'solution') $solutionIds[] = (int)$row->ref;
        }

        $hints = array();
        if (!empty($hintOrders)) {
            $in = implode(',', array_map('intval', $hintOrders));
            $hints = $wpdb->get_results("
              SELECT hint_order AS `order`, content
              FROM {$p}hints
              WHERE exercise_id = {$eid} AND hint_order IN ($in)
              ORDER BY hint_order ASC
            ");
            foreach ($hints as $h) {
                $h->content = stripslashes($h->content);
            }
        }

        $solutions = array();
        if (!empty($solutionIds)) {
            $in = implode(',', array_map('intval', $solutionIds));
            $solutions = $wpdb->get_results("
              SELECT id, title, is_official, content
              FROM {$p}solutions
              WHERE exercise_id = {$eid} AND id IN ($in)
              ORDER BY is_official DESC, id ASC
            ");
            foreach ($solutions as $s) {
                $s->is_official = (int)$s->is_official;
                $s->content     = stripslashes($s->content);
            }
        }

        return new \WP_REST_Response(array(
            'hints'     => $hints,
            'solutions' => $solutions,
        ), 200);
    }

}
