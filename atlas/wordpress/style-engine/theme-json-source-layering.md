---
rule_id: style-engine.theme-json-source-layering
domain: style-engine
topic: source-layering-and-resolution
field_cluster: theme-json-merge-substrate
wp_min: "5.9"
wp_recommended: "6.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/
    section: "Global Settings & Styles — theme.json overview, source layering"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/
    section: "theme.json living spec — schema and merge semantics"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/classes/wp_theme_json_resolver/
    section: "WP_Theme_JSON_Resolver class — merging core/theme/user data"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_get_global_styles/
    section: "wp_get_global_styles() — resolved styles after merge"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_get_global_settings/
    section: "wp_get_global_settings() — resolved settings after merge"
    captured: 2026-05-10
related:
  - style-engine.preset-materialization              # downstream pipeline that consumes the resolved data
  - style-engine.css-variable-emission               # the CSS output stage; this chunk is upstream of it
  - style-engine.cascade-aggregation                 # final stylesheet ordering; this chunk is the data-layer merge upstream
  - style-engine.generated-selectors                 # runtime synthesis that operates on resolved data
  - theme-config.settings.color                      # one of many settings paths that participate in this merge
  - theme-config.styles                              # the styles half of the merged data
---

# RULE — theme.json source layering — the merge that produces the single resolved global styles object

## WHEN

You need to reason about *why* a particular setting or
style value appears (or doesn't) in the final resolved
output that the editor and the front end both read.
Use this knowledge when:

- A theme's `theme.json` declares a color palette and
  the user later adds their own colors via the Site
  Editor's Global Styles panel — and you need to know
  what the final merged palette looks like.
- A child theme's `theme.json` overrides part of the
  parent theme's, and you need to predict the merge.
- You're calling `wp_get_global_styles()` or
  `wp_get_global_settings()` and want to understand
  what data has *already* been merged before your code
  reads it.
- Reading `WP_Theme_JSON_Resolver` source and
  following the core → theme → user merge order.
- Diagnosing "why is the user's customization not
  visible" — usually a layer-precedence misunderstanding.

This chunk does **not** cover:

- The downstream CSS generation from the merged data —
  covered in `style-engine.preset-materialization` and
  `style-engine.css-variable-emission`.
- The selector synthesis that operates on resolved
  data — covered in `style-engine.generated-selectors`.
- The cascade ordering of the generated stylesheets at
  the browser level — covered in
  `style-engine.cascade-aggregation`.
- Per-block instance style attributes
  (`backgroundColor: 'primary'` on a single block
  instance). Those are a *different* mechanism that
  operates *after* the data-layer merge described here;
  Section D briefly contextualizes them.

The principle this chunk operates under: **The single
"global styles" object that downstream code reads is
not authored by anyone in particular — it is the result
of merging three (sometimes four) distinct source
layers into a single object whose values are
*resolved*, not *declared*. Each layer participates;
no layer alone is the answer.**

## SHAPE

### A. The three primary source layers

For any given site, three sources contribute to the
resolved `theme.json` data:

```
Layer 1 — CORE
   ├─ Source: WordPress core's built-in theme.json baseline
   ├─ Class:  WP_Theme_JSON_Resolver::get_core_data()
   ├─ Owns:   Default block schema (every registered block's
   │          allowable settings/styles surface), default
   │          color/typography/spacing fallbacks
   └─ Editable by site operator? NO

Layer 2 — THEME (parent + child if present)
   ├─ Source: theme.json file(s) in active theme directory
   │          (child theme's overlays parent's)
   ├─ Class:  WP_Theme_JSON_Resolver::get_theme_data()
   ├─ Owns:   The theme author's design declarations —
   │          custom colors, typography presets, layout
   │          defaults, opt-ins/opt-outs of core features
   └─ Editable by site operator? NO (would require editing theme files)

Layer 3 — USER
   ├─ Source: wp_global_styles post (custom post type)
   │          stored in wp_posts, edited via Site Editor's
   │          Global Styles panel
   ├─ Class:  WP_Theme_JSON_Resolver::get_user_data()
   ├─ Owns:   Per-site customizations on top of the theme —
   │          color overrides, custom palette additions,
   │          element-level style tweaks
   └─ Editable by site operator? YES (capability-gated; typically administrators)
```

The three layers compose **bottom-up**: Core sets
defaults; Theme overlays its design system; User
overlays their site-specific customizations. The final
resolved object is what `wp_get_global_styles()` /
`wp_get_global_settings()` return.

Two properties to pin:

- **The composition is *not* "last wins"
  unconditionally.** Different paths within the
  schema have different merge semantics (Section C).
  Some scalar values are last-wins; some collections
  merge by slug; some inheritance rules let lower
  layers leak through where higher layers haven't
  spoken.
- **No layer is sovereign over the resolved value.**
  A theme that declares `color.background = '#fff'`
  cannot prevent the user from later overriding to
  `'#000'` through the Global Styles UI (assuming
  the theme hasn't disabled user-side editing for
  that field). The resolved value at any path is a
  function of *all participating layers*, not of any
  one.

### B. The resolution APIs and the resolver class

Three runtime functions produce the merged result:

```php
$settings    = wp_get_global_settings();
$styles      = wp_get_global_styles();
$stylesheet  = wp_get_global_stylesheet();
```

| Function                       | Returns                                                            |
| ------------------------------ | ------------------------------------------------------------------ |
| `wp_get_global_settings($path)` | The merged `settings` tree (or a subset by path)                   |
| `wp_get_global_styles($path)`   | The merged `styles` tree (or a subset by path)                     |
| `wp_get_global_stylesheet()`    | A CSS string built from the merged data (passed through style-engine) |

All three delegate to `WP_Theme_JSON_Resolver`:

```
WP_Theme_JSON_Resolver
   ├─ get_core_data()    → WP_Theme_JSON instance for Core
   ├─ get_theme_data()   → WP_Theme_JSON instance for Theme
   ├─ get_user_data()    → WP_Theme_JSON instance for User
   └─ get_merged_data()  → WP_Theme_JSON with all three merged
```

`WP_Theme_JSON` is the per-layer object; it knows how
to validate, normalize, and merge. The resolver
produces three of them and asks them to merge in
order.

The merge result is **cached per request**. A second
call to `wp_get_global_styles()` in the same request
returns from cache; the merge runs once. This matters
because the merge is non-trivial computation.

### C. Merge mechanics — settings vs styles, scalar vs collection

Two top-level subtrees, with different merge shapes:

**`settings` subtree** — capability declarations.

This is where a layer says "such-and-such should be
available" (a color palette, font sizes, spacing
scale, etc.). Merge semantics:

