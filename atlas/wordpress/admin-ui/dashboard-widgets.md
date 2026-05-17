---
rule_id: admin-ui.dashboard-widgets
domain: admin-ui
topic: institutional-modular-composition
field_cluster: dashboard-widget-substrate
wp_min: "2.7"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/wp_add_dashboard_widget/
    section: "wp_add_dashboard_widget() — registration signature"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/wp_dashboard_setup/
    section: "wp_dashboard_setup action — registration hook"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/remove_meta_box/
    section: "remove_meta_box() — system-level widget removal"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_network_dashboard_setup/
    section: "wp_network_dashboard_setup hook for multisite network admin"
    captured: 2026-05-10
  - url: https://codex.wordpress.org/Dashboard_Widgets_API
    section: "Dashboard Widgets API overview (legacy reference)"
    captured: 2026-05-10
related:
  - admin-ui.list-tables                           # multi-gate reachability parallel; widgets has its own version
  - admin-ui.screen-options                       # user dismissal is the per-user personalization layer
  - admin-ui.admin-menus                          # adjacent admin surface (menu vs widget composition)
  - plugin-dev.hooks-lifecycle-and-priority       # wp_dashboard_setup is an action; registration runs through hooks
  - plugin-dev.capabilities-and-roles             # widget callback capability checks are plugin-side responsibility
---

# RULE — `wp_add_dashboard_widget` — institutional modular composition for the WordPress admin dashboard

## WHEN

You are registering a widget for the WordPress
admin dashboard, removing built-in or other
plugins' widgets, or reasoning about why a widget
appears (or doesn't) for a particular operator.
Use this knowledge when:

- Adding a custom dashboard widget that displays
  plugin-relevant data, status, or shortcuts.
- Removing core widgets (welcome panel, activity,
  events) for branded or simplified admin
  experiences.
- Diagnosing "my widget doesn't appear" —
  almost always: registered on wrong hook,
  wrong screen, capability missing, or user
  dismissed it.
- Understanding why operator A sees the widget
  while operator B doesn't (per-user dismissal,
  per-user order — both are preference layer,
  not registration layer).
- Reading core code that registers built-in
  dashboard widgets (`wp-admin/includes/dashboard.php`).

This chunk does **not** cover:

- The general `add_meta_box()` mechanism for
  post-edit-screen metaboxes —
  `wp_add_dashboard_widget` wraps that mechanism
  for the dashboard screen specifically.
  General-purpose metaboxes are their own topic.
- Block-editor widgets or the block-based widget
  area replacement (different "widget" entirely
  — those are sidebar widgets / block patterns
  for sidebar areas).
- The Site Health screen widgets (those use a
  different registration mechanism specific to
  site health checks).
- The admin dashboard's overall layout / theme
  customization. This chunk is about *adding /
  removing widgets*, not redesigning the
  dashboard chrome.

The principle this chunk operates under: **A
dashboard widget is *declared* once via
registration, then passes through capability,
per-user dismissal, and per-user position
preferences before it reaches a particular
operator's actual view. Registration is the
beginning; reachable presence is a multi-gate
result. The mechanism is *modular composition
with structured placement*, not arbitration or
flat federation.**

## SHAPE

### A. The registration signature

```php
wp_add_dashboard_widget(
    string        $widget_id,
    string        $widget_name,
    callable      $callback,
    callable|null $control_callback = null,
    array|null    $callback_args    = null,
    string        $context          = 'normal',
    string        $priority         = 'core'
): void;
```

The seven parameters:

| Parameter           | Purpose                                                      |
| ------------------- | ------------------------------------------------------------ |
| `$widget_id`        | Unique slug; used in dismissal state, position state, removal |
| `$widget_name`      | Display title shown to operators                              |
| `$callback`         | Function that renders the widget body                         |
| `$control_callback` | Optional callback that renders a config form for the widget   |
| `$callback_args`    | Optional array passed to `$callback`                          |
| `$context`          | `'normal'` (main column) or `'side'` (sidebar column)         |
| `$priority`         | `'high'`, `'core'`, `'default'`, `'low'` — initial placement hint |

