#!/usr/bin/env php
<?php

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('OUINPO_SUITE_VERSION', '0.7.2-beta');

$root = dirname(__DIR__);
$checks = [];
$wpOptions = [];

if (!class_exists('WP_Error', false)) {
    class WP_Error
    {
        private string $code;
        private string $message;
        private array $data;

        public function __construct(string $code = '', string $message = '', array $data = [])
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): array
        {
            return $this->data;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($value): bool
    {
        return $value instanceof WP_Error;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text): string
    {
        return strip_tags((string) $text);
    }
}

if (!function_exists('wp_basename')) {
    function wp_basename($path): string
    {
        return basename(str_replace('\\', '/', (string) $path));
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $name) ?? '';
        return trim($name, '-');
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string
    {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false)
    {
        global $wpOptions;

        return array_key_exists((string) $key, $wpOptions) ? $wpOptions[(string) $key] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null): bool
    {
        global $wpOptions;

        $wpOptions[(string) $key] = $value;
        return true;
    }
}

if (!function_exists('wp_check_filetype_and_ext')) {
    function wp_check_filetype_and_ext($tmp_name, $filename, $mimes): array
    {
        $extension = strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));

        return [
            'ext' => $extension,
            'type' => is_array($mimes) && isset($mimes[$extension]) ? $mimes[$extension] : false,
        ];
    }
}

$jsonParser = path('src/Core/Ai/JsonResponseParser.php');
$exercisesWrapper = path('src/Modules/Exercises/plugin/inc/Services/AiJsonResponseParser.php');
$uploadValidator = path('src/Core/Storage/PrivateUploadValidator.php');
$assets = path('src/Core/Assets.php');
$installer = path('src/Core/Installer.php');
$moduleSettings = path('src/Core/ModuleSettings.php');
$projectsRepository = path('src/Modules/Projects/Repository.php');
$projectsRestController = path('src/Modules/Projects/RestController.php');
$projectsShortcodes = path('src/Modules/Projects/Shortcodes.php');
$projectsPrivateFiles = path('src/Modules/Projects/PrivateFiles.php');
$projectsAiAssistant = path('src/Modules/Projects/ProjectsAiAssistant.php');
$projectsStudentAiAssistant = path('src/Modules/Projects/ProjectsStudentAiAssistant.php');
$projectColumnService = path('src/Modules/Projects/ProjectColumnService.php');
$projectCompetencyService = path('src/Modules/Projects/ProjectCompetencyService.php');
$projectBoardService = path('src/Modules/Projects/ProjectBoardService.php');
$projectStatsService = path('src/Modules/Projects/ProjectStatsService.php');
$projectPermissionService = path('src/Modules/Projects/ProjectPermissionService.php');
$projectDeliverableService = path('src/Modules/Projects/ProjectDeliverableService.php');
$projectEvidenceService = path('src/Modules/Projects/ProjectEvidenceService.php');
$projectJournalService = path('src/Modules/Projects/ProjectJournalService.php');
$projectChecklistService = path('src/Modules/Projects/ProjectChecklistService.php');
$projectMemberService = path('src/Modules/Projects/ProjectMemberService.php');
$projectTaskService = path('src/Modules/Projects/ProjectTaskService.php');
$exercisesPublicAssets = path('src/Modules/Exercises/plugin/public/Assets.php');
$exercisesPublicShortcodes = path('src/Modules/Exercises/plugin/public/Shortcodes.php');
$writtenCss = path('assets/css/front/written.css');
$writtenThemeOuinpoCss = path('assets/css/themes/ouinpo/modules/written.css');
$writtenThemeBsioCss = path('assets/css/themes/bsio/modules/written.css');

check('parseur JSON commun present', is_file($jsonParser));
check('wrapper Exercises AiJsonResponseParser conserve', is_file($exercisesWrapper));

if (is_file($jsonParser)) {
    require_once $jsonParser;

    check('JSON brut accepte', parser_accepts('{"ok":true}', 'ok'));
    check('bloc ```json accepte', parser_accepts("```json\n{\"ok\":true}\n```", 'ok'));
    check('bloc ``` simple accepte', parser_accepts("```\n{\"ok\":true}\n```", 'ok'));
    check('texte avant/apres JSON accepte', parser_accepts("Avant\n{\"ok\":true}\nApres", 'ok'));
    check('JSON invalide rejete', is_wp_error(\Ouinpo\Suite\Core\Ai\JsonResponseParser::parse('{bad json', 'object')));
}

check('validateur upload prive present', is_file($uploadValidator));

if (is_file($uploadValidator)) {
    require_once $uploadValidator;

    $uploadArgs = [
        'allowed_mimes' => [
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
        ],
        'blocked_extensions' => [
            'php',
            'phtml',
            'phar',
            'html',
            'js',
            'css',
        ],
        'max_size' => 1024,
    ];

    $dangerous = \Ouinpo\Suite\Core\Storage\PrivateUploadValidator::validateUploadedFile([
        'name' => 'shell.php',
        'tmp_name' => 'tmp/upload',
        'size' => 12,
        'type' => 'text/plain',
        'error' => 0,
    ], $uploadArgs);

    $doubleUnsupported = \Ouinpo\Suite\Core\Storage\PrivateUploadValidator::validateUploadedFile([
        'name' => 'copie.pdf.php',
        'tmp_name' => 'tmp/upload',
        'size' => 12,
        'type' => 'text/plain',
        'error' => 0,
    ], $uploadArgs);

    $doubleDangerous = \Ouinpo\Suite\Core\Storage\PrivateUploadValidator::validateUploadedFile([
        'name' => 'copie.php.pdf',
        'tmp_name' => 'tmp/upload',
        'size' => 12,
        'type' => 'application/pdf',
        'error' => 0,
    ], $uploadArgs);

    check('extension dangereuse simple rejetee', is_wp_error($dangerous));
    check('double extension finale dangereuse rejetee', is_wp_error($doubleUnsupported));
    check('double extension interne dangereuse rejetee', is_wp_error($doubleDangerous));
}

$assetsSource = read_file($assets);
check('Assets expose fileVersion()', $assetsSource !== '' && str_contains($assetsSource, 'public static function fileVersion('));
check('Assets garde un cache de version', $assetsSource !== '' && str_contains($assetsSource, '$versionCache'));
check('Assets garde filemtime() derriere le cache', $assetsSource !== '' && strpos($assetsSource, '$versionCache') < strpos($assetsSource, 'filemtime('));

