# v3.6.5 - WP Block Bridge Editor Token Parity - Phase 0 Plan

Date: 2026-05-21

Phase: 0 - Plan

## User Request Log

User requested:

```txt
Phase 0 plan go
```

The active handoff is `NEXT-SESSION.md - Post-v3.6.4 Handoff`. The primary
candidate is BACKLOG #41 ripple/editor parity follow-on:

```txt
BACKLOG #41 ripple/editor parity follow-on:
  decide whether the Pilot ripple bridge graduates or remains Pilot-only
  verify editor-canvas parity for hover/focus/pressed/disabled/selected states
  resolve editor md-sys color token enqueue parity
```

v3.6.5 chooses the smallest concrete slice with direct evidence from v3.6.4:

```txt
resolve editor md-sys color token enqueue parity
```

The ripple bridge graduation decision and broader editor state parity matrix
remain BACKLOG #41 residual work after this cycle unless Phase 0 review
explicitly expands scope.

## Cycle Frame

v3.6.5 is an editor token enqueue parity cycle.

The cycle does not introduce a new semantic decision. It preserves all four
existing locks:

```txt
Lock 1 - wp-custom downstream-only
Lock 2 - md-sys color maps to md-ref
Lock 3 - core/button semantic route before visual cleanup
Lock 4 - semantic mismatch handling rule
```

The implementation target is the WordPress editor canvas token plumbing that
v3.6.4 Phase 3 exposed:

```txt
--md-sys-color-on-surface:        empty in editor iframe
--md-sys-color-outline-variant:   empty in editor iframe
```

The desired end state is not new visual design. It is parity:

```txt
Bridge selectors already apply structurally in the editor canvas.
The editor canvas should expose the same md-sys color tokens needed by those
selectors so pullquote and other bridge surfaces resolve colors like the front
end.
```

## Source Evidence

### v3.6.4 Phase 3 Finding

`docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md`
documented:

```txt
--md-sys-color-on-surface:        empty
--md-sys-color-outline-variant:   empty

core/pullquote figure in editor:
  border-block-start-width: 0px
  border-block-start-style: none
  color: rgb(0, 0, 0)
```

It also documented that the v3.6.4 selectors reached the editor canvas:

```txt
core/pullquote inner blockquote:
  padding-inline-start: 0px
  padding-block-start:  0px
  border-inline-start:  0px

core/pullquote p:
  font-size:   28px
  line-height: 36px
  font-style:  italic

core/pullquote cite:
  font-size:          12px
  margin-block-start: 16px
  cite::before:       none
```

Conclusion from v3.6.4:

```txt
Selector correctness: PASS.
Token enqueue parity: FAIL / carry-forward.
```

### BACKLOG #41 Remaining Scope

BACKLOG #41 after v3.6.4 includes:

```txt
editor token enqueue parity:
  v3.6.4 Phase 3 found the editor iframe does not expose
  --md-sys-color-on-surface or --md-sys-color-outline-variant.
  Bridge selectors apply structurally, but color tokens do not resolve in the
  editor canvas. This is pre-existing token enqueue plumbing, not a v3.6.4
  regression.
```

### Current Code Grounding

`products/reference-implementations/axismundi-pilot/functions.php` currently
uses `add_editor_style()` in theme setup:

```php
add_theme_support( 'editor-styles' );
...
add_editor_style( array_values( $editor_styles ) );
```

The editor style list includes:

```txt
assets/styles/tokens.ref.css
assets/styles/tokens.sys.light.css
assets/styles/tokens.comp.css
assets/styles/tokens.sys.dark.css
assets/styles/wp-preset.bridge.css
assets/styles/wp-custom.bridge.css
assets/styles/tokens.css
assets/styles/base.css
assets/styles/icons.css
assets/styles/components.css
assets/styles/blocks.css
assets/styles/prose.css
assets/styles/pilot-block-bridge.css
```

Front-end styles are enqueued through `wp_enqueue_scripts` with explicit
dependency order:

```txt
fonts -> tokens.ref -> tokens.sys.light -> tokens.comp -> tokens.sys.dark
-> wp-preset -> wp-custom -> tokens -> base -> icons -> components -> blocks
-> prose -> pilot-block-bridge
```

Phase 1 must determine why the editor iframe still has empty md-sys color
tokens despite the editor-style list including the token files.

## Scope

### In Scope

1. Editor canvas md-sys color token enqueue parity.
2. Functions-level editor style/enqueue plumbing if Phase 1 proves that
   `add_editor_style()` is insufficient for the editor iframe token context.
3. A narrow editor-token fallback bridge only if enqueue plumbing cannot expose
   the md-sys tokens through the existing token files.
4. Computed editor-canvas probes for the specific tokens and affected
   pullquote surface.
5. Front-end regression probes to prove no front-end token mapping changed.
6. Documentation for the remaining #41 residual scope after the token parity
   slice.

