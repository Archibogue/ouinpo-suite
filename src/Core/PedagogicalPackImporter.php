<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class PedagogicalPackImporter
{
    private static function tableExists(string $table): bool
    {
        global $wpdb;

        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function hasColumn(string $table, string $column): bool
    {
        global $wpdb;

        return (string) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column)) === $column;
    }

    public static function importFromFile(string $path): array
    {
        if (!is_readable($path)) {
            return [
                'ok' => false,
                'message' => 'Fichier illisible.',
                'details' => [],
            ];
        }

        $raw = file_get_contents($path);

        if ($raw === false || trim($raw) === '') {
            return [
                'ok' => false,
                'message' => 'Fichier vide ou impossible à lire.',
                'details' => [],
            ];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return [
                'ok' => false,
                'message' => 'JSON invalide : ' . json_last_error_msg(),
                'details' => [],
            ];
        }

        return self::importFromArray($data);
    }

    public static function importFromArray(array $data): array
    {
        global $wpdb;

        $details = [
            'school_levels_inserted' => 0,
            'school_levels_updated' => 0,
            'domains_inserted' => 0,
            'domains_updated' => 0,
            'difficulties_inserted' => 0,
            'difficulties_updated' => 0,
            'competencies_inserted' => 0,
            'competencies_updated' => 0,
            'competency_school_level_links' => 0,
            'exercises_inserted' => 0,
            'exercises_updated' => 0,
            'exercise_school_level_links' => 0,
            'exercise_competency_links' => 0,
            'hints_imported' => 0,
            'solutions_imported' => 0,
            'exam_meta_imported' => 0,
            'practical_calls_inserted' => 0,
            'practical_calls_updated' => 0,
            'practical_files_imported' => 0,
            'flashcard_decks_inserted' => 0,
            'flashcard_decks_updated' => 0,
            'flashcards_inserted' => 0,
            'flashcards_updated' => 0,
            'flashcard_competency_links' => 0,
            'warnings' => [],
        ];

        $schemaVersion = (string)($data['schema_version'] ?? '');

        if ($schemaVersion !== '1.0') {
            return [
                'ok' => false,
                'message' => 'Version de schéma non supportée : ' . ($schemaVersion !== '' ? $schemaVersion : 'absente'),
                'details' => $details,
            ];
        }

        if (!isset($data['pack']) || !is_array($data['pack'])) {
            return [
                'ok' => false,
                'message' => 'Métadonnées du pack absentes.',
                'details' => $details,
            ];
        }

        $requiredArrays = [
            'school_levels',
            'domains',
            'difficulties',
            'competencies',
            'exercises',
            'flashcards',
        ];

        foreach ($requiredArrays as $key) {
            if (!isset($data[$key])) {
                $data[$key] = [];
            }

            if (!is_array($data[$key])) {
                return [
                    'ok' => false,
                    'message' => "Le champ {$key} doit être un tableau.",
                    'details' => $details,
                ];
            }
        }

        $p = $wpdb->prefix . 'ouin_exo_';

        self::importSchoolLevels($p, $data['school_levels'], $details);
        self::importDomains($p, $data['domains'], $details);
        self::importDifficulties($p, $data['difficulties'], $details);
        self::importCompetencies($p, $data['competencies'], $details);
        self::importExercises($p, $data['exercises'], $details);
        self::importFlashcards($data['flashcards'], $details);
        return [
            'ok' => true,
            'message' => 'Pack importé.',
            'details' => $details,
        ];
    }

    private static function importSchoolLevels(string $p, array $rows, array &$details): void
    {
        global $wpdb;

        $table = $p . 'school_levels';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $details['warnings'][] = 'Niveau ignoré : entrée invalide.';
                continue;
            }

            $slug = sanitize_key((string)($row['slug'] ?? ''));
            $label = sanitize_text_field((string)($row['label'] ?? ''));
            $sortOrder = isset($row['sort_order']) ? (int) $row['sort_order'] : (isset($row['rank']) ? (int) $row['rank'] : 0);

            if ($slug === '' || $label === '') {
                $details['warnings'][] = 'Niveau ignoré : slug ou label manquant.';
                continue;
            }

            $existingId = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s",
                $slug
            ));

            if ($existingId) {
                $wpdb->update(
                    $table,
                    [
                        'label' => $label,
                        'sort_order' => max(0, $sortOrder),
                    ],
                    ['slug' => $slug],
                    ['%s', '%d'],
                    ['%s']
                );

                $details['school_levels_updated']++;
            } else {
                $wpdb->insert(
                    $table,
                    [
                        'slug' => $slug,
                        'label' => $label,
                        'sort_order' => max(0, $sortOrder),
                    ],
                    ['%s', '%s', '%d']
                );

                $details['school_levels_inserted']++;
            }
        }
    }

    private static function importDomains(string $p, array $rows, array &$details): void
    {
        global $wpdb;

        $table = $p . 'domains';

        if (!self::tableExists($table)) {
            return;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $details['warnings'][] = 'Domaine ignorÃ© : entrÃ©e invalide.';
                continue;
            }

            $slug = sanitize_key((string)($row['slug'] ?? ($row['domain_slug'] ?? '')));
            $label = sanitize_text_field((string)($row['label'] ?? ($row['domain'] ?? '')));
            $track = strtoupper(sanitize_text_field((string)($row['track'] ?? '')));
            $description = wp_kses_post((string)($row['description'] ?? ''));
            $sortOrder = isset($row['sort_order']) ? (int)$row['sort_order'] : (int)($row['rank'] ?? 0);
            $active = isset($row['active']) ? ((int)$row['active'] === 1 ? 1 : 0) : 1;

            if ($slug === '' || $label === '') {
                $details['warnings'][] = 'Domaine ignorÃ© : slug ou libellÃ© manquant.';
                continue;
            }

            $payload = [
                'slug' => $slug,
                'label' => $label,
                'track' => $track,
                'description' => $description !== '' ? $description : null,
                'sort_order' => max(0, $sortOrder),
                'active' => $active,
            ];

            $existingId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s AND track = %s",
                $slug,
                $track
            ));

            if ($existingId > 0) {
                $updated = $wpdb->update(
                    $table,
                    $payload,
                    ['id' => $existingId],
                    ['%s', '%s', '%s', '%s', '%d', '%d'],
                    ['%d']
                );

                if ($updated === false) {
                    $details['warnings'][] = 'Domaine ' . $slug . ' : mise Ã  jour impossible â€” ' . $wpdb->last_error;
                } else {
                    $details['domains_updated']++;
                }
            } else {
                $inserted = $wpdb->insert(
                    $table,
                    $payload,
                    ['%s', '%s', '%s', '%s', '%d', '%d']
                );

                if ($inserted === false) {
                    $details['warnings'][] = 'Domaine ' . $slug . ' : crÃ©ation impossible â€” ' . $wpdb->last_error;
                } else {
                    $details['domains_inserted']++;
                }
            }
        }
    }

    private static function ensureDomain(string $p, string $slug, string $label, string $track, array &$details): ?int
    {
        global $wpdb;

        $table = $p . 'domains';

        if (!self::tableExists($table) || $slug === '' || $label === '') {
            return null;
        }

        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE slug = %s AND track = %s",
            $slug,
            $track
        ));

        if ($id > 0) {
            $wpdb->update(
                $table,
                ['label' => $label],
                ['id' => $id],
                ['%s'],
                ['%d']
            );

            return $id;
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'slug' => $slug,
                'label' => $label,
                'track' => $track,
                'description' => null,
                'sort_order' => 0,
                'active' => 1,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d']
        );

        if ($inserted === false) {
            $details['warnings'][] = 'Domaine ' . $slug . ' : crÃ©ation automatique impossible â€” ' . $wpdb->last_error;
            return null;
        }

        $details['domains_inserted']++;

        return (int) $wpdb->insert_id;
    }

    private static function importDifficulties(string $p, array $rows, array &$details): void
    {
        global $wpdb;

        $table = $p . 'difficulties';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $details['warnings'][] = 'Difficulté ignorée : entrée invalide.';
                continue;
            }

            $slug = sanitize_key((string)($row['slug'] ?? ''));
            $label = sanitize_text_field((string)($row['label'] ?? ''));

            if ($slug === '' || $label === '') {
                $details['warnings'][] = 'Difficulté ignorée : slug ou label manquant.';
                continue;
            }

            $existingId = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s",
                $slug
            ));

            if ($existingId) {
                $wpdb->update(
                    $table,
                    ['label' => $label],
                    ['slug' => $slug],
                    ['%s'],
                    ['%s']
                );

                $details['difficulties_updated']++;
            } else {
                $wpdb->insert(
                    $table,
                    [
                        'slug' => $slug,
                        'label' => $label,
                    ],
                    ['%s', '%s']
                );

                $details['difficulties_inserted']++;
            }
        }
    }

    private static function importCompetencies(string $p, array $rows, array &$details): void
    {
        global $wpdb;

        $table = $p . 'competencies';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $details['warnings'][] = 'Compétence ignorée : entrée invalide.';
                continue;
            }

            $slug = sanitize_title((string)($row['slug'] ?? ''));

            if ($slug === '') {
                $details['warnings'][] = 'Compétence ignorée : slug manquant.';
                continue;
            }

            $track = strtoupper(sanitize_text_field((string)($row['track'] ?? 'NSI')));
            $track = $track !== '' ? substr($track, 0, 50) : 'NSI';

            $rawLevel = sanitize_text_field((string)($row['level'] ?? ''));
            $level = $rawLevel !== '' ? $rawLevel : self::displayLevelFromRow($p, $row);

            $domain = sanitize_text_field((string)($row['domain'] ?? ''));
            $domainSlug = sanitize_key((string)($row['domain_slug'] ?? ''));
            $domainId = self::ensureDomain($p, $domainSlug, $domain, $track, $details);

            $competency = wp_kses_post((string)($row['competency'] ?? ''));
            $capacity = wp_kses_post((string)($row['capacity'] ?? ''));
            $example = wp_kses_post((string)($row['example'] ?? ''));

            $referenceUrl = esc_url_raw((string)($row['reference_url'] ?? ''));
            $active = isset($row['active']) ? (int)$row['active'] : 1;
            $active = $active === 1 ? 1 : 0;

            $cycle = sanitize_key((string)($row['cycle'] ?? ''));

            if ($domain === '' || $domainSlug === '' || trim($competency) === '') {
                $details['warnings'][] = 'Compétence ignorée : domaine, domaine_slug ou compétence manquant pour ' . $slug . '.';
                continue;
            }

            $label = trim(wp_strip_all_tags($domain . ' — ' . $competency));

            $payload = [
                'domain' => $domain,
                'domain_slug' => $domainSlug,
                'competency' => $competency,
                'capacity' => $capacity !== '' ? $capacity : null,
                'example' => $example !== '' ? $example : null,
                'track' => $track,
                'level' => $level,
                'reference_url' => $referenceUrl !== '' ? $referenceUrl : null,
                'slug' => $slug,
                'active' => $active,
                'label' => $label,
                'cycle' => $cycle !== '' ? $cycle : null,
            ];

            $formats = [
                '%s', // domain
                '%s', // domain_slug
                '%s', // competency
                '%s', // capacity
                '%s', // example
                '%s', // track
                '%s', // level
                '%s', // reference_url
                '%s', // slug
                '%d', // active
                '%s', // label
                '%s', // cycle
            ];

            if ($domainId !== null && self::hasColumn($table, 'domain_id')) {
                $payload = ['domain_id' => $domainId] + $payload;
                array_unshift($formats, '%d');
            }

            $existingId = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s",
                $slug
            ));

            $competencyId = 0;

            if ($existingId) {
                $updated = $wpdb->update(
                    $table,
                    $payload,
                    ['slug' => $slug],
                    $formats,
                    ['%s']
                );

                if ($updated === false) {
                    $details['warnings'][] = 'Compétence ' . $slug . ' : mise à jour impossible — ' . $wpdb->last_error;
                } else {
                    $details['competencies_updated']++;
                    $competencyId = (int) $existingId;
                }
            } else {
                $inserted = $wpdb->insert(
                    $table,
                    $payload,
                    $formats
                );

                if ($inserted === false) {
                    $details['warnings'][] = 'Compétence ' . $slug . ' : création impossible — ' . $wpdb->last_error;
                } else {
                    $details['competencies_inserted']++;
                    $competencyId = (int) $wpdb->insert_id;
                }
            }

            if (!empty($competencyId)) {
                self::syncCompetencySchoolLevelLinks($p, $competencyId, $row, $level, $details);
            }
        }
    }

    private static function syncCompetencySchoolLevelLinks(string $p, int $competencyId, array $row, string $rawLevel, array &$details): void
    {
        global $wpdb;

        if ($competencyId <= 0) {
            return;
        }

        $table = $p . 'competency_school_level';
        $levelIds = [];

        if (!empty($row['level_slugs']) && is_array($row['level_slugs'])) {
            foreach ($row['level_slugs'] as $rawSlug) {
                $levelSlug = sanitize_key((string) $rawSlug);
                $levelId = self::getSchoolLevelIdBySlug($p, $levelSlug);
                if ($levelId !== null) {
                    $levelIds[] = $levelId;
                } elseif ($levelSlug !== '') {
                    $details['warnings'][] = "CompÃ©tence {$competencyId} : niveau inconnu ({$levelSlug}).";
                }
            }
        }

        if (!empty($row['level_slug'])) {
            $levelSlug = sanitize_key((string) $row['level_slug']);
            $levelId = self::getSchoolLevelIdBySlug($p, $levelSlug);
            if ($levelId !== null) {
                $levelIds[] = $levelId;
            } elseif ($levelSlug !== '') {
                $details['warnings'][] = "CompÃ©tence {$competencyId} : niveau inconnu ({$levelSlug}).";
            }
        }

        if (!$levelIds) {
            if ($rawLevel === 'Transversal') {
                $levelIds = array_map('intval', (array) $wpdb->get_col("SELECT id FROM {$p}school_levels ORDER BY sort_order ASC, id ASC"));
            } else {
                $levelId = self::getSchoolLevelIdByLegacyLabel($p, $rawLevel);
                if ($levelId !== null) {
                    $levelIds[] = $levelId;
                }
            }
        }

        $levelIds = array_values(array_unique(array_filter(array_map('intval', $levelIds))));

        if (!$levelIds) {
            $details['warnings'][] = "Compétence {$competencyId} : aucun niveau scolaire associé.";
            return;
        }

        $wpdb->delete($table, ['competency_id' => $competencyId], ['%d']);

        foreach ($levelIds as $levelId) {
            $wpdb->insert(
                $table,
                [
                    'competency_id'   => $competencyId,
                    'school_level_id' => $levelId,
                ],
                ['%d', '%d']
            );

            if (empty($wpdb->last_error)) {
                $details['competency_school_level_links']++;
            } else {
                $details['warnings'][] = "Compétence {$competencyId} : lien niveau impossible — " . $wpdb->last_error;
            }
        }
    }

    private static function importExercises(string $p, array $rows, array &$details): void
{
    global $wpdb;

    $tExercises = $p . 'exercises';
    $tExerciseSchoolLevel = $p . 'exercise_school_level';
    $tExerciseCompetency = $p . 'exercise_competency';
    $tHints = $p . 'hints';
    $tSolutions = $p . 'solutions';
    $tExamMeta = $p . 'exam_meta';
    $tPracticalFiles = $p . 'practical_files';

    foreach ($rows as $row) {
        if (!is_array($row)) {
            $details['warnings'][] = 'Exercice ignoré : entrée invalide.';
            continue;
        }

        $slug = sanitize_title((string)($row['slug'] ?? ''));
        $title = sanitize_text_field((string)($row['title'] ?? ''));
        $statement = wp_kses_post((string)($row['statement'] ?? ''));

        if ($slug === '' || $title === '' || trim($statement) === '') {
            $details['warnings'][] = 'Exercice ignoré : slug, titre ou énoncé manquant.';
            continue;
        }

        $levelSlug = sanitize_key((string)($row['level_slug'] ?? ''));
        $difficultySlug = sanitize_key((string)($row['difficulty_slug'] ?? ''));

        $levelId = self::getSchoolLevelIdBySlug($p, $levelSlug);
        $difficultyId = self::getDifficultyIdBySlug($p, $difficultySlug);

        if ($levelId === null && $levelSlug !== '') {
            $details['warnings'][] = "Exercice {$slug} : niveau inconnu ({$levelSlug}).";
        }

        if ($difficultyId === null) {
            $details['warnings'][] = "Exercice {$slug} : difficulté inconnue ({$difficultySlug}).";
        }

        $isActive = isset($row['is_active']) ? (int)$row['is_active'] : 1;
        $isActive = $isActive === 1 ? 1 : 0;

        $payload = [
            'level_id' => $levelId,
            'difficulty_id' => $difficultyId,
            'title' => $title,
            'slug' => $slug,
            'statement' => $statement,
            'is_active' => $isActive,
        ];

        $formats = [
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%d',
        ];

        $existingId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tExercises} WHERE slug = %s",
            $slug
        ));

        if ($existingId) {
            $exerciseId = (int)$existingId;

            $wpdb->update(
                $tExercises,
                $payload,
                ['id' => $exerciseId],
                $formats,
                ['%d']
            );

            $details['exercises_updated']++;
        } else {
            $payload['created_at'] = current_time('mysql');

            $wpdb->insert(
                $tExercises,
                $payload,
                array_merge($formats, ['%s'])
            );

            $exerciseId = (int)$wpdb->insert_id;
            $details['exercises_inserted']++;
        }

        if ($exerciseId <= 0) {
            $details['warnings'][] = "Exercice {$slug} : impossible de récupérer l’identifiant après import.";
            continue;
        }

        /*
         * Les éléments dépendants du contenu du pack sont remplacés.
         * On ne touche pas aux données élèves : statuts, tentatives, badges, résultats.
         */
        $wpdb->delete($tExerciseSchoolLevel, ['exercise_id' => $exerciseId], ['%d']);
        $wpdb->delete($tExerciseCompetency, ['exercise_id' => $exerciseId], ['%d']);
        $wpdb->delete($tHints, ['exercise_id' => $exerciseId], ['%d']);
        $wpdb->delete($tSolutions, ['exercise_id' => $exerciseId], ['%d']);
        $wpdb->delete($tExamMeta, ['exercise_id' => $exerciseId], ['%d']);
        $wpdb->delete($tPracticalFiles, ['exercise_id' => $exerciseId], ['%d']);

        
        self::importExerciseSchoolLevelLinks($p, $exerciseId, $levelId, $row, $details);
        self::importExerciseCompetencyLinks($p, $exerciseId, $row, $details);
        self::importExerciseHints($p, $exerciseId, $row, $details);
        self::importExerciseSolutions($p, $exerciseId, $row, $details);
        self::importExerciseExamMeta($p, $exerciseId, $row, $details);
        self::importPracticalCalls($p, $exerciseId, $row, $details);
        self::importPracticalFiles($p, $exerciseId, $row, $details);
    }
}

