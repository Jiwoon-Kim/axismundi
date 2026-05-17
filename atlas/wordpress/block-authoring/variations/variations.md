---
rule_id: block.variations
domain: block-authoring
topic: variations
field_cluster: identity
wp_min: "verification-needed"
wp_recommended: "6.6"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
    section: "Block Variations — definition, fields, scope, isDefault, isActive, registration paths"
    captured: 2026-05-09
related:
  - block.deprecation                  # identity ontology triangle: time axis
  - block.transforms                   # identity ontology triangle: semantic adjacency axis
  - block.json-attributes-core         # variations declare initial attribute values
  - block.inner-blocks                 # variations may declare initial innerBlocks
  - block.json-supports-field          # variation can include className override (intersects styles)
  - block.json-basic-metadata          # name / title / description / category / keywords / icon shape mirrors block.json
  - data-layer.block-styles            # block styles = visual projection sibling to variation's semantic projection
---

# RULE — `variations` — identity projection system

## WHEN

Defining presets / projections of a block that share the same
implementation but represent **different semantic roles** for the
end user. Use when:

- One block implementation should appear as multiple inserter items
  (e.g., `core/embed` exposing YouTube, Twitter, Spotify variations
  via the same underlying embed implementation).
- A core block needs an opinionated alternate default
  (e.g., Media & Text with image-on-right as the default).
- A grouping block needs initial-state presets for first-insert
  (e.g., Columns block offering 50/50, 30/70, 70/30 layouts).
- You're extending an existing block with a new role-specific
  projection (e.g., adding a "Wide Image" variation to `core/image`).

This is the **third axis of the identity ontology**, completing the
triangle:

| Axis | Identity relation | Mechanism |
|---|---|---|
| `block.deprecation` | same identity through TIME | parser-time compat for evolved schemas |
| `block.transforms` | DIFFERENT identities through semantic adjacency | editor-time conversion between block types |
| **`block.variations`** | **same identity through ROLE** | **inserter / discovery presets within one block type** |

## SHAPE

### Variation object — 12 fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | `string` | recommended (technically optional, but enables unregister + `isDefault` + diagnostics) | Machine-readable variation ID. Unique within the block type. |
| `title` | `string` | optional | Human-readable. Shown in inserter / pickers. |
| `description` | `string` | optional | Human-readable description. Shown in pickers / hover. |
| `category` | `string` | optional | Inserter category for organization. |
| `keywords` | `string[]` | optional | Search terms for inserter discovery. Translatable. |
| `icon` | `string \| Object` | optional | Same shape as block type's icon. |
| `attributes` | `Object` | optional | **Initial attribute values** the variation applies on insert. Overrides block defaults. |
| `innerBlocks` | `Array[]` | optional | **Initial nested-block config**. Same shape as the block-tree example arrays. |
| `example` | `Object` | optional | Preview data for the Inspector Help Panel. Set to `undefined` to disable preview. |
| `scope` | `WPBlockVariationScope[]` | optional, default `['block', 'inserter']` | Where the variation is offered (see scope values below). |
| `isDefault` | `boolean` | optional, default `false` | Replaces the block type's normal inserter entry with this variation. |
| `isActive` | `Function \| string[]` | optional but **recommended** | How the editor detects whether a placed block matches this variation (see isActive details). |

### `scope` values

| Scope | Effect |
|---|---|
| `block` | Used in `BlockVariationPicker` (Columns / Query block-type variation pickers) — variations offered as initial-state choices when inserting a parent block that has multiple shapes. |
| `inserter` | Variation appears as a separate item in the global block inserter. |
| `transform` | Variation appears in the variation-transformations component (a UI to switch between variations of the same block, distinct from `block.transforms`). |

Default scope `['block', 'inserter']`.

### Registration paths (3 ways)

