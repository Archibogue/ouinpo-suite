<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class CopyUploadService
{
    private const ALLOWED_MIMES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    private static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    public static function allowed_mimes(): array
    {
        return self::ALLOWED_MIMES;
    }

    public static function store(array $file, int $batch_id, string $student_ref = '', int $student_user_id = 0, string $manual_text = ''): int|\WP_Error
    {
        global $wpdb;
        if ($batch_id <= 0) {
            return new \WP_Error('invalid_batch', 'Lot invalide.');
        }

        if (empty($file['tmp_name']) || empty($file['name'])) {
            return new \WP_Error('missing_file', 'Fichier manquant.');
        }

        $max_bytes = max(1, (int) AiSettings::get('ouinpo_ai_correction_max_file_mb')) * MB_IN_BYTES;
        if (!empty($file['size']) && (int) $file['size'] > $max_bytes) {
            return new \WP_Error('file_too_large', 'Fichier trop lourd.');
        }

        $filename = sanitize_file_name(wp_basename((string) $file['name']));
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ($filename === '' || !isset(self::ALLOWED_MIMES[$ext])) {
            return new \WP_Error('unsupported_file_type', 'Type de fichier non autorisé.');
        }

        $check = wp_check_filetype_and_ext((string) $file['tmp_name'], $filename, self::ALLOWED_MIMES);
        if (empty($check['ext']) || empty($check['type'])) {
            return new \WP_Error('mime_not_allowed', 'MIME non autorisé.');
        }

        $dir = self::batch_dir($batch_id);
        $filename = wp_unique_filename($dir['path'], $filename);
        $target = trailingslashit($dir['path']) . $filename;

        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return new \WP_Error('move_failed', 'Impossible de déplacer le fichier.');
        }
        @chmod($target, 0640);

        $student_ref = sanitize_text_field($student_ref);
        if ($student_ref === '') {
            $student_ref = 'anonyme-' . str_pad((string) (count(CorrectionBatchService::copies($batch_id)) + 1), 3, '0', STR_PAD_LEFT);
        }

        $ocr_text = CopyOcrService::extract($target, (string) $check['type'], $manual_text);
        $status = $ocr_text !== '' ? 'ready' : 'ocr_needed';
        $error = $ocr_text !== '' ? '' : CopyOcrService::unavailable_message();

        $ok = $wpdb->insert(self::table('correction_copies'), [
            'batch_id' => $batch_id,
            'student_user_id' => $student_user_id > 0 ? $student_user_id : null,
            'student_ref' => $student_ref,
            'file_name' => $filename,
            'file_path' => $target,
            'file_url' => '',
            'mime_type' => (string) $check['type'],
            'file_size' => (int) ($file['size'] ?? 0),
            'pages_count' => $ext === 'pdf' ? null : 1,
            'ocr_text' => $ocr_text,
            'status' => $status,
            'error_message' => $error,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']);

        if (!$ok) {
            @unlink($target);
            return new \WP_Error('copy_insert_failed', 'Impossible d’enregistrer la copie.');
        }

        return (int) $wpdb->insert_id;
    }

    public static function batch_dir(int $batch_id): array
    {
        $uploads = wp_upload_dir();
        $base = trailingslashit((string) $uploads['basedir']) . 'ouinpo';
        $path = trailingslashit($base) . 'corrections-scan/batch-' . $batch_id;

        self::ensure_dir($base);
        self::ensure_dir(trailingslashit($base) . 'corrections-scan');
        self::protect_dir($path);

        return ['path' => $path, 'url' => '', 'subdir' => '/ouinpo/corrections-scan/batch-' . $batch_id];
    }

    private static function ensure_dir(string $path): void
    {
        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }
        if (!is_dir($path) || !is_writable($path)) {
            return;
        }
        if (!file_exists(trailingslashit($path) . 'index.html')) {
            @file_put_contents(trailingslashit($path) . 'index.html', '');
        }
    }

    private static function protect_dir(string $path): void
    {
        self::ensure_dir($path);
        if (!is_dir($path) || !is_writable($path)) {
            return;
        }
        if (!file_exists(trailingslashit($path) . '.htaccess')) {
            @file_put_contents(trailingslashit($path) . '.htaccess', "Options -Indexes\nRequire all denied\nDeny from all\n");
        }
    }
}
