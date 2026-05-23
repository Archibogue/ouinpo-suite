<?php
namespace Ouinpo\Exercises\Rest;

defined('ABSPATH') || exit;

class PracticalRoutes {
    const NS = 'ouinpo/v1';

    public static function register() {
        // Liste des sujets pratiques
        register_rest_route(self::NS, '/practical-subjects', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'index'],
                // Public: liste des sujets pratiques consultable côté élève.
                'permission_callback' => [__CLASS__, 'can_view_public_subjects'],
            ],
        ]);

        // Détail d’un sujet pratique
        register_rest_route(self::NS, '/practical-subjects/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'show'],
                // Public: détail d’un sujet pratique consultable côté élève.
                'permission_callback' => [__CLASS__, 'can_view_public_subjects'],
            ],
        ]);

        // Appels évaluateurs d’un sujet
        register_rest_route(self::NS, '/practical-subjects/(?P<id>\d+)/calls', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'calls'],
                // Public: appels évaluateurs affichés dans le sujet pratique.
                'permission_callback' => [__CLASS__, 'can_view_public_subjects'],
            ],
        ]);

        // Fichiers liés à un sujet
        register_rest_route(self::NS, '/practical-subjects/(?P<id>\d+)/files', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'files'],
                // Public: fichiers liés au sujet pratique affichés dans l’interface.
                'permission_callback' => [__CLASS__, 'can_view_public_files'],
            ],
        ]);

        // Progression de l'élève connecté sur les appels
        register_rest_route(self::NS, '/practical-subjects/(?P<id>\d+)/progress', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'progress'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ],
        ]);

        // Envoi d’une réponse à SegFault pour un appel
        register_rest_route(self::NS, '/practical-subjects/(?P<id>\d+)/calls/(?P<call_id>\d+)/ai-evaluate', [
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'ai_evaluate_call'],
                'permission_callback' => [__CLASS__, 'can_evaluate_ai'],
            ],
        ]);
    }

    public static function can_evaluate_ai() {
        if (is_user_logged_in()) {
            return true;
        }
    
        if (!self::public_ai_enabled()) {
            return new \WP_Error(
                'ouinpo_ai_login_required',
                'Connexion requise pour utiliser cette correction IA.',
                ['status' => 401]
            );
        }

        return true;
    }
    
    public static function can_view_public_subjects() {
        if (is_user_logged_in()) {
            return true;
        }

        return \Ouinpo\Suite\Core\AiSettings::public_access_enabled('ouinpo_public_practical_subjects_enabled')
            ? true
            : new \WP_Error('ouinpo_login_required', 'Connexion requise pour consulter les sujets pratiques.', ['status' => 401]);
    }

    public static function can_view_public_files() {
        if (is_user_logged_in()) {
            return true;
        }

        return \Ouinpo\Suite\Core\AiSettings::public_access_enabled('ouinpo_public_practical_files_enabled')
            ? true
            : new \WP_Error('ouinpo_login_required', 'Connexion requise pour consulter les fichiers des sujets pratiques.', ['status' => 401]);
    }

    private static function public_ai_enabled(): bool {
        return \Ouinpo\Suite\Core\AiSettings::enabled_for_usage('practical_correction')
            && \Ouinpo\Suite\Core\AiSettings::public_enabled()
            && class_exists('\\OuInPo\\SegFault\\Albert')
            && \OuInPo\SegFault\Albert::public_available();
    }
    
    private static function public_ai_quota_limit(): int {

        $limit = (int) apply_filters(

            'ouinpo_ai_public_daily_limit',

            (int) get_option('ouinpo_ai_public_daily_limit', 10)

        );



        return max(1, min(200, $limit));

    }



    private static function consume_public_ai_quota() {

        if (is_user_logged_in()) {

            return true;

        }



        return \Ouinpo\Suite\Core\AiSettings::consumePublicRateLimit(
            'practical_ai',
            (int) apply_filters('ouinpo_ai_public_hourly_limit', 5, 'practical_ai'),
            self::public_ai_quota_limit(),
            (int) apply_filters('ouinpo_ai_public_global_daily_limit', 0, 'practical_ai')
        );

    }


    private static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    private static function sanitize_school_level($raw): string {
        $raw = sanitize_key((string) $raw);
        return substr($raw, 0, 20);
    }

    private static function sanitize_difficulty($raw): string {
        $raw = sanitize_key((string) $raw);
        $allowed = ['debutant', 'confirme', 'expert'];
        return in_array($raw, $allowed, true) ? $raw : '';
    }

    private static function can_preview_hidden_subjects(): bool {
        return \Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_PRACTICAL_SUBJECTS);
    }

    private static function get_subject_row(int $exercise_id, bool $include_hidden = false): ?array {
        global $wpdb;

        $tExo   = self::table('exercises');
        $tExam  = self::table('exam_meta');
        $tDiff  = self::table('difficulties');
        $tESL   = self::table('exercise_school_level');
        $tLevel = self::table('school_levels');

        $row = $wpdb->get_row($wpdb->prepare("
            SELECT
                e.id,
                e.title,
                e.slug,
                e.statement,
                e.is_active,
                e.created_at,
                d.slug  AS difficulty_slug,
                d.label AS difficulty_label,
                em.source_type,
                em.session_label,
                em.year_label,
                em.center_label,
                em.theme_bac,
                em.estimated_minutes,
                em.subject_group
            FROM {$tExo} e
            INNER JOIN {$tExam} em
                ON em.exercise_id = e.id
            LEFT JOIN {$tDiff} d
                ON d.id = e.difficulty_id
            WHERE e.id = %d
              " . ($include_hidden ? '' : 'AND e.is_active = 1') . "
              AND em.exam_type = 'practical_subject'
            LIMIT 1
        ", $exercise_id), ARRAY_A);

        if (!$row) {
            return null;
        }

        $levels = $wpdb->get_col($wpdb->prepare("
            SELECT sl.label
            FROM {$tESL} esl
            INNER JOIN {$tLevel} sl ON sl.id = esl.school_level_id
            WHERE esl.exercise_id = %d
            ORDER BY sl.sort_order ASC, sl.label ASC
        ", $exercise_id));

        $row['school_levels'] = array_values(array_unique(array_filter(array_map('strval', (array) $levels))));

        return $row;
    }

    public static function index(\WP_REST_Request $r) {
        global $wpdb;

        $tExo   = self::table('exercises');
        $tExam  = self::table('exam_meta');
        $tDiff  = self::table('difficulties');
        $tESL   = self::table('exercise_school_level');
        $tLevel = self::table('school_levels');
        $tCalls = self::table('practical_calls');

        $school_level = self::sanitize_school_level($r->get_param('school_level'));
        $difficulty   = self::sanitize_difficulty($r->get_param('difficulty'));
        $source_type  = sanitize_key((string) $r->get_param('source_type'));
        $theme_bac    = sanitize_text_field((string) $r->get_param('theme_bac'));

        $sql = "
            SELECT DISTINCT
                e.id,
                e.title,
                e.slug,
                d.slug  AS difficulty_slug,
                d.label AS difficulty_label,
                em.source_type,
                em.session_label,
                em.year_label,
                em.center_label,
                em.theme_bac,
                em.estimated_minutes,
                (
                    SELECT COUNT(*)
                    FROM {$tCalls} pc
                    WHERE pc.exercise_id = e.id
                      AND pc.is_active = 1
                ) AS calls_count
            FROM {$tExo} e
            INNER JOIN {$tExam} em
                ON em.exercise_id = e.id
            LEFT JOIN {$tDiff} d
                ON d.id = e.difficulty_id
        ";

        $where = [
            "e.is_active = 1",
            "em.exam_type = 'practical_subject'",
        ];
        $args = [];

        if ($school_level !== '') {
            $sql .= " INNER JOIN {$tESL} esl ON esl.exercise_id = e.id
                      INNER JOIN {$tLevel} sl ON sl.id = esl.school_level_id ";
            $where[] = "sl.slug = %s";
            $args[] = $school_level;
        }

        if ($difficulty !== '') {
            $where[] = "d.slug = %s";
            $args[] = $difficulty;
        }

        if ($source_type !== '') {
            $where[] = "em.source_type = %s";
            $args[] = $source_type;
        }

        if ($theme_bac !== '') {
            $where[] = "em.theme_bac = %s";
            $args[] = $theme_bac;
        }

        $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY e.created_at DESC, e.id DESC";

        $rows = !empty($args)
            ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        $rows = $rows ?: [];
        
        foreach ($rows as &$row) {
            $row['files_count'] = count(self::build_files_from_subject_folder((int) $row['id']));
        }
        unset($row);

        return rest_ensure_response($rows ?: []);
    }

    public static function show(\WP_REST_Request $r) {
        $id = (int) $r['id'];

        if ($id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide', ['status' => 400]);
        }

        $row = self::get_subject_row($id, self::can_preview_hidden_subjects());

        if (!$row) {
            return new \WP_Error('not_found', 'Sujet pratique introuvable', ['status' => 404]);
        }

        if ((int) ($row['is_active'] ?? 0) !== 1) {
            $row['_preview_notice'] = 'Ce sujet est masqué côté élèves.';
        }

        return rest_ensure_response($row);
    }

    public static function calls(\WP_REST_Request $r) {
        global $wpdb;

        $exercise_id = (int) $r['id'];

        if ($exercise_id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide', ['status' => 400]);
        }

        if (!self::get_subject_row($exercise_id, self::can_preview_hidden_subjects())) {
            return new \WP_Error('not_found', 'Sujet pratique introuvable', ['status' => 404]);
        }

        $tCalls = self::table('practical_calls');

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                id,
                exercise_id,
                call_order,
                title,
                prompt_html,
                answer_mode,
                max_points,
                is_active
            FROM {$tCalls}
            WHERE exercise_id = %d
              AND is_active = 1
            ORDER BY call_order ASC, id ASC
        ", $exercise_id), ARRAY_A);

        return rest_ensure_response($rows ?: []);
    }

public static function files(\WP_REST_Request $r) {
    $exercise_id = (int) $r['id'];

    if ($exercise_id <= 0) {
        return new \WP_Error('invalid_id', 'Identifiant invalide', ['status' => 400]);
    }

    if (!self::get_subject_row($exercise_id, self::can_preview_hidden_subjects())) {
        return new \WP_Error('not_found', 'Sujet pratique introuvable', ['status' => 404]);
    }

    $files = self::build_files_from_subject_folder($exercise_id);

    return rest_ensure_response($files);
}

    public static function progress(\WP_REST_Request $r) {
        global $wpdb;

        $exercise_id = (int) $r['id'];
        $user_id     = (int) get_current_user_id();

        if ($exercise_id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide', ['status' => 400]);
        }

        if (!$user_id) {
            return new \WP_Error('forbidden', 'Connexion requise', ['status' => 401]);
        }

        if (!self::get_subject_row($exercise_id)) {
            return new \WP_Error('not_found', 'Sujet pratique introuvable', ['status' => 404]);
        }

        $tCalls  = self::table('practical_calls');
        $tStatus = self::table('practical_call_status');

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                c.id AS practical_call_id,
                c.call_order,
                c.title,
                COALESCE(s.status, 'none') AS status,
                s.updated_at
            FROM {$tCalls} c
            LEFT JOIN {$tStatus} s
                ON s.practical_call_id = c.id
               AND s.exercise_id = c.exercise_id
               AND s.user_id = %d
            WHERE c.exercise_id = %d
              AND c.is_active = 1
            ORDER BY c.call_order ASC, c.id ASC
        ", $user_id, $exercise_id), ARRAY_A);

        return rest_ensure_response($rows ?: []);
    }

    public static function ai_evaluate_call(\WP_REST_Request $r) {
        global $wpdb;

        $exercise_id       = (int) $r['id'];
        $practical_call_id = (int) $r['call_id'];
        $user_id           = (int) get_current_user_id();
        $answer            = trim((string) $r->get_param('answer'));

        if ($exercise_id <= 0 || $practical_call_id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide', ['status' => 400]);
        }

        if ($answer === '') {
            return new \WP_Error('empty_answer', 'Réponse vide', ['status' => 400]);
        }
        
        if (mb_strlen($answer) > 12000) {
            return new \WP_Error(
                'answer_too_long',
                'Réponse trop longue.',
                ['status' => 400]
            );
        }        

        $subject = self::get_subject_row($exercise_id);
        if (!$subject) {
            return new \WP_Error('not_found', 'Sujet pratique introuvable', ['status' => 404]);
        }

        $tCalls = self::table('practical_calls');

        $call = $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$tCalls}
            WHERE id = %d
              AND exercise_id = %d
              AND is_active = 1
            LIMIT 1
        ", $practical_call_id, $exercise_id), ARRAY_A);

        if (!$call) {
            return new \WP_Error('call_not_found', 'Appel introuvable', ['status' => 404]);
        }

        $quota = self::consume_public_ai_quota();
        if (is_wp_error($quota)) {
            return $quota;
        }

        $payload = [
            'exercise_id' => $exercise_id,
            'user_id'     => $user_id,
            'is_logged'   => $user_id > 0,
            'subject'     => $subject,
            'call'        => $call,
            'answer'      => $answer,
        ];

        /**
         * À brancher sur ton moteur SegFault.
         * Le filtre doit renvoyer :
         * [
         *   'verdict' => 'correct'|'partial'|'incorrect',
         *   'feedback' => '...',
         *   'confidence' => 0.95
         * ]
         */
        $evaluation = apply_filters('ouinpo_practical_ai_evaluate', null, $payload);

        if (!is_array($evaluation) || empty($evaluation['verdict'])) {
            return new \WP_Error(
                'ai_unavailable',
                'Le moteur d’évaluation pratique n’est pas encore branché.',
                ['status' => 500]
            );
        }

        $verdict = (string) $evaluation['verdict'];
        if (!in_array($verdict, ['correct', 'partial', 'incorrect'], true)) {
            $verdict = 'incorrect';
        }

        $feedback   = isset($evaluation['feedback']) ? (string) $evaluation['feedback'] : '';
        $confidence = isset($evaluation['confidence']) ? (float) $evaluation['confidence'] : null;

        $stored = false;
        
        if ($user_id > 0) {
            $tAttempts = self::table('practical_call_attempts');
            $inserted = $wpdb->insert(
                $tAttempts,
                [
                    'user_id'           => $user_id,
                    'exercise_id'       => $exercise_id,
                    'practical_call_id' => $practical_call_id,
                    'answer_text'       => $answer,
                    'verdict'           => $verdict,
                    'feedback'          => $feedback,
                    'confidence'        => $confidence,
                    'created_at'        => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s']
            );
        
            if (!$inserted) {
                return new \WP_Error(
                    'attempt_insert_failed',
                    'Impossible d’enregistrer la tentative.',
                    ['status' => 500]
                );
            }
        
            $stored = true;
        }
        
        $safe_to_mark_solved = !empty($evaluation['safe_to_mark_solved']);
        
        $new_status = ($verdict === 'correct' && $safe_to_mark_solved)
            ? 'solved'
            : 'attempted';
        
        if ($user_id > 0) {
            $tStatus = self::table('practical_call_status');
        
            $existing_status = $wpdb->get_var($wpdb->prepare("
                SELECT status
                FROM {$tStatus}
                WHERE user_id = %d
                  AND exercise_id = %d
                  AND practical_call_id = %d
                LIMIT 1
            ", $user_id, $exercise_id, $practical_call_id));
        
            if ($existing_status === 'solved') {
                $new_status = 'solved';
            }
        
            $wpdb->query($wpdb->prepare("
                INSERT INTO {$tStatus}
                    (user_id, exercise_id, practical_call_id, status, declared_at, updated_at)
                VALUES
                    (%d, %d, %d, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    declared_at = VALUES(declared_at),
                    updated_at = VALUES(updated_at)
            ",
                $user_id,
                $exercise_id,
                $practical_call_id,
                $new_status,
                current_time('mysql'),
                current_time('mysql')
            ));
        }

        return rest_ensure_response([
            'verdict'             => $verdict,
            'feedback'            => $feedback,
            'next_steps'          => is_array($evaluation['next_steps'] ?? null) ? array_values($evaluation['next_steps']) : [],
            'confidence'          => $confidence,
            'safe_to_mark_solved' => $safe_to_mark_solved,
            'status'              => $new_status,
            'stored'              => $stored,
        ]);
    }

private static function normalize_folder_name(string $name): string {
    $name = remove_accents($name);
    $name = trim($name);
    $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    $name = trim((string) $name, '_');

    return $name;
}

private static function guess_file_kind_from_filename(string $filename): string {
    $name = strtolower($filename);
    $ext  = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    if (strpos($name, 'sujet') !== false || $ext === 'pdf') {
        return 'subject';
    }

    if ($ext === 'py') {
        return 'starter';
    }

    return 'resource';
}

private static function build_files_from_subject_folder(int $exercise_id): array {
    $uploads = wp_upload_dir();

    $folder = \Ouinpo\Exercises\PracticalFiles::get_folder_name_for_exercise($exercise_id);

    if ($folder === '') {
        return [];
    }

    $base_path = trailingslashit($uploads['basedir']) . 'ouinpo/practical/' . $folder;
    $base_url  = trailingslashit($uploads['baseurl']) . 'ouinpo/practical/' . $folder;

    if (!is_dir($base_path) || !is_readable($base_path)) {
        return [];
    }

    $entries = @scandir($base_path);
    if (!is_array($entries)) {
        return [];
    }

    $files = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        if ($entry[0] === '.') {
            continue;
        }

        if ($entry === 'index.php') {
            continue;
        }

        if ($entry === 'index.html' || $entry === '.htaccess') {

            continue;

        }

        if (
            class_exists('\\Ouinpo\\Exercises\\PracticalFiles')
            && !\Ouinpo\Exercises\PracticalFiles::is_allowed_file_name($entry)
        ) {

            continue;

        }

        $full_path = $base_path . '/' . $entry;
        if (!is_file($full_path)) {
            continue;
        }

        $files[] = [
            'id'                => 0,
            'exercise_id'       => $exercise_id,
            'practical_call_id' => null,
            'wp_attachment_id'  => null,
            'label'             => $entry,
            'url'               => trailingslashit($base_url) . rawurlencode($entry),
            'file_kind'         => self::guess_file_kind_from_filename($entry),
            'file_order'        => 999,
            '_filename'         => $entry,
        ];
    }

    usort($files, static function(array $a, array $b): int {
        return strnatcasecmp((string) ($a['_filename'] ?? ''), (string) ($b['_filename'] ?? ''));
    });

    foreach ($files as $i => &$file) {
        $file['file_order'] = $i + 1;
        unset($file['_filename']);
    }
    unset($file);

    return $files;
}

}
