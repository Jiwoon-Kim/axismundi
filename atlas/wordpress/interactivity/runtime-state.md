---
rule_id: interactivity.runtime-state
domain: interactivity
topic: reactive-substrate
field_cluster: store-actions-state
wp_min: "6.5"
wp_recommended: "6.5+"
status: evolving
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-interactivity/
    section: "@wordpress/interactivity — store, state, actions, callbacks reference"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/
    section: "Interactivity API — store() function, state proxies, action semantics"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/news/2024/04/03/getting-started-with-the-interactivity-api/
    section: "Getting Started — first store registration patterns"
    captured: 2026-05-09
related:
  - interactivity.directive-protocol           # grammar counterpart; this chunk is the substrate the grammar references
  - data-layer.entity-resolution               # entity authority substrate; runtime-state coordinates with it but is separate
  - data-layer.persistence                     # actions may invoke persistence; reconciliation lifecycle applies
  - block-authoring.block-json.bindings        # bindings + interactivity store can both reference entity state
  - block-authoring.block-json.context         # block-tree DI counterpart; data-wp-context is DOM-tree DI
  - block.dynamic-rendering                    # SSR may pass server-known state into the store via initial state hydration
  - (planned) interactivity.hydration          # boundary chunk — server-initial state flows into client store
---

# RULE — runtime-state — ephemeral reactive coordination substrate

## WHEN

Asking what actually flows through the directive attachments
established by directive-protocol. Use this chunk to understand:

- The structure of an Interactivity store (state / actions /
  callbacks).
- The reactivity model (how state changes propagate to bound
  DOM via subscriptions).
- The authority lifetime distinction between entity state
  (data-layer, persisted) and runtime state (this chunk,
  ephemeral by default).
- Why "stores coordinate, not render" — the key divergence
  from React's component-tree-ownership model.
- How actions interact with persistence boundary (data-layer)
  and how the two substrates compose.

This chunk is the **substrate counterpart** to directive-
protocol's grammar:
- directive-protocol = reactive authority grammar (DOM
  attachments).
- **runtime-state = reactive authority substrate** (what flows
  through those attachments).

After this chunk, interactivity bounded context has its core
ontology pair (grammar + substrate). Hydration chunk will close
the bounded context backbone by formalizing the
server-initial → client-mount boundary.

## SHAPE

### Store anatomy

```js
import { store } from '@wordpress/interactivity';

const { state } = store( 'myPlugin', {

  // Reactive state — bound to directives via state.x references
  state: {
    isOpen: false,
    count: 0,
    // Derived state — getter automatically tracks dependencies
    get doubled() {
      return state.count * 2;
    },
    get message() {
      return state.isOpen ? 'open' : 'closed';
    },
  },

  // Actions — mutation protocols, invoked from data-wp-on--{event}
  actions: {
    toggle() {
      state.isOpen = ! state.isOpen;
    },
    increment( amount = 1 ) {
      state.count += amount;
    },
    // Async action via generator (yield for async ops)
    *saveCount() {
      yield apiFetch( { path: '/...', method: 'POST', data: { count: state.count } } );
      // post-yield: persistence completed
    },
  },

  // Callbacks — side effects, invoked from data-wp-watch / data-wp-init / data-wp-run
  callbacks: {
    logChanges() {
      console.log( 'count is', state.count );
      // Re-runs when state.count changes (auto-tracked deps)
    },
    onMount() {
      // Initialization side effect
    },
  },

} );
```

Three top-level sections per store: `state` (reactive data +
derived), `actions` (mutation methods), `callbacks` (side
effects).

### Reactivity model — proxy-based subscription tracking

The store's `state` is wrapped in a reactive proxy:
- Reading `state.x` inside a directive binding, getter, or
  callback creates a subscription.
- Writing `state.x = newValue` notifies all subscribers.
- Subscribers re-evaluate (re-bind DOM, re-compute derived,
  re-run callback).

Subscription tracking is **automatic and fine-grained** — no
explicit subscribe/unsubscribe; reading the property establishes
the dependency.

### Authority lifetime layers

Gutenberg's full state ontology spans five authority types with
different lifetimes:

| authority type | source | lifetime | persistence | scope |
|---|---|---|---|---|
| **entity state** | data-layer (post / template / etc.) | until DB change | persisted | global (all clients eventually) |
| **block instance state** | block attributes (serialized) | until block deletion | persisted with post | per-block-in-post |
| **directive context state** | data-wp-context attribute | until DOM removal | NOT persisted | scoped subtree of DOM |
| **store ephemeral state** | store(...).state in JS | until page unload | NOT persisted by default | per-namespace per-page |
| **derived state** | getters in state | recomputes on dep change | not stored (computed) | wherever the getter is referenced |

This is **authority lifetimes ontology** — different state kinds
have different lifecycles, persistence, and scopes. Treating all
state as one kind misses critical distinctions.

