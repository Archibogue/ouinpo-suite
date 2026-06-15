<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\AiSettings;
use Ouinpo\Suite\Core\Ai\JsonResponseParser;
use WP_Error;

defined('ABSPATH') || exit;

final class ProjectsAiAssistant
{
    private const USAGE = 'pedagogical_suggestions';
    private const QUOTA_SCOPE = 'teacher_ai';
    private const AI_CLASS = '\\OuInPo\\SegFault\\OpenAI';
    private const MAX_AI_TASKS = 30;
    private const MAX_AI_DELIVERABLES = 20;
    private const MAX_AI_COMPETENCY_LINKS = 40;
    private const MAX_AI_RISKS = 20;
    private const MAX_AI_TEXT_LENGTH = 1400;
    private const MAX_AI_CONTEXT_TASKS = 60;
    private const MAX_AI_CONTEXT_DELIVERABLES = 60;
    private const MAX_AI_CONTEXT_EVIDENCE = 40;
    private const MAX_AI_CONTEXT_LOGS = 12;

    private Repository $repository;

    public function __construct(?Repository $repository = null)
    {
        $this->repository = $repository ?: new Repository();
    }

    public function suggest(int $projectId, string $kind, array $input, int $userId)
    {
        $kind = sanitize_key($kind);
        $schema = $this->schemaForKind($kind);
        if ($schema === null) {
            return new WP_Error('ouinpo_projects_ai_bad_kind', 'Type de suggestion IA invalide.', ['status' => 400]);
        }

        $project = $this->repository->getProject($projectId);
        if (!$project) {
            return new WP_Error('ouinpo_projects_not_found', 'Projet introuvable.', ['status' => 404]);
        }

        $ready = $this->ensureAiReady($userId);
        if (is_wp_error($ready)) {
            $this->log($projectId, $kind, false, 'none', 0, $ready->get_error_code());
            return $ready;
        }

        $context = $this->buildContext($projectId);
        $teacherContext = $this->cleanString($input['teacher_context'] ?? '', 1200);
        if ($teacherContext !== '') {
            $context['teacher_context'] = $teacherContext;
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->userPrompt($kind, $schema, $context)],
        ];

        $provider = $this->providerLabel();
        $raw = (string) \OuInPo\SegFault\OpenAI::respond($messages, [
            'temperature' => 0.2,
            'max_tokens' => max(1600, AiSettings::maxTokens('ouinpo_ai_practical_ai_max_tokens')),
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ]);

        $decoded = self::parseJsonObject($raw);
        if (is_wp_error($decoded)) {
            $this->log($projectId, $kind, false, $provider, strlen($raw), $decoded->get_error_code());
            return $decoded;
        }

        $validated = $this->validateSuggestion($kind, $decoded, $context, false);
        if (is_wp_error($validated)) {
            $this->log($projectId, $kind, false, $provider, strlen($raw), $validated->get_error_code());
            return $validated;
        }

        $this->log($projectId, $kind, true, $provider, strlen($raw), 'ok');

