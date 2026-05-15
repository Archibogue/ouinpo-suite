# Changelog — OuInPo Suite

Toutes les modifications notables de OuInPo Suite sont documentées dans ce fichier.

## [0.5.1-beta] - 2026-05-15

Version bêta technique destinée à des tests contrôlés avant diffusion large.

### Ajouté

- Écran de première configuration.
- Modèle de page « Données personnelles, IA et usages pédagogiques ».
- Documentation dédiée aux rôles et droits.
- Pack NSI complet de démonstration.
- Script `scripts/test-dist.ps1`.

### Modifié

- Documentation README complétée pour la bêta technique destinée à des tests contrôlés.
- Niveaux Gate rendus dynamiques avec fallback compatible.
- Import pédagogique renforcé avec statuts `success`, `partial` et `failed`.
- Schéma JSON des packs pédagogiques complété.

### Corrigé

- Signature Gate sécurisée côté serveur.
- Badge Gate rendu configurable.
- Suppression des anciens éléments codés en dur du Gate.
- Ajout ou renforcement de l’unicité des signatures Gate.
- Détection plus prudente de la page statique « Données personnelles, IA et usages pédagogiques ».

## [0.5.0] - 2026-05-14

### Securite et distribution

- Nettoyage de la version de distribution et corrections de packaging.
- Durcissement des capacites eleves : retrait de `upload_files` pour les roles `eleve` et `ouinpo_student`, remplace par des capacites OuInPo dediees aux depots.
- Reglages IA rendus configurables depuis l'administration : activation globale/publique, usages, fournisseurs, URL, cle API, modeles, quotas, parametres de generation, personas et consignes systeme.
- Durcissement des reglages SegFault/RAG via `register_setting` et callbacks de sanitation adaptes.
- Reglages d'administration pour les acces REST anonymes aux exercices, indices, solutions, sujets pratiques et fichiers associes.
- Encadrement des logs IA/RAG : logs synthetiques uniquement si `WP_DEBUG` et l'option de debug IA/RAG sont actifs.
- Documentation des dependances systeme optionnelles, des routes publiques et des consequences des reglages anonymes.
- Documentation des assets, licences et elements remplacables par les enseignants.
- Gate : ajout d'une configuration admin versionnee des enigmes, migration douce depuis le corpus historique, validation IA/fallback exact configurables et cooldown serveur anti-spam.
- Gate : progression adaptee aux identifiants stables d'enigmes, sans reinitialiser les progressions existantes ni supprimer la signature/certificat.

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
