# v3.6.24 Core Block Style Guide Full Spec - Phase 5 Close

Status: CLOSED - ready for commit  
Date: 2026-05-24  
Route: D - Source + Mirror Full Spec  
Mirror handling: M3 publish-tooling regeneration  
Lock 5: 14th overall / 9th implementation-cycle

## 1. Verdict

v3.6.24 closes the Core Block Style Guide full-spec cycle.

```txt
Closed:
  - v3.6.23 6-category shell extended into a 15-section full-spec catalog
  - Heading specimen added
  - Cover specimen added
  - Media & Text specimen added
  - Text / Media / Design / Widgets / Theme gap rows added
  - #blocks-table validator anchor preserved
  - #blocks-search category anchor preserved
  - #blocks-theme category anchor preserved
  - source catalog and generated mirror updated through M3

Not closed:
  - distributable skeleton
  - page templates
  - release-seal derivatives
  - wp.org readiness
  - Theme/FSE implementation
  - Pattern Overrides / Block Bindings runtime
  - Embeds provider policy
```

## 2. Cycle Document Inventory

```txt
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-0-PLAN.md
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-1-REPORT.md
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-2-IMPLEMENTATION.md
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-3-VERIFICATION.md
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-5-CLOSE.md
```

No cross-cutting non-cycle document was added in v3.6.24.

## 3. Implementation Surface

Expected single close commit scope:

```txt
added:
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-0-PLAN.md
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-1-REPORT.md
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-2-IMPLEMENTATION.md
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-3-VERIFICATION.md
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-5-CLOSE.md

modified:
  products/reference-implementations/axismundi-lab/style-guide-blocks.html
  styleguide/blocks.html
```

Total:

```txt
7 files
```

Recommended commit message:

```txt
Close v3.6.24 core block style guide full spec
```

## 4. Closed Decisions

### 4.1 Full Spec Definition

v3.6.24 confirmed that "full spec" does not mean implementing every WordPress core block.

It means each relevant block is explicit in one of four states:

```txt
Implemented specimen
Reference / gap row
External prerequisite
Out of scope
```

This closes the ambiguity left by the v3.6.23 shell.

### 4.2 Catalog Structure

The catalog now has 15 sections:

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

### 4.3 Implemented Specimen Additions

v3.6.24 added three implemented specimen surfaces:

```txt
Heading:
  backed by v3.6.2 Tier 1 + base.css semantic heading mapping.

Cover:
  bounded image-derived media specimen; no CSS contract added.

Media & Text:
  bounded image + text composition specimen; no CSS contract added.
```

### 4.4 Gap Rows

v3.6.24 added explicit gap/reference rows for:

```txt
Text:
  Details
  Math
  Classic

Media:
  Audio
  Video
  File
  Icon

Design:
  Accordion
  Row / Stack / Grid
  More / Page Break / Spacer

Widgets:
  Custom HTML -> prose route
  Archives / Calendar / Terms List / Categories List
  Latest Comments / Latest Posts / Page List / RSS
  Shortcode / Social Icons / Tag Cloud

Theme:
  Navigation
  Query Loop / Post Template
  Site Logo / Site Title / Site Tagline
  Template Part
  Breadcrumbs
  Comments / terms / post metadata family
```

### 4.5 Media Boundaries

Media support is explicit:

```txt
Implemented:
  Image
  Gallery
  Cover
  Media & Text

External prerequisite:
  Audio
  Video
  File

Reference-only:
  Icon
```

Video remains blocked by Pixabay / third-party isolation. File remains blocked by missing placeholder file asset. Audio remains a native-control support question.

### 4.6 Theme/FSE Boundary

Theme blocks remain route notes and gap rows. v3.6.24 does not implement:

```txt
Navigation
Query Loop
Post Template
Site Logo
Template Part
Breadcrumbs
Theme/FSE runtime behavior
```

### 4.7 Pattern Overrides / Block Bindings

Pattern Overrides and Block Bindings remain reference-only and routed to BACKLOG #21 / future plugin territory. No runtime or PHP filter work was added.

### 4.8 Validator Anchors

Preserved:

```txt
#blocks-table
#blocks-search
#blocks-theme
```

Added:

```txt
#blocks-heading
#blocks-text-gaps
#blocks-design-gaps
```

