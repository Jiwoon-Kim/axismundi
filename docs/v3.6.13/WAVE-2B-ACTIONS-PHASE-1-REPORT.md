# v3.6.13 Wave 2B-4 Actions Consumers - Phase 1 Diagnostic Inventory

Date: 2026-05-22

Phase: 1 - Diagnostic Inventory

## Verdict

Recommended route:

```txt
Route A - Full Wave 2B-4 Actions Consumer Closure
```

Implement:

```txt
FAB menu #5
Split button #7
Toolbar #8
```

Reason:

- All three rows have existing baseline primitives and styleguide anchors.
- The missing target module directories are cleanly addable.
- The only factual popover consumer needed for this cycle is Split button's
  trailing menu surface.
- FAB menu can close as a self-contained expanded action set without forcing
  `popover/` provider migration.
- Toolbar can close with a lab-scoped runtime and without using global
  `theme.js`.
- `components.css`, `style-guide.html`, `scripts/style-guide.js`,
  `scripts/theme.js`, `popover/`, `ripple/`, and `icon-system/` can remain
  unchanged.

Implementation files remain untouched in Phase 1.

## Lock 5 Compliance

No safe-shortcut exception was used.

Lock 5 required diagnostic elements:

```txt
source inputs:       v3.6.13 Phase 0 plan + focused local reads below
baseline boundaries: Toolbar §29, FAB menu Chunk H1, Split button Chunk H2
provider boundaries: popover/ factual only for Split button; ripple/icon declarative only
semantic boundaries: FAB menu / Split button / Toolbar kept distinct
route buckets:       A/B/C/D/E/F evaluated
selected route:      Route A
rejected routes:     B/C/D/E/F rejected with evidence
write scope:         22 files, listed below
fences:              no-touch files listed below
validation plan:     Phase 3 action-consumer QA + standard commands
```

## Current Local State

Phase 1 entry status:

```txt
main == origin/main at 459419b
last close: v3.6.12 Wave 2B-3 DateTime
Phase 0 plan: docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-0-PLAN.md
```

Observed mount staleness:

```txt
Some date-time/ files may appear as M on the mounted view in reviewer tools.
HEAD blob first-line matches working tree first-line and local git status is
treated as clean. This is the known mount staleness pattern.
```

## Source Inputs

Primary:

```txt
NEXT-SESSION.md §0 reading order
docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-0-PLAN.md
docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-5-CLOSE.md
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-5-CLOSE.md
docs/v3.6.10/WAVE-2B-FORM-PHASE-1-REPORT.md
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-5-CLOSE.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
```

Focused local reads:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/modules/
products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-WP-MAPPING.md
products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/button-group/docs/BUTTON-GROUP-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/button-group/docs/BUTTON-GROUP-WP-MAPPING.md
products/reference-implementations/axismundi-lab/modules/menu/
products/reference-implementations/axismundi-lab/modules/popover/
products/reference-implementations/axismundi-lab/modules/ripple/
products/reference-implementations/axismundi-lab/modules/icon-system/
```

## Phase 0 Review Notes Absorbed

### P3-1 - Per-Row Ripple State Matrix

Decision matrix:

| Row / host | Decision | Evidence |
|---|---|---|
| FAB menu close button | TARGET unbounded | It is a 56px circular FAB-like action surface; FAB family #3/#4 already uses unbounded ripple after v3.5.6. |
| FAB menu action rows | TARGET bounded | They are pill/rectangular action rows; bounded matches Menu/List item geometry and prevents oversized circular bleed. |
| FAB menu disabled action rows | no `data-ax-ripple` | Disabled-host hygiene remains BACKLOG #46; do not silently attach animated ripple. |
| Split button primary segment | TARGET bounded | It is an `.ax-button` segment; Button #1 TARGET bounded. |
| Split button trailing chevron segment | TARGET bounded | It is the popover trigger segment and visually rectangular/pill-bounded. |
| Split button disabled segments | no `data-ax-ripple` | Keep #46 separate. |
| Toolbar icon buttons | TARGET unbounded | They compose Icon button #2 inside `.ax-toolbar`; Icon button TARGET unbounded. |
| Toolbar text/button slots, if included | TARGET bounded | Button #1 semantics if a text button specimen is included. |
| Toolbar spacer/separator slots | NONE | Non-interactive. |

This promotes the three rows from CANDIDATE to explicit per-host TARGET only in
the v3.6.13 consumer context. Provider files remain unchanged.

### P3-2 - FAB Menu Role Decision

Decision:

```txt
Use native button-list semantics.
Do not use role=menu/menuitem.
Do not consume popover/ for FAB menu in v3.6.13.
```

Evidence:

- `components.css` Chunk H1 defines `<ul class="ax-fab-menu__list">` and
  `<button class="ax-fab-menu__item-button">`.
- `style-guide.html #components-fab-menu` uses native buttons inside list
  items, not `role="menu"`.
