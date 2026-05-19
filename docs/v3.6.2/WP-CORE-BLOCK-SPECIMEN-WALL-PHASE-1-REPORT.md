# v3.6.2 — WP Core Block Specimen Wall — Phase 1 Report

Date: 2026-05-20

## Verdict

Phase 1 is implementation-complete.

The Tier 1 specimen wall fixture exists, can be imported into the Pilot through
WP-CLI, renders through WordPress, and passes the v0 render gate.

## Implemented

```txt
Fixture:
  products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html

Importer:
  tools/generators/build_pilot_specimen_wall.py

Render gate:
  tools/validators/validate_pilot_specimen_wall.js
  npm run validate:specimen-wall

Generated page:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
```

## Tier 1 Coverage

```txt
T1.01 core/paragraph       represented
T1.02 core/heading         represented
T1.03 core/list            represented
T1.04 core/quote           represented
T1.05 core/code            represented
T1.06 core/table           represented
T1.07 core/buttons/button  represented
T1.08 core/search          represented
T1.09 core/separator       represented
T1.10 core/group           represented
T1.11 core/columns/column  represented

Tier 1 block families represented: 11 / 11
```

Every Tier 1 family has a stable `data-ax-specimen-id` anchor and is
individually targetable by Playwright.

## Render Gate

```txt
HTTP 200:
  PASS

Console/page errors:
  PASS

Horizontal overflow at 390px viewport:
  PASS

Tier 1 anchors present:
  PASS — 11 / 11
```

Raw render-gate output:

```txt
tmp/phase1-specimen-wall/specimen-wall-render-gate.json
tmp/phase1-specimen-wall/specimen-wall-390.png
```

## Validation

```txt
python tools/generators/build_pilot_specimen_wall.py
  PASS — updated page 29

npm run validate:specimen-wall
  PASS — HTTP 200, no console/page errors, no horizontal overflow, 11/11 anchors

php -l products/reference-implementations/axismundi-pilot/functions.php
  PASS

npm test
  PASS — overall 1.000 with Axis E/F/G at 1.000

npm run validate:computed
  PASS

git diff --check
  PASS
```

## Scope Discipline

No bridge CSS, reset CSS, component baseline CSS, or token architecture files
were edited in Phase 1.

Phase 1 intentionally does not classify findings. Classification belongs to
Phase 2, after the wall is stable and the computed snapshot data can be read as
evidence.
