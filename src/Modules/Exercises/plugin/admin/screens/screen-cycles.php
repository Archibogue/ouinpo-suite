<?php

namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;
use Ouinpo\Suite\Core\School\CycleRepository;
use Ouinpo\Suite\Core\School\CycleRetentionPolicyRepository;

defined('ABSPATH') || exit;

if (!Capabilities::can(Capabilities::MANAGE_CLASSES)) {
    wp_die('Acces refuse.');
}

$cyclesRepo = new CycleRepository();
$policyRepo = new CycleRetentionPolicyRepository();

$action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';
$cycleId = isset($_GET['id']) ? absint($_GET['id']) : 0;

if (!empty($_POST) && check_admin_referer('ouinpo_cycles_form', 'ouinpo_cycles_nonce')) {
    $postAction = isset($_POST['action']) ? sanitize_key(wp_unslash((string) $_POST['action'])) : '';
    $postId = isset($_POST['id']) ? absint($_POST['id']) : 0;

    if ($postAction === 'save') {
        $savedId = $cyclesRepo->saveCycle([
            'label' => isset($_POST['label']) ? sanitize_text_field(wp_unslash((string) $_POST['label'])) : '',
            'slug' => isset($_POST['slug']) ? sanitize_title(wp_unslash((string) $_POST['slug'])) : '',
            'description' => isset($_POST['description']) ? wp_kses_post(wp_unslash((string) $_POST['description'])) : '',
            'duration_years' => isset($_POST['duration_years']) ? absint($_POST['duration_years']) : 0,
            'portfolio_enabled' => !empty($_POST['portfolio_enabled']) ? 1 : 0,
            'status' => isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'active',
        ], $postId);

        if ($savedId > 0) {
            add_settings_error('ouinpo_cycles', 'saved', $postId > 0 ? 'Cycle mis a jour.' : 'Cycle cree.', 'updated');
            $action = '';
            $cycleId = 0;
        } else {
            add_settings_error('ouinpo_cycles', 'save_failed', 'Impossible d enregistrer ce cycle.', 'error');
            $action = $postId > 0 ? 'edit' : 'new';
            $cycleId = $postId;
        }
    }

    if ($postAction === 'status' && $postId > 0) {
        $status = isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'inactive';
        $cyclesRepo->setCycleStatus($postId, $status);
        add_settings_error('ouinpo_cycles', 'status', 'Statut du cycle mis a jour.', 'updated');
        $action = '';
        $cycleId = 0;
    }

    if ($postAction === 'default_policies' && $postId > 0) {
        $cycle = $cyclesRepo->getCycle($postId);
        $created = $policyRepo->ensureDefaults($postId, !empty($cycle['portfolio_enabled']));
        add_settings_error('ouinpo_cycles', 'policies', sprintf('%d politique(s) par defaut creee(s).', $created), 'updated');
        $action = 'edit';
        $cycleId = $postId;
    }
}

$cycles = $cyclesRepo->listCycles(true);
$current = [
    'id' => 0,
    'label' => '',
    'slug' => '',
    'description' => '',
    'duration_years' => '',
    'portfolio_enabled' => 0,
    'status' => 'active',
];

if ($action === 'edit' && $cycleId > 0) {
    $row = $cyclesRepo->getCycle($cycleId);
    if ($row) {
        $current = array_merge($current, $row);
    } else {
        $action = '';
        $cycleId = 0;
    }
}

if (!empty($_POST) && ($action === 'new' || $action === 'edit')) {
    $current = [
        'id' => isset($_POST['id']) ? absint($_POST['id']) : 0,
        'label' => isset($_POST['label']) ? sanitize_text_field(wp_unslash((string) $_POST['label'])) : '',
        'slug' => isset($_POST['slug']) ? sanitize_title(wp_unslash((string) $_POST['slug'])) : '',
        'description' => isset($_POST['description']) ? wp_kses_post(wp_unslash((string) $_POST['description'])) : '',
        'duration_years' => isset($_POST['duration_years']) ? absint($_POST['duration_years']) : '',
        'portfolio_enabled' => !empty($_POST['portfolio_enabled']) ? 1 : 0,
        'status' => isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'active',
    ];
}

