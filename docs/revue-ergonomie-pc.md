# Revue ergonomique globale — OuInPo Suite sur PC

Date : 20 septembre 2026.

## Périmètre et méthode

Revue statique du code courant : navigation Suite, interfaces enseignant et élève, CSS des modules et thèmes, interactions JavaScript. Les modifications locales en cours, notamment PataDesk et Submissions, sont incluses dans l'état examiné. Aucun code fonctionnel n'a été modifié.

Cette revue ne constitue pas une recette visuelle sur WordPress : le thème actif, les extensions, les données réelles, le zoom et la largeur des pages peuvent modifier le résultat. Les défauts directement observables dans le code sont distingués des risques et propositions à valider. Le module Meta n'a pas de parcours de travail comparable aux modules pédagogiques ; il ne justifie pas une refonte ergonomique propre.

## Diagnostic général

La Suite possède de bonnes bases pour le PC : navigation par tâches, tableaux, filtres, grilles, édition de code au clavier, interfaces de correction et séparation de plusieurs états pédagogiques. Le problème principal est l'hétérogénéité des comportements et de l'organisation entre modules.

La direction recommandée est une interface de travail cohérente : contexte de classe et de ressource visible, navigation stable, listes efficaces, zone de travail suffisamment large, actions explicites et protection des saisies. Une simple réduction des marges ou un élargissement général des textes ne suffirait pas.

## Constats prioritaires

### 1. PataDesk : les brouillons ne survivent pas au rechargement — priorité haute

**Constat établi.** `assets/js/front/ticket-simulator.js:107` conserve les brouillons dans une `Map` en mémoire de page. La restauration lors des changements d'affichage est prévue, mais pas après fermeture ou rechargement. Le message de remise précise que les saisies non enregistrées ne sont pas incluses (`:181`).

**Conséquence.** Un élève qui actualise, ferme son onglet ou remet trop vite peut perdre du travail ou remettre une version antérieure à celle qu'il vient de saisir.

**Recommandation.** Ajouter un état global « Modifications non enregistrées », un avertissement de sortie uniquement lorsque nécessaire et une sauvegarde de brouillon liée à l'utilisateur côté serveur. Avant remise, proposer explicitement d'enregistrer les modifications ou de revenir les vérifier. Préserver la distinction entre enregistrer, résoudre un ticket et remettre la copie. Éviter de laisser des réponses persistantes dans le stockage local d'un PC partagé.

**Recette.** Saisir une note, changer de ticket, revenir, recharger, simuler une erreur d'enregistrement, remettre : chaque étape doit rendre évident ce qui est conservé et ce qui sera remis.

### 2. Flashcards : plusieurs notations peuvent partir avant la fin de l'enregistrement — priorité haute

**Constat établi côté interface.** `grade()` et les gestionnaires de clic dans `assets/js/front/flashcards.js:718` et `:790` n'installent ni verrou de requête ni désactivation des boutons. La carte suivante est affichée après la requête de notation puis le rafraîchissement des paquets.

**Conséquence.** Sous latence, un double clic ou deux clics sur des appréciations différentes peuvent envoyer plusieurs requêtes pour la même carte. L'effet exact sur la progression dépend du traitement serveur ; il n'a pas été reproduit ici.

**Recommandation.** Verrouiller l'ensemble des appréciations durant l'enregistrement, afficher « Enregistrement… », permettre une reprise après erreur et vérifier la protection serveur contre les doublons. Rafraîchir les indicateurs secondaires sans retarder inutilement la carte suivante.

**Recette.** Sous réseau ralenti, double cliquer puis alterner deux appréciations : une seule décision doit être prise en compte.

### 3. Suivi enseignant : largeur des filtres incompatible avec certains conteneurs PC — priorité haute

**Constat établi dans la structure CSS.** `assets/css/front/teacher-competencies.css:53` impose cinq colonnes d'au moins 160 px, plus un bouton et cinq espacements de 14 px : plus de 870 px sont nécessaires avant même la largeur du bouton. Le passage à deux colonnes dépend d'une fenêtre de moins de 980 px (`:280`).

**Conséquence à vérifier visuellement.** Une fenêtre de 1366 px peut conserver cette grille alors que le thème WordPress laisse seulement 800 px au module. Le risque concerne donc aussi les ordinateurs, pas uniquement les téléphones.

