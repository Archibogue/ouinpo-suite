<?php
namespace Ouinpo\Suite\Core;

final class Compat
{
    public static function ensureUploadsLayout(): void
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['basedir'])) {
            wp_mkdir_p($uploads['basedir'] . '/ouinpo/segfault/data');
            wp_mkdir_p($uploads['basedir'] . '/ouinpo/segfault/sources');
        }
    }
}