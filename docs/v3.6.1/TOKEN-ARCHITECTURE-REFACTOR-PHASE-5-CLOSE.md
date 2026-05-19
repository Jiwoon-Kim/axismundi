# v3.6.1 — Token Architecture Refactor — Phase 5 Close

Date: 2026-05-20

## Verdict

v3.6.1 is closed.

The cycle implemented the strict M3 token architecture across the lab,
published styleguide mirror, and Pilot theme, then locked the architecture in
validators and close-time docs.

## Closed Scope

```txt
1. Token layers split:
   md-ref / md-sys.light / md-sys.dark / comp / empty tokens.css shim

2. WordPress projection layers added:
   wp-preset.bridge.css
   wp-custom.bridge.css

3. theme.json settings.custom.axismundi.* added:
   26 downstream-only var() leaves

4. Dark mode infrastructure added:
   data-theme controls + sys-layer remapping only

5. Cross-surface sync:
   axismundi-lab
   axismundi-pilot assets
   published styleguide mirror

6. Validation expanded:
   Axis E md-sys -> md-ref
   Axis F bridge downstream refs
   Axis G theme.json custom downstream refs
   computed light/dark matrix + real click path

7. BACKLOG #20 closed
8. BACKLOG #42 closed
```

## Lesson Locks Applied

Applied to:

```txt
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
NEXT-SESSION.md
```

Lock 1:

```txt
Every settings.custom.axismundi.* entry MUST be defined as:
  var(--comp-*) or var(--md-sys-*) or var(--md-ref-*)

Literal hex / rgb / px / number values are forbidden in this namespace.
Rationale: wp-custom is a downstream projection of M3, never a source.
Validator: tools/validators/validate_theme_pilot.py Axis G.
```

Lock 2:

```txt
Every --md-sys-color-* entry MUST be defined as:
  var(--md-ref-palette-*)

Literal hex / rgb / hsl values are forbidden in the md-sys color layer.
Rationale: md-sys is the runtime semantic layer; md-ref is the primitive source.
Dark mode swaps sys -> ref mappings only.
Validator: tools/validators/validate_theme_pilot.py Axis E.
```

## Phase 3 Routed Findings

Phase 3 visual QA passed with two routed P3 findings:

```txt
1. .wp-block-table tfoot native border strength
2. core/button semantic boundary:
   styleguide <button> specimens vs WordPress link-based core/button markup
```

These do not block v3.6.1. They are routed to BACKLOG #43 and, if the fix
belongs in the broader bridge layer, BACKLOG #41.

## Docs Updated

```txt
CHANGELOG.md
ROADMAP.md
BACKLOG.md
CURRENT-STATE.md
NEXT-SESSION.md
AGENTS.md
CLAUDE.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
```

## Validation

Final validation for this close:

```txt
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
npm run validate:computed
git diff --check
```

All commands passed at close.

## Next Route

The next cycle is intentionally not auto-started. Candidate routes:

```txt
BACKLOG #43 — WP core block specimen wall / full variation audit
BACKLOG #41 — full block bridge expansion
Wave 2 plan-first
BACKLOG #21 — Interpreter Plugin strategy
```
