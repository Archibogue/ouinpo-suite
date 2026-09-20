# Ergonomie PC — lot 1

Version : **0.7.10-beta**, finalisée le 21 septembre 2026 sur `main`.

## Comportements livrés

- **PataDesk** : brouillons privés sauvegardés sur le serveur après 800 ms de pause, restauration après rechargement, état de sauvegarde visible pendant le défilement sur ordinateur, reprise après erreur et avertissement de sortie si une sauvegarde reste en attente. Aucun texte élève n'est stocké dans le stockage local du navigateur.
- **Validation PataDesk** : sauvegarder un brouillon n'envoie pas de message et ne valide pas le travail. Chaque formulaire garde sa validation ; ses brouillons sont consommés dans la transaction de l'action. La remise refuse les brouillons restants côté serveur. L'abandon demande confirmation.
- **Flashcards** : une seule notation en cours, commandes désactivées pendant l'enregistrement, confirmation sous la carte et retour du focus. Un échec secondaire du rafraîchissement ne transforme pas une notation réussie en échec apparent. Les indicateurs de préparation ne sont plus répétés pendant la session.
- **Projects** : journal, livrables et traces actualisent uniquement leur liste. Les formulaires voisins et fichiers sélectionnés sont conservés. Le formulaire envoyé est réinitialisé après succès. Les doubles soumissions sont bloquées ; une reprise de lecture ne répète pas l'écriture. Les sélecteurs de livrables voisins du même projet sont synchronisés en conservant le choix existant ; un choix supprimé revient à « Aucun ».
- **Livrables Projects** : titres plus lisibles, cellules alignées en haut, actions disposées dans une cellule de tableau, types et statuts traduits en français sans modifier les valeurs API.
- **Suivi enseignant** : filtres et indicateurs dimensionnés selon le conteneur. Message explicite en l'absence de données et compteur « Élèves avec données », distinct de l'effectif inscrit.
- **Administration** : navigations nommées, lien courant identifié par `aria-current="page"`, contour de focus clavier visible.
- **RechText** : commandes désactivées aux bornes, arrêt immédiat en fin de lecture, reprise possible en revenant en arrière et apparence distincte des boutons indisponibles.

## Migration et droits

Le schéma PataDesk passe à 3, avec une colonne nullable `drafts` dans les tentatives. Les anciennes tentatives commencent sans brouillons. Les brouillons ont une révision indépendante de la simulation et sont supprimés avec la tentative. Un autre élève ou un enseignant observateur ne peut pas les lire dans la vue élève ni les modifier. Les restrictions de suivi, remise et archivage s'appliquent.

Un conflit entre fenêtres refuse l'enregistrement : la saisie locale est conservée et l'utilisateur est invité à la copier avant de recharger. Il ne s'agit pas d'une édition collaborative.

La route Projects de lecture d'une section est authentifiée, soumise aux droits du projet et interdite de mise en cache. Seule la liste est remplacée.

## Contrôles automatisés

- Syntaxe PHP du dépôt et syntaxe des scripts JavaScript modifiés.
- Flashcards : concurrence, échec secondaire, reprise et focus.
- Projects : double soumission, saisies voisines, échec d'envoi, changement de statut, reprise de lecture, options de livrables et isolation entre projets.
- PataDesk : autosauvegarde, saisie pendant une requête, restauration, révision obsolète, limites, rollback, droits, remise, isolation et changement de ticket pendant une sauvegarde. Régressions : évaluation, dialogues, remise à zéro, suppressions, nettoyage élève, présentation et espace de travail.
- RechText : bornes, arrêt de la lecture, retour en arrière et réinitialisation.
- `tools/check-patadesk-wordpress.php` : **57 contrôles réussis** sur WordPress/MariaDB et HTTP local, dont migration depuis des tables anciennes isolées, idempotence et préservation des données.
- Vérification des packs et test du ZIP WordPress par `scripts/test-dist.ps1`.

## Recette réelle sur bsiotest.local

Chrome, GeneratePress, formats 1366 × 768 et 1920 × 1080 ; suivi enseignant également vérifié à 1000 px. Aucun débordement horizontal observé sur les vues contrôlées.

- Flashcards : révélation, notation, commandes occupées, passage à la carte suivante, confirmation et focus.
- Projects : ajouts au journal et aux livrables avec saisies voisines conservées ; nouveau livrable disponible sans rechargement. Envoi réel de `recette-ergonomie.txt` réussi, lien de téléchargement affiché et texte du journal voisin conservé.
- PataDesk : brouillon de note retrouvé à l'identique après rechargement puis validé. Deux onglets sur la même tentative : A sauvegarde, B reçoit un conflit et conserve sa saisie ; après rechargement de A, la version A reste présente.
- Suivi enseignant : classe BTS de 12 élèves fictifs, trois compétences `[TEST]` et 36 statuts variés. Filtres classe/élève et vue par domaine contrôlés, avec un libellé de compétence long. Les vues devoirs/exercices ont été vérifiées sans résultats ; cette recette ne couvre pas des copies de devoirs remplies.
- RechText : lecture jusqu'à 7/7, boutons Suivant/Lecture désactivés, reprise après retour en arrière, aspect du bouton Précédent désactivé vérifié après correction CSS.
- Administration : repères courants « Préparer » et « Exercices » confirmés dans le DOM.

## Réglages et données de recette

La base et les fichiers locaux ont été sauvegardés avant la première synchronisation. Les données fictives de recette sont conservées sur bsiotest.local et ne sont pas intégrées au dépôt ni au ZIP. Aucun e-mail de recette envoyé. Projects et RechText ont été activés localement pour la recette.

Les pages pédagogiques locales utilisent le réglage GeneratePress par page « Sans colonne latérale » (`_generate-sidebar-layout-meta = no-sidebar`). Les valeurs précédentes ont été sauvegardées dans un fichier temporaire `ouinpo-sidebar-before-*.json`. Ce réglage est propre au site : il doit être reproduit dans les options de mise en page du site cible. Le plugin ne masque pas globalement les widgets.

## Limites et lot suivant

La version reste une bêta. La recette couvre ce lot sur Chrome et le thème local ; elle ne remplace pas la vérification sur le site cible avec ses données, ses thèmes et ses autres navigateurs. Le menu automatique du thème, les espaces de correction et l'assistant IA restent des chantiers distincts de cette étape.