private static function getSchoolLevelIdBySlug(string $p, string $slug): ?int
{
    global $wpdb;

    if ($slug === '') {
        return null;
    }

    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$p}school_levels WHERE slug = %s",
        $slug
    ));

    return $id ? (int)$id : null;
}

private static function displayLevelFromRow(string $p, array $row): string
{
    global $wpdb;

    $slugs = [];

    if (!empty($row['level_slugs']) && is_array($row['level_slugs'])) {
        foreach ($row['level_slugs'] as $rawSlug) {
            $slug = sanitize_key((string) $rawSlug);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }
    }

    if (!empty($row['level_slug'])) {
        $slug = sanitize_key((string) $row['level_slug']);
        if ($slug !== '') {
            $slugs[] = $slug;
        }
    }

    $slugs = array_values(array_unique($slugs));

    if (!$slugs) {
        return '';
    }

    $placeholders = implode(',', array_fill(0, count($slugs), '%s'));
    $labels = (array) $wpdb->get_col($wpdb->prepare(
        "SELECT label FROM {$p}school_levels WHERE slug IN ({$placeholders}) ORDER BY sort_order ASC, id ASC",
        $slugs
    ));

    if (count($labels) > 1) {
        return 'Transversal';
    }

    return isset($labels[0]) ? (string) $labels[0] : '';
}

