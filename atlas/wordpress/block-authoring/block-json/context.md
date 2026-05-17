---
rule_id: block.json-context
domain: block-authoring
topic: block-json
field_cluster: composition
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-context/
    section: "Context — Defining (providesContext / usesContext) + Using (JS / PHP)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#provides-context
    section: "Metadata — providesContext / Context (usesContext)"
    captured: 2026-05-09
related:
  - block.json-attributes-core         # context values are SOURCED from provider's attributes
  - block.json-hierarchy-constraints   # context propagates along the same tree topology
  - block.edit-save-components         # context is available in edit() but NOT save()
  - block.dynamic-rendering            # render_callback receives $block->context (PHP)
  - block.inner-blocks                 # the primary tree mechanism context propagates through
  - explanations.architecture          # mental model: React Context analogy (for orientation only)
---

# RULE — `providesContext` / `usesContext` — block-tree-scoped DI

## WHEN

Two related cases:

- **Provider:** Your block holds a value (e.g., a record ID, a layout
  mode, a query slug) that descendant blocks need to consume without
  hard-coding the relationship between provider and consumer block
  types. Use `providesContext` to publish the value on the block tree.
- **Consumer:** Your block needs to read a value declared by an ancestor
  block in the same block tree (e.g., a Post Excerpt block reading the
  Post ID from an enclosing Query Loop). Use `usesContext` to inherit
  the value.

The mechanism is a **block-tree-scoped dependency injection**: providers
publish keyed values, descendants in the same subtree opt-in by key.
There is no explicit reference between provider and consumer block
types — only the shared key.

## SHAPE

### Provider — `providesContext`

```json
{
  "attributes": {
    "recordId": { "type": "number" }
  },
  "providesContext": {
    "my-plugin/recordId": "recordId"
  }
}
```

| Map element | What it is |
|---|---|
| **Object key** (`"my-plugin/recordId"`) | The **context key** — a namespaced identifier consumers use to request this value. |
| **Object value** (`"recordId"`) | The **attribute name** on this same block whose runtime value gets exposed under that key. |

The mapping is `context-key → attribute-name (string reference)`.
The runtime value is whatever the named attribute holds at any moment;
when the attribute changes, the published context value updates.

### Consumer — `usesContext`

```json
{
  "usesContext": [ "my-plugin/recordId" ]
}
```

A flat array of context keys to subscribe to. Order doesn't matter.

### Consuming the value at runtime

JavaScript `edit`:

```js
edit( { context } ) {
  return 'The record ID: ' + context[ 'my-plugin/recordId' ];
}
```

PHP `render_callback` (3rd argument is the `WP_Block` instance):

```php
register_block_type( 'my-plugin/record-title', array(
    'render_callback' => function( $attributes, $content, $block ) {
        return 'The record ID is: ' . $block->context['my-plugin/recordId'];
    },
) );
```

## REQUIRES

- Provider block MUST declare an `attributes` entry whose name matches
  the value side of the `providesContext` map. The map only references
  attribute names; it cannot publish arbitrary computed values.
- Consumer block MUST declare every context key it intends to read in
  `usesContext`. Without explicit subscription, the value is NOT
  delivered (no implicit propagation).
- Provider and consumer MUST be in the same block tree, with provider as
  an ancestor of consumer (direct parent or any depth above).
- Context keys SHOULD be namespaced
  (`vendor-prefix/key-name`). Bare names risk collision with other
  plugins or with values WordPress core publishes.
- For PHP consumption, the block MUST use `register_block_type()` with
  a `render_callback` (or a `block.json` `render` field). Pure-JS
  blocks have no PHP context entry point.

## INVARIANTS

- Context resolution: when a block declares
  `usesContext: ["key"]`, the editor walks up the block tree from the
  consumer and uses the value from the **closest ancestor** whose
  `providesContext` includes that key. Other providers higher up the
  tree are shadowed.
- Currently, only **attribute-derived** values can be published.
  Source explicitly states: "Currently, block context only supports
  values derived from the block's own attributes. This could be
  enhanced in the future to support additional sources."
