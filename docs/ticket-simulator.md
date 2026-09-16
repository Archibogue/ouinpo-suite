# PataDesk / TicketSimulator

## Mise en route

Le module `ticket_simulator` est optionnel. L'activer dans **OuInPo Suite →
Réglages → Modules**, puis ouvrir **OuInPo Suite → PataDesk**.

L'activation sur une installation existante initialise les cinq tables et les
droits du module via sa propre version de schéma. Aucun changement de version
globale ni réinstallation du plugin n'est nécessaire.

Dans PataDesk, préparer le scénario de démonstration, choisir **Publié** et
enregistrer. La préparation seule n'insère aucune donnée. Affecter ensuite le
scénario à un ou plusieurs comptes étudiants ou aux classes / sous-groupes
existants. L'attribution de droits de gestion des classes est requise pour ces
affectations. Un compte dont le suivi pédagogique est désactivé ne peut pas
démarrer une tentative.

Une affectation peut être préparée pendant que le scénario est en brouillon,
mais elle ne devient visible à l'étudiant qu'après publication. Si l'affectation
apparaît chez le professeur mais pas chez l'étudiant, sélectionner **Publié**
puis **Enregistrer le scénario**, ou utiliser **Publier et enregistrer le scénario**
dans le panneau **Affectations**. Les affectations déjà enregistrées sont conservées.

Créer une page avec `[ouinpo_ticket_simulator]`. La création de pages de la Suite
propose également cette page lorsque le module est activé. Les pages étudiantes
authentifiées doivent être exclues de la mise en cache intégrale du site, comme
les autres pages privées de la Suite.

## Édition sans code

La page étudiant affiche un **Guide d'utilisation — comment traiter un ticket ?**
dépliable à l'accueil et dans chaque tentative. Il explique la qualification,
les échanges, les ressources, la console, les tests, les notes et la résolution.

L'éditeur propose des formulaires repliables :

- Demandeurs fictifs et spécialistes (aucun compte WordPress créé).
- Ressources : nom affiché, type, langage et contenu pédagogique.
- Tickets : demandeur, description et qualification initiale visible.
- Qualification et solution attendues, réservées au professeur.
- Transitions : cocher les états accessibles depuis chaque état.
- Actions : type, message obligatoire, réponse, coût fictif, éventuels points.
- Prérequis : cocher les actions nécessaires, toutes devant être réalisées.
- Ressources débloquées à l'issue d'une action ou à réception d'une réponse.
- Réponses conditionnelles : la première variante dont tous les prérequis
  sont remplis est choisie ; sinon la réponse par défaut est utilisée.
- Traces attendues à la résolution et éventuelle réouverture.

Les identifiants sont des clés stables (`INC-0001`, `logs`, `dba`, etc.). Les
renommer après avoir établi des liens exige d'actualiser ces liens. Les
références inconnues, doublons et cycles de prérequis sont refusés à la sauvegarde.
Les ressources initialement visibles ou révélées doivent être associées au ticket.

Pour créer un test avant/après correction, rendre l'action de test répétable,
définir une sortie d'échec par défaut, puis ajouter une variante de succès dont
le prérequis est l'action de correction. Pour exiger un véritable test après
correction dans les traces de résolution, créer un test séparé ayant la
correction comme prérequis, comme dans la démonstration.

Les actions `take`, `resolve` et `close` ciblent respectivement `accepted`,
`resolved` et `closed`. Le professeur doit autoriser les transitions correspondantes.
Par défaut, une question passe en `waiting_user`, un avis ou transfert en
`waiting_specialist`, une escalade ou réaffectation en `escalated`. Les échanges
reviennent en `diagnosing` sauf choix explicite ; une réaffectation définitive
conserve par défaut son statut et son assignation. Les avis ne changent pas
l'assignation ; les transferts et escalades la rendent à l'étudiant à la reprise.

L'action **Recevoir la réponse et reprendre** matérialise l'aller-retour.
Le retour doit être autorisé depuis l'état d'attente : par exemple, une question
avec `return_status: "resolving"` exige `resolving` dans les transitions de
`waiting_user`. L'import et l'enregistrement refusent un retour incohérent en
indiquant le ticket et l'action. Les copies des tentatives existantes ne sont pas
réécrites : corriger le modèle ne répare pas une tentative déjà commencée.
Aucun cron, temps d'attente réel ou IA n'est nécessaire. Les demandes aux
spécialistes et communications exigent un texte rédigé. Une demande utilisateur
prédéfinie est historisée avec sa réponse.