- **Booleans / scalars** (e.g., `settings.color.custom`)
  — last layer wins. If the user disables custom
  colors and the theme had enabled them, the
  resolved value is `false`.
- **Indexed collections** (e.g., `settings.color.palette`)
  — varies by field, but the dominant pattern is
  *replace by slug*: a user-added color with
  slug `"accent"` adds to the palette; a user-added
  color with slug `"primary"` (where the theme also
  had `"primary"`) replaces the theme's version
  rather than duplicating it.
- **Per-block setting overrides** (e.g.,
  `settings.blocks["core/heading"].color.palette`)
  — block-scoped settings override the global
  setting for that one block, with the same merge
  semantics within the block scope.

**`styles` subtree** — applied values.

This is where a layer says "this should look like
that" (the body's background color, an element's
font size, etc.). Merge semantics:

- **Scalar style properties** (e.g.,
  `styles.color.background`) — last layer wins.
  User customization beats theme default.
- **Element styles** (e.g.,
  `styles.elements.button.color.text`) — same
  last-wins for scalars; structurally same shape.
- **Per-block style overrides** (e.g.,
  `styles.blocks["core/heading"].typography.fontSize`)
  — applies only when that block type renders;
  layers compose the same way.

**Inheritance / unset semantics:**

When a higher layer doesn't speak about a path, the
lower layer's value persists into the resolved
output. This is *not* the same as the higher layer
explicitly setting the value to `null` or `false`
(which would override). Silence is inheritance;
explicit unset is override.

The practical consequence: a theme that wants to
"reset" something the core layer provided must set
it explicitly in `theme.json`. Omitting the field
inherits the core's value, not "no value."

