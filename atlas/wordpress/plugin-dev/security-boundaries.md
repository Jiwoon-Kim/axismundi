---
rule_id: plugin-dev.security-boundaries
domain: plugin-dev
topic: security-doctrine
field_cluster: governance-synthesis
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/apis/security/
    section: "Security in WordPress — sanitize / escape / validate / nonces / capabilities"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/plugins/security/
    section: "Plugin Handbook — Security best practices"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/apis/security/nonces/
    section: "Nonces — CSRF protection"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/plugins/users/roles-and-capabilities/
    section: "Roles and Capabilities — capability model"
    captured: 2026-05-09
related:
  - plugin-dev.register-block-bindings-source  # trust surface (1st security surfacing)
  - plugin-dev.register-meta                   # legitimacy surface (2nd security surfacing)
  - plugin-dev.register-rest-route             # permeability surface (3rd security surfacing)
  - data-layer.persistence                     # capability enforcement at persistence boundary
  - block.dynamic-rendering                    # XSS surface in render output
  - plugin-dev.register-post-type              # entity authority — governance doctrine direct projection
  - plugin-dev.register-taxonomy               # semantic federation — assign vs manage capability separation
  - plugin-dev.capabilities-and-roles          # paired doctrine — adjudication constitution
  - style-engine.cascade-aggregation           # symmetric capstone — authority arbitration in cascade vs governance in plugin-dev
  - interactivity.hydration                    # symmetric capstone — authority continuity vs governance
---

# RULE — security-boundaries — federated authority governance doctrine

## WHEN

Designing or auditing a plugin/theme that registers any kind of
authority surface (binding source, meta, REST route, custom post
type, taxonomy, settings, slotfill, editor hook). Use this chunk
to understand:

- The distinction between security mechanisms (sanitize / auth /
  permission / capability / nonce) and the security DIMENSIONS
  they govern.
- Why "WordPress is secure" + "use the APIs correctly" do NOT
  compose to "your plugin is secure" — security is a **distributed
  governance doctrine**, not a property of API usage.
- The 3-tier security model (trust / legitimacy / permeability)
  that earlier plugin-dev chunks surfaced and this chunk
  formalizes.
- Responsibility distribution between core, plugin authors, theme
  authors, and user roles.
- Why "registration is governance declaration, not security
  completion."

This chunk is **plugin-dev bounded context's capstone**. It is
NOT an API reference; it is a **governance doctrine synthesis**
that future plugin-dev chunks (register_post_type, taxonomy,
settings, slotfills, hooks) reference rather than re-deriving
security framing per chunk.

The KB symmetry now stands:

| bounded context | capstone | character |
|---|---|---|
| style-engine | cascade-aggregation | authority arbitration |
| interactivity | hydration | authority continuity |
| **plugin-dev** | **security-boundaries (this)** | **authority governance** |

Each capstone synthesizes the bounded context into a single
operational doctrine.

## SHAPE

### A. Security surfaces by federation layer

Each plugin-dev federation layer has its own security surface(s):

| federation layer | security surface | mechanism |
|---|---|---|
| **origin** (bindings source) | trust boundary | source registration + provider intent verification |
| **persistence** (meta) | legitimacy gate | sanitize_callback + auth_callback |
| **transport** (REST route) | permeability gate | permission_callback + args validation + nonce |
| **entity** (CPT / taxonomy) | capability schema | capability_type + map_meta_cap |
| **UI / editor** (slotfills, editor hooks) | authoring access | block / panel filtering + capability checks |

Each federation extension chunk surfaces security at its specific
boundary. This chunk synthesizes them into a single doctrine.

### B. Security mechanisms — the toolbox

