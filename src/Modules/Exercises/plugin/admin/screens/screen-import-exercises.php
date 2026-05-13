<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;

if (!defined('ABSPATH')) exit;

class Screen_Import_Exercises {

    public static function render() {
        if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) {
            wp_die(__('Accès refusé.', 'ouinpo-exercises'));
        }

        global $wpdb;

        $table_exercises      = $wpdb->prefix . 'ouin_exo_exercises';
        $table_difficulties   = $wpdb->prefix . 'ouin_exo_difficulties';
        $table_levels         = $wpdb->prefix . 'ouin_exo_school_levels';
        $table_exo_level      = $wpdb->prefix . 'ouin_exo_exercise_school_level';
        $table_competencies   = $wpdb->prefix . 'ouin_exo_competencies';
        $table_exo_comp       = $wpdb->prefix . 'ouin_exo_exercise_competency';
        $table_hints          = $wpdb->prefix . 'ouin_exo_hints';
        $table_solutions      = $wpdb->prefix . 'ouin_exo_solutions';
        $table_exam_meta      = $wpdb->prefix . 'ouin_exo_exam_meta';

        $messages = [];
        $errors   = [];
        $infos    = [];

        $difficulty_map = [];
        foreach ($wpdb->get_results("SELECT id, slug FROM {$table_difficulties}", ARRAY_A) as $row) {
            $difficulty_map[mb_strtolower(trim((string) $row['slug']))] = (int) $row['id'];
        }

        $level_map = [];
        foreach ($wpdb->get_results("SELECT id, slug FROM {$table_levels}", ARRAY_A) as $row) {
            $level_map[mb_strtolower(trim((string) $row['slug']))] = (int) $row['id'];
        }

        $competency_map = [];
        foreach ($wpdb->get_results("SELECT id, slug FROM {$table_competencies} WHERE active = 1", ARRAY_A) as $row) {
            $competency_map[mb_strtolower(trim((string) $row['slug']))] = (int) $row['id'];
        }

