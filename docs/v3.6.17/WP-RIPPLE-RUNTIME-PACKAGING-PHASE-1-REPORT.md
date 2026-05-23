# v3.6.17 - WP Ripple Runtime Packaging Decision - Phase 1 Report

Date: 2026-05-23
Status: Phase 1 diagnostic complete; awaiting Opus review
Scope: BACKLOG #41 shared WordPress ripple runtime packaging decision only

## Verdict

Phase 1 diagnostic is complete. No implementation files were edited.

Recommended Phase 2 route:

```txt
No-code decision report.
```

Packaging decision recommended by Phase 1:

```txt
Route D - Split CSS State / JS Ripple Policy

Theme/Pilot may keep CSS state-layer parity for WordPress core block surfaces.
Animated shared WordPress ripple runtime should not be packaged as a theme-side
graduation of lab Ripple v2 in v3.6.17.

If a shared animated WordPress ripple runtime is pursued later, it belongs to a
future plugin/custom-binding package or a dedicated WordPress runtime package,
with explicit post-content and editor-surface policy.
```

Secondary classification:

```txt
Route C applies to the animated runtime owner:
  plugin/custom-binding territory for shared WordPress animated ripple.

Route A applies to Phase 2 execution shape:
  no-code decision report, not runtime implementation.
```

P1 blockers:

```txt
None for a no-code Phase 2 decision report.
```

Phase 2 remains blocked until Opus Phase 1 verdict and user execution GO.

## Current Baseline

```txt
HEAD:        0142dbb Close v3.6.16 lab a11y diagnostics fix
Branch:      main == origin/main
WP core:     7.0
Phase 1:     read-only diagnostic + this report
```

Pre-existing unrelated working tree changes remain outside this cycle:

```txt
products/reference-implementations/axismundi-lab/modules/button/lab-button.css
products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.css
```

v3.6.17 Phase 1 did not modify those files.

## Reading / Evidence Inputs

Phase 1 used:

```txt
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-0-PLAN.md
BACKLOG.md #41 / #21 / #44 / #46 / #47
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/functions.php
tools/generators/build_pilot_assets.py
tools/validators/validate_pilot_specimen_wall.js
```

## Static Runtime Inventory

### Lab Ripple v2 Provider

Source:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
```

Stable public contract:

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
```

Boundary guard:

```txt
FORBIDDEN_ANCESTORS =
  .prose, .wp-block-post-content, .entry-content, [contenteditable]
```

This is decisive for #41: the current lab provider refuses exactly the
front-end post-content surface where the Pilot local runtime attaches.

Token aliases currently live in lab ripple CSS:

```txt
--md-ripple-hover-color: var(--md-sys-color-on-surface);
--md-ripple-pressed-color: var(--md-sys-color-on-surface);
--ax-ripple-hover-color: var(--md-ripple-hover-color);
--ax-ripple-pressed-color: var(--md-ripple-pressed-color);
--ax-ripple-hover-opacity: var(--md-sys-state-hover-state-layer-opacity);
--ax-ripple-pressed-opacity: 0.16;
--ax-ripple-duration: var(--md-sys-motion-curve-slow-spatial-duration);
--ax-ripple-easing: var(--md-sys-motion-curve-slow-spatial);
```

The `0.16` pressed opacity is a lab ripple module token decision already
documented in `RIPPLE-V2-AUDIT.md`; Phase 1 does not move it into `theme.json`
or `wp-custom`.

### Pilot Front-End Bridge

Source:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
```

Copied assets:

```txt
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

Pilot bridge JS behavior:

```txt
selector:       .wp-block-button__link
ready hook:     DOMContentLoaded or immediate
marker:         data-ax-pilot-ripple-attached="true"
data marker:    data-ax-ripple="bounded"
event:          pointerdown
disabled guard: aria-disabled="true" or .is-disabled
global API:     none
refresh API:    none
detach API:     none
mode support:   bounded only
forbidden guard:none
```

Pilot bridge CSS owns both core/button CSS state layers and local `.ax-ripple`
styling for `.wp-block-button__link`.

### WordPress Enqueue Boundary

`functions.php` currently:

```txt
adds editor styles for assets/styles/pilot-block-bridge.css
enqueues front-end assets/styles/pilot-block-bridge.css
enqueues front-end assets/scripts/pilot-block-bridge.js via wp_enqueue_scripts
does not use enqueue_block_editor_assets
does not enqueue pilot-block-bridge.js in editor parent or editor iframe
does not register custom blocks
```

This means the editor receives CSS bridge rules, not animated Pilot runtime JS.

### Asset Builder / Mirror Boundary

`tools/generators/build_pilot_assets.py` currently:

```txt
copies lab stylesheet sources into Pilot assets/styles/
copies Pilot bridge CSS from axismundi-pilot/bridge/ into assets/styles/
copies Pilot bridge JS from axismundi-pilot/bridge/ into assets/scripts/
does not copy lab ripple provider files into Pilot assets/scripts/
does not define a shared runtime source outside lab or Pilot bridge
```

Current mirror drift status:

```txt
pilot-block-bridge.css source and copied asset: byte-identical
pilot-block-bridge.js source and copied asset:  byte-identical
```

SHA256:

```txt
pilot-block-bridge.css:
  5D52091467E6DE2C13FF6E03D27181EB8349A5FD7736E6CBB47B709EFC716552

pilot-block-bridge.js:
  D2506A65D38AA265D999FD4A6D43DEE45BA1B83E9AF538672F7545A0091DBA16
```

## Runtime Probes

### Front End

Surface:

```txt
http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
```

Observed:

```txt
bridge script:
  /wp-content/themes/axismundi-pilot/assets/scripts/pilot-block-bridge.js

bridge style:
  /wp-content/themes/axismundi-pilot/assets/styles/pilot-block-bridge.css

typeof window.axRipple:
  undefined

.wp-block-button__link count:
  5

.wp-block-button__link inside .wp-block-post-content:
  5

data-ax-pilot-ripple-attached count:
  5

data-ax-ripple count:
  5

console/page errors:
  0
```

Interpretation:

```txt
The Pilot front end runs a Pilot-only local button runtime. It does not expose
or consume the lab provider API.
```

### Editor

Surface:

```txt
http://localhost:8888/wp-admin/post.php?post=14&action=edit
source page: axismundi-core-block-editor-smoke
```

Observed in editor parent:

```txt
bridge scripts: []
bridge styles:  []
typeof window.axRipple: undefined
```

Observed in `iframe[name="editor-canvas"]`:

```txt
bridge scripts: []
bridge stylesheet links: []
inline style mentions of bridge/button CSS: 4
typeof window.axRipple: undefined
button surfaces: 5
data-ax-pilot-ripple-attached count: 0
data-ax-ripple count: 0
console/page errors: 0
```

Interpretation:

```txt
The editor iframe receives CSS through WordPress editor-style transform, but no
animated Pilot runtime and no lab provider API. This preserves the v3.6.6
conclusion that editor parity is CSS state-layer parity where exposed, not
animated ripple parity.
```

## Phase 1 Questions Answered

### Q1. Current Runtime Inventory

```txt
Pilot front end:
  pilot-block-bridge.css loaded
  pilot-block-bridge.js loaded
  window.axRipple absent
  Pilot-only markers added to core/button links

Editor parent:
  pilot-block-bridge.js absent
  window.axRipple absent

Editor iframe:
  bridge CSS present as transformed editor styles
  pilot-block-bridge.js absent
  window.axRipple absent
  no data-ax-ripple or data-ax-pilot-ripple-attached markers
```

### Q2. Provider Contract Compatibility

Shared WordPress runtime would need at least:

```txt
[data-ax-ripple] opt-in or a WordPress-specific selector/registration path
bounded mode for button/link surfaces
disabled / aria-disabled guard
visual-only .ax-ripple nodes with aria-hidden
reduced-motion behavior
attach/detach/refresh or equivalent lifecycle API
clear policy for post-content and editor-owned forbidden ancestors
token alias location
```

Current Pilot local runtime has only a subset:

```txt
bounded button-only attachment
disabled guard
aria-hidden .ax-ripple node
reduced-motion fade
no global API
no detach / refresh
no unbounded mode
no provider classes
no forbidden ancestor guard
no shared token aliases
```

Therefore the Pilot bridge should not be renamed or treated as the shared
Ripple v2 provider.

### Q3. Post-Content Anchors

Current facts:

```txt
Pilot front-end target:
  .wp-block-button__link inside .wp-block-post-content

Ripple v2 forbidden ancestors:
  .prose, .wp-block-post-content, .entry-content, [contenteditable]
```

Conclusion:

```txt
The existing lab provider cannot be copied into the Pilot front end as-is and
expected to attach to the current Pilot target. It would refuse the surface.
```

Decision impact:

```txt
Do not weaken the lab provider's forbidden ancestor policy in v3.6.17.
If WordPress post-content anchors should receive shared animated ripple later,
that must be an explicit WordPress runtime policy, not accidental provider
graduation.
```

### Q4. Editor-Owned Surfaces

Current facts:

```txt
Editor parent and iframe do not load animated runtime JS.
Editor iframe receives CSS bridge rules.
v3.6.6 already verified focus-visible and disabled CSS state parity; hover and
pressed were not exposed as real core/button link targets in the editor.
```

Conclusion:

```txt
Animated ripple should not enter editor-owned content surfaces in this cycle.
Editor parity remains CSS state-layer parity where WordPress exposes a state.
```

### Q5. Attach / Detach Lifecycle

Current Pilot local runtime:

```txt
one-time DOMContentLoaded scan
no refresh
no detach
no MutationObserver
no WordPress hook/bootstrap for dynamic block rendering
```

Lab provider:

```txt
window.axRipple.attach()
window.axRipple.detach()
window.axRipple.refresh()
```

Conclusion:

```txt
A true shared WordPress runtime needs an explicit lifecycle contract. A
one-time front-end scan may be enough for current static Pilot pages, but it is
not a sufficient shared WordPress packaging decision.
```

Phase 1 does not select a lifecycle implementation. It records lifecycle as a
future package requirement.

### Q6. Token Alias Location

Current token locations:

```txt
lab ripple CSS:
  --md-ripple-* and --ax-ripple-* aliases exist

Pilot bridge CSS:
  button state layers use --md-sys-state-* and --md-sys-color-*
  ripple sizing uses inline --ax-ripple-size/x/y from JS
  no shared --md-ripple-* aliases
```

Conclusion:

```txt
Keep lab ripple aliases in lab ripple CSS for now.
Do not move ripple aliases into theme.json or wp-custom.
Do not introduce shared aliases in v3.6.17 without a packaging implementation
route.
```

If a future plugin/custom-binding runtime owns animated WordPress ripple, its
stylesheet should own or import its alias layer, and every color must route
through existing md-sys/md-ref tokens.

### Q7. Runtime Mirror Drift

Current mirror surfaces:

```txt
1. Lab provider source:
   products/reference-implementations/axismundi-lab/modules/ripple/

2. Pilot bridge source:
   products/reference-implementations/axismundi-pilot/bridge/

3. Pilot copied runtime assets:
   products/reference-implementations/axismundi-pilot/assets/
```

Current authority:

```txt
lab provider source is authoritative for lab Ripple v2 only.
Pilot bridge source is authoritative for Pilot-only WP bridge assets.
Pilot copied runtime assets are generated/copy targets from build_pilot_assets.py.
```

Conclusion:

```txt
There is no current shared runtime authority.
Do not create one implicitly by hand-copying lab ripple into Pilot assets.
If a shared runtime is created later, it needs a single source and generator
path before implementation.
```

Required drift checks for any future implementation route:

```txt
python tools/generators/build_pilot_assets.py
git diff --exit-code -- pilot bridge source vs copied CSS asset
git diff --exit-code -- pilot bridge source vs copied JS asset
node --check for every touched JS runtime
```

### Q8. WordPress Packaging Surface

Recommended ownership split:

```txt
Theme/Pilot:
  CSS state-layer parity for current core/button surfaces.
  Current Pilot-only front-end demo runtime may remain local until replaced.

Shared animated WordPress ripple runtime:
  future plugin/custom-binding package or dedicated WordPress runtime package.
```

Why not theme-side graduation now:

```txt
post-content target conflicts with current forbidden ancestors
editor surfaces are editor-owned and receive no runtime JS today
current Pilot runtime lacks shared lifecycle/API/mode/token contract
there is no shared source/generator package
```

Why not BACKLOG #21:

```txt
The possible plugin/custom-binding owner is a routing classification only.
This cycle does not design or implement the M3 Interpreter Plugin.
```

### Q9. Validation

For a no-code Phase 2 decision report:

```powershell
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

For any future implementation route, add:

```powershell
node --check products\reference-implementations\axismundi-lab\modules\ripple\lab-ripple.js
node --check products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.js
python tools\generators\build_pilot_assets.py
git diff --exit-code -- products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.css products\reference-implementations\axismundi-pilot\assets\styles\pilot-block-bridge.css
git diff --exit-code -- products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.js products\reference-implementations\axismundi-pilot\assets\scripts\pilot-block-bridge.js
```

Browser evidence for a future runtime route:

```txt
front-end post-content anchor attach/non-attach decision
editor iframe runtime absence or explicit reviewed presence
window.axRipple or package API shape
disabled / aria-disabled guard
reduced-motion behavior
console/page errors 0
```

### Q10. Phase 4 Trigger

Phase 1 does not trigger Phase 4.

Reason:

```txt
The current evidence is enough to make a no-code packaging decision:
CSS state can remain theme-owned, while shared animated WordPress ripple
runtime should be routed to future plugin/custom-binding packaging.
```

Phase 4 remains intentionally unused unless Phase 1 review rejects this route
and requires a broader architecture audit.

## Route Evaluation

### Route A - Decision-Only Packaging Report

```txt
Execution shape: selected for Phase 2.
```

Why:

```txt
The narrowed #41 item is a decision.
Implementation would require a new shared runtime authority, generator path,
forbidden-ancestor policy, lifecycle policy, and token alias policy.
Those are not safe to patch before review.
```

### Route B - Theme-Packaged Front-End Runtime Proposal

```txt
Not selected for v3.6.17 implementation.
```

Why:

```txt
The lab provider refuses .wp-block-post-content, while the Pilot target is
.wp-block-button__link inside .wp-block-post-content.
```

Route B could become future work only after an explicit WordPress-specific
post-content exception or separate package policy is approved.

### Route C - Plugin / Custom Binding Runtime Proposal

```txt
Selected as owner classification for animated shared runtime.
```

Why:

```txt
Animated ripple in WordPress content/editor surfaces crosses the current theme
bridge boundary. It needs runtime lifecycle and editor/post-content policy that
fits plugin/custom-binding territory better than a theme-only CSS bridge.
```

This does not implement or design BACKLOG #21.

### Route D - Split CSS State / JS Ripple Policy

```txt
Selected as the packaging decision.
```

Policy:

```txt
Theme/Pilot owns CSS state-layer parity.
Shared animated JS ripple for WordPress content is future plugin/custom-binding
runtime territory.
Current Pilot-only JS remains local evidence, not shared runtime authority.
```

### Route E - Deeper Architecture Audit Trigger

```txt
Not selected.
```

Why:

```txt
No deeper architecture audit is required to avoid theme-side runtime
graduation in this cycle. Future plugin packaging can be planned when that
backlog item is selected.
```

## Preserved #41 Sub-Decisions

### 1. Post-Content Front-End Anchors

```txt
Decision: do not graduate lab Ripple v2 as-is onto post-content anchors.
Current Pilot-only button ripple may remain Pilot-local.
Future shared runtime needs explicit WordPress post-content policy.
```

### 2. Editor-Owned Content Surfaces

```txt
Decision: no animated ripple runtime in editor-owned content surfaces in
v3.6.17. Editor remains CSS state-layer parity only.
```

### 3. Forbidden Ancestor Policy

```txt
Decision: preserve lab Ripple v2 forbidden ancestors.
Do not weaken .prose / .wp-block-post-content / .entry-content /
[contenteditable] guards as a theme-side shortcut.
```

### 4. Attach / Detach Lifecycle

```txt
Decision: current Pilot one-time scan is not a shared runtime lifecycle.
Future shared WordPress runtime needs an explicit attach/detach/refresh or
equivalent bootstrap contract.
```

### 5. Shared Token Alias Location

```txt
Decision: no shared alias relocation in v3.6.17.
Lab ripple aliases stay in lab ripple CSS.
Future plugin/custom-binding runtime should own/import any WordPress runtime
aliases and route colors through md-sys/md-ref.
```

## Non-Goals Confirmed

```txt
BACKLOG #46 disabled ripple host hygiene: not touched.
BACKLOG #47 popover provider hygiene: not touched.
BACKLOG #21 Interpreter Plugin: not implemented or designed.
BACKLOG #44 specimen coverage / validator hardening: not touched.
Pilot revision: not included.
No custom blocks.
No theme.json edits.
No functions.php edits.
No lab ripple edits.
No Pilot bridge edits.
No build_pilot_assets.py edits.
No styleguide edits.
No no-inline-styles / compat-api/css / Edge Tools / webhint policy work.
```

## Validation Run During Phase 1

Commands:

```powershell
wp-env run cli wp core version
node -e "try{require('playwright'); console.log('playwright ok')}catch(e){...}"
Get-FileHash pilot bridge source/assets
git diff --exit-code -- pilot bridge source/assets
Playwright front-end runtime probe
Playwright editor runtime probe
```

Results:

```txt
WordPress core: 7.0
Playwright: available
Pilot bridge source/assets: byte-identical
Front-end runtime probe: PASS
Editor runtime probe: PASS
Implementation files edited: no
```

Full validation is deferred to Phase 2/Phase 5 because Phase 1 did not edit
implementation files.

## Recommended Phase 2

Recommended Phase 2 artifact:

```txt
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-2-DECISION.md
```

Recommended content:

```txt
Record Route D as the packaging decision.
Record Route C as future animated runtime owner classification.
Record no code changes.
Record #41 sub-decisions and future handoff shape.
Do not edit runtime/provider/Pilot/WP implementation files.
```

Phase 2 must not begin until:

```txt
1. Opus reviews this Phase 1 report.
2. Opus returns P1/P2/P3 + GO/NO-GO/APPROVE WITH NOTES.
3. User gives Phase 2 execution GO.
```

## Phase 1 Review Request

Please review:

```txt
P1:
  Any blockers to Route D as the #41 packaging decision and Route A as the
  no-code Phase 2 execution shape?

P2:
  Any missing evidence for post-content anchors, editor-owned surfaces,
  forbidden ancestors, attach/detach lifecycle, or token alias location?

P3:
  Any wording changes needed before Phase 2 decision report?
```

Requested verdict format:

```txt
P1 / P2 / P3 + GO / NO-GO / APPROVE WITH NOTES
```
