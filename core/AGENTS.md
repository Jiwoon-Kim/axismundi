# AGENTS.md -- Core reference material

`core/` records the research that led to Axismundi; it is not a parallel
implementation of the shipped products. Read a source here to recover context,
then verify its claim against the current product and upstream before relying
on it for a change.

## Material 3

`design-systems/material3/assets/` is protected font and icon source material.
Do not move, rewrite, subset, or regenerate it unless the task explicitly
names it.

The files there are current; the prose beside them describes the lab. Its icon
README names Material Symbols **Rounded** as the recommended default, which is
true of `axismundi-lab` and not of the shipped theme — the theme bundles
**Outlined** only, and `assets/README.md` still lists that theme as future work
and the deleted root `/styleguide/` mirror as a live surface. Take the assets
and the reasoning; check every claim about a product against the product.

`specs/` is high-value M3 reference, not a release contract. Re-check a value
against current Material guidance before shipping it; the Button XS icon gap is
one known example where the stored value predates the current specification.

`runtime/tokens.css` and `token_ontology.jsonld` preserve an early prototype
and provenance. Do not import either into products or use either to decide the
current token architecture. `runtime/base.css` follows the token direction but
is likewise not product CSS.
