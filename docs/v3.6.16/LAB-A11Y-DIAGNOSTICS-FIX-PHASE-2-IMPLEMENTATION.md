# v3.6.16 Phase 2 Implementation Report - Lab A11y Diagnostics Fix

Date: 2026-05-23
Status: Phase 2 implementation complete; awaiting review
Route: Route A - 4-in-1 narrow fix sweep
Scope: BACKLOG #48 target diagnostics only

## Baseline

```txt
HEAD:          882fb2b Close v3.6.15 vs code diagnostics sweep
Phase 1:       accepted with P2-1 path amendment, then corrected in place
Write scope:   4 source/specimen files + 3 audit addenda + v3.6.16 docs
Source fence:  baseline/provider runtime/Pilot/generator/styleguide sources untouched
```

## Phase 1 Review Finding Absorbed

```txt
P2-1 path typos: resolved before Phase 2 implementation.

Corrected paths:
  products/reference-implementations/axismundi-pilot/functions.php
  products/reference-implementations/axismundi-lab/stylesheets/components.css
  products/reference-implementations/axismundi-pilot/**
```

## Implemented Fixes

### 1. DateTime CSS Comment Hygiene

File:

```txt
products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.css
```

Change:

```txt
/* EXTRACTED */ -> [EXTRACTED]
```

The nested CSS comment marker was replaced inside the header prose. This removes
the VS Code CSS parser confusion without changing selectors, declarations,
runtime, ARIA, or specimen behavior.

### 2. Menu Checkable Item Semantics

File:

```txt
products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
```

Change:

```txt
role="menuitem" aria-selected="true"
-> role="menuitemcheckbox" aria-checked="true"
```

The "Autosave on" menu item keeps its `.is-selected` visual state and check icon,
but now exposes the accessible state through the ARIA menuitemcheckbox pattern.

Audit addendum:

```txt
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
```

The addendum states that checkable menu examples use `menuitemcheckbox` plus
`aria-checked`, while `.is-selected` remains visual styling only.

### 3. Nav Bar Current Destination Semantics

File:

```txt
products/reference-implementations/axismundi-lab/modules/nav-bar/lab-nav-bar-pattern.html
```

Change:

```txt
aria-selected="true" -> aria-current="page"
```

The disabled-items specimen now matches the first nav-bar specimen: active
navigation destinations use `aria-current="page"`. This restores intra-module
specimen consistency.

Audit addendum:

```txt
products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-SPEC-AUDIT.md
```

The addendum records that nav bar destinations do not use the `tablist` / `tab`
model and do not use `aria-selected`.

### 4. Ripple Menuitem Parent Context

File:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html
```

Change:

```txt
Added a local role="menu" host around the standalone role="menuitem" specimen.
```

The ripple target remains the `role="menuitem"` button with `data-ax-ripple`,
preserving menuitem TARGET consumer coverage while satisfying the required ARIA
parent context.

Audit addendum:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

The addendum records that lab menuitem TARGET specimens must live inside a menu
host, and that the wrapper only supplies specimen semantics.

## Files Changed

```txt
products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
products/reference-implementations/axismundi-lab/modules/nav-bar/lab-nav-bar-pattern.html
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
docs/v3.6.16/
```

Diff stat at implementation checkpoint:

```txt
7 files changed, 26 insertions(+), 4 deletions(-)
```

## Fences Preserved

Unmodified implementation surfaces:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-pilot/**
tools/generators/**
tools/validators/**
styleguide/**
```

Generated artifacts from `npm test` and `npm run publish:styleguide` were
restored after validation. Publish-created untracked styleguide CSS files were
removed. The remaining working tree changes match the declared Route A scope.

## Validation

```txt
PASS  node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js
PASS  node --check products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
PASS  php -l products/reference-implementations/axismundi-pilot/functions.php
PASS  npm test
      Overall 1.000
      Axis A/B/C/D/E/F/G all 1.000
PASS  python tools/generators/build_pilot_specimen_wall.py
      Updated page 13 and page 14 at localhost:8888
PASS  npm run validate:specimen-wall
PASS  npm run validate:computed
PASS  npm run publish:styleguide
PASS  git diff --check
```

Primary Phase 3 evidence still required:

```txt
User-side VS Code Problems panel re-sweep for the 4 target files.
Expected result: the 4 BACKLOG #48 severity-8 target diagnostics are absent.
```

## Phase 3 Carry-Forward

```txt
1. User re-sweep:
   - date-time/lab-date-time.css
   - menu/lab-menu-pattern.html
   - nav-bar/lab-nav-bar-pattern.html
   - ripple/lab-ripple-pattern.html

2. Confirm target diagnostics gone:
   - nested CSS comment parser errors
   - menu aria-selected on menuitem
   - nav-bar aria-selected on button
   - ripple standalone menuitem required-parent

3. Confirm no new Route A regressions in those target files.

4. If new non-local accessibility diagnostics appear, apply Phase 1 Q7 and
   trigger a Phase 4/deeper-audit decision before close.
```

## Lock Impact

```txt
Lock 1 Axis G:       npm test Axis G 1.000, no theme.json edits
Lock 2 Axis E:       npm test Axis E 1.000, no token edits
Lock 3 core/button:  WordPress bridge implementation untouched
Lock 4 semantic row: no row/status changes
Lock 5 diagnostic:   Phase 1 accepted before Phase 2; Route A stayed inside
                     the reviewed write scope
```

## Phase 2 Recommendation

```txt
Recommendation: APPROVE WITH NOTES - GO for Phase 3 verification

P1 blockers: none
P2 findings: none
P3 notes:
  - Phase 3 depends on user-side VS Code Problems panel re-sweep.
  - Edge Tools/webhint broader policy warnings remain outside v3.6.16.
  - Phase 4 remains intentionally unused unless Phase 3 triggers Q7.
```
