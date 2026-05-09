<?php

namespace Ouinpo\Exercises;



if (!defined('ABSPATH')) exit;



final class Shortcodes {



  public static function init() {

    // Deux alias par shortcode (underscore & hyphen)

    add_shortcode('ouinpo_exercises', [__CLASS__, 'render_list']);

    add_shortcode('ouinpo-exercises', [__CLASS__, 'render_list']);



    add_shortcode('ouinpo_exercise',  [__CLASS__, 'render_single']);

    add_shortcode('ouinpo-exercise',  [__CLASS__, 'render_single']);



    // ✅ Progression des compétences BO (élève connecté)

    add_shortcode('ouinpo_competences_progress', [__CLASS__, 'render_progress']);



    // ✅ Interface front "prof" (visualisation)

    add_shortcode('ouinpo_competences_prof', [__CLASS__, 'render_teacher']);



    // ✅ Page "Mes badges" (élève connecté)

    add_shortcode('ouinpo_student_badges', [__CLASS__, 'render_student_badges']);



    // ✅ Palmarès public des badges (filtré par année scolaire)

    // Usage : [ouinpo_badges_palmares] ou [ouinpo_badges_palmares year="2025-2026"]

    add_shortcode('ouinpo_badges_palmares', [__CLASS__, 'render_badges_palmares']);

    

    // ✅  Bandeau de révision

    add_shortcode('ouinpo_revision_band', [__CLASS__, 'render_revision_band']);

    add_shortcode('ouinpo-revision-band', [__CLASS__, 'render_revision_band']);

    

    // ✅ Carte du site dynamique

    add_shortcode('ouinpo_site_map', [__CLASS__, 'render_site_map']);

    add_shortcode('ouinpo-site-map', [__CLASS__, 'render_site_map']);

    

    // ✅ Sujets d'épreuve pratique

    add_shortcode('ouinpo_practical_subjects', [__CLASS__, 'render_practical_subjects']);

    add_shortcode('ouinpo-practical-subjects', [__CLASS__, 'render_practical_subjects']);

    

    add_shortcode('ouinpo_practical_subject', [__CLASS__, 'render_practical_subject']);

    add_shortcode('ouinpo-practical-subject', [__CLASS__, 'render_practical_subject']); 

    

  }

private static function page_url_or_fallback(string $slug, string $fallback): string
{
    $page = get_page_by_path($slug);

    if ($page instanceof \WP_Post) {
        return get_permalink($page);
    }

    return home_url($fallback);
}

private static function render_ai_notice(string $context = 'exercise'): string {

  $url = esc_url(home_url('/donnees-personnelles-ia-et-usages-pedagogiques-sur-ouinpo/'));



  if ($context === 'chat') {

    return '

      <div class="ouinpo-ia-notice ouinpo-ia-notice--compact" role="note" aria-label="Information sur l’usage de l’intelligence artificielle">

        <p>

          <strong>IA pédagogique</strong> — N’écris pas de données personnelles.

          <a href="' . $url . '">En savoir plus</a>

        </p>

      </div>';

  }



  if ($context === 'practical') {

    return '

      <div class="ouinpo-ia-notice" role="note" aria-label="Information sur l’usage de l’intelligence artificielle">

        <p>

          <strong>Information importante.</strong>

          Les réponses aux appels évaluateurs de ce sujet pratique peuvent être analysées par une aide pédagogique fondée sur l’intelligence artificielle.

          N’écris pas de données personnelles dans tes réponses

          (nom complet, adresse, téléphone, mail, informations privées ou concernant un autre élève).

          L’IA aide à progresser, mais elle ne remplace pas le professeur.

          <a href="' . $url . '">En savoir plus</a>

        </p>

      </div>';

  }



  return '

    <div class="ouinpo-ia-notice" role="note" aria-label="Information sur l’usage de l’intelligence artificielle">

      <p>

        <strong>Information importante.</strong>

        Cet exercice utilise une aide pédagogique fondée sur l’intelligence artificielle pour analyser certaines réponses.

        N’écris pas de données personnelles dans tes réponses

        (nom complet, adresse, téléphone, mail, informations privées ou concernant un autre élève).

        L’IA aide à progresser, mais elle ne remplace pas le professeur.

        <a href="' . $url . '">En savoir plus</a>

      </p>

    </div>';

}

  private static function sitemap_normalize_text($text) {

    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $text = wp_strip_all_tags($text);

    $text = str_replace(['’', '`', '´'], "'", $text);

    $text = remove_accents($text);

    $text = preg_replace('/\s+/', ' ', $text);

    return mb_strtolower(trim((string) $text), 'UTF-8');

  }



  private static function sitemap_parse_titles($csv) {

    $items = array_map('trim', explode(',', (string) $csv));

    $items = array_filter($items, static fn($v) => $v !== '');

    return array_map([__CLASS__, 'sitemap_normalize_text'], $items);

  }



  private static function sitemap_parse_slugs($csv) {

    $items = array_map('trim', explode(',', (string) $csv));

    $items = array_filter($items, static fn($v) => $v !== '');

    return array_map('sanitize_title', $items);

  }



  private static function sitemap_is_excluded_entry($title, $slug, array $excluded_titles, array $excluded_slugs) {

    $title_norm = self::sitemap_normalize_text($title);

    $slug_norm  = sanitize_title((string) $slug);



    if ($title_norm !== '' && in_array($title_norm, $excluded_titles, true)) {

      return true;

    }



    if ($slug_norm !== '' && in_array($slug_norm, $excluded_slugs, true)) {

      return true;

    }



    return false;

  }



  private static function sitemap_get_menu_items($menu_identifier) {

    if (!$menu_identifier) {

      return [];

    }



    $menu = wp_get_nav_menu_object($menu_identifier);

    if (!$menu) {

      return [];

    }



    $items = wp_get_nav_menu_items($menu->term_id, [

      'update_post_term_cache' => false,

    ]);



    return is_array($items) ? $items : [];

  }



  private static function sitemap_build_menu_tree(array $items) {

    $tree = [];



    foreach ($items as $item) {

      $parent = (int) $item->menu_item_parent;

      if (!isset($tree[$parent])) {

        $tree[$parent] = [];

      }

      $tree[$parent][] = $item;

    }



    return $tree;

  }



private static function sitemap_find_cards_root(array $tree, array $excluded_titles = [], array $excluded_slugs = []) {

  $roots = $tree[0] ?? [];

  if (empty($roots)) {

    return 0;

  }



  $filtered = [];

  foreach ($roots as $item) {

    $title = trim(wp_strip_all_tags($item->title));

    $url   = !empty($item->url) ? $item->url : '#';



    $path = (string) wp_parse_url($url, PHP_URL_PATH);

    $path = trim($path, '/');

    $slug = $path !== '' ? basename($path) : '';



    if (self::sitemap_is_excluded_entry($title, $slug, $excluded_titles, $excluded_slugs)) {

      continue;

    }



    $filtered[] = $item;

  }



  $with_children = [];

  foreach ($filtered as $item) {

    if (!empty($tree[(int) $item->ID])) {

      $with_children[] = $item;

    }

  }



  if (count($with_children) === 1) {

    return (int) $with_children[0]->ID;

  }



  return 0;

}



  private static function sitemap_render_menu_branch(array $tree, $parent = 0, array $excluded_titles = [], array $excluded_slugs = []) {

    $parent = (int) $parent;



    if (empty($tree[$parent])) {

      return '';

    }



    $html = '<ul class="ouinpo-sitemap-list">';



    foreach ($tree[$parent] as $item) {

      $title = trim(wp_strip_all_tags($item->title));

      $url   = !empty($item->url) ? $item->url : '#';



      $path = (string) wp_parse_url($url, PHP_URL_PATH);

      $path = trim($path, '/');

      $slug = $path !== '' ? basename($path) : '';



      if (self::sitemap_is_excluded_entry($title, $slug, $excluded_titles, $excluded_slugs)) {

        continue;

      }



      $html .= '<li>';

      if (!empty($item->url)) {

        $html .= '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>';

      } else {

        $html .= esc_html($title);

      }

      $html .= self::sitemap_render_menu_branch($tree, (int) $item->ID, $excluded_titles, $excluded_slugs);

      $html .= '</li>';

    }



    $html .= '</ul>';



    return $html;

  }



  private static function sitemap_render_table_children(array $tree, $parent = 0, array $excluded_titles = [], array $excluded_slugs = []) {

    $parent = (int) $parent;



    if (empty($tree[$parent])) {

      return '<span class="ouinpo-sitemap-table__empty">—</span>';

    }



    $html = '<ul class="ouinpo-sitemap-course-list">';



    foreach ($tree[$parent] as $item) {

      $title = trim(wp_strip_all_tags($item->title));

      $url   = !empty($item->url) ? $item->url : '#';



      $path = (string) wp_parse_url($url, PHP_URL_PATH);

      $path = trim($path, '/');

      $slug = $path !== '' ? basename($path) : '';



      if (self::sitemap_is_excluded_entry($title, $slug, $excluded_titles, $excluded_slugs)) {

        continue;

      }



      $html .= '<li>';

      if (!empty($item->url)) {

        $html .= '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>';

      } else {

        $html .= '<span>' . esc_html($title) . '</span>';

      }



      $children_html = self::sitemap_render_table_children($tree, (int) $item->ID, $excluded_titles, $excluded_slugs);

      if ($children_html !== '<span class="ouinpo-sitemap-table__empty">—</span>') {

        $html .= $children_html;

      }



      $html .= '</li>';

    }



    $html .= '</ul>';



    return $html;

  }



  private static function sitemap_render_menu_table(array $tree, array $excluded_titles = [], array $excluded_slugs = [], $label_col = 'Thème', $items_col = 'Cours') {

    if (empty($tree[0])) {

      return '';

    }



    $rows = '';



    foreach ($tree[0] as $item) {

      $title = trim(wp_strip_all_tags($item->title));

      $url   = !empty($item->url) ? $item->url : '#';



      $path = (string) wp_parse_url($url, PHP_URL_PATH);

      $path = trim($path, '/');

      $slug = $path !== '' ? basename($path) : '';



      if (self::sitemap_is_excluded_entry($title, $slug, $excluded_titles, $excluded_slugs)) {

        continue;

      }



      $label_html = !empty($item->url)

        ? '<a class="ouinpo-sitemap-table__theme-link" href="' . esc_url($url) . '"><strong>' . esc_html($title) . '</strong></a>'

        : '<strong>' . esc_html($title) . '</strong>';



      $children_html = self::sitemap_render_table_children($tree, (int) $item->ID, $excluded_titles, $excluded_slugs);

      $count = !empty($tree[(int) $item->ID]) ? count($tree[(int) $item->ID]) : 0;

      $meta  = $count > 0 ? '<div class="ouinpo-sitemap-table__meta">' . esc_html(sprintf(_n('%d ressource', '%d ressources', $count, 'ouinpo'), $count)) . '</div>' : '';



      $rows .= '<tr>';

      $rows .= '<th scope="row"><div class="ouinpo-sitemap-table__theme">' . $label_html . $meta . '</div></th>';

      $rows .= '<td>' . $children_html . '</td>';

      $rows .= '</tr>';

    }



    if ($rows === '') {

      return '';

    }



    $html = '<div class="ouinpo-sitemap-table-wrap">';

    $html .= '<table class="ouinpo-sitemap-table">';

    $html .= '<thead><tr>';

    $html .= '<th scope="col">' . esc_html($label_col) . '</th>';

    $html .= '<th scope="col">' . esc_html($items_col) . '</th>';

    $html .= '</tr></thead>';

    $html .= '<tbody>' . $rows . '</tbody>';

    $html .= '</table>';

    $html .= '</div>';



    return $html;

  }



