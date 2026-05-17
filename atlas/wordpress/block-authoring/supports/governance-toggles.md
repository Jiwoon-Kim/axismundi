---
rule_id: block.supports.governance-toggles
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
    section: "Supports — align, alignWide, anchor, ariaLabel, className, contentRole, customClassName, html, inserter, listView, lock, multiple, renaming, reusable, splitting, visibility"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # several flags inject attributes (align, anchor, ariaLabel, className)
  - block.wrapper-attributes             # render-affecting flags emit through wrapper
  - block.supports.layout                # alignWide and align couple to layout's constrained type
  - block.json-hierarchy-constraints     # adjacent: insertion topology vs governance
  - block.inner-blocks                   # listView and contentRole affect inner-blocks editor UX
  - block.deprecation                    # several flags interact with deprecation behavior
---

# RULE — Governance toggles — editor-affordance + render-affordance flags

## WHEN

Defining a block and you need to **constrain or expose editor-side
behavior** rather than declare a rendering capability. This is a
**capability family distinct from styling families** (color, typography,
spacing, dimensions, shadow, background, filter, layout, position).

Where styling families ask *"What can this block render?"*, governance
toggles ask *"What can the block author / editor user DO with this
block?"* — discoverability, editability, instance lifecycle, identity,
classification.

This chunk batches **16 minor flags** that share a governance
character: most are simple booleans, most have minimal serialization,
and none have theme.json preset systems. The family **bifurcates
internally** into two subtypes (see SHAPE).

## SHAPE

### Subtype taxonomy — internal bifurcation

| Subtype | Members | Hallmark |
|---|---|---|
| **Render-affecting governance** | `align`, `alignWide`, `anchor`, `ariaLabel`, `className`, `customClassName` | Affects rendered markup (wrapper class, id, aria attribute) AND/OR serializes attribute state |
| **Editor-only governance** | `contentRole`, `html`, `inserter`, `listView`, `lock`, `multiple`, `renaming`, `reusable`, `splitting`, `visibility` | Affects editor UI / behavior / inserter only — no rendered output, no attribute injection |

6 of 16 (~37%) are render-affecting; 10 of 16 (~63%) are editor-only.

### Render-affecting subgroup — flag matrix

| Flag | Type | Default | Effect | Stored as |
|---|---|---|---|---|
| `align` | `boolean \| string[]` | `false` | Adds alignment toolbar (left, center, right, wide, full). Array form restricts options. | `align` (string attribute) |
| `alignWide` | `boolean` | `true` | Per-block opt-out of theme's wide-alignment support. Set `false` to remove wide/full options for this block. | (no per-instance attribute — registration-time only) |
| `anchor` | `boolean` | `false` | Adds anchor-id field + copy-link button in the Advanced inspector panel. | `anchor` (string attribute → emits `id` on wrapper) |
| `ariaLabel` | `boolean` | `false` | Enables aria-label definition (NO UI field exposed; programmatic only). | `aria-label` attribute on wrapper |
| `className` | `boolean` | `true` | Auto-adds `wp-block-{namespace}-{slug}` class to the wrapper. Set `false` to suppress. | (auto-emitted, no per-instance attribute) |
| `customClassName` | `boolean` | `true` | Adds "Additional CSS Classes" field in Advanced inspector panel. | `className` (string attribute → appended to wrapper class) |

### Editor-only subgroup — flag matrix

| Flag | Type | Default | Effect | Since |
|---|---|---|---|---|
| `contentRole` | `boolean` | `false` | Marks block as content for content-only editing modes. Inner blocks remain editable. | WP 6.9 |
| `html` | `boolean` | `true` | Allows per-block HTML edit mode. Set `false` to remove. | (original) |
| `inserter` | `boolean` | `true` | Block appears in inserter / transforms menu / Style Book. Set `false` to hide (programmatic insertion only). | (original) |
| `listView` | `boolean` | `false` | Adds inner-blocks List View panel to inspector. **No attributes added** — editor-only. | WP 7.0 |
| `lock` | `boolean` | `true` | Allows the lock toggle UI in block Options. Set `false` to remove the UI (lock state can still be set via attributes). | (original) |
| `multiple` | `boolean` | `true` | Allow multiple instances per post. Set `false` to make the block single-instance (subsequent inserter clicks dimmed). | (original) |
| `renaming` | `boolean` | `true` | Allows the rename UI in block Options / Advanced panel. | WP 6.5 |
| `reusable` | `boolean` | `true` | Allows conversion to reusable block (synced pattern). Set `false` to remove the option. | (original) |
| `splitting` | `boolean` | (no default) | Enter splits the block into two (typical for paragraph/heading). Requires RichText `identifier` prop matching the text attribute key. | (original) |
| `visibility` | `boolean` | `true` | Allows the hide-block UI in block Options. | WP 6.9 |

