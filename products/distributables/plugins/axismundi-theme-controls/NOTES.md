# Theme Controls — what was decided, and why

Kept because the reasoning cost far more than the code, and none of it is
visible from the code alone.

## The mechanism, and the three that were rejected

Axismundi keeps every colour value in CSS. Its `theme.json` palette is 32
entries of `var(--md-sys-color-*)` and its `settings.custom` is empty, so
changing the scheme means redefining `--md-ref-palette-*` on `:root` and
beating the theme's own `:root` rules, which have identical specificity.

**Chosen: an attribute gate.** `:root[data-ax-scheme="blue"]` is specificity
(0,2,0) against a bare `:root` at (0,1,0), so it wins on specificity and never
on source order. Measured with this stylesheet inserted *first* in head, ahead
of every one of the theme's own: still takes effect the moment the attribute is
set. Switching is one attribute, which is also why it can happen before first
paint.

**Rejected: style variations.** They cannot carry this. Selecting a variation
stores it as user global styles, and `styles.css` does not survive that round
trip — written to the global styles post and read back, it is gone.
`settings.custom` goes the same way. As administrator with `unfiltered_html`
and `edit_css`, identically. Ordering would have blocked it regardless:
`global-styles-inline-css` prints at byte 41430 of the page, the theme's
`tokens.ref.css` link at 88324.

**Rejected here, right elsewhere: `adoptedStyleSheets`.** Constructable
stylesheets do sit after the document's own sheets in the cascade. Measured
precedence:

```
inline style on <html>  >  document.adoptedStyleSheets  >  <link> / <style>
```

All of it reversible — removing the sheet returns the page to baseline exactly.
It is the right tool for a scheme *computed in the browser* from a colour this
plugin has never seen, because such a scheme cannot be pre-emitted behind an
attribute. It is the wrong tool for the shipped schemes, because it can only
run after first paint.

Shadow DOM is not needed for either. Material Web reaches for it because its
components are web components; custom properties inherit through shadow
boundaries anyway, and this styles light-DOM markup.

**Rejected: `material-color-utilities` on the page.** Computing a scheme in the
browser needs Google's HCT implementation. The subset required is 7 files,
67,533 bytes raw and 16,925 gzipped — affordable, and only needed for a colour
picked at runtime. Pre-generated schemes need none of it.

## Where the schemes come from

`assets/schemes.css` is generated, not written. The generator lives in the
Omphalos theme, which also holds M3's published palette tables transcribed and
checked against Axismundi's own tokens (94 of 94 matching):

```
node scripts/generate-scheme.mjs "Blue" "#1157CE" --css blue
```

Two copies of that formula would drift, which is why it is not duplicated here.

Each seed is tone 40 of one of M3's twelve published static palettes, so the
seed is a published value even though the families derived from it are
computed. The derivation is M3's TonalSpot, recovered by measuring the baseline
families rather than taken on faith: secondary at the source hue with chroma
16, tertiary at +60 degrees and chroma 24, neutral at chroma 6, neutral-variant
at chroma 8. Applied to M3's own baseline seed those constraints reproduce the
published tables within one step per channel — except the surface-container
stops (4, 6, 12, 17, 22, 24, 87, 92, 94, 96), which were added to the scale
later and are tuned rather than derived, and drift up to three.

The static palettes cannot themselves be schemes. They are standalone semantic
colours: take Blue as a primary and there is no published table at its hue + 60
to serve as tertiary. That is why generation is needed at all.

## What to expect visually

Less than you would think, and this is not a bug. The surfaces carry chroma 6,
so a scheme swap moves them from one faint tint to another —
`#F3EDF7` to `#EEEDF4` at `surface-container`. What reads clearly is the
accents: links, filled buttons, the active state. A scheme swap looks dramatic
only where the accent roles have surface area.

## Deliberately not done

- **Not a block.** The Theme Switcher plugin is the block-shaped one and took
  several releases to get there. One control in the footer proves the mechanism.
- **No settings screen**, no scheme CRUD, no per-scheme light/dark pairing.
- **No arbitrary colour.** See the library cost above.
- **`error` untouched by every scheme**, on purpose.

## Unexplained

Once during testing, a click on Orange left the cookie reading `cyan` a
navigation later. It did not reproduce: a clean click on Green persisted
correctly across a navigation to a different page, with the attribute, the
cookie, the token and the control's pressed state all agreeing. Recorded rather
than declared fixed.