## Tentatives et évaluation

### Parcours guidé de traitement des demandes

Dans l'éditeur, choisir **Parcours terminé lorsque tous les tickets sont…** :
résolus ou clôturés. Dans chaque ticket, ouvrir **Accompagnement pédagogique** :

1. Activer le mode guidé et cocher les champs obligatoires souhaités : nature,
   impact, urgence, priorité et justification de priorité.
2. Définir séparément la **nature** (incident, assistance/service, évolution) et
   la **catégorie technique** (applicatif, réseau, système…). Les anciennes
   catégories ne sont jamais converties automatiquement en nature.
3. Sélectionner les traces et tests exigés dans les options de résolution.
   En mode guidé, une qualification vide ou « À qualifier », une action exigée
   absente ou un test exigé non réussi bloque la résolution sans modifier l'état.
   Hors mode guidé, la procédure historique accepter/réouvrir reste applicable.
4. Facultativement, exiger la **validation simulée du demandeur avant clôture**.
   Préparer ses réponses successives : confirmation, ou problème persistant puis
   confirmation. Ce choix est une procédure du scénario, pas une règle universelle
   du BTS. Les réponses ne sont visibles qu'au moment de leur réception.
5. Un retour négatif rouvre le ticket et invalide les tests exigés : l'élève reprend
   le diagnostic, relance les tests puis documente une nouvelle résolution.
   Prévoir les transitions `resolved → reopened`, `reopened → resolved` et une
   action de résolution répétable accessible depuis `reopened`. Les tests doivent
   être relançables depuis cet état ou après retour au diagnostic.

L'élève utilise **Recevoir la validation simulée du demandeur** dans Actions après
résolution. Une confirmation rend la clôture accessible ; tant que le critère de
fin est « clôture », un ticket seulement résolu laisse la tentative en cours.
Avec le critère « résolution », la tentative peut être terminée avant la clôture.
Un ticket rouvert remet le parcours en cours et retire sa date de fin.

Les champs libres sont contrôlés pour leur présence, pas pour leur pertinence.
SegFault propose dans le bilan une **appréciation IA des réponses libres**, fondée
sur des extraits de l'élève et la cohérence avec les traces, sans accès aux corrigés
ni aux critères privés. L'enseignant reste responsable de l'évaluation pédagogique.
Ni la fin du parcours, ni les tests simulés, ni cet avis IA ne certifient B1.2.

Sans les nouvelles options : mode guidé désactivé, aucun champ supplémentaire
obligatoire, aucune confirmation imposée, fin à la résolution. Aucun changement
SQL ni migration des tentatives : les règles proviennent toujours de leur copie
figée. Pour appliquer de nouvelles règles, publier un nouveau scénario et
l'affecter ; **Archiver et recommencer** conserve les règles de l'ancienne copie.

### Console de correction

Dans **Ressources**, ouvrir le fichier révélé puis **Ouvrir la console de
correction**. Modifier l'extrait, cliquer sur **Enregistrer mon code**, puis
relancer le test dans **Tests**. La copie est propre à ce ticket et à cette
tentative. L'historique conserve chaque version enregistrée ; le professeur
peut la consulter en observation. Modifier un extrait invalide ses tests
précédents : il faut les relancer après chaque changement.

Dans l'éditeur professeur, cocher **Autoriser la modification de cet extrait**
sur la ressource et renseigner l'**Extrait corrigé attendu** privé. Créer une
action de type **Test simulé**, sélectionner la ressource dans **Vérifier
l'extrait enregistré**, définir les sorties d'échec et de succès et rendre le
test répétable. Sélectionner ce test dans **Tests devant avoir réussi pour
valider la résolution** si sa réussite doit participer à l'évaluation.

Le test compare le texte enregistré au texte attendu, en normalisant les fins
de ligne et les espaces de fin de ligne. Il ne vérifie pas l'équivalence
sémantique de deux programmes et n'exécute aucune commande. Placer uniquement
l'extrait à corriger dans la ressource pour délimiter la partie modifiable.
Le contenu est limité à 100 Ko. La correction attendue ne transite pas dans
les réponses étudiantes. Les scénarios sans cette option restent compatibles.

