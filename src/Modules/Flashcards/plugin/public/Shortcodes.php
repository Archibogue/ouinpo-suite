<?php

namespace Ouinpo\Flashcards;

defined('ABSPATH') || exit;

final class Shortcodes
{
    public static function init(): void
    {
        add_shortcode('ouinpo_flashcards', [__CLASS__, 'render_app']);
        add_shortcode('ouinpo-flashcards', [__CLASS__, 'render_app']);
    }

    public static function render_app($atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<div class="ouinpo-fc-login">Connecte-toi pour accéder à tes cartes de révision.</div>';
        }

        $atts = shortcode_atts([
            'deck'   => '',
            'track'  => '',
            'level'  => '',
            'domain' => '',
            'title'  => 'Mes cartes du jour',
        ], $atts, 'ouinpo_flashcards');

        wp_enqueue_style('ouinpo-flashcards');
        if (wp_style_is('ouinpo-theme-css', 'registered')) {
            wp_enqueue_style('ouinpo-theme-css');
        }
        wp_enqueue_script('ouinpo-flashcards');

        $config = [
            'api'    => esc_url_raw(rest_url('ouinpo/v1/flashcards')),
            'nonce'  => wp_create_nonce('wp_rest'),
            'deck'   => sanitize_text_field((string) $atts['deck']),
            'track'  => sanitize_text_field((string) $atts['track']),
            'level'  => sanitize_text_field((string) $atts['level']),
            'domain' => sanitize_text_field((string) $atts['domain']),
            'labels' => [
                'again' => 'Encore fragile',
                'hard'  => 'Presque su',
                'good'  => 'Su',
            ],
        ];

        ob_start();
        ?>

        <script>
            window.OUINPO_FLASHCARDS = <?php echo wp_json_encode($config); ?>;
        </script>

        <div class="ouinpo-fc-app ouinpo-fc-app-v2"
             data-default-deck="<?php echo esc_attr((string) $atts['deck']); ?>"
             data-default-domain="<?php echo esc_attr((string) $atts['domain']); ?>">

            <div class="ouinpo-fc-header">
                <h2><?php echo esc_html((string) $atts['title']); ?></h2>
                <p class="ouinpo-fc-subtitle">Des cartes courtes pour fixer les notions vues en cours, sans remplacer les exercices.</p>
            </div>

            <section class="ouinpo-fc-session" aria-label="Révision en cours" hidden>
                <div class="ouinpo-fc-session-head">
                    <div>
                        <h3>Révision en cours</h3>
                        <p class="ouinpo-fc-session-summary" aria-live="polite">La première carte arrive.</p>
                    </div>
                    <button type="button" class="button button-secondary ouinpo-fc-edit-selection">Modifier la sélection</button>
                </div>

                <div class="ouinpo-fc-kpis ouinpo-fc-kpis-session" aria-label="Indicateurs de la session">
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="active_decks">0</span>
                        <span class="ouinpo-fc-kpi-label">paquets</span>
                    </div>
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="total_cards">0</span>
                        <span class="ouinpo-fc-kpi-label">cartes</span>
                    </div>
                    <div class="ouinpo-fc-kpi ouinpo-fc-kpi-wide">
                        <span class="ouinpo-fc-kpi-value" data-kpi="mastered_ratio">0 / 0</span>
                        <span class="ouinpo-fc-kpi-label">mémorisées</span>
                    </div>
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="progress_pct">0%</span>
                        <span class="ouinpo-fc-kpi-label">progression</span>
                    </div>
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="due_cards" data-count="due">0</span>
                        <span class="ouinpo-fc-kpi-label">à revoir</span>
                    </div>
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="new_cards" data-count="new">0</span>
                        <span class="ouinpo-fc-kpi-label">nouvelles</span>
                    </div>
                </div>

