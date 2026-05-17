---
rule_id: plugin-dev.capabilities-and-roles
domain: plugin-dev
topic: adjudication-constitution
field_cluster: capability-substrate
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/plugins/users/roles-and-capabilities/
    section: "Plugin Handbook — Roles and Capabilities"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/current_user_can/
    section: "current_user_can() — primary capability check API"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/map_meta_cap/
    section: "map_meta_cap() — meta-to-primitive capability translation"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/classes/wp_role/
    section: "WP_Role / WP_Roles — role bundle classes"
    captured: 2026-05-09
  - url: https://wordpress.org/documentation/article/roles-and-capabilities/
    section: "Default WordPress roles and their capability membership"
    captured: 2026-05-09
related:
  - plugin-dev.security-boundaries             # paired doctrine — governance + adjudication
  - plugin-dev.register-post-type              # capability_type concrete usage
  - plugin-dev.register-taxonomy               # 4-capability schema (manage/edit/delete/assign)
  - plugin-dev.register-rest-route             # permission_callback adjudication site
  - plugin-dev.register-meta                   # auth_callback adjudication site
  - data-layer.persistence                     # capability checks at persistence boundary
  - style-engine.cascade-aggregation           # cross-KB symmetry — map_meta_cap = adjudication compiler ↔ cascade arbitration
  - (planned) plugin-dev.nonces                # complementary security primitive (CSRF != capability)
---

# RULE — capabilities-and-roles — adjudication constitution

## WHEN

> **If you are checking roles directly, you are likely
> bypassing WordPress's constitutional authority model.**

Use this chunk when:
- Designing custom capability schemas (CPT capability_type,
  taxonomy 4-capability, custom plugin capabilities).
- Authoring authorization logic anywhere
  (current_user_can / user_can / permission_callback /
  auth_callback).
- Designing custom roles (add_role / capability membership
  decisions).
- Debugging "user can't access this" / "user can access
  unintended thing" issues.
- Understanding why role checks are an antipattern.
- Multisite / network capability semantics design.

This is **plugin-dev's second doctrine-tier chunk**, paired
with `security-boundaries`. The two form plugin-dev's
**dual-doctrine substrate**:

```
plugin-dev doctrines (paired):
   security-boundaries     → governance doctrine
                            "How should authority be governed?"
   capabilities-and-roles  → adjudication constitution (THIS)
                            "How is authority constitutionally adjudicated?"
```

`security-boundaries` defines POLICY (what governance applies
where); `capabilities-and-roles` defines the OPERATIONAL
INTERPRETATION (the constitutional framework by which authority
legitimacy is decided per request, per actor, per context).

## SHAPE

### The 4-layer authority constitutional model

```
Layer 1: Primitive capabilities      ── atomic authority rights
                                       (edit_posts, publish_posts, manage_options, ...)
   ↑
Layer 2: Meta capabilities           ── contextual authority questions
                                       (edit_post for instance N, delete_user for user M)
   ↑
Layer 3: Mapping (map_meta_cap)      ── adjudication compiler
                                       (translates meta cap requests into primitive checks
                                        + contextual conditions like ownership)
   ↑
Layer 4: Roles                        ── deployment defaults
                                       (administrator, editor, author, ... — bundled
                                        capability distribution profiles)

Runtime adjudication                  ── EXECUTION SURFACE (NOT ontology layer)
                                       current_user_can($cap, ...$args)
                                       user_can($user_id, $cap, ...$args)
```

The 4 layers are constitutional ontology. Runtime adjudication
(`current_user_can` etc.) is the **constitutional execution
query** — how the constitution is invoked at request time.

### A. Primitive capability substrate (Layer 1)

```php
// Built-in primitives
'edit_posts'         // create / modify own posts
'edit_others_posts'  // modify posts owned by others
'publish_posts'      // transition to publish status
'delete_posts'       // delete own posts
'delete_others_posts'// delete others' posts
'read_private_posts' // read posts in private status
'read'               // baseline read access
'manage_options'     // site-wide settings access
'manage_categories'  // taxonomy admin (categories)
'edit_users'         // user admin
'edit_themes'        // theme editor file write
'install_plugins'    // plugin install
// ... ~70 default primitives in WordPress core
```

CRUD-family pattern: `edit_X` / `read_X` / `delete_X` /
`publish_X`. Custom CPT capability_type generates parallel
families:

```php
register_post_type( 'product', array(
    'capability_type' => 'product',
    'map_meta_cap'    => true,
) );
// Generates: edit_product, edit_products, edit_others_products,
//            publish_products, read_product, delete_product,
//            delete_products, delete_others_products, etc.
```

Primitives are the **atomic authority rights** of the system.
Every authorization decision ultimately resolves to primitive
capability checks.

### B. Role bundles (Layer 4)

```php
// Default 6 roles (capability distribution profiles)
'administrator'   // ~62 caps including manage_options, install_plugins
'editor'          // ~34 caps including edit_others_posts, manage_categories
'author'          // ~10 caps (edit_published_posts, upload_files, etc.)
'contributor'     // ~4 caps (edit_posts, read, delete_posts on own)
'subscriber'      // ~1 cap (read)
// 'super admin'  // multisite-only; can_manage_network capabilities
```