La démonstration actuelle remplace le bouton donnant la bonne correction par
l'édition de `ExportService.php`. Les copies déjà enregistrées sur le site et
les tentatives existantes ne sont pas migrées automatiquement : préparer et
publier la nouvelle démonstration (ou configurer les champs ci-dessus), l'affecter
et démarrer une nouvelle tentative. **Réinitialiser** une ancienne tentative
réutilise son ancienne copie du scénario.

Une copie complète du scénario est figée au démarrage. Modifier le modèle ne
change pas les tentatives déjà commencées. Un démarrage répété retrouve la
tentative non archivée de l'étudiant pour ce scénario.

La résolution exige cause, solution, tests, résultat et message final. Le moteur
constate la présence des actions attendues et compare la qualification aux
valeurs attendues. Il ne note pas automatiquement la qualité des textes libres.
Les points facultatifs d'une action sont acquis au plus une fois ; le coût fictif
s'accumule à chaque exécution. Ces points restent privés et ne constituent pas
une note finale. La démarche, les messages et les résultats restent consultables.

**Observer** affiche les événements, notes, résultats, compte rendu et attendus.
L'observation est en lecture seule pour les actions étudiantes. L'archivage et
la réinitialisation nécessitent aussi le droit de gérer le scénario. Réinitialiser
archive l'ancienne tentative et en crée une autre à partir de la même copie du
scénario. Aucune trace n'est effacée. Retirer une affectation empêche les nouveaux
démarrages, sans retirer l'accès aux tentatives déjà reçues.

## Stockage et intégration

### Nettoyage des tentatives de test

Dans l'administration PataDesk, sous **Tentatives et observation**, le bouton
**Supprimer toutes les tentatives** est réservé aux comptes disposant de la gestion
globale PataDesk (administrateur par défaut). Après confirmation, il supprime
définitivement les tentatives de tous les élèves, actives comme archivées, leurs
états de tickets, codes enregistrés, notes et historiques. Les scénarios et les
affectations sont conservés : les élèves peuvent démarrer une nouvelle tentative.
La numérotation affichée et celle des bilans repartent à 1 après le nettoyage
global, même si les anciennes tentatives avaient déjà été supprimées. Les identifiants
internes restent uniques : une ancienne page ouverte ne peut pas agir sur une
nouvelle tentative portant le même numéro affiché. Une erreur SQL annule toute la suppression. Cette opération
concerne les données du site, pas les fichiers Markdown déjà téléchargés.

Le bouton **Supprimer les tentatives de cet élève**, sur chaque ligne de tentative,
permet au gestionnaire global de supprimer tous les essais de cet élève dans tous
les scénarios, archives comprises. Les affectations, scénarios et données des
autres élèves sont conservés. Cette suppression ciblée ne remet pas à zéro la
numérotation globale. Les onglets déjà ouverts deviennent périmés ; aucune page
WordPress ni aucun onglet du navigateur n'est supprimé à distance.

### Bilan étudiant au format Markdown

Dans **Qualifier / affecter le ticket**, l'élève choisit l'impact (Faible, Moyen,
Élevé), l'urgence (Faible, Moyenne, Élevée) et la priorité (Basse, Normale, Haute,
Critique) dans des menus déroulants. La priorité reste un choix de l'élève selon
le contexte et le SLA ; sa justification peut être enregistrée dans Notes.
Les valeurs personnalisées des scénarios existants restent sélectionnées et
conservées tant que l'élève ne les remplace pas. Cliquer sur **Enregistrer la
qualification** pour valider les choix.

Lors du téléchargement, SegFault analyse le bilan étudiant et ajoute une section
**Conseils de SegFault** : priorisation selon impact, urgence et SLA, vérifications
simples avant interventions lourdes, communication et tests après correction.
Les recommandations sont des retours pédagogiques IA, sans notation automatique.
Le contexte inclut les limites de la simulation, les actions actuellement proposées,
les actions déjà réalisées et les ressources révélées. SegFault est instruit de
citer ces actions et de ne pas demander de commandes SQL, d'accès à une machine
réelle ou de tests en production. Pour un ticket clôturé ou une tentative archivée,
les améliorations concernent une prochaine tentative. Les actions futures cachées
ne sont pas transmises. Les anciens conseils en cache sont invalidés par cette révision.
Les corrigés privés et les réponses futures ne sont pas transmis à l'IA.

