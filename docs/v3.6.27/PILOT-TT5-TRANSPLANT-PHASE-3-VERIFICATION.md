# v3.6.27 Phase 3 Verification - Pilot TT5 Structural Transplant

**Status:** Phase 3 verification complete; awaiting Reviewer review before Phase 5.
**Cycle:** v3.6.27 / 17th overall / 12th impl-cycle candidate
**Date:** 2026-05-25
**Phase 2:** `docs/v3.6.27/PILOT-TT5-TRANSPLANT-PHASE-2-IMPLEMENTATION.md`

---

## Verdict

PASS.

The v3.6.27 Pilot TT5 structural transplant preserves the validation baseline,
keeps the Pilot theme active in wp-env, exposes the new JSON block/section style
variations through WordPress 7.0 theme JSON resolution, and passes front-end
route smoke checks.

---

## Required Validation

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

ConvertFrom-Json over all products/reference-implementations/axismundi-pilot/styles/**/*.json
  PASS

git diff --check HEAD
  PASS
```

`npm test` regenerated tracked validation report files as a side effect. Those
generated diffs were restored and are not part of Phase 3.

---

## wp-env Verification

wp-env started successfully.

Actual WordPress version:

```txt
wp core version
  7.0

get_bloginfo("version")
  7.0
```

Note:

```txt
.wp-env.json currently names WordPress/WordPress#6.9.4, but the running
container reports WordPress 7.0. Phase 3 records the actual runtime value.
```

Active theme:

```txt
wp theme list --status=active
  axismundi-pilot active 0.2.0-pilot

wp_get_theme()
  Axismundi Pilot|0.2.0-pilot
```

---

## JSON Style Variation Recognition

Theme JSON file discovery found all new files:

```txt
styles/blocks/01-display.json
styles/blocks/02-subtitle.json
styles/blocks/03-annotation.json
styles/sections/section-1.json
styles/sections/section-2.json
styles/sections/section-3.json
styles/sections/section-4.json
styles/sections/section-5.json
theme.json
```

`WP_Theme_JSON_Resolver::get_style_variations("block")` returned 8 entries:

```txt
text-display:
  title: Display
  blockTypes: core/heading, core/paragraph

text-subtitle:
  title: Subtitle
  blockTypes: core/heading, core/paragraph

text-annotation:
  title: Annotation
  blockTypes: core/heading, core/paragraph

section-1:
  title: Section 1
  blockTypes: core/group, core/columns, core/column

section-2:
  title: Section 2
  blockTypes: core/group, core/columns, core/column

section-3:
  title: Section 3
  blockTypes: core/group, core/columns, core/column

section-4:
  title: Section 4
  blockTypes: core/group, core/columns, core/column

section-5:
  title: Section 5
  blockTypes: core/group, core/columns, core/column
```

This is stronger than static JSON parsing: WordPress 7.0 resolved the files as
block-scoped style variations.

---

## Site Editor Check

Site Editor loaded under wp-env:

```txt
URL: http://localhost:8888/wp-admin/site-editor.php
Title: Blog Home ‹ Template ‹ Axismundi ‹ Editor — WordPress
Body text included:
  Design
  Styles
  Navigation
  Pages
  Templates
  Patterns
  Blog Home ‹ Template
```

Initial Site Editor screen did not expose the style picker labels directly in
top-level text. Instead of claiming UI picker confirmation, Phase 3 uses the
WordPress 7.0 resolver evidence above:

```txt
WP_Theme_JSON_Resolver::get_style_variations("block") -> 8 expected entries.
```

Verdict:

```txt
Site Editor loads.
Server-side style variation recognition PASS.
Manual picker click-through not claimed.
```

---

## Front-End Route Smoke

Playwright smoke against wp-env:

```txt
/                                  -> 200, h1 "Latest posts", filled button 1, overflowX 0
/?s=design                         -> 200, h1 "Search results for: “design”", overflowX 0
/?p=1                              -> 200, h1 "Axismundi Prose QA", overflowX 0
/?author=1                         -> 200, h1 "admin", overflowX 0
/?pagename=definitely-missing      -> 404, h1 "Page not found", overflowX 0
```

This verifies the new `home.html`, `search.html`, archive route, single route,
and `404.html` path at the HTTP/render level.

---

## Import Guard

Phase 2 import guard remains valid:

```txt
No TT5 hex palette import.
No Manrope / Fira Code import.
No twentytwentyfive namespace import.
No TT5 accent-* palette import.
No TT5 pattern body import.
```

---

## Section-5 Decision

Reviewer accepted section-5 direct MD3 sys variables because
`inverse-surface` / `inverse-on-surface` are not registered Pilot palette slugs.

Current section-5:

```txt
background: var(--md-sys-color-inverse-surface)
text:       var(--md-sys-color-inverse-on-surface)
```

Route-forward:

```txt
If Pilot later registers inverse-surface / inverse-on-surface palette slugs,
section-5 can be normalized to var:preset|color|inverse-*.
Do not add those palette slugs in v3.6.27.
```

---

## Phase 3 Limits

```txt
Manual Site Editor picker click-through was not claimed.
No screenshot artifact was committed.
wp-env runtime is WordPress 7.0, not exactly WordPress 6.7.
```

These are not blockers because:

```txt
WordPress 7.0 is later than the required 6.7 floor.
Server-side resolver recognized the JSON block style variations.
All required validators passed.
Front-end route smoke passed.
```

---

## Recommendation

Proceed to Phase 5 close after Reviewer review.

Do not reorder `theme.json` or `functions.php` in this cycle. Any readability
hygiene should be a later scoped documentation/code-hygiene cycle, not part of
v3.6.27 verification.
