<?php

namespace Ouinpo\Suite\Modules\Projects;

use WP_Error;

defined('ABSPATH') || exit;

final class PrivateFiles
{
    public const UPLOAD_SUBDIR = 'projects';
    public const META_PRIVATE_FILE = '_ouinpo_project_private_file';
    public const META_PROJECT_ID = '_ouinpo_project_id';
    public const META_EVIDENCE_ID = '_ouinpo_project_evidence_id';
    public const META_PRIVATE_PATH = '_ouinpo_project_private_path';

    private static string $activeSubdir = '';

    public static function filterUploadDir(array $dirs): array
    {
        if (self::$activeSubdir === '') {
            return $dirs;
        }

        $subdir = '/ouinpo/' . trim(self::$activeSubdir, '/');

        $dirs['subdir'] = $subdir;
        $dirs['path'] = $dirs['basedir'] . $subdir;
        $dirs['url'] = $dirs['baseurl'] . $subdir;

        return $dirs;
    }

    public static function storeUploadedFile(array $file)
    {
        $validatedName = Repository::validateEvidenceUploadFile($file);
        if (is_wp_error($validatedName)) {
            return $validatedName;
        }

        $directory = self::ensureDirectory(self::UPLOAD_SUBDIR);
        if (is_wp_error($directory)) {
            return $directory;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $file['name'] = (string) $validatedName;
        self::$activeSubdir = self::UPLOAD_SUBDIR;
        add_filter('upload_dir', [self::class, 'filterUploadDir']);

        try {
            $uploaded = wp_handle_upload($file, [
                'test_form' => false,
                'mimes' => Repository::evidenceUploadMimes(),
            ]);
        } finally {
            remove_filter('upload_dir', [self::class, 'filterUploadDir']);
            self::$activeSubdir = '';
        }

        if (!is_array($uploaded) || !empty($uploaded['error'])) {
            return new WP_Error(
                'ouinpo_projects_upload_failed',
                is_array($uploaded) ? (string) $uploaded['error'] : 'Upload impossible.'
            );
        }

        $relativePath = self::relativePath((string) ($uploaded['file'] ?? ''));
        if (is_wp_error($relativePath)) {
            return $relativePath;
        }

        $uploaded['private_relative_path'] = $relativePath;

        return $uploaded;
    }

    public static function ensureDirectory(string $subdir)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('ouinpo_projects_upload_dir', (string) $uploads['error']);
        }

        $base = trailingslashit((string) $uploads['basedir']);
        $root = $base . 'ouinpo';
        $dir = trailingslashit($root) . trim($subdir, '/');

        if (!wp_mkdir_p($dir)) {
            return new WP_Error('ouinpo_projects_upload_dir', 'Dossier de depot inaccessible.');
        }

        self::writeProtectionFiles($root);
        self::writeProtectionFiles($dir);

        return $dir;
    }

    public static function markAttachmentPrivate(int $attachmentId, int $projectId, int $evidenceId, string $relativePath): void
    {
        if ($attachmentId <= 0 || $projectId <= 0 || $evidenceId <= 0 || $relativePath === '') {
            return;
        }

        update_post_meta($attachmentId, self::META_PRIVATE_FILE, '1');
        update_post_meta($attachmentId, self::META_PROJECT_ID, $projectId);
        update_post_meta($attachmentId, self::META_EVIDENCE_ID, $evidenceId);
        update_post_meta($attachmentId, self::META_PRIVATE_PATH, $relativePath);
    }

    public static function isPrivateAttachment(int $attachmentId): bool
    {
        return $attachmentId > 0 && (string) get_post_meta($attachmentId, self::META_PRIVATE_FILE, true) === '1';
    }

    public static function attachmentRelativePath(int $attachmentId): string
    {
        return $attachmentId > 0 ? (string) get_post_meta($attachmentId, self::META_PRIVATE_PATH, true) : '';
    }

    public static function downloadUrl(int $evidenceId): string
    {
        if ($evidenceId <= 0) {
            return '';
        }

        return add_query_arg(
            '_wpnonce',
            wp_create_nonce('wp_rest'),
            rest_url('ouinpo-projects/v1/evidence/' . $evidenceId . '/download')
        );
    }

    public static function relativePath(string $absolutePath)
    {
        if ($absolutePath === '') {
            return new WP_Error('ouinpo_projects_private_path', 'Chemin de fichier invalide.');
        }

        $uploads = wp_upload_dir();
        $base = trailingslashit(wp_normalize_path((string) $uploads['basedir']));
        $path = wp_normalize_path($absolutePath);
        $privatePrefix = $base . 'ouinpo/projects/';

        if (!str_starts_with($path, $privatePrefix)) {
            return new WP_Error('ouinpo_projects_private_path', 'Fichier hors dossier prive.');
        }

        return ltrim(substr($path, strlen($base)), '/');
    }

    public static function absolutePath(string $relativePath)
    {
        $relativePath = ltrim(wp_normalize_path($relativePath), '/');

        if (
            $relativePath === ''
            || str_contains($relativePath, '../')
            || !str_starts_with($relativePath, 'ouinpo/projects/')
        ) {
            return new WP_Error('ouinpo_projects_private_path', 'Chemin prive invalide.');
        }

        $uploads = wp_upload_dir();
        $base = trailingslashit(wp_normalize_path((string) $uploads['basedir']));
        $expectedDir = $base . 'ouinpo/projects/';
        $path = $base . $relativePath;
        $real = realpath($path);

        if (!$real) {
            return new WP_Error('ouinpo_projects_private_missing', 'Fichier prive introuvable.');
        }

        $real = wp_normalize_path($real);
        if (!str_starts_with($real, $expectedDir) || !is_file($real) || !is_readable($real)) {
            return new WP_Error('ouinpo_projects_private_path', 'Fichier prive invalide.');
        }

        return $real;
    }

    public static function sendFile(string $path, string $filename, string $mimeType): void
    {
        $filename = sanitize_file_name($filename) ?: wp_basename($path);
        $mimeType = $mimeType !== '' ? $mimeType : 'application/octet-stream';

        nocache_headers();
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');

        readfile($path);
        exit;
    }

    private static function writeProtectionFiles(string $dir): void
    {
        $index = trailingslashit($dir) . 'index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        $htaccess = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "Options -Indexes\nRequire all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }
    }
}
