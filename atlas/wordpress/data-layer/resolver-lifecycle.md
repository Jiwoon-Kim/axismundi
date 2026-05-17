---
rule_id: data-layer.resolver-lifecycle
domain: data-layer
topic: state-uncertainty
field_cluster: resolver-substrate
wp_min: "5.0"
wp_recommended: "6.5+"
package_min: "@wordpress/data@^9"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/#resolvers
    section: "@wordpress/data — resolvers, resolution metadata, invalidateResolution"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/data/README.md#resolvers
    section: "Resolver generators, controls integration, status selectors"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2018/11/07/introduction-to-the-data-module/
    section: "Original Data module rationale — why declarative async state is the model"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/
    section: "@wordpress/api-fetch — typical apiFetch control consumed by resolvers"
    captured: 2026-05-10
related:
  - data-layer.wp-data-registry                 # the registry / store substrate this lifecycle sits on
  - data-layer.entity-resolution                # the canonical consumer — core entity resolvers fetch via REST
  - data-layer.persistence                      # write-side counterpart; dispatched actions can invalidate resolutions
  - interactivity.runtime-state                 # parallel state model with no resolver layer (different runtime, different mechanism)
---

# RULE — `@wordpress/data` resolver lifecycle — declarative governance of state uncertainty

## WHEN

You need to reason about *when* state becomes available
in a `@wordpress/data` store, *who* triggers the fetch,
*how* the data layer tracks that work, and *when* a
fetched value should be re-fetched. Use this knowledge
when:

- A `useSelect` returns `undefined` on first render and
  you need to understand what's about to happen.
- Designing a store that should auto-fetch from REST
  (or any async source) on first selector access.
- Wiring loading / error UI based on the data layer's
  own resolution state rather than per-component
  pending flags.
- Implementing cache invalidation after a write so
  consumers re-fetch fresh data.
- Reading core code that uses generator-based resolvers
  (`function*`) and want to follow the controls dispatch.

This chunk does **not** cover:

- The store, registry, and selector/dispatch APIs in
  general — that is `data-layer.wp-data-registry`. This
  chunk assumes that registry chunk as background.
- The specific entity resolvers shipped by `core`
  (`getEntityRecord`, `getEntityRecords`, etc.) — those
  are documented in `data-layer.entity-resolution` from
  the consumer's perspective.
- The write-side reconciliation flow that follows a
  successful save — that is in `data-layer.persistence`.

The principle this chunk operates under: **A resolver
governs how an unresolved selector becomes resolved.**
The mechanism is declarative — the resolver describes
*what to do once* per (selector, args) tuple, and the
data layer manages the rest (cache keys, status,
invalidation, error capture).

## SHAPE

### A. Resolver as selector-bound co-routine

A resolver is declared *next to* a selector, in the
store config, with the same name:

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

createReduxStore( 'myplugin/store', {
    reducer,
    actions,
    selectors,
    resolvers,
} );
```

Three properties of this binding:

- **One resolver per selector name.** The resolver is
  identified by matching the selector's key. There is
  no notion of "multiple resolvers competing for one
  selector."
- **The resolver receives the selector's args
  (without `state`).** When the consumer calls
  `select( store ).getThing( 42 )`, the resolver
  receives `42`. The resolver doesn't read state
  directly; it produces actions that mutate state
  through the reducer.
- **Resolvers are typically generators.** The `function*`
  shape lets the resolver `yield` declarative side-effect
  descriptors (Section D) instead of `await`ing
  imperatively. The data layer drives the generator
  forward by handling each yielded descriptor through
  the registered controls.

The selector and the resolver are *paired contracts*:

- Selector contract: "given current state and args,
  return what's available *now*."
- Resolver contract: "given args, ensure that state
  *will hold* the value that selector should return —
  by dispatching the actions that put it there."

The selector knows nothing about how state arrives. The
resolver knows nothing about how state is read. The
binding is purely by name.

### B. The selector trigger flow

Resolvers do not fire on store registration. They fire
*lazily*, in response to selector calls, and only once
per unique args combination. The flow on first access:

```
1. Component renders, calls:
     select( 'myplugin/store' ).getThing( 42 )
                              │
2. Data layer checks resolution metadata for ('getThing', [42]):
     hasFinishedResolution → false
     isResolving           → false
                              │
