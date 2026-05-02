<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class ModuleInstaller
{
    public static function activate(): void
    {
        self::maybe_upgrade();
    }

    public static function maybe_upgrade(): void
    {
        if (class_exists(InstallV2::class)) {
            InstallV2::maybe_upgrade();
        }
    }
}