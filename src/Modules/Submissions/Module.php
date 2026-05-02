<?php
namespace Ouinpo\Suite\Modules\Submissions;

use Ouinpo\Suite\Core\ModuleInterface;

final class Module implements ModuleInterface
{
    private bool $loaded = false;

    public function id(): string
    {
        return 'submissions';
    }

    public function name(): string
    {
        return 'OuInPo Dépôts & Ressources';
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

        // Si l’ancien plugin standalone est encore actif par erreur,
        // on évite un double chargement et surtout une redéclaration de classe.
        if (class_exists('Ouinpo_Submissions_Plugin', false)) {
            $this->loaded = true;
            return;
        }

        $file = __DIR__ . '/plugin/ouinpo-submissions.php';

        if (is_file($file)) {
            require_once $file;
        }

        $this->loaded = true;
    }
}