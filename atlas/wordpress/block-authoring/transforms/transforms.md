---
rule_id: block.transforms
domain: block-authoring
topic: transforms
field_cluster: lifecycle
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-transforms/
    section: "Block Transforms — direction, 6 types, ungroup, schemas"
    captured: 2026-05-09
related:
  - block.deprecation                  # parallel runtime: parser-time compat vs editor-time conversion
  - block.markup-representation        # transforms produce new block tree IR
  - block.inner-blocks                 # block transforms reshape the InnerBlocks subtree
  - block.json-attributes-core         # transform output blocks carry attributes
  - block.edit-save-components         # transforms operate on parsed block objects, not on save() output
  - block.json-supports-field          # supports.splitting interacts with enter-type transforms
  - data-layer.shortcode-package       # cross-context: shortcode transform uses @wordpress/shortcode
---

# RULE — `transforms` — semantic conversion runtime

## WHEN

Defining a block that participates in **semantic conversion** —
either AS a target (other content / blocks become this block) or AS a
source (this block converts into something else). Use when the
relationship between the block and another representation
(another block, raw HTML, a shortcode, dropped files, typed prefix,
ENTER on text) should be expressible as a user-triggered transform
in the editor.

This is the **editor / user authority counterpart to deprecation**.
Where `block.deprecation` is the parser-time compatibility runtime
(automatic, identity-preserving, historical evolution), `transforms`
is the editor-time semantic translation runtime (manual, identity-
changing, lateral reinterpretation).

| Layer | `deprecation` | `transforms` |
|---|---|---|
| Identity | preserved (same block type) | changed (block type → block type) |
| Goal | backward compatibility | semantic conversion |
| Trigger | invalid/current mismatch (automatic) | user/editor action (manual) |
| Direction | historical evolution | lateral reinterpretation |
| Authority | parser/runtime | editor/user |
| Canonicality | preserve existing block | create new block(s) |

## SHAPE

### Top-level structure

The block configuration's `transforms` key holds three optional
subkeys:

```js
export const settings = {
  // ... other settings
  transforms: {
    from:    [ /* array of from-direction transforms */ ],
    to:      [ /* array of to-direction transforms */ ],
    ungroup: ( attributes, innerBlocks ) => [ /* ... */ ],
  },
};
```

- `from` — transforms that PRODUCE this block (other content becomes
  this block).
- `to` — transforms that this block can BECOME (this block converts
  into another).
- `ungroup` — special decomposition transform (this grouping block
  becomes its constituent inner blocks).

### Transform type matrix

| Type | Direction | Trigger | Key parameters beyond type |
|---|---|---|---|
| `block` | **to + from** (bidirectional) | User clicks block-type switcher in toolbar | `blocks` (array of names or `"*"` wildcard), `transform` (callback), optional: `isMatch`, `isMultiBlock`, `priority` |
| `enter` | from only | User types content + Enter key in new line | `regExp`, `transform`, optional: `priority` |
| `files` | from only | User drops files into editor | `transform`, optional: `isMatch`, `priority` |
| `prefix` | from only | User types prefix + space in new line | `prefix` (string), `transform`, optional: `priority` |
| `raw` | from only | User pastes / drops HTML, OR uses "Convert to Blocks" UI | `transform` (optional), `schema` (object|function), `selector` (CSS), `isMatch`, `priority` |
| `shortcode` | from only | Editor parses post content containing shortcodes | `tag` (string|array), `transform` OR `attributes`, optional: `isMatch`, `priority` |

### Common parameters

- `transform( ... )` callback returns a block object
  (`createBlock( name, attributes, innerBlocks? )`) OR an array of
  block objects.
- `isMatch( ... )` callback returns boolean — gates whether the
  transform is offered / applied. False = transform unavailable.
- `priority` (number, default `10`) — WordPress hook-style ordering;
  lower wins. Used to break ties when multiple transforms could
  match the same input.

### `block` type — additional notes

- `blocks` array can include the wildcard `"*"` meaning "all block
  types are valid sources/targets". Used for example by `core/group`
  to make any block convertible into a group.
- `isMultiBlock: true` allows transform when multiple blocks are
  selected. The `transform` callback receives arrays
  (`(attributesArray, innerBlocksArray)`) instead of single values.

### `raw` type — schema sub-system

