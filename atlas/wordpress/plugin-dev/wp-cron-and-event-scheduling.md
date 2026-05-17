---
rule_id: plugin-dev.wp-cron-and-event-scheduling
domain: plugin-dev
topic: temporal-orchestration
field_cluster: deferred-event-dispatch
wp_min: "2.1"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/plugins/cron/
    section: "Plugin handbook — Cron API overview, traffic-driven model"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_schedule_event/
    section: "wp_schedule_event() — recurring event registration"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_schedule_single_event/
    section: "wp_schedule_single_event() — one-time event registration"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_cron/
    section: "wp_cron() — the tick that fires due events"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/spawn_cron/
    section: "spawn_cron() — async HTTP self-trigger to wp-cron.php"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/cron_schedules/
    section: "cron_schedules filter — custom recurrence definitions"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/apis/wp-cron/
    section: "WP-Cron API reference — DISABLE_WP_CRON, external triggering"
    captured: 2026-05-10
related:
  - plugin-dev.hooks-lifecycle-and-priority      # the substrate cron events ultimately fire on
  - plugin-dev.security-boundaries               # capability checks must happen inside cron callbacks
  - data-layer.persistence                       # wp_options storage substrate (cron events live in 'cron' option)
---

# RULE — WP-Cron — temporal orchestration through traffic-driven event dispatch

## WHEN

You need to reason about *when* a deferred or
recurring task in WordPress actually runs — what
schedules it, what triggers the tick that picks it
up, and why a scheduled event might not fire when
the timestamp suggested it would. Use this knowledge
when:

- Scheduling a recurring task (clean up old
  records nightly, fetch data hourly, send a
  weekly digest).
- Scheduling a one-time deferred task (email
  reminder N seconds after action, cleanup after
  a delay).
- Diagnosing "my cron event didn't fire" —
  almost always one of: no traffic, lock
  contention, `DISABLE_WP_CRON` without an
  external trigger, or callback errors.
- Choosing between WordPress's built-in cron and
  a system cron (cron job on the server hitting
  wp-cron.php on a schedule).
- Reading WordPress core's cron handling and
  following the schedule → tick → fire chain.

This chunk does **not** cover:

- The hook system that scheduled events
  ultimately fire on — covered in
  `plugin-dev.hooks-lifecycle-and-priority`. WP-Cron
  registers a *future invocation of `do_action`*;
  the action's dispatch semantics are that
  chunk's territory.
- Capability checks inside cron callbacks. The
  cron mechanism itself doesn't gate by
  permission; the callback's body must do its
  own checks if relevant.
- The Action Scheduler library (used by
  WooCommerce and others as a more robust cron
  alternative). That is a separate package built
  on top of cron, not cron itself.

The principle this chunk operates under: **A
scheduled event is not an event that has fired. It
is an entry in a queue that fires *if and when* a
cron tick reaches it. The mechanism is **temporal
intent + traffic-driven dispatch**, not guaranteed
execution at a wall-clock time.**

## SHAPE

### A. The scheduling functions

Two registration calls cover almost all use cases:

```php
// Recurring:
wp_schedule_event(
    int    $timestamp,                 // first occurrence (Unix)
    string $recurrence,                // schedule slug (hourly, daily, …)
    string $hook,                      // action hook name to fire
    array  $args = [],                 // args passed to the action
    bool   $wp_error = false           // 5.7+ — return WP_Error on failure
): bool|WP_Error;

// One-time:
wp_schedule_single_event(
    int    $timestamp,
    string $hook,
    array  $args = [],
    bool   $wp_error = false
): bool|WP_Error;
```

Three properties to pin:

- **The hook is just an action.** When the event
  fires, WordPress calls `do_action($hook,
  ...$args)`. Anything that can listen to a hook
  (covered in
  `plugin-dev.hooks-lifecycle-and-priority`) can
  respond to a cron event. There is no special
  "cron callback" type.
