<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;

use Ouinpo\Suite\Core\ModuleInterface;

defined('ABSPATH') || exit;

final class Module implements ModuleInterface
{
    private bool $loaded = false;
    public function id(): string { return 'ticket_simulator'; }
    public function name(): string { return self::label(); }
    public static function label(): string { return (string) apply_filters('ouinpo_ticket_simulator_name', 'PataDesk'); }
    public function activate(): void { Installer::install(); }
    public function deactivate(): void { /* Preserve all teaching data. */ }
    public function boot(): void
    {
        if ($this->loaded) { return; }
        $this->loaded = true;
        Installer::maybeUpgrade();
        RestController::init();
        Shortcodes::init();
        if (is_admin()) { AdminPage::init(); }
    }
}
