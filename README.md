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
Les niveaux `Seconde`, `Première` et `Terminale` ne sont que des valeurs installées par défaut. Ils sont gérés comme des niveaux ordinaires : un administrateur peut les renommer, les réordonner, les remplacer par d'autres niveaux, ou les supprimer lorsqu'ils ne sont liés à aucune donnée.

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

### Niveaux scolaires

La source de vérité des niveaux est la table `ouin_exo_school_levels`. Les contenus ne doivent pas supposer que les slugs `seconde`, `premiere` ou `terminale` existent : ce sont seulement les exemples créés sur une installation neuve.

Une compétence n'est pas rattachée à un niveau par son ancien champ `level`, mais par les liens de la table `ouin_exo_competency_school_level`. Le champ `level` reste un champ hérité d'affichage et de compatibilité.

`Transversal` n'est pas un niveau scolaire. Une compétence est considérée comme transversale lorsqu'elle est liée à plusieurs niveaux scolaires. Les anciens packs qui contiennent `level = "Transversal"` restent acceptés : à l'import, la compétence est alors liée aux niveaux existants.


### Domaines BO

La source structurée des domaines est la table `ouin_exo_domains`. Une compétence appartient à un domaine via `domain_id`, et peut ensuite être liée à un ou plusieurs niveaux scolaires via `ouin_exo_competency_school_level`.

Les anciens champs `domain` et `domain_slug` de `ouin_exo_competencies` sont conservés pour compatibilité avec les packs et shortcodes existants. Les migrations créent automatiquement les domaines à partir des couples historiques `domain_slug` / `domain` / `track`, puis renseignent `domain_id`.

Un domaine n'est pas un niveau : il peut appartenir à un référentiel ou une filière (`track`) comme `NSI`, `SNT` ou `BTS SIO`, tandis que la transversalité d'une compétence reste déduite de ses liens avec plusieurs niveaux scolaires.
Les pages publiques ne sont pas créées automatiquement. Il faut les créer dans WordPress, puis y placer les shortcodes nécessaires.

## Packs pédagogiques

Les packs pédagogiques permettent d’importer des contenus dans une installation neuve ou existante :

- compétences ;
- exercices ;
- indices et solutions ;
- métadonnées de type bac ;
- sujets pratiques ;
- flashcards.

Le dossier `packs/` contient le schéma et les exemples fournis avec le plugin.
Un pack peut déclarer ses propres niveaux dans `school_levels`, avec un `slug`, un `label` et optionnellement `sort_order`. Les exercices et compétences peuvent ensuite utiliser `level_slug` pour un niveau ou `level_slugs` pour plusieurs niveaux. Si un niveau référencé n'existe pas et n'est pas déclaré dans le pack, l'import signale un avertissement au lieu de créer silencieusement une donnée imprévue.


Un pack peut aussi déclarer ses domaines dans domains, avec slug, label, 	rack, description, sort_order et ctive. Si un pack ancien ne déclare pas domains, l'import crée ou met à jour le domaine à partir des champs de compatibilité de chaque compétence.

Pour une installation de démonstration, importer par exemple :

```text
packs/ouinpo-pack-demo.json
```

Pour tester les niveaux personnalisés, importer :

```text
packs/ouinpo-pack-demo-niveaux-dynamiques.json
```

Après import, vérifier dans l’administration que les exercices, sujets pratiques et flashcards apparaissent bien.

## Pages WordPress et shortcodes

Les shortcodes doivent être placés dans des pages WordPress créées manuellement. Les slugs ci-dessous sont conseillés, mais peuvent être adaptés.

### Pages indispensables

| Page WordPress | Slug conseillé | Shortcode |
|---|---|---|
| Exercices | `exercices` | `[ouinpo_exercises page="/exercice/"]` |
| Exercice | `exercice` | `[ouinpo_exercise]` |
| Sujets pratiques | `sujets-pratiques` | `[ouinpo_practical_subjects page="/epreuve-pratique-sujet/"]` |
| Sujet pratique | `epreuve-pratique-sujet` | `[ouinpo_practical_subject]` |
| Flashcards | `flashcards` | `[ouinpo_flashcards]` |
| Ma progression | `ma-progression` | `[ouinpo_competences_progress]` |
| Mes badges | `mes-badges` | `[ouinpo_student_badges]` |
| Palmarès des badges | `palmares-badges` | `[ouinpo_badges_palmares]` |
| Carte du site | `carte-du-site` | `[ouinpo_site_map]` |

