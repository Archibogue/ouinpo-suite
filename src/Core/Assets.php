<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class Assets
{
    public static function version(string $relativePath, string $fallback = ''): string
    {
        $baseDir = self::baseDir();
        $file = $baseDir !== '' ? $baseDir . ltrim($relativePath, '/\\') : '';

        if ($file !== '' && file_exists($file)) {
            return (string) filemtime($file);
        }

        if ($fallback !== '') {
            return $fallback;
        }

        return defined('OUINPO_SUITE_VERSION') ? (string) OUINPO_SUITE_VERSION : '1.0.0';
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