$installerSource = read_file($installer);
$maybeUpgrade = method_body($installerSource, 'maybeUpgrade');
check('maybeUpgrade retourne avant les migrations si version a jour', $maybeUpgrade !== '' && strpos($maybeUpgrade, 'return;') < strpos($maybeUpgrade, 'ensureProjectsStudentAiSchema('));

$moduleSettingsSource = read_file($moduleSettings);
check('ModuleSettings declare un cache local', $moduleSettingsSource !== '' && str_contains($moduleSettingsSource, '$enabledCache'));
check('ModuleSettings invalide le cache a la sauvegarde', $moduleSettingsSource !== '' && str_contains($moduleSettingsSource, 'self::$enabledCache = null'));
check('ModuleSettings expose availableModules()', $moduleSettingsSource !== '' && str_contains($moduleSettingsSource, 'public static function availableModules('));
check('ModuleSettings normalise via allowlist', $moduleSettingsSource !== '' && str_contains($moduleSettingsSource, 'array_fill_keys(self::availableModules()'));

if (is_file($moduleSettings)) {
    require_once $moduleSettings;

    $expectedModules = [
        'exercises',
        'flashcards',
        'submissions',
        'segfault',
        'gate',
        'rechtext',
        'meta',
        'projects',
    ];
    $availableModules = \Ouinpo\Suite\Core\ModuleSettings::availableModules();
    $availableLookup = array_fill_keys($availableModules, true);
    $bootstrapModules = bootstrap_module_ids(read_file(path('src/Core/Bootstrap.php')));

    check('ModuleSettings liste les modules officiels attendus', all_present($expectedModules, $availableLookup));
    check('ModuleSettings couvre les modules declares dans Bootstrap', $bootstrapModules !== [] && all_present($bootstrapModules, $availableLookup));

    $optionKey = \Ouinpo\Suite\Core\ModuleSettings::OPTION_KEY;
    $wpOptions[$optionKey] = ['exercises', 'badkey', 'flashcards'];
    reset_module_settings_cache();
    check(
        'ModuleSettings ignore badkey a la lecture',
        \Ouinpo\Suite\Core\ModuleSettings::getEnabledModules() === ['exercises', 'flashcards']
    );

    \Ouinpo\Suite\Core\ModuleSettings::saveEnabledModules(['badkey', 'projects', 'projects']);
    check(
        'ModuleSettings ne reecrit pas badkey a la sauvegarde',
        $wpOptions[$optionKey] === ['projects', 'exercises']
    );
}

