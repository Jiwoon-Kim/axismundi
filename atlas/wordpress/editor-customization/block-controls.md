---
rule_id: editor-customization.block-controls
domain: editor-customization
topic: action-surface
field_cluster: block-toolbar-controls
wp_min: "5.0"
wp_recommended: "6.5+"
package_min: "@wordpress/block-editor@^12"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/components/block-controls/
    section: "BlockControls — component reference + group variants"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/slotfills/block-controls/
    section: "BlockControls slot — toolbar placement and extension"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-button/
    section: "ToolbarButton — base toolbar button component"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-group/
    section: "ToolbarGroup — button grouping with separators"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-dropdown-menu/
    section: "ToolbarDropdownMenu — collapsed multi-action surfaces"
    captured: 2026-05-10
related:
  - editor-customization.inspector-controls       # sister surface — sidebar configuration counterpart
  - block.edit-and-save-contracts                 # the edit contract that hosts BlockControls
  - block.json-attributes-core                    # the attribute schema toolbar actions read/write
  - block.edit-save-components                    # other components used inside edit (RichText, InnerBlocks)
  - editor-customization.slotfills                # the underlying slot/fill mechanism
---

# RULE — `<BlockControls>` — block toolbar contextual-action surface

## WHEN

You are inside a block's `edit` function and need to
expose **immediately-actionable controls** that adjust
the block's attributes through single-tap interactions
in the floating toolbar above the selected block. Use
this knowledge when:

- Adding alignment, formatting, or other one-shot
  toggles to a custom block's toolbar.
- Choosing between the default toolbar group and the
  named groups (block, inline, other, parent).
- Reviewing where a control should live: in the
  toolbar (`<BlockControls>`), in the sidebar
  (`<InspectorControls>`), or inline in the block
  body.
- Diagnosing why a toolbar button isn't appearing
  (almost always: the block isn't selected, the JSX
  is outside the edit return, the wrong group target,
  or the action is conflict-ing with a parent block's
  toolbar in `inline` group).

This chunk does **not** cover:

- The SlotFill mechanism itself (`createSlotFill`,
  custom slots, fill ordering) — covered in
  `editor-customization.slotfills`.
- The sidebar component `<InspectorControls>` in
  detail — covered in
  `editor-customization.inspector-controls`. Section
  F here is a contrast paragraph; the sidebar's full
  surface lives in that sibling chunk.
- The format toolbar inside `<RichText>` (used for
  bold / italic / link inline formatting) — that is
  `RichText`'s own internal toolbar, distinct from
  `<BlockControls>` even though `BlockControls
  group="inline"` extends adjacent to it.
- Specific control components from
  `@wordpress/components` — Toolbar-family components
  are referenced in examples here but not
  exhaustively cataloged.

The principle this chunk operates under:
**`<BlockControls>` is the *contextual action surface*
of a block. It is structurally and semantically
distinct from the *configuration surface*
(`<InspectorControls>`, sidebar) — they are siblings,
not duplicates. Both surfaces read and write the same
attribute schema; they differ in interaction tempo,
density, and visual location.**

## SHAPE

### A. The component pair — fill in `edit`, slot in the toolbar

`<BlockControls>` is a Fill component from
`@wordpress/block-editor`. The matching Slot lives
in the floating block toolbar that appears above the
selected block in the editor canvas. The block's
`edit` function provides the Fill; the editor's
chrome provides the Slot.

```js
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { alignLeft, alignCenter, alignRight } from '@wordpress/icons';

const Edit = ( { attributes, setAttributes } ) => {
    return (
        <>
            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon={ alignLeft }
                        label="Align left"
                        isPressed={ attributes.align === 'left' }
                        onClick={ () => setAttributes( { align: 'left' } ) }
                    />
                    <ToolbarButton
                        icon={ alignCenter }
                        label="Align center"
                        isPressed={ attributes.align === 'center' }
                        onClick={ () => setAttributes( { align: 'center' } ) }
                    />
                </ToolbarGroup>
            </BlockControls>

            <div style={ { textAlign: attributes.align } }>
                {/* the block's actual rendered content */}
            </div>
        </>
    );
};
```