  private static function sitemap_render_named_menu_section($section_title, $menu_identifier, array $excluded_titles = [], array $excluded_slugs = [], array $options = []) {

    $items = self::sitemap_get_menu_items($menu_identifier);



    if (empty($items)) {

      return '';

    }



$options = wp_parse_args($options, [

  'layout'          => 'list',

  'show_title'      => true,

  'cards'           => false,

  'table_label_col' => 'Thème',

  'table_items_col' => 'Cours publiés sur OuInPo',

]);



$tree = self::sitemap_build_menu_tree($items);

$start_parent = 0;



if (!empty($options['cards']) && $options['layout'] === 'list') {

  $start_parent = self::sitemap_find_cards_root($tree, $excluded_titles, $excluded_slugs);

}



$html = '<section class="ouinpo-sitemap-block ouinpo-sitemap-block--' . esc_attr($options['layout']) . '">';

if (!empty($options['show_title'])) {

  $html .= '<h2>' . esc_html($section_title) . '</h2>';

}



if ($options['layout'] === 'table') {

  $html .= self::sitemap_render_menu_table($tree, $excluded_titles, $excluded_slugs, $options['table_label_col'], $options['table_items_col']);

} else {

  $html .= self::sitemap_render_menu_branch($tree, $start_parent, $excluded_titles, $excluded_slugs);

}



    $html .= '</section>';



    return $html;

  }



  public static function render_site_map($atts = [], $content = '') {

      wp_enqueue_style('ouinpo-exo-css');

      

    $atts = shortcode_atts([

      'main_menu'        => 'principal',

      'seconde_menu'     => 'seconde',

      'premiere_menu'    => 'premiere',

      'terminale_menu'   => 'terminale',

      'histoire_menu'    => 'histoire',

      'outils_menu'      => 'outils',

      'exclude_slugs'    => 'carte-du-site,a-propos-de-l-ouinpo,privacy-policy,quizz-premiere,quizz-tale,recherche-textuelle,simulation-recherche-textuelle,tests-diagnostiques,pr-archibald-bogue,manifeste-de-l-ouinpo,badges-2,compte,console,deconnexion,exercice,reset-password,reinitialisation-du-mot-de-passe,utilisateur-utilisatrice,members-2,register,login,mot-de-passe-oublie,merci-inscription',

      'exclude_titles'   => "À propos de l’ OUINPO,Badges 2,Compte,Console,Déconnexion,Exercice,MANIFESTE DE L’OuInPo,MANIFESTE DE L'OuInPo,Mentions légales,Mentions Légales Potentielles,Pr Archibald Bogue,Quizz 1ere,Quizz Tale,Réinitialisation du mot de passe,Simulation : Recherche textuelle,Tests diagnostiques,Utilisateur/utilisatrice",

      'cache_minutes'    => '10',

      'only'             => '',

      'layout'           => 'list',

      'show_intro'       => '1',

      'show_title'       => '1',

      'cards'            => '0',

      'add_exclude_slugs'  => '',

      'add_exclude_titles' => '',

      'table_label_col'  => 'Thème',

      'table_items_col'  => 'Cours publiés sur OuInPo',

    ], $atts, 'ouinpo_site_map');



    $excluded_slugs = array_values(array_unique(array_merge(

      self::sitemap_parse_slugs($atts['exclude_slugs']),

      self::sitemap_parse_slugs($atts['add_exclude_slugs'])

    )));

    

    $excluded_titles = array_values(array_unique(array_merge(

      self::sitemap_parse_titles($atts['exclude_titles']),

      self::sitemap_parse_titles($atts['add_exclude_titles'])

    )));

    $only            = self::sitemap_parse_slugs($atts['only']);

    $layout          = in_array($atts['layout'], ['list', 'table'], true) ? $atts['layout'] : 'list';

    $show_intro      = $atts['show_intro'] !== '0';

    $show_title      = $atts['show_title'] !== '0';

    $cards           = $atts['cards'] === '1';



    $cache_key = 'ouinpo_site_map_menus_only_' . md5(wp_json_encode([

      'main_menu'        => $atts['main_menu'],

      'seconde_menu'     => $atts['seconde_menu'],

      'premiere_menu'    => $atts['premiere_menu'],

      'terminale_menu'   => $atts['terminale_menu'],

      'histoire_menu'    => $atts['histoire_menu'],

      'outils_menu'      => $atts['outils_menu'],

      'exclude_slugs'    => $excluded_slugs,

      'exclude_titles'   => $excluded_titles,

      'only'             => $only,

      'layout'           => $layout,

      'show_intro'       => $show_intro,

      'show_title'       => $show_title,

      'cards'            => $cards,

      'table_label_col'  => $atts['table_label_col'],

      'table_items_col'  => $atts['table_items_col'],

    ]));



    $use_cache = !current_user_can('edit_pages');

    if ($use_cache) {

      $cached = get_transient($cache_key);

      if ($cached !== false) {

        return $cached;

      }

    }



    $available_sections = [

      'main' => [

        'title' => 'Navigation principale',

        'menu'  => $atts['main_menu'],

      ],

      'seconde' => [

        'title' => 'Seconde — SNT',

        'menu'  => $atts['seconde_menu'],

      ],

      'premiere' => [

        'title' => 'Première — NSI',

        'menu'  => $atts['premiere_menu'],

      ],

      'terminale' => [

        'title' => 'Terminale — NSI',

        'menu'  => $atts['terminale_menu'],

      ],

      'histoire' => [

        'title' => 'Histoire de l’informatique',

        'menu'  => $atts['histoire_menu'],

      ],

      'outils' => [

        'title' => 'Boîte à outils / Exercices',

        'menu'  => $atts['outils_menu'],

      ],

    ];



    if (!empty($only)) {

      $available_sections = array_intersect_key($available_sections, array_flip($only));

    }



    $sections = [];

    foreach ($available_sections as $section) {

      $section_html = self::sitemap_render_named_menu_section(

        $section['title'],

        $section['menu'],

        $excluded_titles,

        $excluded_slugs,

        [

          'layout'          => $layout,

          'show_title'      => $show_title,

          'cards'           => $cards,

          'table_label_col' => $atts['table_label_col'],

          'table_items_col' => $atts['table_items_col'],

        ]

      );



      if ($section_html !== '') {

        $sections[] = $section_html;

      }

    }



    if (empty($sections)) {

      return '';

    }



    $classes = ['ouinpo-site-map', 'ouinpo-site-map--' . $layout];

    if ($cards) {

      $classes[] = 'ouinpo-site-map--cards';

    }

    if (!$show_intro) {

      $classes[] = 'ouinpo-site-map--embedded';

    }

    if (count($sections) === 1) {

      $classes[] = 'ouinpo-site-map--single';

    }



    $html = '<div class="' . esc_attr(implode(' ', $classes)) . '">';



    if ($show_intro) {

      $html .= '<div class="alert">';

      $html .= '<strong>🧭 Carte du site dynamique</strong><br>';

      $html .= 'Cette carte est générée à partir des menus WordPress du site, sans utiliser les sommaires des pages.';

      $html .= '</div>';

    }



    $html .= '<div class="ouinpo-sitemap-grid">';

    foreach ($sections as $section_html) {

      $html .= $section_html;

    }

    $html .= '</div>';



    $html .= '</div>';



    if ($use_cache) {

      set_transient($cache_key, $html, max(1, (int) $atts['cache_minutes']) * MINUTE_IN_SECONDS);

    }



    return $html;

  }



private static function current_student_school_level_id(): int {

  if (!is_user_logged_in()) {

    return 0;

  }



  // Les comptes prof/admin gardent une vue libre : pas de restriction ni défaut imposé.

  if (current_user_can('edit_posts') || current_user_can('edit_users')) {

    return 0;

  }



  if (!class_exists(__NAMESPACE__ . '\\Years')) {

    return 0;

  }



  $year_id = (int) Years::active_id();

  if ($year_id <= 0) {

    return 0;

  }



  global $wpdb;

  $p = $wpdb->prefix . 'ouin_exo_';

  $tbl_gm = $p . 'group_members';

  $tbl_g  = $p . 'groups';



  $gm_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl_gm));

  $g_exists  = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl_g));

  if (!$gm_exists || !$g_exists) {

    return 0;

  }



  $gm_cols = $wpdb->get_col("SHOW COLUMNS FROM {$tbl_gm}");

  $has_override = in_array('school_level_id_override', (array) $gm_cols, true);

  $level_expr = $has_override

    ? 'COALESCE(gm.school_level_id_override, g.school_level_id)'

    : 'g.school_level_id';



  $level_id = (int) $wpdb->get_var($wpdb->prepare(

    "SELECT {$level_expr} AS level_id

       FROM {$tbl_gm} gm

       JOIN {$tbl_g} g ON g.id = gm.group_id

      WHERE gm.user_id = %d

        AND g.year_id = %d

        AND {$level_expr} IN (1,2,3)

      ORDER BY level_id DESC

      LIMIT 1",

    get_current_user_id(),

    $year_id

  ));



  return in_array($level_id, [1, 2, 3], true) ? $level_id : 0;

}



private static function exercise_level_options_for_current_user(): array {

  $all = [

    'seconde'   => 'Seconde',

    'premiere'  => 'Première',

    'terminale' => 'Terminale',

  ];



  $level_id = self::current_student_school_level_id();



  if ($level_id === 1) {

    return ['seconde' => 'Seconde'];

  }



  if ($level_id === 2) {

    return [

      'seconde'  => 'Seconde',

      'premiere' => 'Première',

    ];

  }



  if ($level_id === 3) {

    return $all;

  }



  return $all;

}



