<?php
namespace Ouinpo\Exercises;

if (!defined('ABSPATH')) exit;

/**
 * Moteur d’attribution automatique des badges
 * - badges de domaine (BO-*)
 * - badges méta (Meta-*)
 *
 * Les "badges de compétence" sont virtuels : on les déduit
 * du nombre d’exos réussis par compétence, pour calculer les domaines et méta.
 */
class BadgeEngine {

    /**
     * Comptes exclus de l’attribution automatique des badges.
     * Ici : user_id 2.
     */
    protected const AUTO_BADGES_DISABLED_USER_IDS = [2];

    /**
     * Cache local des niveaux scolaires courants des utilisateurs.
     */
    protected static array $user_level_label_cache = [];

    protected static array $user_level_id_cache = [];

    /**
     * Point d’entrée : recalculer tous les badges pour un utilisateur.
     */
    public static function recompute_for_user(int $user_id): void {

        if ($user_id <= 0) return;

        // Le user 2 ne gagne jamais de badge automatiquement.
        if (self::auto_badges_disabled_for_user($user_id)) {
            return;
        }

        // 1) Statistiques par compétence + niveaux Bronze / Argent / Or
        $competency_levels = self::compute_competency_levels($user_id);

        if (!$competency_levels) {
            return;
        }

        // 2) Attribution des badges de domaine (BO-*)
        $domain_badges_owned = self::compute_domain_badges($user_id, $competency_levels);

        // 3) Attribution des badges méta (Meta-Seconde / Meta-Première / Meta-Terminale)
        self::compute_meta_badges($user_id, $domain_badges_owned);
    }

    /* ============================================================
     * 1) NIVEAUX PAR COMPÉTENCE (virtuel)
     * ========================================================== */

    /**
     * Retourne un tableau :
     *  [competency_id => ['bronze' => bool, 'argent' => bool, 'or' => bool]]
     */
    protected static function compute_competency_levels(int $user_id): array {
        global $wpdb;
        $t_ex      = $wpdb->prefix . 'ouin_exo_exercises';
        $t_status  = $wpdb->prefix . 'ouin_exo_user_status';
        $t_ex_comp = $wpdb->prefix . 'ouin_exo_exercise_competency';
        $t_exam_meta = $wpdb->prefix . 'ouin_exo_exam_meta';

        // difficulty_id : 1 = débutant, 2 = confirmé, 3 = expert
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    ec.competency_id,
                    COUNT(DISTINCT s.exercise_id) AS total_success,
                    SUM(CASE WHEN e.difficulty_id >= 2 THEN 1 ELSE 0 END) AS success_confirmed_or_more,
                    SUM(CASE WHEN e.difficulty_id = 3 THEN 1 ELSE 0 END) AS success_expert
                 FROM $t_ex_comp ec
                 JOIN $t_status s
                   ON s.exercise_id = ec.exercise_id
                  AND s.user_id = %d
                  AND s.status = 'solved'
                JOIN $t_ex e
                  ON e.id = ec.exercise_id
                 AND e.is_active = 1
                LEFT JOIN $t_exam_meta em
                  ON em.exercise_id = e.id
                WHERE (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')
                GROUP BY ec.competency_id",
                $user_id
            )
        );


        if (!$rows) {
            return [];
        }

        $levels = [];

        foreach ($rows as $r) {
            $cid       = (int) $r->competency_id;
            $total     = (int) $r->total_success;
            $confirmed = (int) $r->success_confirmed_or_more;
            $expert    = (int) $r->success_expert;

            $bronze = $total >= 1;
            $argent = $total >= 3 && $confirmed >= 1;
            $or     = $total >= 5 && $expert >= 1 && $bronze && $argent;

            $levels[$cid] = [
                'bronze' => $bronze,
                'argent' => $argent,
                'or'     => $or,
            ];
        }

