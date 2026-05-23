# v3.6.16 Lab Module A11y Diagnostics Fix - Phase 0 Plan

Date: 2026-05-23

## Purpose

Address BACKLOG #48, the v3.6.15 VS Code Problems panel follow-on for four lab
module diagnostics:

```txt
date-time/lab-date-time.css:22-31
  CSS parser hygiene: nested comment marker in the header comment.

menu/lab-menu-pattern.html:77
  aria-selected is invalid on role=menuitem.

nav-bar/lab-nav-bar-pattern.html:81
  aria-selected is invalid on a plain button nav item.

ripple/lab-ripple-pattern.html:190
  standalone role=menuitem lacks required menu/menubar/group parent.
```

The cycle is a narrow diagnostics-fix cycle, not a provider redesign, component
reopen, or broad Microsoft Edge Tools / webhint policy cycle.

## Source Inputs

Reading order:

```txt
1. AGENTS.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #48, plus #46 / #47 for non-folding constraints
7. docs/v3.6.15/VS-CODE-DIAGNOSTICS-SWEEP-PHASE-5-CLOSE.md
8. docs/v3.6.15/VS-CODE-DIAGNOSTICS-SWEEP-PHASE-1-REPORT.md
9. docs/v3.6.15/VS-CODE-DIAGNOSTICS-SWEEP-PHASE-0-PLAN.md
10. Related module SPEC / RUNTIME docs:
    - modules/date-time/docs/DATE-TIME-SPEC-AUDIT.md
    - modules/menu/docs/MENU-SPEC-AUDIT.md
    - modules/menu/docs/MENU-RUNTIME-AUDIT.md
    - modules/nav-bar/docs/NAV-BAR-SPEC-AUDIT.md
    - modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

## Current Evidence

From v3.6.15:

```txt
VS Code Problems panel:
  Wave 3 priority slice source errors: 0
  BACKLOG #48 P2 diagnostics: 4

Parser / validator support:
  JavaScript syntax 25/25 PASS
  PHP syntax 8/8 PASS
  Python compile PASS
  JSON parse 50/50 PASS
  npm test Axis A/B/C/D/E/F/G all 1.000
  build_pilot_specimen_wall PASS
  validate:specimen-wall PASS
  validate:computed PASS
```

The v3.6.16 acceptance basis must include a user-captured VS Code Problems panel
re-sweep for the four target diagnostics. Codex-runnable validators remain
supporting evidence.

## Route Candidates

### Route A - 4-in-1 Narrow Fix Sweep

```txt
Fix all four BACKLOG #48 diagnostics in one cycle.

Pros:
  - Closes BACKLOG #48 in one pass.
  - All four findings share the same source: v3.6.15 Problems panel sweep.
  - Scope remains small if changes stay inside module pattern/CSS/docs.

Cons:
  - 3 of 4 findings need semantic decisions.
  - Touches date-time, menu, nav-bar, and ripple in one cycle.
  - Requires careful provider-fence language because ripple/ and menu/ are
    provider-related directories.
```

Preliminary recommendation: Route A, if Phase 1 confirms every fix can remain
pattern/spec-local and no provider runtime mutation is required.

### Route B - 1+3 Split

```txt
Fix date-time nested comment mechanically in v3.6.16, route the 3 semantic ARIA
items to v3.6.17.

Pros:
  - Lowest semantic risk.
  - Very small implementation.

Cons:
  - Leaves the main a11y findings unresolved.
  - Creates extra cadence overhead.
  - BACKLOG #48 remains open.
```

Fallback trigger: Phase 1 finds menu/nav/ripple decisions require broader SPEC
redesign than pattern-page diagnostics.

### Route C - Diagnostic Report Only

```txt
Do not fix; produce a deeper Phase 1 report and route all implementation later.
```

Not recommended unless Phase 1 finds hidden coupling.

## Phase 1 Questions

Phase 1 must answer before implementation:

```txt
1. DateTime:
   Is the nested comment marker the only VS Code CSS parser source error?
   Is replacing /* EXTRACTED */ with [EXTRACTED] sufficient?

