<?php

namespace OuInPo\SegFault;



if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', function (string $hook = ''): void {
  $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

  if (!in_array($page, ['ouinpo-segfault', 'ouinpo-segfault-progress'], true)) {
    return;
  }

  \Ouinpo\Suite\Core\Assets::enqueueStyle('ouinpo-segfault-admin', 'assets/css/admin/segfault-admin.css');
  \Ouinpo\Suite\Core\Assets::enqueueScript('ouinpo-segfault-admin-js', 'assets/js/admin/segfault-admin.js');
});



add_action('admin_menu', function () {

  $parent = \Ouinpo\Suite\Core\Admin\AdminMenuRegistry::legacyParent('ouinpo-segfault');



  if (!defined('OUINPO_SUITE_ADMIN_SLUG')) {

    add_menu_page(

      'SegFault (NSI)',

      'SegFault (NSI)',

      \Ouinpo\Suite\Core\Capabilities::MANAGE_AI,

      'ouinpo-segfault',

      __NAMESPACE__.'\\admin_page',

      'dashicons-format-chat'

    );

  }



  add_submenu_page(

    $parent,

    'SegFault (NSI)',

    'SegFault',

    \Ouinpo\Suite\Core\Capabilities::MANAGE_AI,

    'ouinpo-segfault',

    __NAMESPACE__.'\\admin_page'

  );



  add_submenu_page(

    $parent,

    'Suivi élèves',

    'Suivi élèves',

    \Ouinpo\Suite\Core\Capabilities::VIEW_STUDENT_DATA,

    'ouinpo-segfault-progress',

    __NAMESPACE__.'\\admin_progress_page'

  );

});



add_action('admin_init', function () {

  \Ouinpo\Suite\Core\AiSettings::register_settings('ouinpo_sf');


  if (is_admin() && \Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_AI)) {

    ouinpo_sf_ensure_progress_tables();

  }

});



/**

 * ✅ Niveau élève via groupe (source de vérité).

 */

if (!function_exists(__NAMESPACE__.'\\ouinpo_sf_student_level_from_group')) {

    function ouinpo_sf_student_level_from_group(int $user_id, array &$debug = null): string {

      static $cache = [];

      static $debug_cache = [];

    

      global $wpdb;

    

      if ($user_id <= 0) {

        return '';

      }

    

      if (array_key_exists($user_id, $cache)) {

        if (is_array($debug)) {

          $debug = $debug_cache[$user_id] ?? [];

        }

        return $cache[$user_id];

      }

    

      $t_members = $wpdb->prefix.'ouin_exo_group_members';

      $t_groups  = $wpdb->prefix.'ouin_exo_groups';

      $t_levels  = $wpdb->prefix.'ouin_exo_school_levels';

    

      if (function_exists('\ouinpo_sf_table_columns')) {

        $cols = \ouinpo_sf_table_columns($t_members);

      } else {

        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t_members}", 0) ?: [];

      }

    

      if (in_array('updated_at', $cols, true)) {

        $order = 'm.updated_at DESC';

      } elseif (in_array('created_at', $cols, true)) {

        $order = 'm.created_at DESC';

      } elseif (in_array('group_id', $cols, true)) {

        $order = 'm.group_id DESC';

      } else {

        $order = 'm.user_id DESC';

      }

    

      $row = $wpdb->get_row($wpdb->prepare("

        SELECT

          m.group_id           AS member_group_id,

          g.id                 AS group_id,

          g.school_level_id    AS school_level_id,

          lv.id                AS level_id,

          lv.slug              AS level_slug,

          lv.label             AS level_label

        FROM {$t_members} m

        LEFT JOIN {$t_groups} g  ON g.id = m.group_id

        LEFT JOIN {$t_levels} lv ON lv.id = g.school_level_id

        WHERE m.user_id = %d

        ORDER BY {$order}

        LIMIT 1

      ", $user_id), ARRAY_A);

    

      $debug_cache[$user_id] = $row ?: [];

    

      if (is_array($debug)) {

        $debug = $debug_cache[$user_id];

      }

    

      if (!$row) {

        $cache[$user_id] = '';

        return '';

      }

    

      $school_level_id = (int)($row['school_level_id'] ?? 0);

      $slug  = strtolower(trim((string)($row['level_slug'] ?? '')));

      $label = strtolower(trim((string)($row['level_label'] ?? '')));

    

      $level = '';

    

      if (in_array($slug, ['seconde','premiere','terminale'], true)) {

        $level = $slug;

      } elseif ($slug !== '') {

        if (str_starts_with($slug, 'term')) {

          $level = 'terminale';

        } elseif (str_starts_with($slug, 'prem')) {

          $level = 'premiere';

        } elseif (str_starts_with($slug, 'sec')) {

          $level = 'seconde';

        }

      }

    

      if ($level === '' && $label !== '') {

        if (str_contains($label, 'term')) {

          $level = 'terminale';

        } elseif (str_contains($label, 'prem')) {

          $level = 'premiere';

        } elseif (str_contains($label, 'sec')) {

          $level = 'seconde';

        }

      }

    

      if ($level === '') {

        if ($school_level_id === 1) {

          $level = 'seconde';

        } elseif ($school_level_id === 2) {

          $level = 'premiere';

        } elseif ($school_level_id === 3) {

          $level = 'terminale';

        }

      }

    

      $cache[$user_id] = $level;

    

      return $level;

    }

    

}



/**

 * ✅ AJAX admin: retourne domaines + compétences filtrés sur le niveau de l'élève

 */

add_action('wp_ajax_ouinpo_sf_filters', function () {

  if (!\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::VIEW_STUDENT_DATA)) {

    wp_send_json_error(['message' => 'forbidden'], 403);

  }



  $nonce = isset($_POST['nonce']) ? sanitize_text_field((string)$_POST['nonce']) : '';

  if (!wp_verify_nonce($nonce, 'ouinpo_sf_filters')) {

    wp_send_json_error(['message' => 'bad_nonce'], 400);

  }



  $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

  if ($student_id <= 0) {

    wp_send_json_success([

      'level' => 'premiere',

      'domains' => [],

      'competencies' => []

    ]);

  }



  $dbg = [];

  $level_from_group = ouinpo_sf_student_level_from_group($student_id, $dbg);

  $level = $level_from_group;



  if ($level === '') {

    $meta = strtolower(trim((string)get_user_meta($student_id, 'nsi_level', true)));

    if (in_array($meta, ['premiere','terminale'], true)) $level = $meta;

  }



  if ($level === '') {

    $level = function_exists('\\ouinpo_sf_user_nsi_level')

      ? \ouinpo_sf_user_nsi_level($student_id, null)

      : 'premiere';

  }



  if ($level === 'seconde') $level = 'premiere';

  if (!in_array($level, ['premiere','terminale'], true)) $level = 'premiere';



  global $wpdb;

  $t = $wpdb->prefix.'ouin_exo_competencies';



  $domains = $wpdb->get_results(

    $wpdb->prepare("

      SELECT DISTINCT track, level, domain, domain_slug

      FROM {$t}

      WHERE active = 1 AND level = %s

      ORDER BY track ASC, domain ASC

    ", $level),

    ARRAY_A

  ) ?: [];



  $competencies = $wpdb->get_results(

    $wpdb->prepare("

      SELECT id, domain, domain_slug, competency, track, level

      FROM {$t}

      WHERE active = 1 AND level = %s

      ORDER BY track ASC, level ASC, domain ASC, id ASC

    ", $level),

    ARRAY_A

  ) ?: [];



  wp_send_json_success([

    'level' => $level,

    'domains' => $domains,

    'competencies' => $competencies,

  ]);

});



/**

 * ✅ Crée (si besoin) les tables MySQL pour le suivi des parcours.

 * On garde temporairement student_id dans ouin_sf_paths pour compatibilité legacy.

 */

function ouinpo_sf_ensure_progress_tables() {

    static $done = false;



    if ($done) {

        return;

    }



    /*

     * Les tables de progression/parcours sont créées par l’installer

     * principal de la suite à l’activation ou à la montée de version.

     *

     * Cette fonction reste comme point de compatibilité historique,

     * mais ne doit plus appeler dbDelta() ni installOrUpgradeSharedSchema().

     */

    $done = true;

}



/**

 * Supprime (optionnel) les fichiers sources md/txt/pdf du dossier sources/

 * sans toucher au XML WXR.

 */

function ouinpo_sf_clear_sources_folder(bool $also_delete_xml = false): int {

  if (!defined('OUINPO_SF_SRC')) {

    return 0;

  }



  $src = trailingslashit(OUINPO_SF_SRC);

  $n = 0;



  $patterns = [

    $src . '*.md',

    $src . '*.txt',

    $src . '*.pdf',

  ];



  if ($also_delete_xml) {

    $patterns[] = $src . '*.xml';

  }



  foreach ($patterns as $pat) {

    $files = glob($pat) ?: [];



    foreach ($files as $f) {

      if (is_file($f) && @unlink($f)) {

        $n++;

      }

    }

  }



  return $n;

}

function ouinpo_sf_xml_cdata(string $s): string {

  return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $s) . ']]>';

}



function ouinpo_sf_xml_text(string $s): string {

  if (function_exists('esc_xml')) return esc_xml($s);

  return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

}



/**

 * Génère un WXR "propre" pour SegFault :

 * - uniquement post/page publiés

 * - sans bloc auteurs/emails

 * - sans attachments / CPT / metas

 * - avec seulement les infos utiles au parseur RAG

 */



function ouinpo_sf_clean_html_for_wxr(string $html, int $post_id = 0): string {

  if (trim($html) === '') {

    return '';

  }



  if (!class_exists('\DOMDocument') || !class_exists('\DOMXPath')) {

    return trim($html);

  }



  libxml_use_internal_errors(true);



  $doc = new \DOMDocument();

  $wrapped = '<!DOCTYPE html><html><body><div id="ouinpo-wxr-root">' . $html . '</div></body></html>';

  $doc->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);



  $xpath = new \DOMXPath($doc);



  // 1) Supprimer les balises purement techniques / interactives

  $tags_to_remove = [

    'script',

    'style',

    'iframe',

    'form',

    'input',

    'button',

    'select',

    'option',

    'textarea',

    'noscript',

    'svg',

    'canvas'

  ];



  foreach ($tags_to_remove as $tag) {

    $nodes = $doc->getElementsByTagName($tag);

    for ($i = $nodes->length - 1; $i >= 0; $i--) {

      $node = $nodes->item($i);

      if ($node && $node->parentNode) {

        $node->parentNode->removeChild($node);

      }

    }

  }



  // 2) Enlever les commentaires HTML

  foreach ($xpath->query('//comment()') as $comment) {

    if ($comment->parentNode) {

      $comment->parentNode->removeChild($comment);

    }

  }



  // 3) Supprimer les attributs techniques inutiles au contexte IA

  $all = $xpath->query('//*');

  foreach ($all as $el) {

    if (!$el->hasAttributes()) {

      continue;

    }



    $to_remove = [];

    foreach ($el->attributes as $attr) {

      $name = strtolower($attr->name);

      $value = (string) $attr->value;



      if (

        str_starts_with($name, 'data-') ||

        str_starts_with($name, 'on') ||

        in_array($name, ['nonce', 'aria-controls', 'aria-expanded', 'aria-hidden'], true)

      ) {

        $to_remove[] = $name;

        continue;

      }



      if (

        strpos($value, 'admin-ajax.php') !== false ||

        strpos($value, 'wp-json') !== false

      ) {

        $to_remove[] = $name;

        continue;

      }

    }



    foreach ($to_remove as $name) {

      $el->removeAttribute($name);

    }

  }



  // 4) Enlever quelques blocs connus comme purement techniques

  $technical_xpath = [

    "//*[contains(@class,'h5p-iframe-wrapper')]",

    "//*[contains(@class,'um-account-side')]",

    "//*[contains(@class,'um-account-main')]",

    "//*[contains(@class,'ouinpo-prof')]",

    "//*[contains(@class,'ouinpo-sf-prof')]",

    "//*[contains(@class,'screen-reader-text')]"

  ];



  foreach ($technical_xpath as $query) {

    foreach ($xpath->query($query) as $node) {

      if ($node->parentNode) {

        $node->parentNode->removeChild($node);

      }

    }

  }



  // 5) Récupérer uniquement le contenu du wrapper

  $root = $doc->getElementById('ouinpo-wxr-root');

  if (!$root) {

    libxml_clear_errors();

    return trim($html);

  }



  $clean = '';

  foreach ($root->childNodes as $child) {

    $clean .= $doc->saveHTML($child);

  }



  // 6) Petit nettoyage final

  $clean = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $clean);

  $clean = trim((string) $clean);



  libxml_clear_errors();



  return $clean;

} 



function ouinpo_sf_generate_clean_wxr(string $target_file = '') {

  if (!defined('OUINPO_SF_SRC')) {

    return new \WP_Error('missing_src', 'Constante OUINPO_SF_SRC absente.');

  }



  if ($target_file === '') {

    $target_file = trailingslashit(OUINPO_SF_SRC) . 'site-segfault-clean.xml';

  }



  if (class_exists(__NAMESPACE__.'\\Storage')) {

    \OuInPo\SegFault\Storage::ensure_dirs();

  } else {

    wp_mkdir_p(dirname($target_file));

  }



$exclude_slugs = ouinpo_sf_get_clean_wxr_excluded_slugs();



  $q = new \WP_Query(array(

    'post_type'      => array('post', 'page'),

    'post_status'    => 'publish',

    'posts_per_page' => -1,

    'orderby'        => 'modified',

    'order'          => 'DESC',

    'fields'         => 'ids',

    'no_found_rows'  => true,

  ));



  $items = array();

  $count = 0;



  foreach ($q->posts as $pid) {

    $slug = (string) get_post_field('post_name', $pid);

    if (in_array($slug, $exclude_slugs, true)) {

      continue;

    }



    $ptype = (string) get_post_type($pid);

    if (!in_array($ptype, array('post', 'page'), true)) {

      continue;

    }



    $title = (string) get_the_title($pid);

    $url   = (string) get_permalink($pid);



    $html = (string) apply_filters('the_content', get_post_field('post_content', $pid));

    $html = ouinpo_sf_clean_html_for_wxr($html, $pid);

    

if (

  strpos($html, 'admin-ajax.php') !== false ||

  strpos($html, 'data-nonce=') !== false ||

  strpos($html, 'um-account') !== false ||

  strpos($html, 'um-profile') !== false ||

  strpos($html, 'um-form') !== false ||

  strpos($html, 'ouinpo-teacher ouinpo-competences') !== false ||

  strpos($html, 't-kpi-students') !== false ||

  strpos($html, 'ds-kpi-evaluated') !== false ||

  strpos($html, 'ouinpo-res-files') !== false ||

  strpos($html, '?ouinpo_download=') !== false

) {

  continue;

}

    

    if (trim(wp_strip_all_tags($html)) === '') {

      continue;

    }



    $post_date_gmt = (string) get_post_field('post_date_gmt', $pid);

    $post_date     = (string) get_post_field('post_date', $pid);

    $rss_date      = mysql2date(DATE_RSS, ($post_date_gmt && $post_date_gmt !== '0000-00-00 00:00:00') ? $post_date_gmt : $post_date, false);



    $items[] =

      "  <item>\n" .

      "    <title>" . ouinpo_sf_xml_cdata($title) . "</title>\n" .

      "    <link>" . ouinpo_sf_xml_text($url) . "</link>\n" .

      "    <pubDate>" . ouinpo_sf_xml_text($rss_date) . "</pubDate>\n" .

      "    <guid isPermaLink=\"false\">" . ouinpo_sf_xml_text($url) . "</guid>\n" .

      "    <content:encoded>" . ouinpo_sf_xml_cdata($html) . "</content:encoded>\n" .

      "    <wp:post_type>" . ouinpo_sf_xml_cdata($ptype) . "</wp:post_type>\n" .

      "    <wp:status><![CDATA[publish]]></wp:status>\n" .

      "  </item>";



    $count++;

  }



  wp_reset_postdata();



  $xml =

    "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n" .

    "<rss version=\"2.0\"\n" .

    "  xmlns:content=\"http://purl.org/rss/1.0/modules/content/\"\n" .

    "  xmlns:wp=\"http://wordpress.org/export/1.2/\"\n" .

    ">\n" .

    "<channel>\n" .

    "  <title>" . ouinpo_sf_xml_cdata(get_bloginfo('name')) . "</title>\n" .

    "  <link>" . ouinpo_sf_xml_text(home_url('/')) . "</link>\n" .

    "  <description>" . ouinpo_sf_xml_cdata((string) get_bloginfo('description')) . "</description>\n" .

    "  <language>" . ouinpo_sf_xml_text((string) get_bloginfo('language')) . "</language>\n" .

    "  <wp:wxr_version>1.2</wp:wxr_version>\n" .

    "  <wp:base_site_url>" . ouinpo_sf_xml_text(home_url('/')) . "</wp:base_site_url>\n" .

    "  <wp:base_blog_url>" . ouinpo_sf_xml_text(home_url('/')) . "</wp:base_blog_url>\n" .

         implode("\n", $items) . "\n" .

    "</channel>\n" .

    "</rss>\n";



  $ok = @file_put_contents($target_file, $xml);

  if ($ok === false) {

    return new \WP_Error('write_failed', 'Impossible d’écrire le fichier XML : '.$target_file);

  }



  update_option('ouinpo_sf_wxr_path', $target_file, false);



  if (method_exists('\OuInPo\SegFault\RAG', 'wxr_reset_batch_state')) {

    \OuInPo\SegFault\RAG::wxr_reset_batch_state();

  } else {

    delete_option('ouinpo_sf_wxr_cursor');

    delete_option('ouinpo_sf_wxr_done');

    delete_option('ouinpo_sf_wxr_total');

  }



  return array(

    'file'  => $target_file,

    'count' => $count,

  );

}



