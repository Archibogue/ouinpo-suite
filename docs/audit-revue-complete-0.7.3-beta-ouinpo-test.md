# Audit revue complete OuInPo Suite 0.7.3-beta sur ouinpo-test

Date de revue : 2026-06-17, Europe/Paris.

## 1. Resume executif

L'archive `dist/ouinpo-suite-0.7.3-beta.zip` existe, contient bien `ouinpo-suite/ouinpo-suite.php`, declare `Version: 0.7.3-beta`, `Requires at least: 6.4`, `Requires PHP: 8.1` et `OUINPO_SUITE_VERSION = 0.7.3-beta`.

Le depot courant n'est toutefois plus aligne avec cette version : le point d'entree racine `ouinpo-suite.php` declare `0.7.4-beta`, et `scripts/test-dist.ps1` reconstruit une archive `0.7.4-beta`. L'audit fonctionnel a donc cible l'archive 0.7.3-beta extraite en temporaire, puis installee provisoirement sur LocalWP `ouinpo-test`.

Resultat global : **partageable sous conditions, pas pret a partager tel quel**. Aucun fatal error n'a ete observe sur le front ou `/wp-json/` apres installation temporaire de l'archive 0.7.3-beta. En revanche, les logs LocalWP contiennent des erreurs SQL `dbDelta` critiques sur des tables d'evaluations, et l'activation exacte d'une installation vierge 0.7.3-beta n'a pas pu etre rejouee proprement via WP-CLI car `wp` n'est pas disponible dans le PATH et le bootstrap PHP CLI est bloque par un plugin tiers (`ultimate-member`). La revue statique et les tests HTTP/MySQL restent probants sur plusieurs zones critiques.

## 2. Environnement teste

- Depot : `D:\Documents\Projets\ouinpo-suite-distribution`
- Branche Git : `feature/year-closure-cycles...origin/feature/year-closure-cycles`
- Dernier commit : `5f963c3 fix: preserve project access during yearly closure`
- PHP CLI depot : PHP 8.2.12
- Site LocalWP : `C:\Users\vonk\Local Sites\ouinpo-test\app\public`
- URL locale : `http://ouinpo-test.local`
- Base LocalWP : MySQL 8.4.0, base `local`, port `10005`
- WordPress local : accessible par HTTP ; version non obtenue via WP-CLI global car `wp` absent
- Plugin local avant audit : `ouinpo-suite` actif en `0.7.4-beta`
- Plugin teste : archive `dist/ouinpo-suite-0.7.3-beta.zip`, installee temporairement puis restauree vers l'etat initial

## 3. Commandes executees

Commandes principales :

- `Get-ChildItem -Recurse -Filter ouinpo-suite.php`
- `git status --short --branch`
- `git log -1 --oneline`
- `git diff --check`
- `php -v`
- `wp --info` : echec, commande absente
- `php .\tools\verify-packs.php`
- `php .\tools\verify-optimizations.php`
- `powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-dist.ps1`
- `php -l` sur les fichiers PHP source, hors copies de distribution et bibliotheques tierces
- extraction temporaire de `dist/ouinpo-suite-0.7.3-beta.zip`
- `php -l` sur les fichiers PHP de l'archive 0.7.3-beta extraite
- `tar -tf .\dist\ouinpo-suite-0.7.3-beta.zip`
- `mysqldump` LocalWP avant installation temporaire
- copie de sauvegarde du plugin LocalWP avant installation temporaire
- `Invoke-WebRequest http://ouinpo-test.local`
- `Invoke-WebRequest http://ouinpo-test.local/wp-json/`
- tests REST anonymes sur routes Projects, SegFault, fichiers ecrits
- requetes MySQL directes sur `wp_options`, `wp_users`, `wp_posts`, tables `wp_ouin%`

## 4. Etat Git avant/apres

Avant audit :

```text
## feature/year-closure-cycles...origin/feature/year-closure-cycles
```

Apres audit, avant creation du rapport :

```text
## feature/year-closure-cycles...origin/feature/year-closure-cycles
```

`git diff --check` : aucun probleme signale.

Apres creation du rapport, seul ce fichier doit apparaitre comme modification attendue :

```text
docs/audit-revue-complete-0.7.3-beta-ouinpo-test.md
```

## 5. Resultat des verifications statiques

Inventaire depot complet :

