<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class StudentFileUploadService
{
    private const MAX_FILES_PER_REQUEST = 20;
    private const MAX_TOTAL_REQUEST_BYTES = 52428800;

    private const ALLOWED_MIMES = [
        'py' => 'text/x-python',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'sql' => 'application/sql',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'csv' => 'text/csv',
        'xml' => 'application/xml',
        'yml' => 'application/x-yaml',
        'yaml' => 'application/x-yaml',
        'zip' => 'application/zip',
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

    public static function store(array $file, int $batch_id, string $student_ref = '', int $student_user_id = 0): array|\WP_Error
    {
        global $wpdb;
        FileCorrectionBatchService::ensure_schema();

        $batch = FileCorrectionBatchService::get_batch($batch_id);
        if (!$batch) {
            return new \WP_Error('invalid_batch', 'Lot fichiers invalide.');
        }
        if (empty($file['tmp_name']) || empty($file['name'])) {
            return new \WP_Error('missing_file', 'Fichier manquant.');
        }
        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_failed', 'Upload du fichier impossible.');
        }

        $max_bytes = max(1, (int) AiSettings::get('ouinpo_ai_file_correction_max_file_mb')) * MB_IN_BYTES;
        if (!empty($file['size']) && (int) $file['size'] > $max_bytes) {
            return new \WP_Error('file_too_large', 'Fichier trop lourd.');
        }

        $original = sanitize_file_name(wp_basename((string) $file['name']));
        $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        if ($original === '' || !StudentFileExtractService::is_supported_filename($original)) {
            return new \WP_Error('unsupported_file_type', 'Type de fichier non autorisÃ©.');
        }

        $check = wp_check_filetype_and_ext((string) $file['tmp_name'], $original, self::ALLOWED_MIMES);
        if (!self::mime_is_acceptable($ext, (string) ($check['type'] ?? ''), (string) ($file['type'] ?? ''))) {
            return new \WP_Error('mime_not_allowed', 'MIME non autorisÃ© ou incohÃ©rent.');
        }

        $dir = self::batch_dir($batch_id);
        $stored_name = wp_unique_filename($dir['path'], wp_generate_uuid4() . '-upload');
        $target = trailingslashit($dir['path']) . $stored_name;
        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return new \WP_Error('move_failed', 'Impossible de dÃ©placer le fichier.');
        }
        @chmod($target, 0640);

        $submissions = $ext === 'zip'
            ? StudentFileExtractService::extract_zip($target)
            : StudentFileExtractService::extract_single($target, $original);
        if (is_wp_error($submissions)) {
            @unlink($target);
            return $submissions;
        }

        $created = [];
        $base_index = count(CorrectionBatchService::copies($batch_id)) + 1;
        foreach ($submissions as $index => $submission) {
            $ref = self::student_ref($student_ref, $base_index + $index);
            $content = (string) ($submission['content'] ?? '');
            if (trim($content) === '') {
                continue;
            }

            $ok = $wpdb->insert(self::table('correction_copies'), [
                'batch_id' => $batch_id,
                'student_user_id' => $student_user_id > 0 ? $student_user_id : null,
                'student_ref' => $ref,
                'source_type' => 'file',
                'file_name' => $ext === 'zip' ? $original . ' / ' . $ref : $original,
                'file_path' => $target,
                'file_url' => '',
                'mime_type' => (string) (($check['type'] ?? '') ?: ($file['type'] ?? '')),
                'file_size' => (int) ($file['size'] ?? 0),
                'pages_count' => null,
                'ocr_text' => $content,
                'extraction_type' => $ext === 'zip' ? 'zip_static' : 'file_static',
                'file_manifest' => wp_json_encode((array) ($submission['manifest'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'extracted_content' => $content,
                'extraction_warnings' => wp_json_encode((array) ($submission['warnings'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'ready',
                'error_message' => '',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

            if ($ok) {
                $created[] = (int) $wpdb->insert_id;
            }
        }

        if (empty($created)) {
            @unlink($target);
            return new \WP_Error('no_extractable_content', 'Aucun contenu analysable dans le fichier.');
        }

        return $created;
    }

    public static function validate_request_limits(array $files): true|\WP_Error
    {
        if (count($files) > self::MAX_FILES_PER_REQUEST) {
            return new \WP_Error('too_many_files', 'Trop de fichiers dans la requête.');
        }

        $total = 0;
        foreach ($files as $file) {
            $total += (int) ($file['size'] ?? 0);
        }
        if ($total > self::MAX_TOTAL_REQUEST_BYTES) {
            return new \WP_Error('request_too_large', 'Taille totale des fichiers trop importante.');
        }

        return true;
    }

    public static function batch_dir(int $batch_id): array
    {
        $uploads = wp_upload_dir();
        $base = trailingslashit((string) $uploads['basedir']) . 'ouinpo';
        $path = trailingslashit($base) . 'corrections-file/batch-' . $batch_id;

        self::ensure_dir($base);
        self::ensure_dir(trailingslashit($base) . 'corrections-file');
        self::ensure_dir($path);

        return ['path' => $path, 'url' => '', 'subdir' => '/ouinpo/corrections-file/batch-' . $batch_id];
    }

    private static function student_ref(string $requested, int $index): string
    {
        $requested = sanitize_text_field($requested);
        if ($requested !== '') {
            return $requested;
        }
        return 'anonyme-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);
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
        if (!file_exists(trailingslashit($path) . '.htaccess')) {
            @file_put_contents(trailingslashit($path) . '.htaccess', "Options -Indexes\nRequire all denied\nDeny from all\n");
        }
    }

    private static function mime_is_acceptable(string $ext, string $detected, string $declared): bool
    {
        if (!isset(self::ALLOWED_MIMES[$ext])) {
            return false;
        }

        $mime = strtolower($detected ?: $declared);
        if ($mime === '') {
            return $ext !== 'zip';
        }
        if ($ext === 'zip') {
            return in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true);
        }
        if (str_starts_with($mime, 'text/')) {
            return true;
        }
        return in_array($mime, ['application/json', 'application/xml', 'application/sql', 'application/javascript', 'application/x-yaml', 'application/octet-stream'], true);
    }
}
