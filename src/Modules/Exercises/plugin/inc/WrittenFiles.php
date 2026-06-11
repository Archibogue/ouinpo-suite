<?php

namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class WrittenFiles
{
    private const MAX_UPLOAD_BYTES = 10485760;
    private const SIGNED_DOWNLOAD_TTL = 1200;

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

    public static function max_upload_bytes(): int
    {
        return max(1, (int) apply_filters('ouinpo_written_file_max_upload_bytes', self::MAX_UPLOAD_BYTES));
    }

    public static function download_url(int $file_id): string
    {
        if ($file_id <= 0) {
            return '';
        }

        $url = rest_url('ouinpo/v1/written-files/' . $file_id . '/download');
        if (is_user_logged_in()) {
            $url = add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), $url);
        }

        return $url;
    }

    public static function signed_download_url_for_upload_url(string $file_url, int $ttl = self::SIGNED_DOWNLOAD_TTL): string
    {
        $relative = self::relative_path_from_upload_url($file_url);
        if ($relative === '') {
            return '';
        }

        $expires = time() + max(60, min(DAY_IN_SECONDS, $ttl));
        $signature = self::signature($relative, $expires);

        return add_query_arg([
            'path' => $relative,
            'expires' => $expires,
            'signature' => $signature,
        ], rest_url('ouinpo/v1/written-files/download'));
    }

    public static function ensure_storage_protection(): void
    {
        $uploads = wp_upload_dir();
        $base = trailingslashit((string) ($uploads['basedir'] ?? ''));
        if ($base === '') {
            return;
        }

        self::protect_directory($base . 'ouinpo');
        self::protect_directory($base . 'ouinpo/written', true);
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

        self::ensure_storage_protection();
        self::protect_directory($path, true);

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

        $size = self::validate_upload_size($file);
        if (is_wp_error($size)) {
            return $size;
        }

        $original_filename = self::validate_file_name((string) $file['name']);
        if (is_wp_error($original_filename)) {
            return $original_filename;
        }

        $mime = self::validate_uploaded_mime($file);
        if (is_wp_error($mime)) {
            return $mime;
        }

        $dir = self::get_subject_dir($folder_seed, $subject_id);
        $filename = wp_unique_filename($dir['path'], $original_filename);
        $target = trailingslashit($dir['path']) . $filename;

        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return new \WP_Error('move_failed', 'Impossible de deplacer le fichier.');
        }

        @chmod($target, 0644);

        return [
            'filename'          => $filename,
            'original_filename' => $original_filename,
            'size'              => is_file($target) ? (int) filesize($target) : $size,
            'hash'              => is_file($target) ? (string) hash_file('sha256', $target) : '',
            'path'              => $target,
            'url'               => trailingslashit($dir['url']) . rawurlencode($filename),
        ];
    }

    public static function local_path_from_upload_url(string $file_url): string
    {
        $relative = self::relative_path_from_upload_url($file_url);

        return $relative !== '' ? self::local_path_from_relative_path($relative) : '';
    }

    public static function local_path_from_relative_path(string $relative): string
    {
        $relative = trim(rawurldecode($relative));
        $relative = str_replace('\\', '/', $relative);
        $relative = ltrim($relative, '/');

        if ($relative === '' || str_contains($relative, '..') || !str_starts_with($relative, 'ouinpo/written/')) {
            return '';
        }

        $uploads = wp_upload_dir();
        $base_dir = wp_normalize_path((string) ($uploads['basedir'] ?? ''));
        if ($base_dir === '') {
            return '';
        }

        $path = wp_normalize_path(trailingslashit($base_dir) . $relative);
        $safe_base = wp_normalize_path(trailingslashit($base_dir) . 'ouinpo/written/');

        return str_starts_with($path, $safe_base) ? $path : '';
    }

    public static function verify_signed_download(string $relative, int $expires, string $signature): bool
    {
        if ($expires < time() || $signature === '') {
            return false;
        }

        return hash_equals(self::signature($relative, $expires), $signature);
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

    private static function validate_upload_size(array $file)
    {
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 && !empty($file['tmp_name']) && is_file((string) $file['tmp_name'])) {
            $size = (int) filesize((string) $file['tmp_name']);
        }

        if ($size <= 0) {
            return new \WP_Error('empty_file', 'Fichier vide.');
        }

        $max = self::max_upload_bytes();
        if ($size > $max) {
            return new \WP_Error('file_too_large', sprintf(
                'Fichier trop volumineux. Taille maximale : %s Mo.',
                number_format_i18n($max / 1048576, 1)
            ));
        }

        return $size;
    }

    private static function relative_path_from_upload_url(string $file_url): string
    {
        $file_url = esc_url_raw(trim($file_url));
        if ($file_url === '') {
            return '';
        }

        $uploads = wp_upload_dir();
        $base_url = rtrim((string) ($uploads['baseurl'] ?? ''), '/');
        if ($base_url === '' || !str_starts_with($file_url, $base_url . '/')) {
            return '';
        }

        $relative = rawurldecode(ltrim(substr($file_url, strlen($base_url)), '/'));
        $relative = str_replace('\\', '/', $relative);

        return $relative !== '' && !str_contains($relative, '..') && str_starts_with($relative, 'ouinpo/written/')
            ? $relative
            : '';
    }

    private static function signature(string $relative, int $expires): string
    {
        return hash_hmac('sha256', $relative . '|' . $expires, wp_salt('auth'));
    }

    private static function protect_directory(string $path, bool $deny_direct_access = false): void
    {
        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }

        $index = trailingslashit($path) . 'index.html';
        if (!file_exists($index)) {
            file_put_contents($index, '');
        }

        $htaccess = trailingslashit($path) . '.htaccess';
        $rules = $deny_direct_access
            ? "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            : "Options -Indexes\n";
        if (!file_exists($htaccess) || (string) file_get_contents($htaccess) !== $rules) {
            file_put_contents($htaccess, $rules);
        }
    }
}
