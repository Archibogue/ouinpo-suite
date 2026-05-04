<?php

// Module interne OuInPo Suite : SegFault Notifier.



if (!defined('ABSPATH')) exit;



class OuInPo_Segfault_Notifier {

    const VERSION = '1.5.5';



    public function __construct() {

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

    }



    public function enqueue_assets() {

            if (!is_user_logged_in()) {

                return;

            }



        // CSS badge

        $css_rel = 'assets/css/front/segfault-notifier.css';

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
                'ouinpo-segfault-notifier',
                $css_url,
                $deps,
                $css_ver
            );
        }



        // JS

        $js_rel = 'assets/js/front/segfault-notifier.js';
        $fallback_root = dirname(__DIR__, 5);

        $js_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL . $js_rel
            : plugin_dir_url($fallback_root . '/ouinpo-suite.php') . $js_rel;

        $js_path = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR . $js_rel
            : trailingslashit($fallback_root) . $js_rel;

        $js_ver = file_exists($js_path)
            ? (string) filemtime($js_path)
            : self::VERSION;

        wp_enqueue_script(
            'ouinpo-segfault-notify',
            $js_url,
            array(),
            $js_ver,
            true
        );



        /**

         * 🔎 BADGES : on regarde dans {prefix}ouin_exo_user_badges ce qui existe

         * depuis la dernière fois où on a affiché quelque chose.

         */

        $badges_payload = array(

            'count'  => 0,

            'badges' => array(),

        );



        $user_id = get_current_user_id();

        if ($user_id) {

            global $wpdb;

            $t_user_badges = $wpdb->prefix . 'ouin_exo_user_badges';

            $t_badges      = $wpdb->prefix . 'ouin_exo_badges';



            $last_seen = get_user_meta($user_id, 'ouinpo_badges_seen_at', true);



            // Première fois : on remonte très loin dans le temps pour capter les badges déjà là

            if (empty($last_seen)) {

                $last_seen = '1970-01-01 00:00:00';

            }



            $rows = $wpdb->get_results(

                $wpdb->prepare(

                    "SELECT ub.badge_id, ub.awarded_at, b.slug, b.title, b.description

                       FROM $t_user_badges ub

                       JOIN $t_badges b ON b.id = ub.badge_id

                      WHERE ub.user_id = %d

                        AND ub.awarded_at > %s

                      ORDER BY ub.awarded_at DESC

                      LIMIT 10",

                    $user_id,

                    $last_seen

                ),

                ARRAY_A

            );



            if (!empty($rows)) {

                foreach ($rows as $r) {

                    $badges_payload['badges'][] = array(

                        'id'          => (int) $r['badge_id'],

                        'slug'        => $r['slug'],

                        'title'       => $r['title'],

                        'description' => $r['description'],

                        'awarded_at'  => $r['awarded_at'],

                    );

                }

                $badges_payload['count'] = count($badges_payload['badges']);



                // On n’avance le curseur QUE s’il y a eu de nouveaux badges

                update_user_meta($user_id, 'ouinpo_badges_seen_at', current_time('mysql'));

            }

        }



        // Exposer au JS

        wp_localize_script('ouinpo-segfault-notify', 'OuInPoBadges', $badges_payload);




    }

}



new OuInPo_Segfault_Notifier();
