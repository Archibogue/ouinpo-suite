<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\Assets as CoreAssets;

defined('ABSPATH') || exit;

final class Assets
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'register']);
        add_action('admin_enqueue_scripts', [self::class, 'admin']);
    }

    public static function register(): void
    {
        $css = 'assets/css/front/projects.css';
        $js = 'assets/js/front/projects.js';
        $cssDeps = wp_style_is('ouinpo-core-css', 'registered') ? ['ouinpo-core-css'] : [];

        if (wp_style_is('ouinpo-theme-projects-css', 'registered')) {
            $cssDeps[] = 'ouinpo-theme-projects-css';
        }

        CoreAssets::registerStyle(
            'ouinpo-projects',
            $css,
            $cssDeps
        );

        CoreAssets::registerScript(
            'ouinpo-projects',
            $js
        );

        wp_localize_script('ouinpo-projects', 'OuinpoProjects', [
            'restUrl' => esc_url_raw(rest_url('ouinpo-projects/v1')),
            'root' => esc_url_raw(rest_url('ouinpo-projects/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUserId' => get_current_user_id(),
            'i18n' => [
                'error' => 'Action impossible.',
                'editTitle' => 'Nouveau titre de la tache',
                'confirmDelete' => 'Archiver cette tache ?',
            ],
        ]);
    }

    public static function admin(string $hook = ''): void
    {
        unset($hook);

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page !== 'ouinpo-projects') {
            return;
        }

        CoreAssets::enqueueStyle('ouinpo-projects-admin', 'assets/css/admin/projects-admin.css');
    }

    public static function enqueueFront(): void
    {
        wp_enqueue_style('ouinpo-projects');
        wp_enqueue_script('ouinpo-projects');

        if (wp_style_is('ouinpo-theme-css', 'registered')) {
            wp_enqueue_style('ouinpo-theme-css');
        }
    }

}