Same Fill / Slot pattern as `<InspectorControls>`, with
two consequences in the toolbar context:

- **The toolbar is selection-scoped and floats over
  the canvas.** It mounts when the block is selected;
  it is not part of the static editor chrome. The
  toolbar's vertical space is constrained — the
  surface is designed for compact, icon-driven
  controls, not form-shaped configuration.
- **Buttons should *act immediately*.** A toolbar
  button is a tap-to-do, not a tap-to-open-a-panel.
  Long-form configuration belongs in the sidebar
  (`<InspectorControls>`).

### B. Usage pattern — toolbar buttons as immediate-action triggers

Each toolbar button follows the action shape:

```js
<ToolbarButton
    icon={ … }
    label="Description for screen readers and tooltip"
    isPressed={ /* current state predicate */ }
    onClick={ () => setAttributes( { … } ) }
/>
```

Three load-bearing properties:

- **`onClick` does the action.** No "Apply" button, no
  intermediate form state — the button press is the
  action. The next render shows the new state via
  `isPressed`.
- **`isPressed` reflects current state.** For toggle-
  like actions (alignment, formatting flags), the
  button visually indicates whether its action is
  "active." Reads from attributes; toggling is
  symmetric.
- **`label` is required for accessibility.** The
  button is icon-only by default, so the label is
  what screen readers announce and what hovers as a
  tooltip.

The interaction tempo: see icon → understand action →
tap → result is immediate. This is meaningfully
different from the sidebar's see-label → focus-input
→ enter-value → blur-or-tab → result. Toolbar = command;
sidebar = configuration.

### C. Group variants — placement targets within the toolbar

The block toolbar has multiple group targets. Passing a
`group` prop to `<BlockControls>` chooses where the
fill lands:

| `group` value (omitted = default = `"default"`) | Placement                                         |
| ----------------------------------------------- | ------------------------------------------------- |
| `"default"`                                     | Main toolbar area (most block-level actions)      |
| `"block"`                                       | Block-level controls area (alignment, etc.)       |
| `"inline"`                                      | Inline format toolbar (next to RichText format buttons) |
| `"other"`                                       | "Options" overflow dropdown                       |
| `"parent"`                                      | Parent block's toolbar (used from within an inner block to add to the parent's toolbar) |

```js
// Action that should appear inside the inline format toolbar:
<BlockControls group="inline">
    <ToolbarButton
        label="Highlight"
        icon={ markerIcon }
        onClick={ () => insertHighlight() }
    />
</BlockControls>
```

The group system is **named target dispatch**, exactly
the same shape as `<InspectorControls>`'s group system.
A control aimed at `"inline"` lands in the inline
format area; a control aimed at `"other"` collapses
into the overflow dropdown; both render in their
respective targets with no notion of one "winning"
over another.

This is worth pinning twice (once in
`<InspectorControls>`, once here) because the
multi-group surface in either component can look
arbitration-shaped. It isn't, in either place. Each
group is an independent target.

### D. Component family — what to put inside

The body of a `<BlockControls>` typically contains
toolbar-shaped components from
`@wordpress/components`:

- **`<ToolbarGroup>`** — a visually grouped set of
  buttons separated by a divider from neighboring
  groups. Use to cluster related actions
  (alignment-buttons, formatting-buttons, etc.).
- **`<ToolbarButton>`** — the canonical icon-driven
  button. Accepts `icon`, `label`, `isPressed`,
  `isDisabled`, `onClick`.
- **`<ToolbarDropdownMenu>`** — a dropdown that
  collapses several actions behind a single toolbar
  slot. Use when actions are mutually exclusive (a
  single alignment selection rather than a row of
  alignment buttons), or when toolbar space is
  constrained.
- **`<ToolbarItem>`** — wrapper that lets non-button
  items participate in toolbar keyboard navigation.
- Pre-made convenience wrappers like
  **`<AlignmentControl>`** /
  **`<BlockAlignmentControl>`** /
  **`<BlockVerticalAlignmentControl>`** that bundle
  common patterns.

