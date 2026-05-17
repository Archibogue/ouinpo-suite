# Importeur de packs v2

L'importeur v2 conserve le format de pack `schema_version` `"1.0"` et la
forme de retour historique:

- `ok`
- `message`
- `details`

`details` est enrichi pour faciliter le diagnostic:

- `status`: `success`, `partial` ou `failed`;
- `warnings`: avertissements non bloquants;
- `errors`: erreurs bloquantes;
- `transaction_used`, `transaction_started`, `rollback_performed`;
- compteurs historiques et sous-tableau `counters`.

## Statuts

`success` signifie que l'import est alle au bout sans avertissement.

`partial` signifie que l'import est alle au bout avec au moins un avertissement.
Les donnees principales ont ete traitees, mais le rapport doit etre lu.

`failed` signifie qu'une erreur bloquante a arrete l'import. Quand l'erreur est
detectee en prevalidation, aucune ecriture volontaire n'est lancee.

## Erreurs bloquantes

La prevalidation refuse notamment:

- JSON invalide ou illisible;
- `schema_version` absent ou different de `"1.0"`;
- bloc `pack` absent ou invalide;
- `pack.slug` ou `pack.title` absent;
- section attendue qui n'est pas un tableau;
- exercice sans `slug`, sans `title` ou sans `statement`;
- exercice referencant un niveau inconnu;
- exercice referencant une difficulte inconnue quand elle est declaree;
- exercice referencant une competence inconnue;
- deck flashcard invalide;
- flashcard sans recto ou sans verso;
- sujet pratique `practical_subject` sans appels pratiques valides.

Les references sont considerees connues si elles existent dans le pack en cours
ou deja en base. Cela permet d'importer les packs distribues dans l'ordre
documente, par exemple referentiel puis flashcards.

## Avertissements

Un avertissement ne bloque pas l'import. Il sert a documenter une situation
degradee ou informative: section vide, champ optionnel absent, lien optionnel
impossible, valeur normalisee ou contenu deja present.

## Transaction best-effort

L'importeur tente `START TRANSACTION`, puis `COMMIT` en fin d'import. En cas
d'erreur pendant l'ecriture, il tente `ROLLBACK`.

Ces indicateurs ne promettent pas une atomicite garantie:

- `transaction_used`: une transaction a ete demandee et acceptee;
- `transaction_started`: `START TRANSACTION` a repondu sans erreur;
- `rollback_performed`: un `ROLLBACK` a ete tente et accepte apres erreur.

Selon le moteur SQL et les tables WordPress, certains effets peuvent ne pas etre
annules malgre un rollback accepte. Une sauvegarde reste necessaire avant un
reimport sur un site utilise.

## Reimport

Le reimport reste base sur les slugs stables:

- competences par `slug`;
- exercices par `slug`;
- decks par `slug`;
- flashcards par `deck_id` + `sort_order`.

Pour les exercices, les contenus dependants du pack sont remplaces avant
recreation: liens niveaux, liens competences, indices, solutions, `exam_meta`
et fichiers pratiques. Les donnees eleves ne sont pas supprimees par cet
importeur.

Cette strategie preserve l'idempotence attendue: reimporter le meme pack ne doit
pas creer de doublons pour les competences, exercices, indices, solutions,
metadonnees d'examen, decks et flashcards.