- PHP : 576 fichiers
- CSS : 116 fichiers
- Markdown : 85 fichiers
- JS : 56 fichiers
- JSON : 31 fichiers
- ZIP : 8 archives dans `dist/` et `release-test/`

Inventaire archive 0.7.3-beta extraite :

- 208 fichiers PHP
- 55 fichiers CSS
- 33 fichiers Markdown
- 24 fichiers JS
- 7 fichiers JSON
- 4 fichiers `.txt`
- 2 fichiers `.htm`
- 1 `.php-dist`
- 1 `.png`
- 1 `.jpg`

Archive 0.7.3-beta :

- 338 entrees
- aucun `.env`, `wp-config.php`, `auth.json`, `secrets.php`, `.sql`, `.log`, `.wxr`, `.zip`, `node_modules` ou `.git` detecte dans le zip
- structure WordPress OK : `ouinpo-suite/ouinpo-suite.php`

Lint PHP :

- Source hors `dist/`, `ouinpo-suite-prod`, `release-test`, `pdfparser`, `parsedown`, `fpdf` : OK
- Archive extraite 0.7.3-beta : OK, aucun fichier en erreur
- Un lint lance en parallele sur `dist/build` a ete fausse par `test-dist.ps1`, qui a reconstruit `dist/build` pendant l'execution ; le controle fiable est celui fait sur l'archive 0.7.3 extraite en temporaire.

Scripts :

- `tools/verify-packs.php` : OK, 5 packs verifies
- `tools/verify-optimizations.php` : OK, 142 verifications
- `scripts/test-dist.ps1` : OK, mais construit la version courante `0.7.4-beta`, pas `0.7.3-beta`

## 6. Resultat installation / activation sur ouinpo-test

Actions realisees :

- Base sauvegardee avant test : `C:\Users\vonk\Local Sites\ouinpo-test\app\public\ouinpo-test-before-ouinpo-suite-0.7.3-beta-20260617-014737.sql`
- Plugin local sauvegarde : `wp-content/plugins/ouinpo-suite-before-audit-073-20260617-014737`
- Archive 0.7.3-beta extraite puis copiee temporairement dans `wp-content/plugins/ouinpo-suite`
- Version fichier verifiee : `0.7.3-beta`
- Front local teste : HTTP 200
- `/wp-json/` teste : HTTP 200
- Plugin local restaure ensuite en `0.7.4-beta`
- Copie testee conservee : `wp-content/plugins/ouinpo-suite-tested-073-20260617-015157`

Limite importante : le plugin etait deja actif avant le test. Remplacer les fichiers a permis de tester le boot HTTP de 0.7.3-beta, mais n'a pas rejoue `register_activation_hook`. L'option locale `ouinpo_suite_version` est restee a `0.7.4-beta`, et `ouinpo_exo_db_version` a `2.8.0`.

## 7. Resultat des tests admin

WP-CLI global absent, et le bootstrap WordPress par PHP CLI 8.3 avec l'ini LocalWP est bloque par un fatal d'un plugin tiers `ultimate-member` :

```text
ftp_pwd(): Argument #1 ($ftp) must be of type FTP\Connection, null given
```

Les pages admin n'ont donc pas ete pilotees en navigateur automatise. La revue statique confirme :

- menus Suite Admin presents dans `src/Core/Admin/SuiteAdmin.php`
- nonces presents sur formulaires de modules, packs, referentiel BO, droits, pages
- `ModuleSettings::lockedModules()` verrouille `exercises`
- `ModuleSettings::normalizeModules()` applique une allowlist et reinjecte les modules verrouilles

## 8. Resultat front / shortcodes

Le front local repond en 200 apres installation temporaire 0.7.3-beta. Les pages locales contenant des shortcodes OuInPo existent deja, notamment `Exercices`, `Exercice`, `Mes Badges`, `Parcours`, `Carte du site`, `Sujets de l'Epreuve Pratique`, `Epreuve Pratique`.

Limite : les rendus shortcodes authentifies et les captures visuelles n'ont pas ete automatises faute de Playwright/Cypress disponible et faute de session admin utilisable via WP-CLI. Les routes REST sous-jacentes repondent et sont listees via `/wp-json/`.

## 9. Resultat REST / permissions

Routes detectees :

- `ouinpo/v1`
- `ouinpo-projects/v1`
- `ouinpo-segfault/v1`
- `ouinpo-test/v1`

