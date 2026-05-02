<?php
namespace Ouinpo\Flashcards\Admin;

use Ouinpo\Flashcards\Service;

defined('ABSPATH') || exit;

final class ScreenFlashcards
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'decks';
        $deck_id = isset($_GET['deck_id']) ? (int) $_GET['deck_id'] : 0;
        $card_id = isset($_GET['card_id']) ? (int) $_GET['card_id'] : 0;

        $stats = Service::stats();
        $decks = Service::deck_options();
        $editingDeck = $deck_id ? Service::get_deck($deck_id) : null;
        $editingCard = $card_id ? Service::get_card($card_id) : null;
        $cards = $deck_id ? Service::get_cards_for_deck($deck_id) : [];

        echo '<div class="wrap ouinpo-suite-wrap">';
        echo '<h1>Flashcards</h1>';
        echo '<p>Module de mémorisation des cours par répétition espacée, séparé des exercices.</p>';

        self::notices();
        self::stats($stats);
        self::tabs($tab, $deck_id, $card_id);

        echo '<div class="card" style="max-width:none;padding:1rem;">';
        match ($tab) {
            'cards' => self::render_cards_tab($decks, $deck_id, $cards, $editingCard),
            'import' => self::render_import_tab($decks, $deck_id),
            'stats' => self::render_stats_tab($decks),
            default => self::render_decks_tab($decks, $editingDeck),
        };
        echo '</div></div>';
    }

    private static function notices(): void
    {
        if (!empty($_GET['fc_error'])) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html(rawurldecode((string) $_GET['fc_error'])));
        }
        $messages = [
            'deck_saved' => 'Paquet enregistré.',
            'deck_deleted' => 'Paquet supprimé.',
            'card_saved' => 'Carte enregistrée.',
            'card_deleted' => 'Carte supprimée.',
        ];
        foreach ($messages as $key => $label) {
            if (!empty($_GET[$key])) {
                printf('<div class="notice notice-success"><p>%s</p></div>', esc_html($label));
            }
        }
        if (isset($_GET['imported'])) {
            printf('<div class="notice notice-success"><p>%d cartes importées.</p></div>', (int) $_GET['imported']);
        }
    }

    private static function stats(array $stats): void
    {
        echo '<div class="ouinpo-suite-grid ouinpo-suite-grid-compact" style="margin:1rem 0;">';
        foreach ([
            'Paquets' => (int) ($stats['decks'] ?? 0),
            'Cartes' => (int) ($stats['cards'] ?? 0),
            'Révisions' => (int) ($stats['reviews'] ?? 0),
            'Cartes dues' => (int) ($stats['due_today'] ?? 0),
        ] as $label => $value) {
            echo '<div class="card"><h3 style="margin-top:0;">' . esc_html($label) . '</h3><p style="font-size:1.6rem;margin:0;">' . esc_html((string) $value) . '</p></div>';
        }
        echo '</div>';
    }

    private static function tabs(string $tab, int $deck_id, int $card_id): void
    {
        $base = admin_url('admin.php?page=ouinpo-flashcards');
        $tabs = [
            'decks' => 'Paquets',
            'cards' => 'Cartes',
            'import' => 'Import CSV',
            'stats' => 'Statistiques',
        ];

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $url = add_query_arg(['page' => 'ouinpo-flashcards', 'tab' => $slug] + ($deck_id ? ['deck_id' => $deck_id] : []) + ($card_id ? ['card_id' => $card_id] : []), admin_url('admin.php'));
            $class = 'nav-tab' . ($tab === $slug ? ' nav-tab-active' : '');
            printf('<a class="%s" href="%s">%s</a>', esc_attr($class), esc_url($url), esc_html($label));
        }
        echo '</h2>';
    }

