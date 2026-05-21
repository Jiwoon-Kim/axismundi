# v3.6.5 - WP Block Bridge Editor Token Parity - Phase 2 Report

Date: 2026-05-21

Phase: 2 - Editor Token Parity Patch

## Verdict

Phase 2 is complete.

The malformed trailing comment in `tokens.sys.light.css` was repaired across
the three tracked copies:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.light.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.light.css
styleguide/stylesheets/tokens.sys.light.css
```

Editor md-sys light color tokens now land in the editor iframe. Pullquote
divider and color values resolve in the editor canvas, matching the front-end
light-mode values.

No `theme.json`, `functions.php`, fallback editor-token bridge, TT5-derived
change, ripple runtime change, or #44 fixture/editor-valid change was made.

## Patch

Before:

```css
}

/* ------------------------------------------------------------
```

After:

```css
}

/* ------------------------------------------------------------ */
```

Why close instead of delete:

```txt
The trailing divider appears to be an intended section/comment divider. Closing
it preserves that authoring trace, keeps the diff to a one-line in-place
syntax repair per file, and balances comment opener/closer counts.
```

## Three-Copy Lockstep

Before Phase 2:

```txt
SHA256:
  9E1BE835CEC23BE40CC5C0B76B3341ED3729ABDE921B9F64A221321BF80F8A38

Lines:
  102

Comment count:
  /* = 47
  */ = 46
```

After Phase 2:

```txt
SHA256:
  7F837F7C7F7104C19ABC21563A8BFE2D05DB227858791B3CD41D25E2DCC26E98

Lines:
  101

Comment count:
  /* = 47
  */ = 47
```

All three copies remain byte-identical after the fix.

## Editor Iframe Probe

Surface:

```txt
http://localhost:8888/wp-admin/post.php?post=29&action=edit
WordPress core: 7.0
Editor iframe: iframe[name="editor-canvas"]
Editor iframe URL: blob:http://localhost:8888/...
```

Editor iframe structure:

```txt
root class: block-editor-iframe__html
body class: block-editor-iframe__body editor-styles-wrapper post-type-page ...
```

Token stylesheet landing:

```txt
tokens.sys.light inline style:
  index:    6
  length:   5626
  hasMdSys: true
  hasRef:   true

document.styleSheets token rule evidence:
  md-ref sheet:        cssRules > 0
  md-sys light sheet:  cssRules > 0
  md-sys dark sheet:   cssRules > 0
  wp-preset bridge:    cssRules > 0
  wp-custom bridge:    cssRules > 0
```

Editor iframe root computed values after fix:

```txt
--md-ref-palette-neutral-10:         #1D1B20
--md-ref-palette-neutral-90:         #E6E0E9
--md-ref-palette-neutral-variant-80: #CAC4D0
--md-sys-color-on-surface:           #1D1B20
--md-sys-color-outline-variant:      #CAC4D0
--wp--custom--axismundi--state-layer--hover: 0.08
```

Editor pullquote computed values after fix:

```txt
border-block-start-width: 1px
border-block-start-style: solid
border-block-start-color: rgb(202, 196, 208)
color: rgb(29, 27, 32)
cite color: rgb(73, 69, 79)
```

Acceptance:

```txt
tokens.sys.light length > 0: PASS
tokens.sys.light cssRules > 0: PASS
editor --md-sys-color-on-surface = #1D1B20: PASS
editor --md-sys-color-outline-variant = #CAC4D0: PASS
editor pullquote divider visible: PASS
```

## Front-End Regression Probe

Surface:

```txt
http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
Viewport: 390px width
WordPress core: 7.0
```

Front-end root computed values:

```txt
--md-ref-palette-neutral-10:         #1D1B20
--md-ref-palette-neutral-90:         #E6E0E9
--md-ref-palette-neutral-variant-80: #CAC4D0
--md-sys-color-on-surface:           #1D1B20
--md-sys-color-outline-variant:      #CAC4D0
--wp--custom--axismundi--state-layer--hover: 0.08
```

Front-end pullquote computed values:

```txt
border-block-start-width: 1px
border-block-start-style: solid
border-block-start-color: rgb(202, 196, 208)
color: rgb(29, 27, 32)
cite color: rgb(73, 69, 79)
```

Acceptance:

```txt
front-end md-sys light values unchanged: PASS
front-end pullquote values unchanged: PASS
```

## Scope Guard

```txt
TT5-derived changes:                         none
ripple runtime changes:                      none
broader editor state parity implementation: none
#44 editor-invalid-content changes:          none
theme.json changes:                          none
functions.php changes:                       none
fallback editor token bridge:                none
```

The Phase 1 note about
`--wp--custom--axismundi--state-layer--hover-opacity` was treated as probe
noise, not a defect. The actual existing token
`--wp--custom--axismundi--state-layer--hover` resolves to `0.08` in both editor
and front-end probes.

## Validation

```txt
wp-env run cli wp core version: 7.0
wp-env run cli wp core update: WordPress is up to date

python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS (Axis A-G all 1.000)
npm run validate:computed: PASS
git diff --check: PASS
```

## Next

Proceed to Phase 2 review. After GO, Phase 3 should perform editor/front-end
visual QA and confirm the editor-invalid-content warning remains routed to
BACKLOG #44, not absorbed into v3.6.5.
