<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Exercises\PathsService;

defined('ABSPATH') || exit;

class Screen_Paths
{
    private const PAGE_SLUG = 'ouinpo-paths';
    private const NONCE_ACTION = 'ouinpo_paths_form';
    private const NONCE_NAME = 'ouinpo_paths_nonce';

    private static function current_view(): string
    {
        $view = isset($_GET['view']) ? sanitize_key((string) $_GET['view']) : 'create';
        return in_array($view, ['create', 'models'], true) ? $view : 'create';
    }

    public static function render(): void
    {
        if (!current_user_can('edit_users')) {
            wp_die('Accès refusé');
        }

        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
            self::handle_post();
        }

        self::render_notices_from_query();
        settings_errors('ouinpo_paths');

        $view = self::current_view();

        $show_create_form = ($view === 'create');
        $show_models_list = ($view === 'models');

        $current_action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : '';
        $current_id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        $editing = ($current_action === 'edit' && $current_id > 0)
            ? PathsService::get_path($current_id)
            : null;

        $assigning = ($current_action === 'assign' && $current_id > 0)
            ? PathsService::get_path($current_id)
            : null;

        if ($assigning && empty($assigning['is_template'])) {
            $assigning = null;
        }

        $groups    = PathsService::get_groups();
        $students  = PathsService::get_students();
        $exercises = PathsService::get_exercises();
        $paths     = PathsService::list_paths();

        $models = [];
        $assigned_paths = [];

        foreach ($paths as $path) {
            if (!empty($path['is_template'])) {
                $models[] = $path;
            } else {
                $assigned_paths[] = $path;
            }
        }

        $selected_group_ids = is_array($editing['group_ids'] ?? null) ? $editing['group_ids'] : [];
        $selected_user_ids  = is_array($editing['user_ids'] ?? null) ? $editing['user_ids'] : [];
        $exercise_csv       = !empty($editing['exercise_ids']) && is_array($editing['exercise_ids'])
            ? implode(', ', $editing['exercise_ids'])
            : '';
        $selected_mode      = in_array((string) ($editing['mode'] ?? 'free'), ['free', 'sequential'], true)
            ? (string) $editing['mode']
            : 'free';
        $selected_student_note = (string) ($editing['student_note'] ?? '');
        $is_template        = !empty($editing['is_template']);
        $level_options      = PathsService::get_template_level_options();
        $domain_options     = PathsService::get_template_domain_options();
        $goal_options       = PathsService::get_template_goal_options();
        $selected_level_slug  = sanitize_key((string) ($editing['level_slug'] ?? ''));
        $selected_domain_slug = sanitize_key((string) ($editing['domain_slug'] ?? ''));
        $selected_goal_slug   = sanitize_key((string) ($editing['goal_slug'] ?? ''));

        if ($show_create_form) {
            echo '<h1>Parcours — création</h1>';
            echo '<p class="description">Crée ou modifie ici un parcours d’exercices, qu’il soit affecté directement ou utilisé comme modèle.</p>';
        } else {
            echo '<h1>Parcours — modèles</h1>';
            echo '<p class="description">Bibliothèque de modèles réutilisables pour l’affectation. Un modèle n’est jamais donné directement aux élèves.</p>';
        }

        if ($show_models_list && $assigning): ?>
            <hr>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <h2 style="margin:0;">Affecter un modèle</h2>
                <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=models')); ?>">
                    Retour aux modèles
                </a>
            </div>

            <div class="notice notice-info">
                <p>
                    <strong>Modèle sélectionné :</strong>
                    <?php echo esc_html($assigning['title']); ?>
                </p>
                <p class="description" style="margin:0;">
                    Cette action crée un nouveau parcours assigné à partir du modèle, sans modifier le modèle lui-même.
                </p>
            </div>

