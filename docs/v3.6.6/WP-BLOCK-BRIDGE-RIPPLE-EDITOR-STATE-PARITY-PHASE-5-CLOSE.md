# v3.6.6 - WP Block Bridge Ripple / Editor State Parity - Phase 5 Close

Date: 2026-05-21

Phase: 5 - Close

## Verdict

v3.6.6 is closed as a BACKLOG #41 ripple / editor-state diagnostic and routing
cycle.

The cycle confirms:

```txt
Pilot ripple bridge graduates: no
Pilot ripple bridge remains Pilot-only: yes
Editor animated ripple parity target: no
Editor CSS state-layer parity where exposed: yes
```

BACKLOG #41 remains narrow-open for one future question:

```txt
shared WordPress ripple runtime packaging decision
```

No implementation files changed in this cycle.

## Commits

```txt
0d45daf  Add v3.6.6 ripple editor state parity plan
3955b1f  Document v3.6.6 ripple editor state inventory
999b434  Record v3.6.6 ripple route decision
256d29f  Record v3.6.6 ripple editor visual QA
```

## Documents

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
```

## Closed In #41

Editor-canvas state parity for `core/button` is closed for the current v3.6.x
theme bridge scope:

```txt
focus-visible: PASS
disabled:      PASS
hover:         not exposed / no theme target
pressed:       not exposed / no theme target
selected:      not exposed / no theme target
```

Reasoning:

```txt
The editor receives md-sys tokens and the CSS state bridge.
The real editor .wp-block-button__link has pointer-events:none, so Gutenberg's
block wrapper owns hover/pressed pointer interaction.
core/button anchors expose no selected state in the current theme bridge.
```

## Narrow-Open In #41

The remaining #41 question is:

```txt
Should a future v3.7.x WordPress binding / plugin-custom track package the
Ripple v2 provider for WordPress surfaces?
```

Sub-decisions to preserve:

```txt
1. post-content front-end anchors
2. editor-owned content surfaces
3. forbidden ancestor policy
4. attach/detach lifecycle
5. shared token alias location
```

This is not a v3.6.6 theme bridge implementation task.

## Route C Evidence

Phase 1 selected and Phase 2/3 confirmed Route C:

```txt
True shared runtime needs packaging/plugin/custom binding work; defer
graduation and keep Pilot-only for now.
```

The corrected Route C rationale:

```txt
Pilot front-end runtime currently attaches to .wp-block-button__link rendered
inside .wp-block-post-content. Ripple v2's FORBIDDEN_ANCESTORS policy
(closest('.prose, .wp-block-post-content, .entry-content, [contenteditable]'))
would refuse provider-runtime attachment on that surface.
```

Contract delta:

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
```

## Phase 3 Evidence

Front-end:

```txt
Pilot block bridge script count: 1
typeof window.axRipple:          undefined
console/page errors:             0

hover ::before opacity:          0.08
focus outline:                   2px solid rgb(103, 80, 164)
pressed ::before opacity:        0.1
pressed border-radius:           8px
pressed ripple count:            1
disabled opacity:                0.38
disabled new ripple:             no
selected state:                  not exposed
```

Editor:

```txt
Pilot scripts in editor iframe:  0
Pilot scripts in parent page:    0
typeof iframe window.axRipple:   undefined

real link pointer-events:        none
focus outline:                   2px solid rgb(103, 80, 164)
disabled pointer-events:         none
disabled opacity:                0.38
hover state:                     not exposed / no theme target
pressed state:                   not exposed / no theme target
selected state:                  not exposed / no theme target
```

Editor root tokens preserved from v3.6.5:

```txt
--md-sys-color-on-surface:                  #1D1B20
--md-sys-color-primary:                     #6750A4
--md-sys-color-outline-variant:             #CAC4D0
--md-sys-state-hover-state-layer-opacity:   0.08
--md-sys-state-pressed-state-layer-opacity:  0.10
--md-sys-shape-corner-small:                8px
--wp--custom--axismundi--state-layer--hover: 0.08
```

## #44 Forward Routing

v3.6.6 Phase 1 and Phase 3 observed:

```txt
editor open console errors:           56
block validation console error count: 56
```

This remains BACKLOG #44 editor-valid fixture / editor compatibility work.
v3.6.6 does not repair fixture validity and does not claim an invalid-content
fix.

## Lock Compliance

```txt
Lock 1 - wp-custom downstream-only:
  preserved; Axis G remains 1.000.

Lock 2 - md-sys color maps to md-ref:
  preserved; Axis E remains 1.000.

Lock 3 - core/button semantic route before visual cleanup:
  preserved; v3.6.3's core/button anchor route was not reopened.

Lock 4 - semantic mismatch handling rule:
  preserved; selected state was classified as not exposed / no theme target
  rather than faked through a visual patch.
```

## Methodology Finding

Diagnostic-first Phase 1 worked again in v3.6.6, after v3.6.5.

Decision:

```txt
Do not promote this to Lock 5 in v3.6.6.
```

Reason:

```txt
The pattern is useful and should stay available for unknown failure modes, but
this cycle does not need a new close-time operating rule. Reconsider after more
cycles prove the pattern across a broader set of work.
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

Final close validation:

```txt
wp-env run cli wp core version: 7.0
python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS (Axis A/B/C/D/E/F/G all 1.000)
npm run validate:computed: PASS
git diff --check: PASS
```

## Next Route

Recommended primary next route:

```txt
BACKLOG #44 specimen follow-on coverage + editor compatibility
```

Other viable routes remain:

```txt
Wave 2 plan-first
BACKLOG #21 Interpreter Plugin strategy
BACKLOG #41 shared WordPress ripple runtime packaging decision
```

The next cycle must remain plan-first.
