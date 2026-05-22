# v3.6.10 - Wave 2B Form - Phase 0 Plan

Date: 2026-05-22

Phase: 0 - Plan

## Verdict

Selected candidate:

```txt
Wave 2B Form
```

This is the primary next route from the v3.6.9 close. v3.6.9 completed
Wave 2A Navigation by closing BACKLOG #45 Menu / popover consumer closure.
The next cycle should move to the Wave 2B form/modal/action slice, starting
with diagnostic inventory before any implementation file changes.

Implementation files must not be edited before Phase 0 review gives GO.

## User Request Log

User said:

```txt
다음 사이클 Phase 0 plan go
```

Carry-forward from v3.6.9 close:

```txt
v3.6.9 fully CLOSED.
Wave 2A complete.
Recommended primary candidate: Wave 2B Form.
Alternatives: BACKLOG #21, narrowed #41, residual #44, #46, #47.
Diagnostic-first remains a methodology finding, not Lock 5.
```

## Source Inputs

### Phase 0 Actually Read

Read according to `NEXT-SESSION.md §0` order:

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #44 / #46 / #47 / #21 / #14
7. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-5-CLOSE.md
8. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-3-VISUAL-QA.md
9. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-2-REPORT.md
10. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-1-REPORT.md
11. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-0-PLAN.md
12. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
13. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
14. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
15. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
16. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-0-PLAN.md
17. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
18. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
19. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
20. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
21. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
22. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
23. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
24. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
25. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
26. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
27. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
28. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
29. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
30. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
31. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
32. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
33. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
34. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
35. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
36. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
37. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
38. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
39. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
40. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
41. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
42. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
43. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
44. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
45. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
46. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
47. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
```

Additional focused reads for this candidate:

```txt
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/
products/reference-implementations/axismundi-lab/modules/text-field/
products/reference-implementations/axismundi-lab/modules/search-bar/
products/reference-implementations/axismundi-lab/modules/date-time/
products/reference-implementations/axismundi-lab/modules/button/
products/reference-implementations/axismundi-lab/modules/button-group/
products/reference-implementations/axismundi-lab/modules/fab/
products/reference-implementations/axismundi-lab/modules/menu/
products/reference-implementations/axismundi-lab/modules/popover/
products/reference-implementations/axismundi-lab/modules/ripple/
products/reference-implementations/axismundi-lab/modules/icon-system/
```

## Current Baseline

```txt
origin/main:     2baecbb
local HEAD:      34ce0f3
local status:    main...origin/main [ahead 20], clean at Phase 0 entry
last close:      v3.6.9 Wave 2A-2 Menu / Popover Consumer
WordPress core:  7.0 in wp-env, per v3.6.9 close
```

This Phase 0 plan itself will become the next local commit after the entry
baseline above.

## Selected Candidate

The selected candidate is:

```txt
Wave 2B Form
```

`NEXT-SESSION.md`, `CURRENT-STATE.md`, `ROADMAP.md`, and the v3.6.9 close doc
all identify Wave 2B as the preferred next route after Wave 2A completion.

This plan treats Wave 2B as a diagnostic-first candidate, not as a preselected
implementation package. Phase 1 should decide whether v3.6.10 implements:

```txt
Checkbox / Radio / Switch only,
Dialog / Sheet only,
Date+Time completion only,
Actions consumers only,
a split decision with no implementation,
or another evidence-backed route.
```

## Candidate Rationale

v3.6.9 completed the Navigation slice:

```txt
Wave 2A-1: App bar / Nav bar / Nav rail / Tabs - CLOSED in v3.6.8
Wave 2A-2: Menu / popover consumer - CLOSED in v3.6.9
```

Wave 2B is now the freshest primary candidate because:

- it moves out of Navigation into a distinct Inputs / Feedback / Actions mix;
- it gives diagnostic-first another non-WP-domain test before any Lock 5
  promotion is considered;
- the form trio has native semantics and baseline primitives that can be
  validated without opening provider modules;
- Dialog / Sheet / Date+Time / Split button / FAB menu are present in Wave 2
  but have stronger runtime or provider-boundary risk, so Phase 1 should map
  their dependencies before they are bundled into any implementation slice.

## Wave 2B Candidate Surface

Canonical Wave 2 rows that remain relevant after Wave 2A:

```txt
Actions:
  Split button #7       TODO   popover/ consumer
  Toolbar #8            TODO   ripple/ CANDIDATE
  FAB menu #5           TODO   popover/ + ripple/ + icon-system consumer

