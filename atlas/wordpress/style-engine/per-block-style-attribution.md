---
rule_id: style-engine.per-block-style-attribution
domain: style-engine
topic: instance-realization
field_cluster: block-attribute-to-wrapper-css
wp_min: "5.9"
wp_recommended: "6.5+"
status: stable
language: js-and-php
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
    section: "useBlockProps — editor-side wrapper props hook"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/get_block_wrapper_attributes/
    section: "get_block_wrapper_attributes() — PHP-side wrapper attributes for dynamic blocks"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
    section: "Block supports — color/typography/spacing/etc. attribute participation"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/#preset-css-custom-properties
    section: "Preset CSS custom properties — has-{slug}-{type} class generation"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-style-engine/
    section: "@wordpress/style-engine — per-instance CSS synthesis for complex styles"
    captured: 2026-05-10
related:
  - style-engine.theme-json-source-layering         # the merge that defines the design vocabulary this chunk consumes
  - style-engine.preset-materialization             # the value-transformation pipeline this chunk's classes hook into
  - style-engine.css-variable-emission              # the custom property declarations this chunk's classes consume
  - style-engine.generated-selectors                # runtime synthesis for complex per-instance styles
  - block.edit-and-save-contracts                   # the edit/save contract that hosts useBlockProps
  - block.wrapper-attributes                        # the wrapper hub this chunk operates through
  - block.json-attributes-core                      # the attribute schema this chunk's mechanism reads
---

# RULE — per-block style attribution — block attributes becoming wrapper CSS

## WHEN

You need to reason about *how* a block instance's
style-bearing attributes (color, spacing, typography,
border, etc.) end up as actual CSS on the rendered
DOM element. Use this knowledge when:

- A block's `style.color.background` attribute is set
  but the rendering doesn't show the color — and you
  need to trace the attribute → class → CSS chain.
- Choosing between `useBlockProps()` (editor /
  static-block save) and `get_block_wrapper_attributes()`
  (dynamic-block PHP render) for a custom block.
- Reading core block code and following how a preset
  reference like `'primary'` becomes a
  `has-primary-background-color` class on the wrapper.
- Diagnosing class-generation differences between the
  editor preview and the rendered front-end output
  (almost always: missing `useBlockProps.save()` in
  `save`, or missing `get_block_wrapper_attributes()`
  in `render.php`).
- Implementing a custom block that consumes the
  theme.json-merged design vocabulary at the
  per-instance level.

This chunk does **not** cover:

- The 3-layer theme.json source merge that produces
  the design vocabulary — covered in
  `style-engine.theme-json-source-layering`.
  This chunk *consumes* that merged vocabulary at the
  block-instance level.
- The preset value transformation pipeline (preset
  reference → CSS variable → applied value) — covered
  in `style-engine.preset-materialization`.
- The `.wp-container-*` / `.wp-elements-*` runtime
  selector synthesis for complex per-instance styles
  — covered in `style-engine.generated-selectors`.
- The full `useBlockProps()` API surface — covered in
  `block.wrapper-attributes`. This chunk focuses on
  the *style attribution* slice.
- The block.json `attributes` schema in general —
  covered in `block.json-attributes-core`. This chunk
  focuses on the style-bearing subset.

The principle this chunk operates under: **A block
instance's style attributes do not directly appear in
CSS. They pass through a deterministic
attribute → wrapper-class / wrapper-style → matched-CSS-rule
pipeline. The pipeline is implemented in three parallel
runtimes (editor JS, save serialization, PHP render);
all three produce equivalent output for the same input.
The mechanism is *realization* — making one declared
intent visible — not *bridging* — preserving runtime
authority across contexts.**

## SHAPE

### A. The 4-stage attribute → render pipeline

For a block instance with `backgroundColor: 'primary'`:

```
Stage 1 — DECLARATION (block attribute)
   Block instance carries:
     attributes.backgroundColor = 'primary'
   Stored in serialized form (post_content delimiter or static HTML).
              │
              ▼
Stage 2 — REALIZATION (wrapper-prop generation)
   At render time, useBlockProps() / useBlockProps.save() /
   get_block_wrapper_attributes() reads attributes,
   computes wrapper props:
     className: 'has-background has-primary-background-color'
     style: ''   (no inline style needed for preset reference)
              │
              ▼
Stage 3 — EMISSION (DOM)
   Wrapper element renders with the computed props:
     <div class="has-background has-primary-background-color">…</div>
              │
              ▼
Stage 4 — APPLICATION (CSS matching)
   Theme.json-emitted CSS rules match the class:
     .has-primary-background-color {
         background-color: var(--wp--preset--color--primary);
     }
   The CSS variable resolves (per the source-layering merge)
   to the actual color value on this site.
```

