<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class Shortcodes
{
    public static function init(): void
    {
        add_shortcode('ouinpo_ticket_simulator', [self::class, 'render']);
        // Enqueue before the theme prints its head on ordinary shortcode pages.
        add_action('wp_enqueue_scripts', static function () {
            global $post;
            if ($post && has_shortcode($post->post_content, 'ouinpo_ticket_simulator')) { Assets::enqueue(); }
        });
    }
    public static function render(): string
    {
        if (!is_user_logged_in() || !\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::TICKET_PRACTICE)) { return '<p>Connectez-vous avec un compte autorisé au suivi PataDesk.</p>'; }
        Assets::enqueue();
        return '<div class="ouinpo-ticketing" data-ticket-student><p role="status">Chargement du centre de services…</p></div>';
    }
}
