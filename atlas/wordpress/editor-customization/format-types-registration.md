---
rule_id: editor-customization.format-types-registration
domain: editor-customization
topic: inline-semantic-governance
field_cluster: rich-text-format-substrate
wp_min: "5.0"
wp_recommended: "6.0+"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-rich-text/
    section: "@wordpress/rich-text — registerFormatType, applyFormat, removeFormat, toggleFormat"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/richtext/
    section: "RichText component reference — format toolbar integration"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/how-to-guides/format-api/
    section: "How-to guide — Format API for custom inline formatting"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/rich-text/README.md
    section: "@wordpress/rich-text README — current API surface"
    captured: 2026-05-10
related:
  - block.edit-save-components                  # RichText is the principal component formats integrate with
  - block.block-styles-registration             # adjacent operator-selectable mechanism (block-scope vs selection-scope)
  - block.variations                            # adjacent block-type-extension; different mechanism / different scope
  - editor-customization.block-controls         # the toolbar surface where format buttons typically appear
  - editor-customization.slotfills              # the broader fill mechanism; format toolbar buttons use a fill pattern
---

# RULE — `registerFormatType` — RichText inline semantic format extension

## WHEN

You are adding a custom inline text format to the
WordPress editor — a way to mark text with
semantic meaning (highlight, keyboard input,
abbreviation, custom inline element) that
operators can apply to any selection within a
RichText field. Use this knowledge when:

- Adding a custom format button to RichText
  toolbars (highlight, kbd, abbr, custom marker).
- Diagnosing "my format button doesn't appear"
  — almost always: registered too late,
  registered without `edit` component, or
  toolbar context constraints.
- Implementing a format with custom attributes
  (e.g., a colorable highlight where each
  application stores its own color).
- Choosing between `registerFormatType` (this
  chunk), `registerBlockStyle` (block styles
  chunk), and `registerBlockVariation`
  (variations) — three adjacent extension
  mechanisms with very different scopes.
- Reading core's built-in formats (`core/bold`,
  `core/italic`, `core/link`, etc.) as
  reference implementations.

This chunk does **not** cover:

- The RichText component itself in detail —
  covered indirectly in
  `block.edit-save-components`. This chunk
  focuses on the *format extension* layer
  RichText hosts.
- Block styles (`registerBlockStyle`) — covered
  in `block.block-styles-registration`. Section F
  of this chunk pins the distinction.
- Block variations (`registerBlockVariation`)
  — covered in `block.variations`. Different
  mechanism with different scope.
- The full toolbar mechanism — `<BlockControls>`
  is covered in `editor-customization.block-controls`.

The principle this chunk operates under: **A
registered format type is a *named inline-markup
contract* available to any RichText field. The
operator selects text and applies formats
through the toolbar; multiple formats can apply
to the same selection simultaneously. The
mechanism is *operator-driven compound
composition*, not *exclusive arbitration* — a
selection can be bold AND italic AND
highlighted simultaneously.**

## SHAPE

### A. `registerFormatType` signature and fields

```js
import { registerFormatType } from '@wordpress/rich-text';

registerFormatType( 'myplugin/highlight', {
    title:        __( 'Highlight', 'myplugin' ),
    tagName:      'mark',
    className:    'myplugin-highlight',
    edit:         HighlightFormatButton,
    attributes:   {  // optional
        color: 'style',
    },
    keywords:     [ __( 'mark', 'myplugin' ), __( 'yellow' ) ],
    interactive:  false,
} );
```

The fields:

| Field          | Purpose                                                       |
| -------------- | ------------------------------------------------------------- |
| `name` (1st arg) | Unique format type identifier (`vendor/format-name`)        |
| `title`        | Display label in toolbar / dropdown                            |
| `tagName`      | HTML element the format produces (`mark`, `a`, `kbd`, etc.)   |
| `className`    | CSS class to add to the element                                |
| `edit`         | React component for the toolbar button (typically `RichTextToolbarButton`) |
| `attributes`   | Optional schema for per-application attribute storage          |
| `keywords`     | Search terms for toolbar dropdown discoverability              |
| `interactive`  | Whether the rendered element is interactive (default true for `<a>`-like; false for `<mark>`) |
| `inheritedClassName` | Whether outer formats' classes propagate inward         |