Four distinct stages, each a precondition for the
next:

- Without Stage 1 (no attribute set), Stage 2 has
  nothing to realize.
- Without Stage 2 (skipped wrapper-prop generation),
  Stage 3 emits a wrapper with no class.
- Without Stage 3 (no DOM render), Stage 4's CSS
  rules have nothing to match.
- Without Stage 4 (no theme.json palette emission),
  Stage 3's class doesn't resolve to any visible
  color.

The chunk's central observation: *the attribute and
the visible color are separated by four
transformations*. None of them is bridging; each is
a deterministic step in a one-direction pipeline.

### B. Class generation rules

The wrapper-prop generation logic uses a small set of
naming conventions to translate attribute values into
class names. Three families:

**Preset references → `has-{slug}-{type}` classes.**

When an attribute holds a preset slug (rather than a
literal value), the wrapper gets a class encoding
the preset:

| Attribute (value)                        | Generated class(es)                                   |
| ---------------------------------------- | ----------------------------------------------------- |
| `backgroundColor: 'primary'`             | `has-background has-primary-background-color`         |
| `textColor: 'accent'`                    | `has-text-color has-accent-color`                     |
| `gradient: 'sunset'`                     | `has-background has-sunset-gradient-background`       |
| `fontSize: 'large'`                      | `has-large-font-size`                                 |
| `fontFamily: 'serif'`                    | `has-serif-font-family`                               |

The `has-{slug}-{type}` class is consumed by the
theme.json-emitted CSS rule for that preset (see the
table in Stage 4 above). The slug is the continuity
surface from theme.json declaration through to CSS
selector — the same string `'primary'` appears in:

- The theme.json palette entry `{slug: 'primary', …}`.
- The block instance's attribute value `'primary'`.
- The wrapper class `has-primary-background-color`.
- The CSS custom property `--wp--preset--color--primary`.

**Layout variants → `is-{name}` classes.**

For block-supports-driven layout, each declared
variant gets its own class:

| Attribute / support                      | Generated class                                       |
| ---------------------------------------- | ----------------------------------------------------- |
| `align: 'wide'`                          | `alignwide`                                           |
| `align: 'full'`                          | `alignfull`                                           |
| `layout: { type: 'flex' }`               | `is-layout-flex`                                      |
| `layout: { type: 'flow' }`               | `is-layout-flow`                                      |

These classes are consumed by selectors in the
generated stylesheet's layout section (covered more
fully in `style-engine.generated-selectors`).

**Custom (non-preset) values → no class, inline style.**

When an attribute holds a literal value rather than
a preset reference, the wrapper does *not* get a
preset-named class. Instead, the value goes into
the wrapper's `style` attribute as a direct CSS
property:

| Attribute (value)                        | Wrapper output                                        |
| ---------------------------------------- | ----------------------------------------------------- |
| `style.color.text: '#a1b2c3'`            | `style="color: #a1b2c3"`                              |
| `style.spacing.padding: '24px'`          | `style="padding: 24px"`                               |
| `style.typography.lineHeight: '1.6'`     | `style="line-height: 1.6"`                            |

Two reasons this matters:

- **CSS specificity differs.** Inline styles win
  over class-based rules; preset-via-class
  applications are interruptable by user CSS, while
  custom-value-via-inline-style is much harder to
  override.
- **Theme designers should prefer presets.**
  Documents authored against presets stay
  consistent if the theme later changes the palette;
  documents with literal values are frozen at
  authoring time.

### C. Combined preset + custom attribute on the same property

A common case is a block with one property using a
preset and another using a custom value:

```js
attributes: {
    backgroundColor: 'primary',           // preset
    style: {
        color: { text: '#102030' },       // custom literal
        spacing: { padding: '16px' },     // custom literal
    }
}
```

The wrapper combines both treatments:

```html
<div
    class="has-background has-primary-background-color"
    style="color: #102030; padding: 16px"
>…</div>
```

Each property is realized independently. The class
chain handles preset-referencing properties; the
inline style chain handles literal-valued
properties. They coexist without coordination.

### D. Three parallel realization paths

