# v3.6.16 Phase 5 Close - Lab A11y Diagnostics Fix

Date: 2026-05-23
Status: CLOSED
Route: Route A - 4-in-1 narrow fix sweep
Backlog: #48 resolved by v3.6.16

## Release Summary

v3.6.16 closes the BACKLOG #48 lab module a11y/CSS diagnostics follow-on that
was routed by the v3.6.15 VS Code Diagnostics Sweep.

The cycle fixed four user-captured VS Code Problems panel target diagnostics:

```txt
date-time/lab-date-time.css
  CSS parser errors from nested /* EXTRACTED */ marker in a block comment.

menu/lab-menu-pattern.html
  Invalid aria-selected on role=menuitem.

nav-bar/lab-nav-bar-pattern.html
  Invalid aria-selected on a plain button nav item.

ripple/lab-ripple-pattern.html
  Standalone role=menuitem without required parent.
```

Phase cadence:

```txt
Phase 0: plan
Phase 1: diagnostic and semantic decisions
Phase 2: implementation
Phase 3: user-side Problems panel verification
Phase 4: intentionally unused; Q7 trigger did not fire
Phase 5: close
```

## Files Changed

Source/specimen files:

```txt
products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
products/reference-implementations/axismundi-lab/modules/nav-bar/lab-nav-bar-pattern.html
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple-pattern.html
```

Audit addenda:

```txt
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/nav-bar/docs/NAV-BAR-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

Cycle docs:

```txt
docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-0-PLAN.md
docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-1-REPORT.md
docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-2-IMPLEMENTATION.md
docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-3-VERIFICATION.md
docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-5-CLOSE.md
```

## Implementation

```txt
DateTime:
  Replaced nested /* EXTRACTED */ prose with [EXTRACTED].

Menu:
  Changed the "Autosave on" menu item to role="menuitemcheckbox" with
  aria-checked="true"; retained .is-selected as visual styling.

Nav bar:
  Changed the disabled-items active destination from aria-selected="true" to
  aria-current="page", restoring consistency with the first nav-bar specimen.

Ripple:
  Wrapped the menuitem TARGET specimen in a local role="menu" host while keeping
  data-ax-ripple on the menuitem itself.
```

Audit docs record short v3.6.16 addenda only. The modules were not reopened for
broader redesign.

## Primary Verification

User-side VS Code Problems panel re-sweep after Phase 2:

| Target file | Errors | Warnings | Result |
| --- | ---: | ---: | --- |
| `date-time/lab-date-time.css` | 0 | 2 | target CSS parser errors gone |
| `menu/lab-menu-pattern.html` | 0 | 1 | target `aria-selected` / `menuitem` error gone |
| `nav-bar/lab-nav-bar-pattern.html` | 0 | 1 | target `aria-selected` / button error gone |
| `ripple/lab-ripple-pattern.html` | 0 | 3 | target required-parent error gone |

BACKLOG #48 target diagnostics closed:

```txt
PASS  date-time CSS nested comment parser errors
PASS  menu invalid aria-selected on role=menuitem
PASS  nav-bar invalid aria-selected on plain button
PASS  ripple standalone role=menuitem required-parent
```

Remaining warnings are out of scope for v3.6.16 and remain routed as policy or
compatibility follow-ons:

```txt
no-inline-styles on pattern critical styles
compat-api/css broad browser-support warnings
button-group inline-size: fit-content Samsung Internet compatibility warning
Microsoft Edge Tools / webhint normative policy
VS Code workspace diagnostics config policy
```

## Supporting Validation

```txt
PASS  node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js
PASS  node --check products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
PASS  php -l products/reference-implementations/axismundi-pilot/functions.php
PASS  npm test
      Overall 1.000
      Axis A/B/C/D/E/F/G all 1.000
PASS  python tools/generators/build_pilot_specimen_wall.py
PASS  npm run validate:specimen-wall
PASS  npm run validate:computed
PASS  npm run publish:styleguide
PASS  git diff --check
```

Generated validator reports and publish mirror artifacts were restored after the
validation runs.

## Fences Preserved

Unchanged by v3.6.16:

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

No baseline, provider runtime, Pilot, generator, validator, or styleguide source
changes were committed by this cycle.

## Mount Staleness Note

Phase 2 review saw mount-visible corruption symptoms in stale source snapshots.
Local verification confirmed the working tree was byte-clean:

```txt
menu pattern:      normal </body> / </html> termination
nav-bar pattern:   NUL=False, UTF-8 text
date-time CSS:     newline terminated
audit docs:        v3.6.16 addenda present
```

The observed mount lag reached roughly 22-67 hours for source files, confirming
that `[[feedback-mount-staleness]]` should continue to treat local git status and
user-side direct verification as authoritative.

## Lock Impact

```txt
Lock 1 Axis G:       npm test Axis G 1.000, no theme.json edits
Lock 2 Axis E:       npm test Axis E 1.000, no token edits
Lock 3 core/button:  WordPress bridge implementation untouched
Lock 4 semantic row: no row/status changes
Lock 5 diagnostic:   Phase 1 accepted before Phase 2; Phase 3 verified target
                     diagnostics before close
```

Lock 5 self-application count:

```txt
v3.6.11 implementation
v3.6.12 implementation
v3.6.13 implementation
v3.6.14 implementation
v3.6.15 diagnostic-only variant
v3.6.16 implementation

=> sixth clean self-application overall
=> fifth implementation-cycle self-application
```

## Routed Forward

Primary next-cycle candidates:

```txt
BACKLOG #21 Interpreter Plugin strategy
BACKLOG #41 shared WordPress ripple runtime packaging decision
BACKLOG #44 specimen follow-on coverage
BACKLOG #46 disabled ripple host authoring hygiene
BACKLOG #47 popover provider menu-item-class logic extraction hygiene
Pilot theme revision
```

Policy / diagnostics follow-ons:

```txt
VS Code workspace diagnostics config policy
Microsoft Edge Tools / webhint normative policy for lab module pages
no-inline-styles policy for pattern critical styles
broad compat-api/css handling policy
button-group inline-size: fit-content compatibility warning
```

## Close Verdict

```txt
v3.6.16 CLOSED
BACKLOG #48 RESOLVED
P1 blockers: none
P2 findings: none
Phase 4: intentionally unused
```