function ouinpo_sf_get_clean_wxr_excluded_slugs(): array {

  return apply_filters('ouinpo_sf_clean_wxr_excluded_slugs', array(

    'mentions-legales',

    'politique-de-confidentialite',

    'politique-de-confidentialite-cookies',


    'mon-compte',

    'console',



    // Pages compte / UM

    'login',

    'register',

    'user',

    'account',

    'merci-inscription',



    // Pages applicatives / mécaniques

    'laboratoire-secret',

    'sample-page',

    'depot-eleve',

    'suivi-des-eleves',

    'exercice',

    'ressources-a-telecharger',



    // Pages de suivi / gamification / palmarès

    'registre-des-apprentis-satrapes-et-para-satrapes',

    'palmares',

    'palmares-des-apprentis',

    'badges',

    'mes-badges',

    'registre',

  ));

}



function ouinpo_sf_rag_index_coverage_audit(): array {

  global $wpdb;



  \OuInPo\SegFault\DB::init();

  $pdo = \OuInPo\SegFault\DB::pdo();



  $excluded_slugs = ouinpo_sf_get_clean_wxr_excluded_slugs();



  $q = new \WP_Query(array(

    'post_type'      => array('post', 'page'),

    'post_status'    => 'publish',

    'posts_per_page' => -1,

    'orderby'        => 'title',

    'order'          => 'ASC',

    'fields'         => 'ids',

    'no_found_rows'  => true,

  ));



  $expected = [];

  $missing = [];

  $present = [];



  $st = $pdo->prepare("

    SELECT COUNT(*)

    FROM documents

    WHERE origin = 'site'

      AND url = :url

  ");



  foreach ($q->posts as $pid) {

    $slug = (string) get_post_field('post_name', $pid);



    if (in_array($slug, $excluded_slugs, true)) {

      continue;

    }



    $ptype = (string) get_post_type($pid);

    if (!in_array($ptype, array('post', 'page'), true)) {

      continue;

    }



    $title = (string) get_the_title($pid);

    $url   = (string) get_permalink($pid);



    // Même nettoyage que le WXR propre : si le contenu devient vide ou technique, on ignore.

    $html = (string) apply_filters('the_content', get_post_field('post_content', $pid));

    $html = ouinpo_sf_clean_html_for_wxr($html, $pid);



    if (

      strpos($html, 'admin-ajax.php') !== false ||

      strpos($html, 'data-nonce=') !== false ||

      strpos($html, 'um-account') !== false ||

      strpos($html, 'um-profile') !== false ||

      strpos($html, 'um-form') !== false ||

      strpos($html, 'ouinpo-teacher ouinpo-competences') !== false ||

      strpos($html, 't-kpi-students') !== false ||

      strpos($html, 'ds-kpi-evaluated') !== false ||

      strpos($html, 'ouinpo-res-files') !== false ||

      strpos($html, '?ouinpo_download=') !== false

    ) {

      continue;

    }



    if (trim(wp_strip_all_tags($html)) === '') {

      continue;

    }



    $expected[] = [

      'id'    => (int)$pid,

      'title' => $title,

      'slug'  => $slug,

      'type'  => $ptype,

      'url'   => $url,

    ];



    $st->execute([':url' => $url]);

    $n = (int) $st->fetchColumn();



    if ($n > 0) {

      $present[] = [

        'id'     => (int)$pid,

        'title'  => $title,

        'slug'   => $slug,

        'type'   => $ptype,

        'url'    => $url,

        'chunks' => $n,

      ];

    } else {

      $missing[] = [

        'id'    => (int)$pid,

        'title' => $title,

        'slug'  => $slug,

        'type'  => $ptype,

        'url'   => $url,

      ];

    }

  }



  wp_reset_postdata();



  return [

    'expected_count' => count($expected),

    'present_count'  => count($present),

    'missing_count'  => count($missing),

    'present'        => $present,

    'missing'        => $missing,

  ];

}



function ouinpo_sf_render_rag_status_box(): void {

  try {

    \OuInPo\SegFault\DB::init();

    $pdo = \OuInPo\SegFault\DB::pdo();



    // Colonnes attendues

    $cols = [];

    foreach ($pdo->query("PRAGMA table_info(documents)") as $col) {

      if (!empty($col['name'])) {

        $cols[] = (string)$col['name'];

      }

    }



    $needed = [

      'embedding_provider',

      'embedding_model',

      'content_hash',

      'chunk_index',

      'section_title',

      'visibility',

    ];



    $missing = array_values(array_diff($needed, $cols));



    // Totaux

    $total_chunks = (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();



    $with_sections = 0;

    if (in_array('section_title', $cols, true)) {

      $with_sections = (int)$pdo

        ->query("SELECT COUNT(*) FROM documents WHERE section_title IS NOT NULL AND TRIM(section_title) <> ''")

        ->fetchColumn();

    }



    // Répartition visibilité

    $visibility_rows = [];

    if (in_array('visibility', $cols, true)) {

      $visibility_rows = $pdo

        ->query("

          SELECT COALESCE(NULLIF(TRIM(visibility), ''), '(vide)') AS visibility, COUNT(*) AS n

          FROM documents

          GROUP BY COALESCE(NULLIF(TRIM(visibility), ''), '(vide)')

          ORDER BY n DESC

        ")

        ->fetchAll(\PDO::FETCH_ASSOC);

    }



    // Répartition embedding

    $embedding_rows = [];

    if (in_array('embedding_provider', $cols, true) && in_array('embedding_model', $cols, true)) {

      $embedding_rows = $pdo

        ->query("

          SELECT

            COALESCE(NULLIF(TRIM(embedding_provider), ''), '(vide)') AS provider,

            COALESCE(NULLIF(TRIM(embedding_model), ''), '(vide)') AS model,

            COUNT(*) AS n

          FROM documents

          GROUP BY

            COALESCE(NULLIF(TRIM(embedding_provider), ''), '(vide)'),

            COALESCE(NULLIF(TRIM(embedding_model), ''), '(vide)')

          ORDER BY n DESC

        ")

        ->fetchAll(\PDO::FETCH_ASSOC);

    }



    // Origines

    $origin_rows = $pdo

      ->query("

        SELECT COALESCE(NULLIF(TRIM(origin), ''), '(vide)') AS origin, COUNT(*) AS n

        FROM documents

        GROUP BY COALESCE(NULLIF(TRIM(origin), ''), '(vide)')

        ORDER BY n DESC

      ")

      ->fetchAll(\PDO::FETCH_ASSOC);



    // Batch WXR

    $xml = (string)get_option('ouinpo_sf_wxr_path', '');

    $cursor = (int)get_option('ouinpo_sf_wxr_cursor', 0);

    $total  = (int)get_option('ouinpo_sf_wxr_total', 0);

    $done   = (int)get_option('ouinpo_sf_wxr_done', 0);



    if ($xml && is_file($xml) && $total === 0 && method_exists('\OuInPo\SegFault\RAG', 'wxr_count_items')) {

      $total = \OuInPo\SegFault\RAG::wxr_count_items($xml);

    }



    $pct = ($total > 0) ? (int)floor(100 * min($cursor, $total) / $total) : 0;



    $provider = (string)get_option('ouinpo_sf_rag_embedding_provider', 'openai');

    $albert_embedding = (string)get_option('ouinpo_sf_albert_embedding_model', 'BAAI/bge-m3');

    $albert_reranker  = (string)get_option('ouinpo_sf_albert_reranker_model', 'BAAI/bge-reranker-v2-m3');

    $rerank_candidates = (int)get_option('ouinpo_sf_rag_rerank_candidates', 40);



    ?>

    <div class="notice notice-info" class="ouinpo-sf-admin-notice ouinpo-sf-admin-notice--rag">

      <h2 class="ouinpo-sf-title-compact">État du RAG SegFault</h2>



      <?php if ($missing): ?>

        <p class="ouinpo-sf-error ouinpo-sf-admin-message">

          <strong>Schéma incomplet :</strong>

          colonnes manquantes :

          <code><?php echo esc_html(implode(', ', $missing)); ?></code>

        </p>

      <?php else: ?>

        <p class="ouinpo-sf-ok ouinpo-sf-admin-message">

          <strong>Schéma SQLite OK.</strong>

          Les colonnes RAG Albert sont présentes.

        </p>

      <?php endif; ?>



      <div class="ouinpo-sf-grid">

        <div class="ouinpo-sf-admin-card">

          <strong>Chunks indexés</strong><br>

          <span class="ouinpo-sf-admin-number"><?php echo esc_html((string)$total_chunks); ?></span>

        </div>



        <div class="ouinpo-sf-admin-card">

          <strong>Chunks avec section</strong><br>

          <span class="ouinpo-sf-admin-number"><?php echo esc_html((string)$with_sections); ?></span>

          <?php if ($total_chunks > 0): ?>

            <span class="ouinpo-sf-muted">

              — <?php echo esc_html((string)round(100 * $with_sections / max(1, $total_chunks))); ?> %

            </span>

          <?php endif; ?>

        </div>



        <div class="ouinpo-sf-admin-card">

          <strong>Moteur embedding actif</strong><br>

          <code><?php echo esc_html($provider); ?></code>

          <?php if ($provider === 'albert'): ?>

            <br><code><?php echo esc_html($albert_embedding); ?></code>

          <?php endif; ?>

        </div>



        <div class="ouinpo-sf-admin-card">

          <strong>Reranker</strong><br>

          <code><?php echo esc_html($albert_reranker); ?></code><br>

          <span class="ouinpo-sf-muted"><?php echo esc_html((string)$rerank_candidates); ?> candidats</span>

        </div>

      </div>



      <div class="ouinpo-sf-grid-wide">

        <div class="ouinpo-sf-admin-card">

          <strong>Répartition par visibilité</strong>

          <?php if (empty($visibility_rows)): ?>

            <p>Aucune donnée.</p>

          <?php else: ?>

            <ul class="ouinpo-sf-list-bottomless">

              <?php foreach ($visibility_rows as $r): ?>

                <li>

                  <code><?php echo esc_html((string)$r['visibility']); ?></code>

                  — <?php echo esc_html((string)$r['n']); ?>

                </li>

              <?php endforeach; ?>

            </ul>

          <?php endif; ?>

        </div>



        <div class="ouinpo-sf-admin-card">

          <strong>Répartition par origine</strong>

          <?php if (empty($origin_rows)): ?>

            <p>Aucune donnée.</p>

          <?php else: ?>

            <ul class="ouinpo-sf-list-bottomless">

              <?php foreach ($origin_rows as $r): ?>

                <li>

                  <code><?php echo esc_html((string)$r['origin']); ?></code>

                  — <?php echo esc_html((string)$r['n']); ?>

                </li>

              <?php endforeach; ?>

            </ul>

          <?php endif; ?>

        </div>



        <div class="ouinpo-sf-admin-card">

          <strong>Embeddings stockés</strong>

          <?php if (empty($embedding_rows)): ?>

            <p>Aucune donnée.</p>

          <?php else: ?>

            <ul class="ouinpo-sf-list-bottomless">

              <?php foreach ($embedding_rows as $r): ?>

                <li>

                  <code><?php echo esc_html((string)$r['provider']); ?></code>

                  /

                  <code><?php echo esc_html((string)$r['model']); ?></code>

                  — <?php echo esc_html((string)$r['n']); ?>

                </li>

              <?php endforeach; ?>

            </ul>

          <?php endif; ?>

        </div>

      </div>



      <p class="ouinpo-sf-admin-row">

        <strong>Batch WXR :</strong>

        <code><?php echo esc_html($xml ?: '(non défini)'); ?></code><br>

        Progression :

        <strong><?php echo esc_html((string)$cursor); ?></strong>

        /

        <strong><?php echo esc_html($total > 0 ? (string)$total : '?'); ?></strong>

        <?php if ($total > 0): ?>

          — <?php echo esc_html((string)$pct); ?> %

        <?php endif; ?>

        <?php if ($done === 1): ?>

          — <strong class="ouinpo-sf-ok">terminé</strong>

        <?php endif; ?>

      </p>

    </div>

    <?php



  } catch (\Throwable $e) {

    echo '<div class="notice notice-error" class="ouinpo-sf-admin-notice"><p><strong>Diagnostic RAG impossible :</strong> '

      . esc_html($e->getMessage())

      . '</p></div>';

  }

}



function ouinpo_sf_render_rag_tester(): void {

  if (!\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_AI)) {

    return;

  }



  $question = '';

  $result = null;



  if (isset($_POST['ouinpo_sf_rag_test']) && check_admin_referer('ouinpo_sf_rag_test')) {

    $question = isset($_POST['rag_question'])

      ? sanitize_text_field(wp_unslash($_POST['rag_question']))

      : '';



    if ($question !== '' && method_exists('\OuInPo\SegFault\RAG', 'debug_search')) {

      $result = \OuInPo\SegFault\RAG::debug_search($question, 8);

    }

  }



  ?>

  <h2>Tester le RAG</h2>



  <form method="post" class="ouinpo-sf-boxed-form">

    <?php wp_nonce_field('ouinpo_sf_rag_test'); ?>



    <p>

      <label for="rag_question"><strong>Question de test</strong></label><br>

      <input

        type="text"

        id="rag_question"

        name="rag_question"

        class="regular-text"

        class="ouinpo-sf-query-input"

        value="<?php echo esc_attr($question); ?>"

        placeholder="Exemple : explique-moi les piles"

      />

      <button type="submit" name="ouinpo_sf_rag_test" class="button button-primary">

        Tester

      </button>

    </p>



    <p class="description">

      Affiche les chunks retrouvés par le RAG, avant et après reranking. Visible uniquement côté admin.

    </p>

  </form>



  <?php if (is_array($result)): ?>

    <div class="notice notice-info" class="ouinpo-sf-admin-notice">

      <h3 class="ouinpo-sf-title-tight">Résultat du test RAG</h3>



      <?php if (!empty($result['error'])): ?>

        <p class="ouinpo-sf-error">

          <strong>Erreur :</strong> <?php echo esc_html((string)$result['error']); ?>

        </p>

      <?php else: ?>

        <p>

          <strong>Question :</strong>

          <code><?php echo esc_html((string)($result['query'] ?? '')); ?></code><br>

          <strong>Embedding :</strong>

          <code><?php echo esc_html((string)($result['provider'] ?? '')); ?></code>

          /

          <code><?php echo esc_html((string)($result['model'] ?? '')); ?></code><br>

          <strong>Tokens utiles :</strong>

            <br>

            <strong>Demande d’exercice détectée :</strong>

            <code><?php echo !empty($result['exercise_intent']) ? 'oui' : 'non'; ?></code>          

            <?php

              $tokens = $result['tokens'] ?? [];

              if (!is_array($tokens)) {

                $tokens = [$tokens];

              }

            ?>

            <code><?php echo esc_html(implode(', ', array_map('strval', $tokens))); ?></code>

        </p>



        <?php

          $render_rows = function(array $rows, string $title): void {

            echo '<h4>' . esc_html($title) . '</h4>';



            if (empty($rows)) {

              echo '<p>Aucun chunk trouvé.</p>';

              return;

            }



            echo '<table class="widefat striped" class="ouinpo-sf-table-spaced">';

            echo '<thead><tr>';

            echo '<th class="ouinpo-sf-col-score">Score</th>';

            echo '<th>Document</th>';

            echo '<th class="ouinpo-sf-col-origin">Origine</th>';

            echo '<th class="ouinpo-sf-col-visibility">Visibilité</th>';

            echo '<th>Extrait</th>';

            echo '</tr></thead><tbody>';



            foreach ($rows as $r) {

              $score = isset($r['score']) ? number_format((float)$r['score'], 4, ',', ' ') : '';



              $title_doc = trim((string)($r['title'] ?? ''));

              $section = trim((string)($r['section_title'] ?? ''));

              $url = trim((string)($r['url'] ?? ''));



              echo '<tr>';



              echo '<td><code>' . esc_html($score) . '</code></td>';



              echo '<td>';

              echo '<strong>' . esc_html($title_doc !== '' ? $title_doc : 'Document') . '</strong>';



              if ($section !== '') {

                echo '<br><span class="ouinpo-sf-muted">section : ' . esc_html($section) . '</span>';

              }



              if ($url !== '') {

                echo '<br><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">ouvrir</a>';

              }



              echo '</td>';



              echo '<td>';

              echo '<code>' . esc_html((string)($r['origin'] ?? '')) . '</code>';

              if (!empty($r['ptype'])) {

                echo '<br><code>' . esc_html((string)$r['ptype']) . '</code>';

              }

              echo '</td>';



              echo '<td><code>' . esc_html((string)($r['visibility'] ?? '')) . '</code></td>';



              echo '<td>';

              echo esc_html(wp_trim_words((string)($r['chunk'] ?? ''), 55, '…'));

              echo '</td>';



              echo '</tr>';

            }



            echo '</tbody></table>';

          };



        $label_after = !empty($result['exercise_intent'])

          ? 'Résultats retenus sans reranker'

          : 'Après reranker';

        

        $render_rows($result['after'] ?? [], $label_after);

        $render_rows($result['before'] ?? [], 'Avant reranker');

        ?>

      <?php endif; ?>

    </div>

  <?php endif; ?>

  <?php

}



function ouinpo_sf_index_missing_site_posts(int $limit = 30): array {

  global $wpdb;



  \OuInPo\SegFault\DB::init();

  $pdo = \OuInPo\SegFault\DB::pdo();



  $limit = max(1, min(100, $limit));



$result = [

  'checked' => 0,

  'missing_seen' => 0,

  'indexed_posts' => 0,

  'chunks_added' => 0,

  'skipped_empty' => 0,

  'stop_due_budget' => false,

  'errors' => [],

];



$embedding_budget = (int) get_option('ouinpo_sf_max_embeddings_run', 120);

if ($embedding_budget <= 0) {

  $embedding_budget = 120;

}



  // URLs déjà présentes dans SQLite

  $indexed_urls = [];

  try {

    $rows = $pdo

      ->query("SELECT DISTINCT url FROM documents WHERE origin = 'site' AND url IS NOT NULL AND TRIM(url) <> ''")

      ->fetchAll(\PDO::FETCH_COLUMN);



    foreach ($rows as $u) {

      $u = rtrim((string)$u, '/');

      if ($u !== '') {

        $indexed_urls[$u] = true;

      }

    }

  } catch (\Throwable $e) {

    $result['errors'][] = 'Lecture SQLite impossible : '.$e->getMessage();

    return $result;

  }



$exclude_slugs = ouinpo_sf_get_clean_wxr_excluded_slugs();



  $q = new \WP_Query([

    'post_type'      => ['post', 'page'],

    'post_status'    => 'publish',

    'posts_per_page' => -1,

    'orderby'        => 'title',

    'order'          => 'ASC',

    'fields'         => 'ids',

    'no_found_rows'  => true,

  ]);



  foreach ($q->posts as $pid) {

    $result['checked']++;



    $slug = (string)get_post_field('post_name', $pid);

    if (in_array($slug, $exclude_slugs, true)) {

      continue;

    }



    $ptype = (string)get_post_type($pid);

    if (!in_array($ptype, ['post', 'page'], true)) {

      continue;

    }



    $url = (string)get_permalink($pid);

    $url_key = rtrim($url, '/');



    if (isset($indexed_urls[$url_key])) {

      continue;

    }



    $result['missing_seen']++;



    if ($result['missing_seen'] > $limit) {

      break;

    }



    $title = (string)get_the_title($pid);



    $html = (string)apply_filters('the_content', get_post_field('post_content', $pid));



    if (function_exists(__NAMESPACE__.'\\ouinpo_sf_clean_html_for_wxr')) {

      $html = ouinpo_sf_clean_html_for_wxr($html, $pid);

    }



    // Même logique d’exclusion que le WXR propre

    if (

      strpos($html, 'admin-ajax.php') !== false ||

      strpos($html, 'data-nonce=') !== false ||

      strpos($html, 'um-account') !== false ||

      strpos($html, 'um-profile') !== false ||

      strpos($html, 'um-form') !== false ||

      strpos($html, 'ouinpo-teacher ouinpo-competences') !== false ||

      strpos($html, 't-kpi-students') !== false ||

      strpos($html, 'ds-kpi-evaluated') !== false ||

      strpos($html, 'ouinpo-res-files') !== false ||

      strpos($html, '?ouinpo_download=') !== false

    ) {

      continue;

    }



    $text = wp_strip_all_tags($html);

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $text = preg_replace("/[ \t]+/", " ", $text);

    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    $text = trim((string)$text);



    if ($text === '') {

      $result['skipped_empty']++;

      continue;

    }



if ((int)$result['chunks_added'] >= $embedding_budget) {

  $result['stop_due_budget'] = true;

  break;

}



    try {

      $chunks = \OuInPo\SegFault\RAG::index_text($text, 'site', $url, $title, $ptype);



      if ($chunks > 0) {

        $result['indexed_posts']++;

        $result['chunks_added'] += (int)$chunks;

        $indexed_urls[$url_key] = true;

} else {

  if ((int)$result['chunks_added'] >= $embedding_budget) {

    $result['stop_due_budget'] = true;

    break;

  }



  $result['errors'][] = 'Aucun chunk ajouté pour : '.$title.' — '.$url;

}

    } catch (\Throwable $e) {

      $result['errors'][] = 'Erreur sur '.$title.' : '.$e->getMessage();

    }

  }



  wp_reset_postdata();



  return $result;

}



function ouinpo_sf_table_column_exists(string $table, string $column): bool {

  global $wpdb;



  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0) ?: [];

  return in_array($column, $cols, true);

}



function ouinpo_sf_get_exercise_statement_column(): string {

  global $wpdb;



  $t_exo = $wpdb->prefix . 'ouin_exo_exercises';



  if (ouinpo_sf_table_column_exists($t_exo, 'statement_html')) {

    return 'statement_html';

  }



  if (ouinpo_sf_table_column_exists($t_exo, 'statement')) {

    return 'statement';

  }



  return '';

}



function ouinpo_sf_rag_exercises_coverage_audit(): array {

  global $wpdb;



  \OuInPo\SegFault\DB::init();

  $pdo = \OuInPo\SegFault\DB::pdo();



  $t_exo = $wpdb->prefix . 'ouin_exo_exercises';

  $statement_col = ouinpo_sf_get_exercise_statement_column();



  if ($statement_col === '') {

    throw new \RuntimeException('Aucune colonne statement_html ou statement trouvée dans la table des exercices.');

  }



  $rows = $wpdb->get_results("

    SELECT id, title, slug, {$statement_col} AS statement_html

    FROM {$t_exo}

    WHERE is_active = 1

    ORDER BY id ASC

  ", ARRAY_A) ?: [];



  $expected = [];

  $present = [];

  $missing = [];



  $st = $pdo->prepare("

    SELECT COUNT(*)

    FROM documents

    WHERE origin = 'exercise'

      AND url = :url

  ");



  foreach ($rows as $r) {

    $id = (int)($r['id'] ?? 0);

    if ($id <= 0) {

      continue;

    }



    $title = (string)($r['title'] ?? '');

    $slug = (string)($r['slug'] ?? '');

    $url = home_url('/exercice/?exo=' . $id);



    $text = trim(wp_strip_all_tags((string)($r['statement_html'] ?? '')));

    if ($text === '') {

      continue;

    }



    $item = [

      'id' => $id,

      'title' => $title,

      'slug' => $slug,

      'url' => $url,

    ];



    $expected[] = $item;



    $st->execute([':url' => $url]);

    $n = (int)$st->fetchColumn();



    if ($n > 0) {

      $item['chunks'] = $n;

      $present[] = $item;

    } else {

      $missing[] = $item;

    }

  }



  return [

    'expected_count' => count($expected),

    'present_count' => count($present),

    'missing_count' => count($missing),

    'present' => $present,

    'missing' => $missing,

  ];

}



function ouinpo_sf_index_missing_exercises(int $limit = 40): array {

  global $wpdb;



  \OuInPo\SegFault\DB::init();

  $pdo = \OuInPo\SegFault\DB::pdo();



  $limit = max(1, min(100, $limit));



  $result = [

    'checked' => 0,

    'missing_seen' => 0,

    'indexed_exercises' => 0,

    'chunks_added' => 0,

    'skipped_empty' => 0,

    'stop_due_budget' => false,

    'errors' => [],

  ];



  $embedding_budget = (int)get_option('ouinpo_sf_max_embeddings_run', 120);

  if ($embedding_budget <= 0) {

    $embedding_budget = 120;

  }



  $t_exo = $wpdb->prefix . 'ouin_exo_exercises';

  $statement_col = ouinpo_sf_get_exercise_statement_column();



  if ($statement_col === '') {

    $result['errors'][] = 'Aucune colonne statement_html ou statement trouvée.';

    return $result;

  }



  $indexed_urls = [];



  try {

    $rows = $pdo

      ->query("SELECT DISTINCT url FROM documents WHERE origin = 'exercise' AND url IS NOT NULL AND TRIM(url) <> ''")

      ->fetchAll(\PDO::FETCH_COLUMN);



    foreach ($rows as $u) {

      $u = rtrim((string)$u, '/');

      if ($u !== '') {

        $indexed_urls[$u] = true;

      }

    }

  } catch (\Throwable $e) {

    $result['errors'][] = 'Lecture SQLite impossible : ' . $e->getMessage();

    return $result;

  }



$t_link = $wpdb->prefix . 'ouin_exo_exercise_competency';

$t_comp = $wpdb->prefix . 'ouin_exo_competencies';

$t_diff = $wpdb->prefix . 'ouin_exo_difficulties';

$t_exam = $wpdb->prefix . 'ouin_exo_exam_meta';



$has_exam_meta = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_exam)) === $t_exam);



$rows = $wpdb->get_results("

  SELECT

    e.id,

    e.title,

    e.slug,

    e.{$statement_col} AS statement_html,

    d.label AS difficulty_label,

    d.slug AS difficulty_slug,

    GROUP_CONCAT(

      DISTINCT CONCAT(

        COALESCE(c.domain, ''),

        ' — ',

        COALESCE(c.competency, '')

      )

      SEPARATOR ' | '

    ) AS bo_competencies

    " . ($has_exam_meta ? ",

    em.exam_type,

    em.source_type,

    em.theme_bac,

    em.bac_format

    " : ",

    NULL AS exam_type,

    NULL AS source_type,

    NULL AS theme_bac,

    NULL AS bac_format

    ") . "

  FROM {$t_exo} e

  LEFT JOIN {$t_diff} d ON d.id = e.difficulty_id

  LEFT JOIN {$t_link} ec ON ec.exercise_id = e.id

  LEFT JOIN {$t_comp} c ON c.id = ec.competency_id

  " . ($has_exam_meta ? "LEFT JOIN {$t_exam} em ON em.exercise_id = e.id" : "") . "

  WHERE e.is_active = 1

  GROUP BY e.id

  ORDER BY e.id ASC

", ARRAY_A) ?: [];



  foreach ($rows as $r) {

    $result['checked']++;



    $id = (int)($r['id'] ?? 0);

    if ($id <= 0) {

      continue;

    }



    $url = home_url('/exercice/?exo=' . $id);

    $url_key = rtrim($url, '/');



    if (isset($indexed_urls[$url_key])) {

      continue;

    }



    $result['missing_seen']++;



    if ($result['missing_seen'] > $limit) {

      break;

    }



    if ((int)$result['chunks_added'] >= $embedding_budget) {

      $result['stop_due_budget'] = true;

      break;

    }



    $title = trim((string)($r['title'] ?? 'Exercice ' . $id));

    $slug = trim((string)($r['slug'] ?? ''));



    $statement = (string)($r['statement_html'] ?? '');

    $text = wp_strip_all_tags($statement);

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $text = preg_replace("/[ \t]+/", " ", $text);

    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    $text = trim((string)$text);



    if ($text === '') {

      $result['skipped_empty']++;

      continue;

    }



$difficulty_label = trim((string)($r['difficulty_label'] ?? ''));

$difficulty_slug  = trim((string)($r['difficulty_slug'] ?? ''));

$bo_competencies  = trim((string)($r['bo_competencies'] ?? ''));



$exam_type   = trim((string)($r['exam_type'] ?? ''));

$source_type = trim((string)($r['source_type'] ?? ''));

$theme_bac   = trim((string)($r['theme_bac'] ?? ''));

$bac_format  = trim((string)($r['bac_format'] ?? ''));



$pedagogical_keywords = [];



$hay = remove_accents(mb_strtolower(

  $title . ' ' .

  $slug . ' ' .

  $bo_competencies . ' ' .

  $theme_bac . ' ' .

  $text

));



if (str_contains($hay, 'pile') || str_contains($hay, 'lifo')) {

  $pedagogical_keywords[] = 'pile';

  $pedagogical_keywords[] = 'LIFO';

  $pedagogical_keywords[] = 'empiler';

  $pedagogical_keywords[] = 'dépiler';

  $pedagogical_keywords[] = 'sommet';

  $pedagogical_keywords[] = 'parenthésage';

  $pedagogical_keywords[] = 'parenthesage';

}



if (str_contains($hay, 'file') || str_contains($hay, 'fifo')) {

  $pedagogical_keywords[] = 'file';

  $pedagogical_keywords[] = 'FIFO';

  $pedagogical_keywords[] = 'enfiler';

  $pedagogical_keywords[] = 'défiler';

  $pedagogical_keywords[] = 'premier entré premier sorti';

}



if (str_contains($hay, 'arbre')) {

  $pedagogical_keywords[] = 'arbre';

  $pedagogical_keywords[] = 'racine';

  $pedagogical_keywords[] = 'feuille';

  $pedagogical_keywords[] = 'hauteur';

  $pedagogical_keywords[] = 'parcours';

}



if (str_contains($hay, 'sql') || str_contains($hay, 'base') || str_contains($hay, 'relation')) {

  $pedagogical_keywords[] = 'SQL';

  $pedagogical_keywords[] = 'base de données';

  $pedagogical_keywords[] = 'relation';

  $pedagogical_keywords[] = 'clé primaire';

  $pedagogical_keywords[] = 'requête';

}



$pedagogical_keywords = array_values(array_unique($pedagogical_keywords));



$full_text =

  "Type de document : exercice NSI\n" .

  "Origine : banque d'exercices OuInPo\n" .

  "Exercice : " . $title . "\n" .

  "Identifiant exercice : " . $id . "\n" .

  ($slug !== '' ? "Slug : " . $slug . "\n" : "") .

  "Adresse : " . $url . "\n" .

  ($difficulty_label !== '' ? "Difficulté : " . $difficulty_label . "\n" : "") .

  ($difficulty_slug !== '' ? "Difficulté slug : " . $difficulty_slug . "\n" : "") .

  ($bo_competencies !== '' ? "Compétences BO : " . $bo_competencies . "\n" : "") .

  ($exam_type !== '' ? "Type examen : " . $exam_type . "\n" : "") .

  ($source_type !== '' ? "Source bac : " . $source_type . "\n" : "") .

  ($theme_bac !== '' ? "Thème bac : " . $theme_bac . "\n" : "") .

  ($bac_format !== '' ? "Format bac : " . $bac_format . "\n" : "") .

  (!empty($pedagogical_keywords) ? "Mots-clés pédagogiques : " . implode(', ', $pedagogical_keywords) . "\n" : "") .

  "\nÉnoncé de l'exercice :\n" . $text;



    try {

      $chunks = \OuInPo\SegFault\RAG::index_text($full_text, 'exercise', $url, $title, 'exercise');



      if ($chunks > 0) {

        $result['indexed_exercises']++;

        $result['chunks_added'] += (int)$chunks;

        $indexed_urls[$url_key] = true;

      } else {

        $result['errors'][] = 'Aucun chunk ajouté pour : ' . $title . ' — ' . $url;

      }

    } catch (\Throwable $e) {

      $result['errors'][] = 'Erreur sur exercice #' . $id . ' — ' . $title . ' : ' . $e->getMessage();

    }

  }



  return $result;

}



function ouinpo_sf_purge_exercise_chunks(): int {

  \OuInPo\SegFault\DB::init();

  $pdo = \OuInPo\SegFault\DB::pdo();



  $st_count = $pdo->prepare("

    SELECT COUNT(*)

    FROM documents

    WHERE origin = 'exercise'

  ");

  $st_count->execute();

  $count = (int)$st_count->fetchColumn();



  $st = $pdo->prepare("

    DELETE FROM documents

    WHERE origin = 'exercise'

  ");

  $st->execute();



  return $count;

}



function admin_page() {

  DB::init();



if (isset($_POST['ouinpo_sf_purge_exercise_chunks']) && check_admin_referer('ouinpo_sf_purge_exercise_chunks')) {

  $deleted = ouinpo_sf_purge_exercise_chunks();



  echo '<div class="notice notice-success"><p>'

    . esc_html('Chunks d’exercices purgés : '.$deleted.'. Tu peux maintenant relancer l’indexation des exercices manquants.')

    . '</p></div>';

}



  if (isset($_POST['ouinpo_sf_index_missing_exercises']) && check_admin_referer('ouinpo_sf_index_missing_exercises')) {

    $st = ouinpo_sf_index_missing_exercises(40);



    $msg = sprintf(

      'Indexation des exercices : %d exercices examinés, %d absents repérés, %d exercices indexés, %d chunks ajoutés, %d exercices vides ignorés.',

      (int)($st['checked'] ?? 0),

      (int)($st['missing_seen'] ?? 0),

      (int)($st['indexed_exercises'] ?? 0),

      (int)($st['chunks_added'] ?? 0),

      (int)($st['skipped_empty'] ?? 0)

    );



    if (!empty($st['stop_due_budget'])) {

      $msg .= ' Budget embeddings atteint : relance le bouton pour continuer.';

    }



    echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';



    if (!empty($st['errors'])) {

      echo '<div class="notice notice-warning"><p><strong>Détails :</strong></p><ul>';

      foreach (array_slice($st['errors'], 0, 10) as $err) {

        echo '<li><code>' . esc_html((string)$err) . '</code></li>';

      }

      echo '</ul></div>';

    }

  }



  if (isset($_POST['ouinpo_sf_index_missing']) && check_admin_referer('ouinpo_sf_index_missing')) {

    $st = ouinpo_sf_index_missing_site_posts(30);



    $msg = sprintf(

      'Indexation des manquants : %d contenus WP examinés, %d absents repérés, %d contenus indexés, %d chunks ajoutés, %d contenus vides ignorés.',

      (int)($st['checked'] ?? 0),

      (int)($st['missing_seen'] ?? 0),

      (int)($st['indexed_posts'] ?? 0),

      (int)($st['chunks_added'] ?? 0),

      (int)($st['skipped_empty'] ?? 0)

    );



    if (!empty($st['stop_due_budget'])) {

      $msg .= ' Budget embeddings atteint : relance le bouton pour continuer.';

    }



    echo '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';



    if (!empty($st['errors'])) {

      echo '<div class="notice notice-warning"><p><strong>Détails :</strong></p><ul>';

      foreach (array_slice($st['errors'], 0, 10) as $err) {

        echo '<li><code>' . esc_html((string)$err) . '</code></li>';

      }

      echo '</ul></div>';

    }

  }



    if (isset($_POST['ouinpo_sf_generate_clean_wxr']) && check_admin_referer('ouinpo_sf_generate_clean_wxr')) {

      $res = ouinpo_sf_generate_clean_wxr();

    

      if (is_wp_error($res)) {

        echo '<div class="error"><p>'.esc_html($res->get_error_message()).'</p></div>';

      } else {

        $msg = sprintf(

          'WXR SegFault généré : %d contenus exportés. Fichier : %s',

          (int) ($res['count'] ?? 0),

          (string) ($res['file'] ?? '')

        );

        echo '<div class="updated"><p>'.esc_html($msg).'</p></div>';

      }

    }    

    

  if (isset($_POST['ouinpo_sf_reindex']) && check_admin_referer('ouinpo_sf_reindex')) {

    $n = RAG::reindex_all();

    echo '<div class="updated"><p>Réindexation terminée : '.$n.' chunks.</p></div>';

  }



  if (isset($_POST['ouinpo_sf_purge_docs']) && check_admin_referer('ouinpo_sf_purge_docs')) {

    DB::init();

    DB::purge_documents(false);



    delete_option('ouinpo_sf_last_site_index_gmt');

    delete_option('ouinpo_sf_sources_index');

    if (method_exists('\OuInPo\SegFault\RAG', 'wxr_reset_batch_state')) {

      RAG::wxr_reset_batch_state();

    } else {

      delete_option('ouinpo_sf_wxr_cursor');

      delete_option('ouinpo_sf_wxr_done');

      delete_option('ouinpo_sf_wxr_total');

    }



    echo '<div class="updated"><p>Index RAG purgé (table <code>documents</code>) + marqueurs réinitialisés.</p></div>';

  }



  if (isset($_POST['ouinpo_sf_purge_reindex']) && check_admin_referer('ouinpo_sf_purge_reindex')) {

    DB::init();



    $purge_memory  = !empty($_POST['purge_memory']);

    $clear_sources = !empty($_POST['clear_sources']);



    DB::purge_documents(false);

    if ($purge_memory) DB::purge_memory(false);



    delete_option('ouinpo_sf_last_site_index_gmt');

    delete_option('ouinpo_sf_sources_index');



    if (method_exists('\OuInPo\SegFault\RAG', 'wxr_reset_batch_state')) {

      RAG::wxr_reset_batch_state();

    } else {

      delete_option('ouinpo_sf_wxr_cursor');

      delete_option('ouinpo_sf_wxr_done');

      delete_option('ouinpo_sf_wxr_total');

    }



    $deleted = 0;

    if ($clear_sources) $deleted = ouinpo_sf_clear_sources_folder(false);



    $n = RAG::reindex_all();



    $msg = 'Purge + réindex terminé : '.$n.' chunks.';

    if ($purge_memory)  $msg .= ' Mémoire chat purgée.';

    if ($clear_sources) $msg .= ' Fichiers supprimés dans sources/: '.$deleted.'.';



    echo '<div class="updated"><p>'.esc_html($msg).'</p></div>';

  }



if (isset($_POST['ouinpo_sf_run_cron_now']) && check_admin_referer('ouinpo_sf_run_cron_now')) {

  $site = 0;

  $sources = 0;



  if (method_exists('\OuInPo\SegFault\RAG', 'cron_reindex_nightly_stats')) {

    $st = RAG::cron_reindex_nightly_stats();

    $site = (int)($st['site'] ?? 0);

    $sources = (int)($st['sources'] ?? 0);

    

    $mode = (string)($st['mode'] ?? 'inconnu');

    $xml_exists = !empty($st['xml_exists']);

    $xml = (string)($st['xml'] ?? '');

    

    $wxr_before = (int)($st['wxr_before'] ?? 0);

    $wxr_after = (int)($st['wxr_after'] ?? 0);

    $wxr_total = (int)($st['wxr_total_after'] ?? 0);

    $wxr_done = (int)($st['wxr_done_after'] ?? 0);

  } else {

    RAG::cron_reindex_nightly();

  }



  $fix_site = function_exists(__NAMESPACE__.'\\ouinpo_sf_index_missing_site_posts')

    ? ouinpo_sf_index_missing_site_posts(30)

    : [];



  $fix_exo = function_exists(__NAMESPACE__.'\\ouinpo_sf_index_missing_exercises')

    ? ouinpo_sf_index_missing_exercises(40)

    : [];



    $src_dir = defined('OUINPO_SF_SRC') ? trailingslashit(OUINPO_SF_SRC) : '';

    $src_files = [];

    

    if ($src_dir !== '' && is_dir($src_dir)) {

      $src_files = array_merge(

        glob($src_dir.'*.{md,txt}', GLOB_BRACE) ?: [],

        glob($src_dir.'*.pdf') ?: []

      );

    }

    

    $src_count = count($src_files);



    $msg = sprintf(

      'Cron lancé : mode=%s — site=%d, sources=%d — WXR : %d/%s → %d/%s%s — XML : %s',

      $mode,

      $site,

      $sources,

      $wxr_before,

      $wxr_total > 0 ? (string)$wxr_total : '?',

      $wxr_after,

      $wxr_total > 0 ? (string)$wxr_total : '?',

      $wxr_done === 1 ? ' terminé' : '',

      $xml_exists ? 'trouvé' : 'introuvable'

    );



  if (!empty($fix_site['stop_due_budget']) || !empty($fix_exo['stop_due_budget'])) {

    $msg .= ' Budget embeddings atteint : relance le bouton pour continuer.';

  }



  echo '<div class="updated"><p>'.esc_html($msg).'</p></div>';



  $errors = array_merge(

    array_slice($fix_site['errors'] ?? [], 0, 5),

    array_slice($fix_exo['errors'] ?? [], 0, 5)

  );



  if (!empty($errors)) {

    echo '<div class="notice notice-warning"><p><strong>Détails :</strong></p><ul>';

    foreach ($errors as $err) {

      echo '<li><code>' . esc_html((string)$err) . '</code></li>';

    }

    echo '</ul></div>';

  }

}

  if (isset($_POST['ouinpo_sf_wxr_reset']) && check_admin_referer('ouinpo_sf_wxr_reset')) {

    if (method_exists('\OuInPo\SegFault\RAG', 'wxr_reset_batch_state')) {

      RAG::wxr_reset_batch_state();

    } else {

      delete_option('ouinpo_sf_wxr_cursor');

      delete_option('ouinpo_sf_wxr_done');

      delete_option('ouinpo_sf_wxr_total');

    }

    echo '<div class="updated"><p>Batch WXR réinitialisé (curseur supprimé).</p></div>';

  }



  if (isset($_POST['ouinpo_sf_wxr_init']) && check_admin_referer('ouinpo_sf_wxr_init')) {

    DB::init();



    $purge_memory  = !empty($_POST['purge_memory']);

    $clear_sources = !empty($_POST['clear_sources']);



    DB::purge_documents(false);

    if ($purge_memory) DB::purge_memory(false);



    delete_option('ouinpo_sf_last_site_index_gmt');

    delete_option('ouinpo_sf_sources_index');



    if (method_exists('\OuInPo\SegFault\RAG', 'wxr_reset_batch_state')) {

      RAG::wxr_reset_batch_state();

    } else {

      delete_option('ouinpo_sf_wxr_cursor');

      delete_option('ouinpo_sf_wxr_done');

      delete_option('ouinpo_sf_wxr_total');

    }



    $deleted = 0;

    if ($clear_sources) $deleted = ouinpo_sf_clear_sources_folder(false);



    $msg = 'Batch WXR initialisé : index purgé + curseur à 0.';

    if ($purge_memory)  $msg .= ' Mémoire chat purgée.';

    if ($clear_sources) $msg .= ' sources/ nettoyé ('.$deleted.' fichiers).';



    echo '<div class="updated"><p>'.esc_html($msg).'</p></div>';

  }



  if (isset($_POST['ouinpo_sf_wxr_step']) && check_admin_referer('ouinpo_sf_wxr_step')) {

    $xml = (string) get_option('ouinpo_sf_wxr_path', '');

    $batch = (int)($_POST['batch_items'] ?? 10);

    if ($batch <= 0) $batch = 10;

    if ($batch > 50) $batch = 50;



    if (!method_exists('\OuInPo\SegFault\RAG', 'index_wxr_xml_batch')) {

      echo '<div class="error"><p>La méthode <code>RAG::index_wxr_xml_batch()</code> n’existe pas. Mets à jour <code>RAG.php</code> avec le batch WXR.</p></div>';

    } else {

      $st = RAG::index_wxr_xml_batch($xml, $batch);



$missing = 0;

$missing_exo = 0;



if (!empty($st['done']) && function_exists(__NAMESPACE__.'\\ouinpo_sf_index_missing_site_posts')) {

  $st_missing = ouinpo_sf_index_missing_site_posts(30);

  $missing = (int)($st_missing['indexed_posts'] ?? 0);

}



if (!empty($st['done']) && function_exists(__NAMESPACE__.'\\ouinpo_sf_index_missing_exercises')) {

  $st_missing_exo = ouinpo_sf_index_missing_exercises(40);

  $missing_exo = (int)($st_missing_exo['indexed_exercises'] ?? 0);

}



$msg = sprintf(

  "Batch WXR : +%d items (cursor=%d/%d)%s%s%s",

  (int)($st['indexed_items'] ?? 0),

  (int)($st['cursor'] ?? 0),

  (int)($st['total'] ?? 0),

  !empty($st['done']) ? ' — terminé ✅' : '',

  $missing > 0 ? ' — compléments WP indexés : '.$missing : '',

  $missing_exo > 0 ? ' — exercices indexés : '.$missing_exo : ''

);



echo '<div class="updated"><p>'.esc_html($msg).'</p></div>';

    }

  }



  ?>

  <div class="wrap">

    <h1>SegFault — Réglages</h1>

    <?php ouinpo_sf_render_rag_status_box(); ?>

    <?php ouinpo_sf_render_rag_tester(); ?>

    

<?php

try {

  $audit = ouinpo_sf_rag_index_coverage_audit();

  ?>

  <div class="notice notice-warning" class="ouinpo-sf-admin-notice">

    <h2 class="ouinpo-sf-title-tight">Audit couverture RAG WordPress → SQLite</h2>



    <p>

      Contenus WordPress indexables :

      <strong><?php echo esc_html((string)$audit['expected_count']); ?></strong><br>

      Présents dans SQLite :

      <strong class="ouinpo-sf-ok"><?php echo esc_html((string)$audit['present_count']); ?></strong><br>

      Absents de SQLite :

      <strong class="ouinpo-sf-error"><?php echo esc_html((string)$audit['missing_count']); ?></strong>

    </p>



    <?php if (!empty($audit['missing'])): ?>

      <form method="post" class="ouinpo-sf-form-row">

  <?php wp_nonce_field('ouinpo_sf_index_missing'); ?>

  <input

    type="submit"

    name="ouinpo_sf_index_missing"

    class="button button-primary"

    value="🧩 Indexer les contenus manquants"

  />

</form>



      <table class="widefat striped" class="ouinpo-sf-table-top">

        <thead>

          <tr>

            <th>ID WP</th>

            <th>Titre</th>

            <th>Slug</th>

            <th>Type</th>

            <th>URL</th>

          </tr>

        </thead>

        <tbody>

          <?php foreach (array_slice($audit['missing'], 0, 80) as $row): ?>

            <tr>

              <td><code><?php echo esc_html((string)$row['id']); ?></code></td>

              <td><?php echo esc_html((string)$row['title']); ?></td>

              <td><code><?php echo esc_html((string)$row['slug']); ?></code></td>

              <td><code><?php echo esc_html((string)$row['type']); ?></code></td>

              <td>

                <a href="<?php echo esc_url((string)$row['url']); ?>" target="_blank" rel="noopener noreferrer">

                  ouvrir

                </a>

              </td>

            </tr>

          <?php endforeach; ?>

        </tbody>

      </table>



      <?php if ((int)$audit['missing_count'] > 80): ?>

        <p class="description">

          Liste limitée aux 80 premiers contenus manquants.

        </p>

      <?php endif; ?>



    <?php else: ?>

      <p class="ouinpo-sf-ok">

        Tous les contenus WordPress indexables semblent présents dans SQLite.

      </p>

    <?php endif; ?>

  </div>

  <?php

} catch (\Throwable $e) {

  echo '<div class="notice notice-error" class="ouinpo-sf-admin-notice"><p><strong>Audit couverture RAG impossible :</strong> '

    . esc_html($e->getMessage())

    . '</p></div>';

}

?>    



<?php

try {

  $exo_audit = ouinpo_sf_rag_exercises_coverage_audit();

  ?>

  <div class="notice notice-info" class="ouinpo-sf-admin-notice">

    <h2 class="ouinpo-sf-title-tight">Audit RAG des exercices MySQL → SQLite</h2>



    <p>

      Exercices actifs :

      <strong><?php echo esc_html((string)$exo_audit['expected_count']); ?></strong><br>

      Présents dans SQLite :

      <strong class="ouinpo-sf-ok"><?php echo esc_html((string)$exo_audit['present_count']); ?></strong><br>

      Absents de SQLite :

      <strong class="ouinpo-sf-error"><?php echo esc_html((string)$exo_audit['missing_count']); ?></strong>

    </p>





    <?php if (!empty($exo_audit['missing'])): ?>

    

      <form method="post" class="ouinpo-sf-form-row">

          <?php wp_nonce_field('ouinpo_sf_index_missing_exercises'); ?>

          <input

            type="submit"

            name="ouinpo_sf_index_missing_exercises"

            class="button button-primary"

            value="🧩 Indexer les exercices manquants"

          />

        </form>      

    

    <h3>Premiers exercices absents</h3>

    

    <table class="widefat striped" class="ouinpo-sf-table-top">

      <thead>

        <tr>

          <th>ID</th>

          <th>Titre</th>

          <th>Slug</th>

          <th>URL attendue</th>

        </tr>

      </thead>

      <tbody>

        <?php foreach (array_slice($exo_audit['missing'], 0, 80) as $row): ?>

          <tr>

            <td><code><?php echo esc_html((string)$row['id']); ?></code></td>

            <td><?php echo esc_html((string)$row['title']); ?></td>

            <td><code><?php echo esc_html((string)$row['slug']); ?></code></td>

            <td>

              <a href="<?php echo esc_url((string)$row['url']); ?>" target="_blank" rel="noopener noreferrer">

                ouvrir

              </a>

            </td>

          </tr>

        <?php endforeach; ?>

      </tbody>

    </table>



      <?php if ((int)$exo_audit['missing_count'] > 80): ?>

  

        <p class="description">Liste limitée aux 80 premiers exercices absents.</p>

      <?php endif; ?>

    <?php else: ?>

      <p class="ouinpo-sf-ok">Tous les exercices actifs semblent présents dans SQLite.</p>

    <?php endif; ?>

  </div>

  <?php

} catch (\Throwable $e) {

  ?>

  <div class="notice notice-error" class="ouinpo-sf-admin-notice">

    <p>

      <strong>Audit exercices RAG impossible :</strong>

      <?php echo esc_html($e->getMessage()); ?>

    </p>

  </div>

  <?php

}

?>

    

    <form method="post" action="options.php">

      <?php settings_fields('ouinpo_sf'); do_settings_sections('ouinpo_sf'); ?>

      <div class="ouinpo-sf-settings-tabs" role="tablist" aria-label="Sections des reglages IA">
        <button type="button" class="button button-secondary is-active" data-ouinpo-sf-tab="overview">Vue d'ensemble</button>
        <button type="button" class="button button-secondary" data-ouinpo-sf-tab="providers">Fournisseurs</button>
        <button type="button" class="button button-secondary" data-ouinpo-sf-tab="prompts">Prompts</button>
        <button type="button" class="button button-secondary" data-ouinpo-sf-tab="public">Acces publics</button>
        <button type="button" class="button button-secondary" data-ouinpo-sf-tab="privacy">Confidentialite</button>
        <button type="button" class="button button-secondary" data-ouinpo-sf-tab="rag">RAG / indexation</button>
      </div>

        <table class="form-table ouinpo-sf-settings-table" role="presentation">

        

          <tr>

            <th colspan="2">

              <h2>Architecture IA active</h2>

            </th>

          </tr>

        

          <tr>

            <th>Résumé</th>

            <td>

              <div class="ouinpo-sf-info-box">

                <p class="ouinpo-sf-title-tight">

                  <strong>Albert API</strong> est le moteur principal utilisé par SegFault pour les réponses IA :

                  chat, aide sur les pages, correction d’exercices, exercices type bac et sujets pratiques.

                </p>

                <p>

                  <strong>OpenAI / ChatGPT</strong> est conservé comme moteur de secours

                  <strong>uniquement pour les utilisateurs connectés</strong>, si Albert ne répond pas.

                </p>

                <p>

                  Les <strong>visiteurs non connectés</strong> peuvent utiliser l’IA publique, mais leurs échanges

                  ne créent ni mémoire, ni progression, ni badge, ni tentative enregistrée.

                </p>

              </div>

            </td>

          </tr>

        

          <tr>

            <th colspan="2">

              <h2>Reglages IA generaux</h2>

            </th>

          </tr>

          <tr>
            <th>Activation IA</th>
            <td>
              <label><input type="checkbox" name="ouinpo_ai_enabled" value="1" <?php checked(1, (int)get_option('ouinpo_ai_enabled', 0)); ?> /> Activer globalement les usages IA OuInPo.</label><br>
              <label><input type="checkbox" name="ouinpo_ai_public_enabled" value="1" <?php checked(1, (int)get_option('ouinpo_ai_public_enabled', 0)); ?> /> Autoriser les usages IA publics anonymes.</label><br>
              <label><input type="checkbox" name="ouinpo_projects_student_ai_enabled" value="1" <?php checked(1, (int)get_option('ouinpo_projects_student_ai_enabled', 0)); ?> /> Activer l IA eleve Projects pour les brouillons de portfolio.</label><br>
              <label><input type="checkbox" name="ouinpo_ai_debug_logs" value="1" <?php checked(1, (int)get_option('ouinpo_ai_debug_logs', 0)); ?> /> Activer les logs IA/RAG synthetiques quand WP_DEBUG est actif.</label>
            </td>
          </tr>

          <tr>
            <th>Usages IA autorises</th>
            <td>
              <?php foreach ([
                'chat_rag' => 'Chat / RAG',
                'exercise_help' => 'Aide aux exercices',
                'exercise_correction' => 'Correction exercices',
                'gate_validation' => 'Validation Gate',
                'practical_correction' => 'Correction sujets pratiques',
                'feedback_generation' => 'Generation de feedback',
                'pedagogical_suggestions' => 'Suggestions pedagogiques',
              ] as $usage_key => $usage_label): ?>
                <?php $usage_default = $usage_key === 'gate_validation' ? 0 : 1; ?>
                <label><input type="checkbox" name="<?php echo esc_attr('ouinpo_ai_usage_' . $usage_key); ?>" value="1" <?php checked(1, (int)get_option('ouinpo_ai_usage_' . $usage_key, $usage_default)); ?> /> <?php echo esc_html($usage_label); ?></label><br>
              <?php endforeach; ?>
            </td>
          </tr>

          <tr>
            <th>Correction IA admin</th>
            <td>
              <label><input type="checkbox" name="ouinpo_ai_correction_scans_enabled" value="1" <?php checked(1, (int)get_option('ouinpo_ai_correction_scans_enabled', 0)); ?> /> Activer la correction assistee par IA a partir de scans/OCR.</label><br>
              <label><input type="checkbox" name="ouinpo_ai_file_correction_enabled" value="1" <?php checked(1, (int)get_option('ouinpo_ai_file_correction_enabled', 0)); ?> /> Activer la correction assistee par IA a partir de fichiers eleves.</label><br>
              <label><input type="checkbox" name="ouinpo_ai_correction_keep_scans" value="1" <?php checked(1, (int)get_option('ouinpo_ai_correction_keep_scans', 1)); ?> /> Conserver les scans apres validation professeur.</label><br>
              <label><input type="checkbox" name="ouinpo_ai_file_correction_keep_files" value="1" <?php checked(1, (int)get_option('ouinpo_ai_file_correction_keep_files', 1)); ?> /> Conserver les fichiers eleves apres validation professeur.</label><br>
              <label>Taille max scan/PDF (Mo)
                <input type="number" min="1" max="100" name="ouinpo_ai_correction_max_file_mb" value="<?php echo esc_attr((int)get_option('ouinpo_ai_correction_max_file_mb', 12)); ?>" class="ouinpo-sf-input-small" />
              </label>
              <label>Taille max fichier eleve (Mo)
                <input type="number" min="1" max="100" name="ouinpo_ai_file_correction_max_file_mb" value="<?php echo esc_attr((int)get_option('ouinpo_ai_file_correction_max_file_mb', 8)); ?>" class="ouinpo-sf-input-small" />
              </label>
              <label>Conservation fichiers (jours)
                <input type="number" min="1" max="3650" name="ouinpo_ai_file_correction_retention_days" value="<?php echo esc_attr((int)get_option('ouinpo_ai_file_correction_retention_days', 30)); ?>" class="ouinpo-sf-input-small" />
              </label>
              <p class="description">Ces workflows restent admin uniquement. Ils necessitent aussi l'activation IA globale et l'usage "Suggestions pedagogiques".</p>
            </td>
          </tr>

          <tr>
            <th>Fournisseurs</th>
            <td>
              <?php foreach (['ouinpo_ai_default_provider' => 'Defaut', 'ouinpo_ai_public_provider' => 'Anonymes', 'ouinpo_ai_logged_provider' => 'Connectes'] as $option => $label): ?>
                <?php $provider = (string)get_option($option, 'albert'); ?>
                <label><?php echo esc_html($label); ?>
                  <select name="<?php echo esc_attr($option); ?>">
                    <option value="albert" <?php selected($provider, 'albert'); ?>>Albert</option>
                    <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI</option>
                  </select>
                </label><br>
              <?php endforeach; ?>
            </td>
          </tr>

          <tr>
            <th>API, modeles et limites</th>
            <td>
              <label>URL de base <input name="ouinpo_ai_api_base_url" class="regular-text" value="<?php echo esc_attr(get_option('ouinpo_ai_api_base_url', 'https://albert.api.etalab.gouv.fr/v1')); ?>" /></label><br>
              <label>Cle API <input type="password" name="ouinpo_ai_api_key" class="regular-text" autocomplete="off" value="<?php echo esc_attr(\Ouinpo\Suite\Core\AiSettings::secret_input_value('ouinpo_ai_api_key')); ?>" placeholder="<?php echo esc_attr(\Ouinpo\Suite\Core\AiSettings::secret_configured('ouinpo_ai_api_key') ? 'Cle configuree' : ''); ?>" /></label><br>
              <p class="description"><?php echo esc_html(\Ouinpo\Suite\Core\AiSettings::secret_status_label('ouinpo_ai_api_key')); ?>. Laisser la valeur masquee conserve la cle existante.</p>
              <?php $ocrProvider = (string)get_option('ouinpo_ai_ocr_provider', 'albert'); ?>
              <label>API OCR
                <select name="ouinpo_ai_ocr_provider">
                  <option value="albert" <?php selected($ocrProvider, 'albert'); ?>>Albert OCR</option>
                  <option value="none" <?php selected($ocrProvider, 'none'); ?>>Desactivee</option>
                </select>
              </label><br>
              <label>Modele chat <input name="ouinpo_ai_chat_model" value="<?php echo esc_attr(get_option('ouinpo_ai_chat_model', 'openai/gpt-oss-120b')); ?>" /></label>
              <label>Modele code <input name="ouinpo_ai_code_model" value="<?php echo esc_attr(get_option('ouinpo_ai_code_model', 'openweight-code')); ?>" /></label>
              <label>Modele embeddings <input name="ouinpo_ai_embedding_model" value="<?php echo esc_attr(get_option('ouinpo_ai_embedding_model', 'BAAI/bge-m3')); ?>" /></label>
              <label>Modele OCR <input name="ouinpo_ai_ocr_model" value="<?php echo esc_attr(get_option('ouinpo_ai_ocr_model', '')); ?>" placeholder="defaut Albert" /></label><br>
              <p class="description">L OCR Albert envoie au fournisseur un lien temporaire vers le PDF scanne. A utiliser uniquement pour des sujets non sensibles, avec IA activee volontairement, cle API configuree, quotas et timeout adaptes.</p>
              <label>Timeout <input type="number" min="5" max="120" name="ouinpo_ai_timeout" value="<?php echo esc_attr((int)get_option('ouinpo_ai_timeout', 45)); ?>" class="ouinpo-sf-input-small" /></label>
              <label>Max tokens <input type="number" min="128" max="8000" name="ouinpo_ai_max_tokens" value="<?php echo esc_attr((int)get_option('ouinpo_ai_max_tokens', 800)); ?>" class="ouinpo-sf-input-medium" /></label>
              <label>Temperature <input type="number" min="0" max="2" step="0.1" name="ouinpo_ai_temperature" value="<?php echo esc_attr((float)get_option('ouinpo_ai_temperature', 0.3)); ?>" class="ouinpo-sf-input-small" /></label>
              <label>Top p <input type="number" min="0" max="1" step="0.05" name="ouinpo_ai_top_p" value="<?php echo esc_attr((float)get_option('ouinpo_ai_top_p', 1)); ?>" class="ouinpo-sf-input-small" /></label><br>
              <label>Frequency penalty <input type="number" min="-2" max="2" step="0.1" name="ouinpo_ai_frequency_penalty" value="<?php echo esc_attr((float)get_option('ouinpo_ai_frequency_penalty', 0)); ?>" class="ouinpo-sf-input-small" /></label>
              <label>Presence penalty <input type="number" min="-2" max="2" step="0.1" name="ouinpo_ai_presence_penalty" value="<?php echo esc_attr((float)get_option('ouinpo_ai_presence_penalty', 0)); ?>" class="ouinpo-sf-input-small" /></label><br>
            </td>
          </tr>

          <tr>
            <th colspan="2">
              <h2>Quotas IA / Albert</h2>
            </th>
          </tr>

          <tr>
            <th>Requetes IA</th>
            <td>
              <?php foreach ([
                'ouinpo_ai_public_ip_per_minute' => 'Public anonyme par IP / minute',
                'ouinpo_ai_public_ip_per_day' => 'Public anonyme par IP / jour',
                'ouinpo_ai_public_global_per_minute' => 'Public global site / minute',
                'ouinpo_ai_public_global_per_day' => 'Public global site / jour',
                'ouinpo_ai_student_per_minute' => 'Eleve connecte / minute',
                'ouinpo_ai_student_per_day' => 'Eleve connecte / jour',
                'ouinpo_ai_exercise_ai_per_minute' => 'Correction exercice / minute',
                'ouinpo_ai_exercise_ai_per_day' => 'Correction exercice / jour',
                'ouinpo_ai_practical_ai_per_minute' => 'Sujet pratique / minute',
                'ouinpo_ai_practical_ai_per_day' => 'Sujet pratique / jour',
                'ouinpo_ai_teacher_per_minute' => 'Enseignant / minute',
                'ouinpo_ai_teacher_per_day' => 'Enseignant / jour',
                'ouinpo_ai_projects_student_per_minute' => 'Projects IA eleve / minute',
                'ouinpo_ai_projects_student_per_day' => 'Projects IA eleve / jour',
              ] as $option => $label): ?>
                <label><?php echo esc_html($label); ?>
                  <input type="number" min="0" max="10000" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr((int)get_option($option, \Ouinpo\Suite\Core\AiSettings::defaults()[$option] ?? 0)); ?>" class="ouinpo-sf-input-small" />
                </label><br>
              <?php endforeach; ?>
              <p class="description">Les quotas publics restent volontairement prudents. Les visiteurs anonymes sont limites par IP hashee et par plafond global du site.</p>
            </td>
          </tr>

          <tr>
            <th>Max tokens par contexte</th>
            <td>
              <?php foreach ([
                'ouinpo_ai_public_chat_max_tokens' => 'Chat public',
                'ouinpo_ai_exercise_ai_max_tokens' => 'Correction exercice',
                'ouinpo_ai_practical_ai_max_tokens' => 'Correction sujet pratique',
                'ouinpo_ai_projects_student_max_tokens' => 'Projects IA eleve',
                'ouinpo_ai_public_rag_max_tokens' => 'Contexte RAG public',
              ] as $option => $label): ?>
                <label><?php echo esc_html($label); ?>
                  <input type="number" min="128" max="8000" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr((int)get_option($option, \Ouinpo\Suite\Core\AiSettings::defaults()[$option] ?? 800)); ?>" class="ouinpo-sf-input-small" />
                </label><br>
              <?php endforeach; ?>
              <p class="description">Reference indicative Albert : openai/gpt-oss-120b = 50 RPM / 5000 RPD. Garder les limites internes nettement en dessous, surtout pour les acces anonymes.</p>
            </td>
          </tr>

          <tr>
            <th colspan="2">
              <h2>Prompts, personas et messages</h2>
            </th>
          </tr>

          <tr>
            <th>Messages et prompts</th>
            <td>
              <label>Message IA desactivee<br><input name="ouinpo_ai_disabled_message" class="large-text" value="<?php echo esc_attr(get_option('ouinpo_ai_disabled_message', 'L assistant IA est desactive pour le moment.')); ?>" /></label><br>
              <label>Information RGPD / usage pedagogique<br><textarea name="ouinpo_ai_privacy_notice" rows="3" class="large-text"><?php echo esc_textarea(get_option('ouinpo_ai_privacy_notice', \Ouinpo\Suite\Core\AiSettings::defaults()['ouinpo_ai_privacy_notice'])); ?></textarea></label>
              <?php foreach ([
                'ouinpo_ai_persona_general' => 'Persona generale',
                'ouinpo_ai_persona_public' => 'Persona publique',
                'ouinpo_ai_persona_student' => 'Persona eleve',
                'ouinpo_ai_persona_teacher' => 'Persona professeur',
                'ouinpo_ai_rag_system_prompt' => 'Consigne systeme RAG',
                'ouinpo_ai_exercise_correction_prompt' => 'Consigne corrections exercices',
                'ouinpo_ai_practical_correction_prompt' => 'Consigne sujets pratiques',
                'ouinpo_ai_suggestions_prompt' => 'Consigne suggestions pedagogiques',
                'ouinpo_ai_out_of_program_guardrails' => 'Garde-fous hors programme par niveau',
              ] as $option => $label): ?>
                <label><?php echo esc_html($label); ?><br><textarea name="<?php echo esc_attr($option); ?>" rows="3" class="large-text"><?php echo esc_textarea(get_option($option, \Ouinpo\Suite\Core\AiSettings::defaults()[$option] ?? '')); ?></textarea></label><br>
              <?php endforeach; ?>
              <label>Niveau anonyme par defaut <input name="ouinpo_ai_anonymous_default_school_level" value="<?php echo esc_attr(get_option('ouinpo_ai_anonymous_default_school_level', 'premiere')); ?>" /></label><br>
              <label><input type="checkbox" name="ouinpo_ai_show_rag_sources" value="1" <?php checked(1, (int)get_option('ouinpo_ai_show_rag_sources', 1)); ?> /> Afficher les references / sources dans les reponses RAG.</label>
            </td>
          </tr>

          <tr>
            <th colspan="2">
              <h2>Acces publics</h2>
            </th>
          </tr>

          <tr>
            <th>Acces REST publics anonymes</th>
            <td>
              <?php foreach ([
                'ouinpo_public_exercises_enabled' => 'Voir les exercices',
                'ouinpo_public_hints_enabled' => 'Voir les indices',
                'ouinpo_public_solutions_enabled' => 'Voir les solutions',
                'ouinpo_public_practical_subjects_enabled' => 'Voir les sujets pratiques',
                'ouinpo_public_practical_files_enabled' => 'Acceder aux fichiers des sujets pratiques',
              ] as $option => $label): ?>
                <label><input type="checkbox" name="<?php echo esc_attr($option); ?>" value="1" <?php checked(1, (int)get_option($option, 0)); ?> /> <?php echo esc_html($label); ?></label><br>
              <?php endforeach; ?>
              <p class="description">Sur une nouvelle installation, ces acces sont fermes par defaut. Une migration conserve les acces publics des sites deja installes.</p>
            </td>
          </tr>

          <tr>

            <th colspan="2">

              <h2>Moteur principal — Albert API</h2>

            </th>

          </tr>

        

          <tr>

              

            <tr>

              <th>Activer Albert comme moteur principal</th>

              <td>

                <label>

                  <input type="checkbox" name="ouinpo_sf_albert_enabled" value="1" <?php checked(1, (int)get_option('ouinpo_sf_albert_enabled', 0)); ?> />

                  Utiliser Albert comme moteur IA principal du site.

                </label>

                <p class="description">

                  Cette option concerne les usages connectés et internes : chat élève, aide pédagogique,

                  correction d’exercices, sujets pratiques et génération de réponses par SegFault.

                </p>

              </td>

            </tr>              

              

            <th>Autoriser Albert pour les visiteurs non connectés</th>

            <td>

              <label>

                <input type="checkbox" name="ouinpo_sf_public_albert_enabled" value="1" <?php checked(1, (int)get_option('ouinpo_sf_public_albert_enabled', 0)); ?> />

                Autoriser les visiteurs non connectés à utiliser les fonctionnalités IA publiques.

              </label>

                <p class="description">

                  Cette option autorise uniquement les visiteurs non connectés à utiliser les fonctionnalités IA publiques.

                  Elle ne décide pas du moteur IA principal pour les élèves connectés.

                </p>

            </td>

          </tr>

        

          <tr>

            <th>Albert API Key</th>

            <td>

              <input type="password" name="ouinpo_sf_albert_api_key"

                value="<?php echo esc_attr(\Ouinpo\Suite\Core\AiSettings::secret_input_value('ouinpo_sf_albert_api_key')); ?>"

                class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr(\Ouinpo\Suite\Core\AiSettings::secret_configured('ouinpo_sf_albert_api_key') ? 'Cle configuree' : ''); ?>" />

              <p class="description">

                Clé utilisée pour le moteur IA principal. Elle ne doit jamais être exposée côté navigateur.

              </p>

            </td>

          </tr>

        

          <tr>

            <th>Albert Base URL</th>

            <td>

              <input name="ouinpo_sf_albert_base_url" class="regular-text"

                value="<?php echo esc_attr(get_option('ouinpo_sf_albert_base_url', 'https://albert.api.etalab.gouv.fr/v1')); ?>" />

              <p class="description">

                URL de base de l’API Albert. Valeur attendue :

                <code>https://albert.api.etalab.gouv.fr/v1</code>

              </p>

            </td>

          </tr>

        

          <tr>

            <th>Modèle Albert</th>

            <td>

              <input name="ouinpo_sf_albert_model" class="regular-text"

                value="<?php echo esc_attr(get_option('ouinpo_sf_albert_model', 'openai/gpt-oss-120b')); ?>" />

              <p class="description">

                Modèle principal de génération de texte. Modèle actuellement utilisé :

                <code>openai/gpt-oss-120b</code>.

              </p>

            </td>

          </tr>



        <tr>

          <th>Modele Albert - OCR</th>

          <td>

            <input name="ouinpo_sf_albert_ocr_model" class="regular-text"

              value="<?php echo esc_attr(get_option('ouinpo_sf_albert_ocr_model', '')); ?>" placeholder="defaut Albert" />

            <p class="description">

              Modele Albert utilise par l endpoint OCR pour lire les PDF scannes avant le decoupage des annales. Laisse vide pour utiliser le modele par defaut de l API. Le PDF est transmis au fournisseur via un lien temporaire : utiliser uniquement des sujets non sensibles et partageables.

            </p>

          </td>

        </tr>



        <tr>

          <th>Modèle Albert — correction de code</th>

          <td>

            <input name="ouinpo_sf_albert_code_model" class="regular-text"

              value="<?php echo esc_attr(get_option('ouinpo_sf_albert_code_model', 'openweight-code')); ?>" />

            <p class="description">

              Modèle Albert utilisé pour la correction de code Python dans les exercices et sujets pratiques.

              Exemple : <code>openweight-code</code>.

              Ce réglage permet de changer le modèle sans modifier le plugin.

            </p>

          </td>

        </tr>

        

          <tr>

            <th colspan="2">

              <h2>Secours connecté — OpenAI / ChatGPT</h2>

            </th>

          </tr>

        

          <tr>

            <th>OpenAI API Key</th>

            <td>

              <input type="password" name="ouinpo_sf_openai_api_key"

                value="<?php echo esc_attr(\Ouinpo\Suite\Core\AiSettings::secret_input_value('ouinpo_sf_openai_api_key')); ?>"

                class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr(\Ouinpo\Suite\Core\AiSettings::secret_configured('ouinpo_sf_openai_api_key') ? 'Cle configuree' : ''); ?>" />

              <p class="description">

                Clé utilisée uniquement comme secours pour les utilisateurs connectés si Albert est indisponible.

                Selon la configuration actuelle du RAG, elle peut aussi servir aux embeddings OpenAI.

              </p>

            </td>

          </tr>

        

          <tr>

            <th>Modèle texte OpenAI de secours</th>

            <td>

              <input name="ouinpo_sf_model"

                value="<?php echo esc_attr(get_option('ouinpo_sf_model','gpt-5-mini')); ?>" />

              <p class="description">

                Modèle appelé seulement en fallback pour les élèves ou utilisateurs connectés.

                Les visiteurs non connectés ne doivent pas déclencher ce moteur.

              </p>

            </td>

          </tr>

        <tr>
  <th colspan="2">
    <h2>Information RGPD / IA</h2>
  </th>
</tr>

<tr>
  <th>URL de la page d’information</th>
  <td>
    <input
      type="text"
      name="ouinpo_sf_ai_notice_url"
      value="<?php echo esc_attr(get_option('ouinpo_sf_ai_notice_url', '')); ?>"
      class="regular-text"
    />
    <p class="description">
      Lien affiché sous les messages d’information IA. Peut être vide, une URL complète ou un chemin relatif du site.
    </p>
  </td>
</tr>

<tr>
  <th>Message IA publique</th>
  <td>
    <textarea
      name="ouinpo_sf_ai_notice_public"
      rows="4"
      class="large-text"
    ><?php echo esc_textarea(get_option('ouinpo_sf_ai_notice_public', "Assistant IA public — N’écris pas de nom, prénom, note, adresse ou information personnelle. Les réponses peuvent contenir des erreurs.")); ?></textarea>
    <p class="description">
      Message affiché aux visiteurs non connectés avant ou près du chat public.
    </p>
  </td>
</tr>

<tr>
  <th>Message IA élèves connectés</th>
  <td>
    <textarea
      name="ouinpo_sf_ai_notice_logged"
      rows="4"
      class="large-text"
    ><?php echo esc_textarea(get_option('ouinpo_sf_ai_notice_logged', "IA pédagogique — N’écris pas de données personnelles. Les réponses proposées par l’assistant doivent être vérifiées et ne remplacent pas le professeur.")); ?></textarea>
    <p class="description">
      Message affiché aux utilisateurs connectés.
    </p>
  </td>
</tr>

        <tr>
          <th colspan="2">
            <h2>RAG et embeddings</h2>
          </th>
        </tr>

        <tr>

          <th>Moteur d’embedding RAG</th>

          <td>

            <?php $rag_provider = (string)get_option('ouinpo_sf_rag_embedding_provider', 'openai'); ?>

            <select name="ouinpo_sf_rag_embedding_provider">

              <option value="openai" <?php selected($rag_provider, 'openai'); ?>>OpenAI fallback actuel</option>

              <option value="albert" <?php selected($rag_provider, 'albert'); ?>>Albert — bge-m3</option>

            </select>

            <p class="description">

              Ce réglage concerne uniquement l’index RAG. Après changement de moteur, il faut purger puis réindexer.

            </p>

          </td>

        </tr>

        

        <tr>

          <th>Modèle d’embedding OpenAI</th>

          <td>

            <input name="ouinpo_sf_embed_model"

              value="<?php echo esc_attr(get_option('ouinpo_sf_embed_model','text-embedding-3-large')); ?>" />

            <p class="description">

              Utilisé seulement si le moteur d’embedding RAG est réglé sur OpenAI.

            </p>

          </td>

        </tr>

        

        <tr>

          <th>Modèle d’embedding Albert</th>

          <td>

            <input name="ouinpo_sf_albert_embedding_model" class="regular-text"

              value="<?php echo esc_attr(get_option('ouinpo_sf_albert_embedding_model','BAAI/bge-m3')); ?>" />

            <p class="description">

              Modèle Albert utilisé pour vectoriser les chunks du RAG. Valeur recommandée :

              <code>BAAI/bge-m3</code>.

            </p>

          </td>

        </tr>

        

        <tr>

          <th>Modèle reranker Albert</th>

          <td>

            <input name="ouinpo_sf_albert_reranker_model" class="regular-text"

              value="<?php echo esc_attr(get_option('ouinpo_sf_albert_reranker_model','BAAI/bge-reranker-v2-m3')); ?>" />

            <p class="description">

              Préparé pour l’étape suivante : reclassement des chunks candidats avant injection dans SegFault.

            </p>

          </td>

        </tr>

<tr>

  <th>Nombre de candidats reranker</th>

  <td>

    <input type="number" min="10" max="80" name="ouinpo_sf_rag_rerank_candidates"

      value="<?php echo esc_attr((int)get_option('ouinpo_sf_rag_rerank_candidates', 40)); ?>"

      class="ouinpo-sf-input-medium" />

    <p class="description">

      Nombre de chunks candidats envoyés au reranker Albert avant de garder les meilleurs.

      Valeur recommandée : <code>40</code>. Plus haut = plus pertinent mais plus lent.

    </p>

  </td>

</tr>        

          <tr>

            <th colspan="2">

              <h2>Accès, mémoire et confidentialité</h2>

            </th>

          </tr>

        

          <tr>

            <th>Mode membres uniquement</th>

            <td>

              <label>

                <input type="checkbox" name="ouinpo_sf_members_only" value="1" <?php checked(1, (int)get_option('ouinpo_sf_members_only', 0)); ?> />

                Réserver SegFault aux utilisateurs connectés.

              </label>

              <p class="description">

                À laisser décoché si tu veux que les visiteurs non connectés utilisent l’IA publique.

                Les visiteurs publics n’ont pas de mémoire, pas de progression et pas de tentative enregistrée.

              </p>

            </td>

          </tr>

        

          <tr>

            <th>Durée mémoire connectés</th>

            <td>

              <input type="number" min="0" name="ouinpo_sf_memory_days"

                value="<?php echo esc_attr(get_option('ouinpo_sf_memory_days',30)); ?>" />

              <p class="description">

                Durée de conservation de la mémoire conversationnelle pour les utilisateurs connectés ayant consenti.

                Mettre <code>0</code> pour désactiver la mémoire.

              </p>

            </td>

          </tr>

        

          <tr>

            <th colspan="2">

              <h2>Indexation RAG</h2>

            </th>

          </tr>

        

          <tr>

            <th>Chemin XML WXR</th>

            <td>

              <input

                name="ouinpo_sf_wxr_path"

                class="regular-text"

                placeholder="/chemin/absolu/vers/export.xml"

                value="<?php echo esc_attr(get_option('ouinpo_sf_wxr_path','')); ?>"

              />

              <p class="description">

                Chemin <strong>absolu</strong> vers le fichier XML WordPress utilisé pour indexer les contenus du site.

                SegFault s’en sert pour retrouver le contexte des cours, exercices et pages.

              </p>

            </td>

          </tr>



        <tr>

          <th>Budget embeddings par lancement</th>

          <td>

            <input

              type="number"

              min="10"

              max="500"

              name="ouinpo_sf_max_embeddings_run"

              value="<?php echo esc_attr((int)get_option('ouinpo_sf_max_embeddings_run', 120)); ?>"

              class="ouinpo-sf-input-medium"

            />

            <p class="description">

              Nombre maximal de chunks à vectoriser lors d’un bouton d’indexation.

              Valeur conseillée : <code>120</code>.

              Baisser à <code>60</code> si l’hébergement renvoie des erreurs 503.

            </p>

          </td>

        </tr>

        

        </table>

      <?php submit_button(); ?>

    </form>



<h2>Générer un WXR propre pour SegFault</h2>

<form method="post" class="ouinpo-sf-boxed-form">

  <?php wp_nonce_field('ouinpo_sf_generate_clean_wxr'); ?>

  <input

    type="submit"

    name="ouinpo_sf_generate_clean_wxr"

    class="button button-secondary"

    value="🧾 Générer le WXR SegFault propre"

  />

  <p class="description" class="ouinpo-sf-spaced">

    Génère un export XML minimal dans

    <code><?php

      echo esc_html(

        defined('OUINPO_SF_SRC')

          ? trailingslashit(OUINPO_SF_SRC) . 'site-segfault-clean.xml'

          : '(constante OUINPO_SF_SRC absente)'

      );

    ?></code>,

    met à jour automatiquement <code>ouinpo_sf_wxr_path</code>

    et réinitialise le curseur batch WXR.

  </p>

  <p class="description" class="ouinpo-sf-spaced-small">

    Inclus : <strong>posts/pages publiés</strong>.<br>

    Exclus : <strong>auteurs, emails, attachments, CPT, metas</strong>.

  </p>

</form>



    <h2>Actions rapides</h2>

    <div class="ouinpo-sf-flex">



<form method="post">

  <?php wp_nonce_field('ouinpo_sf_purge_exercise_chunks'); ?>

  <input

    type="submit"

    name="ouinpo_sf_purge_exercise_chunks"

    class="button"

    value="🧹 Purger uniquement les chunks d’exercices"

  >

</form>

        

      <form method="post">

        <?php wp_nonce_field('ouinpo_sf_run_cron_now'); ?>

        <input type="submit" name="ouinpo_sf_run_cron_now" class="button button-primary" value="⚡ Lancer le cron maintenant (diff)">

      </form>



      <form method="post">

        <?php wp_nonce_field('ouinpo_sf_purge_docs'); ?>

        <input type="submit" name="ouinpo_sf_purge_docs" class="button" value="🧹 Purger l’index (documents)">

      </form>



      <form method="post">

        <?php wp_nonce_field('ouinpo_sf_reindex'); ?>

        <input type="submit" name="ouinpo_sf_reindex" class="button" value="Réindexer maintenant (complet)">

      </form>

    </div>



    <p class="description" class="ouinpo-sf-table-top">

      Préfère <strong>“cron maintenant (diff)”</strong> ou le <strong>batch WXR</strong> ci-dessous pour éviter les 503.

    </p>



    <h2>Indexation WXR en batch (anti-503)</h2>

    <?php

      $xml = (string)get_option('ouinpo_sf_wxr_path','');

      $cursor = (int)get_option('ouinpo_sf_wxr_cursor', 0);

      $total  = (int)get_option('ouinpo_sf_wxr_total', 0);

      $done   = (int)get_option('ouinpo_sf_wxr_done', 0);



      if ($xml && is_file($xml) && $total === 0 && method_exists('\OuInPo\SegFault\RAG', 'wxr_count_items')) {

        $total = RAG::wxr_count_items($xml);

      }



      $pct = ($total > 0) ? (int)floor(100 * min($cursor, $total) / $total) : 0;

    ?>



    <p>

      XML : <code><?php echo esc_html($xml ?: '(non défini)'); ?></code><br>

      Progression : <strong><?php echo esc_html($cursor); ?></strong> / <strong><?php echo esc_html($total ?: '?'); ?></strong>

      <?php if ($total > 0): ?> (<?php echo esc_html($pct); ?>%)<?php endif; ?>

      <?php if ($done === 1): ?> — <strong>Terminé ✅</strong><?php endif; ?>

    </p>



    <div class="ouinpo-sf-flex">

      <form method="post" class="ouinpo-sf-admin-mini-form">

        <?php wp_nonce_field('ouinpo_sf_wxr_init'); ?>

        <input type="submit" name="ouinpo_sf_wxr_init" class="button button-secondary" value="🚀 Init batch (purge + reset curseur)">

        <div class="ouinpo-sf-spaced">

          <label class="ouinpo-sf-check-label">

            <input type="checkbox" name="purge_memory" value="1"> Purger aussi la mémoire chat

          </label>

          <label class="ouinpo-sf-check-label">

            <input type="checkbox" name="clear_sources" value="1"> Vider <code>sources/</code> (md/txt/pdf) — ne supprime pas le XML

          </label>

        </div>

      </form>



      <form method="post" class="ouinpo-sf-admin-mini-form">

        <?php wp_nonce_field('ouinpo_sf_wxr_step'); ?>

        <input type="hidden" name="ouinpo_sf_wxr_step" value="1" />

        <input type="number" name="batch_items" value="10" min="1" max="50" class="ouinpo-sf-col-score">

        <input type="submit" class="button button-primary" value="▶ Continuer (batch)">

        <p class="description" class="ouinpo-sf-spaced-small">

          10 conseillé. Monte à 20 si ça passe sans problème.

        </p>

      </form>



      <form method="post" class="ouinpo-sf-admin-mini-form">

        <?php wp_nonce_field('ouinpo_sf_wxr_reset'); ?>

        <input type="submit" name="ouinpo_sf_wxr_reset" class="button" value="♻ Reset batch (cursor)">

      </form>

    </div>

  </div>

  <?php

}



/* ========================================================================

   Helpers parcours

   ======================================================================== */



function ouinpo_sf_progress_tables(): array {

  global $wpdb;

  return [

    $wpdb->prefix . 'ouin_sf_paths',

    $wpdb->prefix . 'ouin_sf_path_items',

    $wpdb->prefix . 'ouin_exo_exercises',

    $wpdb->prefix . 'ouin_exo_user_status',

    $wpdb->prefix . 'ouin_sf_path_targets',

  ];

}



function ouinpo_sf_get_students_for_select(): array {

  $users = get_users([

    'fields' => ['ID','display_name','user_login'],

    'orderby' => 'display_name',

    'order' => 'ASC',

  ]);



  $out = [];

  foreach ($users as $u) {

    if (user_can($u->ID, 'manage_options')) continue;

    $out[] = $u;

  }

  return $out;

}



function ouinpo_sf_get_groups_for_select(): array {

  global $wpdb;

  $t_groups = $wpdb->prefix . 'ouin_exo_groups';



  return $wpdb->get_results("

    SELECT id, label

    FROM {$t_groups}

    ORDER BY label ASC, id ASC

  ", ARRAY_A) ?: [];

}



function ouinpo_sf_get_user_ids_for_group(int $group_id): array {

  global $wpdb;



  if ($group_id <= 0) {

    return [];

  }



  $t_members = $wpdb->prefix . 'ouin_exo_group_members';



  $ids = $wpdb->get_col($wpdb->prepare("

    SELECT user_id

    FROM {$t_members}

    WHERE group_id = %d

  ", $group_id)) ?: [];



  $ids = array_values(array_unique(array_map('intval', $ids)));

  return array_values(array_filter($ids, static fn($v) => $v > 0));

}



function ouinpo_sf_get_years_for_select(): array {

  global $wpdb;



  $t_years = $wpdb->prefix . 'ouin_exo_academic_years';

  $exists  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_years));



  if ($exists !== $t_years) {

    return [];

  }



  return $wpdb->get_results("

    SELECT id, slug, starts_on, ends_on, is_active

    FROM {$t_years}

    ORDER BY starts_on DESC, id DESC

  ", ARRAY_A) ?: [];

}



function ouinpo_sf_get_active_year_id(): int {

  $years = ouinpo_sf_get_years_for_select();

  foreach ($years as $y) {

    if (!empty($y['is_active'])) {

      return (int) $y['id'];

    }

  }

  return 0;

}



function ouinpo_sf_insert_path_targets(int $path_id, array $user_ids, array $group_ids, int $assigned_by, string $now): void {

  global $wpdb;

  $t_targets = $wpdb->prefix . 'ouin_sf_path_targets';



  $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), fn($v) => $v > 0)));

  $group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids), fn($v) => $v > 0)));



  foreach ($user_ids as $uid) {

    $wpdb->replace($t_targets, [

      'path_id'      => $path_id,

      'target_type'  => 'user',

      'target_id'    => $uid,

      'assigned_at'  => $now,

      'assigned_by'  => $assigned_by,

      'created_at'   => $now,

    ], ['%d','%s','%d','%s','%d','%s']);

  }



  foreach ($group_ids as $gid) {

    $wpdb->replace($t_targets, [

      'path_id'      => $path_id,

      'target_type'  => 'group',

      'target_id'    => $gid,

      'assigned_at'  => $now,

      'assigned_by'  => $assigned_by,

      'created_at'   => $now,

    ], ['%d','%s','%d','%s','%d','%s']);

  }

}