### Action / mutation protocol

```js
actions: {
  // Synchronous action — direct mutation, immediate propagation
  increment() {
    state.count += 1;
  },

  // Async action — yields for async ops, resumes after
  *saveAndReset() {
    yield wp.data.dispatch( 'core' ).saveEditedEntityRecord( ... );
    state.count = 0;
  },
}
```

Actions are the **intent-to-mutation** protocol:
- Invoked from directive event handlers (data-wp-on--click, etc.)
- Mutate `state` (changes auto-propagate to subscribers)
- May yield for async ops (generators)
- May call other stores' actions (cross-namespace coordination)
- May invoke @wordpress/data dispatches (cross-runtime — into
  data-layer)

### Cross-substrate coordination

```js
import { store, getContext } from '@wordpress/interactivity';
import { dispatch, select } from '@wordpress/data';

store( 'myPlugin', {
  actions: {
    *saveToEntity() {
      // Read from data-layer
      const post = select( 'core' ).getEditedEntityRecord( 'postType', 'post', 123 );

      // Modify entity (data-layer)
      dispatch( 'core' ).editEntityRecord( 'postType', 'post', 123, {
        meta: { ...post.meta, value: state.localValue },
      } );

      // Persist (data-layer reconciliation lifecycle)
      yield dispatch( 'core' ).saveEditedEntityRecord( 'postType', 'post', 123 );

      // Update ephemeral
      state.lastSaved = Date.now();
    },
  },
} );
```

Actions can cross from runtime-state (ephemeral) into data-layer
(persisted/reconcilable). The two substrates COMPOSE via
@wordpress/data dispatches.

### What this is NOT

- NOT a virtual DOM. Stores hold state; they do not represent
  the DOM tree as a virtual structure.
- NOT a global Redux store. Each `store('namespace', {...})` is
  per-namespace; namespaces are isolated by default.
- NOT persistent. Default lifetime is the page; reload loses
  state unless explicitly persisted via data-layer or other
  mechanisms.
- NOT a replacement for data-layer. Entities live in data-layer;
  this substrate handles ephemeral / coordination state.
- NOT React-component-state-equivalent. React state is per-
  component; interactivity store is per-namespace and shared
  across all directive-bound DOM nodes in that namespace.

## REQUIRES

- WP 6.5+ (Interactivity API stable).
- `@wordpress/interactivity` runtime loaded (auto when blocks
  declare interactivity).
- Each `data-wp-interactive="namespace"` region needs a
  registered `store('namespace', {...})`.
- Store registrations (the JS `store()` calls) loaded before
  hydration occurs (otherwise directives reference undefined
  store members).
- For derived state: getters use `state.x` access INSIDE the
  getter (NOT bound `this.x` — the proxy tracking depends on
  proxy property access).
- For async actions: generator function syntax (`*name()`) +
  `yield` for async operations.
- ⚠ Specific reactivity engine details, dependency tracking
  granularity, action concurrency semantics — verification-needed.

## INVARIANTS

### 1. Stores are authority localities (per-namespace authority graphs)

Each `store('namespace', {...})` creates a **per-namespace
authority locality**:
- State scoped to the namespace (no cross-namespace property
  collisions).
- Actions / callbacks scoped to the namespace.
- Subscriptions / reactivity scoped to the namespace.

Multiple namespaces on the same page coexist without
interference. This is **authority locality** at the runtime
state layer — comparable to entity locality at the data-layer
(per-entity-record state).

Plugins / themes / blocks each declare their own namespaces;
the runtime maintains separate stores per namespace.

### 2. Actions are mutation protocols, not direct mutations

Actions are NOT just functions that change variables. They are
**mutation protocols**:

- Invoked from declarative directive surfaces
  (`data-wp-on--click="actions.x"`).
- Modify reactive state (auto-propagating change).
- May yield for async ops (mid-action persistence,
  network calls).
- May invoke other actions (cross-store, cross-substrate).
- Errors propagate per generator semantics for async actions.

Reading actions as "callback functions" misses the protocol
character — they are intent declarations the runtime executes
within the reactive graph.

### 3. Derived state = reactive projections (getters)

Getters in `state` create **reactive projections**:

```js
state: {
  count: 0,
  get doubled() { return state.count * 2; },
  get isHigh() { return state.count > 100; },
}
```

- Each getter automatically tracks its dependencies (which
  state properties it reads).
- When dependencies change, subscribers of the getter re-emit.
- Multiple getters can reference the same dependencies; updates
  propagate to all.

This is identical in spirit to:
- styles.* layer's `var:preset|*|*` references (declarative
  consumption of reactive substrate).
- data-layer selectors over entity store.
- React's useMemo / Vue's computed.

The recurring pattern: **declarative consumption of reactive
substrate via auto-tracked dependencies**.

### 4. Context propagation mirrors DOM topology (DOM-tree DI)

