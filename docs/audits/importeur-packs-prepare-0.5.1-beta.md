# Audit des changements importeur de packs depuis prepare-0.5.1-beta

Branche auditee : `prepare-0.5.1-beta` comparee a `main`.

Fichiers compares :

- `src/Core/PedagogicalPackImporter.php`
- `packs/ouinpo-pack.schema.json`
- `packs/ouinpo-pack-nsi-complet.json`
- `packs/README.md`
- `src/Core/Installer.php`

## Synthese

La branche `prepare-0.5.1-beta` contient un vrai chantier d'importeur v2, pas une simple correction documentaire. Les changements les plus visibles sont l'ajout d'un rapport d'import plus detaille, d'une tentative de transaction SQL avec rollback, d'erreurs bloquantes, d'un schema JSON formel et d'un nouveau pack NSI complet.

Ces changements semblent utiles, mais ils modifient le comportement d'import en profondeur. Ils doivent rester isoles tant qu'ils n'ont pas ete testes manuellement dans WordPress sur installation vierge et sur installation contenant deja des contenus.

## Fonctionnalites nouvelles ajoutees

- Statut global d'import : `success`, `partial`, `failed`.
- Tableaux `warnings` et `errors` plus structures.
- Tentative de transaction SQL : `START TRANSACTION`, `COMMIT`, `ROLLBACK`.
- Compteurs plus fins pour les niveaux, difficultes, domaines, competences, exercices, liens niveaux, liens competences, indices, solutions, metadonnees bac, sujets pratiques, appels pratiques, fichiers pratiques, decks et flashcards.
- Wrappers SQL internes `insertOrFail`, `updateOrFail`, `deleteOrFail`, `queryOrFail`, avec distinction entre erreurs bloquantes et avertissements.
- Import plus strict des donnees invalides : plusieurs cas qui etaient des avertissements deviennent bloquants.
- Nouveau pack `packs/ouinpo-pack-nsi-complet.json` avec niveaux, difficultes, domaines, 21 competences, 17 exercices dont 2 sujets pratiques, et 20 flashcards.
- Schema JSON `packs/ouinpo-pack.schema.json` transforme en schema draft 2020-12, avec proprietes documentees.

## Le schema des packs change-t-il ?

Oui, le fichier de schema change fortement.

Le champ `schema_version` reste `1.0`, donc le changement n'annonce pas une rupture de version de schema. En revanche le fichier devient un vrai schema JSON et formalise des champs qui existaient deja ou etaient implicites :

- `pack.pack_version`
- `pack.plugin_min_version`
- `school_levels`
- `domains`
- `level_slug`
- `level_slugs`
- `exam_meta`
- `practical_calls`
- `practical_files`
- `flashcards.cards[].competency_slugs`
- `badges` reserve pour evolution future

Point de vigilance : comme `schema_version` reste `1.0`, un ancien pack et un nouveau pack peuvent sembler appartenir au meme format alors que les attentes de validation et de rapport changent. Avant fusion, il faudrait decider si ce schema reste compatible `1.0` ou s'il merite une version explicite plus haute.

## Les anciens packs restent-ils compatibles ?

Probablement oui pour les packs simples, mais a verifier.

Les signaux favorables :

- `schema_version: "1.0"` reste accepte.
- Le schema autorise `additionalProperties`.
- Les tableaux historiques `school_levels`, `difficulties`, `competencies`, `exercises`, `flashcards`, `badges` restent dans le vocabulaire.
- Les champs historiques de compatibilite des competences (`level`, `domain`, `domain_slug`) restent presents.

Les risques :

- Des entrees invalides qui etaient ignorees ou transformees en avertissements peuvent maintenant provoquer un echec.
- Les erreurs de liens de niveaux ou de competences peuvent devenir bloquantes selon le chemin d'import.
- L'import supprime et remplace certains liens et contenus rattaches a un exercice avant de les recreer ; c'est coherent pour un import idempotent, mais risqué si un pack ancien est incomplet.
- Les flashcards semblent plus strictes sur la presence du deck, des cartes, du recto et du verso.

Conclusion : compatibilite plausible, mais pas garantie sans tests avec les packs existants de `main` et avec un pack minimal ancien.

## Les imports existants risquent-ils de casser ?

Oui, le risque existe.

Les principaux points de casse possibles :

