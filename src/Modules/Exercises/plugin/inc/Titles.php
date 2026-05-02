<?php

namespace Ouinpo\Exercises;



if (!defined('ABSPATH')) exit;



class Titles {



    /**

     * À appeler depuis le bootstrap du plugin

     */

    public static function init() {

        // Filtre Ultimate Member : modifier le display name

        add_filter('um_user_display_name_filter', [__CLASS__, 'filter_um_display_name'], 10, 3);



        // Shortcode pour la page "Choisir mon titre"

        add_shortcode('ouinpo_title_selector', [__CLASS__, 'render_title_selector']);

    }



    /**

     * Récupérer le titre de badge choisi par un utilisateur

     */

    public static function get_user_badge_title($user_id) {

        $badge_id = (int) get_user_meta($user_id, 'ouinpo_title_badge_id', true);

        if (!$badge_id) {

            return null;

        }



        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_badges';



        $title = $wpdb->get_var(

            $wpdb->prepare("SELECT title FROM $table WHERE id = %d", $badge_id)

        );



        return $title ?: null;

    }



    /**

     * 🔐 Vérifier que l’utilisateur possède vraiment ce badge

     * Implémentation réelle basée sur {prefix}_exo_user_badges

     */

    protected static function user_has_badge($user_id, $badge_id) {

        $user_id  = (int) $user_id;

        $badge_id = (int) $badge_id;



        if ($user_id <= 0 || $badge_id <= 0) {

            return false;

        }



        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_user_badges';



        $exists = $wpdb->get_var(

            $wpdb->prepare(

                "SELECT 1 FROM $table WHERE user_id = %d AND badge_id = %d LIMIT 1",

                $user_id,

                $badge_id

            )

        );



        return (bool) $exists;

    }



    /**

     * Filtre Ultimate Member : on ajoute le titre après le display name

     */

    public static function filter_um_display_name($name, $user_id, $attrs) {

        $title = self::get_user_badge_title($user_id);

        if (!$title) {

            return $name;

        }



        // Format : "Pr. Archibogue — Transcendant Satrape du Calcul ..."

        return $name . ' — ' . $title;

    }



    /**

     * Shortcode [ouinpo_title_selector]

     * Page où l’utilisateur peut choisir son titre Ouinpien

     */

    public static function render_title_selector() {

        if (!is_user_logged_in()) {

            return '<p>Vous devez être connecté·e pour choisir un titre.</p>';

        }



        $user_id = get_current_user_id();



        // Gestion du POST (choix du titre)

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ouinpo_choose_title'])) {

            check_admin_referer('ouinpo_choose_title_action', 'ouinpo_choose_title_nonce');



            $badge_id = isset($_POST['badge_id']) ? (int) $_POST['badge_id'] : 0;

            if ($badge_id > 0 && self::user_has_badge($user_id, $badge_id)) {

                update_user_meta($user_id, 'ouinpo_title_badge_id', $badge_id);

                echo '<div class="notice notice-success"><p>Votre titre Ouinpien a été mis à jour.</p></div>';

            } else {

                echo '<div class="notice notice-error"><p>Ce titre ne peut pas être choisi pour le moment.</p></div>';

            }

        }



        // Récupérer le titre actuel

        $current_title = self::get_user_badge_title($user_id);



        // Récupérer uniquement les badges obtenus par l’utilisateur

        global $wpdb;

        $t_badges      = $wpdb->prefix . 'ouin_exo_badges';

        $t_user_badges = $wpdb->prefix . 'ouin_exo_user_badges';



        $badges = $wpdb->get_results(

            $wpdb->prepare(

                "SELECT b.id, b.slug, b.title, b.description, b.theme

                 FROM $t_badges b

                 INNER JOIN $t_user_badges ub ON ub.badge_id = b.id

                 WHERE ub.user_id = %d

                 ORDER BY b.theme, b.title",

                $user_id

            )

        );



        ob_start();

        ?>

        <div class="ouinpo-title-selector">

            <h2>Votre titre Ouinpien</h2>



            <?php if ($current_title): ?>

                <p><strong>Titre actuel :</strong> <?php echo esc_html($current_title); ?></p>

            <?php else: ?>

                <p>Vous n'avez pas encore choisi de titre Ouinpien.</p>

            <?php endif; ?>



            <h3>Choisir un titre parmi vos badges</h3>



            <?php if (!$badges): ?>

                <p>Vous n'avez pas encore obtenu de badge permettant de choisir un titre affiché.  

                Continuez vos exploits ouinpiesques pour en débloquer !</p>

            <?php else: ?>

                <div class="ouinpo-badges-grid">

                    <?php foreach ($badges as $badge): ?>

                        <div class="ouinpo-badge-card">

                            <h4><?php echo esc_html($badge->title); ?></h4>

                            <?php if (!empty($badge->description)): ?>

                                <p class="ouinpo-badge-desc">

                                    <?php echo esc_html($badge->description); ?>

                                </p>

                            <?php endif; ?>

                            <form method="post">

                                <?php wp_nonce_field('ouinpo_choose_title_action', 'ouinpo_choose_title_nonce'); ?>

                                <input type="hidden" name="badge_id" value="<?php echo (int) $badge->id; ?>">

                                <button type="submit" name="ouinpo_choose_title">

                                    Utiliser ce titre

                                </button>

                            </form>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

        <style>

            .ouinpo-badges-grid {

                display: grid;

                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));

                gap: 1rem;

                margin-top: 1rem;

            }

            .ouinpo-badge-card {

                border: 1px solid rgba(255,255,255,0.2);

                padding: 0.75rem 1rem;

                background: rgba(0,0,0,0.15);

            }

            .ouinpo-badge-card h4 {

                margin-top: 0;

                margin-bottom: 0.5rem;

            }

            .ouinpo-badge-card button {

                margin-top: 0.5rem;

            }

        </style>

        <?php

        return ob_get_clean();

    }

}