private static function default_exercise_level_for_current_user(): string {

  $level_id = self::current_student_school_level_id();



  if ($level_id === 1) return 'seconde';

  if ($level_id === 2) return 'premiere';

  if ($level_id === 3) return 'terminale';



  return '';

}

private static function exercise_list_fallback_html(string $page, string $lvl, string $exam_only): string {
  if (!class_exists('\Ouinpo\Exercises\Rest\ExercisesRoutes') || !class_exists('\WP_REST_Request')) {
    return '<div class="ouinpo-loading">Chargement des exercices…</div>';
  }

  $request = new \WP_REST_Request('GET', '/ouinpo/v1/exercises');

  if ($lvl !== '') {
    $request->set_param('school_level', $lvl);
  }

  if ($exam_only === '1') {
    $request->set_param('exam_only', '1');
  }

  $response = \Ouinpo\Exercises\Rest\ExercisesRoutes::index($request);

  if (is_wp_error($response)) {
    return '<div class="ouinpo-loading">Chargement des exercices…</div>';
  }

  $items = $response instanceof \WP_REST_Response
    ? $response->get_data()
    : $response;

  if (!is_array($items)) {
    return '<div class="ouinpo-loading">Chargement des exercices…</div>';
  }

  if (empty($items)) {
    return '<div class="ouinpo-empty">Aucun exercice ne correspond aux filtres.</div>';
  }

  $items = array_slice($items, 0, 200);

  ob_start();
  ?>
  <div class="ouinpo-exercises-meta"></div>
  <section class="ouinpo-exo-domain-block">
    <h3 class="ouinpo-exo-domain-title">Tous les exercices</h3>
    <ul class="ouinpo-exercises-list ouinpo-exo-list">
      <?php foreach ($items as $item): ?>
        <?php
        $item = is_object($item) ? $item : (object) $item;
        $id = isset($item->id) ? (int) $item->id : 0;
        if ($id <= 0) {
          continue;
        }

        $title = isset($item->title) && $item->title !== ''
          ? (string) $item->title
          : 'Exercice #' . $id;

        $url = add_query_arg('exo', $id, $page);
        ?>
        <li class="ouinpo-exercise-item ouin-exo-li">
          <div class="ouinpo-exercise-main ouin-exo-main">
            <a class="ouinpo-exercise-link ouin-exo-link" href="<?php echo esc_url($url); ?>">
              <?php echo esc_html($title); ?>
            </a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php
  return trim((string) ob_get_clean());
}



  /** Liste des exercices (le JS remplira #ouinpo-exercises depuis l’API REST) */

public static function render_list($atts = array(), $content = '') {

  wp_enqueue_script('ouinpo-exo-js');

  wp_enqueue_style('ouinpo-exo-css');



  $atts = shortcode_atts(array(

    'page'      => '',

    'lvl'       => '',

    'exam_only' => '0',

  ), $atts, 'ouinpo_exercises');



  $page = trim((string) $atts['page']);

  if ($page === '') {
      $page = self::page_url_or_fallback('exercice', '/exercice/');
  }

  $page = esc_url_raw($page);



  $is_logged = is_user_logged_in();



  // Choix proposés dans le filtre de niveau.

  // Élève de seconde : Seconde seulement.

  // Élève de première : Seconde + Première, avec Première par défaut.

  // Élève de terminale : Seconde + Première + Terminale, avec Terminale par défaut.

  // Prof/admin ou visiteur : vue libre avec option Tous.

  $level_options = self::exercise_level_options_for_current_user();

  $default_lvl   = self::default_exercise_level_for_current_user();

  $allowed       = array_keys($level_options);



  $lvl_attr = sanitize_key((string) $atts['lvl']);

  if (!in_array($lvl_attr, $allowed, true)) {

    $lvl_attr = '';

  }



  $lvl_get = isset($_GET['lvl']) ? sanitize_key((string) $_GET['lvl']) : '';

  if ($lvl_get !== '' && !in_array($lvl_get, $allowed, true)) {

    $lvl_get = '';

  }



  if ($lvl_get !== '') {

    $lvl = $lvl_get;

  } elseif ($lvl_attr !== '') {

    $lvl = $lvl_attr;

  } else {

    $lvl = $default_lvl;

  }



  $show_all_option = (!$is_logged || $default_lvl === '' || current_user_can('edit_posts') || current_user_can('edit_users'));



  $level_label = '';

  if ($lvl !== '' && isset($level_options[$lvl])) {

    $level_label = $level_options[$lvl];

  }



  $exam_only = !empty($atts['exam_only']) && in_array((string) $atts['exam_only'], ['1', 'true', 'yes', 'oui', 'on'], true)

    ? '1'

    : '0';

  $initial_list_html = $is_logged
    ? '<div class="ouinpo-loading">Chargement des exercices…</div>'
    : self::exercise_list_fallback_html($page, $lvl, $exam_only);

  if (!$is_logged) {
    wp_add_inline_script(
      'ouinpo-exo-js',
      <<<'JS'
(function(){
  var attempts = 0;
  var maxAttempts = 80;
  var observer = null;
  var timer = null;

  function moveExerciseFilters() {
    var slot = document.getElementById('ouinpo-exo-dynamic-filters-slot');
    var filters = document.getElementById('ouinpo-exo-dynamic-filters');
    var shell = slot ? slot.closest('.ouinpo-exercises-shell') : null;

    if (!slot || !filters || !shell || filters.parentNode === slot) {
      return !!(slot && filters && filters.parentNode === slot);
    }

    if (filters.closest('.ouinpo-exercises-shell') === shell) {
      slot.appendChild(filters);
      return true;
    }

    return false;
  }

  function tick() {
    attempts += 1;

    if (moveExerciseFilters() || attempts >= maxAttempts) {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
      if (observer) {
        observer.disconnect();
        observer = null;
      }
    }
  }

  if (window.MutationObserver) {
    observer = new MutationObserver(tick);
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  timer = window.setInterval(tick, 150);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tick);
  } else {
    tick();
  }
})();
JS,
      'after'
    );
  }



  ob_start();

  ?>

  <div class="ouinpo-exercises-page">

    <div class="ouinpo-exercises-shell">



      <section class="ouinpo-panel ouinpo-panel--filters">

        <h2 class="ouinpo-panel-title">Choisir le niveau</h2>



        <div class="ouinpo-filters-grid ouinpo-filters-grid--single">

          <div class="ouinpo-field">

            <label for="ouinpo-exo-level">Niveau</label>

            <select id="ouinpo-exo-level" class="ouinpo-select">

              <?php if ($show_all_option): ?>

                <option value="" <?php selected($lvl, ''); ?>>Tous</option>

              <?php endif; ?>



              <?php foreach ($level_options as $slug => $label): ?>

                <option value="<?php echo esc_attr($slug); ?>" <?php selected($lvl, $slug); ?>>

                  <?php echo esc_html($label); ?>

                </option>

              <?php endforeach; ?>

            </select>

          </div>

        </div>

      </section>

      <script>
      (function(){
        var sel = document.getElementById("ouinpo-exo-level");
        if(!sel) return;
        sel.addEventListener("change", function(){
          var url = new URL(window.location.href);
          if (this.value) {
            url.searchParams.set("lvl", this.value);
          } else {
            url.searchParams.delete("lvl");
          }
          window.location.href = url.toString();
        });
      })();
      </script>


      <div id="ouinpo-exo-dynamic-filters-slot"></div>



      <section class="ouinpo-panel ouinpo-panel--results">

        <div class="ouinpo-panel-head">

          <h2 class="ouinpo-panel-title">Banque d’exercices</h2>

          <p class="ouinpo-panel-intro">

            Choisis un exercice, filtre par difficulté, domaine ou compétence, puis poursuis ta progression dans les arcanes du code.

          </p>

        </div>



        <div

          id="ouinpo-exercises"

          class="ouinpo-exercises-root"

          data-exo-page="<?php echo esc_attr($page); ?>"

          data-source="shortcode"

          data-logged="<?php echo $is_logged ? '1' : '0'; ?>"

          data-level="<?php echo esc_attr($lvl); ?>"

          data-level-label="<?php echo esc_attr($level_label); ?>"

          data-exam-only="<?php echo esc_attr($exam_only); ?>"

        >

          <?php echo $initial_list_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        </div>



        <div id="ouinpo-exo-list" class="ouinpo-exercises-legacy" hidden></div>

      </section>



    </div>

  </div>

  <?php



  return ob_get_clean();

}

/** Liste des sujets d’épreuve pratique */

public static function render_practical_subjects($atts = array(), $content = '') {

  wp_enqueue_script('ouinpo-practical-js');

  wp_enqueue_style('ouinpo-practical-css');



  $atts = shortcode_atts(array(

    'page'       => '',

    'lvl'        => '',

    'source_type'=> '',

    'theme_bac'  => '',

  ), $atts, 'ouinpo_practical_subjects');



  $page = trim((string) $atts['page']);

  if ($page === '') {
      $page = self::page_url_or_fallback('epreuve-pratique-sujet', '/epreuve-pratique-sujet/');
  }

  $page = esc_url_raw($page);



  $is_logged = is_user_logged_in();



  $allowed = ['seconde', 'premiere', 'terminale'];



  $lvl_attr = sanitize_key((string) $atts['lvl']);

  if (!in_array($lvl_attr, $allowed, true)) {

    $lvl_attr = '';

  }



  $lvl = isset($_GET['lvl']) ? sanitize_key($_GET['lvl']) : $lvl_attr;

  if (!in_array($lvl, $allowed, true)) {

    $lvl = $lvl_attr;

  }



  $source_type = sanitize_key((string) $atts['source_type']);

  $theme_bac   = sanitize_text_field((string) $atts['theme_bac']);



  ob_start();

  ?>

  <div class="ouinpo-exercises-page ouinpo-practical-page">

    <div class="ouinpo-exercises-shell">



      <?php if (!$is_logged): ?>

        <section class="ouinpo-panel ouinpo-panel--filters">

          <h2 class="ouinpo-panel-title">Filtrer les sujets</h2>



          <div class="ouinpo-filters-grid ouinpo-filters-grid--single">

            <div class="ouinpo-field">

              <label for="ouinpo-practical-level">Niveau</label>

              <select id="ouinpo-practical-level" class="ouinpo-select">

                <option value="" <?php selected($lvl, ''); ?>>Tous</option>

                <option value="seconde" <?php selected($lvl, 'seconde'); ?>>Seconde</option>

                <option value="premiere" <?php selected($lvl, 'premiere'); ?>>Première</option>

                <option value="terminale" <?php selected($lvl, 'terminale'); ?>>Terminale</option>

              </select>

            </div>

          </div>

        </section>



      <?php endif; ?>



      <section class="ouinpo-panel ouinpo-panel--results">

        <div class="ouinpo-panel-head">

          <h2 class="ouinpo-panel-title">Sujets d’épreuve pratique</h2>

          <p class="ouinpo-panel-intro">

            Choisis un sujet, télécharge les fichiers fournis, puis réponds aux appels évaluateurs corrigés par SegFault.

          </p>

        </div>



        <div

          id="ouinpo-practical-subjects"

          class="ouinpo-practical-subjects-root"

          data-subject-page="<?php echo esc_attr($page); ?>"

          data-logged="<?php echo $is_logged ? '1' : '0'; ?>"

          data-level="<?php echo esc_attr($lvl); ?>"

          data-source-type="<?php echo esc_attr($source_type); ?>"

          data-theme-bac="<?php echo esc_attr($theme_bac); ?>"

        >

          <div class="ouinpo-loading">Chargement des sujets pratiques…</div>

        </div>

      </section>



    </div>

  </div>

  <?php



  return ob_get_clean();

}



