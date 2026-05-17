---
rule_id: plugin-dev.register-post-type
domain: plugin-dev
topic: extensibility
field_cluster: entity-constitution
wp_min: "2.9"
wp_recommended: "5.0+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/register_post_type/
    section: "register_post_type() — entity type registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/plugins/post-types/
    section: "Plugin Handbook — Custom Post Types"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/
    section: "Registering Custom Post Types — labels / capabilities / supports"
    captured: 2026-05-09
related:
  - plugin-dev.security-boundaries             # doctrine framework directly applied to CPT
  - plugin-dev.register-block-bindings-source  # federation layer 1 (origin)
  - plugin-dev.register-meta                   # federation layer 2 (persistence) — meta scoped via object_subtype
  - plugin-dev.register-rest-route             # federation layer 3 (transport) — show_in_rest hooks default REST routes
  - data-layer.entity-resolution               # registered post type becomes entity in entity store
  - data-layer.persistence                     # CPT participates in persistence reconciliation lifecycle
  - block-authoring.block-json.bindings        # bindings can target CPT meta via core/post-meta
  - (planned) plugin-dev.register-taxonomy     # classification federation hooks
  - (planned) plugin-dev.capabilities-and-roles # capability model deeper dive
---

# RULE — `register_post_type()` — entity constitution registration (federation stack closure)

## WHEN

A plugin or theme needs to introduce a new authority-bearing
entity kind into WordPress beyond core's posts / pages / media /
templates. Use this API when:

- The data has its own identity, lifecycle, capability model,
  and addressability — distinct from existing post types.
- Block bindings will target the type's meta or content.
- The type needs REST API exposure (modern editor integration).
- Custom URL routing / archives are part of the design.
- The type's capability model differs from generic post
  capabilities.

This is the **fourth plugin-dev chunk** in KB. With prior 3
federation layer chunks + the security doctrine capstone, this
chunk **closes the plugin-dev federation stack**:

```
authority federation stack (after this chunk):
   origin       ── register_block_bindings_source     ✓
   persistence  ── register_meta                       ✓
   transport    ── register_rest_route                 ✓
   governance   ── security-boundaries (doctrine)      ✓
   entity       ── register_post_type                  ✓ (THIS)
```

CPT registration is qualitatively different from prior 3
federation extensions: bindings source, meta, and REST route
extend EXISTING authority kinds (origin / persistence /
transport surfaces). **register_post_type declares NEW AUTHORITY
SUBJECTS** — entity species that did not previously exist in
WordPress's authority architecture.

This is **entity constitution registration** — the API declares
what kinds of authority-bearing entities may exist (their
rights, visibility, governance, transport, lifecycle).

The KB-level question pivot for this chunk:

> "What new content can plugins create?"
> reframes to:
> "**What new authority-bearing entities may plugins
> constitutionally federate into WordPress?**"

## SHAPE

### A. Identity constitution

```php
register_post_type( 'product', array(
    'label'        => __( 'Products', 'myplugin' ),
    'labels'       => array(
        'name'          => __( 'Products', 'myplugin' ),
        'singular_name' => __( 'Product', 'myplugin' ),
        'add_new_item'  => __( 'Add New Product', 'myplugin' ),
        // ... many more label variants
    ),
    'description'  => __( 'Sellable items in catalog', 'myplugin' ),
    'hierarchical' => false,                  // page-like vs post-like
    'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
) );
```

Identity declarations: post type slug (3rd-arg unique
identifier), display labels (translatable), hierarchical model
(parent-child relationships allowed), supported authoring
modules.

### B. Governance constitution

```php
register_post_type( 'product', array(
    // ... identity above ...
    'capability_type' => 'product',           // capability namespace
    'capabilities'    => array(
        'edit_post'         => 'edit_product',
        'edit_posts'        => 'edit_products',
        'edit_others_posts' => 'edit_others_products',
        'publish_posts'     => 'publish_products',
        'read_post'         => 'read_product',
        'delete_post'       => 'delete_product',
        // ... full capability mapping
    ),
    'map_meta_cap'    => true,                // primitive ↔ meta capability mapping
) );
```

| key | role |
|---|---|
| `capability_type` | declares capability namespace; `'product'` generates `edit_product`, `edit_products`, etc. |
| `capabilities` | explicit override map; finer-grained than capability_type alone |
| `map_meta_cap` | enables WordPress's primitive ↔ meta capability mapping (recommended `true`) |

