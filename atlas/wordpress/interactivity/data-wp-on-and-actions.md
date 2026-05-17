---
rule_id: interactivity.data-wp-on-and-actions
domain: interactivity
topic: action-execution-runtime
field_cluster: event-binding-and-action-dispatch
wp_min: "6.5"
wp_recommended: "6.5+"
status: stable
language: php-and-javascript
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/
    section: "Interactivity API — store(), actions, getContext, getElement"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/core-concepts/the-reactive-and-declarative-mindset/
    section: "Core concepts — reactive declarations, action shape"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/interactivity/docs/2-api-reference.md
    section: "Interactivity API reference — directive list including data-wp-on, data-wp-on-window, data-wp-on-document"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2024/03/04/interactivity-api-merged-in-6-5/
    section: "Interactivity API merge announcement"
    captured: 2026-05-10
related:
  - interactivity.view-script-activation        # the activation contract that this chunk's bindings depend on
  - interactivity.directive-protocol            # the broader directive grammar; data-wp-on is one family member
  - interactivity.runtime-state                 # the store mechanism that defines actions
  - interactivity.hydration                     # the mechanism that wires bindings; this chunk's bindings are post-hydration
---

# RULE — `data-wp-on--{event}` and the action execution runtime

## WHEN

You need to reason about *what happens* when a user
interacts with a hydrated interactivity-API block —
how event bindings reach action functions, what scope
the action runs in, and where the failure modes
between binding and execution live. Use this knowledge
when:

- Authoring a block that responds to clicks, input
  changes, form submissions, keyboard events, or any
  other DOM event.
- Choosing between `data-wp-on--*` (element scope),
  `data-wp-on-window--*`, and
  `data-wp-on-document--*`.
- Reading or writing actions inside a `store()`
  config — particularly understanding how actions
  access scoped state via `getContext()` and the
  current element via `getElement()`.
- Diagnosing "the click does nothing" — the failure
  is almost always one of: namespace mismatch,
  action path typo, action threw silently, or the
  binding was never hydrated.
- Implementing async actions (network requests,
  delayed updates) and reasoning about side-effect
  timing.

This chunk does **not** cover:

- The activation contract (when bindings *become*
  bindings) — covered in
  `interactivity.view-script-activation`. This
  chunk assumes hydration has completed.
- The full directive grammar (`data-wp-bind--*`,
  `data-wp-text`, `data-wp-class--*`,
  `data-wp-context`, etc.) — covered in
  `interactivity.directive-protocol`. This chunk
  focuses on the *event* family specifically.
- The store registration mechanism (`store()`'s
  state shape, store composition, derived state) —
  covered in `interactivity.runtime-state`. This
  chunk focuses on the *actions* slice.
- The hydration internals that make
  `data-wp-on--*` markup attach to DOM event
  listeners — covered in `interactivity.hydration`.
  This chunk operates at the level above hydration
  mechanics.

The principle this chunk operates under: **An event
binding wired up by hydration is not the same thing
as an action that has executed. Several preconditions
sit between "the binding is live" and "the action
ran" — and each one is a distinct failure surface.**

## SHAPE

### A. The directive shape — `data-wp-on--{event}`

The event-binding directive's syntax:

```html
<button
    data-wp-on--click="actions.increment"
>
    Increment
</button>
```

Two parts:

- **`data-wp-on--{eventName}`** — directive name. The
  segment after `--` is the DOM event to listen for:
  `click`, `input`, `change`, `submit`, `keydown`,
  `mouseenter`, `focus`, `blur`, etc. Any standard
  DOM event name works.
- **Value** — a *path string* referencing an action
  inside the currently scoped store. The default
  resolution is against the namespace declared by
  the nearest enclosing `data-wp-interactive`. Cross-
  namespace references use `actions.namespace::name`
  syntax.

The path resolution at runtime:

```
data-wp-interactive="myplugin/counter"
        │
        ▼
data-wp-on--click="actions.increment"
        │
        ▼
Resolves against the registered store
named "myplugin/counter":
        store.actions.increment
```

The path is a *static lookup string*, not a JS
expression. You can reference nested actions
(`actions.nested.handler`) but you cannot pass
arguments (`actions.foo("bar")`) or compose
expressions inline. To act on event-time values,
read from `getContext()` / the event object inside
the action.

### B. Action declaration in `store()`

Actions are declared as a property of the store
config:

