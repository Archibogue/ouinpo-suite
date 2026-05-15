# OuInPo Suite

OuInPo Suite est un plugin WordPress destiné aux enseignants de NSI et de SNT.

Il propose un ensemble d’outils pédagogiques pour organiser des exercices, suivre la progression des élèves, gérer des compétences du programme, préparer des devoirs, utiliser des flashcards, attribuer des badges et, si l’enseignant le souhaite, activer certaines fonctions d’assistance par IA.

## Statut

Version 0.5.1-beta : bêta technique destinée à des tests contrôlés avant diffusion large. Le plugin est partageable pour evaluation, installation pilote ou usage encadre, mais il ne doit pas etre presente comme stable pour une diffusion large sans validation sur le site cible.

Modules actifs par defaut sur une installation neuve : `exercises` et `flashcards`. Le module `exercises` est le socle et reste actif. Les autres modules, dont Gate, Submissions, SegFault et RechText, doivent etre actives volontairement depuis l'administration.

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

## Archives et distribution

Le depot GitHub de developpement peut contenir des outils de maintenance, par exemple `.distignore`, `tools/` et `tools/build-dist.ps1`.

Le zip installable WordPress doit contenir le dossier du plugin, son code, ses assets, sa documentation publique et les packs pedagogiques prevus. Il ne doit pas contenir le dossier `dist/`, d'anciennes archives, de dumps SQL, d'exports WordPress personnels, de secrets ou de fichiers locaux.

Le zip de distribution est l'archive partagee aux enseignants. Il peut etre produit depuis le depot de developpement, mais il doit rester directement installable dans WordPress comme une extension classique.

Avant partage, le script PowerShell `scripts/test-dist.ps1` peut verifier rapidement l'archive presente dans `dist/` :

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\test-dist.ps1
```

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

Un ecran d'aide est disponible dans **OuInPo Suite > Premiere configuration**. Il regroupe les liens vers les modules, les pages, l'import pedagogique, les reglages IA, les roles et le diagnostic.

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

Pour une installation de démonstration, importer le pack NSI complet :

```text
packs/ouinpo-pack-nsi-complet.json
```

Ce pack contient quatre niveaux, trois difficultes, dix domaines, vingt-et-une competences, dix-sept exercices dont deux sujets pratiques, ainsi que vingt flashcards. Il utilise des slugs stables pour limiter les doublons : un nouvel import met a jour les contenus portant les memes slugs lorsque l'importeur le permet.

### Tester rapidement avec le pack NSI complet

1. Installer le plugin depuis le zip.
2. Activer l'extension.
3. Ouvrir **OuInPo Suite > Premiere configuration**.
4. Creer ou reparer les pages publiques.
5. Importer `packs/ouinpo-pack-nsi-complet.json`.
6. Creer un utilisateur enseignant et un utilisateur eleve.
7. Tester un exercice public puis une soumission.
8. Tester une flashcard.
9. Tester un sujet pratique.

Après import, vérifier dans l’administration que les exercices, sujets pratiques et flashcards apparaissent bien.

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

### Page recommandee : donnees personnelles, IA et usages pedagogiques

Il est recommande de creer une page locale expliquant les usages pedagogiques de l'IA, les donnees a ne pas saisir, les limites des reponses automatiques, les personnes a contacter et le cadre applicable dans l'etablissement. Un modele adaptable est fourni dans `docs/modele-donnees-personnelles-ia.md` et une page suggeree peut etre creee depuis **Pages & shortcodes**.

Avant toute utilisation avec des élèves, il est recommandé de :

- vérifier le cadre applicable dans l’établissement ;
- informer les élèves des usages prévus ;
- éviter l’envoi de données personnelles ;
- désactiver les fonctions IA publiques si elles ne sont pas nécessaires ;
- vérifier les quotas et les coûts éventuels.

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

Les droits reels dependent des modules actives, des options du plugin, des roles/capacites WordPress et des reglages de visibilite des pages. Voir aussi `docs/roles-et-droits.md`.

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

## Notes de maintenance

Les images du certificat Gate (`parchment_bg.jpg`, `logo_ouinpo.png`) restent a optimiser dans une passe dediee avec un outil graphique fiable. Elles n'ont pas ete regenerees automatiquement afin d'eviter une image degradee ou inutilisable dans le certificat.

## Schéma SQL

Le schéma généré par une installation vierge a été comparé au schéma de production OuInPo.

Les différences restantes sont non bloquantes :

- ordre d’index ou syntaxe phpMyAdmin dans les exports ;
- commentaires SQL absents sur certaines colonnes ;
- contraintes étrangères Flashcards présentes sur installation neuve, car les tables sont créées en InnoDB.
## Securite, IA et acces publics

OuInPo Suite 0.5.0 ajoute une page de reglages IA dans l'administration SegFault. Les administrateurs peuvent y activer ou desactiver l'IA globale et publique, choisir les usages autorises, regler les fournisseurs, URL, cles API, modeles, quotas, parametres de generation, personas et consignes systeme. Les cles API sont stockees comme options WordPress et ne doivent pas etre exportees.

Les routes REST publiques des exercices et sujets pratiques sont maintenant gouvernees par des options d'administration :

- exercices visibles par les visiteurs anonymes ;
- indices visibles par les visiteurs anonymes ;
- solutions visibles par les visiteurs anonymes ;
- sujets pratiques visibles par les visiteurs anonymes ;
- fichiers associes aux sujets pratiques accessibles aux visiteurs anonymes.

Sur une nouvelle installation, ces acces anonymes sont fermes par defaut pour une distribution prudente. Lors d'une mise a jour depuis une version anterieure a 0.5.0, une migration douce conserve les acces publics existants afin de ne pas casser un site deja configure.

Les logs IA/RAG detailles sont desactives par defaut. Les logs synthetiques ne sont emis que si `WP_DEBUG` est actif et si l'option de debug IA/RAG OuInPo est activee. Ils ne doivent pas contenir de cle API, prompt complet, reponse complete de l'IA ou donnee personnelle.

## Dependances systeme optionnelles

Certaines fonctionnalites utilisent des outils ou fonctions disponibles selon l'hebergement :

- `pdftotext` : optionnel pour extraire du texte de certains PDF lors de l'indexation RAG SegFault. Si l'outil est absent, l'indexation ignore proprement ces PDF ou utilise les fallbacks PHP disponibles.
- `proc_open` avec `python3` ou `python` : optionnel pour verifier la syntaxe Python via `ast.parse` dans les corrections d'exercices. Si la fonction PHP ou l'interpreteur est indisponible, la correction continue sans verification syntaxique automatique.

Recommandation d'hebergement : PHP 8.1+, WordPress 6.4+, fonctions de processus autorisees uniquement si l'hebergeur les encadre correctement, et aucun binaire systeme expose a des utilisateurs non fiables. L'absence de ces dependances ne doit pas provoquer d'erreur fatale.
