<?php
namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\AssessmentsService;
use Ouinpo\Suite\Core\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class AssessmentsCompetencyRoutes
{
    private const NS = 'ouinpo/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/assessments-bo', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'list'],
                'permission_callback' => fn() => Capabilities::can(Capabilities::MANAGE_ASSESSMENTS),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'create'],
                'permission_callback' => fn() => Capabilities::can(Capabilities::MANAGE_ASSESSMENTS),
            ],
        ]);

        register_rest_route(self::NS, '/assessments-bo/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get'],
                'permission_callback' => fn() => Capabilities::can(Capabilities::MANAGE_ASSESSMENTS),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'update'],
                'permission_callback' => fn() => Capabilities::can(Capabilities::MANAGE_ASSESSMENTS),
            ],
        ]);

        register_rest_route(self::NS, '/assessments-bo/(?P<id>\d+)/results', [
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'save_results'],
                'permission_callback' => fn() => Capabilities::can(Capabilities::MANAGE_ASSESSMENTS),
            ],
        ]);
    }

    public static function list(): WP_REST_Response
    {
        return new WP_REST_Response(AssessmentsService::list_competency_assessments(), 200);
    }

    public static function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int)$request['id'];
        $data = AssessmentsService::get_competency_assessment($id);

        if (!$data) {
            return new WP_Error('assessment_not_found', 'DS introuvable.', ['status' => 404]);
        }

        return new WP_REST_Response($data, 200);
    }

    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = AssessmentsService::save_competency_assessment([
            'title'          => $request->get_param('title'),
            'group_id'       => $request->get_param('group_id'),
            'due_on'         => $request->get_param('due_on'),
            'notes'          => $request->get_param('notes'),
            'competency_ids' => (array)$request->get_param('competency_ids'),
        ]);

        if (is_wp_error($id)) {
            return $id;
        }

        return new WP_REST_Response(['id' => $id], 201);
    }

    public static function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $assessment_id = (int)$request['id'];

        $id = AssessmentsService::save_competency_assessment([
            'title'          => $request->get_param('title'),
            'group_id'       => $request->get_param('group_id'),
            'due_on'         => $request->get_param('due_on'),
            'notes'          => $request->get_param('notes'),
            'competency_ids' => (array)$request->get_param('competency_ids'),
        ], $assessment_id);

        if (is_wp_error($id)) {
            return $id;
        }

        return new WP_REST_Response(['id' => $id, 'updated' => true], 200);
    }

    public static function save_results(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $assessment_id = (int)$request['id'];
        $results = (array)$request->get_param('results');
        $apply_progression = (bool)$request->get_param('apply_progression');

        $ok = AssessmentsService::save_competency_results(
            $assessment_id,
            $results,
            $apply_progression,
            get_current_user_id()
        );

        if (is_wp_error($ok)) {
            return $ok;
        }

        return new WP_REST_Response(['ok' => true], 200);
    }
}
