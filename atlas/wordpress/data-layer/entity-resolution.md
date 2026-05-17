---
rule_id: data-layer.entity-resolution
domain: data-layer
topic: authority-resolution
field_cluster: entity-graph
wp_min: "verification-needed"
wp_recommended: ""
status: evolving
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core/
    section: "@wordpress/core-data — entity store, selectors, actions"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-core-data/
    section: "core-data package reference — entity records, edits, persistence"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/rest-api/reference/
    section: "WordPress REST API reference — endpoint structure for entity types"
    captured: 2026-05-09
related:
  - block-authoring.block-json.bindings        # bindings consume entity sources resolved here
  - block.dynamic-rendering                    # PHP renders against entity state
  - block.json-attributes-core                 # attributes hosted by bindings project entity values
  - block.json-context                         # block context propagation pairs with entity context
  - (planned) interactivity.runtime-state      # interactivity orchestrates reactive state including entity subscriptions
  - (planned) data-layer.persistence           # save dispatch / REST mutation — separate chunk
  - (planned) data-layer.entity-types          # specific entity type behaviors (post / template / user)
---

# RULE — entity resolution — runtime authority substrate

## WHEN

Asking how WordPress entities (posts, templates, template parts,
users, site settings, taxonomies) become available as runtime
state inside the block editor or any client consuming the
WordPress data layer. Use this chunk to understand:

- Why `getEntityRecord('postType', 'post', 123)` is not just a
  REST fetch — it's a subscription to a reactive entity store.
- How block bindings' `core/post-meta` source actually resolves
  values (this chunk documents the substrate bindings depend on).
- The distinction between persisted entity state (canonical),
  edited entity state (buffered), and transient editor state.
- Why selectors in data-layer are NOT CSS selectors but
  authority queries against the entity store.
- Why "save" in WordPress is not a single transaction but a
  pipeline crossing multiple boundaries.

This chunk introduces the **second bounded context of Phase 7**
(reactive authority graph runtime). Bindings opened the runtime
authority axis at the attribute level; data-layer opens the
**entity authority substrate** that bindings (and interactivity)
depend on.

## SHAPE

### 1. Entity abstraction — what authoritative subjects exist

WordPress's entity model documented through @wordpress/core-data:

| entity kind | type examples | authority domain |
|---|---|---|
| `postType` | `post`, `page`, `wp_template`, `wp_template_part`, `wp_block` (reusable), CPTs | content authority |
| `taxonomy` | `category`, `post_tag`, custom taxonomies | classification authority |
| `root` | `site`, `user`, `theme`, `menu`, `comment`, `widget` | global / identity authority |

Each entity has a `kind` (top-level grouping) + `name` (specific
type). Records within an entity type are addressed by ID.

This is the first KB chunk where Gutenberg's runtime becomes
**entity-centric** — not block-centric, not preset-centric, but
focused on authoritative mutable subjects with identity, fields,
and persistence.

### 2. Resolver pipeline — how selectors trigger fetches

```js
// JS-side consumption
import { useSelect } from '@wordpress/data';

const post = useSelect( ( select ) =>
  select( 'core' ).getEntityRecord( 'postType', 'post', 123 )
);
```

```
selector invocation (getEntityRecord)
   ↓
store cache check
   ↓ (miss)
resolver dispatch (auto-triggered for unresolved selectors)
   ↓
REST API fetch (GET /wp/v2/posts/123)
   ↓
response normalization + store insertion
   ↓
selector re-evaluates with resolved data
   ↓
subscriber components receive update
```

The selector / resolver split is core: **selectors are
synchronous reads from the store; resolvers are async fetchers
triggered when selectors return unresolved data**. The first
selector call returns `null`/`undefined`; the resolver fetches;
the selector re-emits the resolved value once data arrives.

### 3. Store architecture — three state layers per entity