function ouinpo_sf_get_path_targets(int $path_id): array {

  global $wpdb;



  $t_targets = $wpdb->prefix . 'ouin_sf_path_targets';

  $t_members = $wpdb->prefix . 'ouin_exo_group_members';

  $t_groups  = $wpdb->prefix . 'ouin_exo_groups';



  $targets = $wpdb->get_results($wpdb->prepare("

    SELECT t.target_type, t.target_id, g.label AS group_label

    FROM {$t_targets} t

    LEFT JOIN {$t_groups} g

      ON t.target_type = 'group' AND g.id = t.target_id

    WHERE t.path_id = %d

    ORDER BY t.target_type, t.target_id

  ", $path_id), ARRAY_A) ?: [];



  $user_ids = [];

  $group_ids = [];



  foreach ($targets as $t) {

    $type = (string)($t['target_type'] ?? '');

    $id   = (int)($t['target_id'] ?? 0);



    if ($type === 'user' && $id > 0) {

      $user_ids[] = $id;

      continue;

    }



    if ($type === 'group' && $id > 0) {

      $group_ids[] = $id;



      $ids = $wpdb->get_col($wpdb->prepare("

        SELECT user_id

        FROM {$t_members}

        WHERE group_id = %d

      ", $id)) ?: [];



      foreach ($ids as $uid) {

        $uid = (int)$uid;

        if ($uid > 0) {

          $user_ids[] = $uid;

        }

      }

    }

  }



  $user_ids  = array_values(array_unique($user_ids));

  $group_ids = array_values(array_unique($group_ids));



  return [

    'targets'   => $targets,

    'user_ids'  => $user_ids,

    'group_ids' => $group_ids,

  ];

}

function ouinpo_sf_render_path_targets_label(int $path_id): string {

  $data = ouinpo_sf_get_path_targets($path_id);

  $targets = $data['targets'] ?? [];



  $labels = [];

  foreach ($targets as $t) {

    $type = (string)($t['target_type'] ?? '');

    $id   = (int)($t['target_id'] ?? 0);



    if ($type === 'user' && $id > 0) {

      $u = get_userdata($id);

      $labels[] = $u ? $u->display_name : ('User #'.$id);

    } elseif ($type === 'group' && $id > 0) {

      $labels[] = !empty($t['group_label']) ? ('Classe : '.$t['group_label']) : ('Classe #'.$id);

    }

  }



  return implode(', ', $labels);

}



function ouinpo_sf_fetch_paths_with_items(): array {

  global $wpdb;

  [$t_paths, $t_items, $t_exo, $t_status, $t_targets] = ouinpo_sf_progress_tables();



  $paths = $wpdb->get_results("SELECT * FROM {$t_paths} ORDER BY updated_at DESC, id DESC", ARRAY_A);

  if (!$paths) return [];



  $path_ids = array_map(fn($p) => (int)$p['id'], $paths);

  $in = implode(',', array_map('intval', $path_ids));



  $items = $wpdb->get_results("

    SELECT i.*, e.title AS exo_title

    FROM {$t_items} i

    LEFT JOIN {$t_exo} e ON e.id = i.exercise_id

    WHERE i.path_id IN ({$in})

    ORDER BY i.path_id ASC, i.position ASC, i.id ASC

  ", ARRAY_A);



  $by_path = [];

  foreach ($items as $it) {

    $pid = (int)$it['path_id'];

    if (!isset($by_path[$pid])) $by_path[$pid] = [];

    $by_path[$pid][] = $it;

  }



  foreach ($paths as &$p) {

    $path_id = (int)$p['id'];

    $p['items'] = $by_path[$path_id] ?? [];



    $targets_data = ouinpo_sf_get_path_targets($path_id);

    $target_user_ids  = $targets_data['user_ids'] ?? [];

    $target_group_ids = $targets_data['group_ids'] ?? [];



    // Compat legacy si ancien parcours sans targets

    if (empty($target_user_ids) && !empty($p['student_id'])) {

      $target_user_ids = [(int)$p['student_id']];

    }



    $p['target_user_ids']  = $target_user_ids;

    $p['target_group_ids'] = $target_group_ids;



    $p['targets_label'] = ouinpo_sf_render_path_targets_label($path_id);

    if ($p['targets_label'] === '' && !empty($p['student_id'])) {

      $u = get_userdata((int)$p['student_id']);

      $p['targets_label'] = $u ? $u->display_name : ('User #'.(int)$p['student_id']);

    }



    // Précharge les noms des destinataires une seule fois

    $target_users = [];

    foreach ($target_user_ids as $uid) {

      $u = get_userdata((int)$uid);

      $target_users[(int)$uid] = [

        'id' => (int)$uid,

        'display_name' => $u ? (string)$u->display_name : ('User #'.(int)$uid),

        'user_login'   => $u ? (string)$u->user_login   : '',

      ];

    }



    // Précharge les statuts de tous les destinataires du parcours

    $status_maps = [];

    foreach ($target_user_ids as $uid) {

      $rows = $wpdb->get_results(

        $wpdb->prepare("

          SELECT exercise_id, status, updated_at, declared_at

          FROM {$t_status}

          WHERE user_id = %d

        ", $uid),

        ARRAY_A

      );



      $map = [];

      foreach ($rows as $r) {

        $map[(int)$r['exercise_id']] = [

          'status'      => (string)($r['status'] ?? 'none'),

          'updated_at'  => (string)($r['updated_at'] ?? ''),

          'declared_at' => (string)($r['declared_at'] ?? ''),

        ];

      }

      $status_maps[(int)$uid] = $map;

    }



    $total = count($p['items']);

    $solved_acc = 0;

    $attempted_acc = 0;

    $users_count = count($target_user_ids);



    foreach ($p['items'] as &$it) {

      $eid = (int)$it['exercise_id'];



      $solved_for_item = 0;

      $attempted_for_item = 0;

      $per_student = [];



      foreach ($target_user_ids as $uid) {

        $uid = (int)$uid;

        $row = $status_maps[$uid][$eid] ?? [

          'status' => 'none',

          'updated_at' => '',

          'declared_at' => '',

        ];



        $st = (string)$row['status'];



        if ($st === 'solved') {

          $solved_for_item++;

        } elseif ($st === 'attempted') {

          $attempted_for_item++;

        }



        $per_student[] = [

          'user_id'      => $uid,

          'display_name' => $target_users[$uid]['display_name'] ?? ('User #'.$uid),

          'user_login'   => $target_users[$uid]['user_login'] ?? '',

          'status'       => $st,

          'updated_at'   => (string)$row['updated_at'],

          'declared_at'  => (string)$row['declared_at'],

        ];

      }



      // tri utile : réussis, puis tentés, puis non commencés ; puis nom

      usort($per_student, static function(array $a, array $b): int {

        $rank = ['solved' => 0, 'attempted' => 1, 'none' => 2];

        $ra = $rank[$a['status'] ?? 'none'] ?? 9;

        $rb = $rank[$b['status'] ?? 'none'] ?? 9;

        if ($ra !== $rb) return $ra <=> $rb;

        return strcasecmp((string)($a['display_name'] ?? ''), (string)($b['display_name'] ?? ''));

      });



      $it['solved_count'] = $solved_for_item;

      $it['attempted_count'] = $attempted_for_item;

      $it['target_count'] = $users_count;

      $it['per_student'] = $per_student;



      // résumé global de l’exercice

      if ($users_count <= 1) {

        if ($solved_for_item >= 1) {

          $it['student_status'] = 'solved';

        } elseif ($attempted_for_item >= 1) {

          $it['student_status'] = 'attempted';

        } else {

          $it['student_status'] = 'none';

        }

      } else {

        if ($solved_for_item === $users_count && $users_count > 0) {

          $it['student_status'] = 'solved';

        } elseif (($solved_for_item + $attempted_for_item) > 0) {

          $it['student_status'] = 'attempted';

        } else {

          $it['student_status'] = 'none';

        }

      }



      $solved_acc += $solved_for_item;

      $attempted_acc += $attempted_for_item;

    }

    unset($it);



    $progress_denominator = max(1, $total * max(1, $users_count));

    $p['progress_total'] = $total;

    $p['progress_target_users'] = $users_count;

    $p['progress_solved'] = $solved_acc;

    $p['progress_attempted'] = $attempted_acc;

    $p['progress_pct'] = (int)round(100 * $solved_acc / $progress_denominator);

  }

  unset($p);



  return $paths;

}



function ouinpo_sf_filter_paths(array $paths, array $filters = []): array {

  $year_id        = (int)($filters['year_id'] ?? 0);

  $group_id       = (int)($filters['group_id'] ?? 0);

  $user_id        = (int)($filters['user_id'] ?? 0);

  $group_user_ids = array_values(array_unique(array_map('intval', $filters['group_user_ids'] ?? [])));



  if ($year_id <= 0 && $group_id <= 0 && $user_id <= 0) {

    return $paths;

  }



  $filtered = [];



  foreach ($paths as $p) {

    $path_year_id     = (int)($p['year_id'] ?? 0);

    $target_group_ids = array_map('intval', $p['target_group_ids'] ?? []);

    $target_user_ids  = array_map('intval', $p['target_user_ids'] ?? []);



    if ($year_id > 0 && $path_year_id !== $year_id) {

      continue;

    }



    if ($group_id > 0) {

      $matches_group = in_array($group_id, $target_group_ids, true);



      if (!$matches_group && !empty($group_user_ids)) {

        $matches_group = count(array_intersect($target_user_ids, $group_user_ids)) > 0;

      }



      if (!$matches_group) {

        continue;

      }

    }



    if ($user_id > 0 && !in_array($user_id, $target_user_ids, true)) {

      continue;

    }



    $filtered[] = $p;

  }



  return $filtered;

}

function ouinpo_sf_find_exercise_ids_by_domain_and_comp(

  string $domain_value,

  ?int $competency_id,

  string $student_level,

  int $student_id,

  int $limit = 12,

  bool $exclude_solved = true

): array {

  global $wpdb;



  $domain_value = trim($domain_value);

  if ($domain_value === '') return [];



  $limit = max(1, min(50, $limit));

    $student_level = in_array($student_level, ['seconde','premiere','terminale'], true) ? $student_level : 'premiere';

    $allowed_levels = function_exists(__NAMESPACE__.'\\ouinpo_sf_allowed_competency_levels')

      ? ouinpo_sf_allowed_competency_levels($student_level)

      : ['Première'];

  $t_exo    = $wpdb->prefix.'ouin_exo_exercises';

  $t_link   = $wpdb->prefix.'ouin_exo_exercise_competency';

  $t_comp   = $wpdb->prefix.'ouin_exo_competencies';

  $t_status = $wpdb->prefix.'ouin_exo_user_status';

  $t_diff   = $wpdb->prefix.'ouin_exo_difficulties';

  $t_exam   = $wpdb->prefix.'ouin_exo_exam_meta';



    $has_domain_slug = ouinpo_sf_table_column_exists($t_comp, 'domain_slug');

    $domain_col = $has_domain_slug ? 'domain_slug' : 'domain';



  $join_status = '';

  $where_status = '';

  $params = [];



  if ($student_id > 0) {

    $join_status = "LEFT JOIN {$t_status} us ON us.user_id = %d AND us.exercise_id = e.id";

    $params[] = $student_id;



    if ($exclude_solved) {

      $where_status = "AND (us.status IS NULL OR us.status <> 'solved')";

    }

  }



  $where = [];

  $where[] = "e.is_active = 1";

  $where[] = "c.active = 1";

  $where[] = "NOT EXISTS (

  SELECT 1

  FROM {$t_exam} em_practical

  WHERE em_practical.exercise_id = e.id

    AND em_practical.exam_type = 'practical_subject'

)";

    $level_placeholders = implode(',', array_fill(0, count($allowed_levels), '%s'));

    $where[] = "c.level IN ({$level_placeholders})";

    $params = array_merge($params, $allowed_levels);



  $where[] = "c.{$domain_col} = %s";

  $params[] = $domain_value;



  if (!empty($competency_id) && (int)$competency_id > 0) {

    $where[] = "ec.competency_id = %d";

    $params[] = (int)$competency_id;

  }



  $sql = "

    SELECT DISTINCT e.id

    FROM {$t_exo} e

    INNER JOIN {$t_link} ec ON ec.exercise_id = e.id

    INNER JOIN {$t_comp} c  ON c.id = ec.competency_id

    LEFT JOIN {$t_diff} d   ON d.id = e.difficulty_id

    {$join_status}

    WHERE ".implode(' AND ', $where)."

    {$where_status}

    ORDER BY

      " . ($student_id > 0 ? "

      CASE

        WHEN us.status IS NULL OR us.status = '' OR us.status = 'none' THEN 0

        WHEN us.status = 'attempted' THEN 1

        WHEN us.status = 'solved' THEN 2

        ELSE 3

      END ASC,

      " : "") . "

      CASE

        WHEN d.slug = 'debutant' THEN 0

        WHEN d.slug = 'confirme' THEN 1

        WHEN d.slug = 'expert'   THEN 2

        ELSE 99

      END ASC,

      e.id DESC

    LIMIT {$limit}

  ";



  $ids = $wpdb->get_col($wpdb->prepare($sql, ...$params)) ?: [];



  $out = [];

  foreach ($ids as $id) {

    $eid = (int)$id;

    if ($eid > 0) $out[] = $eid;

  }

  return array_values(array_unique($out));

}



/* ========================================================================

   ✅ PAGE PROF : Suivi des parcours / progression

   ======================================================================== */



function admin_progress_page() {

  if (!\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::VIEW_STUDENT_DATA)) {

    wp_die('Accès refusé.');

  }



  ouinpo_sf_ensure_progress_tables();



  global $wpdb;

  [$t_paths, $t_items, $t_exo, $t_status, $t_targets] = ouinpo_sf_progress_tables();



  if (!empty($_POST['sf_action']) && check_admin_referer('ouinpo_sf_progress')) {

    $action = sanitize_text_field(wp_unslash($_POST['sf_action'] ?? ''));



    if ($action === 'delete_path') {

      $path_id = (int)($_POST['path_id'] ?? 0);

      if ($path_id > 0) {

        $wpdb->delete($t_items, ['path_id' => $path_id], ['%d']);

        $wpdb->delete($t_targets, ['path_id' => $path_id], ['%d']);

        $wpdb->delete($t_paths, ['id' => $path_id], ['%d']);

        echo '<div class="notice notice-success"><p>Parcours supprimé 🗑️</p></div>';

      }

    }

  }



  $students = ouinpo_sf_get_students_for_select();

  $groups   = ouinpo_sf_get_groups_for_select();

  $years    = ouinpo_sf_get_years_for_select();



  $active_year_id = ouinpo_sf_get_active_year_id();



  $selected_year_id = isset($_GET['sf_year_id'])

    ? max(0, (int) $_GET['sf_year_id'])

    : $active_year_id;



  $selected_group_id = isset($_GET['sf_group_id'])

    ? max(0, (int) $_GET['sf_group_id'])

    : 0;



  $selected_user_id = isset($_GET['sf_user_id'])

    ? max(0, (int) $_GET['sf_user_id'])

    : 0;



$selected_group_user_ids = $selected_group_id > 0

  ? ouinpo_sf_get_user_ids_for_group($selected_group_id)

  : [];



if ($selected_group_id > 0) {

  $students = array_values(array_filter($students, function ($u) use ($selected_group_user_ids, $selected_user_id) {

    $uid = (int)($u->ID ?? 0);



    if ($uid <= 0) {

      return false;

    }



    if (in_array($uid, $selected_group_user_ids, true)) {

      return true;

    }



    return ($selected_user_id > 0 && $uid === $selected_user_id);

  }));

}





  $paths = ouinpo_sf_fetch_paths_with_items();

$paths = ouinpo_sf_filter_paths($paths, [

  'year_id'        => $selected_year_id,

  'group_id'       => $selected_group_id,

  'user_id'        => $selected_user_id,

  'group_user_ids' => $selected_group_user_ids,

]);



  $years_by_id = [];

  foreach ($years as $y) {

    $years_by_id[(int)$y['id']] = (string)$y['slug'];

  }

  ?>

  <div class="wrap">

    <h1>Suivi élèves — Parcours & progression</h1>



    <p class="description">

      Cet écran ne sert plus à créer des parcours. Il sert uniquement au suivi et à la consultation.

    </p>



    <form method="get" class="ouinpo-sf-filter-form">

      <input type="hidden" name="page" value="ouinpo-segfault-progress">



      <div class="ouinpo-sf-filter-row">

        <div>

          <label for="sf_year_id"><strong>Année scolaire</strong></label><br>

          <select name="sf_year_id" id="sf_year_id" class="ouinpo-sf-select-year">

            <option value="0">— Toutes —</option>

            <?php foreach ($years as $y): ?>

              <option value="<?php echo (int)$y['id']; ?>" <?php selected($selected_year_id, (int)$y['id']); ?>>

                <?php

                echo esc_html(

                  (string)$y['slug'] . (!empty($y['is_active']) ? ' (active)' : '')

                );

                ?>

              </option>

            <?php endforeach; ?>

          </select>

        </div>



        <div>

          <label for="sf_group_id"><strong>Classe</strong></label><br>

          <select name="sf_group_id" id="sf_group_id" class="ouinpo-sf-select-group">

            <option value="0">— Toutes —</option>

            <?php foreach ($groups as $g): ?>

              <option value="<?php echo (int)$g['id']; ?>" <?php selected($selected_group_id, (int)$g['id']); ?>>

                <?php echo esc_html($g['label']); ?>

              </option>

            <?php endforeach; ?>

          </select>

        </div>



        <div>

          <label for="sf_user_id"><strong>Élève</strong></label><br>

          <select name="sf_user_id" id="sf_user_id" class="ouinpo-sf-select-user">

            <option value="0">— Tous —</option>

            <?php foreach ($students as $u): ?>

              <option value="<?php echo (int)$u->ID; ?>" <?php selected($selected_user_id, (int)$u->ID); ?>>

                <?php echo esc_html($u->display_name . ' (@' . $u->user_login . ')'); ?>

              </option>

            <?php endforeach; ?>

          </select>

        </div>



        <div>

          <?php submit_button('Filtrer', 'secondary', '', false); ?>

          <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-segfault-progress')); ?>">Réinitialiser</a>

        </div>

      </div>

    </form>



    <h2>Parcours existants</h2>



    <?php if (empty($paths)): ?>

      <p>Aucun parcours correspondant aux filtres.</p>

    <?php else: ?>



      <?php foreach ($paths as $p):

        $teacher_id = (int)($p['teacher_id'] ?? 0);

        $teacher = $teacher_id > 0 ? get_userdata($teacher_id) : false;



        if ($teacher) {

          $teacher_name = $teacher->display_name;

        } elseif (!empty($p['student_id'])) {

          $creator = get_userdata((int)$p['student_id']);

          $teacher_name = $creator ? $creator->display_name . ' (élève)' : 'Élève';

        } else {

          $teacher_name = 'Élève';

        }



        $pct = (int)$p['progress_pct'];

        $total = (int)$p['progress_total'];

        $solved = (int)$p['progress_solved'];

        $attempted = (int)$p['progress_attempted'];

        $target_users = (int)($p['progress_target_users'] ?? 0);

        $year_label = !empty($years_by_id[(int)($p['year_id'] ?? 0)]) ? $years_by_id[(int)$p['year_id']] : '—';

      ?>

        <div class="sf-card">

          <h3 class="ouinpo-sf-progress-title"><?php echo esc_html($p['title']); ?></h3>



          <div class="sf-meta">

            Année : <strong><?php echo esc_html($year_label); ?></strong> —

            Destinataires : <strong><?php echo esc_html($p['targets_label'] ?: '—'); ?></strong> —

            Créé par : <?php echo esc_html($teacher_name); ?> —

            Mis à jour : <?php echo esc_html(mysql2date('d/m/Y à H:i', $p['updated_at'])); ?>

          </div>



          <p class="ouinpo-sf-progress-line">

          <span class="sf-progressbar" style="--progress: <?php echo $pct; ?>%;"><span></span></span>

            <strong><?php echo $pct; ?>%</strong>

            (<?php echo $solved; ?> réussites sur <?php echo max(1, $total * max(1, $target_users)); ?> combinaisons exercice×élève,

            <?php echo $attempted; ?> tentatives)

          </p>



          <ul class="sf-items">

            <?php foreach ($p['items'] as $it):

              $eid = (int)$it['exercise_id'];

              $title = trim((string)($it['exo_title'] ?? ''));

              if ($title === '') {

                $title = 'Exercice '.$eid;

              }

              $url = home_url('/exercice/?exo='.$eid);



              $st = (string)($it['student_status'] ?? 'none');

              if ($st === 'solved') {

                $badge = '✅ terminé';

                $cls='ok';

              } elseif ($st === 'attempted') {

                $badge = '🟡 en cours';

                $cls='warn';

              } else {

                $badge = '⚪ non commencé';

                $cls='none';

              }



              $doneCount = (int)($it['solved_count'] ?? 0);

              $tCount = (int)($it['target_count'] ?? 0);

              $attemptCount = (int)($it['attempted_count'] ?? 0);

            ?>

              <li>

                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">

                  <?php echo esc_html($title); ?>

                </a>



                <span class="sf-pill <?php echo esc_attr($cls); ?>">

                  <?php echo esc_html($badge); ?>

                  <?php if ($tCount > 1): ?>

                    — <?php echo esc_html($doneCount . '/' . $tCount . ' réussis'); ?>

                    <?php if ($attemptCount > 0): ?>

                      <?php echo esc_html(', ' . $attemptCount . ' tentés'); ?>

                    <?php endif; ?>

                  <?php endif; ?>

                </span>



                <?php if (!empty($it['per_student'])): ?>

                  <details class="sf-students-detail">

                    <summary>Voir le détail par élève</summary>

                    <ul class="sf-students-list">

                      <?php foreach ($it['per_student'] as $stu):

                        $stu_status = (string)($stu['status'] ?? 'none');



                        if ($stu_status === 'solved') {

                          $stu_label = 'réussi';

                          $stu_cls = 'ok';

                        } elseif ($stu_status === 'attempted') {

                          $stu_label = 'tenté';

                          $stu_cls = 'warn';

                        } else {

                          $stu_label = 'non commencé';

                          $stu_cls = 'none';

                        }



                        $date_txt = '';

                        if (!empty($stu['updated_at'])) {

                          $date_txt = mysql2date('d/m/Y H:i', $stu['updated_at']);

                        }

                      ?>

                        <li>

                          <strong><?php echo esc_html($stu['display_name']); ?></strong>

                          <?php if (!empty($stu['user_login'])): ?>

                            <span class="ouinpo-sf-login-muted">(@<?php echo esc_html($stu['user_login']); ?>)</span>

                          <?php endif; ?>



                          <span class="sf-mini-pill <?php echo esc_attr($stu_cls); ?>">

                            <?php echo esc_html($stu_label); ?>

                          </span>



                          <?php if ($date_txt !== '' && $stu_status !== 'none'): ?>

                            <span class="sf-date">— <?php echo esc_html($date_txt); ?></span>

                          <?php endif; ?>

                        </li>

                      <?php endforeach; ?>

                    </ul>

                  </details>

                <?php endif; ?>

              </li>

            <?php endforeach; ?>

          </ul>



          <form method="post" data-confirm="Supprimer ce parcours ?">

            <?php wp_nonce_field('ouinpo_sf_progress'); ?>

            <input type="hidden" name="sf_action" value="delete_path">

            <input type="hidden" name="path_id" value="<?php echo (int)$p['id']; ?>">

            <?php submit_button('Supprimer', 'delete', 'submit', false); ?>

          </form>

        </div>

      <?php endforeach; ?>

    <?php endif; ?>

  </div>

  <?php

}
