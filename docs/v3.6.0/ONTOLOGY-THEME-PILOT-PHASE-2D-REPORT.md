# Axismundi v3.6.0 — Ontology Theme Pilot Phase 2D Report

Status: PASS
Date: 2026-05-18
Scope: Patterns / Block Styles

## 1. User Request Log

- Keep the Pilot theme core-block-only.
- Do not include Carousel; it remains plugin-routed.
- Treat `blocks.html` and `prose.html` as Pilot specification surfaces.
- Use the existing lab/module assets as read-only implementation references.
- Verify rendered WordPress output, including Korean prose, before closing the sub-cycle.

## 2. Implementation Summary

Phase 2D adds the Pilot's first WordPress authoring layer:

- `patterns/hero.php`
- `patterns/button-actions.php`
- `patterns/card-list.php`
- `patterns/search-section.php`
- `patterns/prose-sample.php`

The patterns use WordPress core blocks only. No custom block registration was added.

`functions.php` now registers:

- Pattern categories:
  - `axismundi-showcase`
  - `axismundi-composition`
  - `axismundi-prose`
- Block styles:
  - `core/button`: `filled`, `tonal`, `elevated`, `outlined`, `text`
  - `core/group`: `card-filled`, `card-elevated`, `card-outlined`
  - `core/list`: `list-segmented`
  - `core/separator`: `divider-inset`, `divider-middle-inset`
  - `core/search`: `filled-search`

`blocks.css` gained a minimal `core/search` `is-style-filled-search` mapping so the registered block style has an actual visual contract.

## 3. Runtime Registration Verification

Block style registry:

```txt
core/button:filled=yes
core/button:tonal=yes
core/button:elevated=yes
core/button:outlined=yes
core/button:text=yes
core/group:card-filled=yes
core/group:card-elevated=yes
core/group:card-outlined=yes
core/list:list-segmented=yes
core/separator:divider-inset=yes
core/separator:divider-middle-inset=yes
core/search:filled-search=yes
```

Pattern categories:

```txt
axismundi-showcase=yes
axismundi-composition=yes
axismundi-prose=yes
```

Theme pattern files are discoverable after refreshing the WordPress theme pattern cache:

```txt
axismundi-pilot/hero=yes
axismundi-pilot/button-actions=yes
axismundi-pilot/card-list=yes
axismundi-pilot/search-section=yes
axismundi-pilot/prose-sample=yes
```

Note: the first registry check incorrectly treated `get_all_registered()` array keys as slugs. The registry stores the slug in each pattern data object; the corrected check verifies the `slug` field.

## 4. Render Smoke

A database-only QA page was created in `wp-env`:

```txt
Title: Axismundi Pattern QA
URL:   http://localhost:8888/?page_id=10
```

The QA page combines all five Pilot patterns. The page is a runtime artifact only and is not committed.

Viewport smoke:

```txt
390px:  overflowX=0, CSS=7/7, hero=yes, list=yes, search=yes, Korean prose=yes, console errors=0
768px:  overflowX=0, CSS=7/7, hero=yes, list=yes, search=yes, Korean prose=yes, console errors=0
1280px: overflowX=0, CSS=7/7, hero=yes, list=yes, search=yes, Korean prose=yes, console errors=0
```

Button style classes observed:

```txt
is-style-filled
is-style-outlined
is-style-tonal
is-style-elevated
is-style-text
```

## 5. Verification

```txt
PHP lint:             PASS
Custom block check:   register_block_type() absent
Validator:            1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:             PASS
Pilot CSS mirror:     assets/styles/blocks.css regenerated
```

## 6. Acceptance Criteria

- Core blocks only: PASS
- Pattern category registration: PASS
- Block style registration: PASS
- Search block visual style has CSS backing: PASS
- Korean prose pattern included: PASS
- 390 / 768 / 1280 render smoke: PASS
- Console errors: PASS
- Carousel excluded: PASS
- Existing `ontology-theme-pilot/` untouched: PASS

## 7. Next Step

Proceed to Phase 3 Pilot acceptance QA:

- Activate the theme in `wp-env`.
- Inspect the front end, pattern QA page, single post, page, blocks/prose surfaces.
- Confirm 8 Wave 1 components minus Carousel remain visually coherent in WordPress context.
- Confirm no custom-block expectation leaked into the theme.
