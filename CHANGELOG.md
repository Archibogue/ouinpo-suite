# Changelog — OuInPo Suite

Toutes les modifications notables de OuInPo Suite sont documentées dans ce fichier.

## [0.6.4-beta] - 2026-06-06

- Lot 5.1 Projects : stabilisation securite/runtime de l'assistant IA eleve Projects.
- Migration Projects IA eleve rendue idempotente meme si la version installee est deja a jour, et diagnostic Suite enrichi avec l'etat/quota IA eleve Projects.
- Version plugin mise a jour en `0.6.4-beta` et migration idempotente des options `ouinpo_projects_student_ai_enabled`, `ouinpo_ai_projects_student_per_minute`, `ouinpo_ai_projects_student_per_day` et `ouinpo_ai_projects_student_max_tokens`.
- Refus explicite des projets archives, de l'IA globale/projet desactivee, des non membres, des nonces invalides et des payloads eleves vides avant tout appel fournisseur ou consommation de quota.
- Validation JSON eleve renforcee avec bornes `MAX_STUDENT_AI_QUESTIONS`, `MAX_STUDENT_AI_TEXT_LENGTH`, `MAX_STUDENT_AI_WARNINGS` et `MAX_STUDENT_CONTEXT_ITEMS`.
- Nettoyage des entrees eleves, schemas stricts pour questions, synthese et brouillon portfolio, et copie front robuste avec fallback sans Clipboard API.
- Documentation de la recette de test lot 5.1, des logs minimaux et du rappel : `Cette aide ne remplace pas ton propre bilan.`
- Hotfix revue Projects : refus admin 403 pour `project_id` non gere, menu Projects coherent avec les capacites de gestion, avertissement si la protection locale des fichiers de traces ne peut pas etre confirmee, rollback des uploads en echec et suppression des attachments prives lors de la suppression d'une trace.

## [0.6.3-beta] - 2026-06-06

- Lot 4 Projects : ajout de l'assistant IA enseignant "Assistant pataprojectif" en brouillon/previsualisation.
- Ajout des capacites `ouinpo_projects_ai_use` et `ouinpo_projects_ai_apply`, reservees aux enseignants et administrateurs.
- Ajout des routes REST IA Projects pour proposer des taches, livrables, competences, analyser les risques, aider le portfolio et produire une synthese enseignant.
- Reutilisation des reglages IA centraux, de l'usage `pedagogical_suggestions` et des quotas enseignants existants.
- Application serveur uniquement apres selection et confirmation enseignant, avec verification des identifiants, de l'appartenance au projet et dedoublonnage.
- Journalisation IA synthetique sans prompt complet ni reponse complete.
- Lot 4.1 Projects : durcissement runtime IA, validation stricte du JSON, refus des objets hors schema ou hors projet, messages d'erreur propres et boutons IA verrouilles pendant les appels.
- Documentation du quota IA Projects : reutilisation des quotas enseignants, consommation seulement sur appel fournisseur, aucune consommation lors de l'application ou des erreurs de permission.
- Lot 5 Projects : ajout d'un assistant IA eleve strictement lecture seule pour preparer questions de recul, synthese personnelle et brouillon portfolio.
- Ajout de l'option globale `ouinpo_projects_student_ai_enabled`, du drapeau projet `student_ai_enabled`, de la capacite `ouinpo_projects_ai_student_use`, de quotas dedies et du shortcode `[ouinpo_project_student_ai]`.
- Ajout des routes REST `POST /projects/{id}/student-ai/reflection-questions`, `personal-summary` et `portfolio-draft`, reservees aux membres actuels du projet avec nonce REST.
- Contexte IA eleve minimise et anonymise : pas de noms d'autres membres, emails, chemins prives, URLs de telechargement, contenu de fichiers, prompts complets ni reponses completes dans les logs.
- L'IA eleve ne peut pas creer, modifier, supprimer ni appliquer tache, livrable, competence ou trace ; aucun acces anonyme et aucun build ni modification du dossier `dist/`.

## [0.6.2-beta] - 2026-06-06