`data-wp-context='{...}'` declares scoped reactive context that
descendant directives consume via `context.x`:

```html
<div data-wp-context='{ "isExpanded": false }'>
  <button data-wp-on--click="actions.toggle">Toggle</button>
  <div data-wp-bind--hidden="!context.isExpanded">Content</div>
</div>
```

This is **DOM-tree dependency injection** — context flows down
the DOM hierarchy, descendants inherit unless they declare their
own.

The pattern recurs across runtimes:

| layer | DI mechanism | propagation tree |
|---|---|---|
| block-authoring | providesContext / usesContext | block tree (editor) |
| **interactivity** | **data-wp-context** | **DOM tree (runtime)** |

Same DI semantics, different runtime layer. Context state is
locally scoped, mutable, reactive — distinct from store state
which is namespace-scoped and global to the namespace.

### 5. Runtime state may never persist — ephemeral is the default

The store's state is **ephemeral by default**:
- No automatic persistence to localStorage / database / cookies.
- Page unload loses the state.
- Page reload re-initializes via store() calls.

Persistence is OPT-IN via:
- Calling data-layer dispatches in actions
  (`saveEditedEntityRecord`).
- Custom localStorage / sessionStorage code in actions.
- Server-passed initial state (via SSR — covered in hydration
  chunk).

This is fundamentally different from data-layer entity state
which IS persisted and reconciled. Conflating them leads to
wrong assumptions about state durability.

### 6. Ephemeral state is FIRST-CLASS, not a workaround for missing persistence

Reading "ephemeral by default" as a limitation misses that
**ephemeral state is structurally important**:

- UI state (open/closed, hover, focus, animation phase) has no
  persistent counterpart.
- Coordination state between components doesn't need to outlive
  the page.
- Performance: avoiding persistence overhead for transient
  state.
- Privacy: client-only state never crosses the network.

Ephemeral state is a **first-class authority kind**, not a
"persistence not yet wired up" placeholder. The 5-layer
authority lifetimes ontology treats it as legitimate alongside
persistent and reconcilable state.

### 7. Subscriptions create reactive edges in store-DOM graph

Each directive that reads state creates a **reactive edge**:

```
state.x  ←  subscription  ←  data-wp-bind--aria-expanded
state.x  ←  subscription  ←  data-wp-class--is-open
state.x  ←  subscription  ←  state getter (derived)
state.x  ←  subscription  ←  data-wp-watch callback
```

When state.x changes, all edges propagate. The runtime maintains
this graph implicitly via proxy tracking; authors don't manage
subscriptions manually.

This is **the reactive graph** that directive-protocol
references at the grammar layer. directives = edge declarations;
runtime-state = edge subjects + propagation engine.

### 8. Stores coordinate, not render — DOM ownership is NOT in the store

This is the **key divergence from React / VDOM frameworks**:

| framework | store/component owns | renders |
|---|---|---|
| React | virtual tree (component output) | reconciles VDOM to DOM |
| Vue | virtual tree (template output) | reconciles VDOM to DOM |
| **Interactivity API** | **state (subjects of reactivity)** | **does NOT render; mutates existing DOM via directive subscriptions** |

The store coordinates STATE; the DOM is owned by the SERVER
RENDER (initial) and mutated by directive subscriptions
(reactive). The store never says "render this component"; it
says "state.x is now 5" and the runtime updates whatever
directives subscribed to state.x.

Result:
- DOM is stable structurally; only directive-bound attributes /
  text mutate.
- No reconciliation algorithm at the store level.
- Server-rendered HTML survives — runtime adds reactivity, doesn't
  replace.

> **DOM is stable; authority flows through it.**

This is the DOM-proximal model's structural manifestation in the
state substrate.

### 9. Actions can cross persistence boundary into data-layer

Actions can invoke @wordpress/data dispatches, including
data-layer's persistence mechanisms:

```
runtime-state action
   ↓ (may invoke)
data-layer dispatch (editEntityRecord / saveEditedEntityRecord)
   ↓
persistence pipeline (data-layer.persistence)
   ↓ (yields)
reconciliation result
   ↓ (action resumes)
runtime-state mutation (state.x = ...)
```

This **cross-substrate coordination** is the structural
connection between Phase 7's two substrates:
- runtime-state: ephemeral coordination
- data-layer: persisted authority

Actions that span both inherit data-layer's reconciliation
lifecycle (optimistic UI, conflict, capability checks). This
is where Phase 7's full authority graph becomes operational.

### 10. Authority lifetimes ontology — Gutenberg's layered state model

Bringing the 5-layer table from SHAPE to invariant status:

```
Authority lifetimes ontology (5 layers):

   entity state         ── data-layer; persisted, reconcilable, global
   block instance state ── block attributes (serialized in markup)
   context state        ── data-wp-context; DOM-scoped, ephemeral
   store state          ── store(...).state; namespace-scoped, ephemeral
   derived state        ── getters; computed from any of the above
```