Roles ARE NOT a separate authority concept. **A role is a
named capability bundle** — a deployment-friendly preset
distribution of primitives. Adding `editor` to a user adds
the editor's capability set to that user.

```php
// Create custom role
add_role( 'product_manager', __( 'Product Manager' ), array(
    'read'                => true,
    'edit_products'       => true,
    'edit_others_products'=> true,
    'publish_products'    => true,
    'delete_products'     => true,
    // ... explicit capability membership
) );

// Modify existing role
$role = get_role( 'editor' );
$role->add_cap( 'edit_products' );
$role->remove_cap( 'edit_themes' );

// Remove role
remove_role( 'product_manager' );
```

WP_Role / WP_Roles classes manage role storage in the
options table.

### C. Meta capability translation (Layer 3 — adjudication compiler)

Meta capabilities express **contextual authority questions**
that primitives alone cannot answer:

| meta capability | contextual question |
|---|---|
| `edit_post` | Can this user edit POST 123? (depends on ownership + status + primitive caps) |
| `delete_user` | Can this user delete USER 5? (cannot delete self; super admin in multisite) |
| `read_post` | Can this user read POST 123? (depends on visibility + ownership + primitive caps) |
| `edit_user_meta` | Can this user edit meta of USER 5? (capability + relationship checks) |

Primitives are CONTEXT-FREE; meta caps require CONTEXT.
`map_meta_cap` is the **adjudication engine** that translates
meta caps into one or more primitive checks plus contextual
conditions:

```php
// Conceptual sketch of map_meta_cap behavior
function map_meta_cap_for_edit_post( $caps, $cap, $user_id, $args ) {
    // $cap = 'edit_post'
    // $args[0] = post ID
    $post = get_post( $args[0] );
    if ( $post->post_author == $user_id ) {
        return array( 'edit_posts' );          // own post: just need edit_posts
    } else {
        return array( 'edit_others_posts' );   // others' post: need edit_others_posts
    }
}
```

Setting `'map_meta_cap' => true` in CPT registration enables
this translation engine for the CPT's meta caps. Without it,
meta cap checks fall through to primitive name (`edit_product`
checked literally as a primitive — incorrect for ownership-
sensitive operations).

The **map_meta_cap filter** allows custom mapping logic for
custom meta caps:

```php
add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
    if ( 'edit_my_custom_thing' === $cap ) {
        // custom contextual logic returning required primitives
        $thing = get_thing( $args[0] );
        return $user_id == $thing->owner_id
            ? array( 'edit_my_things' )
            : array( 'edit_others_my_things' );
    }
    return $caps;
}, 10, 4 );
```

### D. Runtime adjudication (execution surface, NOT ontology layer)

```php
// Primary adjudication
if ( current_user_can( 'edit_posts' ) ) { ... }

// Meta cap with context
if ( current_user_can( 'edit_post', $post_id ) ) { ... }

// Specific user adjudication
if ( user_can( $user_id, 'publish_products' ) ) { ... }

// Last-resort filter override
add_filter( 'user_has_cap', function ( $allcaps, $caps, $args, $user ) {
    // Modify capability resolution per user/context
    return $allcaps;
}, 10, 4 );
```

`current_user_can` / `user_can` / `author_can` are the
**constitutional execution queries** — they invoke the
4-layer constitution to adjudicate a specific request. The
queries are NOT the constitution; the constitution is the
4-layer model. Queries are how the constitution is consulted.

### E. Federation integration

| federation surface | capability constitution touchpoint |
|---|---|
| `register_post_type` `capability_type` | declares capability namespace; generates primitives |
| `register_post_type` `capabilities` array | explicit primitive mapping for meta caps |
| `register_post_type` `map_meta_cap: true` | enables adjudication compiler for the CPT |
| `register_taxonomy` `capabilities` | 4-cap schema (manage / edit / delete / assign — note assign separation) |
| `register_meta` `auth_callback` | per-meta access adjudication via current_user_can |
| `register_rest_route` `permission_callback` | per-request adjudication at transport boundary |
| Admin menu `show_ui` / `show_in_menu` | UI hint only — does NOT govern authority |
| Block editor capability hints | UI hint only — server-side adjudication is authoritative |

> **UI exposure surfaces may reference capability constitution,
> but never replace it.**

This is the recurring axis (declaration ≠ exposure ≠
enforcement) projected onto capability checks: hidden UI is
NOT denied access; visible UI is NOT granted access.
Authoritative adjudication runs at the request boundary
(REST permission, admin-load capability check, render-time
verification).

## REQUIRES

- WordPress capability system available (always present).
- For custom CPT capabilities: `add_cap()` calls to grant
  capabilities to roles after registration. Registration alone
  does NOT distribute capabilities.
- For map_meta_cap behavior: `'map_meta_cap' => true` in
  CPT registration; without it, meta cap requests do NOT
  translate (or translate via legacy fallback that may not
  match expectations).
- For multisite: super admin capability semantics override
  site-level checks for many operations.
- Consistent capability check timing — capabilities can change
  mid-session; checks must run at request boundary, not
  cached at registration.
- ⚠ Multisite capability semantics, capability cache behavior,
  WP-CLI capability resolution, capability check ordering with
  multiple plugins filtering map_meta_cap — verification-needed.