The shared shape: each component is a *one-tap*
affordance (button) or a *one-step-then-tap* affordance
(dropdown menu → choose item). Multi-step forms or
keyboard-text-entry inputs do not belong in the
toolbar; they belong in the sidebar.

### E. Federation — multiple fills stack in the toolbar

Several distinct sources can contribute to the same
BlockControls slot:

- The block's own `edit` function (the canonical
  case).
- Plugins extending the block via the
  `editor.BlockEdit` filter — wrapping the block's
  `edit` with additional `<BlockControls>` fills.
- Higher-order components targeting block types
  generally.

The editor stacks all fills into the toolbar, in
roughly mount order. Toolbar real estate is finite, so
in practice extension fills tend to land in the
overflow dropdown (`group="other"`) when the main row
fills up. The mechanism does **not** arbitrate
between fills; it composes them. Visual constraints
come from the rendering layer's space budget, not
from a priority decision in the framework.

This is the same federation shape that recurs across
the JS layer — many participants contribute to a
shared rendering target without coordinating with each
other. Recognizable from `wp-data-registry`,
`wp-scripts` externals, and from
`<InspectorControls>` (the immediately adjacent
chunk).

### F. Sister surface — `<InspectorControls>` for the sidebar

Direct contrast with the sibling chunk:

| Aspect              | `<BlockControls>`                          | `<InspectorControls>`                       |
| ------------------- | ------------------------------------------ | ------------------------------------------- |
| Render location     | Floating toolbar above the selected block  | Right sidebar (Block tab)                   |
| Interaction tempo   | One-tap action                             | Form-style configuration                    |
| Visual density      | Compact, icon-driven, horizontal           | Expansive, label + input pairs, vertical    |
| Typical contents    | `ToolbarGroup` + `ToolbarButton` + `ToolbarDropdownMenu` | `PanelBody` + `TextControl`/`SelectControl`/`ToggleControl` |
| Visibility          | When block is selected                     | When block is selected                      |
| Group targets       | default / block / inline / other / parent  | default / advanced / position / dimensions / typography / color / border / background / styles |
| Reads from          | Block attributes                           | Block attributes                            |
| Writes through      | `setAttributes`                            | `setAttributes`                             |

The two surfaces are **siblings**, not nested or
overlapping. A block's `edit` typically returns both
in the same render: a `<BlockControls>` for
immediate-action concerns (alignment, formatting,
flags), and an `<InspectorControls>` for deliberate
configuration (label text, link target, layout
strategy, color choice).

The decision rule for which surface to use:

- **Is this an action a user does *during* editing
  flow, mid-stream, without breaking concentration?**
  Toolbar. (Alignment changes, format toggles, "do X
  to selection" actions.)
- **Is this a configuration choice the user thinks
  about *between* edits, with multiple controls
  often inspected together?** Sidebar.

Both surfaces ultimately call `setAttributes` — they
are equally authoritative over attribute state. They
differ in interaction shape, not in power.

## WHY

### Why a separate action surface

The toolbar exists because some adjustments are
*part of editing flow* rather than *configuration of
the editing target*. When a user is composing a
heading and wants to center-align it, the action is
a continuation of editing — interrupting the flow to
open a sidebar panel, scroll to the alignment
control, and click it would be disruptive. A
center-align icon in the floating toolbar lets the
action happen without the user's attention leaving
the block.

The sidebar would also work mechanically — a
`<ToggleControl label="Centered">` in
`<InspectorControls>` would change the same
attribute. The choice of the toolbar is about
*tempo*, not about *capability*.

### Why the same Fill/Slot mechanism for two different surfaces

Reusing the SlotFill mechanism between
`<BlockControls>` and `<InspectorControls>` (and
also `<PluginDocumentSettingPanel>`,
`<PluginSidebar>`, etc.) means there is one
extension model for the editor. A plugin that wants
to add a button to a block's toolbar uses the same
mental model as adding a control to its sidebar:
provide a Fill, target the right Slot.

If the toolbar and the sidebar used different
extension mechanisms, the editor's surface area
would have two parallel APIs to learn. The
homogenization is for developer ergonomics, not for
end-user experience.

### Why toolbar groups exist at all

In principle a single toolbar with all buttons
inline could work for short toolbars. As blocks
gained more capabilities (alignment, formatting,
linking, transforms, options), the single row
overflowed. Group targets let the framework lay out
the toolbar predictably:

- The main `default` / `block` area for primary
  actions.
- The `inline` area adjacent to RichText format
  buttons for inline-text-related actions.
- The `other` overflow dropdown for tertiary
  actions.
- The `parent` group for inner blocks to inject
  into their parent's toolbar.

Each group renders in its own region of the toolbar
chrome. This is layout dispatch, not priority
arbitration.

### Why `isPressed` rather than separate "active" buttons

A toolbar button that's currently active is
visually highlighted. The same `<ToolbarButton>`
component with `isPressed={true}` styles itself as
active; with `isPressed={false}` styles itself as
inactive. One component, two visual states, driven
by attribute reading.

The alternative — separate `ActiveToolbarButton`
and `InactiveToolbarButton` components — would
require components to swap on every state change
rather than re-render their own visuals. The
chosen design fits React's render model and keeps
the toolbar API small.

## WHEN NOT

Skip `<BlockControls>` if:

- The control is **expansive configuration** with
  multiple inputs that benefit from grouping. Use
  `<InspectorControls>` for vertical-form layout.
- The control should be **always visible**, even
  when the block is not selected (rare for blocks).
  Toolbar is selection-scoped; for always-visible
  controls, render inline in the block body.
- The action requires **deliberate user input**
  (typing into a field, picking a color from a
  palette, choosing from a long list). Toolbar
  components support some of these via dropdowns,
  but the sidebar's vertical room handles them more
  comfortably.
- The "control" is actually a **format inside
  RichText** (bold, italic, link). Use RichText's
  format API; `<BlockControls group="inline">` is
  for *additional* inline actions adjacent to
  formats, not for the formats themselves.

## COUNTER-PATTERNS

### Anti-pattern 1 — Putting expansive forms in the toolbar

```js
<BlockControls>
    <ToolbarGroup>
        <ToolbarItem>
            <TextControl
                value={ attributes.title }
                onChange={ ( v ) => setAttributes( { title: v } ) }
            />
        </ToolbarItem>
    </ToolbarGroup>
</BlockControls>
```

A text input inside the toolbar fights the toolbar's
compact horizontal shape, breaks keyboard navigation,
and produces a mismatch between the surface's tempo
and the action's tempo. Move text input to
`<InspectorControls>` and use the toolbar only for
single-tap toggles or dropdown selects.

### Anti-pattern 2 — Forgetting the `label` on `<ToolbarButton>`

```js
<ToolbarButton
    icon={ alignLeft }
    onClick={ () => setAttributes( { align: 'left' } ) }
/>
```

Without `label`, the button has no accessible name.
Screen readers announce nothing useful; tooltips
don't render. Always provide a descriptive label.

### Anti-pattern 3 — Using `BlockControls` for cross-block actions

```js
<BlockControls>
    <ToolbarButton
        label="Reset all blocks"
        onClick={ () => resetEverything() }
    />
</BlockControls>
```

The block toolbar is scoped to the *selected block*.
Putting cross-document actions there is misleading —
users expect toolbar actions to affect the selected
block. Use document-level surfaces
(`<PluginToolbar>`, `<PluginDocumentSettingPanel>`)
for cross-block operations.

### Anti-pattern 4 — Toolbar buttons that "open" sidebars

```js
<ToolbarButton
    label="Configure"
    onClick={ () => openMyConfigSidebar() }
/>
```

If a configuration belongs in the sidebar, put the
controls in `<InspectorControls>` directly. A
toolbar button that "leads to configuration"
duplicates the sidebar's role and adds a step
without adding capability.

### Anti-pattern 5 — Filling the inline group with non-text-related actions

```js
<BlockControls group="inline">
    <ToolbarButton
        label="Delete this block"
        onClick={ () => removeBlock( clientId ) }
    />
</BlockControls>
```

The `inline` group sits next to RichText format
buttons (bold, italic, link). Users expect that
area to be about inline text formatting. A
block-level destructive action is jarring there.
Use `default` (or `other`) instead.

### Anti-pattern 6 — Action that requires "save" or commits later

```js
<ToolbarButton
    label="Stage rename"
    onClick={ () => stagePendingRename( newName ) }
/>
```

Toolbar actions should commit immediately. If an
action needs a separate confirmation step or a
"save" gesture, it is configuration; move it to the
sidebar where the form-shape is appropriate.

## OPERATIONAL NOTES

The action surface's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the central
  fit. As with `<InspectorControls>`, the block's
  attributes are the *declared* schema; the toolbar
  *exposes* one read-write representation of those
  attributes for human interaction. The toolbar
  exposure is shaped for *immediate-action tempo*;
  the inspector exposure is shaped for *deliberate-
  configuration tempo*; the rendered block body
  exposes attributes as *visible output*; `save`
  exposes them as *serialized form*. One declaration,
  multiple exposures, each shaped to its audience
  and its interaction speed. Naming Law 1 here is
  genuinely clarifying because the existence of two
  control surfaces (toolbar + sidebar) reading the
  same attribute schema is the most direct
  illustration of the declaration / multi-exposure
  pattern in the block-authoring layer.
- **Doctrine 5 (Authority Continuity)** applies
  *moderately*. The block's attributes persist
  across selection state, toolbar mount/unmount,
  sidebar mount/unmount, and serialization. Both
  control surfaces read and write the same persisting
  authority. Worth one mention; not a section.
- **Doctrine 6 (Authority Mediation)** appears
  *softly*, in the same form as
  `<InspectorControls>`: `setAttributes` is the
  canonical write-channel from interactive surfaces
  into attribute state. This is *write-channel
  governance* at the UI level — same softer
  expression as `wp-data-registry`'s and
  `resolver-lifecycle`'s write-channel mediation,
  and as `<InspectorControls>`'s in this same
  bounded context. Worth one mention; not a section.
- **Federation** appears in Section E: multiple
  fills stack in the toolbar, all rendered. Same
  federation shape as `<InspectorControls>`'s and
  the wider JS layer's. Cross-reference; not
  re-elaborated.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit.* Two superficially Law-4-shaped features
  in the toolbar are not arbitration:
  - The group variants (default / block / inline /
    other / parent) are *named-target dispatch*.
    Each group renders in its own toolbar region;
    no group "wins" over another.
  - Multiple fills targeting the same slot are
    *stacked composition*, not *priority
    selection*. All fills render; toolbar space
    constraints are layout concerns, not
    arbitration policy. The framework does not
    decide which fill "wins" the toolbar slot —
    when space runs short, the editor's layout
    moves fills into overflow without dropping
    any.
  - Multi-button `ToolbarGroup` clustering is
    visual grouping, not arbitration over which
    button applies.
  Naming Law 4 here would be the same category
  error as in `<InspectorControls>`. Surface
  vocabulary about "many things, organized" does
  not imply candidate arbitration.
- **"More immediate ≠ more authoritative."** A
  toolbar action and a sidebar control that change
  the same attribute are *equivalent in effect*.
  The toolbar's immediacy is *temporal* (less wait
  between intent and result), not *constitutional*
  (more priority over the sidebar). Both surfaces
  are ordinary attribute writers; the difference is
  interaction shape. Naming this distinction
  explicitly because it is exactly where someone
  inheriting the toolbar code might over-read its
  immediacy as authority.
