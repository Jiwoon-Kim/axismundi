---
rule_id: editor-customization.block-filters
domain: editor-customization
topic: lifecycle-interception
field_cluster: governance-surfaces
wp_min: "5.0"
wp_recommended: "5.0+"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
    section: "Block Filters — registerBlockType / BlockEdit / BlockListBlock / save filters"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/
    section: "@wordpress/hooks — addFilter / removeFilter / priority"
    captured: 2026-05-09
related:
  - block-authoring.block-json.basic-metadata     # blocks.registerBlockType filter intercepts at registration boundary
  - block-authoring.edit-save-components          # editor.BlockEdit / blocks.getSaveElement intercept these
  - block-authoring.wrapper-attributes            # editor.BlockListBlock / blocks.getSaveContent.extraProps mutate wrapper
  - block-authoring.markup-representation         # save-time filters affect serialized IR
  - plugin-dev.security-boundaries                # filters expand attack surface (interception debt)
  - plugin-dev.capabilities-and-roles             # filters that mutate capability-relevant fields cross security doctrine
  - _meta.structural-patterns                     # constitutional law layer applied here
  - (planned) editor-customization.slotfills      # adjacent: slot-fill governance (different interception model)
  - (planned) editor-customization.editor-hooks   # adjacent: editor data store hooks
---

# RULE — block filters — authority interception governance

## WHEN

A plugin or theme needs to **intercept and mutate** an aspect
of WordPress block lifecycle without owning the block —
modify how a registered block is configured, how its editor
UI renders, what classes / props attach to its rendered DOM,
or what its save output looks like.

Use block filters when:
- Adding inspector controls / styles to existing blocks (your
  own or core).
- Injecting custom attributes into already-registered blocks.
- Wrapping all blocks of a type with custom DOM in the editor.
- Adding HTML attributes to saved markup.
- Conditionally disabling block features cross-cutting.

This is the **first chunk in editor-customization bounded
context** AND the **first chunk authored under the constitutional
protocol** (`_meta/structural-patterns.md`). It is positioned
deliberately as a constitutional field test: predicted laws are
explicit; observed manifestations are documented; the chunk's
own success demonstrates whether structural-patterns has
predictive power.

The doctrinal backbone for editor-customization (established
here):

> **Plugin-dev extends authority outward;**
> **editor-customization intercepts authority inward.**

Where plugin-dev (5 federation chunks + 2 doctrines) federates
NEW authority surfaces into WordPress, editor-customization
governs how EXISTING authority surfaces may be intercepted,
reshaped, and conditionally re-expressed across authoring
lifecycles. Block filters are this governance's primary
mechanism.

## SHAPE

### A. Lifecycle interception topology

Block filters intercept at **5 distinct lifecycle phases**.
Each phase has its own filter hook + intervention scope:

```
Block lifecycle phases + interception filters:

1. REGISTRATION
   blocks.registerBlockType
   ↓ intercepts: settings object before block is registered
   ↓ scope: schema mutation (attributes, supports, edit, save)

2. EDITOR RENDERING (component composition)
   editor.BlockEdit
   ↓ intercepts: BlockEdit React component
   ↓ scope: editor UI mutation (HOC pattern)

3. EDITOR RENDERING (block list wrapping)
   editor.BlockListBlock
   ↓ intercepts: rendered block in editor canvas
   ↓ scope: outer DOM wrapping in editor only

4. SAVE PROPS (serialization wrapper attributes)
   blocks.getSaveContent.extraProps
   ↓ intercepts: props applied to saved block wrapper
   ↓ scope: serialization-time wrapper mutation

5. SAVE ELEMENT (serialization element)
   blocks.getSaveElement
   ↓ intercepts: full save-output React element
   ↓ scope: serialization-time DOM tree mutation
```

5 lifecycle boundaries; 5 distinct intervention surfaces.
Each filter operates at ONE phase only.

### B. Filter mechanics

```js
import { addFilter, removeFilter } from '@wordpress/hooks';

// Add filter at default priority (10)
addFilter(
    'blocks.registerBlockType',           // hook name
    'my-plugin/add-attribute-to-image',   // namespace (vendor/identifier)
    function ( settings, name ) {
        if ( name !== 'core/image' ) {
            return settings;
        }
        return {
            ...settings,
            attributes: {
                ...settings.attributes,
                myCustomAttr: {
                    type: 'string',
                    default: '',
                },
            },
        };
    },
    10                                    // priority (optional, default 10)
);

// Remove filter
removeFilter(
    'blocks.registerBlockType',
    'my-plugin/add-attribute-to-image'
);
```

