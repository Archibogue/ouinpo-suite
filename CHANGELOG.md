# Changelog — OuInPo Suite

Toutes les modifications notables de OuInPo Suite sont documentées dans ce fichier.

## [0.4.0] — 2026-05-01

### Ajouté

- Ajout d’une page d’administration unifiée `OuInPo Suite`.
- Réorganisation du menu admin autour des usages enseignants :
  - Tableau de bord ;
  - Contenus ;
  - Révisions ;
  - Évaluations ;
  - Classes & élèves ;
  - Référentiel BO ;
  - IA & parcours ;
  - Réglages.
- Ajout d’un système de modules activables / désactivables.
- Ajout d’un onglet `Réglages → Modules`.
- Ajout d’un diagnostic de diffusion.
- Ajout d’un importeur de packs pédagogiques JSON.
- Import des compétences BO depuis JSON.
- Import des exercices depuis JSON.
- Import des liens exercices ↔ compétences.
- Import des liens exercices ↔ niveaux.
- Import des indices, solutions et métadonnées bac.
- Import des flashcards depuis JSON.
- Import des liens flashcards ↔ compétences.
- Import des sujets pratiques depuis JSON.
- Import des appels de sujets pratiques.
- Import des fichiers associés aux sujets pratiques.
- Ajout du dossier `packs/`.
- Ajout de la documentation des packs pédagogiques.
- Ajout d’une licence dédiée aux contenus pédagogiques.

### Modifié

- Le module Exercices est considéré comme socle de la suite et reste toujours chargé.
- Le menu admin masque les anciens sous-menus techniques au profit de hubs pédagogiques.
- Les liens de l’interface admin tiennent compte des modules désactivés.
- Les routes REST front sont compatibles avec les installations utilisant `index.php?rest_route=...`.
- L’import pédagogique est idempotent : un même pack peut être importé plusieurs fois sans créer de doublons.
- Les packs pédagogiques utilisent des slugs stables plutôt que des identifiants numériques.

### Corrigé

- Correction de l’affichage des exercices importés côté front.
- Correction de la génération des liens REST dans le JavaScript public.
- Correction du rattachement des exercices importés à leur niveau scolaire.
- Correction de l’import des flashcards après erreur d’appel de méthode.
- Correction de liens orphelins dans l’administration lorsque certains modules sont désactivés.
- Harmonisation des namespaces du module Exercices.
- Correction de plusieurs incohérences de schéma entre installation vierge et installation existante.

### Sécurité et diffusion

- Les clés API ne sont pas incluses dans les packs.
- Les données élèves ne sont pas incluses dans les packs.
- Les dumps SQL et exports WordPress ne sont pas destinés à la distribution.
- Les contenus pédagogiques redistribuables sont documentés séparément dans `CONTENT-LICENSE.md`.
- Les ressources officielles ou tierces doivent être vérifiées avant redistribution.

## [0.3.1] — version interne

### Modifié

- Consolidation de l’activation du plugin.
- Amélioration du schéma partagé.
- Premiers ajustements pour une installation sur WordPress vierge.
- Préparation de la structure de diffusion.

## [0.3.0] — version interne

### Ajouté

- Point d’entrée unique `OuInPo Suite`.
- Regroupement progressif des modules existants.
- Première structure de distribution.