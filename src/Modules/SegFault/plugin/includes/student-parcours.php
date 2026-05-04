<?php
// ============================================================
// Shortcode élève : [segfault_mes_parcours]
// Affichage "style screen-exercices" (skin OuInPo via segfault.css)
// + Génération de parcours par Domaine + Comp (option)
// ============================================================

if (!defined('ABSPATH')) exit;

if (!function_exists('ouinpo_sf_enqueue_student_parcours_script')) {
  function ouinpo_sf_enqueue_student_parcours_script(array $config) {
    $rel = 'assets/js/front/segfault-student-parcours.js';
    $fallback_root = dirname(__DIR__, 5);

    $base_url = defined('OUINPO_SUITE_URL')
      ? OUINPO_SUITE_URL
      : plugin_dir_url($fallback_root . '/ouinpo-suite.php');

    $base_dir = defined('OUINPO_SUITE_DIR')
      ? OUINPO_SUITE_DIR
      : trailingslashit($fallback_root);

    $base_url = trailingslashit($base_url);
    $base_dir = trailingslashit($base_dir);

    $file = $base_dir . $rel;
    $version = defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0';

    if (file_exists($file)) {
      $version = (string) filemtime($file);
    }

    wp_enqueue_script(
      'ouinpo-sf-student-parcours-js',
      $base_url . $rel,
      [],
      $version,
      true
    );

    wp_add_inline_script(
      'ouinpo-sf-student-parcours-js',
      'window.OuinpoSfStudentParcours = ' . wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';',
      'before'
    );
  }
}

/**
 * AJAX élève : génère un parcours à partir d’un domaine (+ comp optionnelle)
 * Action : ouinpo_sf_student_generate_path
 *
 * IMPORTANT : les fonctions de sélection sont dans le namespace OuInPo\SegFault
 * -> on les appelle avec \OuInPo\SegFault\...
 */
add_action('wp_ajax_ouinpo_sf_student_generate_path', function () {
  if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'not_logged_in'], 401);
  }

  $nonce = isset($_POST['nonce']) ? sanitize_text_field((string)$_POST['nonce']) : '';
  if (!wp_verify_nonce($nonce, 'ouinpo_sf_student_generate_path')) {
    wp_send_json_error(['message' => 'bad_nonce'], 400);
  }

  $student_id = (int)get_current_user_id();

  $domain_value = isset($_POST['domain_value']) ? sanitize_text_field((string)$_POST['domain_value']) : '';
  $domain_value = trim($domain_value);

  $competency_id = isset($_POST['competency_id']) ? (int)$_POST['competency_id'] : 0;
  if ($competency_id <= 0) $competency_id = null;

  $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 7;
  $limit = max(1, min(25, $limit));

  if ($domain_value === '') {
    wp_send_json_error(['message' => 'missing_domain'], 400);
  }

  // ✅ Niveau via groupe (source de vérité) + fallback
  $lvl = '';
  if (function_exists('\\OuInPo\\SegFault\\ouinpo_sf_student_level_from_group')) {
    $lvl = \OuInPo\SegFault\ouinpo_sf_student_level_from_group($student_id);
  }
  if ($lvl === '') {
    if (function_exists('\\ouinpo_sf_user_nsi_level')) {
      $lvl = \ouinpo_sf_user_nsi_level($student_id, null);
    }
  }

    if (!in_array($lvl, ['seconde','premiere','terminale'], true)) $lvl = 'premiere';

  if (!function_exists('\\OuInPo\\SegFault\\ouinpo_sf_find_exercise_ids_by_domain_and_comp')) {
    wp_send_json_error([
      'message' => 'missing_selector',
      'hint'    => 'ouinpo_sf_find_exercise_ids_by_domain_and_comp non chargée'
    ], 500);
  }

  $ids = \OuInPo\SegFault\ouinpo_sf_find_exercise_ids_by_domain_and_comp(
    $domain_value,
    $competency_id,
    $lvl,
    $student_id,
    $limit,
    true
  );

  if (empty($ids)) {
    wp_send_json_success(['ok' => false, 'message' => 'no_exercises', 'level' => $lvl]);
  }

  if (!class_exists('\Ouinpo\Exercises\PathsService')) {
    wp_send_json_error(['message' => 'missing_paths_service'], 500);
  }

  $title = $competency_id
    ? ('Parcours — '.$domain_value.' (compétence)')
    : ('Parcours — '.$domain_value);

  $result = \Ouinpo\Exercises\PathsService::save_path([
    'title'        => $title,
    'student_note' => 'Parcours généré automatiquement à partir de tes besoins du moment. Tu peux le supprimer plus tard car il a été créé depuis ton espace élève.',
    'mode'         => 'free',
    'is_active'    => 1,
    'is_template'  => 0,
    'exercise_ids' => $ids,
    'group_ids'    => [],
    'user_ids'     => [$student_id],
  ]);

  if (is_wp_error($result)) {
    wp_send_json_error([
      'message' => 'path_create_failed',
      'detail'  => $result->get_error_message(),
    ], 500);
  }

  $path_id = (int) $result;

  wp_send_json_success([
    'ok'      => true,
    'path_id' => $path_id,
    'count'   => count($ids),
    'level'   => $lvl,
  ]);
});


