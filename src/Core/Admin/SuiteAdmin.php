<?php

namespace Ouinpo\Suite\Core\Admin;

use Ouinpo\Suite\Core\Bootstrap;
use Ouinpo\Suite\Core\ModuleSettings;
use Ouinpo\Suite\Core\PedagogicalPackImporter;

if (!defined('ABSPATH')) {
    exit;
}

final class SuiteAdmin
{
    public const ROOT_SLUG = 'ouinpo-suite';
    private const OPTION_BO_DOMAINS = 'ouinpo_suite_bo_domains';

    public static function init(): void
    {
        if (!defined('OUINPO_SUITE_ADMIN_SLUG')) {
            define('OUINPO_SUITE_ADMIN_SLUG', self::ROOT_SLUG);
        }

        add_action('admin_menu', [self::class, 'registerRootMenu'], 5);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminStyles']);
        add_action('admin_head', [self::class, 'adminStyles']);
    }

    public static function enqueueAdminStyles(string $hook = ''): void
    {
        self::enqueueCss(
            'ouinpo-suite-admin',
            'assets/css/admin/suite-admin.css'
        );

        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash((string) $_GET['page']))
            : '';

        if ($page !== self::ROOT_SLUG && strpos($page, self::ROOT_SLUG . '-') !== 0) {
            return;
        }