The `name` is global across all RichText
instances — registered formats are available
in every RichText field, including those in
other plugins' blocks.

### B. RichText format application APIs

Three core functions from `@wordpress/rich-text`
modify a RichText value to apply, remove, or
toggle a format on a selection:

```js
import { applyFormat, removeFormat, toggleFormat } from '@wordpress/rich-text';

// Apply: adds the format to the current selection.
const newValue = applyFormat( value, {
    type: 'myplugin/highlight',
    attributes: { color: '#ffeb3b' },
} );

// Remove: removes the format from the current selection.
const newValue = removeFormat( value, 'myplugin/highlight' );

// Toggle: applies if absent, removes if present.
const newValue = toggleFormat( value, {
    type: 'myplugin/highlight',
    attributes: { color: '#ffeb3b' },
} );
```

Three properties to pin:

- **The functions are pure.** They take a value
  + format, return a new value. The original
  value is unchanged. This fits React's
  immutable-data flow.
- **The selection scope is implicit in `value`.**
  The RichText `value` carries the current
  selection range; the apply/remove operations
  act on whatever range is currently selected.
- **Apply ADDS, doesn't REPLACE.** Calling
  `applyFormat` for `myplugin/highlight` on a
  selection that's already bold leaves the
  bold *and* adds the highlight. The selection
  becomes both bold and highlighted.

### C. Compound application — many formats per selection

The compound-formatting property is the
chunk's central conceptual move. A single
character index in a RichText value can carry
*multiple* active formats simultaneously:

```
Text:    "Hello world"
                   ^
                   character index 6 ('w')
                   active formats: bold, italic, highlight
```

The internal representation reflects this — a
RichText value carries:

- `text`: the plain string content.
- `formats`: an array (length = text length),
  where each entry is itself an array of the
  formats active at that character index.

```js
{
    text: "Hello",
    formats: [
        [],                                          // 'H' has no formats
        [ { type: 'core/bold' } ],                   // 'e' is bold
        [ { type: 'core/bold' }, { type: 'myplugin/highlight' } ],  // 'l' is bold + highlight
        [ { type: 'core/bold' }, { type: 'myplugin/highlight' } ],  // 'l' is bold + highlight
        [ { type: 'core/italic' } ],                 // 'o' is italic only
    ],
}
```

This data shape makes compound application
*structurally first-class*. The runtime renders
nested elements that reflect the compound state:

```html
H<strong>e<mark>ll</mark></strong><em>o</em>
```

Different formats can have different ranges;
overlapping ranges produce nested elements
naturally. There is no "which format wins" —
all of them apply.

### D. Toolbar UI integration

The `edit` component for a registered format
typically uses `RichTextToolbarButton` from
`@wordpress/block-editor`:

```js
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { applyFormat, removeFormat } from '@wordpress/rich-text';

const HighlightFormatButton = ( { value, onChange, isActive } ) => {
    return (
        <RichTextToolbarButton
            icon={ /* SVG icon */ }
            title={ __( 'Highlight', 'myplugin' ) }
            onClick={ () => {
                if ( isActive ) {
                    onChange( removeFormat( value, 'myplugin/highlight' ) );
                } else {
                    onChange( applyFormat( value, { type: 'myplugin/highlight' } ) );
                }
            } }
            isActive={ isActive }
        />
    );
};
```

Three props the format's `edit` receives:

| Prop      | Purpose                                          |
| --------- | ------------------------------------------------ |
| `value`   | The current RichText value (text + formats)      |
| `onChange`| Callback to update the value                     |
| `isActive`| Whether the current selection has this format   |

The `RichTextToolbarButton` itself is a fill
that renders into a slot within the
RichText's format toolbar (typically inside
`<BlockControls>`'s inline group, or in a
"More" dropdown when toolbar space is tight).

