---
rule_id: block.dynamic-rendering
domain: block-authoring
topic: dynamic-rendering
field_cluster: contract
wp_min: "6.1"
wp_recommended: ""
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/static-dynamic-rendering/
    section: "Static or Dynamic rendering of a block — both modes + hybrid"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#render
    section: "Metadata — render field (Since WP 6.1)"
    captured: 2026-05-09
related:
  - block.edit-save-components       # save: () => null is the static-side marker
  - block.markup-representation      # the self-closing delimiter is the dynamic IR form
  - block.wrapper-attributes         # get_block_wrapper_attributes is the PHP wrapper hook
  - block.json-attributes-core       # $attributes flow into render_callback
  - block.json-context               # $block->context provides DI to dynamic blocks
  - block.inner-blocks               # InnerBlocks.Content needed in save() of dynamic-with-children blocks
  - block.register-via-block-json    # render_callback can be passed in $args
  - block.register-auto-php          # supports.autoRegister wraps this pattern
  - block-authoring.block-json.bindings        # bindings = same authority-not-ownership pattern at attribute layer
  - data-layer.entity-resolution               # entity authority that render_callback projects from
  - data-layer.persistence                     # persisted state feeding render input on each request
  - interactivity.directive-protocol           # render output may carry data-wp-* directives
  - interactivity.hydration                    # server-rendered HTML = FIRST reactive frame; render_callback produces it
---

# RULE — Dynamic rendering — `render_callback` / `render` field

## WHEN

Use dynamic rendering when at least one is true:

- The block's content depends on **server-side state at render time**:
  current site title, latest posts, current user, query results,
  template context, etc.
- The block's markup needs to **update immediately across all
  instances** without users editing each post (e.g., changing layout
  HTML applies to every existing block at next page load — avoiding
  validation errors that a static-block update would cause).
- The block is **PHP-only** (no client-side JS registration), via
  `supports.autoRegister`.

This chunk completes the static/dynamic ontology started in
`block.edit-save-components`. There, `save: () => null` answered
*"the front-end HTML is not canonical"*. This chunk answers the
follow-up question: **then who is the canonical rendering authority?**
Answer: the `render_callback` function (or `render.php` file). When
present, **its output replaces** any HTML that `save()` produced.

## SHAPE

### Three rendering modes

| Mode | `save()` returns | `render_callback` / `render` | Front-end output |
|---|---|---|---|
| **Static** | HTML markup | (none) | Stored save() output |
| **Dynamic (pure)** | `null` | defined | render_callback output, fresh per request |
| **Hybrid** | HTML markup | defined | render_callback output (saved HTML used ONLY if dynamic path is unavailable, e.g. plugin deactivated) |

### Two ways to declare dynamic rendering

1. **`render_callback` arg in `register_block_type()`** (PHP):

   ```php
   register_block_type( __DIR__ . '/build', array(
       'render_callback' => 'my_plugin_render_block',
   ) );
   ```

2. **`render` field in `block.json`** pointing to a PHP file
   (typically `render.php`), since WP 6.1:

   ```json
   { "render": "file:./render.php" }
   ```

### Render function signature

Both declaration paths receive identical parameters:

| Parameter | Type | Contents |
|---|---|---|
| `$attributes` | `array` | The block's attributes (parsed from delimiter + extracted from saved HTML for sourced attrs). |
| `$content` | `string` | The markup of the block as stored in the database, if any. Empty string for `save: () => null` blocks. |
| `$block` | `WP_Block` | The block instance. Carries `$block->context` for resolved block-context values. |

For `render.php` files, these are exposed as variables in scope:
`$attributes`, `$content`, `$block`.

### Minimal dynamic block

```php
// render.php
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
  <?php echo esc_html( $attributes['title'] ?? '' ); ?>
</div>
```

```js
// index.js — paired client-side registration
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
registerBlockType( metadata.name, {
  edit: Edit,
  save: () => null,   // dynamic — front-end deferred to PHP
} );
```

```html
<!-- Stored in post_content -->
<!-- wp:my-plugin/foo {"title":"Hello"} /-->
```

## REQUIRES

- Block MUST be registered server-side (`register_block_type()` with
  block.json or with a path containing block.json). Pure client-only
  registration cannot have `render_callback`.
- For `render` field: the file path MUST be a `WPDefinedPath` form
  (`"file:./render.php"`). Per WP 6.1+.
- The `render_callback` (or `render.php`) MUST be safe to load on
  every block render. Top-level function/class declarations in the
  file cause "already declared" fatals on multi-instance pages —
  declare once in a shared library, include from there.
- For dynamic blocks with `<InnerBlocks />` in the editor: the
  `save()` MUST use `<InnerBlocks.Content />` (not return `null`),
  otherwise the inner blocks are not serialized to the database and
  the dynamic render has nothing to wrap.
