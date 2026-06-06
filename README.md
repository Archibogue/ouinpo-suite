# OuInPo Suite

OuInPo Suite est un plugin WordPress destiné aux enseignants de NSI et de SNT.

Il propose un ensemble d’outils pédagogiques pour organiser des exercices, suivre la progression des élèves, gérer des compétences du programme, préparer des devoirs, utiliser des flashcards, attribuer des badges et, si l’enseignant le souhaite, activer certaines fonctions d’assistance par IA.

## Statut

Version 0.6.2-beta : beta technique partageable a des enseignants volontaires pour test encadre. Elle ne doit pas etre presentee comme une version stable. Tout usage avec des eleves reels doit etre precede d'une validation sur le site cible : roles, pages, acces publics, workflows IA et cadre donnees personnelles.

Modules actifs par defaut sur une installation neuve : `exercises` et `flashcards`. Le module `exercises` est le socle et reste actif. Les autres modules, dont Gate, Submissions, SegFault, RechText et Projects, doivent etre actives volontairement depuis l'administration.

## Pour commencer

- Guide de demarrage rapide : [`docs/guide-demarrage-rapide.md`](docs/guide-demarrage-rapide.md).
- Ce que le plugin fait ou ne fait pas encore : [`docs/ce-que-fait-le-plugin.md`](docs/ce-que-fait-le-plugin.md).
- Mise a jour d'une installation existante : [`docs/mise-a-jour.md`](docs/mise-a-jour.md).
- Roles et droits : [`docs/roles-et-droits.md`](docs/roles-et-droits.md).
- Acces publics et prives : [`docs/acces-publics-prives.md`](docs/acces-publics-prives.md).
- Donnees personnelles et IA : [`docs/modele-donnees-personnelles-ia.md`](docs/modele-donnees-personnelles-ia.md).
- Politique de securite : [`SECURITY.md`](SECURITY.md).

## Fonctionnalités principales

- Banque d’exercices NSI / SNT
- Classement par niveau, domaine, compétence et difficulté
- Suivi de la progression des élèves
- Gestion de groupes/classes
- Badges pédagogiques
- Flashcards de révision
- Concepteur de devoirs
- Sujets pratiques de type bac NSI
- Suivi de projets BTS SIO avec Kanban et journal de bord via le module optionnel Projects
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

## Archives et distribution

Le depot GitHub de developpement peut contenir des outils de maintenance, par exemple `.distignore`, `tools/` et `tools/build-dist.ps1`.

Le zip installable WordPress doit contenir le dossier du plugin, son code, ses assets, sa documentation publique et les packs pedagogiques prevus. Il ne doit pas contenir le dossier `dist/`, d'anciennes archives, de dumps SQL, d'exports WordPress personnels, de secrets ou de fichiers locaux.

Le zip de distribution est l'archive partagee aux enseignants. Il peut etre produit depuis le depot de developpement, mais il doit rester directement installable dans WordPress comme une extension classique.

Commandes utiles depuis le depot de developpement :

```powershell
.\tools\build-dist.ps1
.\scripts\test-dist.ps1
```

Le script de test reconstruit le zip, verifie la presence de `ouinpo-suite/ouinpo-suite.php`, les chemins internes et l'absence de fichiers interdits.

## Choix de securite a verifier

- La suppression du plugin conserve volontairement les tables, options et fichiers OuInPo. `uninstall.php` evite une perte accidentelle de donnees pedagogiques.
- Les acces publics aux exercices, indices, solutions, fichiers pratiques et fonctions IA sont opt-in. Ils doivent etre actives volontairement par l'administrateur.
- Les quotas publics IA utilisent un hash de l'adresse IP. Des eleves derriere un meme NAT ou proxy peuvent donc partager un quota.
- Les fichiers pratiques publics exposent les ressources placees dans `uploads/ouinpo/practical/` si l'administrateur active l'acces public correspondant.
- L'extraction PDF SegFault peut utiliser `pdftotext` via `shell_exec` lorsque l'hebergement l'autorise. Sinon le plugin utilise son parseur PHP de secours.

## Test sur WordPress vierge

