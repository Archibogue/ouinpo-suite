<?php

namespace Ouinpo\Suite\Core\Ai;

defined('ABSPATH') || exit;

final class JsonResponseParser
{
    public static function parse(string $raw, string $expectedRoot = 'object', array $errors = []): array|\WP_Error
    {
        $clean = self::stripWrappers($raw);
        if ($clean === '') {
            return self::error($errors, 'empty', 'empty_ai_response', 'Reponse IA vide.');
        }

        $candidate = self::extractBalanced($clean, $expectedRoot, $errors);
        if (\is_wp_error($candidate)) {
            return $candidate;
        }

        try {
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $message = self::message($errors, 'invalid_json', 'La reponse IA ne contient pas de JSON valide : ' . $e->getMessage());
            if (array_key_exists('append_json_error_to_message', $errors) && !$errors['append_json_error_to_message']) {
                $message = self::message($errors, 'invalid_json', 'La reponse IA ne contient pas de JSON valide.');
            }

            return self::error($errors, 'invalid_json', 'invalid_json', $message, [
                'json_error' => $e->getMessage(),
            ]);
        }

        if ($expectedRoot === 'array' && !is_array($decoded)) {
            return self::error($errors, 'invalid_root', 'invalid_json_root', self::message($errors, 'invalid_root_array', 'La reponse IA doit etre un tableau JSON.'));
        }

        if ($expectedRoot !== 'array' && (!is_array($decoded) || array_is_list($decoded))) {
            return self::error($errors, 'invalid_root', 'invalid_json_root', self::message($errors, 'invalid_root_object', 'La reponse IA doit etre un objet JSON.'));
        }

        return $decoded;
    }

    public static function excerpt(string $raw, int $limit = 1200): string
    {
        $text = trim(\wp_strip_all_tags($raw));
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit);
        }

        return substr($text, 0, $limit);
    }

    private static function stripWrappers(string $raw): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $text = trim($text);

        if (preg_match('/^```(?:json|JSON)?\s*(.*?)\s*```$/s', $text, $m)) {
            return trim($m[1]);
        }

        $text = preg_replace('/^\s*```(?:json|JSON)?\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;

        return trim($text);
    }

    private static function extractBalanced(string $text, string $expectedRoot, array $errors): string|\WP_Error
    {
        $openers = $expectedRoot === 'array' ? ['['] : ['{'];
        if ($expectedRoot === 'any') {
            $openers = ['{', '['];
        }

        $length = strlen($text);
        for ($start = 0; $start < $length; $start++) {
            $char = $text[$start];
            if (!in_array($char, $openers, true)) {
                continue;
            }

            $close = $char === '{' ? '}' : ']';
            $depth = 0;
            $inString = false;
            $escaped = false;

            for ($i = $start; $i < $length; $i++) {
                $current = $text[$i];

                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }
                    if ($current === '\\') {
                        $escaped = true;
                        continue;
                    }
                    if ($current === '"') {
                        $inString = false;
                    }
                    continue;
                }

                if ($current === '"') {
                    $inString = true;
                    continue;
                }

                if ($current === $char) {
                    $depth++;
                    continue;
                }

                if ($current === $close) {
                    $depth--;
                    if ($depth === 0) {
                        return substr($text, $start, $i - $start + 1);
                    }
                }
            }
        }

        return self::error($errors, 'invalid_json', 'invalid_json', self::message($errors, 'not_found', 'Aucun objet JSON complet n a ete trouve dans la reponse IA.'));
    }

    private static function error(array $errors, string $type, string $defaultCode, string $message, array $data = []): \WP_Error
    {
        $code = isset($errors[$type . '_code']) ? (string) $errors[$type . '_code'] : $defaultCode;

        if (isset($errors['status'])) {
            $data['status'] = (int) $errors['status'];
        }

        return new \WP_Error($code, $message, $data);
    }

    private static function message(array $errors, string $key, string $default): string
    {
        return isset($errors[$key . '_message']) ? (string) $errors[$key . '_message'] : $default;
    }
}