**Recommandation.** Adapter les filtres à la largeur du conteneur, comme le fait déjà PataDesk, ou utiliser une grille flexible. Conserver les libellés et une action de filtrage clairement située.

**Recette.** Dans une fenêtre de 1366 px, tester un conteneur de 800 px puis de 1100 px : aucun filtre ne doit sortir de la zone de travail.

### 4. Projects : rechargements complets pendant un travail composé de plusieurs formulaires — priorité haute

**Constat établi.** Les opérations sur le journal, les livrables et les traces appellent `window.location.reload()` ; par exemple `assets/js/front/projects.js:293`. `preserveScroll()` préserve la position, ce qui est utile, mais ne sauvegarde pas les autres champs de la page.

**Conséquence à reproduire selon la composition de la page.** Enregistrer un livrable pendant la rédaction d'une autre zone peut interrompre le travail et faire perdre les saisies voisines. La récupération éventuelle des champs par le navigateur ne constitue pas une garantie.

**Recommandation.** Actualiser seulement le bloc concerné, conserver le focus et les autres saisies, afficher une confirmation locale. Protéger aussi les formulaires contre les soumissions répétées.

### 5. Navigation : trop de niveaux simultanés pour les tâches fréquentes — priorité moyenne

**Constat établi.** `src/Core/Admin/SuiteAdmin.php:172` définit jusqu'à neuf entrées principales, dont « Première configuration ». La Suite ajoute des onglets et sous-onglets à la navigation WordPress (`:211`, `:254`).

**Risque ergonomique.** Sur portable, la navigation et ses retours à la ligne peuvent occuper une part importante de la hauteur. Plusieurs notions se croisent : Préparer, Révisions, Évaluations, Suivre, IA & parcours.

**Recommandation.** Conserver les verbes métier. Après installation, déplacer la première configuration dans Réglages ou dans un encart contextuel. Donner à chaque outil une entrée principale prévisible, avec des raccourcis depuis les parcours connexes. Réserver l'accès aux réglages techniques au contexte approprié. Ajouter `aria-current="page"` aux liens actifs et nommer les différentes navigations.

### 6. Retours d'action hétérogènes — priorité moyenne

**Constat établi.** Projects, compétences et exercices utilisent encore des `alert()` pour diverses erreurs ; PataDesk et Flashcards disposent de messages intégrés et de zones de statut. Exemples : `assets/js/front/projects.js:303`, `assets/js/admin/admin-competencies.js:358`, `assets/js/front/exercises.js:3186`.

**Conséquence.** Les boîtes bloquantes interrompent les séries de corrections et séparent l'erreur du champ concerné. Des messages tels que « Écriture du statut impossible » n'indiquent pas suffisamment la marche à suivre.

**Recommandation.** Standardiser les états « En cours », « Enregistré », « Échec — Réessayer », avec un message près de l'action, annoncé aux technologies d'assistance. Conserver une confirmation explicite pour une suppression ou une remise engageante.

### 7. Gate : formulaire d'édition difficile à lire et à identifier au clavier — priorité moyenne

**Constat établi.** `src/Modules/Gate/plugin/ouinpo-gate.php:1285` et les champs suivants utilisent largement les placeholders pour distinguer titre, énoncé, aide, réponse de référence et critères. Plusieurs champs n'ont pas de libellé individuel associé ; des termes comme « fallback exact » ou « Cooldown » restent visibles.

**Conséquence.** Les indications disparaissent après saisie et l'édition de plusieurs énigmes oblige à mémoriser la signification des champs.

**Recommandation.** Ajouter des libellés persistants et associés. Regrouper « Énoncé », « Réponse attendue », « Aides » et « Validation avancée ». Employer « Délai entre deux essais » et « Vérification exacte si l'IA est indisponible ». Prévoir une prévisualisation élève.

## Revue par domaine

