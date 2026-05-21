# v3.6.6 - WP Block Bridge Ripple / Editor State Parity - Phase 3 Visual QA

Date: 2026-05-21

Phase: 3 - Visual QA

## Verdict

Phase 3 visual QA is complete.

The Phase 1 / Phase 2 Route C decision is confirmed:

```txt
Pilot ripple bridge remains Pilot-only in v3.6.6.
The bridge does not graduate into shared Ripple v2 / WordPress binding runtime.
Editor animated ripple parity is not a theme-owned target.
Editor CSS state parity is confirmed only where WordPress exposes a state.
```

No implementation files changed in Phase 3.

## Phase 2 Review Carry-Forward

Opus Phase 2 review returned:

```txt
GO
P1: none
P2: none
P3: reviewer-preference notes
```

Reviewer preference adopted:

```txt
BACKLOG #41 should remain narrow-open for:
  shared WordPress ripple runtime packaging decision
```

The editor-canvas state parity question can close for the current v3.6.x theme
bridge after this QA pass.

## Surfaces

```txt
Front end:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Editor:
  http://localhost:8888/wp-admin/post.php?post=29&action=edit
  iframe[name="editor-canvas"]

Viewport:
  390 x 900

WordPress:
  7.0
```

## Front-End Probe

Runtime:

```txt
Pilot block bridge script count: 1
typeof window.axRipple:          undefined
console/page errors:             0
```

Root tokens:

```txt
--md-sys-color-on-surface:                  #1D1B20
--md-sys-color-primary:                     #6750A4
--md-sys-color-outline-variant:             #CAC4D0
--md-sys-state-hover-state-layer-opacity:   0.08
--md-sys-state-pressed-state-layer-opacity:  0.10
--md-sys-shape-corner-small:                8px
--wp--custom--axismundi--state-layer--hover: 0.08
```

Button rest state:

```txt
href:                          #button-fill
data-ax-ripple:                bounded
data-ax-pilot-ripple-attached: true
text-decoration-line:          none
user-select:                   none
pointer-events:                auto
position:                      relative
isolation:                     isolate
overflow:                      hidden
border-radius:                 20px
::before opacity:              0
ripple count:                  0
```

Front-end state matrix:

| State | Observed values | Result |
|---|---|---|
| hover | `::before opacity: 0.08` | PASS |
| focus-visible | `outline: 2px solid rgb(103, 80, 164)` | PASS |
| pressed | `::before opacity: 0.1`; `border-radius: 8px`; `ripple count: 1` | PASS |
| disabled | `aria-disabled: true`; `pointer-events: none`; `opacity: 0.38`; no new ripple | PASS |
| selected | no `aria-pressed`, no `aria-selected`, no `.is-selected` | NOT EXPOSED / NO THEME TARGET |

Assessment:

```txt
PASS.

The front-end Pilot core/button state bridge still resolves through md-sys
tokens. The local Pilot ripple remains attached to front-end button anchors,
but no shared window.axRipple provider exists.
```

## Editor Canvas Probe

Editor iframe:

```txt
html class:
  block-editor-iframe__html

body class:
  block-editor-iframe__body editor-styles-wrapper post-type-page ...
```

Runtime:

```txt
Pilot scripts in editor iframe: 0
Pilot scripts in editor parent page: 0
typeof iframe window.axRipple: undefined
```

Root tokens:

```txt
--md-sys-color-on-surface:                  #1D1B20
--md-sys-color-primary:                     #6750A4
--md-sys-color-outline-variant:             #CAC4D0
--md-sys-state-hover-state-layer-opacity:   0.08
--md-sys-state-pressed-state-layer-opacity:  0.10
--md-sys-shape-corner-small:                8px
--wp--custom--axismundi--state-layer--hover: 0.08
```

This reconfirms that the v3.6.5 editor token fix remains intact.

Real editor button rest state:

