# v3.6.9 - Wave 2A-2 Menu / Popover Consumer - Phase 0 Plan

Date: 2026-05-22

Phase: 0 - Plan

## Verdict

Selected candidate:

```txt
BACKLOG #45 - Wave 2A-2 Menu / popover consumer closure
```

This is the primary next route from the v3.6.8 close. The cycle should finish
the Wave 2A Navigation split by adding the Menu component as a consumer module
without reopening the already-closed `popover/` provider.

Implementation files must not be edited before Phase 0 review gives GO.

## Source Inputs

### Phase 0 Actually Read

Read according to `NEXT-SESSION.md §0` order:

```txt
1. AGENTS.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #44 / #45 / #46 / #21 / #14
7. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
8. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
9. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
10. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
11. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-0-PLAN.md
12. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
13. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
14. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
15. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
16. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
17. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
18. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
19. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
20. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
21. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
22. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
23. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
24. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
25. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
26. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
27. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
28. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
29. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
30. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
31. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
32. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
33. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
34. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
35. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
36. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
37. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
38. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
39. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
40. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
41. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
42. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
```

Additional Phase 0 reads for this candidate:

```txt
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
products/reference-implementations/axismundi-lab/modules/popover/docs/POPOVER-AUDIT.md
products/reference-implementations/axismundi-lab/modules/popover/lab-popover.js
products/reference-implementations/axismundi-lab/modules/popover/lab-popover.css
products/reference-implementations/axismundi-lab/modules/popover/lab-popover-pattern.html
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/icon-system/docs/*
products/reference-implementations/axismundi-lab/stylesheets/components.css §Menu
products/reference-implementations/axismundi-lab/style-guide.html #components-menu
products/reference-implementations/axismundi-lab/modules/README.md
package.json
```

## Current Baseline

```txt
origin/main:     2baecbb
local HEAD:      6df49c4
local status:    main...origin/main [ahead 15], clean at Phase 0 entry
last close:      v3.6.8 Wave 2A Navigation Core
WordPress core:  7.0 in wp-env, per v3.6.8 close
```

## Candidate Rationale

v3.6.8 deliberately split Wave 2A:

```txt
Wave 2A-1: App bar / Nav bar / Nav rail / Tabs - CLOSED
Wave 2A-2: Menu - OPEN as BACKLOG #45
```

Menu is the freshest and most bounded next candidate because:

- it completes the Navigation slice that v3.6.8 intentionally left open;
- it tests diagnostic-first in a harder component-lab domain;
- it is the first direct consumer-boundary cycle for `popover/`;
- it can validate DISTINCT but COUPLED discipline before later consumers
  such as Split button, FAB menu, Date/Time, and future Select rely on the
  same provider contract.

## Governing Prior Decisions

### DISTINCT But COUPLED

`docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #15 says:

```txt
Menu owns role=menu/menuitem/density/icon/shortcut/selected/disabled/divider/submenu;
popover/ owns anchor/position/dismiss/outside-click/Escape/focus restore/viewport collision
```

`docs/v3.5.0/PROMOTION-CRITERIA.md §5.2` says:

```txt
popover/ MUST NOT contain menu-item logic
Menu module MUST NOT reimplement anchored-surface positioning when popover/
already provides it.
```

This cycle must preserve both sides.

### Existing Provider Contract

`popover/` is already closed as infrastructure. It exposes:

```txt
window.labPopover.init(root?)
window.labPopover.close()
window.labPopover.isOpen
window.labPopover.openMenuId
```

It wires triggers by `[data-popover-trigger]` and uses `aria-controls` to find
the menu surface. It owns:

```txt
anchor positioning
open / close state
outside pointerdown dismissal
Escape dismissal
focus restoration
viewport repositioning
open-scoped document listeners
forbidden-ancestor bail-out
```

Phase 1 must determine whether this provider contract is enough for Menu.

### Existing Menu Primitive

`components.css` already contains baseline visual primitives for:

```txt
.ax-menu
.ax-menu.is-open
.ax-menu__section-label
.ax-menu__divider
.ax-menu__item
.ax-menu__item-leading
.ax-menu__item-trailing
.ax-menu__item-label
.ax-menu__item-supporting
.ax-menu__item-trailing-text
.ax-menu__item.is-selected
.ax-menu__item[aria-selected="true"]
.ax-menu__item:disabled
.ax-menu__item[aria-disabled="true"]
```

`style-guide.html #components-menu` contains static visual specimens. Those
are evidence, not closure.

