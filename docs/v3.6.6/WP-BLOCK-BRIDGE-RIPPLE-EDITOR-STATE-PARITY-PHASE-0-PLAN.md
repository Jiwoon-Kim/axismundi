# v3.6.6 - WP Block Bridge Ripple / Editor State Parity - Phase 0 Plan

Date: 2026-05-21

Phase: 0 - Plan

## User Request Log

User requested:

```txt
Read NEXT-SESSION.md §0 order and propose/write the next Phase 0 plan for the
selected candidate.
```

Current baseline supplied by user:

```txt
origin/main = 2baecbb
v3.6.0 through v3.6.5 are closed.
WordPress core in wp-env is 7.0.
v3.6.x cadence uses Phase 0 / 1 / 2 / 3 / 5.
Phase 4 intentionally unused.
```

The selected candidate is BACKLOG #41:

```txt
ripple/editor state parity follow-on
```

This is selected before BACKLOG #44 because #44 is explicitly framed as
follow-on coverage/editor compatibility after #41 scoping, and because #41 now
has only two remaining questions after v3.6.5:

```txt
1. decide whether the Pilot ripple bridge graduates or remains Pilot-only
2. verify editor-canvas parity for hover/focus/pressed/disabled/selected states
```

## Cycle Frame

v3.6.6 is a diagnostic-first BACKLOG #41 cycle.

The cycle starts by classifying the current Pilot ripple bridge against the
existing Ripple v2 infrastructure contract before any implementation patch.
It also records which editor-canvas states are real acceptance targets and
which states are not exposed by WordPress editor markup in a theme-only bridge.

The expected outcome is one of:

```txt
A. keep ripple Pilot-only, with documented reasons and no runtime graduation
B. converge Pilot bridge with the existing Ripple v2 contract in place
C. defer true shared WordPress ripple runtime to plugin/custom binding territory
D. apply narrow state-parity CSS only after Phase 1 proves a concrete mismatch
```

Phase 1 must choose the route with evidence before Phase 2 patches.

## Source Inputs

Required reading before Phase 1:

```txt
NEXT-SESSION.md
BACKLOG.md #41 / #44 / #21 / #14
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
docs/v3.5.6/RIPPLE-V2-PHASE-0-REPORT.md
docs/v3.5.6/RIPPLE-V2-PHASE-2-PLAN.md
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/functions.php
```

TT5 may be read only as a future selector/theme.json structure reference:

```txt
C:\Users\thaum\dev\twentytwentyfive.1.5\twentytwentyfive
```

TT5 must not be used as an Axismundi token or visual-style source.

## Active Locks

The cycle preserves all four close-time locks:

```txt
Lock 1 - wp-custom downstream-only
Lock 2 - md-sys color maps to md-ref
Lock 3 - core/button semantic route before visual cleanup
Lock 4 - semantic mismatch handling rule
```

Additional ripple-specific boundary from v3.5.6:

```txt
components.css §0 owns static hover/focus/pressed state-layer behavior.
ripple/ owns animated pointer ripple as progressive enhancement.
Ripple must not replace focus-visible or become the only state affordance.
```

## Scope

### In Scope

1. Inventory the Pilot-specific button ripple bridge:
   `data-axPilotRippleAttached`, inserted `.ax-ripple` spans, reduced-motion
   behavior, disabled guards, and current token usage.
2. Compare it to the Ripple v2 provider contract:
   `[data-ax-ripple]`, `.ax-ripple-host*`, `window.axRipple.attach/detach/refresh`,
   bounded/unbounded modes, forbidden ancestors, reduced motion, and token aliases.
3. Decide whether the Pilot bridge should remain Pilot-only or graduate toward
   a shared WordPress binding runtime.
4. Verify editor-canvas parity for states that WordPress exposes or can be
   meaningfully probed:
   hover, focus/focus-visible, pressed/active, disabled, and selected.
5. Record state-by-state findings separately for front end and editor canvas.
6. Patch only after Phase 1 has selected a route and Phase 1 review gives GO.

### Out of Scope

```txt
custom block registration 안 함.
plugin implementation 안 함.
Interpreter Plugin BACKLOG #21 구현 안 함.
BACKLOG #44 editor-invalid-content fixture repair 안 함.
TT5-derived implementation 안 함.
theme.json token/value rewrite 안 함.
components.css §0 baseline rewrite 안 함.
styleguide direct edit 안 함.
Carousel / BACKLOG #38 포함 안 함.
```

Additional non-goals:

- Do not reopen the v3.6.3 core/button anchor semantic route.
- Do not treat keyboard-centered ripple as required; v3.5.6 chose pointer-only
  ripple with keyboard focus-visible state-layer.
