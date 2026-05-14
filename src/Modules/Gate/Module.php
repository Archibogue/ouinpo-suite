<?php

namespace Ouinpo\Suite\Modules\Gate;

use Ouinpo\Suite\Core\ModuleInterface;

final class Module implements ModuleInterface
{
    private bool $loaded = false;

    public function id(): string
    {
        return 'gate';
    }

    public function name(): string
    {
        return 'OuInPo Gate';
    }

    public function boot(): void
    {
        $this->loadNativePlugin();

        if (function_exists('ouinpo_gate_ensure_config')) {
            \ouinpo_gate_ensure_config();
        }
    }

    public function activate(): void
    {
        // Le schema partage est desormais gere uniquement
        // par \Ouinpo\Suite\Core\Installer.
        $this->loadNativePlugin();

        if (function_exists('ouinpo_gate_ensure_config')) {
            \ouinpo_gate_ensure_config();
        }
    }

    public function deactivate(): void
    {
        // Rien pour l'instant.
    }

    private function loadNativePlugin(): void
    {
        if ($this->loaded) {
            return;
        }

        // Si l'ancien plugin standalone est encore actif par erreur,
        // on evite un double chargement.
        if (function_exists('ouinpo_gate_salt') || function_exists('ouinpo_enigmes')) {
            $this->loaded = true;
            return;
        }

        $file = __DIR__ . '/plugin/ouinpo-gate.php';

        if (is_file($file)) {
            require_once $file;
        }

        $this->loaded = true;
    }
}