Pour valider une archive avant partage :

1. Installer un WordPress neuf avec une base vide.
2. Installer le zip du plugin depuis **Extensions > Ajouter une extension > Televerser une extension**.
3. Activer le plugin puis ouvrir **OuInPo Suite > Reglages > Diagnostic**.
4. Verifier que les tables principales existent et qu'aucune cle API ou chemin local n'est configure.
5. Importer un pack de demonstration depuis **OuInPo Suite > Reglages > Import pedagogique**.
6. Creer les pages depuis **Pages & shortcodes**, puis tester les pages publiques et les pages eleves avec un compte distinct.

## Première configuration

Après activation, vérifier les points suivants :

1. Les tables du plugin sont bien créées.
2. Les niveaux scolaires et les difficultés de base sont présents.
3. Importer un pack pédagogique si l’on souhaite disposer de compétences, d’exercices, de sujets pratiques ou de flashcards de démonstration.
4. Créer ou reparer les pages publiques depuis **OuInPo Suite > Réglages > Pages & shortcodes**, ou les créer manuellement dans WordPress.
5. Vérifier que les shortcodes sont présents dans les pages correspondantes. Une page au bon slug mais sans shortcode attendu doit etre reparee avant d'etre consideree comme correcte.
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
Les pages publiques peuvent être créées depuis **OuInPo Suite > Réglages > Pages & shortcodes**. Elles peuvent aussi être créées manuellement dans WordPress, puis recevoir les shortcodes nécessaires.

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


Un pack peut aussi déclarer ses domaines dans `domains`, avec `slug`, `label`, `track`, `description`, `sort_order` et `active`. Si un pack ancien ne déclare pas `domains`, l'import crée ou met à jour le domaine à partir des champs de compatibilité de chaque compétence.

Pour un test rapide, importer :

```text
packs/ouinpo-pack-demo-minimal.json
```

Pour une installation professeur, importer dans cet ordre :

```text
packs/ouinpo-pack-referentiel-snt-nsi.json
packs/ouinpo-pack-flashcards-nsi.json
packs/ouinpo-pack-exercices-site-origine.json
```

Les sujets pratiques ne sont pas encore distribues dans ces packs. Les fichiers `packs/ouinpo-pack-test-*.json`, lorsqu'ils existent dans le depot de travail, sont des packs de test technique exclus du zip de distribution par le script de build.

Après import, vérifier dans l’administration que les exercices et flashcards apparaissent bien.

## Pages WordPress et shortcodes

Les pages et shortcodes ci-dessous correspondent à ceux proposés par **OuInPo Suite > Réglages > Pages & shortcodes**. Les slugs peuvent être adaptés si les liens internes sont ajustés en conséquence.

Attention : les shortcodes publics peuvent afficher des exercices, indices, solutions, sujets pratiques ou fichiers selon les reglages et les contenus disponibles. L'enseignant doit choisir volontairement les pages ou ils sont places.

### Pages indispensables

| Page WordPress | Slug conseillé | Shortcode |
|---|---|---|
| Exercices | `exercices` | `[ouinpo_exercises]` |
| Exercice | `exercice` | `[ouinpo_exercise]` |
| Épreuve pratique | `epreuve-pratique` | `[ouinpo_practical_subjects]` |
| Sujet pratique | `epreuve-pratique-sujet` | `[ouinpo_practical_subject]` |
| Flashcards | `flashcards` | `[ouinpo_flashcards]` |
| Mes compétences | `mes-competences` | `[ouinpo_competences_progress]` |
| Mes badges | `mes-badges` | `[ouinpo_student_badges]` |
| Palmarès des badges | `palmares-badges` | `[ouinpo_badges_palmares]` |
| Carte du site | `carte-du-site` | `[ouinpo_site_map]` |

La carte du site dynamique est optionnelle et reflète surtout l'organisation du site OuInPo d'origine. Elle peut être ignorée sur une installation plus simple.

### Pages utiles selon les modules activés

