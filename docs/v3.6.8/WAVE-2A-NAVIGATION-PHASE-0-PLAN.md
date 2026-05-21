# v3.6.8 - Wave 2A Navigation - Phase 0 Plan

Date: 2026-05-21

Phase: 0 - Plan

## User Request Log

User said:

```txt
후보 1개 선택 후 Phase 0 plan go
```

Carry-forward from v3.6.7 close review:

```txt
v3.6.7 closed.
Next cycle remains plan-first.
Candidate order:
  1. Wave 2 plan-first
  2. BACKLOG #21 Interpreter Plugin strategy
  3. narrowed BACKLOG #41 shared WordPress ripple runtime packaging decision
  4. residual BACKLOG #44 coverage follow-ons
```

This Phase 0 selects:

```txt
Wave 2A Navigation
```

Implementation files must not be edited before Phase 0 review GO.

## Current Baseline

Local status at Phase 0 entry:

```txt
## main...origin/main [ahead 10]
```

No unstaged or untracked work was present before writing this plan.

Repository state:

```txt
v3.6.0 through v3.6.7 are closed.
WordPress core in wp-env is 7.0.
v3.6.x cadence uses Phase 0 / 1 / 2 / 3 / 5.
Phase 4 is intentionally unused.
```

## Selected Candidate

v3.6.8 should be a Wave 2A Navigation cycle.

Reason:

```txt
v3.6.7 closed the highest-priority #44 editor compatibility question and
narrowed #44 to follow-on coverage/polish. The current ROADMAP, CURRENT-STATE,
v3.6.7 close doc, and reviewer relay all promote Wave 2 as the next primary
candidate.

Wave 2 is the largest remaining wave at 14 entries, so this cycle should not
attempt all of Wave 2. The existing component coverage map already says Wave 2
is likely to split into 2A/2B. Navigation is a coherent 5-row slice:

  App bar #11
  Nav bar #12
  Nav rail #13
  Tabs #14
  Menu #15
```

This cycle is intentionally plan-first and inventory-first. It should use
Phase 1 to decide whether v3.6.8 implements all five navigation rows, a smaller
navigation subset, or documentation/audit scaffolding only.

## Source Reconciliation

`NEXT-SESSION.md §1`, `CURRENT-STATE.md`, `ROADMAP.md`, and the v3.6.7 close
doc agree that Wave 2 is a primary next candidate after v3.6.7.

`NEXT-SESSION.md §5` still contains a pre-close-style #44 paragraph under
"Recommended primary routes". Because v3.6.7 Phase 5 explicitly closed #44's
editor compatibility question and demoted residual #44 coverage to an
alternative route, this plan treats the later close docs and current state
board as controlling.

Do not edit `NEXT-SESSION.md` in this Phase 0 plan. The cosmetic/status cleanup
can be absorbed by a later close or handoff update.

## Phase 0 Actually Read

Read according to `NEXT-SESSION.md §0` order:

```txt
AGENTS.md
CURRENT-STATE.md
PROJECT-CONTEXT.md
CHANGELOG.md latest entry
ROADMAP.md current tail
BACKLOG.md #41 / #44 / #21 / #14
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
```

Additional Phase 0 reads:

```txt
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.8/SEARCH-BAR-PHASE-2-PLAN.md
docs/v3.5.10/BUTTON-GROUP-PHASE-2-PLAN.md
docs/v3.5.11/LIST-PHASE-2-PLAN.md
package.json
products/reference-implementations/axismundi-lab/modules/
```

## Governing Prior Decisions

Wave 2 from `COMPONENT-COVERAGE-MAP.md`:

```txt
Split button #7
Toolbar #8
FAB menu #5
App bar #11
Nav bar #12
Nav rail #13
Tabs #14
Menu #15
Checkbox #18
Radio #19
Switch #20
Date+Time picker #22+#23
Dialog #26
Sheet #27
```

This Phase 0 narrows the next cycle to the Navigation rows:

```txt
App bar #11 - Component Full-Spec; ripple candidate in action slots; icon-system consumer
Nav bar #12 - Component Full-Spec; ripple TARGET; icon-system consumer
Nav rail #13 - Component Full-Spec; ripple TARGET; icon-system consumer
Tabs #14 - Component Full-Spec + Interaction; bounded ripple TARGET; arrow-key nav
Menu #15 - Component Full-Spec + Interaction dependency; popover/icon-system consumer
```

Infrastructure boundary from `PROMOTION-CRITERIA.md §5` remains binding:

```txt
Infrastructure modules MAY provide reusable mechanisms.
Infrastructure modules MUST NOT absorb consumer-specific semantics.
Consumer modules MUST NOT reimplement infrastructure behavior when a provider
exists.
```

Menu is therefore special:

```txt
Menu owns role=menu/menuitem/density/icon/shortcut/selected/disabled/divider/submenu.
popover/ owns anchor/position/dismiss/outside-click/Escape/focus restore/viewport collision.
```

## Active Locks

This cycle preserves all four close-time locks:

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
new domain test for that method.

## Scope

### In Scope

1. Inventory all five Navigation rows against the current repo:
   App bar, Nav bar, Nav rail, Tabs, Menu.
2. Decide the Wave 2A implementation route:
   all five rows, a smaller subset, docs/audit first, or split into two
   navigation cycles.
3. Identify existing traces and historical specimens for nav-like surfaces:
   styleguide shell, module pattern banners, nav-bar/nav-rail/tabs references,
   icon-system touchpoints, and popover/menu relationship.
4. Define the audit artifact shape needed for each selected component:
   SPEC / MEASUREMENT / WP-MAPPING, plus RUNTIME where interaction requires it.
5. Define dependency contracts:
   ripple, icon-system, popover, and possible focus/keyboard handling.
6. Preserve Menu/popover DISTINCT but COUPLED boundaries.
7. Preserve styleguide shell work from v3.5.17 as chrome/specimen precedent,
   not as App bar / Nav drawer / Sheet closure.
8. Decide whether Phase 2 should create lab module artifacts or remain
   plan/report-only after inventory.

### Out Of Scope

```txt
Do not implement all 14 Wave 2 entries.
Do not implement Wave 2B form family in this cycle.
Do not implement Dialog or Sheet in this cycle.
Do not implement BACKLOG #21 Interpreter Plugin.
Do not enter BACKLOG #41 shared WordPress ripple runtime packaging.
Do not consume residual BACKLOG #44 coverage follow-ons.
Do not promote diagnostic-first to Lock 5.
Do not edit theme.json or WordPress Pilot implementation files.
Do not edit lab components.css Section 0.
Do not edit styleguide/ published mirror manually.
Do not collapse Menu semantics into popover/ or add menu-specific branches to popover/.
Do not claim App bar/Nav bar/Nav rail/Tabs/Menu DONE until Phase 5 evidence supports it.
```

## Route Buckets

Phase 1 must choose one route before Phase 2 patches:

```txt
A. Full Navigation 2A:
   Implement App bar, Nav bar, Nav rail, Tabs, and Menu lab artifacts in one
   v3.6.8 cycle after inventory confirms the write set is safe.

B. Navigation Core First:
   Implement App bar, Nav bar, Nav rail, and Tabs. Defer Menu because it is a
   popover consumer with stronger semantic/runtime boundary risk.

C. Menu/Popover Consumer First:
   Implement Menu as the first Navigation row to exercise the DISTINCT but
   COUPLED popover boundary. Defer App bar/Nav bar/Nav rail/Tabs.

D. Audit-First Navigation:
   Produce the Phase 1 audit scaffold and route decisions only; defer
   implementation to a follow-up if the navigation surface is too large or
   dependency decisions are under-specified.

E. Split Wave 2A Into Two Cycles:
   Confirm a formal split, such as 2A-1 App/Nav/Tabs and 2A-2 Menu, with no
   implementation in v3.6.8 beyond the split decision.

F. Other, with evidence.
```

