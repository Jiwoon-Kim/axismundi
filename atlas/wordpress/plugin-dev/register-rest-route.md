---
rule_id: plugin-dev.register-rest-route
domain: plugin-dev
topic: extensibility
field_cluster: transport-boundary
wp_min: "4.4"
wp_recommended: "5.5+"
status: evolving
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/register_rest_route/
    section: "register_rest_route() — REST route registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
    section: "Adding Custom Endpoints — patterns + permission_callback + args"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
    section: "Routes and Endpoints — namespace, route patterns, methods"
    captured: 2026-05-09
related:
  - plugin-dev.register-block-bindings-source  # authority origin (federation layer 1)
  - plugin-dev.register-meta                   # authority persistence (federation layer 2)
  - data-layer.entity-resolution               # core REST endpoints feed entity-resolution; custom routes can too
  - data-layer.persistence                     # custom routes may serve as persistence transport beyond entity REST
  - block.dynamic-rendering                    # render_callback may consume route responses for server-side data
  - (planned) plugin-dev.security-boundaries   # cross-cutting security synthesis (3rd surfacing here triggers formalization)
  - (planned) plugin-dev.register-post-type    # entity schema (federation layer 4)
---

# RULE — `register_rest_route()` — transport constitution layer

## WHEN

A plugin needs to expose authority across the WordPress site
boundary — to external clients, JS apps, headless consumers,
automation, third-party services. Use this API when:

- The plugin needs custom endpoints beyond what entity REST
  routes provide.
- A specific operation needs its own permission / validation /
  schema beyond what `show_in_rest` registration affords.
- Authority must be exposed to consumers outside the WordPress
  PHP runtime (browser fetches, mobile apps, server-to-server).
- A workflow / RPC-style operation doesn't fit the entity
  CRUD model.

This is the **third plugin-dev chunk** in KB. With
`register_block_bindings_source` (origin) and `register_meta`
(persistence), this chunk completes the **read/write/transport
triangle** of plugin-dev's authority federation stack:

```
authority federation stack:
   origin       ── register_block_bindings_source     ✓
   persistence  ── register_meta                       ✓
   transport    ── register_rest_route                 (THIS)
   entity       ── (planned) register_post_type
```

REST route registration is qualitatively different from the
prior two: bindings source and meta extend INTERNAL substrate;
**REST routes declare INTENTIONAL system permeability**.
Authority crosses from inside WordPress to outside actors
through routes. This makes REST the **transport constitution
layer** of the federation — the rules governing what authority
may legitimately cross system boundaries.

## SHAPE

### Route registration

```php
register_rest_route(
    'myplugin/v1',                      // namespace (vendor/version)
    '/items/(?P<id>\d+)',               // route pattern
    array(
        'methods'             => WP_REST_Server::READABLE, // or EDITABLE, DELETABLE, CREATABLE, ALLMETHODS
        'callback'            => 'myplugin_get_item',
        'permission_callback' => 'myplugin_check_permission',
        'args'                => array(
            'id' => array(
                'description' => __( 'Item ID', 'myplugin' ),
                'type'        => 'integer',
                'required'    => true,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        ),
    )
);
```

### Multiple endpoints per route

```php
register_rest_route( 'myplugin/v1', '/items/(?P<id>\d+)', array(
    array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'myplugin_get_item',
        'permission_callback' => '__return_true', // public read
    ),
    array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'myplugin_update_item',
        'permission_callback' => 'myplugin_check_edit_permission',
    ),
) );
```

### Parameters by ontology role

#### A. Route declaration

| key | role |
|---|---|
| `$namespace` (1st arg) | `vendor/version` jurisdiction marker (e.g., `myplugin/v1`) |
| `$route` (2nd arg) | URL pattern with regex captures (e.g., `/items/(?P<id>\d+)`) |
| `methods` | HTTP method constants: `READABLE` (GET), `CREATABLE` (POST), `EDITABLE` (POST/PUT/PATCH), `DELETABLE` (DELETE), `ALLMETHODS` |

#### B. Transport ABI (args schema)

| key in `args[$param]` | role |
|---|---|
| `type` | data type contract (`string`, `integer`, `boolean`, `array`, `object`) |
| `required` | parameter is mandatory |
| `default` | value when omitted |
| `sanitize_callback` | normalize/filter input (per-arg) |
| `validate_callback` | reject invalid input (per-arg, returns boolean or WP_Error) |
| `description` | OpenAPI-style human description |
| `enum` | restricted value set |
| `format` | string format hint (`email`, `uri`, `date-time`, etc.) |

#### C. Security boundary

| key | role |
|---|---|
| `permission_callback` | gates request access; receives `WP_REST_Request`; MUST return boolean (true to allow) or WP_Error |

`permission_callback` is **REQUIRED** as of WP 5.5+ (omitting
generates a notice). For public endpoints, use
`'__return_true'` explicitly to declare intent.

#### D. Handler

| key | role |
|---|---|
| `callback` | handles the request; receives `WP_REST_Request`; returns response data, `WP_REST_Response`, or `WP_Error` |

Handler should return a structured response or `WP_Error`;
the REST server normalizes to JSON.

