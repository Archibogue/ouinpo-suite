<?php

namespace Ouinpo\Exercises;

if (!defined('ABSPATH')) exit;

class Assets {

    public static function init() {
        add_action('wp_enqueue_scripts', [self::class, 'register']);
    }

    public static function register() {
        $plugin_main = defined('OUINPO_SUITE_FILE')
            ? OUINPO_SUITE_FILE
            : (
                defined('OUINPO_EXO_PLUGIN_FILE')
                    ? OUINPO_EXO_PLUGIN_FILE
                    : dirname(__DIR__) . '/ouinpo-exercices.php'
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
            'exo_js'        => 'src/Modules/Exercises/plugin/public/assets/js/exercises.js',
            'practical_js'  => 'src/Modules/Exercises/plugin/public/assets/js/practical.js',
            'student_js'    => 'src/Modules/Exercises/plugin/public/assets/js/student-competencies.js',
            'teacher_js'    => 'src/Modules/Exercises/plugin/public/assets/js/teacher-competencies.js',
            'badges_js'     => 'src/Modules/Exercises/plugin/public/assets/js/student-badges.js',

            'core_css'      => 'assets/css/core/base.css',
            'exo_css'       => 'assets/css/front/exercises.css',
            'practical_css' => 'assets/css/front/practical.css',
            'teacher_css'   => 'assets/css/front/teacher-competencies.css',

            'theme_neutral' => 'assets/css/themes/neutral.css',
            'theme_ouinpo'  => 'assets/css/themes/ouinpo.css',
        ];

        $ver = [];

        foreach ($files as $key => $rel) {
            $ver[$key] = file_exists($base_path . $rel)
                ? (string) filemtime($base_path . $rel)
                : $fallback_version;
        }

        /*
        * Mode de style.
        *
        * Par défaut, le plugin charge le thème neutre.
        * Le thème OuInPo est une couche visuelle optionnelle portée par
        * assets/css/themes/ouinpo.css.
        */
        $style_mode = get_option('ouinpo_suite_style_mode', 'neutral');

        if (!in_array($style_mode, ['neutral', 'ouinpo'], true)) {
            $style_mode = 'neutral';
        }

        $theme_file = $style_mode === 'neutral'
            ? $files['theme_neutral']
            : $files['theme_ouinpo'];

        /*
         * CSS commun.
         */
        wp_register_style(
            'ouinpo-core-css',
            $base_url . $files['core_css'],
            [],
            $ver['core_css']
        );

        /*
         * Thème actif.
         * Pour l’instant, le fichier peut être vide.
         */
        wp_register_style(
            'ouinpo-theme-css',
            $base_url . $theme_file,
            [],
            file_exists($base_path . $theme_file)
                ? (string) filemtime($base_path . $theme_file)
                : $fallback_version
        );

        /*
         * Module Exercices.
         */
        wp_register_style(
            'ouinpo-exo-module-css',
            $base_url . $files['exo_css'],
            ['ouinpo-core-css'],
            $ver['exo_css']
        );

        /*
         * Handle public conservé.
         * Les shortcodes peuvent continuer à appeler :
         * wp_enqueue_style('ouinpo-exo-css');
         */
        wp_register_style(
            'ouinpo-exo-css',
            false,
            ['ouinpo-exo-module-css', 'ouinpo-theme-css'],
            $fallback_version
        );

        /*
         * Module Sujets pratiques.
         */
        wp_register_style(
            'ouinpo-practical-module-css',
            $base_url . $files['practical_css'],
            ['ouinpo-core-css', 'ouinpo-exo-module-css'],
            $ver['practical_css']
        );

        /*
         * Handle public conservé.
         */
        wp_register_style(
            'ouinpo-practical-css',
            false,
            ['ouinpo-practical-module-css', 'ouinpo-theme-css'],
            $fallback_version
        );

        /*
         * Module professeur / compétences.
         */
        wp_register_style(
            'ouinpo-teacher-module-css',
            $base_url . $files['teacher_css'],
            ['ouinpo-core-css'],
            $ver['teacher_css']
        );

        /*
         * Handle public conservé.
         */
        wp_register_style(
            'ouinpo-teacher-css',
            false,
            ['ouinpo-teacher-module-css', 'ouinpo-theme-css'],
            $fallback_version
        );

        /*
         * Scripts.
         * On utilise $base_url partout pour rester cohérent avec la nouvelle structure.
         */
        wp_register_script(
            'ouinpo-exo-js',
            $base_url . $files['exo_js'],
            [],
            $ver['exo_js'],
            true
        );

        wp_register_script(
            'ouinpo-practical-js',
            $base_url . $files['practical_js'],
            [],
            $ver['practical_js'],
            true
        );

        wp_register_script(
            'ouinpo-student-competencies',
            $base_url . $files['student_js'],
            [],
            $ver['student_js'],
            true
        );

        wp_register_script(
            'ouinpo-teacher-competencies',
            $base_url . $files['teacher_js'],
            [],
            $ver['teacher_js'],
            true
        );

        wp_register_script(
            'ouinpo-student-badges',
            $base_url . $files['badges_js'],
            [],
            $ver['badges_js'],
            true
        );

        $rest_info = [
            'nonce' => wp_create_nonce('wp_rest'),
            'api'   => rest_url(),
        ];

        wp_localize_script('ouinpo-exo-js', 'OUINEXO', $rest_info);
        wp_localize_script('ouinpo-practical-js', 'OUINEXO', $rest_info);
        wp_localize_script('ouinpo-student-competencies', 'OUINEXO', $rest_info);
        wp_localize_script('ouinpo-teacher-competencies', 'OUINEXO', $rest_info);
        wp_localize_script('ouinpo-student-badges', 'OUINEXO', $rest_info);
    }
}