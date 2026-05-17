---
rule_id: block.block-styles-registration
domain: block-authoring
topic: operator-selectable-presentation
field_cluster: block-style-variant-substrate
wp_min: "5.3"
wp_recommended: "6.0+"
status: stable
language: js-and-php
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-styles/
    section: "Block Styles — registration, is-style-{name} class, isDefault"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/register_block_style/
    section: "register_block_style() — PHP-side registration"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#styles
    section: "block.json styles array — third registration path"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
    section: "Block Variations — isActive callback (different mechanism, often confused with styles)"
    captured: 2026-05-10
related:
  - block.variations                              # adjacent identity-projection mechanism with isActive matching (Law 4 territory)
  - style-engine.per-block-style-attribution      # how block-instance attributes become CSS; styles add a class layer to that
  - block.wrapper-attributes                     # is-style-{name} ends up on the wrapper via useBlockProps
  - block.json-basic-metadata                    # block.json styles[] is a basic-metadata field
---

# RULE — `registerBlockStyle` — operator-selectable presentation variants per block type

## WHEN

You are adding alternate visual styles to a block
type so that operators can pick between them on a
per-instance basis (Default / Rounded / Outlined /
etc.). Use this knowledge when:

- Adding visual variants for a custom block where
  the difference is purely stylistic (CSS class
  toggling), not attribute-driven.
- Extending a core block (`core/button`,
  `core/quote`, `core/image`) with custom style
  variants from a plugin or theme.
- Choosing between `registerBlockStyle` (this
  chunk) and `registerBlockVariation` (variations
  chunk) — the two are easy to confuse.
- Diagnosing "my style isn't appearing in the
  picker" — almost always: registered too late,
  registered wrong block name, or CSS for the
  variant not enqueued.

This chunk does **not** cover:

- The block variations mechanism
  (`registerBlockVariation`) — covered in
  `block.variations`. Variations are
  *attribute-preset projections* of a block
  type with `isActive` callbacks for matching;
  styles are *visual variants* with no
  `isActive` mechanism. Section F documents
  the boundary explicitly.
- The wrapper-attribute system that ultimately
  emits the `is-style-{name}` class — covered
  in `block.wrapper-attributes` and
  `style-engine.per-block-style-attribution`.
  This chunk focuses on the *registration* of
  styles; that chunk focuses on the *attribute-
  to-CSS-class mechanism*.
- Theme-level visual customization beyond block
  styles (CSS overrides, theme.json styling).
  Block styles are an opt-in registration
  mechanism for *block-type-specific* style
  variants.

The principle this chunk operates under: **A
registered block style is a *named CSS class
contract* for one block type. The operator
selects which style applies to a given instance
through the editor's style picker UI; the
runtime emits the corresponding `is-style-{name}`
class on the wrapper. The mechanism is
*operator-driven selection*, not *algorithmic
candidate matching* — there is no `isActive`
callback, no first-match-wins logic, no
arbitration. Available styles are a menu;
selection is a click.**

## SHAPE

### A. Three registration paths

WordPress accepts block style registration
through three equivalent paths:

**Path 1 — JS via `wp.blocks.registerBlockStyle`:**

```js
wp.blocks.registerBlockStyle( 'core/button', {
    name: 'rounded',
    label: __( 'Rounded', 'my-plugin' ),
} );
```

The most common path. Runs in the editor
runtime. Fires from any plugin's editor script
that loads after `@wordpress/blocks` is
available.

**Path 2 — PHP via `register_block_style`:**

```php
register_block_style( 'core/button', array(
    'name'  => 'rounded',
    'label' => __( 'Rounded', 'my-plugin' ),
) );
```

PHP-side registration. Useful for plugins that
want to register styles without shipping editor
JS, or for themes registering styles in
`functions.php`.

**Path 3 — `block.json` `styles` array:**

```json
{
    "name": "myplugin/my-block",
    "styles": [
        { "name": "default", "label": "Default", "isDefault": true },
        { "name": "rounded", "label": "Rounded" },
        { "name": "outlined", "label": "Outlined" }
    ]
}
```

Declarative registration as part of the block's
own metadata. Useful when the block author
ships its own style variants alongside the block
itself.

The three paths produce equivalent results: the
style is registered for the block type and
appears in the inspector's style picker.

### B. The `is-style-{name}` class mechanism

When an operator selects a style for a block
instance, the runtime adds the class
`is-style-{name}` to the block's wrapper
element:

```html
<!-- Default style selected -->
<div class="wp-block-myplugin-my-block">…</div>

<!-- 'rounded' style selected -->
<div class="wp-block-myplugin-my-block is-style-rounded">…</div>

<!-- 'outlined' style selected -->
<div class="wp-block-myplugin-my-block is-style-outlined">…</div>
```

