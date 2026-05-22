# v3.6.13 Wave 2B-4 Actions Consumers - Phase 0 Plan

Date: 2026-05-22

Phase: 0 - Plan

Status: Proposed, awaiting review

## Verdict

Recommended next diagnostic target:

```txt
Wave 2B-4 Actions consumers #5 / #7 / #8
```

Candidate rows:

```txt
FAB menu #5
Split button #7
Toolbar #8
```

This is a plan-first cycle. Do not implement in Phase 0.

Phase 1 must determine whether the three Actions rows can close together as
one bounded consumer slice or whether the popover-coupled rows need to split
from Toolbar.

## Current Baseline

```txt
local HEAD:      47f7190
origin/main:     47f7190
branch:          main
push status:     pushed before Phase 0
last close:      v3.6.12 Wave 2B-3 DateTime
WordPress core:  7.0 per v3.6.12 close evidence
```

v3.6.12 closed the first PARTIAL-to-DONE cycle and BACKLOG #19. This cycle is
the next Wave 2B slice and the third post-promotion Lock 5 self-application.

## Lock 5 Compliance

Lock 5 requires diagnosis before implementation for plan-first cycles where the
route, failure mode, or boundary risk is not already known.

This Phase 0 identifies:

```txt
source inputs:             listed below
baseline boundaries:       components.css Chunk H1/H2 and §29 Toolbar
provider boundaries:       popover/ ripple/ icon-system/ unchanged unless review returns
semantic boundaries:       FAB menu / Split button / Toolbar each own different semantics
route buckets:             A-F below
selected route:            Phase 1 chooses
rejected routes:           Phase 1 records with evidence
write scope:               conditional Phase 2 shapes below
fences:                    Files Not Expected To Change below
validation plan:           standard commands + Phase 3 action-consumer QA
```

No safe-shortcut exception is used.

## Source Inputs

Read according to `NEXT-SESSION.md §0` current order:

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #44 / #46 / #47 / #21 / #14
7-11. docs/v3.6.12/WAVE-2B-DATE-TIME-PHASE-{5,3,2,1,0}*
12-16. docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-{5,3,2,1,0}*
17-21. docs/v3.6.10/WAVE-2B-FORM-PHASE-{5,3,2,1,0}*
22-26. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-{5,3,2,1,0}*
27-31. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-{5,3,2,1,0}*
32-62. earlier v3.6.x docs and lesson sources
```

Focused Phase 0 reads:

```txt
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/
products/reference-implementations/axismundi-lab/modules/fab/
products/reference-implementations/axismundi-lab/modules/menu/
products/reference-implementations/axismundi-lab/modules/button/
products/reference-implementations/axismundi-lab/modules/button-group/
products/reference-implementations/axismundi-lab/modules/popover/
products/reference-implementations/axismundi-lab/modules/ripple/
products/reference-implementations/axismundi-lab/modules/icon-system/
```

## Candidate Definition

Wave 2B-4 is the remaining Actions consumer slice:

| Row | Component | Group | Status | Target module | Provider boundary |
|---:|---|---|---|---|---|
| 5 | FAB menu | Actions | TODO | `fab-menu/` | `popover/`, `ripple/`, `icon-system/` |
| 7 | Split button | Actions | TODO | `split-button/` | `popover/`, `ripple/` |
| 8 | Toolbar | Actions | TODO | `toolbar/` | `ripple/` |

This is not a blank domain. It is a provider-consumer composition cycle across
already closed Action primitives:

```txt
Button #1       DONE
Icon button #2  DONE
FAB #3+#4       DONE in fab/
Button group #6 DONE
Menu #15        DONE
popover/        DONE provider
ripple/         DONE provider
icon-system/    DONE provider
```

The main question is whether #5/#7/#8 can close in one Actions-consumer pass
without touching providers, baseline CSS, styleguide runtime, or prior modules.

## Existing Local Surface

Module directory state:

```txt
existing: fab/, menu/, button/, button-group/, popover/, ripple/, icon-system/
missing:  fab-menu/, split-button/, toolbar/
```

Baseline CSS evidence:

```txt
Toolbar:
  components.css Chunk G2 / formal §29
  .ax-toolbar
  .ax-toolbar--docked
  .ax-toolbar--floating
  .ax-toolbar--vibrant
  .ax-toolbar .ax-icon-button...
  .ax-toolbar__spacer