The toolbar surface federates: every plugin's
`RichTextToolbarButton` fill renders alongside
core's bold/italic/link buttons, in registration
order or via dropdown overflow.

### E. `isActive` semantics for formats

The `isActive` prop is **automatically computed**
by the data layer: it's true when the current
selection has the format applied, false
otherwise.

This is structurally different from
`registerBlockVariation`'s `isActive` callback
(see Section F):

| Mechanism                          | `isActive` shape                                                |
| ---------------------------------- | --------------------------------------------------------------- |
| `registerBlockVariation`           | Author-provided callback `( attributes ) => boolean`            |
| `registerFormatType` toolbar button | Automatic boolean computed from selection's format presence    |

For formats, there's no algorithmic walk to
match candidates. The format is either present
on the selection (boolean: true) or not
(boolean: false). The `isActive` is descriptive,
not arbitrating.

### F. Distinction from block styles and variations

Three adjacent extension mechanisms in block-
authoring-adjacent terrain:

| Aspect            | Format types (this chunk)        | Block styles                 | Block variations              |
| ----------------- | -------------------------------- | ---------------------------- | ----------------------------- |
| Scope             | Text selection within RichText   | Whole block instance         | Whole block instance          |
| Multiple per scope| Yes (compound)                   | No (one style per instance)  | No (one variation matched)    |
| Selection trigger | Operator click on toolbar button | Operator click in inspector  | `isActive` callback (algorithm) |
| Storage           | RichText `formats` array         | `is-style-{name}` className  | Block attributes              |
| Doctrinal Law 4   | Anti (compound composition)       | Anti (operator-selected variant) | Adjacency / fit (algorithmic match) |

Reading the table:

- **Format types** apply to *parts of text
  within a block*; multiple can coexist on
  the same character; user-driven application.
- **Block styles** apply to *the whole block
  instance*; one at a time; user-driven
  selection in inspector.
- **Block variations** apply to *the whole
  block instance*; one matched by `isActive`;
  algorithm-driven detection.

Three mechanisms; three different scopes;
three different selection mechanisms; three
different doctrinal Law 4 profiles. This is
the bounded-context asymmetry observation
worth pinning: *adjacent extension mechanisms
in the same broad terrain can have entirely
different scopes, triggers, and doctrinal
shapes*.

## WHY

### Why compound composition rather than exclusive selection

Inline text formatting is *additive* in
real-world authoring: a highlighted phrase
might also be bold; a link can include
italicized text; a code span might be
strikethrough. The user-mental-model expects
"I can apply this AND that" — not "applying
this removes that."

The compound representation matches this
intuition. The RichText `formats` array
structure makes it cheap to add or remove a
single format without disturbing others;
applying highlight to bold text doesn't
re-think the bold.

The cost is a richer data structure than
"single class per element"; the benefit is
authoring matches expectations.

### Why automatic `isActive` rather than callback-based

For formats, "is the current selection
formatted with this type?" is a
straightforward query against the format
data — the data layer knows whether the
format is present in the selection's range.
A callback would require the format type to
re-compute the same information from
attributes; redundant work.

For variations, "does the current block's
attributes match this variation's preset?"
requires understanding the variation's
intent — which only the variation's
registrant can provide. The callback exists
because the matching question is
domain-specific.

The two `isActive`s have different shapes
because their underlying questions have
different complexity.

### Why format types are global rather than per-block

A format like "highlight" is conceptually
the same regardless of which block contains
the text. Registering once and reusing
across all RichText instances avoids
redundancy.

The cost is no per-block restriction (a
format intended for headings could be
applied in paragraphs too); the benefit is
the registration scales with the format
catalog, not with the block catalog.

For per-block restrictions, individual
RichText instances can override
`allowedFormats` to limit which formats
appear in their toolbar — but this is the
RichText-instance's choice, not the format
registration's.

## WHEN NOT

Skip `registerFormatType` if:

- The styling concern is **block-level**, not
  selection-level. Use block styles
  (`registerBlockStyle`) — the variant applies
  to the whole block, not to text selections.
- The variant should be **a different block
  conceptually**. Use a separate block type or
  a block variation.
- The "format" is actually **a different
  semantic block** (e.g., quote, code block).
  Those are blocks, not inline formats.
- The "format" needs to **transform the entire
  paragraph structure**. Inline formats are
  for character-level markup; structural
  changes belong in block transforms.

## COUNTER-PATTERNS

### Anti-pattern 1 — Treating formats as exclusive

```js
// Mental model: "Applying highlight should clear other formats."
onClick: () => {
    let newValue = removeFormat( value, 'core/bold' );
    newValue = removeFormat( newValue, 'core/italic' );
    newValue = applyFormat( newValue, { type: 'myplugin/highlight' } );
    onChange( newValue );
}
```

Stripping other formats before applying
yours fights the compound-composition design.
The user expects to keep their bold; your
button forcibly removes it. Just apply your
format:

```js
onClick: () => {
    onChange( applyFormat( value, { type: 'myplugin/highlight' } ) );
}
```

### Anti-pattern 2 — Mutating the value object

```js
onClick: () => {
    value.formats[ 5 ] = [ { type: 'myplugin/highlight' } ];  // direct mutation
    onChange( value );
}
```

The format APIs return new values for a
reason — RichText state is immutable. Direct
mutation may not trigger re-renders correctly.
Use `applyFormat` / `removeFormat` /
`toggleFormat`.

### Anti-pattern 3 — Forgetting `tagName` and `className` distinction

```js
registerFormatType( 'myplugin/highlight', {
    title: 'Highlight',
    tagName: 'span',          // generic — loses semantic meaning
    className: 'highlight',    // makes it stylable
    edit: ...,
} );
```

Using `<span>` works mechanically but loses
the semantic value. Prefer specific elements
(`mark`, `kbd`, `abbr`, `cite`, etc.) when a
semantic HTML element exists for the
intended meaning. Reserve `<span>` for cases
where no semantic element fits.

### Anti-pattern 4 — Registering without a `RichTextToolbarButton`

```js
registerFormatType( 'myplugin/highlight', {
    title: 'Highlight',
    tagName: 'mark',
    className: 'myplugin-highlight',
    edit: HighlightFormatButton,  // doesn't render into toolbar
} );

const HighlightFormatButton = () => <button>Highlight</button>;
```

The `edit` component is expected to render
into the format toolbar via
`RichTextToolbarButton` (or another
toolbar-targeting fill). A bare `<button>`
renders nowhere visible in normal toolbar
context — the format is registered but the
button never appears.

### Anti-pattern 5 — Hardcoding format presence assumptions

```js
const HighlightFormatButton = ( { value, isActive, onChange } ) => {
    if ( ! isActive && /* user is in admin mode */ ) {
        // assumption: format not yet present means we should auto-apply
    }
};
```

Don't auto-apply formats. Format application
should be operator-driven. Buttons toggle
based on click; auto-applying violates the
operator-selection paradigm and produces
unpredictable text formatting.

### Anti-pattern 6 — Format with HTML element that's hard to style

```js
registerFormatType( 'myplugin/something', {
    title: 'Something',
    tagName: 'span',
    className: '',  // empty
    edit: ...,
} );
```

Empty `className` plus generic `tagName`
means CSS can't target the format reliably.
Either provide a meaningful `className`, use
a more specific `tagName`, or both.

## OPERATIONAL NOTES

The format types substrate's interpretive
shape, in proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *toolbar-availability vs
  selection-application* form. The format
  type is *declared* via registration; it is
  *exposed* (applied to text) only when the
  operator selects text and clicks the
  toolbar button. Most registered formats are
  never applied to most selections in most
  documents. The toolbar shows what's
  *available*; the document carries what was
  *adopted*. Naming Law 1 here is genuinely
  clarifying because the *gap* between
  "this format is registered" and "this text
  has this format" is the operator-selection
  step — and the gap can be wide (formats
  registered for years without touching a
  single character).
