<?php
namespace OuInPo\SegFault;

defined('ABSPATH') || exit;

use WP_Query;

class RAG {

  // ============================================================
  // Réindexe TOUT (sources privées + site). Retourne nb de chunks.
  // À utiliser uniquement en manuel : c'est le "gros" bouton.
  // - Priorité à l'indexation WXR (export XML WordPress) si configurée
  // - Fallback sur index_site() si pas de XML
  //
  // ✅ Corrections :
  // - garde la version "batch" si WXR dispo
  // ============================================================
  public static function reindex_all(): int {
    DB::init();
    $n = 0;

    // 1) Sources privées (full) — OK, mais limité par max_chunks_run
    $n += self::index_sources();

    // 2) Site : si WXR dispo -> on fait UN step batch (au lieu du full)
    $xml = (string) get_option('ouinpo_sf_wxr_path', '');
    if ($xml !== '' && is_file($xml) && method_exists(__CLASS__, 'index_wxr_xml_batch')) {

      $batch_items = (int) get_option('ouinpo_sf_manual_wxr_items', 10);
      if ($batch_items <= 0) $batch_items = 10;
      if ($batch_items > 50) $batch_items = 50;

      self::index_wxr_xml_batch($xml, $batch_items);

      // $n ne peut pas refléter précisément les chunks ajoutés par batch (items != chunks)
    } else {
      // fallback live WP (peut être lourd mais diff + max_chunks_run limitent)
      $n += self::index_site();
    }

    return $n;
  }

  /**
   * Indexation complète des sources privées (md/txt/pdf).
   */
  public static function index_sources(): int {
    $count = 0;

    // Taille max des fichiers pour limiter les gros monstres
    $max_text_size = 5 * 1024 * 1024;   // 5 Mo pour md/txt
    $max_pdf_mb = (int) get_option('ouinpo_sf_max_pdf_source_mb', 80);
    
    if ($max_pdf_mb < 5) {
      $max_pdf_mb = 5;
    }
    
    if ($max_pdf_mb > 100) {
      $max_pdf_mb = 100;
    }
    
    $max_pdf_size = $max_pdf_mb * 1024 * 1024;

    // 1) Fichiers texte (.md / .txt)
    foreach (glob(OUINPO_SF_SRC.'*.{md,txt}', GLOB_BRACE) as $file) {
      if (is_file($file) && filesize($file) > $max_text_size) {
        error_log('[SegFault] Fichier texte trop lourd ignoré (index_sources): '.$file);
        continue;
      }

      $title = basename($file);
      $url   = ''; // pas de lien côté UI
      $text  = @file_get_contents($file) ?: '';

      self::delete_private_by_title($title);
      $count += self::index_text($text, 'private', $url, $title, 'text');
    }

    // 2) Fichiers PDF (lecture seule pour l'IA, sans lien public)
    foreach (glob(OUINPO_SF_SRC.'*.pdf') as $file) {
      if (is_file($file) && filesize($file) > $max_pdf_size) {
        error_log('[SegFault] PDF trop lourd ignoré (index_sources): '.$file);
        continue;
      }

      $title = preg_replace('/\.pdf$/i', '', basename($file));
      $url   = '';
      $text  = self::pdf_to_text($file);
      if (trim($text) === '') $text = $title;

      self::delete_private_by_title($title);
      $count += self::index_text($text, 'private', $url, $title, 'pdf');
    }

    return $count;
  }

  // ============================================================
  // Indexation du site via export XML WordPress (WXR)
  // ============================================================

  private static function html_to_text(string $html): string {
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);

    $html = preg_replace('#</(p|div|h1|h2|h3|h4|h5|h6|li|tr|table|ul|ol|pre|code|blockquote)>#i', "\n", $html);
    $html = preg_replace('#<(br|hr)\b[^>]*>#i', "\n", $html);

    $text = wp_strip_all_tags($html);

    $text = preg_replace("/[ \t]+/", " ", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
  }

  private static function wxr_allowed_types(): array {
    return [
      'post',
      'page',
      // 'ouinpo_resource',
    ];
  }

  public static function index_wxr_xml(string $xml_file): int {
    if (!is_file($xml_file)) {
      error_log('[SegFault] XML introuvable (index_wxr_xml): '.$xml_file);
      return 0;
    }

    $allowed_types = self::wxr_allowed_types();
    $count = 0;

    libxml_use_internal_errors(true);
    $raw = @file_get_contents($xml_file);
    if ($raw === false || trim($raw) === '') {
      error_log('[SegFault] XML vide/illisible (index_wxr_xml): '.$xml_file);
      return 0;
    }

    $xml = simplexml_load_string($raw);
    if (!$xml || !isset($xml->channel->item)) {
      error_log('[SegFault] XML invalide ou sans items (index_wxr_xml): '.$xml_file);
      return 0;
    }

    $ns = $xml->getNamespaces(true);
    $wp_ns      = $ns['wp'] ?? null;
    $content_ns = $ns['content'] ?? null;

    foreach ($xml->channel->item as $item) {
      $iwp      = $wp_ns ? $item->children($wp_ns) : null;
      $icontent = $content_ns ? $item->children($content_ns) : null;

      $status = $iwp ? (string)$iwp->status : '';
      $ptype  = $iwp ? (string)$iwp->post_type : '';

      if ($status !== 'publish') continue;
      if (!in_array($ptype, $allowed_types, true)) continue;

      $title = trim((string)$item->title);
      $url   = trim((string)$item->link);

      $html = $icontent ? (string)$icontent->encoded : '';
      if (trim($html) === '') continue;

      $text = self::html_to_text($html);
      if ($text === '') continue;

      if ($url !== '') self::delete_by_origin_and_url('site', $url);

      $count += self::index_text($text, 'site', $url, ($title !== '' ? $title : '(sans titre)'), $ptype);
    }

    return $count;
  }

  // ============================================================
  // WXR batch (streaming) — indexe N items par passe, avec curseur
  // ============================================================

  public static function wxr_reset_batch_state(): void {
    delete_option('ouinpo_sf_wxr_cursor');
    delete_option('ouinpo_sf_wxr_done');
    delete_option('ouinpo_sf_wxr_total');
  }

  public static function wxr_count_items(string $xml_file): int {
    if (!is_file($xml_file)) return 0;

    $cached = (int) get_option('ouinpo_sf_wxr_total', 0);
    if ($cached > 0) return $cached;

    $reader = new \XMLReader();
    if (!$reader->open($xml_file, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      return 0;
    }

    $n = 0;
    while ($reader->read()) {
      if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'item') $n++;
    }
    $reader->close();

    update_option('ouinpo_sf_wxr_total', $n, false);
    return $n;
  }

  public static function index_wxr_xml_batch(string $xml_file, int $batch_items = 10): array {
    DB::init();

    $state = [
      'indexed_items' => 0,
      'cursor'        => (int) get_option('ouinpo_sf_wxr_cursor', 0),
      'total'         => 0,
      'done'          => false,
    ];

    if (!is_file($xml_file)) {
      $state['done'] = true;
      return $state;
    }

    $allowed_types  = self::wxr_allowed_types();
    $state['total'] = self::wxr_count_items($xml_file);

    if ((int)get_option('ouinpo_sf_wxr_done', 0) === 1) {
      $state['done'] = true;
      return $state;
    }

    $start_cursor = $state['cursor'];
    $target_end   = $start_cursor + max(1, $batch_items);

    $reader = new \XMLReader();
    if (!$reader->open($xml_file, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      $state['done'] = true;
      return $state;
    }

    $item_index = 0;
    $processed  = 0;

    while ($reader->read()) {
      if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'item') continue;

      if ($item_index < $start_cursor) { $item_index++; continue; }
      if ($item_index >= $target_end) break;

      $outer = $reader->readOuterXML();
      $item_index++;

      if (!is_string($outer) || trim($outer) === '') continue;

      $added = self::index_one_wxr_item_xml($outer, $allowed_types);

      if ($added > 0) $processed++;
    }

    $reader->close();

    $state['indexed_items'] = $processed;
    $state['cursor']        = $item_index;

    update_option('ouinpo_sf_wxr_cursor', $state['cursor'], false);

    if ($state['total'] > 0 && $state['cursor'] >= $state['total']) {
      update_option('ouinpo_sf_wxr_done', 1, false);
      $state['done'] = true;
    }

    return $state;
  }

  private static function index_one_wxr_item_xml(string $item_xml, array $allowed_types): int {
    libxml_use_internal_errors(true);

    $sx = simplexml_load_string($item_xml);
    if (!$sx) return 0;

    $ns = $sx->getNamespaces(true);
    $wp_ns      = $ns['wp'] ?? null;
    $content_ns = $ns['content'] ?? null;

    $iwp      = $wp_ns ? $sx->children($wp_ns) : null;
    $icontent = $content_ns ? $sx->children($content_ns) : null;

    $status = $iwp ? (string)$iwp->status : '';
    $ptype  = $iwp ? (string)$iwp->post_type : '';

    if ($status !== 'publish') return 0;
    if (!in_array($ptype, $allowed_types, true)) return 0;

    $title = trim((string)$sx->title);
    $url   = trim((string)$sx->link);

    $html = $icontent ? (string)$icontent->encoded : '';
    if (trim($html) === '') return 0;

    $text = self::html_to_text($html);
    if ($text === '') return 0;

    if ($url !== '') self::delete_by_origin_and_url('site', $url);

    return self::index_text($text, 'site', $url, ($title !== '' ? $title : '(sans titre)'), $ptype);
  }

  public static function index_site(): int {
    $count = 0;

    $q = new \WP_Query([
      'post_type'      => 'post',
      'posts_per_page' => -1,
      'post_status'    => 'publish',
      'fields'         => 'ids'
    ]);
    foreach ($q->posts as $pid) {
      $title = get_the_title($pid);
      $url   = get_permalink($pid);
      $html  = get_post_field('post_content', $pid);

      $html_rendered = apply_filters('the_content', $html);
      $text  = self::html_to_text($html_rendered);

      self::delete_by_origin_and_url('site', $url);
      $count += self::index_text($text, 'site', $url, $title, 'post');
    }

    $q2 = new \WP_Query([
      'post_type'      => 'page',
      'posts_per_page' => -1,
      'post_status'    => 'publish',
      'fields'         => 'ids'
    ]);
    foreach ($q2->posts as $pid) {
      $title = get_the_title($pid);
      $url   = get_permalink($pid);
      $html  = get_post_field('post_content', $pid);

      $html_rendered = apply_filters('the_content', $html);
      $text  = self::html_to_text($html_rendered);

      self::delete_by_origin_and_url('site', $url);
      $count += self::index_text($text, 'site', $url, $title, 'page');
    }

    return $count;
  }

  /* ========= Indexation différentielle & cron ========= */

public static function cron_reindex_nightly(): void {
  DB::init();

  $budget_wxr_items = (int) get_option('ouinpo_sf_cron_wxr_items', 10);
  $budget_site_live = (int) get_option('ouinpo_sf_cron_site_live', 20);
  $budget_sources   = (int) get_option('ouinpo_sf_cron_sources', 10);

  /*
   * Sources privées d’abord :
   * elles sont peu nombreuses et importantes pour le contexte pédagogique.
   */
  $done_src = self::index_sources_diff(max(1, $budget_sources));

  $done_site = 0;

  $xml = (string) get_option('ouinpo_sf_wxr_path', '');
  if ($xml !== '' && is_file($xml)) {
    $st = self::index_wxr_xml_batch($xml, max(1, $budget_wxr_items));
    $done_site = (int)($st['indexed_items'] ?? 0);
  } else {
    $done_site = self::index_site_diff(max(1, $budget_site_live));
  }

  $missing = self::index_missing_site_posts(80);
  $done_site += $missing;

  error_log(sprintf('[SegFault] Cron nightly : site=%d, sources=%d', $done_site, $done_src));
}

public static function cron_reindex_nightly_stats(): array {
  DB::init();

  $budget_wxr_items = (int) get_option('ouinpo_sf_cron_wxr_items', 10);
  $budget_site_live = (int) get_option('ouinpo_sf_cron_site_live', 20);
  $budget_sources   = (int) get_option('ouinpo_sf_cron_sources', 10);

  $xml = (string) get_option('ouinpo_sf_wxr_path', '');

  $wxr_before = (int) get_option('ouinpo_sf_wxr_cursor', 0);
  $wxr_total_before = (int) get_option('ouinpo_sf_wxr_total', 0);
  $wxr_done_before = (int) get_option('ouinpo_sf_wxr_done', 0);

  $xml_exists = ($xml !== '' && is_file($xml));

  /*
   * Sources privées d’abord.
   */
  $done_src = self::index_sources_diff(max(1, $budget_sources));

  $done_site = 0;
  $mode = '';

  if ($xml_exists) {
    $mode = 'WXR batch';

    $st = self::index_wxr_xml_batch($xml, max(1, $budget_wxr_items));

    $done_site = (int)($st['indexed_items'] ?? 0);
  } else {
    $mode = 'diff WordPress live';

    $done_site = self::index_site_diff(max(1, $budget_site_live));
  }

  $wxr_after = (int) get_option('ouinpo_sf_wxr_cursor', 0);
  $wxr_total_after = (int) get_option('ouinpo_sf_wxr_total', 0);
  $wxr_done_after = (int) get_option('ouinpo_sf_wxr_done', 0);

  error_log(sprintf(
    '[SegFault] Cron manual : mode=%s site=%d sources=%d wxr=%d/%d -> %d/%d done=%d xml_exists=%d xml=%s',
    $mode,
    $done_site,
    $done_src,
    $wxr_before,
    $wxr_total_before,
    $wxr_after,
    $wxr_total_after,
    $wxr_done_after,
    $xml_exists ? 1 : 0,
    $xml
  ));

  $missing = self::index_missing_site_posts(80);

  return [
    'site' => $done_site + $missing,
    'sources' => $done_src,

    'mode' => $mode,
    'xml' => $xml,
    'xml_exists' => $xml_exists,

    'wxr_before' => $wxr_before,
    'wxr_after' => $wxr_after,
    'wxr_total_before' => $wxr_total_before,
    'wxr_total_after' => $wxr_total_after,
    'wxr_done_before' => $wxr_done_before,
    'wxr_done_after' => $wxr_done_after,

    'missing_site' => $missing,
  ];
}
  private static function delete_by_origin_and_url(string $origin, string $url): void {
    if ($url === '') return;
    $db = DB::pdo();
    $st = $db->prepare("DELETE FROM documents WHERE origin = ? AND url = ?");
    $st->execute([$origin, $url]);
  }

  private static function delete_private_by_title(string $title): void {
    if ($title === '') return;
    $db = DB::pdo();
    $st = $db->prepare("DELETE FROM documents WHERE origin = 'private' AND title = ?");
    $st->execute([$title]);
  }

