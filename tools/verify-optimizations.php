#!/usr/bin/env php
<?php

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('OUINPO_SUITE_VERSION', '0.7.0-beta');

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
$projectBoardService = path('src/Modules/Projects/ProjectBoardService.php');
$projectStatsService = path('src/Modules/Projects/ProjectStatsService.php');

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
$projectBoardSource = read_file($projectBoardService);
$projectStatsSource = read_file($projectStatsService);
$repositoryBoard = method_body($repositorySource, 'getBoard');
$boardGetBoard = method_body($projectBoardSource, 'getBoard');
$repositoryProjectSummary = method_body($repositorySource, 'getProjectSummary');
$statsProjectSummary = method_body($projectStatsSource, 'getProjectSummary');
check('ProjectBoardService existe', is_file($projectBoardService));
check('Repository delegue getBoard au service', $repositoryBoard !== '' && str_contains($repositoryBoard, 'ProjectBoardService'));
check('Projects charge les checklists en groupe', $projectBoardSource !== '' && str_contains($projectBoardSource, 'function getChecklistForTasks('));
check('getBoard utilise le chargement groupe des checklists', $boardGetBoard !== '' && str_contains($boardGetBoard, 'getChecklistForTasks(array_column($tasks, \'id\'))'));
check('ProjectStatsService existe', is_file($projectStatsService));
check('Repository delegue getProjectSummary au service', $repositoryProjectSummary !== '' && str_contains($repositoryProjectSummary, 'ProjectStatsService'));
check('getProjectSummary utilise des agregats SQL', $statsProjectSummary !== '' && str_contains($statsProjectSummary, 'SUM(CASE WHEN'));
check('getProjectSummary evite les get_var() de compteurs separes', $statsProjectSummary !== '' && substr_count($statsProjectSummary, 'get_var(') === 0);

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
