<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Exercises\WrittenFiles;
use Ouinpo\Exercises\Services\WrittenSubjectPdfImporter;
use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class ScreenWritten
{
    private static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    private static function redirect_url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => 'ouinpo-written-subjects'], $args), admin_url('admin.php'));
    }

    private static function normalize_uploaded_files(array $files): array
    {
        $names = $files['name'] ?? [];
        if (!is_array($names)) {
            return [$files];
        }

        $normalized = [];
        foreach ($names as $index => $name) {
            $normalized[] = [
                'name'     => $name,
                'type'     => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    private static function get_levels(): array
    {
        global $wpdb;
        $t = self::table('school_levels');
        return $wpdb->get_results("SELECT id, slug, label FROM {$t} ORDER BY sort_order ASC, id ASC", ARRAY_A) ?: [];
    }

    private static function get_competencies(): array
    {
        global $wpdb;
        $t = self::table('competencies');
        return $wpdb->get_results("
            SELECT id, domain, competency, track, level
            FROM {$t}
            WHERE active = 1
            ORDER BY track, level, domain, id
        ", ARRAY_A) ?: [];
    }

    private static function selected_levels(int $subject_id): array
    {
        if ($subject_id <= 0) {
            return [];
        }

        global $wpdb;
        $t = self::table('written_subject_school_level');
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT school_level_id FROM {$t} WHERE subject_id = %d",
            $subject_id
        )) ?: []);
    }

    private static function fetch_subject(int $subject_id): ?array
    {
        if ($subject_id <= 0) {
            return null;
        }

        global $wpdb;
        $t = self::table('written_subjects');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $subject_id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private static function fetch_files(int $subject_id): array
    {
        if ($subject_id <= 0) {
            return [];
        }

        global $wpdb;
        $t = self::table('subject_files');
        return $wpdb->get_results($wpdb->prepare("
            SELECT id, label, file_name, file_url, file_kind, file_order
            FROM {$t}
            WHERE subject_type = 'written'
              AND subject_id = %d
            ORDER BY file_order ASC, id ASC
        ", $subject_id), ARRAY_A) ?: [];
    }

    private static function fetch_tree(int $subject_id): array
    {
        if ($subject_id <= 0) {
            return [];
        }

        global $wpdb;
        $tEx = self::table('written_exercises');
        $tQ = self::table('written_questions');
        $tH = self::table('written_question_hints');
        $tQC = self::table('written_question_competency');

        $exercises = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$tEx}
            WHERE subject_id = %d
            ORDER BY exercise_order ASC, id ASC
        ", $subject_id), ARRAY_A) ?: [];

        foreach ($exercises as &$exercise) {
            $exercise_id = (int) $exercise['id'];
            $questions = $wpdb->get_results($wpdb->prepare("
                SELECT *
                FROM {$tQ}
                WHERE exercise_id = %d
                ORDER BY question_order ASC, id ASC
            ", $exercise_id), ARRAY_A) ?: [];

            foreach ($questions as &$question) {
                $question_id = (int) $question['id'];
                $question['competency_ids'] = array_map('intval', $wpdb->get_col($wpdb->prepare(
                    "SELECT competency_id FROM {$tQC} WHERE question_id = %d ORDER BY competency_id ASC",
                    $question_id
                )) ?: []);
                $question['hints'] = $wpdb->get_results($wpdb->prepare("
                    SELECT *
                    FROM {$tH}
                    WHERE question_id = %d
                    ORDER BY hint_order ASC, id ASC
                ", $question_id), ARRAY_A) ?: [];
            }
            unset($question);

            $exercise['questions'] = $questions;
        }
        unset($exercise);

        return $exercises;
    }

    public static function render(): void
    {
        if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) {
            wp_die('Acces refuse.');
        }

        global $wpdb;

        $subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
        $subject = self::fetch_subject($subject_id);
        $levels = self::get_levels();
        $competencies = self::get_competencies();
        $selected_levels = $subject ? self::selected_levels((int) $subject['id']) : [];
        $tree = $subject ? self::fetch_tree((int) $subject['id']) : [];
        $files = $subject ? self::fetch_files((int) $subject['id']) : [];

        $subjects = $wpdb->get_results("
            SELECT s.*, COUNT(DISTINCT e.id) AS exercises_count, COUNT(DISTINCT q.id) AS questions_count
            FROM " . self::table('written_subjects') . " s
            LEFT JOIN " . self::table('written_exercises') . " e ON e.subject_id = s.id
            LEFT JOIN " . self::table('written_questions') . " q ON q.exercise_id = e.id
            GROUP BY s.id
            ORDER BY s.created_at DESC, s.id DESC
        ", ARRAY_A) ?: [];

        if (empty($tree)) {
            $tree = [[
                'id' => 0,
                'exercise_order' => 1,
                'title' => 'Exercice 1',
                'intro_html' => '',
                'max_points' => '',
                'is_active' => 1,
                'questions' => [[
                    'id' => 0,
                    'question_order' => 1,
                    'question_label' => '1.a',
                    'prompt_html' => '',
                    'answer_type' => 'text',
                    'max_points' => '',
                    'is_active' => 1,
                    'competency_ids' => [],
                    'hints' => [[
                        'id' => 0,
                        'hint_order' => 1,
                        'title' => 'Aide IA',
                        'content' => '',
                        'is_ai' => 1,
                    ]],
                ]],
            ]];
        }

        echo '<div class="wrap">';
        echo '<h1>Annales ecrites NSI</h1>';

        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Annale enregistree.</p></div>';
        }
        if (isset($_GET['file_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(wp_unslash((string) $_GET['file_error'])) . '</p></div>';
        }
        if (isset($_GET['import_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(wp_unslash((string) $_GET['import_error'])) . '</p></div>';
        }

        echo '<div class="ouinpo-admin-block">';
        echo '<h2>Creer une annale depuis un PDF</h2>';
        echo '<p class="description">Le PDF est extrait, decoupe par l IA, puis cree en brouillon masque pour verification avant publication.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field('ouinpo_import_written_pdf');
        echo '<input type="hidden" name="action" value="ouinpo_import_written_pdf">';
        echo '<p><label for="written-import-pdf">PDF du sujet officiel</label><br><input id="written-import-pdf" type="file" name="written_subject_pdf" accept=".pdf" required></p>';
        echo '<p><label>Titre de secours <input type="text" name="fallback_title" class="regular-text" placeholder="Annale NSI"></label></p>';
        echo '<p><label>Origine <select name="source_type"><option value="annale">Annale</option><option value="inspired">Inspire annale</option><option value="type_bac">Type bac</option></select></label></p>';
        if ($levels) {
            echo '<p>Niveaux a proposer a l IA : ';
            foreach ($levels as $level) {
                echo '<label class="ouinpo-admin-inline-check"><input type="checkbox" name="school_levels[]" value="' . (int) $level['id'] . '"> ' . esc_html((string) $level['label']) . '</label> ';
            }
            echo '</p>';
        }
        submit_button('Importer et decouper avec l IA', 'primary', 'submit', false);
        echo '</form>';
        echo '</div>';

        echo '<div class="ouinpo-admin-practical-layout">';
        echo '<div>';
        echo '<h2>Annales</h2>';
        echo '<p><a class="button button-primary" href="' . esc_url(self::redirect_url()) . '">Nouvelle annale</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>Titre</th><th>Session</th><th>Questions</th><th>Visible</th></tr></thead><tbody>';
        if (!$subjects) {
            echo '<tr><td colspan="4">Aucune annale pour le moment.</td></tr>';
        }
        foreach ($subjects as $row) {
            $url = self::redirect_url(['subject_id' => (int) $row['id']]);
            echo '<tr>';
            echo '<td><a href="' . esc_url($url) . '">' . esc_html((string) $row['title']) . '</a></td>';
            echo '<td>' . esc_html(trim((string) ($row['session_label'] ?? '') . ' ' . (string) ($row['year_label'] ?? '') . ' ' . (string) ($row['center_label'] ?? ''))) . '</td>';
            echo '<td>' . esc_html((string) (int) $row['questions_count']) . '</td>';
            echo '<td>' . (!empty($row['is_active']) ? 'Oui' : 'Non') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        echo '<div>';
        echo '<h2>' . ($subject ? 'Editer l annale #' . (int) $subject['id'] : 'Creer une annale') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" id="ouinpo-written-subject-form" data-ouinpo-written-encoded-form="1">';
        wp_nonce_field('ouinpo_save_written_subject');
        echo '<input type="hidden" name="action" value="ouinpo_save_written_subject">';
        echo '<input type="hidden" name="subject_id" value="' . esc_attr((string) ($subject['id'] ?? 0)) . '">';

        echo '<table class="form-table" role="presentation"><tbody>';
        self::render_input_row('Titre', 'title', (string) ($subject['title'] ?? ''), true);
        self::render_input_row('Slug', 'slug', (string) ($subject['slug'] ?? ''), false);
        self::render_select_row('Origine', 'source_type', (string) ($subject['source_type'] ?? 'annale'), [
            'annale' => 'Annale',
            'inspired' => 'Inspire annale',
            'type_bac' => 'Type bac',
        ]);
        self::render_input_row('Session', 'session_label', (string) ($subject['session_label'] ?? ''), false);
        self::render_input_row('Annee', 'year_label', (string) ($subject['year_label'] ?? ''), false);
        self::render_input_row('Centre', 'center_label', (string) ($subject['center_label'] ?? ''), false);
        self::render_input_row('Groupe de sujet', 'subject_group', (string) ($subject['subject_group'] ?? ''), false);
        self::render_input_row('Duree estimee', 'estimated_minutes', (string) ($subject['estimated_minutes'] ?? ''), false, 'number');
        echo '<tr><th scope="row">Niveaux</th><td>';
        foreach ($levels as $level) {
            echo '<label class="ouinpo-admin-inline-check"><input type="checkbox" name="school_levels[]" value="' . (int) $level['id'] . '" ' . checked(in_array((int) $level['id'], $selected_levels, true), true, false) . '> ' . esc_html((string) $level['label']) . '</label> ';
        }
        echo '</td></tr>';
        echo '<tr><th scope="row">Visible</th><td><label><input type="checkbox" name="is_active" value="1" ' . checked((int) ($subject['is_active'] ?? 1), 1, false) . '> Oui</label></td></tr>';
        echo '<tr><th scope="row"><label for="statement">Presentation</label></th><td>';
        wp_editor((string) ($subject['statement'] ?? ''), 'written_statement', ['textarea_name' => 'statement', 'textarea_rows' => 6]);
        echo '</td></tr>';
        echo '</tbody></table>';

        if ($subject) {
            echo '<h2>Fichiers du sujet</h2>';
            if ($files) {
                echo '<table class="widefat striped"><thead><tr><th>Ordre</th><th>Type</th><th>Fichier</th><th>Action</th></tr></thead><tbody>';
                foreach ($files as $file) {
                    $delete_url = wp_nonce_url(
                        add_query_arg([
                            'action' => 'ouinpo_delete_written_file',
                            'subject_id' => (int) $subject['id'],
                            'file_id' => (int) $file['id'],
                        ], admin_url('admin-post.php')),
                        'ouinpo_delete_written_file_' . (int) $file['id']
                    );
                    echo '<tr>';
                    echo '<td>' . esc_html((string) (int) $file['file_order']) . '</td>';
                    echo '<td>' . esc_html((string) $file['file_kind']) . '</td>';
                    echo '<td><a href="' . esc_url((string) $file['file_url']) . '" target="_blank" rel="noopener">' . esc_html((string) $file['label']) . '</a></td>';
                    echo '<td><a class="button-link-delete" href="' . esc_url($delete_url) . '">Supprimer</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            $accepted_extensions = array_map(static fn(string $ext): string => '.' . $ext, array_keys(WrittenFiles::allowed_mimes()));
            echo '<p><label for="written-files">Ajouter des fichiers</label><br><input type="file" id="written-files" name="written_files[]" multiple accept="' . esc_attr(implode(',', $accepted_extensions)) . '"></p>';
        }

        echo '<h2>Exercices, questions et aides IA</h2>';
        echo '<div id="ouinpo-written-exercises" data-next-exercise="' . esc_attr((string) count($tree)) . '">';
        foreach (array_values($tree) as $exercise_index => $exercise) {
            self::render_exercise_block($exercise_index, $exercise, $competencies);
        }
        echo '</div>';
        echo '<p><button type="button" class="button" id="ouinpo-add-written-exercise">+ Ajouter un exercice</button></p>';

        submit_button($subject ? 'Mettre a jour l annale' : 'Creer l annale');
        echo '</form>';
        echo '</div></div></div>';
    }

    private static function render_input_row(string $label, string $name, string $value, bool $required = false, string $type = 'text'): void
    {
        echo '<tr><th scope="row"><label for="written-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input id="written-' . esc_attr($name) . '" name="' . esc_attr($name) . '" type="' . esc_attr($type) . '" class="regular-text" value="' . esc_attr($value) . '"' . ($required ? ' required' : '') . '>';
        echo '</td></tr>';
    }

    private static function render_select_row(string $label, string $name, string $current, array $options): void
    {
        echo '<tr><th scope="row"><label for="written-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<select id="written-' . esc_attr($name) . '" name="' . esc_attr($name) . '">';
        foreach ($options as $value => $text) {
            echo '<option value="' . esc_attr((string) $value) . '" ' . selected($current, (string) $value, false) . '>' . esc_html((string) $text) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private static function render_exercise_block(int $exercise_index, array $exercise, array $competencies): void
    {
        $prefix = 'written_exercises[' . $exercise_index . ']';
        echo '<section class="ouinpo-admin-block ouinpo-written-exercise" data-exercise-index="' . esc_attr((string) $exercise_index) . '">';
        echo '<h3>Exercice</h3>';
        echo '<input type="hidden" name="' . esc_attr($prefix) . '[id]" value="' . esc_attr((string) ($exercise['id'] ?? 0)) . '">';
        echo '<p><label>Ordre <input type="number" min="1" name="' . esc_attr($prefix) . '[exercise_order]" value="' . esc_attr((string) ($exercise['exercise_order'] ?? ($exercise_index + 1))) . '" class="small-text"></label> ';
        echo '<label>Titre <input type="text" name="' . esc_attr($prefix) . '[title]" value="' . esc_attr((string) ($exercise['title'] ?? '')) . '" class="regular-text"></label> ';
        echo '<label>Points <input type="number" min="0" step="0.25" name="' . esc_attr($prefix) . '[max_points]" value="' . esc_attr((string) ($exercise['max_points'] ?? '')) . '" class="small-text"></label> ';
        echo '<label><input type="checkbox" name="' . esc_attr($prefix) . '[is_active]" value="1" ' . checked((int) ($exercise['is_active'] ?? 1), 1, false) . '> actif</label></p>';
        echo '<p><label>Intro<br><textarea name="' . esc_attr($prefix) . '[intro_html]" rows="4" class="ouinpo-admin-full-width">' . esc_textarea((string) ($exercise['intro_html'] ?? '')) . '</textarea></label></p>';
        echo '<div class="ouinpo-written-questions">';
        foreach (array_values((array) ($exercise['questions'] ?? [])) as $question_index => $question) {
            self::render_question_block($exercise_index, $question_index, $question, $competencies);
        }
        echo '</div>';
        echo '<p><button type="button" class="button ouinpo-add-written-question">+ Ajouter une question</button></p>';
        echo '</section>';
    }

    private static function render_question_block(int $exercise_index, int $question_index, array $question, array $competencies): void
    {
        $prefix = 'written_exercises[' . $exercise_index . '][questions][' . $question_index . ']';
        $selected = array_map('intval', (array) ($question['competency_ids'] ?? []));
        echo '<div class="ouinpo-admin-subblock ouinpo-written-question">';
        echo '<h4>Question</h4>';
        echo '<input type="hidden" name="' . esc_attr($prefix) . '[id]" value="' . esc_attr((string) ($question['id'] ?? 0)) . '">';
        echo '<p><label>Ordre <input type="number" min="1" name="' . esc_attr($prefix) . '[question_order]" value="' . esc_attr((string) ($question['question_order'] ?? ($question_index + 1))) . '" class="small-text"></label> ';
        echo '<label>Label <input type="text" name="' . esc_attr($prefix) . '[question_label]" value="' . esc_attr((string) ($question['question_label'] ?? '')) . '" class="small-text"></label> ';
        echo '<label>Type <select name="' . esc_attr($prefix) . '[answer_type]">';
        foreach (['text' => 'Texte', 'code' => 'Code', 'sql' => 'SQL', 'mixed' => 'Mixte'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected((string) ($question['answer_type'] ?? 'text'), $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>Points <input type="number" min="0" step="0.25" name="' . esc_attr($prefix) . '[max_points]" value="' . esc_attr((string) ($question['max_points'] ?? '')) . '" class="small-text"></label> ';
        echo '<label><input type="checkbox" name="' . esc_attr($prefix) . '[is_active]" value="1" ' . checked((int) ($question['is_active'] ?? 1), 1, false) . '> actif</label></p>';
        echo '<p><label>Enonce de la question<br><textarea name="' . esc_attr($prefix) . '[prompt_html]" rows="5" class="ouinpo-admin-full-width">' . esc_textarea((string) ($question['prompt_html'] ?? '')) . '</textarea></label></p>';
        echo '<p><label>Competences BO liees<br><select name="' . esc_attr($prefix) . '[competency_ids][]" multiple size="5" class="ouinpo-admin-table-wide">';
        foreach ($competencies as $competency) {
            $id = (int) $competency['id'];
            $label = trim((string) $competency['track'] . ' - ' . (string) $competency['level'] . ' - ' . (string) $competency['domain'] . ' - ' . (string) $competency['competency']);
            echo '<option value="' . $id . '" ' . selected(in_array($id, $selected, true), true, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<div class="ouinpo-written-hints">';
        foreach (array_values((array) ($question['hints'] ?? [])) as $hint_index => $hint) {
            self::render_hint_block($exercise_index, $question_index, $hint_index, $hint);
        }
        echo '</div>';
        echo '<p><button type="button" class="button ouinpo-add-written-hint">+ Ajouter une aide IA</button></p>';
        echo '</div>';
    }

    private static function render_hint_block(int $exercise_index, int $question_index, int $hint_index, array $hint): void
    {
        $prefix = 'written_exercises[' . $exercise_index . '][questions][' . $question_index . '][hints][' . $hint_index . ']';
        echo '<div class="ouinpo-admin-subblock ouinpo-written-hint">';
        echo '<input type="hidden" name="' . esc_attr($prefix) . '[id]" value="' . esc_attr((string) ($hint['id'] ?? 0)) . '">';
        echo '<p><label>Ordre <input type="number" min="1" name="' . esc_attr($prefix) . '[hint_order]" value="' . esc_attr((string) ($hint['hint_order'] ?? ($hint_index + 1))) . '" class="small-text"></label> ';
        echo '<label>Titre <input type="text" name="' . esc_attr($prefix) . '[title]" value="' . esc_attr((string) ($hint['title'] ?? 'Aide IA')) . '" class="regular-text"></label></p>';
        echo '<p><label>Contenu<br><textarea name="' . esc_attr($prefix) . '[content]" rows="4" class="ouinpo-admin-full-width">' . esc_textarea((string) ($hint['content'] ?? '')) . '</textarea></label></p>';
        echo '</div>';
    }

    private static function encoded_key(string $name): string
    {
        return rtrim(strtr(base64_encode($name), '+/', '-_'), '=');
    }

    private static function decode_base64url(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);

        return is_string($decoded) ? $decoded : '';
    }

    private static function posted_encoded_text(string $name, string $fallback = ''): string
    {
        $encoded = $_POST['ouinpo_written_encoded'] ?? [];
        if (!is_array($encoded)) {
            return $fallback;
        }

        $key = self::encoded_key($name);
        if (!array_key_exists($key, $encoded)) {
            return $fallback;
        }

        return self::decode_base64url(wp_unslash((string) $encoded[$key]));
    }

    public static function handle_save(): void
    {
        if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) {
            wp_die('Acces refuse.');
        }

        check_admin_referer('ouinpo_save_written_subject');

        global $wpdb;

        $tS = self::table('written_subjects');
        $tSL = self::table('written_subject_school_level');
        $tE = self::table('written_exercises');
        $tQ = self::table('written_questions');
        $tQC = self::table('written_question_competency');
        $tH = self::table('written_question_hints');
        $tF = self::table('subject_files');

        $subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
        $title = sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? '')));
        $slug = sanitize_title(wp_unslash((string) ($_POST['slug'] ?? '')));
        if ($slug === '') {
            $slug = sanitize_title($title);
        }

        if ($title === '' || $slug === '') {
            wp_safe_redirect(self::redirect_url(['subject_id' => $subject_id]));
            exit;
        }

        $source_type = sanitize_key(wp_unslash((string) ($_POST['source_type'] ?? 'annale')));
        if (!in_array($source_type, ['annale', 'inspired', 'type_bac'], true)) {
            $source_type = 'annale';
        }

        $data = [
            'title' => $title,
            'slug' => $slug,
            'statement' => wp_kses_post(self::posted_encoded_text('statement', wp_unslash((string) ($_POST['statement'] ?? '')))),
            'source_type' => $source_type,
            'session_label' => sanitize_text_field(wp_unslash((string) ($_POST['session_label'] ?? ''))) ?: null,
            'year_label' => sanitize_text_field(wp_unslash((string) ($_POST['year_label'] ?? ''))) ?: null,
            'center_label' => sanitize_text_field(wp_unslash((string) ($_POST['center_label'] ?? ''))) ?: null,
            'subject_group' => sanitize_text_field(wp_unslash((string) ($_POST['subject_group'] ?? ''))) ?: null,
            'estimated_minutes' => trim((string) ($_POST['estimated_minutes'] ?? '')) !== '' ? max(1, (int) $_POST['estimated_minutes']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ];

        if ($subject_id > 0 && self::fetch_subject($subject_id)) {
            $wpdb->update($tS, $data, ['id' => $subject_id], null, ['%d']);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($tS, $data);
            $subject_id = (int) $wpdb->insert_id;
        }

        $wpdb->delete($tSL, ['subject_id' => $subject_id], ['%d']);
        $school_levels = isset($_POST['school_levels']) ? array_map('intval', (array) wp_unslash($_POST['school_levels'])) : [];
        foreach (array_values(array_unique($school_levels)) as $level_id) {
            if ($level_id > 0) {
                $wpdb->insert($tSL, ['subject_id' => $subject_id, 'school_level_id' => $level_id], ['%d', '%d']);
            }
        }

        $posted_exercises = isset($_POST['written_exercises']) && is_array($_POST['written_exercises'])
            ? wp_unslash($_POST['written_exercises'])
            : [];

        $kept_exercises = [];
        foreach ($posted_exercises as $exercise_index => $exercise_row) {
            if (!is_array($exercise_row)) {
                continue;
            }

            $exercise_id = (int) ($exercise_row['id'] ?? 0);
            $exercise_order = max(1, (int) ($exercise_row['exercise_order'] ?? 1));
            $exercise_title = sanitize_text_field((string) ($exercise_row['title'] ?? ''));
            $intro_name = 'written_exercises[' . $exercise_index . '][intro_html]';
            $intro_html = wp_kses_post(self::posted_encoded_text($intro_name, (string) ($exercise_row['intro_html'] ?? '')));
            $has_questions = !empty($exercise_row['questions']) && is_array($exercise_row['questions']);

            if ($exercise_title === '' && trim($intro_html) === '' && !$has_questions) {
                continue;
            }

            $exercise_data = [
                'subject_id' => $subject_id,
                'exercise_order' => $exercise_order,
                'title' => $exercise_title ?: null,
                'intro_html' => $intro_html ?: null,
                'max_points' => isset($exercise_row['max_points']) && $exercise_row['max_points'] !== '' ? (float) $exercise_row['max_points'] : null,
                'is_active' => !empty($exercise_row['is_active']) ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ];

            if ($exercise_id > 0) {
                $wpdb->update($tE, $exercise_data, ['id' => $exercise_id, 'subject_id' => $subject_id]);
            } else {
                $exercise_data['created_at'] = current_time('mysql');
                $wpdb->insert($tE, $exercise_data);
                $exercise_id = (int) $wpdb->insert_id;
            }

            if ($exercise_id <= 0) {
                continue;
            }

            $kept_exercises[] = $exercise_id;
            $kept_questions = [];

            foreach ((array) ($exercise_row['questions'] ?? []) as $question_index => $question_row) {
                if (!is_array($question_row)) {
                    continue;
                }

                $question_id = (int) ($question_row['id'] ?? 0);
                $prompt_name = 'written_exercises[' . $exercise_index . '][questions][' . $question_index . '][prompt_html]';
                $prompt_html = wp_kses_post(self::posted_encoded_text($prompt_name, (string) ($question_row['prompt_html'] ?? '')));
                $question_label = sanitize_text_field((string) ($question_row['question_label'] ?? ''));

                if ($question_label === '' && trim($prompt_html) === '') {
                    continue;
                }

                $answer_type = sanitize_key((string) ($question_row['answer_type'] ?? 'text'));
                if (!in_array($answer_type, ['text', 'code', 'sql', 'mixed'], true)) {
                    $answer_type = 'text';
                }

                $question_data = [
                    'exercise_id' => $exercise_id,
                    'question_order' => max(1, (int) ($question_row['question_order'] ?? 1)),
                    'question_label' => $question_label ?: (string) max(1, (int) ($question_row['question_order'] ?? 1)),
                    'prompt_html' => $prompt_html,
                    'answer_type' => $answer_type,
                    'max_points' => isset($question_row['max_points']) && $question_row['max_points'] !== '' ? (float) $question_row['max_points'] : null,
                    'is_active' => !empty($question_row['is_active']) ? 1 : 0,
                    'updated_at' => current_time('mysql'),
                ];

                if ($question_id > 0) {
                    $wpdb->update($tQ, $question_data, ['id' => $question_id, 'exercise_id' => $exercise_id]);
                } else {
                    $question_data['created_at'] = current_time('mysql');
                    $wpdb->insert($tQ, $question_data);
                    $question_id = (int) $wpdb->insert_id;
                }

                if ($question_id <= 0) {
                    continue;
                }

                $kept_questions[] = $question_id;

                $wpdb->delete($tQC, ['question_id' => $question_id], ['%d']);
                $competency_ids = isset($question_row['competency_ids']) ? array_map('intval', (array) $question_row['competency_ids']) : [];
                foreach (array_values(array_unique($competency_ids)) as $competency_id) {
                    if ($competency_id > 0) {
                        $wpdb->insert($tQC, ['question_id' => $question_id, 'competency_id' => $competency_id], ['%d', '%d']);
                    }
                }

                $kept_hints = [];
                foreach ((array) ($question_row['hints'] ?? []) as $hint_index => $hint_row) {
                    if (!is_array($hint_row)) {
                        continue;
                    }

                    $content_name = 'written_exercises[' . $exercise_index . '][questions][' . $question_index . '][hints][' . $hint_index . '][content]';
                    $content = wp_kses_post(self::posted_encoded_text($content_name, (string) ($hint_row['content'] ?? '')));
                    if (trim($content) === '') {
                        continue;
                    }

                    $hint_id = (int) ($hint_row['id'] ?? 0);
                    $hint_data = [
                        'question_id' => $question_id,
                        'hint_order' => max(1, (int) ($hint_row['hint_order'] ?? 1)),
                        'title' => sanitize_text_field((string) ($hint_row['title'] ?? 'Aide IA')) ?: null,
                        'content' => $content,
                        'is_ai' => 1,
                        'updated_at' => current_time('mysql'),
                    ];

                    if ($hint_id > 0) {
                        $wpdb->update($tH, $hint_data, ['id' => $hint_id, 'question_id' => $question_id]);
                    } else {
                        $hint_data['created_at'] = current_time('mysql');
                        $wpdb->insert($tH, $hint_data);
                        $hint_id = (int) $wpdb->insert_id;
                    }

                    if ($hint_id > 0) {
                        $kept_hints[] = $hint_id;
                    }
                }

                self::delete_missing_ids($tH, 'question_id', $question_id, $kept_hints);
            }

            self::delete_missing_ids($tQ, 'exercise_id', $exercise_id, $kept_questions);
        }

        self::delete_missing_ids($tE, 'subject_id', $subject_id, $kept_exercises);

        $file_errors = [];
        if ($subject_id > 0 && !empty($_FILES['written_files']) && is_array($_FILES['written_files'])) {
            $file_order = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(MAX(file_order), 0) FROM {$tF} WHERE subject_type = 'written' AND subject_id = %d",
                $subject_id
            ));
            foreach (self::normalize_uploaded_files($_FILES['written_files']) as $file) {
                $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($error !== UPLOAD_ERR_OK) {
                    $file_errors[] = 'Upload impossible pour un fichier.';
                    continue;
                }

                $stored = WrittenFiles::store_uploaded_file($file, $data['subject_group'] ?: $slug, $subject_id);
                if (is_wp_error($stored)) {
                    $file_errors[] = $stored->get_error_message();
                    continue;
                }

                $file_order++;
                $kind = strtolower((string) pathinfo((string) $stored['filename'], PATHINFO_EXTENSION)) === 'pdf' ? 'subject' : 'resource';
                $wpdb->insert($tF, [
                    'subject_type' => 'written',
                    'subject_id' => $subject_id,
                    'label' => (string) $stored['filename'],
                    'file_name' => (string) $stored['filename'],
                    'file_url' => (string) $stored['url'],
                    'file_kind' => $kind,
                    'file_order' => $file_order,
                    'created_at' => current_time('mysql'),
                ], ['%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s']);
            }
        }

        $redirect = ['subject_id' => $subject_id, 'saved' => 1];
        if ($file_errors) {
            $redirect['file_error'] = implode(' ', array_unique($file_errors));
        }

        wp_safe_redirect(self::redirect_url($redirect));
        exit;
    }

    private static function delete_missing_ids(string $table, string $owner_column, int $owner_id, array $kept_ids): void
    {
        global $wpdb;

        $kept_ids = array_values(array_filter(array_map('intval', $kept_ids)));
        if ($kept_ids) {
            $in = implode(',', $kept_ids);
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE {$owner_column} = %d AND id NOT IN ({$in})", $owner_id));
            return;
        }

        $wpdb->delete($table, [$owner_column => $owner_id], ['%d']);
    }

    public static function handle_delete_file(): void
    {
        if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) {
            wp_die('Acces refuse.');
        }

        $file_id = isset($_GET['file_id']) ? (int) $_GET['file_id'] : 0;
        $subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
        check_admin_referer('ouinpo_delete_written_file_' . $file_id);

        global $wpdb;
        $t = self::table('subject_files');
        $wpdb->delete($t, ['id' => $file_id, 'subject_type' => 'written'], ['%d', '%s']);

        wp_safe_redirect(self::redirect_url(['subject_id' => $subject_id, 'saved' => 1]));
        exit;
    }

    public static function handle_import_pdf(): void
    {
        if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) {
            wp_die('Acces refuse.');
        }

        check_admin_referer('ouinpo_import_written_pdf');

        $file = $_FILES['written_subject_pdf'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            wp_safe_redirect(self::redirect_url(['import_error' => 'PDF manquant ou upload impossible.']));
            exit;
        }

        $filename = sanitize_file_name((string) ($file['name'] ?? ''));
        if (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
            wp_safe_redirect(self::redirect_url(['import_error' => 'Seuls les PDF sont acceptes pour cet import.']));
            exit;
        }

        $check = wp_check_filetype_and_ext((string) ($file['tmp_name'] ?? ''), $filename, ['pdf' => 'application/pdf']);
        if (empty($check['ext']) || $check['ext'] !== 'pdf') {
            wp_safe_redirect(self::redirect_url(['import_error' => 'Le fichier ne semble pas etre un PDF valide.']));
            exit;
        }

        $fallback_title = sanitize_text_field(wp_unslash((string) ($_POST['fallback_title'] ?? 'Annale NSI')));
        $source_type = sanitize_key(wp_unslash((string) ($_POST['source_type'] ?? 'annale')));
        $school_levels = isset($_POST['school_levels']) ? array_map('intval', (array) wp_unslash($_POST['school_levels'])) : [];
        $stored = WrittenFiles::store_uploaded_file($file, $fallback_title !== '' ? $fallback_title : 'annale-nsi', 0);
        if (is_wp_error($stored)) {
            wp_safe_redirect(self::redirect_url(['import_error' => $stored->get_error_message()]));
            exit;
        }

        $importer = new WrittenSubjectPdfImporter();
        $result = $importer->import([
            'file_path' => (string) $stored['path'],
            'file_url' => (string) $stored['url'],
            'fallback_title' => $fallback_title,
            'source_type' => $source_type,
            'school_levels' => $school_levels,
        ]);

        if (is_wp_error($result)) {
            if (!empty($stored['path']) && is_file((string) $stored['path'])) {
                @unlink((string) $stored['path']);
            }
            wp_safe_redirect(self::redirect_url(['import_error' => $result->get_error_message()]));
            exit;
        }

        $subject_id = (int) ($result['subject_id'] ?? 0);
        if ($subject_id > 0) {
            self::attach_stored_subject_file($stored, $subject_id);
        }

        wp_safe_redirect(self::redirect_url(['subject_id' => $subject_id, 'saved' => 1]));
        exit;
    }

    private static function store_uploaded_subject_file(array $file, int $subject_id, string $folder_seed): void
    {
        if ($subject_id <= 0) {
            return;
        }

        $stored = WrittenFiles::store_uploaded_file($file, $folder_seed, $subject_id);
        if (is_wp_error($stored)) {
            return;
        }

        self::attach_stored_subject_file($stored, $subject_id);
    }

    private static function attach_stored_subject_file(array $stored, int $subject_id): void
    {
        if ($subject_id <= 0 || empty($stored['filename']) || empty($stored['url'])) {
            return;
        }

        global $wpdb;
        $tF = self::table('subject_files');
        $file_order = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(file_order), 0) FROM {$tF} WHERE subject_type = 'written' AND subject_id = %d",
            $subject_id
        ));

        $wpdb->insert($tF, [
            'subject_type' => 'written',
            'subject_id' => $subject_id,
            'label' => (string) $stored['filename'],
            'file_name' => (string) $stored['filename'],
            'file_url' => (string) $stored['url'],
            'file_kind' => 'subject',
            'file_order' => $file_order + 1,
            'created_at' => current_time('mysql'),
        ], ['%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s']);
    }
}