```php
// Shape filtering (pre-persistence input cleanup)
$clean = sanitize_text_field( $raw );
$clean = sanitize_email( $raw );
$clean = wp_kses_post( $raw );

// Validation (rejection of invalid input)
if ( ! is_email( $value ) ) { return new WP_Error( 'invalid_email' ); }
$validated = rest_validate_request_arg( $value, $request, 'param' );

// Per-meta access governance
'auth_callback' => function ( $allowed, $meta_key, $object_id, $user_id ) {
    return current_user_can( 'edit_post', $object_id );
}

// Per-route access governance
'permission_callback' => function ( WP_REST_Request $req ) {
    return current_user_can( 'manage_options' );
}

// Capability check (top-level authority)
if ( ! current_user_can( 'edit_posts' ) ) { wp_die(); }

// Custom capability mapping
register_post_type( 'product', array(
    'capability_type' => 'product',
    'map_meta_cap'    => true,
) );

// Nonces (CSRF protection)
$nonce = wp_create_nonce( 'my_action_' . $object_id );
if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'my_action_' . $object_id ) ) {
    wp_die();
}

// Output context escaping
echo esc_html( $value );
echo esc_attr( $value );
echo esc_url( $value );
echo wp_kses_post( $rich_html );
```

Each mechanism governs a different **security DIMENSION** —
they are not interchangeable. Selection depends on what is being
governed (input shape vs identity vs request origin vs
authorization vs output context).

### C. Boundary failures — anti-doctrine catalog

| failure mode | symptom | dimension violated |
|---|---|---|
| **overexposure** | declared authority visible / accessible beyond intent | declaration ≠ exposure axis |
| **undervalidation** | sanitize without validate; type drift accepted | shape gate insufficient |
| **privilege escalation** | auth_callback returns true universally; permission_callback ignores capability | access gate failure |
| **trust leakage** | unsanitized output through registered authority surface | output context mismatch |
| **nonce omission** | state-changing routes without CSRF protection | request origin gate missing |
| **capability omission** | registered authority has no capability check | identity gate absent |
| **default permissiveness** | `__return_true` used reflexively | governance not declared |
| **assumption transfer** | "core handles it" without verification | responsibility leakage |

### D. Responsibility distribution — asymmetric by design

> **Responsibility distribution is asymmetric.**

Three-tier responsibility model:

| actor | provides |
|---|---|
| **Core** | security membranes (REST infrastructure, capability model, nonce framework, sanitize/escape primitives, hook system) |
| **Plugins / Themes** | doctrine implementation (callbacks, sanitization logic, capability checks, intent declarations, output escaping) |
| **Users / Roles** | capability membership (administrator / editor / author / contributor / subscriber) |

Core provides INFRASTRUCTURE. Plugins/themes provide INTENT and
ENFORCEMENT. Users/roles provide IDENTITY context. None of the
three alone produces security; **security emerges from the
correct composition of all three**.

This is the **WordPress hybrid security constitution**: partially
centralized (core supplies the membranes) + heavily federated
(plugins implement doctrine) + doctrine-rich (best practices
established) + implementation-variable (every plugin author
makes per-decision).

## REQUIRES

- The earlier 3 plugin-dev chunks (bindings-source, meta,
  rest-route) document specific surfacings; this doctrine chunk
  synthesizes them.
- WordPress capability system + REST API + nonce framework as
  shared infrastructure.
- ⚠ Specific implementation details (multisite capability
  variations, custom auth providers, capability cache behavior,
  nonce timing) — verification-needed and cataloged below.

## INVARIANTS

### 1. Security governs authority across federation layers, NOT isolated APIs

Macro doctrine framing:

> Security in WordPress is NOT a per-API checklist (sanitize
> here, capability there). Security is **federation-wide
> governance** — the coordinated doctrine that authority crossing
> any federation layer (origin / persistence / transport / entity /
> UI) is verified, validated, and authorized.

Reading security as "use the right escape function" misses the
ontology: the question is not "did I escape this string?" but
"did authority cross this boundary under appropriate
governance?"

This invariant is the chunk's load-bearing scope statement.
All subsequent invariants operate within this frame.

### 2. Every new authority surface expands attack surface

> Plugin-dev = architecture extension AND security expansion.

Each registered binding source / meta key / REST route / CPT /
slotfill is a new authority surface in WordPress's federation.
Each surface is also a new boundary attackers may probe. The
two grow together; they cannot be separated.

Implications:
- Adding a feature is adding security responsibility.
- Removing unused registrations REDUCES attack surface.
- Plugins should register only what they need; over-registration
  expands attack surface unnecessarily.

