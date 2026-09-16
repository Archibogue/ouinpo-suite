<?php
/**
 * Admin screen: Group members assignment
 */
defined('ABSPATH') || exit;

if (!\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_CLASSES)) {
    wp_die('Accès refusé');
}

global $wpdb;

$tbl_groups  = $wpdb->prefix . 'ouin_exo_groups';
$tbl_members = $wpdb->prefix . 'ouin_exo_group_members';
$tbl_levels  = $wpdb->prefix . 'ouin_exo_school_levels';
$learning_policy = new \Ouinpo\Suite\Core\Privacy\LearningDataPolicy();

$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

// Actions : add/remove
if (!empty($_POST) && check_admin_referer('ouinpo_assign_form','ouinpo_assign_nonce')) {
    $group_id = intval($_POST['group_id'] ?? 0);
    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$tbl_groups} WHERE id=%d", $group_id))) {
        wp_die('Classe introuvable.');
    }

    if (isset($_POST['add_users']) && is_array($_POST['add_users'])) {
        foreach ($_POST['add_users'] as $uid) {
            $uid = intval($uid);
            if ($uid > 0 && $learning_policy->canBeAssignedToClass($uid)) {
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$tbl_members} (group_id, user_id, role, school_level_id_override)
                     VALUES (%d, %d, 'student', NULL)",
                     $group_id, $uid
                ));
            }
        }
        add_settings_error('ouinpo_assign', 'added', 'Élèves ajoutés dans la classe.', 'updated');
    }
    if (isset($_POST['remove_users']) && is_array($_POST['remove_users'])) {
        foreach ($_POST['remove_users'] as $uid) {
            $uid = intval($uid);
            if ($uid > 0) {
                $wpdb->delete($tbl_members, ['group_id'=>$group_id, 'user_id'=>$uid], ['%d','%d']);
                $class_subgroups = \Ouinpo\Suite\Core\ClassSubgroups::all($group_id);
                foreach ($class_subgroups as &$class_subgroup) {
                    $class_subgroup['members'] = array_values(array_diff($class_subgroup['members'], [$uid]));
                }
                unset($class_subgroup);
                \Ouinpo\Suite\Core\ClassSubgroups::save($group_id, $class_subgroups);
            }
        }
        add_settings_error('ouinpo_assign', 'removed', 'Élèves retirés de la classe.', 'updated');
    }
    if (isset($_POST['override_level']) && is_array($_POST['override_level'])) {
        foreach ($_POST['override_level'] as $uid => $lev) {
            $uid = intval($uid);
            $lev = ($lev === '' ? null : intval($lev));
            $wpdb->update($tbl_members,
                ['school_level_id_override' => $lev],
                ['group_id'=>$group_id, 'user_id'=>$uid],
                ['%d'], ['%d','%d']
            );
        }
        add_settings_error('ouinpo_assign', 'override', 'Surcharges de niveau enregistrées.', 'updated');
    }
}

// Fetch groups & selected group info
$groups = $wpdb->get_results("SELECT id, label FROM {$tbl_groups} ORDER BY created_at DESC");
$current_group = null;
if ($group_id) {
    $current_group = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_groups} WHERE id=%d", $group_id));
    if (!$current_group) $group_id = 0;
}

// Users
// Récupère tous les utilisateurs "élèves" (par défaut tous sauf roles admin/editor, ajuste si tu as un rôle dédié)
$args_all = [
    'fields' => ['ID','display_name','user_email'],
    'number' => 9999,
    'orderby' => 'display_name',
    'order' => 'ASC',
    // 'role__in' => ['subscriber'], // décommente si tu as un rôle spécifique
];
$all_users = \Ouinpo\Suite\Core\StudentName::getUsers($args_all);