        return $levels;
    }

    /* ============================================================
     * 2) BADGES DE DOMAINE
     * ========================================================== */

    protected static function compute_domain_badges(int $user_id, array $competency_levels): array {
        global $wpdb;
        $t_comp        = $wpdb->prefix . 'ouin_exo_competencies';
        $t_ex          = $wpdb->prefix . 'ouin_exo_exercises';
        $t_ex_comp     = $wpdb->prefix . 'ouin_exo_exercise_competency';
        $t_status      = $wpdb->prefix . 'ouin_exo_user_status';
        $t_badges      = $wpdb->prefix . 'ouin_exo_badges';
        $t_user_badges = $wpdb->prefix . 'ouin_exo_user_badges';
        $t_exam_meta   = $wpdb->prefix . 'ouin_exo_exam_meta';
        $t_comp_level  = $wpdb->prefix . 'ouin_exo_competency_school_level';

        // On ne calcule les badges de domaine que sur le niveau courant de l'élève.
        // Cela évite qu'un élève de Terminale gagne des badges de Première,
        // ou inversement, lorsqu'un même domain_slug existe dans plusieurs niveaux.
        $user_level_ids = self::current_level_ids_for_user($user_id);
        
        if (!$user_level_ids) {
            return [];
        }
        
        $level_placeholders = implode(',', array_fill(0, count($user_level_ids), '%d'));

        // 1) Total des compétences par domaine
        $rows_total = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.domain_slug, COUNT(DISTINCT c.id) AS total_comp
                 FROM $t_comp c

                 JOIN $t_comp_level csl

                   ON csl.competency_id = c.id
                 WHERE c.active = 1
                   AND c.domain_slug <> ''
                   AND csl.school_level_id IN ($level_placeholders)
                 GROUP BY c.domain_slug",
                $user_level_ids
            ),
            OBJECT_K
        );

        if (!$rows_total) {
            return [];
        }

        // 2) Statistiques par domaine sur les exercices RÉUSSIS de cet utilisateur
        $rows_ex = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    c.domain_slug,
                    us.exercise_id,
                    MAX(CASE WHEN e.difficulty_id >= 2 THEN 1 ELSE 0 END) AS has_confirmed,
                    MAX(CASE WHEN e.difficulty_id = 3 THEN 1 ELSE 0 END) AS has_expert
                 FROM $t_status us
                 JOIN $t_ex_comp ec
                   ON ec.exercise_id = us.exercise_id
                 JOIN $t_comp c
                   ON c.id = ec.competency_id
                  AND c.active = 1
                  AND c.domain_slug <> ''
                JOIN $t_comp_level csl

                  ON csl.competency_id = c.id

                 AND csl.school_level_id IN ($level_placeholders)
                JOIN $t_ex e
                  ON e.id = us.exercise_id
                 AND e.is_active = 1
                LEFT JOIN $t_exam_meta em
                  ON em.exercise_id = e.id
                WHERE us.user_id = %d
                  AND us.status  = 'solved'
                  AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')
                 GROUP BY c.domain_slug, us.exercise_id",
                array_merge($user_level_ids, [$user_id])
            )
        );

        // 3) Couverture des compétences par domaine
        $rows_cov = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT
                    c.domain_slug,
                    c.id AS competency_id
                 FROM $t_status us
                 JOIN $t_ex_comp ec
                   ON ec.exercise_id = us.exercise_id
                 JOIN $t_comp c
                   ON c.id = ec.competency_id
                  AND c.active = 1
                  AND c.domain_slug <> ''
                 JOIN $t_comp_level csl

                   ON csl.competency_id = c.id

                  AND csl.school_level_id IN ($level_placeholders)
                 JOIN $t_ex e
                   ON e.id = us.exercise_id
                  AND e.is_active = 1
                 LEFT JOIN $t_exam_meta em
                   ON em.exercise_id = e.id
                 WHERE us.user_id = %d
                   AND us.status  = 'solved'
                   AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')",
                array_merge($user_level_ids, [$user_id])
            )
        );

        // 4) Construction des stats par domaine
        $domain_stats = [];

        foreach ($rows_total as $dom => $r) {
            $domain_stats[$dom] = [
                'solved_ex'    => 0,
                'confirmed_ex' => 0,
                'expert_ex'    => 0,
                'covered_comp' => 0,
                'total_comp'   => (int) $r->total_comp,
            ];
        }

        if ($rows_ex) {
            foreach ($rows_ex as $r) {
                $dom = $r->domain_slug;
                if (!isset($domain_stats[$dom])) {
                    $domain_stats[$dom] = [
                        'solved_ex'    => 0,
                        'confirmed_ex' => 0,
                        'expert_ex'    => 0,
                        'covered_comp' => 0,
                        'total_comp'   => 0,
                    ];
                }
                $domain_stats[$dom]['solved_ex']++;

                if ((int) $r->has_confirmed > 0) {
                    $domain_stats[$dom]['confirmed_ex']++;
                }
                if ((int) $r->has_expert > 0) {
                    $domain_stats[$dom]['expert_ex']++;
                }
            }
        }

        if ($rows_cov) {
            $seen = [];
            foreach ($rows_cov as $r) {
                $dom = $r->domain_slug;
                $cid = (int) $r->competency_id;
                $key = $dom . '#' . $cid;

                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                if (!isset($domain_stats[$dom])) {
                    $domain_stats[$dom] = [
                        'solved_ex'    => 0,
                        'confirmed_ex' => 0,
                        'expert_ex'    => 0,
                        'covered_comp' => 0,
                        'total_comp'   => 0,
                    ];
                }
                $domain_stats[$dom]['covered_comp']++;
            }
        }

        // 5) Badges BO-* par domaine
        $badge_rows = $wpdb->get_results(
            "SELECT id, slug, theme
             FROM $t_badges
             WHERE slug LIKE 'bo-%-bronze'
                OR slug LIKE 'bo-%-argent'
                OR slug LIKE 'bo-%-or'"
        );

        $badges_by_domain_tier = [];

        if ($badge_rows) {
            foreach ($badge_rows as $b) {
                $slug = (string) $b->slug;
                $tier = null;

                if (substr($slug, -7) === '-bronze') {
                    $tier = 'bronze';
                } elseif (substr($slug, -7) === '-argent') {
                    $tier = 'argent';
                } elseif (substr($slug, -3) === '-or') {
                    $tier = 'or';
                }
                if (!$tier) continue;

                $dom = $b->theme; // theme = domain_slug
                if (!isset($badges_by_domain_tier[$dom])) {
                    $badges_by_domain_tier[$dom] = [];
                }
                $badges_by_domain_tier[$dom][$tier] = (int) $b->id;
            }
        }

        // 6) Badges déjà possédés
        $owned_rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT badge_id
                 FROM $t_user_badges
                 WHERE user_id = %d",
                $user_id
            )
        );
        $owned = array_map('intval', $owned_rows ?: []);

        $result = [];

        // 7) Application des nouvelles règles par domaine
        foreach ($domain_stats as $dom => $st) {
            $solved    = (int) $st['solved_ex'];
            $confirmed = (int) $st['confirmed_ex'];
            $expert    = (int) $st['expert_ex'];
            $covered   = (int) $st['covered_comp'];
            $totalComp = max(0, (int) $st['total_comp']);

            $tiers = [
                'bronze' => false,
                'argent' => false,
                'or'     => false,
            ];

            if (empty($badges_by_domain_tier[$dom])) {
                $result[$dom] = $tiers;
                continue;
            }

            $dom_badges = $badges_by_domain_tier[$dom];

            // ---- Bronze ----
            $hasBronze = false;
            $coverageOK = ($totalComp > 0) ? (($covered * 100) >= ($totalComp * 25)) : false;

            if (!empty($dom_badges['bronze']) && $solved >= 2 && $coverageOK) {
                $badge_id = $dom_badges['bronze'];
                if (!in_array($badge_id, $owned, true)) {
                    self::award_badge($user_id, $badge_id, 'auto');
                    $owned[] = $badge_id;
                }
                $hasBronze = true;
                $tiers['bronze'] = true;
            } elseif (!empty($dom_badges['bronze']) && in_array($dom_badges['bronze'], $owned, true)) {
                $hasBronze = true;
                $tiers['bronze'] = true;
            }

            // ---- Argent ----
            $hasArgent = false;
            $fullCoverage = ($totalComp > 0) ? (($covered * 100) >= ($totalComp * 75)) : false;

            if (!empty($dom_badges['argent']) && $solved >= 5 && $fullCoverage && $confirmed >= 1) {
                $badge_id = $dom_badges['argent'];
                if (!in_array($badge_id, $owned, true)) {
                    self::award_badge($user_id, $badge_id, 'auto');
                    $owned[] = $badge_id;
                }
                $hasArgent = true;
                $tiers['argent'] = true;
            } elseif (!empty($dom_badges['argent']) && in_array($dom_badges['argent'], $owned, true)) {
                $hasArgent = true;
                $tiers['argent'] = true;
            }

            // ---- Or ----
            if (!empty($dom_badges['or'])) {
                $badge_id = $dom_badges['or'];
            
                // Couverture 100% requise pour l'Or
                $fullCoverageOr = ($totalComp > 0 && $covered >= $totalComp);
            
                if ($hasBronze && $hasArgent && $solved >= 8 && $expert >= 1 && $confirmed >= 2 && $fullCoverageOr) {
                    if (!in_array($badge_id, $owned, true)) {
                        self::award_badge($user_id, $badge_id, 'auto');
                        $owned[] = $badge_id;
                    }
                    $tiers['or'] = true;
                } elseif (in_array($badge_id, $owned, true)) {
                    $tiers['or'] = true;
                }
            }
            $result[$dom] = $tiers;
        }

        return $result;
    }

    /* ============================================================
     * 3) BADGES MÉTA (Meta-Seconde / Meta-Première / Meta-Terminale)
     * ========================================================== */

    protected static function compute_meta_badges(int $user_id, array $domain_badges_owned): void {
        global $wpdb;
        $t_comp        = $wpdb->prefix . 'ouin_exo_competencies';
        $t_badges      = $wpdb->prefix . 'ouin_exo_badges';
        $t_user_badges = $wpdb->prefix . 'ouin_exo_user_badges';

        if (!$domain_badges_owned) return;

        // 1) Cycles <-> niveau BO
        $cycles = [
            'Meta-Seconde'   => 'Seconde',
            'Meta-Première'  => 'Première',
            'Meta-Terminale' => 'Terminale',
        ];

        // Un élève ne peut obtenir que le méta-badge de son niveau courant.
        $user_levels = self::current_level_labels_for_user($user_id);
        if (!$user_levels) {
            return;
        }

        // 2) Tous les domaines existants par cycle (indépendamment de l'élève)
        $domains_all_by_cycle = [];
        foreach ($cycles as $theme => $level) {
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT domain_slug
                     FROM $t_comp
                     WHERE level = %s
                       AND active = 1
                       AND domain_slug <> ''",
                    $level
                )
            );
            $domains_all_by_cycle[$theme] = $rows ?: [];
        }

        // 3) Charger tous les badges méta et les répartir Bronze / Argent / Or
        $meta_rows = $wpdb->get_results(
            "SELECT id, slug, theme
             FROM $t_badges
             WHERE theme IN ('Meta-Seconde','Meta-Première','Meta-Terminale')"
        );

        $meta_by_theme_tier = []; // [theme]['bronze'|'argent'|'or'] = badge_id

        if ($meta_rows) {
            $tmp = [];
            foreach ($meta_rows as $b) {
                $tmp[$b->theme][] = $b;
            }
            foreach ($tmp as $theme => $list) {
                usort($list, fn($a, $b) => $a->id <=> $b->id);
                $tiers = ['bronze', 'argent', 'or'];
                foreach ($list as $i => $b) {
                    if (!isset($tiers[$i])) continue;
                    $meta_by_theme_tier[$theme][$tiers[$i]] = (int) $b->id;
                }
            }
        }

        // Badges déjà possédés par l'utilisateur
        $owned_rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT badge_id FROM $t_user_badges WHERE user_id = %d",
                $user_id
            )
        );
        $owned = array_map('intval', $owned_rows ?: []);

        // 4) Pour chaque méta (Seconde / Première / Terminale)
        foreach ($cycles as $meta_theme => $level_label) {
            
            if (!in_array($level_label, $user_levels, true)) {
                continue;
            }
            
            $all_doms = $domains_all_by_cycle[$meta_theme] ?? [];
            if (!$all_doms) continue;

            // Les méta-badges doivent être calculés sur les domaines attendus
            // du niveau courant, et non sur les seuls domaines déjà touchés
            // par l'élève.
            $doms = array_values(array_unique(array_filter(array_map('strval', $all_doms))));

            if (!$doms) continue;

            $nb_domains = count($doms);

            $count_bronze  = 0;
            $count_argent  = 0;
            $count_or      = 0;
            $all_have_bronze_or_better = true;

            foreach ($doms as $dom) {
                $tiers = $domain_badges_owned[$dom] ?? ['bronze'=>false,'argent'=>false,'or'=>false];

                $has_bronze_or_better = !empty($tiers['bronze']) || !empty($tiers['argent']) || !empty($tiers['or']);
                if ($has_bronze_or_better) {
                    $count_bronze++;
                } else {
                    $all_have_bronze_or_better = false;
                }

                if (!empty($tiers['argent']) || !empty($tiers['or'])) {
                    $count_argent++;
                }
                if (!empty($tiers['or'])) {
                    $count_or++;
                }
            }

            $p_bronze = $count_bronze / $nb_domains;
            $p_argent = $count_argent / $nb_domains;
            $p_or     = $count_or     / $nb_domains;

            if (empty($meta_by_theme_tier[$meta_theme])) continue;
            $meta_ids = $meta_by_theme_tier[$meta_theme];

            // --- Bronze méta ---
            if (!empty($meta_ids['bronze'])) {
                $badge_id = $meta_ids['bronze'];
                if ($p_bronze > 0.20 && !in_array($badge_id, $owned, true)) {
                    self::award_badge($user_id, $badge_id, 'auto');
                    $owned[] = $badge_id;
                }
            }

            // --- Argent méta ---
            if (!empty($meta_ids['argent'])) {
                $badge_id = $meta_ids['argent'];
                if ($p_argent > 0.50 && !in_array($badge_id, $owned, true)) {
                    self::award_badge($user_id, $badge_id, 'auto');
                    $owned[] = $badge_id;
                }
            }

            // --- Or méta ---
            if (!empty($meta_ids['or'])) {
                $badge_id = $meta_ids['or'];
                if ($p_or > 0.80 && $all_have_bronze_or_better && !in_array($badge_id, $owned, true)) {
                    self::award_badge($user_id, $badge_id, 'auto');
                    $owned[] = $badge_id;
                }
            }
        }
    }

    /* ============================================================
     * Helpers
     * ========================================================== */

    public static function can_user_receive_badge(int $user_id, int $badge_id, string $source = 'auto'): bool {
        if ($user_id <= 0 || $badge_id <= 0) {
            return false;
        }

        // Blocage demandé : user 2 exclu des badges automatiques.
        if ($source === 'auto' && self::auto_badges_disabled_for_user($user_id)) {
            return false;
        }

        return self::badge_matches_user_level($user_id, $badge_id);
    }

    protected static function auto_badges_disabled_for_user(int $user_id): bool {
        return in_array($user_id, self::AUTO_BADGES_DISABLED_USER_IDS, true);
    }

    protected static function current_level_ids_for_user(int $user_id): array {

        global $wpdb;

        if ($user_id <= 0) {

            return [];

        }

        if (isset(self::$user_level_id_cache[$user_id])) {

            return self::$user_level_id_cache[$user_id];

        }

        $p = $wpdb->prefix . 'ouin_exo_';

        $year_id = 0;

        if (class_exists(__NAMESPACE__ . '\\Years')) {

            $active_id = Years::active_id();

            $year_id = $active_id ? (int) $active_id : 0;

        }

        if ($year_id <= 0) {

            $year_id = (int) get_option('ouin_exo_active_year_id', 0);

        }

        $sql = "

            SELECT DISTINCT COALESCE(gm.school_level_id_override, g.school_level_id) AS level_id

            FROM {$p}group_members gm

            JOIN {$p}groups g

              ON g.id = gm.group_id

            WHERE gm.user_id = %d

              AND gm.role = 'student'

              AND COALESCE(gm.school_level_id_override, g.school_level_id) IS NOT NULL

        ";

        $params = [$user_id];

        if ($year_id > 0) {

            $sql .= " AND (g.year_id = %d OR g.year_id IS NULL)";

            $params[] = $year_id;

        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, $params))))));

        self::$user_level_id_cache[$user_id] = $ids;

        return $ids;

    }



    protected static function current_level_labels_for_user(int $user_id): array {
        global $wpdb;

        if ($user_id <= 0) {
            return [];
        }

        if (isset(self::$user_level_label_cache[$user_id])) {
            return self::$user_level_label_cache[$user_id];
        }

        $p = $wpdb->prefix . 'ouin_exo_';

        $year_id = 0;
        if (class_exists(__NAMESPACE__ . '\\Years')) {
            $active_id = Years::active_id();
            $year_id = $active_id ? (int) $active_id : 0;
        }

        if ($year_id <= 0) {
            $year_id = (int) get_option('ouin_exo_active_year_id', 0);
        }

        $sql = "
            SELECT DISTINCT sl.label
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

        $rows = $wpdb->get_col($wpdb->prepare($sql, $params));

        $valid = [
            'Seconde'   => true,
            'Première'  => true,
            'Terminale' => true,
        ];

        $levels = [];
        foreach ($rows ?: [] as $label) {
            $label = (string) $label;
            if (isset($valid[$label])) {
                $levels[] = $label;
            }
        }

        $levels = array_values(array_unique($levels));
        self::$user_level_label_cache[$user_id] = $levels;

        return $levels;
    }

    protected static function badge_matches_user_level(int $user_id, int $badge_id): bool {
        global $wpdb;

        $t_badges = $wpdb->prefix . 'ouin_exo_badges';
        $t_comp   = $wpdb->prefix . 'ouin_exo_competencies';
        $t_comp_level = $wpdb->prefix . 'ouin_exo_competency_school_level';

        $badge = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, slug, theme
                 FROM $t_badges
                 WHERE id = %d
                 LIMIT 1",
                $badge_id
            )
        );

        if (!$badge) {
            return false;
        }

        $slug  = trim((string) $badge->slug);
        $theme = trim((string) $badge->theme);

        // Badges spéciaux : autorisés.
        if ($theme === 'special' || str_starts_with($slug, 'special-')) {
            return true;
        }

        if ($theme === '') {
            return false;
        }

        // Badges transversaux : autorisés pour tous les niveaux.
        if (self::badge_theme_is_transversal($theme)) {
            return true;
        }

        $user_level_ids = self::current_level_ids_for_user($user_id);
        if (!$user_level_ids) {
            return false;
        }

        $user_levels = self::current_level_labels_for_user($user_id);



        // Méta-badges : uniquement le méta-badge du niveau courant.
        $meta_levels = [
            'Meta-Seconde'   => 'Seconde',
            'Meta-Première'  => 'Première',
            'Meta-Terminale' => 'Terminale',
        ];

        if (isset($meta_levels[$theme])) {
            return in_array($meta_levels[$theme], $user_levels, true);
        }

        // Badges de domaine BO : le domaine doit exister dans le niveau courant.
        $placeholders = implode(',', array_fill(0, count($user_level_ids), '%d'));
        $params = array_merge([$theme], $user_level_ids);

        $allowed = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1
                 FROM $t_comp c

                 JOIN $t_comp_level csl

                   ON csl.competency_id = c.id
                 WHERE c.active = 1
                   AND c.domain_slug = %s
                   AND csl.school_level_id IN ($placeholders)
                 LIMIT 1",
                $params
            )
        );

        return (bool) $allowed;
    }

    protected static function badge_theme_is_transversal(string $theme): bool {
        global $wpdb;

        if ($theme === 'Transversal' || stripos($theme, 'transversal') !== false) {
            return true;
        }

        $t_comp = $wpdb->prefix . 'ouin_exo_competencies';

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1
                 FROM $t_comp
                 WHERE active = 1
                   AND domain_slug = %s
                   AND level = 'Transversal'
                 LIMIT 1",
                $theme
            )
        );

        return (bool) $exists;
    }

    protected static function award_badge(int $user_id, int $badge_id, string $source = 'auto'): void {
        global $wpdb;
        $t_user_badges = $wpdb->prefix . 'ouin_exo_user_badges';

        if (!self::can_user_receive_badge($user_id, $badge_id, $source)) {
            return;
        }

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM $t_user_badges WHERE user_id = %d AND badge_id = %d LIMIT 1",
                $user_id,
                $badge_id
            )
        );
        if ($exists) return;

        $wpdb->insert(
            $t_user_badges,
            [
                'user_id'    => $user_id,
                'badge_id'   => $badge_id,
                'awarded_at' => current_time('mysql'),
                'source'     => $source,
            ],
            ['%d','%d','%s','%s']
        );

        $pending = get_user_meta($user_id, 'ouinpo_new_badges', true);
        if (!is_array($pending)) {
            $pending = [];
        }

        $pending[] = [
            'badge_id'   => (int) $badge_id,
            'awarded_at' => current_time('mysql'),
            'source'     => $source,
        ];

        update_user_meta($user_id, 'ouinpo_new_badges', $pending);

        do_action('ouinpo_exo_badge_awarded', $user_id, $badge_id, $source);
    }
    
}
