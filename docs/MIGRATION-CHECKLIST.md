# Checklist de migration

## Avant

- [x] Sauvegarde N0C vérifiée
- [x] Export SQL local téléchargé
- [x] Archive locale des plugins actuels
- [x] Staging prêt
- [x] Désactivation des anciens plugins prévue
- [x] Version PHP de l’hébergement vérifiée en 8.1+

## Déploiement / structure

- [x] Copier `ouinpo-suite` dans `wp-content/plugins/ouinpo-suite/`
- [x] Activer uniquement le plugin racine `ouinpo-suite`
- [x] Conserver une archive externe des anciens plugins séparés
- [x] Vérifier les chemins des fichiers principaux
- [x] Vérifier que les modules natifs sont bien chargés depuis `src/Modules/*/plugin/`

> Note : le dossier `wp-content/plugins/ouinpo-suite/legacy/` n’est plus requis pour l’exécution normale.  
> La stratégie actuelle repose sur des modules natifs intégrés + une archive externe des anciens plugins.

## Tests fonctionnels minimum

- [x] Page d'exercice publique
- [x] Évaluation IA d'une réponse
- [x] Tableau badges élève
- [x] Tableau compétences prof
- [x] Dépôt élève
- [x] Ressources de classe
- [x] Chat SegFault
- [x] Parcours SegFault
- [x] Gate / énigmes
- [x] Génération certificat PDF
- [x] Simulation de recherche textuelle
- [x] Métadonnées SEO

## Tests admin complémentaires

- [x] Page admin suite
- [x] Page Années scolaires
- [x] Page DS / Devoirs surveillés
- [x] Import des exercices
- [x] Gestion des badges
- [x] Affectation des badges
- [ ] Vérifier une nouvelle importation d’exercices après les dernières évolutions de schéma
- [ ] Vérifier un cycle complet DS -> validation des compétences -> mise à jour du suivi élève
- [ ] Vérifier les filtres et modifications sur l’écran d’affectation des badges

## Points d'attention

- [x] Les anciens plugins ne doivent plus être activés en parallèle
- [x] SegFault stocke désormais SQLite + sources dans `uploads/ouinpo/segfault/`
- [x] Une migration automatique depuis les anciens dossiers `data/` et `sources/` du plugin existe
- [x] Le chargeur central recrée les tables Gate et SegFault si besoin
- [x] Les routes REST dupliquées Exercises ont été supprimées
- [x] La route `/ouinpo/v1/exercises/(?P<id>\d+)/status` n’est plus déclarée qu’une seule fois
- [x] La route `/ouinpo/v1/me/badges` n’est plus déclarée qu’une seule fois
- [x] La route de debug `/ouinpo/v1/debug-badges` a été supprimée
- [x] Les méthodes legacy mortes ont été supprimées de `ExercisesRoutes`
- [x] Les fichiers natifs internes n’exposent plus d’en-têtes `Plugin Name`
- [x] Les `register_activation_hook()` résiduels ont été supprimés des modules internes principaux
- [ ] Les migrations Exercises passent encore par `ModuleInstaller -> InstallV2::maybe_upgrade()`

## Nettoyage phase 2

- [x] Créer un vrai stockage SegFault basé sur `uploads`
- [x] Déplacer SQLite + sources vers `uploads`
- [x] Convertir MyISAM en InnoDB
- [x] Ajouter les contraintes SQL utiles
- [x] Extraire un installateur de module dédié pour Exercises
- [x] Conserver un seul point d’entrée de chargement par module
- [x] Supprimer les outils de debug exposés inutilement
- [ ] Remplacer à terme `InstallV2` par un système de migration natif au module Exercises

## Fichiers repris

- [x] `src/Modules/Exercises/Module.php`
- [x] `src/Modules/Exercises/plugin/inc/ModuleInstaller.php`
- [x] `src/Modules/Exercises/plugin/inc/Install.php`
- [x] `src/Modules/Exercises/plugin/inc/Rest/ExercisesRoutes.php`
- [x] `src/Modules/Exercises/plugin/inc/Rest/StatusRoutes.php`
- [x] `src/Modules/Exercises/plugin/inc/Rest/BadgesRoutes.php`
- [x] `src/Modules/Exercises/plugin/inc/Rest/MeRoutes.php`
- [x] `src/Modules/Exercises/plugin/ouinpo-exercices.php`
- [x] `src/Modules/Gate/plugin/ouinpo-gate.php`
- [x] `src/Modules/SegFault/plugin/segfault.php`
- [x] `src/Modules/SegFault/plugin/addons/ouinpo-segfault-notifier.php`
- [x] `src/Modules/Submissions/plugin/ouinpo-submissions.php`
- [x] `src/Modules/RechText/plugin/ouinpo_recherche_textuelle.php`
- [x] `src/Modules/Meta/plugin/ouinpo-meta-description.php`

## Critères de fin de migration

- [ ] Un seul plugin visible et activé côté WordPress : `ouinpo-suite`
- [ ] Aucun ancien plugin standalone nécessaire au fonctionnement
- [x] Aucun doublon de route REST
- [x] Aucun outil de debug exposé inutilement
- [ ] Les migrations Exercises ne dépendent plus directement de `InstallV2` dans le flux normal
- [ ] Documentation de l’architecture mise à jour (`README`, `MODULE-MAPPING`, `LEGACY-DISABLED`, `MIGRATION-CHECKLIST`)