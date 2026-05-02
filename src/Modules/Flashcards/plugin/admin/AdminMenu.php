<?php
namespace Ouinpo\Flashcards\Admin;

use Ouinpo\Flashcards\Service;

defined('ABSPATH') || exit;

final class AdminMenu
{
    public static function register_menu(): void
    {
        add_submenu_page(
            'ouinpo-suite',
            'Flashcards',
            'Flashcards',
            'manage_options',
            'ouinpo-flashcards',
            ['\\Ouinpo\\Flashcards\\Admin\\ScreenFlashcards', 'render']
        );
    }

    public static function handle_save_deck(): void
    {
        self::guard();
        try {
            $deck_id = Service::save_deck(wp_unslash($_POST));
            self::redirect(['tab' => 'decks', 'deck_saved' => 1, 'deck_id' => $deck_id]);
        } catch (\Throwable $e) {
            self::redirect(['tab' => 'decks', 'fc_error' => rawurlencode($e->getMessage())]);
        }
    }

    public static function handle_delete_deck(): void
    {
        self::guard();
        $deck_id = isset($_POST['deck_id']) ? (int) $_POST['deck_id'] : 0;
        if ($deck_id > 0) {
            Service::delete_deck($deck_id);
        }
        self::redirect(['tab' => 'decks', 'deck_deleted' => 1]);
    }

    public static function handle_save_card(): void
    {
        self::guard();
        $deck_id = isset($_POST['deck_id']) ? (int) $_POST['deck_id'] : 0;
        try {
            $card_id = Service::save_card(wp_unslash($_POST));
            self::redirect(['tab' => 'cards', 'deck_id' => $deck_id, 'card_saved' => 1, 'card_id' => $card_id]);
        } catch (\Throwable $e) {
            self::redirect(['tab' => 'cards', 'deck_id' => $deck_id, 'fc_error' => rawurlencode($e->getMessage())]);
        }
    }

    public static function handle_delete_card(): void
    {
        self::guard();
        $card_id = isset($_POST['card_id']) ? (int) $_POST['card_id'] : 0;
        $deck_id = isset($_POST['deck_id']) ? (int) $_POST['deck_id'] : 0;
        if ($card_id > 0) {
            Service::delete_card($card_id);
        }
        self::redirect(['tab' => 'cards', 'deck_id' => $deck_id, 'card_deleted' => 1]);
    }

    public static function handle_import_cards(): void
    {
        self::guard();
        $deck_id = isset($_POST['deck_id']) ? (int) $_POST['deck_id'] : 0;
        $csv = isset($_POST['csv']) ? (string) wp_unslash($_POST['csv']) : '';
        try {
            $count = Service::import_cards_from_csv($deck_id, $csv);
            self::redirect(['tab' => 'import', 'deck_id' => $deck_id, 'imported' => $count]);
        } catch (\Throwable $e) {
            self::redirect(['tab' => 'import', 'deck_id' => $deck_id, 'fc_error' => rawurlencode($e->getMessage())]);
        }
    }

    private static function guard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }
        check_admin_referer('ouinpo_fc_admin_action');
    }

    private static function redirect(array $params): void
    {
        $base = admin_url('admin.php?page=ouinpo-flashcards');
        wp_safe_redirect(add_query_arg($params, $base));
        exit;
    }
}
