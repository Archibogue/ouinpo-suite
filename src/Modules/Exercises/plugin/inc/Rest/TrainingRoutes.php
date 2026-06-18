<?php
namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\PathsService;
use Ouinpo\Suite\Core\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class TrainingRoutes
{
    private const NS = 'ouinpo/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/training/paths', [[
            'methods' => 'GET',
            'callback' => [__CLASS__, 'paths'],
            'permission_callback' => '__return_true',
        ]]);

        register_rest_route(self::NS, '/training/paths/(?P<id>\d+)/start', [[
            'methods' => 'POST',
            'callback' => [__CLASS__, 'start'],
            'permission_callback' => 'is_user_logged_in',
        ]]);

        register_rest_route(self::NS, '/training/dashboard', [[
            'methods' => 'GET',
            'callback' => [__CLASS__, 'dashboard'],
            'permission_callback' => [__CLASS__, 'can_view_own_training'],
        ]]);

        register_rest_route(self::NS, '/training/badges', [[
            'methods' => 'GET',
            'callback' => [__CLASS__, 'badges'],
            'permission_callback' => [__CLASS__, 'can_view_own_training'],
        ]]);
    }

    public static function paths(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'paths' => PathsService::get_public_autonomous_paths([
                'level_slug' => sanitize_key((string) $request->get_param('level_slug')),
                'domain_slug' => sanitize_key((string) $request->get_param('domain_slug')),
                'goal_slug' => sanitize_key((string) $request->get_param('goal_slug')),
            ]),
        ], 200);
    }

    public static function start(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        if (!user_can($user_id, Capabilities::START_PUBLIC_PATHS) && !user_can($user_id, Capabilities::PRACTICE_EXERCISES)) {
            return new WP_REST_Response(new \WP_Error('forbidden', 'Acces refuse.'), 403);
        }

        $result = PathsService::start_path_for_user($user_id, (int) $request['id']);
        if (is_wp_error($result)) {
            return new WP_REST_Response($result, 400);
        }

        return new WP_REST_Response([
            'ok' => true,
            'path_id' => (int) $result,
        ], 200);
    }

    public static function dashboard(): WP_REST_Response
    {
        $user_id = get_current_user_id();
        if (!self::can_view_own_training()) {
            return new WP_REST_Response(new \WP_Error('forbidden', 'Acces refuse.'), 403);
        }

        return new WP_REST_Response(PathsService::get_user_training_dashboard($user_id), 200);
    }

    public static function badges(): WP_REST_Response
    {
        if (!self::can_view_own_training()) {
            return new WP_REST_Response(new \WP_Error('forbidden', 'Acces refuse.'), 403);
        }

        return new WP_REST_Response([
            'earned' => PathsService::get_user_path_badges(get_current_user_id()),
            'paths' => PathsService::get_public_autonomous_paths(),
        ], 200);
    }

    public static function can_view_own_training(): bool
    {
        $user_id = get_current_user_id();

        return $user_id > 0
            && (
                user_can($user_id, Capabilities::VIEW_OWN_PROGRESS)
                || user_can($user_id, Capabilities::VIEW_OWN_LEARNING_DATA)
            );
    }
}
