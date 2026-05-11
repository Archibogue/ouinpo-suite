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
                'RÃ©visions',
                'RÃ©visions',
                'edit_posts',
                'ouinpo-suite-revisions',
                [self::class, 'renderRevisionsHub']
            );
        }

        add_submenu_page(
            self::ROOT_SLUG,
            'Ã‰valuations',
            'Ã‰valuations',
            'edit_posts',
            'ouinpo-suite-evaluations',
            [self::class, 'renderEvaluationsHub']
        );

        add_submenu_page(
            self::ROOT_SLUG,
            'Classes & Ã©lÃ¨ves',
            'Classes & Ã©lÃ¨ves',
            'edit_posts',
            'ouinpo-suite-classes',
            [self::class, 'renderClassesHub']
        );

        add_submenu_page(
            self::ROOT_SLUG,
            'RÃ©fÃ©rentiel BO',
            'RÃ©fÃ©rentiel BO',
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
            'RÃ©glages',
            'RÃ©glages',
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
            $tabs['ouinpo-suite-revisions'] = 'RÃ©visions';
        }

        $tabs['ouinpo-suite-evaluations'] = 'Ã‰valuations';
        $tabs['ouinpo-suite-classes'] = 'Classes & Ã©lÃ¨ves';
        $tabs['ouinpo-suite-referentiel'] = 'RÃ©fÃ©rentiel BO';

        if (current_user_can('manage_options')) {
            $tabs['ouinpo-suite-badges'] = 'Badges';
        }

        if (self::hasAiOrPathModule()) {
            $tabs['ouinpo-suite-ai'] = 'IA & parcours';
        }

        $tabs['ouinpo-suite-settings'] = 'RÃ©glages';

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

    private static function statusBadge(bool $ok, string $okLabel = 'OK', string $koLabel = 'Ã€ vÃ©rifier'): void
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
                <div class="ouinpo-suite-empty">Aucun Ã©lÃ©ment rÃ©cent.</div>
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

        self::pageIntro('OuInPo Suite', 'Vue dâ€™ensemble de la suite et accÃ¨s rapides aux actions les plus utiles.');
        self::tabs(self::mainTabs(), self::ROOT_SLUG);
        ?>

        <h2 class="ouinpo-suite-section">Vue dâ€™ensemble</h2>
        <div class="ouinpo-suite-grid-compact">
            <?php
            self::metricCard(
                'Exercices',
                ($stats['exercises_total'] !== null ? number_format_i18n($stats['exercises_total']) : 'â€”'),
                ($stats['exercises_active'] !== null ? number_format_i18n($stats['exercises_active']) . ' actifs' : 'table non disponible'),
                admin_url('admin.php?page=ouinpo-suite-contents')
            );

            self::metricCard(
                'Classes & Ã©lÃ¨ves',
                ($stats['groups_total'] !== null ? number_format_i18n($stats['groups_total']) : 'â€”'),
                ($stats['members_total'] !== null ? number_format_i18n($stats['members_total']) . ' affectations' : 'groupes ou affectations indisponibles'),
                admin_url('admin.php?page=ouinpo-suite-classes')
            );

            if (ModuleSettings::isEnabled('submissions')) {
                self::metricCard(
                    'DÃ©pÃ´ts Ã©lÃ¨ves',
                    number_format_i18n((int) $stats['submissions_total']),
                    number_format_i18n((int) $stats['submissions_7d']) . ' sur 7 jours',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );
            }

            if (self::hasAiOrPathModule()) {
                self::metricCard(
                    'IA & parcours',
                    ($stats['suggestions_total'] !== null ? number_format_i18n($stats['suggestions_total']) : 'â€”'),
                    'suggestions' . ($stats['paths_active'] !== null ? ' Â· ' . number_format_i18n($stats['paths_active']) . ' parcours actifs' : ''),
                    admin_url('admin.php?page=ouinpo-suite-ai')
                );
            }
            ?>
        </div>

        <h2 class="ouinpo-suite-section">Actions rapides</h2>
        <div class="ouinpo-suite-grid">
            <?php
            self::quickAction(
                'CrÃ©er / gÃ©rer les contenus',
                'AccÃ¨s au catalogue des exercices et sujets pratiques.',
                admin_url('admin.php?page=ouinpo-suite-contents')
            );

            if (ModuleSettings::isEnabled('flashcards')) {
                self::quickAction(
                    'PrÃ©parer les rÃ©visions',
                    'GÃ©rer les flashcards et paquets de cartes.',
                    admin_url('admin.php?page=ouinpo-suite-revisions')
                );
            }

            self::quickAction(
                'Importer des exercices',
                'Ajouter rapidement de nouveaux exercices.',
                admin_url('admin.php?page=ouinpo-suite-contents&tab=import')
            );

            self::quickAction(
                'Devoirs surveillÃ©s',
                'GÃ©rer les DS et Ã©valuations.',
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
                    'DÃ©pÃ´ts Ã©lÃ¨ves',
                    'Voir les travaux rÃ©cents des Ã©lÃ¨ves.',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );
            }

            if (current_user_can('manage_options') && self::hasAiOrPathModule()) {
                self::quickAction(
                    'IA & parcours',
                    'Configurer les assistants, les parcours et lâ€™indexation.',
                    admin_url('admin.php?page=ouinpo-suite-ai')
                );
            }
            ?>
        </div>

        <?php if (ModuleSettings::isEnabled('submissions')): ?>
            <h2 class="ouinpo-suite-section">ActivitÃ© rÃ©cente</h2>
            <div class="ouinpo-suite-grid-wide">
                <?php
                self::renderRecentPostsPanel(
                    'Derniers dÃ©pÃ´ts Ã©lÃ¨ves',
                    'ouinpo_submission',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );

                self::renderRecentPostsPanel(
                    'DerniÃ¨res ressources prof',
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

        self::pageIntro('Contenus', 'Exercices, sujets pratiques, imports et paramÃ¨tres des contenus pÃ©dagogiques.');
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
                    ($stats['exercises_total'] !== null ? number_format_i18n($stats['exercises_total']) : 'â€”'),
                    ($stats['exercises_active'] !== null ? number_format_i18n($stats['exercises_active']) . ' actifs' : 'table non disponible'),
                    admin_url('admin.php?page=ouinpo-exercices')
                );

                self::quickAction(
                    'GÃ©rer les exercices',
                    'CrÃ©er, modifier et organiser les exercices du catalogue.',
                    admin_url('admin.php?page=ouinpo-exercices')
                );

                self::quickAction(
                    'Exercices type bac',
                    'Retrouver les exercices orientÃ©s bac et leurs mÃ©tadonnÃ©es.',
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
                    'GÃ©rer les sujets pratiques et leurs appels.',
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
                        'Configurer les rÃ©glages du module Exercices.',
                        admin_url('admin.php?page=ouinpo-exercises-settings')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Options</h3>
                        <p>Ces rÃ©glages sont rÃ©servÃ©s aux administrateurs.</p>
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

        self::pageIntro('RÃ©visions', 'Flashcards, paquets de cartes et mÃ©morisation active.');
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
                    ($decksCount !== null ? number_format_i18n($decksCount) : 'â€”'),
                    $decksCount !== null ? 'paquets enregistrÃ©s' : 'table non disponible',
                    current_user_can('manage_options') ? admin_url('admin.php?page=ouinpo-flashcards&tab=decks') : null
                );

                self::metricCard(
                    'Cartes',
                    ($cardsCount !== null ? number_format_i18n($cardsCount) : 'â€”'),
                    $cardsCount !== null ? 'cartes enregistrÃ©es' : 'table non disponible',
                    current_user_can('manage_options') ? admin_url('admin.php?page=ouinpo-flashcards&tab=cards') : null
                );

                if (current_user_can('manage_options')) {
                    self::quickAction(
                        'GÃ©rer les flashcards',
                        'CrÃ©er les paquets, modifier les cartes et prÃ©parer les rÃ©visions.',
                        admin_url('admin.php?page=ouinpo-flashcards')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Flashcards</h3>
                        <p>La gestion des flashcards est rÃ©servÃ©e aux administrateurs.</p>
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
                        <p>Import rÃ©servÃ© aux administrateurs.</p>
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

        self::pageIntro('Classes & Ã©lÃ¨ves', 'Organisation des classes, affectations et productions des Ã©lÃ¨ves.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-classes');

        $classTabs = [
            'niveaux'      => 'Niveaux',
            'groupes'      => 'Classes',
            'affectations' => 'Affectations',
        ];

        if (ModuleSettings::isEnabled('submissions')) {
            $classTabs['depots'] = 'DÃ©pÃ´ts';
            $classTabs['ressources'] = 'Ressources';
        }

        if (!isset($classTabs[$tab])) {
            $tab = 'groupes';
        }

        self::subTabs('ouinpo-suite-classes', $classTabs, $tab);

        if ($tab === 'niveaux') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                if (current_user_can('edit_users')) {
                    self::quickAction(
                        'Gerer les niveaux',
                        'Creer, modifier ou supprimer les niveaux scolaires utilises par les classes et exercices.',
                        admin_url('admin.php?page=ouinpo-levels')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Niveaux</h3>
                        <p>La gestion des niveaux est reservee aux profils autorises.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } elseif ($tab === 'groupes') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::metricCard(
                    'Classes',
                    ($stats['groups_total'] !== null ? number_format_i18n($stats['groups_total']) : 'â€”'),
                    ($stats['members_total'] !== null ? number_format_i18n($stats['members_total']) . ' affectations' : 'indisponible'),
                    current_user_can('edit_users') ? admin_url('admin.php?page=ouinpo-groups') : null
                );

                if (current_user_can('edit_users')) {
                    self::quickAction(
                        'GÃ©rer les classes',
                        'CrÃ©er, modifier et organiser les groupes.',
                        admin_url('admin.php?page=ouinpo-groups')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Groupes</h3>
                        <p>La gestion des groupes est rÃ©servÃ©e aux profils autorisÃ©s.</p>
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
                    ($stats['members_total'] !== null ? number_format_i18n($stats['members_total']) : 'â€”'),
                    'Ã©lÃ¨ves liÃ©s Ã  des classes',
                    current_user_can('edit_users') ? admin_url('admin.php?page=ouinpo-assignments') : null
                );

                if (current_user_can('edit_users')) {
                    self::quickAction(
                        'GÃ©rer les affectations',
                        'Associer les Ã©lÃ¨ves aux classes.',
                        admin_url('admin.php?page=ouinpo-assignments')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Affectations</h3>
                        <p>La gestion des affectations est rÃ©servÃ©e aux profils autorisÃ©s.</p>
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
                    'DÃ©pÃ´ts Ã©lÃ¨ves',
                    number_format_i18n((int) $stats['submissions_total']),
                    number_format_i18n((int) $stats['submissions_7d']) . ' sur 7 jours',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );

                self::quickAction(
                    'Voir tous les dÃ©pÃ´ts',
                    'AccÃ©der Ã  la liste complÃ¨te des travaux dÃ©posÃ©s.',
                    admin_url('edit.php?post_type=ouinpo_submission')
                );

                self::renderRecentPostsPanel(
                    'Derniers dÃ©pÃ´ts Ã©lÃ¨ves',
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
                    'ressources enregistrÃ©es',
                    admin_url('edit.php?post_type=ouinpo_resource')
                );

                self::quickAction(
                    'Voir les ressources',
                    'AccÃ©der Ã  la liste complÃ¨te des ressources pÃ©dagogiques.',
                    admin_url('edit.php?post_type=ouinpo_resource')
                );

                self::renderRecentPostsPanel(
                    'DerniÃ¨res ressources prof',
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

    private static function boSchoolLevels(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_school_levels';
        $levels = [];
        $hasTable = self::tableExists($table);

        if ($hasTable) {
            $rows = $wpdb->get_results(
                "SELECT id, slug, label FROM {$table} ORDER BY sort_order ASC, id ASC",
                ARRAY_A
            ) ?: [];

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $label = trim((string) ($row['label'] ?? ''));

                if ($id <= 0 || $label === '') {
                    continue;
                }

                $levels[$id] = [
                    'id'    => $id,
                    'slug'  => sanitize_key((string) ($row['slug'] ?? '')),
                    'label' => $label,
                ];
            }
        }
        return $levels;
    }

    private static function boLevelOptions(): array
    {
        $options = ['transversal' => 'Transversal'];

        foreach (self::boSchoolLevels() as $level) {
            $options[(string) $level['id']] = (string) $level['label'];
        }

        return $options;
    }

    private static function normalizeBoTrack(string $track): string
    {
        $track = strtoupper(trim($track));
        return array_key_exists($track, self::boTrackOptions()) ? $track : 'NSI';
    }

    private static function normalizeBoLevel(string $level): string
    {
        return (string) self::boLevelContext($level)['level'];
    }

    private static function normalizeBoDomainSlug(string $slug, string $domain = ''): string
    {
        $source = trim($slug) !== '' ? $slug : $domain;
        $source = sanitize_title(remove_accents((string) $source));
        return $source !== '' ? $source : 'domaine';
    }

    private static function boLevelContext($raw): array
    {
        $levels = self::boSchoolLevels();
        $rawString = trim((string) $raw);
        $normalized = strtolower(remove_accents($rawString));
        $normalized = str_replace('Ã¨', 'e', $normalized);
        $normalized = str_replace('ÃƒÂ¨', 'e', $normalized);

        if ($normalized === 'transversal' || $rawString === '0') {
            return [
                'key'            => 'transversal',
                'level_id'       => 0,
                'level_slug'     => 'transversal',
                'level'          => 'Transversal',
                'is_transversal' => true,
            ];
        }

        if (!$levels) {
            return [
                'key'            => 'transversal',
                'level_id'       => 0,
                'level_slug'     => 'transversal',
                'level'          => 'Transversal',
                'is_transversal' => true,
            ];
        }

        if (ctype_digit($rawString) && isset($levels[(int) $rawString])) {
            $level = $levels[(int) $rawString];

            return [
                'key'            => (string) $level['id'],
                'level_id'       => (int) $level['id'],
                'level_slug'     => (string) $level['slug'],
                'level'          => (string) $level['label'],
                'is_transversal' => false,
            ];
        }

        foreach ($levels as $level) {
            $levelLabel = strtolower(remove_accents((string) $level['label']));
            $levelSlug = sanitize_key((string) $level['slug']);

            if ($normalized === $levelLabel || sanitize_key($rawString) === $levelSlug) {
                return [
                    'key'            => (string) $level['id'],
                    'level_id'       => (int) $level['id'],
                    'level_slug'     => (string) $level['slug'],
                    'level'          => (string) $level['label'],
                    'is_transversal' => false,
                ];
            }
        }

        if ($rawString === '' || $normalized === 'premiere') {
            foreach ($levels as $level) {
                $levelLabel = strtolower(remove_accents((string) $level['label']));

                if ($level['slug'] === 'premiere' || $levelLabel === 'premiere') {
                    return [
                        'key'            => (string) $level['id'],
                        'level_id'       => (int) $level['id'],
                        'level_slug'     => (string) $level['slug'],
                        'level'          => (string) $level['label'],
                        'is_transversal' => false,
                    ];
                }
            }
        }

        $first = reset($levels);

        return [
            'key'            => (string) $first['id'],
            'level_id'       => (int) $first['id'],
            'level_slug'     => (string) $first['slug'],
            'level'          => (string) $first['label'],
            'is_transversal' => false,
        ];
    }

    private static function boDomainKey(string $domainSlug, string $track, $level): string
    {
        $levelContext = self::boLevelContext($level);

        return self::normalizeBoDomainSlug($domainSlug) . '|' . self::normalizeBoTrack($track) . '|' . $levelContext['key'];
    }

    private static function parseBoDomainKey(string $domainKey): array
    {
        $parts = explode('|', $domainKey);
        $levelContext = self::boLevelContext($parts[2] ?? '');

        return [
            'domain_slug' => isset($parts[0]) ? self::normalizeBoDomainSlug((string) $parts[0]) : '',
            'track'       => isset($parts[1]) ? self::normalizeBoTrack((string) $parts[1]) : 'NSI',
            'level_id'    => $levelContext['level_id'],
            'level_key'   => $levelContext['key'],
            'level_slug'  => $levelContext['level_slug'],
            'level'       => $levelContext['level'],
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
                : 'PremiÃ¨re';

            $levelContext = self::boLevelContext($item['level_id'] ?? ($item['level_key'] ?? $level));
            $active = isset($item['active']) ? (int) $item['active'] : 1;

            if ($domain === '' || $domainSlug === '') {
                continue;
            }

            $key = self::boDomainKey($domainSlug, $track, $levelContext['key']);

            $domains[$key] = [
                'domain'       => $domain,
                'domain_slug'  => $domainSlug,
                'track'        => $track,
                'level'        => $levelContext['level'],
                'level_id'     => $levelContext['level_id'],
                'level_key'    => $levelContext['key'],
                'level_slug'   => $levelContext['level_slug'],
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
        $tCompLevel = $wpdb->prefix . 'ouin_exo_competency_school_level';
        $tLevels = $wpdb->prefix . 'ouin_exo_school_levels';

        if (self::tableExists($tComp) && self::tableExists($tCompLevel) && self::tableExists($tLevels)) {
            $rows = $wpdb->get_results(
                "SELECT
                    c.domain,
                    c.domain_slug,
                    c.track,
                    CASE WHEN c.level = 'Transversal' THEN 'transversal' ELSE CAST(sl.id AS CHAR) END AS level_key,
                    CASE WHEN c.level = 'Transversal' THEN 0 ELSE sl.id END AS level_id,
                    CASE WHEN c.level = 'Transversal' THEN 'Transversal' ELSE sl.label END AS level,
                    CASE WHEN c.level = 'Transversal' THEN 'transversal' ELSE sl.slug END AS level_slug,
                    COUNT(DISTINCT c.id) AS total,
                    COUNT(DISTINCT CASE WHEN c.active = 1 THEN c.id END) AS active_total
                FROM {$tComp} c
                LEFT JOIN {$tCompLevel} csl ON csl.competency_id = c.id
                LEFT JOIN {$tLevels} sl ON sl.id = csl.school_level_id
                WHERE c.domain IS NOT NULL
                  AND c.domain <> ''
                  AND c.domain_slug IS NOT NULL
                  AND c.domain_slug <> ''
                  AND (c.level = 'Transversal' OR sl.id IS NOT NULL)
                GROUP BY c.domain, c.domain_slug, c.track, level_key, level_id, level, level_slug
                ORDER BY
                    FIELD(c.track, 'SNT', 'NSI'),
                    level_key,
                    c.domain ASC"
            );

            foreach ($rows as $row) {
                $domain = sanitize_text_field((string) $row->domain);
                $domainSlug = self::normalizeBoDomainSlug((string) $row->domain_slug, $domain);
                $track = self::normalizeBoTrack((string) $row->track);
                $levelContext = self::boLevelContext((string) $row->level_key);

                if ($domain === '' || $domainSlug === '') {
                    continue;
                }

                $key = self::boDomainKey($domainSlug, $track, $levelContext['key']);

                if (!isset($domains[$key])) {
                    $domains[$key] = [
                        'domain'       => $domain,
                        'domain_slug'  => $domainSlug,
                        'track'        => $track,
                        'level'        => (string) $row->level,
                        'level_id'     => (int) $row->level_id,
                        'level_key'    => $levelContext['key'],
                        'level_slug'   => (string) $row->level_slug,
                        'active'       => ((int) $row->active_total > 0) ? 1 : 0,
                        'total'        => (int) $row->total,
                        'active_total' => (int) $row->active_total,
                    ];
                } else {
                    $domains[$key]['domain'] = $domain;
                    $domains[$key]['domain_slug'] = $domainSlug;
                    $domains[$key]['track'] = $track;
                    $domains[$key]['level'] = (string) $row->level;
                    $domains[$key]['level_id'] = (int) $row->level_id;
                    $domains[$key]['level_key'] = $levelContext['key'];
                    $domains[$key]['level_slug'] = (string) $row->level_slug;
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
                ($a['track'] ?? '') . ' ' . ($a['level_key'] ?? '') . ' ' . ($a['domain'] ?? ''),
                ($b['track'] ?? '') . ' ' . ($b['level_key'] ?? '') . ' ' . ($b['domain'] ?? '')
            );
        });

        return $domains;
    }

    private static function referentielLevelIdsForKey($levelKey): array
    {
        $levelContext = self::boLevelContext($levelKey);

        if (!empty($levelContext['is_transversal'])) {
            return array_map('intval', array_keys(self::boSchoolLevels()));
        }

        return $levelContext['level_id'] > 0 ? [(int) $levelContext['level_id']] : [];
    }

    private static function syncReferentielCompetencyLevels(int $competencyId, $levelKey): void
    {
        if ($competencyId <= 0) {
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_competency_school_level';
        if (!self::tableExists($table)) {
            return;
        }

        $wpdb->delete($table, ['competency_id' => $competencyId], ['%d']);

        foreach (self::referentielLevelIdsForKey($levelKey) as $levelId) {
            $wpdb->insert(
                $table,
                [
                    'competency_id'   => $competencyId,
                    'school_level_id' => $levelId,
                ],
                ['%d', '%d']
            );
        }
    }

    private static function referentielLevelKeyForCompetency(int $competencyId, string $legacyLevel = ''): string
    {
        if ($legacyLevel === 'Transversal') {
            return 'transversal';
        }

        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_competency_school_level';
        if (self::tableExists($table)) {
            $levelId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT school_level_id
                   FROM {$table}
                  WHERE competency_id = %d
                  ORDER BY school_level_id ASC
                  LIMIT 1",
                $competencyId
            ));

            if ($levelId > 0) {
                return (string) $levelId;
            }
        }

        return (string) self::boLevelContext($legacyLevel)['key'];
    }

    private static function referentielCompetencyIdsForDomain(string $tComp, array $domain): array
    {
        global $wpdb;

        $domainSlug = self::normalizeBoDomainSlug((string) ($domain['domain_slug'] ?? ''));
        $track = self::normalizeBoTrack((string) ($domain['track'] ?? 'NSI'));
        $levelContext = self::boLevelContext($domain['level_key'] ?? ($domain['level_id'] ?? ($domain['level'] ?? '')));

        if ($domainSlug === '' || !self::tableExists($tComp)) {
            return [];
        }

        if (!empty($levelContext['is_transversal'])) {
            return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
                "SELECT id
                   FROM {$tComp}
                  WHERE domain_slug = %s
                    AND track = %s
                    AND level = 'Transversal'",
                $domainSlug,
                $track
            )));
        }

        $table = $wpdb->prefix . 'ouin_exo_competency_school_level';
        if (self::tableExists($table)) {
            return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT c.id
                   FROM {$tComp} c
                   JOIN {$table} csl ON csl.competency_id = c.id
                  WHERE c.domain_slug = %s
                    AND c.track = %s
                    AND csl.school_level_id = %d",
                $domainSlug,
                $track,
                (int) $levelContext['level_id']
            )));
        }

        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT id
               FROM {$tComp}
              WHERE domain_slug = %s
                AND track = %s
                AND level = %s",
            $domainSlug,
            $track,
            (string) $levelContext['level']
        )));
    }

    public static function renderReferentielHub(): void
    {
        $tab = self::currentTab('competences');

        self::pageIntro('RÃ©fÃ©rentiel BO', 'Domaines, compÃ©tences officielles et associations pÃ©dagogiques.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-referentiel');

        self::subTabs('ouinpo-suite-referentiel', [
            'competences' => 'CompÃ©tences BO',
            'domaines'   => 'Domaines BO',
            'courses'    => 'Cours â†” compÃ©tences',
            'years'      => 'AnnÃ©es scolaires',
        ], $tab);

        if ($tab === 'domaines') {
            self::renderReferentielDomainsTable();

        } elseif ($tab === 'courses') {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                self::quickAction(
                    'Cours â†” compÃ©tences BO',
                    'Associer les cours WordPress aux compÃ©tences du BO.',
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
                    'AnnÃ©es scolaires',
                    'CrÃ©er les futures annÃ©es et choisir lâ€™annÃ©e active.',
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
                <h2 class="ouinpo-suite-card-title">CompÃ©tences BO</h2>
                <p>AccÃ¨s rÃ©servÃ© aux profils autorisÃ©s.</p>
            </div>
            <?php
            return;
        }

        global $wpdb;

        $tComp = $wpdb->prefix . 'ouin_exo_competencies';
        $tPost = $wpdb->prefix . 'ouin_exo_post_competency';
        $tExo  = $wpdb->prefix . 'ouin_exo_exercise_competency';
        $tCompLevel = $wpdb->prefix . 'ouin_exo_competency_school_level';
        $tLevels = $wpdb->prefix . 'ouin_exo_school_levels';

        self::handleReferentielBoActions($tComp);
        settings_errors('ouinpo_ref_bo');

        if (!self::tableExists($tComp)) {
            ?>
            <div class="card ouinpo-suite-card-bounded">
                <h2 class="ouinpo-suite-card-title">CompÃ©tences BO</h2>
                <p>La table des compÃ©tences nâ€™existe pas encore.</p>
            </div>
            <?php
            return;
        }

        $hasDynamicLevelTables = self::tableExists($tCompLevel) && self::tableExists($tLevels);

        $trackRaw = isset($_GET['ref_track']) ? sanitize_text_field((string) $_GET['ref_track']) : '';
        $levelRaw = isset($_GET['ref_level']) ? sanitize_text_field((string) $_GET['ref_level']) : '';

        $track = $trackRaw !== '' ? self::normalizeBoTrack($trackRaw) : '';
        $level = $levelRaw !== '' ? (string) self::boLevelContext($levelRaw)['key'] : '';
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
            $levelContext = self::boLevelContext($level);

            if (!empty($levelContext['is_transversal'])) {
                $where[] = "c.level = 'Transversal'";
            } elseif ($hasDynamicLevelTables) {
                $where[] = "EXISTS (
                    SELECT 1
                      FROM {$tCompLevel} csl_filter
                     WHERE csl_filter.competency_id = c.id
                       AND csl_filter.school_level_id = %d
                )";
                $args[] = (int) $levelContext['level_id'];
            } else {
                $where[] = 'c.level = %s';
                $args[] = (string) $levelContext['level'];
            }
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(c.domain LIKE %s OR c.competency LIKE %s OR c.slug LIKE %s)';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $levelLabelsSql = $hasDynamicLevelTables
            ? "CASE
                    WHEN c.level = 'Transversal' THEN 'Transversal'
                    ELSE (
                        SELECT GROUP_CONCAT(DISTINCT sl2.label ORDER BY sl2.id SEPARATOR ', ')
                        FROM {$tCompLevel} csl2
                        JOIN {$tLevels} sl2 ON sl2.id = csl2.school_level_id
                        WHERE csl2.competency_id = c.id
                    )
                END AS level_labels"
            : 'c.level AS level_labels';

        $sql = "
            SELECT
                c.id,
                c.domain,
                c.domain_slug,
                c.track,
                c.level,
                {$levelLabelsSql},
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
                FIELD(c.level, 'Seconde', 'PremiÃ¨re', 'Terminale', 'Transversal'),
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
                self::referentielLevelKeyForCompetency((int) $editRow->id, (string) $editRow->level)
            );
        }
        ?>

        <?php if (current_user_can('manage_options')): ?>
            <div id="ouinpo-bo-form" class="card ouinpo-suite-form-card">
                <h2 class="ouinpo-suite-card-title">
                    <?php echo $editRow ? 'Modifier une compÃ©tence BO' : 'Ajouter une compÃ©tence BO'; ?>
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
                                            Aucun domaine BO nâ€™est disponible. CrÃ©e dâ€™abord un domaine dans lâ€™onglet â€œDomaines BOâ€.
                                        </p>
                                    <?php else: ?>
                                        <select id="bo_domain_choice" required>
                                            <option value="">â€” Choisir un domaine â€”</option>

                                            <?php foreach ($domainOptions as $key => $domainItem): ?>
                                                <option
                                                    value="<?php echo esc_attr($key); ?>"
                                                    data-domain="<?php echo esc_attr($domainItem['domain']); ?>"
                                                    data-domain-slug="<?php echo esc_attr($domainItem['domain_slug']); ?>"
                                                    data-track="<?php echo esc_attr($domainItem['track']); ?>"
                                                    data-level="<?php echo esc_attr($domainItem['level']); ?>"
                                                    data-level-id="<?php echo esc_attr($domainItem['level_key'] ?? $domainItem['level_id'] ?? ''); ?>"
                                                    <?php selected($selectedDomainKey, $key); ?>
                                                >
                                                    <?php echo esc_html($domainItem['domain'] . ' â€” ' . $domainItem['track'] . ' / ' . $domainItem['level']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <p id="bo_domain_summary" class="description"></p>
                                    <?php endif; ?>

                                    <input id="bo_domain" type="hidden" name="domain" value="<?php echo esc_attr($editRow->domain ?? ''); ?>">
                                    <input id="bo_domain_slug" type="hidden" name="domain_slug" value="<?php echo esc_attr($editRow->domain_slug ?? ''); ?>">
                                    <input id="bo_track" type="hidden" name="track" value="<?php echo esc_attr($editRow->track ?? 'NSI'); ?>">
                                    <input id="bo_level_id" type="hidden" name="level_id" value="<?php echo esc_attr($editRow ? self::referentielLevelKeyForCompetency((int) $editRow->id, (string) $editRow->level) : ''); ?>">
                                    <input id="bo_level" type="hidden" name="level" value="<?php echo esc_attr($editRow->level ?? 'PremiÃ¨re'); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th><label for="bo_competency">CompÃ©tence</label></th>
                                <td>
                                    <textarea id="bo_competency" name="competency" rows="4" class="large-text" required><?php
                                        echo esc_textarea($editRow->competency ?? '');
                                    ?></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th><label for="bo_slug">Slug compÃ©tence</label></th>
                                <td>
                                    <input id="bo_slug" type="text" name="slug" class="regular-text"
                                        value="<?php echo esc_attr($editRow->slug ?? ''); ?>"
                                        placeholder="ex : algorithmique-parcours-tableau">
                                    <p class="description">
                                        Laisse vide pour gÃ©nÃ©rer automatiquement le slug Ã  partir du domaine et de la compÃ©tence.
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th>Active</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="active" value="1" <?php checked((int)($editRow->active ?? 1), 1); ?>>
                                        CompÃ©tence active
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button($editRow ? 'Enregistrer les modifications' : 'Ajouter la compÃ©tence'); ?>

                    <?php if ($editRow): ?>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-suite-referentiel&tab=competences')); ?>">
                            Annuler
                        </a>
                    <?php endif; ?>
                </form>

            </div>
        <?php endif; ?>

        <div class="card ouinpo-suite-card-bounded">
            <h2 class="ouinpo-suite-card-title">CompÃ©tences BO</h2>

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

                <input type="search" name="ref_s" value="<?php echo esc_attr($search); ?>" placeholder="Rechercher domaine / compÃ©tence / slug">

                <?php submit_button('Filtrer', 'secondary', '', false); ?>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th class="ouinpo-suite-col-22">Domaine</th>
                        <th class="ouinpo-suite-col-10">Piste</th>
                        <th class="ouinpo-suite-col-10">Niveau</th>
                        <th>CompÃ©tence</th>
                        <th class="ouinpo-suite-col-8">Cours</th>
                        <th class="ouinpo-suite-col-8">Exos</th>
                        <th class="ouinpo-suite-col-8">Actif</th>
                        <th class="ouinpo-suite-col-18">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="8">Aucune compÃ©tence trouvÃ©e.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($row->domain); ?></strong><br>
                                    <span class="ouinpo-suite-muted"><?php echo esc_html((string) $row->slug); ?></span>
                                </td>
                                <td><?php echo esc_html($row->track); ?></td>
                                <td><?php echo esc_html($row->level_labels ?: $row->level); ?></td>
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
                                            <?php submit_button(((int) $row->active === 1) ? 'DÃ©sactiver' : 'RÃ©activer', 'secondary small', '', false); ?>
                                        </form>

                                        <form method="post" class="ouinpo-suite-inline-form" data-confirm="Supprimer cette compÃ©tence ? Si elle est liÃ©e Ã  des exercices, cours ou suivis, elle sera seulement dÃ©sactivÃ©e.">
                                            <?php wp_nonce_field('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce'); ?>
                                            <input type="hidden" name="ouinpo_ref_action" value="delete_competency">
                                            <input type="hidden" name="competency_id" value="<?php echo (int) $row->id; ?>">
                                            <?php submit_button('Supprimer', 'delete small', '', false); ?>
                                        </form>
                                    <?php else: ?>
                                        â€”
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
                <p>AccÃ¨s rÃ©servÃ© aux profils autorisÃ©s.</p>
            </div>
            <?php
            return;
        }

        global $wpdb;

        $tComp = $wpdb->prefix . 'ouin_exo_competencies';

        self::handleReferentielBoActions($tComp);
        settings_errors('ouinpo_ref_bo');

        $domains = self::referentielBoDomains($tComp, false);

        $editDomainKey = isset($_GET['edit_domain_key'])
            ? sanitize_text_field((string) wp_unslash($_GET['edit_domain_key']))
            : '';

        $editDomain = null;

        if ($editDomainKey !== '' && current_user_can('manage_options') && isset($domains[$editDomainKey])) {
            $editDomain = $domains[$editDomainKey];
        }

        $defaultDomainLevelKey = (string) self::boLevelContext('PremiÃ¨re')['key'];
        $editDomainLevelKey = $editDomain
            ? (string) self::boLevelContext($editDomain['level_key'] ?? ($editDomain['level_id'] ?? ($editDomain['level'] ?? $defaultDomainLevelKey)))['key']
            : $defaultDomainLevelKey;
        ?>

        <?php if (current_user_can('manage_options')): ?>
            <div id="ouinpo-bo-domain-form" class="card ouinpo-suite-form-card">
                <h2 class="ouinpo-suite-card-title">
                    <?php echo $editDomain ? 'Modifier un domaine BO' : 'CrÃ©er un domaine BO'; ?>
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
                                    <p class="description">Il est rempli automatiquement Ã  partir du nom, mais peut Ãªtre corrigÃ© avant enregistrement.</p>
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
                                    <select id="bo_domain_level" name="level_id">
                                        <?php foreach (self::boLevelOptions() as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($editDomainLevelKey, $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button($editDomain ? 'Enregistrer le domaine' : 'CrÃ©er le domaine'); ?>

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
                        <th class="ouinpo-suite-col-10">CompÃ©tences</th>
                        <th class="ouinpo-suite-col-10">Actives</th>
                        <th class="ouinpo-suite-col-10">Ã‰tat</th>
                        <th class="ouinpo-suite-col-24">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$domains): ?>
                        <tr>
                            <td colspan="8">Aucun domaine trouvÃ©.</td>
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
                                <td><?php echo esc_html($row['level']); ?></td>
                                <td><?php echo number_format_i18n($total); ?></td>
                                <td><?php echo number_format_i18n($activeTotal); ?></td>
                                <td><?php echo $isActive ? 'Actif' : 'MasquÃ©'; ?></td>
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
                                            <?php submit_button($isActive ? 'Masquer' : 'RÃ©activer', 'secondary small', '', false); ?>
                                        </form>

                                        <form method="post" class="ouinpo-suite-inline-form" data-confirm="Supprimer ce domaine du registre ? Les compÃ©tences dÃ©jÃ  liÃ©es ne seront pas supprimÃ©es.">
                                            <?php wp_nonce_field('ouinpo_ref_bo_action', 'ouinpo_ref_bo_nonce'); ?>
                                            <input type="hidden" name="ouinpo_ref_action" value="delete_domain">
                                            <input type="hidden" name="domain_key" value="<?php echo esc_attr($domainKey); ?>">
                                            <?php submit_button('Supprimer', 'delete small', '', false); ?>
                                        </form>
                                    <?php else: ?>
                                        â€”
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
                add_settings_error('ouinpo_ref_bo', 'bo_missing_table', 'La table des compÃ©tences nâ€™existe pas encore.', 'error');
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
                : 'PremiÃ¨re';

            $levelContext = self::boLevelContext($_POST['level_id'] ?? ($_POST['level'] ?? $level));
            $level = (string) $levelContext['level'];

            $competency = isset($_POST['competency'])
                ? wp_kses_post((string) wp_unslash($_POST['competency']))
                : '';

            $slug = isset($_POST['slug'])
                ? sanitize_title((string) wp_unslash($_POST['slug']))
                : '';

            $active = isset($_POST['active']) ? 1 : 0;

            if ($domain === '' || $domainSlug === '' || trim(wp_strip_all_tags($competency)) === '') {
                add_settings_error('ouinpo_ref_bo', 'bo_missing', 'Domaine et compÃ©tence sont obligatoires.', 'error');
                return;
            }

            $domainKey = self::boDomainKey($domainSlug, $track, $levelContext['key']);
            $domains = self::referentielBoDomains($tComp, true);

            if (!isset($domains[$domainKey])) {
                add_settings_error('ouinpo_ref_bo', 'bo_domain_unknown', 'Choisis un domaine BO existant avant de crÃ©er une compÃ©tence.', 'error');
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
                add_settings_error('ouinpo_ref_bo', 'bo_duplicate_slug', 'Ce slug de compÃ©tence existe dÃ©jÃ .', 'error');
                return;
            }

            $label = trim(wp_strip_all_tags($domain . ' â€” ' . $competency));

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
                $updated = $wpdb->update($tComp, $data, ['id' => $id], $formats, ['%d']);
                if ($updated === false) {
                    add_settings_error('ouinpo_ref_bo', 'bo_update_failed', 'Impossible de modifier cette competence BO.', 'error');
                    return;
                }
                self::syncReferentielCompetencyLevels($id, $levelContext['key']);
                add_settings_error('ouinpo_ref_bo', 'bo_updated', 'CompÃ©tence BO modifiÃ©e.', 'updated');
            } else {
                $inserted = $wpdb->insert($tComp, $data, $formats);
                if ($inserted === false || (int) $wpdb->insert_id <= 0) {
                    add_settings_error('ouinpo_ref_bo', 'bo_insert_failed', 'Impossible d ajouter cette competence BO.', 'error');
                    return;
                }
                self::syncReferentielCompetencyLevels((int) $wpdb->insert_id, $levelContext['key']);
                add_settings_error('ouinpo_ref_bo', 'bo_inserted', 'CompÃ©tence BO ajoutÃ©e.', 'updated');
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
                    $active ? 'CompÃ©tence rÃ©activÃ©e.' : 'CompÃ©tence dÃ©sactivÃ©e.',
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
                    'Cette compÃ©tence est liÃ©e Ã  des contenus ou suivis. Elle a Ã©tÃ© dÃ©sactivÃ©e au lieu dâ€™Ãªtre supprimÃ©e.',
                    'updated'
                );
                return;
            }

            $wpdb->delete($tComp, ['id' => $id], ['%d']);
            add_settings_error('ouinpo_ref_bo', 'bo_deleted', 'CompÃ©tence supprimÃ©e dÃ©finitivement.', 'updated');
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

            $levelContext = self::boLevelContext($_POST['level_id'] ?? ($_POST['level'] ?? 'PremiÃ¨re'));
            $level = (string) $levelContext['level'];

            if ($domain === '' || $domainSlug === '') {
                add_settings_error('ouinpo_ref_bo', 'domain_missing', 'Nom de domaine obligatoire.', 'error');
                return;
            }

            $domains = self::storedBoDomains();
            $newKey = self::boDomainKey($domainSlug, $track, $levelContext['key']);
            $allDomains = self::referentielBoDomains($tComp, false);

            if ($oldKey === '' && isset($allDomains[$newKey])) {
                add_settings_error('ouinpo_ref_bo', 'domain_conflict', 'Un domaine avec ce slug, cette piste et ce niveau existe dÃ©jÃ .', 'error');
                return;
            }

            if ($oldKey !== '' && $oldKey !== $newKey) {
                if (isset($allDomains[$newKey])) {
                    add_settings_error('ouinpo_ref_bo', 'domain_conflict', 'Un domaine avec ce slug, cette piste et ce niveau existe dÃ©jÃ .', 'error');
                    return;
                }

                if (self::tableExists($tComp)) {
                    $old = self::parseBoDomainKey($oldKey);

                    foreach (self::referentielCompetencyIdsForDomain($tComp, $old) as $competencyId) {
                        $wpdb->update(
                            $tComp,
                            [
                                'domain'      => $domain,
                                'domain_slug' => $domainSlug,
                                'track'       => $track,
                                'level'       => $level,
                            ],
                            ['id' => $competencyId],
                            ['%s', '%s', '%s', '%s'],
                            ['%d']
                        );

                        self::syncReferentielCompetencyLevels((int) $competencyId, $levelContext['key']);
                    }
                }

                if (isset($domains[$oldKey])) {
                    unset($domains[$oldKey]);
                }
            }

            if ($oldKey !== '' && $oldKey === $newKey && self::tableExists($tComp)) {
                $old = self::parseBoDomainKey($oldKey);

                foreach (self::referentielCompetencyIdsForDomain($tComp, $old) as $competencyId) {
                    $wpdb->update(
                        $tComp,
                        [
                            'domain'      => $domain,
                            'domain_slug' => $domainSlug,
                            'track'       => $track,
                            'level'       => $level,
                        ],
                        ['id' => $competencyId],
                        ['%s', '%s', '%s', '%s'],
                        ['%d']
                    );

                    self::syncReferentielCompetencyLevels((int) $competencyId, $levelContext['key']);
                }
            }

            $domains[$newKey] = [
                'domain'       => $domain,
                'domain_slug'  => $domainSlug,
                'track'        => $track,
                'level'        => $levelContext['level'],
                'level_id'     => $levelContext['level_id'],
                'level_key'    => $levelContext['key'],
                'level_slug'   => $levelContext['level_slug'],
                'active'       => 1,
                'total'        => 0,
                'active_total' => 0,
            ];

            self::saveStoredBoDomains($domains);

            add_settings_error('ouinpo_ref_bo', 'domain_saved', 'Domaine BO enregistrÃ©.', 'updated');
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
                    'level_id'     => (int) ($allDomains[$domainKey]['level_id'] ?? 0),
                    'level_key'    => (string) ($allDomains[$domainKey]['level_key'] ?? ''),
                    'level_slug'   => (string) ($allDomains[$domainKey]['level_slug'] ?? ''),
                    'active'       => $active,
                    'total'        => 0,
                    'active_total' => 0,
                ];

                self::saveStoredBoDomains($domains);

                add_settings_error(
                    'ouinpo_ref_bo',
                    'domain_toggled',
                    $active ? 'Domaine rÃ©activÃ©.' : 'Domaine masquÃ© pour les nouvelles compÃ©tences.',
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
                        'level_id'     => (int) ($allDomains[$domainKey]['level_id'] ?? 0),
                        'level_key'    => (string) ($allDomains[$domainKey]['level_key'] ?? ''),
                        'level_slug'   => (string) ($allDomains[$domainKey]['level_slug'] ?? ''),
                        'active'       => 0,
                        'total'        => 0,
                        'active_total' => 0,
                    ];

                    self::saveStoredBoDomains($domains);
                }
            }

            add_settings_error('ouinpo_ref_bo', 'domain_deleted', 'Domaine BO retirÃ© du registre. Les compÃ©tences existantes ne sont pas supprimÃ©es.', 'updated');
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

        self::pageIntro('Ã‰valuations', 'Devoirs surveillÃ©s et suivi des compÃ©tences des Ã©lÃ¨ves.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-evaluations');

        $tabs = [
            'suivi' => 'Suivi des compÃ©tences',
            'ds'    => 'Devoirs surveillÃ©s',
        ];

        if (ModuleSettings::isEnabled('submissions')) {
            $tabs['depots'] = 'DÃ©pÃ´ts Ã©lÃ¨ves';
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
                    'Suivi des compÃ©tences',
                    'AccÃ©der Ã  lâ€™Ã©cran de suivi par annÃ©e, classe, domaine et Ã©lÃ¨ve.',
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
                    'Devoirs surveillÃ©s',
                    'CrÃ©er et gÃ©rer les DS.',
                    admin_url('admin.php?page=ouinpo-assessments')
                );

                self::quickAction(
                    'Concepteur de devoirs',
                    'Composer un devoir Ã  partir des exercices du catalogue.',
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
                    'DÃ©pÃ´ts Ã©lÃ¨ves',
                    'Voir les travaux rÃ©cents pour croiser Ã©valuation et entraÃ®nement.',
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

        self::pageIntro('IA & parcours', 'Outils dâ€™accompagnement, recommandations, parcours et assistants.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-ai');

        if (empty($aiTabs)) {
            ?>
            <div class="card ouinpo-suite-card-bounded">
                <h2 class="ouinpo-suite-card-title">IA & parcours</h2>
                <p>Aucun module IA ou parcours nâ€™est activÃ©.</p>
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
                    ($stats['suggestions_total'] !== null ? number_format_i18n($stats['suggestions_total']) : 'â€”'),
                    'suggestions enregistrÃ©es',
                    current_user_can('manage_options') ? admin_url('admin.php?page=ouinpo-segfault') : null
                );

                if (current_user_can('manage_options')) {
                    self::quickAction(
                        'Ouvrir SegFault',
                        'AccÃ©der aux outils, sources, indexation et paramÃ¨tres SegFault.',
                        admin_url('admin.php?page=ouinpo-segfault')
                    );

                    self::quickAction(
                        'Suivi Ã©lÃ¨ves SegFault',
                        'Consulter le suivi des Ã©lÃ¨ves liÃ© Ã  SegFault.',
                        admin_url('admin.php?page=ouinpo-segfault-progress')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">SegFault</h3>
                        <p>Les rÃ©glages SegFault sont rÃ©servÃ©s aux administrateurs.</p>
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
                    ($stats['gate_progress'] !== null ? number_format_i18n($stats['gate_progress']) : 'â€”'),
                    'entrÃ©es de progression',
                    current_user_can('list_users') ? admin_url('admin.php?page=ouinpo') : null
                );

                if (current_user_can('list_users')) {
                    self::quickAction(
                        'Ouvrir Gate',
                        'AccÃ©der au suivi global et aux certificats.',
                        admin_url('admin.php?page=ouinpo')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Gate</h3>
                        <p>Lâ€™accÃ¨s Ã  Gate est rÃ©servÃ© aux profils autorisÃ©s.</p>
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
                        Le module Recherche textuelle fournit principalement un shortcode pÃ©dagogique
                        pour visualiser la recherche dans un texte. Il nâ€™a pas encore dâ€™Ã©cran dâ€™administration dÃ©diÃ©.
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
            'Version OuInPo Suite' => defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : 'â€”',
            'Version WordPress'    => get_bloginfo('version'),
            'Version PHP'          => PHP_VERSION,
            'PrÃ©fixe des tables'   => $wpdb->prefix,
            'Jeu de caractÃ¨res BD' => $wpdb->get_charset_collate(),
            'Fuseau horaire'       => wp_timezone_string() ?: 'â€”',
            'Dossier uploads'      => $upload_ok ? 'Accessible en Ã©criture' : 'Ã€ vÃ©rifier',
        ];

        $options = [
            'Version BD suite'       => (string) get_option('ouinpo_suite_version', 'non installÃ©e'),
            'Version BD exercices'   => (string) get_option('ouinpo_exo_db_version', 'non installÃ©e'),
            'Version BD flashcards'  => (string) get_option('ouinpo_flashcards_db_version', 'non installÃ©e'),
            'IA Albert'              => ((int) get_option('ouinpo_sf_albert_enabled', 0) === 1) ? 'ActivÃ©e' : 'DÃ©sactivÃ©e',
            'IA publique Albert'     => ((int) get_option('ouinpo_sf_public_albert_enabled', 0) === 1) ? 'ActivÃ©e' : 'DÃ©sactivÃ©e',
            'ClÃ© Albert'             => trim((string) get_option('ouinpo_sf_albert_api_key', '')) !== '' ? 'PrÃ©sente, valeur masquÃ©e' : 'Absente',
            'ClÃ© OpenAI'             => trim((string) get_option('ouinpo_sf_openai_api_key', '')) !== '' ? 'PrÃ©sente, valeur masquÃ©e' : 'Absente',
            'Index WXR SegFault'     => trim((string) get_option('ouinpo_sf_wxr_path', '')) !== '' ? 'Chemin configurÃ©' : 'Non configurÃ©',
        ];
        ?>
        <div class="notice notice-info ouinpo-suite-notice">
            <p class="ouinpo-suite-no-margin">
                <strong>Diagnostic de diffusion.</strong>
                Cette page sert Ã  vÃ©rifier quâ€™une installation OuInPo est saine et Ã  repÃ©rer ce qui ne doit jamais partir dans une archive partagÃ©e : clÃ©s API, donnÃ©es Ã©lÃ¨ves, logs, rÃ©ponses, chemins locaux.
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
                $p . 'ouin_sf_paths'        => 'Parcours individualisÃ©s',
                $p . 'ouin_sf_path_items'   => 'Ã‰lÃ©ments de parcours',
                $p . 'ouin_sf_path_targets' => 'Affectations de parcours',
                $p . 'ouin_sf_suggestions'  => 'Suggestions IA / exercices',
            ],
            'Exercices' => [
                $p . 'ouin_exo_academic_years'          => 'AnnÃ©es scolaires',
                $p . 'ouin_exo_school_levels'           => 'Niveaux',
                $p . 'ouin_exo_groups'                  => 'Classes / groupes',
                $p . 'ouin_exo_group_members'           => 'Membres des classes',
                $p . 'ouin_exo_exercises'               => 'Exercices',
                $p . 'ouin_exo_exercise_school_level'   => 'Niveaux des exercices',
                $p . 'ouin_exo_difficulties'            => 'DifficultÃ©s',
                $p . 'ouin_exo_hints'                   => 'Indices',
                $p . 'ouin_exo_solutions'               => 'Solutions',
                $p . 'ouin_exo_competencies'            => 'CompÃ©tences BO',
                $p . 'ouin_exo_exercise_competency'     => 'Liens exercices / compÃ©tences',
                $p . 'ouin_exo_post_competency'         => 'Liens cours / compÃ©tences',
                $p . 'ouin_exo_user_status'             => 'Statuts Ã©lÃ¨ves',
                $p . 'ouin_exo_user_reveals'            => 'Indices / solutions rÃ©vÃ©lÃ©s',
                $p . 'ouin_exo_user_competencies'       => 'CompÃ©tences Ã©lÃ¨ves',
                $p . 'ouin_exo_competency_teaching'     => 'CompÃ©tences vues en classe',
                $p . 'ouin_exo_exam_meta'               => 'MÃ©tadonnÃ©es bac',
                $p . 'ouin_exo_practical_calls'         => 'Appels pratiques',
                $p . 'ouin_exo_practical_files'         => 'Fichiers pratiques',
                $p . 'ouin_exo_practical_call_attempts' => 'Tentatives pratiques',
                $p . 'ouin_exo_practical_call_status'   => 'Statuts pratiques',
                $p . 'ouin_exo_badges'                  => 'Badges',
                $p . 'ouin_exo_user_badges'             => 'Badges Ã©lÃ¨ves',
                $p . 'ouin_exo_assessments'             => 'Devoirs',
                $p . 'ouin_exo_assessment_items'        => 'Exercices des devoirs',
                $p . 'ouin_exo_assessment_competencies' => 'CompÃ©tences des devoirs',
                $p . 'ouin_exo_assessment_results'      => 'RÃ©sultats des devoirs',
                $p . 'ouin_exo_assessment_attendance'   => 'PrÃ©sences aux devoirs',
                $p . 'ouin_exo_ai_attempts'             => 'Tentatives IA',
            ],
            'Flashcards' => [
                $p . 'ouin_fc_decks'           => 'Paquets de cartes',
                $p . 'ouin_fc_cards'           => 'Cartes',
                $p . 'ouin_fc_card_competency' => 'Liens cartes / compÃ©tences',
                $p . 'ouin_fc_user_cards'      => 'Ã‰tat des cartes par Ã©lÃ¨ve',
                $p . 'ouin_fc_reviews'         => 'Historique de rÃ©vision',
            ],
        ];
    }

    private static function renderTablesDiagnostic(): void
    {
        ?>
        <div class="card ouinpo-suite-card-bounded ouinpo-suite-card-spaced">
            <h2 class="ouinpo-suite-card-title">Tables attendues</h2>
            <p class="ouinpo-suite-muted">
                Les compteurs sont utiles pour vÃ©rifier une installation. Pour une archive de diffusion, les tables contenant des donnÃ©es Ã©lÃ¨ves doivent Ãªtre vides ou absentes du paquet exportÃ©.
            </p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th class="ouinpo-suite-col-22">Module</th>
                        <th>Table</th>
                        <th class="ouinpo-suite-col-26">RÃ´le</th>
                        <th class="ouinpo-suite-col-12">Ã‰tat</th>
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
                                <td><?php echo $count === null ? 'â€”' : esc_html(number_format_i18n($count)); ?></td>
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
            $wpdb->prefix . 'ouinpo_logs'                    => 'rÃ©ponses aux Ã©nigmes / exercices interactifs',
            $wpdb->prefix . 'ouinpo_progress'                => 'progression Gate',
            $wpdb->prefix . 'ouinpo_signatures'              => 'signatures et messages',
            $wpdb->prefix . 'ouin_exo_group_members'         => 'composition des classes',
            $wpdb->prefix . 'ouin_exo_user_status'           => 'statuts dâ€™exercices par Ã©lÃ¨ve',
            $wpdb->prefix . 'ouin_exo_user_reveals'          => 'indices et solutions rÃ©vÃ©lÃ©s',
            $wpdb->prefix . 'ouin_exo_user_competencies'     => 'compÃ©tences attribuÃ©es aux Ã©lÃ¨ves',
            $wpdb->prefix . 'ouin_exo_user_badges'           => 'badges attribuÃ©s',
            $wpdb->prefix . 'ouin_exo_assessment_results'    => 'rÃ©sultats de devoirs',
            $wpdb->prefix . 'ouin_exo_assessment_attendance' => 'prÃ©sences aux devoirs',
            $wpdb->prefix . 'ouin_exo_ai_attempts'           => 'traces de tentatives IA',
            $wpdb->prefix . 'ouin_fc_user_cards'             => 'Ã©tat des flashcards par Ã©lÃ¨ve',
            $wpdb->prefix . 'ouin_fc_reviews'                => 'historique des rÃ©visions',
            $wpdb->prefix . 'ouin_sf_suggestions'            => 'suggestions personnalisÃ©es',
        ];
        ?>
        <div class="card ouinpo-suite-card-bounded ouinpo-suite-card-spaced">
            <h2 class="ouinpo-suite-card-title">ContrÃ´le avant partage</h2>
            <p class="ouinpo-suite-muted">
                Ces Ã©lÃ©ments ne doivent pas Ãªtre inclus dans une archive destinÃ©e Ã  dâ€™autres professeurs.
            </p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Ã‰lÃ©ment sensible</th>
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
                            <td><?php echo $count === null ? 'â€”' : esc_html(number_format_i18n($count)); ?></td>
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
                        <td><code>ouinpo_sf_albert_api_key</code><br><span class="ouinpo-suite-muted">clÃ© API Albert</span></td>
                        <td>â€”</td>
                        <td><?php self::statusBadge(trim((string) get_option('ouinpo_sf_albert_api_key', '')) === '', 'Absente', 'PrÃ©sente : ne pas exporter'); ?></td>
                    </tr>
                    <tr>
                        <td><code>ouinpo_sf_openai_api_key</code><br><span class="ouinpo-suite-muted">clÃ© API OpenAI</span></td>
                        <td>â€”</td>
                        <td><?php self::statusBadge(trim((string) get_option('ouinpo_sf_openai_api_key', '')) === '', 'Absente', 'PrÃ©sente : ne pas exporter'); ?></td>
                    </tr>
                    <tr>
                        <td><code>ouinpo_sf_wxr_path</code><br><span class="ouinpo-suite-muted">chemin local vers un export WordPress / RAG</span></td>
                        <td>â€”</td>
                        <td><?php self::statusBadge(trim((string) get_option('ouinpo_sf_wxr_path', '')) === '', 'Absent', 'PrÃ©sent : chemin local Ã  nettoyer'); ?></td>
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
                <p>Ces rÃ©glages sont rÃ©servÃ©s aux administrateurs.</p>
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
                Choisissez le style utilisÃ© par les pages publiques de la suite.
                Le style sobre est recommandÃ© pour une installation partagÃ©e avec dâ€™autres enseignants.
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('ouinpo_suite_settings'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">Style de lâ€™interface</th>
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
                                            â€” style actuel, plus marquÃ© et pataphysique.
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
                                            â€” style neutre, clair, adaptÃ© Ã  une version partageable.
                                        </span>
                                    </label>

                                    <p class="description">
                                        Ce rÃ©glage ne supprime aucun fichier CSS. Il choisit simplement le thÃ¨me chargÃ© cÃ´tÃ© public.
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Enregistrer lâ€™apparence'); ?>
            </form>
        </div>
        <?php
    }

    private static function renderModulesSettings(): void
    {
        if (!current_user_can('manage_options')) {
            ?>
            <div class="notice notice-error">
                <p>Ces rÃ©glages sont rÃ©servÃ©s aux administrateurs.</p>
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
            echo '<div class="notice notice-success is-dismissible"><p>RÃ©glages des modules enregistrÃ©s.</p></div>';
        }
        ?>
        <div class="card ouinpo-suite-card-bounded">
            <h2 class="ouinpo-suite-card-title">Modules de la suite</h2>

            <p class="ouinpo-suite-muted">
                DÃ©sactiver un module empÃªche son chargement, ses menus, shortcodes ou fonctionnalitÃ©s,
                mais ne supprime aucune table ni aucune donnÃ©e.
            </p>

            <form method="post">
                <?php wp_nonce_field('ouinpo_suite_modules', 'ouinpo_suite_modules_nonce'); ?>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th class="ouinpo-suite-col-22">Ã‰tat</th>
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
                                        <?php echo $isEnabled ? 'ActivÃ©' : 'DÃ©sactivÃ©'; ?>
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
                                        Socle de la suite. Non dÃ©sactivable dans cette version.
                                    <?php elseif ($id === 'segfault'): ?>
                                        Chat, RAG, suggestions et parcours individualisÃ©s.
                                    <?php elseif ($id === 'flashcards'): ?>
                                        Cartes de rÃ©vision et mÃ©morisation.
                                    <?php elseif ($id === 'submissions'): ?>
                                        DÃ©pÃ´ts Ã©lÃ¨ves et ressources.
                                    <?php elseif ($id === 'gate'): ?>
                                        Ã‰nigmes, progression Gate et certificats.
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
                <p>Import rÃ©servÃ© aux administrateurs.</p>
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
                        'message' => 'Pack intÃ©grÃ© invalide.',
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
                        'message' => 'Aucun fichier JSON envoyÃ©.',
                        'details' => [],
                    ];
                } else {
                    $name = isset($_FILES['ouinpo_pack_upload']['name'])
                        ? sanitize_file_name((string) $_FILES['ouinpo_pack_upload']['name'])
                        : '';

                    if (!str_ends_with($name, '.json')) {
                        $result = [
                            'ok' => false,
                            'message' => 'Le fichier envoyÃ© doit Ãªtre un JSON.',
                            'details' => [],
                        ];
                    } else {
                        $result = PedagogicalPackImporter::importFromFile((string) $_FILES['ouinpo_pack_upload']['tmp_name']);
                    }
                }
            } else {
                $result = [
                    'ok' => false,
                    'message' => 'Source dâ€™import inconnue.',
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
            <h2 class="ouinpo-suite-card-title">Import pÃ©dagogique</h2>

            <p class="ouinpo-suite-muted">
                Cette version importe uniquement les niveaux, difficultÃ©s et compÃ©tences BO.
                Les exercices, flashcards et sujets pratiques seront traitÃ©s dans les versions suivantes de lâ€™importeur.
            </p>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ouinpo_pack_import', 'ouinpo_pack_import_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">Pack intÃ©grÃ©</th>
                            <td>
                                <label>
                                    <input type="radio" name="ouinpo_pack_source" value="bundled" checked>
                                    Importer un pack fourni avec lâ€™extension
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
                                    Importer un fichier JSON depuis lâ€™ordinateur
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
                    'CrÃ©er, modifier et organiser les badges disponibles.',
                    admin_url('admin.php?page=ouinpo-badges')
                );

                self::quickAction(
                    'Attributions manuelles',
                    'Attribuer ou retirer des badges Ã  des Ã©lÃ¨ves.',
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
                    'Attribuer un badge Ã  un Ã©lÃ¨ve, une classe ou vÃ©rifier les badges existants.',
                    admin_url('admin.php?page=ouinpo-badge-assignments')
                );

                self::quickAction(
                    'Catalogue des badges',
                    'Revenir Ã  la gestion des badges.',
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

        self::pageIntro('RÃ©glages', 'ParamÃ¨tres, diagnostic et maintenance lÃ©gÃ¨re de la suite.');
        self::tabs(self::mainTabs(), 'ouinpo-suite-settings');

        $settingsTabs = [
            'modules' => 'Modules',
            'appearance' => 'Apparence',
            'pages' => 'Pages & shortcodes',
        ];

        if (ModuleSettings::isEnabled('meta')) {
            $settingsTabs['meta'] = 'Meta & Social';
        }

        $settingsTabs['import'] = 'Import pÃ©dagogique';
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

        } elseif ($tab === 'pages') {
            settings_errors('ouinpo_suite_pages');
            PagesSetup::render();

        } elseif ($tab === 'meta' && ModuleSettings::isEnabled('meta')) {
            ?>
            <div class="ouinpo-suite-grid">
                <?php
                if (current_user_can('manage_options')) {
                    self::quickAction(
                        'Meta & Social',
                        'GÃ©rer les balises meta, Open Graph et rÃ©glages sociaux.',
                        admin_url('admin.php?page=ouinpo-meta-social')
                    );
                } else {
                    ?>
                    <div class="card ouinpo-suite-card">
                        <h3 class="ouinpo-suite-card-title">Meta & Social</h3>
                        <p>Ces rÃ©glages avancÃ©s sont rÃ©servÃ©s aux administrateurs.</p>
                    </div>
                    <?php
                }

                self::quickAction(
                    'Retour au tableau de bord',
                    'Revenir Ã  la vue dâ€™ensemble de la suite.',
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
                    'AccÃ©der rapidement Ã  lâ€™Ã©cran dâ€™import.',
                    admin_url('admin.php?page=ouinpo-import-exercises')
                );

                self::quickAction(
                    'Options exercices',
                    'AccÃ©der aux rÃ©glages spÃ©cifiques du module Exercices.',
                    admin_url('admin.php?page=ouinpo-suite-contents&tab=options')
                );

                if (ModuleSettings::isEnabled('submissions')) {
                    self::quickAction(
                        'Voir les dÃ©pÃ´ts Ã©lÃ¨ves',
                        'Consulter les productions rÃ©centes.',
                        admin_url('edit.php?post_type=ouinpo_submission')
                    );

                    self::quickAction(
                        'Voir les ressources prof',
                        'AccÃ©der aux ressources pÃ©dagogiques.',
                        admin_url('edit.php?post_type=ouinpo_resource')
                    );
                }

                if (current_user_can('manage_options') && ModuleSettings::isEnabled('segfault')) {
                    self::quickAction(
                        'SegFault',
                        'AccÃ©der aux rÃ©glages et outils SegFault.',
                        admin_url('admin.php?page=ouinpo-segfault')
                    );
                }
                ?>
            </div>

            <div class="card ouinpo-suite-card-bounded ouinpo-suite-card-spaced">
                <h2 class="ouinpo-suite-card-title">Rappel</h2>
                <p>Les opÃ©rations lourdes ou sensibles restent volontairement sur leurs Ã©crans mÃ©tier dâ€™origine. Cette page sert surtout de point dâ€™entrÃ©e, de contrÃ´le et dâ€™accÃ¨s rapide.</p>
            </div>
            <?php
        }

        self::endPage();
    }
}