The class is the bridge from the operator's
choice to the actual visual outcome. The plugin
or theme provides the matching CSS:

```css
.wp-block-myplugin-my-block.is-style-rounded {
    border-radius: 8px;
    overflow: hidden;
}

.wp-block-myplugin-my-block.is-style-outlined {
    border: 2px solid currentColor;
    background: transparent;
}
```

Two properties to pin:

- **The class is added to `className`
  attribute**, which `useBlockProps()` /
  `useBlockProps.save()` /
  `get_block_wrapper_attributes()` spread onto
  the wrapper. Same wrapper-prop infrastructure
  as the rest of block styling (covered in
  `style-engine.per-block-style-attribution`).
- **CSS enqueue is the registrant's
  responsibility.** Registering a style does
  *not* enqueue any CSS automatically. The
  plugin/theme must enqueue the stylesheet
  containing the `.is-style-{name}` rules. The
  fourth path-2 argument
  (`'inline_style' => '...'`) lets registrants
  inline the CSS at registration time, but
  external stylesheets are equally common.

### C. `isDefault` and the "default style" concept

One style per block can be marked `isDefault`
(or `is_default` in PHP). The default style
applies when no other style is selected:

```js
wp.blocks.registerBlockStyle( 'core/button', {
    name: 'default',
    label: __( 'Default', 'my-plugin' ),
    isDefault: true,
} );

wp.blocks.registerBlockStyle( 'core/button', {
    name: 'pill',
    label: __( 'Pill', 'my-plugin' ),
} );
```

Three properties to pin:

- **The "default" is a UI selection state**, not
  an algorithmic match. When the operator
  hasn't actively chosen a style, the picker
  shows the `isDefault` style as selected.
- **The default doesn't add a class.** A block
  with no style explicitly selected (or with
  the `isDefault` style selected) typically has
  no `is-style-{name}` class on its wrapper.
  The default style's CSS lives in the block's
  base class rules.
- **At most one default per block type.** If
  multiple styles are registered with
  `isDefault: true`, the last registration wins
  (no arbitration, just last-write-wins on the
  flag).

### D. Operator selection in the inspector

The style picker appears in the block's
inspector sidebar (or as a dedicated panel,
depending on theme conventions). It lists all
registered styles for the block type as
clickable thumbnails with labels.

The selection flow:

```
1. Operator selects block in editor.
2. Inspector shows "Styles" panel listing all
   registered styles for this block type.
3. Operator clicks a style.
4. Editor adds 'is-style-{name}' to the block's
   className attribute (replacing any existing
   is-style-* class).
5. Block re-renders with the new class.
6. CSS rules matching the new class apply.
```

The flow is *operator-driven*. No algorithm
walks candidates; no `isActive` matches against
attributes; no first-match-wins. The operator
sees options, picks one, and that's the
outcome.

The contrast with variations (Section F) is
exactly this: variations *match* candidates to
attributes via `isActive`; styles *display*
candidates and let the operator choose.

### E. Style unregistration and removal

