# Correctif P1 dbDelta assessments 0.7.4-beta

Date : 2026-06-17, Europe/Paris.

## Objet

Ce correctif cible uniquement le P1 de l'audit : les erreurs SQL generees par `dbDelta()` sur les tables Exercises :

- `wp_ouin_exo_assessment_results`
- `wp_ouin_exo_assessment_attendance`

Symptome observe dans LocalWP : requetes invalides du type `ALTER TABLE ... ADD  `` (``)` et warnings `Undefined array key` dans `wp-admin/includes/upgrade.php`.

## Cause retenue

Le parseur de `dbDelta()` interprete mal les definitions des deux tables d'evaluations dans ce contexte MySQL 8.4 / WordPress LocalWP. Les deux tables avaient des definitions avec cles primaires composites, index et types `ENUM`, ce qui suffisait a declencher des diffs SQL invalides sur une base deja existante.

## Correction appliquee

Dans `src/Modules/Exercises/plugin/inc/InstallV2.php` :

- les deux tables d'evaluations ne passent plus par `dbDelta()`;
- elles sont creees avec `CREATE TABLE IF NOT EXISTS`;
- les colonnes, cles primaires et index attendus sont ajoutes seulement s'ils manquent;
- les noms d'index restent explicites et stables : `PRIMARY`, `user_id`, `competency_id`, `observed_status`, `is_absent`;
- les identifiants SQL sont validates avant construction des requetes;
- une erreur SQL controlee empeche maintenant `ouinpo_exo_db_version` d'etre avancee silencieusement;
- `maybe_upgrade()` repare une installation deja marquee en `2.8.0` si le controle des deux tables n'a jamais ete valide;
- un verrou d'option `ouinpo_exo_schema_migration_lock` evite les reparations concurrentes;
- les autres appels `dbDelta()` d'Exercises passent par un wrapper qui supprime les lignes vides avant d'appeler WordPress et remonte `wpdb->last_error` dans l'etat de migration.

Pendant la validation, une reexecution forcee a montre que le bug de parsing pouvait aussi toucher d'autres tables Exercises lors d'un upgrade complet. Le correctif final normalise donc tous les appels `dbDelta()` restants, tout en gardant les deux tables initialement critiques hors `dbDelta()`.

## Script ajoute

`tools/check-exercises-schema.php` ajoute un controle non destructif par defaut :

```powershell
php tools/check-exercises-schema.php
```

Le script verifie statiquement que les deux tables ne passent plus par `dbDelta()` et que les helpers conditionnels existent.

Modes optionnels :

```powershell
php tools/check-exercises-schema.php --wp-load="C:\path\to\wp-load.php"
php tools/check-exercises-schema.php --wp-load="C:\path\to\wp-load.php" --apply
```

Le mode `--apply` rejoue explicitement la migration WordPress. Il n'est pas lance par defaut.

## Tests executes

Commandes vertes :

```powershell
php -l src/Modules/Exercises/plugin/inc/InstallV2.php
php -l tools/check-exercises-schema.php
php tools/check-exercises-schema.php
git diff --check
php tools/verify-packs.php
php tools/verify-optimizations.php
```

Resultats :

- `tools/check-exercises-schema.php` : OK, absence de `dbDelta($sql_assessment_results)` et `dbDelta($sql_assessment_attendance)`.
- `verify-packs.php` : 5 fichiers verifies.
- `verify-optimizations.php` : 142 verifications.
- `git diff --check` : OK.

## Verification LocalWP ouinpo-test

Sauvegarde DB creee avant toute operation destructive :

```text
C:\Users\vonk\AppData\Local\Temp\ouinpo-test-before-p1-dbdelta-fix-20260617-020458.sql
```

Operations LocalWP realisees :