Each layer has distinct:
- Source authority
- Lifetime
- Persistence semantics
- Scope
- Mutation paths

The ontology is a CLOSED set per current Gutenberg architecture:
all reactive consumers project from one or more of these layers.
Future additions (e.g., distributed state for collab editing)
would extend this ontology, but the current model is structurally
complete for single-author / multi-tab editing.

This 5-layer authority lifetimes view is the runtime-state
chunk's KB-level contribution: the reactive runtime is NOT one
state kind; it is a layered system of authority types that
compose via subscriptions, derived projections, and cross-
substrate dispatches.

## VERIFICATION NEEDED

`status: evolving`. Items requiring verification:

- Reactivity engine internals (Preact signals? custom proxy?
  fine-grained tracking?).
- Dependency tracking granularity (does reading state.deeply.nested
  track the deep path or whole object?).
- Action concurrency — what happens if the same action is
  invoked concurrently?
- Async action cancellation semantics (generator early return).
- Cross-namespace store access patterns (is store('other') from
  one namespace's actions supported / encouraged?).
- Server-passed initial state hydration timing (covered in
  hydration chunk).
- Memory management — are unsubscribed subscribers garbage
  collected promptly?
- Performance under high state mutation rate.
- Error handling — uncaught errors in actions / callbacks /
  derived getters.
- Plugin extension paths — custom store middleware, action
  interceptors.
- Editor preview vs frontend behavior — does the editor execute
  Interactivity stores during preview?
- React + Interactivity coexistence — sharing state between
  React-rendered editor blocks and Interactivity-runtime
  frontend blocks.

For practical decisions: prefer browser DevTools observation
over inferred behavior; the API surface is documented but
reactivity semantics often surface only at use.

## ANTIPATTERNS

- ❌ Treating store state as persisted. Default is ephemeral;
  page reload loses it. Persist explicitly via data-layer or
  storage APIs if durability is needed.
- ❌ Using store state to mirror entity state. Read entity state
  via @wordpress/data selectors directly; mirroring creates
  drift.
- ❌ Mutating state outside actions / callbacks (e.g., from a
  directive value expression). Mutations should originate from
  actions for traceability and to participate in any future
  middleware.
- ❌ Treating actions as synchronous for async ops. Use
  generator syntax with yield; otherwise async ops happen but
  their results don't sequentially update state.
- ❌ Reading from a different namespace's store inside an action
  without acknowledging the cross-namespace dependency.
  Cross-store coordination is supported but should be explicit.
- ❌ Using context (data-wp-context) for state that should live
  in store. Context is DOM-scoped and lost on subtree removal;
  store state is namespace-scoped and survives DOM mutations
  within the page.
- ❌ Putting business logic in derived getters. Getters should
  be cheap projections; expensive logic belongs in actions or
  callbacks (where async + side effects are appropriate).
- ❌ Expecting subscriptions to fire on object identity change
  (e.g., `state.list = newArray`). Reactivity tracks property
  reads; replacing the whole array may or may not trigger
  per-item subscribers depending on implementation
  (verification-needed for exact semantics).
- ❌ Using interactivity store as a global event bus. It's a
  state substrate; for events use directives + actions
  explicitly.
- ❌ Forgetting to register the store before directives reference
  it. Store registration must complete before hydration; missing
  stores cause directives to reference nothing.
- ❌ Assuming React component state APIs work. Interactivity is
  a separate runtime; useState / useEffect from React don't
  apply here.

## RELATED

- `interactivity.directive-protocol` — grammar counterpart.
  Directives REFERENCE store members (state.x, actions.x,
  callbacks.x); this chunk documents what those members are.
  The two together = interactivity's core ontology.
- `data-layer.entity-resolution` — entity state authority
  substrate. runtime-state coordinates with it (cross-substrate
  dispatches) but is structurally separate (different
  persistence, different scope).
- `data-layer.persistence` — actions invoking persistence
  inherit reconciliation lifecycle, optimistic UI semantics,
  conflict / capability boundaries.
- `block-authoring.block-json.bindings` — bindings + interactivity
  store can both project entity state into block UI; the two
  serve different consumers (bindings for static-render block
  attributes; store for reactive runtime).
- `block-authoring.block-json.context` — block-tree DI counterpart.
  data-wp-context is the runtime DOM-tree DI version of
  providesContext / usesContext.
- `block.dynamic-rendering` — server may pass server-known
  initial state into store via SSR; covered fully in
  (planned) hydration chunk.
- `style-engine.cascade-aggregation` — symmetric runtime
  counterpart. Both maintain reactive graphs; CSS cascade
  reacts to DOM/style changes; runtime-state reacts to property
  mutations. Different graphs, parallel patterns.
- (planned) `interactivity.hydration` — server-initial state
  flows into client store. Boundary chunk that closes
  interactivity bounded context backbone.

## META

**Substrate counterpart to directive-protocol; interactivity
ontology pair complete.**

```
interactivity bounded context (status):
   directive-protocol    → reactive authority grammar           ✓
   runtime-state         → reactive authority substrate         ✓
   ↓
   (NEXT) hydration      → server-initial → client-mount boundary
                            (closes bounded context backbone)
```

**Authority lifetimes ontology established (KB-level):**

The 5-layer model (entity / block / context / store / derived)
is the runtime-state chunk's KB-level contribution. Future
chunks reasoning about state across Gutenberg can reference
this ontology to disambiguate which authority layer is in
scope.

Layered comparison with prior KB authority models:

| KB layer | authority distribution |
|---|---|
| capability authority (block-authoring + theme-config) | what controls exist + who configures them |
| realization authority (style-engine) | how values become CSS |
| reconciliation authority (data-layer) | how persisted state evolves |
| **runtime state authority (this chunk)** | **how ephemeral / reactive state coordinates** |

KB now has authority models at every layer Gutenberg operates.

**KB-level framing extension (Phase 7 deepening):**

> Gutenberg's reactive runtime is **DOM-stable**:
> - DOM topology is owned by server render + author markup
> - Authority flows through DOM via subscriptions
> - Stores coordinate state without owning DOM tree
>
> This is the structural difference from React/Vue/Angular.
> The DOM is treated as primary reality (server-first
> philosophy); the runtime adds coordination, not ownership.

**KB recognized as ontology atlas:**

User observation acknowledged: KB has moved past handbook
summarization to **WordPress/Gutenberg ontology atlas**. Each
chunk now contributes to a coherent map of:
- Authority distributions
- Composition axes
- Realization pipelines
- Runtime substrates
- Reactive coordination

The atlas character means future chunks should be evaluated
not just for accuracy but for structural fit — does the chunk
extend the ontology coherently, or does it surface tensions
that need resolution?

**DSL extension applied:** VERIFICATION NEEDED + META, per
runtime/implementation-derived applicability.

**Status `evolving`** — Interactivity API state model is stable
core but reactivity engine details / async action semantics /
cross-substrate coordination patterns continue to evolve per
WP version.

**Anticipated next chunk:**

`interactivity.hydration` — server-initial → client-mount
boundary. Will likely converge multiple framings:
- compiler/runtime split (style-engine pattern)
- SSR-first (WP philosophy)
- delayed authority reconciliation (data-layer)
- runtime attachments (directives + store)
- bindings, entity resolution, persistence latency

Hydration is where Phase 7's full authority graph crystallizes
at the boundary between server and client. This chunk closes
interactivity bounded context's structural backbone.

---

## Q9 RETROACTIVE PATCH — Phase 8.16 v2-Deployment Validation (2026-05-10)

> **Retroactive verification triggered by**:
> Phase 8.15 KB Constitution v2 Epoch Snapshot operational
> guidance + Phase 8.16 strategic posture: "v2 must prove
> usability before it pursues expansion." This is **first
> v2-deployment Q9 retro applied to Phase 7-native
> interactivity chunk** to test whether v2 vocabulary
> classifies existing material cleanly without forcing.
>
> **Strategic role**: Constitutional deployment validation,
> NOT constitutional escalation. Per Phase 8.16 hard rule:
> **"Prefer constitutional deployment over constitutional
> invention."**
>
> **Methodological framing**: This retro applies v2
> vocabulary (Law 3b reactive-subscription Bridge sub-
> character + computational-architectural character +
> Doctrine 6 falsification + Q11 5-outcome awareness) to
> Phase 7-native chunk authored 2026-05-09 (pre-Doctrine-6,
> pre-Bridge Pattern, pre-Law 3b, pre-character taxonomy).
>
> **Q9 retro discipline**: Per Phase 8.16 framing, **classify
> first, speculate later**. Honest evaluation refuses
> premature constitutional invention.

### Retro context

This chunk was authored 2026-05-09 (Phase 7-native) as
substrate counterpart to directive-protocol (also Phase 7-
native, retroed Phase 8.12). Original analysis describes
runtime-state through Phase 7 ontology (5-layer authority
lifetimes / DOM-stable model / authority lifetime
distinctions). No v2 vocabulary used.

The retro questions (per Phase 8.16 strategic guidance):

> **Primary question**: Does runtime-state primarily
> instantiate existing Law 3b / Doctrine 5 / computational-
> architectural character?
>
> **NOT**: What new constitutional thing can this become?

### LENS 1 — v2 vocabulary classification (existing chunk through v2 lens)

#### Law 3b reactive-subscription sub-character (3b-react) classification

Original chunk's "stores coordinate, not render" + DOM-
stable model + reactive subscription substrate IS Law 3b
3b-react sub-character manifestation:

| original chunk element | Law 3b 3b-react reading |
|---|---|
| Invariant 7 ("Subscriptions create reactive edges in store-DOM graph") | reactive-subscription topology = 3b-react sub-character core |
| Invariant 8 ("Stores coordinate, not render — DOM ownership is NOT in the store") | DOM-proximal reactive subscription character (3b-react vs static-data 3b-static) |
| Server-initial state via wp_interactivity_state | PHP-initiated state injection = Bridge initiation stage |
| Inline script config setting state | HTML-mediated state delivery = Bridge mediation stage |
| store() registration + Proxy wrap | JS-consumed reactive substrate establishment = Bridge consumption stage |
| Reactive edges propagating mutations to DOM | Continuous reactive subscription topology = 3b-react ongoing character |

> **Classification finding**: runtime-state's reactive
> substrate IS Law 3b 3b-react sub-character manifestation
> (reactive-subscription Bridge). 2nd direct chunk
> manifestation of 3b-react after directive-protocol Phase
> 8.12 (1st instance via Q9 retro).

This is **constitutional vocabulary deployment**: existing
runtime-state material is CLEANLY CLASSIFIABLE under v2's
Law 3b 3b-react sub-character. No new sub-character needed.

#### Computational-architectural character classification

Original chunk's framing parallels style-engine + directive-
protocol (Phase 8.9 + 8.12 surfaced computational-
architectural character):

| character dimension | runtime-state evidence |
|---|---|
| Declarative authoring | store({state, actions, callbacks}) declaration |
| Compiled into runtime graph | Proxy-wrapped reactive object graph |
| Browser-executed | JS engine + @wordpress/interactivity package |
| Result in DOM behavior | reactive subscriptions update directive-bound DOM |
| Server-first | wp_interactivity_state initial state baked into HTML |
| Has escape hatches | actions/callbacks with arbitrary JS |

> **Classification finding**: runtime-state confirms
> Computational-architectural character for interactivity
> bounded context. 2nd direct chunk evidence after
> directive-protocol Phase 8.12 (1st instance).

> **interactivity bounded context Computational-architectural
> classification: STRENGTHENED via 2nd intra-context chunk
> evidence.**

#### Doctrine 6 (Authority Access Mediation) falsification

Per Phase 8.10 Law 1 trap principle + Phase 8.16 explicit
warning ("Do NOT actively seek Law 3c / 6h / 6i / etc"):

| Doctrine 6 candidate screening | result |
|---|---|
| Does store registration gate authority access? | NO — store registration is reactive substrate establishment, not access gating |
| Does state Proxy gate access to data? | NO — Proxy provides reactive tracking, not access mediation |
| Do actions gate authority? | NO — actions are state mutators + event handlers, not access gates |
| Do callbacks gate authority? | NO — callbacks are side-effect functions, not gating |
| Does namespace isolation gate access? | WEAK — namespace isolation provides separation of concerns; NOT capability-gating choreography |

> **Classification finding**: Doctrine 6 is **NOT PRESENT**
> in runtime-state. This is consistent with computational-
> architectural classification (Doctrine 6 manifests in
> governance-architectural bounded contexts only per Phase
> 8.9 P1 + Phase 8.12 P4 observations).

> **No Doctrine 6 force-fitting.** Constitutional restraint
> demonstrated.

#### Doctrine 5 Resolution manifestation classification

Original chunk's reactive subscription + getter (computed
value) pattern IS Doctrine 5 Resolution manifestation:

| reactive computation aspect | Doctrine 5 Resolution mapping |
|---|---|
| Property access on Proxy | Resolution stage at access time (per-evaluation) |
| Getter dependency tracking | Resolution dependency graph construction |
| Re-evaluation on mutation | Resolution re-execution under changed inputs |
| Subscriber notification | Resolution propagation to consumers |

> **Classification finding**: runtime-state's reactive
> subscription model IS continuous Doctrine 5 Resolution
> (re-resolved per state change). Resolution Surface
> (Recurring cross-context, KB-Wide REFUSED Phase 7.8)
> manifests as ongoing reactive resolution.

#### Federation Pattern classification

Original chunk's namespace isolation IS Federation Pattern
manifestation:

| namespace federation aspect | Federation Pattern mapping |
|---|---|
| Multiple plugins register stores in distinct namespaces | per-vendor reactive graph federation |
| Independent reactive graphs per namespace | isolation between federated participants |
| Cross-namespace access via explicit store('namespace') call | federation boundary explicit traversal |
| No global state sharing | federation prevents collisions |

> **Classification finding**: runtime-state confirms
> Federation Pattern manifestation in interactivity bounded
> context. Federation continues 9-context KB-Wide-equivalent
> recurrence (no new context; runtime-state extends
> interactivity intra-context Federation density).

