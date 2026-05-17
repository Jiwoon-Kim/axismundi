---
rule_id: block.wrapper-attributes
domain: block-authoring
topic: wrapper-attributes
field_cluster: contract
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
    section: "@wordpress/block-editor — useBlockProps hook"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-wrapper/
    section: "Editor markup / Save markup / Dynamic render markup"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/get_block_wrapper_attributes/
    section: "get_block_wrapper_attributes() PHP function reference"
related:
  - block.edit-save-components       # the contract in which this hook is invoked
  - block.dynamic-rendering          # PHP wrapper attrs are the dynamic-render counterpart
  - block.json-supports-field        # supports flags inject classes/styles HERE
  - block.markup-representation      # what gets serialized into the HTML body
  - block.json-attributes-core       # certain attributes (anchor id, class) flow through here
  - block.inner-blocks               # useInnerBlocksProps is the InnerBlocks-aware companion
  - style-engine.generated-selectors          # wrapper-local synthesized namespaces; wrapper IS the carrier
  - style-engine.css-variable-emission        # variables traverse the wrapper boundary as deferred realization carriers
  - style-engine.preset-materialization       # wrapper is the carrier surface of materialization stage 5 (selector binding)
  - style-engine.cascade-aggregation          # wrapper-bound inline styles = highest-tier authority escalation
  - theme-config.json-settings-residual-governance  # useRootPaddingAwareAlignments materializes through wrapper attachment
---

# RULE — Wrapper attributes — `useBlockProps` / `get_block_wrapper_attributes`

## WHEN

You are writing `edit()`, `save()`, or a server-side render
(`render_callback` / `render.php`) for a block, and you need to apply
the auto-generated identifiers, accessibility attributes, supports-API
classes and styles, and editor-state metadata to the block's outermost
DOM element.

This is **not** a className helper. It is the
**editor/runtime synchronization contract**: the single point where
the block editor's auto-generated state (selection, identity,
supports cascade, style engine output, layout class, ARIA hints) is
attached to your block's wrapper. The block wrapper IS the
**runtime surface boundary** — the only DOM element WordPress uses
to identify, select, drag, classify, style, and serialize the block.

## SHAPE

### Three APIs, one contract

| API | Used in | Returns |
|---|---|---|
| `useBlockProps( props? )` | `edit()` (JS) | Object of props to spread on outer element |
| `useBlockProps.save( props? )` | `save()` (JS) | Object of props for the SAVED markup wrapper |
| `get_block_wrapper_attributes( $args? )` | `render_callback` / `render.php` (PHP) | String to echo inside the opening tag |

### Editor (`useBlockProps`)

```js
import { useBlockProps } from '@wordpress/block-editor';

function Edit() {
  const blockProps = useBlockProps( {
    className: 'my-custom-class',
    style: { color: '#222' },
  } );
  return <div { ...blockProps }>Hello</div>;
}
```

Authored props are merged with auto-generated props.

### Save (`useBlockProps.save`)

```js
function save() {
  const blockProps = useBlockProps.save( {
    className: 'my-custom-class',
  } );
  return <div { ...blockProps }>Hello</div>;
}
```

The save companion emits only the props relevant to persisted markup
(NOT editor-only metadata like `is-selected` or `data-block`).

### Dynamic render (`get_block_wrapper_attributes`)

```php
function render_block() {
  return sprintf(
    '<div %1$s>%2$s</div>',
    get_block_wrapper_attributes(),
    'Hello'
  );
}
```

Returns the attribute string to splice into the opening tag.
Optional `$args` array accepts overrides like
`array( 'class' => 'extra-class' )`.

## REQUIRES

- The wrapper element MUST be a standard DOM element (`<div>`,
  `<section>`, `<table>`, ...) OR a React component that forwards
  extra props to a native DOM element. **NOT** `<Fragment>`,
  **NOT** `<ServerSideRender>`.
- The hook MUST be called on the **outermost** element of the block.
  Spreading on a non-outermost element disconnects the block from
  the editor's selection / drag / class systems.
- For block API version 2 or higher, calling `useBlockProps` is
  **required**. Source: *"Use of this hook on the outermost element
  of a block is required if using API >= v2."*
- If a `ref` is needed on the wrapper, pass it INTO `useBlockProps`
  via the props arg — the hook returns it back through the props it
  outputs. Setting a `ref` directly on the element AFTER spreading
  will override the hook's ref handling.
- For `useBlockProps.save()` and `get_block_wrapper_attributes()`,
  the matching invocation depends on the rendering mode:
  - Static block (no `render_callback` / `render`): use
    `useBlockProps.save()` in `save()`.
  - Dynamic block: use `get_block_wrapper_attributes()` in PHP;
    `save: () => null` skips the static save path.

