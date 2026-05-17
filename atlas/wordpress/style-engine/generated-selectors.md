---
rule_id: style-engine.generated-selectors
domain: style-engine
topic: runtime-synthesis
field_cluster: selector-graph
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: css
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-style-engine/
    section: "@wordpress/style-engine — runtime style synthesis package"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/news/2024/01/styling-blocks-with-the-style-engine/
    section: "Style engine overview — runtime CSS generation"
    captured: 2026-05-09
related:
  - block-authoring.block-json.selectors           # declaration counterpart — the synthesis pair
  - block-authoring.supports.layout                # primary .wp-container-* trigger
  - block-authoring.supports.spacing               # blockGap → container synthesis
  - theme-config.styles.css                        # escape hatch this chunk explains
  - theme-config.styles                            # styles.elements → .wp-elements-* trigger
  - block-authoring.markup-representation          # wrapper element receives generated classes
---

# RULE — generated selectors (`.wp-container-*` / `.wp-elements-*`) — runtime topology synthesis

## WHEN

Reading rendered Gutenberg DOM and encountering classes the theme
never authored — `.wp-container-{n}` on a layout wrapper,
`.wp-elements-{uuid}` scoping element styles. Use this chunk to
understand:

- Why these classes appear, who emits them, and what they own.
- Why blockGap propagation works without theme CSS.
- Why per-instance element styling (e.g., a single button's link
  color override) doesn't bleed across instances.
- Why the `selectors` block.json field exists at all (this chunk is
  its runtime counterpart).
- Why `styles.css` escape hatches are structurally necessary
  (cascade topology is partially runtime-owned, not schema-owned).

This is the **first true runtime-native chunk** in KB.
Settings/styles/supports were declarative input surfaces; this is
the compiler internals layer that consumes them.

## SHAPE

The style engine emits **two parallel selector namespaces** at
render time. Both are class attributes added to block wrapper
elements, with corresponding rules emitted as inline `<style>` or
concatenated server-side stylesheet entries.

### Container topology synthesis — `.wp-container-*`

```html
<!-- author-side block markup — no class authored -->
<div class="wp-block-group has-display-flex">
  <!-- inner blocks -->
</div>

<!-- runtime-rendered output -->
<div class="wp-block-group has-display-flex wp-container-core-group-is-layout-7a3b1c2d">
  <!-- inner blocks -->
</div>
```

```css
/* style engine emits */
.wp-container-core-group-is-layout-7a3b1c2d {
  flex-wrap: wrap;
  gap: var(--wp--style--block-gap, 1rem);
  /* layout topology rules */
}
.wp-container-core-group-is-layout-7a3b1c2d > * {
  margin-block: 0;
}
```

Triggered by: `supports.layout`, `supports.spacing.blockGap`,
constrained-width inheritance, per-instance layout overrides.

### Element projection synthesis — `.wp-elements-*`

```html
<!-- author writes styles.elements.link.color override per-instance -->
<div class="wp-block-cover wp-elements-9b1f2e44a8c5...">
  <a href="...">link inside cover</a>
</div>
```

```css
/* style engine emits */
.wp-elements-9b1f2e44a8c5... a {
  color: var(--wp--preset--color--accent);
}
```

Triggered by: per-instance `styles.elements.{link|button|heading|caption|...}`
overrides set in the editor.

### What these are NOT

- NOT theme-authored selectors. Author-side `selectors` field
  declares attachment intent; these classes are the runtime
  fulfillment.
- NOT stable identifiers. The `{n}` suffix and `{uuid}` are
  ephemeral runtime artifacts.
- NOT a public API surface. Plugins/themes that hardcode these
  class names will break across renders / WP versions.
- NOT the only style engine output. The engine also emits
  preset-derived CSS variables, theme-wide selectors from
  `styles.*`, and per-block-type selectors. Generated selectors
  are the **per-instance scoped** sub-output.

## REQUIRES

- A trigger condition exists at the block instance level:
  - Layout container: block declares `supports.layout` AND has
    instance-level layout/spacing values.
  - Element scoping: instance has `styles.elements.*` overrides
    distinct from theme defaults.
- Style engine has access to the block tree at render
  (server-side render or client-side editor canvas).
- The wrapper element exists to receive the class attribute
  (block uses `useBlockProps` / equivalent).
- ⚠ Exact ID generation algorithm, hash inputs, and stability
  guarantees are not crisply documented in handbook prose;
  inferred from runtime observation. Verification-needed for
  precise contract.

## INVARIANTS

### 1. Two projection modes of a single synthesis engine

`.wp-container-*` and `.wp-elements-*` are **not two different
features** — they are two projection modes of the same runtime
attachment-topology synthesizer:

| namespace | ontology role | input authority |
|---|---|---|
| `.wp-container-*` | relational topology synthesis | layout / blockGap / constrained-width |
| `.wp-elements-*` | semantic projection synthesis | styles.elements.{role} per-instance |

