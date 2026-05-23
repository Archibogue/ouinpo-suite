<?php

namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\Services\AiFileCorrectionService;
use Ouinpo\Exercises\Services\FileCorrectionBatchService;
use Ouinpo\Exercises\Services\FileCorrectionPersistService;
use Ouinpo\Exercises\Services\StudentFileUploadService;
use Ouinpo\Suite\Core\AiSettings;
use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class AiFileCorrectionRoutes
{
    private const NS = 'ouinpo/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/ai-file-corrections/sources', [
            ['methods' => 'GET', 'callback' => [self::class, 'sources'], 'permission_callback' => [self::class, 'can_use']],
        ]);
        register_rest_route(self::NS, '/ai-file-corrections/batches', [
            ['methods' => 'POST', 'callback' => [self::class, 'create_batch'], 'permission_callback' => [self::class, 'can_use']],
        ]);
        register_rest_route(self::NS, '/ai-file-corrections/batches/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [self::class, 'batch'], 'permission_callback' => [self::class, 'can_use']],
            ['methods' => 'DELETE', 'callback' => [self::class, 'delete_batch'], 'permission_callback' => [self::class, 'can_use']],
        ]);
        register_rest_route(self::NS, '/ai-file-corrections/batches/(?P<id>\d+)/submissions', [
            ['methods' => 'POST', 'callback' => [self::class, 'upload_submission'], 'permission_callback' => [self::class, 'can_use']],
        ]);
        register_rest_route(self::NS, '/ai-file-corrections/submissions/(?P<id>\d+)/analyze', [
            ['methods' => 'POST', 'callback' => [self::class, 'analyze'], 'permission_callback' => [self::class, 'can_use']],
        ]);
        register_rest_route(self::NS, '/ai-file-corrections/submissions/(?P<id>\d+)/validate', [
            ['methods' => 'POST', 'callback' => [self::class, 'validate_copy'], 'permission_callback' => [self::class, 'can_use']],
        ]);
        register_rest_route(self::NS, '/ai-file-corrections/submissions/(?P<id>\d+)/reject', [
            ['methods' => 'POST', 'callback' => [self::class, 'reject_copy'], 'permission_callback' => [self::class, 'can_use']],
        ]);
    }

    public static function can_manage(): bool
    {
        return current_user_can(Capabilities::MANAGE_ASSESSMENTS) || current_user_can('manage_options');
    }

    public static function can_use(): bool
    {
        return self::can_manage() && (int) AiSettings::get('ouinpo_ai_file_correction_enabled') === 1;
    }

    public static function sources()
    {
        return rest_ensure_response(FileCorrectionBatchService::sources());
    }

    public static function create_batch(\WP_REST_Request $request)
    {
        $p = (array) $request->get_json_params();
        $id = FileCorrectionBatchService::create_batch(
            sanitize_key((string) ($p['context_type'] ?? 'assessment')),
            (int) ($p['context_id'] ?? 0),
            (int) ($p['group_id'] ?? 0),
            sanitize_text_field((string) ($p['title'] ?? ''))
        );
        if (is_wp_error($id)) {
            return $id;
        }
        return rest_ensure_response(['batch' => FileCorrectionBatchService::get_batch((int) $id)]);
    }

    public static function batch(\WP_REST_Request $request)
    {
        $batch = FileCorrectionBatchService::get_batch((int) $request['id']);
        return $batch ? rest_ensure_response(['batch' => $batch]) : new \WP_Error('batch_not_found', 'Lot fichiers introuvable.', ['status' => 404]);
    }

    public static function delete_batch(\WP_REST_Request $request)
    {
        $batch_id = (int) $request['id'];
        if (!FileCorrectionBatchService::get_batch($batch_id)) {
            return new \WP_Error('batch_not_found', 'Lot fichiers introuvable.', ['status' => 404]);
        }
        return rest_ensure_response(['ok' => \Ouinpo\Exercises\Services\CorrectionBatchService::delete_batch($batch_id)]);
    }

    public static function upload_submission(\WP_REST_Request $request)
    {
        $batch_id = (int) $request['id'];
        $files = $request->get_file_params();
        $uploaded = self::normalize_files($files['student_files'] ?? null);
        if (empty($uploaded)) {
            return new \WP_Error('missing_file', 'Fichier manquant.', ['status' => 400]);
        }
        $limits = StudentFileUploadService::validate_request_limits($uploaded);
        if (is_wp_error($limits)) {
            return $limits;
        }

        $created = [];
        foreach ($uploaded as $file) {
            $ids = StudentFileUploadService::store(
                $file,
                $batch_id,
                sanitize_text_field((string) $request->get_param('student_ref')),
                (int) $request->get_param('student_user_id')
            );
            if (is_wp_error($ids)) {
                return $ids;
            }
            $created = array_merge($created, $ids);
        }

        return rest_ensure_response([
            'batch' => FileCorrectionBatchService::get_batch($batch_id),
            'submission_ids' => $created,
        ]);
    }

    public static function analyze(\WP_REST_Request $request)
    {
        $proposal = (new AiFileCorrectionService())->analyze((int) $request['id']);
        if (is_wp_error($proposal)) {
            return $proposal;
        }
        return rest_ensure_response(['proposal' => $proposal]);
    }

    public static function validate_copy(\WP_REST_Request $request)
    {
        $params = (array) $request->get_json_params();
        $correction = is_array($params['correction'] ?? null) ? $params['correction'] : [];
        $result = FileCorrectionPersistService::validate_copy((int) $request['id'], $correction);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(['correction' => $result]);
    }

    public static function reject_copy(\WP_REST_Request $request)
    {
        FileCorrectionPersistService::reject_copy((int) $request['id']);
        return rest_ensure_response(['ok' => true]);
    }

    private static function normalize_files($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        if (!is_array($raw['name'] ?? null)) {
            return [$raw];
        }

        $files = [];
        foreach ((array) $raw['name'] as $i => $name) {
            if ((string) $name === '') {
                continue;
            }
            $files[] = [
                'name' => $name,
                'type' => $raw['type'][$i] ?? '',
                'tmp_name' => $raw['tmp_name'][$i] ?? '',
                'error' => $raw['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $raw['size'][$i] ?? 0,
            ];
        }
        return $files;
    }
}
