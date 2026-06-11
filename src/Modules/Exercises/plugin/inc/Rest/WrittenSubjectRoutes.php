<?php

namespace Ouinpo\Exercises\Rest;

defined('ABSPATH') || exit;

final class WrittenSubjectRoutes
{
    private const NS = 'ouinpo/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/written-subjects', [[
            'methods' => 'GET',
            'callback' => [__CLASS__, 'index'],
            'permission_callback' => [__CLASS__, 'can_view'],
        ]]);

        register_rest_route(self::NS, '/written-subjects/(?P<id>\d+)', [[
            'methods' => 'GET',
            'callback' => [__CLASS__, 'show'],
            'permission_callback' => [__CLASS__, 'can_view'],
        ]]);

        register_rest_route(self::NS, '/written-files/(?P<id>\d+)/download', [[
            'methods' => 'GET',
            'callback' => [__CLASS__, 'download_file'],
            'permission_callback' => '__return_true',
        ]]);

        register_rest_route(self::NS, '/written-files/download', [[
            'methods' => 'GET',
            'callback' => [__CLASS__, 'download_signed_file'],
            'permission_callback' => '__return_true',
        ]]);

        register_rest_route(self::NS, '/written-subjects/(?P<id>\d+)/student-report', [[
            'methods' => 'POST',
            'callback' => [__CLASS__, 'student_report'],
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]]);

        register_rest_route(self::NS, '/written-subjects/(?P<id>\d+)/save-progress', [[
            'methods' => 'POST',
            'callback' => [__CLASS__, 'save_subject_progress'],
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]]);

        register_rest_route(self::NS, '/written-subjects/(?P<id>\d+)/reset-progress', [[
            'methods' => 'POST',
            'callback' => [__CLASS__, 'reset_subject_progress'],
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]]);

        register_rest_route(self::NS, '/written-questions/(?P<id>\d+)/student-advice', [[
            'methods' => 'POST',
            'callback' => [__CLASS__, 'student_question_advice'],
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]]);

        register_rest_route(self::NS, '/written-questions/(?P<id>\d+)/status', [[
            'methods' => 'POST',
            'callback' => [__CLASS__, 'update_status'],
            'permission_callback' => static function () {
                return is_user_logged_in();
            },
        ]]);
    }

    public static function can_view()
    {
        return self::can_current_user_view_written_subjects();
    }

    public static function can_current_user_view_written_subjects()
    {
        if (is_user_logged_in()) {
            return true;
        }

        return \Ouinpo\Suite\Core\AiSettings::public_access_enabled('ouinpo_public_exercises_enabled')
            ? true
            : new \WP_Error('ouinpo_login_required', 'Connexion requise pour consulter les annales écrites.', ['status' => 401]);
    }

    private static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    private static function sanitize_school_level($raw): string
    {
        return substr(sanitize_key((string) $raw), 0, 20);
    }

    public static function index(\WP_REST_Request $request)
    {
        global $wpdb;

        $tS = self::table('written_subjects');
        $tE = self::table('written_exercises');
        $tQ = self::table('written_questions');
        $tSL = self::table('written_subject_school_level');
        $tLevel = self::table('school_levels');
        $tQC = self::table('written_question_competency');
        $tComp = self::table('competencies');
        $tFiles = self::table('subject_files');
        $tStatus = self::table('written_question_status');
        $tAnswers = self::table('written_question_answers');
        $tUsedHints = self::table('written_hint_usage');
        $user_id = is_user_logged_in() ? (int) get_current_user_id() : 0;

        $school_level = self::sanitize_school_level($request->get_param('school_level'));
        $domain_slug = sanitize_key((string) $request->get_param('domain_slug'));
        $competency_id = max(0, (int) $request->get_param('competency_id'));
        $source_type = sanitize_key((string) $request->get_param('source_type'));
        $year_label = sanitize_text_field((string) $request->get_param('year_label'));

        $student_counts_sql = $user_id > 0
            ? ",
                COUNT(DISTINCT CASE
                    WHEN qs.status = 'solved' THEN q.id
                    ELSE NULL
                END) AS solved_questions_count,
                COUNT(DISTINCT CASE
                    WHEN qs.status IN ('attempted', 'solved')
                      OR (qa.answer_text IS NOT NULL AND TRIM(qa.answer_text) <> '')
                      OR qhu.hint_id IS NOT NULL
                    THEN q.id
                    ELSE NULL
                END) AS started_questions_count"
            : ",
                0 AS solved_questions_count,
                0 AS started_questions_count";

        $sql = "
            SELECT
                s.id,
                s.title,
                s.slug,
                s.source_type,
                s.session_label,
                s.year_label,
                s.center_label,
                s.subject_group,
                s.estimated_minutes,
                COUNT(DISTINCT e.id) AS exercises_count,
                COUNT(DISTINCT q.id) AS questions_count,
                COUNT(DISTINCT f.id) AS files_count
                {$student_counts_sql}
            FROM {$tS} s
            LEFT JOIN {$tE} e ON e.subject_id = s.id AND e.is_active = 1
            LEFT JOIN {$tQ} q ON q.exercise_id = e.id AND q.is_active = 1
            LEFT JOIN {$tFiles} f ON f.subject_type = 'written' AND f.subject_id = s.id
        ";

        if ($user_id > 0) {
            $sql .= $wpdb->prepare("
                LEFT JOIN {$tStatus} qs ON qs.question_id = q.id AND qs.user_id = %d
                LEFT JOIN {$tAnswers} qa ON qa.question_id = q.id AND qa.user_id = %d
                LEFT JOIN {$tUsedHints} qhu ON qhu.question_id = q.id AND qhu.user_id = %d
            ", $user_id, $user_id, $user_id);
        }

        if ($domain_slug !== '' || $competency_id > 0) {
            $sql .= " INNER JOIN {$tQC} qc_filter ON qc_filter.question_id = q.id INNER JOIN {$tComp} c_filter ON c_filter.id = qc_filter.competency_id ";
        }

        $where = ["s.is_active = 1"];
        $args = [];

        if ($school_level !== '') {
            $sql .= " INNER JOIN {$tSL} slr ON slr.subject_id = s.id INNER JOIN {$tLevel} sl ON sl.id = slr.school_level_id ";
            $where[] = 'sl.slug = %s';
            $args[] = $school_level;
        }

        if ($source_type !== '') {
            $where[] = 's.source_type = %s';
            $args[] = $source_type;
        }

        if ($domain_slug !== '') {
            $where[] = 'c_filter.domain_slug = %s';
            $args[] = $domain_slug;
        }

        if ($competency_id > 0) {
            $where[] = 'c_filter.id = %d';
            $args[] = $competency_id;
        }

        if ($domain_slug !== '' || $competency_id > 0) {
            $where[] = 'c_filter.active = 1';
        }

        if ($year_label !== '') {
            $where[] = 's.year_label = %s';
            $args[] = $year_label;
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " GROUP BY s.id
            ORDER BY
                s.year_label DESC,
                (s.center_label IS NULL OR s.center_label = '') ASC,
                s.center_label ASC,
                s.created_at DESC,
                s.id DESC";

        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        return rest_ensure_response($rows ?: []);
    }

    public static function show(\WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        if ($id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide.', ['status' => 400]);
        }

        $subject = self::get_subject($id);
        if (!$subject) {
            return new \WP_Error('not_found', 'Annale écrite introuvable.', ['status' => 404]);
        }

        return rest_ensure_response($subject);
    }

    public static function download_file(\WP_REST_Request $request)
    {
        $file_id = (int) $request['id'];
        if ($file_id <= 0) {
            return new \WP_Error('invalid_file', 'Fichier invalide.', ['status' => 400]);
        }

        $file = self::get_written_file($file_id);
        if (!$file) {
            return new \WP_Error('file_not_found', 'Fichier introuvable.', ['status' => 404]);
        }

        $permission = self::can_download_file($file);
        if (is_wp_error($permission)) {
            return $permission;
        }

        $path = \Ouinpo\Exercises\WrittenFiles::local_path_from_upload_url((string) ($file['file_url'] ?? ''));

        return self::send_file($path, (string) ($file['original_file_name'] ?? $file['file_name'] ?? 'fichier'));
    }

    public static function download_signed_file(\WP_REST_Request $request)
    {
        $relative = rawurldecode((string) $request->get_param('path'));
        $expires = (int) $request->get_param('expires');
        $signature = (string) $request->get_param('signature');

        if (!\Ouinpo\Exercises\WrittenFiles::verify_signed_download($relative, $expires, $signature)) {
            return new \WP_Error('invalid_signature', 'Lien de telechargement expire ou invalide.', ['status' => 403]);
        }

        $path = \Ouinpo\Exercises\WrittenFiles::local_path_from_relative_path($relative);

        return self::send_file($path, basename($relative));
    }

    public static function update_status(\WP_REST_Request $request)
    {
        $question_id = (int) $request['id'];
        $status = sanitize_key((string) $request->get_param('status'));

        $result = self::save_question_status(get_current_user_id(), $question_id, $status);
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(['ok' => true, 'status' => $status]);
    }

    public static function student_report(\WP_REST_Request $request)
    {
        $subject_id = (int) $request['id'];
        if ($subject_id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide.', ['status' => 400]);
        }

        $subject = self::get_subject($subject_id);
        if (!$subject) {
            return new \WP_Error('not_found', 'Annale écrite introuvable.', ['status' => 404]);
        }

        if (!\Ouinpo\Suite\Core\AiSettings::enabled_for_usage('written_subject_report')) {
            return new \WP_Error('ai_disabled', (string) \Ouinpo\Suite\Core\AiSettings::get('ouinpo_ai_disabled_message'), ['status' => 503]);
        }

        if (!class_exists('\OuInPo\SegFault\OpenAI')) {
            return new \WP_Error('ai_unavailable', 'Service IA indisponible pour le moment.', ['status' => 503]);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $input = self::normalize_report_input($params, $subject);
        if (is_wp_error($input)) {
            return $input;
        }

        $quota = self::consume_report_quota();
        if (is_wp_error($quota)) {
            return $quota;
        }

        self::save_report_input(get_current_user_id(), $input);

        $report = self::generate_student_report($subject, $input);
        if (is_wp_error($report)) {
            return $report;
        }

        return rest_ensure_response([
            'ok' => true,
            'stored' => true,
            'report' => $report,
        ]);
    }

    public static function save_subject_progress(\WP_REST_Request $request)
    {
        $subject_id = (int) $request['id'];
        if ($subject_id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide.', ['status' => 400]);
        }

        $subject = self::get_subject($subject_id);
        if (!$subject) {
            return new \WP_Error('not_found', 'Annale écrite introuvable.', ['status' => 404]);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $input = self::normalize_report_input($params, $subject, true);
        if (is_wp_error($input)) {
            return $input;
        }

        self::save_report_input(get_current_user_id(), $input);

        return rest_ensure_response([
            'ok' => true,
            'stored' => true,
        ]);
    }

    public static function reset_subject_progress(\WP_REST_Request $request)
    {
        $subject_id = (int) $request['id'];
        if ($subject_id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide.', ['status' => 400]);
        }

        $question_ids = self::get_subject_question_ids($subject_id);
        if (!$question_ids) {
            return new \WP_Error('not_found', 'Annale écrite introuvable ou sans questions actives.', ['status' => 404]);
        }

        self::delete_student_subject_progress(get_current_user_id(), $question_ids);

        if (class_exists('\Ouinpo\Exercises\BadgeEngine')) {
            \Ouinpo\Exercises\BadgeEngine::recompute_for_user(get_current_user_id());
        }

        return rest_ensure_response([
            'ok' => true,
            'reset' => true,
        ]);
    }

    public static function student_question_advice(\WP_REST_Request $request)
    {
        $question_id = (int) $request['id'];
        if ($question_id <= 0) {
            return new \WP_Error('invalid_id', 'Identifiant invalide.', ['status' => 400]);
        }

        $context = self::get_question_context($question_id);
        if (!$context) {
            return new \WP_Error('not_found', 'Question introuvable.', ['status' => 404]);
        }

        if (!\Ouinpo\Suite\Core\AiSettings::enabled_for_usage('written_subject_answers')) {
            return new \WP_Error('ai_disabled', (string) \Ouinpo\Suite\Core\AiSettings::get('ouinpo_ai_disabled_message'), ['status' => 503]);
        }

        if (!class_exists('\OuInPo\SegFault\OpenAI')) {
            return new \WP_Error('ai_unavailable', 'Service IA indisponible pour le moment.', ['status' => 503]);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $answer = self::clean_answer_text((string) ($params['answer'] ?? $params['answer_text'] ?? ''));
        if ($answer === '') {
            return new \WP_Error('empty_answer', 'Ecris ta reponse avant de demander un conseil IA.', ['status' => 400]);
        }

        $used_hints = self::normalize_question_hint_ids($params['used_hints'] ?? [], $context);

        $quota = self::consume_report_quota();
        if (is_wp_error($quota)) {
            return $quota;
        }

        self::save_report_input(get_current_user_id(), [
            'answers' => [$question_id => $answer],
            'used_hints' => [$question_id => $used_hints],
            'question_ids' => [$question_id],
        ]);

        $previous_answers = self::build_previous_answer_context($context, $params['context_answers'] ?? []);

        $advice = self::generate_question_advice($context, $answer, $used_hints, $previous_answers);
        if (is_wp_error($advice)) {
            return $advice;
        }

        $attempt_count = self::increment_question_attempt_if_open(get_current_user_id(), $question_id);

        if (in_array((string) ($advice['verdict'] ?? ''), ['partial', 'incorrect'], true)) {
            self::save_question_status(get_current_user_id(), $question_id, 'attempted');
        } elseif (!empty($advice['safe_to_mark_solved'])) {
            self::save_question_status(get_current_user_id(), $question_id, 'solved');
        }

        $advice['attempt_count'] = $attempt_count;

        return rest_ensure_response([
            'ok' => true,
            'stored' => true,
            'attempt_count' => $attempt_count,
            'advice' => $advice,
        ]);
    }

    public static function handle_status_form(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Connexion requise.');
        }

        $question_id = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;
        $status = sanitize_key((string) ($_POST['status'] ?? 'attempted'));
        $redirect = esc_url_raw((string) ($_POST['redirect_to'] ?? wp_get_referer() ?: home_url('/')));

        check_admin_referer('ouinpo_written_question_status_' . $question_id);

        $result = self::save_question_status(get_current_user_id(), $question_id, $status);
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $status_code = is_array($error_data) ? (int) ($error_data['status'] ?? 403) : 403;
            wp_die(esc_html($result->get_error_message()), '', ['response' => $status_code]);
        }

        wp_safe_redirect($redirect ?: home_url('/'));
        exit;
    }

    private static function save_question_status(int $user_id, int $question_id, string $status)
    {
        if ($user_id <= 0 || $question_id <= 0) {
            return new \WP_Error('invalid_status_target', 'Cible invalide.', ['status' => 400]);
        }

        if (!in_array($status, ['attempted', 'solved'], true)) {
            return new \WP_Error('invalid_status', 'Statut invalide.', ['status' => 400]);
        }

        global $wpdb;

        $tQ = self::table('written_questions');
        $tE = self::table('written_exercises');
        $tSub = self::table('written_subjects');
        $tS = self::table('written_question_status');

        $subject_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT sub.id
             FROM {$tQ} q
             INNER JOIN {$tE} e ON e.id = q.exercise_id AND e.is_active = 1
             INNER JOIN {$tSub} sub ON sub.id = e.subject_id AND sub.is_active = 1
             WHERE q.id = %d
               AND q.is_active = 1
             LIMIT 1",
            $question_id
        ));

        if ($subject_id <= 0) {
            return new \WP_Error('forbidden_status_target', 'Question non accessible.', ['status' => 403]);
        }

        $permission = self::can_current_user_view_written_subjects();
        if (is_wp_error($permission)) {
            return $permission;
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT attempt_count, last_attempt_at FROM {$tS} WHERE user_id = %d AND question_id = %d LIMIT 1",
            $user_id,
            $question_id
        ), ARRAY_A);

        $wpdb->replace($tS, [
            'user_id' => $user_id,
            'question_id' => $question_id,
            'status' => $status,
            'attempt_count' => max(0, (int) ($existing['attempt_count'] ?? 0)),
            'last_attempt_at' => $existing['last_attempt_at'] ?? null,
            'declared_at' => $status === 'solved' ? current_time('mysql') : null,
            'updated_at' => current_time('mysql'),
        ], ['%d', '%d', '%s', '%d', '%s', '%s', '%s']);

        if (class_exists('\Ouinpo\Exercises\BadgeEngine')) {
            \Ouinpo\Exercises\BadgeEngine::recompute_for_user($user_id);
        }

        return true;
    }

    private static function get_written_file(int $file_id): ?array
    {
        if ($file_id <= 0) {
            return null;
        }

        global $wpdb;

        $tFiles = self::table('subject_files');
        $tSubjects = self::table('written_subjects');

        $row = $wpdb->get_row($wpdb->prepare("
            SELECT
                f.id,
                f.subject_id,
                f.label,
                f.file_name,
                f.original_file_name,
                f.file_url,
                f.file_kind,
                s.is_active AS subject_active
            FROM {$tFiles} f
            INNER JOIN {$tSubjects} s ON s.id = f.subject_id
            WHERE f.id = %d
              AND f.subject_type = 'written'
            LIMIT 1
        ", $file_id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private static function can_download_file(array $file)
    {
        if (\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_EXERCISES)) {
            return true;
        }

        if ((int) ($file['subject_active'] ?? 0) !== 1) {
            return new \WP_Error('file_not_found', 'Fichier introuvable.', ['status' => 404]);
        }

        $permission = self::can_view();

        return is_wp_error($permission) ? $permission : true;
    }

    private static function send_file(string $path, string $filename)
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return new \WP_Error('file_not_found', 'Fichier introuvable.', ['status' => 404]);
        }

        $filename = sanitize_file_name($filename) ?: basename($path);
        $type = wp_check_filetype($filename, \Ouinpo\Exercises\WrittenFiles::allowed_mimes());
        $mime = !empty($type['type']) ? (string) $type['type'] : 'application/octet-stream';

        if (headers_sent()) {
            return new \WP_Error('headers_sent', 'Telechargement indisponible.', ['status' => 500]);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        status_header(200);
        $disposition = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === 'zip' ? 'attachment' : 'inline';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private static function increment_question_attempt_if_open(int $user_id, int $question_id): int
    {
        if ($user_id <= 0 || $question_id <= 0) {
            return 0;
        }

        global $wpdb;

        $tS = self::table('written_question_status');
        $now = current_time('mysql');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT status, attempt_count FROM {$tS} WHERE user_id = %d AND question_id = %d LIMIT 1",
            $user_id,
            $question_id
        ), ARRAY_A);

        if (is_array($row) && (string) ($row['status'] ?? '') === 'solved') {
            return max(0, (int) ($row['attempt_count'] ?? 0));
        }

        if (is_array($row)) {
            $next = max(0, (int) ($row['attempt_count'] ?? 0)) + 1;
            $wpdb->update($tS, [
                'attempt_count' => $next,
                'last_attempt_at' => $now,
                'updated_at' => $now,
            ], [
                'user_id' => $user_id,
                'question_id' => $question_id,
            ], ['%d', '%s', '%s'], ['%d', '%d']);

            return $next;
        }

        $wpdb->insert($tS, [
            'user_id' => $user_id,
            'question_id' => $question_id,
            'status' => 'none',
            'attempt_count' => 1,
            'last_attempt_at' => $now,
            'declared_at' => null,
            'updated_at' => $now,
        ], ['%d', '%d', '%s', '%d', '%s', '%s', '%s']);

        return 1;
    }

    private static function normalize_report_input(array $params, array $subject, bool $allow_empty_answers = false)
    {
        $questions = [];
        $hint_to_question = [];

        foreach ((array) ($subject['exercises'] ?? []) as $exercise) {
            foreach ((array) ($exercise['questions'] ?? []) as $question) {
                $question_id = (int) ($question['id'] ?? 0);
                if ($question_id <= 0) {
                    continue;
                }

                $questions[$question_id] = $question;

                foreach ((array) ($question['hints'] ?? []) as $hint) {
                    $hint_id = (int) ($hint['id'] ?? 0);
                    if ($hint_id > 0) {
                        $hint_to_question[$hint_id] = $question_id;
                    }
                }
            }
        }

        if (!$questions) {
            return new \WP_Error('empty_subject', 'Ce sujet ne contient pas encore de questions actives.', ['status' => 400]);
        }

        $raw_answers = $params['answers'] ?? [];
        $answers = [];

        if (is_array($raw_answers)) {
            foreach ($raw_answers as $key => $value) {
                if (is_array($value)) {
                    $question_id = (int) ($value['question_id'] ?? $key);
                    $answer_text = (string) ($value['answer_text'] ?? $value['answer'] ?? '');
                } else {
                    $question_id = (int) $key;
                    $answer_text = (string) $value;
                }

                if (!isset($questions[$question_id])) {
                    continue;
                }

                $answers[$question_id] = self::clean_answer_text($answer_text);
            }
        }

        foreach (array_keys($questions) as $question_id) {
            if (!isset($answers[$question_id])) {
                $answers[$question_id] = '';
            }
        }

        $has_answer = false;
        foreach ($answers as $answer_text) {
            if (trim($answer_text) !== '') {
                $has_answer = true;
                break;
            }
        }

        if (!$has_answer && !$allow_empty_answers) {
            return new \WP_Error('empty_answers', 'Ajoute au moins une reponse avant de demander un rapport.', ['status' => 400]);
        }

        $raw_used_hints = $params['used_hints'] ?? [];
        $used_hints = [];

        if (is_array($raw_used_hints)) {
            foreach ($raw_used_hints as $key => $value) {
                if (is_array($value) && array_key_exists('hint_ids', $value)) {
                    $question_id = (int) ($value['question_id'] ?? $key);
                    $hint_ids = is_array($value['hint_ids']) ? $value['hint_ids'] : [];
                } else {
                    $question_id = (int) $key;
                    $hint_ids = is_array($value) ? $value : [$value];
                }

                if (!isset($questions[$question_id])) {
                    continue;
                }

                foreach ($hint_ids as $hint_id_raw) {
                    $hint_id = (int) $hint_id_raw;
                    if ($hint_id <= 0 || ($hint_to_question[$hint_id] ?? 0) !== $question_id) {
                        continue;
                    }

                    if (!isset($used_hints[$question_id])) {
                        $used_hints[$question_id] = [];
                    }
                    $used_hints[$question_id][$hint_id] = $hint_id;
                }
            }
        }

        $used_hints_clean = [];
        foreach ($used_hints as $question_id => $hint_ids) {
            $used_hints_clean[(int) $question_id] = array_values($hint_ids);
        }

        return [
            'answers' => $answers,
            'used_hints' => $used_hints_clean,
            'question_ids' => array_keys($questions),
        ];
    }

    private static function save_report_input(int $user_id, array $input): void
    {
        if ($user_id <= 0) {
            return;
        }

        global $wpdb;

        $tA = self::table('written_question_answers');
        $tU = self::table('written_hint_usage');
        $now = current_time('mysql');

        foreach ((array) ($input['answers'] ?? []) as $question_id => $answer_text) {
            $wpdb->replace($tA, [
                'user_id' => $user_id,
                'question_id' => (int) $question_id,
                'answer_text' => (string) $answer_text,
                'updated_at' => $now,
            ], ['%d', '%d', '%s', '%s']);
        }

        foreach ((array) ($input['question_ids'] ?? []) as $question_id) {
            $wpdb->delete($tU, [
                'user_id' => $user_id,
                'question_id' => (int) $question_id,
            ], ['%d', '%d']);
        }

        foreach ((array) ($input['used_hints'] ?? []) as $question_id => $hint_ids) {
            foreach ((array) $hint_ids as $hint_id) {
                $wpdb->replace($tU, [
                    'user_id' => $user_id,
                    'question_id' => (int) $question_id,
                    'hint_id' => (int) $hint_id,
                    'used_at' => $now,
                ], ['%d', '%d', '%d', '%s']);
            }
        }
    }

    private static function delete_student_subject_progress(int $user_id, array $question_ids): void
    {
        if ($user_id <= 0) {
            return;
        }

        $question_ids = array_values(array_unique(array_filter(array_map('intval', $question_ids))));
        if (!$question_ids) {
            return;
        }

        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $args = array_merge([$user_id], $question_ids);

        foreach (['written_question_answers', 'written_hint_usage', 'written_question_status'] as $suffix) {
            $table = self::table($suffix);
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE user_id = %d AND question_id IN ({$placeholders})",
                $args
            ));
        }
    }

    private static function clean_answer_text(string $answer_text): string
    {
        $answer_text = str_replace("\0", '', wp_check_invalid_utf8($answer_text));
        $answer_text = trim($answer_text);

        if (function_exists('mb_substr')) {
            return mb_substr($answer_text, 0, 12000);
        }

        return substr($answer_text, 0, 12000);
    }

    private static function normalize_question_hint_ids($raw, array $context): array
    {
        $question = (array) ($context['question'] ?? []);
        $question_id = (int) ($question['id'] ?? 0);
        $allowed = [];

        foreach ((array) ($question['hints'] ?? []) as $hint) {
            $hint_id = (int) ($hint['id'] ?? 0);
            if ($hint_id > 0) {
                $allowed[$hint_id] = true;
            }
        }

        if (is_array($raw) && $question_id > 0 && isset($raw[$question_id]) && is_array($raw[$question_id])) {
            $raw = $raw[$question_id];
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];
        foreach ($raw as $item) {
            $hint_id = (int) $item;
            if ($hint_id > 0 && isset($allowed[$hint_id])) {
                $ids[$hint_id] = $hint_id;
            }
        }

        return array_values($ids);
    }

    private static function build_previous_answer_context(array $context, $raw_answers): array
    {
        $subject = (array) ($context['subject'] ?? []);
        $current_exercise = (array) ($context['exercise'] ?? []);
        $current_question = (array) ($context['question'] ?? []);
        $subject_id = (int) ($subject['id'] ?? 0);
        $current_exercise_id = (int) ($current_exercise['id'] ?? 0);
        $current_question_id = (int) ($current_question['id'] ?? 0);

        if ($subject_id <= 0 || $current_exercise_id <= 0 || $current_question_id <= 0) {
            return [];
        }

        $submitted_answers = [];
        if (is_array($raw_answers)) {
            foreach ($raw_answers as $key => $value) {
                if (is_array($value)) {
                    $question_id = (int) ($value['question_id'] ?? $key);
                    $answer_text = (string) ($value['answer_text'] ?? $value['answer'] ?? '');
                } else {
                    $question_id = (int) $key;
                    $answer_text = (string) $value;
                }

                if ($question_id > 0) {
                    $submitted_answers[$question_id] = self::clean_answer_text($answer_text);
                }
            }
        }

        $full_subject = self::get_subject($subject_id);
        if (!$full_subject) {
            return [];
        }

        $previous_answers = [];
        $previous_answer_budget = 8000;
        $previous_answer_used = 0;
        foreach ((array) ($full_subject['exercises'] ?? []) as $exercise) {
            if ((int) ($exercise['id'] ?? 0) !== $current_exercise_id) {
                continue;
            }

            $exercise_title = (string) (($exercise['title'] ?? '') ?: 'Exercice ' . (int) ($exercise['exercise_order'] ?? 1));

            foreach ((array) ($exercise['questions'] ?? []) as $question) {
                $question_id = (int) ($question['id'] ?? 0);
                if ($question_id <= 0) {
                    continue;
                }

                if ($question_id === $current_question_id) {
                    return $previous_answers;
                }

                $answer_text = array_key_exists($question_id, $submitted_answers)
                    ? $submitted_answers[$question_id]
                    : self::clean_answer_text((string) ($question['student_answer'] ?? ''));

                if (trim($answer_text) === '') {
                    continue;
                }

                $entry = [
                    'question_id' => $question_id,
                    'question_label' => $exercise_title . ' - Question ' . (string) ($question['question_label'] ?? ''),
                    'prompt' => self::excerpt((string) ($question['prompt_html'] ?? ''), 900),
                    'answer' => self::excerpt_user_text($answer_text, 2500),
                ];

                $entry_size = strlen(wp_json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                if ($previous_answer_used + $entry_size > $previous_answer_budget) {
                    $remaining = $previous_answer_budget - $previous_answer_used;
                    if ($remaining < 400) {
                        break;
                    }

                    $entry['answer'] = self::excerpt_user_text($entry['answer'], max(200, $remaining - 250));
                    $entry_size = strlen(wp_json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                }

                $previous_answers[] = $entry;
                $previous_answer_used += $entry_size;
            }

            return $previous_answers;
        }

        return [];
    }

    private static function consume_report_quota()
    {
        $teacher_quota = \Ouinpo\Suite\Core\AiSettings::currentUserUsesTeacherAiQuota();

        return \Ouinpo\Suite\Core\AiSettings::consumeUserRateLimit(
            $teacher_quota ? 'teacher_ai' : 'written_subject_report',
            get_current_user_id(),
            $teacher_quota ? \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_teacher_per_minute') : \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_exercise_ai_per_minute'),
            $teacher_quota ? \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_teacher_per_day') : \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_exercise_ai_per_day')
        );
    }

    private static function current_ai_user_id(): int
    {
        return is_user_logged_in() ? (int) get_current_user_id() : 0;
    }

    private static function student_pedagogical_context(): string
    {
        $user_id = self::current_ai_user_id();
        if (
            $user_id <= 0
            || !class_exists('\OuInPo\SegFault\RAG')
            || !method_exists('\OuInPo\SegFault\RAG', 'student_pedagogical_context')
        ) {
            return '';
        }

        return self::excerpt_user_text((string) \OuInPo\SegFault\RAG::student_pedagogical_context($user_id), 2600);
    }

    private static function course_rag_context(string $query, int $limit = 4, int $max_tokens = 1200, ?array &$debug = null): string
    {
        $debug = [
            'count' => 0,
            'sources' => '',
        ];

        $query = trim(wp_strip_all_tags($query));
        if ($query === '' || !class_exists('\OuInPo\SegFault\RAG')) {
            return '';
        }

        $user_id = self::current_ai_user_id();
        $chunks = [];

        try {
            if (method_exists('\OuInPo\SegFault\RAG', 'search_courses_by_competency')) {
                $chunks = array_merge($chunks, \OuInPo\SegFault\RAG::search_courses_by_competency($query, min(3, $limit), $user_id));
            }

            if (count($chunks) < $limit && method_exists('\OuInPo\SegFault\RAG', 'search')) {
                $chunks = array_merge($chunks, \OuInPo\SegFault\RAG::search($query, $limit, $user_id));
            }
        } catch (\Throwable $e) {
            \Ouinpo\Suite\Core\AiSettings::debug_log('Written subject RAG context unavailable', [
                'stage' => 'written_subject_rag_context',
                'error' => $e->getMessage(),
            ]);
            return '';
        }

        $filtered = [];
        $seen = [];
        foreach ($chunks as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }

            $ptype = strtolower(trim((string) ($chunk['ptype'] ?? ($chunk['type'] ?? ($chunk['origin'] ?? '')))));
            if ($ptype === 'exercise') {
                continue;
            }

            $key = (string) ($chunk['url'] ?? '');
            if ($key === '') {
                $key = (string) ($chunk['title'] ?? '') . '|' . substr((string) ($chunk['chunk'] ?? ''), 0, 80);
            }

            if ($key !== '' && isset($seen[$key])) {
                continue;
            }

            if ($key !== '') {
                $seen[$key] = true;
            }

            $filtered[] = $chunk;
            if (count($filtered) >= $limit) {
                break;
            }
        }

        if (!$filtered || !method_exists('\OuInPo\SegFault\RAG', 'format_context')) {
            return '';
        }

        $debug['count'] = count($filtered);
        $debug['sources'] = implode(' | ', array_map(static function (array $chunk): string {
            $title = trim((string) ($chunk['title'] ?? 'Document'));
            $url = trim((string) ($chunk['url'] ?? ''));
            return $url !== '' ? $title . ' <' . $url . '>' : $title;
        }, $filtered));

        return \OuInPo\SegFault\RAG::format_context($filtered, max(400, $max_tokens));
    }

    private static function out_of_program_notice(string $query): string
    {
        $user_id = self::current_ai_user_id();
        if (
            $user_id <= 0
            || trim($query) === ''
            || !class_exists('\OuInPo\SegFault\RAG')
            || !method_exists('\OuInPo\SegFault\RAG', 'topic_is_out_of_program_for_user')
        ) {
            return '';
        }

        try {
            return \OuInPo\SegFault\RAG::topic_is_out_of_program_for_user($query, $user_id)
                ? 'Attention programme : certaines notions semblent hors programme pour le niveau connu de l eleve. Le signaler sans penaliser une reponse conforme au sujet.'
                : '';
        } catch (\Throwable $e) {
            \Ouinpo\Suite\Core\AiSettings::debug_log('Written subject program guard unavailable', [
                'stage' => 'written_subject_program_guard',
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private static function debug_written_ai_context(string $stage, array $meta): void
    {
        \Ouinpo\Suite\Core\AiSettings::debug_log('Written subject AI context', array_merge([
            'stage' => $stage,
        ], $meta));
    }

    private static function question_rag_query(array $subject, array $exercise, array $question, array $competencies): string
    {
        return implode("\n", array_values(array_filter([
            (string) ($subject['title'] ?? ''),
            (string) ($exercise['title'] ?? ''),
            self::excerpt((string) ($exercise['intro_html'] ?? ''), 800),
            self::excerpt((string) ($question['prompt_html'] ?? ''), 900),
            implode("\n", array_values(array_filter($competencies))),
        ])));
    }

    private static function report_rag_query(array $context): string
    {
        $parts = [
            (string) ($context['subject_title'] ?? ''),
            implode(' ', (array) ($context['subject_meta'] ?? [])),
        ];

        foreach (array_slice((array) ($context['questions'] ?? []), 0, 6) as $question) {
            if (!is_array($question)) {
                continue;
            }

            $parts[] = (string) ($question['question_label'] ?? '');
            $parts[] = (string) ($question['prompt'] ?? '');
            $parts[] = implode(' ', (array) ($question['competencies'] ?? []));
        }

        return self::excerpt_user_text(implode("\n", array_values(array_filter($parts))), 4500);
    }

    private static function generate_student_report(array $subject, array $input)
    {
        $context = self::build_report_context($subject, $input);
        $rag_query = self::report_rag_query($context);
        $rag_debug = [];
        $context['course_context'] = self::course_rag_context($rag_query, 5, 1400, $rag_debug);
        $context['student_pedagogical_context'] = self::student_pedagogical_context();
        $context['program_guardrail'] = self::out_of_program_notice($rag_query);

        self::debug_written_ai_context('written_subject_student_report', [
            'subject_id' => (int) ($subject['id'] ?? 0),
            'answered_questions' => (int) ($context['answered_questions_count'] ?? 0),
            'course_context_chars' => strlen((string) ($context['course_context'] ?? '')),
            'course_context_sources' => (string) ($rag_debug['sources'] ?? ''),
            'course_context_count' => (int) ($rag_debug['count'] ?? 0),
            'student_context_chars' => strlen((string) ($context['student_pedagogical_context'] ?? '')),
            'program_guardrail' => $context['program_guardrail'] !== '' ? 1 : 0,
        ]);

        $messages = [[
            'role' => 'system',
            'content' => "Tu es un assistant pedagogique NSI. Tu rediges un rapport de conseils pour un eleve apres un sujet ecrit de bac NSI, meme si l'eleve n'a repondu qu'a une seule question. Base-toi uniquement sur les questions repondues, les competences BO, le contexte de cours fourni et les aides qu'il indique avoir utilisees. Ne fais pas semblant d'avoir analyse les questions sans reponse. Ne donne pas de note chiffree et ne corrige pas exhaustivement le sujet. Reste bienveillant, precis et actionnable. Reponds uniquement en JSON valide.",
        ], [
            'role' => 'user',
            'content' => wp_json_encode([
                'schema_attendu' => [
                    'summary' => 'Synthese courte en 2 ou 3 phrases.',
                    'strengths' => ['points forts observes'],
                    'priorities' => ['priorites de travail'],
                    'question_advice' => [[
                        'question_id' => 0,
                        'question_label' => 'Exercice 1 - Question 1.a',
                        'advice' => 'Conseil ciblé.',
                        'next_step' => 'Action concrete.',
                        'used_hints_note' => 'Comment exploiter les aides utilisees.',
                        'attempt_note' => 'Comment tenir compte du nombre d essais IA deja realises et, si la question est reussie, du nombre d essais avant reussite.',
                    ]],
                    'revision_plan' => ['actions de revision courtes'],
                    'teacher_note' => 'Formulation prudente utile pour relire avec le professeur.',
                ],
                'contexte' => $context,
                'consignes' => [
                    'Si une seule question est repondue, produis un rapport court sur cette question uniquement.',
                    'Utilise le contexte de cours/RAG seulement pour cadrer les notions attendues et le programme, sans inventer de source absente.',
                    'Adapte les conseils au contexte pedagogique eleve quand il est fourni, sans citer d identite personnelle.',
                    'Si program_guardrail est renseigne, signale la prudence programme sans sanctionner abusivement une reponse attendue par le sujet.',
                    'Tiens compte de attempt_count : plusieurs essais appellent un conseil de consolidation, pas une penalite. Si status=solved, attempt_count_before_success indique le nombre d essais avant reussite.',
                    'Ne laisse pas les champs vides.',
                    'Mentionne sobrement que le rapport porte sur les questions deja repondues.',
                    'Utilise exactement les cles summary, strengths, priorities, question_advice, revision_plan, teacher_note.',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]];

        $raw = \OuInPo\SegFault\OpenAI::respond($messages, [
            'temperature' => 0.25,
            'max_tokens' => max(1200, \Ouinpo\Suite\Core\AiSettings::maxTokens('ouinpo_ai_exercise_ai_max_tokens')),
            'stage' => 'written_subject_student_report',
            'response_format' => ['type' => 'json_object'],
        ]);

        $json = \Ouinpo\Exercises\Services\AiJsonResponseParser::parse((string) $raw, 'object');
        if (is_wp_error($json)) {
            return new \WP_Error('invalid_ai_report', 'Le rapport IA n a pas pu etre lu. Reessaie dans un instant.', [
                'status' => 502,
                'detail' => $json->get_error_message(),
            ]);
        }

        $report = self::sanitize_report($json, $context);
        if (!empty($report['_fallback'])) {
            unset($report['_fallback']);
            return self::fallback_report($context);
        }

        return $report;
    }

    private static function generate_question_advice(array $context, string $answer, array $used_hint_ids, array $previous_answers = [])
    {
        $question = (array) ($context['question'] ?? []);
        $exercise = (array) ($context['exercise'] ?? []);
        $subject = (array) ($context['subject'] ?? []);

        $used_hints = [];
        foreach ((array) ($question['hints'] ?? []) as $hint) {
            $hint_id = (int) ($hint['id'] ?? 0);
            if ($hint_id > 0 && in_array($hint_id, $used_hint_ids, true)) {
                $used_hints[] = [
                    'title' => self::excerpt((string) (($hint['title'] ?? '') ?: 'Aide IA'), 120),
                    'content' => self::excerpt((string) ($hint['content'] ?? ''), 700),
                ];
            }
        }

        $competencies = [];
        foreach ((array) ($question['competencies'] ?? []) as $competency) {
            $competencies[] = trim((string) ($competency['domain'] ?? '') . ' - ' . (string) ($competency['competency'] ?? ''));
        }

        $rag_query = self::question_rag_query($subject, $exercise, $question, $competencies);
        $rag_debug = [];
        $course_context = self::course_rag_context($rag_query, 4, 1200, $rag_debug);
        $student_context = self::student_pedagogical_context();
        $program_guardrail = self::out_of_program_notice($rag_query);

        self::debug_written_ai_context('written_question_student_advice', [
            'subject_id' => (int) ($subject['id'] ?? 0),
            'exercise_id' => (int) ($exercise['id'] ?? 0),
            'question_id' => (int) ($question['id'] ?? 0),
            'previous_answers' => count($previous_answers),
            'course_context_chars' => strlen($course_context),
            'course_context_sources' => (string) ($rag_debug['sources'] ?? ''),
            'course_context_count' => (int) ($rag_debug['count'] ?? 0),
            'student_context_chars' => strlen($student_context),
            'program_guardrail' => $program_guardrail !== '' ? 1 : 0,
        ]);

        $messages = [[
            'role' => 'system',
            'content' => "Tu es un evaluateur pedagogique NSI. Tu analyses une reponse d'eleve a une question de bac ecrit. Tu utilises le contexte de cours fourni pour cadrer le programme et les attendus, sans inventer de source absente. Tu ne donnes pas la correction complete et tu n'attribues pas de note chiffree. Tu determines un verdict prudent, un taux de confiance et des pistes d'amelioration. Reponds uniquement en JSON valide.",
        ], [
            'role' => 'user',
            'content' => wp_json_encode([
                'schema_attendu' => [
                    'verdict' => 'correct | partial | incorrect',
                    'feedback' => 'Retour court, utile et non exhaustif.',
                    'next_steps' => ['action concrete pour ameliorer la reponse'],
                    'confidence' => 0.82,
                    'safe_to_mark_solved' => false,
                    'strengths' => ['point solide observe'],
                    'improvements' => ['point a ameliorer'],
                    'hint_usage_note' => 'Comment exploiter les aides utilisees.',
                    'inherited_issue_note' => 'Rappel si une erreur vient d une reponse precedente du meme exercice.',
                ],
                'regles' => [
                    'les reponses precedentes du meme exercice sont du contexte: utilise les definitions, fonctions, variables ou resultats que l eleve y introduit',
                    'si une reponse courante utilise correctement une definition, fonction, variable ou un resultat introduit precedemment, juge la logique courante avec cette definition locale',
                    'si cette definition ou ce resultat precedent est faux, ne penalise pas automatiquement la question courante: indique que la question courante est reussie si son usage est coherent, puis renseigne inherited_issue_note pour rappeler explicitement que l element precedent doit etre corrige',
                    'n evalue pas les reponses precedentes pour elles-memes, sauf si elles rendent la reponse courante incoherente',
                    'utilise course_context comme cadre documentaire prioritaire si pertinent, sans citer les numeros de contexte',
                    'adapte le retour au student_pedagogical_context quand il est fourni, sans mentionner d identite personnelle',
                    'si program_guardrail est renseigne, signale la prudence programme sans sanctionner abusivement une reponse attendue par le sujet',
                    'si verdict=correct, propose surtout une consolidation courte',
                    'si verdict=partial, propose une etape precise pour terminer',
                    'si verdict=incorrect, donne une piste de depart sans fournir toute la correction',
                    'verdict correct seulement si la reponse est suffisante pour la question',
                    'verdict partial si la demarche est pertinente mais incomplete ou imprecise',
                    'verdict incorrect si la reponse est hors sujet, absente ou conceptuellement fausse',
                    'confidence est un nombre entre 0 et 1',
                    'safe_to_mark_solved vaut true seulement si verdict=correct et confiance >= 0.75',
                ],
                'contexte' => [
                    'subject_title' => (string) ($subject['title'] ?? ''),
                    'question_label' => (string) (($exercise['title'] ?? 'Exercice') . ' - Question ' . ($question['question_label'] ?? '')),
                    'exercise_context' => self::excerpt((string) ($exercise['intro_html'] ?? ''), 2200),
                    'prompt' => self::excerpt((string) ($question['prompt_html'] ?? ''), 1500),
                    'ai_rubric' => self::excerpt((string) (($question['ai_rubric'] ?? '') ?: ($question['correction_guidance'] ?? '')), 1000),
                    'answer' => self::excerpt_user_text($answer, 2000),
                    'previous_answers' => $previous_answers,
                    'used_hints' => $used_hints,
                    'competencies' => array_values(array_filter($competencies)),
                    'course_context' => $course_context,
                    'student_pedagogical_context' => $student_context,
                    'program_guardrail' => $program_guardrail,
                ],
                'exemples' => [[
                    'situation' => 'Question precedente: l eleve definit une fonction Z incorrecte. Question courante: il applique ensuite correctement cette fonction Z selon sa definition locale.',
                    'attendu' => 'verdict=correct si la question courante demandait seulement l utilisation coherente de Z; renseigner inherited_issue_note pour rappeler que la definition de Z doit etre corrigee.',
                ]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]];

        $raw = \OuInPo\SegFault\OpenAI::respond($messages, [
            'temperature' => 0.25,
            'max_tokens' => min(1200, max(700, \Ouinpo\Suite\Core\AiSettings::maxTokens('ouinpo_ai_exercise_ai_max_tokens'))),
            'stage' => 'written_question_student_advice',
            'response_format' => ['type' => 'json_object'],
        ]);

        $json = \Ouinpo\Exercises\Services\AiJsonResponseParser::parse((string) $raw, 'object');
        if (is_wp_error($json)) {
            return new \WP_Error('invalid_ai_advice', 'Le conseil IA n a pas pu etre lu. Reessaie dans un instant.', [
                'status' => 502,
                'detail' => $json->get_error_message(),
            ]);
        }

        return self::sanitize_question_evaluation($json);
    }

    private static function build_report_context(array $subject, array $input): array
    {
        $items = [];
        $answers = (array) ($input['answers'] ?? []);
        $used_hints = (array) ($input['used_hints'] ?? []);
        $total_questions = 0;
        $answered_questions = 0;

        foreach ((array) ($subject['exercises'] ?? []) as $exercise) {
            $exercise_title = (string) (($exercise['title'] ?? '') ?: 'Exercice ' . (int) ($exercise['exercise_order'] ?? 1));

            foreach ((array) ($exercise['questions'] ?? []) as $question) {
                $question_id = (int) ($question['id'] ?? 0);
                if ($question_id <= 0) {
                    continue;
                }
                $total_questions++;

                $answer = self::excerpt_user_text((string) ($answers[$question_id] ?? ''), 1800);
                if (trim($answer) === '') {
                    continue;
                }
                $answered_questions++;

                $hint_ids = array_map('intval', (array) ($used_hints[$question_id] ?? []));
                $hints = [];
                foreach ((array) ($question['hints'] ?? []) as $hint) {
                    $hint_id = (int) ($hint['id'] ?? 0);
                    if ($hint_id <= 0 || !in_array($hint_id, $hint_ids, true)) {
                        continue;
                    }

                    $hints[] = [
                        'title' => self::excerpt((string) (($hint['title'] ?? '') ?: 'Aide IA'), 120),
                        'content' => self::excerpt((string) ($hint['content'] ?? ''), 700),
                    ];
                }

                $competencies = [];
                foreach ((array) ($question['competencies'] ?? []) as $competency) {
                    $competencies[] = trim((string) ($competency['domain'] ?? '') . ' - ' . (string) ($competency['competency'] ?? ''));
                }

                $items[] = [
                    'question_id' => $question_id,
                    'question_label' => $exercise_title . ' - Question ' . (string) ($question['question_label'] ?? ''),
                    'exercise_context' => self::excerpt((string) ($exercise['intro_html'] ?? ''), 1800),
                    'prompt' => self::excerpt((string) ($question['prompt_html'] ?? ''), 1200),
                    'ai_rubric' => self::excerpt((string) (($question['ai_rubric'] ?? '') ?: ($question['correction_guidance'] ?? '')), 1000),
                    'answer' => $answer,
                    'attempt_count' => max(0, (int) ($question['student_attempt_count'] ?? 0)),
                    'attempt_count_before_success' => (string) ($question['student_status'] ?? 'none') === 'solved' ? max(0, (int) ($question['student_attempt_count'] ?? 0)) : 0,
                    'status' => (string) ($question['student_status'] ?? 'none'),
                    'used_hints' => $hints,
                    'competencies' => array_values(array_filter($competencies)),
                ];
            }
        }

        return [
            'subject_title' => (string) ($subject['title'] ?? ''),
            'subject_meta' => array_values(array_filter([
                (string) ($subject['session_label'] ?? ''),
                (string) ($subject['year_label'] ?? ''),
                (string) ($subject['center_label'] ?? ''),
            ])),
            'answered_questions_count' => $answered_questions,
            'total_questions_count' => $total_questions,
            'unanswered_questions_count' => max(0, $total_questions - $answered_questions),
            'questions' => $items,
        ];
    }

    private static function sanitize_report(array $report, array $context): array
    {
        $question_labels = [];
        foreach ((array) ($context['questions'] ?? []) as $question) {
            $question_labels[(int) ($question['question_id'] ?? 0)] = (string) ($question['question_label'] ?? '');
        }

        $list = static function ($value, int $limit = 6): array {
            if (!is_array($value)) {
                $value = $value !== null && $value !== '' ? [$value] : [];
            }

            $out = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $item = self::first_value($item, ['text', 'label', 'title', 'advice', 'content', 'item']) ?? '';
                }
                $text = trim(wp_strip_all_tags((string) $item));
                if ($text !== '') {
                    $out[] = $text;
                }
                if (count($out) >= $limit) {
                    break;
                }
            }

            return $out;
        };

        $question_advice = [];
        foreach ((array) (self::first_value($report, ['question_advice', 'questions_advice', 'conseils_par_question', 'conseils_questions']) ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question_id = (int) (self::first_value($item, ['question_id', 'id']) ?? 0);
            $question_advice[] = [
                'question_id' => $question_id,
                'question_label' => trim(wp_strip_all_tags((string) (self::first_value($item, ['question_label', 'label', 'question']) ?? ($question_labels[$question_id] ?? 'Question')))),
                'advice' => trim(wp_strip_all_tags((string) (self::first_value($item, ['advice', 'conseil', 'feedback', 'commentaire']) ?? ''))),
                'next_step' => trim(wp_strip_all_tags((string) (self::first_value($item, ['next_step', 'prochaine_etape', 'action', 'a_faire']) ?? ''))),
                'used_hints_note' => trim(wp_strip_all_tags((string) (self::first_value($item, ['used_hints_note', 'aides_utilisees', 'hint_usage_note']) ?? ''))),
                'attempt_note' => trim(wp_strip_all_tags((string) (self::first_value($item, ['attempt_note', 'essais_note', 'attempts_note']) ?? ''))),
            ];
        }

        $clean = [
            'summary' => trim(wp_strip_all_tags((string) (self::first_value($report, ['summary', 'synthese', 'resume']) ?? ''))),
            'strengths' => $list(self::first_value($report, ['strengths', 'points_forts', 'reussites']) ?? [], 5),
            'priorities' => $list(self::first_value($report, ['priorities', 'priorites', 'axes_de_travail', 'points_a_travailler']) ?? [], 5),
            'question_advice' => $question_advice,
            'revision_plan' => $list(self::first_value($report, ['revision_plan', 'plan_de_revision', 'plan']) ?? [], 6),
            'teacher_note' => trim(wp_strip_all_tags((string) (self::first_value($report, ['teacher_note', 'note_professeur', 'prudence']) ?? ''))),
        ];

        if (
            $clean['summary'] === ''
            && !$clean['strengths']
            && !$clean['priorities']
            && !$clean['question_advice']
            && !$clean['revision_plan']
            && $clean['teacher_note'] === ''
        ) {
            $clean['_fallback'] = true;
        }

        return $clean;
    }

    private static function fallback_report(array $context): array
    {
        $answered = (int) ($context['answered_questions_count'] ?? count((array) ($context['questions'] ?? [])));
        $total = (int) ($context['total_questions_count'] ?? $answered);
        $questions = (array) ($context['questions'] ?? []);
        $question_advice = [];

        foreach ($questions as $question) {
            $question_advice[] = [
                'question_id' => (int) ($question['question_id'] ?? 0),
                'question_label' => (string) ($question['question_label'] ?? 'Question'),
                'advice' => 'Ta reponse est bien enregistree. Pour progresser, compare chaque affirmation avec les mots-cles de l enonce et les notions de cours associees.',
                'next_step' => 'Demande une evaluation IA sur cette question pour obtenir un retour plus precis.',
                'used_hints_note' => !empty($question['used_hints']) ? 'Reprends les aides cochees et verifie que ta reponse exploite explicitement leurs indications.' : '',
                'attempt_note' => !empty($question['attempt_count'])
                    ? (
                        (string) ($question['status'] ?? '') === 'solved'
                            ? 'Question reussie apres ' . (int) $question['attempt_count'] . ' essai(s) IA.'
                            : 'Nombre d essais IA deja realises : ' . (int) $question['attempt_count'] . '.'
                    )
                    : '',
            ];
        }

        return [
            'summary' => $answered <= 1
                ? 'Rapport genere sur la question deja repondue. Les autres questions ne sont pas encore analysees.'
                : 'Rapport genere sur les ' . $answered . ' questions deja repondues sur ' . $total . '.',
            'strengths' => ['Tu as commence le sujet et tes reponses sont sauvegardees.'],
            'priorities' => ['Complete progressivement les questions restantes pour obtenir un rapport plus global.'],
            'question_advice' => $question_advice,
            'revision_plan' => ['Relis la ou les questions repondues.', 'Demande une evaluation IA question par question.', 'Complete ensuite les questions non traitees.'],
            'teacher_note' => 'Ce rapport est limite aux reponses actuellement saisies.',
        ];
    }

    private static function sanitize_question_evaluation(array $advice): array
    {
        $list = static function ($value, int $limit = 4): array {
            if (!is_array($value)) {
                $value = $value !== null && $value !== '' ? [$value] : [];
            }

            $out = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $item = self::first_value($item, ['text', 'label', 'title', 'advice', 'content', 'item']) ?? '';
                }

                $text = trim(wp_strip_all_tags((string) $item));
                if ($text !== '') {
                    $out[] = $text;
                }

                if (count($out) >= $limit) {
                    break;
                }
            }

            return $out;
        };

        $verdict = sanitize_key((string) (self::first_value($advice, ['verdict', 'evaluation', 'statut']) ?? 'incorrect'));
        if (!in_array($verdict, ['correct', 'partial', 'incorrect'], true)) {
            $verdict = 'incorrect';
        }

        $confidence = self::first_value($advice, ['confidence', 'confiance', 'score_confiance']);
        $confidence = is_numeric($confidence) ? (float) $confidence : 0.0;
        if ($confidence > 1.0 && $confidence <= 100.0) {
            $confidence = $confidence / 100.0;
        }
        $confidence = max(0.0, min(1.0, $confidence));

        $feedback = trim(wp_strip_all_tags((string) (self::first_value($advice, ['feedback', 'summary', 'synthese', 'resume', 'commentaire']) ?? '')));
        $next_steps = $list(self::first_value($advice, ['next_steps', 'next_step', 'prochaines_etapes', 'prochaine_etape', 'actions', 'a_faire']) ?? [], 4);
        $strengths = $list(self::first_value($advice, ['strengths', 'points_forts', 'reussites']) ?? [], 3);
        $improvements = $list(self::first_value($advice, ['improvements', 'ameliorations', 'points_a_ameliorer', 'priorities', 'priorites', 'axes_de_travail']) ?? [], 3);
        $hint_usage_note = trim(wp_strip_all_tags((string) (self::first_value($advice, ['hint_usage_note', 'used_hints_note', 'aides_utilisees']) ?? '')));
        $inherited_issue_note = trim(wp_strip_all_tags((string) (self::first_value($advice, ['inherited_issue_note', 'erreur_heritee', 'rappel_erreur_precedente']) ?? '')));
        $safe_to_mark_solved = self::truthy(self::first_value($advice, ['safe_to_mark_solved', 'validation_possible', 'peut_valider']))
            || ($verdict === 'correct' && $confidence >= 0.75);

        if ($feedback === '') {
            $feedback = 'Evaluation generee, mais la reponse IA n a pas fourni de commentaire exploitable.';
        }

        return [
            'verdict' => $verdict,
            'feedback' => $feedback,
            'next_steps' => $next_steps,
            'confidence' => $confidence,
            'safe_to_mark_solved' => $safe_to_mark_solved,
            'strengths' => $strengths,
            'improvements' => $improvements,
            'hint_usage_note' => $hint_usage_note,
            'inherited_issue_note' => $inherited_issue_note,
        ];
    }

    private static function truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'oui'], true);
    }

    private static function first_value(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private static function excerpt(string $html, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($html)) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit);
        }

        return substr($text, 0, $limit);
    }

    private static function excerpt_user_text(string $text, int $limit): string
    {
        $text = str_replace("\0", '', wp_check_invalid_utf8($text));
        $text = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit);
        }

        return substr($text, 0, $limit);
    }

    private static function get_question_context(int $question_id): ?array
    {
        if ($question_id <= 0) {
            return null;
        }

        global $wpdb;

        $tS = self::table('written_subjects');
        $tE = self::table('written_exercises');
        $tQ = self::table('written_questions');
        $tH = self::table('written_question_hints');
        $tQC = self::table('written_question_competency');
        $tC = self::table('competencies');

        $row = $wpdb->get_row($wpdb->prepare("
            SELECT
                q.*,
                e.id AS exercise_id,
                e.title AS exercise_title,
                e.exercise_order,
                e.intro_html AS exercise_intro_html,
                s.id AS subject_id,
                s.title AS subject_title,
                s.session_label,
                s.year_label,
                s.center_label
            FROM {$tQ} q
            INNER JOIN {$tE} e ON e.id = q.exercise_id AND e.is_active = 1
            INNER JOIN {$tS} s ON s.id = e.subject_id AND s.is_active = 1
            WHERE q.id = %d
              AND q.is_active = 1
            LIMIT 1
        ", $question_id), ARRAY_A);

        if (!is_array($row)) {
            return null;
        }

        $question = $row;
        $question['hints'] = $wpdb->get_results($wpdb->prepare("
            SELECT id, hint_order, title, content, is_ai
            FROM {$tH}
            WHERE question_id = %d
            ORDER BY hint_order ASC, id ASC
        ", $question_id), ARRAY_A) ?: [];

        $question['competencies'] = $wpdb->get_results($wpdb->prepare("
            SELECT c.id, c.domain, c.competency, c.track, c.level
            FROM {$tQC} qc
            INNER JOIN {$tC} c ON c.id = qc.competency_id
            WHERE qc.question_id = %d
            ORDER BY c.track, c.level, c.domain, c.id
        ", $question_id), ARRAY_A) ?: [];

        return [
            'subject' => [
                'id' => (int) $row['subject_id'],
                'title' => (string) $row['subject_title'],
                'session_label' => (string) ($row['session_label'] ?? ''),
                'year_label' => (string) ($row['year_label'] ?? ''),
                'center_label' => (string) ($row['center_label'] ?? ''),
            ],
            'exercise' => [
                'id' => (int) $row['exercise_id'],
                'title' => (string) (($row['exercise_title'] ?? '') ?: 'Exercice ' . (int) ($row['exercise_order'] ?? 1)),
                'exercise_order' => (int) ($row['exercise_order'] ?? 1),
                'intro_html' => (string) ($row['exercise_intro_html'] ?? ''),
            ],
            'question' => $question,
        ];
    }

    private static function get_subject_question_ids(int $subject_id): array
    {
        if ($subject_id <= 0) {
            return [];
        }

        global $wpdb;

        $tS = self::table('written_subjects');
        $tE = self::table('written_exercises');
        $tQ = self::table('written_questions');

        return array_map('intval', $wpdb->get_col($wpdb->prepare("
            SELECT q.id
            FROM {$tQ} q
            INNER JOIN {$tE} e ON e.id = q.exercise_id AND e.is_active = 1
            INNER JOIN {$tS} s ON s.id = e.subject_id AND s.is_active = 1
            WHERE s.id = %d
              AND q.is_active = 1
            ORDER BY e.exercise_order ASC, q.question_order ASC, q.id ASC
        ", $subject_id)) ?: []);
    }

    public static function get_subject(int $id): ?array
    {
        global $wpdb;

        $tS = self::table('written_subjects');
        $tE = self::table('written_exercises');
        $tQ = self::table('written_questions');
        $tH = self::table('written_question_hints');
        $tQC = self::table('written_question_competency');
        $tC = self::table('competencies');
        $tFiles = self::table('subject_files');
        $tAnswers = self::table('written_question_answers');
        $tUsedHints = self::table('written_hint_usage');
        $tStatus = self::table('written_question_status');
        $user_id = is_user_logged_in() ? (int) get_current_user_id() : 0;

        $subject = $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$tS}
            WHERE id = %d
              AND is_active = 1
            LIMIT 1
        ", $id), ARRAY_A);

        if (!is_array($subject)) {
            return null;
        }

        $subject['files'] = $wpdb->get_results($wpdb->prepare("
            SELECT id, label, file_name, original_file_name, file_url, file_kind, file_order
            FROM {$tFiles}
            WHERE subject_type = 'written'
              AND subject_id = %d
            ORDER BY file_order ASC, id ASC
        ", $id), ARRAY_A) ?: [];
        foreach ($subject['files'] as &$file) {
            $download_url = \Ouinpo\Exercises\WrittenFiles::download_url((int) ($file['id'] ?? 0));
            $file['download_url'] = $download_url;
            $file['file_url'] = $download_url;
        }
        unset($file);

        $exercises = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$tE}
            WHERE subject_id = %d
              AND is_active = 1
            ORDER BY exercise_order ASC, id ASC
        ", $id), ARRAY_A) ?: [];

        foreach ($exercises as &$exercise) {
            $exercise_id = (int) $exercise['id'];
            $questions = $wpdb->get_results($wpdb->prepare("
                SELECT *
                FROM {$tQ}
                WHERE exercise_id = %d
                  AND is_active = 1
                ORDER BY question_order ASC, id ASC
            ", $exercise_id), ARRAY_A) ?: [];

            foreach ($questions as &$question) {
                $question_id = (int) $question['id'];
                $question['hints'] = $wpdb->get_results($wpdb->prepare("
                    SELECT id, hint_order, title, content, is_ai
                    FROM {$tH}
                    WHERE question_id = %d
                    ORDER BY hint_order ASC, id ASC
                ", $question_id), ARRAY_A) ?: [];
                $question['competencies'] = $wpdb->get_results($wpdb->prepare("
                    SELECT c.id, c.domain, c.competency, c.track, c.level
                    FROM {$tQC} qc
                    INNER JOIN {$tC} c ON c.id = qc.competency_id
                    WHERE qc.question_id = %d
                    ORDER BY c.track, c.level, c.domain, c.id
                ", $question_id), ARRAY_A) ?: [];

                $question['student_answer'] = '';
                $question['student_status'] = 'none';
                $question['student_attempt_count'] = 0;
                $question['used_hint_ids'] = [];

                if ($user_id > 0) {
                    $question['student_answer'] = (string) ($wpdb->get_var($wpdb->prepare(
                        "SELECT answer_text FROM {$tAnswers} WHERE user_id = %d AND question_id = %d LIMIT 1",
                        $user_id,
                        $question_id
                    )) ?? '');

                    $status_row = $wpdb->get_row($wpdb->prepare(
                        "SELECT status, attempt_count FROM {$tStatus} WHERE user_id = %d AND question_id = %d LIMIT 1",
                        $user_id,
                        $question_id
                    ), ARRAY_A);

                    if (is_array($status_row)) {
                        $question['student_status'] = (string) ($status_row['status'] ?? 'none');
                        $question['student_attempt_count'] = max(0, (int) ($status_row['attempt_count'] ?? 0));
                    }

                    $question['used_hint_ids'] = array_map('intval', $wpdb->get_col($wpdb->prepare(
                        "SELECT hint_id FROM {$tUsedHints} WHERE user_id = %d AND question_id = %d ORDER BY hint_id ASC",
                        $user_id,
                        $question_id
                    )) ?: []);
                }
            }
            unset($question);

            $exercise['questions'] = $questions;
        }
        unset($exercise);

        $subject['exercises'] = $exercises;

        return $subject;
    }
}
