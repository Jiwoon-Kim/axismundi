---
rule_id: editor-customization.inspector-controls
domain: editor-customization
topic: configuration-surface
field_cluster: block-sidebar-controls
wp_min: "5.0"
wp_recommended: "6.5+"
package_min: "@wordpress/block-editor@^12"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/components/inspector-controls/
    section: "InspectorControls — component reference + group variants"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/slotfills/inspector-controls/
    section: "InspectorControls slot — sidebar tabs + extension targets"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/components/
    section: "@wordpress/components — PanelBody, TextControl, SelectControl, etc."
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-in-the-editor/
    section: "Block in the editor — Edit component, attributes, controls"
    captured: 2026-05-10
related:
  - block.edit-and-save-contracts                  # the edit contract InspectorControls slots into
  - block.json-attributes-core                     # the attribute schema controls read from / write into
  - block.edit-save-components                     # sibling components used inside edit (RichText, InnerBlocks, useBlockProps)
  - editor-customization.slotfills                 # the underlying slot/fill mechanism (deeper substrate)
  - editor-customization.editor-hooks              # cross-block extension surface where InspectorControls fills are also relevant
---

# RULE — `<InspectorControls>` — block sidebar configuration surface

## WHEN

You are inside a block's `edit` function and need to
expose user-facing controls that **adjust the block's
attributes**. Use this knowledge when:

- Adding a settings panel to the right-hand block
  sidebar (the "Block" tab) for a custom block.
- Choosing between the default sidebar group and the
  named groups (advanced, dimensions, typography, etc.).
- Reviewing where a control should live: in the
  sidebar (`<InspectorControls>`), in the toolbar
  (`<BlockControls>`), or inline in the block body.
- Diagnosing why a control isn't appearing in the
  sidebar (almost always: the block isn't selected,
  the JSX is outside the edit return, or the wrong
  group target was chosen).
- Extending another block's sidebar via a filter (a
  use case that consumes the same component).

This chunk does **not** cover:

- The SlotFill mechanism itself (`createSlotFill`,
  custom slots, fill ordering) — covered in
  `editor-customization.slotfills`.
- The toolbar component `<BlockControls>` in detail —
  Section F is a contrast paragraph; the toolbar's
  full surface is its own chunk if needed.
- Specific control components from
  `@wordpress/components` — those have their own
  documentation surface and are referenced in
  examples here without being exhaustively cataloged.
- Cross-block extension via `editor.BlockEdit` filter
  — that pathway uses InspectorControls but its
  governance shape lives in
  `editor-customization.editor-hooks`.

The principle this chunk operates under:
**`<InspectorControls>` is the *configuration surface*
of a block. It is structurally and semantically
distinct from the *execution surface* (the rendered
block content). The two surfaces share only one thing:
the block's attributes.**

## SHAPE

### A. The component pair — fill in `edit`, slot in the editor

`<InspectorControls>` is a Fill component from
`@wordpress/block-editor`. The matching Slot lives in
the editor frame (in the right-hand block sidebar
panel). The block's `edit` function provides the Fill;
the editor's chrome provides the Slot.

```js
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

const Edit = ( { attributes, setAttributes } ) => {
    return (
        <>
            <InspectorControls>
                <PanelBody title="Settings" initialOpen={ true }>
                    <TextControl
                        label="Heading"
                        value={ attributes.heading }
                        onChange={ ( value ) => setAttributes( { heading: value } ) }
                    />
                </PanelBody>
            </InspectorControls>

            <div>
                {/* the block's actual rendered content goes here */}
                <h2>{ attributes.heading }</h2>
            </div>
        </>
    );
};
```

Two consequences of the Fill / Slot split:

- **Where the JSX is *written* (inside `edit`'s
  return) is independent of where it *renders* (the
  sidebar).** This is exactly what makes
  InspectorControls feel "magical" the first time —
  your `<TextControl>` lives next to your block body
  in source, but appears in the sidebar in the editor
  UI.
- **The Fill is conditional on the block being
  selected.** The editor only mounts the
  InspectorControls Slot when there's a selected
  block, and only fills it from the selected block's
  `edit`. Switching block selection swaps which
  block's controls are visible.

### B. Usage pattern — controls mutate attributes through `setAttributes`

Every control follows the same shape:

```js
<SomeControl
    value={ attributes.someAttr }
    onChange={ ( newValue ) => setAttributes( { someAttr: newValue } ) }
/>
```

The control reads the *current attribute value* and
writes the *new attribute value* on user interaction.
The component is *fully controlled* — its visible
state always reflects the underlying attribute, and
user interaction always flows through `setAttributes`.

This produces a small but important asymmetry:

- The control's *display* depends on the attribute.
- The attribute's *value* depends on the control's
  `onChange` (or any other source that calls
  `setAttributes`).
- The block's *rendering* depends on the attribute.

The inspector control is a **read-write window into
attribute state**. It is not a place where derived
state, render logic, or business rules belong. Those
live in the rendering portion of `edit`, in `save`,
or in dispatched actions.

### C. Group variants — placing controls in the right sidebar panel

The block sidebar has multiple panel groups. Passing
a `group` prop to `<InspectorControls>` targets which
panel the fill lands in:

| `group` value (omitted = default) | Sidebar panel target                        |
| --------------------------------- | ------------------------------------------- |
| _(none)_                          | The default custom panels area              |
| `"advanced"`                      | The "Advanced" collapsible panel at the bottom |
| `"position"`                      | Position-related controls panel             |
| `"dimensions"`                    | Dimensions panel                            |
| `"typography"`                    | Typography panel                            |
| `"color"`                         | Color panel                                 |
| `"border"`                        | Border panel                                |
| `"background"`                    | Background panel                            |
| `"styles"`                        | Styles panel                                |

```js
<InspectorControls group="advanced">
    <PanelRow>
        <ToggleControl
            label="Show in feed"
            checked={ attributes.showInFeed }
            onChange={ ( value ) => setAttributes( { showInFeed: value } ) }
        />
    </PanelRow>
</InspectorControls>
```

The group system is **named target dispatch**, not
priority arbitration. A control aimed at `"advanced"`
goes to the Advanced panel; a control with no group
goes to the default area; both render side by side
in their respective panels with no notion of one
"winning" over the other.

This is worth pinning because the multi-group surface
can superficially look like a candidate selection
mechanism. It isn't. Each group is an independent
target.

### D. Component family — what to put inside

The body of an `<InspectorControls>` typically
contains structural components from
`@wordpress/components`:

- **`<PanelBody>`** — a collapsible section with a
  title. Use for grouping related controls. The
  `initialOpen` prop sets whether it starts expanded.
- **`<PanelRow>`** — a single row inside a panel for
  inline label + control pairs.

…and then the actual control components, such as:

- **`<TextControl>`**, **`<TextareaControl>`** — text input.
- **`<SelectControl>`**, **`<RadioControl>`** — discrete choice.
- **`<ToggleControl>`** — boolean.
- **`<RangeControl>`**, **`<NumberControl>`** — numeric.
- **`<ColorPalette>`**, **`<ColorPicker>`** — color.
- **`<__experimental*>`** components — newer / unstable
  controls (use with awareness that the API may
  change).

The control library is large; this chunk does not
catalog it. The shared shape is what matters: every
control accepts a `value` and an `onChange`, and you
wire those to attributes.

### E. Federation — multiple fills target the same slot

Several distinct sources can contribute to the same
InspectorControls slot:

- The block's own `edit` function (the canonical
  case).
- Plugins extending the block via the
  `editor.BlockEdit` filter (which wraps the block's
  `edit` and can add additional `<InspectorControls>`
  fills).
- Higher-order components that augment all blocks of
  a type (similar pattern, different injection
  point).

When multiple sources fill the same slot, the editor
**stacks all fills**, in approximately mount order,
inside the sidebar. There is no first-fill-wins
semantics, no priority arbitration, no override.
Each fill renders next to the others.

This is the same federation shape that recurs
throughout the WordPress JS layer — many
participants contribute to a shared rendering target
without coordinating with each other. Recognizable
from `wp-data-registry` (multiple stores in one
registry), `wp-scripts` externals (many plugins
share the same `window.wp.*` runtime), and elsewhere.

### F. Sister surface — `<BlockControls>` for the toolbar

`<BlockControls>` is the equivalent component for the
**block toolbar** (the floating toolbar that appears
above a selected block) rather than the sidebar.
Same SlotFill mechanism, different placement target.

