<?php
namespace OuInPo\SegFault;

defined('ABSPATH') || exit;

final class Storage
{
    public static function plugin_dir(): string
    {
        return trailingslashit(dirname(__DIR__));
    }

    public static function legacy_data_dir(): string
    {
        return self::plugin_dir() . 'data';
    }

    public static function legacy_sources_dir(): string
    {
        return self::plugin_dir() . 'sources';
    }

    public static function legacy_db_path(): string
    {
        return self::legacy_data_dir() . '/segfault.db';
    }

    public static function uploads_base_dir(): string
    {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['basedir']) . 'ouinpo/segfault';
    }

    public static function data_dir(): string
    {
        return self::uploads_base_dir() . '/data';
    }

    public static function sources_dir(): string
    {
        return trailingslashit(self::uploads_base_dir() . '/sources');
    }

    public static function db_path(): string
    {
        return self::data_dir() . '/segfault.db';
    }

    public static function ensure_dirs(): void
    {
        $dirs = [
            self::uploads_base_dir(),
            self::data_dir(),
            untrailingslashit(self::sources_dir()),
        ];
    
        foreach ($dirs as $dir) {
            wp_mkdir_p($dir);
    
            $index = trailingslashit($dir) . 'index.php';
            if (!file_exists($index)) {
                @file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
    
            $htaccess = trailingslashit($dir) . '.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents(
                    $htaccess,
                    "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
                );
            }
        }
    }

    public static function migrate_legacy_assets(): void
    {
        self::ensure_dirs();

        // SQLite
        if (!file_exists(self::db_path()) && file_exists(self::legacy_db_path())) {
            @copy(self::legacy_db_path(), self::db_path());

            $wal_legacy = self::legacy_db_path() . '-wal';
            $shm_legacy = self::legacy_db_path() . '-shm';
            $wal_new    = self::db_path() . '-wal';
            $shm_new    = self::db_path() . '-shm';

            if (file_exists($wal_legacy) && !file_exists($wal_new)) {
                @copy($wal_legacy, $wal_new);
            }
            if (file_exists($shm_legacy) && !file_exists($shm_new)) {
                @copy($shm_legacy, $shm_new);
            }
        }

        // Sources privées : on copie sans écraser
        $legacy_sources = glob(self::legacy_sources_dir() . '/*') ?: [];
        foreach ($legacy_sources as $src) {
            if (!is_file($src)) {
                continue;
            }

            $dest = self::sources_dir() . basename($src);
            if (!file_exists($dest)) {
                @copy($src, $dest);
            }
        }
    }
}