```js
import { store, getContext, getElement } from '@wordpress/interactivity';

store( 'myplugin/counter', {
    state: {
        count: 0,
    },
    actions: {
        increment() {
            const context = getContext();
            context.count += 1;
        },
        decrement() {
            const context = getContext();
            context.count -= 1;
        },
        async fetchAndAdd() {
            const result = await fetch( '/wp-json/myplugin/v1/value' )
                .then( ( r ) => r.json() );
            getContext().count += result.value;
        },
    },
} );
```

Three properties to pin:

- **Actions are plain functions on the
  `actions` object.** No special wrapper, no
  framework decorator. Just functions that do work
  when called.
- **Actions can be sync or async.** An async action
  is a regular JS `async function` (or a function
  returning a Promise). The runtime invokes the
  action when the event fires; if the result is a
  Promise, the runtime does not wait for it before
  returning from the event handler — it runs as JS
  normally would.
- **Actions are looked up by name.** The directive
  value `actions.increment` resolves to
  `store.actions.increment`. Renaming an action
  without updating the directive markup breaks the
  binding.

### C. Action invocation — what runs when the event fires

When a hydrated event fires, the runtime:

```
1. The bound DOM event handler (attached during hydration) fires.
2. The runtime resolves the path string ("actions.increment")
   against the scoped store ("myplugin/counter").
3. If resolution succeeds:
     a. Set up the implicit context for getContext() / getElement().
     b. Invoke action.call( null, event ).
     c. The action's return value (if a Promise) is observed by
        the runtime but not awaited synchronously by the event handler.
4. If resolution fails (no such action), the runtime logs an
   error and the click effectively does nothing user-visible.
```

The action receives the **DOM event** as its first
argument. The `getContext()` and `getElement()`
helpers do *not* take arguments — they look up the
ambient context the runtime set up in step 3a.

```js
actions: {
    handleSubmit( event ) {
        event.preventDefault();
        const formData = new FormData( event.target );
        // ... use formData
    },
},
```

The shape *"action receives event, then queries
context via helpers"* is the canonical pattern. The
event has the standard DOM API (`preventDefault`,
`stopPropagation`, `target`, etc.). The context and
element come from the helpers because they are
*positionally* derived from where the directive sits
in the DOM, not from the action's arguments.

### D. Scoped helpers — `getContext()` and `getElement()`

These are the canonical access surfaces for state
and DOM:

**`getContext()`** returns the nearest
`data-wp-context` object in the DOM ancestry of the
element where the directive is declared. The
returned object is *reactive* — mutating its
properties triggers updates to bound DOM:

```html
<div data-wp-interactive="counter" data-wp-context='{"count": 0}'>
    <p data-wp-text="context.count">0</p>
    <button data-wp-on--click="actions.increment">+</button>
</div>
```

```js
actions: {
    increment() {
        const context = getContext();
        context.count += 1;       // mutation triggers re-render of <p>
    },
},
```

The context is **per scope**. Two sibling blocks
each with their own `data-wp-context` produce two
distinct context objects; an action invoked from
one does not see the other's state.

**`getElement()`** returns an object describing the
element the directive sits on. The most common
shape:

```js
actions: {
    handleClick() {
        const { ref } = getElement();
        ref.focus();
    },
},
```

`ref` is the actual DOM node. Useful for direct DOM
access (focus, scroll, measurements) when reactive
bindings can't express what you need.

Both helpers are **call-site sensitive** — they
only return meaningful values inside an action that
the runtime has invoked. Calling them from arbitrary
code (a `setTimeout`, an external function) outside
the runtime's invocation context returns nothing
useful.

### E. Event-target variants

Three event-binding directives, distinguished by
target:

| Directive                         | Target               |
| --------------------------------- | -------------------- |
| `data-wp-on--{event}`             | The element itself   |
| `data-wp-on-window--{event}`      | `window`             |
| `data-wp-on-document--{event}`    | `document`           |

```html
<div
    data-wp-on-window--resize="actions.handleResize"
    data-wp-on-document--keydown="actions.handleGlobalKey"
>
    …
</div>
```

The window/document variants attach the listener
during hydration and *detach it* when the
declaring element is removed from the DOM. They are
the right shape for cases where the element wants
to listen to global events but should clean up when
unmounted.

The runtime handles attach/detach lifecycle. Direct
`window.addEventListener` calls in actions
short-circuit this — they leak listeners on
unmount. Use the directive variants for global
events whenever possible.

### F. Async actions and side-effect timing

Async actions are first-class:

```js
actions: {
    *load() {
        getContext().isLoading = true;
        try {
            const data = yield fetch( '/wp-json/myplugin/v1/data' )
                .then( ( r ) => r.json() );
            getContext().items = data;
        } finally {
            getContext().isLoading = false;
        }
    },
},
```