private static function getSchoolLevelIdByLegacyLabel(string $p, string $label): ?int
{
    global $wpdb;

    $label = trim($label);
    if ($label === '') {
        return null;
    }

    $slug = sanitize_title($label);
    $aliases = [
        'Première' => 'premiere',
        'Premiere' => 'premiere',
    ];

    if (isset($aliases[$label])) {
        $slug = $aliases[$label];
    }

    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id
           FROM {$p}school_levels
          WHERE label = %s
             OR slug = %s
          LIMIT 1",
        $label,
        $slug
    ));

    return $id ? (int)$id : null;
}

private static function getDifficultyIdBySlug(string $p, string $slug): ?int
{
    global $wpdb;

    if ($slug === '') {
        return null;
    }

    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$p}difficulties WHERE slug = %s",
        $slug
    ));

    return $id ? (int)$id : null;
}

private static function getCompetencyIdBySlug(string $p, string $slug): ?int
{
    global $wpdb;

    if ($slug === '') {
        return null;
    }

    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$p}competencies WHERE slug = %s",
        $slug
    ));

    return $id ? (int)$id : null;
}

private static function importExerciseCompetencyLinks(string $p, int $exerciseId, array $row, array &$details): void
{
    global $wpdb;

    $tExerciseCompetency = $p . 'exercise_competency';

    $slugs = $row['competency_slugs'] ?? [];

    if (!is_array($slugs)) {
        $details['warnings'][] = "Exercice {$exerciseId} : competency_slugs doit être un tableau.";
        return;
    }

    foreach ($slugs as $rawSlug) {
        $competencySlug = sanitize_title((string)$rawSlug);

        if ($competencySlug === '') {
            continue;
        }

        $competencyId = self::getCompetencyIdBySlug($p, $competencySlug);

        if ($competencyId === null) {
            $details['warnings'][] = "Exercice {$exerciseId} : compétence inconnue {$competencySlug}.";
            continue;
        }

        $wpdb->insert(
            $tExerciseCompetency,
            [
                'exercise_id' => $exerciseId,
                'competency_id' => $competencyId,
            ],
            ['%d', '%d']
        );

        if (empty($wpdb->last_error)) {
            $details['exercise_competency_links']++;
        }
    }
}