```
entity record state (3 layers per entity, per ID):
┌──────────────────────────────────────────────────┐
│ 1. Persisted record                              │
│    Last server-confirmed state                   │
│    Source: REST GET response                     │
│    Mutated by: save() success                    │
├──────────────────────────────────────────────────┤
│ 2. Edited record (buffer)                        │
│    User edits not yet saved                      │
│    Source: editEntityRecord() actions            │
│    Mutated by: editor UI / programmatic edits    │
│    Read via: getEditedEntityRecord()             │
├──────────────────────────────────────────────────┤
│ 3. Transient/UI state                            │
│    Selection, focus, undo history                │
│    Source: editor-only state                     │
│    NOT persisted, NOT in entity store            │
└──────────────────────────────────────────────────┘
```

The **edited record** layer is a buffer — `editEntityRecord()`
modifies it; `saveEditedEntityRecord()` flushes it to REST and,
on success, the persisted layer absorbs the changes and the
edit buffer clears.

### 4. Persistence boundary — save as multi-stage pipeline

```
user action in editor (e.g., paragraph content change)
   ↓
block editor dispatches editEntityRecord('postType', 'post', 123, {...})
   ↓
edited record layer accumulates change (in-memory only)
   ↓
[user clicks Save / autosave triggers]
   ↓
saveEditedEntityRecord() dispatch
   ↓
REST API request (POST/PUT /wp/v2/posts/123)
   ↓
[wait for response]
   ↓ (success)
persisted record updated with response data
edited record buffer cleared
selectors re-emit with new persisted state
   ↓ (failure)
edit buffer preserved; error state surfaced; user retries
```

"Save" is NOT a single transaction — it's a 6-stage pipeline
crossing in-memory buffer / network / server processing / store
update / subscription propagation. Each stage has failure modes.

### What this is NOT

- NOT a relational database abstraction. core-data is a CACHE
  of entities exposed via REST; the canonical store is MySQL
  via WordPress's traditional ORM.
- NOT a state management framework competing with Redux directly
  (it IS built on Redux, but presents an entity-oriented API).
- NOT only for block editor. core-data is general — any JS
  consuming WordPress can use it.
- NOT real-time. Entity changes from other clients are NOT
  pushed; staleness is possible until next refetch.
- NOT a single-source-of-truth for ALL state. UI / interaction /
  selection state lives outside core-data.

## REQUIRES

- @wordpress/data + @wordpress/core-data packages available
  (standard in block editor environment).
- A REST endpoint exposing the entity (default for post types,
  taxonomies, users, etc.; CPTs need `show_in_rest` registration).
- Authentication / capability the resolver can use (for
  protected entities — the user's session cookie typically
  authorizes editor requests).
- Network availability for resolver fetches.
- ⚠ Behavior under offline / poor connectivity, retry semantics,
  resolver scheduling priority — verification-needed.

## INVARIANTS

### 1. Entities are runtime authority subjects

Prior KB chunks treated authority as residing in:
- block declarations (block-authoring)
- theme configuration (theme-config)
- compiled style outputs (style-engine)

Data-layer introduces a different kind of authority subject:

> **Entities** (posts, templates, users, etc.) are
> **authoritative mutable subjects** with identity, fields,
> persistence, and lifecycle.

This is qualitatively new. Settings/styles/supports were
authority SURFACES; entities are authority SUBJECTS that have
state independent of any particular consumer. They exist before
any block references them; they persist after consumers
disappear.

### 2. Blocks project entity state rather than owning it

Bindings invariant #3 (attributes become attachment hosts) finds
its full ontological grounding here:

> Block instances are **temporary projections** of entity state
> for editing / display purposes. The entity is the authority
> owner; the block is the authority projection consumer.

Pre-bindings, blocks appeared to OWN their attribute values
(serialized in markup). Post-bindings + entity-resolution, the
clearer ontology is:
- Block instance = projection surface
- Entity = authority owner
- Bindings = projection mechanism
- core-data = projection substrate

This inversion is structural — it changes how to reason about
block content, persistence, and reactivity.

### 3. Resolution is reactive, not transactional lookup

`getEntityRecord(...)` is NOT "fetch the post and return it."
It is:

> "Subscribe to the entity record. Trigger a fetch if not
> cached. Return current store value (possibly null initially).
> Re-emit when the value changes."