// Seuls les élèves sans aucune affectation sont proposés à l'ajout.
$assigned_users = array_fill_keys(array_map('intval', $wpdb->get_col("SELECT DISTINCT user_id FROM {$tbl_members}")), true);
$available_users = [];
update_meta_cache('user', array_map(static fn($user) => (int) $user->ID, $all_users));
foreach ($all_users as $user) {
    if (isset($assigned_users[(int) $user->ID]) || !$learning_policy->canBeAssignedToClass((int) $user->ID)) {
        continue;
    }
    $name = \Ouinpo\Suite\Core\StudentName::format($user);
    $available_users[] = [
        'id' => (int) $user->ID,
        'label' => $name . ' <' . $user->user_email . '>',
    ];
}
usort($available_users, static fn($a, $b) => strnatcasecmp(remove_accents($a['label']), remove_accents($b['label'])));

// Membres de la classe sélectionnée
$members = [];
if ($group_id) {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT user_id, school_level_id_override FROM {$tbl_members} WHERE group_id=%d", $group_id));
    foreach ($rows as $r) {
        $members[intval($r->user_id)] = intval($r->school_level_id_override ?: 0);
    }
}

$levels = $wpdb->get_results("SELECT id, label FROM {$tbl_levels} ORDER BY sort_order ASC, id ASC");
$levels_map = [];
foreach ($levels as $l) $levels_map[intval($l->id)] = $l->label;

$subgroups = \Ouinpo\Suite\Core\ClassSubgroups::all($group_id);
if ($current_group && isset($_POST['subgroup_action']) && check_admin_referer('ouinpo_assign_form', 'ouinpo_assign_nonce')) {
    $subgroup_action = sanitize_key($_POST['subgroup_action']);
    if ($subgroup_action === 'create') {
        $label = sanitize_text_field(wp_unslash($_POST['subgroup_label'] ?? ''));
        if ($label !== '') {
            $subgroups[wp_generate_uuid4()] = ['label' => $label, 'members' => []];
        }
    } elseif ($subgroup_action === 'save') {
        $remove = array_map('sanitize_key', (array) ($_POST['remove_subgroups'] ?? []));
        foreach ($subgroups as $id => &$subgroup) {
            $selected = array_map('intval', (array) ($_POST['subgroup_members'][$id] ?? []));
            $subgroup['members'] = array_values(array_intersect(array_keys($members), $selected));
        }
        unset($subgroup);
        foreach ($remove as $id) unset($subgroups[$id]);
    }
    \Ouinpo\Suite\Core\ClassSubgroups::save($group_id, $subgroups);
    add_settings_error('ouinpo_assign', 'subgroups', 'Groupes de la classe enregistrés.', 'updated');
}