            <form method="post" style="margin-top:16px;">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="paths_action" value="instantiate">
                <input type="hidden" name="template_id" value="<?php echo (int) $assigning['id']; ?>">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ouinpo-assign-title">Titre du parcours créé</label></th>
                            <td>
                                <input
                                    type="text"
                                    id="ouinpo-assign-title"
                                    name="assign_title"
                                    class="regular-text"
                                    value=""
                                >
                                <p class="description">Optionnel. Si tu laisses vide, un titre sera généré automatiquement.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Classes</th>
                            <td>
                                <select name="assign_group_ids[]" multiple size="8" style="min-width:320px;">
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo (int) $group['id']; ?>">
                                            <?php echo esc_html($group['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Affectation à une ou plusieurs classes.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Élèves</th>
                            <td>
                                <select name="assign_user_ids[]" multiple size="10" style="min-width:420px;">
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo (int) $student['id']; ?>">
                                            <?php
                                            echo esc_html(
                                                $student['display_name']
                                                . (!empty($student['user_login']) ? ' (' . $student['user_login'] . ')' : '')
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Affectation individuelle. Tu peux cumuler classes et élèves spécifiques.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Créer et affecter le parcours'); ?>
            </form>
        <?php endif; ?>

        <?php if ($show_create_form): ?>
            <hr>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <h2 style="margin:0;"><?php echo $editing ? 'Modifier un parcours' : 'Créer un parcours'; ?></h2>

                <?php if ($editing): ?>
                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=create')); ?>">
                        Nouveau parcours
                    </a>
                <?php endif; ?>
            </div>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="paths_action" value="save">
                <?php if ($editing): ?>
                    <input type="hidden" name="path_id" value="<?php echo (int) $editing['id']; ?>">
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ouinpo-path-title">Titre</label></th>
                            <td>
                                <input
                                    type="text"
                                    id="ouinpo-path-title"
                                    name="title"
                                    class="regular-text"
                                    required
                                    value="<?php echo esc_attr($editing['title'] ?? ''); ?>"
                                >
                                <p class="description">Exemples : « Révisions SQL DS1 », « Remédiation récursivité », « Parcours Première — Structures de données ».</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Type</th>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="is_template"
                                        id="ouinpo-is-template"
                                        value="1"
                                        <?php checked($is_template, true); ?>
                                    >
                                    Modèle de parcours
                                </label>
                                <p class="description">
                                    Un modèle n’est pas affecté directement aux élèves. Il sert de base réutilisable pour créer des parcours assignés.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ouinpo-student-note">Commentaire élève</label></th>
                            <td>
                                <textarea
                                    id="ouinpo-student-note"
                                    name="student_note"
                                    rows="4"
                                    class="large-text"
                                    placeholder="Consigne, conseil de méthode, ordre recommandé, contexte du parcours…"
                                ><?php echo esc_textarea($selected_student_note); ?></textarea>
                                <p class="description">Texte affiché à l’élève dans sa liste de parcours et dans le détail du parcours.</p>
                            </td>
                        </tr>

                        <tr class="ouinpo-template-meta-row">
                            <th scope="row"><label for="ouinpo-level-slug">Niveau visé</label></th>
                            <td>
                                <select id="ouinpo-level-slug" name="level_slug">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($level_options as $slug => $label): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_level_slug, $slug); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Champ utilisé uniquement pour les modèles proposés aux élèves.</p>
                            </td>
                        </tr>

                        <tr class="ouinpo-template-meta-row">
                            <th scope="row"><label for="ouinpo-domain-slug">Domaine BO principal</label></th>
                            <td>
                                <select id="ouinpo-domain-slug" name="domain_slug">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($domain_options as $slug => $label): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_domain_slug, $slug); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">On retient ici le domaine BO principal du modèle.</p>
                            </td>
                        </tr>

                        <tr class="ouinpo-template-meta-row">
                            <th scope="row"><label for="ouinpo-goal-slug">Objectif</label></th>
                            <td>
                                <select id="ouinpo-goal-slug" name="goal_slug">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($goal_options as $slug => $label): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_goal_slug, $slug); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Exemple : remédiation, entraînement ou approfondissement.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="ouinpo-path-mode">Mode</label></th>
                            <td>
                                <select id="ouinpo-path-mode" name="mode">
                                    <option value="free" <?php selected($selected_mode, 'free'); ?>>Libre</option>
                                    <option value="sequential" <?php selected($selected_mode, 'sequential'); ?>>Séquentiel</option>
                                </select>
                                <p class="description">
                                    <strong>Libre</strong> : tous les exercices sont accessibles.<br>
                                    <strong>Séquentiel</strong> : l’exercice suivant reste verrouillé tant que le précédent n’est pas réussi.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Actif</th>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        <?php checked((int) ($editing['is_active'] ?? 1), 1); ?>
                                    >
                                    Parcours visible/actif
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="exercise_ids_csv">Ordre des exercices</label></th>
                            <td>
                                <textarea
                                    id="exercise_ids_csv"
                                    name="exercise_ids_csv"
                                    rows="4"
                                    class="large-text"
                                    placeholder="12, 18, 5, 44"
                                ><?php echo esc_textarea($exercise_csv); ?></textarea>
                                <p class="description">Saisis les <strong>ID des exercices</strong> dans l’ordre voulu, séparés par des virgules.</p>

