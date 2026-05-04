<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Exercises\AssessmentsService;

if (!defined('ABSPATH')) exit;

class Screen_Assessment_Builder {
    private const PAGE_SLUG = 'ouinpo-assessment-builder';
    private const NONCE_ACTION = 'ouinpo_assessment_builder';
    private const NONCE_NAME = 'ouinpo_assessment_builder_nonce';

    public static function render(): void {
        if (!current_user_can('edit_posts') && !current_user_can('edit_users')) {
            wp_die('Accès refusé.');
        }

        self::load_dependencies();
        self::enqueue_script();
        self::ensure_assessment_item_columns();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handle_post();
        }

        $filters = self::current_filters();
        $groups = self::get_groups();
        $levels = self::get_levels();
        $difficulties = self::get_difficulties();
        $domains = self::get_domains($filters);
        $competencies = self::get_competencies($filters);
        $exercises = self::get_exercises($filters);
        $selected_group = !empty($filters['group_id']) ? self::get_group((int) $filters['group_id']) : null;

        $class_student_count = !empty($filters['group_id'])
            ? self::count_group_students((int) $filters['group_id'])
            : 0;
        
        $class_done_map = !empty($filters['group_id'])
            ? self::class_exercise_done_map((int) $filters['group_id'])
            : [];
        
        foreach ($exercises as $exo) {
            $stats = $class_done_map[(int) $exo->id] ?? ['attempted' => 0, 'solved' => 0];
        
            $exo->class_student_count = $class_student_count;
            $exo->class_attempted_count = (int) ($stats['attempted'] ?? 0);
            $exo->class_solved_count = (int) ($stats['solved'] ?? 0);
        }
        
        $suggested_ids = self::suggested_exercise_ids($exercises, $filters);
        $missing_suggested_competencies = self::missing_suggested_competencies($exercises, $filters, $suggested_ids);

        settings_errors('ouinpo_assessment_builder');
        ?>
        <h1 class="wp-heading-inline">Concepteur de devoirs</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-assessments')); ?>" class="page-title-action">Voir les DS</a>
        <hr class="wp-header-end">

        <div class="notice notice-info">
            <p>Ce concepteur compose un DS à partir des exercices existants. Les sujets pratiques complets sont exclus par défaut.</p>
        </div>

        <?php if (!empty($missing_suggested_competencies)): ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Attention :</strong>
                    aucune proposition automatique n’a pu couvrir certaines compétences sélectionnées avec les filtres actuels.
                </p>
                <ul>
                    <?php foreach ($missing_suggested_competencies as $label): ?>
                        <li><?php echo esc_html($label); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    Essaie d’élargir les filtres : difficulté, type bac / annale, ou durée cible.
                </p>
            </div>
        <?php endif; ?>

        <div class="ouinpo-builder-layout">
            <div class="ouinpo-builder-card">
                <h2>1. Filtrer les exercices</h2>

                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">

