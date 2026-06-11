<?php

namespace Ouinpo\Suite\Core\Admin;

defined('ABSPATH') || exit;

final class AdminMenuRegistry
{
    public static function suiteActive(): bool
    {
        return defined('OUINPO_SUITE_ADMIN_SLUG');
    }

    public static function rootSlug(): string
    {
        return self::suiteActive() ? (string) OUINPO_SUITE_ADMIN_SLUG : 'ouinpo-suite';
    }

    public static function legacyParent(string $fallbackParent): ?string
    {
        return self::suiteActive() ? null : $fallbackParent;
    }

    public static function addLegacySubmenu(
        string $fallbackParent,
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        callable $callback
    ): void {
        add_submenu_page(
            self::legacyParent($fallbackParent),
            $pageTitle,
            $menuTitle,
            $capability,
            $menuSlug,
            $callback
        );
    }

    public static function addSuiteSubmenu(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        callable $callback
    ): void {
        add_submenu_page(
            self::rootSlug(),
            $pageTitle,
            $menuTitle,
            $capability,
            $menuSlug,
            $callback
        );
    }
}
