#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$themeLayers = [
    'foundation' => 'foundation.css',
    'content' => 'content.css',
    'components' => 'components.css',
    'legacy-overrides' => 'legacy-overrides.css',
];

$themeModules = [
    'exercises' => 'ouinpo-theme-exercises-css',
    'practical' => 'ouinpo-theme-practical-css',
    'written' => 'ouinpo-theme-written-css',
    'teacher' => 'ouinpo-theme-teacher-css',
    'flashcards' => 'ouinpo-theme-flashcards-css',
    'segfault' => 'ouinpo-theme-segfault-css',
    'rechtext' => 'ouinpo-theme-rechtext-css',
    'projects' => 'ouinpo-theme-projects-css',
    'submissions' => 'ouinpo-theme-submissions-css',
    'titles' => 'ouinpo-theme-titles-css',
];

$publicHandles = [
    'ouinpo-theme-css' => 'src/Modules/Exercises/plugin/public/Assets.php',
    'ouinpo-exo-css' => 'src/Modules/Exercises/plugin/public/Assets.php',
    'ouinpo-practical-css' => 'src/Modules/Exercises/plugin/public/Assets.php',
    'ouinpo-written-css' => 'src/Modules/Exercises/plugin/public/Assets.php',
    'ouinpo-teacher-css' => 'src/Modules/Exercises/plugin/public/Assets.php',
    'ouinpo-flashcards' => 'src/Modules/Flashcards/plugin/public/Assets.php',
    'ouinpo-projects' => 'src/Modules/Projects/Assets.php',
    'ouinpo-rechtext-css' => 'src/Modules/RechText/plugin/ouinpo_recherche_textuelle.php',
    'ouinpo-sf' => 'src/Modules/SegFault/plugin/segfault.php',
    'ouinpo-submissions' => 'src/Modules/Submissions/plugin/ouinpo-submissions.php',
    'ouinpo-titles-css' => 'src/Modules/Exercises/plugin/inc/Titles.php',
];

$publicThemeDeps = [
    'ouinpo-exo-css' => 'ouinpo-theme-exercises-css',
    'ouinpo-practical-css' => 'ouinpo-theme-practical-css',
    'ouinpo-written-css' => 'ouinpo-theme-written-css',
    'ouinpo-teacher-css' => 'ouinpo-theme-teacher-css',
    'ouinpo-flashcards' => 'ouinpo-theme-flashcards-css',
    'ouinpo-projects' => 'ouinpo-theme-projects-css',
    'ouinpo-rechtext-css' => 'ouinpo-theme-rechtext-css',
    'ouinpo-sf' => 'ouinpo-theme-segfault-css',
    'ouinpo-submissions' => 'ouinpo-theme-submissions-css',
    'ouinpo-titles-css' => 'ouinpo-theme-titles-css',
];

require_file('assets/css/themes/neutral.css');
require_file('assets/css/themes/ouinpo.css');
require_file('assets/css/themes/bsio.css');

foreach (['ouinpo', 'bsio'] as $theme) {
    foreach ($themeLayers as $layer) {
        require_file("assets/css/themes/{$theme}/{$layer}");
    }

    foreach (array_keys($themeModules) as $module) {
        require_file("assets/css/themes/{$theme}/modules/{$module}.css");
    }
}

