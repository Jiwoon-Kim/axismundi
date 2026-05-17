---
rule_id: block.inner-blocks
domain: block-authoring
topic: inner-blocks
field_cluster: composition
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/nested-blocks-inner-blocks/
    section: "Nested Blocks: Using InnerBlocks (component + hook + props + relationships)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useinnerblocksprops
    section: "@wordpress/block-editor — useInnerBlocksProps hook"
    captured: 2026-05-09
related:
  - block.json-hierarchy-constraints   # parent / ancestor / allowedBlocks (block.json — schema-level constraints)
  - block.wrapper-attributes           # useBlockProps must be called before useInnerBlocksProps
  - block.markup-representation        # nested delimiters in serialized IR mirror the innerBlocks tree
  - block.json-context                 # providesContext flows down through InnerBlocks descendants
  - block.dynamic-rendering            # dynamic blocks with InnerBlocks MUST use InnerBlocks.Content in save
  - block.edit-save-components         # innerBlocks prop on save() is the data view
  - block.json-attributes-core         # attributes for runtime allowedBlocks prop sourcing
---

# RULE — `InnerBlocks` — nested IR composition system

## WHEN

You are building a block that should **contain other blocks** as
nested children — Columns, Group, Cover, Navigation, custom container
patterns. InnerBlocks is the API that turns a block into a composition
container: the editor renders a nested editing surface inside your
block, the children are stored as nested delimiter pairs in the
serialized IR, and the entire subtree round-trips through the parser
as a recursive `innerBlocks` array.

This is **not just a children slot.** InnerBlocks is the entry point
into Gutenberg's nested IR composition system, with intersecting
concerns:

- nested-IR composition (children mirror nested delimiters)
- insertion constraints (parent / ancestor / allowedBlocks at multiple
  layers)
- editing boundaries (template, templateLock)
- context propagation (providesContext / usesContext flows through
  the nesting)
- block-tree identity (each nested block has its own clientId / type)
- serialization nesting (save() must explicitly call
  `<InnerBlocks.Content />`)
- dynamic rendering coupling (`save: () => null` + InnerBlocks needs
  special handling)

## SHAPE

### Component form (most common)

```js
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

registerBlockType( 'my-plugin/wrapper', {
  edit: () => {
    const blockProps = useBlockProps();
    return (
      <div { ...blockProps }>
        <InnerBlocks />
      </div>
    );
  },
  save: () => {
    const blockProps = useBlockProps.save();
    return (
      <div { ...blockProps }>
        <InnerBlocks.Content />
      </div>
    );
  },
} );
```

### Hook form (more markup control)

```js
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

registerBlockType( 'my-plugin/wrapper', {
  edit: () => {
    const blockProps = useBlockProps();
    const innerBlocksProps = useInnerBlocksProps( blockProps );
    return <div { ...innerBlocksProps } />;
  },
  save: () => {
    const blockProps = useBlockProps.save();
    const innerBlocksProps = useInnerBlocksProps.save( blockProps );
    return <div { ...innerBlocksProps } />;
  },
} );
```

The `useBlockProps` return value can be passed INTO `useInnerBlocksProps`
to merge wrapper attrs and inner-blocks attrs onto a single element.

### Hook with destructured `children` (custom HTML alongside children)

```js
const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps );
return (
  <div { ...innerBlocksProps }>
    { children }
    <span>Custom HTML at same level as inner blocks</span>
  </div>
);
```

### Component / hook props

| Prop | Type | Effect |
|---|---|---|
| `allowedBlocks` | `string[]` | Limit which block types may be direct children. Runtime — can be derived from attributes. Complements `block.json` `allowedBlocks` field. |
| `orientation` | `"horizontal" \| "vertical"` (default `"vertical"`) | Affects block mover icons direction + drag/drop behavior. Does NOT actually lay out the children — that's CSS's job. |
| `template` | `array` | Prefill set when InnerBlocks has no existing content. Each entry: `[ blockName, attributes?, innerBlocks? ]`. |
| `templateLock` | `"all" \| "insert" \| false` | `all` = no changes; `insert` = no new inserts but reorder OK; `false` / unset = no lock. |
| `defaultBlock` | `{ name, attributes? }` | The block inserted by the appender by default (instead of opening the block picker). |
| `directInsert` | `boolean` | When `defaultBlock` is set: `true` = bypass picker dropdown, insert immediately; `false` / unset = open picker so user can choose a variation. |

## REQUIRES

