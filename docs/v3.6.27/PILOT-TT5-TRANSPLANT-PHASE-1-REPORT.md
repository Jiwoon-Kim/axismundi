# v3.6.27 Phase 1 Report - Pilot TT5 Structural Transplant

**Status:** Phase 1 diagnostic complete; awaiting Reviewer review before Phase 2.
**Cycle:** v3.6.27 / 17th overall / 12th impl-cycle candidate
**Date:** 2026-05-25
**Phase 0:** `docs/v3.6.27/PILOT-TT5-TRANSPLANT-PHASE-0-PLAN.md`

---

## Verdict

APPROVE Phase 2 only after Reviewer review.

Recommended Phase 2 route: **S1 + S2 + S3/S4 narrowed + S5 cautious add**.

```txt
S1: update Pilot compatibility metadata and layout sizes.
S2: add the five missing templates.
S3/S4: convert header to pattern-driven part; keep footer conversion optional
       unless Reviewer approves it.
S5: add JSON style variation files only after S1 schema update, and do not
    replace existing PHP-registered block styles in this cycle.
```

No implementation happened in Phase 1.

---

## Read-Only Diagnostic Lock

Phase 1 inspected source files and ran validators. The only intended new file
is this report.

Validation commands were run to capture the required baseline:

```txt
npm test                         PASS, Axis A-G all 1.000
npm run validate:computed        PASS
npm run validate:specimen-wall   PASS
```

`npm test` regenerated tracked validation reports as a side effect. Those
generated diffs are not Phase 1 deliverables and should not be committed with
this report.

---

## P1-P8 Diagnostic

### P1. theme.json schema, version, and layout

Current Pilot:

```txt
products/reference-implementations/axismundi-pilot/theme.json

line 2:  $schema = https://schemas.wp.org/trunk/theme.json
line 3:  version = 3
line 9:  contentSize = 640px
line 10: wideSize = 1280px
```

Phase 2 S1 may update:

```txt
$schema -> https://schemas.wp.org/wp/6.7/theme.json
contentSize -> 645px
wideSize -> 1340px
```

Do not change palette values or font families.

### P2. style.css version and compatibility header

Current Pilot:

```txt
products/reference-implementations/axismundi-pilot/style.css

line 7:  Version: 0.1.0-pilot
line 8:  Requires at least: 6.5
line 9:  Tested up to: 6.9
line 10: Requires PHP: 8.1
```

Phase 2 S1 may update:

```txt
Version -> 0.2.0-pilot
Requires at least -> 6.7
Tested up to -> 7.0
```

PHP requirement stays unchanged unless Reviewer asks otherwise.

### P3. functions.php pattern and style registration

Current Pilot:

```txt
products/reference-implementations/axismundi-pilot/functions.php

line 14:  AXISMUNDI_PILOT_VERSION = 0.1.0-pilot
lines 270-306: PHP register_block_style() for current Pilot styles
lines 311-325: register_block_pattern_category() for Pilot categories
```

Existing PHP block styles:

```txt
core/button: tonal, elevated, text
core/group: card-filled, card-elevated, card-outlined
core/list: list-segmented
core/separator: divider-inset, divider-middle-inset
core/search: filled-search
```

Existing pattern categories:

```txt
axismundi-showcase
axismundi-composition
axismundi-prose
```

There is no manual `register_block_pattern()` call. Current Pilot patterns use
WordPress pattern file discovery through `patterns/*.php` metadata.

Phase 2 must update `AXISMUNDI_PILOT_VERSION` with `style.css` if S1 changes
the version.

### P4. theme.json templateParts[]

Current Pilot:

```txt
products/reference-implementations/axismundi-pilot/theme.json

lines 404-415:
  header / Header / area header
  footer / Footer / area footer
```

There are no registered sidebar or large-title parts yet.

Recommendation:

```txt
Keep templateParts[] to header/footer in Phase 2 unless a new part is actually
consumed by a template.

Do not add TT5 parity parts just to mirror TT5.
```

### P5. customTemplates[] and template consistency

Current Pilot:

```txt
theme.json customTemplates[]: absent

templates/:
  index.html
  page.html
  single.html
```

Phase 2 S2 may add:

```txt
404.html
archive.html
home.html
page-no-title.html
search.html
```

Recommendation:

```txt
Do not add customTemplates[] for these default hierarchy templates. They are
standard block theme templates and should be discovered from templates/.
```

### P6. bridge layout impact

Checked files:

```txt
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

Findings:

```txt
No literal 640px / 1280px / 645px / 1340px assumptions found.
CSS is scoped around .wp-block-post-content, block selectors, and MD3 tokens.
JS is scoped to .wp-block-button__link ripple enhancement and theme switcher.
```

Risk:

```txt
Layout size changes can still affect visual wrapping and specimen screenshots,
but no direct bridge constant requires code migration.
```

### P7. validation baseline

Captured before implementation:

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
```

Phase 2 must preserve this baseline or document any intentional validator
contract change.

### P8. styles/blocks and styles/sections

Current Pilot:

```txt
products/reference-implementations/axismundi-pilot/styles/
  absent
```

TT5 comparator:

```txt
twentytwentyfive/styles/blocks/
  01-display.json
  02-subtitle.json
  03-annotation.json
  post-terms-1.json

twentytwentyfive/styles/sections/
  section-1.json through section-5.json
```

The TT5 files use `"$schema": "https://schemas.wp.org/wp/6.7/theme.json"`,
`"version": 3`, `"slug"`, `"blockTypes"`, and `"styles"`.

Official WordPress support note:

```txt
WordPress 6.6 introduced block style variation theme.json partials with a
blockTypes array, including section-style use cases for Group / Columns /
Column. TT5 uses that mechanism in a WP 6.7 theme.
```

Primary source: WordPress Developer Blog, "Styling sections, nested elements,
and more with Block Style Variations in WordPress 6.6."
https://developer.wordpress.org/news/2024/06/styling-sections-nested-elements-and-more-with-block-style-variations-in-wordpress-6-6/

---

## S1-S5 Phase 2 Disposition

### S1 disposition

APPROVE for Phase 2.

Required exact edits:

```txt
theme.json:
  schema -> https://schemas.wp.org/wp/6.7/theme.json
  contentSize -> 645px
  wideSize -> 1340px

style.css:
  Version -> 0.2.0-pilot
  Requires at least -> 6.7
  Tested up to -> 7.0

functions.php:
  AXISMUNDI_PILOT_VERSION -> 0.2.0-pilot
```

### S2 disposition

APPROVE for Phase 2.

Add the five missing default hierarchy templates:

```txt
404.html
archive.html
home.html
page-no-title.html
search.html
```

Use existing Axismundi Pilot markup vocabulary. Do not copy TT5 template body
content.

### S3/S4 disposition

APPROVE narrowed header conversion.

Recommended:

```txt
1. Move current parts/header.html markup into patterns/header.php.
2. Replace parts/header.html with a pattern reference:
   <!-- wp:pattern {"slug":"axismundi-pilot/header"} /-->
3. Keep the existing header metadata compatible with template-part/header.
```

Footer:

```txt
Route-forward unless Reviewer approves conversion. Existing footer.html is
small, direct, and already registered as a template part.
```

Optional parts:

```txt
Do not add header-large-title.html or sidebar.html in Phase 2 unless a newly
added template consumes them.
```

### S5 disposition

APPROVE cautious add after S1.

Important ordering:

```txt
S1 schema update must happen before S5, because the JSON partials use the
WP 6.7 theme.json schema and blockTypes style-variation mechanism.
```

Recommended JSON additions:

```txt
styles/blocks/01-display.json
styles/blocks/02-subtitle.json
styles/blocks/03-annotation.json
styles/sections/section-1.json
styles/sections/section-2.json
styles/sections/section-3.json
styles/sections/section-4.json
styles/sections/section-5.json
```

Do not replace existing PHP-registered block styles in this cycle. The current
PHP styles are active Pilot contract. S5 should add new text/section styles
only, using MD3 tokens and independent values.

Blocked imports:

```txt
TT5 hex values
TT5 accent-* palette mapping
TT5 Manrope / Fira Code fonts
TT5 clamp values copied as-is
TT5 pattern body content
```

---

## Route Decision

Recommended route: **Phase 2 multi-surface implementation, narrowed**.

```txt
Execute:
  S1
  S2
  S3 header conversion + S4 header pattern
  S5 text/section JSON additions

Defer:
  footer pattern conversion
  header-large-title part
  sidebar part
  replacing PHP register_block_style() styles with JSON style variations
  customTemplates[] additions
```

Reason:

```txt
The Pilot has enough current structure to absorb TT5's structural evidence,
but S3/S4 and S5 both intersect existing Pilot mechanisms. Narrowing prevents
the cycle from becoming a full block-theme architecture rewrite.
```

---

## Memory Watch

M16 remains WATCH.

```txt
The diagnostic did not need to promote TT5 into core ontology. It kept TT5 as
external comparator evidence, so the M16 over-modeling trigger did not fire.
```

M17 remains WATCH, with additional evidence.

```txt
The layered Decision Matrix pattern was useful for separating TT5 behavior,
local schema evidence, and Pilot implementation routes. However, promotion is
better considered after Phase 2 verifies that the schema guided implementation
without drift.
```

---

## Reviewer Questions

```txt
R1. Approve S1 exact version/schema/layout edits?
R2. Approve S2 five-template expansion with no customTemplates[] addition?
R3. Approve header-only S3/S4 conversion and footer route-forward?
R4. Approve S5 as additive JSON style variations while preserving PHP block
    style registrations?
R5. Require browser/site-editor confirmation for S5 in Phase 3, or accept
    static JSON/schema + validator checks if wp-env is unavailable?
```

---

## Phase 2 Entry Gate

Phase 2 may start only after Reviewer approves this report.

Expected Phase 2 implementation order:

```txt
1. S1 metadata/layout/schema/version.
2. Run npm test.
3. S2 templates.
4. S3/S4 header pattern conversion.
5. S5 additive JSON style variation files.
6. Run validation set.
```

No distributable files, Google Sites extraction, styleguide edits, bindings
edits, lab edits, or runtime edits are authorized by this Phase 1 report.