| component | role |
|---|---|
| `hook` (1st arg) | which lifecycle phase to intercept |
| `namespace` (2nd arg) | required `vendor/identifier` — collision-avoidance + removal handle |
| `callback` (3rd arg) | mutation function; receives lifecycle-specific args |
| `priority` (4th arg) | execution order (lower = earlier; default 10) |

Filter callbacks receive lifecycle-specific arguments AND must
return the (possibly mutated) value. Failure to return cancels
mutation propagation through subsequent filters.

### C. Governance surfaces — what each filter mutates

```js
// 1. SCHEMA MUTATION — registerBlockType filter
addFilter( 'blocks.registerBlockType', 'my-plugin/x', ( settings, name ) => {
    // Mutate: attributes, supports, edit, save, deprecated, etc.
    return modifiedSettings;
} );

// 2. UI MUTATION — BlockEdit filter (HOC pattern)
addFilter( 'editor.BlockEdit', 'my-plugin/x',
    createHigherOrderComponent( ( BlockEdit ) => ( props ) => {
        return (
            <Fragment>
                <BlockEdit {...props} />
                <InspectorControls>
                    <PanelBody>...custom controls...</PanelBody>
                </InspectorControls>
            </Fragment>
        );
    }, 'withCustomControls' )
);

// 3. EDITOR DOM MUTATION — BlockListBlock filter
addFilter( 'editor.BlockListBlock', 'my-plugin/x',
    createHigherOrderComponent( ( BlockListBlock ) => ( props ) => {
        return <div className="my-wrapper"><BlockListBlock {...props} /></div>;
    }, 'withWrapper' )
);

// 4. WRAPPER PROPS MUTATION — getSaveContent.extraProps
addFilter( 'blocks.getSaveContent.extraProps', 'my-plugin/x',
    ( props, blockType, attributes ) => {
        return { ...props, 'data-custom': attributes.myCustomAttr };
    }
);

// 5. SAVE ELEMENT MUTATION — getSaveElement
addFilter( 'blocks.getSaveElement', 'my-plugin/x',
    ( element, blockType, attributes ) => {
        // Wrap or transform the entire save output
        return <div className="wrapped">{ element }</div>;
    }
);
```

Different filters mutate **structurally different concerns**:
schema (registration) / UI (editor) / editor wrapper /
serialization wrapper / serialization element. They do NOT
overlap — choosing the wrong filter for the intent produces
non-functional or incorrect customization.

### D. Responsibility distribution

Asymmetric (per security-boundaries pattern):

| actor | provides |
|---|---|
| **Core** | filter infrastructure (@wordpress/hooks), 5 documented filter points, predictable invocation order |
| **Plugins / Themes** | filter logic, namespace registration, priority decisions, validation parity (save filters affect persistence) |
| **Block authors** | block definitions that filters mutate (often without consent) |
| **Editor users** | downstream effect recipients |

Critical asymmetry: **block authors do NOT consent to filters
applied to their blocks.** Filters operate on registered blocks
without the original block author's involvement. This makes
filter authoring an **architecture-level governance act**, not
a local customization choice.

### E. Failure surfaces — interception debt

Each interception layer introduces governance power alongside
**interception debt**:

| failure mode | symptom | dimension violated |
|---|---|---|
| **lifecycle divergence** | save filter changes output; existing posts invalidate | schema-validation contract broken |
| **priority collisions** | multiple plugins filter at default priority; ordering undefined | governance arbitration ambiguity |
| **HOC chain bloat** | many BlockEdit filters compose; performance + debugging cost | composition unbounded |
| **frontend / editor parity** | BlockListBlock changes editor view but NOT frontend; user confusion | editor ≠ frontend reality divergence |
| **registration mutation overreach** | mutate core block schema; plugin uninstalled but blocks corrupted | persistence dependency on plugin presence |
| **silent failure** | callback throws; filter chain breaks; no error surface | observability gap |
| **namespace omission** | filter cannot be removed; persists permanently | governance reversibility lost |

**Interception debt** mirrors security-boundaries' "security
debt" pattern — accumulated governance complexity from
incremental authority interception that is not retired or
revisited. Plugin filters added casually accumulate; one
plugin's filter at priority 10 may block another plugin's
filter at priority 11 from running on already-modified
settings.

