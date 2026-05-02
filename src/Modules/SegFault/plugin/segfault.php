<?php

// Module interne OuInPo Suite : SegFault Chat.



if (!defined('ABSPATH')) exit;



// --- Autoload manuel pour Smalot\PdfParser (sans Composer)

spl_autoload_register(function ($class) {

  if (strpos($class, 'Smalot\\PdfParser') === 0) {

    $relative = str_replace('\\', '/', substr($class, strlen('Smalot\\PdfParser\\')));

    $path = __DIR__ . '/libs/pdfparser/src/Smalot/PdfParser/' . $relative . '.php';

    if (file_exists($path)) require_once $path;

  }

});



define('OUINPO_SF_DIR', plugin_dir_path(__FILE__));

define('OUINPO_SF_URL', plugin_dir_url(__FILE__));



require_once OUINPO_SF_DIR.'includes/Storage.php';



define('OUINPO_SF_DATA_DIR', \OuInPo\SegFault\Storage::data_dir());

define('OUINPO_SF_DB', \OuInPo\SegFault\Storage::db_path());

define('OUINPO_SF_SRC', \OuInPo\SegFault\Storage::sources_dir());





require_once OUINPO_SF_DIR.'includes/DB.php';

require_once OUINPO_SF_DIR.'includes/Persona.php';

require_once OUINPO_SF_DIR.'includes/OpenAI.php';

require_once OUINPO_SF_DIR.'includes/Albert.php';

require_once OUINPO_SF_DIR.'includes/RAG.php';

require_once OUINPO_SF_DIR.'admin.php';

require_once OUINPO_SF_DIR.'includes/student-parcours.php';



\OuInPo\SegFault\Storage::ensure_dirs();

\OuInPo\SegFault\Storage::migrate_legacy_assets();



// --- Markdown → HTML avec Parsedown ------------------------------------------

require_once __DIR__ . '/libs/parsedown/Parsedown.php';



if (!function_exists('ouinpo_sf_markdown_to_html')) {

  function ouinpo_sf_markdown_to_html(string $text): string {

    static $parsedown = null;

    if ($parsedown === null) {

      $parsedown = new Parsedown();

      if (method_exists($parsedown, 'setSafeMode')) $parsedown->setSafeMode(true);

    }

    $html = $parsedown->text($text);

    $html = str_replace('<pre><code>', '<pre><code class="ouinpo-code">', $html);

    return $html;

  }

}



// === Exercices / Reco =========================================================

if (!defined('OUINPO_SEGFAULT_EXO_MAX')) define('OUINPO_SEGFAULT_EXO_MAX', 5);



// -----------------------------------------------------------------------------

// Helpers généraux

// -----------------------------------------------------------------------------

function ouinpo_sf_is_internal(string $url): bool {

  $site = parse_url(site_url(), PHP_URL_HOST);

  $h    = parse_url($url, PHP_URL_HOST);

  return $site && $h && (strcasecmp($site, $h) === 0);

}



function ouinpo_sf_is_internal_post(string $url): bool {

  $pid = url_to_postid($url);

  if (!$pid) return false;

  if (get_post_status($pid) !== 'publish') return false;

  $type = get_post_type($pid);

  return in_array($type, ['post','page','exercice'], true);

}



function ouinpo_sf_table_exists(string $table): bool {

  static $cache = [];



  if (isset($cache[$table])) {

    return $cache[$table];

  }



  global $wpdb;



  $cache[$table] = ($wpdb->get_var(

    $wpdb->prepare("SHOW TABLES LIKE %s", $table)

  ) === $table);



  return $cache[$table];

}



function ouinpo_sf_table_columns(string $table): array {

  static $cache = [];



  if (isset($cache[$table])) {

    return $cache[$table];

  }



  global $wpdb;



  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0) ?: [];

  $cache[$table] = array_map('strval', $cols);



  return $cache[$table];

}



function ouinpo_sf_table_has_column(string $table, string $col): bool {

  return in_array($col, ouinpo_sf_table_columns($table), true);

}



function ouinpo_sf_resolve_user_group_for_year(int $user_id, int $year_id): int {

  if ($user_id <= 0 || $year_id <= 0) return 0;



  global $wpdb;

  $table_gm = $wpdb->prefix.'ouin_exo_group_members';

  $table_g  = $wpdb->prefix.'ouin_exo_groups';



  $gid = (int)$wpdb->get_var($wpdb->prepare(

    "SELECT gm.group_id

       FROM {$table_gm} gm

       INNER JOIN {$table_g} g ON g.id = gm.group_id

      WHERE gm.user_id = %d

        AND g.year_id = %d

      ORDER BY gm.group_id ASC

      LIMIT 1",

    $user_id, $year_id

  ));



  return $gid > 0 ? $gid : 0;

}



function ouinpo_sf_group_level_slug(int $group_id, int $year_id): string {

  if ($group_id <= 0 || $year_id <= 0) return '';



  global $wpdb;

  $table_g  = $wpdb->prefix.'ouin_exo_groups';

  $table_sl = $wpdb->prefix.'ouin_exo_school_levels';



  $slug = (string)$wpdb->get_var($wpdb->prepare(

    "SELECT sl.slug

       FROM {$table_g} g

       INNER JOIN {$table_sl} sl ON sl.id = g.school_level_id

      WHERE g.id = %d

        AND g.year_id = %d

      LIMIT 1",

    $group_id, $year_id

  ));



  return strtolower(trim($slug));

}



// -----------------------------------------------------------------------------

// Suggestions auto : table + logging des exos proposés via "sources"

// -----------------------------------------------------------------------------

function ouinpo_sf_ensure_suggestions_table(): void {

  /*

   * La table {prefix}ouin_sf_suggestions est créée par

   * \Ouinpo\Suite\Core\Installer::installOrUpgradeSharedSchema()

   * à l’activation ou lors d’une montée de version.

   *

   * Ne jamais lancer dbDelta() pendant un appel REST ou le rendu d’une page.

   */

}



/**

 * Log automatique : dès que la réponse affiche des "sources" de type exercise

 * - 1 ligne par (user_id, exercise_id)

 * - shown_count++ à chaque réapparition

 */

function ouinpo_sf_log_suggested_exercises(int $user_id, string $session, string $page_url, string $q, array $sources): void {

  if ($user_id <= 0) return;

  if (empty($sources)) return;



  global $wpdb;

  $t = $wpdb->prefix . 'ouin_sf_suggestions';



  $exo_ids = [];

  foreach ($sources as $s) {

    $type = (string)($s['type'] ?? '');

    if ($type !== 'exercise') continue;



    $url = (string)($s['url'] ?? '');

    if ($url === '') continue;



    $parts = parse_url($url);

    if (empty($parts['query'])) continue;



    parse_str($parts['query'], $qs);

    if (empty($qs['exo'])) continue;



    $eid = (int)$qs['exo'];

    if ($eid > 0) $exo_ids[] = $eid;

  }



  $exo_ids = array_values(array_unique($exo_ids));

  if (!$exo_ids) return;



  $now = current_time('mysql');

  $safe_session = sanitize_text_field($session);

  $safe_page    = $page_url ? esc_url_raw($page_url) : '';

  $safe_q       = sanitize_textarea_field($q);



  foreach ($exo_ids as $eid) {

    $wpdb->query(

      $wpdb->prepare(

        "INSERT INTO {$t}

          (user_id, exercise_id, first_suggested_at, last_suggested_at, shown_count, last_session, last_page_url, last_query)

         VALUES

          (%d, %d, %s, %s, 1, %s, %s, %s)

         ON DUPLICATE KEY UPDATE

          last_suggested_at = VALUES(last_suggested_at),

          shown_count = shown_count + 1,

          last_session = VALUES(last_session),

          last_page_url = VALUES(last_page_url),

          last_query = VALUES(last_query)",

        $user_id, $eid, $now, $now, $safe_session, $safe_page, $safe_q

      )

    );

  }

}



// -----------------------------------------------------------------------------

// Sources (liens sous la réponse)

// -----------------------------------------------------------------------------

