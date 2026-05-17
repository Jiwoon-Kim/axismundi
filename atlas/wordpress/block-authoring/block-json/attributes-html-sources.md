---
rule_id: block.json-attributes-html-sources
domain: block-authoring
topic: block-json
field_cluster: data
parent_rule: block.json-attributes-core
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/#value-source
    section: "Attributes — attribute source / text source / html source"
    captured: 2026-05-09
related:
  - block.json-attributes-core            # parent: schema, type, default
  - block.json-attributes-query-source    # array-of-objects via nested mini-DSL
  - block.edit-save-components            # save() must produce matching HTML
  - block.deprecation                     # source change requires migration
  - block.markup-representation           # delimiter vs HTML storage trade-off
  - tooling.hpq                           # the underlying DOM extraction lib
---

# RULE — `attributes` HTML extraction sources (attribute / text / html)

## WHEN

An attribute's value should round-trip through the **rendered HTML**
of the block, not through the comment delimiter. Use this when the
data is also part of what the user sees / search engines crawl: image
URLs, captions, alt text, link hrefs, rich content. The block author
takes responsibility for ensuring `save()` produces matching HTML.

This chunk covers three of the source types: `attribute`, `text`,
`html`. The `query` source has its own chunk because it composes
these sources into a nested mini-DSL.

## SHAPE

### Common shape

```json
{
  "attributes": {
    "<attr-name>": {
      "type": "<json-type>",
      "source": "attribute" | "text" | "html",
      "selector": "<CSS selector>",
      "attribute": "<HTML attribute name>"
    }
  }
}
```

| Field | Purpose | Required |
|---|---|---|
| `source` | Selects the extractor: `attribute`, `text`, `html`. | yes |
| `selector` | CSS selector (anything `querySelector` accepts) — `tag`, `.class`, `#id`, etc. Defaults to the block's root node when omitted. | optional |
| `attribute` | Name of the HTML attribute to read. Required only with `source: 'attribute'`. | conditional |
| `type` | JSON type for validation. Most HTML extracts are `string`. `boolean` is allowed for attribute presence checks. | yes |

### `source: 'attribute'`

Read the value of a specific HTML attribute on the matched element.

```json
{ "url": { "type": "string", "source": "attribute", "selector": "img", "attribute": "src" } }
```

Saved markup → extracted value:

```html
<div>Block Content <img src="https://example.com/i.jpg" /></div>
```

→ `{ "url": "https://example.com/i.jpg" }`

**Boolean attribute presence check:**

```json
{ "disabled": { "type": "boolean", "source": "attribute", "selector": "button", "attribute": "disabled" } }
```

```html
<button disabled>…</button>
```

→ `{ "disabled": true }`

(All other HTML attribute values extract as `string`, even numeric
ones like `width="50"`.)

### `source: 'text'`

Read the **textContent** of the matched element (concatenated text
nodes, no markup).

```json
{ "content": { "type": "string", "source": "text", "selector": "figcaption" } }
```

```html
<figcaption>The inner text of the <strong>figcaption</strong></figcaption>
```

→ `{ "content": "The inner text of the figcaption" }`
(strong tag stripped — `textContent` rules.)

### `source: 'html'`

Read the **innerHTML** of the matched element (markup preserved).

```json
{ "content": { "type": "string", "source": "html", "selector": "figcaption" } }
```

```html
<figcaption>The inner text of the <strong>figcaption</strong></figcaption>
```

→ `{ "content": "The inner text of the <strong>figcaption</strong>" }`

Most commonly used by `RichText` for editable rich content.

## REQUIRES

- `selector` MUST be a valid `document.querySelector()` argument (CSS
  selector). If omitted, the source runs against the block's root
  node — matching only the outermost element.
- For `source: 'attribute'`, the `attribute` field is required and
  names the HTML attribute to extract.
- The block's `save()` function MUST produce HTML containing elements
  that satisfy each source's `selector`. If `save()` doesn't render the
  matched element, extraction returns nothing → falls back to `default`
  → data appears lost on next load.
- The block author OWNS the round-trip integrity. There is no automatic
  serialization for sourced attributes (unlike no-source attributes
  which auto-serialize into the comment delimiter).