                                <details style="margin-top:10px;">
                                    <summary><strong>Voir les exercices disponibles</strong></summary>
                                    <div style="margin-top:10px; max-height:260px; overflow:auto; border:1px solid #ddd; background:#fff; padding:10px;">
                                        <table class="widefat striped">
                                            <thead>
                                                <tr>
                                                    <th style="width:80px;">ID</th>
                                                    <th>Titre</th>
                                                    <th style="width:100px;">Actif</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($exercises as $exo): ?>
                                                <tr>
                                                    <td><?php echo (int) $exo['id']; ?></td>
                                                    <td><?php echo esc_html($exo['title']); ?></td>
                                                    <td><?php echo !empty($exo['is_active']) ? 'Oui' : 'Non'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            </td>
                        </tr>

                        <tr class="ouinpo-targets-row">
                            <th scope="row">Classes</th>
                            <td>
                                <select name="group_ids[]" multiple size="8" style="min-width:320px;">
                                    <?php foreach ($groups as $group): ?>
                                        <option
                                            value="<?php echo (int) $group['id']; ?>"
                                            <?php selected(in_array((int) $group['id'], $selected_group_ids, true), true); ?>
                                        >
                                            <?php echo esc_html($group['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Affectation à une ou plusieurs classes.</p>
                            </td>
                        </tr>

                        <tr class="ouinpo-targets-row">
                            <th scope="row">Élèves</th>
                            <td>
                                <select name="user_ids[]" multiple size="10" style="min-width:420px;">
                                    <?php foreach ($students as $student): ?>
                                        <option
                                            value="<?php echo (int) $student['id']; ?>"
                                            <?php selected(in_array((int) $student['id'], $selected_user_ids, true), true); ?>
                                        >
                                            <?php
                                            echo esc_html(
                                                $student['display_name']
                                                . (!empty($student['user_login']) ? ' (' . $student['user_login'] . ')' : '')
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Affectation individuelle. Tu peux cumuler classes et élèves spécifiques.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button($editing ? 'Mettre à jour le parcours' : 'Créer le parcours'); ?>

                <?php if ($editing): ?>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=create')); ?>">Annuler la modification</a>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=create')); ?>">Nouveau parcours</a>
                    </p>
                <?php endif; ?>
            </form>

            <hr>
            <h2>Parcours affectés</h2>
            <p class="description">
                Parcours réellement donnés aux élèves ou aux classes.
                C’est ici que tu retrouves les parcours non modèles pour les modifier, dupliquer ou supprimer.
            </p>

            <?php self::render_assigned_table($assigned_paths); ?>

            <script>
            (function () {
                const checkbox = document.getElementById('ouinpo-is-template');
                const rows = document.querySelectorAll('.ouinpo-targets-row');
                const templateRows = document.querySelectorAll('.ouinpo-template-meta-row');

                function refreshTargetsVisibility() {
                    if (!checkbox) return;
                    rows.forEach(function (row) {
                        row.style.display = checkbox.checked ? 'none' : '';
                    });
                    templateRows.forEach(function (row) {
                        row.style.display = checkbox.checked ? '' : 'none';
                    });
                }

                if (checkbox) {
                    checkbox.addEventListener('change', refreshTargetsVisibility);
                    refreshTargetsVisibility();
                }
            })();
            </script>
        <?php endif; ?>

        <?php if ($show_models_list): ?>
            <hr>
            <h2>Modèles</h2>
            <p class="description">Bibliothèque de parcours réutilisables. Un modèle sert de base et n’est jamais donné directement aux élèves.</p>

            <?php self::render_models_table($models); ?>
        <?php endif;
    }