- **Law 3b (Cross-Runtime Bridge).** All toolbar
  code runs in the editor JS runtime. No runtime
  boundary, no authority preservation across
  contexts. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** Toolbar
  exists entirely at editor-runtime. The build
  pipeline is upstream; not part of this mechanism.
  Omitted.
- **Section X archetypes.** A block toolbar surface
  is not a "civilization." Same framework-omission
  discipline as the surrounding chunks. Omitted.

A small literacy contribution worth pinning:

> *Contextual action ≠ persistent configuration.*
> A surface where the user *takes one-tap actions
> mid-flow* is structurally distinct from a surface
> where the user *sits down to configure*. Both
> surfaces may write to the same underlying schema,
> but their interaction tempos, visual densities,
> and discoverability shapes differ. The two are
> *complementary* — neither subsumes the other —
> and the choice between them for a given control
> is editorial (matched to expected interaction
> rhythm), not hierarchical (matched to importance).

This contribution closes a *4-surface* synthesis
across block-authoring chunks:

> *One declared attribute schema (`block.json`
> attributes) is exposed through four distinct
> authoring-side surfaces, each with its own
> audience and access pattern:*
>
> - ***Interaction** — the `edit` function body
>   (`block.edit-and-save-contracts`).*
> - ***Configuration** — the sidebar
>   (`editor-customization.inspector-controls`).*
> - ***Action** — the toolbar
>   (`editor-customization.block-controls`,
>   this chunk).*
> - ***Persistence** — the `save` function
>   (`block.edit-and-save-contracts`).*
>
> *Each surface reads from the same schema; each
> writes through `setAttributes` (or, in `save`'s
> case, serializes attributes into HTML). Recognizing
> the four-way split prevents a recurring class of
> design mistakes — putting render logic in
> configuration, putting expansive configuration in
> action, putting interaction state in persistence —
> by asking "which surface's job is this?" before
> placing code.*

