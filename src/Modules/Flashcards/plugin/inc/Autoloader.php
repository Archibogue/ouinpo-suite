<?php
namespace Ouinpo\Flashcards;

defined('ABSPATH') || exit;

final class Autoloader {
    public static function init(string $baseDir): void {
        spl_autoload_register(function($class) use ($baseDir) {
            $ns = 'Ouinpo\\Flashcards\\';
            if (strpos($class, $ns) !== 0) {
                return;
            }

            $relative = substr($class, strlen($ns));
            $relativePath = str_replace('\\', '/', $relative);

            $paths = [
                $baseDir . '/inc/' . $relativePath . '.php',
                $baseDir . '/inc/Rest/' . basename($relativePath) . '.php',
            ];

            foreach ($paths as $path) {
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        });
    }
}
