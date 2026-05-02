<?php
namespace Ouinpo\Suite\Modules\Flashcards;

use Ouinpo\Suite\Core\ModuleInterface;

final class Module implements ModuleInterface
{
    private bool $loaded = false;

    public function id(): string
    {
        return 'flashcards';
    }

    public function name(): string
    {
        return 'OuInPo Flashcards';
    }

    public function boot(): void
    {
        $this->loadNativePlugin();

        if (class_exists(\Ouinpo\Flashcards\ModuleInstaller::class)) {
            \Ouinpo\Flashcards\ModuleInstaller::maybe_upgrade();
        }
    }

    public function activate(): void
    {
        $this->loadNativePlugin();

        if (class_exists(\Ouinpo\Flashcards\ModuleInstaller::class)) {
            \Ouinpo\Flashcards\ModuleInstaller::activate();
        }
    }

    public function deactivate(): void
    {
        // Rien pour l'instant
    }

    private function loadNativePlugin(): void
    {
        if ($this->loaded) {
            return;
        }

        if (defined('OUINPO_FLASHCARDS_PLUGIN_FILE')) {
            $this->loaded = true;
            return;
        }

        $file = __DIR__ . '/plugin/ouinpo-flashcards.php';
        if (is_file($file)) {
            require_once $file;
        }

        $this->loaded = true;
    }
}
