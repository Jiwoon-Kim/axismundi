# v3.6.27 Phase 2 Implementation - Pilot TT5 Structural Transplant

**Status:** Phase 2 implementation complete; awaiting Reviewer review before Phase 3.
**Cycle:** v3.6.27 / 17th overall / 12th impl-cycle candidate
**Date:** 2026-05-25
**Phase 1:** `docs/v3.6.27/PILOT-TT5-TRANSPLANT-PHASE-1-REPORT.md`

---

## Verdict

Implemented the Reviewer-approved narrowed route:

```txt
S1: theme.json / style.css / functions.php compatibility update
S2: five default hierarchy templates added
S3/S4: header-only pattern-driven conversion
S5: additive JSON block/section style variations
```

No distributable files, Google Sites extraction, styleguide edits, bindings
edits, lab edits, runtime edits, TT5 content imports, TT5 font imports, or TT5
hex palette imports were made.

---

## S1 - Compatibility And Layout

Updated:

```txt
products/reference-implementations/axismundi-pilot/theme.json
  $schema:
    https://schemas.wp.org/trunk/theme.json
    -> https://schemas.wp.org/wp/6.7/theme.json

  settings.layout.contentSize:
    640px -> 645px

  settings.layout.wideSize:
    1280px -> 1340px

products/reference-implementations/axismundi-pilot/style.css
  Version:
    0.1.0-pilot -> 0.2.0-pilot

  Requires at least:
    6.5 -> 6.7

  Tested up to:
    6.9 -> 7.0

products/reference-implementations/axismundi-pilot/functions.php
  AXISMUNDI_PILOT_VERSION:
    0.1.0-pilot -> 0.2.0-pilot
```

Validation after S1:

```txt
npm test
  PASS, Axis A-G all 1.000
```

---

## S2 - Template Expansion

Added five default hierarchy templates:

```txt
products/reference-implementations/axismundi-pilot/templates/404.html
products/reference-implementations/axismundi-pilot/templates/archive.html
products/reference-implementations/axismundi-pilot/templates/home.html
products/reference-implementations/axismundi-pilot/templates/page-no-title.html
products/reference-implementations/axismundi-pilot/templates/search.html
```

Template count is now 8:

```txt
404.html
archive.html
home.html
index.html
page-no-title.html
page.html
search.html
single.html
```

Reviewer note applied:

```txt
home.html is the blog home template, not a static front-page clone.
It now has a short blog-home heading/paragraph and preserves a filled button
CTA so the existing computed front-page validator gate remains valid.
```

No `customTemplates[]` entry was added.

---

## S3/S4 - Header Pattern Conversion

Added:

```txt
products/reference-implementations/axismundi-pilot/patterns/header.php
```

Pattern metadata:

```txt
Title: Axismundi Pilot header
Slug: axismundi-pilot/header
Categories: header
Block Types: core/template-part/header
```

Updated:

```txt
products/reference-implementations/axismundi-pilot/parts/header.html
```

The header part now delegates to the pattern:

```html
<!-- wp:pattern {"slug":"axismundi-pilot/header"} /-->
```

Footer remained unchanged and route-forward, per Reviewer approval.

No `header-large-title.html` or `sidebar.html` part was added.

---

## S5 - Additive JSON Style Variations

Added block text style variation files:

```txt
products/reference-implementations/axismundi-pilot/styles/blocks/01-display.json
products/reference-implementations/axismundi-pilot/styles/blocks/02-subtitle.json
products/reference-implementations/axismundi-pilot/styles/blocks/03-annotation.json
```

Added section style variation files:

```txt
products/reference-implementations/axismundi-pilot/styles/sections/section-1.json
products/reference-implementations/axismundi-pilot/styles/sections/section-2.json
products/reference-implementations/axismundi-pilot/styles/sections/section-3.json
products/reference-implementations/axismundi-pilot/styles/sections/section-4.json
products/reference-implementations/axismundi-pilot/styles/sections/section-5.json
```

Existing PHP `register_block_style()` styles were preserved:

```txt
core/button: tonal, elevated, text
core/group: card-filled, card-elevated, card-outlined
core/list: list-segmented
core/separator: divider-inset, divider-middle-inset
core/search: filled-search
```

Section token mapping:

```txt
section-1:
  background: var:preset|color|surface-variant
  text:       var:preset|color|on-surface-variant

section-2:
  background: var:preset|color|primary-container
  text:       var:preset|color|on-primary-container

section-3:
  background: var:preset|color|secondary-container
  text:       var:preset|color|on-secondary-container

section-4:
  background: var:preset|color|tertiary-container
  text:       var:preset|color|on-tertiary-container

section-5:
  background: var(--md-sys-color-inverse-surface)
  text:       var(--md-sys-color-inverse-on-surface)
```

Reason for section-5 exception:

```txt
Pilot theme.json does not register inverse-surface / inverse-on-surface palette
slugs. Using invented `var:preset|color|inverse-*` references would violate the
Reviewer instruction to use only actual registered palette slugs. The file uses
the existing MD3 sys CSS variables directly instead.
```

---

## Validation

Commands run after implementation:

```txt
npm test
  PASS
  Overall: 1.000
  Axis A schema: 1.000
  Axis B theme: 1.000
  Axis C css: 1.000
  Axis D runtime: 1.000
  Axis E tokens: 1.000
  Axis F bridge: 1.000
  Axis G custom: 1.000

npm run validate:computed
  PASS

npm run validate:specimen-wall
  PASS

php -l products/reference-implementations/axismundi-pilot/functions.php
  PASS

ConvertFrom-Json over all new styles/**/*.json
  PASS

git diff --check
  PASS
```

Import guard:

```txt
rg "#(?:[0-9A-Fa-f]{3}){1,2}|Manrope|Fira Code|twentytwentyfive|accent-[1-6]"
  over changed Pilot theme/template/part/pattern/style surfaces
  -> no matches
```

Note:

```txt
npm test regenerates tracked validation reports as a side effect. Those
generated diffs were restored and are not committed in Phase 2.
```

---

## Implementation Notes

### Computed validator correction

After adding `home.html`, WordPress used it for `/`, which removed the filled
button expected by the existing front-page computed validator. The template was
kept distinct from `index.html` as a blog home, but a small filled-button CTA
was added so the existing front-page gate remains meaningful.

### S5 site-editor confirmation

Site Editor / wp-env confirmation has not yet been run in Phase 2. Per Reviewer
R5, Phase 3 should prefer live Site Editor confirmation. If unavailable, Phase 3
may use the approved fallback:

```txt
static JSON schema validation
npm test
npm run validate:computed
npm run validate:specimen-wall
git diff --check
explicit "wp-env not confirmed" note
```

---

## Route-Forward

```txt
footer pattern conversion
header-large-title part
sidebar part
replacing PHP register_block_style() styles with JSON style variations
customTemplates[] additions
Google Sites extraction
distributable skeleton
site-editor live confirmation if not completed in Phase 3
```

---

## Reviewer Questions Before Phase 3

```txt
R1. Accept section-5 direct MD3 sys variable mapping because inverse palette
    slugs are not registered?
R2. Accept home.html filled-button CTA as the correct way to preserve the
    existing computed validator gate after home.html became the front template?
R3. Require wp-env Site Editor verification in Phase 3, or allow fallback if
    local wp-env is unavailable?
```