- **`$args` is part of the event identity.** Two
  events for the same `$hook` with different
  `$args` are independent. To target a specific
  recurring event for unscheduling, the
  `$args` must match exactly.
- **Recurrence is by named slug.** `'hourly'`,
  `'twicedaily'`, `'daily'` are core defaults.
  `'weekly'` was added in WP 5.4. Custom
  recurrences are added via the
  `cron_schedules` filter (Section E).

Removal calls:

```php
wp_unschedule_event( int $timestamp, string $hook, array $args = [] );
wp_clear_scheduled_hook( string $hook, array $args = [] );  // all events for hook+args
wp_unschedule_hook( string $hook );                          // all events for hook (any args)
```

Query calls:

```php
wp_next_scheduled( string $hook, array $args = [] );        // next timestamp, or false
wp_get_scheduled_event( string $hook, array $args = [], int $timestamp = null );
```

### B. The cron storage layer

All scheduled events live in a single WordPress
option named `cron`:

```
$cron_option = get_option( 'cron' );

[
    1700000000 => [
        'my_hook'         => [
            md5(serialize([])) => [
                'schedule' => 'daily',
                'args'     => [],
                'interval' => 86400,
            ],
        ],
        'other_plugin_hook' => [ … ],
    ],
    1700003600 => [
        'hourly_task'     => [ … ],
    ],
    'version' => 2,
]
```

Three observations:

- **Single option, all plugins.** Every
  registered event from every plugin / theme /
  core lives in this one option. This is the
  *cron federation* surface — many participants,
  one shared queue.
- **Indexed by timestamp first, then by hook
  with args-hash.** This is what makes
  "next-due" lookup cheap (the timestamps are
  the array keys, naturally sortable).
- **Stored as a serialized PHP array.** The
  option is updated on every `wp_schedule_event`
  / `wp_unschedule_event`. Heavy churn (many
  schedules / unschedules per request) can become
  visible.

### C. The tick — what makes due events actually fire

WordPress doesn't have a background process. Cron
events fire only when something *triggers a cron
tick*. The triggers, in order of typical
prevalence:

```
Trigger 1 — Front-end page load
   On every public page load, after WordPress
   initializes, wp_cron() is called (via spawn_cron()
   making an async HTTP request to wp-cron.php).
   The page rendering does not wait for cron.

Trigger 2 — Admin page load
   Same as above for admin pages.

Trigger 3 — Direct external request
   Any HTTP request to /wp-cron.php (typically from
   a system cron job hitting it on a schedule). This
   is the canonical pattern for sites with low
   traffic.

Trigger 4 — DISABLE_WP_CRON + external cron
   Setting `define('DISABLE_WP_CRON', true);` in
   wp-config.php turns OFF triggers 1 and 2. The
   site then relies entirely on Trigger 3 (the
   external trigger).
```

When a tick fires (regardless of which trigger):

```
1. Acquire the doing_cron lock (transient, 60s expiry).
2. Load the cron option.
3. For each timestamp ≤ now (in ascending order):
     For each hook at this timestamp:
        Reschedule (if recurring) → modify cron option for next occurrence.
        Unschedule (if single)    → remove this event.
        Fire do_action( $hook, ...$args ).
4. Release the lock.
5. Return.
```

Step 3's reschedule-or-unschedule happens **before**
the action fires. This means a callback that throws
or times out doesn't leave the event stuck for the
next tick — it has already been moved forward
(recurring) or removed (single).

The tick is **synchronous within the request that
fires it**. The async-spawn from triggers 1/2 means
the *triggering page* doesn't wait for cron, but
the wp-cron.php request itself runs all due events
in sequence.

### D. The lock mechanism

`spawn_cron()` checks a transient named
`doing_cron`. If set, the spawn aborts (preventing
overlapping cron runs):

```
1. Check transient doing_cron.
2. If set and ≤ 60s old: skip (another cron is running).
3. If unset or stale: set transient with current timestamp; spawn.
```

