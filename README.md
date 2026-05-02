# OuInPo Suite

OuInPo Suite est un plugin WordPress destiné aux enseignants de NSI et de SNT.

Il propose un ensemble d’outils pédagogiques pour organiser des exercices, suivre la progression des élèves, gérer des compétences du programme, préparer des devoirs, utiliser des flashcards, attribuer des badges et, si l’enseignant le souhaite, activer certaines fonctions d’assistance par IA.

## Fonctionnalités principales

- Banque d’exercices NSI / SNT
- Classement par niveau, domaine, compétence et difficulté
- Suivi de la progression des élèves
- Gestion de groupes/classes
- Badges pédagogiques
- Flashcards de révision
- Concepteur de devoirs
- Sujets pratiques de type bac NSI
- Tableaux de suivi pour l’enseignant
- Modules IA optionnels
- Diagnostic d’installation

## Prérequis

- WordPress 6.4 ou supérieur recommandé
- PHP 8.1 ou supérieur recommandé
- Base de données MySQL ou MariaDB
- Droits administrateur WordPress pour l’installation
- Un thème WordPress compatible avec les shortcodes

Le plugin a été développé pour un usage pédagogique en lycée, principalement en NSI et SNT.

## Installation

1. Télécharger l’archive du plugin.
2. Dans WordPress, aller dans **Extensions → Ajouter une extension**.
3. Cliquer sur **Téléverser une extension**.
4. Choisir le fichier `.zip`.
5. Installer puis activer le plugin.
6. Aller dans **OuInPo Suite → Réglages → Diagnostic**.
7. Vérifier que les tables principales sont présentes.

## Première configuration

Après activation, vérifier les points suivants :

1. Les tables du plugin sont bien créées.
2. Les niveaux scolaires et les difficultés de base sont présents.
3. Importer un pack pédagogique si l’on souhaite disposer de compétences, d’exercices, de sujets pratiques ou de flashcards de démonstration.
4. Créer manuellement les pages publiques nécessaires dans WordPress.
5. Placer les shortcodes dans les pages correspondantes.
6. Vérifier que les liens entre pages fonctionnent, notamment avec les permaliens simples WordPress.
7. Désactiver ou ignorer les modules non utilisés.
8. Renseigner les clés API uniquement si l’enseignant souhaite utiliser les fonctions d’IA.

Les compétences BO complètes ne sont pas créées automatiquement par l’installeur. Elles doivent être importées via un pack pédagogique ou créées depuis l’administration du plugin.

Les pages publiques ne sont pas créées automatiquement. Voir `docs/SHORTCODES.md` pour la liste des pages recommandées et des shortcodes à insérer.

## Pages publiques à créer

Pour une installation minimale, créer au moins les pages suivantes :

| Page WordPress | Shortcode |
|---|---|
| Exercices | `[ouinpo_exercises]` |
| Exercice | `[ouinpo_exercise]` |
| Sujets pratiques | `[ouinpo_practical_subjects]` |
| Sujet pratique | `[ouinpo_practical_subject]` |
| Flashcards | `[ouinpo_flashcards]` |
| Ma progression | `[ouinpo_competences_progress]` |

Sur un WordPress en permaliens simples, les liens peuvent utiliser des URLs de type `?page_id=...`. Le plugin est prévu pour conserver ces paramètres lors de l’ouverture d’un exercice ou d’un sujet pratique.

## Shortcodes principaux

Les shortcodes peuvent être placés dans des pages WordPress.

