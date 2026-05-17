---
rule_id: interactivity.data-wp-watch-and-init
domain: interactivity
topic: reactive-callback-runtime
field_cluster: state-reactive-and-mount-bootstrap
wp_min: "6.5"
wp_recommended: "6.5+"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/
    section: "Interactivity API — store(), callbacks (init, watch)"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/interactivity/docs/2-api-reference.md
    section: "Interactivity API directive reference — data-wp-init, data-wp-watch"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/core-concepts/the-reactive-and-declarative-mindset/
    section: "Reactive and declarative mindset — callbacks vs actions"
    captured: 2026-05-10
related:
  - interactivity.view-script-activation       # the activation contract; init runs after Phase 5 hydration
  - interactivity.data-wp-on-and-actions       # the event-triggered execution chunk; the trio's third leg
  - interactivity.runtime-state                # the store mechanism; callbacks live alongside actions
  - interactivity.directive-protocol           # broader directive grammar; init/watch are members
---

# RULE — `data-wp-init` and `data-wp-watch` — mount-bootstrap and state-reactive callbacks

## WHEN

You need to reason about *when* an interactivity-API
callback runs that is **not** triggered by a user
event — code that should fire once on mount, or
code that should re-run whenever observed state
changes. Use this knowledge when:

- Setting up DOM-level work that needs to happen
  once when the block becomes interactive (focus a
  field, fetch initial data, attach a third-party
  library).
- Reacting to derived state — when a value in
  store state changes, computing or syncing
  something downstream (DOM updates the
  declarative directives can't express, external
  library calls, mirroring state to URL params).
- Choosing between `data-wp-init` (one-shot) and
  `data-wp-watch` (reactive) for a behavior that
  could fit either.
- Diagnosing "the callback runs more / fewer
  times than I expected" — almost always: choosing
  watch when init fits, or vice versa, or
  forgetting that watch re-runs on every observed
  dependency change.
- Reading core code that uses `callbacks` (rather
  than `actions`) in `store()` config.

This chunk does **not** cover:

- The user-event-triggered action mechanism
  (`data-wp-on--*`, `actions: {}`) — covered in
  `interactivity.data-wp-on-and-actions`. This
  chunk is the *non-event* trigger family.
- The view-script activation contract that brings
  the runtime up to the point where `init` and
  `watch` can fire — covered in
  `interactivity.view-script-activation`. This
  chunk operates *after* hydration.
- The store mechanism in general — covered in
  `interactivity.runtime-state`. This chunk
  focuses on the `callbacks` slice of store
  config.
