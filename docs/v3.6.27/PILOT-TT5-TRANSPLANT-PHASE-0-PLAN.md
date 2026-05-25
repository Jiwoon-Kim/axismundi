# v3.6.27 Phase 0 Plan - Pilot TT5 Structural Transplant

**Status:** Phase 0 - Plan Only
**Cycle:** v3.6.27 / 17th overall / 12th impl-cycle candidate
**Variant:** multi-surface Pilot implementation
**Date:** 2026-05-25
**Predecessor:** v3.6.26 TT5 docs + codebase audit exploration

---

## Verdict

Proceed to Phase 1 diagnostic before implementation.

v3.6.27 should transplant selected TT5 1.5 structural patterns into the
Axismundi Pilot only. TT5 is evidence for WordPress block-theme structure, not
visual design authority. Pilot keeps probe status; no distributable contract is
created or implied.

Allowed TT5-derived evidence:

```txt
theme.json schema/layout shape
template and part surface coverage
pattern-driven template-part organization
block style variation JSON file structure
section style JSON file structure
```

Blocked TT5-derived material:

```txt
hex palette values
Manrope / Fira Code font adoption
TT5 pattern content
TT5 clamp spacing/typography values as-is
TT5 visual language as Axismundi doctrine
```

---

## Source Inputs

### TT5 local source

```txt
C:\Users\thaum\dev\twentytwentyfive.1.5\twentytwentyfive\
```

Relevant TT5 surfaces from v3.6.26 exploration:

```txt
theme.json: version 3 / WP 6.7-facing theme
templates/: 8 files
parts/: 7 files
patterns/: 98 files
styles/: 8 named style variations + theme.json default = 9 styles
styles/blocks/: 4 block style JSON files
styles/sections/: 5 section style JSON files
styles/typography/: 7 typography preset JSON files
styles/colors/: 8 color preset JSON files
```

### Axismundi Pilot source

```txt
products/reference-implementations/axismundi-pilot/
```

Current Pilot baseline checked during Phase 0 plan preparation:

```txt
theme.json:
  $schema: https://schemas.wp.org/trunk/theme.json
  version: 3
  settings.layout.contentSize: 640px
  settings.layout.wideSize: 1280px
  palette: MD3 CSS variables, not hex values

style.css:
  Version: 0.1.0-pilot
  Requires at least: 6.5
  Tested up to: 6.9
  Requires PHP: 8.1

templates:
  index.html
  page.html
  single.html

parts:
  header.html
  footer.html

patterns:
  button-actions.php
  card-list.php
  hero.php
  prose-sample.php
  search-section.php

styles/:
  absent
```

### Ontology and matrix sources

```txt
docs/v3.6.26/TT5-DOCS-CODEBASE-AUDIT-PHASE-0-PLAN.md
docs/v3.6.25/*
corpus/webdesign-craftsman-written/matrix-seed.md
atlas/web-production-workflow/*
core/web-production/web-production-ontology.md
```

---

## Implementation Scope

Phase 2 may implement S1-S5 only after Phase 1 diagnostic and Reviewer review.

### S1 - theme.json + style.css compatibility update

Target changes:

```txt
theme.json:
  $schema:
    https://schemas.wp.org/trunk/theme.json
    -> https://schemas.wp.org/wp/6.7/theme.json

  settings.layout.contentSize:
    640px -> 645px

  settings.layout.wideSize:
    1280px -> 1340px

style.css:
  Version:
    0.1.0-pilot -> 0.2.0-pilot

  Requires at least:
    6.5 -> 6.7

  Tested up to:
    6.9 -> 7.0
```

Constraints:

```txt
Do not change palette token values.
Do not replace MD3 CSS variable colors with TT5 hex values.
Do not change font families to Manrope / Fira Code.
Do not alter Lock 1 or Lock 2 token ownership.
```

### S2 - templates expansion

Current Pilot templates: 3.

Target Pilot templates: 8.

Add:

```txt
products/reference-implementations/axismundi-pilot/templates/404.html
products/reference-implementations/axismundi-pilot/templates/archive.html
products/reference-implementations/axismundi-pilot/templates/home.html
products/reference-implementations/axismundi-pilot/templates/page-no-title.html
products/reference-implementations/axismundi-pilot/templates/search.html
```

