<?php
namespace Ouinpo\Exercises;

use Ouinpo\Suite\Core\Capabilities;

if (!defined('ABSPATH')) exit;

final class RevisionBand
{
    const META_INTRO   = '_ouinpo_revision_intro';
    const META_PREREQ  = '_ouinpo_revision_prereq';
    const META_RETENIR = '_ouinpo_revision_retenir';
    const META_SAVOIR  = '_ouinpo_revision_savoir';

    public static function init(): void
    {
        self::register_meta();

        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_post']);
    }

    public static function register_meta(): void
    {
        $post_types = ['post', 'page'];

        foreach ($post_types as $post_type) {
            register_post_meta($post_type, self::META_INTRO, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => [__CLASS__, 'sanitize_text'],
                'auth_callback'     => [__CLASS__, 'can_edit_post_meta'],
            ]);

            register_post_meta($post_type, self::META_PREREQ, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => [__CLASS__, 'sanitize_textarea'],
                'auth_callback'     => [__CLASS__, 'can_edit_post_meta'],
            ]);

            register_post_meta($post_type, self::META_RETENIR, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => [__CLASS__, 'sanitize_textarea'],
                'auth_callback'     => [__CLASS__, 'can_edit_post_meta'],
            ]);

            register_post_meta($post_type, self::META_SAVOIR, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => [__CLASS__, 'sanitize_textarea'],
                'auth_callback'     => [__CLASS__, 'can_edit_post_meta'],
            ]);
        }
    }

    public static function can_edit_post_meta(): bool
    {
        return Capabilities::can(Capabilities::MANAGE_EXERCISES);
    }

    public static function sanitize_text($value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function sanitize_textarea($value): string
    {
        $value = (string) $value;
        $value = wp_kses_post($value);
        $value = preg_replace("/\r\n|\r/", "\n", $value);
        return trim($value);
    }

    public static function register_meta_boxes(): void
    {
        foreach (['post', 'page'] as $post_type) {
            add_meta_box(
                'ouinpo_revision_band',
                'Repères de révision OuInPo',
                [__CLASS__, 'render_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    public static function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('ouinpo_revision_band_save', 'ouinpo_revision_band_nonce');

        $meta = self::get_meta($post->ID);
        ?>
        <p>
            <label for="ouinpo_revision_intro"><strong>Phrase d’accroche courte</strong></label><br>
            <input
                type="text"
                id="ouinpo_revision_intro"
                name="ouinpo_revision_intro"
                value="<?php echo esc_attr($meta['intro']); ?>"
                class="widefat"
                placeholder="Ex. : Cette page consolide les bases du Web côté client."
            >
        </p>

        <p>
            <label for="ouinpo_revision_prereq"><strong>Prérequis</strong></label><br>
            <textarea
                id="ouinpo_revision_prereq"
                name="ouinpo_revision_prereq"
                class="widefat"
                rows="4"
                placeholder="Une ligne = un prérequis&#10;Ex. : variables&#10;conditions&#10;fonctions"
            ><?php echo esc_textarea($meta['prereq_raw']); ?></textarea>
        </p>

        <p>
            <label for="ouinpo_revision_retenir"><strong>À retenir</strong></label><br>
            <textarea
                id="ouinpo_revision_retenir"
                name="ouinpo_revision_retenir"
                class="widefat"
                rows="3"
                placeholder="Ex. : Une page Web combine structure, présentation et échanges réseau."
            ><?php echo esc_textarea($meta['retenir']); ?></textarea>
        </p>

        <p>
            <label for="ouinpo_revision_savoir"><strong>Tu vas apprendre à…</strong></label><br>
            <textarea
                id="ouinpo_revision_savoir"
                name="ouinpo_revision_savoir"
                class="widefat"
                rows="5"
                placeholder="Une ligne = un savoir-faire&#10;Ex. : identifier une URL&#10;distinguer HTML et CSS&#10;repérer une requête HTTP"
            ><?php echo esc_textarea($meta['savoir_raw']); ?></textarea>
        </p>

        <p class="description">
            Place ensuite le shortcode <code>[ouinpo_revision_band]</code> dans le contenu du cours, juste sous le titre.
            Le niveau, le thème, les compétences BO liées et quelques exercices liés seront déduits automatiquement si la page
            est déjà reliée à des compétences.
        </p>
        <?php
    }

    public static function save_post(int $post_id): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (wp_is_post_autosave($post_id)) return;

        if (!isset($_POST['ouinpo_revision_band_nonce'])) return;
        if (!wp_verify_nonce($_POST['ouinpo_revision_band_nonce'], 'ouinpo_revision_band_save')) return;

        if (!current_user_can('edit_post', $post_id)) return;

        $intro   = isset($_POST['ouinpo_revision_intro'])   ? self::sanitize_text($_POST['ouinpo_revision_intro']) : '';
        $prereq  = isset($_POST['ouinpo_revision_prereq'])  ? self::sanitize_textarea($_POST['ouinpo_revision_prereq']) : '';
        $retenir = isset($_POST['ouinpo_revision_retenir']) ? self::sanitize_textarea($_POST['ouinpo_revision_retenir']) : '';
        $savoir  = isset($_POST['ouinpo_revision_savoir'])  ? self::sanitize_textarea($_POST['ouinpo_revision_savoir']) : '';

        self::save_meta_value($post_id, self::META_INTRO, $intro);
        self::save_meta_value($post_id, self::META_PREREQ, $prereq);
        self::save_meta_value($post_id, self::META_RETENIR, $retenir);
        self::save_meta_value($post_id, self::META_SAVOIR, $savoir);
    }

    private static function save_meta_value(int $post_id, string $key, string $value): void
    {
        if ($value === '') {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }

    public static function get_meta(int $post_id): array
    {
        $intro   = (string) get_post_meta($post_id, self::META_INTRO, true);
        $prereq  = (string) get_post_meta($post_id, self::META_PREREQ, true);
        $retenir = (string) get_post_meta($post_id, self::META_RETENIR, true);
        $savoir  = (string) get_post_meta($post_id, self::META_SAVOIR, true);

        return [
            'intro'      => $intro,
            'prereq_raw' => $prereq,
            'retenir'    => $retenir,
            'savoir_raw' => $savoir,
            'prereq'     => self::lines_from_text($prereq),
            'savoir'     => self::lines_from_text($savoir),
        ];
    }

    public static function get_front_payload(int $post_id): array
    {
        $meta = self::get_meta($post_id);
        $competencies = self::get_competencies_for_post($post_id);
        $exercises = self::get_related_exercises($competencies, 3);

        return [
            'intro'        => $meta['intro'],
            'prereq'       => $meta['prereq'],
            'retenir'      => $meta['retenir'],
            'savoir'       => $meta['savoir'],
            'level_label'  => self::compute_level_label($competencies),
            'theme_label'  => self::compute_theme_label($competencies),
            'competencies' => $competencies,
            'exercises'    => $exercises,
            'path_url' => self::build_path_creation_url($competencies),
        ];
    }

    public static function has_visible_content(array $payload): bool
    {
        return !empty($payload['intro'])
            || !empty($payload['prereq'])
            || !empty($payload['retenir'])
            || !empty($payload['savoir'])
            || !empty($payload['level_label'])
            || !empty($payload['theme_label'])
            || !empty($payload['competencies'])
            || !empty($payload['exercises']);
    }

    private static function lines_from_text(string $text): array
    {
        if ($text === '') return [];

        $lines = preg_split("/\r\n|\r|\n/", $text);
        $lines = array_map('trim', (array) $lines);
        $lines = array_filter($lines, static function ($line) {
            return $line !== '';
        });

        return array_values(array_unique($lines));
    }

    private static function get_competencies_for_post(int $post_id): array
    {
        global $wpdb;

        $tblLink = $wpdb->prefix . 'ouin_exo_post_competency';
        $tblComp = $wpdb->prefix . 'ouin_exo_competencies';

        $sql = $wpdb->prepare("
            SELECT
                c.id,
                c.domain,
                c.domain_slug,
                c.track,
                c.level,
                c.label,
                c.slug,
                c.competency
            FROM {$tblLink} pc
            INNER JOIN {$tblComp} c ON c.id = pc.competency_id
            WHERE pc.post_id = %d
              AND c.active = 1
            ORDER BY
              CASE c.track
                WHEN 'SNT' THEN 0
                WHEN 'NSI' THEN 1
                ELSE 9
              END,
              CASE c.level
                WHEN 'Seconde' THEN 0
                WHEN 'Première' THEN 1
                WHEN 'Terminale' THEN 2
                WHEN 'Transversal' THEN 3
                ELSE 9
              END,
              c.domain ASC,
              c.id ASC
        ", $post_id);

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!$rows) return [];

        return array_map(static function ($row) {
            return [
                'id'     => (int) $row['id'],
                'domain' => (string) $row['domain'],
                'domain_slug' => (string) $row['domain_slug'],
                'track'  => (string) $row['track'],
                'level'  => (string) $row['level'],
                'label'  => (string) $row['label'],
                'slug'   => (string) $row['slug'],
                'competency' => (string) $row['competency'],
            ];
        }, $rows);
    }

    private static function get_related_exercises(array $competencies, int $limit = 3): array
    {
        global $wpdb;

        if (empty($competencies)) return [];

        $competency_ids = array_values(array_unique(array_map(static function ($c) {
            return (int) $c['id'];
        }, $competencies)));

        if (empty($competency_ids)) return [];

        $tblEx   = $wpdb->prefix . 'ouin_exo_exercises';
        $tblMap  = $wpdb->prefix . 'ouin_exo_exercise_competency';
        $tblExam = $wpdb->prefix . 'ouin_exo_exam_meta';

        $ids_sql = implode(',', $competency_ids);

        $sql = "
            SELECT DISTINCT e.id, e.title, e.slug
            FROM {$tblEx} e
            INNER JOIN {$tblMap} ec ON ec.exercise_id = e.id
            LEFT JOIN {$tblExam} em ON em.exercise_id = e.id
            WHERE ec.competency_id IN ({$ids_sql})
              AND e.is_active = 1
              AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')
            ORDER BY e.title ASC
            LIMIT " . (int) $limit;

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!$rows) return [];

        $base_url = self::get_exercises_page_url();

        return array_map(static function ($row) use ($base_url) {
            $id = (int) $row['id'];
            return [
                'id'    => $id,
                'title' => (string) $row['title'],
                'slug'  => (string) $row['slug'],
                'url'   => add_query_arg('exo', $id, $base_url),
            ];
        }, $rows);
    }

    private static function get_exercises_page_url(): string
    {
        return home_url('/exercice/');
    }

    private static function compute_level_label(array $competencies): string
    {
        if (empty($competencies)) return '';

        $tracks = array_values(array_unique(array_map(static function ($c) {
            return (string) $c['track'];
        }, $competencies)));

        $levels = array_values(array_unique(array_map(static function ($c) {
            return (string) $c['level'];
        }, $competencies)));

        $track = $tracks[0] ?? '';
        $level = $levels[0] ?? '';

        if (count($tracks) === 1 && count($levels) === 1) {
            if ($track === 'NSI') return $level . ' NSI';
            if ($track === 'SNT') return $level . ' — SNT';
            return $level;
        }

        if (count($levels) === 1) {
            return $levels[0];
        }

        return 'Plusieurs niveaux';
    }

    private static function compute_theme_label(array $competencies): string
    {
        if (empty($competencies)) return '';

        $domains = array_values(array_unique(array_map(static function ($c) {
            return trim((string) $c['domain']);
        }, $competencies)));

        $domains = array_values(array_filter($domains, static function ($d) {
            return $d !== '';
        }));

        if (empty($domains)) return '';
        if (count($domains) === 1) return $domains[0];
        if (count($domains) === 2) return $domains[0] . ' · ' . $domains[1];

        return 'Plusieurs domaines';
    }
    
private static function build_path_creation_url(array $competencies): string
{
    if (empty($competencies)) {
        return '';
    }

    $first = $competencies[0];

    $domain_value = '';
    if (!empty($first['domain_slug'])) {
        $domain_value = (string) $first['domain_slug'];
    } elseif (!empty($first['domain'])) {
        $domain_value = (string) $first['domain'];
    }

    $competency_id = !empty($first['id']) ? (int) $first['id'] : 0;

    if ($domain_value === '' || $competency_id <= 0) {
        return '';
    }

    return add_query_arg([
        'sf_domain'  => $domain_value,
        'sf_comp_id' => $competency_id,
    ], home_url('/parcours/'));
}
}
