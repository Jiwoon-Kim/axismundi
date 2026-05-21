# v3.6.7 - WP Specimen Follow-On Editor Compatibility - Phase 5 Close

Date: 2026-05-21

Phase: 5 - Close

## Verdict

v3.6.7 is closed as the BACKLOG #44 editor compatibility / split fixture
cycle.

The cycle implements Route C:

```txt
Original front-end specimen wall: preserved
New editor-valid smoke fixture:   added
Specimen importer:                extended to import both pages
Specimen validator:               extended through the existing entry point
```

BACKLOG #44 remains narrow-open for follow-on coverage only:

```txt
mark/highlight coverage
long-line code coverage
deep pullquote coverage
Material Symbols follow-on coverage / #14 cross-reference
validator hardening polish
```

## Commits

```txt
c9d851a  Add v3.6.7 specimen editor compatibility plan
808d722  Document v3.6.7 specimen editor inventory
5338a33  Add v3.6.7 editor-valid specimen smoke
5861adc  Document v3.6.7 specimen editor QA
```

## Documents

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
```

## Closed In #44

The editor compatibility question for the existing specimen methodology is
closed by split fixtures.

```txt
Front-end evidence surface:
  products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
  slug: axismundi-core-block-specimen-wall
  local page: 29
  purpose: stable data-ax anchors for computed-style evidence

Editor-valid evidence surface:
  products/reference-implementations/axismundi-pilot/fixtures/core-block-editor-smoke.html
  slug: axismundi-core-block-editor-smoke
  local page: 41
  purpose: WordPress-save-compatible core block editor smoke
```

The original wall's `data-ax-specimen-*` anchors remain intentional and are not
required to be editor-valid. The new smoke fixture contains zero
`data-ax-specimen-*` attributes and validates cleanly in the editor.

## Route C Evidence

Phase 1 diagnosed the existing wall as a fixture/save mismatch, not a theme
bridge defect:

```txt
raw console/page errors:                 56
raw block validation heuristic count:    56
unique mismatch count:                   28
duplicate factor:                        2
```

The mismatch set came from committed fixture markup that is useful for
front-end evidence but not identical to WordPress core block save output:

```txt
core/group:     14
core/list:       1
core/table:      3
core/button:     5
core/separator:  3
core/column:     2
```

Route C preserves the old wall and adds a separate editor-valid surface rather
than weakening the stable front-end anchors.

## Phase 3 Evidence

Front-end wall:

```txt
HTTP status:          200
console/page errors:  0
horizontal overflow:  0
Tier 1 anchors:       11/11
findings:             0
```

Editor smoke front end:

```txt
HTTP status:          200
console/page errors:  0
horizontal overflow:  0
sections:             6
button links:         5
search blocks:        2
```

Editor smoke editor canvas:

```txt
editor iframe count:       1
editor console/page errors: 0
block validation errors:   0
invalid-content UI text:   0
Attempt recovery UI text:  0
```

Original wall editor reference:

```txt
editor iframe count:       1
editor console/page errors: 56
block validation errors:   56
invalid-content UI text:   0
Attempt recovery UI text:  0
```

The unchanged `56 / 56` reference confirms the split boundary: the old wall
remains front-end-only evidence; the new smoke fixture supplies editor-valid
evidence.

## BACKLOG #44 Narrow-Open

Remaining #44 scope after v3.6.7:

```txt
1. mark/highlight coverage
2. long-line code coverage
3. deep pullquote coverage
4. Material Symbols follow-on coverage / BACKLOG #14 cross-reference
5. validator hardening polish:
   - WP_ADMIN_USER / WP_ADMIN_PASS fallback for the Playwright login helper
   - strict editor smoke section count if the fixture contract should freeze
   - less timing-sensitive editor settle wait if flakiness appears
   - tmp output directory rename from phase1-specific to generic specimen-wall
```

Do not use #44 to reopen #41 bridge/reset work, the core/button semantic
boundary, custom blocks, plugin runtime, or TT5 implementation.

## BACKLOG #41 Status

v3.6.7 does not enter the narrowed BACKLOG #41 packaging decision.

The #41 status remains:

```txt
Open - narrowed by v3.6.6 to shared WordPress ripple runtime packaging decision
```

## Lock Compliance

```txt
Lock 1 - wp-custom downstream-only:
  preserved; Axis G remains 1.000.

Lock 2 - md-sys color maps to md-ref:
  preserved; Axis E remains 1.000.

Lock 3 - core/button semantic route before visual cleanup:
  preserved; core/button semantic routing was not reopened.

Lock 4 - semantic mismatch handling rule:
  preserved; fixture/save mismatch was isolated through Route C, not hidden by
  a visual patch.
```

## Methodology Finding

Diagnostic-first Phase 1 worked again in v3.6.7, after v3.6.5 and v3.6.6.

Decision:

```txt
Do not promote this to Lock 5 in v3.6.7.
```

Reason:

```txt
Three successful cycles all still reside in WP block bridge / specimen wall
territory. Reconsider Lock 5 promotion after diagnostic-first proves itself in
a different domain, such as Wave 2 component work, BACKLOG #21 plugin strategy,
or similar.
```

## Non-Goals Confirmed

```txt
theme.json edit:             no
functions.php edit:          no
pilot-block-bridge.css edit: no
pilot-block-bridge.js edit:  no
lab ripple edit:             no
components.css Section 0:    no
styleguide edit:             no
validate_theme_pilot.py edit:no
custom block work:           no
plugin runtime work:         no
TT5-derived implementation:  no
```

## Validation

Final close validation:

```txt
wp-env run cli wp core version: 7.0
python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS (Axis A/B/C/D/E/F/G all 1.000)
npm run validate:computed: PASS
git diff --check: PASS
```

## Next Route

Recommended primary next routes:

```txt
Wave 2 plan-first
BACKLOG #21 Interpreter Plugin strategy
```

Other viable routes remain:

```txt
BACKLOG #41 shared WordPress ripple runtime packaging decision
BACKLOG #44 remaining specimen coverage follow-ons
```

The next cycle must remain plan-first.
