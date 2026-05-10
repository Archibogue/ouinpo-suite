<?php

namespace Ouinpo\Suite\Core\Admin;

use Ouinpo\Suite\Core\ModuleSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class PagesSetup
{
    private const NONCE_ACTION = 'ouinpo_suite_pages_setup';
    private const NONCE_NAME = 'ouinpo_suite_pages_setup_nonce';

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            ?>
            <div class="notice notice-error">
                <p>Cette configuration est réservée aux administrateurs.</p>
            </div>
            <?php
            return;
        }

        $request_method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '';

        if ($request_method === 'POST') {
            self::handleSubmit();
        }

        $pages = self::pages();
        ?>
        <div class="card ouinpo-suite-card-bounded">
            <h2 class="ouinpo-suite-card-title">Pages publiques</h2>
            <p class="ouinpo-suite-muted">
                Crée les pages utiles avec leur shortcode. Les titres peuvent être modifiés avant création ; les slugs restent stables pour que les liens internes continuent de fonctionner.
            </p>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="ouinpo_suite_pages_action" value="create_pages">

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th class="ouinpo-suite-col-8">Créer</th>
                            <th>Page</th>
                            <th>Shortcode</th>
                            <th class="ouinpo-suite-col-18">État</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $key => $page): ?>
                            <?php $existing = self::findPage((string) $page['slug']); ?>
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="pages[]"
                                        value="<?php echo esc_attr($key); ?>"
                                        <?php checked(!$existing); ?>
                                    >
                                </td>
                                <td>
                                    <label>
                                        <span class="screen-reader-text">Titre de la page <?php echo esc_html((string) $page['title']); ?></span>
                                        <input
                                            type="text"
                                            class="regular-text"
                                            name="titles[<?php echo esc_attr($key); ?>]"
                                            value="<?php echo esc_attr($existing ? get_the_title($existing) : (string) $page['title']); ?>"
                                        >
                                    </label>
                                    <p class="description">
                                        Slug : <code><?php echo esc_html((string) $page['slug']); ?></code>
                                    </p>
                                </td>
                                <td><code><?php echo esc_html((string) $page['shortcode']); ?></code></td>
                                <td>
                                    <?php if ($existing): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($existing->ID)); ?>">Modifier</a>
                                        <span class="ouinpo-suite-muted"> · </span>
                                        <a href="<?php echo esc_url(get_permalink($existing)); ?>">Voir</a>
                                    <?php else: ?>
                                        <span class="ouinpo-suite-status ouinpo-suite-status--warning">À créer</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button('Créer ou mettre à jour les pages sélectionnées'); ?>
            </form>
        </div>
        <?php
    }

    private static function handleSubmit(): void
    {
        if (
            !isset($_POST[self::NONCE_NAME])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            add_settings_error('ouinpo_suite_pages', 'bad_nonce', 'Action refusée : sécurité WordPress expirée.', 'error');
            return;
        }

        $selected = isset($_POST['pages']) && is_array($_POST['pages'])
            ? array_map('sanitize_key', wp_unslash($_POST['pages']))
            : [];
        $titles = isset($_POST['titles']) && is_array($_POST['titles'])
            ? wp_unslash($_POST['titles'])
            : [];
        $pages = self::pages();
        $created = 0;
        $updated = 0;

        foreach ($selected as $key) {
            if (!isset($pages[$key])) {
                continue;
            }

            $page = $pages[$key];
            $raw_title = isset($titles[$key]) && is_scalar($titles[$key]) ? $titles[$key] : '';
            $title = sanitize_text_field((string) $raw_title);
            if ($title === '') {
                $title = (string) $page['title'];
            }

            $existing = self::findPage((string) $page['slug']);
            $content = (string) $page['shortcode'];

            if ($existing) {
                $result = wp_update_post([
                    'ID' => $existing->ID,
                    'post_title' => $title,
                ], true);

                if (!is_wp_error($result)) {
                    $updated++;
                }

                continue;
            }

            $result = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $title,
                'post_name' => (string) $page['slug'],
                'post_content' => $content,
            ], true);

            if (!is_wp_error($result)) {
                $created++;
            }
        }

        if ($created > 0 || $updated > 0) {
            add_settings_error(
                'ouinpo_suite_pages',
                'pages_saved',
                sprintf('%d page(s) créée(s), %d page(s) renommée(s).', $created, $updated),
                'updated'
            );
        } else {
            add_settings_error('ouinpo_suite_pages', 'pages_none', 'Aucune page sélectionnée.', 'notice-info');
        }
    }

    private static function pages(): array
    {
        $pages = [
            'exercises' => [
                'title' => 'Exercices',
                'slug' => 'exercices',
                'shortcode' => '[ouinpo_exercises]',
            ],
            'exercise' => [
                'title' => 'Exercice',
                'slug' => 'exercice',
                'shortcode' => '[ouinpo_exercise]',
            ],
            'practical_subjects' => [
                'title' => 'Épreuve pratique',
                'slug' => 'epreuve-pratique',
                'shortcode' => '[ouinpo_practical_subjects]',
            ],
            'practical_subject' => [
                'title' => 'Sujet pratique',
                'slug' => 'epreuve-pratique-sujet',
                'shortcode' => '[ouinpo_practical_subject]',
            ],
            'competences' => [
                'title' => 'Mes compétences',
                'slug' => 'mes-competences',
                'shortcode' => '[ouinpo_competences_progress]',
            ],
            'competences_teacher' => [
                'title' => 'Suivi des compétences',
                'slug' => 'suivi-competences',
                'shortcode' => '[ouinpo_competences_prof]',
            ],
            'badges' => [
                'title' => 'Mes badges',
                'slug' => 'mes-badges',
                'shortcode' => '[ouinpo_student_badges]',
            ],
            'badges_palmares' => [
                'title' => 'Palmarès des badges',
                'slug' => 'palmares-badges',
                'shortcode' => '[ouinpo_badges_palmares]',
            ],
            'site_map' => [
                'title' => 'Carte du site',
                'slug' => 'carte-du-site',
                'shortcode' => '[ouinpo_site_map]',
            ],
        ];

        if (ModuleSettings::isEnabled('flashcards')) {
            $pages['flashcards'] = [
                'title' => 'Flashcards',
                'slug' => 'flashcards',
                'shortcode' => '[ouinpo_flashcards]',
            ];
        }

        if (ModuleSettings::isEnabled('submissions')) {
            $pages['upload'] = [
                'title' => 'Dépôt de travaux',
                'slug' => 'depot-travaux',
                'shortcode' => '[ouinpo_upload]',
            ];
            $pages['my_submissions'] = [
                'title' => 'Mes dépôts',
                'slug' => 'mes-depots',
                'shortcode' => '[ouinpo_my_submissions]',
            ];
            $pages['resources'] = [
                'title' => 'Ressources',
                'slug' => 'ressources',
                'shortcode' => '[ouinpo_resources]',
            ];
        }

        if (ModuleSettings::isEnabled('gate')) {
            $pages['gate'] = [
                'title' => 'Gate',
                'slug' => 'gate',
                'shortcode' => '[ouinpo_gate]',
            ];
            $pages['signpad'] = [
                'title' => 'Signatures',
                'slug' => 'signatures',
                'shortcode' => '[ouinpo_signpad]',
            ];
        }

        if (ModuleSettings::isEnabled('segfault')) {
            $pages['segfault_chat'] = [
                'title' => 'Assistant SegFault',
                'slug' => 'assistant-segfault',
                'shortcode' => '[segfault_chat]',
            ];
            $pages['segfault_paths'] = [
                'title' => 'Mes parcours',
                'slug' => 'mes-parcours',
                'shortcode' => '[segfault_mes_parcours]',
            ];
        }

        if (ModuleSettings::isEnabled('rechtext')) {
            $pages['rechtext'] = [
                'title' => 'Recherche textuelle',
                'slug' => 'recherche-textuelle',
                'shortcode' => '[ouinpo_recherche_textuelle]',
            ];
        }

        return $pages;
    }

    private static function findPage(string $slug): ?\WP_Post
    {
        $page = get_page_by_path($slug);

        return $page instanceof \WP_Post ? $page : null;
    }
}