## REQUIRES

- @wordpress/hooks runtime loaded (default in editor environment;
  may need explicit dependency in scripts).
- Filter registration MUST happen BEFORE the lifecycle phase
  triggers:
  - `blocks.registerBlockType`: filter MUST register before
    `registerBlockType()` calls run for target blocks.
  - `editor.BlockEdit` / `BlockListBlock`: must register
    before block render.
  - Save filters: must register before save serialization
    runs (typically before editor save, AND consistent across
    page loads to prevent validation failures).
- Namespace MUST follow `vendor/identifier` format. Without
  namespace, removeFilter cannot reference the registration.
- Callback MUST return the (possibly mutated) value; returning
  undefined cancels propagation.
- For save filters: filter MUST be deterministic and consistent
  across page loads — block validation regenerates save output
  and compares; non-deterministic filter output causes
  validation failures.
- ⚠ Specific behaviors: filter ordering with mixed-priority
  registrations, async filter handling, removeFilter semantics
  for filters added in different load contexts, performance
  characteristics with deeply-composed HOC chains —
  verification-needed.

## INVARIANTS

### 1. Block filters are authority interception surfaces, NOT cosmetic hooks

The load-bearing reframing:

> Block filters do NOT add features to blocks. They **intercept
> existing block lifecycle authority** at specific phases and
> mutate the authority's expression. The block's authority
> originates from registration; filters reshape it without
> assuming ownership.

Reading filters as "ways to add features to blocks" misses
the interception ontology. The block author DECLARED the
authority; the filter author RESHAPES it.

### 2. Editor customization governs block lifecycle phases through interception, NOT ownership

Plugin-dev introduced new authority (federation: external
extension). Editor-customization governs existing authority
(interception: internal modulation):

| approach | mechanism | example |
|---|---|---|
| **federation** (plugin-dev) | new authority surfaces | register_block_bindings_source declares NEW source |
| **interception** (editor-customization) | mutate existing authority | filter mutates EXISTING block's settings |

The two patterns are complementary, not competing. plugin-dev
extends WordPress outward; editor-customization modulates
WordPress inward. Together they form complete extensibility:
new authority creation + existing authority modulation.

### 3. Filter layers create governance relationships between actors and block definitions

Filters are NOT one-time actions; they are **persistent
governance relationships**:

```
Block author → declares block (authority origin)
   ↓
Filter author 1 → intercepts at priority 10
Filter author 2 → intercepts at priority 11
Filter author 3 → intercepts at priority 12
   ↓
Editor user → experiences composed result
```

The filter chain is a **relationship graph** between actors
and the block definition. Each filter is a relationship edge;
the resolved block configuration is the graph's emergent
output.

This is structurally relationship-centric (Law 5 — Entity →
Relationship Pivot — see invariant 8).

### 4. Priority ordering functions as governance arbitration

Multiple filters at the same hook compose via **priority
ordering** (lower priority = earlier execution):

