# NEXT-SESSION.md - Post-v3.6.14 Handoff

> **Status**: v3.6.0 Ontology Theme Pilot, v3.6.1 Token Architecture
> Refactor, v3.6.2 WP Core Block Specimen Wall, v3.6.3 WP Block Bridge
> Expansion, v3.6.4 WP Block Bridge Residual Cleanup, v3.6.5 WP Block
> Bridge Editor Token Parity, v3.6.6 WP Block Bridge Ripple / Editor State
> Parity, v3.6.7 WP Specimen Follow-On Editor Compatibility, v3.6.8 Wave 2A
> Navigation Core, v3.6.9 Wave 2A-2 Menu / Popover Consumer, v3.6.10
> Wave 2B-1 Form Controls, v3.6.11 Wave 2B-2 Dialog / Sheet, v3.6.12
> Wave 2B-3 DateTime, v3.6.13 Wave 2B-4 Actions Consumers, and v3.6.14
> Wave 3 Closure - Inputs / Feedback Final are closed.
> **Use**: read at the start of the next Codex/Claude session.
> **Last updated**: 2026-05-23.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #44 / #46 / #47 / #21 / #14
7. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-5-CLOSE.md
8. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-3-VISUAL-QA.md
9. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-2-REPORT.md
10. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-1-REPORT.md
11. docs/v3.6.14/WAVE-3-COMPONENTS-PHASE-0-PLAN.md
12. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-5-CLOSE.md
13. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-3-VISUAL-QA.md
14. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-2-REPORT.md
15. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-1-REPORT.md
16. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-0-PLAN.md
17. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-5-CLOSE.md
18. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-3-VISUAL-QA.md
19. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-2-REPORT.md
20. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-1-REPORT.md
21. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-0-PLAN.md
22. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-5-CLOSE.md
23. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-3-VISUAL-QA.md
24. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-2-REPORT.md
25. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-1-REPORT.md
26. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-0-PLAN.md
27. docs/v3.6.10/WAVE-2B-FORM-PHASE-5-CLOSE.md
28. docs/v3.6.10/WAVE-2B-FORM-PHASE-3-VISUAL-QA.md
29. docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
30. docs/v3.6.10/WAVE-2B-FORM-PHASE-1-REPORT.md
31. docs/v3.6.10/WAVE-2B-FORM-PHASE-0-PLAN.md
32. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-5-CLOSE.md
33. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-3-VISUAL-QA.md
34. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-2-REPORT.md
35. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-1-REPORT.md
36. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-0-PLAN.md
37. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
38. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
39. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
40. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
41. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-0-PLAN.md
42. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
43. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
44. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
45. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
46. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
47. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
48. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
49. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
50. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
51. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
52. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
53. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
54. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
55. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
56. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
57. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
58. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
59. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
60. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
61. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
62. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
63. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
64. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
65. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
66. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
67. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
68. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
69. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
70. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
71. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
72. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
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
v3.6.12  Wave 2B-3 DateTime                         CLOSED
v3.6.13  Wave 2B-4 Actions Consumers                 CLOSED
v3.6.14  Wave 3 Closure - Inputs / Feedback Final   CLOSED

Next route:
  Start next cycle plan-first.
  Primary candidates:
    VS Code diagnostics sweep after component modularization.
  Alternative candidates:
    BACKLOG #21 Interpreter Plugin strategy.
    BACKLOG #41 shared WordPress ripple runtime packaging decision.
    BACKLOG #44 remaining specimen coverage follow-ons.
    BACKLOG #46 disabled ripple host authoring hygiene.
    BACKLOG #47 popover provider menu-item-class logic extraction hygiene.
    Pilot theme revision.
    Sheet drag-to-dismiss follow-on.
    Styleguide integration for Slider / Loading / Progress module pages.
    Docker-dependent validation retry once wp-env is available.
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

## 2) v3.6.14 Close Summary

Closed by v3.6.14:

- Wave 3 Closure - Inputs / Feedback Final.
- Slider #21, Loading #30, and Progress #31.
- All remaining TODO component rows.

Evidence:

- Added `modules/slider/`, `modules/loading/`, and `modules/progress/`.
- Slider uses lab-scoped CSS, `lab-slider.js`, pattern HTML, and SPEC /
  MEASUREMENT / RUNTIME / WP docs.
- Loading and Progress use lab-scoped CSS, pattern HTML, and SPEC /
  MEASUREMENT / WP docs.
- 12 Phase 3 visual cells passed with console 0, 4xx 0, overflow 0, and
  `theme.js` no-load.
- Slider keyboard/value sync, Loading `role=status`, and Progress
  `role=progressbar` evidence passed.
- Loading and Progress reduced-motion fallback passed via CDP emulation.
- Slider reduced-motion is N/A because it has no animation surface.
- Lock 5 fourth clean post-promotion self-application held.

Current matrix snapshot:

```txt
DONE       31
PARTIAL     0
TODO        0
RECORD      3
```

Validation status:

- PASS: `node --check` for `lab-slider.js`.
- PASS: `php -l products/reference-implementations/axismundi-pilot/functions.php`.
- PASS: `npm test`; Axis A/B/C/D/E/F/G all 1.000.
- PASS: `npm run publish:styleguide`, with generated mirror restored.
- PASS: `git diff --check`.
- BLOCKED: `build_pilot_specimen_wall`, `validate:specimen-wall`, and
  `validate:computed` because Docker Desktop / `wp-env` was unavailable.