`raw` transforms uniquely accept a `schema` parameter for HTML
content-model validation. Schemas are passed to `cleanNodeList`
from `@wordpress/dom` to clean pasted content before transform
matching.

```js
schema = { span: { children: { '#text': {} } } };
```

Function form receives `phrasingContentSchema` (predefined HTML
phrasing elements) and `isPaste` boolean:

```js
schema = ({ phrasingContentSchema }) => ({
  div: {
    required: true,
    attributes: [ 'data-post-id' ],
    children: {
      h2: { children: phrasingContentSchema },
      p:  { children: phrasingContentSchema },
    },
  },
});
```

### `ungroup` — special decomposition

Outside `to`/`from`, the `ungroup` subkey defines how a grouping
block decomposes into its constituent parts. Receives
`(attributes, innerBlocks)`, returns an array of replacement blocks.

```js
transforms: {
  ungroup: ( attributes, innerBlocks ) =>
    innerBlocks.flatMap( ( inner ) => inner.innerBlocks ),
},
```

UI: appears as the same Ungroup button used for the default core
group block, IF the block has `ungroup` defined AND is currently
selected with at least one inner block.

## REQUIRES

- `transforms` key MUST be in the block's client-side registration
  (`registerBlockType` settings object). Server-side registration
  alone does not enable transforms.
- For `block` type: `blocks` array MUST contain the names of
  registered (or to-be-registered) block types, OR the wildcard `"*"`.
  Names of unregistered blocks silently make the transform unavailable.
- `transform` callback MUST return either a single block (from
  `createBlock`) or an array of blocks. Returning `null` /
  `undefined` aborts the transform silently.
- For `raw` transforms with `schema`: schema shape MUST conform to
  `cleanNodeList` expectations. Invalid schemas cause unpredictable
  filtering behavior.
- For `shortcode` transforms: provide either `transform` callback OR
  `attributes` mapping (with shortcode source functions). If both
  are provided, `transform` takes precedence.
- For `enter` / `prefix` transforms to fire, the block must be
  inserted on a NEW LINE (not in the middle of existing content);
  Enter/space at end of line + matching content triggers the
  transform.

## INVARIANTS

- **Hybrid graph topology.** The transforms system is NOT a uniform
  bidirectional graph. It is:
  - **Bidirectional core**: `block` type creates A↔B edges between
    block types (via to/from arrays on either side).
  - **Directed input edges**: `enter`, `files`, `prefix`, `raw`,
    `shortcode` create EXTERNAL_INPUT → block edges (from-only).
  - **Decomposition edges**: `ungroup` creates a parent → [children]
    expansion (one-to-many).
- **Transform direction is declared per-block, not per-pair.** A
  paragraph→heading transform can be declared on EITHER the
  paragraph block (in its `to` array) OR the heading block (in its
  `from` array). Both sides may declare the same conversion.
  When both exist, deduplication is the editor's responsibility.
- **`block` type alone is bidirectional.** All other types are
  from-only inputs into the editor — there is no way to "transform
  a block back into a shortcode" or "transform a block back into
  pasted HTML". Reverse direction is structurally impossible for
  non-block transform types (the original input source is
  irrecoverable).
- **`raw` is the umbrella for content-paste transforms.**
  `shortcode` transforms are documented as being applied "as part
  of the raw transformation process". Pasting content invokes the
  raw pipeline, which internally tries shortcode matching among
  other strategies.
- **Trigger type taxonomy:**
  - User-initiated: `block` (toolbar switcher), `ungroup` (toolbar
    button)
  - Editor input event: `enter` (Enter key), `prefix` (text+space)
  - File system event: `files` (drag-drop)
  - Content paste / parse: `raw`, `shortcode`
- **Trigger conflict resolution: priority.** When multiple transforms
  match the same input, lower `priority` value wins. Default 10.
  This is a WordPress hook-style ordering — useful for declaring
  fallback transforms (higher priority) vs preferred transforms
  (lower priority).
- **`isMatch` is the gate, `transform` is the executor.** `isMatch`
  is called first; if it returns false, the transform is hidden /
  skipped. Performance: keep `isMatch` cheap because it may be
  called for every block type / user input.