### C. Exposure constitution

The exposure governance matrix — multi-dimensional declaration ≠
exposure:

| flag | authority dimension |
|---|---|
| `public` | global discoverability doctrine; default base for other flags |
| `publicly_queryable` | frontend query permeability (URL-accessible) |
| `exclude_from_search` | search-engine and on-site search visibility |
| `show_ui` | admin governance exposure (admin menu / list table) |
| `show_in_menu` | admin menu visibility (subordinate to show_ui) |
| `show_in_nav_menus` | nav-menu composition exposure |
| `show_in_admin_bar` | admin-bar shortcut visibility |
| `show_in_rest` | REST API transport exposure (modern editor integration) |
| `rest_base` | REST endpoint slug (defaults to post type) |
| `rest_namespace` | REST namespace (defaults to `wp/v2`) |

When `public: true` is set, several flags inherit defaults
(`publicly_queryable: true`, `show_ui: true`,
`show_in_nav_menus: true`, `exclude_from_search: false`). When
omitted, defaults are restrictive (post type effectively
private).

### D. Routing constitution

```php
register_post_type( 'product', array(
    'has_archive'     => true,                      // /products/ archive page
    'rewrite'         => array(
        'slug'        => 'products',
        'with_front'  => false,
        'feeds'       => true,
        'pages'       => true,
        'ep_mask'     => EP_PERMALINK,
    ),
    'query_var'       => true,
) );
```

Routing flags federate the entity into WordPress's URL space —
the entity becomes addressable / queryable / archivable as part
of the public site structure.

### E. Ecosystem composition

```php
register_post_type( 'product', array(
    'taxonomies' => array( 'product_category', 'product_tag' ),
    'template'   => array(
        array( 'core/heading', array( 'placeholder' => __( 'Product name' ) ) ),
        array( 'core/paragraph', array( 'placeholder' => __( 'Product description' ) ) ),
    ),
    'template_lock' => 'all',
) );
```

Composition declarations attach the entity to other federation
participants:
- `taxonomies` — which classification systems apply
- `template` — initial block tree for new instances
- `template_lock` — composition governance constraint (locks
  block structure)

Plus implicit composition via:
- `register_meta(..., 'object_subtype' => 'product')` — meta
  fields scoped to this type
- bindings sources resolving via `core/post-meta` against this
  type's instances

### Default behavior

If `$args` is empty `array()`, the post type is registered with
default options that are RESTRICTIVE (private, no UI, no REST,
no rewrite, default capability_type). For most use cases at
least `public`, `show_in_rest`, `supports`, `capability_type`
should be considered explicitly.

## REQUIRES

- Registration on the `init` action (or a sufficiently early
  hook). Late registration may miss UI / REST initialization.
- Post type slug:
  - Must be 1-20 characters.
  - Must NOT collide with reserved terms (`post`, `page`,
    `attachment`, `revision`, `nav_menu_item`, `custom_css`,
    `customize_changeset`, `oembed_cache`, `user_request`,
    `wp_block`, `wp_template`, `wp_template_part`, etc.).
  - Should namespace to avoid plugin collisions
    (e.g., `myplugin_product` rather than `product`).
- For `show_in_rest: true` to enable block editor: also need
  `supports: array('editor')` AND a registered template if
  custom blocks are required.
- For custom `capability_type`: capability membership must be
  granted to roles via `add_cap()` calls or role registration;
  registering the post type does NOT grant capabilities to
  any role automatically.
- `map_meta_cap: true` is strongly recommended for any
  non-trivial capability_type; without it, capability mapping
  is incomplete.
- For routing: flush_rewrite_rules() needed after registration
  in some scenarios (typically activation hook, NOT every
  request).
- ⚠ Specific behaviors for: per-language site post types,
  multisite network behavior, capability_type with custom
  meta capabilities, REST schema generation completeness,
  template_lock interaction with patterns — verification-needed.

## INVARIANTS

### 1. Custom post types declare new authority-bearing entity classes, NOT content labels

The load-bearing reframing for this chunk:

> register_post_type does NOT just add a "content type." It
> declares **a new entity class in WordPress's authority
> architecture** with its own:
> - identity (slug + labels + hierarchical model)
> - governance (capability schema)
> - exposure (multi-dimensional visibility declarations)
> - routing (URL space federation)
> - lifecycle (drafts / publish / trash / etc.)
> - composition (taxonomies / templates / supports)