private static function importExerciseHints(string $p, int $exerciseId, array $row, array &$details): void
{
    global $wpdb;

    $tHints = $p . 'hints';
    $hints = $row['hints'] ?? [];

    if (!is_array($hints)) {
        $details['warnings'][] = "Exercice {$exerciseId} : hints doit être un tableau.";
        return;
    }

    foreach ($hints as $index => $hint) {
        if (!is_array($hint)) {
            $details['warnings'][] = "Exercice {$exerciseId} : indice invalide.";
            continue;
        }

        $order = isset($hint['order']) ? (int)$hint['order'] : ((int)$index + 1);
        $order = max(1, min(255, $order));

        $content = wp_kses_post((string)($hint['content'] ?? ''));

        if (trim($content) === '') {
            continue;
        }

        $wpdb->insert(
            $tHints,
            [
                'exercise_id' => $exerciseId,
                'hint_order' => $order,
                'content' => $content,
            ],
            ['%d', '%d', '%s']
        );

        if (empty($wpdb->last_error)) {
            $details['hints_imported']++;
        }
    }
}

private static function importExerciseSolutions(string $p, int $exerciseId, array $row, array &$details): void
{
    global $wpdb;

    $tSolutions = $p . 'solutions';
    $solutions = $row['solutions'] ?? [];

    if (!is_array($solutions)) {
        $details['warnings'][] = "Exercice {$exerciseId} : solutions doit être un tableau.";
        return;
    }

    foreach ($solutions as $index => $solution) {
        if (!is_array($solution)) {
            $details['warnings'][] = "Exercice {$exerciseId} : solution invalide.";
            continue;
        }

        $order = isset($solution['order']) ? (int)$solution['order'] : ((int)$index + 1);
        $order = max(1, min(255, $order));

        $title = sanitize_text_field((string)($solution['title'] ?? 'Solution'));
        $content = wp_kses_post((string)($solution['content'] ?? ''));
        $isOfficial = isset($solution['is_official']) ? (int)$solution['is_official'] : 0;
        $isOfficial = $isOfficial === 1 ? 1 : 0;

        if (trim($content) === '') {
            continue;
        }

        $wpdb->insert(
            $tSolutions,
            [
                'exercise_id' => $exerciseId,
                'title' => $title !== '' ? $title : 'Solution',
                'content' => $content,
                'solution_order' => $order,
                'is_official' => $isOfficial,
                'created_at' => current_time('mysql'),
                'updated_at' => null,
            ],
            ['%d', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        if (empty($wpdb->last_error)) {
            $details['solutions_imported']++;
        }
    }
}

    private static function importExerciseExamMeta(string $p, int $exerciseId, array $row, array &$details): void
    {
        global $wpdb;

        if (empty($row['exam_meta']) || !is_array($row['exam_meta'])) {
            return;
        }

        $meta = $row['exam_meta'];
        $tExamMeta = $p . 'exam_meta';

        $examType = sanitize_key((string)($meta['exam_type'] ?? 'written'));
        if (!in_array($examType, ['written', 'practical_subject'], true)) {
            $examType = 'written';
        }

        $sourceType = sanitize_key((string)($meta['source_type'] ?? 'type_bac'));
        if (!in_array($sourceType, ['annale', 'inspired', 'type_bac', 'classic'], true)) {
            $sourceType = 'type_bac';
        }

        $bacFormat = sanitize_key((string)($meta['bac_format'] ?? ''));
        if (!in_array($bacFormat, ['question_courte', 'lecture_code', 'code_a_completer', 'ecriture_complete', 'raisonnement'], true)) {
            $bacFormat = null;
        }

        $estimatedMinutes = isset($meta['estimated_minutes']) && $meta['estimated_minutes'] !== null
            ? max(0, (int)$meta['estimated_minutes'])
            : null;

        $sortInSubject = isset($meta['sort_in_subject']) && $meta['sort_in_subject'] !== null
            ? max(0, (int)$meta['sort_in_subject'])
            : null;

        $isExamLike = isset($meta['is_exam_like']) ? (int)$meta['is_exam_like'] : 1;
        $isExamLike = $isExamLike === 1 ? 1 : 0;

        $wpdb->insert(
            $tExamMeta,
            [
                'exercise_id' => $exerciseId,
                'exam_type' => $examType,
                'source_type' => $sourceType,
                'session_label' => self::nullableText($meta['session_label'] ?? null, 120),
                'year_label' => self::nullableText($meta['year_label'] ?? null, 20),
                'center_label' => self::nullableText($meta['center_label'] ?? null, 80),
                'theme_bac' => self::nullableText($meta['theme_bac'] ?? null, 80),
                'bac_format' => $bacFormat,
                'estimated_minutes' => $estimatedMinutes,
                'is_exam_like' => $isExamLike,
                'subject_group' => self::nullableText($meta['subject_group'] ?? null, 80),
                'sort_in_subject' => $sortInSubject,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );

        if (empty($wpdb->last_error)) {
            $details['exam_meta_imported']++;
        }
    }

    private static function nullableText(mixed $value, int $maxLength): ?string
    {
        $text = sanitize_text_field((string)($value ?? ''));
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLength);
        }

        return substr($text, 0, $maxLength);
    }

    private static function importExerciseSchoolLevelLinks(
    string $p,
    int $exerciseId,
    ?int $defaultLevelId,
    array $row,
    array &$details
): void {
    global $wpdb;

    $table = $p . 'exercise_school_level';

    $levelIds = [];

    /*
     * Cas avancé : un exercice peut être associé à plusieurs niveaux.
     * Exemple JSON :
     * "level_slugs": ["premiere", "terminale"]
     */
    if (!empty($row['level_slugs']) && is_array($row['level_slugs'])) {
        foreach ($row['level_slugs'] as $rawSlug) {
            $levelSlug = sanitize_key((string) $rawSlug);
            $levelId = self::getSchoolLevelIdBySlug($p, $levelSlug);

            if ($levelId !== null) {
                $levelIds[] = $levelId;
            } elseif ($levelSlug !== '') {
                $details['warnings'][] = "Exercice {$exerciseId} : niveau inconnu ({$levelSlug}).";
            }
        }
    }

    /*
     * Cas standard : on utilise level_slug.
     */
    if ($defaultLevelId !== null) {
        $levelIds[] = $defaultLevelId;
    }

    $levelIds = array_values(array_unique(array_filter($levelIds)));

    if (!$levelIds) {
        $details['warnings'][] = "Exercice {$exerciseId} : aucun niveau scolaire associé.";
        return;
    }

    foreach ($levelIds as $levelId) {
        $wpdb->insert(
            $table,
            [
                'exercise_id' => $exerciseId,
                'school_level_id' => $levelId,
            ],
            ['%d', '%d']
        );

        if (empty($wpdb->last_error)) {
            $details['exercise_school_level_links']++;
        } else {
            $details['warnings'][] = "Exercice {$exerciseId} : lien niveau impossible — " . $wpdb->last_error;
        }
    }
}

private static function importFlashcards(array $groups, array &$details): void
{
    global $wpdb;

    $pFc = $wpdb->prefix . 'ouin_fc_';

    $tDecks = $pFc . 'decks';
    $tCards = $pFc . 'cards';
    $tCardCompetency = $pFc . 'card_competency';

    foreach ($groups as $group) {
        if (!is_array($group)) {
            $details['warnings'][] = 'Groupe de flashcards ignoré : entrée invalide.';
            continue;
        }

        $deck = $group['deck'] ?? null;

        if (!is_array($deck)) {
            $details['warnings'][] = 'Groupe de flashcards ignoré : deck manquant.';
            continue;
        }

        $deckSlug = sanitize_title((string)($deck['slug'] ?? ''));
        $deckTitle = sanitize_text_field((string)($deck['title'] ?? ''));

        if ($deckSlug === '' || $deckTitle === '') {
            $details['warnings'][] = 'Deck ignoré : slug ou titre manquant.';
            continue;
        }

        $track = strtoupper(sanitize_text_field((string)($deck['track'] ?? 'NSI')));
        if (!in_array($track, ['SNT', 'NSI'], true)) {
            $track = 'NSI';
        }

        $level = sanitize_text_field((string)($deck['level'] ?? ''));

        $isActive = isset($deck['is_active']) ? (int)$deck['is_active'] : 1;
        $isActive = $isActive === 1 ? 1 : 0;

        $deckPayload = [
            'title' => $deckTitle,
            'slug' => $deckSlug,
            'description' => wp_kses_post((string)($deck['description'] ?? '')),
            'track' => $track,
            'level' => $level,
            'source_post_id' => null,
            'is_active' => $isActive,
            'updated_at' => current_time('mysql'),
        ];

        $deckFormats = [
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%d',
            '%s',
        ];

        $existingDeckId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tDecks} WHERE slug = %s",
            $deckSlug
        ));

        if ($existingDeckId) {
            $deckId = (int)$existingDeckId;

            $wpdb->update(
                $tDecks,
                $deckPayload,
                ['id' => $deckId],
                $deckFormats,
                ['%d']
            );

            $details['flashcard_decks_updated']++;
        } else {
            $deckPayload['created_at'] = current_time('mysql');

            $wpdb->insert(
                $tDecks,
                $deckPayload,
                array_merge($deckFormats, ['%s'])
            );

            $deckId = (int)$wpdb->insert_id;
            $details['flashcard_decks_inserted']++;
        }

        if ($deckId <= 0) {
            $details['warnings'][] = "Deck {$deckSlug} : impossible de récupérer l’identifiant.";
            continue;
        }

        $cards = $group['cards'] ?? [];

        if (!is_array($cards)) {
            $details['warnings'][] = "Deck {$deckSlug} : cards doit être un tableau.";
            continue;
        }

        foreach ($cards as $index => $card) {
            self::importFlashcard($deckId, $deckSlug, $index, $card, $details, $tCards, $tCardCompetency);
        }
    }
}

