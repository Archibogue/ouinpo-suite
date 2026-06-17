# Issue #20 - SegFault SQLite activation diagnostic

Date: 2026-06-17

## Summary

Issue #20 was reproduced as an activation robustness problem in the SegFault module.

The failure is not tied to year-closure schema creation. The MySQL/MariaDB activation path is healthy. The failing path is the SegFault private SQLite database opened during full plugin activation.

## Public asset tested

- Release: `v0.7.4-beta`
- Asset: `ouinpo-suite-0.7.4-beta.zip`
- SHA256: `748D1ECBB45CCBD165C0918F26606064E2356C82F5AA435E03117F57427A1816`

## MySQL/MariaDB control

Disposable local WordPress test:

- PHP: `8.2.12`
- WordPress: `7.0`
- MySQL: `8.4.0`
- Table prefix: `ouf_`

Results:

- `activate_plugin('ouinpo-suite/ouinpo-suite.php')`: OK
- Deactivate/reactivate: OK
- `/`: HTTP 200
- `/wp-json/`: HTTP 200
- `/wp-admin/`: login redirect then HTTP 200
- `[ouinpo_exercises]` smoke page: HTTP 200
- `php tools/check-exercises-schema.php --wp-load=<wp-load.php>`: OK
- No `PDOException unable to open database file`
- No WordPress `debug.log` created

## SQLite/SegFault reproduction

Disposable local WordPress test with an intentionally invalid SegFault upload path:

- `wp-content/uploads/ouinpo` was created as a file instead of a directory before activation.
- SegFault then tried to initialize its private SQLite database under `uploads/ouinpo/segfault/data/segfault.db`.
- `wp_mkdir_p()` could not create the nested SegFault directories.
- `DB::init()` reached `new PDO('sqlite:' . OUINPO_SF_DB)`.

Observed result before the fix:

```text
PDOException during SegFault DB init
```

Depending on the exact invalid filesystem state, PHP reports either:

```text
unable to open database file
```

or a path/open_basedir variant of the same root failure.

## Cause

`Plugin::activate()` activates every registered module. This includes SegFault even when SegFault is not enabled in the module settings used by normal boot.

During `SegFault\Module::activate()`:

1. `segfault.php` defines `OUINPO_SF_DB` from `wp_upload_dir()`.
2. `Storage::ensure_dirs()` attempts to create `uploads/ouinpo/segfault/data`.
3. `DB::init()` immediately opens the SQLite file with PDO.
4. If the uploads path is invalid or not writable, the PDO exception escapes activation.

This means a SegFault-specific local filesystem issue can break full activation of the entire suite.

## Fix

The fix is intentionally narrow:

- `DB::pdo()` now re-runs SegFault storage directory preparation before opening SQLite.
- `DB::pdo()` validates that the SQLite parent directory exists and is writable.
- PDO failures are normalized into a clear `RuntimeException`.
- `SegFault\Module::install()` catches SQLite init failures during activation, logs them, stores `ouinpo_sf_sqlite_init_error`, and lets the rest of the suite finish activation.
- A successful SegFault SQLite init clears `ouinpo_sf_sqlite_init_error`.

## Decision

This is a plugin robustness bug triggered by a local filesystem/configuration problem.

The release `v0.7.4-beta` remains valid for MySQL/MariaDB. A dedicated follow-up PR should carry the SegFault activation hardening fix.
