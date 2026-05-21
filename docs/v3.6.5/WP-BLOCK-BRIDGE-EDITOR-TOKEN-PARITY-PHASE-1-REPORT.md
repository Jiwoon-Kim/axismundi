# v3.6.5 - WP Block Bridge Editor Token Parity - Phase 1 Report

Date: 2026-05-21

Phase: 1 - Editor Token Plumbing Inventory

## Verdict

Phase 1 inventory is complete. No implementation files were edited.

Root cause classification:

```txt
E. other, with evidence
```

The editor token gap is not a simple missing enqueue. WordPress loads the Pilot
editor styles into the editor iframe as inline `<style>` blocks, and most token
files land correctly. The specific failure is that `tokens.sys.light.css`
becomes a zero-length inline style in the editor iframe because the file ends
with a dangling opening comment:

```css
/* ------------------------------------------------------------
```

Front-end CSS parsing keeps the earlier `:root` rule alive, so front-end md-sys
light tokens resolve. The WordPress editor-style transform is less forgiving:
the light sys file lands as an empty inline style, so default light md-sys color
tokens are absent in the editor canvas.

## Files Read

```txt
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/assets/styles/tokens.ref.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.light.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.dark.css
products/reference-implementations/axismundi-pilot/assets/styles/wp-preset.bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/wp-custom.bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.light.css
styleguide/stylesheets/tokens.sys.light.css
```

## Static Inventory

`functions.php` editor setup:

```php
add_theme_support( 'editor-styles' );
...
add_editor_style( array_values( $editor_styles ) );
```

The editor style list includes:

```txt
assets/styles/fonts.css
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

The front-end enqueue chain uses explicit dependencies:

```txt
fonts -> tokens.ref -> tokens.sys.light -> tokens.comp -> tokens.sys.dark
-> wp-preset -> wp-custom -> tokens -> base -> icons -> components -> blocks
-> prose -> pilot-block-bridge
```

The relevant light sys declarations exist in the source file:

```css
:root {
  --md-sys-color-on-surface:         var(--md-ref-palette-neutral-10);
  --md-sys-color-outline-variant:    var(--md-ref-palette-neutral-variant-80);
}
```

But all three copies of `tokens.sys.light.css` end with an orphan opening
comment:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.light.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.light.css
styleguide/stylesheets/tokens.sys.light.css

Last line:
  /* ------------------------------------------------------------
```

## Editor Iframe Probe

Surface:

```txt
http://localhost:8888/wp-admin/post.php?post=29&action=edit
Editor iframe: iframe[name="editor-canvas"]
Editor iframe URL: blob:http://localhost:8888/...
```

Editor iframe structure:

```txt
root tag:   HTML
root class: block-editor-iframe__html
body class: block-editor-iframe__body editor-styles-wrapper post-type-page admin-color-fresh wp-embed-responsive
```

HTTP / DOM style landing:

```txt
External editor CSS links present:
  wp-includes/css/dist/components/style.min.css
  wp-includes/css/dist/block-library/style.min.css
  wp-includes/css/dist/block-editor/content.min.css
  wp-includes/css/dist/block-library/editor.min.css
  wp-includes/css/dist/block-library/theme.min.css

Pilot editor styles are present as inline <style> blocks.
```

Inline style evidence:

```txt
style index 7:
  length: 6708
  hasRef: true
  source: tokens.ref.css
  cssRules: 1
  result: md-ref tokens land

style index 8:
  length: 0
  source position: immediately after tokens.ref.css
  expected source: tokens.sys.light.css
  cssRules: 0
  result: md-sys light defaults do not land

style index 9:
  length: 23539
  source: tokens.comp.css
  cssRules: 26
  result: spacing/typescale/state/motion tokens land

style index 10:
  length: 8631
  source: tokens.sys.dark.css
  hasMdSys: true
  hasRef: true
  cssRules: 4
  result: dark override rules land, but do not apply in light/default mode

style index 11:
  length: 2168
  source: wp-preset.bridge.css
  hasMdSys: true
  cssRules: 1

style index 12:
  length: 2789
  source: wp-custom.bridge.css
  cssRules: 1
```

Editor iframe root computed values:

```txt
--md-ref-palette-neutral-10:              #1D1B20
--md-ref-palette-neutral-90:              #E6E0E9
--md-ref-palette-neutral-variant-80:      #CAC4D0
--md-sys-color-on-surface:                empty
--md-sys-color-outline-variant:           empty
--wp--custom--axismundi--state-layer--hover-opacity: empty
--wp--custom--axismundi--state-layer--hover:         0.08
```

Notes:

- md-ref tokens land.
- comp/state tokens land.
- wp-custom bridge lands.
- `--wp--custom--axismundi--state-layer--hover-opacity` is not expected; the
  actual bridge token is `--wp--custom--axismundi--state-layer--hover`.
- md-sys light color tokens do not land because the light sys inline style is
  empty.

Editor pullquote computed values:

```txt
border-block-start-width: 0px
border-block-start-style: none
border-block-start-color: rgb(0, 0, 0)
color: rgb(0, 0, 0)
cite color: rgb(0, 0, 0)
```

Interpretation:

```txt
Bridge selector reaches the editor.
Pullquote structural styles reach the editor.
Color-dependent properties fail because md-sys light color tokens are missing.
```

