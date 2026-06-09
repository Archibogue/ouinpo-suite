<?php

namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class WrittenFiles
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
        'html', 'htm', 'svg', 'js', 'mjs',
        'exe', 'sh', 'bat', 'cmd', 'com', 'msi',
        'htaccess',
    ];

    private const ALLOWED_MIMES = [
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'json' => 'application/json',
        'md'   => 'text/markdown',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'zip'  => 'application/zip',
    ];

    public static function allowed_mimes(): array
    {
        return self::ALLOWED_MIMES;
    }

    public static function get_subject_dir(string $folder_seed, int $subject_id): array
    {
        $uploads = wp_upload_dir();
        $folder = self::normalize_folder_name($folder_seed);

        if ($folder === 'written_subject') {
            $folder = 'written_' . max(1, $subject_id);
        }

        $subdir = '/ouinpo/written/' . $folder;
        $path = trailingslashit((string) $uploads['basedir']) . ltrim($subdir, '/');
        $url = trailingslashit((string) $uploads['baseurl']) . ltrim($subdir, '/');

        self::protect_directory(trailingslashit((string) $uploads['basedir']) . 'ouinpo');
        self::protect_directory(trailingslashit((string) $uploads['basedir']) . 'ouinpo/written');
        self::protect_directory($path);

        return [
            'folder_name' => $folder,
            'path'        => $path,
            'url'         => $url,
            'subdir'      => $subdir,
        ];
    }

    public static function store_uploaded_file(array $file, string $folder_seed, int $subject_id): array|\WP_Error
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

        $dir = self::get_subject_dir($folder_seed, $subject_id);
        $filename = wp_unique_filename($dir['path'], $filename);
        $target = trailingslashit($dir['path']) . $filename;

        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return new \WP_Error('move_failed', 'Impossible de deplacer le fichier.');
        }

        @chmod($target, 0644);

        return [
            'filename' => $filename,
            'path'     => $target,
            'url'      => trailingslashit($dir['url']) . rawurlencode($filename),
        ];
    }

    private static function normalize_folder_name(string $name): string
    {
        $name = remove_accents($name);
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $name = preg_replace('/_+/', '_', (string) $name);
        $name = trim((string) $name, '_');

        return $name !== '' ? $name : 'written_subject';
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
            return new \WP_Error('unsupported_file_type', 'Type de fichier non autorise.');
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

        if ($extension === '' || !isset(self::ALLOWED_MIMES[$extension])) {
            return new \WP_Error('unsupported_file_type', 'Type de fichier non autorise.');
        }

        $check = wp_check_filetype_and_ext((string) ($file['tmp_name'] ?? ''), $filename, self::ALLOWED_MIMES);

        if (!empty($check['ext']) && $check['ext'] !== $extension) {
            return new \WP_Error('mime_mismatch', 'Le type du fichier ne correspond pas a son extension.');
        }

        if (!empty($check['type'])) {
            return true;
        }

        $declared = isset($file['type']) ? strtolower((string) $file['type']) : '';
        $text_like = ['txt', 'csv', 'json', 'md'];
        if (in_array($extension, $text_like, true) && in_array($declared, ['', 'text/plain', 'application/octet-stream'], true)) {
            return true;
        }

        return new \WP_Error('mime_not_allowed', 'Type MIME non autorise.');
    }

    private static function protect_directory(string $path): void
    {
        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }

        $index = trailingslashit($path) . 'index.html';
        if (!file_exists($index)) {
            file_put_contents($index, '');
        }

        $htaccess = trailingslashit($path) . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n");
        }
    }
}
