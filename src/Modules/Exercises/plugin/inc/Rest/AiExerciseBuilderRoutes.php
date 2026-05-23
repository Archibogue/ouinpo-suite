<?php

namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\Services\AiExerciseGenerator;
use Ouinpo\Exercises\Services\ExerciseInsertService;
use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class AiExerciseBuilderRoutes
{
    private const NS = 'ouinpo/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/ai-exercise-builder/generate', [
            [
                'methods' => 'POST',
                'callback' => [self::class, 'generate'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        register_rest_route(self::NS, '/ai-exercise-builder/create', [
            [
                'methods' => 'POST',
                'callback' => [self::class, 'create'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
    }

    public static function can_manage(): bool
    {
        return current_user_can(Capabilities::MANAGE_EXERCISES) || current_user_can('manage_options');
    }

    public static function generate(\WP_REST_Request $request)
    {
        $payload = self::sanitize_generation_payload((array) $request->get_json_params());
        $generator = new AiExerciseGenerator();
        $proposal = $generator->generate($payload);

        if (is_wp_error($proposal)) {
            return $proposal;
        }

        return rest_ensure_response([
            'proposal' => $proposal,
        ]);
    }

    public static function create(\WP_REST_Request $request)
    {
        $params = (array) $request->get_json_params();
        $proposal = is_array($params['proposal'] ?? null) ? $params['proposal'] : [];

        $exercise_id = ExerciseInsertService::create_from_ai($proposal);
        if (is_wp_error($exercise_id)) {
            return $exercise_id;
        }

        return rest_ensure_response([
            'exercise_id' => $exercise_id,
            'edit_url' => admin_url('admin.php?page=ouinpo-exercices&action=edit&id=' . (int) $exercise_id),
            'public_url' => home_url('/exercice/?exo=' . (int) $exercise_id),
        ]);
    }

    private static function sanitize_generation_payload(array $raw): array
    {
        return [
            'level_id' => (int) ($raw['level_id'] ?? 0),
            'domain_slug' => sanitize_key((string) ($raw['domain_slug'] ?? '')),
            'competency_ids' => array_values(array_unique(array_filter(array_map('intval', (array) ($raw['competency_ids'] ?? []))))),
            'difficulty_slug' => sanitize_key((string) ($raw['difficulty_slug'] ?? '')),
            'exercise_type' => sanitize_key((string) ($raw['exercise_type'] ?? 'classic')),
            'estimated_minutes' => max(1, min(240, (int) ($raw['estimated_minutes'] ?? 20))),
            'free_prompt' => sanitize_textarea_field((string) ($raw['free_prompt'] ?? '')),
            'action' => sanitize_key((string) ($raw['action'] ?? 'generate')),
            'previous' => is_array($raw['previous'] ?? null) ? $raw['previous'] : [],
        ];
    }
}