  private static function title_for_source_file(string $file): string {
    $base = basename($file);
    $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    if ($ext === 'pdf') return preg_replace('/\.pdf$/i', '', $base);
    return $base;
  }

  private static function index_one_source_file(string $file, string $rel): int {
    if (!is_file($file)) return 0;

    $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $title = self::title_for_source_file($file);
    $url   = '';
    $text  = '';

    $max_text_size = 5 * 1024 * 1024;
    $max_pdf_mb = (int) get_option('ouinpo_sf_max_pdf_source_mb', 80);
    
    if ($max_pdf_mb < 5) {
      $max_pdf_mb = 5;
    }
    
    if ($max_pdf_mb > 100) {
      $max_pdf_mb = 100;
    }
    
    $max_pdf_size = $max_pdf_mb * 1024 * 1024;
    $size          = @filesize($file) ?: 0;

    if (in_array($ext, ['md', 'txt'], true)) {
      if ($size > $max_text_size) {
        error_log('[SegFault] Fichier texte trop lourd ignoré (index_one_source_file): '.$file);
        return 0;
      }
      $text = @file_get_contents($file) ?: '';
    } elseif ($ext === 'pdf') {
      if ($size > $max_pdf_size) {
        error_log('[SegFault] PDF trop lourd ignoré (index_one_source_file): '.$file);
        return 0;
      }
      $text = self::pdf_to_text($file);
      if (trim($text) === '') $text = $title;
    } else {
      return 0;
    }

    $text = trim($text);
    if ($text === '') return 0;

    self::delete_private_by_title($title);

    $ptype = in_array($ext, ['md', 'txt'], true) ? 'text' : $ext;
    return self::index_text($text, 'private', $url, $title, $ptype);
  }

