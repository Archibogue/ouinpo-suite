# Packs pédagogiques OuInPo Suite

Ce dossier contient uniquement les packs JSON distribues avec OuInPo Suite `0.5.2-beta`, plus le schema de reference `ouinpo-pack.schema.json`.

Les packs sont construits a partir des données du site d'origine, puis nettoyes pour ne conserver que des données pedagogiques importables. Les anciens packs de test et packs intermediaires ont ete deplaces dans `tests/packs/` et ne sont pas distribués dans le zip.

## Packs distribués

| Pack | Role | Contenu |
|---|---|---|
| `ouinpo-pack-demo-minimal.json` | Test rapide de l'import | Quelques niveaux, difficultes, domaines, competences, exercices simples et flashcards de demonstration. |
| `ouinpo-pack-referentiel-snt-nsi.json` | Base de travail professeur | Niveaux `seconde`, `premiere`, `terminale`, difficultes, domaines et competences SNT/NSI avec slugs stables. Aucun exercice, aucune flashcard. |
| `ouinpo-pack-flashcards-nsi.json` | Revision active NSI | Decks et flashcards NSI rattaches aux competences quand le lien existe. A importer apres le referentiel. |
| `ouinpo-pack-exercices-site-origine.json` | Exercices du site d'origine | Exercices non pratiques avec enonces, indices, solutions, niveaux, difficultes, domaines, competences et metadonnees bac disponibles. |

Le pack `ouinpo-pack-demo-minimal.json` est reserve aux tests d'installation et de validation rapide. Pour une installation professeur, utiliser plutot le referentiel, puis les flashcards et/ou les exercices.

## Ordre d'import recommande

1. `ouinpo-pack-referentiel-snt-nsi.json`
2. `ouinpo-pack-flashcards-nsi.json`
3. `ouinpo-pack-exercices-site-origine.json`

Le pack demo minimal peut etre importe seul sur une installation de test. Il n'est pas necessaire si le referentiel complet est importe.

## Sujets pratiques

`ouinpo-pack-exercices-site-origine.json` exclut explicitement les sujets pratiques :

- aucun `exam_meta.exam_type = "practical_subject"` ;
- aucun `practical_calls` ;
- aucun `practical_files`.

Les sujets pratiques pourront faire l'objet d'un pack separe dans une version ulterieure, apres validation de l'importeur et du schema attendu.

## Donnees interdites

Un pack distribue ne doit jamais contenir :

- comptes eleves ;
- noms, prenoms, emails ou identifiants personnels ;
- groupes ou classes reels ;
- resultats, tentatives, progressions ou statuts eleves ;
- badges attribues ;
- logs ;
- adresses IP, user-agents ou traces techniques nominatives ;
- cles API, tokens ou secrets ;
- chemins locaux ;
- exports SQL, exports WordPress complets ou dumps de base.

Les packs de ce dossier doivent rester des fichiers JSON pedagogiques autonomes. Les exports SQL et exports intermediaires ne doivent pas etre ajoutes a `packs/`.

## Schema

Le champ `schema_version` reste `"1.0"` pour la version `0.5.2-beta`.

Le fichier `ouinpo-pack.schema.json` documente la structure generale acceptee par l'importeur actuel :

- `school_levels`
- `domains`
- `difficulties`
- `competencies`
- `exercises`
- `flashcards`
- `badges`

Le champ `badges` reste reserve : les packs distribues ne contiennent pas de badges attribues.

## Reimport

Les contenus sont relies par des slugs stables, jamais par les identifiants numeriques du site d'origine. Un reimport peut mettre a jour des contenus portant les memes slugs selon le comportement de l'importeur.

Avant de reimporter sur un site deja utilise avec des enseignants ou des eleves, faire une sauvegarde et tester sur une copie.
