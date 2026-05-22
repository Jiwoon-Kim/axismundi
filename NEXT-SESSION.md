# NEXT-SESSION.md - Post-v3.6.11 Handoff

> **Status**: v3.6.0 Ontology Theme Pilot, v3.6.1 Token Architecture
> Refactor, v3.6.2 WP Core Block Specimen Wall, v3.6.3 WP Block Bridge
> Expansion, v3.6.4 WP Block Bridge Residual Cleanup, v3.6.5 WP Block
> Bridge Editor Token Parity, v3.6.6 WP Block Bridge Ripple / Editor State
> Parity, v3.6.7 WP Specimen Follow-On Editor Compatibility, v3.6.8 Wave 2A
> Navigation Core, v3.6.9 Wave 2A-2 Menu / Popover Consumer, v3.6.10
> Wave 2B-1 Form Controls, and v3.6.11 Wave 2B-2 Dialog / Sheet are closed.
> **Use**: read at the start of the next Codex/Claude session.
> **Last updated**: 2026-05-22.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #44 / #46 / #47 / #21 / #14
7. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-5-CLOSE.md
8. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-3-VISUAL-QA.md
9. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-2-REPORT.md
10. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-1-REPORT.md
11. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-0-PLAN.md
12. docs/v3.6.10/WAVE-2B-FORM-PHASE-5-CLOSE.md
13. docs/v3.6.10/WAVE-2B-FORM-PHASE-3-VISUAL-QA.md
14. docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
15. docs/v3.6.10/WAVE-2B-FORM-PHASE-1-REPORT.md
16. docs/v3.6.10/WAVE-2B-FORM-PHASE-0-PLAN.md
17. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-5-CLOSE.md
18. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-3-VISUAL-QA.md
19. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-2-REPORT.md
20. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-1-REPORT.md
21. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-0-PLAN.md
22. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
23. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
24. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
25. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
26. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-0-PLAN.md
27. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
28. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
29. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
30. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
31. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
32. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
33. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
34. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
35. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
36. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
37. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
38. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
39. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
40. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
41. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
42. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
43. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
44. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
45. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
46. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
47. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
48. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
49. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
50. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
51. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
52. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
53. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
54. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
55. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
56. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
57. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
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
v3.6.5   WP Block Bridge Editor Token Parity        CLOSED
v3.6.6   WP Block Bridge Ripple / Editor State Parity CLOSED
v3.6.7   WP Specimen Follow-On Editor Compatibility CLOSED
v3.6.8   Wave 2A Navigation Core                    CLOSED
v3.6.9   Wave 2A-2 Menu / Popover Consumer          CLOSED
v3.6.10  Wave 2B-1 Form Controls                    CLOSED
v3.6.11  Wave 2B-2 Dialog / Sheet                   CLOSED

Next route:
  Start next cycle plan-first.
  Primary candidates:
    Wave 2B-3 Date+Time #22+#23 PARTIAL completion.
    BACKLOG #21 Interpreter Plugin strategy.
  Alternative candidates:
    Wave 2B-4 Actions consumers #5 / #7 / #8.
    BACKLOG #41 shared WordPress ripple runtime packaging decision.
    BACKLOG #44 remaining specimen coverage follow-ons.
    BACKLOG #46 disabled ripple host authoring hygiene.
    BACKLOG #47 popover provider menu-item-class logic extraction hygiene.
    Sheet drag-to-dismiss follow-on.
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

## 2) v3.6.11 Close Summary

Closed by v3.6.11:

```txt
Phase 1 inventory:
  Dialog / Sheet baseline selectors mapped
  style-guide.js modal runtime recorded as factual precedent only
  native <dialog> vs custom Sheet host contract decided
  focus-trap overlap recorded as 0% shared custom overlap
  Route A selected: Dialog + Sheet module-local runtime

Phase 2 implementation:
  Dialog lab module added
  Sheet lab module added
  lab-dialog.js added; native showModal() owns Dialog focus containment
  lab-sheet.js added; Sheet owns local focus containment
  No shared focus-trap/backdrop provider added
  components.css, style-guide.js, and provider modules unchanged

Phase 3 visual QA:
  2 modules x desktop/mobile x light/dark: console 0 / overflow 0
  Dialog real pointer backdrop path PASS
  Dialog defensive .modal-scrim path PASS
  Sheet bottom + side focus containment PASS
  Sheet scrim close + focus restore PASS
  Drag-to-dismiss defer note visible
```

Validation at close:

```txt
node --check products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog.js PASS
node --check products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet.js PASS
wp-env run cli wp core version                      7.0
python tools/generators/build_pilot_specimen_wall.py PASS
npm run validate:specimen-wall                       PASS
php -l products/reference-implementations/axismundi-pilot/functions.php PASS
npm test                                             PASS (Axis A-G all 1.000)
npm run validate:computed                            PASS
npm run publish:styleguide                           PASS, generated mirror restored
git diff --check                                     PASS
```

Routed forward:

```txt
Wave 2B-3:
  Date+Time #22+#23 PARTIAL completion

Wave 2B-4:
  Actions consumers #5 / #7 / #8

BACKLOG #41 / #44 / #46 / #47:
  unchanged

Sheet drag-to-dismiss:
  Wave 2B-2 follow-on note in ROADMAP / NEXT-SESSION, no BACKLOG item

Native Dialog backdrop:
  future .dialog::backdrop visual styling must revisit external .modal-scrim layering

Lock 5:
  first post-promotion self-application held
```

Phase 3 test target convention:

```txt
For module pattern pages, prefer a repository-root localhost server:
  http://127.0.0.1:<port>/products/reference-implementations/axismundi-lab/modules/<module>/<pattern>.html

This avoids file:// automation policy blocks and preserves repository-root
self-hosted font / Material Symbols paths.
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

```txt
Lock 5 - diagnostic-first before implementation

For plan-first cycles where the route, failure mode, or boundary risk is not
already known, Phase 1 diagnostic inventory is mandatory before Phase 2
implementation. The diagnostic names source inputs, baseline / provider /
semantic boundaries, route buckets, selected and rejected routes, write scope,
fences, and validation plan.

Do not patch first and backfill the route later. Tiny mechanical edits with
explicit scope and no boundary risk may skip the full report only when the
shortcut is recorded as safe.
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
Wave 2B-3 Date+Time #22+#23 PARTIAL completion:
  complete the existing date-time/ PARTIAL input module, plan-first

BACKLOG #21 Interpreter Plugin strategy:
  plugin-tier strategy, with Lock 3/4 routing kept explicit
```

Alternative routes:

```txt
Wave 2B-4 Actions consumers #5 / #7 / #8
BACKLOG #41 shared WordPress ripple runtime packaging decision
BACKLOG #44 remaining specimen coverage / validator polish
BACKLOG #46 disabled ripple host authoring hygiene
BACKLOG #47 popover provider menu-item-class logic extraction hygiene
Sheet drag-to-dismiss follow-on
```

Phase cadence:

```txt
v3.6.x uses Phase 0 / 1 / 2 / 3 / 5.
Phase 4 is intentionally unused in this cadence.
```