                    <div class="ouinpo-builder-field">
                        <label for="builder-group">Classe</label>
                        <select id="builder-group" name="group_id">
                            <option value="0">— Toutes / non précisée —</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo (int) $group->id; ?>" <?php selected((int) $filters['group_id'], (int) $group->id); ?>>
                                    <?php echo esc_html($group->label . (!empty($group->year_slug) ? ' — ' . $group->year_slug : '') . (!empty($group->level_label) ? ' — ' . $group->level_label : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ouinpo-builder-field">
                        <label for="builder-level">Niveau</label>
                        <select id="builder-level" name="level_id">
                            <option value="0">Auto / tous</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo (int) $level->id; ?>" <?php selected((int) $filters['level_id'], (int) $level->id); ?>>
                                    <?php echo esc_html($level->label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ouinpo-builder-field">
                        <label for="builder-difficulty">Difficulté</label>
                        <select id="builder-difficulty" name="difficulty_id">
                            <option value="0">Toutes</option>
                            <?php foreach ($difficulties as $difficulty): ?>
                                <option value="<?php echo (int) $difficulty->id; ?>" <?php selected((int) $filters['difficulty_id'], (int) $difficulty->id); ?>>
                                    <?php echo esc_html($difficulty->label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ouinpo-builder-field">
                        <label for="builder-target-filter">Durée cible</label>
                        <input
                            id="builder-target-filter"
                            type="number"
                            min="10"
                            step="5"
                            name="target_minutes"
                            value="<?php echo (int) $filters['target_minutes']; ?>"
                        >
                    </div>
                    
                    <div class="ouinpo-builder-field">
                        <label>
                            <input type="checkbox" name="auto_suggest" value="1" <?php checked(!empty($filters['auto_suggest'])); ?>>
                            Proposer automatiquement une sélection
                        </label>
                    </div>
                    
                    <div class="ouinpo-builder-field">
                        <label>
                            <input type="checkbox" name="avoid_class_done" value="1" <?php checked(!empty($filters['avoid_class_done'])); ?>>
                            Défavoriser les exercices déjà faits par la classe
                        </label>
                    </div>

                    <div class="ouinpo-builder-field">
                        <label for="builder-domain">Domaines BO</label>
                        <select id="builder-domain" name="domain_slugs[]" multiple size="6">
                            <?php foreach ($domains as $domain): ?>
                                <option
                                    value="<?php echo esc_attr($domain->domain_slug); ?>"
                                    <?php selected(in_array((string) $domain->domain_slug, (array) $filters['domain_slugs'], true)); ?>
                                >
                                    <?php echo esc_html($domain->domain); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Maintiens Ctrl ou Cmd pour choisir plusieurs domaines.</p>
                    </div>

                    <div class="ouinpo-builder-field">
                        <label for="builder-competency">Compétences BO</label>
                        <select id="builder-competency" name="competency_ids[]" multiple size="8">
                            <?php foreach ($competencies as $comp): ?>
                                <option
                                    value="<?php echo (int) $comp->id; ?>"
                                    <?php selected(in_array((int) $comp->id, (array) $filters['competency_ids'], true)); ?>
                                >
                                    <?php echo esc_html($comp->domain . ' — ' . wp_trim_words((string) $comp->competency, 14)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Laisse vide pour inclure toutes les compétences compatibles avec les autres filtres.</p>
                    </div>

                    <div class="ouinpo-builder-field">
                        <label for="builder-source">Type bac / annale</label>
                        <select id="builder-source" name="source_type">
                            <option value="">Tous</option>
                            <option value="type_bac" <?php selected($filters['source_type'], 'type_bac'); ?>>Type bac</option>
                            <option value="inspired" <?php selected($filters['source_type'], 'inspired'); ?>>Inspiré d’annales</option>
                            <option value="annale" <?php selected($filters['source_type'], 'annale'); ?>>Annale</option>
                        </select>
                    </div>

                    <div class="ouinpo-builder-field">
                        <label>
                            <input type="checkbox" name="exam_only" value="1" <?php checked(!empty($filters['exam_only'])); ?>>
                            Exercices type bac / annales uniquement
                        </label>
                    </div>

                    <div class="ouinpo-builder-field">
                        <label for="builder-q">Recherche</label>
                        <input id="builder-q" type="text" name="q" value="<?php echo esc_attr($filters['q']); ?>" placeholder="Titre, slug, énoncé...">
                    </div>

                    <p>
                        <button type="submit" class="button button-primary">Filtrer</button>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>">Réinitialiser</a>
                    </p>
                </form>

                <hr>

                <h2>2. Panier</h2>

                <div class="ouinpo-builder-kpis">
                    <div class="ouinpo-builder-kpi"><span>Exos</span><strong id="ouinpo-builder-count">0</strong></div>
                    <div class="ouinpo-builder-kpi"><span>Durée</span><strong><span id="ouinpo-builder-minutes">0</span> min</strong></div>
                    <div class="ouinpo-builder-kpi"><span>Points</span><strong id="ouinpo-builder-points">0</strong></div>
                </div>

                <div id="ouinpo-builder-warning"></div>
            </div>

            <div>
                <form method="post" id="ouinpo-builder-create-form">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                    <input type="hidden" name="op" value="create_assessment_from_builder">

                    <div class="ouinpo-builder-card ouinpo-builder-card--summary">
                        <h2>Informations du devoir</h2>

                        <div class="ouinpo-builder-field">
                            <label for="assessment-title">Titre</label>
                            <input type="text" id="assessment-title" name="title" required value="<?php echo esc_attr(self::default_title($selected_group)); ?>">
                        </div>

                        <div class="ouinpo-builder-field">
                            <label for="assessment-date">Date</label>
                            <input type="date" id="assessment-date" name="due_on" required value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                        </div>

                        <div class="ouinpo-builder-field">
                            <label for="assessment-group">Classe</label>
                            <select id="assessment-group" name="group_id" required>
                                <option value="0">— Choisir —</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo (int) $group->id; ?>" <?php selected((int) $filters['group_id'], (int) $group->id); ?>>
                                        <?php echo esc_html($group->label . (!empty($group->year_slug) ? ' — ' . $group->year_slug : '') . (!empty($group->level_label) ? ' — ' . $group->level_label : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ouinpo-builder-field">
                            <label for="target-minutes">Durée cible en minutes</label>
                            <input type="number" min="0" step="5" id="target-minutes" name="target_minutes" value="<?php echo (int) $filters['target_minutes']; ?>">
                        </div>

                        <div class="ouinpo-builder-field">
                            <label for="assessment-notes">Notes prof</label>
                            <textarea id="assessment-notes" name="notes" rows="3">Créé depuis le concepteur de devoirs.</textarea>
                        </div>
                    </div>

                    <div class="ouinpo-builder-card">
                        <h2>3. Choisir les exercices</h2>

                        <p class="description">
                            <?php echo count($exercises); ?> exercice(s) affiché(s). Les compétences du DS seront déduites automatiquement de la sélection.
                        </p>

                        <?php if (empty($exercises)): ?>
                            <p>Aucun exercice ne correspond aux filtres.</p>
                        <?php else: ?>
                            <?php foreach ($exercises as $exo): ?>
                                <?php
                                $estimated = !empty($exo->estimated_minutes) ? (int) $exo->estimated_minutes : self::estimated_minutes_from_difficulty((string) $exo->difficulty_slug);
                                $points = self::suggested_points((string) $exo->difficulty_slug, $estimated);
                                $competency_labels = self::parse_competency_labels((string) $exo->competency_labels);
                                ?>

                                <div class="ouinpo-builder-exo" data-minutes="<?php echo (int) $estimated; ?>">
                                    <div>
                                        <input
                                            type="checkbox"
                                            class="ouinpo-builder-check"
                                            name="exercise_ids[]"
                                            value="<?php echo (int) $exo->id; ?>"
                                            <?php checked(in_array((int) $exo->id, $suggested_ids, true)); ?>
                                        >
                                    </div>

                                    <div>
                                        <div class="ouinpo-builder-title">
                                            #<?php echo (int) $exo->id; ?> — <?php echo esc_html($exo->title); ?>
                                        </div>

                                        <div class="ouinpo-builder-meta">
                                            <?php echo esc_html($exo->difficulty_label ?: 'Difficulté non renseignée'); ?>
                                            · <?php echo (int) $estimated; ?> min
                                            <?php if (!empty($exo->source_type) && in_array((string) $exo->source_type, ['annale', 'inspired', 'type_bac'], true)): ?>
                                                · <?php echo esc_html(self::source_label((string) $exo->source_type)); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($exo->center_label) || !empty($exo->session_label) || !empty($exo->year_label)): ?>
                                                · <?php echo esc_html(trim(($exo->center_label ?: '') . ' ' . ($exo->session_label ?: '') . ' ' . ($exo->year_label ?: ''))); ?>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($competency_labels)): ?>
                                            <div class="ouinpo-builder-tags">
                                                <?php foreach (array_slice($competency_labels, 0, 4) as $label): ?>
                                                    <span class="ouinpo-builder-tag"><?php echo esc_html($label); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($competency_labels) > 4): ?>
                                                    <span class="ouinpo-builder-tag">+<?php echo count($competency_labels) - 4; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($exo->class_student_count)): ?>
                                            <div class="ouinpo-builder-meta">
                                                Déjà tenté par <?php echo (int) $exo->class_attempted_count; ?>/<?php echo (int) $exo->class_student_count; ?>
                                                <?php if ((int) $exo->class_solved_count > 0): ?>
                                                    · réussi par <?php echo (int) $exo->class_solved_count; ?>/<?php echo (int) $exo->class_student_count; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                    </div>

                                    <div>
                                        <label>Ordre</label><br>
                                        <input class="ouinpo-builder-small" type="number" min="1" step="1" name="sort_order[<?php echo (int) $exo->id; ?>]" value="<?php echo (int) $exo->id; ?>">
                                    </div>

                                    <div>
                                        <label>Points</label><br>
                                        <input class="ouinpo-builder-small ouinpo-builder-points" type="number" min="0" step="0.25" name="points[<?php echo (int) $exo->id; ?>]" value="<?php echo esc_attr((string) $points); ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <p class="ouinpo-builder-submit-row">
                            <button type="submit" class="button button-primary button-hero">Créer le DS avec la sélection</button>
                        </p>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private static function load_dependencies(): void {
        if (!class_exists('\\Ouinpo\\Exercises\\AssessmentsService')) {
            $file = dirname(__DIR__, 2) . '/inc/AssessmentsService.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    private static function enqueue_script(): void {
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash((string) $_GET['page']))
            : '';

        if ($page !== self::PAGE_SLUG) {
            return;
        }

        $relative_path = 'assets/js/admin/assessment-builder.js';
        $fallback_dir = dirname(__DIR__, 6);

        $base_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL
            : plugin_dir_url($fallback_dir . '/ouinpo-suite.php');

        $base_dir = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR
            : $fallback_dir;

        $file = trailingslashit($base_dir) . $relative_path;
        $version = defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0';

        if (file_exists($file)) {
            $version = (string) filemtime($file);
        }

        wp_enqueue_script(
            'ouinpo-assessment-builder-js',
            trailingslashit($base_url) . $relative_path,
            [],
            $version,
            true
        );
    }

    private static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $name;
    }

    private static function get_slug_array_param(string $key): array {
        $raw = $_GET[$key] ?? [];
    
        if (!is_array($raw)) {
            $raw = [$raw];
        }
    
        $raw = wp_unslash($raw);
    
        $values = [];
    
        foreach ($raw as $value) {
            $slug = sanitize_title((string) $value);
    
            if ($slug !== '') {
                $values[] = $slug;
            }
        }
    
        return array_values(array_unique($values));
    }
    
    private static function get_int_array_param(string $key): array {
        $raw = $_GET[$key] ?? [];
    
        if (!is_array($raw)) {
            $raw = [$raw];
        }
    
        $raw = wp_unslash($raw);
    
        $values = [];
    
        foreach ($raw as $value) {
            $id = (int) $value;
    
            if ($id > 0) {
                $values[] = $id;
            }
        }
    
        return array_values(array_unique($values));
    }

    private static function current_filters(): array {
        $group_id = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
        $level_id = isset($_GET['level_id']) ? (int) $_GET['level_id'] : 0;
    
        if ($level_id <= 0 && $group_id > 0) {
            $group = self::get_group($group_id);
            if ($group && !empty($group->school_level_id)) {
                $level_id = (int) $group->school_level_id;
            }
        }
    
        $domain_slugs = self::get_slug_array_param('domain_slugs');
        $competency_ids = self::get_int_array_param('competency_ids');
    
        // Compatibilité avec l’ancienne V1 si une URL contient encore domain_slug / competency_id.
        if (empty($domain_slugs) && isset($_GET['domain_slug'])) {
            $old_domain = sanitize_title((string) wp_unslash($_GET['domain_slug']));
            if ($old_domain !== '') {
                $domain_slugs[] = $old_domain;
            }
        }
    
        if (empty($competency_ids) && isset($_GET['competency_id'])) {
            $old_competency = (int) $_GET['competency_id'];
            if ($old_competency > 0) {
                $competency_ids[] = $old_competency;
            }
        }
    
        return [
            'group_id'         => $group_id,
            'level_id'         => $level_id,
            'difficulty_id'    => isset($_GET['difficulty_id']) ? (int) $_GET['difficulty_id'] : 0,
    
            // V2 : filtres multiples.
            'domain_slugs'     => $domain_slugs,
            'competency_ids'   => $competency_ids,
    
            // Compatibilité interne V1.
            'domain_slug'      => $domain_slugs[0] ?? '',
            'competency_id'    => $competency_ids[0] ?? 0,
    
            'source_type'      => isset($_GET['source_type']) ? sanitize_key((string) $_GET['source_type']) : '',
            'exam_only'        => !empty($_GET['exam_only']) ? 1 : 0,
            'q'                => isset($_GET['q']) ? sanitize_text_field((string) wp_unslash($_GET['q'])) : '',
            'target_minutes'   => isset($_GET['target_minutes']) ? max(10, (int) $_GET['target_minutes']) : 90,
            'auto_suggest'     => !empty($_GET['auto_suggest']) ? 1 : 0,
            'avoid_class_done' => !empty($_GET['avoid_class_done']) ? 1 : 0,
        ];
    }
    private static function get_groups(): array {
        global $wpdb;

        $tbl_groups = self::table('groups');
        $tbl_years = self::table('academic_years');
        $tbl_levels = self::table('school_levels');

        return $wpdb->get_results(
            "SELECT g.id, g.label, g.year_id, g.school_level_id, y.slug AS year_slug, l.label AS level_label
             FROM {$tbl_groups} g
             LEFT JOIN {$tbl_years} y ON y.id = g.year_id
             LEFT JOIN {$tbl_levels} l ON l.id = g.school_level_id
             ORDER BY y.starts_on DESC, g.label ASC"
        ) ?: [];
    }

    private static function get_group(int $group_id): ?object {
        global $wpdb;

        if ($group_id <= 0) {
            return null;
        }

        $tbl_groups = self::table('groups');
        $tbl_years = self::table('academic_years');
        $tbl_levels = self::table('school_levels');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT g.id, g.label, g.year_id, g.school_level_id, y.slug AS year_slug, l.label AS level_label
             FROM {$tbl_groups} g
             LEFT JOIN {$tbl_years} y ON y.id = g.year_id
             LEFT JOIN {$tbl_levels} l ON l.id = g.school_level_id
             WHERE g.id = %d
             LIMIT 1",
            $group_id
        ));

        return $row ?: null;
    }