## INVARIANTS

- **Editor-mode (`useBlockProps`) injections include** (per source
  example):
  - Auto-generated `id` (e.g., `id="block-{clientId}"`)
  - `tabindex`, `role` (e.g., `role="document"`), `aria-label`
  - `data-block` (the editor's block instance ID)
  - `data-type` (the registered block name)
  - `data-title` (the registered block title)
  - The auto-class `wp-block`
  - The block-specific class
    `wp-block-{namespace-and-slug-as-kebab-case}`
  - Selection-state classes (`is-selected`,
    `block-editor-block-list__block`, etc.)
  - Authored `className` / `style` from the props arg, MERGED with
    auto-generated values.
- **Save-mode (`useBlockProps.save`) injections include** (much
  smaller set — only what should persist):
  - The block-specific class
    `wp-block-{namespace-and-slug}` (auto-generated)
  - Supports-API generated classes (`has-{slug}-color`,
    `has-background`, alignment classes, etc.)
  - Supports-API inline styles (when custom-color / custom-spacing
    is in use)
  - The `id` attribute generated by `supports.anchor` if the user
    set one
  - Authored `className` / `style` merged into the above
  - **NOT** `id="block-{clientId}"`, **NOT** `data-block`, **NOT**
    selection-state classes — those are editor-only metadata.
- **Dynamic-render (`get_block_wrapper_attributes`) injections
  match `useBlockProps.save` semantics**, since both target the
  serialized / rendered front-end form. Differences are in the
  return type (string vs Object) and how you splice into markup
  (`echo` inside opening tag vs JSX spread).
- The wrapper is the **convergence point** for everything generated
  by the supports cascade (color / typography / spacing / layout /
  border / shadow / alignment / anchor / className / customClassName).
  Skipping `useBlockProps` disconnects ALL of these from the rendered
  DOM — controls work in the inspector, but classes/styles never
  reach the markup.
- Authored props (`className`, `style`, etc.) and auto-generated
  props are **merged** — `className` strings are concatenated;
  `style` objects are shallow-merged. Order in the merged result is
  not authored-first or auto-first; rely on CSS specificity, not
  property order, to resolve conflicts.
- For `useInnerBlocksProps()`: it is the InnerBlocks-aware companion
  to `useBlockProps`. Use it on a wrapper that contains
  `<InnerBlocks />` to mark the element as both a block wrapper AND
  an inner-blocks wrapper. See `block.inner-blocks`.
- The `id="block-{clientId}"` injected by editor-mode is
  **session-scoped**. It is not the `supports.anchor` ID; it
  changes on each editor reload. Don't rely on it for persistent
  link targets.
- Editor and save markup **diverge** intentionally. The editor needs
  selection / drag / ARIA infrastructure that has no place in the
  saved / front-end DOM. The two hooks (`useBlockProps` vs
  `useBlockProps.save`) reflect this divergence.
- ⚠ **Minimum WP version unknown.** `useBlockProps` arrived with
  Block API v2; saying "Use of this hook ... is required if using
  API >= v2" implies it has been available since v2. Frontmatter
  `wp_min` is `"verification-needed"` because no `Since:` version is
  documented for the API itself.

## ANTIPATTERNS

- ❌ Skipping `useBlockProps()` because "I don't need extra classes".
  The hook injects far more than classes — selection state, IDs,
  ARIA, supports cascade output. Without it, the editor cannot
  select, drag, or visually classify the block.
- ❌ Spreading `useBlockProps()` on an element that is NOT the
  outermost element of the block. Selection / drag handles attach
  to whichever element gets the hook; placing it on a child breaks
  the editor's block-level interactions.
- ❌ Using `<Fragment>` or `<ServerSideRender>` as the wrapper.
  These don't accept the wrapper props the hook returns. Selection
  / drag / classes break.
- ❌ Manually adding the same className that a supports flag would
  inject (e.g., hard-coding `has-background` when
  `supports.color.background = true` is enabled). Duplicates and
  drift on schema change.
- ❌ Setting `ref={...}` on the element AFTER spreading
  `{...blockProps}`. The spread includes the hook's own ref;
  overriding it disconnects the editor's instance tracking. Pass
  the ref INTO the hook instead: `useBlockProps( { ref } )`.
- ❌ Using `useBlockProps()` in `save()` instead of
  `useBlockProps.save()`. The full editor-mode hook injects
  editor-only metadata (`is-selected`, `data-block`) into the
  serialized markup, polluting the saved post and triggering
  validation failures on next load.
- ❌ Using `useBlockProps.save()` for a block with `save: () =>
  null` (dynamic rendering). The save path doesn't run; use
  `get_block_wrapper_attributes()` in the PHP render instead.
- ❌ Treating the editor `id="block-{clientId}"` as a stable anchor
  for permalinks or styling. It changes per session. For stable
  anchors, declare `supports.anchor: true` and let the user set the
  ID via the editor UI.
- ❌ Adding `className: classnames(...)` to props inside `useBlockProps`
  thinking the merge will pick the right precedence. Both authored
  and auto-generated classes coexist; your CSS rules must handle
  whatever combination appears.

## RELATED

- `block.edit-save-components` — the contracts (`edit` / `save`)
  inside which these hooks are invoked. The hook calls have no
  effect outside this context.
- `block.dynamic-rendering` (planned) — the PHP rendering path
  where `get_block_wrapper_attributes` is the canonical mechanism.
- `block.json-supports-field` — supports flags are the primary
  source of auto-injected classes/styles flowing through this
  contract. Without `useBlockProps`, supports output never reaches
  the wrapper.
- `block.markup-representation` — what `useBlockProps.save()`
  produces is the persisted-markup wrapper that the validation
  cycle regenerates and compares against.
- `block.json-attributes-core` — `supports.anchor` value flows from
  user input through attributes, into `useBlockProps`-generated
  `id`. Other attribute-based values may also pre-process before
  reaching the wrapper.
- `block.inner-blocks` (planned) — `useInnerBlocksProps()` is the
  InnerBlocks-aware companion; use it instead of `useBlockProps`
  when the wrapper also contains nested blocks.
- `block.supports.color`, `block.supports.typography` (planned),
  etc. — per-flag rules that produce specific classes/styles
  flowing through this contract.

## RETROACTIVE REFRAMING (post-style-engine bounded context closure)

**Status note**: This section was added 2026-05-09 after the
style-engine bounded context closed (generated-selectors /
css-variable-emission / preset-materialization /
cascade-aggregation). It is **not a correction** of the chunk
above — the original content remains accurate. It is a
retrospective reinterpretation: bounded-context completion
revealed wrapper-attributes' deeper ontological role that was
not visible when this chunk was originally written.

**KB pattern note**: This is the first explicit
RETROACTIVE REFRAMING section in the KB. The pattern is:
when a downstream bounded context closes and reframes the role
of an upstream chunk, the upstream chunk gains a layered
re-reading — not a rewrite. Future reframings of other chunks
may use this pattern.

### Reframing — wrapper attributes as authority transport surface

The original chunk reads `useBlockProps` as the
"editor/runtime synchronization contract" attaching auto-
generated state to the wrapper. After the style-engine bounded
context closure, a deeper ontology is visible:

> Wrapper attributes are the **authority transport surface** of
> the entire Gutenberg style compiler.
> The wrapper is the **physical membrane** through which all
> compiled style authorities cross from the style-engine
> compiler into the browser cascade VM.

The wrapper is not just where classes / styles attach — it is
the **realization carrier multiplex** carrying multiple
authority types simultaneously:

| carrier | transported authority | source |
|---|---|---|
| `class="wp-block-{type}"` | block-type identity | block registration |
| `class="has-{slug}-color"` | preset reference (selector-binding form) | supports + materialization |
| `class="wp-container-{id}"` | runtime topology synthesis | generated-selectors |
| `class="wp-elements-{uuid}"` | element-projection synthesis | generated-selectors |
| `class="alignwide"` / `"alignfull"` | layout-topology authority | supports.align + layout engine |
| `class="is-style-{slug}"` | block-style variation identity | block.json styles |
| `style="color: var(--wp--preset--*)"` | per-instance escalated authority | cascade-aggregation tier 10 |
| `style="--wp--style--root--padding-*"` | scoped variable declaration | useRootPaddingAwareAlignments |
| `data-block="{clientId}"` | editor instance identity | editor runtime |
| `id="{anchor}"` | persistent anchor identity | supports.anchor |

This is **realization carrier multiplexing** — one DOM element
carrying simultaneous transports from multiple compiler outputs
to one browser cascade input.

### RETROACTIVE INVARIANTS

#### A. Wrapper attributes are authority carriers, not mere markup metadata

The original chunk framed wrapper-attributes as "convergence
point". Post-style-engine, that framing strengthens to:

> Wrapper surfaces physically transport runtime style
> authorities. Each class / style / attribute carries an
> authority claim with origin, scope, and cascade priority.

Reading wrapper attributes as "markup details" or "styling
hooks" misses that each attribute IS an authority encoding.

#### B. Wrapper surfaces form the compiler/runtime handshake membrane

The wrapper is the **handshake surface** between Gutenberg
(compiler) and browser (runtime VM):

```
style-engine compiler emits authorities
   ↓
wrapper carries authorities (this chunk's contract)
   ↓
browser cascade engine resolves authorities (out of scope)
```

Wrapper is simultaneously:
- **compiler output surface** — where style-engine emissions
  physically attach
- **browser input surface** — what the browser cascade engine
  reads to compute styles

This dual role makes wrapper attributes the **ABI boundary**
between Gutenberg's compiler and the browser's runtime — the
runtime ABI framing established in css-variable-emission
applies at the wrapper layer too.

#### C. Generated selectors specialize wrapper-scoped authority projection

Post-generated-selectors, the original chunk's mention of
auto-injected classes gains depth:

> `.wp-container-{id}` and `.wp-elements-{uuid}` are
> **wrapper-local synthesized namespaces**. They are not
> standalone selectors — they exist as classes ATTACHED TO
> WRAPPERS that carry the per-instance scoping authority into
> the cascade.

Generated-selectors documented WHAT these classes ARE
(runtime topology synthesis); this chunk documents WHERE they
LIVE (the wrapper). Without the wrapper carrier, the generated
classes would have no attachment point — generated-selectors
and wrapper-attributes are inseparable mechanisms.

#### D. Inline styles are wrapper-bound escalation authorities

Post-cascade-aggregation, inline `style="..."` on the wrapper
gains a precise role:

> Inline styles on the wrapper element are the
> **physical materialization** of cascade-aggregation's
> highest-tier authority escalation. They are not "quick
> overrides" or "performance shortcuts" — they encode the
> authority claim "this exact element, this exact value, no
> further negotiation."

The wrapper IS the physical membrane on which this escalation
is attached. Cascade-aggregation invariant 6 ("Inline styles =
authority escalation paths") is the same fact viewed from the
cascade side; wrapper-attributes invariant D is the same fact
viewed from the carrier side.

#### E. CSS variables traverse the wrapper boundary as deferred realization carriers

Post-css-variable-emission, the role of `--wp--*` variables
attached to wrappers is:

> Variables declared on wrappers (`style="--wp--style--root--padding-top: 1rem"`)
> are **deferred realization carriers**: they cross the
> wrapper boundary as still-unresolved values, to be resolved
> by browser variable substitution at use site.

This makes wrapper a participant in the runtime ABI:
- Variables emitted on `:root` cross the wrapper boundary as
  inheritance.
- Variables emitted on the wrapper itself cross as scoped
  declarations.
- Variables consumed in the wrapper's `style` attribute cross
  as deferred lookups.

Wrapper is the physical site of three different variable-
authority transactions.

#### F. Layout topology policies materialize through wrapper attachment

Post-residual-governance:
`useRootPaddingAwareAlignments` (single boolean in settings)
materializes through wrapper attachment:

```
settings.useRootPaddingAwareAlignments: true
   ↓
runtime layout topology policy activated
   ↓
--wp--style--root--padding-* variables emitted on :root
   ↓
wrapper class behavior switched (alignfull / constrained
calculations re-computed)
   ↓
wrapper carries the topology-aware classes + variable
consumption
   ↓
generated container selectors apply scoped rules
   ↓
final cascade
```

The settings declaration leaks runtime topology policy; the
wrapper is the **physical site** where that policy materializes.
This closes the chain residual-governance opened.

### Pipeline closure — block-authoring ↔ style-engine sealed

After this retroactive reframing, the chain closes:

```
block schema (block-authoring + theme-config)
   ↓
wrapper transport (wrapper-attributes — this chunk's carrier role)
   ↓
style engine synthesis (generated-selectors + variable-emission)
   ↓
materialization lifecycle (preset-materialization)
   ↓
cascade arbitration (cascade-aggregation)
   ↓
browser execution (out of scope — VM)
```

Block-authoring and style-engine bounded contexts are now
**physically sealed** — wrapper-attributes is the load-bearing
membrane connecting them. Without this carrier layer, the two
bounded contexts would be conceptually adjacent but operationally
disconnected; with it, they form one coherent compiler/runtime
stack.

### KB-level framing payoff

This retroactive reframing completes the KB-level
re-definition that cascade-aggregation initiated:

> Gutenberg is a **schema-driven runtime style graph compiler**
> with the WRAPPER ELEMENT as its physical compiler-runtime
> handshake surface.

Block-authoring established the SCHEMA. Style-engine established
the COMPILER. Wrapper-attributes (with this reframing) is the
ABI BOUNDARY between compiler output and browser runtime input.
