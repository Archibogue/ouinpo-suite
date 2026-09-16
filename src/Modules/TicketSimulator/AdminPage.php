<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
use Ouinpo\Suite\Core\Admin\AdminMenuRegistry;
defined('ABSPATH') || exit;

final class AdminPage
{
    public static function init(): void
    {
        add_action('admin_menu', static function () {
            if (PermissionService::manage() || \Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::TICKET_OBSERVE)) {
                AdminMenuRegistry::addSuiteSubmenu(Module::label(), Module::label(), 'read', 'ouinpo-ticket-simulator', [self::class, 'render']);
            }
        }, 30);
        add_action('admin_enqueue_scripts', static function () {
            if (($_GET['page'] ?? '') === 'ouinpo-ticket-simulator') { Assets::enqueue(true); }
        });
    }
    public static function render(): void
    {
        if (!PermissionService::manage() && !\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::TICKET_OBSERVE)) { wp_die('Accès refusé.'); }
        echo '<div class="wrap ouinpo-ticketing" data-ticket-admin data-can-manage="' . (PermissionService::manage() ? '1' : '0') . '"><h1>' . esc_html(Module::label()) . '</h1><p role="status">Chargement des scénarios…</p></div>';
    }
}
