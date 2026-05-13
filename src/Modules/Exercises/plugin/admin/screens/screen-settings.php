<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

class Screen_Settings
{
    private const OPTION_SHOW_STATUS_ACTIONS = 'ouinpo_exo_show_status_actions';
    private const NONCE_ACTION = 'ouinpo_exo_settings_save';
    private const NONCE_NAME   = 'ouinpo_exo_settings_nonce';

    public static function render(): void
    {
        if (!Capabilities::can(Capabilities::MANAGE_SETTINGS)) {
            wp_die('Accès refusé');
        }

        if (
            isset($_SERVER['REQUEST_METHOD']) &&
            strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST'
        ) {
            self::handle_post();
        }

        $show_status_actions = (int) get_option(self::OPTION_SHOW_STATUS_ACTIONS, 0) === 1;
        ?>
        <h1>Options des exercices</h1>

        <form method="post">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Boutons de statut manuel</th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="show_status_actions"
                                    value="1"
                                    <?php checked($show_status_actions); ?>
                                >
                                Afficher les boutons “J’ai tenté” et “J’ai réussi” aux utilisateurs connectés
                            </label>
                            <p class="description">
                                Les visiteurs non connectés ne verront jamais ces boutons.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button('Enregistrer les options'); ?>
        </form>
        <?php
    }

    private static function handle_post(): void
    {
        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $value = !empty($_POST['show_status_actions']) ? 1 : 0;
        update_option(self::OPTION_SHOW_STATUS_ACTIONS, $value, false);

        echo '<div class="notice notice-success is-dismissible"><p>Options enregistrées.</p></div>';
    }
}