### Out of Scope

```txt
ripple bridge graduation implementation 안 함.
broader editor state parity matrix implementation 안 함.
v3.6.3 semantic decision 재논의 안 함.
custom block implementation 안 함.
```

Additional non-goals:

- Do not change `theme.json` token values unless Phase 1 proves the editor
  token gap cannot be solved at enqueue/style scope.
- Do not add plugin behavior.
- Do not change block markup, save content, fixtures, or imported specimen
  content.
- Do not broaden BACKLOG #44 editor-invalid-content or Material Symbols work
  into this cycle.
- Do not alter front-end dark-mode token semantics; dark mode remains sys-layer
  remapping only.
- Do not promote the Pilot ripple runtime into a shared package in this cycle.
- Do not add a new validator axis unless Phase 1 finds the token gap cannot be
  checked with existing computed/editor probes.

## Phase Partition

### Phase 0 - Plan

Deliver this plan doc for Opus review before implementation.

Expected artifact:

```txt
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
```

Exit criteria:

- Scope is limited to editor md-sys color token parity.
- Ripple graduation and broader editor state parity remain explicit non-goals.
- Lock 1/2 token architecture rules are named as constraints.
- Lock 3/4 are preserved and not reopened.
- Validator strategy includes editor computed probes and the existing Axis E/F/G
  guards.

### Phase 1 - Editor Token Plumbing Inventory

Read before editing:

```txt
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/assets/styles/tokens.ref.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.light.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.dark.css
products/reference-implementations/axismundi-pilot/assets/styles/wp-preset.bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/wp-custom.bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
```

Entry probes:

```txt
Editor canvas:
  HTTP: confirm each editor-style CSS file is fetched by the editor iframe
  DOM: confirm iframe document root element
  DOM: confirm token CSS rules appear in document.styleSheets
  DOM: confirm :root token declarations resolve at the iframe root
  --md-ref-palette-neutral-10
  --md-ref-palette-neutral-90
  --md-sys-color-on-surface
  --md-sys-color-outline-variant
  --wp--custom--axismundi--state-layer--hover-opacity
  pullquote border-block-start-width/style/color
  pullquote color

Front end:
  same md-ref and md-sys tokens
  same pullquote computed values
```

Phase 1 must classify the root cause before implementation:

```txt
A. token files are not loaded in editor canvas
B. token files load but :root selectors do not land on the editor iframe root
C. sys dark/light selector shape fails inside editor canvas
D. style order/dependency issue empties or overrides md-sys tokens
E. other, with evidence
```

Expected artifact:

```txt
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
```

Exit criteria:

- Before values recorded for editor and front end.
- Root cause bucket A/B/C/D/E selected with evidence.
- Implementation route chosen before any patch is applied.
- Phase 1 must not edit implementation files. If Phase 1 evidence surfaces an
  unambiguously trivial patch, request scope expansion at Phase 1 review rather
  than combining inventory and patch in the same commit.

### Phase 2 - Editor Token Parity Patch

Patch only the route selected in Phase 1.

Likely files, depending on Phase 1 evidence:

```txt
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
```

Preferred route:

```txt
Fix editor token exposure through WordPress editor style/enqueue plumbing while
continuing to consume the existing token files as source of truth.
```

Fallback route:

```txt
If WordPress editor-style selector rewriting prevents root-level md-sys tokens
from resolving, add the narrowest editor-canvas token bridge that maps md-sys
tokens back to existing md-ref tokens without literal color values.
```

Patch constraints:

- Any new md-sys color declaration must map to `var(--md-ref-palette-*)`.
- No literal hex/rgb/hsl color values.
- No new wp-custom literal values.
- No weakening Axis E/F/G.
- No custom blocks.
- No plugin behavior.
- Source and asset mirror CSS must remain byte-identical if CSS is edited.

Expected artifact:

```txt
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
```

Exit criteria:

- Editor canvas exposes `--md-sys-color-on-surface`.
- Editor canvas exposes `--md-sys-color-outline-variant`.
- Editor pullquote figure divider resolves to a nonzero border with an md-sys
  routed color.
- Editor pullquote figure text resolves to `--md-sys-color-on-surface`.
- Front-end pullquote light/dark values remain unchanged from v3.6.4.
- Axis E/F/G remain 1.000 PASS.
- `validate:specimen-wall` and `validate:computed` remain PASS.

### Phase 3 - Editor Visual QA

Light visual QA after Phase 2.

Surfaces:

```txt
Front end:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Editor:
  http://localhost:8888/wp-admin/post.php?post=29&action=edit
```

Expected evidence:

```txt
Editor canvas:
  md-sys color token values no longer empty
  pullquote divider visible
  pullquote text/cite colors resolve
  existing editor invalid-content warning remains routed to #44, not solved

Front end:
  no regression in light/dark pullquote values
  no regression in button state-layer/focus values
```

Expected artifact:

```txt
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
```

