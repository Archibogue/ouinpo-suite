<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;

if (!defined('ABSPATH')) exit;

if (!Capabilities::can(Capabilities::MANAGE_CLASSES)) {
    wp_die('Acces refuse.');
}

global $wpdb;

$tbl_levels       = $wpdb->prefix . 'ouin_exo_school_levels';
$tbl_groups       = $wpdb->prefix . 'ouin_exo_groups';
$tbl_members      = $wpdb->prefix . 'ouin_exo_group_members';
$tbl_exercises    = $wpdb->prefix . 'ouin_exo_exercises';
$tbl_exo_levels   = $wpdb->prefix . 'ouin_exo_exercise_school_level';
$tbl_comp_levels  = $wpdb->prefix . 'ouin_exo_competency_school_level';
$tbl_cycles       = $wpdb->prefix . 'ouinpo_cycles';

$has_sort_order = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$tbl_levels} LIKE %s", 'sort_order'));
$has_cycle_id = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$tbl_levels} LIKE %s", 'cycle_id'));
$has_cycle_rank = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$tbl_levels} LIKE %s", 'cycle_rank'));
$has_is_cycle_terminal = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$tbl_levels} LIKE %s", 'is_cycle_terminal'));
$has_default_next_level_id = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$tbl_levels} LIKE %s", 'default_next_level_id'));
$table_exists = static function (string $table) use ($wpdb): bool {
    return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
};

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

$usage_for_level = static function (int $id) use ($wpdb, $table_exists, $tbl_groups, $tbl_members, $tbl_exercises, $tbl_exo_levels, $tbl_comp_levels): array {
    return [
        'groups' => $table_exists($tbl_groups) ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_groups} WHERE school_level_id = %d",
            $id
        )) : 0,
        'members' => ($table_exists($tbl_members) && $table_exists($tbl_groups)) ? count(\Ouinpo\Suite\Core\Privacy\LearningAudiencePolicy::filterClassStudentIds($wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT gm.user_id
               FROM {$tbl_members} gm
               LEFT JOIN {$tbl_groups} g ON g.id = gm.group_id
              WHERE gm.role = 'student'
                AND COALESCE(gm.school_level_id_override, g.school_level_id) = %d",
            $id
        )) ?: [])) : 0,
        'exercises_legacy' => $table_exists($tbl_exercises) ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_exercises} WHERE level_id = %d",
            $id
        )) : 0,
        'exercises_links' => $table_exists($tbl_exo_levels) ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_exo_levels} WHERE school_level_id = %d",
            $id
        )) : 0,
        'competencies_links' => $table_exists($tbl_comp_levels) ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_comp_levels} WHERE school_level_id = %d",
            $id
        )) : 0,
    ];
};

$sync_level_competencies = static function (int $level_id, array $competency_ids) use ($wpdb, $table_exists, $tbl_comp_levels): void {
    if ($level_id <= 0) {
        return;
    }

    if (!$table_exists($tbl_comp_levels)) {
        return;
    }

    $clean_ids = [];
    foreach ($competency_ids as $competency_id) {
        $competency_id = (int) $competency_id;
        if ($competency_id > 0) {
            $clean_ids[$competency_id] = true;
        }
    }

    $wpdb->delete($tbl_comp_levels, ['school_level_id' => $level_id], ['%d']);

    foreach (array_keys($clean_ids) as $competency_id) {
        $wpdb->insert(
            $tbl_comp_levels,
            [
                'competency_id'   => $competency_id,
                'school_level_id' => $level_id,
            ],
            ['%d', '%d']
        );
    }
};