The 60-second window:

- Prevents concurrent cron from two simultaneous
  page loads.
- Means a cron run that takes longer than 60s
  releases the lock prematurely; a second
  spawn could overlap.
- Means a crashed cron run leaves the lock
  set for up to 60 seconds, blocking new
  triggers in that window.

The lock is **best-effort**, not transactional.
For workloads that absolutely cannot overlap,
implement explicit locking inside callbacks.

### E. Custom recurrences via `cron_schedules`

To use a recurrence other than the built-in slugs:

```php
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['every_15_min'] = array(
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => __( 'Every 15 Minutes', 'my-plugin' ),
    );
    return $schedules;
} );

wp_schedule_event( time(), 'every_15_min', 'my_15min_hook' );
```

Two important properties:

- **The filter must be registered before
  scheduling.** If `wp_schedule_event` is called
  with an unknown slug, it fails. Register the
  filter on `init` (or earlier) and schedule on
  the same or later hook.
- **Removing a custom recurrence doesn't unschedule
  existing events.** They remain in the cron option
  with the now-unknown slug. Subsequent ticks may
  fail to reschedule them (depending on core
  version's defensive handling). Always
  unschedule events before removing the
  recurrence definition.

### F. The 4-stage temporal ladder

A scheduled event moves through four observable
states, each a precondition for the next:

```
Stage 1 — SCHEDULED
   wp_schedule_event() succeeded; event is in the cron option.
   wp_next_scheduled() returns the timestamp.
              │
              ▼ (wall-clock time progresses to ≥ timestamp)
Stage 2 — ELIGIBLE
   The event's timestamp is now ≤ current time.
   The next cron tick will pick it up — *if* a tick fires.
              │
              ▼ (a cron trigger occurs: traffic, admin load, external request)
Stage 3 — TRIGGERED
   The cron tick has reached this event during dispatch.
   The lock has been acquired; reschedule/unschedule has run.
              │
              ▼ (do_action fires the hook)
Stage 4 — EXECUTED
   The action's registered callbacks have run.
   Any errors during execution have surfaced.
```

Each transition has a distinct failure mode:

- **Scheduled → Eligible:** waits for time;
  always happens.
- **Eligible → Triggered:** depends on cron
  triggering. No traffic = no tick = no
  trigger. Lock contention = trigger skipped.
  `DISABLE_WP_CRON` without external trigger =
  trigger never happens.
- **Triggered → Executed:** PHP error in
  callback, timeout, server restart mid-run.
  Other callbacks at the same priority may not
  fire if a prior callback fatals.

The pattern parallels the interactivity 4-step
ladder (Embedded → Activated → Triggered →
Executed) but in *temporal* terrain: there, the
states span *one rendered request*; here, they
span *time across many requests*.

The pair-up:

> **Interactivity ladder** — one declared interactive
> capability moves through embedded → activated →
> triggered → executed within a single page
> rendering session.
>
> **WP-Cron ladder** — one scheduled future event
> moves through scheduled → eligible → triggered
> → executed across time, dependent on traffic
> patterns and tick infrastructure.

Same shape; different temporal scope; same
diagnostic frame.

## WHY

### Why traffic-driven rather than real cron

WordPress cannot assume the hosting environment
provides background processes. A typical PHP-on-
shared-hosting WordPress install has only the
PHP-handling-an-incoming-request runtime
available. There is no daemon to wake up at
3:00 AM and run scheduled tasks.

The traffic-driven model uses incoming requests as
"ticks": each visit becomes an opportunity to run
overdue tasks. The cost — sites with no traffic
have no ticks — is tolerable for the typical case
(any site with regular visitors gets cron-like
behavior); the benefit is that WP-Cron works on
literally any PHP host.

For sites where reliable timing matters
(membership renewal billing, scheduled email at
exact times), the canonical solution is to set
`DISABLE_WP_CRON` and configure a system cron job
to hit `wp-cron.php` on a fixed schedule. WordPress
provides the mechanism; the operator chooses the
trigger source.

### Why a single shared `cron` option

The federation property: every plugin's events
live in the same option. Reasons this works:

- Cheap query: "what's due now?" is a single
  option read plus a numeric comparison on the
  array's top-level keys.
- Single update point: schedule / unschedule
  modifies one option; no cross-table
  coordination needed.
- Predictable storage: every plugin can introspect
  `get_option('cron')` to see what's scheduled
  globally.

The cost is contention: many plugins scheduling
events simultaneously means many writes to one
option. For typical workloads this is fine; for
extreme cases (high-frequency event scheduling),
Action Scheduler exists as an alternative with
per-event row-level storage.

### Why the lock allows overlapping after 60s

A perfect lock would require distributed-system
guarantees WordPress can't make on shared
hosting. The 60-second window is a pragmatic
compromise:

- Most cron runs complete well within 60s.
- A run that exceeds 60s probably indicates a
  callback bug; allowing a second spawn helps
  catch up on missed schedules.
- The window is short enough that two
  simultaneously-spawned cron runs are unlikely.

Long-running tasks should explicitly chunk their
work or implement their own locking. The 60s
window is a *baseline guard*, not an absolute
prohibition on overlap.

### Why the reschedule happens before the action fires

If reschedule happened after, a fatal error in the
callback would leave the event stuck (still due,
no rescheduling) — the next tick would re-fire it
and re-fatal. Pre-firing reschedule means the
event "moves on" regardless of what happens in the
callback. The cost — a recurring event whose
callback always fails still gets rescheduled
forever — is fixable by the operator removing the
event explicitly. The benefit — broken callbacks
don't trap the cron system — is structural.

## WHEN NOT

Skip WP-Cron if:

- You need **guaranteed timing** (run at exactly
  3:00 AM, not "after the next visit after
  3:00 AM"). Use a system cron job with
  `DISABLE_WP_CRON`, or use Action Scheduler
  for more robust queuing.
- You need **high-frequency tasks** (more than
  every few minutes). The traffic-driven model
  doesn't fit; system cron with appropriate
  granularity does.
- You need **immediate background execution**
  ("fire this now but don't block the request").
  WP-Cron's "now" is "next tick"; a single
  event scheduled at `time()` runs on the next
  tick, which may be the same request (via
  `spawn_cron`) but isn't guaranteed.
- The work is **request-scoped** (clean up after
  this specific user's request completes). Use
  a regular hook callback or PHP shutdown
  function, not cron.
- You need **transactional semantics** (this
  job must run exactly once; no duplicates).
  WP-Cron's lock is best-effort; for strict
  exactly-once, use a queue with row-level
  locking.

## COUNTER-PATTERNS

### Anti-pattern 1 — Assuming exact timing

```php
// Schedule for 2:00 PM:
wp_schedule_single_event( strtotime( '2pm' ), 'send_lunch_email' );
// Hopes email goes out at 2:00 PM exactly.
```

The event becomes *eligible* at 2:00 PM. Whether
it *fires* depends on traffic between 2:00 PM and
the next visit. On a sparse-traffic site it might
fire at 2:47 PM, or 4:00 PM, or never (if no
traffic until tomorrow).

For exact timing, use a system cron job hitting
wp-cron.php every minute, or use Action
Scheduler.

### Anti-pattern 2 — Scheduling without checking for existing events

```php
// On every plugin load:
add_action( 'init', function() {
    wp_schedule_event( time(), 'hourly', 'my_cleanup' );
} );
```

`wp_schedule_event` doesn't deduplicate. Every
admin page load schedules another instance. The
cron option fills with redundant events all
firing at slightly-different timestamps.

Always check first:

```php
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'my_cleanup' ) ) {
        wp_schedule_event( time(), 'hourly', 'my_cleanup' );
    }
} );
```

### Anti-pattern 3 — Forgetting to unschedule on plugin deactivation

```php
// Plugin is deactivated. Cleanup hook is removed
// because the plugin's PHP isn't loaded.
// But the event is still in the cron option.
// Next tick: do_action( 'my_cleanup' ) fires;
// no callback registered; nothing happens; but
// the event still exists in the option.
```

Plugin deactivation should clear the plugin's
scheduled events:

```php
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'my_cleanup' );
} );
```

Otherwise the cron option accumulates orphaned
events across plugin install/uninstall cycles.

### Anti-pattern 4 — Long-running callbacks that exceed PHP timeout

```php
add_action( 'my_cleanup', function() {
    foreach ( $millions_of_records as $record ) {
        delete_record( $record );
    }
} );
```

PHP's `max_execution_time` will kill the request
mid-loop. The cron run terminates incomplete; the
event was already rescheduled (Section C step 3),
so the next tick fires again and may also fail.

Chunk the work — process N records per
invocation, schedule another single event for the
remainder:

```php
add_action( 'my_cleanup', function() {
    $batch = get_next_batch( 100 );
    foreach ( $batch as $record ) {
        delete_record( $record );
    }
    if ( has_more() ) {
        wp_schedule_single_event( time() + 30, 'my_cleanup' );
    }
} );
```

### Anti-pattern 5 — Treating `time()` and stored timestamps as the same time zone

```php
wp_schedule_event( strtotime( '2024-12-25 09:00' ), 'daily', 'holiday_task' );
```

`strtotime` uses PHP's default timezone (often
UTC); `wp_schedule_event` stores Unix timestamps
(timezone-agnostic). The "9:00 AM" the operator
intended may resolve to a different local time
than expected.

Use `wp_date( 'U', strtotime(...) )` or be
explicit about timezone handling. Cron timing in
non-UTC display contexts is an easy bug source.

### Anti-pattern 6 — Filtering `cron_schedules` after a slug is already in use

```php
// Initial install schedules with custom slug.
// Later, a code change removes the cron_schedules filter.
// The cron option still references 'every_15_min'.
// Rescheduling on the next tick fails (slug unknown).
```

If a custom recurrence slug is removed, unschedule
events using it before removing the slug
definition. Otherwise the option accumulates
unrunnable events.

### Anti-pattern 7 — Treating cron callbacks as authenticated

```php
add_action( 'my_cron_task', function() {
    if ( current_user_can( 'manage_options' ) ) {
        do_admin_thing();
    }
} );
```

Cron callbacks run in a context where there is no
"current user." `current_user_can()` returns
false. If the callback needs admin authority,
either:

- Don't gate by capability (cron is internal;
  you control what it can do).
- Use `wp_set_current_user()` explicitly to a
  known admin user (rare; usually a smell).

The lack of authenticated user context is the
right behavior for cron — it's a server-side
batch process, not a user action — but easy to
forget when porting code from request-scoped to
cron-scoped contexts.

## OPERATIONAL NOTES

The cron substrate's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *temporal-staged* form. The
  event is *declared* (registered into the cron
  option); it is *exposed* (executed) only after
  passing through eligibility (time has arrived),
  triggering (a tick has fired and reached this
  event), and execution (the callback has run).
  The four stages are observable; the gaps
  between them are real and diagnostic. Naming
  Law 1 here is genuinely clarifying because the
  *temporal gap* between scheduling and execution
  is the substrate's defining property.
- **Federation** appears as a **strong
  secondary**. The single `cron` option is the
  shared registry; every plugin / theme / core
  registers events into it; the tick dispatches
  every due event regardless of who registered
  it. Same federation pattern as the hook system
  (8.36) and the `@wordpress/data` registry, in
  the cron-storage variant. Worth one section
  reference (Section B); doesn't need
  re-elaboration.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly*. The `(hook, args)` tuple is the
  identity surface across schedule, query,
  unschedule, and execution. Hashing the args
  produces the per-event key in the storage
  array. Worth one mention; not a section.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *High-risk
  non-fit.* Multiple events scheduled at the
  same timestamp *look* like candidates queued
  for selection. They are not. All due events
  fire — they are processed in the order they
  appear in the option array, but none is
  discarded. The "queue" is a sequence-of-
  invocations, not a competition for one
  output slot. Naming Law 4 here would
  conflate *queue processing* with *candidate
  arbitration*. Same family of words ("queue,"
  "schedule"), different mechanism (FIFO
  iteration vs first-match-wins selection).
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** *Adjacent and explicitly non-fit.*
  The pattern of "external system cron hitting
  wp-cron.php" looks bridge-shaped — an external
  trigger reaches into the WordPress runtime to
  fire events. But no runtime authority is
  preserved across the boundary. The external
  trigger is a *temporal poke*, not a context
  handoff. The cron tick runs in its own PHP
  request, with its own request lifecycle, no
  state carried from the external system. The
  external cron is a *source of "tick now"
  signals*, not a *runtime context whose
  authority must continue*. Same explicit
  non-fit as the other "external trigger looks
  like a bridge" cases pinned in
  `interactivity.view-script-activation` and
  `data-layer.resolver-lifecycle`.
- **Doctrine 6 (Authority Mediation).** *Explicit
  non-fit.* The cron mechanism doesn't gate
  scheduling by capability. Any code can call
  `wp_schedule_event`. Capability checks (if
  any) live inside the callback, not at the
  scheduling boundary. Same pattern as the hook
  system: the substrate dispatches; the callback
  decides what to do. The hook system explicitly
  pinned this distinction in the prior chunk;
  the cron system inherits it.
- **Law 6 (Compiler ↔ Runtime Split).** No
  build / runtime split. Cron exists entirely
  at request runtime. Omitted.
- **Section X archetypes.** A temporal
  orchestration substrate is not a
  "civilization." Same framework-omission
  discipline as the surrounding chunks.
  Omitted.

Three literacy contributions worth pinning,
all extending the existing toolkits:

> *Scheduled ≠ executed.* An event that
> WordPress has accepted into the cron option
> is not the same as an event that has fired.
> Four stages — scheduled, eligible, triggered,
> executed — sit between the two. The substrate
> guarantees scheduling; it does not guarantee
> execution at any particular wall-clock time.
> The gap is the traffic-driven tick model
> (and, in `DISABLE_WP_CRON` deployments, the
> external-trigger reliability).

This contribution adds a *temporal* form to the
existence-vs-operation toolkit (alongside JIT
translations' availability, resolver
lifecycle's need fulfillment, and the various
declared-vs-fired patterns):

- *Embedded ≠ activated* (interactivity).
- *Reactive binding ≠ executed* (data-wp-on).
- *Availability ≠ activation* (JIT translations).
- *Registered ≠ reachable* (list tables).
- *Registered callback ≠ fired callback* (hooks).
- *Scheduled ≠ executed* (this chunk, cron).

The pattern across all six: a *registered
something* sits in a queue / cache / index /
preference / dispatch table waiting for a
trigger condition. The trigger condition can
fail to materialize. Same shape, different
substrates.

> *Scheduled queue ≠ candidate arbitration.*
> A queue of events processed in timestamp
> order, with all due events firing on each
> tick, is not the same as a candidate ladder
> from which one entry is selected as the
> winner. The queue *processes* every event;
> arbitration *selects* one. The order matters
> for *when* events fire (oldest-due first);
> it does not determine *which* event runs
> (all of them do).

This adds a seventh distinct example to the
anti-Law-4 inventory:

- *Need fulfillment ≠ option arbitration*
  (resolver lifecycle).
- *Availability ≠ activation*'s implicit
  anti-Law-4 (JIT translations).
- *Formula-driven selection ≠ candidate
  arbitration* (plural forms).
- *Operator-selected ordering ≠ candidate
  arbitration* (list tables).
- *Layer precedence ≠ candidate arbitration*
  (theme.json source layering).
- *Hook priority ≠ candidate arbitration*
  (hooks lifecycle).
- *Scheduled queue ≠ candidate arbitration*
  (this chunk).

The pattern continues to grow as different
mechanisms wear surface vocabulary that
*sounds* arbitration-shaped without sharing the
mechanism.

> *Temporal-stage ladder.* The 4-stage cron
> ladder (scheduled → eligible → triggered →
> executed) is the temporal parallel to the
> interactivity 4-step ladder (embedded →
> activated → triggered → executed). Both
> name observable states with deterministic
> transitions whose failure modes are
> diagnostic. The cron ladder spans *time
> across many requests*; the interactivity
> ladder spans *one rendered request*. Same
> shape of staged pipeline, different temporal
> scope.

This contribution doesn't add to the toolkit
inventory but *links* the temporal-staged Law 1
form (this chunk) with the within-request-staged
Law 1 form (interactivity). Recognizing the
parallel makes both ladders easier to reach for
when diagnosing pipeline failures.

## CHECKLIST

When using WP-Cron:

- [ ] Don't expect exact timing. Cron is
      "next tick after due"; for exact timing,
      use system cron + DISABLE_WP_CRON.
- [ ] Always check `wp_next_scheduled()` before
      `wp_schedule_event()` to avoid duplicate
      registrations.
- [ ] Always `wp_clear_scheduled_hook()` on
      `register_deactivation_hook()` to avoid
      orphaned events accumulating.
- [ ] Custom recurrences need
      `cron_schedules` filter registration
      *before* scheduling. Failing to do so
      is a silent failure.
- [ ] Chunk long-running callbacks. Reschedule
      a follow-up `wp_schedule_single_event`
      if more work remains; don't try to
      finish everything in one invocation.
- [ ] Don't gate cron callbacks by
      `current_user_can()`. Cron has no current
      user.
- [ ] For high-traffic sites, the traffic-
      driven model is enough. For low-traffic
      sites or sites needing reliable timing,
      use system cron + DISABLE_WP_CRON.
- [ ] When debugging "cron not firing," walk
      the 4-stage ladder: scheduled? eligible?
      triggered? executed? Each transition has
      a distinct cause-of-failure profile.

## REFERENCES

- WordPress plugin handbook — Cron API.
  Documents the traffic-driven model and the
  scheduling functions.
  https://developer.wordpress.org/plugins/cron/
- `wp_schedule_event()` reference.
  https://developer.wordpress.org/reference/functions/wp_schedule_event/
- `wp_schedule_single_event()` reference.
  https://developer.wordpress.org/reference/functions/wp_schedule_single_event/
- `wp_cron()` reference. The tick that
  walks due events.
  https://developer.wordpress.org/reference/functions/wp_cron/
- `spawn_cron()` reference. The async-spawn
  of wp-cron.php that triggers 1 and 2 use.
  https://developer.wordpress.org/reference/functions/spawn_cron/
- `cron_schedules` filter reference. The
  customization point for recurrence
  definitions.
  https://developer.wordpress.org/reference/hooks/cron_schedules/
- WP-Cron API guide. Documents
  `DISABLE_WP_CRON` and external triggering.
  https://developer.wordpress.org/apis/wp-cron/

Cross-context:

- `plugin-dev.hooks-lifecycle-and-priority` —
  the substrate cron events ultimately fire
  on. WP-Cron is a temporal layer over the
  hook dispatch substrate; the pair of chunks
  forms the *substrate + temporal extension*
  architecture.
- `plugin-dev.security-boundaries` —
  capability checks belong inside cron
  callbacks. The cron mechanism doesn't
  authorize; the callback's body decides
  what's allowed.
- `data-layer.persistence` — the wp_options
  storage substrate that holds the `cron`
  option.