- **Transforms operate on PARSED block objects, not on serialized
  markup.** The `transform` callback receives attributes and (for
  block-with-InnerBlocks transforms) `innerBlocks` as already-parsed
  block tree nodes. The output is also a block tree node, which the
  editor then serializes into the new delimiter pair.
- **Transforms create NEW blocks; the original block is REPLACED.**
  This means the original block's `clientId` is gone after transform;
  the new block has a fresh `clientId`. References to the original
  by clientId become stale.
- **Lossy conversion is the author's responsibility.** Source does
  NOT define a "compatibility" or "loss reporting" mechanism. If
  paragraph→heading drops alignment-attribute support, the author's
  `transform` callback decides whether to omit, default-substitute,
  or preserve the value. There is no built-in lossy-conversion
  warning UI.
- **"Same meaning" is undefined.** Source provides no semantic
  equivalence framework. The editor exposes whatever transforms
  block authors declare; whether `paragraph→heading` is "the same
  meaning at a different heading level" or "different meaning
  entirely" is a question the system does not formally answer.
  Transforms are AUTHOR DECLARATIONS of meaningful conversions, not
  a derived semantic equivalence graph.
- **InnerBlocks are passed through `block` transforms.** When a
  block-to-block transform's source has inner blocks, the
  `transform( attributes, innerBlocks )` callback receives them
  and the author decides how to redistribute (preserve, drop,
  restructure). This is the primary topology rewrite mechanism.
- **`ungroup` is structurally distinct from `to` transforms.**
  A block declaring `ungroup` does NOT need to also declare
  `to: [{ type: 'block', blocks: ['*'], ... }]`. Ungroup uses its
  own UI (the Ungroup button). The two mechanisms can coexist.
- **`raw` schema enables content sanitization.** Without a custom
  schema, pasted HTML goes through `cleanNodeList` with default
  rules — typically allowing phrasing content (`<strong>`, `<em>`,
  etc.) and stripping non-phrasing constructs (custom `<div>`s,
  `<details>`, etc.). Custom schemas extend the allowed set.
- **`shortcode` transforms can use either function form OR
  attribute-mapping form.** Function form: callback receives parsed
  shortcode `{ named, numeric }`. Attribute form: each block
  attribute can have a `shortcode` source function that extracts
  from shortcode atts. Use attribute form when sourcing from
  multiple atts cleanly; use function form for complex transforms.