private static function importFlashcard(
    int $deckId,
    string $deckSlug,
    int $index,
    mixed $card,
    array &$details,
    string $tCards,
    string $tCardCompetency
): void {
    global $wpdb;

    if (!is_array($card)) {
        $details['warnings'][] = "Deck {$deckSlug} : carte invalide.";
        return;
    }

    $frontHtml = wp_kses_post((string)($card['front_html'] ?? ''));
    $backHtml = wp_kses_post((string)($card['back_html'] ?? ''));

    if (trim($frontHtml) === '' || trim($backHtml) === '') {
        $details['warnings'][] = "Deck {$deckSlug} : carte ignorée, recto ou verso manquant.";
        return;
    }

    $cardType = sanitize_key((string)($card['card_type'] ?? 'definition'));
    if (!in_array($cardType, ['definition', 'distinction', 'repere', 'syntaxe', 'vocabulaire'], true)) {
        $cardType = 'definition';
    }

    $sortOrder = isset($card['sort_order']) ? (int)$card['sort_order'] : ($index + 1);
    $isActive = isset($card['is_active']) ? (int)$card['is_active'] : 1;
    $isActive = $isActive === 1 ? 1 : 0;

    /*
     * Les cartes n’ont pas encore de slug en base.
     * On utilise donc un identifiant stable par deck + sort_order.
     * Si tu modifies l’ordre d’une carte dans un pack, cela créera une autre carte.
     * C’est acceptable pour la v1.
     */
    $existingCardId = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$tCards}
         WHERE deck_id = %d
           AND sort_order = %d",
        $deckId,
        $sortOrder
    ));

    $payload = [
        'deck_id' => $deckId,
        'card_type' => $cardType,
        'front_html' => $frontHtml,
        'back_html' => $backHtml,
        'note_teacher' => wp_kses_post((string)($card['note_teacher'] ?? '')),
        'sort_order' => $sortOrder,
        'is_active' => $isActive,
        'updated_at' => current_time('mysql'),
    ];

    $formats = [
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%d',
        '%s',
    ];

    if ($existingCardId) {
        $cardId = (int)$existingCardId;

        $wpdb->update(
            $tCards,
            $payload,
            ['id' => $cardId],
            $formats,
            ['%d']
        );

        $details['flashcards_updated']++;
    } else {
        $payload['created_at'] = current_time('mysql');

        $wpdb->insert(
            $tCards,
            $payload,
            array_merge($formats, ['%s'])
        );

        $cardId = (int)$wpdb->insert_id;
        $details['flashcards_inserted']++;
    }

    if ($cardId <= 0) {
        $details['warnings'][] = "Deck {$deckSlug} : impossible de récupérer l’identifiant d’une carte.";
        return;
    }

    $wpdb->delete($tCardCompetency, ['card_id' => $cardId], ['%d']);

    $competencySlugs = $card['competency_slugs'] ?? [];

    if (!is_array($competencySlugs)) {
        $details['warnings'][] = "Carte {$cardId} : competency_slugs doit être un tableau.";
        return;
    }

    foreach ($competencySlugs as $rawSlug) {
        $competencySlug = sanitize_title((string)$rawSlug);
        $competencyId = self::getCompetencyIdBySlug($wpdb->prefix . 'ouin_exo_', $competencySlug);

        if ($competencyId === null) {
            $details['warnings'][] = "Carte {$cardId} : compétence inconnue {$competencySlug}.";
            continue;
        }

        $wpdb->insert(
            $tCardCompetency,
            [
                'card_id' => $cardId,
                'competency_id' => $competencyId,
            ],
            ['%d', '%d']
        );

        if (empty($wpdb->last_error)) {
            $details['flashcard_competency_links']++;
        } else {
            $details['warnings'][] = "Carte {$cardId} : lien compétence impossible — " . $wpdb->last_error;
        }
    }
}