### Ripple And Icon Consumer State

`MODULE-STATUS-MATRIX.md` says:

```txt
Menu #15:
  Provider: popover/, icon-system/
  ripple state: TARGET bounded
```

Therefore Menu Phase 2 may declare `data-ax-ripple="bounded"` on menu items
if animated ripple is part of the pattern. It must not edit the ripple
provider.

Icon usage should follow the existing Material Symbols policy:

```txt
material-symbols-rounded notranslate
translate="no"
aria-hidden="true" for decorative glyphs
draggable="false" where local pattern uses it
```

Do not use this cycle to solve BACKLOG #14 unless a new real icon failure is
proven.

## Active Locks

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

Diagnostic-first remains a methodology finding, not Lock 5. This cycle is a
second component-lab domain test after v3.6.8 and the first direct
provider-consumer boundary test for the method.

## In Scope

1. Inventory existing Menu visual primitives, static specimens, and provider
   contract.
2. Decide whether Menu can close as a provider consumer with `popover/`
   unchanged.
3. If Phase 2 proceeds, add a dedicated `menu/` lab module.
4. Add Menu audit docs:
   - SPEC audit
   - MEASUREMENT audit
   - RUNTIME audit
   - WP mapping audit
5. Add a Menu pattern page that validates:
   - static open menu
   - anchored trigger + menu through `popover/`
   - selected item
   - disabled item
   - divider
   - section label
   - leading icon
   - trailing shortcut text
   - supporting text
   - bounded ripple consumers if included
6. Verify Menu does not require any provider mutation.
7. Verify existing `popover/` behavior still works with the Menu consumer:
   - trigger exists
   - runtime handler attaches
   - host/surface exists
   - open visible state works
   - Escape close works
   - outside pointerdown close works
   - focus restore works
   - viewport placement remains provider-owned
8. Preserve Wave 2B, BACKLOG #21, #41, #44, and #46 as routed alternatives.

## Out Of Scope

```txt
Do not edit AGENTS.md / CLAUDE.md.
Do not promote diagnostic-first to Lock 5.
Do not edit theme.json.
Do not edit functions.php.
Do not edit Pilot bridge source or asset pairs.
Do not edit Pilot fixtures.
Do not edit components.css.
Do not edit blocks.css.
Do not edit modules/popover/* unless Phase 1 explicitly returns to review.
Do not edit modules/ripple/*.
Do not edit modules/icon-system/*.
Do not manually edit styleguide/*.
Do not implement Split button.
Do not implement FAB menu.
Do not implement Date/Time picker follow-on.
Do not implement future Select.
Do not enter BACKLOG #41 ripple packaging.
Do not enter BACKLOG #44 specimen coverage.
Do not enter BACKLOG #46 disabled ripple host hygiene except as a routing note.
Do not create WordPress custom blocks or plugin runtime.
```

## Route Buckets

### Route A - Menu Consumer Closure, Provider Unchanged

Implement `menu/` as a dedicated consumer module that uses the existing
`popover/` public contract unchanged.

Expected Phase 2 shape:

```txt
modules/menu/lab-menu.css
modules/menu/lab-menu.js          optional, only for Menu-owned semantics
modules/menu/lab-menu-pattern.html
modules/menu/docs/MENU-SPEC-AUDIT.md
modules/menu/docs/MENU-MEASUREMENT-AUDIT.md
modules/menu/docs/MENU-RUNTIME-AUDIT.md
modules/menu/docs/MENU-WP-MAPPING.md
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-2-REPORT.md
```

This is the preferred route if Phase 1 proves the existing provider contract is
sufficient.

### Route B - Menu Static / Semantics First, Runtime Deferred

Add Menu component docs and static pattern evidence, but explicitly defer live
popover consumption because provider integration needs more design.

This route is acceptable only if Phase 1 proves live consumer closure would
hide a provider contract gap.

### Route C - Provider Contract Gap, Return To Review

