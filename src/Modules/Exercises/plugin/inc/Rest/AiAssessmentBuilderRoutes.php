<?php

namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\Services\AiAssessmentGenerator;
use Ouinpo\Exercises\Services\ClassKpiService;
use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class AiAssessmentBuilderRoutes
{
    private const NS = 'ouinpo/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/ai-assessment-builder/kpi', [
            [
                'methods' => 'POST',
                'callback' => [self::class, 'kpi'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route(self::NS, '/ai-assessment-builder/generate', [
            [
                'methods' => 'POST',
                'callback' => [self::class, 'generate'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
    }

    public static function can_manage(): bool
    {
        return current_user_can(Capabilities::MANAGE_ASSESSMENTS) || current_user_can('manage_options');
    }

    public static function kpi(\WP_REST_Request $request)
    {
        $payload = self::payload((array) $request->get_json_params());
        return rest_ensure_response([
            'kpi' => ClassKpiService::build(
                $payload['group_id'],
                $payload['level_id'],
                $payload['competency_ids'],
                $payload['domain_slugs']
            ),
        ]);
    }

    public static function generate(\WP_REST_Request $request)
    {
        $generator = new AiAssessmentGenerator();
        $proposal = $generator->generate(self::payload((array) $request->get_json_params()));

        if (is_wp_error($proposal)) {
            return $proposal;
        }

        return rest_ensure_response(['proposal' => $proposal]);
    }

    private static function payload(array $raw): array
    {
        return [
            'group_id' => (int) ($raw['group_id'] ?? 0),
            'level_id' => (int) ($raw['level_id'] ?? 0),
            'target_minutes' => max(10, min(240, (int) ($raw['target_minutes'] ?? 90))),
            'items_count' => max(1, min(12, (int) ($raw['items_count'] ?? 4))),
            'domain_slugs' => array_values(array_unique(array_filter(array_map('sanitize_title', (array) ($raw['domain_slugs'] ?? []))))),
            'competency_ids' => array_values(array_unique(array_filter(array_map('intval', (array) ($raw['competency_ids'] ?? []))))),
            'difficulty_slug' => sanitize_key((string) ($raw['difficulty_slug'] ?? 'confirme')),
            'existing_ratio' => max(0, min(100, (int) ($raw['existing_ratio'] ?? 70))),
            'new_ratio' => max(0, min(100, (int) ($raw['new_ratio'] ?? 30))),
            'free_constraints' => sanitize_textarea_field((string) ($raw['free_constraints'] ?? '')),
        ];
    }
}
