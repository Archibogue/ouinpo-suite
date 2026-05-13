<?php

// Module interne OuInPo Suite : Meta Description + Social Cards.



if (!defined('ABSPATH')) exit;



final class Ouinpo_Meta_Social {



    const META_KEY = '_ouinpo_meta_description';

    const OPTION_DEFAULT_IMAGE = 'ouinpo_meta_default_social_image';



    public static function init() {

        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);

        add_action('save_post', [__CLASS__, 'save_post']);

        add_action('wp_head', [__CLASS__, 'print_meta_tags'], 1);

        add_action('admin_menu', [__CLASS__, 'add_settings_page']);

        add_action('admin_init', [__CLASS__, 'register_settings']);

    }



    public static function get_supported_post_types(): array {

        $post_types = get_post_types(['public' => true], 'names');

        unset($post_types['attachment']);



        $post_types = apply_filters('ouinpo_meta_description_post_types', $post_types);



        if (!is_array($post_types) || empty($post_types)) {

            return ['post', 'page'];

        }



        return array_values(array_unique(array_filter($post_types)));

    }



    public static function get_supported_taxonomies(): array {

        $taxes = [

            'category',

            'post_tag',

            'ouinpo_section',

            'ouinpo_chapter',

            'ouinpo_classe',

        ];



        $taxes = apply_filters('ouinpo_meta_description_taxonomies', $taxes);



        return array_values(array_unique(array_filter($taxes)));

    }



    public static function add_meta_boxes(): void {

        foreach (self::get_supported_post_types() as $post_type) {

            add_meta_box(

                'ouinpo_meta_description_box',

                'Meta description',

                [__CLASS__, 'render_meta_box'],

                $post_type,

                'normal',

                'default'

            );

        }

    }



    public static function render_meta_box(\WP_Post $post): void {

        wp_nonce_field('ouinpo_meta_description_save', 'ouinpo_meta_description_nonce');



        $value = get_post_meta($post->ID, self::META_KEY, true);



        echo '<p>Texte destiné à l’extrait affiché par les moteurs de recherche et aux aperçus de partage.</p>';

        echo '<textarea name="ouinpo_meta_description" class="large-text" rows="5" maxlength="160">'

            . esc_textarea($value)

            . '</textarea>';

        echo '<p><em>Conseil : 140 à 160 caractères, clairs, concrets, sans jargon fumeux.</em></p>';

    }



    public static function save_post(int $post_id): void {

        if (!isset($_POST['ouinpo_meta_description_nonce'])) return;

        if (!wp_verify_nonce($_POST['ouinpo_meta_description_nonce'], 'ouinpo_meta_description_save')) return;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (wp_is_post_revision($post_id)) return;



        $post_type = get_post_type($post_id);

        if (!$post_type || !in_array($post_type, self::get_supported_post_types(), true)) return;

        if (!current_user_can('edit_post', $post_id)) return;



        $value = isset($_POST['ouinpo_meta_description'])

            ? sanitize_text_field(wp_unslash($_POST['ouinpo_meta_description']))

            : '';



        if ($value === '') {

            delete_post_meta($post_id, self::META_KEY);

        } else {

            update_post_meta($post_id, self::META_KEY, $value);

        }

    }



    public static function normalize_text(string $text, int $max = 160): string {

        $text = strip_shortcodes($text);

        $text = wp_strip_all_tags($text);

        $text = preg_replace('/\s+/u', ' ', $text);

        $text = trim((string) $text);



        if ($text === '') return '';



        if (mb_strlen($text) > $max) {

            $text = mb_substr($text, 0, $max - 3) . '...';

        }



        return $text;

    }



    public static function looks_like_shortcode_content(string $content): bool {

        $content = trim($content);

        if ($content === '') return true;

        if (preg_match('/^\[[^\]]+\]$/', $content)) return true;



        $without_shortcodes = trim(strip_shortcodes($content));

        return mb_strlen(wp_strip_all_tags($without_shortcodes)) < 30;

    }



    public static function get_ouinpo_fallback_for_post(\WP_Post $post): string {

        $slug = (string) $post->post_name;

        $content = (string) $post->post_content;



        if (strpos($content, '[ouinpo_exercises') !== false || strpos($content, '[ouinpo-exercises') !== false) {

            return 'Exercices d’informatique sur OuInPo, classés par niveau et thématiques, avec indices et corrigés progressifs.';

        }



        if (strpos($content, '[ouinpo_exercise') !== false || strpos($content, '[ouinpo-exercise') !== false) {

            return 'Exercice d’informatique sur OuInPo avec énoncé, indices progressifs et corrigé expliqué.';

        }



        if (strpos($content, '[ouinpo_competences_progress') !== false) {

            return 'Suivi de progression des compétences en informatique sur OuInPo.';

        }



        if (strpos($content, '[ouinpo_student_badges') !== false) {

            return 'Badges et récompenses obtenus sur OuInPo pour valoriser la progression en informatique.';

        }



        if (strpos($content, '[ouinpo_badges_palmares') !== false) {

            return 'Palmarès des badges OuInPo : distinctions, progression et réussites des élèves.';

        }



        if ($slug === 'exercices' || $slug === 'exercice') {

            return 'Exercices d’informatique sur OuInPo avec niveaux, indices et corrigés.';

        }



        return '';

    }



    public static function get_description_for_singular(\WP_Post $post): string {

        $custom = get_post_meta($post->ID, self::META_KEY, true);

        if (!empty($custom)) {

            return self::normalize_text($custom, 160);

        }



        if (!empty($post->post_excerpt)) {

            return self::normalize_text($post->post_excerpt, 160);

        }



        $ouinpo_fallback = self::get_ouinpo_fallback_for_post($post);

        if ($ouinpo_fallback !== '') {

            return self::normalize_text($ouinpo_fallback, 160);

        }



        if (!empty($post->post_content) && !self::looks_like_shortcode_content($post->post_content)) {

            return self::normalize_text($post->post_content, 160);

        }



        if (!empty($post->post_title)) {

            return self::normalize_text($post->post_title . ' - ' . get_bloginfo('name'), 160);

        }



        return '';

    }



    public static function get_description_for_term(\WP_Term $term): string {

        $taxonomy = $term->taxonomy;

        $name = $term->name;



        if (!empty($term->description)) {

            return self::normalize_text($term->description, 160);

        }



        if ($taxonomy === 'ouinpo_section') {

            return self::normalize_text($name . ' : contenus et ressources de la section sur OuInPo.', 160);

        }



        if ($taxonomy === 'ouinpo_chapter') {

            return self::normalize_text($name . ' : cours, exercices, TP et ressources d’informatique sur OuInPo.', 160);

        }



        if ($taxonomy === 'ouinpo_classe') {

            return self::normalize_text('Ressources et contenus OuInPo pour la classe ' . $name . '.', 160);

        }



        return self::normalize_text($name . ' - ' . get_bloginfo('name'), 160);

    }



    public static function get_current_description(): string {

        if (is_singular()) {

            $post = get_queried_object();

            if ($post instanceof \WP_Post) {

                return self::get_description_for_singular($post);

            }

        }



        if (is_tax() || is_category() || is_tag()) {

            $term = get_queried_object();

            if ($term instanceof \WP_Term && in_array($term->taxonomy, self::get_supported_taxonomies(), true)) {

                return self::get_description_for_term($term);

            }

        }



        if (is_home() || is_front_page()) {

            $desc = get_bloginfo('description');

            if (!empty($desc)) {

                return self::normalize_text($desc, 160);

            }

            return self::normalize_text(get_bloginfo('name'), 160);

        }



        if (is_post_type_archive()) {

            $post_type = get_query_var('post_type');

            if (is_array($post_type)) $post_type = reset($post_type);



            if (is_string($post_type) && $post_type !== '') {

                $obj = get_post_type_object($post_type);

                if ($obj && !empty($obj->labels->name)) {

                    return self::normalize_text($obj->labels->name . ' - ' . get_bloginfo('name'), 160);

                }

            }

        }



        if (is_search()) {

            return self::normalize_text('Résultats de recherche pour "' . get_search_query() . '" sur ' . get_bloginfo('name'), 160);

        }



        if (is_author()) {

            $author = get_queried_object();

            if ($author instanceof \WP_User) {

                return self::normalize_text('Articles publiés par ' . $author->display_name . ' - ' . get_bloginfo('name'), 160);

            }

        }



        return self::normalize_text(get_bloginfo('description') ?: get_bloginfo('name'), 160);

    }



    public static function get_current_title(): string {

        if (is_singular()) {

            $post = get_queried_object();

            if ($post instanceof \WP_Post) {

                return self::normalize_text(get_the_title($post), 110);

            }

        }



        if (is_tax() || is_category() || is_tag()) {

            $term = get_queried_object();

            if ($term instanceof \WP_Term) {

                return self::normalize_text($term->name, 110);

            }

        }



        if (is_post_type_archive()) {

            $post_type = get_query_var('post_type');

            if (is_array($post_type)) $post_type = reset($post_type);



            if (is_string($post_type) && $post_type !== '') {

                $obj = get_post_type_object($post_type);

                if ($obj && !empty($obj->labels->name)) {

                    return self::normalize_text($obj->labels->name, 110);

                }

            }

        }



        if (is_search()) {

            return self::normalize_text('Recherche : ' . get_search_query(), 110);

        }



        return self::normalize_text(wp_get_document_title(), 110);

    }



    public static function get_current_url(): string {

        if (is_singular()) {

            $post = get_queried_object();

            if ($post instanceof \WP_Post) {

                return get_permalink($post);

            }

        }



        if (is_tax() || is_category() || is_tag()) {

            $term = get_queried_object();

            if ($term instanceof \WP_Term) {

                $url = get_term_link($term);

                return is_wp_error($url) ? home_url('/') : $url;

            }

        }



        if (is_post_type_archive()) {

            $post_type = get_query_var('post_type');

            if (is_array($post_type)) $post_type = reset($post_type);



            if (is_string($post_type) && $post_type !== '') {

                $url = get_post_type_archive_link($post_type);

                if ($url) return $url;

            }

        }



        global $wp;

        return home_url(add_query_arg([], $wp->request ?? ''));

    }



    public static function get_default_social_image(): string {

        $url = get_option(self::OPTION_DEFAULT_IMAGE, '');

        $url = is_string($url) ? trim($url) : '';



        if ($url !== '') {

            return esc_url_raw($url);

        }



        // Fallback : logo du site si personnalisateur utilisé

        $custom_logo_id = get_theme_mod('custom_logo');

        if ($custom_logo_id) {

            $img = wp_get_attachment_image_url($custom_logo_id, 'full');

            if ($img) return esc_url_raw($img);

        }



        // Dernier fallback : aucune image

        return '';

    }



    public static function get_current_image(): string {

        if (is_singular()) {

            $post = get_queried_object();

            if ($post instanceof \WP_Post && has_post_thumbnail($post)) {

                $img = get_the_post_thumbnail_url($post, 'full');

                if ($img) return esc_url_raw($img);

            }

        }



        return self::get_default_social_image();

    }



    public static function get_og_type(): string {

        if (is_singular()) return 'article';

        return 'website';

    }



    public static function print_meta_tags(): void {

        if (is_admin() || is_feed() || is_robots() || is_trackback()) {

            return;

        }



        $title = self::get_current_title();

        $description = self::get_current_description();

        $url = self::get_current_url();

        $image = self::get_current_image();

        $site_name = get_bloginfo('name');

        $og_type = self::get_og_type();



        if ($description !== '') {

            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";

        }



        echo '<meta property="og:locale" content="fr_FR">' . "\n";

        echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";

        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";

        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";

        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";

        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";



        if ($image !== '') {

            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";

        }



        echo '<meta name="twitter:card" content="' . esc_attr($image !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";

        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";

        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";



        if ($image !== '') {

            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";

        }

    }



    public static function add_settings_page(): void {

        if (defined('OUINPO_SUITE_ADMIN_SLUG')) {

            add_submenu_page(

                OUINPO_SUITE_ADMIN_SLUG,

                'OuInPo Meta & Social',

                'Meta & Social',

                \Ouinpo\Suite\Core\Capabilities::MANAGE_SETTINGS,

                'ouinpo-meta-social',

                [__CLASS__, 'render_settings_page']

            );

            return;

        }

    

        add_options_page(

            'OuInPo Meta & Social',

            'OuInPo Meta & Social',

            \Ouinpo\Suite\Core\Capabilities::MANAGE_SETTINGS,

            'ouinpo-meta-social',

            [__CLASS__, 'render_settings_page']

        );

    }

    public static function register_settings(): void {

        register_setting(

            'ouinpo_meta_social_group',

            self::OPTION_DEFAULT_IMAGE,

            [

                'type' => 'string',

                'sanitize_callback' => 'esc_url_raw',

                'default' => '',

            ]

        );

    }



    public static function render_settings_page(): void {

        if (!\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_SETTINGS)) return;



        ?>

        <div class="wrap">

            <h1>OuInPo Meta &amp; Social</h1>

            <form method="post" action="options.php">

                <?php

                settings_fields('ouinpo_meta_social_group');

                do_settings_sections('ouinpo_meta_social_group');

                ?>

                <table class="form-table" role="presentation">

                    <tr>

                        <th scope="row">

                            <label for="<?php echo esc_attr(self::OPTION_DEFAULT_IMAGE); ?>">Image sociale par défaut</label>

                        </th>

                        <td>

                            <input

                                type="url"

                                class="regular-text"

                                id="<?php echo esc_attr(self::OPTION_DEFAULT_IMAGE); ?>"

                                name="<?php echo esc_attr(self::OPTION_DEFAULT_IMAGE); ?>"

                                value="<?php echo esc_attr(get_option(self::OPTION_DEFAULT_IMAGE, '')); ?>"

                                placeholder="<?php echo esc_attr(home_url('/wp-content/uploads/...')); ?>"

                            />

                            <p class="description">

                                URL d’image utilisée si la page n’a pas d’image mise en avant. Utile pour Discord, X/Twitter et autres aperçus.

                            </p>

                        </td>

                    </tr>

                </table>

                <?php submit_button(); ?>

            </form>

        </div>

        <?php

    }

}



Ouinpo_Meta_Social::init();
