---
rule_id: plugin-dev.register-meta
domain: plugin-dev
topic: extensibility
field_cluster: persistence-substrate
wp_min: "4.6"
wp_recommended: "5.5+"
status: evolving
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/register_meta/
    section: "register_meta() — register meta key for an object type"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/register_post_meta/
    section: "register_post_meta() — convenience wrapper for post type meta"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/#registering-meta
    section: "Exposing meta via REST API — show_in_rest semantics"
    captured: 2026-05-09
related:
  - plugin-dev.register-block-bindings-source  # twin: bindings source = origin; register_meta = persistence slot
  - data-layer.persistence                     # persistence substrate this API extends
  - data-layer.entity-resolution               # registered meta becomes available through entity-resolution
  - block-authoring.block-json.bindings        # bindings can target meta via core/post-meta source
  - (planned) plugin-dev.rest-route-permission # show_in_rest exposure interacts with REST permission ontology
  - (planned) plugin-dev.security-boundaries   # sanitize_callback + auth_callback = authority legitimacy enforcement
  - (planned) plugin-dev.register-post-type    # CPTs may declare object_subtype scope for meta
---

# RULE — `register_meta()` — schema-governed persistence authority surface

## WHEN

A plugin or theme needs to persist custom data attached to a
WordPress entity (post / user / term / comment) AND have that
data participate as a first-class authority surface — not as
arbitrary opaque storage. Use this API when:

- Custom fields should be exposed via REST API for editor or
  external consumption.
- Block bindings will target the meta via `core/post-meta` source.
- Sanitization / authorization rules need to govern who can
  read or write the field.
- The data has a declared schema (type, single vs array, default
  value) that consumers should rely on.

This is the **second plugin-dev chunk** in KB. Together with
`register_block_bindings_source` (authority origin federation),
this chunk establishes the **authority federation stack** at
the persistence layer. Where bindings source registration
externalizes Gutenberg's authority origin set, register_meta
externalizes its **persistence substrate**.

Classic perception: "post meta = custom fields key/value store."
Modern KB framing: **schema-declared persistence authority
surface** — the API governs WHAT can be persisted, HOW it is
sanitized, WHO can access it, and WHERE it appears (REST,
editor entity, bindings).

## SHAPE

### Registration call

```php
register_meta( 'post', 'my_custom_field', array(
    'object_subtype'    => 'page',           // optional — scope to specific post type
    'type'              => 'string',          // schema type
    'description'       => __( 'My field description', 'myplugin' ),
    'single'            => true,              // one value (vs array of values)
    'default'           => '',
    'sanitize_callback' => 'sanitize_text_field',
    'auth_callback'     => '__return_true',
    'show_in_rest'      => true,              // boolean OR array config
    'revisions_enabled' => true,              // WP 6.4+
) );
```

Convenience wrapper for post types:

```php
register_post_meta( 'page', 'my_custom_field', $args );
```

### Parameters by ontology role

#### A. Registration scope

| key | role |
|---|---|
| `$object_type` (1st arg) | top-level object: `post`, `user`, `term`, `comment` |
| `object_subtype` (in $args) | optional narrowing: specific post type, taxonomy, etc. |

#### B. Persistence schema

| key | role |
|---|---|
| `type` | data type contract: `string`, `integer`, `number`, `boolean`, `array`, `object` |
| `single` | `true` = one scalar value; `false` = array of values |
| `default` | value when meta is unset |
| `description` | human-readable schema description |

#### C. Exposure governance

| key | role |
|---|---|
| `show_in_rest` | gate for REST API exposure; `true` for default schema OR detailed array for fine-tuned schema |
| `revisions_enabled` | (WP 6.4+) include meta in post revisions |

#### D. Security governance

| key | role |
|---|---|
| `sanitize_callback` | normalizes / filters values BEFORE persistence (authority shape governance) |
| `auth_callback` | gates WHO can edit this meta (authority access governance) |

#### E. Runtime integrations (consequences of registration)

When properly registered, the meta becomes available across:
- **REST API**: `meta.{key}` field in entity response (when
  `show_in_rest`)