function ouinpo_sf_build_sources(array $chunks, int $limit = 5): array {

  global $wpdb;



  $seen    = [];

  $courses = [];

  $exos    = [];

  $others  = [];



  foreach ($chunks as $c) {

    $score = isset($c['score']) ? (float)$c['score'] : null;



    // Si un score existe et qu'il est vraiment faible, on ignore.

    // Les valeurs dépendent du modèle, donc seuil volontairement prudent.

    if ($score !== null && $score < 0.25) {

      continue;

    }

    $url = (string)($c['url'] ?? '');

    if ($url === '') $url = (string)($c['link'] ?? '');

    if ($url === '') $url = (string)($c['permalink'] ?? '');

    if ($url === '') continue;



    if (!empty($c['is_current'])) continue; // jamais la page courante



    $title = (string)($c['title'] ?? '');

    if ($title === '') $title = (string)($c['post_title'] ?? '');

    if ($title === '') $title = $url;



    $ptype = (string)($c['ptype'] ?? ($c['type'] ?? ($c['origin'] ?? '')));

    $ptype = strtolower(trim($ptype));



    // Normalisation URL exercice (force /exercice/?exo=<id>)

    if ($ptype === 'exercise') {

      $exo_id = 0;



      if (isset($c['exo_id'])) $exo_id = (int)$c['exo_id'];

      elseif (isset($c['id'])) $exo_id = (int)$c['id'];



      if ($exo_id <= 0 && strpos($url, 'exo=') !== false) {

        $parts = parse_url($url);

        parse_str($parts['query'] ?? '', $qs);

        if (!empty($qs['exo'])) {

          $exo_raw = $qs['exo'];

          if (ctype_digit((string)$exo_raw)) {

            $exo_id = (int)$exo_raw;

          } else {

            $table_exo = $wpdb->prefix.'ouin_exo_exercises';

            $found_id = (int)$wpdb->get_var($wpdb->prepare(

              "SELECT id FROM {$table_exo} WHERE slug = %s LIMIT 1", $exo_raw

            ));

            if ($found_id > 0) $exo_id = $found_id;

          }

        }

      }



      if ($exo_id > 0) $url = home_url('/exercice/?exo='.$exo_id);

    }



    // Filtre contenus internes non-exos : on évite juste /exercice sans param

    if (ouinpo_sf_is_internal($url) && $ptype !== 'exercise') {

      $parts = parse_url($url);

      $path  = rtrim($parts['path'] ?? '', '/');

      parse_str($parts['query'] ?? '', $qs);

      if ($path === '/exercice' && empty($qs['exo'])) continue;

    }



    if (isset($seen[$url])) continue;

    $seen[$url] = true;



    $item = ['title' => $title, 'url' => $url];

    if ($ptype) $item['type'] = $ptype;



    if ($ptype === 'exercise') {

      $item['badge'] = 'Exercice';

      $exos[] = $item;

    } elseif (ouinpo_sf_is_internal($url) && in_array($ptype, ['post','page'], true)) {

      $courses[] = $item;

    } else {

      $others[] = $item;

    }

  }



  // -----------------------------

  // ✅ Quotas : toujours 2 exos si possible

  // -----------------------------

$limit = max(1, (int)$limit);



$out = [];



// 1) Priorité aux cours/pages réellement retrouvés.

foreach ($courses as $c) {

  $out[] = $c;

  if (count($out) >= $limit) return $out;

}



// 2) Ensuite seulement les exercices.

foreach ($exos as $e) {

  $out[] = $e;

  if (count($out) >= $limit) return $out;

}



// 3) Puis le reste éventuel.

foreach ($others as $o) {

  $out[] = $o;

  if (count($out) >= $limit) return $out;

}



return $out;

}



function ouinpo_sf_sources_to_answer_html(array $sources): string {

  if (empty($sources)) return '';



  $courses = [];

  $exos = [];



  foreach ($sources as $s) {

    if (empty($s['url']) || empty($s['title'])) continue;



    $type = strtolower((string)($s['type'] ?? $s['ptype'] ?? 'post'));



    if ($type === 'exercise') {

      $exos[] = $s;

    } else {

      $courses[] = $s;

    }

  }



  $html = '';



  if (!empty($courses)) {

    $html .= '<div class="sf-inline-suggestions sf-inline-courses">';

    $html .= '<p><strong>📚 Cours à consulter</strong></p>';

    $html .= '<ul>';



    foreach (array_slice($courses, 0, 3) as $c) {

      $html .= '<li><a href="' . esc_url($c['url']) . '" target="_blank" rel="nofollow noopener">'

        . esc_html($c['title']) . '</a></li>';

    }



    $html .= '</ul>';

    $html .= '</div>';

  }



  if (!empty($exos)) {

    $html .= '<div class="sf-inline-suggestions sf-inline-exercises">';

    $html .= '<p><strong>🧪 Exercices conseillés</strong></p>';

    $html .= '<ul>';



    foreach (array_slice($exos, 0, 3) as $e) {

      $html .= '<li><a href="' . esc_url($e['url']) . '" target="_blank" rel="nofollow noopener">'

        . esc_html($e['title']) . '</a></li>';

    }



    $html .= '</ul>';

    $html .= '</div>';

  }



  return $html;

}



// -----------------------------------------------------------------------------

// Page courante -> chunk de contexte

// -----------------------------------------------------------------------------

function ouinpo_sf_build_current_page_chunk(string $url): ?array {

  if (!ouinpo_sf_is_internal($url)) return null;



  global $wpdb;

  $parts = parse_url($url);

  parse_str($parts['query'] ?? '', $qs);



  // Exercice via ?exo=

  if (!empty($qs['exo'])) {

    $exo_raw = $qs['exo'];

    $table_exo = $wpdb->prefix.'ouin_exo_exercises';



    $row = null;

    if (ctype_digit((string)$exo_raw)) {

      $row = $wpdb->get_row($wpdb->prepare(

        "SELECT id, title, statement FROM {$table_exo} WHERE id = %d LIMIT 1", (int)$exo_raw

      ));

    } else {

      $row = $wpdb->get_row($wpdb->prepare(

        "SELECT id, title, statement FROM {$table_exo} WHERE slug = %s LIMIT 1", $exo_raw

      ));

    }



    if ($row) {

      return [

        'title'      => $row->title ?: 'Exercice',

        'url'        => home_url('/exercice/?exo='.$row->id),

        'chunk'      => wp_strip_all_tags($row->statement ?? ''),

        'ptype'      => 'exercise',

        'exo_id'     => (int)$row->id,

        'is_current' => true,

      ];

    }

  }



  // Contenu WP

  $pid = url_to_postid($url);

  if ($pid) {

    $post = get_post($pid);

    if ($post && $post->post_status === 'publish') {

      $html = apply_filters('the_content', $post->post_content);

      return [

        'title'      => get_the_title($post),

        'url'        => get_permalink($post),

        'chunk'      => wp_trim_words(wp_strip_all_tags($html), 400, '…'),

        'ptype'      => get_post_type($post) ?: 'post',

        'is_current' => true,

      ];

    }

  }



  return null;

}



// -----------------------------------------------------------------------------

// Profil BO / exercices

// -----------------------------------------------------------------------------

function ouinpo_sf_get_user_competencies_profile(int $user_id, ?int $year_id = null): ?array {

  if ($user_id <= 0) return null;



  global $wpdb;

  $table_uc   = $wpdb->prefix.'ouin_exo_user_competencies';

  $table_comp = $wpdb->prefix.'ouin_exo_competencies';

  $table_t    = $wpdb->prefix.'ouin_exo_competency_teaching';



  if ($year_id === null) {

    $year_id = (int)$wpdb->get_var($wpdb->prepare(

      "SELECT MAX(year_id) FROM {$table_uc} WHERE user_id = %d",

      $user_id

    ));

    if ($year_id === 0) return null;

  }



  $group_id = ouinpo_sf_resolve_user_group_for_year($user_id, $year_id);

  if ($group_id <= 0) return null;



  $rows = $wpdb->get_results($wpdb->prepare(

    "SELECT

        uc.competency_id,

        uc.year_id,

        uc.group_id,

        uc.status,

        c.slug AS comp_code,

        c.competency AS comp_label

     FROM {$table_uc} uc

     INNER JOIN {$table_t} t

       ON t.year_id = uc.year_id

      AND t.group_id = uc.group_id

      AND t.competency_id = uc.competency_id

      AND t.teaching_state = 'seen'

     LEFT JOIN {$table_comp} c

       ON c.id = uc.competency_id

     WHERE uc.user_id = %d

       AND uc.year_id = %d

       AND uc.group_id = %d

       AND LOWER(c.level) <> 'transversal'",

    $user_id,

    $year_id,

    $group_id

  ), ARRAY_A);



  if (empty($rows)) return null;



  $status_map = [

    'not_acquired'  => ['level'=>0,'label'=>'Non acquis'],

    'in_progress'   => ['level'=>1,'label'=>'En progression'],

    'consolidating' => ['level'=>2,'label'=>'En consolidation'],

    'acquired'      => ['level'=>3,'label'=>'Acquis'],

  ];



  $competencies = [];

  foreach ($rows as $row) {

    $s = $row['status'] ?? 'not_acquired';

    $map = $status_map[$s] ?? $status_map['not_acquired'];



    $code  = trim((string)($row['comp_code'] ?? ''));

    $label = trim((string)($row['comp_label'] ?? ''));



    $display = ($code !== '' && $label !== '')

      ? ($code . ' — ' . $label)

      : ($label ?: ($code ?: 'Compétence #' . $row['competency_id']));



    $competencies[] = [

      'competency_id' => (int)$row['competency_id'],

      'year_id'       => (int)$row['year_id'],

      'group_id'      => (int)$row['group_id'],

      'status'        => $s,

      'level'         => $map['level'],

      'level_label'   => $map['label'],

      'display'       => $display,

    ];

  }



  return [

    'user_id'      => $user_id,

    'year_id'      => (int)$year_id,

    'group_id'     => (int)$group_id,

    'competencies' => $competencies,

  ];

}



