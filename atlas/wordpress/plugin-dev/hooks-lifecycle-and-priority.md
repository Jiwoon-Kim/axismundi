---
rule_id: plugin-dev.hooks-lifecycle-and-priority
domain: plugin-dev
topic: federation-substrate
field_cluster: action-filter-dispatch
wp_min: "2.0"
wp_recommended: "5.0+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/add_action/
    section: "add_action() — registration, priority, accepted_args"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/add_filter/
    section: "add_filter() — registration, priority, accepted_args"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/do_action/
    section: "do_action() — action dispatch"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/apply_filters/
    section: "apply_filters() — filter dispatch with value flow"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/classes/wp_hook/
    section: "WP_Hook class — internal storage and dispatch implementation"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/plugins/hooks/
    section: "Hooks handbook — actions vs filters convention"
    captured: 2026-05-10
related:
  - plugin-dev.security-boundaries                 # capability checks happen inside callbacks; not at hook level
  - editor-customization.editor-hooks              # editor-side hook surface (block-editor specific filters)
  - editor-customization.block-filters             # the JS-side filter family
  - data-layer.wp-data-registry                    # JS-runtime federation comparison
---

# RULE — `add_action` / `add_filter` and the hook priority dispatch substrate

## WHEN

You are writing a plugin that needs to participate in
WordPress's event/transformation flow — registering
callbacks on actions, filters, or both — and need to
reason about *when* your callback fires, *in what
order* relative to other callbacks, and *what happens*
when multiple plugins target the same hook. Use this
knowledge when:

- Choosing a priority for `add_action` /
  `add_filter` and predicting where your callback
  lands in the execution sequence.
- Diagnosing "my filter doesn't seem to be applied"
  — almost always one of: wrong hook name, wrong
  priority relative to other callbacks, callback
  not returning a value (filters), or registration
  happening too late.
- Removing a callback you didn't register
  (`remove_action` / `remove_filter` with another
  plugin's callback as target).
- Reading core code or another plugin's code and
  following which callbacks fire in what order on
  a particular hook.
- Understanding why hooks are not a permission
  system, an arbitration system, or a runtime
  bridge — even though they superficially look
  like one or all of those.

This chunk does **not** cover:

- Specific WordPress core hooks (`init`,
  `wp_loaded`, `the_content`, `template_redirect`,
  etc.). Those are documented in the WordPress
  reference; this chunk covers the *mechanism*
  every hook uses.
- The block-editor JS-side hook system
  (`@wordpress/hooks`). That is a separate
  implementation with the same conceptual shape;
  see `editor-customization.block-filters` and
  `editor-customization.editor-hooks`.
- Capability checks inside callbacks. Those are
  the callback's responsibility (covered in
  `plugin-dev.security-boundaries`); the hook
  mechanism itself does not gate registration or
  invocation by capability.
- Cron / scheduled events that wrap hooks in a
  temporal layer. That is a separate mechanism
  built on top of hooks; deferred until a future
  chunk if needed.

The principle this chunk operates under: **WordPress
hooks are a federated dispatch substrate. Many
participants register callbacks on a shared hook
namespace; when the hook fires, every registered
callback runs, in priority order. The mechanism is
*ordered orchestration*, not *candidate
arbitration*; *federated participation*, not
*centralized authority*.**

## SHAPE

### A. Two families: actions and filters

WordPress's hook system has two function families
with one underlying mechanism:

| Family   | Purpose                                                | Return value flow                                 |
| -------- | ------------------------------------------------------ | ------------------------------------------------- |
| Actions  | Side effects (logging, sending email, mutating globals) | Callbacks return nothing; their returns are ignored |
| Filters  | Value transformation                                    | Each callback receives the previous one's return; the final return is `apply_filters`'s output |

```php
// Actions — fire side effects.
add_action( 'init', 'my_plugin_init' );
function my_plugin_init() {
    // do work
}
do_action( 'init' );  // fires every registered callback in order

// Filters — transform a value through the chain.
add_filter( 'the_content', 'my_plugin_modify_content' );
function my_plugin_modify_content( $content ) {
    return $content . '<p>Appended!</p>';
}
$result = apply_filters( 'the_content', $original_content );
```

