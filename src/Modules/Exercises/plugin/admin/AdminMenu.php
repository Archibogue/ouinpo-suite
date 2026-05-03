<?php
namespace Ouinpo\Exercises\Admin;

if (!defined('ABSPATH')) exit;

class AdminMenu {

    public static function enqueue_admin_styles(string $hook = ''): void {
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash((string) $_GET['page']))
            : '';

        $styles = [
            'ouinpo-assessment-builder' => [
                'handle' => 'ouinpo-assessment-builder-admin',
                'file'   => 'assets/css/admin/assessment-builder.css',
            ],
            'ouinpo-assessments' => [
                'handle' => 'ouinpo-assessments-admin',
                'file'   => 'assets/css/admin/assessments.css',
            ],
            'ouinpo-badges' => [
                'handle' => 'ouinpo-badges-admin',
                'file'   => 'assets/css/admin/badges.css',
            ],
        ];

        if (!isset($styles[$page])) {
            return;
        }

        self::enqueue_admin_css(
            $styles[$page]['handle'],
            $styles[$page]['file']
        );
    }

    private static function enqueue_admin_css(string $handle, string $relativePath): void
    {
        $baseUrl = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL
            : (defined('OUINPO_EXO_PLUGIN_FILE') ? plugin_dir_url(OUINPO_EXO_PLUGIN_FILE) : '');

        if ($baseUrl === '') {
            return;
        }

        $baseDir = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR
            : (defined('OUINPO_EXO_PLUGIN_FILE') ? plugin_dir_path(OUINPO_EXO_PLUGIN_FILE) : '');

        $version = defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0';
        $file = $baseDir !== '' ? $baseDir . $relativePath : '';

        if ($file !== '' && file_exists($file)) {
            $version = (string) filemtime($file);
        }

        wp_enqueue_style(
            $handle,
            $baseUrl . $relativePath,
            [],
            $version
        );
    }

    public static function register_menu() {
        $parent = defined('OUINPO_SUITE_ADMIN_SLUG')
            ? OUINPO_SUITE_ADMIN_SLUG
            : 'ouinpo-exercices';
    
        if (!defined('OUINPO_SUITE_ADMIN_SLUG')) {
            add_menu_page(
                'Exercices OuInPo',
                'Exercices NSI',
                'edit_posts',
                'ouinpo-exercices',
                [self::class, 'render_exercises'],
                'dashicons-welcome-learn-more',
                56
            );
        }
    
        add_submenu_page(
            $parent,
            'Exercices OuInPo',
            'Exercices NSI',
            'edit_posts',
            'ouinpo-exercices',
            [self::class, 'render_exercises']
        );
    
        add_submenu_page(
            $parent,
            'Badges',
            'Badges',
            'manage_options',
            'ouinpo-badges',
            [self::class, 'render_badges']
        );
    
        add_submenu_page(
            $parent,
            'Devoirs surveillés',
            'Devoirs surveillés',
            'edit_posts',
            'ouinpo-assessments',
            [self::class, 'render_assessments']
        );

        add_submenu_page(
            $parent,
            'Concepteur de devoirs',
            'Concepteur de devoirs',
            'edit_posts',
            'ouinpo-assessment-builder',
            [self::class, 'render_assessment_builder']
        );
    
        add_submenu_page(
            $parent,
            'Importer des exercices',
            'Import exercices',
            'edit_posts',
            'ouinpo-import-exercises',
            [self::class, 'render_import_exercises']
        );
    
        add_submenu_page(
            $parent,
            'Suivi des compétences',
            'Suivi compétences',
            'edit_users',
            'ouinpo-competencies',
            [self::class, 'render_competencies']
        );
    
        add_submenu_page(
            $parent,
            'Classes',
            'Classes',
            'edit_users',
            'ouinpo-groups',
            [self::class, 'renderGroups']
        );
    
        add_submenu_page(
            $parent,
            'Affectations',
            'Affectations',
            'edit_users',
            'ouinpo-assignments',
            [self::class, 'renderAssignments']
        );
    
        add_submenu_page(
            $parent,
            'Cours ↔ compétences BO',
            'Cours ↔ BO',
            'edit_users',
            'ouinpo-courses-competencies',
            [self::class, 'render_courses_competencies']
        );
        
        add_submenu_page(
            $parent,
            'Années scolaires',
            'Années scolaires',
            'edit_users',
            'ouinpo-years',
            [self::class, 'render_years']
        );
        
        add_submenu_page(
            $parent,
            'Attributions de badges',
            'Attributions de badges',
            'manage_options',
            'ouinpo-badge-assignments',
            [self::class, 'renderBadgeAssignments']
        );
        
        add_submenu_page(
            $parent,
            'Parcours',
            'Parcours',
            'edit_users',
            'ouinpo-paths',
            [self::class, 'render_paths']
        );        
        add_submenu_page(
            $parent,
            'Options exercices',
            'Options exercices',
            'manage_options',
            'ouinpo-exercises-settings',
            [self::class, 'render_settings']
        );

        add_submenu_page(
            $parent,
            'Sujets pratiques',
            'Sujets pratiques',
            'edit_users',
            'ouinpo-practical-subjects',
            ['\\Ouinpo\\Exercises\\Admin\\ScreenPractical', 'render']
        );
        
        }

    /* -------------------- RENDERERS -------------------- */

    public static function render_exercises() {
        self::render_screen(__DIR__ . '/screens/screen-exercises.php', [
            '\\Ouinpo\\Exercises\\Admin\\Screen_Exercises'
        ]);
    }

    public static function render_competencies() {
        require_once __DIR__ . '/screens/screen-competencies.php';
        if (class_exists('\Ouinpo\Exercises\Admin\Screen_Competencies')) {
            \Ouinpo\Exercises\Admin\Screen_Competencies::render();
        } else {
            echo '<div class="notice notice-error"><p>Erreur : classe <code>\Ouinpo\Exercises\Admin\Screen_Competencies</code> introuvable.</p></div>';
        }
    }

    public static function render_badges() {
        self::render_screen(__DIR__ . '/screens/screen-badges.php', [
            '\\Ouinpo\\Exercises\\Admin\\Screen_Badges'
        ]);
    }

    public static function render_assessments() {
        self::render_screen(__DIR__ . '/screens/screen-assessments.php', [
            '\\Ouinpo\\Exercises\\Admin\\Screen_Assessments'
        ]);
    }

    public static function render_assessment_builder() {
        self::render_screen(__DIR__ . '/screens/screen-assessment-builder.php', [
            '\\Ouinpo\\Exercises\\Admin\\Screen_Assessment_Builder',
            '\\Ouinpo\\Exercises\\Admin\\Screen_Assessment_Builder'
        ]);
    }
    
    public static function render_paths() {
        self::render_screen(__DIR__ . '/screens/screen-paths.php', [
            '\\Ouinpo\\Exercises\\Admin\\Screen_Paths'
        ]);
    }    

    // --- NOUVEAU renderer : import d'exercices ---
    public static function render_import_exercises() {
        self::render_screen(__DIR__ . '/screens/screen-import-exercises.php', [
            '\\Ouinpo\\Exercises\\Admin\\Screen_Import_Exercises'
        ]);
    }

    public static function handle_export_exercises_csv() {
        if (!current_user_can('edit_posts')) {
            wp_die('Accès refusé.');
        }
    
        require_once __DIR__ . '/screens/screen-import-exercises.php';
    
        if (
            class_exists('\\Ouinpo\\Exercises\\Admin\\Screen_Import_Exercises')
            && method_exists('\\Ouinpo\\Exercises\\Admin\\Screen_Import_Exercises', 'export_csv')
        ) {
            \Ouinpo\Exercises\Admin\Screen_Import_Exercises::export_csv();
        }
    
        wp_die('Export impossible : écran d’import introuvable.');
    }

    public static function renderBadgeAssignments() {
        require_once __DIR__ . '/screens/screen-badge-assignments.php';
    }

    public static function render_years() {
        require_once __DIR__ . '/screens/screen-years.php';
    }

    public static function renderGroups() {
        require_once __DIR__ . '/screens/screen-groups.php';
    }

    public static function renderAssignments() {
        require_once __DIR__ . '/screens/screen-group-members.php';
    }

    // --- NOUVEAU renderer : Cours ↔ Compétences BO -------------------
    public static function render_courses_competencies() {
        $file = __DIR__ . '/screens/screen-course-competencies.php';

        echo '<div class="wrap">';
        if (!file_exists($file)) {
            echo '<div class="notice notice-error"><p><strong>Erreur :</strong> fichier manquant '
                . esc_html($file) . '</p></div></div>';
            return;
        }

        require_once $file;

        if (function_exists('\ouinpo_render_courses_competencies_page')) {
            \ouinpo_render_courses_competencies_page();
        } else {
            echo '<div class="notice notice-error"><p><strong>Erreur :</strong> fonction '
                . '<code>ouinpo_render_courses_competencies_page()</code> introuvable dans le fichier écran.</p>'
                . '<p>Vérifie que la fonction est bien définie en global, sans namespace.</p></div>';
        }

        echo '</div>';
    }
    
    public static function render_settings() {
        self::render_screen(__DIR__ . '/screens/screen-settings.php', [
            '\\Ouinpo\\Exercises\\Admin\\Screen_Settings'
        ]);
    }    
    
    // ---------------------------------------------------

    /* -------------------- HELPERS -------------------- */

    private static function render_screen(string $file, array $classCandidates) {
        echo '<div class="wrap">';
        if (!file_exists($file)) {
            echo '<div class="notice notice-error"><p><strong>Erreur :</strong> fichier manquant '
                . esc_html($file) . '</p></div></div>';
            return;
        }

        require_once $file;

        foreach ($classCandidates as $fqcn) {
            if (class_exists($fqcn) && method_exists($fqcn, 'render')) {
                call_user_func([$fqcn, 'render']);
                echo '</div>';
                return;
            }
        }

        echo '<div class="notice notice-error"><p><strong>Erreur :</strong> classe '
            . '<code>' . esc_html(implode('</code> ou <code>', $classCandidates)) . '</code> introuvable ou méthode <code>render()</code> absente.</p>'
            . '<p>Vérifie le namespace en haut du fichier écran.</p></div></div>';
    }
}

