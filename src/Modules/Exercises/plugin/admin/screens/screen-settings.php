<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

class Screen_Settings
{
    private const OPTION_SHOW_STATUS_ACTIONS = 'ouinpo_exo_show_status_actions';
    private const OPTION_PUBLIC_PATHS = 'ouinpo_training_public_paths_enabled';
    private const OPTION_SELF_ENROLMENT = 'ouinpo_training_self_enrolment_enabled';
    private const OPTION_PATH_BADGES = 'ouinpo_training_path_badges_enabled';
    private const OPTION_GLOBAL_STATS = 'ouinpo_training_include_learners_global_stats';
    private const OPTION_RETENTION = 'ouinpo_training_progress_retention';
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
        $public_paths = (int) get_option(self::OPTION_PUBLIC_PATHS, 1) === 1;
        $self_enrolment = (int) get_option(self::OPTION_SELF_ENROLMENT, 1) === 1;
        $path_badges = (int) get_option(self::OPTION_PATH_BADGES, 1) === 1;
        $global_stats = (int) get_option(self::OPTION_GLOBAL_STATS, 0) === 1;
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
                    <tr>
                        <th scope="row">Centre d entrainement</th>
                        <td>
                            <p><label><input type="checkbox" name="public_paths" value="1" <?php checked($public_paths); ?>> Autoriser les parcours publics/autonomes</label></p>
                            <p><label><input type="checkbox" name="self_enrolment" value="1" <?php checked($self_enrolment); ?>> Autoriser l inscription autonome aux parcours publics</label></p>
                            <p><label><input type="checkbox" name="path_badges" value="1" <?php checked($path_badges); ?>> Autoriser les badges obtenus par parcours</label></p>
                            <p><label><input type="checkbox" name="global_stats" value="1" <?php checked($global_stats); ?>> Afficher les apprenants autonomes dans les statistiques globales</label></p>
                            <p>
                                <strong>Conservation progression autonome</strong><br>
                                Jusqu a suppression du compte ou reinitialisation manuelle.
                                <input type="hidden" name="retention" value="account_deletion">
                            </p>
                            <p class="description">Les apprenants autonomes restent exclus des classes, tableaux professeur et clotures scolaires.</p>
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
        update_option(self::OPTION_PUBLIC_PATHS, !empty($_POST['public_paths']) ? 1 : 0, false);
        update_option(self::OPTION_SELF_ENROLMENT, !empty($_POST['self_enrolment']) ? 1 : 0, false);
        update_option(self::OPTION_PATH_BADGES, !empty($_POST['path_badges']) ? 1 : 0, false);
        update_option(self::OPTION_GLOBAL_STATS, !empty($_POST['global_stats']) ? 1 : 0, false);

        update_option(self::OPTION_RETENTION, 'account_deletion', false);

        echo '<div class="notice notice-success is-dismissible"><p>Options enregistrées.</p></div>';
    }
}