    private static function get_levels(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, label, slug FROM " . self::table('school_levels') . " ORDER BY id ASC"
        ) ?: [];
    }

    private static function get_difficulties(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, slug, label FROM " . self::table('difficulties') . " ORDER BY id ASC"
        ) ?: [];
    }

    private static function get_domains(array $filters): array {
        global $wpdb;

        $tbl = self::table('competencies');
        $where = "WHERE active = 1 AND domain_slug IS NOT NULL AND domain_slug <> ''";
        $args = [];

        $level_label = self::level_label((int) $filters['level_id']);

        if ($level_label !== '') {
            $where .= " AND (level = %s OR level = 'Transversal')";
            $args[] = $level_label;
        }

        $sql = "SELECT DISTINCT domain, domain_slug FROM {$tbl} {$where} ORDER BY domain ASC";

        return $args
            ? ($wpdb->get_results($wpdb->prepare($sql, $args)) ?: [])
            : ($wpdb->get_results($sql) ?: []);
    }

    private static function get_competencies(array $filters): array {
        global $wpdb;
    
        $tbl = self::table('competencies');
        $where = "WHERE active = 1";
        $args = [];
    
        $level_label = self::level_label((int) $filters['level_id']);
    
        if ($level_label !== '') {
            $where .= " AND (level = %s OR level = 'Transversal')";
            $args[] = $level_label;
        }
    
        $domain_slugs = array_values(array_filter((array) ($filters['domain_slugs'] ?? [])));
    
        if (!empty($domain_slugs)) {
            $placeholders = implode(',', array_fill(0, count($domain_slugs), '%s'));
            $where .= " AND domain_slug IN ({$placeholders})";
    
            foreach ($domain_slugs as $slug) {
                $args[] = $slug;
            }
        }
    
        $sql = "SELECT id, domain, competency, level, track
                FROM {$tbl}
                {$where}
                ORDER BY domain ASC, id ASC";
    
        return $args
            ? ($wpdb->get_results($wpdb->prepare($sql, $args)) ?: [])
            : ($wpdb->get_results($sql) ?: []);
    }
    private static function level_label(int $level_id): string {
        global $wpdb;

        if ($level_id <= 0) {
            return '';
        }

        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT label FROM " . self::table('school_levels') . " WHERE id = %d LIMIT 1",
            $level_id
        ));
    }

    private static function get_exercises(array $filters): array {
        global $wpdb;

        $tbl_e = self::table('exercises');
        $tbl_d = self::table('difficulties');
        $tbl_em = self::table('exam_meta');
        $tbl_ec = self::table('exercise_competency');
        $tbl_c = self::table('competencies');
        $tbl_esl = self::table('exercise_school_level');

        $where = ["e.is_active = 1"];
        $args = [];

        // Exclusion volontaire des sujets pratiques complets.
        $where[] = "(em.exam_type IS NULL OR em.exam_type <> 'practical_subject')";

        if (!empty($filters['level_id'])) {
            $where[] = "(e.level_id = %d OR EXISTS (
                SELECT 1
                FROM {$tbl_esl} esl
                WHERE esl.exercise_id = e.id
                  AND esl.school_level_id = %d
            ))";
            $args[] = (int) $filters['level_id'];
            $args[] = (int) $filters['level_id'];
        }

        if (!empty($filters['difficulty_id'])) {
            $where[] = "e.difficulty_id = %d";
            $args[] = (int) $filters['difficulty_id'];
        }

        $domain_slugs = array_values(array_filter((array) ($filters['domain_slugs'] ?? [])));
        $competency_ids = array_values(array_filter(array_map('intval', (array) ($filters['competency_ids'] ?? []))));
        
        $bo_conditions = [];
        $bo_args = [];
        
        if (!empty($domain_slugs)) {
            $domain_placeholders = implode(',', array_fill(0, count($domain_slugs), '%s'));
        
            $bo_conditions[] = "cf.domain_slug IN ({$domain_placeholders})";
        
            foreach ($domain_slugs as $slug) {
                $bo_args[] = $slug;
            }
        }
        
        if (!empty($competency_ids)) {
            $competency_placeholders = implode(',', array_fill(0, count($competency_ids), '%d'));
        
            $bo_conditions[] = "ecf.competency_id IN ({$competency_placeholders})";
        
            foreach ($competency_ids as $id) {
                $bo_args[] = (int) $id;
            }
        }
        
        if (!empty($bo_conditions)) {
            $where[] = "EXISTS (
                SELECT 1
                FROM {$tbl_ec} ecf
                JOIN {$tbl_c} cf ON cf.id = ecf.competency_id
                WHERE ecf.exercise_id = e.id
                  AND (" . implode(' OR ', $bo_conditions) . ")
            )";
        
            foreach ($bo_args as $arg) {
                $args[] = $arg;
            }
        }

        if (!empty($filters['source_type']) && in_array($filters['source_type'], ['annale', 'inspired', 'type_bac'], true)) {
            $where[] = "em.source_type = %s";
            $args[] = (string) $filters['source_type'];
        }

        if (!empty($filters['exam_only'])) {
            $where[] = "em.is_exam_like = 1";
        }

        if (!empty($filters['q'])) {
            $like = '%' . $wpdb->esc_like((string) $filters['q']) . '%';
            $where[] = "(e.title LIKE %s OR e.slug LIKE %s OR e.statement LIKE %s)";
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT
                e.id,
                e.title,
                e.slug,
                d.label AS difficulty_label,
                d.slug AS difficulty_slug,
                em.estimated_minutes,
                em.source_type,
                em.center_label,
                em.session_label,
                em.year_label,
                GROUP_CONCAT(DISTINCT CONCAT(c.domain, ' — ', LEFT(c.competency, 90)) SEPARATOR '||') AS competency_labels,
                GROUP_CONCAT(DISTINCT c.id ORDER BY c.id SEPARATOR ',') AS competency_ids,
                GROUP_CONCAT(DISTINCT c.domain_slug ORDER BY c.domain_slug SEPARATOR ',') AS domain_slugs
            FROM {$tbl_e} e
            LEFT JOIN {$tbl_d} d ON d.id = e.difficulty_id
            LEFT JOIN {$tbl_em} em ON em.exercise_id = e.id
            LEFT JOIN {$tbl_ec} ec ON ec.exercise_id = e.id
            LEFT JOIN {$tbl_c} c ON c.id = ec.competency_id
            WHERE {$where_sql}
            GROUP BY e.id, e.title, e.slug, d.label, d.slug, em.estimated_minutes, em.source_type, em.center_label, em.session_label, em.year_label
            ORDER BY
                CASE d.slug
                    WHEN 'debutant' THEN 1
                    WHEN 'confirme' THEN 2
                    WHEN 'expert' THEN 3
                    ELSE 4
                END,
                COALESCE(em.estimated_minutes, 999),
                e.title ASC
            LIMIT 300
        ";

        return $args
            ? ($wpdb->get_results($wpdb->prepare($sql, $args)) ?: [])
            : ($wpdb->get_results($sql) ?: []);
    }

    private static function parse_competency_labels(string $raw): array {
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('||', $raw))));
    }

    private static function parse_competency_ids(?string $raw): array {
        if (!$raw) {
            return [];
        }
    
        return array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
    }

    private static function parse_slug_list(?string $raw): array {
        if (!$raw) {
            return [];
        }
    
        $values = [];
    
        foreach (explode(',', $raw) as $value) {
            $slug = sanitize_title(trim((string) $value));
    
            if ($slug !== '') {
                $values[] = $slug;
            }
        }
    
        return array_values(array_unique($values));
    }

    private static function count_group_students(int $group_id): int {
        global $wpdb;
    
        if ($group_id <= 0) {
            return 0;
        }
    
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM " . self::table('group_members') . "
             WHERE group_id = %d
               AND role = 'student'",
            $group_id
        ));
    }

    private static function class_exercise_done_map(int $group_id): array {
        global $wpdb;
    
        if ($group_id <= 0) {
            return [];
        }
    
        $tbl_gm = self::table('group_members');
        $tbl_us = self::table('user_status');
    
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT
                us.exercise_id,
                COUNT(DISTINCT CASE WHEN us.status IN ('attempted','solved') THEN us.user_id END) AS attempted_count,
                COUNT(DISTINCT CASE WHEN us.status = 'solved' THEN us.user_id END) AS solved_count
             FROM {$tbl_gm} gm
             JOIN {$tbl_us} us ON us.user_id = gm.user_id
             WHERE gm.group_id = %d
               AND gm.role = 'student'
             GROUP BY us.exercise_id",
            $group_id
        )) ?: [];
    
        $map = [];
    
        foreach ($rows as $row) {
            $map[(int) $row->exercise_id] = [
                'attempted' => (int) $row->attempted_count,
                'solved'    => (int) $row->solved_count,
            ];
        }
    
        return $map;
    }

    private static function suggested_exercise_ids(array $exercises, array $filters): array {
        if (empty($filters['auto_suggest']) || empty($exercises)) {
            return [];
        }
    
        $target = max(10, (int) ($filters['target_minutes'] ?? 90));
    
        $wanted_competencies = array_values(array_filter(array_map('intval', (array) ($filters['competency_ids'] ?? []))));
        $wanted_domains = array_values(array_filter((array) ($filters['domain_slugs'] ?? [])));
    
        $selected = [];
        $selected_ids = [];
        $covered_competencies = [];
        $covered_domains = [];
        $minutes = 0;
    
        $estimate = static function(object $exo): int {
            return !empty($exo->estimated_minutes)
                ? (int) $exo->estimated_minutes
                : self::estimated_minutes_from_difficulty((string) $exo->difficulty_slug);
        };
    
        $pick = function(object $exo, bool $respect_target = true) use (
            &$selected,
            &$selected_ids,
            &$covered_competencies,
            &$covered_domains,
            &$minutes,
            $target,
            $estimate
        ): bool {
            $id = (int) $exo->id;
    
            if (isset($selected_ids[$id])) {
                return false;
            }
    
            $estimated = $estimate($exo);
    
            if ($respect_target && !empty($selected) && $minutes + $estimated > $target + 10) {
                return false;
            }
    
            $selected[] = $exo;
            $selected_ids[$id] = true;
            $minutes += $estimated;
    
            foreach (self::parse_competency_ids((string) ($exo->competency_ids ?? '')) as $competency_id) {
                $covered_competencies[$competency_id] = true;
            }
    
            foreach (self::parse_slug_list((string) ($exo->domain_slugs ?? '')) as $domain_slug) {
                $covered_domains[$domain_slug] = true;
            }
    
            return true;
        };
    
        $ranked = $exercises;
    
        usort($ranked, function(object $a, object $b) use ($filters, $target): int {
            return self::suggestion_score($a, $filters, $target) <=> self::suggestion_score($b, $filters, $target);
        });
    
        // Priorité 1 : si des compétences précises sont demandées,
        // on sélectionne au moins un exercice pour chaque compétence,
        // même si cela dépasse la durée cible.
        foreach ($wanted_competencies as $competency_id) {
            if (isset($covered_competencies[$competency_id])) {
                continue;
            }
        
            $candidate = null;
        
            foreach ($ranked as $exo) {
                if (isset($selected_ids[(int) $exo->id])) {
                    continue;
                }
        
                $exo_competencies = self::parse_competency_ids((string) ($exo->competency_ids ?? ''));
        
                if (in_array($competency_id, $exo_competencies, true)) {
                    $candidate = $exo;
                    break;
                }
            }
        
            if ($candidate) {
                // On essaie d'abord de respecter la durée.
                // Si ce n'est pas possible, on force quand même,
                // car la couverture des compétences est prioritaire.
                if (!$pick($candidate, true)) {
                    $pick($candidate, false);
                }
            }
        }
    
        // Priorité 2 : si aucun filtre de compétence précis n’est posé,
        // on essaie de représenter les domaines choisis.
        if (empty($wanted_competencies) && !empty($wanted_domains)) {
            foreach ($wanted_domains as $domain_slug) {
                if (isset($covered_domains[$domain_slug])) {
                    continue;
                }
    
                foreach ($ranked as $exo) {
                    $exo_domains = self::parse_slug_list((string) ($exo->domain_slugs ?? ''));
    
                    if (in_array($domain_slug, $exo_domains, true)) {
                        if ($pick($exo, true)) {
                            break;
                        }
                    }
                }
    
                if ($minutes >= $target - 5) {
                    break;
                }
            }
        }
    
        // Priorité 3 : compléter jusqu’à approcher la durée cible.
        foreach ($ranked as $exo) {
            if ($minutes >= $target - 5) {
                break;
            }
    
            $pick($exo, true);
        }
    
        if (empty($selected) && !empty($ranked)) {
            $pick($ranked[0], false);
        }
    
        return array_map(static fn(object $exo) => (int) $exo->id, $selected);
    }

    private static function missing_suggested_competencies(array $exercises, array $filters, array $suggested_ids): array {
        $wanted_competencies = array_values(array_filter(array_map('intval', (array) ($filters['competency_ids'] ?? []))));
    
        if (empty($wanted_competencies)) {
            return [];
        }
    
        $covered = [];
    
        foreach ($exercises as $exo) {
            if (!in_array((int) $exo->id, $suggested_ids, true)) {
                continue;
            }
    
            foreach (self::parse_competency_ids((string) ($exo->competency_ids ?? '')) as $competency_id) {
                $covered[$competency_id] = true;
            }
        }
    
        $missing_ids = [];
    
        foreach ($wanted_competencies as $competency_id) {
            if (empty($covered[$competency_id])) {
                $missing_ids[] = $competency_id;
            }
        }
    
        if (empty($missing_ids)) {
            return [];
        }
    
        return self::get_competency_labels_by_ids($missing_ids);
    }

    private static function get_competency_labels_by_ids(array $ids): array {
        global $wpdb;
    
        $ids = array_values(array_filter(array_map('intval', $ids)));
    
        if (empty($ids)) {
            return [];
        }
    
        $tbl = self::table('competencies');
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    
        $sql = "
            SELECT id, domain, competency
            FROM {$tbl}
            WHERE id IN ({$placeholders})
            ORDER BY domain ASC, id ASC
        ";
    
        $rows = $wpdb->get_results($wpdb->prepare($sql, $ids)) ?: [];
    
        $labels = [];
    
        foreach ($rows as $row) {
            $labels[] = $row->domain . ' — ' . wp_trim_words((string) $row->competency, 16);
        }
    
        return $labels;
    }
   
    private static function suggestion_score(object $exo, array $filters, int $target): float {
        $estimated = !empty($exo->estimated_minutes)
            ? (int) $exo->estimated_minutes
            : self::estimated_minutes_from_difficulty((string) $exo->difficulty_slug);
    
        $score = 0.0;
    
        // On privilégie les exercices de durée moyenne.
        $score += abs($estimated - min(20, max(10, (int) round($target / 4)))) * 0.8;
    
        $difficulty = (string) ($exo->difficulty_slug ?? '');
    
        if ($difficulty === 'confirme') {
            $score += 0;
        } elseif ($difficulty === 'debutant') {
            $score += 4;
        } elseif ($difficulty === 'expert') {
            $score += 8;
        } else {
            $score += 10;
        }
    
        // Bonus si l’exercice couvre explicitement une compétence demandée.
        $wanted_competencies = array_values(array_filter(array_map('intval', (array) ($filters['competency_ids'] ?? []))));
        $exo_competencies = self::parse_competency_ids((string) ($exo->competency_ids ?? ''));
    
        if (!empty($wanted_competencies) && !empty(array_intersect($wanted_competencies, $exo_competencies))) {
            $score -= 12;
        }
    
        // Bonus si l’exercice couvre explicitement un domaine demandé.
        $wanted_domains = array_values(array_filter((array) ($filters['domain_slugs'] ?? [])));
        $exo_domains = self::parse_slug_list((string) ($exo->domain_slugs ?? ''));
    
        if (!empty($wanted_domains) && !empty(array_intersect($wanted_domains, $exo_domains))) {
            $score -= 6;
        }
    
        // Si une classe est choisie, on défavorise les exercices déjà très vus.
        if (!empty($filters['avoid_class_done']) && !empty($exo->class_student_count)) {
            $ratio_attempted = ((int) $exo->class_attempted_count) / max(1, (int) $exo->class_student_count);
            $ratio_solved = ((int) $exo->class_solved_count) / max(1, (int) $exo->class_student_count);
    
            $score += $ratio_attempted * 80;
            $score += $ratio_solved * 40;
        }
    
        // Petite préférence pour les exercices proches du bac.
        if (($exo->source_type ?? '') === 'annale') {
            $score -= 2;
        } elseif (($exo->source_type ?? '') === 'inspired') {
            $score -= 1;
        }
    
        return $score;
    }
    private static function default_title(?object $group): string {
        $title = 'Devoir surveillé';

        if ($group && !empty($group->level_label)) {
            $title .= ' — ' . $group->level_label;
        }

        return $title;
    }

    private static function estimated_minutes_from_difficulty(string $difficulty_slug): int {
        if ($difficulty_slug === 'debutant') {
            return 10;
        }

        if ($difficulty_slug === 'expert') {
            return 25;
        }

        return 15;
    }

    private static function suggested_points(string $difficulty_slug, int $estimated_minutes): float {
        if ($estimated_minutes >= 35) {
            return 8.0;
        }

        if ($estimated_minutes >= 25) {
            return 6.0;
        }

        if ($estimated_minutes >= 15) {
            return 4.0;
        }

        if ($difficulty_slug === 'expert') {
            return 6.0;
        }

        if ($difficulty_slug === 'confirme') {
            return 4.0;
        }

        return 2.0;
    }

    private static function source_label(string $source): string {
        if ($source === 'annale') {
            return 'Annale';
        }

        if ($source === 'inspired') {
            return 'Inspiré d’annales';
        }

        if ($source === 'type_bac') {
            return 'Type bac';
        }

        return $source;
    }

    private static function handle_post(): void {
        if (empty($_POST[self::NONCE_NAME]) || !wp_verify_nonce((string) $_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            add_settings_error('ouinpo_assessment_builder', 'nonce', 'Nonce invalide.', 'error');
            return;
        }

        $op = isset($_POST['op']) ? sanitize_key((string) $_POST['op']) : '';

        if ($op === 'create_assessment_from_builder') {
            self::handle_create_assessment();
        }
    }

    private static function handle_create_assessment(): void {
        global $wpdb;

        if (!class_exists('\\Ouinpo\\Exercises\\AssessmentsService')) {
            add_settings_error('ouinpo_assessment_builder', 'missing_service', 'Service des DS introuvable.', 'error');
            return;
        }

        $group_id = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
        
        if ($group_id <= 0) {
            add_settings_error(
                'ouinpo_assessment_builder',
                'missing_group',
                'Choisis une classe avant de créer le DS.',
                'error'
            );
            return;
        }

        $exercise_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['exercise_ids'] ?? [])))));
        
        if (!empty($exercise_ids)) {
            $tbl_e  = self::table('exercises');
            $tbl_em = self::table('exam_meta');
            $in = implode(',', array_map('intval', $exercise_ids));
        
            $exercise_ids = array_map('intval', $wpdb->get_col(
                "SELECT e.id
                 FROM {$tbl_e} e
                 LEFT JOIN {$tbl_em} em ON em.exercise_id = e.id
                 WHERE e.id IN ({$in})
                   AND e.is_active = 1
                   AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')"
            ) ?: []);
        }
        
        if (empty($exercise_ids)) {
            add_settings_error('ouinpo_assessment_builder', 'no_exercises', 'Sélectionne au moins un exercice classique. Les sujets pratiques complets sont exclus des DS.', 'error');
            return;
        }
        
        $in = implode(',', array_map('intval', $exercise_ids));
        $tbl_ec = self::table('exercise_competency');

        $competency_ids = array_map('intval', $wpdb->get_col(
            "SELECT DISTINCT competency_id
             FROM {$tbl_ec}
             WHERE exercise_id IN ({$in})"
        ) ?: []);

        if (empty($competency_ids)) {
            add_settings_error('ouinpo_assessment_builder', 'no_competencies', 'Les exercices sélectionnés ne portent aucune compétence BO. Le DS n’a pas été créé.', 'error');
            return;
        }

        $target_minutes = isset($_POST['target_minutes']) ? max(0, (int) $_POST['target_minutes']) : 0;
        $notes = trim(wp_kses_post((string) ($_POST['notes'] ?? '')));

        if ($target_minutes > 0) {
            $notes .= ($notes !== '' ? "\n\n" : '') . 'Durée cible : ' . $target_minutes . ' minutes.';
        }

        $assessment_id = AssessmentsService::save_competency_assessment([
            'title'          => $_POST['title'] ?? '',
            'group_id'       => $group_id,
            'due_on'         => $_POST['due_on'] ?? '',
            'notes'          => $notes,
            'competency_ids' => $competency_ids,
        ], 0);

        if (is_wp_error($assessment_id)) {
            add_settings_error('ouinpo_assessment_builder', 'create_failed', $assessment_id->get_error_message(), 'error');
            return;
        }

        $tbl_ai = self::table('assessment_items');
        $orders = (array) ($_POST['sort_order'] ?? []);
        $points = (array) ($_POST['points'] ?? []);

        usort($exercise_ids, static function(int $a, int $b) use ($orders): int {
            $oa = isset($orders[$a]) ? (int) $orders[$a] : $a;
            $ob = isset($orders[$b]) ? (int) $orders[$b] : $b;

            if ($oa === $ob) {
                return $a <=> $b;
            }

            return $oa <=> $ob;
        });

        $position = 1;

        foreach ($exercise_ids as $exercise_id) {
            $raw_point = isset($points[$exercise_id])
                ? str_replace(',', '.', (string) $points[$exercise_id])
                : '';

            $point_value = is_numeric($raw_point) ? (float) $raw_point : null;

            $wpdb->insert($tbl_ai, [
                'assessment_id' => (int) $assessment_id,
                'exercise_id'   => (int) $exercise_id,
                'sort_order'    => $position,
                'points'        => $point_value,
            ], ['%d', '%d', '%d', '%f']);

            $position++;
        }

        wp_safe_redirect(admin_url('admin.php?page=ouinpo-assessments&action=grade&id=' . (int) $assessment_id . '&created=1'));
        exit;
    }

    private static function ensure_assessment_item_columns(): void {
        global $wpdb;

        $table = self::table('assessment_items');

        $columns = $wpdb->get_col($wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s",
            DB_NAME,
            $table
        )) ?: [];

        if (!in_array('sort_order', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER exercise_id");
        }

        if (!in_array('points', $columns, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN points DECIMAL(5,2) NULL AFTER sort_order");
        }
    }
}