### D. The fourth de facto layer — per-block instance attributes

Per-instance block attributes (a heading with
`backgroundColor: 'primary'` on this specific
instance) are *not* part of the theme.json source
merge. They are a separate mechanism that operates
*after* the merge:

```
Layer 1-3 merge produces:
   - Resolved settings (which presets exist)
   - Resolved styles (default styling per element)
   - Generated CSS custom properties from settings
              │
              ▼
Per-block instance attributes apply on top:
   - Block's wrapper gets a CSS class
     (e.g., has-primary-background-color)
   - That class consumes the custom property
     emitted by the merge
              │
              ▼
Final rendered styling per block instance
```

The two mechanisms compose at the rendered-CSS
layer, not at the data layer. A block can only
reference a preset that the merged settings declare;
the merged settings determine the *vocabulary* the
block has to choose from. The block instance then
*selects* from that vocabulary per-instance.

For this chunk's purposes, the takeaway is: **the
3-layer merge defines the available design language;
per-block usage selects within it**. Conflating the
two mechanisms produces confusing diagnostics
("why is my color value still red when I changed
the user layer?" — because the user changed the
theme.json layer's preset value, but the block
instance is consuming a *different* preset slug).

### E. Reality boundary — what reaches the frontend, the editor, and what stays latent

The merged data has three audiences:

- **Frontend rendering.** `wp_get_global_stylesheet()`
  emits CSS at frontend page load (cached after
  first generation). The merged settings produce
  CSS custom property declarations
  (`--wp--preset--color--primary: #abc`); the
  merged styles produce element-targeted CSS
  rules.
- **Editor rendering.** The block editor
  reads the same merged data via REST endpoints
  (`/wp/v2/global-styles/themes/{theme}` for
  inspecting, `/wp/v2/global-styles/{id}` for
  user-layer editing). The Global Styles UI
  manipulates the user layer through these
  endpoints.
- **Latent declarations.** Settings that were
  declared but no rendered block or template
  references them produce CSS custom properties
  that exist in the stylesheet but are never
  consumed by any selector. The CSS variables are
  declared at `:root` regardless of usage.

This last point matters: declaration in theme.json
does not require usage. A theme that ships ten
custom colors emits ten CSS variables whether or
not any block uses them. *Declared at a layer ≠
applied at a render*.

The frontend / editor / latent distinction is the
data layer's *exposure* edge. Up to this point the
merge has produced an internal data structure;
beyond this point, that structure becomes CSS,
becomes editor-displayed, or sits in the resolved
data without ever surfacing visibly.

## WHY

### Why three layers rather than one

A single-layer model would mean either:

- The theme is the only source — users can't
  customize. Sites become identical to whatever the
  theme shipped, modulo manually editing theme files.
- The user is the only source — themes can't ship
  default styling. Every site starts blank.
- Core is the only source — themes and users are
  irrelevant. WordPress becomes a single visual
  identity.

The three-layer split lets each contributor own
their natural domain: core sets the schema and safe
defaults; themes provide the design system; users
personalize their site. The merge resolves the
contributions into one effective state without
requiring any contributor to coordinate with the
others ahead of time.

### Why path-based merging rather than whole-document replacement

Whole-document replacement would mean a user
adjusting one color overwrites every theme default.
Path-based merging means the user's color override
applies *just at that path*, while the rest of the
theme's declarations remain in effect.

This requires merge logic with path-awareness —
non-trivial — but the user-experience benefit is
large: a one-color customization stays surgical
instead of forcing the user to redeclare everything
the theme had set.

### Why the merge result is cached per request

The merge involves loading three sources, parsing
each, validating against the schema, and walking
the deep tree to compose. On a page that calls
`wp_get_global_styles()` from many places (block
render callbacks, editor extensions, theme
templates), running the merge each time would be
wasteful.

Per-request caching collapses N calls into one
computation. The cache is naturally bounded by
request lifetime, so layer changes (a user
saving a customization) take effect on the next
request without explicit invalidation.

### Why per-block attributes are a separate mechanism