This frames extension cost: every authority surface introduced
incurs **doctrine debt** that must be paid via security
implementation. See "security debt" framing below.

### 3. Trust / legitimacy / permeability form distinct security questions

The 3-tier model surfaced across earlier plugin-dev chunks,
formalized here:

| tier | question | governs |
|---|---|---|
| **Tier 1 — Trust** | Should this authority source EXIST? | origin legitimacy (who may register / activate / provide authority) |
| **Tier 2 — Legitimacy** | Is this authority structurally and semantically VALID? | shape correctness (sanitize_callback) + access correctness (auth_callback) |
| **Tier 3 — Permeability** | May authority CROSS this boundary, here, now, for this actor? | per-request authorization (permission_callback, capabilities, nonces) |

These are **distinct questions**, not redundant gates:
- A trusted source can produce ill-formed authority (tier 1 ✓,
  tier 2 ✗ → sanitize/validate rejects).
- A legitimate value can be requested by an unauthorized actor
  (tier 2 ✓, tier 3 ✗ → permission rejects).
- Tier 3 success does not legitimize tier 2 violations (a user
  with permission can still submit unsanitized data — sanitize
  runs anyway).

Each tier is independently necessary; none alone is sufficient.

### 4. Registration is governance declaration, NOT security completion

```
register_meta(...);              ← declaration of governed surface
                                   (NOT statement that meta is now secure)

register_rest_route(...);         ← declaration of accessible boundary
                                   (NOT statement that boundary is now safe)

register_block_bindings_source(...); ← declaration of authority origin
                                       (NOT statement that origin is trusted)
```

Registration creates the governance SLOT; the plugin author
fills the slot with doctrine via callbacks (sanitize, validate,
auth, permission, capability). Default permissiveness fills the
slot with NO governance — declaration done, security absent.

> **Registration ≠ security.**
> Registration declares "this authority surface exists in the
> federation." Security requires "this surface is governed by
> appropriate doctrine."

### 5. Core supplies security membranes; plugins remain responsible for doctrine execution

The hybrid security constitution operationalized:

> **Core supplies the constitutional membranes; ecosystem actors
> execute doctrine through them.**

Core provides:
- API surfaces with security HOOKS (callbacks plugins implement).
- Primitives for sanitize/escape/validate/nonce/capability.
- Capability model (roles, capability mapping).
- REST infrastructure with permission_callback HOOK.

Plugin/theme doctrine execution:
- Implement callbacks with appropriate logic.
- Choose correct sanitize/escape function for context.
- Verify capabilities before sensitive operations.
- Generate and verify nonces for state changes.
- Map custom capabilities when needed.

Core's security guarantees END at the membrane boundary;
plugin authors INHERIT responsibility from there. Treating
"core APIs" as security guarantees is responsibility leakage.

### 6. Schema, capability, and transport each enforce different security dimensions

**Mechanism-dimension orthogonality**:

| dimension | mechanism | governs |
|---|---|---|
| **shape** | sanitize_callback / type / format | what shape may authority take? |
| **rejection** | validate_callback / is_email() / etc. | what shapes are invalid? |
| **identity** | current_user_can / capability_type | who is the actor? |
| **access** | auth_callback / map_meta_cap | does this actor have access? |
| **boundary** | permission_callback / nonce | may authority cross this boundary now? |
| **output** | esc_html / esc_attr / wp_kses_post | how is authority safely emitted? |

Mechanisms are NOT interchangeable. sanitize doesn't authorize;
auth doesn't validate shape; nonce doesn't grant capability.
Each dimension needs its own mechanism; layered defense
requires multiple dimensions enforced.

This invariant is why the antipattern catalog exists — common
plugin-dev errors are dimension confusion (using one
mechanism's protection for a different dimension's failure).

### 7. Security is cross-boundary authority arbitration

KB-wide symmetry surfacing:

| KB layer | arbitration |
|---|---|
| style-engine cascade-aggregation | competing CSS authorities arbitrate via cascade |
| interactivity hydration | server / client authority arbitrate via continuity |
| **plugin-dev security-boundaries** | **competing actors / sources / boundaries arbitrate via governance** |