Cette fonction réutilise le pont IA de SegFault et ses fournisseurs configurés.
Elle nécessite l'activation globale de l'IA et de l'usage **Suggestions pédagogiques**
(`ouinpo_ai_usage_pedagogical_suggestions`). Les quotas IA étudiant par minute et
par jour s'appliquent dans un compteur dédié aux bilans. Les conseils d'un même
bilan sont réutilisés pendant une heure pour le même utilisateur ; un changement
du travail enregistré déclenche une nouvelle analyse. Le bilan étudiant est envoyé
au fournisseur, avec les notes et le code enregistrés, sans l'identifiant du compte.

Si l'IA est désactivée, indisponible, le quota atteint ou le contexte dépasse 60 Ko,
le fichier complet reste téléchargeable et indique pourquoi les conseils manquent.
Le bouton utilise `POST /attempts/{id}/summary`. L'ancien appel `GET` sur cette
route inclut également les conseils pour les pages déjà ouvertes ou en cache.
L'export sans IA est disponible via `GET /attempts/{id}/summary/plain`.
Aucun appel réseau n'est effectué pendant le verrouillage SQL.

Vérification locale : `php tools/check-ticket-advice.php` (pont IA simulé).

Le bouton **Télécharger mon bilan (.md)**, en haut de la tentative, produit
`patadesk-tentative-ID.md`. Il fonctionne pendant le travail, après résolution
et en lecture seule sur une tentative archivée. Le fichier reprend le contexte,
la progression, la qualification des tickets, les actions, échanges, notes,
résultats des tests, le dernier code enregistré et les comptes rendus.

Le bilan utilise toutes les pages du journal, et non uniquement les événements
déjà affichés dans le navigateur. Les modifications non enregistrées ne sont
pas incluses. Il ne contient ni réponses futures, ni correction attendue, ni
score privé du professeur. L'accès est limité au propriétaire de la tentative
et aux observateurs autorisés. Aucun fichier public n'est créé sur le serveur.

Route : `GET /attempts/{id}/summary`, avec les mêmes contrôles de session et
nonce que les autres routes. Réponse JSON : `filename` et `markdown`, téléchargée
par le navigateur sous forme de fichier UTF-8. Le verrou de tentative maintient
la cohérence entre les états et le journal pendant la génération.

Vérification dédiée : `php tools/check-ticket-markdown.php` (droits, exclusion
des données privées, échappement et pagination au-delà de 500 événements).

### Supprimer définitivement un scénario

Le bouton **Supprimer** figure dans la liste professeur ; **Supprimer le scénario**
est aussi disponible dans l'éditeur d'un scénario enregistré. Une confirmation
explicite annonce la suppression du scénario, de toutes ses affectations
(utilisateurs, classes, sous-groupes) et de toutes ses tentatives, actives ou
archivées, avec tickets, code enregistré et événements. Les autres scénarios
et leurs données sont conservés. Les comptes étudiants et les classes ne sont pas supprimés.

Cette action est réservée au propriétaire disposant du droit de gestion ou à
l'administrateur global. Elle est irréversible après succès. Le nonce, la
confirmation et la révision sont contrôlés côté serveur. Une transaction
annule l'ensemble en cas d'échec ; les créations d'affectations ou tentatives
partagent le verrou du scénario pour éviter les données orphelines.
Utiliser l'archivage pour conserver les traces.

Namespace : `Ouinpo\Suite\Modules\TicketSimulator`.

| Table (préfixe WordPress omis) | Rôle |
| --- | --- |
| `ouinpo_ticket_scenarios` | Définition JSON versionnée, auteur et publication |
| `ouinpo_ticket_assignments` | Cibles existantes : utilisateur, classe, sous-groupe |
| `ouinpo_ticket_attempts` | Étudiant, professeur, copie du scénario, dates et révision |
| `ouinpo_ticket_attempt_tickets` | État courant par ticket et tentative |
| `ouinpo_ticket_events` | Journal paginé, ordonné par identifiant |

Tables InnoDB créées avec `$wpdb` / `dbDelta()`. Option de version :
`ouinpo_ticket_schema_version`. Les mutations utilisent une transaction et un
verrou de ligne sur la tentative, complétés par une révision contrôlée. Une
requête issue d'un onglet périmé reçoit une erreur 409 ; actualiser avant de
réessayer. L'édition des scénarios possède également un contrôle de révision.

