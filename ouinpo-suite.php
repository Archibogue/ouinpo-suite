<?php
/**
 * Plugin Name: OuInPo Suite
 * Description: Point d'entree unique pour la suite OuInPo (Exercices, Depots, SegFault, Gate, RechText, Meta, Projects).
 * Version: 0.6.3-beta
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: OuInPo
 * License: GPLv2 or later
 * Text Domain: ouinpo-suite
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OUINPO_SUITE_FILE', __FILE__);
define('OUINPO_SUITE_DIR', plugin_dir_path(__FILE__));
define('OUINPO_SUITE_URL', plugin_dir_url(__FILE__));
define('OUINPO_SUITE_VERSION', '0.6.3-beta');

require_once OUINPO_SUITE_DIR . 'src/Core/Autoloader.php';
\Ouinpo\Suite\Core\Autoloader::init(OUINPO_SUITE_DIR . 'src');

register_activation_hook(__FILE__, function (): void {
    $plugin = \Ouinpo\Suite\Core\Bootstrap::makePlugin();
    $plugin->activate();
});

register_deactivation_hook(__FILE__, function (): void {
    $plugin = \Ouinpo\Suite\Core\Bootstrap::makePlugin();
    $plugin->deactivate();
});

add_action('plugins_loaded', function (): void {
    $plugin = \Ouinpo\Suite\Core\Bootstrap::makePlugin();
    $plugin->boot();
}, 1);
