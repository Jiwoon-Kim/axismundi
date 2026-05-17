---
rule_id: editor-customization.editor-preferences-store
domain: editor-customization
topic: editor-runtime-personalization
field_cluster: preferences-store-substrate
wp_min: "5.5"
wp_recommended: "6.5+"
package_min: "@wordpress/preferences@^4"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-preferences/
    section: "@wordpress/preferences — store actions, selectors, persistence"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/preferences/README.md
    section: "@wordpress/preferences README — current API surface"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2024/02/27/editor-preferences-system-improvements-in-6-5/
    section: "WP 6.5 — wp_persisted_preferences user meta + REST persistence layer"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/components/preferences-modal/
    section: "PreferencesModal — UI surface for editor preferences"
    captured: 2026-05-10
related:
  - admin-ui.screen-options                       # admin-side parallel; this chunk is the editor-runtime variant
  - data-layer.wp-data-registry                  # the data store this preferences store lives within
  - plugin-dev.capabilities-and-roles            # the layer this chunk's preferences are explicitly NOT
  - admin-ui.dashboard-widgets                   # parallel preference vs system distinction
---

# RULE — `@wordpress/preferences` — editor-runtime personalization store

## WHEN

You are reasoning about how the WordPress
post editor or Site Editor remembers operator
preferences (welcome guide visibility,
distraction-free mode, sidebar pinning,
fixed toolbar, custom plugin toggles), where
those preferences live, and how to register
or read them in plugin code. Use this
knowledge when:

- Adding a custom feature toggle to the
  editor's Preferences modal.
- Reading or setting a preference
  programmatically from a plugin.
- Diagnosing "my preference doesn't persist
  across sessions" — almost always: missing
  default registration, wrong scope, or
  REST persistence failure.
- Choosing between
  `@wordpress/preferences` (this chunk),
  Screen Options (admin), and full data
  stores for state that crosses the
  preference / non-preference boundary.
- Understanding why core's preference
  features (welcome guides, distraction-free
  mode, etc.) work the way they do.

This chunk does **not** cover:

- Admin-side Screen Options
  (`add_screen_option`) — covered in
  `admin-ui.screen-options`. Section F pins
  the distinction.
- Capability / role-based access control
  — covered in
  `plugin-dev.capabilities-and-roles`. The
  central conceptual point of this chunk
  (Section G) is that *preferences are not
  capabilities*.
- The general data store mechanism
  (`@wordpress/data`) — covered in
  `data-layer.wp-data-registry`. The
  preferences store is one specific store
  in that registry.
- The PreferencesModal UI component itself
  in detail. This chunk references it as
  the surface where preferences are
  toggled, but doesn't catalog its full
  prop interface.

The principle this chunk operates under:
**The editor's preferences store holds
*per-operator personalization state for
editor UI* — sidebar pinning, panel
visibility, mode toggles. The mechanism is
*personalization governance*, not *access
authorization*. A preference being set or
unset doesn't grant or revoke any
capability; it shapes one operator's
editor view.**

## SHAPE

### A. The preferences store — scopes and features

The store is registered as
`'core/preferences'` in the
`@wordpress/data` registry. Its data shape:

```
{
    scope_a: {
        feature_x: value,
        feature_y: value,
    },
    scope_b: {
        feature_z: value,
    },
    ...
}
```

Two-level namespacing:

- **Scope** — typically `'core/edit-post'`,
  `'core/edit-site'`, or a plugin slug like
  `'myplugin'`. Scopes group related
  preferences.
- **Feature** — the specific preference
  name within a scope (e.g.,
  `'fixedToolbar'`, `'welcomeGuide'`).

A given preference is identified by
`(scope, feature)` as a tuple. Multiple
plugins can register their own scopes
without collision; same feature name in
different scopes is fine.

### B. The core API

```js
import { dispatch, select } from '@wordpress/data';

// Set defaults (typically on plugin load):
dispatch( 'core/preferences' ).setDefaults( 'myplugin', {
    showAdvancedPanel: false,
    autoSaveDraft:     true,
} );

// Read a preference:
const isShown = select( 'core/preferences' ).get( 'myplugin', 'showAdvancedPanel' );

// Set a preference (persists):
dispatch( 'core/preferences' ).set( 'myplugin', 'showAdvancedPanel', true );

// Toggle a boolean preference:
dispatch( 'core/preferences' ).toggle( 'myplugin', 'showAdvancedPanel' );
```