| Page WordPress | Slug conseillé | Shortcode |
|---|---|---|
| Dépôt de travaux | `depot-travaux` | `[ouinpo_upload]` |
| Mes dépôts | `mes-depots` | `[ouinpo_my_submissions]` |
| Ressources | `ressources` | `[ouinpo_resources]` |
| Mes projets | `mes-projets` | `[ouinpo_my_projects]` |
| Suivi des projets | `suivi-projets` | `[ouinpo_teacher_projects]` |
| Suivi des compétences | `suivi-competences` | `[ouinpo_competences_prof]` |

Les trois premieres pages de ce tableau sont prevues pour des eleves connectes lorsque le module Submissions est active. Le suivi des competences est reserve aux enseignants ou administrateurs ayant les capacites OuInPo necessaires.

Il est possible de regrouper le dépôt et l’historique des dépôts sur une seule page :

```text
[ouinpo_upload]

[ouinpo_my_submissions]
```

### Pages IA et parcours

| Page WordPress | Slug conseillé | Shortcode |
|---|---|---|
| Assistant SegFault | `assistant-segfault` | `[segfault_chat]` |
| Mes parcours | `mes-parcours` | `[segfault_mes_parcours]` |

Ces pages supposent le module SegFault active. Le chat peut etre public ou reserve selon les reglages IA ; les parcours sont destines aux eleves connectes.

### Pages optionnelles

| Page WordPress | Slug conseillé | Shortcode |
|---|---|---|
| Recherche textuelle | `recherche-textuelle` | `[ouinpo_recherche_textuelle]` |
| Gate | `gate` | `[ouinpo_gate]` |
| Signatures | `signatures` | `[ouinpo_signpad]` |

Ces pages correspondent a des modules avances desactives par defaut sur une installation neuve. Les activer seulement si elles sont configurees et utiles au site.

### SPOPI Projects

Le module optionnel `projects` ajoute le suivi pedagogique de projets BTS SIO, sans appel IA. Il fournit une gestion simple des projets, des membres, d'un tableau Kanban, d'un journal de bord projet, de livrables, de traces/preuves, de liens vers les competences BO existantes et d'une fiche projet HTML imprimable.

Shortcodes disponibles :

| Shortcode | Usage |
|---|---|
| `[ouinpo_my_projects]` | Liste des projets visibles par l'utilisateur connecte, avec acces Kanban et journal |
| `[ouinpo_project_kanban id="..."]` | Tableau Kanban d'un projet autorise |
| `[ouinpo_project_journal id="..."]` | Journal de bord d'un projet autorise |
| `[ouinpo_project_deliverables id="..."]` | Livrables attendus, statuts et validation enseignant |
| `[ouinpo_project_evidence id="..."]` | Traces/preuves deposees par les membres du projet |
| `[ouinpo_project_sheet id="..."]` | Fiche projet HTML imprimable |
| `[ouinpo_teacher_projects]` | Vue enseignant avec statut, membres, taches, livrables, traces, alertes et acces Kanban/fiche |

Capacites ajoutees :

```text
ouinpo_projects_manage_all
ouinpo_projects_manage_class
ouinpo_projects_create
ouinpo_projects_view_own
ouinpo_projects_edit_own_tasks
ouinpo_projects_comment
ouinpo_projects_validate
```

Tables creees :

```text
wp_ouinpo_projects
wp_ouinpo_project_members
wp_ouinpo_project_columns
wp_ouinpo_project_tasks
wp_ouinpo_project_task_comments
wp_ouinpo_project_checklist_items
wp_ouinpo_project_logs
wp_ouinpo_project_deliverables
wp_ouinpo_project_evidence
wp_ouinpo_project_competency_links
```

Les routes REST utilisent le namespace `ouinpo-projects/v1` et exigent un utilisateur connecte, un nonce REST et les capacites/appartenances adaptees. Les eleves ne voient que les projets dont ils sont membres. Les livrables sont geres/valides par les enseignants du projet ; les membres peuvent deposer des traces si leurs capacites Projects le permettent.

Routes REST principales ajoutees au lot 2 :

