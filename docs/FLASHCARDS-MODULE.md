# Module Flashcards — OuInPo Suite

## Ce qui est inclus

- Nouveau module `Flashcards` enregistré dans `src/Core/Bootstrap.php`
- Tables SQL `{prefix}_fc_*`
- Admin WordPress : sous-menu `OuInPo Suite > Flashcards`
- CRUD paquets / cartes
- Import CSV simple
- REST API `/wp-json/ouinpo/v1/flashcards/*`
- Shortcode élève : `[ouinpo_flashcards]`
- Répétition espacée V1 avec 3 boutons : `Encore fragile`, `Presque su`, `Su`

## Fichiers principaux

- `src/Modules/Flashcards/Module.php`
- `src/Modules/Flashcards/plugin/ouinpo-flashcards.php`
- `src/Modules/Flashcards/plugin/inc/ModuleInstaller.php`
- `src/Modules/Flashcards/plugin/inc/Service.php`
- `src/Modules/Flashcards/plugin/inc/Rest/FlashcardsRoutes.php`
- `src/Modules/Flashcards/plugin/admin/AdminMenu.php`
- `src/Modules/Flashcards/plugin/admin/screens/screen-flashcards.php`
- `src/Modules/Flashcards/plugin/public/Shortcodes.php`
- `src/Modules/Flashcards/plugin/public/assets/js/flashcards.js`
- `src/Modules/Flashcards/plugin/public/assets/css/flashcards.css`

## Tables créées

- `{prefix}_fc_decks`
- `{prefix}_fc_cards`
- `{prefix}_fc_card_competency`
- `{prefix}_fc_user_cards`
- `{prefix}_fc_reviews`

## Import CSV

Format par ligne :

```text
definition;<p>Question</p>;<p>Réponse</p>;10;NSI-premiere-lang-001
```

Colonnes :
1. `card_type`
2. `front_html`
3. `back_html`
4. `sort_order`
5. `competency_slugs`

## Shortcode

```text
[ouinpo_flashcards]
```

## Remarques

- V1 pensée pour la mémorisation de cours, pas pour remplacer les exercices.
- Le filtrage par niveau suit le niveau courant de l'élève quand il est connu via les tables `ouin_exo_*`.
- Pas encore de gestion fine d'assignation par classe/groupe spécifique dans cette V1.