### Default-value semantics across the family

Many governance flags default to `true` — these are **opt-OUT** flags
(the capability is enabled by default; declaring the flag exists to
DISABLE it). Examples: `alignWide`, `className`, `customClassName`,
`html`, `inserter`, `lock`, `multiple`, `renaming`, `reusable`,
`visibility`.

Others default to `false` — these are **opt-IN** flags (must be
declared to enable). Examples: `align`, `anchor`, `ariaLabel`,
`contentRole`, `listView`.

This default-direction split is itself a sub-pattern within governance.

## REQUIRES

- Block MUST be registered server-side OR client-side (per the
  governance flag's purpose; some govern client-only behavior).
- `align` and `alignWide` consumers SHOULD ensure the block's CSS
  handles the alignment classes (`alignleft`, `aligncenter`,
  `alignright`, `alignwide`, `alignfull`). The supports flag
  declares the editor control; CSS rendering of the alignment is
  the block author's responsibility (or core's, for core blocks).
- `anchor` consumers must spread `useBlockProps()` /
  `useBlockProps.save()` / `get_block_wrapper_attributes()` for the
  generated `id` attribute to reach the wrapper.
- `splitting` consumers MUST add an `identifier` prop to the
  RichText element matching the text attribute key. Source: *"RichText
  in the `edit` function must have an `identifier` prop that matches
  the attribute key of the text, so that it updates the selection
  correctly and we know where to split."*
- `listView`, `contentRole` consumers SHOULD have inner blocks for the
  flag to be meaningful (both relate to inner-blocks editor UX).
- `customClassName` writes to the `className` attribute — block's
  `save()` should NOT manually emit `className` if relying on the
  wrapper hook (which already merges `className` from this attribute).

## INVARIANTS

### Editor effects

The dominant H3 section for governance — most flags exist to
control editor UI exposure or block lifecycle.

**Discoverability cluster:**
- `inserter: false` removes block from inserter / transforms / Style
  Book. The block can still be inserted programmatically (via patterns,
  templates, or `wp:insertBlock` action dispatch).
- `visibility: false` removes the hide-block UI option.
- `listView: true` adds an inner-blocks tree panel to the inspector
  (parallel to the global document list view).

**Mutability / lifecycle cluster:**
- `lock: false` removes the lock UI from block Options. Lock state
  can still be set programmatically via the block's `lock` attribute
  (per `block.json-hierarchy-constraints` / template locking).
- `renaming: false` removes the rename UI.
- `reusable: false` removes the "Convert to reusable block" option.
- `multiple: false` makes the block single-instance per post; the
  inserter dims the icon when one already exists.
- `html: false` removes the per-block HTML edit mode.
- `splitting: true` makes Enter split the block into two — typically
  used for text blocks (paragraph, heading).

**Identity / accessibility cluster:**
- `anchor: true` adds an anchor-id input + copy-link button.
- `ariaLabel: true` enables programmatic aria-label setting (no UI
  exposed).
- `contentRole: true` marks the block as content for content-only
  editing modes (the block participates in restricted-edit contexts).

**Alignment cluster:**
- `align: true` exposes the alignment toolbar (5 options); array form
  restricts to a subset.
- `alignWide: false` opts out of theme's wide-alignment offer (typically
  affects whether "wide" / "full" appear in the align toolbar).

**Class cluster:**
- `customClassName: true` (default) shows the "Additional CSS Classes"
  field in the Advanced inspector.
- `className: false` suppresses the auto-generated
  `wp-block-{namespace-and-slug}` class (rare — usually you want the
  class for theme styling hooks).