- `http://ouinpo-test.local` : HTTP 200.
- `http://ouinpo-test.local/wp-json/` : HTTP 200.
- lecture MySQL directe des colonnes et index des deux tables : structure attendue presente.
- lecture du tail `logs/php/error.log` : les erreurs `ADD  `` (``)` visibles sont les anciennes entrees du 2026-06-16 23:23:14 UTC ; aucune nouvelle entree `dbDelta` n'a ete observee apres le smoke HTTP.

Limite :

- le mode `--wp-load` du script est bloque localement par une erreur CLI WordPress de connexion DB et sort maintenant en erreur explicite ;
- la migration corrigee n'a pas ete forcee sur la base LocalWP, afin de ne pas modifier les donnees pedagogiques sans necessite.

## Etat attendu

Le prochain boot ou upgrade qui execute `InstallV2::upgrade_schema()` ne doit plus produire les requetes invalides `ALTER TABLE ... ADD  `` (``)` pour ces deux tables. Si une requete controlee echoue, le correctif logue le contexte et bloque l'avancement de `ouinpo_exo_db_version`.

## Validation archive reconstruite 0.7.4-beta

Date : 2026-06-17, Europe/Paris.

Archive testee :

```text
D:\Documents\Projets\ouinpo-suite-distribution\dist\ouinpo-suite-0.7.4-beta.zip
```

Environnement :

- Site LocalWP : `C:\Users\vonk\Local Sites\ouinpo-test\app\public`
- URL : `http://ouinpo-test.local`
- WordPress local : `7.0`
- MySQL : `8.4.0`, base `local`, port `10005`
- PHP CLI depot : `8.2.12`
- Plugin actif avant test : `ouinpo-suite/ouinpo-suite.php`, `0.7.4-beta`
- `ouinpo_exo_db_version` initial : `2.8.0`

Sauvegardes :

```text
C:\Users\vonk\AppData\Local\Temp\ouinpo-test-before-zip-074-dbdelta-validation-20260617-021323.sql
C:\Users\vonk\Local Sites\ouinpo-test\app\public\wp-content\plugins\ouinpo-suite-before-zip-074-validation-20260617-021342
C:\Users\vonk\Local Sites\ouinpo-test\app\public\wp-content\plugins\ouinpo-suite-before-zip-074-lock-validation-20260617-021627
C:\Users\vonk\Local Sites\ouinpo-test\app\public\wp-content\plugins\ouinpo-suite-before-zip-074-normalized-dbdelta-20260617-022008
```

Commandes et resultats principaux :

```powershell
php -l src/Modules/Exercises/plugin/inc/InstallV2.php
php -l tools/check-exercises-schema.php
php tools/check-exercises-schema.php
git diff --check
php tools/verify-packs.php
php tools/verify-optimizations.php
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-dist.ps1
```

Resultats :

- lint PHP : OK.
- `tools/check-exercises-schema.php` : OK, controle du helper de reparation, du verrou et du wrapper `dbDelta`.
- `verify-packs.php` : OK, 5 fichiers verifies.
- `verify-optimizations.php` : OK, 142 verifications.
- `scripts/test-dist.ps1` : OK, zip `0.7.4-beta`, 350 entrees.
- controle ZIP : `ouinpo-suite/ouinpo-suite.php` present, `Version: 0.7.4-beta`, constante `OUINPO_SUITE_VERSION` a `0.7.4-beta`.
- controle ZIP anti-fuite : aucun `.git`, dump SQL, log, `.env`, `wp-config.php`, secret evident, `node_modules` ou zip imbrique detecte.

Installation ZIP LocalWP :

- archive installee dans `wp-content/plugins/ouinpo-suite`;
- front `http://ouinpo-test.local` : HTTP 200;
- REST `http://ouinpo-test.local/wp-json/` : HTTP 200;
- admin `http://ouinpo-test.local/wp-admin/` : 302 vers login, pas de fatal.

Verification MySQL finale :

- `wp_ouin_exo_assessment_results` presente.
- `wp_ouin_exo_assessment_attendance` presente.
- colonnes attendues presentes sur les deux tables.
- cles primaires attendues presentes.
- index attendus presents :
  - `assessment_results` : `PRIMARY`, `user_id`, `competency_id`, `observed_status`;
  - `assessment_attendance` : `PRIMARY`, `user_id`, `is_absent`.