- **Editor entity**: accessible via `getEditedEntityRecord(...).meta`
- **Block bindings**: targetable via
  `{ "source": "core/post-meta", "args": { "key": "my_custom_field" } }`
- **Revisions**: included in revision snapshots (when
  `revisions_enabled`)

### show_in_rest detailed schema form

```php
'show_in_rest' => array(
    'schema' => array(
        'type'  => 'object',
        'properties' => array(
            'first_name' => array( 'type' => 'string' ),
            'last_name'  => array( 'type' => 'string' ),
        ),
    ),
),
```

Use the array form when meta has structured shape that REST
consumers should validate against.

## REQUIRES

- Registration on the `init` action (or earlier appropriate hook).
  Late registration may miss REST schema generation.
- For `show_in_rest`: object type's REST endpoint must support
  meta (post types need `'show_in_rest' => true` in
  `register_post_type`; default supported types include `post`,
  `page`).
- `sanitize_callback` MUST handle all input forms safely
  (typed coercion, length limits, character escaping per the
  declared `type`).
- `auth_callback` signature: `function ( $allowed, $meta_key,
  $object_id, $user_id, $cap, $caps )` returning boolean.
  Returning `true` permits access.
- For `single: false` (array meta), values are stored as
  multiple meta rows; reads return array; serialization /
  ordering semantics differ from single meta.
- ⚠ Exact behavior when re-registering the same meta key
  (override semantics, error conditions), `revisions_enabled`
  internals, REST schema integration with custom validation —
  verification-needed.

## INVARIANTS

### 1. Meta registration declares persistence authority surfaces

Pre-register_meta perception:

> "Meta is opaque key/value storage on entities."

Post-Phase-7 KB framing:

> register_meta declares a **persistence authority surface** —
> a schema-governed slot in the data-layer persistence substrate
> with explicit type contract, sanitization rules, access
> governance, and exposure declarations.

Unregistered meta still works (you can call `update_post_meta`
without any registration), but it is **outside the schema-
governed authority architecture**: not visible to REST by
default, not safely consumable by bindings, not subject to the
sanitization/auth contract that registered meta declares.

The choice between unregistered and registered meta is
qualitative — the latter participates in Gutenberg's authority
federation; the former does not.

### 2. Meta keys become schema-governed authority slots, NOT arbitrary storage

This invariant generalizes #1:

> A registered meta key is **a typed authority slot** with
> declared persistence schema, NOT a free-form storage location.

Implications:
- Type contract (`type: 'integer'`) means consumers can rely
  on the value being an integer (subject to sanitization
  enforcement).
- Single vs array (`single: true|false`) is a structural
  contract; consumers know the shape.
- Default value is part of the contract — when meta is unset,
  consumers see the declared default rather than `''` or
  `null`.

This is structurally similar to how `block.json attributes`
declares typed attribute schemas: register_meta declares typed
persistence schemas. Both systems govern WHAT can be stored
and HOW it is shaped.

### 3. Declaration ≠ exposure ≠ accessibility

KB-recurring axis applies again. Three independent surfaces:

| surface | controlled by |
|---|---|
| **declaration** (meta is registered as authority surface) | `register_meta()` call |
| **exposure** (meta is visible via REST + editor entity) | `show_in_rest` argument |
| **accessibility** (specific user can read/write) | `auth_callback` |

Each is governed independently:
- A meta can be DECLARED but not EXPOSED (registered without
  `show_in_rest` — used internally by plugin only).
- A meta can be DECLARED + EXPOSED but not ACCESSIBLE to a
  specific user (auth_callback rejects).
- A meta can be EXPOSED via REST yet INACCESSIBLE if the user
  fails the entity's general capability check (REST permission_
  callback for the entity type).

This is the same declaration ≠ exposure pattern documented for
capabilities (supports / appearanceTools), variations (scope),
templates (postTypes), etc. — recurring at the persistence
layer.

### 4. Sanitization and authorization are authority legitimacy gates