                <div class="ouinpo-fc-card is-empty">
                    <div class="ouinpo-fc-meta"></div>
                    <div class="ouinpo-fc-front">Clique sur “Commencer la révision”.</div>
                    <div class="ouinpo-fc-back" hidden></div>
                </div>

                <div class="ouinpo-fc-actions">
                    <button type="button" class="button button-secondary ouinpo-fc-reveal">🕯️ Révéler la réponse</button>
                    <div class="ouinpo-fc-grade-actions" hidden>
                        <button type="button" class="button ouinpo-fc-grade" data-grade="again">🔴 Encore fragile</button>
                        <button type="button" class="button ouinpo-fc-grade" data-grade="hard">🟠 Presque su</button>
                        <button type="button" class="button button-primary ouinpo-fc-grade" data-grade="good">🟢 Su</button>
                    </div>
                </div>
            </section>

            <section class="ouinpo-fc-prep" aria-label="Préparer la révision">
                <div class="ouinpo-fc-prep-head">
                    <div>
                        <h3>Préparer ma révision</h3>
                        <p class="ouinpo-fc-prep-text">Choisis un domaine ou laisse “Tous les domaines vus”, puis lance une session. Les paquets cochés limitent la révision ; sans coche, tout le domaine est utilisé.</p>
                    </div>
                    <div class="ouinpo-fc-prep-actions">
                        <button type="button" class="button button-primary ouinpo-fc-start-session">Commencer la révision</button>
                        <button type="button" class="button button-secondary ouinpo-fc-start-all">Tout ce qui est vu</button>
                    </div>
                </div>

                <div class="ouinpo-fc-toolbar">
                    <label>
                        <span>Domaine</span>
                        <select class="ouinpo-fc-domain-select">
                            <option value="">Tous les domaines vus</option>
                        </select>
                    </label>
                    <p class="ouinpo-fc-context-summary" aria-live="polite">Chargement des paquets disponibles…</p>
                </div>

                <div class="ouinpo-fc-kpis" aria-label="Indicateurs de révision">
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="active_decks">0</span>
                        <span class="ouinpo-fc-kpi-label">paquets utilisés</span>
                    </div>
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="total_cards">0</span>
                        <span class="ouinpo-fc-kpi-label">cartes disponibles</span>
                    </div>
                    <div class="ouinpo-fc-kpi ouinpo-fc-kpi-wide">
                        <span class="ouinpo-fc-kpi-value" data-kpi="mastered_ratio">0 / 0</span>
                        <span class="ouinpo-fc-kpi-label">mémorisées</span>
                    </div>
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="progress_pct">0%</span>
                        <span class="ouinpo-fc-kpi-label">progression</span>
                    </div>
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="due_cards" data-count="due">0</span>
                        <span class="ouinpo-fc-kpi-label">à revoir</span>
                    </div>
                    <div class="ouinpo-fc-kpi">
                        <span class="ouinpo-fc-kpi-value" data-kpi="new_cards" data-count="new">0</span>
                        <span class="ouinpo-fc-kpi-label">nouvelles</span>
                    </div>
                </div>

                <details class="ouinpo-fc-chooser" open>
                    <summary>Personnaliser par paquets</summary>

                    <div class="ouinpo-fc-deck-tools">
                        <div>
                            <strong>Paquets disponibles</strong>
                            <p>Coche uniquement les paquets voulus. Si rien n’est coché, toute la sélection visible est révisée.</p>
                        </div>
                        <div class="ouinpo-fc-deck-tool-buttons">
                            <button type="button" class="button button-secondary ouinpo-fc-select-all">Tout cocher</button>
                            <button type="button" class="button button-secondary ouinpo-fc-clear-selection">Tout le domaine</button>
                        </div>
                    </div>

                    <div class="ouinpo-fc-decks"></div>
                </details>
            </section>

            <div class="ouinpo-fc-feedback" aria-live="polite"></div>
        </div>

        <?php
        return (string) ob_get_clean();
    }
}