| priority value | execution order |
|---|---|
| 1-9 | early (preempt others) |
| 10 (default) | most filters |
| 11+ | late (override others' modifications) |

Priority is the **governance arbitration mechanism** for
filter composition. This applies Law 4 (Arbitration Compiler)
at the filter layer:

> Multiple filter authority claims → priority-ordered
> execution → composed result.

Like cascade-aggregation arbitrates CSS authority and
map_meta_cap arbitrates capability authority, filter priority
arbitrates interception authority. Same arbitration compiler
pattern, different domain.

### 5. Different filters target distinct lifecycle boundaries (registration / edit / save)

The 5 filter points correspond to 3 distinct **lifecycle
boundaries** (Law 6 — Compiler ↔ Runtime Split):

| boundary | filters | scope |
|---|---|---|
| **registration time** | `blocks.registerBlockType` | block schema configuration (declarative) |
| **edit / runtime** | `editor.BlockEdit`, `editor.BlockListBlock` | editor render output (runtime) |
| **save / serialization** | `blocks.getSaveContent.extraProps`, `blocks.getSaveElement` | serialized output (compiler-output) |

The boundaries align with the compiler/runtime split:
registration = declarative source; edit = editor runtime
execution; save = serialization compilation. Filters at one
boundary do NOT affect the others — a registration filter
that adds an attribute does not automatically inject UI for
that attribute (need editor.BlockEdit filter too).

### 6. Customization may mutate declaration, rendering, or serialization independently

The independence of lifecycle boundaries (invariant 5)
implies that **customization is decomposable**: a feature
addition may require ALL THREE boundary filters or only one,
depending on the desired effect surface.

| desired effect | required filters |
|---|---|
| Add attribute (data-only) | registerBlockType (schema only) |
| Add attribute + UI | registerBlockType + BlockEdit |
| Add attribute + UI + saved markup | registerBlockType + BlockEdit + getSaveContent.extraProps |
| Wrap block with custom UI in editor only | BlockListBlock |
| Wrap block in saved output | getSaveElement |

This mapping is not a checklist — it's a **constitutional
choice**: where in the lifecycle should the customization
take effect? Each choice has different persistence /
visibility / divergence implications.

### 7. Interception expands governance power while increasing divergence risk

Each filter added expands what the customizing actor can
govern — AND introduces a new divergence vector:

- **editor / frontend divergence**: BlockListBlock affects
  editor only; frontend may not match.
- **author / consumer divergence**: filter mutates settings
  block author declared; downstream consumers see modified
  contract.
- **plugin / state divergence**: filter changes save output;
  posts saved with filter active become invalid if filter
  removed.
- **priority / order divergence**: same hook + same priority
  + multiple plugins = undefined ordering.

This is **interception debt** — the governance complexity
accumulated by interception layers. The debt grows with each
filter; it must be retired (removed when not needed) or it
permanently constrains the system. Mirrors security-boundaries'
"security debt" pattern.

### 8. Editor-customization operationally tests Entity → Relationship Pivot recurrence by shifting customization from isolated block mutation toward lifecycle governance relationships

> This invariant IS the constitutional field test predicted
> in `_meta/structural-patterns.md` Section E.

Pre-block-filters reading: "filters mutate individual blocks"
(entity-centric — "what does this filter do to THIS block?").

Post-block-filters reading: filters create **lifecycle
governance relationships** (relationship-centric — "what
authority relationship exists between this filter and the
block lifecycle phase it intercepts?").

The pivot from isolated block mutation to lifecycle governance
relationships is observable in this chunk. Per the constitutional
test protocol, this manifests Law 5 in editor-customization —
but **promotes the pattern from "2 contexts verified" to
"3 contexts verified" pending audit**.

The full law-promotion path:
- 2-context observation (style-engine + plugin-dev) = KB-Wide
  law (already promoted in structural-patterns audit).
- 3-context observation (this chunk) = reaffirms KB-Wide
  status; deepens predictive confidence.
- Future contexts (site-building / interactivity) — additional
  test surfaces.

This invariant explicitly DOES NOT claim a NEW law. It
documents that the EXISTING Entity → Relationship Pivot law
manifests here, as predicted. The constitutional protocol
worked.

## ANTIPATTERNS

- ❌ **`editor.BlockEdit` = universal solution**. BlockEdit
  filter affects editor UI only; doesn't change frontend
  rendering, doesn't add to saved markup, doesn't extend
  attributes. For each effect surface, the appropriate filter
  must be selected.
- ❌ **Save filter applied without validation implications
  consideration**. Save filters change serialized output;
  WordPress's block validation regenerates save output and
  compares. Filter that produces different output than current
  invalidates existing posts. Use deprecated[] in registration
  to handle migrations, or scope save changes to new posts only.
- ❌ **Priority irrelevant**. Multiple plugins filtering the
  same hook at default priority 10 have undefined relative
  order. Ecosystem hygiene: choose explicit priorities to
  signal intended composition position.
- ❌ **UI mutation = persisted mutation**. BlockListBlock
  changes editor canvas appearance only. Frontend rendering
  uses save output; if save output is unchanged, frontend is
  unchanged. To affect both, mutate at editor + save layers.
- ❌ **Filter namespace optional**. Without `vendor/identifier`
  namespace, the filter cannot be removed via removeFilter.
  Effectively permanent registration; ecosystem antipattern.
- ❌ **Frontend parity automatic**. Editor and frontend can
  diverge if filters apply asymmetrically. The editor preview
  showing X does NOT guarantee the frontend renders X.
- ❌ **One filter layer sufficient**. Many customizations
  require composed filters across lifecycle boundaries
  (e.g., new attribute + UI to edit it + serialize it = 3
  filters minimum).
- ❌ **Registration mutation harmless**. Filtering
  registerBlockType mutates the block's schema globally for
  the session. If plugin is deactivated, blocks may have
  attributes referenced in saved posts that no longer exist
  in the schema — invalid block state.
- ❌ **Filter authoring without considering interception
  debt**. Each filter adds to ecosystem complexity. Audit
  whether the filter is still needed; remove when obsolete.
- ❌ **Mutating other plugin's blocks without coordination**.
  Filters on `core/*` blocks are common; filters on
  `other-plugin/*` blocks may break the other plugin
  unexpectedly.
- ❌ **Skipping namespace when intent is one-time**. Even
  one-time customizations should have removeFilter capability;
  testing / debugging require it.

## RELATED

- `block-authoring.block-json.basic-metadata` —
  blocks.registerBlockType filter intercepts at registration
  boundary. The filter modifies the settings being registered.
- `block-authoring.edit-save-components` — editor.BlockEdit
  filter wraps the BlockEdit React component;
  blocks.getSaveElement filter intercepts the save() output.
  The filters intercept these contracts.
- `block-authoring.wrapper-attributes` — editor.BlockListBlock
  + blocks.getSaveContent.extraProps mutate wrapper element
  props. The wrapper-attributes chunk documented the carrier
  layer; filters here document the interception layer.
- `block-authoring.markup-representation` — save-time filters
  affect serialized IR. Block validation regenerates from
  save() and compares; filter consistency is critical to
  avoid validation failures.
- `plugin-dev.security-boundaries` — filters expand attack
  surface (interception debt mirrors security debt). Filter
  authors take on governance responsibility.
- `plugin-dev.capabilities-and-roles` — filters that mutate
  capability-relevant fields (e.g., supports.lock or
  blockHooks behavior) cross security doctrine boundaries.
- `_meta.structural-patterns` — constitutional law layer
  applied here. This chunk is the first authored under the
  constitutional protocol.
- (planned) `editor-customization.slotfills` — adjacent
  bounded-context mechanism. Slot-fill governance is a
  different interception model (DOM injection points vs
  lifecycle hooks).
- (planned) `editor-customization.editor-hooks` — adjacent
  bounded-context mechanism. Editor data store hooks
  (subscriptions to editor state) operate at runtime, not
  lifecycle.

## META

**editor-customization bounded context — first chunk; first
chunk authored under constitutional protocol.**

**Doctrinal backbone for editor-customization:**

> **Plugin-dev extends authority outward;**
> **editor-customization intercepts authority inward.**

Plugin-dev (5 federation chunks + 2 doctrines) federates NEW
authority surfaces into WordPress's authority architecture.
Editor-customization governs how EXISTING authority surfaces
may be intercepted, reshaped, and conditionally re-expressed
across authoring lifecycles.

The two bounded contexts are complementary:
- plugin-dev = external federation pattern
- editor-customization = internal governance modulation pattern

Together they form WordPress's complete extensibility surface:
new authority creation + existing authority modulation.

### Constitutional Field Test (first deployment)

This chunk is authored under the protocol established in
`_meta/structural-patterns.md` Section E. Pre-write predictions
documented; post-write observations documented.

**Predicted vs observed law manifestation:**

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 1 — Declaration ≠ Exposure** | Strong | Filter declaration ≠ filter execution scope ≠ filter effect surface (registration/edit/save independent) | **Confirmed (3rd-form: declaration ≠ exposure ≠ effect)** |
| **Law 4 — Arbitration Compiler** | Strong | Priority ordering = governance arbitration of competing filter authority | **Confirmed (3rd domain after style-engine + plugin-dev)** |
| **Law 5 — Entity → Relationship Pivot** | Moderate-Strong | Filter chain = lifecycle governance relationships; pivot from isolated mutation to relationship graph | **Confirmed (context-local, broader recurrence pending site-building / interactivity verification)** |
| **Law 6 — Compiler ↔ Runtime Split** | Strong | 5 filter points map to registration / runtime / serialization boundaries | **Confirmed (filter boundaries align with split)** |
| **Law 2 — HTML Primacy** | Implicit | Save filters mutate HTML; doctrine respected (save output is HTML, not virtual) | **Confirmed (implicit doctrine adherence)** |
| **Law 3 — Authority Continuity** | Secondary | Filter chain preserves authority transit through lifecycle phases | **Partial (mostly implicit; lifecycle continuity not chunk's primary framing)** |

**Constitutional protocol verdict: SUCCESS.** The 4 strongly-
predicted laws (1, 4, 5, 6) all manifested in this chunk's
content. Predictive frontier section in structural-patterns
correctly anticipated editor-customization's constitutional
character.

### Hypothetical 7th law candidate — "Authority Interception Surface"

Per `_meta/structural-patterns.md` Section A promotion criteria,
a Local Pattern requires recurrence in a 2nd bounded context
before promotion to Recurring; Recurring requires audit
verification before promotion to KB-Wide.

> **Editor-customization surfaces "authority interception
> surfaces" as a potential seventh structural law candidate,**
> **pending recurrence beyond this bounded context.**

The candidate pattern: **mechanisms that intercept and reshape
existing authority without owning it**. Observed exclusively
in editor-customization at this writing.

Candidate verification path:
- Local (current): block filters are interception surfaces.
- Recurring (test): does interception surface recur in
  site-building? interactivity? admin-ui?
- KB-Wide (verify): if Recurring confirmed, audit verifies
  with explicit instances.

**This chunk does NOT promote the candidate to law.** It
surfaces the candidate explicitly so future chunks can test
recurrence. If editor-customization remains the only context
exhibiting the pattern, it stays Local. If site-building
filters / admin-ui notice filters / similar mechanisms
manifest in 2+ contexts, promotion to Recurring is appropriate.

### Constitutional protocol observations

**What worked:**
- Pre-write predictions (5 laws + 1 implicit doctrine) gave
  the chunk a structural skeleton before authoring.
- Constitutional Field Test table forced explicit verification
  of each prediction during writing.
- Hypothetical law candidate process kept promotion discipline
  intact (no premature elevation).

**What to refine for next constitutional-protocol chunk:**
- Consider documenting "Strength" rating per law (Strong /
  Moderate / Implicit) consistently across chunks.
- Consider "Verification depth" notation (1-context observed
  / 2-context confirmed / audit-verified) for transparency.

These refinements may surface as future spec updates; not
required for current chunk validity.

### KB-level framing extension

editor-customization bounded context establishes the
**internal governance modulation** counterpart to plugin-dev's
external federation. The two patterns close WordPress's
extensibility ontology:

| pattern | character | example mechanisms |
|---|---|---|
| **External federation** (plugin-dev) | new authority surfaces declared into WordPress | register_block_bindings_source / register_meta / register_post_type / register_taxonomy / register_rest_route |
| **Internal modulation** (editor-customization) | existing authority surfaces intercepted / reshaped / conditionally re-expressed | block filters (this chunk), slot-fills, editor hooks (planned) |

Both are governed by capabilities-and-roles (authority
adjudication) and security-boundaries (governance doctrine);
both can introduce interception/security debt; both require
namespace hygiene + responsibility distribution.

### KB self-evaluation against spec criteria

- ✅ Accuracy — describes documented filter mechanisms.
- ✅ Structural fit — establishes editor-customization
  bounded context tone; positions filters as interception
  governance.
- ✅ Reusability — uses authority ontology glossary
  (interception / governance / arbitration / debt /
  modulation).
- ✅ Phase fit — first chunk in new bounded context;
  references plugin-dev (paired) and structural-patterns
  (constitutional foundation).
- ✅ Doctrine respect — HTML primacy (filters operate on
  HTML output, don't replace HTML); declaration ≠ exposure
  (filter declaration vs effect surface separation).

### Status: `stable`

WordPress hooks/filter system is mature (WP 1.x lineage).
Block filter API mature since WP 5.0 (Gutenberg merge).
Verification-needed catalog covers specific behaviors
(performance, async, cross-load registration semantics) but
core API is stable.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line backbone

> **Block filters do not create new authority — they intercept,**
> **arbitrate, and reshape existing authority across block**
> **lifecycle boundaries.**

### Anticipated next chunks (priority)

1. **`editor-customization.slotfills`** — adjacent governance
   pattern (DOM injection points vs lifecycle hooks). Tests
   whether interception surface candidate appears here too;
   may promote to Recurring.

2. **`editor-customization.editor-hooks`** — editor data
   store hooks; runtime subscriptions vs lifecycle filters.

3. **Other bounded contexts** (additive) — site-building /
   i18n / admin-ui / build-tooling. Constitutional protocol
   continues per chunk.

4. **`plugin-dev.nonces`** — completes plugin-dev security
   primitive trio. Self-contained; can be intermixed.

Recommended next: `editor-customization.slotfills` (immediate
recurrence test for "authority interception surface" candidate).