/** Affichage d’un sujet d’épreuve pratique */

public static function render_practical_subject($atts = array(), $content = '') {

  wp_enqueue_script('ouinpo-practical-js');

  wp_enqueue_style('ouinpo-practical-css');



  global $wpdb;



  $atts = shortcode_atts(array(

    'id'   => 0,

    'slug' => '',

    'auto' => '1',

  ), $atts, 'ouinpo_practical_subject');



  $id = (int) $atts['id'];



  if ($id <= 0 && $atts['auto'] === '1') {

    if (isset($_GET['practical'])) {

      $id = (int) $_GET['practical'];

    } elseif (isset($_GET['subject'])) {

      $id = (int) $_GET['subject'];

    }

  }



  if ($id <= 0 && !empty($atts['slug'])) {

    $table_exercises = $wpdb->prefix . 'ouin_exo_exercises';

    $table_exam_meta = $wpdb->prefix . 'ouin_exo_exam_meta';



    $id = (int) $wpdb->get_var($wpdb->prepare(

      "SELECT e.id

       FROM {$table_exercises} e

       INNER JOIN {$table_exam_meta} em ON em.exercise_id = e.id

       WHERE e.slug = %s

         AND em.exam_type = 'practical_subject'

       LIMIT 1",

      sanitize_title($atts['slug'])

    ));

  }



  if ($id <= 0) {

    return '<p>Sujet pratique non spécifié. Utilise <code>[ouinpo_practical_subject id="123"]</code> '

         . 'ou <code>[ouinpo_practical_subject slug="mon-sujet"]</code>, '

         . 'ou ajoute <code>?practical=123</code> à l’URL.</p>';

  }



  $table_exam_meta = $wpdb->prefix . 'ouin_exo_exam_meta';

  $is_practical = (int) $wpdb->get_var($wpdb->prepare(

    "SELECT COUNT(*)

     FROM {$table_exam_meta}

     WHERE exercise_id = %d

       AND exam_type = 'practical_subject'",

    $id

  ));



  if ($is_practical <= 0) {

    return '<p>Le sujet demandé n’est pas un sujet d’épreuve pratique valide.</p>';

  }



  $is_logged = is_user_logged_in();

  $ai_notice = self::render_ai_notice('practical');



return '

  <div class="ouinpo-practical-subject"

       data-subject-id="' . esc_attr($id) . '"

       data-logged="' . ($is_logged ? '1' : '0') . '">



    <div class="ouinpo-practical-subject__header">

      <h2 class="ouinpo-practical-title">Chargement du sujet pratique…</h2>

    </div>



    <div class="ouinpo-practical-statement"></div>

    <div class="ouinpo-practical-files"></div>'

    . $ai_notice .

    '<div class="ouinpo-practical-progress is-hidden"></div>

    <div class="ouinpo-practical-calls"></div>

  </div>';

}



  /** Affichage d’un exercice (id, slug, ou auto via ?exo=) */



