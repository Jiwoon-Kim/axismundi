# v3.6.9 - Wave 2A-2 Menu / Popover Consumer - Phase 1 Report

Status: Phase 1 diagnostic inventory and route selection.

Candidate:

```txt
BACKLOG #45 - Wave 2A-2 Menu / popover consumer closure
```

Verdict:

```txt
GO for Route A - Menu Consumer Closure, Provider Unchanged.
```

Phase 1 performed diagnosis only. No implementation files were edited.

## Phase 0 Review Carry-Forward

### P3-1 - Path String Disambiguation

The Phase 0 plan's intent was to fence the Pilot bridge source/asset pairs.
The path strings for those four files were inaccurate. The correct HEAD paths
are:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

Phase 1 keeps the same fence intent: all four files are out of scope and must
remain unchanged in v3.6.9.

### P3-2 - Baseline Timing

Phase 0 recorded the entry baseline as:

```txt
local HEAD: 6df49c4
status:     main...origin/main [ahead 15], clean at Phase 0 entry
```

That is correct for plan authoring time. After the Phase 0 plan commit,
Phase 1 starts from:

```txt
local HEAD: 675c572
status:     main...origin/main [ahead 16], clean at Phase 1 entry
```

### P3-3 - Submenu Scope Decision

Decision:

```txt
Defer interactive submenu in v3.6.9.
```

Reason:

Submenu is explicitly part of Menu's semantic surface, but interactive nested
anchored surfaces would test a new provider shape: multiple related open
surfaces, parent-child dismissal, pointer corridor / hover intent, focus
containment across nested surfaces, and viewport collision for a secondary
surface. The existing `popover/` contract is single-open by design. Adding
interactive submenu in this cycle would either require a provider change or
encourage Menu to reimplement anchored-surface behavior, both of which violate
the Phase 0 boundary.

Phase 2 may include a static or documented "submenu deferred" specimen only if
it does not imply runtime support. No nested popover behavior is in scope.

## Source Inputs

Phase 1 used the Phase 0 reading set plus the focused local files below.

```txt
NEXT-SESSION.md §0 reading order
BACKLOG.md #45 / #46 / #41 / #44
CURRENT-STATE.md
ROADMAP.md
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-0-PLAN.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/popover/lab-popover.js
products/reference-implementations/axismundi-lab/modules/popover/lab-popover.css
products/reference-implementations/axismundi-lab/modules/popover/lab-popover-pattern.html
products/reference-implementations/axismundi-lab/modules/popover/docs/POPOVER-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

## Current Local State

```txt
branch/status: main...origin/main [ahead 16]
modules/menu:  absent
Phase 1 mode:  read-only diagnostic
```

Existing lab modules include:

```txt
app-bar
nav-bar
nav-rail
popover
ripple
tabs
icon-system
...
```

`modules/menu/` is not present, so Phase 2 would add a new lab module rather
than alter an existing one.

## Index / Path Notes

As in v3.6.8, component index numbers in this cycle refer to the
`docs/v3.5.0/COMPONENT-COVERAGE-MAP.md` / `MODULE-STATUS-MATRIX.md` component
indices, not BACKLOG.md item numbers.

```txt
Menu #15 = Component-Coverage / Module-Status index.
BACKLOG #45 = the current v3.6.9 backlog item.
```

The canonical lab `components.css` path is:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
```

## Existing Baseline Primitive

`components.css` already contains a Menu visual primitive under Chunk E3.
The important selectors present in HEAD are:

```txt
.ax-menu
.ax-menu.is-open
.ax-menu__section-label
.ax-menu__divider
.ax-menu__item
.ax-menu__item:where(a)
.ax-menu__item::before
.ax-menu__item:hover::before
.ax-menu__item:focus-visible::before
.ax-menu__item:active::before
.ax-menu__item:focus-visible
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

This confirms that Phase 2 does not need baseline Menu styling. The existing
primitive covers the visual surface, state layer, focus indicator, selected
state, disabled state, dividers, labels, supporting text, and leading/trailing
slots.

Phase 2 must not edit `components.css`. Any new CSS must be lab-page layout
only, scoped under:

```txt
.lab-menu-demo
```

## Existing Styleguide Specimens

`style-guide.html` already includes:

```txt
#components-menu
```

Observed static specimens:

```txt
standard open menu
menu with divider
menu with section label
leading icons
trailing shortcut text
selected item
disabled item
two-line/supporting text items
```

The styleguide specimens are static `.ax-menu.is-open` demonstrations. They do
not close Menu because they do not exercise the live consumer/provider
relationship with `popover/`.

Phase 2 should not manually edit the styleguide. If a publish step emits mirror
files, those artifacts must be restored unless Phase 5 explicitly owns a docs
or publish-surface update.

## Existing Popover Provider Contract

`modules/popover/lab-popover.js` exposes:

```txt
window.labPopover.init(root?)
window.labPopover.close()
window.labPopover.isOpen
window.labPopover.openMenuId
```

The provider wires triggers by:

```txt
[data-popover-trigger]
aria-controls="<menu-id>"
```

The provider owns:

```txt
anchor positioning
single-open state
open/close visual state
aria-expanded sync on the trigger
aria-controls fallback enforcement
role="menu" fallback enforcement on the surface
focus movement to first non-disabled item on open
outside pointerdown dismissal
Escape dismissal
focus restoration
viewport repositioning on resize/scroll
forbidden-ancestor bail-out for .prose and [contenteditable]
menu-local ArrowUp / ArrowDown / Home / End / Tab behavior while open
item-click close after activation
```

This is sufficient for Menu's first consumer closure without modifying
`popover/`.

## Menu vs Popover Responsibility Map

Menu owns:

```txt
role=menu / role=menuitem authoring
item density and slots
leading icons
shortcut/trailing text
supporting text
selected state markup
disabled state markup
section labels
dividers
destructive item semantics / tone, if represented
consumer decision to opt into bounded ripple
static submenu placeholder or explicit submenu defer note
```

Popover owns:

```txt
trigger wiring
anchor positioning
surface open/close class
aria-expanded synchronization
outside-click dismiss
Escape dismiss
focus restoration
viewport collision / repositioning
open-scoped document listeners
forbidden-ancestor bail-out
```

Boundary conclusion:

```txt
Menu can consume popover as-is.
Popover must not gain menu-item logic.
Menu must not reimplement anchored positioning or dismissal.
```

## Runtime Need

Decision:

```txt
No lab-menu.js is required for Route A Phase 2.
```

Reason:

The currently required live behaviors are already provider-owned:

```txt
open / close / outside dismiss / Escape dismiss / focus restoration
ArrowUp / ArrowDown / Home / End within an open menu
Tab dismiss
click item -> close after activation
```

The remaining Menu-owned responsibilities can be represented by HTML semantics
and static authoring:

```txt
selected item: aria-selected="true" and/or .is-selected
disabled item: disabled on button items, or aria-disabled="true" where needed
section labels: .ax-menu__section-label
dividers: .ax-menu__divider
leading/trailing/supporting slots: existing .ax-menu__item-* structure
```

If Phase 2 discovers a need for consumer-local selection toggling or submenu
runtime, that is not Route A anymore. It should stop and return to review as
Route C or a narrower follow-up.

## Ripple Decision

Decision:

```txt
Use data-ax-ripple="bounded" on enabled Menu item hosts.
Do not add data-ax-ripple to disabled Menu item hosts in v3.6.9.
```

Reason:

`MODULE-STATUS-MATRIX.md` and `RIPPLE-V2-AUDIT.md` classify Menu #15 as a
TARGET bounded ripple consumer. The provider already supports disabled and
`aria-disabled` no-ripple behavior, but BACKLOG #46 exists because disabled
hosts with `data-ax-ripple` are an authoring hygiene question. This cycle does
not need to reopen that hygiene decision. It can avoid the ambiguity by placing
bounded ripple only on enabled item hosts.

This preserves:

```txt
Menu #15 TARGET bounded
BACKLOG #46 remains open as disabled host authoring hygiene
ripple/ provider unchanged
```

## Icon-System Decision

Decision:

```txt
Use existing Material Symbols authoring policy.
Do not edit icon-system/.
```

Menu pattern markup should use:

```html
<span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">...</span>
```

This matches v3.6.8 Navigation Core and keeps BACKLOG #14 out of scope.

## State Map

| State | Existing primitive / provider | Phase 2 expectation |
|---|---|---|
| closed | `.ax-menu` hidden by default | initial surface closed |
| open | `.ax-menu.is-open` visible | provider toggles `is-open` |
| hover | `.ax-menu__item:hover::before` | inherited from baseline |
| focus-visible | `.ax-menu__item:focus-visible` + `::before` | inherited from baseline, keyboard checked in Phase 3 |
| pressed | `.ax-menu__item:active::before` | inherited from baseline |
| selected | `.is-selected` / `[aria-selected="true"]` | author selected item specimen |
| disabled | `:disabled` / `[aria-disabled="true"]` | author disabled item specimen, no ripple attribute |

No state requires `components.css` edits.

## Structure Map

| Structure | Existing support | v3.6.9 decision |
|---|---|---|
| section label | `.ax-menu__section-label` | include |
| divider | `.ax-menu__divider` | include |
| leading icon | `.ax-menu__item-leading` | include |
| trailing shortcut | `.ax-menu__item-trailing-text` | include |
| supporting text | `.ax-menu__item-supporting` inside label | include |
| selected item | `.is-selected` / `aria-selected` | include |
| disabled item | `disabled` / `aria-disabled` | include |
| destructive item | baseline can represent by icon/label; no special token route | include only if no new color route is required |
| submenu | listed in Menu semantic surface | defer interactive submenu |

## Route Selection

### Route A - Menu Consumer Closure, Provider Unchanged

Decision:

```txt
SELECTED
```

Evidence:

```txt
modules/menu/ is absent, so a new module can be added cleanly.
components.css already contains Menu visual primitive and states.
style-guide.html already contains static Menu specimens.
popover/ already provides the runtime needed for first live Menu closure.
Menu #15 is a bounded ripple TARGET consumer.
icon-system policy is already sufficient.
No provider, baseline, token, WordPress, or bridge file needs to change.
```

Expected Phase 2 shape:

```txt
products/reference-implementations/axismundi-lab/modules/menu/lab-menu.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-RUNTIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-WP-MAPPING.md
docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-2-REPORT.md
```

`lab-menu.js` is not expected under Route A. If Phase 2 needs it, the report
must justify that the behavior is Menu-owned and does not duplicate `popover/`.

### Route B - Menu Static / Semantics First, Runtime Deferred

Decision:

```txt
REJECTED
```

Reason:

The whole point of BACKLOG #45 is the Menu/popover consumer closure. A static
Menu-only artifact would duplicate the styleguide's existing static Menu
specimens and would not test the DISTINCT but COUPLED boundary.

### Route C - Provider Contract Gap, Return To Review

Decision:

```txt
NOT SELECTED, BUT ACTIVE STOP CONDITION
```

Reason:

No provider gap is currently required for first Menu closure. However, Route C
must trigger if Phase 2 discovers a need for:

```txt
popover/* edits
nested submenu runtime
multi-open parent/child surfaces
consumer-specific provider branches
new viewport collision behavior
new document-level listener behavior
```

If any of those appears, implementation must stop and return to review.

### Route D - Audit-Only / No-Code

Decision:

```txt
REJECTED
```

Reason:

Phase 1 has enough evidence for a bounded implementation route. Deferring to an
audit-only cycle would leave the v3.6.8 Wave 2A split incomplete without
reducing meaningful risk.

### Route E - Hygiene Side-Route Only

Decision:

```txt
REJECTED
```

Reason:

BACKLOG #46 is real, but v3.6.9 does not need to solve disabled ripple host
hygiene to close Menu. Phase 2 can avoid adding ripple attributes to disabled
Menu items and keep #46 open.

### Route F - Other

Decision:

```txt
NO EVIDENCE
```

## Files Not Expected To Change

Phase 2 Route A must not edit:

```txt
AGENTS.md
CLAUDE.md
theme.json
functions.php
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
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
products/reference-implementations/axismundi-lab/style-guide.html
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
```

Manual styleguide edits remain out of scope. `publish:styleguide` may be run
for verification, but any generated publish artifacts must be restored unless a
later reviewed phase explicitly owns them.

## Phase 2 Constraints

Allowed:

```txt
.lab-menu-demo
.lab-menu-demo .ax-menu
.lab-menu-demo .ax-menu__item
.lab-menu-demo [data-popover-trigger]
```

Forbidden:

```txt
unscoped .ax-menu overrides
unscoped .ax-menu__item overrides
unscoped [data-popover-trigger] overrides
unscoped [data-ax-ripple] overrides
unscoped .material-symbols-rounded overrides
provider-specific branches inside popover/
provider-specific branches inside ripple/
provider-specific branches inside icon-system/
```

Pattern HTML should load the existing providers:

```txt
../popover/lab-popover.js
../ripple/lab-ripple.js
```

It should also load the same design-system CSS stack used by neighboring module
pattern pages.

## Phase 3 Expected QA

Phase 3 should verify at minimum:

```txt
desktop light / desktop dark / mobile light / mobile dark
console errors: 0
horizontal overflow at 390px: 0
trigger count
menu surface count
initial open menu count: 0
open count after trigger click: 1
aria-expanded true after open
first enabled menu item receives focus after open
ArrowDown / ArrowUp / Home / End navigation
Escape closes and restores focus to trigger
outside pointerdown closes
item click closes after activation
forbidden ancestor trigger does not open
enabled item bounded ripple creates .ax-ripple
disabled item does not carry data-ax-ripple
disabled item does not create .ax-ripple
submenu marked deferred, not interactive
```

## Lock / Scope Check

Lock 1:

```txt
Preserved. No WordPress or wp-custom source changes are needed.
```

Lock 2:

```txt
Preserved. No token files or md-sys/md-ref mappings are touched.
```

Lock 3:

```txt
Preserved. core/button semantic routing is unrelated and not reopened.
```

Lock 4:

```txt
Preserved. Menu/popover is handled through explicit DISTINCT but COUPLED
routing instead of collapsing provider and consumer semantics.
```

Lock 5:

```txt
Not promoted. Diagnostic-first remains a methodology finding, not a lock.
AGENTS.md and CLAUDE.md remain out of scope.
```

## Commands / Checks

Phase 1 diagnostic checks used:

```txt
git status --short --branch
Get-ChildItem products/reference-implementations/axismundi-lab/modules
Select-String / rg over components.css, style-guide.html, MODULE-STATUS-MATRIX,
  COMPONENT-COVERAGE-MAP, PROMOTION-CRITERIA, POPOVER-AUDIT.md,
  lab-popover.js, lab-popover-pattern.html, RIPPLE-V2-AUDIT.md
Get-ChildItem over the corrected Pilot bridge source/asset paths
```

No validation command generated tracked artifacts in Phase 1.

## Next

Submit this Phase 1 report for review.

If approved, Phase 2 may implement Route A with the write scope listed above.
Implementation must stop and return to review if it requires edits to
`popover/`, `ripple/`, `icon-system/`, `components.css`, WordPress files, Pilot
bridge/fixtures, or the prior v3.6.8 Navigation Core modules.