```text
GET/POST /projects/{id}/deliverables
PATCH/DELETE /deliverables/{id}
PATCH /deliverables/{id}/status
GET/POST /projects/{id}/evidence
PATCH/DELETE /evidence/{id}
GET/POST /projects/{id}/competencies
GET/POST /tasks/{id}/competencies
GET/POST /deliverables/{id}/competencies
DELETE /competency-links/{id}
```

Les liens de competences utilisent la table existante `ouin_exo_competencies`. Le module ne cree pas un second referentiel : si aucune competence BO n'est importee ou creee, les panneaux de liaison restent vides.

Limites actuelles : pas d'IA, pas d'export PDF portfolio, pas de badge projet, pas d'integration GitHub/GitLab, pas de Gantt, pas de temps passe, pas de messagerie ni notification email. La fiche projet est un HTML imprimable, pas un export documentaire avance.

#### Recette manuelle recommandee

1. Activer le module Projects dans `OuInPo Suite > Reglages > Modules`.
2. Verifier que la migration a cree les tables `ouinpo_project*`.
3. Creer un projet depuis l'administration Projects.
4. Verifier la creation des 7 colonnes par defaut.
5. Ajouter deux eleves comme membres.
6. Tester `[ouinpo_my_projects]` avec un eleve membre.
7. Tester le meme shortcode avec un eleve non membre.
8. Creer une tache depuis le Kanban.
9. Deplacer la tache avec les boutons gauche/droite.
10. Ajouter un commentaire via REST ou outil de test.
11. Ajouter une entree de journal.
12. Creer les livrables BTS par defaut depuis l'admin Projects.
13. Deposer une trace avec un eleve membre.
14. Lier une competence BO au projet ou a un livrable.
15. Verifier la fiche `[ouinpo_project_sheet]`.
16. Verifier la vue `[ouinpo_teacher_projects]` avec un compte professeur.


### Gate configurable

Le module Gate se configure depuis l'administration OuInPo Suite, onglet `Gate > Configuration`.

Chaque enigme possede un identifiant stable, un ordre, un etat actif/inactif, un enonce, une aide optionnelle, une reponse de reference, des variantes acceptees, des criteres de validation IA, un niveau, un theme, des messages de feedback, des limites de tentatives, un cooldown et des options IA/fallback. Les reponses de reference et les criteres ne sont pas exposes dans le HTML public.

Lors de la premiere activation avec une configuration absente, OuInPo initialise `ouinpo_gate_questions` a partir de l'ancien corpus de 42 enigmes. Une configuration existante n'est pas ecrasee et les progressions eleves conservees dans `ouinpo_progress` ne sont pas reinitialisees. Les anciennes progressions par index sont relues et migrees progressivement vers les identifiants d'enigmes au prochain enregistrement.

La validation IA Gate est optionnelle et s'appuie sur les reglages IA centralises du plugin lorsque les classes IA sont disponibles. Le serveur envoie uniquement l'enonce, la reponse de reference, les variantes, les criteres, le niveau, le theme et la reponse saisie. L'IA doit repondre en JSON strict avec une decision booleenne et un feedback court. Si l'IA est desactivee ou indisponible, le fallback exact normalise peut etre autorise globalement et par enigme.

La validation IA Gate utilise l'usage dedie `gate_validation`, desactive par defaut dans les reglages IA centraux. Il est donc possible d'autoriser ou couper cette validation sans modifier la correction IA des exercices.

Le badge attribue a la fin du Gate est configurable dans l'administration Gate. Aucune installation ne doit dependre d'un identifiant de badge issu d'un site de production.

Un cooldown anti-spam est controle cote serveur avant chaque validation. Le JavaScript affiche seulement le compte a rebours et evite les doubles clics ; modifier le HTML ne permet pas de contourner le delai. Les logs Gate ne stockent pas les reponses completes par defaut.
### Shortcodes d’intégration

Ces shortcodes ne nécessitent pas forcément une page dédiée :

| Shortcode | Usage |
|---|---|
| `[ouinpo_revision_band]` | Bandeau de révision à placer dans un cours ou une page élève |
| `[ouinpo_hint]...[/ouinpo_hint]` | Contenu conditionnel lié au module Gate |
| `[ouinpo_class_field]` | Champ classe à intégrer dans un formulaire ou une page d’inscription personnalisée |