```js
// 1. In block's own registerBlockType settings — ships with the block
registerBlockType( 'my-plugin/foo', {
  // ... other settings
  variations: [
    { name: 'preset-a', title: 'Preset A', attributes: { ... } },
    { name: 'preset-b', title: 'Preset B', attributes: { ... } },
  ],
} );

// 2. JS — for existing blocks (extension)
wp.blocks.registerBlockVariation( 'core/embed', {
  name: 'custom-embed',
  attributes: { providerNameSlug: 'custom' },
} );

// 3. PHP — dynamic variations via filter hook
add_filter( 'get_block_type_variations',
  function ( $variations, $block_type ) {
    if ( 'core/image' !== $block_type->name ) return $variations;
    $variations[] = array(
      'name'       => 'wide-image',
      'title'      => __( 'Wide image' ),
      'scope'      => array( 'inserter' ),
      'attributes' => array( 'align' => 'wide' ),
    );
    return $variations;
  }, 10, 2 );
```

PHP and JS registrations are **merged** — both sources contribute to
the final variation list for a block type.

```js
// Removal (existing or your own)
wp.blocks.unregisterBlockVariation( 'core/embed', 'youtube' );
```

### `isActive` forms

```js
// String[] form (preferred for simple cases)
isActive: [ 'providerNameSlug' ]

// Nested attribute paths (since WP 6.6)
isActive: [ 'query.postType' ]

// Function form (for complex matching)
isActive: ( blockAttributes, variationAttributes ) =>
  blockAttributes.providerNameSlug === variationAttributes.providerNameSlug
```

## REQUIRES

- The variation object MUST be valid against the block type's
  attribute schema — `attributes` keys must be declared in the
  block's `attributes` field.
- For meaningful diagnostics and unregistration, `name` SHOULD be
  unique. Unnamed variations cannot be removed via
  `unregisterBlockVariation()`.
- For the editor to identify a placed block as a specific variation
  (so it shows the variation's title/icon/description in the
  inspector), `isActive` MUST be set. Without it, the editor
  treats placed blocks as the original block type, not as the
  variation.
- For PHP-registered variations: the filter callback signature is
  `( $variations, $block_type ) => $variations`, hooked on
  `get_block_type_variations` (priority 10, 2 args).
- `isDefault: true` only takes effect if the variation is registered
  BEFORE any other isDefault variation for the same block. Last-
  registered does NOT override; first-registered isDefault wins.
- For `block` scope variations: the parent block must use the
  `BlockVariationPicker` component to surface them at insert-time.

## INVARIANTS

- **Identity is preserved.** All variations of `core/embed` are still
  `core/embed` blocks at the persistence / parser layer. The
  variation is purely a presentation/projection concept at the
  editor + inserter layer. Markup serializes as the parent block
  type, with the variation's `attributes` baked into the saved values.
- **Variations are presets, not types.** Source: *"A block variation
  differs from the original block by a set of initial attributes or
  inner blocks. When you insert the block variation into the Editor,
  these attributes and/or inner blocks are applied."* The
  variation's attributes act as DEFAULTS at insert time; the user
  may then modify any of them. A variation is NOT a constraint —
  it's a starting state.
- **isActive defines the inverse mapping** (block instance →
  variation it matches). Without isActive, the editor cannot show
  variation-specific UI metadata for already-placed blocks. Without
  isActive set:
  - Inserter still works (variation appears as expected).
  - Variation insertion still works (attributes get applied).
  - But once placed, the editor displays the original block type's
    title/icon/description, not the variation's. UX confusion.
- **isActive `string[]` form does literal equality on attribute
  values.** When all listed attribute paths match between the block
  instance and the variation declaration, the variation is active.
  Multiple variations could potentially match — first-matching wins
  in the typical evaluation order (verification-needed for exact
  resolution rules).
- **isActive function form** receives `(blockAttributes,
  variationAttributes)`. Use for complex matching (regex on URL,
  composite conditions, etc.). Performance: `isActive` runs
  whenever the editor needs to identify a block; keep cheap.
