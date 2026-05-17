---
rule_id: block.edit-save-components
domain: block-authoring
topic: edit-save
field_cluster: contract
wp_min: "verification-needed"
wp_recommended: "6.9"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/
    section: "Edit and Save — function contracts, props, validation"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-wrapper/
    section: "The block — Editor markup / Save markup / Dynamic render markup"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-in-the-editor/
    section: "Block in the Editor — Edit React component props"
    captured: 2026-05-09
related:
  - block.json-attributes-core           # attributes prop = the schema declared there
  - block.json-context                   # context prop (edit only, NOT save)
  - block.wrapper-attributes             # useBlockProps / useBlockProps.save / get_block_wrapper_attributes
  - block.markup-representation          # save() output IS the canonical IR (when not dynamic)
  - block.deprecation                    # validation failure → deprecation flow
  - block.dynamic-rendering              # save: () => null cases
  - block.inner-blocks                   # innerBlocks prop on save() / InnerBlocks component
---

# RULE — `edit` / `save` — block render contracts

## WHEN

Registering a block on the client (`registerBlockType`). You need to
declare:

- **`edit`** — the React component the editor renders for this block.
  Receives current attributes + interaction handlers. May mount custom
  inspector controls, toolbars, and inner blocks.
- **`save`** — the function that produces the markup serialized into
  the post (post_content). For **static blocks**, this markup IS the
  front-end output. For **dynamic blocks**, return `null` — the
  front-end output is generated server-side from `render_callback` /
  `render.php`.

`edit` and `save` are the bridge between editor state and the
serialized block IR (HTML + delimiters). They are not regular React
components — both are subject to specific contracts that the parser /
validator depends on.

## SHAPE

### `edit` signature

```js
edit( props ): ReactElement
```

`props` object contains (most common):

| Prop | Type | Purpose |
|---|---|---|
| `attributes` | `object` | Current values of all declared attributes. |
| `setAttributes` | `function` | Update attributes. Accepts partial object OR updater function (WP 6.9+). |
| `isSelected` | `boolean` | True when this block instance is selected in the editor. |
| `context` | `object` | Block context values declared in `usesContext`. Shape: `{ "key": value }`. |
| `clientId` | `string` | Editor-side block instance ID (NOT persisted). |
| `className` | `string` | Auto-generated wrapper class. Usually consumed via `useBlockProps()` instead. |

Minimal example:

```js
import { useBlockProps } from '@wordpress/block-editor';

const Edit = ( { attributes, setAttributes } ) => {
  const blockProps = useBlockProps();
  return (
    <div { ...blockProps }>
      { attributes.content }
    </div>
  );
};
```

### `save` signature

```js
save( props ): ReactElement | null
```

`props` object contains:

| Prop | Type | Purpose |
|---|---|---|
| `attributes` | `object` | The serialized values to render. |
| `innerBlocks` | `array` | Object representations of nested blocks. Rare — most save() implementations ignore this. |

Two canonical forms:

```js
// Static block — markup IS the front-end output
save: ( { attributes } ) => {
  const blockProps = useBlockProps.save();
  return <div { ...blockProps }>{ attributes.content }</div>;
};
```

```js
// Dynamic block — front-end output deferred to server
save: () => null;
```

### `setAttributes` updater form (WP 6.9+)

```js
const toggle = () =>
  setAttributes( ( current ) => ( { active: ! current.active } ) );
```

