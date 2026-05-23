# v3.6.16 Phase 3 Verification Report - Lab A11y Diagnostics Fix

Date: 2026-05-23
Status: Phase 3 verification complete; awaiting review
Route: Route A - 4-in-1 narrow fix sweep
Primary evidence: user-captured VS Code Problems panel re-sweep

## Baseline

```txt
HEAD:            882fb2b Close v3.6.15 vs code diagnostics sweep
Implementation:  Phase 2 accepted with mount-staleness artifact ruled out by
                 user/local byte-clean verification
Working tree:    Route A source/docs + docs/v3.6.16/
Phase 4:         intentionally unused unless Q7 trigger fires
```

## Mount Staleness Follow-Up

Phase 2 review saw mount-visible corruption artifacts. Local direct verification
confirmed the working tree is byte-clean:

```txt
PASS  menu/lab-menu-pattern.html        </body> / </html> normal termination
PASS  nav-bar/lab-nav-bar-pattern.html  NUL=False, UTF-8 text tail normal
PASS  date-time/lab-date-time.css       final byte 0x0A, newline terminated
PASS  MENU/NAV-BAR/RIPPLE-V2 audit docs contain "v3.6.16 Diagnostic Addendum"
```

The Phase 2 mount-visible corruption symptoms are confirmed as
`[[feedback-mount-staleness]]` artifacts and are not blockers.

## Primary Evidence - VS Code Problems Panel

User-side re-sweep captured the VS Code Problems panel after Phase 2.

### Target File Summary

| Target file | Errors | Warnings | Result |
| --- | ---: | ---: | --- |
| `date-time/lab-date-time.css` | 0 | 2 | target CSS parser errors gone |
| `menu/lab-menu-pattern.html` | 0 | 1 | target `aria-selected` / `menuitem` error gone |
| `nav-bar/lab-nav-bar-pattern.html` | 0 | 1 | target `aria-selected` / button error gone |
| `ripple/lab-ripple-pattern.html` | 0 | 3 | target required-parent error gone |

### Target Diagnostics Absence

```txt
PASS  date-time CSS nested comment parser errors
      Before: css-identifierexpected / css-lcurlyexpected / css-ruleorselectorexpected
      After:  no severity-8 CSS parser diagnostics on the target file

PASS  menu aria-selected on role=menuitem
      Before: axe/aria aria-allowed-attr severity 8
      After:  no severity-8 ARIA diagnostic on the target file

PASS  nav-bar aria-selected on button
      Before: axe/aria aria-allowed-attr severity 8
      After:  no severity-8 ARIA diagnostic on the target file

PASS  ripple standalone role=menuitem required parent
      Before: axe/aria aria-required-parent severity 8
      After:  no severity-8 ARIA diagnostic on the target file
```

### Remaining Target-File Warnings

Remaining warnings in the four target files are non-target diagnostics already
routed outside v3.6.16:

```txt
date-time/lab-date-time.css:
  severity 4 compat-api/css color-mix
  severity 4 compat-api/css scrollbar-width

menu/lab-menu-pattern.html:
  severity 4 no-inline-styles critical style

nav-bar/lab-nav-bar-pattern.html:
  severity 4 no-inline-styles critical style

ripple/lab-ripple-pattern.html:
  severity 4 compat-api/css color-mix
  severity 4 compat-api/css min-height:auto
  severity 4 no-inline-styles critical style
```

These match the v3.6.15 policy routing: Edge Tools/webhint normative policy,
no-inline-styles policy, and broad compat-api/css compatibility handling are not
part of BACKLOG #48's v3.6.16 narrow fix.

### Non-Target Diagnostics Observed

The user capture also includes non-target diagnostics outside the four target
files:

```txt
button-group/lab-button-group.css:
  severity 8 compat-api/css inline-size: fit-content / Samsung Internet

loading/lab-loading-pattern.html:
  severity 4 no-inline-styles critical style
```

These are not regressions from Route A. The button-group compatibility error was
already present in the v3.6.15 Problems panel capture and remains routed outside
v3.6.16.

## Supporting Evidence - Codex-Runnable Checks

Structural checks:

```txt
PASS  date-time/lab-date-time.css contains [EXTRACTED], not nested /* EXTRACTED */
PASS  menu/lab-menu-pattern.html contains role="menuitemcheckbox" aria-checked="true"
PASS  nav-bar/lab-nav-bar-pattern.html active specimens use aria-current="page"
PASS  ripple/lab-ripple-pattern.html menuitem lives inside role="menu"
```

Validator checks:

```txt
PASS  node --check products/reference-implementations/axismundi-lab/modules/date-time/lab-date-time.js
PASS  node --check products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
PASS  npm test
      Overall 1.000
      Axis A/B/C/D/E/F/G all 1.000
PASS  git diff --check
```

`npm test` generated validator report artifacts and they were restored after the
run. The working tree remains limited to Route A source/docs plus v3.6.16 cycle
docs.

## Q7 Trigger Check

```txt
New non-local accessibility failures caused by Route A: none observed.
Provider/runtime dependency outside fence: none observed.
Broader APG redesign need: none observed.
```

Phase 4 remains intentionally unused.

## Lock Impact

```txt
Lock 1 Axis G:       npm test Axis G 1.000, no theme.json edits
Lock 2 Axis E:       npm test Axis E 1.000, no token edits
Lock 3 core/button:  WordPress bridge implementation untouched
Lock 4 semantic row: no row/status changes
Lock 5 diagnostic:   Phase 1 accepted before Phase 2, Phase 3 verified the
                     reviewed target diagnostics before close
```

## Phase 5 Carry-Forward

```txt
1. Record target diagnostics closure:
   - DateTime CSS nested comment parser errors
   - Menu invalid aria-selected on menuitem
   - Nav bar invalid aria-selected on button
   - Ripple standalone menuitem required-parent

2. Keep broader diagnostics routed:
   - Edge Tools/webhint normative policy
   - no-inline-styles policy
   - broad compat-api/css policy
   - button-group fit-content compatibility warning

3. Record mount staleness:
   - Phase 2 review observed 22-67h stale source snapshots.
   - Local byte-clean verification confirmed mount artifacts.

4. Lock 5:
   - v3.6.16 should close as the sixth Lock 5 self-application and the fifth
     implementation-cycle application after v3.6.15's diagnostic-only variant.
```

## Phase 3 Recommendation

```txt
Recommendation: APPROVE WITH NOTES - GO for Phase 5 close

P1 blockers: none
P2 findings: none
P3 notes:
  - Non-target Edge Tools/webhint/compat warnings remain routed outside v3.6.16.
  - Mount staleness should be noted in Phase 5 close.
  - Phase 4 remains intentionally unused.
```