add_action('wp_ajax_ouinpo_sf_student_use_template', function () {
  if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'not_logged_in'], 401);
  }

  $nonce = isset($_POST['nonce']) ? sanitize_text_field((string)$_POST['nonce']) : '';
  if (!wp_verify_nonce($nonce, 'ouinpo_sf_student_use_template')) {
    wp_send_json_error(['message' => 'bad_nonce'], 400);
  }

  if (!class_exists('\Ouinpo\Exercises\PathsService')) {
    wp_send_json_error(['message' => 'missing_paths_service'], 500);
  }

  $student_id  = (int) get_current_user_id();
  $template_id = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
  if ($template_id <= 0) {
    wp_send_json_error(['message' => 'missing_template_id'], 400);
  }

  $result = \Ouinpo\Exercises\PathsService::instantiate_template($template_id, [$student_id], [], '');
  if (is_wp_error($result)) {
    wp_send_json_error([
      'message' => 'instantiate_failed',
      'detail'  => $result->get_error_message(),
    ], 500);
  }

  wp_send_json_success([
    'ok'      => true,
    'path_id' => (int) $result,
  ]);
});

add_action('wp_ajax_ouinpo_sf_student_delete_path', function () {
  if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'not_logged_in'], 401);
  }

  $nonce = isset($_POST['nonce']) ? sanitize_text_field((string)$_POST['nonce']) : '';
  if (!wp_verify_nonce($nonce, 'ouinpo_sf_student_delete_path')) {
    wp_send_json_error(['message' => 'bad_nonce'], 400);
  }

  if (!class_exists('\Ouinpo\Exercises\PathsService')) {
    wp_send_json_error(['message' => 'missing_paths_service'], 500);
  }

  $student_id = (int) get_current_user_id();
  $path_id    = isset($_POST['path_id']) ? (int) $_POST['path_id'] : 0;
  if ($path_id <= 0) {
    wp_send_json_error(['message' => 'missing_path_id'], 400);
  }

  $deleted = \Ouinpo\Exercises\PathsService::delete_user_self_path($path_id, $student_id);
  if (!$deleted) {
    wp_send_json_error(['message' => 'forbidden_delete'], 403);
  }

  wp_send_json_success(['ok' => true]);
});