Structure rule:

```txt
Use WordPress block-template structure:
  template-part header
  main group with tagName main
  template-part footer

Use Axismundi Pilot markup and existing styles.
Use axismundi-pilot namespace for slugs.
Do not copy TT5 template body content.
```

### S3 - parts pattern-driven conversion and optional additions

Current Pilot parts:

```txt
parts/header.html
parts/footer.html
```

Target decision:

```txt
parts/header.html -> pattern-driven one-line wrapper:
  <!-- wp:pattern {"slug":"axismundi-pilot/header"} /-->

parts/footer.html -> evaluate in Phase 1.
  Convert only if footer pattern ownership is clearer than direct part markup.
```

Optional additions, only if Phase 1 confirms template need:

```txt
parts/header-large-title.html
parts/sidebar.html
```

Constraints:

```txt
Do not copy TT5 part markup.
Do not introduce unused parts only for parity theater.
Every new part must be consumed by at least one template or documented as
route-forward.
```

### S4 - patterns

Create or update Pilot pattern files as needed:

```txt
products/reference-implementations/axismundi-pilot/patterns/header.php
products/reference-implementations/axismundi-pilot/patterns/footer.php
```

Header format follows WordPress pattern metadata structure:

```php
<?php
/**
 * Title: Header
 * Slug: axismundi-pilot/header
 * Categories: header
 * Block Types: core/template-part/header
 */
?>
<!-- Axismundi Pilot block markup here. -->
```

Phase 1 must check whether `functions.php` already provides the necessary
pattern support or whether WordPress automatic pattern discovery is sufficient.

Constraints:

```txt
Pattern content must come from existing Axismundi Pilot parts or newly authored
Pilot markup.
Do not import TT5 pattern body content.
```

### S5 - block style variation JSON files

Adopt TT5's file-structured style variation pattern without importing values.

Create candidate directories:

```txt
products/reference-implementations/axismundi-pilot/styles/blocks/
products/reference-implementations/axismundi-pilot/styles/sections/
```

Candidate text style files:

```txt
styles/blocks/01-display.json
styles/blocks/02-subtitle.json
styles/blocks/03-annotation.json
```

Candidate section style files:

```txt
styles/sections/section-1.json
styles/sections/section-2.json
styles/sections/section-3.json
styles/sections/section-4.json
styles/sections/section-5.json
```

Value rules:

```txt
Use Axismundi MD3 sys color tokens:
  var(--md-sys-color-surface)
  var(--md-sys-color-surface-variant)
  var(--md-sys-color-primary-container)
  var(--md-sys-color-secondary-container)
  var(--md-sys-color-tertiary-container)
  matching on-* tokens for text

Use Axismundi typography tokens or conservative independent values.
TT5 clamp values are evidence only.
TT5 accent-* hex values are blocked.
```

Phase 1 must verify the JSON shape required by WordPress 6.7 before Phase 2
creates these files.

---

## Non-Goals

v3.6.27 must not:

```txt
1. Copy TT5 hex palette values.
2. Adopt Manrope / Fira Code as Pilot fonts.
3. Copy TT5 pattern PHP body content.
4. Create a distributable skeleton.
5. Start Google Sites extraction.
6. Modify styleguide, bindings, lab, or runtime files.
7. Introduce Lock 1 / Lock 2 / Lock 3 / Lock 4 violations.
8. Treat TT5 as a full Axismundi core ontology.
9. Promote M16 or M17 in Phase 0.
10. Touch release artifacts or wp.org submission files.
```

---

## Phase 1 Diagnostic Prerequisites

Phase 1 must answer P1-P8 before implementation.

```txt
P1. Confirm current theme.json $schema URL, version, contentSize, and wideSize.
P2. Confirm style.css Version / Requires at least / Tested up to exact values.
P3. Inspect functions.php for pattern support, pattern categories, and slug policy.
P4. Inspect theme.json templateParts[] and confirm current part registration.
P5. Inspect theme.json customTemplates[] and current templates/ consistency.
P6. Inspect pilot-block-bridge.css/js for contentSize/wideSize assumptions.
P7. Capture current validation baseline for Axis A-G before implementation.
P8. Confirm styles/blocks/ and styles/sections/ absence or existing contents.
```

