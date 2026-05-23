<?php

namespace Ouinpo\Exercises\Services;

defined('ABSPATH') || exit;

final class StudentFileExtractService
{
    private const MAX_FILES_PER_ZIP = 80;
    private const MAX_TOTAL_BYTES = 5242880;
    private const MAX_FILE_CHARS = 12000;
    private const MAX_SUBMISSION_CHARS = 32000;

    private const LANGUAGES = [
        'py' => 'python',
        'txt' => 'text',
        'md' => 'markdown',
        'sql' => 'sql',
        'html' => 'html',
        'css' => 'css',
        'js' => 'javascript',
        'json' => 'json',
        'csv' => 'csv',
        'xml' => 'xml',
        'yml' => 'yaml',
        'yaml' => 'yaml',
    ];

    private const BLOCKED_EXTENSIONS = [
        'php', 'phar', 'phtml', 'sh', 'bash', 'bat', 'cmd', 'ps1',
        'exe', 'dll', 'jar', 'scr', 'com', 'msi',
    ];

    public static function allowed_extensions(): array
    {
        return array_merge(array_keys(self::LANGUAGES), ['zip']);
    }

    public static function blocked_extensions(): array
    {
        return self::BLOCKED_EXTENSIONS;
    }

    public static function language_for_filename(string $filename): string
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        return self::LANGUAGES[$ext] ?? 'text';
    }

    public static function is_supported_filename(string $filename): bool
    {
        $base = wp_basename($filename);
        if ($base === '' || $base[0] === '.' || str_contains($filename, "\0")) {
            return false;
        }

        $parts = array_values(array_filter(array_map('strtolower', explode('.', $base))));
        if (empty($parts)) {
            return false;
        }

        foreach ($parts as $part) {
            if (in_array($part, self::BLOCKED_EXTENSIONS, true)) {
                return false;
            }
        }

        $ext = strtolower((string) pathinfo($base, PATHINFO_EXTENSION));
        return isset(self::LANGUAGES[$ext]) || $ext === 'zip';
    }

    public static function extract_single(string $path, string $filename): array|\WP_Error
    {
        if (!is_file($path) || !is_readable($path)) {
            return new \WP_Error('file_unreadable', 'Fichier illisible.');
        }
        if (!self::is_supported_filename($filename) || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === 'zip') {
            return new \WP_Error('unsupported_file_type', 'Type de fichier non autorisÃ©.');
        }
        if ((int) filesize($path) <= 0) {
            return new \WP_Error('empty_file', 'Fichier vide.');
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return new \WP_Error('empty_file', 'Fichier vide.');
        }
        if (self::looks_binary($raw)) {
            return new \WP_Error('binary_file', 'Fichier binaire non analysable.');
        }

        $warnings = [];
        $content = self::normalize_text($raw);
        $truncated = false;
        if (strlen($content) > self::MAX_FILE_CHARS) {
            $content = substr($content, 0, self::MAX_FILE_CHARS);
            $truncated = true;
            $warnings[] = 'Fichier tronquÃ© avant envoi Ã  lâ€™IA.';
        }

        $file = [
            'filename' => sanitize_file_name(wp_basename($filename)),
            'language' => self::language_for_filename($filename),
            'content' => $content,
            'truncated' => $truncated,
            'warnings' => $warnings,
        ];

        return [
            [
                'student_ref_hint' => '',
                'files' => [$file],
                'manifest' => [
                    [
                        'filename' => $file['filename'],
                        'language' => $file['language'],
                        'size' => (int) filesize($path),
                        'truncated' => $truncated,
                        'warnings' => $warnings,
                    ],
                ],
                'warnings' => $warnings,
                'content' => self::format_submission_content([$file]),
            ],
        ];
    }

    public static function extract_zip(string $path): array|\WP_Error
    {
        if (!class_exists('\ZipArchive')) {
            return new \WP_Error('zip_unavailable', 'Extraction ZIP indisponible sur cet hÃ©bergement.');
        }
        if (!is_file($path) || !is_readable($path)) {
            return new \WP_Error('file_unreadable', 'Archive illisible.');
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($path)) {
            return new \WP_Error('invalid_zip', 'Archive ZIP invalide.');
        }

        $groups = [];
        $total_bytes = 0;
        $accepted = 0;
        $global_warnings = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $normalized = str_replace('\\', '/', $name);
            if (self::is_ignored_zip_entry($normalized)) {
                continue;
            }
            if (self::has_suspicious_path($normalized)) {
                $zip->close();
                return new \WP_Error('zip_slip_detected', 'Archive refusÃ©e : chemin suspect.');
            }
            if (preg_match('/\.(zip|rar|7z|tar|gz)$/i', $normalized)) {
                $global_warnings[] = 'Archive imbriquÃ©e ignorÃ©e : ' . sanitize_text_field($normalized);
                continue;
            }
            if (!self::is_supported_filename($normalized)) {
                $global_warnings[] = 'Fichier ignorÃ© car non autorisÃ© : ' . sanitize_text_field($normalized);
                continue;
            }

            $accepted++;
            if ($accepted > self::MAX_FILES_PER_ZIP) {
                $zip->close();
                return new \WP_Error('zip_too_many_files', 'Archive refusÃ©e : trop de fichiers.');
            }

            $size = (int) ($stat['size'] ?? 0);
            $total_bytes += $size;
            if ($total_bytes > self::MAX_TOTAL_BYTES) {
                $zip->close();
                return new \WP_Error('zip_too_large', 'Archive refusÃ©e : contenu dÃ©compressÃ© trop volumineux.');
            }
            if ($size <= 0) {
                $global_warnings[] = 'Fichier vide ignorÃ© : ' . sanitize_text_field($normalized);
                continue;
            }

            $raw = $zip->getFromIndex($i);
            if (!is_string($raw) || $raw === '' || self::looks_binary($raw)) {
                $global_warnings[] = 'Fichier binaire ou illisible ignorÃ© : ' . sanitize_text_field($normalized);
                continue;
            }

            $parts = explode('/', $normalized);
            $group = count($parts) > 1 ? sanitize_file_name((string) $parts[0]) : 'non-attribue';
            if ($group === '') {
                $group = 'non-attribue';
            }
            $safe_filename = count($parts) > 1
                ? implode('/', array_map('sanitize_file_name', array_slice($parts, 1)))
                : sanitize_file_name($normalized);

            $warnings = [];
            $content = self::normalize_text($raw);
            $truncated = false;
            if (strlen($content) > self::MAX_FILE_CHARS) {
                $content = substr($content, 0, self::MAX_FILE_CHARS);
                $truncated = true;
                $warnings[] = 'Fichier tronquÃ© avant envoi Ã  lâ€™IA.';
            }

            $groups[$group] ??= ['student_ref_hint' => $group === 'non-attribue' ? '' : $group, 'files' => [], 'manifest' => [], 'warnings' => []];
            $groups[$group]['files'][] = [
                'filename' => $safe_filename,
                'language' => self::language_for_filename($normalized),
                'content' => $content,
                'truncated' => $truncated,
                'warnings' => $warnings,
            ];
            $groups[$group]['manifest'][] = [
                'filename' => $safe_filename,
                'language' => self::language_for_filename($normalized),
                'size' => $size,
                'truncated' => $truncated,
                'warnings' => $warnings,
            ];
            $groups[$group]['warnings'] = array_merge($groups[$group]['warnings'], $warnings);
        }

        $zip->close();

        if (empty($groups)) {
            return new \WP_Error('zip_no_supported_files', 'Archive sans fichier analysable.');
        }

        foreach ($groups as &$group) {
            $group['warnings'] = array_values(array_unique(array_merge($global_warnings, $group['warnings'])));
            $group['content'] = self::format_submission_content($group['files']);
            if (strlen($group['content']) > self::MAX_SUBMISSION_CHARS) {
                $group['content'] = substr($group['content'], 0, self::MAX_SUBMISSION_CHARS);
                $group['warnings'][] = 'Rendu tronquÃ© car trop long pour lâ€™analyse IA.';
            }
        }
        unset($group);

        return array_values($groups);
    }

    private static function is_ignored_zip_entry(string $name): bool
    {
        $base = wp_basename($name);
        return $base === '' || $base === '.DS_Store' || $base[0] === '.' || str_starts_with($name, '__MACOSX/');
    }

    private static function has_suspicious_path(string $name): bool
    {
        return str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) === 1 || str_contains($name, ':');
    }

    private static function looks_binary(string $raw): bool
    {
        return str_contains(substr($raw, 0, 2048), "\0");
    }

    private static function normalize_text(string $raw): string
    {
        if (function_exists('mb_convert_encoding')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        return str_replace(["\r\n", "\r"], "\n", $raw);
    }

    private static function format_submission_content(array $files): string
    {
        $parts = [];
        foreach ($files as $file) {
            $parts[] = "### " . (string) $file['filename'] . " (" . (string) $file['language'] . ")\n" . (string) $file['content'];
        }
        return implode("\n\n", $parts);
    }
}