If per-block attributes were part of the theme.json
merge, every block instance would need to be
considered during global resolution — which would
mean the merge depends on the rendered content,
which depends on the merge. Cycle.

Keeping per-block attributes downstream lets the
data-layer merge complete first (producing the
"what design language is available" result), then
each block instance independently consumes that
vocabulary. The two mechanisms compose; neither
depends on the other's output to do its own work.

## WHEN NOT

Skip source-layering reasoning if:

- You are working with **theme.json declarations
  for a single layer** (e.g., authoring just the
  theme's theme.json) and don't need to predict
  user-layer interactions.
- The question is about **CSS generation
  mechanics** rather than data resolution. Once
  the merge is complete, downstream chunks
  (`style-engine.preset-materialization`,
  `css-variable-emission`) are the right
  references.
- You are doing **per-block instance styling**
  through the block editor's UI. Those are
  applied through the block's wrapper class
  generation, not through theme.json merge.
- You are working with a **classic theme** that
  has no `theme.json`. The resolver still runs
  (with empty theme data), but the user-layer
  customization surface is reduced because the
  Global Styles UI is theme.json-aware.

## COUNTER-PATTERNS

### Anti-pattern 1 — Modifying core or theme data through filters expecting persistence

```php
add_filter( 'wp_theme_json_data_theme', function( $theme_json ) {
    $data = $theme_json->get_data();
    $data['settings']['color']['palette'][] = array( 'slug' => 'accent', 'name' => 'Accent', 'color' => '#abc' );
    return new WP_Theme_JSON( $data, 'theme' );
} );
```

The `wp_theme_json_data_theme` filter (and its
core / blocks / user counterparts) lets a plugin
*augment a layer's data*. Doing this is fine, but
the augmentation lives in PHP — it is not
persisted to the theme's file or to the user's
post. The plugin must continue running for the
augmentation to apply. Treat filter-based
modification as runtime augmentation, not as
authoritative storage.

### Anti-pattern 2 — Reading raw theme.json file instead of resolved data

```php
$theme_json = json_decode(
    file_get_contents( get_stylesheet_directory() . '/theme.json' ),
    true
);
$primary = $theme_json['settings']['color']['palette'][0]['color'];
```

This reads the *theme layer in isolation* and
misses the user layer's overrides plus core's
defaults. For values the consumer actually uses,
go through `wp_get_global_settings()` /
`wp_get_global_styles()`.

### Anti-pattern 3 — Treating user layer as theme-author authority

```php
// Plugin assumes user-layer values were "approved" by the theme.
$user_color = wp_get_global_settings( array( 'color', 'palette' ) );
foreach ( $user_color as $color ) {
    // ... use color, expecting it matches the theme's design intent
}
```

User-layer additions can include colors entirely
outside the theme's intended palette (a user
adding `#ff00ff` to test). If your plugin needs
"the theme-author-approved palette," read the
theme layer specifically:

```php
$theme_data = WP_Theme_JSON_Resolver::get_theme_data();
$theme_palette = $theme_data->get_settings()['color']['palette'] ?? array();
```

### Anti-pattern 4 — Bypassing the resolver in plugin-side overrides

```php
// Plugin storing its own settings and ignoring the merge.
update_option( 'my_plugin_global_color', '#abc' );
// Then expecting blocks to consume it without a merge participation.
```

Settings outside the theme.json merge don't
participate in the global styles vocabulary.
Blocks won't find `#abc` as a referenceable
preset; the editor's Global Styles UI won't
expose it. If a plugin should contribute to the
global styles vocabulary, do it through the
`wp_theme_json_data_theme` (or `_user`) filter —
not through a parallel storage path.

### Anti-pattern 5 — Conflating preset disappearance with preset undefinition

```php
// Theme removed a color from theme.json in an update.
// Existing blocks still reference the old slug.
// Block renders with no color (custom property is undefined).
```

When a theme update removes a preset, blocks that
referenced it by slug now reference an undefined
custom property. The CSS rule still emits
(`color: var(--wp--preset--color--removed)`), but
the property doesn't exist, so the browser falls
back to inherited / initial. Themes that remove
presets should either provide migration fallbacks
or accept that older content will degrade.

### Anti-pattern 6 — Assuming "last wins" for collection fields