| Domaine | Base à conserver | Évolution recommandée pour le PC |
|---|---|---|
| Accueil et configuration | Tableau de bord, configuration guidée, activation facultative des modules | Mettre en premier les tâches en attente et les accès fréquents ; rendre la configuration moins présente une fois terminée. |
| Banque d'exercices | Filtres, catégories, cartes et progression | Proposer une vue liste compacte pour comparer beaucoup de ressources ; conserver recherche et filtres au retour d'un exercice. Vérifier ce dernier comportement en situation réelle. |
| Résolution d'exercices | Brouillons locaux existants, aides, indentation et sortie par Échap puis Tab | Rendre explicite la différence entre brouillon local et réponse enregistrée ; garder les commandes proches de la réponse ; proposer énoncé et réponse côte à côte lorsque la largeur le permet. |
| Sujets pratiques et annales | Zones de code, contenu structuré, largeurs de lecture limitées | Prévoir un mode de travail large avec navigation entre questions et accès stable à la sauvegarde. Conserver une largeur raisonnable pour la prose à l'intérieur de cet espace. |
| Flashcards | Choix des paquets, révélation, retour de notation et annonces `aria-live` | Corriger d'abord les requêtes concurrentes ; ajouter ensuite des raccourcis affichés pour révéler et noter, actifs uniquement dans la session et hors saisie. |
| Compétences et suivi | Filtres métier, indicateurs et certains éléments sticky | Corriger la grille des filtres ; rendre l'élève et la compétence identifiables pendant le défilement des grands tableaux ; prévoir une densité compacte lisible. |
| Classes, groupes et affectations | Partage par classe, groupe ou personne | Afficher un résumé des destinataires avant validation, notamment quand « Toute la classe » rend la sélection de groupes sans effet restrictif ; conserver le contexte de classe entre tâches liées. |
| Devoirs et évaluations | Concepteur déjà organisé en deux colonnes (`assessment-builder.css:1`) | Maintenir sélection, total et action principale visibles ; vérifier la largeur réellement disponible avec le menu WordPress ouvert, particulièrement autour du seuil de 1000 px. |
| Dépôts et ressources | Parcours élève/professeur, aperçu Markdown récemment ajouté | Recetter la lisibilité des limites de fichier avant dépôt, la progression, la confirmation de réception et l'accès au retour professeur ; favoriser un panneau de prévisualisation dans les listes de correction. |
| Projects | Kanban horizontal, actions explicites, journal, exports et impression | Supprimer les rechargements risqués ; proposer une vue liste en complément du Kanban pour les grandes quantités ; garder les boutons de changement d'état même si un glisser-déposer est ajouté. |
| PataDesk | Adaptation au conteneur, focus visible, brouillons internes, file de tickets et correction côte à côte | Fiabiliser la persistance ; rendre l'action attendue et l'état de remise immédiatement visibles ; vérifier la hauteur occupée par l'en-tête et la file sur portable. |
| SegFault / IA | Sources, messages, mode plein écran | Le widget de base est limité à 680 px et son historique à 360 px (`segfault.css:9`, `:94`) : ajouter un mode latéral adapté au travail simultané avec l'exercice ; vérifier le retour du focus et la sortie au clavier du plein écran. |
| Gate | Progression et verrouillage durant la validation déjà présents | Simplifier l'éditeur, expliciter les délais et les échecs ; vérifier le focus après apparition d'une nouvelle énigme. |
| RechText | Deux visualisations côte à côte, pas à pas et lecture automatique | Garder les commandes à portée de vue ; désactiver Précédent/Suivant aux bornes ; ajouter des raccourcis locaux. Les gestionnaires bornent actuellement l'étape sans désactiver ces boutons. |
| Badges, titres et parcours autonomes | Progression et distinction de l'apprenant autonome | Donner priorité au prochain objectif atteignable ; utiliser une confirmation intégrée pour le changement de titre plutôt qu'une succession de boîtes de dialogue. |
| Réglages et apparence | Thèmes séparés, diagnostic et modules optionnels | Distinguer réglages pédagogiques courants et paramètres techniques avancés ; montrer l'effet attendu de chaque option sans exposer les détails techniques dans les parcours élèves. |

Les propositions de cette matrice sont des orientations de conception, sauf lorsqu'un comportement précis et sa source sont indiqués. Elles ne signifient pas que tous les éléments proposés sont absents de tous les écrans.

## Règles communes à adopter

