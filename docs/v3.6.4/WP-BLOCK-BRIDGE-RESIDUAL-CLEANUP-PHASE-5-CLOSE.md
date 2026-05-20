# v3.6.4 - WP Block Bridge Residual Cleanup - Phase 5 Close

Date: 2026-05-21

Phase: 5 - Close

## Verdict

v3.6.4 is closed as a Lock 3/4 enforcing mechanical cleanup cycle for BACKLOG
#41 residual work.

The cycle did not reopen the v3.6.3 semantic decisions. It implemented the
mechanical work that those decisions unlocked:

```txt
Phase 1:
  core/button link affordance cleanup
  text-decoration removed
  user-select disabled
  href semantics preserved

Phase 2:
  core/quote selector narrowing
  core/pullquote distinct surface bridge
  pullquote inner blockquote no longer absorbs quote styling
  pullquote cite no longer absorbs quote prefix

Phase 3:
  light/dark visual QA
  editor canvas smoke
  front-end drag console smoke
```

No custom blocks, plugin behavior, `theme.json`, `functions.php`, or fixture
expansion work was introduced.

## Commits

```txt
4aab775  Add v3.6.4 residual cleanup phase 0 plan
cfee3d8  Amend v3.6.4 residual cleanup plan
cd2115c  Clean up core button link affordance
040f1c7  Separate quote and pullquote bridge surfaces
975d50c  Record v3.6.4 residual cleanup visual QA
6f81376  Add editor canvas smoke to v3.6.4 visual QA
fe5df23  Record front-end drag console smoke
```

## Documents

```txt
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
```

## Closed

BACKLOG #41 residual items closed by v3.6.4:

```txt
button mechanical cleanup after route:
  .wp-block-button__link text-decoration reset
  .wp-block-button__link user-select reset
  focus-visible outline preserved
  hover/pressed state layers preserved
  href semantics preserved

quote/pullquote implementation after route:
  quote selectors narrowed away from pullquote's inner blockquote
  pullquote figure receives distinct top/bottom divider surface
  pullquote paragraph receives headline-medium italic treatment
  pullquote citation receives body-small on-surface-variant treatment
  pullquote cite prefix leak removed
```

## Lock Compliance

```txt
Lock 1 - wp-custom downstream-only:
  preserved; no theme.json or wp-custom source changes.

Lock 2 - md-sys color maps to md-ref:
  preserved; all new bridge color use routes through md-sys tokens.

Lock 3 - core/button semantic route before visual cleanup:
  enforced; visual cleanup accepted only after v3.6.3 named the anchor route.
  href remained present in front-end and editor probes.

Lock 4 - semantic mismatch handling rule:
  enforced; quote/pullquote stayed distinct surfaces and were not collapsed
  into one generic blockquote CSS patch.
```

## Visual QA

Phase 3 confirmed the mechanical patches across light and dark mode:

```txt
Button:
  light focus outline: 2px solid rgb(103, 80, 164)
  dark focus outline:  2px solid rgb(208, 188, 255)
  hover opacity:       0.08
  pressed opacity:     0.10

Pullquote:
  light divider:       rgb(202, 196, 208)
  dark divider:        rgb(73, 69, 79)
  light text:          rgb(29, 27, 32)
  dark text:           rgb(230, 224, 233)
```

The R1 absorption path stayed closed in both modes:

```txt
pullquote inner blockquote padding: 0px
pullquote inner blockquote border:  0px
pullquote cite::before:             none
```

## Editor Canvas

The editor canvas smoke confirmed that the v3.6.4 selectors reach the editor:

```txt
Button:
  href:                 #button-fill
  text-decoration-line: none
  user-select:          none

Pullquote:
  inner blockquote padding-inline-start: 0px
  inner blockquote border-inline-start:  0px
  p font-size:                         28px
  cite::before:                        none
```

The same smoke surfaced a pre-existing editor token/style parity gap:

```txt
--md-sys-color-on-surface:        empty in editor iframe
--md-sys-color-outline-variant:   empty in editor iframe
```

As a result, pullquote color/divider tokens do not resolve in the editor canvas
the way they do on the front end. This is not a v3.6.4 regression. The bridge
selectors apply structurally; the remaining issue is editor token enqueue
plumbing and belongs with BACKLOG #41 editor parity or BACKLOG #44 editor
compatibility.

## Drag Console Smoke

The user-observed drag-time console errors on `?p=36` referenced `content.js`.
An extension-free Playwright Chromium reproduction attempt on the same page
produced:

```txt
Console/page errors: 0
Theme script observed:
  /wp-content/themes/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

`content.js` is not an Axismundi Pilot theme file or a repository file. Treat
the report as likely browser extension/content-script noise unless it
reproduces in an extension-free browser or in the tracked Pilot script bundle.

## Routed Forward

BACKLOG #41 remains open, but its residual scope is narrower:

```txt
Still open under #41:
  ripple bridge graduation
  editor-canvas parity for hover/focus/pressed/disabled/selected states
  editor token enqueue parity for md-sys color tokens

Also relevant:
  BACKLOG #44 editor compatibility / invalid content warning
```

No new backlog item is required for v3.6.4.

## Validation

Final close validation:

```txt
python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS
npm run validate:computed: PASS
git diff --check: PASS
```

## Methodology Finding

v3.6.4 confirms the v3.6.3 Lock 3/4 workflow:

```txt
Name semantic routes first.
Then accept narrow mechanical visual cleanup.
Prove the cleanup did not mutate semantics.
Route editor parity gaps separately from front-end bridge correctness.
```

Temporary DOM probes remain a good fit for validating selector behavior without
expanding the committed specimen fixture when the cycle explicitly excludes
fixture growth.
