---
rule_id: admin-ui.screen-options
domain: admin-ui
topic: per-user-personalization
field_cluster: screen-preferences-substrate
wp_min: "3.3"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/add_screen_option/
    section: "add_screen_option() — per-user per-screen preference registration"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/classes/wp_screen/
    section: "WP_Screen class — get_option(), add_help_tab(), screen meta"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/set-screen-option/
    section: "set-screen-option / set_screen_option_{option} filter — custom option persistence"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/get_hidden_columns/
    section: "get_hidden_columns() — current-user hidden column list"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/get_user_meta/
    section: "get_user_meta() — the storage layer screen options ultimately use"
    captured: 2026-05-10
related:
  - admin-ui.list-tables                            # the most common consumer of per-page + hidden columns
  - admin-ui.settings-api                           # adjacent admin governance surface (system-level config)
  - admin-ui.admin-menus                            # registers the page hook screen-options binds to
  - plugin-dev.capabilities-and-roles               # capability is a different axis (this chunk is preference axis)
---

# RULE — Screen Options — per-user per-screen personalization layer

## WHEN

You are working on a wp-admin screen and need to
expose per-user preferences (items per page, hidden
columns, layout choices, custom toggles) through the
top-right "Screen Options" panel. Use this knowledge
when:

- Adding a custom items-per-page input to a
  `WP_List_Table` page.
- Adding a custom checkbox-style preference (e.g.,
  "Show advanced fields") to an admin screen.
- Reading current-user preferences in `prepare_items()`
  or render code.
- Diagnosing why a hidden-column preference seems to
  "leak" between users (it doesn't — they're per-user;
  the diagnosis usually finds a global cache instead).
- Understanding why an option declared via
  `add_screen_option()` doesn't persist (almost always:
  missing `set-screen-option` filter for custom
  options).

This chunk does **not** cover:

- The `WP_List_Table` substrate that consumes
  per-page and hidden-column preferences — covered
  in `admin-ui.list-tables`. This chunk is its
  *personalization layer*.
- Help tabs (`add_help_tab()`) or the screen meta
  toggle / sidebar — those are sibling surfaces on
  `WP_Screen` but a different mechanism.
- Capability checks or access gating — covered in
  `plugin-dev.capabilities-and-roles`. Capabilities
  are policy; screen options are preference. This
  chunk's central distinction depends on keeping the
  two separate.
- Block-editor user preferences (the
  `core/preferences` data store), which is a
  different mechanism for a different runtime.

The principle this chunk operates under: **Screen
Options are per-user preferences, not per-user
permissions. A hidden column is not a forbidden
column. A 20-items-per-page preference is not a
read-quota. The mechanism's entire surface lives at
the personalization layer; the authority layer is
elsewhere.**

## SHAPE

### A. The Screen Options surface — where it appears, what it is

Every wp-admin page has a `WP_Screen` instance
(reachable via `get_current_screen()`). For screens
that have any registered options (or core-injected
ones like the column visibility for list tables), WP
renders a "Screen Options" panel collapsed at the
top-right of the page. Operators click to expand it
and see:

- Column visibility checkboxes (auto-injected for
  any screen with `_column_headers` set, e.g., a
  `WP_List_Table`).
- "Number of items per page" input (when registered
  via `add_screen_option('per_page', …)`).
- Layout column toggles (1-column / 2-column, on
  screens that support it).
- Custom options registered by plugins.

The panel posts to `?wp_screen_options` on submit;
WordPress saves the values to the current user's
meta and reloads the page with the preferences
applied.

The panel is **always personalization, never
authorization**. It is the operator's view of how
they want this screen to look — not a control over
what they can do on this screen.

### B. `add_screen_option()` registration

Per-screen, per-option registration. Must fire on
the page's `load-{$page_hook}` action so the screen
is the right one when the call runs:

```php
add_action( 'load-' . $my_page_hook, function() {
    add_screen_option( 'per_page', array(
        'label'   => __( 'Items per page', 'my-plugin' ),
        'default' => 20,
        'option'  => 'my_items_per_page',  // user_meta key
    ) );
} );
```

The `$option` argument names the *user_meta key*
where this user's preference will be stored. By
convention the key includes the screen identifier
to avoid collisions.

Three built-in option types are recognized
specially:

| Option type           | Built-in handling                                          |
| --------------------- | ---------------------------------------------------------- |
| `'per_page'`          | Renders an integer input; auto-saved to specified user meta |
| `'layout_columns'`    | Renders a 1/2-column toggle; some core screens use this    |
| Anything else         | Custom — requires `set-screen-option` filter (Section E)    |

For list-table pages, `'per_page'` is the dominant
case. For dashboard widgets and a few other
screens, `'layout_columns'` appears.

### C. Storage layer — per-user meta keys

All screen options ultimately store as user meta
(via `update_user_meta` / `get_user_meta`). The key
naming conventions:

| What                        | User meta key shape                                  |
| --------------------------- | ---------------------------------------------------- |
| Per-page preference         | The `'option'` argument to `add_screen_option`        |
| Hidden columns (list table) | `manage{$screen->id}columnshidden`                    |
| Layout columns              | `screen_layout_{$screen->id}`                         |
| Custom option               | The `'option'` argument to `add_screen_option`        |

Reading examples:

```php
// In prepare_items() for a list table:
$per_page = (int) get_user_meta( get_current_user_id(), 'my_items_per_page', true );
if ( $per_page < 1 ) {
    $per_page = 20;  // default
}

// Or via the helper provided by WP_List_Table:
$per_page = $this->get_items_per_page( 'my_items_per_page', 20 );

// Hidden columns:
$hidden = get_hidden_columns( get_current_screen() );
```

The storage is **per-user**: every operator has
their own row(s) in `wp_usermeta` for their own
preferences. There is no "site-wide screen
preference" — the mechanism is fundamentally
per-account.

### D. Reading preferences in render / query code

Three points where preferences typically influence
rendering:

- **`prepare_items()` in a list table.** Reads
  per-page (to set `LIMIT`), hidden columns (so
  `_column_headers` is correctly populated), and
  any custom preferences that affect the query.
- **Per-column render methods.** Generally do
  *not* read hidden columns directly — the column
  is rendered into HTML regardless; the HTML class
  (added by core) hides it via CSS. Reading the
  hidden state in render code is usually a sign of
  conflating preference with permission (Anti-pattern 4
  of `admin-ui.list-tables`).
- **Custom screen logic.** Any custom toggle
  registered via `add_screen_option` typically gets
  read via `get_user_meta` at the start of the
  page handler.

Two properties to pin:

- **Reading current-user meta requires a logged-in
  user context.** All admin screen renders run
  authenticated, so this is normally fine. CLI
  rendering or background contexts may not have a
  current user.
- **Defaults are the operator's first-encounter
  state.** Always provide a sane fallback (`get_user_meta`
  returns `''` if the user has never set a
  preference); the default is what every operator
  sees until they personalize.

### E. Custom options need `set-screen-option` filter

For options that aren't `'per_page'` or
`'layout_columns'`, WP doesn't know how to save the
posted value. The `set-screen-option` filter (or
its dynamic variant `set_screen_option_{$option}`)
is where the plugin tells WP how to handle its
custom option:

```php
add_filter( 'set_screen_option_my_advanced_toggle', function( $status, $option, $value ) {
    return $value;  // accept the posted value as-is (validate first in production)
}, 10, 3 );
```