### LENS 2 — Constitutional restraint (NO new candidates surfaced)

Per Phase 8.16 hard rule + Phase 8.14 v2 conservative
discipline:

| pattern observation candidate | constitutional restraint applied |
|---|---|
| Proxy reactive substrate as "NEW constitutional pattern"? | NO — Proxy is implementation mechanism for Law 3b reactive subscription; NOT constitutional pattern |
| 5-layer authority lifetimes (entity / block / context / store / derived) as "NEW classification taxonomy"? | NO — already documented Phase 7-native; Phase 8.16 retro does NOT promote to formal taxonomy |
| Cross-substrate coordination (runtime-state + data-layer) as "NEW pattern"? | NO — coordination is operational pattern; existing Doctrine 5 + Federation cover |
| withScope() reactive scope preservation as "NEW sub-pattern"? | NO — implementation mechanism, NOT constitutional pattern |
| Sync vs async action distinction as "NEW variant"? | NO — execution mode, NOT architectural variant |
| Computed values (getters) as "NEW Doctrine 5 sub-pattern"? | NO — operational character of Doctrine 5 Resolution; existing classification covers |

> **No new candidates surfaced. v2 vocabulary cleanly
> classifies all original chunk material via existing
> constitutional elements.**

### Q9 retro verdict — CONFIRM (Outcome A — most desirable per Phase 8.16 framing)