Phase 1 sequence:

```txt
1. Answer P1-P8 with file references.
2. Reconcile S1-S5 with current Pilot source-of-authority.
3. Decide whether S3 footer conversion and optional parts are in or route-forward.
4. Decide exact JSON schema for S5.
5. Produce Phase 1 report.
6. Trigger Reviewer review before Phase 2 implementation.
```

---

## Source-of-Authority Table

| Surface | Source authority | Tracked copy | v3.6.27 disposition |
|---|---|---|---|
| `theme.json` | Pilot repo direct source | none | S1 target |
| `style.css` | Pilot repo direct source | none | S1 header target |
| `templates/*.html` | Pilot repo direct source | none | S2 add targets |
| `parts/*.html` | Pilot repo direct source | none | S3 conversion/add target |
| `patterns/*.php` | Pilot repo direct source | none | S4 add/update target |
| `styles/blocks/*.json` | Pilot repo direct source | none | S5 add target |
| `styles/sections/*.json` | Pilot repo direct source | none | S5 add target |
| `assets/styles/pilot-block-bridge.css` | Pilot bridge source | bridge | Phase 1 impact check |
| TT5 local files | External comparator | no copy | Evidence only |
| v3.6.25 matrix | Evaluation frame | repo source | Governs route |

---

## Phase Structure

```txt
Phase 0:
  This plan.

Phase 1:
  Diagnostic inventory and implementation-readiness report.
  No implementation.

Phase 2:
  Implement in order:
    S1 layout/schema/header metadata
    S2 templates
    S3/S4 parts and patterns
    S5 block/section style JSON files

Phase 3:
  Verification and visual QA.

Phase 5:
  Close report and route-forward list.
```

Phase 2 is blocked until Reviewer review of Phase 1.

---

## Validation Plan

Phase 1 baseline:

```txt
git status --short --branch
npm test or repo-equivalent validator command
current Axis A-G snapshot
```

After S1:

```txt
validate computed/token baseline
confirm contentSize/wideSize do not break bridge assumptions
```

After S2-S4:

```txt
build Pilot specimen wall if available
validate specimen wall if available
php -l products/reference-implementations/axismundi-pilot/functions.php
```

After S5:

```txt
validate JSON syntax
verify WordPress 6.7 recognizes styles/blocks and styles/sections shape
confirm block style picker exposure when wp-env or equivalent is available
```

Final:

```txt
git diff --check
npm test
no TT5 hex/font/content imports
no styleguide/bindings/lab/runtime changes
```

---

## Risks

```txt
R1. Treating TT5 visual decisions as Axismundi design authority.
R2. Copying TT5 pattern content instead of using structural evidence.
R3. Introducing JSON style files that WordPress 6.7 does not discover.
R4. Converting parts to patterns without registering or discovering patterns.
R5. Widening layout dimensions and accidentally invalidating Pilot bridge QA.
R6. Adding unused templates or parts for parity rather than real Pilot coverage.
R7. Starting distributable bootstrap by implication.
R8. Promoting M16/M17 before Phase 1 supplies actual second-cycle evidence.
```

---

## Memory Watch

M16 remains WATCH in Phase 0.

Promote only if Phase 1/2 actually uses the 12 entities + 7 non-entities to
block over-modeling, especially treating TT5 as a full core ontology.

M17 remains WATCH in Phase 0.

Promote only if the layered Decision Matrix schema is reused for the TT5
corpus with clear practical value.

---

## Phase 0 Exit Criteria

```txt
[x] Phase 0 plan created at docs/v3.6.27/PILOT-TT5-TRANSPLANT-PHASE-0-PLAN.md
[x] Phase 0 plan committed.
[x] Phase 1 P1-P8 checklist acknowledged.
[x] Non-goals acknowledged, especially hex copy ban and Lock 1/2 boundaries.
```

Phase 1 may start after this file is committed. Phase 1 is diagnostic-only and
must trigger Reviewer review before Phase 2 implementation.