        $import_stats = [
            'total'   => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('ouinpo_import_exercises');

            $update_existing   = !empty($_POST['update_existing']);
            $replace_hints     = !empty($_POST['replace_hints']);
            $replace_solutions = !empty($_POST['replace_solutions']);

            $infos[] = 'Formulaire reçu par le serveur. Traitement du fichier en cours.';
            $infos[] = $update_existing
                ? 'Mode : création des nouveaux exercices et mise à jour des slugs existants.'
                : 'Mode : création uniquement. Les slugs déjà existants seront ignorés.';

            if (empty($_FILES['ouinpo_exercises_csv']['tmp_name'])) {
                $errors[] = 'Aucun fichier CSV fourni.';
            } else {
                $tmp_name  = $_FILES['ouinpo_exercises_csv']['tmp_name'];
                $separator = self::detect_separator($tmp_name);
                $infos[]   = "Séparateur détecté : '{$separator}'.";

                $handle = fopen($tmp_name, 'r');
                if ($handle === false) {
                    $errors[] = 'Impossible de lire le fichier CSV.';
                } else {
                    $header = fgetcsv($handle, 0, $separator);
                    if ($header === false) {
                        $errors[] = 'Fichier CSV vide ou illisible.';
                    } else {
                        $header = self::normalize_header_row($header);
                        $cols   = self::build_header_map($header);

                        $required = ['title', 'slug', 'statement_html'];
                        foreach ($required as $col) {
                            if (!isset($cols[$col])) {
                                $errors[] = "Colonne requise manquante dans le CSV : {$col}";
                            }
                        }

                        if (empty($errors)) {
                            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                                if (self::is_blank_row($row)) {
                                    continue;
                                }

                                $import_stats['total']++;
                                $line_no = $import_stats['total'] + 1;

                                $title          = self::csv_value($row, $cols, ['title']);
                                $slug           = sanitize_title(self::csv_value($row, $cols, ['slug']));
                                $statement_html = self::csv_value($row, $cols, ['statement_html', 'statement', 'enonce_html']);

                                if ($title === '') {
                                    $import_stats['skipped']++;
                                    $errors[] = "Ligne {$line_no} : titre vide, ligne ignorée.";
                                    continue;
                                }

                                if ($slug === '') {
                                    $import_stats['skipped']++;
                                    $errors[] = "Ligne {$line_no} : slug vide ou invalide, ligne ignorée.";
                                    continue;
                                }

                                $difficulty_slug = mb_strtolower(self::csv_value($row, $cols, ['difficulty_slug', 'difficulty']));
                                $difficulty_id   = null;
                                if ($difficulty_slug !== '') {
                                    if (!isset($difficulty_map[$difficulty_slug])) {
                                        $import_stats['skipped']++;
                                        $errors[] = "Ligne {$line_no} : difficulté inconnue '{$difficulty_slug}'.";
                                        continue;
                                    }
                                    $difficulty_id = $difficulty_map[$difficulty_slug];
                                }

                                $is_active_raw = self::csv_value($row, $cols, ['is_active', 'active']);
                                $is_active     = self::normalize_bool($is_active_raw, 1);

                                $has_levels_col = self::has_any_column($cols, ['school_level_slugs', 'school_levels', 'school_level_slug', 'school_level']);
                                $has_comp_col   = self::has_any_column($cols, ['competency_slugs', 'competencies', 'competency_slug']);
                                $has_hints_col  = self::has_hint_columns($cols);
                                $has_sol_col    = self::has_solution_columns($cols);

                                $has_exam_col = self::has_any_column($cols, [
                                    'exam_type',
                                    'source_type',
                                    'session_label',
                                    'year_label',
                                    'center_label',
                                    'theme_bac',
                                    'bac_format',
                                    'estimated_minutes',
                                    'is_exam_like',
                                    'subject_group',
                                    'sort_in_subject'
                                ]);

                                $level_ids = self::parse_level_ids(
                                    self::csv_value($row, $cols, ['school_level_slugs', 'school_levels', 'school_level_slug', 'school_level']),
                                    $level_map,
                                    $line_no,
                                    $errors
                                );

                                $competency_ids = self::parse_competency_ids(
                                    self::csv_value($row, $cols, ['competency_slugs', 'competencies', 'competency_slug']),
                                    $competency_map,
                                    $line_no,
                                    $errors
                                );

                                $hints     = self::extract_hints($row, $cols);
                                $solutions = self::extract_solutions($row, $cols);
                                $exam_meta = self::extract_exam_meta($row, $cols, $line_no, $errors);

                                $title          = sanitize_text_field($title);
                                $statement_html = wp_kses_post($statement_html);

                                $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM {$table_exercises} WHERE slug = %s LIMIT 1",
                                    $slug
                                ));

                                if ($existing_id > 0 && !$update_existing) {
                                    $import_stats['skipped']++;
                                    $errors[] = "Ligne {$line_no} : un exercice avec le slug '{$slug}' existe déjà. Active la mise à jour si tu veux le remplacer.";
                                    continue;
                                }

                                $exercise_data = [
                                    'title'     => $title,
                                    'slug'      => $slug,
                                    'statement' => $statement_html,
                                    'is_active' => $is_active,
                                ];

                                if ($difficulty_id !== null) {
                                    $exercise_data['difficulty_id'] = $difficulty_id;
                                }

                                if ($existing_id > 0) {
                                    $updated = $wpdb->update(
                                        $table_exercises,
                                        $exercise_data,
                                        ['id' => $existing_id],
                                        self::infer_formats($exercise_data),
                                        ['%d']
                                    );

                                    if ($updated === false) {
                                        $import_stats['skipped']++;
                                        $errors[] = "Ligne {$line_no} : échec de mise à jour de l'exercice '{$slug}'. {$wpdb->last_error}";
                                        continue;
                                    }

                                    $exercise_id = $existing_id;
                                    $import_stats['updated']++;
                                } else {
                                    $exercise_data['created_at'] = current_time('mysql');

                                    $inserted = $wpdb->insert(
                                        $table_exercises,
                                        $exercise_data,
                                        self::infer_formats($exercise_data)
                                    );

                                    if (!$inserted) {
                                        $import_stats['skipped']++;
                                        $msg = "Ligne {$line_no} : échec d'insertion de l'exercice '{$slug}'.";
                                        if (!empty($wpdb->last_error)) {
                                            $msg .= ' ' . $wpdb->last_error;
                                        }
                                        $errors[] = $msg;
                                        continue;
                                    }

                                    $exercise_id = (int) $wpdb->insert_id;
                                    $import_stats['created']++;
                                }

                                if ($has_levels_col || $existing_id === 0) {
                                    self::sync_relations($wpdb, $table_exo_level, $exercise_id, 'school_level_id', $level_ids);
                                }

                                if ($has_comp_col || $existing_id === 0) {
                                    self::sync_relations($wpdb, $table_exo_comp, $exercise_id, 'competency_id', $competency_ids);
                                }

                                if (($replace_hints && $has_hints_col) || $existing_id === 0) {
                                    self::replace_hints($wpdb, $table_hints, $exercise_id, $hints);
                                }

                                if (($replace_solutions && $has_sol_col) || $existing_id === 0) {
                                    self::replace_solutions($wpdb, $table_solutions, $exercise_id, $solutions);
                                }
                                
                                if ($has_exam_col) {
                                    self::sync_exam_meta($wpdb, $table_exam_meta, $exercise_id, $exam_meta);
                                }
                            }
                        }
                    }

