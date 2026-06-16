<?php

namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;
use Ouinpo\Suite\Core\School\YearClosureExecutor;
use Ouinpo\Suite\Core\School\YearClosurePlanner;

defined('ABSPATH') || exit;

if (!Capabilities::can(Capabilities::MANAGE_CLASSES)) {
    wp_die('Acces refuse.');
}

$planner = new YearClosurePlanner();
$result = null;
$executed = null;

$options = [
    'to_year_slug' => isset($_POST['to_year_slug']) ? sanitize_text_field(wp_unslash((string) $_POST['to_year_slug'])) : '',
    'to_year_starts_on' => isset($_POST['to_year_starts_on']) ? sanitize_text_field(wp_unslash((string) $_POST['to_year_starts_on'])) : '',
    'to_year_ends_on' => isset($_POST['to_year_ends_on']) ? sanitize_text_field(wp_unslash((string) $_POST['to_year_ends_on'])) : '',
];

if (!empty($_POST) && check_admin_referer('ouinpo_year_closure_form', 'ouinpo_year_closure_nonce')) {
    $action = isset($_POST['action']) ? sanitize_key(wp_unslash((string) $_POST['action'])) : '';
    $fromYearId = isset($_POST['from_year_id']) ? absint($_POST['from_year_id']) : 0;

    if ($action === 'analyze') {
        $result = $planner->plan($fromYearId, $options, true);
        add_settings_error('ouinpo_year_closure', 'analyzed', 'Simulation de cloture generee.', 'updated');
    }

    if ($action === 'execute') {
        $expected = isset($_POST['expected_confirmation']) ? sanitize_text_field(wp_unslash((string) $_POST['expected_confirmation'])) : '';
        $typed = isset($_POST['confirmation_text']) ? sanitize_text_field(wp_unslash((string) $_POST['confirmation_text'])) : '';
        $checked = !empty($_POST['confirm_non_destructive']);

        if (!$checked || $typed !== $expected) {
            add_settings_error('ouinpo_year_closure', 'confirm_failed', 'Confirmation de cloture incorrecte.', 'error');
            $result = $planner->plan($fromYearId, $options, false);
        } else {
            $executed = (new YearClosureExecutor())->execute($fromYearId, $options);
            $result = $planner->plan(0, [], false);
            add_settings_error(
                'ouinpo_year_closure',
                !empty($executed['ok']) ? 'executed' : 'execute_failed',
                !empty($executed['ok']) ? 'Cloture non destructive executee.' : 'Execution impossible : ' . sanitize_text_field((string) ($executed['error'] ?? 'erreur inconnue')),
                !empty($executed['ok']) ? 'updated' : 'error'
            );
        }
    }
}

if ($result === null) {
    $result = $planner->plan(0, [], false);
}

$summary = (array) ($result['summary'] ?? []);
$fromYear = (array) ($summary['from_year'] ?? []);
$toYear = (array) ($summary['to_year'] ?? []);
$expected = 'CLOTURER ' . (string) ($fromYear['slug'] ?? '');