Shortcodes egalement enregistres : `[ouinpo-flashcards]`, `[ouinpo-exercises]`, `[ouinpo-exercise]`, `[ouinpo-revision-band]`, `[ouinpo-site-map]`, `[ouinpo-practical-subjects]`, `[ouinpo-practical-subject]`, `[segfault_parcours]`.

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

Le lien "En savoir plus" des notices IA est vide par defaut. L'enseignant peut renseigner une URL complete ou un chemin relatif dans les reglages SegFault.

### Prudence sur les contenus indexés par le RAG

Le module SegFault peut utiliser un index documentaire pour répondre aux questions des élèves ou des visiteurs, selon les réglages activés par l’administrateur.

Avant d’activer un accès public au RAG ou au chat IA, il est recommandé de vérifier les contenus indexés afin d’exclure :
- les pages privées ;
- les contenus réservés aux enseignants ;
- les corrections non destinées aux élèves ;
- les données personnelles ;
- les exports techniques ;
- les pages d’administration ou de test.

L’activation d’un accès IA public doit donc être faite volontairement, après vérification du périmètre documentaire exposé.

### Page recommandee : donnees personnelles, IA et usages pedagogiques

Il est recommande de creer une page locale expliquant les usages pedagogiques de l'IA, les donnees a ne pas saisir, les limites des reponses automatiques, les personnes a contacter et le cadre applicable dans l'etablissement. Un modele adaptable est fourni dans `docs/modele-donnees-personnelles-ia.md`.

Avant toute utilisation avec des élèves, il est recommandé de :

- vérifier le cadre applicable dans l’établissement ;
- informer les élèves des usages prévus ;
- éviter l’envoi de données personnelles ;
- désactiver les fonctions IA publiques si elles ne sont pas nécessaires ;
- vérifier les quotas, la disponibilite et le risque d'epuisement des quotas quotidiens.

## Données élèves

Le plugin peut stocker des données de progression, de statut d’exercice, de badges, de devoirs et de révision.

Le module Gate peut stocker, selon les usages actives : la progression par utilisateur et par page, les enigmes resolues, des logs de validation sans reponse complete par defaut, la date de reussite, les signatures, le nom public saisi, le pseudo, le message, l'adresse IP et le user-agent associes a une signature. Ces donnees servent a verifier la completion du Gate, empecher les signatures multiples, afficher le registre et generer un certificat.

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

## Roles et capacites

Les roles actuels sont `ouinpo_teacher` et `ouinpo_student`. Les anciens roles `prof` et `eleve` restent supportes pour compatibilite avec les installations existantes et certains modules historiques. Les deux familles peuvent donc coexister volontairement.

| Profil | Role attendu |
|---|---|
| Administrateur WordPress | Installe le plugin, active les modules, configure les pages, configure l'IA, gere les reglages globaux, les roles et les capacites. |
| Enseignant OuInPo | Gere les exercices, competences, classes/groupes si le module le permet, soumissions, evaluations et badges selon les capacites attribuees. |
| Eleve OuInPo | Consulte les exercices autorises, repond, suit sa progression, utilise les flashcards, accede eventuellement aux badges et au Gate. |
| Visiteur | Accede uniquement aux pages publiques. L'IA publique n'est disponible que si l'administrateur l'active explicitement. |

Les droits reels dependent des modules actives, des options du plugin, des roles/capacites WordPress et des reglages de visibilite des pages. Voir aussi `docs/roles-et-droits.md` et `docs/acces-publics-prives.md`.

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
- `THIRD-PARTY-NOTICES.md`

## Avertissement

OuInPo Suite est un outil pédagogique. Il ne remplace pas l’évaluation professionnelle de l’enseignant.

Les corrections automatiques, suggestions et aides éventuelles doivent toujours être relues et contextualisées par le professeur.

## État du projet

Version bêta technique destinée à des tests contrôlés avant diffusion large.

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

La desinstallation WordPress conserve volontairement les donnees : tables, exercices, competences, flashcards, badges, resultats, historiques et reglages ne sont pas supprimes automatiquement.

