<?php

namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class PracticalFiles
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
        'html', 'htm', 'svg', 'js', 'mjs',
        'exe', 'sh', 'bat', 'cmd', 'com', 'msi',
        'htaccess',
    ];

    private const ALLOWED_MIMES = [
        'txt'  => 'text/plain',
        'pdf'  => 'application/pdf',
        'py'   => 'text/x-python',
        'sql'  => 'application/sql',
        'csv'  => 'text/csv',
        'json' => 'application/json',
        'md'   => 'text/markdown',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'zip'  => 'application/zip',
    ];

    private static function table(string $suffix): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    private static function normalize_folder_name(string $name): string
    {
        $name = remove_accents($name);
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim((string) $name, '_');

        return $name !== '' ? $name : 'practical_subject';
    }

    public static function get_folder_name_for_exercise(int $exercise_id): string
    {
        global $wpdb;

        $tExam = self::table('exam_meta');

        $folder = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT subject_group
             FROM {$tExam}
             WHERE exercise_id = %d
             LIMIT 1",
            $exercise_id
        ));

        $folder = self::normalize_folder_name($folder);

        if ($folder === '' || $folder === 'practical_subject') {
            $folder = 'practical_' . $exercise_id;
        }

        return $folder;
    }

    public static function get_subject_dir(int $exercise_id): array
    {
        $uploads = wp_upload_dir();
        $folder = self::get_folder_name_for_exercise($exercise_id);
        $subdir = '/ouinpo/practical/' . $folder;
        $path = $uploads['basedir'] . $subdir;
        $url = $uploads['baseurl'] . $subdir;

        self::protect_directory($uploads['basedir'] . '/ouinpo');
        self::protect_directory($uploads['basedir'] . '/ouinpo/practical');
        self::protect_directory($path);

        return [
            'folder_name' => $folder,
            'path'        => $path,
            'url'         => $url,
            'subdir'      => $subdir,
        ];
    }

    public static function store_uploaded_file(array $file, int $exercise_id): array|\WP_Error
    {
        if (empty($file['tmp_name']) || empty($file['name'])) {
            return new \WP_Error('missing_file', 'Fichier manquant.');
        }

        $filename = self::validate_file_name((string) $file['name']);
        if (is_wp_error($filename)) {
            return $filename;
        }

        $mime = self::validate_uploaded_mime($file);
        if (is_wp_error($mime)) {
            return $mime;
        }

        $dir = self::get_subject_dir($exercise_id);
        $filename = wp_unique_filename($dir['path'], $filename);
        $target = trailingslashit($dir['path']) . $filename;

        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            return new \WP_Error('move_failed', 'Impossible de déplacer le fichier.');
        }

        $stat = @stat(dirname($target));
        $perms = $stat ? ($stat['mode'] & 0000666) : 0644;
        @chmod($target, $perms);

        return [
            'folder_name' => $dir['folder_name'],
            'filename'    => $filename,
            'path'        => $target,
            'url'         => trailingslashit($dir['url']) . $filename,
        ];
    }

    public static function is_allowed_file_name(string $filename): bool
    {
        return !is_wp_error(self::validate_file_name($filename));
    }

    public static function allowed_mimes(): array
    {
        return self::ALLOWED_MIMES;
    }

    public static function count_blocked_existing_files(): int
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return 0;
        }

        $root = trailingslashit((string) $uploads['basedir']) . 'ouinpo/practical';
        if (!is_dir($root)) {
            return 0;
        }

        $blocked = 0;

        try {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($items as $item) {
                if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                    continue;
                }

                $filename = $item->getFilename();
                if (in_array($filename, ['index.html', '.htaccess'], true)) {
                    continue;
                }

                if (!self::is_allowed_file_name($filename)) {
                    $blocked++;
                }
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $blocked;
    }

    private static function validate_file_name(string $filename): string|\WP_Error
    {
        $filename = sanitize_file_name(wp_basename($filename));
        $filename = trim($filename);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return new \WP_Error('invalid_filename', 'Nom de fichier invalide.');
        }

        if ($filename[0] === '.' || str_starts_with(strtolower($filename), 'htaccess')) {
            return new \WP_Error('blocked_filename', 'Nom de fichier interdit.');
        }

        $parts = array_values(array_filter(explode('.', strtolower($filename)), static fn($part) => $part !== ''));
        $extension = end($parts);

        if (!is_string($extension) || !isset(self::ALLOWED_MIMES[$extension])) {
            return new \WP_Error('unsupported_file_type', 'Type de fichier non autorisé.');
        }

        foreach ($parts as $part) {
            if (in_array($part, self::BLOCKED_EXTENSIONS, true)) {
                return new \WP_Error('dangerous_extension', 'Extension de fichier dangereuse.');
            }
        }

        return $filename;
    }

    private static function validate_uploaded_mime(array $file)
    {
        $filename = isset($file['name']) ? sanitize_file_name((string) $file['name']) : '';
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = self::ALLOWED_MIMES;

        if ($extension === '' || !isset($allowed[$extension])) {
            return new \WP_Error('unsupported_file_type', 'Type de fichier non autorisé.');
        }

        $check = wp_check_filetype_and_ext((string) ($file['tmp_name'] ?? ''), $filename, $allowed);

        if (!empty($check['ext']) && $check['ext'] !== $extension) {
            return new \WP_Error('mime_mismatch', 'Le type du fichier ne correspond pas à son extension.');
        }

        if (!empty($check['type'])) {
            return true;
        }

        $declared = isset($file['type']) ? strtolower((string) $file['type']) : '';
        $text_like = ['txt', 'py', 'sql', 'csv', 'json', 'md'];
        if (in_array($extension, $text_like, true) && in_array($declared, ['', 'text/plain', 'application/octet-stream'], true)) {
            return true;
        }

        return new \WP_Error('mime_not_allowed', 'Type MIME non autorisé.');
    }

    private static function protect_directory(string $path): void
    {
        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }

        if (!is_dir($path) || !is_writable($path)) {
            return;
        }

        $index = trailingslashit($path) . 'index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }

        $htaccess = trailingslashit($path) . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|asp|aspx|jsp|sh|bat|cmd)$\">\nRequire all denied\n</FilesMatch>\n");
        }
    }
}