- **Nested attribute paths in isActive supported since WP 6.6.**
  E.g., `isActive: ['query.postType']` matches against
  `block.attributes.query.postType`. Pre-6.6 only top-level
  attribute names work.
- **Scopes determine surfacing context, not behavior.** A variation
  with `scope: ['inserter']` only appears in the global block
  inserter — not in BlockVariationPicker (parent block insert flow)
  and not in variation-transformations UI. Adjust scope to control
  where the variation is offered.
- **PHP + JS variations merge.** Source: *"variations registered
  through PHP will be merged with any variations registered through
  JavaScript using `registerBlockVariation()`."* Order of merging
  is not explicitly documented; first-isDefault rule still applies
  to the merged set.
- **isDefault is sticky.** First-registered isDefault variation
  wins. To override: explicitly unregister the previous default
  (`unregisterBlockVariation`), then register your isDefault
  variation. This is rarely a problem for ships-with-block
  variations but matters when extending core blocks.
- **Variations CAN override default block style via className**
  (per source's Quote example: `attributes: { className:
  'is-style-blue-quote' }`). This crosses into block-styles
  territory — variations are not strictly attribute-only; they
  can carry style projection too.
- **innerBlocks in variations** declares the INITIAL tree shape on
  insert. The user can then modify / add / remove children. This
  is used by Columns variations to set "2 columns 50/50" vs "3
  columns 30/40/30" presets — same Columns block, different starting
  topology.
- **`patterns` field appears in some variations but is not in the
  documented field list.** The Embed examples show
  `patterns: [/^https?:\/\/(www\.)?twitter\.com\/.+/i]` for URL
  matching used by paste-detection. This is likely a feature-
  specific extension (used by the embed block's transform/raw
  pipeline), not a generic variation field. ⚠ Verify per use case.
- ⚠ **Minimum WP version unknown** for the variations API as a whole.
  Specific features are versioned (nested isActive paths since 6.6,
  PHP `get_block_type_variations` filter — version unspecified).
  Frontmatter `wp_min` is `"verification-needed"`; set
  `wp_recommended: "6.6"` because nested isActive paths are a
  meaningful capability gain.

## ANTIPATTERNS

- ❌ Omitting `name` from a variation. Cannot be unregistered;
  cannot reliably be the target of `isDefault` collision resolution;
  diagnostic messages are vague.
- ❌ Omitting `isActive`. The variation works at insert time but
  the editor can't identify already-placed instances — they show
  as the original block. Users can't tell a Twitter Embed apart
  from a generic Embed in the inspector.
- ❌ Using a `Function` `isActive` when a `string[]` would suffice.
  Source explicitly recommends `string[]` form when possible —
  faster to evaluate, easier to compose, no bug surface.
- ❌ Expecting `isDefault: true` to override an existing isDefault
  variation. First-registered wins. To replace: unregister the
  existing default first.
- ❌ Confusing variation with transform. Variation = same block,
  preset of attributes/innerBlocks at insert-time. Transform =
  different block, conversion at any time after insert. Quote vs
  PullQuote: if they're declared as separate block types with a
  transform between them → transform; if PullQuote is declared as
  a variation of Quote with `attributes: {citation: true}` → variation.
  The block author's choice determines this — Gutenberg doesn't
  decide the boundary.
- ❌ Confusing variation with block style. Block style applies a
  CSS class only — purely visual. Variation can apply attributes,
  innerBlocks, AND className — semantic + visual. Use block style
  for "Outline button vs Filled button"; use variation for
  "YouTube embed vs Twitter embed".
- ❌ Declaring variations whose `attributes` keys don't exist in
  the block's attribute schema. Silent fail — values can't bind to
  unknown attribute slots.
- ❌ Hardcoding variation list when dynamic generation is needed.
  Use the PHP filter `get_block_type_variations` for variations
  derived from registered post types, taxonomies, or other
  WordPress data. Static JS arrays don't see runtime data.
- ❌ Setting `scope: ['inserter']` only on a variation meant for
  the BlockVariationPicker (parent insert flow). The variation
  must include `'block'` scope to appear there.
- ❌ Treating `patterns` as a documented variation field. It
  appears in embed examples but is not in the field list. Probably
  feature-specific to embed block's URL paste detection. Don't
  assume universal support.
- ❌ Relying on nested `isActive` attribute paths
  (e.g., `'query.postType'`) when targeting WP < 6.6. Use top-level
  attribute paths or feature-detect.
- ❌ Registering many variations on a block whose attribute schema
  doesn't differ meaningfully between them. Variation count
  multiplies inserter clutter without adding semantic value.

## RELATED

- `block.deprecation` — identity ontology TIME axis.
  Variations preserve identity through ROLE; deprecation preserves
  identity through TIME (schema evolution). Both keep the block
  type stable; they differ in what changes (role vs schema).
- `block.transforms` — identity ontology SEMANTIC ADJACENCY axis.
  Variations are projections WITHIN one block type; transforms are
  conversions BETWEEN block types. The boundary is the block
  author's choice (declare as variation vs declare as separate
  block + transform).
- `block.json-attributes-core` — variation `attributes` field
  overrides the block's attribute defaults at insert time. The
  attribute schema is the constraint — variation values MUST
  conform.
- `block.inner-blocks` — variation `innerBlocks` field declares the
  initial nested tree shape on insert (e.g., Columns variation
  with N columns). Same shape as block-tree examples.
- `block.json-supports-field` — variation can include `className`
  override (in `attributes: { className: 'is-style-...' }`),
  crossing into styles territory.
- `block.json-basic-metadata` — variation's `name` / `title` /
  `description` / `category` / `keywords` / `icon` mirror the
  block.json identity fields. Variations are essentially
  block-identity projections sharing the implementation.
- `data-layer.block-styles` (cross-context, planned) — block styles
  are the visual sibling: same block, different appearance via
  CSS class. Compare to variations (same block, different role
  via attributes/innerBlocks). Both are "projections" but at
  different layers: styles = visual, variations = semantic.

## RETROACTIVE REFRAMING (Q9 trigger from site-building.block-pattern-resolution)

**Status note**: Added 2026-05-09 following Q9 trigger from
`site-building.block-pattern-resolution-and-precedence`. Per
Phase 7.6 Section D Q9 retroactive verification methodology,
this chunk is verified against Resolution Surface candidate
to determine whether variation selection exhibits latent
Resolution Surface manifestation.

The original chunk frames variations as "identity projection
system" (preserved). This section adds the Resolution Surface
lens: does variation selection exhibit Doctrine 5 paired
operations character?

**KB pattern**: 6th explicit RETROACTIVE REFRAMING section in
KB:
- wrapper-attributes (post-style-engine closure)
- dynamic-rendering (post-Phase-7-capstone)
- markup-representation (post-Phase-7-capstone)
- cascade-aggregation (post-Resolution-Surface-surfacing,
  Integrated)
- capabilities-and-roles (post-Resolution-Surface-surfacing,
  Distributed)
- **variations (Q9 triggered, this section)**

**Methodological commitment** (per Phase 7.6 Doctrine 5b
discipline): honest evaluation. Variation selection may exhibit:
- **Confirmed (integrated)**: arbitration + resolution co-located
- **Confirmed (distributed)**: stages distributed across mechanisms
- **Confirmed (hybridized)**: mixed integrated + distributed
- **Divergent**: variation is identity projection but NOT
  Resolution-character

### Reframing — variation selection through Doctrine 5 paired operations lens

Pre-Q9 reading: variations are "identity projection presets
selectable from inserter."

Post-Q9 analysis with Doctrine 5 lens:

**Arbitration test for variation selection:**

> Stage 1 (Arbitration): What variation candidates qualify
> for inserter display in current context?

Variation arbitration mechanisms:
- Registration: `registerBlockVariation()` adds to candidate
  pool
- Scope filtering: `scope: ['inserter']` vs `['block']` vs
  `['transform']` arbitrates surface placement
- isActive function: arbitrates which variation matches current
  block instance state
- Inserter search: keyword/title matching arbitrates visibility

**Variation arbitration IS structurally distributed**:
multiple mechanisms (registration, scope filter, isActive,
search match) collectively arbitrate which variation
candidates qualify for which contexts.

**Resolution test for variation selection:**

> Stage 2 (Resolution): Which variation actually becomes the
> instantiated block?

Resolution mechanisms:
- User selection in inserter
- Variation's `attributes` + `innerBlocks` materialized into
  new block instance
- Block tree mutation (insertion event)

**Variation resolution IS integrated** at insertion site:
selection + attribute application + block tree mutation occur
as single actualization event.

### Verification result: HYBRIDIZED (Doctrine 5 confirmed)

> **Variation selection exhibits Hybridized Doctrine 5**
> **architecture (parallel to block-pattern-resolution).**

| stage | architecture |
|---|---|
| Arbitration (registration + scope + isActive + search) | **Distributed** |
| Resolution (selection + attribute application + insertion) | **Integrated** |

This is the **2nd Hybridized variant documented in KB** (after
block-pattern-resolution).

**Resolution Surface manifestation: CONFIRMED.** Variation
selection IS Resolution Surface manifestation, exhibiting
the same Hybridized architecture as block patterns. Both are
**block-authoring + composition runtime selection** mechanisms.

### RETROACTIVE INVARIANTS

#### A. Variation selection exhibits Doctrine 5 paired operations (Hybridized)

The original chunk's "identity projection" framing is accurate
but **structurally enrichable**. Identity projection IS
Resolution Surface manifestation at the variation layer:

- Identity projection = WHICH variation identity actualizes
  for THIS block instance
- This IS structural Resolution per Doctrine 5

The original framing emphasized the IDENTITY aspect (same
block, different semantic role). The retro adds the
RESOLUTION aspect (multi-source variation candidates resolve
to one selected variation per insertion event).

Both framings are accurate. Identity is the WHAT (semantic
character of the projection); Resolution is the HOW (paired
arbitration + actualization mechanism).

#### B. Variation Hybridization parallels block-pattern Hybridization (Doctrine 5b strengthened)

KB now has 2 documented Hybridized Doctrine 5 manifestations:

| chunk | arbitration | resolution | architecture |
|---|---|---|---|
| site-building.block-pattern-resolution | Distributed (registration / category / context / exposure / search) | Integrated (insertion event) | Hybridized |
| **block-authoring.variations (this retro)** | **Distributed (registration / scope / isActive / search)** | **Integrated (selection + attribute application + insertion)** | **Hybridized** |

Both are **selectable-preset-from-inserter** mechanisms.
Architectural parallelism is structural — selection-from-
candidates mechanisms tend toward Hybridized variant
(Distributed candidate pipeline + Integrated actualization).

This **strengthens Doctrine 5b architectural variant
integrity** (Phase 7.6) — Hybridized is not edge case;
it recurs at structurally similar mechanisms.

#### C. Resolution Surface intra-context recurrence in block-authoring (NEW)

Pre-Q9: Resolution Surface had cross-context PRESENCE
(site-building + style-engine + plugin-dev) but NO
block-authoring manifestation documented.

Post-Q9 (this retro): variation selection IS Resolution
Surface manifestation in **block-authoring**.

This expands Resolution Surface bounded-context coverage to
**4 contexts**:
- site-building (template hierarchy + block patterns —
  intra-context density)
- style-engine (cascade-aggregation — Integrated)
- plugin-dev (capabilities-and-roles — Distributed)
- **block-authoring (variations — Hybridized, this retro)**

> **Resolution Surface candidate status update**:
>
> 4-bounded-context PRESENCE confirmed (site-building +
> style-engine + plugin-dev + block-authoring).
> Architectural variant diversity: Integrated + Distributed +
> Hybridized (3 variants documented).
> Strongest evidence base for any KB candidate beyond KB-Wide
> laws.
>
> **Phase 7.8 KB-Wide audit verification candidacy**:
> Resolution Surface meets cross-context recurrence + variant
> diversity threshold for audit consideration. NOT yet
> promoted (audit required).

#### D. Variations and block patterns share structural pattern: SELECTION FROM CANDIDATES

KB-level pattern observation (NEW):

> Mechanisms that present **selectable presets from a candidate
> pool to user via inserter UI** tend to exhibit Hybridized
> Doctrine 5 architecture.

Documented instances:
- Block patterns (site-building.block-pattern-resolution)
- Variations (block-authoring.variations, this retro)
- (potentially) Transforms (Q9 trigger from same source —
  yet to be retro-verified)

This structural observation suggests **"Selection from
Candidates" as recurring sub-pattern within Doctrine 5
Hybridized variant**. Status: **OBSERVATION ONLY** (not
constitutionalized; Phase 7.6 deferred candidates discipline).

If transforms retro confirms similar pattern, this becomes
3-instance recurrence and may warrant explicit doctrinal
recognition (Phase 7.7 patch consideration).

#### E. Q9 retroactive verification methodology validated (3 instances now)

Q9 was first operationalized in
site-building.block-pattern-resolution. This chunk is the
**FIRST executed Q9 retro** (cascade + capabilities retros
were pre-Q9; this is post-Q9 deliberate methodology
application).

Q9 retros completed:
1. cascade-aggregation (pre-Q9 methodology, post-hoc
   classified as Q9-equivalent) — Integrated
2. capabilities-and-roles (pre-Q9 methodology, post-hoc
   classified as Q9-equivalent) — Distributed
3. **variations (this — first deliberate Q9 application)** —
   Hybridized

3-instance Q9 application × 3 architectural variants documented
(Integrated + Distributed + Hybridized). Q9 methodology
demonstrably distinguishes variants honestly.

### Constitutional implications

**1. Resolution Surface 4-context PRESENCE — strongest active
candidate in KB:**

After this retro:
- 4 bounded contexts (site-building × 2 + style-engine +
  plugin-dev + block-authoring)
- 3 architectural variants (Integrated + Distributed +
  Hybridized)
- 1 intra-context density (site-building 2 chunks)
- 3 retroactive verifications (cascade + capabilities +
  variations)
- 1 forward authoring (site-building template hierarchy)

This is the **strongest evidence base of any non-KB-Wide
candidate in KB**. Phase 7.8 audit verification timeline
becomes more defined.

**2. "Selection from Candidates" sub-pattern observation:**

Surfaced as observation only:

> Mechanisms presenting selectable presets to users via
> inserter UI tend toward Hybridized Doctrine 5 architecture.

Tested across 2 instances (block patterns + variations).
3rd instance test pending (transforms retro). If 3-instance
confirmation → Phase 7.7 patch consideration.

**3. Transforms retro now URGENT:**

After this variations retro:
- Variations exhibit Hybridized Doctrine 5
- Transforms hypothesized (per Q9 trigger) to exhibit
  similar character
- Confirming transforms would establish 3-instance
  "Selection from Candidates" sub-pattern

Transforms retro becomes the **next critical Q9 work item**
to either confirm or refute the sub-pattern hypothesis.

### Methodological discipline preserved

This retro:
- Honestly evaluated 4 possible Doctrine 5 outcomes
- Concluded **Hybridized** (matching block-pattern character)
- Did NOT promote "Selection from Candidates" sub-pattern
- Surfaced sub-pattern as observation only
- Acknowledged 4-context PRESENCE for Resolution Surface
- Did NOT promote Resolution Surface to KB-Wide (audit
  required)
- Documented Phase 7.8 audit verification candidacy
  explicitly

> **KB methodological maturity advance**: Q9 retroactive
> verification methodology now demonstrated across 3
> architectural variants (Integrated / Distributed /
> Hybridized). Methodology distinguishes variants honestly
> rather than uniformly.
