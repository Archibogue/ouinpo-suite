<?php

namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\Services\AiCorrectionService;
use Ouinpo\Exercises\Services\CopyUploadService;
use Ouinpo\Exercises\Services\CorrectionBatchService;
use Ouinpo\Exercises\Services\CorrectionPersistService;
use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class AiCorrectionRoutes
{
    private const NS = 'ouinpo/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/ai-corrections/batches', [
            ['methods' => 'GET', 'callback' => [self::class, 'batches'], 'permission_callback' => [self::class, 'can_manage']],
            ['methods' => 'POST', 'callback' => [self::class, 'create_batch'], 'permission_callback' => [self::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/ai-corrections/batches/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [self::class, 'batch'], 'permission_callback' => [self::class, 'can_manage']],
            ['methods' => 'DELETE', 'callback' => [self::class, 'delete_batch'], 'permission_callback' => [self::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/ai-corrections/batches/(?P<id>\d+)/copies', [
            ['methods' => 'POST', 'callback' => [self::class, 'upload_copy'], 'permission_callback' => [self::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/ai-corrections/copies/(?P<id>\d+)/analyze', [
            ['methods' => 'POST', 'callback' => [self::class, 'analyze'], 'permission_callback' => [self::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/ai-corrections/copies/(?P<id>\d+)/validate', [
            ['methods' => 'POST', 'callback' => [self::class, 'validate_copy'], 'permission_callback' => [self::class, 'can_manage']],
        ]);
        register_rest_route(self::NS, '/ai-corrections/copies/(?P<id>\d+)/reject', [
            ['methods' => 'POST', 'callback' => [self::class, 'reject_copy'], 'permission_callback' => [self::class, 'can_manage']],
        ]);
    }

    public static function can_manage(): bool
    {
        return current_user_can(Capabilities::MANAGE_ASSESSMENTS) || current_user_can('manage_options');
    }

    public static function batches()
    {
        return rest_ensure_response(['assessments' => CorrectionBatchService::assessments()]);
    }

    public static function create_batch(\WP_REST_Request $request)
    {
        $p = (array) $request->get_json_params();
        $id = CorrectionBatchService::create_batch(
            (int) ($p['assessment_id'] ?? 0),
            (int) ($p['group_id'] ?? 0),
            sanitize_text_field((string) ($p['title'] ?? ''))
        );
        if (is_wp_error($id)) {
            return $id;
        }
        return rest_ensure_response(['batch' => CorrectionBatchService::get_batch((int) $id)]);
    }

    public static function batch(\WP_REST_Request $request)
    {
        $batch = CorrectionBatchService::get_batch((int) $request['id']);
        return $batch && (string) ($batch['source_type'] ?? 'scan') === 'scan'
            ? rest_ensure_response(['batch' => $batch])
            : new \WP_Error('batch_not_found', 'Lot introuvable.', ['status' => 404]);
    }

    public static function delete_batch(\WP_REST_Request $request)
    {
        $batch = CorrectionBatchService::get_batch((int) $request['id']);
        if (!$batch || (string) ($batch['source_type'] ?? 'scan') !== 'scan') {
            return new \WP_Error('batch_not_found', 'Lot introuvable.', ['status' => 404]);
        }
        return rest_ensure_response(['ok' => CorrectionBatchService::delete_batch((int) $request['id'])]);
    }

    public static function upload_copy(\WP_REST_Request $request)
    {
        $batch_id = (int) $request['id'];
        $batch = CorrectionBatchService::get_batch($batch_id);
        if (!$batch || (string) ($batch['source_type'] ?? 'scan') !== 'scan') {
            return new \WP_Error('batch_not_found', 'Lot introuvable.', ['status' => 404]);
        }
        $files = $request->get_file_params();
        $file = $files['copy_file'] ?? null;
        if (!is_array($file)) {
            return new \WP_Error('missing_file', 'Fichier manquant.', ['status' => 400]);
        }
        $copy_id = CopyUploadService::store(
            $file,
            $batch_id,
            sanitize_text_field((string) $request->get_param('student_ref')),
            (int) $request->get_param('student_user_id'),
            (string) $request->get_param('manual_text')
        );
        if (is_wp_error($copy_id)) {
            return $copy_id;
        }
        return rest_ensure_response(['batch' => CorrectionBatchService::get_batch($batch_id), 'copy_id' => $copy_id]);
    }

    public static function analyze(\WP_REST_Request $request)
    {
        $proposal = (new AiCorrectionService())->analyze((int) $request['id']);
        if (is_wp_error($proposal)) {
            return $proposal;
        }
        return rest_ensure_response(['proposal' => $proposal]);
    }

    public static function validate_copy(\WP_REST_Request $request)
    {
        $params = (array) $request->get_json_params();
        $correction = is_array($params['correction'] ?? null) ? $params['correction'] : [];
        $result = CorrectionPersistService::validate_copy((int) $request['id'], $correction);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(['correction' => $result]);
    }

    public static function reject_copy(\WP_REST_Request $request)
    {
        CorrectionPersistService::reject_copy((int) $request['id']);
        return rest_ensure_response(['ok' => true]);
    }
}