settings_errors('ouinpo_year_closure');
?>
<div class="wrap">
  <h1>Cloture annuelle</h1>

  <div class="notice notice-info">
    <p>Cette version ne supprime aucune donnee. Elle prepare la cloture, les roles, les cycles et la simulation des purges.</p>
  </div>

  <?php if (!empty($summary['error'])): ?>
    <div class="notice notice-error"><p>Aucune annee active detectee.</p></div>
  <?php else: ?>
    <form method="post" class="ouinpo-admin-panel">
      <?php wp_nonce_field('ouinpo_year_closure_form', 'ouinpo_year_closure_nonce'); ?>
      <input type="hidden" name="action" value="analyze">
      <input type="hidden" name="from_year_id" value="<?php echo (int) ($fromYear['id'] ?? 0); ?>">

      <h2>Annees</h2>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">Annee active actuelle</th>
          <td><strong><?php echo esc_html((string) ($fromYear['slug'] ?? '-')); ?></strong></td>
        </tr>
        <tr>
          <th scope="row"><label for="to_year_slug">Annee suivante</label></th>
          <td>
            <input type="text" name="to_year_slug" id="to_year_slug" value="<?php echo esc_attr((string) ($toYear['slug'] ?? '')); ?>" pattern="\d{4}-\d{4}">
            <input type="date" name="to_year_starts_on" value="<?php echo esc_attr((string) ($toYear['starts_on'] ?? '')); ?>">
            <input type="date" name="to_year_ends_on" value="<?php echo esc_attr((string) ($toYear['ends_on'] ?? '')); ?>">
            <p class="description"><?php echo !empty($summary['to_year_exists']) ? 'Annee cible existante.' : 'Annee cible a creer.'; ?></p>
          </td>
        </tr>
      </table>
      <?php submit_button('Analyser la cloture', 'secondary'); ?>
    </form>

    <h2>Simulation</h2>
    <table class="widefat fixed striped">
      <thead>
        <tr>
          <th>Classe</th>
          <th>Niveau actuel</th>
          <th>Niveau suivant</th>
          <th>Eleves</th>
          <th>Transition</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ((array) ($summary['class_plans'] ?? []) as $classPlan): ?>
          <?php $transition = (array) ($classPlan['transition'] ?? []); ?>
          <tr>
            <td><?php echo esc_html((string) ($classPlan['source_label'] ?? '')); ?></td>
            <td><?php echo esc_html((string) ($classPlan['from_level_label'] ?? '-')); ?></td>
            <td><?php echo esc_html((string) (($classPlan['to_level_label'] ?? '') ?: 'Alumni / sortie')); ?></td>
            <td><?php echo (int) ($classPlan['students_count'] ?? 0); ?></td>
            <td>
              <?php
              if (!empty($classPlan['from_level_id']) && empty($classPlan['from_cycle_id'])) {
                  echo 'niveau sans cycle';
              } elseif (!empty($transition['is_redoublement'])) {
                  echo 'redoublement';
              } elseif (!empty($transition['stays_in_same_cycle'])) {
                  echo 'meme cycle';
              } elseif (!empty($transition['enters_new_cycle'])) {
                  echo 'changement cycle';
              } else {
                  echo 'sortie cycle';
              }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h2>Resume</h2>
    <ul>
      <li>Classes a creer : <?php echo (int) ($summary['classes_to_create'] ?? 0); ?></li>
      <li>Classes a archiver : <?php echo (int) ($summary['classes_to_archive'] ?? 0); ?></li>
      <li>Eleves promus : <?php echo (int) ($summary['students_promoted'] ?? 0); ?></li>
      <li>Eleves restant dans le meme cycle : <?php echo (int) ($summary['students_same_cycle'] ?? 0); ?></li>
      <li>Eleves sortant d un cycle : <?php echo (int) ($summary['students_cycle_exit'] ?? 0); ?></li>
      <li>Eleves a convertir alumni : <?php echo (int) ($summary['students_to_alumni'] ?? 0); ?></li>
      <li>Projets actifs a reporter : <?php echo (int) ($summary['active_projects_to_carry'] ?? 0); ?></li>
      <li>Projets explicitement rattaches : <?php echo (int) ($summary['explicit_projects_to_carry'] ?? 0); ?></li>
      <li>Projets legacy detectes par membres : <?php echo (int) ($summary['legacy_member_projects_to_carry'] ?? 0); ?></li>
      <li>Projets portfolio a preserver : <?php echo (int) ($summary['portfolio_projects_to_preserve'] ?? 0); ?></li>
      <li>Acces archive alumni a preparer : <?php echo (int) ($summary['alumni_archive_projects_to_prepare'] ?? 0); ?></li>
      <li>Donnees annuelles a reinitialiser plus tard : <?php echo (int) ($summary['annual_data_to_reset_later'] ?? 0); ?></li>
      <li>Purges RGPD planifiees, non executees : <?php echo (int) ($summary['gdpr_purges_planned'] ?? 0); ?></li>
    </ul>

    <form method="post" class="ouinpo-admin-panel">
      <?php wp_nonce_field('ouinpo_year_closure_form', 'ouinpo_year_closure_nonce'); ?>
      <input type="hidden" name="action" value="execute">
      <input type="hidden" name="from_year_id" value="<?php echo (int) ($fromYear['id'] ?? 0); ?>">
      <input type="hidden" name="to_year_slug" value="<?php echo esc_attr((string) ($toYear['slug'] ?? '')); ?>">
      <input type="hidden" name="to_year_starts_on" value="<?php echo esc_attr((string) ($toYear['starts_on'] ?? '')); ?>">
      <input type="hidden" name="to_year_ends_on" value="<?php echo esc_attr((string) ($toYear['ends_on'] ?? '')); ?>">
      <input type="hidden" name="expected_confirmation" value="<?php echo esc_attr($expected); ?>">

      <h2>Execution non destructive</h2>
      <p>Recopier <code><?php echo esc_html($expected); ?></code> pour confirmer.</p>
      <p><label><input type="checkbox" name="confirm_non_destructive" value="1"> Je confirme l execution non destructive.</label></p>
      <p><input type="text" name="confirmation_text" class="regular-text" autocomplete="off"></p>
      <?php submit_button('Executer la cloture non destructive', 'primary'); ?>
    </form>
  <?php endif; ?>
</div>