Security in WordPress is structurally **authority arbitration
across boundaries**:
- Multiple actors may attempt to access the same authority
  (arbitrated by capability + nonce).
- Multiple sources may compete to provide authority (arbitrated
  by registration + trust).
- Multiple boundaries may need crossing (arbitrated by
  permission + transport ABI).

This makes plugin-dev's security capstone a SYMMETRIC peer to
style-engine's cascade-aggregation and interactivity's
hydration — three bounded contexts, three capstones, all
documenting authority arbitration in different domains.

### 8. Governed extensibility requires distributed security literacy

Final doctrine-level invariant:

> WordPress's federated authority model means **every plugin
> author participates in the security architecture**. Plugins
> are not customers consuming a secure platform; they are
> federation participants whose doctrine implementation
> contributes to or compromises the ecosystem's security.

Implications:
- Plugin developer education in security is structural, not
  optional.
- Code review at the ecosystem level (audits, scans) is the
  closest WordPress has to centralized enforcement.
- Defaults matter: when a plugin omits security, the user
  experiences the omission as the platform's behavior.
- Plugin authorship = architecture participation; security
  literacy is part of the participation contract.

This invariant frames plugin-dev as a discipline, not just an
API set. It also justifies the chunk's existence: KB documents
the doctrine because the doctrine is itself federation
infrastructure.

## VERIFICATION NEEDED

The DOCTRINE in this chunk is `status: stable` — the
governance principles do not shift across WP versions.
Specific MECHANISM behaviors are evolving and verification-
needed:

**Stable doctrine / evolving mechanisms** distinction:

The 3-tier security model, federation governance scope,
mechanism-dimension orthogonality, asymmetric responsibility
distribution — these are doctrinal and stable.

The following implementation specifics are evolving / variable:

- Multisite capability semantics (network admin vs site admin
  capability resolution; super admin behavior).
- Capability cache behavior — when do capability changes
  propagate to in-flight requests?
- Custom authentication architectures (Application Passwords,
  OAuth providers, JWT plugins) and their interaction with
  permission_callback / current_user_can.
- Nonce lifetime policy (default 24-hour window;
  configurability).
- REST authentication provider precedence when multiple are
  active.
- WP_REST_Server's exact request authentication pipeline.
- map_meta_cap edge cases for custom post types.
- Capability tests against `do_not_allow` capability.
- REST endpoint OPTIONS preflight authentication behavior.
- Cookie auth + REST nonce verification under edge cases
  (long sessions, browser cache, etc.).
- Specific filter hooks that can override capability checks
  (`map_meta_cap` filter, `user_has_cap` filter).
- WP-CLI capability behavior vs HTTP request capability.

For practical decisions: trust empirical observation (audit
your specific scenario) over inferred behavior. Doctrine
applies; mechanism specifics need per-scenario verification.

## ANTIPATTERNS

The critical confusion catalog — these are the dimension-
mismatch errors plugin authors most commonly make:

- ❌ **sanitize ≠ authorize**. sanitize_text_field cleans input
  shape; it does NOT check whether the actor is allowed to
  submit. Both are needed.
- ❌ **auth_callback ≠ permission_callback**. auth_callback gates
  per-meta access; permission_callback gates per-route access.
  Both layer; neither replaces the other.
- ❌ **registration ≠ security**. Registering a meta / route /
  source declares the surface exists; security requires
  filling the governance slots with appropriate logic.
- ❌ **REST namespace ≠ protection**. The namespace is a
  jurisdiction marker (collision avoidance, versioning); it
  does NOT confer access control. Use permission_callback.
- ❌ **hidden UI ≠ denied authority**. Hiding a control in the
  editor is a UX hint only; authoritative enforcement happens
  server-side at REST permission_callback / capability checks.
- ❌ **`show_in_rest: true` ≠ safe public API**. Exposing meta
  via REST means it's READABLE by anyone with REST read access
  to the entity. If the data is sensitive, use detailed
  show_in_rest configuration with restrictive auth, or do NOT
  expose.