The Interactivity API uses **generator functions**
for async actions (similar to how
`@wordpress/data` resolvers do, in shape if not in
internals). The `yield` keyword waits for promises;
between yields, state can be mutated and the runtime
can render intermediate states (a loading spinner,
in this example).

For *plain async function* actions, side effects
fire when the function naturally awaits — but the
runtime does not orchestrate "intermediate render"
moments the way generator-based actions allow. For
trivial fetches, plain `async` is fine; for actions
with multiple visible state transitions, generators
are the recommended shape.

The action runtime is **synchronous up to the first
yield/await**. State mutations before that point
batch into one render; mutations after each
yield/await render at the end of that microtask. The
mental model is "each `yield` is a save point; the
DOM updates between save points."

## WHY

### Why path strings rather than function references

The directive value is a string (`"actions.increment"`)
rather than a JS function reference (which would be
impossible in HTML attributes anyway). Two
consequences:

- **Markup is portable.** The HTML can be cached,
  pre-rendered, edited, or transferred without
  carrying JS state.
- **Lookup is namespace-scoped.** The runtime
  resolves the string against the nearest declared
  namespace. Same path string in two different
  blocks resolves to two different actions if the
  namespaces differ.

The cost is the indirection — a typo in the path
string isn't caught at "compile time" (there is no
compile time). Linting and tests are the
substitutes.

### Why `getContext()` instead of passing context as an argument

The runtime invokes the action with the event as
the first argument. Adding context as a second
argument would have worked, but it would mean
*every* action declaration includes a `(event,
context)` signature whether or not the context is
used.

The helper-based approach lets actions opt in
exactly to what they need:

```js
actions: {
    simpleClick() { /* event ignored, no context */ },
    contextualClick() { const ctx = getContext(); ctx.count++; },
    elementClick() { const { ref } = getElement(); ref.classList.add('hit'); },
},
```

The trade-off: helpers are call-site sensitive (they
must run inside the runtime's invocation), which is
a constraint that arguments wouldn't have. In
practice this constraint is the correct shape — an
action's context *is* its invocation context.

### Why generator functions for async

Async actions need to mutate state across multiple
async boundaries while letting the runtime control
when DOM updates happen. Plain `async function`
gives correctness but no control over update timing.
Generators let the runtime drive the function step
by step, rendering between yields.

This is the same family of motivations that
generator-based resolvers in `@wordpress/data` solve
(documented in `data-layer.resolver-lifecycle`).
Different problem, similar shape.

## WHEN NOT

Skip `data-wp-on--*` if:

- You need a **synchronous mutation that should
  fire on render**, not on user event. Use
  `data-wp-init` (runs once after hydration) or
  `data-wp-watch` (runs whenever its dependencies
  change).
- The interaction is **inside the editor** (not
  the frontend). The editor has its own event
  system; `data-wp-on--*` is part of the
  Interactivity runtime that runs on the public
  frontend.
- You need a **purely visual effect** that
  doesn't change state (`:hover` styles,
  CSS-driven transitions). Use CSS; reactive
  state is overkill.
- The event you need is **non-DOM** (custom
  events from a third-party library). The
  Interactivity API binds DOM events; for custom
  events, write a regular `addEventListener` in
  an `init` callback (with cleanup in a return
  function).

## COUNTER-PATTERNS

### Anti-pattern 1 — Calling `getContext()` outside an action invocation

```js
const ctx = getContext();  // at module top level
store( 'myplugin/counter', {
    actions: {
        increment() { ctx.count += 1; },
    },
} );
```

The top-level `getContext()` call has no invocation
scope. It returns nothing useful. Move the call
*inside* the action so the runtime's
just-set-up scope is in effect.

### Anti-pattern 2 — Trying to pass arguments via the directive value

```html
<button data-wp-on--click="actions.setValue(42)">Set 42</button>
```

The directive value is a path string, not a JS
expression. The above doesn't call `setValue` with
`42` — it tries to look up an action named
`actions.setValue(42)`, which doesn't exist. To
parameterize, store the value in context:

```html
<button
    data-wp-context='{"target": 42}'
    data-wp-on--click="actions.setValue"
>Set 42</button>
```

```js
actions: {
    setValue() {
        const { target } = getContext();
        getContext().count = target;
    },
},
```

### Anti-pattern 3 — Mutating non-reactive references