Three properties to pin:

- **`setDefaults` is non-persistent.**
  Defaults are runtime-only — they don't
  write to user meta. They're the value
  returned when the operator hasn't
  explicitly set the preference.
- **`set` and `toggle` persist.** When the
  operator (or programmatic code) calls
  `set` or `toggle`, the new value is
  persisted (Section C) and survives
  across editor sessions.
- **Reads are reactive.** When used inside
  `useSelect` (or any `@wordpress/data`
  subscriber), components re-render when
  preferences change, allowing live UI
  response to preference toggles.

### C. The persistence layer — REST + user meta

WP 6.5+ persists editor preferences through
the REST API to a single user-meta entry:

```
user_meta key: wp_persisted_preferences
user_meta value: {
    "version": 1,
    "core/edit-post": { "fixedToolbar": true, … },
    "core/edit-site": { "welcomeGuide": false, … },
    "myplugin":       { "showAdvancedPanel": true }
}
```

The lifecycle:

```
1. Editor JS calls dispatch.set(scope, feature, value).
2. Data store updates in-memory state immediately.
3. Persistence layer (debounced) POSTs to REST endpoint
   /wp/v2/users/me?meta=wp_persisted_preferences with the
   updated JSON.
4. Server validates capability ('edit_user' on the user) and
   updates user_meta.
5. Next editor session: editor JS reads user meta on bootstrap;
   preference store hydrates from the JSON.
```

Three properties:

- **All preferences for a user share one
  user_meta entry.** Updates POST the full
  preference set, not deltas. The REST
  endpoint replaces the entire JSON blob.
- **Persistence is debounced.** Toggling a
  preference rapidly (e.g., quickly
  pinning/unpinning a sidebar) doesn't
  generate one REST call per toggle — the
  persistence layer batches changes within
  a short window.
- **Pre-WP-6.5 used localStorage.** The
  REST migration moved persistence
  server-side so preferences sync across
  browsers/devices. localStorage remains as
  a fallback for unauthenticated contexts.

### D. Built-in editor preferences

Core registers preferences in the
`'core/edit-post'` and `'core/edit-site'`
scopes. A representative sample:

| Scope                | Feature                        | Effect                                         |
| -------------------- | ------------------------------ | ---------------------------------------------- |
| `core/edit-post`     | `fixedToolbar`                 | Pins block toolbar to top of editor frame      |
| `core/edit-post`     | `welcomeGuide`                 | First-time welcome modal visibility            |
| `core/edit-post`     | `distractionFree`              | Minimized UI mode                              |
| `core/edit-post`     | `showIconLabels`               | Show icon button labels in inserter / toolbars |
| `core/edit-post`     | `keepCaretInsideBlock`         | Caret behavior at block boundaries             |
| `core/edit-site`     | `welcomeGuide`                 | Site Editor's first-use modal                  |
| `core/edit-site`     | `welcomeGuideStyles`           | Global Styles welcome modal                    |
| `core/edit-post`     | `mostUsedBlocks`               | Inserter "most used" section visibility        |

The full list evolves per WP version; the
table is illustrative, not exhaustive.

Three properties of these built-ins worth
noting:

- **All are per-operator.** Each user has
  their own `wp_persisted_preferences`
  meta entry. Operator A's distraction-free
  mode doesn't affect operator B.
- **None affect site-wide behavior.** A
  pinned toolbar is just *this operator's*
  pinning preference; the published
  content, the site's permissions, the
  block schema — none change.
- **Toggling a preference is the action;
  the action is its own reward.** No
  side effects on permissions, no
  cross-user notifications, no impact on
  data the operator can access.

### E. Custom preference scopes

Plugins can register their own scopes to
hold plugin-specific preferences:

```js
import { dispatch } from '@wordpress/data';
import { domReady } from '@wordpress/dom-ready';

domReady( () => {
    dispatch( 'core/preferences' ).setDefaults( 'myplugin', {
        autoExpandPanel:    true,
        showHints:          false,
        compactMode:        false,
    } );
} );
```

Then use them:

```js
import { useSelect, useDispatch } from '@wordpress/data';

function MyPanel() {
    const isExpanded = useSelect(
        ( s ) => s( 'core/preferences' ).get( 'myplugin', 'autoExpandPanel' ),
        []
    );
    const { toggle } = useDispatch( 'core/preferences' );

    return (
        <Panel
            initialOpen={ isExpanded }
            onToggle={ () => toggle( 'myplugin', 'autoExpandPanel' ) }
        />
    );
}
```

The custom scope automatically benefits from
the preferences store's persistence — values
survive sessions without the plugin
implementing storage itself.

### F. Distinction from Screen Options (admin-side parallel)

Screen Options (`add_screen_option`,
covered in `admin-ui.screen-options`) and
the editor preferences store are *parallel
mechanisms in different runtimes*:

| Aspect              | Screen Options (admin)                 | `@wordpress/preferences` (editor)        |
| ------------------- | -------------------------------------- | ---------------------------------------- |
| Runtime             | wp-admin PHP request                   | Editor browser-side JS                    |
| UI surface          | Top-right panel on admin pages         | "Preferences" modal in editor            |
| Storage layer       | Per-screen user meta (e.g., `manage{$screen->id}columnshidden`) | Single user meta `wp_persisted_preferences` |
| Persistence pathway | Form POST to admin-post.php            | REST API to `/wp/v2/users/me`             |
| Data shape          | Per-screen, per-operator               | Per-scope, per-feature, per-operator     |
| Reactive updates    | Page reload required                   | Reactive store; live UI updates          |

Both mechanisms are **personalization
layers**, not authorization layers. Both
sit at the same conceptual layer:
*per-operator UI customization*.