- ❌ **nonce ≠ authorization**. Nonces verify request ORIGIN
  (CSRF protection) — they confirm the request came from a
  page on the same site. They do NOT verify the user has
  capability. Use BOTH nonce AND current_user_can for
  state-changing operations.
- ❌ **`__return_true` as reflexive permission**. Using
  `'__return_true'` for permission_callback / auth_callback
  is correct ONLY when the operation is genuinely public.
  Reflexive use to silence WP 5.5+ permission warnings creates
  silent unauthorized access surfaces.
- ❌ **"core probably handles it"**. Core supplies membranes;
  plugin authors implement doctrine. Assuming core enforces
  beyond the membrane is responsibility leakage.
- ❌ **Trusting registration time = trusting runtime**. A user
  with capability AT REGISTRATION may not have it AT REQUEST.
  Capabilities check at the request boundary, not just at
  registration.
- ❌ **Conflating capability with role**. Roles are NAMED
  capability bundles; capabilities are the actual permission
  primitives. Code against capabilities (`current_user_can(
  'edit_posts' )`), not roles (`if ( $user->roles[0] === 'editor'
  )`), so custom roles work correctly.
- ❌ **Treating security as feature toggle**. Security is
  ongoing doctrine discipline, not a one-time configuration.
  Each new authority surface added requires doctrine
  evaluation.
- ❌ **Output escape mismatched to context**. `esc_html`
  inside an attribute is incorrect (use esc_attr); raw
  output of user-controlled HTML is XSS (use wp_kses_post for
  rich content); URL output without esc_url enables
  open-redirect attacks.
- ❌ **Negative-space security**: assuming WordPress's defaults
  are restrictive. Many defaults are PERMISSIVE (omitting
  permission_callback pre-WP-5.5 = public access; default
  meta sanitization is none). Absence of doctrine is itself
  an architectural event with security consequences.
- ❌ **Accepting "security debt"**. When a registered authority
  surface has weak / missing governance (registered but no
  appropriate sanitize/auth/permission), the gap is
  **architectural security debt**. Debt accumulates as
  surfaces multiply; ecosystem-scale debt becomes plugin
  vulnerability statistics.

## RELATED

- `plugin-dev.register-block-bindings-source` — origin layer's
  security surfacing (1st in plugin-dev). Trust framing
  introduced. This chunk's invariant 3 tier 1.
- `plugin-dev.register-meta` — persistence layer's security
  surfacing (2nd). sanitize_callback + auth_callback as
  legitimacy gates. This chunk's invariant 3 tier 2.
- `plugin-dev.register-rest-route` — transport layer's security
  surfacing (3rd). permission_callback as permeability gate.
  This chunk's invariant 3 tier 3.
- `data-layer.persistence` — capability enforcement timing
  (at persistence boundary, NOT edit). This chunk's invariant
  6 dimension table.
- `block.dynamic-rendering` — XSS surface in render output.
  Output escape mechanism applies (esc_html / wp_kses_post in
  render_callback).
- (planned) `plugin-dev.register-post-type` — entity authority
  layer. CPT introduces capability_type + map_meta_cap;
  doctrine from this chunk applies. Pre-framed: CPT = governed
  entity authority schema.
- (planned) `plugin-dev.capabilities-and-roles` — capability
  model deeper dive. This chunk references capabilities; the
  model itself deserves dedicated treatment.
- (planned) `plugin-dev.nonces` — CSRF protection deeper dive.
  Nonce mechanism, lifetime, scoping.
