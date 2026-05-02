<?php
namespace Ouinpo\Suite\Modules\Exercises;

use Ouinpo\Suite\Core\ModuleInterface;

final class Module implements ModuleInterface
{
    private bool $loaded = false;

    public function id(): string
    {
        return 'exercises';
    }

    public function name(): string
    {
        return 'OuInPo Exercices';
    }

    public function boot(): void
    {
        $this->loadNativePlugin();

        if (class_exists(\Ouinpo\Exercises\ModuleInstaller::class)) {
            \Ouinpo\Exercises\ModuleInstaller::maybe_upgrade();
        }
    }

    public function activate(): void
    {
        $this->loadNativePlugin();

        if (class_exists(\Ouinpo\Exercises\ModuleInstaller::class)) {
            \Ouinpo\Exercises\ModuleInstaller::activate();
        }
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

        if (defined('OUINPO_EXO_PLUGIN_FILE')) {
            $this->loaded = true;
            return;
        }

        $file = __DIR__ . '/plugin/ouinpo-exercices.php';

        if (is_file($file)) {
            require_once $file;
        }

        $this->loaded = true;
    }
}