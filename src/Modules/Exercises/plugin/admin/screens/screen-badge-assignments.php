<?php
/**
 * Admin screen: Manual badge assignments
 */
defined('ABSPATH') || exit;

global $wpdb;

$tbl_badges      = $wpdb->prefix . 'ouin_exo_badges';
$tbl_user_badges = $wpdb->prefix . 'ouin_exo_user_badges';
$tbl_groups      = $wpdb->prefix . 'ouin_exo_groups';
$tbl_members     = $wpdb->prefix . 'ouin_exo_group_members';

$badge_id = isset($_REQUEST['badge_id']) ? intval($_REQUEST['badge_id']) : 0;
$group_id = isset($_REQUEST['group_id']) ? intval($_REQUEST['group_id']) : 0;
$search   = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
$badge_level_filter = isset($_REQUEST['badge_level']) ? sanitize_key(wp_unslash($_REQUEST['badge_level'])) : '';

$badge_level_options = [
    ''             => 'Toutes les catégories',
    'transversal'  => 'Transversale',
    'seconde'      => 'Seconde',
    'premiere'     => 'Première',
    'terminale'    => 'Terminale',
    'special'      => 'Spécial',
];

if (!array_key_exists($badge_level_filter, $badge_level_options)) {
    $badge_level_filter = '';
}


// -------------------------
// Traitement POST
// -------------------------
if (!empty($_POST) && check_admin_referer('ouinpo_badge_assign_form', 'ouinpo_badge_assign_nonce')) {
    $badge_id = intval($_POST['badge_id'] ?? 0);
    $group_id = intval($_POST['group_id'] ?? 0);
    $search   = sanitize_text_field(wp_unslash($_POST['s'] ?? ''));
    $action   = sanitize_key($_POST['bulk_action'] ?? '');
    $badge_level_filter = sanitize_key(wp_unslash($_POST['badge_level'] ?? ''));
    
    if (!array_key_exists($badge_level_filter, $badge_level_options)) {
        $badge_level_filter = '';
    }

    $selected_users = isset($_POST['user_ids']) && is_array($_POST['user_ids'])
        ? array_map('intval', $_POST['user_ids'])
        : [];

    $selected_users = array_values(array_filter($selected_users));

    if ($badge_id <= 0) {
        add_settings_error('ouinpo_badge_assign', 'no_badge', 'Choisis d’abord un badge.', 'error');
    } elseif (empty($selected_users)) {
        add_settings_error('ouinpo_badge_assign', 'no_users', 'Sélectionne au moins un élève.', 'error');
    } else {
        $inserted = 0;
        $deleted  = 0;
        $skipped  = 0;

        foreach ($selected_users as $user_id) {
            if ($action === 'assign_manual') {
                
                if (
                    class_exists(\Ouinpo\Exercises\BadgeEngine::class)
                    && !\Ouinpo\Exercises\BadgeEngine::can_user_receive_badge($user_id, $badge_id, 'manual')
                ) {
                    $skipped++;
                    continue;
                }                
                
                $existing_source = $wpdb->get_var($wpdb->prepare(
                    "SELECT source
                     FROM {$tbl_user_badges}
                     WHERE user_id = %d AND badge_id = %d
                     LIMIT 1",
                    $user_id,
                    $badge_id
                ));

                if ($existing_source) {
                    $skipped++;
                    continue;
                }

                $ok = $wpdb->insert(
                    $tbl_user_badges,
                    [
                        'user_id'    => $user_id,
                        'badge_id'   => $badge_id,
                        'awarded_at' => current_time('mysql'),
                        'source'     => 'manual',
                    ],
                    ['%d', '%d', '%s', '%s']
                );

                if ($ok) {
                    $inserted++;

                    $pending = get_user_meta($user_id, 'ouinpo_new_badges', true);
                    if (!is_array($pending)) {
                        $pending = [];
                    }

                    $pending[] = [
                        'badge_id'   => $badge_id,
                        'awarded_at' => current_time('mysql'),
                        'source'     => 'manual',
                    ];

                    update_user_meta($user_id, 'ouinpo_new_badges', $pending);

                    do_action('ouinpo_exo_badge_awarded', $user_id, $badge_id, 'manual');
                }
            }

            if ($action === 'remove_manual') {
                $ok = $wpdb->delete(
                    $tbl_user_badges,
                    [
                        'user_id'  => $user_id,
                        'badge_id' => $badge_id,
                        'source'   => 'manual',
                    ],
                    ['%d', '%d', '%s']
                );

                if ($ok) {
                    $deleted++;
                } else {
                    $skipped++;
                }
            }
        }

        if ($action === 'assign_manual') {
            add_settings_error(
                'ouinpo_badge_assign',
                'assigned',
                sprintf(
                    'Attribution terminée : %d ajouté(s), %d ignoré(s) car déjà possédé(s).',
                    $inserted,
                    $skipped
                ),
                'updated'
            );
        }

        if ($action === 'remove_manual') {
            add_settings_error(
                'ouinpo_badge_assign',
                'removed',
                sprintf(
                    'Retrait manuel terminé : %d supprimé(s), %d ignoré(s).',
                    $deleted,
                    $skipped
                ),
                'updated'
            );
        }
    }
}