    private static function render_assigned_table(array $paths): void
    {
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Titre</th>
                    <th style="width:220px;">Cibles</th>
                    <th style="width:120px;">Source</th>
                    <th style="width:120px;">Mode</th>
                    <th style="width:100px;">Actif</th>
                    <th style="width:100px;">Exos</th>
                    <th style="width:320px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($paths)): ?>
                <tr>
                    <td colspan="8">Aucun parcours affecté pour le moment.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($paths as $path): ?>
                    <?php
                    $edit_url = self::edit_url((int) $path['id']);
                    $source_label = !empty($path['template_source_id'])
                        ? 'Modèle #' . (int) $path['template_source_id']
                        : 'Direct';
                    ?>
                    <tr>
                        <td><?php echo (int) $path['id']; ?></td>
                        <td>
                            <strong><?php echo esc_html($path['title']); ?></strong><br>
                            <span style="color:#666;">
                                <?php echo esc_html($path['items_preview'] ?: ''); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($path['targets_label'] ?: '—'); ?></td>
                        <td><?php echo esc_html($source_label); ?></td>
                        <td><?php echo (($path['mode'] ?? 'free') === 'sequential') ? 'Séquentiel' : 'Libre'; ?></td>
                        <td><?php echo !empty($path['is_active']) ? 'Oui' : 'Non'; ?></td>
                        <td><?php echo (int) $path['items_count']; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Modifier</a>

                            <form method="post" style="display:inline-block; margin-left:6px;">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="paths_action" value="duplicate">
                                <input type="hidden" name="path_id" value="<?php echo (int) $path['id']; ?>">
                                <button type="submit" class="button button-small">Dupliquer</button>
                            </form>

                            <form method="post" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Supprimer ce parcours ?');">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="paths_action" value="delete">
                                <input type="hidden" name="path_id" value="<?php echo (int) $path['id']; ?>">
                                <button type="submit" class="button button-small">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_models_table(array $models): void
    {
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Titre</th>
                    <th style="width:110px;">Niveau</th>
                    <th style="width:180px;">Domaine BO</th>
                    <th style="width:140px;">Objectif</th>
                    <th style="width:120px;">Mode</th>
                    <th style="width:100px;">Actif</th>
                    <th style="width:100px;">Exos</th>
                    <th style="width:320px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($models)): ?>
                <tr>
                    <td colspan="9">Aucun modèle pour le moment.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($models as $path): ?>
                    <?php
                    $edit_url   = self::edit_url((int) $path['id']);
                    $assign_url = self::assign_url((int) $path['id']);
                    $level_options  = PathsService::get_template_level_options();
                    $domain_options = PathsService::get_template_domain_options();
                    $goal_options   = PathsService::get_template_goal_options();
                    $level_label  = $level_options[sanitize_key((string) ($path['level_slug'] ?? ''))] ?? '—';
                    $domain_label = $domain_options[sanitize_key((string) ($path['domain_slug'] ?? ''))] ?? '—';
                    $goal_label   = $goal_options[sanitize_key((string) ($path['goal_slug'] ?? ''))] ?? '—';
                    ?>
                    <tr>
                        <td><?php echo (int) $path['id']; ?></td>
                        <td>
                            <strong><?php echo esc_html($path['title']); ?></strong><br>
                            <span style="color:#666;">
                                <?php echo esc_html($path['items_preview'] ?: ''); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($level_label); ?></td>
                        <td><?php echo esc_html($domain_label); ?></td>
                        <td><?php echo esc_html($goal_label); ?></td>
                        <td><?php echo (($path['mode'] ?? 'free') === 'sequential') ? 'Séquentiel' : 'Libre'; ?></td>
                        <td><?php echo !empty($path['is_active']) ? 'Oui' : 'Non'; ?></td>
                        <td><?php echo (int) $path['items_count']; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Modifier</a>
                            <a class="button button-small button-primary" style="margin-left:6px;" href="<?php echo esc_url($assign_url); ?>">Affecter</a>

                            <form method="post" style="display:inline-block; margin-left:6px;">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="paths_action" value="duplicate">
                                <input type="hidden" name="path_id" value="<?php echo (int) $path['id']; ?>">
                                <button type="submit" class="button button-small">Dupliquer</button>
                            </form>

                            <form method="post" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Supprimer ce modèle ?');">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="paths_action" value="delete">
                                <input type="hidden" name="path_id" value="<?php echo (int) $path['id']; ?>">
                                <button type="submit" class="button button-small">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private static function handle_post(): void
    {
        if (!current_user_can('edit_users')) {
            wp_die('Accès refusé');
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $action = sanitize_key((string) ($_POST['paths_action'] ?? ''));

        if ($action === 'delete') {
            $path_id = (int) ($_POST['path_id'] ?? 0);
            if ($path_id > 0) {
                PathsService::delete_path($path_id);
                self::redirect([
                    'view'    => self::current_view(),
                    'deleted' => 1,
                ]);
            }
            return;
        }

        if ($action === 'duplicate') {
            $path_id = (int) ($_POST['path_id'] ?? 0);

            $result = PathsService::duplicate_path($path_id);

            if (is_wp_error($result)) {
                add_settings_error('ouinpo_paths', 'ouinpo_paths_error', $result->get_error_message(), 'error');
                return;
            }

            self::redirect([
                'view'       => 'create',
                'duplicated' => 1,
                'action'     => 'edit',
                'id'         => (int) $result,
            ]);
            return;
        }

        if ($action === 'instantiate') {
            $template_id = (int) ($_POST['template_id'] ?? 0);

            $group_ids = isset($_POST['assign_group_ids']) && is_array($_POST['assign_group_ids'])
                ? array_map('intval', wp_unslash($_POST['assign_group_ids']))
                : [];

            $user_ids = isset($_POST['assign_user_ids']) && is_array($_POST['assign_user_ids'])
                ? array_map('intval', wp_unslash($_POST['assign_user_ids']))
                : [];

            $result = PathsService::instantiate_template(
                $template_id,
                $user_ids,
                $group_ids,
                sanitize_text_field(wp_unslash((string) ($_POST['assign_title'] ?? '')))
            );

            if (is_wp_error($result)) {
                add_settings_error('ouinpo_paths', 'ouinpo_paths_error', $result->get_error_message(), 'error');
                return;
            }

            self::redirect([
                'view'     => 'create',
                'assigned' => 1,
                'action'   => 'edit',
                'id'       => (int) $result,
            ]);
            return;
        }

        if ($action !== 'save') {
            return;
        }

        $exercise_csv = trim(wp_unslash((string) ($_POST['exercise_ids_csv'] ?? '')));
        $exercise_ids = preg_split('/[^\d]+/', $exercise_csv, -1, PREG_SPLIT_NO_EMPTY);
        $exercise_ids = array_values(array_filter(array_map('intval', $exercise_ids), static fn($v) => $v > 0));

        $group_ids = isset($_POST['group_ids']) && is_array($_POST['group_ids'])
            ? array_map('intval', wp_unslash($_POST['group_ids']))
            : [];

        $user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids'])
            ? array_map('intval', wp_unslash($_POST['user_ids']))
            : [];

        $result = PathsService::save_path([
            'path_id'      => (int) ($_POST['path_id'] ?? 0),
            'title'        => sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? ''))),
            'student_note' => wp_kses_post(wp_unslash((string) ($_POST['student_note'] ?? ''))),
            'level_slug'   => sanitize_key((string) ($_POST['level_slug'] ?? '')),
            'domain_slug'  => sanitize_key((string) ($_POST['domain_slug'] ?? '')),
            'goal_slug'    => sanitize_key((string) ($_POST['goal_slug'] ?? '')),
            'mode'         => sanitize_key((string) ($_POST['mode'] ?? 'free')),
            'is_active'    => !empty($_POST['is_active']) ? 1 : 0,
            'is_template'  => !empty($_POST['is_template']) ? 1 : 0,
            'exercise_ids' => $exercise_ids,
            'group_ids'    => $group_ids,
            'user_ids'     => $user_ids,
        ]);

        if (is_wp_error($result)) {
            add_settings_error('ouinpo_paths', 'ouinpo_paths_error', $result->get_error_message(), 'error');
            return;
        }

        $is_update = !empty($_POST['path_id']);

        self::redirect([
            'view'   => 'create',
            ($is_update ? 'updated' : 'created') => 1,
            'action' => 'edit',
            'id'     => (int) $result,
        ]);
    }

    private static function render_notices_from_query(): void
    {
        $messages = [
            'created'    => 'Parcours créé.',
            'updated'    => 'Parcours mis à jour.',
            'deleted'    => 'Parcours supprimé.',
            'duplicated' => 'Parcours dupliqué.',
            'assigned'   => 'Modèle affecté : un nouveau parcours a été créé.',
        ];

        foreach ($messages as $key => $message) {
            if (!empty($_GET[$key])) {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html($message)
                );
            }
        }
    }

    private static function edit_url(int $path_id): string
    {
        return add_query_arg([
            'page'   => self::PAGE_SLUG,
            'view'   => 'create',
            'action' => 'edit',
            'id'     => $path_id,
        ], admin_url('admin.php'));
    }

    private static function assign_url(int $path_id): string
    {
        return add_query_arg([
            'page'   => self::PAGE_SLUG,
            'view'   => 'models',
            'action' => 'assign',
            'id'     => $path_id,
        ], admin_url('admin.php'));
    }

    private static function redirect(array $args = []): void
    {
        $base = admin_url('admin.php?page=' . self::PAGE_SLUG);
        wp_safe_redirect(add_query_arg($args, $base));
        exit;
    }
}