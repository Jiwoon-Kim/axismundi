# Changing the scheme at runtime

What was measured, so the next attempt starts from facts rather than from this
being tried twice.

## The problem

Every colour value in Axismundi lives in CSS. Its `theme.json` palette is 32
entries of `var(--md-sys-color-*)` and its `settings.custom` is empty, so
changing the scheme means redefining `--md-ref-palette-*` on `:root` — and
winning against the parent's own `:root` rules, which have identical
specificity. Source order decides, and the theme's stylesheets are late.

Style variations cannot do it. Measured: selecting one stores it as user global
styles, and `styles.css` does not survive that round trip — written to the
global styles post and read back, it is gone. `settings.custom` goes the same
way. As administrator with `unfiltered_html` and `edit_css`, identically. And
the ordering would have blocked it anyway: `global-styles-inline-css` prints at
byte 41430 of the page, the theme's `tokens.ref.css` link at 88324.

## What works

Two JS routes, both measured on the running site.

```
inline style on <html>   >   document.adoptedStyleSheets   >   <link> / <style>
```

Constructable stylesheets sit after the document's own sheets in the cascade,
which is exactly the position the problem needs. An inline property on the root
element beats both. Both are removable and the page returns to baseline:

```
before                         #6750A4      the parent's baseline
adopted sheet applied          #0F56CD
inline property applied        #00687C      beats the adopted sheet
inline property removed        #0F56CD      adopted sheet still holds
adopted sheet removed          #6750A4      clean
```

Shadow DOM is not needed. Material Web reaches for it because its components
are web components; custom properties inherit through shadow boundaries either
way, and this theme styles light-DOM markup, so document-level adoption reaches
everything.

`adoptedStyleSheets` is the better of the two: one object carrying all 40
tokens, swapped or dropped atomically, leaving no attribute on `<html>` for
anything else to trip over.

## What it looks like

A full scheme generated from M3's static Blue seed, applied over the baseline
in light mode, every value read off the running page:

| role | baseline | blue |
| --- | --- | --- |
| primary | `#6750A4` | `#0F56CD` |
| on-primary | `#FFFFFF` | `#FFFFFF` |
| primary-container | `#EADDFF` | `#DAE2FF` |
| secondary | `#625B71` | `#585E71` |
| tertiary | `#7D5260` | `#735471` |
| surface | `#FEF7FF` | `#FAF8FF` |
| surface-container | `#F3EDF7` | `#EEEDF4` |
| surface-container-high | `#ECE6F0` | `#E8E7EF` |
| on-surface | `#1D1B20` | `#1A1B21` |
| outline | `#79747E` | `#757780` |
| error | `#B3251E` | `#B3251E` |

Everything moves except `on-primary`, which is white under both, and `error`,
which is untouched on purpose — M3 holds it at a fixed hue across schemes.

**The visible change is small, and that is not a bug.** The surfaces carry
chroma 6, so they shift from a faint purple tint to a faint blue one and little
else; side by side the difference is real and subtle. A scheme swap only reads
as dramatic where the accent roles have surface area. Worth knowing before
building a picker and being disappointed by it.

## What is still unsolved

**The flash.** Applying a scheme after first paint means the baseline renders
first. The Theme Switcher plugin already solves this shape of problem for
light/dark, with an early inline script that runs before paint; the same
technique applies, and the same cost — the scheme has to be readable
synchronously, from a cookie or from server-rendered markup.

**Where it lives.** The generator is a build-time Node script. Computing a
scheme in the browser needs `material-color-utilities` on the page, which is
Google's own implementation and the only honest way to get HCT without
reimplementing it — it ships for JS, Dart, Java and C++, not PHP. So a runtime
picker either bundles the library or is limited to schemes generated ahead of
time. Pre-generated is far cheaper and covers a site-owner choosing a palette;
bundling is only needed if a visitor picks an arbitrary colour.

None of this belongs in a child theme long term. A colour that follows the user
across themes is a plugin, the way the scheme switcher already is. This file
records what the lab measured so that plugin does not rediscover it.
