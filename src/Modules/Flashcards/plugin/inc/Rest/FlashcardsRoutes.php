<?php

namespace Ouinpo\Flashcards\Rest;



use Ouinpo\Flashcards\Service;
use Ouinpo\Suite\Core\Privacy\LearningDataPolicy;



defined('ABSPATH') || exit;



final class FlashcardsRoutes

{

    public static function register(): void

    {

        register_rest_route('ouinpo/v1', '/flashcards/decks', [

            'methods' => 'GET',

            'callback' => [self::class, 'decks'],

            'permission_callback' => function () { return is_user_logged_in(); },

        ]);



        register_rest_route('ouinpo/v1', '/flashcards/me', [

            'methods' => 'GET',

            'callback' => [self::class, 'me'],

            'permission_callback' => function () { return is_user_logged_in(); },

        ]);

        register_rest_route('ouinpo/v1', '/flashcards', [
            'methods' => 'GET',
            'callback' => [self::class, 'me'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('ouinpo/v1', '/flashcards/session', [

            'methods' => 'GET',

            'callback' => [self::class, 'session'],

            'permission_callback' => function () { return is_user_logged_in(); },

        ]);



        register_rest_route('ouinpo/v1', '/flashcards/grade', [

            'methods' => 'POST',

            'callback' => [self::class, 'grade'],

            'permission_callback' => function () { return is_user_logged_in(); },

        ]);

    }



    private static function request_deck_ids(\WP_REST_Request $request): array

    {

        $deck_ids = $request->get_param('deck_ids');

        if ($deck_ids === null || $deck_ids === '') {

            $deck_id = (int) $request->get_param('deck_id');

            return $deck_id > 0 ? [$deck_id] : [];

        }

        return Service::normalize_deck_ids($deck_ids);

    }



    private static function request_context(\WP_REST_Request $request): array

    {

        return [

            'track' => $request->get_param('track'),

            'level' => $request->get_param('level'),

        ];

    }



    public static function decks(\WP_REST_Request $request)

    {

        $uid = get_current_user_id();

        $ctx = self::request_context($request);

        $domain_slug = sanitize_title((string) $request->get_param('domain_slug'));

        $deck_ids = self::request_deck_ids($request);



        return rest_ensure_response([

            'ok' => true,

            'level' => Service::current_user_level_label($uid),

            'domains' => Service::get_accessible_domains($uid, $ctx),

            'decks' => Service::get_accessible_decks($uid, array_merge($ctx, [

                'domain_slug' => $domain_slug,

                'deck_ids' => $deck_ids,

            ])),

        ]);

    }



    public static function me(\WP_REST_Request $request)

    {

        $uid = get_current_user_id();

        $ctx = self::request_context($request);

        $domain_slug = sanitize_title((string) $request->get_param('domain_slug'));

        $deck_ids = self::request_deck_ids($request);



        return rest_ensure_response([

            'ok' => true,

            'level' => Service::current_user_level_label($uid),

            'counts' => Service::get_due_counts($uid, $deck_ids, $domain_slug ?: null, $ctx),

            'domains' => Service::get_accessible_domains($uid, $ctx),

            'decks' => Service::get_accessible_decks($uid, array_merge($ctx, [

                'domain_slug' => $domain_slug,

            ])),

        ]);

    }



    public static function session(\WP_REST_Request $request)

    {

        $uid = get_current_user_id();

        $ctx = self::request_context($request);

        $domain_slug = sanitize_title((string) $request->get_param('domain_slug'));

        $deck_ids = self::request_deck_ids($request);



        return rest_ensure_response([

            'ok' => true,

            'counts' => Service::get_due_counts($uid, $deck_ids, $domain_slug ?: null, $ctx),

            'card' => Service::get_next_card_for_user($uid, $deck_ids, $domain_slug ?: null, $ctx),

        ]);

    }



    public static function grade(\WP_REST_Request $request)

    {

        $uid = get_current_user_id();

        $card_id = (int) $request->get_param('card_id');

        $grade = (string) $request->get_param('grade');

        $ctx = self::request_context($request);

        $domain_slug = sanitize_title((string) $request->get_param('domain_slug'));

        $deck_ids = self::request_deck_ids($request);



        if ($card_id <= 0) {

            return new \WP_Error('bad_request', 'card_id manquant', ['status' => 400]);

        }

        if (!(new LearningDataPolicy())->canStoreLearningData((int) $uid)) {
            return rest_ensure_response([
                'ok' => true,
                'stored' => false,
                'reason' => 'tracking_disabled',
                'counts' => Service::get_due_counts($uid, $deck_ids, $domain_slug ?: null, $ctx),
                'card' => Service::get_next_card_for_user($uid, $deck_ids, $domain_slug ?: null, $ctx),
            ]);
        }



        try {

            $result = Service::review_card($uid, $card_id, $grade);

        } catch (\Throwable $e) {

            return new \WP_Error('review_failed', $e->getMessage(), ['status' => 400]);

        }



        return rest_ensure_response([

            'ok' => true,

            'review' => $result,

            'counts' => Service::get_due_counts($uid, $deck_ids, $domain_slug ?: null, $ctx),

            'card' => Service::get_next_card_for_user($uid, $deck_ids, $domain_slug ?: null, $ctx),

        ]);

    }

}