- M3 FAB menu is an expanded action set, not the WAI-ARIA Menu Button pattern.
- Reusing Menu #15 semantics would bring menu-specific arrow/Home/End behavior
  and popover menu-item assumptions that the FAB menu baseline does not claim.

FAB menu owns:

```txt
expanded/collapsed state
close/main FAB action
action item labels
action item activation
disabled action authoring
icon slots
```

It does not own:

```txt
popover anchor positioning
menuitem keyboard model
submenu behavior
```

### P3-3 - Toolbar / theme.js Collision Avoidance

Decision:

```txt
Add lab-toolbar.js as a lab-scoped runtime.
Do not load scripts/theme.js in lab-toolbar-pattern.html.
Do not edit scripts/theme.js.
```

Evidence:

- `scripts/theme.js §3` attaches a document-level click listener to
  `[role="toolbar"] [aria-pressed]`.
- Module pattern pages should not depend on styleguide-global runtime.
- Visible toolbar toggles must be real controls, so static-only pressed
  specimens are insufficient for the interactive Toolbar closure.

Allowed `lab-toolbar.js` contract:

```txt
scope: .lab-toolbar-demo only
owned behavior: click toggles aria-pressed + is-selected on enabled toolbar buttons
no document/window persistent listener except DOMContentLoaded once
no editor/plugin command behavior
```

### P3-4 - Split Button Markup Contract

Decision:

```html
<div class="ax-split-button" role="group" aria-label="Save actions">
  <button type="button" class="ax-button ...">Save</button>
  <button type="button"
          class="ax-button ..."
          aria-label="More save options"
          aria-haspopup="menu"
          aria-expanded="false"
          aria-controls="split-save-menu"
          data-popover-trigger>
    <span class="material-symbols-rounded notranslate ax-split-button__trailing-icon"
          translate="no" aria-hidden="true" draggable="false">arrow_drop_down</span>
  </button>
</div>
```

Rationale:

- Parent `role="group"` matches styleguide precedent and labels the paired
  controls.
- Primary segment is a real native button and does not carry
  `aria-haspopup`.
- Trailing segment alone opens the menu and consumes `popover/`.
- Focus order is normal DOM order; no roving tabindex.
- The opened surface may use `.ax-menu` / `role="menu"` because the trailing
  control opens a Menu-like option set. Popover owns positioning/dismissal;
  Split button owns primary-vs-menu semantics.

Phase 2 should add a small `lab-split-button.js` only for lab-local primary
action status output. It must not replace popover behavior.

### P3-5 - BACKLOG #41/#44/#46/#47 Stay-Out Verification

Decision:

```txt
#41 not entered: no WordPress shared ripple runtime packaging.
#44 not entered: no specimen-wall residual coverage/validator polish.
#46 not entered: disabled ripple host authoring is local only; no provider hygiene.
#47 not entered: popover provider menu-item-class extraction remains future hygiene.
```

Provider edit need:

```txt
popover/: no
ripple/: no
icon-system/: no
```

If Phase 2 discovers otherwise, stop and return for review.

## Module Presence Inventory

Existing dependencies:

```txt
button/
button-group/
fab/
menu/
popover/
ripple/
icon-system/
```

Missing target modules:

```txt
fab-menu/
split-button/
toolbar/
```

Implication:

All three target directories can be created cleanly in Phase 2.

## Baseline CSS Inventory

### Toolbar #8

Evidence:

```txt
components.css formal §29 Toolbar
marker: line 5004
selector range: lines 5020-5077
```

Selector surface:

```txt
.ax-toolbar
.ax-toolbar.ax-toolbar--docked
.ax-toolbar.ax-toolbar--floating
.ax-toolbar.ax-toolbar--vibrant
.ax-toolbar .ax-icon-button.is-standard
.ax-toolbar .ax-icon-button.is-selected
.ax-toolbar .ax-icon-button[aria-pressed="true"]
.ax-toolbar.ax-toolbar--vibrant .ax-icon-button.is-selected
.ax-toolbar.ax-toolbar--vibrant .ax-icon-button[aria-pressed="true"]
.ax-toolbar__spacer
```

Baseline states:

```txt
docked
floating
vibrant
selected / aria-pressed
disabled delegated to slot controls
```

### FAB Menu #5

Evidence:

```txt
components.css Chunk H1 - FAB menu
marker: line 5258
selector range: lines 5287-5522
```

Selector surface:

```txt
.ax-fab-menu
.ax-fab-menu.is-color-secondary
.ax-fab-menu.is-color-tertiary
.ax-fab-menu__close
.ax-fab-menu__close:hover
.ax-fab-menu__close::before
.ax-fab-menu__close:focus-visible
.ax-fab-menu__close-icon-rest
.ax-fab-menu__close-icon-open
.ax-fab-menu.is-open .ax-fab-menu__close-icon-rest
.ax-fab-menu.is-open .ax-fab-menu__close-icon-open
.ax-fab-menu__list
.ax-fab-menu.is-open .ax-fab-menu__list
.ax-fab-menu__item
.ax-fab-menu__item-button
.ax-fab-menu__item-button::before
.ax-fab-menu__item-button:focus-visible
.ax-fab-menu__item-icon
.ax-fab-menu__item-label
.ax-fab-menu__item-button:disabled
.ax-fab-menu__item-button[aria-disabled="true"]
```

Baseline states:

```txt
closed / open
primary / secondary / tertiary color sets
hover / focus-visible / pressed state layers
disabled items
```

### Split Button #7

Evidence:

```txt
components.css Chunk H2 - Split button
marker: line 5531
selector range: lines 5547-5607
```

Selector surface:

```txt
.ax-split-button
.ax-split-button .ax-button
.ax-split-button.is-size-l .ax-button
.ax-split-button.is-size-xl .ax-button
.ax-split-button .ax-button:first-child
.ax-split-button .ax-button:last-child
.ax-split-button .ax-button:hover
.ax-split-button .ax-button.is-selected
.ax-split-button .ax-button[aria-expanded="true"]
.ax-split-button__trailing-icon
.material-symbols-rounded.ax-split-button__trailing-icon
```

Baseline states:

```txt
default M
S shown in styleguide
L / XL CSS hooks
hover inner-corner morph
aria-expanded selected trailing segment
```

## Styleguide Precedent Inventory

### FAB Menu

Anchor:

```txt
#components-fab-menu
```

Specimens:

```txt
Primary open state
Secondary open state
Tertiary open state
Interactive toggle via JS
```

The interactive specimen uses `data-fab-menu-toggle`, but the runtime is
styleguide precedent only. It is not a module closure.

### Split Button

Anchor:

```txt
#components-split-button
```

Specimens:

```txt
Default M size, filled/tonal/outlined examples
Small size example
Trailing button carries aria-haspopup=menu and aria-expanded
```

