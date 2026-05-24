# v3.6.24 Core Block Style Guide Full Spec - Phase 3 Verification

Status: Phase 3 verification  
Date: 2026-05-24  
Route verified: Route D + M3  
Verdict: PASS

## 1. Verification Summary

v3.6.24 Phase 2 implementation passed full validation, generated-mirror cleanup, source/mirror anchor checks, and browser smoke through a local HTTP server.

```txt
implementation files:
  products/reference-implementations/axismundi-lab/style-guide-blocks.html
  styleguide/blocks.html

docs added so far:
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-0-PLAN.md
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-1-REPORT.md
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-2-IMPLEMENTATION.md
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-3-VERIFICATION.md
```

## 2. Validation Commands

```txt
npm run publish:styleguide       PASS
npm run validate:computed        PASS
npm run validate:specimen-wall   PASS
npm test                         PASS, Axis A-G 1.000
git diff --check                 PASS
```

`npm test` wrote generated validation reports:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

Both were restored after validation.

## 3. M3 Publish Tooling Verification

Phase 3 reran:

```powershell
npm run publish:styleguide
```

Publish output:

```txt
stylesheets/ copied/generated: 45 files
scripts copied:                style-guide.js, theme.js
HTML generated:
  style-guide.html        -> index.html
  style-guide-blocks.html -> blocks.html
  style-guide-prose.html  -> prose.html
README generated
publish surface total: 53 files
```

M3 cleanup performed:

```txt
Kept:
  styleguide/blocks.html

Restored:
  styleguide/README.md
  styleguide/index.html
  styleguide/prose.html
  styleguide/stylesheets/*

Removed:
  untracked generated module stylesheets created by publish tooling
```

This matches the v3.6.23/v3.6.24 M3 discipline: run the generator, keep intended mirror output, restore unrelated generated churn.

## 4. Source / Mirror Hash Stability

Post-Phase 3 hashes:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  SHA256 80BBB6F732FA61C630372BAFDAE991C72DA08E0E6CB6C912B7159471B1EB30EA

styleguide/blocks.html
  SHA256 E4C4B81652821F071ED4F4CA9839435443477B73461F1215F8BA727847D7271E
```

These match Phase 2 baseline hashes.

The files are intentionally not byte-identical because `styleguide/blocks.html` includes the publish banner and link rewrites from `tools/generators/publish_styleguide.py`.

## 5. Anchor Preservation

Preserved anchors:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  line 497: #blocks-table
  line 925: #blocks-search
  line 961: #blocks-theme

styleguide/blocks.html
  line 504: #blocks-table
  line 932: #blocks-search
  line 968: #blocks-theme
```

New anchors:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  line 348: #blocks-heading
  line 652: #blocks-text-gaps
  line 905: #blocks-design-gaps

styleguide/blocks.html
  line 355: #blocks-heading
  line 659: #blocks-text-gaps
  line 912: #blocks-design-gaps
```

`npm run validate:computed` passed, confirming the `#blocks-table` validator dependency remains intact.

## 6. Catalog Structure Verification

Source catalog now has 15 sections:

```txt
Text:
  blocks-paragraph
  blocks-heading
  blocks-quote
  blocks-code
  blocks-list
  blocks-table
  blocks-text-gaps

Media:
  blocks-media

Design:
  blocks-alignment
  blocks-separator
  blocks-group
  blocks-button
  blocks-design-gaps

Widgets:
  blocks-search

Theme:
  blocks-theme
```

Mirror catalog has the same 15 sections with the expected publish banner offset.

## 7. Four-State Classification Sample

Verified sample rows:

```txt
Implemented specimen:
  #blocks-heading
  core/cover inside #blocks-media
  core/media-text inside #blocks-media

Reference / gap row:
  Details
  Math
  Accordion
  Row / Stack / Grid

External prerequisite:
  Audio
  Video
  File
  Icon

Out of scope / route note:
  Classic
  Custom HTML -> style-guide-prose.html
  Theme/FSE blocks -> future template/plugin cycles
```

The Phase 0/1 four-state model was applied without adding CSS or validator scope.

## 8. Browser / Runtime Smoke

Phase 2 attempted `file://` browser smoke, but in-app Browser policy blocked direct file navigation. Phase 3 used a local HTTP server instead:

```powershell
python -m http.server 8765
```

Smoke URL:

```txt
http://127.0.0.1:8765/styleguide/blocks.html
```

Browser smoke result:

```txt
title:                 Axismundi - Block Catalog
section count:         15
console errors:        0
horizontal overflow:   false
viewport width:        1265
scroll width:          1265
```

Anchors present in browser:

```txt
blocks-table:       true
blocks-search:      true
blocks-theme:       true
blocks-heading:     true
blocks-text-gaps:   true
blocks-design-gaps: true
```

Observed nav labels:

```txt
Paragraph
Heading
Quote / Pullquote
Code / Verse
List
Table
Text gaps
Media specimens
Alignment helpers
Separator
Columns / Group / Card
Buttons
Design gaps
Search
Theme blocks route
```

Observed section order:

```txt
blocks-paragraph
blocks-heading
blocks-quote
blocks-code
blocks-list
blocks-table
blocks-text-gaps
blocks-media
blocks-alignment
blocks-separator
blocks-group
blocks-button
blocks-design-gaps
blocks-search
blocks-theme
```

## 9. Scope Verification

| Scope | Status | Evidence |
|---|---|---|
| Route D source catalog edit | PASS | source changed |
| M3 generated mirror retained | PASS | `styleguide/blocks.html` changed |
| #blocks-table preserved | PASS | source + mirror |
| #blocks-search preserved | PASS | source + mirror |
| #blocks-theme preserved | PASS | source + mirror |
| New anchors mirrored | PASS | heading/text-gaps/design-gaps in source + mirror |
| CSS unchanged | PASS | no CSS files remain modified |
| Validator unchanged | PASS | no validator files modified |
| Pilot unchanged | PASS | no Pilot files modified |
| Distributable unchanged | PASS | no distributable files modified |
| BACKLOG unchanged | PASS | no BACKLOG edit |
| D-layer artifacts restored | PASS | generated reports restored |
| Browser smoke | PASS | local HTTP smoke |

## 10. Lock 5 Count Chain

v3.6.24 selects the narrow implementation branch.

| Cycle | Overall | Impl-cycle | Variant |
|---|---:|---:|---|
| v3.6.17 | 7th | 5th | no-code packaging decision |
| v3.6.18 | 8th | 5th | no-code mapping audit decision |
| v3.6.19 | 9th | 6th | narrow docs hygiene |
| v3.6.20 | 10th | 6th | no-code boundary decision |
| v3.6.21 | 11th | 6th | no-code contract decision |
| v3.6.22 | 12th | 7th | narrow implementation |
| v3.6.23 | 13th | 8th | narrow implementation |
| v3.6.24 | 14th | 9th | narrow implementation |

`de106ab` remains a maintenance commit and is excluded from this chain.

## 11. Phase 4 Assessment

Phase 4 is not recommended.

Reason:

```txt
Media risk was contained through gap/prerequisite rows.
Theme/FSE risk was contained through route notes.
Validator risk was contained by preserving #blocks-table and avoiding new validator scope.
CSS tracked-copy risk was avoided by not editing CSS.
```

If Phase 4 remains unused, the recent unused chain becomes 13 consecutive cycles:

```txt
v3.6.5 / v3.6.6 / v3.6.9 / v3.6.14 / v3.6.16 /
v3.6.17 / v3.6.18 / v3.6.19 / v3.6.20 / v3.6.21 /
v3.6.22 / v3.6.23 / v3.6.24
```

## 12. Memory Candidate Notes

```txt
M7 tracked-copy mirror handling:
  strong. v3.6.22 used M2, v3.6.23 and v3.6.24 used M3.
  v3.6.24 adds untracked generated module stylesheet cleanup evidence.

M11 upstream snapshot freeze:
  watch. v3.6.24 reused v3.6.23 frozen snapshot instead of refetching trunk.

M12 six-category catalog presentation:
  stronger watch. v3.6.24 extended the v3.6.23 shell into a full-spec catalog.

M14 goal-direct pressure vs framework completeness:
  stronger watch. v3.6.24 kept catalog completeness ahead of distributable skeleton work.
```

## 13. Phase 5 Forward Notes

Phase 5 close should record:

```txt
- Route D + M3 final close
- 15-section catalog full spec
- Heading / Cover / Media & Text added
- gap rows by category
- #blocks-table validator anchor preserved
- local HTTP browser smoke success
- Lock 5 14th overall / 9th impl-cycle
- Phase 4 unused chain 13 cycles if no Phase 4 is opened
- G1/G2 progress reassessment before v3.6.25 distributable skeleton
```

