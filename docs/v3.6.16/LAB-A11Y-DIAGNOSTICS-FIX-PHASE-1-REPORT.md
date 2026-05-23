# v3.6.16 Phase 1 Diagnostic Report - Lab A11y Diagnostics Fix

Date: 2026-05-23
Status: Phase 1 diagnostic complete; awaiting review
Route recommendation: Route A - 4-in-1 narrow fix sweep
Scope: BACKLOG #48 target diagnostics only

## Baseline

```txt
HEAD:              882fb2b Close v3.6.15 vs code diagnostics sweep
Branch:            main == origin/main
Working tree:      docs/v3.6.16/ untracked only
Source edits:      none in Phase 1
Primary evidence:  v3.6.15 user-captured VS Code Problems panel diagnostics
Supporting:        v3.6.15 parser/validator sweep and Docker validation retry
```

Phase 1 is read-only. It inventories the four BACKLOG #48 diagnostics, closes the
semantic decisions needed for implementation, and preserves the v3.6.16 Phase 0
fence before Phase 2.

## Opus Phase 0 Findings Absorbed

```txt
P3-1 Phase 4 trigger:   answered as Q7 below. Phase 4 remains intentionally
                        unused unless Phase 1 review or Phase 3 re-sweep exposes
                        a broader accessibility audit need beyond the 4 target
                        diagnostics.

P3-2 menu runtime:      products/reference-implementations/axismundi-lab/modules/menu/
                        contains docs/, lab-menu-pattern.html, and lab-menu.css.
                        No lab-menu.js exists. Phase 2 can protect the menu
                        runtime fence by limiting menu edits to pattern HTML plus
                        a SPEC audit addendum.

P3-3 Route A precedent: v3.6.13 proved a 3-in-1 narrow multi-module cycle can
                        close cleanly under Lock 5. v3.6.16 is not identical
                        because it edits existing module pattern pages rather
                        than adding fresh modules, but the precedent still
                        supports a bounded multi-file route when Phase 1 closes
                        all semantic choices before implementation.
```

## Target Diagnostics Inventory

| Target | Source | Diagnostic | Initial class | Phase 1 disposition |
| --- | --- | --- | --- | --- |
| DateTime CSS | `date-time/lab-date-time.css:22-31` | VS Code CSS parser errors from nested `/* EXTRACTED */` marker inside a block comment | Mechanical | Fix in CSS comment only |
| Menu pattern | `menu/lab-menu-pattern.html:77` | `aria-selected="true"` not allowed on `role="menuitem"` | Semantic | Use checkable menu item semantics |
| Nav bar pattern | `nav-bar/lab-nav-bar-pattern.html:81` | `aria-selected="true"` not allowed on a button | Semantic | Use navigation current-page semantics |
| Ripple pattern | `ripple/lab-ripple-pattern.html:190` | standalone `role="menuitem"` lacks required parent `menu`, `menubar`, or `group` | Semantic | Preserve menuitem consumer coverage inside a menu host |

## Phase 1 Questions Answered

### Q1. DateTime CSS Parser Error

The target error is caused by a nested comment marker in the file header:

```txt
*   The originals are retained in benchmark-interactions.css with an
*   /* EXTRACTED */ marker per the Charter EXTRACTED policy.
```

CSS block comments cannot contain another `/* ... */` sequence. The fix is
mechanical: replace the nested marker text with a non-comment token such as
`[EXTRACTED]` while preserving the documented policy reference.

Recommended Phase 2 edit:

```txt
date-time/lab-date-time.css
  Replace nested /* EXTRACTED */ prose with [EXTRACTED].
```

No DateTime SPEC audit addendum is required because the change is source-comment
hygiene only and does not affect runtime, styling, ARIA, or specimen behavior.

### Q2. Menu Selected State Semantics

The target element is inside a valid `role="menu"` host and represents a checked
menu command:

```html
<button class="ax-menu__item is-selected" role="menuitem" aria-selected="true">
  ... Autosave on
</button>
```

`aria-selected` is not valid on `menuitem`. Because the item presents a check icon
and the label "Autosave on", the narrow semantic fix is to model the item as a
checkable menu item.

Recommended Phase 2 edit:

```txt
menu/lab-menu-pattern.html
  role="menuitem" aria-selected="true"
  -> role="menuitemcheckbox" aria-checked="true"

menu/docs/MENU-SPEC-AUDIT.md
  Add a short note that checkable/selected menu examples use
  role="menuitemcheckbox" with aria-checked, while visual styling may retain
  .is-selected.
```

Rejected alternatives:

```txt
aria-current: not a location/page-current case.
visual-only state: would remove accessible check state from "Autosave on".
tab/listbox semantics: wrong widget pattern for a menu command.
```

### Q3. Nav Bar Current State Semantics

The first nav-bar specimen already uses `aria-current="page"` for the active
destination. The target diagnostic appears in the disabled-items specimen, where
the active "Forum" button uses `aria-selected="true"`.

Nav bar destinations are navigation links/buttons, not tabs. The correct narrow
fix is to align the second specimen with the first: use `aria-current="page"` for
the active destination and keep the visual active class.

Recommended Phase 2 edit:

```txt
nav-bar/lab-nav-bar-pattern.html
  aria-selected="true" -> aria-current="page"

nav-bar/docs/NAV-BAR-SPEC-AUDIT.md
  Add a short note that active navigation destinations use aria-current="page";
  this pattern does not use the tablist/tab model.
```

Rejected alternatives:

```txt
role=tab / tablist: would reclassify navigation as a tab widget.
visual-only state: would remove accessible current-page state.
```

### Q4. Ripple Menuitem Consumer Coverage

The ripple pattern includes a standalone TARGET consumer coverage specimen:

```html
<button class="ax-menu__item t-label-large" type="button" role="menuitem" data-ax-ripple>
  Menu item
</button>
```

The specimen is intended to prove ripple behavior on an existing menu item
consumer. Changing the role to `button` would remove that coverage. The narrowest
fix is to keep the `menuitem` consumer and provide its required parent context in
the specimen page.

Recommended Phase 2 edit:

```txt
ripple/lab-ripple-pattern.html
  Wrap the standalone menuitem example in a small role="menu" host with an
  accessible label, preserving data-ax-ripple on the menuitem itself.

ripple/docs/RIPPLE-V2-AUDIT.md
  Add a short specimen-semantics note that menuitem TARGET coverage must live
  inside a menu host in lab pattern pages.
```

Rejected alternatives:

```txt
Change role to button: loses menuitem TARGET consumer evidence.
Edit ripple runtime/CSS: unnecessary and outside fence.
Edit provider/menu implementation: outside BACKLOG #48 scope.
```

### Q5. Documentation Addenda

Recommended docs scope for Phase 2:

```txt
Required addenda:
  products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md

Not required:
  date-time/docs/*    DateTime change is source-comment hygiene only.
```

The addenda should be short and evidence-focused. They should not reopen the
modules, rewrite existing audits, or introduce broad APG policy beyond the four
diagnostics.

### Q6. Verification Plan

Phase 2/3 verification should use the v3.6.15 primary/supporting evidence split:

```txt
Primary evidence:
  User-side VS Code Problems panel re-sweep for the 4 target files after Phase 2.
  Expected target result: the 4 severity-8 target diagnostics are gone.

Codex-runnable supporting evidence:
  node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js
  node --check products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
  php -l products/reference-implementations/axismundi-pilot/functions.php
  npm test
  python tools/generators/build_pilot_specimen_wall.py
  npm run validate:specimen-wall
  npm run validate:computed
  npm run publish:styleguide
  git diff --check
```

The user-captured VS Code re-sweep is required because Microsoft Edge Tools,
webhint, axe, and the VS Code CSS language service are the primary diagnostic
surface for BACKLOG #48.

