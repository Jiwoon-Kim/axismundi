# Carousel — Ontology & Binding Check

> Checks the carousel lab module against the project's WordPress block ontology
> (`bindings/wordpress-material3/binding_map.json` and the M3 token ontology),
> and against the project's theme/plugin territory rules.

## Core claim

> **Gallery is not Carousel.**
> **Gallery + horizontal layout context + progressive interaction layer**
> **can become a Carousel candidate.**

This sentence is the constraint that determines how the carousel module can
ever be bound to a WordPress block. It says three things:

1. There is **no direct 1:1 binding** between `core/gallery` and an M3 carousel.
2. A binding exists only when **three preconditions co-occur**: the block is a
   `core/gallery`, *and* it is in a horizontal layout context, *and* the
   progressive interaction layer (this lab module or a successor) is loaded
   and applied.
3. Without all three, `core/gallery` continues to render as a static gallery
   grid — which is the correct fallback.

## Ontology binding (informal)

In `bindings/wordpress-material3/binding_map.json` terms, the carousel
binding would be a **conditional binding** rather than a `Direct.*` binding:

```
core/gallery
  → conditional binding
  → M3 carousel / image list / horizontal media rail
```

Conditions (all required, ordered by reliability of detection):

| Condition | Mechanism |
|---|---|
| Block is `core/gallery` | block name match |
| Horizontal layout context | layout context detection (e.g. parent is a `core/group` with a flow direction marker, or the block has a `.is-style-horizontal` style variant, or both) |
| Progressive interaction layer present | `lab-carousel.js` (or its eventual successor in `components.css` / `interactions.js`) is loaded *and* `enableMaterialYouSliders()` (or its successor) successfully attaches to a `.ax-material-slider`-class wrapper |

If any condition is missing, the binding does not apply — the gallery
renders as a gallery.

## Why a direct `core/gallery → carousel` binding is rejected

Three reasons, ordered from most architectural to most practical:

1. **Block semantics**. `core/gallery` is a *gallery* — a collection of
   images with a static spatial relationship. A carousel imposes a temporal
   relationship (you see one item, then the next, then the next). Forcing
   every gallery into a carousel changes the meaning of the user's content,
   not just its presentation.

2. **Federation portability**. The microblog target federates over
   ActivityPub. Federated consumers (Mastodon, etc.) strip presentational
   chrome and serialize content into simpler structures. A gallery
   federates as an image collection; a carousel does not federate as a
   useful structure at all (the temporal "one at a time" reading collapses
   to "all of them at once" on the consumer side). Direct-binding a
   gallery to a carousel would harm federated rendering for no gain.

3. **WordPress block editor expectations**. Authors who insert a
   `core/gallery` block expect a gallery preview in the editor. A binding
   that silently swaps to a carousel breaks editor WYSIWYG and confuses
   authors during content production.

A *style variant* or a *block-level pattern* layered on top of `core/gallery`
is the right shape for the carousel binding: opt-in by author, falls back
cleanly, federates as gallery.

## Theme territory vs Plugin territory

Per the project's Constitution and the GPT consultation that authorized
v3.3.2, this module's eventual integration must stay within the theme
territory boundary. Anything outside is plugin territory and does not
belong in `components.css` / `interactions.js` / theme runtime.

### Theme allowed

- CSS `scroll-snap` and `overflow-x: auto` on the gallery wrapper.
- Gallery fallback rendering when JS is disabled or the carousel layer is
  not loaded (the gallery just looks like a gallery).
- Previous / next button DOM + their click handlers.
- `prefers-reduced-motion: reduce` handling (instant-snap on reduced-motion).
- Progressive enhancement JS — i.e. JS that *adds* behavior to a still-valid
  no-JS baseline.

### Plugin territory (NOT for this module or its successor)

- Block editor carousel picker UI (a sidebar inspector that lets authors
  switch a gallery into carousel mode — that is editor UX, not runtime
  rendering).
- Custom slide schema (new block attributes for per-slide options like
  "duration", "transition", etc. — that requires block registration which
  is plugin-scope).
- Slide reorder UI (drag-and-drop reordering inside the editor — same
  rationale).
- Remote or federated media logic (fetching slides from a remote feed,
  embedding ActivityPub objects as slides — that is application logic,
  not theme presentation).
- A `carousel` custom post type — clearly plugin scope.

This boundary is enforced by where the code lives, not just by review:
anything that touches block registration, inspector controls, REST endpoints,
or CPT definitions cannot ship inside a theme — it must live in a plugin
package under `products/distributables/plugins/`.

## Mapping to existing binding-map schema

When the carousel module passes its audit and is ready for ontology
integration, the corresponding entry in
`bindings/wordpress-material3/binding_map.json` should look approximately
like:

```jsonc
{
  "binding_id": "core_gallery_carousel_conditional",
  "binding_type": "Conditional",  // not Direct.CoreBlockStyle
  "applies_to_block": "core/gallery",
  "conditions": [
    { "kind": "layout_context", "value": "horizontal" },
    { "kind": "interaction_layer_present", "value": "ax-material-slider" }
  ],
  "render_path": {
    "with_conditions_met": "M3 carousel (multi-browse | hero | uncontained)",
    "fallback":            "core/gallery default grid"
  },
  "notes": "See axismundi-lab/docs/CAROUSEL-ONTOLOGY-CHECK.md."
}
```

This entry does not exist yet — the carousel module is still lab-internal.
It will be added in the version that promotes the module out of lab.

## Status

- **Carousel module location**: lab (not in main, not in publish-surface HTML).
- **Binding map entry**: not present.
- **Conditions detection logic**: not implemented (this is a v3.3.x or
  v3.4.x task).
- **Gallery fallback prototype**: not built (would also be a v3.3.x task).

The module is currently a free-standing visual / interaction experiment.
Anything that consumes it has to load `lab-carousel.{css,js}` manually
(only `lab-carousel-pattern.html` does this). When the gallery fallback
prototype is built, this file should be updated with the actual binding
map JSON, the actual detection function signature, and the actual
fallback HTML pattern.
