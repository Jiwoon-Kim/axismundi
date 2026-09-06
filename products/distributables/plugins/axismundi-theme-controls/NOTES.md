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

It varies far more than one look suggests, and an earlier version of this note
got it wrong by generalising from a single measurement.

On the dark home page with the Blue scheme the change is subtle. The surfaces
carry chroma 6, so they move from one faint tint to another — `#F3EDF7` to
`#EEEDF4` at `surface-container` — and only links and filled buttons clearly
shift. That was measured, and then written up as though it were the general
case.

On a light page, with a high-chroma seed, on a layout that gives the accent
roles surface area, the same mechanism is unmistakable: the Pink scheme
(tone-40 chroma 81) over the scaffold single-post template turns the surfaces,
the tag chips, the links and the supporting pane pink together.

So three things decide how visible a swap is, and none of them is this plugin:
whether the page is light or dark, how chromatic the seed is — 35.9 to 81.1
across the shipped set — and how much of the page the accent roles touch.

## Deliberately not done

- **Not a block.** The Theme Switcher plugin is the block-shaped one and took
  several releases to get there. One control in the footer proves the mechanism.
- **No settings screen**, no scheme CRUD, no per-scheme light/dark pairing.
- **No arbitrary colour.** See the library cost above.
- **`error` untouched by every scheme**, on purpose. Worth knowing what that
  costs the Red scheme: its primary sits at error's hue, so the two stop being
  distinguishable by colour alone.
- **A control that wraps.** Twelve options in a fixed corner row is more than
  the shape wants; at 800px it goes to two lines. Fine for a proof, and the
  first thing to change if this becomes something a site ships.

## The clamp

M3's TonalSpot constants assume a saturated seed. Applied to a near-neutral one
they invert the scheme: on the published static Grey, tone-40 chroma 1.6, the
raw constants give a secondary at chroma 16 and a tertiary at 24 — both far
more colourful than the primary they are meant to sit under. Grey variant does
the same at 3.6.

So no family is derived more chromatic than its own seed. A neutral seed gives
a neutral scheme, which is what asking for Grey means, and the clamp reaches
nothing else: every other static palette has a tone-40 chroma between 35.9 and
81.1, above the largest constant. Verified after the change that the
constraints still reproduce M3's published baseline within one step per
channel.

## Unexplained

Once during testing, a click on Orange left the cookie reading `cyan` a
navigation later. It did not reproduce: a clean click on Green persisted
correctly across a navigation to a different page, with the attribute, the
cookie, the token and the control's pressed state all agreeing. Recorded rather
than declared fixed.