Reading CPT as "label and content model" misses the authority
ontology. The CPT IS a new species of authority-bearing entity;
post instances are its individual subjects.

### 2. Entity registration federates new subjects into WordPress's authority graph

> register_post_type extends WordPress's authority graph with a
> NEW SUBJECT KIND. Every instance of the type becomes an
> authority subject in the federation — readable, writable,
> persistable, transportable, addressable through the same
> infrastructure that core entities use.

Implications:
- The type immediately participates in data-layer.entity-resolution
  (instances become getEntityRecord targets when show_in_rest).
- The type's instances participate in data-layer.persistence
  (full reconciliation lifecycle applies).
- Bindings can target instance attributes / meta.
- REST consumers can query instances at `/wp/v2/{rest_base}`.
- Block templates can reference the type for content.

The type doesn't request these integrations — it INHERITS them
by virtue of being registered through the entity-constitution
API.

### 3. Capability schemas govern entity-specific authority rights

`capability_type` + `map_meta_cap` constitute the **capability
namespace declaration + capability translation doctrine** for
the entity:

| concept | role |
|---|---|
| **capability namespace** (`capability_type`) | declares the entity's capability vocabulary (`edit_product`, `delete_product`, etc.) |
| **capability mapping** (`capabilities` array) | explicit assignment of WordPress primitive caps (edit_post, etc.) to entity caps |
| **meta capability translation** (`map_meta_cap: true`) | enables WordPress to translate meta capabilities (`edit_post` for instance N) to primitive capabilities (`edit_products` + ownership check for instance N) |