The distinction matters because both look
similar in operator-facing surface ("a
panel I can toggle to change my view")
while differing structurally in runtime,
storage, and reactivity. A plugin moving
between admin pages and the editor needs
to use the right mechanism for the
runtime.

The literacy: **same governance role
(personalization), different runtime
implementations**. The KB now has two
chunks documenting parallel personalization
mechanisms in different runtimes — a
recurring pattern (governance shapes that
manifest differently per runtime context).

### G. Distinction from capability / permission systems

The chunk's central conceptual move:
**editor preferences are not capabilities**.

| Action                                     | Preference layer                        | Capability layer                          |
| ------------------------------------------ | --------------------------------------- | ----------------------------------------- |
| Hide welcome guide                         | `set('core/edit-post', 'welcomeGuide', false)` | (not a capability)                  |
| Pin sidebar                                | `set('core/edit-post', 'fixedToolbar', true)` | (not a capability)                  |
| Restrict access to publish posts           | (not a preference)                      | `current_user_can('publish_posts')`        |
| Restrict access to edit other users' posts | (not a preference)                      | `current_user_can('edit_others_posts')`    |

Preferences and capabilities answer
different questions:

- **Preferences**: *how does this operator
  want their view to look?*
- **Capabilities**: *what is this operator
  permitted to do?*

A hidden welcome guide is *not* a forbidden
welcome guide — the operator can re-enable
it from the Preferences modal at any time.
A pinned sidebar is *not* an institutionally
policed sidebar — it's just visible
differently for this operator.

The two mechanisms can compose (a plugin
might check capability before showing a
custom preference toggle), but they are
structurally distinct layers.

This is the most important Doctrine 6
non-fit observation in the chunk: surface
vocabulary about "controls" and "settings"
and "options" tempts a Doctrine 6 reading;
the underlying mechanism is
personalization, not mediation. Same
governance vocabulary; different mechanism.

## WHY

### Why a separate preferences store rather than ad-hoc state

Without `@wordpress/preferences`, every
plugin wanting per-operator UI state would
implement its own:

- Choose its own storage (localStorage?
  user meta? options?).
- Implement its own persistence pathway.
- Define its own scope conventions.
- Risk collision with other plugins.

The shared store gives every plugin the
same surface: register defaults, get/set
through the data layer, persistence
handled centrally. Operators get a unified
Preferences modal surface; plugins get
unified persistence guarantees.

### Why per-operator rather than site-wide

Editor UI customization is operator-
specific by nature. One editor wants a
fixed toolbar; another wants it to scroll
with content. One wants the welcome guide
on every visit; another wants it
permanently hidden. Site-wide settings
would force one operator's preference on
all operators.

The cost is per-operator user meta
storage; the benefit is each operator
gets their own ergonomics without
overriding others'.

### Why the persistence layer batches REST calls

Operators toggle preferences rapidly
during exploratory editor use — testing
distraction-free mode, comparing toolbar
positions, etc. Per-toggle REST calls
would mean dozens of HTTP requests for a
single preference exploration session.

Debouncing batches these into one REST
call after a short window of inactivity.
The cost is preferences may not appear
on a parallel editor session for a
fraction of a second; the benefit is
proportional REST traffic.

### Why preferences and capabilities are kept structurally distinct

A unified mechanism would force the
question "is this a preference or a
permission?" to be re-answered at every
configuration choice. Many UI toggles
genuinely *are* preferences (visual mode);
many genuinely *are* permissions (publish
ability). Mixing them at the same layer
would mean treating "show welcome guide"
and "publish posts" as the same kind of
thing — confusingly different in their
revocation costs and security
implications.

Keeping them at separate layers means
each layer has one job: preferences shape
view; capabilities gate action.

## WHEN NOT

Skip the editor preferences store if:

- The state is **per-document** (not
  per-operator). Document-scoped state
  belongs to the document (block
  attributes, post meta), not to operator
  preferences.
- The state is **temporary** (cleared at
  end of session). Use component-local
  React state or transient data; no
  persistence needed.
- The state is **a capability**. Use
  `current_user_can()` and the
  capability-and-roles system; preferences
  don't enforce.
- The state is **admin-side** (post
  list table column visibility, etc.).
  Use `add_screen_option`; the editor
  preferences store doesn't apply outside
  the editor.
- You need **server-side reading** of the
  preference. The store is editor-runtime;
  PHP reads `wp_persisted_preferences`
  user meta directly if needed (rare —
  preferences are typically editor-only
  signals).

## COUNTER-PATTERNS

### Anti-pattern 1 — Treating preferences as capabilities

```js
const canSeeAdvanced = useSelect( ( s ) =>
    s( 'core/preferences' ).get( 'myplugin', 'showAdvanced' ),
    []
);

if ( canSeeAdvanced ) {
    // Show advanced functionality, treating preference as authorization.
    renderSensitiveData();
}
```

The preference being true means "this
operator wants to see advanced UI" — not
"this operator is permitted to access
advanced data." Operators can flip the
preference at any time. Use capability
checks for actual authorization:

```js
const canAccessAdvanced = useSelect( ( s ) =>
    s( 'core' ).canUser( 'create', 'myplugin/advanced' ),
    []
);

if ( canAccessAdvanced && showsAdvancedUI ) {
    renderSensitiveData();
}
```

### Anti-pattern 2 — Setting preferences for other users

```js
dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
// In some "admin can dismiss for all users" contrived path.
```

The preferences store reads from and
writes to the *current user's* preferences
through the user-me REST endpoint.
There's no way to set preferences for
other users via this API. If you need
site-wide defaults, set them via
`setDefaults` (which is per-session,
runtime-only) or use a different
mechanism (custom REST endpoint that
writes other users' meta with proper
capability check).

### Anti-pattern 3 — Forgetting to call `setDefaults`

```js
const isPanelExpanded = useSelect( ( s ) =>
    s( 'core/preferences' ).get( 'myplugin', 'expandedPanel' ),
    []
);
// Returns undefined for new operators who never set it.
```

Without `setDefaults`, the first read
returns undefined. UI logic gets
ambiguous "is it false or just unset?"
state. Always register defaults:

```js
dispatch( 'core/preferences' ).setDefaults( 'myplugin', {
    expandedPanel: false,  // explicit default
} );
```

### Anti-pattern 4 — Putting non-preference state in the preferences store

```js
// Plugin storing the post being edited:
dispatch( 'core/preferences' ).set( 'myplugin', 'currentEditingId', 42 );
```

The preferences store is for
*personalization*, not for transient or
content state. Document IDs, current
selections, in-progress edits belong in
their own data stores, not in
preferences. Mixing pollutes the
persisted preferences blob with churning
data.

### Anti-pattern 5 — Synchronous reading expecting current value

```js
import { select } from '@wordpress/data';

// Top-level (module init) read:
const value = select( 'core/preferences' ).get( 'myplugin', 'feature' );
// Always undefined or default — preferences hydrate later.
```

Preferences hydrate after editor bootstrap
(REST request to fetch user meta). A
top-level synchronous read fires before
hydration. Use `useSelect` (which
re-renders on hydration) or wait for
editor-ready signals:

```js
import { useSelect } from '@wordpress/data';

function MyComponent() {
    const value = useSelect( ( s ) =>
        s( 'core/preferences' ).get( 'myplugin', 'feature' ),
        []
    );
    return <div>{ value ? 'On' : 'Off' }</div>;
}
```

### Anti-pattern 6 — Ignoring scope conventions

```js
dispatch( 'core/preferences' ).setDefaults( 'feature', { value: true } );
// Generic 'feature' scope — collides with anything else using same name.
```

Use namespaced scopes (`vendor/plugin-name`
or `vendor`). Generic names risk silent
override or read of the wrong values.

## OPERATIONAL NOTES

The preferences store's interpretive shape,
in proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is
  the central fit, in a *defaults vs
  active state* form. The default is
  *declared* via `setDefaults`; the
  current preference value is *exposed*
  through the store's reactive read API.
  Many declared defaults remain at their
  default value forever; the operator
  may explicitly mutate some, leaving
  others untouched. Naming Law 1 here is
  genuinely clarifying because the *gap*
  between "default registered" and
  "actively customized by this operator"
  is real and observable through the
  store.
- **Doctrine 5 (Authority Continuity)**
  appears *lightly*. The
  `(scope, feature)` tuple is the
  identity surface that persists across
  sessions. Same key reads the same
  meaning across editor reloads. Worth
  one mention; not a section.
- **Federation** appears in a *light
  registration-composition* form.
  Multiple plugins call `setDefaults`
  for their own scopes; all
  registrations coexist in the shared
  store. Same pattern family as the
  query_vars chunk's federation-
  composition (Phase 8.41) but
  significantly lighter — the composition
  doesn't combine outputs (each scope is
  independent) so it's closer to
  *federated namespacing* than full
  composition. Worth one mention; not a
  section.