FAB menu:
  components.css Chunk H1
  .ax-fab-menu
  .ax-fab-menu.is-color-secondary
  .ax-fab-menu.is-color-tertiary
  .ax-fab-menu__close
  .ax-fab-menu__list
  .ax-fab-menu__item
  .ax-fab-menu__item-button
  .ax-fab-menu__item-icon
  .ax-fab-menu__item-label

Split button:
  components.css Chunk H2
  .ax-split-button
  .ax-split-button .ax-button
  .ax-split-button.is-size-l
  .ax-split-button.is-size-xl
  .ax-split-button__trailing-icon
```

Styleguide anchors exist:

```txt
#components-fab-menu
#components-split-button
#components-toolbar
```

Important styleguide precedent:

```txt
style-guide.html has FAB menu static and interactive examples.
style-guide.html has Split button static examples.
style-guide.html has Toolbar examples and notes that theme.js handles
[role="toolbar"] [aria-pressed] toggling globally.
```

These are source evidence only. v3.6.13 must not edit `style-guide.html`,
`scripts/style-guide.js`, or `scripts/theme.js`.

## Baseline Boundary

`components.css` already contains visual primitives for all three rows. Phase 2
must not edit baseline CSS unless Phase 1 returns for review.

Allowed Phase 2 lab CSS shape, if implementation proceeds:

```txt
.lab-fab-menu-demo ...
.lab-split-button-demo ...
.lab-toolbar-demo ...
```

Forbidden selector shapes:

```txt
.ax-fab-menu { ... }              unscoped baseline override
.ax-split-button { ... }          unscoped baseline override
.ax-toolbar { ... }               unscoped baseline override
[data-popover-trigger] { ... }    provider branch
[data-ax-ripple] { ... }          ripple provider branch
.material-symbols-rounded { ... } icon-system branch
```

## Provider Boundary

### popover/

`popover/` is a DONE anchored-surface provider. It owns:

```txt
anchor positioning
open/close state
outside pointer dismissal
Escape dismissal
focus restoration
viewport collision/repositioning
open-scoped document listeners
forbidden-ancestor bail-out
```

Potential consumers in this cycle:

```txt
Split button #7
FAB menu #5
```

Popover must not absorb:

```txt
FAB menu action semantics
FAB expansion semantics
Split button primary-action semantics
Toolbar command semantics
menu-item-specific behavior beyond the existing factual provider contract
```

### ripple/

`ripple/` is a DONE provider. Matrix currently lists FAB menu #5, Split button
#7, and Toolbar #8 as CANDIDATE, not TARGET.

Phase 1 must decide for each row:

```txt
FAB menu:     unbounded on close/main FAB? bounded on action rows? or static state-layer only?
Split button: bounded per segment? chevron only? primary + chevron?
Toolbar:      bounded per icon button? none for non-interactive separators?
```

Disabled surfaces must not silently acquire animated ripple. BACKLOG #46 stays
separate.

### icon-system/

`icon-system/` is a DONE provider. FAB menu and Toolbar are expected icon
consumers; Split button uses a trailing `arrow_drop_down` Material Symbol.

Phase 2 must use the existing policy:

```html
<span class="material-symbols-rounded notranslate"
      translate="no"
      aria-hidden="true"
      draggable="false">...</span>
```

Do not reopen BACKLOG #14 or edit icon-system provider files.

## Semantic Boundary

Each component owns its own meaning:

```txt
FAB menu:
  expanded FAB action set; depends on FAB family + Menu-like action list
  owns labels, action item semantics, expanded/collapsed state, disabled actions

Split button:
  primary action + trailing menu affordance
  owns primary-action activation vs menu-open semantics
  may consume popover/ for the trailing surface only

Toolbar:
  command container, role=toolbar, labelled command group
  owns pressed/selected command state and keyboard/focus behavior
  does not become Button group and does not become editor plugin UI