The wrapper-prop computation runs in three contexts,
each with its own implementation:

| Context              | Function                          | Runtime         |
| -------------------- | --------------------------------- | --------------- |
| Editor preview       | `useBlockProps()`                 | Browser JS (editor) |
| Static-block save    | `useBlockProps.save()`            | Browser JS (during save serialization) |
| Dynamic-block render | `get_block_wrapper_attributes()`  | PHP (front-end render) |

All three produce **the same shape of output for the
same input attributes**. The class-generation rules
(Section B) and inline-style rules (Section B) are
identical across implementations — they have to be,
because the editor preview, the saved HTML, and the
front-end render need to look the same on a given
block instance.

Usage examples:

```js
// In edit():
import { useBlockProps } from '@wordpress/block-editor';

const Edit = ( { attributes, setAttributes } ) => {
    const blockProps = useBlockProps();
    return <div { ...blockProps }>…</div>;
};
```

```js
// In save():
import { useBlockProps } from '@wordpress/block-editor';

const Save = ( { attributes } ) => {
    const blockProps = useBlockProps.save();
    return <div { ...blockProps }>…</div>;
};
```

```php
// In render.php:
<div <?php echo get_block_wrapper_attributes(); ?>>…</div>
```

Three implementations, one logical contract. The
shape of the contract:

```
input:  ( block attributes, supports declarations,
          theme.json-merged settings vocabulary )
output: { className, style, …other wrapper attributes }
```

The crucial structural point: **the three runtimes
do not communicate.** Each computes its output
independently from the same input. There is no
runtime that "owns" the wrapper and shares it with
the others; there is one *contract* that all three
implement separately. Editor JS doesn't tell PHP
what classes to use; PHP doesn't tell save what
classes to use; the save's serialized HTML doesn't
"hand off" anything to PHP. They are **parallel
realizations of one specification**, not bridged
runtimes.

### E. Style-engine handoff for complex cases

For attributes whose CSS realization needs more than
class-based or inline-style application — most often
when a per-instance value combines into something
the existing CSS class system can't express
(complex border configurations, nested padding
shorthand, layout-aware spacing) — the style-engine
emits dedicated CSS rules using the
`.wp-elements-{uuid}` selector pattern (documented
in `style-engine.generated-selectors`).

The handoff flow:

```
Stage 2 (wrapper-prop generation) detects that an
attribute requires per-instance CSS that classes
can't express.
              │
              ▼
Style-engine generates a unique selector
(.wp-elements-{uuid}) and writes a CSS rule for it.
              │
              ▼
Stage 2 adds the unique class to the wrapper props.
              │
              ▼
Stage 4 application: the generated rule matches.
```

Most simple attributes (preset references, single
literal values) use class-based application and
never reach the style-engine's per-instance CSS
synthesis. The synthesis path exists for the
complex cases that wouldn't be expressible
otherwise.

For this chunk's purposes, the takeaway is: *most
attributes flow through the simple class /
inline-style path documented above; complex cases
hand off to per-instance CSS synthesis*. Both paths
end at the wrapper element with the correct visual
result.

## WHY

### Why class-based for presets and inline-style for literals

Presets are *site-wide vocabulary*. A block using
the `'primary'` preset should automatically reflect
the site's current `'primary'` color, even if the
theme later changes its palette. Class-based
application achieves this: the class
`has-primary-background-color` consumes a CSS
custom property that the theme.json merge produces;
changing the merge changes the property; every
block using the class updates.

Literal values are *one-off intent*. A block using
`#a1b2c3` as a literal background means "this exact
color, regardless of palette." Inline style
achieves this: the value is on the element,
unaffected by any palette changes.

The two mechanisms separate "intent referencing
shared design language" from "intent specifying a
specific value." Each gets the realization shape
that matches its meaning.

### Why three parallel implementations rather than one

The editor renders blocks in a React runtime; the
serialization step produces static HTML; the
front-end render for dynamic blocks runs in PHP.
Each context has its own performance characteristics,
constraints, and interaction with the surrounding
code:

- The editor needs hooks (`useBlockProps()` returns
  a memoized object; React re-renders when
  attributes change).
- The save serialization needs to produce HTML that
  doesn't carry editor-only data.
- The PHP render needs to assemble HTML attributes
  as a string for inclusion in the rendered output.