The styleguide does not wire a live popover surface for Split button.

### Toolbar

Anchor:

```txt
#components-toolbar
```

Specimens:

```txt
Docked standard color
Floating standard color
Floating vibrant color
```

Toolbar specimens use:

```txt
role="toolbar"
aria-label
.ax-icon-button
aria-pressed for selected/toggle states
```

`theme.js §3` globally toggles any `[role="toolbar"] [aria-pressed]`. This
remains styleguide/global precedent, not a module runtime.

## Responsibility Maps

### FAB Menu

| Responsibility | Owner |
|---|---|
| FAB family dimensions/elevation precedent | existing `fab/` docs |
| `.ax-fab-menu*` visual primitive | `components.css` baseline, unchanged |
| expanded/collapsed state | `lab-fab-menu.js` local runtime |
| action item activation | `lab-fab-menu.js` local demo output |
| action list semantics | FAB menu module, native button list |
| anchored positioning/dismissal | not used in v3.6.13 |
| ripple attach | existing `ripple/` provider, declarative only |
| icon rendering | existing `icon-system/` policy, declarative only |

### Split Button

| Responsibility | Owner |
|---|---|
| `.ax-split-button*` visual primitive | `components.css` baseline, unchanged |
| primary action | Split button module, lab-local status output |
| trailing menu trigger | Split button module markup |
| menu surface semantics | Split button module via `.ax-menu` consumer markup |
| anchor positioning / dismiss / Escape / focus restore | existing `popover/` provider |
| ripple attach | existing `ripple/` provider, declarative only |
| chevron icon | existing `icon-system/` policy |

### Toolbar

| Responsibility | Owner |
|---|---|
| `.ax-toolbar*` visual primitive | `components.css` baseline, unchanged |
| `role=toolbar` / aria-label | Toolbar module markup |
| pressed-state toggle | `lab-toolbar.js` local runtime |
| selected visual hook | baseline `.ax-toolbar [aria-pressed=true]` + `.is-selected` |
| editor command semantics | out of scope / plugin territory |
| ripple attach | existing `ripple/` provider, declarative only |
| icon rendering | existing `icon-system/` policy |

## Runtime Need Decision

| Row | JS file | Reason |
|---|---|---|
| FAB menu | `lab-fab-menu.js` | Needed for real open/close, aria-expanded sync, Escape close, outside/sibling close, item activation output. Component-local only. |
| Split button | `lab-split-button.js` | Needed only for primary action status output and optional menu item activation output. Popover remains responsible for trailing menu open/close. |
| Toolbar | `lab-toolbar.js` | Needed for lab-scoped aria-pressed / is-selected toggling without relying on global `theme.js`. |

No JS file may attach persistent document/window listeners except a one-time
`DOMContentLoaded` setup. If outside-click behavior is required for FAB menu, it
must be scoped to the module root or avoided; do not create a global provider.

## Icon-System Decision

Use Material Symbols for:

```txt
FAB menu close/rest and open icons
FAB menu action icons
Split button trailing arrow_drop_down
Toolbar icon-button glyphs
```

Policy:

```html
class="material-symbols-rounded notranslate"
translate="no"
aria-hidden="true"
draggable="false"
```

No icon-system provider edits. No BACKLOG #14 work.

## Matrix Count Verification

After v3.6.12:

```txt
Date picker #22: DONE
Time picker #23: DONE
BACKLOG #19: closed
```

Expected current TOC component row counts:

```txt
DONE:    25
PARTIAL:  0
TODO:     6
RECORD:   3
```

Observed:

```txt
CURRENT-STATE.md Matrix Snapshot still shows DONE 24 / PARTIAL 1 / TODO 6 / RECORD 3.
docs/v3.5.0/MODULE-STATUS-MATRIX.md and COMPONENT-COVERAGE-MAP.md remain
historical framework docs and are not expected to be rewritten in this cycle.
```

