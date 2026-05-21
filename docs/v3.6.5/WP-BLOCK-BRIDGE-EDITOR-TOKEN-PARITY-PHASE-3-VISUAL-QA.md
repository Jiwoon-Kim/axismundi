# v3.6.5 - WP Block Bridge Editor Token Parity - Phase 3 Visual QA

Date: 2026-05-21

Phase: 3 - Visual QA

## Verdict

Phase 3 visual QA is complete.

Editor md-sys color token parity is restored. The editor iframe now resolves
the same light-mode md-sys color tokens that the front end uses:

```txt
--md-sys-color-on-surface:         #1D1B20
--md-sys-color-outline-variant:    #CAC4D0
--md-sys-color-on-surface-variant: #49454F
```

The pullquote divider and text colors now resolve inside the editor canvas.
Front-end light and dark values remain unchanged from v3.6.4.

No implementation files changed in Phase 3.

## Surfaces

```txt
Editor:
  http://localhost:8888/wp-admin/post.php?post=29&action=edit
  iframe[name="editor-canvas"]

Front end:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
  390px viewport probe

WordPress core:
  7.0
```

## Editor Canvas

Editor iframe structure:

```txt
root class: block-editor-iframe__html
body class: block-editor-iframe__body editor-styles-wrapper post-type-page ...
```

Token style landing:

```txt
tokens.ref:
  length: 6708
  hasRef: true

tokens.sys.light:
  length: 5626
  hasMdSys: true
  hasRef: true

tokens.sys.dark:
  length: 8631
  hasMdSys: true
  hasRef: true
```

Editor iframe root tokens:

```txt
--md-sys-color-on-surface:         #1D1B20
--md-sys-color-outline-variant:    #CAC4D0
--md-sys-color-on-surface-variant: #49454F
```

Editor pullquote:

```txt
border-block-start-width: 1px
border-block-start-style: solid
border-block-start-color: rgb(202, 196, 208)
color: rgb(29, 27, 32)
cite color: rgb(73, 69, 79)
```

Assessment:

```txt
PASS.

The malformed light sys stylesheet no longer collapses to an empty editor
inline style. Pullquote divider and color values resolve through md-sys tokens
inside the editor canvas.
```

## Front-End Light Mode

Front-end root tokens:

```txt
--md-sys-color-on-surface:         #1D1B20
--md-sys-color-outline-variant:    #CAC4D0
--md-sys-color-on-surface-variant: #49454F
```

Front-end pullquote:

```txt
border-block-start-width: 1px
border-block-start-style: solid
border-block-start-color: rgb(202, 196, 208)
color: rgb(29, 27, 32)
cite color: rgb(73, 69, 79)
```

Assessment:

```txt
PASS.

Front-end light mode remains unchanged and now matches editor canvas token
resolution for the affected pullquote surface.
```

## Front-End Dark Mode

Front-end dark-mode root tokens:

```txt
--md-sys-color-on-surface:         #E6E0E9
--md-sys-color-outline-variant:    #49454F
--md-sys-color-on-surface-variant: #CAC4D0
```

Front-end dark-mode pullquote:

```txt
border-block-start-width: 1px
border-block-start-style: solid
border-block-start-color: rgb(73, 69, 79)
color: rgb(230, 224, 233)
cite color: rgb(202, 196, 208)
```

Assessment:

```txt
PASS.

Dark mode still uses sys-layer remapping only. No v3.6.5 regression detected.
```

## Button Regression Smoke

Editor probe:

```txt
href:                 #button-fill
text-decoration-line: none
user-select:          none
```

Front-end light probe:

```txt
href:                 #button-fill
text-decoration-line: none
user-select:          none
```

Assessment:

```txt
PASS.

v3.6.5 token repair did not regress v3.6.4 button mechanical cleanup.
```

## #44 Warning Routing

The editor still reports the pre-existing invalid-content warning:

```txt
Block contains unexpected or invalid content.
```

Probe count:

```txt
invalidContentWarningCount: 57
```

Assessment:

```txt
NOT v3.6.5 scope.

This remains BACKLOG #44 editor-valid fixture / editor compatibility work. The
v3.6.5 fix restores editor token parity only; it does not repair fixture
validity and does not absorb #44 work.
```

## Phase 0 Non-Goals Confirmed

```txt
ripple bridge graduation implementation:          no
broader editor state parity matrix implementation: no
v3.6.3 semantic decision re-discussed:            no
custom block implementation:                      no
theme.json edit:                                  no
functions.php edit:                               no
fixture / pattern / template edit:                no
#44 editor-invalid-content fix:                   no
TT5-derived implementation:                       no
```

## Validation

Validation state carried forward from Phase 2:

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

Proceed to Phase 3 review, then Phase 5 close after GO.
