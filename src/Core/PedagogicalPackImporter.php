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

    private static function cleanMessage(string $message): string
    {
        $message = wp_strip_all_tags($message);
        $message = preg_replace('/\s+/', ' ', $message);
        return trim((string) $message);
    }

    private static function contextLabel(array $context): string
    {
        $parts = [];
        foreach (['step', 'object', 'slug', 'table'] as $key) {
            if (!isset($context[$key]) || $context[$key] === '') {
                continue;
            }
            $parts[] = $key . '=' . sanitize_text_field((string) $context[$key]);
        }

        return $parts ? ' (' . implode(', ', $parts) . ')' : '';
    }

    private static function recordWarning(array &$details, string $message, array $context = []): void
    {
        $details['warnings'][] = self::cleanMessage($message . self::contextLabel($context));
    }

    private static function recordError(array &$details, string $message, array $context = []): void
    {
        $details['errors'][] = self::cleanMessage($message . self::contextLabel($context));
    }

    private static function hasBlockingErrors(array $details): bool
    {
        return !empty($details['errors']);
    }

    private static function sqlWriteErrorMessage(string $action, string $table): string
    {
        global $wpdb;

        $lastError = self::cleanMessage((string) $wpdb->last_error);
        $message = "Erreur SQL lors de {$action} dans la table {$table}";

        return $lastError !== '' ? $message . ' : ' . $lastError : $message . '.';
    }

    private static function insertOrFail(string $table, array $data, array $formats, array &$details, array $context = [], bool $blocking = true): bool
    {
        global $wpdb;

        $result = $wpdb->insert($table, $data, $formats);
        if ($result !== false) {
            return true;
        }

        $message = self::sqlWriteErrorMessage('de l\'insertion', $table);
        if ($blocking) {
            self::recordError($details, $message, $context + ['table' => $table]);
            throw new \RuntimeException('Insertion SQL indispensable impossible.');
        }

        self::recordWarning($details, $message, $context + ['table' => $table]);
        return false;
    }

    private static function updateOrFail(string $table, array $data, array $where, array $formats, array $whereFormats, array &$details, array $context = [], bool $blocking = true): bool
    {
        global $wpdb;

        $result = $wpdb->update($table, $data, $where, $formats, $whereFormats);
        if ($result !== false) {
            return true;
        }

        $message = self::sqlWriteErrorMessage('de la mise a jour', $table);
        if ($blocking) {
            self::recordError($details, $message, $context + ['table' => $table]);
            throw new \RuntimeException('Mise a jour SQL indispensable impossible.');
        }

        self::recordWarning($details, $message, $context + ['table' => $table]);
        return false;
    }

    private static function queryOrFail(string $query, array &$details, array $context = [], bool $blocking = true): bool
    {
        global $wpdb;

        $result = $wpdb->query($query);
        if ($result !== false) {
            return true;
        }

        $message = self::sqlWriteErrorMessage('de la requete', (string) ($context['table'] ?? ''));
        if ($blocking) {
            self::recordError($details, $message, $context);
            throw new \RuntimeException('Requete SQL indispensable impossible.');
        }

        self::recordWarning($details, $message, $context);
        return false;
    }

    private static function deleteOrFail(string $table, array $where, array $whereFormats, array &$details, array $context = [], bool $blocking = true): bool
    {
        global $wpdb;

        $result = $wpdb->delete($table, $where, $whereFormats);
        if ($result !== false) {
            return true;
        }

        $message = self::sqlWriteErrorMessage('de la suppression', $table);
        if ($blocking) {
            self::recordError($details, $message, $context + ['table' => $table]);
            throw new \RuntimeException('Suppression SQL indispensable impossible.');
        }

        self::recordWarning($details, $message, $context + ['table' => $table]);
        return false;
    }

    public static function importFromFile(string $path): array
    {
        if (!is_readable($path)) {
            return [
                'ok' => false,
                'message' => 'Fichier illisible.',
                'details' => [
                    'import_status' => 'failed',
                    'rollback_performed' => 0,
                    'errors' => ['Fichier illisible.'],
                    'warnings' => [],
                ],
            ];
        }

        $raw = file_get_contents($path);

        if ($raw === false || trim($raw) === '') {
            return [
                'ok' => false,
                'message' => 'Fichier vide ou impossible a lire.',
                'details' => [
                    'import_status' => 'failed',
                    'rollback_performed' => 0,
                    'errors' => ['Fichier vide ou impossible a lire.'],
                    'warnings' => [],
                ],
            ];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return [
                'ok' => false,
                'message' => 'JSON invalide : ' . json_last_error_msg(),
                'details' => [
                    'import_status' => 'failed',
                    'rollback_performed' => 0,
                    'errors' => ['JSON invalide : ' . json_last_error_msg()],
                    'warnings' => [],
                ],
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
            'hints_inserted' => 0,
            'hints_updated' => 0,
            'hints_unchanged' => 0,
            'hints_errors' => 0,
            'solutions_imported' => 0,
            'solutions_inserted' => 0,
            'solutions_updated' => 0,
            'solutions_errors' => 0,
            'exam_meta_imported' => 0,
            'exam_meta_inserted' => 0,
            'exam_meta_updated' => 0,
            'exam_meta_errors' => 0,
            'practical_subjects_inserted' => 0,
            'practical_subjects_updated' => 0,
            'practical_calls_inserted' => 0,
            'practical_calls_updated' => 0,
            'practical_files_imported' => 0,
            'flashcard_decks_inserted' => 0,
            'flashcard_decks_updated' => 0,
            'flashcards_inserted' => 0,
            'flashcards_updated' => 0,
            'flashcard_competency_links' => 0,
            'flashcard_competency_link_errors' => 0,
            'transaction_started' => 0,
            'transaction_used' => 0,
            'rollback_performed' => 0,
            'import_status' => 'pending',
            'warnings' => [],
            'errors' => [],
        ];

        $schemaVersion = (string)($data['schema_version'] ?? '');

        if ($schemaVersion !== '1.0') {
            $details['import_status'] = 'failed';
            $details['errors'][] = 'Version de schema non supportee.';
            return [
                'ok' => false,
                'message' => 'Version de schema non supportee : ' . ($schemaVersion !== '' ? $schemaVersion : 'absente'),
                'details' => $details,
            ];
        }

        if (!isset($data['pack']) || !is_array($data['pack'])) {
            $details['import_status'] = 'failed';
            $details['errors'][] = 'Metadonnees du pack absentes.';
            return [
                'ok' => false,
                'message' => 'Metadonnees du pack absentes.',
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
                $details['import_status'] = 'failed';
                $details['errors'][] = "Le champ {$key} doit etre un tableau.";
                return [
                    'ok' => false,
                    'message' => "Le champ {$key} doit etre un tableau.",
                    'details' => $details,
                ];
            }
        }

        $p = $wpdb->prefix . 'ouin_exo_';

        $transaction = self::beginTransaction($details);

        try {
            self::importSchoolLevels($p, $data['school_levels'], $details);
            self::importDomains($p, $data['domains'], $details);
            self::importDifficulties($p, $data['difficulties'], $details);
            self::importCompetencies($p, $data['competencies'], $details);
            self::importExercises($p, $data['exercises'], $details);
            self::importFlashcards($data['flashcards'], $details);

            if (self::hasBlockingErrors($details)) {
                throw new \RuntimeException('Import interrompu par erreurs bloquantes.');
            }

            if ($transaction) {
                self::queryOrFail('COMMIT', $details, ['step' => 'transaction'], true);
            }

            $details['import_status'] = !empty($details['warnings']) ? 'partial' : 'success';

            return [
                'ok' => true,
                'message' => $details['import_status'] === 'partial'
                    ? 'Pack importe avec avertissements.'
                    : 'Pack importe.',
                'details' => $details,
            ];
        } catch (\Throwable $e) {
            if ($transaction) {
                if (self::queryOrFail('ROLLBACK', $details, ['step' => 'transaction', 'table' => 'transaction'], false)) {
                    $details['rollback_performed'] = 1;
                }
            }

            $details['import_status'] = 'failed';
            self::recordError($details, 'Import interrompu : ' . $e->getMessage());

            return [
                'ok' => false,
                'message' => 'Import annule.',
                'details' => $details,
            ];
        }
    }

    private static function beginTransaction(array &$details): bool
    {
        if (!self::queryOrFail('START TRANSACTION', $details, ['step' => 'transaction', 'table' => 'transaction'], false)) {
            self::recordWarning($details, 'Transaction SQL indisponible : import effectue sans rollback automatique.');
            return false;
        }

        $details['transaction_started'] = 1;
        $details['transaction_used'] = 1;
        return true;
    }

    private static function blockingWarnings(array $warnings): array
    {
        $blocking = [];

        foreach ($warnings as $warning) {
            $message = (string) $warning;
            $normalized = function_exists('remove_accents')
                ? strtolower(remove_accents($message))
                : strtolower($message);

            if (str_contains($normalized, 'transaction sql indisponible')) {
                continue;
            }

            foreach (['inconnu', 'inconnue', 'impossible', 'sql', 'identifiant apres import'] as $needle) {
                if (str_contains($normalized, $needle)) {
                    $blocking[] = $message;
                    break;
                }
            }
        }

        return $blocking;
    }

    private static function importSchoolLevels(string $p, array $rows, array &$details): void
    {
        global $wpdb;

        $table = $p . 'school_levels';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                self::recordWarning($details, 'Niveau ignore : entree invalide.', ['step' => 'school_levels']);
                continue;
            }

            $slug = sanitize_key((string)($row['slug'] ?? ''));
            $label = sanitize_text_field((string)($row['label'] ?? ''));
            $sortOrder = isset($row['sort_order']) ? (int) $row['sort_order'] : (isset($row['rank']) ? (int) $row['rank'] : 0);

            if ($slug === '' || $label === '') {
                self::recordWarning($details, 'Niveau ignore : slug ou label manquant.', ['step' => 'school_levels']);
                continue;
            }

            $existingId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));

            if ($existingId) {
                if (self::updateOrFail($table, ['label' => $label, 'sort_order' => max(0, $sortOrder)], ['slug' => $slug], ['%s', '%d'], ['%s'], $details, ['step' => 'school_levels', 'object' => 'niveau', 'slug' => $slug])) {
                    $details['school_levels_updated']++;
                }
            } else {
                if (self::insertOrFail($table, ['slug' => $slug, 'label' => $label, 'sort_order' => max(0, $sortOrder)], ['%s', '%s', '%d'], $details, ['step' => 'school_levels', 'object' => 'niveau', 'slug' => $slug])) {
                    $details['school_levels_inserted']++;
                }
            }
        }
    }

    private static function importDomains(string $p, array $rows, array &$details): void
    {
        global $wpdb;

        $table = $p . 'domains';

        if (!self::tableExists($table) && $rows === []) {
            return;
        }

        if (!self::tableExists($table)) {
            self::recordError($details, 'Table des domaines indisponible.', ['step' => 'domains', 'table' => $table]);
            throw new \RuntimeException('Table des domaines indisponible.');
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                self::recordWarning($details, 'Domaine ignore : entree invalide.', ['step' => 'domains']);
                continue;
            }

            $slug = sanitize_key((string)($row['slug'] ?? ($row['domain_slug'] ?? '')));
            $label = sanitize_text_field((string)($row['label'] ?? ($row['domain'] ?? '')));
            $track = strtoupper(sanitize_text_field((string)($row['track'] ?? '')));
            $description = wp_kses_post((string)($row['description'] ?? ''));
            $sortOrder = isset($row['sort_order']) ? (int)$row['sort_order'] : (int)($row['rank'] ?? 0);
            $active = isset($row['active']) ? ((int)$row['active'] === 1 ? 1 : 0) : 1;

            if ($slug === '' || $label === '') {
                self::recordWarning($details, 'Domaine ignore : slug ou libelle manquant.', ['step' => 'domains']);
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

            $existingId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s AND track = %s", $slug, $track));

            if ($existingId > 0) {
                if (self::updateOrFail($table, $payload, ['id' => $existingId], ['%s', '%s', '%s', '%s', '%d', '%d'], ['%d'], $details, ['step' => 'domains', 'object' => 'domaine', 'slug' => $slug])) {
                    $details['domains_updated']++;
                }
            } else {
                if (self::insertOrFail($table, $payload, ['%s', '%s', '%s', '%s', '%d', '%d'], $details, ['step' => 'domains', 'object' => 'domaine', 'slug' => $slug])) {
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

        $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s AND track = %s", $slug, $track));

        if ($id > 0) {
            self::updateOrFail($table, ['label' => $label], ['id' => $id], ['%s'], ['%d'], $details, ['step' => 'domains', 'object' => 'domaine', 'slug' => $slug]);
            return $id;
        }

        self::insertOrFail(
            $table,
            ['slug' => $slug, 'label' => $label, 'track' => $track, 'description' => null, 'sort_order' => 0, 'active' => 1],
            ['%s', '%s', '%s', '%s', '%d', '%d'],
            $details,
            ['step' => 'domains', 'object' => 'domaine', 'slug' => $slug]
        );

        $details['domains_inserted']++;
        return (int) $wpdb->insert_id;
    }

    private static function importDifficulties(string $p, array $rows, array &$details): void
    {
        global $wpdb;

        $table = $p . 'difficulties';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                self::recordWarning($details, 'Difficulte ignoree : entree invalide.', ['step' => 'difficulties']);
                continue;
            }

            $slug = sanitize_key((string)($row['slug'] ?? ''));
            $label = sanitize_text_field((string)($row['label'] ?? ''));

            if ($slug === '' || $label === '') {
                self::recordWarning($details, 'Difficulte ignoree : slug ou label manquant.', ['step' => 'difficulties']);
                continue;
            }

            $existingId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));

            if ($existingId) {
                if (self::updateOrFail($table, ['label' => $label], ['slug' => $slug], ['%s'], ['%s'], $details, ['step' => 'difficulties', 'object' => 'difficulte', 'slug' => $slug])) {
                    $details['difficulties_updated']++;
                }
            } else {
                if (self::insertOrFail($table, ['slug' => $slug, 'label' => $label], ['%s', '%s'], $details, ['step' => 'difficulties', 'object' => 'difficulte', 'slug' => $slug])) {
                    $details['difficulties_inserted']++;
                }
            }
        }
    }

    private static function importCompetencies(string $p, array $rows, array &$details): void
    {
        global $wpdb;

        $table = $p . 'competencies';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                self::recordWarning($details, 'Competence ignoree : entree invalide.', ['step' => 'competencies']);
                continue;
            }

            $slug = sanitize_title((string)($row['slug'] ?? ''));
            if ($slug === '') {
                self::recordWarning($details, 'Competence ignoree : slug manquant.', ['step' => 'competencies']);
                continue;
            }

            $track = strtoupper(sanitize_text_field((string)($row['track'] ?? 'NSI')));
            $track = $track !== '' ? substr($track, 0, 50) : 'NSI';
            $rawLevel = sanitize_text_field((string)($row['level'] ?? ''));
            $level = $rawLevel !== '' ? $rawLevel : self::displayLevelFromRow($p, $row);
            $domain = sanitize_text_field((string)($row['domain'] ?? ''));
            $domainSlug = sanitize_key((string)($row['domain_slug'] ?? ''));
            $competency = wp_kses_post((string)($row['competency'] ?? ''));

            if ($domain === '' || $domainSlug === '' || trim($competency) === '') {
                self::recordWarning($details, 'Competence ignoree : domaine, domaine_slug ou competence manquant.', ['step' => 'competencies', 'object' => 'competence', 'slug' => $slug]);
                continue;
            }

            $domainId = self::ensureDomain($p, $domainSlug, $domain, $track, $details);
            if ($domainId === null && self::tableExists($p . 'domains')) {
                self::recordError($details, 'Domaine reference introuvable ou impossible a creer.', ['step' => 'competencies', 'object' => 'competence', 'slug' => $slug]);
                throw new \RuntimeException('Domaine reference introuvable.');
            }

            $capacity = wp_kses_post((string)($row['capacity'] ?? ''));
            $example = wp_kses_post((string)($row['example'] ?? ''));
            $referenceUrl = esc_url_raw((string)($row['reference_url'] ?? ''));
            $active = isset($row['active']) ? (int)$row['active'] : 1;
            $active = $active === 1 ? 1 : 0;
            $cycle = sanitize_key((string)($row['cycle'] ?? ''));
            $label = trim(wp_strip_all_tags($domain . ' - ' . $competency));

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
            $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'];

            if ($domainId !== null && self::hasColumn($table, 'domain_id')) {
                $payload = ['domain_id' => $domainId] + $payload;
                array_unshift($formats, '%d');
            }

            $existingId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
            $competencyId = 0;

            if ($existingId) {
                if (self::updateOrFail($table, $payload, ['slug' => $slug], $formats, ['%s'], $details, ['step' => 'competencies', 'object' => 'competence', 'slug' => $slug])) {
                    $details['competencies_updated']++;
                    $competencyId = (int) $existingId;
                }
            } else {
                if (self::insertOrFail($table, $payload, $formats, $details, ['step' => 'competencies', 'object' => 'competence', 'slug' => $slug])) {
                    $details['competencies_inserted']++;
                    $competencyId = (int) $wpdb->insert_id;
                }
            }

            if ($competencyId <= 0) {
                self::recordError($details, 'Identifiant de competence introuvable apres import.', ['step' => 'competencies', 'object' => 'competence', 'slug' => $slug]);
                throw new \RuntimeException('Identifiant de competence introuvable apres import.');
            }

            self::syncCompetencySchoolLevelLinks($p, $competencyId, $row, $level, $details);
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
                    self::recordError($details, 'Niveau reference inconnu.', ['step' => 'competency_school_levels', 'object' => 'competence', 'slug' => (string) $competencyId]);
                    throw new \RuntimeException('Niveau reference inconnu.');
                }
            }
        }

        if (!empty($row['level_slug'])) {
            $levelSlug = sanitize_key((string) $row['level_slug']);
            $levelId = self::getSchoolLevelIdBySlug($p, $levelSlug);
            if ($levelId !== null) {
                $levelIds[] = $levelId;
            } elseif ($levelSlug !== '') {
                self::recordError($details, 'Niveau reference inconnu.', ['step' => 'competency_school_levels', 'object' => 'competence', 'slug' => (string) $competencyId]);
                throw new \RuntimeException('Niveau reference inconnu.');
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
            self::recordWarning($details, 'Aucun niveau scolaire associe a la competence.', ['step' => 'competency_school_levels', 'object' => 'competence', 'slug' => (string) $competencyId]);
            return;
        }

        self::deleteOrFail($table, ['competency_id' => $competencyId], ['%d'], $details, ['step' => 'competency_school_levels', 'object' => 'competence', 'slug' => (string) $competencyId]);

        foreach ($levelIds as $levelId) {
            if (self::insertOrFail($table, ['competency_id' => $competencyId, 'school_level_id' => $levelId], ['%d', '%d'], $details, ['step' => 'competency_school_levels', 'object' => 'competence', 'slug' => (string) $competencyId])) {
                $details['competency_school_level_links']++;
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
                self::recordWarning($details, 'Exercice ignore : entree invalide.', ['step' => 'exercises']);
                continue;
            }

            $slug = sanitize_title((string)($row['slug'] ?? ''));
            $title = sanitize_text_field((string)($row['title'] ?? ''));
            $statement = wp_kses_post((string)($row['statement'] ?? ''));

            if ($slug === '' || $title === '' || trim($statement) === '') {
                self::recordError($details, 'Exercice invalide : slug, titre ou enonce manquant.', ['step' => 'exercises', 'object' => 'exercice', 'slug' => $slug]);
                throw new \RuntimeException('Exercice invalide dans le pack.');
            }

            $levelSlug = sanitize_key((string)($row['level_slug'] ?? ''));
            $difficultySlug = sanitize_key((string)($row['difficulty_slug'] ?? ''));
            $levelId = self::getSchoolLevelIdBySlug($p, $levelSlug);
            $difficultyId = self::getDifficultyIdBySlug($p, $difficultySlug);

            if ($levelId === null && $levelSlug !== '') {
                self::recordError($details, 'Niveau reference inexistant.', ['step' => 'exercises', 'object' => 'exercice', 'slug' => $slug]);
                throw new \RuntimeException('Niveau reference inexistant.');
            }

            if ($difficultyId === null && $difficultySlug !== '') {
                self::recordError($details, 'Difficulte referencee inexistante.', ['step' => 'exercises', 'object' => 'exercice', 'slug' => $slug]);
                throw new \RuntimeException('Difficulte referencee inexistante.');
            }

            if ($difficultyId === null && $difficultySlug === '') {
                self::recordWarning($details, 'Exercice sans difficulte associee.', ['step' => 'exercises', 'object' => 'exercice', 'slug' => $slug]);
            }

            $isActive = isset($row['is_active']) ? (int)$row['is_active'] : 1;
            $isActive = $isActive === 1 ? 1 : 0;
            $payload = ['level_id' => $levelId, 'difficulty_id' => $difficultyId, 'title' => $title, 'slug' => $slug, 'statement' => $statement, 'is_active' => $isActive];
            $formats = ['%d', '%d', '%s', '%s', '%s', '%d'];

            $existingId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tExercises} WHERE slug = %s", $slug));

            if ($existingId) {
                $exerciseId = (int)$existingId;
                if (self::updateOrFail($tExercises, $payload, ['id' => $exerciseId], $formats, ['%d'], $details, ['step' => 'exercises', 'object' => 'exercice', 'slug' => $slug])) {
                    $details['exercises_updated']++;
                }
            } else {
                $payload['created_at'] = current_time('mysql');
                if (self::insertOrFail($tExercises, $payload, array_merge($formats, ['%s']), $details, ['step' => 'exercises', 'object' => 'exercice', 'slug' => $slug])) {
                    $exerciseId = (int)$wpdb->insert_id;
                    $details['exercises_inserted']++;
                } else {
                    $exerciseId = 0;
                }
            }

            if ($exerciseId <= 0) {
                self::recordError($details, 'Identifiant exercice introuvable apres import.', ['step' => 'exercises', 'object' => 'exercice', 'slug' => $slug]);
                throw new \RuntimeException('Identifiant exercice introuvable apres import.');
            }

            $row['_ouinpo_existing_hints'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tHints} WHERE exercise_id = %d", $exerciseId));
            $row['_ouinpo_existing_solutions'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tSolutions} WHERE exercise_id = %d", $exerciseId));
            $row['_ouinpo_existing_exam_meta'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tExamMeta} WHERE exercise_id = %d", $exerciseId));

            self::deleteOrFail($tExerciseSchoolLevel, ['exercise_id' => $exerciseId], ['%d'], $details, ['step' => 'exercise_school_levels', 'object' => 'exercice', 'slug' => $slug]);
            self::deleteOrFail($tExerciseCompetency, ['exercise_id' => $exerciseId], ['%d'], $details, ['step' => 'exercise_competencies', 'object' => 'exercice', 'slug' => $slug]);
            self::deleteOrFail($tHints, ['exercise_id' => $exerciseId], ['%d'], $details, ['step' => 'hints', 'object' => 'exercice', 'slug' => $slug]);
            self::deleteOrFail($tSolutions, ['exercise_id' => $exerciseId], ['%d'], $details, ['step' => 'solutions', 'object' => 'exercice', 'slug' => $slug]);
            self::deleteOrFail($tExamMeta, ['exercise_id' => $exerciseId], ['%d'], $details, ['step' => 'exam_meta', 'object' => 'exercice', 'slug' => $slug]);
            self::deleteOrFail($tPracticalFiles, ['exercise_id' => $exerciseId], ['%d'], $details, ['step' => 'practical_files', 'object' => 'exercice', 'slug' => $slug]);

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
    $tExerciseCompetency = $p . 'exercise_competency';
    $slugs = $row['competency_slugs'] ?? [];

    if (!is_array($slugs)) {
        self::recordError($details, 'competency_slugs doit etre un tableau.', ['step' => 'exercise_competencies', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
        throw new \RuntimeException('competency_slugs invalide.');
    }

    foreach ($slugs as $rawSlug) {
        $competencySlug = sanitize_title((string)$rawSlug);
        if ($competencySlug === '') {
            continue;
        }

        $competencyId = self::getCompetencyIdBySlug($p, $competencySlug);
        if ($competencyId === null) {
            self::recordError($details, 'Competence referencee inexistante.', ['step' => 'exercise_competencies', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            throw new \RuntimeException('Competence referencee inexistante.');
        }

        if (self::insertOrFail($tExerciseCompetency, ['exercise_id' => $exerciseId, 'competency_id' => $competencyId], ['%d', '%d'], $details, ['step' => 'exercise_competencies', 'object' => 'exercice', 'slug' => (string) $exerciseId])) {
            $details['exercise_competency_links']++;
        }
    }
}

private static function importExerciseHints(string $p, int $exerciseId, array $row, array &$details): void
{
    global $wpdb;

    $tHints = $p . 'hints';
    $hints = $row['hints'] ?? [];
    $hadExistingHints = !empty($row['_ouinpo_existing_hints']);

    if (!is_array($hints)) {
        $details['hints_errors']++;
        self::recordError($details, 'hints doit etre un tableau.', ['step' => 'hints', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
        throw new \RuntimeException('hints invalide.');
    }

    foreach ($hints as $index => $hint) {
        if (!is_array($hint)) {
            $details['hints_errors']++;
            self::recordError($details, 'Indice invalide.', ['step' => 'hints', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            throw new \RuntimeException('Indice invalide.');
        }

        $order = isset($hint['order']) ? (int)$hint['order'] : ((int)$index + 1);
        $order = max(1, min(255, $order));
        $content = wp_kses_post((string)($hint['content'] ?? ''));
        if (trim($content) === '') {
            continue;
        }

        $existingId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tHints} WHERE exercise_id = %d AND hint_order = %d", $exerciseId, $order));
        if (self::insertOrFail($tHints, ['exercise_id' => $exerciseId, 'hint_order' => $order, 'content' => $content], ['%d', '%d', '%s'], $details, ['step' => 'hints', 'object' => 'exercice', 'slug' => (string) $exerciseId])) {
            $details['hints_imported']++;
            if ($existingId > 0 || $hadExistingHints) {
                $details['hints_updated']++;
            } else {
                $details['hints_inserted']++;
            }
        }
    }
}

private static function importExerciseSolutions(string $p, int $exerciseId, array $row, array &$details): void
{
    global $wpdb;

    $tSolutions = $p . 'solutions';
    $solutions = $row['solutions'] ?? [];
    $hadExistingSolutions = !empty($row['_ouinpo_existing_solutions']);

    if (!is_array($solutions)) {
        $details['solutions_errors']++;
        self::recordError($details, 'solutions doit etre un tableau.', ['step' => 'solutions', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
        throw new \RuntimeException('solutions invalide.');
    }

    foreach ($solutions as $index => $solution) {
        if (!is_array($solution)) {
            $details['solutions_errors']++;
            self::recordError($details, 'Solution invalide.', ['step' => 'solutions', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            throw new \RuntimeException('Solution invalide.');
        }

        $order = isset($solution['order']) ? (int)$solution['order'] : ((int)$index + 1);
        $order = max(1, min(255, $order));
        $title = sanitize_text_field((string)($solution['title'] ?? 'Solution'));
        $content = wp_kses_post((string)($solution['content'] ?? ''));
        $isOfficial = isset($solution['is_official']) ? (int)$solution['is_official'] : 0;
        $isOfficial = $isOfficial === 1 ? 1 : 0;

        if (trim($content) === '') {
            $details['solutions_errors']++;
            self::recordError($details, 'Solution obligatoire vide.', ['step' => 'solutions', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            throw new \RuntimeException('Solution obligatoire vide.');
        }

        $existingId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tSolutions} WHERE exercise_id = %d AND solution_order = %d", $exerciseId, $order));
        if (self::insertOrFail($tSolutions, ['exercise_id' => $exerciseId, 'title' => $title !== '' ? $title : 'Solution', 'content' => $content, 'solution_order' => $order, 'is_official' => $isOfficial, 'created_at' => current_time('mysql'), 'updated_at' => null], ['%d', '%s', '%s', '%d', '%d', '%s', '%s'], $details, ['step' => 'solutions', 'object' => 'exercice', 'slug' => (string) $exerciseId])) {
            $details['solutions_imported']++;
            if ($existingId > 0 || $hadExistingSolutions) {
                $details['solutions_updated']++;
            } else {
                $details['solutions_inserted']++;
            }
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
            $details['exam_meta_errors']++;
            self::recordError($details, 'exam_type invalide.', ['step' => 'exam_meta', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            throw new \RuntimeException('exam_type invalide.');
        }

        $sourceType = sanitize_key((string)($meta['source_type'] ?? 'type_bac'));
        if (!in_array($sourceType, ['annale', 'inspired', 'type_bac', 'classic'], true)) {
            $sourceType = 'type_bac';
        }

        $bacFormat = sanitize_key((string)($meta['bac_format'] ?? ''));
        if (!in_array($bacFormat, ['question_courte', 'lecture_code', 'code_a_completer', 'ecriture_complete', 'raisonnement'], true)) {
            $bacFormat = null;
        }

        $estimatedMinutes = isset($meta['estimated_minutes']) && $meta['estimated_minutes'] !== null ? max(0, (int)$meta['estimated_minutes']) : null;
        $sortInSubject = isset($meta['sort_in_subject']) && $meta['sort_in_subject'] !== null ? max(0, (int)$meta['sort_in_subject']) : null;
        $isExamLike = isset($meta['is_exam_like']) ? (int)$meta['is_exam_like'] : 1;
        $isExamLike = $isExamLike === 1 ? 1 : 0;

        if ($examType === 'practical_subject' && empty($row['practical_calls'])) {
            $details['exam_meta_errors']++;
            self::recordError($details, 'Sujet pratique sans appel pratique.', ['step' => 'exam_meta', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            throw new \RuntimeException('Sujet pratique incomplet.');
        }

        $existed = !empty($row['_ouinpo_existing_exam_meta']) ? 1 : 0;
        if (self::insertOrFail(
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
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s'],
            $details,
            ['step' => 'exam_meta', 'object' => 'exercice', 'slug' => (string) $exerciseId]
        )) {
            $details['exam_meta_imported']++;
            if ($existed > 0) {
                $details['exam_meta_updated']++;
            } else {
                $details['exam_meta_inserted']++;
            }
            if ($examType === 'practical_subject') {
                if ($existed > 0) {
                    $details['practical_subjects_updated']++;
                } else {
                    $details['practical_subjects_inserted']++;
                }
            }
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
    $table = $p . 'exercise_school_level';
    $levelIds = [];

    if (!empty($row['level_slugs']) && is_array($row['level_slugs'])) {
        foreach ($row['level_slugs'] as $rawSlug) {
            $levelSlug = sanitize_key((string) $rawSlug);
            $levelId = self::getSchoolLevelIdBySlug($p, $levelSlug);
            if ($levelId !== null) {
                $levelIds[] = $levelId;
            } elseif ($levelSlug !== '') {
                self::recordError($details, 'Niveau reference inexistant.', ['step' => 'exercise_school_levels', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
                throw new \RuntimeException('Niveau reference inexistant.');
            }
        }
    }

    if ($defaultLevelId !== null) {
        $levelIds[] = $defaultLevelId;
    }

    $levelIds = array_values(array_unique(array_filter($levelIds)));
    if (!$levelIds) {
        self::recordWarning($details, 'Aucun niveau scolaire associe.', ['step' => 'exercise_school_levels', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
        return;
    }

    foreach ($levelIds as $levelId) {
        if (self::insertOrFail($table, ['exercise_id' => $exerciseId, 'school_level_id' => $levelId], ['%d', '%d'], $details, ['step' => 'exercise_school_levels', 'object' => 'exercice', 'slug' => (string) $exerciseId])) {
            $details['exercise_school_level_links']++;
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
            self::recordWarning($details, 'Groupe de flashcards ignore : entree invalide.', ['step' => 'flashcards']);
            continue;
        }

        $deck = $group['deck'] ?? null;
        if (!is_array($deck)) {
            self::recordWarning($details, 'Groupe de flashcards ignore : deck manquant.', ['step' => 'flashcards']);
            continue;
        }

        $deckSlug = sanitize_title((string)($deck['slug'] ?? ''));
        $deckTitle = sanitize_text_field((string)($deck['title'] ?? ''));
        if ($deckSlug === '' || $deckTitle === '') {
            self::recordError($details, 'Deck invalide : slug ou titre manquant.', ['step' => 'flashcard_decks', 'object' => 'deck', 'slug' => $deckSlug]);
            throw new \RuntimeException('Deck flashcards invalide.');
        }

        $track = strtoupper(sanitize_text_field((string)($deck['track'] ?? 'NSI')));
        if (!in_array($track, ['SNT', 'NSI'], true)) {
            $track = 'NSI';
        }

        $level = sanitize_text_field((string)($deck['level'] ?? ''));
        $premiereLabel = 'Premi' . "\xC3\xA8" . 're';
        if (!in_array($level, ['Seconde', 'Premiere', $premiereLabel, 'Terminale', 'Transversal'], true)) {
            $level = 'Transversal';
        }
        if ($level === 'Premiere') {
            $level = $premiereLabel;
        }

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
        $deckFormats = ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'];
        $existingDeckId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tDecks} WHERE slug = %s", $deckSlug));

        if ($existingDeckId) {
            $deckId = (int)$existingDeckId;
            if (self::updateOrFail($tDecks, $deckPayload, ['id' => $deckId], $deckFormats, ['%d'], $details, ['step' => 'flashcard_decks', 'object' => 'deck', 'slug' => $deckSlug])) {
                $details['flashcard_decks_updated']++;
            }
        } else {
            $deckPayload['created_at'] = current_time('mysql');
            if (self::insertOrFail($tDecks, $deckPayload, array_merge($deckFormats, ['%s']), $details, ['step' => 'flashcard_decks', 'object' => 'deck', 'slug' => $deckSlug])) {
                $deckId = (int)$wpdb->insert_id;
                $details['flashcard_decks_inserted']++;
            } else {
                $deckId = 0;
            }
        }

        if ($deckId <= 0) {
            self::recordError($details, 'Identifiant du deck introuvable apres import.', ['step' => 'flashcard_decks', 'object' => 'deck', 'slug' => $deckSlug]);
            throw new \RuntimeException('Identifiant du deck introuvable apres import.');
        }

        $cards = $group['cards'] ?? [];
        if (!is_array($cards)) {
            self::recordError($details, 'cards doit etre un tableau.', ['step' => 'flashcards', 'object' => 'deck', 'slug' => $deckSlug]);
            throw new \RuntimeException('Cartes flashcards invalides.');
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
        self::recordError($details, 'Carte invalide.', ['step' => 'flashcards', 'object' => 'deck', 'slug' => $deckSlug]);
        throw new \RuntimeException('Carte flashcard invalide.');
    }

    $frontHtml = wp_kses_post((string)($card['front_html'] ?? ''));
    $backHtml = wp_kses_post((string)($card['back_html'] ?? ''));
    if (trim($frontHtml) === '' || trim($backHtml) === '') {
        self::recordError($details, 'Carte ignoree : recto ou verso manquant.', ['step' => 'flashcards', 'object' => 'deck', 'slug' => $deckSlug]);
        throw new \RuntimeException('Carte flashcard incomplete.');
    }

    $cardType = sanitize_key((string)($card['card_type'] ?? 'definition'));
    if (!in_array($cardType, ['definition', 'distinction', 'repere', 'syntaxe', 'vocabulaire'], true)) {
        $cardType = 'definition';
    }
    $sortOrder = isset($card['sort_order']) ? (int)$card['sort_order'] : ($index + 1);
    $isActive = isset($card['is_active']) ? (int)$card['is_active'] : 1;
    $isActive = $isActive === 1 ? 1 : 0;

    $existingCardId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tCards} WHERE deck_id = %d AND sort_order = %d", $deckId, $sortOrder));
    $payload = ['deck_id' => $deckId, 'card_type' => $cardType, 'front_html' => $frontHtml, 'back_html' => $backHtml, 'note_teacher' => wp_kses_post((string)($card['note_teacher'] ?? '')), 'sort_order' => $sortOrder, 'is_active' => $isActive, 'updated_at' => current_time('mysql')];
    $formats = ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s'];

    if ($existingCardId) {
        $cardId = (int)$existingCardId;
        if (self::updateOrFail($tCards, $payload, ['id' => $cardId], $formats, ['%d'], $details, ['step' => 'flashcards', 'object' => 'deck', 'slug' => $deckSlug])) {
            $details['flashcards_updated']++;
        }
    } else {
        $payload['created_at'] = current_time('mysql');
        if (self::insertOrFail($tCards, $payload, array_merge($formats, ['%s']), $details, ['step' => 'flashcards', 'object' => 'deck', 'slug' => $deckSlug])) {
            $cardId = (int)$wpdb->insert_id;
            $details['flashcards_inserted']++;
        } else {
            $cardId = 0;
        }
    }

    if ($cardId <= 0) {
        self::recordError($details, 'Identifiant de carte introuvable apres import.', ['step' => 'flashcards', 'object' => 'deck', 'slug' => $deckSlug]);
        throw new \RuntimeException('Identifiant de carte introuvable apres import.');
    }

    self::deleteOrFail($tCardCompetency, ['card_id' => $cardId], ['%d'], $details, ['step' => 'flashcard_competencies', 'object' => 'deck', 'slug' => $deckSlug]);

    $competencySlugs = $card['competency_slugs'] ?? [];
    if (!is_array($competencySlugs)) {
        self::recordWarning($details, 'competency_slugs doit etre un tableau.', ['step' => 'flashcard_competencies', 'object' => 'deck', 'slug' => $deckSlug]);
        return;
    }

    foreach ($competencySlugs as $rawSlug) {
        $competencySlug = sanitize_title((string)$rawSlug);
        if ($competencySlug === '') {
            continue;
        }
        $competencyId = self::getCompetencyIdBySlug($wpdb->prefix . 'ouin_exo_', $competencySlug);
        if ($competencyId === null) {
            self::recordWarning($details, 'Competence de flashcard inconnue.', ['step' => 'flashcard_competencies', 'object' => 'deck', 'slug' => $deckSlug]);
            continue;
        }

        if (self::insertOrFail($tCardCompetency, ['card_id' => $cardId, 'competency_id' => $competencyId], ['%d', '%d'], $details, ['step' => 'flashcard_competencies', 'object' => 'deck', 'slug' => $deckSlug], false)) {
            $details['flashcard_competency_links']++;
        } else {
            $details['flashcard_competency_link_errors']++;
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
        self::recordError($details, 'practical_calls doit etre un tableau.', ['step' => 'practical_calls', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
        throw new \RuntimeException('practical_calls invalide.');
    }

    $table = $p . 'practical_calls';

    foreach ($calls as $index => $call) {
        if (!is_array($call)) {
            self::recordError($details, 'Appel pratique invalide.', ['step' => 'practical_calls', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            throw new \RuntimeException('Appel pratique invalide.');
        }

        $callOrder = isset($call['call_order']) ? (int) $call['call_order'] : ((int) $index + 1);
        $callOrder = max(1, min(255, $callOrder));
        $title = sanitize_text_field((string)($call['title'] ?? ''));
        $promptHtml = wp_kses_post((string)($call['prompt_html'] ?? ''));

        if (trim($promptHtml) === '') {
            self::recordError($details, 'Appel pratique sans prompt_html.', ['step' => 'practical_calls', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            throw new \RuntimeException('Appel pratique incomplet.');
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
        $payload = ['exercise_id' => $exerciseId, 'call_order' => $callOrder, 'title' => $title !== '' ? $title : null, 'prompt_html' => $promptHtml, 'ai_rubric' => wp_kses_post((string)($call['ai_rubric'] ?? '')), 'answer_mode' => $answerMode, 'max_points' => $maxPoints, 'is_active' => $isActive, 'updated_at' => current_time('mysql')];
        $formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%f', '%d', '%s'];
        $existingId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE exercise_id = %d AND call_order = %d", $exerciseId, $callOrder));

        if ($existingId) {
            if (self::updateOrFail($table, $payload, ['id' => (int) $existingId], $formats, ['%d'], $details, ['step' => 'practical_calls', 'object' => 'exercice', 'slug' => (string) $exerciseId])) {
                $details['practical_calls_updated']++;
            }
        } else {
            $payload['created_at'] = current_time('mysql');
            if (self::insertOrFail($table, $payload, array_merge($formats, ['%s']), $details, ['step' => 'practical_calls', 'object' => 'exercice', 'slug' => (string) $exerciseId])) {
                $details['practical_calls_inserted']++;
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
        self::recordWarning($details, 'practical_files doit etre un tableau.', ['step' => 'practical_files', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
        return;
    }

    $tFiles = $p . 'practical_files';
    $tCalls = $p . 'practical_calls';

    foreach ($files as $index => $file) {
        if (!is_array($file)) {
            self::recordWarning($details, 'Fichier pratique invalide.', ['step' => 'practical_files', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            continue;
        }

        $label = sanitize_text_field((string)($file['label'] ?? ''));
        if ($label === '') {
            self::recordWarning($details, 'Fichier pratique ignore : label manquant.', ['step' => 'practical_files', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
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
            $foundCallId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tCalls} WHERE exercise_id = %d AND call_order = %d", $exerciseId, $callOrder));
            if ($foundCallId) {
                $callId = (int) $foundCallId;
            } else {
                self::recordWarning($details, 'Fichier pratique lie a un appel introuvable.', ['step' => 'practical_files', 'object' => 'exercice', 'slug' => (string) $exerciseId]);
            }
        }

        if (self::insertOrFail($tFiles, ['exercise_id' => $exerciseId, 'practical_call_id' => $callId, 'wp_attachment_id' => null, 'label' => $label, 'file_url' => $fileUrl !== '' ? $fileUrl : null, 'file_kind' => $fileKind, 'file_order' => $fileOrder, 'created_at' => current_time('mysql')], ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s'], $details, ['step' => 'practical_files', 'object' => 'exercice', 'slug' => (string) $exerciseId], false)) {
            $details['practical_files_imported']++;
        }
    }
}

}
