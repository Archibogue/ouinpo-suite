<?php

namespace Ouinpo\Flashcards;

defined('ABSPATH') || exit;

final class Assets
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'register']);
    }

    public static function register(): void
    {
        $plugin_main = defined('OUINPO_SUITE_FILE')
            ? OUINPO_SUITE_FILE
            : (
                defined('OUINPO_FLASHCARDS_PLUGIN_FILE')
                    ? OUINPO_FLASHCARDS_PLUGIN_FILE
                    : dirname(__DIR__) . '/ouinpo-flashcards.php'
            );

        $base_path = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR
            : plugin_dir_path($plugin_main);

        $base_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL
            : plugin_dir_url($plugin_main);

        $fallback_version = defined('OUINPO_SUITE_VERSION')
            ? OUINPO_SUITE_VERSION
            : '1.0.0';

        $files = [
            'css' => 'assets/css/front/flashcards.css',
            'js'  => 'assets/js/front/flashcards.js',
        ];

        $css_ver = \Ouinpo\Suite\Core\Assets::fileVersion($base_path . $files['css'], $fallback_version);

        $js_ver = \Ouinpo\Suite\Core\Assets::fileVersion($base_path . $files['js'], $fallback_version);

        $deps = [];

        if (wp_style_is('ouinpo-core-css', 'registered')) {
            $deps[] = 'ouinpo-core-css';
        }

        if (wp_style_is('ouinpo-theme-flashcards-css', 'registered')) {
            $deps[] = 'ouinpo-theme-flashcards-css';
        }

        wp_register_style(
            'ouinpo-flashcards',
            $base_url . $files['css'],
            $deps,
            $css_ver
        );

        $jsDeps = [];
        if (wp_script_is('ouinpo-scroll-restore', 'registered')) {
            $jsDeps[] = 'ouinpo-scroll-restore';
        }

        wp_register_script(
            'ouinpo-flashcards',
            $base_url . $files['js'],
            $jsDeps,
            $js_ver,
            true
        );
    }
}
