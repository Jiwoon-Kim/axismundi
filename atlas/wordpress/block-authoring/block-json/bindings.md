---
rule_id: block-authoring.block-json.bindings
domain: block-authoring
topic: runtime-authority-attachment
field_cluster: bindings
wp_min: "6.5"
wp_recommended: "6.5+"
status: evolving
language: json
sources:
  - url: https://developer.wordpress.org/news/2024/02/20/an-introduction-to-block-bindings/
    section: "WP Developer News — Introduction to Block Bindings (WP 6.5)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/
    section: "Block Bindings API reference"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/register_block_bindings_source/
    section: "register_block_bindings_source() — PHP API for source provider registration"
    captured: 2026-05-09
related:
  - block.json-attributes-core              # bindings reference attribute names declared here
  - block.dynamic-rendering                 # bindings interact with PHP render-time resolution
  - block.markup-representation             # bindings live in block instance comment-delimiter metadata
  - block.deprecation                       # binding-aware vs binding-less serialization compatibility
  - style-engine.preset-materialization     # parallel late-binding pattern (presets) — extended to runtime authority
  - (planned) data-layer.entity-resolution  # bindings consume entity sources resolved by data-layer
  - (planned) interactivity.runtime-state   # bindings introduce runtime authority that interactivity orchestrates
---

# RULE — block bindings — runtime authority attachment surface

## WHEN

A block attribute's VALUE should not come from authored literal
serialization but from a **runtime-resolved source** — typically
post meta, pattern overrides, or a custom-registered provider.
Use bindings when:

- A block displays content that lives in entity fields outside
  the block's own serialized markup (e.g., a heading bound to
  a custom post meta key).
- Pattern overrides require per-instance content variation
  while keeping pattern structure intact.
- An attribute's authority belongs to an external source the
  block should mirror / project / synchronize with.
- Editor mutations should propagate to entity persistence (when
  the source supports writable bindings).

This chunk introduces the **first runtime authority attachment
surface** in block-authoring. All prior block-authoring chunks
documented compile-time declarations (attributes, supports,
hierarchy, context). Bindings opens the runtime authority axis:
a block attribute can be **a hosted attachment point** rather
than a serialized value owner.

This is the **phase-transition chunk** marking KB's pivot from
compiler ontology to runtime systems ontology.

## SHAPE

### 1. Binding declaration — per-instance metadata

Bindings are declared in **block instance metadata** (the
serialized comment delimiter), NOT in block.json as a static
field:

```html
<!-- wp:image {
  "metadata": {
    "bindings": {
      "url": {
        "source": "core/post-meta",
        "args": { "key": "custom_image_url" }
      },
      "alt": {
        "source": "core/post-meta",
        "args": { "key": "custom_image_alt" }
      }
    }
  }
} -->
<figure class="wp-block-image">
  <img src="" alt="" />
</figure>
<!-- /wp:image -->
```

Per-attribute structure:
| key | meaning |
|---|---|
| `source` | Registered binding source name (e.g., `core/post-meta`) |
| `args` | Source-specific argument map (interpreted by the source provider) |

The block's serialized attribute values (`src=""`, `alt=""`)
become **placeholders**; the actual values are resolved at
render time by the source provider.

⚠ Whether block.json itself can declare DEFAULT bindings (e.g.,
"this attribute is always bound to post-meta key X by default")
versus only per-instance bindings — verification-needed.
Documented usage is per-instance.

### 2. Source provider abstraction

Sources are registered via PHP `register_block_bindings_source()`.
Documented core providers:

| provider | authority origin | typical use |
|---|---|---|
| `core/post-meta` | post entity meta field | bind block attribute to post custom field |
| `core/pattern-overrides` | pattern instance override | per-instance content variation in synced patterns |

Custom providers can be registered for arbitrary external
authority (CMS field plugins, headless data sources, computed
values).

A registered source provides:
- A `label` (human-readable name).
- A `get_value_callback` (function returning the resolved value
  given args + block context).