$repositorySource = read_file($projectsRepository);
$projectColumnSource = read_file($projectColumnService);
$projectCompetencySource = read_file($projectCompetencyService);
$projectBoardSource = read_file($projectBoardService);
$projectStatsSource = read_file($projectStatsService);
$projectPermissionSource = read_file($projectPermissionService);
$projectDeliverableSource = read_file($projectDeliverableService);
$projectEvidenceSource = read_file($projectEvidenceService);
$projectJournalSource = read_file($projectJournalService);
$projectChecklistSource = read_file($projectChecklistService);
$projectMemberSource = read_file($projectMemberService);
$projectTaskSource = read_file($projectTaskService);
$exercisesPublicAssetsSource = read_file($exercisesPublicAssets);
$exercisesPublicShortcodesSource = read_file($exercisesPublicShortcodes);
$projectsRestSource = read_file($projectsRestController);
$projectsShortcodesSource = read_file($projectsShortcodes);
$projectsPrivateFilesSource = read_file($projectsPrivateFiles);
$projectsAiSource = read_file($projectsAiAssistant);
$projectsStudentAiSource = read_file($projectsStudentAiAssistant);
$repositoryBoard = method_body($repositorySource, 'getBoard');
$boardGetBoard = method_body($projectBoardSource, 'getBoard');
$columnDefaultColumns = method_body($projectColumnSource, 'defaultColumns');
$columnEnsureDefaultColumns = method_body($projectColumnSource, 'ensureDefaultColumns');
$columnGetFirstColumnId = method_body($projectColumnSource, 'getFirstColumnId');
$columnGetColumnIdForStatusKey = method_body($projectColumnSource, 'getColumnIdForStatusKey');
$columnBelongsToProject = method_body($projectColumnSource, 'columnBelongsToProject');
$repositoryDefaultColumns = method_body($repositorySource, 'defaultColumns');
$repositoryEnsureDefaultColumns = method_body($repositorySource, 'ensureDefaultColumns');
$repositoryGetFirstColumnId = method_body($repositorySource, 'getFirstColumnId');
$repositoryGetColumnIdForStatusKey = method_body($repositorySource, 'getColumnIdForStatusKey');
$repositoryColumnBelongsToProject = method_body($repositorySource, 'columnBelongsToProject');
$competencyGetLinks = method_body($projectCompetencySource, 'getCompetencyLinks');
$competencyAddLink = method_body($projectCompetencySource, 'addCompetencyLink');
$competencyAvailable = method_body($projectCompetencySource, 'getAvailableCompetencies');
$competencyObjectBelongs = method_body($projectCompetencySource, 'objectBelongsToProject');
$competencyExists = method_body($projectCompetencySource, 'competencyExists');
$repositoryProjectSummary = method_body($repositorySource, 'getProjectSummary');
$statsProjectSummary = method_body($projectStatsSource, 'getProjectSummary');
$repositoryUserCanViewProject = method_body($repositorySource, 'userCanViewProject');
$repositoryUserCanEditTask = method_body($repositorySource, 'userCanEditTask');
$decorateEvidenceAttachment = method_body($projectEvidenceSource, 'decorateEvidenceAttachment');
$storeUploadedFile = method_body($projectsPrivateFilesSource, 'storeUploadedFile');
$absolutePath = method_body($projectsPrivateFilesSource, 'absolutePath');
$downloadEvidence = method_body($projectsRestSource, 'downloadEvidence');
$canDownloadEvidence = method_body($projectsRestSource, 'canDownloadEvidence');
$memberGetMembers = method_body($projectMemberSource, 'getMembers');
$memberAddMember = method_body($projectMemberSource, 'addMember');
$memberRemoveMember = method_body($projectMemberSource, 'removeMember');
$permissionIsProjectMember = method_body($projectPermissionSource, 'isProjectMember');
$repositoryCleanMemberRole = method_body($repositorySource, 'cleanMemberRole');
$taskCreateTask = method_body($projectTaskSource, 'createTask');
$taskUpdateTask = method_body($projectTaskSource, 'updateTask');
$taskMoveTask = method_body($projectTaskSource, 'moveTask');
$taskDeleteTask = method_body($projectTaskSource, 'deleteTask');
$taskGetTask = method_body($projectTaskSource, 'getTask');
$taskGetMainTasks = method_body($projectTaskSource, 'getMainTasks');
$taskNextTaskPosition = method_body($projectTaskSource, 'nextTaskPosition');
$taskBelongsToProject = method_body($projectTaskSource, 'taskBelongsToProject');
check('CSS module sujets ecrits present', is_file($writtenCss));
check('CSS theme OuInPo sujets ecrits present', is_file($writtenThemeOuinpoCss));
check('CSS theme BSIO sujets ecrits present', is_file($writtenThemeBsioCss));
check('Assets enregistre ouinpo-written-css', $exercisesPublicAssetsSource !== '' && str_contains($exercisesPublicAssetsSource, "'ouinpo-written-css'"));
check('Assets enregistre ouinpo-theme-written-css', $exercisesPublicAssetsSource !== '' && str_contains($exercisesPublicAssetsSource, "'ouinpo-theme-written-css'"));
check('ouinpo-written-css depend du module et du theme ecrits', $exercisesPublicAssetsSource !== '' && str_contains($exercisesPublicAssetsSource, "['ouinpo-written-module-css', 'ouinpo-theme-written-css']"));
check('ouinpo-theme-written-css depend du theme global', $exercisesPublicAssetsSource !== '' && str_contains($exercisesPublicAssetsSource, "['ouinpo-theme-css']"));
check('Shortcodes ecrits chargent ouinpo-written-css', $exercisesPublicShortcodesSource !== '' && substr_count($exercisesPublicShortcodesSource, "wp_enqueue_style('ouinpo-written-css')") >= 2);
check('ProjectBoardService existe', is_file($projectBoardService));
check('Repository delegue getBoard au service', $repositoryBoard !== '' && str_contains($repositoryBoard, 'ProjectBoardService'));
check('Projects charge les checklists en groupe', $projectBoardSource !== '' && str_contains($projectBoardSource, 'function getChecklistForTasks('));
check('getBoard utilise le chargement groupe des checklists', $boardGetBoard !== '' && str_contains($boardGetBoard, 'getChecklistForTasks(array_column($tasks, \'id\'))'));
check('Chargement groupe des checklists reste dans ProjectBoardService', $projectBoardSource !== '' && str_contains($projectBoardSource, 'private function getChecklistForTasks('));
check('ProjectStatsService existe', is_file($projectStatsService));
check('Repository delegue getProjectSummary au service', $repositoryProjectSummary !== '' && str_contains($repositoryProjectSummary, 'ProjectStatsService'));
check('getProjectSummary utilise des agregats SQL', $statsProjectSummary !== '' && str_contains($statsProjectSummary, 'SUM(CASE WHEN'));
check('getProjectSummary evite les get_var() de compteurs separes', $statsProjectSummary !== '' && substr_count($statsProjectSummary, 'get_var(') === 0);
check('ProjectDeliverableService existe', is_file($projectDeliverableService));
check('ProjectDeliverableService utilise le namespace Projects', $projectDeliverableSource !== '' && str_contains($projectDeliverableSource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectDeliverableService centralise les methodes livrables', methods_present($projectDeliverableSource, [
    'ensureDefaultDeliverables',
    'getDeliverables',
    'getDeliverable',
    'createDeliverable',
    'updateDeliverable',
    'updateDeliverableStatus',
    'deleteDeliverable',
    'nextDeliverablePosition',
    'deliverableBelongsToProject',
]));
check('Repository conserve les facades livrables Projects', repository_deliverable_facades_delegate($repositorySource));
check('ProjectEvidenceService existe', is_file($projectEvidenceService));
check('ProjectEvidenceService utilise le namespace Projects', $projectEvidenceSource !== '' && str_contains($projectEvidenceSource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectEvidenceService centralise les methodes evidence', methods_present($projectEvidenceSource, [
    'getEvidence',
    'getEvidenceItem',
    'createEvidence',
    'updateEvidence',
    'deleteEvidence',
    'decorateEvidenceAttachment',
]));
check('Repository conserve les facades evidence Projects', repository_evidence_facades_delegate($repositorySource));
check('Evidence privee utilise la route REST protegee', $decorateEvidenceAttachment !== '' && str_contains($decorateEvidenceAttachment, 'PrivateFiles::downloadUrl'));
check('Evidence non privee conserve wp_get_attachment_url()', $decorateEvidenceAttachment !== '' && str_contains($decorateEvidenceAttachment, 'wp_get_attachment_url('));
check('Upload evidence passe par PrivateFiles::storeUploadedFile()', $projectsRestSource !== '' && str_contains($projectsRestSource, 'PrivateFiles::storeUploadedFile($file)'));
check('Validation upload evidence reste dans Repository', $repositorySource !== '' && str_contains($repositorySource, 'PrivateUploadValidator::validateUploadedFile'));
check('PrivateFiles valide les uploads evidence via Repository', $storeUploadedFile !== '' && str_contains($storeUploadedFile, 'Repository::validateEvidenceUploadFile($file)'));
check('PrivateFiles::downloadUrl pointe vers la route evidence protegee', method_contains_all($projectsPrivateFilesSource, 'downloadUrl', [
    'rest_url(\'ouinpo-projects/v1/evidence/\' . $evidenceId . \'/download\')',
    "wp_create_nonce('wp_rest')",
]));
check('PrivateFiles::absolutePath bloque les chemins hors dossier prive', $absolutePath !== '' && source_contains_all($absolutePath, [
    "str_contains(\$relativePath, '../')",
    "!str_starts_with(\$relativePath, 'ouinpo/projects/')",
    'realpath($path)',
    '!str_starts_with($real, $expectedDir)',
]));
check('Route REST de telechargement evidence conservee', source_contains_all($projectsRestSource, [
    "register_rest_route(self::NS, '/evidence/(?P<id>\\d+)/download'",
    "'permission_callback' => [self::class, 'canDownloadEvidence']",
]));
check('Permission de telechargement evidence verifie le projet', $canDownloadEvidence !== '' && source_contains_all($canDownloadEvidence, [
    'getEvidenceItem',
    'userCanViewProject',
    "new WP_Error('ouinpo_projects_forbidden'",
]));
check('Telechargement prive evidence verifie attachment et metas', $downloadEvidence !== '' && source_contains_all($downloadEvidence, [
    'PrivateFiles::isPrivateAttachment',
    'PrivateFiles::META_PROJECT_ID',
    'PrivateFiles::META_EVIDENCE_ID',
    'PrivateFiles::absolutePath',
    'PrivateFiles::sendFile',
]));
check('ProjectJournalService existe', is_file($projectJournalService));
check('ProjectJournalService utilise le namespace Projects', $projectJournalSource !== '' && str_contains($projectJournalSource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectJournalService centralise les methodes journal', methods_present($projectJournalSource, [
    'getComments',
    'addComment',
    'getLogs',
    'addLog',
]));
check('Repository conserve les facades journal Projects', repository_journal_facades_delegate($repositorySource));
check('ProjectJournalService conserve les ordres des commentaires et logs', source_contains_all($projectJournalSource, [
    'ORDER BY c.created_at ASC, c.id ASC',
    'ORDER BY l.created_at DESC, l.id DESC',
]));
check('ProjectChecklistService existe', is_file($projectChecklistService));
check('ProjectChecklistService utilise le namespace Projects', $projectChecklistSource !== '' && str_contains($projectChecklistSource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectChecklistService centralise les methodes checklist', methods_present($projectChecklistSource, [
    'getChecklistForTask',
    'getChecklistItem',
    'addChecklistItem',
    'updateChecklistItem',
    'deleteChecklistItem',
]));
check('Repository conserve les facades checklist Projects', repository_checklist_facades_delegate($repositorySource));
check('ProjectChecklistService conserve l ordre des items', $projectChecklistSource !== '' && str_contains($projectChecklistSource, 'ORDER BY position ASC, id ASC'));
check('ProjectMemberService existe', is_file($projectMemberService));
check('ProjectMemberService utilise le namespace Projects', $projectMemberSource !== '' && str_contains($projectMemberSource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectMemberService centralise les methodes membres', methods_present($projectMemberSource, [
    'getMembers',
    'addMember',
    'removeMember',
]));
check('Repository conserve les facades membres Projects', repository_member_facades_delegate($repositorySource));
check('ProjectMemberService conserve l ordre des membres', $projectMemberSource !== '' && str_contains($projectMemberSource, 'ORDER BY pm.role ASC, u.display_name ASC, pm.user_id ASC'));
check('ProjectMemberService conserve les roles via Repository', $projectMemberSource !== '' && str_contains($projectMemberSource, 'Repository::cleanMemberRole($role)'));
check('ProjectMemberService conserve les colonnes membres', source_contains_all($memberGetMembers, [
    'SELECT pm.*, u.display_name, u.user_email',
    'LEFT JOIN {$wpdb->users} u ON u.ID = pm.user_id',
    'WHERE pm.project_id = %d',
]));
check('ProjectMemberService conserve l upsert membre', source_contains_all($memberAddMember, [
    'Repository::cleanMemberRole($role)',
    'INSERT INTO {$this->repository->table(\'members\')}',
    'ON DUPLICATE KEY UPDATE role = VALUES(role)',
]));
check('ProjectMemberService conserve la suppression par projet et utilisateur', source_contains_all($memberRemoveMember, [
    '$this->repository->table(\'members\')',
    "'project_id' => \$projectId",
    "'user_id' => \$userId",
]));
check('ProjectPermissionService::isProjectMember lit toujours la table members', source_contains_all($permissionIsProjectMember, [
    "SELECT COUNT(*) FROM {\$this->repository->table('members')}",
    'WHERE project_id = %d AND user_id = %d',
]));
check('Repository conserve cleanMemberRole()', $repositoryCleanMemberRole !== '' && source_contains_all($repositoryCleanMemberRole, [
    'self::MEMBER_ROLES',
    "? \$role : 'member'",
]));
check('ProjectTaskService existe', is_file($projectTaskService));
check('ProjectTaskService utilise le namespace Projects', $projectTaskSource !== '' && str_contains($projectTaskSource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectTaskService centralise les methodes taches', methods_present($projectTaskSource, [
    'createTask',
    'updateTask',
    'moveTask',
    'deleteTask',
    'getTask',
    'getMainTasks',
    'nextTaskPosition',
    'taskBelongsToProject',
]));
check('Repository conserve les facades taches Projects', repository_task_facades_delegate($repositorySource));
check('ProjectTaskService utilise les facades colonnes du Repository', source_contains_all($taskCreateTask . $taskMoveTask, [
    '$this->repository->columnBelongsToProject',
    '$this->repository->getFirstColumnId',
]));
check('ProjectTaskService conserve createTask colonne et position', source_contains_all($taskCreateTask, [
    '$columnId = (int) ($data[\'column_id\'] ?? 0)',
    '!$this->repository->columnBelongsToProject($columnId, $projectId)',
    '$columnId = $this->repository->getFirstColumnId($projectId)',
    '$position = $this->nextTaskPosition($columnId)',
]));
check('ProjectTaskService conserve l assignation membre', source_contains_all($taskCreateTask . $taskUpdateTask, [
    'Repository::cleanNullableId',
    '$this->repository->isProjectMember',
    '$assignedUserId = null',
]));
check('ProjectTaskService conserve createTask nettoyages et insertion', source_contains_all($taskCreateTask, [
    'Repository::cleanTitle',
    'Repository::cleanLongText',
    'Repository::cleanPriority',
    'Repository::cleanDate',
    'Repository::cleanTaskStatus',
    '$wpdb->insert',
    '$this->repository->table(\'tasks\')',
]));
check('ProjectTaskService conserve updateTask partiel et updated_at', source_contains_all($taskUpdateTask, [
    'array_key_exists(\'title\', $data)',
    'array_key_exists(\'description\', $data)',
    'array_key_exists(\'assigned_user_id\', $data)',
    'array_key_exists(\'priority\', $data)',
    'array_key_exists(\'due_date\', $data)',
    'array_key_exists(\'status\', $data)',
    '$updates[\'updated_at\'] = current_time(\'mysql\')',
]));
check('ProjectTaskService conserve move sans recalcul global', source_contains_all($taskMoveTask, [
    '$this->repository->columnBelongsToProject',
    "'column_id' => \$columnId",
    "'position' => max(0, \$position)",
    "'updated_at' => current_time('mysql')",
]) && !str_contains($taskMoveTask, 'nextTaskPosition('));
check('ProjectTaskService conserve delete par archivage', source_contains_all($taskDeleteTask, [
    "'status' => 'archived'",
    "'updated_at' => current_time('mysql')",
    '$this->repository->table(\'tasks\')',
]) && !str_contains($taskDeleteTask, 'delete(')
    && !str_contains($taskDeleteTask, "table('checklist")
    && !str_contains($taskDeleteTask, "table('comments")
    && !str_contains($taskDeleteTask, "table('logs"));
