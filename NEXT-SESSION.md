# NEXT-SESSION.md - Post-v3.6.4 Handoff

> **Status**: v3.6.0 Ontology Theme Pilot, v3.6.1 Token Architecture
> Refactor, v3.6.2 WP Core Block Specimen Wall, v3.6.3 WP Block Bridge
> Expansion, and v3.6.4 WP Block Bridge Residual Cleanup are closed.
> **Use**: read at the start of the next Codex/Claude session.
> **Last updated**: 2026-05-21.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #44 / #21 / #14
7. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
8. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
9. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
10. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
11. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
12. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
13. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
14. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
15. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
16. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
17. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
18. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
19. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
20. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
21. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
22. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
```

Repo docs remain authority. Chat is relay, not source of truth.

Default relay ownership:

```txt
Codex:
  implementation files and phase plan/report docs

Opus/Claude:
  review findings only, preferably as user-relayed text or
  docs/<cycle>/*-review.md if repo-based handoff is requested
```

## 1) Current State

```txt
v3.5.18  Pre-Pilot cleanup + Carousel reroute       CLOSED
v3.6.0   Ontology Theme Pilot v0                    CLOSED
v3.6.1   Token Architecture Refactor                CLOSED
v3.6.2   WP Core Block Specimen Wall                CLOSED
v3.6.3   WP Block Bridge Expansion                  CLOSED
v3.6.4   WP Block Bridge Residual Cleanup           CLOSED

Next route:
  Start next cycle plan-first.
  Primary candidates:
    BACKLOG #41 ripple/editor parity follow-on.
    BACKLOG #44 specimen follow-on coverage + editor compatibility.
  Alternative candidates:
    Wave 2 plan-first.
    BACKLOG #21 Interpreter Plugin strategy.
```

Public repository:

```txt
https://github.com/Jiwoon-Kim/axismundi
https://jiwoon-kim.github.io/axismundi/
```

Local workspace:

```txt
C:\Users\thaum\dev\axismundi
```

## 2) v3.6.4 Close Summary

Closed by v3.6.4:

```txt
Phase 1 button mechanical cleanup:
  core/button href route preserved
  post-content underline leakage removed
  user-select disabled
  focus/hover/pressed state behavior preserved

Phase 2 quote/pullquote cleanup:
  quote selectors narrowed
  pullquote distinct bridge surface added
  pullquote inner blockquote no longer absorbs quote padding/bar styling
  pullquote cite no longer absorbs quote prefix

Phase 3 visual QA:
  light/dark button + quote/pullquote sweep
  editor canvas smoke
  front-end drag console smoke
```

Validation at close:

```txt
python tools/generators/build_pilot_specimen_wall.py PASS
npm run validate:specimen-wall                       PASS
php -l products/reference-implementations/axismundi-pilot/functions.php PASS
npm test                                             PASS
npm run validate:computed                            PASS
git diff --check                                     PASS
```

Routed forward:

```txt
BACKLOG #41:
  ripple bridge graduation
  editor-canvas parity questions from original #41
  editor md-sys color token enqueue parity surfaced by v3.6.4

BACKLOG #44:
  editor-invalid-content
  mark/highlight coverage
  Material Symbols font constraint
  editor compatibility follow-on
```

## 3) Lesson Locks

These are now close-time rules, not suggestions:

```txt
Lock 1 - wp-custom downstream-only

Every settings.custom.axismundi.* entry MUST be defined as:
  var(--comp-*) or var(--md-sys-*) or var(--md-ref-*)

Literal hex / rgb / px / number values are forbidden in this namespace.
Rationale: wp-custom is a downstream projection of M3, never a source.
Validator: tools/validators/validate_theme_pilot.py Axis G.
```

```txt
Lock 2 - md-sys color maps to md-ref

Every --md-sys-color-* entry MUST be defined as:
  var(--md-ref-palette-*)

Literal hex / rgb / hsl values are forbidden in the md-sys color layer.
Rationale: md-sys is the runtime semantic layer; md-ref is the primitive source.
Dark mode swaps sys -> ref mappings only.
Validator: tools/validators/validate_theme_pilot.py Axis E.
```

```txt
Lock 3 - core/button semantic route before visual cleanup

Before accepting visual cleanup for core/button link affordances, name the
semantic route. A core/button anchor with href is navigation and may receive an
M3 button visual bridge. A real action, form behavior, AJAX flow, federation
action, or durable custom schema must be routed to plugin/custom-block
territory, not implemented in the theme bridge.
```

```txt
Lock 4 - semantic mismatch handling rule

When a WordPress core block visually maps to M3 but carries divergent markup,
interaction, or accessibility semantics, route the mismatch as either
theme-owned semantic-decision or plugin/custom-block territory before
accepting a visual fix. Do not silently ignore the mismatch and do not collapse
distinct core block structures into one generic CSS patch.
```

## 4) Resume Checklist

Start by running:

```powershell
cd C:\Users\thaum\dev\axismundi
git status --short
wp-env start
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
```

Then open/check relevant Pilot/styleguide surfaces for the next cycle. For
Pilot feedback work, include:

```txt
http://localhost:8888/
http://localhost:8888/?page_id=10
http://localhost:8888/?p=1
http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
file:///C:/Users/thaum/dev/axismundi/styleguide/blocks.html
```

## 5) Next Action

Choose the next cycle. Do not auto-start implementation without a Phase 0 plan.

Recommended primary routes:

```txt
BACKLOG #41 ripple/editor parity follow-on:
  decide whether the Pilot ripple bridge graduates or remains Pilot-only
  verify editor-canvas parity for hover/focus/pressed/disabled/selected states
  resolve editor md-sys color token enqueue parity

BACKLOG #44 specimen follow-on coverage + editor compatibility:
  editor-invalid-content
  mark/highlight coverage
  Material Symbols font constraint
  deeper pullquote coverage if needed
```

Alternative routes:

```txt
Wave 2 plan-first
BACKLOG #21 Interpreter Plugin strategy
```