function ouinpo_sf_get_user_competency_trends(int $user_id, ?int $year_id = null, ?int $group_id = null): array {

  if ($user_id <= 0) return [];



  global $wpdb;



  $tblA = $wpdb->prefix.'ouin_exo_assessments';

  $tblR = $wpdb->prefix.'ouin_exo_assessment_results';



  if ($year_id === null || $group_id === null) {

    $profile = ouinpo_sf_get_user_competencies_profile($user_id, $year_id);

    if (!$profile) return [];

    $year_id  = (int)($profile['year_id'] ?? 0);

    $group_id = (int)($profile['group_id'] ?? 0);

  }



  if ($year_id <= 0 || $group_id <= 0) return [];



  $rows = $wpdb->get_results($wpdb->prepare(

    "SELECT

        r.competency_id,

        a.id AS assessment_id,

        a.due_on,

        r.observed_status,

        r.updated_at

     FROM {$tblR} r

     INNER JOIN {$tblA} a ON a.id = r.assessment_id

     WHERE r.user_id = %d

       AND a.year_id = %d

       AND a.group_id = %d

     ORDER BY a.due_on DESC, r.assessment_id DESC, r.updated_at DESC",

    $user_id,

    $year_id,

    $group_id

  ), ARRAY_A);



  if (empty($rows)) return [];



  $rank = static function(string $status): int {

    return match ($status) {

      'in_progress'   => 1,

      'consolidating' => 2,

      'acquired'      => 3,

      default         => 0,

    };

  };



  $by_comp = [];

  foreach ($rows as $row) {

    $cid = (int)$row['competency_id'];

    if (!isset($by_comp[$cid])) $by_comp[$cid] = [];

    $by_comp[$cid][] = $row;

  }



  $out = [];

  foreach ($by_comp as $cid => $history) {

    $current = (string)($history[0]['observed_status'] ?? 'not_acquired');

    $prev    = (string)($history[1]['observed_status'] ?? '');



    $trend = 'new';

    if ($prev !== '') {

      $delta = $rank($current) - $rank($prev);

      if ($delta > 0) $trend = 'up';

      elseif ($delta < 0) $trend = 'down';

      else $trend = ($current === 'acquired') ? 'confirmed' : 'stable';

    }



    $out[$cid] = [

      'current_status' => $current,

      'trend'          => $trend,

      'history'        => array_slice($history, 0, 3),

    ];

  }



  return $out;

}



function ouinpo_sf_get_user_exercise_status_profile(int $user_id): ?array {

  if ($user_id <= 0) return null;



  global $wpdb;

  $table_status = $wpdb->prefix.'ouin_exo_user_status';

  $table_exo    = $wpdb->prefix.'ouin_exo_exercises';



  $rows = $wpdb->get_results($wpdb->prepare(

    "SELECT us.exercise_id, us.status, ex.title AS exo_title

     FROM {$table_status} us

     LEFT JOIN {$table_exo} ex ON ex.id = us.exercise_id

     WHERE us.user_id = %d",

    $user_id

  ), ARRAY_A);



  if (empty($rows)) return null;



  $attempted = [];

  $solved = [];



  foreach ($rows as $row) {

    $eid = (int)($row['exercise_id'] ?? 0);

    $st  = $row['status'] ?? 'none';

    if ($eid <= 0 || $st === 'none') continue;



    $title = trim((string)($row['exo_title'] ?? '')) ?: ('Exercice '.$eid);



    if ($st === 'attempted') $attempted[$eid] = ['id'=>$eid,'title'=>$title];

    elseif ($st === 'solved') $solved[$eid] = ['id'=>$eid,'title'=>$title];

  }



  if (empty($attempted) && empty($solved)) return null;



  return [

    'user_id'   => $user_id,

    'attempted' => array_values($attempted),

    'solved'    => array_values($solved),

  ];

}



// -----------------------------------------------------------------------------

// Scaling niveau élève + domaines visibles (évite domaines d’autres niveaux)

// -----------------------------------------------------------------------------

function ouinpo_sf_user_nsi_level(int $user_id, ?int $year_id = null): string {

  $m = strtolower(trim(remove_accents((string)get_user_meta($user_id, 'nsi_level', true))));

  if (in_array($m, ['seconde', 'premiere', 'terminale'], true)) return $m;



  global $wpdb;

  $table_uc = $wpdb->prefix.'ouin_exo_user_competencies';



  if ($year_id === null) {

    $year_id = (int)$wpdb->get_var($wpdb->prepare(

      "SELECT MAX(year_id) FROM {$table_uc} WHERE user_id = %d",

      $user_id

    ));

  }



  if (!$year_id) return 'premiere';



  $group_id = ouinpo_sf_resolve_user_group_for_year($user_id, $year_id);



  if ($group_id > 0) {

    $slug = ouinpo_sf_group_level_slug($group_id, $year_id);



    if ($slug === 'seconde' || $slug === '2nde') return 'seconde';

    if ($slug === 'premiere' || $slug === 'première' || $slug === '1ere' || $slug === '1ère') return 'premiere';

    if ($slug === 'terminale' || $slug === 'term') return 'terminale';

  }



  return 'premiere';

}



function ouinpo_sf_allowed_level_slugs(string $level): array {

  $level = strtolower(trim(remove_accents($level)));



  if ($level === 'terminale' || $level === 'term') {

    return ['seconde', 'premiere', 'terminale'];

  }



  if ($level === 'premiere' || $level === '1ere' || $level === '1ere') {

    return ['seconde', 'premiere'];

  }



  if ($level === 'seconde' || $level === '2nde') {

    return ['seconde'];

  }



  return ['seconde'];

}



function ouinpo_sf_allowed_competency_levels(string $level): array {

  $level = strtolower(trim(remove_accents($level)));



  if ($level === 'terminale' || $level === 'term') {

    return ['Seconde', 'Première', 'Terminale', 'Transversal'];

  }



  if ($level === 'premiere' || $level === '1ere' || $level === '1ere') {

    return ['Seconde', 'Première', 'Transversal'];

  }



  if ($level === 'seconde' || $level === '2nde') {

    return ['Seconde', 'Transversal'];

  }



  return ['Seconde', 'Transversal'];

}



function ouinpo_sf_current_competency_level_label(string $level): string {

  $level = strtolower(trim(remove_accents($level)));



  if ($level === 'seconde' || $level === '2nde') return 'Seconde';

  if ($level === 'premiere' || $level === '1ere' || $level === '1ere') return 'Première';

  if ($level === 'terminale' || $level === 'term') return 'Terminale';



  return '';

}



function ouinpo_sf_list_domains_for_user(int $user_id): array {

  global $wpdb;



  $profile = ouinpo_sf_get_user_competencies_profile($user_id, null);

  if (!$profile || empty($profile['competencies'])) return [];



  $year_id = (int)($profile['year_id'] ?? 0);

  $level   = ouinpo_sf_user_nsi_level($user_id, $year_id ?: null);



  $ids = [];

  foreach ($profile['competencies'] as $c) $ids[] = (int)($c['competency_id'] ?? 0);

  $ids = array_values(array_unique(array_filter($ids)));

  if (empty($ids)) return [];



  $table_comp = $wpdb->prefix.'ouin_exo_competencies';

  $domain_col = null;



  if (ouinpo_sf_table_has_column($table_comp, 'domain_slug')) $domain_col = 'domain_slug';

  elseif (ouinpo_sf_table_has_column($table_comp, 'domain'))  $domain_col = 'domain';

  else return [];



  $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    $allowed_levels = ouinpo_sf_allowed_competency_levels($level);

    $level_placeholders = implode(',', array_fill(0, count($allowed_levels), '%s'));

    

    $params = array_merge($ids, $allowed_levels);

    

    $sql = "

      SELECT DISTINCT {$domain_col} AS d

      FROM {$table_comp}

      WHERE id IN ({$placeholders})

        AND {$domain_col} IS NOT NULL AND {$domain_col} <> ''

        AND level IN ({$level_placeholders})

      ORDER BY {$domain_col} ASC

    ";



  $rows = $wpdb->get_col($wpdb->prepare($sql, ...$params));



  $out = [];

  

  foreach ($rows as $d) {

    $d = trim((string)$d);

    if ($d === '') continue;

    $out[] = ['value'=>$d, 'label'=>$d];

  }

  return $out;

}



// -----------------------------------------------------------------------------

// Difficulté (via difficulty_id + table {prefix}exo_difficulties)

// -----------------------------------------------------------------------------

function ouinpo_sf_allowed_difficulty_ids_for_level(int $lvl): array {

  if ($lvl <= 0) return [1];

  if ($lvl === 1) return [1, 2];

  if ($lvl === 2) return [2, 3];

  return [3];

}

function ouinpo_sf_difficulty_rank_from_id(int $difficulty_id): int {

  if ($difficulty_id === 1) return 0;

  if ($difficulty_id === 2) return 1;

  if ($difficulty_id === 3) return 2;

  return 99;

}



// -----------------------------------------------------------------------------

// Assets

// -----------------------------------------------------------------------------

