# v3.6.6 - WP Block Bridge Ripple / Editor State Parity - Phase 1 Report

Date: 2026-05-21

Phase: 1 - Ripple / State Parity Inventory

## Verdict

Phase 1 inventory is complete. No implementation files were edited.

Selected route:

```txt
C. True shared runtime needs packaging/plugin/custom binding work; defer
   graduation and keep Pilot-only for now.
```

Secondary conclusion:

```txt
No Phase 2 state CSS patch is selected.
```

Rationale:

- The Pilot front-end ripple bridge works for `core/button` anchors, but it is
  a Pilot-local minimal runtime, not the Ripple v2 provider contract.
- The editor canvas does not load Pilot runtime JS and should not be made an
  animated ripple target in this theme cycle.
- Real editor `core/button` link markup has `pointer-events: none`; Gutenberg's
  block wrapper owns pointer interaction. Hover/pressed animated ripple is not
  exposed as a theme-owned editor target.
- CSS state-layer rules themselves land in the editor iframe. A temporary DOM
  probe confirmed hover/pressed state-layer behavior, but real editor
  `core/button` pointer states are blocked by editor mechanics.
- Selected state is not exposed for `core/button` anchors in either front end
  or editor canvas.

## Phase 0 Review Carry-Forward

Opus Phase 0 review returned:

```txt
APPROVE WITH NOTES
P1: none
P2: strengthen Phase 1 reading list
P3: non-blocking notes
```

P2 response:

```txt
This report records the full Phase 0 / Phase 1 reading list below, including
v3.6.3 semantic decisions, FEEDBACK-AND-STRATEGY §1-2, the v3.6.0 Pilot
lessons document, and the current state/handoff docs.
```

P3-5 local status response:

```txt
Codex local git status before Phase 1:
  ## main...origin/main [ahead 1]

No unstaged or untracked work was present before Phase 1 began.
```

Phase 0 review verdict is treated as chat-relayed review evidence. No
`*-PHASE-0-REVIEW.md` artifact was created.

## Reading List

### Phase 0 Actually Read

```txt
AGENTS.md
CURRENT-STATE.md
PROJECT-CONTEXT.md
NEXT-SESSION.md
CHANGELOG.md latest entry
ROADMAP.md current tail
BACKLOG.md #41 / #44 / #21 / #14
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
docs/v3.5.0/PROMOTION-CRITERIA.md G1-G26
docs/v3.5.6/RIPPLE-V2-PHASE-0-PLAN.md
docs/v3.5.6/RIPPLE-V2-PHASE-0-REPORT.md
docs/v3.5.6/RIPPLE-V2-PHASE-2-PLAN.md
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/functions.php
```

### Phase 1 Additional Reads