add_shortcode('segfault_mes_parcours', function ($atts) {

  $members_only = (int)get_option('ouinpo_sf_members_only', 0);
  if ($members_only && !is_user_logged_in()) return '';
  if (!is_user_logged_in()) return '<p>Connecte-toi pour voir tes parcours.</p>';

  $user_id = (int)get_current_user_id();
  global $wpdb;

  $t_paths   = $wpdb->prefix.'ouin_sf_paths';
  $t_items   = $wpdb->prefix.'ouin_sf_path_items';
  $t_exo     = $wpdb->prefix.'ouin_exo_exercises';
  $t_status  = $wpdb->prefix.'ouin_exo_user_status';
  $t_comp    = $wpdb->prefix.'ouin_exo_competencies';
  $t_targets = $wpdb->prefix.'ouin_sf_path_targets';
  $t_gm      = $wpdb->prefix.'ouin_exo_group_members';

  $view_id = isset($_GET['sf_path']) ? (int)$_GET['sf_path'] : 0;

  $badge = function (string $st): array {
    if ($st === 'solved')    return ['✅', 'Réussi',   'sf-pill ok'];
    if ($st === 'attempted') return ['🟡', 'Tenté',    'sf-pill warn'];
    return ['⚪', 'À faire',  'sf-pill none'];
  };

  // ------------------------------------------------------------
  // Données "générateur" : niveau élève -> domaines & compétences
  // ------------------------------------------------------------
  $student_level = '';
  if (function_exists('\\OuInPo\\SegFault\\ouinpo_sf_student_level_from_group')) {
    $student_level = \OuInPo\SegFault\ouinpo_sf_student_level_from_group($user_id);
  }
  if ($student_level === '') {
    $student_level = function_exists('\\ouinpo_sf_user_nsi_level')
      ? \ouinpo_sf_user_nsi_level($user_id, null)
      : 'premiere';
  }

    if (!in_array($student_level, ['seconde','premiere','terminale'], true)) $student_level = 'premiere';

    $allowed_comp_levels = function_exists('\\OuInPo\\SegFault\\ouinpo_sf_allowed_competency_levels')
      ? \OuInPo\SegFault\ouinpo_sf_allowed_competency_levels($student_level)
      : ['Première'];
    
    $level_placeholders = implode(',', array_fill(0, count($allowed_comp_levels), '%s'));
    
    $domains = $wpdb->get_results($wpdb->prepare("
      SELECT DISTINCT track, domain, domain_slug
      FROM {$t_comp}
      WHERE active = 1
        AND level IN ({$level_placeholders})
      ORDER BY track ASC, domain ASC
    ", ...$allowed_comp_levels), ARRAY_A) ?: [];
    
    $competencies = $wpdb->get_results($wpdb->prepare("
      SELECT id, track, level, domain, domain_slug, competency
      FROM {$t_comp}
      WHERE active = 1
        AND level IN ({$level_placeholders})
      ORDER BY track ASC, domain ASC, id ASC
    ", ...$allowed_comp_levels), ARRAY_A) ?: [];

  $ajax_url     = admin_url('admin-ajax.php');
  $nonce_gen    = wp_create_nonce('ouinpo_sf_student_generate_path');
  $nonce_tpl    = wp_create_nonce('ouinpo_sf_student_use_template');
  $nonce_delete = wp_create_nonce('ouinpo_sf_student_delete_path');
  
  $prefill_domain = isset($_GET['sf_domain']) ? sanitize_text_field((string) $_GET['sf_domain']) : '';
  $prefill_comp_id = isset($_GET['sf_comp_id']) ? (int) $_GET['sf_comp_id'] : 0;
  
  $template_domain_options = class_exists('\Ouinpo\Exercises\PathsService')
    ? \Ouinpo\Exercises\PathsService::get_template_domain_options()
    : [];
  $template_goal_options = class_exists('\Ouinpo\Exercises\PathsService')
    ? \Ouinpo\Exercises\PathsService::get_template_goal_options()
    : [];
    $templates = class_exists('\Ouinpo\Exercises\PathsService')
      ? \Ouinpo\Exercises\PathsService::list_active_templates([
          'level_slug' => $student_level,
        ])
      : [];

  $template_domain_values = [];
  $template_goal_values = [];
  foreach ($templates as $tpl) {
    $d = sanitize_key((string) ($tpl['domain_slug'] ?? ''));
    $g = sanitize_key((string) ($tpl['goal_slug'] ?? ''));
    if ($d !== '' && !isset($template_domain_values[$d]) && isset($template_domain_options[$d])) {
      $template_domain_values[$d] = $template_domain_options[$d];
    }
    if ($g !== '' && !isset($template_goal_values[$g]) && isset($template_goal_options[$g])) {
      $template_goal_values[$g] = $template_goal_options[$g];
    }
  }

  // ------------------------------------------------------------
  // ------------------------------------------------------------
  // Vue détail
  // ------------------------------------------------------------
  if ($view_id > 0) {

    // ✅ Nouveau modèle : accès si ciblé directement ou via une classe
    // + compat legacy via p.student_id
    $path = $wpdb->get_row($wpdb->prepare("
      SELECT DISTINCT p.*
      FROM {$t_paths} p
      LEFT JOIN {$t_targets} t
        ON t.path_id = p.id
      LEFT JOIN {$t_gm} gm
        ON t.target_type = 'group'
       AND t.target_id = gm.group_id
      WHERE p.id = %d
        AND p.is_active = 1
        AND (
             (t.target_type = 'user'  AND t.target_id = %d)
          OR (t.target_type = 'group' AND gm.user_id = %d)
          OR (p.student_id = %d)
        )
      LIMIT 1
    ", $view_id, $user_id, $user_id, $user_id), ARRAY_A);

    if (!$path) {
      return '<div class="ouinpo-sf-card"><p>Parcours introuvable.</p></div>';
    }

    $path_mode = in_array((string)($path['mode'] ?? 'free'), ['free', 'sequential'], true)
      ? (string)$path['mode']
      : 'free';

    $items = $wpdb->get_results($wpdb->prepare("
      SELECT i.position, i.exercise_id, i.note, e.title AS exo_title
      FROM {$t_items} i
      LEFT JOIN {$t_exo} e ON e.id = i.exercise_id
      WHERE i.path_id = %d
      ORDER BY i.position ASC, i.id ASC
    ", $view_id), ARRAY_A) ?: [];

    $ids = [];
    foreach ($items as $r) {
      $eid = (int)($r['exercise_id'] ?? 0);
      if ($eid > 0) $ids[] = $eid;
    }
    $ids = array_values(array_unique($ids));

    $status_map = class_exists('\\Ouinpo\\Exercises\\PathsService')
      ? \Ouinpo\Exercises\PathsService::get_user_status_map_for_path($view_id, $user_id)
      : [];

    if (empty($status_map) && !empty($ids)) {
      $in = implode(',', array_fill(0, count($ids), '%d'));
      $params = array_merge([$user_id], $ids);

      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT exercise_id, status
        FROM {$t_status}
        WHERE user_id = %d AND exercise_id IN ($in)
      ", ...$params), ARRAY_A) ?: [];

      foreach ($rows as $r) {
        $status_map[(int)$r['exercise_id']] = (string)($r['status'] ?? 'none');
      }
    }

    $unlocked_ids = ($path_mode === 'sequential' && class_exists('\\Ouinpo\\Exercises\\PathsService'))
      ? \Ouinpo\Exercises\PathsService::get_unlocked_exercise_ids_for_user($view_id, $user_id)
      : $ids;

    $unlocked_lookup = array_fill_keys(array_map('intval', $unlocked_ids), true);

    $total = count($items);
    $solved = 0;
    $attempted = 0;
    foreach ($items as $it) {
      $eid = (int)($it['exercise_id'] ?? 0);
      $st = $status_map[$eid] ?? 'none';
      if ($st === 'solved') $solved++;
      elseif ($st === 'attempted') $attempted++;
    }
    $pct = ($total > 0) ? (int)round(100 * $solved / $total) : 0;

    $back_url = esc_url(remove_query_arg('sf_path'));
    $can_delete_current = class_exists('\Ouinpo\Exercises\PathsService')
      ? \Ouinpo\Exercises\PathsService::can_user_self_remove_path($view_id, $user_id)
      : false;

    $exercise_page = get_page_by_path('exercice');
    $exercise_base_url = ($exercise_page instanceof \WP_Post)
    ? get_permalink($exercise_page)
    : home_url('/exercice/');  

    $html = '';
    $html .= '<div class="sf-back">';
    $html .= '<a class="ouinpo-sf-btn" href="'.$back_url.'">← Retour à mes parcours</a>';
    if ($can_delete_current) {
      $html .= ' <a href="#" class="ouinpo-sf-btn sf-delete-path" data-path-id="'.(int)$view_id.'">Supprimer ce parcours</a>';
    }
    $html .= '</div>';

    $html .= '<div class="ouinpo-sf-card">';
    $html .= '<h2>'.esc_html((string)($path['title'] ?? 'Parcours')).'</h2>';
    if (!empty($path['student_note'])) {
      $html .= '<div class="ouinpo-sf-muted">'.wp_kses_post(wpautop((string)$path['student_note'])).'</div>';
    }

    if ($path_mode === 'sequential') {
      $html .= '<p class="ouinpo-sf-muted"><strong>Mode séquentiel</strong> : réussis chaque exercice pour débloquer le suivant.</p>';
    } else {
      $html .= '<p class="ouinpo-sf-muted"><strong>Mode libre</strong> : tous les exercices sont accessibles.</p>';
    }

    $html .= '<p class="ouinpo-sf-muted">';
    $html .= '<span class="sf-progressbar" style="--progress: '.(int)$pct.'%;"><span></span></span>';
    $html .= '<strong>'.(int)$pct.'%</strong> ('.(int)$solved.'/'.(int)$total.' réussis, '.(int)$attempted.' tentés)';
    $html .= '</p>';

    $html .= '<table class="ouinpo-sf-table">';
    $html .= '<thead><tr>
      <th class="ouinpo-sf-col-narrow">Ordre</th>
      <th>Exercice</th>
      <th class="ouinpo-sf-col-status">Statut</th>
      <th class="ouinpo-sf-col-action">Lien</th>
    </tr></thead><tbody>';

    if (empty($items)) {
      $html .= '<tr><td colspan="4">Aucun exercice dans ce parcours.</td></tr>';
    } else {
      foreach ($items as $it) {
        $eid = (int)($it['exercise_id'] ?? 0);
        if ($eid <= 0) continue;

        $title = trim((string)($it['exo_title'] ?? ''));
        if ($title === '') $title = 'Exercice '.$eid;

        $st = $status_map[$eid] ?? 'none';
        $is_locked = ($path_mode === 'sequential' && empty($unlocked_lookup[$eid]));

        if ($is_locked) {
          $ico = '🔒';
          $lab = 'Verrouillé';
          $cls = 'sf-pill none';
          $action_html = '<span class="ouinpo-sf-muted">Termine le précédent</span>';
        } else {
          [$ico, $lab, $cls] = $badge($st);
          $url = esc_url(add_query_arg([
            'exo'     => $eid,
            'sf_path' => $view_id,
          ], $exercise_base_url));
          $action_html = '<a class="ouinpo-sf-btn" href="'.$url.'" target="_blank" rel="noopener noreferrer">Ouvrir</a>';
        }

        $html .= '<tr>';
        $html .= '<td>'.(int)($it['position'] ?? 0).'</td>';
        $html .= '<td>'.esc_html($title).'</td>';
        $html .= '<td><span class="'.esc_attr($cls).'">'.esc_html($ico.' '.$lab).'</span></td>';
        $html .= '<td>'.$action_html.'</td>';
        $html .= '</tr>';
      }
    }

    $html .= '</tbody></table>';
    $html .= '</div>';

    return $html;
  }

  // ------------------------------------------------------------
  // Vue liste
  // ------------------------------------------------------------
  // ✅ Nouveau modèle : parcours affectés directement ou via groupe
  // + compat legacy via p.student_id
  $paths = $wpdb->get_results($wpdb->prepare("
    SELECT DISTINCT p.*
    FROM {$t_paths} p
    LEFT JOIN {$t_targets} t
      ON t.path_id = p.id
    LEFT JOIN {$t_gm} gm
      ON t.target_type = 'group'
     AND t.target_id = gm.group_id
    WHERE p.is_active = 1
      AND (
           (t.target_type = 'user'  AND t.target_id = %d)
        OR (t.target_type = 'group' AND gm.user_id = %d)
        OR (p.student_id = %d)
      )
    ORDER BY p.updated_at DESC, p.id DESC
  ", $user_id, $user_id, $user_id), ARRAY_A) ?: [];

  $items_by_path = [];
  if (!empty($paths)) {
    $path_ids = array_values(array_unique(array_map(fn($p) => (int)$p['id'], $paths)));
    $path_ids = array_values(array_filter($path_ids));

    if (!empty($path_ids)) {
      $in = implode(',', array_fill(0, count($path_ids), '%d'));
      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT path_id, exercise_id
        FROM {$t_items}
        WHERE path_id IN ($in)
      ", ...$path_ids), ARRAY_A) ?: [];

      foreach ($rows as $r) {
        $pid = (int)($r['path_id'] ?? 0);
        $eid = (int)($r['exercise_id'] ?? 0);
        if ($pid <= 0 || $eid <= 0) continue;
        $items_by_path[$pid] ??= [];
        $items_by_path[$pid][] = $eid;
      }
    }
  }

  $all_exo_ids = [];
  foreach ($items_by_path as $arr) foreach ($arr as $eid) $all_exo_ids[] = (int)$eid;
  $all_exo_ids = array_values(array_unique(array_filter($all_exo_ids)));

  $status_map = [];
  if (!empty($all_exo_ids)) {
    $in = implode(',', array_fill(0, count($all_exo_ids), '%d'));
    $params = array_merge([$user_id], $all_exo_ids);

    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT exercise_id, status
      FROM {$t_status}
      WHERE user_id = %d AND exercise_id IN ($in)
    ", ...$params), ARRAY_A) ?: [];

    foreach ($rows as $r) {
      $status_map[(int)$r['exercise_id']] = (string)($r['status'] ?? 'none');
    }
  }

  // ------------------------------------------------------------
  // Rendu page (générateur + table parcours)
  // ------------------------------------------------------------
  $html = '';

  $html .= '<div class="ouinpo-sf-card">';
  $html .= '<h2>Générer un parcours</h2>';
  $html .= '<p class="ouinpo-sf-muted">Niveau détecté : <strong>'.esc_html($student_level === 'terminale' ? 'Terminale' : 'Première').'</strong>. Choisis un domaine et, si tu veux, une compétence précise.</p>';

  $html .= '<div class="ouinpo-sf-gen">';

  $html .= '<div class="ouinpo-sf-field">';
  $html .= '<label for="sf-gen-domain">Domaine</label>';
  $html .= '<select id="sf-gen-domain" class="ouinpo-sf-select">';
  $html .= '<option value="">— Choisir —</option>';

  $cur = '';
  foreach ($domains as $d) {
    $track = trim((string)($d['track'] ?? ''));
    if ($track !== $cur) {
      if ($cur !== '') $html .= '</optgroup>';
      $cur = $track;
      $html .= '<optgroup label="'.esc_attr($cur ?: 'Domaine').'">';
    }
    $val = trim((string)($d['domain_slug'] ?? ''));
    if ($val === '') $val = trim((string)($d['domain'] ?? ''));
    $lab = trim((string)($d['domain'] ?? $val));
    if ($val === '') continue;
    $html .= '<option value="'.esc_attr($val).'">'.esc_html($lab).'</option>';
  }
  if ($cur !== '') $html .= '</optgroup>';
  $html .= '</select>';
  $html .= '</div>';

  $html .= '<div class="ouinpo-sf-field">';
  $html .= '<label for="sf-gen-comp">Compétence (option)</label>';
  $html .= '<select id="sf-gen-comp" class="ouinpo-sf-select" disabled>';
  $html .= '<option value="0">— Toutes —</option>';
  $html .= '</select>';
  $html .= '</div>';

  $html .= '<div class="ouinpo-sf-field ouinpo-sf-field-small">';
  $html .= '<label for="sf-gen-limit">Nb</label>';
  $html .= '<input id="sf-gen-limit" class="ouinpo-sf-input" type="number" min="1" max="25" value="7">';
  $html .= '</div>';

  $html .= '<div class="ouinpo-sf-field ouinpo-sf-field-action">';
  $html .= '<label>&nbsp;</label>';
  $html .= '<button type="button" class="ouinpo-sf-btn" id="sf-gen-btn">Générer</button>';
  $html .= '</div>';

  $html .= '</div>';
  $html .= '<div id="sf-gen-msg" class="ouinpo-sf-muted ouinpo-sf-gen-msg"></div>';
  $html .= '</div>';

if (!empty($templates)) {
  $html .= '<div class="ouinpo-sf-card">';
  $html .= '<h2>Choisir un parcours modèle</h2>';
  $html .= '<p class="ouinpo-sf-muted">Seuls les modèles de ton niveau te sont proposés. Tu peux ensuite filtrer par domaine BO et par objectif.</p>';

  $html .= '<div class="ouinpo-sf-gen">';

  $html .= '<div class="ouinpo-sf-field">';
  $html .= '<label for="sf-template-domain">Domaine BO</label>';
  $html .= '<select id="sf-template-domain" class="ouinpo-sf-select">';
  $html .= '<option value="">— Tous —</option>';
  foreach ($template_domain_values as $slug => $label) {
    $html .= '<option value="'.esc_attr($slug).'">'.esc_html($label).'</option>';
  }
  $html .= '</select>';
  $html .= '</div>';

  $html .= '<div class="ouinpo-sf-field">';
  $html .= '<label for="sf-template-goal">Objectif</label>';
  $html .= '<select id="sf-template-goal" class="ouinpo-sf-select">';
  $html .= '<option value="">— Tous —</option>';
  foreach ($template_goal_values as $slug => $label) {
    $html .= '<option value="'.esc_attr($slug).'">'.esc_html($label).'</option>';
  }
  $html .= '</select>';
  $html .= '</div>';

  $html .= '<div class="ouinpo-sf-field ouinpo-sf-field-action">';
  $html .= '<label>&nbsp;</label>';
  $html .= '<button type="button" class="ouinpo-sf-btn" id="sf-template-filter-btn">Filtrer</button>';
  $html .= '</div>';

  $html .= '</div>';

  $html .= '<p id="sf-template-empty" class="ouinpo-sf-muted is-hidden">Aucun modèle ne correspond à ces filtres.</p>';
  $html .= '<div id="sf-template-msg" class="ouinpo-sf-muted ouinpo-sf-gen-msg"></div>';

  $html .= '<table class="ouinpo-sf-table ouinpo-sf-template-table">';
  $html .= '<colgroup>'
    . '<col class="ouinpo-sf-col-template-title">'
    . '<col class="ouinpo-sf-col-template-domain">'
    . '<col class="ouinpo-sf-col-template-goal">'
    . '<col class="ouinpo-sf-col-template-mode">'
    . '<col class="ouinpo-sf-col-template-action">'
    . '</colgroup>';

  $html .= '<thead><tr>'
    . '<th><div class="ouinpo-sf-cell-wrap">Modèle</div></th>'
    . '<th><div class="ouinpo-sf-cell-wrap">Domaine</div></th>'
    . '<th><div class="ouinpo-sf-cell-wrap">Objectif</div></th>'
    . '<th><div class="ouinpo-sf-cell-wrap">Mode</div></th>'
    . '<th><div class="ouinpo-sf-cell-wrap">Action</div></th>'
    . '</tr></thead><tbody id="sf-template-tbody">';

  foreach ($templates as $tpl) {
    $tpl_id = (int)($tpl['id'] ?? 0);
    if ($tpl_id <= 0) continue;

    $tpl_mode_label = (($tpl['mode'] ?? 'free') === 'sequential') ? 'Séquentiel' : 'Libre';
    $tpl_domain_slug = sanitize_key((string) ($tpl['domain_slug'] ?? ''));
    $tpl_goal_slug   = sanitize_key((string) ($tpl['goal_slug'] ?? ''));
    $tpl_domain_label = $template_domain_options[$tpl_domain_slug] ?? '—';
    $tpl_goal_label   = $template_goal_options[$tpl_goal_slug] ?? '—';

    $html .= '<tr class="sf-template-row" data-domain="'.esc_attr($tpl_domain_slug).'" data-goal="'.esc_attr($tpl_goal_slug).'">';

    $html .= '<td><div class="ouinpo-sf-cell-wrap">';
    $html .= '<strong>'.esc_html((string)($tpl['title'] ?? 'Modèle')).'</strong>';
    if (!empty($tpl['student_note'])) {
      $html .= '<br><span class="ouinpo-sf-muted">'.wp_kses_post((string)$tpl['student_note']).'</span>';
    }
    $html .= '</div></td>';

    $html .= '<td><div class="ouinpo-sf-cell-wrap">'.esc_html($tpl_domain_label).'</div></td>';
    $html .= '<td><div class="ouinpo-sf-cell-wrap">'.esc_html($tpl_goal_label).'</div></td>';
    $html .= '<td><div class="ouinpo-sf-cell-wrap">'.esc_html($tpl_mode_label).'</div></td>';
    $html .= '<td class="sf-actions"><a href="#" class="ouinpo-sf-btn sf-use-template" data-template-id="'.$tpl_id.'">Choisir</a></td>';

    $html .= '</tr>';
  }

  $html .= '</tbody></table>';
  $html .= '</div>';
}
  $html .= '<div class="ouinpo-sf-card">';
  $html .= '<h2>Mes parcours</h2>';

  if (empty($paths)) {
    $html .= '<p>Aucun parcours assigné pour le moment.</p>';
    $html .= '</div>';
  } else {
    $html .= '<table class="ouinpo-sf-table">';
    $html .= '<thead><tr>
      <th class="ouinpo-sf-col-id">ID</th>
      <th>Parcours</th>
      <th class="ouinpo-sf-col-prog">Progression</th>
      <th class="ouinpo-sf-col-action">Actions</th>
    </tr></thead><tbody>';

    foreach ($paths as $p) {
      $pid = (int)($p['id'] ?? 0);
      if ($pid <= 0) continue;

      $ids = $items_by_path[$pid] ?? [];
      $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

      $total = count($ids);
      $solved = 0;
      foreach ($ids as $eid) {
        if (($status_map[$eid] ?? 'none') === 'solved') $solved++;
      }
      $pct = ($total > 0) ? (int)round(100 * $solved / $total) : 0;

      $view_url = esc_url(add_query_arg('sf_path', $pid));
      $mode_label = (($p['mode'] ?? 'free') === 'sequential') ? 'Séquentiel' : 'Libre';
      $can_delete = class_exists('\Ouinpo\Exercises\PathsService')
        ? \Ouinpo\Exercises\PathsService::can_user_self_remove_path($pid, $user_id)
        : false;

      $bar = '<span class="sf-progressbar" style="--progress: '.(int)$pct.'%;"><span></span></span>';
      $txt = '<strong>'.(int)$pct.'%</strong> ('.(int)$solved.'/'.(int)$total.')';

      $html .= '<tr>';
      $html .= '<td>'.$pid.'</td>';
      $html .= '<td>'
        . esc_html((string)($p['title'] ?? 'Parcours'))
        . '<br><span class="ouinpo-sf-muted">Mode : '.esc_html($mode_label).'</span>';
      if (!empty($p['student_note'])) {
        $html .= '<br><span class="ouinpo-sf-muted">'.wp_kses_post((string)$p['student_note']).'</span>';
      }
      $html .= '</td>';
      $html .= '<td>'.$bar.' '.$txt.'</td>';
      $html .= '<td class="sf-actions"><a class="ouinpo-sf-btn" href="'.$view_url.'">Voir</a>';
      if ($can_delete) {
        $html .= ' <a href="#" class="ouinpo-sf-btn sf-delete-path" data-path-id="'.$pid.'">Supprimer</a>';
      }
      $html .= '</td>';
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '</div>';
  }

  ouinpo_sf_enqueue_student_parcours_script([
    'ajaxUrl' => $ajax_url,
    'nonce' => [
      'gen' => $nonce_gen,
      'tpl' => $nonce_tpl,
      'del' => $nonce_delete,
    ],
    'comps' => $competencies,
    'prefill' => [
      'domain' => $prefill_domain,
      'compId' => $prefill_comp_id,
    ],
  ]);

  return $html;
});