// -------------------------
// Données de référence
// -------------------------

// Libellés lisibles pour les thèmes de badges
$badge_theme_labels = [
    'Meta-Seconde'   => 'Méta Seconde',
    'Meta-Première'  => 'Méta Première',
    'Meta-Terminale' => 'Méta Terminale',
    'special'        => 'Spécial',
];

// Libellés des domaines BO (theme = domain_slug)
$domain_rows = $wpdb->get_results("
    SELECT DISTINCT domain_slug, domain
    FROM {$wpdb->prefix}ouin_exo_competencies
    WHERE domain_slug IS NOT NULL
      AND domain_slug <> ''
      AND active = 1
    ORDER BY domain ASC
");

if ($domain_rows) {
    foreach ($domain_rows as $row) {
        $slug = (string) $row->domain_slug;
        $label = (string) $row->domain;
        if ($slug !== '' && $label !== '') {
            $badge_theme_labels[$slug] = $label;
        }
    }
}

$level_map = [
    'transversal' => 'Transversal',
    'seconde'     => 'Seconde',
    'premiere'    => 'Première',
    'terminale'   => 'Terminale',
];

if ($badge_level_filter === 'special') {
    $badges = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, slug, theme
         FROM {$tbl_badges}
         WHERE theme = %s
         ORDER BY title ASC",
        'special'
    ));
} elseif (isset($level_map[$badge_level_filter])) {
    $level_label = $level_map[$badge_level_filter];

    $extra_themes = [];
    if ($badge_level_filter === 'seconde') {
        $extra_themes[] = 'Meta-Seconde';
    } elseif ($badge_level_filter === 'premiere') {
        $extra_themes[] = 'Meta-Première';
    } elseif ($badge_level_filter === 'terminale') {
        $extra_themes[] = 'Meta-Terminale';
    }

    $sql = "
        SELECT DISTINCT b.id, b.title, b.slug, b.theme
        FROM {$tbl_badges} b
        LEFT JOIN {$wpdb->prefix}ouin_exo_competencies c
            ON c.domain_slug = b.theme
        WHERE (
            c.level = %s
    ";

    $params = [$level_label];

    if (!empty($extra_themes)) {
        $placeholders = implode(',', array_fill(0, count($extra_themes), '%s'));
        $sql .= " OR b.theme IN ($placeholders)";
        $params = array_merge($params, $extra_themes);
    }

    $sql .= "
        )
        ORDER BY b.title ASC
    ";

    $badges = $wpdb->get_results($wpdb->prepare($sql, $params));
} else {
    $badges = $wpdb->get_results("
        SELECT id, title, slug, theme
        FROM {$tbl_badges}
        ORDER BY title ASC
    ");
}

$groups = $wpdb->get_results("
    SELECT id, label
    FROM {$tbl_groups}
    ORDER BY label ASC
");

// -------------------------
// Liste des utilisateurs
// -------------------------
$users = [];
$group_membership = [];

if ($group_id > 0) {
    $member_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT gm.user_id, g.label
         FROM {$tbl_members} gm
         INNER JOIN {$tbl_groups} g ON g.id = gm.group_id
         WHERE gm.group_id = %d
           AND gm.role = 'student'
         ORDER BY gm.user_id ASC",
        $group_id
    ));

    $member_ids = [];
    foreach ($member_rows as $row) {
        $uid = intval($row->user_id);
        $member_ids[] = $uid;
        $group_membership[$uid] = (string) $row->label;
    }

    if (!empty($member_ids)) {
        $args = [
            'include' => $member_ids,
            'fields'  => ['ID', 'display_name', 'user_email', 'user_login'],
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 9999,
        ];

        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['display_name', 'user_email', 'user_login'];
        }

        $users = get_users($args);
    }
} else {
    $args = [
        'fields'  => ['ID', 'display_name', 'user_email', 'user_login'],
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'number'  => 9999,
    ];

    if ($search !== '') {
        $args['search'] = '*' . $search . '*';
        $args['search_columns'] = ['display_name', 'user_email', 'user_login'];
    }

    $users = get_users($args);

    if (!empty($users)) {
        $user_ids = [];
        foreach ($users as $u) {
            $user_ids[] = intval($u->ID);
        }

        $membership_rows = $wpdb->get_results(
            "SELECT gm.user_id, g.label
             FROM {$tbl_members} gm
             INNER JOIN {$tbl_groups} g ON g.id = gm.group_id
             WHERE gm.role = 'student'"
        );

        foreach ($membership_rows as $row) {
            $uid = intval($row->user_id);
            if (in_array($uid, $user_ids, true) && !isset($group_membership[$uid])) {
                $group_membership[$uid] = (string) $row->label;
            }
        }
    }
}