check('ProjectTaskService conserve getTask par id', source_contains_all($taskGetTask, [
    'SELECT * FROM {$this->repository->table(\'tasks\')} WHERE id = %d LIMIT 1',
    'return is_array($row) ? $row : null',
]));
check('ProjectTaskService conserve getMainTasks hors archive', source_contains_all($taskGetMainTasks, [
    'max(1, min(50, $limit))',
    "WHERE project_id = %d AND status <> 'archived'",
    'ORDER BY status ASC, priority DESC, due_date ASC, id ASC',
    'LIMIT %d',
]));
check('ProjectTaskService conserve nextTaskPosition()', source_contains_all($taskNextTaskPosition, [
    'COALESCE(MAX(position), 0) + 1',
    '$this->repository->table(\'tasks\')',
    'WHERE column_id = %d',
]));
check('ProjectTaskService conserve taskBelongsToProject()', source_contains_all($taskBelongsToProject, [
    '$task = $this->getTask($taskId)',
    "(int) \$task['project_id'] === \$projectId",
]));
check('ProjectsAiAssistant conserve le placement IA via getColumnIdForStatusKey()', source_contains_all($projectsAiSource, [
    '$this->repository->createTask($projectId',
    '$this->repository->getColumnIdForStatusKey($projectId',
]));
check('ProjectColumnService existe', is_file($projectColumnService));
check('ProjectColumnService utilise le namespace Projects', $projectColumnSource !== '' && str_contains($projectColumnSource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectColumnService centralise les methodes colonnes', methods_present($projectColumnSource, [
    'defaultColumns',
    'ensureDefaultColumns',
    'getFirstColumnId',
    'getColumnIdForStatusKey',
    'columnBelongsToProject',
]));
check('ProjectColumnService conserve les colonnes par defaut', source_contains_all($projectColumnSource, [
    "'status_key' => 'a_cadrer'",
    "'status_key' => 'a_faire'",
    "'status_key' => 'en_cours'",
    "'status_key' => 'a_tester'",
    "'status_key' => 'a_documenter'",
    "'status_key' => 'a_valider'",
    "'status_key' => 'termine'",
]));
check('ProjectColumnService conserve l ordre des status_key par defaut', status_keys_in_order($columnDefaultColumns, [
    'a_cadrer',
    'a_faire',
    'en_cours',
    'a_tester',
    'a_documenter',
    'a_valider',
    'termine',
]));
check('ProjectColumnService protege ensureDefaultColumns contre les doublons', source_contains_all($columnEnsureDefaultColumns, [
    'SELECT COUNT(*)',
    '$exists > 0',
    'return;',
]));
check('ProjectColumnService conserve les champs et formats des colonnes', source_contains_all($columnEnsureDefaultColumns, [
    "'project_id' => \$projectId",
    "'title' => \$column['title']",
    "'position' => \$position + 1",
    "'status_key' => sanitize_key((string) \$column['status_key'])",
    "'created_at' => \$now",
    "['%d', '%s', '%d', '%s', '%s']",
]));
check('ProjectColumnService getFirstColumnId conserve l initialisation et l ordre', source_contains_all($columnGetFirstColumnId, [
    '$this->ensureDefaultColumns($projectId)',
    'ORDER BY position ASC, id ASC LIMIT 1',
]));
check('ProjectColumnService getColumnIdForStatusKey nettoie et fallback', source_contains_all($columnGetColumnIdForStatusKey, [
    '$this->ensureDefaultColumns($projectId)',
    '$statusKey = sanitize_key($statusKey)',
    'AND status_key = %s',
    'ORDER BY position ASC, id ASC LIMIT 1',
    'return $this->getFirstColumnId($projectId);',
    'return $columnId > 0 ? $columnId : $this->getFirstColumnId($projectId);',
]));
check('ProjectColumnService columnBelongsToProject verifie id et project_id', source_contains_all($columnBelongsToProject, [
    'WHERE id = %d AND project_id = %d',
    '$columnId',
    '$projectId',
    '> 0',
]));
check('ProjectColumnService conserve les fallbacks colonnes', source_contains_all($projectColumnSource, [
    '$this->ensureDefaultColumns($projectId)',
    'return $this->getFirstColumnId($projectId);',
    'return $columnId > 0 ? $columnId : $this->getFirstColumnId($projectId);',
]));
check('Repository conserve les facades colonnes Projects', source_contains_all($repositorySource, [
    'function ensureDefaultColumns(',
    'function getFirstColumnId(',
    'function getColumnIdForStatusKey(',
    'function columnBelongsToProject(',
    'function columns(): ProjectColumnService',
    'ProjectColumnService::defaultColumns()',
    '$this->columns()->ensureDefaultColumns($projectId)',
    '$this->columns()->getFirstColumnId($projectId)',
    '$this->columns()->getColumnIdForStatusKey($projectId, $statusKey)',
    '$this->columns()->columnBelongsToProject($columnId, $projectId)',
]) && !str_contains($projectTaskSource, 'function ensureDefaultColumns(')
    && !str_contains($projectTaskSource, 'function getFirstColumnId(')
    && !str_contains($projectTaskSource, 'function getColumnIdForStatusKey(')
    && !str_contains($projectTaskSource, 'function columnBelongsToProject('));