public static function render_single($atts = array(), $content = '') {

  wp_enqueue_script('ouinpo-exo-js');

  wp_enqueue_style('ouinpo-exo-css');



  global $wpdb;

  $atts = shortcode_atts(array(

    'id'   => 0,

    'slug' => '',

    'auto' => '1',

  ), $atts, 'ouinpo_exercise');



  $id = (int) $atts['id'];



  if ($id <= 0 && $atts['auto'] === '1' && isset($_GET['exo'])) {

    $id = (int) $_GET['exo'];

  }



  if ($id <= 0 && !empty($atts['slug'])) {

    $table = $wpdb->prefix . 'ouin_exo_exercises';

    $id = (int) $wpdb->get_var($wpdb->prepare(

      "SELECT id FROM {$table} WHERE slug=%s",

      sanitize_title($atts['slug'])

    ));

  }



if ($id <= 0) {

  return '<p>Exercice non spécifié. Utilise <code>[ouinpo_exercise id="123"]</code> '

       . 'ou <code>[ouinpo_exercise slug="mon-slug"]</code>, ou ajoute <code>?exo=123</code> à l’URL.</p>';

}



$table_exam_meta = $wpdb->prefix . 'ouin_exo_exam_meta';



$is_practical_subject = (int) $wpdb->get_var($wpdb->prepare(

  "SELECT COUNT(*)

   FROM {$table_exam_meta}

   WHERE exercise_id = %d

     AND exam_type = 'practical_subject'",

  $id

));



if ($is_practical_subject > 0) {

  $url = add_query_arg('practical', (int) $id, home_url('/epreuve-pratique-sujet/'));



  return '<div class="ouinpo-exo ouinpo-exo-locked">'

       . '<p><strong>Sujet pratique</strong> : ce contenu n’est pas un exercice classique.</p>'

       . '<p><a href="' . esc_url($url) . '">Ouvrir dans la page des sujets pratiques</a></p>'

       . '</div>';

}



$is_logged = is_user_logged_in();



  // ------------------------------------------------------------

  // Garde "parcours séquentiel" via ?sf_path=

  // ------------------------------------------------------------

  if ($is_logged && isset($_GET['sf_path'])) {

    $path_id = (int) $_GET['sf_path'];

    $user_id = (int) get_current_user_id();



    if ($path_id > 0) {

      $path = PathsService::get_path($path_id);



      if (!$path || !PathsService::is_path_assigned_to_user($path_id, $user_id)) {

        return '<div class="ouinpo-exo ouinpo-exo-locked">'

             . '<p><strong>Accès refusé</strong> : ce parcours ne t’est pas assigné.</p>'

             . '</div>';

      }



      $ordered_ids = PathsService::get_ordered_exercise_ids($path_id);

      if (!in_array($id, $ordered_ids, true)) {

        return '<div class="ouinpo-exo ouinpo-exo-locked">'

             . '<p><strong>Exercice hors parcours</strong> : cet exercice ne fait pas partie du parcours demandé.</p>'

             . '</div>';

      }



      if (($path['mode'] ?? 'free') === 'sequential'

          && !PathsService::is_exercise_unlocked_for_user($path_id, $user_id, $id)) {



        $ref = wp_get_referer();

        $back_link = '';



        if ($ref) {

          $back_link = '<p><a href="' . esc_url($ref) . '">← Retour au parcours</a></p>';

        }



        return '<div class="ouinpo-exo ouinpo-exo-locked">'

             . '<p><strong>Exercice verrouillé</strong> : termine d’abord le précédent dans ce parcours séquentiel.</p>'

             . $back_link

             . '</div>';

      }

    }

  }



  $show_status_actions = $is_logged

      && ((int) get_option('ouinpo_exo_show_status_actions', 0) === 1);



  $id_attr = esc_attr($id);

  $ai_notice = self::render_ai_notice('exercise');



    $answer_box = '

      <div class="exo-answer-box">

        <h4>Ma réponse</h4>

        <button type="button" class="exo-insert-code">Insérer un bloc code</button>

        <textarea class="exo-answer-text" rows="10" placeholder="Rédige ici ta réponse.

    Si tu ajoutes du code, encadre-le avec [code] et [/code]."></textarea>

        <p class="exo-answer-help">

          <em>Pour du code, encadre-le avec <code>[code]</code> et <code>[/code]</code>.</em>

        </p>'

        . (!$is_logged ? '

        <p class="exo-answer-help exo-answer-help--public">

          <em>Mode visiteur : SegFault donne un retour, mais aucune progression, note, badge ou tentative n’est enregistré.</em>

        </p>' : '') .

        '<div class="exo-answer-actions exo-answer-actions--submit">

          <button type="button" class="exo-submit-answer">Soumettre à SegFault</button>

        </div>

        <div class="exo-ai-feedback"></div>

      </div>';



  return '

    <div class="ouinpo-exo"

         data-exo-id="' . $id_attr . '"

         data-logged="' . ($is_logged ? '1' : '0') . '"

         data-show-status-actions="' . ($show_status_actions ? '1' : '0') . '">

        <div class="exo-statement"></div>'

      . $ai_notice

      . $answer_box

      .'<div class="exo-hints">

        <button data-hint="1">Indice 1</button>

        <button data-hint="2" disabled>Indice 2</button>

        <button data-hint="3" disabled>Indice 3</button>

      </div>

      <div class="exo-solutions">

        <h4>Corrigés possibles</h4>

        <ul class="solutions-list"></ul>

      </div>

      <div class="exo-reveal"></div>

    </div>';

}



  /** ✅ Progression des compétences BO (élève connecté) */



public static function render_progress($atts = array(), $content = '') {

  if (!is_user_logged_in()) {

    return '<div class="ouinpo-competences ouinpo-alert">Connecte-toi pour voir ta progression.</div>';

  }



  global $wpdb;

  $atts = shortcode_atts(array(

    'year'   => 'active',

    'group'  => 'auto',

    'detail' => '1'

  ), $atts, 'ouinpo_competences_progress');



  $uid      = get_current_user_id();

  $tblYears = $wpdb->prefix . 'ouin_exo_academic_years';

  $tblGM    = $wpdb->prefix . 'ouin_exo_group_members';



  if ($atts['year'] === 'active') {

    $year_id = (int) $wpdb->get_var("SELECT id FROM $tblYears WHERE is_active=1 LIMIT 1");

  } else {

    $year_id = (int) $atts['year'];

  }



  if ($atts['group'] === 'auto') {

    $group_id = (int) $wpdb->get_var($wpdb->prepare(

      "SELECT group_id FROM $tblGM WHERE user_id=%d ORDER BY group_id ASC LIMIT 1",

      $uid

    ));

  } else {

    $group_id = (int) $atts['group'];

  }



  wp_enqueue_style('ouinpo-exo-css');

  wp_enqueue_script('ouinpo-student-competencies');



  $nonce = wp_create_nonce('wp_rest');

  wp_add_inline_script(
    'ouinpo-student-competencies',
    'window.OUINEXO = Object.assign({}, window.OUINEXO || {}, ' . wp_json_encode([
      'api'   => esc_url_raw(rest_url()),
      'nonce' => $nonce,
    ]) . ');',
    'before'
  );

  ob_start(); ?>

    <div class="ouinpo-competences"

         data-year="<?php echo esc_attr($year_id); ?>"

         data-group="<?php echo esc_attr($group_id); ?>"

         data-detail="<?php echo esc_attr($atts['detail']); ?>"

         data-nonce="<?php echo esc_attr($nonce); ?>">



      <div class="ouinpo-me-tabs">

        <button type="button" class="ouinpo-me-tab is-active" data-tab="global">

          Vue globale

        </button>

        <button type="button" class="ouinpo-me-tab" data-tab="devoirs">

          Devoirs notés

        </button>

        <button type="button" class="ouinpo-me-tab" data-tab="training">

          Exercices par compétence

        </button>

      </div>



      <section class="ouinpo-me-panel is-active" id="ouinpo-tab-global">

        <div class="ouinpo-me-summary">

          <h2>Mes compétences BO</h2>

          <div class="ouinpo-cards">

            <div class="card">

              <div class="kpi" id="kpi-total">—</div>

              <div class="kpi-label">Compétences suivies</div>

            </div>

            <div class="card">

              <div class="kpi" id="kpi-acq">—</div>

              <div class="kpi-label">Acquis</div>

            </div>

            <div class="card">

              <div class="kpi" id="kpi-conso">—</div>

              <div class="kpi-label">En consolidation</div>

            </div>

            <div class="card">

              <div class="kpi" id="kpi-prog">—</div>

              <div class="kpi-label">En progression</div>

            </div>

            <div class="card">

              <div class="kpi" id="kpi-na">—</div>

              <div class="kpi-label">Non acquis</div>

            </div>

          </div>

        </div>



        <div class="ouinpo-me-domains">

          <h3>Par domaine</h3>

          <div id="domains-bars"></div>

        </div>



        <div class="ouinpo-me-detail is-hidden" id="detail-block">

          <h3>Détail par compétence — priorités d’entraînement</h3>

          <ul id="detail-list" class="ouinpo-list"></ul>

        </div>

      </section>



<section class="ouinpo-me-panel" id="ouinpo-tab-devoirs">

  <div class="ouinpo-me-summary">

    <h2>Mon évolution via les devoirs notés</h2>

    <div class="ouinpo-cards ouinpo-ds-cards">

      <div class="card">

        <div class="kpi" id="ds-kpi-evaluated">—</div>

        <div class="kpi-label">Compétences évaluées</div>

      </div>

      <div class="card">

        <div class="kpi" id="ds-kpi-acq">—</div>

        <div class="kpi-label">Acquis</div>

      </div>

      <div class="card">

        <div class="kpi" id="ds-kpi-conso">—</div>

        <div class="kpi-label">En consolidation</div>

      </div>

      <div class="card">

        <div class="kpi" id="ds-kpi-prog">—</div>

        <div class="kpi-label">En progression</div>

      </div>

      <div class="card">

        <div class="kpi" id="ds-kpi-na">—</div>

        <div class="kpi-label">Non acquis</div>

      </div>

    </div>

  </div>



  <div class="ouinpo-ds-filter js-ds-assessment-filter is-hidden">

    <label for="ouinpo-ds-assessment-select">Choisir un devoir noté</label>

    <select id="ouinpo-ds-assessment-select" class="js-ds-assessment-select">

      <option value="">Tous les devoirs notés</option>

    </select>

  </div>



  <div class="ouinpo-me-priorities">

    <h3>À travailler en priorité</h3>

    <ul id="ds-priority-list" class="ouinpo-list"></ul>

  </div>



  <div class="ouinpo-me-ds-list">

    <h3>Détail par compétence</h3>

    <div id="ds-competencies-list"></div>

  </div>

</section>



        <section class="ouinpo-me-panel" id="ouinpo-tab-training">

          <div class="ouinpo-me-summary">

            <h2>Mes exercices par compétence</h2>

            <div class="ouinpo-cards">

              <div class="card">

                <div class="kpi" id="ex-kpi-total">—</div>

                <div class="kpi-label">Compétences proposées</div>

              </div>

              <div class="card">

                <div class="kpi" id="ex-kpi-worked">—</div>

                <div class="kpi-label">Déjà travaillées</div>

              </div>

              <div class="card">

                <div class="kpi" id="ex-kpi-solid">—</div>

                <div class="kpi-label">Réussite solide</div>

              </div>

              <div class="card">

                <div class="kpi" id="ex-kpi-priority">—</div>

                <div class="kpi-label">À découvrir / renforcer</div>

              </div>

            </div>

          </div>

        

          <div class="ouinpo-help-box">

            <h3>🧠 Comment lire cet onglet</h3>

            <p>

              La <strong>couverture</strong> indique combien d’exercices de la compétence tu as déjà travaillés.

              Le <strong>taux de réussite</strong> indique combien d’exercices tentés tu as réussis.

            </p>

            <p>

              Une bonne réussite sur très peu d’exercices ne dit pas la même chose qu’une réussite stable sur plusieurs exercices.

            </p>

          </div>

        

          <div class="ouinpo-training-filters">

            <div class="ouinpo-filter">

              <label for="ouinpo-training-domain">Domaine</label>

              <select id="ouinpo-training-domain" class="js-training-domain">

                <option value="">Tous les domaines</option>

              </select>

            </div>

        

            <div class="ouinpo-filter">

              <label for="ouinpo-training-comp">Compétence BO</label>

              <select id="ouinpo-training-comp" class="js-training-comp">

                <option value="">Toutes les compétences</option>

              </select>

            </div>

          </div>

        

          <div class="ouinpo-me-ds-list">

            <h3>Détail par compétence</h3>

            <div id="training-competencies-list"></div>

          </div>

        </section>

    </div>

  <?php

  return ob_get_clean();

}



  /**

   * ✅ Interface front "prof" :

   * visualisation avec filtres (Année / Classe / Domaine / Élève)

   * Vues : Détail par élève, Par domaine.

   */

public static function render_teacher($atts = [], $content = '') {



  if (!current_user_can('edit_users')) {

    return '<div class="ouinpo-competences ouinpo-alert">Cette page est réservée aux enseignants.</div>';

  }



  global $wpdb;



  $tblYears   = $wpdb->prefix . 'ouin_exo_academic_years';

  $tblGroups  = $wpdb->prefix . 'ouin_exo_groups';

  $tblGM      = $wpdb->prefix . 'ouin_exo_group_members';

  $tblComps   = $wpdb->prefix . 'ouin_exo_competencies';

  $tblUsers   = $wpdb->users;



  // --- Lecture des filtres (query string) ---

  $year_id  = isset($_GET['year_id'])  ? (int) $_GET['year_id']  : 0;

  $group_id = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;

  $domain   = isset($_GET['domain'])   ? sanitize_text_field($_GET['domain']) : '';

  $user_id  = isset($_GET['user_id'])  ? (int) $_GET['user_id']  : 0;

  $view     = isset($_GET['view'])     ? sanitize_text_field($_GET['view'])    : 'detail';



  $allowed_views = ['detail', 'domain'];

  if (!in_array($view, $allowed_views, true)) {

    $view = 'detail';

  }



  // --- Années disponibles ---

  $years = $wpdb->get_results("SELECT id, slug AS label, is_active FROM $tblYears ORDER BY starts_on DESC");



  if ($year_id <= 0 && $years) {

    foreach ($years as $y) {

      if ($y->is_active) {

        $year_id = (int) $y->id;

        break;

      }

    }

    if ($year_id <= 0) {

      $year_id = (int) $years[0]->id;

    }

  }



  // --- Groupes pour l’année ---

  $groups = [];

  if ($year_id > 0) {

    $groups = $wpdb->get_results(

      $wpdb->prepare("SELECT id, label FROM $tblGroups WHERE year_id = %d ORDER BY label ASC", $year_id)

    );

  }



  if ($group_id > 0 && $groups) {

    $valid = false;

    foreach ($groups as $g) {

      if ((int) $g->id === $group_id) {

        $valid = true;

        break;

      }

    }

    if (!$valid) $group_id = 0;

  }



  // --- Domaines disponibles ---

  $tblLevels  = $wpdb->prefix . 'ouin_exo_school_levels';

  $domains    = [];

  $levelLabel = null;



  if ($group_id > 0) {

    $levelRow = $wpdb->get_row($wpdb->prepare(

      "SELECT sl.label

       FROM $tblGroups g

       JOIN $tblLevels sl ON sl.id = g.school_level_id

       WHERE g.id = %d",

      $group_id

    ));



    if ($levelRow) {

      $levelLabel = $levelRow->label;

    }

  }



  if ($levelLabel) {

    $domains = $wpdb->get_results($wpdb->prepare(

      "SELECT DISTINCT domain_slug AS slug, domain AS label, level

       FROM $tblComps

       WHERE active = 1

         AND level IN ('Transversal', %s)

       ORDER BY

         CASE

           WHEN level = %s THEN 0

           WHEN level = 'Transversal' THEN 1

           ELSE 2

         END,

         domain ASC",

      $levelLabel,

      $levelLabel

    ));

  } else {

    $domains = $wpdb->get_results(

      "SELECT DISTINCT domain_slug AS slug, domain AS label

       FROM $tblComps

       WHERE active = 1

       ORDER BY domain ASC"

    );

  }



  // --- Liste des élèves ---

  $students = [];

  if ($year_id > 0) {

    $sql = "

      SELECT DISTINCT u.ID, u.display_name

      FROM $tblUsers AS u

      INNER JOIN $tblGM AS gm ON gm.user_id = u.ID

      INNER JOIN $tblGroups AS g ON g.id = gm.group_id

      WHERE gm.role = 'student'

        AND g.year_id = %d

    ";

    $params = [$year_id];



    if ($group_id > 0) {

      $sql .= " AND gm.group_id = %d";

      $params[] = $group_id;

    }



    $sql .= " ORDER BY u.display_name ASC";



    $students = $wpdb->get_results($wpdb->prepare($sql, ...$params));



    if ($user_id > 0 && $students) {

      $ids = array_map(static function ($s) { return (int) $s->ID; }, $students);

      if (!in_array($user_id, $ids, true)) {

        $user_id = 0;

      }

    }

  }



  // --- Assets front prof ---

wp_enqueue_style('ouinpo-teacher-css');

wp_enqueue_script('ouinpo-teacher-competencies');



  $nonce = wp_create_nonce('wp_rest');



  ob_start(); ?>

      <div class="ouinpo-teacher ouinpo-competences">



        <h2>Tableau de bord de la classe</h2>



        <div class="ouinpo-toolbar">



          <div class="field">

            <label for="t-year">Année</label>

            <select id="t-year">

              <option value="0">— Choisir —</option>

              <?php foreach($years as $y): ?>

                <option value="<?= (int)$y->id ?>" <?= selected($year_id, $y->id, false) ?>>

                  <?= esc_html($y->label) ?><?= $y->is_active ? ' (active)' : '' ?>

                </option>

              <?php endforeach; ?>

            </select>

          </div>



          <div class="field">

            <label for="t-group">Classe</label>

            <select id="t-group">

              <option value="0">— Toutes —</option>

              <?php foreach($groups as $g): ?>

                <option value="<?= (int)$g->id ?>" <?= selected($group_id, $g->id, false) ?>>

                  <?= esc_html($g->label) ?>

                </option>

              <?php endforeach; ?>

            </select>

          </div>



          <div class="field">

            <label for="t-domain">Domaine</label>

            <select id="t-domain">

              <option value="">Tous les domaines</option>

              <?php foreach($domains as $d): ?>

                <option value="<?= esc_attr($d->slug) ?>" <?= selected($domain, $d->slug, false) ?>>

                  <?= esc_html($d->label) ?>

                </option>

              <?php endforeach; ?>

            </select>

          </div>



          <div class="field">

            <label for="t-user">Élève</label>

            <select id="t-user">

              <option value="0">Tous les élèves</option>

              <?php foreach($students as $s): ?>

                <option value="<?= (int)$s->ID ?>" <?= selected($user_id, $s->ID, false) ?>>

                  <?= esc_html($s->display_name) ?>

                </option>

              <?php endforeach; ?>

            </select>

          </div>



          <div class="field">

            <label for="t-view">Vue</label>

            <select id="t-view">

              <option value="detail" <?= selected($view, 'detail', false) ?>>Détail par élève</option>

              <option value="domain" <?= selected($view, 'domain', false) ?>>Par domaine</option>

            </select>

          </div>



          <button id="t-refresh" class="btn-copper">Actualiser</button>

        </div>



        <div class="ouinpo-me-tabs">

          <button type="button" class="ouinpo-me-tab is-active" data-tab="global">

            Vue globale

          </button>

          <button type="button" class="ouinpo-me-tab" data-tab="ds">

            Évolution via les Devoirs

          </button>

            <button type="button" class="ouinpo-me-tab" data-tab="ex">

              Suivi des exercices

            </button>

        </div>



        <section class="ouinpo-me-panel is-active" id="ouinpo-teacher-tab-global">

          <div class="ouinpo-cards">

            <div class="card">

              <div class="kpi" id="t-kpi-students">—</div>

              <div class="kpi-label">Élèves</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-kpi-acq">—</div>

              <div class="kpi-label">Compétences acquises</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-kpi-conso">—</div>

              <div class="kpi-label">En consolidation</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-kpi-progress">—</div>

              <div class="kpi-label">En progression</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-kpi-na">—</div>

              <div class="kpi-label">À travailler / non évaluées</div>

            </div>

          </div>



          <div id="t-results" class="copper-panel"

               data-year="<?= (int)$year_id ?>"

               data-group="<?= (int)$group_id ?>"

               data-domain="<?= esc_attr($domain) ?>"

               data-user="<?= (int)$user_id ?>"

               data-view="<?= esc_attr($view) ?>"

               data-nonce="<?= esc_attr($nonce) ?>">

            <div class="loading">Chargement…</div>

          </div>

        </section>



        <section class="ouinpo-me-panel" id="ouinpo-teacher-tab-ds">

          <div class="ouinpo-cards ouinpo-ds-cards">

            <div class="card">

              <div class="kpi" id="t-ds-kpi-evaluated">—</div>

              <div class="kpi-label">Devoirs</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-ds-kpi-acq">—</div>

              <div class="kpi-label">Élèves évalués</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-ds-kpi-conso">—</div>

              <div class="kpi-label">Absences</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-ds-kpi-prog">—</div>

              <div class="kpi-label">Compétences observées</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-ds-kpi-na">—</div>

              <div class="kpi-label">Acquis</div>

            </div>

          </div>



          <div id="t-ds-results" class="copper-panel"

               data-year="<?= (int)$year_id ?>"

               data-group="<?= (int)$group_id ?>"

               data-domain="<?= esc_attr($domain) ?>"

               data-user="<?= (int)$user_id ?>"

               data-nonce="<?= esc_attr($nonce) ?>">

            <div class="loading">Chargement…</div>

          </div>

        </section>



        <section class="ouinpo-me-panel" id="ouinpo-teacher-tab-ex">

          <div class="ouinpo-cards">

            <div class="card">

              <div class="kpi" id="t-ex-kpi-students">—</div>

              <div class="kpi-label">Élèves</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-ex-kpi-total">—</div>

              <div class="kpi-label">Compétences suivies</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-ex-kpi-worked">—</div>

              <div class="kpi-label">Déjà travaillées</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-ex-kpi-solid">—</div>

              <div class="kpi-label">Solides</div>

            </div>

            <div class="card">

              <div class="kpi" id="t-ex-kpi-priority">—</div>

              <div class="kpi-label">Prioritaires</div>

            </div>

          </div>

        

          <div id="t-ex-results" class="copper-panel"

               data-year="<?= (int)$year_id ?>"

               data-group="<?= (int)$group_id ?>"

               data-domain="<?= esc_attr($domain) ?>"

               data-user="<?= (int)$user_id ?>"

               data-nonce="<?= esc_attr($nonce) ?>">

            <div class="loading">Chargement…</div>

          </div>

        </section>



      </div>

  <?php

  return ob_get_clean();

}



  /** ✅ Page "Mes badges" : vue élève avec tri / filtres (utilise student-badges.js) */

  public static function render_student_badges($atts = [], $content = '') {

    if (!is_user_logged_in()) {

      return '<div class="ouinpo-competences ouinpo-alert">Connecte-toi pour voir tes badges.</div>';

    }



    wp_enqueue_style('ouinpo-exo-css');
    wp_enqueue_script('ouinpo-student-badges');



    return '<div id="ouinpo-student-badges"></div>';

  }



  /** ✅ Palmarès public des badges par année scolaire (tri = vue élève) */

  public static function render_badges_palmares($atts = [], $content = '') {

    global $wpdb;



    wp_enqueue_style('ouinpo-exo-css');
    wp_enqueue_script('ouinpo-student-badges');



    $atts = shortcode_atts([

      'year' => 'active',

    ], $atts, 'ouinpo_badges_palmares');



    $tYears      = $wpdb->prefix . 'ouin_exo_academic_years';

    $tGroups     = $wpdb->prefix . 'ouin_exo_groups';

    $tGM         = $wpdb->prefix . 'ouin_exo_group_members';

    $tBadges     = $wpdb->prefix . 'ouin_exo_badges';

    $tUserBadges = $wpdb->prefix . 'ouin_exo_user_badges';

    $tUsers      = $wpdb->users;

    $tComps      = $wpdb->prefix . 'ouin_exo_competencies';



    $years = $wpdb->get_results("SELECT * FROM $tYears ORDER BY starts_on DESC");

    if (!$years) {

      return '<div class="ouinpo-competences ouinpo-alert">Aucune année scolaire n’est configurée pour le palmarès.</div>';

    }



    if (isset($_GET['ouin_year']) && $_GET['ouin_year'] !== '') {

      $year_raw = sanitize_text_field($_GET['ouin_year']);

    } else {

      $year_raw = trim((string) $atts['year']);

    }



    $year_row = null;



    if ($year_raw === 'active') {

      $year_row = $wpdb->get_row("SELECT * FROM $tYears WHERE is_active = 1 LIMIT 1");

    } elseif (ctype_digit($year_raw)) {

      $year_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tYears WHERE id = %d", (int)$year_raw));

    } else {

      $year_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tYears WHERE slug = %s", $year_raw));

    }



    if (!$year_row) {

      $year_row = $wpdb->get_row("SELECT * FROM $tYears WHERE is_active = 1 LIMIT 1");

      if (!$year_row) {

        $year_row = $years[0];

      }

    }



    $year_label = esc_html($year_row->slug);

    $start_dt   = $year_row->starts_on . ' 00:00:00';

    $end_dt     = $year_row->ends_on   . ' 23:59:59';



    $render_palmares_shell = static function(string $body) use ($years, $year_row): string {

      $out  = '<div id="ouinpo-student-badges" class="ouinpo-palmares">';

      $out .= '<div class="ouinpo-palmares-header">';

      $out .= '  <h1 class="ouinpo-badges-section-title">Palmarès des badges</h1>';

      $out .= '  <div class="ouinpo-palmares-year-filter">';

      $out .= '    <label for="ouinpo-palmares-year">Année&nbsp;:</label>';

      $out .= '    <select id="ouinpo-palmares-year">';

      foreach ($years as $y) {

        $sel = ((int)$y->id === (int)$year_row->id) ? ' selected' : '';

        $label = esc_html($y->slug . ($y->is_active ? ' (active)' : ''));

        $out .= '      <option value="' . (int)$y->id . '"' . $sel . '>' . $label . '</option>';

      }

      $out .= '    </select>';

      $out .= '  </div>';

      $out .= '</div>';



      $out .= $body;

      $out .= '</div>';



      return $out;

    };



    $sql = "

      SELECT

        ub.user_id,

        ub.badge_id,

        ub.awarded_at,

        b.id        AS b_id,

        b.slug      AS slug,

        b.title     AS badge_title,

        b.description,

        b.theme,

        b.image_url

      FROM $tUserBadges AS ub

      INNER JOIN $tBadges AS b     ON b.id = ub.badge_id

      INNER JOIN $tGM AS gm        ON gm.user_id = ub.user_id

      INNER JOIN $tGroups AS g     ON g.id = gm.group_id

      INNER JOIN $tUsers AS u      ON u.ID = ub.user_id

      WHERE gm.role = 'student'

        AND g.year_id = %d

        AND ub.awarded_at BETWEEN %s AND %s

        AND u.user_login NOT IN ('eleve1ere', 'eleveTale')

    ";



    $rows = $wpdb->get_results(

      $wpdb->prepare($sql, $year_row->id, $start_dt, $end_dt),

      ARRAY_A

    );



    if (!$rows) {

      return $render_palmares_shell(

        '<div class="ouinpo-competences ouinpo-alert">Aucun badge décerné pour l’année ' . $year_label . ' pour le moment.</div>'

      );

    }



    // ------------------------------------------------------------

    // Helpers alignés sur student-badges.js

    // ------------------------------------------------------------

    $starts_with = static function(string $hay, string $needle): bool {

      return $needle !== '' && substr($hay, 0, strlen($needle)) === $needle;

    };



    $infer_level_from_badge = static function(array $b) use ($starts_with): string {

      $slug  = strtolower((string)($b['slug']  ?? ''));

      $theme = strtolower((string)($b['theme'] ?? ''));



      if ($theme === 'special' || $starts_with($slug, 'special-')) return 'Spécial';



      if (strpos($slug, 'seconde') !== false || strpos($theme, 'seconde') !== false) return 'Seconde';

      if (

        strpos($slug, 'premiere') !== false || strpos($slug, 'première') !== false ||

        strpos($theme, 'premiere') !== false || strpos($theme, 'première') !== false

      ) return 'Première';

      if (strpos($slug, 'terminale') !== false || strpos($theme, 'terminale') !== false) return 'Terminale';



      /* Comme ton JS : algorithmique -> Terminale

      if ($theme === 'algorithmique') return 'Terminale';*/



      return 'Transversal';

    };



    // Domaines connus (slug -> label), comme fetch /competencies/options dans le JS

    $known_domains = [];

    $domain_level  = [];

    

    $domain_rows = $wpdb->get_results(

      "SELECT domain_slug, domain, level

       FROM $tComps

       WHERE active = 1 AND domain_slug <> ''

       GROUP BY domain_slug, domain, level

       ORDER BY domain ASC",

      ARRAY_A

    );

    

    foreach ($domain_rows as $dr) {

      $ds = strtolower(trim((string)($dr['domain_slug'] ?? '')));

      if ($ds === '') continue;

      $known_domains[$ds] = (string)($dr['domain'] ?? $ds);

      $domain_level[$ds]  = (string)($dr['level']  ?? '');

    }



    // ------------------------------------------------------------

    // 1) Garder seulement le meilleur badge par "famille" et par élève (tiers bronze/argent/or)

    // ------------------------------------------------------------

    $level_order = ['bronze' => 1, 'argent' => 2, 'or' => 3];

    $best = [];



    foreach ($rows as $row) {

      $user_id = (int) $row['user_id'];

      $slug    = strtolower((string) $row['slug']);



      $level  = null;

      $suffix = '';



      foreach ($level_order as $lvl => $_) {

        $s = '-' . $lvl;

        if (substr($slug, -strlen($s)) === $s) {

          $level  = $lvl;

          $suffix = $s;

          break;

        }

      }



      if ($level === null) {

        $family        = $slug;

        $row['_level'] = 99;

        $row['_tier']  = '';

      } else {

        $family        = substr($slug, 0, -strlen($suffix));

        $row['_level'] = $level_order[$level];

        if ($level === 'bronze')      $row['_tier'] = 'bronze';

        elseif ($level === 'argent')  $row['_tier'] = 'silver';

        else                          $row['_tier'] = 'gold';

      }



      if (!isset($best[$user_id])) {

        $best[$user_id] = [];

      }

      if (!isset($best[$user_id][$family]) || $row['_level'] > $best[$user_id][$family]['_level']) {

        $best[$user_id][$family] = $row;

      }

    }



    if (empty($best)) {

      return $render_palmares_shell(

        '<div class="ouinpo-competences ouinpo-alert">Aucun badge éligible au palmarès pour cette année.</div>'

      );

    }



    // ------------------------------------------------------------

    // 2) Construire : defs de badges + holders

    // ------------------------------------------------------------

    $holders_by_badge = [];

    $badge_defs       = [];



    foreach ($best as $uid => $families) {

      foreach ($families as $family => $row) {

        $bid = (int) $row['b_id'];



        if (!isset($holders_by_badge[$bid])) {

          $holders_by_badge[$bid] = [];

          $copy = $row;

          unset($copy['user_id'], $copy['badge_id'], $copy['_level']);

          $badge_defs[$bid] = $copy;

        }



        $holders_by_badge[$bid][] = (int)$uid;

      }

    }



    if (empty($badge_defs)) {

      return $render_palmares_shell(

        '<div class="ouinpo-competences ouinpo-alert">Aucun badge à afficher dans le palmarès.</div>'

      );

    }



    // Cache noms utilisateurs

    $users_cache = [];

    foreach ($holders_by_badge as $bid => $uids) {

      foreach ($uids as $uid) {

        $uid = (int) $uid;

        if (!isset($users_cache[$uid])) {

          $u = get_userdata($uid);

          $users_cache[$uid] = $u ? $u->display_name : 'Élève ' . $uid;

        }

      }

    }



    // ------------------------------------------------------------

    // 3) Catégorisation EXACTE comme student-badges.js :

    //    - meta+special

    //    - domain (theme == domain_slug connu)

    //    - competency (reste)

    // ------------------------------------------------------------

    $meta_and_special = []; // [bid => badge_def]

    $domain_badges    = [];

    $competency       = [];



    foreach ($badge_defs as $bid => $b) {

      $theme = strtolower((string)($b['theme'] ?? ''));

      $slug  = strtolower((string)($b['slug']  ?? ''));



      $is_meta    = ($starts_with($theme, 'meta') || $starts_with($slug, 'meta-'));

      $is_special = ($theme === 'special' || $starts_with($slug, 'special-'));



      // marquages visuels (comme rendu élève)

      if ($is_special) $b['_platinum'] = true;



      if ($is_meta || $is_special) {

        $meta_and_special[$bid] = $b;

      } elseif ($theme !== '' && isset($known_domains[$theme])) {

        $domain_badges[$bid] = $b;

      } else {

        $competency[$bid] = $b;

      }



      $badge_defs[$bid] = $b;

    }



    // ------------------------------------------------------------

    // 4) Rendu “student-like” :

    //    section -> niveaux (ordre fixe) -> domaines (tri label) -> badges (ordre d’arrivée)

    // ------------------------------------------------------------

    $levels_order = ['Spécial', 'Terminale', 'Première', 'Seconde', 'Transversal'];



    $display_level_label = static function (string $level): string {

      return $level === 'Transversal' ? 'Transversale' : $level;

    };



    $render_section = function (string $title, array $badges_map) use (

      $badge_defs,

      $holders_by_badge,

      $users_cache,

      $infer_level_from_badge,

      $known_domains,

      $domain_level,

      $levels_order,

      $starts_with,

      $display_level_label

    ) {

      if (empty($badges_map)) return '';



      // Préparer les items (déductions level/domain comme le JS)

      $items = [];

      $idx = 0;

      foreach ($badges_map as $bid => $_) {

        if (!isset($badge_defs[$bid])) continue;

        $b = $badge_defs[$bid];



        $slug  = strtolower((string)($b['slug']  ?? ''));

        $theme = strtolower((string)($b['theme'] ?? ''));



        $level = $infer_level_from_badge($b);

        // ✅ Si on n'a pas deviné via slug/theme, on prend le niveau du domaine (si connu)

        $is_transversal_domain = (

          $theme !== '' &&

          isset($known_domains[$theme]) &&

          stripos((string)$known_domains[$theme], '(transversal)') !== false

        );



        $level = $is_transversal_domain ? 'Transversal' : $infer_level_from_badge($b);



        // On ne réinjecte le niveau du domaine que pour les vrais domaines de cycle,

        // pas pour les domaines explicitement marqués "(transversal)".

        if (

          !$is_transversal_domain &&

          $level === 'Transversal' &&

          $theme !== '' &&

          !empty($domain_level[$theme])

        ) {

          $level = $domain_level[$theme];

        }

        

        // domain_slug UNIQUEMENT si theme est un domaine connu (exact JS)

        $domain_slug  = '';

        $domain_label = 'Autres';

        if (

          $theme !== '' &&

          $theme !== 'special' &&

          !$starts_with($theme, 'meta') &&

          isset($known_domains[$theme])

        ) {

          $domain_slug  = $theme;

          $domain_label = (string)$known_domains[$theme];

        }



        $b['_level_label']  = $level;

        $b['_domain_slug']  = $domain_slug;

        $b['_domain_label'] = $domain_label;

        $b['_idx'] = $idx++; // ✅ sert à garder l’ordre d’origine à tier égal

        

        $items[] = $b;

      }



      if (!$items) return '';



      // Group by level

      $byLevel = [];

      foreach ($items as $b) {

        $L = (string)($b['_level_label'] ?? 'Transversal');

        if (!isset($byLevel[$L])) $byLevel[$L] = [];

        $byLevel[$L][] = $b; // ✅ pas de tri : ordre d’arrivée

      }



      $html  = '<section class="ouinpo-badges-section">';

      $html .= '<h2 class="ouinpo-badges-section-title">' . esc_html($title) . '</h2>';



      foreach ($levels_order as $lvl) {

        if (empty($byLevel[$lvl])) continue;



        $html .= '<div class="ouinpo-badges-level-block">';

        $html .= '<h3 class="ouinpo-badges-level-title">' . esc_html($display_level_label($lvl)) . '</h3>';



        // Group by domain within level

        $byDomain = []; // slug => ['label'=>..., 'items'=>[]]

        foreach ($byLevel[$lvl] as $b) {

          $ds = (string)($b['_domain_slug'] ?? '');

          $dl = (string)($b['_domain_label'] ?? 'Autres');

          if (!isset($byDomain[$ds])) $byDomain[$ds] = ['label' => $dl, 'items' => []];

          $byDomain[$ds]['items'][] = $b;

        }

        

        // ✅ Tri Or -> Argent -> Bronze (et "spécial/platinum" tout en haut si présent)

        $tierWeight = static function($badge): int {

          if (!empty($badge['_platinum'])) return 4; // spécial/platinum d'abord

          $t = (string)($badge['_tier'] ?? '');

          if ($t === 'gold')   return 3;

          if ($t === 'silver') return 2;

          if ($t === 'bronze') return 1;

          return 0; // badges sans tier à la fin

        };

        

        foreach ($byDomain as $k => $obj) {

          usort($byDomain[$k]['items'], static function($a, $b) use ($tierWeight){

            $wa = $tierWeight($a);

            $wb = $tierWeight($b);

            if ($wa !== $wb) return $wb <=> $wa;          // desc : 3,2,1,0

            return ((int)($a['_idx'] ?? 0)) <=> ((int)($b['_idx'] ?? 0)); // ordre d’origine

          });

        }



        // Domains sorted by label (exact JS)

        uasort($byDomain, static function($a, $b){

          return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));

        });



        // Meta ranks “comme le JS” : rang 1..3 par bloc de niveau, selon l’ordre d’affichage

        $meta_rank = 0;



        foreach ($byDomain as $ds => $obj) {

          $domTitle = (!empty($obj['label']) && $obj['label'] !== 'Autres') ? (string)$obj['label'] : '';



          $html .= '<div class="ouinpo-badges-domain-block">';

          if ($domTitle !== '') {

            $html .= '<h4 class="ouinpo-badges-domain-title">' . esc_html($domTitle) . '</h4>';

          }



          $html .= '<div class="ouinpo-badges-grid">';



          foreach ($obj['items'] as $badge) {

            $bid = (int)($badge['b_id'] ?? 0);



            $bt  = isset($badge['badge_title']) ? esc_html($badge['badge_title']) : '';

            $img = !empty($badge['image_url'])   ? esc_url($badge['image_url'])   : '';



            $slug  = strtolower((string)($badge['slug']  ?? ''));

            $theme = strtolower((string)($badge['theme'] ?? ''));



            $is_meta = ($starts_with($theme, 'meta') || $starts_with($slug, 'meta-'));

            $is_special = ($theme === 'special' || $starts_with($slug, 'special-'));



$tier_class = '';



// platinum (spécial)

if (!empty($badge['_platinum']) || $is_special) {

  $tier_class .= ' ouinpo-badge-tier-platinum';

}



/**

 * ✅ Tier fiable : on le (re)déduit DU SLUG en priorité,

 * puis fallback sur _tier si besoin.

 */

$tier = '';

if (substr($slug, -7) === '-bronze') $tier = 'bronze';

elseif (substr($slug, -7) === '-argent') $tier = 'silver';

elseif (substr($slug, -3) === '-or') $tier = 'gold';

else {

  // fallback si ta donnée _tier est déjà bonne

  $t0 = (string)($badge['_tier'] ?? '');

  if ($t0 === 'bronze' || $t0 === 'silver' || $t0 === 'gold') $tier = $t0;

}



if ($tier !== '') {

  $tier_class .= ' ouinpo-badge-tier-' . esc_attr($tier);

}



if ($is_meta) {

  // ✅ Rank = tier (1=bronze, 2=silver, 3=gold)

  $rank = 0;

  if ($tier === 'bronze') $rank = 1;

  elseif ($tier === 'silver') $rank = 2;

  elseif ($tier === 'gold') $rank = 3;



  if ($rank > 0) {

    $tier_class .= ' ouinpo-badge-meta-rank-' . $rank;

  } else {

    // fallback positionnel : uniquement si PAS DE TIER DÉTECTABLE

    $meta_rank++;

    if ($meta_rank <= 3) {

      $tier_class .= ' ouinpo-badge-meta-rank-' . (int)$meta_rank;

    }

  }

}

            $desc_raw  = (string)($badge['description'] ?? '');

            $desc_text = trim(wp_strip_all_tags($desc_raw));

            

            $html .= '<article class="ouinpo-badge-item' . $tier_class . '"'

                  . ' data-domain="' . esc_attr($ds) . '"'

                  . '>';

            $html .= '<div class="ouinpo-palmares-layout">';

            $html .= '<div class="ouinpo-palmares-left">';

            $html .= $img ? '<img class="ouinpo-badge-bigimg" src="' . $img . '" alt="' . $bt . '">' : '<div class="ouinpo-badge-bigimg"></div>';

            $html .= '</div>';

            

            $html .= '<div class="ouinpo-palmares-right">';

            $html .= '<div class="ouinpo-badge-title">' . $bt . '</div>';

            

            if ($desc_text !== '') {

              $html .= '<details class="ouinpo-badge-desc">';

              $html .= '<summary>&#9881;&#xfe0e; Description</summary>';

              $html .= '<div class="ouinpo-badge-desc-box">' . nl2br(esc_html($desc_text)) . '</div>';

              $html .= '</details>';

            }



            if (!empty($holders_by_badge[$bid])) {

              $count = count($holders_by_badge[$bid]);



              $html .= '<div class="ouinpo-badge-earned">';

            // ✅ Libellé du compteur (cas spécial Pr. Archibogue = user_id 2)

            $label = $count . ' élève' . ($count > 1 ? 's' : '');

            

            if ($count === 1) {

              $only_uid = (int) ($holders_by_badge[$bid][0] ?? 0);

              if ($only_uid === 2) {

                $label = '1 professeur';

              }

            }

            

            $html .= '<div class="ouinpo-palmares-count">' . esc_html($label) . '</div>';



              $html .= '<ul class="ouinpo-palmares-list">';

              foreach ($holders_by_badge[$bid] as $uid) {

                $name = $users_cache[$uid] ?? ('Élève ' . (int)$uid);

                $html .= '<li>' . esc_html($name) . '</li>';

              }

              $html .= '</ul>';

              $html .= '</div>';

            }



            $html .= '</div>'; // right

            $html .= '</div>'; // layout

            $html .= '</article>';

          }



          $html .= '</div>'; // grid

          $html .= '</div>'; // domain block

        }



        $html .= '</div>'; // level block

      }



      $html .= '</section>';

      return $html;

    };



    // ------------------------------------------------------------

    // Sortie

    // ------------------------------------------------------------

    $body  = '';

    $body .= $render_section('Badges spéciaux & méta-badges', $meta_and_special);

    $body .= $render_section('Badges de domaines',            $domain_badges);

    $body .= $render_section('Badges de compétences',         $competency);



    return $render_palmares_shell($body);

  }

  

