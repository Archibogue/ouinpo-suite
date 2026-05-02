<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

/**
 * Autoloader minimal et robuste.
 * - Charge les classes dans inc/ et inc/Rest/
 * - Compatibilité avec ancien namespace "OuInPo\Exo"
 * - /admin et /public restent inclus via require_once dans le plugin principal.
 */
final class Autoloader {
  public static function init(string $baseDir): void {

    spl_autoload_register(function($class) use ($baseDir) {

      // Compatibilité double namespace
        $namespaces = [
          'Ouinpo\\Exercises\\',
          'OuInPo\\Exercises\\', // compatibilité casse historique
          'OuInPo\\Exo\\',       // ancien préfixe encore présent dans certains fichiers
        ];
      foreach ($namespaces as $ns) {
        if (strpos($class, $ns) === 0) {
          // Transforme "Ouinpo\Exercises\Foo\Bar" -> "Foo/Bar"
          $relative = substr($class, strlen($ns));
          $relativePath = str_replace('\\', '/', $relative);

          // Candidats : inc/Foo/Bar.php et inc/Rest/Foo/Bar.php
          $paths = [
            $baseDir . '/inc/' . $relativePath . '.php',
            $baseDir . '/inc/Rest/' . basename($relativePath) . '.php', // compat routeurs
          ];

          foreach ($paths as $path) {
            if (is_file($path)) {
              require $path;
              return;
            }
          }
        }
      }
      // Silence volontaire si classe introuvable
    });
  }
}