Stop before implementation if Menu cannot close without a `popover/` change.
Write a Phase 1 report that identifies the missing provider capability and
requests a re-gate.

No provider patch is authorized by this Phase 0.

### Route D - Audit-Only / No-Code Cycle

Write a deeper Menu/popover contract audit only, leaving implementation for a
later cycle.

This is a fallback if the contract surface is too ambiguous for safe Phase 2.

### Route E - Hygiene Side-Route Only

Reject for this cycle unless Phase 1 proves Menu is blocked and the only safe
work is a small hygiene decision such as BACKLOG #46.

BACKLOG #46 is not the primary route.

### Route F - Other

Escape hatch for evidence not anticipated in Phase 0. Must name the evidence
and return for review before implementation.

## Phase Plan

### Phase 0 - Plan

This document.

Exit criteria:

```txt
selected candidate named
NEXT-SESSION.md §0 reading order reflected
route buckets defined
locks and fences stated
Phase 1 diagnostic tasks listed
no implementation files edited
Opus/user review GO received
```

### Phase 1 - Diagnostic Inventory / Route Selection

Required tasks:

1. Reconfirm local status and absence of `modules/menu/`.
2. Inventory `components.css §Menu` selector surface.
3. Inventory `style-guide.html #components-menu` static specimens.
4. Inventory `popover/` public contract and current pattern-page behavior.
5. Identify what belongs to Menu vs what belongs to `popover/`.
6. Decide whether Menu requires any component-local JS beyond using
   `window.labPopover.init()`.
7. Decide whether Menu items should include `data-ax-ripple="bounded"` in this
   cycle, given BACKLOG #46's disabled-host hygiene remains separate.
8. Map Menu's required states:
   - hover
   - focus-visible
   - pressed
   - selected
   - disabled
   - open surface
   - closed surface
9. Map Menu's required structures:
   - section label
   - divider
   - leading icon
   - trailing shortcut
   - supporting text
   - destructive item if present
   - submenu placeholder or explicit defer
10. Validate that Phase 2 can avoid:
    - `components.css` edits
    - `popover/` edits
    - `ripple/` edits
    - `icon-system/` edits
11. Select Route A/B/C/D/E/F.

Phase 1 report expected:

```txt
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-1-REPORT.md
```

No implementation files may be edited in Phase 1.

### Phase 2 - Implementation Or No-Code Route Report

If Route A is approved after Phase 1 review, expected write scope:

```txt
products/reference-implementations/axismundi-lab/modules/menu/lab-menu.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu.js
products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-RUNTIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-WP-MAPPING.md
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-2-REPORT.md
```

`lab-menu.js` is optional in Phase 2. It should exist only if Menu-owned
semantics need consumer-local behavior that `popover/` must not absorb.

If Route B/C/D is selected, Phase 2 may be a no-code report instead.

### Phase 3 - Visual / Interaction QA

Expected QA:

```txt
desktop viewport
mobile viewport 390px
light mode
dark mode
console/page errors 0
horizontal overflow 0
trigger open
Escape close
outside pointerdown close
focus restoration
ArrowUp / ArrowDown / Home / End if menu runtime owns these in the final route
selected state
disabled state
divider / section label / shortcut text visual checks
bounded ripple count and disabled-ripple behavior if ripple is included
provider unchanged verification
```

Global portal / overlay smoke from `AGENTS.md` applies because Menu uses an
overlay/anchored surface:

```txt
trigger exists
runtime handler attaches
host / portal element exists
open and visible state works
close / dismiss path works
console and page errors are absent
```

### Phase 5 - Close

Expected close artifacts:

```txt
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

`AGENTS.md` / `CLAUDE.md` are not expected to change unless review explicitly
approves a new lock. Lock 5 promotion is not expected in v3.6.9.

## Expected Files Not To Change

```txt
AGENTS.md
CLAUDE.md
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/fixtures/*
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
products/reference-implementations/axismundi-lab/modules/app-bar/*
products/reference-implementations/axismundi-lab/modules/nav-bar/*
products/reference-implementations/axismundi-lab/modules/nav-rail/*
products/reference-implementations/axismundi-lab/modules/tabs/*
styleguide/* by manual edit
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
```

If Phase 1 finds that one of these must change, stop and return to review.

## Expected Phase 2 Selector Rules

Allowed lab scope:

```txt
.lab-menu-demo
```

Allowed consumer selectors:

```txt
.lab-menu-demo .ax-menu
.lab-menu-demo .ax-menu__item
.lab-menu-demo [data-popover-trigger]
```

Forbidden in v3.6.9 Phase 2:

```txt
unscoped .ax-menu overrides that alter the baseline primitive
unscoped .ax-menu__item overrides
unscoped [data-popover-trigger] overrides
unscoped [data-ax-ripple] overrides
unscoped .material-symbols-rounded overrides
provider-specific branches inside popover/
provider-specific branches inside ripple/
provider-specific branches inside icon-system/
```

## Validation Strategy

Standard validation:

```powershell
wp-env run cli wp core version
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
npm run publish:styleguide
git diff --check
```

Menu-specific QA should use a fresh Playwright context against:

```txt
file:///C:/Users/thaum/dev/axismundi/products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
```

Expected values should be numeric where possible:

```txt
console errors: 0
overflow at 390px: 0
trigger count
menu surface count
open menu count before/after trigger
aria-expanded before/after open/close
focused element before/open/close
ripple host count
ripple created count
disabled item ripple created count
```

## Risk Analysis

### R1 - Menu Absorbs Popover Or Popover Absorbs Menu

Risk:

```txt
Menu may reimplement positioning/dismissal or popover may gain menu-item logic.
```

Mitigation:

```txt
Phase 1 must map Menu-owned vs provider-owned responsibilities before Phase 2.
Route C returns to review if a provider gap is discovered.
```

### R2 - Provider Mutation By Convenience

Risk:

```txt
Small edits to lab-popover.js may look attractive during Menu implementation.
```

Mitigation:

```txt
modules/popover/* is fenced. Any provider patch requires Phase 1 re-gate.
```

### R3 - Baseline Primitive Drift

Risk:

```txt
Menu lab CSS may accidentally redefine .ax-menu baseline properties.
```

Mitigation:

```txt
components.css entire file is fenced. Lab CSS must stay under .lab-menu-demo.
```

### R4 - Fake Controls

Risk:

```txt
Visible menu triggers or menu items may render but not do anything.
```

Mitigation:

```txt
FEEDBACK-AND-STRATEGY.md §5 applies: visible control must map to real runtime
behavior. Static specimens must be clearly static; interactive specimens must
be wired and verified.
```

### R5 - Ripple Hygiene Bleeds Into #46

Risk:

```txt
Menu may try to solve disabled ripple host authoring hygiene from BACKLOG #46.
```

Mitigation:

```txt
Treat #46 as separate. If Menu includes disabled items, Phase 1 must decide
whether those disabled items carry data-ax-ripple and record the rationale,
without changing the ripple provider.
```

### R6 - Icon-System Drift

Risk:

```txt
Menu leading/trailing icons may trigger global Material Symbols policy edits.
```

Mitigation:

```txt
Use existing icon markup policy only. Do not enter BACKLOG #14.
```

### R7 - Lock 5 Premature Promotion

Risk:

```txt
v3.6.9 may be tempting as another diagnostic-first success and push AGENTS.md /
CLAUDE.md edits.
```

Mitigation:

```txt
Do not promote diagnostic-first to Lock 5 in Phase 0. Phase 5 may record the
methodology finding, but AGENTS.md / CLAUDE.md remain fenced unless review
explicitly approves promotion.
```

### R8 - Publish / Validator Artifact Churn

Risk:

```txt
npm test and publish:styleguide may rewrite generated files.
```

Mitigation:

```txt
Restore validator-generated reports and publish mirror artifacts after
validation unless Phase 2 review explicitly expands write scope.
```

## Phase 0 Exit Criteria

```txt
Selected candidate: BACKLOG #45 Wave 2A-2 Menu
Route buckets A-F defined
Locks 1-4 quoted
Lock 5 defer stated
Provider and baseline fences stated
Phase 1 diagnostic tasks defined
Validation strategy defined
Implementation files edited: no
Ready for Phase 0 review
```

## Next

Submit this Phase 0 plan for review.

Do not edit implementation files before Phase 0 review gives Phase 1 GO.