public static function render_revision_band($atts = array(), $content = '') {

  if (!is_singular()) return '';



  wp_enqueue_style('ouinpo-exo-css');



  $post_id = get_the_ID();

  if (!$post_id) return '';



  if (!class_exists(\Ouinpo\Exercises\RevisionBand::class)) {

    return '';

  }



  $data = \Ouinpo\Exercises\RevisionBand::get_front_payload((int) $post_id);



  if (!\Ouinpo\Exercises\RevisionBand::has_visible_content($data)) {

    return '';

  }



  $exercises = !empty($data['exercises']) ? array_slice($data['exercises'], 0, 2) : [];

  $has_more  = !empty($data['intro']) || !empty($data['prereq']) || !empty($data['savoir']) || !empty($data['competencies']);



  $exercises_page = get_page_by_path('exercices');

  $exercises_url  = ($exercises_page instanceof \WP_Post)

    ? get_permalink($exercises_page)

    : home_url('/exercices/');



  $cta_url   = '';

  $cta_label = '';



  if (is_user_logged_in()) {

    if (!empty($data['path_url'])) {

      $cta_url   = $data['path_url'];

      $cta_label = 'Créer un parcours ciblé';

    }

  } else {

    $cta_url   = $exercises_url;

    $cta_label = 'Voir la banque d’exercices';

  }



  $show_side = !empty($exercises) || $cta_url !== '';



  ob_start();

  ?>

  <div class="ouinpo-revision-band">



    <div class="ouinpo-revision-band__top">



      <div class="ouinpo-revision-band__main">

        <div class="ouinpo-revision-band__title">

          <span class="ouinpo-revision-band__title-icon">🧭</span>

          <span class="ouinpo-revision-band__title-text">Repères de révision</span>

        </div>



        <?php if (!empty($data['level_label']) || !empty($data['theme_label'])): ?>

          <ul class="ouinpo-revision-band__meta">

            <?php if (!empty($data['level_label'])): ?>

              <li class="ouinpo-revision-band__chip"><?php echo esc_html($data['level_label']); ?></li>

            <?php endif; ?>

            <?php if (!empty($data['theme_label'])): ?>

              <li class="ouinpo-revision-band__chip"><?php echo esc_html($data['theme_label']); ?></li>

            <?php endif; ?>

          </ul>

        <?php endif; ?>



        <?php if (!empty($data['retenir'])): ?>

          <p class="ouinpo-revision-band__retenir">

            <span class="ouinpo-revision-band__retenir-label">À retenir :</span>

            <?php echo esc_html($data['retenir']); ?>

          </p>

        <?php endif; ?>

      </div>



      <?php if ($show_side): ?>

        <div class="ouinpo-revision-band__side">

          <span class="ouinpo-revision-band__side-title">Pour continuer</span>

          <ul class="ouinpo-revision-band__links">

            <?php foreach ($exercises as $exo): ?>

              <li>

                <a href="<?php echo esc_url($exo['url']); ?>">

                  <?php echo esc_html($exo['title']); ?>

                </a>

              </li>

            <?php endforeach; ?>

          </ul>

          <?php if ($cta_url !== ''): ?>

            <div class="ouinpo-revision-band__path">

              <a class="ouinpo-revision-band__path-link" href="<?php echo esc_url($cta_url); ?>">

                <?php echo esc_html($cta_label); ?>

              </a>

            </div>

          <?php endif; ?>

        </div>

      <?php endif; ?>



    </div>



    <?php if ($has_more): ?>

      <details class="ouinpo-revision-band__more">

        <summary>Voir les prérequis et objectifs</summary>



        <div class="ouinpo-revision-band__more-grid">



          <?php if (!empty($data['intro'])): ?>

            <div class="ouinpo-revision-band__box ouinpo-revision-band__full">

              <span class="ouinpo-revision-band__box-title">Repère</span>

              <div><?php echo esc_html($data['intro']); ?></div>

            </div>

          <?php endif; ?>



          <?php if (!empty($data['prereq'])): ?>

            <div class="ouinpo-revision-band__box">

              <span class="ouinpo-revision-band__box-title">Prérequis</span>

              <ul class="ouinpo-revision-band__list">

                <?php foreach ($data['prereq'] as $item): ?>

                  <li><?php echo esc_html($item); ?></li>

                <?php endforeach; ?>

              </ul>

            </div>

          <?php endif; ?>



          <?php if (!empty($data['savoir'])): ?>

            <div class="ouinpo-revision-band__box">

              <span class="ouinpo-revision-band__box-title">Tu vas apprendre à</span>

              <ul class="ouinpo-revision-band__list">

                <?php foreach ($data['savoir'] as $item): ?>

                  <li><?php echo esc_html($item); ?></li>

                <?php endforeach; ?>

              </ul>

            </div>

          <?php endif; ?>



          <?php if (!empty($data['competencies'])): ?>

            <div class="ouinpo-revision-band__box ouinpo-revision-band__full">

              <span class="ouinpo-revision-band__box-title">Compétences BO liées</span>

              <ul class="ouinpo-revision-band__list">

                <?php foreach ($data['competencies'] as $comp): ?>

                  <li><?php echo esc_html($comp['competency']); ?></li>

                <?php endforeach; ?>

              </ul>

            </div>

          <?php endif; ?>



        </div>

      </details>

    <?php endif; ?>



  </div>

  <?php

  return ob_get_clean();

}    

    

}

