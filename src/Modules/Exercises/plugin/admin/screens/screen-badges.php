<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

class Screen_Badges {
    public static function render() {
        if (!Capabilities::can(Capabilities::MANAGE_BADGES)) {
            return;
        }

        global $wpdb;
        $p = $wpdb->prefix . 'ouin_exo_';

        wp_enqueue_media();
        self::enqueue_script();

        $base_url = admin_url('admin.php?page=ouinpo-badges');
        $notice = '';
        $notice_type = 'success';

        // -------------------------
        // Suppression d'un badge
        // -------------------------
        if (
            !empty($_POST['ouin_badge_delete_nonce'])
            && wp_verify_nonce(wp_unslash($_POST['ouin_badge_delete_nonce']), 'delete_badge')
        ) {
            $badge_id = isset($_POST['badge_id']) ? (int) $_POST['badge_id'] : 0;

            if ($badge_id > 0) {
                $badge = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM {$p}badges WHERE id = %d", $badge_id)
                );

                if (!$badge) {
                    $notice = 'Badge introuvable.';
                    $notice_type = 'error';
                } else {
                    $awarded_count = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$p}user_badges WHERE badge_id = %d",
                            $badge_id
                        )
                    );

                    if ($awarded_count > 0) {
                        $notice = 'Suppression refusée : ce badge a déjà été attribué à ' . $awarded_count . ' utilisateur(s).';
                        $notice_type = 'error';
                    } else {
                        $deleted = $wpdb->delete($p . 'badges', ['id' => $badge_id], ['%d']);
                    
                        if ($deleted !== false) {
                            $notice = 'Badge supprimé.';
                            $notice_type = 'success';
                        } else {
                            $notice = 'Erreur lors de la suppression du badge.';
                            if (!empty($wpdb->last_error)) {
                                $notice .= ' ' . $wpdb->last_error;
                            }
                            $notice_type = 'error';
                        }
                    }
                }
            }
        }

        // -------------------------
        // Création / modification
        // -------------------------
        $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

        if (
            !empty($_POST['ouin_badge_nonce'])
            && wp_verify_nonce(wp_unslash($_POST['ouin_badge_nonce']), 'save_badge')
        ) {
            $badge_id = isset($_POST['badge_id']) ? (int) $_POST['badge_id'] : 0;
            $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
            $slug  = sanitize_title(wp_unslash($_POST['slug'] ?? ''));

            if ($slug === '' && $title !== '') {
                $slug = sanitize_title($title);
            }

            $data = [
                'slug'        => $slug,
                'title'       => $title,
                'description' => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
                'theme'       => sanitize_text_field(wp_unslash($_POST['theme'] ?? '')),
                'image_url'   => esc_url_raw(wp_unslash($_POST['image_url'] ?? '')),
            ];

            if ($data['slug'] === '' || $data['title'] === '') {
                $notice = 'Le slug et le titre sont obligatoires.';
                $notice_type = 'error';
            } else {
                $duplicate = null;

                if ($badge_id > 0) {
                    $duplicate = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$p}badges WHERE slug = %s AND id <> %d LIMIT 1",
                            $data['slug'],
                            $badge_id
                        )
                    );
                } else {
                    $duplicate = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$p}badges WHERE slug = %s LIMIT 1",
                            $data['slug']
                        )
                    );
                }

                if ($duplicate) {
                    $notice = 'Ce slug existe déjà. Choisis-en un autre.';
                    $notice_type = 'error';
                } else {
                    if ($badge_id > 0) {
                        $ok = $wpdb->update(
                            $p . 'badges',
                            $data,
                            ['id' => $badge_id],
                            ['%s', '%s', '%s', '%s', '%s'],
                            ['%d']
                        );

                        if ($ok !== false) {
                            $notice = 'Badge mis à jour.';
                            $notice_type = 'success';
                            $edit_id = $badge_id;
                        } else {
                            $notice = 'Erreur lors de la mise à jour du badge.';
                            if (!empty($wpdb->last_error)) {
                                $notice .= ' ' . $wpdb->last_error;
                            }
                            $notice_type = 'error';
                        }
                    } else {
                        $ok = $wpdb->insert(
                            $p . 'badges',
                            $data,
                            ['%s', '%s', '%s', '%s', '%s']
                        );

                        if ($ok) {
                            $edit_id = (int) $wpdb->insert_id;
                            $notice = 'Badge ajouté.';
                            $notice_type = 'success';
                        } else {
                            $notice = 'Erreur lors de l’ajout du badge.';
                            if (!empty($wpdb->last_error)) {
                                $notice .= ' ' . $wpdb->last_error;
                            }
                            $notice_type = 'error';
                        }
                    }
                }
            }
        }

        $editing = null;
        if ($edit_id > 0) {
            $editing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$p}badges WHERE id = %d", $edit_id)
            );
            if (!$editing) {
                $edit_id = 0;
            }
        }

        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $theme_filter = sanitize_text_field(wp_unslash($_GET['theme_filter'] ?? ''));

        $themes = $wpdb->get_col(
            "SELECT DISTINCT theme FROM {$p}badges WHERE theme IS NOT NULL AND theme <> '' ORDER BY theme ASC"
        );

        $where = [];
        $params = [];

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(b.title LIKE %s OR b.slug LIKE %s OR b.description LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($theme_filter !== '') {
            $where[] = 'b.theme = %s';
            $params[] = $theme_filter;
        }

        $sql = "
            SELECT b.*, COUNT(ub.user_id) AS awarded_count
            FROM {$p}badges b
            LEFT JOIN {$p}user_badges ub ON ub.badge_id = b.id
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY b.id ORDER BY b.theme ASC, b.title ASC';

        if ($params) {
            $badges = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $badges = $wpdb->get_results($sql);
        }

        $form_badge_id = $editing ? (int) $editing->id : 0;
        $form_slug     = $editing ? (string) $editing->slug : '';
        $form_title    = $editing ? (string) $editing->title : '';
        $form_desc     = $editing ? (string) $editing->description : '';
        $form_theme    = $editing ? (string) $editing->theme : '';
        $form_image    = $editing ? (string) $editing->image_url : '';
        ?>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <h1>Badges</h1>
        <p class="ouinpo-badge-muted">
            Création, modification et choix d’image via la médiathèque WordPress.
        </p>

        <p>
            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-badge-assignments')); ?>">
                Attribuer des badges à des élèves
            </a>
        </p>

        <div class="ouinpo-badge-admin-grid">
            <div class="ouinpo-badge-admin-card">
                <h2 class="ouinpo-badge-card-title"><?php echo $editing ? 'Modifier un badge' : 'Ajouter un badge'; ?></h2>

                <form method="post">
                    <?php wp_nonce_field('save_badge', 'ouin_badge_nonce'); ?>
                    <input type="hidden" name="badge_id" value="<?php echo (int) $form_badge_id; ?>">

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ouin-badge-slug">Slug</label></th>
                            <td><input id="ouin-badge-slug" name="slug" required class="regular-text" value="<?php echo esc_attr($form_slug); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ouin-badge-title">Titre</label></th>
                            <td><input id="ouin-badge-title" name="title" required class="regular-text" value="<?php echo esc_attr($form_title); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ouin-badge-description">Description</label></th>
                            <td><textarea id="ouin-badge-description" name="description" class="large-text" rows="4"><?php echo esc_textarea($form_desc); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ouin-badge-theme">Thème</label></th>
                            <td><input id="ouin-badge-theme" name="theme" class="regular-text" value="<?php echo esc_attr($form_theme); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ouin-badge-image">Image</label></th>
                            <td>
                                <input id="ouin-badge-image" name="image_url" class="regular-text" value="<?php echo esc_attr($form_image); ?>">
                                <p class="description">Tu peux coller une URL, choisir une image déjà présente, ou téléverser un nouveau fichier.</p>
                                <p class="ouinpo-badge-actions">
                                    <button type="button" class="button" id="ouin-badge-media">Choisir / téléverser une image</button>
                                    <button type="button" class="button" id="ouin-badge-image-clear">Retirer l’image</button>
                                </p>
                                <div id="ouin-badge-preview" class="ouinpo-badge-preview-box">
                                    <?php if (!empty($form_image)): ?>
                                        <img src="<?php echo esc_url($form_image); ?>" alt="Aperçu du badge">
                                    <?php else: ?>
                                        <em>Aucune image</em>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <p class="submit ouinpo-badge-actions">
                        <button class="button button-primary"><?php echo $editing ? 'Enregistrer les modifications' : 'Ajouter le badge'; ?></button>
                        <?php if ($editing): ?>
                            <a class="button" href="<?php echo esc_url($base_url); ?>">Annuler</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <div class="ouinpo-badge-admin-card">
                <h2 class="ouinpo-badge-card-title">Badges existants</h2>

                <form method="get" class="ouinpo-badge-toolbar">
                    <input type="hidden" name="page" value="ouinpo-badges">

                    <div class="field">
                        <label for="ouin-badge-search">Recherche</label>
                        <input id="ouin-badge-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="titre, slug, description">
                    </div>

                    <div class="field">
                        <label for="ouin-badge-theme-filter">Thème</label>
                        <select id="ouin-badge-theme-filter" name="theme_filter">
                            <option value="">Tous</option>
                            <?php foreach ($themes as $theme): ?>
                                <option value="<?php echo esc_attr($theme); ?>" <?php selected($theme_filter, $theme); ?>>
                                    <?php echo esc_html($theme); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <button class="button">Filtrer</button>
                    </div>

                    <?php if ($search !== '' || $theme_filter !== ''): ?>
                        <div class="field">
                            <a class="button" href="<?php echo esc_url($base_url); ?>">Réinitialiser</a>
                        </div>
                    <?php endif; ?>
                </form>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th class="ouinpo-badge-list-thumb">Image</th>
                            <th>Titre</th>
                            <th>Slug</th>
                            <th>Thème</th>
                            <th>Attribué</th>
                            <th class="ouinpo-badge-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($badges): ?>
                        <?php foreach ($badges as $b): ?>
                            <tr>
                                <td class="ouinpo-badge-list-thumb">
                                    <?php if (!empty($b->image_url)): ?>
                                        <img src="<?php echo esc_url($b->image_url); ?>" alt="">
                                    <?php else: ?>
                                        <span class="ouinpo-badge-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($b->title); ?></strong>
                                    <?php if (!empty($b->description)): ?>
                                        <div class="ouinpo-badge-muted ouinpo-badge-description-excerpt">
                                            <?php echo esc_html(wp_trim_words(wp_strip_all_tags($b->description), 18)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html($b->slug); ?></code></td>
                                <td><?php echo esc_html($b->theme ?: '—'); ?></td>
                                <td><?php echo (int) $b->awarded_count; ?></td>
                                <td>
                                    <div class="ouinpo-badge-actions">
                                        <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'ouinpo-badges', 'edit' => (int) $b->id], admin_url('admin.php'))); ?>">Modifier</a>

                                        <?php if ((int) $b->awarded_count === 0): ?>
                                            <form method="post" class="ouinpo-inline-form" data-confirm="Supprimer ce badge ?">
                                                <?php wp_nonce_field('delete_badge', 'ouin_badge_delete_nonce'); ?>
                                                <input type="hidden" name="badge_id" value="<?php echo (int) $b->id; ?>">
                                                <button class="button button-small">Supprimer</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="ouinpo-badge-muted">déjà attribué</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><em>Aucun badge trouvé.</em></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private static function enqueue_script(): void {
        $relative_path = 'assets/js/admin/badges.js';
        $base_dir = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR
            : dirname(__DIR__, 6);
        $base_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL
            : plugin_dir_url($base_dir . '/ouinpo-suite.php');
        $file = $base_dir . $relative_path;
        $version = file_exists($file)
            ? (string) filemtime($file)
            : (defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0');

        wp_enqueue_script(
            'ouinpo-badges-admin-js',
            $base_url . $relative_path,
            [],
            $version,
            true
        );
    }
}
