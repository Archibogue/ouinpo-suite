<?php
// Module interne OuInPo Suite : Exercices.

if (!defined('ABSPATH')) exit;

if (!defined('OUINPO_EXO_PLUGIN_FILE')) {
    define('OUINPO_EXO_PLUGIN_FILE', __FILE__);
}

// ==========================================================
// 1) Core : autoloader & install
// ==========================================================

require __DIR__ . '/inc/Autoloader.php';
Ouinpo\Exercises\Autoloader::init(__DIR__);

require_once __DIR__ . '/inc/Titles.php';
require_once __DIR__ . '/inc/BadgeEngine.php';
require_once __DIR__ . '/inc/RevisionBand.php';
require_once __DIR__ . '/inc/PracticalAiBridge.php';
require_once __DIR__ . '/admin/screens/screen-practical.php';

// Les migrations sont pilotées par le module parent de la suite.


// ==========================================================
// 2) REST API : routes
// ==========================================================

add_action('rest_api_init', function () {
    \Ouinpo\Exercises\Rest\ExercisesRoutes::register();
    \Ouinpo\Exercises\Rest\CompetenciesRoutes::register();
    \Ouinpo\Exercises\Rest\BadgesRoutes::register();
    \Ouinpo\Exercises\Rest\StatusRoutes::register();
    \Ouinpo\Exercises\Rest\AiAnswerRoutes::register();
    \Ouinpo\Exercises\Rest\AssessmentsRoutes::register();
    \Ouinpo\Exercises\Rest\AssessmentsCompetencyRoutes::register();
    \Ouinpo\Exercises\Rest\MeRoutes::register();
    \Ouinpo\Exercises\Rest\PracticalRoutes::register();
}, 99);

// ==========================================================
// 3) Front : shortcodes & assets
// ==========================================================

require_once __DIR__ . '/public/Shortcodes.php';
require_once __DIR__ . '/public/Assets.php';

add_action('init', function () {
    /*
     * Namespace canonique du module Exercices :
     * Ouinpo\Exercises
     *
     * On évite ici l'ancien préfixe OuInPo\Exercises, car il dépend
     * trop de l'ordre de chargement et peut empêcher certaines classes
     * d'être initialisées.
     */

    if (class_exists(\Ouinpo\Exercises\AccessGate::class)) {
        \Ouinpo\Exercises\AccessGate::init();
    }

    if (class_exists(\Ouinpo\Exercises\Years::class)) {
        \Ouinpo\Exercises\Years::init();
    }

    if (class_exists(\Ouinpo\Exercises\LevelsSchool::class)) {
        \Ouinpo\Exercises\LevelsSchool::init();
    }

    if (class_exists(\Ouinpo\Exercises\Shortcodes::class)) {
        \Ouinpo\Exercises\Shortcodes::init();
    }

    if (class_exists(\Ouinpo\Exercises\Assets::class)) {
        \Ouinpo\Exercises\Assets::init();
    }

    if (class_exists(\Ouinpo\Exercises\RevisionBand::class)) {
        \Ouinpo\Exercises\RevisionBand::init();
    }

    if (class_exists(\Ouinpo\Exercises\Titles::class)) {
        \Ouinpo\Exercises\Titles::init();
    }

    if (class_exists(\Ouinpo\Exercises\PracticalAiBridge::class)) {
        \Ouinpo\Exercises\PracticalAiBridge::init();
    }

    // BadgeEngine : pas d’init, il est appelé lors des mises à jour de statut.
});


// ==========================================================
// 4) Admin : menu & écran d’admin des exercices
// ==========================================================

if (is_admin()) {
    require_once __DIR__ . '/admin/AdminMenu.php';
    require_once __DIR__ . '/admin/screens/screen-exercises.php';

    add_action('admin_menu', ['\\OuInPo\\Exercises\\Admin\\AdminMenu', 'register_menu']);
    add_action('admin_enqueue_scripts', ['\\Ouinpo\\Exercises\\Admin\\AdminMenu', 'enqueue_admin_styles']);
    add_action('admin_post_ouinpo_export_exercises_csv', ['\\OuInPo\\Exercises\\Admin\\AdminMenu', 'handle_export_exercises_csv']);
    add_action('admin_post_ouinpo_save_practical_subject', ['\\Ouinpo\\Exercises\\Admin\\ScreenPractical', 'handle_save']);
    
    add_action('admin_notices', function () {
        if (!class_exists('\\OuInPo\\Exercises\\Admin\\AdminMenu')) {
            echo '<div class="notice notice-error"><p>OuInPo Exercices : classe AdminMenu introuvable.</p></div>';
        }
    });
}