Exit criteria:

- Editor token parity PASS.
- Front-end bridge regression PASS.
- Existing #44 warning remains routed, not absorbed into this cycle.
- Ripple graduation remains unimplemented.

### Phase 5 - Close

Update:

```txt
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
BACKLOG.md #41
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
```

`AGENTS.md` and `CLAUDE.md` date stamps should update only if the close touches
their stated operating context. No new lock is expected.

Exit criteria:

- BACKLOG #41 records editor md-sys token enqueue parity as closed.
- BACKLOG #41 residual narrows to ripple bridge graduation and broader editor
  state parity.
- BACKLOG #44 remains the owner of editor-invalid-content / coverage follow-on.
- Final validation is recorded.

## Validation Strategy

Standard close validation:

```txt
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
npm run validate:computed
git diff --check
```

Required token guards:

```txt
Axis E - md-sys color maps to md-ref: 1.000 PASS
Axis F - bridge downstream-only:      1.000 PASS
Axis G - wp-custom downstream-only:   1.000 PASS
```

Additional computed probes for this cycle:

```txt
Editor canvas:
  getComputedStyle(root).getPropertyValue("--md-sys-color-on-surface")
  getComputedStyle(root).getPropertyValue("--md-sys-color-outline-variant")
  pullquote border-block-start-width/style/color
  pullquote color
  pullquote cite color

Front end:
  same pullquote values in light and dark
```

No new validator axis is expected. If Phase 2 needs a reusable editor-token
gate, it should follow the Axis E/F/G forward-proof shape:

```txt
source-of-truth list explicit
failure identifies token, context, expected route, observed value
no weakening of existing axes
```

## Risks

### R1 - Editor style root selector mismatch

`add_editor_style()` may load styles into the editor iframe but rewrite or
scope selectors such that `:root` token declarations do not land where bridge
rules consume them.

Mitigation:

- Phase 1 classifies selector/root behavior before patching.
- Prefer enqueue/style-context fix over duplicating tokens.
- If fallback bridge is needed, map md-sys tokens to md-ref variables without
  literals.

### R2 - Token lock regression

Fixing editor tokens by inserting literal color values would violate Lock 2.

Mitigation:

- No hex/rgb/hsl literals.
- Axis E/F/G must remain PASS.
- New declarations, if any, must use `var(--md-ref-palette-*)` routes.

### R3 - Front-end regression

Editor parity work could accidentally alter front-end token load order or dark
mode behavior.

Mitigation:

- Front-end light/dark computed values must be compared against v3.6.4.
- `validate:computed` remains mandatory.

### R4 - #44 scope bleed

The editor invalid-content warning may tempt this cycle to expand into fixture
repair or editor compatibility beyond token enqueue.

Mitigation:

- #44 warning remains explicitly out of scope.
- This cycle fixes token exposure only.

### R5 - Ripple scope creep

Ripple graduation is still open under #41 and sits near the same editor/state
parity area.

Mitigation:

- Ripple implementation and graduation decision are non-goals.
- This cycle may observe ripple state only as regression smoke, not as a
  decision or patch.

## Files Expected To Change After GO

Phase 1:

```txt
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
```

Phase 2, depending on Phase 1 route:

```txt
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
```

Phase 3:

```txt
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
```

Phase 5:

```txt
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
BACKLOG.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
```

## Files Not Expected To Change

```txt
theme.json
products/reference-implementations/axismundi-pilot/fixtures/*
products/reference-implementations/axismundi-pilot/patterns/*
products/reference-implementations/axismundi-pilot/templates/*
products/reference-implementations/axismundi-lab/*
styleguide/*
```

If Phase 1 proves that `theme.json` is the only correct editor token surface,
stop and request review before expanding scope.

## Opus Review Checklist

Phase 0 review should verify:

1. The cycle selects only editor md-sys color token enqueue parity from the
   remaining #41 scope.
2. Ripple graduation and broader editor state parity are explicit non-goals.
3. Lock 1/2 token architecture constraints are enforced in scope, validation,
   risks, and exit criteria.
4. Lock 3/4 are preserved and not reopened.
5. Phase 1 requires root-cause classification before implementation.
6. Phase 2 prefers enqueue/style plumbing before fallback token duplication.
7. Validator strategy retains Axis E/F/G, `validate:specimen-wall`, and
   `validate:computed`.
8. #44 editor-invalid-content remains out of scope.

## Methodology Candidate

v3.6.5 introduces a diagnostic-first cycle shape:

```txt
When the failure mode is unknown, Phase 1 classifies root cause and selects an
implementation route before any patch.
```

Do not promote this to a new lock during Phase 0. Revisit at Phase 5 as a
methodology finding. If later cycles reuse the same pattern for ripple
graduation or editor state parity, consider whether it deserves lock status.

## Next

Submit this Phase 0 plan for Opus review. Do not edit implementation files
until Phase 0 receives GO.
