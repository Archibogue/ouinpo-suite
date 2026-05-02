<?php
namespace Ouinpo\Suite\Modules\RechText;

use Ouinpo\Suite\Core\ModuleInterface;

final class Module implements ModuleInterface
{
    private bool $loaded = false;

    public function id(): string
    {
        return 'rechtext';
    }

    public function name(): string
    {
        return 'OuInPo Recherche Textuelle';
    }

    public function boot(): void
    {
        $this->loadNativePlugin();
    }

    public function activate(): void
    {
        $this->loadNativePlugin();
    }

    public function deactivate(): void
    {
        // Rien pour l’instant
    }

    private function loadNativePlugin(): void
    {
        if ($this->loaded) {
            return;
        }

        $file = __DIR__ . '/plugin/ouinpo_recherche_textuelle.php';

        if (is_file($file)) {
            require_once $file;
        }

        $this->loaded = true;
    }
}