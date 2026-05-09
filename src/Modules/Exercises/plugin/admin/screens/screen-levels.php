<?php
namespace Ouinpo\Exercises\Admin;

if (!defined('ABSPATH')) exit;

if (!current_user_can('edit_users')) {
    wp_die('Acces refuse.');
}

global $wpdb;

$tbl_levels       = $wpdb->prefix . 'ouin_exo_school_levels';
$tbl_groups       = $wpdb->prefix . 'ouin_exo_groups';
$tbl_members      = $wpdb->prefix . 'ouin_exo_group_members';
$tbl_exercises    = $wpdb->prefix . 'ouin_exo_exercises';
$tbl_exo_levels   = $wpdb->prefix . 'ouin_exo_exercise_school_level';

$action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
$level_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$normalize_slug = static function (string $slug, string $label): string {
    $slug = trim($slug);
    if ($slug === '') {
        $slug = $label;
    }

    $slug = sanitize_title($slug);
    return substr($slug, 0, 20);
};

$usage_for_level = static function (int $id) use ($wpdb, $tbl_groups, $tbl_members, $tbl_exercises, $tbl_exo_levels): array {
    return [
        'groups' => (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_groups} WHERE school_level_id = %d",
            $id
        )),
        'members' => (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_members} WHERE school_level_id_override = %d",
            $id
        )),
        'exercises_legacy' => (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_exercises} WHERE level_id = %d",
            $id
        )),
        'exercises_links' => (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_exo_levels} WHERE school_level_id = %d",
            $id
        )),
    ];
};

if (!empty($_POST) && check_admin_referer('ouinpo_levels_form', 'ouinpo_levels_nonce')) {
    $post_action = isset($_POST['action']) ? sanitize_key(wp_unslash((string) $_POST['action'])) : '';
    $post_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($post_action === 'save') {
        $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash((string) $_POST['label'])) : '';
        $slug_raw = isset($_POST['slug']) ? sanitize_text_field(wp_unslash((string) $_POST['slug'])) : '';
        $slug = $normalize_slug($slug_raw, $label);
        $errors = [];

        if ($label === '') {
            $errors[] = 'Le libelle est obligatoire.';
        }

        if ($slug === '') {
            $errors[] = 'Le slug est obligatoire.';
        }

        if (strlen($slug) > 20) {
            $errors[] = 'Le slug ne peut pas depasser 20 caracteres.';
        }

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_levels} WHERE slug = %s AND id <> %d",
            $slug,
            $post_id
        ));

        if ($exists > 0) {
            $errors[] = 'Un niveau utilise deja ce slug.';
        }

        if (empty($errors)) {
            $data = [
                'slug'  => $slug,
                'label' => $label,
            ];

            if ($post_id > 0) {
                $wpdb->update($tbl_levels, $data, ['id' => $post_id], ['%s', '%s'], ['%d']);
                add_settings_error('ouinpo_levels', 'updated', 'Niveau mis a jour.', 'updated');
            } else {
                $wpdb->insert($tbl_levels, $data, ['%s', '%s']);
                add_settings_error('ouinpo_levels', 'created', 'Niveau cree.', 'updated');
            }

            $action = '';
            $level_id = 0;
        } else {
            foreach ($errors as $i => $message) {
                add_settings_error('ouinpo_levels', 'error_' . $i, $message, 'error');
            }
            $action = $post_id > 0 ? 'edit' : 'new';
            $level_id = $post_id;
        }
    }

    if ($post_action === 'delete' && $post_id > 0) {
        $usage = $usage_for_level($post_id);
        $total_usage = array_sum($usage);

        if ($total_usage > 0) {
            add_settings_error(
                'ouinpo_levels',
                'delete_used',
                'Impossible de supprimer ce niveau : il est encore utilise par des classes, eleves ou exercices.',
                'error'
            );
        } else {
            $wpdb->delete($tbl_levels, ['id' => $post_id], ['%d']);
            add_settings_error('ouinpo_levels', 'deleted', 'Niveau supprime.', 'updated');
        }

        $action = '';
        $level_id = 0;
    }
}