1. **Dimensionner selon la tâche.** Espace large pour tableaux, Kanban et correction ; largeur de lecture contenue pour énoncés et consignes. Éviter une largeur maximale identique pour tous les usages.
2. **Tenir compte de la hauteur.** Sur portable, titres, aides, filtres et indicateurs doivent laisser apparaître rapidement le début du travail. Replier les explications secondaires, sans masquer les consignes nécessaires.
3. **Adapter au conteneur.** Une fenêtre large ne garantit pas une page WordPress large. Généraliser l'approche déjà utilisée par PataDesk aux zones qui peuvent être intégrées dans une colonne.
4. **Maintenir le contexte.** Classe, groupe, élève, ressource et état doivent rester identifiables. Une action sur une ligne ne doit pas effacer filtres, sélection et saisies indépendantes.
5. **Harmoniser les actions.** Une action principale par zone ; actions secondaires moins saillantes ; suppression distincte. Employer des verbes précis : Enregistrer le brouillon, Remettre, Publier les résultats, Archiver.
6. **Unifier les composants.** `assets/css/core/base.css` reste essentiellement vide, tandis que les modules et thèmes définissent leurs variantes. Construire progressivement un socle commun de boutons, champs, états, tableaux et focus, sans supprimer les identités visuelles des thèmes.
7. **Rendre le clavier efficace.** Garder les contrôles natifs, un ordre de focus logique et une sortie de toutes les zones. Les raccourcis complètent les boutons ; ils ne doivent pas intercepter la rédaction. Les éditeurs Exercices et Pratique proposent déjà une sortie Échap puis Tab, à conserver.
8. **Favoriser la lecture durable.** Réserver les petites tailles aux métadonnées secondaires ; plusieurs styles descendent à 11–12 px ou 0,7 rem. Vérifier la taille calculée et le contraste dans chaque thème avant de conclure à un défaut visuel.

## Ordre de réalisation proposé

**Lot 1 — Fiabilité du travail.** Brouillons et remise PataDesk ; verrouillage des Flashcards ; sauvegardes Projects sans rechargement global ; filtres du suivi adaptés au conteneur.

**Lot 2 — Cohérence quotidienne.** Messages d'état communs ; hiérarchie des actions ; libellés Gate ; navigation active accessible ; conservation du contexte dans les listes.

**Lot 3 — Productivité sur PC.** Vues compactes et panneaux de détail ; correction côte à côte ; commandes persistantes lorsque pertinentes ; raccourcis locaux ; mode latéral de l'assistant.

**Lot 4 — Harmonisation visuelle.** Composants partagés, alignements, typographie, espacements et vérification des trois thèmes. Une refonte décorative seule ne résoudrait pas les problèmes des trois premiers lots.

## Recette à effectuer sur le site cible

Tester dans des fenêtres de 1366 × 768, 1440 × 900 et 1920 × 1080 ; tester également une fenêtre partagée de 960 px de large, le zoom à 125 %, 150 % et 200 %, et un conteneur WordPress de 800 px dans une fenêtre large. Ces dimensions sont des cibles de recette, pas des mesures déjà réalisées.

Parcours :

- Enseignant : retrouver une classe, affecter une ressource à un groupe, ouvrir une copie, commenter, noter et publier.
- Élève : reprendre un exercice, écrire du code, enregistrer, consulter une aide et retrouver son travail.
- Flashcards : révéler et noter une série sous réseau ralenti, avec double clic volontaire.
- Projects : préparer deux saisies, enregistrer l'une puis vérifier la conservation de l'autre.
- PataDesk : qualifier, dialoguer, modifier du code, changer de ticket, revenir, recharger et remettre.
- IA : ouvrir l'assistant pendant la rédaction, changer de mode, fermer et retrouver le champ initial.
- Accessibilité clavier : parcourir les commandes, changer d'onglet, sortir des éditeurs et repérer les erreurs sans souris.
- Volumétrie : listes de nombreuses ressources, classe complète, Kanban chargé, intitulés longs et messages d'erreur détaillés.

Critères : absence de défilement horizontal de toute la page ; défilement local autorisé pour code et grands tableaux ; action principale accessible ; aucun champ masqué par un élément fixe ; aucune perte silencieuse de saisie ; confirmation d'enregistrement compréhensible ; focus visible et conservé de façon logique. Mesurer les clics et déplacements nécessaires sur ces parcours avant et après modification.

## Conclusion

La priorité PC est de rendre la Suite fiable et continue dans les tâches longues : conserver le travail, exploiter la largeur utile, limiter les ruptures et harmoniser les commandes. Les bases existent déjà dans plusieurs modules ; il faut les généraliser avant de multiplier les nouvelles présentations.