```txt
href:                          #button-fill
data-ax-ripple:                null
data-ax-pilot-ripple-attached: null
text-decoration-line:          none
user-select:                   none
pointer-events:                none
position:                      relative
isolation:                     isolate
overflow:                      hidden
border-radius:                 20px
::before opacity:              0
ripple count:                  0
```

Editor state matrix:

| State | Observed values | Result |
|---|---|---|
| hover | link `pointer-events: none`; Gutenberg wrapper intercepts pointer; `::before opacity: 0` | NOT EXPOSED / NO THEME TARGET |
| focus-visible | `outline: 2px solid rgb(103, 80, 164)` | PASS |
| pressed | link `pointer-events: none`; `border-radius: 20px`; `::before opacity: 0`; `ripple count: 0` | NOT EXPOSED / NO THEME TARGET |
| disabled | `aria-disabled: true`; `pointer-events: none`; `opacity: 0.38` | PASS |
| selected | no `aria-pressed`, no `aria-selected`, no `.is-selected` | NOT EXPOSED / NO THEME TARGET |

Assessment:

```txt
PASS.

The editor receives the CSS state bridge and md-sys tokens. The real editor
button link does not expose hover/pressed pointer states to theme CSS because
the link has pointer-events:none and the editor block wrapper owns pointer
interaction. Animated ripple JS remains absent from the editor canvas by
enqueue scope.
```

## #44 Warning Routing

Editor probe:

```txt
console/page errors while opening editor:   56
block validation console errors:            56
UI invalid-content text count at probe time: 0
```

Assessment:

```txt
Routed only.

The console errors are the existing BACKLOG #44 editor-valid fixture / editor
compatibility territory. v3.6.6 does not repair fixture validity and does not
claim an invalid-content fix.
```

## BACKLOG #41 Recommendation

Adopt the Phase 2 reviewer-preferred route:

```txt
Keep BACKLOG #41 narrow-open for:
  shared WordPress ripple runtime packaging decision
```

Close within #41:

```txt
editor-canvas state parity for core/button in the current v3.6.x theme bridge:
  focus-visible: PASS
  disabled: PASS
  hover: not exposed / no theme target
  pressed: not exposed / no theme target
  selected: not exposed / no theme target
```

Narrow remaining question:

```txt
Should a future v3.7.x WordPress binding / plugin-custom track package the
Ripple v2 provider for WordPress surfaces, and how should it handle:
  - post-content front-end anchors
  - editor-owned content surfaces
  - forbidden ancestor policy
  - attach/detach lifecycle
  - shared token alias location
```

## Non-Goals Confirmed

```txt
pilot-block-bridge.css edit: no
pilot-block-bridge.js edit:  no
theme.json edit:             no
functions.php edit:          no
lab ripple edit:             no
components.css §0 edit:      no
styleguide edit:             no
fixture edit:                no
custom block work:           no
plugin runtime work:         no
TT5-derived implementation:  no
```

## Validation

```powershell
wp-env run cli wp core version
  PASS - 7.0

python tools\generators\build_pilot_specimen_wall.py
  PASS - updated specimen wall page 29

npm run validate:specimen-wall
  PASS

php -l products\reference-implementations\axismundi-pilot\functions.php
  PASS

npm test
  PASS - overall 1.000; Axis A/B/C/D/E/F/G all 1.000

npm run validate:computed
  PASS

git diff --check
  PASS
```

Validator-generated report files were restored after validation because Phase 3
does not change validator artifacts.

## Phase 3 Exit Criteria

```txt
front-end state matrix recorded with numeric values: PASS
editor state matrix recorded with numeric values: PASS
Pilot runtime JS absent from editor iframe: PASS
v3.6.5 editor md-sys token parity preserved: PASS
#44 console error count routed: PASS
BACKLOG #41 narrow-open recommendation recorded: PASS
implementation files edited: no
```

## Next

Submit this Phase 3 visual QA report for Opus review.

After GO, proceed to Phase 5 close with BACKLOG #41 narrowed open for the
shared WordPress ripple runtime packaging decision.