```txt
CLAUDE.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
tools/validators/validate_pilot_computed_styles.js
tools/validators/validate_pilot_specimen_wall.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

## Governing Prior Decisions

v3.6.3 semantic decisions remain binding:

```txt
core/button anchor with href = valid navigation receiving M3 button visuals.
Action behavior, form submission, AJAX, federation actions, and durable custom
schemas remain plugin/custom-block territory.
```

v3.6.0 reverse bridge order remains binding:

```txt
Markdown / HTML defaults -> WordPress core block -> core reset -> bridge ->
M3 mapping -> interaction bridge -> computed-style audit.
```

Ripple v2 hierarchy remains binding:

```txt
components.css §0 = static state-layer foundation
ripple/            = animated pointer ripple progressive enhancement
```

Editor-ripple boundary for this cycle:

```txt
Animated ripple JS must not enter the editor canvas in v3.6.6.
Editor parity target is CSS state-layer parity where WordPress exposes a
state. It is not animated ripple parity.
```

Token alias policy for any future Route B patch:

```txt
If a Pilot-only convergence patch later needs --md-ripple-* or --ax-ripple-*
aliases, declare them only inside Pilot bridge CSS / Pilot copied asset CSS for
that route. Do not backflow those aliases into lab ripple, styleguide, or
components.css §0 during this cycle. Any alias must remain downstream-routed
through existing md-sys/md-ref values; no literal md-sys color values.
```

## Static Inventory

### Pilot Runtime

Files:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

Current behavior:

```txt
selector:        .wp-block-button__link
attach marker:   data-ax-pilot-ripple-attached="true"
ripple marker:   data-ax-ripple="bounded"
runtime API:     none
global API:      no window.axRipple
mode support:    bounded only
disabled guard:  aria-disabled="true" or .is-disabled
forbidden guard: none in Pilot runtime
```

Inserted ripple node:

```txt
span.ax-ripple
aria-hidden="true"
inline --ax-ripple-size / --ax-ripple-x / --ax-ripple-y
```

### Ripple v2 Provider Contract

Files:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

Provider contract:

```txt
[data-ax-ripple]
[data-ax-ripple="bounded"]
[data-ax-ripple="unbounded"]
.ax-ripple-host
.ax-ripple-host--bounded
.ax-ripple-host--unbounded
window.axRipple.attach(control, options?)
window.axRipple.detach(control)
window.axRipple.refresh(root?)
forbidden ancestors: .prose, .wp-block-post-content, .entry-content, [contenteditable]
```

Required provider tokens:

```txt
--md-ripple-hover-color
--md-ripple-pressed-color
--ax-ripple-hover-color
--ax-ripple-pressed-color
--ax-ripple-hover-opacity
--ax-ripple-pressed-opacity
```

### Contract Delta

```txt
Pilot front-end bridge:
  data-ax-ripple marker: yes
  .ax-ripple insertion: yes
  aria-hidden ripple node: yes
  window.axRipple API: no
  attach/detach/refresh: no
  .ax-ripple-host classes: no
  unbounded mode: no
  forbidden ancestor policy: no
  --md-ripple-* aliases: no
  --ax-ripple-* aliases: no

Conclusion:
  Pilot bridge is a local progressive enhancement for core/button. It is not
  the shared Ripple v2 runtime and should not be called graduated.
```

## Source / Asset Mirror Check

Bridge source and copied Pilot assets are currently in lockstep:

```txt
pilot-block-bridge.css source SHA256:
  5D52091467E6DE2C13FF6E03D27181EB8349A5FD7736E6CBB47B709EFC716552

pilot-block-bridge.css asset SHA256:
  5D52091467E6DE2C13FF6E03D27181EB8349A5FD7736E6CBB47B709EFC716552

pilot-block-bridge.js source SHA256:
  D2506A65D38AA265D999FD4A6D43DEE45BA1B83E9AF538672F7545A0091DBA16

pilot-block-bridge.js asset SHA256:
  D2506A65D38AA265D999FD4A6D43DEE45BA1B83E9AF538672F7545A0091DBA16
```

## Runtime Probes

Environment:

```txt
wp-env: started
WordPress core: 7.0
Specimen wall: rebuilt page 29
Viewport: 390 x 900
```

### Front End

Surface:

```txt
http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
```

Pilot runtime:

```txt
script loaded:
  /wp-content/themes/axismundi-pilot/assets/scripts/pilot-block-bridge.js

typeof window.axRipple:
  undefined
```

Root tokens:

```txt
--md-sys-color-on-surface:                 #1D1B20
--md-sys-color-primary:                    #6750A4
--md-sys-state-hover-state-layer-opacity:  0.08
--md-sys-state-pressed-state-layer-opacity: 0.10
--md-sys-shape-corner-small:               8px
--wp--custom--axismundi--state-layer--hover: 0.08