add_action('wp_enqueue_scripts', function () {

  $sf_css_rel = 'assets/css/front/segfault.css';
  $sf_css_ver = defined('OUINPO_SUITE_DIR') && file_exists(OUINPO_SUITE_DIR . $sf_css_rel)
      ? (string) filemtime(OUINPO_SUITE_DIR . $sf_css_rel)
      : '1.1.6';

  wp_enqueue_style(
      'ouinpo-sf',
      defined('OUINPO_SUITE_URL') ? OUINPO_SUITE_URL . $sf_css_rel : OUINPO_SF_URL . 'assets/segfault.css',
      [],
      $sf_css_ver
  );

  wp_enqueue_script('ouinpo-sf', OUINPO_SF_URL.'assets/segfault.js', [], '1.1.6', true);

  wp_localize_script('ouinpo-sf', 'OUINPO_SF', [

    'rest'         => esc_url_raw(rest_url('ouinpo-segfault/v1/chat')),

    'public_rest'  => esc_url_raw(rest_url('ouinpo-segfault/v1/public-chat')),

    'memory_rest'  => esc_url_raw(rest_url('ouinpo-segfault/v1/memory/clear')),

    'nonce'        => wp_create_nonce('wp_rest'),

    'is_logged_in' => is_user_logged_in() ? 1 : 0,

    'public_ai'    => (class_exists('\\OuInPo\\SegFault\\Albert') && \OuInPo\SegFault\Albert::public_available()) ? 1 : 0,

  ]);

});



if (!function_exists('ouinpo_sf_ai_notice_url')) {
  function ouinpo_sf_ai_notice_url(): string {
    $raw = trim((string) get_option(
      'ouinpo_sf_ai_notice_url',
      '/donnees-personnelles-ia-et-usages-pedagogiques-sur-ouinpo/'
    ));

    if ($raw === '') {
      return '';
    }

    if (preg_match('#^https?://#i', $raw)) {
      return esc_url($raw);
    }

    return esc_url(home_url($raw));
  }
}

if (!function_exists('ouinpo_sf_ai_notice_html')) {
  function ouinpo_sf_ai_notice_html(): string {
    $is_public = !is_user_logged_in();

    $default_public = "Assistant IA public — N’écris pas de nom, prénom, note, adresse ou information personnelle. Les réponses peuvent contenir des erreurs.";
    $default_logged = "IA pédagogique — N’écris pas de données personnelles. Les réponses proposées par l’assistant doivent être vérifiées et ne remplacent pas le professeur.";

    $option_name = $is_public
      ? 'ouinpo_sf_ai_notice_public'
      : 'ouinpo_sf_ai_notice_logged';

    $default_text = $is_public
      ? $default_public
      : $default_logged;

    $text = (string) get_option($option_name, $default_text);
    $text = trim($text);

    if ($text === '') {
      $text = $default_text;
    }

    $url = ouinpo_sf_ai_notice_url();

    $html = '
      <div class="sf-ai-notice sf-ai-notice--compact" role="note" aria-label="Information sur l’usage de l’intelligence artificielle">
        <div class="sf-ai-notice__text">'
          . wp_kses_post(wpautop($text)) .
        '</div>';

    if ($url !== '') {
      $html .= '
        <p class="sf-ai-notice__link">
          <a href="' . esc_url($url) . '">En savoir plus</a>
        </p>';
    }

    $html .= '
      </div>';

    return $html;
  }
}



// -----------------------------------------------------------------------------

// SegFault public via Albert API : visiteurs anonymes, sans mémoire

// -----------------------------------------------------------------------------

function ouinpo_sf_public_albert_enabled(): bool {

  return class_exists('\\OuInPo\\SegFault\\Albert')

    && \OuInPo\SegFault\Albert::public_available();

}



function ouinpo_sf_public_client_hash(): string {

  $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

  $salt = defined('AUTH_SALT') ? AUTH_SALT : wp_salt('auth');

  return substr(hash('sha256', $ip . '|' . $salt), 0, 24);

}



function ouinpo_sf_public_quota_check() {

  $hourly = max(1, (int) get_option('ouinpo_sf_public_hourly_limit', 5));

  $daily  = max(1, (int) get_option('ouinpo_sf_public_daily_limit', 100));

  $hash   = ouinpo_sf_public_client_hash();



  $hour_key = 'ouinpo_sf_pub_ai_h_' . gmdate('YmdH') . '_' . $hash;

  $day_key  = 'ouinpo_sf_pub_ai_d_' . gmdate('Ymd');



  $h = (int) get_transient($hour_key);

  if ($h >= $hourly) {

    return new \WP_Error('quota_hour', 'Limite atteinte pour cette heure. Réessaie un peu plus tard.', ['status' => 429]);

  }



  $d = (int) get_transient($day_key);

  if ($d >= $daily) {

    return new \WP_Error('quota_day', 'Limite quotidienne du chat public atteinte.', ['status' => 429]);

  }



  set_transient($hour_key, $h + 1, HOUR_IN_SECONDS + 120);

  set_transient($day_key,  $d + 1, DAY_IN_SECONDS + 120);



  return true;

}



function ouinpo_sf_public_sanitize_message($value): string {

  $q = is_string($value) ? $value : '';

  $q = wp_check_invalid_utf8($q);

  $q = str_replace("\0", '', $q);

  $q = trim($q);

  if (function_exists('mb_strlen') && mb_strlen($q) > 1200) {

    $q = mb_substr($q, 0, 1200);

  } elseif (strlen($q) > 3000) {

    $q = substr($q, 0, 3000);

  }

  return $q;

}



function ouinpo_sf_public_system_prompt(): string {

  return "Tu es SegFault, l’assistant public du site OuInPo.\n"

    . "Tu aides à comprendre les notions de SNT et de NSI pour des élèves de lycée.\n"

    . "Réponds en français clair, en 10 lignes maximum sauf demande explicite de développement.\n"

    . "Explique progressivement, avec un exemple simple si utile.\n"

    . "Ne demande jamais de données personnelles.\n"

    . "Ne prétends jamais accéder au compte, aux notes, à la classe, aux badges ou à la progression d’un élève.\n"

    . "Si l’utilisateur fournit une donnée personnelle, demande-lui de la retirer et réponds de façon générale.\n"

    . "Si la demande ressemble à un devoir ou une évaluation à rendre, donne une piste ou une méthode, sans fournir une correction complète.\n"

    . "Reste dans le cadre SNT/NSI, programmation, algorithmique, web, réseaux, données et informatique.";

}



// -----------------------------------------------------------------------------

// Shortcodes

// -----------------------------------------------------------------------------

add_shortcode('segfault_chat', function () {

  $members_only = (int)get_option('ouinpo_sf_members_only', 0);

  if ($members_only && !is_user_logged_in()) return '';



  ob_start(); ?>

  <div id="sf-chat-inline" class="ouinpo-sf-widget">

    <div class="sf-header">😼 SegFault — Chassistant</div>

    <?php echo ouinpo_sf_ai_notice_html(); ?>

    <div class="sf-messages"></div>

    <div class="sf-input">

      <textarea rows="3" placeholder="Pose ta question NSI... (Entrée = envoyer, Maj+Entrée = nouvelle ligne)"></textarea>

      <button type="button">Envoyer</button>

    </div>

    <?php if (is_user_logged_in()) : ?>

      <div class="sf-footer">

        <label><input type="checkbox" id="sf-consent" checked /> Mémoriser cet échange 30 jours</label>

        <button type="button" id="sf-clear">Effacer la mémoire</button>

      </div>

    <?php else : ?>

      <div class="sf-footer sf-public-footer">Assistant public sans mémoire de conversation.</div>

    <?php endif; ?>

  </div>

  <?php return ob_get_clean();

});



add_shortcode('segfault_parcours', function ($atts) {

  $members_only = (int)get_option('ouinpo_sf_members_only', 0);

  if ($members_only && !is_user_logged_in()) return '';

  if (!is_user_logged_in()) return '<p>Connecte-toi pour voir ton parcours.</p>';



  $limit = isset($atts['limit']) ? (int)$atts['limit'] : 8;

  if ($limit < 1)  $limit = 1;

  if ($limit > 12) $limit = 12;



  ob_start(); ?>

  <div id="sf-parcours" data-limit="<?php echo esc_attr($limit); ?>">

    <h1>Mon parcours conseillé</h1>

    <p>

      Priorité aux compétences les plus urgentes. Progressif (difficulté adaptée à ton niveau).

      Les exercices déjà réussis ne sont pas reproposés.

    </p>



    <div class="sf-parcours-filter" style="display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; margin: 1rem 0;">

      <label for="sf-domain"><strong>Domaine :</strong></label>

      <select id="sf-domain">

        <option value="">Tous</option>

      </select>

      <span id="sf-parcours-status" style="opacity:.85;"></span>

    </div>



    <div id="sf-parcours-list"><p>Chargement du parcours…</p></div>

  </div>

  <?php

  return ob_get_clean();

});



function ouinpo_sf_is_generic_exercise_followup(string $q): bool {

  $q_norm = strtolower(remove_accents($q));

  $q_norm = preg_replace('/\s+/u', ' ', trim($q_norm));



  $asks_exercise = (bool)preg_match(

    '/\b(exercice|exercices|exo|exos|entrainement|entrainements|entrainement|revision|reviser)\b/u',

    $q_norm

  );



  if (!$asks_exercise) return false;



  // Si l'élève précise déjà le sujet, ce n'est pas une reprise implicite.

  if (preg_match('/\b(sur|concernant|a propos|au sujet|portant sur|plutot|plutôt)\b/u', $q_norm)) {

    return false;

  }



  return true;

}