private static function importPracticalCalls(string $p, int $exerciseId, array $row, array &$details): void
{
    global $wpdb;

    $calls = $row['practical_calls'] ?? [];

    if ($calls === []) {
        return;
    }

    if (!is_array($calls)) {
        $details['warnings'][] = "Exercice {$exerciseId} : practical_calls doit être un tableau.";
        return;
    }

    $table = $p . 'practical_calls';

    foreach ($calls as $index => $call) {
        if (!is_array($call)) {
            $details['warnings'][] = "Exercice {$exerciseId} : appel pratique invalide.";
            continue;
        }

        $callOrder = isset($call['call_order']) ? (int) $call['call_order'] : ((int) $index + 1);
        $callOrder = max(1, min(255, $callOrder));

        $title = sanitize_text_field((string)($call['title'] ?? ''));
        $promptHtml = wp_kses_post((string)($call['prompt_html'] ?? ''));

        if (trim($promptHtml) === '') {
            $details['warnings'][] = "Exercice {$exerciseId} : appel {$callOrder} ignoré, prompt_html manquant.";
            continue;
        }

        $answerMode = sanitize_key((string)($call['answer_mode'] ?? 'code'));
        if (!in_array($answerMode, ['text', 'code', 'mixed'], true)) {
            $answerMode = 'code';
        }

        $maxPoints = null;
        if (isset($call['max_points']) && $call['max_points'] !== null && $call['max_points'] !== '') {
            $maxPoints = (float) $call['max_points'];
        }

        $isActive = isset($call['is_active']) ? (int) $call['is_active'] : 1;
        $isActive = $isActive === 1 ? 1 : 0;

        $payload = [
            'exercise_id' => $exerciseId,
            'call_order' => $callOrder,
            'title' => $title !== '' ? $title : null,
            'prompt_html' => $promptHtml,
            'ai_rubric' => wp_kses_post((string)($call['ai_rubric'] ?? '')),
            'answer_mode' => $answerMode,
            'max_points' => $maxPoints,
            'is_active' => $isActive,
            'updated_at' => current_time('mysql'),
        ];

        $formats = [
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%f',
            '%d',
            '%s',
        ];

        $existingId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE exercise_id = %d
               AND call_order = %d",
            $exerciseId,
            $callOrder
        ));

        if ($existingId) {
            $wpdb->update(
                $table,
                $payload,
                ['id' => (int) $existingId],
                $formats,
                ['%d']
            );

            if (empty($wpdb->last_error)) {
                $details['practical_calls_updated']++;
            } else {
                $details['warnings'][] = "Exercice {$exerciseId} : mise à jour appel {$callOrder} impossible — " . $wpdb->last_error;
            }
        } else {
            $payload['created_at'] = current_time('mysql');

            $wpdb->insert(
                $table,
                $payload,
                array_merge($formats, ['%s'])
            );

            if (empty($wpdb->last_error)) {
                $details['practical_calls_inserted']++;
            } else {
                $details['warnings'][] = "Exercice {$exerciseId} : création appel {$callOrder} impossible — " . $wpdb->last_error;
            }
        }
    }
}