#### E. Versioning / jurisdiction (via namespace)

```
myplugin/v1   ──  initial version
myplugin/v2   ──  breaking change; coexists with v1 during deprecation
```

Namespaces are conventionally `vendor/version` to allow
backward-compatible coexistence during API evolution.

## REQUIRES

- Registration MUST happen on the `rest_api_init` action.
  Earlier hooks miss the REST initialization; later misses
  request resolution.
- `permission_callback` MUST be defined (WP 5.5+ requirement;
  omission produces a notice + may behave unsafely).
- Route patterns MUST use valid regex capture syntax
  (`(?P<name>pattern)` for named captures).
- Arg `sanitize_callback` runs before `validate_callback` —
  validate sees sanitized input.
- Handler MUST return a serializable structure (array, object,
  WP_REST_Response, WP_Error). Returning resources (file
  handles, etc.) breaks JSON encoding.
- For routes mutating state: appropriate nonce verification
  if invoked from cookie-authenticated browser contexts
  (REST nonce header `X-WP-Nonce`).
- ⚠ Exact behavior for overlapping route patterns, namespace
  collision resolution, OPTIONS preflight handling, batch
  endpoint integration — verification-needed.

## INVARIANTS

### 1. REST routes declare transport authority boundaries

The load-bearing reframing for this chunk:

> REST routes are NOT URLs. They are **declared transport
> authority boundaries** — the membrane through which
> authority crosses from inside WordPress to outside actors
> (and vice versa).

Each route declaration encodes:
- Authority that may exit the system (response data).
- Authority that may enter the system (request payload).
- Capability gate (permission_callback) governing crossing.
- Schema contract (args) constraining what authority shapes
  may transit.

Reading routes as "endpoints" misses the membrane character.
The route IS the boundary specification, not just an address.

### 2. Routes expose system permeability, NOT merely accessible URLs

Pre-Phase-7 framing: "register_rest_route adds a URL plugins/
external clients can hit."

Post-Phase-7 framing:

> Each registered route declares **a permeability point** in
> WordPress's authority architecture. The aggregate of all
> registered routes IS the system's **permeability profile** —
> what authority may flow in and out, governed by what
> contracts.

Implications:
- Adding a route INCREASES system permeability.
- Removing or restricting a route DECREASES it.
- Permeability is a security-relevant metric: too-broad
  permission_callbacks expand attack surface.
- Ecosystem-scale: every plugin's routes contribute to the
  site's combined permeability.

### 3. permission_callback governs authority permeability

`permission_callback` is the **third explicit security
surfacing** in plugin-dev:

| chunk | security boundary |
|---|---|
| register-block-bindings-source (1st) | trust boundaries (general framing — who may provide authority) |
| register-meta (2nd) | authority legitimacy (sanitize_callback shape governance + auth_callback access governance) |
| **register-rest-route (3rd — this chunk)** | **transport permeability (permission_callback boundary crossing governance)** |

Pattern across the three:
- Bindings source: WHO provides authority (origin trust).
- Register_meta: WHETHER authority is legitimate (shape +
  access at persistence).
- **REST route: WHETHER authority may cross the boundary
  (permeability)**.

After three independent surfacings, the security ontology has
three concrete instances. The cross-cutting
`plugin-dev.security-boundaries` chunk is now justified for
synthesis (anticipated as next chunk).

### 4. Args schemas are transport ABI contracts

Parallel to prior ABI patterns in KB:

| layer | ABI |
|---|---|
| style-engine | CSS variables (`--wp--preset--*`) — runtime ABI |
| register_meta | meta key + type + single + sanitize + auth — persistence ABI |
| **register_rest_route** | **route URL + methods + args schema — transport ABI** |

A route's args schema is a **declared contract** that REST
clients can rely on. The contract surface includes:
- URL pattern (where to send the request).
- HTTP methods supported.
- Args names, types, requiredness, validation rules.
- Response structure (implicit via handler return).

Plugins establishing REST routes are publishing **transport
ABI**. Renaming routes, removing args, or changing types is
breaking change.

### 5. Namespaces define federated authority jurisdictions

`vendor/v1` namespace prefix is NOT just naming convention. It
is **a federated authority jurisdiction marker**:

| jurisdictional concern | how namespace addresses it |
|---|---|
| collision avoidance | `myplugin/v1` distinct from `otherplugin/v1` |
| versioning | `myplugin/v1` vs `myplugin/v2` for breaking evolution |
| ownership | the vendor portion declares authority owner |
| compatibility | namespaces can coexist during deprecation periods |
| ecosystem coexistence | many plugins' namespaces share the REST root |

The namespace IS a **jurisdiction declaration** — "these
routes are governed by this vendor under this version's
contract." Compare with WordPress core's `/wp/v2/...`
namespace: WordPress's REST authority is under the `wp`
jurisdiction at version 2.

This is structurally similar to block name `vendor/slug`
namespacing and bindings source name `vendor/slug` namespacing
— consistent jurisdiction marker pattern across KB.

### 6. REST is inter-system authority diplomacy

Bindings source + register_meta extended INTERNAL substrate.
REST routes are the **first plugin-dev mechanism explicitly
governing inter-system interaction**:

| participant | interaction |
|---|---|
| WordPress server | hosts and processes |
| browser client | originates requests, consumes responses |
| plugin code | declares routes, handles requests |
| third-party app | external consumer / producer |
| automation system | scheduled / triggered access |

REST routes formalize how these actors **interact across
system boundaries**. The interaction is governed by the
route's contract (permission, args, methods).

> Plugin-dev moves from extending WordPress's internal
> authority federation to governing the **diplomacy** between
> WordPress and external authority systems.

### 7. Transport declaration ≠ transport exposure ≠ transport trust

KB-recurring axis (declaration ≠ exposure) at the transport
layer, expanded to three independent surfaces:

| surface | controlled by |
|---|---|
| **declaration** (route exists in routing table) | `register_rest_route()` call |
| **exposure** (route visible in `/wp-json/` index) | `show_in_index` arg + permission_callback |
| **trust** (specific request is allowed to traverse) | `permission_callback` evaluation per request |

Routes can be DECLARED but not EXPOSED in the public index
(`show_in_index: false`). Routes can be EXPOSED but every
request denied (`permission_callback` returns false).

Each surface is independently governed. Confusing them leads
to both security holes (assumed-private routes that are
discoverable) and usability bugs (correctly authorized
clients can't find the routes).

### 8. REST route registration completes the origin / persistence / transport triad

KB-level positioning invariant:

> With this chunk, plugin-dev's authority federation stack
> has its first 3 layers documented:
>
> - Origin (where authority comes from): bindings source
> - Persistence (where authority lives): register_meta
> - **Transport (how authority moves): register_rest_route**
>
> The 4th layer (entity schema: register_post_type) declares
> WHAT KINDS of authority subjects exist. Transport completes
> the **lifecycle triangle** for authority that already has
> declared origin and persistence: it can now MOVE between
> systems.

After this chunk, plugin-dev has documented the complete
authority lifecycle (origin → persistence → transport) before
introducing entity schema. This ordering matters: entity
schema (CPT) declares NEW authority subjects, but the stack
to support them (origin / persistence / transport) is already
in place by the time CPT is introduced.

## VERIFICATION NEEDED

`status: evolving` — REST API is mature (WP 4.4+); specific
behaviors continue to refine:

- Behavior when multiple plugins register conflicting
  namespaces or overlapping route patterns.
- Exact precedence between `permission_callback` and
  capability checks inside the handler.
- OPTIONS preflight handling for cross-origin requests.
- Batch endpoint behavior with custom routes
  (`/wp/v2/batch/v1` integration).
- `show_in_index` exact semantics — what gets included in
  `/wp-json/` discovery.
- Cache headers, ETags, conditional GET support — are these
  automatic for custom routes or handler-responsibility?
- Pagination conventions for collection endpoints (links,
  headers).
- HATEOAS link generation for custom routes.
- REST authentication providers (cookie + nonce vs Application
  Passwords vs OAuth) interaction with permission_callback.
- Error response format normalization across handlers.
- Schema-driven response validation — does WP validate handler
  responses against declared schema?
- Plugin deactivation behavior — routes disappear; in-flight
  requests' fate.
- Custom REST controllers (extending `WP_REST_Controller`) vs
  procedural handlers — semantic differences.

For practical decisions: trust empirical observation
(curl + browser DevTools network panel) over inferred behavior.

## ANTIPATTERNS

- ❌ Omitting `permission_callback`. WP 5.5+ generates a notice;
  pre-5.5 silently allows public access. Always declare
  explicitly — `'__return_true'` for public endpoints, real
  capability check for protected.
- ❌ Using `'__return_true'` for routes that mutate or read
  sensitive data. Public access to write/sensitive routes is
  a security failure.
- ❌ Skipping `args` schema for parameterized routes. Without
  declared args, REST clients can't introspect contracts and
  validation must be reimplemented in the handler.
- ❌ Missing nonce verification for cookie-authenticated
  state-changing routes. Browser-initiated requests need
  `X-WP-Nonce` header (or equivalent CSRF protection).
- ❌ Returning raw arrays without WP_REST_Response wrapping
  for routes needing custom headers or status codes.
- ❌ Renaming routes after publishing. Routes are transport
  ABI; clients depend on URL stability. Use namespace
  versioning for breaking changes.
- ❌ Putting business logic in `permission_callback`.
  permission_callback should be a fast capability gate;
  business logic belongs in handler.
- ❌ Forgetting that `permission_callback` runs on every
  request — heavy logic compounds. Use object cache or
  return early.
- ❌ Returning user-controlled input verbatim without escaping
  in the response. While JSON encoding handles most XSS, any
  embedded HTML / URLs need careful sanitization.
- ❌ Registering routes outside `rest_api_init`. Other hooks
  miss the REST initialization context.
- ❌ Using arbitrary URL patterns without considering
  collision with future core routes. Stick to namespaced
  routes (`/myplugin/v1/...`); avoid `/myplugin/items/...`
  at root.
- ❌ Treating REST routes as "the API" while ignoring core
  REST endpoints. Custom routes complement core; don't
  reimplement what `/wp/v2/posts` etc. already provide.

## RELATED

- `plugin-dev.register-block-bindings-source` — authority
  origin (layer 1 of federation stack). Source providers
  may sometimes consume custom REST routes for external
  data; the two compose.
- `plugin-dev.register-meta` — authority persistence (layer
  2). Custom REST routes may read/write registered meta;
  show_in_rest exposes meta at standard entity REST routes,
  while custom routes can offer alternative views.
- `data-layer.entity-resolution` — core entity REST routes
  feed entity-resolution. Custom routes can also be consumed
  via core-data store registration (separate chunk needed).
- `data-layer.persistence` — custom REST routes may serve as
  alternate persistence transport beyond entity REST. The
  reconciliation lifecycle from data-layer.persistence
  applies if routes mutate entities.
- `block.dynamic-rendering` — render_callback may invoke
  custom REST routes server-side (or core REST endpoints)
  to gather data for dynamic blocks. The route's
  permission_callback applies even for server-side calls.
- (planned) `plugin-dev.security-boundaries` — cross-cutting
  security synthesis. Three plugin-dev chunks now have
  surfaced security from different angles; formalization
  warranted as next chunk.
- (planned) `plugin-dev.register-post-type` — entity schema
  layer (4 of federation stack). Closes the federation stack
  with new authority subject kinds.

## META

**plugin-dev bounded context — third chunk; federation stack
3/4 complete.**

```
authority federation stack:
   origin       ── register_block_bindings_source     ✓
   persistence  ── register_meta                       ✓
   transport    ── register_rest_route                 ✓ (THIS)
   entity       ── (planned) register_post_type
```

The **read/write/transport triangle** is now closed. Authority
can be declared as origin, persisted in substrate, and
transported across system boundaries — all through plugin-side
registration APIs. The 4th layer (entity schema) introduces
NEW authority subjects; the existing 3 layers support them.

**Security ontology — 3 surfacings; cross-cutting chunk
warranted:**

| chunk | security boundary | mechanism |
|---|---|---|
| register-block-bindings-source | trust (origin authority) | general framing |
| register-meta | legitimacy (shape + access at persistence) | sanitize_callback + auth_callback |
| **register-rest-route (THIS)** | **permeability (transport boundary crossing)** | **permission_callback** |

After 3 independent surfacings, the cross-cutting chunk
`plugin-dev.security-boundaries` is justified. The 3 surfaces
form a coherent layered model:

- **Trust** — does the actor have legitimate origin authority?
- **Legitimacy** — is the authority shape + access correct?
- **Permeability** — may the authority cross this boundary?

These compose: a request must pass permeability gate (REST
permission), authority access gate (auth_callback for meta),
and originate from trusted source (binding source registration).

**Multi-boundary ABI architecture revealed:**

| boundary | ABI mechanism |
|---|---|
| runtime (cascade) | CSS variables (`--wp--preset--*`) |
| persistence | meta keys + schema (register_meta) |
| transport | route + args schema (register_rest_route) |

Plugin-dev bounded context is structurally a **multi-boundary
ABI architecture** — registration APIs declare contracts at
each boundary the system has. This framing now anchored across
3 chunks.

**KB-level framing extension (plugin-dev domain identity
deepened):**

> Plugin-dev bounded context is the **external authority
> architecture** layer of WordPress, structured as a
> **federation stack** (origin / persistence / transport /
> entity) with **multi-boundary ABI declarations**
> (runtime / persistence / transport contracts) and
> **layered security governance** (trust / legitimacy /
> permeability gates).
>
> Plugins are federation participants governed by registration
> contracts at each architectural layer.

**KB self-evaluation against spec criteria:**

- ✅ Accuracy — describes documented API contract.
- ✅ Structural fit — extends authority federation stack
  coherently; positions REST as transport constitution.
- ✅ Reusability — uses authority ontology glossary
  (transport / boundary / ABI / federation / jurisdiction /
  permeability / diplomacy).
- ✅ Phase fit — plugin-dev third chunk; references prior
  plugin-dev chunks + data-layer substrate appropriately.
- ✅ Doctrine respect — implicit HTML primacy preserved
  (REST returns JSON which composes with HTML rendering;
  not SPA framing).

**DSL extensions applied:** VERIFICATION NEEDED + META.

**Status `evolving`** — REST API is mature, but cross-route
behaviors, batch integration, authentication provider
semantics evolve.

**Anticipated next chunks (priority):**

1. **`plugin-dev.security-boundaries`** — NOW justified.
   Synthesizes 3 surfacings (trust / legitimacy / permeability)
   into cross-cutting chunk. Recommended BEFORE
   register_post_type so CPT can be read as governed entity
   schema (security framing already locked in).

2. **`plugin-dev.register-post-type`** — completes federation
   stack 4/4. Reframes classic API via KB ontology (entity
   schema extension; not "WordPress procedural API").

3. Other plugin-dev families (block filters / slotfills /
   hooks) on demand.

**Recommended next: security-boundaries** — the 3-surfacing
threshold is exactly the formalization trigger documented in
prior chunk META sections. Writing it before CPT also locks
plugin-dev's tone (governed extensibility) before introducing
the most-classic plugin API. After security-boundaries:
register_post_type, then editor-customization or other
bounded contexts as needed.

---

## Q9 RETROACTIVE PATCH — Phase 8.5+ Doctrine 6 Verification (2026-05-10)

> **Retroactive verification triggered by**:
> `plugin-dev/nonces.md` chunk (2026-05-10) which surfaced
> Doctrine 6 sub-element 6f (Origin-authenticity-gated
> mediation) AND identified `register-rest-route` as Q9 retro
> candidate for potential Doctrine 6 manifestation.
>
> **Strategic role**: Mediation KB-Wide re-audit Criterion 5
> (Forward + retroactive evidence both contributing) blocker
> resolution. Pre-this-retro: Mediation evidence was 5 forward
> + 1 forward (nonces) + 0 retro. Post-this-retro: Mediation
> gains its FIRST retroactive verification chunk → Criterion 5
> fully MET pathway.
>
> **Q9 retro discipline**: Confirm / Distributed / Divergent /
> Additive verdict per Phase 7.6+ retroactive verification
> protocol.

### Retro context

This chunk was authored 2026-05-09 (Phase 7-native, pre-
Doctrine-6-formalization). At time of authoring, Mediation
existed as candidate but was not yet formalized as Doctrine 6.
This retro tests whether Doctrine 6 was **structurally latent**
in REST route registration's original analysis.

The retro question:
> Does REST route governance reveal latent mediation
> architecture, thereby proving Mediation is historically
> structural rather than merely forward-discovered?

### Latent Doctrine 6 evidence in original chunk

Re-reading the original chunk through Doctrine 6 lens reveals
**multiple latent mediation manifestations** the original
Phase 7-native analysis described in proto-mediation language
without naming it as such:

| original chunk element | Doctrine 6 retroactive reading |
|---|---|
| **Invariant 3**: "permission_callback governs authority permeability" | Doctrine 6 mediation manifestation: per-request access gating choreography |
| **Invariant 7**: "Transport declaration ≠ transport exposure ≠ transport trust" (3-form gap) | Multi-axis Doctrine 6 mediation: 3 distinct gating surfaces (declaration / exposure / trust) |
| **WP 5.5+ permission_callback REQUIREMENT** | Doctrine 6 enforcement: WP made gating obligatory (constitutional discipline) |
| **REST permission notice for omitted permission_callback** | Doctrine 6 structural recognition: permission gate IS structural requirement, not optional security feature |
| **`__return_true` as explicit public-access declaration** | Doctrine 6 explicit mediation declaration: even "no gate" must be DECLARED as deliberate gating choice |
| **show_in_index + permission_callback orthogonality** | Multi-axis Doctrine 6 mediation: visibility gating (cognitive-surface-adjacent) ⊥ access gating |
| **Per-request permission_callback evaluation** | Doctrine 6 runtime mediation: gate is RE-EVALUATED per request (not declaration-time only) |
| **Args validate_callback + sanitize_callback chain** | Doctrine 6 input-gating mediation: data crossing transport boundary subject to validation+sanitization gates |
| **Namespace `vendor/v1` jurisdiction marker** | Doctrine 6 jurisdictional mediation: namespace declares which authority owns the gating |
| **HTTP methods constraint (READABLE / EDITABLE / etc.)** | Doctrine 6 method-axis mediation: HTTP method itself is gating axis |

> **Verdict (latent evidence)**: Doctrine 6 was STRUCTURALLY
> LATENT in original Phase 7-native chunk. Original analysis
> described mediation phenomena using domain-specific
> vocabulary (permeability / boundary / trust) without
> Doctrine 6 framework. Post-Phase-8.5 retro re-frames same
> phenomena under formal Doctrine 6 vocabulary.

This is **structurally significant**: the original chunk did
NOT need to be ABOUT Doctrine 6 — Doctrine 6 was already
implicit in the architecture, captured by the original analysis
in proto-mediation language. Retro VERIFICATION confirms
Mediation is historically structural, not merely forward-
discovered.

### Q9 retro verdict — ADDITIVE

Per Phase 7.6+ retroactive verification protocol (Confirm /
Distributed / Divergent / Additive):

| verdict | applicability |
|---|---|
| Confirm | Doctrine 6 manifestation matches existing 6a-6f sub-elements exactly | NO — REST manifestation is broader |
| Distributed | Single Doctrine 6 manifestation distributed across multiple mechanisms | PARTIAL — multi-axis but coherent under permission_callback as primary |
| Divergent | REST manifestation is structurally different from Doctrine 6 character | NO — clearly mediation-grade |
| **Additive** | **REST manifestation extends Doctrine 6 with NEW sub-element + multi-form character beyond prior 6a-6f scope** | **YES — primary verdict** |

> **Q9 retro verdict: ADDITIVE.**
>
> REST route registration adds **NEW Doctrine 6 sub-element 6g
> (Endpoint-permission-gated mediation)** AND demonstrates
> **multi-axis composite mediation character** (3-form gap +
> input gating + namespace jurisdiction + method-axis +
> permission-gating) richer than any prior Doctrine 6
> manifestation.

This Additive verdict is honest evaluation: REST mediation is
NOT just a 5th instance confirmation; it is structurally
broader — it extends Doctrine 6's manifestation surface area.

### NEW sub-element: 6g Endpoint-permission-gated mediation

> **Phase 8.5+ Doctrine 6 sub-element addition (this retro)**:

| sub-element | bounded context | gating mechanism |
|---|---|---|
| 6a Capability-gated | admin-ui (settings-api) | user capability check |
| 6b Routing-gated | admin-ui (admin-menus) | navigation topology |
| 6c Cognitive-surface-gated | admin-ui (notices) | multi-axis attention gating |
| 6d Subscription-gated | editor-customization (editor-hooks) | direct subscribe/dispatch |
| 6e Context-reassignment-gated | i18n (locale-switching) | runtime context mutation |
| 6f Origin-authenticity-gated | plugin-dev (nonces) | request-origin HMAC verification |
| **6g Endpoint-permission-gated (NEW, this retro)** | **plugin-dev (register-rest-route)** | **per-request permission_callback evaluation at REST endpoint** |

**Doctrine 6 sub-element count**: 6 → 7.

**6g distinguishing character** (vs adjacent sub-elements):
- vs **6a (Capability-gated)**: 6g operates at endpoint
  registration boundary; 6a operates at admin form / setting
  access. 6g may DELEGATE to capability check internally,
  but the gating SURFACE is endpoint-bound, not capability-
  bound.
- vs **6f (Origin-authenticity-gated)**: 6g verifies authorization
  for the action; 6f verifies origin legitimacy. 6g asks "is
  this caller authorized?"; 6f asks "did this request originate
  from a legitimate source?". Per-mutation REST endpoints
  typically employ BOTH 6f (cookie nonce) AND 6g
  (permission_callback).
- vs **6b (Routing-gated)**: 6g gates ENDPOINT access (data
  transport boundary); 6b gates NAVIGATION (UI topology). Both
  involve "routes" but at different architectural layers.

> **6g is structurally distinct from prior 6 sub-elements;
> not subsumable.** Default classification: independent
> Doctrine 6 sub-element (parallel to 6f).

### Composite Doctrine 6 manifestation observation

Beyond 6g specifically, REST route registration exhibits
**multi-form Doctrine 6 composite character**:

```
REST route Doctrine 6 composite:

   6g Endpoint-permission-gated      ← PRIMARY (permission_callback)
   + 6c-adjacent visibility gating    ← show_in_index
   + input-gating mediation           ← args validate_callback + sanitize_callback
   + namespace jurisdictional gating  ← vendor/v1 ownership
   + method-axis gating               ← HTTP methods constraint
```

This is **richer composite mediation than any single sub-
element manifestation prior**. REST registration is the
**first multi-form Doctrine 6 composite** documented in KB.

> **Constitutional observation (NEW)**: Doctrine 6 sub-elements
> may COMPOSE within single mechanism, not just manifest
> independently. REST route is first composite-manifestation
> evidence.

Status: **Surfaced only.** Composite manifestation pattern
needs cross-context verification (other multi-form Doctrine 6
mechanisms?) before formalization. Cross-context candidates:
- Settings API + capability + sanitize + screen (multi-form
  composite?)
- Admin menu + capability + parent + position + screen-context
  (multi-form composite?)

### Federation Pattern strengthening

Original chunk Invariant 5 ("Namespaces define federated
authority jurisdictions") is **direct Federation Pattern
manifestation** in transport boundary domain:

| Federation aspect | original analysis | Doctrine 6 retro reading |
|---|---|---|
| Vendor namespace ownership | "vendor portion declares authority owner" | Federation: per-vendor REST jurisdictional federation |
| Coexistence | "many plugins' namespaces share the REST root" | Federation: shared REST authority root with per-vendor mediation |
| Versioning | "v1 / v2 coexist during deprecation" | Federation: per-vendor version-stratified jurisdiction |

> **Federation Pattern manifestation in REST routes
> retroactively confirmed.** Adds plugin-dev REST as 9th-context
> Federation manifestation (after 8 prior contexts including
> nonces NONCE_SALT/KEY federation).

Federation Pattern KB-Wide-equivalent status further reinforced.

### Mediation KB-Wide re-audit Criterion 5 — RESOLUTION

Pre-this-retro Mediation Criterion 5 status:
- 6 forward chunks (settings-api + admin-menus + editor-hooks +
  locale-switching + notices + nonces)
- 0 retro chunks
- **Criterion 5 PARTIALLY MET** (forward yes; retro absent)

Post-this-retro Mediation Criterion 5 status:
- 6 forward chunks (unchanged)
- **1 retro chunk (this retro)**
- **Criterion 5 FULLY MET** (forward + retro both contributing)

> **Mediation re-audit blocker RESOLVED.**
> Criterion 5 fully MET via this single retro. Mediation
> evidence is now historically structural (latent in 2026-05-09
> Phase 7-native chunk; explicit in 2026-05-10 Doctrine 6
> framework) — proving Mediation is NOT merely recently-over-
> observed.

### Mediation audit criterion impact (full re-assessment)

Post-this-retro Mediation criteria status:

| criterion | pre-this-retro | post-this-retro | improved? |
|---|---|---|---|
| 1 — Context PRESENCE ≥ 4 | 5 contexts | 5 contexts (unchanged; same context) | (no) |
| 2 — Architectural variants ≥ 2 | 6 mechanisms | **7 mechanisms** (NEW 6g) | ✅ |
| 3 — Intra-context density ≥ 1 | admin-ui 3-form | admin-ui 3-form + **plugin-dev 2-form (NEW intra-context density)** | ✅ |
| 4 — Q10 sub-pattern check | completed | completed (composite manifestation noted) | (refined) |
| 5 — Forward + retro both | 6 forward + 0 retro | **6 forward + 1 retro** | ✅ MAJOR |
| 6 — Gating abstraction independence | 6 mechanisms | **7 mechanisms** (composite manifestation strengthens) | ✅ |
| 7 — Structural consequence | predicts debt classes | predicts debt classes + composite character | ✅ |

> **Mediation evidence post-this-retro**: 5 contexts × 7 gating
> mechanisms × 2-context intra-context density (admin-ui +
> plugin-dev) × forward+retro mix × composite manifestation
> observation.

This substantively advances Mediation toward Phase 8.6 KB-Wide
LAW re-audit viability. KB-Wide LAW promotion path now has:
- ✅ Criterion 5 fully MET (was blocker)
- ✅ Plugin-dev 2-form intra-context density (was 0)
- ✅ 7 distinct gating mechanisms (was 5)
- ⚠ Architectural ubiquity still 3-4/5 character categories
  (Authority federation + Governance modulation + Semantic
  substrate; Schema authority + Compiler/runtime still
  unverified)

### Phase 8.6 Mediation re-audit prerequisite assessment

Phase 8.6 Mediation re-audit prerequisites (per Phase 8.5
patch documented future trigger conditions):
- ✅ Cross-context expansion to 5+ bounded contexts (5/5
  reached)
- ⚠ Mediation manifestation in Schema authority OR Compiler/
  runtime OR Composition runtime category (still pending)
- ✅ Retroactive Q9 verification completion (≥1 retro chunk
  reached via this retro)
- ⚠ Demonstration of independence from Law 1 across all
  gating mechanisms (still partial)

> **Phase 8.6 Mediation re-audit viability**: 2/4 prerequisites
> fully met; 2/4 partial. Re-audit may produce KB-Wide LAW
> promotion if architectural ubiquity prerequisite addressed
> (1+ Schema authority OR Compiler/runtime Doctrine 6
> manifestation discovered).

Q9 retro candidates that may address remaining prerequisites:
- `block-authoring.registration` retro — Schema authority
  category Doctrine 6 manifestation potential (block.json
  permissions / restrictions)
- `style-engine.preset-materialization` retro — Compiler/
  runtime category Doctrine 6 manifestation potential (preset
  visibility gating?)
- `interactivity.directive-protocol` retro — Bridge Pattern +
  potential Doctrine 6 in Compiler/runtime category

### Constitutional Field Test additions (post-retro)

#### Table A — Universal Law Manifestation (retro additions)

| Law / Doctrine | Pre-retro reading | Post-retro reading | Status change |
|---|---|---|---|
| **Doctrine 6** | (didn't exist at chunk authoring time) | NEW 6g sub-element + composite manifestation | **Newly identified manifestation** |
| **Federation Pattern** | implicit (namespace) | explicit (Federation 9th-context) | **Retroactively confirmed** |
| **Authority Mediation Surface (now Doctrine 6)** | proto-mediation language | formal Doctrine 6 multi-axis composite | **Major retro confirmation** |
| **Law 1 — Declaration ≠ Exposure** | 3-form (declaration / exposure / trust) | 3-form confirmed; structurally aligned with Doctrine 6 multi-axis gating | (confirmed) |

#### Table B — Pattern Recurrence (retro additions)

| Candidate | Pre-retro status | Post-retro outcome | Effect on candidate |
|---|---|---|---|
| **Authority Mediation Surface (Doctrine 6)** | Doctrine-tier; 5-context cross-context PRESENCE; 6 sub-elements | NEW 6g sub-element + plugin-dev 2-form intra-context density + composite manifestation observation | **STRENGTHENED (Phase 8.6 re-audit viability advanced)** |
| **Federation Pattern** | KB-Wide-equivalent (8-context) | Retroactively confirmed in REST routes | **9th-context manifestation; KB-Wide further reinforced** |
| **Composite Doctrine 6 manifestation (NEW observation)** | did not exist | REST route exhibits multi-form Doctrine 6 composite | **Surfaced only (cross-context verification needed)** |
| **Multi-form mediation pattern (potential)** | did not exist | sub-elements may COMPOSE within single mechanism | **Surfaced only (observation; not promoted)** |

### Q10 sub-pattern emergence (retro)

> **Q10 RETRO ANSWER: NO new sub-pattern observed within
> Doctrine 6 sub-element layer.**

Initial hypothesis: REST composite manifestation might be
sub-pattern of Doctrine 6.

Honest evaluation: composite manifestation is **doctrine-
level pattern observation**, NOT sub-pattern. Sub-patterns
operate within single sub-element; composite operates ACROSS
sub-elements. Per Phase 7.5 Doctrine 3 Epistemic Integrity:

> Composite manifestation deserves observation status; needs
> cross-context verification before formalization as
> doctrine-level pattern.

Q10 retro discipline: **NO premature sub-pattern inflation;
composite remains observation only.**

### KB-wide pattern recurrence updates (retro additions)

**Doctrine 6 sub-element count**: 6 → 7 (this retro adds 6g).

**Doctrine 6 plugin-dev intra-context density**: 1 → 2 chunks
(nonces + register-rest-route retro). Plugin-dev becomes
**2nd bounded context with Doctrine 6 intra-context density**
(after admin-ui 3-form).

**Federation Pattern**: 8th-context → 9th-context manifestation
(REST namespace federation retroactively confirmed).

**Authority federation character category**: plugin-dev now
has 2 documented Doctrine 6 sub-elements (6f + 6g) within
single character category. Strongest Authority federation
category Doctrine 6 density.

### KB self-evaluation (retro)

- ✅ Accuracy — retro re-reading of original chunk; no
  rewriting of original analysis
- ✅ Structural fit — Doctrine 6 retroactive verification per
  Phase 7.6+ retroactive verification protocol
- ✅ Reusability — uses Phase 8.5 patched vocabulary (Doctrine 6,
  6g sub-element, composite manifestation)
- ✅ Phase fit — Phase 8.5+ retro; addresses Mediation re-audit
  Criterion 5 blocker
- ✅ Doctrine respect — Epistemic Integrity preserved (Q10
  refused premature composite-as-sub-pattern inflation;
  composite surfaced not promoted)
- ✅ **Q9 retro verdict explicit**: ADDITIVE — REST extends
  Doctrine 6 with NEW 6g sub-element + composite manifestation
  observation
- ✅ **Q10 retro verdict explicit**: NO new sub-pattern
  (composite is doctrine-level observation, not sub-pattern)
- ✅ **Discipline preserved**: original analysis unchanged;
  retro is ADDITIVE annotation; no retroactive content rewrite

### Retro impact summary

| dimension | impact |
|---|---|
| Doctrine 6 sub-element count | 6 → 7 (NEW 6g) |
| Doctrine 6 cross-context PRESENCE | 5 contexts (unchanged; plugin-dev already counted) |
| Doctrine 6 intra-context density | 1 context (admin-ui) → 2 contexts (admin-ui + plugin-dev) |
| Mediation Criterion 5 | PARTIALLY MET → FULLY MET |
| Mediation Criterion 2 | 6 mechanisms → 7 mechanisms |
| Mediation Criterion 6 | 6 mechanisms → 7 mechanisms (composite strengthens abstraction independence) |
| Federation Pattern | 8 contexts → 9 contexts |
| Composite Doctrine 6 manifestation | NEW observation (Surfaced) |
| Phase 8.6 Mediation re-audit viability | 2/4 prerequisites met → re-audit possible if architectural ubiquity addressed |

### Constitutional principle (retro-derived)

> **Retroactive verification proves historical structurality.**
> When a candidate (Mediation) is retroactively visible in
> a Phase 7-native chunk (register-rest-route, authored
> 2026-05-09 before Doctrine 6 formalization 2026-05-10), the
> candidate is HISTORICALLY structural — not merely
> recently-over-observed forward-discovery artifact.

This addresses the historical depth asymmetry concern
articulated in Phase 8.x Mediation audit. Mediation is no
longer forward-only; it is verified latent in earlier
chunks. KB-Wide LAW re-audit gains historical depth evidence.

### Anticipated next chunks (post-this-retro)

1. **Phase 8.6 Mediation Re-audit prep** — recompute audit
   gate criteria; if 4/4 prerequisites met OR architectural
   ubiquity sufficient, conduct re-audit.

2. **`block-authoring.registration` Q9 retro** — Schema
   authority category Doctrine 6 manifestation test; addresses
   architectural ubiquity prerequisite.

3. **`style-engine.preset-materialization` Q9 retro** —
   Compiler/runtime category Doctrine 6 manifestation test;
   addresses architectural ubiquity prerequisite.

4. **`interactivity.directive-protocol` Q9 retro** — Bridge
   Pattern Recurring (cross-context) verification + potential
   Doctrine 6 interactivity manifestation.

5. **Composite Doctrine 6 manifestation cross-context
   verification** — settings-api / admin-menus chunks may
   exhibit composite character; would strengthen composite
   observation toward formalization.

Recommended: **Phase 8.6 Mediation Re-audit prep OR block-
authoring/style-engine retro** — the choice depends on
whether immediate re-audit OR additional ubiquity evidence
collection is higher leverage. Per Phase 8.5 conservative
discipline, additional ubiquity evidence (1+ retro per Schema
authority / Compiler/runtime category) BEFORE re-audit may
maximize promotion verdict probability.

### Status updates

- This file's overall `status` remains `evolving` (original
  evaluation preserved).
- Retro patch adds Q9/Q10 retro verdicts + Doctrine 6 6g
  sub-element identification + Mediation criterion impact
  assessment.
- Original chunk content (lines 1-567) UNCHANGED; this retro
  is purely additive at end of file.
