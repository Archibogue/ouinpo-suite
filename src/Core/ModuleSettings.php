<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class ModuleSettings
{
    public const OPTION_KEY = 'ouinpo_suite_enabled_modules';

    private static ?array $enabledCache = null;

    /**
     * Identifiants des modules declarés par la suite.
     */
    public static function availableModules(): array
    {
        return [
            'exercises',
            'flashcards',
            'submissions',
            'segfault',
            'gate',
            'rechtext',
            'meta',
            'projects',
        ];
    }

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
        if (self::$enabledCache !== null) {
            return self::$enabledCache;
        }

        $raw = get_option(self::OPTION_KEY, null);

        if (!is_array($raw)) {
            self::$enabledCache = self::defaultEnabledModules();

            return self::$enabledCache;
        }

        self::$enabledCache = self::normalizeModules($raw);

        return self::$enabledCache;
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
        $clean = self::normalizeModules($moduleIds);

        update_option(self::OPTION_KEY, $clean, false);
        self::$enabledCache = null;
    }

    private static function normalizeModules(array $moduleIds): array
    {
        $allowed = array_fill_keys(self::availableModules(), true);
        $clean = [];

        foreach ($moduleIds as $moduleId) {
            $moduleId = sanitize_key((string) $moduleId);

            if ($moduleId !== '' && isset($allowed[$moduleId])) {
                $clean[] = $moduleId;
            }
        }

        foreach (self::lockedModules() as $locked) {
            if (isset($allowed[$locked])) {
                $clean[] = $locked;
            }
        }

        return array_values(array_unique($clean));
    }
}
