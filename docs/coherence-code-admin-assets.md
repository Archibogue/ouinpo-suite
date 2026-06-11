# Cohérence code admin et assets

Ce document fixe les conventions internes pour OuInPo Suite 0.7.0-beta. Il ne décrit pas de nouvelle fonctionnalité pédagogique.

## Principe admin

- Le menu latéral reste court : il expose les grands espaces de travail, pas toutes les pages métier.
- Les hubs internes sont organisés par usage enseignant : préparer, évaluer, suivre, référentiel, projets, IA et réglages.
- Les onglets internes d'un hub pointent vers des écrans réels ou vers des pages héritées avec des actions explicites.
- Les pages héritées restent accessibles par leur URL directe, mais ne doivent pas réapparaître comme doublons dans la barre latérale.
- Ne pas masquer des sous-menus par CSS. Préférer une page cachée via `AdminMenuRegistry::legacyParent($fallback)` ou un hub Suite qui pointe vers l'écran.

## Hubs Suite

- `ouinpo-suite` : tableau de bord court, métriques et actions rapides principales.
- `ouinpo-suite-first-setup` : première configuration, import initial, droits, pages et diagnostic.
- `ouinpo-suite-contents` : hub **Préparer**. Onglets attendus : Exercices, Annales écrites, Sujets pratiques, Flashcards si actif, Import, Options.
- `ouinpo-suite-evaluations` : hub **Évaluer**. Onglets attendus : Devoirs surveillés, Concepteur, Corrections IA.
- `ouinpo-suite-classes` : hub **Suivre**. Onglets attendus : Classes, Affectations, Dépôts si Submissions actif, Ressources si Submissions actif, Compétences, Badges.
- `ouinpo-suite-referentiel` : référentiel BO, compétences, cours et parcours liés au programme.
- `ouinpo-projects` : hub **Projets** métier conservé sous son slug hérité, exposé depuis les parcours/IA quand le module Projects est actif.
- `ouinpo-suite-ai` : IA et parcours, seulement si au moins un module concerné est actif.
- `ouinpo-suite-settings` : modules, apparence, IA, pages, droits, import, diagnostic et maintenance.
- Les usages IA detailles, dont les options des annales ecrites, se reglent dans l'ecran historique SegFault ; `OuInPo Suite > Reglages > IA` affiche un resume de statut.
- `ouinpo-suite-revisions` peut rester disponible pour compatibilité lorsque Flashcards est actif, mais Flashcards doit aussi être accessible depuis Préparer.
- `ouinpo-suite-badges` reste enregistré pour compatibilité, mais les accès enseignant aux badges passent par Suivre.

## Menus admin

- `SuiteAdmin` construit le menu visible `OuInPo Suite`.
- Les modules peuvent conserver leurs callbacks historiques, mais leurs pages métier doivent être enregistrées comme pages cachées lorsque `OUINPO_SUITE_ADMIN_SLUG` est défini.
- Utiliser `Ouinpo\Suite\Core\Admin\AdminMenuRegistry::legacyParent($fallback)` pour les anciennes pages qui doivent rester accessibles par URL directe sans apparaître comme doublons.
- Utiliser `AdminMenuRegistry::addSuiteSubmenu()` seulement pour une page qui doit vraiment devenir un onglet visible du menu central.
- Les hubs visibles du menu central doivent pointer vers les anciens écrans avec des boutons ou liens explicites, plutôt que dupliquer tous les sous-menus.

## Slugs admin

- Les pages centrales utilisent le préfixe `ouinpo-suite-*`.
- Les pages métier héritées conservent leur slug public admin existant.
- Ne pas renommer un slug sans compatibilité explicite.
- Une nouvelle page métier doit choisir un slug stable, court, préfixé par `ouinpo-`, puis être référencée depuis un hub Suite si elle est destinée à l'usage courant.

## Slugs hérités conservés

- Contenus : `ouinpo-exercices`, `ouinpo-written-subjects`, `ouinpo-practical-subjects`, `ouinpo-flashcards`, `ouinpo-import-exercises`, `ouinpo-exercises-settings`.
- Évaluation : `ouinpo-assessments`, `ouinpo-assessment-builder`, `ouinpo-ai-corrections`, `ouinpo-ai-file-corrections`.
- Suivi : `ouinpo-groups`, `ouinpo-assignments`, `ouinpo-competencies`, `ouinpo-badges`, `ouinpo-badge-assignments`, `edit.php?post_type=ouinpo_submission`, `edit.php?post_type=ouinpo_resource`.
- Référentiel et parcours : `ouinpo-courses-competencies`, `ouinpo-paths`, `ouinpo-years`, `ouinpo-levels`.
- Modules complémentaires : `ouinpo-segfault`, `ouinpo-segfault-progress`, `ouinpo`, `ouinpo-projects`, `ouinpo-meta-social`.

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
- Ne pas localiser de secret, clé IA, token privé ou chemin serveur sensible dans un script front.

## CSS

- Les couleurs transversales doivent passer par ces variables quand le contexte s'y prête:
  - `--ouinpo-color-text`
  - `--ouinpo-color-muted`
  - `--ouinpo-color-primary`
  - `--ouinpo-color-border`
  - `--ouinpo-color-panel`
- Les modes visuels supportes sont `neutral`, `ouinpo` et `bsio`.
- Éviter de cacher des sous-menus par CSS. Préférer une non-déclaration visible, une page cachée via parent `null`, ou `remove_submenu_page()` si une page externe impose un doublon.
- `assets/css/front/projects.css` est organisé par sections: base, cards, buttons, ai, kanban, forms, tables, exports et print.
- Ne pas changer fortement l'apparence dans une passe de coherence; limiter les modifications aux variables et a l'organisation.

## JS Projects

- `assets/js/front/projects.js` reste sans bundler obligatoire.
- Les blocs internes suivent les sections: rest, board, journal, deliverables, evidence, exports, aiTeacher, aiStudent, boot.
- Conserver les data-attributes existants.
- Conserver les endpoints REST existants.
- Garder une compatibilité navigateur raisonnable: pas de syntaxe demandant une compilation obligatoire.

## PHP, vues et logique metier

- Les callbacks admin doivent rester minces autant que possible.
- Les traitements POST sensibles appartiennent à une classe dédiée ou à un service métier lorsque le volume devient important.
- Les calculs de stats, diagnostics et requêtes partagées doivent être déplacés vers des services dédiés lorsqu'ils sont réutilisés ou volumineux.
- Les vues peuvent être placées dans `Admin/views`, `Admin/*View.php`, `Front/views` ou `Front/*View.php` selon le module.
- Ne pas introduire de moteur de template externe.

## Nouvelle action admin

1. Déclarer une capability adaptée.
2. Vérifier la capability avant toute action.
3. Vérifier un nonce.
4. Appliquer `wp_unslash()` puis `sanitize_*`.
5. Utiliser `$wpdb->prepare()` pour toute requête contenant une donnée variable.
6. Rediriger avec `wp_safe_redirect()` après POST quand c'est possible.

## Nouveau shortcode

1. Enregistrer le shortcode dans la classe courte du module.
2. Garder le rendu volumineux dans une vue ou une classe front dédiée.
3. Enqueue les assets front uniquement quand le shortcode est rendu.
4. Échapper les sorties avec `esc_html`, `esc_attr`, `esc_url`, `esc_textarea` ou `wp_kses_post` selon le contexte.