Phase 5 should clean `CURRENT-STATE.md` snapshot counts if v3.6.13 proceeds to
close. Do not edit framework docs in Phase 2.

## Route Selection

### Route A - SELECTED

Full Wave 2B-4 Actions Consumer Closure.

Reasons:

1. All three target directories are absent and cleanly addable.
2. All three rows have baseline CSS primitives and styleguide anchors.
3. FAB menu can close self-contained; no popover migration is required.
4. Split button can consume existing `popover/` unchanged for trailing menu.
5. Toolbar can avoid global `theme.js` through lab-scoped runtime.
6. Ripple decisions are per-host and declarative; provider remains unchanged.
7. Icon usage follows existing Material Symbols policy.
8. No stop-and-return trigger is active.

### Route B - REJECTED

Toolbar first.

Reason:

Toolbar is provider-light, but Phase 1 found enough evidence to close the
popover-adjacent rows without provider edits. Splitting would defer useful
Actions closure without reducing real risk.

### Route C - REJECTED

FAB menu + Split button first.

Reason:

Toolbar collision with `theme.js` is resolved by omitting `theme.js` from the
pattern page and using `lab-toolbar.js`. It does not need a separate cycle.

### Route D - REJECTED

Provider/runtime boundary review.

Reason:

No provider edit, baseline edit, styleguide runtime edit, plugin edit, or new
shared infrastructure need was found.

### Route E - REJECTED

Audit/split only.

Reason:

The split naming pattern remains available if Phase 2 discovers unexpected
runtime coupling, but Phase 1 evidence supports a bounded implementation route.

### Route F - REJECTED

No alternate evidence-backed route.

## Phase 2 Write Scope

Expected Route A files: 22 files.

```txt
products/reference-implementations/axismundi-lab/modules/fab-menu/
  lab-fab-menu.css
  lab-fab-menu.js
  lab-fab-menu-pattern.html
  docs/FAB-MENU-SPEC-AUDIT.md
  docs/FAB-MENU-MEASUREMENT-AUDIT.md
  docs/FAB-MENU-RUNTIME-AUDIT.md
  docs/FAB-MENU-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/split-button/
  lab-split-button.css
  lab-split-button.js
  lab-split-button-pattern.html
  docs/SPLIT-BUTTON-SPEC-AUDIT.md
  docs/SPLIT-BUTTON-MEASUREMENT-AUDIT.md
  docs/SPLIT-BUTTON-RUNTIME-AUDIT.md
  docs/SPLIT-BUTTON-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/toolbar/
  lab-toolbar.css
  lab-toolbar.js
  lab-toolbar-pattern.html
  docs/TOOLBAR-SPEC-AUDIT.md
  docs/TOOLBAR-MEASUREMENT-AUDIT.md
  docs/TOOLBAR-RUNTIME-AUDIT.md
  docs/TOOLBAR-WP-MAPPING.md

docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-2-REPORT.md
```

## Phase 2 Constraints

CSS:

```txt
Only lab-scoped selectors:
  .lab-fab-menu-demo
  .lab-split-button-demo
  .lab-toolbar-demo

No unscoped:
  .ax-fab-menu
  .ax-split-button
  .ax-toolbar
  .ax-button
  .ax-menu
  [data-popover-trigger]
  [data-ax-ripple]
  .material-symbols-rounded
```

Runtime:

```txt
No provider edits.
No global namespace except optional fixture APIs:
  window.labFabMenu
  window.labSplitButton
  window.labToolbar
No document/window persistent listeners.
No shared action-menu provider.
No plugin/editor behavior.
```

Dependencies:

```txt
FAB menu pattern may load ripple and icon/font styles.
Split button pattern may load popover + ripple providers.
Toolbar pattern may load ripple provider.
Pattern pages must not load scripts/theme.js.
```

## Files Still Not Expected To Change