Tests anonymes :

- `GET /wp-json/ouinpo-projects/v1/projects` : 401 `Connexion requise`
- `GET /wp-json/ouinpo-projects/v1/projects/1/board` : 401 `Connexion requise`
- `POST /wp-json/ouinpo-segfault/v1/chat` : refuse dans l'etat local
- `POST /wp-json/ouinpo-segfault/v1/public-chat` : 403 car `ouinpo_sf_members_only=1`
- `POST /wp-json/ouinpo-segfault/v1/memory/clear` : refuse dans l'etat local
- `GET /wp-json/ouinpo/v1/written-files/1/download` : 404 propre, pas de fuite de chemin
- `POST /wp-json/ouinpo/v1/written-questions/1/student-advice` : 404 propre si question absente

Revue statique REST :

- Projects : toutes les routes de `RestController` ont un `permission_callback`.
- Projects : `requireLoggedInRestNonce()` est utilise avant les permissions metier.
- Projects : `moveTask()` verifie que la colonne cible appartient au projet de la tache.
- Projects : telechargement evidence privee passe par `canDownloadEvidence`, metas attachment/projet/evidence, puis chemin borne.
- Routes publiques explicites : badges GET, options de competences, telechargements ecrits avec verification interne, conseil IA ecrit avec refus si IA publique non active.

## 10. Resultat modules

Core / Suite Admin :

- Boot sur `plugins_loaded` via point d'entree.
- Activation centrale dans `Plugin::activate()`.
- `Installer::maybeUpgrade()` est appele au boot et quitte si version installee >= courante.
- Risque : toute erreur de version/option peut declencher des migrations au boot.

Exercises :

- Charge obligatoirement.
- `ModuleInstaller::maybe_upgrade()` appele pendant le boot du module.
- Lint OK.
- REST riche et permissions differenciees.
- Point critique : erreurs `dbDelta` constatees en logs LocalWP sur tables d'evaluations.

Flashcards :

- Routes REST detectees.
- Installateur dedie.
- Pas d'anomalie bloquante en revue statique rapide.

Submissions :

- Uploads et telechargements proteges par capabilities et nonces.
- Code plus ancien et monolithique ; dette technique plus forte que Projects.

SegFault :

- IA par defaut fermee dans le code (`ouinpo_ai_public_enabled=0`, `ouinpo_sf_public_albert_enabled=0`, cles vides).
- Logs IA synthetiques uniquement si `WP_DEBUG` et option `ouinpo_ai_debug_logs=1`.
- Clefs saisies en password et masquees par `AiSettings`.
- Routes publiques refusent proprement selon les options locales.

Gate :

- FPDF embarque avec licence.
- Assets certificat presents.
- Non teste jusqu'a generation PDF faute de session admin/front dediee.

RechText / Meta :

- Aucun bloquant identifie en revue rapide.

## 11. Resultat Projects

Points positifs :

- Service de permissions centralise dans `ProjectPermissionService`.
- `canViewProject()` exige membre, enseignant createur/professeur, ou cap globale.
- Membres archives geres via `access_level`.
- Taches : edition limitee au manager ou createur/assigne si membre avec droit `can_edit`.
- Livrables : validation separee par capability `PROJECTS_VALIDATE`.
- Evidence : upload via `PrivateFiles::storeUploadedFile()`, chemin relatif borne, attachment marque prive.
- Telechargement evidence : route REST protegee, verification projet/evidence/metas, pas d'URL directe publique pour les fichiers prives.
- Dossier LocalWP `wp-content/uploads/ouinpo/projects` protege par `index.php` et `.htaccess`.
- Acces direct HTTP a `uploads/ouinpo/projects/index.php` : 403.

Limite : la recette complete multi-utilisateurs (admin, teacher, student_a, student_b, outsider) n'a pas ete executee car WP-CLI est indisponible et le bootstrap PHP CLI est bloque par `ultimate-member`.

## 12. Resultat IA absente ou desactivee

Defaults code :

- IA publique desactivee par defaut.
- IA eleve Projects desactivee par defaut.
- Cles API vides par defaut.
- Messages propres en cas d'IA non activee ou classe OpenAI absente sur les routes ecrites.

Etat local :

- `ouinpo_sf_members_only=1`
- `ouinpo_sf_public_albert_enabled=1`
- `ouinpo_projects_student_ai_enabled=1`

