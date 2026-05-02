<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

class Install
{
    public static function activate()
    {
        if (class_exists(ModuleInstaller::class)) {
            ModuleInstaller::activate();
        }
    }

    public static function maybe_upgrade()
    {
        if (class_exists(ModuleInstaller::class)) {
            ModuleInstaller::maybe_upgrade();
        }
    }
}