check('Repository delegue chaque facade colonne a columns()', source_contains_all($repositoryDefaultColumns, [
    'ProjectColumnService::defaultColumns()',
]) && source_contains_all($repositoryEnsureDefaultColumns, [
    '$this->columns()->ensureDefaultColumns($projectId)',
]) && source_contains_all($repositoryGetFirstColumnId, [
    '$this->columns()->getFirstColumnId($projectId)',
]) && source_contains_all($repositoryGetColumnIdForStatusKey, [
    '$this->columns()->getColumnIdForStatusKey($projectId, $statusKey)',
]) && source_contains_all($repositoryColumnBelongsToProject, [
    '$this->columns()->columnBelongsToProject($columnId, $projectId)',
]));
check('ProjectBoardService utilise toujours la facade ensureDefaultColumns()', source_contains_all($boardGetBoard, [
    '$this->repository->ensureDefaultColumns($projectId)',
]));
check('ProjectTaskService utilise toujours les facades colonnes Repository', source_contains_all($taskCreateTask . $taskMoveTask, [
    '$this->repository->columnBelongsToProject',
    '$this->repository->getFirstColumnId',
]));
check('RestController moveTask verifie toujours columnBelongsToProject()', method_contains_all($projectsRestSource, 'moveTask', [
    '$repository->columnBelongsToProject($columnId',
    "(int) \$task['project_id']",
]));
check('ProjectCompetencyService existe', is_file($projectCompetencyService));
check('ProjectCompetencyService utilise le namespace Projects', $projectCompetencySource !== '' && str_contains($projectCompetencySource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectCompetencyService centralise les methodes competences', methods_present($projectCompetencySource, [
    'getCompetencyLinks',
    'getCompetencyLink',
    'addCompetencyLink',
    'deleteCompetencyLink',
    'getAvailableCompetencies',
    'objectBelongsToProject',
    'competencyExists',
]));
check('ProjectCompetencyService conserve la jointure optionnelle competences', source_contains_all($competencyGetLinks, [
    "\$wpdb->prefix . 'ouin_exo_competencies'",
    '$this->repository->tableExists($competencies)',
    "LEFT JOIN {\$competencies} c ON c.id = l.competency_id",
    "'' AS domain, '' AS domain_slug, '' AS competency, '' AS label",
]));
check('ProjectCompetencyService conserve filtres et tri des liens', source_contains_all($competencyGetLinks, [
    'l.project_id = %d',
    'l.object_type = %s',
    'l.object_id = %d',
    "FROM {\$this->repository->table('competency_links')} l",
    'ORDER BY l.object_type ASC, l.object_id ASC, l.id ASC',
]));
check('ProjectCompetencyService conserve INSERT IGNORE et lookup historique', source_contains_all($competencyAddLink, [
    'INSERT IGNORE INTO',
    '(project_id, object_type, object_id, competency_id, created_by, created_at)',
    'VALUES (%d, %s, %d, %d, %d, %s)',
    "WHERE object_type = %s AND object_id = %d AND competency_id = %d",
]) && competency_post_insert_lookup_omits_project_id($competencyAddLink));
check('ProjectCompetencyService conserve les validations avant ajout', source_contains_all($competencyAddLink, [
    'Repository::cleanCompetencyObjectType($objectType)',
    '$projectId <= 0',
    '$objectId <= 0',
    '$competencyId <= 0',
    '$userId <= 0',
    '!$this->objectBelongsToProject($projectId, $objectType, $objectId)',
    '!$this->competencyExists($competencyId)',
]));
check('ProjectCompetencyService conserve la liste active et limite bornee', source_contains_all($competencyAvailable, [
    "\$wpdb->prefix . 'ouin_exo_competencies'",
    '$this->repository->tableExists($table)',
    '$limit = max(1, min(1000, $limit))',
    'WHERE active = %d OR active IS NULL',
    'ORDER BY domain ASC, id ASC',
]));
check('ProjectCompetencyService objectBelongsToProject couvre les objets attendus', source_contains_all($competencyObjectBelongs, [
    "Repository::cleanCompetencyObjectType(\$objectType)",
    "\$objectType === 'project'",
    "\$objectType === 'task'",
    "\$objectType === 'deliverable'",
    "\$objectType === 'evidence'",
    '$this->repository->getProject($projectId)',
    '$this->repository->getTask($objectId)',
    '$this->repository->getDeliverable($objectId)',
    '$this->repository->getEvidenceItem($objectId)',
]));
check('ProjectCompetencyService competencyExists conserve le referentiel BO', source_contains_all($competencyExists, [
    "\$wpdb->prefix . 'ouin_exo_competencies'",
    '$this->repository->tableExists($table)',
    'SELECT COUNT(*) FROM {$table} WHERE id = %d',
]));
check('Repository conserve les facades competences Projects', repository_competency_facades_delegate($repositorySource));
check('Repository expose tableExists pour les services Projects', method_body($repositorySource, 'tableExists') !== '' && str_contains($repositorySource, 'public function tableExists(string $table): bool'));
check('RestController et IA utilisent toujours les facades competences Repository', source_contains_all($projectsRestSource . $projectsAiSource, [
    'getCompetencyLinks',
    'addCompetencyLink',
    'getAvailableCompetencies',
    'objectBelongsToProject',
]));
check('ProjectPermissionService existe', is_file($projectPermissionService));
check('ProjectPermissionService utilise le namespace Projects', $projectPermissionSource !== '' && str_contains($projectPermissionSource, 'namespace Ouinpo\\Suite\\Modules\\Projects;'));
check('ProjectPermissionService centralise les regles principales', methods_present($projectPermissionSource, [
    'isProjectMember',
    'canManageAllProjects',
    'canCreateOrManageProjects',
    'canViewProject',
    'canManageProject',
    'canEditTask',
    'canCreateTask',
    'canCommentOrLog',
    'canSubmitProjectItem',
    'canManageEvidenceItem',
    'canValidateDeliverables',
    'canUseProjectAi',
    'canUseStudentAi',
]));
check('ProjectPermissionService couvre evidence et IA', $projectPermissionSource !== '' && str_contains($projectPermissionSource, 'canManageEvidenceItem(') && str_contains($projectPermissionSource, 'canUseProjectAi(') && str_contains($projectPermissionSource, 'canUseStudentAi('));
check('Repository delegue les permissions Projects au service', $repositoryUserCanViewProject !== '' && str_contains($repositoryUserCanViewProject, 'permissions()->canViewProject') && $repositoryUserCanEditTask !== '' && str_contains($repositoryUserCanEditTask, 'permissions()->canEditTask'));
check('ProjectPermissionService conserve manage_options', $projectPermissionSource !== '' && str_contains($projectPermissionSource, "user_can(\$userId, 'manage_options')"));
check('Repository conserve les facades de permission Projects', repository_permission_facades_delegate($repositorySource));
check('IA eleve Projects utilise ProjectPermissionService', $projectsStudentAiSource !== '' && str_contains($projectsStudentAiSource, 'new ProjectPermissionService($this->repository)') && str_contains($projectsStudentAiSource, 'canUseStudentAi('));
check('RestController conserve les routes Projects attendues', source_contains_all($projectsRestSource, [
    "register_rest_route(self::NS, '/projects'",
    "register_rest_route(self::NS, '/projects/(?P<id>\\d+)'",
    "register_rest_route(self::NS, '/projects/(?P<id>\\d+)/board'",
    "register_rest_route(self::NS, '/projects/(?P<id>\\d+)/tasks'",
    "register_rest_route(self::NS, '/projects/(?P<id>\\d+)/evidence'",
    "register_rest_route(self::NS, '/evidence/(?P<id>\\d+)/download'",
    "register_rest_route(self::NS, '/projects/(?P<id>\\d+)/ai/'",
    "register_rest_route(self::NS, '/projects/(?P<id>\\d+)/student-ai/'",
]));
check('RestController conserve les callbacks de permission Projects', source_contains_all($projectsRestSource, [
    "'permission_callback' => [self::class, 'canViewProject']",
    "'permission_callback' => [self::class, 'canManageProject']",
    "'permission_callback' => [self::class, 'canCreateTask']",
    "'permission_callback' => [self::class, 'canCreateEvidence']",
    "'permission_callback' => [self::class, 'canManageEvidence']",
    "'permission_callback' => [self::class, 'canDownloadEvidence']",
    "'permission_callback' => [self::class, 'canUseProjectAi']",
    "'permission_callback' => [self::class, 'canUseProjectStudentAi']",
]));
check('Shortcodes Projects conservent les noms publics attendus', source_contains_all($projectsShortcodesSource, [
    "add_shortcode('ouinpo_my_projects'",
    "add_shortcode('ouinpo_project_kanban'",
    "add_shortcode('ouinpo_project_journal'",
    "add_shortcode('ouinpo_project_deliverables'",
    "add_shortcode('ouinpo_project_evidence'",
    "add_shortcode('ouinpo_project_ai_assistant'",
    "add_shortcode('ouinpo_project_student_ai'",
]));

