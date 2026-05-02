# Packs pédagogiques OuInPo Suite

Les packs pédagogiques permettent d’importer des contenus dans OuInPo Suite sans utiliser de dump SQL.

Un pack peut contenir :

- des compétences du BO ;
- des exercices ;
- des indices ;
- des solutions ;
- des métadonnées bac ;
- des flashcards ;
- plus tard : des sujets pratiques.

Un pack ne doit jamais contenir :

- comptes élèves ;
- groupes ou classes réels ;
- résultats d’élèves ;
- statuts d’exercices ;
- historiques de révision ;
- devoirs rendus ;
- logs ;
- clés API ;
- exports WordPress complets ;
- dumps SQL.

## Principe général

Les contenus sont reliés par des slugs stables, jamais par les identifiants numériques de la base.

Exemple :

```json
{
  "exercise_slug": "recherche-bibliotheque-babel",
  "competency_slugs": [
    "nsi-premiere-algorithmique-test-001"
  ]
}
```

Les niveaux et les difficultés sont créés automatiquement par l’installateur du plugin. Les packs pédagogiques peuvent donc généralement laisser ces tableaux vides :

```json
"school_levels": [],
"difficulties": []
```

## Structure générale d’un pack

```json
{
  "schema_version": "1.0",
  "pack": {
    "slug": "ouinpo-pack-exemple",
    "title": "Pack exemple",
    "description": "Description du pack.",
    "author": "OuInPo",
    "license": "Voir CONTENT-LICENSE.md",
    "created_at": "2026-05-01"
  },
  "school_levels": [],
  "difficulties": [],
  "competencies": [],
  "exercises": [],
  "flashcards": [],
  "badges": []
}
```

## Compétences BO

Format :

```json
{
  "slug": "nsi-premiere-algorithmique-001",
  "track": "NSI",
  "level": "Première",
  "cycle": "premiere",
  "domain": "Algorithmique",
  "domain_slug": "algorithmique",
  "competency": "Écrire, mettre au point et exécuter un programme.",
  "capacity": "Comprendre et mettre en œuvre un algorithme simple.",
  "example": "Recherche dans un tableau.",
  "reference_url": "",
  "active": 1
}
```

Champs importants :

- `slug` : identifiant stable de la compétence ;
- `track` : `SNT` ou `NSI` ;
- `level` : `Seconde`, `Première`, `Terminale` ou `Transversal` ;
- `domain_slug` : slug stable du domaine ;
- `active` : `1` pour visible, `0` pour masquée.

## Exercices

Format :

```json
{
  "title": "La recherche dans la bibliothèque de Babel",
  "slug": "recherche-bibliotheque-babel",
  "level_slug": "premiere",
  "difficulty_slug": "confirme",
  "statement": "<p>Énoncé HTML de l’exercice.</p>",
  "is_active": 1,
  "competency_slugs": [
    "nsi-premiere-algorithmique-001"
  ],
  "hints": [
    {
      "order": 1,
      "content": "<p>Premier indice.</p>"
    }
  ],
  "solutions": [
    {
      "order": 1,
      "title": "Solution possible",
      "content": "<pre><code>def exemple():\\n    return True</code></pre>",
      "is_official": 1
    }
  ],
  "exam_meta": {
    "exam_type": "written",
    "source_type": "type_bac",
    "session_label": "",
    "year_label": "",
    "center_label": "",
    "theme_bac": "algorithmique",
    "bac_format": "ecriture_complete",
    "estimated_minutes": 12,
    "is_exam_like": 1,
    "subject_group": "",
    "sort_in_subject": null
  }
}
```

Champs importants :

- `slug` : identifiant stable de l’exercice ;
- `level_slug` : niveau principal, par exemple `premiere` ;
- `level_slugs` : optionnel, pour associer un exercice à plusieurs niveaux ;
- `difficulty_slug` : `debutant`, `confirme`, `expert` ;
- `competency_slugs` : compétences associées ;
- `hints` : indices importés dans l’ordre ;
- `solutions` : solutions importées dans l’ordre ;
- `exam_meta` : métadonnées bac.

Lors d’un réimport, l’exercice est mis à jour à partir de son `slug`. Les liens compétences, niveaux, indices, solutions et métadonnées bac sont remplacés par ceux du pack. Les données élèves ne sont pas modifiées.

## Métadonnées bac

Valeurs prévues pour `exam_type` :

```text
written
practical_subject
```

Valeurs prévues pour `source_type` :

```text
annale
inspired
type_bac
classic
```

Valeurs prévues pour `bac_format` :

```text
question_courte
lecture_code
code_a_completer
ecriture_complete
raisonnement
```

Exemple :

```json
"exam_meta": {
  "exam_type": "written",
  "source_type": "type_bac",
  "theme_bac": "algorithmique",
  "bac_format": "ecriture_complete",
  "estimated_minutes": 12,
  "is_exam_like": 1
}
```

## Flashcards

Format :

```json
{
  "deck": {
    "title": "NSI Première — Algorithmique",
    "slug": "nsi-premiere-algorithmique",
    "description": "Cartes de révision sur l’algorithmique en Première NSI.",
    "track": "NSI",
    "level": "Première",
    "is_active": 1
  },
  "cards": [
    {
      "card_type": "definition",
      "front_html": "<p>Qu’est-ce qu’un parcours de tableau ?</p>",
      "back_html": "<p>C’est le fait de visiter successivement les éléments d’un tableau.</p>",
      "note_teacher": "",
      "sort_order": 1,
      "is_active": 1,
      "competency_slugs": [
        "nsi-premiere-algorithmique-001"
      ]
    }
  ]
}
```

Valeurs prévues pour `card_type` :

```text
definition
distinction
repere
syntaxe
vocabulaire
```

Les decks sont identifiés par `deck.slug`.

Dans la version actuelle, les cartes sont mises à jour par :

```text
deck_id + sort_order
```

Donc il faut conserver un ordre stable dans les packs.

## Import idempotent

Un pack peut être importé plusieurs fois.

Résultat attendu :

- premier import : création ;
- imports suivants : mise à jour ;
- pas de doublons ;
- pas de suppression de données élèves.

## Licence

Chaque pack doit préciser sa licence dans le champ `license`.

Les contenus pédagogiques OuInPo redistribuables sont placés sous la licence indiquée dans `CONTENT-LICENSE.md`.

Les ressources officielles, sujets d’examen, extraits de programmes ou ressources tierces doivent être vérifiés avant redistribution.