```php
// Theme declares: palette = [primary, secondary]
// User declares:  palette = [accent]
// Expectation: resolved palette = [accent]
// Reality:     depends on merge semantics — may be [primary, secondary, accent] or replace by slug.
```

For collection fields, the merge is rarely pure
replacement and rarely pure append. The
semantics are field-specific (often
"replace-by-slug, append-if-new"). Read the
schema documentation for the specific field
before assuming a merge shape.

## OPERATIONAL NOTES

The source-layering substrate's interpretive shape,
in proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *multi-source* form. Each
  layer (Core / Theme / User) *declares* values
  at various paths; the resolved output *exposes*
  one value per path, computed by merging. Naming
  Law 1 here is genuinely clarifying because the
  *gap* between "this layer declared X at path Y"
  and "the resolved output has Y = X" is exactly
  the merge's transformation work. The framing
  *"declared at a layer ≠ resolved as reality"*
  captures the chunk in a phrase.
- **Doctrine 5 (Authority Continuity)** appears
  *moderately*. The schema path (e.g.,
  `styles.color.background` or
  `settings.color.palette[?slug='primary'].color`)
  is the continuity surface — same path persists
  across declarations in different layers and
  across the resolved output. Path-based identity
  is what makes the merge possible at all. Worth
  one mention; not a section.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *The
  highest-risk non-fit to name precisely* in this
  terrain. Layer precedence (Core < Theme < User)
  *strongly resembles* an arbitration ladder —
  ordered, with later contributors "winning" some
  fields. But the mechanism is not arbitration:
  - Every layer **participates**. None is
    discarded once a higher layer "wins."
    Inheritance pulls lower-layer values through
    where higher layers are silent.
  - The merge is **deterministic computation**, not
    candidate selection. Each path's resolved value
    is a function of all layers' contributions at
    that path, computed via field-specific merge
    rules — not a "first match wins" walk.
  - Collection fields explicitly **compose** values
    from multiple layers (replace-by-slug, append-
    if-new), which arbitration cannot express.
  Naming Law 4 here would conflate *override
  topology* with *candidate ladder*. The phrasing
  worth pinning: *layer precedence ≠ candidate
  arbitration; precedence is one input to a
  deterministic merge function, not a search rule*.
- **Federation.** *Explicit non-fit.* The three
  layers are not federation participants in the
  sense that recurs elsewhere in the KB. Federation
  (in plugin-dev, wp-scripts, wp-data-registry,
  interactivity) means *many parallel, equivalent
  participants* federating around a shared
  registry. Theme.json layers are *vertical and
  asymmetric*: Core / Theme / User have specific
  positions, specific authority over specific
  fields, and specific edit pathways. Layered
  governance ≠ federated participation. Pinning
  this distinction to preserve federation's
  meaning where it does apply.
- **Doctrine 6 (Authority Mediation).** No access
  mediation in the merge mechanism itself.
  Capability checks for *who can edit which layer*
  live in the editing surfaces (the Site Editor's
  Global Styles panel checks `edit_theme_options`
  before saving the user layer) — not in the
  merge. Omitted.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** All merging happens in a single PHP
  runtime per request. No cross-runtime authority
  preservation. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** The merge
  itself runs at request time. Some downstream
  caching (the generated stylesheet cache) has
  build-time-flavored properties, but the merge
  semantics this chunk documents are runtime.
  Omitted.
- **Section X archetypes.** A 3-layer data merge
  is not a "civilization." Same framework-omission
  discipline as the surrounding chunks. Omitted.

A literacy contribution worth pinning, central to
this chunk:

> *Layer precedence ≠ candidate arbitration.* A
> mechanism that takes contributions from multiple
> sources and computes a single resolved value
> through a deterministic merge function is not the
> same shape as a mechanism that walks ordered
> candidates looking for the first match. Both
> involve *order* in some sense — the merge is
> sensitive to which layer contributed what — but
> the merge **uses every layer's contribution**,
> while arbitration **selects one and discards the
> rest**. Override topology is a deterministic
> function of all inputs; arbitration is a search
> over alternatives.

This contribution adds a fifth distinct anti-Law-4
example to the toolkit:

- *Need fulfillment ≠ option arbitration*
  (resolver lifecycle).
- *Availability ≠ activation*'s implicit anti-Law-4
  (JIT translations).
- *Formula-driven selection ≠ candidate
  arbitration* (plural forms).
- *Operator-selected ordering ≠ candidate
  arbitration* (list tables).
- *Layer precedence ≠ candidate arbitration*
  (this chunk, theme.json source layering).

Each is a different mechanism that wears
arbitration's surface vocabulary. Together they
form a small inventory of "shapes that look like
ladders but aren't." The inventory is prose-level
literacy; not a constitutional pattern.

A second contribution, parallel to the data-layer
and i18n equivalents:

> *Declared at a layer ≠ resolved as reality.* A
> value present in any one of the contributing
> sources is not the same as the value the
> resolver returns at that path. Multiple layers
> participate; the resolver's output is a
> *function of all layers*, not the contents of
> any one of them. Reading any single layer in
> isolation answers a different question
> ("what did this layer declare?") than reading
> the merged result ("what is now true?").

This pairs with previous existence-vs-operation
toolkit contributions but extends them into a
*multi-source-declaration* form. Where the
existence-vs-operation tools distinguished one
declaration from its execution, this contribution
distinguishes *N declarations* from their merged
exposure. The shape is: *aggregation across
multiple authoritative sources to produce a single
resolved view*.

## CHECKLIST

When working with theme.json source layering:

- [ ] Read resolved values via
      `wp_get_global_settings()` /
      `wp_get_global_styles()`, not by parsing
      `theme.json` files directly.
- [ ] If you need *just one layer's* contribution
      (e.g., the theme author's intended palette
      excluding user additions), reach for the
      resolver class methods
      (`get_theme_data()`, `get_user_data()`)
      explicitly.
- [ ] When augmenting layers via filters
      (`wp_theme_json_data_theme`,
      `wp_theme_json_data_user`, etc.), remember
      the augmentation is runtime-only — it does
      not persist back to the theme file or the
      user post.
- [ ] Don't assume "last wins" for collection
      fields. Check the schema documentation for
      replace-by-slug vs append-only semantics.
- [ ] Treat silence in a layer as inheritance,
      not as override. Explicitly set values to
      override; omit them to inherit.
- [ ] Per-block instance attributes are a
      *separate* mechanism. Theme.json layering
      defines vocabulary; block instances select
      from it. Don't conflate.
- [ ] For "what does the user actually see," the
      answer requires the merged result *plus*
      per-block selections *plus* CSS generation
      cascade. This chunk owns the first; the
      other chunks own the rest.

## REFERENCES

- Global Settings & Styles handbook. Documents
  the source-layering concept and the editor's
  consumption.
  https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/
- theme.json living spec. Documents the schema
  and (where specified) the merge semantics for
  individual fields.
  https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/
- `WP_Theme_JSON_Resolver` reference. The class
  that owns the merge orchestration.
  https://developer.wordpress.org/reference/classes/wp_theme_json_resolver/
- `wp_get_global_styles()` reference. The
  resolved-styles read API.
  https://developer.wordpress.org/reference/functions/wp_get_global_styles/
- `wp_get_global_settings()` reference. The
  resolved-settings read API.
  https://developer.wordpress.org/reference/functions/wp_get_global_settings/

Cross-context:

- `style-engine.preset-materialization` —
  downstream pipeline that consumes the resolved
  data. The merge produces the data; that chunk
  documents the value-transformation lifecycle
  from preset declaration to applied CSS.
- `style-engine.css-variable-emission` — the
  emission stage that turns resolved settings
  into CSS custom property declarations.
- `style-engine.cascade-aggregation` — the final
  stylesheet ordering at the browser's CSS
  cascade. This chunk's merge is the data-layer
  upstream; that chunk's cascade is the CSS-layer
  downstream.
- `style-engine.generated-selectors` — runtime
  selector synthesis that operates on the
  resolved data.
- `theme-config.settings.color` and the rest of
  `theme-config/...` — field-level documentation
  for each settings/styles path. The merge
  semantics this chunk documents apply to each
  of those paths individually.