Expected Phase 1 outcome:

```txt
Route A/B/C/D/E/F selected with evidence.
No implementation files edited in Phase 1.
```

## Phase Partition

### Phase 0 - Plan

Artifact:

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-0-PLAN.md
```

Exit criteria:

```txt
Selected candidate is Wave 2A Navigation.
Plan-first is preserved.
Phase 1 is inventory before implementation.
Navigation scope and Menu/popover risk are explicit.
Locks 1-4 are explicit.
Phase 4 remains unused.
Implementation files are untouched.
```

### Phase 1 - Navigation Inventory / Route Selection

No implementation files may be edited in Phase 1.

Read and inventory:

```txt
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
products/reference-implementations/axismundi-lab/modules/popover/
products/reference-implementations/axismundi-lab/modules/ripple/
products/reference-implementations/axismundi-lab/modules/icon-system/
products/reference-implementations/axismundi-lab/modules/button/
products/reference-implementations/axismundi-lab/modules/icon-button/
products/reference-implementations/axismundi-lab/modules/fab/
products/reference-implementations/axismundi-lab/modules/list/
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/icons.css
```

Inventory tasks:

```txt
Navigation rows:
  - App bar #11: identify slots, scroll behavior, action semantics, ripple state.
  - Nav bar #12: identify item structure, selected state, label/icon pattern.
  - Nav rail #13: identify collapsed/expanded variants, item structure,
    selected state, destination semantics.
  - Tabs #14: identify primary/secondary tabs, selected state, indicator,
    keyboard requirements, bounded ripple route.
  - Menu #15: identify semantic ownership vs popover ownership, menu item
    states, divider, selected, disabled, shortcut, submenu/defer status.

Existing traces:
  - Search for nav-bar/nav-rail/tabs/menu specimens or styleguide remnants.
  - Compare with icon-system docs and Material Symbols policy.
  - Compare with ripple consumer-state buckets.
  - Compare with popover audit/provider contract.

Route selection:
  - Choose A/B/C/D/E/F.
  - For each rejected route, record evidence and risk.
```

Expected artifact:

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
```

Exit criteria:

```txt
All five Navigation rows inventoried.
Existing implementation traces mapped.
Dependencies classified: ripple / icon-system / popover / none.
Menu/popover boundary explicitly preserved.
Route A/B/C/D/E/F selected.
Implementation files edited: no.
```

### Phase 2 - Selected Navigation Patch

Patch only the Phase 1 selected route.

Possible files if Route B is selected:

```txt
products/reference-implementations/axismundi-lab/modules/app-bar/
products/reference-implementations/axismundi-lab/modules/nav-bar/
products/reference-implementations/axismundi-lab/modules/nav-rail/
products/reference-implementations/axismundi-lab/modules/tabs/
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
```

Possible files if Route C is selected:

```txt
products/reference-implementations/axismundi-lab/modules/menu/
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
```

Possible files if Route D or E is selected:

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
```

Patch constraints:

```txt
Lab module CSS must be lab-scoped under a module demo/root class.
Do not add unscoped baseline overrides.
Do not edit components.css Section 0.
Do not edit published styleguide/ manually.
Do not edit WordPress Pilot theme files.
Do not edit popover/ to contain menu-specific behavior.
Do not edit ripple/ or icon-system/ unless Phase 1 review explicitly expands scope.
```

Exit criteria:

```txt
Selected route is implemented or documented.
New module artifacts, if any, are lab-scoped.
Dependency contracts are cited in docs.
No baseline/token/WordPress regression.
```

### Phase 3 - Visual / Interaction QA

Surfaces depend on the selected Phase 2 route.

Minimum checks for any implemented navigation module:

```txt
Desktop viewport
Mobile viewport
Light mode
Dark mode
Keyboard focus order
Visible focus indicators
Selected/active state
Disabled state if applicable
Console/page errors: 0
No horizontal overflow at 390px
```

If a route touches overlay/portal/menu behavior, apply the global portal /
overlay smoke test:

```txt
trigger exists
runtime handler attaches
host / portal element exists
open and visible state works
close / dismiss path works
console and page errors are absent
```

If a route touches ripple:

```txt
data-ax-ripple attachment is intentional
bounded/unbounded choice is recorded
ripple count is measured on target, not container
disabled targets do not spawn ripple
```

Expected artifact:

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
```

