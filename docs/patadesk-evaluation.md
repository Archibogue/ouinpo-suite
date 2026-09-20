# PataDesk : parcours BTS SIO et évaluations

## Priorité d’utilisation de l’interface

**Préférence confirmée le 20 septembre 2026 : usage principalement sur ordinateur de bureau ou PC portable.** Conserver une largeur confortable, des listes lisibles à la souris et au clavier, et la copie à côté de la correction lorsque le conteneur est suffisamment large. Le téléphone et la tablette sont des compatibilités secondaires ; ils ne doivent pas dicter l’organisation de l’interface sur ordinateur.

## Cadre pédagogique

Le [référentiel BTS SIO diffusé par CERTA](https://www.reseaucerta.org/wp-content/uploads/sujets/public/BTS_ServicesInformatiquesOrganisations2019.pdf), page PDF 32, distingue la collecte, le suivi et l’orientation des demandes, le traitement des services réseau/système/applicatifs et celui des applications. Ce sont des attendus du diplôme ; le module ne couvre pas seul le bloc 1.

Le [guide d’accompagnement du 30 octobre 2020](https://www.reseaucerta.org/wp-content/uploads/sujets/public/GuideAccompagnement30oct.pdf), pages 14 à 17, recommande un contexte d’organisation, des acteurs, des ressources, un outil de tickets et des engagements de service ; le code proposé doit être adapté à la progression. Ces deux documents ont été consultés depuis la [page CERTA](https://www.reseaucerta.org/bts-sio/) le 20 septembre 2026. Le lien « référentiel révisé » de cette page étant mal formé, le PDF a été ouvert directement.

Les documents locaux suivants ont été consultés **en lecture seule** dans `E:/Documents/Cours_BTS_SIO` : progression du bloc 1, grille de couverture S1, planning des évaluations et `AP_Debug_Portail_SPOPI/README.md`.

| Progression locale | Usage de PataDesk | Preuve à apprécier humainement |
| --- | --- | --- |
| Semaines 2 à 4 | Demande brute, fiche, qualification, priorité argumentée, réponse et orientation | Pertinence de la reformulation, des informations recherchées et du destinataire |
| Semaines 5 à 7 | Tickets préqualifiés, diagnostic et intervention sur le portail local ou script fourni | Reproduction, hypothèse/test, résultat, correction et vérification |
| Documentation support puis CMS | Références de fichiers et bilan liés au ticket | Procédure effectivement testée, article et réglages réalisés hors PataDesk |
| Évolutions de sites | Activités distinctes, traces et grille personnalisable | Modification effective et vérification ; un ticket seul ne prouve pas B1.3 |

Le découpage en semaines est un choix local. La décision du 20 septembre dans le planning prévaut sur les mentions antérieures de pratique en S1-04 : **l’évaluation du vendredi 25 septembre 2026, 9 h–10 h, reste sur papier, sans ordinateur**. Aucun sujet de cette évaluation n’a été créé ou modifié. L’AP original reste une séance de deux heures, en binômes, sans note ; les nouveaux imports sont des variantes distinctes, à affecter explicitement.

Fin de parcours, comparaison automatique, note pédagogique, conseil IA et acquisition d’une compétence sont des notions distinctes. Aucune note ne déclenche une certification B1.2. Le temps fictif et les points historiques d’action n’entrent jamais dans le calcul de la note.

## Préparer un scénario

Les imports `tools/templates/patadesk/SPOPI-*-v2.json` conservent les anciens fichiers :

- `SPOPI-qualification-v2.json` : une demande brute, qualification et réponse initiale ; fin sans résolution.
- `SPOPI-orientation-v2.json` : même entrée, avec orientation documentée supplémentaire.
- `SPOPI-diagnostic-essentiel-v2.json` : T01, T03 et T05 préqualifiés ; trois interventions sur le portail local. Les comparaisons de code restent indicatives et ne bloquent pas une correction extérieure pertinente.
- `SPOPI-approfondissements-v2.json` : T02/T04, évolutions et passerelles support/réseau, dans une autre activité. Aucun point perdu dans le parcours essentiel si cette activité n’est pas réalisée.

Importer un fichier dans l’éditeur PataDesk, le relire, l’enregistrer et le publier. Les imports contiennent des attendus privés : ne pas distribuer ces JSON aux élèves. Le générateur local `node tools/build-patadesk-spopi-v2.mjs` régénère uniquement les quatre variantes ; il ne lit ni n’écrit les fichiers des cours.

L’éditeur propose les fins de parcours **Qualifiés**, **Orientés**, **Résolus**, **Clôturés**. Pour les deux premières, l’élève utilise **Terminer cet exercice** : le statut technique reste inchangé. Les traces de contexte, d’informations recherchées et de réponse initiale sont exigées ; l’orientation ajoute son propre écrit. La fiche doit exister pour une demande brute. Les contrôles ne jugent que la présence. Une remise notée peut rester incomplète.

Chaque ticket peut déclarer des traces supplémentaires exigées et être marqué comme extension facultative. Une grille ne peut pas viser directement un ticket facultatif ; préférer une activité séparée, y compris pour les critères portant sur l’ensemble du scénario.

La nature (`incident`, `service`, `evolution`) est distincte de la catégorie technique. **Note technique interne** produit un événement interne, jamais un message au demandeur. Les anciennes définitions ne sont pas réécrites : pour corriger un ancien import, créer sa nouvelle version et changer le type de l’action concernée.

La grille de priorité et les engagements sont des textes éditables et visibles. Les variantes donnent une correspondance **locale à ce dossier** P1/Haute, P2/Normale ; ce n’est pas une règle universelle. Le contrat fictif distingue prise en charge, absence de délai de résolution garanti, périmètre et escalade. L’échéance de séance n’est pas un SLA. L’enseignant peut valoriser une priorité différente si sa justification est cohérente.

## Affecter un entraînement ou une évaluation

Dans **Affectations**, ouvrir **Créer une activité distincte : entraînement ou évaluation notée**.

1. Donner un nom parlant, choisir le mode et un élève, une classe ou un sous-groupe existant.
2. Configurer ouverture, échéance, nombre de tentatives (1 à 100), refus du retard ou remise signalée.
3. Choisir les indices, conseils IA et dialogues IA autorisés. Le passage au mode noté les désactive tous par défaut ; une activation explicite reste possible.
4. Choisir un modèle de grille : qualification, diagnostic ou intervention complète. Modifier, ajouter ou retirer les critères sans JSON. Renseigner intitulé, attendu public, maximum, compétence facultative, indications privées et portée.
5. Choisir éventuellement une date minimale de publication. **La publication reste manuelle**, même après cette date.
6. Créer l’activité pour la cible sélectionnée. Refaire l’opération pour une autre cible si nécessaire.

Les dates sont saisies dans le fuseau du navigateur, converties en UTC et affichées en UTC à l’élève. Aucun chronomètre individuel n’est imposé. Chaque création produit une affectation indépendante, y compris pour le même scénario et la même cible. L’affectation historique simple reste un entraînement sans ces nouvelles contraintes.

Les réglages et la grille sont copiés au démarrage. Cette première version crée des activités distinctes ; elle ne propose pas d’édition rétroactive de leurs paramètres. Pour changer les consignes, retirer l’activité et en créer une nouvelle. Une réouverture permet de prolonger explicitement l’échéance de la copie concernée.

Les aides contrôlées sont les actions cochées **Aide facultative** dans l’éditeur ; les ressources et échanges nécessaires à la mission restent accessibles selon leurs règles habituelles. Ne pas incorporer un corrigé à une ressource publique, à une description ou à une réponse simulée. Les corrigés doivent rester dans les champs privés. Les restrictions sont vérifiées sur les actions, dialogues et exports côté serveur ; changer l’interface ou appeler directement une route ne les contourne pas. L’activation locale de l’IA n’outrepasse pas sa désactivation globale dans la Suite.

## Travail de l’élève

L’élève retrouve le nom de l’activité, sa période, ses aides et sa grille. Dans **Traces pédagogiques**, il enregistre sa reproduction, son hypothèse/test, le résultat observé, la correction, la vérification, des preuves textuelles ou références de fichiers et un bilan court. Les saisies en cours sont conservées en mémoire de page pendant la navigation, mais doivent être enregistrées.

La console conserve son fonctionnement : comparaison de texte à une référence, sans exécution du programme. Un écart n’établit pas que le code est incorrect. Les manipulations sur le portail SPOPI ou le script fourni se déroulent **hors WordPress**. Les chemins de fichiers restent du texte : aucun fichier local n’est lu et aucun code étudiant n’est exécuté sur le serveur.

**Remettre mon travail** affiche les traces manquantes et demande confirmation. Une copie incomplète est acceptée. Seules les données enregistrées sont remises. La copie devient en lecture seule ; actions, notes, code, dialogues, remise à zéro et redémarrage de cette copie sont bloqués. Les anciens bilans sans IA restent téléchargeables. Une nouvelle tentative, si autorisée, reçoit un autre identifiant ; elle conserve toutes les copies précédentes et compte dans la limite de l’affectation.

Les quatre états sont indépendants du statut des tickets : travail en cours, travail remis, correction en cours, résultat publié. Un ticket peut rester ouvert dans une copie remise. Une absence de remise ne reçoit jamais automatiquement zéro.

## Correction et publication

Dans l’accueil professeur, ouvrir **Remises, correction et export CSV**. Filtrer par affectation, classe, sous-groupe et état. Les filtres de classe/groupe utilisent la cible enregistrée et les appartenances actuelles des élèves affectés individuellement ; un changement ultérieur d’appartenance peut donc modifier ce dernier classement. Les élèves n’ayant jamais démarré ne figurent pas dans cette liste de tentatives.

Ouvrir une copie. Le panneau d’évaluation réunit les versions remises, leurs bilans avec preuves et historique, les attendus privés, la grille et l’historique privé des corrections. Les tickets visibles dans le reste de la page décrivent le travail courant ; pour une copie rouverte, corriger la **version remise** affichée dans le panneau.

Attribuer les points et les commentaires, puis une appréciation générale. Le champ **Preuves effectivement vérifiées** permet de consigner références et méthode de vérification par l’enseignant ; il ne transforme pas les déclarations de l’élève en résultats automatiques.

Une case de points vide signifie **à corriger**, pas zéro. Les valeurs négatives ou supérieures au maximum sont refusées. Le total du barème doit être positif. La note vaut `20 × points attribués / total`, arrondie à deux décimales, avec demi supérieur. Elle reste indéfinie tant qu’un critère manque.

**Enregistrer la correction en brouillon** n’expose rien à l’élève. **Publier explicitement le résultat** exige tous les critères corrigés et le respect de la date minimale éventuelle. Après publication, la note, les commentaires et l’appréciation figurent dans la vue élève et dans son bilan Markdown.

Modifier une correction publiée la replace en brouillon et masque le résultat courant jusqu’à une nouvelle publication explicite. L’ancienne publication et chaque correction enregistrée restent dans l’historique privé. Un export que l’élève a déjà téléchargé n’est évidemment pas retiré de son ordinateur.

**Rouvrir la copie** exige un motif. Donner une nouvelle échéance si l’ancienne est dépassée avec retard interdit ; la réouverture seule ne prolonge pas le délai. La nouvelle remise ajoute une version, sans effacer l’ancienne. Le barème et le scénario des remises antérieures restent inchangés. Seuls l’enseignant propriétaire autorisé et l’administrateur global peuvent corriger, rouvrir ou publier. Un observateur extérieur et un autre élève sont refusés.

L’export CSV applique les mêmes filtres et droits ; il exporte seulement les notes publiées. Les cellules pouvant être interprétées comme formules sont préfixées d’une apostrophe. Il n’intègre pas les indications privées. Aucune assistance IA à la notation n’a été ajoutée : la notation est entièrement manuelle.

## Réutilisation et données

Réutilisés : scénarios et snapshots, qualifications et demandes brutes, moteur de simulation, ressources, notes internes et échanges, événements paginés, export Markdown, pont IA et quotas, classes/sous-groupes, capabilities et contrôle de propriétaire, transactions et révisions anti-concurrence.

Les mécanismes existants d’Exercises (barèmes par questions/appels, indications IA) et Submissions (remises de documents) ont été examinés. Ils n’exposent pas de service autonome correspondant aux copies multi-tickets avec snapshots PataDesk. La nouvelle classe `Assessment` conserve donc les données dans le module, sans coupler les tickets aux notes d’un autre module ni modifier Submissions.

Migration interne de schéma **1 → 2**, sans changement de version publique du plugin :

- `assignments.settings` : paramètres et grille ; `activity_key` distingue plusieurs activités sur une même cible.
- Remplacement de l’ancienne unicité par `(scenario_id,target_type,target_id,activity_key)` ; les anciennes lignes gardent une clé vide.
- `attempts.assessment` : paramètres figés, état de remise, snapshots des copies, corrections et historique.
- Les anciennes tentatives restent en entraînement ; leur scénario et leurs données ne sont pas convertis.

Une remise conserve scénario, états, événements paginés, grille et bilan. Les écritures de remise/correction verrouillent la tentative et incrémentent sa révision. Les données de correction ne passent pas par le journal public des événements. La version de migration n’est enregistrée qu’après vérification des tables, colonnes et nouvel index.

Les suppressions administratives irréversibles déjà présentes restent disponibles avec leurs droits et confirmations ; elles suppriment aussi les évaluations contenues dans les tentatives. Ne pas utiliser le nettoyage des essais sur les copies à conserver. Il n’y a ni purge automatique ni déploiement réalisé par cette modification.

## Vérifications réalisées et recette restante

Tests locaux réussis (doublures WordPress/base, pas une installation WordPress réelle) :

| Commande | Résultat |
| --- | --- |
| `php tools/check-ticket-assessment.php` | 176 contrôles, dont 60 nouveaux couvrant barèmes, confidentialité, remise/réouverture, isolation, limites de tentatives et périodes |
| `php tools/check-ticket-simulator.php` | 116 contrôles du moteur et des droits |
| `php tools/check-ticket-pedagogy.php` | 175 contrôles, parcours historiques B1.2 |
| `php tools/check-ticket-markdown.php` | 139 contrôles, export et pagination |
| `php tools/check-ticket-advice.php` | 158 contrôles, IA simulée et export |
| `php tools/check-ticket-deletion.php` / `check-ticket-cleanup.php` | 144 / 137 contrôles |
| `php tools/check-patadesk-comptanova.php` | Import local et parcours complet réussis |
| `node tools/check-ticket-workspace.mjs` | Interface sur DOM simulé, édition et lecture seule |
| `node tools/check-ticket-preview.mjs` | Aperçu HTTP historique réussi ; ne teste pas les évaluations sous WordPress |
| PHP lint du module ; `node --check` des deux interfaces | Réussite |

Les nombres incluent des contrôles communs : ne pas les additionner comme autant de tests distincts. La vérification des variantes inclut leur validation par le moteur réel. Les tests de comparaison de code restent des tests de simulation.

**À exécuter sur WordPress/MySQL de test avant utilisation notée :**

1. Migration d’une base version 1 remplie, préfixe personnalisé, réactivation, index unique et conservation des anciennes copies. Éprouver aussi un échec de migration et sa reprise.
2. Deux élèves, deux enseignants et un administrateur ; mêmes scénarios affectés en entraînement et en noté ; vérification des appartenances classe/sous-groupe.
3. Appels REST directs avec mauvais nonce, mauvaise révision, copie étrangère ; actions/code/notes/ressources/dialogues/export avant et après remise ; vérifier l’absence de correction privée dans toutes les réponses élèves.
4. Ouverture future, échéance dépassée, retard autorisé signalé, deux onglets démarrant/remettant simultanément et épuisement du quota de tentatives.
5. Remise incomplète, brouillon partiel sans zéro automatique, publication refusée si incomplète, réouverture motivée avec prolongation, seconde remise et invariance de la première.
6. Modification du scénario après démarrage et après remise ; note publiée modifiée puis republiée ; export Markdown avant/après publication et CSV ouvert dans un tableur.
7. IA globale désactivée puis configuration explicite autorisée ; indices marqués ; confirmer les refus réseau sans appel fournisseur quand l’activité interdit l’IA.
8. Recette visuelle au clavier et à 390/768/1440 px : grille, copies longues, preuves, formulaires, boutons, annonces d’erreur et navigation. Tester le thème cible et les autres modules existants.

### Recette réelle sur BSIO_Test — 20 septembre 2026

À la demande supplémentaire de l’utilisateur, la copie de travail a été installée
uniquement sur **Local / BSIO_Test**, `http://bsiotest.local` : WordPress 7.1.1,
PHP 8.3.17, MariaDB 10.6.23, Apache 2.4.43. Aucune version publique n’a été publiée
et aucun site de production n’a été modifié. Les fichiers PHP PataDesk et ses
deux fichiers JavaScript ont été comparés par empreinte au dépôt : identiques.

Le script CLI [check-patadesk-wordpress.php](../tools/check-patadesk-wordpress.php)
refuse l’exécution HTTP et tout site autre que l’installation locale nommée
`bsiotest`. Il charge réellement WordPress et utilise les repositories, le REST
WordPress et des appels HTTP authentifiés passant par Apache. Il empêche l’envoi
de courriels depuis le processus de recette. Les sessions HTTP créées par le
script sont invalidées après les tests.

**Dernière exécution : 56 contrôles réussis, aucun échec.**

- Création des colonnes et index sur MariaDB ; migration réelle de tables au
  schéma 1 sous un préfixe de recette isolé, ancien snapshot conservé,
  affectation historique en entraînement, répétition idempotente.
- Même scénario et même élève : entraînement et évaluation distincts ; démarrage
  répété, remise obligatoire avant seconde tentative, plafond effectif.
- Exercice de qualification terminé avec ticket toujours `new` ; note interne
  enregistrée comme `technical_note`, sans message utilisateur.
- Ouverture future et échéance passée bloquées ; retard autorisé marqué sur la remise.
- Remise incomplète, verrouillage, correction privée, publication visible,
  réouverture et seconde remise conservant intégralement la première.
- Autre élève et autre enseignant refusés ; nonce invalide refusé ; appel REST
  direct de notation par un élève refusé ; export Markdown avant/après publication.
- HTTP authentifié réel : lecture filtrée, nonce, refus d’écriture après remise,
  refus d’accès étranger et téléchargement du bilan sans IA.
- Page réelle avec shortcode et chargement de l’asset ; export CSV et filtre
  d’affectation sur les données réelles.

La sauvegarde de la base avant activation est conservée **hors du dossier public
et du dépôt**, dans le dossier parent du site de test Local. Son chemin propre
au poste n’est pas inclus dans la documentation distribuée.
Les comptes `pdqa_*`, scénarios `[RECETTE ...]` et pages `Recette PataDesk ...`
créés lors des exécutions restent identifiables sur le site pour consultation.
La dernière page est
[Recette PataDesk](http://bsiotest.local/recette-patadesk-20260920_141638/).
Dernier jeu : scénario 4, affectations entraînement 7 et notée 8, tentatives
10 et 11, professeur 14, élèves 15 et 16, autre professeur 17. Ce sont uniquement
des comptes fictifs de recette, sans notes d’élèves réels. Le dernier test détruit
ses cinq tables de migration temporaires après vérification ; le premier essai
de migration a conservé ses tables sous `wp_pdqa_20260920_141514_`.

L’environnement CLI Local signale une extension Imagick indisponible ; les
56 contrôles ci-dessus passent malgré cet avertissement, et aucune fonction
de traitement d’image n’est utilisée par cette recette.

**Restait à faire à l’issue de la recette initiale :** inspection visuelle et clavier
(réalisée ensuite, voir ci-dessous), classes/sous-groupes réels,
concurrence effective de deux requêtes, reprise d’une migration SQL interrompue,
ouverture du CSV dans un tableur, fournisseur IA réellement activé et recette
des autres modules sur le thème cible. Les tests HTTP du shortcode ne prouvent
pas le bon rendu ni l’interaction JavaScript de tous les formulaires.

## Améliorations de présentation et recette Chrome — 20 septembre 2026

Cette intervention porte sur le rendu, les métadonnées des lectures autorisées et les filtres. Aucun cours, scénario enregistré, copie, note, droit, menu ou page n’a été modifié. Les fichiers du module ont été synchronisés uniquement sur `BSIO_Test`, sans nouvelle version publiée.

- Progression pédagogique, statut technique et remise/correction ont des indications distinctes. Un exercice de qualification terminé ne demande plus une résolution ou une prise en charge supplémentaire. Pour une activité notée, « À remettre » reste une information séparée.
- Titres de scénario, noms d’activité et noms des élèves autorisés remplacent les identifiants comme information principale. États français dans les vues et le CSV ; identifiants conservés comme repères secondaires. Le CSV garde ses six colonnes.
- Les dates sont affichées en français dans le fuseau du navigateur, nommé à côté des dates et des champs de saisie (`Europe/Paris` pendant la recette). Les horodatages UTC des copies sont adaptés à l’affichage sans réécrire leur source.
- Les anciennes notes contenant le JSON des traces sont décodées en rubriques textuelles, y compris les accents échappés. Un format invalide reste du texte. Les copies utilisent le parseur Parsedown déjà embarqué dans la Suite, en mode sûr, suivi de `wp_kses_post` ; les images distantes sont exclues du rendu.
- Les attendus privés et l’historique de correction restent dans les sections réservées à l’enseignant, séparés du document élève. Le récapitulatif distingue les critères vides des zéros, indique les points saisis, le total et l’état de publication. Aucun calcul de note ni règle de remise/publication n’a changé.
- Les critères s’étendent naturellement, sans hauteur maximale ni défilement interne. Copie et correction sont côte à côte à partir de 960 px disponibles dans le module ; en dessous, elles s’empilent. Le tableau des remises se transforme en cartes sous 760 px disponibles. Ces seuils dépendent du conteneur, pas seulement de l’écran.
- Les filtres proposent les activités, classes et groupes présents dans les tentatives autorisées, avec recherche nominative d’élève. Chargement, résultat vide et erreur disposent de messages. Les réponses tardives d’une vue quittée ne contaminent plus la suivante.

### Titre du shortcode

`[ouinpo_ticket_simulator show_title="auto"]` est le comportement par défaut : le module omet son titre sur une page singulière possédant déjà un titre WordPress. Aucun style ne masque les titres du thème.

- `show_title="no"` : ne jamais répéter le titre dans le module.
- `show_title="yes"` : afficher le titre du module, notamment si le thème masque lui-même le titre de page.

Aucune modification du contenu de la page locale n’était nécessaire. Sur `/patadesk/`, un seul titre PataDesk est constaté et le module conserve **1 120 px** de largeur à une fenêtre de 1440 px.

### Tests effectivement réalisés

Chrome connecté au compte administrateur existant, données `[RECETTE 20260920_141638]`, sans enregistrer de formulaire :

| Écran / interaction | Vérification |
| --- | --- |
| Accueil et tentatives | 1440, 768 et 390 px ; recherche, noms accessibles, titre unique, listes vides |
| Tentative 10, qualification terminée | Ticket toujours Nouveau ; parcours terminé ; aucune prochaine étape technique exigée |
| Notes et historique du ticket | Rubriques lisibles, accents, dates ; activation clavier de l’historique ; repli à 768 et 390 px |
| Remises | Tableau sur ordinateur, cartes sur conteneur étroit ; filtre activité 8 donnant deux tentatives ; filtre résultat publié et recherche inconnue donnant un état vide ; recherche nominative validée avec Entrée |
| Tentative 11, copies et correction | Deux copies conservées, Markdown rendu, historique remise/brouillon/publication/réouverture ; deux colonnes à 1440 px, une à 768 et 390 px |
| Zéro / non corrigé | Saisie locale de zéro : 1/5 critères renseignés, 0/20 points ; autres champs vides. Aucun enregistrement et aucune publication ; saisies abandonnées en quittant la vue |
| Affectations et barème | Cibles nommées, dates avec fuseau, critères sans défilement interne, tous les contrôles visibles étiquetés ; 1440, 768 et 390 px ; passage clavier du maximum de points au repère de compétence |

Aucun débordement horizontal constaté sur ces vues : largeur du document inférieure ou égale à celle de la fenêtre ; aucun élément visible du module au-delà du bord droit. Les captures ont notamment permis de corriger une légende de tableau trop étroite en mode cartes. Le focus clavier est visible sur les boutons et champs testés. Les surcharges de taille du navigateur sont retirées après les contrôles.

Contrôles automatisés :

- `check-ticket-assessment.php` : 176 vérifications, incluant les règles de note et de confidentialité existantes.
- `check-ticket-pedagogy.php` : 175 vérifications ; `check-ticket-markdown.php` : 139 vérifications. Ces totaux incluent des contrôles communs, ils ne s’additionnent pas comme des tests indépendants.
- `check-ticket-workspace.mjs`, `check-ticket-drafts.mjs` : rendus éditables/lecture seule et isolation des saisies.
- `check-ticket-presentation.mjs` : traces traitées comme texte, dates UTC, qualification terminée, remise encore attendue, titre masqué, zéro distinct de vide, récapitulatif et réponses asynchrones tardives.
- `check-ticket-presentation.php` : rendu Markdown, ancien JSON, accents, source inchangée, blocage du contenu actif, retrait des images, dates et traduction des anciens états.
- `check-patadesk-ui-readonly.php` sur le vrai WordPress local : lectures autorisées enseignant/élève, liste élève limitée à ses tentatives, refus de copie étrangère, absence du brouillon/historique/HTML privé côté élève, rendu sûr avec le véritable assainissement WordPress, six colonnes CSV et recherche vide.

Le dernier script utilise uniquement les identités de recette dans le processus PHP, sans créer de session navigateur, changer de droit ou réinitialiser de mot de passe. Une empreinte des cinq tables PataDesk est comparée avant/après les lectures, et reste identique.

**Limites précises :** aucun navigateur connecté avec un compte élève n’était disponible. Les contrôles de confidentialité serveur passent avec le compte élève existant, mais le parcours visuel élève connecté, ses boutons de remise et l’affichage d’une note publiée restent à vérifier en session élève. Les fixtures sont affectées individuellement et ne possèdent pas de classes/sous-groupes : les sélecteurs vides sont vérifiés, leur filtrage sur une classe ou un groupe réellement peuplé reste à tester. Aucun scénario de sauvegarde/publication n’a été rejoué sur les données existantes lors de cette recette d’interface. L’avertissement Imagick de PHP CLI est antérieur à ces changements.

### Navigation WordPress proposée — réglages à appliquer séparément

Pour éviter le menu automatique de toutes les pages, créer un menu dédié dans **Apparence → Menus**, puis l’affecter à l’emplacement principal de GeneratePress. Décocher l’ajout automatique des nouvelles pages de premier niveau.

| Entrée principale | Destination / sous-entrées existantes |
| --- | --- |
| Accueil | Accueil du site |
| S’entraîner | Exercices, Flashcards, PataDesk |
| Évaluations | Épreuve pratique |
| Mon suivi | Mes compétences, Mes badges |

Garder le suivi enseignant dans l’administration de la Suite. Réserver « Carte du site » et « Données personnelles, IA et usages pédagogiques » au pied de page. Ne pas inclure les pages `Recette PataDesk ...`, `Sample Page`, ni les pages techniques « Exercice » et « Sujet pratique » dans le menu principal : elles peuvent rester publiées et accessibles par leurs liens utiles. Ne supprimer aucune page. Conserver la largeur actuelle du contenu et l’absence de barre latérale sur PataDesk.

Ces réglages sont une proposition : **aucun menu ni réglage GeneratePress n’a été changé automatiquement**.