| Aspect            | `<InspectorControls>`         | `<BlockControls>`            |
| ----------------- | ----------------------------- | ---------------------------- |
| Render location   | Right sidebar (Block tab)     | Toolbar above selected block |
| Typical contents  | PanelBody + form-shaped controls | ToolbarGroup + ToolbarButton |
| Visibility        | When block is selected        | When block is selected       |
| Fill / Slot pair  | Yes                           | Yes                          |

The two are **siblings**, not nested or overlapping.
A block's `edit` typically returns both: a
`<BlockControls>` for inline-editing actions
(alignment, link, formatting), and an
`<InspectorControls>` for configuration. They serve
different interaction modes (immediate manipulation
vs deliberate configuration), in different visual
locations, with different expected control
densities.

This chunk does not extend further into
`<BlockControls>` mechanics. The contrast is enough
to disambiguate the two when authoring a new block.

## WHY

### Why a separate configuration surface

A block has at least three distinct authoring modes:

- **Inline editing** — direct manipulation of the
  block content (typing into a heading, dragging an
  image into place).
- **Toolbar actions** — context-sensitive immediate
  actions (alignment, link, format).
- **Configuration** — deliberate parameter setting
  that the user expects to need a moment to think
  about (color choice, layout strategy, behavioral
  toggles).

Conflating configuration with inline editing or
toolbar actions creates a user-experience problem:
configuration controls clutter the inline editing
view, and inline editing actions get lost in
configuration panels. The sidebar's spatial
separation lets configuration breathe — multiple
panels, expanded labels, structural grouping — at
the cost of one extra visual location.

`<InspectorControls>` exists to make this separation
mechanical: a control written inside
`<InspectorControls>` is automatically placed in the
configuration surface. The author does not need to
know how the editor frame is laid out.

### Why fill-side composition rather than registration

The fill-side pattern (the block's `edit` returns
its own `<InspectorControls>` with whatever it
wants inside) means a block self-describes its
configuration surface. The alternative — registering
controls through a separate API call — would require
a parallel registration mechanism with its own
lifecycle.

The current design lets the block's React component
own the entire rendered representation, including
the sidebar fill. The sidebar fill is *part of the
block's edit output*; it just renders elsewhere. This
keeps the conceptual model unified.

### Why the group system uses named targets, not priority

Each group corresponds to a distinct visible panel
in the sidebar. There is no scenario where two groups
"compete" — a control aimed at "advanced" cannot
also displace a control aimed at "default" because
they don't share a render target. A named-target
mechanism matches the actual UI shape; a priority
mechanism would invent a competition that doesn't
exist.

### Why controls re-render on every attribute change

Each control reads `attributes.someAttr` and the
`edit` function re-runs whenever attributes change.
This is React's standard behavior. The cost (one
re-render per keystroke in a TextControl, throttled
by React's batching) is much smaller than the
alternative — manually managing control state and
synchronizing it with attributes — would have been.

The controlled-component pattern makes the control's
displayed value definitionally consistent with the
attribute's current value. Drift between "what the
control shows" and "what the attribute holds" is
not possible by construction.

## WHEN NOT

Skip `<InspectorControls>` if:

- The control is **inline-natural** — alignment
  buttons, formatting toggles, link insertion.
  These belong in `<BlockControls>` (toolbar) or in
  `RichText`'s formatting controls, not in the
  sidebar.
- The control is **always-on display** for the user
  even when the block isn't selected. The sidebar is
  selection-scoped; for always-visible controls,
  consider rendering them inline in the block body
  (gated on `isSelected` if needed).
- The "control" is actually **business logic in
  disguise** (a toggle that has a complex side
  effect). Extract the side effect into a dispatched
  action; the toggle just sets an attribute.
- You need controls that are **scoped to the entire
  document** (not to one block). Document-level
  settings live elsewhere — usually in custom
  document panels via slot fills targeting
  `PluginDocumentSettingPanel`, not in
  `<InspectorControls>`.

## COUNTER-PATTERNS

### Anti-pattern 1 — Mutating state outside `setAttributes`

```js
<TextControl
    value={ attributes.label }
    onChange={ ( value ) => {
        someExternalStore.setLabel( value );  // wrong direction
    } }
/>
```

The control's `onChange` should call `setAttributes`.
External state stores are not the persistence
mechanism for block attributes. If the value should
also propagate to a store (e.g., for cross-block
coordination), do that *in addition to*
`setAttributes`, not instead of it.

