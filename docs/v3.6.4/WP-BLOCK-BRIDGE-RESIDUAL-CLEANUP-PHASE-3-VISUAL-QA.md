# v3.6.4 - WP Block Bridge Residual Cleanup - Phase 3 Visual QA

Date: 2026-05-20

Phase: 3 - Visual QA

## Verdict

Phase 3 visual QA is complete.

The v3.6.4 mechanical cleanup patches hold across light and dark modes:

```txt
Button:
  core/button anchors keep href semantics.
  text underline is removed.
  user-select is disabled.
  focus-visible outline remains visible.
  hover and pressed state layers resolve to M3 state tokens.

Quote/Pullquote:
  core/quote retains primary-bordered quote styling.
  core/pullquote reads as a distinct centered editorial surface.
  pullquote's inner blockquote no longer absorbs quote padding/bar styling.
  pullquote cite no longer absorbs quote cite prefix.
```

No implementation files changed in Phase 3.

## Surface

```txt
Specimen wall:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Viewport:
  390px mobile-width probe

Modes:
  light
  dark
```

Quote/pullquote used a temporary DOM probe appended under a
`.wp-block-post-content` host. The committed specimen fixture was not expanded.

## Button State Interaction

Target:

```txt
[data-ax-specimen-variant="button-fill"] .wp-block-button__link
```

### Focus State

```txt
Light:
  href:                 #button-fill
  text-decoration-line: none
  user-select:          none
  outline:              2px solid rgb(103, 80, 164)
  outline-offset:       2px

Dark:
  href:                 #button-fill
  text-decoration-line: none
  user-select:          none
  outline:              2px solid rgb(208, 188, 255)
  outline-offset:       2px
```

Assessment:

```txt
PASS.

The underline cleanup does not remove the focus affordance. The outline tracks
the theme's primary color in light and dark mode.
```

### Hover / Pressed State Layer

Values were sampled after the transition settled.

```txt
Light:
  hover ::before opacity:   0.08
  pressed ::before opacity: 0.10
  active border-radius:     8px

Dark:
  hover ::before opacity:   0.08
  pressed ::before opacity: 0.10
  active border-radius:     8px

Tokens:
  --md-sys-state-hover-state-layer-opacity:   0.08
  --md-sys-state-pressed-state-layer-opacity: 0.10
  --md-sys-shape-corner-small:                8px
```

Assessment:

```txt
PASS.

Button state interaction remains token-routed after the text-decoration and
user-select cleanup.
```

## Quote / Pullquote Distinct Surface

Probe structure:

```html
<blockquote class="wp-block-quote">
  <p>Quote probe.</p>
  <cite>Quote Cite</cite>
</blockquote>

<figure class="wp-block-pullquote">
  <blockquote>
    <p>Pullquote probe.</p>
    <cite>Pull Cite</cite>
  </blockquote>
</figure>
```

### Light Mode

```txt
core/quote:
  padding-inline-start: 24px
  padding-block-start:  8px
  border-inline-start:  4px solid rgb(103, 80, 164)
  text-align:           start
  cite::before:         "-- "

core/pullquote:
  margin-block-start:     32px
  padding-block-start:    24px
  border-block-start/end: 1px solid rgb(202, 196, 208)
  text-align:             center
  color:                  rgb(29, 27, 32)

core/pullquote inner blockquote:
  padding-inline-start: 0px
  padding-block-start:  0px
  border-inline-start:  0px

core/pullquote p:
  text-align:  center
  color:       rgb(29, 27, 32)
  font-size:   28px
  line-height: 36px
  font-style:  italic

core/pullquote cite:
  margin-block-start: 16px
  color:              rgb(73, 69, 79)
  font-size:          12px
  cite::before:       none
```

### Dark Mode