Resume checklist:

1. If Docker Desktop is available, rerun:
   `python tools/generators/build_pilot_specimen_wall.py`,
   `npm run validate:specimen-wall`, and `npm run validate:computed`.
2. Confirm local `git status --short --branch`; local git status is
   authoritative for mount-staleness cases.
3. Start the next cycle plan-first; do not enter Phase 2 without a review
   trigger.

Routed forward:

- VS Code diagnostics sweep as primary next candidate.
- Optional styleguide integration for
  `lab/modules/{slider,loading,progress}/`.
- Loading inline-in-button "Saving" contrast and Progress linear determinate
  dark-mode contrast as low-priority visual observations.
- BACKLOG #21 / #41 / #44 / #46 / #47 and Pilot theme revision remain
  available candidates after diagnostic sweep.

## 3) v3.6.13 Close Summary

Closed by v3.6.13:

- Wave 2B-4 Actions Consumers.
- FAB menu #5, Split button #7, and Toolbar #8.
- Wave 2B as a whole.

Evidence:

- 22 Phase 2 files added for 3 modules x lab CSS / JS / pattern / 4 audit docs
  plus the Phase 2 report.
- 12 Phase 3 visual cells passed with console 0, 4xx 0, and overflow 0.
- FAB menu verified intentional outside-click absence plus Escape close.
- Split button verified primary action distinct from trailing chevron popover
  trigger.
- Toolbar verified local `aria-pressed` state sync without loading
  `scripts/theme.js`.
- Toolbar ripple count clarified: 7 icon buttons total, 6 enabled unbounded
  ripple hosts, and 1 disabled no-ripple host.
- Lock 5 third clean post-promotion self-application held.

Current matrix snapshot:

```txt
DONE       28
PARTIAL     0
TODO        3
RECORD      3
```

Routed forward:

- Remaining TODO component rows.
- BACKLOG #21 Interpreter Plugin strategy.
- BACKLOG #41 / #44 / #46 / #47 unchanged.
- VS Code diagnostics sweep after component modularization.

## 3) v3.6.12 Close Summary

Closed by v3.6.12:

```txt
Phase 1 inventory:
  Existing date-time/ module preserved and mapped as PARTIAL -> DONE candidate
  Chunk H3/H4 Date+Time baseline selectors mapped
  popover/ relationship resolved as aspirational/stale, not factual consumer
  BACKLOG #19 itemized into drift-closed, Phase 2 closure, and decision rows
  Route A selected: self-contained DateTime completion

Phase 2 implementation:
  Existing lab-date-time.js / CSS / pattern updated in place
  Date grid gained role=row wrappers, aria-current, aria-labelledby,
    aria-multiselectable, live announcements, Home/End, PageUp/PageDown,
    Shift+PageUp/PageDown, and Enter/Space activation
  Time picker listbox/option contract preserved
  Four modern DateTime audit docs added
  Legacy DATE-TIME-AUDIT.md preserved with a v3.6.12 addendum
  No popover migration, provider edit, baseline edit, plugin/WP edit, or Lock 5 shortcut

Phase 3 visual QA:
  DateTime x desktop/mobile x light/dark: console 0 / overflow 0
  CDP Accessibility.getFullAXTree verified grid: 1, row: 6, gridcell: 42
  Date keyboard matrix PASS: Arrow, Home/End, PageUp/PageDown,
    Shift+PageUp/PageDown, Enter, Space, roving tabindex, live text
  Time picker non-regression PASS: listbox/options, 12h/24h, typed input,
    Escape close, OK commit
  Forbidden-ancestor bail-out PASS
```

Validation at close:

```txt
node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js PASS
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
Wave 2B-4:
  Actions consumers #5 / #7 / #8 closed by v3.6.13

BACKLOG #41 / #44 / #46 / #47:
  unchanged

BACKLOG #19:
  closed by v3.6.12

Sheet drag-to-dismiss:
  Wave 2B-2 follow-on note in ROADMAP / NEXT-SESSION, no BACKLOG item

Native Dialog backdrop:
  future .dialog::backdrop visual styling must revisit external .modal-scrim layering

DateTime provider-matrix wording:
  current DateTime module is self-contained; stale/aspirational popover/ wording
  routes as light documentation cleanup, not a BACKLOG item

Lock 5:
  second post-promotion self-application held; no safe-shortcut exception used
```

Phase 3 test target convention:

```txt
For module pattern pages, prefer a repository-root localhost server:
  http://127.0.0.1:<port>/products/reference-implementations/axismundi-lab/modules/<module>/<pattern>.html

This avoids file:// automation policy blocks and preserves repository-root
self-hosted font / Material Symbols paths.

For a11y-heavy modules, CDP Accessibility.getFullAXTree is a primary evidence
path for role hierarchy checks. Manual NVDA / VoiceOver audio testing remains
supplementary unless a reviewer explicitly requires it.
```

## 4) Lesson Locks

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

## 5) Resume Checklist

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

## 6) Next Action

Choose the next cycle. Do not auto-start implementation without a Phase 0 plan.

Recommended primary routes:

```txt
Remaining TODO component rows:
  plan-first module coverage for the remaining unclosed component rows

VS Code diagnostics sweep:
  after component modularization, collect and list workspace errors / warnings

BACKLOG #21 Interpreter Plugin strategy:
  plugin-tier strategy, with Lock 3/4 routing kept explicit
```

Alternative routes:

```txt
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