La suppression complete doit etre faite manuellement, ou via une future option dediee.

Ce comportement evite la perte accidentelle d'exercices, competences, traces eleves ou contenus pedagogiques.

Avant toute suppression définitive de données OuInPo, l’administrateur du site doit effectuer une sauvegarde complète de la base de données.

Une future version pourra proposer une option explicite de purge complète, mais aucune suppression automatique n’est effectuée par défaut.

## Schéma SQL

Le schéma généré par une installation vierge a été comparé au schéma de production OuInPo.

Les différences restantes sont non bloquantes :

- ordre d’index ou syntaxe phpMyAdmin dans les exports ;
- commentaires SQL absents sur certaines colonnes ;
- contraintes étrangères Flashcards présentes sur installation neuve, car les tables sont créées en InnoDB.
## Securite, IA et acces publics

OuInPo Suite 0.6.0-beta contient une page de reglages IA dans l'administration SegFault. Les administrateurs peuvent y activer ou desactiver l'IA globale et publique, choisir les usages autorises, regler les fournisseurs, URL, cles API, modeles, quotas, parametres de generation, personas, consignes systeme et workflows de correction IA. Les cles API sont stockees comme options WordPress et ne doivent pas etre exportees.

Les routes REST publiques des exercices et sujets pratiques sont maintenant gouvernees par des options d'administration :

- exercices visibles par les visiteurs anonymes ;
- indices visibles par les visiteurs anonymes ;
- solutions visibles par les visiteurs anonymes ;
- sujets pratiques visibles par les visiteurs anonymes ;
- fichiers associes aux sujets pratiques accessibles aux visiteurs anonymes.

Sur une nouvelle installation, ces acces anonymes sont fermes par defaut pour une distribution prudente. Lors d'une mise a jour depuis une version anterieure a 0.5.0, une migration douce conserve les acces publics existants afin de ne pas casser un site deja configure.

Les logs IA/RAG detailles sont desactives par defaut. Les logs synthetiques ne sont emis que si `WP_DEBUG` est actif et si l'option de debug IA/RAG OuInPo est activee. Ils ne doivent pas contenir de cle API, prompt complet, reponse complete de l'IA ou donnee personnelle.

### Quotas IA / Albert

Les quotas internes du plugin doivent rester inferieurs aux quotas Albert disponibles afin d'eviter la saturation, les indisponibilites temporaires, les abus publics et l'epuisement du quota quotidien. Les reglages par defaut sont adaptes a un usage pedagogique encadre :

- visiteurs anonymes : 5 requetes par IP hashee et par minute, 100 par IP hashee et par jour ;
- plafond public global du site : 40 requetes par minute, 4000 par jour ;
- eleves connectes : 15 requetes par minute, 300 par jour ;
- corrections d'exercices : 5 requetes par minute, 120 par jour ;
- corrections de sujets pratiques : 5 requetes par minute, 80 par jour ;
- enseignants : 30 requetes par minute, 1000 par jour.

Les quotas publics restent volontairement limites, meme si Albert propose des plafonds eleves. Un NAT ou un proxy d'etablissement peut faire partager le meme quota IP a plusieurs eleves ; dans ce cas, il faut ajuster avec prudence les limites anonymes et privilegier les comptes connectes.

## Dependances systeme optionnelles

Certaines fonctionnalites utilisent des outils ou fonctions disponibles selon l'hebergement :

- `pdftotext` : optionnel pour extraire du texte de certains PDF lors de l'indexation RAG SegFault. Si l'outil est absent, l'indexation ignore proprement ces PDF ou utilise les fallbacks PHP disponibles.
- `proc_open` avec `python3` ou `python` : optionnel pour verifier la syntaxe Python via `ast.parse` dans les corrections d'exercices. Si la fonction PHP ou l'interpreteur est indisponible, la correction continue sans verification syntaxique automatique.

Recommandation d'hebergement : PHP 8.1+, WordPress 6.4+, fonctions de processus autorisees uniquement si l'hebergeur les encadre correctement, et aucun binaire systeme expose a des utilisateurs non fiables. L'absence de ces dependances ne doit pas provoquer d'erreur fatale.