- Output from `render_callback` / `render.php` MUST be sanitized.
  WordPress does NOT auto-escape render output; use `esc_html`,
  `esc_attr`, `wp_kses_post` as appropriate.
- The wrapper element in render output SHOULD spread
  `get_block_wrapper_attributes()` to receive supports-API classes /
  styles / accessibility attributes (see `block.wrapper-attributes`).

## INVARIANTS

- **Canonical rendering authority** when both paths exist:
  `render_callback` / `render.php` output **wins** on the front end
  over any `save()` output. The saved HTML serves only as a fallback
  for cases where the render function is unavailable (plugin
  deactivated, render path removed).
- **`save: () => null` skips validation.** The block markup
  validation cycle (parse → re-invoke save → compare) does NOT run
  when save returns null. Source: *"When `save` is `null`, the Block
  Editor will skip the block markup validation process, avoiding
  issues with frequently changing markup."* This is a primary
  motivation for choosing pure-dynamic over hybrid.
- **The serialized form of a pure-dynamic block is the self-closing
  delimiter** (`<!-- wp:vendor/slug {"attr":"val"} /-->`). No HTML
  body. See `block.markup-representation`.
- **Hybrid blocks store full HTML AND use render_callback.** Use
  cases:
  - Cover block: saves HTML representation; render_callback injects
    featured image when "Use featured image" is enabled.
  - Image block: saves HTML; render_callback adds extra attributes
    conditionally.
  - The trade-off: storage bloat (HTML in DB) for graceful
    degradation when the render path goes away.
- **The two declaration paths are functionally equivalent.** Choose
  by codebase preference:
  - `render_callback` arg → all rendering logic in one PHP function
    co-located with the registration call.
  - `render.php` → separate file, naturally co-located with
    `block.json` and the JS source. `block.json` `render` field is
    the WP-recommended form for create-block-scaffolded plugins.
- **`$args['render_callback']` overrides `block.json.render`.** If
  both are provided, the function in `$args` wins.
- **`$content` is the stored body, NOT the regenerated save output.**
  For pure-dynamic blocks (`save: () => null`), `$content` is empty
  string. For hybrid blocks, it carries whatever `save()` produced
  the last time the post was saved.
- **Render runs once per block instance per request.** Heavy logic
  (database queries, network calls) compounds with the number of
  block instances on a page. Cache aggressively or use the
  `$content` fallback to avoid redundant work for unchanged blocks.
- **Editor preview uses `ServerSideRender`** (a React component) for
  dynamic blocks. The editor calls back to PHP via REST to fetch
  fresh HTML when attributes change. This makes editor previews
  accurate for dynamic content but adds a network round-trip per
  attribute edit.
- **Front-end render does NOT include block delimiters.** The
  `<!-- wp:... -->` markers exist only in the stored `post_content`.
  Front-end output from `render_callback` is plain HTML.
- **`$block->context['key']` provides resolved block-context values**
  (declared via `usesContext`). See `block.json-context`.
- ⚠ The `render` field arrived in **WP 6.1** (per
  block-metadata reference). `render_callback` arg in
  `register_block_type` is older but no `Since:` is documented.
  Frontmatter `wp_min` set to `6.1` for the `render` field path;
  feature-detect for `render_callback`.

## ANTIPATTERNS

- ❌ Returning markup from `save()` for a pure-dynamic block. The
  saved HTML never reaches the front end (render_callback wins),
  but it's still subject to validation — every change to `save()`
  causes existing posts to invalidate. Use `save: () => null`.
- ❌ Pure-dynamic block (`save: () => null`) but no
  `render_callback` / `render`. Front end shows nothing. The block
  is invisible.
- ❌ Heavy queries / external API calls in `render_callback`
  without caching. Each block instance triggers the work; multi-
  instance pages explode in cost. Use object cache, transients,
  or precomputed data.
- ❌ Outputting unescaped data in `render.php`. WordPress does not
  sanitize render output. XSS surface; use `esc_html`, `esc_attr`,
  `wp_kses_post` per context.
- ❌ Top-level `function foo() { ... }` declarations inside
  `render.php`. Re-included per block instance → fatal. Move
  declarations to a separately-included library file.
- ❌ Forgetting `<InnerBlocks.Content />` in `save()` of a dynamic
  block that has `<InnerBlocks />` in the editor. Inner blocks
  serialize to nothing; the render_callback has no `$content` to
  process for nested blocks.
- ❌ Manually constructing wrapper class strings instead of using
  `get_block_wrapper_attributes()`. Loses supports-API output
  (color classes, alignment, etc.) — the rendered front-end DOM
  drops user-configured styling.
