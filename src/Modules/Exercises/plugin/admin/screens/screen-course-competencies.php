<?php

if (!defined('ABSPATH')) exit;

/**
 * Récupère les domaines de compétences, triés :
 * SNT, puis NSI Première, NSI Terminale, puis Transversal.
 */
function ouinpo_cc_get_domains_for_select(): array {
    global $wpdb;
    $table = $wpdb->prefix . 'ouin_exo_competencies';

    $rows = $wpdb->get_results("
        SELECT DISTINCT domain, track, level
        FROM {$table}
        WHERE active = 1
    ", ARRAY_A);

    $orderTrack = ['SNT' => 0, 'NSI' => 1, 'Transversal' => 3];
    $orderLevel = ['Seconde' => 0, 'Première' => 1, 'Terminale' => 2, 'Transversal' => 3];

    usort($rows, function ($a, $b) use ($orderTrack, $orderLevel) {
        $ta = $orderTrack[$a['track']] ?? 99;
        $tb = $orderTrack[$b['track']] ?? 99;
        if ($ta !== $tb) return $ta <=> $tb;

        $la = $orderLevel[$a['level']] ?? 99;
        $lb = $orderLevel[$b['level']] ?? 99;
        if ($la !== $lb) return $la <=> $lb;

        return strcasecmp($a['domain'], $b['domain']);
    });

    $domains = [];
    foreach ($rows as $r) {
        $key = md5($r['domain'] . '|' . $r['track'] . '|' . $r['level']);
        if (!isset($domains[$key])) {
            $label = $r['domain'];
            if ($r['track'] === 'SNT') {
                $label .= ' — SNT';
            } elseif ($r['track'] === 'NSI') {
                $label .= ' — ' . $r['level'] . ' NSI';
            } else {
                $label .= ' — Transversal';
            }
            $domains[$key] = [
                'key'    => $key,
                'label'  => $label,
                'domain' => $r['domain'],
                'track'  => $r['track'],
                'level'  => $r['level'],
            ];
        }
    }

    return array_values($domains);
}

/**
 * Récupère les posts (pages/articles) présents dans les menus,
 * avec possibilité de filtrer par menu donné ET par texte dans
 * le libellé de l'élément de menu.
 *
 * @param int    $filter_menu_id    0 = tous les menus, sinon ID du menu (term_id)
 * @param string $filter_menu_label texte à chercher dans l'élément de menu (case-insensitive)
 *
 * @return array ['posts' => [...], 'menus' => [...]]
 */
function ouinpo_cc_get_posts_by_menu(int $filter_menu_id = 0, string $filter_menu_label = ''): array {
    global $wpdb;

    $menus = wp_get_nav_menus(); // liste des menus (terms nav_menu)

    if (empty($menus)) {
        return [
            'posts' => [],
            'menus' => [],
        ];
    }

    $filter_menu_label = trim($filter_menu_label);
    $post_ids_map      = []; // [post_id => true]
    $post_menu_names   = []; // [post_id => [menu_name => true, ...]]
    $post_menu_labels  = []; // [post_id => ["Menu : Label complet", ...]]

    foreach ($menus as $menu) {
        /** @var WP_Term $menu */
        if ($filter_menu_id && (int)$menu->term_id !== $filter_menu_id) {
            continue; // filtrage sur un menu précis
        }

        $items = wp_get_nav_menu_items($menu->term_id);
        if (empty($items)) continue;

        // Indexer les items par ID pour pouvoir remonter aux parents
        $items_by_id = [];
        foreach ($items as $it) {
            $items_by_id[$it->ID] = $it;
        }

        foreach ($items as $item) {
            // On s'intéresse aux éléments de type "post_type" (page, article)
            if ($item->type !== 'post_type') continue;

            $object_id = (int)$item->object_id;
            if ($object_id <= 0) continue;

            // Reconstituer le chemin complet : Parent › Enfant › Sous-enfant
            $labels = [];
            $current = $item;

            // On ajoute d'abord le label de l'item lui-même
            $own_label = trim((string)$current->title);
            if ($own_label === '') {
                $own_label = '(sans titre)';
            }
            $labels[] = $own_label;

            // Puis on remonte la chaîne des parents (liens personnalisés inclus)
            $seen_guard = 0;
            while (!empty($current->menu_item_parent) && $seen_guard < 10) {
                $seen_guard++;
                $parent_id = (int)$current->menu_item_parent;
                if (!$parent_id || !isset($items_by_id[$parent_id])) {
                    break;
                }
                $parent = $items_by_id[$parent_id];
                $parent_label = trim((string)$parent->title);
                if ($parent_label === '') {
                    $parent_label = '(sans titre)';
                }
                // On empile au début (chemin : Parent › ... › Enfant)
                array_unshift($labels, $parent_label);
                $current = $parent;
            }

            // Chemin complet type "Bases de Python › Variables et types"
            $full_label = implode(' › ', $labels);

            // Filtre sur le label, si renseigné (on teste sur le chemin complet)
            if ($filter_menu_label !== '' && stripos($full_label, $filter_menu_label) === false) {
                continue;
            }

            // On marque ce post comme présent dans un menu
            $post_ids_map[$object_id] = true;

            // Nom du menu
            if (!isset($post_menu_names[$object_id])) {
                $post_menu_names[$object_id] = [];
            }
            $post_menu_names[$object_id][$menu->name] = true;

            // Étiquette telle qu'affichée : Menu : "chemin complet"
            if (!isset($post_menu_labels[$object_id])) {
                $post_menu_labels[$object_id] = [];
            }
            $post_menu_labels[$object_id][] = $menu->name . ' : ' . $full_label;
        }
    }

    if (empty($post_ids_map)) {
        return [
            'posts' => [],
            'menus' => $menus,
        ];
    }

    $ids = implode(',', array_map('intval', array_keys($post_ids_map)));

    // Ne garder que les posts/pages publiés
    $rows = $wpdb->get_results("
        SELECT ID, post_title, post_type
        FROM {$wpdb->posts}
        WHERE ID IN ($ids)
          AND post_status = 'publish'
          AND post_type IN ('post','page')
        ORDER BY post_type, menu_order, post_title
    ", ARRAY_A);

    // Ajout des infos de menus / éléments de menu
    foreach ($rows as &$row) {
        $pid = (int)$row['ID'];

        // Noms de menus
        if (!empty($post_menu_names[$pid])) {
            $row['menus'] = implode(', ', array_keys($post_menu_names[$pid]));
        } else {
            $row['menus'] = '';
        }

        // Éléments de menu (Menu : Chemin complet)
        if (!empty($post_menu_labels[$pid])) {
            $row['menu_items'] = implode(' | ', array_unique($post_menu_labels[$pid]));
        } else {
            $row['menu_items'] = '';
        }
    }
    unset($row);

    return [
        'posts' => $rows,
        'menus' => $menus,
    ];
}

/**
 * Page admin : Cours ↔ Compétences du BO
 */
function ouinpo_render_courses_competencies_page() {
    global $wpdb;

    $link_table = $wpdb->prefix . 'ouin_exo_post_competency';
    $comp_table = $wpdb->prefix . 'ouin_exo_competencies';

    // --- Filtre "domaine", "menu" et "élément de menu" (GET ou POST) ---
    $domains = ouinpo_cc_get_domains_for_select();

    $selected_domain_key = isset($_REQUEST['domain_key']) ? sanitize_text_field($_REQUEST['domain_key']) : '';
    $selected_menu_id    = isset($_REQUEST['menu_id']) ? (int)$_REQUEST['menu_id'] : 0;
    $menu_item_search    = isset($_REQUEST['menu_item_search']) ? sanitize_text_field($_REQUEST['menu_item_search']) : '';

    $domain_filter = null;
    foreach ($domains as $d) {
        if ($d['key'] === $selected_domain_key) {
            $domain_filter = $d;
            break;
        }
    }

    // --- Traitement du formulaire (ajout / suppression) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['ouinpo_cc_nonce'])
        && wp_verify_nonce($_POST['ouinpo_cc_nonce'], 'ouinpo_courses_competencies')
    ) {
        $action         = isset($_POST['ouinpo_cc_action']) ? sanitize_text_field($_POST['ouinpo_cc_action']) : '';
        $post_ids       = isset($_POST['post_ids']) ? array_map('intval', (array)$_POST['post_ids']) : [];
        $competency_ids = isset($_POST['competency_ids']) ? array_map('intval', (array)$_POST['competency_ids']) : [];

        if ($post_ids && $competency_ids && in_array($action, ['add','remove'], true)) {
            if ($action === 'add') {
                foreach ($post_ids as $pid) {
                    foreach ($competency_ids as $cid) {
                        $wpdb->query(
                            $wpdb->prepare(
                                "INSERT IGNORE INTO {$link_table} (post_id, competency_id)
                                 VALUES (%d, %d)",
                                $pid, $cid
                            )
                        );
                    }
                }
                echo '<div class="notice notice-success"><p>Compétences ajoutées aux cours sélectionnés.</p></div>';
            } elseif ($action === 'remove') {
                foreach ($post_ids as $pid) {
                    foreach ($competency_ids as $cid) {
                        $wpdb->query(
                            $wpdb->prepare(
                                "DELETE FROM {$link_table}
                                 WHERE post_id = %d AND competency_id = %d",
                                $pid, $cid
                            )
                        );
                    }
                }
                echo '<div class="notice notice-success"><p>Compétences retirées des cours sélectionnés.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Merci de sélectionner au moins une compétence et un cours.</p></div>';
        }

        // On garde aussi le filtre texte après POST
        if (isset($_POST['menu_item_search'])) {
            $menu_item_search = sanitize_text_field($_POST['menu_item_search']);
        }
    }

    // --- Compétences du domaine sélectionné ---
    $competencies = [];
    if ($domain_filter) {
        $competencies = $wpdb->get_results($wpdb->prepare("
            SELECT id, slug, competency
            FROM {$comp_table}
            WHERE active = 1
              AND domain = %s
              AND track  = %s
              AND level  = %s
            ORDER BY id
        ", $domain_filter['domain'], $domain_filter['track'], $domain_filter['level']), ARRAY_A);
    }

    // --- Posts présents dans les menus (avec filtre de menu + filtre texte sur élément) ---
    $posts_info = ouinpo_cc_get_posts_by_menu($selected_menu_id, $menu_item_search);
    $posts      = $posts_info['posts'];
    $all_menus  = $posts_info['menus'];

    // --- Compétences déjà associées aux cours (pour le domaine sélectionné) ---
    $post_competencies = [];
    if ($posts && $domain_filter) {
        $post_ids = array_column($posts, 'ID');
        if ($post_ids) {
            $ids_str  = implode(',', array_map('intval', $post_ids));

            $where_domain = $wpdb->prepare(
                " AND c.domain = %s AND c.track = %s AND c.level = %s ",
                $domain_filter['domain'],
                $domain_filter['track'],
                $domain_filter['level']
            );

            $rows_comp = $wpdb->get_results("
                SELECT pc.post_id, c.slug
                FROM {$link_table} pc
                JOIN {$comp_table} c ON c.id = pc.competency_id
                WHERE pc.post_id IN ($ids_str)
                  AND c.active = 1
                  {$where_domain}
                ORDER BY c.slug
            ", ARRAY_A);

            foreach ($rows_comp as $rc) {
                $pid = (int)$rc['post_id'];
                $post_competencies[$pid][] = $rc['slug'];
            }
        }
    }

    ?>
    <div class="wrap">
      <h1>Cours ↔ Compétences du BO</h1>

      <!-- Formulaire de filtres (domaine + menu + élément de menu) -->
      <form method="get">
        <input type="hidden" name="page" value="ouinpo-courses-competencies">

        <p>
          <label for="domain_key"><strong>Domaine / Niveau :</strong></label>
          <select name="domain_key" id="domain_key">
            <option value="">— Choisir un domaine —</option>
            <?php foreach ($domains as $d): ?>
              <option value="<?php echo esc_attr($d['key']); ?>" <?php selected($selected_domain_key, $d['key']); ?>>
                <?php echo esc_html($d['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </p>

        <p>
          <label for="menu_id"><strong>Menu :</strong></label>
          <select name="menu_id" id="menu_id">
            <option value="0">— Tous les menus —</option>
            <?php foreach ($all_menus as $menu): ?>
              <option value="<?php echo (int)$menu->term_id; ?>" <?php selected($selected_menu_id, (int)$menu->term_id); ?>>
                <?php echo esc_html($menu->name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </p>

        <p>
          <label for="menu_item_search"><strong>Filtrer par élément de menu :</strong></label>
          <input type="text"
                 name="menu_item_search"
                 id="menu_item_search"
                 value="<?php echo esc_attr($menu_item_search); ?>"
                 placeholder="Ex. « Piles », « SQL », « Listes chaînées »"
            class="ouinpo-admin-select-wide">
          <button class="button">Filtrer</button>
        </p>
      </form>

      <?php if ($domain_filter): ?>
        <hr>
        <form method="post">
          <?php wp_nonce_field('ouinpo_courses_competencies', 'ouinpo_cc_nonce'); ?>
          <!-- On garde les filtres après soumission -->
          <input type="hidden" name="domain_key" value="<?php echo esc_attr($selected_domain_key); ?>">
          <input type="hidden" name="menu_id" value="<?php echo (int)$selected_menu_id; ?>">
          <input type="hidden" name="menu_item_search" value="<?php echo esc_attr($menu_item_search); ?>">

          <h2>1. Choisir les compétences du domaine sélectionné</h2>
          <p>
            <em>Astuce : Ctrl+clic (ou Cmd+clic sur Mac) pour sélectionner plusieurs compétences.</em>
          </p>

        <select name="competency_ids[]" multiple size="8" class="ouinpo-admin-full-width">
            <?php foreach ($competencies as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>">
                <?php echo esc_html($c['slug'] . ' — ' . wp_trim_words($c['competency'], 12, '…')); ?>
              </option>
            <?php endforeach; ?>
          </select>

    <h2 class="ouinpo-admin-section-title">2. Sélectionner les cours (articles/pages dans le menu choisi)</h2>

          <table class="widefat fixed striped">
            <thead>
              <tr>
            <th class="ouinpo-admin-col-checkbox"><input type="checkbox" id="ouinpo-cc-checkall" data-check-all-target='input[name="post_ids[]"]'></th>
                <th>Titre</th>
                <th>Type</th>
                <th>Menu(s)</th>
                <th>Élément(s) de menu</th>
                <th>Compétences (domaine sélectionné)</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($posts): ?>
              <?php foreach ($posts as $p): ?>
                <?php
                  $pid   = (int)$p['ID'];
                  $comps = isset($post_competencies[$pid])
                      ? implode(', ', array_unique($post_competencies[$pid]))
                      : '';
                ?>
                <tr>
                  <td><input type="checkbox" name="post_ids[]" value="<?php echo $pid; ?>"></td>
                  <td><?php echo esc_html($p['post_title']); ?></td>
                  <td><?php echo esc_html($p['post_type']); ?></td>
                  <td><?php echo esc_html($p['menus'] ?? ''); ?></td>
                  <td><?php echo esc_html($p['menu_items'] ?? ''); ?></td>
                <td><?php echo $comps !== '' ? esc_html($comps) : '<span class="ouinpo-admin-empty">—</span>'; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6">Aucun article/page trouvé avec ces filtres.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>

    <p class="ouinpo-admin-submit-row">
            <button type="submit" name="ouinpo_cc_action" value="add" class="button button-primary">
              Ajouter ces compétences aux cours sélectionnés
            </button>
            <button type="submit" name="ouinpo_cc_action" value="remove" class="button">
              Enlever ces compétences des cours sélectionnés
            </button>
          </p>
        </form>

      <?php else: ?>
        <p><em>Sélectionne d’abord un domaine pour voir les compétences et les cours.</em></p>
      <?php endif; ?>
    </div>
    <?php
}
