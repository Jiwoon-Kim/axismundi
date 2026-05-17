---
rule_id: block.deprecation
domain: block-authoring
topic: deprecation
field_cluster: lifecycle
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/
    section: "Deprecation — matching algorithm, entry shape, migrate, isEligible, examples"
    captured: 2026-05-09
related:
  - block.edit-save-components       # validation failure triggers deprecation matching
  - block.markup-representation      # deprecations match against the stored IR
  - block.json-attributes-core       # attribute schema versioning across deprecations
  - block.json-supports-field        # supports schema versioning across deprecations
  - block.inner-blocks               # migrate may return [attributes, innerBlocks] tuple
---

# RULE — `deprecated` array — backward-compatibility matching runtime

## WHEN

You're updating a static block's `save()` markup, attribute schema, or
supports configuration in a way that changes the regenerated markup
shape. Existing posts using the old form would otherwise be flagged as
invalid on next editor load (because regenerated markup no longer
matches stored markup). Deprecation entries describe how the parser
should re-interpret old stored representations and migrate them to
the current schema.

Use this when a simple rename strategy (registering a new block under
a different name) is undesirable — i.e., you want existing content to
continue editing under the same block type after the upgrade.

This is the **compatibility runtime layered on top of the parser**:
deprecation does not "migrate the database forward"; it teaches the
current parser how to recognize old forms.

## SHAPE

### `deprecated` — array on the registered block type

```js
registerBlockType( 'my-plugin/foo', {
  // current schema
  attributes: {/* current */},
  supports:   {/* current */},
  save: ( { attributes } ) => <div>{ attributes.content }</div>,

  // backward compatibility entries — REVERSE chronological order
  deprecated: [
    v3,  // most recent old version
    v2,
    v1,  // oldest version
  ],
} );
```

### Deprecation entry shape

| Field | Type | Required | Notes |
|---|---|---|---|
| `attributes` | `object` | yes (if attribute schema differs from current) | Old attributes definition. **Not** inherited from current. |
| `supports` | `object` | yes (if supports differs from current) | Old supports definition. **Not** inherited. |
| `save` | `function` | yes | Old save() implementation. **Not** inherited. Must produce the markup that was actually stored under this version. |
| `migrate` | `function` | optional | Translates old attributes (and innerBlocks) into shape the current `save` expects. Signature: `(oldAttrs, oldInnerBlocks) => newAttrs \| [newAttrs, newInnerBlocks]`. Skipped if the entry's `save` produces invalid block. |
| `isEligible` | `function` | optional | Force-trigger this deprecation even if the block is currently valid. Signature: `(rawAttrs, currentInnerBlocks, data) => boolean`. NOT called when previous deprecations' save returned invalid. |

### `migrate` signature

```js
migrate( oldAttrs, oldInnerBlocks ) {
  // Either return new attributes object:
  return { content: oldAttrs.text };
  // OR return tuple of [attributes, innerBlocks]:
  return [
    { ...rest },
    [ createBlock( 'core/paragraph', { content: oldAttrs.title } ) ],
  ];
}
```

### `isEligible` parameters

```js
isEligible( rawAttributes, currentInnerBlocks, { blockNode, block } ) {
  // return true to force-apply this deprecation
}
```

`data.blockNode` = raw parsed form of the block; `data.block` = the
result of applying the block type to `blockNode`.

## REQUIRES

- Each entry MUST include `attributes`, `supports`, and `save` if any
  of them differ from current — they are NOT auto-inherited from the
  current registration.
- The entry's `save` MUST produce exactly the markup that was stored
  by this version of the block in the past. The matching algorithm
  uses `save` regeneration as the validation key.
- `deprecated` array order SHOULD be **reverse chronological** (newest
  deprecated version first). The matching algorithm tries entries in
  array order; recent versions are more likely to match first → less
  wasted work.
- `migrate` MUST be a pure function — given the same input, returns
  the same output. The matching algorithm calls it after pairing
  attributes with the matched deprecation's schema.
- The entry's `save` MUST be self-contained or use SNAPSHOT COPIES of
  helpers, NOT live imports. Live imports break deprecation behavior
  if the imported functions change shape later.

## INVARIANTS

- **Trigger condition:** A deprecation is attempted when EITHER:
  - the current `save` method does not produce a valid block (i.e.,
    the validation cycle fails — see `block.edit-save-components`); OR
  - a deprecation defines an `isEligible` function that returns true
    for the parsed block (this case applies even to currently-valid
    blocks).
- **The 5-step matching algorithm** (verbatim from source, with
  emphasis on the non-chain semantics):
  1. If the current `save` method does not produce a valid block, the
     **first deprecation** in the array is passed the original saved
     content.
  2. If that deprecation's `save` method produces valid content, this
     deprecation is used to parse the block attributes. If it has a
     `migrate` method, `migrate` runs using the attributes parsed by
     the deprecation.
  3. If the first deprecation's `save` method does NOT produce a
     valid block, subsequent deprecations are tried in array order
     until one producing a valid block is encountered.
  4. The attributes and any `innerBlocks` from the first matching
     deprecation are passed back to the current `save` to regenerate
     valid content.
  5. The current block should now be in a valid state; the
     deprecation workflow stops.