What this chunk is **not** about:

- **Doctrine 6 (Authority Mediation).**
  *The most important non-fit to name
  precisely* — the chunk's central
  doctrinal contribution. The
  preferences store *looks* governance-
  shaped on superficial reading: toggles,
  modes, visibility controls. But:
  - Setting a preference does not grant
    or revoke any capability.
  - Preferences are operator-set; the
    operator can flip them at any time.
  - Hiding UI is not forbidding the
    underlying functionality.
  - There is no enforcement layer; the
    preference is just operator's view.
  This is *personalization*, not
  *authority mediation*. Naming Doctrine
  6 here would conflate UI customization
  with governance — the same conflation
  the screen-options chunk warned about,
  now in editor-runtime terrain. The
  literacy *Preference ≠ permission*
  established at 8.34a applies directly
  here, *in a different runtime*, with
  the same structural discipline.
- **Law 4 (Arbitration Compiler).** No
  candidate selection. Each preference
  is an independent value; toggling one
  doesn't arbitrate against others.
  Multiple preferences coexist (compound
  state, like format types). Omitted.
- **Law 3b (Cross-Runtime Authority
  Continuity Bridge).** *Adjacent and
  explicitly non-fit.* The persistence
  REST roundtrip (editor JS → REST →
  user_meta → next session reads) looks
  bridge-shaped. But no runtime
  authority is preserved across the
  boundary; this is the same pattern as
  the ServerSideRender chunk
  (Phase 8.38) and Global Styles
  persistence (Phase 8.44). Data
  transports across runtimes;
  authority does not. The anti-Law-3b
  inventory's *REST roundtrip with
  persistence* member already covers
  this shape; not added as new
  inventory member.
- **Law 6 (Compiler ↔ Runtime Split).**
  All in editor JS runtime + PHP
  request runtime; not a build /
  runtime split. Omitted.
- **Section X archetypes.** A
  preferences store is not a
  "civilization." Same framework-
  omission discipline. Omitted.

## Two literacy contributions worth pinning