Inputs:
  Checkbox #18          TODO   independent, native input + custom visual
  Radio #19             TODO   independent, native input + radiogroup
  Switch #20            TODO   independent, native checkbox + custom visual
  Date picker #22       PARTIAL date-time/ + popover/ consumer
  Time picker #23       PARTIAL folds into date-time/

Feedback:
  Dialog #26            TODO   independent runtime, focus trap + backdrop
  Sheet #27             TODO   independent runtime, modal surfaces
```

`COMPONENT-COVERAGE-MAP.md` says Wave 2 closure delivers "navigation + form +
modal" capability. Since Navigation is now closed, v3.6.10 should inventory
the remaining form/modal/action surfaces and pick the smallest coherent
implementation route.

## Index Disambiguation

Index numbers like `#18`, `#19`, and `#20` in this cycle refer to TOC component
indices from `docs/v3.5.0/COMPONENT-COVERAGE-MAP.md` and
`docs/v3.5.0/MODULE-STATUS-MATRIX.md`, not `BACKLOG.md` item numbers.

This matters because `BACKLOG.md #21`, `#41`, `#44`, `#46`, and `#47` are
different project backlog items.

## Initial Local Trace Summary

### Existing Modules

Existing `lab/modules/` directories include:

```txt
text-field/
search-bar/
date-time/
button/
button-group/
fab/
menu/
popover/
ripple/
icon-system/
```

Missing Wave 2B candidate target directories at Phase 0 entry:

```txt
checkbox/
radio/
switch/
dialog/
sheet/
split-button/
toolbar/
fab-menu/
```

### Baseline CSS Traces

`components.css` already contains baseline primitives for all major Wave 2B
candidate groups:

```txt
§12 Dialog
§13 Sheet
§22 Checkbox
§23 Radio
§24 Switch
§29 Toolbar
§31 FAB menu
§32 Split button
§33 Date picker
§34 Time picker
```

The form trio is especially clean:

```txt
Chunk F1 - Checkbox + Radio
Chunk F2 - Switch + Slider
```

Those comments explicitly frame Checkbox / Radio / Switch as native form
controls with custom visual spans. Phase 1 must verify the actual state matrix
and not infer completion from selector presence alone.

### Styleguide Anchors

`style-guide.html` has anchors for:

```txt
#components-checkbox
#components-radio
#components-switch
#components-date-picker
#components-time-picker
#components-dialog
#components-sheet
#components-fab-menu
#components-split-button
#components-toolbar
```

These are baseline catalog specimens, not module closure evidence. Phase 1
must distinguish static catalog coverage from lab module completion.

## Governing Prior Decisions

### Wave 2A Is Closed

v3.6.8 and v3.6.9 closed the Navigation slice. Do not reopen App bar, Nav bar,
Nav rail, Tabs, or Menu in v3.6.10 unless Phase 1 finds a direct dependency
that blocks Wave 2B diagnostics.

### Menu / Popover Hygiene Is Routed To #47

BACKLOG #47 tracks the pre-existing `popover/` menu-item-class logic hygiene:

```txt
lab-popover.js menu item selectors and keyboard behavior
lab-popover.css §3 .ax-menu__item:focus-visible override
```

v3.6.10 must not fold #47 into Wave 2B unless Phase 1 proves that a selected
Wave 2B route cannot proceed without provider extraction. If that happens,
Phase 1 should stop and return for review instead of editing `popover/`.

### Disabled Ripple Host Hygiene Remains #46

BACKLOG #46 remains open for disabled ripple host authoring hygiene. If form
or action surfaces expose disabled ripple-host questions, Phase 1 should route
them explicitly and avoid silent authoring-policy changes.

### Test Target Convention

v3.6.9 Phase 5 recorded the module QA convention:

```txt
Prefer repo-root localhost URLs for module pattern QA unless the cycle has a
more specific test target.
```

For a future Phase 3, expected URL shape:

```txt
http://127.0.0.1:<port>/products/reference-implementations/axismundi-lab/modules/<module>/<pattern>.html
```

### Lock 5 Is Still Not A Lock

Diagnostic-first has succeeded across v3.6.5 through v3.6.9, including two
component-lab cycles. It remains a methodology finding, not a repo lock.

Do not edit `AGENTS.md` or `CLAUDE.md` in v3.6.10 unless review explicitly
approves Lock 5 promotion. Promotion is not expected in this plan.