Both `add_action` and `add_filter` ultimately
register into the same internal structure
(`$wp_filter` array of `WP_Hook` instances).
`do_action` and `apply_filters` ultimately walk
the same structure. The action vs filter
distinction is **convention** — it tells you whether
to expect a return value to flow through. The
mechanism is one.

### B. Registration mechanics

The full registration signature:

```php
add_action(
    string   $hook,
    callable $callback,
    int      $priority      = 10,
    int      $accepted_args = 1
): true;
```

- **`$hook`** — the hook name (a string). Anything;
  WordPress doesn't validate it. Registering on a
  nonexistent hook is silently fine.
- **`$callback`** — anything PHP `is_callable()`
  accepts: function name, `[$obj, 'method']`,
  `[ClassName::class, 'static_method']`,
  `Closure`, invokable object.
- **`$priority`** — integer or float. Default `10`.
  Lower numbers fire earlier. Negative numbers
  allowed but unusual.
- **`$accepted_args`** — how many arguments the
  callback expects. Default `1`. Important when the
  hook fires with multiple args (the dispatcher
  truncates to this count).

Registration is **eager and unconditional**. The
moment `add_action` returns, the callback is in
the dispatch table. There is no "validation phase,"
no "approval step," no other plugin allowed to
veto.

```php
// Multiple registrations on the same hook with the
// same callback are allowed (they are tracked
// separately under unique keys).
add_action( 'init', 'my_init', 10 );
add_action( 'init', 'my_init', 20 );
// Both registrations exist; my_init fires twice.
```

### C. Priority and execution ordering

`WP_Hook` stores callbacks indexed by priority:

```
WP_Hook
   ├─ priority 1
   │   └─ [ callback_a, callback_b ]   (in registration order)
   ├─ priority 5
   │   └─ [ callback_c ]
   ├─ priority 10  (default)
   │   └─ [ callback_d, callback_e, callback_f ]
   └─ priority 99
       └─ [ callback_g ]
```

Dispatch order (ascending priority, registration
order within priority):

```
callback_a → callback_b → callback_c
   → callback_d → callback_e → callback_f
   → callback_g
```

Three properties to pin:

- **All callbacks fire.** No callback is skipped
  based on what an earlier callback returned. A
  filter callback can return the same value
  unchanged; the chain still runs every other
  registered callback. There is no "first to
  return non-null wins" semantics.
- **Same-priority registrations fire in
  insertion order.** If plugin A and plugin B both
  register on `init` at priority 10, they fire in
  the order they were `add_action`'d. This is
  *not* alphabetical, *not* by plugin name; just
  the order PHP saw the calls.
- **Priority is hint, not authority.** A callback
  cannot "lock out" a later callback by setting a
  high priority. The later callback (if it
  registered with higher priority number) still
  fires after; it sees whatever value the earlier
  callbacks produced.

The execution sequence is **deterministic given
the registration set**. Adding a callback at
priority `5` doesn't redirect execution; it
inserts a new step before the priority-10 group.

### D. Dispatch mechanics

**`do_action($hook, ...$args)`**:

```
1. Look up WP_Hook for $hook (return early if no callbacks).
2. For each priority (ascending):
     For each callback at this priority (insertion order):
        Invoke callback($args, truncated to its accepted_args).
3. Return; the action call returns nothing useful.
```

Side effects accumulate; nothing flows back to
the caller of `do_action`.

**`apply_filters($hook, $value, ...$args)`**:

```
1. Look up WP_Hook for $hook (return $value unchanged if no callbacks).
2. current = $value
3. For each priority (ascending):
     For each callback at this priority (insertion order):
        next = callback(current, $args[0..accepted_args-2])
        current = next
4. Return current.
```

The filter chain composes: each callback's return
becomes the next's input. A callback that returns
nothing (or `null`) breaks the chain — the next
callback receives `null`, not the previous value.
This is the most common filter authoring bug.

Both dispatchers are **synchronous**. The hook
fires, every callback runs to completion, then
control returns to the dispatcher's caller.

### E. Removal and introspection

**Removal:**

```php
remove_action( 'init', 'my_init', 10 );
remove_filter( 'the_content', [ $obj, 'method' ], 10 );
```