Le module suit `ModuleInterface`, `Core\Assets`, les capabilities et le système
de pages existant. Le moteur métier est indépendant du contrôleur et du stockage.
`TestEngineInterface` prévoit l'injection d'autres moteurs ; le MVP utilise
uniquement `SimulatedTestEngine`, qui sélectionne du texte préparé.

Pour changer le nom affiché, utiliser le filtre `ouinpo_ticket_simulator_name`.
Les identifiants techniques et le shortcode restent stables. Le titre d'une
page WordPress déjà créée reste modifiable dans l'éditeur de pages.

### Droits

- `ouinpo_ticket_manage_scenarios` : gérer ses scénarios.
- `ouinpo_ticket_view_attempts` : observer les tentatives de ses scénarios.
- `ouinpo_ticket_practice` : consulter / traiter ses propres tentatives,
  sous réserve des règles existantes de suivi pour l'écriture.
- `ouinpo_ticket_manage_all` : administration globale.

Les enseignants reçoivent les deux premiers droits ; les étudiants reçoivent le
troisième ; les administrateurs reçoivent tous les droits. Les réglages de droits
de la Suite les présentent automatiquement. Une capability seule ne remplace
pas les contrôles de propriété de chaque scénario ou tentative.

### API REST

Préfixe : `/wp-json/ouinpo-ticket-simulator/v1`.
Authentification par session WordPress et en-tête `X-WP-Nonce` (`wp_rest`).

- `GET/POST /scenarios`, `GET/PATCH /scenarios/{id}` : professeur.
- `DELETE /scenarios/{id}` : suppression définitive en cascade, avec
  `confirm_delete: true` et `revision` entière, propriétaire autorisé ou administrateur.
- `GET /demo` : définition de démonstration, professeur uniquement.
- `GET /targets?search=...` : recherche d'étudiants / classes existants.
- `GET/POST /scenarios/{id}/assignments` : affectations.
- `GET /assignments`, `POST /assignments/{id}/attempts` : étudiant.
- `GET /attempts`, `GET /attempts/{id}` : filtrage selon les droits.
- `GET /attempts/{id}/events?after=...` : 500 événements par page.
- `PATCH /attempts/{id}/tickets/{ticket}` : qualification.
- `POST .../actions/{action}` : simulation ; `__reply` est réservé au moteur.
- `POST .../notes` : note technique.
- `POST .../resources/{resource}` : consulter une ressource révélée et journaliser.
- `PATCH .../resources/{resource}/code` : enregistrer un extrait modifiable avec
  `content` et `revision`, sous les mêmes contrôles de propriété et de verrouillage.
- `POST /attempts/{id}/archive`, `POST /attempts/{id}/reset` : gestion professeur.

Les mutations étudiantes envoient la propriété JSON entière `revision`.
Les vues étudiantes sont construites par liste de champs autorisés, sans les
réponses futures, conditions, attendus, scores ni copie brute du scénario.
Les contenus des ressources sont récupérés séparément après contrôle d'accès.
Le code reste du texte et n'est jamais interprété ni lu depuis un chemin fourni.
Le scénario embarqué est lui-même protégé contre la consultation HTTP directe.

## Import / export

Modèle prêt à importer et à dupliquer :
[`modele-scenario.json`](../tools/templates/patadesk/modele-scenario.json).
Le [guide du modèle](../tools/templates/patadesk/README.md) décrit les champs,
références, tests de code et étapes de publication. Ce fichier contient les
corrections professeur : il reste dans `tools/`, hors archive publique du plugin.

Le bouton d'export produit la définition JSON du scénario en cours d'édition.
Le bouton d'import charge cette définition dans l'éditeur ; l'enregistrement
est explicite et applique la même validation que la création manuelle.
Version de format : `format_version: 1`. Les tableaux racine sont `users`,
`specialists`, `resources` et `tickets`. Actions et variantes sont imbriquées
dans chaque ticket. Le format est propre à PataDesk, distinct des packs généraux.

Limites actuelles : scénario de 1 Mo maximum, 100 éléments par collection racine,
200 actions par ticket, 50 variantes par action. Listes administratives : 200
scénarios / tentatives récents et 100 étudiants par recherche. Une recherche
permet d'affecter d'autres étudiants. L'administration à très grande échelle
nécessitera une pagination supplémentaire des scénarios et tentatives.

