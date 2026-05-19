# Axismundi v3.6.0 — Ontology Theme Pilot Phase 5 Close

Status: CLOSED
Date: 2026-05-19
Scope: Mechanical close for the v3.6.0 WordPress block theme Pilot

## 1. Close Verdict

v3.6.0 closes as:

```txt
Pilot v0 — scaffold + Wave 1 reverse mapping + block bridge MVP
```

This is a successful Pilot close, not a final theme release. The cycle proved
that Axismundi can consume its lab/styleguide assets inside a real WordPress
block theme while preserving the no-custom-block rule.

## 2. Completed Surface

- Created `products/reference-implementations/axismundi-pilot/`.
- Activated the Pilot in `wp-env`.
- Built scaffold, templates, template parts, patterns, and block styles.
- Added a self-contained asset bridge for CSS, fonts, icons, and Pilot bridge
  assets.
- Registered five content font families in the WordPress Font Library.
- Kept Material Symbols as a chrome-only icon font.
- Added a Pilot-specific block bridge for WordPress core block output.
- Verified Korean prose rendering.

## 3. Acceptance Summary

```txt
Validator:             1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:              PASS
npm run validate:computed: PASS
functions.php lint:    PASS
wp-env smoke:          PASS
User visual QA:        PASS after Phase 3 follow-up
```

Phase 3 visual QA found several WordPress core default leaks. These were fixed
in-cycle and absorbed into the computed-style validator:

- native Button `fill` / `outline` mapping;
- Outlined Button color and border tokens;
- Table `thead` and Stripes core defaults;
- Search button core background;
- Code / Preformatted default borders;
- Separator gray border.

## 4. Lessons Locked

The Pilot changed the integration framing:

```txt
v3.5.x design-system construction:
  M3 spec -> Axismundi component -> public styleguide

v3.6.0 theme integration:
  Markdown / HTML defaults -> WordPress core block -> reset -> bridge -> M3 mapping
```

Phase 5 applies the lesson lock to:

```txt
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
```

The key verification rule is:

```txt
Source selector exists      != proof
Generated asset contains it != proof
Rendered computed value     == proof
```

## 5. Routed Work

- BACKLOG #20: partially validated; final close deferred to v3.6.1 token
  architecture refactor.
- BACKLOG #21: Interpreter Plugin owns the reverse/customizable direction.
- BACKLOG #41: full WordPress block bridge state and ripple enhancement.
- BACKLOG #42: Token Architecture Refactor.
- BACKLOG #43: WP core block specimen wall / full variation audit.

## 6. Next Cycle

v3.6.1 should enter as **Token Architecture Refactor**:

```txt
md-ref -> md-sys.light / md-sys.dark -> wp-preset.bridge / wp-custom.bridge -> ax-comp
```

It is cross-cutting across `axismundi-lab` and `axismundi-pilot`, and must begin
with a User Request Log quoting `PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md`
directly.