The removal call must match the original
registration's `$hook`, `$callback`, and
`$priority` exactly. Mismatched priority means
the removal silently fails. Closures are notoriously
hard to remove because the original reference must
be preserved:

```php
add_action( 'init', function() { /* … */ } );
// Cannot remove this — no reference to the closure exists.
```

**Introspection:**

| Function                              | Returns                                              |
| ------------------------------------- | ---------------------------------------------------- |
| `has_action($hook, $callback)`        | `false`, or the priority the callback is at         |
| `has_filter($hook, $callback)`        | Same shape                                          |
| `did_action($hook)`                   | Number of times the action has been fired this request |
| `current_action()` / `current_filter()` | The hook currently being dispatched (during callback) |
| `doing_action($hook)` / `doing_filter($hook)` | Boolean — is this hook currently dispatching? |

These are useful for diagnostics ("did this hook
fire yet?") and for callbacks that need to know
their dispatch context ("am I being called from
inside a different hook?").

### F. Federation — one hook namespace, many participants

The hook namespace is **shared globally**. Every
plugin, every theme, every part of WordPress core
sees the same `$wp_filter` array. Many participants
can register on the same hook simultaneously.

Concretely, on a typical `init` action:

```
Hook 'init' has registered callbacks from:
   - WordPress core (rewrite rules, block registration, …)
   - Theme (text domain loading, image sizes, …)
   - Plugin A (post type registration, …)
   - Plugin B (taxonomy registration, …)
   - Plugin C (cron schedule registration, …)
   - …

When do_action('init') fires:
   ALL of the above run, in priority order,
   each independently making its contributions.
```

Three properties of this federation:

- **No plugin "owns" a hook.** Even hooks defined
  by core (`do_action('init')` is in core) are not
  core's exclusive turf. Any plugin can register
  on `init`; core has no say.
- **No registration is rejected.** The dispatch
  table accepts every registration. There is no
  capability check, no namespace ownership, no
  permission system at the hook level.
- **Each callback is independent.** What plugin A
  does in its `init` callback does not constrain
  plugin B's `init` callback. They share the same
  PHP execution context (same globals, same
  request state) but their callbacks are
  independent code.

This is the **archetypal federation pattern** that
recurs throughout the KB:

- Plugin federation around shared hook dispatch
  (this chunk).
- JS package federation around `window.wp.*`
  externals (`build-tooling.wp-scripts`).
- Store federation around the
  `@wordpress/data` registry
  (`data-layer.wp-data-registry`).
- Block extension fills federating around shared
  Slots (`editor-customization.inspector-controls`,
  `editor-customization.block-controls`).

The shape recurs because the design problem
recurs: many independent contributors need a
coordinated way to participate in a shared
substrate without explicit cross-coordination.
WordPress hooks are the canonical PHP-side
expression of this pattern.

## WHY

### Why one shared namespace rather than per-plugin namespaces

Per-plugin namespaces would mean a plugin can only
react to its own events. The whole value of hooks
is that core events (a post being saved, a comment
posted, a page rendered) trigger reactions from
*any plugin or theme that wants to participate*.
Per-plugin isolation would make this impossible.

The cost is name collision risk (two plugins
registering on `init` is fine; two plugins
defining unrelated actions named `do_my_thing` is
collision-prone). The convention solution is
namespace prefixes: `myplugin/do_thing` instead
of `do_my_thing`.

### Why all callbacks fire rather than first-match-wins

First-match-wins (or any single-winner pattern)
would mean later-priority callbacks could be
shadowed by earlier ones. The federation property
breaks: plugin A's logging callback could mute
plugin B's content modification.

All-fire semantics let every participant
contribute independently. The cost — a hook with
many slow callbacks gets slow — is bounded by how
many callbacks register; it's not a structural
penalty of the mechanism.

### Why priority is integer-shaped rather than dependency-shaped

A priority number is simple, deterministic,
inspectable. A dependency-graph mechanism ("this
callback must fire after callback X") would be
more expressive but vastly more complex: cycle
detection, missing-dependency handling, refused
registrations. Integer priorities are coarse but
predictable; plugins coordinate by convention
(default `10`; lower numbers for "must run early";
higher numbers for "must run late").

### Why filters require return values

A filter that doesn't return a value (or returns
`null`) breaks the chain — the next callback
receives `null`, not the previous filtered value.
This is a feature, not a bug: it forces filter
authors to be explicit about composition. A
filter that wants to "do nothing" still must
return the value unchanged. The discipline is
load-bearing for chain composition.

The cost is that "I forgot to return the value"
is the dominant filter bug. The benefit is that
the chain semantics are unambiguous: every filter
contributes a transformation; the final value is
the composition of all of them.

## WHEN NOT

Skip the hook system if:

- You need **synchronous communication between
  specific code units** (caller and known callee).
  Just call the function directly. Hooks introduce
  registration overhead and dispatch indirection
  that direct calls don't need.
- You need **guaranteed ordering between specific
  callbacks**. Priority is a coarse hint; if you
  need "this must run after that," you may be
  fighting the design. Restructure into direct
  function calls or split the work across
  different hooks.
- You need **single-receiver semantics**. Hooks
  always invoke every callback. If only one
  receiver should handle an event, model it as
  a method call or a registry lookup, not a hook.
- You are working **inside the editor JS runtime**.
  PHP `add_action`/`add_filter` is PHP-only. The
  JS-side hook system (`@wordpress/hooks`) has
  the same shape but is a separate
  implementation; covered in
  `editor-customization.block-filters`.

## COUNTER-PATTERNS

### Anti-pattern 1 — Filter callback that doesn't return

```php
add_filter( 'the_content', function( $content ) {
    do_something_with( $content );
    // Forgot to return.
} );
// Next filter in chain receives null. The_content becomes null.
// Subsequent filters may break, output may vanish.
```

Always `return` from filters. If you need to do
side effects without modifying the value:

```php
add_filter( 'the_content', function( $content ) {
    do_something_with( $content );
    return $content;
} );
```

Or use an action instead — actions are for side
effects.

### Anti-pattern 2 — Anonymous closure that needs removal

```php
add_action( 'init', function() {
    // ... a one-off
} );

// Later, want to remove it. No reference exists.
remove_action( 'init', '???' );  // can't be done.
```

If a callback may need removal, use a named
function or a callable that retains a stable
reference:

```php
$callback = function() { /* … */ };
add_action( 'init', $callback );
// Later:
remove_action( 'init', $callback );
```

### Anti-pattern 3 — Treating priority as authority

```php
add_filter( 'the_content', 'my_filter', 99999 );
// Hoping nothing else can run after this.
```

Another plugin can register at priority `100000`,
or any number larger than 99999. Priority is a
hint, not an exclusion. If you need "always last,"
you are fighting the design; reconsider the
architecture.

### Anti-pattern 4 — Registering during dispatch in ways that affect current dispatch

```php
add_action( 'init', function() {
    add_action( 'init', 'my_late_callback', 99 );  // mid-init
} );
```

The newly added callback may or may not fire
during the current dispatch (depends on whether
`init`'s priority loop has reached priority 99
yet). The behavior is implementation-defined and
subtly different across WP versions. Register
callbacks at predictable times (typically during
plugin load), not from inside other callbacks.

### Anti-pattern 5 — Wrong `accepted_args` count

```php
// Hook fires with 3 args:
do_action( 'my_hook', $a, $b, $c );

// Callback registered with default accepted_args = 1:
add_action( 'my_hook', function( $a ) {
    // $b and $c are unavailable.
} );
```

If your callback needs more args, declare them in
both the function signature and the
`accepted_args` parameter:

```php
add_action( 'my_hook', function( $a, $b, $c ) {
    // …
}, 10, 3 );
```

Otherwise, PHP truncates to `accepted_args` and
the missing parameters are unset.

### Anti-pattern 6 — Removing callbacks you don't own

```php
// Plugin B trying to neutralize plugin A's hook:
remove_action( 'init', 'plugin_a_init', 10 );
```

This works if you're certain about plugin A's
function name and priority. But if plugin A's
implementation changes (renames the function,
shifts to a class method, changes the priority),
your removal silently fails. Coordinate with
plugin A or use a more stable contract (a
filter plugin A intentionally exposes for
modification).

## OPERATIONAL NOTES

The hook substrate's interpretive shape, in
proportional v2 vocabulary:

- **Federation** is the **central fit**. The hook
  namespace is the shared registry; every plugin /
  theme / core component is an equal participant;
  registration is open; dispatch invokes every
  registration. This is the archetypal federation
  pattern in its PHP-runtime expression — the
  same shape that recurs in `wp-data-registry`,
  `wp-scripts`'s externals, the SlotFill family,
  and the `WP_Block_Metadata_Registry`. Naming
  Federation here is genuinely clarifying because
  the hook system *is* the canonical PHP-runtime
  federation example; calling it federation
  anchors the pattern's meaning across the KB.
- **Law 1 (Declaration ≠ Exposure)** is a
  **PRIMARY** fit (alongside Federation, in a
  *registered → fired* form). The callback is
  *declared* via `add_action` / `add_filter`; it
  is *exposed* (executed) only when `do_action` /
  `apply_filters` is invoked for the matching
  hook. The gap between registration and firing
  is real and observable: a callback registered
  on a hook that is never fired never runs.
  Naming Law 1 here is genuinely clarifying
  because the *gap* between "the callback is in
  the dispatch table" and "the callback has run"
  is one of the substrate's most diagnostic
  surfaces.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly*. The hook name string is the
  continuity surface across registration,
  storage, and dispatch. Same hook string in
  different contexts refers to the same
  participation point. Worth one mention; not a
  section.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *The
  highest-risk non-fit to name precisely* in this
  terrain. Hook priority *strongly resembles* an
  arbitration ladder — ordered numbers,
  multiple participants, sequence matters. But
  the mechanism is not arbitration:
  - **All callbacks fire.** Priority orders them;
    it does not select among them. No callback
    is discarded based on what an earlier
    callback did.
  - **Nobody "wins."** A higher-priority
    callback runs first; that doesn't make it
    "win." Lower-priority callbacks still run
    after. There is no winner.
  - **The sequence is the output**, not a
    selection from candidates. Every priority
    level contributes to the final state (for
    actions) or the final value (for filters).
  Naming Law 4 here would conflate *ordered
  orchestration* with *candidate selection*. The
  phrasing worth pinning: *hook priority ≠
  candidate arbitration; priority is execution
  ordering, not selection from alternatives*.
- **Doctrine 6 (Authority Mediation).** *Explicit
  non-fit.* The hook system has no concept of
  "who is allowed to register." Plugin A can
  register on hooks defined by plugin B; nothing
  enforces ownership or permission. Capability
  checks happen *inside callbacks* (the callback
  decides what to do based on permissions); the
  *hook mechanism itself* mediates nothing. The
  pattern often paired with hooks (capability-
  checked admin actions, nonce-verified bulk
  operations) is a *combination* of hooks and
  separate authorization machinery, not a
  property of hooks themselves. Naming Doctrine 6
  here would conflate the substrate with the
  capability layer that often runs *atop* it.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** All dispatch happens in a single
  PHP runtime per request. No cross-runtime
  authority preservation. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** The hook
  system exists entirely at request runtime. No
  build / runtime split inherent. Omitted.
- **Section X archetypes.** A federated dispatch
  substrate is not a "civilization." Same
  framework-omission discipline as the
  surrounding chunks. Omitted.

Two literacy contributions worth pinning:

> *Hook priority ≠ candidate arbitration.* A
> mechanism that orders multiple callbacks and
> invokes them in sequence is not the same shape
> as a mechanism that selects one callback from
> alternatives. Both involve order; only the
> second discards. Hook priority orders for
> *composition* (every callback contributes);
> arbitration orders for *selection* (one
> callback wins). Different shapes; different
> meanings; different consequences for what code
> runs when.

This contribution adds a sixth distinct example to
the anti-Law-4 inventory:

- *Need fulfillment ≠ option arbitration*
  (resolver lifecycle).
- *Availability ≠ activation*'s implicit anti-Law-4
  (JIT translations).
- *Formula-driven selection ≠ candidate
  arbitration* (plural forms).
- *Operator-selected ordering ≠ candidate
  arbitration* (list tables).
- *Layer precedence ≠ candidate arbitration*
  (theme.json source layering).
- *Hook priority ≠ candidate arbitration*
  (this chunk).

The inventory keeps growing as different
mechanisms wear arbitration's surface vocabulary
without sharing its mechanism. The pattern
across all six: surface vocabulary about
"ordering," "selection," "priority," "precedence"
tempts a Law 4 reading; underlying mechanism in
each case is something else (cache, formula,
parameterization, merge, composition).

> *Registered callback ≠ fired callback.* A
> callback in the dispatch table is not the same
> as a callback that has run. The gap is the
> hook firing — and a hook that doesn't fire
> never invokes its callbacks, no matter how many
> are registered. The gap is also the diagnostic
> surface: "is this callback registered? is the
> hook firing? is the priority right?" — distinct
> questions, distinct failure modes.

This contribution extends the existence-vs-
operation toolkit into a *registered → fired*
form, parallel to the existing entries:

- *Embedded capability ≠ activated behavior*
  (interactivity directives).
- *Reactive binding ≠ executed action*
  (data-wp-on actions).
- *Availability ≠ activation* (JIT translations).
- *Registered surface ≠ reachable surface*
  (list tables).
- *Registered callback ≠ fired callback* (this
  chunk).

The pattern: *something is in place, but the
trigger / event / dispatch hasn't arrived to
make it happen yet*. Different mechanisms; same
shape of distinction.

A small additional observation worth pinning,
on the Federation side:

> *Shared substrate ≠ centralized authority.* A
> registry that accepts every registration from
> every participant is not a body that
> *governs* those registrations. The hook
> system holds; it does not authorize. The
> distinction matters because "everyone
> federates here" sounds like a coordination
> point that decides things. The hook system
> decides nothing — it dispatches.

This applies more broadly to the federation
pattern across the KB: federated registries
*hold* contributions; they do not *adjudicate*
them.

## CHECKLIST

When using `add_action` / `add_filter`:

- [ ] Use namespace-prefixed hook names for
      hooks you define (`myplugin/event` rather
      than `event`). Reduces collision risk.
- [ ] Default to priority `10` unless you have
      a specific ordering reason. Predictability
      beats cleverness.
- [ ] If your callback needs more than one
      argument, set `accepted_args` to match
      the function signature.
- [ ] Filter callbacks must always return a
      value. If you only want side effects,
      use an action.
- [ ] If a callback may need removal later,
      use a named function or a stored
      callable; avoid anonymous closures for
      removable callbacks.
- [ ] When removing a callback, match `$hook`,
      `$callback`, and `$priority` exactly.
      Mismatched priority is a silent failure.
- [ ] Don't rely on priority for ordering
      between specific plugins' callbacks; you
      can't predict their values.
- [ ] Don't assume capability checks. The hook
      system doesn't enforce them; your
      callback must do them itself if needed.

## REFERENCES

- `add_action()` reference. Documents the
  registration signature, accepted_args
  semantics, and return value.
  https://developer.wordpress.org/reference/functions/add_action/
- `add_filter()` reference. Same registration
  surface; documents filter conventions.
  https://developer.wordpress.org/reference/functions/add_filter/
- `do_action()` reference. The action
  dispatcher.
  https://developer.wordpress.org/reference/functions/do_action/
- `apply_filters()` reference. The filter
  dispatcher; documents the value-flow chain.
  https://developer.wordpress.org/reference/functions/apply_filters/
- `WP_Hook` class reference. The internal class
  that implements per-hook storage and
  dispatch.
  https://developer.wordpress.org/reference/classes/wp_hook/
- WordPress plugin handbook — Hooks chapter.
  Documents the actions vs filters convention
  and surveys common hooks.
  https://developer.wordpress.org/plugins/hooks/

Cross-context:

- `plugin-dev.security-boundaries` — capability
  checks happen inside callbacks. The hook
  mechanism doesn't gate registration or
  invocation; the callback's body is where
  authorization lives.
- `editor-customization.editor-hooks` — the
  editor-side filter family for block-editor
  customization (PHP-side).
- `editor-customization.block-filters` — the
  JS-side hook system (`@wordpress/hooks`).
  Same conceptual shape, separate
  implementation; the cross-comparison is in
  that chunk.
- `data-layer.wp-data-registry` — the
  JS-runtime federation parallel. The hook
  system here is the PHP-runtime canonical
  example of the same pattern.