Both share: schema-authored input → runtime synthesis →
per-instance scoping → selector graph generation → companion CSS
emission. Treating them as the same mechanism with different
projection modes is the load-bearing framing.

### 2. Container topology synthesis = first relational selector namespace

`.wp-container-*` is the first WordPress selector namespace that
encodes **relationships between nodes**, not just identity of a
node. It carries:

- Sibling spacing (gap propagation)
- Parent layout flow (flex / grid / flow)
- Constrained-width inheritance
- Nested gap behavior

Prior WordPress CSS architecture was overwhelmingly **entity-centric**
(`.wp-block-{type}`, `.wp-block-{type}-{instance}`). Container
synthesis introduces **relationship-centric** selectors —
"this child of this layout container under this gap regime."

### 3. Element projection synthesis = semantic role runtime

`.wp-elements-*` materializes the semantic-role projection axis
(link / button / heading / caption / etc.) at the instance
level. Where `styles.elements.link.color` at theme-global scope
emits a theme-wide selector, per-instance overrides emit a
scoped `.wp-elements-{uuid} a` selector — the runtime
projection of the same declarative role schema, isolated to one
block instance.

This unifies the recurring "semantic role" axis (block.variations
/ supports.color.{heading,button,link,caption} /
styles.elements.* / templateParts.area) with runtime topology.

### 4. Per-instance scoping — selector identity is per-render artifact

Each generated selector binds to ONE block instance in the
rendered tree. Two instances of the same block type with
different per-instance styles receive different generated
classes. The selector exists to **isolate** instance-level
style attachment from sibling instances.

### 5. Selector identity is ephemeral infrastructure, not contract

Generated selector identifiers are **runtime infrastructure
artifacts, NOT stable semantic identities**:

- `slug ≠ identity` — the suffix is generation-derived, not
  meaning-bearing.
- `selector ≠ public API` — plugins/themes MUST NOT hardcode
  `.wp-container-7` or any specific generated class as a
  styling anchor.
- ID may change across re-renders, block tree edits, WP
  versions, or render path (SSR vs CSR).

If a theme needs a stable hook, it must use the author-side
selectors block.json field or theme-level `styles.blocks.{name}`
— NOT the generated runtime artifact.

### 6. Runtime synthesis replaces static selector predictability

This is a historic architectural shift in WordPress CSS:

| era | selector regime |
|---|---|
| classic / pre-FSE | predictable global classes (author-controlled) |
| block themes / FSE | scoped runtime-synthesized selectors (engine-controlled) |

Pre-FSE WordPress let theme authors design the cascade by
authoring stable selectors. Style engine reverses authority —
the engine OWNS the per-instance selector graph; authors
contribute declarations that the engine compiles.

This explains why selectors block.json field arrived (WP 6.3) —
authors needed back a controlled extension surface as the engine
absorbed selector authority.

### 7. Style engine owns cascade topology (partially)

The cascade is **partially runtime-owned**:

- Authors still control: theme-global selectors via styles.*,
  styles.css escape hatch, per-block CSS via styles.blocks.{name}.css.
- Engine owns: per-instance generated selectors, their
  ordering relative to theme.json output, their specificity
  contribution to the final cascade.

Consequence — styles.css and selectors.css escape hatches exist
because **schema cannot fully describe a cascade the engine
partially synthesizes at runtime**. Escape hatches are not
oversight; they're the structural complement to runtime cascade
ownership.

### 8. Schema declares; runtime synthesizes — pattern peak

This chunk is the apex of the recurring KB pattern:

| layer | declares | synthesizes |
|---|---|---|
| supports.color | "this block type accepts color" | which controls show in editor |
| settings.color.palette | preset entries | `--wp--preset--color--*` CSS variables |
| styles.elements.link | element-role color values | theme-wide scoped selector |
| **selectors** (block.json) | **attachment surface intent** | **— (declaration only)** |
| **generated-selectors** | **— (no declaration)** | **per-instance attachment graph** |

Selectors and generated-selectors are the **declaration / synthesis
pair** — neither is complete without the other.

### 9. Schema incompleteness is structural, not gap

Because the runtime selector graph is **dynamic** (depends on
block tree, per-instance values, layout topology), no static
schema can fully cover the resulting CSS surface. Therefore:

- styles.css escape hatch (theme-global) — needed to override
  what the engine emits.
- selectors override surface (block.json) — needed to redirect
  attachment when engine defaults misalign.
- styles.blocks.{name}.css — per-block escape hatch.
- generated runtime classes themselves — the engine's own
  output of what schema couldn't pre-declare.

These four surfaces are not redundant escape valves; each
addresses a different level of the schema-incompleteness
necessity.

### 10. Pipeline: declaration → synthesis → realization → carrier

Style engine attachment forms a 4-stage pipeline:

| stage | role | concrete artifact |
|---|---|---|
| **declaration** | author intent | `selectors` field in block.json |
| **synthesis** | runtime selector graph | `.wp-container-*` / `.wp-elements-*` |
| **realization** | CSS rules emitted | `<style>` block / concatenated stylesheet |
| **carrier** | DOM attachment | wrapper element receiving the class |