                    fclose($handle);
                }
            }

            if ($import_stats['created'] > 0 || $import_stats['updated'] > 0) {
                $messages[] = sprintf(
                    'Import terminé : %d créé(s), %d mis à jour, %d ignoré(s) sur %d ligne(s).',
                    $import_stats['created'],
                    $import_stats['updated'],
                    $import_stats['skipped'],
                    $import_stats['total']
                );
            } elseif ($import_stats['total'] > 0) {
                $errors[] = sprintf(
                    'Aucun exercice importé : %d ligne(s) ignorées sur %d.',
                    $import_stats['skipped'],
                    $import_stats['total']
                );
            }
        }

        $export_url = wp_nonce_url(
            admin_url('admin-post.php?action=ouinpo_export_exercises_csv'),
            'ouinpo_export_exercises_csv'
        );
        ?>
        <div class="wrap">
            <h1>Importer des exercices</h1>

            <?php if (!empty($infos)) : ?>
                <div class="notice notice-info is-dismissible">
                    <?php foreach ($infos as $i) : ?>
                        <p><?php echo esc_html($i); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($messages)) : ?>
                <div class="notice notice-success is-dismissible">
                    <?php foreach ($messages as $m) : ?>
                        <p><?php echo esc_html($m); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)) : ?>
                <div class="notice notice-error">
                    <?php foreach ($errors as $e) : ?>
                        <p><?php echo esc_html($e); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p>
                Cet importeur est aligné sur le modèle actuel des exercices : il peut créer ou mettre à jour un exercice,
                gérer plusieurs niveaux scolaires, les compétences BO, les trois indices et plusieurs corrigés.
            </p>

            <p>
                <a href="<?php echo esc_url($export_url); ?>" class="button">
                    Exporter les exercices existants au format CSV
                </a>
            </p>

            <h2>Colonnes du CSV</h2>
            <p>Colonnes minimales requises :</p>
            <ul>
                <li><code>title</code></li>
                <li><code>slug</code></li>
                <li><code>statement_html</code></li>
            </ul>

            <p>Colonnes optionnelles prises en charge :</p>
            <ul>
                <li><code>difficulty_slug</code> : <code>debutant</code>, <code>confirme</code>, <code>expert</code></li>
                <li><code>school_level_slugs</code> ou <code>school_level_slug</code> : <code>seconde|premiere|terminale</code></li>
                <li><code>competency_slugs</code> : slugs séparés par <code>|</code> ou <code>,</code></li>
                <li><code>is_active</code> : <code>1</code>, <code>0</code>, <code>true</code>, <code>false</code>, <code>oui</code>, <code>non</code></li>
                <li><code>hint_1</code>, <code>hint_2</code>, <code>hint_3</code></li>
                <li><code>solution_1_title</code>, <code>solution_1_html</code>, <code>solution_1_order</code>, <code>solution_1_official</code></li>
                <li>idem pour <code>solution_2_*</code>, <code>solution_3_*</code>, etc.</li>
                <li>alias simple accepté : <code>solution_html</code> pour un corrigé unique officiel</li>
                <li><code>source_type</code> : <code>annale</code>, <code>inspired</code>, <code>type_bac</code></li>
                <li><code>session_label</code> : ex. <code>Métropole Ex. 3</code></li>
                <li><code>exam_type</code> : <code>written</code> ou <code>practical_subject</code></li>
                <li><code>theme_bac</code> : ex. <code>algorithmique</code>, <code>programmation</code>, <code>structures_de_donnees</code>, <code>bases_de_donnees_sql</code>, <code>reseaux_securite</code>, <code>architecture_systemes</code></li>
                <li><code>year_label</code> : ex. <code>2024</code></li>
                <li><code>center_label</code> : ex. <code>Métropole</code>, <code>Polynésie</code></li>
                <li><code>bac_format</code> : <code>question_courte</code>, <code>lecture_code</code>, <code>code_a_completer</code>, <code>ecriture_complete</code>, <code>raisonnement</code></li>
                <li><code>estimated_minutes</code> : durée estimée en minutes</li>
                <li><code>is_exam_like</code> : <code>1</code> ou <code>0</code></li>
                <li><code>subject_group</code> : permet de regrouper plusieurs exos d’une même annale écrite</li>
                <li><code>sort_in_subject</code> : ordre dans ce groupe</li>
            </ul>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ouinpo_import_exercises'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ouinpo_exercises_csv">Fichier CSV</label></th>
                        <td>
                            <input type="file" name="ouinpo_exercises_csv" id="ouinpo_exercises_csv" accept=".csv,text/csv" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="update_existing" value="1">
                                Mettre à jour les exercices existants quand le slug existe déjà
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Indices</th>
                        <td>
                            <label>
                                <input type="checkbox" name="replace_hints" value="1" checked>
                                Remplacer les indices existants lors d’une mise à jour
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Corrigés</th>
                        <td>
                            <label>
                                <input type="checkbox" name="replace_solutions" value="1" checked>
                                Remplacer les corrigés existants lors d’une mise à jour
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Importer les exercices</button>
                </p>
            </form>

            <h2>Exemple</h2>
            <pre><?php echo esc_html(
            "title;slug;statement_html;difficulty_slug;school_level_slugs;competency_slugs;is_active;exam_type;source_type;session_label;year_label;center_label;theme_bac;bac_format;estimated_minutes;is_exam_like;subject_group;sort_in_subject;hint_1;hint_2;solution_1_title;solution_1_html;solution_1_official
            Parcours dans un graphe;parcours-graphe-annale;<p>...</p>;confirme;terminale;NSI-terminale-graphes-001;1;written;annale;Métropole Ex. 3;2024;Métropole;graphes;raisonnement;12;1;metropole-2024-sujet1;1;<p>Commence par identifier la structure.</p>;<p>Pense au BFS.</p>;Corrigé officiel;<pre><code>...</code></pre>;1"
            ); ?></pre>
        </div>
        <?php
    }

    public static function export_csv(): void {
        if (!Capabilities::can(Capabilities::MANAGE_EXERCISES)) {
            wp_die('Accès refusé.');
        }

        check_admin_referer('ouinpo_export_exercises_csv');

        global $wpdb;

        $table_exercises    = $wpdb->prefix . 'ouin_exo_exercises';
        $table_difficulties = $wpdb->prefix . 'ouin_exo_difficulties';
        $table_levels       = $wpdb->prefix . 'ouin_exo_school_levels';
        $table_exo_level    = $wpdb->prefix . 'ouin_exo_exercise_school_level';
        $table_competencies = $wpdb->prefix . 'ouin_exo_competencies';
        $table_exo_comp     = $wpdb->prefix . 'ouin_exo_exercise_competency';
        $table_hints        = $wpdb->prefix . 'ouin_exo_hints';
        $table_solutions    = $wpdb->prefix . 'ouin_exo_solutions';
        $table_exam_meta    = $wpdb->prefix . 'ouin_exo_exam_meta';
        
        $exam_meta_by_exercise = self::fetch_exam_meta_for_export($wpdb, $table_exam_meta);

        $exercises = $wpdb->get_results("
            SELECT
                e.id,
                e.title,
                e.slug,
                e.statement,
                e.is_active,
                d.slug AS difficulty_slug
            FROM {$table_exercises} e
            LEFT JOIN {$table_difficulties} d ON d.id = e.difficulty_id
            ORDER BY e.id ASC
        ", ARRAY_A);

        $levels_by_exercise = self::fetch_grouped_values($wpdb, "
            SELECT rel.exercise_id, lvl.slug AS value
            FROM {$table_exo_level} rel
            INNER JOIN {$table_levels} lvl ON lvl.id = rel.school_level_id
            ORDER BY rel.exercise_id ASC, lvl.slug ASC
        ");

        $competencies_by_exercise = self::fetch_grouped_values($wpdb, "
            SELECT rel.exercise_id, c.slug AS value
            FROM {$table_exo_comp} rel
            INNER JOIN {$table_competencies} c ON c.id = rel.competency_id
            ORDER BY rel.exercise_id ASC, c.slug ASC
        ");

        $hints_by_exercise     = self::fetch_hints_for_export($wpdb, $table_hints);
        $solutions_by_exercise = self::fetch_solutions_for_export($wpdb, $table_solutions);

        $max_solutions = 0;
        foreach ($solutions_by_exercise as $solutions) {
            $max_solutions = max($max_solutions, count($solutions));
        }
        $max_solutions = max(1, $max_solutions);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ouinpo-exercises-export-' . gmdate('Y-m-d-His') . '.csv');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            wp_die('Impossible de générer le CSV.');
        }

        fwrite($out, "\xEF\xBB\xBF");

        $header = [
            'title',
            'slug',
            'statement_html',
            'difficulty_slug',
            'school_level_slugs',
            'competency_slugs',
            'is_active',
            'exam_type',
            'source_type',
            'session_label',
            'year_label',
            'center_label',
            'theme_bac',
            'bac_format',
            'estimated_minutes',
            'is_exam_like',
            'subject_group',
            'sort_in_subject',
            'hint_1',
            'hint_2',
            'hint_3',
        ];

        for ($i = 1; $i <= $max_solutions; $i++) {
            $header[] = "solution_{$i}_title";
            $header[] = "solution_{$i}_html";
            $header[] = "solution_{$i}_order";
            $header[] = "solution_{$i}_official";
        }

        fputcsv($out, $header, ';');

        foreach ($exercises as $exercise) {
            $exercise_id = (int) $exercise['id'];
            $solutions   = $solutions_by_exercise[$exercise_id] ?? [];

            $row = [
                (string) $exercise['title'],
                (string) $exercise['slug'],
                (string) $exercise['statement'],
                (string) ($exercise['difficulty_slug'] ?? ''),
                implode('|', $levels_by_exercise[$exercise_id] ?? []),
                implode('|', $competencies_by_exercise[$exercise_id] ?? []),
                !empty($exercise['is_active']) ? '1' : '0',
                (string) ($exam_meta_by_exercise[$exercise_id]['exam_type'] ?? ''),
                (string) ($exam_meta_by_exercise[$exercise_id]['source_type'] ?? ''),
                (string) ($exam_meta_by_exercise[$exercise_id]['session_label'] ?? ''),
                (string) ($exam_meta_by_exercise[$exercise_id]['year_label'] ?? ''),
                (string) ($exam_meta_by_exercise[$exercise_id]['center_label'] ?? ''),
                (string) ($exam_meta_by_exercise[$exercise_id]['theme_bac'] ?? ''),
                (string) ($exam_meta_by_exercise[$exercise_id]['bac_format'] ?? ''),
                isset($exam_meta_by_exercise[$exercise_id]['estimated_minutes']) ? (string) $exam_meta_by_exercise[$exercise_id]['estimated_minutes'] : '',
                isset($exam_meta_by_exercise[$exercise_id]['is_exam_like']) ? (string) ((int) $exam_meta_by_exercise[$exercise_id]['is_exam_like']) : '',
                (string) ($exam_meta_by_exercise[$exercise_id]['subject_group'] ?? ''),
                isset($exam_meta_by_exercise[$exercise_id]['sort_in_subject']) ? (string) $exam_meta_by_exercise[$exercise_id]['sort_in_subject'] : '',
                (string) ($hints_by_exercise[$exercise_id][1] ?? ''),
                (string) ($hints_by_exercise[$exercise_id][2] ?? ''),
                (string) ($hints_by_exercise[$exercise_id][3] ?? ''),
            ];

            for ($i = 1; $i <= $max_solutions; $i++) {
                $sol = $solutions[$i - 1] ?? null;
                $row[] = $sol['title'] ?? '';
                $row[] = $sol['content'] ?? '';
                $row[] = isset($sol['solution_order']) ? (string) $sol['solution_order'] : '';
                $row[] = isset($sol['is_official']) ? (string) ((int) $sol['is_official']) : '';
            }

            fputcsv($out, $row, ';');
        }

        fclose($out);
        exit;
    }

    private static function fetch_exam_meta_for_export($wpdb, string $table_exam_meta): array {
        $rows = $wpdb->get_results("
            SELECT exercise_id, exam_type, source_type, session_label, year_label, center_label,
                   theme_bac, bac_format, estimated_minutes, is_exam_like, subject_group, sort_in_subject
            FROM {$table_exam_meta}
        ", ARRAY_A);
    
        $out = [];
    
        foreach ($rows as $row) {
            $exercise_id = (int) ($row['exercise_id'] ?? 0);
            if ($exercise_id <= 0) {
                continue;
            }
    
            $out[$exercise_id] = [
                'exam_type'         => (string) ($row['exam_type'] ?? ''),
                'source_type'       => (string) ($row['source_type'] ?? ''),
                'session_label'     => (string) ($row['session_label'] ?? ''),
                'year_label'        => (string) ($row['year_label'] ?? ''),
                'center_label'      => (string) ($row['center_label'] ?? ''),
                'theme_bac'         => (string) ($row['theme_bac'] ?? ''),
                'bac_format'        => (string) ($row['bac_format'] ?? ''),
                'estimated_minutes' => isset($row['estimated_minutes']) ? (int) $row['estimated_minutes'] : null,
                'is_exam_like'      => !empty($row['is_exam_like']) ? 1 : 0,
                'subject_group'     => (string) ($row['subject_group'] ?? ''),
                'sort_in_subject'   => isset($row['sort_in_subject']) ? (int) $row['sort_in_subject'] : null,
            ];
        }
    
        return $out;
    }

    private static function detect_separator(string $tmp_name): string {
        $sample = file_get_contents($tmp_name, false, null, 0, 4096);
        if ($sample === false) {
            return ';';
        }

        return substr_count($sample, ',') > substr_count($sample, ';') ? ',' : ';';
    }

    private static function normalize_header_row(array $header): array {
        foreach ($header as $i => $name) {
            $name = (string) $name;
            if ($i === 0) {
                $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
            }
            $header[$i] = trim(mb_strtolower($name));
        }
        return $header;
    }

    private static function build_header_map(array $header): array {
        $map = [];
        foreach ($header as $i => $name) {
            if ($name !== '') {
                $map[$name] = $i;
            }
        }
        return $map;
    }

    private static function has_any_column(array $cols, array $aliases): bool {
        foreach ($aliases as $alias) {
            if (isset($cols[$alias])) {
                return true;
            }
        }
        return false;
    }

    private static function has_hint_columns(array $cols): bool {
        for ($i = 1; $i <= 3; $i++) {
            if (isset($cols["hint_{$i}"]) || isset($cols["indice_{$i}"])) {
                return true;
            }
        }
        return false;
    }

    private static function has_solution_columns(array $cols): bool {
        foreach ($cols as $name => $_index) {
            if ($name === 'solution_html' || $name === 'solution_content' || preg_match('/^solution_\d+_html$/', $name)) {
                return true;
            }
        }
        return false;
    }

    private static function csv_value(array $row, array $cols, array $aliases): string {
        foreach ($aliases as $alias) {
            if (isset($cols[$alias])) {
                return trim((string) ($row[$cols[$alias]] ?? ''));
            }
        }
        return '';
    }

    private static function is_blank_row(array $row): bool {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private static function normalize_bool(string $raw, int $default = 1): int {
        $raw = trim(mb_strtolower($raw));
        if ($raw === '') {
            return $default;
        }
        if (in_array($raw, ['1', 'true', 'oui', 'yes', 'on'], true)) {
            return 1;
        }
        if (in_array($raw, ['0', 'false', 'non', 'no', 'off'], true)) {
            return 0;
        }
        return $default;
    }

    private static function parse_multi_slugs(string $raw): array {
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[|,]/', $raw);
        $out   = [];
        foreach ($parts as $part) {
            $slug = mb_strtolower(trim((string) $part));
            if ($slug !== '') {
                $out[] = $slug;
            }
        }
        return array_values(array_unique($out));
    }

    private static function parse_level_ids(string $raw, array $level_map, int $line_no, array &$errors): array {
        $slugs = self::parse_multi_slugs($raw);
        $ids   = [];

        foreach ($slugs as $slug) {
            if (!isset($level_map[$slug])) {
                $errors[] = "Ligne {$line_no} : niveau scolaire inconnu '{$slug}'.";
                continue;
            }
            $ids[] = (int) $level_map[$slug];
        }

        return array_values(array_unique($ids));
    }

    private static function parse_competency_ids(string $raw, array $competency_map, int $line_no, array &$errors): array {
        $slugs = self::parse_multi_slugs($raw);
        $ids   = [];

        foreach ($slugs as $slug) {
            if (!isset($competency_map[$slug])) {
                $errors[] = "Ligne {$line_no} : compétence inconnue '{$slug}'.";
                continue;
            }
            $ids[] = (int) $competency_map[$slug];
        }

        return array_values(array_unique($ids));
    }

    private static function extract_hints(array $row, array $cols): array {
        $hints = [];
        for ($i = 1; $i <= 3; $i++) {
            $value = self::csv_value($row, $cols, ["hint_{$i}", "indice_{$i}"]);
            if ($value !== '') {
                $hints[$i] = wp_kses_post($value);
            }
        }
        return $hints;
    }

    private static function extract_solutions(array $row, array $cols): array {
        $solutions = [];

        if (isset($cols['solution_html']) || isset($cols['solution_content'])) {
            $content = self::csv_value($row, $cols, ['solution_html', 'solution_content']);
            if ($content !== '') {
                $solutions[] = [
                    'title'          => self::csv_value($row, $cols, ['solution_title']) ?: 'Corrigé officiel',
                    'content'        => wp_kses_post($content),
                    'solution_order' => 1,
                    'is_official'    => 1,
                ];
            }
        }

        foreach ($cols as $name => $index) {
            if (!preg_match('/^solution_(\d+)_html$/', $name, $m)) {
                continue;
            }
            $n       = (int) $m[1];
            $content = trim((string) ($row[$index] ?? ''));
            if ($content === '') {
                continue;
            }
            $solutions[] = [
                'title'          => self::csv_value($row, $cols, ["solution_{$n}_title"]) ?: "Corrigé {$n}",
                'content'        => wp_kses_post($content),
                'solution_order' => max(1, (int) (self::csv_value($row, $cols, ["solution_{$n}_order"]) ?: $n)),
                'is_official'    => self::normalize_bool(self::csv_value($row, $cols, ["solution_{$n}_official"]), $n === 1 ? 1 : 0),
            ];
        }

        usort($solutions, static function(array $a, array $b): int {
            if ($a['solution_order'] === $b['solution_order']) {
                return (int) $b['is_official'] <=> (int) $a['is_official'];
            }
            return (int) $a['solution_order'] <=> (int) $b['solution_order'];
        });

        return $solutions;
    }

    private static function infer_formats(array $data): array {
        $formats = [];
        foreach ($data as $value) {
            $formats[] = is_int($value) ? '%d' : '%s';
        }
        return $formats;
    }

    private static function sync_relations($wpdb, string $table, int $exercise_id, string $column, array $ids): void {
        $wpdb->delete($table, ['exercise_id' => $exercise_id], ['%d']);
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            if ($id <= 0) {
                continue;
            }
            $wpdb->insert($table, [
                'exercise_id' => $exercise_id,
                $column       => $id,
            ], ['%d', '%d']);
        }
    }

    private static function replace_hints($wpdb, string $table_hints, int $exercise_id, array $hints): void {
        $wpdb->delete($table_hints, ['exercise_id' => $exercise_id], ['%d']);
        foreach ($hints as $order => $content) {
            if (trim((string) $content) === '') {
                continue;
            }
            $wpdb->insert($table_hints, [
                'exercise_id' => $exercise_id,
                'hint_order'  => (int) $order,
                'content'     => $content,
            ], ['%d', '%d', '%s']);
        }
    }

    private static function replace_solutions($wpdb, string $table_solutions, int $exercise_id, array $solutions): void {
        $wpdb->delete($table_solutions, ['exercise_id' => $exercise_id], ['%d']);
        foreach ($solutions as $solution) {
            if (trim((string) ($solution['content'] ?? '')) === '') {
                continue;
            }
            $wpdb->insert($table_solutions, [
                'exercise_id'    => $exercise_id,
                'title'          => sanitize_text_field((string) ($solution['title'] ?? 'Corrigé')),
                'content'        => (string) $solution['content'],
                'solution_order' => max(1, (int) ($solution['solution_order'] ?? 1)),
                'is_official'    => !empty($solution['is_official']) ? 1 : 0,
                'created_at'     => current_time('mysql'),
                'updated_at'     => current_time('mysql'),
            ], ['%d', '%s', '%s', '%d', '%d', '%s', '%s']);
        }
    }

    private static function fetch_grouped_values($wpdb, string $sql): array {
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $out  = [];

        foreach ($rows as $row) {
            $exercise_id = (int) ($row['exercise_id'] ?? 0);
            $value       = trim((string) ($row['value'] ?? ''));

            if ($exercise_id <= 0 || $value === '') {
                continue;
            }

            $out[$exercise_id][] = $value;
        }

        foreach ($out as $exercise_id => $values) {
            $out[$exercise_id] = array_values(array_unique($values));
        }

        return $out;
    }

    private static function fetch_hints_for_export($wpdb, string $table_hints): array {
        $rows = $wpdb->get_results("
            SELECT exercise_id, hint_order, content
            FROM {$table_hints}
            ORDER BY exercise_id ASC, hint_order ASC
        ", ARRAY_A);

        $out = [];

        foreach ($rows as $row) {
            $exercise_id = (int) ($row['exercise_id'] ?? 0);
            $hint_order  = (int) ($row['hint_order'] ?? 0);

            if ($exercise_id <= 0 || $hint_order < 1 || $hint_order > 3) {
                continue;
            }

            $out[$exercise_id][$hint_order] = (string) ($row['content'] ?? '');
        }

        return $out;
    }

    private static function fetch_solutions_for_export($wpdb, string $table_solutions): array {
        $rows = $wpdb->get_results("
            SELECT exercise_id, title, content, solution_order, is_official
            FROM {$table_solutions}
            ORDER BY exercise_id ASC, solution_order ASC, is_official DESC
        ", ARRAY_A);

        $out = [];

        foreach ($rows as $row) {
            $exercise_id = (int) ($row['exercise_id'] ?? 0);

            if ($exercise_id <= 0) {
                continue;
            }

            $out[$exercise_id][] = [
                'title'          => (string) ($row['title'] ?? ''),
                'content'        => (string) ($row['content'] ?? ''),
                'solution_order' => (int) ($row['solution_order'] ?? 1),
                'is_official'    => !empty($row['is_official']) ? 1 : 0,
            ];
        }

        return $out;
    }
    
    private static function extract_exam_meta(array $row, array $cols, int $line_no, array &$errors): array {
    $exam_type       = mb_strtolower(self::csv_value($row, $cols, ['exam_type']));
    $source_type     = mb_strtolower(self::csv_value($row, $cols, ['source_type']));
    $session_label   = self::csv_value($row, $cols, ['session_label']);
    $year_label      = self::csv_value($row, $cols, ['year_label']);
    $center_label    = self::csv_value($row, $cols, ['center_label']);
    $theme_bac       = self::csv_value($row, $cols, ['theme_bac']);
    $bac_format      = mb_strtolower(self::csv_value($row, $cols, ['bac_format']));
    $estimated_raw   = self::csv_value($row, $cols, ['estimated_minutes']);
    $is_exam_like    = self::normalize_bool(self::csv_value($row, $cols, ['is_exam_like']), 1);
    $subject_group   = self::csv_value($row, $cols, ['subject_group']);
    $sort_raw        = self::csv_value($row, $cols, ['sort_in_subject']);

    if ($exam_type === '') {
        $exam_type = 'written';
    }
    
    if ($exam_type === 'practical_question') {
        $exam_type = 'practical_subject';
    }
    
    if (!in_array($exam_type, ['written', 'practical_subject'], true)) {
        $errors[] = "Ligne {$line_no} : exam_type invalide '{$exam_type}'.";
        $exam_type = 'written';
    }

    if ($source_type === '') {
        $source_type = '';
    } elseif (!in_array($source_type, ['annale', 'inspired', 'type_bac', 'classic'], true)) {
        $errors[] = "Ligne {$line_no} : source_type invalide '{$source_type}'.";
        $source_type = '';
    }

    if ($bac_format === '') {
        $bac_format = '';
    } elseif (!in_array($bac_format, ['question_courte', 'lecture_code', 'code_a_completer', 'ecriture_complete', 'raisonnement'], true)) {
        $errors[] = "Ligne {$line_no} : bac_format invalide '{$bac_format}'.";
        $bac_format = '';
    }

    $estimated_minutes = null;
    if ($estimated_raw !== '') {
        $estimated_minutes = max(1, (int) $estimated_raw);
    }

    $sort_in_subject = null;
    if ($sort_raw !== '') {
        $sort_in_subject = max(1, (int) $sort_raw);
    }

    return [
        'exam_type'       => $exam_type,
        'source_type'     => $source_type,
        'session_label'   => sanitize_text_field($session_label),
        'year_label'      => sanitize_text_field($year_label),
        'center_label'    => sanitize_text_field($center_label),
        'theme_bac'       => sanitize_text_field($theme_bac),
        'bac_format'      => $bac_format,
        'estimated_minutes' => $estimated_minutes,
        'is_exam_like'    => $is_exam_like,
        'subject_group'   => sanitize_text_field($subject_group),
        'sort_in_subject' => $sort_in_subject,
    ];
}  

    private static function exam_meta_is_empty(array $meta): bool {
        return
            ($meta['source_type'] ?? '') === '' &&
            ($meta['session_label'] ?? '') === '' &&
            ($meta['year_label'] ?? '') === '' &&
            ($meta['center_label'] ?? '') === '' &&
            ($meta['theme_bac'] ?? '') === '' &&
            ($meta['bac_format'] ?? '') === '' &&
            empty($meta['estimated_minutes']) &&
            ($meta['subject_group'] ?? '') === '' &&
            empty($meta['sort_in_subject']);
    }
    
    private static function sync_exam_meta($wpdb, string $table_exam_meta, int $exercise_id, array $exam_meta): void {
        if (self::exam_meta_is_empty($exam_meta)) {
            $wpdb->delete($table_exam_meta, ['exercise_id' => $exercise_id], ['%d']);
            return;
        }
    
        $data = [
            'exercise_id'        => $exercise_id,
            'exam_type'          => $exam_meta['exam_type'],
            'source_type'        => $exam_meta['source_type'] ?: 'type_bac',
            'session_label'      => $exam_meta['session_label'],
            'year_label'         => $exam_meta['year_label'],
            'center_label'       => $exam_meta['center_label'],
            'theme_bac'          => $exam_meta['theme_bac'],
            'bac_format'         => $exam_meta['bac_format'] ?: null,
            'estimated_minutes'  => $exam_meta['estimated_minutes'],
            'is_exam_like'       => !empty($exam_meta['is_exam_like']) ? 1 : 0,
            'subject_group'      => $exam_meta['subject_group'],
            'sort_in_subject'    => $exam_meta['sort_in_subject'],
            'created_at'         => current_time('mysql'),
            'updated_at'         => current_time('mysql'),
        ];
    
        $wpdb->replace(
            $table_exam_meta,
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s']
        );
    }
    
}