function ouinpo_sf_last_user_turn_before_current(string $session, string $current_q): string {

  if (

    !class_exists('\OuInPo\SegFault\DB')

    || !method_exists('\OuInPo\SegFault\DB', 'last_turns')

  ) {

    return '';

  }



  $recent = \OuInPo\SegFault\DB::last_turns($session, 8);

  if (!is_array($recent) || empty($recent)) return '';



  $current_norm = trim($current_q);



  for ($i = count($recent) - 1; $i >= 0; $i--) {

    $t = $recent[$i];



    if (($t['role'] ?? '') !== 'user') continue;



    $content = trim((string)($t['content'] ?? ''));

    if ($content === '') continue;

    if ($content === $current_norm) continue;



    return $content;

  }



  return '';

}



// -----------------------------------------------------------------------------

// REST endpoints : /parcours + /chat (version allégée)

// -----------------------------------------------------------------------------

add_action('rest_api_init', function () {



  // ========================= /parcours =========================

  register_rest_route('ouinpo-segfault/v1', '/parcours', [

    'methods'  => 'GET',

    'permission_callback' => function () {

      $members_only = (int)get_option('ouinpo_sf_members_only', 0);

      if ($members_only && !is_user_logged_in()) return false;

      return is_user_logged_in();

    },

    'callback' => function (\WP_REST_Request $req) {



      $user_id = get_current_user_id();

      $limit = (int)($req->get_param('limit') ?? 8);

      if ($limit < 1)  $limit = 1;

      if ($limit > 12) $limit = 12;



      $domain = sanitize_text_field((string)($req->get_param('domain') ?? ''));



      global $wpdb;

      ouinpo_sf_ensure_suggestions_table();



      $table_exo  = $wpdb->prefix.'ouin_exo_exercises';

      $table_comp = $wpdb->prefix.'ouin_exo_competencies';

      $table_link = $wpdb->prefix.'ouin_exo_exercise_competency';

      $table_diff = $wpdb->prefix.'ouin_exo_difficulties';

      $table_sugg = $wpdb->prefix.'ouin_sf_suggestions';

      $table_exam = $wpdb->prefix.'ouin_exo_exam_meta';



      // colonne domaine

      $domain_col = null;

      if (ouinpo_sf_table_has_column($table_comp, 'domain_slug')) $domain_col = 'domain_slug';

      elseif (ouinpo_sf_table_has_column($table_comp, 'domain'))  $domain_col = 'domain';



      // profil + niveau élève (premiere|terminale)

      $profile = ouinpo_sf_get_user_competencies_profile($user_id, null);

      $year_id = $profile ? (int)($profile['year_id'] ?? 0) : 0;

      $level   = ouinpo_sf_user_nsi_level($user_id, $year_id ?: null);

      $trends  = ouinpo_sf_get_user_competency_trends(

        $user_id,

        $year_id ?: null,

        $profile ? (int)($profile['group_id'] ?? 0) : null

      );



      // statuts exos

      $exo_profile = ouinpo_sf_get_user_exercise_status_profile($user_id);

      $solved_ids = [];

      $attempted_ids = [];

      if ($exo_profile && !empty($exo_profile['solved'])) {

        foreach ($exo_profile['solved'] as $ex) $solved_ids[] = (int)$ex['id'];

      }

      if ($exo_profile && !empty($exo_profile['attempted'])) {

        foreach ($exo_profile['attempted'] as $ex) $attempted_ids[] = (int)$ex['id'];

      }

      $solved_ids = array_values(array_unique(array_filter($solved_ids)));

      $attempted_ids = array_values(array_unique(array_filter($attempted_ids)));

      $solved_set = array_fill_keys($solved_ids, true);

      $attempted_set = array_fill_keys($attempted_ids, true);



      // compétences triées par urgence :

      // 1) faibles d'abord

      // 2) à faiblesse égale, celles en baisse dans les DS d'abord

      $urgent = [];

      if ($profile && !empty($profile['competencies'])) {

        foreach ($profile['competencies'] as $c) {

          $cid = (int)($c['competency_id'] ?? 0);

          $lvl = (int)($c['level'] ?? 0);

          if ($cid <= 0) continue;



          $trend = $trends[$cid]['trend'] ?? 'new';



          $trend_rank = match ($trend) {

            'down'      => 0,

            'stable'    => 1,

            'new'       => 2,

            'up'        => 3,

            'confirmed' => 4,

            default     => 2,

          };



          $urgent[] = [

            'competency_id' => $cid,

            'lvl'           => $lvl,

            'trend'         => $trend,

            'trend_rank'    => $trend_rank,

          ];

        }

      }



      usort($urgent, function($a, $b) {

        if ($a['lvl'] !== $b['lvl']) {

          return $a['lvl'] <=> $b['lvl'];

        }

        if ($a['trend_rank'] !== $b['trend_rank']) {

          return $a['trend_rank'] <=> $b['trend_rank'];

        }

        return $a['competency_id'] <=> $b['competency_id'];

      });



      // générer parcours progressif

      $cards = [];

      $seen_exo = [];

      $per_comp_take = 4;



      foreach ($urgent as $u) {

        if (count($cards) >= $limit * 6) break;



        $cid = (int)$u['competency_id'];

        $lvl = (int)$u['lvl'];



        // difficulté autorisée selon niveau (RÈGLE DEMANDÉE)

        $allowed_ids = ouinpo_sf_allowed_difficulty_ids_for_level($lvl);

        $in = implode(',', array_fill(0, count($allowed_ids), '%d'));

        $where_diff = " AND e.difficulty_id IN ($in) ";



        // filtre domaine

        $where_domain = '';

        $domain_params = [];

        if ($domain !== '' && $domain_col) {

          $where_domain = " AND c.{$domain_col} = %s ";

          $domain_params[] = $domain;

        }



        // domaine strict (évite exos “pas dans le domaine”)

        $select_domains = '';

        $having_domain = '';

        $having_params = [];

        if ($domain_col) {

          $select_domains = ", GROUP_CONCAT(DISTINCT c.{$domain_col} ORDER BY c.{$domain_col} SEPARATOR ', ') AS exo_domains";

          if ($domain !== '') {

            $having_domain = " HAVING SUM(CASE WHEN c.{$domain_col} <> %s THEN 1 ELSE 0 END) = 0 ";

            $having_params[] = $domain;

          }

        }



        // params : user_id, cid, level, [domain], allowed_ids..., [having], limit

        $allowed_comp_levels = ouinpo_sf_allowed_competency_levels($level);

        $level_placeholders = implode(',', array_fill(0, count($allowed_comp_levels), '%s'));

        

        $params = [$user_id, $cid];

        $params = array_merge($params, $allowed_comp_levels, $domain_params, $allowed_ids, $having_params);



        $sql = "

          SELECT

            e.id, e.title, e.statement,

            e.difficulty_id,

            d.slug  AS difficulty_slug,

            d.label AS difficulty_label,

            COALESCE(s.shown_count, 0) AS shown_count

            {$select_domains}

          FROM {$table_exo} e

          INNER JOIN {$table_link} ec ON ec.exercise_id = e.id

          INNER JOIN {$table_comp} c  ON c.id = ec.competency_id

          LEFT JOIN {$table_diff} d   ON d.id = e.difficulty_id

          LEFT JOIN {$table_sugg} s

            ON s.user_id = %d

           AND s.exercise_id = e.id

          LEFT JOIN {$table_exam} em

            ON em.exercise_id = e.id

          WHERE ec.competency_id = %d

            AND c.level IN ({$level_placeholders})

            AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')

            {$where_domain}

            {$where_diff}

          GROUP BY e.id

          {$having_domain}

          ORDER BY e.id DESC

          LIMIT %d

        ";



        $params[] = $per_comp_take * 8;



        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        if (empty($rows)) continue;



        // scoring local : progressif d’abord, puis moins souvent suggéré,

        // puis tenté avant nouveau ; les exos déjà réussis sont exclus

        $local = [];

        foreach ($rows as $r) {

          $eid = (int)($r['id'] ?? 0);

          if ($eid <= 0) continue;

          if (isset($seen_exo[$eid])) continue;

          if (isset($solved_set[$eid])) continue;



          $status = 'new';

          $badge  = '🆕 Nouveau';

          if (isset($attempted_set[$eid])) {

            $status = 'attempted';

            $badge  = '🟡 Tenté';

          }



          $did = (int)($r['difficulty_id'] ?? 0);

          $diff_rank = ouinpo_sf_difficulty_rank_from_id($did);

          $shown_count = (int)($r['shown_count'] ?? 0);



          // à difficulté égale : tenté avant nouveau

          $status_rank = ($status === 'attempted') ? 0 : 1;



          $local[] = [

            'eid'         => $eid,

            'row'         => $r,

            'status'      => $status,

            'badge'       => $badge,

            'status_rank' => $status_rank,

            'diff_rank'   => $diff_rank,

            'shown_count' => $shown_count,

            'did'         => $did,

          ];

        }



        usort($local, function($a, $b){

          if ($a['diff_rank'] !== $b['diff_rank']) {

            return $a['diff_rank'] <=> $b['diff_rank'];

          }

          if ($a['shown_count'] !== $b['shown_count']) {

            return $a['shown_count'] <=> $b['shown_count'];

          }

          if ($a['status_rank'] !== $b['status_rank']) {

            return $a['status_rank'] <=> $b['status_rank'];

          }

          return $b['eid'] <=> $a['eid'];

        });



        foreach ($local as $x) {

          if (count($cards) >= $limit * 6) break;



          $eid = $x['eid'];

          $r   = $x['row'];



          $title = trim((string)($r['title'] ?? '')) ?: ('Exercice '.$eid);

          $excerpt = wp_trim_words(wp_strip_all_tags((string)($r['statement'] ?? '')), 22, '…');



          $card = [

            'url'     => home_url('/exercice/?exo='.$eid),

            'title'   => $title,

            'excerpt' => $excerpt,

            'type'    => 'exercise',

            'badge'   => $x['badge'],

            'status'  => $x['status'],



            // tri final par urgence

            'priority_competency_id'    => $cid,

            'priority_competency_level' => $lvl,



            // debug/affichage

            'difficulty_id'    => $x['did'],

            'difficulty'       => (string)($r['difficulty_slug'] ?? ''),

            'difficulty_label' => (string)($r['difficulty_label'] ?? ''),

          ];



          if (!empty($r['exo_domains'])) $card['exo_domains'] = (string)$r['exo_domains'];



          $cards[] = $card;

          $seen_exo[$eid] = true;

        }

      }



      // tri final : compétence la plus urgente -> progressif -> tenté avant nouveau

      $rank_status = ['attempted'=>0,'new'=>1];



      usort($cards, function($a,$b) use ($rank_status){

        $la = (int)($a['priority_competency_level'] ?? 99);

        $lb = (int)($b['priority_competency_level'] ?? 99);

        if ($la !== $lb) return $la <=> $lb;



        $da = ouinpo_sf_difficulty_rank_from_id((int)($a['difficulty_id'] ?? 0));

        $db = ouinpo_sf_difficulty_rank_from_id((int)($b['difficulty_id'] ?? 0));

        if ($da !== $db) return $da <=> $db;



        $sa = $rank_status[(string)($a['status'] ?? 'new')] ?? 1;

        $sb = $rank_status[(string)($b['status'] ?? 'new')] ?? 1;

        if ($sa !== $sb) return $sa <=> $sb;



        // id exo desc

        $ida = 0; $idb = 0;

        parse_str(parse_url($a['url'] ?? '', PHP_URL_QUERY) ?: '', $qa);

        parse_str(parse_url($b['url'] ?? '', PHP_URL_QUERY) ?: '', $qb);

        $ida = (int)($qa['exo'] ?? 0);

        $idb = (int)($qb['exo'] ?? 0);

        return $idb <=> $ida;

      });



      // limite finale

      if (count($cards) > $limit) $cards = array_slice($cards, 0, $limit);



      return new \WP_REST_Response([

        'ok'              => true,

        'user_id'         => $user_id,

        'cards'           => $cards,

        'domains'         => ouinpo_sf_list_domains_for_user($user_id),

        'selected_domain' => $domain,

        'student_level'   => $level,

      ], 200);

    }

  ]);



  // ========================= /public-chat =========================

  register_rest_route('ouinpo-segfault/v1', '/public-chat', [

    'methods' => ['POST'],

    'permission_callback' => function () {

      return true;

    },

    'callback' => function (\WP_REST_Request $req) {

      try {

        $members_only = (int) get_option('ouinpo_sf_members_only', 0);

        if ($members_only && !is_user_logged_in()) {

          return new \WP_REST_Response([

            'error' => 'forbidden',

            'message' => 'Le chat public est réservé aux utilisateurs connectés.'

          ], 403);

        }  

        

        if (is_user_logged_in()) {

          return new \WP_REST_Response([

            'error' => 'logged_in',

            'message' => 'Le chat public est réservé aux visiteurs non connectés.'

          ], 403);

        }



        if (!ouinpo_sf_public_albert_enabled()) {

          return new \WP_REST_Response([

            'error' => 'disabled',

            'message' => 'Le chat public n’est pas activé.'

          ], 403);

        }



        $quota = ouinpo_sf_public_quota_check();

        if (is_wp_error($quota)) {

          return new \WP_REST_Response([

            'error' => $quota->get_error_code(),

            'message' => $quota->get_error_message()

          ], (int)($quota->get_error_data()['status'] ?? 429));

        }



        // Même contrat d’entrée que /chat : message, session, consent, page.

        // Pour les visiteurs anonymes, session/consent sont acceptés pour compatibilité,

        // mais aucune mémoire n’est lue ni enregistrée côté serveur.

        $body     = $req->get_json_params();

        $q        = ouinpo_sf_public_sanitize_message($body['message'] ?? '');

        $consent  = (bool)($body['consent'] ?? false); // volontairement non utilisé en public

        $session  = sanitize_text_field($body['session'] ?? ''); // compatibilité réponse/JS

        $page_url = isset($body['page']) ? esc_url_raw($body['page']) : '';



        if ($q === '') {

          return new \WP_REST_Response(['error' => 'empty', 'message' => 'Question vide.'], 400);

        }



        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $q) || preg_match('/(?:\+33|0)[1-9](?:[ .\-]?\d{2}){4}/', $q)) {

          return new \WP_REST_Response([

            'session' => '',

            'answer' => ouinpo_sf_markdown_to_html("Je préfère ne pas traiter de données personnelles dans le chat public. Reformule ta question sans nom, adresse, téléphone, note ou information permettant d’identifier quelqu’un."),

            'sources' => []

          ], 200);

        }



        // ------------------------------------------------------

        // Même logique de contexte que le chat habituel :

        // RAG cours/private + page courante si fournie.

        // ------------------------------------------------------

        $chunks_context = \OuInPo\SegFault\RAG::search($q, 6, 0);



        $current_chunk = null;

        

        if (!empty($page_url)) {

          $current_chunk = ouinpo_sf_build_current_page_chunk($page_url);

        

          if (

            $current_chunk

            && is_user_logged_in()

            && class_exists('\OuInPo\SegFault\RAG')

            && method_exists('\OuInPo\SegFault\RAG', 'source_allowed_for_current_user')

            && !\OuInPo\SegFault\RAG::source_allowed_for_current_user($current_chunk)

          ) {

            $current_chunk = null;

          }

        }



        $mentions_here = (bool)preg_match(

          '/(cette page|sur cette page|dans cette page|dans ce cours|sur ce cours|dans ce chapitre|ici|cet exercice|dans cet exercice|de cet exercice)/iu',

          $q

        );



        if ($current_chunk && $mentions_here) {

          $chunks_context = [$current_chunk];

        } else {

          if ($current_chunk) {

            $already = false;

            foreach ($chunks_context as $c) {

              if (!empty($c['url']) && $c['url'] === $current_chunk['url']) { $already = true; break; }

            }

            if (!$already) array_unshift($chunks_context, $current_chunk);

          }

        }



        $context_text = \OuInPo\SegFault\RAG::format_context($chunks_context);



        // Même persona que le SegFault habituel + garde-fous publics.

        $system_persona = \OuInPo\SegFault\Persona::system();

        $public_rules   = ouinpo_sf_public_system_prompt();



        $messages = [

          ['role' => 'system', 'content' => $system_persona . "\n\n" . $public_rules],

          [

            'role' => 'user',

            'content' =>

              "Question :\n{$q}\n\n".

              "Contexte (extraits) :\n{$context_text}\n\n".

              "Consignes :\n".

              "- Réponds clairement, avec une aide pédagogique utile.\n".

              "- Si la question porte sur la page courante, utilise d’abord le contexte fourni.\n".

                "- Explique la notion clairement.\n".

                "- Ne propose un exercice que si l’élève en demande explicitement un.\n".

                "- Si tu proposes un exercice, fais-le en 1 à 3 lignes, sans donner la solution.\n".

              "- Ne crée pas de bloc « Sources : », « Cours à consulter » ou « Exercices conseillés » : le site les ajoute automatiquement.\n".

              "- N’indique jamais que tu as accès à un compte, à une classe, à une note ou à une progression élève."

          ]

        ];



        $answer = \OuInPo\SegFault\Albert::respond($messages, [

          // Même nom d’option accepté que l’IA habituelle ; Albert convertit en max_completion_tokens.

          'temperature' => 0.3,

          'top_p'       => 1.0,

          'max_tokens'  => 700,

        ]);



        // ------------------------------------------------------

        // Même structure de sources UI que /chat, sans exos personnalisés

        // ni logging de suggestions, car l’utilisateur est anonyme.

        // ------------------------------------------------------

        $chunks_exos = [];

        $src = ouinpo_sf_build_sources(array_merge($chunks_context, $chunks_exos), 5);



        if (empty($src) && !empty($current_chunk) && !empty($current_chunk['url'])) {

          $src[] = [

            'title' => $current_chunk['title'] ?? 'Ressource courante',

            'url'   => $current_chunk['url'],

            'type'  => $current_chunk['ptype'] ?? 'page',

            'badge' => (($current_chunk['ptype'] ?? '') === 'exercise')

              ? 'Exercice (page en cours)'

              : 'Cours (page en cours)',

          ];

        }



        $answer_html = ouinpo_sf_markdown_to_html($answer);

        $answer_html .= ouinpo_sf_sources_to_answer_html($src);

        

        return new \WP_REST_Response([

          'session' => '',

          'answer'  => $answer_html,

          'sources' => []

        ], 200);



      } catch (\Throwable $e) {

        error_log('[SegFault Public Albert] REST error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

        return new \WP_REST_Response([

          'error' => 'exception',

          'message' => 'Erreur interne SegFault public.'

        ], 500);

      }

    }

  ]);



  // ========================= /chat =========================

    register_rest_route('ouinpo-segfault/v1', '/chat', [

      'methods' => ['GET', 'POST'],

      'permission_callback' => function () {

        $members_only = (int) get_option('ouinpo_sf_members_only', 0);

        if ($members_only && !is_user_logged_in()) {

          return false;

        }

        return true;

      },

      'callback' => function (\WP_REST_Request $req) {



      if ($req->get_method() === 'GET') {

        return new \WP_REST_Response([

          'ok'      => true,

          'message' => 'Endpoint SegFault opérationnel. Utilise POST pour poser une question.',

        ], 200);

      }



      try {

        $body     = $req->get_json_params();

        $q        = trim($body['message'] ?? '');

        $consent  = (bool)($body['consent'] ?? false);

        $session  = sanitize_text_field($body['session'] ?? '');

        $page_url = isset($body['page']) ? esc_url_raw($body['page']) : '';



        $members_only = (int)get_option('ouinpo_sf_members_only', 0);

        if ($members_only && !is_user_logged_in()) {

          return new \WP_REST_Response([

            'error'   => 'forbidden',

            'message' => 'Chat réservé aux membres connectés.'

          ], 403);

        }



        if ($q === '') return new \WP_REST_Response(['error' => 'empty'], 400);



        // session mémoire

        $session = \OuInPo\SegFault\DB::ensure_session($session, $consent);



        $user_id = get_current_user_id();



        $q_for_guard = $q;

        $implicit_previous_user_turn = '';

        

        if (ouinpo_sf_is_generic_exercise_followup($q)) {

          $implicit_previous_user_turn = ouinpo_sf_last_user_turn_before_current($session, $q);

        

          if ($implicit_previous_user_turn !== '') {

            $q_for_guard = $implicit_previous_user_turn . "\n" . $q;

          }

        }



        $is_out_of_program_for_student = false;

        $out_of_program_label = 'une notion hors programme';

        $out_of_program_notice = '';

        

        if (

          is_user_logged_in()

          && class_exists('\OuInPo\SegFault\RAG')

          && method_exists('\OuInPo\SegFault\RAG', 'topic_is_out_of_program_for_user')

        ) {

          $is_out_of_program_for_student = \OuInPo\SegFault\RAG::topic_is_out_of_program_for_user($q_for_guard, $user_id);

        

          if ($is_out_of_program_for_student) {

            $q_norm = strtolower(remove_accents($q_for_guard));

            $q_norm = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $q_norm);

            $q_norm = preg_replace('/\s+/u', ' ', trim((string)$q_norm));

        

            $labels = [

              '/\bfiles?\b/u' => 'les files',

              '/\bfifo\b/u' => 'les files',

              '/\bqueue\b/u' => 'les files',

              '/\bpiles?\b/u' => 'les piles',

              '/\blifo\b/u' => 'les piles',

              '/\bstack\b/u' => 'les piles',

              '/\barbres?\s+binaires?\s+de\s+recherche\b/u' => 'les arbres binaires de recherche',

              '/\babr\b/u' => 'les arbres binaires de recherche',

              '/\bbst\b/u' => 'les arbres binaires de recherche',

              '/\barbres?\b/u' => 'les arbres',

              '/\bgraphes?\b/u' => 'les graphes',

              '/\bdijkstra\b/u' => 'l’algorithme de Dijkstra',

              '/\broutage\b/u' => 'le routage',

              '/\brecursivite\b/u' => 'la récursivité',

              '/\brecursif\b/u' => 'la récursivité',

              '/\bdiviser pour regner\b/u' => 'diviser pour régner',

              '/\bprogrammation dynamique\b/u' => 'la programmation dynamique',

            ];

        

            foreach ($labels as $pattern => $label) {

              if (preg_match($pattern, $q_norm)) {

                $out_of_program_label = $label;

                break;

              }

            }

        

            $out_of_program_notice =

              "ATTENTION PROGRAMME PRIORITAIRE : l'élève connecté pose une question sur {$out_of_program_label}, une notion hors programme pour son niveau. "

              . "Tu dois le signaler clairement dès le début. "

              . "Même si l'élève demande explicitement un exercice, tu ne dois proposer aucun exercice, ne pas inventer d'exercice, ne pas donner d'énoncé à faire, et ne pas donner de consigne de programmation sur cette notion. "

              . "Tu peux seulement donner une intuition courte, puis réorienter vers une notion du niveau de l'élève.";

          }

        }





        // ------------------------------------------------------

        // RAG : séparation CONTEXTE IA vs SOURCES UI

        // ------------------------------------------------------



        // 1) Contexte IA : cours + private (PAS d'exos)

        $chunks_context = \OuInPo\SegFault\RAG::search($q_for_guard, 6, $user_id);

        

        $chunks_context = array_values(array_filter($chunks_context, static function($c) {

          $pt = strtolower(trim((string)($c['ptype'] ?? ($c['type'] ?? ($c['origin'] ?? '')))));

          return $pt !== 'exercise';

        }));        



        // 2) Exos pour la zone Sources uniquement (si méthode dispo)

        $wants_exercises = (bool)preg_match(

          '/\b(exercice|exercices|exo|exos|entrainement|entraînement|s.?entraîner|s.?entrainer|réviser|reviser|travail à faire|à faire)\b/iu',

          $q

        );        

        $chunks_exos = [];

        

        if ($wants_exercises && !$is_out_of_program_for_student && method_exists('\OuInPo\SegFault\RAG', 'search_with_exercises')) {

          $tmp = \OuInPo\SegFault\RAG::search_with_exercises($q_for_guard, $user_id, 6);

        

          if (is_array($tmp)) {

            foreach ($tmp as $c) {

              $pt = strtolower(trim((string)($c['ptype'] ?? ($c['type'] ?? ($c['origin'] ?? '')))));

              if ($pt !== 'exercise') continue;

              if (empty($c['url'])) continue;

              $chunks_exos[] = $c;

            }

          }

        }



        // Page courante si dispo

        $current_chunk = null;

        

        if (!empty($page_url)) {

          $current_chunk = ouinpo_sf_build_current_page_chunk($page_url);

        

          if (

            $current_chunk

            && is_user_logged_in()

            && class_exists('\OuInPo\SegFault\RAG')

            && method_exists('\OuInPo\SegFault\RAG', 'source_allowed_for_current_user')

            && !\OuInPo\SegFault\RAG::source_allowed_for_current_user($current_chunk)

          ) {

            $current_chunk = null;

          }

        }



        // si l'élève dit “ici / dans cet exercice…”, on focalise le CONTEXTE IA

        $mentions_here = (bool)preg_match(

          '/(cette page|sur cette page|dans cette page|dans ce cours|sur ce cours|dans ce chapitre|ici|cet exercice|dans cet exercice|de cet exercice)/iu',

          $q

        );



        if ($current_chunk && $mentions_here) {

          $chunks_context = [$current_chunk];

        } else {

          if ($current_chunk) {

            $already = false;

            foreach ($chunks_context as $c) {

              if (!empty($c['url']) && $c['url'] === $current_chunk['url']) { $already = true; break; }

            }

            if (!$already) array_unshift($chunks_context, $current_chunk);

          }

        }



$context_text = \OuInPo\SegFault\RAG::format_context($chunks_context);



// Persona

$system_persona = \OuInPo\SegFault\Persona::system();



// Contexte pédagogique élève

$student_context = '';

if (

  is_user_logged_in()

  && class_exists('\OuInPo\SegFault\RAG')

  && method_exists('\OuInPo\SegFault\RAG', 'student_pedagogical_context')

) {

  $student_context = \OuInPo\SegFault\RAG::student_pedagogical_context($user_id);

}







$system_content = trim(

  $system_persona

  . "\n\n"

  . $out_of_program_notice

  . "\n\n"

  . "CONTEXTE PÉDAGOGIQUE ÉLÈVE\n"

  . $student_context

  . "\n\n"

  . "Consignes pédagogiques :\n"

  . "- Adapte la réponse au niveau scolaire de l'élève.\n"

  . "- Ne mentionne jamais le nom, le prénom ou l'identité de l'élève.\n"

  . "- Ne propose pas en priorité un exercice déjà réussi par l'élève.\n"

  . "- Si un exercice a déjà été tenté, propose plutôt une aide progressive ou un exercice voisin.\n"

  . "- Si une compétence BO est fragile, commence par une explication courte et guidée.\n"

  . "- Si une compétence BO est acquise, propose une consolidation ou un défi raisonnable.\n"

  . "- Ne pas utiliser de notion hors programme du niveau indiqué sans le signaler explicitement."

);



$implicit_context_text = '';



if ($implicit_previous_user_turn !== '') {

  $implicit_context_text =

    "Contexte implicite de la demande : la question actuelle semble reprendre la question précédente de l'élève :\n"

    . $implicit_previous_user_turn

    . "\n\n";

}



$messages = [

  ['role' => 'system', 'content' => $system_content],

  [

    'role' => 'user',

    'content' =>

      $implicit_context_text .

        "Question actuelle :\n{$q}\n\n".

      "Contexte documentaire RAG :\n{$context_text}\n\n".

        "Consignes :\n".

          "- Explique la notion clairement.\n".

          "- Ne cite jamais les numéros de contexte comme [0], [1], [2] ou ([2]). Si tu utilises le contexte, reformule sans référence numérotée.\n".

          (

            $is_out_of_program_for_student

              ? "- Garde-fou prioritaire : ne propose aucun exercice, même si l’élève en demande un. Ne crée pas d’énoncé, ne donne pas de consigne de programmation, ne donne pas de mini-exercice. Donne seulement une intuition courte, puis réoriente vers une notion du niveau de l’élève.\n"

              : "- Ne propose un exercice que si l’élève en demande explicitement un.\n"

                . "- Si tu proposes un exercice, fais-le en 1 à 3 lignes, sans donner la solution.\n"

          ).

        "- Ne crée pas de bloc « Sources : », « Cours à consulter » ou « Exercices conseillés » : le site les ajoute automatiquement."

  ]

];



        // Historique (si dispo)

        if (class_exists('\OuInPo\SegFault\DB') && method_exists('\OuInPo\SegFault\DB', 'last_turns')) {

          $recent = \OuInPo\SegFault\DB::last_turns($session, 4);

          if (is_array($recent) && !empty($recent)) {

            $history_msgs = [];

            foreach ($recent as $t) {

              $role = ($t['role'] === 'assistant') ? 'assistant' : 'user';

              $content = (string)($t['content'] ?? '');

              if ($content !== '') $history_msgs[] = ['role'=>$role,'content'=>$content];

            }

            if (!empty($history_msgs)) {

              $current = array_pop($messages);

              foreach ($history_msgs as $hm) $messages[] = $hm;

              $messages[] = $current;

            }

          }

        }



        $answer = \OuInPo\SegFault\OpenAI::respond($messages);

        $answer = preg_replace('/\s*\(\[\d+\]\)/u', '', $answer);

        $answer = preg_replace('/\s*\[\d+\]/u', '', $answer);

        $answer_html = ouinpo_sf_markdown_to_html($answer);



        // save mémoire (texte brut)

        \OuInPo\SegFault\DB::save_turn($session, 'user', $q);

        \OuInPo\SegFault\DB::save_turn($session, 'assistant', $answer);



        // ------------------------------------------------------

        // Sources UI : cours/pages + exos (sans polluer le contexte IA)

        // ------------------------------------------------------

        $src = [];

        

        if (!$is_out_of_program_for_student) {

        

        if ($wants_exercises) {

          // On sépare volontairement cours et exercices.

          // Les cours sont d'abord cherchés via les compétences BO,

          // puis on garde le RAG classique en fallback.

          $chunks_courses = [];

        

          if (

            method_exists('\OuInPo\SegFault\RAG', 'search_courses_by_competency')

          ) {

            $chunks_courses = \OuInPo\SegFault\RAG::search_courses_by_competency($q_for_guard, 3, $user_id);

          }

        

          if (empty($chunks_courses)) {

            $chunks_courses = $chunks_context;

          }

        

          $course_src = ouinpo_sf_build_sources($chunks_courses, 3);

          $exo_src    = ouinpo_sf_build_sources($chunks_exos, 3);

        

          $src = array_merge($course_src, $exo_src);

        } else {

          $chunks_courses = [];

        

          if (

            method_exists('\OuInPo\SegFault\RAG', 'search_courses_by_competency')

          ) {

            $chunks_courses = \OuInPo\SegFault\RAG::search_courses_by_competency($q_for_guard, 5, $user_id);

          }

        

          if (!empty($chunks_courses)) {

            $src = ouinpo_sf_build_sources($chunks_courses, 5);

          } else {

            $src = ouinpo_sf_build_sources($chunks_context, 5);

          }

        }

        

          // fallback : page courante si rien

          if (empty($src) && !empty($current_chunk) && !empty($current_chunk['url'])) {

            $src[] = [

              'title' => $current_chunk['title'] ?? 'Ressource courante',

              'url'   => $current_chunk['url'],

              'type'  => $current_chunk['ptype'] ?? 'page',

              'badge' => (($current_chunk['ptype'] ?? '') === 'exercise')

                ? 'Exercice (page en cours)'

                : 'Cours (page en cours)',

            ];

          }

        }



        // ✅ Log automatique des exos proposés (pour "parcours auto" côté prof)

        ouinpo_sf_ensure_suggestions_table();

        ouinpo_sf_log_suggested_exercises($user_id, $session, $page_url, $q, $src);



        // ✅ Suggestions intégrées directement dans la bulle de réponse

        $answer_html .= ouinpo_sf_sources_to_answer_html($src);



        return new \WP_REST_Response([

          'session'    => $session,

          'answer'     => $answer_html,

          'answer_raw' => $answer,

          'sources'    => []

        ], 200);



        } catch (\Throwable $e) {

          error_log('[SegFault Chat] REST error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

          return new \WP_REST_Response([

            'error'   => 'exception',

            'message' => 'Erreur interne SegFault.',

          ], 500);

        }

    }

  ]);



  // ========================= /memory/clear =========================

  register_rest_route('ouinpo-segfault/v1', '/memory/clear', [

    'methods' => 'POST',

    'permission_callback' => function () {

      $members_only = (int)get_option('ouinpo_sf_members_only', 0);

      if ($members_only && !is_user_logged_in()) return false;

      return true;

    },

    'callback' => function (\WP_REST_Request $req) {

      try {

        $body    = $req->get_json_params();

        $session = sanitize_text_field($body['session'] ?? '');



        if ($session === '') {

          return new \WP_REST_Response([

            'ok' => true,

          ], 200);

        }



        \OuInPo\SegFault\DB::delete_session($session);



        return new \WP_REST_Response([

          'ok' => true,

        ], 200);



      } catch (\Throwable $e) {

        error_log('[SegFault Memory Clear] REST error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

        return new \WP_REST_Response([

          'error'   => 'exception',

          'message' => 'Erreur interne SegFault.',

        ], 500);

      }

    }

  ]);



});