2. Menu:
   Does the selected item represent a checkable menu item, or a visual "current
   command state" example?
   Candidate fixes:
     - role=menuitemcheckbox + aria-checked=true
     - role=menuitem + visual check icon only
     - aria-current if and only if the item represents current location/page

3. Nav bar:
   Does the active nav destination represent current page/location?
   Candidate fixes:
     - aria-current=page on the active button
     - role=tab / tablist model only if the specimen is really a tab switcher
     - visual-only is-active class if it is not a navigation target

4. Ripple:
   Is the standalone ax-menu__item specimen meant to prove menu-item consumer
   coverage or only ripple attachment on a menu-shaped host?
   Candidate fixes:
     - wrap in role=menu with aria-label
     - change role=menuitem to button and keep menu styling only as a visual
       host example

5. Docs:
   Which SPEC / RUNTIME docs need short v3.6.16 addenda?

6. Verification:
   Which user-side VS Code Problems re-sweep rows are required for close?
```

## Phase Cadence

```txt
Phase 0: plan and route candidates
Phase 1: semantic diagnostic inventory and route recommendation
Phase 2: implementation only after review GO
Phase 3: VS Code Problems re-sweep + smoke/validator evidence
Phase 5: close / release metadata
```

Phase 4 is intentionally unused unless Phase 1 discovers a deeper accessibility
audit need.

## Expected Write Scope

Allowed if Phase 2 proceeds:

```txt
docs/v3.6.16/

products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
products/reference-implementations/axismundi-lab/modules/nav-bar/lab-nav-bar-pattern.html
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html

Potential short addenda only if Phase 1 confirms needed:
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
products/reference-implementations/axismundi-lab/modules/date-time/docs/DATE-TIME-SPEC-AUDIT.md
```

Not expected to change:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/popover/
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu.css
products/reference-implementations/axismundi-lab/modules/nav-bar/lab-nav-bar.css
products/reference-implementations/axismundi-pilot/
tools/generators/
tools/validators/
styleguide/
```

Provider fence:

```txt
Pattern HTML inside modules/ripple/ may be edited only to fix the specimen
diagnostic. Ripple runtime/provider JS and CSS remain out of scope.
```

3-tracked-copy impact:

```txt
None expected. No token CSS, Pilot bridge CSS, or styleguide mirror token copies
are planned.
```

## Validation Plan

Phase 2 / Phase 3 validation candidates:

```txt
node --check products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
npm run publish:styleguide
git diff --check
```

Required user-side evidence before close:

```txt
VS Code Problems panel re-sweep for:
  date-time/lab-date-time.css
  menu/lab-menu-pattern.html
  nav-bar/lab-nav-bar-pattern.html
  ripple/lab-ripple-pattern.html
```

## Lock Impact

```txt
Lock 1 Axis G: verify with npm test; no theme.json edits planned.
Lock 2 Axis E: verify with npm test; no token edits planned.
Lock 3 core/button: no WordPress bridge implementation planned.
Lock 4 semantic row routing: no component row status changes planned.
Lock 5 diagnostic-first: active; Phase 1 must close semantic decisions before
                         Phase 2 implementation.
```

## Non-Goals

```txt
No Microsoft Edge Tools / webhint policy sweep.
No no-inline-styles cleanup.
No compat-api/css broad warning cleanup.
No VS Code workspace diagnostics config.
No BACKLOG #46 / #47 implementation.
No provider runtime mutation.
No baseline CSS mutation.
No Pilot revision.
```

## Review Gate

Phase 0 review should decide:

```txt
1. Is Route A acceptable as preliminary, with Phase 1 authority to fall back?
2. Are the expected write fences narrow enough?
3. Is user-side Problems panel re-sweep required in Phase 3? (recommended: yes)
4. May Phase 1 proceed read-only?
```