Without this filter, the option's posted value is
ignored. The filter can also return `false` to
explicitly reject saving (useful for capability-
gated saves: "only admins can change this
preference").

The filter completes the registration flow:

```
add_screen_option( 'my_advanced_toggle', […] );
                  │
                  ▼
       Renders panel UI for this option
                  │
                  ▼
       Operator submits Screen Options form
                  │
                  ▼
       set_screen_option_my_advanced_toggle filter fires
       Plugin validates / sanitizes; returns value
                  │
                  ▼
       update_user_meta( $user_id, 'my_advanced_toggle', $value )
                  │
                  ▼
       Page reload; new value reads back from user meta
```

For `per_page` and `layout_columns`, this flow runs
inside core; for anything custom, the plugin must
participate.

### F. The preference / permission boundary

The mechanism's most important property — and the
chunk's central conceptual value — is what
preferences *are not*.

| Preference (Screen Options)              | Permission (capabilities + access)        |
| ----------------------------------------- | ----------------------------------------- |
| Hidden column                             | Forbidden column                          |
| Per-page = 5                              | Read quota of 5 records                   |
| Layout = 1-column                         | Restricted to single-column functionality |
| Custom toggle "Show advanced fields" off  | Forbidden from advanced operations         |

Each row pairs a *preference state* with what
sounds like the same thing in policy language. They
are not the same thing:

- A hidden column is **still in the rendered HTML**
  with a CSS class that hides it. Any operator
  could untoggle the preference and see the
  column. There is no policy preventing that.
- Per-page = 5 means "show me 5 at a time"; the
  operator can paginate to see all records, set
  per-page = 100, or query the data through
  another route. The mechanism shapes the
  operator's view, not the operator's reach.
- Custom toggles are operator-set; they govern
  what the operator wants to *see*, not what they
  are *permitted to do*.

**A preference revoked is not a permission revoked.**
This distinction is the operational reason to keep
the two layers separate in code: anything that
needs to be *forbidden* must be enforced at the
capability/access layer, regardless of what the
preference layer happens to indicate.

The corollary in code: never use Screen Options
state as a security gate.

```php
// Wrong — using preference as permission:
if ( ! in_array( 'sensitive_data', get_hidden_columns( $screen ), true ) ) {
    echo esc_html( $item['sensitive_data'] );  // exposes if not hidden
}

// Right — using capability as permission:
if ( current_user_can( 'view_sensitive_data' ) ) {
    echo esc_html( $item['sensitive_data'] );
}
```

The two reads might happen to align for a given
operator on a given day; relying on that alignment
is the bug.

## WHY

### Why per-user storage

A site has many operators with different roles,
different screen widths, different working
preferences. A site-wide "default per-page" would
force one choice on all operators; per-user storage
lets each operator personalize without affecting
anyone else.

The cost is per-user meta rows (cheap; user_meta
scales fine for typical operator counts). The
benefit is that the screen adapts to the operator
rather than the operator adapting to the screen.

### Why the column-visibility checkbox grid is auto-injected

Every list-table screen has a known set of columns
(from `get_columns()`). Generating a checkbox per
column is mechanical — there's no plugin choice to
make. Auto-injection means every list table gets
column visibility controls without each plugin
having to wire them up. Plugins can opt out (by
returning fewer columns from `get_columns()` or by
filtering the hidden columns), but the default is
"the controls just work."

### Why the `set-screen-option` filter rather than direct user_meta

Two reasons compound:

- **Validation chokepoint.** A filter forces
  plugins to confirm the value is acceptable
  before it persists. Returning false means
  "don't save"; returning a sanitized value means
  "save this version." Direct user_meta writes
  would skip this step.
- **Capability checking happens here.** If a custom
  preference should be admin-only, the filter is
  where the capability check lives:

  ```php
  add_filter( 'set_screen_option_my_admin_toggle', function( $status, $option, $value ) {
      if ( ! current_user_can( 'manage_options' ) ) {
          return false;  // reject save
      }
      return $value;
  }, 10, 3 );
  ```

The filter is the seam where preference setting
*can* intersect permission checking — but only
because the plugin chose to wire it that way. The
mechanism does not impose this; it offers the
hook.

### Why preference and permission are kept structurally distinct

Conflating them would mean:

- Capability changes would have to invalidate
  preferences (revoking permission would need to
  un-set the preference too).
- Preference state would become a security
  concern (an attacker who flipped a preference
  could expose sensitive data).
- Auditing what "this user can see" would require
  joining capability state and preference state —
  two distinct mental models in one query.

Keeping them separate means each layer has one
job: capability decides what is *allowed*;
preference decides what is *displayed*. The two
compose at render time but answer different
questions.

## WHEN NOT

Skip Screen Options if:

- The preference is **inherently site-wide** (not
  per-operator). Use the Settings API and
  `get_option`; that's the cross-user mechanism.
- The toggle controls **what an operator can do**,
  not what they see. That's a capability concern;
  use roles/capabilities, not screen options.
- The screen has **no user interaction** (a static
  display, a CLI report). Screen Options requires
  a `WP_Screen` and a logged-in user context.
- You want **rich custom UI** in the Screen Options
  panel (multi-step wizards, complex layouts).
  The panel is designed for simple toggles and
  inputs; rich UI belongs elsewhere on the page or
  in a settings screen.

## COUNTER-PATTERNS

### Anti-pattern 1 — Treating hidden columns as forbidden columns

```php
public function column_secret( $item ) {
    if ( in_array( 'secret', get_hidden_columns( get_current_screen() ), true ) ) {
        return '';  // user "isn't supposed to see this"
    }
    return esc_html( $item['secret'] );
}
```

Hidden columns is a *display preference*. The
operator can untoggle it instantly. If `secret`
should be access-controlled, gate by capability:

```php
public function column_secret( $item ) {
    if ( ! current_user_can( 'view_secrets' ) ) {
        return '';
    }
    return esc_html( $item['secret'] );
}
```

(This is the same anti-pattern called out in
`admin-ui.list-tables` Anti-pattern 4. It belongs
in both chunks because the temptation appears in
both places.)

### Anti-pattern 2 — Calling `add_screen_option` outside `load-{$page_hook}`

```php
add_action( 'admin_init', function() {
    add_screen_option( 'per_page', […] );  // wrong hook
} );
```

`admin_init` fires on every admin request; the
current screen at that point may not be the page
you're configuring. The option will be registered
on the wrong screen (or no screen). Use the
specific page-load hook:

```php
add_action( 'load-' . $my_page_hook, function() {
    add_screen_option( 'per_page', […] );
} );
```

### Anti-pattern 3 — Custom option without `set-screen-option` filter

```php
add_action( 'load-' . $my_hook, function() {
    add_screen_option( 'my_custom_toggle', […] );
} );
// Operator submits the form. Value is not saved.
```

WordPress doesn't know how to save custom options
without the filter. Add it:

```php
add_filter( 'set_screen_option_my_custom_toggle', function( $status, $option, $value ) {
    return $value;
}, 10, 3 );
```

### Anti-pattern 4 — Reading screen options before user is set

```php
$per_page = get_user_meta( get_current_user_id(), 'my_per_page', true );
// In a context with no current user, get_current_user_id() returns 0.
// User meta read with user_id 0 returns nothing meaningful.
```

Confirm there's a logged-in user context before
reading. In normal admin flow this is guaranteed;
in CLI or cron contexts it isn't.

### Anti-pattern 5 — Storing global state in screen options

```php
add_filter( 'set_screen_option_site_wide_setting', function( $status, $option, $value ) {
    update_option( 'site_wide_setting', $value );  // wrong layer
    return false;
}, 10, 3 );
```

If the value is site-wide, store it as an option
(Settings API) and surface it in a settings page,
not in Screen Options. Screen Options is the
per-user layer; using it for site-wide state means
each operator's submission overwrites the global,
or the global appears differently to different
operators. Use the right substrate.

### Anti-pattern 6 — Auto-revealing hidden columns based on policy

```php
// Hook that "helps" by un-hiding security-relevant columns
add_filter( 'manage_my_screen_columns_hidden', function( $hidden ) {
    if ( current_user_can( 'view_secrets' ) ) {
        return array_diff( $hidden, array( 'secret' ) );
    }
    return $hidden;
} );
```

This overrides the operator's preference based on
their permission. It conflates the two layers in
the wrong direction: permission shouldn't *change
the preference* either. The right shape is to let
the operator see the column toggle and choose; the
column itself only renders when capability permits.

## OPERATIONAL NOTES

The screen-options surface's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *per-user* form. The screen
  option is *declared* by `add_screen_option()` on
  page load (system-level declaration); it is
  *exposed* (rendered, set, persisted, read) on a
  per-user basis through user meta. Two layered
  Law 1 instances, parallel to the
  `WP_List_Table` chunk's column declaration vs
  reachable column distinction. Naming Law 1 here
  is genuinely clarifying because the *gap* between
  "the preference is registered" and "this
  operator's preference value is in effect" is
  per-user, not system-wide.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly*. The user-meta key persists as the
  storage continuity surface across sessions; the
  same operator returns to the same screen and
  sees their preferences re-applied. Worth one
  mention; not a section.

What this chunk is **not** about:

- **Doctrine 6 (Authority Mediation).** *The most
  important non-fit to name precisely* in this
  terrain. Screen options *look* mediation-shaped
  — operator chooses what to see, the framework
  controls what gets rendered. But this is
  **preference, not permission**. The framework
  does not enforce restrictions through screen
  options; the operator's choice can be inverted
  at any time. Mediation requires *authority over
  reach*; preference is *configuration of view*.
  Naming Doctrine 6 here would conflate
  personalization with governance and dilute
  Doctrine 6's specific access-control meaning.
  The boundary between this chunk and the
  capability layer (`plugin-dev.capabilities-and-roles`)
  is exactly where preference ends and permission
  begins.
- **Law 4 (Arbitration Compiler).** No candidate
  selection. Operator picks one per-page value;
  operator picks which columns to hide; the
  framework applies those choices. There is no
  ladder, no priority decision, no first-match-
  wins. Each preference is a *direct setter* for
  one user-meta value. Omitted.
- **Federation.** *Explicit non-fit.* Per-user
  state stored in user meta is not federation.
  There are not multiple participants federated
  around a shared registry — there are many
  individual users, each with their own slice of
  user meta, no cross-user sharing or
  coordination. (Same explicit non-fit as the
  list-tables chunk's screen-options reasoning;
  pinned again here because the temptation
  recurs.)
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** All preference reads and writes
  happen in the same PHP runtime as the page
  handler. No runtime boundary. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** No build /
  runtime split. Omitted.
- **Section X archetypes.** A per-user preference
  layer is not a "civilization." Same framework-
  omission discipline as the surrounding chunks.
  Omitted.

A literacy contribution worth pinning, central to
this chunk and surfacing in the chunk title:

> *Preference ≠ permission.* A per-user setting
> that controls what an operator *sees* is
> structurally distinct from a per-role
> capability that controls what an operator *can
> do*. Both shape the operator's experience, but
> they live at different layers, persist in
> different places, and answer different
> questions. Conflating them — using preference
> as a security gate, or making capability
> changes silently rewrite preferences — produces
> bugs that are hard to catch precisely because
> the two states often happen to align.

This pairs with the previous admin chunk's
*"Registered surface ≠ reachable surface"* to
form an *admin governance layer pair*:

> *In the multi-gate reachability cascade
> (`admin-ui.list-tables`), some gates are
> preference (this chunk) and others are
> permission (`plugin-dev.capabilities-and-roles`).
> The two kinds of gate compose into the operator's
> reachable surface — but they remain
> structurally distinct, with different change
> mechanics and different security implications.*

The pair extends the existence-vs-operation toolkit
into a *layered governance* form: existence
(declaration) → preference layer (per-user
modulation) → permission layer (per-role gating)
→ operation (what the operator can actually do
right now).

## CHECKLIST

When using Screen Options:

- [ ] Register options in `load-{$page_hook}` —
      the only hook where the right `WP_Screen` is
      current.
- [ ] For `per_page` and `layout_columns`, no
      custom save filter is needed; for any other
      option, register a `set_screen_option_{$option}`
      filter that returns the value to save (or
      `false` to reject).
- [ ] Read preferences via `get_user_meta` (with a
      sane default) or via the `WP_List_Table`
      helper `get_items_per_page` for per-page.
- [ ] For hidden columns, read with
      `get_hidden_columns( get_current_screen() )`.
- [ ] **Never** use screen-options state as a
      security gate. Hidden columns are
      preference; capability checks are permission.
- [ ] If a custom option should be capability-
      gated *for who can save it*, do that check
      inside the `set-screen-option` filter, not
      in render code.
- [ ] Don't use Screen Options for site-wide
      state. Use the Settings API instead.

## REFERENCES

- `add_screen_option()` reference. Documents the
  registration call and recognized option types.
  https://developer.wordpress.org/reference/functions/add_screen_option/
- `WP_Screen` class reference. Documents
  `get_option()`, screen meta, help tabs (sibling
  surface).
  https://developer.wordpress.org/reference/classes/wp_screen/
- `set-screen-option` / `set_screen_option_{$option}`
  filter reference. Documents the custom-option
  save handshake.
  https://developer.wordpress.org/reference/hooks/set-screen-option/
- `get_hidden_columns()` reference. Returns
  current-user hidden columns for a screen.
  https://developer.wordpress.org/reference/functions/get_hidden_columns/
- `get_user_meta()` reference. The storage layer
  screen options ultimately read/write through.
  https://developer.wordpress.org/reference/functions/get_user_meta/

Cross-context:

- `admin-ui.list-tables` — the most common
  consumer of screen options. The preference
  layer this chunk documents modulates the
  reachability cascade that chunk maps. Together
  they form the *institutional display + per-user
  personalization pair*.
- `admin-ui.settings-api` — the *site-wide*
  settings counterpart. Settings API for
  cross-user state; Screen Options for per-user
  state. Different layers, different persistence,
  different audiences.
- `admin-ui.admin-menus` — the page registration
  that produces the `$page_hook` Screen Options
  hangs `load-{$hook}` registration off of.
- `plugin-dev.capabilities-and-roles` — the
  permission layer that lives *outside* this
  chunk. Preference / permission are distinct
  axes; this chunk is preference, that one is
  permission.