$failed = 0;
foreach ($checks as [$label, $ok]) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, PHP_EOL . $failed . ' verification(s) en echec.' . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Optimisations OK: ' . count($checks) . ' verification(s).' . PHP_EOL;
exit(0);

function path(string $relative): string
{
    global $root;

    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function check(string $label, bool $ok): void
{
    global $checks;

    $checks[] = [$label, $ok];
}

function read_file(string $file): string
{
    if (!is_file($file)) {
        return '';
    }

    $source = file_get_contents($file);

    return is_string($source) ? $source : '';
}

function parser_accepts(string $raw, string $key): bool
{
    $parsed = \Ouinpo\Suite\Core\Ai\JsonResponseParser::parse($raw, 'object');

    return is_array($parsed) && array_key_exists($key, $parsed);
}

function all_present(array $expected, array $lookup): bool
{
    foreach ($expected as $value) {
        if (!isset($lookup[$value])) {
            return false;
        }
    }

    return true;
}

function source_contains_all(string $source, array $needles): bool
{
    if ($source === '') {
        return false;
    }

    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            return false;
        }
    }

    return true;
}

function status_keys_in_order(string $source, array $expected): bool
{
    if ($source === '') {
        return false;
    }

    $offset = 0;
    foreach ($expected as $statusKey) {
        $position = strpos($source, "'status_key' => '{$statusKey}'", $offset);
        if ($position === false) {
            return false;
        }
        $offset = $position + strlen($statusKey);
    }

    return true;
}