- `SHOW CREATE TABLE` confirme les deux schemas et les contraintes FK historiques.
- `ouinpo_exo_db_version` final : `2.8.0`.
- `ouinpo_exo_assessment_schema_checked` final : `2.8.0`.
- aucun verrou `ouinpo_exo_schema_migration_lock` residuel.

Tests de migration et idempotence :

- Reparation partielle : suppression controlee de l'index secondaire `observed_status` et du marqueur `ouinpo_exo_assessment_schema_checked`, avec `ouinpo_exo_db_version` conserve a `2.8.0`.
- Deux boots HTTP paralleles front/REST : HTTP 200, index `observed_status` recree, marqueur remis a `2.8.0`, aucun log supplementaire apres ajout du verrou.
- Idempotence : boots HTTP repetes sans modification de schema, aucun doublon de colonne/index et longueur de `logs/php/error.log` inchangee.
- Upgrade force : `ouinpo_exo_db_version` abaisse a `2.7.9`, boot HTTP, retour a `2.8.0`, marqueur remis a `2.8.0`, aucun nouveau `ALTER TABLE ... ADD  `` (``)` apres normalisation des appels `dbDelta()`.

Logs :

- Les anciennes erreurs du 2026-06-16 23:23:14 UTC et un essai intermediaire du 2026-06-17 00:18:03 UTC restent dans l'historique du fichier.
- Apres le ZIP final `normalized-dbdelta`, la longueur du log est restee stable a `9129438` pendant les tests de reparation partielle, d'idempotence et d'upgrade force.

Limites :

- `php tools/check-exercises-schema.php --wp-load="C:\Users\vonk\Local Sites\ouinpo-test\app\public\wp-load.php"` reste bloque par une erreur de connexion DB du bootstrap CLI WordPress local ; le script sort en erreur explicite.
- Fresh install WordPress vierge non executee : pas de second site LocalWP propre disponible dans ce tour.
- Upgrade complet depuis une vraie archive 0.7.3-beta restauree sur snapshot vierge non execute ; le scenario partiellement migre a ete simule sans suppression de donnees en retirant uniquement un index secondaire.

Conclusion :

**P1 corrige** pour le perimetre valide : source, archive reconstruite, installation ZIP reelle LocalWP, reparation partielle, idempotence et upgrade force sans nouvelle erreur `dbDelta` apres le ZIP final.

## Validation fresh install / release candidate 0.7.4-beta

Date : 2026-06-17, Europe/Paris.

ZIP teste :

```text
D:\Documents\Projets\ouinpo-suite-distribution\dist\ouinpo-suite-0.7.4-beta.zip
```

SHA256 :

```text
748D1ECBB45CCBD165C0918F26606064E2356C82F5AA435E03117F57427A1816
```

Environnement :

- WordPress jetable propre dans `%TEMP%`.
- Base MySQL neuve `ouinpo_fresh_074_20260617022926`.
- Prefixe non standard `ouf_`.
- WordPress `7.0`, PHP `8.2.12`, MySQL `8.4.0`.

Resultat :

- activation du ZIP : OK.
- front : HTTP 200.
- `/wp-json/` : HTTP 200.
- admin : HTTP 200, pas de fatal.
- `tools/check-exercises-schema.php --wp-load=...` : OK.
- tables `assessment_results` et `assessment_attendance` creees avec colonnes, cles primaires et index attendus.
- aucun `debug.log` WordPress cree.
- aucun nouveau `ALTER TABLE ... ADD  `` (``)`.
- idempotence validee par chargements repetes et cycle desactivation/reactivation.
- page smoke `[ouinpo_exercises]` : HTTP 200, pas de fatal.

Documentation detaillee :

- `docs/release-validation-0.7.4-beta.md`

Conclusion : **release candidate 0.7.4-beta validee**.
