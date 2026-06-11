<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;

if (!defined('ABSPATH')) exit;

/**
 * Écran admin minimal pour :
 * - Lister les exercices
 * - Créer/éditer un exercice
 * - Gérer les niveaux scolaires, indices (1..3) et corrigés multiples
 *
 * Tables utilisées (préfixe $wpdb->prefix.'ouin_exo_'):
 *   - exercises (id, title, slug, statement, is_active)
 *   - exercise_school_level (exercise_id, school_level_id)
 *   - school_levels (id, slug, label, sort_order)
 *   - hints (exercise_id, hint_order, content)     // 1..3
 *   - solutions (id, exercise_id, title, content, solution_order, is_official)
 */
class Screen_Exercises {

  public static function render() {
    if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) {
      wp_die('Accès refusé');
    }

    // Actions POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      self::handle_post();
    }

    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';

    if ($action === 'delete') {
      $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
      if ($id > 0 && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'ouin_exo_delete_'.$id)) {
        self::delete_exercise($id);
        wp_safe_redirect( admin_url('admin.php?page=ouinpo-exercices&deleted=1') );
        exit;
      } else {
        wp_die('Lien de suppression invalide.');
      }
    } elseif ($action === 'edit') {
      $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
      self::render_edit($id);
    } else {
      self::render_list();
    }
  }

  private static function table($name) {
    global $wpdb;
    return $wpdb->prefix . 'ouin_exo_' . $name;
  }

  private static function get_levels() {
    global $wpdb;
    $t = self::table('school_levels');
    return $wpdb->get_results("SELECT id, slug, label FROM {$t} ORDER BY sort_order ASC, id ASC");
  }

  private static function get_exercise($id) {
    global $wpdb;
    $t = self::table('exercises');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id));
  }

  private static function get_exercise_levels($exercise_id) {
    global $wpdb;
    $t = self::table('exercise_school_level');
    return $wpdb->get_col($wpdb->prepare("SELECT school_level_id FROM {$t} WHERE exercise_id=%d", $exercise_id));
  }

  private static function get_hints_map(int $exercise_id): array {
    global $wpdb;
    $t = self::table('hints');

    // Toujours retourner les 3 clés
    $map = [1 => '', 2 => '', 3 => ''];

    if ($exercise_id > 0) {
      $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT hint_order, content FROM {$t} WHERE exercise_id=%d ORDER BY hint_order ASC", $exercise_id)
      );
      foreach ($rows as $r) {
        $ord = (int) $r->hint_order;
        if ($ord >= 1) {
          // Confort d'affichage dans le formulaire : on enlève d’éventuels slashes résiduels
          $map[$ord] = stripslashes((string) $r->content);
        }
      }
    }
    return $map;
  }

  private static function get_solutions($exercise_id) {
    global $wpdb;
    $t = self::table('solutions');
    return $wpdb->get_results($wpdb->prepare("SELECT id, title, content, solution_order, is_official FROM {$t} WHERE exercise_id=%d ORDER BY is_official DESC, solution_order ASC", $exercise_id));
  }

  private static function get_difficulties() {
    global $wpdb;
    $t = self::table('difficulties');
    return $wpdb->get_results("SELECT id, slug, label FROM {$t} ORDER BY id ASC");
  }

  private static function get_competencies() {
    global $wpdb;
    $t = self::table('competencies');
    return $wpdb->get_results("
      SELECT id, domain, domain_slug, competency, track, level
      FROM {$t}
      WHERE active = 1
      ORDER BY track, level, domain, id
    ");
  }

  private static function get_exercise_competencies(int $exercise_id): array {
    global $wpdb;
    $t = self::table('exercise_competency');
    if ($exercise_id <= 0) {
      return [];
    }
    return array_map('intval', $wpdb->get_col(
      $wpdb->prepare("SELECT competency_id FROM {$t} WHERE exercise_id=%d", $exercise_id)
    ));
  }

  private static function get_exam_meta(int $exercise_id): array {
    global $wpdb;
    $t = self::table('exam_meta');

    $defaults = [
      'exam_type'         => 'written',
      'source_type'       => '',
      'session_label'     => '',
      'year_label'        => '',
      'center_label'      => '',
      'theme_bac'         => '',
      'bac_format'        => '',
      'estimated_minutes' => '',
      'is_exam_like'      => 0,
      'subject_group'     => '',
      'sort_in_subject'   => '',
    ];

    if ($exercise_id <= 0) {
      return $defaults;
    }

    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$t} WHERE exercise_id=%d", $exercise_id),
      ARRAY_A
    );

    if (!$row) {
      return $defaults;
    }

    return array_merge($defaults, [
      'exam_type'         => (string) ($row['exam_type'] ?? 'written'),
      'source_type'       => (string) ($row['source_type'] ?? ''),
      'session_label'     => (string) ($row['session_label'] ?? ''),
      'year_label'        => (string) ($row['year_label'] ?? ''),
      'center_label'      => (string) ($row['center_label'] ?? ''),
      'theme_bac'         => (string) ($row['theme_bac'] ?? ''),
      'bac_format'        => (string) ($row['bac_format'] ?? ''),
      'estimated_minutes' => isset($row['estimated_minutes']) ? (string) $row['estimated_minutes'] : '',
      'is_exam_like'      => !empty($row['is_exam_like']) ? 1 : 0,
      'subject_group'     => (string) ($row['subject_group'] ?? ''),
      'sort_in_subject'   => isset($row['sort_in_subject']) ? (string) $row['sort_in_subject'] : '',
    ]);
  }

  private static function get_exam_theme_options(): array {
    return [
      'algorithmique'         => 'Algorithmique',
      'programmation'         => 'Programmation',
      'structures_de_donnees' => 'Structures de données',
      'bases_de_donnees_sql'  => 'Bases de données / SQL',
      'reseaux_securite'      => 'Réseaux et sécurité',
      'architecture_systemes' => 'Architecture et systèmes',
    ];
  }

  private static function get_bac_format_options(): array {
    return [
      'question_courte'   => 'Question courte',
      'lecture_code'      => 'Lecture de code',
      'code_a_completer'  => 'Code à compléter',
      'ecriture_complete' => 'Écriture complète',
      'raisonnement'      => 'Raisonnement',
    ];
  }

  private static function exam_meta_is_empty(array $meta): bool {
    return
      ($meta['source_type'] ?? '') === '' &&
      ($meta['session_label'] ?? '') === '' &&
      ($meta['year_label'] ?? '') === '' &&
      ($meta['center_label'] ?? '') === '' &&
      ($meta['theme_bac'] ?? '') === '' &&
      ($meta['bac_format'] ?? '') === '' &&
      empty($meta['estimated_minutes']) &&
      empty($meta['is_exam_like']) &&
      ($meta['subject_group'] ?? '') === '' &&
      empty($meta['sort_in_subject']);
  }

  private static function sync_exam_meta(int $exercise_id, array $meta): void {
    global $wpdb;
    $t = self::table('exam_meta');

    if ($exercise_id <= 0) {
      return;
    }

    if (self::exam_meta_is_empty($meta)) {
      $wpdb->delete($t, ['exercise_id' => $exercise_id], ['%d']);
      return;
    }

    $wpdb->replace(
      $t,
      [
        'exercise_id'        => $exercise_id,
        'exam_type'          => 'written',
        'source_type'        => $meta['source_type'] ?: (!empty($meta['is_exam_like']) ? 'type_bac' : 'classic'),
        'session_label'      => $meta['session_label'] ?: null,
        'year_label'         => $meta['year_label'] ?: null,
        'center_label'       => $meta['center_label'] ?: null,
        'theme_bac'          => $meta['theme_bac'] ?: null,
        'bac_format'         => $meta['bac_format'] ?: null,
        'estimated_minutes'  => $meta['estimated_minutes'] !== '' ? (int) $meta['estimated_minutes'] : null,
        'is_exam_like'       => !empty($meta['is_exam_like']) ? 1 : 0,
        'subject_group'      => $meta['subject_group'] ?: null,
        'sort_in_subject'    => $meta['sort_in_subject'] !== '' ? (int) $meta['sort_in_subject'] : null,
        'created_at'         => current_time('mysql'),
        'updated_at'         => current_time('mysql'),
      ],
      ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s']
    );
  }

  private static function delete_exercise(int $id) {
    global $wpdb;
    if ($id <= 0) return;

    $p_exo   = self::table('exercises');
    $p_lv    = self::table('exercise_school_level');
    $p_hint  = self::table('hints');
    $p_sol   = self::table('solutions');
    $p_comp  = self::table('exercise_competency');
    $p_items = self::table('assessment_items');
    $p_exam  = self::table('exam_meta');

    $where = ['exercise_id' => $id];
    $fmt   = ['%d'];

    // Nettoyage des tables liées
    $wpdb->delete($p_hint,  $where, $fmt);
    $wpdb->delete($p_sol,   $where, $fmt);
    $wpdb->delete($p_lv,    $where, $fmt);
    $wpdb->delete($p_comp,  $where, $fmt);
    $wpdb->delete($p_items, $where, $fmt);
    $wpdb->delete($p_exam,  $where, $fmt);

    // Enfin, suppression de l’exercice lui-même
    $wpdb->delete($p_exo, ['id' => $id], ['%d']);
  }

  private static function handle_bulk() {
    if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) {
      wp_die('Droits insuffisants.');
    }
    if (!isset($_POST['ouin_exo_bulk_nonce']) || !wp_verify_nonce($_POST['ouin_exo_bulk_nonce'], 'ouin_exo_bulk')) {
      wp_die('Nonce invalide.');
    }

    $action = isset($_POST['bulk_action']) ? sanitize_key($_POST['bulk_action']) : '';
    $ids    = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : [];

    if (!$action || !$ids) {
      return;
    }

    global $wpdb;
    $p_exo = self::table('exercises');

    switch ($action) {
      case 'activate':
      case 'deactivate':
        $is_active = ($action === 'activate') ? 1 : 0;
        foreach ($ids as $id) {
          if ($id > 0) {
            $wpdb->update($p_exo, ['is_active' => $is_active], ['id' => $id], ['%d'], ['%d']);
          }
        }
        break;

      case 'delete':
        foreach ($ids as $id) {
          if ($id > 0) {
            self::delete_exercise($id);
          }
        }
        break;
    }

    // Redirection pour éviter le re-POST
    wp_safe_redirect( admin_url('admin.php?page=ouinpo-exercices') );
    exit;
  }

  private static function render_list() {
    global $wpdb;
    $t_exo  = self::table('exercises');
    $t_diff = self::table('difficulties');
    $t_exam = self::table('exam_meta');

    $difficulties       = self::get_difficulties();
    $exam_theme_options = self::get_exam_theme_options();
    $source_type_labels = [
      'annale'   => 'Annale',
      'inspired' => 'Inspiré annale',
      'type_bac' => 'Type bac',
      'classic'  => 'Classique',
    ];

    $search       = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
    $difficulty   = isset($_GET['filter_difficulty']) ? (int) $_GET['filter_difficulty'] : 0;
    $exam_like    = isset($_GET['filter_exam_like']) ? sanitize_key((string) $_GET['filter_exam_like']) : '';
    $source_type  = isset($_GET['filter_source_type']) ? sanitize_key((string) $_GET['filter_source_type']) : '';
    $theme_bac    = isset($_GET['filter_theme_bac']) ? sanitize_key((string) $_GET['filter_theme_bac']) : '';
    $active       = isset($_GET['filter_active']) ? sanitize_key((string) $_GET['filter_active']) : '';

    $where = ["(em.exam_type IS NULL OR em.exam_type <> 'practical_subject')"];
    $params = [];

    if ($search !== '') {
      $where[] = "(e.title LIKE %s OR e.slug LIKE %s)";
      $like = '%' . $wpdb->esc_like($search) . '%';
      $params[] = $like;
      $params[] = $like;
    }

    if ($difficulty > 0) {
      $where[] = "e.difficulty_id = %d";
      $params[] = $difficulty;
    }

    if ($exam_like === 'yes') {
      $where[] = "COALESCE(em.is_exam_like, 0) = 1";
    } elseif ($exam_like === 'no') {
      $where[] = "COALESCE(em.is_exam_like, 0) = 0";
    }

    if (isset($source_type_labels[$source_type])) {
      $where[] = "em.source_type = %s";
      $params[] = $source_type;
    }

    if (isset($exam_theme_options[$theme_bac])) {
      $where[] = "em.theme_bac = %s";
      $params[] = $theme_bac;
    }

    if ($active === 'yes') {
      $where[] = "e.is_active = 1";
    } elseif ($active === 'no') {
      $where[] = "e.is_active = 0";
    }

    $sql = "
      SELECT e.id, e.title, e.slug, e.is_active, e.created_at,
             d.label AS difficulty_label,
             em.source_type, em.theme_bac, em.session_label, em.year_label, em.is_exam_like
      FROM {$t_exo} AS e
      LEFT JOIN {$t_diff} AS d ON e.difficulty_id = d.id
      LEFT JOIN {$t_exam} AS em ON em.exercise_id = e.id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY e.created_at DESC
      LIMIT 200
    ";

    if (!empty($params)) {
      $sql = $wpdb->prepare($sql, $params);
    }

    $rows = $wpdb->get_results($sql);
    ?>
    <div class="wrap">
      <h1 class="wp-heading-inline">Exercices NSI</h1>
      <a href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-exercices&action=edit')); ?>" class="page-title-action">Ajouter</a>
      <hr class="wp-header-end">

      <?php if (!empty($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p>Exercice(s) supprimé(s).</p></div>
      <?php endif; ?>

      <form method="get" class="ouinpo-admin-filter-box">
        <input type="hidden" name="page" value="ouinpo-exercices">
        <div class="ouinpo-admin-filter-grid">
          <label>
            <span class="ouinpo-admin-field-label">Recherche</span>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" class="regular-text ouinpo-admin-full-width">
          </label>

          <label>
            <span class="ouinpo-admin-field-label">Difficulté</span>
            <select name="filter_difficulty" class="ouinpo-admin-full-width">
              <option value="0">Toutes</option>
              <?php foreach ($difficulties as $diff): ?>
                <option value="<?php echo (int) $diff->id; ?>" <?php selected($difficulty, (int) $diff->id); ?>>
                  <?php echo esc_html($diff->label); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>
            <span class="ouinpo-admin-field-label">Type</span>
            <select name="filter_exam_like" class="ouinpo-admin-full-width">
              <option value="">Tous</option>
              <option value="yes" <?php selected($exam_like, 'yes'); ?>>Type bac</option>
              <option value="no" <?php selected($exam_like, 'no'); ?>>Classique</option>
            </select>
          </label>

          <label>
            <span class="ouinpo-admin-field-label">Origine</span>
            <select name="filter_source_type" class="ouinpo-admin-full-width">
              <option value="">Toutes</option>
              <?php foreach ($source_type_labels as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($source_type, $value); ?>>
                  <?php echo esc_html($label); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>
            <span class="ouinpo-admin-field-label">Thème bac</span>
            <select name="filter_theme_bac" class="ouinpo-admin-full-width">
              <option value="">Tous</option>
              <?php foreach ($exam_theme_options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($theme_bac, $value); ?>>
                  <?php echo esc_html($label); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>
            <span class="ouinpo-admin-field-label">État</span>
            <select name="filter_active" class="ouinpo-admin-full-width">
              <option value="">Tous</option>
              <option value="yes" <?php selected($active, 'yes'); ?>>Actifs</option>
              <option value="no" <?php selected($active, 'no'); ?>>Inactifs</option>
            </select>
          </label>
        </div>

        <p class="ouinpo-admin-actions-row">
          <button type="submit" class="button button-primary">Filtrer</button>
          <a href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-exercices')); ?>" class="button">Réinitialiser</a>
          <span class="ouinpo-admin-muted"><?php echo count($rows); ?> résultat(s) affiché(s)</span>
        </p>
      </form>

      <form method="post">
        <?php wp_nonce_field('ouin_exo_bulk', 'ouin_exo_bulk_nonce'); ?>
        <input type="hidden" name="action" value="bulk_exercises">

        <div class="tablenav top">
          <div class="alignleft actions bulkactions">
            <label class="screen-reader-text" for="bulk-action-selector-top">Actions groupées</label>
            <select name="bulk_action" id="bulk-action-selector-top">
              <option value="">Actions groupées</option>
              <option value="activate">Activer</option>
              <option value="deactivate">Désactiver</option>
              <option value="delete">Supprimer</option>
            </select>
            <input type="submit" class="button action" value="Appliquer">
          </div>
          <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <td id="cb" class="manage-column column-cb check-column">
                <input type="checkbox" id="ouin-exo-cb-all" data-check-all-target=".ouin-exo-cb">
              </td>
              <th scope="col">ID</th>
              <th scope="col">Titre</th>
              <th scope="col">Slug</th>
              <th scope="col">Difficulté</th>
              <th scope="col">Type</th>
              <th scope="col">Origine</th>
              <th scope="col">Thème bac</th>
              <th scope="col">Session</th>
              <th scope="col">Actif</th>
              <th scope="col">Créé le</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
              <th scope="row" class="check-column">
                <input type="checkbox" class="ouin-exo-cb" name="ids[]" value="<?php echo (int) $r->id; ?>">
              </th>
              <td><?php echo (int)$r->id; ?></td>
              <td><?php echo esc_html($r->title); ?></td>
              <td><code><?php echo esc_html($r->slug); ?></code></td>
              <td><?php echo $r->difficulty_label ? esc_html($r->difficulty_label) : '&mdash;'; ?></td>
              <td><?php echo !empty($r->is_exam_like) ? 'Type bac' : 'Classique'; ?></td>
              <td><?php echo !empty($r->source_type) && isset($source_type_labels[$r->source_type]) ? esc_html($source_type_labels[$r->source_type]) : '&mdash;'; ?></td>
              <td><?php echo !empty($r->theme_bac) && isset($exam_theme_options[$r->theme_bac]) ? esc_html($exam_theme_options[$r->theme_bac]) : '&mdash;'; ?></td>
              <td><?php echo trim((string) ($r->session_label . ' ' . $r->year_label)) !== '' ? esc_html(trim((string) ($r->session_label . ' ' . $r->year_label))) : '&mdash;'; ?></td>
              <td><?php echo $r->is_active ? 'Oui' : 'Non'; ?></td>
              <td><?php echo esc_html($r->created_at); ?></td>
              <td>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-exercices&action=edit&id='.(int)$r->id)); ?>" class="button button-small">Éditer</a>
                <?php
                  $del_url = wp_nonce_url(
                    admin_url('admin.php?page=ouinpo-exercices&action=delete&id='.(int)$r->id),
                    'ouin_exo_delete_'.(int)$r->id,
                    '_wpnonce'
                  );
                ?>
                <a href="<?php echo esc_url($del_url); ?>" class="button button-small button-link-delete" data-confirm="Supprimer définitivement cet exercice ?">Supprimer</a>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="12">Aucun exercice pour l’instant.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </form>

    </div>
    <?php
  }

  private static function render_edit($id) {
    $is_new = $id <= 0;
    $exercise = $is_new ? (object)[
      'id'           => 0,
      'title'        => '',
      'slug'         => '',
      'statement'    => '',
      'is_active'    => 1,
      'difficulty_id'=> null,
    ] : self::get_exercise($id);

    if (!$exercise && !$is_new) {
      echo '<div class="notice notice-error"><p>Exercice introuvable.</p></div>';
      self::render_list();
      return;
    }

    $levels           = self::get_levels();
    $selected_levels  = $is_new ? [] : self::get_exercise_levels($exercise->id);
    $hints            = self::get_hints_map( (int) $exercise->id );
    $solutions        = $is_new ? [] : self::get_solutions($exercise->id);
    $exam_meta        = $is_new ? self::get_exam_meta(0) : self::get_exam_meta((int) $exercise->id);

    // 🔹 Difficultés et compétences BO
    $difficulties          = self::get_difficulties();
    $competencies          = self::get_competencies();
    $selected_competencies = $is_new ? [] : self::get_exercise_competencies((int) $exercise->id);
    $exam_theme_options    = self::get_exam_theme_options();
    $bac_format_options    = self::get_bac_format_options();

    ?>
    <div class="wrap">
<h1 class="wp-heading-inline">
  <?php echo $is_new ? 'Nouvel exercice' : 'Éditer l’exercice #'.(int)$exercise->id; ?>
</h1>

<a href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-exercices&action=edit')); ?>"
   class="page-title-action">+ Nouvel exercice</a>

<a href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-exercices')); ?>"
   class="page-title-action">← Retour à la liste</a>

<hr class="wp-header-end">
      <form method="post">
        <?php wp_nonce_field('ouin_exo_save','ouin_exo_nonce'); ?>
        <input type="hidden" name="action" value="save_exercise">
        <input type="hidden" name="exercise_id" value="<?php echo (int)$exercise->id; ?>">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="title">Titre</label></th>
            <td>
              <input required type="text" class="regular-text" id="title" name="title"
                     value="<?php echo esc_attr($exercise->title); ?>">
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="slug">Slug</label></th>
            <td>
              <input required type="text" class="regular-text" id="slug" name="slug"
                     value="<?php echo esc_attr($exercise->slug); ?>">
              <p class="description">Unique. Utilisé dans l’URL/API.</p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="difficulty_id">Difficulté</label></th>
            <td>
              <select id="difficulty_id" name="difficulty_id">
                <option value="">— Non définie —</option>
                <?php foreach ($difficulties as $diff): ?>
                  <option value="<?php echo (int) $diff->id; ?>"
                    <?php selected( (int)($exercise->difficulty_id ?? 0), (int)$diff->id ); ?>>
                    <?php echo esc_html($diff->label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="description">Par ex. Débutant / Confirmé / Expert.</p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="statement">Énoncé</label></th>
            <td>
              <?php
              // Éditeur WP — stripslashes pour confort d’affichage
              $content = isset($exercise->statement) ? stripslashes($exercise->statement) : '';
              wp_editor($content, 'statement', [
                'textarea_name' => 'statement',
                'textarea_rows' => 12,
              ]);
              ?>
            </td>
          </tr>

          <tr>
            <th scope="row">Niveaux scolaires</th>
            <td>
              <?php foreach ($levels as $lv): ?>
                <label class="ouinpo-admin-inline-check">
                  <input type="checkbox" name="school_levels[]" value="<?php echo (int)$lv->id; ?>"
                    <?php checked(in_array((int)$lv->id, array_map('intval',$selected_levels), true)); ?>>
                  <?php echo esc_html($lv->label); ?>
                </label>
              <?php endforeach; ?>
              <p class="description">Les élèves ne voient que les exercices de leur niveau par défaut.</p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="ouin-exo-competencies">Compétences BO liées</label></th>
            <td>
              <select id="ouin-exo-competencies"
                      name="competencies[]"
                      multiple="multiple"
                      size="8"
                      class="ouinpo-admin-table-wide">
                <?php
                  $current_group = '';
                  foreach ($competencies as $c):
                    $group = trim($c->track . ' — ' . $c->level);
                    if ($group !== $current_group) {
                      if ($current_group !== '') {
                        echo '</optgroup>';
                      }
                      $current_group = $group;
                      echo '<optgroup label="' . esc_attr($current_group) . '">';
                    }
                    $is_selected = in_array((int)$c->id, $selected_competencies, true);
                ?>
                  <option value="<?php echo (int)$c->id; ?>" <?php echo $is_selected ? 'selected="selected"' : ''; ?>>
                    <?php echo esc_html($c->domain . ' — ' . $c->competency); ?>
                  </option>
                <?php
                  endforeach;
                  if ($current_group !== '') {
                    echo '</optgroup>';
                  }
                ?>
              </select>
              <p class="description">Ctrl/Cmd+clic pour sélectionner plusieurs compétences.</p>
            </td>
          </tr>

          <tr>
            <th scope="row">Métadonnées bac</th>
            <td>
              <fieldset class="ouinpo-admin-meta-grid">
                <label>
                  <span class="ouinpo-admin-field-label">Exercice de type bac</span>
                  <input type="checkbox" name="exam_meta[is_exam_like]" value="1" <?php checked(!empty($exam_meta['is_exam_like'])); ?>>
                  <span>Oui</span>
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Origine</span>
                  <select name="exam_meta[source_type]" class="ouinpo-admin-select-wide">
                    <option value="">— Aucune —</option>
                    <option value="annale" <?php selected((string) $exam_meta['source_type'], 'annale'); ?>>Annale</option>
                    <option value="inspired" <?php selected((string) $exam_meta['source_type'], 'inspired'); ?>>Inspiré annale</option>
                    <option value="type_bac" <?php selected((string) $exam_meta['source_type'], 'type_bac'); ?>>Type bac</option>
                    <option value="classic" <?php selected((string) $exam_meta['source_type'], 'classic'); ?>>Classique</option>
                  </select>
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Session</span>
                  <input type="text" class="regular-text" name="exam_meta[session_label]" value="<?php echo esc_attr((string) $exam_meta['session_label']); ?>">
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Année</span>
                  <input type="text" class="small-text" name="exam_meta[year_label]" value="<?php echo esc_attr((string) $exam_meta['year_label']); ?>">
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Centre</span>
                  <input type="text" class="regular-text" name="exam_meta[center_label]" value="<?php echo esc_attr((string) $exam_meta['center_label']); ?>">
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Thème bac</span>
                  <select name="exam_meta[theme_bac]" class="ouinpo-admin-select-wide">
                    <option value="">— Aucun —</option>
                    <?php foreach ($exam_theme_options as $value => $label): ?>
                      <option value="<?php echo esc_attr($value); ?>" <?php selected((string) $exam_meta['theme_bac'], $value); ?>>
                        <?php echo esc_html($label); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Format bac</span>
                  <select name="exam_meta[bac_format]" class="ouinpo-admin-select-wide">
                    <option value="">— Aucun —</option>
                    <?php foreach ($bac_format_options as $value => $label): ?>
                      <option value="<?php echo esc_attr($value); ?>" <?php selected((string) $exam_meta['bac_format'], $value); ?>>
                        <?php echo esc_html($label); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Durée estimée (minutes)</span>
                  <input type="number" min="1" step="1" class="small-text" name="exam_meta[estimated_minutes]" value="<?php echo esc_attr((string) $exam_meta['estimated_minutes']); ?>">
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Groupe de sujet</span>
                  <input type="text" class="regular-text" name="exam_meta[subject_group]" value="<?php echo esc_attr((string) $exam_meta['subject_group']); ?>">
                </label>

                <label>
                  <span class="ouinpo-admin-field-label">Ordre dans le sujet</span>
                  <input type="number" min="1" step="1" class="small-text" name="exam_meta[sort_in_subject]" value="<?php echo esc_attr((string) $exam_meta['sort_in_subject']); ?>">
                </label>
              </fieldset>
              <p class="description">Ici, on ne gère que les exercices écrits. Les sujets pratiques restent dans l’écran dédié.</p>
            </td>
          </tr>

          <tr>
            <th scope="row">Actif</th>
            <td>
              <label>
                <input type="checkbox" name="is_active" value="1"
                  <?php checked((int)$exercise->is_active === 1); ?>>
                Visible
              </label>
            </td>
          </tr>
        </table>

        <h2>Indices (cachés par défaut)</h2>
        <table class="form-table">
          <tbody id="ouin-hints-tbody">
          <?php
          $hint_orders = array_keys($hints);
          sort($hint_orders, SORT_NUMERIC);
          foreach ($hint_orders as $i):
          ?>
            <tr>
              <th scope="row"><label for="hint_<?php echo $i; ?>">Indice <?php echo $i; ?></label></th>
              <td>
                <p><label>Ordre <input type="number" min="1" name="hint_orders[<?php echo $i; ?>]" value="<?php echo (int) $i; ?>" class="small-text"></label></p>
                <textarea id="hint_<?php echo $i; ?>"
                          name="hints[<?php echo $i; ?>]"
                          rows="6"
                          class="ouinpo-admin-full-width"><?php echo esc_textarea($hints[$i] ?? ''); ?></textarea>
                <p class="description">Tu peux saisir du HTML simple (bold, listes, liens).</p>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <p><button type="button" class="button" id="ouin-add-hint" data-hints-target="#ouin-hints-tbody" data-next-index="<?php echo esc_attr((string) (max($hint_orders ?: [0]) + 1)); ?>">+ Ajouter une aide IA</button></p>

        <h2>Corrigés possibles (cachés par défaut)</h2>
        <p class="description">Tu peux proposer plusieurs corrigés (un “officiel”, d’autres alternatifs).</p>
        <table class="widefat fixed striped">
          <thead>
            <tr>
              <th>Ordre</th>
              <th>Titre</th>
              <th>Officiel</th>
              <th>Contenu</th>
            </tr>
          </thead>
          <tbody id="ouin-solutions-tbody">
            <?php
            $index = 0;
            if ($solutions) {
              foreach ($solutions as $sol) {
                self::render_solution_row($index++, $sol);
              }
            } else {
              // Une ligne vide par défaut
              self::render_solution_row($index++, null);
            }
            ?>
          </tbody>
        </table>
        <p><button type="button" class="button" id="ouin-add-solution" data-solutions-target="#ouin-solutions-tbody" data-next-index="<?php echo (int) $index; ?>">+ Ajouter un corrigé</button></p>

        <?php submit_button($is_new ? 'Créer l’exercice' : 'Enregistrer'); ?>
      </form>
    </div>

    <?php
  }

  private static function render_solution_row($i, $sol) {
    $sol = $sol ?: (object)[
  'id'            => 0,
  'title'         => 'Soluce',
  'content'       => '',
  'solution_order'=> 1,
  'is_official'   => 1
];
    ?>
    <tr>
      <td><input type="number" min="1" name="solutions[<?php echo $i; ?>][solution_order]" value="<?php echo (int)$sol->solution_order; ?>" class="ouinpo-admin-input-order"></td>
      <td><input type="text" name="solutions[<?php echo $i; ?>][title]" class="regular-text" value="<?php echo esc_attr($sol->title); ?>"></td>
      <td class="ouinpo-admin-cell-centered"><input type="checkbox" name="solutions[<?php echo $i; ?>][is_official]" value="1" <?php checked((int)$sol->is_official === 1); ?>></td>
      <td>
        <textarea name="solutions[<?php echo $i; ?>][content]" rows="6" class="ouinpo-admin-full-width"><?php echo esc_textarea(stripslashes($sol->content)); ?></textarea>
        <input type="hidden" name="solutions[<?php echo $i; ?>][id]" value="<?php echo (int)$sol->id; ?>">
      </td>
    </tr>
    <?php
  }

  private static function handle_post() {
    // 1) Actions bulk sur la liste
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_exercises') {
      self::handle_bulk();
      return;
    }

    // 2) Sauvegarde d'un exercice
    if (!isset($_POST['ouin_exo_nonce']) || !wp_verify_nonce($_POST['ouin_exo_nonce'], 'ouin_exo_save')) {
      wp_die('Nonce invalide.');
    }
    if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) wp_die('Droits insuffisants.');

    global $wpdb;
    $p_exo  = self::table('exercises');
    $p_lv   = self::table('exercise_school_level');
    $p_hint = self::table('hints');
    $p_sol  = self::table('solutions');
    $p_comp = self::table('exercise_competency');

    $id = isset($_POST['exercise_id']) ? (int) $_POST['exercise_id'] : 0;

    // Dés-échappe les champs texte, puis nettoie
    $title = isset($_POST['title']) ? sanitize_text_field( wp_unslash($_POST['title']) ) : '';
    $slug  = isset($_POST['slug'])  ? sanitize_title( wp_unslash($_POST['slug']) ) : '';
    $is_active     = isset($_POST['is_active']) ? 1 : 0;
    $difficulty_id = isset($_POST['difficulty_id']) ? (int) $_POST['difficulty_id'] : 0;

    $raw_exam_meta = isset($_POST['exam_meta']) && is_array($_POST['exam_meta']) ? $_POST['exam_meta'] : [];
    $exam_meta = [
      'exam_type'         => 'written',
      'source_type'       => sanitize_key((string) ($raw_exam_meta['source_type'] ?? '')),
      'session_label'     => sanitize_text_field(wp_unslash((string) ($raw_exam_meta['session_label'] ?? ''))),
      'year_label'        => sanitize_text_field(wp_unslash((string) ($raw_exam_meta['year_label'] ?? ''))),
      'center_label'      => sanitize_text_field(wp_unslash((string) ($raw_exam_meta['center_label'] ?? ''))),
      'theme_bac'         => sanitize_key((string) ($raw_exam_meta['theme_bac'] ?? '')),
      'bac_format'        => sanitize_key((string) ($raw_exam_meta['bac_format'] ?? '')),
      'estimated_minutes' => isset($raw_exam_meta['estimated_minutes']) && $raw_exam_meta['estimated_minutes'] !== '' ? max(1, (int) $raw_exam_meta['estimated_minutes']) : '',
      'is_exam_like'      => !empty($raw_exam_meta['is_exam_like']) ? 1 : 0,
      'subject_group'     => sanitize_text_field(wp_unslash((string) ($raw_exam_meta['subject_group'] ?? ''))),
      'sort_in_subject'   => isset($raw_exam_meta['sort_in_subject']) && $raw_exam_meta['sort_in_subject'] !== '' ? max(1, (int) $raw_exam_meta['sort_in_subject']) : '',
    ];

    $allowed_source_types = ['annale', 'inspired', 'type_bac', 'classic'];
    if (!in_array($exam_meta['source_type'], $allowed_source_types, true)) {
      $exam_meta['source_type'] = '';
    }
    
    $allowed_themes = array_keys(self::get_exam_theme_options());
    if ($exam_meta['theme_bac'] !== '' && !in_array($exam_meta['theme_bac'], $allowed_themes, true)) {
      $exam_meta['theme_bac'] = '';
    }
    
    $allowed_formats = array_keys(self::get_bac_format_options());
    if ($exam_meta['bac_format'] !== '' && !in_array($exam_meta['bac_format'], $allowed_formats, true)) {
      $exam_meta['bac_format'] = '';
    }
    
    if ($exam_meta['source_type'] === 'classic') {
      $exam_meta['is_exam_like'] = 0;
    }

    // Autoriser HTML dans l’énoncé/indices/corrigés mais filtrer
    $allowed = wp_kses_allowed_html('post');

    // 1) Énoncé : unslash => kses
    $raw_statement = isset($_POST['statement']) ? wp_unslash((string) $_POST['statement']) : '';
    $statement = $allowed ? wp_kses($raw_statement, $allowed) : wp_kses_post($raw_statement);

    if (!$title || !$slug) {
      add_action('admin_notices', function(){
        echo '<div class="notice notice-error"><p>Titre et slug sont requis.</p></div>';
      });
      return;
    }

    // Données exercice
    $exo_data = [
      'title'     => $title,
      'slug'      => $slug,
      'statement' => $statement,
      'is_active' => $is_active,
    ];
    $exo_format = ['%s','%s','%s','%d'];

    if ($difficulty_id > 0) {
      $exo_data['difficulty_id'] = $difficulty_id;
      $exo_format[] = '%d';
    }

    // Insert / Update exercice
    if ($id > 0) {
      $wpdb->update($p_exo, $exo_data, ['id' => $id], $exo_format, ['%d']);
    } else {
      $wpdb->insert($p_exo, $exo_data, $exo_format);
      $id = (int)$wpdb->insert_id;
    }

    // Niveaux scolaires
    $levels = isset($_POST['school_levels']) ? array_map('intval', (array)$_POST['school_levels']) : [];
    $wpdb->delete($p_lv, ['exercise_id'=>$id], ['%d']);
    foreach ($levels as $lv_id) {
      if ($lv_id > 0) {
        $wpdb->insert($p_lv, ['exercise_id'=>$id, 'school_level_id'=>$lv_id], ['%d','%d']);
      }
    }

    // Compétences BO
    $competencies = isset($_POST['competencies']) ? array_map('intval', (array)$_POST['competencies']) : [];
    $wpdb->delete($p_comp, ['exercise_id'=>$id], ['%d']);
    foreach ($competencies as $cid) {
      if ($cid > 0) {
        $wpdb->insert($p_comp, ['exercise_id'=>$id, 'competency_id'=>$cid], ['%d','%d']);
      }
    }

    // Indices / aides IA, sans limite fixe.
    $hints = isset($_POST['hints']) ? (array)$_POST['hints'] : [];
    $hint_orders = isset($_POST['hint_orders']) ? (array) $_POST['hint_orders'] : [];
    $wpdb->delete($p_hint, ['exercise_id'=>$id], ['%d']);
    foreach ($hints as $key => $raw_hint) {
      $ord = isset($hint_orders[$key]) ? max(1, (int) $hint_orders[$key]) : max(1, (int) $key);
      $raw = wp_unslash((string)$raw_hint);
      $content = $allowed ? wp_kses($raw, $allowed) : wp_kses_post($raw);
      if (trim($content) !== '') {
        $wpdb->insert($p_hint, [
          'exercise_id'=>$id, 'hint_order'=>$ord, 'content'=>$content
        ], ['%d','%d','%s']);
      }
    }

    // Corrigés multiples — UNSLASH sur title/content, puis KSES sur content
    $solutions = isset($_POST['solutions']) ? (array)$_POST['solutions'] : [];
    $kept_ids = [];
    foreach ($solutions as $row) {
      $sid      = isset($row['id']) ? (int)$row['id'] : 0;
      $titleSol = isset($row['title']) ? sanitize_text_field( wp_unslash($row['title']) ) : '';
      $order    = isset($row['solution_order']) ? (int)$row['solution_order'] : 1;
      $official = isset($row['is_official']) ? 1 : 0;
      $raw_ctt  = isset($row['content']) ? wp_unslash((string)$row['content']) : '';
      $content  = $allowed ? wp_kses($raw_ctt, $allowed) : wp_kses_post($raw_ctt);

      if ($titleSol === '' && trim($content) === '') continue; // ignore lignes vides

      if ($sid > 0) {
        $wpdb->update($p_sol, [
          'title'=>$titleSol, 'content'=>$content, 'solution_order'=>$order, 'is_official'=>$official
        ], ['id'=>$sid, 'exercise_id'=>$id], ['%s','%s','%d','%d'], ['%d','%d']);
        $kept_ids[] = $sid;
      } else {
        $wpdb->insert($p_sol, [
          'exercise_id'=>$id, 'title'=>$titleSol, 'content'=>$content, 'solution_order'=>$order, 'is_official'=>$official
        ], ['%d','%s','%s','%d','%d']);
        $kept_ids[] = (int)$wpdb->insert_id;
      }
    }

    // Supprime les corrigés qui ne sont plus dans le formulaire
    if ($id > 0) {
      $ids_in = $kept_ids ? implode(',', array_map('intval',$kept_ids)) : '0';
      $wpdb->query($wpdb->prepare("DELETE FROM {$p_sol} WHERE exercise_id=%d AND id NOT IN ($ids_in)", $id));
    }

    self::sync_exam_meta($id, $exam_meta);

    // Feedback + redirection douce
    add_action('admin_notices', function(){
      echo '<div class="notice notice-success"><p>Exercice enregistré.</p></div>';
    });

    wp_safe_redirect( admin_url('admin.php?page=ouinpo-exercices&action=edit&id='.$id) );
    exit;
  }
}

