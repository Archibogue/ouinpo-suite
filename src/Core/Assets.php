<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class Assets
{
    private static array $versionCache = [];

    public static function version(string $relativePath, string $fallback = ''): string
    {
        $baseDir = self::baseDir();
        $file = $baseDir !== '' ? $baseDir . ltrim($relativePath, '/\\') : '';

        return self::fileVersion($file, $fallback);
    }

    public static function fileVersion(string $file, string $fallback = ''): string
    {
        $cacheKey = $file !== '' ? $file : '__missing__:' . $fallback;

        if (isset(self::$versionCache[$cacheKey])) {
            return self::$versionCache[$cacheKey];
        }

        if ($file !== '' && file_exists($file)) {
            self::$versionCache[$cacheKey] = (string) filemtime($file);

            return self::$versionCache[$cacheKey];
        }

        if ($fallback !== '') {
            self::$versionCache[$cacheKey] = $fallback;

            return self::$versionCache[$cacheKey];
        }

        self::$versionCache[$cacheKey] = defined('OUINPO_SUITE_VERSION') ? (string) OUINPO_SUITE_VERSION : '1.0.0';

        return self::$versionCache[$cacheKey];
    }

    public static function url(string $relativePath): string
    {
        $baseUrl = self::baseUrl();
        return $baseUrl !== '' ? $baseUrl . ltrim($relativePath, '/\\') : '';
    }

    public static function enqueueStyle(string $handle, string $relativePath, array $deps = []): void
    {
        $url = self::url($relativePath);
        if ($url === '') {
            return;
        }

        wp_enqueue_style($handle, $url, $deps, self::version($relativePath));
    }

    public static function enqueueScript(string $handle, string $relativePath, array $deps = [], bool $inFooter = true): void
    {
        $url = self::url($relativePath);
        if ($url === '') {
            return;
        }

        wp_enqueue_script($handle, $url, $deps, self::version($relativePath), $inFooter);
    }

    public static function registerStyle(string $handle, string $relativePath, array $deps = []): void
    {
        $url = self::url($relativePath);
        if ($url === '') {
            return;
        }

        wp_register_style($handle, $url, $deps, self::version($relativePath));
    }

    public static function registerScript(string $handle, string $relativePath, array $deps = [], bool $inFooter = true): void
    {
        $url = self::url($relativePath);
        if ($url === '') {
            return;
        }

        wp_register_script($handle, $url, $deps, self::version($relativePath), $inFooter);
    }

    private static function baseUrl(): string
    {
        return defined('OUINPO_SUITE_URL') ? (string) OUINPO_SUITE_URL : '';
    }

    private static function baseDir(): string
    {
        return defined('OUINPO_SUITE_DIR') ? (string) OUINPO_SUITE_DIR : '';
    }
}
