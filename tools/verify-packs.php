#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$packsDir = $root . DIRECTORY_SEPARATOR . 'packs';
$files = glob($packsDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
sort($files);

$errors = [];
$warnings = [];

if (!$files) {
    $errors[] = 'Aucun pack JSON trouve dans packs/.';
}

foreach ($files as $file) {
    $name = basename($file);
    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        $errors[] = "{$name}: fichier vide ou illisible.";
        continue;
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $errors[] = "{$name}: JSON invalide: " . $exception->getMessage();
        continue;
    }

    if (!is_array($data)) {
        $errors[] = "{$name}: la racine JSON doit etre un objet.";
        continue;
    }

    if (!isset($data['schema_version']) || trim((string) $data['schema_version']) === '') {
        $errors[] = "{$name}: schema_version manquant.";
    }

    $pack = $data['pack'] ?? null;
    if (!is_array($pack)) {
        $errors[] = "{$name}: pack manquant ou invalide.";
    } else {
        foreach (['slug', 'title'] as $field) {
            if (!isset($pack[$field]) || trim((string) $pack[$field]) === '') {
                $errors[] = "{$name}: pack.{$field} manquant.";
            }
        }
    }

    foreach (find_private_fields($data) as $path) {
        $errors[] = "{$name}: champ manifestement prive detecte: {$path}.";
    }

    $levelRefs = collect_refs($data['school_levels'] ?? null, ['slug', 'label']);
    $domainRefs = collect_refs($data['domains'] ?? null, ['slug']);

    foreach (($data['competencies'] ?? []) as $index => $competency) {
        if (!is_array($competency)) {
            continue;
        }

        $domainSlug = trim((string) ($competency['domain_slug'] ?? ''));
        if ($domainSlug !== '' && $domainRefs && !isset($domainRefs[$domainSlug])) {
            $warnings[] = "{$name}: competencies[{$index}].domain_slug reference un domaine absent: {$domainSlug}.";
        }

        $level = trim((string) ($competency['level_slug'] ?? ($competency['level'] ?? '')));
        if ($level !== '' && $levelRefs && !isset($levelRefs[$level]) && !isset($levelRefs[normalize_ref($level)])) {
            $warnings[] = "{$name}: competencies[{$index}] reference un niveau absent: {$level}.";
        }
    }

    foreach (($data['exercises'] ?? []) as $index => $exercise) {
        if (!is_array($exercise)) {
            continue;
        }

        $levelSlug = trim((string) ($exercise['level_slug'] ?? ''));
        if ($levelSlug !== '' && $levelRefs && !isset($levelRefs[$levelSlug]) && !isset($levelRefs[normalize_ref($levelSlug)])) {
            $warnings[] = "{$name}: exercises[{$index}].level_slug reference un niveau absent: {$levelSlug}.";
        }

        $domainSlug = trim((string) ($exercise['domain_slug'] ?? ''));
        if ($domainSlug !== '' && $domainRefs && !isset($domainRefs[$domainSlug])) {
            $warnings[] = "{$name}: exercises[{$index}].domain_slug reference un domaine absent: {$domainSlug}.";
        }
    }
}

foreach ($warnings as $warning) {
    fwrite(STDERR, "WARN {$warning}" . PHP_EOL);
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR {$error}" . PHP_EOL);
    }
    exit(1);
}

echo 'Packs OK: ' . count($files) . ' fichier(s) verifies.' . PHP_EOL;
exit(0);

function collect_refs($items, array $fields): array
{
    if (!is_array($items)) {
        return [];
    }

    $refs = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        foreach ($fields as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $refs[$value] = true;
                $refs[normalize_ref($value)] = true;
            }
        }
    }

    return $refs;
}

function find_private_fields(array $data, string $path = ''): array
{
    $hits = [];
    $privatePattern = '/(^|_)(password|passwd|secret|token|api_key|private_key|client_secret|auth_json|wp_config)(_|$)/i';

    foreach ($data as $key => $value) {
        $keyString = (string) $key;
        $currentPath = $path === '' ? $keyString : $path . '.' . $keyString;

        if (preg_match($privatePattern, $keyString)) {
            $hits[] = $currentPath;
        }

        if (is_array($value)) {
            array_push($hits, ...find_private_fields($value, $currentPath));
        }
    }

    return $hits;
}

function normalize_ref(string $value): string
{
    $value = strtolower(trim($value));
    $value = str_replace(["\u{FFFD}", '?', 'Ã¨', 'Ã©', 'Ãª'], ['e', 'e', 'e', 'e', 'e'], $value);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($converted) && $converted !== '') {
        $value = $converted;
    }

    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    return trim($value, '-');
}