> *Preference state ≠ capability state.*
> A toggle in the editor's preferences
> modal is not the same as a capability
> check in the access control system.
> Setting a preference shapes one
> operator's view; granting a capability
> grants permission to act. Operators
> can flip preferences at any time; they
> cannot grant themselves capabilities.
> A hidden welcome guide is not a
> forbidden welcome guide; a pinned
> toolbar is not an institutional policy.
> The two layers compose in some UIs
> (a plugin may check capability before
> showing a preference toggle) but
> remain structurally distinct.

This contribution adds a *runtime-context
variant* to the preference-vs-permission
literacy established at 8.34a. The KB
now has two chunks documenting
*Preference ≠ permission* in two
different runtime contexts (admin-side
screen options at 8.34a; editor-side
preferences here). The recurring pattern
across both: governance-shaped surfaces
in different runtime contexts can have
similar surface vocabulary while
mechanically being personalization, not
authorization.

> *Editor preference ≠ admin preference.*
> Two parallel personalization mechanisms
> in different runtimes:
> `add_screen_option` (admin-side) and
> `@wordpress/preferences` (editor-side).
> Both serve the same governance role
> (per-operator UI customization), with
> different storage, persistence
> pathways, reactivity, and UI surfaces.
> Choosing the right mechanism depends on
> the runtime context — admin pages use
> Screen Options; editor surfaces use
> the preferences store. The mechanisms
> are *structurally distinct
> implementations of the same role*, not
> interchangeable.

This contribution adds a **cross-runtime-
context asymmetry** observation: governance
roles can manifest through different
mechanisms depending on the runtime that
hosts them. The KB's existing
within-bounded-context asymmetry
observations (block styles vs variations
at 8.42; format types / styles /
variations at 8.43) operated *within one
runtime*. This contribution extends the
observation: *across runtime contexts*
(admin-server vs editor-browser),
governance shapes can also have
asymmetric implementations.

The Federation pattern's variant matrix
already showed registration shapes vary
by mechanism; this observation extends
that recognition to *role implementations
varying by runtime* — same role, different
runtime, different mechanism.

## CHECKLIST

When using `@wordpress/preferences`:

- [ ] Use namespaced scopes
      (`vendor/plugin-name`, not generic
      names).
- [ ] Always call `setDefaults` to declare
      initial values; don't rely on
      undefined-as-default.
- [ ] Use `useSelect` for reactive reads
      so components re-render when
      preferences change.
- [ ] Treat preferences as
      *personalization*, not as
      *capabilities*. Use capability
      checks for actual authorization.
- [ ] Don't store transient or content
      state in the preferences store.
      Use document state, block
      attributes, or component-local
      state.
- [ ] For admin-page parallels, use
      `add_screen_option` instead — they
      serve the same role in different
      runtimes.
- [ ] When debugging "my preference
      isn't persisting," check: is the
      REST endpoint reachable? Is the
      capability check passing? Is the
      scope name consistent?

## REFERENCES

- `@wordpress/preferences` data store
  reference. Documents actions,
  selectors, default semantics.
  https://developer.wordpress.org/block-editor/reference-guides/data/data-core-preferences/
- `@wordpress/preferences` README on
  GitHub.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/preferences/README.md
- WP 6.5 — editor preferences system
  improvements. Documents the
  `wp_persisted_preferences` user-meta
  + REST persistence layer.
  https://make.wordpress.org/core/2024/02/27/editor-preferences-system-improvements-in-6-5/
- PreferencesModal component reference.
  The UI surface for editor preferences.
  https://developer.wordpress.org/block-editor/reference-guides/components/preferences-modal/

Cross-context:

- `admin-ui.screen-options` — the
  admin-side parallel mechanism.
  Together with this chunk, the KB
  documents per-operator personalization
  in two different runtime contexts;
  the cross-runtime-context asymmetry
  observation lives here.
- `data-layer.wp-data-registry` — the
  data store registry the preferences
  store lives within. The preferences
  store is one specific store; the
  registry mechanism is that chunk's
  territory.
- `plugin-dev.capabilities-and-roles`
  — the access-control layer the
  preferences store is *explicitly
  not*. The Doctrine 6 boundary is
  what this chunk's central
  contribution preserves.
- `admin-ui.dashboard-widgets` —
  another *parallel preference vs
  system* distinction (`personal
  dismissal ≠ system deregistration`
  literacy applies in spirit).