foreach (glob(path('assets/css/themes') . DIRECTORY_SEPARATOR . '**' . DIRECTORY_SEPARATOR . '*.css') ?: [] as $file) {
    reject_import($file);
}
foreach (glob(path('assets/css/themes') . DIRECTORY_SEPARATOR . '*.css') ?: [] as $file) {
    reject_import($file);
}
foreach (glob(path('assets/css/themes') . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.css') ?: [] as $file) {
    reject_import($file);
}
foreach (glob(path('assets/css/themes') . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . '*.css') ?: [] as $file) {
    reject_import($file);
}

$assetSource = read_file('src/Modules/Exercises/plugin/public/Assets.php');
$assetRuntimeSource = strip_php_comments($assetSource);

foreach (['neutral', 'ouinpo', 'bsio'] as $mode) {
    require_contains($assetRuntimeSource, "'{$mode}'", "mode de style {$mode} supporte");
}

foreach (array_values($themeModules) as $themeHandle) {
    require_contains($assetRuntimeSource, "'{$themeHandle}'", "handle thematique {$themeHandle} enregistre");
}

foreach (array_keys($themeLayers) as $layer) {
    require_contains($assetRuntimeSource, "'ouinpo-theme-' . \$layer_key . '-css'", 'modele de handle pour couches thematiques enregistre');
    require_contains($assetRuntimeSource, "'{$layer}' => '{$themeLayers[$layer]}'", "couche {$layer} declaree");
}

foreach ($publicHandles as $handle => $sourceFile) {
    require_contains(read_file($sourceFile), "'{$handle}'", "handle public historique {$handle} preserve");
}

foreach ($publicThemeDeps as $publicHandle => $themeHandle) {
    $source = read_file($publicHandles[$publicHandle]);
    require_contains($source, "'{$themeHandle}'", "{$publicHandle} depend du handle thematique {$themeHandle}");
}

require_contains($assetRuntimeSource, "'theme_neutral' => 'assets/css/themes/neutral.css'", 'neutral.css est la source du mode neutral');

if (str_contains($assetRuntimeSource, 'assets/css/themes/ouinpo.css') || str_contains($assetRuntimeSource, 'assets/css/themes/bsio.css')) {
    $errors[] = 'les fichiers historiques ouinpo.css/bsio.css sont encore references comme source principale';
}

foreach (['assets/css/themes/ouinpo.css', 'assets/css/themes/bsio.css'] as $compatFile) {
    $source = read_file($compatFile);
    if (substr_count($source, '{') > 0) {
        $errors[] = "{$compatFile} contient des regles CSS alors qu il doit rester un fichier de compatibilite court";
    }
}

$exercisesCss = read_file('assets/css/front/exercises.css');
if (preg_match('/\.ouinpo-written-[A-Za-z0-9_-]+/', $exercisesCss) === 1) {
    $errors[] = 'assets/css/front/exercises.css contient des regles .ouinpo-written-*';
}

require_contains(read_file('assets/css/front/exercises.css'), '.ouinpo-exercise-', 'front exercises reste separe');
require_contains(read_file('assets/css/front/practical.css'), '.ouinpo-practical-', 'front practical reste separe');
require_contains(read_file('assets/css/front/written.css'), '.ouinpo-written-', 'front written reste separe');

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'FAIL ' . $error . PHP_EOL);
    }
    exit(1);
}

echo 'Theme CSS runtime diagnostics OK.' . PHP_EOL;
exit(0);

function path(string $relative): string
{
    global $root;

    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function read_file(string $relative): string
{
    $file = path($relative);
    if (!is_file($file)) {
        return '';
    }

    $source = file_get_contents($file);

    return is_string($source) ? $source : '';
}

function require_file(string $relative): void
{
    global $errors;

    if (!is_file(path($relative))) {
        $errors[] = "fichier manquant: {$relative}";
    }
}

function reject_import(string $file): void
{
    global $errors, $root;

    $source = file_get_contents($file);
    if (!is_string($source)) {
        return;
    }

    if (str_contains($source, '@import')) {
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
        $errors[] = "directive @import interdite: {$relative}";
    }
}

function require_contains(string $source, string $needle, string $label): void
{
    global $errors;

    if (!str_contains($source, $needle)) {
        $errors[] = $label;
    }
}

function strip_php_comments(string $source): string
{
    $tokens = token_get_all($source);
    $withoutComments = '';

    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $withoutComments .= is_array($token) ? $token[1] : $token;
    }

    return $withoutComments;
}