The selector is a SUBSCRIPTION; the resolver is the FETCHER
that backfills the store. Components calling the selector
automatically re-render when the underlying entity state
changes (from save, from invalidation, from another
component's mutation).

This is fundamentally different from traditional WordPress PHP
where `get_post(123)` returns a snapshot. The data-layer is a
reactive subscription system, not a request-response API.

### 4. Selectors are authority queries (KB-wide selector symmetry)

The term "selector" in data-layer creates a symmetry with
style-engine selectors:

| layer | selector type | what it queries |
|---|---|---|
| style-engine | CSS selectors | DOM attachment graph (where rules apply) |
| data-layer | data selectors | entity store graph (what authority is current) |

Both are **graph traversal queries** against authority
attachment substrates. Style-engine selectors traverse the
rendered DOM authority graph; data-layer selectors traverse the
entity store authority graph. Both produce reactive results
(style cascade re-evaluates on DOM mutation; data selectors
re-emit on store mutation).

This symmetry is not coincidence — both layers are authority
arbitration mechanisms in different domains.

### 5. core-data is a runtime authority cache

The store is structurally:
- **Cache** for persisted entity state (avoids re-fetching).
- **Buffer** for in-progress edits (edited record layer).
- **Subscription graph** for change propagation.
- **Resolver scheduler** for async fetching coordination.

Reading core-data as "Redux store with WordPress data" misses
the runtime cache + subscription + scheduler character. It is a
**runtime authority cache**, not just a state container.

### 6. Persistence is deferred and buffered

Edit semantics in data-layer separate INTENT from PERSISTENCE:

| stage | mechanism | persisted? |
|---|---|---|
| edit dispatch | `editEntityRecord()` | NO (in-memory buffer) |
| save dispatch | `saveEditedEntityRecord()` | initiates REST save |
| save success | resolver flushes buffer | YES (persisted layer updated) |
| save failure | buffer preserved | NO (user must retry) |

Editor mutations exist for an indeterminate time in the buffer
before persisting. "Save" is the explicit flush operation. This
enables:
- Editor "unsaved changes" indicators
- Atomic save of multiple field changes
- Undo / redo over edits before save
- Discard edits without REST round-trip

### 7. Entity edits exist before persistence — draft authority layer

The edited record layer is itself a kind of authority — a
**draft authority** sitting between user intent and persisted
state:

```
canonical (persisted)
     ↑
   ┌─┴─┐ ← edit buffer (draft authority)
   │   │
canonical (persisted)
```

Other clients reading the entity see the persisted layer (they
don't see the in-progress edits). The editing client sees the
edit buffer overlaid on persisted state via
`getEditedEntityRecord()`. This **client-local draft layer** is
crucial for collaborative editing semantics (and for
understanding why multi-client editing surfaces conflict
resolution issues).

### 8. Resolution crosses server / client / persistence boundaries

A single `useSelect( s => s('core').getEntityRecord(...) )` call
can trigger:

- Client-side: store cache check.
- Network boundary: REST request to server.
- Server-side: WP_REST_Server processing, capability checks,
  permission filters, content filters.
- Database: SQL query (cached or fresh).
- Server-side: response serialization.
- Network boundary: response transmission.
- Client-side: store insertion + subscriber notification.

Six boundary crossings for what looks like a property access.
Each crossing has its own failure modes, latency, caching
characteristics. "Resolution" is shorthand for an entire
distributed pipeline.

### 9. Bindings depend on entity resolution infrastructure

The phase linkage with bindings is structural:

- Bindings declare `source: "core/post-meta"` references.
- The `core/post-meta` provider's `get_value_callback` (PHP) /
  resolver (JS) consults the entity store for the current post's
  meta field.
- Editor reactivity for bound attributes flows through entity
  store subscriptions — when the entity's meta field changes
  (via `editEntityRecord`), bound block attributes re-resolve.

Without entity resolution infrastructure, bindings would have no
substrate to attach to. Documented usage of `core/post-meta`
implicitly relies on this entire pipeline. Custom binding
sources (registered via `register_block_bindings_source`) often
similarly depend on entity resolution; some may use other
authority origins (computed values, external APIs without
WordPress entity backing).

### 10. Data-layer turns Gutenberg from compiler into stateful runtime

This is the **Phase 7 second capstone framing**:

> Block-authoring + theme-config + style-engine made Gutenberg
> a SCHEMA-DRIVEN COMPILER (input → output transformation).
> Bindings + data-layer makes Gutenberg a STATEFUL RUNTIME
> (input + cached state + reactive subscriptions + persistence
> pipeline).

Compiler ontology: declarations → emissions. Stateful runtime
ontology: declarations + state cache + subscriptions + mutation
+ persistence + reactivity.

The two are NOT in conflict — Gutenberg is now a **two-axis
system**: compiler (visual realization axis) + stateful runtime
(authority orchestration axis). Block instances are points
where both axes intersect: visual compilation provides their
appearance; stateful runtime provides their authoritative
content.

## VERIFICATION NEEDED

`status: evolving` — core-data is mature but specific
behaviors are implementation-derived:

- Resolver scheduling priority and concurrency limits.
- Cache invalidation strategy (TTL? mutation-driven? both?).
- Behavior when multiple selectors trigger overlapping resolver
  fetches — deduplication semantics.
- Behavior under offline / poor connectivity (queue? abort?).
- Edited record persistence across page reloads (does the buffer
  survive? generally NO unless using session-storage extension).
- Selector subscription performance characteristics under heavy
  store mutation.
- Conflict semantics when entity changes server-side during
  client editing (server-version-newer detection).
- Capability / permission check timing (client-side hint vs
  authoritative server-side check).
- Editor-specific data store extensions vs core-data store
  proper — boundary not always clear.
- Custom REST endpoint integration — how custom endpoints can
  participate in entity resolution.
- Memoization behavior of selectors with complex args.
- Garbage collection of resolved-but-unsubscribed entities.

For practical decisions: trust empirical observation
(`select( 'core' ).getEntityRecord(...)` in browser console)
over inferred behavior when subtle semantics matter.

## ANTIPATTERNS

- ❌ Treating `getEntityRecord(...)` as a synchronous fetch.
  Initial returns `null`/`undefined` until resolver completes.
  Components must handle the loading state.
- ❌ Using `apiFetch()` directly for entities that have
  core-data store coverage. Bypasses cache, subscriptions, edit
  buffer; causes inconsistency between component view and store.
- ❌ Treating the edited record as authoritative for other
  consumers. The buffer is local to the editing client; other
  consumers see only persisted state.
- ❌ Calling `editEntityRecord` repeatedly without considering
  buffer accumulation. Each edit adds to the buffer; cumulative
  edits all flush on next save.
- ❌ Reading entity field values from block serialized markup
  when bindings or entity-projecting components are in play.
  The markup is stale; entity state is current.
- ❌ Hardcoding entity REST URLs. Use core-data selectors;
  endpoint structure may change, store API is the stable
  contract.
- ❌ Mutating store state directly via Redux internals.
  Use the public action creators; direct mutation breaks
  subscriptions and resolver scheduling.
- ❌ Assuming entity changes propagate across browser tabs /
  devices in real time. core-data is not push-based; staleness
  is the default.
- ❌ Treating "save" as a single transaction. The 6-stage
  pipeline has failure points at each stage; UI should reflect
  the in-progress / failed states.
- ❌ Subscribing to entire entity collections when only one
  field is needed. Subscribers re-render on any change to the
  selector's return value; over-broad selectors cause excess
  re-renders.
- ❌ Relying on the edit buffer surviving page reload. By
  default it does not; explicit persistence / autosave is
  required.

## RELATED

- `block-authoring.block-json.bindings` — bindings consume the
  entity authority documented here. The `core/post-meta` source
  resolves through this layer; custom binding sources often do
  too.
- `block.dynamic-rendering` — PHP-side rendering of dynamic
  blocks queries entity state via WordPress's PHP API
  (`get_post`, `get_post_meta`, etc.). Editor and PHP both read
  the same canonical entity state; data-layer provides the JS
  subscription view of it.
- `block.json-attributes-core` — attributes hosted by bindings
  ultimately project values from this resolution layer.
- `block.json-context` — block context propagation pairs
  conceptually with entity context (current post / template).
  Several context values are entity references (`postId`,
  `postType`).
- (planned) `data-layer.persistence` — save dispatch pipeline,
  REST mutation semantics, conflict handling. This chunk
  documents resolution; persistence deserves its own chunk.
- (planned) `data-layer.entity-types` — specific behavior per
  entity type (post lifecycle, template hierarchy interaction,
  user capability semantics). This chunk is the resolution
  substrate; per-type semantics is a separate concern.
- (planned) `interactivity.runtime-state` — interactivity
  orchestrates reactive state including entity subscriptions.
  data-layer provides the entity authority; interactivity
  provides the directive grammar coordinating it.
- `block.json-supports-field` — supports-derived classes /
  styles operate at compile time; their inputs come from
  per-instance attributes (which may be entity-projected via
  bindings + this layer).

## META

**Phase 7 second capstone chunk.**

Bindings opened the runtime authority axis at the attribute
level. data-layer documents the entity authority substrate
that bindings depend on. After this chunk, the pre-interactivity
substrate is in place:

```
Phase 7 substrate (BEFORE interactivity entry):
   block-authoring.block-json.bindings  → attribute attachment surface
   data-layer.entity-resolution         → entity authority substrate
   ↓
   interactivity bounded context        → reactive orchestration grammar
                                          (still to come)
```

**KB framing — relationship between data-layer and interactivity:**

| layer | role |
|---|---|
| data-layer | state authority substrate (what entities exist, how to read/edit/persist them) |
| interactivity | reactive coordination grammar (directive protocol orchestrating state, including entity subscriptions) |

When interactivity bounded context begins, "what is the state
that becomes reactive?" will have a concrete answer: entity
state from data-layer + per-block instance state +
interactivity-introduced ephemeral state (UI mode, animations,
etc.).

**KB-level framing extension:**

> Gutenberg's compiler architecture (Phases 1-6) handled
> visual realization. Gutenberg's stateful runtime
> (Phase 7+) handles authority orchestration.
>
> data-layer is the **state substrate** of the stateful
> runtime — the authoritative entity graph that all reactive
> consumers ultimately project from.

**DSL extension applied:**

VERIFICATION NEEDED + META sections. This chunk is in
`data-layer` bounded context, not style-engine, but its
runtime / implementation-derived character makes the extended
DSL appropriate (per the applicability extension established
with bindings).

**Status: `evolving`** — core-data is mature but evolving with
each WP version (block-editor data interactions iterate). The
fundamental model documented here is stable; specific
behaviors and APIs may shift.

**Anticipated next chunks:**

1. **`data-layer.persistence`** — save dispatch pipeline deeper
   dive (currently summarized in SHAPE section 4 + invariant 6).
   Consider when persistence-specific concerns (autosave,
   conflict handling, transactional semantics) need dedicated
   treatment.

2. **`data-layer.entity-types`** — entity-type-specific
   behaviors. post lifecycle (draft / publish / scheduled),
   template hierarchy interaction (wp_template /
   wp_template_part), user capability semantics. Currently
   abstract in this chunk's entity table.

3. **`interactivity.{first chunk}`** — entry into the third
   bounded context of Phase 7. Recommended only after
   data-layer's resolution + persistence are settled.
   Candidates: directive protocol / runtime state / hydration
   boundary.

4. **`block.dynamic-rendering` retro patch** — dynamic-rendering
   was written before entity-resolution; may benefit from
   retroactive section showing how PHP entity reads connect
   to JS data-layer entity reads (canonical state shared,
   different consumption APIs).

Recommended sequence: (1) `data-layer.persistence` (to fully
seal the data-layer substrate before interactivity); (2)
interactivity entry; (3) retro patches as bounded contexts
mature.
