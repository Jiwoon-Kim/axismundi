---
rule_id: admin-ui.admin-menus
domain: admin-ui
topic: routing-topology-mediation
field_cluster: governance-surfaces
wp_min: "1.5"
wp_recommended: "5.0+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/plugins/administration-menus/
    section: "Administration Menus — top-level + submenu pages"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/add_menu_page/
    section: "add_menu_page() — top-level admin menu registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/add_submenu_page/
    section: "add_submenu_page() — submenu registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/hooks/admin_menu/
    section: "admin_menu hook — registration timing"
    captured: 2026-05-09
related:
  - admin-ui.settings-api                          # paired chunk — Mediation density build within admin-ui
  - editor-customization.slotfills                 # injection-topology counterpart (DIFFERENT topology character)
  - plugin-dev.security-boundaries                 # 3-tier security model directly applied
  - plugin-dev.capabilities-and-roles              # capability constitution at every menu boundary
  - plugin-dev.register-block-bindings-source      # federation pattern (plugins federate menus into admin)
  - _meta.structural-patterns                      # Phase 7.5 patched spec applied; 2nd promotion event documented
---

# RULE — Admin Menus — capability-routed administrative interface topology

## WHEN

A plugin or theme needs to register administrative interface
entries — top-level menu items, submenus under existing menus
(plugin / settings / tools / users / appearance), or
reorganize menu placement.

Use Admin Menus when:
- Adding plugin admin pages requiring custom navigation entries.
- Adding submenu items under existing admin menus.
- Implementing role-specific admin navigation
  (capability-gated visibility).
- Reordering or renaming admin menu items.

This is the **second admin-ui chunk** and the **second
substantive chunk authored under Phase 7.5 patched spec**.
Strategic role: **Mediation density test within admin-ui** —
if admin menus exhibit mediation character similar to settings-
api, Authority Mediation Surface candidate gets promoted Local
→ Recurring (intra-context).

The doctrinal extension this chunk tests:

> **Settings API governs administrative persistence mediation;**
> **Admin Menus test whether administrative authority also**
> **recurs through hierarchical topology governance and**
> **capability-routed interface constitution.**

The chunk's primary work: honest evaluation of admin menus'
governance character — single-pattern (pure mediation /
pure topology) or hybrid (mediation + topology + arbitration
overlap) bounded-context test.

> **This chunk should determine whether administrative**
> **authority is merely exposed through menus — or**
> **constitutionally routed through hierarchical**
> **capability-governed navigability systems.**

## SHAPE

### A. Top-level menu registration

```php
add_menu_page(
    __( 'My Plugin', 'my-plugin' ),         // page_title (browser tab)
    __( 'My Plugin', 'my-plugin' ),         // menu_title (sidebar text)
    'manage_options',                       // capability gate
    'my-plugin',                            // menu_slug (URL fragment)
    'my_plugin_render_admin_page',          // render callback
    'dashicons-admin-plugins',              // icon
    25                                      // position (numeric)
);
```

| component | role |
|---|---|
| `$page_title` | browser title for the page |
| `$menu_title` | text shown in admin sidebar |
| `$capability` | capability required to see + access menu |
| `$menu_slug` | URL slug + identifier for submenus |
| `$callback` | render function for page content |
| `$icon_url` | dashicon class or URL |
| `$position` | numeric ordering hint (collisions possible) |

### B. Submenu registration

```php
add_submenu_page(
    'my-plugin',                            // parent_slug
    __( 'Settings', 'my-plugin' ),          // page_title
    __( 'Settings', 'my-plugin' ),          // menu_title
    'manage_options',                       // capability
    'my-plugin-settings',                   // menu_slug
    'my_plugin_settings_render',            // callback
    null                                    // position (optional)
);

// Convenience wrappers for core submenus:
add_options_page( ... );    // → Settings menu
add_management_page( ... ); // → Tools menu
add_theme_page( ... );      // → Appearance menu
add_plugins_page( ... );    // → Plugins menu
add_users_page( ... );      // → Users menu
add_dashboard_page( ... );  // → Dashboard menu
add_posts_page( ... );      // → Posts menu
add_media_page( ... );      // → Media menu
```

The `parent_slug` parameter establishes **navigation
hierarchy** — submenus appear nested under their parent.
Convenience wrappers target core menus by slug; custom plugins
typically use add_submenu_page with custom parent or core
parent slugs.

### C. Registration timing and capability gating