- Optionally a `set_value_callback` (function persisting a new
  value back to the source — for writable bindings).
- Optionally `uses_context` (block context dependencies the
  source needs).

The provider registry IS the **authority resolver registry** —
Gutenberg gains a runtime authority abstraction layer.

### 3. Binding directionality

Documented modes:

| mode | get_value_callback | set_value_callback | semantics |
|---|---|---|---|
| **read-only** | provided | not provided | external authority projects into block; editor cannot change |
| **writable** | provided | provided | editor mutations persist back via the source |
| **bidirectional** | provided | provided | full reactive synchronization (writable + provider may emit changes) |

Read vs write distinction is qualitative — read-only bindings
are simple projections; writable bindings introduce
**editor-to-entity persistence semantics**, fundamentally
different in complexity.

### 4. Attachment lifecycle

```
1. Schema declaration
   block.json declares attribute (e.g., "url": {"type": "string"})

2. Source registration
   PHP register_block_bindings_source() with name + callbacks

3. Binding metadata authoring
   block instance comment metadata.bindings.{attribute}.source declared

4. Runtime resolution
   On render: get_value_callback(args, context) returns value

5. Editor hydration
   Editor surfaces the resolved value in block UI; may show
   "bound" indicator on the attribute control

6. Optional persistence propagation (writable)
   On editor save: set_value_callback persists modified value
   back to the source

7. Re-render synchronization
   On source change (other request, other client): block
   re-renders with new resolved value
```

Bindings are NOT compile-time declaration; they are **runtime
attachment lifecycle** — declaration is one stage of many.

### What this is NOT

- NOT a block.json static field at the level of `supports` /
  `attributes` (per-instance metadata declarations are the
  primary form).
- NOT a styling mechanism (orthogonal to style-engine; bindings
  resolve VALUES, not styles).
- NOT a templating system (template parts / patterns occupy
  that role; bindings operate at the attribute level inside
  individual blocks).
- NOT bidirectional by default — many providers are read-only.
- NOT a substitute for `dynamic-rendering`. Dynamic rendering
  generates block HTML at request time; bindings resolve
  attribute values at render time. They COMPOSE: a dynamic
  block can have bound attributes.

## REQUIRES

- WP 6.5+ (block bindings API introduction).
- A registered source provider for any `source` value
  referenced in bindings metadata.
- The bound attribute MUST be declared in the block's
  attributes schema (bindings reference attribute NAMES).
- For writable bindings: source provider must register a
  `set_value_callback`.
- Source's `uses_context` (if any) must align with block
  context availability (see block.context for context
  propagation).
- Editor support for binding UI surfaces depends on block type
  + attribute control (verification-needed for exact UI behavior
  per attribute type).

## INVARIANTS

### 1. Bindings are authority attachments, not value declarations

The most load-bearing invariant of this chunk:

> A binding does NOT declare "this attribute equals X."
> A binding declares "this attribute IS HOSTED BY source S
> with arguments A."

The serialized attribute value becomes a placeholder; the
source becomes the authority. Reading bindings as "default
value declaration" misses the entire point — the value is
NEVER owned by the block, only HOSTED by it.

### 2. Bindings introduce runtime authority into block-authoring

All prior block-authoring authority was compile-time:
- block.json supports — compile-time exposure declaration
- block.json attributes — compile-time schema declaration
- block.json context — compile-time DI declaration
- block.json hierarchy — compile-time constraint declaration

Bindings is the FIRST block-authoring mechanism that introduces:
- Runtime entity resolution
- Mutable external authority
- Source provider registration as authority abstraction
- Reactive synchronization (where source supports it)

This is qualitatively different from prior fields. The
block-authoring bounded context is no longer purely declarative;
bindings opens a runtime authority pathway.

### 3. Attributes become attachment hosts rather than value owners

This is the **culmination of a KB-wide inversion** that has
been building:

| layer | inversion |
|---|---|
| settings | from "registry stores values" to "registry stores reference targets" (preset slugs) |
| styles | from "rules carry values" to "rules carry variable references" |
| variables | from "literals" to "deferred computation edges" |
| selectors | from "authored selectors" to "attachment declarations" |
| **bindings** | **from "attributes own values" to "attributes host attachment points"** |

Each layer's inversion was incremental; bindings completes the
pattern at the attribute layer. After bindings, "block attribute
holds a value" is the LIMITED case (no bindings present); the
GENERAL case is "attribute hosts a value-resolution contract."

### 4. Bindings separate serialization from authority

A block with bindings has TWO state surfaces:

| surface | content | authority |
|---|---|---|
| serialized markup | placeholder value (often empty / stale) | NOT authoritative |
| source-resolved state | runtime-fetched value | AUTHORITATIVE |

Saved markup is NO LONGER the source of truth for bound
attributes. The serialized state preserves binding metadata +
last-known value (for fallback / SEO crawlers); the
authoritative value lives in the source.

This separation is structural and connects to dynamic-rendering:
both share the principle that **rendered output is not
necessarily reconstructible from saved markup alone** — an
external authority must be consulted.

### 5. Source providers abstract authority origin

`register_block_bindings_source()` creates an abstraction layer:
blocks reference sources by NAME (`core/post-meta`,
`my-plugin/api-field`); the provider implements actual
resolution.

Implications:
- Multiple providers can coexist (post-meta + pattern-overrides
  + custom).