To remove a registered style (e.g., to remove
core's "Rounded" from `core/button`):

```js
wp.blocks.unregisterBlockStyle( 'core/button', 'rounded' );
```

```php
unregister_block_style( 'core/button', 'rounded' );
```

Two notes:

- **Unregistration must run after registration.**
  Hook timing matters: register on `init`,
  unregister on a later hook (or on init with
  later priority).
- **Existing instances retain the class.** A
  block that was previously saved with
  `is-style-rounded` keeps that class in its
  serialized HTML even if the style is later
  unregistered. The block won't show "Rounded"
  in the picker, but the class persists in
  storage.

### F. Distinction from variations — the chunk's central clarification

Block styles and block variations are different
mechanisms that are easy to confuse:

| Aspect                | Block styles (`registerBlockStyle`)   | Block variations (`registerBlockVariation`)            |
| --------------------- | ------------------------------------- | ----------------------------------------------------- |
| What varies           | CSS class on wrapper (`is-style-*`)    | Attribute presets, optionally innerBlocks             |
| Selection mechanism   | Operator clicks in inspector picker    | `isActive` callback matches against attributes        |
| Multiple per instance | One active style per block             | One variation matches at a time (per `isActive`)      |
| Inserter behavior     | Doesn't add inserter items             | Can add inserter items (with `scope: ['inserter']`)   |
| Doctrinal shape       | Operator-selected (anti-Law-4)         | Algorithm-matched candidate (Law 4 adjacency / fit)   |

The distinction matters because:

- A new block author asking "should I use
  styles or variations?" needs to know the
  difference.
- The cross-cutting question "is this Law 4
  arbitration?" has *opposite* answers for the
  two: variations have `isActive` ladders
  (arbitration territory); styles have
  operator-selection (composition / anti-
  arbitration).

The chunk pair: this chunk owns *operator-
selected presentation*; `block.variations`
owns *algorithmic candidate matching*. Two
adjacent mechanisms, two different doctrinal
shapes.

## WHY

### Why a separate styles mechanism rather than just CSS

Without `registerBlockStyle`, a theme or plugin
could still ship CSS targeting `.wp-block-X` and
expect operators to add classes manually via the
"Additional CSS Class(es)" field. The mechanism
exists because:

- **Discoverability.** A registered style
  appears in the inspector picker, visible to
  every operator. Manual className addition
  requires the operator to know what classes
  to type.
- **Consistency.** Plugin-shipped style options
  appear in the same UI surface as core style
  options. Operators don't need to know
  whether a particular style came from core
  or a plugin.
- **Removability.** `unregisterBlockStyle`
  allows precise control over what styles
  appear for an operator. CSS-only approaches
  can't be unregistered — they're either in
  the stylesheet or not.
- **Discoverable enumeration.** Tools and
  meta-plugins can `getBlockStyles( 'core/button' )`
  to enumerate available styles for a block
  programmatically.

The cost is one more registration API; the
benefit is style options become first-class
operator-facing UI rather than implicit class
conventions.

### Why no `isActive` for styles

Styles describe *visual presentation choice*,
not *attribute-derived state*. The choice is a
click; the attribute is the result. There is
nothing to "match" — the operator's click is
the truth.

Variations are different: a variation can
become "active" because the block's attributes
happen to match the variation's preset (e.g.,
the operator manually changed an attribute to
the value the variation declares). The
`isActive` callback exists to detect this
matching state. Styles have no such matching
state to detect.

The asymmetry is intentional and meaningful:
*operator-driven* mechanisms don't need
matching; *attribute-derived* mechanisms do.

### Why `isDefault` is a UI signal, not a structural commitment

Marking a style `isDefault` means "show this as
selected in the picker when no other style is
explicitly chosen." It does *not* mean "if
nothing matches, fall back to this." The
default style is an operator-facing affordance,
not a fallback ladder.

This is consistent with the broader operator-
selected framing: there's no algorithmic
fallback because there's nothing to fall back
*from*. Either the operator explicitly chose a
style, or they didn't and the default
displays.

## WHEN NOT

Skip `registerBlockStyle` if:

- **The visual variant is attribute-driven.**
  If the difference between "states" is what
  attributes the block has, that's variations
  territory or just attribute-driven CSS, not
  styles.
- **There's only one visual treatment.** A
  block with only one look needs no styles
  registration. Just style the block with
  `.wp-block-X` rules in your stylesheet.
- **The operator should pick from inserter
  items.** That's variations with
  `scope: ['inserter']`, not styles. Styles
  apply *after* a block is already inserted.
- **The variation is conceptually a different
  block.** If "Outlined Button" and "Filled
  Button" feel like different blocks (different
  use cases, different inspector affordances),
  consider two separate block types or
  variations rather than styles.

## COUNTER-PATTERNS

### Anti-pattern 1 — Confusing styles with variations

```js
wp.blocks.registerBlockStyle( 'core/button', {
    name: 'cta',
    label: 'CTA',
    attributes: { backgroundColor: 'primary' },  // styles don't accept attributes
} );
```

Block styles only accept `name`, `label`,
`isDefault`, and (in some paths) `inline_style`.
Setting `attributes` on a style does nothing.
What you probably want is variations:

```js
wp.blocks.registerBlockVariation( 'core/button', {
    name: 'cta',
    title: 'CTA',
    attributes: { backgroundColor: 'primary' },
    isActive: [ 'backgroundColor' ],
} );
```

### Anti-pattern 2 — Registering style without enqueuing CSS

```js
wp.blocks.registerBlockStyle( 'core/button', {
    name: 'rounded',
    label: 'Rounded',
} );
// No CSS rules for .is-style-rounded ever ship.
```

The style appears in the picker, the operator
selects it, the class is added — but visually
nothing changes because no CSS targets the
class. Either enqueue a stylesheet:

```php
wp_enqueue_style( 'myplugin-styles',
    plugins_url( 'styles.css', __FILE__ ) );
```

…with `.is-style-rounded` rules inside, or
inline the CSS at registration time (PHP path
supports this via `'inline_style' => '...'`).

