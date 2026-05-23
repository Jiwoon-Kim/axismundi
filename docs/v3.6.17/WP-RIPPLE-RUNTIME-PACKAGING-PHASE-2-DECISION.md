# v3.6.17 - WP Ripple Runtime Packaging Decision - Phase 2 Decision

Date: 2026-05-23
Status: Phase 2 no-code decision complete
Backlog: #41 shared WordPress ripple runtime packaging decision

## Verdict

Phase 2 records the BACKLOG #41 shared WordPress ripple runtime packaging
decision as a no-code decision report.

No implementation files were edited.

Decision:

```txt
Theme/Pilot keeps CSS state-layer parity for current WordPress core block
surfaces.

Shared animated WordPress ripple runtime does not graduate from the lab Ripple
v2 provider into the Pilot/theme in v3.6.17.

If shared animated ripple for WordPress content is pursued later, it belongs to
a future plugin/custom-binding runtime package or dedicated WordPress runtime
package with explicit post-content, editor-surface, lifecycle, and token-alias
policy.
```

## Review Gate

Phase 1 Opus verdict:

```txt
P1 / GO - APPROVE WITH NOTES
Phase 2 no-code decision report GO-ready pending user execution trigger.
```

User Phase 2 execution GO was received before this document was written.

Opus Phase 1 P2 notes absorbed here:

```txt
P2.1 Layered route selection: recorded below.
P2.2 Future lifecycle requirements: recorded below.
P2.3 Token alias decision with Lock 1/2: recorded below.
```

Phase 1 amendment was not required.

## Layered Route Selection

Phase 0 framed the candidate routes as A/B/C/D/E. Phase 1 evidence showed that
the correct answer is layered rather than mutually exclusive:

```txt
Route D = packaging policy decision:
  Split CSS state and animated JS ripple.
  Theme/Pilot owns CSS state-layer parity.
  Shared animated JS ripple is not theme-graduated in v3.6.17.

Route C = future owner classification:
  Shared animated WordPress ripple belongs to future plugin/custom-binding or
  dedicated WordPress runtime packaging if pursued.

Route A = Phase 2 execution shape:
  No-code decision report.
  No runtime, provider, Pilot, WP, generator, validator, or styleguide edit.
```

Rejected for v3.6.17 implementation:

```txt
Route B:
  Do not package lab Ripple v2 as theme-side front-end WordPress runtime.

Route E:
  No deeper Phase 4 architecture audit is needed to make this no-code decision.
```

## Decision Basis

Phase 1 proved four current facts:

```txt
1. Pilot front-end runtime attaches to .wp-block-button__link inside
   .wp-block-post-content.

2. Lab Ripple v2 explicitly forbids .wp-block-post-content, .entry-content,
   .prose, and [contenteditable].

3. Editor parent and editor iframe do not load animated Pilot bridge JS or
   lab Ripple v2 JS.

4. Editor iframe receives CSS bridge rules through WordPress editor styles,
   preserving CSS state-layer parity without animated ripple runtime.
```

Therefore:

```txt
Copying or graduating lab Ripple v2 into the Pilot/theme would either fail to
attach to the current target or require weakening the forbidden-ancestor policy.
Both are outside the safe v3.6.17 route.
```

## #41 Sub-Decisions

### 1. Post-Content Front-End Anchors

Decision:

```txt
Do not graduate lab Ripple v2 as-is onto post-content anchors.
```

Rationale:

```txt
The current Pilot front-end target is .wp-block-button__link inside
.wp-block-post-content, but lab Ripple v2 treats .wp-block-post-content as a
forbidden ancestor.
```

Allowed current state:

```txt
The existing Pilot-only front-end button ripple may remain local evidence.
It is not the shared WordPress runtime authority.
```

Future requirement:

```txt
Any future shared animated runtime must explicitly decide whether WordPress
post-content anchors are allowed targets, and if so under which package and
guard policy.
```

### 2. Editor-Owned Content Surfaces

Decision:

```txt
Do not package animated ripple runtime into editor-owned content surfaces in
v3.6.17.
```

Rationale:

```txt
The editor currently receives CSS state-layer bridge rules, not animated
runtime JS. v3.6.6 already closed current core/button editor parity as
focus-visible PASS, disabled PASS, and hover/pressed/selected not exposed or no
theme target.
```

Future requirement:

```txt
Animated editor ripple would require a specific editor extension/runtime
policy. It must not be introduced by theme-side bridge drift.
```

### 3. Forbidden Ancestor Policy

Decision:

```txt
Preserve the lab Ripple v2 forbidden-ancestor policy.
```

Current forbidden ancestors:

```txt
.prose
.wp-block-post-content
.entry-content
[contenteditable]
```

Rationale:

```txt
These guards protect prose, post content, federated/content surfaces, and
editor-owned regions. v3.6.17 does not weaken them to make theme-side runtime
graduation easier.
```

Future requirement:

```txt
If a WordPress-specific runtime needs a post-content exception, that exception
must be owned by the future WordPress runtime package, not by the lab provider
contract.
```

### 4. Attach / Detach Lifecycle

Decision:

```txt
The current Pilot one-time DOMContentLoaded scan is not a shared WordPress
runtime lifecycle.
```

Current contrast:

```txt
Pilot local runtime:
  one-time DOMContentLoaded scan
  no refresh
  no detach
  no MutationObserver
  no WordPress hook/bootstrap

Lab Ripple v2 provider:
  window.axRipple.attach(control, options?)
  window.axRipple.detach(control)
  window.axRipple.refresh(root?)
```

Future lifecycle requirements:

```txt
Any future shared WordPress runtime must define how it handles:
  - static front-end core block rendering;
  - dynamically inserted blocks or patterns;
  - navigation/page-fragment replacement;
  - AJAX/infinite-scroll content injected by themes or plugins;
  - editor preview re-rendering, if editor runtime is explicitly allowed;
  - attach idempotency on repeated scans;
  - detach/dispose for removed controls;
  - reduced-motion behavior across refreshed controls.
```

Phase 2 does not select a lifecycle implementation.

### 5. Shared Token Alias Location

Decision:

```txt
Do not relocate or globalize ripple token aliases in v3.6.17.
```

Current locations:

```txt
Lab ripple CSS:
  --md-ripple-* and --ax-ripple-* aliases exist for lab Ripple v2.

Pilot bridge CSS:
  uses md-sys state-layer and color tokens for CSS state parity.
  uses inline --ax-ripple-size / --ax-ripple-x / --ax-ripple-y for local
  Pilot ripple geometry.
```

Lock 1 / Lock 2 impact:

```txt
Lock 1 - wp-custom downstream-only:
  Ripple aliases must not be moved into theme.json settings.custom.axismundi.*
  as literal values.

Lock 2 - md-sys color maps to md-ref:
  Any future ripple color alias must route through md-sys/md-ref. It must not
  introduce literal md-sys color values.
```

Important literal note:

```txt
Lab Ripple v2 currently uses --ax-ripple-pressed-opacity: 0.16 inside the lab
module CSS. That is an existing module-level decision, not a wp-custom leaf and
not a theme.json value. Phase 2 does not promote it into shared WP custom data.
```

Future requirement:

```txt
If a future plugin/custom-binding runtime owns animated WordPress ripple, its
stylesheet should own or import the runtime alias layer and keep color aliases
downstream from md-sys/md-ref.
```

## Runtime Mirror Surfaces

Current authority split remains:

```txt
Lab provider source:
  products/reference-implementations/axismundi-lab/modules/ripple/
  Authority for lab Ripple v2 only.

Pilot bridge source:
  products/reference-implementations/axismundi-pilot/bridge/
  Authority for Pilot-only WP bridge source.

Pilot copied assets:
  products/reference-implementations/axismundi-pilot/assets/
  Generated/copy target from build_pilot_assets.py.
```

No new shared runtime authority is created in v3.6.17.

Future packaging requirement:

```txt
Before any shared WordPress runtime implementation, choose a single source of
truth and update the asset generator / validation path so copies cannot drift.
```

Required future drift checks:

```powershell
python tools\generators\build_pilot_assets.py
git diff --exit-code -- products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.css products\reference-implementations\axismundi-pilot\assets\styles\pilot-block-bridge.css
git diff --exit-code -- products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.js products\reference-implementations\axismundi-pilot\assets\scripts\pilot-block-bridge.js
```

If the future source is not `axismundi-pilot/bridge/`, those checks must be
updated to compare the chosen source against the generated package artifacts.

## Non-Goals Confirmed

```txt
BACKLOG #46 disabled ripple host hygiene: not included.
BACKLOG #47 popover provider hygiene: not included.
BACKLOG #21 Interpreter Plugin: not implemented or designed.
BACKLOG #44 specimen coverage / validator hardening: not included.
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
No no-inline-styles policy work.
No compat-api/css policy work.
No Microsoft Edge Tools / webhint policy work.
```

## Implementation Status

No files changed in Phase 2 except this document:

```txt
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-2-DECISION.md
```

Expected unchanged implementation surfaces:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/functions.php
tools/generators/build_pilot_assets.py
```

## BACKLOG #41 Close Text Draft

Suggested Phase 5 BACKLOG #41 close/update text:

```txt
v3.6.17 closed the remaining #41 shared WordPress ripple runtime packaging
decision as a no-code architecture decision.

Layered route:
  Route D = packaging policy: split CSS state-layer parity from animated JS
  ripple.

  Route C = future owner classification: shared animated WordPress ripple
  runtime belongs to a future plugin/custom-binding or dedicated WordPress
  runtime package if pursued.

  Route A = v3.6.17 execution shape: no-code decision report.

Sub-decisions:
  1. Post-content anchors: do not graduate lab Ripple v2 as-is onto
     .wp-block-post-content anchors because the lab provider forbids that
     ancestor.
  2. Editor-owned surfaces: no animated ripple runtime enters editor content in
     v3.6.17; editor parity remains CSS state-layer parity where exposed.
  3. Forbidden ancestors: preserve lab Ripple v2 .prose /
     .wp-block-post-content / .entry-content / [contenteditable] guards.
  4. Lifecycle: current Pilot DOMContentLoaded scan is not a shared runtime
     lifecycle; future package needs explicit attach/detach/refresh or
     equivalent WordPress bootstrap.
  5. Token aliases: no shared alias relocation in v3.6.17; future runtime
     aliases must remain downstream from md-sys/md-ref and must not become
     wp-custom literals.

No provider, Pilot bridge, WordPress enqueue, generator, validator, or
styleguide implementation files changed.
```

## Validation Plan

Because Phase 2 is a no-code decision report, recommended validation before
Phase 3/5:

```powershell
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

Optional no-code smoke:

```txt
Repeat the front-end runtime probe from Phase 1 to confirm the current runtime
state has not changed:
  pilot-block-bridge.js loaded on front end
  window.axRipple undefined
  post-content button links carry Pilot-only markers
  console/page errors 0
```

## Phase 4

Phase 4 remains intentionally unused.

Reason:

```txt
Phase 1 provided enough evidence for a no-code packaging decision. No deeper
architecture audit is needed before recording Route D / C / A.
```

## Next

Proceed to Phase 3 verification after review/user continuation, then Phase 5
close if validation passes.