### Anti-pattern 2 — Controls that derive other controls' values

```js
<TextControl value={ attributes.title } onChange={ … } />
<TextControl value={ attributes.title.toUpperCase() } onChange={ … } />
```

If two controls show derived views of the same data,
the second one's `onChange` either won't work
(`toUpperCase` isn't reversible) or will create a
loop. Derived display goes in the block body, not in
a control. Keep one control per attribute.

### Anti-pattern 3 — Putting render logic inside the inspector

```js
<InspectorControls>
    <PanelBody>
        <div className="my-block-preview">
            { attributes.heading }
        </div>
    </PanelBody>
</InspectorControls>
```

The sidebar is for configuration controls, not for
preview / render. The preview belongs in the block
body. A user looking at the sidebar expects controls;
finding rendered content there is jarring and
wastes the sidebar's specialized layout.

### Anti-pattern 4 — Using the wrong group for control placement

```js
<InspectorControls group="color">
    <RangeControl
        label="Padding"
        value={ attributes.padding }
        onChange={ ( v ) => setAttributes( { padding: v } ) }
    />
</InspectorControls>
```

A padding control in the color panel is misfiled.
Use `group="dimensions"` (or no group, depending on
how the rest of the block is organized). Group
choice is editorial, not arbitrary; pick the panel
that matches the control's category.

### Anti-pattern 5 — Conditional fills based on derived attribute state

```js
{ attributes.advanced && (
    <InspectorControls>
        <PanelBody title="Settings">
            <TextControl … />
        </PanelBody>
    </InspectorControls>
) }
```

Hiding the entire fill on a condition is rarely what
you want — the user can't see the controls that
would let them turn `advanced` off. Show the
controls and let `disabled` / `help` text clarify
state instead. Conditional rendering of *individual*
controls inside the panel is fine; conditional
rendering of the whole fill usually isn't.

### Anti-pattern 6 — Forgetting `<PanelBody>` wrapping

```js
<InspectorControls>
    <TextControl … />
    <SelectControl … />
</InspectorControls>
```

Controls without a `<PanelBody>` wrapper render
without panel structure, which doesn't match the
visual conventions of other blocks' sidebars.
Group related controls in `<PanelBody>` panels with
descriptive titles.

## OPERATIONAL NOTES

The control surface's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the central
  fit. The block's attributes are the *declared*
  schema (from `block.json-attributes-core`); the
  control surface *exposes* one read-write
  representation of those attributes for human
  interaction. Render in the block body exposes a
  different representation of the same attributes;
  `save` exposes a third (the serialized form). One
  declaration, multiple exposures, each shaped to
  its audience. Naming Law 1 here is genuinely
  clarifying because the asymmetry between
  declaration (attributes) and the *configuration*
  exposure of those attributes is exactly what
  separates the inspector surface from the rendering
  surface.
- **Doctrine 5 (Authority Continuity)** applies in a
  *moderate* form. The block's attributes are the
  authority surface that survives across the
  inspector's interaction sessions, the block's
  selection state, and (for static blocks) the
  serialized output. Inspector mounts and unmounts
  with selection, but the attribute it reads / writes
  persists. Worth one mention; not a section.
- **Doctrine 6 (Authority Mediation)** appears
  *softly* in the controlled-component pattern.
  Attribute mutation flows through `setAttributes`,
  not through arbitrary state mutation. This is
  *write-channel governance* at the UI level —
  the same softer expression as the
  `wp-data-registry`'s and `resolver-lifecycle`'s
  write-channel mediation. Not access mediation,
  not capability-checked; just one canonical write
  pathway. Worth one mention; not a section.
- **Federation** appears in Section E: multiple
  fills can target the same slot, all stack
  together. Same federation shape pinned across
  the JS layer (registry, externals, etc.). Cross-
  reference; not re-elaborated.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *The most
  important non-fit to name precisely* in this
  terrain. Several superficially Law-4-shaped
  features are not arbitration:
  - The group variants (default / advanced /
    typography / color / etc.) are *named-target
    dispatch*. Each group renders in its own
    panel; no group "wins" over another.
  - Multiple fills targeting the same slot
    (Section E) are *stacked composition*, not
    *priority selection*. All fills render; none
    is suppressed by another.
  - Multi-control panels are *UI grouping*, not
    arbitration over which control applies.
  Naming Law 4 here would be a category error
  driven by the surface impression of "many
  things, choose where each goes." The mechanism
  is dispatch by name and composition by stacking,
  not arbitration.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** All inspector code runs in the editor
  JS runtime. No runtime boundary, no authority
  preservation across contexts. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** Inspector
  controls exist entirely at editor-runtime. The
  build pipeline is upstream; not part of this
  mechanism. Omitted.
- **Section X archetypes.** A configuration surface
  for blocks is not a "civilization." Same
  framework-omission discipline as the surrounding
  chunks. Omitted.

A small literacy contribution worth pinning:

> *Configuration surface ≠ execution surface.* A
> place where the user *adjusts* what a thing does
> is structurally distinct from the place where the
> thing *is rendered*. Both surfaces read from the
> same declared schema (the block's attributes), but
> the configuration surface reads-and-writes for
> human interaction, while the execution surface
> reads-only for output. Conflating the two — by
> putting render logic in the inspector or by
> making rendering depend on inspector-side
> ephemeral state — collapses an important
> separation.

This pairs cleanly with the existing block-authoring
literacy contribution from
`block.edit-and-save-contracts`:

> *Authoring interaction ≠ content persistence.*

Together: one declared attribute schema is exposed
through three distinct surfaces — *interaction*
(`edit` body), *configuration* (inspector controls),
and *persistence* (`save` output). Each surface has
its own audience, its own access pattern (read-write
vs read-only vs write-once-then-read), and its own
change cost. Recognizing the three-way split prevents
a recurring class of design mistakes where one
surface's logic leaks into another.

## CHECKLIST

When using `<InspectorControls>`:

- [ ] Place it inside `edit`'s return, alongside the
      block body (typically as siblings inside a
      fragment).
- [ ] Wire each control's `value` to an attribute
      and its `onChange` to `setAttributes` for the
      same attribute. One control per attribute is
      the default shape.
- [ ] Group related controls inside `<PanelBody>`
      with a clear title. Avoid bare controls.
- [ ] Choose the right `group` for each panel:
      default for custom block-specific settings,
      named groups (typography, color, dimensions,
      etc.) when the controls fit core's panel
      categories.
- [ ] Don't put rendering or preview content inside
      the inspector. The sidebar is for
      configuration only.
- [ ] Don't conditionally render the entire
      `<InspectorControls>` based on a state the
      user can only change *through* it. Use
      `disabled` / `help` text instead.
- [ ] Use `<BlockControls>` (toolbar) for inline /
      formatting actions; reserve
      `<InspectorControls>` for deliberate
      configuration.
- [ ] Treat fills from other plugins (via
      `editor.BlockEdit` etc.) as composition
      partners, not competitors. The editor stacks
      everyone's fills; design controls so they
      coexist with whatever else may appear in the
      same panel.

## REFERENCES

- `<InspectorControls>` component reference. Group
  variants, prop signature, fill semantics.
  https://developer.wordpress.org/block-editor/reference-guides/components/inspector-controls/
- InspectorControls slot reference (slot/fill
  perspective). Documents the slot's location in
  the editor frame.
  https://developer.wordpress.org/block-editor/reference-guides/slotfills/inspector-controls/
- `@wordpress/components` reference. Documents
  PanelBody, PanelRow, TextControl, SelectControl,
  ToggleControl, RangeControl, ColorPalette, etc.
  https://developer.wordpress.org/block-editor/reference-guides/components/
- Block-in-the-editor handbook. Explains the Edit
  component contract that hosts InspectorControls
  fills.
  https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-in-the-editor/

Cross-context:

- `block.edit-and-save-contracts` — the `edit`
  function that hosts InspectorControls. The
  configuration surface is one part of the
  interaction contract.
- `block.json-attributes-core` — the attribute
  schema that controls read from / write into via
  `setAttributes`.
- `block.edit-save-components` — sibling chunk on
  the practical components used inside `edit` and
  `save` (`useBlockProps`, `RichText`,
  `InnerBlocks`, etc.).
- `editor-customization.slotfills` — the underlying
  SlotFill mechanism. InspectorControls is one
  pre-made fill component built on that substrate.
- `editor-customization.editor-hooks` — the
  cross-block extension surface (filters like
  `editor.BlockEdit`) where third-party
  InspectorControls fills are added to other blocks'
  inspectors.
