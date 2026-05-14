<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class ModuleSettings
{
    public const OPTION_KEY = 'ouinpo_suite_enabled_modules';

    /**
     * Le module Exercices reste le socle de la suite.
     * Il n'est pas désactivable dans cette première version.
     */
    public static function lockedModules(): array
    {
        return [
            'exercises',
        ];
    }

    /**
     * Modules activés par défaut sur une installation neuve.
     */
    public static function defaultEnabledModules(): array
    {
        return [
            'exercises',
            'flashcards',
        ];
    }

    public static function getEnabledModules(): array
    {
        $raw = get_option(self::OPTION_KEY, null);

        if (!is_array($raw)) {
            return self::defaultEnabledModules();
        }

        $enabled = array_values(array_unique(array_map('sanitize_key', $raw)));

        foreach (self::lockedModules() as $locked) {
            if (!in_array($locked, $enabled, true)) {
                $enabled[] = $locked;
            }
        }

        return $enabled;
    }

    public static function isEnabled(string $moduleId): bool
    {
        $moduleId = sanitize_key($moduleId);

        if (in_array($moduleId, self::lockedModules(), true)) {
            return true;
        }

        return in_array($moduleId, self::getEnabledModules(), true);
    }

    public static function saveEnabledModules(array $moduleIds): void
    {
        $clean = [];

        foreach ($moduleIds as $moduleId) {
            $moduleId = sanitize_key((string) $moduleId);

            if ($moduleId !== '') {
                $clean[] = $moduleId;
            }
        }

        foreach (self::lockedModules() as $locked) {
            $clean[] = $locked;
        }

        $clean = array_values(array_unique($clean));

        update_option(self::OPTION_KEY, $clean, false);
    }
}
