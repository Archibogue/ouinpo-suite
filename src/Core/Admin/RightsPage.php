<?php

namespace Ouinpo\Suite\Core\Admin;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class RightsPage
{
    private const NONCE_ACTION = 'ouinpo_suite_rights';
    private const NONCE_NAME = 'ouinpo_suite_rights_nonce';

    public static function render(): void
    {
        if (!self::userCanManageRights()) {
            wp_die('Accès refusé.');
        }

        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '';

        if ($requestMethod === 'POST') {
            self::handlePost();
        }

        $roles = self::getEditableRoles();
        $capabilityLabels = self::capabilityLabels();
        ?>
        <div class="card ouinpo-suite-card-bounded ouinpo-suite-card-spaced">
            <h2 class="ouinpo-suite-card-title">Droits OuInPo Suite</h2>
            <p class="ouinpo-suite-muted">
                Ces droits contrôlent uniquement les fonctionnalités OuInPo. Les capacités WordPress natives ne sont pas affichées et ne sont jamais modifiées par cette page.
            </p>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="ouinpo_suite_rights_action" value="save_rights">

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th scope="col">Rôle</th>
                            <?php foreach ($capabilityLabels as $capability => $label): ?>
                                <th scope="col">
                                    <span title="<?php echo esc_attr($capability); ?>">
                                        <?php echo esc_html($label); ?>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $roleKey => $role): ?>
                            <?php $isAdministrator = ($roleKey === 'administrator'); ?>
                            <tr>
                                <th scope="row">
                                    <strong><?php echo esc_html(self::roleLabel($roleKey, $role)); ?></strong>
                                    <code><?php echo esc_html($roleKey); ?></code>
                                    <?php if ($isAdministrator): ?>
                                        <p class="description">Le rôle administrator conserve toujours tous les droits OuInPo.</p>
                                    <?php endif; ?>
                                    <input type="hidden" name="ouinpo_suite_roles[]" value="<?php echo esc_attr($roleKey); ?>">
                                </th>
                                <?php foreach ($capabilityLabels as $capability => $label): ?>
                                    <?php $checked = $isAdministrator || self::roleHasCapability($roleKey, $capability); ?>
                                    <td>
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="ouinpo_suite_rights[<?php echo esc_attr($roleKey); ?>][]"
                                                value="<?php echo esc_attr($capability); ?>"
                                                <?php checked($checked); ?>
                                                <?php disabled($isAdministrator); ?>
                                            >
                                            <span class="screen-reader-text">
                                                <?php echo esc_html($label . ' pour ' . self::roleLabel($roleKey, $role)); ?>
                                            </span>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="submit" class="button button-primary">Enregistrer les droits</button>
                </p>
            </form>
        </div>
        <?php
    }

    private static function handlePost(): void
    {
        if (!self::userCanManageRights()) {
            wp_die('Accès refusé.');
        }

        $action = isset($_POST['ouinpo_suite_rights_action'])
            ? sanitize_key(wp_unslash((string) $_POST['ouinpo_suite_rights_action']))
            : '';

        if ($action !== 'save_rights') {
            return;
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $knownRoles = self::getEditableRoles();
        $allowedCapabilities = array_keys(self::capabilityLabels());
        $postedRoles = self::postedRoles(array_keys($knownRoles));
        $postedRights = self::postedRights(array_keys($knownRoles), $allowedCapabilities);

        foreach ($postedRoles as $roleKey) {
            $role = get_role($roleKey);

            if (!$role) {
                continue;
            }

            if ($roleKey === 'administrator') {
                foreach ($allowedCapabilities as $capability) {
                    $role->add_cap($capability);
                }

                continue;
            }

            $roleCapabilities = $postedRights[$roleKey] ?? [];

            foreach ($allowedCapabilities as $capability) {
                if (in_array($capability, $roleCapabilities, true)) {
                    $role->add_cap($capability);
                } else {
                    $role->remove_cap($capability);
                }
            }
        }

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach ($allowedCapabilities as $capability) {
                $administrator->add_cap($capability);
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>Droits OuInPo Suite enregistrés.</p></div>';
    }

    private static function getEditableRoles(): array
    {
        $wpRoles = wp_roles();

        return is_array($wpRoles->roles) ? $wpRoles->roles : [];
    }

    private static function capabilityLabels(): array
    {
        $labels = Capabilities::labels();
        $allowed = array_fill_keys(Capabilities::all(), true);

        return array_intersect_key($labels, $allowed);
    }

    private static function userCanManageRights(): bool
    {
        return current_user_can(Capabilities::MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    private static function postedRoles(array $knownRoles): array
    {
        $rawRoles = isset($_POST['ouinpo_suite_roles']) ? wp_unslash($_POST['ouinpo_suite_roles']) : [];
        $rawRoles = is_array($rawRoles) ? $rawRoles : [];
        $known = array_fill_keys($knownRoles, true);
        $roles = [];

        foreach ($rawRoles as $rawRole) {
            $roleKey = sanitize_key((string) $rawRole);

            if ($roleKey !== '' && isset($known[$roleKey])) {
                $roles[$roleKey] = $roleKey;
            }
        }

        return array_values($roles);
    }

    private static function postedRights(array $knownRoles, array $allowedCapabilities): array
    {
        $rawRights = isset($_POST['ouinpo_suite_rights']) ? wp_unslash($_POST['ouinpo_suite_rights']) : [];
        $rawRights = is_array($rawRights) ? $rawRights : [];
        $known = array_fill_keys($knownRoles, true);
        $allowed = array_fill_keys($allowedCapabilities, true);
        $rights = [];

        foreach ($rawRights as $rawRole => $rawCapabilities) {
            $roleKey = sanitize_key((string) $rawRole);

            if ($roleKey === '' || !isset($known[$roleKey]) || !is_array($rawCapabilities)) {
                continue;
            }

            foreach ($rawCapabilities as $rawCapability) {
                $capability = sanitize_key((string) $rawCapability);

                if (isset($allowed[$capability])) {
                    $rights[$roleKey][$capability] = $capability;
                }
            }
        }

        foreach ($rights as $roleKey => $capabilities) {
            $rights[$roleKey] = array_values($capabilities);
        }

        return $rights;
    }

    private static function roleHasCapability(string $roleKey, string $capability): bool
    {
        $role = get_role($roleKey);

        return $role ? (bool) $role->has_cap($capability) : false;
    }

    private static function roleLabel(string $roleKey, array $role): string
    {
        $name = isset($role['name']) && is_string($role['name']) ? $role['name'] : $roleKey;

        return translate_user_role($name);
    }
}
