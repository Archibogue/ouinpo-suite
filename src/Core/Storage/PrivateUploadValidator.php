<?php

namespace Ouinpo\Suite\Core\Storage;

defined('ABSPATH') || exit;

final class PrivateUploadValidator
{
    public static function validateUploadedFile(array $file, array $args)
    {
        $allowedMimes = isset($args['allowed_mimes']) && is_array($args['allowed_mimes'])
            ? $args['allowed_mimes']
            : [];
        $allowedExtensions = isset($args['allowed_extensions']) && is_array($args['allowed_extensions'])
            ? array_values(array_map('strtolower', $args['allowed_extensions']))
            : array_keys($allowedMimes);
        $blockedExtensions = isset($args['blocked_extensions']) && is_array($args['blocked_extensions'])
            ? array_values(array_map('strtolower', $args['blocked_extensions']))
            : [];
        $messages = isset($args['messages']) && is_array($args['messages']) ? $args['messages'] : [];
        $codes = isset($args['codes']) && is_array($args['codes']) ? $args['codes'] : [];

        $rawName = isset($file['name']) ? (string) $file['name'] : '';
        $rawBaseName = wp_basename($rawName);
        $rawBaseLower = strtolower((string) $rawBaseName);
        if (!empty($args['reject_raw_dotfiles']) && ($rawBaseName === '' || $rawBaseName[0] === '.' || $rawBaseLower === '.env')) {
            return self::error($codes, $messages, 'invalid_filename');
        }

        if (!empty($args['require_file_fields']) && (empty($file['name']) || empty($file['tmp_name']))) {
            return self::error($codes, $messages, 'missing_file');
        }

        if (!empty($file['error'])) {
            return self::error($codes, $messages, 'upload_error');
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if (empty($args['allow_empty']) && $size <= 0) {
            return self::error($codes, $messages, 'empty_file');
        }

        $maxSize = isset($args['max_size']) ? (int) $args['max_size'] : 0;
        if ($maxSize > 0 && $size > $maxSize) {
            return self::error($codes, $messages, 'file_too_large');
        }

        $filename = sanitize_file_name(wp_basename($rawName));
        if ($filename === '' || $filename === '.' || $filename === '..' || $filename[0] === '.') {
            return self::error($codes, $messages, 'invalid_filename');
        }

        $extension = self::normalizedExtension($filename, $allowedExtensions);
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            return self::error($codes, $messages, 'unsupported_file_type');
        }

        $parts = self::filenameParts($filename);
        $count = count($parts);
        foreach ($parts as $index => $part) {
            if (!in_array($part, $blockedExtensions, true)) {
                continue;
            }

            if (self::isAllowedBlockedPart($extension, $part, $index, $count, $args)) {
                continue;
            }

            return self::error($codes, $messages, 'dangerous_extension');
        }

        if (!empty($args['require_uploaded_file']) && (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name']))) {
            return self::error($codes, $messages, 'missing_tmp_file');
        }

        $tmpName = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $check = wp_check_filetype_and_ext($tmpName, $filename, $allowedMimes);
        $filetypeExtension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (!empty($check['ext']) && strtolower((string) $check['ext']) !== $filetypeExtension) {
            return self::error($codes, $messages, 'mime_mismatch');
        }

        if (!empty($check['type'])) {
            return $filename;
        }

        $declared = isset($file['type']) ? strtolower((string) $file['type']) : '';
        if (self::allowsFallbackMime($extension, $declared, $args)) {
            return $filename;
        }

        return self::error($codes, $messages, 'mime_not_allowed');
    }

    public static function normalizedExtension(string $filename, array $allowedExtensions = []): string
    {
        $filename = strtolower(sanitize_file_name(wp_basename($filename)));
        $parts = self::filenameParts($filename);
        if (empty($parts)) {
            return '';
        }

        $allowedExtensions = array_values(array_map('strtolower', $allowedExtensions));
        usort($allowedExtensions, static function ($left, $right) {
            return substr_count($right, '.') <=> substr_count($left, '.')
                ?: strlen($right) <=> strlen($left);
        });

        foreach ($allowedExtensions as $extension) {
            if ($extension !== '' && substr($filename, -strlen('.' . $extension)) === '.' . $extension) {
                return $extension;
            }
        }

        return (string) end($parts);
    }

    private static function filenameParts(string $filename): array
    {
        return array_values(array_filter(explode('.', strtolower($filename)), static function ($part) {
            return $part !== '';
        }));
    }

    private static function isAllowedBlockedPart(string $extension, string $part, int $index, int $count, array $args): bool
    {
        $allowedBlockedParts = isset($args['allowed_blocked_parts']) && is_array($args['allowed_blocked_parts'])
            ? $args['allowed_blocked_parts']
            : [];

        if (!isset($allowedBlockedParts[$extension]) || !is_array($allowedBlockedParts[$extension])) {
            return false;
        }

        $extensionParts = self::filenameParts($extension);
        $extensionPartCount = count($extensionParts);
        $extensionOffset = $count - $extensionPartCount;
        if ($index < $extensionOffset || $index >= $count) {
            return false;
        }

        return in_array($part, $allowedBlockedParts[$extension], true);
    }

    private static function allowsFallbackMime(string $extension, string $declared, array $args): bool
    {
        $fallbacks = isset($args['fallback_declared_mimes']) && is_array($args['fallback_declared_mimes'])
            ? $args['fallback_declared_mimes']
            : [];

        if (!isset($fallbacks[$extension]) || !is_array($fallbacks[$extension])) {
            return false;
        }

        return in_array($declared, array_map('strtolower', $fallbacks[$extension]), true);
    }

    private static function error(array $codes, array $messages, string $key): \WP_Error
    {
        $defaultMessages = [
            'missing_file' => 'Aucun fichier recu.',
            'upload_error' => 'Erreur upload.',
            'empty_file' => 'Fichier vide.',
            'file_too_large' => 'Fichier trop lourd.',
            'invalid_filename' => 'Nom de fichier invalide.',
            'unsupported_file_type' => 'Type de fichier non autorise.',
            'dangerous_extension' => 'Extension de fichier dangereuse.',
            'missing_tmp_file' => 'Fichier temporaire manquant.',
            'mime_mismatch' => 'Le type du fichier ne correspond pas a son extension.',
            'mime_not_allowed' => 'Type MIME non autorise.',
        ];

        return new \WP_Error(
            isset($codes[$key]) ? (string) $codes[$key] : $key,
            isset($messages[$key]) ? (string) $messages[$key] : $defaultMessages[$key]
        );
    }
}
