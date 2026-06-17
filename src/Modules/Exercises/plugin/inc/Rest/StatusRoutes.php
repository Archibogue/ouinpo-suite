<?php
namespace Ouinpo\Exercises\Rest;

use Ouinpo\Suite\Core\Privacy\LearningDataPolicy;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class StatusRoutes
{
    const NS = 'ouinpo/v1';

    public static function register()
    {
        register_rest_route(self::NS, '/exercises/(?P<id>\d+)/status', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'set'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);
    }

    private static function is_practical_subject(int $exercise_id): bool
    {
        if ($exercise_id <= 0) {
            return false;
        }
    
        global $wpdb;
        $p = $wpdb->prefix . 'ouin_exo_';
    
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$p}exam_meta
             WHERE exercise_id = %d
               AND exam_type = 'practical_subject'",
            $exercise_id
        )) > 0;
    }

    public static function get(WP_REST_Request $r): WP_REST_Response
    {
        global $wpdb;
        $p   = $wpdb->prefix . 'ouin_exo_';
        $uid = get_current_user_id();
        $eid = (int) $r['id'];

        if (self::is_practical_subject($eid)) {
            return new WP_REST_Response((object) [
                'status'      => 'none',
                'declared_at' => null,
                'updated_at'  => null,
            ], 200);
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status, declared_at, updated_at
                 FROM {$p}user_status
                 WHERE user_id = %d AND exercise_id = %d",
                $uid,
                $eid
            )
        );

        if (!$row) {
            $row = (object) [
                'status'      => 'none',
                'declared_at' => null,
                'updated_at'  => null,
            ];
        }

        return new WP_REST_Response($row, 200);
    }

    public static function set(WP_REST_Request $r): WP_REST_Response
    {
        global $wpdb;
        $p   = $wpdb->prefix . 'ouin_exo_';
        $uid = get_current_user_id();
        $eid = (int) $r['id'];

        if (!(new LearningDataPolicy())->canStoreLearningData((int) $uid)) {
            return new WP_REST_Response(LearningDataPolicy::trackingDisabledResponse() + ['status' => 'none'], 200);
        }
        
        if (self::is_practical_subject($eid)) {
            return new WP_REST_Response(
                new \WP_Error(
                    'practical_subject_forbidden',
                    'Les sujets pratiques ne peuvent pas recevoir un statut classique tenté/réussi.'
                ),
                400
            );
        }
        
        $raw = (string) $r->get_param('status');

        if ($raw === 'succeeded') {
            $raw = 'solved';
        }
        if ($raw === 'reset') {
            $raw = 'none';
        }

        $allowed = ['none', 'attempted', 'solved'];
        $status  = in_array($raw, $allowed, true) ? $raw : 'none';

        if ($status === 'none') {
            $wpdb->delete(
                $p . 'user_status',
                ['user_id' => $uid, 'exercise_id' => $eid],
                ['%d', '%d']
            );

            if (!empty($wpdb->last_error)) {
                return new WP_REST_Response(
                    new \WP_Error('db_error', 'Suppression du statut impossible.'),
                    500
                );
            }

            return new WP_REST_Response(['ok' => true, 'status' => 'none'], 200);
        }

        $now = current_time('mysql');

        $data = [
            'user_id'     => $uid,
            'exercise_id' => $eid,
            'status'      => $status,
            'updated_at'  => $now,
            'declared_at' => ($status === 'solved') ? $now : null,
        ];

        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}user_status WHERE user_id=%d AND exercise_id=%d",
                $uid,
                $eid
            )
        );

        if ($exists) {
            $ok = $wpdb->update(
                $p . 'user_status',
                $data,
                ['user_id' => $uid, 'exercise_id' => $eid]
            );
        } else {
            $ok = $wpdb->insert($p . 'user_status', $data);
        }

        if ($ok === false || !empty($wpdb->last_error)) {
            return new WP_REST_Response(
                new \WP_Error('db_error', 'Écriture du statut impossible.'),
                500
            );
        }

        if (class_exists(\Ouinpo\Exercises\BadgeEngine::class)) {
            \Ouinpo\Exercises\BadgeEngine::recompute_for_user((int) $uid);
        }

        return new WP_REST_Response(['ok' => true, 'status' => $status], 200);
    }
}