settings_errors('ouinpo_cycles');
?>
<div class="wrap">
  <h1 class="wp-heading-inline">Cycles pedagogiques</h1>
  <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-cycles&action=new')); ?>">Ajouter</a>
  <hr class="wp-header-end">

  <div class="notice notice-info">
    <p>Cette version ne supprime aucune donnee. Elle prepare la cloture, les roles, les cycles et la simulation des purges.</p>
  </div>

  <div class="ouinpo-admin-layout">
    <div class="ouinpo-admin-layout-main">
      <h2 class="title">Liste des cycles</h2>
      <table class="widefat fixed striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Libelle</th>
            <th>Slug</th>
            <th>Duree</th>
            <th>Portfolio</th>
            <th>Statut</th>
            <th>Niveaux</th>
            <th>Politiques</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$cycles): ?>
            <tr><td colspan="9">Aucun cycle.</td></tr>
          <?php else: ?>
            <?php foreach ($cycles as $cycle): ?>
              <?php
              $levels = $cyclesRepo->listLevelsForCycle((int) $cycle['id']);
              $policies = $policyRepo->listForCycle((int) $cycle['id']);
              ?>
              <tr>
                <td><?php echo (int) $cycle['id']; ?></td>
                <td><strong><?php echo esc_html((string) $cycle['label']); ?></strong></td>
                <td><code><?php echo esc_html((string) $cycle['slug']); ?></code></td>
                <td><?php echo !empty($cycle['duration_years']) ? (int) $cycle['duration_years'] . ' an(s)' : '-'; ?></td>
                <td><?php echo !empty($cycle['portfolio_enabled']) ? 'Oui' : 'Non'; ?></td>
                <td><?php echo esc_html((string) $cycle['status']); ?></td>
                <td>
                  <?php echo $levels ? esc_html(implode(', ', array_map(static fn($level) => (string) $level['label'], $levels))) : '-'; ?>
                </td>
                <td><?php echo (int) count($policies); ?></td>
                <td>
                  <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-cycles&action=edit&id=' . (int) $cycle['id'])); ?>">Modifier</a>
                  <form method="post" class="ouinpo-admin-inline-form">
                    <?php wp_nonce_field('ouinpo_cycles_form', 'ouinpo_cycles_nonce'); ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?php echo (int) $cycle['id']; ?>">
                    <input type="hidden" name="status" value="<?php echo esc_attr((string) $cycle['status'] === 'active' ? 'inactive' : 'active'); ?>">
                    <button type="submit" class="button button-small"><?php echo (string) $cycle['status'] === 'active' ? 'Desactiver' : 'Activer'; ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="ouinpo-admin-layout-side ouinpo-admin-layout-side--sticky">
      <h2 class="title"><?php echo !empty($current['id']) ? 'Modifier le cycle' : 'Nouveau cycle'; ?></h2>
      <form method="post">
        <?php wp_nonce_field('ouinpo_cycles_form', 'ouinpo_cycles_nonce'); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php echo (int) $current['id']; ?>">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="label">Libelle</label></th>
            <td><input type="text" name="label" id="label" class="regular-text" required value="<?php echo esc_attr((string) $current['label']); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label for="slug">Slug</label></th>
            <td><input type="text" name="slug" id="slug" class="regular-text" maxlength="120" value="<?php echo esc_attr((string) $current['slug']); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label for="description">Description</label></th>
            <td><textarea name="description" id="description" class="large-text" rows="4"><?php echo esc_textarea((string) $current['description']); ?></textarea></td>
          </tr>
          <tr>
            <th scope="row"><label for="duration_years">Duree</label></th>
            <td><input type="number" name="duration_years" id="duration_years" min="1" max="20" class="small-text" value="<?php echo esc_attr((string) $current['duration_years']); ?>"> an(s)</td>
          </tr>
          <tr>
            <th scope="row">Portfolio</th>
            <td><label><input type="checkbox" name="portfolio_enabled" value="1" <?php checked(!empty($current['portfolio_enabled'])); ?>> Portfolio de cycle active</label></td>
          </tr>
          <tr>
            <th scope="row"><label for="status">Statut</label></th>
            <td>
              <select name="status" id="status">
                <?php foreach (['active', 'inactive', 'archived'] as $status): ?>
                  <option value="<?php echo esc_attr($status); ?>" <?php selected((string) $current['status'], $status); ?>><?php echo esc_html($status); ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        </table>

        <?php submit_button(!empty($current['id']) ? 'Enregistrer' : 'Creer le cycle'); ?>
      </form>

      <?php if (!empty($current['id'])): ?>
        <hr>
        <h3>Niveaux rattaches</h3>
        <?php $currentLevels = $cyclesRepo->listLevelsForCycle((int) $current['id']); ?>
        <?php if ($currentLevels): ?>
          <ul>
            <?php foreach ($currentLevels as $level): ?>
              <li><?php echo esc_html((string) $level['label']); ?> <?php echo !empty($level['cycle_rank']) ? '(rang ' . (int) $level['cycle_rank'] . ')' : ''; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p>Aucun niveau rattache.</p>
        <?php endif; ?>

        <h3>Politiques de conservation</h3>
        <?php $currentPolicies = $policyRepo->listForCycle((int) $current['id']); ?>
        <?php if (!$currentPolicies): ?>
          <form method="post">
            <?php wp_nonce_field('ouinpo_cycles_form', 'ouinpo_cycles_nonce'); ?>
            <input type="hidden" name="action" value="default_policies">
            <input type="hidden" name="id" value="<?php echo (int) $current['id']; ?>">
            <?php submit_button('Creer les politiques par defaut', 'secondary', 'submit', false); ?>
          </form>
        <?php else: ?>
          <table class="widefat striped">
            <thead><tr><th>Domaine</th><th>Meme cycle</th><th>Sortie</th><th>Alumni</th></tr></thead>
            <tbody>
              <?php foreach ($currentPolicies as $policy): ?>
                <tr>
                  <td><?php echo esc_html((string) $policy['data_domain']); ?></td>
                  <td><?php echo esc_html((string) $policy['action_same_cycle']); ?></td>
                  <td><?php echo esc_html((string) $policy['action_cycle_exit']); ?></td>
                  <td><?php echo esc_html((string) $policy['alumni_access']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
