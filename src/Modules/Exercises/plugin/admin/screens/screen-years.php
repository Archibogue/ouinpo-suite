<?php
namespace Ouinpo\Exercises\Admin;

if (!defined('ABSPATH')) exit;

global $wpdb;

$tbl_years  = $wpdb->prefix . 'ouin_exo_academic_years';
$tbl_groups = $wpdb->prefix . 'ouin_exo_groups';

$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
$year_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!empty($_POST) && check_admin_referer('ouinpo_years_form', 'ouinpo_years_nonce')) {
    $post_action = isset($_POST['action']) ? sanitize_key($_POST['action']) : '';
    $post_id     = isset($_POST['id']) ? intval($_POST['id']) : 0;

    $slug      = sanitize_text_field($_POST['slug'] ?? '');
    $starts_on = sanitize_text_field($_POST['starts_on'] ?? '');
    $ends_on   = sanitize_text_field($_POST['ends_on'] ?? '');

    if ($post_action === 'save') {
        $errors = [];

        if (!preg_match('/^\d{4}-\d{4}$/', $slug)) {
            $errors[] = 'Le slug doit être au format 2026-2027.';
        }
        if (!$starts_on || !$ends_on) {
            $errors[] = 'Les dates de début et de fin sont obligatoires.';
        }
        if ($starts_on && $ends_on && $starts_on >= $ends_on) {
            $errors[] = 'La date de fin doit être postérieure à la date de début.';
        }

        $slug_exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_years} WHERE slug = %s AND id <> %d",
            $slug,
            $post_id
        ));
        if ($slug_exists > 0) {
            $errors[] = 'Une année portant ce slug existe déjà.';
        }

        if (empty($errors)) {
            $data = [
                'slug'      => $slug,
                'starts_on' => $starts_on,
                'ends_on'   => $ends_on,
            ];

            if ($post_id > 0) {
                $wpdb->update($tbl_years, $data, ['id' => $post_id], ['%s', '%s', '%s'], ['%d']);
                add_settings_error('ouinpo_years', 'updated', 'Année scolaire mise à jour.', 'updated');
            } else {
                $active_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tbl_years} WHERE is_active = 1");
                $data['is_active'] = $active_count < 1 ? 1 : 0;
                $wpdb->insert($tbl_years, $data, ['%s', '%s', '%s', '%d']);

                if (!empty($data['is_active'])) {
                    update_option('ouin_exo_active_year_id', (int) $wpdb->insert_id);
                }

                add_settings_error('ouinpo_years', 'created', 'Année scolaire créée.', 'updated');
            }

            $action = '';
            $year_id = 0;
        } else {
            foreach ($errors as $i => $message) {
                add_settings_error('ouinpo_years', 'error_' . $i, $message, 'error');
            }
            $action = $post_id > 0 ? 'edit' : 'new';
        }
    }

    if ($post_action === 'activate' && $post_id > 0) {
        $wpdb->query("UPDATE {$tbl_years} SET is_active = 0 WHERE is_active <> 0");
        $wpdb->update($tbl_years, ['is_active' => 1], ['id' => $post_id], ['%d'], ['%d']);
        update_option('ouin_exo_active_year_id', $post_id);

        $purged_paths = 0;
        if (class_exists('\Ouinpo\Exercises\PathsService')) {
            $purged_paths = (int) \Ouinpo\Exercises\PathsService::purge_non_template_paths_for_new_active_year($post_id);
        }

        add_settings_error('ouinpo_years', 'activated', 'Année scolaire activée.', 'updated');
        if ($purged_paths > 0) {
            add_settings_error('ouinpo_years', 'purged_paths', sprintf('%d parcours non modèles de l’année précédente ont été supprimés.', $purged_paths), 'updated');
        }
        $action = '';
        $year_id = 0;
    }

    if ($post_action === 'delete' && $post_id > 0) {
        $is_active = (int) $wpdb->get_var($wpdb->prepare("SELECT is_active FROM {$tbl_years} WHERE id = %d", $post_id));
        $groups_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tbl_groups} WHERE year_id = %d", $post_id));

        if ($is_active === 1) {
            add_settings_error('ouinpo_years', 'delete_active', 'Impossible de supprimer l\'année active.', 'error');
        } elseif ($groups_count > 0) {
            add_settings_error('ouinpo_years', 'delete_used', 'Impossible de supprimer une année déjà utilisée par des groupes.', 'error');
        } else {
            $wpdb->delete($tbl_years, ['id' => $post_id], ['%d']);
            add_settings_error('ouinpo_years', 'deleted', 'Année scolaire supprimée.', 'updated');
        }

        $action = '';
        $year_id = 0;
    }
}

