# v3.6.19 Asset Surface Audit + Cross-Reference Index - Phase 5 Close

## Verdict

v3.6.19 is ready to close as Route B - Narrow Documentation Hygiene.

The cycle resolved the asset-surface authority question without consolidating
paths, moving assets, changing runtime references, or regenerating publish
mirrors.

## Documents

Cycle documents:

```txt
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-0-PLAN.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-1-REPORT.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-2-DECISION.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-3-VERIFICATION.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-5-CLOSE.md
```

Cross-cutting project document added by v3.6.19:

```txt
docs/ASSET-SURFACE-INDEX.md
```

`docs/ASSET-SURFACE-INDEX.md` is not a v3.6.19 cycle artifact. It survives the
cycle as a cross-cutting project document and may be referenced by future asset,
theme bootstrap, catalog, and distributable packaging work.

## Closed In v3.6.19

Closed:

- Asset surface authority audit.
- Cross-reference index route.
- Stale Material Symbols top-level asset README wording.
- Stale `Opus/Ogg` audio wording in root license / notice docs.
- Ambiguous brand "canonical draft" wording after user clarified the source SVG
  is complete and release seal means derivative-lock timing.

Not closed:

- Pilot vs distributable theme bootstrap.
- Release-seal derivative generation.
- Media catalog implementation.
- Distributable build-copy pipeline.

## Implementation Summary

Added:

```txt
docs/ASSET-SURFACE-INDEX.md
```

The index records seven asset surfaces:

```txt
assets/brand/
assets/media/
compare/brand-assets-research/
core/design-systems/material3/assets/
products/reference-implementations/axismundi-pilot/assets/
products/reference-implementations/ontology-theme-pilot/assets/
styleguide/
```

Narrow docs hygiene write surface:

```txt
core/design-systems/material3/assets/README.md
NOTICE.md
LICENSE-MATRIX.md
assets/brand/README.md
```

Phase docs added:

```txt
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-0-PLAN.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-1-REPORT.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-2-DECISION.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-3-VERIFICATION.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-5-CLOSE.md
```

## Drift Closure

| Finding | Status | Close evidence |
|---|---|---|
| D1 - Material Symbols top-level README stale | Closed | `core/design-systems/material3/assets/README.md` now states the three-part policy: all three style sets are stored; current runtime registers Rounded only; Outlined / Sharp route to plugin / future variation territory |
| D2 - `Opus/Ogg` stale wording | Closed | `NOTICE.md` and `LICENSE-MATRIX.md` now say MP3 source/reference plus Opus derivative |
| D3 - Pilot copy policy unclear | Closed as indexed policy | `docs/ASSET-SURFACE-INDEX.md` records Pilot as product-local copy surface with byte-identical M3 font/icon payloads at v3.6.19 |
| D4 - Brand source vs release seal wording | Closed | `assets/brand/README.md` states source SVGs are complete and deployment derivatives remain unlocked |
| D5 - Third-party brand research isolation | Preserved | `docs/ASSET-SURFACE-INDEX.md` records `compare/brand-assets-research/` as DO-NOT-SHIP |
| D6 - Media per-file provenance | Preserved | `docs/ASSET-SURFACE-INDEX.md` and `assets/LICENSES.md` preserve per-file provenance |

## Material Symbols Policy Preserved

v3.6.19 preserves the three-part Material Symbols policy:

```txt
Stored binaries:
  core/design-systems/material3/assets/icons/ stores Outlined, Rounded, and
  Sharp Material Symbols WOFF2 files with license/source records.

Registered current runtime:
  Current lab / Pilot / styleguide runtime CSS registers Material Symbols
  Rounded only.

Outlined / Sharp route:
  Outlined and Sharp remain plugin / future variation territory until a future
  cycle explicitly registers them.
```

No runtime CSS changed.

## Brand Source vs Release Seal

`assets/brand/*.svg` are complete project identity source assets.

The following deployment derivatives remain unlocked:

```txt
favicon
512 / 1024 PNG exports
screenshot.png
README hero
plugin icon
WordPress.org assets
```

These belong to a future release-seal or theme-bootstrap context.

## Non-Goals Confirmed

v3.6.19 did not:

- consolidate asset paths;
- move assets;
- delete assets;
- edit SVG, WOFF2, MP3, Opus, WebP, or WebM files;
- edit runtime CSS;
- edit PHP;
- edit `theme.json`;
- edit templates, pages, or patterns;
- edit `styleguide/`;
- regenerate publish mirrors;
- edit D-layer files;
- implement Pilot vs distributable bootstrap;
- implement media catalog work;
- generate release-seal derivatives.

## Validation

Phase 3 validation:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php PASS
npm test                                                           PASS
  Axis A/B/C/D/E/F/G all 1.000
python tools\generators\build_pilot_specimen_wall.py              PASS
npm run validate:specimen-wall                                     PASS
npm run validate:computed                                          PASS
git diff --check                                                   PASS
```

Generated artifact hygiene:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

These two files were rewritten by `npm test` and restored before Phase 3
verification closed.

## Lock Compliance

Lock 1:

- Preserved. No `wp-custom` relocation.

Lock 2:

- Preserved. No `md-sys` / `md-ref` edits.

Lock 3:

- Preserved. No `core/button` route change.

Lock 4:

- Preserved. Asset-surface authority is explicit before any visual/catalog or
  runtime work.

Lock 5:

- Preserved. Phase 1 diagnostic preceded Phase 2 documentation hygiene and
  Phase 3 verification.
- v3.6.19 is the ninth clean Lock 5 self-application overall.
- v3.6.19 is the sixth implementation-cycle application because Route B changed
  cross-cutting docs and narrow surface READMEs after Phase 1 evidence.

## Routed Forward

Candidate follow-ons remain plan-first:

- Pilot vs distributable theme bootstrap.
- Release-seal derivative generation:
  - favicon;
  - PNG exports;
  - `screenshot.png`;
  - README hero;
  - plugin icon;
  - WordPress.org assets.
- Distributable build-copy pipeline:
  - decide whether Pilot copy surfaces become generated build outputs;
  - decide how future distributable themes copy M3 runtime assets.
- Material Symbols Outlined / Sharp runtime enablement, if ever desired.
- Third-party video isolation decision for the Pixabay placeholder.
- `ontology-theme-pilot/assets/` modernization or explicit freeze, if reopened.
- Media catalog implementation from the v3.6.18 mapping audit.
- BACKLOG #21 Interpreter Plugin strategy.
- BACKLOG #44 specimen coverage, especially long-line code and deep pullquote.
- BACKLOG #46 disabled ripple host hygiene.
- BACKLOG #47 popover provider hygiene.
- v3.6.15-v3.6.17 diagnostics policy follow-ons.

## Asset Index Maintenance Rule

Future cycles touching any asset surface should update the matching row of
`docs/ASSET-SURFACE-INDEX.md` if authority, ownership, license class, shipping
posture, consumer/reference pattern, policy notes, or follow-on status changes.

Do not collapse asset surfaces into one directory unless a future architecture
cycle explicitly reopens path-as-policy.

## Phase 4

Phase 4 was intentionally unused.

This matches the recent cadence where Phase 4 is reserved for deeper
architecture audit needs and remains unused when Phase 1 / 2 / 3 resolve the
cycle cleanly.

## Close Verdict

v3.6.19 is ready for close review.

Commit / push should wait for Opus Phase 5 verdict and explicit user commit
GO.