function methods_present(string $source, array $methods): bool
{
    foreach ($methods as $method) {
        if (method_body($source, $method) === '') {
            return false;
        }
    }

    return true;
}

function method_contains_all(string $source, string $method, array $needles): bool
{
    return source_contains_all(method_body($source, $method), $needles);
}

function repository_permission_facades_delegate(string $source): bool
{
    $facades = [
        'isProjectMember' => 'permissions()->isProjectMember',
        'userCanViewProject' => 'permissions()->canViewProject',
        'userCanManageProject' => 'permissions()->canManageProject',
        'userCanManageProjectRow' => 'permissions()->canManageProjectRow',
        'userCanManageAll' => 'permissions()->canManageAllProjects',
        'userCanEditTask' => 'permissions()->canEditTask',
        'userCanSubmitProjectItem' => 'permissions()->canSubmitProjectItem',
        'userCanManageEvidenceItem' => 'permissions()->canManageEvidenceItem',
    ];

    foreach ($facades as $method => $needle) {
        $body = method_body($source, $method);
        if ($body === '' || !str_contains($body, $needle)) {
            return false;
        }
    }

    return true;
}

function repository_deliverable_facades_delegate(string $source): bool
{
    $facades = [
        'ensureDefaultDeliverables' => 'deliverables()->ensureDefaultDeliverables',
        'getDeliverables' => 'deliverables()->getDeliverables',
        'getDeliverable' => 'deliverables()->getDeliverable',
        'createDeliverable' => 'deliverables()->createDeliverable',
        'updateDeliverable' => 'deliverables()->updateDeliverable',
        'updateDeliverableStatus' => 'deliverables()->updateDeliverableStatus',
        'deleteDeliverable' => 'deliverables()->deleteDeliverable',
        'nextDeliverablePosition' => 'deliverables()->nextDeliverablePosition',
        'deliverableBelongsToProject' => 'deliverables()->deliverableBelongsToProject',
    ];

    foreach ($facades as $method => $needle) {
        $body = method_body($source, $method);
        if ($body === '' || !str_contains($body, $needle)) {
            return false;
        }
    }

    return str_contains($source, 'function deliverables(): ProjectDeliverableService');
}