- Transaction SQL indisponible ou partielle selon le moteur et les tables. Le code signale ce cas, mais il faut verifier le comportement reel sur MySQL/MariaDB et tables WordPress existantes.
- Rollback attendu mais impossible si certaines tables ne participent pas a une transaction.
- Erreur bloquante en milieu d'import apres suppression de liens ou contenus associes si la transaction n'est pas effective.
- Reimport d'un pack incomplet pouvant retirer puis recreer des indices, solutions, metadonnees ou liens, avec risque de perte de personnalisation locale.
- Practical subjects : un `exam_type` `practical_subject` sans `practical_calls` devient une erreur.
- Flashcards : cartes incompletes ou deck mal forme peuvent interrompre l'import.
- Le rapport d'import admin dans `SuiteAdmin.php` devra rester aligne avec les nouvelles clefs de details si ce chantier est repris.

## Y a-t-il des migrations necessaires ?

Pour l'importeur lui-meme, le diff ne montre pas de migration de schema dediee aux packs. Le schema des packs est surtout applicatif et documentaire.

En revanche, `src/Core/Installer.php` change aussi dans `prepare-0.5.1-beta` :

- ajout d'une contrainte unique `user_page` sur les signatures Gate ;
- suppression defensive des doublons Gate existants avant creation de l'index ;
- helpers `ensureGateSignatureUniqueIndex()` et `gateSignatureIndexIsValid()`.

Cette modification ne concerne pas directement l'importeur de packs. Elle touche Gate et une migration de base de donnees. Elle ne doit donc pas etre embarquee dans une PR d'importeur sans justification separee, d'autant que Gate a deja ete repris via une PR dediee.

## Tests manuels WordPress necessaires

Tests sur installation vierge :

1. Installer le zip depuis `main` + branche candidate.
2. Activer le plugin.
3. Verifier le diagnostic.
4. Importer un pack minimal historique.
5. Importer `ouinpo-pack-nsi-complet.json`.
6. Verifier les compteurs du rapport : niveaux, domaines, competences, exercices, sujets pratiques, flashcards.
7. Ouvrir les pages publiques des exercices, sujets pratiques et flashcards.
8. Reimporter le meme pack et verifier l'absence de doublons.

Tests sur installation avec donnees existantes :

1. Sauvegarder base et fichiers.
2. Importer un pack deja utilise dans `main`.
3. Modifier localement un exercice importe, un indice, une solution et une flashcard.
4. Reimporter le pack et observer ce qui est remplace.
5. Tester un pack avec une competence inconnue.
6. Tester un pack avec un niveau inconnu.
7. Tester un pack avec `practical_subject` sans `practical_calls`.
8. Tester un pack avec carte flashcard incomplete.
9. Forcer une erreur SQL sur une table de test et verifier `failed`, `partial`, rollback et absence de donnees incoherentes.

Tests de securite et diffusion :

1. Verifier qu'aucun pack ne contient de donnees eleves, comptes, logs, cles API, exports SQL ou chemins locaux.
2. Verifier que le build exclut toujours les packs de test non distribues.
3. Verifier que le rapport d'import n'affiche pas d'information sensible issue de `wpdb->last_error`.

## Risques pour une version diffusee a d'autres enseignants

- Risque de faux sentiment de securite si le rapport annonce un rollback alors que la base ne garantit pas toutes les transactions.
- Risque de perte de personnalisation locale lors d'un reimport, notamment sur indices, solutions, metadonnees, liens et fichiers pratiques.
- Risque de blocage sur packs anciens imparfaits qui passaient auparavant.
- Risque de support accru si le nouveau pack NSI complet est percu comme contenu officiel ou exhaustif.
- Risque de confusion si `schema_version` reste `1.0` malgre un schema beaucoup plus formalise.
- Risque de melanger un chantier importeur avec une migration Gate sans lien direct.

## Recommandation

Ne pas fusionner ces changements tels quels dans `main`.

Prochaine etape conseillee :

1. Isoler l'importeur v2 dans une branche dediee sans modification Gate.
2. Ajouter une petite matrice de packs de test : pack ancien minimal, pack incomplet volontaire, pack NSI complet.
3. Valider manuellement l'idempotence et les echecs avec rollback.
4. Decider explicitement de la version de schema.
5. Documenter clairement ce qui est remplace lors d'un reimport.