## Vérifications

```powershell
php tools/check-ticket-simulator.php
php tools/check-ticket-deletion.php
node tools/check-ticket-preview.mjs
php tools/check-ticket-pedagogy.php
php tools/check-ticket-advice.php
php tools/check-ticket-markdown.php
php tools/check-ticket-cleanup.php
node --check assets/js/front/ticket-simulator.js
node --check assets/js/admin/ticket-simulator-admin.js
php tools/check-class-subgroups.php
php tools/verify-packs.php
```

Le test autonome utilise des doublures WordPress / base de données. Il vérifie
le moteur réel, les permissions, les nonces, les refus de routes et l'absence
d'écritures sur les requêtes refusées. Il ne remplace pas un test MySQL.

`check-ticket-preview.mjs` démarre un serveur PHP éphémère sur la boucle locale,
teste les assets et le parcours de démonstration via HTTP, puis arrête le serveur.
Il n'automatise pas le navigateur. Les contrôles PHP (dont parcours B1.2,
compatibilité, export, IA simulée, suppression et modèle d'import) et le parcours
HTTP ont réussi dans l'environnement de développement ; la recette WordPress/MySQL et
l'inspection visuelle responsive n'y ont pas été exécutées.

Un aperçu isolé utilisant le moteur PHP réel est fourni pour la recette UI :

```powershell
php -S 127.0.0.1:8765 tools/ticket-simulator-preview.php
```

Ouvrir `http://127.0.0.1:8765/` pour l'élève et `/?admin=1` pour le professeur.
Ce serveur utilise uniquement une session de démonstration ; il ne teste pas
les permissions WordPress ni le schéma SQL. Le routeur refuse tout usage hors
du serveur PHP de développement en boucle locale. Les outils sont exclus du
paquet de distribution par le script de build existant.

### Recette sur WordPress de test

1. Activer le module sur une installation existante puis vérifier ses cinq
   tables avec un préfixe de base différent de `wp_`.
2. Créer et publier le scénario de démonstration ; l'affecter à deux étudiants.
3. Avec le premier : prendre en charge → diagnostiquer → question et réponse →
   logs → code → demande au DBA et réponse → console de correction → enregistrement → test → résolution
   avec les cinq champs → confirmation simulée du demandeur → clôture.
   Qualifier d'abord la nature, l'impact, l'urgence, la priorité et sa justification.
4. Avec le second : tenter une résolution prématurée et vérifier le blocage guidé.
   Préparer également une réponse « problème persistant », vérifier la réouverture,
   refaire les tests puis résoudre, recevoir la confirmation et clôturer.
   Confirmer que la progression du premier et le modèle n'ont pas changé.
5. Ouvrir la tentative en observation ; vérifier notes, demandes, tests et attendus.
6. Modifier le modèle : la tentative existante garde l'ancienne copie.
7. Depuis les outils réseau du navigateur, vérifier les réponses 403 pour nonce
   absent/invalide, tentative étrangère, scénario professeur et ressource cachée.
8. Envoyer deux actions avec la même révision : une seule réussit, l'autre est refusée.
9. Affecter une classe puis un sous-groupe existant ; vérifier les appartenances,
   le retrait d'un élève du groupe et le refus de nouveaux démarrages non autorisés.
10. Archiver / recommencer : ancienne chronologie conservée, nouvelle tentative indépendante.
11. Désactiver / réactiver : routes, menu et shortcode retirés puis rétablis ; données conservées.
12. Vérifier au clavier et à 390, 768 et 1440 px : navigation, formulaires,
    code avec numéros de lignes, copie et absence de débordement horizontal.
13. Vérifier les pages existantes Exercices, Flashcards et Projects sur le thème cible.

### Périmètre MVP

Pièces jointes représentées par des ressources textuelles ; pas de dépôt binaire.
Pas de coloration syntaxique ajoutée : aucune bibliothèque commune adaptée n'a
été identifiée dans les assets inspectés. Le code est présenté en police fixe
avec lignes numérotées. Les SLA sont indicatifs, les délais sont fictifs et aucun
moteur de commandes réelles n'est exposé. L'appréciation IA du texte libre est
indicative ; l'évaluation finale reste humaine. Pas de suppression automatique, de portfolio généré ni de collaboration
multi-étudiants dans une même tentative.
