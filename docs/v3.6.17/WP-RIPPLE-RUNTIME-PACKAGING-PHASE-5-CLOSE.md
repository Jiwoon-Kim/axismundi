# v3.6.17 - WP Ripple Runtime Packaging Decision - Phase 5 Close

Date: 2026-05-23
Status: CLOSED
Backlog: #41 resolved by v3.6.17
Route: Layered Route D / C / A no-code decision

## Verdict

v3.6.17 closes the remaining BACKLOG #41 shared WordPress ripple runtime
packaging decision.

Layered route:

```txt
Route D = packaging policy:
  Split CSS state-layer parity from animated JS ripple.

Route C = future owner classification:
  Shared animated WordPress ripple runtime belongs to a future plugin/custom-
  binding or dedicated WordPress runtime package if pursued.

Route A = v3.6.17 execution shape:
  no-code decision report.
```

No provider, Pilot bridge, WordPress enqueue, generator, validator, or
styleguide implementation files changed.

## Documents

```txt
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-0-PLAN.md
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-1-REPORT.md
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-2-DECISION.md
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-3-VISUAL-QA.md
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-5-CLOSE.md
```

## Closed In #41

v3.6.6 narrowed BACKLOG #41 to one remaining question:

```txt
Should a future v3.7.x WordPress binding / plugin-custom track package the
Ripple v2 provider for WordPress surfaces?
```

v3.6.17 answers that question:

```txt
Theme/Pilot keeps CSS state-layer parity for current WordPress core block
surfaces.

The shared animated WordPress ripple runtime does not graduate from the lab
Ripple v2 provider into the Pilot/theme in v3.6.17.

If shared animated ripple for WordPress content is pursued later, it must be
packaged as a future plugin/custom-binding runtime or dedicated WordPress
runtime package with explicit post-content, editor-surface, lifecycle, and
token-alias policy.
```

## Sub-Decisions

```txt
1. Post-content anchors:
   Do not graduate lab Ripple v2 as-is onto .wp-block-post-content anchors
   because the lab provider forbids that ancestor. The current Pilot-only
   button ripple may remain local evidence, not shared runtime authority.

2. Editor-owned surfaces:
   No animated ripple runtime enters editor content in v3.6.17. Editor parity
   remains CSS state-layer parity where WordPress exposes a state.

3. Forbidden ancestors:
   Preserve the lab Ripple v2 .prose / .wp-block-post-content /
   .entry-content / [contenteditable] guards.

4. Lifecycle:
   The current Pilot DOMContentLoaded scan is not a shared runtime lifecycle.
   A future package needs explicit attach/detach/refresh or equivalent
   WordPress bootstrap.

5. Token aliases:
   No shared alias relocation in v3.6.17. Future runtime aliases must remain
   downstream from md-sys/md-ref and must not become wp-custom literals.
```

## Phase 3 Evidence

Validation:

```txt
PASS  php -l products/reference-implementations/axismundi-pilot/functions.php
PASS  npm test
      Overall 1.000
      Axis A/B/C/D/E/F/G all 1.000
PASS  python tools/generators/build_pilot_specimen_wall.py
PASS  npm run validate:specimen-wall
PASS  npm run validate:computed
PASS  git diff --check
```

No-code front-end smoke:

```txt
pilot-block-bridge.js loaded: yes
pilot-block-bridge.css loaded: yes
typeof window.axRipple:       undefined
.wp-block-button__link count: 5
inside .wp-block-post-content: 5
Pilot-only attached markers:  5
data-ax-ripple markers:       5
console/page errors:          0
```

`npm test` generated `binding_legitimacy_audit.json` and
`pilot_validation_report.md` line-ending churn; generated artifacts were
restored before close.

## Lock Compliance

```txt
Lock 1 - wp-custom downstream-only:
  Preserved. Ripple aliases were not moved into theme.json
  settings.custom.axismundi.* as literal values.

Lock 2 - md-sys color maps to md-ref:
  Preserved. Future runtime color aliases are required to route through
  md-sys/md-ref; no literal md-sys color values were introduced.

Lock 3 - core/button semantic route before visual cleanup:
  Preserved. v3.6.3's core/button anchor route was not reopened.

Lock 4 - semantic mismatch handling:
  Preserved. Post-content anchors and editor-owned surfaces were routed as
  packaging/runtime policy, not patched over in theme code.

Lock 5 - diagnostic-first before implementation:
  Preserved. Phase 1 diagnostic selected the route before Phase 2; Phase 2 was
  a no-code decision report.
```

Lock 5 count:

```txt
v3.6.17 is the seventh clean Lock 5 self-application overall.
The fifth implementation-cycle application count remains unchanged because
v3.6.17 is a no-code packaging-decision variant.
```

## Non-Goals Confirmed

```txt
BACKLOG #46 disabled ripple host hygiene: not touched.
BACKLOG #47 popover provider hygiene: not touched.
BACKLOG #21 Interpreter Plugin: not implemented or designed.
BACKLOG #44 specimen coverage / validator hardening: not touched.
Pilot revision: not included.
No custom block registration.
No Gutenberg core modification.
No theme.json edits.
No functions.php edits.
No lab ripple edits.
No Pilot bridge edits.
No build_pilot_assets.py edits.
No validator edits.
No styleguide edits.
No no-inline-styles / compat-api/css / Edge Tools / webhint policy work.
```

## Phase 4

Phase 4 was intentionally unused.

Reason:

```txt
Phase 1 provided enough evidence for the no-code decision, and Phase 3
validated the current runtime state without deeper architecture-audit need.
```

## Routed Forward

Candidate set after #41 close:

```txt
BACKLOG #21 Interpreter Plugin strategy
BACKLOG #44 remaining specimen coverage follow-ons
BACKLOG #46 disabled ripple host authoring hygiene
BACKLOG #47 popover provider menu-item-class logic extraction hygiene
Pilot theme revision
Sheet drag-to-dismiss follow-on
Styleguide integration for Slider / Loading / Progress module pages
diagnostics policy follow-ons:
  VS Code workspace diagnostics config
  Microsoft Edge Tools / webhint normative policy
  no-inline-styles policy
  broad compat-api/css policy
  button-group inline-size: fit-content compatibility warning
```

Future shared animated WordPress ripple runtime, if pursued, should be opened
as a new plugin/custom-binding or dedicated WordPress runtime packaging item,
not as a theme-side reopening of #41.

## Close Verdict

```txt
v3.6.17 CLOSED
BACKLOG #41 RESOLVED
P1 blockers: none
P2 findings: none
Phase 4: intentionally unused
```