- A block can contain **only ONE** `InnerBlocks` component (or one
  `useInnerBlocksProps` invocation). Multiple inner-blocks slots per
  block are not supported.
- For static blocks: `save()` MUST render `<InnerBlocks.Content />`
  (component form) or spread `useInnerBlocksProps.save( blockProps )`
  (hook form). Without it, the children do not serialize → editor
  shows them, but next load they're gone.
- For dynamic blocks (`render_callback` / `render.php`) that use
  InnerBlocks: `save()` MUST still output `<InnerBlocks.Content />`
  (NOT `() => null`). The serialized children become the `$content`
  variable in the render function.
- When using `useInnerBlocksProps`: `useBlockProps` MUST be called
  BEFORE `useInnerBlocksProps`. Source: *"It is important to note
  that `useBlockProps` hook must be called before `useInnerBlocksProps`,
  otherwise `useBlockProps` will return empty object."*
- The wrapper element receiving `useInnerBlocksProps` props MUST be a
  native DOM element (same constraint as `useBlockProps`).
- When the wrapper combines InnerBlocks with custom sibling HTML:
  destructure the hook's `children` from the returned object and
  render it alongside the custom markup inside a single wrapper
  element.

## INVARIANTS

- **Nested-IR composition.** The in-memory block-tree's `innerBlocks`
  array mirrors the lexical nesting of delimiters in serialized
  `post_content`. Parsing recurses on each nested delimiter pair to
  produce a tree of block objects. Serialization recurses in the
  opposite direction.
- **Children get rendered automatically by the hook / component.**
  `<InnerBlocks />` acts as a placeholder for "render the children
  here"; in the hook form, the `children` are produced by the hook
  and placed at the wrapper's content position by default. There is
  no separate "render children" call you must invoke.
- **Single-slot constraint:** one block = one InnerBlocks. To
  implement N independent child slots, design N separate parent
  blocks with `parent` constraints, OR use block patterns / templates
  to compose multiple wrappers inside a layout.
- **Two `allowedBlocks` layers coexist:**
  1. `block.json` `allowedBlocks` — schema-level (per
     `block.json-hierarchy-constraints`). Static, declared at
     registration time.
  2. `<InnerBlocks allowedBlocks={...} />` runtime prop — can be
     dynamic (e.g., derived from a block attribute). Can vary per
     block instance.

  ⚠ **Interaction precedence between the two is not explicitly
  documented in source.** The how-to guide describes the runtime
  prop as "in addition to" the block.json field, suggesting both
  apply. Verify behavior empirically before relying on either to
  override the other.
- **`template` only prefills empty InnerBlocks.** Once children
  exist (whether user-inserted or template-created and edited), the
  template is not re-applied. To enforce a template structure on
  existing content, combine with `templateLock: "all"`.
- **`templateLock` modes:**
  - `"all"` — children cannot be added, removed, moved, or replaced.
    Editing remaining attributes within children is still possible.
  - `"insert"` — no new children may be added, but existing children
    can be reordered or removed.
  - `false` / unset — no lock; default editor behavior.
- **`orientation` is a UI signal, NOT layout.** It changes the
  direction of mover icons and drag/drop affordances. Actual visual
  layout (flex / grid / horizontal stacking) is the author's CSS
  responsibility on the wrapper.
- **`defaultBlock` + `directInsert` controls appender UX.**
  Without `defaultBlock`: appender opens the block picker.
  With `defaultBlock` + `directInsert: true`: appender inserts the
  default block immediately, no picker.
  With `defaultBlock` + `directInsert: false` or unset: appender
  still opens picker (lets user choose a registered variation of
  that default block).
  When `allowedBlocks` is a single block type with no variations,
  `directInsert` is **redundant** — appender already inserts directly.
- **Context propagation flows through InnerBlocks.** A block
  declaring `providesContext` makes those values visible to ANY
  block declaring `usesContext` for the same key, anywhere in the
  InnerBlocks subtree (per `block.json-context`). InnerBlocks is
  the canonical tree mechanism along which context propagates.
- **Hierarchy constraint cross-mechanism:** declaring `parent` /
  `ancestor` / `allowedBlocks` in **child blocks'** `block.json`
  controls inserter visibility per
  `block.json-hierarchy-constraints`. Combined with this rule's
  parent-side `allowedBlocks` runtime prop, you can scope insertion
  from BOTH directions.
