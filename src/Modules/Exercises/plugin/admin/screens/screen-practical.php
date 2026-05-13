<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class ScreenPractical
{
    private static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    private static function redirect_url(array $args = []): string {
        return add_query_arg(
            array_merge(['page' => 'ouinpo-practical-subjects'], $args),
            admin_url('admin.php')
        );
    }

    public static function render(): void {
        if (!Capabilities::can(Capabilities::MANAGE_PRACTICAL_SUBJECTS)) {
            wp_die('Accès refusé.');
        }

        global $wpdb;

        $tExo   = self::table('exercises');
        $tExam  = self::table('exam_meta');
        $tDiff  = self::table('difficulties');
        $tCalls = self::table('practical_calls');
        $tESL   = self::table('exercise_school_level');
        $tLevel = self::table('school_levels');

        $subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;

        $subject = null;
        if ($subject_id > 0) {
            $subject = $wpdb->get_row($wpdb->prepare("
                SELECT
                    e.id,
                    e.title,
                    e.slug,
                    e.statement,
                    e.is_active,
                    e.difficulty_id,
                    em.source_type,
                    em.session_label,
                    em.year_label,
                    em.center_label,
                    em.theme_bac,
                    em.estimated_minutes,
                    em.subject_group
                FROM {$tExo} e
                INNER JOIN {$tExam} em ON em.exercise_id = e.id
                WHERE e.id = %d
                  AND em.exam_type = 'practical_subject'
                LIMIT 1
            ", $subject_id), ARRAY_A);
        }

        $levels = $wpdb->get_results("
            SELECT id, slug, label
            FROM {$tLevel}
            ORDER BY sort_order ASC, id ASC
        ", ARRAY_A);

        $selected_levels = [];
        if ($subject_id > 0) {
            $selected_levels = $wpdb->get_col($wpdb->prepare("
                SELECT school_level_id
                FROM {$tESL}
                WHERE exercise_id = %d
            ", $subject_id));
            $selected_levels = array_map('intval', (array) $selected_levels);
        }

        $difficulties = $wpdb->get_results("
            SELECT id, slug, label
            FROM {$tDiff}
            ORDER BY id ASC
        ", ARRAY_A);

        $calls = [];
        if ($subject_id > 0) {
            $call_rows = $wpdb->get_results($wpdb->prepare("
                SELECT id, call_order, title, prompt_html, ai_rubric, answer_mode, max_points, is_active
                FROM {$tCalls}
                WHERE exercise_id = %d
                ORDER BY call_order ASC, id ASC
            ", $subject_id), ARRAY_A);

            foreach ((array) $call_rows as $row) {
                $calls[(int) $row['call_order']] = $row;
            }
        }

        $subjects = $wpdb->get_results("
            SELECT
                e.id,
                e.title,
                e.slug,
                em.session_label,
                em.year_label,
                em.theme_bac,
                em.subject_group,
                e.is_active
            FROM {$tExo} e
            INNER JOIN {$tExam} em ON em.exercise_id = e.id
            WHERE em.exam_type = 'practical_subject'
            ORDER BY e.id DESC
        ", ARRAY_A);

        $theme_options = [
            'algorithmique' => 'Algorithmique',
            'programmation' => 'Programmation',
            'structures_de_donnees' => 'Structures de données',
            'bases_de_donnees_sql' => 'Bases de données / SQL',
            'reseaux_securite' => 'Réseaux et sécurité',
            'architecture_systemes' => 'Architecture et systèmes',
        ];

        echo '<div class="wrap">';
        echo '<h1>Sujets pratiques</h1>';

        if (isset($_GET['saved']) && $_GET['saved'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Sujet pratique enregistré.</p></div>';
        }

    echo '<div class="ouinpo-admin-practical-layout">';

    echo '<div class="postbox ouinpo-admin-postbox">';
    echo '<h2 class="ouinpo-admin-heading-topless">Sujets existants</h2>';

        if (!$subjects) {
            echo '<p>Aucun sujet pratique pour le moment.</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>ID</th><th>Titre</th><th>Session</th><th>Thème</th><th>Dossier</th><th></th>';
            echo '</tr></thead><tbody>';

            foreach ($subjects as $row) {
                $edit_url = esc_url(self::redirect_url(['subject_id' => (int) $row['id']]));
                echo '<tr>';
                echo '<td>' . (int) $row['id'] . '</td>';
                echo '<td>' . esc_html($row['title']) . '</td>';
                echo '<td>' . esc_html((string) $row['session_label']) . ' ' . esc_html((string) $row['year_label']) . '</td>';
                echo '<td>' . esc_html((string) $row['theme_bac']) . '</td>';
                echo '<td><code>' . esc_html((string) $row['subject_group']) . '</code></td>';
                echo '<td><a class="button button-small" href="' . $edit_url . '">Éditer</a></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

    echo '<p class="ouinpo-admin-form-spaced">';
        echo '<a class="button" href="' . esc_url(self::redirect_url()) . '">Nouveau sujet</a>';
        echo '</p>';

        echo '</div>';

    echo '<div class="postbox ouinpo-admin-postbox">';
    echo '<h2 class="ouinpo-admin-heading-topless">' . ($subject ? 'Éditer le sujet #' . (int) $subject['id'] : 'Créer un sujet pratique') . '</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ouinpo_save_practical_subject');
        echo '<input type="hidden" name="action" value="ouinpo_save_practical_subject">';
        echo '<input type="hidden" name="subject_id" value="' . (int) ($subject['id'] ?? 0) . '">';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="practical-title">Titre</label></th><td>';
        echo '<input name="title" id="practical-title" type="text" class="regular-text" value="' . esc_attr((string) ($subject['title'] ?? '')) . '" required>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-slug">Slug</label></th><td>';
        echo '<input name="slug" id="practical-slug" type="text" class="regular-text" value="' . esc_attr((string) ($subject['slug'] ?? '')) . '">';
        echo '<p class="description">Laisse vide pour le générer automatiquement.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Niveaux</th><td>';
        foreach ($levels as $level) {
            $checked = in_array((int) $level['id'], $selected_levels, true) ? 'checked' : '';
    echo '<label class="ouinpo-admin-inline-label">';
            echo '<input type="checkbox" name="school_levels[]" value="' . (int) $level['id'] . '" ' . $checked . '> ';
            echo esc_html($level['label']);
            echo '</label>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-difficulty">Difficulté</label></th><td>';
        echo '<select name="difficulty_id" id="practical-difficulty">';
        echo '<option value="">— Aucune —</option>';
        foreach ($difficulties as $difficulty) {
            $selected = selected((int) ($subject['difficulty_id'] ?? 0), (int) $difficulty['id'], false);
            echo '<option value="' . (int) $difficulty['id'] . '" ' . $selected . '>' . esc_html($difficulty['label']) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-source-type">Origine</label></th><td>';
        echo '<select name="source_type" id="practical-source-type">';
        foreach ([
            'annale'   => 'Annale',
            'inspired' => 'Inspiré annale',
            'type_bac' => 'Type bac',
        ] as $value => $label) {
            $selected = selected((string) ($subject['source_type'] ?? 'type_bac'), $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-session">Session</label></th><td>';
        echo '<input name="session_label" id="practical-session" type="text" class="regular-text" value="' . esc_attr((string) ($subject['session_label'] ?? '')) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-year">Année</label></th><td>';
        echo '<input name="year_label" id="practical-year" type="text" class="regular-text" value="' . esc_attr((string) ($subject['year_label'] ?? '')) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-center">Centre</label></th><td>';
        echo '<input name="center_label" id="practical-center" type="text" class="regular-text" value="' . esc_attr((string) ($subject['center_label'] ?? '')) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-theme">Thème bac</label></th><td>';
        echo '<select name="theme_bac" id="practical-theme">';
        echo '<option value="">— Choisir —</option>';
        foreach ($theme_options as $value => $label) {
            $selected = selected((string) ($subject['theme_bac'] ?? ''), $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-estimated">Durée estimée</label></th><td>';
        echo '<input name="estimated_minutes" id="practical-estimated" type="number" min="0" step="1" value="' . esc_attr((string) ($subject['estimated_minutes'] ?? '60')) . '"> minutes';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="practical-folder">Dossier logique</label></th><td>';
        echo '<input name="subject_group" id="practical-folder" type="text" class="regular-text" value="' . esc_attr((string) ($subject['subject_group'] ?? '')) . '">';
        echo '<p class="description">Exemple : <code>26_BCG_NSI_1</code></p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Énoncé</th><td>';
        wp_editor(
            (string) ($subject['statement'] ?? ''),
            'practical_statement_editor',
            [
                'textarea_name' => 'statement',
                'textarea_rows' => 14,
                'media_buttons' => false,
            ]
        );
        echo '</td></tr>';

        echo '</tbody></table>';

        echo '<hr>';
        echo '<h2>Appels évalués</h2>';
        echo '<p>On prépare 6 emplacements. Les lignes vides ne seront pas enregistrées.</p>';

        for ($i = 1; $i <= 6; $i++) {
            $call = $calls[$i] ?? [
                'id' => 0,
                'call_order' => $i,
                'title' => '',
                'prompt_html' => '',
                'ai_rubric' => '',
                'answer_mode' => 'code',
                'max_points' => '',
                'is_active' => 1,
            ];

        echo '<div class="ouinpo-admin-call-box">';
        echo '<h3 class="ouinpo-admin-heading-topless">Appel ' . $i . '</h3>';

            echo '<input type="hidden" name="calls[' . $i . '][id]" value="' . (int) $call['id'] . '">';
            echo '<input type="hidden" name="calls[' . $i . '][call_order]" value="' . $i . '">';

            echo '<p><label>Titre<br>';
            echo '<input type="text" class="regular-text" name="calls[' . $i . '][title]" value="' . esc_attr((string) $call['title']) . '">';
            echo '</label></p>';

            echo '<p><label>Mode de réponse<br>';
            echo '<select name="calls[' . $i . '][answer_mode]">';
            foreach (['text' => 'Texte', 'code' => 'Code', 'mixed' => 'Mixte'] as $value => $label) {
                $selected = selected((string) $call['answer_mode'], $value, false);
                echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</label></p>';

            echo '<p><label>Barème indicatif<br>';
            echo '<input type="number" step="0.25" min="0" name="calls[' . $i . '][max_points]" value="' . esc_attr((string) $call['max_points']) . '">';
            echo '</label></p>';

            echo '<p><label>Consigne visible (<code>prompt_html</code>)<br>';
        echo '<textarea name="calls[' . $i . '][prompt_html]" rows="5" class="ouinpo-admin-full-width">' . esc_textarea((string) $call['prompt_html']) . '</textarea>';
            echo '</label></p>';

            echo '<p><label>Rubric IA (<code>ai_rubric</code>)<br>';
        echo '<textarea name="calls[' . $i . '][ai_rubric]" rows="10" class="ouinpo-admin-full-width">' . esc_textarea((string) $call['ai_rubric']) . '</textarea>';
            echo '</label></p>';

            $checked = !empty($call['is_active']) ? 'checked' : '';
            echo '<p><label><input type="checkbox" name="calls[' . $i . '][is_active]" value="1" ' . $checked . '> Actif</label></p>';

            echo '</div>';
        }

        submit_button($subject ? 'Mettre à jour le sujet pratique' : 'Créer le sujet pratique');
        echo '</form>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public static function handle_save(): void {
        if (!Capabilities::can(Capabilities::MANAGE_PRACTICAL_SUBJECTS)) {
            wp_die('Accès refusé.');
        }

        check_admin_referer('ouinpo_save_practical_subject');

        global $wpdb;

        $tExo   = self::table('exercises');
        $tExam  = self::table('exam_meta');
        $tESL   = self::table('exercise_school_level');
        $tCalls = self::table('practical_calls');

        $subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0;
        
        $title = isset($_POST['title'])
            ? sanitize_text_field(wp_unslash((string) $_POST['title']))
            : '';
        
        $slug = isset($_POST['slug'])
            ? sanitize_title(wp_unslash((string) $_POST['slug']))
            : '';
        
        if ($slug === '') {
            $slug = sanitize_title($title);
        }
        
        $statement = isset($_POST['statement'])
            ? wp_kses_post(wp_unslash((string) $_POST['statement']))
            : '';
        
        $difficulty_id = !empty($_POST['difficulty_id']) ? (int) $_POST['difficulty_id'] : null;
        
        $source_type = isset($_POST['source_type'])
            ? sanitize_key(wp_unslash((string) $_POST['source_type']))
            : 'type_bac';
        
        $session_label = isset($_POST['session_label'])
            ? sanitize_text_field(wp_unslash((string) $_POST['session_label']))
            : '';
        
        $year_label = isset($_POST['year_label'])
            ? sanitize_text_field(wp_unslash((string) $_POST['year_label']))
            : '';
        
        $center_label = isset($_POST['center_label'])
            ? sanitize_text_field(wp_unslash((string) $_POST['center_label']))
            : '';
        
        $theme_bac = isset($_POST['theme_bac'])
            ? sanitize_key(wp_unslash((string) $_POST['theme_bac']))
            : '';
        
        $estimated_raw = isset($_POST['estimated_minutes'])
            ? trim(wp_unslash((string) $_POST['estimated_minutes']))
            : '';
        
        $estimated_minutes = $estimated_raw !== '' ? (int) $estimated_raw : null;
        
        $subject_group = isset($_POST['subject_group'])
            ? sanitize_text_field(wp_unslash((string) $_POST['subject_group']))
            : '';
        
        $school_levels = isset($_POST['school_levels']) && is_array($_POST['school_levels'])
            ? array_map('intval', wp_unslash($_POST['school_levels']))
            : [];
        if ($title === '') {
            wp_safe_redirect(self::redirect_url(['subject_id' => $subject_id]));
            exit;
        }

        if ($subject_id > 0) {
            $wpdb->update(
                $tExo,
                [
                    'title'         => $title,
                    'slug'          => $slug,
                    'statement'     => $statement,
                    'difficulty_id' => $difficulty_id,
                    'is_active'     => 1,
                ],
                ['id' => $subject_id],
                ['%s', '%s', '%s', '%d', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $tExo,
                [
                    'level_id'       => null,
                    'difficulty_id'  => $difficulty_id,
                    'title'          => $title,
                    'slug'           => $slug,
                    'statement'      => $statement,
                    'is_active'      => 1,
                    'created_at'     => current_time('mysql'),
                ],
                ['%d', '%d', '%s', '%s', '%s', '%d', '%s']
            );

            $subject_id = (int) $wpdb->insert_id;
        }

        $wpdb->replace(
            $tExam,
            [
                'exercise_id'        => $subject_id,
                'exam_type'          => 'practical_subject',
                'source_type'        => $source_type ?: 'type_bac',
                'session_label'      => $session_label ?: null,
                'year_label'         => $year_label ?: null,
                'center_label'       => $center_label ?: null,
                'theme_bac'          => $theme_bac ?: null,
                'estimated_minutes'  => $estimated_minutes,
                'is_exam_like'       => 1,
                'subject_group'      => $subject_group ?: null,
                'updated_at'         => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        $wpdb->delete($tESL, ['exercise_id' => $subject_id], ['%d']);
        foreach ($school_levels as $level_id) {
            if ($level_id > 0) {
                $wpdb->insert(
                    $tESL,
                    [
                        'exercise_id'     => $subject_id,
                        'school_level_id' => $level_id,
                    ],
                    ['%d', '%d']
                );
            }
        }

        $existing_calls = $wpdb->get_results($wpdb->prepare("
            SELECT id
            FROM {$tCalls}
            WHERE exercise_id = %d
        ", $subject_id), ARRAY_A);

        $existing_ids = array_map(static fn($r) => (int) $r['id'], (array) $existing_calls);
        $kept_ids = [];

        $posted_calls = isset($_POST['calls']) && is_array($_POST['calls'])
            ? wp_unslash($_POST['calls'])
            : [];
        
        foreach ($posted_calls as $row) {
            if (!is_array($row)) {
                continue;
            }
        
            $call_id = !empty($row['id']) ? (int) $row['id'] : 0;
            $call_order = !empty($row['call_order']) ? (int) $row['call_order'] : 0;
        
            $call_title = isset($row['title'])
                ? sanitize_text_field((string) $row['title'])
                : '';
        
            $prompt_html = isset($row['prompt_html'])
                ? wp_kses_post((string) $row['prompt_html'])
                : '';
        
            $ai_rubric = isset($row['ai_rubric'])
                ? sanitize_textarea_field((string) $row['ai_rubric'])
                : '';
        
            $answer_mode = isset($row['answer_mode'])
                ? sanitize_key((string) $row['answer_mode'])
                : 'code';
        
            $max_points = isset($row['max_points']) && $row['max_points'] !== ''
                ? (float) $row['max_points']
                : null;
        
            $is_active = !empty($row['is_active']) ? 1 : 0;

            if ($call_order <= 0) {
                continue;
            }

            if ($call_title === '' && trim($prompt_html) === '' && trim($ai_rubric) === '') {
                continue;
            }

            if (!in_array($answer_mode, ['text', 'code', 'mixed'], true)) {
                $answer_mode = 'code';
            }

            if ($call_id > 0 && in_array($call_id, $existing_ids, true)) {
                $wpdb->update(
                    $tCalls,
                    [
                        'call_order'  => $call_order,
                        'title'       => $call_title ?: null,
                        'prompt_html' => $prompt_html,
                        'ai_rubric'   => $ai_rubric ?: null,
                        'answer_mode' => $answer_mode,
                        'max_points'  => $max_points,
                        'is_active'   => $is_active,
                        'updated_at'  => current_time('mysql'),
                    ],
                    ['id' => $call_id],
                    ['%d', '%s', '%s', '%s', '%s', '%f', '%d', '%s'],
                    ['%d']
                );
                $kept_ids[] = $call_id;
            } else {
                $wpdb->insert(
                    $tCalls,
                    [
                        'exercise_id' => $subject_id,
                        'call_order'  => $call_order,
                        'title'       => $call_title ?: null,
                        'prompt_html' => $prompt_html,
                        'ai_rubric'   => $ai_rubric ?: null,
                        'answer_mode' => $answer_mode,
                        'max_points'  => $max_points,
                        'is_active'   => $is_active,
                        'created_at'  => current_time('mysql'),
                        'updated_at'  => current_time('mysql'),
                    ],
                    ['%d', '%d', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s']
                );
                $kept_ids[] = (int) $wpdb->insert_id;
            }
        }

        foreach ($existing_ids as $existing_id) {
            if (!in_array($existing_id, $kept_ids, true)) {
                $wpdb->update(
                    $tCalls,
                    [
                        'is_active'  => 0,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $existing_id],
                    ['%d', '%s'],
                    ['%d']
                );
            }
        }

        wp_safe_redirect(self::redirect_url([
            'subject_id' => $subject_id,
            'saved'      => 1,
        ]));
        exit;
    }
}
