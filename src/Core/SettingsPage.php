<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class SettingsPage
{
    private const OPTION_STYLE_MODE = 'ouinpo_suite_style_mode';
    private const PAGE_SLUG = 'ouinpo-suite-settings';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 30);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_menu(): void
    {
        $parent_slug = defined('OUINPO_SUITE_ADMIN_SLUG')
            ? OUINPO_SUITE_ADMIN_SLUG
            : 'ouinpo-suite';

        add_submenu_page(
            $parent_slug,
            'Réglages OuInPo Suite',
            'Réglages',
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    public static function register_settings(): void
    {
        register_setting(
            'ouinpo_suite_settings',
            self::OPTION_STYLE_MODE,
            [
                'type'              => 'string',
                'sanitize_callback' => [self::class, 'sanitize_style_mode'],
                'default'           => 'ouinpo',
            ]
        );

        add_settings_section(
            'ouinpo_suite_appearance',
            'Apparence',
            function (): void {
                echo '<p>Choisissez le style utilisé par les pages publiques de la suite OuInPo.</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            self::OPTION_STYLE_MODE,
            'Style de l’interface',
            [self::class, 'render_style_mode_field'],
            self::PAGE_SLUG,
            'ouinpo_suite_appearance'
        );
    }

    public static function sanitize_style_mode($value): string
    {
        $value = is_string($value) ? $value : 'ouinpo';

        return in_array($value, ['ouinpo', 'neutral'], true)
            ? $value
            : 'ouinpo';
    }

    public static function render_style_mode_field(): void
    {
        $current = get_option(self::OPTION_STYLE_MODE, 'ouinpo');

        if (!in_array($current, ['ouinpo', 'neutral'], true)) {
            $current = 'ouinpo';
        }

        ?>
        <fieldset>
            <label style="display:block; margin-bottom:10px;">
                <input
                    type="radio"
                    name="<?php echo esc_attr(self::OPTION_STYLE_MODE); ?>"
                    value="ouinpo"
                    <?php checked($current, 'ouinpo'); ?>
                >
                <strong>OuInPo</strong>
                <span style="color:#666;">
                    — style actuel, littéraire et pataphysique.
                </span>
            </label>

            <label style="display:block; margin-bottom:10px;">
                <input
                    type="radio"
                    name="<?php echo esc_attr(self::OPTION_STYLE_MODE); ?>"
                    value="neutral"
                    <?php checked($current, 'neutral'); ?>
                >
                <strong>Sobre</strong>
                <span style="color:#666;">
                    — style clair et neutre, adapté à une version partageable.
                </span>
            </label>

            <p class="description">
                Le style sobre est recommandé pour une installation par d’autres enseignants.
                Le style OuInPo conserve l’apparence actuelle de ton site.
            </p>
        </fieldset>
        <?php
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        ?>
        <div class="wrap">
            <h1>Réglages OuInPo Suite</h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('ouinpo_suite_settings');
                do_settings_sections(self::PAGE_SLUG);
                submit_button('Enregistrer les réglages');
                ?>
            </form>
        </div>
        <?php
    }
}