- ❌ Reading from `$_GET` / `$_POST` / globals inside
  `render_callback` without sanitization & nonces. Render runs
  on every front-end page request; opens broad input surface.
- ❌ Assuming `$content` carries the regenerated save() output.
  It carries the STORED body — possibly stale relative to current
  `save()` if the schema changed. For attributes, always use
  `$attributes` (parsed fresh).

## RELATED

- `block.edit-save-components` — `save: () => null` is the static-
  side declaration; this rule defines the dynamic-side counterpart.
  Together they form the static/dynamic ontology.
- `block.markup-representation` — pure-dynamic blocks use the
  self-closing delimiter form; hybrid blocks use the paired form.
  The serialized IR encodes which mode is in effect.
- `block.wrapper-attributes` — `get_block_wrapper_attributes()` is
  the PHP companion to `useBlockProps.save()`. Required in render
  output to receive supports-API output.
- `block.json-attributes-core` — `$attributes` parameter is the
  parsed attribute object; defaults and types are declared in
  `block.json` `attributes`.
- `block.json-context` — `$block->context['key']` resolves to the
  context value provided by an ancestor block declaring
  `providesContext` for that key.
- `block.inner-blocks` — `<InnerBlocks.Content />` in save() is
  required to serialize inner blocks for dynamic blocks; the
  rendered output of nested blocks arrives via `$content`.
- `block.register-via-block-json` — both declaration paths
  (`render_callback` arg / `render` field) integrate with
  `register_block_type()`.
- `block.register-auto-php` — `supports.autoRegister: true` is a
  shorthand for the PHP-only / dynamic-rendering combination.

## RETROACTIVE REFRAMING (post-Phase-7-capstone)

**Status note**: This section was added 2026-05-09 after Phase 7
bounded contexts (bindings + data-layer + interactivity) closed.
The original chunk above is accurate but reads dynamic-rendering
through pre-Phase-7 ontology (SSR / render_callback / freshness).
Post-Phase-7, the same mechanism reads as a fundamentally
different ontological role.

The original chunk is NOT replaced; this section adds layered
re-reading visible only after downstream bounded contexts
matured.

**KB pattern**: This is the second explicit RETROACTIVE
REFRAMING section (after wrapper-attributes). The pattern is
established: bounded-context closure produces retrospective
ontology revelation about earlier chunks; the upstream chunk
gains a layered re-reading rather than a rewrite.

### Reframing — dynamic rendering as authority projection

Pre-Phase-7 reading: `render_callback` is "a PHP function that
generates HTML at request time."

Post-Phase-7 reading:

> `render_callback` is a **server-side authority projection
> node** in Gutenberg's distributed authority architecture.
> It reads from current authority sources (entities, post meta,
> context, computed values) and PROJECTS them into HTML for
> downstream consumption (browser parse + Interactivity
> hydration + cascade).

The mechanism is the same; the ontological role is different.
Dynamic blocks were already participating in distributed
authority continuity before the KB had language for it.

### RETROACTIVE INVARIANTS

#### A. Dynamic rendering = server-side authority projection

The backbone retroactive framing:

| block kind | serialized markup role | authority location |
|---|---|---|
| **static block** | serialized markup ≈ authority (truth IS in post_content) | post_content |
| **dynamic block** | serialized markup = projection snapshot (post_content stores invocation topology) | runtime resolution at request time |

This connects directly to bindings invariant #4
("Bindings separate serialization from authority"). Dynamic
blocks were the FIRST instance of this principle in Gutenberg
— bindings extended it to attribute-level granularity, but the
pattern existed at block-level since dynamic rendering was
introduced.

#### B. Serialization no longer owns truth (dynamic was the precedent)

Bindings + data-layer formalized the principle:

> The serialized form is a **placeholder / invocation /
> reference**; authoritative state lives in resolvable
> sources, not in the serialized markup itself.

Dynamic blocks established this pattern years before bindings:
- post_content stores `<!-- wp:vendor/foo /-->` (invocation
  topology only).
- Authoritative HTML is generated per request from CURRENT
  authority sources.
- Multiple users / contexts may see different rendered output
  from the same stored invocation.

This makes dynamic rendering the **proto-form** of the
authority-not-ownership ontology that bindings + data-layer +
hydration now formalize across the stack.

#### C. Dynamic rendering prefigures hydration continuity

Pre-Phase-7 framing of dynamic rendering: "PHP renders HTML
fresh per request."

Post-hydration framing:

> Dynamic rendering was already executing **authority continuity
> across execution boundaries** before the KB had vocabulary
> for it. Authority crossed: DB → PHP runtime → REST/entity
> resolution → serialization → frontend render. Each crossing
> required reconciliation; each boundary had failure modes;
> the lifecycle was already a pipeline.