## INVARIANTS

### 1. Capabilities are primitive authority units, NOT user labels

> **Capabilities are atomic authority rights**: discrete, named,
> context-free permissions like `edit_posts`, `manage_options`,
> `delete_users`. They are NOT user labels (those are roles).
> They are NOT actions (those are operations). They are NOT
> features (those are UI affordances).

Each primitive capability is a unit of authority that an actor
either has or lacks. The capability system is designed for
adjudication: "does this actor have THIS specific authority
right?"

### 2. Roles aggregate authority bundles, NOT govern authority directly

**THE LOAD-BEARING INVERSION** — also surfaced in WHEN +
META as a recurring framing.

> **Roles are deployment defaults; capabilities are
> constitutional primitives.**

Common conceptual bug:
- ❌ "Administrator role allows X" — role-centric reading
- ✅ "Administrator role HAS the capability that authorizes X" —
  constitutional reading

Operational consequence:
- Code against capabilities (`current_user_can( 'edit_posts' )`),
  NOT roles (`if ( in_array( 'editor', $user->roles ) )`).
- Custom roles with custom capability sets work transparently
  with capability-based code.
- Role-name-based code BREAKS when roles are renamed,
  removed, or when custom roles are introduced that should
  also have the right.

The role/capability inversion is WordPress's most-frequently-
violated constitutional principle in the wild. Plugin/theme
authors who internalize this distinction write code that
respects the constitution.

### 3. Meta capabilities express contextual authority questions

> Meta capabilities (`edit_post`, `delete_user`, `read_post`,
> `edit_user_meta`) express questions that PRIMITIVES ALONE
> cannot answer. They require CONTEXT (which post? which user?)
> and may resolve to different primitive requirements based on
> ownership, status, visibility, or other contextual conditions.

Meta caps are not "weaker" or "stronger" primitives — they're
a different kind of authority concept. Treating meta caps as
primitives (calling `current_user_can( 'edit_post' )` without
the context arg) leads to incorrect adjudication.

### 4. map_meta_cap translates contextual authority into enforceable primitive rights (adjudication compiler)

> `map_meta_cap` is the **adjudication compiler** of the
> constitution. It translates contextual meta cap requests into
> sequences of primitive checks plus contextual conditions
> (ownership, status, relationship). The compiler is invoked
> automatically by `current_user_can` / `user_can` when meta
> caps are checked with context arguments.

KB-wide symmetry surfacing:

| layer | arbitration / adjudication compiler |
|---|---|
| style-engine cascade-aggregation | competing CSS authorities → cascade graph compilation |
| **plugin-dev capabilities-and-roles map_meta_cap** | **contextual authority requests → primitive check compilation** |

Both are arbitration COMPILERS that transform multi-source
authority claims into resolved decisions. The symmetry is
structural — KB documents arbitration compilers in two
domains (visual cascade + capability adjudication).

### 5. Capability systems separate authority adjudication from identity labels by mediating through capability bundles and contextual translation

(Refined from initial proposal for precision.)

> WordPress's capability system **decouples WHO from WHAT**.
> Identity (user, role label) and authority (capability set)
> are mediated through:
> - capability bundles (roles distribute defaults)
> - contextual translation (meta caps resolve to primitives
>   per context via map_meta_cap)
> - per-request adjudication (current_user_can checks capability
>   in current context)

Implications:
- A user is NOT an authority subject directly; the user's
  capability membership is.
- Identity changes (role rename) do NOT necessarily change
  authority (if capabilities preserved).
- Custom capability schemes can adjudicate without inventing
  custom identity systems.

This decoupling is what enables plugins / themes to introduce
custom authority surfaces without introducing custom identity
systems.

### 6. Capability checks are architecture-level governance surfaces, NOT UI hints

> `current_user_can` is **authoritative authorization**, not
> UX convenience. UI hide based on capability is a HINT;
> the authoritative check happens at the request boundary
> (REST permission_callback, admin-load capability check,
> render-time verification).

Common confusion:
- ❌ Hide button → assume user can't perform action
- ✅ Hide button (UX hint) + check capability at request handler
  (architecture enforcement)

The two layers compose:
- UI capability check: if false, hide affordance (UX).
- Server-side capability check: if false, reject operation
  (security).

UI hide alone is bypassed by direct request fabrication; only
server-side checks are authoritative.

### 7. Plugin-dev APIs ultimately federate into the capability constitution

Every plugin-dev federation API resolves into capability
constitution at adjudication time:

```
register_block_bindings_source     → callback may consult capabilities
register_meta auth_callback        → calls current_user_can
register_rest_route                → permission_callback calls current_user_can
register_post_type                 → capability_type cascade
register_taxonomy                  → 4-cap schema (manage/edit/delete/assign)
```

The federation stack does not provide its own authority
adjudication — it federates INTO the capability constitution.
This makes capabilities-and-roles a CROSS-CUTTING dependency
of plugin-dev, justifying its doctrine-tier role.

### 8. Capabilities-and-roles form WordPress's authority adjudication substrate

KB-level positioning:

> `capabilities-and-roles` is **WordPress's authority
> adjudication substrate** — the constitutional framework
> through which all authorization decisions ultimately
> resolve.
>
> Its scope spans bounded contexts: block-authoring (editor
> capabilities for block usage), theme-config (theme switch
> capability), data-layer (entity capabilities), interactivity
> (admin block library access), plugin-dev (all federation
> APIs), site-building (template editing capabilities), admin-
> ui (admin menu capability checks).

Reading capabilities-and-roles as "plugin-dev internal" misses
its cross-KB scope. The doctrine applies to every authorization
decision in WordPress.

## VERIFICATION NEEDED

`status: stable` — capability/role MODEL is mature and stable.
Specific MECHANISMS may evolve / vary:

**Stable doctrine / evolving mechanisms** distinction (per spec):
- The 4-layer model + map_meta_cap translation pattern + role
  bundle concept = stable doctrine.
- Specific implementations evolve.

Items requiring verification:

- Multisite capability semantics: super admin override scope,
  network-only capabilities, site-vs-network capability
  resolution.
- Capability cache behavior — when do capability changes
  propagate to in-flight requests?
- WP_CLI capability resolution vs HTTP request capability
  resolution.
- map_meta_cap filter ordering when multiple plugins filter.
- Capability check behavior with deactivated plugins (caps
  defined by deactivated plugin code).
- WP-Cron capability context.
- REST request capability behavior with different
  authentication providers (cookie vs Application Passwords
  vs OAuth).
- `do_not_allow` capability handling.
- Block editor capability hints derivation timing.
- Custom user provider plugins (LDAP, SSO) and capability
  resolution.
- Capability persistence when removing custom roles
  (orphaned capability data on users assigned the role).
- Performance with many filter additions to user_has_cap /
  map_meta_cap.

For practical decisions: empirical testing per scenario.

## ANTIPATTERNS

- ❌ **Role checks > capability checks**. Code against
  capabilities (`current_user_can( 'edit_posts' )`), NOT roles
  (`in_array( 'editor', $user->roles )` or
  `$user->roles[0] === 'editor'`). Custom roles + role
  rename + role removal break role-name code; capability code
  remains correct.
- ❌ **administrator = universal bypass**. Administrator does
  NOT have all capabilities (e.g., super admin has caps admin
  lacks in multisite; some caps are restricted by file editing
  flags). Always check the SPECIFIC capability needed.
- ❌ **`current_user_can` = UI convenience**. It is
  authoritative authorization. Treating it as UX hint and
  skipping server-side checks creates security holes.
- ❌ **Meta capabilities = primitive permissions**. Meta caps
  express contextual questions; map_meta_cap translates them.
  Calling `current_user_can( 'edit_post' )` without context
  argument does NOT correctly adjudicate post-specific
  authority.
- ❌ **`capability_type` alone secures CPT**. Custom
  capability_type DECLARES the namespace; capability membership
  must be granted to roles via add_cap(). Without grants,
  NO ROLE has the new capabilities — entity uneditable for
  everyone.
- ❌ **Hidden admin menu = denied authority**. show_ui /
  show_in_menu hide UI affordances; authoritative checks
  happen at request boundary regardless of UI visibility.
  Hiding UI is UX; check capability is security.
- ❌ **Custom role = secure by default**. Custom roles need
  EXPLICIT capability set design. add_role() with empty
  capabilities array creates a role with NO authority —
  user with this role cannot read the site if `read` is
  omitted.
- ❌ **Treating `manage_options` as universal admin check**.
  `manage_options` governs site OPTIONS access, NOT general
  admin operations. Editors can access many admin areas
  without manage_options. Use the specific capability
  appropriate to the operation (e.g., `edit_themes` for
  theme operations, `install_plugins` for plugin install).
- ❌ **Assuming custom capabilities automatically map through
  `map_meta_cap`**. Custom capability_type generates primitive
  caps but does NOT automatically install map_meta_cap
  translation logic for custom meta caps. Either set
  `'map_meta_cap' => true` for CPT (uses default mapping based
  on post_author) OR add explicit map_meta_cap filter logic
  for custom meta caps.
- ❌ **Hardcoding role names in conditionals**. Breaks with
  custom role configurations, role rename, role removal.
  Capability check is portable across role configurations.
- ❌ **Forgetting to remove caps when removing custom roles**.
  Removing the role does NOT remove the role's capabilities
  from users currently assigned that role; orphaned cap
  membership persists.
- ❌ **Confusing user_has_cap filter with permission_callback**.
  user_has_cap is constitutional filter (overrides capability
  resolution); permission_callback is request-level gate.
  Different layers, different semantics.

## RELATED

- `plugin-dev.security-boundaries` — paired doctrine.
  security-boundaries asks "how should authority be governed";
  this chunk asks "how is authority constitutionally
  adjudicated". Together they form plugin-dev's dual-doctrine
  substrate.
- `plugin-dev.register-post-type` — concrete usage of
  capability_type + map_meta_cap. CPT chunk's capability
  schema discussion references this constitution.
- `plugin-dev.register-taxonomy` — 4-cap schema (manage / edit /
  delete / assign). Taxonomy chunk's manage vs assign
  distinction directly applies the constitution.
- `plugin-dev.register-rest-route` — permission_callback is
  a constitutional execution query at the transport boundary.
- `plugin-dev.register-meta` — auth_callback is a
  constitutional execution query at the meta boundary.
