#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$themeParts = [
    'foundation.css',
    'content.css',
    'components.css',
    'legacy-overrides.css',
];

$moduleParts = [
    'exercises.css',
    'practical.css',
    'written.css',
    'teacher.css',
    'flashcards.css',
    'segfault.css',
    'rechtext.css',
    'projects.css',
    'submissions.css',
    'titles.css',
];

foreach (['ouinpo', 'bsio'] as $theme) {
    foreach ($themeParts as $file) {
        require_file("assets/css/themes/{$theme}/{$file}");
    }

    foreach ($moduleParts as $file) {
        require_file("assets/css/themes/{$theme}/modules/{$file}");
    }
}

require_file('assets/css/themes/ouinpo.css');
require_file('assets/css/themes/bsio.css');

foreach (glob(path('assets/css/themes') . DIRECTORY_SEPARATOR . '*.css') ?: [] as $file) {
    reject_import($file);
    require_charset($file);
}

foreach (glob(path('assets/css/themes') . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.css') ?: [] as $file) {
    reject_import($file);
    require_charset($file);
}

foreach (glob(path('assets/css/themes') . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . '*.css') ?: [] as $file) {
    reject_import($file);
    require_charset($file);
}

$assetsSource = read_file('src/Modules/Exercises/plugin/public/Assets.php');
$assetSources = $assetsSource
    . read_file('src/Modules/Flashcards/plugin/public/Assets.php')
    . read_file('src/Modules/Projects/Assets.php')
    . read_file('src/Modules/RechText/plugin/ouinpo_recherche_textuelle.php')
    . read_file('src/Modules/SegFault/plugin/segfault.php')
    . read_file('src/Modules/Submissions/plugin/ouinpo-submissions.php')
    . read_file('src/Modules/Exercises/plugin/inc/Titles.php');

foreach ([
    'ouinpo-theme-css',
    'ouinpo-theme-exercises-css',
    'ouinpo-theme-practical-css',
    'ouinpo-theme-written-css',
    'ouinpo-theme-teacher-css',
    'ouinpo-theme-flashcards-css',
    'ouinpo-theme-segfault-css',
    'ouinpo-theme-rechtext-css',
    'ouinpo-theme-projects-css',
    'ouinpo-theme-submissions-css',
    'ouinpo-theme-titles-css',
] as $handle) {
    require_contains($assetSources, "'{$handle}'", "handle {$handle} present");
}

require_contains(
    $assetsSource,
    "['ouinpo-written-module-css', 'ouinpo-theme-written-css']",
    'ouinpo-written-css depend du theme ecrit'
);
require_contains(
    $assetsSource,
    "['ouinpo-practical-module-css', 'ouinpo-theme-practical-css']",
    'ouinpo-practical-css depend du theme pratique'
);
require_contains(
    $assetsSource,
    "['ouinpo-exo-module-css', 'ouinpo-theme-exercises-css']",
    'ouinpo-exo-css depend du theme exercices'
);
require_contains(
    $assetsSource,
    "'legacy-overrides' => 'legacy-overrides.css'",
    'legacy-overrides est declare comme couche de theme'
);

$exercisesCss = read_file('assets/css/front/exercises.css');
if (preg_match('/\.ouinpo-written-[A-Za-z0-9_-]+/', $exercisesCss) === 1) {
    $errors[] = 'assets/css/front/exercises.css contient encore des regles .ouinpo-written-*';
}

foreach (['ouinpo', 'bsio'] as $theme) {
    $legacySource = read_file("assets/css/themes/{$theme}/legacy-overrides.css");
    if (preg_match('/\.ouinpo-written-[A-Za-z0-9_-]+/', $legacySource) === 1) {
        $errors[] = "assets/css/themes/{$theme}/legacy-overrides.css contient encore des regles .ouinpo-written-*";
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'FAIL ' . $error . PHP_EOL);
    }
    exit(1);
}

echo 'Theme CSS OK.' . PHP_EOL;
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

function require_charset(string $file): void
{
    global $errors, $root;

    $normalized = str_replace('\\', '/', $file);
    if (!str_contains($normalized, '/assets/css/themes/ouinpo') && !str_contains($normalized, '/assets/css/themes/bsio')) {
        return;
    }

    $source = file_get_contents($file);
    if (!is_string($source)) {
        return;
    }

    if (!str_starts_with($source, '@charset "UTF-8";')) {
        $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
        $errors[] = "charset UTF-8 manquant en tete: {$relative}";
    }
}

function require_contains(string $source, string $needle, string $label): void
{
    global $errors;

    if (!str_contains($source, $needle)) {
        $errors[] = $label;
    }
}
