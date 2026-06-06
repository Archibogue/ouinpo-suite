<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class Assets
{
    private const VERSION = '1.0.0';

    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'register']);
        add_action('admin_enqueue_scripts', [self::class, 'admin']);
    }

    public static function register(): void
    {
        $baseUrl = defined('OUINPO_SUITE_URL') ? OUINPO_SUITE_URL : '';
        $baseDir = defined('OUINPO_SUITE_DIR') ? OUINPO_SUITE_DIR : '';

        if ($baseUrl === '') {
            return;
        }

        $css = 'assets/css/front/projects.css';
        $js = 'assets/js/front/projects.js';

        wp_register_style(
            'ouinpo-projects',
            $baseUrl . $css,
            wp_style_is('ouinpo-core-css', 'registered') ? ['ouinpo-core-css'] : [],
            self::version($baseDir . $css)
        );

        wp_register_script(
            'ouinpo-projects',
            $baseUrl . $js,
            [],
            self::version($baseDir . $js),
            true
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

        $baseUrl = defined('OUINPO_SUITE_URL') ? OUINPO_SUITE_URL : '';
        $baseDir = defined('OUINPO_SUITE_DIR') ? OUINPO_SUITE_DIR : '';
        $css = 'assets/css/admin/projects-admin.css';

        if ($baseUrl !== '') {
            wp_enqueue_style('ouinpo-projects-admin', $baseUrl . $css, [], self::version($baseDir . $css));
        }
    }

    public static function enqueueFront(): void
    {
        wp_enqueue_style('ouinpo-projects');
        wp_enqueue_script('ouinpo-projects');

        if (wp_style_is('ouinpo-theme-css', 'registered')) {
            wp_enqueue_style('ouinpo-theme-css');
        }
    }

    private static function version(string $path): string
    {
        return $path !== '' && file_exists($path)
            ? (string) filemtime($path)
            : self::VERSION;
    }
}