private static function render_decks_tab(array $decks, ?array $editingDeck): void
{
    echo '<div class="ouinpo-suite-grid">';
    echo '<div>';
    echo '<h2>Paquets existants</h2>';
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Titre</th><th>Slug</th><th>Track</th><th>Niveau</th><th>Actif</th><th></th></tr></thead><tbody>';
    foreach ($decks as $deck) {
        $edit = esc_url(add_query_arg(['page' => 'ouinpo-flashcards', 'tab' => 'decks', 'deck_id' => $deck['id']], admin_url('admin.php')));
        $cards = esc_url(add_query_arg(['page' => 'ouinpo-flashcards', 'tab' => 'cards', 'deck_id' => $deck['id']], admin_url('admin.php')));
        echo '<tr>';
        echo '<td>' . (int) $deck['id'] . '</td>';
        echo '<td>' . esc_html($deck['title']) . '</td>';
        echo '<td><code>' . esc_html($deck['slug']) . '</code></td>';
        echo '<td>' . esc_html($deck['track']) . '</td>';
        echo '<td>' . esc_html($deck['level']) . '</td>';
        echo '<td>' . (!empty($deck['is_active']) ? 'Oui' : 'Non') . '</td>';
        echo '<td><a class="button" href="' . $edit . '">Éditer</a> <a class="button button-secondary" href="' . $cards . '">Cartes</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';

    $deck = $editingDeck ?: [
        'id' => 0,
        'title' => '',
        'slug' => '',
        'description' => '',
        'track' => 'NSI',
        'level' => 'Première',
        'source_post_id' => '',
        'source_post_slug' => '',
        'is_active' => 1,
    ];

    if (!empty($deck['source_post_id'])) {
        $src_post = get_post((int) $deck['source_post_id']);
        if ($src_post instanceof \WP_Post) {
            $deck['source_post_slug'] = $src_post->post_name;
        } else {
            $deck['source_post_slug'] = '';
        }
    } else {
        $deck['source_post_slug'] = '';
    }

    echo '<div>';
    echo '<h2>' . ($deck['id'] ? 'Éditer le paquet' : 'Nouveau paquet') . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('ouinpo_fc_admin_action');
    echo '<input type="hidden" name="action" value="ouinpo_fc_save_deck">';
    echo '<input type="hidden" name="id" value="' . (int) $deck['id'] . '">';

    self::field('Titre', '<input type="text" class="regular-text" name="title" value="' . esc_attr((string) $deck['title']) . '" required>');
    self::field('Slug', '<input type="text" class="regular-text" name="slug" value="' . esc_attr((string) $deck['slug']) . '" required>');
    self::field('Description', '<textarea class="large-text" rows="4" name="description">' . esc_textarea((string) $deck['description']) . '</textarea>');
    self::field('Track', self::select('track', ['SNT' => 'SNT', 'NSI' => 'NSI'], (string) $deck['track']));
    self::field('Niveau', self::select('level', ['Seconde' => 'Seconde', 'Première' => 'Première', 'Terminale' => 'Terminale', 'Transversal' => 'Transversal'], (string) $deck['level']));

    $sourceControl =
        '<input type="text" class="regular-text" name="source_post_slug" value="' . esc_attr((string) ($deck['source_post_slug'] ?? '')) . '" placeholder="ex. modularite-modules-et-bibliotheques">'
        . (!empty($deck['source_post_id'])
            ? '<p class="description" style="margin:.35rem 0 0;">ID actuel : ' . (int) $deck['source_post_id'] . '</p>'
            : '<p class="description" style="margin:.35rem 0 0;">Saisis le <strong>slug</strong> du cours WordPress source.</p>');
    self::field('Post source', $sourceControl);

    self::field('Actif', '<label><input type="checkbox" name="is_active" value="1" ' . checked(!empty($deck['is_active']), true, false) . '> Oui</label>');
    submit_button($deck['id'] ? 'Mettre à jour le paquet' : 'Créer le paquet');
    echo '</form>';

    if (!empty($deck['id'])) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Supprimer ce paquet et ses cartes ?\');">';
        wp_nonce_field('ouinpo_fc_admin_action');
        echo '<input type="hidden" name="action" value="ouinpo_fc_delete_deck">';
        echo '<input type="hidden" name="deck_id" value="' . (int) $deck['id'] . '">';
        submit_button('Supprimer le paquet', 'delete', '', false);
        echo '</form>';
    }
    echo '</div></div>';
}

    private static function render_cards_tab(array $decks, int $deck_id, array $cards, ?array $editingCard): void
    {
        echo '<h2>Cartes</h2>';
        echo '<p>Choisis un paquet puis ajoute ou modifie ses cartes.</p>';

        echo '<form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;align-items:center;">';
        echo '<input type="hidden" name="page" value="ouinpo-flashcards">';
        echo '<input type="hidden" name="tab" value="cards">';
        echo self::select('deck_id', array_reduce($decks, function($carry, $deck) { $carry[$deck['id']] = $deck['title']; return $carry; }, []), (string) $deck_id);
        submit_button('Charger', 'secondary', '', false);
        echo '</form>';

        if (!$deck_id) {
            echo '<p class="ouinpo-suite-muted">Sélectionne un paquet pour afficher ses cartes.</p>';
            return;
        }

        echo '<div class="ouinpo-suite-grid">';
        echo '<div>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Type</th><th>Recto</th><th>Verso</th><th>Compétences</th><th></th></tr></thead><tbody>';
        foreach ($cards as $card) {
            $edit = esc_url(add_query_arg(['page' => 'ouinpo-flashcards', 'tab' => 'cards', 'deck_id' => $deck_id, 'card_id' => $card['id']], admin_url('admin.php')));
            echo '<tr>';
            echo '<td>' . (int) $card['id'] . '</td>';
            echo '<td>' . esc_html($card['card_type']) . '</td>';
            echo '<td>' . wp_trim_words(wp_strip_all_tags((string) $card['front_html']), 16) . '</td>';
            echo '<td>' . wp_trim_words(wp_strip_all_tags((string) $card['back_html']), 16) . '</td>';
            echo '<td><code>' . esc_html((string) ($card['competency_slugs'] ?? '')) . '</code></td>';
            echo '<td><a class="button" href="' . $edit . '">Éditer</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        $card = $editingCard ?: [
            'id' => 0,
            'deck_id' => $deck_id,
            'card_type' => 'definition',
            'front_html' => '',
            'back_html' => '',
            'note_teacher' => '',
            'sort_order' => 0,
            'is_active' => 1,
            'competency_slugs' => '',
        ];

        echo '<div>';
        echo '<h3>' . ($card['id'] ? 'Éditer la carte' : 'Nouvelle carte') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ouinpo_fc_admin_action');
        echo '<input type="hidden" name="action" value="ouinpo_fc_save_card">';
        echo '<input type="hidden" name="id" value="' . (int) $card['id'] . '">';
        echo '<input type="hidden" name="deck_id" value="' . (int) $deck_id . '">';
        self::field('Type', self::select('card_type', ['definition' => 'definition', 'distinction' => 'distinction', 'repere' => 'repère', 'syntaxe' => 'syntaxe', 'vocabulaire' => 'vocabulaire'], (string) $card['card_type']));
        self::field('Recto (HTML autorisé)', '<textarea class="large-text code" rows="6" name="front_html" required>' . esc_textarea((string) $card['front_html']) . '</textarea>');
        self::field('Verso (HTML autorisé)', '<textarea class="large-text code" rows="8" name="back_html" required>' . esc_textarea((string) $card['back_html']) . '</textarea>');
        self::field('Note prof', '<textarea class="large-text" rows="3" name="note_teacher">' . esc_textarea((string) $card['note_teacher']) . '</textarea>');
        self::field('Ordre', '<input type="number" class="small-text" name="sort_order" value="' . esc_attr((string) $card['sort_order']) . '">');
        self::field('Compétences (slugs séparés par espaces, virgules ou ;)', '<textarea class="large-text code" rows="3" name="competency_slugs">' . esc_textarea((string) ($card['competency_slugs'] ?? '')) . '</textarea>');
        self::field('Active', '<label><input type="checkbox" name="is_active" value="1" ' . checked(!empty($card['is_active']), true, false) . '> Oui</label>');
        submit_button($card['id'] ? 'Mettre à jour la carte' : 'Créer la carte');
        echo '</form>';

        if (!empty($card['id'])) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Supprimer cette carte ?\');">';
            wp_nonce_field('ouinpo_fc_admin_action');
            echo '<input type="hidden" name="action" value="ouinpo_fc_delete_card">';
            echo '<input type="hidden" name="card_id" value="' . (int) $card['id'] . '">';
            echo '<input type="hidden" name="deck_id" value="' . (int) $deck_id . '">';
            submit_button('Supprimer la carte', 'delete', '', false);
            echo '</form>';
        }
        echo '</div></div>';
    }

    private static function render_import_tab(array $decks, int $deck_id): void
    {
        echo '<h2>Import CSV</h2>';
        echo '<p>Format attendu par ligne : <code>type;front_html;back_html;sort_order;competency_slugs</code></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ouinpo_fc_admin_action');
        echo '<input type="hidden" name="action" value="ouinpo_fc_import_cards">';
        self::field('Paquet', self::select('deck_id', array_reduce($decks, function($carry, $deck) { $carry[$deck['id']] = $deck['title']; return $carry; }, []), (string) $deck_id));
        self::field('CSV', '<textarea class="large-text code" name="csv" rows="12" placeholder="definition;&lt;p&gt;Qu\'est-ce que DNS ?&lt;/p&gt;;&lt;p&gt;...&lt;/p&gt;;10;SNT-web-001"></textarea>');
        submit_button('Importer les cartes');
        echo '</form>';
    }

    private static function render_stats_tab(array $decks): void
    {
        echo '<h2>Statistiques rapides</h2>';
        echo '<p>Vue d’ensemble des paquets existants.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Paquet</th><th>Track</th><th>Niveau</th><th>Actif</th></tr></thead><tbody>';
        foreach ($decks as $deck) {
            echo '<tr>';
            echo '<td>' . esc_html($deck['title']) . '</td>';
            echo '<td>' . esc_html($deck['track']) . '</td>';
            echo '<td>' . esc_html($deck['level']) . '</td>';
            echo '<td>' . (!empty($deck['is_active']) ? 'Oui' : 'Non') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p style="margin-top:1rem;">Shortcode élève : <code>[ouinpo_flashcards]</code></p>';
    }

    private static function field(string $label, string $control): void
    {
        echo '<p><strong>' . esc_html($label) . '</strong><br>' . $control . '</p>';
    }

    private static function select(string $name, array $options, string $selected): string
    {
        $html = '<select name="' . esc_attr($name) . '">';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . esc_attr((string) $value) . '" ' . selected((string) $selected, (string) $value, false) . '>' . esc_html((string) $label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}