### Attribute effects

Render-affecting subgroup injects the following attributes:

| Flag | Attribute(s) added |
|---|---|
| `align` | `align` (string — alignment slug) |
| `anchor` | `anchor` (string — used to emit `id` on wrapper) |
| `ariaLabel` | (aria-label attribute, programmatic) |
| `customClassName` | `className` (string — appended to wrapper class) |

Editor-only subgroup adds **NO attributes**. Their state is
registration-time configuration only — `lock`, `multiple`, `inserter`
etc. don't change per block instance.

Two exceptions worth noting:
- `lock` flag controls the lock-UI exposure, but a separate `lock`
  attribute (per-instance) IS used by the block locking API to
  actually lock/unlock instances. The supports flag and the attribute
  share a name but are different concepts. See
  `block.json-hierarchy-constraints` for individual block locking.
- `splitting` doesn't add an attribute itself but REQUIRES that the
  RichText `identifier` prop be set in `edit()` — the supports flag
  is paired with a runtime requirement.

### Wrapper effects

Render-affecting subgroup emits to the wrapper:

- `align` → wrapper class `align{slug}` (e.g., `alignwide`,
  `alignfull`, `alignleft`).
- `anchor` → wrapper `id={user-set-string}`.
- `ariaLabel` → wrapper `aria-label={value}`.
- `className` (default true) → wrapper class
  `wp-block-{namespace-and-slug}` (auto-emitted by useBlockProps).
- `customClassName` → user-entered classes appended to wrapper class
  string.

Editor-only subgroup emits **NOTHING to the wrapper**. The execution
surface for these flags is the editor UI, not the rendered DOM.

All wrapper emission flows through `useBlockProps()` /
`useBlockProps.save()` / `get_block_wrapper_attributes()` per
`block.wrapper-attributes`.

### Serialization effects

Render-affecting subgroup serializes attribute values into the block
delimiter:

```html
<!-- wp:my-plugin/foo {"align":"wide","anchor":"custom-id","className":"extra-class"} -->
```

Editor-only subgroup serializes **NOTHING**. The flags are static
configuration declared at block registration; there is no per-instance
state to round-trip.

### theme.json interaction

- `align` / `alignWide` couple to layout's constrained type via
  theme.json `settings.layout.contentSize` and
  `settings.layout.wideSize` (which define what "wide" and "full"
  resolve to).
- ⚠ Most other governance flags have **no documented theme.json
  interaction**. They are pure block.json registration concerns.
  The split between "theme-mediated capabilities" (color, typography,
  spacing, etc.) and "block-author-only governance" (lock, inserter,
  etc.) is sharp here.

### General invariants

- **First "Capabilities without rendering" family.** Editor-only
  governance flags have execution surface = EDITOR. There is no
  front-end emission, no serialization beyond static configuration,
  no theme.json coupling. The supports cascade ends at the editor's
  UI / behavior layer.
- **Default-direction sub-pattern:** opt-OUT flags (most editor-only
  + alignWide / className / customClassName) vs opt-IN flags (align,
  anchor, ariaLabel, contentRole, listView, splitting). Authors
  must check default-direction per flag — declaring `lock: true`
  is a no-op (already default); declaring `lock: false` is the
  meaningful action.
- **align is a layout satellite.** Despite being categorized as
  governance here, `align` and `alignWide` couple to layout's
  constrained-type semantics. The wide/full alignment values map to
  theme.json `settings.layout` widths. align could equally be
  classified as "composition" family — it sits at a boundary.
- **lock is doubly defined.** The supports flag `lock` controls
  whether the lock UI is shown; a separate per-instance `lock`
  attribute (not a supports flag) controls actual lock state.
  Same name, different concepts.
- **className suppression is rare.** Setting `className: false` is
  usually a mistake — the auto-class is the hook themes use to
  style blocks. Suppress only if the block is intentionally
  unstyled / used as raw markup.
- **`splitting` is the only governance flag with a runtime
  requirement** (RichText `identifier` prop). Most are pure
  declarations; splitting demands paired `edit()` code.