- **Doctrine 5 (Authority Continuity)**
  appears *lightly*. The format type name
  is the identity surface across registration,
  toolbar button, applied format reference,
  and serialized HTML class. Same string
  `'myplugin/highlight'` in registration, in
  the format object stored in `formats`
  array, in the resulting HTML element's
  class. Worth one mention; not a section.
- **Federation** appears in a *user-triggered
  composition* form. Multiple plugins
  register formats into the shared toolbar
  surface; all formats appear in the toolbar
  (or its overflow); the operator composes
  them on text via discrete selection
  actions. This is similar to query_vars'
  *registration-federation + composed-
  singular-output* pattern (Phase 8.41) but
  triggered by user interaction rather than
  automatic. Worth one cross-reference.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit* and the chunk's central doctrinal
  contribution. The mechanism *looks*
  arbitration-shaped at first read — many
  format types, toolbar selection, "active"
  state. But:
  - Multiple formats *can apply
    simultaneously*. There is no "winner."
  - Applying a format **adds** to the
    existing format set; it doesn't
    **discard** previous formats.
  - The `isActive` boolean is *descriptive*
    (does this selection have this format?),
    not *arbitrating* (which format wins?).
  - There is no candidate ladder, no
    first-match-wins, no terminal selection.
  This is *compound composition*, not
  *candidate arbitration*. The phrasing worth
  pinning: **compound application ≠
  candidate arbitration; multiple available
  options applying together is composition,
  not the selection of one over others**.
  Naming Law 4 here would conflate the
  compound mechanism with arbitration shapes
  it doesn't share.
- **Doctrine 6 (Authority Mediation).** No
  access mediation in format registration or
  application. Any RichText field can host
  any registered format unless the field
  explicitly limits via `allowedFormats`.
  Omitted.
- **Law 3b (Cross-Runtime Bridge).** All
  format application runs in the editor JS
  runtime; serialization stores the
  resulting HTML. No cross-runtime authority
  preservation. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** No
  build / runtime split inherent. Omitted.
- **Section X archetypes.** A format
  registration substrate is not a
  "civilization." Same framework-omission
  discipline as the surrounding chunks.
  Omitted.

Two literacy contributions worth pinning:

> *Available format ≠ applied format.* A
> format type that is registered globally is
> not the same as a format applied to a
> particular text selection. Registration
> populates the toolbar (supply); application
> populates the document (adoption). The
> toolbar enumerates available formats
> indiscriminately; the operator selects
> text and clicks; the runtime adds the
> format to the selection's active set.
> Most registered formats are never applied
> to most text — the gap between supply and
> adoption is the dominant case.

This contribution adds another *toolbar-
availability vs selection-application* form
to the existence-vs-operation toolkit. Where
the block styles chunk distinguished
"available style ≠ active style" at the
block-instance level, this one names the
same shape at the *text-selection* level.
Two adjacent mechanisms, both in the
existence-vs-operation family, both
operator-driven, but with different scope
units (block instance vs text selection).

> *Compound application ≠ candidate
> arbitration.* A mechanism that allows
> multiple options to apply to the same
> target simultaneously is not the same as a
> mechanism that selects one option from
> alternatives. Both involve "many available
> things"; only the arbitration mechanism
> *picks one and discards the rest*. Compound
> application keeps every applied option
> active; the data structure carries the set;
> rendering reflects the composition. The
> presence of "many" doesn't make a mechanism
> arbitration; what makes arbitration is
> *terminal selection with discarding*.

This contribution adds the **tenth distinct
example** to the anti-Law-4 inventory:

- *Need fulfillment ≠ option arbitration*
  (resolver lifecycle).
- *Availability ≠ activation* (JIT
  translations) implicit.
- *Formula-driven selection ≠ candidate
  arbitration* (plural forms).
- *Operator-selected ordering ≠ candidate
  arbitration* (list tables).
