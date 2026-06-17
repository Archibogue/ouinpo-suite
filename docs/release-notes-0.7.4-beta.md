# OuInPo Suite 0.7.4-beta

## Status

Release candidate validated locally.

No tag or public GitHub Release has been created from these notes.

## Main fix

This release fixes the Exercises schema migration issue that could generate invalid `dbDelta()` SQL such as:

```sql
ALTER TABLE ... ADD  `` (``)
```

## What changed

- Controlled migration for `assessment_results`.
- Controlled migration for `assessment_attendance`.
- Automatic repair of partially migrated assessment schemas.
- Migration lock to avoid concurrent schema repairs.
- Safer `dbDelta()` wrapper for the remaining Exercises tables.
- DB version advancement blocked when critical migration errors occur.
- Added schema validation tool.

## Validated artifact

ZIP:

```text
dist/ouinpo-suite-0.7.4-beta.zip
```

SHA256:

```text
748D1ECBB45CCBD165C0918F26606064E2356C82F5AA435E03117F57427A1816
```

## Validation summary

- ZIP install tested.
- Fresh install tested.
- Non-standard DB prefix tested.
- Idempotence tested.
- Partial schema repair tested.
- No `ALTER TABLE ... ADD  `` (``)` observed after the final fix.
- No WordPress `debug.log` created during fresh validation.

## Known limits

- Wider multi-role browser recette remains recommended before broad public distribution.
- Production sites were not touched.