## Active Locks

### Lock 1 - wp-custom downstream-only

For WordPress theme work, `settings.custom.axismundi.*` is a downstream
projection. Every leaf must be `var(--comp-*)`, `var(--md-sys-*)`, or
`var(--md-ref-*)`. Literal hex, rgb, px, and number values are forbidden in
that namespace. The permanent guard is `tools/validators/validate_theme_pilot.py`
Axis G.

### Lock 2 - md-sys color maps to md-ref

For color roles, every `--md-sys-color-*` entry must be defined as
`var(--md-ref-palette-*)`. Literal hex, rgb, and hsl values are forbidden in
the md-sys color layer. Dark mode swaps sys -> ref mappings only; it does not
rewrite ref primitives or inject theme.json color literals. The permanent
guard is `tools/validators/validate_theme_pilot.py` Axis E.

### Lock 3 - core/button semantic route before visual cleanup

For `core/button`, name the semantic route before accepting visual cleanup for
link affordances. A `core/button` anchor with `href` is navigation and may
receive an M3 button visual bridge. A real action, form behavior, AJAX flow,
federation action, or durable custom schema must be routed to plugin/custom-
block territory, not implemented in the theme bridge.

### Lock 4 - semantic mismatch handling rule

When a WordPress core block visually maps to M3 but carries divergent markup,
interaction, or accessibility semantics, route the mismatch as either theme-
owned semantic-decision or plugin/custom-block territory before accepting a
visual fix. Do not silently ignore the mismatch and do not collapse distinct
core block structures into one generic CSS patch.

### Methodology Note - not Lock 5

Diagnostic-first remains a methodology finding, not Lock 5. v3.6.10 is a new
domain test because the leading candidate is the form/input family rather than
Navigation or WordPress bridge/specimen work.

## In Scope

Phase 1 should inventory and classify:

1. Checkbox #18 baseline primitives, styleguide specimens, native input
   semantics, states, and module gap.
2. Radio #19 baseline primitives, radiogroup semantics, states, and module gap.
3. Switch #20 baseline primitives, native checkbox semantics, thumb/track
   states, and module gap.
4. Date picker #22 and Time picker #23 current `date-time/` PARTIAL state and
   whether v3.6.10 should defer them or include a completion path.
5. Dialog #26 baseline primitives and runtime needs: focus trap, Escape,
   backdrop, close path, full-screen variant.
6. Sheet #27 baseline primitives and runtime needs: bottom modal, side modal,
   drag-to-dismiss or explicit defer, scrim, focus restoration.
7. Split button #7 baseline primitives and popover consumer boundary.
8. FAB menu #5 baseline primitives and dependencies on closed FAB, Menu,
   popover, ripple, and icon-system modules.
9. Toolbar #8 baseline primitives and whether it belongs in Wave 2B or a
   separate Actions follow-on.
10. Provider dependency map for `popover/`, `ripple/`, and `icon-system/`.
11. Native semantics map for form controls, including label association,
    checked/unchecked, indeterminate, disabled, focus-visible, error, and
    required/invalid where exposed.
12. Route selection among A/B/C/D/E/F with evidence.

## Out Of Scope

This Phase 0 and the following Phase 1 must not:

1. Edit any implementation file before Phase 1 review GO.
2. Promote diagnostic-first to Lock 5.
3. Edit `AGENTS.md` or `CLAUDE.md`.
4. Edit `theme.json`.
5. Edit `functions.php`.
6. Edit WordPress Pilot bridge source or copied asset pairs.
7. Edit Pilot fixtures or specimen wall fixtures.
8. Edit `components.css`; for this cycle it is an entire-file fence unless
   Phase 1 explicitly returns for a baseline-update review.
9. Edit `style-guide.html` manually.
10. Edit `styleguide/` mirror files except by `publish:styleguide`, followed
    by restoration of generated churn.
11. Edit `popover/`, `ripple/`, or `icon-system/` provider modules.
12. Edit already-closed Wave 2A modules: `app-bar/`, `nav-bar/`, `nav-rail/`,
    `tabs/`, or `menu/`.