- Do not promote a shared runtime if Phase 1 finds that editor/prose forbidden
  ancestor boundaries make theme-side graduation unsafe.
- Do not absorb #44's invalid-content warning into this cycle; log it only as
  an editor compatibility background condition.

## Phase Partition

### Phase 0 - Plan

Artifact:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
```

Exit criteria:

- Selected candidate is named as BACKLOG #41.
- Ripple graduation and editor state parity are both included.
- Diagnostic-first Phase 1 is required before implementation.
- Locks 1-4 and Ripple v2 state-layer hierarchy are explicit.
- #44 remains out of scope except as routed editor-warning context.

### Phase 1 - Ripple / State Parity Inventory

No implementation files may be edited in Phase 1.

Inventory tasks:

```txt
Pilot runtime:
  - Does the front end load only pilot-block-bridge.js?
  - Does the editor canvas load any Pilot runtime JS?
  - Does .wp-block-button__link receive data-ax-ripple and/or
    data-ax-pilot-ripple-attached?
  - Does pointerdown create aria-hidden .ax-ripple spans?
  - Are disabled and aria-disabled hosts skipped?
  - Does reduced motion avoid radial expansion?

Ripple v2 contract comparison:
  - data-ax-ripple contract
  - .ax-ripple-host classes
  - bounded/unbounded mode support
  - window.axRipple attach/detach/refresh API
  - forbidden ancestor policy
  - --md-ripple-* and --ax-ripple-* token aliases

Editor state parity:
  - hover state-layer opacity where probeable
  - focus-visible outline and token route
  - pressed/active border-radius and state-layer opacity where probeable
  - disabled visual and event suppression where markup exposes disabled state
  - selected state only where WordPress markup exposes a meaningful selected
    or pressed attribute/class; otherwise mark "not exposed / no theme target"
```

Root-cause / route buckets:

```txt
A. Pilot bridge already matches the minimum theme-owned runtime need; keep
   Pilot-only and document no graduation.
B. Pilot bridge duplicates Ripple v2 enough that it should converge with the
   provider contract in the Pilot assets.
C. True shared runtime needs packaging/plugin/custom binding work; defer
   graduation and keep Pilot-only for now.
D. Editor state parity has a concrete CSS mismatch independent of ripple
   graduation; patch the state bridge only.
E. Other, with evidence.
```

Expected artifact:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
```

Exit criteria:

- Before values recorded for front end and editor canvas.
- State matrix records pass/fail/not-exposed for each state.
- Ripple graduation route A/B/C/D/E selected with evidence.
- Implementation route is chosen before any patch.

### Phase 2 - Selected Patch Or Route Decision

Patch only the route selected in Phase 1.

Likely files if Phase 1 selects a Pilot bridge convergence patch:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

Possible patch shapes:

```txt
State-only patch:
  narrow CSS adjustment for editor/front-end parity after a reproduced mismatch.

Pilot convergence patch:
  align Pilot runtime naming/token usage with Ripple v2 without introducing a
  repo-wide shared runtime package.

No-code decision:
  document that shared graduation is deferred or rejected for this cycle.
```

Patch constraints:

- Source bridge and copied Pilot asset bridge must remain byte-identical.
- New ripple color values must route through existing md-sys/md-ref tokens.
- No literal hex/rgb/hsl color values for md-sys color roles.
- No new wp-custom literal values.
- No custom blocks or plugin behavior.
- No `theme.json` edit unless Phase 1 review explicitly expands scope.

Expected artifact:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
```

Exit criteria:

- Selected route is implemented or explicitly documented as no-code.
- Front-end button state behavior remains PASS.
- Editor-canvas state parity is improved or explicitly classified as
  not-exposed / non-theme-owned.
- Source/asset mirror drift is absent.

### Phase 3 - Visual / Interaction QA

Surfaces:

```txt
Front end:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Editor:
  http://localhost:8888/wp-admin/post.php?post=29&action=edit
  iframe[name="editor-canvas"]
```

Evidence to record:

```txt
Front end:
  .wp-block-button__link href remains present
  text-decoration remains none
  user-select remains none
  hover opacity = 0.08
  pressed opacity = 0.10 or documented ripple pressed opacity route
  focus-visible outline remains visible
  disabled/aria-disabled no ripple
  pointerdown creates and cleans aria-hidden ripple only where in scope

Editor:
  token values from v3.6.5 remain present
  button hover/focus/pressed/disabled/selected state matrix recorded
  no new invalid-content repair claim
```

Expected artifact:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
```

Exit criteria:

- State parity matrix is complete.
- Runtime decision is reflected in observed behavior.
- #44 invalid-content warning remains routed, not claimed fixed.
- Console/page errors absent in an extension-free browser run.

### Phase 5 - Close

Close artifacts:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

`AGENTS.md` / `CLAUDE.md` should update only if Phase 5 promotes a new lock or
changes operating rules.

Exit criteria:

- BACKLOG #41 records whether ripple graduation is closed, deferred, or still
  open with a narrower question.
- BACKLOG #41 records editor state parity findings by state.
- BACKLOG #44 remains owner of editor-invalid-content/editor-valid fixture work.
- Final validation is recorded.

## Applicable G1-G26 Gates

Universal gates:

```txt
G1. validate_theme_pilot.py / npm test PASS
G2. Baseline untouched for components.css §0 and styleguide authoring surfaces
G5. CHANGELOG entry at Phase 5
```

Infrastructure gates:

```txt
G22. Multi-consumer requirement preserved or graduation rejected/deferred
G23. Semantic neutrality verified: ripple must not contain button-only meaning
G24. Boundary rules respected: state-layer foundation remains separate
G25. Independent audit doc preserved; new cycle report records decision evidence
G26. Public dependency contract documented if any contract changes
```

WordPress binding gates added by v3.6.x practice:

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
git diff --check
```

Additional probes:

```txt
Front-end button:
  href
  text-decoration-line
  user-select
  ::before hover/active opacity
  focus-visible outline
  active border-radius
  disabled/aria-disabled pointerdown behavior
  .ax-ripple aria-hidden insertion and cleanup

Editor button:
  same computed values where pseudo states are probeable
  selected/pressed state only where WP exposes a meaningful class/attribute

Mirror check:
  Compare bridge source files and Pilot copied asset files if either changes.
```

## Risks

### R1 - Theme runtime absorbs infrastructure

Risk:

```txt
Promoting Pilot ripple by copying provider behavior into pilot-block-bridge.js
could create a second ripple runtime instead of a shared contract.
```

Mitigation:

- Phase 1 compares against Ripple v2 before patching.
- If true graduation needs packaging, choose route C and defer.
- Do not invent a new public API in Pilot.

### R2 - Ripple enters editor-owned content unsafely

Risk:

```txt
Ripple v2 forbids .wp-block-post-content and [contenteditable] ancestors, but
the editor canvas is exactly an editor-owned content surface.
```

Mitigation:

- Phase 1 must classify front-end and editor runtime targets separately.
- Editor parity may be CSS state-layer parity, not animated ripple parity.
- Do not force pointer runtime into editor content unless review explicitly
  accepts the boundary.

### R3 - State parity overclaims selected state

Risk:

```txt
WordPress may not expose a selected state for core/button that maps to an M3
selected state in theme CSS.
```

Mitigation:

- State matrix includes "not exposed / no theme target".
- Do not fake selected state with invented markup.

### R4 - Lock 1/2 token regression

Risk:

```txt
Ripple or state-layer cleanup could introduce literal colors or bypass md-sys.
```

Mitigation:

- Use existing md-sys/md-ref/comp tokens only.
- Keep Axis E/F/G PASS.

### R5 - #44 scope bleed

Risk:

```txt
Editor invalid-content warnings may make editor probes noisy.
```

Mitigation:

- Count/log the warning but do not repair fixture validity in v3.6.6.
- Keep #44 as owner of editor-valid fixture work.

## Files Expected To Change After GO

Phase 1:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
```

Phase 2, only if the selected route needs code:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
```

Phase 3:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
```

Phase 5:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

## Files Not Expected To Change

```txt
theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/fixtures/*
products/reference-implementations/axismundi-pilot/patterns/*
products/reference-implementations/axismundi-pilot/templates/*
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/modules/ripple/*
styleguide/*
tools/validators/*
```

If Phase 1 proves that any of these files must change, stop and request Phase
1 review approval before expanding scope.

## Opus Review Checklist

Phase 0 review should verify:

1. #41 is the correct next candidate before #44 / Wave 2 / #21.
2. The cycle is diagnostic-first and does not patch before Phase 1 evidence.
3. Ripple graduation decision is framed as route A/B/C/D/E, not assumed.
4. Editor state parity separates real exposed states from non-exposed states.
5. The v3.5.6 Ripple v2 hierarchy remains intact:
   static state-layer foundation first, animated ripple as enhancement.
6. Locks 1-4 are preserved.
7. #44 editor-invalid-content remains out of scope.
8. Validation includes Axis E/F/G, specimen wall, computed styles, and
   extension-free browser probes.

## Next

Submit this Phase 0 plan for Opus review. Do not edit implementation files
until Phase 0 receives GO.