La carte du site dynamique est optionnelle et reflète surtout l'organisation du site OuInPo d'origine. Elle peut être ignorée sur une installation plus simple.

### Pages utiles selon les modules activés

| Page WordPress | Slug conseillé | Shortcode |
|---|---|---|
| Dépôt élève | `depot-eleve` | `[ouinpo_upload]` |
| Mes dépôts | `mes-depots` | `[ouinpo_my_submissions]` |
| Ressources pédagogiques | `ressources-pedagogiques` | `[ouinpo_resources]` |
| Suivi des compétences prof | `suivi-competences-prof` | `[ouinpo_competences_prof]` |
| Choisir mon titre | `choisir-mon-titre` | `[ouinpo_title_selector]` |

Il est possible de regrouper le dépôt et l’historique des dépôts sur une seule page :

```text
[ouinpo_upload]

[ouinpo_my_submissions]
```

### Pages IA et parcours

| Page WordPress | Slug conseillé | Shortcode |
|---|---|---|
| SegFault | `segfault` | `[segfault_chat]` |
| Mon parcours conseillé | `mon-parcours-conseille` | `[segfault_parcours]` |
| Mes parcours | `mes-parcours` | `[segfault_mes_parcours]` |

### Pages optionnelles

| Page WordPress | Slug conseillé | Shortcode |
|---|---|---|
| Recherche textuelle | `recherche-textuelle` | `[ouinpo_recherche_textuelle]` |
| Quête OuInPo | `quete-ouinpo` | `[ouinpo_gate page="registre-des-apprentis-satrapes-et-para-satrapes" needed="42" reveal="link"]` |
| Registre des apprentis | `registre-des-apprentis-satrapes-et-para-satrapes` | `[ouinpo_signpad page="registre-des-apprentis-satrapes-et-para-satrapes" needed="42" show_list="1"]` |

### Shortcodes d’intégration

Ces shortcodes ne nécessitent pas forcément une page dédiée :

| Shortcode | Usage |
|---|---|
| `[ouinpo_revision_band]` | Bandeau de révision à placer dans un cours ou une page élève |
| `[ouinpo_hint]...[/ouinpo_hint]` | Contenu conditionnel lié au module Gate |
| `[ouinpo_class_field]` | Champ classe à intégrer dans un formulaire ou une page d’inscription personnalisée |

## Permaliens simples WordPress

Sur un WordPress vierge, les URLs peuvent être en `?page_id=...` au lieu d’utiliser des slugs lisibles.

Dans ce cas, après avoir créé les pages de détail, remplacer les URLs des shortcodes de liste par les URLs réelles.

Exemple pour les exercices :

```text
[ouinpo_exercises page="/?page_id=34"]
```

Exemple pour les sujets pratiques :

```text
[ouinpo_practical_subjects page="/?page_id=35"]
```

`34` et `35` sont à remplacer par les identifiants réels des pages créées dans WordPress.

## IA et confidentialité

Les fonctions IA sont optionnelles.

Aucune clé API n’est fournie avec le plugin. Chaque enseignant doit configurer ses propres accès, s’il souhaite utiliser un fournisseur d’IA.

Avant toute utilisation avec des élèves, il est recommandé de :

- vérifier le cadre applicable dans l’établissement ;
- informer les élèves des usages prévus ;
- éviter l’envoi de données personnelles ;
- désactiver les fonctions IA publiques si elles ne sont pas nécessaires ;
- vérifier les quotas et les coûts éventuels.

## Données élèves

Le plugin peut stocker des données de progression, de statut d’exercice, de badges, de devoirs et de révision.

Avant de partager une archive du plugin, ne jamais inclure :

- dump SQL de production ;
- données élèves ;
- résultats d’exercices ;
- logs ;
- réponses saisies ;
- badges attribués ;
- clés API ;
- export WordPress personnel ;
- fichier de configuration local.

## Diagnostic

Une page de diagnostic est disponible dans l’administration :

```text
OuInPo Suite → Réglages → Diagnostic
```

Elle permet de vérifier :

- la version du plugin ;
- l’environnement WordPress/PHP ;
- les tables présentes ;
- les options principales ;
- les données sensibles à ne pas exporter.

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

- installation sur WordPress vierge ;
- absence de données personnelles ;
- absence de clés API ;
- documentation complète ;
- import/export des contenus pédagogiques ;
- tests des pages principales ;
- compatibilité avec plusieurs thèmes WordPress.

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
- contraintes étrangères Flashcards présentes sur installation neuve, car les tables sont créées en InnoDB.