$years = $wpdb->get_results("
    SELECT y.*, COUNT(g.id) AS groups_count
    FROM {$tbl_years} y
    LEFT JOIN {$tbl_groups} g ON g.year_id = y.id
    GROUP BY y.id
    ORDER BY y.starts_on DESC, y.id DESC
");

$latest = $wpdb->get_row("SELECT * FROM {$tbl_years} ORDER BY starts_on DESC, id DESC LIMIT 1");

$default_slug = '';
$default_starts_on = '';
$default_ends_on = '';

if ($latest && preg_match('/^(\d{4})-(\d{4})$/', (string) $latest->slug, $m)) {
    $y1 = (int) $m[1] + 1;
    $y2 = (int) $m[2] + 1;
    $default_slug = $y1 . '-' . $y2;
    $default_starts_on = $y1 . '-09-01';
    $default_ends_on = $y2 . '-08-31';
} else {
    $default_slug = '2026-2027';
    $default_starts_on = '2026-09-01';
    $default_ends_on = '2027-08-31';
}

$current = (object) [
    'id'        => 0,
    'slug'      => $default_slug,
    'starts_on' => $default_starts_on,
    'ends_on'   => $default_ends_on,
    'is_active' => 0,
];

if ($action === 'edit' && $year_id > 0) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_years} WHERE id = %d", $year_id));
    if ($row) {
        $current = $row;
    } else {
        $action = '';
        $year_id = 0;
    }
}

if (!empty($_POST) && check_admin_referer('ouinpo_years_form', 'ouinpo_years_nonce') && ($action === 'edit' || $action === 'new')) {
    $current = (object) [
        'id'        => isset($_POST['id']) ? intval($_POST['id']) : 0,
        'slug'      => sanitize_text_field($_POST['slug'] ?? $default_slug),
        'starts_on' => sanitize_text_field($_POST['starts_on'] ?? $default_starts_on),
        'ends_on'   => sanitize_text_field($_POST['ends_on'] ?? $default_ends_on),
        'is_active' => 0,
    ];
}

settings_errors('ouinpo_years');
?>
<div class="wrap">
  <h1 class="wp-heading-inline">Années scolaires</h1>
  <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-years&action=new')); ?>">Ajouter</a>
  <hr class="wp-header-end"/>

        <div class="ouinpo-admin-layout">
            <div class="ouinpo-admin-layout-main">
      <h2 class="title">Liste des années</h2>
      <table class="widefat fixed striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Slug</th>
            <th>Début</th>
            <th>Fin</th>
            <th>Statut</th>
            <th>Groupes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($years)): ?>
            <tr><td colspan="7">Aucune année scolaire.</td></tr>
          <?php else: ?>
            <?php foreach ($years as $y): ?>
              <tr>
                <td><?php echo (int) $y->id; ?></td>
                <td><strong><?php echo esc_html($y->slug); ?></strong></td>
                <td><?php echo esc_html($y->starts_on); ?></td>
                <td><?php echo esc_html($y->ends_on); ?></td>
                                <td><?php echo (int) $y->is_active === 1 ? '<span class="ouinpo-admin-status-active">Active</span>' : 'Inactive'; ?></td>
                <td><?php echo (int) $y->groups_count; ?></td>
                <td>
                  <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-years&action=edit&id=' . $y->id)); ?>">Modifier</a>

                  <?php if ((int) $y->is_active !== 1): ?>
                                        <form method="post" class="ouinpo-admin-inline-form">
                      <?php wp_nonce_field('ouinpo_years_form', 'ouinpo_years_nonce'); ?>
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="id" value="<?php echo (int) $y->id; ?>">
                      <button type="submit" class="button button-small">Activer</button>
                    </form>
                  <?php endif; ?>

                                        <form method="post" class="ouinpo-admin-inline-form" onsubmit="return confirm('Supprimer cette année scolaire ?');">
                    <?php wp_nonce_field('ouinpo_years_form', 'ouinpo_years_nonce'); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $y->id; ?>">
                    <button type="submit" class="button button-small" <?php disabled((int) $y->is_active === 1 || (int) $y->groups_count > 0); ?>>Supprimer</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

            <div class="ouinpo-admin-layout-side">
      <h2 class="title"><?php echo $current->id ? 'Modifier l\'année' : 'Créer une année'; ?></h2>
      <form method="post">
        <?php wp_nonce_field('ouinpo_years_form', 'ouinpo_years_nonce'); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo (int) $current->id; ?>">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="slug">Slug</label></th>
            <td>
              <input type="text" name="slug" id="slug" class="regular-text" required value="<?php echo esc_attr($current->slug); ?>">
              <p class="description">Format attendu : 2026-2027.</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="starts_on">Début</label></th>
            <td><input type="date" name="starts_on" id="starts_on" required value="<?php echo esc_attr($current->starts_on); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label for="ends_on">Fin</label></th>
            <td><input type="date" name="ends_on" id="ends_on" required value="<?php echo esc_attr($current->ends_on); ?>"></td>
          </tr>
        </table>

        <?php submit_button($current->id ? 'Enregistrer' : 'Créer l\'année'); ?>
      </form>
    </div>
  </div>
</div>