Without proper capability schema:
- Custom roles cannot have entity-specific permissions.
- Default behavior falls back to generic post capabilities
  (potentially over-permissive or under-permissive for the
  entity's actual governance needs).
- Security-boundaries doctrine cannot be applied per-entity
  (everything inherits generic post governance).

Custom capability_type is the entity's **right declaration** —
what rights exist for this kind of subject.

### 4. Exposure flags create multi-dimensional declaration ≠ exposure surfaces

The KB-recurring axis (declaration ≠ exposure) reaches its
**most-multidimensional instance** in CPT exposure flags:

```
ENTITY EXISTS (declaration: register_post_type called)
   │
   ├─ public ─── meta-flag affecting multiple sub-flags
   │
   ├─ publicly_queryable    ── frontend query permeability
   ├─ exclude_from_search   ── search visibility
   ├─ show_ui               ── admin governance exposure
   ├─ show_in_menu          ── admin menu visibility
   ├─ show_in_nav_menus     ── nav menu composition
   ├─ show_in_admin_bar     ── admin bar shortcut
   ├─ show_in_rest          ── REST transport exposure
   └─ has_archive           ── archive page existence
```

Each flag governs a DIFFERENT exposure dimension. They can be
set independently to express nuanced governance:
- An entity can be DECLARED but not EXPOSED in any dimension
  (private internal data: all flags false).
- An entity can be REST-exposed but admin-hidden (show_in_rest:
  true, show_ui: false — for headless / API-only entities).
- An entity can be admin-exposed but REST-hidden (legacy
  pattern, generally discouraged for modern editor support).

CPT chunks should explicitly enumerate exposure dimensions —
defaults often produce unintended visibility profiles.

### 5. Supports declare operational authority modules, NOT cosmetic features

The `supports` array declares which **operational authority
modules** attach to this entity type:

| support value | authority module attached |
|---|---|
| `title` | titled-content authority |
| `editor` | block-editor content authority + auto-attaches REST integration |
| `excerpt` | summary content authority |
| `thumbnail` | featured image authority |
| `revisions` | revision/history authority (data-layer.persistence revision integration) |
| `author` | authorship attribution authority |
| `comments` | comment-attachment authority |
| `custom-fields` | classic custom-fields meta UI surface (governance-relevant!) |
| `page-attributes` | menu_order + parent (with hierarchical) |
| `post-formats` | format-classification authority |
| `trackbacks` | trackback authority |

Each support value attaches a SPECIFIC authority module to the
entity. Reading supports as "features to enable in the editor"
misses that each module brings its own:
- UI surface (governance-relevant)
- Capability requirements
- Schema implications
- REST integration

`supports: array('editor')` is the minimum for block editor
integration. `supports: array('custom-fields')` exposes the
classic custom fields metabox — security-relevant
(post-Phase-7 KB framing: registered meta with proper
governance is the modern path; classic Custom Fields metabox
exposes ALL post meta including unregistered keys).

### 6. Routing flags federate entities into public address space

`has_archive`, `rewrite`, `query_var` declare the entity's
**federation into URL space**:

```
non-routed entity:
   - exists in DB
   - accessible via admin / REST
   - NOT addressable via public URL

routed entity (rewrite + query_var):
   - public URL: /{rewrite-slug}/{instance-slug}/
   - archive URL: /{rewrite-slug}/ (when has_archive)
   - feed URLs (when rewrite.feeds: true)
   - permalink ep_mask integration
   - participation in main_query
```

URL space federation is a **public-facing authority
declaration** — the entity announces itself as a citizen of the
site's URL graph. Implications:
- SEO consequences (URL discoverability + indexing).
- Permalink structure conflicts (if rewrite.slug collides with
  existing routes).
- Frontend query integration (template hierarchy applies).
- Archive template resolution.

This goes beyond REST exposure (transport) — routing exposes
the entity to the **frontend public addressability layer**.

### 7. CPTs are governed constitutions, NOT mere schema extensions

KB-level positioning invariant:

> A registered post type is **a constitution**:
> - Identity (what is this entity?)
> - Rights (what capabilities apply?)
> - Visibility (where may it appear?)
> - Routing (where does it live in URL space?)
> - Composition (what other federation participants attach?)
> - Lifecycle (drafts, publish, trash, scheduled, autosave)
>
> Reading register_post_type as "schema declaration" understates
> what the API actually does. It's a multi-dimensional
> governance constitution declaration for a new authority
> subject kind.

Direct application of security-boundaries doctrine:

| security-boundaries tier | CPT projection |
|---|---|
| **Trust** (should this exist?) | should this entity kind be federated into WordPress's authority architecture? |
| **Legitimacy** (is shape/access correct?) | is the capability schema coherent? are exposure flags appropriate? are supports modules correct? |
| **Permeability** (may authority cross boundary?) | which exposure surfaces are appropriate? at what capability level? |

Each CPT registration is a constitution-writing event with
security implications at every dimension.

### 8. register_post_type closes plugin-dev's authority federation stack

KB-level closure invariant:

```
authority federation stack — COMPLETE after this chunk:

   origin       ── register_block_bindings_source     ✓
                  (NEW authority sources)

   persistence  ── register_meta                       ✓
                  (NEW authority persistence slots)

   transport    ── register_rest_route                 ✓
                  (NEW authority transport boundaries)

   governance   ── security-boundaries (doctrine)      ✓
                  (HOW federation is governed)

   entity       ── register_post_type                  ✓ (THIS)
                  (NEW authority subject kinds)
```

After this chunk, plugin-dev has the **complete federation
extensibility surface** documented:
- Origin: where authority comes from
- Persistence: where it lives
- Transport: how it moves
- Governance: how it is trusted/validated/protected
- Entity: what kinds of authority subjects exist

Subsequent plugin-dev chunks (taxonomy / capabilities / nonces /
hooks / filters / slotfills) extend WITHIN this federation stack
rather than adding new layers. The stack is structurally
complete.

## VERIFICATION NEEDED

`status: stable` — register_post_type API itself is mature
(WP 2.9+) with stable contract. Specific implementations
that may vary or evolve:

- Per-language / multilingual plugin behavior with CPTs
  (WPML, Polylang interaction).
- Multisite network-active CPT registration vs site-active.
- REST schema generation completeness for complex `supports` /
  `capability_type` combinations.
- template_lock interaction with patterns + reusable blocks.
- Block-template assignment to CPTs (block themes).
- `post_status` extensions (custom statuses via
  `register_post_status`) and their interaction with capabilities.
- Capability cache behavior when role capabilities change
  mid-session.
- Performance with very many registered post types.
- REST controller customization (`rest_controller_class`)
  semantics.
- Trash / delete cap mapping with custom capability_type.
- Auto-draft behavior for custom post types.
- WP-CLI behavior with custom post types and capability checks.
- Behavior when post type registration is deregistered
  (`unregister_post_type`) — data persistence vs API removal.

For practical decisions: empirical testing per scenario
(register the type, audit REST schema, verify capability
behavior with test users) over inferred behavior.

## ANTIPATTERNS

- ❌ **Treating CPT as "just custom content"**. CPT registration
  is constitution writing — capability schema, exposure
  governance, routing federation. Skipping these dimensions
  produces misconfigured entities with security or UX issues.
- ❌ **`public: true` = safe**. `public` flips multiple flags to
  permissive defaults including frontend query exposure,
  search visibility, public URL space participation. Audit
  whether these are appropriate.
- ❌ **`show_ui: true` = permission**. show_ui is admin UI
  governance exposure; capability checks at the request
  boundary (admin / REST permission_callback) are separate
  enforcement. Hiding UI does NOT secure data.
- ❌ **`show_in_rest: true` = modern by default**. Exposing CPT
  via REST requires capability checks at the REST layer; meta
  fields exposed via `register_meta(..., 'show_in_rest' => true,
  'object_subtype' => 'product')` need their own
  auth_callback. show_in_rest enables transport, not security.
- ❌ **`supports: array('editor')` = enough**. For custom CPTs
  with structure expectations, also consider templates,
  template_lock, taxonomies, custom-fields module implications,
  and which other supports modules attach appropriate authority.
- ❌ **`capability_type: 'custom_thing'` alone = security**.
  Custom capability_type DECLARES the namespace; capability
  membership must be granted to roles separately. Without
  add_cap calls, NO ROLE has the new capabilities — the
  entity is uneditable for everyone (including admins) until
  capabilities are granted.
- ❌ **`rewrite: ...` = SEO only**. Rewrite slugs participate in
  WordPress's URL space; collisions with existing routes break
  navigation. Test with permalinks set to various structures.
- ❌ **Skipping `map_meta_cap: true`**. Without it, meta
  capabilities (per-instance permissions) are not mapped to
  primitive capabilities; capability checks on individual
  posts may behave incorrectly.
- ❌ **Reserved post type slug usage**. Using `post`, `page`,
  `attachment`, `wp_template`, etc. (or even subtle near-misses)
  causes registration to fail or collide unpredictably.
- ❌ **Post type slug without namespace**. Using `product`
  rather than `myplugin_product` collides with other plugins
  that register the same name. Namespace is good ecosystem
  hygiene.
- ❌ **Calling `flush_rewrite_rules()` on every request**.
  Heavy operation; only call on plugin activation hook (or
  similar one-time hook). Rewrite rules are cached.
- ❌ **Registering CPT inside REST callbacks or render
  callbacks**. Late registration misses initialization
  windows. Register on `init`.
- ❌ **Reusing existing capability_type without considering
  governance fit**. Setting `capability_type: 'post'` reuses
  WordPress post capabilities (edit_posts etc.) for the new
  type. May be appropriate, but loses entity-specific
  governance — anyone who can edit posts can edit this type.
- ❌ **Forgetting taxonomy registration when CPT depends on
  taxonomies**. Listing in `taxonomies` array does NOT
  register the taxonomy; use register_taxonomy separately.

## RELATED

- `plugin-dev.security-boundaries` — doctrine framework
  directly applied to CPT (invariant 7). Trust / legitimacy /
  permeability tiers project onto CPT registration decisions.
- `plugin-dev.register-block-bindings-source` — federation
  layer 1. CPT instances become potential targets for binding
  sources resolving via core/post-meta against the type.
- `plugin-dev.register-meta` — federation layer 2. Meta scoped
  to CPT via `object_subtype: 'product'`. CPT + register_meta
  + bindings = end-to-end custom content pipeline.
- `plugin-dev.register-rest-route` — federation layer 3.
  show_in_rest: true auto-creates default REST routes for the
  CPT; custom routes can complement or replace via
  rest_controller_class.
- `data-layer.entity-resolution` — registered CPT becomes
  entity in entity store; getEntityRecord('postType', 'product',
  $id) works after registration.
- `data-layer.persistence` — CPT instances participate in full
  persistence reconciliation lifecycle (edit buffer, save
  dispatch, REST request, capability check, sanitization, DB
  write, revision, response).
- `block-authoring.block-json.bindings` — bindings can target
  CPT meta via core/post-meta source.
- (planned) `plugin-dev.register-taxonomy` — classification
  federation hooks; the most natural next chunk.
- (planned) `plugin-dev.capabilities-and-roles` — capability
  model deeper dive (referenced in invariant 3; warrants
  dedicated treatment).

## META

**plugin-dev bounded context — federation stack closure (4/4 +
governance doctrine).**

```
plugin-dev (after this chunk):
   register-block-bindings-source  → trust / origin           ✓
   register-meta                   → legitimacy / persistence  ✓
   register-rest-route             → permeability / transport  ✓
   security-boundaries             → governance doctrine       ✓
   register-post-type              → entity / federation 4/4   ✓
   ↓
   FEDERATION STACK STRUCTURALLY COMPLETE.
```

**KB-level framing extension:**

> If `register_meta` governs **what authority may persist**,
> and `register_rest_route` governs **how authority may travel**,
> then `register_post_type` governs **what kinds of
> authority-bearing subjects may exist**.

This three-API trio (with security-boundaries as cross-cutting
doctrine) constitutes plugin-dev's authority federation
extensibility surface. Subsequent plugin-dev chunks (taxonomy,
capabilities, nonces, hooks, filters, slotfills) extend WITHIN
this surface, not beyond it.

**Classic API → modern reframing payoff:**

register_post_type is the most-classic of the documented
plugin APIs (WP 2.9+, ubiquitous in plugin development).
Without security-boundaries written first, this chunk would
have read as classic content modeling. With doctrine in place,
it reads as **entity constitution registration in a federated
authority architecture**.

This positioning was the strategic value of the
security-boundaries-before-CPT sequence (per user guidance):
- Classic API would have anchored CPT as content model.
- Doctrine-first approach anchors CPT as entity constitution.

**KB-wide ontology synthesis:**

After this chunk, KB has documented:
- Authority architecture (Phases 1-7)
- Authority governance (plugin-dev capstone + federation)
- Authority subject extensibility (this chunk closes it)

The ontology supports the question:
> "What does WordPress's authority architecture look like as a
> system, including how plugins extend it?"

Answer is now structurally complete in KB across:
- Internal authority (block-authoring + theme-config +
  style-engine substrate)
- Runtime authority (data-layer + interactivity)
- External authority (plugin-dev federation stack)

**Status `stable`:**

CPT API is mature; doctrine framing is stable. Specific
multilingual / multisite / version-dependent behaviors are
verification-needed (cataloged) but the core API contract is
settled.

**DSL extensions applied:** VERIFICATION NEEDED + META, per
the runtime/implementation-derived applicability rule (CPT
behavior has many implementation-derived edge cases even with
stable contract).

**Anticipated next chunks (priority):**

1. **`plugin-dev.register-taxonomy`** — classification
   federation hooks. The natural pair with CPT; registers
   taxonomies that may attach to CPTs via the `taxonomies`
   parameter. Closes the entity-classification federation
   layer.

2. **`plugin-dev.capabilities-and-roles`** — capability model
   deeper dive. Referenced extensively in invariants 3 + 7.
   Now warranted as dedicated chunk after CPT establishes
   capability_type usage.

3. **`plugin-dev.nonces`** — CSRF protection deeper dive.
   Mechanism + lifetime + scoping. Referenced in
   security-boundaries.

4. **Other plugin-dev families** — block filters, slotfills,
   hooks, settings — all extend within established federation
   doctrine.

5. **Other bounded contexts** (additive): editor-customization /
   site-building / i18n / build-tooling / admin-ui.

Recommended next: register-taxonomy (immediate composition with
CPT; closes entity-classification federation). Then
capabilities-and-roles (capability model formalization). Then
choice between deepening plugin-dev or entering additive
bounded contexts.

**KB self-evaluation against spec criteria:**

- ✅ Accuracy — describes documented API contract.
- ✅ Structural fit — closes plugin-dev federation stack;
  applies security doctrine; positions CPT in authority
  architecture.
- ✅ Reusability — uses authority ontology glossary
  (constitution / federation / subject / governance / exposure /
  routing / authority).
- ✅ Phase fit — federation stack closure; references all
  prior plugin-dev chunks + relevant data-layer + bindings
  chunks.
- ✅ Doctrine respect — directly applies security-boundaries
  doctrine to CPT (invariant 7); HTML primacy implicit (CPT
  rendered through templates which are HTML-primary).