A single shared implementation would require either
running JS on the server (heavy infrastructure
cost) or the PHP render somehow consuming
JavaScript-shaped logic (cross-language complexity).
The three-implementations-of-one-contract design
keeps each runtime native while accepting the cost
of maintaining three implementations in sync.

### Why the class-name conventions are stable

The `has-{slug}-{type}` and `is-{name}` patterns
are part of WordPress's documented CSS surface.
Themes write CSS targeting these classes; user
custom CSS targets them; third-party tools depend
on them. Changing the patterns would break
extensive ecosystems. The conventions are
effectively frozen.

This stability is what makes the multi-runtime
realization tractable — every implementation is
producing output to the same naming spec, and the
spec doesn't drift.

## WHEN NOT

Skip the per-block style attribution mechanism if:

- The block has **no styling attributes**. Pure
  text-content blocks (legacy paragraph variants,
  for example) may not need the wrapper machinery.
  They still typically use `useBlockProps()` for
  the block-name class and other framework-emitted
  props, but the style attribution chain runs with
  empty input.
- You are working with a **classic theme** rendering
  pre-block content. There is no block; there is
  no wrapper-prop generation; styles come from
  theme stylesheets directly.
- The intended styling is **theme-author CSS
  applied to all instances**. That belongs in the
  theme's stylesheet (or in a per-block style file
  registered through `block.json`'s `style` /
  `editorStyle` fields), not in per-instance
  attribution.
- You need **dynamic, request-time styling that
  responds to runtime state** (e.g., user data,
  current time). The attribution mechanism reads
  static block attributes; runtime-driven styling
  needs interactivity-API patterns instead.

## COUNTER-PATTERNS

### Anti-pattern 1 — Skipping `useBlockProps.save()` in save()

```js
const Save = ( { attributes } ) => {
    return (
        <div className="my-block">  {/* missing useBlockProps.save spread */}
            { attributes.content }
        </div>
    );
};
```

The save's wrapper won't get the
attribute-derived classes / styles. The editor
preview (which uses `useBlockProps()`) will look
correct; the saved/rendered version won't. Use
`useBlockProps.save()` and spread its result onto
the wrapper.

### Anti-pattern 2 — Skipping `get_block_wrapper_attributes()` in render.php

```php
<div class="my-block">
    <?php echo $content; ?>
</div>
```

Dynamic-block renders without
`get_block_wrapper_attributes()` lose the
attribute-derived classes / styles for the
front-end output. The block's color choice in the
editor won't reflect on the front end:

```php
<div <?php echo get_block_wrapper_attributes(); ?>>
    <?php echo $content; ?>
</div>
```

### Anti-pattern 3 — Manually computing class names from attributes

```js
const Edit = ( { attributes } ) => {
    const className =
        ( attributes.backgroundColor ? 'has-background has-' + attributes.backgroundColor + '-background-color' : '' )
        + ' my-custom-class';
    return <div className={ className }>…</div>;
};
```

The framework computes these classes already.
Hand-rolling the rules:

- Misses edge cases (gradient, layout, etc.).
- Can drift from the framework's evolving
  conventions.
- Doesn't compose with `useBlockProps()`'s other
  props (drag handle wiring, accessibility, etc.).

Always go through `useBlockProps()` and let it
spread the canonical wrapper props onto your
element.

### Anti-pattern 4 — Using preset references when literal CSS is intended

```js
attributes: {
    backgroundColor: '#abc',  // wrong — backgroundColor expects a slug
}
```

`backgroundColor` (the top-level attribute) holds a
*preset slug*. To use a literal value, set
`style.color.background` instead:

```js
attributes: {
    style: {
        color: {
            background: '#abc',
        },
    },
}
```

The class-name conventions assume top-level
attributes are slugs and `style.*` paths hold
literals.

### Anti-pattern 5 — Conflating the three realizations as a runtime bridge

Mental model trap: "the editor's wrapper props
'sync' to the front-end's wrapper attributes, so
this is a runtime bridge."

It is not. Each runtime independently computes
the output from the same input. There is no
sync, no handoff, no shared runtime state. The
three implementations are *equivalent* by
specification, not *coordinated* by execution.

The practical implication: don't try to "tell"
one runtime what the others computed. If they're
producing different output, the diagnosis is that
one of them isn't being called (Anti-patterns 1
and 2), not that they need to be coordinated.

### Anti-pattern 6 — Duplicating preset CSS in the block's own stylesheet

