<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class RestController
{
    private const NS = 'ouinpo-projects/v1';

    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(self::NS, '/projects', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'listProjects'],
                'permission_callback' => [self::class, 'canUseRest'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'createProject'],
                'permission_callback' => [self::class, 'canCreateProject'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getProject'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'updateProject'],
                'permission_callback' => [self::class, 'canManageProject'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/members', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getMembers'],
                'permission_callback' => [self::class, 'canManageProject'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'addMember'],
                'permission_callback' => [self::class, 'canManageProject'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/members/(?P<user_id>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'removeMember'],
                'permission_callback' => [self::class, 'canManageProject'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/board', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getBoard'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/tasks', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'createTask'],
                'permission_callback' => [self::class, 'canCreateTask'],
            ],
        ]);

        register_rest_route(self::NS, '/tasks/(?P<id>\d+)', [
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'updateTask'],
                'permission_callback' => [self::class, 'canEditTask'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'deleteTask'],
                'permission_callback' => [self::class, 'canDeleteTask'],
            ],
        ]);

        register_rest_route(self::NS, '/tasks/(?P<id>\d+)/move', [
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'moveTask'],
                'permission_callback' => [self::class, 'canEditTask'],
            ],
        ]);

        register_rest_route(self::NS, '/tasks/(?P<id>\d+)/comments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getComments'],
                'permission_callback' => [self::class, 'canViewTaskProject'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'addComment'],
                'permission_callback' => [self::class, 'canCommentTask'],
            ],
        ]);

        register_rest_route(self::NS, '/tasks/(?P<id>\d+)/checklist', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'addChecklistItem'],
                'permission_callback' => [self::class, 'canEditTask'],
            ],
        ]);

        register_rest_route(self::NS, '/checklist/(?P<id>\d+)', [
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'updateChecklistItem'],
                'permission_callback' => [self::class, 'canEditChecklistItemTask'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'deleteChecklistItem'],
                'permission_callback' => [self::class, 'canEditChecklistItemTask'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/logs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getLogs'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'addLog'],
                'permission_callback' => [self::class, 'canAddProjectLog'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/deliverables', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getDeliverables'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'createDeliverable'],
                'permission_callback' => [self::class, 'canCreateDeliverable'],
            ],
        ]);

        register_rest_route(self::NS, '/deliverables/(?P<id>\d+)', [
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'updateDeliverable'],
                'permission_callback' => [self::class, 'canManageDeliverable'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'deleteDeliverable'],
                'permission_callback' => [self::class, 'canManageDeliverable'],
            ],
        ]);

        register_rest_route(self::NS, '/deliverables/(?P<id>\d+)/status', [
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'updateDeliverableStatus'],
                'permission_callback' => [self::class, 'canManageDeliverable'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/evidence', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getEvidence'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'createEvidence'],
                'permission_callback' => [self::class, 'canCreateEvidence'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/evidence/upload', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'uploadEvidence'],
                'permission_callback' => [self::class, 'canCreateEvidence'],
            ],
        ]);

        register_rest_route(self::NS, '/evidence/(?P<id>\d+)', [
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'updateEvidence'],
                'permission_callback' => [self::class, 'canManageEvidence'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'deleteEvidence'],
                'permission_callback' => [self::class, 'canManageEvidence'],
            ],
        ]);

        register_rest_route(self::NS, '/evidence/(?P<id>\d+)/download', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'downloadEvidence'],
                'permission_callback' => [self::class, 'canDownloadEvidence'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/competencies', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getProjectCompetencies'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'addProjectCompetency'],
                'permission_callback' => [self::class, 'canManageProject'],
            ],
        ]);

        register_rest_route(self::NS, '/tasks/(?P<id>\d+)/competencies', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getTaskCompetencies'],
                'permission_callback' => [self::class, 'canViewTaskProject'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'addTaskCompetency'],
                'permission_callback' => [self::class, 'canManageTaskProject'],
            ],
        ]);

        register_rest_route(self::NS, '/deliverables/(?P<id>\d+)/competencies', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'getDeliverableCompetencies'],
                'permission_callback' => [self::class, 'canViewDeliverableProject'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'addDeliverableCompetency'],
                'permission_callback' => [self::class, 'canManageDeliverable'],
            ],
        ]);

        register_rest_route(self::NS, '/competency-links/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'deleteCompetencyLink'],
                'permission_callback' => [self::class, 'canDeleteCompetencyLink'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/export/html', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'exportProjectHtml'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/export/markdown', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'exportProjectMarkdown'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
        ]);

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/bts-situation/markdown', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'exportBtsSituationMarkdown'],
                'permission_callback' => [self::class, 'canViewProject'],
            ],
        ]);

        foreach ([
            'suggest-tasks' => 'suggestAiTasks',
            'suggest-deliverables' => 'suggestAiDeliverables',
            'suggest-competencies' => 'suggestAiCompetencies',
            'analyze-risks' => 'analyzeAiRisks',
            'portfolio-summary' => 'portfolioAiSummary',
            'teacher-summary' => 'teacherAiSummary',
        ] as $route => $callback) {
            register_rest_route(self::NS, '/projects/(?P<id>\d+)/ai/' . $route, [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [self::class, $callback],
                    'permission_callback' => [self::class, 'canUseProjectAi'],
                ],
            ]);
        }

        foreach ([
            'reflection-questions' => 'studentAiReflectionQuestions',
            'personal-summary' => 'studentAiPersonalSummary',
            'portfolio-draft' => 'studentAiPortfolioDraft',
        ] as $route => $callback) {
            register_rest_route(self::NS, '/projects/(?P<id>\d+)/student-ai/' . $route, [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [self::class, $callback],
                    'permission_callback' => [self::class, 'canUseProjectStudentAi'],
                ],
            ]);
        }

        register_rest_route(self::NS, '/projects/(?P<id>\d+)/ai/apply-suggestion', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'applyAiSuggestion'],
                'permission_callback' => [self::class, 'canApplyProjectAi'],
            ],
        ]);
    }

    public static function listProjects(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        return rest_ensure_response((new Repository())->listVisibleProjects(get_current_user_id()));
    }

    public static function createProject(WP_REST_Request $request)
    {
        $repository = new Repository();
        $projectId = $repository->createProject(self::body($request), get_current_user_id());

        if ($projectId <= 0) {
            return new WP_Error('ouinpo_projects_create_failed', 'Creation du projet impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getProjectSummary($projectId));
    }

    public static function getProject(WP_REST_Request $request)
    {
        $project = (new Repository())->getProjectSummary(self::id($request));

        return $project ? rest_ensure_response($project) : new WP_Error('ouinpo_projects_not_found', 'Projet introuvable.', ['status' => 404]);
    }

    public static function updateProject(WP_REST_Request $request)
    {
        $repository = new Repository();
        $projectId = self::id($request);

        if (!$repository->updateProject($projectId, self::body($request))) {
            return new WP_Error('ouinpo_projects_update_failed', 'Modification du projet impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getProjectSummary($projectId));
    }

    public static function getMembers(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response((new Repository())->getMembers(self::id($request)));
    }

    public static function addMember(WP_REST_Request $request)
    {
        $body = self::body($request);
        $userId = absint($body['user_id'] ?? 0);

        if ($userId <= 0 || !get_user_by('id', $userId)) {
            return new WP_Error('ouinpo_projects_invalid_member', 'Utilisateur invalide.', ['status' => 400]);
        }

        $repository = new Repository();
        if (!$repository->addMember(self::id($request), $userId, (string) ($body['role'] ?? 'member'))) {
            return new WP_Error('ouinpo_projects_member_failed', 'Ajout du membre impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getMembers(self::id($request)));
    }

    public static function removeMember(WP_REST_Request $request): WP_REST_Response
    {
        (new Repository())->removeMember(self::id($request), absint($request['user_id']));

        return rest_ensure_response(['deleted' => true]);
    }

    public static function getBoard(WP_REST_Request $request): WP_REST_Response
    {
        $repository = new Repository();
        $columns = $repository->getBoard(self::id($request));
        $userId = get_current_user_id();

        foreach ($columns as &$column) {
            foreach ($column['tasks'] as &$task) {
                $task['can_edit'] = $repository->userCanEditTask($task, $userId);
            }
            unset($task);
        }
        unset($column);

        return rest_ensure_response([
            'project' => $repository->getProjectSummary(self::id($request)),
            'columns' => $columns,
        ]);
    }

    public static function createTask(WP_REST_Request $request)
    {
        $repository = new Repository();
        $taskId = $repository->createTask(self::id($request), self::body($request), get_current_user_id());

        if ($taskId <= 0) {
            return new WP_Error('ouinpo_projects_task_failed', 'Creation de la tache impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getTask($taskId));
    }

    public static function updateTask(WP_REST_Request $request)
    {
        $repository = new Repository();
        $taskId = self::id($request);

        if (!$repository->updateTask($taskId, self::body($request))) {
            return new WP_Error('ouinpo_projects_task_update_failed', 'Modification de la tache impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getTask($taskId));
    }

    public static function moveTask(WP_REST_Request $request)
    {
        $body = self::body($request);
        $repository = new Repository();
        $taskId = self::id($request);
        $task = $repository->getTask($taskId);
        $columnId = absint($body['column_id'] ?? 0);

        if (!$task || $columnId <= 0 || !$repository->columnBelongsToProject($columnId, (int) $task['project_id'])) {
            return new WP_Error('ouinpo_projects_bad_move', 'Deplacement invalide.', ['status' => 400]);
        }

        $position = max(0, absint($body['position'] ?? $repository->nextTaskPosition($columnId)));

        if (!$repository->moveTask($taskId, $columnId, $position)) {
            return new WP_Error('ouinpo_projects_move_failed', 'Deplacement impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getTask($taskId));
    }

    public static function deleteTask(WP_REST_Request $request): WP_REST_Response
    {
        (new Repository())->deleteTask(self::id($request));

        return rest_ensure_response(['deleted' => true]);
    }

    public static function getComments(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response((new Repository())->getComments(self::id($request)));
    }

    public static function addComment(WP_REST_Request $request)
    {
        $body = self::body($request);
        $commentId = (new Repository())->addComment(self::id($request), get_current_user_id(), (string) ($body['comment'] ?? ''));

        if ($commentId <= 0) {
            return new WP_Error('ouinpo_projects_comment_failed', 'Commentaire vide ou invalide.', ['status' => 400]);
        }

        return rest_ensure_response(['id' => $commentId]);
    }

    public static function addChecklistItem(WP_REST_Request $request)
    {
        $body = self::body($request);
        $itemId = (new Repository())->addChecklistItem(self::id($request), (string) ($body['label'] ?? ''));

        if ($itemId <= 0) {
            return new WP_Error('ouinpo_projects_checklist_failed', 'Element de checklist invalide.', ['status' => 400]);
        }

        return rest_ensure_response((new Repository())->getChecklistItem($itemId));
    }

    public static function updateChecklistItem(WP_REST_Request $request)
    {
        $repository = new Repository();
        $itemId = self::id($request);

        if (!$repository->updateChecklistItem($itemId, self::body($request))) {
            return new WP_Error('ouinpo_projects_checklist_update_failed', 'Modification impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getChecklistItem($itemId));
    }

    public static function deleteChecklistItem(WP_REST_Request $request): WP_REST_Response
    {
        (new Repository())->deleteChecklistItem(self::id($request));

        return rest_ensure_response(['deleted' => true]);
    }

    public static function getLogs(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response((new Repository())->getLogs(self::id($request)));
    }

    public static function addLog(WP_REST_Request $request)
    {
        $logId = (new Repository())->addLog(self::id($request), get_current_user_id(), self::body($request));

        if ($logId <= 0) {
            return new WP_Error('ouinpo_projects_log_failed', 'Le travail realise est obligatoire.', ['status' => 400]);
        }

        return rest_ensure_response(['id' => $logId]);
    }

    public static function getDeliverables(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response((new Repository())->getDeliverables(self::id($request)));
    }

    public static function createDeliverable(WP_REST_Request $request)
    {
        $repository = new Repository();
        $body = self::body($request);

        $deliverableId = $repository->createDeliverable(self::id($request), $body, get_current_user_id());
        if ($deliverableId <= 0) {
            return new WP_Error('ouinpo_projects_deliverable_failed', 'Livrable invalide.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getDeliverable($deliverableId));
    }

    public static function updateDeliverable(WP_REST_Request $request)
    {
        $repository = new Repository();
        if (!$repository->updateDeliverable(self::id($request), self::body($request))) {
            return new WP_Error('ouinpo_projects_deliverable_update_failed', 'Modification du livrable impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getDeliverable(self::id($request)));
    }

    public static function updateDeliverableStatus(WP_REST_Request $request)
    {
        $body = self::body($request);
        $status = Repository::cleanDeliverableStatus($body['status'] ?? '');

        if ($status === 'validated' && !current_user_can(Capabilities::PROJECTS_VALIDATE) && !current_user_can('manage_options')) {
            return new WP_Error('ouinpo_projects_validation_forbidden', 'Validation refusee.', ['status' => 403]);
        }

        $repository = new Repository();
        if (!$repository->updateDeliverableStatus(self::id($request), $status, get_current_user_id())) {
            return new WP_Error('ouinpo_projects_deliverable_status_failed', 'Changement de statut impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getDeliverable(self::id($request)));
    }

    public static function deleteDeliverable(WP_REST_Request $request): WP_REST_Response
    {
        (new Repository())->deleteDeliverable(self::id($request));

        return rest_ensure_response(['deleted' => true]);
    }

    public static function getEvidence(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response((new Repository())->getEvidence(self::id($request)));
    }

    public static function createEvidence(WP_REST_Request $request)
    {
        $repository = new Repository();
        $evidenceId = $repository->createEvidence(self::id($request), self::body($request), get_current_user_id());

        if ($evidenceId <= 0) {
            return new WP_Error('ouinpo_projects_evidence_failed', 'Trace invalide.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getEvidenceItem($evidenceId));
    }

    public static function uploadEvidence(WP_REST_Request $request)
    {
        $projectId = self::id($request);
        $repository = new Repository();
        $files = $request->get_file_params();
        $file = is_array($files['file'] ?? null) ? $files['file'] : null;

        if (!$file) {
            return new WP_Error('ouinpo_projects_upload_missing', 'Aucun fichier recu.', ['status' => 400]);
        }

        $deliverableId = absint($request->get_param('deliverable_id'));
        if ($deliverableId > 0 && !$repository->deliverableBelongsToProject($deliverableId, $projectId)) {
            return new WP_Error('ouinpo_projects_bad_deliverable', 'Livrable invalide pour ce projet.', ['status' => 400]);
        }

        $taskId = absint($request->get_param('task_id'));
        if ($taskId > 0 && !$repository->taskBelongsToProject($taskId, $projectId)) {
            return new WP_Error('ouinpo_projects_bad_task', 'Tache invalide pour ce projet.', ['status' => 400]);
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded = PrivateFiles::storeUploadedFile($file);
        if (is_wp_error($uploaded)) {
            return new WP_Error($uploaded->get_error_code(), $uploaded->get_error_message(), ['status' => 400]);
        }

        $title = Repository::cleanTitle($request->get_param('title') ?: preg_replace('/\.[^.]+$/', '', wp_basename((string) $uploaded['file'])));
        $description = Repository::cleanLongText($request->get_param('description') ?? '');

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => (string) ($uploaded['type'] ?? 'application/octet-stream'),
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'inherit',
            'guid' => (string) ($uploaded['url'] ?? ''),
        ], (string) $uploaded['file'], 0, true);

        if (is_wp_error($attachmentId)) {
            return new WP_Error($attachmentId->get_error_code(), $attachmentId->get_error_message(), ['status' => 400]);
        }

        $metadata = wp_generate_attachment_metadata((int) $attachmentId, (string) $uploaded['file']);
        if (is_array($metadata)) {
            wp_update_attachment_metadata((int) $attachmentId, $metadata);
        }

        $evidenceId = $repository->createEvidence($projectId, [
            'title' => $title,
            'description' => $description,
            'evidence_type' => 'attachment',
            'url' => '',
            'attachment_id' => (int) $attachmentId,
            '_allow_attachment' => true,
            'deliverable_id' => $deliverableId ?: null,
            'task_id' => $taskId ?: null,
        ], get_current_user_id());

        if ($evidenceId <= 0) {
            return new WP_Error('ouinpo_projects_evidence_failed', 'Trace fichier invalide.', ['status' => 400]);
        }

        PrivateFiles::markAttachmentPrivate(
            (int) $attachmentId,
            $projectId,
            $evidenceId,
            (string) ($uploaded['private_relative_path'] ?? '')
        );

        return rest_ensure_response($repository->getEvidenceItem($evidenceId));
    }

    public static function downloadEvidence(WP_REST_Request $request)
    {
        $repository = new Repository();
        $evidence = $repository->getEvidenceItem(self::id($request));

        if (!$evidence) {
            return new WP_Error('ouinpo_projects_evidence_not_found', 'Trace introuvable.', ['status' => 404]);
        }

        $attachmentId = (int) ($evidence['attachment_id'] ?? 0);
        if ($attachmentId <= 0 || !PrivateFiles::isPrivateAttachment($attachmentId)) {
            return new WP_Error('ouinpo_projects_private_missing', 'Fichier prive introuvable.', ['status' => 404]);
        }

        if ((int) get_post_meta($attachmentId, PrivateFiles::META_PROJECT_ID, true) !== (int) $evidence['project_id']) {
            return new WP_Error('ouinpo_projects_private_mismatch', 'Fichier prive invalide.', ['status' => 403]);
        }

        if ((int) get_post_meta($attachmentId, PrivateFiles::META_EVIDENCE_ID, true) !== (int) $evidence['id']) {
            return new WP_Error('ouinpo_projects_private_mismatch', 'Fichier prive invalide.', ['status' => 403]);
        }

        $path = PrivateFiles::absolutePath(PrivateFiles::attachmentRelativePath($attachmentId));
        if (is_wp_error($path)) {
            return new WP_Error($path->get_error_code(), $path->get_error_message(), ['status' => 404]);
        }

        PrivateFiles::sendFile(
            (string) $path,
            (string) ($evidence['attachment_filename'] ?: get_the_title($attachmentId) ?: 'trace-' . (int) $evidence['id']),
            (string) ($evidence['attachment_mime'] ?: get_post_mime_type($attachmentId) ?: 'application/octet-stream')
        );
    }

    public static function updateEvidence(WP_REST_Request $request)
    {
        $repository = new Repository();

        if (!$repository->updateEvidence(self::id($request), self::body($request))) {
            return new WP_Error('ouinpo_projects_evidence_update_failed', 'Modification de la trace impossible.', ['status' => 400]);
        }

        return rest_ensure_response($repository->getEvidenceItem(self::id($request)));
    }

    public static function deleteEvidence(WP_REST_Request $request): WP_REST_Response
    {
        (new Repository())->deleteEvidence(self::id($request));

        return rest_ensure_response(['deleted' => true]);
    }

    public static function getProjectCompetencies(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response((new Repository())->getCompetencyLinks(self::id($request)));
    }

    public static function addProjectCompetency(WP_REST_Request $request)
    {
        $body = self::body($request);
        $projectId = self::id($request);
        $linkId = (new Repository())->addCompetencyLink(
            $projectId,
            'project',
            $projectId,
            absint($body['competency_id'] ?? 0),
            get_current_user_id()
        );

        return $linkId > 0
            ? rest_ensure_response(['id' => $linkId])
            : new WP_Error('ouinpo_projects_competency_failed', 'Lien competence invalide.', ['status' => 400]);
    }

    public static function getTaskCompetencies(WP_REST_Request $request)
    {
        $repository = new Repository();
        $task = $repository->getTask(self::id($request));
        if (!$task) {
            return new WP_Error('ouinpo_projects_task_not_found', 'Tache introuvable.', ['status' => 404]);
        }

        return rest_ensure_response($repository->getCompetencyLinks((int) $task['project_id'], 'task', self::id($request)));
    }

    public static function addTaskCompetency(WP_REST_Request $request)
    {
        $repository = new Repository();
        $task = $repository->getTask(self::id($request));
        if (!$task) {
            return new WP_Error('ouinpo_projects_task_not_found', 'Tache introuvable.', ['status' => 404]);
        }

        $body = self::body($request);
        $linkId = $repository->addCompetencyLink(
            (int) $task['project_id'],
            'task',
            self::id($request),
            absint($body['competency_id'] ?? 0),
            get_current_user_id()
        );

        return $linkId > 0
            ? rest_ensure_response(['id' => $linkId])
            : new WP_Error('ouinpo_projects_competency_failed', 'Lien competence invalide.', ['status' => 400]);
    }

    public static function getDeliverableCompetencies(WP_REST_Request $request)
    {
        $repository = new Repository();
        $deliverable = $repository->getDeliverable(self::id($request));
        if (!$deliverable) {
            return new WP_Error('ouinpo_projects_deliverable_not_found', 'Livrable introuvable.', ['status' => 404]);
        }

        return rest_ensure_response($repository->getCompetencyLinks((int) $deliverable['project_id'], 'deliverable', self::id($request)));
    }

    public static function addDeliverableCompetency(WP_REST_Request $request)
    {
        $repository = new Repository();
        $deliverable = $repository->getDeliverable(self::id($request));
        if (!$deliverable) {
            return new WP_Error('ouinpo_projects_deliverable_not_found', 'Livrable introuvable.', ['status' => 404]);
        }

        $body = self::body($request);
        $linkId = $repository->addCompetencyLink(
            (int) $deliverable['project_id'],
            'deliverable',
            self::id($request),
            absint($body['competency_id'] ?? 0),
            get_current_user_id()
        );

        return $linkId > 0
            ? rest_ensure_response(['id' => $linkId])
            : new WP_Error('ouinpo_projects_competency_failed', 'Lien competence invalide.', ['status' => 400]);
    }

    public static function deleteCompetencyLink(WP_REST_Request $request): WP_REST_Response
    {
        (new Repository())->deleteCompetencyLink(self::id($request));

        return rest_ensure_response(['deleted' => true]);
    }

    public static function exportProjectHtml(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response([
            'content' => ProjectExporter::projectHtml(self::id($request)),
        ]);
    }

    public static function exportProjectMarkdown(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response([
            'content' => ProjectExporter::projectMarkdown(self::id($request)),
            'filename' => 'projet-' . self::id($request) . '.md',
        ]);
    }

    public static function exportBtsSituationMarkdown(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response([
            'content' => ProjectExporter::btsSituationMarkdown(self::id($request)),
            'filename' => 'situation-bts-' . self::id($request) . '.md',
        ]);
    }

    public static function suggestAiTasks(WP_REST_Request $request)
    {
        return self::aiSuggest($request, 'suggest_tasks');
    }

    public static function suggestAiDeliverables(WP_REST_Request $request)
    {
        return self::aiSuggest($request, 'suggest_deliverables');
    }

    public static function suggestAiCompetencies(WP_REST_Request $request)
    {
        return self::aiSuggest($request, 'suggest_competencies');
    }

    public static function analyzeAiRisks(WP_REST_Request $request)
    {
        return self::aiSuggest($request, 'analyze_risks');
    }

    public static function portfolioAiSummary(WP_REST_Request $request)
    {
        return self::aiSuggest($request, 'portfolio_summary');
    }

    public static function teacherAiSummary(WP_REST_Request $request)
    {
        return self::aiSuggest($request, 'teacher_summary');
    }

    public static function studentAiReflectionQuestions(WP_REST_Request $request)
    {
        return self::studentAiSuggest($request, 'reflection_questions');
    }

    public static function studentAiPersonalSummary(WP_REST_Request $request)
    {
        return self::studentAiSuggest($request, 'personal_summary');
    }

    public static function studentAiPortfolioDraft(WP_REST_Request $request)
    {
        return self::studentAiSuggest($request, 'portfolio_draft');
    }

    public static function applyAiSuggestion(WP_REST_Request $request)
    {
        $result = (new ProjectsAiAssistant())->applySuggestion(
            self::id($request),
            self::body($request),
            get_current_user_id()
        );

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function canUseRest(WP_REST_Request $request)
    {
        unset($request);

        return self::requireLoggedInRestNonce();
    }

    public static function canCreateProject(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        return current_user_can(Capabilities::PROJECTS_CREATE)
            || current_user_can(Capabilities::PROJECTS_MANAGE_CLASS)
            || current_user_can(Capabilities::PROJECTS_MANAGE_ALL)
            || current_user_can('manage_options')
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Droit de creation requis.', ['status' => 403]);
    }

    public static function canViewProject(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $projectId = self::id($request);
        if ($projectId <= 0) {
            return new WP_Error('ouinpo_projects_bad_id', 'Identifiant invalide.', ['status' => 400]);
        }

        return (new Repository())->userCanViewProject($projectId, get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Acces refuse.', ['status' => 403]);
    }

    public static function canManageProject(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        return (new Repository())->userCanManageProject(self::id($request), get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Gestion du projet refusee.', ['status' => 403]);
    }

    public static function canUseProjectAi(WP_REST_Request $request)
    {
        return self::canUseProjectAiWithCapability($request, Capabilities::PROJECTS_AI_USE);
    }

    public static function canApplyProjectAi(WP_REST_Request $request)
    {
        return self::canUseProjectAiWithCapability($request, Capabilities::PROJECTS_AI_APPLY, true);
    }

    public static function canUseProjectStudentAi(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $projectId = self::id($request);
        if ($projectId <= 0) {
            return new WP_Error('ouinpo_projects_bad_id', 'Identifiant invalide.', ['status' => 400]);
        }

        $repository = new Repository();
        $project = $repository->getProject($projectId);
        if (!$project) {
            return new WP_Error('ouinpo_projects_not_found', 'Projet introuvable.', ['status' => 404]);
        }

        if (sanitize_key((string) ($project['status'] ?? '')) === 'archived') {
            return new WP_Error('ouinpo_projects_student_ai_archived', 'Assistant IA eleve indisponible pour un projet archive.', ['status' => 403]);
        }

        if (!ProjectsStudentAiAssistant::globalEnabled()) {
            return new WP_Error('ouinpo_projects_student_ai_disabled', 'Assistant IA eleve desactive.', ['status' => 403]);
        }

        if (!ProjectsStudentAiAssistant::projectStudentAiEnabled($project)) {
            return new WP_Error('ouinpo_projects_student_ai_project_disabled', 'Assistant IA eleve desactive pour ce projet.', ['status' => 403]);
        }

        $userId = get_current_user_id();
        if (!$repository->isProjectMember($projectId, $userId)) {
            return new WP_Error('ouinpo_projects_student_ai_not_member', 'Assistant IA reserve aux membres actuels du projet.', ['status' => 403]);
        }

        return current_user_can(Capabilities::PROJECTS_AI_STUDENT_USE) || current_user_can('manage_options')
            ? true
            : new WP_Error('ouinpo_projects_student_ai_forbidden', 'Droit IA eleve requis.', ['status' => 403]);
    }

    public static function canCreateTask(WP_REST_Request $request)
    {
        $view = self::canViewProject($request);
        if (is_wp_error($view)) {
            return $view;
        }

        $userId = get_current_user_id();
        if ((new Repository())->userCanManageProject(self::id($request), $userId) || current_user_can(Capabilities::PROJECTS_EDIT_OWN_TASKS)) {
            return true;
        }

        return new WP_Error('ouinpo_projects_forbidden', 'Creation de tache refusee.', ['status' => 403]);
    }

    public static function canEditTask(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $task = $repository->getTask(self::id($request));

        if (!$task) {
            return new WP_Error('ouinpo_projects_task_not_found', 'Tache introuvable.', ['status' => 404]);
        }

        return $repository->userCanEditTask($task, get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Modification de tache refusee.', ['status' => 403]);
    }

    public static function canDeleteTask(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $task = $repository->getTask(self::id($request));

        if (!$task) {
            return new WP_Error('ouinpo_projects_task_not_found', 'Tache introuvable.', ['status' => 404]);
        }

        $userId = get_current_user_id();
        if (
            $repository->userCanManageProject((int) $task['project_id'], $userId)
            || ((int) $task['created_by'] === $userId && $repository->userCanEditTask($task, $userId))
        ) {
            return true;
        }

        return new WP_Error('ouinpo_projects_forbidden', 'Suppression de tache refusee.', ['status' => 403]);
    }

    public static function canViewTaskProject(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $task = (new Repository())->getTask(self::id($request));
        if (!$task) {
            return new WP_Error('ouinpo_projects_task_not_found', 'Tache introuvable.', ['status' => 404]);
        }

        return (new Repository())->userCanViewProject((int) $task['project_id'], get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Acces refuse.', ['status' => 403]);
    }

    public static function canCommentTask(WP_REST_Request $request)
    {
        $view = self::canViewTaskProject($request);
        if (is_wp_error($view)) {
            return $view;
        }

        return current_user_can(Capabilities::PROJECTS_COMMENT)
            || current_user_can(Capabilities::PROJECTS_MANAGE_CLASS)
            || current_user_can(Capabilities::PROJECTS_MANAGE_ALL)
            || current_user_can('manage_options')
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Commentaire refuse.', ['status' => 403]);
    }

    public static function canEditChecklistItemTask(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $item = $repository->getChecklistItem(self::id($request));
        if (!$item) {
            return new WP_Error('ouinpo_projects_checklist_not_found', 'Element introuvable.', ['status' => 404]);
        }

        $task = $repository->getTask((int) $item['task_id']);
        if (!$task) {
            return new WP_Error('ouinpo_projects_task_not_found', 'Tache introuvable.', ['status' => 404]);
        }

        return $repository->userCanEditTask($task, get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Modification refusee.', ['status' => 403]);
    }

    public static function canAddProjectLog(WP_REST_Request $request)
    {
        $view = self::canViewProject($request);
        if (is_wp_error($view)) {
            return $view;
        }

        return current_user_can(Capabilities::PROJECTS_COMMENT)
            || current_user_can(Capabilities::PROJECTS_MANAGE_CLASS)
            || current_user_can(Capabilities::PROJECTS_MANAGE_ALL)
            || current_user_can('manage_options')
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Journal refuse.', ['status' => 403]);
    }

    public static function canCreateDeliverable(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        return (new Repository())->userCanManageProject(self::id($request), get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Creation de livrable refusee.', ['status' => 403]);
    }

    public static function canManageDeliverable(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $deliverable = $repository->getDeliverable(self::id($request));
        if (!$deliverable) {
            return new WP_Error('ouinpo_projects_deliverable_not_found', 'Livrable introuvable.', ['status' => 404]);
        }

        return $repository->userCanManageProject((int) $deliverable['project_id'], get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Gestion du livrable refusee.', ['status' => 403]);
    }

    public static function canViewDeliverableProject(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $deliverable = $repository->getDeliverable(self::id($request));
        if (!$deliverable) {
            return new WP_Error('ouinpo_projects_deliverable_not_found', 'Livrable introuvable.', ['status' => 404]);
        }

        return $repository->userCanViewProject((int) $deliverable['project_id'], get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Acces refuse.', ['status' => 403]);
    }

    public static function canCreateEvidence(WP_REST_Request $request)
    {
        $view = self::canViewProject($request);
        if (is_wp_error($view)) {
            return $view;
        }

        return (new Repository())->userCanSubmitProjectItem(self::id($request), get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Depot de trace refuse.', ['status' => 403]);
    }

    public static function canManageEvidence(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $evidence = $repository->getEvidenceItem(self::id($request));
        if (!$evidence) {
            return new WP_Error('ouinpo_projects_evidence_not_found', 'Trace introuvable.', ['status' => 404]);
        }

        return $repository->userCanManageEvidenceItem($evidence, get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Gestion de la trace refusee.', ['status' => 403]);
    }

    public static function canDownloadEvidence(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $evidence = $repository->getEvidenceItem(self::id($request));
        if (!$evidence) {
            return new WP_Error('ouinpo_projects_evidence_not_found', 'Trace introuvable.', ['status' => 404]);
        }

        return $repository->userCanViewProject((int) $evidence['project_id'], get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Acces refuse.', ['status' => 403]);
    }

    public static function canManageTaskProject(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $task = $repository->getTask(self::id($request));
        if (!$task) {
            return new WP_Error('ouinpo_projects_task_not_found', 'Tache introuvable.', ['status' => 404]);
        }

        return $repository->userCanManageProject((int) $task['project_id'], get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Gestion du projet refusee.', ['status' => 403]);
    }

    public static function canDeleteCompetencyLink(WP_REST_Request $request)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $repository = new Repository();
        $link = $repository->getCompetencyLink(self::id($request));
        if (!$link) {
            return new WP_Error('ouinpo_projects_competency_link_not_found', 'Lien introuvable.', ['status' => 404]);
        }

        return $repository->userCanManageProject((int) $link['project_id'], get_current_user_id())
            ? true
            : new WP_Error('ouinpo_projects_forbidden', 'Gestion du lien refusee.', ['status' => 403]);
    }

    private static function requireLoggedInRestNonce()
    {
        if (!is_user_logged_in()) {
            return new WP_Error('ouinpo_projects_auth_required', 'Connexion requise.', ['status' => 401]);
        }

        $nonce = isset($_SERVER['HTTP_X_WP_NONCE'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_X_WP_NONCE']))
            : '';

        if ($nonce === '' && isset($_REQUEST['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce']));
        }

        if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('ouinpo_projects_bad_nonce', 'Nonce REST invalide.', ['status' => 403]);
        }

        return true;
    }

    private static function id(WP_REST_Request $request): int
    {
        return absint($request['id']);
    }

    private static function aiSuggest(WP_REST_Request $request, string $kind)
    {
        $result = (new ProjectsAiAssistant())->suggest(
            self::id($request),
            $kind,
            self::body($request),
            get_current_user_id()
        );

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    private static function studentAiSuggest(WP_REST_Request $request, string $kind)
    {
        $result = (new ProjectsStudentAiAssistant())->suggest(
            self::id($request),
            $kind,
            self::body($request),
            get_current_user_id()
        );

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    private static function canUseProjectAiWithCapability(WP_REST_Request $request, string $capability, bool $requireUseCapability = false)
    {
        $allowed = self::requireLoggedInRestNonce();
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $projectId = self::id($request);
        if ($projectId <= 0) {
            return new WP_Error('ouinpo_projects_bad_id', 'Identifiant invalide.', ['status' => 400]);
        }

        $repository = new Repository();
        if (!$repository->getProject($projectId)) {
            return new WP_Error('ouinpo_projects_not_found', 'Projet introuvable.', ['status' => 404]);
        }

        $userId = get_current_user_id();
        $hasCapability = current_user_can($capability) || current_user_can('manage_options');
        if ($requireUseCapability) {
            $hasCapability = $hasCapability && (current_user_can(Capabilities::PROJECTS_AI_USE) || current_user_can('manage_options'));
        }
        if (!$hasCapability || !$repository->userCanManageProject($projectId, $userId)) {
            return new WP_Error('ouinpo_projects_ai_forbidden', 'Assistant IA reserve aux enseignants responsables du projet.', ['status' => 403]);
        }

        return true;
    }

    private static function body(WP_REST_Request $request): array
    {
        $body = $request->get_json_params();

        if (!is_array($body)) {
            $body = $request->get_body_params();
        }

        return is_array($body) ? $body : [];
    }
}
