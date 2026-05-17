---
rule_id: block.json-attributes-core
domain: block-authoring
topic: block-json
field_cluster: data
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
    section: "Attributes — overview, type validation, enum validation, default value, comment-delimiter (no source)"
    captured: 2026-05-09
related:
  - block.json-attributes-html-sources    # attribute / text / html sources
  - block.json-attributes-query-source    # query source (nested mini-DSL)
  - block.json-supports-field             # supports flags inject attributes implicitly
  - block.edit-save-components            # attributes flow to edit() / save()
  - block.deprecation                     # changing attribute schema requires deprecation
  - block.markup-representation           # how the delimiter encodes attributes
---

# RULE — `attributes` field — block state schema

## WHEN

Defining a block in `block.json` that needs to store any data — content,
configuration, references, computed values. The `attributes` field is
the schema for everything the block remembers between save and load,
plus the bridge between the block's `edit` component, `save` function,
and any server-side render.

This chunk covers the **schema layer**: the attribute object's
structure, `type` / `enum` / `default`, and the **default storage path**
(no `source` → block comment delimiter). Storage paths that read from
HTML markup (`source: 'attribute' | 'text' | 'html' | 'query'`) are
covered in sibling chunks because they introduce a different ontology
layer (DOM extraction rules, not state schema).

## SHAPE

```json
{
  "attributes": {
    "title": {
      "type": "string"
    },
    "size": {
      "enum": [ "large", "small", "tiny" ]
    },
    "isFeatured": {
      "type": "boolean",
      "default": false
    },
    "items": {
      "type": "array",
      "default": []
    }
  }
}
```

### Field map

| Field | Type | Required | Notes |
|---|---|---|---|
| `type` | `string` | required (unless `enum` provided) | One of: `null`, `boolean`, `object`, `array`, `string`, `integer`, `number`. `number` is treated the same as `integer`. |
| `enum` | `array` | required (unless `type` provided) | Fixed set of allowed values. Can be combined with `type`. |
| `default` | (matches `type` / `enum`) | optional | Value used when source extraction returns nothing. |
| `source` | `string` | optional | Where to read the attribute value from. **No source** = stored in block comment delimiter (this rule). With source = stored in HTML — see sibling chunks. |
| `selector` | `string` | optional | Used with sourced attributes; ignored when no source. |

### Combined `type` + `enum`

```json
{
  "size": {
    "type": "string",
    "enum": [ "small", "medium", "large" ]
  }
}
```

The `enum` constrains the values; `type` validates each enum entry's
shape.

## REQUIRES

- Each attribute MUST have at least `type` OR `enum`.
- If `default` is provided, its shape MUST match the declared `type` /
  `enum` constraints.
- `attributes` field is itself a top-level `block.json` field
  (sibling of `name`, `title`, etc.).
- The `attributes` prop arrives in the `Edit` component as
  `props.attributes` and in the `save` function as `attributes`. Update
  via `setAttributes()` in `Edit` only.
- For attributes WITHOUT a source (this rule): the value is
  automatically serialized into the block's comment delimiter. The
  block author does NOT need to write it into the `save()` output.
- For attributes WITH a source: the block author IS responsible for
  ensuring `save()` produces HTML matching the source extraction rule.
  Otherwise round-trip fails. (See sibling source chunks.)

## INVARIANTS

- Parsing flow on block load:
  1. WordPress reads the block delimiter.
  2. For each attribute: extract value from `source` (or from delimiter
     if no source).
  3. Validate value against `type` / `enum`.
  4. If validation fails OR no value found, use `default`.
  5. Pass into `Edit` / `save` / `render_callback` as `attributes`.
- The `type` field declares **what** the value is, NOT **where** it
  lives. Storage location is determined by `source` (or its absence).
- `null` and `boolean` are explicit type values, not "type was omitted"
  shorthands. Omitting `type` (without an `enum`) is an invalid
  attribute definition.
- Attributes WITHOUT a source serialize as JSON into the block
  delimiter:
  ```html
  <!-- wp:my-plugin/foo {"title":"hello","size":"large"} -->
  ```
  Default values MAY be omitted from the serialized JSON — they
  re-resolve from `default` on next load.
- Comment-delimiter storage is **schema-strict**: only declared
  attributes survive a save/load cycle. Undeclared keys in the
  delimiter JSON are dropped.
- Adding/removing/renaming attributes changes the schema. Existing
  saved blocks parsed under the new schema may fail validation —
  use **block deprecation** to migrate (see RELATED).
- The default-value fallback runs **per attribute**, not per block.
  An attribute can be missing/invalid while siblings are intact; it
  resolves to its individual `default`.
- Attribute **names** become object keys in `attributes` and live
  unchanged across edit/save. They are case-sensitive.
- ⚠ **Minimum WP version unknown.** The `attributes` field is part of
  original Block API (since the block editor was introduced) but
  source docs do not state an explicit `Since:` version for the field
  itself. Frontmatter `wp_min` is `"verification-needed"`.
- Per WP guidance: prefer storing data in HTML (via `source`) rather
  than in delimiter when feasible, to reduce delimiter payload size.
  Delimiter storage is the right choice for non-rendered data
  (configuration, IDs, computed flags).

## ANTIPATTERNS

- ❌ Defining an attribute with neither `type` nor `enum`. Invalid;
  parser may silently drop the attribute or fall through.
- ❌ Setting `default` to a value incompatible with `type`
  (e.g., `{ "type": "boolean", "default": "yes" }`). Parser
  inconsistencies; behavior across WP versions not guaranteed.
- ❌ Renaming an attribute without writing a deprecation. Saved
  content under the old name silently disappears. Always pair schema
  changes with `deprecated` registrations.
- ❌ Storing large blobs (rich content, image sets) in the comment
  delimiter (no source). Inflates post markup, slows parsing. Use a
  source-based attribute (`html`, `query`) so the data lives in HTML
  body where it belongs.
- ❌ Mutating `attributes` directly in the `Edit` component (e.g.,
  `attributes.title = 'new'`). Use `setAttributes({ title: 'new' })`.
  Direct mutation bypasses React state and persistence.
- ❌ Assuming the `default` runs on every render. It only runs when
  the parsed value is missing or fails validation; mid-edit values
  override defaults until cleared.
- ❌ Using `number` thinking it's distinct from `integer`. Per WP
  spec, they are equivalent. Choose one (`integer` is more common).
- ❌ Including computed/derived values in `attributes`. Derived state
  belongs in component memos / `useSelect`, not in persisted schema.

## RELATED

- `block.json-attributes-html-sources` — when an attribute reads from
  HTML markup (`source: 'attribute' | 'text' | 'html'`). Required
  reading for any attribute that round-trips through visible content.
- `block.json-attributes-query-source` — `source: 'query'` is a nested
  mini-DSL for arrays-of-objects extraction (e.g., gallery images).
- `block.json-supports-field` — supports flags implicitly inject
  attributes (`backgroundColor`, `style.color.background`, etc.).
  Both layers cohabit the same attribute schema; understand both.
- `block.edit-save-components` — `attributes` is the prop both
  components consume; `setAttributes()` is the only legitimate
  mutator.
- `block.deprecation` — changing the attribute schema (rename, type
  change, source change) requires `deprecated` array entries to
  migrate existing saved content.
- `block.markup-representation` — how the comment delimiter encodes
  attribute values; round-trip semantics.