Like React's `setState` updater pattern. Pure function: `(currentAttrs)
=> newAttrs`.

## REQUIRES

- The wrapper element returned by `edit` / `save` MUST be a standard
  DOM element (`<div>`, `<table>`, etc.) OR a React component that
  forwards extra props to a native DOM element. **NOT** a
  `<Fragment>`, **NOT** a `<ServerSideRender>` (these don't accept the
  wrapper props the editor injects).
- `edit` MUST spread `useBlockProps()` onto the wrapper. Without it,
  the editor cannot identify, select, drag, or auto-class the block.
- `save` (when not `null`) MUST spread `useBlockProps.save()` onto the
  wrapper. Without it, the saved markup omits the auto-generated
  block class (`wp-block-{namespace}-{slug}`) and supports-API
  classes/inline styles.
- `save` MUST be **pure and stateless**. No `useState`, no `useEffect`,
  no `useSelect`, no global access. Output depends only on `attributes`
  (and optionally `innerBlocks`).
- `attributes` updates in `edit` go through `setAttributes` only.
  Direct mutation (`attributes.title = 'x'`) bypasses React state and
  the persistence layer.

## INVARIANTS

- Three markup destinations for any block:
  1. **Editor markup** — what `edit` renders inside the editor canvas.
  2. **Save markup** — what `save` returns; serialized into
     `post_content` between block delimiters.
  3. **Dynamic render markup** — what `render_callback` / `render.php`
     produces server-side. **Overrides** save markup on the front end
     when present.
- `save: () => null` is a deliberate declaration: **"the front-end
  HTML is not canonical — generate it server-side every render."**
  Dynamic blocks set this. Without server-side render, the front-end
  shows nothing.
- For static blocks (no `render_callback` / `render`), the save
  markup IS the canonical front-end representation. The editor's
  validator regenerates this markup on every load and compares to
  the stored markup; mismatch → block flagged invalid (see Validation
  Cycle below).
- **Validation cycle:** On editor load → for each block:
  1. Parse stored `post_content` between delimiters; extract
     attributes per the schema.
  2. Re-invoke `save()` with the parsed attributes.
  3. Compare the regenerated markup to what's stored.
  4. If identical → block is valid; render `edit` UI normally.
  5. If different → block is **invalid**; user sees recovery dialog
     (Attempt Block Recovery / Convert to HTML / Convert to Classic).
  This cycle is why `save` MUST be pure: a non-deterministic `save`
  produces "different markup" on every reload, marking valid blocks
  as invalid.
- `setAttributes` accepts either a partial object (`{ key: value }`)
  OR an updater function (WP 6.9+: `(current) => newPartial`). The
  updater form is preferred when the new value depends on the
  current value, especially with arrays/objects.
- Arrays and objects must be **replaced, not mutated** when stored in
  attributes. Mutating-then-passing-the-same-reference can be missed
  by React change detection and Gutenberg's Redux-style state model.
- The `context` prop (declared via `usesContext`) is delivered to
  `edit` only. **`save` does NOT receive `context`** — context-derived
  values cannot be statically serialized; use dynamic rendering for
  that case.
- The `innerBlocks` prop on `save` is "typically used for internal
  operations" per source; most blocks don't reference it. The
  authoritative way to render inner blocks is the `<InnerBlocks.Content />`
  component (companion to the `<InnerBlocks />` editor component).
- A string returned from `save` is **escaped** by default. To return
  raw HTML, use `wp.element.RawHTML` (discouraged — XSS surface).
  Prefer a WordPress Element / JSX hierarchy.
- `useBlockProps()` and `useBlockProps.save()` are the **convergence
  point** for: auto-generated classes, supports-API output (color /
  spacing / typography classes & inline styles), accessibility
  attributes, block selection state. Skipping them disconnects the
  block from the entire wrapper-attribute system.
- ⚠ **Minimum WP version unknown** for the base `edit` / `save`
  signatures (original Block API). The `setAttributes` updater
  function form is **WP 6.9+** (per source: "Since WordPress 6.9").
  Frontmatter `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Using `useState` / `useEffect` / `useSelect` in `save`. Output
  becomes non-deterministic; validation marks the block invalid on
  next load.
- ❌ Returning markup from `save` for a block that has a
  `render_callback`. The render_callback overrides save on the front
  end, making the save markup pointless work AND a stale-cache
  liability if `render_callback` output diverges later.
- ❌ Writing `save: () => null` for a block that does NOT have a
  server-side render path. The front end will show no markup — the
  block becomes invisible on save.
- ❌ Mutating `attributes` directly in `edit`
  (`attributes.list.push(item); setAttributes({ list: attributes.list })`).
  Use a new array / object reference:
  `setAttributes({ list: [ ...attributes.list, item ] })`.
- ❌ Using `<Fragment>` or `<ServerSideRender>` as the block wrapper.
  Editor wrapper props can't attach; selection / drag / classes break.
- ❌ Returning a string with raw HTML from `save`. The string gets
  escaped — `<strong>` shows as text, not bold. Use JSX or
  `wp.element.RawHTML` (with caution).
- ❌ Reading from external state in `save` (data store, window
  globals, fetch result). External state changes after save = block
  invalidation on next load.
- ❌ Skipping `useBlockProps()` because "I don't need extra classes".
  The hook injects far more than classes — selection state, ID,
  ARIA, supports output. Always spread it on the wrapper.
- ❌ Hardcoding the block name as a string in JS when registering
  client-side. Import from `block.json` (`import metadata from
  './block.json'; registerBlockType( metadata.name, ... )`) to
  prevent drift between PHP and JS.
- ❌ Trying to access `context` in `save` — not provided. Move
  context-dependent rendering to a `render_callback` (dynamic block).
- ❌ Heavy computation in `save`. It runs on every editor load (for
  validation regeneration) AND every front-end render (for static
  blocks). Move expensive logic to a memoized component used in
  `edit`, or to server-side `render_callback`.

## RELATED

- `block.json-attributes-core` — `attributes` prop on edit/save IS
  the schema declared in `block.json` `attributes` field. Read first.
- `block.json-context` — `context` prop on edit consumes
  `usesContext` declarations; `save` does not receive context.
- `block.wrapper-attributes` (planned) — full mechanics of
  `useBlockProps()`, `useBlockProps.save()`,
  `get_block_wrapper_attributes()`. The convergence point for
  supports / classes / inline styles / accessibility.
- `block.markup-representation` (planned) — what `save()` actually
  outputs into the delimiter; the IR format and parser semantics
  that the validation cycle depends on.
- `block.deprecation` (planned) — when `save()` changes shape across
  plugin versions, deprecation entries provide migration paths so
  existing posts don't invalidate.
- `block.dynamic-rendering` (planned) — `save: () => null` is the
  declarative half; `render_callback` / `render.php` is the dynamic
  half.
- `block.inner-blocks` (planned) — the `<InnerBlocks />` /
  `<InnerBlocks.Content />` component pair handles nested block
  composition; the `innerBlocks` prop on save is the data view.