Per Phase 7.6+ retroactive verification protocol (Confirm /
Distributed / Divergent / Additive):

| verdict | applicability for v2 deployment |
|---|---|
| **Confirm** | **runtime-state cleanly classified under existing v2 vocabulary (Law 3b 3b-react + Computational-architectural + Doctrine 5 + Federation; Doctrine 6 absent)** | **YES — primary verdict** |
| Distributed | Single classification distributed across multiple mechanisms | partial — multiple v2 elements apply but each classifies a distinct aspect |
| Divergent | Structurally different from existing v2 classifications | NO — existing v2 vocabulary fits cleanly |
| Additive | Adds NEW constitutional element | **NO — Phase 8.16 discipline refuses additive expansion** |

> **Q9 retro verdict: CONFIRM (Outcome A).**
>
> v2 vocabulary deployment validated: existing runtime-state
> material is CLEANLY CLASSIFIABLE under v2's Law 3b
> 3b-react + Computational-architectural + Doctrine 5 +
> Federation classifications. **No constitutional invention
> required.**

This is the **most desirable Phase 8.16 outcome** per
Phase 8.16 strategic framing: "Runtime-state strongly
validates Law 3b-react + computational-architectural
category + Bridge sub-character coherence. **This
strengthens v2 stability.**"

### Constitutional restraint demonstration

This retro demonstrates v2 deployment without constitutional
invention:
- ✅ Existing Law 3b 3b-react covers reactive subscription
  substrate
- ✅ Existing Computational-architectural classification
  covers interactivity bounded context character
- ✅ Existing Doctrine 5 Resolution covers reactive
  computation
- ✅ Existing Federation Pattern covers namespace isolation
- ✅ Doctrine 6 correctly absent (no force-fitting; Law 1
  trap avoided)
- ❌ NO new Law sub-pattern candidates surfaced
- ❌ NO new Bridge sub-characters surfaced
- ❌ NO new Doctrine sub-elements surfaced
- ❌ NO new architectural variants surfaced
- ❌ NO new constitutional layers surfaced

> **v2 governs ordinary cases without expansion pressure.**

### Q10 sub-pattern emergence (retro)

> **Q10 RETRO ANSWER: NO new sub-pattern observed.**

