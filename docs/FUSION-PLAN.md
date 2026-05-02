# Plan de fusion — état actuel

La fusion a été réalisée sous la forme d'un **plugin unique modulaire** `ouinpo-suite`.

## Modules natifs embarqués
- Exercises
- Submissions
- SegFault
- Gate
- RechText
- Meta

## Intégration particulière
- Le notifier SegFault est intégré au module `SegFault` via `src/Modules/SegFault/plugin/addons/`.

## État d'exécution
- Le chargement normal ne dépend plus d'un dossier `legacy/`.
- Les modules sont chargés directement depuis `src/Modules/*/plugin/`.

## Étape suivante recommandée
- Stabiliser la prod
- Conserver une archive des anciens plugins séparés hors activation
- Faire ensuite, sans urgence, le ménage interne et les refactors architecturaux
