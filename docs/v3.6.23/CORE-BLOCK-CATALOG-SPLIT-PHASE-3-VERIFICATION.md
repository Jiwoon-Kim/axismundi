# v3.6.23 Core Block Catalog Split - Phase 3 Verification

Status: VERIFIED FOR REVIEW
Date: 2026-05-24
Cycle: v3.6.23
Phase: 3 - verification
Route verified: E + M3

## Verdict

Phase 3 verifies the v3.6.23 category-aware block catalog split.

```txt
catalog split:                 PASS
M3 publish-tooling mirror:     PASS
#blocks-table anchor:          PASS
computed-style validator:      PASS
browser smoke:                 PASS
generated artifact restore:    PASS
scope boundaries:              PASS
```

v3.6.23 remains a narrow implementation cycle.

## Verification Summary

Commands run:

```txt
npm run publish:styleguide
npm run validate:computed
npm run validate:specimen-wall
npm test
git diff --check
```

Results:

```txt
npm run publish:styleguide       PASS
npm run validate:computed        PASS
npm run validate:specimen-wall   PASS
npm test                         PASS, Overall 1.000
  A schema:  1.000
  B theme:   1.000
  C css:     1.000
  D runtime: 1.000
  E tokens:  1.000
  F bridge:  1.000
  G custom:  1.000
git diff --check                 PASS
```

`npm test` rewrote generated D-layer reports. They were restored:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

## M3 Publish Tooling Verification

`npm run publish:styleguide` regenerated the full publish surface:

```txt
stylesheets/ (45 files, paths rewritten)
scripts/style-guide.js
scripts/theme.js
style-guide.html -> index.html
style-guide-blocks.html -> blocks.html
style-guide-prose.html -> prose.html
README.md
```

Phase 3 preserved the intended output only:

```txt
styleguide/blocks.html
```

Unrelated generated churn was restored:

```txt
styleguide/README.md
styleguide/index.html
styleguide/prose.html
styleguide/stylesheets/**
```

This matches the Phase 2 route: source edit + publish-tooling mirror, without
expanding the commit surface to the entire generated publish directory.

## Source / Mirror Hash Stability

Hashes match the Phase 2 baseline:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  sha256: C2947C91D0730FAA0A7A3A9E4AB7E82ADFF66BA0A44B02841D17F241C1D74F44
  lines: 881
  CR bytes: 0

styleguide/blocks.html
  sha256: 2799E2E895AEBE525123C78DF10D540B98AB649EC7FB19B509E71E304AD2C99C
  lines: 888
  CR bytes: 0
```

The files are intentionally not byte-identical. The publish mirror includes
the generator banner and rewritten links.

## Validator Anchor Verification

Phase 1 identified `#blocks-table` as a computed-style validator dependency.
Phase 2 preserved it. Phase 3 rechecked both source and mirror:

```txt
source:
  products/reference-implementations/axismundi-lab/style-guide-blocks.html
  #blocks-table nav link
  id="blocks-table"
  #blocks-search nav link
  id="blocks-search"
  #blocks-theme nav link
  id="blocks-theme"

mirror:
  styleguide/blocks.html
  #blocks-table nav link
  id="blocks-table"
  #blocks-search nav link
  id="blocks-search"
  #blocks-theme nav link
  id="blocks-theme"
```

`npm run validate:computed` passed, confirming the validator still reaches the
table snapshots under `styleguide/blocks.html#blocks-table`.

## Browser / Runtime Smoke

Local static server:

```txt
http://127.0.0.1:8765/styleguide/blocks.html
```

Browser result:

```txt
console errors:       0
horizontal overflow:  false
page title:           Axismundi - Block Catalog
```

Navigation groups:

```txt
Text
Media
Design
Widgets
Theme
```

Anchors present:

```txt
blocks-table:   true
blocks-search:  true
blocks-theme:   true
```

Observed section order:

```txt
Text     blocks-paragraph   Paragraph
Text     blocks-quote       Quote & Pullquote
Text     blocks-code        Code / Preformatted / Poetry
Text     blocks-list        List
Text     blocks-table       Table
Media    blocks-media       Image & Gallery
Design   blocks-alignment   Alignment helpers
Design   blocks-separator   Separator - 5 styles
Design   blocks-group       Columns / Group / Card variants
Design   blocks-button      Buttons - 5 style variants
Widgets  blocks-search      Search
Theme    blocks-theme       Theme blocks route
```