private static function importPracticalFiles(string $p, int $exerciseId, array $row, array &$details): void
{
    global $wpdb;

    $files = $row['practical_files'] ?? [];

    if ($files === []) {
        return;
    }

    if (!is_array($files)) {
        $details['warnings'][] = "Exercice {$exerciseId} : practical_files doit être un tableau.";
        return;
    }

    $tFiles = $p . 'practical_files';
    $tCalls = $p . 'practical_calls';

    foreach ($files as $index => $file) {
        if (!is_array($file)) {
            $details['warnings'][] = "Exercice {$exerciseId} : fichier pratique invalide.";
            continue;
        }

        $label = sanitize_text_field((string)($file['label'] ?? ''));

        if ($label === '') {
            $details['warnings'][] = "Exercice {$exerciseId} : fichier pratique ignoré, label manquant.";
            continue;
        }

        $fileKind = sanitize_key((string)($file['file_kind'] ?? 'starter'));
        if (!in_array($fileKind, ['starter', 'resource', 'subject'], true)) {
            $fileKind = 'starter';
        }

        $fileOrder = isset($file['file_order']) ? (int) $file['file_order'] : ((int) $index + 1);
        $fileOrder = max(1, min(255, $fileOrder));

        $fileUrl = esc_url_raw((string)($file['file_url'] ?? ''));

        $callId = null;
        if (isset($file['practical_call_order']) && $file['practical_call_order'] !== null && $file['practical_call_order'] !== '') {
            $callOrder = (int) $file['practical_call_order'];

            $foundCallId = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tCalls}
                 WHERE exercise_id = %d
                   AND call_order = %d",
                $exerciseId,
                $callOrder
            ));

            if ($foundCallId) {
                $callId = (int) $foundCallId;
            } else {
                $details['warnings'][] = "Exercice {$exerciseId} : fichier {$label}, appel {$callOrder} introuvable.";
            }
        }

        $wpdb->insert(
            $tFiles,
            [
                'exercise_id' => $exerciseId,
                'practical_call_id' => $callId,
                'wp_attachment_id' => null,
                'label' => $label,
                'file_url' => $fileUrl !== '' ? $fileUrl : null,
                'file_kind' => $fileKind,
                'file_order' => $fileOrder,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );

        if (empty($wpdb->last_error)) {
            $details['practical_files_imported']++;
        } else {
            $details['warnings'][] = "Exercice {$exerciseId} : fichier {$label} impossible — " . $wpdb->last_error;
        }
    }
}

}
