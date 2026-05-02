<?php
namespace Ouinpo\Suite\Modules\SegFault;

use Ouinpo\Suite\Core\ModuleInterface;

final class Module implements ModuleInterface
{
    private bool $loaded = false;

    public function id(): string
    {
        return 'segfault';
    }

    public function name(): string
    {
        return 'OuInPo SegFault Chat';
    }

    public function boot(): void
    {
        $this->loadNativePlugin();
        $this->loadNotifierAddon();
    }

    public function activate(): void
    {
        $this->loadNativePlugin();
        $this->loadNotifierAddon();
        $this->install();
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

        if (defined('OUINPO_SF_DIR') || class_exists(\OuInPo\SegFault\DB::class, false)) {
            $this->loaded = true;
            return;
        }

        $file = __DIR__ . '/plugin/segfault.php';

        if (is_file($file)) {
            require_once $file;
        }

        $this->loaded = true;
    }

    private function loadNotifierAddon(): void
    {
        // Évite double chargement si l’ancien plugin standalone est actif par erreur
        if (class_exists(\OuInPo_Segfault_Notifier::class, false) || class_exists('OuInPo_Segfault_Notifier', false)) {
            return;
        }

        $file = __DIR__ . '/plugin/addons/ouinpo-segfault-notifier.php';

        if (is_file($file)) {
            require_once $file;
        }
    }

    private function install(): void
    {
        if (class_exists(\OuInPo\SegFault\Storage::class)) {
            \OuInPo\SegFault\Storage::ensure_dirs();
            \OuInPo\SegFault\Storage::migrate_legacy_assets();
        } elseif (defined('OUINPO_SF_DATA_DIR')) {
            if (!is_dir(OUINPO_SF_DATA_DIR)) {
                wp_mkdir_p(OUINPO_SF_DATA_DIR);
            }
            if (defined('OUINPO_SF_SRC') && !is_dir(OUINPO_SF_SRC)) {
                wp_mkdir_p(OUINPO_SF_SRC);
            }
        }

        if (class_exists(\OuInPo\SegFault\DB::class)) {
            \OuInPo\SegFault\DB::init();
        }

        if (function_exists('ouinpo_sf_ensure_progress_tables')) {
            ouinpo_sf_ensure_progress_tables();
        }

        if (function_exists('ouinpo_sf_ensure_suggestions_table')) {
            ouinpo_sf_ensure_suggestions_table();
        }
    }
}