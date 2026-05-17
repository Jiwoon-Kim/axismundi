---
rule_id: block.json-attributes-query-source
domain: block-authoring
topic: block-json
field_cluster: data
parent_rule: block.json-attributes-core
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/#query-source
    section: "Attributes — query source"
    captured: 2026-05-09
related:
  - block.json-attributes-core            # parent: schema, type, default
  - block.json-attributes-html-sources    # query composes attribute/text/html sources
  - block.edit-save-components            # save() must produce repeated markup
  - block.inner-blocks                    # InnerBlocks is a different mechanism for nesting
  - block.deprecation                     # query schema change requires migration
---

# RULE — `attributes` query source — array-of-objects extraction

## WHEN

An attribute's value is a **collection of structured items extracted
from the rendered HTML** — for example, an array of images each with
a `url` and `alt`, or an array of list items each with text and a
data attribute. The `query` source is essentially a **nested attributes
definition**: each matched element becomes an array entry whose shape
is defined by an inner attribute schema.

This is distinct from `InnerBlocks` (which composes other blocks).
Use `query` for repeated **HTML structures**; use `InnerBlocks` for
repeated **block instances**.

## SHAPE

```json
{
  "attributes": {
    "images": {
      "type": "array",
      "source": "query",
      "selector": "img",
      "query": {
        "url": {
          "type": "string",
          "source": "attribute",
          "attribute": "src"
        },
        "alt": {
          "type": "string",
          "source": "attribute",
          "attribute": "alt"
        }
      }
    }
  }
}
```

| Field | Purpose | Required |
|---|---|---|
| `type` | Always `array` for query results. | yes |
| `source` | Literal value `"query"`. | yes |
| `selector` | CSS selector matching each array entry's element. Each match becomes one array item. | yes (effectively) |
| `query` | Inner attributes definition. Same shape as the outer `attributes` field — uses `source: "attribute" / "text" / "html"` (NOT another `query`). | yes |

### Extraction example

Saved markup:

```html
<div>
  <img src="https://example.com/big.jpg" alt="large image" />
  <img src="https://example.com/small.jpg" alt="small image" />
</div>
```

With the SHAPE above, `attributes.images` resolves to:

```json
{
  "images": [
    { "url": "https://example.com/big.jpg",   "alt": "large image" },
    { "url": "https://example.com/small.jpg", "alt": "small image" }
  ]
}
```

### Inner attribute selectors

Inside `query`, each inner attribute MAY use its own `selector` to
match a sub-element of the array entry:

```json
{
  "items": {
    "type": "array",
    "source": "query",
    "selector": "li",
    "query": {
      "label":   { "type": "string", "source": "text",      "selector": "span.label" },
      "anchor":  { "type": "string", "source": "attribute", "selector": "a", "attribute": "href" }
    }
  }
}
```

If the inner `selector` is omitted, the inner extractor runs against
the array-entry element itself (the element matched by the outer
`selector`).

## REQUIRES

- Outer `type` MUST be `"array"`. The query result is always an array.
- `query` is itself an object whose keys are attribute names and
  whose values follow the same shape as a single attribute definition
  with `source: "attribute" | "text" | "html"`.
- Inner attribute definitions use the same source vocabulary as the
  HTML extraction sources rule — re-read
  `block.json-attributes-html-sources` if unfamiliar.
- The block's `save()` MUST produce HTML where the outer `selector`
  matches one element per intended array entry. If `save()` renders
  3 `<img>` elements, parsing yields a 3-item array.
- For each array entry, `save()` must also produce the elements /
  attributes that the inner extractors look for.

## INVARIANTS

- Parsing flow:
  1. Apply outer `selector` to the block's HTML → obtain a list of
     matched elements.
  2. For each matched element, run the `query` inner attributes
     against it, producing a single object.
  3. Return the array of objects, in document order.
  4. Validate as `type: "array"`; on validation failure, fall to
     `default`.
- Inner attributes' `selector` (when present) is scoped to the
  outer-matched element's subtree — NOT to the whole block.
- Documentation states query is "effectively a nested block attributes
  definition" and that "It is possible (although not necessarily
  recommended) to nest further" — `query` inside `query` is supported
  but increases parsing complexity. Prefer flatter structures when
  feasible.
- The result preserves DOM document order. There is no
  ordering / sorting hook; if order matters, the order in the saved
  HTML is canonical.
- An empty match-set returns an empty array, not `default`. `default`
  applies on validation failure (e.g., wrong type), not on legitimately
  empty results.
- Each array entry is a plain JS object; mutation by `Edit` requires
  rebuilding the array (`setAttributes({ images: [...attributes.images, newOne] })`)
  rather than mutating in place.
- Like all sourced attributes, the round-trip integrity is the
  block author's responsibility: `save()` must produce HTML that the
  query schema can re-extract identically.

## ANTIPATTERNS

- ❌ `type: "object"` for a query source. Query produces arrays —
  always declare `array`.
- ❌ Forgetting the inner `query` field. Without it, the parser has
  no schema for what to extract per array entry; result is an array of
  empty objects (or undefined behavior).
- ❌ Using `source: "query"` inside the inner `query`. Technically
  allowed (per docs: "possible although not necessarily recommended")
  but parsing complexity compounds. Flatter is better.
- ❌ Mutating the array attribute in place in `Edit`
  (`attributes.images.push(...)` then `setAttributes({ images:
  attributes.images })`). React change detection may miss the update;
  always pass a new array.
- ❌ Confusing `query` with `InnerBlocks`. Query reads HTML and
  produces JS objects; InnerBlocks is a block-composition primitive
  with its own block instances. Use InnerBlocks when each "entry"
  is itself an editable block; use query when entries are static HTML
  structures owned by THIS block.
- ❌ Relying on inner attributes to extract from outside the
  array-entry element's subtree. The inner `selector` is scoped to
  the outer match's subtree.
- ❌ Renaming the outer or inner `selector` without a deprecation.
  Existing saved blocks won't re-parse → array becomes empty on
  load.

## RELATED

- `block.json-attributes-core` — parent: `attributes` field, `type`
  / `enum` / `default` semantics.
- `block.json-attributes-html-sources` — sibling sources
  (`attribute`, `text`, `html`); inner `query` extractors compose
  these.
- `block.edit-save-components` — `save()` HTML must include all the
  repeated structures the query selector will match.
- `block.inner-blocks` — alternative mechanism when each "entry"
  should be an editable block instance (InnerBlocks API), not raw
  HTML extraction.
- `block.deprecation` — outer/inner selector or schema changes
  require `deprecated` entries to migrate existing saved blocks.
