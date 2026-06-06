<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\ModuleInterface;

defined('ABSPATH') || exit;

final class Module implements ModuleInterface
{
    private bool $loaded = false;

    public function id(): string
    {
        return 'projects';
    }

    public function name(): string
    {
        return 'SPOPI Projects';
    }

    public function boot(): void
    {
        $this->load();
    }

    public function activate(): void
    {
        $this->load();
    }

    public function deactivate(): void
    {
        // Les donnees projet sont conservees.
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        Assets::init();
        Shortcodes::init();
        RestController::init();

        if (is_admin()) {
            AdminPage::init();
        }

        $this->loaded = true;
    }
}
