<?php
// Module interne OuInPo Suite : Flashcards.

defined('ABSPATH') || exit;

if (!defined('OUINPO_FLASHCARDS_PLUGIN_FILE')) {
    define('OUINPO_FLASHCARDS_PLUGIN_FILE', __FILE__);
}

require __DIR__ . '/inc/Autoloader.php';
\Ouinpo\Flashcards\Autoloader::init(__DIR__);

add_action('rest_api_init', function () {
    \Ouinpo\Flashcards\Rest\FlashcardsRoutes::register();
}, 99);

require_once __DIR__ . '/public/Shortcodes.php';
require_once __DIR__ . '/public/Assets.php';

add_action('init', function () {
    if (class_exists(\Ouinpo\Flashcards\Shortcodes::class)) \Ouinpo\Flashcards\Shortcodes::init();
    if (class_exists(\Ouinpo\Flashcards\Assets::class))     \Ouinpo\Flashcards\Assets::init();
});

if (is_admin()) {
    require_once __DIR__ . '/admin/AdminMenu.php';
    require_once __DIR__ . '/admin/screens/screen-flashcards.php';

    add_action('admin_menu', ['\\Ouinpo\\Flashcards\\Admin\\AdminMenu', 'register_menu'], 30);
    add_action('admin_post_ouinpo_fc_save_deck', ['\\Ouinpo\\Flashcards\\Admin\\AdminMenu', 'handle_save_deck']);
    add_action('admin_post_ouinpo_fc_delete_deck', ['\\Ouinpo\\Flashcards\\Admin\\AdminMenu', 'handle_delete_deck']);
    add_action('admin_post_ouinpo_fc_save_card', ['\\Ouinpo\\Flashcards\\Admin\\AdminMenu', 'handle_save_card']);
    add_action('admin_post_ouinpo_fc_delete_card', ['\\Ouinpo\\Flashcards\\Admin\\AdminMenu', 'handle_delete_card']);
    add_action('admin_post_ouinpo_fc_import_cards', ['\\Ouinpo\\Flashcards\\Admin\\AdminMenu', 'handle_import_cards']);
}