- `data-layer.persistence` — capability checks at the
  persistence boundary; constitution applies at
  reconciliation lifecycle.
- `style-engine.cascade-aggregation` — KB-wide symmetry.
  Style-engine cascade compiles competing CSS authorities;
  capability constitution compiles contextual authority
  requests via map_meta_cap. Both are arbitration compilers
  in different domains.
- (planned) `plugin-dev.nonces` — complementary security
  primitive. CSRF protection (nonce) is orthogonal to
  authorization (capability). Both layer; neither replaces
  the other.

## META

**plugin-dev bounded context — second doctrine-tier chunk
(adjudication constitution, paired with security-boundaries
governance doctrine).**

**One-line backbone:**

> **In WordPress, roles distribute authority defaults,**
> **but capabilities constitutionally adjudicate authority**
> **legitimacy.**

**KB-level framing extension:**

> If `security-boundaries` defines **governance doctrine**,
> then `capabilities-and-roles` defines the **constitutional
> substrate by which authority is adjudicated** across all
> federated systems.

**Three-layer terminology hierarchy** established in
plugin-dev:

| layer | concern | chunk |
|---|---|---|
| **Governance** | policy decisions about authority | security-boundaries |
| **Constitution** | rights framework defining authority | capabilities-and-roles (this) |
| **Adjudication** | operational interpretation of rights per request | runtime: current_user_can / user_can / permission_callback / auth_callback |

The three layers compose: doctrine governs how the
constitution is applied; the constitution defines what
authority means; adjudication invokes the constitution per
request.

**KB capstone symmetry — extended (4 instances):**

| bounded context | doctrine / capstone | authority problem |
|---|---|---|
| style-engine | cascade-aggregation | arbitration |
| interactivity | hydration | continuity |
| **plugin-dev** | **security-boundaries** | **governance** |
| **plugin-dev** | **capabilities-and-roles (this)** | **adjudication** |

**plugin-dev = first dual-doctrine bounded context.** This is
not a deficiency — it reflects plugin-dev's role as external
federation, which structurally requires BOTH governance
(security-boundaries) AND adjudication (this chunk). Internal
bounded contexts (style-engine, interactivity) operate within
core's authority; plugin-dev introduces external authority and
needs both governance + adjudication doctrines.

**KB-WIDE PATTERN — Arbitration Compiler:**

This chunk surfaces a structural pattern across two bounded
contexts:

| bounded context | arbitration compiler |
|---|---|
| style-engine | cascade-aggregation (CSS authority arbitration) |
| **plugin-dev** | **map_meta_cap (capability authority adjudication)** |

Both are arbitration compilers that transform multi-source
authority claims into resolved decisions. The pattern
suggests **arbitration compilation is a recurring structural
solution in WordPress's authority architecture** — wherever
multiple authority claims must be reconciled into a single
decision, an arbitration compiler emerges.

**plugin-dev domain identity — fully crystallized:**

> Plugin-dev bounded context = **external authority + semantic
> + adjudication architecture** layer of WordPress.
>
> Federation stack (origin / persistence / transport / entity /
> semantic) + paired doctrines (governance + adjudication
> constitution) + multi-boundary ABI declarations + cross-
> cutting capability substrate = the complete extensibility
> system through which external actors federate authority
> into WordPress under constitutional governance.

**Stable doctrine / evolving mechanisms distinction:**

The capability constitution itself is stable. Specific
mechanisms (map_meta_cap behaviors, multisite super admin
semantics, REST authentication provider integration) evolve.
This is the second KB chunk explicitly using the stable-
doctrine/evolving-mechanism distinction (after security-
boundaries).

The distinction matures KB epistemic vocabulary:
- `status: stable` for the doctrine.
- VERIFICATION NEEDED catalog for mechanism specifics.

**Status: `stable` (doctrine):**

Capabilities/roles model = WordPress core constitutional
substrate. Mature since WP 2.0+ era. map_meta_cap = stable.
current_user_can = canonical. Doctrine itself is structurally
stable (versions evolve mechanisms, not the constitution).

**DSL extensions applied:** VERIFICATION NEEDED + META, per
runtime/implementation-derived applicability rule.

**Cross-KB constitutional substrate:**

This chunk's scope spans bounded contexts:
- block-authoring: editor capabilities for block usage
- theme-config: switch_themes / edit_theme_options
- data-layer: entity-level capabilities (edit_posts cascade
  through map_meta_cap)
- interactivity: admin block library access capabilities
- plugin-dev: all federation APIs reference capabilities
- site-building: edit_theme_options + template hierarchy
  capabilities
- admin-ui: admin menu / setting capability checks

The chunk lives in plugin-dev folder for organizational fit
(plugin-dev is where capability customization is most
commonly authored) but its semantics apply KB-wide.

**Anticipated next chunks (priority):**

1. **`plugin-dev.nonces`** — CSRF protection. Complementary
   security primitive; orthogonal to capabilities. Often
   confused with authorization; dedicated chunk needed.

2. **Other plugin-dev families** — block filters / slotfills /
   hooks / settings. All operate within established federation
   + governance + constitution.

3. **Other bounded contexts** (additive): editor-customization /
   site-building / i18n / build-tooling / admin-ui. Now have
   complete plugin-dev foundation to reference.