13. Enter BACKLOG #21 Interpreter Plugin strategy.
14. Enter narrowed BACKLOG #41 shared WordPress ripple runtime packaging.
15. Enter residual BACKLOG #44 coverage/polish.
16. Enter BACKLOG #46 disabled ripple host hygiene except as a routed note.
17. Enter BACKLOG #47 provider hygiene except as a routed note.
18. Implement WordPress editor, plugin, custom block, or durable form handling.
19. Treat baseline styleguide controls as sufficient evidence of module DONE.
20. Introduce new global document/window listeners without a Phase 1 route
    decision and review.

## Route Buckets

### Route A - Full Wave 2B

Implement or close every remaining Wave 2B candidate:

```txt
Checkbox / Radio / Switch
Date+Time completion
Dialog / Sheet
Split button / FAB menu / Toolbar
```

This is likely too broad unless Phase 1 proves the implementation surface is
mostly audit/pattern-only and provider-neutral.

### Route B - Form Controls Core First

Implement Checkbox / Radio / Switch as a coherent native form-control slice.

Likely artifacts if selected:

```txt
modules/checkbox/
modules/radio/
modules/switch/
docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
```

This is the conservative expected route if Phase 1 confirms:

- the three controls are independent in `MODULE-STATUS-MATRIX.md`;
- baseline primitives are sufficient;
- no provider edits are needed;
- no new JS runtime is needed beyond possible pattern-local indeterminate
  setup or test helper logic;
- native semantics can be verified cleanly.

### Route C - Dialog / Sheet Runtime First

Implement Dialog and/or Sheet as the next Interaction Runtime slice.

This route is only acceptable if Phase 1 shows the runtime can be component-
local and does not require a new shared overlay provider, popover mutation,
global shell mutation, or styleguide baseline change.

### Route D - Actions Consumer First

Implement Split button, FAB menu, and/or Toolbar.

This route would continue the provider-consumer work after Menu. It must be
careful with `popover/`, `ripple/`, `icon-system/`, and #47 hygiene. If any
provider edit is required, stop and return for review.

### Route E - Audit / Split Decision Only

No implementation. Produce a Phase 2 decision report that splits Wave 2B into
smaller cycles, such as:

```txt
Wave 2B-1: Checkbox / Radio / Switch
Wave 2B-2: Dialog / Sheet
Wave 2B-3: Date+Time completion
Wave 2B-4: Split button / FAB menu / Toolbar
```

This is acceptable if Phase 1 finds the candidate space too broad for a safe
single implementation cycle.

### Route F - Other Evidence-Backed Route

Use only if Phase 1 discovers evidence that does not fit A-E. The Phase 1
report must name the evidence and why the listed routes are insufficient.

## Preferred Initial Hypothesis

The initial hypothesis is Route B:

```txt
Checkbox / Radio / Switch core form controls first.
```

Reason:

- all three are Inputs group rows;
- all three are TODO and have no existing module directory;
- all three are independent in `MODULE-STATUS-MATRIX.md`;
- all three have baseline primitives and styleguide anchors;
- all three can test the input-control domain before Lock 5 promotion;
- they avoid `popover/`, #47, Dialog/Sheet runtime, and Date+Time PARTIAL
  complexity.

This is a hypothesis only. Phase 1 must either confirm Route B or select a
different bucket with evidence.

## Risk Register

### R1 - Wave 2B Overbreadth

Wave 2B spans Inputs, Feedback runtime, and Actions consumers. A full cycle may
be too wide.

Mitigation: Phase 1 must route by evidence and may select Route E split-only.

### R2 - Native Semantics Hidden By Custom Visuals

Checkbox / Radio / Switch use hidden native inputs plus custom visual spans.
It is easy to verify the visual surface while missing label, keyboard, checked,
indeterminate, disabled, or form semantics.

Mitigation: Phase 1 must map native input semantics and Phase 3 must verify
DOM state, not just screenshots.

### R3 - Dialog / Sheet Runtime Scope Explosion

Dialog and Sheet require focus management, Escape/backdrop dismissal, modal
state, and possibly portal/overlay conventions.

Mitigation: if selected, Phase 2 must keep runtime component-local or stop for
review. Global overlay/provider extraction is out of scope.

### R4 - Popover Provider Bleed

Split button, FAB menu, Date+Time, and Menu all relate to `popover/`. BACKLOG
#47 already tracks pre-existing provider hygiene.

Mitigation: `popover/` is fenced. If any selected route requires provider
mutation, stop and return to Phase 1 review.

### R5 - Baseline Mutation Too Early

`components.css` contains broad baseline primitives for the candidate surfaces.
Changing them during Wave 2B would blur module validation with baseline
redefinition.

