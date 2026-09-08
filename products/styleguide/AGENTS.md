# AGENTS.md — Style guide

A Jekyll site published at `/styleguide/`, built and deployed by
`.github/workflows/styleguide.yml` from a Pages Actions artifact.

```bash
python bin/build.py --verify   # sync, generate, --check, validate, build
python bin/build.py --serve    # local site at :4000
```

`--verify` is the gate for any change under this directory.

## It reads from the products

Token CSS, fonts, and `theme.json` values arrive at build time through
`tools/generators/sync_styleguide_assets.py` and the generators. **Do not
transcribe a palette, a type scale, or a spacing value into this site.** A
transcribed copy documents something nobody ships — that is why the old pilot
asset bridge was deleted.

Where a value cannot be copied and must be restated, the restatement gets a
validator in `tools/validators/`.

## Media queries cannot read var()

Measured, not assumed: at a 900px viewport `@media (min-width: var(--bp))` does
not match, and `CSS.supports()` returns **true** for it, so feature detection
does not catch this. Breakpoint values are therefore written into the CSS text
as literals at build time and emitted as custom properties from the same data;
`validate_styleguide_layout.py` checks that the two still agree.

## Demo markup is the block editor's markup

Specimens use the classes WordPress emits — `.wp-block-buttons`,
`.wp-block-button`, `.wp-block-button__link`, `is-style-*`. A demo that only
resembles the product cannot be used to check the product.

Where a contract is documented ahead of core supporting it, say so on the page
rather than implying core already emits it.

## Pages

Front matter drives placement. `_foundations/` and `_components/` take an
`order`; `_components/` also takes `kind: material | axismundi`. A component
page is for something the products ship — measure it in the lab first.

Component stylesheets carry a `?v=` cache-buster: a Jekyll rebuild does not
stop a browser holding the previous CSS, which has twice produced a "the fix
did not work" that was a cached file.

## Language

Korean body text, English boundaries — see the root `AGENTS.md`. CSS and Python
comments here are English, matching the products.