Ces valeurs locales heritees ne representent pas une installation vierge. Les tests anonymes ont ete refuses proprement.

## 13. Resultat fichiers / uploads prives

Projects :

- Extensions autorisees : `pdf`, `txt`, `md`, `csv`, `json`, `sql`, `py`, `png`, `jpg`, `jpeg`, `webp`, `zip`.
- Extensions dangereuses bloquees via `PrivateUploadValidator`.
- Refus fichiers vides.
- Refus dotfiles bruts.
- Verification MIME avec fallback controle pour fichiers texte/code.
- Chemin absolu refuse s'il sort de `uploads/ouinpo/projects`.
- Protection `.htaccess` presente et verifiee en HTTP.

Risque residuel : `zip` est autorise. C'est acceptable pour des preuves de projet si l'archive n'est jamais extraite cote serveur, mais a documenter clairement et a tester avec anti-bombe/limite taille.

## 14. Resultat packs JSON

- Tous les JSON `packs/` et `tests/packs/` testes sont valides.
- `tools/verify-packs.php` : OK.
- Aucun dump SQL/log/secret detecte dans l'archive.
- `packs/ouinpo-pack-exercices-site-origine.json` contient de nombreux liens et exemples `ouinpo.org` / `www.ouinpo.org`. Ce n'est pas une fuite de secret, mais c'est une dependance pedagogique/site visible pour un partage public multi-sites.

## 15. Points bloquants

Aucun P0 fatal confirme pendant l'installation temporaire HTTP de 0.7.3-beta.

Point proche bloquant release : erreurs SQL `dbDelta` dans les logs LocalWP pendant les migrations Exercises. A corriger avant partage public.

## 16. Risques importants

### P1 - Erreurs SQL dbDelta sur tables d'evaluations

- Gravite : P1 critique
- Fichiers : `src/Modules/Exercises/plugin/inc/InstallV2.php`
- Fonctions/classes : `InstallV2::upgrade_schema()`, `ModuleInstaller::maybe_upgrade()`, `Exercises\Module::boot()`
- Scenario : boot/admin LocalWP avec migration Exercises.
- Observe : logs LocalWP du 2026-06-16 23:23:14 UTC avec `ALTER TABLE wp_ouin_exo_assessment_results ADD  `` (``)` et `ALTER TABLE wp_ouin_exo_assessment_attendance ADD  `` (``)`, plus warnings `Undefined array key` dans `wp-admin/includes/upgrade.php`.
- Attendu : migration silencieuse, idempotente, sans SQL invalide.
- Cause probable : `dbDelta()` parse mal une definition de table/index, ou compare mal les index composites/ENUM sur MySQL 8.4.
- Correction recommandee : isoler les tables `assessment_results` et `assessment_attendance`, ajouter un test d'installation sur base vierge MySQL 8.4, remplacer les ajouts problematiques par `add_index_if_missing()`/SQL controle si besoin, et ne mettre a jour `ouinpo_exo_db_version` qu'apres verification.
- Risque regression : moyen, zone schema.
- Test a ajouter : test d'activation sur base vierge + reactivation + upgrade depuis version N-1 en verifiant absence de `wpdb->last_error`.

### P2 - Version de travail et archive demandee divergent

- Gravite : P2 important
- Fichiers : `ouinpo-suite.php`, `dist/ouinpo-suite-0.7.3-beta.zip`, `scripts/test-dist.ps1`
- Observe : racine en `0.7.4-beta`, archive demandee en `0.7.3-beta`, script de distribution reconstruit `0.7.4-beta`.
- Attendu : audit/release reproductible sur une source taggee 0.7.3-beta.
- Correction recommandee : auditer depuis un tag Git `v0.7.3-beta` ou conserver un manifeste de build avec commit exact et hash de zip.
- Test a ajouter : controle CI qui compare header, constante, nom du zip et changelog.

### P2 - Migrations appelees pendant le boot

- Gravite : P2 important
- Fichiers : `src/Core/Plugin.php`, `src/Modules/Exercises/Module.php`, `src/Modules/Exercises/plugin/inc/ModuleInstaller.php`
- Observe : `Installer::maybeUpgrade()` puis `ModuleInstaller::maybe_upgrade()` sont appeles pendant le boot.
- Attendu : les migrations lourdes ne doivent pas se declencher sur une requete front ordinaire, sauf garde de version tres fiable.
- Correction recommandee : garder la sortie rapide, mais journaliser/tester la duree et verrouiller les migrations avec transient/option de lock ; envisager un declenchement admin/activation pour les operations lourdes.
- Test a ajouter : boot front sans changement de version ne doit faire aucun `ALTER`, aucun `dbDelta`.

