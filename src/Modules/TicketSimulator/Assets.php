<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
use Ouinpo\Suite\Core\Assets as CoreAssets;
defined('ABSPATH') || exit;

final class Assets
{
    public static function enqueue(bool $admin = false): void
    {
        $deps = wp_style_is('ouinpo-core-css', 'registered') ? ['ouinpo-core-css'] : [];
        CoreAssets::enqueueStyle('ouinpo-ticketing', 'assets/css/front/ticket-simulator.css', $deps);
        CoreAssets::enqueueScript('ouinpo-ticketing', 'assets/js/front/ticket-simulator.js');
        wp_localize_script('ouinpo-ticketing', 'OuinpoTicketing', [
            'canDeleteAllAttempts' => PermissionService::all(),
            'root' => esc_url_raw(rest_url(RestController::NS)), 'nonce' => wp_create_nonce('wp_rest'), 'name' => Module::label(),
        ]);
        if ($admin) {
            CoreAssets::enqueueStyle('ouinpo-ticketing-admin', 'assets/css/admin/ticket-simulator-admin.css', ['ouinpo-ticketing']);
            CoreAssets::enqueueScript('ouinpo-ticketing-admin', 'assets/js/admin/ticket-simulator-admin.js', ['ouinpo-ticketing']);
        }
    }
}
