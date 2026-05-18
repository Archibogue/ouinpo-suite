<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class PedagogicalPackImporter
{
    private const SUPPORTED_SCHEMA_VERSION = '1.0';

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

    private static function makeDetails(): array
    {
        return [
            'status' => 'pending',
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
            'transaction_used' => false,
            'transaction_started' => false,
            'rollback_performed' => false,
            'warnings' => [],
            'errors' => [],
            'counters' => [],
        ];
    }

    private static function finalizeDetails(array &$details): void
    {
        $counterKeys = [
            'school_levels_inserted',
            'school_levels_updated',
            'domains_inserted',
            'domains_updated',
            'difficulties_inserted',
            'difficulties_updated',
            'competencies_inserted',
            'competencies_updated',
            'competency_school_level_links',
            'exercises_inserted',
            'exercises_updated',
            'exercise_school_level_links',
            'exercise_competency_links',
            'hints_imported',
            'solutions_imported',
            'exam_meta_imported',
            'practical_calls_inserted',
            'practical_calls_updated',
            'practical_files_imported',
            'flashcard_decks_inserted',
            'flashcard_decks_updated',
            'flashcards_inserted',
            'flashcards_updated',
            'flashcard_competency_links',
        ];

        $details['counters'] = [];

        foreach ($counterKeys as $key) {
            $details['counters'][$key] = (int)($details[$key] ?? 0);
        }
    }

    private static function failResult(string $message, array $details): array
    {
        $details['status'] = 'failed';
        self::finalizeDetails($details);

        return [
            'ok' => false,
            'message' => $message,
            'details' => $details,
        ];
    }

    private static function cleanMessage(string $message): string
    {
        $message = wp_strip_all_tags($message);
        $message = preg_replace('/\s+/', ' ', $message);

        return trim((string) $message);
    }

    private static function addWarning(array &$details, string $message): void
    {
        $details['warnings'][] = self::cleanMessage($message);
    }

    private static function addError(array &$details, string $message): void
    {
        $details['errors'][] = self::cleanMessage($message);
    }

    private static function sqlError(string $action, string $table): string
    {
        global $wpdb;

        $error = self::cleanMessage((string) $wpdb->last_error);
        $message = "Erreur SQL pendant {$action} sur {$table}";

        return $error !== '' ? $message . ' : ' . $error : $message . '.';
    }

    private static function insertOrFail(string $table, array $data, array $formats, array &$details, string $context): bool
    {
        global $wpdb;

        $result = $wpdb->insert($table, $data, $formats);

        if ($result !== false) {
            return true;
        }

        self::addError($details, $context . ' - ' . self::sqlError('insertion', $table));
        throw new \RuntimeException('Insertion SQL impossible.');
    }

    private static function updateOrFail(string $table, array $data, array $where, array $formats, array $whereFormats, array &$details, string $context): bool
    {
        global $wpdb;

        $result = $wpdb->update($table, $data, $where, $formats, $whereFormats);

        if ($result !== false) {
            return true;
        }

        self::addError($details, $context . ' - ' . self::sqlError('mise a jour', $table));
        throw new \RuntimeException('Mise a jour SQL impossible.');
    }

    private static function deleteOrFail(string $table, array $where, array $whereFormats, array &$details, string $context): bool
    {
        global $wpdb;

        $result = $wpdb->delete($table, $where, $whereFormats);

        if ($result !== false) {
            return true;
        }

        self::addError($details, $context . ' - ' . self::sqlError('suppression', $table));
        throw new \RuntimeException('Suppression SQL impossible.');
    }

    private static function queryOrFail(string $query, array &$details, string $context): bool
    {
        global $wpdb;

        $result = $wpdb->query($query);

        if ($result !== false) {
            return true;
        }

        self::addError($details, $context . ' - ' . self::sqlError('requete', 'transaction'));
        throw new \RuntimeException('Requete SQL impossible.');
    }

    private static function startTransaction(array &$details): bool
    {
        global $wpdb;

        $details['transaction_used'] = true;
        $result = $wpdb->query('START TRANSACTION');

        if ($result === false) {
            $details['transaction_used'] = false;
            self::addWarning($details, 'Transaction SQL indisponible : import poursuivi sans garantie de rollback.');
            return false;
        }

        $details['transaction_started'] = true;
        return true;
    }

    public static function importFromFile(string $path): array
    {
        $details = self::makeDetails();

        if (!is_readable($path)) {
            self::addError($details, 'Fichier illisible.');
            return self::failResult('Fichier illisible.', $details);
        }

        $raw = file_get_contents($path);

        if ($raw === false || trim($raw) === '') {
            self::addError($details, 'Fichier vide ou impossible a lire.');
            return self::failResult('Fichier vide ou impossible a lire.', $details);
        }


        $data = json_decode($raw, true);

        if (!is_array($data)) {
            self::addError($details, 'JSON invalide : ' . json_last_error_msg());
            return self::failResult('JSON invalide : ' . json_last_error_msg(), $details);
        }


        return self::importFromArray($data);
    }

    public static function importFromArray(array $data): array
    {
        global $wpdb;

        $details = self::makeDetails();
        $data = self::normalizePackArrays($data, $details);

        if (!empty($details['errors'])) {
            return self::failResult('Pack refuse avant import.', $details);
        }

        self::prevalidatePack($data, $details);

        if (!empty($details['errors'])) {
            return self::failResult('Pack refuse avant import.', $details);
        }

        $p = $wpdb->prefix . 'ouin_exo_';
        $transactionStarted = self::startTransaction($details);

        try {
            self::importSchoolLevels($p, $data['school_levels'], $details);
            self::importDomains($p, $data['domains'], $details);
            self::importDifficulties($p, $data['difficulties'], $details);
            self::importCompetencies($p, $data['competencies'], $details);
            self::importExercises($p, $data['exercises'], $details);
            self::importFlashcards($data['flashcards'], $details);

            if ($transactionStarted) {
                self::queryOrFail('COMMIT', $details, 'Commit transaction import pack');
            }

            $details['status'] = !empty($details['warnings']) ? 'partial' : 'success';
            self::finalizeDetails($details);

            return [
                'ok' => true,
                'message' => $details['status'] === 'partial'
                    ? 'Pack importe avec avertissements.'
                    : 'Pack importe.',
                'details' => $details,
            ];
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $rollback = $wpdb->query('ROLLBACK');
                $details['rollback_performed'] = $rollback !== false;

                if ($rollback === false) {
                    self::addWarning($details, 'Rollback SQL demande mais non confirme : ' . self::cleanMessage((string) $wpdb->last_error));
                }
            }

            if (empty($details['errors'])) {
                self::addError($details, 'Import interrompu : ' . $e->getMessage());
            }

            return self::failResult('Import annule.', $details);
        }

    }

    private static function normalizePackArrays(array $data, array &$details): array
    {
        $schemaVersion = (string)($data['schema_version'] ?? '');

        if ($schemaVersion !== self::SUPPORTED_SCHEMA_VERSION) {
            self::addError($details, 'Version de schema non supportee : ' . ($schemaVersion !== '' ? $schemaVersion : 'absente') . '.');
            return $data;
        }

        if (!isset($data['pack']) || !is_array($data['pack'])) {
            self::addError($details, 'Metadonnees du pack absentes ou invalides.');
            return $data;
        }

        if (sanitize_title((string)($data['pack']['slug'] ?? '')) === '') {
            self::addError($details, 'pack.slug est obligatoire.');
        }

        if (trim((string)($data['pack']['title'] ?? '')) === '') {
            self::addError($details, 'pack.title est obligatoire.');
        }

        foreach (['school_levels', 'domains', 'difficulties', 'competencies', 'exercises', 'flashcards', 'badges'] as $key) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = [];
                continue;
            }

            if (!is_array($data[$key])) {
                self::addError($details, "Le champ {$key} doit etre un tableau.");
            }
        }

        return $data;
    }

    private static function prevalidatePack(array $data, array &$details): void
    {
        $p = $GLOBALS['wpdb']->prefix . 'ouin_exo_';
        $levelSlugs = self::collectKnownSlugs($data['school_levels'], 'slug', 'school_levels', $details);
        $difficultySlugs = self::collectKnownSlugs($data['difficulties'], 'slug', 'difficulties', $details);
        $competencySlugs = self::collectKnownSlugs($data['competencies'], 'slug', 'competencies', $details, true);

        foreach ($data['exercises'] as $index => $row) {
            if (!is_array($row)) {
                self::addError($details, "Exercice #{$index} invalide : entree non objet.");
                continue;
            }

            $slug = sanitize_title((string)($row['slug'] ?? ''));
            $label = $slug !== '' ? $slug : '#' . (string)($index + 1);
            $title = trim((string)($row['title'] ?? ''));
            $statement = trim(wp_strip_all_tags((string)($row['statement'] ?? '')));

            if ($slug === '') {
                self::addError($details, "Exercice {$label} : slug manquant.");
            }

            if ($title === '') {
                self::addError($details, "Exercice {$label} : titre manquant.");
            }

            if ($statement === '') {
                self::addError($details, "Exercice {$label} : enonce manquant.");
            }

            $levelSlug = sanitize_key((string)($row['level_slug'] ?? ''));
            if ($levelSlug !== '' && !isset($levelSlugs[$levelSlug]) && self::getSchoolLevelIdBySlug($p, $levelSlug) === null) {
                self::addError($details, "Exercice {$label} : niveau inconnu ({$levelSlug}).");
            }

            if (!empty($row['level_slugs'])) {
                if (!is_array($row['level_slugs'])) {
                    self::addError($details, "Exercice {$label} : level_slugs doit etre un tableau.");
                } else {
                    foreach ($row['level_slugs'] as $rawLevelSlug) {
                        $extraLevelSlug = sanitize_key((string) $rawLevelSlug);
                        if ($extraLevelSlug !== '' && !isset($levelSlugs[$extraLevelSlug]) && self::getSchoolLevelIdBySlug($p, $extraLevelSlug) === null) {
                            self::addError($details, "Exercice {$label} : niveau inconnu ({$extraLevelSlug}).");
                        }
                    }
                }
            }

            $difficultySlug = sanitize_key((string)($row['difficulty_slug'] ?? ''));
            if ($difficultySlug !== '' && !isset($difficultySlugs[$difficultySlug]) && self::getDifficultyIdBySlug($p, $difficultySlug) === null) {
                self::addError($details, "Exercice {$label} : difficulte inconnue ({$difficultySlug}).");
            }

            if (isset($row['competency_slugs']) && !is_array($row['competency_slugs'])) {
                self::addError($details, "Exercice {$label} : competency_slugs doit etre un tableau.");
            }

            foreach ((array)($row['competency_slugs'] ?? []) as $rawCompetencySlug) {
                $competencySlug = sanitize_title((string)$rawCompetencySlug);
                if ($competencySlug !== '' && !isset($competencySlugs[$competencySlug]) && self::getCompetencyIdBySlug($p, $competencySlug) === null) {
                    self::addError($details, "Exercice {$label} : competence inconnue ({$competencySlug}).");
                }
            }

            if (($row['exam_meta']['exam_type'] ?? '') === 'practical_subject') {
                self::prevalidatePracticalSubject($row, $label, $details);
            }

            foreach (['hints', 'solutions', 'practical_calls', 'practical_files'] as $childKey) {
                if (isset($row[$childKey]) && !is_array($row[$childKey])) {
                    self::addError($details, "Exercice {$label} : {$childKey} doit etre un tableau.");
                }
            }
        }

        foreach ($data['flashcards'] as $groupIndex => $group) {
            if (!is_array($group)) {
                self::addError($details, "Groupe de flashcards #{$groupIndex} invalide.");
                continue;
            }

            $deck = $group['deck'] ?? null;
            if (!is_array($deck)) {
                self::addError($details, "Groupe de flashcards #{$groupIndex} : deck manquant ou invalide.");
                continue;
            }

            $deckSlug = sanitize_title((string)($deck['slug'] ?? ''));
            $deckLabel = $deckSlug !== '' ? $deckSlug : '#' . (string)($groupIndex + 1);
            if ($deckSlug === '' || trim((string)($deck['title'] ?? '')) === '') {
                self::addError($details, "Deck {$deckLabel} : slug ou titre manquant.");
            }

            if (!isset($group['cards']) || !is_array($group['cards'])) {
                self::addError($details, "Deck {$deckLabel} : cards doit etre un tableau.");
                continue;
            }

            foreach ($group['cards'] as $cardIndex => $card) {
                if (!is_array($card)) {
                    self::addError($details, "Deck {$deckLabel} : carte #{$cardIndex} invalide.");
                    continue;
                }

                if (trim(wp_strip_all_tags((string)($card['front_html'] ?? ''))) === '') {
                    self::addError($details, "Deck {$deckLabel} : carte #{$cardIndex} sans recto.");
                }

                if (trim(wp_strip_all_tags((string)($card['back_html'] ?? ''))) === '') {
                    self::addError($details, "Deck {$deckLabel} : carte #{$cardIndex} sans verso.");
                }
            }
        }

    }

    private static function collectKnownSlugs(array $rows, string $field, string $section, array &$details, bool $titleSanitize = false): array
    {
        $slugs = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                self::addError($details, "{$section} #{$index} invalide : entree non objet.");
                continue;
            }

            $slug = $titleSanitize
                ? sanitize_title((string)($row[$field] ?? ''))
                : sanitize_key((string)($row[$field] ?? ''));

            if ($slug !== '') {
                $slugs[$slug] = true;
            }
        }

        return $slugs;
    }

    private static function prevalidatePracticalSubject(array $row, string $label, array &$details): void
    {
        $calls = $row['practical_calls'] ?? [];

        if (!is_array($calls) || empty($calls)) {
            self::addError($details, "Sujet pratique {$label} : practical_calls absent ou invalide.");
            return;
        }

        foreach ($calls as $index => $call) {
            if (!is_array($call) || trim(wp_strip_all_tags((string)($call['prompt_html'] ?? ''))) === '') {
                self::addError($details, "Sujet pratique {$label} : appel #" . ((int)$index + 1) . ' incomplet.');
            }
        }
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
                self::updateOrFail(
                    $table,
                    [
                        'label' => $label,
                        'sort_order' => max(0, $sortOrder),
                    ],
                    ['slug' => $slug],
                    ['%s', '%d'],
                    ['%s'],
                    $details,
                    "Niveau {$slug}"
                );

                $details['school_levels_updated']++;
            } else {
                self::insertOrFail(
                    $table,
                    [
                        'slug' => $slug,
                        'label' => $label,
                        'sort_order' => max(0, $sortOrder),
                    ],
                    ['%s', '%s', '%d'],
                    $details,
                    "Niveau {$slug}"
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
                $details['warnings'][] = 'Domaine ignoré : entrée invalide.';
                continue;
            }

            $slug = sanitize_key((string)($row['slug'] ?? ($row['domain_slug'] ?? '')));
            $label = sanitize_text_field((string)($row['label'] ?? ($row['domain'] ?? '')));
            $track = strtoupper(sanitize_text_field((string)($row['track'] ?? '')));
            $description = wp_kses_post((string)($row['description'] ?? ''));
            $sortOrder = isset($row['sort_order']) ? (int)$row['sort_order'] : (int)($row['rank'] ?? 0);
            $active = isset($row['active']) ? ((int)$row['active'] === 1 ? 1 : 0) : 1;

            if ($slug === '' || $label === '') {
                $details['warnings'][] = 'Domaine ignoré : slug ou libellé manquant.';
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
                $updated = self::updateOrFail(
                    $table,
                    $payload,
                    ['id' => $existingId],
                    ['%s', '%s', '%s', '%s', '%d', '%d'],
                    ['%d'],
                    $details,
                    "Domaine {$slug}"
                );

                if ($updated === false) {
                    $details['warnings'][] = 'Domaine ' . $slug . ' : mise à jour impossible — ' . $wpdb->last_error;
                } else {
                    $details['domains_updated']++;
                }
            } else {
                $inserted = self::insertOrFail(
                    $table,
                    $payload,
                    ['%s', '%s', '%s', '%s', '%d', '%d'],
                    $details,
                    "Domaine {$slug}"
                );

                if ($inserted === false) {
                    $details['warnings'][] = 'Domaine ' . $slug . ' : création impossible — ' . $wpdb->last_error;
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
            self::updateOrFail(
                $table,
                ['label' => $label],
                ['id' => $id],
                ['%s'],
                ['%d'],
                $details,
                "Domaine {$slug}"
            );

            return $id;
        }

        $inserted = self::insertOrFail(
            $table,
            [
                'slug' => $slug,
                'label' => $label,
                'track' => $track,
                'description' => null,
                'sort_order' => 0,
                'active' => 1,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d'],
            $details,
            "Domaine {$slug}"
        );

        if ($inserted === false) {
            $details['warnings'][] = 'Domaine ' . $slug . ' : création automatique impossible — ' . $wpdb->last_error;
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
                self::updateOrFail(
                    $table,
                    ['label' => $label],
                    ['slug' => $slug],
                    ['%s'],
                    ['%s'],
                    $details,
                    "Difficulte {$slug}"
                );

                $details['difficulties_updated']++;
            } else {
                self::insertOrFail(
                    $table,
                    [
                        'slug' => $slug,
                        'label' => $label,
                    ],
                    ['%s', '%s'],
                    $details,
                    "Difficulte {$slug}"
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
                $updated = self::updateOrFail(
                    $table,
                    $payload,
                    ['slug' => $slug],
                    $formats,
                    ['%s'],
                    $details,
                    "Competence {$slug}"
                );

                if ($updated === false) {
                    $details['warnings'][] = 'Compétence ' . $slug . ' : mise à jour impossible — ' . $wpdb->last_error;
                } else {
                    $details['competencies_updated']++;
                    $competencyId = (int) $existingId;
                }
            } else {
                $inserted = self::insertOrFail(
                    $table,
                    $payload,
                    $formats,
                    $details,
                    "Competence {$slug}"
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
                    $details['warnings'][] = "Compétence {$competencyId} : niveau inconnu ({$levelSlug}).";
                }
            }
        }

        if (!empty($row['level_slug'])) {
            $levelSlug = sanitize_key((string) $row['level_slug']);
            $levelId = self::getSchoolLevelIdBySlug($p, $levelSlug);
            if ($levelId !== null) {
                $levelIds[] = $levelId;
            } elseif ($levelSlug !== '') {
                $details['warnings'][] = "Compétence {$competencyId} : niveau inconnu ({$levelSlug}).";
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

        self::deleteOrFail($table, ['competency_id' => $competencyId], ['%d'], $details, "Competence {$competencyId} niveaux");

        foreach ($levelIds as $levelId) {
            self::insertOrFail(
                $table,
                [
                    'competency_id'   => $competencyId,
                    'school_level_id' => $levelId,
                ],
                ['%d', '%d'],
                $details,
                "Competence {$competencyId} niveau {$levelId}"
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

        $hasExplicitIsActive = array_key_exists('is_active', $row);
        $isActive = $hasExplicitIsActive ? (int)$row['is_active'] : 1;
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

        if (!$hasExplicitIsActive && $existingId) {
            $existingIsActive = $wpdb->get_var($wpdb->prepare(
                "SELECT is_active FROM {$tExercises} WHERE id = %d",
                (int) $existingId
            ));
            $isActive = (int) $existingIsActive === 1 ? 1 : 0;
            $payload['is_active'] = $isActive;
        }

        if ($existingId) {
            $exerciseId = (int)$existingId;

            self::updateOrFail(
                $tExercises,
                $payload,
                ['id' => $exerciseId],
                $formats,
                ['%d'],
                $details,
                "Exercice {$slug}"
            );

            $details['exercises_updated']++;
        } else {
            $payload['created_at'] = current_time('mysql');

            self::insertOrFail(
                $tExercises,
                $payload,
                array_merge($formats, ['%s']),
                $details,
                "Exercice {$slug}"
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
        self::deleteOrFail($tExerciseSchoolLevel, ['exercise_id' => $exerciseId], ['%d'], $details, "Exercice {$slug} liens niveaux");
        self::deleteOrFail($tExerciseCompetency, ['exercise_id' => $exerciseId], ['%d'], $details, "Exercice {$slug} liens competences");
        self::deleteOrFail($tHints, ['exercise_id' => $exerciseId], ['%d'], $details, "Exercice {$slug} indices");
        self::deleteOrFail($tSolutions, ['exercise_id' => $exerciseId], ['%d'], $details, "Exercice {$slug} solutions");
        self::deleteOrFail($tExamMeta, ['exercise_id' => $exerciseId], ['%d'], $details, "Exercice {$slug} exam_meta");
        self::deleteOrFail($tPracticalFiles, ['exercise_id' => $exerciseId], ['%d'], $details, "Exercice {$slug} fichiers pratiques");

        
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
        // Affichage herite uniquement : "Transversal" n'est jamais une source de verite.
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

        self::insertOrFail(
            $tExerciseCompetency,
            [
                'exercise_id' => $exerciseId,
                'competency_id' => $competencyId,
            ],
            ['%d', '%d'],
            $details,
            "Exercice {$exerciseId} competence {$competencySlug}"
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

        self::insertOrFail(
            $tHints,
            [
                'exercise_id' => $exerciseId,
                'hint_order' => $order,
                'content' => $content,
            ],
            ['%d', '%d', '%s'],
            $details,
            "Exercice {$exerciseId} indice {$order}"
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

        self::insertOrFail(
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
            ['%d', '%s', '%s', '%d', '%d', '%s', '%s'],
            $details,
            "Exercice {$exerciseId} solution {$order}"
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

        self::insertOrFail(
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
            ],
            $details,
            "Exercice {$exerciseId} exam_meta"
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
        self::insertOrFail(
            $table,
            [
                'exercise_id' => $exerciseId,
                'school_level_id' => $levelId,
            ],
            ['%d', '%d'],
            $details,
            "Exercice {$exerciseId} niveau {$levelId}"
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

            self::updateOrFail(
                $tDecks,
                $deckPayload,
                ['id' => $deckId],
                $deckFormats,
                ['%d'],
                $details,
                "Deck {$deckSlug}"
            );

            $details['flashcard_decks_updated']++;
        } else {
            $deckPayload['created_at'] = current_time('mysql');

            self::insertOrFail(
                $tDecks,
                $deckPayload,
                array_merge($deckFormats, ['%s']),
                $details,
                "Deck {$deckSlug}"
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

        self::updateOrFail(
            $tCards,
            $payload,
            ['id' => $cardId],
            $formats,
            ['%d'],
            $details,
            "Deck {$deckSlug} carte {$sortOrder}"
        );

        $details['flashcards_updated']++;
    } else {
        $payload['created_at'] = current_time('mysql');

        self::insertOrFail(
            $tCards,
            $payload,
            array_merge($formats, ['%s']),
            $details,
            "Deck {$deckSlug} carte {$sortOrder}"
        );

        $cardId = (int)$wpdb->insert_id;
        $details['flashcards_inserted']++;
    }

    if ($cardId <= 0) {
        $details['warnings'][] = "Deck {$deckSlug} : impossible de récupérer l’identifiant d’une carte.";
        return;
    }

    self::deleteOrFail($tCardCompetency, ['card_id' => $cardId], ['%d'], $details, "Carte {$cardId} liens competences");

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

        self::insertOrFail(
            $tCardCompetency,
            [
                'card_id' => $cardId,
                'competency_id' => $competencyId,
            ],
            ['%d', '%d'],
            $details,
            "Carte {$cardId} competence {$competencySlug}"
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
            self::updateOrFail(
                $table,
                $payload,
                ['id' => (int) $existingId],
                $formats,
                ['%d'],
                $details,
                "Exercice {$exerciseId} appel pratique {$callOrder}"
            );

            if (empty($wpdb->last_error)) {
                $details['practical_calls_updated']++;
            } else {
                $details['warnings'][] = "Exercice {$exerciseId} : mise à jour appel {$callOrder} impossible — " . $wpdb->last_error;
            }
        } else {
            $payload['created_at'] = current_time('mysql');

            self::insertOrFail(
                $table,
                $payload,
                array_merge($formats, ['%s']),
                $details,
                "Exercice {$exerciseId} appel pratique {$callOrder}"
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

        self::insertOrFail(
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
            ],
            $details,
            "Exercice {$exerciseId} fichier pratique {$label}"
        );

        if (empty($wpdb->last_error)) {
            $details['practical_files_imported']++;
        } else {
            $details['warnings'][] = "Exercice {$exerciseId} : fichier {$label} impossible — " . $wpdb->last_error;
        }
    }
}

}
