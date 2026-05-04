<?php

namespace Ouinpo\Exercises\Admin;



if (!defined('ABSPATH')) exit;



/**

 * Écran admin "Suivi des compétences BO"

 * - Mode "student" : suivi des compétences élèves

 * - Mode "course"  : compétences vues en cours (teaching_state)

 * - Filtres : Année / Groupe / Domaine / Élève

 * - Vue : détail (ligne par compétence) ou domaine (agrégée)

 * - JS: public/admin-competencies.js

 */

class Screen_Competencies {



  public static function render() {

    if (!current_user_can('edit_users')) {

      wp_die(__('Accès refusé', 'ouinpo'));

    }



    global $wpdb;

    $tblYears   = $wpdb->prefix . 'ouin_exo_academic_years';

    $tblGroups  = $wpdb->prefix . 'ouin_exo_groups';

    $tblComps   = $wpdb->prefix . 'ouin_exo_competencies';

    $tblUC      = $wpdb->prefix . 'ouin_exo_user_competencies';

    $tblLevels  = $wpdb->prefix . 'ouin_exo_school_levels';

    $tblGM      = $wpdb->prefix . 'ouin_exo_group_members';

    $tblU       = $wpdb->users;



    $years  = $wpdb->get_results("SELECT id, slug AS label, is_active FROM $tblYears ORDER BY starts_on DESC");

    $groups = $wpdb->get_results("SELECT id, label FROM $tblGroups ORDER BY label ASC");



    // Filtres GET

    $year_id  = isset($_GET['year_id'])  ? (int) $_GET['year_id']  : 0;

    $group_id = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;

    $domain   = isset($_GET['domain'])   ? sanitize_text_field($_GET['domain']) : '';

    $user_id  = isset($_GET['user_id'])  ? (int) $_GET['user_id']  : 0;

    $view     = isset($_GET['view'])     ? sanitize_text_field($_GET['view']) : 'detail'; // detail|domain

    $mode     = isset($_GET['mode'])     ? sanitize_key($_GET['mode']) : 'student';       // student|course



    if (!in_array($mode, ['student', 'course'], true)) {

      $mode = 'student';

    }



    if (!in_array($view, ['detail', 'domain'], true)) {

      $view = 'detail';

    }



    // ====== Construction de la liste "Domaine" ======

    // Valeur envoyée = domain_slug

    // Libellé affiché = domain

    $domains = [];



    if ($group_id > 0 && $year_id > 0) {

      $lvlSlug = $wpdb->get_var($wpdb->prepare(

        "SELECT sl.slug

           FROM $tblGroups g

           JOIN $tblLevels sl ON sl.id = g.school_level_id

          WHERE g.id = %d AND g.year_id = %d

          LIMIT 1",

        $group_id, $year_id

      ));



      $levelLabel = null;

      if ($lvlSlug) {

        $s = strtolower($lvlSlug);

        if ($s === 'seconde') $levelLabel = 'Seconde';

        elseif ($s === 'premiere') $levelLabel = 'Première';

        elseif ($s === 'terminale' || $s === 'term') $levelLabel = 'Terminale';

      }



      if ($levelLabel) {

        $domains = $wpdb->get_results($wpdb->prepare(

          "SELECT c.domain_slug, c.domain

             FROM $tblUC uc

             JOIN $tblComps c ON c.id = uc.competency_id

            WHERE uc.year_id = %d

              AND uc.group_id = %d

              AND (c.level = %s OR c.level = 'Transversal')

            GROUP BY c.domain_slug, c.domain

            ORDER BY

              MIN(CASE

                WHEN c.track = 'NSI' THEN 1

                WHEN c.track = 'SNT' THEN 2

                WHEN c.track = 'Transversal' THEN 3

                ELSE 4

              END),

              c.domain",

          $year_id, $group_id, $levelLabel

        ));

      }

    }



    if (empty($domains)) {

      $domains = $wpdb->get_results(

        "SELECT domain_slug, domain

           FROM $tblComps

          GROUP BY domain_slug, domain

          ORDER BY

            MIN(CASE

              WHEN track = 'NSI' THEN 1

              WHEN track = 'SNT' THEN 2

              WHEN track = 'Transversal' THEN 3

              ELSE 4

            END),

            domain"

      );

    }



    // ====== Construction de la liste d'élèves (select) ======

    $students = [];

    if ($mode === 'student') {

      if ($year_id > 0 && $group_id > 0) {

        $students = $wpdb->get_results($wpdb->prepare(

          "SELECT DISTINCT u.ID AS id, u.display_name AS name

             FROM $tblGM gm

             JOIN $tblGroups g ON g.id = gm.group_id

             JOIN $tblU u ON u.ID = gm.user_id

            WHERE g.year_id = %d

              AND gm.group_id = %d

            ORDER BY u.display_name ASC, u.ID ASC",

          $year_id, $group_id

        ));

      } elseif ($year_id > 0) {

        $students = $wpdb->get_results($wpdb->prepare(

          "SELECT DISTINCT u.ID AS id, u.display_name AS name

             FROM $tblGM gm

             JOIN $tblGroups g ON g.id = gm.group_id

             JOIN $tblU u ON u.ID = gm.user_id

            WHERE g.year_id = %d

            ORDER BY u.display_name ASC, u.ID ASC",

          $year_id

        ));

      }

    }



// Assets

$plugin_base_url = plugins_url('', dirname(__DIR__, 2) . '/ouinpo-exercices.php');

$asset_version = defined('OUINPO_SUITE_VERSION')
    ? OUINPO_SUITE_VERSION
    : '0.4.0';

$css_rel = 'assets/css/admin/teacher-competencies.css';
$css_file = defined('OUINPO_SUITE_DIR')
    ? OUINPO_SUITE_DIR . $css_rel
    : '';

wp_enqueue_style(
    'ouinpo-teacher-competencies-admin',
    defined('OUINPO_SUITE_URL')
        ? OUINPO_SUITE_URL . $css_rel
        : $plugin_base_url . '/public/assets/css/teacher-competencies.css',
    [],
    ($css_file !== '' && file_exists($css_file))
        ? (string) filemtime($css_file)
        : $asset_version
);

$js_rel = 'assets/js/admin/admin-competencies.js';
$js_dir = defined('OUINPO_SUITE_DIR')
    ? OUINPO_SUITE_DIR
    : dirname(__DIR__, 6);
$js_url = defined('OUINPO_SUITE_URL')
    ? OUINPO_SUITE_URL
    : plugin_dir_url($js_dir . '/ouinpo-suite.php');
$js_file = $js_dir . $js_rel;

wp_enqueue_script(
    'ouinpo-competencies',
    $js_url . $js_rel,
    ['jquery'],
    file_exists($js_file)
        ? (string) filemtime($js_file)
        : $asset_version,
    true
);

$nonce = wp_create_nonce('wp_rest');

wp_localize_script(
    'ouinpo-competencies',
    'OUINEXO',
    [
        'api'   => esc_url_raw(rest_url('ouinpo/v1')),
        'nonce' => $nonce,
    ]
);



    echo '<div class="wrap">';

    echo '<h1>Suivi des compétences BO</h1>';



        echo '<div class="card ouinpo-admin-card-padded">';

    echo '<form method="get" action="">';

    echo '<input type="hidden" name="page" value="ouinpo-competencies"/>';



    echo '<table class="form-table">';



    // Ligne 0 : Mode

    echo '<tr>';

    echo '<th scope="row"><label>Mode</label></th><td colspan="3">';

    echo '<label><input type="radio" name="mode" value="student" ' . checked($mode, 'student', false) . '> Suivi élèves</label> &nbsp;&nbsp;';

    echo '<label><input type="radio" name="mode" value="course" ' . checked($mode, 'course', false) . '> Compétences vues en cours</label>';

    echo '</td>';

    echo '</tr>';



    // Ligne 1 : Année + Classe

    echo '<tr>';

    echo '<th scope="row"><label for="filter-year">Année</label></th><td>';

    echo '<select id="filter-year" name="year_id"><option value="0">— Choisir —</option>';

    foreach ($years as $y) {

      printf(

        '<option value="%d"%s>%s%s</option>',

        (int)$y->id,

        selected($year_id, $y->id, false),

        esc_html($y->label),

        $y->is_active ? ' (active)' : ''

      );

    }

    echo '</select></td>';



    echo '<th scope="row"><label for="filter-group">Classe (groupe)</label></th><td>';

    echo '<select id="filter-group" name="group_id"><option value="0">— Toutes —</option>';

    foreach ($groups as $g) {

      printf(

        '<option value="%d"%s>%s</option>',

        (int)$g->id,

        selected($group_id, $g->id, false),

        esc_html($g->label)

      );

    }

    echo '</select></td>';

    echo '</tr>';



    // Ligne 2 : Domaine + Vue

    echo '<tr>';

    echo '<th scope="row"><label for="filter-domain">Domaine</label></th><td>';

    echo '<select id="filter-domain" name="domain"><option value="">— Tous —</option>';

    foreach ($domains as $d) {

      printf(

        '<option value="%s"%s>%s</option>',

        esc_attr($d->domain_slug),

        selected($domain, $d->domain_slug, false),

        esc_html($d->domain)

      );

    }

    echo '</select></td>';



    echo '<th scope="row"><label>Vue</label></th><td>';

    if ($mode === 'student') {

      echo '<label><input type="radio" name="view" value="detail" ' . checked($view, 'detail', false) . '> Détail</label> &nbsp;&nbsp;';

      echo '<label><input type="radio" name="view" value="domain" ' . checked($view, 'domain', false) . '> Par domaine</label>';

    } else {

      echo '<input type="hidden" name="view" value="detail">';

      echo '<span class="description">Mode cours : une ligne par compétence.</span>';

    }

    echo '</td>';

    echo '</tr>';



    // Ligne 3 : Élève ou info "cours"

    echo '<tr>';

    if ($mode === 'student') {

      echo '<th scope="row"><label for="filter-user">Élève</label></th><td>';

      echo '<select id="filter-user" name="user_id">';

      echo '<option value="0">— Tous —</option>';

      if (!empty($students)) {

        foreach ($students as $s) {

          printf(

            '<option value="%d"%s>%s (ID %d)</option>',

            (int)$s->id,

            selected($user_id, $s->id, false),

            esc_html($s->name),

            (int)$s->id

          );

        }

      }

      echo '</select>';

      echo '</td>';

      echo '<td colspan="2"><button class="button button-primary">Appliquer</button></td>';

    } else {

      echo '<th scope="row">État du cours</th><td>';

      echo '<input type="hidden" name="user_id" value="0">';

      echo '<span class="description">Tu pourras marquer chaque compétence comme <strong>Pas encore vue</strong> ou <strong>Vue</strong>.</span>';

      echo '</td>';

      echo '<td colspan="2"><button class="button button-primary">Appliquer</button></td>';

    }

    echo '</tr>';



    echo '</table>';

    echo '</form>';

    echo '</div>';



    if ($mode === 'course') {

        echo '<p class="description ouinpo-admin-description-spaced">';

      echo 'Ce mode servira à indiquer, pour une classe donnée, quelles compétences ont déjà été vues en cours.';

      echo '</p>';

    }



    printf(

      '<div id="ouinpo-competencies-app"

             data-year="%d"

             data-group="%d"

             data-domain="%s"

             data-user="%d"

             data-view="%s"

             data-mode="%s"

             data-nonce="%s"><p>Chargement…</p></div>',

      (int)$year_id,

      (int)$group_id,

      esc_attr($domain),

      (int)$user_id,

      esc_attr($view),

      esc_attr($mode),

      esc_attr($nonce)

    );



    echo '</div>'; // .wrap

  }

}