```txt
core/quote:
  padding-inline-start: 24px
  padding-block-start:  8px
  border-inline-start:  4px solid rgb(208, 188, 255)
  text-align:           start
  cite::before:         "-- "

core/pullquote:
  margin-block-start:     32px
  padding-block-start:    24px
  border-block-start/end: 1px solid rgb(73, 69, 79)
  text-align:             center
  color:                  rgb(230, 224, 233)

core/pullquote inner blockquote:
  padding-inline-start: 0px
  padding-block-start:  0px
  border-inline-start:  0px

core/pullquote p:
  text-align:  center
  color:       rgb(230, 224, 233)
  font-size:   28px
  line-height: 36px
  font-style:  italic

core/pullquote cite:
  margin-block-start: 16px
  color:              rgb(202, 196, 208)
  font-size:          12px
  cite::before:       none
```

Assessment:

```txt
PASS.

The two surfaces read as distinct:
  core/quote      = left primary bar + prose quote
  core/pullquote  = centered editorial pullquote + top/bottom dividers

The R1 absorption path remains closed in both modes:
  inner blockquote padding = 0px
  inner blockquote border  = 0px
  pullquote cite prefix    = none
```

## Phase 0 Non-Goals Confirmed

```txt
v3.6.3 semantic decision re-discussed: no
ripple/editor parity included:         no
custom block implementation:           no
plugin behavior:                       no
theme.json edit:                       no
functions.php edit:                    no
fixture expansion:                     no
```

## Editor Canvas Smoke

The block editor was opened at:

```txt
http://localhost:8888/wp-admin/post.php?post=29&action=edit
```

Observed editor structure:

```txt
Editor iframe:
  iframe[name="editor-canvas"]

Editor wrapper:
  block-editor-iframe__body editor-styles-wrapper post-type-page
```

Existing editor compatibility notice remains:

```txt
Block contains unexpected or invalid content.
Attempt recovery
```

This is the pre-existing BACKLOG #44 editor-valid fixture/editor compatibility
lane. v3.6.4 does not reopen or solve it.

### Button In Editor

Computed inside the editor canvas:

```txt
.wp-block-button__link:
  href:                 #button-fill
  text-decoration-line: none
  user-select:          none
```

Assessment:

```txt
PASS for the Phase 1 mechanical cleanup.

The editor canvas receives the button link-affordance cleanup while preserving
the anchor href.
```

### Quote / Pullquote In Editor

Temporary DOM probe inside the editor canvas showed the selector-narrowing
portion of Phase 2 applies:

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

But the editor iframe does not currently expose the required color sys tokens:

```txt
--md-sys-color-on-surface:        empty
--md-sys-color-outline-variant:   empty
```

As a result, the editor canvas does not resolve the pullquote figure dividers
the same way the front end does:

```txt
core/pullquote figure in editor:
  border-block-start-width: 0px
  border-block-start-style: none
  color: rgb(0, 0, 0)
```

Assessment:

```txt
PASS for selector narrowing / R1 absorption closure.
NOT a v3.6.4 implementation blocker.

This is an editor token/style parity carry-forward item, not a quote/pullquote
semantic-route failure. It belongs with the already-deferred #41 editor parity
or #44 editor compatibility lane.
```

## Front-End Drag Console Smoke

The user observed this console error while dragging on `?p=36`:

```txt
content.js:2 Uncaught (in promise) TypeError: t.substring is not a function
content.js:1 Uncaught (in promise) The message port closed before a response was received.
```

The page was retested in an extension-free Playwright Chromium session:

```txt
URL:
  http://localhost:8888/?p=36

Title:
  WordPress Block Catalog - Axismundi Pilot

Theme scripts observed:
  /wp-content/themes/axismundi-pilot/assets/scripts/pilot-block-bridge.js

Drag actions:
  text-selection style drag across post content
  anchor/button-adjacent drag

Console/page errors:
  0
```

Assessment:

```txt
NOT REPRODUCED in the extension-free browser.

The reported stack points at `content.js`, which is not a file in the
Axismundi Pilot theme or this repository. Treat this as likely browser
extension/content-script noise unless it reproduces in an extension-free
browser or in the tracked Pilot script bundle.
```

## Validation

No implementation files changed in Phase 3. Validation state carried forward
from Phase 1 and Phase 2:

```txt
python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS (Axis A-G all 1.000)
npm run validate:computed: PASS
git diff --check: PASS
```

## Next

Proceed to Phase 3 review, then Phase 5 close after GO.
