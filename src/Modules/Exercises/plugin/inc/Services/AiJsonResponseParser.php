<?php

namespace Ouinpo\Exercises\Services;

defined('ABSPATH') || exit;

final class AiJsonResponseParser
{
    public static function parse(string $raw, string $expected_root = 'object'): array|\WP_Error
    {
        $clean = self::strip_wrappers($raw);
        if ($clean === '') {
            return new \WP_Error('empty_ai_response', 'Reponse IA vide.');
        }

        $candidate = self::extract_balanced($clean, $expected_root);
        if (\is_wp_error($candidate)) {
            return $candidate;
        }

        try {
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new \WP_Error('invalid_json', 'La reponse IA ne contient pas de JSON valide : ' . $e->getMessage(), [
                'json_error' => $e->getMessage(),
            ]);
        }

        if ($expected_root === 'array' && !is_array($decoded)) {
            return new \WP_Error('invalid_json_root', 'La reponse IA doit etre un tableau JSON.');
        }

        if ($expected_root !== 'array' && (!is_array($decoded) || array_is_list($decoded))) {
            return new \WP_Error('invalid_json_root', 'La reponse IA doit etre un objet JSON.');
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

    private static function strip_wrappers(string $raw): string
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

    private static function extract_balanced(string $text, string $expected_root): string|\WP_Error
    {
        $openers = $expected_root === 'array' ? ['['] : ['{'];
        if ($expected_root === 'any') {
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
            $in_string = false;
            $escaped = false;

            for ($i = $start; $i < $length; $i++) {
                $current = $text[$i];

                if ($in_string) {
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }
                    if ($current === '\\') {
                        $escaped = true;
                        continue;
                    }
                    if ($current === '"') {
                        $in_string = false;
                    }
                    continue;
                }

                if ($current === '"') {
                    $in_string = true;
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

        return new \WP_Error('invalid_json', 'Aucun objet JSON complet n a ete trouve dans la reponse IA.');
    }
}