settings_errors('ouinpo_assign');
?>
<div class="wrap">
  <h1 class="wp-heading-inline">Affectations</h1>
  <hr class="wp-header-end"/>

  <form method="get" class="ouinpo-admin-picker-form">
    <input type="hidden" name="page" value="ouinpo-assignments">
    <label for="group_id" class="ouinpo-admin-label-spaced">Choisir une classe :</label>
    <select name="group_id" id="group_id">
      <option value="">—</option>
      <?php foreach ($groups as $g): ?>
        <option value="<?php echo intval($g->id); ?>" <?php selected($group_id, intval($g->id)); ?>>
          <?php echo esc_html($g->label); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php submit_button('Afficher', 'secondary', '', false); ?>
    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-groups')); ?>">Gérer les classes</a>
  </form>

  <?php if (!$group_id): ?>
    <p>Sélectionne une classe pour gérer ses élèves.</p>
  <?php else: ?>
    <h2><?php echo esc_html($current_group->label); ?></h2>
    <div class="notice notice-info"><p>Astuce : tu peux ajouter/retirer en masse puis enregistrer. Tu peux aussi surcharger le <em>niveau</em> pour un élève (utile si un élève de Première suit des exos de Seconde).</p></div>

    <div class="ouinpo-admin-grid-two">
      <div>
        <h3>Élèves sans classe</h3>
        <form method="post">
          <?php wp_nonce_field('ouinpo_assign_form','ouinpo_assign_nonce'); ?>
          <input type="hidden" name="group_id" value="<?php echo intval($group_id); ?>">
          <select name="add_users[]" multiple size="18" class="ouinpo-admin-full-width">
            <?php foreach ($available_users as $available_user): ?>
              <option value="<?php echo $available_user['id']; ?>">
                <?php echo esc_html($available_user['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p><?php submit_button('Ajouter dans la classe →', 'primary', '', false); ?></p>
        </form>
      </div>

      <div>
        <h3>Membres de la classe</h3>
        <form method="post">
          <?php wp_nonce_field('ouinpo_assign_form','ouinpo_assign_nonce'); ?>
          <input type="hidden" name="group_id" value="<?php echo intval($group_id); ?>">

          <table class="widefat fixed striped">
            <thead>
              <tr>
                <th class="ouinpo-admin-col-remove">Retirer</th>
                <th>Élève</th>
                <th>E-mail</th>
                <th class="ouinpo-admin-col-override">Niveau surchargé</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $has_member = false;
            foreach ($all_users as $u):
              if (!isset($members[$u->ID])) continue;
              $has_member = true;
              $override = intval($members[$u->ID]);
            ?>
              <tr>
                <td class="ouinpo-admin-cell-centered">
                  <label><input type="checkbox" name="remove_users[]" value="<?php echo intval($u->ID); ?>"></label>
                </td>
                <td><?php echo esc_html(\Ouinpo\Suite\Core\StudentName::format($u)); ?></td>
                <td><?php echo esc_html($u->user_email); ?></td>
                <td>
                  <select name="override_level[<?php echo intval($u->ID); ?>]" class="ouinpo-admin-select-override">
                    <option value="">— (par défaut de la classe)</option>
                    <?php foreach ($levels as $l): ?>
                      <option value="<?php echo intval($l->id); ?>" <?php selected($override, intval($l->id)); ?>>
                        <?php echo esc_html($l->label); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            <?php endforeach; if (!$has_member): ?>
              <tr><td colspan="4">Aucun élève dans cette classe.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>

          <p class="ouinpo-admin-actions-row ouinpo-admin-actions-row--loose">
            <?php submit_button('Enregistrer les surcharges', 'secondary', '', false); ?>
            <?php submit_button('Retirer les cochés', 'delete', '', false); ?>
          </p>
        </form>
      </div>
    </div>
    <h2>Groupes de la classe (facultatifs)</h2>
    <p>Vous pouvez conserver la classe entière sans créer de groupe, créer un seul groupe ou plusieurs. L’accès « Toute la classe » reste toujours disponible pour les ressources.</p>
    <form method="post">
      <?php wp_nonce_field('ouinpo_assign_form', 'ouinpo_assign_nonce'); ?>
      <input type="hidden" name="group_id" value="<?php echo (int) $group_id; ?>">
      <input type="hidden" name="subgroup_action" value="create">
      <label>Nom du groupe <input name="subgroup_label" required placeholder="Groupe 1"></label>
      <?php submit_button('Créer le groupe', 'secondary', '', false); ?>
    </form>
    <?php if ($subgroups): ?>
    <form method="post">
      <?php wp_nonce_field('ouinpo_assign_form', 'ouinpo_assign_nonce'); ?>
      <input type="hidden" name="group_id" value="<?php echo (int) $group_id; ?>">
      <input type="hidden" name="subgroup_action" value="save">
      <?php foreach ($subgroups as $id => $subgroup): ?>
        <fieldset>
          <legend><strong><?php echo esc_html($subgroup['label']); ?></strong></legend>
          <?php foreach ($all_users as $u): if (!isset($members[$u->ID])) continue; ?>
            <label style="display:block"><input type="checkbox" name="subgroup_members[<?php echo esc_attr($id); ?>][]" value="<?php echo (int) $u->ID; ?>" <?php checked(in_array((int) $u->ID, $subgroup['members'], true)); ?>> <?php echo esc_html(\Ouinpo\Suite\Core\StudentName::format($u)); ?></label>
          <?php endforeach; ?>
          <p><label><input type="checkbox" name="remove_subgroups[]" value="<?php echo esc_attr($id); ?>"> Supprimer ce groupe (les ressources associées ne seront pas ouvertes à toute la classe)</label></p>
        </fieldset>
      <?php endforeach; ?>
      <?php submit_button('Enregistrer les groupes'); ?>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