Mitigation: treat `components.css` as an entire-file fence in v3.6.10 unless
Phase 1 explicitly requests a baseline-update re-gate.

### R6 - Ripple Or Icon-System Drift

Actions consumers may tempt ripple/icon-system edits. Form controls likely do
not require these providers, but visual icons or action compositions may.

Mitigation: `ripple/` and `icon-system/` are fenced. Consumers may use existing
public contracts only after Phase 1 classifies the state.

### R7 - Date+Time PARTIAL Reopen Without Scope

Date picker and Time picker are PARTIAL via `date-time/`. They are Wave 2 but
not the same shape as Checkbox / Radio / Switch.

Mitigation: Phase 1 must decide whether Date+Time belongs in v3.6.10 or should
remain a separate PARTIAL completion cycle.

### R8 - Plugin Or WordPress Scope Bleed

Form controls can suggest real submission handling, validation persistence,
editor integration, or plugin form builders.

Mitigation: v3.6.10 is lab component work only unless Phase 1 selects a no-code
plugin routing report. No WordPress binding or plugin implementation.

### R9 - Lock 5 Premature Promotion

Wave 2B may provide stronger diagnostic-first evidence. That does not itself
authorize a new lock.

Mitigation: Lock 5 promotion is not expected. Keep `AGENTS.md` and `CLAUDE.md`
unchanged unless review explicitly asks for promotion.

### R10 - Publish / Validator Artifact Churn

`publish:styleguide` and validators may generate tracked mirror/report files.

Mitigation: restore generated churn after validation. Phase reports must note
when artifacts are restored.

## Files Expected To Change In Phase 0

```txt
docs/v3.6.10/WAVE-2B-FORM-PHASE-0-PLAN.md
```

No implementation files should change in Phase 0.

## Files Not Expected To Change Before Phase 1 Review

```txt
AGENTS.md
CLAUDE.md
theme.json
functions.php
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
products/reference-implementations/axismundi-lab/modules/app-bar/*
products/reference-implementations/axismundi-lab/modules/nav-bar/*
products/reference-implementations/axismundi-lab/modules/nav-rail/*
products/reference-implementations/axismundi-lab/modules/tabs/*
products/reference-implementations/axismundi-lab/modules/menu/*
products/reference-implementations/axismundi-lab/modules/date-time/*
products/reference-implementations/axismundi-lab/modules/text-field/*
products/reference-implementations/axismundi-lab/modules/search-bar/*
products/reference-implementations/axismundi-lab/modules/button/*
products/reference-implementations/axismundi-lab/modules/button-group/*
products/reference-implementations/axismundi-lab/modules/fab/*
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/fixtures/*
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
```

## Phase Plan

### Phase 0 - Plan

Create this plan, commit it, and submit it for review.

Exit criteria:

- one plan doc added;
- no implementation files changed;
- route buckets and risks documented;
- NEXT-SESSION §0 reading order recorded;
- validation: `git diff --check`.

### Phase 1 - Diagnostic Inventory

Produce:

```txt
docs/v3.6.10/WAVE-2B-FORM-PHASE-1-REPORT.md
```

Required inventory tasks:

1. Reconfirm local status, HEAD, and clean tree at Phase 1 entry.
2. Reconfirm `modules/checkbox`, `modules/radio`, `modules/switch`,
   `modules/dialog`, `modules/sheet`, `modules/split-button`,
   `modules/toolbar`, and `modules/fab-menu` absence or presence.
3. Inventory `components.css` baseline selector families for Checkbox, Radio,
   Switch, Dialog, Sheet, Date picker, Time picker, Split button, Toolbar, and
   FAB menu.
4. Inventory `style-guide.html` baseline anchors and specimen counts for the
   same surfaces.
5. Classify each row by category, dependency, and risk:
   Component Full-Spec, Interaction Runtime, dual category, PARTIAL completion,
   or Actions consumer.
6. For Checkbox / Radio / Switch, map native input states:
   unchecked, checked, indeterminate where applicable, disabled,
   focus-visible, hover, active, error/invalid where exposed.
7. For Dialog / Sheet, map runtime needs:
   trigger, host, scrim/backdrop, open/close, Escape, focus restoration,
   focus trap, outside click, reduced motion, mobile viewport behavior.
8. For Split button / FAB menu / Toolbar, map provider dependencies and whether
   they require `popover/`, `ripple/`, `icon-system/`, Menu, Button, or FAB
   changes.