function repository_evidence_facades_delegate(string $source): bool
{
    $facades = [
        'getEvidence' => 'evidence()->getEvidence',
        'getEvidenceItem' => 'evidence()->getEvidenceItem',
        'createEvidence' => 'evidence()->createEvidence',
        'updateEvidence' => 'evidence()->updateEvidence',
        'deleteEvidence' => 'evidence()->deleteEvidence',
        'decorateEvidenceAttachment' => 'ProjectEvidenceService::decorateEvidenceAttachment',
    ];

    foreach ($facades as $method => $needle) {
        $body = method_body($source, $method);
        if ($body === '' || !str_contains($body, $needle)) {
            return false;
        }
    }

    return str_contains($source, 'function evidence(): ProjectEvidenceService');
}

function repository_journal_facades_delegate(string $source): bool
{
    $facades = [
        'getComments' => 'journal()->getComments',
        'addComment' => 'journal()->addComment',
        'getLogs' => 'journal()->getLogs',
        'addLog' => 'journal()->addLog',
    ];

    foreach ($facades as $method => $needle) {
        $body = method_body($source, $method);
        if ($body === '' || !str_contains($body, $needle)) {
            return false;
        }
    }

    return str_contains($source, 'function journal(): ProjectJournalService');
}

function repository_checklist_facades_delegate(string $source): bool
{
    $facades = [
        'getChecklistForTask' => 'checklist()->getChecklistForTask',
        'getChecklistItem' => 'checklist()->getChecklistItem',
        'addChecklistItem' => 'checklist()->addChecklistItem',
        'updateChecklistItem' => 'checklist()->updateChecklistItem',
        'deleteChecklistItem' => 'checklist()->deleteChecklistItem',
    ];

    foreach ($facades as $method => $needle) {
        $body = method_body($source, $method);
        if ($body === '' || !str_contains($body, $needle)) {
            return false;
        }
    }

    return str_contains($source, 'function checklist(): ProjectChecklistService');
}

function repository_member_facades_delegate(string $source): bool
{
    $facades = [
        'getMembers' => 'members()->getMembers',
        'addMember' => 'members()->addMember',
        'removeMember' => 'members()->removeMember',
    ];

    foreach ($facades as $method => $needle) {
        $body = method_body($source, $method);
        if ($body === '' || !str_contains($body, $needle)) {
            return false;
        }
    }

    return str_contains($source, 'function members(): ProjectMemberService');
}

function repository_task_facades_delegate(string $source): bool
{
    $facades = [
        'createTask' => 'tasks()->createTask',
        'updateTask' => 'tasks()->updateTask',
        'moveTask' => 'tasks()->moveTask',
        'deleteTask' => 'tasks()->deleteTask',
        'getTask' => 'tasks()->getTask',
        'getMainTasks' => 'tasks()->getMainTasks',
        'nextTaskPosition' => 'tasks()->nextTaskPosition',
        'taskBelongsToProject' => 'tasks()->taskBelongsToProject',
    ];

    foreach ($facades as $method => $needle) {
        $body = method_body($source, $method);
        if ($body === '' || !str_contains($body, $needle)) {
            return false;
        }
    }

    return str_contains($source, 'function tasks(): ProjectTaskService');
}

function repository_competency_facades_delegate(string $source): bool
{
    $facades = [
        'getCompetencyLinks' => 'competencies()->getCompetencyLinks',
        'getCompetencyLink' => 'competencies()->getCompetencyLink',
        'addCompetencyLink' => 'competencies()->addCompetencyLink',
        'deleteCompetencyLink' => 'competencies()->deleteCompetencyLink',
        'getAvailableCompetencies' => 'competencies()->getAvailableCompetencies',
        'objectBelongsToProject' => 'competencies()->objectBelongsToProject',
        'competencyExists' => 'competencies()->competencyExists',
    ];

    foreach ($facades as $method => $needle) {
        $body = method_body($source, $method);
        if ($body === '' || !str_contains($body, $needle)) {
            return false;
        }
    }

    return str_contains($source, 'function competencies(): ProjectCompetencyService');
}

function competency_post_insert_lookup_omits_project_id(string $source): bool
{
    $start = strpos($source, 'SELECT id FROM');
    if ($start === false) {
        return false;
    }

    $lookup = substr($source, $start);

    return str_contains($lookup, 'WHERE object_type = %s AND object_id = %d AND competency_id = %d')
        && !str_contains($lookup, 'project_id');
}

function bootstrap_module_ids(string $source): array
{
    if ($source === '') {
        return [];
    }

    preg_match_all('/use\s+Ouinpo\\\\Suite\\\\Modules\\\\([^\\\\]+)\\\\Module\s+as\s+([A-Za-z]+)Module;/', $source, $uses, PREG_SET_ORDER);
    preg_match_all('/register\(new\s+([A-Za-z]+)Module\(\)\)/', $source, $registers, PREG_SET_ORDER);

    $aliases = [];
    foreach ($uses as $use) {
        $aliases[$use[2]] = module_namespace_to_id($use[1]);
    }

    $ids = [];
    foreach ($registers as $register) {
        if (isset($aliases[$register[1]])) {
            $ids[] = $aliases[$register[1]];
        }
    }

    return array_values(array_unique($ids));
}

function module_namespace_to_id(string $namespace): string
{
    $map = [
        'Exercises' => 'exercises',
        'Flashcards' => 'flashcards',
        'Submissions' => 'submissions',
        'SegFault' => 'segfault',
        'Gate' => 'gate',
        'RechText' => 'rechtext',
        'Meta' => 'meta',
        'Projects' => 'projects',
    ];

    return $map[$namespace] ?? strtolower($namespace);
}

function reset_module_settings_cache(): void
{
    $property = new ReflectionProperty(\Ouinpo\Suite\Core\ModuleSettings::class, 'enabledCache');
    $property->setAccessible(true);
    $property->setValue(null, null);
}

function method_body(string $source, string $method): string
{
    $needle = 'function ' . $method . '(';
    $start = strpos($source, $needle);
    if ($start === false) {
        return '';
    }

    $open = strpos($source, '{', $start);
    if ($open === false) {
        return '';
    }

    $depth = 0;
    $length = strlen($source);
    for ($i = $open; $i < $length; $i++) {
        if ($source[$i] === '{') {
            $depth++;
        } elseif ($source[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $open, $i - $open + 1);
            }
        }
    }

    return '';
}