```css
/* Block's own style.css duplicates what theme.json already emits */
.wp-block-myplugin-block.has-primary-background-color {
    background-color: #abc !important;
}
```

The class is already wired to the theme.json
preset. Hardcoding the color in the block's
stylesheet:

- Defeats the user's ability to customize the
  preset.
- Forces `!important` to win against the
  framework's emitted CSS, which is fragile.
- Creates a maintenance burden when the
  framework's conventions change.

If the block needs different color behavior than
the preset would provide, design it as a
separate attribute or use a custom literal — don't
override the framework's preset wiring.

## OPERATIONAL NOTES

The per-block attribution mechanism's interpretive
shape, in proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *layered cascade* form. The
  block instance *declares* an attribute value;
  wrapper-prop generation *exposes* a class /
  style; the DOM *exposes* the wrapper element;
  the CSS rule's matching *exposes* the visible
  styling. Four distinct exposures of one
  declaration. Naming Law 1 here is genuinely
  clarifying because the *gap* between "this block
  has this color attribute" and "this DOM element
  shows this color" is a four-stage chain — and
  diagnosing failures means identifying which
  stage didn't fire.
- **Doctrine 5 (Authority Continuity)** appears
  *moderately*. The preset slug is the continuity
  surface across the entire pipeline: theme.json
  palette entry → block attribute value → wrapper
  class name → CSS custom property → applied
  value. The same string (`'primary'`) persists
  across five distinct representations. Worth one
  mention; not a section.

What this chunk is **not** about:

- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** *The most important non-fit to name
  precisely* in this terrain. The three parallel
  realization paths (`useBlockProps()` /
  `useBlockProps.save()` /
  `get_block_wrapper_attributes()`) sit in three
  different runtimes (editor JS / save
  serialization / PHP render). The naive reading
  is "they bridge across runtimes to keep the
  wrapper consistent." This reading is **wrong**:
  - There is no runtime authority being
    preserved across boundaries. Each runtime
    independently computes wrapper props from
    the same input attributes.
  - The output is **equivalent by specification**,
    not **coordinated by execution**. The
    contract is the spec; the runtimes implement
    the contract independently.
  - No state, identity, or capability persists
    from one runtime to another. The attributes
    are the input; the wrapper props are the
    output; nothing else flows.
  Naming Law 3b here would conflate *parallel
  realization* with *cross-runtime bridge*. The
  phrasing worth pinning: *parallel surfaces ≠
  cross-runtime bridge; equivalent-by-spec
  implementations of one contract are not the
  same as runtime-authority preservation across
  contexts*.
- **Law 4 (Arbitration Compiler).** No candidate
  selection. Each attribute deterministically
  maps to its corresponding class / style via
  documented naming conventions. Multiple
  attributes on the same element compose
  independently (preset + custom value coexist
  per Section C); they don't compete. Omitted.
- **Doctrine 6 (Authority Mediation).** No
  access mediation. The wrapper-prop generation
  reads attributes and produces CSS-shaped
  output; capability checks for *who can set
  the attributes* live elsewhere (in the
  inspector controls, in the editor's
  permission model). Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** The
  build pipeline is upstream; not part of the
  attribution mechanism. The three realization
  contexts are runtime contexts (each running
  at request / interaction time), not a
  build / runtime split. Omitted.
- **Federation.** The three realization paths
  are not federation participants. Federation
  means many parallel, equivalent participants
  federating around a shared registry; here we
  have three implementations of one contract
  with no shared registry between them.
  Omitted.
- **Section X archetypes.** A wrapper-prop
  generation mechanism is not a "civilization."
  Same framework-omission discipline as the
  surrounding chunks. Omitted.

Two literacy contributions worth pinning:

> *Attribute declaration ≠ wrapper realization.*
> A block instance carrying an attribute value is
> not the same as that value appearing as CSS on
> the rendered element. Four stages —
> declaration, realization, emission, application
> — sit between the two. The chain is
> deterministic; each stage is a specific
> transformation, with its own failure mode if
> skipped or misconfigured.

This contribution extends Law 1 (Declaration ≠
Exposure) into a *multi-stage cascade* form:
where the simpler Law 1 instances distinguish two
states (declared / exposed), this one names the
shape when *several intermediate transformations*
sit between declaration and final visible
realization. Pairs naturally with the
*4-step interactivity ladder* (embedded /
activated / triggered / executed) — both are
diagnostic frames for "why isn't this working?"
walks through staged pipelines.