The four-surface synthesis is *prose-level
literacy*, not a constitutional rule. It does not
appear in `structural-patterns.md`, does not modify
any law or doctrine, and does not require a Q1–Q11
pass to deploy. It exists as a reading lens for
future block-authoring chunks (and for reviewing
block code) that benefit from the same separation
without re-deriving it each time.

## CHECKLIST

When using `<BlockControls>`:

- [ ] Place it inside `edit`'s return, alongside
      the block body and any sibling
      `<InspectorControls>`. A fragment is the
      typical wrapper.
- [ ] Wrap related buttons in `<ToolbarGroup>` for
      visual grouping with separators.
- [ ] Always provide a `label` on each
      `<ToolbarButton>` for accessibility and
      tooltips.
- [ ] Use `isPressed` to reflect the action's
      current state (toggle-like buttons).
- [ ] Choose the right `group` for each control:
      default for primary actions, `inline` for
      RichText-adjacent actions, `other` for
      overflow, `parent` for inner-block-to-parent
      injection.
- [ ] Reserve toolbar buttons for one-tap actions.
      Move expansive forms / text input to
      `<InspectorControls>`.
- [ ] If toolbar real estate is tight, collapse
      multiple actions into a `<ToolbarDropdownMenu>`
      rather than fighting for the main row.
- [ ] Treat fills from other plugins as composition
      partners. Toolbar overflow is the editor's
      layout response to too many fills, not a
      priority decision; design buttons to coexist.

## REFERENCES

- `<BlockControls>` component reference. Group
  variants, prop signature, fill semantics.
  https://developer.wordpress.org/block-editor/reference-guides/components/block-controls/
- BlockControls slot reference. Documents the
  toolbar slot's location and layout response.
  https://developer.wordpress.org/block-editor/reference-guides/slotfills/block-controls/
- `<ToolbarButton>` reference. Documents
  `icon` / `label` / `isPressed` / `isDisabled` /
  `onClick`.
  https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-button/
- `<ToolbarGroup>` reference. Documents grouping
  semantics and visual separators.
  https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-group/
- `<ToolbarDropdownMenu>` reference. The collapsing
  menu pattern used when toolbar space is
  constrained.
  https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-dropdown-menu/

Cross-context:

- `editor-customization.inspector-controls` —
  sister surface (sidebar configuration). Together
  the two cover the user-facing block-control
  surface space.
- `block.edit-and-save-contracts` — the `edit`
  function that hosts `<BlockControls>` fills.
- `block.json-attributes-core` — the attribute
  schema toolbar buttons read from / write into via
  `setAttributes`.
- `block.edit-save-components` — sibling chunk on
  the practical components used inside `edit` and
  `save` (`useBlockProps`, `RichText`,
  `InnerBlocks`, etc.).
- `editor-customization.slotfills` — the underlying
  SlotFill mechanism. `<BlockControls>` is one
  pre-made fill component built on that substrate.