### P2 - Packs pedagogiques encore marques par ouinpo.org

- Gravite : P2 important pour partage public
- Fichiers : `packs/ouinpo-pack-exercices-site-origine.json`
- Observe : liens et exemples nombreux vers `ouinpo.org`.
- Attendu : contenu generique ou marque comme pack "site origine".
- Correction recommandee : garder ce pack comme optionnel, ajouter un pack demo neutre par defaut et documenter les liens d'exemple.
- Test a ajouter : scan anti-liens absolus hors allowlist par pack.

### P3 - Tests navigateur et roles incomplets

- Gravite : P3 amelioration / limite d'audit
- Observe : pas de Playwright/Cypress disponible, WP-CLI absent, bootstrap CLI bloque par plugin tiers.
- Attendu : recette role teacher/student/outsider automatisee.
- Correction recommandee : ajouter un script `tools/local-smoke.php` ou un WP-CLI local documente, avec creation de comptes de test et nettoyage non destructif.
- Test a ajouter : scenario Projects complet avec REST nonce et cookies par role.

## 17. Dette technique

- `Submissions` reste monolithique et plus difficile a auditer que Projects.
- Les modules historiques embarquent des plugins natifs avec styles/codage plus heterogenes.
- Bibliotheques tierces embarquees : FPDF, Parsedown, Smalot PDF Parser. Elles sont presentes avec notices/licences, mais le suivi de versions doit rester documente.
- Certains textes et packs restent tres marques OuInPo/SNT/NSI ; le theme BSIO existe mais le contenu demo neutre doit etre renforce.

## 18. Recommandations par priorite

1. Corriger et tester les erreurs `dbDelta` des tables d'evaluations avant toute diffusion large.
2. Faire un test d'activation 0.7.3-beta sur WordPress vierge + MySQL 8.4 + PHP 8.1/8.2, avec `WP_DEBUG_LOG`.
3. Creer un tag/source fige 0.7.3-beta ou reglementer l'audit sur 0.7.4-beta.
4. Ajouter une recette automatisee Projects multi-roles.
5. Documenter clairement l'usage des packs "origine" vs packs neutres.
6. Ajouter un controle CI de zip : interdits, version, hash, lint, verify-packs, verify-optimizations.

## 19. Correctifs proposes, non appliques

Aucun correctif fonctionnel n'a ete applique pendant cette revue.

Correctifs proposes :

- Patch minimal sur `InstallV2` apres reproduction isolee du `dbDelta`.
- Script non intrusif `tools/audit-localwp.php` ou commande WP-CLI locale pour creer comptes de test, pages brouillons et restaurer l'etat.
- Test de build qui empeche `test-dist.ps1` de produire une version differente de celle auditee.

## 20. Checklist finale

- Archive 0.7.3-beta presente : oui
- Structure `ouinpo-suite/ouinpo-suite.php` : oui
- Lint PHP : oui
- Scripts packs/optimisations : oui
- Zip sans secrets/dumps/logs : oui
- Installation HTTP locale 0.7.3-beta : oui, temporaire
- Activation hook rejoue exactement : non
- Admin teste en navigateur : non
- Front HTTP : oui
- REST route list : oui
- REST anonyme Projects refuse : oui
- Projects fichiers prives proteges : oui
- Tests multi-roles complets : non
- Tests Kanban complets : non
- Tests uploads dangereux reels : non, revue statique + verify-optimizations seulement
- Packs JSON valides : oui
- Pret a partager : **partageable sous conditions, pas pret tel quel**

## 21. Suivi correctif P1 dbDelta

Date de suivi : 2026-06-17, Europe/Paris.

Correctif applique dans `src/Modules/Exercises/plugin/inc/InstallV2.php` :