`sanitize_callback` and `auth_callback` are NOT just validation
helpers — they are **authority legitimacy enforcement** at the
persistence boundary:

| callback | governs | semantics |
|---|---|---|
| `sanitize_callback` | authority shape | "what shape may this authority take?" — input filtering, type coercion, length limits |
| `auth_callback` | authority access | "who may exercise authority over this slot?" — capability checks, ownership checks |

These are the **second-tier security framing** in plugin-dev
(after register_block_bindings_source's first introduction).
The framing extends:

> Plugin extensibility introduces authority into the federation.
> Sanitization governs whether the authority is legitimate
> in shape; authorization governs whether the actor is
> legitimate to exercise it.

Both callbacks are the plugin author's responsibility — the
runtime invokes them but does not provide defaults that fit
arbitrary use cases. Skipping either creates an unsafe
authority surface in the federation.

### 5. Meta registration extends data-layer persistence substrate externally

`data-layer.persistence` documented WordPress's persistence
substrate as a 6-stage reconciliation pipeline operating on
canonical entity state. `register_meta` is the **plugin-side
extension point** for that substrate:

> Plugins extend the persistence substrate by registering new
> typed authority slots that participate in the same
> reconciliation lifecycle as core entity fields.

When a registered meta is edited via `editEntityRecord` and
saved via `saveEditedEntityRecord`, the full data-layer.
persistence pipeline applies:
- Edit buffer accumulates changes.
- Save dispatches REST request.
- Server-side capability + sanitization (via auth_callback +
  sanitize_callback).
- DB write through update_meta family functions.
- Revision creation if revisions_enabled.
- Response flushes edit buffer.

Plugins inherit the entire reconciliation behavior; they do
not reimplement persistence semantics.

### 6. Bindings + meta = end-to-end custom authority pipeline

Composition of register_block_bindings_source + register_meta
creates an end-to-end custom authority pipeline:

```
Plugin registers meta (this chunk)
   ↓
Meta becomes available in entity store + REST + bindings
   ↓
Plugin registers binding source (or uses core/post-meta) targeting the meta
   ↓
Block.json bindings declarations target { source, args: { key } }
   ↓
Block instance projects meta value into rendered HTML
   ↓
Editor edits propagate through editEntityRecord(...) → save
   ↓
sanitize_callback + auth_callback gate at persistence boundary
   ↓
Persisted; reconciliation lifecycle completes
   ↓
Other clients eventually re-fetch updated value
```

This is a **complete distributed authority pipeline** built
entirely from plugin-side primitives, plugged into Gutenberg's
core federation. Plugin authors don't reimplement the
authority architecture — they declare extensions to it.

### 7. Meta is a persistence ABI for plugin-defined authority

Parallels established earlier in KB:

| layer | ABI |
|---|---|
| style-engine | `--wp--preset--*` CSS variables (runtime ABI between authored declarations and rendered cascade) |
| **register_meta** | **meta key + type + single + default + sanitize + auth (persistence ABI between plugin authority and entity store)** |

A registered meta key is more than a DB row label — it is a
**persistence ABI**: a declared contract that other actors
(REST consumers, editor UI, bindings, plugins, themes) can rely
on.

Implications:
- Meta key + schema is the API surface; renaming or removing
  it is breaking change.
- Plugins / themes can compose against the ABI without
  knowledge of the implementation.
- The ABI persists across sessions, requests, environments —
  same contract everywhere.

### 8. Classic custom fields are legacy perception; registered meta is modern governance

A KB-level positioning invariant for plugin-dev:

> "Custom fields" as classic WordPress UI affordance (the
> Custom Fields metabox in classic editor) treats meta as
> opaque key/value storage. Modern Gutenberg ontology treats
> registered meta as **schema-governed authority surface**.
>
> The two perspectives describe the same DB rows but project
> different semantics. Plugin-dev chunks should use the modern
> framing (governance / authority surface) rather than the
> classic framing (key/value bag).

This positioning is plugin-dev-bounded-context-specific. It
does NOT mean classic custom fields are wrong — it means they
are a **different ontological reading** of the same mechanism.
KB chunks reflect the Gutenberg-native reading.

## VERIFICATION NEEDED

`status: evolving` — registration API itself is mature (WP
4.6+), but:

- Exact override semantics when same meta key is registered
  multiple times (do later calls override or merge?).
- `revisions_enabled` exact behavior with array meta and
  complex types.
- REST schema integration with custom validation patterns
  (when does WP fall back to default vs use declared schema?).
- Editor entity hydration timing for newly registered meta
  (does the editor pick up new registrations during a
  session?).
- Behavior when `auth_callback` rejects but the user has the
  entity-level capability — which gate dominates.
- Performance characteristics of `single: false` (array) meta
  with many values.
- Plugin deactivation behavior — meta keys remain in DB, but
  schema / sanitization / auth governance disappears.
- Migration patterns when meta schema changes (type change,
  single→array, etc.).
- Cross-plugin namespace collision handling — what if two
  plugins register the same meta key?
- Coexistence with classic Custom Fields metabox UI in
  block editor / classic editor mixed environments.

For practical decisions: prefer empirical testing
(register the meta, inspect REST response, attempt edits with
varied capabilities) over inferred behavior.

## ANTIPATTERNS

- ❌ Treating meta as opaque storage and skipping registration.
  Unregistered meta is invisible to REST, unsafe for bindings,
  and lacks the sanitization / authorization contract.
  Acceptable only for true plugin-internal scratch state.
- ❌ Registering meta without `sanitize_callback`. The default
  is no sanitization; arbitrary data flows to DB. XSS / data
  corruption surface.
- ❌ Setting `auth_callback` to `'__return_true'` for sensitive
  meta. Removes access governance; any user with edit capability
  on the entity can write the meta. Use a real capability check.
- ❌ Setting `show_in_rest: true` without considering exposure
  implications. The meta becomes readable by anyone with REST
  read access to the entity; sensitive data should NOT be
  exposed by default.
- ❌ Renaming a registered meta key after deployment. The key
  is the persistence ABI; renaming breaks REST consumers,
  bindings declarations, editor integrations.
- ❌ Changing `type` or `single` on a registered key after data
  exists. Existing data in the DB doesn't auto-migrate;
  consumer expectations break.
- ❌ Registering meta inside a render callback or REST endpoint.
  Late registration misses REST schema generation; the meta may
  be invisible to early-loading editor code. Register on `init`.
- ❌ Forgetting `object_subtype` when meta should be type-
  specific. Without it, the meta appears on all post types
  (or relevant subtypes); UI and validation may not match.
- ❌ Using `register_post_meta` without considering revisions.
  Meta registered without `revisions_enabled` is NOT included
  in revisions; editor revision navigation won't reflect the
  meta state.
- ❌ Treating sanitize_callback as the only validation. The
  callback runs at persistence; client-side validation is
  separate. Both layers may need parallel logic.
- ❌ Storing structured / nested data via single-string meta
  with manual JSON encoding. Use `type: 'object'` /
  `type: 'array'` with REST schema declaration; gives
  consumers proper structure.

## RELATED

- `plugin-dev.register-block-bindings-source` — twin authority
  registration. Bindings source = authority origin; register_meta
  = authority persistence slot. The two compose to form
  end-to-end custom authority pipelines (invariant 6).
- `data-layer.persistence` — persistence substrate this API
  extends. Plugins inherit the full reconciliation lifecycle
  by registering meta; they don't reimplement persistence.
- `data-layer.entity-resolution` — registered meta becomes
  available through entity-resolution selectors
  (getEntityRecord, getEditedEntityRecord). The entity store
  consumes registered meta as part of entity records.
- `block-authoring.block-json.bindings` — bindings can target
  meta via core/post-meta source; this requires the meta to
  be registered (and typically `show_in_rest`).
- (planned) `plugin-dev.rest-route-permission` —
  `show_in_rest` exposure interacts with REST permission
  ontology. permission_callback at the entity REST route +
  auth_callback at the meta level form layered access governance.
- (planned) `plugin-dev.security-boundaries` — sanitize_callback
  + auth_callback are second-tier surfacings of security
  ontology. After 3+ plugin-dev chunks reference shared
  security patterns, formalize as cross-cutting chunk.
- (planned) `plugin-dev.register-post-type` — CPTs may scope
  meta via `object_subtype`. Together they form entity schema
  + persistence schema extension.

## META

**plugin-dev bounded context — second chunk.**

```
plugin-dev (after this chunk):
   register-block-bindings-source  → authority origin federation         ✓
   register-meta                   → persistence substrate extension     ✓
   ↓
   (NEXT) register-rest-route      → transport boundary registration
   (NEXT) register-post-type       → entity schema extension (re-framed)
```

**Authority federation stack emerging:**

| stack layer | API | role |
|---|---|---|
| **origin** | register_block_bindings_source | declares NEW authority origins federation can attach to |
| **persistence** | register_meta (THIS) | declares NEW authority persistence slots in entity substrate |
| (NEXT) **transport** | register_rest_route | declares NEW transport boundaries for authority traversal |
| (NEXT) **entity schema** | register_post_type | declares NEW entity kinds with their own authority surfaces |

After this chunk, plugin-dev has its first 2 layers of the
**authority federation stack**. Subsequent chunks complete the
stack.

**Security ontology second surfacing:**

This chunk extends the security framing introduced by
register_block_bindings_source:
- First chunk: introduced trust/permission boundaries (general).
- **This chunk: explicit `sanitize_callback` + `auth_callback`
  as authority legitimacy enforcement** (specific mechanisms).

Pattern: each plugin-dev chunk surfaces security at the
specific boundary it governs. After 3 chunks have surfaced
security from different angles, formalize cross-cutting chunk
`plugin-dev.security-boundaries`.

**KB-level framing extension (plugin-dev domain identity):**

> Plugin-dev bounded context is the **external authority
> architecture** layer of WordPress. Registration APIs
> extend Gutenberg's authority federation in 4 dimensions:
> origin (where authority comes from), persistence (where it
> lives), transport (how it moves), entity schema (what kinds
> exist).
>
> Plugins are not procedural extensions to a closed system —
> they are federation participants in WordPress's authority
> architecture, governed by registration contracts at each
> dimension.

This framing is now anchored in 2 chunks (origin + persistence)
and ready for further chunks to extend coherently.

**KB self-evaluation against spec criteria:**

- ✅ Accuracy — describes documented API contract.
- ✅ Structural fit — extends Phase 7 + plugin-dev ontology
  via authority federation stack.
- ✅ Reusability — uses authority ontology glossary
  (authority, attachment, projection, substrate, federation,
  reconciliation).
- ✅ Phase fit — Phase 7 follow-up; references data-layer.
  persistence + bindings substrate appropriately.
- ✅ Doctrine respect — implicit HTML primacy preserved
  (meta values flow through HTML attribute projection); no
  SPA framing.

**DSL extensions applied:** VERIFICATION NEEDED + META, per
runtime/implementation-derived applicability rule.

**Status `evolving`** — register_meta itself is mature, but
schema integration, REST extension, revisions semantics,
editor integration evolve per WP version.

**Anticipated next chunks (priority):**

1. **`plugin-dev.rest-route-permission`** — third chunk;
   transport boundary + permission_callback. Will deepen
   security ontology to threshold for cross-cutting
   `plugin-dev.security-boundaries` chunk.

2. **`plugin-dev.register-post-type`** — entity schema
   extension. Closes the 4-layer authority federation stack.
   May warrant retroactive section after authoring (CPT is
   classic WP API; modern reframing benefits from KB context).

3. **`plugin-dev.security-boundaries`** — formalize when
   3+ chunks reference security; consolidates trust/capability/
   sanitization patterns.

4. Other plugin-dev families (filters, slotfills, hooks) on
   demand.

Recommended next: `register-rest-route` (transport layer
completes the read/write/transport triangle of authority
federation and provides the third security ontology surfacing).