- Extension du module SPOPI Projects avec livrables, traces/preuves et fiche projet HTML imprimable.
- Ajout des tables `ouinpo_project_deliverables`, `ouinpo_project_evidence` et `ouinpo_project_competency_links`.
- Ajout des liens entre projets/taches/livrables et le referentiel existant `ouin_exo_competencies`.
- Ajout des shortcodes `[ouinpo_project_deliverables]`, `[ouinpo_project_evidence]` et `[ouinpo_project_sheet]`.
- Enrichissement de la vue enseignant avec avancement taches/livrables, derniere trace et alertes simples.
- Extension REST `ouinpo-projects/v1` pour livrables, traces et liens competences.
- Durcissement lot 2.1 des acces indirects REST, des actions Kanban en lecture seule et des validations de livrables.
- Lot 3 Projects : upload securise de fichiers de traces, fiche portfolio enrichie, fiche situation BTS SIO, exports HTML/Markdown et CSS d'impression.
- Stabilisation lot 3.1 des uploads Projects : refus `.env`, verrouillage de `attachment_id` au chemin upload, robustesse copie Markdown et URLs longues.
- Lot 3.2 Projects : stockage prive des nouveaux fichiers de traces sous `uploads/ouinpo/projects/` et telechargement protege par nonce REST et droits projet.
- Aucun appel IA, export PDF, badge projet, integration depot Git ni notification dans ce lot.

## [0.6.1-beta] - 2026-06-06

- Ajout du module optionnel SPOPI Projects pour le suivi pedagogique de projets BTS SIO.
- Ajout des tables projets, membres, colonnes Kanban, taches, commentaires, checklist et journal de bord.
- Ajout des capacites `ouinpo_projects_*` pour administrateurs, enseignants et eleves.
- Ajout des routes REST securisees `ouinpo-projects/v1`.
- Ajout des shortcodes `[ouinpo_my_projects]`, `[ouinpo_project_kanban]`, `[ouinpo_project_journal]` et `[ouinpo_teacher_projects]`.
- Ajout d'une interface admin simple pour creer les projets et gerer les membres.
- Stabilisation des permissions de taches, du payload REST front et des index de migration.
- Aucun appel IA dans ce lot.

## [0.6.0-beta] - 2026-05-23

- Ajout des workflows IA admin pour generer des exercices, composer des devoirs et assister la correction.
- Ajout de la correction IA a partir de scans/OCR et de fichiers numeriques eleves.
- Ajout de la gestion admin des fichiers de sujets pratiques.
- Durcissement des acces publics SegFault, exercices et fichiers pratiques.
- Ajout de tables et colonnes de correction via migration Exercises 2.6.9.

## [0.5.2-beta] - 2026-05-17

- Importeur de packs v2 avec prevalidation, erreurs bloquantes, warnings et transaction best-effort.
- Packs distribues nettoyes.
- Pack demo minimal rendu autonome.

## [0.5.1-beta] - 2026-05-16

### Gate

- Conservation et consolidation des modifications Gate configurables.
- Validation IA Gate optionnelle via l'usage dedie `gate_validation`.
- Cooldown serveur anti-spam et fallback exact normalise.
- Migration douce depuis le corpus historique sans ecraser une configuration existante ni supprimer les progressions.
- Niveaux Gate alignes sur les niveaux admin dynamiques quand ils existent.
Version beta technique partageable pour test encadre, non stable pour diffusion large.

### Securite et distribution

- Ajout de `SECURITY.md` avec consignes beta, cles API, donnees eleves et signalement.
- Ajout d'une CI GitHub Actions minimale : fichiers interdits, syntaxe PHP, JSON des packs et motifs de secrets.
- Ajout de `scripts/test-dist.ps1` pour verifier localement le zip genere.
- Verification et durcissement du packaging de distribution.
- Documentation courte pour demarrage, mise a jour, acces publics/prives, roles/droits, donnees personnelles/IA et limites du plugin.
- Clarification des packs pedagogiques distribues et des packs de test exclus du zip.
- Nettoyage des branches distantes obsoletes `droits-capacites-ouinpo` et `niveaux-admin-dynamiques`.
- Documentation beta completee : parcours de demarrage, prudence sur les donnees eleves, cles API, exports SQL, acces publics et verification des roles.

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