Exit criteria:

```txt
Visual and interaction evidence recorded for the selected route.
Menu/popover boundary, if exercised, is verified by behavior and source scope.
No manual styleguide/ edits.
No claims exceed implemented evidence.
```

### Phase 5 - Close

Expected close artifacts:

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

`AGENTS.md` / `CLAUDE.md` should update only if a new operating rule is
promoted. Lock 5 promotion is not expected in this cycle unless review
explicitly approves it after seeing non-WP-domain evidence.

Exit criteria:

```txt
Wave 2A result recorded honestly: closed, narrowed, split, or routed.
MODULE-STATUS-MATRIX status changes only if evidence supports them and review
authorizes status mutation.
CHANGELOG includes v3.6.8.
NEXT-SESSION.md reading order includes v3.6.8 docs.
Any NEXT-SESSION status-header cosmetic cleanup can be absorbed here.
```

## Applicable G1-G26 Gates

Universal gates:

```txt
G1. npm test / validate_theme_pilot.py PASS.
G2. Baseline surfaces untouched unless explicitly authorized.
G3. Module docs cite dependencies and boundaries.
G4. Phase reports and any new module artifacts exist.
G5. CHANGELOG entry at Phase 5.
G6. Visual QA covers responsive/light/dark states where applicable.
G7. Accessibility semantics are native or explicitly justified.
G8. Keyboard interaction is tested for interaction-bearing modules.
G10. Findings recorded in docs, not chat memory.
```

Component / interaction gates:

```txt
G11-G14 apply to Component Full-Spec rows selected for implementation.
G12/G13 apply to Tabs and Menu if interaction is implemented.
G20. Existing regression gates must remain PASS.
```

Infrastructure/provider gates:

```txt
G21. Theme-can / plugin-should boundary remains explicit.
G22. Multi-consumer requirement for infrastructure changes.
G23. Semantic neutrality for infrastructure providers.
G24. Independent provider audit docs remain authoritative.
G25. Consumer/provider boundary remains DISTINCT but COUPLED.
G26. Existing infrastructure contracts do not drift silently.
```

v3.6.x hard gates:

```txt
Axis E - md-sys color maps to md-ref: PASS
Axis F - bridge downstream-only: PASS
Axis G - wp-custom downstream-only: PASS
validate:specimen-wall: PASS
validate:computed: PASS
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

Additional Phase 1 / Phase 3 probes:

```txt
Lab module pattern pages:
  file:///C:/Users/thaum/dev/axismundi/products/reference-implementations/axismundi-lab/modules/<module>/<pattern>.html

Published mirror only if generated:
  file:///C:/Users/thaum/dev/axismundi/styleguide/<relevant-page>.html

Interaction probes:
  keyboard tab order
  arrow key behavior for Tabs/Menu if implemented
  Escape/close behavior for Menu if implemented
  selected/disabled/focus/hover/pressed states
  ripple target count where applicable