- **Dynamic-block coupling:** for dynamic blocks with InnerBlocks,
  the rendered child markup arrives in `$content` of the
  `render_callback` / `render.php` (per
  `block.dynamic-rendering`). The render function typically wraps
  `$content` directly:
  ```php
  echo sprintf( '<div %s>%s</div>',
    get_block_wrapper_attributes(), $content );
  ```
- **Each nested block has its own `clientId`** in the editor (per
  `block.markup-representation`). The parent's clientId is unrelated
  to the children's clientIds. Selection, drag, and editing target
  individual block instances.
- **Save-side `innerBlocks` prop** (covered in
  `block.edit-save-components`) is rarely needed; the standard
  pattern is to let `<InnerBlocks.Content />` serialize the children
  automatically. Use the `innerBlocks` prop only for unusual cases
  (e.g., conditionally adjusting wrapper className based on child
  count).
- ⚠ **Minimum WP version unknown** for the InnerBlocks API as a
  whole. It has been part of the block editor since early WP 5.x.
  Specific props (`defaultBlock`, `directInsert`, etc.) likely
  arrived in different versions. Frontmatter `wp_min` is
  `"verification-needed"`.

## ANTIPATTERNS

- ❌ Calling `useInnerBlocksProps` BEFORE `useBlockProps`. Source
  warning: `useBlockProps` returns an empty object. Block selection,
  identity, and supports cascade all break silently.
- ❌ Using more than one `InnerBlocks` / `useInnerBlocksProps` per
  block. Not supported. To get multiple child slots, design separate
  child-container blocks with `parent` constraints.
- ❌ `save: () => null` for a block that uses InnerBlocks. The
  children are not serialized → next editor load shows the parent
  empty → users lose their content. For dynamic blocks WITH
  InnerBlocks, save must use `<InnerBlocks.Content />`.
- ❌ Forgetting `<InnerBlocks.Content />` (or the hook save
  companion) in static-block save(). Symptom: children appear in
  the editor while editing, then vanish on save.
- ❌ Treating `template` as a permanent structure enforcement.
  Template only prefills empty InnerBlocks; once children exist
  (even briefly), template stops applying. Pair with `templateLock`
  for enforcement.
- ❌ Using `templateLock: "all"` on a block whose children users
  legitimately need to edit (e.g., a hero with editable
  heading + paragraph children). Reorder / replace get blocked
  unnecessarily.
- ❌ Setting `orientation: "horizontal"` and expecting the children
  to lay out horizontally. The prop is a UI hint for movers /
  drag-drop only. Add CSS (flex / grid) on the wrapper for actual
  layout.
- ❌ Mixing `block.json` `allowedBlocks` with the runtime
  `<InnerBlocks allowedBlocks={[...]} />` prop without testing
  precedence. Behavior under contradiction is not documented;
  pick one source of truth.
- ❌ Heavy `template` (many blocks deeply nested) for a frequently-
  inserted block. Performance hit on every empty-InnerBlocks
  initialization.
- ❌ Passing the wrapper `ref` AFTER spreading
  `{...innerBlocksProps}`. Pass `ref` INTO the hook the same way
  as `useBlockProps`.
- ❌ Hardcoding `allowedBlocks` strings. Block names should match
  registered block `name` values exactly (case-sensitive,
  vendor/slug format). Typos silently allow nothing.

## RELATED

- `block.json-hierarchy-constraints` — `parent` / `ancestor` /
  `allowedBlocks` in **child** blocks' `block.json` complement the
  parent-side `allowedBlocks` runtime prop here. Same insertion-
  constraint problem viewed from the other end of the tree.
- `block.wrapper-attributes` — `useBlockProps` MUST be called
  before `useInnerBlocksProps`. The two hooks are designed to
  combine; passing the first's output into the second merges wrapper
  + InnerBlocks attrs.
- `block.markup-representation` — nested delimiters in serialized
  IR mirror the in-memory tree's `innerBlocks` array. Parsing /
  serialization recurse on each level.
- `block.json-context` — context propagation flows DOWN through
  InnerBlocks. Topology defined by hierarchy + InnerBlocks;
  propagation defined by providesContext / usesContext.
- `block.dynamic-rendering` — `render_callback` receives nested
  children's rendered HTML as `$content`. Save MUST use
  `<InnerBlocks.Content />` for InnerBlocks to participate in
  dynamic rendering.
- `block.edit-save-components` — `innerBlocks` prop on save() is
  rarely-used data view; standard pattern uses
  `<InnerBlocks.Content />` instead.
- `block.json-attributes-core` — runtime `allowedBlocks` prop can be
  sourced from a block attribute, making the constraint
  per-instance dynamic.
