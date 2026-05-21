# v3.6.6 - WP Block Bridge Ripple / Editor State Parity - Phase 2 Report

Date: 2026-05-21

Phase: 2 - No-Code Route Decision

## Verdict

Phase 2 is complete as a no-code route decision report.

No implementation files were edited.

Selected route from Phase 1 is confirmed:

```txt
Route C:
  True shared runtime needs packaging/plugin/custom binding work; defer
  graduation and keep Pilot-only for now.
```

Decision:

```txt
Pilot ripple bridge graduates: no, not in v3.6.6
Pilot ripple bridge remains Pilot-only: yes
Editor animated ripple parity target: no
Editor CSS state-layer parity where exposed: yes
Phase 2 code patch: none
```

## Phase 1 Review Carry-Forward

Opus Phase 1 review returned:

```txt
APPROVE WITH NOTES
P1: none
P2: one wording correction
P3: three non-blocking notes
Phase 2 no-code route decision report: GO
```

This Phase 2 report absorbs the requested P2 correction and the P3 routing
notes. The Phase 1 report is not rewritten.

## P2 Correction

Phase 1's Bucket C rationale included this imprecise wording:

```txt
the Pilot's front-end target is exactly .wp-block-post-content
```

Corrected wording:

```txt
Pilot front-end runtime currently attaches to .wp-block-button__link rendered
inside .wp-block-post-content. Ripple v2's FORBIDDEN_ANCESTORS policy
(closest('.prose, .wp-block-post-content, .entry-content, [contenteditable]'))
would refuse provider-runtime attachment on that surface.
```

Effect on Route C:

```txt
The conclusion is unchanged.
```

Reason:

```txt
The incompatibility is not that .wp-block-post-content itself is the direct
runtime target. The incompatibility is that the current theme-owned Pilot
front-end button target lives under .wp-block-post-content, while the existing
Ripple v2 provider deliberately refuses to attach anywhere inside that content
surface.
```

## Route C Reasoning

### What Stays Pilot-Only

The current Pilot bridge may keep its local front-end enhancement:

```txt
selector: .wp-block-button__link
scope:    front-end Pilot theme surface
effect:   pointer ripple visual enhancement for core/button navigation anchors
status:   Pilot-only
```

This preserves the v3.6.3 `core/button` semantic route:

```txt
core/button anchor with href remains navigation.
The theme may apply an M3 button visual surface.
Action behavior remains plugin/custom-block territory.
```

### What Does Not Graduate

The Pilot bridge does not graduate into the existing Ripple v2 provider in
v3.6.6.

Reasons:

```txt
1. Ripple v2's public provider contract includes window.axRipple
   attach/detach/refresh, .ax-ripple-host classes, bounded/unbounded modes,
   and token aliases. Pilot currently implements only a small button-local
   subset.

2. Ripple v2's forbidden ancestor policy rejects .wp-block-post-content and
   [contenteditable]. This is correct for lab/provider safety, but it conflicts
   with blindly reusing the provider inside the Pilot post-content bridge.

3. The WordPress editor canvas receives editor styles but not Pilot front-end
   JS. Animated ripple would require an explicit editor-runtime decision, not
   incidental theme CSS parity.

4. Shared WordPress ripple runtime needs a packaging / binding / plugin
   strategy so the provider does not become a second copied runtime hidden
   inside pilot-block-bridge.js.
```

### Forward Routing

Forward route:

```txt
Future v3.7.x / plugin-custom binding work should decide whether WordPress
gets a packaged ripple provider runtime, and if so, how it safely handles:
  - post-content front-end anchors
  - editor-owned content surfaces
  - forbidden ancestor policy
  - attach/detach lifecycle
  - shared token alias location
```

This is a packaging / plugin-custom binding strategy question, not a v3.6.6
theme bridge patch.

## BACKLOG #41 Close / Narrowing Draft

Suggested Phase 5 BACKLOG #41 update:

```txt
v3.6.6 close evidence:
  ripple bridge graduation:
    closed for the current Pilot theme cycle as "does not graduate".
    Pilot's front-end core/button ripple remains Pilot-only.
    Shared WordPress ripple runtime is deferred to future v3.7.x
    packaging/plugin-custom binding strategy.

  editor-canvas state parity:
    closed for core/button state exposure in the current theme bridge.
    Focus and disabled CSS states resolve in the editor.
    Hover and pressed pointer states are not exposed on the real editor
    .wp-block-button__link because Gutenberg owns block pointer interaction
    and the link has pointer-events:none.
    Selected state is not exposed for core/button anchors; no theme target.
```

Suggested remaining #41 state after Phase 5:

```txt
BACKLOG #41 may close for the v3.6.x Pilot feedback slice if Phase 3 QA
confirms the Phase 1 findings.

Any future shared ripple runtime should be tracked as a new packaging/plugin
or WordPress binding strategy item rather than kept as unresolved #41 theme
bridge residue.
```

If reviewer prefers keeping #41 open:

```txt
Narrow #41 to "shared WordPress ripple runtime packaging decision" and remove
the editor-canvas state parity question from #41's active v3.6.x theme scope.
```

## #44 Forward Routing

Phase 1 editor probe observed:

```txt
editor open console errors:             56
block validation console error count:   56
UI invalid-content text count at probe:  0
```

Forward route for Phase 5 / BACKLOG #44:

```txt
v3.6.6 Phase 1 observed 56 editor block-validation console errors while
opening the specimen wall in the editor. This remains BACKLOG #44
editor-valid fixture / editor compatibility work and is not fixed by v3.6.6.
```

No fixture repair is selected in v3.6.6.

## Phase 3 Visual QA Scope

Phase 3 should be a compact confirmation pass, not a new implementation pass.

Surfaces:

```txt
Front end:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Editor:
  http://localhost:8888/wp-admin/post.php?post=29&action=edit
  iframe[name="editor-canvas"]
```

Required front-end checks:

```txt
.wp-block-button__link href remains present
data-ax-ripple="bounded" remains present on front-end button links
data-ax-pilot-ripple-attached="true" remains present after runtime attach
hover state-layer opacity resolves near 0.08
focus-visible outline resolves through --md-sys-color-primary
pressed state-layer opacity resolves near 0.10
pressed radius morphs toward --md-sys-shape-corner-small
pointerdown creates aria-hidden .ax-ripple
aria-disabled prevents new ripple creation
```

Required editor checks:

```txt
Pilot runtime JS remains absent from editor iframe
typeof iframe window.axRipple remains undefined
editor root md-sys tokens from v3.6.5 remain present
real editor .wp-block-button__link keeps pointer-events:none
focus outline resolves through --md-sys-color-primary
aria-disabled probe resolves pointer-events:none and opacity 0.38
hover / pressed real editor pointer states remain not exposed
selected state remains not exposed for core/button anchors
```

Required routing checks:

```txt
No implementation file changes.
No fixture repair claim.
#44 editor block-validation console errors remain routed.
No plugin/custom-block implementation.
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

## Validation State

No implementation files changed in Phase 2, so full validation is not rerun
for this report.

Required for Phase 3 / Phase 5:

```powershell
wp-env run cli wp core version
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
git diff --check
```

Current Phase 2 git scope:

```txt
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
```

## Phase 2 Exit Criteria

```txt
P2 correction applied: PASS
Route C confirmed: PASS
BACKLOG #41 close / narrowing draft recorded: PASS
Phase 3 QA scope fixed: PASS
#44 console-error forward routing recorded: PASS
Implementation files edited: no
```

## Next

Submit this Phase 2 no-code route decision report for Opus review.

Do not edit implementation files. Phase 3 may proceed after Phase 2 review GO.