Initial hypothesis screening (per Phase 8.16 "classify
first, speculate later" discipline):

- Could 5-layer authority lifetimes ontology (entity /
  block / context / store / derived) be NEW formalized
  taxonomy? Honest evaluation: **observation only**;
  insufficient cross-context evidence for formal taxonomy
  promotion. Phase 8.20+ candidate IF explicit
  formalization audit conducted.

- Could DOM-stable model be NEW architectural pattern?
  Honest evaluation: **observation only**; computational-
  architectural character already captures this dimension.

> **No premature inflation. Phase 8.16 constitutional
> deployment posture maintained.**

### Phase 8.16 outcome assessment

Per Phase 8.16 best-case outcomes framing:

| outcome | match? |
|---|---|
| **Outcome A (Most desirable)**: Runtime-state strongly validates Law 3b-react + computational-architectural + Bridge sub-character coherence | **YES — this retro's verdict** |
| Outcome B: Runtime-state reveals Law 3b insufficiency (legitimate Phase 8.17 pressure) | NO — no insufficiency observed |
| Outcome C: Runtime-state mostly falls under existing Law 3 + Doctrine 5 (constitutional restraint test) | partially YES (existing classifications cover; restraint demonstrated) |

> **Phase 8.16 v2-deployment validation: SUCCESS (Outcome
> A)**.

### NEW KB-level findings (CONSERVATIVE — Phase 8.16 discipline)

**Single KB-level finding** (per Phase 8.16 conservative
discipline — minimal new findings):

**1. v2 vocabulary deployment validated for Phase 7-native
chunks**

Phase 7-native interactivity chunks (directive-protocol +
runtime-state) classify cleanly under v2 vocabulary without
forcing. v2 framework deploys retroactively against
Phase 7-era material; no constitutional drift between
authoring era and current vocabulary.

> **Constitutional usability validated**: v2 governs both
> v2-native authoring (which doesn't yet exist for forward
> chunks since runtime-state already exists) AND
> retroactive vocabulary deployment against Phase 7-native
> material.

### Mediation audit criterion impact (post-this-retro)

> **Note**: Phase 8.16 retro produces NO Mediation criterion
> advance (Doctrine 6 explicitly absent in this chunk).

### KB-wide pattern recurrence updates (CONSERVATIVE)

**Law 3b 3b-react sub-character intra-context density**:
1 chunk (directive-protocol) → **2 chunks (+ runtime-state
this retro)**. Interactivity bounded context now has 2
3b-react manifestations.

**Computational-architectural character intra-context
density**: 1 chunk (directive-protocol) → **2 chunks (+
runtime-state)**. Interactivity bounded context now has
2 computational-architectural chunks (parallel to
style-engine which has multiple chunks per Phase 8.9 P1).

**Doctrine 5 Resolution Surface PRESENCE**: 6 contexts
(unchanged; interactivity already counted via directive-
protocol).

**Federation Pattern**: 9 contexts (unchanged; interactivity
already counted via directive-protocol).

**Bridge Pattern (Law 3b)**: 5 instances × 4 contexts
(unchanged; runtime-state extends 3b-react sub-character
intra-context density rather than adding new instance).

### Constitutional principle (retro-derived)

> **Constitutional usability is demonstrated through
> classification, not invention.** A constitution proves
> its operational maturity when it can govern ordinary
> cases via existing classifications without requiring new
> ones.

This refines Phase 8.16's "v2 must prove usability before
it pursues expansion" doctrine: usability proof requires
DEMONSTRATING that existing classifications cover ordinary
cases (this retro) BEFORE expanding constitutional structure.

### Comparison: Phase 8.9-8.16 retro arc summary

| retro phase | verdict | v2 deployment posture |
|---|---|---|
| P1 (style-engine, Phase 8.9) | DIVERGENT | breadth ceiling clarification (pre-v2 audit prep) |
| P3 (supports-field, Phase 8.9) | ADDITIVE + CONFIRM | depth advance (pre-v2 audit prep) |
| P4 (directive-protocol, Phase 8.12) | ADDITIVE + STRENGTHENED + DIVERGENT | multi-lens (pre-v2 sub-pattern surfacing) |
| P5 (register-client-js, Phase 8.12.5) | ADDITIVE WITH CAVEATS + Law 3 dependence STRONG | dual-lens (audit gate satisfaction) |
| **P6 (runtime-state, Phase 8.16) — THIS** | **CONFIRM (Outcome A — v2 deployment validated)** | **constitutional deployment validation; NO new candidates** |

**6 retros × 5 distinct outcome types** + **1st pure
v2-deployment validation retro**. Phase 8.9-8.16 retro arc
demonstrates KB's Q9 retro discipline at full sophistication
across discovery + audit-prep + multi-lens + audit-gate +
**deployment-validation** modes.

### Anticipated next chunks (post-this-retro)

Per Phase 8.16 strategic sequence:
1. **`interactivity.hydration` Q9 retro (Phase 8.16b)** —
   2nd v2-deployment validation retro; tests whether
   hydration also deploys cleanly under v2 vocabulary.
   Hydration is Phase 7 capstone (task #81 completed); same
   v2 deployment posture applies.

2. **Interactivity bounded context closure adjudication
   (Phase 8.16c)** — first full bounded context closure
   under v2 framework. Major comparison study: pre-v2
   bounded context closures vs v2-native bounded context
   closure character.

3. **Untouched bounded context entry (build-tooling, REST
   authentication, etc.)** — genuinely v2-native forward
   chunk authoring (where runtime-state and hydration
   already exist).

Recommended: **`interactivity.hydration` Q9 retro** —
completes interactivity bounded context v2-deployment
validation pair (directive-protocol Q9 retro Phase 8.12 +
runtime-state Q9 retro Phase 8.16 + hydration Q9 retro
Phase 8.16b). After 3 v2-deployment validation retros in
single bounded context, interactivity bounded context
closure adjudication becomes viable.

### Status updates

- This file's overall `status` remains `evolving` (original
  evaluation preserved).
- Retro patch adds Q9 v2-deployment validation verdict
  (CONFIRM Outcome A) + Law 3b 3b-react + Computational-
  architectural classifications + constitutional restraint
  demonstration.
- Original chunk content (lines 1-651) UNCHANGED; this retro
  is purely additive at end of file.