### Q7. Phase 4 Trigger

Phase 4 remains intentionally unused for the recommended Route A.

Trigger Phase 4 only if one of these conditions appears before Phase 2 closes:

```txt
1. Phase 1 review rejects a semantic choice because it requires broader APG
   modeling than a pattern-page fix.
2. Phase 2 discovers a provider/runtime dependency outside the declared write
   fence.
3. Phase 3 user-side Problems panel re-sweep surfaces new non-local
   accessibility failures caused by the fixes.
```

Absent those conditions, v3.6.16 stays on Phase 0 / 1 / 2 / 3 / 5 and Phase 4 is
intentionally unused.

## Route Evaluation

### Route A - 4-in-1 Narrow Fix Sweep

```txt
Verdict: recommended

Why:
  - All 4 diagnostics were captured by the same v3.6.15 VS Code Problems sweep.
  - Each fix is local to a source comment or lab pattern specimen.
  - Provider/runtime files remain fenced.
  - Phase 1 closes the 3 semantic decisions before implementation.
  - Phase 3 can verify all target diagnostics with one user-side re-sweep.
```

Route A should remain bounded to these files:

```txt
Source/specimen:
  products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.css
  products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
  products/reference-implementations/axismundi-lab/modules/nav-bar/lab-nav-bar-pattern.html
  products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html

Audit addenda:
  products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md

Cycle docs:
  docs/v3.6.16/
```

### Route B - 1+3 Split

```txt
Verdict: fallback only

Use if reviewer wants the DateTime mechanical CSS comment fix separated from the
3 semantic ARIA fixes. Phase 1 found no implementation need for the split.
```

### Route C - Diagnostic-Only Close

```txt
Verdict: not recommended

v3.6.15 already diagnosed and routed the issues. v3.6.16 should resolve the
target diagnostics unless Phase 1 review rejects Route A.
```

## Fences

Expected to remain unmodified:

```txt
AGENTS.md
CLAUDE.md
NEXT-SESSION.md
CURRENT-STATE.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
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

`menu/lab-menu.js` is not listed because it does not exist. If a future local
copy appears, it remains outside v3.6.16 scope.

## Lock Impact

```txt
Lock 1 Axis G:       no theme.json or token edits expected
Lock 2 Axis E:       no token edits expected
Lock 3 core/button:  no WordPress bridge implementation expected
Lock 4 semantic row: no row/status changes expected
Lock 5 diagnostic:   satisfied by Phase 1 diagnostic-first route; Phase 2 may
                     proceed only after review accepts the semantic choices
```

If Route A closes cleanly, v3.6.16 becomes the sixth Lock 5 self-application
candidate and the fifth implementation-cycle application after v3.6.15's
diagnostic-only variant.

## Non-Goals Confirmed

```txt
- Do not treat Microsoft Edge Tools/webhint warnings as normative policy.
- Do not fix no-inline-styles warnings in v3.6.16.
- Do not fix broad compat-api/css warnings in v3.6.16.
- Do not introduce VS Code workspace diagnostics settings.
- Do not fold BACKLOG #46 or #47 into this cycle.
- Do not edit provider runtime files.
- Do not edit baseline components.css.
- Do not edit Pilot/WP bridge code.
- Do not update generated styleguide mirrors except transient publish validation.
```

## Phase 1 Recommendation

```txt
Recommendation: APPROVE WITH NOTES - GO for Phase 2 implementation

P1 blockers: none
P2 findings: none
P3 notes:
  - Phase 3 requires user-side VS Code Problems panel re-sweep because the
    primary diagnostic surface is editor/extension-provided.
  - Phase 4 remains intentionally unused unless one of the Q7 triggers appears.
  - Broader Edge Tools/webhint policy questions remain routed outside v3.6.16.
```

Phase 2 should implement Route A exactly as scoped above, then stop for Phase 3
verification and user-captured Problems panel evidence.