        return $validated + [
            'kind' => $kind,
            'project_id' => $projectId,
            'ai_notice' => 'Proposition IA a relire et valider par un enseignant avant application.',
        ];
    }

    public function applySuggestion(int $projectId, array $payload, int $userId)
    {
        $kind = sanitize_key((string) ($payload['kind'] ?? ''));
        $items = is_array($payload['items'] ?? null) ? (array) $payload['items'] : [];
        if (!$items) {
            return new WP_Error('ouinpo_projects_ai_empty_apply', 'Aucune proposition selectionnee.', ['status' => 400]);
        }

        $project = $this->repository->getProject($projectId);
        if (!$project) {
            return new WP_Error('ouinpo_projects_not_found', 'Projet introuvable.', ['status' => 404]);
        }

        $context = $this->buildContext($projectId);
        if (!in_array($kind, ['suggest_tasks', 'suggest_deliverables', 'suggest_competencies'], true)) {
            return new WP_Error('ouinpo_projects_ai_apply_kind', 'Ce type de proposition ne peut pas etre applique automatiquement.', ['status' => 400]);
        }

        $validated = $this->validateSuggestion($kind, $this->wrapItemsForKind($kind, $items), $context, true);
        if (is_wp_error($validated)) {
            if ($validated->get_error_code() === 'ouinpo_projects_ai_schema_invalid') {
                $validated->add_data(['status' => 400]);
            }
            return $validated;
        }

        if ($kind === 'suggest_tasks') {
            return $this->applyTasks($projectId, (array) ($validated['tasks'] ?? []), $userId);
        }

        if ($kind === 'suggest_deliverables') {
            return $this->applyDeliverables($projectId, (array) ($validated['deliverables'] ?? []), $userId);
        }

        if ($kind === 'suggest_competencies') {
            return $this->applyCompetencies($projectId, (array) ($validated['competency_links'] ?? []), $userId);
        }

        return new WP_Error('ouinpo_projects_ai_apply_kind', 'Ce type de proposition ne peut pas etre applique automatiquement.', ['status' => 400]);
    }

    private function ensureAiReady(int $userId)
    {
        if (!AiSettings::enabled_for_usage(self::USAGE)) {
            return new WP_Error('ouinpo_projects_ai_disabled', 'Usage IA pedagogique desactive.', ['status' => 403]);
        }

        $this->loadAiBridge();
        if (!class_exists(self::AI_CLASS) || !method_exists(self::AI_CLASS, 'respond')) {
            return new WP_Error('ouinpo_projects_ai_unavailable', 'Moteur IA indisponible.', ['status' => 503]);
        }

        return AiSettings::consumeUserRateLimit(
            self::QUOTA_SCOPE,
            $userId,
            AiSettings::quota('ouinpo_ai_teacher_per_minute'),
            AiSettings::quota('ouinpo_ai_teacher_per_day')
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

    private function buildContext(int $projectId): array
    {
        $project = $this->repository->getProjectSummary($projectId) ?: [];
        $board = $this->repository->getBoard($projectId);
        $deliverables = $this->repository->getDeliverables($projectId);
        $evidence = $this->repository->getEvidence($projectId);
        $logs = array_slice($this->repository->getLogs($projectId), 0, self::MAX_AI_CONTEXT_LOGS);
        $members = $this->repository->getMembers($projectId);
        $competencies = $this->repository->getAvailableCompetencies(500);
        $links = $this->repository->getCompetencyLinks($projectId);

        return [
            'project' => [
                'id' => (int) $projectId,
                'title' => $this->cleanString($project['title'] ?? '', 190),
                'description' => $this->cleanString(wp_strip_all_tags((string) ($project['description'] ?? '')), self::MAX_AI_TEXT_LENGTH),
                'level' => $this->cleanString($project['level'] ?? '', 100),
                'class_slug' => sanitize_key((string) ($project['class_slug'] ?? '')),
                'status' => sanitize_key((string) ($project['status'] ?? '')),
                'start_date' => $this->cleanDateValue($project['start_date'] ?? ''),
                'end_date' => $this->cleanDateValue($project['end_date'] ?? ''),
                'counts' => [
                    'members' => (int) ($project['members_count'] ?? 0),
                    'tasks' => (int) ($project['tasks_count'] ?? 0),
                    'open_tasks' => (int) ($project['open_tasks_count'] ?? 0),
                    'done_tasks' => (int) ($project['done_tasks_count'] ?? 0),
                    'deliverables' => (int) ($project['deliverables_count'] ?? 0),
                    'validated_deliverables' => (int) ($project['validated_deliverables_count'] ?? 0),
                ],
            ],
            'member_roles' => $this->memberRoleCounts($members),
            'columns' => $this->columnsContext($board),
            'tasks' => $this->tasksContext($board),
            'deliverables' => $this->deliverablesContext($deliverables),
            'evidence_metadata' => $this->evidenceContext($evidence),
            'logs' => $this->logsContext($logs),
            'available_competencies' => $this->competenciesContext($competencies),
            'existing_competency_links' => $this->linksContext($links),
        ];
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            AiSettings::persona('projects_teacher', 'ouinpo_ai_persona_teacher'),
            'Tache metier : produire une aide encadree pour projets BTS SIO.',
            'Tu reponds en francais professionnel, concis, et uniquement en JSON valide.',
            'Tu ne modifies jamais un projet. Tu fournis des brouillons a valider par un enseignant.',
            'Tu ne dois pas inventer de traces, de livrables, de taches terminees ni de competences.',
            'Tu distingues clairement observe, suggere et a verifier.',
            'Tu ne demandes pas a lire le contenu des fichiers et tu n inferes pas leur contenu depuis leur nom.',
            'Tu utilises uniquement les identifiants de competences fournis dans le contexte.',
            'Aucun Markdown, aucune explication hors JSON.',
        ]);
    }

    private function userPrompt(string $kind, array $schema, array $context): string
    {
        return "Type de demande: {$kind}\n"
            . "Schema JSON obligatoire:\n"
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\nContexte projet autorise:\n"
            . wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function schemaForKind(string $kind): ?array
    {
        $schemas = [
            'suggest_tasks' => [
                'tasks' => [[
                    'title' => 'string',
                    'description' => 'string',
                    'column_status_key' => 'string parmi columns.status_key',
                    'priority' => 'low|normal|high|urgent',
                    'due_hint' => 'YYYY-MM-DD ou texte court ou null',
                    'competency_slugs' => ['slug existant optionnel'],
                    'checklist' => ['string'],
                    'reason' => 'string',
                ]],
                'warnings' => ['string'],
            ],
            'suggest_deliverables' => [
                'deliverables' => [[
                    'title' => 'string',
                    'description' => 'string',
                    'type' => 'type existant',
                    'due_hint' => 'YYYY-MM-DD ou texte court ou null',
                    'reason' => 'string',
                ]],
                'warnings' => ['string'],
            ],
            'suggest_competencies' => [
                'competency_links' => [[
                    'object_type' => 'project|task|deliverable',
                    'object_id' => 'id existant',
                    'competency_id' => 'id existant',
                    'reason' => 'string',
                    'confidence' => 'observed|suggested|to_verify',
                ]],
                'warnings' => ['string'],
            ],
            'analyze_risks' => [
                'risks' => [[
                    'level' => 'low|medium|high',
                    'title' => 'string',
                    'explanation' => 'string',
                    'suggested_action' => 'string',
                    'evidence_basis' => 'observed|suggested|to_verify',
                ]],
                'positive_points' => ['string'],
                'warnings' => ['string'],
            ],
            'portfolio_summary' => [
                'context' => 'string',
                'work_done' => ['string'],
                'skills_shown' => ['string'],
                'evidence_to_mention' => ['string'],
                'questions_for_student' => ['string'],
                'warnings' => ['string'],
            ],
            'teacher_summary' => [
                'project_status' => 'string',
                'recent_activity' => ['string'],
                'strengths' => ['string'],
                'concerns' => ['string'],
                'questions_to_ask' => ['string'],
                'next_actions' => ['string'],
                'warnings' => ['string'],
            ],
        ];

        return $schemas[$kind] ?? null;
    }

    private function validateSuggestion(string $kind, array $data, array $context, bool $strict = false)
    {
        if ($kind === 'suggest_tasks') {
            return $this->validateTasks($data, $context, $strict);
        }

        if ($kind === 'suggest_deliverables') {
            return $this->validateDeliverables($data, $context, $strict);
        }

        if ($kind === 'suggest_competencies') {
            return $this->validateCompetencies($data, $context, $strict);
        }

        if ($kind === 'analyze_risks') {
            return $this->validateRisks($data);
        }

        if ($kind === 'portfolio_summary' || $kind === 'teacher_summary') {
            return $this->validateSummary($data, $kind);
        }

        return new WP_Error('ouinpo_projects_ai_bad_kind', 'Type de suggestion IA invalide.', ['status' => 400]);
    }

    private function validateTasks(array $data, array $context, bool $strict)
    {
        $knownStatuses = array_column((array) ($context['columns'] ?? []), 'status_key');
        $knownSlugs = array_column((array) ($context['available_competencies'] ?? []), 'slug');
        $rawTasks = $this->requiredArrayField($data, 'tasks');
        if (is_wp_error($rawTasks)) {
            return $rawTasks;
        }

        $tasks = [];
        $warnings = $this->cleanStringList($data['warnings'] ?? [], 12, 220);
        if (count($rawTasks) > self::MAX_AI_TASKS) {
            $warnings[] = 'Nombre de taches IA limite a ' . self::MAX_AI_TASKS . '.';
        }

        foreach (array_slice($rawTasks, 0, self::MAX_AI_TASKS) as $index => $item) {
            if (!is_array($item)) {
                if ($strict) {
                    return $this->schemaError('Tache IA invalide a la position ' . ($index + 1) . '.');
                }
                $warnings[] = 'Tache IA invalide ignoree.';
                continue;
            }
            $title = $this->cleanString($item['title'] ?? '', 190);
            if ($title === '') {
                if ($strict) {
                    return $this->schemaError('Tache IA sans titre refusee.');
                }
                $warnings[] = 'Tache IA sans titre ignoree.';
                continue;
            }
            $statusKey = sanitize_key((string) ($item['column_status_key'] ?? ''));
            if ($statusKey === '' || !in_array($statusKey, $knownStatuses, true)) {
                if ($strict) {
                    return $this->schemaError('Colonne cible invalide pour la tache "' . $title . '".');
                }
                $statusKey = (string) ($knownStatuses[0] ?? '');
            }
            $priority = sanitize_key((string) ($item['priority'] ?? 'normal'));
            if (!in_array($priority, Repository::PRIORITIES, true)) {
                if ($strict) {
                    return $this->schemaError('Priorite invalide pour la tache "' . $title . '".');
                }
                $priority = 'normal';
            }
            if (array_key_exists('checklist', $item) && !is_array($item['checklist'])) {
                if ($strict) {
                    return $this->schemaError('Checklist invalide pour la tache "' . $title . '".');
                }
                $warnings[] = 'Checklist invalide ignoree pour "' . $title . '".';
            }
            if (array_key_exists('competency_slugs', $item) && !is_array($item['competency_slugs'])) {
                if ($strict) {
                    return $this->schemaError('Liste de competences invalide pour la tache "' . $title . '".');
                }
                $warnings[] = 'Competences invalides ignorees pour "' . $title . '".';
            }
            $slugs = array_values(array_intersect($this->cleanSlugList($item['competency_slugs'] ?? []), $knownSlugs));
            $tasks[] = [
                'title' => $title,
                'description' => $this->cleanString($item['description'] ?? '', self::MAX_AI_TEXT_LENGTH),
                'column_status_key' => $statusKey,
                'priority' => $priority,
                'due_hint' => $this->cleanString($item['due_hint'] ?? '', 120),
                'competency_slugs' => $slugs,
                'checklist' => $this->cleanStringList($item['checklist'] ?? [], 10, 180),
                'reason' => $this->cleanString($item['reason'] ?? '', 500),
            ];
        }

        if (!$tasks) {
            return $this->schemaError('Aucune tache IA valide.');
        }

        return ['tasks' => $tasks, 'warnings' => array_values(array_unique($warnings))];
    }

    private function validateDeliverables(array $data, array $context, bool $strict)
    {
        $existingTitles = [];
        foreach ((array) ($context['deliverables'] ?? []) as $deliverable) {
            $existingTitles[] = $this->normalTitle((string) ($deliverable['title'] ?? ''));
        }

        $rawDeliverables = $this->requiredArrayField($data, 'deliverables');
        if (is_wp_error($rawDeliverables)) {
            return $rawDeliverables;
        }

        $deliverables = [];
        $warnings = $this->cleanStringList($data['warnings'] ?? [], 12, 220);
        $seen = [];
        if (count($rawDeliverables) > self::MAX_AI_DELIVERABLES) {
            $warnings[] = 'Nombre de livrables IA limite a ' . self::MAX_AI_DELIVERABLES . '.';
        }

        foreach (array_slice($rawDeliverables, 0, self::MAX_AI_DELIVERABLES) as $index => $item) {
            if (!is_array($item)) {
                if ($strict) {
                    return $this->schemaError('Livrable IA invalide a la position ' . ($index + 1) . '.');
                }
                $warnings[] = 'Livrable IA invalide ignore.';
                continue;
            }
            $title = $this->cleanString($item['title'] ?? '', 190);
            if ($title === '') {
                if ($strict) {
                    return $this->schemaError('Livrable IA sans titre refuse.');
                }
                $warnings[] = 'Livrable IA sans titre ignore.';
                continue;
            }
            $normalTitle = $this->normalTitle($title);
            if (in_array($normalTitle, $existingTitles, true)) {
                if ($strict) {
                    return new WP_Error('ouinpo_projects_ai_duplicate', 'Livrable deja existant : ' . $title, ['status' => 409]);
                }
                $warnings[] = 'Livrable deja existant ignore : ' . $title;
                continue;
            }
            if (in_array($normalTitle, $seen, true)) {
                if ($strict) {
                    return new WP_Error('ouinpo_projects_ai_duplicate', 'Livrable duplique dans la selection : ' . $title, ['status' => 409]);
                }
                $warnings[] = 'Livrable duplique ignore : ' . $title;
                continue;
            }
            $type = sanitize_key((string) ($item['type'] ?? 'other'));
            if (!in_array($type, Repository::DELIVERABLE_TYPES, true)) {
                if ($strict) {
                    return $this->schemaError('Type de livrable invalide pour "' . $title . '".');
                }
                $warnings[] = 'Type invalide ignore pour le livrable "' . $title . '".';
                continue;
            }
            $seen[] = $normalTitle;
            $deliverables[] = [
                'title' => $title,
                'description' => $this->cleanString($item['description'] ?? '', self::MAX_AI_TEXT_LENGTH),
                'type' => $type,
                'due_hint' => $this->cleanString($item['due_hint'] ?? '', 120),
                'reason' => $this->cleanString($item['reason'] ?? '', 500),
            ];
        }

        if (!$deliverables) {
            return $this->schemaError('Aucun livrable IA valide.');
        }

        return ['deliverables' => $deliverables, 'warnings' => array_values(array_unique($warnings))];
    }

    private function validateCompetencies(array $data, array $context, bool $strict)
    {
        $projectId = (int) ($context['project']['id'] ?? 0);
        $knownCompetencies = array_map('intval', array_column((array) ($context['available_competencies'] ?? []), 'id'));
        $existing = [];
        foreach ((array) ($context['existing_competency_links'] ?? []) as $link) {
            $existing[] = (string) ($link['object_type'] ?? '') . ':' . (int) ($link['object_id'] ?? 0) . ':' . (int) ($link['competency_id'] ?? 0);
        }

        $rawLinks = $this->requiredArrayField($data, 'competency_links');
        if (is_wp_error($rawLinks)) {
            return $rawLinks;
        }

        $links = [];
        $warnings = $this->cleanStringList($data['warnings'] ?? [], 12, 220);
        $seen = [];
        if (count($rawLinks) > self::MAX_AI_COMPETENCY_LINKS) {
            $warnings[] = 'Nombre de liens competence IA limite a ' . self::MAX_AI_COMPETENCY_LINKS . '.';
        }

        foreach (array_slice($rawLinks, 0, self::MAX_AI_COMPETENCY_LINKS) as $index => $item) {
            if (!is_array($item)) {
                if ($strict) {
                    return $this->schemaError('Lien competence IA invalide a la position ' . ($index + 1) . '.');
                }
                continue;
            }
            $objectType = sanitize_key((string) ($item['object_type'] ?? ''));
            if (!in_array($objectType, ['project', 'task', 'deliverable'], true)) {
                if ($strict) {
                    return $this->schemaError('Type d objet competence invalide.');
                }
                continue;
            }
            $objectId = absint($item['object_id'] ?? 0);
            $competencyId = absint($item['competency_id'] ?? 0);
            $key = "{$objectType}:{$objectId}:{$competencyId}";
            if (!in_array($competencyId, $knownCompetencies, true)) {
                if ($strict) {
                    return new WP_Error('ouinpo_projects_ai_competency_invalid', 'Competence IA inconnue ou inactive.', ['status' => 400]);
                }
                $warnings[] = 'Competence inconnue ignoree.';
                continue;
            }
            if (in_array($key, $existing, true) || in_array($key, $seen, true)) {
                if ($strict) {
                    return new WP_Error('ouinpo_projects_ai_duplicate', 'Lien competence deja existant ou duplique.', ['status' => 409]);
                }
                continue;
            }
            if (!$this->repository->objectBelongsToProject($projectId, $objectType, $objectId)) {
                if ($strict) {
                    return new WP_Error('ouinpo_projects_ai_object_invalid', 'Objet cible absent ou rattache a un autre projet.', ['status' => 400]);
                }
                $warnings[] = 'Objet cible invalide ignore.';
                continue;
            }
            $confidence = sanitize_key((string) ($item['confidence'] ?? 'suggested'));
            if (!in_array($confidence, ['observed', 'suggested', 'to_verify'], true)) {
                if ($strict) {
                    return $this->schemaError('Niveau de confiance competence invalide.');
                }
                $confidence = 'suggested';
            }
            $seen[] = $key;
            $links[] = [
                'object_type' => $objectType,
                'object_id' => $objectId,
                'competency_id' => $competencyId,
                'reason' => $this->cleanString($item['reason'] ?? '', 500),
                'confidence' => $confidence,
            ];
        }

        if (!$links) {
            return $this->schemaError('Aucun lien competence IA valide.');
        }

        return ['competency_links' => $links, 'warnings' => $warnings];
    }

    private function validateRisks(array $data)
    {
        $rawRisks = $this->requiredArrayField($data, 'risks');
        if (is_wp_error($rawRisks)) {
            return $rawRisks;
        }

        $risks = [];
        foreach (array_slice($rawRisks, 0, self::MAX_AI_RISKS) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $level = sanitize_key((string) ($item['level'] ?? 'medium'));
            if (!in_array($level, ['low', 'medium', 'high'], true)) {
                $level = 'medium';
            }
            $title = $this->cleanString($item['title'] ?? '', 190);
            $explanation = $this->cleanString($item['explanation'] ?? '', 800);
            if ($title === '' || $explanation === '') {
                continue;
            }
            $risks[] = [
                'level' => $level,
                'title' => $title,
                'explanation' => $explanation,
                'suggested_action' => $this->cleanString($item['suggested_action'] ?? '', 800),
                'evidence_basis' => $this->cleanBasis($item['evidence_basis'] ?? 'suggested'),
            ];
        }

        if (!$risks) {
            return $this->schemaError('Aucun risque IA valide.');
        }

        return [
            'risks' => $risks,
            'positive_points' => $this->cleanStringList($data['positive_points'] ?? [], 10, 300),
            'warnings' => $this->cleanStringList($data['warnings'] ?? [], 10, 300),
        ];
    }

    private function validateSummary(array $data, string $kind)
    {
        $keys = $kind === 'portfolio_summary'
            ? ['context', 'work_done', 'skills_shown', 'evidence_to_mention', 'questions_for_student', 'warnings']
            : ['project_status', 'recent_activity', 'strengths', 'concerns', 'questions_to_ask', 'next_actions', 'warnings'];

        $out = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                return $this->schemaError('Champ IA manquant : ' . $key . '.');
            }
            if (($key === 'context' || $key === 'project_status') && !is_scalar($data[$key])) {
                return $this->schemaError('Champ IA texte invalide : ' . $key . '.');
            }
            if ($key !== 'context' && $key !== 'project_status' && !is_array($data[$key])) {
                return $this->schemaError('Champ IA liste invalide : ' . $key . '.');
            }
            $out[$key] = $key === 'context' || $key === 'project_status'
                ? $this->cleanString($data[$key] ?? '', 1200)
                : $this->cleanStringList($data[$key] ?? [], 12, 500);
        }

        return $out;
    }

    private function requiredArrayField(array $data, string $key)
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            return $this->schemaError('Schema IA invalide : champ liste requis "' . $key . '".');
        }

        return array_values($data[$key]);
    }

    private function schemaError(string $message): WP_Error
    {
        return new WP_Error('ouinpo_projects_ai_schema_invalid', $message, ['status' => 502]);
    }

    private function applyTasks(int $projectId, array $tasks, int $userId)
    {
        if (!$tasks) {
            return new WP_Error('ouinpo_projects_ai_nothing_to_apply', 'Aucune tache IA valide a appliquer.', ['status' => 400]);
        }

        $existing = $this->existingTaskTitles($projectId);
        $seen = [];
        foreach ($tasks as $task) {
            $title = (string) ($task['title'] ?? '');
            $normalTitle = $this->normalTitle($title);
            if ($title === '' || $normalTitle === '') {
                return $this->schemaError('Tache IA sans titre refusee.');
            }
            if (in_array($normalTitle, $existing, true)) {
                return new WP_Error('ouinpo_projects_ai_duplicate', 'Tache deja existante : ' . $title, ['status' => 409]);
            }
            if (in_array($normalTitle, $seen, true)) {
                return new WP_Error('ouinpo_projects_ai_duplicate', 'Tache dupliquee dans la selection : ' . $title, ['status' => 409]);
            }
            $seen[] = $normalTitle;
        }

        $applied = [];
        foreach ($tasks as $task) {
            $title = (string) ($task['title'] ?? '');

            $description = (string) ($task['description'] ?? '');
            $dueDate = '';
            $dueHint = (string) ($task['due_hint'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueHint)) {
                $dueDate = $dueHint;
            } elseif ($dueHint !== '') {
                $description = trim($description . "\n\nEcheance suggeree : " . $dueHint);
            }

            $taskId = $this->repository->createTask($projectId, [
                'title' => $title,
                'description' => $description,
                'priority' => (string) ($task['priority'] ?? 'normal'),
                'due_date' => $dueDate,
                'column_id' => $this->repository->getColumnIdForStatusKey($projectId, (string) ($task['column_status_key'] ?? '')),
            ], $userId);

            if ($taskId <= 0) {
                return new WP_Error('ouinpo_projects_ai_apply_failed', 'Creation de tache impossible : ' . $title, ['status' => 500]);
            }

            foreach ((array) ($task['checklist'] ?? []) as $label) {
                $this->repository->addChecklistItem($taskId, (string) $label);
            }

            foreach ($this->competencyIdsFromSlugs((array) ($task['competency_slugs'] ?? [])) as $competencyId) {
                $this->repository->addCompetencyLink($projectId, 'task', $taskId, $competencyId, $userId);
            }

            $existing[] = $this->normalTitle($title);
            $applied[] = ['id' => $taskId, 'title' => $title];
        }

        return ['applied' => ['tasks' => $applied], 'skipped' => []];
    }

    private function applyDeliverables(int $projectId, array $deliverables, int $userId)
    {
        if (!$deliverables) {
            return new WP_Error('ouinpo_projects_ai_nothing_to_apply', 'Aucun livrable IA valide a appliquer.', ['status' => 400]);
        }

        $existing = [];
        foreach ($this->repository->getDeliverables($projectId) as $deliverable) {
            $existing[] = $this->normalTitle((string) ($deliverable['title'] ?? ''));
        }

        $seen = [];
        foreach ($deliverables as $deliverable) {
            $title = (string) ($deliverable['title'] ?? '');
            $normalTitle = $this->normalTitle($title);
            if ($title === '' || $normalTitle === '') {
                return $this->schemaError('Livrable IA sans titre refuse.');
            }
            if (in_array($normalTitle, $existing, true)) {
                return new WP_Error('ouinpo_projects_ai_duplicate', 'Livrable deja existant : ' . $title, ['status' => 409]);
            }
            if (in_array($normalTitle, $seen, true)) {
                return new WP_Error('ouinpo_projects_ai_duplicate', 'Livrable duplique dans la selection : ' . $title, ['status' => 409]);
            }
            $seen[] = $normalTitle;
        }

        $applied = [];
        foreach ($deliverables as $deliverable) {
            $title = (string) ($deliverable['title'] ?? '');

            $description = (string) ($deliverable['description'] ?? '');
            $dueDate = '';
            $dueHint = (string) ($deliverable['due_hint'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueHint)) {
                $dueDate = $dueHint;
            } elseif ($dueHint !== '') {
                $description = trim($description . "\n\nEcheance suggeree : " . $dueHint);
            }

            $deliverableId = $this->repository->createDeliverable($projectId, [
                'title' => $title,
                'description' => $description,
                'type' => (string) ($deliverable['type'] ?? 'other'),
                'due_date' => $dueDate,
            ], $userId);

            if ($deliverableId <= 0) {
                return new WP_Error('ouinpo_projects_ai_apply_failed', 'Creation de livrable impossible : ' . $title, ['status' => 500]);
            }

            $existing[] = $this->normalTitle($title);
            $applied[] = ['id' => $deliverableId, 'title' => $title];
        }

        return ['applied' => ['deliverables' => $applied], 'skipped' => []];
    }

    private function applyCompetencies(int $projectId, array $links, int $userId)
    {
        if (!$links) {
            return new WP_Error('ouinpo_projects_ai_nothing_to_apply', 'Aucun lien competence IA valide a appliquer.', ['status' => 400]);
        }

        $applied = [];
        foreach ($links as $link) {
            $linkId = $this->repository->addCompetencyLink(
                $projectId,
                (string) ($link['object_type'] ?? ''),
                (int) ($link['object_id'] ?? 0),
                (int) ($link['competency_id'] ?? 0),
                $userId
            );
            if ($linkId <= 0) {
                return new WP_Error('ouinpo_projects_ai_apply_failed', 'Ajout de competence impossible pour l objet selectionne.', ['status' => 500]);
            }
            $applied[] = ['id' => $linkId] + $link;
        }

        return ['applied' => ['competency_links' => $applied], 'skipped' => []];
    }

    private function wrapItemsForKind(string $kind, array $items): array
    {
        if ($kind === 'suggest_tasks') {
            return ['tasks' => $items, 'warnings' => []];
        }
        if ($kind === 'suggest_deliverables') {
            return ['deliverables' => $items, 'warnings' => []];
        }
        if ($kind === 'suggest_competencies') {
            return ['competency_links' => $items, 'warnings' => []];
        }

        return [];
    }

    private function competencyIdsFromSlugs(array $slugs): array
    {
        $ids = [];
        $wanted = $this->cleanSlugList($slugs);
        if (!$wanted) {
            return [];
        }

        foreach ($this->repository->getAvailableCompetencies(500) as $competency) {
            if (in_array((string) ($competency['slug'] ?? ''), $wanted, true)) {
                $ids[] = (int) $competency['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private function existingTaskTitles(int $projectId): array
    {
        $titles = [];
        foreach ($this->repository->getBoard($projectId) as $column) {
            foreach ((array) ($column['tasks'] ?? []) as $task) {
                $normal = $this->normalTitle((string) ($task['title'] ?? ''));
                if ($normal !== '') {
                    $titles[] = $normal;
                }
            }
        }

        return array_values(array_unique($titles));
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
        AiSettings::debug_log('Projects AI request', [
            'user_id' => get_current_user_id(),
            'project_id' => $projectId,
            'action' => $action,
            'date' => current_time('mysql'),
            'success' => $success ? 'yes' : 'no',
            'provider' => $provider,
            'size_bytes' => $sizeBytes,
            'code' => $code,
        ]);
    }

    private static function parseJsonObject(string $raw)
    {
        return JsonResponseParser::parse($raw, 'object', [
            'status' => 502,
            'empty_code' => 'ouinpo_projects_ai_empty_response',
            'empty_message' => 'La reponse IA est vide.',
            'invalid_json_code' => 'ouinpo_projects_ai_invalid_json',
            'invalid_json_message' => 'La reponse IA ne contient pas de JSON valide.',
            'append_json_error_to_message' => false,
            'invalid_root_code' => 'ouinpo_projects_ai_invalid_json_root',
            'invalid_root_object_message' => 'La reponse IA doit etre un objet JSON.',
            'not_found_message' => 'Aucun objet JSON complet trouve dans la reponse IA.',
        ]);
    }

    private function memberRoleCounts(array $members): array
    {
        $counts = [];
        foreach ($members as $member) {
            $role = sanitize_key((string) ($member['role'] ?? 'member'));
            $counts[$role] = ($counts[$role] ?? 0) + 1;
        }

        return $counts;
    }

    private function columnsContext(array $board): array
    {
        return array_map(function (array $column): array {
            return [
                'id' => (int) ($column['id'] ?? 0),
                'title' => $this->cleanString($column['title'] ?? '', 120),
                'status_key' => sanitize_key((string) ($column['status_key'] ?? '')),
            ];
        }, $board);
    }

    private function tasksContext(array $board): array
    {
        $tasks = [];
        foreach ($board as $column) {
            foreach ((array) ($column['tasks'] ?? []) as $task) {
                $tasks[] = [
                    'id' => (int) ($task['id'] ?? 0),
                    'title' => $this->cleanString($task['title'] ?? '', 190),
                    'description' => $this->cleanString(wp_strip_all_tags((string) ($task['description'] ?? '')), 900),
                    'column_status_key' => sanitize_key((string) ($column['status_key'] ?? '')),
                    'priority' => sanitize_key((string) ($task['priority'] ?? 'normal')),
                    'status' => sanitize_key((string) ($task['status'] ?? 'open')),
                    'due_date' => $this->cleanDateValue($task['due_date'] ?? ''),
                    'checklist' => $this->cleanStringList(array_column((array) ($task['checklist'] ?? []), 'label'), 20, 160),
                ];
            }
        }

        return array_slice($tasks, 0, self::MAX_AI_CONTEXT_TASKS);
    }

    private function deliverablesContext(array $deliverables): array
    {
        return array_slice(array_map(function (array $deliverable): array {
            return [
                'id' => (int) ($deliverable['id'] ?? 0),
                'title' => $this->cleanString($deliverable['title'] ?? '', 190),
                'description' => $this->cleanString(wp_strip_all_tags((string) ($deliverable['description'] ?? '')), 700),
                'type' => sanitize_key((string) ($deliverable['type'] ?? '')),
                'status' => sanitize_key((string) ($deliverable['status'] ?? '')),
                'due_date' => $this->cleanDateValue($deliverable['due_date'] ?? ''),
            ];
        }, $deliverables), 0, self::MAX_AI_CONTEXT_DELIVERABLES);
    }

    private function evidenceContext(array $evidence): array
    {
        return array_slice(array_map(function (array $item): array {
            return [
                'id' => (int) ($item['id'] ?? 0),
                'title' => $this->cleanString($item['title'] ?? '', 190),
                'evidence_type' => sanitize_key((string) ($item['evidence_type'] ?? '')),
                'description' => $this->cleanString(wp_strip_all_tags((string) ($item['description'] ?? '')), 500),
                'deliverable_id' => (int) ($item['deliverable_id'] ?? 0),
                'deliverable_title' => $this->cleanString($item['deliverable_title'] ?? '', 190),
                'task_id' => (int) ($item['task_id'] ?? 0),
                'task_title' => $this->cleanString($item['task_title'] ?? '', 190),
                'attachment_type' => $this->cleanString($item['attachment_mime'] ?? '', 100),
                'attachment_size' => (int) ($item['attachment_size'] ?? 0),
                'created_at' => $this->cleanString($item['created_at'] ?? '', 30),
            ];
        }, $evidence), 0, self::MAX_AI_CONTEXT_EVIDENCE);
    }

    private function logsContext(array $logs): array
    {
        return array_map(function (array $log): array {
            return [
                'created_at' => $this->cleanString($log['created_at'] ?? '', 30),
                'work_done' => $this->cleanString(wp_strip_all_tags((string) ($log['work_done'] ?? '')), 800),
                'blockers' => $this->cleanString(wp_strip_all_tags((string) ($log['blockers'] ?? '')), 500),
                'decision_taken' => $this->cleanString(wp_strip_all_tags((string) ($log['decision_taken'] ?? '')), 500),
                'next_step' => $this->cleanString(wp_strip_all_tags((string) ($log['next_step'] ?? '')), 500),
            ];
        }, $logs);
    }

    private function competenciesContext(array $competencies): array
    {
        return array_map(function (array $competency): array {
            return [
                'id' => (int) ($competency['id'] ?? 0),
                'domain' => $this->cleanString($competency['domain'] ?? '', 140),
                'domain_slug' => sanitize_key((string) ($competency['domain_slug'] ?? '')),
                'slug' => sanitize_key((string) ($competency['slug'] ?? '')),
                'competency' => $this->cleanString($competency['competency'] ?? '', 240),
                'label' => $this->cleanString($competency['label'] ?? '', 240),
            ];
        }, $competencies);
    }

    private function linksContext(array $links): array
    {
        return array_map(function (array $link): array {
            return [
                'object_type' => sanitize_key((string) ($link['object_type'] ?? '')),
                'object_id' => (int) ($link['object_id'] ?? 0),
                'competency_id' => (int) ($link['competency_id'] ?? 0),
            ];
        }, $links);
    }

    private function cleanStringList($value, int $limit, int $itemLimit): array
    {
        $items = is_array($value) ? $value : [];
        $out = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $clean = $this->cleanString($item, $itemLimit);
            if ($clean !== '') {
                $out[] = $clean;
            }
        }

        return array_values(array_unique($out));
    }

    private function cleanSlugList($value): array
    {
        $items = is_array($value) ? $value : [];
        $slugs = [];
        foreach ($items as $item) {
            $slug = sanitize_key((string) $item);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    private function cleanString($value, int $limit): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $text = trim(wp_strip_all_tags((string) $value));
        if ($limit > 0 && function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit);
        }

        return $limit > 0 ? substr($text, 0, $limit) : $text;
    }

    private function cleanDateValue($value): string
    {
        $date = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }

    private function cleanBasis($value): string
    {
        $basis = sanitize_key((string) $value);

        return in_array($basis, ['observed', 'suggested', 'to_verify'], true) ? $basis : 'suggested';
    }

    private function normalTitle(string $title): string
    {
        return sanitize_title(remove_accents($title));
    }
}