  private static function index_site_diff(int $batch_size = 50): int {
    $count = 0;

    $last_ts = (int) get_option('ouinpo_sf_last_site_index_gmt', 0);
    $date_query = [];

    if ($last_ts > 0) {
      $date_query[] = [
        'column'    => 'post_modified_gmt',
        'after'     => gmdate('Y-m-d H:i:s', $last_ts),
        'inclusive' => false,
      ];
    }

    $q = new \WP_Query([
      'post_type'      => ['post', 'page'],
      'posts_per_page' => $batch_size,
      'post_status'    => 'publish',
      'orderby'        => 'modified',
      'order'          => 'ASC',
      'date_query'     => $date_query,
      'no_found_rows'  => true,
    ]);

    if (!$q->have_posts()) return 0;

    $max_modified_ts = $last_ts;

    foreach ($q->posts as $p) {
      $post_id = $p->ID;
      $title   = get_the_title($post_id);
      $url     = get_permalink($post_id);

      $raw  = get_post_field('post_content', $post_id);
      $html = apply_filters('the_content', $raw);
      $text = self::html_to_text($html);

      $hash      = md5($text);
      $prev_hash = get_post_meta($post_id, '_ouinpo_sf_hash', true);

      if ($prev_hash !== $hash) {
        self::delete_by_origin_and_url('site', $url);
        self::index_text($text, 'site', $url, $title, $p->post_type);
        update_post_meta($post_id, '_ouinpo_sf_hash', $hash);
      }

      $mod_ts = strtotime($p->post_modified_gmt);
      if ($mod_ts && $mod_ts > $max_modified_ts) $max_modified_ts = $mod_ts;

      $count++;
    }

    wp_reset_postdata();

    if ($max_modified_ts > $last_ts) {
      update_option('ouinpo_sf_last_site_index_gmt', $max_modified_ts);
    }

    return $count;
  }

public static function index_site_posts_by_slugs(array $slugs, int $limit = 20): int {
  DB::init();

  global $wpdb;

  $slugs = array_values(array_unique(array_filter(array_map(static function($s) {
    return sanitize_title((string)$s);
  }, $slugs))));

  if (!$slugs) return 0;

  $count = 0;
  $db = DB::pdo();

  $old_budget = get_option('ouinpo_sf_max_embeddings_run', null);

  // Important : on augmente temporairement le budget,
  // car index_text() utilise un compteur static par requête PHP.
  update_option('ouinpo_sf_max_embeddings_run', 5000, false);

  try {
    foreach ($slugs as $slug) {
      if ($count >= $limit) break;

      $post_id = (int)$wpdb->get_var($wpdb->prepare("
        SELECT ID
        FROM {$wpdb->posts}
        WHERE post_name = %s
          AND post_status = 'publish'
          AND post_type IN ('post', 'page')
        ORDER BY ID DESC
        LIMIT 1
      ", $slug));

      if ($post_id <= 0) {
        error_log('[SegFault] index_site_posts_by_slugs : post introuvable pour slug '.$slug);
        continue;
      }

      $post = get_post($post_id);
      if (!$post) {
        error_log('[SegFault] index_site_posts_by_slugs : get_post impossible pour slug '.$slug);
        continue;
      }

      $url = get_permalink($post_id);
      if (!$url) {
        error_log('[SegFault] index_site_posts_by_slugs : URL introuvable pour slug '.$slug);
        continue;
      }

      $title = get_the_title($post_id);
      $raw   = get_post_field('post_content', $post_id);
      $html  = apply_filters('the_content', $raw);
      $text  = self::html_to_text($html);

      if (trim($text) === '') {
        error_log('[SegFault] index_site_posts_by_slugs : texte vide pour '.$slug);
        continue;
      }

      // On force vraiment la réindexation de cette page.
      self::delete_by_origin_and_url('site', $url);

      $added = self::index_text(
        $text,
        'site',
        $url,
        $title,
        get_post_type($post_id) ?: 'post'
      );

      error_log('[SegFault] index_site_posts_by_slugs : '.$slug.' -> '.$added.' chunks');

      $count += (int)$added;
    }
  } finally {
    if ($old_budget === null) {
      delete_option('ouinpo_sf_max_embeddings_run');
    } else {
      update_option('ouinpo_sf_max_embeddings_run', $old_budget, false);
    }
  }

  return $count;
}
public static function index_missing_site_posts(int $limit = 50): int {
  $count = 0;
  $limit = max(1, min(200, $limit));

  $q = new \WP_Query([
    'post_type'      => ['post', 'page'],
    'posts_per_page' => $limit,
    'post_status'    => 'publish',
    'orderby'        => 'modified',
    'order'          => 'DESC',
    'fields'         => 'ids',
    'no_found_rows'  => true,
  ]);

  if (!$q->have_posts()) {
    return 0;
  }

  $db = DB::pdo();

  foreach ($q->posts as $pid) {
    $url = get_permalink($pid);
    if (!$url) continue;
    
    $provider = self::embedding_provider();
    $model    = self::embedding_model();

    $st = $db->prepare("
      SELECT COUNT(*)
      FROM documents
      WHERE origin = 'site'
        AND url = ?
        AND embedding IS NOT NULL
        AND embedding_provider = ?
        AND embedding_model = ?
    ");
    $st->execute([$url, $provider, $model]);

    $already = (int)$st->fetchColumn();
    if ($already > 0) {
      continue;
    }

    $title = get_the_title($pid);
    $raw   = get_post_field('post_content', $pid);
    $html  = apply_filters('the_content', $raw);
    $text  = self::html_to_text($html);

    if (trim($text) === '') {
      continue;
    }

    self::delete_by_origin_and_url('site', $url);
    $count += self::index_text($text, 'site', $url, $title, get_post_type($pid) ?: 'post');
  }

  wp_reset_postdata();

  return $count;
}

private static function private_source_has_current_embedding(string $title): bool {
  if ($title === '') return false;

  $db = DB::pdo();

  $provider = self::embedding_provider();
  $model    = self::embedding_model();

  $st = $db->prepare("
    SELECT COUNT(*)
    FROM documents
    WHERE origin = 'private'
      AND title = ?
      AND embedding IS NOT NULL
      AND embedding_provider = ?
      AND embedding_model = ?
  ");

  $st->execute([$title, $provider, $model]);

  return ((int)$st->fetchColumn()) > 0;
}

private static function source_stored_is_current($stored_entry, int $mtime, int $size, string $provider, string $model): bool {
  /*
   * Ancien format :
   * $stored[$rel] = mtime
   *
   * Nouveau format :
   * $stored[$rel] = [
   *   'mtime' => ...,
   *   'size' => ...,
   *   'provider' => ...,
   *   'model' => ...
   * ]
   */

  if (is_array($stored_entry)) {
    return
      ((int)($stored_entry['mtime'] ?? 0) >= $mtime) &&
      ((int)($stored_entry['size'] ?? -1) === $size) &&
      ((string)($stored_entry['provider'] ?? '') === $provider) &&
      ((string)($stored_entry['model'] ?? '') === $model);
  }

  // Compat ancien format : seulement mtime.
  return ((int)$stored_entry >= $mtime);
}

private static function load_sources_index_state(): array {
  global $wpdb;

  $option = 'ouinpo_sf_sources_index';

  wp_cache_delete($option, 'options');
  wp_cache_delete('alloptions', 'options');
  wp_cache_delete('notoptions', 'options');

  $raw = $wpdb->get_var($wpdb->prepare("
    SELECT option_value
    FROM {$wpdb->options}
    WHERE option_name = %s
    LIMIT 1
  ", $option));

  $stored = is_string($raw) ? maybe_unserialize($raw) : [];

  return is_array($stored) ? $stored : [];
}

private static function save_sources_index_state(array $stored): array {
  global $wpdb;

  $option = 'ouinpo_sf_sources_index';
  $value  = maybe_serialize($stored);

  $ok = $wpdb->query($wpdb->prepare("
    INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
    VALUES (%s, %s, 'off')
    ON DUPLICATE KEY UPDATE
      option_value = VALUES(option_value),
      autoload = 'off'
  ", $option, $value));

  wp_cache_delete($option, 'options');
  wp_cache_delete('alloptions', 'options');
  wp_cache_delete('notoptions', 'options');

  $raw = $wpdb->get_var($wpdb->prepare("
    SELECT option_value
    FROM {$wpdb->options}
    WHERE option_name = %s
    LIMIT 1
  ", $option));

  $check = is_string($raw) ? maybe_unserialize($raw) : [];

  return [
    'ok' => $ok !== false,
    'stored_count' => count($stored),
    'check_count' => is_array($check) ? count($check) : -1,
  ];
}

private static function index_sources_diff(int $budget = 20): int {
  DB::init();

  $count  = 0;
    $stored = self::load_sources_index_state();

  $provider = self::embedding_provider();
  $model    = self::embedding_model();

  $seen = [
    'detected' => 0,
    'removed' => 0,
    'skipped_up_to_date' => 0,
    'forced_missing_chunks' => 0,
    'indexed_files' => 0,
    'zero_chunks' => 0,
  ];

  foreach (array_keys($stored) as $rel) {
    $full = OUINPO_SF_SRC . $rel;

    if (!file_exists($full)) {
      $title = self::title_for_source_file($rel);
      self::delete_private_by_title($title);
      unset($stored[$rel]);
      $seen['removed']++;
    }
  }

  $files = array_merge(
    glob(OUINPO_SF_SRC.'*.{md,txt}', GLOB_BRACE) ?: [],
    glob(OUINPO_SF_SRC.'*.pdf') ?: []
  );

  sort($files);

  foreach ($files as $file) {
    if ($count >= $budget) {
      break;
    }

    if (!is_file($file)) {
      continue;
    }

    $seen['detected']++;

    $rel   = substr($file, strlen(OUINPO_SF_SRC));
    $mtime = @filemtime($file) ?: 0;
    $size  = @filesize($file) ?: 0;
    $title = self::title_for_source_file($file);

    $stored_entry = $stored[$rel] ?? null;

    $looks_current = $stored_entry !== null
      && self::source_stored_is_current($stored_entry, $mtime, $size, $provider, $model);

    if ($looks_current) {
      if (self::private_source_has_current_embedding($title)) {
        $seen['skipped_up_to_date']++;
        continue;
      }

      /*
       * Cas important :
       * l'option dit "à jour", mais SQLite n'a pas les chunks
       * pour le fournisseur/modèle actuel.
       */
      $seen['forced_missing_chunks']++;
    }

    $indexed = self::index_one_source_file($file, $rel);

if ($indexed > 0) {
  $stored[$rel] = [
    'mtime'    => $mtime,
    'size'     => $size,
    'provider' => $provider,
    'model'    => $model,
  ];

  /*
   * Sauvegarde immédiate : si un gros PDF provoque ensuite un souci,
   * les fichiers déjà indexés ne sont pas perdus.
   */
$save_one = self::save_sources_index_state($stored);

error_log(sprintf(
  '[SegFault] Source état sauvegardé : rel=%s indexed=%d saved=%d stored_count=%d check_count=%d',
  $rel,
  $indexed,
  $save_one['ok'] ? 1 : 0,
  $save_one['stored_count'],
  $save_one['check_count']
));
  $count++;
  $seen['indexed_files']++;
} else {
      $seen['zero_chunks']++;

      error_log('[SegFault] Source détectée mais 0 chunk indexé : '.$file);
    }
  }

$save_final = self::save_sources_index_state($stored);

$stored_count = (int)$save_final['stored_count'];
$saved = (bool)$save_final['ok'];
$check_count = (int)$save_final['check_count'];

error_log(sprintf(
  '[SegFault] Sources diff : detected=%d skipped_up_to_date=%d forced_missing_chunks=%d indexed_files=%d zero_chunks=%d removed=%d stored_count=%d saved=%d check_count=%d provider=%s model=%s',
  $seen['detected'],
  $seen['skipped_up_to_date'],
  $seen['forced_missing_chunks'],
  $seen['indexed_files'],
  $seen['zero_chunks'],
  $seen['removed'],
  $stored_count,
  $saved ? 1 : 0,
  $check_count,
  $provider,
  $model
));

return $count;
}
  /* ========= /Indexation différentielle & cron ========= */

private static function embedding_provider(): string {
  $provider = strtolower(trim((string) get_option('ouinpo_sf_rag_embedding_provider', 'openai')));
  return in_array($provider, ['openai', 'albert'], true) ? $provider : 'openai';
}

private static function reranker_enabled(): bool {
  return self::embedding_provider() === 'albert';
}

private static function rerank_results(string $query, array $scored, int $k): array {
  if (empty($scored)) {
    return [];
  }

  if (!self::reranker_enabled()) {
    return array_slice($scored, 0, $k);
  }

  /*
   * Garde-fou :
   * si le meilleur résultat est déjà très supérieur lexicalement,
   * on ne laisse pas le reranker le déclasser.
   *
   * Cas typique :
   * - question courte : "les piles", "explique les piles", "files FIFO"
   * - titre/URL/section très explicite
   */
  $top_score = (float)($scored[0]['score'] ?? 0);
  $second_score = isset($scored[1]) ? (float)($scored[1]['score'] ?? 0) : 0.0;
  $margin = $top_score - $second_score;

  $tokens = self::query_tokens($query, 3, 8);
  $is_short_query = count($tokens) <= 3;

  if (
    $is_short_query &&
    (
      $top_score >= 0.95 ||
      $margin >= 0.25
    )
  ) {
    return array_slice($scored, 0, $k);
  }

  $candidate_count = (int) get_option('ouinpo_sf_rag_rerank_candidates', 40);
  if ($candidate_count < $k) {
    $candidate_count = max($k * 4, 20);
  }
  if ($candidate_count > 80) {
    $candidate_count = 80;
  }

  $candidates = array_slice($scored, 0, $candidate_count);

  try {
    $reranked = Albert::rerank($query, $candidates, $k);

    if (!is_array($reranked) || empty($reranked)) {
      return array_slice($scored, 0, $k);
    }

    return array_slice($reranked, 0, $k);
  } catch (\Throwable $e) {
    error_log('[SegFault RAG rerank] erreur ignorée : ' . $e->getMessage());
    return array_slice($scored, 0, $k);
  }
}
private static function embedding_model(): string {
  if (self::embedding_provider() === 'albert') {
    return Albert::embedding_model();
  }

  $m = trim((string) get_option('ouinpo_sf_embed_model', 'text-embedding-3-large'));
  return $m !== '' ? $m : 'text-embedding-3-large';
}

private static function embed_text(string $text): array {
  if (self::embedding_provider() === 'albert') {
    return Albert::embed($text);
  }

  return OpenAI::embed($text);
}

private static function visibility_for(string $origin, string $ptype = null, string $title = ''): string {
  // Les fichiers dans sources/ sont considérés comme documents prof.
  if ($origin === 'private') return 'teacher';

  $p = mb_strtolower((string)$ptype);
  $t = mb_strtolower($title);

  if (
    str_contains($p, 'solution') ||
    str_contains($p, 'correction') ||
    str_contains($t, 'solution') ||
    str_contains($t, 'solutions') ||
    str_contains($t, 'corrigé') ||
    str_contains($t, 'corrige') ||
    str_contains($t, 'correction') ||
    str_contains($t, 'corrigés') ||
    str_contains($t, 'corriges')
  ) {
    return 'solution';
  }

  return 'public';
}
private static function current_visibility_scope(): array {
  // Public : seulement les contenus publics.
  if (!is_user_logged_in()) {
    return ['public'];
  }

  // Prof / admin : accès aux documents prof et corrigés.
  if (current_user_can('manage_options') || current_user_can('edit_users')) {
    return ['public', 'teacher', 'solution'];
  }

  // Élève connecté : on reste prudent.
  return ['public'];
}

private static function visibility_where_clause(string $prefix = 'vis'): array {
  $allowed = self::current_visibility_scope();

  $parts = [];
  $binds = [];

  foreach ($allowed as $i => $v) {
    $ph = ':' . $prefix . $i;
    $parts[] = $ph;
    $binds[$ph] = $v;
  }

  if (!$parts) {
    return [
      'where' => "visibility = 'public'",
      'binds' => []
    ];
  }

  return [
    'where' => 'visibility IN (' . implode(',', $parts) . ')',
    'binds' => $binds
  ];
}

public static function index_text(string $text, string $origin, string $url, string $title, string $ptype = null): int {
  static $embeddings_used = 0;

    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    $text = preg_replace("/[ \t]+/", " ", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    
    if ($text === '') return 0;

  $chunk_chars = (int) get_option('ouinpo_sf_chunk_chars', 2000);
  if ($chunk_chars <= 400) $chunk_chars = 2000;

  $max_chunks = (int) get_option('ouinpo_sf_max_chunks_run', 40);
  if ($max_chunks < 0) $max_chunks = 0;

  $max_embeddings_run = (int) get_option('ouinpo_sf_max_embeddings_run', 120);
  if ($max_embeddings_run <= 0) $max_embeddings_run = 120;

    $chunks = self::chunk_with_sections($text, $chunk_chars);
    
    if ($max_chunks > 0 && count($chunks) > $max_chunks) {
      $chunks = array_slice($chunks, 0, $max_chunks);
    }

$db = DB::pdo();

$provider = self::embedding_provider();
$model = self::embedding_model();
$hash = hash('sha256', $origin.'|'.$url.'|'.$title.'|'.$ptype.'|'.$text.'|'.$provider.'|'.$model);
$visibility = self::visibility_for($origin, $ptype, $title);

$ins = $db->prepare("
  INSERT INTO documents(
    origin,url,title,chunk,embedding,tokens,ptype,
    embedding_provider,embedding_model,content_hash,chunk_index,section_title,visibility
  )
  VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$n = 0;
foreach ($chunks as $idx => $item) {
    if ($embeddings_used >= $max_embeddings_run) {
      error_log('[SegFault] budget embeddings atteint, arrêt pour cette exécution');
      break;
    }

    $c = is_array($item) ? (string)($item['text'] ?? '') : (string)$item;
    $section_title = is_array($item) ? (string)($item['section_title'] ?? '') : '';

    $c = trim($c);
    if ($c === '') continue;

    $emb = self::embed_text($c);
    if (!$emb) {
      error_log('[SegFault] embedding vide pour un chunk, ignoré');
      continue;
    }

    $ins->execute([
      $origin,
      $url,
      $title,
      $c,
      json_encode($emb),
      self::estimate_tokens($c),
      $ptype,
      $provider,
      $model,
      $hash,
        $idx,
        $section_title,
        $visibility
    ]);
    $n++;
    $embeddings_used++;
  }

  return $n;
}
  // --- Recherche : tokens + stopwords -------------------------------------

  private static function query_tokens(string $query, int $min_len = 3, int $max_tokens = 8): array {
    $q = mb_strtolower(trim($query));
    $q = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $q);
    $q = preg_replace('/\s+/u', ' ', $q);

    $stop = self::stopwords();
    $toks = [];

    foreach (preg_split('/\s+/u', $q) as $w) {
      $w = trim($w);
      if ($w === '') continue;
      if (mb_strlen($w) < $min_len) continue;
      if (isset($stop[$w])) continue;
      $toks[] = $w;
      if (count($toks) >= $max_tokens) break;
    }

    $out = [];
    $seen = [];
    foreach ($toks as $w) {
      if (isset($seen[$w])) continue;
      $seen[$w] = true;
      $out[] = $w;
    }
    return $out;
  }

private static function query_asks_for_exercise(string $query): bool {
  $q = mb_strtolower(remove_accents($query));
  $q = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $q);
  $q = preg_replace('/\s+/u', ' ', trim((string)$q));

  return (bool) preg_match(
    '/\b(exercice|exercices|exo|exos|entrainement|entrainer|entraine|s entrainer|pratique|application|corrige moi|a faire|travail|entrainons|revision|reviser|revise)\b/u',
    $q
  );
}

private static function token_like_variants(string $tok): array {
  $tok = mb_strtolower(trim($tok));
  if ($tok === '') return [];

  $variants = [$tok];

  // pluriel simple : piles -> pile
  if (mb_strlen($tok) > 4 && str_ends_with($tok, 's')) {
    $variants[] = mb_substr($tok, 0, -1);
  }

  // singulier simple : pile -> piles
  if (mb_strlen($tok) > 3 && !str_ends_with($tok, 's')) {
    $variants[] = $tok . 's';
  }

  // quelques variantes utiles sans accent
  $no_accents = remove_accents($tok);
  if ($no_accents !== $tok) {
    $variants[] = $no_accents;

    if (mb_strlen($no_accents) > 4 && str_ends_with($no_accents, 's')) {
      $variants[] = mb_substr($no_accents, 0, -1);
    }

    if (mb_strlen($no_accents) > 3 && !str_ends_with($no_accents, 's')) {
      $variants[] = $no_accents . 's';
    }
  }

  return array_values(array_unique(array_filter($variants)));
}

private static function build_like_where(array $tokens, string $fieldA = 'title', string $fieldB = 'chunk'): array {
  $parts = [];
  $binds = [];

  foreach ($tokens as $i => $tok) {
    $variants = self::token_like_variants($tok);
    if (!$variants) continue;

    $sub = [];

    foreach ($variants as $j => $variant) {
      $ph = ':t'.$i.'_'.$j;
      $sub[] = "($fieldA LIKE $ph OR $fieldB LIKE $ph)";
      $binds[$ph] = '%'.$variant.'%';
    }

    if ($sub) {
      $parts[] = '(' . implode(' OR ', $sub) . ')';
    }
  }

  if (!$parts) {
    return ['where' => '1=1', 'binds' => []];
  }

  return [
    'where' => implode(' OR ', $parts),
    'binds' => $binds,
  ];
}

private static function lexical_bonus(array $row, array $tokens): float {
  if (!$tokens) return 0.0;

  $title   = mb_strtolower((string)($row['title'] ?? ''));
  $section = mb_strtolower((string)($row['section_title'] ?? ''));
  $url     = mb_strtolower((string)($row['url'] ?? ''));
  $chunk   = mb_strtolower((string)($row['chunk'] ?? ''));

  $title   = remove_accents($title);
  $section = remove_accents($section);
  $url     = remove_accents($url);
  $chunk   = remove_accents($chunk);

  $bonus = 0.0;

  foreach ($tokens as $tok) {
    foreach (self::token_like_variants($tok) as $v) {
      $v = remove_accents(mb_strtolower($v));
      if ($v === '') continue;

if (str_contains($title, $v)) {
  $bonus += 0.75;
}

if (str_contains($section, $v)) {
  $bonus += 0.35;
}

if (str_contains($url, $v)) {
  $bonus += 0.65;
}

/*
 * Présence dans le contenu : bonus très faible.
 * Sinon une page générale qui mentionne "pile" ou "file" gagne contre
 * le vrai cours dont le titre est exact.
 */
if (str_contains($chunk, $v)) {
  $bonus += 0.015;
}
    }
  }

  // Bonus spécial pour les slugs de cours très explicites : les-piles-lifo, les-files-fifo, etc.
  foreach ($tokens as $tok) {
    foreach (self::token_like_variants($tok) as $v) {
      $v = remove_accents(mb_strtolower($v));
if ($v !== '' && str_contains($url, 'les-' . $v)) {
  $bonus += 1.20;
}

if ($v !== '' && preg_match('#/(les-)?' . preg_quote($v, '#') . '(-|/|$)#u', $url)) {
  $bonus += 0.90;
}
    }
  }

// Bonus très fort pour les pages de cours dédiées dont le slug contient exactement le mot-clé.
foreach ($tokens as $tok) {
  foreach (self::token_like_variants($tok) as $v) {
    $v = remove_accents(mb_strtolower($v));
    if ($v === '') continue;

    if (
      str_contains($url, '/les-' . $v . '-') ||
      str_contains($url, '/les-' . $v . '/') ||
      str_contains($url, '/' . $v . '-')
    ) {
      $bonus += 1.50;
    }
  }
}

return min($bonus, 2.5);
}

    public static function search(string $query, int $k = 6, int $user_id = 0): array {
      try {
        $db  = DB::pdo();
        if ($user_id <= 0 && is_user_logged_in()) {
          $user_id = get_current_user_id();
        }
        $emb = self::embed_text($query);
        if (!$emb) return [];
    
    $provider = self::embedding_provider();
    $model = self::embedding_model();
    
    $CAP = 500;

    $tokens = self::query_tokens($query, 3, 8);
    $w = self::build_like_where($tokens, 'title', 'chunk');
    $vw = self::visibility_where_clause('vis');

    $titleHitExpr = '0';
    if ($tokens) {
      $hits = [];
      foreach ($tokens as $i => $tok) {
        foreach (self::token_like_variants($tok) as $j => $_variant) {
          $ph = ':t'.$i.'_'.$j;
          $hits[] = "CASE WHEN title LIKE $ph THEN 1 ELSE 0 END";
        }
      }
    
      if ($hits) {
        $titleHitExpr = '(' . implode(' + ', $hits) . ')';
      }
    }

    $exercise_intent = self::query_asks_for_exercise($query);

    $exercise_allowed = true;
    
    if ($exercise_intent) {
      $exercise_allowed = self::exercise_intent_allowed_for_user($query, $user_id);
    }
    
    $order_origin = $exercise_intent
      ? "(origin='exercise') DESC, (origin='site') DESC,"
      : "(origin='site') DESC, (origin='exercise') DESC,";
    
    $sql = "
    SELECT id,origin,url,title,section_title,chunk,embedding,ptype,
           $titleHitExpr AS title_hit
      FROM documents
        WHERE embedding IS NOT NULL
          AND embedding_provider = :emb_provider
          AND embedding_model = :emb_model
          AND {$vw['where']}
          AND ( {$w['where']} )
      ORDER BY {$order_origin} title_hit DESC, id DESC
      LIMIT :cap
    ";

    $st = $db->prepare($sql);
    $st->bindValue(':emb_provider', $provider, \PDO::PARAM_STR);
    $st->bindValue(':emb_model', $model, \PDO::PARAM_STR);
    
    foreach ($w['binds'] as $ph => $val) $st->bindValue($ph, $val, \PDO::PARAM_STR);
    foreach ($vw['binds'] as $ph => $val) $st->bindValue($ph, $val, \PDO::PARAM_STR);
    $st->bindValue(':cap', $CAP, \PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
    $rows = self::filter_document_rows_for_user($rows, $user_id);

    if ($exercise_intent && !$exercise_allowed) {
      $rows = array_values(array_filter($rows, static function($r) {
        return ($r['origin'] ?? '') !== 'exercise';
      }));
    }

    // Fallback : si pas assez de candidats, on complète (en gardant 'site' avant)
    if (count($rows) < min(40, $k * 6)) {
      $need = min($CAP - count($rows), 300);
      if ($need > 0) {
        $st2 = $db->prepare("
          SELECT id,origin,url,title,section_title,chunk,embedding,ptype, 0 AS title_hit
          FROM documents
            WHERE embedding IS NOT NULL
              AND embedding_provider = :emb_provider
              AND embedding_model = :emb_model
              AND {$vw['where']}
            ORDER BY {$order_origin} id DESC
          LIMIT :need
        ");
        
        $st2->bindValue(':emb_provider', $provider, \PDO::PARAM_STR);
        $st2->bindValue(':emb_model', $model, \PDO::PARAM_STR);
        
        foreach ($vw['binds'] as $ph => $val) {
          $st2->bindValue($ph, $val, \PDO::PARAM_STR);
        }
        
        $st2->bindValue(':need', $need, \PDO::PARAM_INT);
        $st2->execute();
        $extra = $st2->fetchAll(\PDO::FETCH_ASSOC);
        $extra = self::filter_document_rows_for_user($extra, $user_id);

        if ($exercise_intent && !$exercise_allowed) {
          $extra = array_values(array_filter($extra, static function($r) {
            return ($r['origin'] ?? '') !== 'exercise';
          }));
        }

        $seen = [];
        foreach ($rows as $r) $seen[$r['id']] = true;
        foreach ($extra as $e) {
          if (!isset($seen[$e['id']])) { $rows[] = $e; $seen[$e['id']] = true; }
        }
      }
    }

$scored = [];
$seen_scored = [];

foreach ($rows as $r) {
  if (empty($r['embedding'])) continue;

  $vec = json_decode($r['embedding'], true);
  if (!is_array($vec) || !$vec) continue;

  $key = md5(
    (string)($r['url'] ?? '') . '|' .
    (string)($r['title'] ?? '') . '|' .
    mb_substr((string)($r['chunk'] ?? ''), 0, 200)
  );

  if (isset($seen_scored[$key])) {
    continue;
  }
  $seen_scored[$key] = true;

  $sim = self::cosine($emb, $vec);

  if (($r['origin'] ?? '') === 'private') {
    $sim += 0.05;
  }

    if ($exercise_intent && $exercise_allowed && ($r['origin'] ?? '') === 'exercise') {
      $sim += 0.80;
    }

  $sim += self::lexical_bonus($r, $tokens);

  $hit = isset($r['title_hit']) ? (int)$r['title_hit'] : 0;
  if ($hit > 0) {
    $sim += min(0.08, 0.02 * $hit);
  }

  $scored[] = [
    'score'         => $sim,
    'title'         => (string)($r['title'] ?? ''),
    'section_title' => (string)($r['section_title'] ?? ''),
    'url'           => (string)($r['url'] ?? ''),
    'chunk'         => (string)($r['chunk'] ?? ''),
    'origin'        => (string)($r['origin'] ?? ''),
    'ptype'         => $r['ptype'] ?? null,
  ];
}

usort($scored, fn($a,$b)=> $a['score'] < $b['score'] ? 1 : -1);

if ($exercise_intent && !$exercise_allowed) {
  array_unshift($scored, [
    'origin' => 'system',
    'ptype'  => 'pedagogical_guard',
    'title'  => 'Garde-fou programme',
    'url'    => '',
    'chunk'  => "GARDE-FOU PRIORITAIRE : la demande porte sur une notion hors programme pour le niveau de l'élève. Il est interdit de proposer un exercice, d'inventer un exercice, de donner un énoncé à faire, ou de fournir une consigne de programmation sur cette notion. Donner seulement une intuition courte, puis proposer une notion du niveau courant à travailler à la place.",
    'score'  => 99.0,
  ]);

  return array_slice($scored, 0, $k);
}

if ($exercise_intent && $exercise_allowed) {
  /*
   * Quand l’utilisateur demande explicitement un exercice,
   * on interroge directement la banque MySQL des exercices.
   * Cela évite de dépendre uniquement du classement vectoriel SQLite.
   */
  $sql_exos = self::match_exercises_direct_by_query($query, 4, $user_id);

  if (empty($sql_exos)) {
    $sql_exos = self::match_exercises_by_query($query, 4, $user_id);
  }

  if (!empty($sql_exos)) {
    $merged = [];

    foreach ($sql_exos as $e) {
      $merged[] = $e;
    }

    foreach ($scored as $s) {
      $merged[] = $s;
    }

    return array_slice($merged, 0, $k);
  }

  /*
   * Fallback : si la recherche MySQL ne trouve rien,
   * on garde les chunks SQLite déjà classés, sans reranker.
   */
  return array_slice($scored, 0, $k);
}

return self::rerank_results($query, $scored, $k);

  } catch (\Throwable $e) {
    error_log('[SegFault RAG search] erreur ignorée : ' . $e->getMessage());
    return [];
  }
}

public static function debug_index_site_post_by_slug(string $slug): array {
  DB::init();

  global $wpdb;

  $slug = sanitize_title($slug);

  $out = [
    'slug' => $slug,
    'post_id' => 0,
    'status' => '',
    'ptype' => '',
    'url' => '',
    'title' => '',
    'raw_len' => 0,
    'html_len' => 0,
    'text_len' => 0,
    'chunks_count' => 0,
    'first_chunk_len' => 0,
    'embedding_dim' => 0,
    'inserted' => 0,
    'error' => '',
  ];

  try {
    $post_id = (int)$wpdb->get_var($wpdb->prepare("
      SELECT ID
      FROM {$wpdb->posts}
      WHERE post_name = %s
        AND post_status = 'publish'
        AND post_type IN ('post', 'page')
      ORDER BY ID DESC
      LIMIT 1
    ", $slug));

    $out['post_id'] = $post_id;

    if ($post_id <= 0) {
      $out['error'] = 'Post introuvable.';
      return $out;
    }

    $post = get_post($post_id);
    if (!$post) {
      $out['error'] = 'get_post() impossible.';
      return $out;
    }

    $out['status'] = (string)get_post_status($post_id);
    $out['ptype']  = (string)get_post_type($post_id);
    $out['url']    = (string)get_permalink($post_id);
    $out['title']  = (string)get_the_title($post_id);

    $raw = (string)get_post_field('post_content', $post_id);
    $html = (string)apply_filters('the_content', $raw);
    $text = self::html_to_text($html);

    $out['raw_len']  = mb_strlen($raw);
    $out['html_len'] = mb_strlen($html);
    $out['text_len'] = mb_strlen($text);

    if (trim($text) === '') {
      $out['error'] = 'Texte extrait vide après html_to_text().';
      return $out;
    }

    $chunk_chars = (int)get_option('ouinpo_sf_chunk_chars', 2000);
    if ($chunk_chars <= 400) $chunk_chars = 2000;

    $chunks = self::chunk_with_sections($text, $chunk_chars);

    $out['chunks_count'] = count($chunks);

    if (!$chunks) {
      $out['error'] = 'Aucun chunk produit par chunk_with_sections().';
      return $out;
    }

    $first = $chunks[0];
    $first_text = is_array($first) ? (string)($first['text'] ?? '') : (string)$first;
    $section_title = is_array($first) ? (string)($first['section_title'] ?? '') : '';

    $first_text = trim($first_text);
    $out['first_chunk_len'] = mb_strlen($first_text);

    if ($first_text === '') {
      $out['error'] = 'Premier chunk vide.';
      return $out;
    }

    $emb = self::embed_text($first_text);

    if (!is_array($emb) || empty($emb)) {
      $out['error'] = 'Embedding vide renvoyé par embed_text().';
      return $out;
    }

    $out['embedding_dim'] = count($emb);

    $db = DB::pdo();

    self::delete_by_origin_and_url('site', $out['url']);

    $provider = self::embedding_provider();
    $model = self::embedding_model();
    $hash = hash('sha256', 'site|'.$out['url'].'|'.$out['title'].'|'.$out['ptype'].'|'.$text.'|'.$provider.'|'.$model);

    $ins = $db->prepare("
      INSERT INTO documents(
        origin,url,title,chunk,embedding,tokens,ptype,
        embedding_provider,embedding_model,content_hash,chunk_index,section_title,visibility
      )
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $ins->execute([
      'site',
      $out['url'],
      $out['title'],
      $first_text,
      json_encode($emb),
      self::estimate_tokens($first_text),
      $out['ptype'],
      $provider,
      $model,
      $hash,
      0,
      $section_title,
      'public'
    ]);

    $out['inserted'] = 1;

    return $out;

  } catch (\Throwable $e) {
    $out['error'] = $e->getMessage();
    return $out;
  }
}

public static function debug_search(string $query, int $k = 8): array {
  $query = trim($query);
  if ($query === '') return [];

  try {
    $db  = DB::pdo();
    $emb = self::embed_text($query);
    if (!$emb) return [];

    $provider = self::embedding_provider();
    $model = self::embedding_model();

    $CAP = 500;

    $tokens = self::query_tokens($query, 3, 8);
    $w = self::build_like_where($tokens, 'title', 'chunk');
    $vw = self::visibility_where_clause('dbgvis');

    $exercise_intent = self::query_asks_for_exercise($query);
    
    $order_origin = $exercise_intent
      ? "(origin='exercise') DESC, (origin='site') DESC,"
      : "(origin='site') DESC, (origin='exercise') DESC,";

    $titleHitExpr = '0';
    if ($tokens) {
      $hits = [];
      foreach ($tokens as $i => $tok) {
        foreach (self::token_like_variants($tok) as $j => $_variant) {
          $ph = ':t'.$i.'_'.$j;
          $hits[] = "CASE WHEN title LIKE $ph THEN 1 ELSE 0 END";
        }
      }
    
      if ($hits) {
        $titleHitExpr = '(' . implode(' + ', $hits) . ')';
      }
    }

    $sql = "
      SELECT id,origin,url,title,section_title,chunk,embedding,ptype,visibility,
             $titleHitExpr AS title_hit
      FROM documents
      WHERE embedding IS NOT NULL
        AND embedding_provider = :emb_provider
        AND embedding_model = :emb_model
        AND {$vw['where']}
        AND ( {$w['where']} )
      ORDER BY {$order_origin} title_hit DESC, id DESC
      LIMIT :cap
    ";

    $st = $db->prepare($sql);
    $st->bindValue(':emb_provider', $provider, \PDO::PARAM_STR);
    $st->bindValue(':emb_model', $model, \PDO::PARAM_STR);

    foreach ($w['binds'] as $ph => $val) {
      $st->bindValue($ph, $val, \PDO::PARAM_STR);
    }

    foreach ($vw['binds'] as $ph => $val) {
      $st->bindValue($ph, $val, \PDO::PARAM_STR);
    }

    $st->bindValue(':cap', $CAP, \PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

// Fallback debug : si la pré-sélection lexicale ne trouve rien,
// on teste quand même les derniers chunks compatibles par modèle/visibilité.
if (empty($rows)) {
  $sql_fallback = "
    SELECT id,origin,url,title,section_title,chunk,embedding,ptype,visibility,
           0 AS title_hit
    FROM documents
    WHERE embedding IS NOT NULL
      AND embedding_provider = :emb_provider
      AND embedding_model = :emb_model
      AND {$vw['where']}
    ORDER BY {$order_origin} id DESC
    LIMIT :cap
  ";

  $st_fb = $db->prepare($sql_fallback);
  $st_fb->bindValue(':emb_provider', $provider, \PDO::PARAM_STR);
  $st_fb->bindValue(':emb_model', $model, \PDO::PARAM_STR);

  foreach ($vw['binds'] as $ph => $val) {
    $st_fb->bindValue($ph, $val, \PDO::PARAM_STR);
  }

  $st_fb->bindValue(':cap', $CAP, \PDO::PARAM_INT);
  $st_fb->execute();
  $rows = $st_fb->fetchAll(\PDO::FETCH_ASSOC);
}

$scored = [];
$seen_scored = [];

foreach ($rows as $r) {
  if (empty($r['embedding'])) continue;

  $vec = json_decode($r['embedding'], true);
  if (!is_array($vec) || !$vec) continue;

  $key = md5(
    (string)($r['url'] ?? '') . '|' .
    (string)($r['title'] ?? '') . '|' .
    mb_substr((string)($r['chunk'] ?? ''), 0, 200)
  );

  if (isset($seen_scored[$key])) {
    continue;
  }
  $seen_scored[$key] = true;

  $sim = self::cosine($emb, $vec);

  if (($r['origin'] ?? '') === 'private') {
    $sim += 0.05;
  }

    if ($exercise_intent && ($r['origin'] ?? '') === 'exercise') {
      $sim += 0.80;
    }

  $sim += self::lexical_bonus($r, $tokens);

  $hit = isset($r['title_hit']) ? (int)$r['title_hit'] : 0;
  if ($hit > 0) {
    $sim += min(0.08, 0.02 * $hit);
  }

  $scored[] = [
    'score'         => $sim,
    'id'            => (int)($r['id'] ?? 0),
    'title'         => (string)($r['title'] ?? ''),
    'section_title' => (string)($r['section_title'] ?? ''),
    'url'           => (string)($r['url'] ?? ''),
    'origin'        => (string)($r['origin'] ?? ''),
    'ptype'         => (string)($r['ptype'] ?? ''),
    'visibility'    => (string)($r['visibility'] ?? ''),
    'chunk'         => (string)($r['chunk'] ?? ''),
  ];
}

    usort($scored, fn($a, $b) => $a['score'] < $b['score'] ? 1 : -1);

    // Pour le debug, on affiche le résultat avant rerank ET après rerank.
    $before = array_slice($scored, 0, max($k, 12));
    
if ($exercise_intent) {
  $sql_exos = self::match_exercises_direct_by_query($query, 4, get_current_user_id());

  if (empty($sql_exos)) {
    $sql_exos = self::match_exercises_by_query($query, 4, get_current_user_id());
  }

  if (!empty($sql_exos)) {
    $merged = [];

    foreach ($sql_exos as $e) {
      $merged[] = $e;
    }

    foreach ($scored as $s) {
      $merged[] = $s;
    }

    $after = array_slice($merged, 0, $k);
  } else {
    $after = array_slice($scored, 0, $k);
  }
} else {
  $after = self::rerank_results($query, $scored, $k);
}

    return [
      'query'           => $query,
      'provider'        => $provider,
      'model'           => $model,
      'tokens'          => $tokens,
      'exercise_intent' => $exercise_intent,
      'before'          => $before,
      'after'           => $after,
    ];

  } catch (\Throwable $e) {
    error_log('[SegFault RAG debug_search] erreur : ' . $e->getMessage());
    return [
      'query' => $query,
      'error' => $e->getMessage(),
    ];
  }
}

// ============================================================
// Exercices : match simple (titre/slug/énoncé) + wrapper search_with_exercises
// ============================================================

private static function exercise_url(string $slug, int $id = 0): string {
  if ($id > 0) return home_url('/exercice/?exo='.$id);
  return home_url('/exercice/?exo='.urlencode($slug));
}

/**
 * Essaie de déduire un niveau scolaire depuis le user_meta nsi_level.
 * Retourne: 'premiere' | 'terminale' | '' (si inconnu)
 * (À adapter si tu as une table groupes/levels côté ouinpo-exercices.)
 */
private static function user_level_slug(int $user_id): string {
  if ($user_id <= 0) return '';

  // Source prioritaire : groupe / classe
  if (function_exists('\\OuInPo\\SegFault\\ouinpo_sf_student_level_from_group')) {
    $dbg = [];
    $level = \OuInPo\SegFault\ouinpo_sf_student_level_from_group($user_id, $dbg);
    if (in_array($level, ['seconde', 'premiere', 'terminale'], true)) {
      return $level;
    }
  }

  // Fallback user_meta
  $m = strtolower(trim((string) get_user_meta($user_id, 'nsi_level', true)));
  if (in_array($m, ['seconde', 'premiere', 'terminale'], true)) {
    return $m;
  }

  return '';
}

/**
 * Recherche d'exercices par requête (sans embeddings) :
 * - score simple basé sur tokens (titre > slug > énoncé)
 * - optionnel: filtre par niveau (si table exo_level dispo)
 */

private static function exercise_intent_allowed_for_user(string $query, int $user_id): bool {
  /*
   * Si aucun niveau élève n’est connu, on ne déclenche pas le garde-fou
   * "hors programme". Un visiteur non connecté n’a pas de niveau pédagogique.
   */
  if ($user_id <= 0 || !is_user_logged_in()) {
    return true;
  }

  // Prof / admin : pas de bridage pédagogique.
  if (current_user_can('manage_options') || current_user_can('edit_users')) {
    return true;
  }

  return !self::topic_is_out_of_program_for_user($query, $user_id);
}

private static function exercise_competency_needles_from_query(string $query): array {
  $q = remove_accents(mb_strtolower($query));
  $q = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $q);
  $q = preg_replace('/\s+/u', ' ', trim($q));

  $needles = [];

  // Première NSI — algorithmique
  if (
    str_contains($q, 'dichotomique') ||
    str_contains($q, 'dichotomie') ||
    str_contains($q, 'recherche binaire') ||
    str_contains($q, 'binary search')
  ) {
    $needles[] = 'dichotom';
    $needles[] = 'recherche binaire';
  }

  if (
    str_contains($q, 'glouton') ||
    str_contains($q, 'gloutons')
  ) {
    $needles[] = 'glouton';
  }

  if (
    str_contains($q, 'tri insertion') ||
    str_contains($q, 'tri par insertion') ||
    str_contains($q, 'insertion')
  ) {
    $needles[] = 'insertion';
  }

  if (
    str_contains($q, 'tri selection') ||
    str_contains($q, 'tri par selection') ||
    str_contains($q, 'selection')
  ) {
    $needles[] = 'selection';
    $needles[] = 'sélection';
  }

  if (
    str_contains($q, 'k plus proches voisins') ||
    str_contains($q, 'knn') ||
    str_contains($q, 'k-nn')
  ) {
    $needles[] = 'plus proches voisins';
    $needles[] = 'knn';
  }

  // Programmation / structures usuelles Première
  if (
    str_contains($q, 'dictionnaire') ||
    str_contains($q, 'dictionnaires')
  ) {
    $needles[] = 'dictionnaire';
  }

  if (
    str_contains($q, 'liste') ||
    str_contains($q, 'listes') ||
    str_contains($q, 'tableau') ||
    str_contains($q, 'tableaux')
  ) {
    $needles[] = 'tableau';
    $needles[] = 'liste';
  }

  if (
    str_contains($q, 'fonction') ||
    str_contains($q, 'fonctions')
  ) {
    $needles[] = 'fonction';
  }

  // Base de données
  if (
    str_contains($q, 'sql') ||
    str_contains($q, 'base de donnees') ||
    str_contains($q, 'bases de donnees') ||
    str_contains($q, 'relationnel')
  ) {
    $needles[] = 'base de données';
    $needles[] = 'sql';
    $needles[] = 'relationnel';
  }

  return array_values(array_unique(array_filter($needles)));
}

private static function match_courses_direct_by_query(string $query, int $limit = 3, int $user_id = 0): array {
  global $wpdb;

  $t_post_comp = $wpdb->prefix . 'ouin_exo_post_competency';
  $t_comp      = $wpdb->prefix . 'ouin_exo_competencies';
  $t_posts     = $wpdb->posts;

  if (
    !self::mysql_table_exists($t_post_comp) ||
    !self::mysql_table_exists($t_comp)
  ) {
    return [];
  }

  $q_phrase = trim($query);
  if ($q_phrase === '') return [];

  $competency_needles = self::exercise_competency_needles_from_query($q_phrase);

  if (!empty($competency_needles)) {
    $tokens = $competency_needles;
  } else {
    $tokens = self::query_tokens($q_phrase, 3, 10);
    if (!$tokens) {
      $tokens = [mb_strtolower($q_phrase)];
    }
  }

  $level = self::user_level_slug($user_id);

  $level_filter = '';
  $level_args = [];

  if ($level !== '' && function_exists('\\OuInPo\\SegFault\\ouinpo_sf_allowed_competency_levels')) {
    $allowed_levels = \OuInPo\SegFault\ouinpo_sf_allowed_competency_levels($level);
    if (!empty($allowed_levels)) {
      $level_placeholders = implode(',', array_fill(0, count($allowed_levels), '%s'));
      $level_filter = "AND c.level IN ({$level_placeholders})";
      $level_args = $allowed_levels;
    }
  }

  $where_parts = [];
  $where_args = [];
  $score_parts = [];
  $score_args = [];

  foreach ($tokens as $tok) {
    $tok = mb_strtolower(trim((string)$tok));
    if ($tok === '') continue;

    foreach (self::token_like_variants($tok) as $v) {
      $like = '%' . $wpdb->esc_like($v) . '%';

      $where_parts[] = "
        LOWER(c.competency) LIKE %s
        OR LOWER(c.domain) LIKE %s
        OR LOWER(c.domain_slug) LIKE %s
        OR LOWER(c.slug) LIKE %s
      ";

      $where_args[] = $like;
      $where_args[] = $like;
      $where_args[] = $like;
      $where_args[] = $like;

      $score_parts[] = "
        CASE WHEN LOWER(c.competency) LIKE %s THEN 20 ELSE 0 END
        + CASE WHEN LOWER(c.domain_slug) LIKE %s THEN 8 ELSE 0 END
        + CASE WHEN LOWER(c.domain) LIKE %s THEN 6 ELSE 0 END
        + CASE WHEN LOWER(c.slug) LIKE %s THEN 4 ELSE 0 END
      ";

      $score_args[] = $like;
      $score_args[] = $like;
      $score_args[] = $like;
      $score_args[] = $like;
    }
  }

  if (!$where_parts || !$score_parts) {
    return [];
  }

  $where_sql = implode(' OR ', array_map(static fn($p) => '(' . $p . ')', $where_parts));
  $score_sql = implode(' + ', array_map(static fn($p) => '(' . $p . ')', $score_parts));

  $limit = max(1, min(8, (int)$limit));

  $sql = "
    SELECT
      p.ID,
      p.post_title,
      p.post_type,
      GROUP_CONCAT(DISTINCT CONCAT(c.domain, ' — ', c.competency) SEPARATOR ' | ') AS comp_labels,
      ({$score_sql}) AS score_direct
    FROM {$t_posts} p
    INNER JOIN {$t_post_comp} pc ON pc.post_id = p.ID
    INNER JOIN {$t_comp} c ON c.id = pc.competency_id
    WHERE p.post_status = 'publish'
      AND p.post_type IN ('post', 'page')
      AND c.active = 1
      {$level_filter}
      AND ({$where_sql})
    GROUP BY p.ID
    ORDER BY score_direct DESC, p.ID DESC
    LIMIT %d
  ";

  $prepared_args = array_merge(
    $score_args,
    $level_args,
    $where_args,
    [$limit]
  );

  $rows = $wpdb->get_results(
    $wpdb->prepare($sql, ...$prepared_args),
    ARRAY_A
  ) ?: [];

  $out = [];

  foreach ($rows as $r) {
    $post_id = (int)($r['ID'] ?? 0);
    if ($post_id <= 0) continue;

    $url = get_permalink($post_id);
    if (!$url) continue;

    $title = trim((string)($r['post_title'] ?? ''));
    if ($title === '') $title = get_the_title($post_id);

    $comp_labels = trim((string)($r['comp_labels'] ?? ''));

    $out[] = [
      'origin' => 'site',
      'ptype'  => (string)($r['post_type'] ?? 'post'),
      'title'  => $title,
      'url'    => $url,
      'chunk'  => $comp_labels !== ''
        ? 'Cours proposé depuis les compétences BO : ' . $comp_labels
        : 'Cours proposé depuis les compétences BO.',
      'score'  => 9.0,
    ];
  }

  return $out;
}

public static function search_courses_by_competency(string $query, int $limit = 3, int $user_id = 0): array {
  if ($user_id <= 0 && is_user_logged_in()) {
    $user_id = get_current_user_id();
  }

  return self::match_courses_direct_by_query($query, $limit, $user_id);
}
 
private static function match_exercises_direct_by_query(string $query, int $limit = 5, int $user_id = 0): array {
  global $wpdb;

  $t_exo    = $wpdb->prefix . 'ouin_exo_exercises';
  $t_exam   = $wpdb->prefix . 'ouin_exo_exam_meta';
  $t_link   = $wpdb->prefix . 'ouin_exo_exercise_competency';
  $t_comp   = $wpdb->prefix . 'ouin_exo_competencies';
  $t_status = $wpdb->prefix . 'ouin_exo_user_status';

  $q_phrase = trim($query);
  if ($q_phrase === '') return [];

    $competency_needles = self::exercise_competency_needles_from_query($q_phrase);
    
    if (!empty($competency_needles)) {
      $tokens = $competency_needles;
    } else {
      $tokens = self::query_tokens($q_phrase, 3, 10);
      if (!$tokens) {
        $tokens = [mb_strtolower($q_phrase)];
      }
    }

  $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t_exo}", 0) ?: [];

  if (in_array('statement_html', $cols, true)) {
    $statement_col = 'statement_html';
  } elseif (in_array('statement', $cols, true)) {
    $statement_col = 'statement';
  } else {
    return [];
  }

    $level = self::user_level_slug($user_id);
    
    $level_filter = '';
    $level_args = [];
    
    if ($level !== '' && function_exists('\\OuInPo\\SegFault\\ouinpo_sf_allowed_competency_levels')) {
      $allowed_levels = \OuInPo\SegFault\ouinpo_sf_allowed_competency_levels($level);
      if (!empty($allowed_levels)) {
        $level_placeholders = implode(',', array_fill(0, count($allowed_levels), '%s'));
        $level_filter = "AND c.level IN ({$level_placeholders})";
        $level_args = $allowed_levels;
      }
    }

  $where_parts = [];
  $score_parts = [];
  $where_args = [];
  $score_args = [];

foreach ($tokens as $tok) {
  $tok = mb_strtolower(trim((string)$tok));
  if ($tok === '') continue;

  foreach (self::token_like_variants($tok) as $v) {
    $like = '%' . $wpdb->esc_like($v) . '%';

    // Recherche volontairement centrée sur les rattachements BO.
    $where_parts[] = "
      LOWER(c.competency) LIKE %s
      OR LOWER(c.domain) LIKE %s
      OR LOWER(c.domain_slug) LIKE %s
    ";

    $where_args[] = $like;
    $where_args[] = $like;
    $where_args[] = $like;

    // Score pédagogique : compétence > domaine_slug > domaine.
    $score_parts[] = "
      CASE WHEN LOWER(c.competency) LIKE %s THEN 20 ELSE 0 END
      + CASE WHEN LOWER(c.domain_slug) LIKE %s THEN 8 ELSE 0 END
      + CASE WHEN LOWER(c.domain) LIKE %s THEN 6 ELSE 0 END
    ";

    $score_args[] = $like;
    $score_args[] = $like;
    $score_args[] = $like;
  }
}

  if (!$where_parts || !$score_parts) {
    return [];
  }

  $where_sql = implode(' OR ', array_map(static fn($p) => '(' . $p . ')', $where_parts));
  $score_sql = implode(' + ', array_map(static fn($p) => '(' . $p . ')', $score_parts));

  $join_status = '';
  $status_filter = '';
  $status_score = '0';
  $status_args = [];

  if ($user_id > 0) {
    $join_status = "LEFT JOIN {$t_status} us ON us.user_id = %d AND us.exercise_id = e.id";
    $status_args[] = $user_id;

    // On exclut les exercices déjà réussis.
    $status_filter = "AND (us.status IS NULL OR us.status <> 'solved')";

    // On garde les tentés possibles, mais on les met après les nouveaux.
    $status_score = "
      CASE
        WHEN us.status = 'attempted' THEN -4
        ELSE 0
      END
    ";
  }

  $limit = max(1, min(12, (int)$limit));

  $sql = "
    SELECT
      e.id,
      e.title,
      e.slug,
      GROUP_CONCAT(DISTINCT CONCAT(c.domain, ' — ', c.competency) SEPARATOR ' | ') AS comp_labels,
      ({$score_sql}) + {$status_score} AS score_direct
    FROM {$t_exo} e
    INNER JOIN {$t_link} ec ON ec.exercise_id = e.id
    INNER JOIN {$t_comp} c ON c.id = ec.competency_id
    LEFT JOIN {$t_exam} em ON em.exercise_id = e.id
    {$join_status}
    WHERE e.is_active = 1
      AND c.active = 1
      {$level_filter}
      {$status_filter}
      AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')
      AND ({$where_sql})
    GROUP BY e.id
    ORDER BY score_direct DESC, e.id DESC
    LIMIT %d
  ";

  $prepared_args = array_merge(
    $score_args,
    $status_args,
    $level_args,
    $where_args,
    [$limit]
  );

  $rows = $wpdb->get_results(
    $wpdb->prepare($sql, ...$prepared_args),
    ARRAY_A
  ) ?: [];

  $out = [];

  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    if ($id <= 0) continue;
    
$comp_labels = trim((string)($r['comp_labels'] ?? ''));

$title = trim((string)($r['title'] ?? '')) ?: ('Exercice #' . $id);
$slug  = trim((string)($r['slug'] ?? ''));

    $out[] = [
      'origin' => 'exercise',
      'ptype'  => 'exercise',
      'exo_id' => $id,
      'title'  => $title,
      'url'    => self::exercise_url($slug, $id),
      'chunk'  => $comp_labels !== ''
        ? 'Exercice proposé depuis la banque OuInPo. Compétences BO : ' . $comp_labels
        : 'Exercice proposé depuis la banque OuInPo.',
      'score'  => 10.0,
    ];
  }

  return $out;
}

private static function match_exercises_by_query(string $query, int $limit = 5, int $user_id = 0): array {
  global $wpdb;

  $t_exo  = $wpdb->prefix . 'ouin_exo_exercises';
  $t_link = $wpdb->prefix . 'ouin_exo_exercise_competency';
  $t_comp = $wpdb->prefix . 'ouin_exo_competencies';
  $t_exam = $wpdb->prefix . 'ouin_exo_exam_meta';

  $q_phrase = trim($query);
  if ($q_phrase === '') return [];

  // Tokens utiles (stopwords filtrés)
  $tokens = self::query_tokens($q_phrase, 3, 10);
  // Fallback si tout a été filtré : on garde la phrase brute
  if (!$tokens) $tokens = [mb_strtolower($q_phrase)];

  // Détecte les colonnes existantes côté compétences
  $has_domain = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t_comp} LIKE %s", 'domain'));
  $has_domain_slug = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t_comp} LIKE %s", 'domain_slug'));
  $has_competency = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t_comp} LIKE %s", 'competency'));
  $has_slug = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t_comp} LIKE %s", 'slug'));

  if (!$has_domain && !$has_domain_slug && !$has_competency && !$has_slug) return [];

  // --- Étape A : récupérer les IDs de compétences qui matchent ---
  $conds = [];
  $args  = [];

  foreach ($tokens as $tok) {
    $like = '%' . $wpdb->esc_like($tok) . '%';
    $sub = [];

    if ($has_competency) { $sub[] = "c.competency LIKE %s";  $args[] = $like; }     // le plus important
    if ($has_domain_slug){ $sub[] = "c.domain_slug LIKE %s"; $args[] = $like; }
    if ($has_domain)     { $sub[] = "c.domain LIKE %s";      $args[] = $like; }
    if ($has_slug)       { $sub[] = "c.slug LIKE %s";        $args[] = $like; }

    $conds[] = '(' . implode(' OR ', $sub) . ')';
  }

  $where_comp = implode(' OR ', $conds);

  // On limite le nombre de compétences candidates pour ne pas exploser la suite
  $cap_comp = 30;

    $level = self::user_level_slug($user_id);
    
    $level_filter = '';
    $level_args = [];
    
    if ($level !== '' && function_exists('\\OuInPo\\SegFault\\ouinpo_sf_allowed_competency_levels')) {
      $allowed_levels = \OuInPo\SegFault\ouinpo_sf_allowed_competency_levels($level);
      if (!empty($allowed_levels)) {
        $level_placeholders = implode(',', array_fill(0, count($allowed_levels), '%s'));
        $level_filter = "AND c.level IN ({$level_placeholders})";
        $level_args = $allowed_levels;
      }
    }


  $sql_comp = "
    SELECT
      c.id,
      c.competency,
      " . ($has_domain_slug ? "c.domain_slug" : "''") . " AS domain_slug,
      " . ($has_domain ? "c.domain" : "''") . " AS domain_label,
      (
        " . ($has_competency ? "SUM(CASE WHEN c.competency LIKE %s THEN 3 ELSE 0 END)" : "0") . "
        " . ($has_domain_slug ? "+ SUM(CASE WHEN c.domain_slug LIKE %s THEN 2 ELSE 0 END)" : "") . "
        " . ($has_domain ? "+ SUM(CASE WHEN c.domain LIKE %s THEN 1 ELSE 0 END)" : "") . "
        " . ($has_slug ? "+ SUM(CASE WHEN c.slug LIKE %s THEN 1 ELSE 0 END)" : "") . "
      ) AS match_score
    FROM {$t_comp} c
    WHERE c.active = 1
      {$level_filter}
      AND ( {$where_comp} )
    GROUP BY c.id
    ORDER BY match_score DESC, c.id DESC
    LIMIT %d
  ";

  // ⚠️ On doit fournir les LIKE du "match_score" pour la phrase brute (pas token par token),
  // sinon ça devient un enfer à préparer. On prend donc la phrase brute :
  $like_phrase = '%' . $wpdb->esc_like(mb_strtolower($q_phrase)) . '%';

  $score_args = [];
  if ($has_competency)  $score_args[] = $like_phrase;
  if ($has_domain_slug) $score_args[] = $like_phrase;
  if ($has_domain)      $score_args[] = $like_phrase;
  if ($has_slug)        $score_args[] = $like_phrase;

$sql_comp_prepared = $wpdb->prepare(
  $sql_comp,
  ...array_merge($score_args, $level_args, $args, [$cap_comp])
);

$comp_rows = $wpdb->get_results($sql_comp_prepared, ARRAY_A);
  if (!$comp_rows) return [];

$comp_scores = [];
foreach ($comp_rows as $cr) {
  $cid = (int)($cr['id'] ?? 0);
  if ($cid <= 0) continue;
  $comp_scores[$cid] = (int)($cr['match_score'] ?? 0);
}

  $comp_ids = [];
  $comp_map = [];
  foreach ($comp_rows as $cr) {
    $cid = (int)($cr['id'] ?? 0);
    if ($cid <= 0) continue;
    $comp_ids[] = $cid;

    $label = trim((string)($cr['competency'] ?? ''));
    $dom   = trim((string)($cr['domain_slug'] ?? ''));
    if ($dom === '') $dom = trim((string)($cr['domain_label'] ?? ''));

    $comp_map[$cid] = ($dom !== '' ? ($dom.' — ') : '') . ($label !== '' ? $label : ('Compétence #'.$cid));
  }
  $comp_ids = array_values(array_unique($comp_ids));
  if (!$comp_ids) return [];


// Bonus sur l'énoncé (statement) : on boost si les tokens apparaissent dans le texte
$bonusParts = [];
$bonusArgs  = [];

foreach ($tokens as $tok) {
  $like = '%' . $wpdb->esc_like(mb_strtolower($tok)) . '%';

  // ✅ MAX(...) pour être compatible ONLY_FULL_GROUP_BY
  // et éviter que le JOIN sur ec ne multiplie le bonus.
  $bonusParts[] = "MAX(CASE WHEN LOWER(e.statement) LIKE %s THEN 3 ELSE 0 END)";
  $bonusArgs[]  = $like;
}

$bonusExpr = $bonusParts ? ('(' . implode(' + ', $bonusParts) . ')') : '0';

// --- Étape B : récupérer les exercices liés à ces compétences ---
// ✅ exos actifs + lien sur comp_ids + score = SOMME des scores des compétences matchées
$placeholders = implode(',', array_fill(0, count($comp_ids), '%d'));

// Table dérivée MySQL-compatible (UNION ALL) : (competency_id, score)
$selects = [];
$args2   = [];
foreach ($comp_ids as $cid) {
  $selects[] = "SELECT %d AS competency_id, %d AS score";
  $args2[] = (int)$cid;
  $args2[] = (int)($comp_scores[$cid] ?? 1);
}
$score_table = implode("\nUNION ALL\n", $selects);

// ✅ Bonus texte (statement) : agrégé en MAX(...) pour éviter la duplication via JOIN
$bonusParts = [];
$bonusArgs  = [];

foreach ($tokens as $tok) {
  $like = '%' . $wpdb->esc_like(mb_strtolower($tok)) . '%';

  // 3 points si token présent dans statement
  $bonusParts[] = "CASE WHEN LOWER(COALESCE(e.statement,'')) LIKE %s THEN 3 ELSE 0 END";
  $bonusArgs[]  = $like;
}

$bonusExpr = $bonusParts ? ('(' . implode(' + ', $bonusParts) . ')') : '0';

$sql_exo = "
  SELECT
    e.id,
    e.title,
    e.slug,
    SUM(cs.score) AS total_score,
    COUNT(DISTINCT ec.competency_id) AS nb_comp_match,
    {$bonusExpr} AS bonus_stmt,
    (SUM(cs.score) + {$bonusExpr}) AS final_score,
    GROUP_CONCAT(DISTINCT ec.competency_id ORDER BY ec.competency_id SEPARATOR ',') AS comp_ids
  FROM {$t_exo} e
  INNER JOIN {$t_link} ec ON ec.exercise_id = e.id
  INNER JOIN (
    {$score_table}
  ) cs ON cs.competency_id = ec.competency_id
  LEFT JOIN {$t_exam} em ON em.exercise_id = e.id
  WHERE e.is_active = 1
    AND ec.competency_id IN ({$placeholders})
    AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')
  GROUP BY e.id
  ORDER BY final_score DESC, nb_comp_match DESC, e.id DESC
  LIMIT %d
";

$limit = max(1, min(12, (int)$limit));

$allArgs = array_merge(
  $bonusArgs,        // pour bonus_stmt
  $bonusArgs,        // pour final_score (2e usage du même bonusExpr)
  $args2,            // UNION ALL score_table (%d,%d,...)
  $comp_ids,         // placeholders IN (...)
  [$limit]           // LIMIT %d
);

$exo_rows = $wpdb->get_results(
  $wpdb->prepare($sql_exo, ...$allArgs),
  ARRAY_A
);

if (!$exo_rows) return [];

  // --- Étape C : format "sources" ---
  $out = [];
  foreach ($exo_rows as $r) {
    $id = (int)($r['id'] ?? 0);
    if ($id <= 0) continue;

    $title = trim((string)($r['title'] ?? '')) ?: ('Exercice #'.$id);
    $slug  = (string)($r['slug'] ?? '');

    // Reconstruit les libellés des compétences matchées (lisible)
    $labels = [];
    $ids_csv = (string)($r['comp_ids'] ?? '');
    if ($ids_csv !== '') {
      foreach (explode(',', $ids_csv) as $cid_raw) {
        $cid = (int)trim($cid_raw);
        if ($cid <= 0) continue;
        if (isset($comp_map[$cid])) $labels[] = $comp_map[$cid];
      }
    }
    $labels = array_slice($labels, 0, 4); // pas un roman sous la source

    $out[] = [
      'origin' => 'exercise',
      'ptype'  => 'exercise',
      'exo_id' => $id,
      'title'  => $title,
      'url'    => self::exercise_url($slug, $id),
      'chunk'  => $labels ? ('Compétences BO : ' . implode(' | ', $labels)) : 'Exercice lié aux compétences BO recherchées.',
      'score'  => 0.0,
    ];
  }

  return $out;
}

/**
 * Wrapper principal : renvoie les chunks RAG + ajoute des exos (en fin).
 * - $k = nb de chunks "cours/private" pour le contexte IA
 * - OUINPO_SEGFAULT_EXO_MAX = nb d'exos max ajoutés
 */
    public static function search_with_exercises(string $query, int $user_id = 0, int $k = 6): array {
      $base = self::search($query, $k, $user_id);
    
        if ($user_id > 0 && !self::exercise_intent_allowed_for_user($query, $user_id)) {
          $base = array_values(array_filter($base, static function($r) {
            return ($r['origin'] ?? '') !== 'exercise';
          }));
        
          array_unshift($base, [
            'origin' => 'system',
            'ptype'  => 'pedagogical_guard',
            'title'  => 'Garde-fou programme',
            'url'    => '',
            'chunk'  => "La demande porte sur une notion hors programme pour le niveau de l'élève. Ne pas proposer d'exercice sur cette notion. Donner seulement une intuition légère si c'est utile, puis réorienter vers une notion du niveau courant.",
            'score'  => 99.0,
          ]);
        
          return $base;
        }
    
      if ($user_id <= 0 || !is_user_logged_in()) return $base;
    
      $limit_exos = defined('OUINPO_SEGFAULT_EXO_MAX') ? (int) OUINPO_SEGFAULT_EXO_MAX : 3;
      if ($limit_exos < 1) $limit_exos = 1;
      if ($limit_exos > 8) $limit_exos = 8;
    
      $exos = self::match_exercises_direct_by_query($query, $limit_exos, $user_id);
    
      if (empty($exos)) {
        $exos = self::match_exercises_by_query($query, $limit_exos, $user_id);
      }
    
      foreach ($exos as $e) $base[] = $e;
    
      return $base;
    }

    public static function search_sources(string $query, int $k = 6): array {
      try {
            $db  = DB::pdo();
            $user_id = is_user_logged_in() ? get_current_user_id() : 0;
            $emb = self::embed_text($query);
            if (!$emb) return [];
        
        $provider = self::embedding_provider();
        $model = self::embedding_model();
        
        $CAP = 500;
    
      $tokens = self::query_tokens($query, 3, 8);
      $w = self::build_like_where($tokens, 'title', 'chunk');
      $vw = self::visibility_where_clause('srcvis');

$exercise_intent = self::query_asks_for_exercise($query);

$order_origin = $exercise_intent
  ? "(origin='exercise') DESC, (origin='site') DESC,"
  : "(origin='site') DESC, (origin='exercise') DESC,";
    
    $titleHitExpr = '0';
    if ($tokens) {
      $hits = [];
      foreach ($tokens as $i => $tok) {
        foreach (self::token_like_variants($tok) as $j => $_variant) {
          $ph = ':t'.$i.'_'.$j;
          $hits[] = "CASE WHEN title LIKE $ph THEN 1 ELSE 0 END";
        }
      }
    
      if ($hits) {
        $titleHitExpr = '(' . implode(' + ', $hits) . ')';
      }
    }
    
      // ✅ seulement ce qui est "cliquable"
      $sql = "
        SELECT id,origin,url,title,section_title,chunk,embedding,ptype,
               $titleHitExpr AS title_hit
        FROM documents
        WHERE embedding IS NOT NULL
          AND embedding_provider = :emb_provider
          AND embedding_model = :emb_model
          AND {$vw['where']}
          AND url <> ''
          AND origin IN ('site', 'exercise')
          AND ( {$w['where']} )
        ORDER BY {$order_origin} title_hit DESC, id DESC
        LIMIT :cap
      ";
    
      $st = $db->prepare($sql);
      $st->bindValue(':emb_provider', $provider, \PDO::PARAM_STR);
        $st->bindValue(':emb_model', $model, \PDO::PARAM_STR);
      foreach ($w['binds'] as $ph => $val) $st->bindValue($ph, $val, \PDO::PARAM_STR);
      foreach ($vw['binds'] as $ph => $val) {
          $st->bindValue($ph, $val, \PDO::PARAM_STR);
        }
      $st->bindValue(':cap', $CAP, \PDO::PARAM_INT);
      $st->execute();
      $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
      $rows = self::filter_document_rows_for_user($rows, $user_id);
    
      $scored = [];
      $seen_scored = [];
      
      foreach ($rows as $r) {
        if (empty($r['embedding'])) continue;
        $vec = json_decode($r['embedding'], true);
        if (!is_array($vec) || !$vec) continue;

        $key = md5(
          (string)($r['url'] ?? '') . '|' .
          (string)($r['title'] ?? '') . '|' .
          mb_substr((string)($r['chunk'] ?? ''), 0, 200)
        );
        
        if (isset($seen_scored[$key])) {
          continue;
        }
        $seen_scored[$key] = true;
    
    $sim = self::cosine($emb, $vec);
    
    if (($r['origin'] ?? '') === 'private') {
      $sim += 0.05;
    }

    $exercise_allowed = true;
    
    if ($exercise_intent) {
      $exercise_allowed = self::exercise_intent_allowed_for_user($query, $user_id);
    }
    
    if ($exercise_intent && $exercise_allowed && ($r['origin'] ?? '') === 'exercise') {
      $sim += 0.80;
    }
    
    $sim += self::lexical_bonus($r, $tokens);
    
    $hit = isset($r['title_hit']) ? (int)$r['title_hit'] : 0;
    if ($hit > 0) {
      $sim += min(0.08, 0.02 * $hit);
    }
    
        $scored[] = [
          'score'         => $sim,
          'title'         => $r['title'],
          'section_title' => $r['section_title'] ?? '',
          'url'           => $r['url'],
          'chunk'         => $r['chunk'],
          'origin'        => $r['origin'],
          'ptype'         => $r['ptype'] ?? null,
        ];
      }
    
    usort($scored, fn($a,$b)=> $a['score'] < $b['score'] ? 1 : -1);
    
    if ($exercise_intent) {
      $user_id = get_current_user_id();
      
        if ($user_id > 0 && self::topic_is_out_of_program_for_user($query, $user_id)) {
          return [];
        }      
    
      $sql_exos = self::match_exercises_direct_by_query($query, $k, $user_id);
    
      if (empty($sql_exos)) {
        $sql_exos = self::match_exercises_by_query($query, $k, $user_id);
      }
    
      if (!empty($sql_exos)) {
        return array_slice($sql_exos, 0, $k);
      }
    
      return array_slice($scored, 0, $k);
    }
    
    return self::rerank_results($query, $scored, $k);

      } catch (\Throwable $e) {
        error_log('[SegFault RAG search_sources] erreur ignorée : ' . $e->getMessage());
        return [];
      }
    }

private static function mysql_table_exists(string $table): bool {
  global $wpdb;

  if ($table === '') return false;

  return $wpdb->get_var($wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $table
  )) === $table;
}

private static function allowed_competency_levels_for_user(int $user_id): array {
  if ($user_id <= 0) return [];

  $level = self::current_student_level($user_id);

  if ($level === '' && function_exists('\\OuInPo\\SegFault\\ouinpo_sf_user_nsi_level')) {
    $level = \OuInPo\SegFault\ouinpo_sf_user_nsi_level($user_id);
  }

  if ($level !== '' && function_exists('\\OuInPo\\SegFault\\ouinpo_sf_allowed_competency_levels')) {
    return \OuInPo\SegFault\ouinpo_sf_allowed_competency_levels($level);
  }

  if ($level === 'terminale') {
    return ['Seconde', 'Première', 'Terminale', 'Transversal'];
  }

  if ($level === 'premiere') {
    return ['Seconde', 'Première', 'Transversal'];
  }

  if ($level === 'seconde') {
    return ['Seconde', 'Transversal'];
  }

  return [];
}

private static function competency_level_rank(string $level): int {
  $level = strtolower(trim(remove_accents($level)));

  if ($level === 'seconde') return 1;
  if ($level === 'premiere') return 2;
  if ($level === 'terminale') return 3;

  return 0;
}

private static function seen_competency_context_for_user(int $user_id): array {
  static $cache = [];

  if ($user_id <= 0) {
    return [
      'has_seen_context' => false,
      'ids' => [],
    ];
  }

  if (isset($cache[$user_id])) {
    return $cache[$user_id];
  }

  global $wpdb;

  $t_members = $wpdb->prefix . 'ouin_exo_group_members';
  $t_groups  = $wpdb->prefix . 'ouin_exo_groups';
  $t_teach   = $wpdb->prefix . 'ouin_exo_competency_teaching';

  if (
    !self::mysql_table_exists($t_members) ||
    !self::mysql_table_exists($t_groups) ||
    !self::mysql_table_exists($t_teach)
  ) {
    $cache[$user_id] = [
      'has_seen_context' => false,
      'ids' => [],
    ];
    return $cache[$user_id];
  }

  $row = $wpdb->get_row($wpdb->prepare("
    SELECT
      gm.group_id,
      g.year_id
    FROM {$t_members} gm
    INNER JOIN {$t_groups} g ON g.id = gm.group_id
    WHERE gm.user_id = %d
    ORDER BY g.year_id DESC, gm.group_id ASC
    LIMIT 1
  ", $user_id), ARRAY_A);

  if (!$row) {
    $cache[$user_id] = [
      'has_seen_context' => false,
      'ids' => [],
    ];
    return $cache[$user_id];
  }

  $group_id = (int)($row['group_id'] ?? 0);
  $year_id  = (int)($row['year_id'] ?? 0);

  if ($group_id <= 0 || $year_id <= 0) {
    $cache[$user_id] = [
      'has_seen_context' => false,
      'ids' => [],
    ];
    return $cache[$user_id];
  }

  $ids = $wpdb->get_col($wpdb->prepare("
    SELECT competency_id
    FROM {$t_teach}
    WHERE year_id = %d
      AND group_id = %d
      AND teaching_state = 'seen'
  ", $year_id, $group_id)) ?: [];

  $ids = array_values(array_unique(array_map('intval', $ids)));

  $cache[$user_id] = [
    'has_seen_context' => !empty($ids),
    'ids' => array_fill_keys($ids, true),
  ];

  return $cache[$user_id];
}

private static function competency_seen_gate_allows(
  string $competency_level,
  int $competency_id,
  string $student_level,
  array $seen_context
): bool {
  $student_rank = self::competency_level_rank($student_level);
  $comp_rank    = self::competency_level_rank($competency_level);

  // Transversal, méthodes, outils généraux.
  if ($comp_rank === 0) return true;

  // Si on n'a pas encore d'état "vu / non vu", on ne bloque pas brutalement.
  if (empty($seen_context['has_seen_context'])) return true;

  // Les niveaux antérieurs sont considérés comme prérequis accessibles.
  if ($student_rank > 0 && $comp_rank < $student_rank) return true;

  // Pour le niveau courant, on privilégie les compétences vues.
  return isset($seen_context['ids'][$competency_id]);
}

private static function document_row_allowed_for_user(array $row, int $user_id): bool {
  if ($user_id <= 0 || !is_user_logged_in()) {
    return true;
  }

  // Prof / admin : pas de bridage pédagogique.
  if (current_user_can('manage_options') || current_user_can('edit_users')) {
    return true;
  }

  $origin = strtolower(trim((string)($row['origin'] ?? '')));
  $url    = trim((string)($row['url'] ?? ''));

  // On ne bride ici que les contenus du site.
  if ($origin !== 'site') {
    return true;
  }

  if ($url === '') {
    return true;
  }

  $post_id = url_to_postid($url);
  if ($post_id <= 0) {
    return true;
  }

  $post_type = get_post_type($post_id);
  if (!in_array($post_type, ['post', 'page'], true)) {
    return true;
  }

  global $wpdb;

  $t_post_comp = $wpdb->prefix . 'ouin_exo_post_competency';
  $t_comp      = $wpdb->prefix . 'ouin_exo_competencies';

  if (
    !self::mysql_table_exists($t_post_comp) ||
    !self::mysql_table_exists($t_comp)
  ) {
    return true;
  }

  $linked = $wpdb->get_results($wpdb->prepare("
    SELECT
      c.id,
      c.level
    FROM {$t_post_comp} pc
    INNER JOIN {$t_comp} c ON c.id = pc.competency_id
    WHERE pc.post_id = %d
      AND c.active = 1
  ", $post_id), ARRAY_A) ?: [];

  // Pages non liées : on laisse passer pour ne pas casser les pages outils/méthodes.
  if (!$linked) {
    return true;
  }

  $allowed_levels = self::allowed_competency_levels_for_user($user_id);
  if (!$allowed_levels) {
    return true;
  }

  $allowed_map  = array_fill_keys($allowed_levels, true);
  $student_level = self::current_student_level($user_id);
  $seen_context = self::seen_competency_context_for_user($user_id);

  foreach ($linked as $r) {
    $cid   = (int)($r['id'] ?? 0);
    $level = (string)($r['level'] ?? '');

    if ($cid <= 0 || $level === '') continue;

    if (!isset($allowed_map[$level])) {
      continue;
    }

    if (self::competency_seen_gate_allows($level, $cid, $student_level, $seen_context)) {
      return true;
    }
  }

  return false;
}

private static function filter_document_rows_for_user(array $rows, int $user_id): array {
  if ($user_id <= 0 || !is_user_logged_in()) {
    return $rows;
  }

  if (!$rows) return [];

  $out = [];

  foreach ($rows as $row) {
    if (self::document_row_allowed_for_user($row, $user_id)) {
      $out[] = $row;
    }
  }

  return $out;
}

public static function source_allowed_for_current_user(array $chunk): bool {
  $user_id = is_user_logged_in() ? get_current_user_id() : 0;

  if ($user_id <= 0) return true;

  return self::document_row_allowed_for_user($chunk, $user_id);
}

public static function current_student_level(int $user_id): string {
  if ($user_id <= 0) {
    return '';
  }

  global $wpdb;

  // 1. Source principale : groupe élève
  $t_members = $wpdb->prefix . 'ouin_exo_group_members';
  $t_groups  = $wpdb->prefix . 'ouin_exo_groups';
  $t_levels  = $wpdb->prefix . 'ouin_exo_school_levels';

  $has_members = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_members)) === $t_members);
  $has_groups  = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_groups)) === $t_groups);
  $has_levels  = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_levels)) === $t_levels);

  if ($has_members && $has_groups && $has_levels) {
    $row = $wpdb->get_row($wpdb->prepare("
      SELECT
        lv.slug AS level_slug,
        lv.label AS level_label,
        g.school_level_id
      FROM {$t_members} m
      INNER JOIN {$t_groups} g ON g.id = m.group_id
      LEFT JOIN {$t_levels} lv ON lv.id = g.school_level_id
      WHERE m.user_id = %d
      ORDER BY m.group_id DESC
      LIMIT 1
    ", $user_id), ARRAY_A);

    if ($row) {
      $slug  = strtolower(trim((string)($row['level_slug'] ?? '')));
      $label = strtolower(trim((string)($row['level_label'] ?? '')));
      $sid   = (int)($row['school_level_id'] ?? 0);

      if (in_array($slug, ['seconde', 'premiere', 'terminale'], true)) {
        return $slug;
      }

      if (str_starts_with($slug, 'sec')) return 'seconde';
      if (str_starts_with($slug, 'prem')) return 'premiere';
      if (str_starts_with($slug, 'term')) return 'terminale';

      if (str_contains($label, 'sec')) return 'seconde';
      if (str_contains($label, 'prem')) return 'premiere';
      if (str_contains($label, 'term')) return 'terminale';

      if ($sid === 1) return 'seconde';
      if ($sid === 2) return 'premiere';
      if ($sid === 3) return 'terminale';
    }
  }

  // 2. Fallback user_meta
  $meta = strtolower(trim((string)get_user_meta($user_id, 'nsi_level', true)));

  if (in_array($meta, ['seconde', 'premiere', 'terminale'], true)) {
    return $meta;
  }

  return '';
}

private static function topic_program_level(string $query): string {
  $q = ' ' . remove_accents(mb_strtolower($query)) . ' ';
  $q = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $q);
  $q = preg_replace('/\s+/u', ' ', $q);

  $terminal_patterns = [
    '/\bpiles?\b/u',
    '/\blifo\b/u',
    '/\bstack\b/u',

    '/\bfiles?\b/u',
    '/\bfifo\b/u',
    '/\bqueue\b/u',

    '/\barbres?\b/u',
    '/\barbres?\s+binaires?\b/u',
    '/\barbres?\s+binaires?\s+de\s+recherche\b/u',
    '/\babr\b/u',
    '/\bbst\b/u',

    '/\bgraphes?\b/u',
    '/\bdijkstra\b/u',
    '/\broutage\b/u',

    '/\brecursivite\b/u',
    '/\brecursif\b/u',
    '/\brecursive\b/u',

    '/\bdiviser\s+pour\s+regner\b/u',
    '/\bprogrammation\s+dynamique\b/u',
  ];

  foreach ($terminal_patterns as $pattern) {
    if (preg_match($pattern, $q)) {
      return 'terminale';
    }
  }

  return '';
}
public static function topic_is_out_of_program_for_user(string $query, int $user_id): bool {
  $user_level = self::current_student_level($user_id);
  $topic_level = self::topic_program_level($query);

  if ($user_level === '' || $topic_level === '') {
    return false;
  }

  if ($user_level === 'premiere' && $topic_level === 'terminale') {
    return true;
  }

  if ($user_level === 'seconde' && in_array($topic_level, ['premiere', 'terminale'], true)) {
    return true;
  }

  return false;
}

public static function student_pedagogical_context(int $user_id): string {
  if ($user_id <= 0 || !is_user_logged_in()) {
    return "Profil pédagogique : utilisateur non connecté. Ne pas supposer de niveau individuel.";
  }

  global $wpdb;

  $lines = [];
  $lines[] = "Profil pédagogique anonymisé de l'élève connecté.";

  // 1. Niveau scolaire
$level = self::current_student_level($user_id);

  if ($level !== '') {
    $label = [
      'seconde' => 'Seconde',
      'premiere' => 'Première NSI',
      'terminale' => 'Terminale NSI',
    ][$level] ?? $level;

    $lines[] = "- Niveau scolaire connu : ".$label.".";
  } else {
    $lines[] = "- Niveau scolaire : inconnu.";
  }

  // 2. Exercices déjà faits / tentés
  $t_status = $wpdb->prefix . 'ouin_exo_user_status';
  $t_exo    = $wpdb->prefix . 'ouin_exo_exercises';

  $has_status_table = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_status)) === $t_status);
  $has_exo_table    = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_exo)) === $t_exo);

  if ($has_status_table && $has_exo_table) {
    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT
        us.exercise_id,
        us.status,
        us.updated_at,
        e.title
      FROM {$t_status} us
      LEFT JOIN {$t_exo} e ON e.id = us.exercise_id
      WHERE us.user_id = %d
        AND us.status IN ('attempted', 'solved')
      ORDER BY us.updated_at DESC
      LIMIT 20
    ", $user_id), ARRAY_A) ?: [];

    $solved = [];
    $attempted = [];

    foreach ($rows as $r) {
      $title = trim((string)($r['title'] ?? ''));
      if ($title === '') {
        $title = 'Exercice #'.(int)($r['exercise_id'] ?? 0);
      }

      if (($r['status'] ?? '') === 'solved') {
        $solved[] = $title;
      } elseif (($r['status'] ?? '') === 'attempted') {
        $attempted[] = $title;
      }
    }

    if ($solved) {
      $lines[] = "- Exercices récemment réussis : ".implode(" ; ", array_slice($solved, 0, 8)).".";
    } else {
      $lines[] = "- Aucun exercice récemment réussi connu.";
    }

    if ($attempted) {
      $lines[] = "- Exercices récemment tentés mais pas forcément réussis : ".implode(" ; ", array_slice($attempted, 0, 8)).".";
    } else {
      $lines[] = "- Aucun exercice récemment tenté connu.";
    }
  } else {
    $lines[] = "- Historique des exercices : non disponible.";
  }

  // 3. Compétences BO individuelles si une table exploitable existe
  $competence_context = self::student_competency_context($user_id, $level);

    if ($competence_context !== '') {
      $lines[] = $competence_context;
      $lines[] = "- Important : si la demande porte sur une compétence acquise, proposer une consolidation ou un défi, pas une reprise débutante.";
    } else {
      $lines[] = "- Niveau individuel par compétence BO : non disponible dans le contexte actuel.";
    }

  // 4. Consignes fortes pour l'IA
  $lines[] = "";
  $lines[] = "Consignes d'adaptation pour la réponse :";
  $lines[] = "- Ne pas mentionner le nom ou le prénom de l'élève.";
  $lines[] = "- Adapter les explications au niveau scolaire indiqué.";
  $lines[] = "- Ne pas proposer en priorité un exercice déjà réussi.";
  $lines[] = "- Si un exercice a déjà été tenté, proposer une aide progressive ou un exercice voisin.";
  $lines[] = "- Si une compétence semble fragile, commencer par une explication courte et un exercice guidé.";
  $lines[] = "- Si une compétence semble acquise, proposer une consolidation ou un défi raisonnable.";
  $lines[] = "- Ne pas utiliser de notion hors programme du niveau indiqué.";

  return implode("\n", $lines);
}

private static function student_competency_context(int $user_id, string $level = ''): string {
  if ($user_id <= 0) return '';

  global $wpdb;

  $t_comp = $wpdb->prefix . 'ouin_exo_competencies';

  if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_comp)) !== $t_comp) {
    return '';
  }

  $items = [];

  /*
   * 1) Tables directes éventuelles de statut par compétence
   */
  $candidate_tables = [
    $wpdb->prefix . 'ouin_exo_user_competencies',
    $wpdb->prefix . 'ouin_exo_competency_status',
    $wpdb->prefix . 'ouin_competency_status',
  ];

  foreach ($candidate_tables as $table) {
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
      continue;
    }

    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0) ?: [];

    if (
      !in_array('user_id', $cols, true) ||
      !in_array('competency_id', $cols, true) ||
      !in_array('status', $cols, true)
    ) {
      continue;
    }

    $where_level = '';
    $args = [$user_id];

    if ($level !== '' && in_array($level, ['seconde', 'premiere', 'terminale'], true)) {
      $where_level = "AND c.level = %s";
      $args[] = $level;
    }

    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT
        s.status AS status,
        c.domain,
        c.domain_slug,
        c.competency
      FROM {$table} s
      INNER JOIN {$t_comp} c ON c.id = s.competency_id
      WHERE s.user_id = %d
        {$where_level}
        AND c.active = 1
      ORDER BY
        CASE s.status
          WHEN 'not_acquired' THEN 0
          WHEN 'in_progress' THEN 1
          WHEN 'consolidating' THEN 2
          WHEN 'acquired' THEN 3
          ELSE 9
        END ASC,
        c.domain ASC,
        c.id ASC
      LIMIT 40
    ", ...$args), ARRAY_A) ?: [];

    foreach ($rows as $r) {
      $items[] = $r;
    }
  }

  /*
   * 2) Résultats issus des devoirs surveillés / évaluations
   */
  $t_results = $wpdb->prefix . 'ouin_exo_assessment_results';

  if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_results)) === $t_results) {
    $cols = $wpdb->get_col("SHOW COLUMNS FROM {$t_results}", 0) ?: [];

    $has_user = in_array('user_id', $cols, true);
    $has_comp = in_array('competency_id', $cols, true);
    $has_status = in_array('observed_status', $cols, true) || in_array('status', $cols, true);

    if ($has_user && $has_comp && $has_status) {
      $status_col = in_array('observed_status', $cols, true) ? 'observed_status' : 'status';

      $where_level = '';
      $args = [$user_id];

      if ($level !== '' && in_array($level, ['seconde', 'premiere', 'terminale'], true)) {
        $where_level = "AND c.level = %s";
        $args[] = $level;
      }

      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT
          r.{$status_col} AS status,
          c.domain,
          c.domain_slug,
          c.competency
        FROM {$t_results} r
        INNER JOIN {$t_comp} c ON c.id = r.competency_id
        WHERE r.user_id = %d
          {$where_level}
          AND c.active = 1
        ORDER BY
          CASE r.{$status_col}
            WHEN 'not_acquired' THEN 0
            WHEN 'in_progress' THEN 1
            WHEN 'consolidating' THEN 2
            WHEN 'acquired' THEN 3
            ELSE 9
          END ASC,
          r.updated_at DESC,
          r.assessment_id DESC,
          r.competency_id DESC
        LIMIT 40
      ", ...$args), ARRAY_A) ?: [];

      foreach ($rows as $r) {
        $items[] = $r;
      }
    }
  }

  if (!$items) {
    return '';
  }

  /*
   * 3) Déduplication : une compétence ne doit apparaître qu'une fois.
   * On garde le statut le plus récent / le premier trouvé dans l’ordre récupéré.
   */
  $seen = [];
  $fragile = [];
  $progress = [];
  $solid = [];

  foreach ($items as $r) {
    $status = (string)($r['status'] ?? '');
    $domain = trim((string)($r['domain'] ?? ''));
    $domain_slug = trim((string)($r['domain_slug'] ?? ''));
    $comp = trim((string)($r['competency'] ?? ''));

    $label = trim(($domain !== '' ? $domain.' — ' : '').$comp);
    if ($label === '') continue;

    $key = mb_strtolower(remove_accents($label));
    if (isset($seen[$key])) continue;
    $seen[$key] = true;

    if ($status === 'not_acquired') {
      $fragile[] = $label;
    } elseif ($status === 'in_progress' || $status === 'consolidating') {
      $progress[] = $label;
    } elseif ($status === 'acquired') {
      $solid[] = $label;
    }
  }

  $out = [];

  if ($fragile) {
    $out[] = "- Compétences BO fragiles : ".implode(" ; ", array_slice($fragile, 0, 8)).".";
  }

  if ($progress) {
    $out[] = "- Compétences BO en cours d'acquisition ou à consolider : ".implode(" ; ", array_slice($progress, 0, 8)).".";
  }

  if ($solid) {
    $out[] = "- Compétences BO acquises ou solides : ".implode(" ; ", array_slice($solid, 0, 8)).".";
  }

  return implode("\n", $out);
}
public static function format_context(array $chunks): string {
  $out = [];

  foreach ($chunks as $i => $c) {
    $title = trim((string)($c['title'] ?? ''));
    $section = trim((string)($c['section_title'] ?? ''));

    $head = "[$i] " . ($title !== '' ? $title : 'Document');

    if ($section !== '' && $section !== $title) {
      $head .= " — section : " . $section;
    }

    if (!empty($c['url'])) {
      $head .= " — " . $c['url'];
    }

    $out[] = $head . "\n" . self::trim((string)($c['chunk'] ?? ''), 900);
  }

  return implode("\n\n", $out);
}

  // --- Utils ---------------------------------------------------------------