- Context flows ONLY through the **block tree** as composed in the
  editor — typically through `InnerBlocks` nesting. Cross-tree
  composition (e.g., separate template parts merged on render) is NOT
  documented as a propagation path; treat as verification-needed for
  any cross-tree assumption.
- **Context is NOT available in `save()`.** Only `edit` (client) and
  `render_callback` / `render` (server) receive it. This means
  context-derived values cannot be statically serialized into the
  saved HTML — they must be re-derived on every render.
- The server-side delivery path is `$block->context['key']` on the
  `WP_Block` instance passed as the 3rd argument to `render_callback`.
  No global / superglobal access pattern.
- The client-side delivery path is the `context` prop on the `Edit`
  component (`edit({ context })`). Not on `save()` props.
- Context values change when the provider's underlying attribute
  changes. Consumers re-render reactively in the editor.
- A consumer block placed OUTSIDE any provider's subtree receives an
  undefined value for that context key — guard against `undefined` in
  the consumer.
- ⚠ **Tree-boundary semantics for template parts, synced patterns
  (formerly reusable blocks), and other composition primitives are not
  fully specified in the source reference.** Verify per primitive
  before assuming context flows through them.
- ⚠ **Minimum WP version unknown.** Source docs describe the API but
  do not state introduction version. Frontmatter `wp_min` is
  `"verification-needed"`.

## ANTIPATTERNS

- ❌ Putting the runtime value in `providesContext` (e.g.,
  `{ "my-plugin/recordId": 42 }`). The map value MUST be an attribute
  name (string reference), not a literal.
- ❌ Using `providesContext` to expose a computed / derived value that
  is not stored as a block attribute. Currently unsupported. Workaround:
  store the computed value in an attribute (even if synthetic), then
  publish that attribute.
- ❌ Trying to access `context` in `save()`. Not provided; serialization
  must use only the block's own attributes.
- ❌ Bare (un-namespaced) context keys. Collision risk with other
  plugins / WordPress core. Always prefix with `vendor/`.
- ❌ Forgetting `usesContext` and expecting the value to arrive
  implicitly because the ancestor provides it. Subscription is
  explicit; without `usesContext` the consumer's `context` prop omits
  that key.
- ❌ Hard-coding the provider block name in the consumer. The
  consumer subscribes to a **key**, not to a block type. Many
  different provider blocks may publish the same key.
- ❌ Treating context as cross-tree global state. It propagates only
  along ancestor chain in a single block tree. For cross-tree shared
  state, use core-data / REST entities, not context.
- ❌ Assuming context updates synchronously in the front-end. The
  server-side `render_callback` runs once per request with the
  context resolved at parse time; runtime mutation is editor-only.
- ❌ Importing the React Context API directly in your block code
  expecting it to interoperate. Block context exposes its own
  `context` prop / `$block->context` — do not bypass via direct React
  Context consumer hooks unless you understand the implementation
  trade-offs.

## RELATED

- `block.json-attributes-core` — context values come FROM the
  provider block's attributes. The attribute schema is the source of
  truth; understand attributes first.
- `block.json-hierarchy-constraints` — `parent` / `ancestor` /
  `allowedBlocks` define the **topology** of the block tree; context
  is the **propagation** along that topology. Same graph, two views.
- `block.edit-save-components` — `edit({ context })` is the consumer
  entry point. `save()` does NOT receive context (key serialization
  boundary).
- `block.dynamic-rendering` — `render_callback($attributes, $content,
  $block)` is the server-side consumer entry point;
  `$block->context['key']` reads the resolved value.
- `block.inner-blocks` — the primary tree mechanism along which
  context propagates. Most provider/consumer pairings are an outer
  block holding `<InnerBlocks />` with descendants subscribing.
- `explanations.architecture` (planned) — mental model orientation
  only: block context resembles React Context but is constrained to
  attribute-derived values, NOT available in `save`, and supported on
  the server-side render path. Do not equate the two APIs.
