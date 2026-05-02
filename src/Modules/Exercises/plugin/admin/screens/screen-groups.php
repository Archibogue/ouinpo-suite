<?php
namespace Ouinpo\Exercises\Admin;

if (!defined('ABSPATH')) exit;

// use wpdb; ❌ → à supprimer

global $wpdb; // ✅ pour accéder à la BDD WordPress

// ... reste de ton code

$tbl_groups   = $wpdb->prefix . 'ouin_exo_groups';
$tbl_years    = $wpdb->prefix . 'ouin_exo_academic_years';
$tbl_levels   = $wpdb->prefix . 'ouin_exo_school_levels';

$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
$group_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle POST (create/update/delete)
if (!empty($_POST) && check_admin_referer('ouinpo_groups_form', 'ouinpo_groups_nonce')) {

    // ✅ lire l'action et l'id depuis POST (et non GET)
    $post_action = isset($_POST['action']) ? sanitize_key($_POST['action']) : '';
    $post_id     = isset($_POST['id']) ? intval($_POST['id']) : 0;

    $label = sanitize_text_field($_POST['label'] ?? '');
    $year_id = isset($_POST['year_id']) ? intval($_POST['year_id']) : null;
    $school_level_id = isset($_POST['school_level_id']) ? intval($_POST['school_level_id']) : null;

    if ($post_action === 'save') {

        if ($post_id > 0) {
            $wpdb->update($tbl_groups, [
                'label' => $label,
                'year_id' => $year_id ?: null,
                'school_level_id' => $school_level_id ?: null,
            ], ['id' => $post_id], ['%s','%d','%d'], ['%d']);

            add_settings_error('ouinpo_groups', 'updated', 'Classe mise à jour.', 'updated');

        } else {
            $wpdb->insert($tbl_groups, [
                'label' => $label,
                'year_id' => $year_id ?: null,
                'school_level_id' => $school_level_id ?: null,
                'created_at' => current_time('mysql'),
            ], ['%s','%d','%d','%s']);

            add_settings_error('ouinpo_groups', 'created', 'Classe créée.', 'updated');
        }

        // reset UI state
        $action = '';
        $group_id = 0;
    }

    if ($post_action === 'delete' && $post_id > 0) {
        // Supprime d'abord les membres
        $wpdb->delete($wpdb->prefix.'ouin_exo_group_members', ['group_id' => $post_id], ['%d']);
        $wpdb->delete($tbl_groups, ['id' => $post_id], ['%d']);

        add_settings_error('ouinpo_groups', 'deleted', 'Classe supprimée.', 'updated');

        // reset UI state
        $action = '';
        $group_id = 0;
    }
}

$active_year = $wpdb->get_row("SELECT * FROM {$tbl_years} WHERE is_active=1 LIMIT 1");
$years  = $wpdb->get_results("SELECT id, slug FROM {$tbl_years} ORDER BY starts_on DESC");
$levels = $wpdb->get_results("SELECT id, label FROM {$tbl_levels} ORDER BY id ASC");

$current = (object)['id'=>0,'label'=>'','year_id'=>$active_year->id ?? null,'school_level_id'=>null];
if ($action === 'edit' && $group_id>0) {
    $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_groups} WHERE id=%d", $group_id));
    if (!$current) { $action=''; $group_id=0; }
}

// List groups
$groups = $wpdb->get_results("
    SELECT G.*, Y.slug as year_slug, L.label as level_label
    FROM {$tbl_groups} G
    LEFT JOIN {$tbl_years} Y ON Y.id = G.year_id
    LEFT JOIN {$tbl_levels} L ON L.id = G.school_level_id
    ORDER BY G.created_at DESC
");
settings_errors('ouinpo_groups');
?>
<div class="wrap">
  <h1 class="wp-heading-inline">Classes</h1>
  <hr class="wp-header-end"/>

  <div style="display:flex; gap:24px; align-items:flex-start; margin-top:12px;">
    <div style="flex:1 1 60%;">
      <h2 class="title">Liste des classes</h2>
      <table class="widefat fixed striped">
        <thead>
          <tr>
            <th>ID</th><th>Libellé</th><th>Année</th><th>Niveau</th><th>Créé le</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($groups)): ?>
            <tr><td colspan="6">Aucune classe.</td></tr>
          <?php else: foreach ($groups as $g): ?>
            <tr>
              <td><?php echo intval($g->id); ?></td>
              <td><?php echo esc_html($g->label); ?></td>
              <td><?php echo esc_html($g->year_slug ?: '—'); ?></td>
              <td><?php echo esc_html($g->level_label ?: '—'); ?></td>
              <td><?php echo esc_html($g->created_at); ?></td>
              <td>
                <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-groups&action=edit&id='.$g->id)); ?>">Modifier</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette classe ?');">
                  <?php wp_nonce_field('ouinpo_groups_form','ouinpo_groups_nonce'); ?>
                  <input type="hidden" name="label" value="">
                  <input type="hidden" name="year_id" value="">
                  <input type="hidden" name="school_level_id" value="">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo intval($g->id); ?>">
                  <button class="button button-small button-link-delete">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div style="flex:1 1 40%; position:sticky; top:32px;">
      <h2 class="title"><?php echo $current->id ? 'Modifier la classe' : 'Nouvelle classe'; ?></h2>
      <form method="post">
        <?php wp_nonce_field('ouinpo_groups_form','ouinpo_groups_nonce'); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo intval($current->id); ?>">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="label">Libellé</label></th>
            <td><input name="label" id="label" type="text" class="regular-text" required value="<?php echo esc_attr($current->label); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label for="year_id">Année scolaire</label></th>
            <td>
              <select name="year_id" id="year_id">
                <option value="">—</option>
                <?php foreach ($years as $y): ?>
                  <option value="<?php echo intval($y->id); ?>"
                    <?php selected(intval($current->year_id), intval($y->id)); ?>>
                    <?php echo esc_html($y->slug); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if ($active_year): ?>
                <p class="description">Année active : <strong><?php echo esc_html($active_year->slug); ?></strong></p>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="school_level_id">Niveau</label></th>
            <td>
              <select name="school_level_id" id="school_level_id">
                <option value="">—</option>
                <?php foreach ($levels as $l): ?>
                  <option value="<?php echo intval($l->id); ?>"
                    <?php selected(intval($current->school_level_id), intval($l->id)); ?>>
                    <?php echo esc_html($l->label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="description">Seconde / Première / Terminale.</p>
            </td>
          </tr>
        </table>

        <?php submit_button($current->id ? 'Enregistrer' : 'Créer la classe'); ?>
      </form>
    </div>
  </div>
</div>