private static function looks_like_section_title(string $line): bool {
  $line = trim($line);
  if ($line === '') return false;

  $len = mb_strlen($line);
  if ($len > 140) return false;

  // Titres numérotés : 1. ..., 2.3 ..., etc.
  if (preg_match('/^\d+(\.\d+)*[\). -]+/u', $line)) return true;

  // Titres fréquents des cours OuInPo
  if (preg_match('/^(Objectifs|Ce qu’il faut retenir|Ce qu\'il faut retenir|Ce qu’il faut savoir faire|Ce qu\'il faut savoir faire|Pour aller plus loin|Vérifie ta compréhension)/iu', $line)) {
    return true;
  }

  // Titres avec emoji pédagogique
  if (preg_match('/^(🎯|📜|🛠️|🧭|🧠|🐍|📌|⚙️|🧪|📚)/u', $line)) return true;

  // Titres HTML convertis fréquents : h1/h2/h3 deviennent souvent des lignes propres,
  // mais on évite de considérer n’importe quelle petite phrase comme un titre.
  if (preg_match('/^(Introduction|Conclusion|Exemple|Exemples|Activité|Activités|Définition|Méthode|Bilan|Trace écrite)$/iu', $line)) {
    return true;
  }

  return false;
}
private static function split_long_section(string $text, string $section_title, int $chars): array {
  $text = trim($text);
  if ($text === '') return [];

  if (mb_strlen($text) <= $chars) {
    return [[
      'text' => $text,
      'section_title' => $section_title,
    ]];
  }

  $out = [];
  $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
  $current = '';

  foreach ($paragraphs as $p) {
    $p = trim($p);
    if ($p === '') continue;

    if ($current !== '' && mb_strlen($current . "\n\n" . $p) > $chars) {
      $out[] = [
        'text' => trim($current),
        'section_title' => $section_title,
      ];
      $current = $p;
    } else {
      $current = $current === '' ? $p : ($current . "\n\n" . $p);
    }
  }

  if (trim($current) !== '') {
    $out[] = [
      'text' => trim($current),
      'section_title' => $section_title,
    ];
  }

  // Filet de sécurité si un paragraphe unique est énorme.
  $final = [];
  foreach ($out as $chunk) {
    $txt = (string)$chunk['text'];
    if (mb_strlen($txt) <= $chars) {
      $final[] = $chunk;
      continue;
    }

    $len = mb_strlen($txt);
    for ($i = 0; $i < $len; $i += $chars) {
      $final[] = [
        'text' => mb_substr($txt, $i, $chars),
        'section_title' => $section_title,
      ];
    }
  }

  return $final;
}