        self::enqueueJs(
            'ouinpo-suite-admin-js',
            'assets/js/admin/suite-admin.js'
        );
    }

    private static function enqueueCss(string $handle, string $relativePath): void
    {
        $baseUrl = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL
            : (defined('OUINPO_SUITE_FILE') ? plugin_dir_url(OUINPO_SUITE_FILE) : '');

        if ($baseUrl === '') {
            return;
        }

        $baseDir = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR
            : (defined('OUINPO_SUITE_FILE') ? plugin_dir_path(OUINPO_SUITE_FILE) : '');

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

    private static function enqueueJs(string $handle, string $relativePath): void
    {
        $baseUrl = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL
            : (defined('OUINPO_SUITE_FILE') ? plugin_dir_url(OUINPO_SUITE_FILE) : '');

        if ($baseUrl === '') {
            return;
        }

        $baseDir = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR
            : (defined('OUINPO_SUITE_FILE') ? plugin_dir_path(OUINPO_SUITE_FILE) : '');

        $version = defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0';
        $file = $baseDir !== '' ? $baseDir . $relativePath : '';

        if ($file !== '' && file_exists($file)) {
            $version = (string) filemtime($file);
        }

        wp_enqueue_script(
            $handle,
            $baseUrl . $relativePath,
            [],
            $version,
            true
        );
    }

    public static function registerRootMenu(): void
    {
        add_menu_page(
            'OuInPo Suite',
            'OuInPo Suite',
            'edit_posts',
            self::ROOT_SLUG,
            [self::class, 'renderDashboard'],
            'dashicons-screenoptions',
            55
        );

        add_submenu_page(
            self::ROOT_SLUG,
            'Tableau de bord',
            'Tableau de bord',
            'edit_posts',
            self::ROOT_SLUG,
            [self::class, 'renderDashboard']
        );

        add_submenu_page(
            self::ROOT_SLUG,
            'Contenus',
            'Contenus',
            'edit_posts',
            'ouinpo-suite-contents',
            [self::class, 'renderContentsHub']
        );

        if (ModuleSettings::isEnabled('flashcards')) {
            add_submenu_page(
                self::ROOT_SLUG,
                'Révisions',
                'Révisions',
                'edit_posts',
                'ouinpo-suite-revisions',
                [self::class, 'renderRevisionsHub']
            );
        }

        add_submenu_page(
            self::ROOT_SLUG,
            'Évaluations',
            'Évaluations',
            'edit_posts',
            'ouinpo-suite-evaluations',
            [self::class, 'renderEvaluationsHub']
        );

        add_submenu_page(
            self::ROOT_SLUG,
            'Classes & élèves',
            'Classes & élèves',
            'edit_posts',
            'ouinpo-suite-classes',
            [self::class, 'renderClassesHub']
        );

        add_submenu_page(
            self::ROOT_SLUG,
            'Référentiel BO',
            'Référentiel BO',
            'edit_posts',
            'ouinpo-suite-referentiel',
            [self::class, 'renderReferentielHub']
        );

        add_submenu_page(
            self::ROOT_SLUG,
            'Badges',
            'Badges',
            'manage_options',
            'ouinpo-suite-badges',
            [self::class, 'renderBadgesHub']
        );

        if (self::hasAiOrPathModule()) {
            add_submenu_page(
                self::ROOT_SLUG,
                'IA & parcours',
                'IA & parcours',
                'edit_posts',
                'ouinpo-suite-ai',
                [self::class, 'renderAiHub']
            );
        }

        add_submenu_page(
            self::ROOT_SLUG,
            'Réglages',
            'Réglages',
            'edit_posts',
            'ouinpo-suite-settings',
            [self::class, 'renderSettingsHub']
        );
    }

    public static function adminStyles(): void
    {
    }

    private static function hasAiOrPathModule(): bool
    {
        return ModuleSettings::isEnabled('segfault')
            || ModuleSettings::isEnabled('gate')
            || ModuleSettings::isEnabled('rechtext');
    }

    private static function mainTabs(): array
    {
        $tabs = [
            self::ROOT_SLUG         => 'Tableau de bord',
            'ouinpo-suite-contents' => 'Contenus',
        ];

        if (ModuleSettings::isEnabled('flashcards')) {
            $tabs['ouinpo-suite-revisions'] = 'Révisions';
        }

        $tabs['ouinpo-suite-evaluations'] = 'Évaluations';
        $tabs['ouinpo-suite-classes'] = 'Classes & élèves';
        $tabs['ouinpo-suite-referentiel'] = 'Référentiel BO';

        if (current_user_can('manage_options')) {
            $tabs['ouinpo-suite-badges'] = 'Badges';
        }

        if (self::hasAiOrPathModule()) {
            $tabs['ouinpo-suite-ai'] = 'IA & parcours';
        }

        $tabs['ouinpo-suite-settings'] = 'Réglages';

        return $tabs;
    }

    private static function pageIntro(string $title, string $text): void
    {
        ?>
        <div class="wrap ouinpo-suite-wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <p class="ouinpo-suite-muted"><?php echo esc_html($text); ?></p>
        <?php
    }

    private static function endPage(): void
    {
        echo '</div>';
    }

    private static function tabs(array $tabs, string $current): void
    {
        echo '<nav class="nav-tab-wrapper">';

        foreach ($tabs as $slug => $label) {
            $class = ($slug === $current) ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">'
                . esc_html($label) . '</a>';
        }

        echo '</nav>';
    }

    private static function currentTab(string $default = 'catalogue'): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : $default;
        return $tab !== '' ? $tab : $default;
    }

    private static function subTabs(string $page, array $tabs, string $current): void
    {
        if (empty($tabs)) {
            return;
        }

        echo '<nav class="nav-tab-wrapper ouinpo-suite-tabs">';

        foreach ($tabs as $slug => $label) {
            $class = ($slug === $current) ? ' nav-tab-active' : '';
            $url = add_query_arg([
                'page' => $page,
                'tab'  => $slug,
            ], admin_url('admin.php'));

            echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_url($url) . '">'
                . esc_html($label) . '</a>';
        }

        echo '</nav>';
    }

    private static function metricCard(string $title, string $value, string $caption = '', ?string $url = null): void
    {
        ?>
        <div class="card ouinpo-suite-card">
            <h2 class="ouinpo-suite-card-title"><?php echo esc_html($title); ?></h2>
            <div class="ouinpo-suite-metric-value">
                <?php echo esc_html($value); ?>
            </div>
            <?php if ($caption !== ''): ?>
                <p class="ouinpo-suite-muted ouinpo-suite-card-caption"><?php echo esc_html($caption); ?></p>
            <?php endif; ?>
            <?php if ($url): ?>
                <p class="ouinpo-suite-no-margin">
                    <a class="button button-secondary" href="<?php echo esc_url($url); ?>">Voir</a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function quickAction(string $title, string $text, string $url): void
    {
        ?>
        <div class="card ouinpo-suite-card">
            <h3 class="ouinpo-suite-card-title"><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($text); ?></p>
            <p class="ouinpo-suite-bottomless">
                <a class="button button-primary" href="<?php echo esc_url($url); ?>">Ouvrir</a>
            </p>
        </div>
        <?php
    }

    private static function statusBadge(bool $ok, string $okLabel = 'OK', string $koLabel = 'À vérifier'): void
    {
        $label  = $ok ? $okLabel : $koLabel;
        $class = $ok ? 'ouinpo-suite-status ouinpo-suite-status--ok' : 'ouinpo-suite-status ouinpo-suite-status--warning';

        echo '<span class="' . esc_attr($class) . '">'
            . esc_html($label)
            . '</span>';
    }

    private static function tableExists(string $table): bool
    {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }

    private static function safeTableCount(string $table, string $where = '1=1'): ?int
    {
        global $wpdb;

        if (!self::tableExists($table)) {
            return null;
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        $value = $wpdb->get_var($sql);

        return ($value === null) ? null : (int) $value;
    }

    private static function recentPosts(string $postType, int $limit = 5): array
    {
        if (!post_type_exists($postType)) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        return is_array($posts) ? $posts : [];
    }

    private static function renderRecentPostsPanel(string $title, string $postType, string $listUrl): void
    {
        $posts = self::recentPosts($postType, 5);
        ?>
        <div class="card ouinpo-suite-card">
            <h2 class="ouinpo-suite-card-title"><?php echo esc_html($title); ?></h2>

            <?php if (!$posts): ?>
                <div class="ouinpo-suite-empty">Aucun élément récent.</div>
            <?php else: ?>
                <ul class="ouinpo-suite-list">
                    <?php foreach ($posts as $post): ?>
                        <li class="ouinpo-suite-list-item">
                            <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                <?php echo esc_html(get_the_title($post->ID) ?: '(sans titre)'); ?>
                            </a>
                            <br>
                            <span class="ouinpo-suite-muted">
                                <?php echo esc_html(get_the_date('d/m/Y H:i', $post->ID)); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="ouinpo-suite-bottomless">
                <a class="button button-secondary" href="<?php echo esc_url($listUrl); ?>">Voir tout</a>
            </p>
        </div>
        <?php
    }

    private static function dashboardStats(): array
    {
        global $wpdb;

        $t_exercises   = $wpdb->prefix . 'ouin_exo_exercises';
        $t_groups      = $wpdb->prefix . 'ouin_exo_groups';
        $t_members     = $wpdb->prefix . 'ouin_exo_group_members';
        $t_paths       = $wpdb->prefix . 'ouin_sf_paths';
        $t_suggestions = $wpdb->prefix . 'ouin_sf_suggestions';
        $t_progress    = $wpdb->prefix . 'ouinpo_progress';

        $submissionTotal = 0;
        $resourceTotal   = 0;
        $submission7d    = 0;

        if (ModuleSettings::isEnabled('submissions') && post_type_exists('ouinpo_submission')) {
            $c = wp_count_posts('ouinpo_submission');

            if ($c) {
                foreach (get_object_vars($c) as $n) {
                    $submissionTotal += (int) $n;
                }
            }

            $submission7d = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(ID)
                 FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_status NOT IN ('trash','auto-draft')
                   AND post_date_gmt >= %s",
                'ouinpo_submission',
                gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS)
            ));
        }

        if (ModuleSettings::isEnabled('submissions') && post_type_exists('ouinpo_resource')) {
            $c = wp_count_posts('ouinpo_resource');

            if ($c) {
                foreach (get_object_vars($c) as $n) {
                    $resourceTotal += (int) $n;
                }
            }
        }

        return [
            'exercises_total'   => self::safeTableCount($t_exercises),
            'exercises_active'  => self::safeTableCount($t_exercises, 'is_active = 1'),
            'groups_total'      => self::safeTableCount($t_groups),
            'members_total'     => self::safeTableCount($t_members),
            'paths_active'      => ModuleSettings::isEnabled('segfault') ? self::safeTableCount($t_paths, 'is_active = 1') : null,
            'suggestions_total' => ModuleSettings::isEnabled('segfault') ? self::safeTableCount($t_suggestions) : null,
            'gate_progress'     => ModuleSettings::isEnabled('gate') ? self::safeTableCount($t_progress) : null,
            'submissions_total' => $submissionTotal,
            'submissions_7d'    => $submission7d,
            'resources_total'   => $resourceTotal,
        ];
    }

    public static function renderDashboard(): void
    {
        $stats = self::dashboardStats();

        self::pageIntro('OuInPo Suite', 'Vue d’ensemble de la suite et accès rapides aux actions les plus utiles.');
        self::tabs(self::mainTabs(), self::ROOT_SLUG);
        ?>

        <h2 class="ouinpo-suite-section">Vue d’ensemble</h2>
        <div class="ouinpo-suite-grid-compact">
            <?php
            self::metricCard(
                'Exercices',
                ($stats['exercises_total'] !== null ? number_format_i18n($stats['exercises_total']) : '—'),
                ($stats['exercises_active'] !== null ? number_format_i18n($stats['exercises_active']) . ' actifs' : 'table non disponible'),
                admin_url('admin.php?page=ouinpo-suite-contents')
            );

            self::metricCard(
                'Classes & élèves',
                ($stats['groups_total'] !== null ? number_format_i18n($stats['groups_total']) : '—'),
                ($stats['members_total'] !== null ? number_format_i18n($stats['members_total']) . ' affectations' : 'groupes ou affectations indisponibles'),
                admin_url('admin.php?page=ouinpo-suite-classes')
            );

            if (ModuleSettings::isEnabled('submissions')) {
                self::metricCard(
                    'Dépôts élèves',
                    number_format_i18n((int) $stats['submissions_total']),
                    number_format_i18n((int) $stats['submissions_7d']) . ' sur 7 jours',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );
            }

            if (self::hasAiOrPathModule()) {
                self::metricCard(
                    'IA & parcours',
                    ($stats['suggestions_total'] !== null ? number_format_i18n($stats['suggestions_total']) : '—'),
                    'suggestions' . ($stats['paths_active'] !== null ? ' · ' . number_format_i18n($stats['paths_active']) . ' parcours actifs' : ''),
                    admin_url('admin.php?page=ouinpo-suite-ai')
                );
            }
            ?>
        </div>

        <h2 class="ouinpo-suite-section">Actions rapides</h2>
        <div class="ouinpo-suite-grid">
            <?php
            self::quickAction(
                'Créer / gérer les contenus',
                'Accès au catalogue des exercices et sujets pratiques.',
                admin_url('admin.php?page=ouinpo-suite-contents')
            );

            if (ModuleSettings::isEnabled('flashcards')) {
                self::quickAction(
                    'Préparer les révisions',
                    'Gérer les flashcards et paquets de cartes.',
                    admin_url('admin.php?page=ouinpo-suite-revisions')
                );
            }

            self::quickAction(
                'Importer des exercices',
                'Ajouter rapidement de nouveaux exercices.',
                admin_url('admin.php?page=ouinpo-suite-contents&tab=import')
            );

            self::quickAction(
                'Devoirs surveillés',
                'Gérer les DS et évaluations.',
                admin_url('admin.php?page=ouinpo-suite-evaluations&tab=ds')
            );

            if (current_user_can('edit_users')) {
                self::quickAction(
                    'Groupes',
                    'Organiser les classes et affectations.',
                    admin_url('admin.php?page=ouinpo-suite-classes')
                );
            }

            if (ModuleSettings::isEnabled('submissions')) {
                self::quickAction(
                    'Dépôts élèves',
                    'Voir les travaux récents des élèves.',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );
            }

            if (current_user_can('manage_options') && self::hasAiOrPathModule()) {
                self::quickAction(
                    'IA & parcours',
                    'Configurer les assistants, les parcours et l’indexation.',
                    admin_url('admin.php?page=ouinpo-suite-ai')
                );
            }
            ?>
        </div>

        <?php if (ModuleSettings::isEnabled('submissions')): ?>
            <h2 class="ouinpo-suite-section">Activité récente</h2>
            <div class="ouinpo-suite-grid-wide">
                <?php
                self::renderRecentPostsPanel(
                    'Derniers dépôts élèves',
                    'ouinpo_submission',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );

                self::renderRecentPostsPanel(
                    'Dernières ressources prof',
                    'ouinpo_resource',
                    admin_url('edit.php?post_type=ouinpo_resource')
                );
                ?>
            </div>
        <?php endif; ?>

        <?php
        self::endPage();
    }

    public static function renderContentsHub(): void
    {
        $tab   = self::currentTab('catalogue');
        $stats = self::dashboardStats();

        self::pageIntro('Contenus', 'Exercices, sujets pratiques, imports et paramètres des contenus pédagogiques.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-contents');

        self::subTabs('ouinpo-suite-contents', [
            'catalogue' => 'Catalogue',
            'pratiques' => 'Sujets pratiques',
            'import'    => 'Import',
            'options'   => 'Options',
        ], $tab);

        if ($tab === 'catalogue') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::metricCard(
                    'Exercices',
                    ($stats['exercises_total'] !== null ? number_format_i18n($stats['exercises_total']) : '—'),
                    ($stats['exercises_active'] !== null ? number_format_i18n($stats['exercises_active']) . ' actifs' : 'table non disponible'),
                    admin_url('admin.php?page=ouinpo-exercices')
                );

                self::quickAction(
                    'Gérer les exercices',
                    'Créer, modifier et organiser les exercices du catalogue.',
                    admin_url('admin.php?page=ouinpo-exercices')
                );

                self::quickAction(
                    'Exercices type bac',
                    'Retrouver les exercices orientés bac et leurs métadonnées.',
                    admin_url('admin.php?page=ouinpo-exercices')
                );
                ?>
            </div>
            <?php
        } elseif ($tab === 'pratiques') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Sujets pratiques',
                    'Gérer les sujets pratiques et leurs appels.',
                    admin_url('admin.php?page=ouinpo-practical-subjects')
                );

                self::quickAction(
                    'Catalogue des exercices',
                    'Revenir au catalogue principal des exercices.',
                    admin_url('admin.php?page=ouinpo-exercices')
                );
                ?>
            </div>
            <?php
        } elseif ($tab === 'import') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Importer des exercices',
                    'Ajouter rapidement de nouveaux exercices au catalogue.',
                    admin_url('admin.php?page=ouinpo-import-exercises')
                );
                ?>
            </div>
            <?php
        } elseif ($tab === 'options') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                if (current_user_can('manage_options')) {
                    self::quickAction(
                        'Options des contenus',
                        'Configurer les réglages du module Exercices.',
                        admin_url('admin.php?page=ouinpo-exercises-settings')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Options</h3>
                        <p>Ces réglages sont réservés aux administrateurs.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        }

        self::endPage();
    }

    public static function renderRevisionsHub(): void
    {
        if (!ModuleSettings::isEnabled('flashcards')) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::ROOT_SLUG));
            exit;
        }

        global $wpdb;

        $tab = self::currentTab('flashcards');

        $tDecks = $wpdb->prefix . 'ouin_fc_decks';
        $tCards = $wpdb->prefix . 'ouin_fc_cards';

        $decksCount = self::safeTableCount($tDecks);
        $cardsCount = self::safeTableCount($tCards);

        self::pageIntro('Révisions', 'Flashcards, paquets de cartes et mémorisation active.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-revisions');

        self::subTabs('ouinpo-suite-revisions', [
            'flashcards' => 'Flashcards',
            'import'     => 'Import',
        ], $tab);

        if ($tab === 'flashcards') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::metricCard(
                    'Paquets de cartes',
                    ($decksCount !== null ? number_format_i18n($decksCount) : '—'),
                    $decksCount !== null ? 'paquets enregistrés' : 'table non disponible',
                    current_user_can('manage_options') ? admin_url('admin.php?page=ouinpo-flashcards&tab=decks') : null
                );

                self::metricCard(
                    'Cartes',
                    ($cardsCount !== null ? number_format_i18n($cardsCount) : '—'),
                    $cardsCount !== null ? 'cartes enregistrées' : 'table non disponible',
                    current_user_can('manage_options') ? admin_url('admin.php?page=ouinpo-flashcards&tab=cards') : null
                );

                if (current_user_can('manage_options')) {
                    self::quickAction(
                        'Gérer les flashcards',
                        'Créer les paquets, modifier les cartes et préparer les révisions.',
                        admin_url('admin.php?page=ouinpo-flashcards')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Flashcards</h3>
                        <p>La gestion des flashcards est réservée aux administrateurs.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } elseif ($tab === 'import') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                if (current_user_can('manage_options')) {
                    self::quickAction(
                        'Importer des cartes',
                        'Importer des flashcards dans un paquet existant.',
                        admin_url('admin.php?page=ouinpo-flashcards&tab=import')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Import</h3>
                        <p>Import réservé aux administrateurs.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        }

        self::endPage();
    }

    public static function renderClassesHub(): void
    {
        $tab   = self::currentTab('groupes');
        $stats = self::dashboardStats();

        self::pageIntro('Classes & élèves', 'Organisation des classes, affectations et productions des élèves.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-classes');

        $classTabs = [
            'groupes'      => 'Classes',
            'affectations' => 'Affectations',
        ];

        if (ModuleSettings::isEnabled('submissions')) {
            $classTabs['depots'] = 'Dépôts';
            $classTabs['ressources'] = 'Ressources';
        }

        if (!isset($classTabs[$tab])) {
            $tab = 'groupes';
        }

        self::subTabs('ouinpo-suite-classes', $classTabs, $tab);

        if ($tab === 'groupes') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::metricCard(
                    'Classes',
                    ($stats['groups_total'] !== null ? number_format_i18n($stats['groups_total']) : '—'),
                    ($stats['members_total'] !== null ? number_format_i18n($stats['members_total']) . ' affectations' : 'indisponible'),
                    current_user_can('edit_users') ? admin_url('admin.php?page=ouinpo-groups') : null
                );

                if (current_user_can('edit_users')) {
                    self::quickAction(
                        'Gérer les classes',
                        'Créer, modifier et organiser les groupes.',
                        admin_url('admin.php?page=ouinpo-groups')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Groupes</h3>
                        <p>La gestion des groupes est réservée aux profils autorisés.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } elseif ($tab === 'affectations') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::metricCard(
                    'Affectations',
                    ($stats['members_total'] !== null ? number_format_i18n($stats['members_total']) : '—'),
                    'élèves liés à des classes',
                    current_user_can('edit_users') ? admin_url('admin.php?page=ouinpo-assignments') : null
                );

                if (current_user_can('edit_users')) {
                    self::quickAction(
                        'Gérer les affectations',
                        'Associer les élèves aux classes.',
                        admin_url('admin.php?page=ouinpo-assignments')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Affectations</h3>
                        <p>La gestion des affectations est réservée aux profils autorisés.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } elseif ($tab === 'depots' && ModuleSettings::isEnabled('submissions')) {
            ?>
            <div class="ouinpo-suite-grid-wide">
                <?php
                self::metricCard(
                    'Dépôts élèves',
                    number_format_i18n((int) $stats['submissions_total']),
                    number_format_i18n((int) $stats['submissions_7d']) . ' sur 7 jours',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );

                self::quickAction(
                    'Voir tous les dépôts',
                    'Accéder à la liste complète des travaux déposés.',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );

                self::renderRecentPostsPanel(
                    'Derniers dépôts élèves',
                    'ouinpo_submission',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );
                ?>
            </div>
            <?php
        } elseif ($tab === 'ressources' && ModuleSettings::isEnabled('submissions')) {
            ?>
            <div class="ouinpo-suite-grid-wide">
                <?php
                self::metricCard(
                    'Ressources prof',
                    number_format_i18n((int) $stats['resources_total']),
                    'ressources enregistrées',
                    admin_url('edit.php?post_type=ouinpo_resource')
                );

                self::quickAction(
                    'Voir les ressources',
                    'Accéder à la liste complète des ressources pédagogiques.',
                    admin_url('edit.php?post_type=ouinpo_resource')
                );

                self::renderRecentPostsPanel(
                    'Dernières ressources prof',
                    'ouinpo_resource',
                    admin_url('edit.php?post_type=ouinpo_resource')
                );
                ?>
            </div>
            <?php
        }

        self::endPage();
    }

    private static function boTrackOptions(): array
    {
        return [
            'SNT' => 'SNT',
            'NSI' => 'NSI',
        ];
    }

    private static function boLevelOptions(): array
    {
        return [
            'Seconde'     => 'Seconde',
            'Première'    => 'Première',
            'Terminale'   => 'Terminale',
            'Transversal' => 'Transversal',
        ];
    }

    private static function normalizeBoTrack(string $track): string
    {
        $track = strtoupper(trim($track));
        return array_key_exists($track, self::boTrackOptions()) ? $track : 'NSI';
    }

    private static function normalizeBoLevel(string $level): string
    {
        $level = trim($level);

        $map = [
            'seconde'     => 'Seconde',
            'premiere'    => 'Première',
            'première'    => 'Première',
            'terminal'    => 'Terminale',
            'terminale'   => 'Terminale',
            'transversal' => 'Transversal',
        ];

        $key = strtolower(remove_accents($level));
        $key = str_replace('è', 'e', $key);

        if (isset($map[$key])) {
            return $map[$key];
        }

        return array_key_exists($level, self::boLevelOptions()) ? $level : 'Première';
    }

    private static function normalizeBoDomainSlug(string $slug, string $domain = ''): string
    {
        $source = trim($slug) !== '' ? $slug : $domain;
        $source = sanitize_title(remove_accents((string) $source));
        return $source !== '' ? $source : 'domaine';
    }

    private static function boDomainKey(string $domainSlug, string $track, string $level): string
    {
        return self::normalizeBoDomainSlug($domainSlug) . '|' . self::normalizeBoTrack($track) . '|' . self::normalizeBoLevel($level);
    }

    private static function parseBoDomainKey(string $domainKey): array
    {
        $parts = explode('|', $domainKey);

        return [
            'domain_slug' => isset($parts[0]) ? self::normalizeBoDomainSlug((string) $parts[0]) : '',
            'track'       => isset($parts[1]) ? self::normalizeBoTrack((string) $parts[1]) : 'NSI',
            'level'       => isset($parts[2]) ? self::normalizeBoLevel((string) $parts[2]) : 'Première',
        ];
    }

    private static function storedBoDomains(): array
    {
        $raw = get_option(self::OPTION_BO_DOMAINS, []);

        if (!is_array($raw)) {
            return [];
        }

        $domains = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $domain = isset($item['domain'])
                ? sanitize_text_field((string) $item['domain'])
                : '';

            $domainSlug = isset($item['domain_slug'])
                ? self::normalizeBoDomainSlug((string) $item['domain_slug'], $domain)
                : self::normalizeBoDomainSlug('', $domain);

            $track = isset($item['track'])
                ? self::normalizeBoTrack((string) $item['track'])
                : 'NSI';

            $level = isset($item['level'])
                ? self::normalizeBoLevel((string) $item['level'])
                : 'Première';

            $active = isset($item['active']) ? (int) $item['active'] : 1;

            if ($domain === '' || $domainSlug === '') {
                continue;
            }

            $key = self::boDomainKey($domainSlug, $track, $level);

            $domains[$key] = [
                'domain'       => $domain,
                'domain_slug'  => $domainSlug,
                'track'        => $track,
                'level'        => $level,
                'active'       => $active === 1 ? 1 : 0,
                'total'        => 0,
                'active_total' => 0,
            ];
        }

        return $domains;
    }

    private static function saveStoredBoDomains(array $domains): void
    {
        update_option(self::OPTION_BO_DOMAINS, array_values($domains), false);
    }

    private static function referentielBoDomains(string $tComp, bool $activeOnly = false): array
    {
        global $wpdb;

        $domains = self::storedBoDomains();

        if (self::tableExists($tComp)) {
            $rows = $wpdb->get_results(
                "SELECT
                    domain,
                    domain_slug,
                    track,
                    level,
                    COUNT(*) AS total,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) AS active_total
                FROM {$tComp}
                WHERE domain IS NOT NULL
                  AND domain <> ''
                  AND domain_slug IS NOT NULL
                  AND domain_slug <> ''
                GROUP BY domain, domain_slug, track, level
                ORDER BY
                    FIELD(track, 'SNT', 'NSI'),
                    FIELD(level, 'Seconde', 'Première', 'Terminale', 'Transversal'),
                    domain ASC"
            );

            foreach ($rows as $row) {
                $domain = sanitize_text_field((string) $row->domain);
                $domainSlug = self::normalizeBoDomainSlug((string) $row->domain_slug, $domain);
                $track = self::normalizeBoTrack((string) $row->track);
                $level = self::normalizeBoLevel((string) $row->level);

                if ($domain === '' || $domainSlug === '') {
                    continue;
                }

                $key = self::boDomainKey($domainSlug, $track, $level);

                if (!isset($domains[$key])) {
                    $domains[$key] = [
                        'domain'       => $domain,
                        'domain_slug'  => $domainSlug,
                        'track'        => $track,
                        'level'        => $level,
                        'active'       => ((int) $row->active_total > 0) ? 1 : 0,
                        'total'        => (int) $row->total,
                        'active_total' => (int) $row->active_total,
                    ];
                } else {
                    $domains[$key]['domain'] = $domain;
                    $domains[$key]['domain_slug'] = $domainSlug;
                    $domains[$key]['track'] = $track;
                    $domains[$key]['level'] = $level;
                    $domains[$key]['total'] = (int) $row->total;
                    $domains[$key]['active_total'] = (int) $row->active_total;
                }
            }
        }

        if ($activeOnly) {
            $domains = array_filter($domains, static function ($domain) {
                return (int) ($domain['active'] ?? 1) === 1;
            });
        }

        uasort($domains, static function ($a, $b) {
            return strcasecmp(
                ($a['track'] ?? '') . ' ' . ($a['level'] ?? '') . ' ' . ($a['domain'] ?? ''),
                ($b['track'] ?? '') . ' ' . ($b['level'] ?? '') . ' ' . ($b['domain'] ?? '')
            );
        });

        return $domains;
    }

    public static function renderReferentielHub(): void
    {
        $tab = self::currentTab('competences');

        self::pageIntro('Référentiel BO', 'Domaines, compétences officielles et associations pédagogiques.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-referentiel');

        self::subTabs('ouinpo-suite-referentiel', [
            'competences' => 'Compétences BO',
            'domaines'   => 'Domaines BO',
            'courses'    => 'Cours ↔ compétences',
            'years'      => 'Années scolaires',
        ], $tab);

        if ($tab === 'domaines') {
            self::renderReferentielDomainsTable();

        } elseif ($tab === 'courses') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Cours ↔ compétences BO',
                    'Associer les cours WordPress aux compétences du BO.',
                    admin_url('admin.php?page=ouinpo-courses-competencies')
                );
                ?>
            </div>
            <?php

        } elseif ($tab === 'years') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Années scolaires',
                    'Créer les futures années et choisir l’année active.',
                    admin_url('admin.php?page=ouinpo-years')
                );
                ?>
            </div>
            <?php

        } else {
            self::renderReferentielCompetenciesTable();
        }

        self::endPage();
    }

    private static function renderReferentielCompetenciesTable(): void
    {
        if (!current_user_can('edit_users')) {
            ?>
            <div class="card ouinpo-suite-card-bounded">
                <h2 class="ouinpo-suite-card-title">Compétences BO</h2>
                <p>Accès réservé aux profils autorisés.</p>
            </div>
            <?php
            return;
        }

        global $wpdb;

        $tComp = $wpdb->prefix . 'ouin_exo_competencies';
        $tPost = $wpdb->prefix . 'ouin_exo_post_competency';
        $tExo  = $wpdb->prefix . 'ouin_exo_exercise_competency';

        self::handleReferentielBoActions($tComp);
        settings_errors('ouinpo_ref_bo');

        if (!self::tableExists($tComp)) {
            ?>
            <div class="card ouinpo-suite-card-bounded">
                <h2 class="ouinpo-suite-card-title">Compétences BO</h2>
                <p>La table des compétences n’existe pas encore.</p>
            </div>
            <?php
            return;
        }

        $trackRaw = isset($_GET['ref_track']) ? sanitize_text_field((string) $_GET['ref_track']) : '';
        $levelRaw = isset($_GET['ref_level']) ? sanitize_text_field((string) $_GET['ref_level']) : '';

        $track = $trackRaw !== '' ? self::normalizeBoTrack($trackRaw) : '';
        $level = $levelRaw !== '' ? self::normalizeBoLevel($levelRaw) : '';
        $search = isset($_GET['ref_s']) ? sanitize_text_field((string) $_GET['ref_s']) : '';

        if ($track !== '' && !isset(self::boTrackOptions()[$track])) {
            $track = '';
        }

        if ($level !== '' && !isset(self::boLevelOptions()[$level])) {
            $level = '';
        }

        $where = ['1=1'];
        $args  = [];

        if ($track !== '') {
            $where[] = 'c.track = %s';
            $args[] = $track;
        }

        if ($level !== '') {
            $where[] = 'c.level = %s';
            $args[] = $level;
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(c.domain LIKE %s OR c.competency LIKE %s OR c.slug LIKE %s)';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $sql = "
            SELECT
                c.id,
                c.domain,
                c.domain_slug,
                c.track,
                c.level,
                c.competency,
                c.slug,
                c.active,
                (
                    SELECT COUNT(*)
                    FROM {$tPost} pc
                    WHERE pc.competency_id = c.id
                ) AS course_count,
                (
                    SELECT COUNT(*)
                    FROM {$tExo} ec
                    WHERE ec.competency_id = c.id
                ) AS exercise_count
            FROM {$tComp} c
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                FIELD(c.track, 'SNT', 'NSI'),
                FIELD(c.level, 'Seconde', 'Première', 'Terminale', 'Transversal'),
                c.domain ASC,
                c.id ASC
        ";

        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, ...$args);
        }

        $rows = $wpdb->get_results($sql);

        $editId = isset($_GET['edit_competency_id']) ? (int) $_GET['edit_competency_id'] : 0;
        $editRow = null;

        if ($editId > 0 && current_user_can('manage_options')) {
            $editRow = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$tComp} WHERE id = %d",
                $editId
            ));
        }

        $domainOptions = self::referentielBoDomains($tComp, true);
        $selectedDomainKey = '';

        if ($editRow) {
            $selectedDomainKey = self::boDomainKey(
                (string) $editRow->domain_slug,
                (string) $editRow->track,
                (string) $editRow->level
            );
        }
        ?>

        <?php if (current_user_can('manage_options')): ?>
            <div id="ouinpo-bo-form" class="card ouinpo-suite-form-card">
                <h2 class="ouinpo-suite-card-title">
                    <?php echo $editRow ? 'Modifier une compétence BO' : 'Ajouter une compétence BO'; ?>
                </h2>

                <form method="post">
                    <?php wp_nonce_field('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce'); ?>
                    <input type="hidden" name="ouinpo_ref_action" value="save_competency">
                    <input type="hidden" name="competency_id" value="<?php echo $editRow ? (int) $editRow->id : 0; ?>">

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th><label for="bo_domain_choice">Domaine BO</label></th>
                                <td>
                                    <?php if (!$domainOptions): ?>
                                        <p class="description">
                                            Aucun domaine BO n’est disponible. Crée d’abord un domaine dans l’onglet “Domaines BO”.
                                        </p>
                                    <?php else: ?>
                                        <select id="bo_domain_choice" required>
                                            <option value="">— Choisir un domaine —</option>

                                            <?php foreach ($domainOptions as $key => $domainItem): ?>
                                                <option
                                                    value="<?php echo esc_attr($key); ?>"
                                                    data-domain="<?php echo esc_attr($domainItem['domain']); ?>"
                                                    data-domain-slug="<?php echo esc_attr($domainItem['domain_slug']); ?>"
                                                    data-track="<?php echo esc_attr($domainItem['track']); ?>"
                                                    data-level="<?php echo esc_attr($domainItem['level']); ?>"
                                                    <?php selected($selectedDomainKey, $key); ?>
                                                >
                                                    <?php echo esc_html($domainItem['domain'] . ' — ' . $domainItem['track'] . ' / ' . $domainItem['level']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <p id="bo_domain_summary" class="description"></p>
                                    <?php endif; ?>

                                    <input id="bo_domain" type="hidden" name="domain" value="<?php echo esc_attr($editRow->domain ?? ''); ?>">
                                    <input id="bo_domain_slug" type="hidden" name="domain_slug" value="<?php echo esc_attr($editRow->domain_slug ?? ''); ?>">
                                    <input id="bo_track" type="hidden" name="track" value="<?php echo esc_attr($editRow->track ?? 'NSI'); ?>">
                                    <input id="bo_level" type="hidden" name="level" value="<?php echo esc_attr($editRow->level ?? 'Première'); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th><label for="bo_competency">Compétence</label></th>
                                <td>
                                    <textarea id="bo_competency" name="competency" rows="4" class="large-text" required><?php
                                        echo esc_textarea($editRow->competency ?? '');
                                    ?></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="bo_slug">Slug compétence</label></th>
                                <td>
                                    <input id="bo_slug" type="text" name="slug" class="regular-text"
                                        value="<?php echo esc_attr($editRow->slug ?? ''); ?>"
                                        placeholder="ex : algorithmique-parcours-tableau">
                                    <p class="description">
                                        Laisse vide pour générer automatiquement le slug à partir du domaine et de la compétence.
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th>Active</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="active" value="1" <?php checked((int)($editRow->active ?? 1), 1); ?>>
                                        Compétence active
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button($editRow ? 'Enregistrer les modifications' : 'Ajouter la compétence'); ?>

                    <?php if ($editRow): ?>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-suite-referentiel&tab=competences')); ?>">
                            Annuler
                        </a>
                    <?php endif; ?>
                </form>

            </div>
        <?php endif; ?>

        <div class="card ouinpo-suite-card-bounded">
            <h2 class="ouinpo-suite-card-title">Compétences BO</h2>

            <form method="get" class="ouinpo-suite-filter-form">
                <input type="hidden" name="page" value="ouinpo-suite-referentiel">
                <input type="hidden" name="tab" value="competences">

                <select name="ref_track">
                    <option value="">Toutes les pistes</option>
                    <?php foreach (self::boTrackOptions() as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($track, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="ref_level">
                    <option value="">Tous les niveaux</option>
                    <?php foreach (self::boLevelOptions() as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($level, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="search" name="ref_s" value="<?php echo esc_attr($search); ?>" placeholder="Rechercher domaine / compétence / slug">

                <?php submit_button('Filtrer', 'secondary', '', false); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th class="ouinpo-suite-col-22">Domaine</th>
                        <th class="ouinpo-suite-col-10">Piste</th>
                        <th class="ouinpo-suite-col-10">Niveau</th>
                        <th>Compétence</th>
                        <th class="ouinpo-suite-col-8">Cours</th>
                        <th class="ouinpo-suite-col-8">Exos</th>
                        <th class="ouinpo-suite-col-8">Actif</th>
                        <th class="ouinpo-suite-col-18">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="8">Aucune compétence trouvée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($row->domain); ?></strong><br>
                                    <span class="ouinpo-suite-muted"><?php echo esc_html((string) $row->slug); ?></span>
                                </td>
                                <td><?php echo esc_html($row->track); ?></td>
                                <td><?php echo esc_html($row->level); ?></td>
                                <td><?php echo esc_html($row->competency); ?></td>
                                <td><?php echo number_format_i18n((int) $row->course_count); ?></td>
                                <td><?php echo number_format_i18n((int) $row->exercise_count); ?></td>
                                <td><?php echo ((int) $row->active === 1) ? 'Oui' : 'Non'; ?></td>
                                <td>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a class="button button-small" href="<?php echo esc_url(add_query_arg([
                                            'page' => 'ouinpo-suite-referentiel',
                                            'tab' => 'competences',
                                            'edit_competency_id' => (int) $row->id,
                                        ], admin_url('admin.php'))); ?>#ouinpo-bo-form">
                                            Modifier
                                        </a>

                                        <form method="post" class="ouinpo-suite-inline-form">
                                            <?php wp_nonce_field('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce'); ?>
                                            <input type="hidden" name="ouinpo_ref_action" value="toggle_competency">
                                            <input type="hidden" name="competency_id" value="<?php echo (int) $row->id; ?>">
                                            <input type="hidden" name="active" value="<?php echo ((int) $row->active === 1) ? '0' : '1'; ?>">
                                            <?php submit_button(((int) $row->active === 1) ? 'Désactiver' : 'Réactiver', 'secondary small', '', false); ?>
                                        </form>

                                        <form method="post" class="ouinpo-suite-inline-form" data-confirm="Supprimer cette compétence ? Si elle est liée à des exercices, cours ou suivis, elle sera seulement désactivée.">
                                            <?php wp_nonce_field('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce'); ?>
                                            <input type="hidden" name="ouinpo_ref_action" value="delete_competency">
                                            <input type="hidden" name="competency_id" value="<?php echo (int) $row->id; ?>">
                                            <?php submit_button('Supprimer', 'delete small', '', false); ?>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function renderReferentielDomainsTable(): void
    {
        if (!current_user_can('edit_users')) {
            ?>
            <div class="card ouinpo-suite-card-bounded">
                <h2 class="ouinpo-suite-card-title">Domaines BO</h2>
                <p>Accès réservé aux profils autorisés.</p>
            </div>
            <?php
            return;
        }

        global $wpdb;

        $tComp = $wpdb->prefix . 'ouin_exo_competencies';

        self::handleReferentielBoActions($tComp);
        settings_errors('ouinpo_ref_bo');

        $domains = self::referentielBoDomains($tComp, false);
        $levelOptions = self::boLevelOptions();

        $editDomainKey = isset($_GET['edit_domain_key'])
            ? sanitize_text_field((string) wp_unslash($_GET['edit_domain_key']))
            : '';

        $editDomain = null;

        if ($editDomainKey !== '' && current_user_can('manage_options') && isset($domains[$editDomainKey])) {
            $editDomain = $domains[$editDomainKey];
        }
        ?>

        <?php if (current_user_can('manage_options')): ?>
            <div id="ouinpo-bo-domain-form" class="card ouinpo-suite-form-card">
                <h2 class="ouinpo-suite-card-title">
                    <?php echo $editDomain ? 'Modifier un domaine BO' : 'Créer un domaine BO'; ?>
                </h2>

                <form method="post">
                    <?php wp_nonce_field('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce'); ?>

                    <input type="hidden" name="ouinpo_ref_action" value="save_domain">
                    <input type="hidden" name="old_domain_key" value="<?php echo esc_attr($editDomainKey); ?>">

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th><label for="bo_domain_name">Nom du domaine</label></th>
                                <td>
                                    <input id="bo_domain_name" type="text" name="domain" class="regular-text"
                                        value="<?php echo esc_attr($editDomain['domain'] ?? ''); ?>" required>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="bo_domain_slug">Slug domaine</label></th>
                                <td>
                                    <input id="bo_domain_slug" type="text" name="domain_slug" class="regular-text"
                                        value="<?php echo esc_attr($editDomain['domain_slug'] ?? ''); ?>" required>
                                    <p class="description">Il est rempli automatiquement à partir du nom, mais peut être corrigé avant enregistrement.</p>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="bo_domain_track">Piste</label></th>
                                <td>
                                    <select id="bo_domain_track" name="track">
                                        <?php foreach (self::boTrackOptions() as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($editDomain['track'] ?? 'NSI', $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="bo_domain_level">Niveau</label></th>
                                <td>
                                    <select id="bo_domain_level" name="level">
                                        <?php foreach (self::boLevelOptions() as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($editDomain['level'] ?? 'Première', $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button($editDomain ? 'Enregistrer le domaine' : 'Créer le domaine'); ?>

                    <?php if ($editDomain): ?>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-suite-referentiel&tab=domaines')); ?>">
                            Annuler
                        </a>
                    <?php endif; ?>
                </form>

            </div>
        <?php endif; ?>

        <div class="card ouinpo-suite-card-bounded">
            <h2 class="ouinpo-suite-card-title">Domaines BO</h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Domaine</th>
                        <th>Slug</th>
                        <th>Piste</th>
                        <th>Niveau</th>
                        <th class="ouinpo-suite-col-10">Compétences</th>
                        <th class="ouinpo-suite-col-10">Actives</th>
                        <th class="ouinpo-suite-col-10">État</th>
                        <th class="ouinpo-suite-col-24">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$domains): ?>
                        <tr>
                            <td colspan="8">Aucun domaine trouvé.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($domains as $domainKey => $row): ?>
                            <?php
                            $total = isset($row['total']) ? (int) $row['total'] : 0;
                            $activeTotal = isset($row['active_total']) ? (int) $row['active_total'] : 0;
                            $isActive = (int) ($row['active'] ?? 1) === 1;
                            ?>
                            <tr>
                                <td><?php echo esc_html($row['domain']); ?></td>
                                <td><code><?php echo esc_html($row['domain_slug']); ?></code></td>
                                <td><?php echo esc_html($row['track']); ?></td>
                                <td><?php echo esc_html($levelOptions[$row['level']] ?? $row['level']); ?></td>
                                <td><?php echo number_format_i18n($total); ?></td>
                                <td><?php echo number_format_i18n($activeTotal); ?></td>
                                <td><?php echo $isActive ? 'Actif' : 'Masqué'; ?></td>
                                <td>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a class="button button-small" href="<?php echo esc_url(add_query_arg([
                                            'page' => 'ouinpo-suite-referentiel',
                                            'tab' => 'domaines',
                                            'edit_domain_key' => $domainKey,
                                        ], admin_url('admin.php'))); ?>#ouinpo-bo-domain-form">
                                            Modifier
                                        </a>

                                        <form method="post" class="ouinpo-suite-inline-form">
                                            <?php wp_nonce_field('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce'); ?>
                                            <input type="hidden" name="ouinpo_ref_action" value="toggle_domain">
                                            <input type="hidden" name="domain_key" value="<?php echo esc_attr($domainKey); ?>">
                                            <input type="hidden" name="active" value="<?php echo $isActive ? '0' : '1'; ?>">
                                            <?php submit_button($isActive ? 'Masquer' : 'Réactiver', 'secondary small', '', false); ?>
                                        </form>

                                        <form method="post" class="ouinpo-suite-inline-form" data-confirm="Supprimer ce domaine du registre ? Les compétences déjà liées ne seront pas supprimées.">
                                            <?php wp_nonce_field('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce'); ?>
                                            <input type="hidden" name="ouinpo_ref_action" value="delete_domain">
                                            <input type="hidden" name="domain_key" value="<?php echo esc_attr($domainKey); ?>">
                                            <?php submit_button('Supprimer', 'delete small', '', false); ?>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function handleReferentielBoActions(string $tComp): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_POST['ouinpo_ref_action'])
            ? sanitize_key((string) wp_unslash($_POST['ouinpo_ref_action']))
            : '';

        if ($action === '') {
            return;
        }

        check_admin_referer('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce');

        global $wpdb;

        if ($action === 'save_competency') {
            if (!self::tableExists($tComp)) {
                add_settings_error('ouinpo_ref_bo', 'bo_missing_table', 'La table des compétences n’existe pas encore.', 'error');
                return;
            }

            $id = isset($_POST['competency_id']) ? (int) $_POST['competency_id'] : 0;

            $domain = isset($_POST['domain'])
                ? sanitize_text_field((string) wp_unslash($_POST['domain']))
                : '';

            $domainSlug = isset($_POST['domain_slug'])
                ? self::normalizeBoDomainSlug((string) wp_unslash($_POST['domain_slug']), $domain)
                : self::normalizeBoDomainSlug('', $domain);

            $track = isset($_POST['track'])
                ? self::normalizeBoTrack((string) wp_unslash($_POST['track']))
                : 'NSI';

            $level = isset($_POST['level'])
                ? self::normalizeBoLevel((string) wp_unslash($_POST['level']))
                : 'Première';

            $competency = isset($_POST['competency'])
                ? wp_kses_post((string) wp_unslash($_POST['competency']))
                : '';

            $slug = isset($_POST['slug'])
                ? sanitize_title((string) wp_unslash($_POST['slug']))
                : '';

            $active = isset($_POST['active']) ? 1 : 0;

            if ($domain === '' || $domainSlug === '' || trim(wp_strip_all_tags($competency)) === '') {
                add_settings_error('ouinpo_ref_bo', 'bo_missing', 'Domaine et compétence sont obligatoires.', 'error');
                return;
            }

            $domainKey = self::boDomainKey($domainSlug, $track, $level);
            $domains = self::referentielBoDomains($tComp, true);

            if (!isset($domains[$domainKey])) {
                add_settings_error('ouinpo_ref_bo', 'bo_domain_unknown', 'Choisis un domaine BO existant avant de créer une compétence.', 'error');
                return;
            }

            if ($slug === '') {
                $slug = sanitize_title($domainSlug . '-' . wp_strip_all_tags($competency));
                $slug = substr($slug, 0, 120);
            }

            $duplicateId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tComp} WHERE slug = %s AND id <> %d LIMIT 1",
                $slug,
                $id
            ));

            if ($duplicateId > 0) {
                add_settings_error('ouinpo_ref_bo', 'bo_duplicate_slug', 'Ce slug de compétence existe déjà.', 'error');
                return;
            }

            $label = trim(wp_strip_all_tags($domain . ' — ' . $competency));

            $data = [
                'domain'      => $domain,
                'domain_slug' => $domainSlug,
                'track'       => $track,
                'level'       => $level,
                'competency'  => $competency,
                'slug'        => $slug,
                'active'      => $active,
                'label'       => $label,
            ];

            $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'];

            if ($id > 0) {
                $wpdb->update($tComp, $data, ['id' => $id], $formats, ['%d']);
                add_settings_error('ouinpo_ref_bo', 'bo_updated', 'Compétence BO modifiée.', 'updated');
            } else {
                $wpdb->insert($tComp, $data, $formats);
                add_settings_error('ouinpo_ref_bo', 'bo_inserted', 'Compétence BO ajoutée.', 'updated');
            }

            return;
        }

        if ($action === 'toggle_competency') {
            if (!self::tableExists($tComp)) {
                return;
            }

            $id = isset($_POST['competency_id']) ? (int) $_POST['competency_id'] : 0;
            $active = isset($_POST['active']) ? (int) $_POST['active'] : 0;
            $active = $active === 1 ? 1 : 0;

            if ($id > 0) {
                $wpdb->update($tComp, ['active' => $active], ['id' => $id], ['%d'], ['%d']);
                add_settings_error(
                    'ouinpo_ref_bo',
                    'bo_toggled',
                    $active ? 'Compétence réactivée.' : 'Compétence désactivée.',
                    'updated'
                );
            }

            return;
        }

        if ($action === 'delete_competency') {
            if (!self::tableExists($tComp)) {
                return;
            }

            $id = isset($_POST['competency_id']) ? (int) $_POST['competency_id'] : 0;

            if ($id <= 0) {
                return;
            }

            $refs = self::referentielCompetencyReferenceCount($id);

            if ($refs > 0) {
                $wpdb->update($tComp, ['active' => 0], ['id' => $id], ['%d'], ['%d']);
                add_settings_error(
                    'ouinpo_ref_bo',
                    'bo_soft_deleted',
                    'Cette compétence est liée à des contenus ou suivis. Elle a été désactivée au lieu d’être supprimée.',
                    'updated'
                );
                return;
            }

            $wpdb->delete($tComp, ['id' => $id], ['%d']);
            add_settings_error('ouinpo_ref_bo', 'bo_deleted', 'Compétence supprimée définitivement.', 'updated');
            return;
        }

        if ($action === 'save_domain') {
            $oldKey = isset($_POST['old_domain_key'])
                ? sanitize_text_field((string) wp_unslash($_POST['old_domain_key']))
                : '';

            $domain = isset($_POST['domain'])
                ? sanitize_text_field((string) wp_unslash($_POST['domain']))
                : '';

            $domainSlug = isset($_POST['domain_slug'])
                ? self::normalizeBoDomainSlug((string) wp_unslash($_POST['domain_slug']), $domain)
                : self::normalizeBoDomainSlug('', $domain);

            $track = isset($_POST['track'])
                ? self::normalizeBoTrack((string) wp_unslash($_POST['track']))
                : 'NSI';

            $level = isset($_POST['level'])
                ? self::normalizeBoLevel((string) wp_unslash($_POST['level']))
                : 'Première';

            if ($domain === '' || $domainSlug === '') {
                add_settings_error('ouinpo_ref_bo', 'domain_missing', 'Nom de domaine obligatoire.', 'error');
                return;
            }

            $domains = self::storedBoDomains();
            $newKey = self::boDomainKey($domainSlug, $track, $level);

            if ($oldKey !== '' && $oldKey !== $newKey) {
                $old = self::parseBoDomainKey($oldKey);

                if (self::tableExists($tComp)) {
                    $conflict = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$tComp}
                         WHERE domain_slug = %s
                           AND track = %s
                           AND level = %s
                           AND NOT (domain_slug = %s AND track = %s AND level = %s)",
                        $domainSlug,
                        $track,
                        $level,
                        $old['domain_slug'],
                        $old['track'],
                        $old['level']
                    ));

                    if ($conflict > 0) {
                        add_settings_error('ouinpo_ref_bo', 'domain_conflict', 'Un domaine avec ce slug, cette piste et ce niveau existe déjà.', 'error');
                        return;
                    }

                    $wpdb->update(
                        $tComp,
                        [
                            'domain'      => $domain,
                            'domain_slug' => $domainSlug,
                            'track'       => $track,
                            'level'       => $level,
                        ],
                        [
                            'domain_slug' => $old['domain_slug'],
                            'track'       => $old['track'],
                            'level'       => $old['level'],
                        ],
                        ['%s', '%s', '%s', '%s'],
                        ['%s', '%s', '%s']
                    );
                }

                if (isset($domains[$oldKey])) {
                    unset($domains[$oldKey]);
                }
            }

            if ($oldKey !== '' && $oldKey === $newKey && self::tableExists($tComp)) {
                $old = self::parseBoDomainKey($oldKey);

                $wpdb->update(
                    $tComp,
                    ['domain' => $domain],
                    [
                        'domain_slug' => $old['domain_slug'],
                        'track'       => $old['track'],
                        'level'       => $old['level'],
                    ],
                    ['%s'],
                    ['%s', '%s', '%s']
                );
            }

            $domains[$newKey] = [
                'domain'       => $domain,
                'domain_slug'  => $domainSlug,
                'track'        => $track,
                'level'        => $level,
                'active'       => 1,
                'total'        => 0,
                'active_total' => 0,
            ];

            self::saveStoredBoDomains($domains);

            add_settings_error('ouinpo_ref_bo', 'domain_saved', 'Domaine BO enregistré.', 'updated');
            return;
        }

        if ($action === 'toggle_domain') {
            $domainKey = isset($_POST['domain_key'])
                ? sanitize_text_field((string) wp_unslash($_POST['domain_key']))
                : '';

            $active = isset($_POST['active']) ? (int) $_POST['active'] : 0;
            $active = $active === 1 ? 1 : 0;

            if ($domainKey === '') {
                return;
            }

            $allDomains = self::referentielBoDomains($tComp, false);
            $domains = self::storedBoDomains();

            if (isset($allDomains[$domainKey])) {
                $domains[$domainKey] = [
                    'domain'       => (string) $allDomains[$domainKey]['domain'],
                    'domain_slug'  => (string) $allDomains[$domainKey]['domain_slug'],
                    'track'        => (string) $allDomains[$domainKey]['track'],
                    'level'        => (string) $allDomains[$domainKey]['level'],
                    'active'       => $active,
                    'total'        => 0,
                    'active_total' => 0,
                ];

                self::saveStoredBoDomains($domains);

                add_settings_error(
                    'ouinpo_ref_bo',
                    'domain_toggled',
                    $active ? 'Domaine réactivé.' : 'Domaine masqué pour les nouvelles compétences.',
                    'updated'
                );
            }

            return;
        }

        if ($action === 'delete_domain') {
            $domainKey = isset($_POST['domain_key'])
                ? sanitize_text_field((string) wp_unslash($_POST['domain_key']))
                : '';

            if ($domainKey === '') {
                return;
            }

            $domains = self::storedBoDomains();

            if (isset($domains[$domainKey])) {
                unset($domains[$domainKey]);
                self::saveStoredBoDomains($domains);
            } else {
                $allDomains = self::referentielBoDomains($tComp, false);

                if (isset($allDomains[$domainKey])) {
                    $domains[$domainKey] = [
                        'domain'       => (string) $allDomains[$domainKey]['domain'],
                        'domain_slug'  => (string) $allDomains[$domainKey]['domain_slug'],
                        'track'        => (string) $allDomains[$domainKey]['track'],
                        'level'        => (string) $allDomains[$domainKey]['level'],
                        'active'       => 0,
                        'total'        => 0,
                        'active_total' => 0,
                    ];

                    self::saveStoredBoDomains($domains);
                }
            }

            add_settings_error('ouinpo_ref_bo', 'domain_deleted', 'Domaine BO retiré du registre. Les compétences existantes ne sont pas supprimées.', 'updated');
            return;
        }
    }

    private static function referentielCompetencyReferenceCount(int $competencyId): int
    {
        global $wpdb;

        $prefixExo = $wpdb->prefix . 'ouin_exo_';
        $prefixFc  = $wpdb->prefix . 'ouin_fc_';

        $checks = [
            [$prefixExo . 'post_competency', 'competency_id'],
            [$prefixExo . 'exercise_competency', 'competency_id'],
            [$prefixExo . 'user_competencies', 'competency_id'],
            [$prefixExo . 'competency_teaching', 'competency_id'],
            [$prefixExo . 'assessment_competencies', 'competency_id'],
            [$prefixExo . 'assessment_results', 'competency_id'],
            [$prefixFc . 'card_competency', 'competency_id'],
        ];

        $total = 0;

        foreach ($checks as [$table, $column]) {
            if (!self::tableExists($table)) {
                continue;
            }

            $total += (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$column} = %d",
                $competencyId
            ));
        }

        return $total;
    }

    public static function renderEvaluationsHub(): void
    {
        $tab = self::currentTab('suivi');

        self::pageIntro('Évaluations', 'Devoirs surveillés et suivi des compétences des élèves.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-evaluations');

        $tabs = [
            'suivi' => 'Suivi des compétences',
            'ds'    => 'Devoirs surveillés',
        ];

        if (ModuleSettings::isEnabled('submissions')) {
            $tabs['depots'] = 'Dépôts élèves';
        }

        if (!isset($tabs[$tab])) {
            $tab = 'suivi';
        }

        self::subTabs('ouinpo-suite-evaluations', $tabs, $tab);

        if ($tab === 'suivi') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Suivi des compétences',
                    'Accéder à l’écran de suivi par année, classe, domaine et élève.',
                    admin_url('admin.php?page=ouinpo-competencies')
                );
                ?>
            </div>
            <?php
        } elseif ($tab === 'ds') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Devoirs surveillés',
                    'Créer et gérer les DS.',
                    admin_url('admin.php?page=ouinpo-assessments')
                );

                self::quickAction(
                    'Concepteur de devoirs',
                    'Composer un devoir à partir des exercices du catalogue.',
                    admin_url('admin.php?page=ouinpo-assessment-builder')
                );
                ?>
            </div>
            <?php
        } elseif ($tab === 'depots' && ModuleSettings::isEnabled('submissions')) {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Dépôts élèves',
                    'Voir les travaux récents pour croiser évaluation et entraînement.',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );
                ?>
            </div>
            <?php
        }

        self::endPage();
    }

    public static function renderAiHub(): void
    {
        $aiTabs = [];

        if (ModuleSettings::isEnabled('segfault')) {
            $aiTabs['segfault'] = 'SegFault';
        }

        if (ModuleSettings::isEnabled('gate')) {
            $aiTabs['gate'] = 'Gate';
        }

        if (ModuleSettings::isEnabled('rechtext')) {
            $aiTabs['rechtext'] = 'Recherche textuelle';
        }

        $defaultTab = array_key_first($aiTabs) ?: 'none';
        $tab = self::currentTab($defaultTab);

        if (!isset($aiTabs[$tab])) {
            $tab = $defaultTab;
        }

        $stats = self::dashboardStats();

        self::pageIntro('IA & parcours', 'Outils d’accompagnement, recommandations, parcours et assistants.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-ai');

        if (empty($aiTabs)) {
            ?>
            <div class="card ouinpo-suite-card-bounded">
                <h2 class="ouinpo-suite-card-title">IA & parcours</h2>
                <p>Aucun module IA ou parcours n’est activé.</p>
            </div>
            <?php
            self::endPage();
            return;
        }

        self::subTabs('ouinpo-suite-ai', $aiTabs, $tab);

        if ($tab === 'segfault') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::metricCard(
                    'Suggestions',
                    ($stats['suggestions_total'] !== null ? number_format_i18n($stats['suggestions_total']) : '—'),
                    'suggestions enregistrées',
                    current_user_can('manage_options') ? admin_url('admin.php?page=ouinpo-segfault') : null
                );

                if (current_user_can('manage_options')) {
                    self::quickAction(
                        'Ouvrir SegFault',
                        'Accéder aux outils, sources, indexation et paramètres SegFault.',
                        admin_url('admin.php?page=ouinpo-segfault')
                    );

                    self::quickAction(
                        'Suivi élèves SegFault',
                        'Consulter le suivi des élèves lié à SegFault.',
                        admin_url('admin.php?page=ouinpo-segfault-progress')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">SegFault</h3>
                        <p>Les réglages SegFault sont réservés aux administrateurs.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } elseif ($tab === 'gate') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::metricCard(
                    'Progressions Gate',
                    ($stats['gate_progress'] !== null ? number_format_i18n($stats['gate_progress']) : '—'),
                    'entrées de progression',
                    current_user_can('list_users') ? admin_url('admin.php?page=ouinpo') : null
                );

                if (current_user_can('list_users')) {
                    self::quickAction(
                        'Ouvrir Gate',
                        'Accéder au suivi global et aux certificats.',
                        admin_url('admin.php?page=ouinpo')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Gate</h3>
                        <p>L’accès à Gate est réservé aux profils autorisés.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } elseif ($tab === 'rechtext') {
            ?>
            <div class="ouinpo-suite-grid">
                <div class="card ouinpo-suite-card">
                    <h3 class="ouinpo-suite-card-title">Recherche textuelle</h3>
                    <p>
                        Le module Recherche textuelle fournit principalement un shortcode pédagogique
                        pour visualiser la recherche dans un texte. Il n’a pas encore d’écran d’administration dédié.
                    </p>
                    <p><code>[ouinpo_recherche_textuelle]</code></p>
                </div>
            </div>
            <?php
        }

        self::endPage();
    }

    private static function renderDiagnosticHub(): void
    {
        global $wpdb;

        $upload = wp_upload_dir();
        $upload_ok = empty($upload['error']) && !empty($upload['basedir']) && is_writable((string) $upload['basedir']);

        $environment = [
            'Version OuInPo Suite' => defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '—',
            'Version WordPress'    => get_bloginfo('version'),
            'Version PHP'          => PHP_VERSION,
            'Préfixe des tables'   => $wpdb->prefix,
            'Jeu de caractères BD' => $wpdb->get_charset_collate(),
            'Fuseau horaire'       => wp_timezone_string() ?: '—',
            'Dossier uploads'      => $upload_ok ? 'Accessible en écriture' : 'À vérifier',
        ];

        $options = [
            'Version BD suite'       => (string) get_option('ouinpo_suite_version', 'non installée'),
            'Version BD exercices'   => (string) get_option('ouinpo_exo_db_version', 'non installée'),
            'Version BD flashcards'  => (string) get_option('ouinpo_flashcards_db_version', 'non installée'),
            'IA Albert'              => ((int) get_option('ouinpo_sf_albert_enabled', 0) === 1) ? 'Activée' : 'Désactivée',
            'IA publique Albert'     => ((int) get_option('ouinpo_sf_public_albert_enabled', 0) === 1) ? 'Activée' : 'Désactivée',
            'Clé Albert'             => trim((string) get_option('ouinpo_sf_albert_api_key', '')) !== '' ? 'Présente, valeur masquée' : 'Absente',
            'Clé OpenAI'             => trim((string) get_option('ouinpo_sf_openai_api_key', '')) !== '' ? 'Présente, valeur masquée' : 'Absente',
            'Index WXR SegFault'     => trim((string) get_option('ouinpo_sf_wxr_path', '')) !== '' ? 'Chemin configuré' : 'Non configuré',
        ];
        ?>
        <div class="notice notice-info ouinpo-suite-notice">
            <p class="ouinpo-suite-no-margin">
                <strong>Diagnostic de diffusion.</strong>
                Cette page sert à vérifier qu’une installation OuInPo est saine et à repérer ce qui ne doit jamais partir dans une archive partagée : clés API, données élèves, logs, réponses, chemins locaux.
            </p>
        </div>

        <div class="ouinpo-suite-grid ouinpo-suite-grid-spaced">
            <?php
            self::renderKeyValueCard('Environnement', $environment);
            self::renderKeyValueCard('Options principales', $options);
            ?>
        </div>
        <?php
        self::renderTablesDiagnostic();
        self::renderDistributionWarnings();
    }

    private static function renderKeyValueCard(string $title, array $rows): void
    {
        ?>
        <div class="card ouinpo-suite-card">
            <h2 class="ouinpo-suite-card-title"><?php echo esc_html($title); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <?php foreach ($rows as $label => $value): ?>
                        <tr>
                            <th class="ouinpo-suite-col-42"><?php echo esc_html((string) $label); ?></th>
                            <td><?php echo esc_html((string) $value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function diagnosticTableGroups(): array
    {
        global $wpdb;

        $p = $wpdb->prefix;

        return [
            'Noyau OuInPo / Gate' => [
                $p . 'ouinpo_progress'   => 'Progression Gate',
                $p . 'ouinpo_logs'       => 'Logs Gate',
                $p . 'ouinpo_signatures' => 'Signatures / certificats',
            ],
            'SegFault / parcours' => [
                $p . 'ouin_sf_paths'        => 'Parcours individualisés',
                $p . 'ouin_sf_path_items'   => 'Éléments de parcours',
                $p . 'ouin_sf_path_targets' => 'Affectations de parcours',
                $p . 'ouin_sf_suggestions'  => 'Suggestions IA / exercices',
            ],
            'Exercices' => [
                $p . 'ouin_exo_academic_years'          => 'Années scolaires',
                $p . 'ouin_exo_school_levels'           => 'Niveaux',
                $p . 'ouin_exo_groups'                  => 'Classes / groupes',
                $p . 'ouin_exo_group_members'           => 'Membres des classes',
                $p . 'ouin_exo_exercises'               => 'Exercices',
                $p . 'ouin_exo_exercise_school_level'   => 'Niveaux des exercices',
                $p . 'ouin_exo_difficulties'            => 'Difficultés',
                $p . 'ouin_exo_hints'                   => 'Indices',
                $p . 'ouin_exo_solutions'               => 'Solutions',
                $p . 'ouin_exo_competencies'            => 'Compétences BO',
                $p . 'ouin_exo_exercise_competency'     => 'Liens exercices / compétences',
                $p . 'ouin_exo_post_competency'         => 'Liens cours / compétences',
                $p . 'ouin_exo_user_status'             => 'Statuts élèves',
                $p . 'ouin_exo_user_reveals'            => 'Indices / solutions révélés',
                $p . 'ouin_exo_user_competencies'       => 'Compétences élèves',
                $p . 'ouin_exo_competency_teaching'     => 'Compétences vues en classe',
                $p . 'ouin_exo_exam_meta'               => 'Métadonnées bac',
                $p . 'ouin_exo_practical_calls'         => 'Appels pratiques',
                $p . 'ouin_exo_practical_files'         => 'Fichiers pratiques',
                $p . 'ouin_exo_practical_call_attempts' => 'Tentatives pratiques',
                $p . 'ouin_exo_practical_call_status'   => 'Statuts pratiques',
                $p . 'ouin_exo_badges'                  => 'Badges',
                $p . 'ouin_exo_user_badges'             => 'Badges élèves',
                $p . 'ouin_exo_assessments'             => 'Devoirs',
                $p . 'ouin_exo_assessment_items'        => 'Exercices des devoirs',
                $p . 'ouin_exo_assessment_competencies' => 'Compétences des devoirs',
                $p . 'ouin_exo_assessment_results'      => 'Résultats des devoirs',
                $p . 'ouin_exo_assessment_attendance'   => 'Présences aux devoirs',
                $p . 'ouin_exo_ai_attempts'             => 'Tentatives IA',
            ],
            'Flashcards' => [
                $p . 'ouin_fc_decks'           => 'Paquets de cartes',
                $p . 'ouin_fc_cards'           => 'Cartes',
                $p . 'ouin_fc_card_competency' => 'Liens cartes / compétences',
                $p . 'ouin_fc_user_cards'      => 'État des cartes par élève',
                $p . 'ouin_fc_reviews'         => 'Historique de révision',
            ],
        ];
    }

    private static function renderTablesDiagnostic(): void
    {
        ?>
        <div class="card ouinpo-suite-card-bounded ouinpo-suite-card-spaced">
            <h2 class="ouinpo-suite-card-title">Tables attendues</h2>
            <p class="ouinpo-suite-muted">
                Les compteurs sont utiles pour vérifier une installation. Pour une archive de diffusion, les tables contenant des données élèves doivent être vides ou absentes du paquet exporté.
            </p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th class="ouinpo-suite-col-22">Module</th>
                        <th>Table</th>
                        <th class="ouinpo-suite-col-26">Rôle</th>
                        <th class="ouinpo-suite-col-12">État</th>
                        <th class="ouinpo-suite-col-10">Lignes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (self::diagnosticTableGroups() as $group => $tables): ?>
                        <?php foreach ($tables as $table => $label): ?>
                            <?php
                            $exists = self::tableExists($table);
                            $count = $exists ? self::safeTableCount($table) : null;
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) $group); ?></td>
                                <td><code><?php echo esc_html((string) $table); ?></code></td>
                                <td><?php echo esc_html((string) $label); ?></td>
                                <td><?php self::statusBadge($exists, 'OK', 'Absente'); ?></td>
                                <td><?php echo $count === null ? '—' : esc_html(number_format_i18n($count)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function renderDistributionWarnings(): void
    {
        global $wpdb;

        $sensitiveTables = [
            $wpdb->prefix . 'ouinpo_logs'                    => 'réponses aux énigmes / exercices interactifs',
            $wpdb->prefix . 'ouinpo_progress'                => 'progression Gate',
            $wpdb->prefix . 'ouinpo_signatures'              => 'signatures et messages',
            $wpdb->prefix . 'ouin_exo_group_members'         => 'composition des classes',
            $wpdb->prefix . 'ouin_exo_user_status'           => 'statuts d’exercices par élève',
            $wpdb->prefix . 'ouin_exo_user_reveals'          => 'indices et solutions révélés',
            $wpdb->prefix . 'ouin_exo_user_competencies'     => 'compétences attribuées aux élèves',
            $wpdb->prefix . 'ouin_exo_user_badges'           => 'badges attribués',
            $wpdb->prefix . 'ouin_exo_assessment_results'    => 'résultats de devoirs',
            $wpdb->prefix . 'ouin_exo_assessment_attendance' => 'présences aux devoirs',
            $wpdb->prefix . 'ouin_exo_ai_attempts'           => 'traces de tentatives IA',
            $wpdb->prefix . 'ouin_fc_user_cards'             => 'état des flashcards par élève',
            $wpdb->prefix . 'ouin_fc_reviews'                => 'historique des révisions',
            $wpdb->prefix . 'ouin_sf_suggestions'            => 'suggestions personnalisées',
        ];
        ?>
        <div class="card ouinpo-suite-card-bounded ouinpo-suite-card-spaced">
            <h2 class="ouinpo-suite-card-title">Contrôle avant partage</h2>
            <p class="ouinpo-suite-muted">
                Ces éléments ne doivent pas être inclus dans une archive destinée à d’autres professeurs.
            </p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Élément sensible</th>
                        <th class="ouinpo-suite-col-16">Lignes</th>
                        <th class="ouinpo-suite-col-22">Conclusion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sensitiveTables as $table => $label): ?>
                        <?php $count = self::safeTableCount($table); ?>
                        <tr>
                            <td>
                                <code><?php echo esc_html($table); ?></code><br>
                                <span class="ouinpo-suite-muted"><?php echo esc_html($label); ?></span>
                            </td>
                            <td><?php echo $count === null ? '—' : esc_html(number_format_i18n($count)); ?></td>
                            <td>
                                <?php
                                if ($count === null || $count === 0) {
                                    self::statusBadge(true, 'OK', 'OK');
                                } else {
                                    self::statusBadge(false, 'Ne pas exporter', 'Ne pas exporter');
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><code>ouinpo_sf_albert_api_key</code><br><span class="ouinpo-suite-muted">clé API Albert</span></td>
                        <td>—</td>
                        <td><?php self::statusBadge(trim((string) get_option('ouinpo_sf_albert_api_key', '')) === '', 'Absente', 'Présente : ne pas exporter'); ?></td>
                    </tr>
                    <tr>
                        <td><code>ouinpo_sf_openai_api_key</code><br><span class="ouinpo-suite-muted">clé API OpenAI</span></td>
                        <td>—</td>
                        <td><?php self::statusBadge(trim((string) get_option('ouinpo_sf_openai_api_key', '')) === '', 'Absente', 'Présente : ne pas exporter'); ?></td>
                    </tr>
                    <tr>
                        <td><code>ouinpo_sf_wxr_path</code><br><span class="ouinpo-suite-muted">chemin local vers un export WordPress / RAG</span></td>
                        <td>—</td>
                        <td><?php self::statusBadge(trim((string) get_option('ouinpo_sf_wxr_path', '')) === '', 'Absent', 'Présent : chemin local à nettoyer'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function renderAppearanceSettings(): void
    {
        if (!current_user_can('manage_options')) {
            ?>
            <div class="notice notice-error">
                <p>Ces réglages sont réservés aux administrateurs.</p>
            </div>
            <?php
            return;
        }

        $option_name = 'ouinpo_suite_style_mode';

        $current = get_option($option_name, 'ouinpo');

        if (!in_array($current, ['ouinpo', 'neutral', 'bsio'], true)) {
            $current = 'ouinpo';
        }

        ?>
        <div class="card ouinpo-suite-card-bounded">
            <h2 class="ouinpo-suite-card-title">Apparence publique</h2>

            <p class="ouinpo-suite-muted">
                Choisissez le style utilisé par les pages publiques de la suite.
                Le style sobre est recommandé pour une installation partagée avec d’autres enseignants.
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('ouinpo_suite_settings'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">Style de l’interface</th>
                            <td>
                                <fieldset>
                                    <label class="ouinpo-suite-settings-choice">
                                        <input
                                            type="radio"
                                            name="<?php echo esc_attr($option_name); ?>"
                                            value="ouinpo"
                                            <?php checked($current, 'ouinpo'); ?>
                                        >
                                        <strong>OuInPo</strong>
                                        <span class="ouinpo-suite-muted">
                                            — style actuel, plus marqué et pataphysique.
                                        </span>
                                    </label>

                                    <label class="ouinpo-suite-settings-choice">
                                        <input
                                            type="radio"
                                            name="<?php echo esc_attr($option_name); ?>"
                                            value="bsio"
                                            <?php checked($current, 'bsio'); ?>
                                        >
                                        <strong>B.S.I.O. &mdash; Bureau des Services Informatiques Ouinpiens</strong>
                                        <span class="ouinpo-suite-muted">
                                            &mdash; intranet clair, tickets, procedures et rapports d'incident.
                                        </span>
                                    </label>

                                    <label class="ouinpo-suite-settings-choice">
                                        <input
                                            type="radio"
                                            name="<?php echo esc_attr($option_name); ?>"
                                            value="neutral"
                                            <?php checked($current, 'neutral'); ?>
                                        >
                                        <strong>Sobre</strong>
                                        <span class="ouinpo-suite-muted">
                                            — style neutre, clair, adapté à une version partageable.
                                        </span>
                                    </label>

                                    <p class="description">
                                        Ce réglage ne supprime aucun fichier CSS. Il choisit simplement le thème chargé côté public.
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Enregistrer l’apparence'); ?>
            </form>
        </div>
        <?php
    }

    private static function renderModulesSettings(): void
    {
        if (!current_user_can('manage_options')) {
            ?>
            <div class="notice notice-error">
                <p>Ces réglages sont réservés aux administrateurs.</p>
            </div>
            <?php
            return;
        }

        $plugin = Bootstrap::makePlugin();

        $modules = [];
        foreach ($plugin->modules() as $module) {
            $modules[$module->id()] = $module->name();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ouinpo_suite_modules_nonce'])) {
            check_admin_referer('ouinpo_suite_modules', 'ouinpo_suite_modules_nonce');

            $posted = isset($_POST['ouinpo_suite_enabled_modules']) && is_array($_POST['ouinpo_suite_enabled_modules'])
                ? array_map('sanitize_key', wp_unslash($_POST['ouinpo_suite_enabled_modules']))
                : [];

            ModuleSettings::saveEnabledModules($posted);

            wp_safe_redirect(add_query_arg(
                [
                    'page'    => 'ouinpo-suite-settings',
                    'tab'     => 'modules',
                    'updated' => '1',
                ],
                admin_url('admin.php')
            ));
            exit;
        }

        $enabled = ModuleSettings::getEnabledModules();
        $locked  = ModuleSettings::lockedModules();

        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Réglages des modules enregistrés.</p></div>';
        }
        ?>
        <div class="card ouinpo-suite-card-bounded">
            <h2 class="ouinpo-suite-card-title">Modules de la suite</h2>

            <p class="ouinpo-suite-muted">
                Désactiver un module empêche son chargement, ses menus, shortcodes ou fonctionnalités,
                mais ne supprime aucune table ni aucune donnée.
            </p>

            <form method="post">
                <?php wp_nonce_field('ouinpo_suite_modules', 'ouinpo_suite_modules_nonce'); ?>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th class="ouinpo-suite-col-22">État</th>
                            <th>Module</th>
                            <th class="ouinpo-suite-col-35">Remarque</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $id => $name): ?>
                            <?php
                            $isLocked  = in_array($id, $locked, true);
                            $isEnabled = in_array($id, $enabled, true) || $isLocked;
                            ?>
                            <tr>
                                <td>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="ouinpo_suite_enabled_modules[]"
                                            value="<?php echo esc_attr($id); ?>"
                                            <?php checked($isEnabled); ?>
                                            <?php disabled($isLocked); ?>
                                        >
                                        <?php echo $isEnabled ? 'Activé' : 'Désactivé'; ?>
                                    </label>

                                    <?php if ($isLocked): ?>
                                        <input type="hidden" name="ouinpo_suite_enabled_modules[]" value="<?php echo esc_attr($id); ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($name); ?></strong><br>
                                    <code><?php echo esc_html($id); ?></code>
                                </td>
                                <td>
                                    <?php if ($id === 'exercises'): ?>
                                        Socle de la suite. Non désactivable dans cette version.
                                    <?php elseif ($id === 'segfault'): ?>
                                        Chat, RAG, suggestions et parcours individualisés.
                                    <?php elseif ($id === 'flashcards'): ?>
                                        Cartes de révision et mémorisation.
                                    <?php elseif ($id === 'submissions'): ?>
                                        Dépôts élèves et ressources.
                                    <?php elseif ($id === 'gate'): ?>
                                        Énigmes, progression Gate et certificats.
                                    <?php elseif ($id === 'rechtext'): ?>
                                        Recherche textuelle.
                                    <?php elseif ($id === 'meta'): ?>
                                        Balises meta, Open Graph et image sociale.
                                    <?php else: ?>
                                        Module optionnel.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p><?php submit_button('Enregistrer les modules', 'primary', 'submit', false); ?></p>
            </form>
        </div>
        <?php
    }

    private static function renderPedagogicalImportHub(): void
    {
        if (!current_user_can('manage_options')) {
            ?>
            <div class="notice notice-error">
                <p>Import réservé aux administrateurs.</p>
            </div>
            <?php
            return;
        }

        $result = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ouinpo_pack_import_nonce'])) {
            check_admin_referer('ouinpo_pack_import', 'ouinpo_pack_import_nonce');

            $source = isset($_POST['ouinpo_pack_source'])
                ? sanitize_text_field((string) wp_unslash($_POST['ouinpo_pack_source']))
                : '';

            if ($source === 'bundled') {
                $pack = isset($_POST['ouinpo_pack_file'])
                    ? sanitize_file_name((string) wp_unslash($_POST['ouinpo_pack_file']))
                    : '';

                $path = trailingslashit(OUINPO_SUITE_DIR) . 'packs/' . $pack;

                if ($pack === '' || !str_ends_with($pack, '.json')) {
                    $result = [
                        'ok' => false,
                        'message' => 'Pack intégré invalide.',
                        'details' => [],
                    ];
                } else {
                    $result = PedagogicalPackImporter::importFromFile($path);
                }
            } elseif ($source === 'upload') {
                if (
                    empty($_FILES['ouinpo_pack_upload'])
                    || !isset($_FILES['ouinpo_pack_upload']['tmp_name'])
                    || !is_uploaded_file((string) $_FILES['ouinpo_pack_upload']['tmp_name'])
                ) {
                    $result = [
                        'ok' => false,
                        'message' => 'Aucun fichier JSON envoyé.',
                        'details' => [],
                    ];
                } else {
                    $name = isset($_FILES['ouinpo_pack_upload']['name'])
                        ? sanitize_file_name((string) $_FILES['ouinpo_pack_upload']['name'])
                        : '';

                    if (!str_ends_with($name, '.json')) {
                        $result = [
                            'ok' => false,
                            'message' => 'Le fichier envoyé doit être un JSON.',
                            'details' => [],
                        ];
                    } else {
                        $result = PedagogicalPackImporter::importFromFile((string) $_FILES['ouinpo_pack_upload']['tmp_name']);
                    }
                }
            } else {
                $result = [
                    'ok' => false,
                    'message' => 'Source d’import inconnue.',
                    'details' => [],
                ];
            }
        }

        $packsDir = trailingslashit(OUINPO_SUITE_DIR) . 'packs/';
        $bundledPacks = [];

        if (is_dir($packsDir)) {
            foreach (glob($packsDir . '*.json') ?: [] as $file) {
                $base = basename($file);

                if ($base === 'ouinpo-pack.schema.json') {
                    continue;
                }

                $bundledPacks[] = $base;
            }

            sort($bundledPacks);
        }

        if ($result !== null) {
            $class = !empty($result['ok']) ? 'notice notice-success' : 'notice notice-error';
            ?>
            <div class="<?php echo esc_attr($class); ?> is-dismissible">
                <p><strong><?php echo esc_html((string) $result['message']); ?></strong></p>

                <?php if (!empty($result['details']) && is_array($result['details'])): ?>
                    <ul class="ouinpo-suite-disc-list">
                        <?php foreach ($result['details'] as $key => $value): ?>
                            <?php if ($key === 'warnings' && is_array($value)): ?>
                                <li>
                                    <strong>Avertissements :</strong>
                                    <?php echo esc_html((string) count($value)); ?>
                                </li>
                            <?php elseif (!is_array($value)): ?>
                                <li>
                                    <?php echo esc_html((string) $key); ?> :
                                    <?php echo esc_html((string) $value); ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($result['details']['warnings']) && is_array($result['details']['warnings'])): ?>
                        <details>
                            <summary>Voir les avertissements</summary>
                            <ul class="ouinpo-suite-disc-list">
                                <?php foreach ($result['details']['warnings'] as $warning): ?>
                                    <li><?php echo esc_html((string) $warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
        }

        ?>
        <div class="card ouinpo-suite-card-bounded">
            <h2 class="ouinpo-suite-card-title">Import pédagogique</h2>

            <p class="ouinpo-suite-muted">
                Cette version importe uniquement les niveaux, difficultés et compétences BO.
                Les exercices, flashcards et sujets pratiques seront traités dans les versions suivantes de l’importeur.
            </p>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ouinpo_pack_import', 'ouinpo_pack_import_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">Pack intégré</th>
                            <td>
                                <label>
                                    <input type="radio" name="ouinpo_pack_source" value="bundled" checked>
                                    Importer un pack fourni avec l’extension
                                </label>

                                <br><br>

                                <select name="ouinpo_pack_file">
                                    <?php if (!$bundledPacks): ?>
                                        <option value="">Aucun pack disponible</option>
                                    <?php else: ?>
                                        <?php foreach ($bundledPacks as $pack): ?>
                                            <option value="<?php echo esc_attr($pack); ?>">
                                                <?php echo esc_html($pack); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Fichier externe</th>
                            <td>
                                <label>
                                    <input type="radio" name="ouinpo_pack_source" value="upload">
                                    Importer un fichier JSON depuis l’ordinateur
                                </label>

                                <br><br>

                                <input type="file" name="ouinpo_pack_upload" accept="application/json,.json">
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Importer le pack'); ?>
            </form>
        </div>
        <?php
    }

    public static function renderBadgesHub(): void
    {
        $tab = self::currentTab('catalogue');

        self::pageIntro(
            'Badges',
            'Gestion des badges, titres et attributions manuelles.'
        );

        self::tabs(self::mainTabs(), 'ouinpo-suite-badges');

        self::subTabs('ouinpo-suite-badges', [
            'catalogue'    => 'Catalogue des badges',
            'attributions' => 'Attributions manuelles',
        ], $tab);

        if ($tab === 'catalogue') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Catalogue des badges',
                    'Créer, modifier et organiser les badges disponibles.',
                    admin_url('admin.php?page=ouinpo-badges')
                );

                self::quickAction(
                    'Attributions manuelles',
                    'Attribuer ou retirer des badges à des élèves.',
                    admin_url('admin.php?page=ouinpo-badge-assignments')
                );
                ?>
            </div>
            <?php

        } elseif ($tab === 'attributions') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Attributions manuelles',
                    'Attribuer un badge à un élève, une classe ou vérifier les badges existants.',
                    admin_url('admin.php?page=ouinpo-badge-assignments')
                );

                self::quickAction(
                    'Catalogue des badges',
                    'Revenir à la gestion des badges.',
                    admin_url('admin.php?page=ouinpo-badges')
                );
                ?>
            </div>
            <?php
        }

        self::endPage();
    }

    public static function renderSettingsHub(): void
    {
        $tab = self::currentTab('modules');

        self::pageIntro('Réglages', 'Paramètres, diagnostic et maintenance légère de la suite.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-settings');

        $settingsTabs = [
            'modules' => 'Modules',
            'appearance' => 'Apparence',
        ];

        if (ModuleSettings::isEnabled('meta')) {
            $settingsTabs['meta'] = 'Meta & Social';
        }

        $settingsTabs['import'] = 'Import pédagogique';
        $settingsTabs['diagnostic'] = 'Diagnostic';
        $settingsTabs['maintenance'] = 'Maintenance';

        if (!isset($settingsTabs[$tab])) {
            $tab = 'modules';
        }

        self::subTabs('ouinpo-suite-settings', $settingsTabs, $tab);

        if ($tab === 'modules') {
            self::renderModulesSettings();

        } elseif ($tab === 'appearance') {
            self::renderAppearanceSettings();

        } elseif ($tab === 'meta' && ModuleSettings::isEnabled('meta')) {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                if (current_user_can('manage_options')) {
                    self::quickAction(
                        'Meta & Social',
                        'Gérer les balises meta, Open Graph et réglages sociaux.',
                        admin_url('admin.php?page=ouinpo-meta-social')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Meta & Social</h3>
                        <p>Ces réglages avancés sont réservés aux administrateurs.</p>
                    </div>
                    <?php
                }

                self::quickAction(
                    'Retour au tableau de bord',
                    'Revenir à la vue d’ensemble de la suite.',
                    admin_url('admin.php?page=' . self::ROOT_SLUG)
                );
                ?>
            </div>
            <?php
        } elseif ($tab === 'import') {
            self::renderPedagogicalImportHub();

        } elseif ($tab === 'diagnostic') {
            self::renderDiagnosticHub();
        } elseif ($tab === 'maintenance') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Importer des exercices',
                    'Accéder rapidement à l’écran d’import.',
                    admin_url('admin.php?page=ouinpo-import-exercises')
                );

                self::quickAction(
                    'Options exercices',
                    'Accéder aux réglages spécifiques du module Exercices.',
                    admin_url('admin.php?page=ouinpo-suite-contents&tab=options')
                );

                if (ModuleSettings::isEnabled('submissions')) {
                    self::quickAction(
                        'Voir les dépôts élèves',
                        'Consulter les productions récentes.',
                        admin_url('edit.php?post_type=ouinpo_submission')
                    );

                    self::quickAction(
                        'Voir les ressources prof',
                        'Accéder aux ressources pédagogiques.',
                        admin_url('edit.php?post_type=ouinpo_resource')
                    );
                }

                if (current_user_can('manage_options') && ModuleSettings::isEnabled('segfault')) {
                    self::quickAction(
                        'SegFault',
                        'Accéder aux réglages et outils SegFault.',
                        admin_url('admin.php?page=ouinpo-segfault')
                    );
                }
                ?>
            </div>

            <div class="card ouinpo-suite-card-bounded ouinpo-suite-card-spaced">
                <h2 class="ouinpo-suite-card-title">Rappel</h2>
                <p>Les opérations lourdes ou sensibles restent volontairement sur leurs écrans métier d’origine. Cette page sert surtout de point d’entrée, de contrôle et d’accès rapide.</p>
            </div>
            <?php
        }

        self::endPage();
    }
}