```

## Risks

### R1 - Wave 2A too large

Risk:

```txt
Five navigation rows include both static component surfaces and interaction
runtime surfaces. Implementing all five may exceed a safe v3.6.8 slice.
```

Mitigation:

- Phase 1 must choose a route before patches.
- Prefer a smaller implementation route if Menu or Tabs expands the risk.
- Do not count incomplete rows as DONE.

### R2 - Menu absorbs popover or popover absorbs Menu

Risk:

```txt
Menu may reimplement popover infrastructure, or popover may gain menu-specific
semantics.
```

Mitigation:

- Preserve DISTINCT but COUPLED.
- Menu owns menu semantics; popover owns anchored surface mechanics.
- Any provider change requires G22-G26 review.

### R3 - Styleguide chrome mistaken for App bar/Sheet closure

Risk:

```txt
v3.5.17 styleguide shell work includes top app bar and drawer-like behavior,
but it explicitly did not close App bar, Nav drawer, or Sheet components.
```

Mitigation:

- Treat styleguide shell as precedent/evidence only.
- Do not claim component closure from shell chrome.

### R4 - Ripple consumer-state drift

Risk:

```txt
Nav bar, Nav rail, and Tabs are ripple TARGET, while App bar action slots are
CANDIDATE. A generic navigation ripple patch could over-attach.
```

Mitigation:

- Record bounded/unbounded route per component and per item.
- Measure target-level ripple, not container-level ripple.
- Do not edit ripple provider without explicit review.

### R5 - Material Symbols / icon-system drift

Risk:

```txt
Navigation components are icon-heavy. A local workaround could violate the
icon-system policy or revive BACKLOG #14 layout-shift concerns.
```

Mitigation:

- Use existing Material Symbols policy and icon-system classes.
- Do not introduce new icon font loading policy in this cycle.
- Route global icon metrics issues to BACKLOG #14.

### R6 - Baseline mutation too early

Risk:

```txt
Wave modules historically use lab-scoped artifacts before public/baseline
promotion. Editing components.css or styleguide directly could skip the
validation lane.
```

Mitigation:

- Keep Phase 2 lab-scoped unless review explicitly approves baseline mutation.
- Use publish generator for styleguide mirror only after source changes.

### R7 - Lock 5 premature promotion

Risk:

```txt
Diagnostic-first may work in a new domain, but one Wave 2A cycle is still not
enough by itself to promote a new close-time lock.
```

Mitigation:

- Record methodology evidence.
- Defer Lock 5 unless review explicitly approves promotion.

## Files Expected To Change After GO

Phase 1:

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
```

Phase 2, depending on selected route:

```txt
products/reference-implementations/axismundi-lab/modules/app-bar/*
products/reference-implementations/axismundi-lab/modules/nav-bar/*
products/reference-implementations/axismundi-lab/modules/nav-rail/*
products/reference-implementations/axismundi-lab/modules/tabs/*
products/reference-implementations/axismundi-lab/modules/menu/*
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
```

Only the files proven necessary by Phase 1 should change.

Phase 3:

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
```

Phase 5:

```txt
docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

## Files Not Expected To Change

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
products/reference-implementations/axismundi-lab/stylesheets/components.css Section 0
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
styleguide/* by manual edit
tools/validators/validate_theme_pilot.py
```

If Phase 1 proves one of these files must change, stop and request review
approval before expanding scope.

## Opus Review Checklist

Phase 0 review should verify:

1. Wave 2A Navigation is a valid next candidate after v3.6.7.
2. The plan reconciles the NEXT-SESSION §5 residual #44 wording with the later
   v3.6.7 close/current-state documents.
3. Phase 1 inventories all five Navigation rows before implementation.
4. Route buckets A-F cover full, partial, Menu-first, audit-only, and split
   outcomes.
5. Menu/popover DISTINCT but COUPLED boundary is explicit.
6. Ripple and icon-system dependencies are fenced.
7. Styleguide shell precedent is not mistaken for App bar/Sheet closure.
8. Locks 1-4 are preserved and Lock 5 remains deferred.
9. Validation includes standard repo gates plus interaction/overlay checks for
   any implemented runtime surface.
10. Phase 4 remains intentionally unused.

## Next

Submit this Phase 0 plan for Opus review. Do not edit implementation files
until Phase 0 receives GO.
