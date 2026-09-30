# Material Symbols assets

The SVG files in this directory are from Google's Material Symbols, licensed under Apache
License 2.0.

* Source: Google Fonts Material Symbols export, imported 2026-09-30.
* Upstream: https://github.com/google/material-design-icons
* Licence: https://www.apache.org/licenses/LICENSE-2.0

They are the `outlined` style at 24dp, `FILL 0`, `wght 400`, `GRAD 0`, `opsz 24`. The
`manifest.json` records the original Material Symbols name and the semantic Axismundi registry
identifier independently. The identifier is stable even if the chosen glyph changes later.

The checked-in files differ from their exports only in rendering attributes owned by the
consumer:

* `fill="#e3e3e3"` is removed so `currentColor` controls the glyph.
* `width="24px"` and `height="24px"` are removed so the rendering component or WordPress Icon
  Registry owns sizing.

The `viewBox` and path data are otherwise unchanged.