No validator update was needed because only `#blocks-table` is currently validator-dependent.

### 4.9 Mirror Handling

v3.6.24 used M3:

```txt
1. Edit source catalog.
2. Run npm run publish:styleguide.
3. Keep intended styleguide/blocks.html output.
4. Restore unrelated generated churn.
5. Record source/mirror hashes.
6. Remove untracked generated module stylesheet artifacts produced by publish tooling.
```

### 4.10 Distributable Boundary

v3.6.24 did not begin distributable skeleton work. That was intentionally delayed after the user reframed v3.6.23 as a shell, not a full catalog.

## 5. Source / Mirror Evidence

Final hashes:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  SHA256 80BBB6F732FA61C630372BAFDAE991C72DA08E0E6CB6C912B7159471B1EB30EA

styleguide/blocks.html
  SHA256 E4C4B81652821F071ED4F4CA9839435443477B73461F1215F8BA727847D7271E
```

Line endings:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html: CR=0
styleguide/blocks.html: CR=0
docs/v3.6.24/*.md: CR=0
```

The files are intentionally not byte-identical because `styleguide/blocks.html` is a generated publish mirror with banner and link rewrites.

## 6. Validation Recap

Phase 3 verification passed:

```txt
npm run publish:styleguide       PASS
npm run validate:computed        PASS
npm run validate:specimen-wall   PASS
npm test                         PASS, Axis A-G 1.000
git diff --check                 PASS
```

Generated artifacts restored:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

Browser smoke:

```txt
server:       python -m http.server 8765
URL:          http://127.0.0.1:8765/styleguide/blocks.html
title:        Axismundi - Block Catalog
sections:     15
console errs: 0
overflow-x:   false
viewport:     1265
scroll width: 1265
```

Browser-confirmed anchors:

```txt
blocks-table       true
blocks-search      true
blocks-theme       true
blocks-heading     true
blocks-text-gaps   true
blocks-design-gaps true
```

## 7. Scope Verification

| Scope | Status | Evidence |
|---|---|---|
| Route D source catalog edit | PASS | source changed |
| M3 generated mirror retained | PASS | `styleguide/blocks.html` changed |
| #blocks-table preserved | PASS | source + mirror + computed validation |
| #blocks-search preserved | PASS | source + mirror |
| #blocks-theme preserved | PASS | source + mirror |
| Heading specimen added | PASS | `#blocks-heading` |
| Cover specimen added | PASS | `core/cover` specimen |
| Media & Text specimen added | PASS | `core/media-text` specimen |
| Gap rows added | PASS | Text / Media / Design / Widgets / Theme |
| CSS unchanged | PASS | no CSS files remain modified |
| Validator unchanged | PASS | no validator files modified |
| Pilot unchanged | PASS | no Pilot files modified |
| Distributable unchanged | PASS | no distributable files modified |
| BACKLOG unchanged | PASS | no BACKLOG edit |
| D-layer artifacts restored | PASS | generated reports restored |
| Browser smoke | PASS | local HTTP smoke |

## 8. Lock 5 Count Chain

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

This is the third consecutive narrow implementation cycle:

```txt
v3.6.22 -> v3.6.23 -> v3.6.24
impl-cycle 7 -> 8 -> 9
```

## 9. Phase 4 Status

Phase 4 intentionally unused.

Reason:

```txt
Media risk contained through gap/prerequisite rows.
Theme/FSE risk contained through route notes.
Validator risk contained by preserving #blocks-table and avoiding validator scope.
CSS tracked-copy risk avoided by not editing CSS.
```

If counted as unused, the recent Phase 4 unused chain becomes 13 consecutive cycles:

```txt
v3.6.5 / v3.6.6 / v3.6.9 / v3.6.14 / v3.6.16 /
v3.6.17 / v3.6.18 / v3.6.19 / v3.6.20 / v3.6.21 /
v3.6.22 / v3.6.23 / v3.6.24
```

## 10. Catalog Cycle Template Lessons

v3.6.23 and v3.6.24 form a reusable catalog-cycle pattern:

```txt
Phase 0:
  category framework, 4-state classification, strong non-goals

Phase 1:
  M9 grep first, IA inventory, 4-state matrix, route recommendation

Phase 2:
  Route D + M3, source edit, publish-tooling mirror, generated churn cleanup

Phase 3:
  full validation, local HTTP browser smoke, anchor preservation, sample 4-state checks

Phase 5:
  single 7-file close commit, Lock 5 advance, G1/G2 progress reassessment
```

This pattern should be reused for future catalog refresh or IA restructuring cycles.

## 11. Goal Alignment

### G1 - Style Guide to Theme Implementation

Progress estimate:

```txt
v3.6.23 close: ~30%
v3.6.24 close: ~50%
```

Reason:

```txt
v3.6.23 created the 6-category shell.
v3.6.24 added a full-spec catalog layer:
  - 3 new specimens
  - explicit gap rows
  - external prerequisites named
  - Theme/FSE boundaries clarified
```

The catalog is now a stronger input for product and template cycles.

### G2 - Theme Release / wp.org Submission

Progress estimate:

```txt
v3.6.23 close: ~10%
v3.6.24 close: ~12-15%
```

Reason:

```txt
v3.6.24 is still indirect prerequisite work.
Direct wp.org progress begins with distributable skeleton bootstrap.
```

## 12. v3.6.25 Readiness

v3.6.25 can now consider distributable skeleton bootstrap, but only with explicit user slug GO.

Required prerequisites from the skeleton memory:

```txt
1. distributable slug decision
2. namespace / text domain / constant prefix
3. asset-copy policy
4. product-local binaries decision
5. root placeholder media policy
6. release-seal derivative timing
7. minimum template set
8. readme.txt submission posture
9. screenshot.png content source
10. wp.org tag / submission compliance posture
```

Default candidate remains:

```txt
axismundi
```

But the next cycle must not create a skeleton without explicit user slug GO.

## 13. Memory Promotion Notes

### M7 - Tracked Copy / Mirror Handling Framework

Promotion/update candidate: YES, update existing memory after close.

Evidence added in v3.6.24:

```txt
v3.6.22: M2 selected for byte-identical tracked copies.
v3.6.23: M3 selected for generator-mediated catalog shell.
v3.6.24: M3 selected again for full-spec catalog.
v3.6.24: publish tooling created untracked generated module stylesheets;
         cleanup removed them from the commit surface.
```

Suggested memory update:

```txt
M3 optional step 6:
  remove untracked generated artifacts beyond intended scope if publish tooling
  creates additional outputs.
```

### M11 - Upstream Snapshot Freeze

Watch. v3.6.24 reused the v3.6.23 frozen snapshot and did not resnapshot trunk.

### M12 - Six-Category Catalog Presentation

Watch strengthened. v3.6.24 extended the v3.6.23 shell into a full-spec catalog.

### M14 - Goal-Direct Pressure vs Framework Completeness

Watch strengthened. v3.6.24 confirms the user reframing was correct: catalog completeness needed one more cycle before distributable skeleton work.

## 14. Routed Forward

Recommended priority:

```txt
1. Distributable skeleton bootstrap
   - requires explicit user slug GO
   - default candidate: axismundi
   - uses v3.6.20 boundary, v3.6.21 selector contract,
     v3.6.22 switcher implementation, and v3.6.23/24 catalog work

2. Page templates / patterns
   - after skeleton exists
   - uses v3.6.24 catalog full spec as page composition input

3. Release-seal derivatives
   - favicon / PNG exports / screenshot / README hero / wp.org assets
   - after product context exists

4. wp.org readiness audit
   - after distributable skeleton + templates + release-seal context
```

Other routed-forward work remains:

```txt
- Theme Switcher Route B comment hygiene
- BACKLOG #21 Interpreter Plugin strategy
- Pattern Overrides / Block Bindings runtime exploration
- Media source/provenance policy for Audio / Video / File
- Pixabay video isolation
- Embeds provider/privacy policy
- Core Block Catalog future upstream snapshot refresh
- BACKLOG #44 specimen coverage
- BACKLOG #46 disabled ripple host hygiene
- BACKLOG #47 popover provider hygiene
- root handoff meta-doc catchup when desired
```

## 15. Close Status

Ready for Phase 5 review.

Expected final pre-commit checks:

```txt
git status --short --branch
git diff --check
```

Expected commit:

```txt
Close v3.6.24 core block style guide full spec
```