// exclure les admins
$filtered_users = [];
foreach ($users as $u) {
    if (!user_can($u->ID, 'manage_options')) {
        $filtered_users[] = $u;
    }
}
$users = $filtered_users;

// -------------------------
// Statut du badge sélectionné
// -------------------------
$status_map = [];

if ($badge_id > 0 && !empty($users)) {
    $user_ids = [];
    foreach ($users as $u) {
        $user_ids[] = intval($u->ID);
    }

    $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
    $params = array_merge([$badge_id], $user_ids);

    $sql = $wpdb->prepare(
        "SELECT user_id, source, awarded_at
         FROM {$tbl_user_badges}
         WHERE badge_id = %d
           AND user_id IN ($placeholders)",
        $params
    );

    $rows = $wpdb->get_results($sql);

    foreach ($rows as $row) {
        $status_map[intval($row->user_id)] = [
            'source'     => (string) $row->source,
            'awarded_at' => (string) $row->awarded_at,
        ];
    }
}

settings_errors('ouinpo_badge_assign');
?>
<div class="wrap">
    <h1 class="wp-heading-inline">Attribution manuelle des badges</h1>
    <hr class="wp-header-end"/>

    <p>
        Cette page sert uniquement à attribuer ou retirer des badges manuels.
        Les badges automatiques restent gérés par le moteur de progression.
    </p>

    <form method="get" style="margin:12px 0 24px; display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
        <input type="hidden" name="page" value="ouinpo-badge-assignments">

        <div>
            <label for="badge_level"><strong>Catégorie de badge</strong></label><br>
            <select
                name="badge_level"
                id="badge_level"
                onchange="document.getElementById('badge_id').value=''; this.form.submit()"
            >
                <?php foreach ($badge_level_options as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($badge_level_filter, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="badge_id"><strong>Badge</strong></label><br>
            <select name="badge_id" id="badge_id" style="min-width:320px;">
                <option value="">— Choisir un badge —</option>
                <?php foreach ($badges as $badge): ?>
                    <option value="<?php echo intval($badge->id); ?>" <?php selected($badge_id, intval($badge->id)); ?>>
                            <?php
                            $theme_label = '';
                            if (!empty($badge->theme)) {
                                $theme_label = $badge_theme_labels[$badge->theme] ?? $badge->theme;
                            }
                            
                            echo esc_html(
                                $badge->title
                                . ($theme_label !== '' ? ' — ' . $theme_label : '')
                                . ' [' . $badge->slug . ']'
                            );
                            ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="group_id"><strong>Groupe</strong></label><br>
            <select name="group_id" id="group_id">
                <option value="">Tous les groupes / tous les élèves</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?php echo intval($group->id); ?>" <?php selected($group_id, intval($group->id)); ?>>
                        <?php echo esc_html($group->label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="s"><strong>Recherche</strong></label><br>
            <input type="search" id="s" name="s" value="<?php echo esc_attr($search); ?>" placeholder="nom, login, e-mail">
        </div>

        <div>
            <?php submit_button('Afficher', 'secondary', '', false); ?>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-badge-assignments')); ?>">Réinitialiser</a>
        </div>
    </form>

    <form method="post">
        <?php wp_nonce_field('ouinpo_badge_assign_form', 'ouinpo_badge_assign_nonce'); ?>
        <input type="hidden" name="badge_id" value="<?php echo intval($badge_id); ?>">
        <input type="hidden" name="group_id" value="<?php echo intval($group_id); ?>">
        <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
        <input type="hidden" name="badge_level" value="<?php echo esc_attr($badge_level_filter); ?>">

        <p style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="button button-primary" type="submit" name="bulk_action" value="assign_manual">
                Attribuer le badge manuellement aux sélectionnés
            </button>
            <button class="button" type="submit" name="bulk_action" value="remove_manual">
                Retirer le badge manuel aux sélectionnés
            </button>
        </p>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="ouin-check-all"></th>
                    <th>Élève</th>
                    <th>E-mail</th>
                    <th>Groupe</th>
                    <th>Statut pour ce badge</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$badge_id): ?>
                <tr>
                    <td colspan="6">Choisis un badge pour afficher les élèves.</td>
                </tr>
            <?php elseif (empty($users)): ?>
                <tr>
                    <td colspan="6">Aucun élève trouvé avec ces filtres.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <?php
                    $uid = intval($user->ID);
                    $status = isset($status_map[$uid]) ? $status_map[$uid] : null;
                    $group_label = isset($group_membership[$uid]) ? $group_membership[$uid] : '—';
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="user_ids[]" value="<?php echo $uid; ?>">
                        </td>
                        <td>
                            <strong><?php echo esc_html($user->display_name ? $user->display_name : $user->user_login); ?></strong><br>
                            <span style="color:#646970;"><?php echo esc_html($user->user_login); ?></span>
                        </td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo esc_html($group_label); ?></td>
                        <td>
                            <?php if (!$status): ?>
                                <span style="color:#646970;">non possédé</span>
                            <?php elseif ($status['source'] === 'manual'): ?>
                                <span style="display:inline-block; padding:2px 8px; border:1px solid #8bc48b; border-radius:999px; background:#edf7ed;">manuel</span>
                            <?php else: ?>
                                <span style="display:inline-block; padding:2px 8px; border:1px solid #84aef2; border-radius:999px; background:#eef4ff;">automatique</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (!empty($status) && !empty($status['awarded_at'])) ? esc_html($status['awarded_at']) : '—'; ?></td>
                        </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
(function(){
    const master = document.getElementById('ouin-check-all');
    if (!master) return;

    master.addEventListener('change', function(){
        document.querySelectorAll('input[name="user_ids[]"]').forEach(function(cb){
            cb.checked = master.checked;
        });
    });
})();
</script>