if (!empty($_POST) && check_admin_referer('ouinpo_levels_form', 'ouinpo_levels_nonce')) {
    $post_action = isset($_POST['action']) ? sanitize_key(wp_unslash((string) $_POST['action'])) : '';
    $post_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($post_action === 'save_settings') {
        update_option(
            'ouinpo_exercises_cumulative_school_levels',
            !empty($_POST['cumulative_school_levels']) ? '1' : '0',
            false
        );
        add_settings_error('ouinpo_levels', 'settings_updated', 'Reglage des niveaux enregistres.', 'updated');
        $action = '';
        $level_id = 0;
    }

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
        $sort_order = isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : 0;

        $data = [
            'slug'       => $slug,
            'label'      => $label,
        ];
        $formats = ['%s', '%s'];

        if ($has_sort_order) {
            $data['sort_order'] = $sort_order;
            $formats[] = '%d';
        }

        if ($has_cycle_id) {
            $cycle_id = isset($_POST['cycle_id']) ? absint($_POST['cycle_id']) : 0;
            $data['cycle_id'] = $cycle_id > 0 ? $cycle_id : null;
            $formats[] = '%d';
        }

        if ($has_cycle_rank) {
            $cycle_rank = isset($_POST['cycle_rank']) ? absint($_POST['cycle_rank']) : 0;
            $data['cycle_rank'] = $cycle_rank > 0 ? $cycle_rank : null;
            $formats[] = '%d';
        }

        if ($has_is_cycle_terminal) {
            $data['is_cycle_terminal'] = !empty($_POST['is_cycle_terminal']) ? 1 : 0;
            $formats[] = '%d';
        }

        if ($has_default_next_level_id) {
            $next_level_id = isset($_POST['default_next_level_id']) ? absint($_POST['default_next_level_id']) : 0;
            $data['default_next_level_id'] = $next_level_id > 0 ? $next_level_id : null;
            $formats[] = '%d';
        }

            if ($post_id > 0) {
                $updated = $wpdb->update($tbl_levels, $data, ['id' => $post_id], $formats, ['%d']);
                if ($updated === false) {
                    add_settings_error('ouinpo_levels', 'db_update_failed', 'Impossible de mettre a jour ce niveau.', 'error');
                    $action = 'edit';
                    $level_id = $post_id;
                } else {
                    $posted_competencies = isset($_POST['competency_ids']) && is_array($_POST['competency_ids'])
                        ? array_map('intval', wp_unslash($_POST['competency_ids']))
                        : [];
                    $sync_level_competencies($post_id, $posted_competencies);
                    add_settings_error('ouinpo_levels', 'updated', 'Niveau mis a jour.', 'updated');
                    $action = '';
                    $level_id = 0;
                }
            } else {
                $inserted = $wpdb->insert($tbl_levels, $data, $formats);
                $new_level_id = (int) $wpdb->insert_id;
                if ($inserted === false || $new_level_id <= 0) {
                    add_settings_error('ouinpo_levels', 'db_insert_failed', 'Impossible de creer ce niveau.', 'error');
                    $action = 'new';
                    $level_id = 0;
                } else {
                    $posted_competencies = isset($_POST['competency_ids']) && is_array($_POST['competency_ids'])
                        ? array_map('intval', wp_unslash($_POST['competency_ids']))
                        : [];
                    $sync_level_competencies($new_level_id, $posted_competencies);
                    add_settings_error('ouinpo_levels', 'created', 'Niveau cree.', 'updated');
                    $action = '';
                    $level_id = 0;
                }
            }
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
        $total_usage = (int) $usage['groups']
            + (int) $usage['members']
            + (int) $usage['exercises_legacy']
            + (int) $usage['exercises_links']
            + (int) $usage['competencies_links'];

        if ($total_usage > 0) {
            $reasons = [];
            if ((int) $usage['groups'] > 0) {
                $reasons[] = (int) $usage['groups'] . ' classe(s)';
            }
            if ((int) $usage['members'] > 0) {
                $reasons[] = (int) $usage['members'] . ' eleve(s)';
            }
            if ((int) $usage['exercises_legacy'] + (int) $usage['exercises_links'] > 0) {
                $reasons[] = ((int) $usage['exercises_legacy'] + (int) $usage['exercises_links']) . ' exercice(s)';
            }
            if ((int) $usage['competencies_links'] > 0) {
                $reasons[] = (int) $usage['competencies_links'] . ' competence(s)';
            }
            add_settings_error(
                'ouinpo_levels',
                'delete_used',
                'Impossible de supprimer ce niveau : utilise par ' . implode(', ', $reasons) . '.',
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

$sort_order_select = $has_sort_order ? 'l.sort_order' : 'l.id * 10 AS sort_order';
$sort_order_sql = $has_sort_order ? 'l.sort_order ASC, l.id ASC' : 'l.id ASC';

$levels = $wpdb->get_results("
    SELECT l.*, {$sort_order_select}
    FROM {$tbl_levels} l
    ORDER BY {$sort_order_sql}
");

$cycles = $table_exists($tbl_cycles) ? $wpdb->get_results("
    SELECT id, label, slug
    FROM {$tbl_cycles}
    WHERE status <> 'archived'
    ORDER BY label ASC, id ASC
") : [];

$cycles_by_id = [];
foreach ((array) $cycles as $cycle) {
    $cycles_by_id[(int) $cycle->id] = $cycle;
}

foreach ((array) $levels as $level) {
    $usage = $usage_for_level((int) $level->id);
    $level->groups_count = (int) $usage['groups'];
    $level->members_count = (int) $usage['members'];
    $level->exercises_legacy_count = (int) $usage['exercises_legacy'];
    $level->exercises_links_count = (int) $usage['exercises_links'];
    $level->competencies_links_count = (int) $usage['competencies_links'];
}

$current = (object) [
    'id'    => 0,
    'slug'  => '',
    'label' => '',
    'sort_order' => 0,
    'cycle_id' => null,
    'cycle_rank' => null,
    'is_cycle_terminal' => 0,
    'default_next_level_id' => null,
];
$competencies = $wpdb->get_results("
    SELECT id, domain, competency, track, level
    FROM {$wpdb->prefix}ouin_exo_competencies
    WHERE IFNULL(active, 1) = 1
    ORDER BY
      track,
      domain,
      competency
");
$current_competency_ids = [];
$cumulative_school_levels = (bool) get_option('ouinpo_exercises_cumulative_school_levels', false);

if ($action === 'new') {
    $current_competency_ids = [];
}

if ($action === 'edit' && $level_id > 0) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_levels} WHERE id = %d", $level_id));
    if ($row) {
        if (!isset($row->sort_order)) {
            $row->sort_order = (int) $row->id * 10;
        }
        $current = $row;
        $current_competency_ids = $table_exists($tbl_comp_levels) ? array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT competency_id FROM {$tbl_comp_levels} WHERE school_level_id = %d",
            $level_id
        ))) : [];
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
        'sort_order' => isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : 0,
        'cycle_id' => isset($_POST['cycle_id']) ? absint($_POST['cycle_id']) : 0,
        'cycle_rank' => isset($_POST['cycle_rank']) ? absint($_POST['cycle_rank']) : 0,
        'is_cycle_terminal' => !empty($_POST['is_cycle_terminal']) ? 1 : 0,
        'default_next_level_id' => isset($_POST['default_next_level_id']) ? absint($_POST['default_next_level_id']) : 0,
    ];
    $current_competency_ids = isset($_POST['competency_ids']) && is_array($_POST['competency_ids'])
        ? array_map('intval', wp_unslash($_POST['competency_ids']))
        : [];
}

settings_errors('ouinpo_levels');
?>
<div class="wrap">
  <h1 class="wp-heading-inline">Niveaux scolaires</h1>
  <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-levels&action=new')); ?>">Ajouter</a>
  <hr class="wp-header-end"/>

  <div class="ouinpo-admin-layout">
    <div class="ouinpo-admin-layout-main">
      <h2 class="title">Reglages de progression</h2>
      <form method="post" class="ouinpo-admin-panel">
        <?php wp_nonce_field('ouinpo_levels_form', 'ouinpo_levels_nonce'); ?>
        <input type="hidden" name="action" value="save_settings">
        <fieldset>
          <label>
            <input
              type="checkbox"
              name="cumulative_school_levels"
              value="1"
              <?php checked($cumulative_school_levels); ?>
            >
            Activer les niveaux cumulatifs
          </label>
          <p class="description">
            Quand cette option est active, un eleve voit les contenus de son niveau et des niveaux dont l'ordre est inferieur ou egal.
          </p>
        </fieldset>
        <?php submit_button('Enregistrer le reglage', 'secondary', 'submit', false); ?>
      </form>

      <h2 class="title">Liste des niveaux</h2>
      <table class="widefat fixed striped">
        <thead>
          <tr>
            <th class="ouinpo-admin-col-id">ID</th>
            <th>Libelle</th>
            <th>Slug</th>
            <th>Ordre</th>
            <th>Cycle</th>
            <th>Rang</th>
            <th>Terminal</th>
            <th>Niveau suivant</th>
            <th>Classes</th>
            <th>Eleves</th>
            <th>Exercices</th>
            <th class="ouinpo-admin-col-competencies">Competences</th>
            <th class="ouinpo-admin-col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($levels)): ?>
            <tr><td colspan="13">Aucun niveau.</td></tr>
          <?php else: ?>
            <?php foreach ($levels as $level): ?>
              <?php
              $exercise_count = (int) $level->exercises_legacy_count + (int) $level->exercises_links_count;
              $competency_count = (int) $level->competencies_links_count;
              $total_usage = (int) $level->groups_count
                + (int) $level->members_count
                + (int) $level->exercises_legacy_count
                + (int) $level->exercises_links_count
                + $competency_count;
              ?>
              <tr>
                <td><?php echo (int) $level->id; ?></td>
                <td><strong><?php echo esc_html($level->label); ?></strong></td>
                <td><code><?php echo esc_html($level->slug); ?></code></td>
                <td><?php echo (int) $level->sort_order; ?></td>
                <td>
                  <?php
                  $cycle = !empty($level->cycle_id) && isset($cycles_by_id[(int) $level->cycle_id]) ? $cycles_by_id[(int) $level->cycle_id] : null;
                  echo $cycle ? esc_html((string) $cycle->label) : '<span class="description">Aucun</span>';
                  ?>
                  <?php if (!$cycle): ?>
                    <p class="description">Ce niveau ne participe pas encore aux clotures par cycle.</p>
                  <?php endif; ?>
                </td>
                <td><?php echo !empty($level->cycle_rank) ? (int) $level->cycle_rank : '-'; ?></td>
                <td><?php echo !empty($level->is_cycle_terminal) ? 'Oui' : 'Non'; ?></td>
                <td>
                  <?php
                  $nextLabel = '-';
                  if (!empty($level->default_next_level_id)) {
                      foreach ($levels as $candidate) {
                          if ((int) $candidate->id === (int) $level->default_next_level_id) {
                              $nextLabel = (string) $candidate->label;
                              break;
                          }
                      }
                  }
                  echo esc_html($nextLabel);
                  ?>
                </td>
                <td><?php echo (int) $level->groups_count; ?></td>
                <td><?php echo (int) $level->members_count; ?></td>
                <td><?php echo $exercise_count; ?></td>
                <td><?php echo $competency_count; ?></td>
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
          <tr>
            <th scope="row"><label for="sort_order">Ordre</label></th>
            <td>
              <input name="sort_order" id="sort_order" type="number" class="small-text" min="0" step="1" value="<?php echo esc_attr((string) (int) $current->sort_order); ?>">
              <p class="description">Utilise pour trier les niveaux et, si l'option cumulative est activee, definir la progression.</p>
            </td>
          </tr>
          <?php if ($has_cycle_id): ?>
          <tr>
            <th scope="row"><label for="cycle_id">Cycle associe</label></th>
            <td>
              <select name="cycle_id" id="cycle_id">
                <option value="">Aucun cycle</option>
                <?php foreach ((array) $cycles as $cycle): ?>
                  <option value="<?php echo (int) $cycle->id; ?>" <?php selected((int) ($current->cycle_id ?? 0), (int) $cycle->id); ?>>
                    <?php echo esc_html((string) $cycle->label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="description">Optionnel pour conserver la compatibilite avec les installations existantes.</p>
            </td>
          </tr>
          <?php endif; ?>
          <?php if ($has_cycle_rank): ?>
          <tr>
            <th scope="row"><label for="cycle_rank">Rang dans le cycle</label></th>
            <td><input name="cycle_rank" id="cycle_rank" type="number" class="small-text" min="0" step="1" value="<?php echo esc_attr((string) (int) ($current->cycle_rank ?? 0)); ?>"></td>
          </tr>
          <?php endif; ?>
          <?php if ($has_is_cycle_terminal): ?>
          <tr>
            <th scope="row">Niveau terminal</th>
            <td><label><input type="checkbox" name="is_cycle_terminal" value="1" <?php checked(!empty($current->is_cycle_terminal)); ?>> Niveau terminal du cycle</label></td>
          </tr>
          <?php endif; ?>
          <?php if ($has_default_next_level_id): ?>
          <tr>
            <th scope="row"><label for="default_next_level_id">Niveau suivant par defaut</label></th>
            <td>
              <select name="default_next_level_id" id="default_next_level_id">
                <option value="">Aucun / sortie de cycle</option>
                <?php foreach ((array) $levels as $nextLevel): ?>
                  <option value="<?php echo (int) $nextLevel->id; ?>" <?php selected((int) ($current->default_next_level_id ?? 0), (int) $nextLevel->id); ?>>
                    <?php echo esc_html((string) $nextLevel->label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endif; ?>
        </table>

        <h3>Competences associees</h3>
        <p class="description">Coche les competences disponibles pour ce niveau. Une competence associee a plusieurs niveaux sera consideree comme transversale.</p>
          <div class="ouinpo-admin-scroll-box">
            <?php if (empty($competencies)): ?>
              <p>Aucune competence active.</p>
            <?php else: ?>
              <?php foreach ($competencies as $competency): ?>
                <label class="ouinpo-admin-check-row">
                  <input
                    type="checkbox"
                    name="competency_ids[]"
                    value="<?php echo (int) $competency->id; ?>"
                    <?php checked(in_array((int) $competency->id, $current_competency_ids, true)); ?>
                  >
                  <span>
                    <strong><?php echo esc_html($competency->domain); ?></strong>
                    <?php echo esc_html(wp_trim_words((string) $competency->competency, 14)); ?>
                    <em><?php echo esc_html($competency->track . ' / ' . $competency->level); ?></em>
                  </span>
                </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

        <?php submit_button($current->id ? 'Enregistrer' : 'Creer le niveau'); ?>
      </form>
    </div>
  </div>
</div>