```text
[ouinpo_exercises]
````

Affiche la liste des exercices.

```text
[ouinpo_exercise]
```

Affiche un exercice individuel.

```text
[ouinpo_practical_subjects]
```

Affiche la liste des sujets pratiques.

```text
[ouinpo_practical_subject]
```

Affiche un sujet pratique individuel.

```text
[ouinpo_competences_progress]
```

Affiche la progression par compétences.

```text
[ouinpo_student_badges]
```

Affiche les badges d’un élève.

```text
[ouinpo_badges_palmares]
```

Affiche un palmarès de badges.

## Pages conseillées

Créer au minimum les pages suivantes :

| Page           | Shortcode conseillé             |
| -------------- | ------------------------------- |
| Exercices      | `[ouinpo_exercises]`            |
| Sujet pratique | `[ouinpo_practical_subjects]`   |
| Mes badges     | `[ouinpo_student_badges]`       |
| Ma progression | `[ouinpo_competences_progress]` |
| Palmarès       | `[ouinpo_badges_palmares]`      |

Les slugs peuvent être adaptés selon le site de l’enseignant.
- Guide des shortcodes et des pages WordPress à créer : `docs/SHORTCODES.md`

## IA et confidentialité

Les fonctions IA sont optionnelles.

Aucune clé API n’est fournie avec le plugin.
Chaque enseignant doit configurer ses propres accès, s’il souhaite utiliser un fournisseur d’IA.

Avant toute utilisation avec des élèves, il est recommandé de :

* vérifier le cadre applicable dans l’établissement ;
* informer les élèves des usages prévus ;
* éviter l’envoi de données personnelles ;
* désactiver les fonctions IA publiques si elles ne sont pas nécessaires ;
* vérifier les quotas et les coûts éventuels.

## Données élèves

Le plugin peut stocker des données de progression, de statut d’exercice, de badges, de devoirs et de révision.

Avant de partager une archive du plugin, ne jamais inclure :

* dump SQL de production ;
* données élèves ;
* résultats d’exercices ;
* logs ;
* réponses saisies ;
* badges attribués ;
* clés API ;
* export WordPress personnel ;
* fichier de configuration local.

## Diagnostic

Une page de diagnostic est disponible dans l’administration :

```text
OuInPo Suite → Réglages → Diagnostic
```

Elle permet de vérifier :

* la version du plugin ;
* l’environnement WordPress/PHP ;
* les tables présentes ;
* les options principales ;
* les données sensibles à ne pas exporter.

Sur un site de production, il est normal que certaines lignes indiquent que des données ne doivent pas être exportées.

## Licence

Le code du plugin OuInPo Suite est distribué sous licence GPLv2 ou ultérieure.

Les contenus pédagogiques originaux éventuellement fournis avec le plugin ou sous forme de packs séparés sont, sauf mention contraire, proposés sous licence CC BY-NC-SA 4.0.

Les données élèves, journaux, résultats, copies, réponses, clés API et configurations locales ne font jamais partie des éléments redistribuables.

Voir :

- `LICENSE`
- `CONTENT-LICENSE.md`

## Avertissement

OuInPo Suite est un outil pédagogique. Il ne remplace pas l’évaluation professionnelle de l’enseignant.

Les corrections automatiques, suggestions et aides éventuelles doivent toujours être relues et contextualisées par le professeur.

## État du projet

Version de partage en préparation.

Avant diffusion large, vérifier :

* installation sur WordPress vierge ;
* absence de données personnelles ;
* absence de clés API ;
* documentation complète ;
* import/export des contenus pédagogiques ;
* tests des pages principales ;
* compatibilité avec plusieurs thèmes WordPress.

## Désactivation et désinstallation

La désactivation du plugin ne supprime aucune donnée.

La désinstallation du plugin ne supprime pas automatiquement les tables, exercices, compétences, flashcards, badges, résultats, historiques ou réglages.

Ce comportement est volontaire : il évite toute perte accidentelle de données pédagogiques ou de données élèves.

Avant toute suppression définitive de données OuInPo, l’administrateur du site doit effectuer une sauvegarde complète de la base de données.

Une future version pourra proposer une option explicite de purge complète, mais aucune suppression automatique n’est effectuée par défaut.

## Schéma SQL

Le schéma généré par une installation vierge a été comparé au schéma de production OuInPo.

Les différences restantes sont non bloquantes :
- ordre d’index ou syntaxe phpMyAdmin dans les exports ;
- commentaires SQL absents sur certaines colonnes ;