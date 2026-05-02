<?php
namespace Ouinpo\Suite\Modules\Meta;

use Ouinpo\Suite\Core\ModuleInterface;

final class Module implements ModuleInterface
{
    private bool $loaded = false;

    public function id(): string
    {
        return 'meta';
    }

    public function name(): string
    {
        return 'OuInPo Meta Description';
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

        // Évite un double chargement si l’ancien plugin standalone
        // est encore actif par erreur.
        if (class_exists('Ouinpo_Meta_Social', false)) {
            $this->loaded = true;
            return;
        }

        $file = __DIR__ . '/plugin/ouinpo-meta-description.php';

        if (is_file($file)) {
            require_once $file;
        }

        $this->loaded = true;
    }
}