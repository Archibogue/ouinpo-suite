<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\AiSettings;
use Ouinpo\Suite\Core\Capabilities;
use WP_Error;

defined('ABSPATH') || exit;

final class ProjectsStudentAiAssistant
{
    private const QUOTA_SCOPE = 'projects_student_ai';
    private const AI_CLASS = '\\OuInPo\\SegFault\\OpenAI';
    private const MAX_TEXT = 1200;
    private const MAX_TASKS = 24;
    private const MAX_DELIVERABLES = 30;
    private const MAX_EVIDENCE = 18;
    private const MAX_LOGS = 10;
    private const MAX_COMPETENCIES = 40;
    private const STUDENT_FIELDS = [
        'my_role',
        'what_i_did',
        'difficulties',
        'solutions',
        'what_i_learned',
        'what_i_want_to_show',
    ];

    private Repository $repository;

    public function __construct(?Repository $repository = null)
    {
        $this->repository = $repository ?: new Repository();
    }

    public static function globalEnabled(): bool
    {
        return (int) AiSettings::get('ouinpo_ai_enabled') === 1
            && (int) AiSettings::get('ouinpo_projects_student_ai_enabled') === 1;
    }

    public static function projectStudentAiEnabled(array $project): bool
    {
        return !empty($project['student_ai_enabled']);
    }