## INVARIANTS

- Parsing flow for sourced attributes:
  1. Render the saved HTML into a DOM fragment.
  2. Apply `selector` (defaults to block root) to find the target
     element(s).
  3. Apply the source-specific extractor:
     - `attribute` → `element.getAttribute(name)` (boolean for
       presence check)
     - `text` → `element.textContent`
     - `html` → `element.innerHTML`
  4. Validate against `type`; fall back to `default` on mismatch.
- The `source` declares **where to read from**; the `type` declares
  **what shape the value has**. They are independent dimensions —
  `type: "string"` can pair with any of the three sources.
- `text` strips all child element markup (textContent semantics).
  `html` preserves child markup (innerHTML semantics). They differ
  on rich content — choose by whether the consumer treats the value
  as plain text or as HTML.
- `getAttribute()` returns the literal HTML attribute string. Numeric
  HTML attributes (`width="50"`) extract as `"50"` string — no
  automatic numeric conversion.
- The `disabled`-style boolean presence pattern is a special case:
  `type: "boolean"` + `source: "attribute"` returns `true` if the
  attribute exists on the element, `false` otherwise (regardless of
  attribute value).
- HTML extraction sources use **hpq** under the hood
  (https://github.com/aduth/hpq) — a small library that parses HTML
  into JS objects. Behavior matches a serverside HTML parser, not the
  full browser DOM API.
- Sourced attributes are **NOT** auto-saved into the delimiter. If
  the source extraction fails (selector matches nothing), the value
  is lost unless `default` recovers it. Compare to no-source
  attributes which round-trip via delimiter regardless of HTML.
- Multiple attributes can share a `selector` — each runs its own
  extractor against the same matched element.
- Selector defaults to block root when omitted. This is useful for
  reading attributes from the wrapper itself (e.g.,
  `id` / `class` / `data-*`).

## ANTIPATTERNS

- ❌ Declaring a sourced attribute without producing the matching
  HTML in `save()`. On next parse, extraction fails → falls to
  `default` → user data appears lost. Always pair attribute
  declaration with corresponding `save()` markup.
- ❌ Using `selector: "body img"` or anything reaching outside the
  block's root. Selectors only run against the block's HTML subtree.
- ❌ Storing rich content via `source: "text"` when you need to
  preserve formatting. Use `source: "html"` (or `RichText` patterns)
  to keep `<strong>`, `<em>`, links, etc.
- ❌ Storing complex JSON (objects, arrays-of-objects) via `source:
  "attribute"` and `JSON.parse` in `Edit`. Brittle. Use
  `source: "query"` for arrays-of-objects, or no-source comment
  delimiter for arbitrary JSON.
- ❌ `source: "meta"` (deprecated). Reads from post meta. Migrate to
  `EntityProvider` and core-data hooks (see how-to-guides for the
  metabox-replacement pattern).
- ❌ Forgetting `attribute` field with `source: "attribute"`. Without
  it, the parser doesn't know which HTML attribute to read. Spec
  requires the `attribute` field.
- ❌ Treating numeric attributes as numbers. `width="50"` extracts
  as `"50"` (string). Coerce in your component if you need an
  integer.
- ❌ Renaming the `selector` of a sourced attribute without writing
  a deprecation. Existing saved blocks have HTML matching the OLD
  selector; new schema can't extract → data lost. Schema changes to
  sources require deprecation entries.

## RELATED

- `block.json-attributes-core` — parent rule for the `attributes`
  field as a whole. Required reading for `type` / `enum` / `default`
  semantics that this rule builds on.
- `block.json-attributes-query-source` — `source: "query"` extracts
  arrays of objects by composing `attribute` / `text` / `html`
  extractors per array element. Use when you need to read repeated
  structures (galleries, lists).
- `block.edit-save-components` — `save()` produces the HTML that
  these sources extract from on next load. Schema/markup contract
  is two-sided.
- `block.deprecation` — source / selector / attribute changes require
  `deprecated` entries to migrate existing saved content.
- `block.markup-representation` — overall delimiter vs HTML storage
  trade-off; sourced attributes live in HTML, sourceless ones in
  delimiter.
- `tooling.hpq` — underlying HTML query library (informational).