```js
actions: {
    accumulate() {
        getContext().items.push( 'new' );  // mutation of array — works
        const arr = getContext().items;
        arr = arr.concat( [ 'new' ] );     // reassigns local; doesn't update context
    },
},
```

Mutating properties of context objects (including
`push` on arrays) flows reactively. Reassigning a
local variable does not — it's just JS scoping.
Mutate the context properties directly, or assign
back: `getContext().items = arr.concat( [ 'new' ] )`.

### Anti-pattern 4 — Bypassing the directive with manual event listeners

```js
actions: {
    init() {
        document.querySelector( '#my-button' ).addEventListener( 'click', () => {
            getContext().count += 1;
        } );
    },
},
```

This sets up a listener that the runtime doesn't
know about — it won't be removed when the element
is unmounted, and `getContext()` may not be in
scope when it fires. Use `data-wp-on--click` on the
button; let the runtime own the lifecycle.

### Anti-pattern 5 — Async actions without generator for multi-step state changes

```js
actions: {
    async load() {
        getContext().isLoading = true;       // first DOM render
        const data = await fetch( '...' );    // await
        getContext().items = await data.json();  // second DOM render?
        getContext().isLoading = false;
    },
},
```

Async actions work, but the runtime's update
batching is less predictable than with generators.
For "show loading → load → show data" flows,
generators give explicit save points:

```js
actions: {
    *load() {
        getContext().isLoading = true;
        const data = yield fetch( '...' ).then( ( r ) => r.json() );
        getContext().items = data;
        getContext().isLoading = false;
    },
},
```

### Anti-pattern 6 — Cross-namespace action call without explicit syntax

```html
<button
    data-wp-interactive="myplugin/a"
    data-wp-on--click="actions.handler"
>…</button>
```

…where `handler` is actually defined in the
`myplugin/b` store. The runtime resolves
`actions.handler` against `myplugin/a` and finds
nothing. To call across namespaces, use the
`namespace::action` form:

```html
<button data-wp-on--click="actions.myplugin/b::handler">…</button>
```

Or restructure so the action lives in the namespace
that owns the element.

## OPERATIONAL NOTES

The action runtime's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *binding* form. The action is
  *declared* in `store()`'s `actions` object; the
  binding is *declared* by the
  `data-wp-on--{event}` markup. Neither declaration
  is the same as *executed behavior*. Execution
  requires the event to fire, the path to resolve,
  and the function to run successfully. Naming
  Law 1 here is genuinely clarifying because the
  *gap* between "the binding is hydrated" and "the
  action ran" is exactly what this chunk maps. The
  one-line capture: *declared activation ≠
  executed behavior*.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly*. The namespace + action name persists
  as the identity surface across markup, store
  registration, and execution. Same continuity
  shape as the view-script chunk's namespace
  continuity. Worth one mention; not a section.
- **Federation** appears very lightly: every plugin
  registers its own actions; all federate around
  the single interactivity registry; cross-
  namespace dispatch (`namespace::action`) is the
  explicit federation-aware syntax. Cross-
  reference; not re-elaborated.

What this chunk is **not** about:

- **Doctrine 6 (Authority Mediation).** Event
  dispatch is *not* governance. The directive
  binds an event to an action; the action runs
  when triggered. There is no capability check, no
  access control, no mediation surface. Even the
  "actions are the canonical write channel"
  framing — true at a soft level for
  `wp-data-registry` and `inspector-controls` — is
  weaker here, because nothing prevents code from
  mutating context objects directly outside of
  actions (the reactive system tracks the mutation
  either way). This is *event handling*, not
  *write-channel governance*. Naming Doctrine 6
  here would inflate the mechanism. Omitted.
- **Law 4 (Arbitration Compiler).** No candidate
  selection. Each `data-wp-on--{event}` binds one
  action to one event on one element. Multiple
  directives on the same element bind different
  events; they don't compete. The runtime resolves
  each binding independently. Omitted.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** *Adjacent and explicitly non-fit
  here.* The actions run in the same browser JS
  runtime as the rest of the interactivity code.
  No runtime boundary is crossed during action
  execution. The cross-runtime bridge for
  interactivity (server-rendered state preserved
  into client runtime) lives at the hydration step
  — covered in `interactivity.hydration` — *not*
  in this chunk's action-firing mechanism. The
  boundary the previous chunk deferred to applies
  identically here: this chunk's territory ends at
  "the action ran"; what *the action does to
  shared state* via reactive mutation is the
  reactive update loop's territory.
