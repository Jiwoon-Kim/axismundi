---
rule_id: data-layer.wp-data-registry
domain: data-layer
topic: state-registry
field_cluster: store-federation-substrate
wp_min: "5.0"
wp_recommended: "6.5+"
package_min: "@wordpress/data@^9"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/
    section: "@wordpress/data — store registration, selectors, dispatch, resolvers"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/data/
    section: "Core data stores reference — core, core/block-editor, core/notices, core/preferences, etc."
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/data/README.md
    section: "createReduxStore, register, useSelect, useDispatch — current API surface"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2018/11/07/introduction-to-the-data-module/
    section: "Introduction to the Data module — original design rationale (federation across plugins)"
    captured: 2026-05-10
related:
  - data-layer.entity-resolution                # built on top of @wordpress/data; the core store provides entity selectors / resolvers
  - data-layer.persistence                      # write/reconciliation pathway for entity stores; relies on dispatch
  - interactivity.runtime-state                 # parallel state model for front-end interactivity (different runtime, different mechanism)
  - interactivity.hydration                     # the place where Law 3b actually applies, distinct from the registry pattern here
  - block.edit-and-save-contracts               # the editor `edit` contract that consumes / dispatches against this registry
---

# RULE — `@wordpress/data` registry — federated reactive state store substrate

## WHEN

You need to read or write WordPress editor / interactivity
state through the documented data layer rather than
through ad-hoc globals or component-local state. Use this
knowledge when:

- Calling `select( 'core' ).getEntityRecord( ... )` from a
  block's `edit` function or a custom panel.
- Registering a plugin-owned store so other code (your own
  or third party) can read its state by name.
- Wiring `useSelect` / `useDispatch` into a React component
  and trying to understand the subscription / re-render
  semantics.
- Reading core code that calls `dispatch(
  'core/block-editor' ).insertBlock( ... )` and want to
  follow how that reaches the editor's reducer.
- Choosing between sync `select()` and async
  `resolveSelect()` for a selector backed by a resolver.

This chunk does **not** cover:

- Specific core stores' selector/action surfaces
  (covered in the core data reference linked under
  Sources, and indirectly in `data-layer.entity-resolution`
  for `core`).
- The interactivity API's runtime state (a different
  state model running in a different runtime; covered in
  `interactivity.runtime-state` and `interactivity.hydration`).
- React rendering details — the chunk assumes basic
  familiarity with hooks and re-render behavior.

The principle this chunk operates under: **`@wordpress/data`
is a registry of stores, not a single global store.** Each
store is independently registered, owns its own state and
reducer, and is accessed by name. The registry is where
they federate.

## SHAPE

### A. The registry — a singleton meeting place

There is one registry in the editor runtime. It is the
default registry exported by `@wordpress/data` (and
exposed at `window.wp.data` for bridge compatibility).
Every store registered via `register()` lives inside
this single registry instance. Every `select()` /
`dispatch()` call resolves through it.

```js
import { register, createReduxStore, select, dispatch } from '@wordpress/data';

const store = createReduxStore( 'myplugin/my-store', {
    reducer,
    actions,
    selectors,
} );

register( store );

// Anywhere afterward:
const value = select( 'myplugin/my-store' ).getThing();
dispatch( 'myplugin/my-store' ).updateThing( newValue );
```

Two properties of the registry are load-bearing:

- **It is a name index, not a state aggregator.** The
  registry maps store names (strings) to store instances.
  It does not merge their states, does not impose
  cross-store schema, does not enforce semantic
  relationships between them. Each store is sovereign
  over its own state.
- **It is shared across all consumers in the runtime.**
  Every block, every plugin extension, every editor
  customization sees the same registry and the same
  registered stores. This is the federation property
  (Section F).

The registry can be subclassed or replaced (advanced
patterns: scoped registries for testing, custom
registries for storybook-style isolation), but the
overwhelming majority of WordPress code talks to the
default singleton registry. Treat the singleton as the
default reading.

### B. Stores — self-contained state machines

A store is configured via `createReduxStore( name, config )`.
The config has four meaningful parts:

| Field        | Purpose                                                              |
| ------------ | -------------------------------------------------------------------- |
| `reducer`    | Pure `(state, action) => newState` function (Redux convention)       |
| `actions`    | Object whose values are action creators — functions returning action objects (or generator functions for resolvers/controls) |
| `selectors`  | Object whose values are `(state, ...args) => value` functions        |
| `resolvers`  | Object whose keys mirror selector names; each value is an async-style function that runs once-per-args to populate state if absent (Section E) |
| `controls`   | Optional declarative side-effect handlers for action generators      |

A complete (small) store:

```js
const DEFAULT_STATE = { count: 0 };

const reducer = ( state = DEFAULT_STATE, action ) => {
    switch ( action.type ) {
        case 'INCREMENT':
            return { ...state, count: state.count + 1 };
        default:
            return state;
    }
};

const actions = {
    increment: () => ( { type: 'INCREMENT' } ),
};

const selectors = {
    getCount: ( state ) => state.count,
};

const store = createReduxStore( 'myplugin/counter', {
    reducer,
    actions,
    selectors,
} );

register( store );
```

Each store is fully self-contained. It does not know
about other stores. If two stores need to coordinate, the
coordination happens at the *consumer* level (a component
that selects from both, or a control that dispatches to
both) — not inside either store.

### C. Reading state — `select` / `useSelect` / `resolveSelect`

Three reading APIs, three different semantics:

**`select( storeName ).selectorName( ...args )` — sync, snapshot.**
Returns the selector's value at this instant. No
subscription. Suitable for one-shot reads inside event
handlers, controls, or non-React code.

```js
const blocks = select( 'core/block-editor' ).getBlocks();
```

**`useSelect( ( select ) => select( storeName ).foo() )` — sync, reactive.**
A React hook. Subscribes the calling component to the
selector; the component re-renders whenever the selector's
return value changes. The mapping function should be
small and deterministic.

```js
const blocks = useSelect( ( select ) => select( 'core/block-editor' ).getBlocks(), [] );
```

**`resolveSelect( storeName ).selectorName( ...args )` — async, resolver-aware.**
Returns a Promise that resolves once the selector's
backing resolver (Section E) has run. Use when the
selector is backed by a resolver (typical for entity
lookups) and you specifically need to wait for the data
to be present.

```js
const post = await resolveSelect( 'core' ).getEntityRecord( 'postType', 'post', 42 );
```

The asymmetry to hold onto: `select()` returns whatever
state currently holds, including `undefined` if a
resolver hasn't fired yet; `resolveSelect()` waits for
the resolver to settle. They are not interchangeable.

### D. Writing state — `dispatch` / `useDispatch`

Two writing APIs:

**`dispatch( storeName ).actionName( ...args )` — direct.**
Calls the action creator, dispatches the resulting
action through the store's reducer.

```js
dispatch( 'core/notices' ).createSuccessNotice( 'Saved.' );
```

**`useDispatch( storeName )` — React-friendly.**
A React hook that returns the dispatchers object for the
store. Stable across re-renders; safe to use in event
handlers and effects.

```js
const { createSuccessNotice } = useDispatch( 'core/notices' );
createSuccessNotice( 'Saved.' );
```

Two properties worth pinning:

- **There is no general "set arbitrary state" API.** All
  state mutation flows through actions defined by the
  store. Consumers cannot reach into another store's
  state directly. This is the discipline that makes
  federation safe — every store controls its own write
  surface through its action set.
- **Dispatchers return whatever the action creator
  returned.** For ordinary action objects, that's the
  action object after dispatch. For generator-based
  action creators (used with controls), it's a Promise
  that resolves when the generator has finished. The
  return shape depends on the action creator's shape.

### E. Resolvers — declarative async data loading

A common pattern: a selector is meant to return data that
hasn't been fetched yet. The resolver mechanism handles
this without requiring components to manually orchestrate
fetch + state update.

Declared per selector name:

```js
const selectors = {
    getThing: ( state, id ) => state.things[ id ],
};

const resolvers = {
    *getThing( id ) {
        const result = yield apiFetch( { path: `/myplugin/v1/thing/${ id }` } );
        return actions.receiveThing( id, result );
    },
};
```

Behavior on first call:

1. Component calls `select( 'myplugin/store' ).getThing( 42 )`.
2. State doesn't have it; selector returns `undefined`.
3. The data layer notices the selector has a registered
   resolver and that this `(args)` combination hasn't run
   yet, and invokes `resolvers.getThing( 42 )`.
4. The resolver's generator yields `apiFetch(...)`, the
   result populates state via the returned action.
5. Subscribed `useSelect` consumers re-render with the
   resolved value.
6. The resolver's `(args)` combination is marked
   resolved; subsequent `select()` calls return the
   cached state without re-fetching.

Two associated APIs surface this state:

- `select( store ).hasFinishedResolution( 'getThing', [ 42 ] )` — Boolean.
- `select( store ).isResolving( 'getThing', [ 42 ] )` — Boolean (true while the resolver is mid-run).

These let components render loading states without
introducing their own pending-flag state.

### F. Federation — many plugins, one registry

The registry's most architecturally important property:
plugins do not own the registry. They register stores
*into* the shared registry. The model is federation —
every participant adds to a shared name index, and every
participant can read every other participant's stores by
name.

This produces predictable consequences:

- **Name collisions matter.** Two plugins registering
  `'myplugin/store'` will conflict; the second
  `register()` typically warns and is silently rejected.
  Convention: namespace store names with the plugin's
  vendor prefix (`acme/inventory`, not `inventory`).
- **No isolation by default.** A store registered by
  plugin A is readable by plugin B. This is the *point*
  of the federation model — extensibility without
  explicit handshakes — but it also means a store's
  state surface is part of its public API by default.
- **Core stores set the conventions.** `core` (entity
  records), `core/block-editor` (blocks in the editor),
  `core/notices` (admin notices), `core/preferences`
  (user prefs), and others define the patterns plugin
  stores tend to follow. Reading a few core stores'
  selector/action shapes is the fastest way to absorb
  the conventions.

The federation is a JavaScript-runtime expression of the
same pattern that appears in plugin-dev (PHP-side
extension registration around shared core registries).
Same shape, different layer.

## WHY

### Why a registry instead of a single global store

Redux's original sample shape is one store per
application. WordPress has a different problem: many
unrelated plugins coexist on a single page, each owning
distinct state, and any of them might extend the editor
or read shared editor state. A single combined store
would mean either:

- Every plugin must contribute reducers to one combined
  reducer (tight coupling, difficult plugin lifecycle).
- Or one plugin's bug in its reducer could corrupt
  state owned by another plugin.

The registry-of-stores pattern keeps each plugin's
reducer isolated to its own store while still allowing
all stores to live in one runtime. Subscription is
per-store, so a re-render triggered by one store's
update doesn't ripple into unrelated stores.

### Why selectors and dispatchers are separated by name

Reading and writing have different shapes: reading is
direct, dispatch is mediated by reducer. Naming them
distinctly (selectors as nouns, actions as verbs) makes
intent explicit at call sites. It also lets the data
layer optimize each independently — selectors can be
memoized, dispatch can run controls and generators —
without entangling the optimizations.

### Why resolvers exist instead of components fetching directly

Centralizing the fetch logic into the store means:

- A given selector's data is fetched at most once per
  args combination, regardless of how many components
  call the selector.
- Loading states are queryable through the data layer
  rather than tracked locally per component.
- Mutations to the store invalidate cached resolutions
  (via `invalidateResolution` actions), giving
  components a uniform way to trigger refetches.

The cost is one indirection (state describes whether the
resolver has run); the gain is consistency across the
application's data layer.

## WHEN NOT

Skip `@wordpress/data` if:

- Your state is **entirely component-local** (a panel's
  open/closed state, a small form's input values).
  `useState` is fine; the registry adds overhead with
  no payoff.
- You are working in **interactivity (front-end view
  scripts)**, not the editor. The interactivity API has
  its own state model (`interactivity.runtime-state`)
  with different runtime characteristics. Don't mix.
- You need **non-reactive** access to a one-time value
  (e.g., reading a setting once at module load). A
  one-shot `select()` is fine; don't reach for a hook
  unless you actually want re-renders on change.
- The state is **already exposed by a core store** and
  you don't need a new namespace. Use the existing
  store's selectors and actions; don't re-implement.

## COUNTER-PATTERNS

### Anti-pattern 1 — Reaching into another store's state directly

```js
// Wrong: trying to grab state from a store you don't own.
const otherStoreState = select( 'core/block-editor' ).__internalState;
```

There is no `__internalState` selector. State is reached
*through* declared selectors, not by spelunking into the
store internals. If a value isn't selectable, it isn't
part of the store's public surface — file a request,
don't bypass the contract.

### Anti-pattern 2 — Mutating state outside the reducer

```js
// Wrong: action creators must return action objects, not mutate state.
const actions = {
    setCount: ( newValue ) => {
        state.count = newValue;          // does nothing useful, breaks invariants
        return { type: 'NOOP' };
    },
};
```

State changes only through the reducer's response to
dispatched actions. Direct mutation of the state object
violates Redux invariants and produces unpredictable
re-render behavior.

### Anti-pattern 3 — Heavy computation inside `useSelect` mapping

```js
const expensiveValue = useSelect( ( select ) => {
    const all = select( 'core' ).getEntityRecords( 'postType', 'post', { per_page: -1 } );
    return all.map( /* expensive transform */ ).filter( /* expensive predicate */ );
}, [] );
```

The mapping runs on every potential re-render trigger
across the subscribed store. Move expensive work to a
memoized selector inside the store, or compute in
`useMemo` *outside* `useSelect`.

### Anti-pattern 4 — Forgetting the dependency array

```js
// Re-subscribes on every render — memory + performance cost.
const value = useSelect( ( select ) => select( 'myplugin/store' ).get( id ) );
```

The hook accepts a dependency array as its second
argument, used to decide when to re-create the
subscription. Pass `[ id ]` (or `[]` when the mapping is
truly static) to keep subscription churn bounded.

### Anti-pattern 5 — Treating `select()` as a fetch trigger when it isn't

```js
// Misconception: "calling select will trigger the resolver."
const post = select( 'core' ).getEntityRecord( 'postType', 'post', 42 );
// post is undefined here on first call, even if a resolver exists.
```

`select()` returns whatever state currently holds. The
resolver fires *as a side effect of having been
selected*, but the returned value is the pre-resolve
value (often `undefined`). Use `resolveSelect()` when
you need to wait, or `useSelect` if you're rendering
and can re-render once the resolver completes.

### Anti-pattern 6 — Cross-plugin store name collisions

```js
// Two plugins both:
register( createReduxStore( 'inventory', { … } ) );
```

The second registration is rejected with a console
warning. State silently belongs to the first plugin's
shape. Always namespace: `acme/inventory`,
`bravo/inventory`.

## OPERATIONAL NOTES

The registry's interpretive shape, in proportional
v2 vocabulary:

- **Federation** is the central fit. Many plugins each
  register stores; all live in one shared registry; any
  participant can read any other's stores by name. This
  is recognizable as the same federation shape that
  appears in plugin-dev (PHP-side registration around
  shared core registries) and in `wp-scripts`'s externals
  contract (JS-side packages federated as `window.wp.*`).
  Naming Federation here is genuinely clarifying because
  the registry's design only makes sense under this
  reading.
- **Law 1 (Declaration ≠ Exposure)** appears in a
  bifurcated form across multiple sub-surfaces. A store
  config *declares* what state exists; `register()`
  *exposes* it to the registry. Selectors *declare* what
  is readable; `select()` calls *expose* current state
  to consumers. Action creators *declare* what mutations
  are possible; `dispatch()` *exposes* one. The pattern
  recurs at three levels in the same chunk. Worth one
  cross-reference, not three.
- **Doctrine 6 (Authority Mediation)** appears *softly*.
  All state mutation must flow through an action; there
  is no direct state-write surface. This is mediation at
  the *write* level, not at the *capability* level
  (capability-checked actions exist but are application
  concerns, not registry mechanics). Worth one mention;
  not a section. The pattern here is *write-channel
  mediation*, weaker than Doctrine 6's full access-control
  expression.

What this chunk is **not** about:

- **Law 3b (Cross-Runtime Authority Continuity Bridge).**
  This is the highest-risk adjacency to name precisely.
  The registry lives in *one* runtime — the editor
  runtime, a single browser-side JS context. No state
  crosses an active execution boundary. The registry
  does not bridge; it concentrates. Hydration scenarios
  (where state declared on the server is rehydrated into
  this registry on the client) are where Law 3b applies,
  and that mechanism lives in `interactivity.hydration`,
  not here. Conflating the registry's federation with
  Law 3b's cross-runtime continuity would dilute Law
  3b's meaning. Same family of words (state, continuity);
  different mechanism (single-runtime federation vs
  cross-runtime authority preservation).
- **Law 4 (Arbitration Compiler).** Selector resolution
  is not candidate arbitration. There is one selector
  per name, one resolver per selector. The "has this
  resolver run for these args" check is a cache, not a
  ladder. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** The registry
  exists entirely at runtime. The build pipeline is
  upstream and does not interact with it. Omitted.
- **Section X archetypes.** A state registry is not a
  "civilization." The Computational-heavy and
  Governance-heavy archetypes are frames for whole
  bounded contexts; the registry is one tool inside the
  data-layer context. Omitted.

A small literacy contribution worth pinning, on the
order of "metadata continuity ≠ runtime continuity" and
"authoring interaction ≠ content persistence":

> *Registry continuity ≠ state continuity.* A long-lived
> singleton that holds many stores is a stable name
> index; the individual store states inside it have
> their own lifecycles, mutate freely, and are not
> mediated by the registry's continuity. The registry
> persists; the state it indexes does not "persist"
> through the registry — it persists *despite* the
> registry being a passive index.

This is useful for future data-layer / interactivity
chunks that need to discuss the difference between
"there is a place where state lives" and "state survives
across that place's lifetime."

## CHECKLIST

When using `@wordpress/data`:

- [ ] Namespace store names with a vendor prefix
      (`vendor/store-name`). Plain names will collide.
- [ ] Read state through the appropriate API for your
      context: `select()` for one-shot reads in
      handlers / non-React; `useSelect` for reactive
      React rendering; `resolveSelect()` when you
      specifically need to await a resolver.
- [ ] Always pass the dependency array to `useSelect` /
      `useDispatch`. Empty `[]` for fully static
      mappings; the values your mapping closes over
      otherwise.
- [ ] Keep mapping functions in `useSelect` cheap.
      Move expensive transforms to memoized selectors
      inside the store, or to `useMemo` outside the
      hook.
- [ ] Mutate state only through dispatched actions.
      Direct mutation breaks Redux invariants and
      re-render behavior.
- [ ] If your selector is backed by a resolver,
      surface loading state via
      `hasFinishedResolution` / `isResolving` rather
      than re-tracking pending state in components.
- [ ] If a value is component-local (not shared,
      not reactive across components), keep it in
      `useState`. Don't promote local state to a
      registered store without need.

## REFERENCES

- `@wordpress/data` package reference. Documents
  `createReduxStore`, `register`, `select`, `dispatch`,
  `useSelect`, `useDispatch`, `resolveSelect`, controls.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/
- Core data stores reference. Per-store documentation
  for `core`, `core/block-editor`, `core/notices`,
  `core/preferences`, etc.
  https://developer.wordpress.org/block-editor/reference-guides/data/
- `@wordpress/data` README on GitHub. Most up-to-date
  source for the API surface.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/data/README.md
- Make WordPress Core — introduction to the Data module.
  Documents the original federation rationale and the
  decision against a single combined store.
  https://make.wordpress.org/core/2018/11/07/introduction-to-the-data-module/

Cross-context:

- `data-layer.entity-resolution` — built on top of this
  registry. The `core` store provides
  `getEntityRecord` selectors and corresponding
  resolvers that fetch from the REST API.
- `data-layer.persistence` — write/reconciliation
  mechanism for entity stores. Dispatches save actions
  and reconciles results back into entity state.
- `interactivity.runtime-state` — parallel state model
  for front-end interactivity. Different runtime,
  different mechanism, deliberately not bridged into
  this registry.
- `interactivity.hydration` — where Law 3b actually
  applies for state crossing runtime boundaries; the
  registry pattern here does not.
- `block.edit-and-save-contracts` — the editor `edit`
  function consumes / dispatches against this registry
  during the interaction contract.