--md-ripple-hover-color:     empty
--md-ripple-pressed-color:   empty
--ax-ripple-pressed-opacity: empty
```

Button rest state:

```txt
tag:                            a
href:                           #button-fill
data-ax-ripple:                 bounded
data-ax-pilot-ripple-attached:  true
text-decoration-line:           none
user-select:                    none
pointer-events:                 auto
position:                       relative
isolation:                      isolate
overflow:                       hidden
border-radius:                  20px
::before opacity:               0
ripple count:                   0
```

Front-end state matrix:

| State | Exposed | Observed | Result |
|---|---:|---|---|
| hover | yes | `::before` opacity ~0.08 | PASS |
| focus-visible / focus | yes | outline `2px solid rgb(103, 80, 164)` | PASS |
| pressed / active | yes | radius morph toward 8px; `::before` opacity ~0.10; ripple count 1 | PASS |
| disabled / aria-disabled | yes | pointer-events none; opacity 0.38; no new ripple created | PASS |
| selected | no | no `aria-pressed`, `aria-selected`, or `is-selected` on button links | NOT EXPOSED / NO THEME TARGET |

Front-end ripple details:

```txt
pointerdown created .ax-ripple: yes
.ax-ripple aria-hidden: yes
disabled pointerdown created new ripple: no
```

### Editor Canvas

Surface:

```txt
http://localhost:8888/wp-admin/post.php?post=29&action=edit
iframe[name="editor-canvas"]
```

Editor iframe:

```txt
html class:
  block-editor-iframe__html

body class:
  block-editor-iframe__body editor-styles-wrapper post-type-page ...
```

Pilot runtime:

```txt
Pilot scripts in editor iframe: []
Pilot scripts in editor parent page: []
typeof iframe window.axRipple: undefined
```

Interpretation:

```txt
The editor canvas receives editor styles, not the Pilot front-end runtime JS.
Animated ripple runtime is not present in the editor and should not be added in
this theme cycle.
```

Editor root tokens:

```txt
--md-sys-color-on-surface:                 #1D1B20
--md-sys-color-primary:                    #6750A4
--md-sys-state-hover-state-layer-opacity:  0.08
--md-sys-state-pressed-state-layer-opacity: 0.10
--md-sys-shape-corner-small:               8px
--wp--custom--axismundi--state-layer--hover: 0.08

--md-ripple-hover-color:     empty
--md-ripple-pressed-color:   empty
--ax-ripple-pressed-opacity: empty
```

Real editor `core/button` rest state:

```txt
tag:                            a
href:                           #button-fill
data-ax-ripple:                 null
data-ax-pilot-ripple-attached:  null
text-decoration-line:           none
user-select:                    none
pointer-events:                 none
position:                       relative
isolation:                      isolate
overflow:                       hidden
border-radius:                  20px
::before opacity:               0
ripple count:                   0
```

Observed editor pointer exposure:

```txt
Playwright hover on the real .wp-block-button__link timed out because a
Gutenberg block wrapper intercepted pointer events. The link itself has
pointer-events: none.
```

Editor real state matrix:

| State | Exposed | Observed | Result |
|---|---:|---|---|
| hover | no | link has pointer-events none; wrapper intercepts pointer | NOT EXPOSED / NO THEME TARGET |
| focus-visible / focus | yes | outline `2px solid rgb(103, 80, 164)` | PASS |
| pressed / active | no | pointer down does not reach link; no active radius/opacity | NOT EXPOSED / NO THEME TARGET |
| disabled / aria-disabled | yes as CSS attribute probe | pointer-events none; opacity 0.38 | PASS |
| selected | no | no `aria-pressed`, `aria-selected`, or `is-selected` on button links | NOT EXPOSED / NO THEME TARGET |

Temporary DOM probe inside the editor iframe confirmed the CSS rules themselves
are present when pointer interaction reaches a `.wp-block-button__link`:

| Probe state | Observed |
|---|---|
| hover | `::before` opacity ~0.08 |
| focus | `::before` opacity 0.08 |
| pressed | radius morph toward 8px; `::before` opacity ~0.10 |
| disabled | pointer-events none; opacity 0.38 |

Assessment:

```txt
CSS state-layer parity exists in the editor styles. The real editor block
surface does not expose hover/pressed pointer states on the link because
Gutenberg owns editor block interaction. No theme CSS patch is selected.
```

## #44 Editor Compatibility Note

The editor compatibility warning remains #44 territory.

Probe evidence:

```txt
console error count while opening editor: 56
block validation error count:            56
UI invalid-content text count:            0
```

Interpretation:

```txt
The warning appears as WordPress block validation console errors in this probe,
even though the exact UI text was not visible/countable at probe time. This is
the same editor-valid fixture territory routed to BACKLOG #44. v3.6.6 does not
repair fixture validity.
```

## Route Bucket Decision

### Bucket A

```txt
Pilot bridge already matches the minimum theme-owned runtime need; keep
Pilot-only and document no graduation.
```

Assessment:

```txt
Partially true for front-end core/button only. The bridge works as a Pilot
local enhancement, but the stronger conclusion is that graduation requires
work outside this cycle.
```

### Bucket B

```txt
Pilot bridge duplicates Ripple v2 enough that it should converge with the
provider contract in the Pilot assets.
```

Assessment:

```txt
Not selected.
```

Why:

```txt
Convergence would need API and contract decisions:
  window.axRipple or no global?
  provider classes or Pilot-only selectors?
  forbidden ancestor behavior in WordPress post content?
  token alias declaration location?
  editor runtime boundary?

