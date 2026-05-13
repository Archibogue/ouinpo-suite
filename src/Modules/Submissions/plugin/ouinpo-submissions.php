<?php

// Module interne OuInPo Suite : Dépôts & Ressources.



if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists('ouinpo_submissions_user_can_manage') ) {

    function ouinpo_submissions_user_can_manage(?int $user_id = null): bool {

        $user_id = $user_id ?: get_current_user_id();

        return user_can($user_id, \Ouinpo\Suite\Core\Capabilities::MANAGE_SUBMISSIONS)
            || user_can($user_id, 'manage_options');

    }

}



if ( ! function_exists('ouinpo_extract_post_type_from_editor_arg') ) {

    function ouinpo_extract_post_type_from_editor_arg( $arg ) {

        if ( class_exists('WP_Block_Editor_Context') && $arg instanceof WP_Block_Editor_Context ) {

            if ( isset($arg->post) ) {

                if ( $arg->post instanceof WP_Post ) return $arg->post->post_type;

                if ( is_numeric($arg->post) )        return get_post_type( (int) $arg->post );

            }

            if ( isset($arg->post_type) && is_string($arg->post_type) ) return $arg->post_type;

            return null;

        }

        if ( $arg instanceof WP_Post ) return $arg->post_type;

        if ( is_numeric($arg) )        return get_post_type( (int) $arg );

        return null;

    }

}



class Ouinpo_Submissions_Plugin {

    const VERSION = '1.6.2';

    const CPT_SUBMISSION = 'ouinpo_submission';

    const CPT_RESOURCE   = 'ouinpo_resource';

    const TAX_CLASS      = 'ouinpo_classe';

    const TAX_SECTION    = 'ouinpo_section';

    // 🔹 Chapitres = domaines du plugin Exercices

    const TAX_CHAPTER    = 'ouinpo_chapter';



    const META_ALLOWED_USERS   = '_ouinpo_allowed_users';

    const META_ALLOWED_CLASSES = '_ouinpo_allowed_classes';

    const META_ALLOWED_GROUPS  = '_ouinpo_allowed_groups';

    const USERMETA_CLASS       = 'ouinpo_user_classes';

    const META_RES_ATTACHMENT  = '_ouinpo_res_attachment';

    const META_RES_ATTACHMENTS_LIST = '_ouinpo_res_attachments_list';

    const META_SUB_ATTACHMENT  = '_ouinpo_attachment';



    const USERMETA_RES_LAST_SEEN = 'ouinpo_res_last_seen_ts';



    private string $private_upload_subdir = '';



    public function filter_private_upload_dir($dirs) {

        if ($this->private_upload_subdir === '') {

            return $dirs;

        }



        $subdir = '/ouinpo/' . trim($this->private_upload_subdir, '/');



        $dirs['subdir'] = $subdir;

        $dirs['path']   = $dirs['basedir'] . $subdir;

        $dirs['url']    = $dirs['baseurl'] . $subdir;



        return $dirs;

    }



    private function ensure_private_upload_dir(string $subdir): string {

        $uploads = wp_upload_dir();

        $dir = trailingslashit($uploads['basedir']) . 'ouinpo/' . trim($subdir, '/');



        wp_mkdir_p($dir);



        $index = trailingslashit($dir) . 'index.php';

        if (!file_exists($index)) {

            @file_put_contents($index, "<?php\n// Silence is golden.\n");

        }



        $htaccess = trailingslashit($dir) . '.htaccess';

        if (!file_exists($htaccess)) {

            @file_put_contents(

                $htaccess,

                "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"

            );

        }



        return $dir;

    }



    private function private_handle_upload_as_attachment(string $field, string $subdir, int $parent_post_id) {

        if (empty($_FILES[$field]['name'])) {

            return new WP_Error('missing_file', 'Aucun fichier reçu.');

        }



        $this->ensure_private_upload_dir($subdir);



        $this->private_upload_subdir = trim($subdir, '/');

        add_filter('upload_dir', array($this, 'filter_private_upload_dir'));



        try {

            $uploaded = wp_handle_upload($_FILES[$field], array(

                'test_form' => false,

            ));

        } finally {

            remove_filter('upload_dir', array($this, 'filter_private_upload_dir'));

            $this->private_upload_subdir = '';

        }



        if (isset($uploaded['error'])) {

            return new WP_Error('upload_error', $uploaded['error']);

        }



        $filename = wp_basename($uploaded['file']);

        $filetype = wp_check_filetype($filename, null);

        $title    = preg_replace('/\.[^.]+$/', '', $filename);



        $attachment_id = wp_insert_attachment(array(

            'post_mime_type' => $filetype['type'] ?: 'application/octet-stream',

            'post_title'     => sanitize_text_field($title),

            'post_content'   => '',

            'post_status'    => 'inherit',

            'post_parent'    => $parent_post_id,

            'guid'           => $uploaded['url'],

        ), $uploaded['file'], $parent_post_id, true);



        if (is_wp_error($attachment_id)) {

            return $attachment_id;

        }



        if (strpos((string) ($filetype['type'] ?? ''), 'image/') === 0) {

            $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);

            if (!is_wp_error($metadata) && !empty($metadata)) {

                wp_update_attachment_metadata($attachment_id, $metadata);

            }

        }