// -----------------------------------------------------------------------------

// Chat flottant + bouton

// -----------------------------------------------------------------------------

add_action('wp_footer', function () {

  $members_only = (int)get_option('ouinpo_sf_members_only', 0);

  if ($members_only && !is_user_logged_in()) return;

  ?>

  <div id="sf-toggle" class="sf-toggle" title="Parler à SegFault">😼</div>



  <div id="sf-chat-floating" class="ouinpo-sf-widget ouinpo-sf-floating sf-hidden" aria-live="polite" aria-label="Assistant NSI SegFault">

    <div class="sf-header">

      😼 SegFault — Assistant NSI

      <div class="sf-header-buttons">

        <button type="button" class="sf-btn sf-fullscreen" title="Plein écran">

          <svg class="sf-icon-svg" viewBox="0 0 24 24" aria-hidden="true">

            <path d="M5 5h6v2H7v4H5V5zm14 0v6h-2V7h-4V5h6zm-6 14h4v-4h2v6h-6v-2zm-4 0v2H5v-6h2v4h4z"

                  fill="none" stroke="#e8aa46" stroke-width="1.9" stroke-linejoin="round" />

          </svg>

        </button>

        <button type="button" class="sf-btn sf-close" title="Fermer">

          <svg class="sf-icon-svg" viewBox="0 0 24 24" aria-hidden="true">

            <path d="M12 2 19 6v12l-7 4-7-4V6z"

                  fill="none" stroke="#e8aa46" stroke-width="1.9" stroke-linejoin="round" />

            <path d="M9 9l6 6M15 9l-6 6"

                  fill="none" stroke="#e8aa46" stroke-width="2.2" stroke-linecap="round" />

          </svg>

        </button>

      </div>

    </div>



    <?php echo ouinpo_sf_ai_notice_html(); ?>



    <div class="sf-messages"></div>

    <div class="sf-input">

      <textarea rows="3" placeholder="Pose ta question NSI... (Entrée = envoyer, Maj+Entrée = nouvelle ligne)"></textarea>

      <button type="button">Envoyer</button>

    </div>

    <?php if (is_user_logged_in()) : ?>

      <div class="sf-footer">

        <label><input type="checkbox" id="sf-consent" checked /> Mémoriser cet échange 30 jours</label>

        <button type="button" id="sf-clear">Effacer la mémoire</button>

      </div>

    <?php else : ?>

      <div class="sf-footer sf-public-footer">Assistant public sans mémoire de conversation.</div>

    <?php endif; ?>

  </div>

  <?php

}, 5);