Those are shared-runtime packaging decisions, not a narrow v3.6.6 patch.
```

### Bucket C

```txt
True shared runtime needs packaging/plugin/custom binding work; defer
graduation and keep Pilot-only for now.
```

Assessment:

```txt
Selected.
```

Why:

```txt
Ripple v2 is a lab infrastructure provider contract. The Pilot currently
copies a small subset into a theme-specific bridge. Promoting it to shared
WordPress binding runtime would require a packaging surface and editor/prose
boundary policy, especially because Ripple v2 forbids .wp-block-post-content
and [contenteditable] ancestors while the Pilot's front-end target is exactly
.wp-block-post-content.
```

### Bucket D

```txt
Editor state parity has a concrete CSS mismatch independent of ripple
graduation; patch the state bridge only.
```

Assessment:

```txt
Not selected.
```

Why:

```txt
The editor CSS state rules land. Real hover/pressed states are not exposed on
the editor link surface. Focus and disabled states already resolve. Selected
state has no exposed core/button target.
```

### Bucket E

```txt
Other, with evidence.
```

Assessment:

```txt
Not selected.
```

## Phase 1 Exit Criteria

```txt
Before values recorded for front end and editor canvas: PASS
State matrix records pass/fail/not-exposed for each state: PASS
Ripple graduation route A/B/C/D/E selected with evidence: PASS (C)
Implementation route chosen before any patch: PASS
Implementation files edited in Phase 1: no
```

## Validation State

Commands run during Phase 1:

```powershell
wp-env start
wp-env run cli wp core version
python tools\generators\build_pilot_specimen_wall.py
git diff --exit-code -- products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

Results:

```txt
wp-env start: PASS
WordPress core: 7.0
build_pilot_specimen_wall.py: PASS - updated specimen wall page 29
source/asset bridge diff: PASS - no drift
```

Full validation is not rerun in Phase 1 because no implementation files were
edited. It remains mandatory after any Phase 2 patch and at Phase 5 close.

Current git scope for Phase 1:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
```

## Recommended Phase 2 Route

Recommended Phase 2 is a no-code route decision report:

```txt
No implementation patch.
Record that Pilot ripple remains Pilot-only for v3.6.6.
Record that true shared WordPress ripple runtime is deferred to future
packaging/plugin/custom binding strategy.
Record that editor state parity is CSS-only where exposed, and animated ripple
does not enter the editor canvas.
```

Phase 2 should not edit:

```txt
pilot-block-bridge.css
pilot-block-bridge.js
theme.json
functions.php
lab ripple files
styleguide
```

## Next

Submit this Phase 1 inventory for Opus review.

Do not edit implementation files unless Phase 1 review explicitly changes the
selected route and gives Phase 2 patch GO.