        return (int) $attachment_id;

    }



    // ➜ Phrase aléatoire pour SegFault

    private static function segfault_random_phrase() {

        $phrases = array(

            "😼 « Ah, de nouvelles miettes numériques — je me régale. »",

            "🐾 « Tiens, quelqu’un a déposé du savoir. J’inspecte, évidemment. »",

            "😸 « Nouvelle ressource détectée — les neurones s’affûtent. »",

            "👁️‍🗨️ « Je sens l’odeur d’un PDF frais. Approchez, petits humains. »",

            "🌀 « Un fichier est arrivé. J’examine la boucle… et je ronronne. »",

            "😾 « Encore des devoirs ? Heureusement, j’aime l’ironie. »",

            "🐱‍👓 « Nouvelles ressources : à lire avant la sieste du prof. »",

            "📦 « Un ZIP élégant… j’en ferais presque un coussin. »",

            "🧪 « TP repéré. Hypothèse : vous allez ramer. Expérimentons. »",

            "📝 « Un corrigé ? Très bien, trichons honnêtement. »",

            "📚 « Cours détecté. Rangez vos excuses, sortez vos cerveaux. »",

            "🗂️ « Dossier repéré. L’ordre avant le chaos, pour une fois. »",

            "🧠 « Matière grise requise. J’observe, vous transpirez. »",

            "💾 « Sauvegardez vos progrès. Je garde les miens dans mes moustaches. »",

            "⚙️ « Paramètres optimaux : café, silence, ressource nouvelle. »",

            "🧭 « Cap sur la réussite : suivez le lien, pas vos intuitions. »",

            "🔎 « Indice trouvé. Le reste, c’est votre boulot. »",

            "🧩 « Pièce ajoutée au puzzle. Tentez d’être compatibles. »",

            "🚀 « Propulsion activée : une ressource de plus vers le succès. »",

            "🛠️ « J’ai aiguisé le tournevis. À vous de visser les neurones. »",

            "📈 « Progrès mesurables imminents… ou plantage spectaculaire. »",

            "🧵 « Fil logique déployé. Ne le coupez pas au premier bug. »",

            "🎯 « Objectif verrouillé. Lisez avant de cliquer partout. »",

            "🧊 « Ressource froide, esprit froid, code propre. »",

            "🔥 « Ressource chaude, attention aux variables qui fondent. »",

            "🪤 « Attention : pièges pédagogiques à l’intérieur. Délicieux. »",

            "🔐 « Contenu déverrouillé. Pas vos cerveaux, hélas. »",

            "🛡️ « Corrigé équipé. Paré à encaisser vos erreurs. »",

            "🌪️ « Tempête d’idées en approche. Accrochez vos boucles. »",

            "🗺️ « Carte fournie. Si vous vous perdez, c’est artistique. »"

        );



        return $phrases[array_rand($phrases)];

    }



    public function __construct() {

        add_action('init',                   array($this,'register_types'));

        add_action('init',                   array($this,'maybe_handle_download'), 1);

        add_action('admin_init',             array($this,'maybe_add_roles'));



        add_action('add_meta_boxes',         array($this,'add_resource_metaboxes'));

        add_action('save_post',              array($this,'save_resource_meta'), 10, 2);



        add_action('show_user_profile',      array($this,'user_classes_field'));

        add_action('edit_user_profile',      array($this,'user_classes_field'));

        add_action('personal_options_update',array($this,'save_user_classes_field'));

        add_action('edit_user_profile_update',array($this,'save_user_classes_field'));



        add_shortcode('ouinpo_upload',       array($this,'shortcode_upload'));

        add_shortcode('ouinpo_my_submissions',array($this,'shortcode_my_submissions'));

        add_shortcode('ouinpo_resources',    array($this,'shortcode_resources'));

        add_shortcode('ouinpo_class_field',  array($this,'shortcode_class_field'));



        add_action('user_register', array($this,'capture_registration_classes'));

        add_action('profile_update', array($this,'capture_profile_classes'), 10, 2);



        add_filter('user_has_cap',           array($this,'filter_submission_caps'), 10, 4);

        add_filter('ajax_query_attachments_args', array($this,'limit_media_library_for_students'));



        add_action('wp_enqueue_scripts',     array($this,'enqueue_styles'));

        add_action('admin_enqueue_scripts',  array($this,'admin_assets'));

        add_action('admin_notices',          array($this,'admin_notices'));



        add_action('wp_ajax_ouinpo_res_upload', array($this,'ajax_res_upload'));



        add_filter('allowed_block_types_all', array($this,'compat_allowed_block_types_all'), 10, 2);



        add_action('rest_api_init',          array($this,'register_rest_routes'));

        add_action('wp_enqueue_scripts',     array($this,'enqueue_front_vars'));

        add_action('wp_enqueue_scripts',     array($this,'enqueue_notifier_script'));



        add_filter('manage_edit-'.self::CPT_RESOURCE.'_columns', array($this,'add_resource_columns'));

        add_action('manage_'.self::CPT_RESOURCE.'_posts_custom_column', array($this,'render_resource_columns'), 10, 2);



        add_action('wp_footer', array($this,'notif_bootstrap_footer'), 999);

        add_filter('manage_edit-'.self::CPT_SUBMISSION.'_columns', array($this,'add_submission_columns'));

        add_action('manage_'.self::CPT_SUBMISSION.'_posts_custom_column', array($this,'render_submission_columns'), 10, 2);

    }



    public function compat_allowed_block_types_all( $allowed_block_types, $second_arg ) {

        $post_type = ouinpo_extract_post_type_from_editor_arg( $second_arg );

        return $allowed_block_types;

    }



    /* ---------- Styles front ---------- */

    public function enqueue_styles() {

        $css_rel = 'assets/css/front/submissions.css';

        $css_path = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR . $css_rel
            : '';

        $css_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL . $css_rel
            : '';

        if ($css_url !== '') {
            $css_ver = ($css_path !== '' && file_exists($css_path))
                ? (string) filemtime($css_path)
                : self::VERSION;

            $deps = [];

            if (wp_style_is('ouinpo-core-css', 'registered')) {
                $deps[] = 'ouinpo-core-css';
            }

            wp_enqueue_style(
                'ouinpo-submissions',
                $css_url,
                $deps,
                $css_ver
            );
            if (wp_style_is('ouinpo-theme-css', 'registered')) {
                wp_enqueue_style('ouinpo-theme-css');
            }
        }

    }

    private function enqueue_resources_script(): void {

        $js_rel = 'assets/js/front/submissions.js';
        $fallback_root = dirname(__DIR__, 4);

        $js_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL . $js_rel
            : plugin_dir_url($fallback_root . '/ouinpo-suite.php') . $js_rel;

        $js_path = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR . $js_rel
            : trailingslashit($fallback_root) . $js_rel;

        $js_version = file_exists($js_path)
            ? (string) filemtime($js_path)
            : self::VERSION;

        wp_enqueue_script(
            'ouinpo-submissions',
            $js_url,
            array('ouinpo-front-vars'),
            $js_version,
            true
        );

    }



    // Expose REST endpoint + nonce au front

    public function enqueue_front_vars() {

        if ( ! is_user_logged_in() ) return;

        wp_register_script('ouinpo-front-vars', false, array(), self::VERSION, true);

        wp_enqueue_script('ouinpo-front-vars');

        $data = array(

            'endpoint' => rest_url('ouinpo/v1/new-resources'),

            'nonce'    => wp_create_nonce('wp_rest'),

        );

        wp_add_inline_script('ouinpo-front-vars', 'window.OuInPoRes = '.wp_json_encode($data).';','before');

    }

    public function enqueue_notifier_script() {

        if ( ! is_user_logged_in() ) return;

        $js_rel = 'assets/js/front/submissions-notifier.js';
        $fallback_root = dirname(__DIR__, 4);

        $js_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL . $js_rel
            : plugin_dir_url($fallback_root . '/ouinpo-suite.php') . $js_rel;

        $js_path = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR . $js_rel
            : trailingslashit($fallback_root) . $js_rel;

        $js_version = file_exists($js_path)
            ? (string) filemtime($js_path)
            : self::VERSION;

        wp_enqueue_script(
            'ouinpo-submissions-notifier-js',
            $js_url,
            array('ouinpo-front-vars'),
            $js_version,
            true
        );

        wp_add_inline_script(
            'ouinpo-submissions-notifier-js',
            'window.OUINPO_SEGF_PHRASE = ' . wp_json_encode(self::segfault_random_phrase()) . ';',
            'before'
        );

    }



    /* ---------- Assets admin ---------- */

    public function admin_assets($hook){

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        $ptype  = $screen && isset($screen->post_type) ? $screen->post_type : null;

        if ( ! $ptype && isset($GLOBALS['typenow']) ) $ptype = $GLOBALS['typenow'];

        if ( ! $ptype && isset($_GET['post']) ) {

            $maybe = get_post_type( (int) $_GET['post'] );

            if ($maybe) $ptype = $maybe;

        }



        if (in_array($ptype, array(self::CPT_RESOURCE), true) || in_array((string) $hook, array('profile.php', 'user-edit.php'), true)) {
            $css_rel = 'assets/css/admin/submissions-admin.css';
            $css_path = defined('OUINPO_SUITE_DIR') ? OUINPO_SUITE_DIR . $css_rel : '';
            $css_url = defined('OUINPO_SUITE_URL') ? OUINPO_SUITE_URL . $css_rel : '';

            if ($css_url !== '') {
                wp_enqueue_style(
                    'ouinpo-submissions-admin',
                    $css_url,
                    [],
                    ($css_path !== '' && file_exists($css_path)) ? (string) filemtime($css_path) : self::VERSION
                );
            }
        }

        if (in_array($ptype, array(self::CPT_RESOURCE), true)) {

            $js_rel = 'assets/js/admin/submissions-admin.js';
            $fallback_root = dirname(__DIR__, 4);

            $js_url = defined('OUINPO_SUITE_URL')
                ? OUINPO_SUITE_URL . $js_rel
                : plugin_dir_url($fallback_root . '/ouinpo-suite.php') . $js_rel;

            $js_path = defined('OUINPO_SUITE_DIR')
                ? OUINPO_SUITE_DIR . $js_rel
                : trailingslashit($fallback_root) . $js_rel;

            $js_version = file_exists($js_path)
                ? (string) filemtime($js_path)
                : self::VERSION;

            wp_enqueue_script(
                'ouinpo-submissions-admin-js',
                $js_url,
                array('jquery'),
                $js_version,
                true
            );

            wp_localize_script('ouinpo-submissions-admin-js', 'OuinpoSubmissionsAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('ouinpo_res_upload'),
                'postId'  => get_the_ID(),
            ));
        }

    }



    /* ---------- Notices admin ---------- */

    public function admin_notices(){

        if ($msg = get_transient('ouinpo_notice')) {

            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html($msg).'</p></div>';

            delete_transient('ouinpo_notice');

        }

        if ($msg = get_transient('ouinpo_error')) {

            echo '<div class="notice notice-error is-dismissible"><p>'.esc_html($msg).'</p></div>';

            delete_transient('ouinpo_error');

        }

    }



    /* ---------- Rôles & capacités ---------- */

    public function maybe_add_roles() {

        if ( ! get_role('eleve') ) { add_role('eleve','Élève', array('read'=>true, 'upload_files'=>true)); }

        if ( ! get_role('prof') )  { add_role('prof','Professeur', array('read'=>true, 'upload_files'=>true)); }



        $caps_submissions = array(

            'edit_ouinpo_submissions'=>true,'edit_others_ouinpo_submissions'=>true,'publish_ouinpo_submissions'=>true,

            'read_private_ouinpo_submissions'=>true,'delete_ouinpo_submissions'=>true,'delete_others_ouinpo_submissions'=>true,

            'edit_private_ouinpo_submissions'=>true,'edit_published_ouinpo_submissions'=>true,

            'delete_private_ouinpo_submissions'=>true,'delete_published_ouinpo_submissions'=>true,

        );

        $caps_resources = array(

            'edit_ouinpo_resources'=>true,'edit_others_ouinpo_resources'=>true,'publish_ouinpo_resources'=>true,

            'read_private_ouinpo_resources'=>true,'delete_ouinpo_resources'=>true,'delete_others_ouinpo_resources'=>true,

            'edit_private_ouinpo_resources'=>true,'edit_published_ouinpo_resources'=>true,

            'delete_private_ouinpo_resources'=>true,'delete_published_ouinpo_resources'=>true,

        );



        foreach (array('prof','ouinpo_teacher','administrator') as $role_slug) {

            if ($r = get_role($role_slug)) {

                foreach(array_keys($caps_submissions + $caps_resources) as $cap){

                    if (!$r->has_cap($cap)) { $r->add_cap($cap); }

                }

            }

        }

        if ($r = get_role('eleve')) {

            foreach(array('read','read_private_ouinpo_resources') as $cap){

                if (!$r->has_cap($cap)) { $r->add_cap($cap); }

            }

        }

    }



    /* ---------- Types & taxonomies ---------- */

    public function register_types() {

        register_taxonomy(self::TAX_CLASS, array(self::CPT_RESOURCE), array(

            'labels' => array('name'=>'Classes','singular_name'=>'Classe'),

            'public' => false,'show_ui'=>true,'show_admin_column'=>true,'hierarchical'=>true,

        ));



        register_taxonomy(self::TAX_SECTION, array(self::CPT_RESOURCE), array(

            'labels' => array('name'=>'Sections','singular_name'=>'Section'),

            'public' => false,'show_ui'=>true,'show_admin_column'=>true,'hierarchical'=>false,

        ));



        // 🔹 Taxonomie des chapitres (alimentée par les domaines du plugin Exercices)

        register_taxonomy(self::TAX_CHAPTER, array(self::CPT_RESOURCE), array(

            'labels' => array(

                'name'          => 'Chapitres / domaines',

                'singular_name' => 'Chapitre / domaine',

            ),

            'public'            => false,

            'show_ui'           => true,

            'show_admin_column' => true,

            'hierarchical'      => true,

        ));



        // Synchroniser les chapitres avec les domaines du plugin Exercices

        global $wpdb;

        $table_comp = $wpdb->prefix . 'ouin_exo_competencies';



        $has_table = $wpdb->get_var(

            $wpdb->prepare("SHOW TABLES LIKE %s", $table_comp)

        );



        if ($has_table === $table_comp) {

            $domains = $wpdb->get_results("

                SELECT DISTINCT domain, domain_slug

                FROM {$table_comp}

                WHERE active = 1

                ORDER BY domain ASC

            ");



            if (!empty($domains)) {

                foreach ($domains as $dom) {

                    $name = $dom->domain;

                    $slug = $dom->domain_slug;



                    if (!$slug) {

                        $slug = sanitize_title($name);

                    }



                    if (!term_exists($slug, self::TAX_CHAPTER)) {

                        wp_insert_term($name, self::TAX_CHAPTER, array(

                            'slug' => $slug,

                        ));

                    }

                }

            }

        }



        foreach(array('cours'=>'Cours','corriges'=>'Corrigés','tp'=>'TP','projets'=>'Projets','ressources'=>'Ressources') as $slug=>$name){

            if ( ! term_exists($slug, self::TAX_SECTION) ) {

                wp_insert_term($name, self::TAX_SECTION, array('slug'=>$slug));

            }

        }

        

        $submission_show_in_menu = defined('OUINPO_SUITE_ADMIN_SLUG')

            ? OUINPO_SUITE_ADMIN_SLUG

            : true;

        

        $resource_show_in_menu = defined('OUINPO_SUITE_ADMIN_SLUG')

            ? OUINPO_SUITE_ADMIN_SLUG

            : true;

        

        register_post_type(self::CPT_SUBMISSION, array(

            'label'=>'Dépôts élèves',

            'public'=>false,

            'show_ui'=>true,

            'show_in_menu'=>$submission_show_in_menu,

            'supports'=>array('title','author','custom-fields'),

            'capability_type'=>array(self::CPT_SUBMISSION, self::CPT_SUBMISSION.'s'),

            'map_meta_cap'=>true,

        ));

        

        register_post_type(self::CPT_RESOURCE, array(

            'label'=>'Ressources prof',

            'public'=>false,

            'show_ui'=>true,

            'show_in_menu'=>$resource_show_in_menu,

            'supports'=>array('title','editor','author','thumbnail','custom-fields'),

            'taxonomies'=>array(self::TAX_CLASS, self::TAX_SECTION, self::TAX_CHAPTER),

        ));

    }



    /* ---------- Intégration avec le plugin Exercices : groupes ---------- */



    private function exo_tables_exist() {

        global $wpdb;

        $groups  = $wpdb->prefix . 'ouin_exo_groups';

        $members = $wpdb->prefix . 'ouin_exo_group_members';



        $has_groups  = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $groups) );

        $has_members = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $members) );



        return ($has_groups === $groups && $has_members === $members);

    }



    /**

     * Liste de tous les groupes (plugin Exercices), avec l'année.

     * Retourne un tableau d'objets { id, label, year_slug, is_active }.

     */

    private function get_exo_groups() {

        global $wpdb;

        if ( ! $this->exo_tables_exist() ) return array();



        $table_groups = $wpdb->prefix . 'ouin_exo_groups';

        $table_years  = $wpdb->prefix . 'ouin_exo_academic_years';



        $sql = "SELECT g.id, g.label, g.year_id,

                       y.slug AS year_slug,

                       y.is_active

                FROM {$table_groups} g

                LEFT JOIN {$table_years} y ON y.id = g.year_id

                ORDER BY y.is_active DESC, y.starts_on DESC, g.label ASC";

        $rows = $wpdb->get_results($sql);



        return is_array($rows) ? $rows : array();

    }



    /**

     * Indexe les groupes par ID: [ id => objet ].

     */

    private function get_exo_groups_indexed() {

        $rows = $this->get_exo_groups();

        $out  = array();

        foreach ($rows as $g) {

            $out[(int)$g->id] = $g;

        }

        return $out;

    }



    /**

     * IDs des groupes (plugin Exercices) auxquels appartient un utilisateur

     * pour l'année active (si définie).

     */

    private function get_exo_group_ids_for_user($user_id) {

        global $wpdb;



        $user_id = (int) $user_id;

        if ($user_id <= 0 || ! $this->exo_tables_exist() ) return array();



        $table_members = $wpdb->prefix . 'ouin_exo_group_members';

        $table_groups  = $wpdb->prefix . 'ouin_exo_groups';

        $table_years   = $wpdb->prefix . 'ouin_exo_academic_years';



        $sql = "SELECT gm.group_id

                FROM {$table_members} gm

                INNER JOIN {$table_groups} g ON g.id = gm.group_id

                LEFT JOIN {$table_years} y ON y.id = g.year_id

                WHERE gm.user_id = %d

                  AND (y.is_active = 1 OR y.id IS NULL)";

        $ids = $wpdb->get_col( $wpdb->prepare($sql, $user_id) );

        if ( ! is_array($ids) ) return array();



        $ids = array_map('intval', $ids);

        $ids = array_values(array_unique($ids));



        return $ids;

    }



    /**

     * Groupes de l'année active uniquement.

     */

    private function get_exo_active_groups() {

        $all = $this->get_exo_groups();

        $active = array();

        foreach ($all as $g) {

            if (isset($g->is_active) && (int) $g->is_active === 1) {

                $active[] = $g;

            }

        }

        return $active;

    }



    /**

     * IDs des membres d'un groupe (plugin Exercices).

     */

    private function get_exo_group_member_ids($group_id) {

        global $wpdb;

        $group_id = (int) $group_id;

        if ($group_id <= 0 || ! $this->exo_tables_exist()) return array();



        $table_members = $wpdb->prefix . 'ouin_exo_group_members';

        $ids = $wpdb->get_col(

            $wpdb->prepare("SELECT user_id FROM {$table_members} WHERE group_id = %d", $group_id)

        );

        if (!is_array($ids)) return array();



        $ids = array_map('intval', $ids);

        $ids = array_values(array_unique($ids));

        return $ids;

    }



    /* ---------- Profil utilisateur : champs classes ---------- */

    public function user_classes_field($user) {

        if ( ! \Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_CLASSES) && get_current_user_id() !== $user->ID ) { return; }

        $terms = get_terms(array('taxonomy'=>self::TAX_CLASS,'hide_empty'=>false));

        $selected = (array) get_user_meta($user->ID, self::USERMETA_CLASS, true);

        ?>

        <h2>Classe(s) de l’utilisateur</h2>

        <table class="form-table" role="presentation">

            <tr>

                <th><label>Classes</label></th>

                <td>

                    <?php if (empty($terms)): ?>

                        <p>Aucune classe. Créez des termes dans « Ressources prof » &gt; « Classes ».</p>

                    <?php else: ?>

                        <?php foreach($terms as $t): ?>

                            <label class="ouinpo-submissions-check-label">

                                <input type="checkbox" name="ouinpo_user_classes[]" value="<?php echo esc_attr($t->term_id); ?>" <?php checked(in_array($t->term_id,$selected)); ?> />

                                <?php echo esc_html($t->name); ?>

                            </label>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </td>

            </tr>

        </table>

        <?php

    }



    public function save_user_classes_field($user_id) {

        if ( ! current_user_can('edit_user', $user_id) ) return;

        $vals = isset($_POST['ouinpo_user_classes']) ? array_map('intval',(array)$_POST['ouinpo_user_classes']) : array();

        update_user_meta($user_id, self::USERMETA_CLASS, $vals);

    }



    /* ---------- Metabox Ressource : accès + fichier ---------- */

    public function add_resource_metaboxes() {

        add_meta_box('ouinpo_res_access','Accès des élèves (téléchargement)', array($this,'metabox_access'), self::CPT_RESOURCE, 'side', 'default');

        add_meta_box('ouinpo_res_files','Fichier à partager', array($this,'metabox_file'), self::CPT_RESOURCE, 'normal', 'default');

    }



    public function metabox_access($post) {

        $allowed_users   = (array) get_post_meta($post->ID, self::META_ALLOWED_USERS, true);

        // on force les IDs de groupes en int pour éviter les problèmes de types

        $allowed_groups  = array_map('intval', (array) get_post_meta($post->ID, self::META_ALLOWED_GROUPS, true));

    

        $groups = $this->get_exo_groups();

        $eleves = get_users(array(

            'role__in'=>array('eleve'),

            'orderby'=>'display_name',

            'order'  =>'ASC',

            'number' =>-1

        ));

    

        wp_nonce_field('ouinpo_res_access_'.$post->ID, 'ouinpo_res_access_nonce');

    

        echo '<p><strong>Par groupes (plugin Exercices)</strong></p>';

        if (empty($groups)) {

            echo '<p>Aucun groupe trouvé. Configurez des groupes dans le plugin Exercices.</p>';

        } else {

            foreach($groups as $g){

                $label = $g->label;

                if (!empty($g->year_slug)) {

                    $label .= ' ('.$g->year_slug.')';

                }

                $gid = (int) $g->id;

    

                printf(

                    '<label class="ouinpo-submissions-check-label"><input type="checkbox" name="ouinpo_allowed_groups[]" value="%d" %s/> %s</label>',

                    $gid,

                    checked(in_array($gid, $allowed_groups), true, false),

                    esc_html($label)

                );

            }

        }

    

        echo '<hr/><p><strong>Par élèves (accès individuel)</strong></p>';

        if (empty($eleves)) {

            echo '<p>Aucun utilisateur avec le rôle « Élève ».</p>';

        } else {

            echo '<div class="ouinpo-submissions-scroll-box">';

            foreach($eleves as $u){

                printf('<label class="ouinpo-submissions-check-label"><input type="checkbox" name="ouinpo_allowed_users[]" value="%d" %s/> %s (%s)</label>',

                    $u->ID, checked(in_array($u->ID,$allowed_users), true, false),

                    esc_html($u->display_name), esc_html($u->user_email));

            }

            echo '</div>';

        }

    

        echo '<p class="description">Les élèves cochés <em>ou</em> appartenant aux groupes cochés verront cette ressource dans [ouinpo_resources].</p>';

    }



    public function metabox_file($post) {

        $main_attachment = (int) get_post_meta($post->ID, self::META_RES_ATTACHMENT, true);

        $list_ids = get_post_meta($post->ID, self::META_RES_ATTACHMENTS_LIST, true);

        if (!is_array($list_ids)) {

            $list_ids = array();

        }

        if (empty($list_ids) && $main_attachment) {

            $list_ids = array($main_attachment);

        }

    

        wp_nonce_field('ouinpo_res_file_'.$post->ID, 'ouinpo_res_file_nonce');

    

        echo '<p>Vous pouvez <strong>glisser-déposer plusieurs fichiers</strong> ci-dessous. '

           . 'Ils seront stockés dans un dossier privé du plugin et servis uniquement via le téléchargement protégé.</p>';

    

        echo '<div id="ouinpo_res_current" class="ouinpo-res-current">';

    

        $empty_class = !empty($list_ids) ? ' is-hidden' : '';

        echo '<p class="ouinpo-empty'.$empty_class.'"><em>Aucun fichier sélectionné.</em></p>';

    

        echo '<ul id="ouinpo_res_files_list" class="ouinpo-res-files-list">';

        foreach ($list_ids as $aid) {

            $aid = (int) $aid;

            if (!$aid) continue;

            $url = add_query_arg(

                array('ouinpo_download'=>$aid,'o_n'=>wp_create_nonce('ouinpo_dl_'.$aid)),

                home_url('/')

            );

            $title = get_the_title($aid);

            echo '<li data-id="'.(int)$aid.'" class="ouinpo-res-file-item">';

            echo '<a href="'.esc_url($url).'" target="_blank">'.esc_html($title).'</a> (#'.(int)$aid.') ';

            echo '<button type="button" class="button-link ouinpo-remove-file">Retirer</button>';

            echo '<input type="hidden" name="ouinpo_res_attachment_ids[]" value="'.(int)$aid.'" />';

            echo '</li>';

        }

        echo '</ul>';

    

        echo '</div>';

    

        echo '<div id="ouinpo-dropzone" class="ouinpo-dropzone">

                <strong>Déposez vos fichiers ici</strong><br><span class="ouinpo-dropzone-help">ou cliquez pour parcourir…</span>

              </div>

              <input type="file" id="ouinpo-hidden-file" class="ouinpo-hidden-file" multiple />';

    }



    public function save_resource_meta($post_id, $post) {

        if ($post->post_type !== self::CPT_RESOURCE) return;

        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

        if ( wp_is_post_revision($post_id) ) return;

        if ( ! current_user_can('edit_post', $post_id) ) return;



        if (isset($_POST['ouinpo_res_access_nonce']) && wp_verify_nonce($_POST['ouinpo_res_access_nonce'],'ouinpo_res_access_'.$post_id)) {

            $users  = isset($_POST['ouinpo_allowed_users'])  ? array_map('intval',(array)$_POST['ouinpo_allowed_users'])  : array();

            $groups = isset($_POST['ouinpo_allowed_groups']) ? array_map('intval',(array)$_POST['ouinpo_allowed_groups']) : array();

        

            update_post_meta($post_id, self::META_ALLOWED_USERS,  $users);

            update_post_meta($post_id, self::META_ALLOWED_GROUPS, $groups);

        }



        if (isset($_POST['ouinpo_res_file_nonce']) && wp_verify_nonce($_POST['ouinpo_res_file_nonce'],'ouinpo_res_file_'.$post_id)) {

            $ids = isset($_POST['ouinpo_res_attachment_ids'])

                ? array_map('intval', (array) $_POST['ouinpo_res_attachment_ids'])

                : array();

        

            $ids = array_values(array_filter(array_unique($ids)));

        

            if (!empty($ids)) {

                update_post_meta($post_id, self::META_RES_ATTACHMENTS_LIST, $ids);

                update_post_meta($post_id, self::META_RES_ATTACHMENT, $ids[0]);

                set_transient('ouinpo_notice', 'Fichiers de ressource enregistrés avec succès.', 30);

            } else {

                delete_post_meta($post_id, self::META_RES_ATTACHMENTS_LIST);

                delete_post_meta($post_id, self::META_RES_ATTACHMENT);

                set_transient('ouinpo_notice', 'Aucun fichier associé à cette ressource.', 30);

            }

        }

    }



    /* ---------- AJAX : upload drag & drop ---------- */

    public function ajax_res_upload(){

        if ( ! current_user_can('upload_files') ) {

            wp_send_json_error(array('message'=>'Droit upload_files requis'), 403);

        }

        check_ajax_referer('ouinpo_res_upload', 'nonce');



        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || get_post_type($post_id)!==self::CPT_RESOURCE) {

            wp_send_json_error(array('message'=>'post_id invalide'), 400);

        }

        if ( empty($_FILES['file']['name']) ) {

            wp_send_json_error(array('message'=>'Aucun fichier reçu'), 400);

        }



        if ( ! current_user_can('edit_post', $post_id) ) {

            wp_send_json_error(array('message' => 'Droits insuffisants sur cette ressource'), 403);

        }

        

        require_once ABSPATH.'wp-admin/includes/file.php';

        require_once ABSPATH.'wp-admin/includes/media.php';

        require_once ABSPATH.'wp-admin/includes/image.php';

        

        $attachment_id = $this->private_handle_upload_as_attachment('file', 'resources', $post_id);

        if ( is_wp_error($attachment_id) ) {

            wp_send_json_error(array('message' => $attachment_id->get_error_message()), 500);

        }

        

        update_post_meta($post_id, self::META_RES_ATTACHMENT, $attachment_id);



        wp_send_json_success(array(

            'attachment_id' => $attachment_id,

            'title'         => get_the_title($attachment_id),

            'download_url'  => add_query_arg(

                array('ouinpo_download'=>$attachment_id,'o_n'=>wp_create_nonce('ouinpo_dl_'.$attachment_id)),

                home_url('/')

            ),

        ));

    }



    /* ---------- Shortcodes ---------- */



    public function shortcode_upload($atts, $content = '') {

        if ( ! is_user_logged_in() ) {

            return '<div class="ouinpo-box">Vous devez être connecté.</div>';

        }



        $u = wp_get_current_user();

        $out = '';



        if (isset($_POST['ouinpo_upload_nonce']) && wp_verify_nonce($_POST['ouinpo_upload_nonce'],'ouinpo_upload_submit')) {



            if (!empty($_FILES['ouinpo_file']['name'])) {



                require_once ABSPATH.'wp-admin/includes/file.php';

                require_once ABSPATH.'wp-admin/includes/media.php';

                require_once ABSPATH.'wp-admin/includes/image.php';



                $title = isset($_POST['ouinpo_title'])

                    ? sanitize_text_field($_POST['ouinpo_title'])

                    : ('Dépôt de '.$u->display_name.' - '.current_time('mysql'));



                $sub_id = wp_insert_post(array(

                    'post_type'   => self::CPT_SUBMISSION,

                    'post_title'  => $title,

                    'post_status' => 'publish',

                    'post_author' => $u->ID,

                ));



                if ($sub_id) {

                    $attachment_id = $this->private_handle_upload_as_attachment('ouinpo_file', 'submissions', $sub_id);



                    if (is_wp_error($attachment_id)) {

                        $out .= '<div class="ouinpo-box">Erreur lors de l’envoi : '.esc_html($attachment_id->get_error_message()).'</div>';

                        wp_delete_post($sub_id, true);

                    } else {

                        update_post_meta($sub_id, self::META_SUB_ATTACHMENT, $attachment_id);

                        $out .= '<div class="ouinpo-box">Fichier envoyé avec succès.</div>';

                    }

                } else {

                    $out .= '<div class="ouinpo-box">Impossible de créer le dépôt.</div>';

                }



            } else {

                $out .= '<div class="ouinpo-box">Veuillez choisir un fichier.</div>';

            }

        }



        ob_start(); ?>

        <form class="ouinpo-box" method="post" enctype="multipart/form-data">

            <p><label>Titre (optionnel)<br/>

                <input type="text" name="ouinpo_title" class="ouinpo-submissions-full-width"/></label>

            </p>

            <p><label>Votre fichier<br/>

                <input type="file" name="ouinpo_file" required/></label>

            </p>

            <?php wp_nonce_field('ouinpo_upload_submit','ouinpo_upload_nonce'); ?>

            <p><button type="submit">Envoyer</button></p>

            <p class="description">Vos dépôts sont visibles uniquement par vous et les profs/administrateurs.</p>

        </form>

        <?php

        $out .= ob_get_clean();

        return $out;

    }



    public function shortcode_my_submissions($atts) {

        if ( ! is_user_logged_in() ) {

            return '<div class="ouinpo-box">Vous devez être connecté.</div>';

        }

        $u = wp_get_current_user();

        $q = new WP_Query(array(

            'post_type'=>self::CPT_SUBMISSION,

            'author'=>$u->ID,

            'post_status'=>'any',

            'posts_per_page'=>-1,

            'orderby'=>'date',

            'order'=>'DESC'

        ));

        ob_start();

        echo '<div class="ouinpo-box"><h3>Mes dépôts</h3>';

        if (!$q->have_posts()) {

            echo '<p>Aucun fichier envoyé pour le moment.</p></div>';

            return ob_get_clean();

        }

        echo '<ul class="ouinpo-list">';

        while($q->have_posts()){ $q->the_post();

            $att_id = (int) get_post_meta(get_the_ID(), self::META_SUB_ATTACHMENT, true);

            $url = $att_id ? add_query_arg(array('ouinpo_download'=>$att_id,'o_n'=>wp_create_nonce('ouinpo_dl_'.$att_id)), home_url('/')) : '';

            echo '<li>'.esc_html(get_the_title()).' <span class="ouinpo-badge">'.esc_html(get_the_date()).'</span>';

            if ($att_id && $url) {

                echo ' - <a href="'.esc_url($url).'">Télécharger</a>';

            }

            echo '</li>';

        }

        wp_reset_postdata();

        echo '</ul></div>';

        return ob_get_clean();

    }



    // Page Ressources : rendu + purge locale + mark=1

    public function shortcode_resources($atts) {

        if ( ! is_user_logged_in() ) {

            return '<div class="ouinpo-box">Vous devez être connecté.</div>';

        }

    

        $this->enqueue_resources_script();

        $u        = wp_get_current_user();

        $is_staff = ouinpo_submissions_user_can_manage((int) $u->ID) || in_array('prof', (array) $u->roles, true);

    

        $user_group_ids = $this->get_exo_group_ids_for_user($u->ID);

    

        $q = new WP_Query(array(

            'post_type'      => self::CPT_RESOURCE,

            'post_status'    => 'publish',

            'posts_per_page' => -1,

            'orderby'        => 'date',

            'order'          => 'DESC',

            'no_found_rows'  => true,

        ));

    

        $order  = array('cours','corriges','tp','projets','ressources');

        $labels = array(

            'cours'      => 'Cours',

            'corriges'   => 'Corrigés',

            'tp'         => 'TP',

            'projets'    => 'Projets',

            'ressources' => 'Ressources',

        );

    

        $by_chapter = array();

    

        if ($q->have_posts()) {

            while($q->have_posts()){ $q->the_post();

                $post_id = get_the_ID();

                $allowed_users   = (array) get_post_meta($post_id, self::META_ALLOWED_USERS, true);

                $allowed_groups  = (array) get_post_meta($post_id, self::META_ALLOWED_GROUPS, true);

    

                $attachment_ids = get_post_meta($post_id, self::META_RES_ATTACHMENTS_LIST, true);

                if (!is_array($attachment_ids)) {

                    $attachment_ids = array();

                }

                $main_att = (int) get_post_meta($post_id, self::META_RES_ATTACHMENT, true);

                if (empty($attachment_ids) && $main_att) {

                    $attachment_ids = array($main_att);

                }

                $attachment_ids = array_values(array_filter(array_unique(array_map('intval', $attachment_ids))));

    

                $allowed = in_array($u->ID, $allowed_users, true)

                           || count(array_intersect($user_group_ids, $allowed_groups)) > 0;

    

                if (!$allowed || empty($attachment_ids)) {

                    continue;

                }

    

                $files = array();

                foreach ($attachment_ids as $aid) {

                    if (!$aid) continue;

                    $url = add_query_arg(

                        array('ouinpo_download'=>$aid,'o_n'=>wp_create_nonce('ouinpo_dl_'.$aid)),

                        home_url('/')

                    );

                    $title = get_the_title($aid);

                    if ($title === '') $title = 'Fichier #'.$aid;

    

                    $files[] = array(

                        'id'    => $aid,

                        'title' => $title,

                        'url'   => $url,

                    );

                }

                if (empty($files)) {

                    continue;

                }

    

                $section_terms = wp_get_post_terms($post_id, self::TAX_SECTION, array('fields'=>'slugs'));

                $section_slug  = (is_wp_error($section_terms) || empty($section_terms)) ? 'ressources' : $section_terms[0];

    

                $chapter_terms = wp_get_post_terms($post_id, self::TAX_CHAPTER, array('fields'=>'all'));

                if (is_wp_error($chapter_terms) || empty($chapter_terms)) {

                    $chapter_slug = 'no-domain';

                    $chapter_name = 'Sans domaine';

                } else {

                    $t = $chapter_terms[0];

                    $chapter_slug = $t->slug ? $t->slug : sanitize_title($t->name);

                    $chapter_name = $t->name ? $t->name : 'Sans domaine';

                }

    

                $content_raw = get_post_field('post_content', $post_id);

                $content     = '';

                if (!empty($content_raw)) {

                    $excerpt = wp_trim_words( wp_strip_all_tags( $content_raw ), 40, '…' );

                    $content = wpautop( esc_html( $excerpt ) );

                }

    

                if ( ! isset($by_chapter[$chapter_slug]) ) {

                    $by_chapter[$chapter_slug] = array(

                        'name'     => $chapter_name,

                        'sections' => array(),

                    );

                }

    

                if ( ! isset($by_chapter[$chapter_slug]['sections'][$section_slug]) ) {

                    $by_chapter[$chapter_slug]['sections'][$section_slug] = array();

                }

    

                $by_chapter[$chapter_slug]['sections'][$section_slug][] = array(

                    'title'   => get_the_title(),

                    'date'    => get_the_date(),

                    'files'   => $files,

                    'content' => $content,

                );

            }

            wp_reset_postdata();

        }

    

        ob_start();

    

        echo '<div class="ouinpo-box"><h3>Ressources pour moi</h3>';

    

        $total = 0;

    

        if (!empty($by_chapter)) {

            uksort($by_chapter, function($a, $b) use ($by_chapter){

                $na = $by_chapter[$a]['name'];

                $nb = $by_chapter[$b]['name'];

                return strcasecmp($na, $nb);

            });

    

            echo '<div class="ouinpo-res-filters">';

            echo '<label>Filtrer par domaine : ';

            echo '<select class="ouinpo-res-select">';

            echo '<option value="all">Tous les domaines</option>';

            foreach($by_chapter as $slug => $data){

                echo '<option value="'.esc_attr($slug).'">'.esc_html($data['name']).'</option>';

            }

            echo '</select>';

            echo '</label>';

            echo '</div>';

    

            foreach ($by_chapter as $chapter_slug => $data) {

    

                if (!empty($data['sections'])) {

                    foreach ($data['sections'] as $sec_items) {

                        $total += count($sec_items);

                    }

                }

    

                echo '<div class="ouinpo-chapter" data-domain="'.esc_attr($chapter_slug).'">';

                echo '<div class="ouinpo-chapter-title">'.esc_html($data['name']).'</div>';

    

                foreach ($order as $slug){

                    if (empty($data['sections'][$slug])) continue;

    

                    echo '<div class="ouinpo-section">';

                    echo '<h4>'.esc_html($labels[$slug]).'</h4>';

                    echo '<ul class="ouinpo-list">';

                    

                    foreach($data['sections'][$slug] as $item){

                        echo '<li>';

                        echo '<strong>'.esc_html($item['title']).'</strong> ';

                        echo '<span class="ouinpo-badge">'.esc_html($item['date']).'</span>';

                    

                        if (!empty($item['files'])) {

                            echo '<div class="ouinpo-res-files">';

                            echo '<span class="ouinpo-files-label">Fichiers :</span>';

                            echo '<ul class="ouinpo-files-list">';

                            foreach ($item['files'] as $f) {

                                echo '<li><a href="'.esc_url($f['url']).'">'.esc_html($f['title']).'</a></li>';

                            }

                            echo '</ul>';

                            echo '</div>';

                        }

                    

                        if (!empty($item['content'])) {

                            echo '<div class="ouinpo-res-desc">'.$item['content'].'</div>';

                        }

                        echo '</li>';

                    }



                    echo '</ul></div>';

                }

    

                echo '</div>';

            }

        }

    

        if ($total === 0) {

            echo '<p>Aucune ressource disponible pour le moment.</p>';

        }

        echo '</div>';

    

        if ($is_staff) {

    

            echo '<div class="ouinpo-box"><h3>Dépôts des élèves (année active)</h3>';

    

            $groups = $this->get_exo_active_groups();

            if (empty($groups)) {

                echo '<p>Aucun groupe pour l\'année active.</p>';

            } else {

    

                $group_members      = array();

                $member_to_groups   = array();

                $all_member_ids     = array();

    

                foreach ($groups as $g) {

                    $gid = (int) $g->id;

                    $ids = $this->get_exo_group_member_ids($gid);

                    if (empty($ids)) continue;

    

                    $group_members[$gid] = $ids;

                    foreach ($ids as $uid) {

                        $all_member_ids[] = $uid;

                        if (!isset($member_to_groups[$uid])) {

                            $member_to_groups[$uid] = array();

                        }

                        $member_to_groups[$uid][] = $gid;

                    }

                }

    

                $all_member_ids = array_values(array_unique(array_map('intval', $all_member_ids)));

    

                if (empty($all_member_ids)) {

                    echo '<p>Aucun élève dans les groupes de l\'année active.</p>';

                } else {

                    $q_sub = new WP_Query(array(

                        'post_type'      => self::CPT_SUBMISSION,

                        'post_status'    => 'any',

                        'posts_per_page' => 200,

                        'orderby'        => 'date',

                        'order'          => 'DESC',

                        'author__in'     => $all_member_ids,

                        'no_found_rows'  => true,

                    ));

    

                    $sub_by_group = array();

    

                    if ($q_sub->have_posts()) {

                        while ($q_sub->have_posts()) { $q_sub->the_post();

                            $sub_id    = get_the_ID();

                            $author_id = (int) get_post_field('post_author', $sub_id);

                            $att_id    = (int) get_post_meta($sub_id, self::META_SUB_ATTACHMENT, true);

                            if ( ! $att_id ) continue;

    

                            $url = add_query_arg(array(

                                'ouinpo_download' => $att_id,

                                'o_n'             => wp_create_nonce('ouinpo_dl_'.$att_id),

                            ), home_url('/'));

    

                            $groups_for_author = isset($member_to_groups[$author_id]) ? $member_to_groups[$author_id] : array();

                            if (empty($groups_for_author)) continue;

    

                            foreach ($groups_for_author as $gid) {

                                if (!isset($sub_by_group[$gid])) {

                                    $sub_by_group[$gid] = array();

                                }

                                if (!isset($sub_by_group[$gid][$author_id])) {

                                    $sub_by_group[$gid][$author_id] = array();

                                }

                                $sub_by_group[$gid][$author_id][] = array(

                                    'title' => get_the_title($sub_id),

                                    'date'  => get_the_date('', $sub_id),

                                    'url'   => $url,

                                );

                            }

                        }

                        wp_reset_postdata();

                    }

    

                    if (empty($sub_by_group)) {

                        echo '<p>Aucun dépôt récent trouvé pour l\'année active (limite 200 derniers).</p>';

                    } else {

                        foreach ($groups as $g) {

                            $gid = (int) $g->id;

                            if (empty($sub_by_group[$gid])) continue;

    

                            $group_name = $g->label;

                            if (!empty($g->year_slug)) {

                                $group_name .= ' ('.$g->year_slug.')';

                            }

    

                            echo '<div class="ouinpo-chapter">';

                            echo '<div class="ouinpo-chapter-title">'.esc_html($group_name).'</div>';

    

                            foreach ($sub_by_group[$gid] as $student_id => $subs) {

                                $student = get_userdata($student_id);

                                if ( ! $student ) continue;

    

                                echo '<div class="ouinpo-section">';

                                echo '<h4>'.esc_html($student->display_name).'</h4>';

                                echo '<ul class="ouinpo-list">';

                                foreach ($subs as $item) {

                                    echo '<li><strong>'.esc_html($item['title']).'</strong> — <a href="'.esc_url($item['url']).'">Télécharger</a> <span class="ouinpo-badge">'.esc_html($item['date']).'</span></li>';

                                }

                                echo '</ul></div>';

                            }

    

                            echo '</div>';

                        }

    

                            echo '<p class="ouinpo-submissions-limit-note">(Affichage limité aux 200 dépôts les plus récents de l\'année active.)</p>';

                    }

                }

            }

    

            echo '</div>';

        }

    

        update_user_meta(get_current_user_id(), self::USERMETA_RES_LAST_SEEN, time());

    

        echo '<div id="ouinpo-resources-view" hidden></div>';

    

        $html = ob_get_clean();

        return $html;

    }



    public function shortcode_class_field($atts){

        $atts = shortcode_atts(array('multiple'=>'no','label'=>'Classe','required'=>'yes'), $atts, 'ouinpo_class_field');

        $terms = get_terms(array('taxonomy'=>self::TAX_CLASS,'hide_empty'=>false));

        if (is_wp_error($terms) || empty($terms)) {

            return '<div class="ouinpo-box">Aucune classe définie pour le moment.</div>';

        }

        $multiple = strtolower($atts['multiple'])==='yes';

        $required = strtolower($atts['required'])==='yes' ? 'required' : '';

        ob_start();

        echo '<div class="ouinpo-box">';

        printf('<label>%s%s<br/>', esc_html($atts['label']), $required ? ' *' : '');

        printf('<select name="ouinpo_user_classes%s" %s class="ouinpo-submissions-full-width">', $multiple ? '[]' : '', $multiple ? 'multiple size="4"' : '');

        foreach($terms as $t){ printf('<option value="%d">%s</option>', $t->term_id, esc_html($t->name)); }

        echo '</select></label>';

        if ($multiple) { echo '<p class="description">Maintenez Ctrl (Windows) ou ⌘ (Mac) pour sélectionner plusieurs classes.</p>'; }

        echo '</div>';

        return ob_get_clean();

    }



    public function capture_registration_classes($user_id){

        if (isset($_POST['ouinpo_user_classes'])) {

            $vals = array_map('intval',(array)$_POST['ouinpo_user_classes']);

            update_user_meta($user_id, self::USERMETA_CLASS, $vals);

        }

    }

    public function capture_profile_classes($user_id, $old_user_data){

        if (isset($_POST['ouinpo_user_classes'])) {

            $vals = array_map('intval',(array)$_POST['ouinpo_user_classes']);

            update_user_meta($user_id, self::USERMETA_CLASS, $vals);

        }

    }



    /* ---------- Capacités dynamiques sur dépôts ---------- */

    public function filter_submission_caps($allcaps, $caps, $args, $user) {

        if (isset($args[2])) {

            $post_id = (int)$args[2];

            $post = get_post($post_id);

            if ($post && $post->post_type === self::CPT_SUBMISSION) {

                $is_owner = (int)$post->post_author === (int)$user->ID;

                $is_staff = ouinpo_submissions_user_can_manage((int) $user->ID) || in_array('prof', (array)$user->roles, true);

                if ($args[0]==='read_post') {

                    $allcaps['read_post'] = ($is_owner || $is_staff);

                } elseif ($args[0]==='edit_post' || $args[0]==='delete_post') {

                    $allcaps[$args[0]] = ($is_owner || $is_staff);

                }

            }

        }

        return $allcaps;

    }



    public function limit_media_library_for_students($args) {

        $u = wp_get_current_user();

        if (in_array('eleve', (array)$u->roles, true)) {

            $args['author'] = $u->ID;

        }

        return $args;

    }



    /* ---------- Téléchargement protégé ---------- */

    public function maybe_handle_download() {

        if (isset($_GET['ouinpo_download'])) {

            $att_id = (int) $_GET['ouinpo_download'];

            if ( ! $att_id || ! wp_verify_nonce($_GET['o_n'] ?? '', 'ouinpo_dl_'.$att_id) ) { status_header(403); exit('Lien invalide.'); }

            $attachment = get_post($att_id);

            if (!$attachment || $attachment->post_type!=='attachment') { status_header(404); exit('Fichier introuvable.'); }

            $current = wp_get_current_user();

            if (!$current || 0 == $current->ID) { status_header(401); exit('Connexion requise.'); }



            $parent = get_post($attachment->post_parent);

            $allowed = false;



            if ($parent && $parent->post_type === self::CPT_SUBMISSION) {

                $allowed = ( (int)$parent->post_author === (int)$current->ID ) || ouinpo_submissions_user_can_manage((int) $current->ID) || in_array('prof',(array)$current->roles,true);

            } else {

                $res_post = ($parent && $parent->post_type===self::CPT_RESOURCE) ? $parent : null;

                if ($res_post) {

                    $allowed_users   = (array) get_post_meta($res_post->ID, self::META_ALLOWED_USERS, true);

                    $allowed_groups  = (array) get_post_meta($res_post->ID, self::META_ALLOWED_GROUPS, true);

                    $user_groups     = $this->get_exo_group_ids_for_user($current->ID);

                    

                    $allowed = in_array($current->ID, $allowed_users, true)

                               || count(array_intersect($user_groups, $allowed_groups)) > 0

                               || ouinpo_submissions_user_can_manage((int) $current->ID)

                               || in_array('prof',(array)$current->roles,true);

                }

            }



            if (!$allowed) { status_header(403); exit('Accès refusé.'); }



            $file = get_attached_file($att_id);

            if (!file_exists($file)) { status_header(404); exit('Fichier manquant.'); }



            $mime = get_post_mime_type($att_id);

            header('Content-Type: '.($mime ?: 'application/octet-stream'));

            header('Content-Disposition: attachment; filename="'.basename($file).'"');

            header('Content-Length: '.filesize($file));

            readfile($file);

            exit;

        }

    }



    /* ---------- Colonnes admin Ressources ---------- */

    public function add_resource_columns($cols){

        $new = array();

        foreach($cols as $k=>$v){

            $new[$k] = $v;

            if ($k==='title'){

                $new['ouinpo_access'] = 'Accès (classes / élèves)';

            }

        }

        return $new;

    }

    public function render_resource_columns($column, $post_id){

        if ($column !== 'ouinpo_access') return;

    

        $groups_ids = (array) get_post_meta($post_id, self::META_ALLOWED_GROUPS, true);

        $users_ids  = (array) get_post_meta($post_id, self::META_ALLOWED_USERS, true);

    

        $out = array();

    

        if (!empty($groups_ids)){

            $all_groups = $this->get_exo_groups_indexed();

            $names = array();

    

            foreach ($groups_ids as $gid){

                $gid = (int) $gid;

                if (isset($all_groups[$gid])) {

                    $g = $all_groups[$gid];

                    $label = $g->label;

                    if (!empty($g->year_slug)) {

                        $label .= ' ('.$g->year_slug.')';

                    }

                    $names[] = $label;

                }

            }

    

            if (!empty($names)) {

                $out[] = '<strong>Groupes:</strong> '.esc_html(implode(', ', $names));

            }

        }

    

        if (!empty($users_ids)){

            $users = get_users(array('include'=>$users_ids,'orderby'=>'display_name','order'=>'ASC'));

            if ($users){

                $names = array_map(function($u){ return $u->display_name; }, $users);

                $display = implode(', ', array_slice($names, 0, 5));

                if (count($names) > 5) { $display .= ' … (+'.(count($names)-5).')'; }

                $out[] = '<strong>Élèves:</strong> '.esc_html($display);

            }

        }

    

        if (empty($out)) echo '<em>Aucun accès défini</em>';

        else echo implode('<br/>', $out);

    }



    /* ---------- REST: nouveautés ---------- */

    public function register_rest_routes() {

        register_rest_route('ouinpo/v1', '/new-resources', array(

            array(

                'methods'  => 'GET',

                'callback' => array($this,'rest_get_new_resources'),

                'permission_callback' => function() { return is_user_logged_in(); },

                'args' => array(

                    'mark'  => array('type'=>'boolean','required'=>false,'description'=>'Si true, met à jour le last_seen à maintenant.'),

                    'limit' => array('type'=>'integer','required'=>false,'default'=>50,'minimum'=>1,'maximum'=>100),

                ),

            ),

        ));

    }



    public function rest_get_new_resources( WP_REST_Request $req ) {

        if ( ! is_user_logged_in() ) {

            return new WP_Error('ouinpo_auth', 'Connexion requise', array('status'=>401));

        }

        $user  = wp_get_current_user();

        $user_group_ids = $this->get_exo_group_ids_for_user($user->ID);

        $mark  = (bool) $req->get_param('mark');

        $limit = max(1, min(100, (int)$req->get_param('limit')));



        $last_seen = (int) get_user_meta($user->ID, self::USERMETA_RES_LAST_SEEN, true);

        if ($last_seen <= 0) {

            $last_seen = 1;

        }



        $q = new WP_Query(array(

            'post_type'      => self::CPT_RESOURCE,

            'post_status'    => 'publish',

            'posts_per_page' => $limit,

            'orderby'        => 'date',

            'order'          => 'DESC',

            'date_query'     => array(

                array('after' => gmdate('Y-m-d H:i:s', $last_seen), 'inclusive' => false, 'column'=>'post_date_gmt'),

            ),

            'no_found_rows'  => true,

        ));



        $user_classes = (array) get_user_meta($user->ID, self::USERMETA_CLASS, true);

        $is_staff = ouinpo_submissions_user_can_manage((int) $user->ID) || in_array('prof', (array)$user->roles, true);



        $groups = array('cours'=>array(),'corriges'=>array(),'tp'=>array(),'projets'=>array(),'ressources'=>array());



        if ($q->have_posts()) {

            while($q->have_posts()){ $q->the_post();

                $pid = get_the_ID();

                $allowed_users   = (array) get_post_meta($pid, self::META_ALLOWED_USERS, true);

                $allowed_groups = (array) get_post_meta($pid, self::META_ALLOWED_GROUPS, true);

                $att_id = (int) get_post_meta($pid, self::META_RES_ATTACHMENT, true);



                $allowed = $is_staff

                    || in_array($user->ID, $allowed_users, true)

                    || count(array_intersect($user_group_ids, $allowed_groups)) > 0;



                if ( ! $allowed || ! $att_id ) continue;



                $terms = wp_get_post_terms($pid, self::TAX_SECTION, array('fields'=>'slugs'));

                $slug  = (is_wp_error($terms) || empty($terms)) ? 'ressources' : $terms[0];

                if ( ! isset($groups[$slug]) ) $groups[$slug] = array();



                $url = add_query_arg(array('ouinpo_download'=>$att_id,'o_n'=>wp_create_nonce('ouinpo_dl_'.$att_id)), home_url('/'));



                $groups[$slug][] = array(

                    'id'     => $pid,

                    'title'  => get_the_title($pid),

                    'date'   => get_the_date('', $pid),

                    'url'    => $url,

                );

            }

            wp_reset_postdata();

        }



        $count = 0; foreach($groups as $arr){ $count += count($arr); }



        if ($mark && $count > 0) {

            update_user_meta($user->ID, self::USERMETA_RES_LAST_SEEN, time());

        }



        return array('count'=>$count, 'since'=>$last_seen, 'sections'=>$groups);

    }



    /* ---------- Footer: badge + persistance locale (anti-doublon) ---------- */

    public function notif_bootstrap_footer() {

        if ( ! is_user_logged_in() ) return;

    }



    /* ---------- Colonnes admin : Dépôts élèves ---------- */

    public function add_submission_columns($cols){

        $new = array();

        foreach ($cols as $k=>$v){

            $new[$k] = $v;

            if ($k === 'title') {

                $new['ouinpo_file'] = 'Fichier envoyé';

            }

        }

        return $new;

    }



    public function render_submission_columns($column, $post_id){

        if ($column !== 'ouinpo_file') return;

        $att_id = (int) get_post_meta($post_id, self::META_SUB_ATTACHMENT, true);

        if ($att_id) {

            $title = get_the_title($att_id);

            $url = add_query_arg(array(

                'ouinpo_download' => $att_id,

                'o_n' => wp_create_nonce('ouinpo_dl_'.$att_id)

            ), home_url('/'));

            echo '<a href="'.esc_url($url).'">'.esc_html($title).'</a>';

        } else {

            echo '<em>Aucun fichier</em>';

        }

    }

}



new Ouinpo_Submissions_Plugin();
