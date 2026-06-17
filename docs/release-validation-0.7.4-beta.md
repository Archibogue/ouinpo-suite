# Validation release candidate OuInPo Suite 0.7.4-beta

Date : 2026-06-17, Europe/Paris.

## Resume

La release candidate `0.7.4-beta` a ete validee sur un WordPress jetable propre, avec base MySQL neuve et prefixe non standard `ouf_`.

Decision : **release candidate 0.7.4-beta validee**.

## Archive testee

```text
D:\Documents\Projets\ouinpo-suite-distribution\dist\ouinpo-suite-0.7.4-beta.zip
```

SHA256 :

```text
748D1ECBB45CCBD165C0918F26606064E2356C82F5AA435E03117F57427A1816
```

Controles ZIP :

- `ouinpo-suite/ouinpo-suite.php` present.
- Header `Version: 0.7.4-beta`.
- Constante `OUINPO_SUITE_VERSION` a `0.7.4-beta`.
- Correctif final present dans `InstallV2.php` : `run_db_delta`, `normalize_db_delta_sql`, `SCHEMA_LOCK_OPTION`.
- Aucun `dbDelta($sql_assessment_results)` ou `dbDelta($sql_assessment_attendance)`.
- Aucun `.git`, dump SQL, log, `.env`, `wp-config.php`, secret evident, `node_modules`, cache, temp ou zip imbrique detecte.

## Environnement fresh install

- Racine jetable : `C:\Users\vonk\AppData\Local\Temp\ouinpo-fresh-074-20260617022926`
- URL locale : `http://127.0.0.1:8094`
- Base MySQL : `ouinpo_fresh_074_20260617022926`
- MySQL : `8.4.0`
- PHP : `8.2.12`
- WordPress : `7.0`
- Prefixe de tables : `ouf_`
- Theme actif initial : `twentytwentyfive`
- Plugins actifs avant installation : aucun
- `ouinpo_exo_db_version` avant installation : absent
- Tables OuInPo avant installation : aucune

## Commandes source et build

Commandes executees avec succes :

```powershell
git status --short --branch
git diff --check
git diff --stat
git ls-files --others --exclude-standard
git add -N docs/audit-revue-complete-0.7.3-beta-ouinpo-test.md docs/fix-dbdelta-assessments-0.7.4-beta.md tools/check-exercises-schema.php
git diff --stat
php -l src/Modules/Exercises/plugin/inc/InstallV2.php
php -l tools/check-exercises-schema.php
php tools/check-exercises-schema.php
php tools/verify-packs.php
php tools/verify-optimizations.php
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-dist.ps1
Get-FileHash .\dist\ouinpo-suite-0.7.4-beta.zip -Algorithm SHA256
```

Resultats :

- lint PHP : OK.
- `tools/check-exercises-schema.php` : OK.
- `verify-packs.php` : OK, 5 fichiers verifies.
- `verify-optimizations.php` : OK, 142 verifications.
- `scripts/test-dist.ps1` : OK, 350 entrees.
- `git diff --check` : OK.

## Methode fresh install

Un WordPress jetable a ete cree a partir des fichiers core locaux de `ouinpo-test`, sans reprendre ses plugins actifs ni sa base :

- copie de `wp-admin`, `wp-includes`, fichiers racine WordPress et themes;
- creation d'un `wp-config.php` jetable avec `WP_DEBUG_LOG`;
- creation d'une base MySQL neuve;
- installation WordPress par `wp_install()`;
- extraction du ZIP reel dans `wp-content/plugins`;
- activation de `ouinpo-suite/ouinpo-suite.php` par `activate_plugin()`.

## Resultat activation

Activation : OK.

Options apres activation :

- `active_plugins` contient `ouinpo-suite/ouinpo-suite.php`.
- `ouinpo_suite_version` = `0.7.4-beta`.
- `ouinpo_exo_db_version` = `2.8.0`.
- `ouinpo_exo_assessment_schema_checked` = `2.8.0`.
- `ouinpo_exo_schema_migration_lock` absent apres migration.

Log WordPress :

- aucun `wp-content/debug.log` cree pendant l'activation et les tests.
- aucune erreur `ALTER TABLE ... ADD  `` (``)`.
- aucun warning `Undefined array key` dans `wp-admin/includes/upgrade.php`.

## Resultat HTTP

Tests via serveur PHP jetable :

- front `http://127.0.0.1:8094` : HTTP 200.
- REST `http://127.0.0.1:8094/wp-json/` : HTTP 200.
- admin `http://127.0.0.1:8094/wp-admin/` : HTTP 200, pas de fatal.
- front et REST charges plusieurs fois : HTTP 200.

Logs serveur PHP :

- requetes acceptees et fermees normalement.
- aucun fatal dans les logs serveur.

## Resultat SQL

Tables OuInPo creees avec prefixe non standard `ouf_`.

Tables critiques :

- `ouf_ouin_exo_assessment_results`
- `ouf_ouin_exo_assessment_attendance`

Colonnes `assessment_results` :

- `assessment_id`
- `user_id`
- `competency_id`
- `observed_status`
- `note`
- `updated_at`
- `updated_by`

Index `assessment_results` :

- `PRIMARY` sur `assessment_id,user_id,competency_id`
- `user_id`
- `competency_id`
- `observed_status`

Colonnes `assessment_attendance` :

- `assessment_id`
- `user_id`
- `is_absent`
- `note`
- `updated_at`
- `updated_by`

Index `assessment_attendance` :

- `PRIMARY` sur `assessment_id,user_id`
- `user_id`
- `is_absent`

`SHOW CREATE TABLE` confirme les schemas attendus et les contraintes FK historiques. Aucun index vide, aucune colonne vide, aucune cle primaire manquante, aucun doublon observe.

## Idempotence

Tests realises :

- chargement front repete;
- chargement `/wp-json/` repete;
- desactivation puis reactivation de `ouinpo-suite/ouinpo-suite.php`;
- controle `tools/check-exercises-schema.php --wp-load="...\wp-load.php"`;
- relecture SQL des index apres reactivation.

Resultats :

- aucune erreur SQL;
- aucun doublon de colonne;
- aucun doublon d'index;
- pas de verrou `ouinpo_exo_schema_migration_lock` residuel;
- `ouinpo_exo_db_version` reste a `2.8.0`;
- `ouinpo_exo_assessment_schema_checked` reste a `2.8.0`;
- aucun `debug.log` WordPress cree.

## Prefixe non standard

Le test a ete realise avec le prefixe `ouf_`.

Conclusion : la migration ne depend pas d'un prefixe `wp_` code en dur pour les tables verifiees.

## Mini smoke fonctionnel

Tests :

- menu admin `OuInPo Suite` detecte par bootstrap admin avec utilisateur administrateur;
- creation d'une page publiee avec `[ouinpo_exercises]`;
- rendu de la page shortcode : HTTP 200, pas de fatal;
- REST liste les routes sans fatal.

## Limites restantes

- Le test a utilise un WordPress jetable local construit depuis les fichiers core du site LocalWP existant, pas un second site LocalWP cree via interface graphique.
- Pas de test navigateur authentifie complet de l'admin.
- Pas de test multi-roles ou contenu pedagogique complet, hors perimetre de cette validation release candidate ciblee.

## Decision finale

**Release candidate 0.7.4-beta validee.**

La source, le ZIP reel, la fresh install, le prefixe non standard, l'activation, les schemas SQL critiques, l'idempotence et le smoke minimal sont valides. Le ZIP peut etre partage comme release candidate, sans push ni tag cree pendant cette validation.
