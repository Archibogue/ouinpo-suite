<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\Ai\JsonResponseParser;

defined('ABSPATH') || exit;

final class AiJsonResponseParser
{
    public static function parse(string $raw, string $expected_root = 'object'): array|\WP_Error
    {
        return JsonResponseParser::parse($raw, $expected_root);
    }

    public static function excerpt(string $raw, int $limit = 1200): string
    {
        return JsonResponseParser::excerpt($raw, $limit);
    }
}