- `style-engine.cascade-aggregation` — symmetric capstone.
  Authority arbitration in cascade vs governance in plugin-dev
  (this chunk's invariant 7 symmetry).
- `interactivity.hydration` — symmetric capstone. Authority
  continuity vs governance.

## META

**plugin-dev bounded context — capstone chunk.**

This chunk synthesizes 3 prior plugin-dev chunks' security
surfacings into a single doctrine. After this, plugin-dev
bounded context has its operational doctrine locked.

**KB capstone symmetry across bounded contexts:**

| bounded context | capstone | character |
|---|---|---|
| style-engine | cascade-aggregation | authority arbitration |
| interactivity | hydration | authority continuity |
| **plugin-dev** | **security-boundaries (this)** | **authority governance** |

Three bounded contexts, three capstones, three different
authority concerns at the operational level. The symmetry is
structural — KB's bounded contexts each reach a capstone that
synthesizes their internal chunks into a single architectural
doctrine.

**KB-level framing extension:**

> KB evolves from **"authority architecture atlas"**
> into **"authority + governance atlas"**.
>
> Authority architecture (Phases 1-7): WHAT authorities exist
> and HOW they relate. Governance (this chunk + future
> plugin-dev chunks): HOW the authority architecture is
> trusted, validated, and protected.

**plugin-dev domain identity locked:**

> **In WordPress, extensibility without governance is not
> flexibility — it is unmanaged authority proliferation.**
>
> Plugin-dev bounded context's doctrine: federated authority
> requires governance at every layer. Trust at origin.
> Legitimacy at persistence. Permeability at transport.
> Capability at entity. Authoring access at UI. Each layer
> demands its specific security mechanism; mechanism choice
> is dimension choice.

This framing is the bounded context's tone-lock. Subsequent
plugin-dev chunks (register_post_type, taxonomy, settings,
slotfills, hooks) should reference and respect this doctrine
rather than re-deriving security framing per chunk.

**CPT pre-framing payoff:**

After this chunk, `register_post_type` (planned next or shortly
after) will read as **"governed entity authority schema"**
rather than "content modeling API." Specifically:
- `capability_type` + `map_meta_cap` = capability schema for
  the entity.
- `public` + `show_in_rest` = exposure governance.
- `supports` = authoring-access dimension.
- The CPT itself = new authority subject in federation,
  governed by capability schema.

Without this chunk, CPT would read classically; with it, CPT
extends the architecture-extension framing the prior 3 chunks
established.

**Status: `stable` — DOCTRINE classification, not API:**

The 3-tier security model (trust / legitimacy / permeability),
federation-wide governance scope, mechanism-dimension
orthogonality, asymmetric responsibility distribution: all
stable doctrine. WordPress's underlying mechanisms evolve
across versions, but the doctrine framework remains constant.

This is the first chunk in KB to use `status: stable` for
**doctrine** rather than for stable APIs. Future synthesis /
doctrine chunks may follow this pattern. The DSL spec
(`evolving = mechanism-evolution`) accommodates: this chunk's
mechanisms are evolving (verification-needed catalog
enumerates), but the doctrine they express is stable.

**DSL extensions applied:** VERIFICATION NEEDED + META, per
runtime/implementation-derived applicability rule. Doctrine
stability + mechanism evolvability together justify the
extensions.

**Anticipated next chunks (priority):**

1. **`plugin-dev.register-post-type`** — completes federation
   stack 4/4. Now reads as governed entity authority schema
   rather than classic content modeling API. The security
   doctrine established here will frame CPT capability /
   exposure / supports discussion.

2. **`plugin-dev.capabilities-and-roles`** — capability model
   deeper dive. This chunk references; dedicated chunk
   warranted as doctrine-level component.

3. **`plugin-dev.nonces`** — CSRF protection deeper dive.
   Mechanism + lifetime + scoping.

4. Other plugin-dev families (filters / slotfills / settings /
   hooks) — all now operate within established security
   doctrine.

5. Other bounded contexts (additive) — editor-customization /
   site-building / i18n / build-tooling / admin-ui.

Recommended next: register-post-type (closes federation stack
4/4 + immediately exercises this chunk's doctrine on a
classic API). After CPT: choose between deepening plugin-dev
(capabilities/nonces/families) or entering additive bounded
contexts.

**KB self-evaluation against spec criteria:**

- ✅ Accuracy — synthesizes documented security mechanisms;
  doctrine framing matches WordPress security guide structure.
- ✅ Structural fit — establishes plugin-dev capstone parallel
  to style-engine + interactivity capstones.
- ✅ Reusability — uses authority ontology glossary
  extensively (authority / governance / federation /
  arbitration / boundary / membrane / doctrine).
- ✅ Phase fit — synthesizes Phase 7 plugin-dev chunks.
- ✅ Doctrine respect — establishes this chunk AS doctrine
  reference for subsequent plugin-dev chunks.