```txt
AGENTS.md
CLAUDE.md
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/fixtures/*
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
products/reference-implementations/axismundi-lab/modules/app-bar/*
products/reference-implementations/axismundi-lab/modules/nav-bar/*
products/reference-implementations/axismundi-lab/modules/nav-rail/*
products/reference-implementations/axismundi-lab/modules/tabs/*
products/reference-implementations/axismundi-lab/modules/menu/*
products/reference-implementations/axismundi-lab/modules/checkbox/*
products/reference-implementations/axismundi-lab/modules/radio/*
products/reference-implementations/axismundi-lab/modules/switch/*
products/reference-implementations/axismundi-lab/modules/dialog/*
products/reference-implementations/axismundi-lab/modules/sheet/*
products/reference-implementations/axismundi-lab/modules/date-time/*
products/reference-implementations/axismundi-lab/modules/button/*
products/reference-implementations/axismundi-lab/modules/button-group/*
products/reference-implementations/axismundi-lab/modules/fab/*
products/reference-implementations/axismundi-lab/modules/icon-button/*
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
```

## Stop-And-Return Conditions

Stop before implementation if Phase 2 needs to:

1. Edit `components.css` or `blocks.css`.
2. Edit `style-guide.html`, `scripts/style-guide.js`, or `scripts/theme.js`.
3. Edit `popover/`, `ripple/`, or `icon-system/`.
4. Add a new shared action-menu / command-menu / toolbar provider.
5. Edit WordPress/Pilot files or introduce plugin behavior.
6. Edit `AGENTS.md`, `CLAUDE.md`, or Lock 5 wording.
7. Reinterpret FAB menu / Split button / Toolbar as one generic Button group.
8. Reopen BACKLOG #47 popover provider hygiene.
9. Reopen BACKLOG #46 disabled ripple hygiene.

## Phase 3 QA Matrix

Visual:

```txt
3 modules x desktop/mobile x light/dark = 12 cells
console errors: 0
390px overflow: 0
token tuples unchanged
```

FAB menu:

```txt
collapsed -> open
aria-expanded sync
Escape close
item activation output
disabled item no activation
unbounded ripple on close button if approved
bounded ripple on enabled action rows
disabled rows no ripple
Material Symbols policy counts
```

Split button:

```txt
primary action output
trailing menu trigger opens popover
aria-expanded sync through popover provider
Escape close + focus restore
outside pointer close
menu item click close / activation
bounded ripple on primary + trailing
primary button does not carry aria-haspopup
```

Toolbar:

```txt
role=toolbar + aria-label
aria-pressed toggles on enabled controls
is-selected sync
disabled controls no toggle
lab-toolbar.js works without scripts/theme.js
unbounded ripple on icon buttons
non-interactive spacer has no ripple
```

Fences:

```txt
components.css unchanged
style-guide.html unchanged
scripts/style-guide.js unchanged
scripts/theme.js unchanged
popover/ripple/icon-system unchanged
AGENTS.md/CLAUDE.md unchanged
```

Standard validation:

```powershell
node --check products\reference-implementations\axismundi-lab\modules\fab-menu\lab-fab-menu.js
node --check products\reference-implementations\axismundi-lab\modules\split-button\lab-split-button.js
node --check products\reference-implementations\axismundi-lab\modules\toolbar\lab-toolbar.js
wp-env run cli wp core version
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
npm run publish:styleguide
git diff --check
```

## Lock Status

```txt
Lock 1: preserved; no wp-custom or WordPress source work.
Lock 2: preserved; no token mapping work.
Lock 3: not reopened; Button primary actions are lab-local, not core/button route changes.
Lock 4: preserved; FAB menu, Split button, Toolbar remain semantically distinct.
Lock 5: applied; Phase 1 diagnostic precedes Phase 2 implementation.
```

## Phase 2 Entry Recommendation

Proceed to Phase 2 Route A after review approval.

Phase 2 must create only the listed new module files and Phase 2 report. Any
need to edit a fenced file or provider activates stop-and-return.