Interactivity hydration is the CLIENT-SIDE half of what
dynamic rendering pioneered server-side. They are not two
separate features — they are two halves of the same
**execution-boundary authority projection** lifecycle:

```
Pre-Phase-7 (dynamic rendering alone):
   DB → PHP render → HTML (transmitted) → browser parse
   ↑─────── authority continuity ───────↑
   (no client-side reactive layer)

Post-Phase-7 (with interactivity):
   DB → PHP render → HTML+directives (transmitted) → browser parse → hydration → reactive runtime
   ↑──────────────── authority continuity ────────────────↑
   (client-side reactive layer continues the lifecycle)
```

Interactivity API is **NOT a new paradigm** — it is the
**latent runtime architecture becoming explicit**. Dynamic
blocks were already crossing execution boundaries; interactivity
extends the boundary crossings into reactive territory.

#### D. render_callback = server-side runtime reconciliation boundary

Pre-Phase-7 framing: "render_callback outputs HTML."

Post-data-layer + persistence framing:

> `render_callback` is a **runtime reconciliation boundary**
> equivalent to data-layer's resolver pipeline at the
> server-side render layer:
>
> - Reads current entity / persisted state (via
>   get_post_meta / WP_Query / get_option / etc.).
> - Resolves block context (block.json-context: $block->context).
> - Reconciles authoritative state into projection HTML.
> - Emits HTML that becomes the input to client-side hydration
>   (when interactivity is involved) or to the cascade /
>   browser cascade engine.

In KB-wide compiler/runtime symmetry terms:

| layer | server-side projection | client-side hydration |
|---|---|---|
| reactive JS | render_callback emits directive-annotated HTML | @wordpress/interactivity hydration attaches subscriptions |
| CSS | server-emitted styles + style-engine output | browser CSS engine ingests + cascades |

render_callback is the **server-side half of the compiler/runtime
linker** — Interactivity hydration is the client-side half. They
are paired execution-boundary mechanisms.

#### E. Server-rendered HTML as first reactive frame (retro linkage)

Hydration invariant #2 established:

> Server-rendered HTML is the FIRST reactive frame, NOT a
> fallback.

For dynamic blocks this means: `render_callback` PRODUCES the
first reactive frame. The HTML it emits is:
- Already in correct visual / textual state (per current
  authority).
- Already carrying directive declarations (when interactivity
  is involved).
- Already the input to the cascade engine.

`render_callback` is therefore not "PHP that produces HTML";
it is **the producer of the first reactive frame** in the
distributed authority continuity pipeline.

This reframing connects render_callback's responsibilities to
hydration's continuity invariants — they form one coordinated
pipeline.

### Pipeline closure across Phase 7

After this retroactive reframing, the full Phase 7 lifecycle
explicitly includes pre-Phase-7 mechanisms:

```
entity authority (data-layer)
   ↓
server-side projection (render_callback — THIS CHUNK)
   ↓
HTML serialization (with directives if applicable)
   ↓
network transport
   ↓
browser parse
   ↓
directive attachment / hydration (interactivity.hydration)
   ↓
client reactive authority (interactivity.runtime-state)
   ↓
persistence reconciliation (data-layer.persistence) — when actions persist
```

dynamic-rendering occupies stage 2-3 of the lifecycle. Without
it, the entire Phase 7 chain has no server-side authority-to-
HTML projection mechanism. WITH it, the chain has been complete
since WP 6.1 — Interactivity API just made the client-side
half explicit.

### KB-level coherence payoff

This retroactive reframing produces a structural narrative
continuity:

```
Pre-2026-05-09 KB framing:
   "Gutenberg suddenly became a reactive runtime in WP 6.5"
Post-this-retro KB framing:
   "Gutenberg has been a distributed authority continuity
    system since dynamic blocks were introduced.
    Interactivity API made the client-side half explicit.
    The server-side half (dynamic-rendering) was already in
    place; we just couldn't see it through pre-Phase-7
    ontology."
```

This continuity matters: KB no longer reads as if Phase 7 was
a sudden architectural pivot. It reads as **architectural
revelation** — Gutenberg's runtime ontology was latent in the
codebase; the KB's job was to surface it. Dynamic-rendering
was the proto-form; bindings + interactivity made it explicit.

**KB-level framing extension:**

> Architectural shifts in Gutenberg are often **revelations
> of latent structure**, not introductions of new paradigms.
> Dynamic-rendering was already authority projection.
> Bindings made the projection mechanism declarative at
> attribute layer. Interactivity made it reactive at runtime
> layer. Hydration made it cross-environment continuous.
>
> The architecture was always there; the documentation
> ontology evolves to surface it.