## Front-End Probe

Surface:

```txt
http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
Viewport: 390px width
```

Front-end root computed values:

```txt
--md-ref-palette-neutral-10:              #1D1B20
--md-ref-palette-neutral-90:              #E6E0E9
--md-ref-palette-neutral-variant-80:      #CAC4D0
--md-sys-color-on-surface:                #1D1B20
--md-sys-color-outline-variant:           #CAC4D0
--wp--custom--axismundi--state-layer--hover-opacity: empty
--wp--custom--axismundi--state-layer--hover:         0.08
```

Front-end pullquote computed values:

```txt
border-block-start-width: 1px
border-block-start-style: solid
border-block-start-color: rgb(202, 196, 208)
color: rgb(29, 27, 32)
cite color: rgb(73, 69, 79)
```

Interpretation:

```txt
Front-end md-sys light tokens resolve despite the dangling trailing comment.
The front-end parser preserves the completed :root rule before the orphan
comment. The editor-style transform does not.
```

## Root Cause Bucket

Selected bucket:

```txt
E. other, with evidence
```

Why not A:

```txt
Token files are loaded into the editor iframe as inline style blocks.
md-ref, comp, dark sys, wp-preset, wp-custom, base, components, blocks, prose,
and pilot-block-bridge all appear in document.styleSheets.
```

Why not B:

```txt
:root selector declarations can land in the iframe. md-ref :root declarations
land; comp :root declarations land; wp-preset/wp-custom :root declarations
land.
```

Why not C:

```txt
Dark selector shape lands in the editor iframe. The dark sys stylesheet has
:root[data-theme="dark"] and @media prefers-color-scheme rules in cssRules.
The issue is the missing light default :root rule.
```

Why not D:

```txt
The md-sys light tokens are not overridden by later styles; they are absent
because the expected light sys inline style is empty.
```

Evidence for E:

```txt
tokens.sys.light.css ends with a dangling opening comment in lab, Pilot, and
styleguide copies. In the editor iframe, the inline style block at the expected
position for tokens.sys.light.css has length 0 and cssRules 0. Removing that
syntax defect is the narrow source-level fix to test in Phase 2.
```

## Implementation Route Chosen

Preferred Phase 2 route:

```txt
Fix the malformed trailing comment in tokens.sys.light.css across the tracked
token copies that currently carry it:

  products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.light.css
  products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.light.css
  styleguide/stylesheets/tokens.sys.light.css
```

This is not token duplication and not a new editor-only bridge. It restores the
existing md-sys light source file so WordPress editor-style ingestion can land
the same tokens the front end already computes.

Scope note:

```txt
The Phase 0 "Files Expected To Change" list did not expect lab/styleguide
changes. Phase 1 evidence shows the malformed token source exists in all three
tracked copies. Phase 2 should request review approval before patching all
three copies, or explicitly choose a Pilot-only patch if the reviewer wants the
cycle to stay narrower.
```

No `theme.json` edit is needed. No fallback editor-token bridge is needed yet.

## TT5 Reference Addendum

User provided a local Twenty Twenty-Five 1.5 reference tree:

```txt
C:\Users\thaum\dev\twentytwentyfive.1.5\twentytwentyfive
```

Phase 1 checked TT5 as a structure reference, not a copy source.

TT5 editor style pattern:

```php
function twentytwentyfive_editor_style() {
	add_editor_style( 'assets/css/editor-style.css' );
}
add_action( 'after_setup_theme', 'twentytwentyfive_editor_style' );
```

TT5's `assets/css/editor-style.css` is intentionally small:

```css
/*
 * Link styles
 * https://github.com/WordPress/gutenberg/issues/42319
 */
a {
	text-decoration-thickness: 1px !important;
	text-underline-offset: .1em;
}
```

TT5 does not solve the v3.6.5 failure by example because it does not ship a
large external md-ref/md-sys token CSS stack through `add_editor_style()`.
Instead, it relies primarily on `theme.json` global styles for core block
coverage and uses a tiny editor stylesheet for editor-specific polish.

Useful TT5 takeaways for future #41 / #44 cycles:

```txt
TT5 is a good selector/schema reference:
  theme.json block coverage for core/button, core/code, core/quote,
  core/pullquote, core/search, core/separator, core/navigation, etc.

TT5 is not a token-source replacement:
  Axismundi keeps M3 md-ref -> md-sys -> wp-preset/wp-custom locks.

TT5 is not a fork base for v3.6.5:
  this cycle's root cause is a malformed Axismundi token stylesheet, not a
  missing TT5-style editor CSS pattern.
```

Phase 2 should continue with the chosen route: repair the malformed
`tokens.sys.light.css` trailing comment first, then re-run editor token probes.

## Phase 1 Exit Criteria

```txt
Before values recorded for editor and front end: PASS
Root cause bucket A/B/C/D/E selected with evidence: PASS (E)
Implementation route chosen before any patch is applied: PASS
Implementation files edited in Phase 1: no
```

## Validation State

No implementation files changed in Phase 1, so full validation is not re-run
for this report.

Current git scope for Phase 1:

```txt
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
```

## Next

Submit this Phase 1 root-cause classification for Opus review.

Phase 2 should not patch until review decides whether the chosen route may
touch all three malformed `tokens.sys.light.css` copies or must stay Pilot-only.