    public function suggest(int $projectId, string $kind, array $input, int $userId)
    {
        $kind = sanitize_key($kind);
        if (!in_array($kind, ['reflection_questions', 'personal_summary', 'portfolio_draft'], true)) {
            return new WP_Error('ouinpo_projects_student_ai_bad_kind', 'Type de demande IA eleve invalide.', ['status' => 400]);
        }

        $project = $this->repository->getProject($projectId);
        if (!$project) {
            return new WP_Error('ouinpo_projects_not_found', 'Projet introuvable.', ['status' => 404]);
        }

        $access = $this->ensureAccess($project, $projectId, $userId);
        if (is_wp_error($access)) {
            return $access;
        }

        $studentInput = $this->studentInput($input);
        if ($studentInput['my_role'] === '' && $studentInput['what_i_did'] === '') {
            return new WP_Error(
                'ouinpo_projects_student_ai_input_required',
                'Indique d’abord ce que tu as réellement fait dans le projet.',
                ['status' => 400]
            );
        }

        $ready = $this->ensureAiReady($userId);
        if (is_wp_error($ready)) {
            $this->log($projectId, $kind, false, 'none', 0, $ready->get_error_code());
            return $ready;
        }

        $context = $this->buildStudentContext($projectId, $userId);
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->userPrompt($kind, $this->schemaForKind($kind), $studentInput, $context)],
        ];

        $provider = $this->providerLabel();
        $raw = (string) \OuInPo\SegFault\OpenAI::respond($messages, [
            'temperature' => 0.2,
            'max_tokens' => AiSettings::maxTokens('ouinpo_ai_projects_student_max_tokens'),
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ]);

        $decoded = self::parseJsonObject($raw);
        if (is_wp_error($decoded)) {
            $this->log($projectId, $kind, false, $provider, strlen($raw), $decoded->get_error_code());
            return $decoded;
        }

        $validated = $this->validateResponse($kind, $decoded);
        if (is_wp_error($validated)) {
            $this->log($projectId, $kind, false, $provider, strlen($raw), $validated->get_error_code());
            return $validated;
        }

        $this->log($projectId, $kind, true, $provider, strlen($raw), 'ok');

        return $validated + [
            'kind' => $kind,
            'project_id' => $projectId,
            'ai_notice' => 'Brouillon IA a relire, corriger et reformuler avec tes propres mots.',
        ];
    }

    private function ensureAccess(array $project, int $projectId, int $userId)
    {
        if (!self::globalEnabled()) {
            return new WP_Error('ouinpo_projects_student_ai_disabled', 'Assistant IA eleve desactive.', ['status' => 403]);
        }

        if (!self::projectStudentAiEnabled($project)) {
            return new WP_Error('ouinpo_projects_student_ai_project_disabled', 'Assistant IA eleve desactive pour ce projet.', ['status' => 403]);
        }

        if ($userId <= 0 || !$this->repository->isProjectMember($projectId, $userId)) {
            return new WP_Error('ouinpo_projects_student_ai_not_member', 'Assistant IA reserve aux membres actuels du projet.', ['status' => 403]);
        }

        if (!current_user_can(Capabilities::PROJECTS_AI_STUDENT_USE) && !current_user_can('manage_options')) {
            return new WP_Error('ouinpo_projects_student_ai_forbidden', 'Droit IA eleve requis.', ['status' => 403]);
        }

        return true;
    }

    private function ensureAiReady(int $userId)
    {
        $this->loadAiBridge();
        if (!class_exists(self::AI_CLASS) || !method_exists(self::AI_CLASS, 'respond')) {
            return new WP_Error('ouinpo_projects_student_ai_unavailable', 'Moteur IA indisponible.', ['status' => 503]);
        }

        return AiSettings::consumeUserRateLimit(
            self::QUOTA_SCOPE,
            $userId,
            AiSettings::quota('ouinpo_ai_projects_student_per_minute'),
            AiSettings::quota('ouinpo_ai_projects_student_per_day')
        );
    }

    private function loadAiBridge(): void
    {
        if (class_exists(self::AI_CLASS)) {
            return;
        }

        $base = defined('OUINPO_SUITE_DIR') ? OUINPO_SUITE_DIR : dirname(__DIR__, 3) . '/';
        foreach ([
            $base . 'src/Modules/SegFault/plugin/includes/Albert.php',
            $base . 'src/Modules/SegFault/plugin/includes/OpenAI.php',
        ] as $path) {
            if (is_file($path)) {
                require_once $path;
            }
        }
    }

    private function studentInput(array $input): array
    {
        $clean = [];
        foreach (self::STUDENT_FIELDS as $field) {
            $clean[$field] = $this->cleanString($input[$field] ?? '', 900);
        }

        return $clean;
    }

    private function buildStudentContext(int $projectId, int $userId): array
    {
        $project = $this->repository->getProjectSummary($projectId) ?: [];
        $board = $this->repository->getBoard($projectId);
        $deliverables = $this->repository->getDeliverables($projectId);
        $evidence = $this->repository->getEvidence($projectId);
        $logs = $this->repository->getLogs($projectId);
        $links = $this->repository->getCompetencyLinks($projectId);

        $studentTaskIds = [];
        $tasks = $this->studentTasks($board, $userId, $studentTaskIds);
        $studentEvidence = $this->studentEvidence($evidence, $userId);
        $evidenceDeliverableIds = array_values(array_unique(array_filter(array_map('intval', array_column($studentEvidence, 'deliverable_id')))));

        return [
            'project' => [
                'id' => $projectId,
                'title' => $this->cleanString($project['title'] ?? '', 190),
                'description' => $this->cleanString(wp_strip_all_tags((string) ($project['description'] ?? '')), self::MAX_TEXT),
                'status' => sanitize_key((string) ($project['status'] ?? '')),
                'period' => [
                    'start_date' => $this->cleanDate($project['start_date'] ?? ''),
                    'end_date' => $this->cleanDate($project['end_date'] ?? ''),
                ],
            ],
            'my_tasks' => $tasks,
            'unassigned_tasks_summary' => $this->unassignedTasksSummary($board),
            'deliverables' => $this->deliverablesContext($deliverables),
            'my_traces' => $studentEvidence,
            'global_traces_summary' => $this->evidenceSummary($evidence),
            'my_journal' => $this->studentLogs($logs, $userId),
            'competencies' => $this->competenciesContext($links, $studentTaskIds, $evidenceDeliverableIds),
        ];
    }

    private function studentTasks(array $board, int $userId, array &$taskIds): array
    {
        $tasks = [];
        foreach ($board as $column) {
            foreach ((array) ($column['tasks'] ?? []) as $task) {
                if ((int) ($task['assigned_user_id'] ?? 0) !== $userId && (int) ($task['created_by'] ?? 0) !== $userId) {
                    continue;
                }
                $taskIds[] = (int) $task['id'];
                $tasks[] = [
                    'id' => (int) $task['id'],
                    'title' => $this->cleanString($task['title'] ?? '', 190),
                    'description' => $this->cleanString($task['description'] ?? '', 500),
                    'status' => sanitize_key((string) ($task['status'] ?? '')),
                    'column' => $this->cleanString($column['title'] ?? '', 120),
                    'priority' => sanitize_key((string) ($task['priority'] ?? 'normal')),
                    'due_date' => $this->cleanDate($task['due_date'] ?? ''),
                ];
                if (count($tasks) >= self::MAX_TASKS) {
                    return $tasks;
                }
            }
        }

        return $tasks;
    }

    private function unassignedTasksSummary(array $board): array
    {
        $summary = [];
        foreach ($board as $column) {
            foreach ((array) ($column['tasks'] ?? []) as $task) {
                if ((int) ($task['assigned_user_id'] ?? 0) > 0) {
                    continue;
                }
                $key = sanitize_key((string) ($task['status'] ?? 'open'));
                $summary[$key] = ($summary[$key] ?? 0) + 1;
            }
        }

        return $summary;
    }

    private function deliverablesContext(array $deliverables): array
    {
        $items = [];
        foreach (array_slice($deliverables, 0, self::MAX_DELIVERABLES) as $deliverable) {
            $items[] = [
                'id' => (int) $deliverable['id'],
                'title' => $this->cleanString($deliverable['title'] ?? '', 190),
                'type' => sanitize_key((string) ($deliverable['type'] ?? 'other')),
                'status' => sanitize_key((string) ($deliverable['status'] ?? 'expected')),
                'due_date' => $this->cleanDate($deliverable['due_date'] ?? ''),
            ];
        }

        return $items;
    }

    private function studentEvidence(array $evidence, int $userId): array
    {
        $items = [];
        foreach ($evidence as $item) {
            if ((int) ($item['user_id'] ?? 0) !== $userId) {
                continue;
            }
            $items[] = [
                'id' => (int) $item['id'],
                'title' => $this->cleanString($item['title'] ?? '', 190),
                'description' => $this->cleanString($item['description'] ?? '', 500),
                'evidence_type' => sanitize_key((string) ($item['evidence_type'] ?? 'other')),
                'deliverable_id' => (int) ($item['deliverable_id'] ?? 0),
                'deliverable_title' => $this->cleanString($item['deliverable_title'] ?? '', 190),
                'task_id' => (int) ($item['task_id'] ?? 0),
                'task_title' => $this->cleanString($item['task_title'] ?? '', 190),
                'attachment_type' => $this->cleanString($item['attachment_mime'] ?? '', 120),
                'created_at' => $this->cleanString($item['created_at'] ?? '', 40),
            ];
            if (count($items) >= self::MAX_EVIDENCE) {
                break;
            }
        }

        return $items;
    }

    private function evidenceSummary(array $evidence): array
    {
        $summary = ['total' => count($evidence), 'by_type' => []];
        foreach ($evidence as $item) {
            $type = sanitize_key((string) ($item['evidence_type'] ?? 'other'));
            $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
        }

        return $summary;
    }

    private function studentLogs(array $logs, int $userId): array
    {
        $items = [];
        foreach ($logs as $log) {
            if ((int) ($log['user_id'] ?? 0) !== $userId) {
                continue;
            }
            $items[] = [
                'created_at' => $this->cleanString($log['created_at'] ?? '', 40),
                'work_done' => $this->cleanString($log['work_done'] ?? '', 700),
                'blockers' => $this->cleanString($log['blockers'] ?? '', 500),
                'decision_taken' => $this->cleanString($log['decision_taken'] ?? '', 500),
                'next_step' => $this->cleanString($log['next_step'] ?? '', 500),
            ];
            if (count($items) >= self::MAX_LOGS) {
                break;
            }
        }

        return $items;
    }

    private function competenciesContext(array $links, array $taskIds, array $deliverableIds): array
    {
        $items = [];
        $taskIds = array_map('intval', $taskIds);
        $deliverableIds = array_map('intval', $deliverableIds);
        foreach ($links as $link) {
            $type = sanitize_key((string) ($link['object_type'] ?? ''));
            $objectId = (int) ($link['object_id'] ?? 0);
            if (
                $type !== 'project'
                && !($type === 'task' && in_array($objectId, $taskIds, true))
                && !($type === 'deliverable' && in_array($objectId, $deliverableIds, true))
            ) {
                continue;
            }
            $label = (string) ($link['label'] ?: $link['competency'] ?: ('Competence #' . (int) $link['competency_id']));
            $items[] = [
                'competency_id' => (int) $link['competency_id'],
                'object_type' => $type,
                'object_id' => $objectId,
                'domain' => $this->cleanString($link['domain'] ?? '', 140),
                'label' => $this->cleanString(wp_strip_all_tags($label), 220),
            ];
            if (count($items) >= self::MAX_COMPETENCIES) {
                break;
            }
        }

        return $items;
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'Tu es un assistant BTS SIO pour aider un eleve a preparer son portfolio personnel.',
            'Tu reponds uniquement en JSON valide, sans Markdown ni texte hors JSON.',
            'Tu ne modifies jamais le projet et tu ne proposes aucune action automatique.',
            'Tu n inventes jamais de travail realise, de trace, de fichier, de competence ni de resultat.',
            'Tu distingues les elements prouves par le contexte des declarations de l eleve.',
            'Tu encourages l eleve a reformuler avec ses propres mots et a verifier avant soumission.',
            'Tu ne cites jamais de nom, email, chemin prive, URL de telechargement, prompt ou contenu de fichier.',
        ]);
    }

    private function userPrompt(string $kind, array $schema, array $studentInput, array $context): string
    {
        return "Demande: {$kind}\n"
            . "Schema JSON strict:\n"
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\nDeclaration de l eleve:\n"
            . wp_json_encode($studentInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\nContexte projet autorise et anonymise:\n"
            . wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function schemaForKind(string $kind): array
    {
        if ($kind === 'reflection_questions') {
            return [
                'questions' => [[
                    'theme' => 'string',
                    'question' => 'string',
                    'why_it_matters' => 'string',
                ]],
                'warnings' => ['string'],
            ];
        }

        if ($kind === 'personal_summary') {
            return [
                'personal_summary' => [
                    'draft' => 'string',
                    'strengths_to_keep' => ['string'],
                    'points_to_clarify' => ['string'],
                    'evidence_to_mention' => ['string'],
                    'questions_before_submission' => ['string'],
                ],
                'warnings' => ['string'],
            ];
        }

        return [
            'portfolio_draft' => [
                'context' => 'string',
                'my_role' => 'string',
                'productions' => 'string',
                'skills' => 'string',
                'difficulties_and_solutions' => 'string',
                'personal_review' => 'string',
                'to_verify' => ['string'],
            ],
            'warnings' => ['string'],
        ];
    }

    private function validateResponse(string $kind, array $decoded)
    {
        $warnings = $this->stringList($decoded['warnings'] ?? [], 8, 260);

        if ($kind === 'reflection_questions') {
            $questions = [];
            foreach (array_slice((array) ($decoded['questions'] ?? []), 0, 8) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $question = $this->cleanString($item['question'] ?? '', 400);
                if ($question === '') {
                    continue;
                }
                $questions[] = [
                    'theme' => $this->cleanString($item['theme'] ?? '', 120),
                    'question' => $question,
                    'why_it_matters' => $this->cleanString($item['why_it_matters'] ?? '', 400),
                ];
            }
            return $questions ? ['questions' => $questions, 'warnings' => $warnings] : $this->schemaError('Questions IA invalides.');
        }

        if ($kind === 'personal_summary') {
            $summary = is_array($decoded['personal_summary'] ?? null) ? $decoded['personal_summary'] : [];
            $draft = $this->cleanString($summary['draft'] ?? '', 2200);
            if ($draft === '') {
                return $this->schemaError('Synthese personnelle IA invalide.');
            }
            return [
                'personal_summary' => [
                    'draft' => $draft,
                    'strengths_to_keep' => $this->stringList($summary['strengths_to_keep'] ?? [], 8, 240),
                    'points_to_clarify' => $this->stringList($summary['points_to_clarify'] ?? [], 8, 240),
                    'evidence_to_mention' => $this->stringList($summary['evidence_to_mention'] ?? [], 8, 240),
                    'questions_before_submission' => $this->stringList($summary['questions_before_submission'] ?? [], 8, 240),
                ],
                'warnings' => $warnings,
            ];
        }

        $draft = is_array($decoded['portfolio_draft'] ?? null) ? $decoded['portfolio_draft'] : [];
        $required = ['context', 'my_role', 'productions', 'skills', 'difficulties_and_solutions', 'personal_review'];
        $portfolio = [];
        foreach ($required as $field) {
            $portfolio[$field] = $this->cleanString($draft[$field] ?? '', 1600);
        }
        $portfolio['to_verify'] = $this->stringList($draft['to_verify'] ?? [], 8, 240);

        if (implode('', array_intersect_key($portfolio, array_flip($required))) === '') {
            return $this->schemaError('Brouillon portfolio IA invalide.');
        }

        return ['portfolio_draft' => $portfolio, 'warnings' => $warnings];
    }

    private function stringList($value, int $limit, int $maxLength): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach (array_slice($value, 0, $limit) as $item) {
            $clean = $this->cleanString($item, $maxLength);
            if ($clean !== '') {
                $items[] = $clean;
            }
        }

        return $items;
    }

    private function schemaError(string $message): WP_Error
    {
        return new WP_Error('ouinpo_projects_student_ai_schema_invalid', $message, ['status' => 502]);
    }

    private function providerLabel(): string
    {
        if (class_exists('\\OuInPo\\SegFault\\Albert') && method_exists('\\OuInPo\\SegFault\\Albert', 'available') && \OuInPo\SegFault\Albert::available()) {
            return 'albert';
        }

        return 'openai';
    }

    private function log(int $projectId, string $action, bool $success, string $provider, int $sizeBytes, string $code): void
    {
        AiSettings::debug_log('Projects student AI request', [
            'user_id' => get_current_user_id(),
            'project_id' => $projectId,
            'action' => $action,
            'provider' => $provider,
            'success' => $success ? 'yes' : 'no',
            'code' => $code,
            'size_bytes' => $sizeBytes,
            'date' => current_time('mysql'),
        ]);
    }

    private static function parseJsonObject(string $raw)
    {
        $clean = trim(preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw);
        if ($clean === '') {
            return new WP_Error('ouinpo_projects_student_ai_empty_response', 'La reponse IA est vide.', ['status' => 502]);
        }

        if (preg_match('/^```(?:json|JSON)?\s*(.*?)\s*```$/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $clean = preg_replace('/^\s*```(?:json|JSON)?\s*/', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```\s*$/', '', $clean) ?? $clean;

        $candidate = self::extractBalancedObject($clean);
        if (is_wp_error($candidate)) {
            return $candidate;
        }

        try {
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return new WP_Error('ouinpo_projects_student_ai_invalid_json', 'La reponse IA ne contient pas de JSON valide.', [
                'status' => 502,
                'json_error' => $exception->getMessage(),
            ]);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            return new WP_Error('ouinpo_projects_student_ai_invalid_json_root', 'La reponse IA doit etre un objet JSON.', ['status' => 502]);
        }

        return $decoded;
    }

    private static function extractBalancedObject(string $text)
    {
        $length = strlen($text);
        for ($start = 0; $start < $length; $start++) {
            if ($text[$start] !== '{') {
                continue;
            }

            $depth = 0;
            $inString = false;
            $escaped = false;
            for ($i = $start; $i < $length; $i++) {
                $current = $text[$i];
                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }
                    if ($current === '\\') {
                        $escaped = true;
                        continue;
                    }
                    if ($current === '"') {
                        $inString = false;
                    }
                    continue;
                }
                if ($current === '"') {
                    $inString = true;
                    continue;
                }
                if ($current === '{') {
                    $depth++;
                    continue;
                }
                if ($current === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return substr($text, $start, $i - $start + 1);
                    }
                }
            }
        }

        return new WP_Error('ouinpo_projects_student_ai_invalid_json', 'Aucun objet JSON complet trouve dans la reponse IA.', ['status' => 502]);
    }

    private function cleanString($value, int $maxLength): string
    {
        $clean = trim(wp_strip_all_tags((string) $value));
        if ($clean === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($clean, 0, $maxLength) : substr($clean, 0, $maxLength);
    }

    private function cleanDate($value): string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