- ⚠ **Minimum WP version unknown.** Transforms have been part of
  the original block editor (WP 5.0+ era) but specific transform
  types' introduction versions are not enumerated in source.
  Frontmatter `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Declaring transforms on the wrong side of the conversion. A
  paragraph→heading transform makes sense as `from` on heading OR
  `to` on paragraph; declaring it as `from` on paragraph makes no
  sense (paragraph being created from itself).
- ❌ Returning `null` / `undefined` from a `transform` callback
  expecting it to "abort with a message". Source does not document a
  failure-feedback mechanism — the transform silently does nothing.
  Use `isMatch` to gate availability instead.
- ❌ Performing async work inside `transform` callbacks. Source
  documents synchronous return of block objects. Async work belongs
  in the resulting block's `edit` lifecycle, not in the transform
  itself (e.g., `files` transform creates a block with a blob URL,
  the actual upload happens in the block's componentDidMount).
- ❌ Using `transform` for backward compatibility (instead of
  `block.deprecation`). Same input, different ontology: deprecation
  preserves block identity across schema versions; transforms create
  a new block of a different type. Mixing them produces broken
  invalidation cascades.
- ❌ Wildcard `blocks: ["*"]` on a `block` transform without
  thoughtful `isMatch`. Every block type becomes a potential source;
  performance and UX degrade if the transform makes sense for only
  some of them.
- ❌ Using `enter` or `prefix` transforms for content that should
  be deliberate user choice. These auto-trigger on input — a
  user typing `?` + space might accidentally invoke a question-block
  transform unrelated to their intent.
- ❌ Forgetting `priority` when declaring overlapping transforms.
  If two `prefix` transforms both match `>`, the one declared first
  may not win — declare priorities explicitly to control precedence.
- ❌ Discarding `innerBlocks` in a `block` transform whose source
  block had inner blocks (without good reason). Users lose nested
  content silently — a usability failure.
- ❌ Defining `ungroup` on a non-grouping block (one that doesn't
  use InnerBlocks meaningfully). The Ungroup UI appears but produces
  unexpected results.
- ❌ Writing complex `isMatch` logic that performs DOM queries / data
  fetches. `isMatch` runs frequently; keep it cheap (string compare,
  property lookup, regex test).
- ❌ Assuming `raw` transforms work without `schema` for HTML
  containing non-phrasing elements (`<div>`, `<details>`, etc.).
  Default cleanup strips these; declare a custom schema to preserve
  them for matching.
- ❌ Treating transforms as a way to expose "block templates". Use
  block patterns or `block.json` `example` for that — transforms are
  for content-driven type changes, not insertion templates.

## RELATED

- `block.deprecation` — the **parallel runtime** with opposite
  authority. Read together: deprecation handles same-identity
  evolution at parse time; transforms handle different-identity
  conversion at editor time. They never operate on the same trigger
  conditions.
- `block.markup-representation` — transforms produce NEW block tree
  IR, which then serializes into NEW delimiter pairs. Original
  block's IR is discarded (the markup it occupied gets replaced).
- `block.inner-blocks` — `block` transforms reshape the InnerBlocks
  subtree (parent type changes, children may be preserved /
  redistributed / restructured). Group→Columns is the canonical
  topology rewrite.
- `block.json-attributes-core` — transform output blocks carry
  attributes; the author's `transform` callback determines
  attribute mapping. Compare to attribute extraction (parse-time)
  vs attribute reconstruction (transform-time).
- `block.edit-save-components` — transforms operate on PARSED block
  objects, NOT on `save()` output. The original block's `save`
  doesn't run during transformation; the new block's `save` runs
  on next render.
- `block.json-supports-field` — `supports.splitting` interacts with
  `enter`-type transforms (Enter key behavior on text blocks). When
  `splitting: true`, Enter splits the block instead of triggering
  enter-transform.
- `data-layer.shortcode-package` (cross-context, planned) —
  `shortcode` transforms use `@wordpress/shortcode` parser. The
  `WPShortcodeMatch` shape passed to `transform`/`attributes`
  comes from this package.

## RETROACTIVE REFRAMING (Q9 trigger from site-building.block-pattern-resolution)

**Status note**: Added 2026-05-09 following Q9 trigger from
`site-building.block-pattern-resolution-and-precedence` (same
trigger as variations retro). Per Phase 7.6 Section D Q9
methodology, transforms verified against Resolution Surface
candidate to test whether transform selection exhibits paired
operations character.

The original chunk frames transforms as "semantic conversion
runtime" (preserved). This section adds Doctrine 5 lens.

**KB pattern**: 7th explicit RETROACTIVE REFRAMING section in
KB:
- wrapper-attributes (post-style-engine closure)
- dynamic-rendering (post-Phase-7-capstone)
- markup-representation (post-Phase-7-capstone)
- cascade-aggregation (post-Resolution-Surface-surfacing)
- capabilities-and-roles (post-Resolution-Surface-surfacing)
- variations (Q9 triggered, 1st deliberate Q9 retro)
- **transforms (Q9 triggered, 2nd deliberate Q9 retro, this
  section)**

**Methodological commitment**: honest evaluation per Phase 7.6
Doctrine 5b discipline.

**Hypothesis from variations retro**: transforms likely exhibit
similar Hybridized character (Distributed arbitration +
Integrated resolution). 3rd-instance "Selection from Candidates"
sub-pattern test depends on this retro's outcome.

### Reframing — transform selection through Doctrine 5 paired operations lens

Pre-Q9 reading: transforms are "user-triggered editor-time
semantic conversions" identified by direction (from/to) +
type (block/raw/shortcode/enter/prefix/files).

Post-Q9 analysis with Doctrine 5 lens:

**Arbitration test for transform selection:**

> Stage 1 (Arbitration): What transform candidates qualify
> for current context?

Transform arbitration mechanisms (DISTRIBUTED across multiple
locations):
- Registration: `transforms.from[]` and `transforms.to[]`
  arrays add to candidate pool
- Direction matching: source block + target block must align
  (transforms.from on target block; transforms.to on source
  block)
- `blocks` array filtering: which block types qualify as
  source/target
- `isMatch` callback evaluation: per-instance contextual
  qualification (cheap predicate filters candidates)
- Type filtering: appropriate transform type for trigger
  (block transform menu / paste / enter / prefix / file drop)
- `priority` arbitration: when multiple transforms match,
  priority determines order

**Transform arbitration IS Distributed** across registration,
direction matching, blocks array filtering, isMatch, type
filtering, priority handling.

**Resolution test for transform selection:**

> Stage 2 (Resolution): Which transform actually executes?

Resolution mechanisms (INTEGRATED at execution site):
- User selection (transform menu click / paste action / Enter
  press / prefix typing / file drop)
- `transform` callback invocation
- Returns new block (or block array)
- Block tree mutation (replace original with transformed)

**Transform resolution IS integrated** at execution site:
selection → callback → block creation → tree mutation occur as
single actualization event.

### Verification result: HYBRIDIZED (Doctrine 5 confirmed) — 2nd deliberate Q9 confirmation

> **Transform selection exhibits Hybridized Doctrine 5**
> **architecture (parallel to variations and block-patterns).**

| stage | architecture |
|---|---|
| Arbitration (registration + direction + blocks + isMatch + type + priority) | **Distributed** |
| Resolution (selection + transform callback + tree mutation) | **Integrated** |

This is the **3rd Hybridized variant documented in KB**:
- Block patterns (site-building.block-pattern-resolution)
- Variations (block-authoring.variations retro)
- **Transforms (block-authoring.transforms retro, this)**

**3 Hybridized instances strengthen Doctrine 5b architectural
variant integrity** — Hybridized is no longer surprising; it
is recognized as the architectural variant for **selection-
from-candidates mechanisms**.

### "Selection from Candidates" sub-pattern PROMOTED to candidate observation

Variations retro surfaced this observation; this transforms
retro **CONFIRMS the 3-instance threshold**:

> **"Selection from Candidates" sub-pattern**:
> Mechanisms that present **selectable presets / conversions
> from a candidate pool to user via inserter-or-equivalent UI**
> tend to exhibit **Hybridized Doctrine 5 architecture**
> (Distributed arbitration pipeline + Integrated actualization
> event).

**3-instance evidence base**:
- Block patterns (site-building) — Hybridized
- Variations (block-authoring) — Hybridized
- Transforms (block-authoring) — Hybridized

**Status: surfaced as Local Pattern with cross-context
PRESENCE** (3 chunks × 2 bounded contexts). NOT promoted to
Recurring (cross-context) per discipline (cross-context
density requires manifestation in additional bounded contexts
beyond block-authoring + site-building).

This sub-pattern observation is **Phase 7.7 patch
consideration candidate** if additional contexts confirm
(e.g., does interactivity directive selection exhibit similar?
does admin notice display selection exhibit similar?).

### RETROACTIVE INVARIANTS

#### A. Transform selection exhibits Doctrine 5 Hybridized architecture (3rd Hybridized in KB)

The original chunk's "semantic conversion runtime" framing is
accurate. The retro adds: this conversion is structurally a
Resolution Surface manifestation with Hybridized
architecture. Identity-changing conversion IS structural
Resolution per Doctrine 5.

#### B. Resolution Surface block-authoring intra-context density (variations + transforms)

Pre-this-retro: Resolution Surface had block-authoring
PRESENCE via variations retro alone (1 chunk).

Post-this-retro: 2 chunks within block-authoring
(variations + transforms) both exhibiting Resolution Surface
with Hybridized architecture.

> **Resolution Surface intra-context density now confirmed
> within block-authoring** (parallel to site-building's
> intra-context density).

Resolution Surface bounded-context coverage updated:
- site-building (template hierarchy + block patterns —
  intra-context density)
- style-engine (cascade-aggregation — Integrated)
- plugin-dev (capabilities-and-roles — Distributed)
- **block-authoring (variations + transforms — intra-context
  density, both Hybridized)**

**4 bounded contexts × multiple instances + 3 architectural
variants × intra-context density in 2 bounded contexts.**

This is the **most evidence-rich non-KB-Wide candidate in
KB**. Phase 7.8 audit verification candidacy confirmed.

#### C. "Selection from Candidates" sub-pattern reaches 3-instance evidence threshold

Sub-pattern recurrence:
- Block patterns (Hybridized) — 1st instance (site-building)
- Variations (Hybridized) — 2nd instance (block-authoring)
- **Transforms (Hybridized) — 3rd instance (block-authoring)**

3-instance recurrence × 2 bounded contexts. The sub-pattern
shows architectural consistency: ALL 3 mechanisms exhibit
Hybridized variant; this is structurally non-coincidental.

**Status: Local Pattern with cross-context PRESENCE.**
Phase 7.7 patch consideration candidate (NOT yet promoted).

#### D. Identity changing transformation IS Resolution Surface manifestation

Original chunk's emphasis on identity character (transforms
change block identity, deprecation preserves it) is
preserved. The retro adds: identity-changing IS itself
structurally a Resolution event:
- Multiple identity candidates qualify (which block types
  can source converts to?)
- Resolution selects one identity (user picks from menu)
- New identity actualizes (transform callback creates new
  block)

Identity-axis framing and Resolution-axis framing are NOT
competing — they're **two analytical lenses on the same
mechanism**. Identity emphasizes the WHAT (what kind of
identity change); Resolution emphasizes the HOW (paired
arbitration + actualization).

#### E. Q9 retroactive verification methodology validated (4 instances now)

Q9 retros completed:
1. cascade-aggregation (pre-Q9 methodology, retro-classified) — Integrated
2. capabilities-and-roles (pre-Q9 methodology, retro-classified) — Distributed
3. variations (1st deliberate Q9 retro) — Hybridized
4. **transforms (2nd deliberate Q9 retro, this) — Hybridized**

Q9 methodology demonstrably distinguishes architectural
variants:
- Integrated (cascade)
- Distributed (capabilities)
- Hybridized (variations + transforms — pattern recurrence
  within Hybridized variant)

### Constitutional implications

**1. Resolution Surface — strongest active candidate, audit
ready:**

After 4 retro confirmations + 1 forward authoring + 4-context
PRESENCE + 3 architectural variants + 2-context intra-context
density:

> **Resolution Surface candidate is the strongest non-KB-Wide
> candidate in KB.** Phase 7.8 audit verification ready.
> Recommended next major work: KB-Wide audit verification
> chunk for Resolution Surface promotion to KB-Wide LAW
> status.

**2. "Selection from Candidates" sub-pattern reaches Phase 7.7
patch threshold:**

3-instance evidence × 2 bounded contexts × consistent
Hybridized architecture = sufficient evidence for Phase 7.7
patch consideration:

```
Phase 7.7 patch consideration:
   Add to Doctrine 5 sub-section:
   "Selection from Candidates sub-pattern:
    Mechanisms presenting selectable presets/conversions
    from a candidate pool to user via inserter-or-equivalent
    UI exhibit Hybridized Doctrine 5 architecture
    (Distributed arbitration + Integrated actualization)."