- les tables `assessment_results` et `assessment_attendance` ne passent plus par `dbDelta()`;
- creation par `CREATE TABLE IF NOT EXISTS`;
- ajouts conditionnels de colonnes, cle primaire et index via helpers dedies;
- reparation legere d'une installation deja marquee `ouinpo_exo_db_version=2.8.0` mais sans controle de schema valide;
- verrou `ouinpo_exo_schema_migration_lock` contre les reparations concurrentes;
- wrapper normalise pour les autres appels `dbDelta()` Exercises afin d'eviter les lignes vides parsees comme index vides;
- garde d'erreur : `ouinpo_exo_db_version` n'est plus avance si la migration controlee echoue.

Fichier de controle ajoute :

- `tools/check-exercises-schema.php`

Documentation detaillee :

- `docs/fix-dbdelta-assessments-0.7.4-beta.md`

Validations executees :

- `php -l src/Modules/Exercises/plugin/inc/InstallV2.php` : OK
- `php -l tools/check-exercises-schema.php` : OK
- `php tools/check-exercises-schema.php` : OK
- `git diff --check` : OK
- `php tools/verify-packs.php` : OK, 5 fichiers verifies
- `php tools/verify-optimizations.php` : OK, 142 verifications
- `powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\test-dist.ps1` : OK, `dist/ouinpo-suite-0.7.4-beta.zip`, 350 entrees
- LocalWP `http://ouinpo-test.local` : HTTP 200
- LocalWP `http://ouinpo-test.local/wp-json/` : HTTP 200
- LocalWP `http://ouinpo-test.local/wp-admin/` : 302 vers login, pas de fatal
- MySQL direct LocalWP : colonnes et index attendus presents sur les deux tables
- Reparation partielle simulee : index `observed_status` retire, `ouinpo_exo_db_version` conserve a `2.8.0`, boot HTTP, index recree et marqueur remis a `2.8.0`
- Idempotence : boots repetes, aucun doublon de colonne/index, aucun log supplementaire apres ZIP final
- Upgrade force : `ouinpo_exo_db_version` abaisse a `2.7.9`, boot HTTP, retour a `2.8.0`, pas de nouveau `ALTER TABLE ... ADD  `` (``)` apres normalisation

Sauvegarde DB LocalWP creee :

```text
C:\Users\vonk\AppData\Local\Temp\ouinpo-test-before-p1-dbdelta-fix-20260617-020458.sql
C:\Users\vonk\AppData\Local\Temp\ouinpo-test-before-zip-074-dbdelta-validation-20260617-021323.sql
```

Archive reconstruite et testee :

```text
D:\Documents\Projets\ouinpo-suite-distribution\dist\ouinpo-suite-0.7.4-beta.zip
```

Limite restante :

- le bootstrap CLI via `wp-load.php` est bloque localement par une erreur WordPress de connexion DB ; le script sort maintenant en erreur explicite dans ce cas.
- fresh install WordPress vierge non executee faute de second site/snapshot vierge disponible pendant ce tour.
- upgrade depuis une vraie archive 0.7.3-beta restauree non execute ; le cas partiellement migre a ete valide par simulation non destructive hors suppression de donnees.

Statut P1 : **corrige pour la source 0.7.4-beta et pour l'archive reconstruite testee sur LocalWP ouinpo-test. A completer par une fresh install CI ou un deuxieme site LocalWP vierge avant partage public large.**

## 22. Validation fresh install / release candidate 0.7.4-beta

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
- Plugins actifs avant installation : aucun.
- Tables/options OuInPo avant installation : absentes.

Validations :

- build `scripts/test-dist.ps1` : OK, 350 entrees.
- activation du ZIP : OK.
- front : HTTP 200.
- `/wp-json/` : HTTP 200.
- admin : HTTP 200, pas de fatal.
- `tools/check-exercises-schema.php --wp-load=...` : OK.
- `assessment_results` et `assessment_attendance` crees avec colonnes, cles primaires et index attendus.
- `ouinpo_exo_db_version` = `2.8.0`.
- `ouinpo_exo_assessment_schema_checked` = `2.8.0`.
- aucun verrou `ouinpo_exo_schema_migration_lock` residuel.
- aucun `debug.log` WordPress cree.
- aucun nouveau `ALTER TABLE ... ADD  `` (``)`.
- idempotence validee par chargements repetes et cycle desactivation/reactivation.
- menu admin `OuInPo Suite` detecte.
- page smoke `[ouinpo_exercises]` : HTTP 200, pas de fatal.

Documentation detaillee :

- `docs/release-validation-0.7.4-beta.md`

Statut release candidate : **0.7.4-beta validee comme release candidate partageable**.