```php
add_action( 'admin_menu', function () {
    add_menu_page( ... );
});

// Multisite network admin
add_action( 'network_admin_menu', function () {
    add_menu_page( ... );
});

// In render callback — re-check capability
function my_plugin_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Access denied.', 'my-plugin' ) );
    }
    // ... page content
}
```

Capability checks happen at **multiple boundaries**:
1. Menu registration capability (visibility gate)
2. WordPress core's permission check on page request
3. Render callback's explicit re-check (defense in depth)
4. Form submission boundary (settings_fields, custom forms)

### D. Menu ordering arbitration

```php
// Custom menu order via filter
add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', function ( $menu_order ) {
    // Reorder by manipulating $menu_order array
    return [
        'index.php',
        'my-plugin',
        'edit.php',
        // ... rest in custom order
    ];
});
```

Multiple plugins competing for menu position create
**arbitration scenarios**:
- `add_menu_page`'s `$position` parameter is a HINT, not
  guarantee.
- Numeric position collisions: WordPress falls back to
  alphabetical or registration order.
- `menu_order` filter provides explicit override.
- Plugin update + reorder + new plugin install creates
  ordering churn.

### E. Failure surfaces — navigation debt

```
Admin menu governance failure modes:

- menu sprawl: too many top-level menus clutter admin sidebar
- hidden capability mismatch: parent capability hides child
  even when child intent allows broader access
- orphaned submenu authority: parent removed but submenu
  registration persists with broken navigation
- order collision: multiple plugins target same position
- capability drift: custom roles / capability changes
  invalidate visibility logic
- admin-only assumption: menu visible to authorized users only
  (user with edit_posts may NOT see manage_options menus,
  triggering "where's the menu?" confusion)
- plugin deactivation: menu disappears but bookmarks remain
- network admin separation: site admin menu ≠ network admin
  menu; multisite-aware code needed
```

**Navigation debt** (6th debt-pattern instance in KB; 2nd
in admin-ui):

| debt mode | symptom |
|---|---|
| menu sprawl | accumulated top-level menus past their feature lifecycle |
| capability mismatch | parent/child capability misalignment hides legitimate access |
| order collision | plugin ordering competition produces inconsistent UX |
| orphaned navigation | parent removed but child persists |
| network divergence | multisite site/network menu inconsistency |

The 6-instance debt recurrence (security / interception /
topology / reactive / settings / **navigation**) further
strengthens "governance debt" as anticipated meta-pattern.

## REQUIRES

- Registration on `admin_menu` action (or `network_admin_menu`
  for multisite network admin).
- `$capability` argument MUST be a valid capability string
  (or 'do_not_allow' to hide).
- `$menu_slug` MUST be unique across all registered menus
  (collisions silently take last registration).
- For submenu: `$parent_slug` MUST exist when add_submenu_page
  runs (registration order matters within admin_menu hook).
- Render callback should explicitly re-check capability for
  defense in depth.
- For custom menu order: both filters required (custom_menu_order
  enable + menu_order modify).
- ⚠ Specific behaviors: position collision resolution, hidden
  parent + visible child semantics, capability cache during
  admin render, menu icon dashicon vs URL behavior, multisite
  site/network admin separation, plugin deactivation cleanup —
  verification-needed.

## INVARIANTS

### 1. Admin menus are capability-routed administrative interface topology, NOT decorative navigation

The load-bearing reframing:

> Admin menus are NOT "ways to add navigation entries." They
> are **capability-routed interface topology** — hierarchical
> navigation structure where each menu entry is a
> CAPABILITY-GATED authority routing decision.

Each menu entry encodes:
- A page (admin authority surface)
- A capability gate (who may navigate to it)
- A position in the navigation tree (where in hierarchy)
- A render callback (what authority is exercised at the page)

Reading admin menus as "decorative sidebar items" misses the
routing-topology ontology.

### 2. Admin menus exhibit hybrid governance character (Mediation + Topology + Arbitration)

Admin menus span **3 distinct governance characters
simultaneously**:

| character | mechanism | example |
|---|---|---|
| **Mediation** | capability-gated access channels | $capability arg gates page access |
| **Topology** | hierarchical navigation graph | parent_slug establishes parent-child |
| **Arbitration** | menu ordering conflict resolution | $position + custom_menu_order arbitrate |

This is **first multi-character single-mechanism chunk** in
admin-ui — admin menus is not single-pattern but multi-character.