4. **KB audit / cross-ref completeness** — at 69+ chunks,
   ontology audit warranted. RETROACTIVE REFRAMING candidates
   may surface (existing chunks gaining post-Phase-7 +
   post-doctrine reading).

Recommended next: `plugin-dev.nonces` (closes plugin-dev's
core security primitive trio: capabilities + nonces + sanitize/
escape) OR enter additive bounded context (editor-
customization or i18n). Both viable; user direction
determines.

**KB self-evaluation against spec criteria:**

- ✅ Accuracy — synthesizes documented capability mechanisms
  into constitutional ontology.
- ✅ Structural fit — establishes plugin-dev as first
  dual-doctrine bounded context; surfaces arbitration
  compiler pattern across KB.
- ✅ Reusability — uses authority ontology glossary
  extensively (authority / adjudication / federation /
  arbitration / constitution / governance / substrate).
- ✅ Phase fit — paired with security-boundaries; references
  all plugin-dev federation chunks.
- ✅ Doctrine respect — establishes this chunk AS doctrine
  reference for all authority-checking code in WordPress.

## RETROACTIVE REFRAMING (post-Resolution-Surface-surfacing)

**Status note**: Added 2026-05-09 as 2nd retroactive
verification of "Resolution Surface" candidate (1st retro:
cascade-aggregation). The original chunk frames capability
adjudication primarily through Arbitration Compiler lens
(map_meta_cap = adjudication compiler). Post-Resolution-
Surface analysis re-examines whether capabilities also exhibit
Resolution stage — and if so, where it resides architecturally.

The original chunk's framing remains accurate; this section
adds finer-grained structural analysis of Arbitration vs
Resolution stages within capability adjudication.

**KB pattern**: 5th explicit RETROACTIVE REFRAMING section in
KB:
- wrapper-attributes (post-style-engine closure)
- dynamic-rendering (post-Phase-7-capstone)
- markup-representation (post-Phase-7-capstone)
- cascade-aggregation (post-Resolution-Surface-surfacing —
  1st retro for Resolution candidate)
- **capabilities-and-roles (post-Resolution-Surface-surfacing
  — 2nd retro for Resolution candidate, THIS SECTION)**

**Methodological commitment** (per user direction):
**maximum doctrinal strictness**. Three possible outcomes
to evaluate honestly:
- **Confirmed (integrated)**: map_meta_cap = Arbitration +
  Resolution co-located
- **Distributed**: map_meta_cap = Arbitration only;
  current_user_can / has_cap iteration = Resolution
- **Divergent**: Resolution weaker than hypothesized; mostly
  arbitration-dominant

> **This retro is less about proving Resolution exists,**
> **more about refining what "Resolution" actually means.**

### Reframing — capability adjudication through Arbitration ↔ Resolution lens

Pre-Resolution-Surface reading: map_meta_cap is "the
adjudication engine that translates meta caps into primitive
checks plus contextual conditions." Cascade-aggregation retro
proposed Arbitration vs Resolution as paired operations.
Apply that lens here:

**Arbitration test for capability adjudication:**

> Stage 1 (Arbitration): What primitive authorities qualify
> for the requested meta capability under given context?