This pipeline is the style-engine bounded context backbone.
Subsequent chunks (CSS variable emission, preset
materialization, cascade ordering) attach to specific stages.

## VERIFICATION NEEDED

This bounded context is runtime-implementation-heavy; handbook
prose only partially exposes the ontology. The following are
inferred from runtime observation + style-engine package source
and require explicit verification before relying on them:

- Selector ID stability across renders / sessions / SSR-CSR
  transitions.
- Cache invalidation lifecycle (when does the engine re-synthesize?).
- Exact hashing/ID-generation algorithm and inputs.
- Deduplication semantics across multiple instances with
  identical per-instance values.
- Aggregation ordering — generated selectors vs theme.json-emitted
  rules vs styles.css vs theme stylesheet — final cascade order.
- WP version of introduction for each namespace
  (`.wp-container-*` vs `.wp-elements-*` may have different
  introduction versions).

Treating runtime authority as **inferred, not fully documented**
is more honest to this layer's character than fabricating
certainty.

## ANTIPATTERNS

- ❌ Hardcoding `.wp-container-7` / `.wp-elements-{specific-uuid}`
  in theme or plugin CSS as a styling anchor. The class is
  ephemeral runtime infrastructure; it will break.
- ❌ Trying to predict ID values to author CSS in advance.
- ❌ Using generated classes as JavaScript selectors for
  behavior attachment. Use stable wrapper classes
  (`wp-block-{type}`) or data attributes.
- ❌ Authoring CSS that tries to undo `.wp-container-*` rules
  with `:not()` or `*` selectors. Fight the cascade with the
  declared escape hatches (styles.css, styles.blocks.{name}.css)
  instead.
- ❌ Treating the two namespaces as unrelated features.
  They share one synthesis engine; understanding the unifying
  ontology is required to reason about either.
- ❌ Expecting `.wp-elements-*` to scope ALL element styles.
  Theme-global element styles (set via styles.elements.*
  without per-instance override) emit theme-wide selectors,
  NOT scoped `.wp-elements-*` ones. Scoping is per-instance,
  not always-on.
- ❌ Believing theme authors regained pre-FSE selector
  predictability. Style engine permanently shifted selector
  authority to the runtime; the selectors field gives back
  attachment-point control, not full selector ownership.
- ❌ Assuming the engine synthesizes selectors deterministically
  identical across server-side and client-side renders.
  Verification-needed; do not rely on cross-environment ID
  match.
- ❌ Treating styles.css as a workaround for engine bugs.
  It is the structural complement to the engine's partial
  cascade ownership — using it for runtime-cascade overrides
  is the documented path, not a fallback.

## RELATED

- `block-authoring.block-json.selectors` — declaration
  counterpart. selectors declares "where authority MAY attach"
  (author intent); this chunk documents "how attachment
  topology materializes" (runtime synthesis). The two form an
  inseparable declaration / synthesis pair.
- `block-authoring.supports.layout` — primary trigger for
  `.wp-container-*` synthesis. Layout declarations at
  per-instance level cause container topology synthesis.
- `block-authoring.supports.spacing` (blockGap) — blockGap
  per-instance values trigger container synthesis even when
  layout itself is not customized.
- `theme-config.styles.css` — the escape hatch this chunk's
  invariants explain. styles.css exists because the engine
  partially owns cascade topology; authors need a documented
  override surface.
- `theme-config.styles` (elements namespace) — `styles.elements.*`
  declarations are the authority input that, when set per-instance,
  trigger `.wp-elements-*` synthesis.
- `block-authoring.markup-representation` — generated classes
  attach to the wrapper element produced by useBlockProps;
  the wrapper is the synthesis carrier.
- (planned) `style-engine.css-variable-emission` — preset →
  CSS variable compilation (the other major style engine output
  alongside generated selectors).
- (planned) `style-engine.preset-materialization` — registry →
  declaration → applied value pipeline.
- (planned) `style-engine.cascade-aggregation` — final cascade
  ordering across theme.json output, generated selectors,
  styles.css, theme stylesheet.

## META

This chunk surfaces a meta-shift in how to read Gutenberg:
**Gutenberg is not a "block schema system" — it is a runtime
style-graph compiler.** Prior KB phases documented compiler
input surfaces (supports / settings / styles / theme.json
top-level fields). The style-engine bounded context documents
compiler internals — selector synthesis, CSS variable emission,
preset materialization, cascade aggregation, wrapper topology.

Three-source authority hierarchy applies in this bounded
context (handbook ontology depth is limited):

| source | authority |
|---|---|
| handbook | declarative intent, schema documentation |
| @wordpress/style-engine package source | actual ontology, synthesis logic |
| rendered runtime output | empirical verification |

Style-engine ontology is **a runtime-derived surface whose
behavior is only partially expressible through schema
documentation**. KB chunks in this bounded context will rely on
runtime inference more heavily than declarative-surface chunks
did, and verification-needed will appear correspondingly more
often.