- **Law 6 (Compiler ↔ Runtime Split).** Action
  execution exists entirely at browser-runtime.
  The build pipeline is upstream; not part of
  this mechanism. Omitted.
- **Section X archetypes.** An event-handler
  directive family is not a "civilization." Same
  framework-omission discipline as the surrounding
  chunks. Omitted.

A small literacy contribution worth pinning:

> *Reactive binding ≠ executed action.* A
> directive that the runtime has hydrated
> (`data-wp-on--click="actions.increment"` is now
> a live event listener) is structurally distinct
> from an action that has run. Several
> preconditions sit between them: the event must
> fire, the path must resolve to a function, and
> the function must complete (or, for generator
> actions, advance through its yield boundaries
> without throwing). The binding is *capacity*;
> the executed action is *event*.

This contribution closes a *4-step ladder* across
the activation arc:

> *One declared interactive capability moves through
> four observable states, each a precondition for
> the next:*
>
> - ***Embedded** — the directive markup exists in
>   HTML (Phase 3 from `view-script-activation`).*
> - ***Activated** — the runtime has wired the
>   binding (Phase 5).*
> - ***Triggered** — the event has fired and
>   resolution succeeded.*
> - ***Executed** — the action has run to
>   completion.*
>
> *Each state is observable; each transition can
> fail. The 4-step ladder is the natural
> diagnostic frame for "the directive isn't
> working" — narrow down which transition the
> failure is on.*

The 4-step ladder is *prose-level literacy*, not a
constitutional rule. It does not appear in
`structural-patterns.md`. It is the natural frame
that emerges from pairing this chunk with
`view-script-activation`.

The contribution also extends the existence-vs-
operation toolkit pinned in the surrounding
chunks (resolver lifecycle / JIT translations /
inspector controls / view-script activation):

- *Existence* — the thing is declared.
- *Operation* — the thing is wired and ready.
- *Behavior* — the thing has actually done.

Three observably distinct states. The
interactivity terrain is where all three live in
the same ladder for one declaration.

## CHECKLIST

When using `data-wp-on--*` and authoring actions:

- [ ] Use `data-wp-on--{event}` for element-scoped
      events; use the `-window` and `-document`
      variants for global events that should
      cleanup when the element unmounts.
- [ ] Pass the event as the action's first
      argument; access scope via `getContext()`
      and `getElement()` inside the action.
- [ ] Don't pass arguments through the directive
      value (it's a path lookup, not an
      expression). Encode parameters in
      `data-wp-context` instead.
- [ ] For multi-step state changes that should
      render between steps, use generator
      (`function*`) actions with `yield`, not
      plain `async function`.
- [ ] When diagnosing "the click does nothing,"
      walk the 4-step ladder: embedded
      (markup present) → activated (hydration
      attached binding) → triggered (event fired
      and path resolved) → executed (action ran
      without error). Each step has distinct
      symptoms.
- [ ] For cross-namespace action calls, use the
      `actions.namespace::name` syntax explicitly
      rather than relying on accidental
      resolution.
- [ ] Don't `addEventListener` directly when a
      directive variant exists; let the runtime
      own listener lifecycle.

## REFERENCES

- Interactivity API reference. Documents `store()`,
  actions, `getContext()`, `getElement()`,
  generator-based async patterns.
  https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/
- Reactive and declarative mindset (core concepts).
  Documents the action shape and intended usage.
  https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/core-concepts/the-reactive-and-declarative-mindset/
- Interactivity API directive reference (GitHub).
  Lists all `data-wp-on*` variants and current
  behavior.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/interactivity/docs/2-api-reference.md
- Interactivity API merge announcement (WP 6.5).
  Establishes the baseline this chunk targets.
  https://make.wordpress.org/core/2024/03/04/interactivity-api-merged-in-6-5/

Cross-context:

- `interactivity.view-script-activation` — the
  activation contract that produces the hydrated
  binding state this chunk's actions rely on.
  Together the two chunks form the activation +
  execution pair.
- `interactivity.directive-protocol` — the broader
  directive grammar. `data-wp-on--*` is one
  family; siblings include `data-wp-bind`,
  `data-wp-text`, `data-wp-class`, `data-wp-context`.
- `interactivity.runtime-state` — the store
  mechanism that owns actions. State / actions /
  callbacks composition lives there; this chunk
  focuses on the actions slice as it is invoked
  by event bindings.
- `interactivity.hydration` — the mechanism that
  wires `data-wp-on--*` markup into actual DOM
  event listeners. Phase 5 of the activation
  sequence; this chunk's actions presuppose it.
