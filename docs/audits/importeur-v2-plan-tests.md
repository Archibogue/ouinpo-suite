# Plan de tests importeur v2

Ce document prepare le chantier de l'importeur de packs v2. Les fixtures sont
dans `tests/packs/` et ne sont pas destinees a la distribution publique: le
script `tools/build-dist.ps1` exclut le dossier racine `tests` du zip.

## Matrice de packs

| Pack | Ce qui est teste | Resultat attendu |
| --- | --- | --- |
| `pack-minimal-ancien-format.json` | Detection d'un pack historique qui ne suit pas l'enveloppe `schema_version` + `pack` du format actuel. | L'importeur v2 doit identifier le format et refuser l'import si aucune compatibilite legacy explicite n'est implementee. |
| `pack-incomplet-competence-inconnue.json` | Exercice qui reference une competence absente de `competencies`. | L'import doit etre bloque avant creation de l'exercice. |
| `pack-incomplet-niveau-inconnu.json` | Exercice qui reference un `level_slug` absent de `school_levels`. | L'import doit etre bloque avant creation de l'exercice. |
| `pack-flashcard-incomplete.json` | Deck valide contenant une carte incomplete avec `front_html` vide. | La carte incomplete doit etre rejetee; le comportement du deck doit etre explicite dans le rapport d'import. |
| `pack-exercice-reimport.json` | Import puis reimport d'un exercice stable avec les memes slugs. | Le second import doit mettre a jour ou ignorer les donnees existantes sans creer de doublons. |

## Erreurs bloquantes

Une erreur est bloquante quand continuer l'import creerait des donnees
orphelines, incoherentes ou impossibles a relier proprement:

- JSON invalide ou illisible.
- Format de pack non reconnu.
- Absence des champs obligatoires du pack (`schema_version`, `pack.slug`,
  `pack.title`) pour les formats non legacy.
- Reference a une competence absente.
- Reference a un niveau absent.
- Reference a une difficulte absente quand l'exercice la declare.
- Exercice sans `slug`, sans `title` ou sans `statement`.
- Carte flashcard sans recto ou sans verso.

## Avertissements

Un avertissement signale une donnee importable mais incomplete ou degradable
sans casser les relations principales:

- Champ optionnel absent (`description`, `reference_url`, `note_teacher`).
- Liste vide attendue mais non problematique (`hints`, `solutions`, `badges`).
- Valeur optionnelle inconnue qui peut etre ignoree sans perte critique.
- Element deja present avec le meme slug et les memes donnees.
- Normalisation appliquee sur un libelle ou un slug, si l'importeur v2 la prend
  en charge.

## Tester le reimport

1. Importer `tests/packs/pack-exercice-reimport.json` sur une base propre.
2. Relever les identifiants internes de la competence, de l'exercice, de
   l'indice, de la solution et des metadonnees d'examen.
3. Relancer exactement le meme import.
4. Verifier le rapport: le second passage doit annoncer des elements inchanges,
   mis a jour ou ignores, mais jamais des creations supplementaires.
5. Modifier uniquement un champ non identifiant dans une copie locale du pack,
   par exemple le titre de l'exercice, puis reimporter cette copie.
6. Verifier que l'element existant est mis a jour selon la strategie retenue par
   l'importeur v2 et que son slug reste stable.

## Tester la non-creation de doublons

Les controles doivent se faire par slug fonctionnel, pas seulement par nombre
total de lignes:

- `fixture-reimport-algorithmique-001` doit exister une seule fois.
- `fixture-reimport-compter-occurrences` doit exister une seule fois.
- L'indice d'ordre `1` rattache a l'exercice doit exister une seule fois.
- La solution officielle d'ordre `1` rattachee a l'exercice doit exister une
  seule fois.
- Les metadonnees d'examen rattachees a l'exercice doivent etre uniques.

Apres deux imports consecutifs du pack de reimport, les compteurs attendus sont:

| Objet | Filtre | Compte attendu |
| --- | --- | --- |
| Competence | slug `fixture-reimport-algorithmique-001` | 1 |
| Exercice | slug `fixture-reimport-compter-occurrences` | 1 |
| Indice | exercice + ordre `1` | 1 |
| Solution | exercice + ordre `1` + officielle | 1 |
| Exam meta | exercice | 1 |

## Validation minimale de cette matrice

Avant de modifier l'importeur, cette premiere etape doit seulement garantir que
les fixtures sont presentes, lisibles et hors distribution:

- valider le JSON des fichiers `tests/packs/pack-*.json`;
- lancer `git diff --check`;
- lancer `powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\build-dist.ps1`;
- lancer `powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-dist.ps1`.