public static function chunk_with_sections(string $text, int $chars): array {
  $text = str_replace(["\r\n", "\r"], "\n", trim($text));
  $text = preg_replace("/[ \t]+/", " ", $text);
  $text = preg_replace("/\n{3,}/", "\n\n", $text);

  if ($text === '') return [];

  $lines = preg_split("/\n/u", $text) ?: [];

  $sections = [];
  $current_title = '';
  $current_body = [];

  foreach ($lines as $line) {
    $line = trim($line);

    if ($line === '') {
      if (!empty($current_body)) $current_body[] = '';
      continue;
    }

    if (self::looks_like_section_title($line)) {
      if (!empty($current_body)) {
        $sections[] = [
          'section_title' => $current_title,
          'text' => trim(implode("\n", $current_body)),
        ];
      }

      $current_title = $line;
      $current_body = [$line];
      continue;
    }

    $current_body[] = $line;
  }

  if (!empty($current_body)) {
    $sections[] = [
      'section_title' => $current_title,
      'text' => trim(implode("\n", $current_body)),
    ];
  }

  if (!$sections) {
    return self::split_long_section($text, '', $chars);
  }

  $chunks = [];
  foreach ($sections as $s) {
    $section_title = trim((string)($s['section_title'] ?? ''));
    $section_text = trim((string)($s['text'] ?? ''));

    if ($section_text === '') continue;

    foreach (self::split_long_section($section_text, $section_title, $chars) as $chunk) {
      $chunks[] = $chunk;
    }
  }

  return $chunks;
}

  public static function chunk(string $t, int $chars): array {
    $t = trim($t);
    $parts = [];
    $len = mb_strlen($t);
    for ($i=0; $i<$len; $i+=$chars) $parts[] = mb_substr($t, $i, $chars);
    return $parts;
  }

  public static function estimate_tokens(string $t): int {
    return max(1, (int)(mb_strlen($t)/4));
  }

  public static function cosine(array $a, array $b): float {
    $dot=0;$na=0;$nb=0;$n=min(count($a),count($b));
    for ($i=0;$i<$n;$i++){ $dot+=$a[$i]*$b[$i]; $na+=$a[$i]*$a[$i]; $nb+=$b[$i]*$b[$i]; }
    if ($na==0 || $nb==0) return 0.0;
    return $dot / (sqrt($na)*sqrt($nb));
  }

  public static function trim(string $t, int $max): string {
    return mb_strlen($t) <= $max ? $t : mb_substr($t,0,$max).'…';
  }

  // --- Extraction PDF ------------------------------------------------------

  public static function pdf_to_text(string $file): string {
    @exec('command -v pdftotext', $o, $rc);
    if ($rc === 0) {
      $cmd = 'pdftotext -enc UTF-8 -layout ' . escapeshellarg($file) . ' -';
      $out = @shell_exec($cmd);
      if (is_string($out) && trim($out) !== '') return $out;
    }

    if (class_exists('\\Smalot\\PdfParser\\Parser')) {
      try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($file);
        $text = $pdf->getText();
        if (is_string($text) && trim($text) !== '') return $text;
      } catch (\Throwable $e) {}
    }

    $raw = @file_get_contents($file);
    if ($raw === false) return '';
    $raw = preg_replace('/\s+/', ' ', $raw);
    if (preg_match_all('/\((.*?)\)/u', $raw, $m)) {
      return implode("\n", array_map('trim', $m[1]));
    }
    return '';
  }

  public static function stopwords(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = array_flip([
      'le','la','les','un','une','des','du','de','d','au','aux','et','ou','mais','donc','or','ni','car',
      'je','tu','il','elle','on','nous','vous','ils','elles','me','te','se','moi','toi','leur',
      'ce','cet','cette','ces','mon','ton','son','notre','votre','leur','mes','tes','ses','nos','vos','leurs',
      'qui','que','quoi','dont','où','quand','comment','pourquoi','avec','sans','sur','sous','dans','entre','par','vers',
      'est','suis','es','êtes','sommes','sont','été','etre','être','fait','fais','faisons','faites',
      'devoir','peux','peut','peuvent','pour','plus','moins','très','tres','tout','toute','tous',
      'the','a','an','and','or','but','so','of','to','in','on','for','by','with','without','from','is','are','be','been','being',
      'give','make','do','get','have','has','had',
      'exercice','exercices','exos','donne','propose','liste','fais','faire','quelques','idées','idee','idée',
      'cours','leçon','lecon','chapitre','fiche','tutoriel','tuto','tutorial','lesson',
      'resume','résumé','synthese','synthèse',
      'expliquer','explique','explication','comprendre','comprends','comprend','definition','définition',
      'comment','pourquoi','quoi','quel','quelle','quels','quelles',
      'stp','svp','merci'
    ]);
    return $s;
  }

  public static function infer_topic_from_turns(array $turns, int $max_keywords=5): string {
    $bag = [];
    $stop = self::stopwords();
    foreach (array_reverse($turns) as $t) {
      if (($t['role'] ?? '') !== 'user') continue;
      $txt = mb_strtolower($t['content'] ?? '');
      $txt = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $txt);
      foreach (preg_split('/\s+/', $txt) as $w) {
        $w = trim($w);
        if ($w === '' || mb_strlen($w) < 3) continue;
        if (isset($stop[$w])) continue;
        $bag[$w] = ($bag[$w] ?? 0) + 1;
      }
    }
    if (!$bag) return '';
    arsort($bag);
    $top = array_slice(array_keys($bag), 0, $max_keywords);
    return implode(' ', $top);
  }

  public static function expand_query_with_topic(string $query, string $topic): string {
    $q = mb_strtolower(trim($query));
    $generic = (mb_strlen($q) <= 18) || preg_match('/\b(exo|exos|exercices?|liste|propose|id[ée]es?)\b/u', $q);
    if ($generic && $topic !== '') return trim($topic.' '.$query);
    return $query;
  }

  /* ========= Profil élève & exercices (BDD) ========= */
  /* (inchangé : ton bloc est long. Il reste en place dans ton fichier original.) */
}