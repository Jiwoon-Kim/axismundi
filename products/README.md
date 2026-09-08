# Products — E layer

Everything that ships, and the two surfaces that support it.

| Directory | What it is | Published? |
|---|---|---|
| `wordpress/` | Themes and plugins. The source of record for anything installed on a real site | GitHub Releases, per product |
| `styleguide/` | The design-system documentation, a Jekyll site | <https://jiwoon-kim.github.io/axismundi/styleguide/> |
| `reference-implementations/` | Hand-coded components, measured before they become product code | No |
| `_archive/` | Superseded work kept for its record | No |

## The three are not interchangeable

`wordpress/` is authoritative. If the theme and a document disagree about a
value, the theme is right and the document is a bug.

`styleguide/` reads from `wordpress/` at build time — the theme's token CSS and
`theme.json` are copied or generated into the site rather than transcribed into
it. That is deliberate: a transcribed palette documents something nobody ships.
Where a value cannot be copied and has to be restated, a validator in `tools/`
holds the two together and CI fails if they drift.

`reference-implementations/axismundi-lab/` is the workbench. A component is
built and measured there first; the style guide then explains the result and
the lab keeps the evidence. Collapsing the two would erase the difference
between what was measured and what was claimed.

## What products may and may not do

Products are thin consumers of `core/` and `bindings/`. They register block
styles, enqueue assets, and provide WordPress integration code.

They do **not** define ontology entities or binding rules. Those live in
`core/` and `bindings/`, and the dependency only runs one way.
