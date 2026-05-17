---
rule_id: multisite.network-admin-and-site-governance
domain: multisite
topic: institutional-jurisdictional-governance
field_cluster: network-and-site-authority-substrate
wp_min: "3.0"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/advanced-administration/multisite/
    section: "Multisite — overview, network admin, site admin"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/is_super_admin/
    section: "is_super_admin() — network-level role check"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/switch_to_blog/
    section: "switch_to_blog() — context switching mid-request"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/get_site_option/
    section: "get_site_option() — network-wide options"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/advanced-administration/multisite/network-admin/
    section: "Network Admin handbook — administration scope"
    captured: 2026-05-10
related:
  - plugin-dev.capabilities-and-roles               # the capability system multisite extends with jurisdiction
  - rest-api.authentication-and-permission-callbacks # Doctrine 6 micro-substrate; this chunk is the macro-jurisdiction layer
  - admin-ui.admin-menus                             # menu registration interacts with network admin scope
  - data-layer.persistence                           # wp_blogs / wp_sitemeta / wp_{blog_id}_* table topology
---

# RULE — Multisite network admin and site governance — institutional jurisdictional layering

## WHEN

You are working on a WordPress Multisite
installation and need to reason about *which
governance layer* a particular concern lives
at — network-wide, per-site, or shared
infrastructure with separate authority.
Use this knowledge when:

- Writing a plugin intended to behave
  correctly on both single-site and
  multisite installations.
- Activating plugins network-wide vs
  per-site, or theme network-enabling vs
  site-activation.
- Implementing capability checks that need
  to respect both site context and
  super-admin status.
- Using `switch_to_blog` for cross-site
  operations and reasoning about which
  site's context applies to which calls.
- Choosing between `get_option` (per-site)
  and `get_site_option` (network-wide).
- Diagnosing "this works on my single-site
  test environment but breaks on multisite"
  — almost always: missing site context,
  wrong capability scope, or wrong options
  table.

This chunk does **not** cover:

- Super-detailed multisite administration
  (network setup, domain mapping, etc.) —
  those are operations topics, not
  governance grammar topics.
- The capability system itself — covered in
  `plugin-dev.capabilities-and-roles`. This
  chunk *layers jurisdiction* on top of
  capability; the capability system itself
  is that chunk's territory.
- WordPress.com / VIP-specific multisite
  conventions. This chunk covers core
  WordPress Multisite.
- Network-level customization beyond
  governance (network themes, network
  pages, etc. that don't affect authority
  semantics).

The principle this chunk operates under: **A
multisite installation distributes
infrastructure (one codebase, shared user
accounts, shared filesystem) across many
sites that retain *separate governance
jurisdictions*. Capability is not a single
yes/no for an identity; it is a yes/no
*scoped to a particular site or to the
network*. The mechanism is *layered
jurisdictional governance*, not flat
capability checking.**

## SHAPE

### A. The institutional split — network and sites

A multisite installation has two
administrative layers:

```
Multisite installation
   │
   ├─ Network (one)
   │   - Network Admin (wp-admin/network/)
   │   - Network options (wp_sitemeta table)
   │   - Network-level user roles (super admin)
   │   - Plugins/themes filesystem (shared)
   │   - User accounts (shared, wp_users + wp_usermeta)
   │
   └─ Sites (many)
       - Site 1 (blog_id=1, typically the main site)
       │   - wp_options (per-site)
       │   - wp_posts, wp_terms (per-site)
       │   - Site Admin (per-site capability scope)
       ├─ Site 2 (blog_id=2)
       │   - wp_2_options, wp_2_posts, wp_2_terms
       │   - Site Admin (own capability scope)
       └─ ...
```

The split has structural consequences:

- **Shared infrastructure**: codebase,
  filesystem, user accounts, network
  options.
- **Separate per-site state**: posts,
  options, taxonomies, comments, capability
  application context.
- **Distinct governance scopes**: super
  admin governs the network; site admin
  governs one site.

The chunk's central observation: **shared
infrastructure does not imply shared
sovereignty**. A plugin available on
every site is not a plugin governed
identically on every site.

### B. Super Admin vs Site Admin — the role layer

Multisite introduces a role above the
single-site Administrator: **Super Admin**.