> *Parallel surfaces ≠ cross-runtime bridge.* A
> mechanism that has multiple implementations,
> each in its own runtime, all producing
> equivalent output for the same input is not the
> same shape as a bridge that preserves runtime
> authority across contexts. Both involve
> "matching behavior across runtimes," but the
> bridge actively transfers state / identity /
> capability across a boundary; parallel
> realization independently re-derives output
> from shared input. The shape is contract-shared,
> not runtime-shared.

This contribution adds a *third* specific
non-Law-3b form to the KB's vocabulary:

- *File copy across phases* (block.json build
  pipeline) — adjacent shape, file is artifact,
  not authority transfer.
- *Async fetch* (resolver lifecycle) — adjacent
  shape, server is source, not runtime context
  preservation.
- *Parallel realization* (this chunk) —
  adjacent shape, contract-shared
  implementations, not runtime bridging.

Together these form a small inventory of
mechanisms that *look bridge-shaped* but do not
preserve runtime authority across contexts. The
inventory is prose-level literacy; not a
constitutional pattern.

## CHECKLIST

When implementing per-block style attribution:

- [ ] Use `useBlockProps()` in `edit` and
      `useBlockProps.save()` in `save`. For
      dynamic blocks, use
      `get_block_wrapper_attributes()` in
      `render.php`. Spread the result onto the
      wrapper element.
- [ ] Don't hand-roll `has-{slug}-{type}` class
      generation. Let the framework's helpers
      produce them.
- [ ] Use top-level color/typography attributes
      (`backgroundColor`, `textColor`,
      `fontSize`) for *preset references*; use
      `style.color.*` / `style.typography.*` paths
      for *literal values*.
- [ ] If a block uses both preset and literal
      values for related properties, expect both
      classes and inline styles on the wrapper —
      they coexist by design.
- [ ] Don't override the framework's preset CSS
      from your block's stylesheet. If you need
      different behavior, expose a different
      attribute or use literals.
- [ ] When debugging "the editor preview looks
      different from the front end," check
      whether `useBlockProps.save()` (static) /
      `get_block_wrapper_attributes()` (dynamic)
      is being called.
- [ ] Don't think of the three realization paths
      as bridged. They are independent
      implementations of one contract; their
      consistency comes from the contract, not
      from runtime communication.

## REFERENCES

- `useBlockProps` reference. Documents the
  editor-side hook and the props it returns.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
- `get_block_wrapper_attributes()` reference.
  Documents the PHP-side wrapper attribute
  generation for dynamic blocks.
  https://developer.wordpress.org/reference/functions/get_block_wrapper_attributes/
- Block supports reference. Documents which
  attributes participate in style attribution
  (color, typography, spacing, border, etc.).
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
- Global Settings & Styles handbook — Preset CSS
  custom properties section. Documents the
  `has-{slug}-{type}` class convention and the
  underlying CSS variable naming.
  https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/#preset-css-custom-properties
- `@wordpress/style-engine` package reference.
  Documents the per-instance CSS synthesis path
  for complex attributes that exceed the
  class-based mechanism.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-style-engine/

Cross-context:

- `style-engine.theme-json-source-layering` —
  the upstream merge that defines the design
  vocabulary this chunk's attributes consume.
  Together they form the *governance + embodiment*
  pair: that chunk maps the design vocabulary's
  authority ecology, this chunk maps how a single
  block instance realizes a value from that
  vocabulary.
- `style-engine.preset-materialization` — the
  value-transformation pipeline this chunk's
  classes hook into. The preset reference
  (`'primary'`) becomes a CSS variable
  (`var(--wp--preset--color--primary)`) through
  that mechanism; this chunk's classes consume
  the variable.
- `style-engine.css-variable-emission` — the
  emission stage that produces the custom
  property declarations the wrapper classes
  resolve to.
- `style-engine.generated-selectors` — runtime
  synthesis of `.wp-elements-{uuid}` selectors
  for complex per-instance styles. The handoff
  in Section E points at that mechanism.
- `block.edit-and-save-contracts` — the edit /
  save contract pair that hosts `useBlockProps()`
  and `useBlockProps.save()`.
- `block.wrapper-attributes` — the broader
  wrapper hub this chunk operates through. This
  chunk focuses on the style-attribution slice;
  that chunk covers the full wrapper API.
- `block.json-attributes-core` — the attribute
  schema that declares which attributes
  participate in styling.