`map_meta_cap` IS this stage:
- Receives meta cap (`edit_post`) + context (`$post_id`)
- Analyzes context (post ownership, post status, current user)
- Returns required primitive cap set (`edit_posts` for own
  post; `edit_others_posts` for others' post)
- Multiple plugins may filter `map_meta_cap` to inject
  additional contextual conditions

map_meta_cap performs **Arbitration**: contextual analysis +
primitive cap selection.

**Resolution test for capability adjudication:**

> Stage 2 (Resolution): Which legitimacy state (allow/deny)
> becomes operational?

`map_meta_cap` does NOT perform this stage. The chain:

```
current_user_can( 'edit_post', $post_id )
   ↓
WP_User->has_cap( 'edit_post', $post_id )
   ↓
1. map_meta_cap → returns required primitive caps array
                  (Arbitration stage output)
   ↓
2. has_cap iterates required caps:
   for each required primitive cap:
      check user's cap membership
      apply user_has_cap filter
      determine pass/fail
   ↓
3. Aggregate iteration result → boolean allow/deny
   ↓
4. current_user_can returns boolean
```

**Resolution stage is in steps 2-4** — primitive cap iteration
+ filter application + final boolean derivation. This happens
in `WP_User->has_cap` and downstream functions, NOT in
`map_meta_cap`.

### Verification result: DISTRIBUTED (Outcome 2)

> **Capability adjudication exhibits Arbitration ↔ Resolution**
> **paired operations, but DISTRIBUTED across multiple**
> **functions/stages, NOT integrated into a single mechanism.**

Architectural locations:
- **Arbitration**: `map_meta_cap` (meta cap → primitive caps +
  context derivation)
- **Resolution**: `WP_User->has_cap` iteration +
  `user_has_cap` filter + final boolean aggregation

This is **structurally different** from cascade-aggregation
which exhibits **integrated** Arbitration + Resolution within
a single mechanism (CSS cascade).

**This is a SIGNIFICANT finding.** Capabilities + cascade
together suggest Resolution Surface manifests in TWO
architectural patterns:

| pattern | example | character |
|---|---|---|
| **Integrated Resolution** | CSS cascade | both stages in single mechanism |
| **Distributed Resolution** | Capabilities adjudication | stages distributed across multiple functions |

### RETROACTIVE INVARIANTS

#### A. Capability adjudication exhibits paired Arbitration + Resolution operations

The original chunk's "Arbitration Compiler" framing for
map_meta_cap is **structurally accurate** — but the chunk's
implicit assumption that "adjudication = single integrated
mechanism" needs refinement.

Adjudication = **paired operations distributed across
mechanisms**:
- map_meta_cap = arbitration mechanism (selects primitive
  cap requirements + context conditions)
- has_cap iteration / user_has_cap filter / boolean
  aggregation = resolution mechanism (operationalizes allow/
  deny outcome)

Both stages are present; both are necessary for capability
authority to become operational.

#### B. Resolution Surface manifests via DISTRIBUTED architecture (NEW finding)

This retro establishes that **Resolution Surface architecture
varies**:

```
Resolution Surface architectural variants:

INTEGRATED (single-mechanism):
   - CSS cascade (cascade-aggregation): arbitration +
     resolution co-located in cascade engine
   - Template hierarchy (site-building): arbitration +
     resolution co-located in get_query_template + first-
     existing-wins logic

DISTRIBUTED (multi-mechanism):
   - Capability adjudication: map_meta_cap (arbitration) +
     WP_User->has_cap iteration (resolution) — separate
     functions, separate locations, paired operationally
```

This is a **more sophisticated finding** than uniform
recurrence. Resolution Surface is not just "present in N
contexts" — it manifests through architectural variants.

#### C. Cross-domain Resolution Surface PRESENCE confirmed (3 contexts)

Updated cross-context PRESENCE evidence for Resolution Surface
candidate:

| context | manifestation | architecture |
|---|---|---|
| **site-building** (template hierarchy, explicit naming) | Resolution + Arbitration paired | Integrated |
| **style-engine** (cascade-aggregation, retro 1) | Resolution + Arbitration paired | Integrated |
| **plugin-dev** (capabilities-and-roles, retro 2 — THIS) | Resolution + Arbitration paired | **Distributed** |

3 bounded contexts now exhibit Resolution Surface. **2 of 3
are different architectural variants** (Integrated vs
Distributed). This breadth + architectural diversity is
strong evidence for cross-domain manifestation.

**Promotion candidate update**:

```
Resolution Surface (post-2nd-retro):
   Pre-retro: Surfaced (site-building) + cascade-aggregation
              latent (1st retro PRESENCE)
   Post-this-retro: 3-context PRESENCE confirmed:
                    - site-building (explicit, integrated)
                    - style-engine (latent, integrated)
                    - plugin-dev (latent, distributed)
   Updated status: STRONG candidate for Recurring
                   (cross-context) — 3 bounded contexts with
                   structurally clear manifestation
```

⚠ Promotion to Recurring (cross-context) status is
**warranted by evidence**, but requires governance decision:
- Strict reading: explicit forward authoring required
- Generous reading: retroactive verification with structural
  clarity sufficient

**Recommendation** (per Phase 7.5 Doctrine 3 Epistemic
Integrity): retroactive evidence with 3-context manifestation +
architectural diversity = **sufficient for Recurring (cross-
context) promotion**. Forcing forward chunks would be
over-constraint.

**Updated promotion**: Resolution Surface candidate **promoted
to Recurring (cross-context)** based on retroactive evidence.
Spec patch (Phase 7.6) should formalize.

#### D. Arbitration ↔ Resolution paired-operations doctrine candidate STRENGTHENED

The cascade-aggregation retro proposed Arbitration ↔
Resolution as candidate doctrinal refinement. This retro
**strengthens** that proposal:

| mechanism | arbitration mechanism | resolution mechanism | architecture |
|---|---|---|---|
| CSS cascade (cascade-aggregation) | specificity + order + !important | variable substitution + inheritance + computed style derivation | Integrated |
| template hierarchy (site-building) | hierarchy logic + candidate generation | first-existing-wins selection | Integrated |
| capability adjudication (this chunk) | map_meta_cap (meta → primitive + context) | has_cap iteration + filters + boolean aggregation | **Distributed** |
| block filter chain (block-filters) | priority arbitration | composed filter outputs → final settings | Integrated |
| menu_position arbitration (admin-menus) | numeric + filter ordering | rendered menu order | Integrated |

**5 mechanisms × paired operations confirmed.**

The architectural variant (Integrated vs Distributed)
suggests doctrine should be:

> **Arbitration and Resolution are paired operations.**
> **They may be co-located in a single mechanism**
> **(integrated architecture) or distributed across**
> **multiple mechanisms (distributed architecture).**

This is the **doctrinally precise formulation** that emerged
from honest retroactive verification.

#### E. Retroactive verification methodology validated (2 instances)

This retro is the **2nd successful application** of the
"retroactive candidate verification" methodology established
in cascade-aggregation retro:

```
Methodology pattern:
   1. Candidate surfaced in chunk N (site-building)
   2. Identify earlier chunks where pattern MAY be latent
      (cascade-aggregation, capabilities-and-roles)
   3. Retroactively verify presence per chunk
      (cascade: confirmed integrated;
       capabilities: confirmed distributed)
   4. Update candidate status (3-context PRESENCE)
   5. Multiple retros confirming → candidate may reach
      Recurring (cross-context) without explicit forward
      recurrence
```

**Both retros applied honestly:**
- cascade-aggregation: confirmed integrated paired operations
- capabilities-and-roles: confirmed distributed paired
  operations

The methodology distinguishes architectural variants honestly
rather than forcing uniform conclusions.

**Methodology now warrants explicit DSL spec recognition**
(Phase 7.6 patch consideration).

### Constitutional implications

**1. Resolution Surface promotion to Recurring (cross-context)**:

Based on 3-context retroactive PRESENCE + architectural
diversity (Integrated + Distributed), Resolution Surface
candidate is **promoted to Recurring (cross-context)** status.

This is the **third promotion event in KB**:
- 1st: Authority Interception Surface (Surfaced → Local)
  via slotfills
- 2nd: Authority Mediation Surface (Local → Recurring
  intra-context) via admin-menus
- **3rd: Resolution Surface (Surfaced → Recurring
  cross-context) via 2-retro verification (THIS)**

**Governance precedent**: 3rd promotion event uses
retroactive verification rather than forward authoring
density. This is methodologically novel; sets precedent for
future promotion events.

**2. Phase 7.6 spec patch becomes timely**:

With 2 retro verifications confirming Arbitration ↔
Resolution as paired operations + architectural diversity
finding, structural-patterns.md spec patch is warranted:

- Add **Arbitration ↔ Resolution** as documented paired
  operations doctrine
- Distinguish **Integrated** vs **Distributed** architectural
  variants
- Document **Retroactive Candidate Verification** methodology
- Promote Resolution Surface to KB-Wide candidate (cross-
  context Recurring + architecturally diverse)

This patch should follow Phase 7.5 patch chronology pattern:
explicit entry, 2-retro evidence, methodological discipline.

**3. Relationship to Arbitration Compiler KB-Wide law**:

Arbitration Compiler is currently KB-Wide (5+ instances × 4
contexts).

Resolution Surface (post-promotion candidate Recurring
cross-context) MAY become KB-Wide if:
- Audit verification across full KB
- Architectural variant taxonomy stabilizes
- Distinction from Arbitration Compiler is clearly maintained

If both reach KB-Wide: **paired KB-Wide laws** (Arbitration +
Resolution) — first paired-laws structure in KB.

⚠ Don't promote Resolution Surface to KB-Wide yet. Audit
required.

### KB-level coherence payoff

The 2 retro verifications produce structural narrative:

```
Resolution Surface origin story (refined post-2nd-retro):

1. site-building.template-hierarchy-and-resolution
   (2026-05-09 forward authoring): Resolution Surface
   surfaced explicitly from composition-native context.
2. cascade-aggregation retro 1 (2026-05-09): Resolution
   Surface verified as LATENT integrated in style-engine.
3. capabilities-and-roles retro 2 (2026-05-09 — THIS):
   Resolution Surface verified as LATENT distributed in
   plugin-dev.
4. Result: 3-context Resolution Surface presence with
   architectural variants. Candidate promoted to Recurring
   (cross-context) via retroactive verification.
5. Phase 7.6 spec patch becomes timely: formalize
   Arbitration ↔ Resolution as paired operations doctrine
   + Integrated vs Distributed variants.
```

**KB pattern strengthened**:

> Significant constitutional patterns are typically NOT
> discovered through novelty — they emerge through NAMING
> events that reveal latent structure already operating in
> earlier chunks. Discovery → naming → retroactive revelation
> → constitutional formalization.

This pattern now has explicit application across:
- Interactivity API (latent runtime architecture, per
  dynamic-rendering retro)
- HTML primacy (latent substrate, per markup-representation
  retro)
- **Resolution Surface (latent operationalization, per
  cascade + capabilities retros)**

### Methodological discipline preserved (with promotion event)

This retro:
- Honestly evaluated 3 possible outcomes (Confirmed /
  Distributed / Divergent)
- Concluded **Distributed** (not Confirmed integrated, not
  Divergent)
- Surfaced architectural diversity finding (Integrated vs
  Distributed Resolution)
- **Promoted Resolution Surface to Recurring (cross-context)**
  based on 3-context PRESENCE + architectural diversity
- Refused to over-credit map_meta_cap as integrated Resolution
- Acknowledged retroactive verification methodology validation
- Documented Phase 7.6 patch as timely (NOT yet applied)

**Promotion decision rationale**:
- 3 contexts × structurally clear manifestation = breadth +
  depth
- Integrated + Distributed variants = architectural
  diversity (anti-monoculture evidence)
- Retroactive verification methodology validated by 2 honest
  retros (cascade integrated; capabilities distributed)
- Phase 7.5 Doctrine 3 (Epistemic Integrity) supports
  retroactive evidence as valid

> **KB methodological maturity advances when retroactive**
> **evidence is treated as first-class — neither over-claimed**
> **(forcing all retros to confirm) nor under-claimed**
> **(rejecting all retroactive evidence). Both retros honestly**
> **applied yielded honest findings.**