- *Layer precedence ≠ candidate arbitration*
  (theme.json source layering).
- *Hook priority ≠ candidate arbitration*
  (hooks lifecycle).
- *Scheduled queue ≠ candidate arbitration*
  (cron).
- *Version branch ≠ candidate arbitration*
  (apiVersion).
- *Operator-selected variant ≠ candidate
  arbitration* (block styles).
- *Compound application ≠ candidate
  arbitration* (this chunk, format types).

Ten distinct mechanisms that wear
arbitration's surface vocabulary without
sharing its mechanism. The inventory now
spans cache-lookup, formula-evaluation,
query-parameterization, deterministic-merge,
composition-by-priority, queue-iteration,
switch-by-version, operator-selection-of-one,
and operator-composition-of-many. The
pattern: surface vocabulary about
"selection," "ordering," "priority,"
"availability" tempts a Law 4 reading;
underlying mechanisms in each case lack
*terminal-selection-with-discarding*.

A bounded-context asymmetry observation worth
naming:

> *Within block-authoring-adjacent terrain,
> three extension mechanisms exist with
> different scopes and different doctrinal
> Law 4 fit:* format types (text-selection
> scope, anti-Law-4 compound), *block styles*
> (block-instance scope, anti-Law-4
> operator-selected), *block variations*
> (block-instance scope, Law-4-adjacent
> algorithmic match). Same broad terrain;
> three distinct mechanisms; three distinct
> doctrinal profiles. The asymmetry sharpens
> all three by contrast.

This parallels the within-bounded-context
asymmetry observation from
`block.block-styles-registration` (which
positioned styles vs variations as opposite
fits). This chunk extends the observation to
a *three-mechanism* analysis: format types
join the family.

## CHECKLIST

When using `registerFormatType`:

- [ ] Pick a meaningful `tagName`
      (`mark`, `kbd`, `abbr`, `cite` over
      generic `span` when applicable).
- [ ] Provide a meaningful `className` for
      CSS targeting.
- [ ] Implement `edit` as a component using
      `RichTextToolbarButton` (or another
      toolbar-targeting fill).
- [ ] Use `applyFormat` / `removeFormat` /
      `toggleFormat` from `@wordpress/rich-text`;
      don't mutate `value` directly.
- [ ] Don't strip other formats when applying
      yours; respect compound application.
- [ ] If a format needs per-application
      attributes (color, link target),
      declare them in the `attributes`
      schema.
- [ ] Use namespaced format names
      (`vendor/format-name`); avoid generic
      names that could collide.
- [ ] Don't conflate format types with
      block styles or variations — different
      scopes, different selection mechanisms.

## REFERENCES

- `@wordpress/rich-text` package reference.
  Documents `registerFormatType`,
  `applyFormat`, `removeFormat`,
  `toggleFormat`, `isFormatActive`.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-rich-text/
- RichText component reference. The component
  formats integrate with.
  https://developer.wordpress.org/block-editor/reference-guides/richtext/
- Format API how-to guide. Walks through
  building a custom format type end-to-end.
  https://developer.wordpress.org/block-editor/how-to-guides/format-api/
- `@wordpress/rich-text` README on GitHub.
  Most up-to-date API surface.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/rich-text/README.md

Cross-context:

- `block.edit-save-components` — RichText is
  the principal component formats integrate
  with.
- `block.block-styles-registration` —
  adjacent operator-selected mechanism at
  block-instance scope. Together with this
  chunk and `block.variations`, the three
  form the within-bounded-context asymmetry
  family.
- `block.variations` — adjacent
  block-type-extension mechanism with
  algorithm-matched (`isActive`) candidate
  selection. Different scope, different
  selection mechanism, different doctrinal
  fit.
- `editor-customization.block-controls` —
  the toolbar surface where format buttons
  typically appear (via `RichTextToolbarButton`).
- `editor-customization.slotfills` — the
  broader fill mechanism; format toolbar
  buttons use the same fill pattern at a
  smaller scope.