```

NOT yet patched. Defer to spec patch session if user
chooses to proceed with Phase 7.7.

**3. Transform's deeper bridge: identity ontology + Resolution
ontology:**

Transforms now bridge:
- Identity ontology triangle (deprecation/transforms/variations
  per original chunk)
- Resolution Surface candidate (Hybridized variant per this
  retro)

This is the **first KB chunk explicitly bridging identity
ontology and Resolution Surface candidate**. Suggests
Resolution may have **identity-character** (resolution about
which identity actualizes) alongside structural-character
(resolution about which structure actualizes).

Future audit work may explore whether Resolution Surface has
multiple character dimensions (identity + structural +
operational?).

### Methodological discipline preserved

This retro:
- Honestly evaluated Doctrine 5 outcome → Hybridized
  (matching variations + block-patterns)
- Promoted "Selection from Candidates" sub-pattern from
  observation to LOCAL Pattern with cross-context PRESENCE
- Did NOT promote sub-pattern to Recurring (cross-context)
  (insufficient cross-bounded-context evidence beyond
  block-authoring + site-building)
- Did NOT promote Resolution Surface to KB-Wide (audit
  required)
- Documented Phase 7.7 patch candidacy explicitly
- Acknowledged 4-context PRESENCE + 3-variant + intra-context
  density as strongest KB candidate evidence

> **KB methodological maturity continues**: Q9 methodology
> validated across 4 instances; sub-pattern surfaced through
> 3-instance Hybridized recurrence; promotion discipline
> consistently preserved.