## Scope Verification

| Scope item | Status |
|---|---|
| `style-guide-blocks.html` category split | PASS |
| `styleguide/blocks.html` generated mirror retained | PASS |
| `style-guide-prose.html` unchanged | PASS |
| Embeds excluded | PASS |
| Search added only as Widgets specimen | PASS |
| Theme blocks route note only, no FSE implementation | PASS |
| `#blocks-table` preserved | PASS |
| Pattern Overrides / Block Bindings not implemented | PASS |
| Theme switcher shell not added | PASS |
| Pilot files unchanged | PASS |
| Distributable files unchanged | PASS |
| D-layer generated reports restored | PASS |
| `git diff --check` clean | PASS |

## Phase 2 Close Criteria Recheck

```txt
1. Text / Media / Design / Widgets / Theme split implemented          PASS
2. Embeds excluded                                                     PASS
3. Table moved to Text, #blocks-table preserved                        PASS
4. Image / Gallery moved to Media                                      PASS
5. Existing specimens preserved                                        PASS
6. styleguide/blocks.html regenerated through publish tooling          PASS
7. style-guide-prose.html remains separate                             PASS
8. No Pilot / distributable / D-layer / theme.json / functions edits   PASS
9. WP 7.0 / 6.9 features reference-only / routed forward               PASS
10. Validation passes, including computed-style #blocks-table checks   PASS
```

## Lock 5 Count Chain

v3.6.23 is a narrow implementation variant.

| Cycle | Overall self-application | Implementation-cycle count | Variant |
|---|---:|---:|---|
| v3.6.17 | 7th | 5th | no-code packaging decision |
| v3.6.18 | 8th | 5th | no-code mapping audit decision |
| v3.6.19 | 9th | 6th | narrow docs hygiene |
| v3.6.20 | 10th | 6th | no-code boundary decision |
| v3.6.21 | 11th | 6th | no-code contract decision |
| v3.6.22 | 12th | 7th | narrow implementation |
| v3.6.23 | 13th | 8th | narrow implementation |

The `de106ab` handoff-doc maintenance commit remains outside the Lock 5 count
chain.

## Phase 4

Phase 4 remains intentionally unused.

The v3.6.23 implementation touched a bounded source/mirror pair and Phase 3
validated the mirror, anchor, computed styles, and browser smoke. No deeper
architecture audit was required.

If Phase 5 keeps Phase 4 unused, the recent unused chain becomes:

```txt
v3.6.5 / v3.6.6 / v3.6.9 / v3.6.14 / v3.6.16 /
v3.6.17 / v3.6.18 / v3.6.19 / v3.6.20 / v3.6.21 /
v3.6.22 / v3.6.23
```

## Memory Candidate Watch

M7 - tracked copy / mirror handling framework:

```txt
status: strong PROMOTE candidate at Phase 5
evidence:
  v3.6.22 selected M2 for byte-identical tracked copies
  v3.6.23 selected M3 for generator-mediated source/mirror output
```

M11 - WordPress upstream snapshot freeze pattern:

```txt
status: WATCH
evidence:
  Gutenberg trunk snapshot commit/date frozen in Phase 0/1
```

M12 - WordPress category catalog presentation contract:

```txt
status: WATCH
evidence:
  v3.6.23 implements the 5-category catalog split
```

M13 - validator anchor preservation:

```txt
status: WATCH or fold into M9
evidence:
  Phase 1 found #blocks-table validator dependency
  Phase 2 preserved source + mirror anchors
  Phase 3 validate:computed passed
```

M10 - trust-but-verify implementation review pattern:

```txt
status: WATCH
evidence:
  Phase 2 review verified actual source/mirror sample lines and anchor state
```

## Phase 5 Forward Notes

Phase 5 should record:

```txt
1. v3.6.23 closes the v3.6.18 Layer 3 catalog split follow-on.
2. Route E + M3 selected and verified.
3. M3 publish tooling generated full mirror churn; only blocks.html was retained.
4. #blocks-table anchor preservation prevented computed validator drift.
5. Search is the only Widgets specimen added.
6. Theme blocks remain route-note only.
7. Embeds remain excluded.
8. Pattern Overrides / Block Bindings remain BACKLOG #21 / plugin territory.
9. Lock 5 count: 13th overall / 8th implementation-cycle.
10. M7 is a strong promotion candidate; M13 may fold into M9.
```