- The reactive system's signal-tracking
  internals. The chunk treats reactivity as a
  contract ("watch re-runs when observed state
  changes"); how the runtime *implements* the
  observation is the runtime's concern.

The principle this chunk operates under: **An
interactive block has at least three distinct
trigger sources for its behavior — user events
(`data-wp-on--*`), state changes (`data-wp-watch`),
and mount lifecycle (`data-wp-init`). The three are
structurally distinct: different declarations,
different invocation rules, different lifecycles,
and different relationships to "what causes the
code to run." Confusing them flattens an important
taxonomy.**

## SHAPE

### A. The two callback families

In addition to `actions` (for event handlers,
covered in
`interactivity.data-wp-on-and-actions`), a store
declares `callbacks` for non-event-triggered
behavior:

```js
import { store, getContext } from '@wordpress/interactivity';

store( 'myplugin/example', {
    state: {
        count: 0,
        doubled: 0,
    },
    actions: {
        increment() {
            getContext().count += 1;
        },
    },
    callbacks: {
        // Fires once on mount:
        initialize() {
            console.log( 'block mounted' );
        },
        // Re-fires whenever observed state changes:
        syncDoubled() {
            const context = getContext();
            context.doubled = context.count * 2;
        },
    },
} );
```

The block's HTML wires the callbacks to the
runtime via dedicated directives:

```html
<div
    data-wp-interactive="myplugin/example"
    data-wp-context='{"count": 0, "doubled": 0}'
    data-wp-init="callbacks.initialize"
    data-wp-watch="callbacks.syncDoubled"
>
    <p data-wp-text="context.count">0</p>
    <p data-wp-text="context.doubled">0</p>
    <button data-wp-on--click="actions.increment">+</button>
</div>
```

Three callbacks-or-actions families, three
directives, three trigger sources:

| Directive               | Trigger source            | Run frequency                        |
| ----------------------- | ------------------------- | ------------------------------------ |
| `data-wp-on--{event}`   | User DOM events           | Once per event fire                  |
| `data-wp-init`          | Element mount (post-hydration) | Once per mount                       |
| `data-wp-watch`         | Observed state changes    | Once per change to read state        |

The three directives target functions in different
slots of the store config:

| Directive               | Reads from store's...  |
| ----------------------- | ---------------------- |
| `data-wp-on--*`         | `actions: {}`          |
| `data-wp-init`          | `callbacks: {}`        |
| `data-wp-watch`         | `callbacks: {}`        |

`actions` and `callbacks` are sibling slots; the
runtime treats them differently because their
trigger semantics differ.

### B. `data-wp-init` — one-shot mount bootstrap

```html
<div data-wp-init="callbacks.initialize">…</div>
```

Behavior:

- Runs **once** after the runtime hydrates the
  element.
- Receives no event argument (it's not an event
  response).
- Has access to `getContext()`, `getElement()`,
  and the store's `state`/`actions`/`callbacks`
  via the runtime's invocation context.
- May return a cleanup function; the runtime
  calls it when the element is unmounted.

Typical use cases:

- Fetching initial data the server-rendered
  state didn't provide.
- Attaching a third-party library to the element
  (a chart library, a code highlighter, a
  carousel).
- Reading URL parameters and writing them into
  context.
- Setting initial focus or scroll position.

```js
callbacks: {
    initialize() {
        const { ref } = getElement();
        const chart = new ThirdPartyChart( ref );
        chart.render( getContext().data );

        return () => {
            chart.destroy();  // cleanup on unmount
        };
    },
},
```

The cleanup function is the right place to
detach event listeners, destroy library
instances, abort in-flight requests, etc.
Forgetting it leaks listeners and library state
across unmounts.

**Multiple init callbacks per element** — use
named variants:

```html
<div
    data-wp-init--chart="callbacks.initChart"
    data-wp-init--analytics="callbacks.initAnalytics"
>…</div>
```

The `--{name}` suffix lets multiple init handlers
coexist on the same element, each independently
mountable and cleaning up.

### C. `data-wp-watch` — reactive dependency tracking

```html
<div data-wp-watch="callbacks.syncDoubled">…</div>
```

Behavior:

- Runs **once on mount** (initial run, like init).
- Then **re-runs every time observed state
  changes**. The runtime tracks which state /
  context properties the callback reads during
  its run; subsequent changes to those
  properties trigger re-execution.
- Same `getContext()` / `getElement()` /
  invocation-context affordances as init.
- May return a cleanup function; the runtime
  calls it **before each re-run** and on
  unmount.

```js
callbacks: {
    syncDoubled() {
        const context = getContext();
        context.doubled = context.count * 2;
        // Reading context.count here registers it as a dependency.
        // Subsequent changes to context.count will re-fire this callback.
    },
},
```

The reactive dependency graph is **automatic**.
The runtime observes which properties the
callback reads via property access during the
run; the read becomes a subscription. The
callback re-runs when *any* observed property
changes, with the new value visible on the next
read.

A consequence: callbacks that read many
properties become subscribers to all of them.
For performance, read narrowly; if a callback
only needs `context.count`, don't unconditionally
read `context.everythingElse` too.

**Multiple watches per element** — same `--{name}`
naming pattern as init:

```html
<div
    data-wp-watch--double="callbacks.syncDoubled"
    data-wp-watch--triple="callbacks.syncTripled"
>…</div>
```

Each runs independently with its own
dependency tracking and cleanup.

### D. `callbacks` vs `actions` — why they're separate slots

The store config separates them deliberately:

```js
store( 'myplugin/example', {
    actions: {
        // Functions invoked by user events (data-wp-on--*).
        increment() { … },
    },
    callbacks: {
        // Functions invoked by lifecycle / state observation
        // (data-wp-init, data-wp-watch).
        syncDoubled() { … },
    },
} );
```

The functional shape is similar (both are
functions; both have access to the same scope
helpers). The semantic shape differs:

- **Actions** answer *"what does this user
  interaction do?"* They are responses to events.
  They typically modify state.
- **Callbacks** answer *"what needs to happen at
  this lifecycle moment, or in response to this
  state change?"* They are responses to internal
  events (mount, state change) rather than
  external (user click).

Treating them as the same slot would lose the
distinction. The runtime can also optimize them
differently — actions are dispatched discretely;
callbacks integrate with the reactive
observation system.

### E. Cleanup functions — both `init` and `watch`

Both callback families support returning a
cleanup function. Same shape, different invocation
rules:

```js
callbacks: {
    initWithCleanup() {
        const interval = setInterval( …, 1000 );
        return () => clearInterval( interval );
    },
    watchWithCleanup() {
        const observer = new IntersectionObserver( … );
        observer.observe( getElement().ref );
        return () => observer.disconnect();
    },
},
```

| Callback type           | Cleanup runs when...                          |
| ----------------------- | --------------------------------------------- |
| `data-wp-init`          | Element unmounts                              |
| `data-wp-watch`         | Before next re-run + on unmount               |

For `watch`, the cleanup-then-re-run cycle
matters: don't accumulate state across runs that
the cleanup doesn't reset. Each re-run starts
with the cleanup's effects applied.

### F. Lifecycle ordering relative to hydration

The 4-step interactivity ladder from the
view-script activation chunk:

```
Embedded → Activated → Triggered → Executed
                ↓
        (this chunk's territory begins)
```

After Phase 5 (Activated / hydrated):

```
Hydration completes
        │
        ▼
data-wp-init callbacks fire (in mount order)
        │
        ▼
data-wp-watch callbacks fire initial run
(also in mount order)
        │
        ▼
[steady state — runtime is alive]
        │
        ▼
User events → actions fire (data-wp-on--*)
State changes → matching watches re-fire
        │
        ▼
Element unmounts
        │
        ▼
Cleanup functions run (init's once, watch's
final time)
```

The runtime is alive between hydration completion
and unmount. During that window, three trigger
sources can fire callbacks:

- User events → actions.
- State changes → watches.
- (init has already fired; doesn't re-fire in
  steady state.)

### G. The trigger source taxonomy

The directive trio surfaces a useful conceptual
distinction:

| Trigger source     | Directive               | Cause                           | Frequency               |
| ------------------ | ----------------------- | ------------------------------- | ----------------------- |
| External event     | `data-wp-on--{event}`   | User DOM interaction            | Once per event fire     |
| Mount lifecycle    | `data-wp-init`          | Element becomes interactive     | Once per mount          |
| State observation  | `data-wp-watch`         | Observed state property changes | Per change              |

These are **structurally distinct trigger sources**.
They aren't variants of each other; they have
different mechanisms, different invocation rules,
different relationships to "what causes the code
to run." A behavior that should run once on mount
is not the same as a behavior that should run on
state change is not the same as a behavior that
should run on user click.

The taxonomy gives reaching-for-the-right-directive
clarity: ask *what causes this to need to run?*
The answer maps to one of the three trigger
sources.

## WHY

### Why three trigger sources rather than one

A single "react-to-anything" mechanism would
require the author to specify *what* to react
to via configuration ("react to clicks, plus
state changes, plus mount"). The split lets each
directive express *one source* clearly:

- `data-wp-on--click` says "react to clicks."
- `data-wp-watch` says "react to state changes
  this callback observes."
- `data-wp-init` says "react to mount."

Each directive's meaning is unambiguous; the
author picks the directive that matches their
intent. The runtime knows what to subscribe to
without configuration.

### Why `init` is one-shot

Mount happens once per element in a single
hydrated session. Re-running `init` on every
state change would conflate it with `watch`;
re-running `init` on each cleanup would conflate
it with the cleanup-then-rerun cycle.

The one-shot semantics give `init` a clear
purpose: setup that needs to happen exactly once,
typically with a cleanup that undoes it exactly
once. The lifecycle is symmetric and predictable.

### Why `watch` automatic dependency tracking

The alternative — explicit dependency lists like
React's `useEffect( …, [ a, b, c ] )` — was
considered. The Interactivity API's choice of
*automatic* tracking has trade-offs:

- **Pro**: simpler authoring; the callback's
  reads *are* its subscriptions; no
  out-of-sync deps array.
- **Con**: harder to reason about subscription
  scope; reading more properties means
  subscribing to more.

The runtime chose automatic tracking, consistent
with signal-style reactivity (Solid, Vue 3,
modern Preact). The consequence: be deliberate
about what `watch` callbacks read.

### Why callbacks and actions are separate slots

Treating them as one slot would mean the runtime
couldn't distinguish "this function responds to
events" from "this function responds to state."
The distinction matters for optimization (the
runtime sets up different infrastructure for the
two) and for clarity (the author signals intent
by where they put the function).

The cost — slightly more boilerplate — is
trivial; the benefit is structural clarity.

## WHEN NOT

Skip `data-wp-init` and `data-wp-watch` if:

- The behavior is **event-triggered**. Use
  `data-wp-on--{event}` and put the function in
  `actions`. Init/watch are for non-event
  triggers.
- The behavior is **purely declarative** —
  binding text, classes, attributes to state
  values. Use `data-wp-text`, `data-wp-bind--*`,
  `data-wp-class--*`. The runtime handles the
  reactive update without needing a `watch`.
- The behavior is **server-side only** — `init`
  and `watch` are browser-runtime mechanisms.
  Server-side rendering doesn't need them.
- The mount-time setup can be **expressed as
  initial state** in `data-wp-context`. Setting
  state in HTML is simpler than computing it in
  `init`; use `init` only when the setup
  requires JS execution.

## COUNTER-PATTERNS

### Anti-pattern 1 — Using `watch` for one-shot setup

```js
callbacks: {
    setup() {
        const { ref } = getElement();
        ref.focus();  // intended once at mount
    },
},
```

```html
<input data-wp-watch="callbacks.setup" />
```

`watch` re-runs on every observed state change.
If `setup` reads any reactive state during its
run, it'll fire again later — with potentially
weird focus-related side effects. Use `init`:

```html
<input data-wp-init="callbacks.setup" />
```

### Anti-pattern 2 — Using `init` for state synchronization

```js
callbacks: {
    syncDoubled() {
        const context = getContext();
        context.doubled = context.count * 2;
    },
},
```

```html
<div data-wp-init="callbacks.syncDoubled">…</div>
```

This runs once at mount; `doubled` reflects the
initial `count` and never updates again as
`count` changes. Use `watch` for the reactive
follow:

```html
<div data-wp-watch="callbacks.syncDoubled">…</div>
```

### Anti-pattern 3 — Forgetting cleanup functions

```js
callbacks: {
    setupInterval() {
        const id = setInterval( …, 1000 );
        // No cleanup return.
    },
},
```

The interval keeps running after the element
unmounts; the page accumulates orphaned timers.
Always return cleanup for any `setX` /
`addEventListener` / library-instance setup:

```js
callbacks: {
    setupInterval() {
        const id = setInterval( …, 1000 );
        return () => clearInterval( id );
    },
},
```

### Anti-pattern 4 — Reading too much in a watch

```js
callbacks: {
    watchSomething() {
        const context = getContext();
        // Reads everything, subscribes to everything.
        const all = JSON.stringify( context );
        if ( all.length > 1000 ) {
            doSomething();
        }
    },
},
```

The callback subscribes to every property in
context because of the full serialization read.
Any change to any property re-fires it. Read
narrowly:

```js
callbacks: {
    watchSomething() {
        const { count } = getContext();
        if ( count > 100 ) {
            doSomething();
        }
    },
},
```

### Anti-pattern 5 — Mutating state inside a watch that observes the same state

```js
callbacks: {
    watchAndMutate() {
        const context = getContext();
        context.count += 1;  // mutating what we observe
    },
},
```

The watch observes `count`, mutates `count`,
which re-triggers the watch, which mutates
again — infinite loop (or at least until the
runtime's cycle protection trips).

Mutate state in actions (event-triggered) or in
watches that observe *different* state than
they mutate. Self-observing-and-mutating is a
loop.

### Anti-pattern 6 — Putting event handlers in `callbacks` instead of `actions`

```js
callbacks: {
    handleClick() {
        getContext().count += 1;
    },
},
```

```html
<button data-wp-on--click="callbacks.handleClick">+</button>
```

The directive says "look in actions"; the function
is in callbacks. Lookup misses; click does
nothing.

Put event handlers in `actions`; lifecycle /
reactive callbacks in `callbacks`. The slot
matches the trigger source.

## OPERATIONAL NOTES

The init/watch substrate's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *trigger-source-staged*
  form. The callbacks are *declared* in
  `store()`'s `callbacks: {}`; they are
  *exposed* (executed) when their trigger
  fires — `init` on mount, `watch` on
  observed state changes. Multi-stage Law 1:
  declaration → mount-stage → trigger-source
  → execution. Naming Law 1 here is genuinely
  clarifying because the *gap* between "this
  callback exists in the store" and "this
  callback has run" depends on which trigger
  source the callback is wired to (init = once
  per mount; watch = repeatedly).
- **Doctrine 5 (Authority Continuity)**
  appears *lightly*. The callback name +
  namespace persist as the identity surface
  across declaration and the runtime's
  invocation. Same pattern as the actions
  chunk's namespace continuity. Worth one
  mention; not a section.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit.* Multiple watch callbacks on the
  same element observe their own dependencies
  and re-run independently when those
  dependencies change. There is no candidate
  selection; all watches that have observed
  a now-changed property fire. Same pattern
  as hooks priority (8.36): composition, not
  arbitration. Multiple init callbacks on the
  same element via the `--{name}` suffix all
  fire on mount in declaration order; no
  candidate "wins." Omitted.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** All callback execution runs in
  the same browser JS runtime as the rest of
  the interactivity code. No runtime boundary
  crossings. Omitted.
- **Doctrine 6 (Authority Mediation).** The
  reactive runtime is internal mechanism; no
  capability checks, no access mediation. Same
  pattern as the actions chunk (8.33a):
  *event handling, not write-channel
  governance*. The same softness that doesn't
  apply to actions doesn't apply here either
  — even less so, because callbacks are
  framework-internal, not user-event-facing.
  Omitted.
- **Federation.** Per-store callbacks; not
  multi-participant federation in the registry
  sense. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).**
  Browser-runtime only. Omitted.
- **Section X archetypes.** A reactive
  callback substrate is not a "civilization."
  Same framework-omission discipline as the
  surrounding chunks. Omitted.

Two literacy contributions worth pinning:

> *Initialization ≠ reaction.* A callback that
> runs once when an element becomes interactive
> (`init`) is structurally distinct from a
> callback that re-runs whenever observed state
> changes (`watch`). Both are non-event-triggered
> callbacks; both live in the same `callbacks: {}`
> slot of the store config; both have access to
> the same scope helpers. But their lifecycles
> differ — init is one-shot with cleanup-on-
> unmount; watch is many-shot with
> cleanup-before-each-rerun. Treating them as
> interchangeable produces bugs: watch as
> setup misfires later; init as state-sync
> doesn't keep up.

This contribution extends the existence-vs-
operation toolkit with a *trigger-source
distinction* form. Where prior toolkit members
distinguished states (declared / activated /
executed), this one distinguishes
*lifecycle-once* from *reactive-recurring*
within an already-active runtime.

> *Trigger source taxonomy: event / state /
> mount.* An interactive block has at least
> three structurally distinct trigger sources
> for its behavior: external user events
> (`data-wp-on--*` → `actions`), observed state
> changes (`data-wp-watch` → `callbacks`), and
> mount lifecycle (`data-wp-init` →
> `callbacks`). The three are not variants of
> each other; each has its own directive, its
> own slot in the store config, its own
> invocation rule, its own lifecycle shape.
> Confusing them — putting event handlers in
> callbacks, using watch for one-shot setup,
> using init for ongoing sync — flattens an
> important taxonomy and produces predictable
> bugs.

This contribution names a *3-source taxonomy*
that didn't have a prose-level handle before.
It's not a constitutional pattern; it's an
authoring frame. When designing block
interactivity, ask *what causes this to need to
run?* The answer maps to one of the three
sources, which determines the directive +
store-slot pair to use.

The interactivity bounded context now spans
three chunks covering the trio:

- `interactivity.view-script-activation` — the
  activation contract that gets the runtime
  alive.
- `interactivity.data-wp-on-and-actions` — the
  event-triggered execution.
- `interactivity.data-wp-watch-and-init` (this
  chunk) — the lifecycle / state-reactive
  callbacks.

Together: *how the runtime starts*, *what user
events do*, and *what other (non-event) triggers
do*. The trio covers most of the practical
authoring surface.

## CHECKLIST

When using `data-wp-init` and `data-wp-watch`:

- [ ] Use `init` for mount-time setup that
      should run **once**. Examples: focus
      management, third-party library setup,
      reading URL params into context.
- [ ] Use `watch` for reactions to **observed
      state changes**. Examples: derived state
      sync, mirroring state to URL, calling an
      external library on data update.
- [ ] Put both in the `callbacks: {}` slot of
      `store()`, not in `actions: {}`.
- [ ] Always return a cleanup function from
      callbacks that set up timers, listeners,
      observers, or library instances.
- [ ] Don't read more state than the callback
      needs — the read becomes a subscription.
- [ ] Don't mutate state observed by the same
      watch — infinite loop.
- [ ] Use the `--{name}` suffix when an
      element needs multiple init or watch
      callbacks; each runs independently.
- [ ] When deciding between init / watch / on,
      ask: *what causes this to need to run?*
      Mount → init. State change → watch.
      User event → on.

## REFERENCES

- Interactivity API API reference. Documents
  `store()`, `actions`, `callbacks`,
  `getContext()`, `getElement()`.
  https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/
- Interactivity API directive reference
  (GitHub). Lists `data-wp-init`,
  `data-wp-watch`, the `--{name}` suffix
  pattern.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/interactivity/docs/2-api-reference.md
- Reactive and declarative mindset (core
  concepts). Documents the actions vs callbacks
  distinction.
  https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/core-concepts/the-reactive-and-declarative-mindset/

Cross-context:

- `interactivity.view-script-activation` — the
  activation contract. `init` and `watch` only
  fire after Phase 5 hydration completes.
- `interactivity.data-wp-on-and-actions` —
  the event-triggered execution chunk. The
  third leg of the interactivity trio.
- `interactivity.runtime-state` — the store
  mechanism. Callbacks and actions are sibling
  slots within the same store config.
- `interactivity.directive-protocol` — the
  broader directive grammar. `data-wp-init`
  and `data-wp-watch` are family members
  alongside `data-wp-on`, `data-wp-bind`,
  `data-wp-text`, etc.
