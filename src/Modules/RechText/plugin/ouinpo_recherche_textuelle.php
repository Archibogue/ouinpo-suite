<?php

// Module interne OuInPo Suite : Recherche textuelle.



if (!defined('ABSPATH')) {

    exit;

}

if (!function_exists('ouinpo_rechtext_enqueue_assets')) {
    function ouinpo_rechtext_enqueue_assets(): void
    {
        $css_rel = 'assets/css/front/rechtext.css';
        $js_rel = 'assets/js/front/rechtext.js';

        $base_dir = defined('OUINPO_SUITE_DIR')
            ? OUINPO_SUITE_DIR
            : dirname(__FILE__, 5) . '/';

        $base_url = defined('OUINPO_SUITE_URL')
            ? OUINPO_SUITE_URL
            : plugin_dir_url(dirname(__FILE__, 5) . '/ouinpo-suite.php');

        $css_path = $base_dir . $css_rel;
        $css_url = $base_url . $css_rel;

        $js_path = $base_dir . $js_rel;
        $js_url = $base_url . $js_rel;

        $css_ver = \Ouinpo\Suite\Core\Assets::fileVersion(
            $css_path,
            defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0'
        );

        $deps = [];

        if (wp_style_is('ouinpo-core-css', 'registered')) {
            $deps[] = 'ouinpo-core-css';
        }

        if (wp_style_is('ouinpo-theme-rechtext-css', 'registered')) {
            $deps[] = 'ouinpo-theme-rechtext-css';
        }

        if ($css_url !== '') {
            wp_enqueue_style(
                'ouinpo-rechtext-css',
                $css_url,
                $deps,
                $css_ver
            );
        }

        if (wp_style_is('ouinpo-theme-css', 'registered')) {
            wp_enqueue_style('ouinpo-theme-css');
        }

        wp_enqueue_script(
            'ouinpo-rechtext-js',
            $js_url,
            [],
            \Ouinpo\Suite\Core\Assets::fileVersion(
                $js_path,
                defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0'
            ),
            true
        );
    }
}

add_shortcode('ouinpo_recherche_textuelle', function ($atts) {
    ouinpo_rechtext_enqueue_assets();
    
    $atts = shortcode_atts([

        'texte' => 'ABAAABCD',

        'motif' => 'ABC',

        'titre' => 'Simulation interactive — recherche textuelle',

    ], $atts, 'ouinpo_recherche_textuelle');



    static $instance = 0;

    $instance++;



    $id = 'ouinpo-rt-' . $instance . '-' . wp_rand(1000, 9999);



    ob_start();

    ?>

    <div class="ouinpo-rt" id="<?php echo esc_attr($id); ?>"

         data-initial-text="<?php echo esc_attr($atts['texte']); ?>"

         data-initial-pattern="<?php echo esc_attr($atts['motif']); ?>">



        <div class="ouinpo-rt__header">

            <h3 class="ouinpo-rt__title"><?php echo esc_html($atts['titre']); ?></h3>

            <p class="ouinpo-rt__subtitle">Compare pas à pas l’algorithme naïf et Boyer-Moore version <em>bad character</em>.</p>

        </div>



        <div class="ouinpo-rt__controls">

            <div class="ouinpo-rt__field">

                <label for="<?php echo esc_attr($id); ?>-text">Texte</label>

                <input id="<?php echo esc_attr($id); ?>-text" type="text" class="ouinpo-rt__input js-rt-text" value="<?php echo esc_attr($atts['texte']); ?>" />

            </div>

            <div class="ouinpo-rt__field">

                <label for="<?php echo esc_attr($id); ?>-pattern">Motif</label>

                <input id="<?php echo esc_attr($id); ?>-pattern" type="text" class="ouinpo-rt__input js-rt-pattern" value="<?php echo esc_attr($atts['motif']); ?>" />

            </div>

            <div class="ouinpo-rt__field ouinpo-rt__field--small">

                <label for="<?php echo esc_attr($id); ?>-speed">Vitesse</label>

                <select id="<?php echo esc_attr($id); ?>-speed" class="ouinpo-rt__input js-rt-speed">

                    <option value="1200">Lente</option>

                    <option value="700" selected>Moyenne</option>

                    <option value="350">Rapide</option>

                </select>

            </div>

        </div>



        <div class="ouinpo-rt__buttons">

            <button type="button" class="js-rt-build">Relancer la simulation</button>

            <button type="button" class="js-rt-prev">◀ Étape précédente</button>

            <button type="button" class="js-rt-next">Étape suivante ▶</button>

            <button type="button" class="js-rt-play">Lecture automatique</button>

            <button type="button" class="js-rt-reset">Réinitialiser</button>

        </div>



        <div class="ouinpo-rt__status js-rt-global-status"></div>



        <div class="ouinpo-rt__panels">

            <section class="ouinpo-rt__panel">

                <h4>Algorithme naïf</h4>

                <div class="ouinpo-rt__stats js-rt-naive-stats"></div>

                <div class="ouinpo-rt__viz js-rt-naive-viz"></div>

                <div class="ouinpo-rt__explain js-rt-naive-explain"></div>

            </section>



            <section class="ouinpo-rt__panel">

                <h4>Boyer-Moore — bad character</h4>

                <div class="ouinpo-rt__stats js-rt-bm-stats"></div>

                <div class="ouinpo-rt__viz js-rt-bm-viz"></div>

                <div class="ouinpo-rt__explain js-rt-bm-explain"></div>

                <div class="ouinpo-rt__table-wrap">

                    <h5>Table bad character</h5>

                    <div class="js-rt-bm-table"></div>

                </div>

            </section>

        </div>

    </div>

    <?php
    return ob_get_clean();

});