### Anti-pattern 3 — Registering on too-late hook

```js
// In a script that loads on every admin page:
wp.blocks.registerBlockStyle( 'core/button', { name: 'rounded', label: 'Rounded' } );
```

If the script runs after the editor has
already enumerated styles, the registration may
not appear in the picker for the current
editor session. Register early:

```js
import { domReady } from '@wordpress/dom-ready';

domReady( () => {
    wp.blocks.registerBlockStyle( 'core/button', { name: 'rounded', label: 'Rounded' } );
} );
```

…or for PHP, on `init`:

```php
add_action( 'init', function() {
    register_block_style( 'core/button', array( 'name' => 'rounded', 'label' => __( 'Rounded' ) ) );
} );
```

### Anti-pattern 4 — Multiple `isDefault` styles for the same block

```js
wp.blocks.registerBlockStyle( 'core/button', { name: 'a', label: 'A', isDefault: true } );
wp.blocks.registerBlockStyle( 'core/button', { name: 'b', label: 'B', isDefault: true } );
// Only one is treated as the default; the second registration's flag wins.
```

Pick one default per block type. Marking
multiple as default is silently a single
default (the last one), which is rarely the
intent.

### Anti-pattern 5 — Treating `isDefault` as fallback semantics

```js
// Author thinks: "If no isActive matches in some other plugin's variations, fall back to my style."
```

`isDefault` is a UI selection state for
*styles*, not a fallback for *variations*.
Variations have their own selection mechanism
(`isActive`). Styles and variations don't
interact in fallback ways.

### Anti-pattern 6 — Unregistering styles before they're registered

```php
add_action( 'init', function() {
    unregister_block_style( 'core/button', 'rounded' );
}, 1 );  // priority 1, before core registers
```

Unregistration runs before the registration it's
trying to remove. The registration then happens
normally; the style appears anyway. Run
unregistration on a later priority:

```php
add_action( 'init', function() {
    unregister_block_style( 'core/button', 'rounded' );
}, 99 );  // after core's registrations
```

## OPERATIONAL NOTES

The styles substrate's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in an *available-vs-active*
  form. The style is *declared* (registered)
  at the block-type level; it is *exposed*
  (active on a block instance) only when the
  operator selects it. Many registered styles
  are never selected on any given block
  instance. The class
  (`is-style-rounded`) on a wrapper is the
  exposure; the registration is the
  availability. Naming Law 1 here is
  genuinely clarifying because the *gap*
  between "this block type has this style
  available" and "this specific block instance
  is using this style" is exactly the
  operator-selection step.
- **Doctrine 5 (Authority Continuity)**
  appears *lightly*. The style name is the
  identity surface across registration,
  selection, class generation, and CSS
  matching. Same string `'rounded'` in
  registration, in `is-style-rounded` class,
  in `.is-style-rounded {}` selector. Worth
  one mention; not a section.
- **Federation** appears *lightly*. Multiple
  plugins / themes can register styles for
  the same block type; they all appear in
  the picker; operator picks among them.
  This is structured-placement federation
  (Phase 8.39 dashboard widget pattern):
  open registration, all participants
  visible, operator chooses one to apply per
  instance. Worth one cross-reference.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit* — and the chunk's central
  doctrinal clarification. The styles
  mechanism *looks* arbitration-shaped on
  superficial reading: multiple candidates
  (registered styles), one selected. But:
  - Selection is **operator-driven**, not
    algorithm-driven. No code walks
    candidates looking for a match.
  - There is **no `isActive`** callback in
    styles. The selection is a click, not a
    computation.
  - **No discarding semantics** — when one
    style is selected, the others remain
    registered and available for the
    operator to switch to at any time.
  This is *operator-selected variant*, not
  *candidate arbitration*. The mechanism that
  *is* candidate arbitration in the
  block-style-adjacent terrain is
  `registerBlockVariation`'s `isActive`
  callback (covered in `block.variations`).
  Naming Law 4 here would conflate the two
  mechanisms; not naming it makes the
  available-vs-active distinction crisp.
- **Doctrine 6 (Authority Mediation).** No
  access mediation in style registration or
  selection. Any block can have any style
  applied. Omitted.
- **Law 3b (Cross-Runtime Bridge).** All
  selection runs in the editor JS runtime;
  serialization stores the resulting class.
  No cross-runtime authority preservation.
  Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** No
  build / runtime split inherent. Omitted.
- **Section X archetypes.** A style
  registration substrate is not a
  "civilization." Same framework-omission
  discipline as the surrounding chunks.
  Omitted.

Two literacy contributions worth pinning:

> *Available style ≠ active style.* A style
> that is registered for a block type is not
> the same as a style applied to a particular
> block instance. Registration is *supply*
> (the picker has this option); selection is
> *adoption* (this instance uses this option).
> The picker enumerates registered styles
> indiscriminately; the operator selects one
> per block instance; the runtime emits the
> matching class. Registration without
> adoption is the dominant case — most
> registered styles are never selected on
> most block instances.

This contribution adds a *operator-selected
variant* form to the existence-vs-operation
toolkit. Where prior toolkit members
distinguished mechanisms triggered by events,
state changes, or lifecycle, this one names
the shape when *human selection* is the
trigger.

> *Operator-selected variant ≠ candidate
> arbitration.* A mechanism that displays
> options to a human and applies the human's
> choice is not the same as a mechanism that
> walks ordered candidates and selects the
> first match algorithmically. Both involve
> "choosing among alternatives," but
> operator-selection's choice is *user input*
> (a click); arbitration's choice is *code
> output* (a function returning truth).
> Different shapes; different mechanisms;
> different doctrinal fit. The presence of
> "multiple registered things" doesn't make a
> mechanism arbitration; what makes
> arbitration is the *algorithmic walk + first
> match wins + discarded candidates* shape.

This contribution adds a **ninth distinct
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
  arbitration* (this chunk, block styles).

Nine distinct mechanisms that wear
arbitration's surface vocabulary without
sharing its mechanism. The pattern continues
to demonstrate the false-analogy resistance
the audit (Phase 8.M1) noted.

The chunk also clarifies an **adjacency
distinction** worth naming:

> *Within block-authoring, styles
> (operator-selected) and variations
> (algorithm-matched) sit adjacent but have
> opposite doctrinal Law 4 fit.* Styles fall
> in the anti-Law-4 inventory; variations
> (covered in `block.variations`) sit closer
> to Law 4 territory because their `isActive`
> callback walks candidates against attributes.
> Two block-type-extension mechanisms; same
> bounded context; different mechanisms;
> different fit. Recognizing the asymmetry
> sharpens both: styles aren't arbitration
> because there's no algorithmic walk;
> variations may be arbitration because their
> `isActive` is exactly that.

This is the chunk's central clarification —
positioning two adjacent mechanisms with
opposite doctrinal profiles, and explaining
*why* the difference is structural rather
than incidental.

## CHECKLIST

When using `registerBlockStyle`:

- [ ] Pick the right path — JS, PHP, or
      `block.json` `styles[]` — based on where
      your code naturally lives. All three
      produce equivalent results.
- [ ] Register early (`init` for PHP;
      `domReady` or editor script init for JS).
      Late registration may not appear.
- [ ] Always provide CSS rules for
      `.is-style-{name}` either via enqueued
      stylesheet or inline_style. Registration
      alone does nothing visible.
- [ ] Mark at most one style per block type
      `isDefault: true`. Multiple defaults
      silently last-wins.
- [ ] Don't confuse styles with variations.
      Styles change CSS class; variations
      change attributes / inserter items /
      have `isActive`.
- [ ] When unregistering, run on a hook
      priority *after* the registration you're
      trying to remove.
- [ ] If the operator-facing UI matters,
      provide thoughtful `label` strings
      (translatable, short, descriptive).

## REFERENCES

- Block Styles handbook reference. Documents
  the three registration paths, isDefault,
  is-style-{name} class.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-styles/
- `register_block_style()` PHP reference.
  Documents the PHP-side argument shape,
  including inline_style.
  https://developer.wordpress.org/reference/functions/register_block_style/
- block.json `styles` field. The third
  registration path as part of metadata.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#styles
- Block Variations handbook reference. The
  *adjacent* mechanism with `isActive` —
  often confused with styles. Section F of
  this chunk pins the distinction.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/

Cross-context:

- `block.variations` — the adjacent
  attribute-preset mechanism with `isActive`
  callback. Together this chunk and that one
  cover *operator-selected* and
  *algorithm-matched* variant mechanisms in
  block-authoring; the doctrinal Law 4 fit
  is opposite for the two.
- `style-engine.per-block-style-attribution`
  — how the block-instance attribute
  (`className` containing `is-style-{name}`)
  becomes the wrapper class. This chunk
  registers the styles; that chunk
  documents how the resulting class becomes
  CSS application.
- `block.wrapper-attributes` — `useBlockProps`
  / `useBlockProps.save()`'s wrapper shape;
  the `is-style-{name}` class flows through
  this infrastructure.
- `block.json-basic-metadata` — block.json
  `styles[]` is one of the basic-metadata
  array fields.