9. Decide whether Date+Time belongs in v3.6.10 or should remain a separate
   PARTIAL completion candidate.
10. Verify whether selected route can preserve all fences, especially
    `components.css`, `popover/`, `ripple/`, and `icon-system/`.
11. Choose Route A/B/C/D/E/F with rejected-bucket evidence.
12. State Lock 5 decision: expected defer.

Phase 1 must stop and return to review if it finds that safe progress requires
editing any provider, baseline, WordPress, Pilot, or lock file.

### Phase 2 - Conditional Implementation Or Decision Report

Phase 2 write scope depends on the Phase 1 route and review verdict.

If Route B is selected, expected write shape may be:

```txt
products/reference-implementations/axismundi-lab/modules/checkbox/
products/reference-implementations/axismundi-lab/modules/radio/
products/reference-implementations/axismundi-lab/modules/switch/
docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
```

Likely per-module artifact shape:

```txt
lab-<module>.css
lab-<module>-pattern.html
docs/<MODULE>-SPEC-AUDIT.md
docs/<MODULE>-MEASUREMENT-AUDIT.md
docs/<MODULE>-WP-MAPPING.md
```

Add runtime docs or JS only if Phase 1 proves the component category requires
it and review approves it.

If Route E is selected, Phase 2 may be no-code:

```txt
docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
```

Any Phase 2 implementation must use lab-scoped selectors, for example:

```txt
.lab-checkbox-demo
.lab-radio-demo
.lab-switch-demo
.lab-dialog-demo
.lab-sheet-demo
```

Forbidden selector shapes unless separately reviewed:

```txt
unscoped .ax-checkbox overrides
unscoped .ax-radio overrides
unscoped .ax-switch overrides
unscoped .dialog overrides
unscoped .sheet overrides
unscoped [data-ax-ripple] overrides
unscoped .material-symbols-rounded overrides
provider-specific branches in popover/ripple/icon-system
```

### Phase 3 - Visual / Interaction QA

Phase 3 should use the v3.6.9 repo-root localhost test target convention.

Expected checks depend on route, but at minimum:

- desktop light;
- desktop dark;
- mobile light at approximately 390px wide;
- mobile dark at approximately 390px wide;
- console errors 0;
- horizontal overflow 0;
- focus-visible state;
- disabled state;
- selected/checked state where applicable;
- native semantics or interaction matrix for the selected modules;
- standard validation commands.

If Dialog / Sheet are selected, apply the global portal / overlay smoke test:

1. trigger exists;
2. runtime handler attaches;
3. host / portal element exists;
4. open and visible state works;
5. close / dismiss path works;
6. console and page errors are absent.

### Phase 5 - Close

Expected Phase 5 artifacts:

```txt
docs/v3.6.10/WAVE-2B-FORM-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

Phase 5 should:

- close or narrow the selected v3.6.10 route;
- update next-session reading order with v3.6.10 docs;
- preserve #41 / #44 / #46 / #47 unless intentionally routed;
- record whether Lock 5 remains deferred;
- update next-cycle candidate ordering;
- clean up any handoff wording that becomes stale.

AGENTS.md / CLAUDE.md are only edited if review explicitly approves a new
Lock 5 promotion. That is not expected.

## Validation Strategy

Phase 0:

```txt
git diff --check
```

Phase 1:

```txt
git status --short --branch
git diff --name-only
```

Phase 2 / Phase 3 / Phase 5 expected validation set:

```txt
wp-env run cli wp core version
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l functions.php
npm test
npm run validate:computed
npm run publish:styleguide
git diff --check
```

If a local module page is introduced, add Playwright/browser QA against:

```txt
http://127.0.0.1:<port>/products/reference-implementations/axismundi-lab/modules/<module>/<pattern>.html
```

## Expected Review Focus

Phase 0 review should verify:

1. Wave 2B is a valid selected candidate after v3.6.9.
2. Route buckets cover the remaining Wave 2 form/modal/action space.
3. Route B is only a hypothesis, not an implementation commitment.
4. `components.css` entire-file fence is explicit.
5. `popover/`, `ripple/`, `icon-system/`, and closed Wave 2A modules are
   fenced.
6. Lock 5 remains deferred.
7. Phase 1 tasks are diagnostic-first and sufficient for route selection.

## Next

Submit this Phase 0 plan for review.

Do not edit implementation files before Phase 0 review gives GO.