This makes admin-ui structurally similar to editor-customization
(which is also multi-pattern bounded context per editor-hooks
chunk's finding).

### 3. Mediation manifests at capability-gating boundaries (admin-ui density build)

Admin menus' Mediation character matches Settings API's
mediation:

| chunk | mediation surface | governed access channel |
|---|---|---|
| settings-api | sanitize_callback + capability + nonce + form transport | persistence access |
| **admin-menus** | **capability gate + menu visibility + page render permission + WordPress core menu rendering** | **navigation access** |

Both share **capability-gated authority access channels** as
structural core. Different domains (persistence vs navigation),
same mediation pattern.

This **strengthens Authority Mediation Surface candidate
within admin-ui** — 2 chunks confirming = density beyond
isolated manifestation. **Promotion event triggered** (see
Table B).

### 4. Topology character is structurally distinct from SlotFill's injection topology

KB-recurring topology pattern observation:

| chunk | topology character | governance |
|---|---|---|
| editor-customization.slotfills | INJECTION topology — UI authority inserted at named slots | what gets ADDED to existing topology |
| **admin-ui.admin-menus** | **ROUTING topology — navigation paths through hierarchical capability gates** | **how authority becomes NAVIGABLE** |

These are **structurally distinct topology characters**:
- SlotFill topology: insertion surface (where authority goes
  into UI)
- Admin menu topology: routing surface (where authority is
  navigable to)

Spec-grade observation:

> **Topology is insufficiently specific at current evidence**
> **density; governance character must distinguish injection**
> **from routing.**

Forcing both into single "topology" pattern would inflate the
candidate. Surfacing **"Administrative Routing Surface" as
distinct candidate** preserves discipline (per Phase 7.5
Doctrine 2 — Candidate structural complement).

### 5. Capability gates at multiple boundaries (Law 1 — Declaration ≠ Exposure 5-form)

Admin menus exhibit even MORE multidimensional declaration ≠
exposure than Settings API:

| surface | controlled by |
|---|---|
| **registration** (menu exists in registry) | add_menu_page() / add_submenu_page() |
| **capability gate** (menu visible in sidebar) | $capability arg |
| **page access** (page accessible by URL) | core's permission check |
| **render** (page renders content) | render callback's re-check |
| **navigation** (parent visible) | parent_slug + parent capability composition |

5-form declaration ≠ exposure. Admin menus surpass even CPT's
exposure flag count for governance multi-dimensionality.

### 6. Menu ordering = arbitration via priority + position + filter

Multiple plugins competing for menu placement create
arbitration scenarios:

| arbitration mechanism | role |
|---|---|
| `add_menu_page` `$position` arg | numeric ordering hint |
| collision fallback | alphabetical / registration order |
| `custom_menu_order` filter | explicit override enable |
| `menu_order` filter | reorder array |

This is **arbitration compiler** at admin layer (Law 4
manifestation). Pattern parallels:
- style-engine cascade-aggregation (CSS authority)
- plugin-dev capabilities-and-roles map_meta_cap (capability
  authority)
- block-filters priority (lifecycle interception authority)
- **admin-menus position + filters (admin navigation authority)**

5+ instances of arbitration compiler across KB now —
strengthens KB-Wide status.

### 7. Plugin federation through admin menu registration

Plugin registration adds menu items to admin authority graph
(Federation pattern manifesting in admin-ui):

```
Federation Pattern recurrence:
   plugin-dev    → register_post_type / register_meta /
                   register_rest_route federate authority
                   into core
   editor-customization → createReduxStore federates state
                          authority into editor
   admin-ui      → add_menu_page federates navigation
                   authority into admin sidebar
```

3-context federation pattern recurrence:
- plugin-dev (origin)
- editor-customization (cross-context recurrence)
- **admin-ui (this chunk's confirmation, 3rd context)**

Strengthens KB-Wide Federation status further.

### 8. Admin-ui = SECOND multi-pattern bounded context — validates Multi-pattern bounded context doctrine

> This invariant tests Phase 7.5 Doctrine 1 (Multi-pattern
> bounded context) **in a second bounded context** beyond
> editor-customization.

Multi-pattern character of admin-ui (after this chunk):

| pattern | manifestation |
|---|---|
| **Authority Mediation Surface** | capability-gated access channels (settings-api + admin-menus) |
| **Administrative Routing Surface** (NEW) | hierarchical navigation topology (admin-menus surfaces) |
| **Authority Interception Surface** | Divergent (admin-ui mediates and routes; doesn't intercept) |
| **Federation Pattern** | plugin menu registration federates into admin |
| **Arbitration Compiler** | menu_order arbitration |

editor-customization (multi-pattern):
- Interception (lifecycle + topology variants)
- Mediation (Surfaced)
- Federation (cross-context recurrence)

admin-ui (multi-pattern, this chunk):
- Mediation (Local → Recurring intra-context promotion)
- Routing (NEW Surfaced candidate)
- Federation (3rd-context confirmation)
- Arbitration (5+ instances of compiler pattern)

**Doctrine 1 validated**: Multi-pattern bounded contexts are
not editor-customization-specific; they are recurring
structural reality. KB has now observed multi-pattern character
in 2 bounded contexts.

## VERIFICATION NEEDED

`status: stable` — Admin menu API mature (WP 1.5+).
Specific behaviors evolving / variable:

- Position collision resolution algorithm (numeric +
  alphabetical fallback details).
- Hidden parent + visible child semantics
  (capability mismatch behavior).
- Capability cache behavior during admin render with custom
  roles.
- Menu icon: dashicon vs URL vs base64 SVG handling.
- Multisite site admin / network admin menu separation
  edge cases.
- Plugin deactivation cleanup of orphaned menu registrations.
- Performance with hundreds of registered menus.
- Submenu position parameter ordering with mixed registration
  contexts.
- Hide-then-show semantics (do_not_allow capability + filter
  interactions).

For practical decisions: empirical testing per scenario.

## ANTIPATTERNS

- ❌ **add_menu_page = secure admin authority**. Menu
  registration declares routing intent; security requires
  proper capability arg + render-time re-check + form-handler
  validation.
- ❌ **Hidden submenu = denied access**. Hiding via capability
  hides VISIBILITY; the URL may still be accessible if
  capability check is missing at the render stage.
- ❌ **Menu order = cosmetic only**. Order affects discoverability;
  collisions create inconsistent UX; admin task efficiency
  depends on predictable navigation.
- ❌ **Capability string = UX hint**. Capability is
  authoritative authorization gate; UI / sidebar visibility
  follows but render callback should re-check.
- ❌ **Admin visibility = governance completion**. Visible menu
  is one of multiple governance surfaces; full governance
  requires registration + visibility + render check + form
  handler check.
- ❌ **`menu_position` provides guaranteed order**. Position
  is a HINT; collisions, plugin order, custom_menu_order
  filter all override. For deterministic order, use
  custom_menu_order filter.
- ❌ **Parent capability gates child access**. Hidden parent
  may render child inaccessible; child's intended capability
  may be more permissive but composition with parent gate
  blocks. Audit parent/child capability composition.
- ❌ **Plugin uninstall cleanup ignored for menu state**. Menu
  registration persists in code; user expectations (bookmarks,
  saved URLs) persist in browser. Document menu deprecation
  in plugin updates.
- ❌ **add_menu_page in non-admin_menu contexts**. Wrong hook
  produces no registration or unpredictable timing.
- ❌ **Submenu-only plugins using add_options_page reflexively**.
  Convenience wrappers target specific core menus; choose
  appropriate one for plugin's governance context (Settings
  for global options; Tools for utilities; etc.).

## RELATED

- `admin-ui.settings-api` — paired chunk for Mediation density
  build within admin-ui. Together they promote Authority
  Mediation Surface from Local to Recurring (intra-context).
- `editor-customization.slotfills` — injection-topology
  counterpart. **Structurally distinct topology character**:
  SlotFill = injection; admin menus = routing. Comparison
  surfaces "Administrative Routing Surface" as separate
  candidate.
- `plugin-dev.security-boundaries` — 3-tier security model
  applied. Admin menus exemplify trust (registration) +
  legitimacy (capability) + permeability (per-request access)
  layered governance.
- `plugin-dev.capabilities-and-roles` — capability constitution
  applied at every menu boundary. The 5-form declaration ≠
  exposure depends on capability adjudication via map_meta_cap
  + current_user_can.
- `plugin-dev.register-block-bindings-source` (and other
  plugin-dev federation chunks) — Federation Pattern recurrence.
  Admin menus federate plugin navigation into admin authority
  graph (3rd-context Federation manifestation).
- `_meta.structural-patterns` — **Phase 7.5 patched spec
  applied; SECOND PROMOTION EVENT documented**.

## META

**admin-ui bounded context — second chunk; second substantive
chunk under Phase 7.5 patched spec; SECOND PROMOTION EVENT
in KB; second multi-pattern bounded context confirmed.**

### Phase 7.5 patched framework deployment

Per settings-api precedent (3 explicit acknowledgments):

1. ✅ **Patched verdict taxonomy deployed** (5-class
   Confirmed/Divergent/Hybridized/Surfaced/Deferred) used in
   Tables A & B.
2. ✅ **Patched maturity ladder applied** (5-tier Surfaced/
   Local/Recurring intra-context/Recurring cross-context/
   KB-Wide). Table B promotes Mediation Local → Recurring
   (intra-context).
3. ✅ **Q8 adjudication doctrine operationalized**: chunk
   evaluated as Confirm (Mediation) + Surface (Routing) +
   Diverge (Interception) simultaneously.

### Doctrinal extension established

> **Settings API governs administrative persistence mediation;**
> **Admin Menus also confirm administrative governance through**
> **capability-routed interface constitution (mediation +**
> **routing topology + arbitration overlap).**

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 5 — Entity → Relationship Pivot** | VERY STRONG | Page ↔ submenu ↔ parent ↔ capability graph | **Confirmed (admin-routing topology variant; deepens 5-context manifestation)** |
| **Law 4 — Arbitration Compiler** | VERY STRONG | menu_position + custom_menu_order + collision fallback | **Confirmed (5+ instances now across KB; KB-Wide status reinforced)** |
| **Law 1 — Declaration ≠ Exposure** | VERY STRONG | 5-form: registration / capability gate / page access / render / parent visibility | **Confirmed (most-multidimensional in KB to date)** |
| **Law 6 — Compiler ↔ Runtime Split** | Strong | Registration (admin_menu hook) vs admin render runtime | **Confirmed** |
| **Law 3 — Authority Continuity** | Strong (implicit) | Capability authority persists across menu navigation | **Confirmed (implicit)** |
| **Law 2 — HTML Primacy** | Implicit | Admin pages render as HTML | **Confirmed (implicit)** |

**Universal law manifestation: SUCCESS.** Law 1 reaches
5-form (most multidimensional yet); Law 4 reinforced with 5+
arbitration compiler instances; Law 5 continues 5-context
manifestation depth.

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | Admin-menus manifestation | Outcome |
|---|---|---|---|
| **Authority Mediation Surface** | Local (admin-ui, 1 chunk) + cross-context PRESENCE | Strong: capability-gated navigation = mediation pattern (parallels settings-api capability-gated persistence) | **PROMOTED: Local → Recurring (intra-context) within admin-ui** |
| **Administrative Routing Surface** (NEW) | did not exist | Surfaced via hierarchical capability-gated navigation topology — structurally distinct from SlotFill's injection topology | **Local Pattern Surface (1st observation); "surfaced, not constitutionalized"** |
| **Authority Interception Surface** | Recurring (intra-context, editor-customization) | Weak/secondary — admin menus are mediation + routing, not interception | **Divergent — admin-ui not interception domain** |
| **Federation Pattern** | KB-Wide (3rd context now) | Plugin menu registration federates navigation into admin authority graph | **Confirmed (3rd-context manifestation; KB-Wide reinforced)** |

### SECOND PROMOTION EVENT — Authority Mediation Surface Local → Recurring (intra-context)

**Promotion details:**
- editor-customization side: Surfaced (1 chunk, editor-hooks)
- admin-ui side: 2 chunks (settings-api + admin-menus) both
  confirming mediation character with shared structural core
  (capability-gated authority access channels)
- Within admin-ui: 2 chunks = density sufficient for Recurring
  (intra-context) per Phase 7.5 Section A criteria

**Promotion path remaining:**
- Recurring (intra-context, admin-ui) → Recurring
  (cross-context): requires editor-customization side density
  build (additional editor-customization mediation chunks)
  OR 3rd bounded context exhibiting mediation
- Recurring (cross-context) → KB-Wide: requires audit
  verification

**Phase 7.5 spec validation**: 5-tier ladder operates
correctly. Recurring (intra-context) tier is genuinely
distinct from Recurring (cross-context) and provides
appropriate granular promotion.

### Administrative Routing Surface — NEW candidate surfaced (NOT promoted)

> **Administrative Routing Surface**: governance through
> hierarchical capability-gated navigation topology where
> authority becomes NAVIGABLE rather than INSERTED.

Distinct from SlotFill's injection topology. Surfaced as
structural complement (per Doctrine 2). Verification path:

```
Administrative Routing Surface candidate:
   Local: 1 observation (this chunk; admin-menus)
   Recurring (intra-context): would require 2nd admin-ui
      routing instance (network admin menus? user
      capability-gated dashboard widgets?)
   Recurring (cross-context): would require routing in
      another bounded context (block editor sidebar
      navigation? site editor template browser?)
```

"Surfaced, not constitutionalized." Future chunks may test
recurrence.

### Spec-grade observation (towards Phase 7.6 patch consideration)

> **Topology is insufficiently specific at current evidence**
> **density; governance character must distinguish injection**
> **from routing.**

This observation may become a Phase 7.6 patch consideration
IF additional chunks exhibit topology with structurally
distinct characters (composition? layout? attachment?).
Current status: **observed nuance, NOT spec patched yet** —
single observation; sustained pattern needed.

### Multi-pattern bounded context doctrine — VALIDATED in 2nd context

Phase 7.5 Doctrine 1 ("A bounded context may simultaneously
host pattern recurrence, divergence, and overlap") is now
validated beyond editor-customization:

| bounded context | multi-pattern character |
|---|---|
| **editor-customization** | Interception (lifecycle + topology variants) + Mediation (Surfaced) + Federation (cross-context recurrence) |
| **admin-ui (this chunk)** | Mediation (Recurring intra-context) + Routing (Surfaced) + Federation (3rd-context confirmation) + Arbitration (compiler) |

Two bounded contexts confirmed multi-pattern. Doctrine 1
status reinforced.

### KB-wide pattern recurrence updates

**Debt pattern (6-instance recurrence)**:

| chunk | debt name | bounded context |
|---|---|---|
| security-boundaries | security debt | plugin-dev |
| block-filters | interception debt | editor-customization |
| slotfills | topology debt | editor-customization |
| editor-hooks | reactive debt | editor-customization |
| settings-api | settings debt | admin-ui |
| **admin-menus** | **navigation debt** | **admin-ui** |

6 instances × 3 bounded contexts. "Governance debt" continues
strengthening as anticipated meta-pattern. NOT yet promoted.

**Federation Pattern (3-context recurrence)**:
- plugin-dev (origin)
- editor-customization (cross-context recurrence via createReduxStore)
- **admin-ui (this chunk; plugin menu registration)**

KB-Wide Federation status reinforced via 3-context
manifestation.

### KB self-evaluation against spec criteria (Phase 7.5 patched)

- ✅ Accuracy — describes documented Admin Menu API.
- ✅ Structural fit — second admin-ui chunk; tests Mediation
  density + surfaces new Routing candidate; validates
  Multi-pattern bounded context doctrine in 2nd context.
- ✅ Reusability — uses authority ontology glossary +
  Phase 7.5 vocabulary (mediation / routing / arbitration /
  navigation debt).
- ✅ Phase fit — Phase 7.5 patched spec applied; references
  all relevant predecessor chunks.
- ✅ Doctrine respect — HTML primacy implicit; declaration ≠
  exposure 5-form invoked; Epistemic Integrity preserved
  (Routing surfaced separately, NOT force-fit into topology).
- ✅ **Q8 adjudication explicitly answered**: Mediation =
  Confirm + Promotion; Routing = Surface (NEW); Interception
  = Diverge; Federation = Confirm.

### Status: `stable`

Admin menu API mature (WP 1.5+). Verification-needed catalog
covers behaviors but core API is settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission

> **This chunk should determine whether administrative**
> **authority is merely exposed through menus — or**
> **constitutionally routed through hierarchical**
> **capability-governed navigability systems.**

Verdict: **Routing-topology confirmed.** Admin menus IS
constitutionally routed authority navigation, not decorative
sidebar UI.

### Anticipated next chunks (priority)

1. **`admin-ui.notices`** — third admin-ui mechanism. Tests
   whether admin notices exhibit interception (admin_notices
   hook) OR mediation (notice queue) OR hybrid. Could test
   Routing Surface recurrence within admin-ui.

2. **`site-building`** entry — composition-heavy bounded
   context. Different test surface (less mediation, more
   relationship-centric / template composition).

3. **`plugin-dev.nonces`** — security primitive trio
   completion (intermixing).

4. **Phase 7.6 spec patch consideration** — if additional
   chunks exhibit topology character distinction repeatedly,
   formalize injection vs routing topology distinction.

Recommended sequence: **`admin-ui.notices`** (continue
admin-ui density build → could promote Routing Surface from
Surfaced to Local within admin-ui if exhibits routing
character) → then site-building or other contexts.