The function delegates internally to
`add_meta_box()` with the dashboard screen ID
(`'dashboard'`). The widget is then part of
WordPress's metabox system — but specifically for
the dashboard screen.

Two properties to pin:

- **`$widget_id` is the long-lived identity**
  across all per-user state. Dismissal, position,
  and removal references all use this ID. Pick a
  namespace-prefixed string (`myplugin_overview`)
  to avoid collisions.
- **`$callback` is invoked at render time, not
  at registration time.** The function signature
  receives `$post = null` (no post on the
  dashboard) and the registered `$callback_args`
  array. The body is responsible for its own
  capability checks if the widget shows
  sensitive data.

### B. Where to register — `wp_dashboard_setup`

Registration must happen on the
`wp_dashboard_setup` action, which fires during
dashboard screen initialization:

```php
add_action( 'wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'myplugin_overview',
        __( 'My Plugin Overview', 'myplugin' ),
        'myplugin_render_overview_widget'
    );
} );
```

Other hooks (`init`, `admin_init`, etc.) fire
either before the dashboard screen exists or
after registration has been read. Wrong hook =
silent failure.

For multisite network-admin dashboards, the
parallel hook is `wp_network_dashboard_setup`.
The two are independent surfaces — a widget
registered on `wp_dashboard_setup` doesn't appear
on the network dashboard and vice versa.

### C. The reachability cascade — registration to operator surface

A registered widget passes through several gates
before an operator actually sees it:

```
Stage 1 — REGISTRATION
   wp_add_dashboard_widget called on wp_dashboard_setup.
   Widget enters core's metabox registry for screen='dashboard'.
              │
              ▼
Stage 2 — DASHBOARD ACCESS
   Operator can access wp-admin/index.php
   (gated by 'read' capability minimum;
   typically also by 'edit_dashboard' or role).
   No access = no dashboard = no widget.
              │
              ▼
Stage 3 — CAPABILITY CHECK (widget-specific)
   Widget callback's body decides whether to render
   sensitive data. Plugin's responsibility, not
   automatic.
              │
              ▼
Stage 4 — USER DISMISSAL FILTER
   get_hidden_meta_boxes('dashboard') returns widget IDs
   this user has dismissed via Screen Options checkbox
   or via the widget's "X" close button.
   Hidden widgets render with display:none CSS, not omitted.
              │
              ▼
Stage 5 — USER ORDER PREFERENCE
   meta-box-order_dashboard user meta determines the
   per-user order within each context (normal/side).
   Defaults to registration order with priority hints.
              │
              ▼
Stage 6 — RENDER
   For visible widgets in user's preferred order,
   $callback fires; output appears in the dashboard.
```

Six gates. The widget *exists in the system*
after Stage 1; it *appears to the operator* only
after Stages 2-6 all permit. The gap is the
substrate's diagnostic surface: when a widget
"isn't showing," walking the stages narrows
down which gate is blocking.

### D. Two distinct removal mechanisms

Two ways a widget can be "not visible" — and they
mean very different things:

**System-level removal:** `remove_meta_box()`

```php
add_action( 'wp_dashboard_setup', function() {
    remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
}, 999 );  // priority 999 — after default registrations
```

This unregisters the widget. It's gone from
*every operator's* dashboard, gone from Screen
Options, gone from the metabox registry. The
widget no longer exists for the screen.

**User-level dismissal:** clicking the widget's
"X" close button or unchecking it in Screen
Options.

This adds the widget ID to the operator's
`metaboxhidden_dashboard` user meta. The widget
*still exists* in the registry; it's still in
Screen Options (where the operator can re-show
it); it's just CSS-hidden in this operator's
dashboard view.

The distinction is structural:

| Action                  | Layer            | Scope             | Recoverable by operator?         |
| ----------------------- | ---------------- | ----------------- | --------------------------------- |
| `remove_meta_box`       | System           | All operators     | No (operator can't re-add)        |
| User dismissal          | Per-user prefs   | Just this operator | Yes (Screen Options checkbox)    |

A widget removed via `remove_meta_box` is
*deregistered*; a widget dismissed by a user is
*preference-hidden*. Operators have no way to
recover system removal. Plugins have no way (via
the dashboard widget API) to dismiss for an
operator.

### E. Built-in dashboard widgets — what core ships

The dashboard ships with built-in widgets at
known IDs:

| Widget ID                  | What it shows                              |
| -------------------------- | ------------------------------------------ |
| `dashboard_right_now`      | "At a Glance" — counts of posts, pages, comments |
| `dashboard_activity`       | Recent comments, recently published posts   |
| `dashboard_quick_press`    | Quick Draft form                            |
| `dashboard_primary`        | WordPress Events and News                   |
| `welcome_panel`            | "Welcome to WordPress!" panel (different mechanism — see note below) |

Plugins can `remove_meta_box` any of these to
declutter the dashboard for branded experiences.
The welcome panel uses a slightly different
mechanism (its own remove function:
`remove_action('welcome_panel', 'wp_welcome_panel')`)
because it predates the metabox-based widget
system.

### F. Position semantics — context + priority + per-user order

The `$context` parameter selects which column the
widget initially lands in:

- `'normal'` — the main, wider column (left side
  of the dashboard).
- `'side'` — the narrower column (right side).

Within each context, `$priority` provides a
coarse initial ordering hint (`high` → `core` →
`default` → `low`). This is the *initial*
placement only; per-user drag-and-drop
reordering supersedes it.

The per-user order is stored in
`meta-box-order_dashboard` user meta with shape:

```
[
    'normal'  => 'widget_a,widget_b,widget_c',
    'side'    => 'widget_d,widget_e',
]
```

Each context's value is a comma-separated string
of widget IDs in the operator's preferred order.

Two important properties:

- **Priority is a hint, not a guarantee.** A
  newly registered `priority='high'` widget may
  land below operators' previously-customized
  ordering. The hint sets defaults; operators
  override.
- **Position is per-user.** Two operators can
  see the same widgets in entirely different
  orders. Plugin code reading "where is my widget
  positioned" can't get a global answer; the
  answer depends on whose dashboard.

### G. The federation shape — structured-placement variant

Multiple plugins register widgets into the same
dashboard. The mechanism shares some properties
with hook-system federation
(`plugin-dev.hooks-lifecycle-and-priority`) but
differs in important ways:

| Property                              | Hooks federation        | Dashboard widgets                |
| ------------------------------------- | ----------------------- | -------------------------------- |
| Shared registry                       | One `$wp_filter` array  | One metabox registry per screen  |
| Open registration                     | Any plugin, any hook    | Any plugin, any context          |
| All participants run                  | Yes (composition)       | Yes (all widgets render unless removed) |
| Order semantics                       | Priority numeric        | Priority bucket + per-user order  |
| Structure on placement                | None (just sequence)    | Strong (context + per-user position) |
| Per-user customization                | None                    | Yes (dismissal + reorder)        |

The dashboard widget mechanism is *federation
with structured placement*: it shares the open-
registration / all-render properties with the
classic federation pattern, but it adds:

- **Spatial structure** (two columns, named).
- **Per-user customization** (dismissal,
  ordering).
- **Priority bucketing** (categorical, not
  numeric).

This is a federation variant — close enough to
the family to recognize, distinct enough that
saying "it's federation" without qualification
under-describes it. The phrasing worth pinning:
*structured-placement federation* — federated
contributors, but with structural placement
semantics atop the federation.

## WHY

### Why a separate registration function rather than direct `add_meta_box`

`wp_add_dashboard_widget` is technically a
wrapper around `add_meta_box` with the screen
fixed to `'dashboard'`. The wrapper exists for:

- **Discoverability** — the function name
  matches operator language ("dashboard widget").
- **Convention** — it picks reasonable defaults
  for a parameter that would otherwise need
  explicit specification.
- **Future-proofing** — wrapping the underlying
  mechanism leaves room for the dashboard to
  evolve away from the metabox system without
  breaking plugins.

The cost is one extra function in the
namespace; the benefit is operator-aligned
naming and convention encoding.

### Why per-user dismissal rather than system-level

Different operators use the dashboard
differently. A site editor wants the activity
feed; a developer wants the system status;
neither needs the other's widgets. Per-user
dismissal lets each operator curate their own
view without forcing site-wide policy.

System-level dismissal (`remove_meta_box`) is
still available — for cases where a plugin
genuinely needs the widget gone for everyone
(security, branding, performance). The two
mechanisms are complementary: per-user for
preference, system-level for policy.

### Why priority is bucketed rather than numeric

Numeric priorities (like the hook system's
0-99) work for fine-grained ordering of many
participants. Dashboard widgets typically have
fewer participants per dashboard; a 4-level
bucket (`high`/`core`/`default`/`low`) is enough
expressiveness without inviting plugin authors
to fight over the integer space.

The buckets also align with intent rather than
mechanism: "this widget is important; high"
reads more clearly than "priority 3".

### Why position state is per-user

Same reason as dismissal: dashboard utility is
operator-specific. A team of editors might want
the activity feed top-left; a team of developers
wants system status top-left. Per-user position
lets each operator arrange for their own
workflow.

The cost is no global truth about widget
position; the benefit is each operator gets a
dashboard that matches their needs.

## WHEN NOT

Skip dashboard widgets if:

- The information is **not glance-worthy** —
  dashboard widgets are for at-a-glance reading,
  not deep workflows. Long forms or complex
  interactions belong on dedicated admin pages.
- The information is **per-post-edit-screen** —
  use `add_meta_box` directly for post / page /
  custom-post-type metaboxes.
- The information requires **constant updates**
  — dashboard widgets render once per page load,
  not in real time. For live data, the widget
  body would need its own JS-driven refresh
  (additional infrastructure beyond what this
  chunk covers).
- The plugin is **not adding operator-facing
  visibility** to the dashboard — dashboard
  widgets are operator-facing by definition.
  Background plugins don't need them.

## COUNTER-PATTERNS

### Anti-pattern 1 — Registering on the wrong hook

```php
add_action( 'init', function() {
    wp_add_dashboard_widget( … );  // wrong hook
} );
```

`init` fires before dashboard screen
initialization. The widget registry isn't ready
to accept registrations. Use
`wp_dashboard_setup`:

```php
add_action( 'wp_dashboard_setup', function() {
    wp_add_dashboard_widget( … );
} );
```

### Anti-pattern 2 — Skipping capability checks in callback

```php
function myplugin_render_widget() {
    echo '<p>' . esc_html( get_secret_data() ) . '</p>';  // exposes to any dashboard viewer
}
```

The dashboard's overall capability gate (Stage 2)
admits any operator who can see the dashboard.
Per-widget data sensitivity is the plugin's
responsibility:

```php
function myplugin_render_widget() {
    if ( ! current_user_can( 'view_secrets' ) ) {
        return;  // or render a placeholder
    }
    echo '<p>' . esc_html( get_secret_data() ) . '</p>';
}
```

### Anti-pattern 3 — Treating user dismissal as system removal

Plugin author observes "my widget isn't showing"
in their own admin and assumes it's broken,
when in fact they (or core) dismissed it earlier
via the X button or Screen Options. The widget
is registered fine; just hidden for *this user*.

Check Screen Options to re-show before
diagnosing as a registration bug.

### Anti-pattern 4 — Hard-coding `meta-box-order_dashboard` user meta

```php
update_user_meta( $user_id, 'meta-box-order_dashboard', [
    'normal' => 'myplugin_widget,dashboard_right_now',
    'side'   => '',
] );
```

This overwrites the operator's existing layout
preferences. They lose the ordering they
configured. Don't programmatically rewrite
per-user state unless the operator explicitly
opted in.

If you want to *suggest* a default layout for a
new operator, set it once on first dashboard
visit (with appropriate detection); don't
overwrite on every plugin load.

### Anti-pattern 5 — Removing core widgets without considering operator workflow

```php
add_action( 'wp_dashboard_setup', function() {
    remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
    remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
    remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
}, 999 );
```

Removing widgets globally affects every operator,
including those who relied on them. If the goal
is decluttering for *some* operators (e.g.,
non-admin roles), gate removal on capability:

```php
add_action( 'wp_dashboard_setup', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
    }
}, 999 );
```

Or, prefer letting operators dismiss widgets
themselves rather than removing them
system-wide.

### Anti-pattern 6 — Using widget IDs without namespace prefix

```php
wp_add_dashboard_widget( 'overview', 'Overview', 'render_overview' );
```

`'overview'` is generic enough that another
plugin could register the same ID. The second
registration silently fails (or replaces the
first; behavior depends on registration order).
Always namespace:

```php
wp_add_dashboard_widget( 'myplugin_overview', … );
```

## OPERATIONAL NOTES

The dashboard-widget substrate's interpretive
shape, in proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *6-stage cascade* form. The
  widget is *declared* via registration; it is
  *exposed* (visible in an operator's dashboard)
  only after passing through dashboard access,
  per-widget capability check, user dismissal
  filter, and user order preference. The gap
  between "the widget is registered" and "this
  operator sees this widget right now" is the
  multi-stage cascade. Naming Law 1 here is
  genuinely clarifying because it makes the
  diagnostic question *which gate is blocking?*
  legible. The framing parallels list-tables'
  multi-gate reachability cascade (8.34) but
  with widget-specific gates (dismissal, order)
  rather than table-specific ones (sortable
  columns, hidden columns).
- **Doctrine 5 (Authority Continuity)** appears
  *moderately*. The widget ID is the continuity
  surface across registration, dismissal state
  storage, position state storage, and removal
  references. Same string identifies the widget
  through every operation that touches it.
  Worth one mention; not a section.
- **Doctrine 6 (Authority Mediation)** appears
  *softly, adjacent*. Dashboard access is
  capability-gated at Stage 2 (a property of
  the dashboard screen, not the widget). Per-
  widget capability checks are the plugin's
  responsibility (Stage 3). Neither is the
  *widget mechanism's* concern; both are
  applications of capability machinery that
  lives elsewhere. Worth one mention; not a
  section.
- **Federation** appears in a **structured-
  placement variant** (Section G). Multiple
  plugins federate into the dashboard registry,
  all participants render — but with spatial
  structure (context columns) and per-user
  customization (dismissal, ordering) that pure
  hook-style federation lacks. Worth one
  section reference; the variant phrase is the
  literacy contribution.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit.* Several superficially Law-4-shaped
  features are not arbitration:
  - Priority bucketing (`high`/`core`/`default`/
    `low`) is an *initial placement hint*, not
    a candidate ladder. All widgets render;
    priority just orders them within their
    context.
  - Per-user position is an *operator-set
    arrangement*, not an arbitration over
    candidates.
  - Multiple plugins registering the same
    widget ID don't compete; the first wins
    silently, the second is rejected (closer to
    a uniqueness constraint than arbitration).
  Naming Law 4 here would conflate *ordered
  placement* with *candidate selection*. Same
  family of non-fits as hooks priority and cron
  scheduling.
- **Law 3b (Cross-Runtime Bridge).** All
  registration and rendering runs in PHP per
  request. No cross-runtime authority
  preservation. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** No
  build / runtime split inherent. Omitted.
- **Section X archetypes.** A dashboard widget
  substrate is not a "civilization." Same
  framework-omission discipline as the
  surrounding chunks. Omitted.

Three literacy contributions worth pinning:

> *Registered widget ≠ rendered dashboard
> presence.* A widget that exists in the metabox
> registry is not the same as a widget visible
> on a particular operator's dashboard. The
> 6-stage cascade — registration, dashboard
> access, capability, dismissal filter, order
> preference, render — sits between the two.
> The diagnostic frame: if a widget "isn't
> showing," walk the stages until you find
> which gate is blocking.

This contribution adds another *multi-gate
reachability* member to the existence-vs-
operation toolkit. The list-tables chunk
introduced 7-gate reachability for table
columns; this chunk adds 6-stage reachability
for dashboard widgets. The pattern recurs in
admin-ui terrains specifically: institutional
governance surfaces tend to have *several
filters* between system declaration and
operator-visible result.

> *Personal dismissal ≠ system deregistration.*
> A widget hidden by a user via the X button
> or Screen Options is not the same as a
> widget removed from the registry by
> `remove_meta_box`. The first is per-user
> preference (CSS-hidden, recoverable through
> Screen Options); the second is system policy
> (gone for everyone, irrecoverable by
> operators). Both make the widget "not
> visible," but they mean structurally
> different things — different scopes, different
> persistence, different recovery paths.

This contribution parallels the
`Preference ≠ permission` literacy from
`admin-ui.screen-options`. Same governance
distinction in slightly different terrain:
*per-user state can hide / suppress / dismiss*,
*system state can register / deregister*.
Conflating them produces predictable bugs
("plugin author thinks their widget is
unregistered when it's just dismissed";
"system admin thinks they removed a widget but
operators can re-show it via Screen Options").
Two layers, two responsibilities, two
recovery models.

> *Federation with structured placement.* A
> registry that admits open contribution and
> renders all participants — federation's
> archetypal property — with additional
> structure on *where* and *in what order*
> participants render is a federation variant.
> Hook dispatch (8.36) is *unstructured-
> sequence* federation: every callback runs in
> priority order, no spatial concept.
> Dashboard widgets are *structured-placement*
> federation: every widget renders, but with
> column placement and per-user reordering
> atop the registry. The two share the
> registry-and-all-render property; they
> differ in how strongly the framework
> structures the rendering side.

This contribution refines Federation as a
pattern family rather than a single shape.
The audit (Phase 8.M1) already noted
"multiplicity ≠ federation"; this chunk
adds the inverse refinement: *federation has
variants*. The dashboard widget variant
expands what counts as federation while
preserving the distinction from non-federation
patterns (per-user state, layered governance,
parallel co-existence — none of those are
federation). Federation has substructure;
naming the substructure is the contribution.

## CHECKLIST

When using `wp_add_dashboard_widget`:

- [ ] Register on the `wp_dashboard_setup`
      action. Other hooks fire too early or
      too late.
- [ ] Use a namespace-prefixed `$widget_id`
      (`myplugin_overview`, not `overview`).
- [ ] Check capabilities inside the render
      callback for sensitive data. The
      dashboard's overall gate is not enough.
- [ ] Don't conflate user dismissal with
      system removal. Use `remove_meta_box`
      for system-level removal; let operators
      dismiss widgets themselves for
      per-user preference.
- [ ] When removing core widgets, gate by
      capability if the goal is per-role
      decluttering rather than universal
      removal.
- [ ] Don't programmatically overwrite
      operators' `meta-box-order_dashboard`
      user meta. Their layout preferences are
      theirs.
- [ ] When diagnosing "widget not showing,"
      walk the 6-stage cascade. Each stage
      has distinct symptoms.

## REFERENCES

- `wp_add_dashboard_widget()` reference.
  Documents the registration signature.
  https://developer.wordpress.org/reference/functions/wp_add_dashboard_widget/
- `wp_dashboard_setup` action hook reference.
  https://developer.wordpress.org/reference/hooks/wp_dashboard_setup/
- `remove_meta_box()` reference. The
  system-level removal mechanism.
  https://developer.wordpress.org/reference/functions/remove_meta_box/
- `wp_network_dashboard_setup` hook reference.
  Network-admin-dashboard registration.
  https://developer.wordpress.org/reference/functions/wp_network_dashboard_setup/
- Dashboard Widgets API overview (legacy
  Codex reference; kept for historical
  context).
  https://codex.wordpress.org/Dashboard_Widgets_API

Cross-context:

- `admin-ui.list-tables` — the multi-gate
  reachability parallel. List tables has
  7-gate reachability for columns; this
  chunk adds 6-stage reachability for
  widgets. Both are
  *registered-system-multi-gate-operator-view*
  patterns in admin-ui terrain.
- `admin-ui.screen-options` — the
  *Preference ≠ permission* parallel. User
  dismissal of widgets is the same governance
  layer as user-hidden columns: per-user
  preference, not access control.
- `admin-ui.admin-menus` — adjacent admin
  composition surface. Menu items vs widget
  composition: both modular, different
  surfaces.
- `plugin-dev.hooks-lifecycle-and-priority` —
  the hook system that `wp_dashboard_setup`
  uses for registration. Dashboard widgets
  are *structured-placement* federation,
  hooks are the *unstructured-sequence*
  federation reference.
- `plugin-dev.capabilities-and-roles` —
  the capability machinery that the
  dashboard's Stage 2 gate and the per-widget
  callback check both consume.