- **Deprecations are NOT a chain of migrations.** Each deprecation is
  an independent (old-shape → current-shape) translator. Source
  explicitly: *"Deprecations do not operate as a chain of updates in
  the way other software data updates, like database migrations, do."*
  The matching algorithm picks ONE matching deprecation and runs only
  its `migrate` — it does not chain v1.migrate → v2.migrate →
  v3.migrate.
- **Skip-entire-deprecation rule:** If a deprecation's `save` produces
  invalid markup against the stored content, the entire entry is
  skipped — `migrate` and `isEligible` are NOT called for that entry.
  Practical consequence: a new migration affecting older versions may
  require updating `migrate` in MULTIPLE deprecations to apply across
  all the eligible old versions.
- **`isEligible` short-circuit:** `isEligible` is **not called** when
  the results of all previous deprecations' `save` functions were
  invalid. It is meaningful only on a deprecation whose `save` itself
  matched.
- **`migrate` return shape:** Either an attributes object alone, OR a
  tuple `[attributes, innerBlocks]`. Use the tuple form when the
  migration needs to produce/restructure inner blocks (e.g., moving
  an attribute's content into a nested core/paragraph block).
- **Import danger:** A deprecation's `save` that imports helpers from
  other files inherits the helpers' future changes. If those helpers
  evolve, deprecation behavior changes silently — old posts that
  matched before may stop matching. Snapshot the helpers into the
  deprecation file to freeze behavior.
- **Reverse-chronological ordering rationale:** Most invalid posts
  were saved by the most recent prior version. Trying recent
  deprecations first short-circuits the most common case. Older
  versions appear later in the array and are tried only if no recent
  match.
- **Validation cycle re-runs after migration.** The output of step 4
  (current `save` invoked with migrated attrs) must itself be a valid
  block per the regeneration check. If migration produces attributes
  that the current `save` cannot serialize correctly, validation
  still fails — deprecation succeeded but the result is broken.
- **`attributes`, `supports`, `save` are NOT inherited.** Source:
  *"It's important to note that `attributes`, `supports`, and `save`
  are not automatically inherited from the current version, since
  they can impact parsing and serialization of a block, so they must
  be defined on the deprecated object in order to be processed during
  a migration."*
- **Alternative strategy: rename instead of deprecate.** Source lists
  two options: "Do not deprecate the block and create a new one (a
  different name)" OR "Provide a 'deprecated' version". The rename
  strategy avoids deprecation entirely — old block stays, new block is
  separate registration. Trade-off: existing content stays under the
  old name forever; users may need to manually convert.
- ⚠ **Minimum WP version unknown** for the deprecation API. It is
  part of the original Block Editor contract. Frontmatter `wp_min`
  is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Treating deprecations as a chain (v1.migrate runs first, then
  v2.migrate, ...). The matching algorithm picks ONE deprecation and
  runs ONLY its `migrate`. Mental model = parser polymorphism, not
  chained migrations.
- ❌ Omitting `attributes` / `supports` / `save` from a deprecation
  entry expecting inheritance from current. Each entry must
  explicitly include the shape that was stored under that version.
- ❌ Adding a deprecation but failing to update `migrate` in earlier
  deprecations that should also receive the same migration. Result:
  some old posts get migrated, others don't, depending on which
  deprecation matches first.
- ❌ Importing helper functions into a deprecation file from
  evolving modules. Behavior of old deprecations becomes coupled to
  current code; snapshot inline instead.
- ❌ Putting deprecations in chronological (oldest first) order. The
  matching loop runs front-to-back; reverse-chronological matches
  recent posts faster.
- ❌ Renaming an attribute without a deprecation. Old posts where
  the attribute was stored in delimiter JSON will silently lose the
  data on next load (parser drops undeclared keys per
  `block.json-attributes-core`).
- ❌ Writing a `migrate` that depends on side effects (network,
  storage, time). Migration runs synchronously during editor load;
  pure-function semantics only.
- ❌ Using `isEligible` to force-migrate currently-valid content
  without testing across all stored variants. Easy to break valid
  posts whose schema was already correct.
- ❌ Forgetting that `migrate` produces input for the CURRENT
  `save` — the migrated attribute shape MUST satisfy the current
  schema. Otherwise validation fails after migration.
- ❌ Calling `createBlock` inside deprecation `migrate` without
  matching the registered block type's schema. Inner blocks created
  with wrong attributes also become invalid blocks.

## RELATED

- `block.edit-save-components` — the validation cycle (parse →
  re-invoke save → compare) is what triggers deprecation matching on
  failure. Validation is the prerequisite mechanism.
- `block.markup-representation` — deprecations match against the
  stored IR (HTML + delimiters). Understanding what's actually in
  storage is required to reason about which deprecation will match.
- `block.json-attributes-core` — attribute schema changes are the
  most common reason for deprecation. The deprecation entry's
  `attributes` field describes the OLD schema; `migrate` transforms
  to the new.
- `block.json-supports-field` — `supports` changes can also affect
  parsing/serialization (auto-injected attributes), requiring
  deprecation entries to capture the old supports shape.
- `block.inner-blocks` — `migrate` may produce a tuple
  `[newAttributes, newInnerBlocks]` to restructure nested blocks
  (e.g., promoting an attribute's content into a child block).