$levels = $wpdb->get_results("
    SELECT
        l.*,
        COUNT(DISTINCT g.id) AS groups_count,
        COUNT(DISTINCT CONCAT(gm.group_id, ':', gm.user_id)) AS members_count,
        COUNT(DISTINCT e.id) AS exercises_legacy_count,
        COUNT(DISTINCT esl.exercise_id) AS exercises_links_count
    FROM {$tbl_levels} l
    LEFT JOIN {$tbl_groups} g ON g.school_level_id = l.id
    LEFT JOIN {$tbl_members} gm ON gm.school_level_id_override = l.id
    LEFT JOIN {$tbl_exercises} e ON e.level_id = l.id
    LEFT JOIN {$tbl_exo_levels} esl ON esl.school_level_id = l.id
    GROUP BY l.id
    ORDER BY FIELD(l.slug, 'seconde', 'premiere', 'terminale', 'transversal') = 0,
             FIELD(l.slug, 'seconde', 'premiere', 'terminale', 'transversal'),
             l.id ASC
");

$current = (object) [
    'id'    => 0,
    'slug'  => '',
    'label' => '',
];

if ($action === 'edit' && $level_id > 0) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_levels} WHERE id = %d", $level_id));
    if ($row) {
        $current = $row;
    } else {
        $action = '';
        $level_id = 0;
    }
}

if (!empty($_POST) && ($action === 'edit' || $action === 'new')) {
    $current = (object) [
        'id'    => isset($_POST['id']) ? (int) $_POST['id'] : 0,
        'slug'  => isset($_POST['slug']) ? sanitize_text_field(wp_unslash((string) $_POST['slug'])) : '',
        'label' => isset($_POST['label']) ? sanitize_text_field(wp_unslash((string) $_POST['label'])) : '',
    ];
}

settings_errors('ouinpo_levels');
?>
<div class="wrap">
  <h1 class="wp-heading-inline">Niveaux scolaires</h1>
  <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-levels&action=new')); ?>">Ajouter</a>
  <hr class="wp-header-end"/>

  <div class="ouinpo-admin-layout">
    <div class="ouinpo-admin-layout-main">
      <h2 class="title">Liste des niveaux</h2>
      <table class="widefat fixed striped">
        <thead>
          <tr>
            <th class="ouinpo-admin-col-id">ID</th>
            <th>Libelle</th>
            <th>Slug</th>
            <th>Classes</th>
            <th>Eleves</th>
            <th>Exercices</th>
            <th class="ouinpo-admin-col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($levels)): ?>
            <tr><td colspan="7">Aucun niveau.</td></tr>
          <?php else: ?>
            <?php foreach ($levels as $level): ?>
              <?php
              $exercise_count = (int) $level->exercises_legacy_count + (int) $level->exercises_links_count;
              $total_usage = (int) $level->groups_count + (int) $level->members_count + $exercise_count;
              ?>
              <tr>
                <td><?php echo (int) $level->id; ?></td>
                <td><strong><?php echo esc_html($level->label); ?></strong></td>
                <td><code><?php echo esc_html($level->slug); ?></code></td>
                <td><?php echo (int) $level->groups_count; ?></td>
                <td><?php echo (int) $level->members_count; ?></td>
                <td><?php echo $exercise_count; ?></td>
                <td>
                  <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-levels&action=edit&id=' . (int) $level->id)); ?>">Modifier</a>
                  <form method="post" class="ouinpo-admin-inline-form" data-confirm="Supprimer ce niveau scolaire ?">
                    <?php wp_nonce_field('ouinpo_levels_form', 'ouinpo_levels_nonce'); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $level->id; ?>">
                    <button type="submit" class="button button-small button-link-delete" <?php disabled($total_usage > 0); ?>>Supprimer</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="ouinpo-admin-layout-side ouinpo-admin-layout-side--sticky">
      <h2 class="title"><?php echo $current->id ? 'Modifier le niveau' : 'Nouveau niveau'; ?></h2>
      <form method="post">
        <?php wp_nonce_field('ouinpo_levels_form', 'ouinpo_levels_nonce'); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo (int) $current->id; ?>">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="label">Libelle</label></th>
            <td>
              <input name="label" id="label" type="text" class="regular-text" required value="<?php echo esc_attr($current->label); ?>">
              <p class="description">Exemples : Seconde, Premiere, Terminale, BTS SIO 1.</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="slug">Slug</label></th>
            <td>
              <input name="slug" id="slug" type="text" class="regular-text" maxlength="20" value="<?php echo esc_attr($current->slug); ?>">
              <p class="description">Laisse vide pour le generer depuis le libelle. Maximum 20 caracteres.</p>
            </td>
          </tr>
        </table>

        <?php submit_button($current->id ? 'Enregistrer' : 'Creer le niveau'); ?>
      </form>
    </div>
  </div>
</div>
