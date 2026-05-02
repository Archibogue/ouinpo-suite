# Legacy désactivé

Cette archive correspond à une version **native** de `ouinpo-suite`.

## État
- Aucun module utile n'est chargé depuis `legacy/`.
- Les modules suivants sont embarqués directement dans `src/Modules/*/plugin/` :
  - Exercises
  - Submissions
  - SegFault
  - Gate
  - RechText
  - Meta
- Le notifier SegFault est intégré dans `src/Modules/SegFault/plugin/addons/`.

## Conséquence
Le dossier `legacy/` n'est plus requis pour l'exécution normale du plugin.

## Sécurité opérationnelle
Garde quand même une archive externe des anciens plugins séparés tant que la version n'a pas été validée complètement en production.