- Plugins can introduce new authority origins without modifying
  blocks (any block can target a new source if it knows the
  source's name).
- Source semantics (caching, async, write semantics) are
  encapsulated by the provider; the block doesn't see them.

This is Gutenberg's first **authority resolver registry** — a
plugin extension surface for introducing new runtime authority
sources.

### 6. Bindings create reactive edges inside the block graph

When source values can change (post meta updated by another
request, pattern override edited in another instance), bindings
create edges in the block graph that PROPAGATE changes:

```
source value changes
   ↓
all bound attribute resolutions update
   ↓
all blocks consuming those attributes re-render
   ↓
editor / frontend reflects new state
```

This is the first **reactive edge mechanism** in block-authoring.
Prior block authority was static (declared at block registration,
fixed for the block's lifetime). Bindings introduce runtime
graph mutation propagation.

### 7. Bidirectional bindings escalate synchronization complexity

Read-only bindings are **projections** — straightforward
authority transport from source to block.

Writable bindings introduce **synchronization complexity**:
- Optimistic vs pessimistic update strategies
- Conflict resolution when source changes mid-edit
- Persistence transactional semantics
- Editor UI feedback during async write
- Failure modes (network failure during write)

These are runtime systems concerns absent from read-only
projections. The mode shift from read to write is qualitative,
not incremental.

### 8. Bindings blur editor / runtime / persisted boundaries

Pre-bindings, three state surfaces were cleanly separated:
- Editor state (what the user is currently editing, in memory)
- Persisted entity (what's saved in the database)
- Rendered frontend (what the visitor sees)

Bindings make these CONVERGE:
- Editor state may be a hydrated projection of an entity field
  (bound attribute value).
- Persisted entity may be the authoritative state propagated
  from editor changes (writable binding).
- Rendered frontend may resolve the same source the editor
  hydrated from (consistency).

The clean editor/runtime/persistence boundary is now permeable
in both directions. This anticipates interactivity's full
reactive runtime, where boundaries become formally orchestrated.

### 9. Bindings are structurally late-bound

The late-binding preservation principle (established for CSS
variables in style-engine) extends to bindings at the attribute
authority level:

| layer | late-binding mechanism |
|---|---|
| CSS variables | `var(--wp--preset--*)` resolves at use site |
| preset references | `var:preset|color|primary` resolves at compile site |
| **bindings** | **source resolution at render time** |

Each layer defers commitment as long as possible. Bindings
defers attribute value commitment to render time (or even later
for reactive sources). The late-binding principle is now
documented across THREE layers: variables, references, bindings.

### 10. Bindings are the bridge from style graph compiler to reactive authority graph runtime

This invariant is the chunk's **phase-transition framing**:

> Bindings are the structural bridge between
> **Gutenberg as schema-driven runtime style graph compiler**
> (block-authoring + theme-config + style-engine, established
> in Phases 1-6)
> and
> **Gutenberg as reactive authority graph runtime**
> (bindings + interactivity + data-layer, beginning here).

The expansion is additive, not replacement: Gutenberg remains
a style graph compiler for visual concerns; it ADDS reactive
authority graph runtime for state / data / external concerns.

This invariant marks a KB-level phase transition. After this
chunk, KB ontology axes shift:
- From "what authority exists?" to "what authorities are
  attached to what?"
- From "how do values realize?" to "how do values resolve?"
- From "compile-time declaration" to "runtime resolution"
- From "block-as-content" to "block-as-attachment-host"

## VERIFICATION NEEDED

bindings is `status: evolving` — the API is recent (WP 6.5+)
and runtime details are implementation-derived. Specific
items requiring verification before reliance:

- Whether block.json itself supports default-binding
  declarations (vs per-instance metadata only).
- Cache invalidation semantics — when do source resolutions
  re-fetch?
- Async source provider support — can `get_value_callback`
  return a Promise / deferred value?
- Editor hydration timing — when in the editor lifecycle are
  bindings resolved?
- Editor / frontend parity — does the editor resolve bindings
  identically to PHP render?
- Optimistic update strategy for writable bindings — does the
  editor display optimistically before persistence completes?
- Conflict resolution — what happens if source changes during
  editor session?
- Source failure fallback — what does the editor / frontend
  show when `get_value_callback` errors?
- Transactional persistence semantics — multi-attribute write
  atomicity.
- Pattern override binding behavior — exact composition of
  bindings + pattern override semantics.
- Block validation interaction — does deprecation handle
  binding-less → bound migration?
- Performance characteristics — N+1 queries when many bound
  blocks share source type?
- Capability gating — which user roles can resolve / persist
  through which sources?

The "evolving" status reflects this — APIs may shift, semantics
may clarify, edge cases may resolve in subsequent WP versions.

## ANTIPATTERNS

- ❌ Treating the serialized attribute value as authoritative
  for bound attributes. The serialized value is a placeholder /
  fallback; the source is authoritative.
- ❌ Hardcoding a source name in block code expecting the
  provider to always exist. Sources are plugin-registered;
  unregistered sources cause binding resolution to fail
  silently or fall back.
- ❌ Using bindings for compile-time defaults. The mechanism is
  for runtime resolution; defaults belong in
  `attributes.{name}.default`.
- ❌ Designing bidirectional bindings without considering
  conflict resolution. Multi-client editing or async source
  changes will surface synchronization issues.
- ❌ Assuming bindings work pre-WP 6.5. Earlier versions ignore
  the metadata; bound attributes will use literal serialized
  values.
- ❌ Binding attributes whose values participate in cascade
  arbitration (e.g., supports-derived classes) without
  considering style-engine compilation timing. Bindings resolve
  at render; style-engine compiles at theme.json processing —
  timing mismatches are possible.
- ❌ Using bindings to inject HTML / executable content. Source
  values are attribute strings; HTML injection bypasses block's
  HTML sanitization expectations.
- ❌ Migrating existing literal-value blocks to bound blocks
  without versioning the block. Older renders / parsers may
  not handle binding metadata; deprecation may be needed for
  backward compatibility.
- ❌ Treating bindings as a replacement for dynamic blocks. The
  two mechanisms compose: dynamic blocks generate HTML at
  render, bindings resolve attributes at render. A bound block
  can be static-rendered (binding resolves attributes); a
  dynamic block can have bound attributes (PHP render
  consumes resolved values).
- ❌ Registering source providers without `uses_context` when
  the provider needs block context. Context propagation
  (block.context) must be respected; missing context can cause
  resolution failures.

## RELATED

- `block.json-attributes-core` — bindings reference attribute
  NAMES declared here. The attribute schema must exist; bindings
  re-route the VALUE source.
- `block.dynamic-rendering` — bindings + dynamic rendering
  compose. PHP render consumes bound attribute values; both
  share "rendered output not necessarily reconstructible from
  saved markup" principle.
- `block.markup-representation` — bindings live in the comment
  delimiter's `metadata.bindings` structure. Markup
  representation captures the syntactic surface; this chunk
  documents the runtime semantics.
- `block.deprecation` — binding-aware vs binding-less
  serialization compatibility. Migrating existing posts to use
  bindings may require deprecation entries.
- `style-engine.preset-materialization` — parallel late-binding
  pattern at the style authority layer. Bindings extends the
  pattern to attribute authority.
- `block.json-context` — providesContext / usesContext for
  block-tree DI. Source providers' `uses_context` field follows
  the same context propagation model.
- (planned) `data-layer.entity-resolution` — bindings consume
  entity sources (post-meta etc.); the entity resolution
  mechanisms live in data-layer bounded context.
- (planned) `interactivity.runtime-state` — interactivity API
  orchestrates the runtime state that bindings sources can
  participate in. Together they form the reactive runtime.
- (planned) `plugin-dev.register-block-bindings-source` —
  PHP API for registering custom source providers.

## META

**Phase transition chunk for KB ontology:**

This chunk is the structural bridge between two KB phases:

```
Phase 1-6 (CLOSED):  schema-driven runtime style graph compiler
                     ┌──────────────────────────────────────────┐
                     │ block-authoring → declaration authority  │
                     │ theme-config    → configuration authority│
                     │ style-engine    → realization authority  │
                     └──────────────────────────────────────────┘
                                          ↓
Phase 7+ (BEGINS):   reactive authority graph runtime
                     ┌──────────────────────────────────────────┐
                     │ bindings        → runtime authority      │
                     │                   attachment             │
                     │ data-layer      → entity resolution      │
                     │ interactivity   → reactive orchestration │
                     └──────────────────────────────────────────┘
```

**KB framing extension (anticipated, will be confirmed in
interactivity bounded context):**

> Gutenberg's compiler architecture expands from
> **style realization** (Phases 1-6)
> to **runtime authority orchestration** (Phases 7+).
>
> Block-authoring + theme-config + style-engine compose a
> compile-time + render-time visual compiler. Bindings +
> data-layer + interactivity compose a runtime + reactive
> + persisted authority graph. Together they describe
> Gutenberg as a **two-axis system**: visual compilation
> + state orchestration.

**DSL extension applicability:**

VERIFICATION NEEDED + META sections were originally
style-engine-bounded-context-specific. With bindings, the
applicability extends:

> The DSL extension applies to chunks documenting
> **runtime / implementation-derived authority surfaces**,
> regardless of bounded context. Bindings is in
> block-authoring but introduces runtime ontology with
> the same epistemic character as style-engine chunks.

Future chunks meeting this criterion (interactivity, data-layer
runtime, dynamic-rendering deeper-dive) should use the
extended DSL.

**Anticipated next: interactivity bounded context entry**

Bindings sets up the framing. The next major chunk would be
interactivity entry — likely starting with
`interactivity.runtime-state` or
`interactivity.directive-protocol`. Specific entry chunk to
be determined; bindings has prepared the ontology ground.

**Block-authoring bounded context status:**

After this chunk, block-authoring is **substantively closed**.
All major top-level block.json fields are documented; all
authority axes (declaration / capability / composition /
attribute / context / variation / transformation / deprecation
/ runtime attachment) are covered. Future block-authoring
chunks would be: deeper revisits of specific subareas, retro
patches as downstream contexts close, or coverage of new fields
introduced in future WP versions.
