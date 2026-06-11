# Coherence code admin et assets

Ce document fixe les conventions internes pour OuInPo Suite 0.7.0-beta. Il ne decrit pas de nouvelle fonctionnalite pedagogique.

## Menus admin

- `SuiteAdmin` construit le menu visible `OuInPo Suite`.
- Les modules peuvent conserver leurs callbacks historiques, mais leurs pages metier doivent etre enregistrees comme pages cachees lorsque `OUINPO_SUITE_ADMIN_SLUG` est defini.
- Utiliser `Ouinpo\Suite\Core\Admin\AdminMenuRegistry::legacyParent($fallback)` pour les anciennes pages qui doivent rester accessibles par URL directe sans apparaitre comme doublons.
- Utiliser `AdminMenuRegistry::addSuiteSubmenu()` seulement pour une page qui doit vraiment devenir un onglet visible du menu central.
- Les anciens slugs utiles restent valides, par exemple `ouinpo-exercices`, `ouinpo-segfault`, `ouinpo`, `ouinpo-projects`, `ouinpo-flashcards`.
- Les hubs visibles du menu central doivent pointer vers les anciens ecrans avec des boutons ou liens explicites, plutot que dupliquer tous les sous-menus.

## Slugs admin

- Les pages centrales utilisent le prefixe `ouinpo-suite-*`.
- Les pages metier heritees conservent leur slug public admin existant.
- Ne pas renommer un slug sans compatibilite explicite.
- Une nouvelle page metier doit choisir un slug stable, court, prefixe par `ouinpo-`, puis etre referencee depuis un hub Suite si elle est destinee a l'usage courant.

## Assets admin

- Utiliser `Ouinpo\Suite\Core\Assets` pour le versioning `filemtime()`.
- Handles attendus:
  - `ouinpo-suite-admin`
  - `ouinpo-suite-admin-js`
  - `ouinpo-projects`
  - `ouinpo-projects-admin`
  - `ouinpo-segfault-admin`
  - `ouinpo-segfault-admin-js`
- `suite-admin.css` et `suite-admin.js` ne doivent etre charges que sur `ouinpo-suite` et `ouinpo-suite-*`.
- Les assets SegFault admin ne doivent etre charges que sur `ouinpo-segfault` et `ouinpo-segfault-progress`.
- Les assets Projects admin ne doivent etre charges que sur `ouinpo-projects`.
- Les assets Projects front restent enregistres globalement, puis enqueued uniquement par les shortcodes Projects.
- Ne pas localiser de secret, cle IA, token prive ou chemin serveur sensible dans un script front.

## CSS

- Les couleurs transversales doivent passer par ces variables quand le contexte s'y prete:
  - `--ouinpo-color-text`
  - `--ouinpo-color-muted`
  - `--ouinpo-color-primary`
  - `--ouinpo-color-border`
  - `--ouinpo-color-panel`
- Les modes visuels supportes sont `neutral`, `ouinpo` et `bsio`.
- Eviter de cacher des sous-menus par CSS. Preferer une non-declaration visible, une page cachee via parent `null`, ou `remove_submenu_page()` si une page externe impose un doublon.
- `assets/css/front/projects.css` est organise par sections: base, cards, buttons, ai, kanban, forms, tables, exports et print.
- Ne pas changer fortement l'apparence dans une passe de coherence; limiter les modifications aux variables et a l'organisation.

## JS Projects

- `assets/js/front/projects.js` reste sans bundler obligatoire.
- Les blocs internes suivent les sections: rest, board, journal, deliverables, evidence, exports, aiTeacher, aiStudent, boot.
- Conserver les data-attributes existants.
- Conserver les endpoints REST existants.
- Garder une compatibilite navigateur raisonnable: pas de syntaxe demandant une compilation obligatoire.

## PHP, vues et logique metier

- Les callbacks admin doivent rester minces autant que possible.
- Les traitements POST sensibles appartiennent a une classe dediee ou a un service metier lorsque le volume devient important.
- Les calculs de stats, diagnostics et requetes partagees doivent etre deplaces vers des services dedies lorsqu'ils sont reutilises ou volumineux.
- Les vues peuvent etre placees dans `Admin/views`, `Admin/*View.php`, `Front/views` ou `Front/*View.php` selon le module.
- Ne pas introduire de moteur de template externe.

## Nouvelle action admin

1. Declarer une capability adaptee.
2. Verifier la capability avant toute action.
3. Verifier un nonce.
4. Appliquer `wp_unslash()` puis `sanitize_*`.
5. Utiliser `$wpdb->prepare()` pour toute requete contenant une donnee variable.
6. Rediriger avec `wp_safe_redirect()` apres POST quand c'est possible.

## Nouveau shortcode

1. Enregistrer le shortcode dans la classe courte du module.
2. Garder le rendu volumineux dans une vue ou une classe front dediee.
3. Enqueue les assets front uniquement quand le shortcode est rendu.
4. Echaper les sorties avec `esc_html`, `esc_attr`, `esc_url`, `esc_textarea` ou `wp_kses_post` selon le contexte.