- ⚠ **Per-flag minimum WP versions** range across original Block API
  (most), WP 6.5 (`renaming`), WP 6.9 (`contentRole`, `visibility`),
  and WP 7.0 (`listView`). A block declaring multiple flags needs
  the highest declared version. Frontmatter `wp_min` is
  `"verification-needed"` because many flags pre-date documented
  `Since:` markers.
- **Subgroup ontology distinction is real but the chunk holds.**
  Render-affecting and editor-only subgroups DO use different
  vocabulary (markup/wrapper/serialization vs editor UI/inserter/
  lifecycle), but they share enough family pattern (boolean flags,
  default-direction split, no theme.json presets, registration-time
  configuration) that single-chunk treatment captures the family
  archetype better than physical split.

## ANTIPATTERNS

- ❌ Setting opt-OUT flags to their default value
  (`lock: true`, `inserter: true`, etc.). No-op — these are already
  default. Wastes registration code.
- ❌ Setting `className: false` without a clear reason. Removes
  the theme styling hook that core blocks rely on. Most blocks
  should keep the auto-class.
- ❌ Confusing the `lock` supports flag with the per-instance `lock`
  attribute. The supports flag controls UI exposure; the attribute
  controls actual lock state. See `block.json-hierarchy-constraints`
  for the locking API.
- ❌ Declaring `splitting: true` without paired RichText `identifier`
  prop in `edit()`. Splitting won't work; user hits Enter and gets
  unexpected behavior.
- ❌ Setting `align` array with non-standard values
  (`align: ["custom-align"]`). Documented values are `left`,
  `center`, `right`, `wide`, `full`. Custom values may not produce
  toolbar buttons or class emission.
- ❌ Treating `multiple: false` as an "instance limit" of N>1. The
  flag is a binary "single-instance" toggle; there is no support for
  arbitrary instance counts.
- ❌ Using `inserter: false` for blocks that should be hidden from
  end users but inserted via patterns. Verify the block is reachable
  via the intended insertion path (pattern, template, or
  programmatic) before shipping.
- ❌ Setting `anchor: true` for a block whose markup doesn't pair
  with `useBlockProps`. The user-entered anchor value won't reach
  the wrapper as `id`.
- ❌ Setting `ariaLabel: true` and expecting a UI field. The flag
  enables programmatic aria-label only — no field is exposed in
  the inspector. Use this when the block sets aria-label via its
  own controls or programmatic logic.
- ❌ Setting `contentRole: true` on a block that's not actually
  content (e.g., a container with no inner blocks and no text). The
  flag is meant for blocks that ARE content in content-only editing
  modes; misuse confuses the editor's restricted-edit logic.
- ❌ Setting `listView: true` on a block without inner blocks. The
  panel renders empty.
- ❌ Setting `reusable: false` on blocks that users would naturally
  want to reuse (frequently-customized templates, custom design
  elements). Friction without strong reason.
- ❌ Setting `visibility: false` on blocks that users might want to
  conditionally show/hide. Removes a useful editor affordance.
- ❌ Hardcoding alignment classes in `save()` instead of letting
  `align` supports inject them. User changes to alignment will not
  reach the markup.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all supports flags — without the wrapper hook,
  `align`, `anchor`, `ariaLabel`, `className`, `customClassName`
  emissions are lost.

## RELATED

- `block.json-supports-field` — parent rule explaining the supports
  mechanism in general; this batch chunk covers the governance family.
- `block.json-attributes-core` — render-affecting flags inject
  attributes (`align`, `anchor`, `className`); editor-only flags do
  not.
- `block.wrapper-attributes` — render-affecting flags emit through
  `useBlockProps()` / `get_block_wrapper_attributes()`. Editor-only
  flags have no wrapper coupling.
- `block.supports.layout` — `align` and `alignWide` couple to
  layout's constrained-type widths (contentSize, wideSize). align
  is effectively a layout satellite.
- `block.json-hierarchy-constraints` — adjacent composition
  governance: insertion-time constraints. Per-instance `lock`
  attribute (separate from this chunk's `lock` supports flag) is
  documented there.
- `block.inner-blocks` — `listView` and `contentRole` affect inner-
  blocks editor UX; `multiple` and `inserter` affect insertion
  topology overall.
- `block.deprecation` — schema changes affecting governance flags
  (e.g., changing `align` array values across versions) may need
  deprecation entries.
