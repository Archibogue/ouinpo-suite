# Audit coherence 0.7.1-beta

OuInPo Suite 0.7.1-beta est une beta technique. Elle peut etre partagee pour test encadre, mais ne doit pas etre presentee comme une version stable.

## Architecture actuelle

- `ouinpo-suite.php` charge l'autoloader, declare la version `0.7.1-beta` et boote la suite sur `plugins_loaded`.
- `src/Core/Plugin.php` orchestre activation, desactivation et boot. Le schema partage est installe ou migre avant le boot des modules.
- `src/Core/Installer.php` contient les tables partagees Gate, SegFault et Projects, les contraintes SegFault et les complements de schema IA Projects.
- `src/Core/ModuleRegistry.php` liste les modules disponibles. Les modules actifs par defaut restent `exercises` et `flashcards`; `exercises` reste le socle non desactivable.
- `src/Core/Admin/SuiteAdmin.php` fournit le hub admin, les diagnostics, les reglages, les raccourcis et les chemins vers les modules metier.
- Les packs pedagogiques livres dans `packs/` restent des fichiers JSON autonomes, importables sans Composer ni service externe.

## Modules

- `exercises`: socle de la suite, exercices, competences, niveaux, devoirs, sujets pratiques, badges et usages IA lies aux corrections.
- `flashcards`: revisions et memorisation.
- `gate`: enigmes, progression et certificats.
- `segfault`: assistants IA, RAG, parcours individualises, indexation et reglages IA principaux.
- `submissions`: depots eleves et ressources prof.
- `projects`: suivi pedagogique BTS SIO, Kanban, livrables, preuves, journal de bord et assistants IA enseignant/eleve optionnels.
- `rechtext`: recherche textuelle pedagogique.
- `meta`: reglages meta et Open Graph.

## Points de securite

- Les acces publics restent desactives par defaut et doivent etre actives volontairement.
- L'IA globale, l'IA publique et l'IA eleve Projects restent desactivees par defaut.
- Les cles IA peuvent etre configurees par option WordPress ou surchargees par constantes `wp-config.php`, notamment `OUINPO_AI_API_KEY`, `OUINPO_SF_ALBERT_API_KEY`, `OUINPO_ALBERT_API_KEY`, `OUINPO_SF_OPENAI_API_KEY` et `OUINPO_OPENAI_API_KEY`.
- Les champs de cles sont masques dans l'administration; une valeur masquee conserve la cle existante lors d'un enregistrement.
- Les diagnostics indiquent seulement si une cle est presente ou absente. Ils n'affichent pas la valeur.
- Les logs IA passent par `AiSettings::debug_log()`, qui filtre les champs contenant key, secret, token, prompt, response, answer ou content.
- `uninstall.php` conserve les donnees pedagogiques afin d'eviter une perte accidentelle.
- Les scripts de distribution excluent dumps SQL, exports WordPress, archives, logs, fichiers locaux et secrets detectables.

## Procedure de test

1. Executer le lint PHP sur tous les fichiers `*.php` avec PHP 8.1, 8.2 et 8.3.
2. Executer `php tools/verify-packs.php`.
3. Executer `pwsh ./scripts/test-dist.ps1`.
4. Verifier que le zip contient `ouinpo-suite/ouinpo-suite.php`.
5. Verifier que le zip ne contient pas de packs `ouinpo-pack-test-*.json`.
6. Verifier que le zip ne contient pas de fichiers interdits: SQL, DB, logs, ZIP imbriques, WXR, XML, `.env`, `wp-config.php`, `secrets.php`, `auth.json`.
7. Installer le zip sur un WordPress vierge, activer le plugin, ouvrir `OuInPo Suite > Reglages > Diagnostic`.
8. Confirmer que `exercises` et `flashcards` sont actifs, que `exercises` n'est pas desactivable, et que Projects reste optionnel.
9. Importer un pack de demonstration et verifier les pages/shortcodes utiles avec un compte enseignant et un compte eleve.
10. Si Projects est active, ouvrir le diagnostic Projects et traiter manuellement les avertissements eventuels.

## Limites connues

- Le diagnostic Projects est volontairement non destructif: il detecte les taches, colonnes, livrables, preuves et liens competences orphelins, mais ne les corrige pas automatiquement.
- Les contraintes SQL SegFault peuvent echouer sur certaines installations historiques contenant deja des donnees incoherentes; les echecs sont journalises dans `ouinpo_suite_fk_failures`.
- Les assistants Projects peuvent proposer des taches, livrables ou liens competences, mais l'application requiert une selection et une confirmation explicites dans l'interface.
- Le controle anti-secrets utilise des motifs simples; il reduit le risque de fuite evidente mais ne remplace pas une revue humaine avant diffusion publique.
- La CI ne charge pas un WordPress complet. Les workflows admin, REST et shortcodes doivent encore etre verifies sur une installation WordPress de test.