3. Two things happen, in this order:
     a. The selector runs against current state, returns
        undefined (state.things[42] doesn't exist yet).
     b. The data layer marks ('getThing', [42]) as
        resolving, then invokes resolvers.getThing(42).
                              │
4. The resolver generator runs. Each yielded descriptor
   is dispatched to the controls system, which performs
   the side effect (fetch, etc.) and resumes the
   generator with the result.
                              │
5. The resolver returns (or yields) action(s) that the
   data layer dispatches. Reducer updates state.
                              │
6. Data layer marks ('getThing', [42]) as finished.
                              │
7. Subscribed useSelect consumers re-render. Their
   select call now returns state.things[42] = result.
```

Two important properties of this flow:

- **The selector value returned at step 3a is `undefined`
  (or whatever the selector computes from incomplete
  state).** The resolver firing does not block the call.
  Consumers must be prepared to render a "loading"
  state on first call.
- **The resolver fires per unique args combination.**
  `getThing(42)` and `getThing(43)` are independent
  resolutions; the resolver runs once for each.

### C. Resolution metadata — cache keys and status surfaces

Resolution status is itself first-class state, queryable
through dedicated selectors that the data layer
auto-injects into every store:

| Selector                                           | Returns                                             |
| -------------------------------------------------- | --------------------------------------------------- |
| `hasStartedResolution( selName, args )`            | `true` if the resolver has been invoked at least once for these args |
| `isResolving( selName, args )`                     | `true` while the resolver is mid-run                |
| `hasFinishedResolution( selName, args )`           | `true` after the resolver completes (success or error) |
| `hasResolutionFailed( selName, args )`             | `true` if the resolver threw                        |
| `getResolutionState( selName, args )`              | The full resolution metadata object                 |
| `getResolutionError( selName, args )`              | The error object if `hasResolutionFailed` is true   |

The cache key is `(selectorName, args)`. `args` is
serialized into a stable string under the hood, so
calls with structurally equal args share a resolution
entry. Two consequences:

- **Argument identity matters.** `getThing(42)` and
  `getThing('42')` are different cache entries. Pass
  args of consistent types to share resolution state
  across consumers.
- **Resolution metadata persists for the registry's
  lifetime** (until explicitly invalidated, Section E).
  A consumer mounting later doesn't re-trigger the
  resolver — it sees the already-resolved state and
  doesn't even fire the resolver-trigger branch.

This makes the resolution metadata itself a useful
rendering input. A common pattern:

```js
const { thing, isLoading } = useSelect( ( select ) => {
    const store = select( 'myplugin/store' );
    return {
        thing:     store.getThing( id ),
        isLoading: ! store.hasFinishedResolution( 'getThing', [ id ] ),
    };
}, [ id ] );
```

The component renders directly from data layer state,
without tracking its own pending flag.

### D. Controls and generators — declarative side effects

The reason resolvers use generators (`function*`) rather
than `async function` is that the data layer wants to
intercept side effects declaratively. A generator yields
*descriptions* of work; registered controls perform the
actual work and resume the generator with the result.

The shape of a yielded descriptor is up to convention,
but the canonical example is `apiFetch`:

```js
import apiFetch from '@wordpress/api-fetch';

const resolvers = {
    *getThing( id ) {
        const result = yield apiFetch( { path: `/myplugin/v1/thing/${ id }` } );
        return actions.receiveThing( id, result );
    },
};
```

`apiFetch( ... )` returns a control descriptor (a plain
object with a recognizable shape). The default controls
provided by `@wordpress/data` recognize it, perform the
HTTP request, and resume the generator with the parsed
response.

Three benefits:

- **Testability.** A resolver is a pure generator. Tests
  can step through it, providing mock results for each
  yield, without a real HTTP fetch.
- **Side-effect substitution.** Controls can be
  overridden per-store (or scoped via custom registries)
  to redirect fetches in test, or to add observability
  middleware.
- **Single dispatch surface.** Whether the resolver
  ultimately reads from REST, from `localStorage`, or
  from a synchronous computation, all consumers see the
  same selector and the same cache lifecycle.

Generator returns are also dispatched. If the generator's
final `return` value is an action object (or a thunk), it
is dispatched into the store. This is the conventional
way to "commit" the fetched data into state.

### E. Invalidation — declaring freshness expiry

After a successful write (Section is in
`data-layer.persistence`), or any other event that
should make a previously-resolved value stale, the data
layer needs to know that the cached resolution is no
longer authoritative. Three invalidation actions:

| Action                                                       | Scope                                              |
| ------------------------------------------------------------ | -------------------------------------------------- |
| `invalidateResolution( selName, args )`                      | One specific (selector, args) entry                |
| `invalidateResolutionForStoreSelector( selName )`            | All args combinations of one selector              |
| `invalidateResolutionForStore()`                             | Every resolution in the store                      |

Dispatched the same way as any other action:

```js
dispatch( 'myplugin/store' ).invalidateResolution( 'getThing', [ 42 ] );
```

After invalidation, the next `select( store ).getThing( 42 )`
call sees no finished resolution and re-triggers the
resolver. Subscribed consumers re-render with the
refreshed state.

Two properties to hold onto:

- **Invalidation is freshness, not deletion.** State
  itself is not removed from the store. Only the
  resolution metadata is cleared. The selector continues
  to return whatever state holds until the resolver
  completes the new fetch and dispatches a fresh action.
- **Invalidation is the consumer's responsibility.** The
  data layer does not auto-invalidate based on time,
  events, or write actions. Stores that want
  "save then re-fetch" must dispatch invalidation
  explicitly as part of their save handler.

### F. Failure and re-resolution

When a resolver throws (or its generator yields a
descriptor that the controls system rejects), the data
layer captures the error:

- `hasFinishedResolution( ... )` becomes `true`. The
  resolution is *complete*, just unsuccessfully.
- `hasResolutionFailed( ... )` becomes `true`.
- `getResolutionError( ... )` returns the error object.
- The selector continues to return whatever state holds
  (typically still `undefined` because the resolver
  didn't dispatch a populating action).

A failed resolution does **not** auto-retry. To retry,
the consumer must dispatch `invalidateResolution( ... )`,
which clears the failure metadata; the next selector
call then re-triggers the resolver.

Common pattern for retry-on-demand:

```js
const { value, isLoading, error } = useSelect( ( select ) => {
    const store = select( 'myplugin/store' );
    return {
        value:     store.getThing( id ),
        isLoading: store.isResolving( 'getThing', [ id ] ),
        error:     store.getResolutionError( 'getThing', [ id ] ),
    };
}, [ id ] );

const { invalidateResolution } = useDispatch( 'myplugin/store' );

if ( error ) {
    return <Retry onClick={ () => invalidateResolution( 'getThing', [ id ] ) } />;
}
```

The error surface is symmetric with the success surface:
both are queryable, both are clearable, both feed
component rendering through the same `useSelect`
mechanism.

## WHY

### Why lazy and once-per-args

Eager resolution would mean fetching everything at
registration. Re-resolving on every selector call would
mean redundant fetches for every consumer of the same
data. The lazy + once-per-args design splits the
difference: data is fetched when needed, exactly as
many times as needed (one per unique args combination
plus any explicit invalidations), and shared across all
consumers transparently.

### Why generators instead of `async function`

`async function` is opaque to the runtime — the data
layer cannot inspect what the function is doing
mid-`await`. Generators are inspectable: each yield
hands control back to the data layer, which can decide
how to satisfy the yielded descriptor. This is what
makes controls substitutable and resolvers testable
without mocking the global fetch.

### Why explicit invalidation rather than auto-invalidation

Auto-invalidation would require the data layer to
understand the *meaning* of writes — "saving a post
invalidates the post's getEntityRecord resolution." That
meaning is store-specific; the registry has no
domain-level knowledge of any given store. Putting
invalidation in the dispatch handler keeps the meaning
where the meaning lives (in the store) and keeps the
registry as a pure mechanism layer.

### Why resolution metadata is itself state

If resolution status were stored in some external
"private" structure, components couldn't subscribe to
it through the same `useSelect` mechanism they use for
data. By making `hasFinishedResolution`, `isResolving`,
etc. real selectors, loading and error UI become
ordinary subscriptions over ordinary selectors. The
data layer has only one subscription mechanism, not
two.

## WHEN NOT

Skip the resolver mechanism if:

- Your store is **not async-backed**. Synchronous state
  (UI flags, local computations) doesn't need a
  resolver — the selector reads state directly. No
  resolver, no resolution metadata, no invalidation.
- You need **non-cached fetches** (e.g., posting to an
  endpoint without storing the response). Use a
  thunk-shaped action, not a resolver. Resolvers are
  for state population.
- You are working in **interactivity (front-end view
  scripts)**. The interactivity API has no resolver
  layer; its state model is a different mechanism with
  different uncertainty management.
- You want **continuous polling**. Resolvers fire once
  per args. For polling, set up a timer in component
  effect that dispatches `invalidateResolution` on
  interval; resolvers will refire.

## COUNTER-PATTERNS

### Anti-pattern 1 — Treating `select()` as a fetch trigger that returns the result

```js
const post = select( 'core' ).getEntityRecord( 'postType', 'post', 42 );
console.log( post.title );  // TypeError: cannot read property 'title' of undefined
```

`select()` returns *current state*. The resolver fires
as a side effect, but the call returns before the
resolver completes. Either:

- Use `resolveSelect` if you need a Promise:
  `await resolveSelect( 'core' ).getEntityRecord( ... )`.
- Use `useSelect` if you're rendering and can re-render
  when the resolver completes.

### Anti-pattern 2 — Re-implementing pending state in the component

```js
function MyComponent({ id }) {
    const [ isLoading, setLoading ] = useState( true );
    const value = useSelect( ( s ) => s( store ).getThing( id ), [ id ] );

    useEffect( () => {
        if ( value !== undefined ) setLoading( false );
    }, [ value ] );

    return isLoading ? <Spinner /> : <div>{ value }</div>;
}
```

The data layer already tracks loading state. Read it:

```js
const { value, isLoading } = useSelect( ( s ) => {
    const store = s( 'myplugin/store' );
    return {
        value:     store.getThing( id ),
        isLoading: ! store.hasFinishedResolution( 'getThing', [ id ] ),
    };
}, [ id ] );
```

### Anti-pattern 3 — Omitting `args` to status selectors

```js
const isLoading = useSelect( ( s ) => s( store ).isResolving( 'getThing' ), [] );
```

`isResolving` requires the args array. Without it,
you're querying a different cache key and almost
certainly always getting `false`. Pass the args
exactly as they would be passed to the selector:
`isResolving( 'getThing', [ 42 ] )`.

### Anti-pattern 4 — Calling fetch directly in a resolver instead of yielding

```js
const resolvers = {
    *getThing( id ) {
        const result = await fetch( `/wp-json/myplugin/v1/thing/${ id }` ).then( r => r.json() );
        return actions.receiveThing( id, result );
    },
};
```

Mixing `await` inside a generator is invalid. Even if
it weren't, calling `fetch` directly bypasses the
controls system: no test substitution, no `apiFetch`
nonce handling, no consistency with the rest of
WordPress's data layer. Yield a control descriptor:

```js
const result = yield apiFetch( { path: `/myplugin/v1/thing/${ id }` } );
```

### Anti-pattern 5 — Forgetting to invalidate after a write

```js
*saveThing( id, value ) {
    yield apiFetch( { path: `/myplugin/v1/thing/${ id }`, method: 'POST', data: value } );
    return actions.receiveThing( id, value );
}
// getThing(id) cache is still considered fresh from the previous fetch.
```

If the write changes server-side state that affects
other selectors (e.g., a list selector), invalidate
explicitly:

```js
*saveThing( id, value ) {
    yield apiFetch( { ... } );
    yield actions.receiveThing( id, value );
    yield actions.invalidateResolution( 'getThings', [] );
}
```

### Anti-pattern 6 — Auto-retry loops on failed resolution

```js
useEffect( () => {
    if ( error ) {
        invalidateResolution( 'getThing', [ id ] );
    }
}, [ error ] );
```

This will retry indefinitely while the underlying
problem persists, hammering the server. Resolution
failures should surface to the user (or to logging),
not auto-retry. Make the retry user-initiated, or use
exponential backoff with explicit attempt counts.

## OPERATIONAL NOTES

The lifecycle's interpretive shape, in proportional
v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is a strong fit
  in three layered forms. The resolver is *declared*
  in store config; it is *exposed* (run) by the
  data layer's selector-trigger logic on cache miss.
  The status (resolving / finished / error) is
  *declared* as queryable surfaces by the data layer's
  auto-injected selectors; consumers *expose*
  current status by calling them. Naming Law 1 here is
  genuinely clarifying because the entire lifecycle is
  a layered declaration-then-exposure cascade —
  resolver declares what to do, status declares what
  happened, action declares what to commit.
- **Doctrine 6 (Authority Mediation)** appears
  *softly* in the controls layer. Resolvers don't
  perform side effects directly; they yield
  descriptors that the controls system mediates. This
  is *side-effect mediation*, distinct from access
  mediation — the same softer expression as the
  write-channel mediation noted in
  `data-layer.wp-data-registry`. Worth one mention;
  not a section.
- **Federation** continues to apply at the registry
  level (each store has its own resolvers; the
  registry indexes them by store name). Already
  framed in the registry chunk; not re-elaborated
  here.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** The most
  important non-fit to name precisely. The word
  *resolution* invites the reading "the data layer
  resolves between candidates" — but there are no
  candidates. There is one resolver per selector;
  one resolver entry per (selector, args) tuple; the
  "should we run the resolver?" check is a Boolean
  cache lookup, not a deterministic walk over an
  ordered candidate ladder. Naming Law 4 here would
  be a category error driven by word overlap. The
  resolver lifecycle is *need fulfillment*, not
  *option arbitration*. Conflating the two would
  dilute Law 4's meaning where it actually applies
  (template hierarchy, navigation fallback,
  `locate_template`).
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** The async fetch invites the reading
  "the resolver bridges the JS runtime and the
  server runtime" — but no runtime authority is
  preserved across an active execution boundary.
  The fetch returns data; data populates state; no
  state, identity, or capability persists from the
  server's runtime into the JS runtime as
  authority. The server is a *source*, not a
  *runtime context whose authority must continue*.
  Async ≠ runtime boundary crossing.
- **Law 6 (Compiler ↔ Runtime Split).** The
  lifecycle exists entirely at runtime. The build
  pipeline is upstream and does not interact with
  it. Omitted.
- **Section X archetypes.** A resolver lifecycle is
  not a "civilization." The framing would inflate
  ordinary state-machine mechanics into ontological
  language that obscures rather than clarifies.
  Omitted.

A small literacy contribution worth pinning:

> *Need fulfillment ≠ option arbitration.* A
> mechanism that decides "this state needs to be
> populated, here is the one declared way to populate
> it" is not the same shape as a mechanism that
> decides "given several candidates, here is the
> winner." Both involve a moment of decision; the
> shape of the decision differs. Resolution chooses
> *whether to run the one resolver*; arbitration
> chooses *which of many candidates wins*.

Useful for future chunks where async, cache, or
"resolution" vocabulary appears and the temptation to
reach for Law 4 returns. Word overlap is not pattern
fit.

## CHECKLIST

When designing or consuming resolvers:

- [ ] One resolver per async-backed selector. The
      selector reads state; the resolver populates
      it.
- [ ] Use generator (`function*`) shape. Yield
      control descriptors (`apiFetch`, etc.) for side
      effects; do not `await` directly.
- [ ] Read loading state via `isResolving` /
      `hasFinishedResolution`, not via per-component
      `useState` flags.
- [ ] Pass `args` to status selectors exactly as
      they would be passed to the data selector.
      Mismatched args = mismatched cache key.
- [ ] Invalidate explicitly after writes that
      affect what selectors should return. The data
      layer does not auto-invalidate.
- [ ] Surface resolution errors to users; do not
      auto-retry on failure without explicit user
      action or backoff.
- [ ] If you need a Promise rather than a render
      subscription, use `resolveSelect` rather than
      racing `select` calls.

## REFERENCES

- `@wordpress/data` resolvers section. Documents
  resolver shape, the controls integration, and the
  status-selector surface.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/#resolvers
- `@wordpress/data` README on GitHub — current
  resolver API including invalidation actions.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/data/README.md#resolvers
- Make WordPress Core — introduction to the Data
  module. Documents the declarative-async rationale
  this chunk operates under.
  https://make.wordpress.org/core/2018/11/07/introduction-to-the-data-module/
- `@wordpress/api-fetch` — the typical control
  descriptor consumed by resolvers, including its
  nonce / preloading conveniences.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/

Cross-context:

- `data-layer.wp-data-registry` — the registry,
  store, and selector/dispatch substrate this
  lifecycle sits on. Read first.
- `data-layer.entity-resolution` — the canonical
  resolver consumer. The `core` store's entity
  selectors / resolvers fetch from REST through this
  exact mechanism.
- `data-layer.persistence` — write-side
  reconciliation. The place where invalidation is
  typically dispatched after a successful save.
- `interactivity.runtime-state` — parallel state
  model with no resolver layer. Documenting the
  contrast helps avoid expecting the same
  uncertainty-management patterns there.