| Role               | Scope          | Typical capabilities                                         |
| ------------------ | -------------- | ------------------------------------------------------------ |
| **Super Admin**    | Network-wide   | `manage_network`, `manage_sites`, `manage_network_users`, `manage_network_themes`, `manage_network_plugins`, `manage_network_options` |
| **Administrator**  | Per-site       | `manage_options` (for own site), `edit_users` (within site, if granted), site-wide content management |
| **Editor / Author / etc.** | Per-site | Standard WordPress roles, applied per-site                  |

Three properties to pin:

- **Super Admin is *additive* to per-site
  roles.** A user can be super admin AND a
  site editor on multiple sites.
- **Super Admin status is identity-level,
  not site-level.** It applies across all
  sites the user accesses.
- **Site Administrator capabilities differ
  from single-site Administrator.** On
  multisite, site administrators *don't*
  automatically have all single-site admin
  capabilities (e.g., `delete_users`,
  `manage_network_*`). Some capabilities
  are *only* available to super admins.

The check `current_user_can( 'manage_options' )`
on a multisite returns true for site
administrators *of the current site*. A
site admin of Site 2 calling this on Site 1
returns false (they're not admin of Site 1).
A super admin returns true on any site.

This is the **layered jurisdictional
governance** pattern: same capability
check, different answers based on the
combination of *identity + role + current
site context*.

### C. Plugin / theme network-availability vs site-activation

Plugins and themes have a two-layer
activation model on multisite:

**Plugins**:

- **Network active**: super admin
  network-activates the plugin; it runs on
  every site; site admins cannot deactivate.
- **Available for site activation**: super
  admin allows individual sites to activate
  the plugin via Network Admin → Plugins.
- **Site-activated**: site admin (or
  network admin acting in site context)
  activates the plugin for that specific
  site.
- **Not available**: super admin has not
  enabled the plugin for site activation;
  site admins can't see it.

**Themes**:

- **Network enabled**: super admin marks
  the theme as available for sites to
  activate. (No "network active theme"
  equivalent — themes are always
  per-site-activated.)
- **Site-activated**: each site selects its
  active theme from the network-enabled
  list.

Three properties to pin:

- **Network active ≠ shared sovereignty.**
  A network-active plugin runs on every
  site, but its functionality may need to
  respect per-site governance internally.
- **Available ≠ activated.** A plugin can
  be available for site activation without
  any site activating it; the codebase is
  loaded only when activated.
- **Network-only enable ≠ active.** A theme
  can be network-enabled but no site has
  selected it.

These are all classic Law 1
declaration-vs-exposure cascades, layered
across jurisdictional boundaries.

### D. Capability checks across jurisdictions

A capability check on multisite asks an
implicit question: *can this user do this
in this site context?*

```php
// Returns true on the site they're admin of:
current_user_can( 'manage_options' );

// Returns true only for super admin:
current_user_can( 'manage_network' );

// Returns true for super admin, true for site admin
// of the site $blog_id (after switch_to_blog):
switch_to_blog( $blog_id );
current_user_can( 'manage_options' );
restore_current_blog();
```

Three patterns to know:

- **`is_super_admin( $user_id = null )`** —
  network-role check, independent of
  current site.
- **`current_user_can( $capability )`** —
  contextual; uses current site's role
  mapping.
- **`current_user_can_for_blog( $blog_id, $capability )`**
  (WP 5.8+, simpler than switch_to_blog)
  — checks capability for a specific site
  without context switching.

The chunk's recurring shape: *the same user
identity has different capability answers
per site*. This is identity continuity (the
user is the same person) with *capability
discontinuity* (what they can do varies by
site).

### E. `switch_to_blog` — explicit jurisdiction switching

`switch_to_blog($blog_id)` shifts the
current site context for subsequent
operations:

```php
switch_to_blog( $other_site_id );
   // Now: $wpdb prefix points to other site's tables;
   //      get_option() reads other site's options;
   //      current_user_can() applies other site's role mapping;
   //      do_action / apply_filters fire in other site's hook context.

$other_site_options = get_option( 'some_setting' );
$other_site_posts   = get_posts( array( 'post_type' => 'post' ) );

restore_current_blog();
   // Restored: $wpdb back to original; original site context resumed.
```

Three properties:

- **Explicit and reversible.** Always pair
  with `restore_current_blog()` to
  restore. Calling `switch_to_blog`
  multiple times stacks (and
  `restore_current_blog` pops). Forgetting
  to restore is a state leak.
- **Comprehensive context shift.** Almost
  every WP API that depends on current
  site context responds to the switch:
  database, options, capability, hooks,
  current user's role mapping.
- **User identity unchanged.** The current
  user is still the same person; their
  identity persists; only their *role
  mapping for this site* (and thus
  capability answers) changes.

This is a runtime-mutable jurisdiction
boundary. The same code can act in
different jurisdictions over the course of
one request.

### F. Network options vs site options

Two parallel options APIs:

| API                     | Storage                          | Scope         |
| ----------------------- | -------------------------------- | ------------- |
| `get_option(...)`       | `wp_options` (or `wp_{blog_id}_options`) | Per-site      |
| `update_option(...)`    | (same)                           | Per-site      |
| `get_site_option(...)`  | `wp_sitemeta`                    | Network-wide  |
| `update_site_option(...)` | `wp_sitemeta`                  | Network-wide  |

On single-site WordPress, `get_site_option`
falls back to `get_option`. On multisite,
they read from genuinely different
storage.

Plugins authored for both single and
multi-site need to choose carefully:

- "This setting should be network-wide" →
  `get_site_option` / `update_site_option`.
- "This setting is per-site" → `get_option`
  / `update_option`.

The wrong choice on multisite either
fragments site-wide data (using per-site
options for what should be network-wide)
or shares per-site customizations (using
network-wide options for what should be
per-site).

### G. The 4-layer governance topology

Putting it together, multisite has at least
four distinct governance layers:

```
Layer 4 — NETWORK INFRASTRUCTURE (filesystem, codebase)
   - Plugins / themes physically present
   - Codebase available to ALL sites
   - No governance applied at this layer (just presence)
        │
        ▼
Layer 3 — NETWORK GOVERNANCE (super admin)
   - Network-activate plugins, network-enable themes
   - Manage sites, network users, network options
   - Capability: manage_network_*
        │
        ▼
Layer 2 — SITE GOVERNANCE (site admin)
   - Site-activate plugins (if network allows)
   - Select theme (from network-enabled)
   - Manage own site's content, options
   - Capability: manage_options (per-site scope)
        │
        ▼
Layer 1 — PER-USER PER-SITE EXPERIENCE (operator preferences)
   - Screen options (admin)
   - Editor preferences
   - Per-user state in own context
```

Each layer has its own:
- Authority scope.
- Capability vocabulary.
- Storage/persistence.
- Override / inheritance rules.

**The 4 layers are not interchangeable.**
A network-active plugin (Layer 3)
populates infrastructure (Layer 4) and is
available everywhere; a site admin (Layer
2) can't unactivate it; an operator
preference (Layer 1) can't override its
existence.

This is the chunk's central topological
contribution: **governance has shape, and
the shape is layered**. The same
"capability check" question gets
different answers depending on which layer
the question is asked at.

## WHY

### Why the network/site split

A WordPress Multisite installation must
serve two needs simultaneously:

1. **Operational efficiency**: one codebase,
   one filesystem, one set of user accounts
   — easier to maintain, update, secure.
2. **Site autonomy**: each site has its own
   content, settings, theme, plugins
   (where allowed) — not all sites should
   look or behave the same.

These needs conflict at the governance
layer. The split lets infrastructure be
shared while sovereignty remains
distributed. Network admin governs the
shared parts; site admins govern their
sites within the bounds the network
allows.

### Why super admin is identity-level rather than site-level

A super admin needs to manage *across*
sites — adding new sites, removing sites,
inspecting any site's data. If super admin
were just "site admin of Site X" the role
wouldn't generalize.

The identity-level shape lets one super
admin user act on every site, including
sites they're not site-admin of. Their
super-admin status is a property of *who
they are* (network-wide), not *what site
they're acting on*.

### Why `switch_to_blog` exists rather than per-call site parameters

Many operations would need the site
context: `get_post(123)`, `get_option('foo')`,
`get_users()`, etc. Adding a `$blog_id`
parameter to *every* such call would mean
dozens of API additions and break
backwards compatibility.

`switch_to_blog` provides a *context shift*:
all subsequent code runs as if the site
were $blog_id. The cost is the
restore/leak risk; the benefit is no API
explosion.

### Why options have parallel network and per-site APIs

Options model *site-scoped settings* by
default; they live in `wp_options`. Some
settings need to apply to the entire
network (network-wide configuration, super
admin preferences, plugin global state).

Adding network options as a separate API
(`get_site_option` / `wp_sitemeta`) keeps
the per-site default unchanged while
providing network scope when needed. The
naming asymmetry (`get_option` vs
`get_site_option` — the latter ironically
named "site" but meaning "network site"
i.e., the multisite installation as a
whole) is a historical artifact;
mechanically the two are distinct APIs
with distinct storage.

## WHEN NOT

Skip multisite-aware reasoning if:

- The installation is **single-site**.
  Multisite governance layers don't apply;
  there's just per-site governance.
- The plugin or theme is **explicitly
  declared single-site only**. Some
  plugins document themselves as
  incompatible with multisite; users
  should respect that.
- The operation is **purely per-user**
  (preferences, screen options) and
  doesn't depend on site or network
  context.
- The operation is **purely per-request**
  (a single REST request's authentication)
  and the multisite layering is handled
  by core's request bootstrap.

## COUNTER-PATTERNS

### Anti-pattern 1 — Treating super admin status as site-scoped

```php
switch_to_blog( $other_site_id );
if ( is_super_admin() ) {
    // Same answer as before switch — super admin status
    // doesn't change with site context.
}
restore_current_blog();
```

Working as documented; some authors expect
that super admin status would "scope down"
to the switched site. It doesn't. Super
admin is identity-level. If you need to
check "is this user the site admin of *this*
site," use:

```php
switch_to_blog( $other_site_id );
$can = current_user_can( 'manage_options' );
   // True if super admin OR if site admin of this specific site
restore_current_blog();
```

### Anti-pattern 2 — Forgetting `restore_current_blog`

```php
foreach ( $sites as $site ) {
    switch_to_blog( $site->blog_id );
    $count = wp_count_posts()->publish;
    // Missing restore_current_blog() — accumulating switches.
}
```

Each switch stacks; without restoring, the
context becomes wrong (and confused). Always
pair switches with restores:

```php
foreach ( $sites as $site ) {
    switch_to_blog( $site->blog_id );
    $count = wp_count_posts()->publish;
    restore_current_blog();
}
```

### Anti-pattern 3 — Using `get_option` for network-wide settings

```php
update_option( 'mynetwork_global_setting', 'value' );
// On multisite: only stored for current site.
// Other sites won't see it.
```

For network-wide settings, use the network
API:

```php
update_site_option( 'mynetwork_global_setting', 'value' );
get_site_option( 'mynetwork_global_setting' );
```

### Anti-pattern 4 — Capability check that ignores multisite layer

```php
if ( current_user_can( 'install_plugins' ) ) {
    // Plugin install UI exposed.
    // On multisite: install_plugins is a network-only capability!
    // Site admins (without super admin) can't install — UI is misleading.
}
```

`install_plugins` is super-admin-only on
multisite. Either gate by `is_super_admin`
explicitly or test on multisite to ensure
the capability behaves as expected.

### Anti-pattern 5 — Plugin behavior assumes plugin is always network-active

```php
// In plugin's main file:
add_action( 'init', function() {
    if ( ! get_option( 'myplugin_enabled' ) ) {
        // Plugin author assumes per-site option, but plugin
        // might be network-active and the site doesn't have a row.
        update_option( 'myplugin_enabled', '1' );
    }
} );
```

Network-active plugins run on every site
but options are per-site by default.
Initialization that assumes a "first run"
might fire repeatedly across sites. Use
defaults parameter or explicitly check via
`get_site_option` if behavior should be
network-wide:

```php
add_action( 'init', function() {
    $enabled = get_option( 'myplugin_enabled', '1' );  // sane default
    // Or use get_site_option for network-wide enable
} );
```

### Anti-pattern 6 — Hard-coded blog_id assumption

```php
$site_data = get_blog_details( 1 );  // always asking about blog 1
```

`blog_id = 1` is the main site by
convention but not enforced. Use
`get_current_blog_id()` for the active
context, or pass the ID explicitly when
calling for a known target.

## OPERATIONAL NOTES

The multisite governance substrate's
interpretive shape, in proportional v2
vocabulary:

- **Doctrine 6 (Authority Mediation)** is
  **STRONG, in a *layered jurisdictional*
  form**. The Doctrine 6 fit specification
  from 8.47 (Mediates + Decides + Terminates
  + Binds) all apply, with an *additional
  jurisdictional dimension* — the same
  capability check yields different answers
  depending on the site context. Multisite
  *extends* Doctrine 6 with *jurisdiction*
  rather than introducing a new doctrine:
  - **Mediates**: the capability check
    still mediates between request and
    action (per the 8.47 fit).
  - **Decides**: the decision is now
    layered — first establish jurisdiction
    (which site context), then apply
    capability mapping for that jurisdiction.
  - **Terminates**: same as before (denial
    blocks action).
  - **Binds**: same as before (operator
    can't bypass).
  - **+ Jurisdictionally scoped**: the
    same identity has different capabilities
    in different sites.
  This is the **third positive Doctrine 6
  anchor** (after capabilities-and-roles
  and rest-api/permission-callbacks). The
  *jurisdictional dimension* is a grammar
  refinement specific to multisite, not a
  new doctrine.
- **Federation** appears in an
  **institutional / jurisdictional** form —
  a new variant for the family. Multiple
  sites federate around shared
  infrastructure (codebase, users, themes,
  plugins) but each retains separate
  authority. This is *infrastructure-shared
  + governance-distributed* federation —
  distinct from the four variants
  established at Phase 8.M2:
  1. Pure federation (hooks).
  2. Structured-placement (dashboard
     widgets).
  3. Registration-arbitration hybrid
     (rewrite rules).
  4. Registration-composition hybrid
     (query vars).
  5. **Institutional jurisdictional
     federation** (multisite, NEW).
  The 5th variant has a different
  registration shape (sites are
  *infrastructure-distributed*, not
  *registry-registered*) and a different
  resolution shape (each site governs
  itself; no single composition).
- **Law 1 (Declaration ≠ Exposure)** is
  **PRIMARY** in a *layered* form. A
  plugin in the codebase (Layer 4
  declaration) may or may not be
  network-active (Layer 3 declaration);
  may or may not be available for site
  activation (also Layer 3); may or may
  not be site-activated (Layer 2
  exposure). Many declarations at
  upstream layers; many fewer realized at
  downstream layers.
- **Doctrine 5 (Authority Continuity)**
  appears in a **bifurcated** form worth
  pinning. The user identity persists
  across sites (same `wp_users` row, same
  user_id); but their *capability mapping*
  varies per site. This is *identity
  continuity with capability
  discontinuity* — a partial Doctrine 5
  that doesn't extend to all attributes
  of the user.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** No
  candidate selection in multisite
  governance. Each site operates
  independently; no first-match-wins
  between sites. Capability checks at
  each layer are gates, not ladders.
  Omitted.
- **Law 3b (Cross-Runtime Authority
  Continuity Bridge).** *Adjacent and
  explicitly non-fit.* Multisite is
  cross-context (cross-site), but it's
  cross-jurisdiction within one runtime
  (PHP). No runtime boundary crossing;
  no separate runtime contexts that
  bridge. `switch_to_blog` shifts
  context within the same PHP request.
  Omitted.
- **Law 6 (Compiler ↔ Runtime Split).**
  No build / runtime split. Omitted.
- **Section X archetypes.** A
  jurisdictional governance topology is
  *tempting* to label as a "civilization"
  given the institutional scale — but
  the framework-omission discipline
  applies. Multisite is one mechanism
  family within WordPress; archetype
  framing would inflate the mechanism
  into ontological language that obscures
  rather than clarifies. Omitted.

### Doctrine 6 jurisdictional dimension

The chunk's central grammar contribution:
adding a *jurisdictional dimension* to
the Doctrine 6 fit specification from
8.47:

| Original (8.47, REST permission)         | Multisite addition (this chunk)            |
| ---------------------------------------- | ------------------------------------------ |
| Mediates                                 | (same)                                     |
| Decides                                  | (same — but with *jurisdictional input*)   |
| Terminates                               | (same)                                     |
| Binds                                    | (same)                                     |
| + Jurisdictionally scoped                | The decision input includes *which site context*; same identity, different decisions per site |

The 5th criterion isn't a *replacement*
for the 4 from 8.47; it's a *dimension*
that becomes relevant when the substrate
involves multiple jurisdictions. On
single-site, jurisdiction collapses to
"the one site" and the dimension is
trivial. On multisite, the dimension is
load-bearing.

This is the same family of grammar
refinements as the *cross-runtime-context
asymmetry* observation from Phase 8.46
(editor preferences vs admin screen
options): **role implementations vary by
context** — runtime context (8.46) or
jurisdictional context (8.48).

## Three literacy contributions worth pinning

> *Network authority ≠ site authority.* A
> capability granted at the network level
> is not the same as a capability granted
> at the site level. Network-level
> capabilities (`manage_network`,
> `manage_sites`) are super-admin
> exclusive; site-level capabilities
> (`manage_options` for own site) are
> site-admin scope. Same WordPress, same
> user, different jurisdictions —
> different answers.

This contribution adds a *jurisdictional
governance distinction* to the KB. It
parallels (and extends) the
*Authentication ≠ authorization* literacy
from 8.47: multisite adds a *jurisdiction*
dimension to authorization. The scope of
authority is now part of the question.

> *Available network resource ≠ locally
> activated resource.* A plugin in the
> codebase, a theme network-enabled, a
> capability technically supported — none
> of these is the same as the resource
> being *active in a particular site
> context*. Many resources are available
> network-wide; many fewer are activated
> per site. The Layer 4 → Layer 3 → Layer
> 2 cascade is the multisite expression
> of the registered-X ≠ effective-X
> pattern.

This contribution adds a *4-layer
governance topology* form to the
existence-vs-operation toolkit.
Multisite makes the layers explicit;
single-site collapses them but the
distinctions still apply.

> *Identity continuity with capability
> discontinuity.* The same user identity
> persists across sites (same user_id,
> same name, same email); their
> capabilities vary per site. This is a
> *bifurcated Doctrine 5*: identity is
> preserved but not all attributes of the
> identity are. The continuity
> the doctrine names attaches to *what
> persists* (identity); the discontinuity
> attaches to *what shifts* (capability
> mapping).

This contribution sharpens Doctrine 5's
boundary by pinning a case where
continuity is *partial*. Future chunks
encountering similar partial-continuity
cases can reference this as a precedent:
*continuity attaches to specific
attributes; partial continuity is a
legitimate form*.

## CHECKLIST

When working with multisite governance:

- [ ] Identify which jurisdiction your code
      operates at: network, site, or
      shared infrastructure.
- [ ] Use `is_super_admin()` for
      identity-level role checks;
      `current_user_can()` for contextual
      capability.
- [ ] Use `switch_to_blog` carefully —
      always pair with `restore_current_blog`.
- [ ] Choose between `get_option` (per-site)
      and `get_site_option` (network-wide)
      based on the setting's intended
      scope.
- [ ] When testing plugin behavior on
      multisite, test both single-site
      and multisite installations to
      catch jurisdictional issues.
- [ ] For network-active plugins, audit
      whether per-site initialization runs
      correctly across multiple sites.
- [ ] Don't hard-code `blog_id = 1`; use
      `get_current_blog_id()` or
      explicit parameters.
- [ ] When `current_user_can_for_blog`
      is available (WP 5.8+), prefer it
      over `switch_to_blog` for simple
      capability checks on a target site.

## REFERENCES

- Multisite handbook overview.
  https://developer.wordpress.org/advanced-administration/multisite/
- `is_super_admin()` reference.
  https://developer.wordpress.org/reference/functions/is_super_admin/
- `switch_to_blog()` reference.
  https://developer.wordpress.org/reference/functions/switch_to_blog/
- `get_site_option()` reference. The
  network-options API.
  https://developer.wordpress.org/reference/functions/get_site_option/
- Network Admin handbook. Detailed
  super-admin governance.
  https://developer.wordpress.org/advanced-administration/multisite/network-admin/

Cross-context:

- `plugin-dev.capabilities-and-roles` —
  the capability system multisite
  *extends* with jurisdiction. Together,
  these two chunks cover the *flat
  capability* + *jurisdictional
  capability* full picture.
- `rest-api.authentication-and-permission-callbacks`
  — the *micro-substrate* of Doctrine 6
  (per-request gating). This chunk is
  the *macro-jurisdictional* layer.
  Together they form the Doctrine 6
  positive anchor *family*: 4-criterion
  fit specification (8.47) + jurisdictional
  dimension (this chunk).
- `admin-ui.admin-menus` — admin menu
  registration interacts with network
  admin scope; the jurisdiction matters
  for which menu shows where.
- `data-layer.persistence` — the
  `wp_blogs` / `wp_sitemeta` /
  `wp_{blog_id}_*` table topology that
  the storage layer here consumes.