```

Do not collapse these into one generic "button row" patch.

## Current Known Edges

### Matrix Snapshot Count

`CURRENT-STATE.md` top-level state says v3.6.12 closed DateTime and BACKLOG #19,
but its Matrix Snapshot may still show the pre-close DONE/PARTIAL counts.

Phase 1 must verify current matrix counts against the canonical docs and record
whether Phase 5 should clean the snapshot.

### styleguide Toolbar Runtime

`style-guide.html` notes that `theme.js` handles all
`[role="toolbar"] [aria-pressed]` toggling globally.

Phase 1 must decide whether a `toolbar/` lab module needs local runtime or can
document existing browser/button behavior without relying on global styleguide
runtime. If visible toolbar toggles are included, they must be real controls.

### FAB Menu Existing Static Runtime

`style-guide.html` has an interactive FAB menu specimen. Phase 1 must determine
whether that behavior is styleguide-only precedent, reusable local consumer
runtime, or evidence that FAB menu needs a dedicated `lab-fab-menu.js`.

## Route Buckets

### Route A - Full Wave 2B-4 Actions Consumer Closure

Implement FAB menu #5, Split button #7, and Toolbar #8 together.

Preferred hypothesis if Phase 1 proves:

```txt
all three rows have bounded baseline coverage
popover/ can be consumed unchanged for FAB menu and Split button
ripple/icon-system can be consumed declaratively unchanged
Toolbar runtime is small and component-local, or no runtime is needed
style-guide/theme runtime remains precedent only
no provider/baseline/WordPress/plugin/lock-file stop trigger activates
```

### Route B - Toolbar First

Implement Toolbar #8 only.

Use if:

```txt
Toolbar is provider-light and can close safely
FAB menu / Split button need deeper popover-consumer review
```

This preserves progress while avoiding premature provider coupling.

### Route C - Popover Consumers First

Implement FAB menu #5 and Split button #7; defer Toolbar.

Use if:

```txt
FAB menu and Split button share enough popover/ripple/icon consumer evidence
Toolbar semantics are more editor/plugin-like than expected
```

Route C must still keep `popover/` unchanged.

### Route D - Provider Or Runtime Boundary Review

No Phase 2 implementation.

Use if Phase 1 finds:

```txt
popover/ provider edits are required
ripple/ provider edits are required
icon-system/ provider edits are required
new shared command/menu/toolbar infrastructure is required
style-guide.js or theme.js must be edited
```

Route D returns for review before any patch.

### Route E - Audit/Split Only

Use if Wave 2B-4 is too broad and should split into named follow-ons:

```txt
Wave 2B-4a Toolbar
Wave 2B-4b Split button
Wave 2B-4c FAB menu
```

No implementation in Phase 2 under this route unless review approves a smaller
slice.

### Route F - Other Evidence-Backed Route

Allowed only with concrete Phase 1 evidence and explicit review approval.

## Phase 1 Inventory Tasks

Phase 1 must complete:

1. Reconfirm `modules/fab-menu/`, `modules/split-button/`, and
   `modules/toolbar/` are absent.
2. Inventory `components.css` Toolbar §29 selector surface and current line
   range.
3. Inventory `components.css` FAB menu Chunk H1 selector surface and current
   line range.
4. Inventory `components.css` Split button Chunk H2 selector surface and
   current line range.
5. Inventory `style-guide.html` specimens for all three anchors.
6. Inventory `theme.js` / `style-guide.html` toolbar toggling precedent without
   treating it as module closure.
7. Map FAB menu vs FAB family vs Menu vs popover responsibilities.
8. Map Split button primary action vs trailing popover responsibilities.
9. Map Toolbar vs Button group vs editor/plugin toolbar responsibilities.
10. Decide runtime need for each row:
    `lab-fab-menu.js`, `lab-split-button.js`, `lab-toolbar.js`, or no JS.
11. Decide ripple state for each row: TARGET bounded/unbounded vs CANDIDATE
    remain vs static state-layer only.
12. Decide icon-system usage and Material Symbols policy for each row.
13. Confirm no components.css / popover / ripple / icon-system /
    style-guide.js / theme.js edits are needed.
14. Verify current matrix counts after v3.6.12 and decide whether Phase 5 should
    clean `CURRENT-STATE.md` snapshot counts.
15. Select Route A/B/C/D/E/F with rejected-route evidence.
16. Define Phase 2 write scope and Phase 3 QA matrix.

## Phase 2 Conditional Write Scope

If Phase 1 selects Route A, expected files may include:

```txt
products/reference-implementations/axismundi-lab/modules/fab-menu/
  lab-fab-menu.css
  lab-fab-menu.js                    optional, only if Phase 1 approves
  lab-fab-menu-pattern.html
  docs/FAB-MENU-SPEC-AUDIT.md
  docs/FAB-MENU-MEASUREMENT-AUDIT.md
  docs/FAB-MENU-RUNTIME-AUDIT.md
  docs/FAB-MENU-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/split-button/
  lab-split-button.css
  lab-split-button.js                optional, only if Phase 1 approves
  lab-split-button-pattern.html
  docs/SPLIT-BUTTON-SPEC-AUDIT.md
  docs/SPLIT-BUTTON-MEASUREMENT-AUDIT.md
  docs/SPLIT-BUTTON-RUNTIME-AUDIT.md
  docs/SPLIT-BUTTON-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/toolbar/
  lab-toolbar.css
  lab-toolbar.js                     optional, only if Phase 1 approves
  lab-toolbar-pattern.html
  docs/TOOLBAR-SPEC-AUDIT.md
  docs/TOOLBAR-MEASUREMENT-AUDIT.md
  docs/TOOLBAR-RUNTIME-AUDIT.md      only if JS/runtime is used
  docs/TOOLBAR-WP-MAPPING.md

docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-2-REPORT.md
```

If a smaller route is selected, Phase 1 must reduce this scope.

Do not add shared infrastructure modules in Phase 2.

## Files Not Expected To Change

Lock files:

```txt
AGENTS.md
CLAUDE.md
```

WordPress / Pilot:

```txt
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/fixtures/*
```

Baseline / styleguide / global runtime:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-lab/scripts/theme.js
```

Providers:

```txt
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
```

Closed component modules:

```txt
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
```

Existing Actions / form-adjacent modules:

```txt
products/reference-implementations/axismundi-lab/modules/button/*
products/reference-implementations/axismundi-lab/modules/button-group/*
products/reference-implementations/axismundi-lab/modules/fab/*
products/reference-implementations/axismundi-lab/modules/icon-button/*
```

Validators / generators:

```txt
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
```

## Stop-And-Return Conditions

Stop and return for review if Phase 1 or Phase 2 finds a need to:

1. Edit `components.css` or `blocks.css`.
2. Edit `style-guide.html`, `scripts/style-guide.js`, or `scripts/theme.js`.
3. Edit `popover/`, `ripple/`, or `icon-system/`.
4. Add a new shared action-menu / command-menu / toolbar provider.
5. Edit WordPress/Pilot files or introduce plugin behavior.
6. Edit `AGENTS.md`, `CLAUDE.md`, or Lock 5 wording.
7. Reinterpret FAB menu / Split button / Toolbar as one generic button group.
8. Reopen BACKLOG #47 popover provider hygiene as part of this cycle.
9. Reopen BACKLOG #46 disabled ripple hygiene as part of this cycle.

## Risk Register

### R1 - Wave 2B-4 Overbreadth

Three Actions rows mix popover consumers, ripple candidates, icon consumers, and
toolbar command semantics.

Mitigation: Phase 1 must select Route A only if all three can close without
provider or baseline edits. Otherwise split.

### R2 - Popover Provider Bleed

FAB menu and Split button may tempt consumer-specific branches inside
`popover/`.

Mitigation: `popover/` is fenced. Any provider edit need activates Route D.

### R3 - Existing Menu Runtime Confusion

FAB menu is Menu-like but not Menu #15. It may share action-list semantics
without inheriting menu-item behavior wholesale.

Mitigation: Phase 1 maps FAB menu vs Menu vs popover responsibilities.

### R4 - Button Group Collapse

Split button and Toolbar can be mistaken for Button group variants.

Mitigation: Phase 1 maps Button group boundaries and rejects generic reuse that
erases split-button or toolbar semantics.

### R5 - Toolbar Global Runtime Dependence

Existing toolbar toggling in `theme.js` is global styleguide behavior.

Mitigation: Treat it as precedent only. Phase 1 decides local runtime or static
specimens; do not edit `theme.js`.

### R6 - Ripple Candidate Silent Promotion

Matrix currently lists FAB menu, Split button, and Toolbar ripple state as
CANDIDATE.

Mitigation: Phase 1 explicitly decides TARGET vs CANDIDATE for each host.

### R7 - Icon-System Drift

FAB menu and Toolbar have many icon slots.

Mitigation: consume Material Symbols policy only; no icon-system edits and no
BACKLOG #14 work.

### R8 - Disabled Ripple Host Hygiene Bleed

Disabled action hosts may interact with BACKLOG #46.

Mitigation: do not resolve #46 in this cycle. Record disabled-host authoring
policy locally and route broader provider tolerance separately.

### R9 - Plugin / Editor Toolbar Scope Creep

Toolbar can resemble editor/plugin UI.

Mitigation: lab Toolbar is public component surface only. Editor toolbar
commands stay plugin territory.

### R10 - FAB Floating Choreography Scope Creep

FAB family docs defer toolbar/FAB choreography and scroll behavior.

Mitigation: FAB menu may include local expand/collapse only; floating-with-FAB
page choreography remains out of scope unless Phase 1 returns for review.

### R11 - Validation Artifact Churn

Standard validators and publish may generate tracked mirrors.

Mitigation: restore generated artifacts after validation unless Phase 5
explicitly records intended state-doc updates.

### R12 - Matrix Count Drift

v3.6.12 close moved DateTime to DONE, but state snapshots may lag.

Mitigation: Phase 1 verifies counts; Phase 5 may clean state docs if needed.

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

Additional checks if Phase 2 implements modules:

```txt
node --check lab-fab-menu.js       if created
node --check lab-split-button.js   if created
node --check lab-toolbar.js        if created
```

Phase 3 expected QA, adjusted by selected route:

```txt
visual matrix: each implemented module x desktop/mobile x light/dark
console/page errors: 0
390px overflow: 0
popover consumers: trigger open/close, outside click, Escape, focus restore
FAB menu: collapsed/open state, item activation, disabled item, icon policy
Split button: primary action remains distinct from trailing menu trigger
Toolbar: role=toolbar, aria-label, pressed state, keyboard/focus behavior
ripple: actual provider attach for approved hosts only
disabled hosts: no unintended animated ripple
icons: material-symbols policy counts match
providers/fences: unchanged
```

## Phase Plan

### Phase 0 - Plan

Add this plan only. No implementation files.

### Phase 1 - Diagnostic Inventory

Complete the 16 inventory tasks, select Route A/B/C/D/E/F, and record rejected
routes. Absorb any Phase 0 review notes.

### Phase 2 - Implementation

Implement only the approved route. Preserve all fences. If a stop trigger
appears, stop and return for review before patching.

### Phase 3 - Visual / Interaction QA

Use repository-root localhost test target convention. Include CDP accessibility
tree evidence if toolbar or menu-like role hierarchy needs deterministic a11y
verification.

### Phase 5 - Close

Update state docs only. Record completed rows, next routing, Lock 5
self-application status, and any narrowed follow-ons.

## Out Of Scope

- BACKLOG #21 Interpreter Plugin strategy.
- BACKLOG #41 shared WordPress ripple runtime packaging.
- BACKLOG #44 residual specimen / validator polish.
- BACKLOG #46 disabled ripple host hygiene.
- BACKLOG #47 popover provider menu-item-class logic extraction.
- Sheet drag-to-dismiss.
- Full-screen Dialog follow-on.
- WordPress block editor toolbar behavior.
- New plugin/custom block behavior.
- Baseline CSS modernization.
- Provider refactors.
- Lock file edits.

## Phase 1 Entry Conditions

Phase 1 may begin after review approval if:

```txt
Phase 0 remains a single-doc addition
working tree is clean
no implementation files are touched
Lock 5 self-application remains explicit
```

Expected Phase 1 report path:

```txt
docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-1-REPORT.md
```
