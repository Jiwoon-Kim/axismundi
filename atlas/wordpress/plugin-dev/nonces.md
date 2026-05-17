---
rule_id: plugin-dev.nonces
domain: plugin-dev
topic: request-origin-governance
field_cluster: security-mediation
wp_min: "2.0.4"
wp_recommended: "5.0+"
status: stable
language: php-and-javascript
sources:
  - url: https://developer.wordpress.org/reference/functions/wp_create_nonce/
    section: "wp_create_nonce() — function reference"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_verify_nonce/
    section: "wp_verify_nonce() — function reference"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/check_admin_referer/
    section: "check_admin_referer() — admin nonce + referer verification"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/check_ajax_referer/
    section: "check_ajax_referer() — AJAX nonce verification"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_nonce_field/
    section: "wp_nonce_field() — form field generation"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/apis/security/nonces/
    section: "Security: Nonces — handbook overview"
    captured: 2026-05-10
related:
  - plugin-dev.security-boundaries                  # security trio sibling
  - plugin-dev.capabilities-and-roles               # security trio sibling
  - plugin-dev.register-rest-route                  # REST API nonce integration
  - admin-ui.notices                                # AJAX dismissal nonce parallel
  - editor-customization.editor-hooks               # editor request nonces
  - _meta.structural-patterns                       # Phase 8.5 patched spec; Doctrine 6
  - _meta.kb-audit-phase8-mediation-surface         # Doctrine 6 reference
---

# RULE — nonces — request-origin governance through action-legitimacy mediation surfaces (plugin-dev security trio completion)

## WHEN

A plugin or theme processes user-initiated actions that mutate
state or trigger side effects — form submissions, AJAX
requests, URL-triggered admin actions, REST API mutations,
options updates, post deletions, settings saves, comment
moderation, plugin/theme activation — and must verify that
the request was **legitimately originated** from the user's
own session (not forged by an external site or attacker).

Use nonce mechanisms when:
- Form submissions in admin or frontend that mutate state.
- AJAX endpoints handling state-changing actions.
- URL-triggered admin actions (delete, activate, etc.).
- REST API mutating endpoints (POST/PUT/DELETE).
- Block editor save operations (handled via wp_rest nonce).
- Custom action endpoints in plugins.
- Any operation where Cross-Site Request Forgery (CSRF) is
  a viable attack vector.

This is **3rd chunk in plugin-dev security trio** AND **first
forward chunk authored under Phase 8.5 patched spec
(Doctrine 6 native vocabulary)**. It completes the security
trio:
- `capabilities-and-roles`: WHO may act (authorization)
- `security-boundaries`: WHAT authority means (adjudication)
- `nonces` (this): WAS THIS REQUEST LEGITIMATELY ORIGINATED
  (origin verification)

The doctrinal extension this chunk establishes:

> **WordPress nonces are NOT merely security primitives.**
> **They constitutionally mediate action legitimacy through**
> **request-origin gating choreography. Nonce verification is**
> **Doctrine 6 (Authority Access Mediation) extended to**
> **request-origin authenticity dimension.**

Constitutional question this chunk answers:

> Do WordPress nonces merely verify requests, or
> constitutionally mediate action legitimacy itself?

**Verdict (per chunk's findings)**: Nonces constitutionally
mediate action legitimacy. Nonce verification is **6th
gating mechanism (6f) under Doctrine 6**: origin-
authenticity-gated mediation. plugin-dev becomes Doctrine 6's
**5th bounded context with direct manifestation** (1st
forward Doctrine 6 deployment in plugin-dev).

## SHAPE

### A. Nonce API surface (PHP)

```php
// Generate a nonce for a specific action
$nonce = wp_create_nonce( 'my-plugin_save_settings' );
// $nonce is a 10-character HMAC-derived hash

// Verify a nonce against expected action
$valid = wp_verify_nonce( $nonce, 'my-plugin_save_settings' );
// Returns:
//   1     - nonce was created < 12 hours ago (current tick)
//   2     - nonce was created 12-24 hours ago (previous tick)
//   false - invalid nonce

// Generate hidden form field with nonce + referer
wp_nonce_field(
    'my-plugin_save_settings',  // $action
    '_wpnonce',                  // $name (default '_wpnonce')
    true,                        // $referer (also adds _wp_http_referer)
    true                         // $echo (echo or return)
);

// Generate URL with nonce parameter
$url = wp_nonce_url(
    admin_url( 'admin.php?page=my-plugin&action=delete&id=42' ),
    'my-plugin_delete_42',       // $action (typically includes ID for uniqueness)
    '_wpnonce'                   // $name
);

// Convenience: verify admin form/URL nonce + referer
check_admin_referer(
    'my-plugin_save_settings',   // $action
    '_wpnonce'                   // $query_arg
);
// Calls wp_die() if invalid; returns 1|2 if valid

// Convenience: verify AJAX request nonce
check_ajax_referer(
    'my-plugin_ajax_action',     // $action
    'nonce',                     // $query_arg (default 'nonce')
    true                         // $die (call wp_die on failure)
);
// Returns 1|2 on success; calls wp_die or returns false on failure

// REST API nonce (handled by REST infrastructure)
// Header: X-WP-Nonce: {nonce_value}
// Action: 'wp_rest'
$rest_nonce = wp_create_nonce( 'wp_rest' );
```

| function | role |
|---|---|
| `wp_create_nonce` | generate action-bound HMAC token (10 chars) |
| `wp_verify_nonce` | verify token; returns 1 (current tick) / 2 (previous tick) / false |
| `wp_nonce_field` | generate hidden form field with nonce + optional referer |
| `wp_nonce_url` | generate URL with nonce query parameter |
| `check_admin_referer` | admin context verify; auto-dies on failure |
| `check_ajax_referer` | AJAX context verify; auto-dies (configurable) |
| `wp_rest` action | standard REST API nonce action name |

### B. JS-side nonce surfacing

```javascript
// Nonces typically passed via wp_localize_script or inline script
wp_localize_script( 'my-plugin-script', 'myPluginData', [
    'nonce'   => wp_create_nonce( 'my-plugin_ajax_action' ),
    'ajaxurl' => admin_url( 'admin-ajax.php' ),
] );

// JS-side usage
fetch( myPluginData.ajaxurl, {
    method: 'POST',
    body: new URLSearchParams( {
        action: 'my_plugin_action',
        nonce: myPluginData.nonce,
        // ... other data
    } ),
} );

// REST API: wpApiSettings.nonce auto-injected
import apiFetch from '@wordpress/api-fetch';
// apiFetch automatically sends X-WP-Nonce header
apiFetch( { path: '/my-plugin/v1/data', method: 'POST', data: {} } );
// Equivalent header: X-WP-Nonce: wpApiSettings.nonce
```

| JS-side surface | role |
|---|---|
| `wp_localize_script` | bridge nonce from PHP to JS context |
| `wpApiSettings.nonce` | global REST API nonce (auto-set by WP) |
| `apiFetch` middleware | auto-injects X-WP-Nonce header |
| `X-WP-Nonce` header | REST API request authentication header |

### C. Nonce internals — HMAC tick algorithm

```
Nonce generation algorithm:

1. Compute current tick: floor( time() / (NONCE_LIFE / 2) )
   - Default NONCE_LIFE = DAY_IN_SECONDS (86400)
   - Tick changes every 12 hours (NONCE_LIFE / 2)

2. Compose payload string:
   $i = wp_nonce_tick();
   $payload = "{$i}|{$action}|{$user_id}|{$token}"
   - $token from session token (or 0 for unauthenticated)

3. Compute HMAC:
   $hash = hash_hmac( 'md5', $payload, $secret )
   - $secret derived from NONCE_SALT + NONCE_KEY (wp-config.php)

4. Truncate hash:
   $nonce = substr( $hash, -12, 10 )
   - 10 hex chars returned to client

Verification algorithm:
   Compute expected nonce for current tick → compare to provided
   If no match: compute expected for previous tick → compare
   Return: 1 (current), 2 (previous), false (no match)
```

| concept | character |
|---|---|
| **NONCE_LIFE** | default 24 hours; redefinable in wp-config.php |
| **NONCE_SALT / NONCE_KEY** | secret material (wp-config.php constants) |
| **Tick** | 12-hour window granularity (NONCE_LIFE / 2) |
| **2-tick window** | nonces valid for 12-24 hours; not single-instant |
| **Action binding** | nonces are action-specific (cannot be reused for different actions) |
| **User binding** | nonces incorporate $user_id (session-scoped) |
| **Session-token binding** | nonces incorporate session token (per-session-scoped) |

> **Nonces are NOT cryptographic random tokens.** They are
> deterministic HMAC values derived from action + user +
> session + tick + secret. Same inputs produce same nonce
> within a tick.

### D. CSRF threat model + nonce mediation choreography

```
Without nonces (CSRF vulnerability):

   Attacker site:
      <img src="https://victim-wp/wp-admin/admin.php?action=delete&id=42">
      (or auto-submitting form to admin-post.php)
   ↓
   User visits attacker site while logged into victim-wp
   ↓
   Browser sends request to victim-wp WITH USER'S COOKIE
   ↓
   Victim-wp processes request (cookie indicates legit session)
   ↓
   Action executes WITHOUT user's intent
   ↓
   ATTACK SUCCESSFUL

With nonces (CSRF mitigation):

   1. User visits victim-wp legitimately
   2. WP renders form/URL with nonce field
      <input type="hidden" name="_wpnonce" value="ab12cd34ef">
      OR URL: ...?action=delete&_wpnonce=ab12cd34ef
   3. User submits form / clicks URL
   4. Request includes nonce (from page context)
   5. Server: check_admin_referer('delete_42')
      - Verifies nonce matches expected for user + action
      - Verifies _wp_http_referer matches site
   6. Pass → process action; Fail → wp_die

   Attacker cannot generate valid nonce because:
      - Nonce binds to specific user_id (attacker can't know
        target user's user_id reliably AND attacker doesn't
        have NONCE_SALT/KEY secret)
      - Nonce binds to session_token (attacker can't access
        target user's session token)
      - Nonce binds to action (must match exactly)
      - Nonce binds to current tick (window-bound)
```

The nonce choreography is **request-origin gating mediation**:
- Form rendering = nonce GENERATION (page context)
- Form submission = nonce TRANSPORT (request body / URL)
- Server processing = nonce VERIFICATION (gating point)
- Verification result = mediation OUTCOME (proceed / die)

### E. Nonce + capability paired pattern

```php
// Standard secure action handler pattern (paired check)
add_action( 'admin_post_my_plugin_save', function () {
    // GATE 1: Origin verification (nonce)
    check_admin_referer( 'my_plugin_save_settings' );
    
    // GATE 2: Authorization (capability)
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Insufficient permissions.', 'my-plugin' ) );
    }
    
    // GATE 3: Input validation (sanitization)
    $value = sanitize_text_field( $_POST['my_value'] ?? '' );
    
    // Now safe to mutate state
    update_option( 'my_plugin_setting', $value );
    wp_safe_redirect( admin_url( 'admin.php?page=my-plugin&saved=1' ) );
    exit;
} );
```

| gate | dimension | mediation form |
|---|---|---|
| Nonce verification | request-origin | origin-authenticity-gated mediation (NEW Doctrine 6 sub-element) |
| Capability check | user authorization | capability-gated mediation (Doctrine 6a) |
| Input sanitization | data integrity | not mediation (data validation) |

> **Nonce + capability are STRUCTURALLY DISTINCT gates.**
> Nonce: "did this request originate legitimately?"
> Capability: "does this user have authority for this action?"
> Both required; neither sufficient alone.

This **paired-gate pattern** is plugin-dev security baseline.
Reasoning through Doctrine 6 reveals that the pair is
**two distinct mediation forms operating sequentially**:
origin-authenticity-gated (6f) + capability-gated (6a).

### F. Action-binding granularity patterns

```php
// Pattern A: Generic action (less granular, less secure)
$nonce = wp_create_nonce( 'delete_post' );
// Same nonce for ANY post deletion → no per-resource binding

// Pattern B: Resource-bound action (recommended)
$nonce = wp_create_nonce( "delete_post_{$post_id}" );
// Per-post nonce; deletion of post 42 ≠ deletion of post 99

// Pattern C: User+resource bound (explicit)
$nonce = wp_create_nonce( "user_{$user_id}_delete_post_{$post_id}" );
// Note: nonces ALREADY bind to user_id internally
// This pattern is redundant unless cross-user delegation
// scenarios are involved

// Pattern D: REST endpoint (standard)
$nonce = wp_create_nonce( 'wp_rest' );
// Single nonce for all REST requests; per-endpoint
// authorization handled separately
```

| pattern | granularity | use case |
|---|---|---|
| Generic action | coarse | low-risk, single-resource actions |
| Resource-bound | fine | per-resource mutations (recommended) |
| User+resource bound | redundant (usually) | cross-user delegation |
| `wp_rest` standard | per-session | REST API requests |

**Granularity affects mediation specificity**: coarse-grained
nonces enable resource-substitution attacks (valid nonce for
post A reused on post B); resource-bound nonces eliminate
this surface.

### G. Failure surfaces — origin-verification debt

```
Nonce failure modes:

- Missing nonce verification entirely → CSRF vulnerability
- Nonce check ONLY (no capability check) → bypass via legit
  but unauthorized user
- Capability check ONLY (no nonce check) → CSRF vulnerability
  via authorized user
- Generic action name (delete_post) → resource-substitution
  attack
- Reusing nonces across distinct actions → action confusion
- Storing nonces in long-term cache → tick-window violation
- Failing to die on verify failure → silent acceptance
- check_ajax_referer with $die=false but no manual die →
  request continues past verification
- Nonce generation in cached page → stale nonce delivery
- Nonce in GET URL (visible in logs/referrer) → leak surface
- Nonce in clipboard-copyable URL → social-engineering surface
- NONCE_SALT/KEY weak or default → hash predictability
- NONCE_SALT/KEY shared across environments → cross-env replay
- Nonce verification on non-mutating GET → false sense of
  security (GET should be idempotent regardless)
- AJAX endpoint without nonce → admin-ajax CSRF surface
- REST endpoint without permission_callback → bypass even
  with nonce (nonce is identity, not authorization)
```

**Origin-verification debt** (13th debt-pattern instance in
KB; first **request-origin governance debt**):

| debt mode | symptom |
|---|---|
| nonce omission | CSRF surface |
| nonce-only (no cap) | unauthorized actions by authenticated users |
| cap-only (no nonce) | CSRF via authorized users |
| coarse action binding | resource-substitution attacks |
| stale nonce delivery | cached pages serve expired nonces |
| GET-URL nonce leak | visible in logs/referrer headers |
| weak NONCE_SALT/KEY | hash predictability |
| silent verify failure | request proceeds despite failed gate |
| permission_callback omission | REST bypass (nonce ≠ authorization) |

13 instances × 7 bounded contexts. Governance debt
meta-pattern continues; now spans **request-origin
governance** dimension (1st origin-authenticity debt).

## REQUIRES

- WP environment (nonce functions are WP core, available
  always since WP 2.0.4+).
- WP 5.0+ recommended for stable REST API nonce conventions.
- `NONCE_SALT` + `NONCE_KEY` defined in wp-config.php
  (random secrets; auto-generated by WP installer).
- Nonces MUST be paired with capability check for any
  state-mutating action (nonce alone is NOT authorization).
- For AJAX: nonce surfaced via wp_localize_script or
  equivalent.
- For REST: `permission_callback` MUST be defined (nonce is
  authentication, not authorization).
- For forms: wp_nonce_field call within form HTML before
  closing tag.
- For URLs: wp_nonce_url wraps action URL.
- Tick-window awareness: nonces valid 12-24 hours; cache
  considerations for nonce-bearing pages.
- ⚠ Specific behaviors: NONCE_LIFE redefinition impact,
  multisite nonce scope, REST API nonce edge cases (cookie
  authentication required), session-token binding behavior
  for unauthenticated users — verification-needed.

## INVARIANTS

### 1. Nonce verification IS Doctrine 6 manifestation (origin-authenticity-gated mediation, NEW 6f)

The constitutional capstone finding for this chunk:

> Nonces are NOT merely security primitives. They are
> **request-origin gating mediation** — Doctrine 6
> manifestation in request-authenticity dimension. Nonce
> verification gates ACCESS TO ACTION EXECUTION based on
> request-origin authenticity (not capability, not routing,
> not cognitive surface, not subscription, not context).

| Doctrine 6 sub-element | bounded context | gating mechanism |
|---|---|---|
| 6a Capability-gated | admin-ui (settings-api) | user capability check |
| 6b Routing-gated | admin-ui (admin-menus) | navigation topology |
| 6c Cognitive-surface-gated | admin-ui (notices) | multi-axis attention gating |
| 6d Subscription-gated | editor-customization (editor-hooks) | direct subscribe/dispatch |
| 6e Context-reassignment-gated | i18n (locale-switching) | runtime context mutation |
| **6f Origin-authenticity-gated (NEW)** | **plugin-dev (nonces)** | **request-origin HMAC verification** |

> **NEW Doctrine 6 sub-element observed**: 6f Origin-
> authenticity-gated mediation.

Per Phase 8.5 sub-element promotion path, 6f starts at
**Local sub-element status** (1 chunk; plugin-dev). Future
manifestations may strengthen 6f toward Recurring (intra-
context) or Recurring (cross-context) sub-element status.

### 2. Authority Mediation Surface — STRENGTHENED to 5-context cross-context PRESENCE

> **MAJOR Mediation re-audit evidence accumulator.**

Pre-this-chunk Authority Mediation Surface status (post-
Doctrine 6 promotion):
- Doctrine 6 (formal status)
- 4-context cross-context PRESENCE (admin-ui, editor-
  customization, i18n + plugin-dev INDIRECT)

Post-this-chunk Authority Mediation Surface status:
- Doctrine 6 (formal status)
- **5-context cross-context PRESENCE** (admin-ui, editor-
  customization, i18n, plugin-dev DIRECT, + plugin-dev
  capability infrastructure was always foundational)
- **6 distinct gating mechanisms** (6a-6f)
- **Cross-character-category expansion**: Doctrine 6 now
  manifests in Authority federation category (plugin-dev)
  beyond Governance modulation category

> **Mediation re-audit viability substantively advanced.**
> Cross-context expansion to 5 contexts (Phase 8.x audit
> required ≥4); cross-character-category expansion (Authority
> federation manifestation NEW); 6 gating mechanisms vs prior
> 5.

This directly improves Mediation criterion 1 (context breadth)
+ criterion 5 (forward diversity adds plugin-dev forward
chunk) + criterion 6 (gating abstraction independence
strengthened by 6f's request-origin form, structurally
distinct from prior 5 forms).

### 3. Declaration ≠ Exposure (Law 1) at strongest manifestation in plugin-dev

Nonces exhibit **4-form declaration-exposure gap** in
plugin-dev:

| stage | character |
|---|---|
| Nonce GENERATED (wp_create_nonce) | declaration |
| Nonce TRANSPORTED (in form / URL / header) | partial exposure |
| Nonce VERIFIED (wp_verify_nonce / check_*_referer) | gating point |
| Action EXECUTED (post-verification) | exposure |

**4 distinct stages, each with independent failure mode**:
- Generated but never transported (nonce never reaches
  client)
- Transported but never verified (server skips check)
- Verified but action proceeds anyway (verify failure not
  followed by wp_die)
- All gates pass but capability check missing (action
  executes without authorization)

> **Law 1 manifests as 4-form declaration-exposure gap in
> nonces** — request-origin gating uses Law 1's structural
> framework with origin-authenticity dimension.

This is plugin-dev's strongest Law 1 manifestation. Mediation
+ Law 1 relationship clearly visible: nonce verification IS
the implementation mechanism for declaration ≠ exposure at
request-origin dimension.

### 4. Authority Continuity (Law 3) — request continuity chain

Nonces preserve **request-origin authority continuity** across:

```
Request continuity chain:

User session     User logs in; session_token established
   ↓
Page load        Server generates nonce binding
                 (action + user_id + session_token + tick)
   ↓
HTML render      Nonce embedded in form/URL/script
   ↓
Browser context  User intentionally interacts (click submit)
   ↓
Network transit  Browser sends request with nonce
   ↓
Server receive   Request enters action handler
   ↓
Verification     wp_verify_nonce reconstructs expected nonce
                 (same action + same user_id + same
                 session_token + current OR previous tick)
                 → match? continuity preserved
   ↓
Action execution Authority continuity verified; action proceeds
```

**6 continuity boundaries** preserved by nonce mechanism:
session-establishment / page-render / HTML-embedding /
browser-context-traverse / network-transit / server-
verification.

> **Law 3 manifests as REQUEST-ORIGIN CONTINUITY** in
> nonces. The HMAC binding IS the continuity contract;
> verification recomputes expected continuity and compares.

This is **NEW Law 3 manifestation form**: continuity
through HMAC binding (vs continuity through stack discipline
in locale-switching, vs continuity through immutable identity
in gettext).

### 5. Compiler ↔ Runtime Split (Law 6) — server-compile vs server-verify split

Nonces exhibit a within-server compile/verify split:

| stage | character |
|---|---|
| Nonce generation (wp_create_nonce on page render) | compile-time (per-request server) |
| Nonce verification (wp_verify_nonce on action handler) | runtime (per-request server) |

This is **server-side compile/runtime split** within single
HTTP request lifecycle:
- Generation = derives nonce from inputs
- Verification = re-derives nonce from inputs + compares

The split is **temporally subtle** (both happen on server)
but **structurally distinct** (generation produces token;
verification consumes token).

> **Law 6 manifests as INTRA-SERVER COMPILE/VERIFY SPLIT**
> in nonces — different from prior Law 6 manifestations
> (cross-process or cross-runtime). Server is BOTH compiler
> and runtime for nonces.

### 6. Resolution Surface — nonce verification as adjudication (cross-context PRESENCE confirmed)

Nonce verification exhibits Resolution character:

```
Verification resolution:

Inputs: provided nonce + action + current user + session token
   ↓
Compute expected nonce (current tick)
   ↓
   Match → return 1 (current tick)
   No match → compute expected nonce (previous tick)
      Match → return 2 (previous tick)
      No match → return false
```

This is **resolution among 3 candidates** (current-tick valid /
previous-tick valid / invalid). Per Phase 7.8 Resolution
Surface refusal verdict, this remains Recurring (cross-context)
pattern at Doctrine 5 layer (NOT KB-Wide).

> **Resolution Surface manifests in plugin-dev's 2nd direct
> instance** (after capabilities-and-roles Q9 retro Distributed
> finding).

### 7. Bridge Pattern — weak echo (PHP generation → transport → JS consumption)

Nonces exhibit weak Bridge Pattern:

```
Nonce bridge (REST API path):

PHP runtime          (wp_create_nonce + wp_localize_script)
   ↓ inline JS
HTML/JS bridge       (wpApiSettings.nonce global)
   ↓
JS consumption       (apiFetch → X-WP-Nonce header)
   ↓ HTTP request
PHP runtime          (REST_Server reads X-WP-Nonce)
   ↓
PHP verification     (wp_verify_nonce on cookie + nonce)
```

vs. classical Bridge Pattern (script-translations):
- Bridge ESTABLISHES JS-side authority continuation from PHP
  side
- script-translations: PHP locale_data → JS wp.i18n
- nonces: PHP-generated nonce → JS apiFetch → PHP verify

**Weak echo characterization**: Nonce bridge is
**round-trip** (PHP→JS→PHP) rather than **one-way**
(PHP→JS). This is structurally DIFFERENT from script-
translations Bridge (one-way authority transfer).

> **Bridge Pattern manifestation in nonces is structurally
> divergent from prior Bridge instances.** Round-trip
> character may warrant separate sub-classification or
> indicate Bridge Pattern is broader than initially observed.

Status: **Bridge Pattern manifestation NOTED but DIVERGENT
in character.** Does NOT add to Bridge Pattern instance count
(3 instances → still 3; this is structurally different
pattern).

### 8. Origin Verification Surface — NEW observation (Surfaced only)

This chunk surfaces a **potential new candidate**:
**Origin Verification Surface**.

Character:
- Verification of request authenticity through cryptographic
  / hash-based binding to legitimate origin context
- Distinct from capability authorization (different question:
  WHO is acting vs DID THIS REQUEST ORIGINATE LEGITIMATELY)
- Distinct from Mediation parent (origin verification is
  one form of gating; question is whether it's independent
  candidate)

Q-question: Is Origin Verification Surface:
- (a) Sub-form of Authority Mediation Surface
  (origin-authenticity-gated mediation = 6f sub-element)
- (b) Independent candidate (parallel to Resolution /
  Mediation / Interception / Routing)

> **Honest evaluation per Phase 7.5 Doctrine 3 Epistemic
> Integrity**: Single instance (plugin-dev only) is
> INSUFFICIENT evidence to determine sub-form vs independent
> candidate.

Status: **SURFACED ONLY — observation, NOT promoted.**
Per Phase 7.6+7.7 "surface, do not constitutionalize"
discipline. Default classification: **6f sub-element of
Doctrine 6** (least disruptive; consistent with Mediation
character analysis).

Cross-context verification candidates:
- REST API authentication broadly (cookie + nonce + bearer
  token verification chains)
- OAuth-style authentication for plugins (third-party
  authentication)
- Webhook signature verification (HMAC-bound external
  callbacks)
- Block editor save tokens (post lock, autosave nonces)

### 9. plugin-dev security trio completion (NEW bounded-context-level observation)

This chunk completes plugin-dev's **security trio**:

```
plugin-dev security trio:

   capabilities-and-roles:  WHO MAY ACT (authorization)
                             - capability adjudication doctrine
                             - Distributed Resolution variant
   
   security-boundaries:     WHAT AUTHORITY MEANS (adjudication)
                             - governance doctrine synthesis
                             - 4 boundary types
   
   nonces (this):           WAS THIS LEGITIMATELY
                            ORIGINATED (verification)
                             - Doctrine 6 sub-element 6f
                             - origin-authenticity-gated
                               mediation
```

| dimension | chunk | governance question |
|---|---|---|
| Authorization | capabilities-and-roles | who? |
| Adjudication | security-boundaries | what counts? |
| Origin verification | nonces | from whom? |

> **NEW BOUNDED-CONTEXT-LEVEL OBSERVATION**: plugin-dev
> exhibits **security tri-modal governance character**:
> Authorization + Adjudication + Origin Verification.

This may be **3rd tri-modal governance bounded context**
(after editor-customization lifecycle/topology/reactive +
admin-ui persistence/routing/signaling).

Status: **SURFACED only.** Tri-modal governance bounded
context character observation strengthens (3rd instance),
approaches threshold for cross-context character formalization
under Phase 7.6/7.7 deferred candidate discipline. Per
discipline, defer formalization until 4th tri-modal
manifestation OR formal patch.

### 10. KB Constitution + Doctrine 6 first forward deployment in plugin-dev — VALIDATION PASSED

> **First forward chunk authored under Phase 8.5 patched
> spec (Doctrine 6 native vocabulary).**

Pre-this-chunk Doctrine 6 manifestations were observed in
chunks authored BEFORE Doctrine 6 formal promotion (5 chunks
across admin-ui + editor-customization + i18n). This chunk
is the **first chunk authored AFTER Doctrine 6 formal
promotion**.

Test: does Doctrine 6 vocabulary integrate cleanly into
forward chunk authoring?

Post-this-chunk evidence:
- Doctrine 6 referenced natively (not retrofitted)
- 6f NEW sub-element identified using Doctrine 6 sub-element
  promotion path framework
- Doctrine 6 vocabulary clarified WHY nonce verification ≠
  capability check (different mediation forms)
- Doctrine 6 enabled cross-character-category Mediation
  expansion identification (plugin-dev as Authority federation
  Mediation manifestation)
- Mediation criterion advancement (5-context PRESENCE; 6
  gating mechanisms; cross-character-category expansion)
  documented

> **Verdict: Phase 8.5 Doctrine 6 first forward deployment
> validation PASSED.** Doctrine 6 vocabulary integrates
> cleanly; constitutional infrastructure synchronization
> proven operationally effective.

This is constitutional infrastructure validation:
synchronization patches do not merely document state but
enable native vocabulary use in subsequent forward
authoring.

## VERIFICATION NEEDED

`status: stable`. Items requiring verification:

- NONCE_LIFE redefinition impact on tick-window calculations.
- Multisite nonce scope (per-site vs network-wide secret).
- REST API nonce edge cases (cookie authentication
  requirement; application passwords interaction).
- Session-token binding behavior for unauthenticated users
  (nonces still work?).
- check_admin_referer + check_ajax_referer differences in
  failure handling.
- Nonce behavior under cache plugins (full-page cache
  serving stale nonces).
- AJAX nonce expiration UX (page open >24h, then submit).
- REST API nonce + JWT/OAuth combination patterns.
- Block editor post lock + autosave nonce mechanisms.
- Application Passwords nonce semantics (when do nonces
  become unnecessary?).
- nonce_user_logged_out filter behavior.
- Behavior when NONCE_SALT changes (all nonces invalidated?).
- Performance characteristics of HMAC computation under
  high-traffic scenarios.

For practical decisions: empirical testing per WP version +
authentication mechanism + caching configuration.

## ANTIPATTERNS

- ❌ **Nonce omission**. Any state-mutating action without
  nonce verification = CSRF surface. Always verify.
- ❌ **Nonce-only without capability check**. Verifies
  request origin but not authorization. Pair with
  current_user_can.
- ❌ **Capability-only without nonce check**. Verifies
  authorization but not origin. CSRF via authorized users.
  Pair with check_*_referer.
- ❌ **Generic action names**. `wp_create_nonce('delete')`
  = same nonce for ALL deletes → resource-substitution
  attack. Bind to specific resource:
  `wp_create_nonce("delete_{$id}")`.
- ❌ **Reusing nonces across actions**. Each distinct action
  needs distinct action name; reuse breaks gating
  granularity.
- ❌ **Storing nonces in long-term cache**. Tick-window is
  12-24h; cached nonces beyond window invalid. Don't cache.
- ❌ **Silent verify failure**. wp_verify_nonce returning
  false but code proceeding = no actual gating. Always
  wp_die or check_*_referer (auto-die).
- ❌ **check_ajax_referer with $die=false but no manual die**.
  Requires explicit handling; safer to leave $die=true.
- ❌ **Nonce in GET URL when sensitive**. URL params logged;
  prefer POST forms + body nonce for sensitive actions.
- ❌ **Verifying nonce on idempotent GET**. GET should be
  idempotent regardless of nonce; nonce on GET implies
  state mutation (move to POST).
- ❌ **Plain admin-ajax endpoint without nonce**. AJAX
  handlers MUST verify nonce; admin-ajax provides no
  built-in CSRF protection.
- ❌ **REST endpoint without permission_callback**.
  Nonce authenticates session; permission_callback authorizes
  action. Both required for mutations.
- ❌ **Weak NONCE_SALT/NONCE_KEY** (default placeholder).
  wp-config.php MUST contain WordPress.org-generated
  random values; defaults break HMAC predictability.
- ❌ **Sharing NONCE_SALT/KEY across environments**.
  Production / staging / dev nonces become cross-replayable.
  Each environment needs distinct secrets.
- ❌ **Treating wp_verify_nonce return as boolean**. Returns
  1 / 2 / false. Truthy check works for "valid" but loses
  tick-window info; explicit `=== false` for invalid is
  clearer.
- ❌ **Nonce field outside form**. wp_nonce_field MUST be
  within `<form>` to be submitted; outside form = no
  transport.
- ❌ **Manual nonce HTML construction**. Use wp_nonce_field /
  wp_nonce_url; manual HTML risks escaping errors.

## RELATED

- `plugin-dev.security-boundaries` — security trio sibling
  (governance doctrine synthesis); paired for full security
  architecture.
- `plugin-dev.capabilities-and-roles` — security trio sibling
  (authorization adjudication); paired with nonces for
  complete request gating.
- `plugin-dev.register-rest-route` — REST API nonce
  integration via permission_callback; wp_rest action;
  X-WP-Nonce header.
- `admin-ui.notices` — AJAX dismissal nonce pattern parallel.
- `editor-customization.editor-hooks` — editor request
  nonces (autosave, save).
- `_meta.structural-patterns` — Phase 8.5 patched spec;
  Doctrine 6 (Authority Access Mediation Doctrine);
  6f sub-element (NEW).
- `_meta.kb-audit-phase8-mediation-surface` — Doctrine 6
  audit reference.

## META

**plugin-dev bounded context — 3rd security trio chunk;
first Doctrine 6 native forward deployment + Mediation
5-context cross-context PRESENCE + 6f sub-element NEW
observation.**

### Phase 8.5 patched framework deployment (FIRST POST-PATCH FORWARD CHUNK)

Per Phase 8.5 Constitutional Synchronization Patch:

1. ✅ **Patched verdict taxonomy deployed** (5-class).
2. ✅ **Patched maturity ladder applied** (5-tier).
3. ✅ **Q8 adjudication doctrine operationalized**.
4. ✅ **Doctrine 5 (Arbitration ↔ Resolution Paired
   Operations) directly applied** — verdict: Resolution
   manifests in nonce verification (cross-context PRESENCE
   confirmed in plugin-dev 2nd direct instance).
5. ✅ **Doctrine 6 (Authority Access Mediation) directly
   applied — FIRST FORWARD DEPLOYMENT** — verdict: 6f
   Origin-authenticity-gated mediation NEW sub-element.
6. ✅ **Q9 retroactive verification trigger applied**.
7. ✅ **Q10 sub-pattern emergence diagnostic applied** —
   verdict: Origin Verification Surface NEW observation
   (sub-form vs independent UNDETERMINED; default
   classification 6f sub-element).
8. ✅ **Q11 N/A** (per-audit invocation; this is forward
   chunk, not audit).

### Doctrinal extension established

> **WordPress nonces are NOT merely security primitives.**
> **They constitutionally mediate action legitimacy through**
> **request-origin gating choreography. Nonce verification**
> **is Doctrine 6 (Authority Access Mediation) extended to**
> **request-origin authenticity dimension.**

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law / Doctrine | Prediction | Observation | Status |
|---|---|---|---|
| **Law 1 — Declaration ≠ Exposure** | VERY STRONG | 4-form gap (nonce generated / transported / verified / action executed); each stage independent failure mode | **Confirmed (4-form gap; strongest plugin-dev manifestation)** |
| **Law 3 — Authority Continuity** | STRONG | 6-boundary request-origin continuity chain (session → page → HTML → browser → network → server-verify); HMAC binding IS continuity contract | **Confirmed (NEW Law 3 manifestation form: HMAC-binding continuity)** |
| **Law 4 — Arbitration Compiler** | Moderate | Verification adjudicates among 3 candidates (current-tick / previous-tick / invalid) | **Confirmed (3-candidate verification adjudication)** |
| **Law 6 — Compiler ↔ Runtime Split** | (test) | Server-side compile/verify split within single request lifecycle | **Confirmed (NEW Law 6 form: intra-server compile/verify split)** |
| **Doctrine 6 — Authority Access Mediation** | CRITICAL TEST | Nonce verification IS Doctrine 6 manifestation (6f Origin-authenticity-gated mediation NEW sub-element) | **Confirmed (NEW 6f sub-element; 5-context cross-context PRESENCE; 6 gating mechanisms; cross-character-category expansion to Authority federation)** |
| **Resolution Surface (candidate)** | Moderate | Nonce verification adjudicates 3 candidates → resolved verdict | **Confirmed (cross-context PRESENCE; plugin-dev 2nd direct instance after capabilities-and-roles)** |
| **Authority Mediation Surface (Doctrine 6)** | CRITICAL TEST | 5-context cross-context PRESENCE; 6 gating mechanisms (NEW 6f); cross-character-category expansion | **STRENGTHENED (re-audit viability advanced; criterion 1 + 5 + 6 strengthened)** |
| **Bridge Pattern (observation)** | Weak echo | Round-trip PHP→JS→PHP (REST API path); structurally divergent from prior one-way bridges | **Noted but DIVERGENT character (does NOT add to Bridge Pattern instance count)** |
| **Origin Verification Surface (NEW observation)** | (test) | Verification of request authenticity through HMAC binding to legitimate origin context | **Surfaced only (single bounded-context observation; default classification: 6f sub-element of Doctrine 6)** |
| **Authority Interception Surface** | Weak | nonce verification doesn't intercept; it gates | **Divergent** |
| **Federation Pattern** | Implicit | Multiple plugins generate independent nonces with shared NONCE_SALT/KEY federation | **Confirmed implicitly (federation through shared secret material)** |

**Universal law manifestation: SUCCESS — major validations:**
- **Doctrine 6** CRITICAL TEST PASSED (NEW 6f sub-element;
  5-context PRESENCE; cross-character-category expansion)
- **Law 1** strongest plugin-dev manifestation (4-form gap)
- **Law 3** NEW manifestation form (HMAC-binding continuity)
- **Law 6** NEW form (intra-server compile/verify split)
- **Origin Verification Surface** NEW observation
- **Mediation re-audit viability** substantively advanced

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | nonces manifestation | Outcome |
|---|---|---|---|
| **Authority Mediation Surface (Doctrine 6)** | Doctrine-tier (Phase 8.x); 4-context cross-context PRESENCE; 5 gating mechanisms | CRITICAL: NEW 6f sub-element; 5-context cross-context PRESENCE; 6 gating mechanisms; cross-character-category expansion to Authority federation | **STRENGTHENED (Mediation criterion 1 + 5 + 6 advanced; KB-Wide LAW re-audit viability improved)** |
| **Resolution Surface** | Recurring (cross-context); KB-Wide REFUSED (Phase 7.8) | Nonce verification adjudicates 3 candidates (current-tick / previous-tick / invalid) | **Confirmed (cross-context PRESENCE; plugin-dev 2nd direct instance)** |
| **Selection from Candidates (sub-pattern)** | Recurring (cross-context, sub-pattern of Doctrine 5 Hybridized) | DIVERGENT — nonce verification is non-user-facing tick-window adjudication; deterministic algorithm | **Divergent (consistent with KB pattern: sub-pattern requires user-facing UI selection)** |
| **Authority Interception Surface** | Recurring (intra-context, editor-customization) + cross-context PRESENCE (admin-ui weak) | Not present — nonce verification gates, doesn't intercept | **Not present** |
| **Federation Pattern** | KB-Wide-equivalent (7-context recurrence) | Implicit: NONCE_SALT/KEY federation across plugins; per-plugin independent nonce generation | **Confirmed implicitly (federation through shared secret material; 8th-context manifestation)** |
| **Bridge Pattern (observation)** | Local (3 instances; 2 contexts) | Round-trip PHP→JS→PHP (REST API path); structurally divergent | **Divergent character (does NOT advance Bridge Pattern; Bridge may need sub-classification: one-way vs round-trip)** |
| **Administrative Routing Surface** | Surfaced (admin-ui only) | Not present — plugin-dev has no navigation topology | **Not present** |
| **Administrative Signaling Surface** | Surfaced (admin-ui only) | Not present — plugin-dev nonces are not signaling | **Not present** |
| **Origin Verification Surface (NEW observation)** | did not exist | Verification of request authenticity through HMAC origin binding | **Surfaced only (default classification: 6f sub-element of Doctrine 6; cross-context verification candidates: REST auth, webhook signatures, OAuth)** |
| **Tri-modal governance bounded context (observation)** | Surfaced (admin-ui + editor-customization) | plugin-dev exhibits security tri-modal: Authorization + Adjudication + Origin Verification | **STRENGTHENED (3rd tri-modal manifestation; approaches threshold for character formalization)** |

### Q9 Retroactive Verification Triggered

> **Q9 ANSWER: YES — this chunk reveals (a) Doctrine 6
> manifestations may exist in REST API authentication
> chains, (b) Bridge Pattern round-trip variant may exist
> in other PHP↔JS authentication mechanisms, (c) tri-modal
> governance pattern may exist beyond admin-ui + editor-
> customization + plugin-dev.**

**Q9 candidates triggered**:
1. **`plugin-dev.register-rest-route` retro** — REST API
   authentication chain; wp_rest nonce action; permission_
   callback gating layer; potential Doctrine 6 manifestation
2. **`interactivity.directive-protocol` retro** — directive
   serialization may need origin verification; potential
   Doctrine 6 manifestation in interactivity bounded context
3. **`editor-customization.editor-hooks` retro** — editor
   save/autosave nonce mechanisms; potential Doctrine 6
   intra-context strengthening
4. **`admin-ui.notices` retro** — AJAX dismissal nonce
   pattern (already observed in chunk); explicit Doctrine 6
   reference may strengthen
5. **`data-layer.persistence` retro** — REST mutations
   require nonce + permission_callback; potential Doctrine 6
   manifestation in data-layer
6. **(specific) Application Passwords / OAuth chunks** —
   alternative authentication mechanisms; potential Doctrine 6
   variants

These are Q9 trigger flags. Notably, **plugin-dev.register-
rest-route retro** is highest priority — if confirmed, would
strengthen Doctrine 6 plugin-dev intra-context density to
2 chunks AND advance Mediation criterion 5 (forward + retro
both contributing).

### Q10 Sub-pattern Emergence (NEW observation surfaced; consistent with discipline)

> **Q10 ANSWER: YES — Origin Verification Surface NEW
> observation surfaced; default classification: 6f sub-element
> of Doctrine 6.**

This chunk surfaces Origin Verification Surface as potential
new pattern. Honest evaluation:
- (a) Sub-form of Authority Mediation Surface (6f sub-element):
  origin-authenticity-gated mediation
- (b) Independent candidate (parallel to Mediation, Resolution,
  etc.)

Per Phase 7.5 Doctrine 3 Epistemic Integrity + default-
classification preference: **6f sub-element of Doctrine 6
default**. Cross-context verification candidates (REST auth /
webhook signatures / OAuth) may strengthen toward independent
candidate status if 3+ instances emerge with structural
character distinct from other Doctrine 6 sub-elements.

> **Q10 honest finding**: NEW observation surfaced + default
> classification chosen (least disruptive) + cross-context
> verification path documented.

This is **first Q10 NEW observation under Phase 8.5 patched
spec**. Doctrine 6 sub-element framework enabled clean
classification (vs forcing independent candidate inflation).

### NEW KB-level findings

**1. Doctrine 6 — NEW 6f sub-element (Origin-authenticity-gated mediation)**

| sub-element | bounded context | gating mechanism |
|---|---|---|
| 6a Capability-gated | admin-ui | user capability |
| 6b Routing-gated | admin-ui | navigation topology |
| 6c Cognitive-surface-gated | admin-ui | multi-axis attention |
| 6d Subscription-gated | editor-customization | subscribe/dispatch |
| 6e Context-reassignment-gated | i18n | context mutation |
| **6f Origin-authenticity-gated (NEW)** | **plugin-dev** | **request-origin HMAC verification** |

> **Doctrine 6 sub-element count: 5 → 6.**

**2. Authority Mediation Surface — 5-context cross-context PRESENCE; cross-character-category expansion**

| context | character category | mediation form |
|---|---|---|
| admin-ui | Governance modulation | 6a + 6b + 6c (3 forms intra-context) |
| editor-customization | Governance modulation | 6d |
| i18n | Semantic substrate | 6e |
| **plugin-dev (NEW)** | **Authority federation** | **6f** |

> **Cross-character-category expansion is structurally
> significant.** Pre-this-chunk Doctrine 6 was concentrated
> in Governance modulation + Semantic substrate (2 of 5
> bounded context character categories). This chunk extends
> Doctrine 6 to **Authority federation** (3rd character
> category).

This directly improves Phase 8.x audit Mediation criterion
analysis (3/5 character categories → 3/5 still, but
qualitatively richer; 4/5 if plugin-dev counts as new
category presence). Future re-audit may find architectural
ubiquity stronger.

**3. Tri-modal governance bounded context character — 3rd manifestation**

| bounded context | modality 1 | modality 2 | modality 3 |
|---|---|---|---|
| editor-customization | LIFECYCLE | TOPOLOGY | REACTIVE |
| admin-ui | PERSISTENCE | ROUTING | SIGNALING |
| **plugin-dev (NEW)** | **AUTHORIZATION** | **ADJUDICATION** | **ORIGIN VERIFICATION** |

> **3rd consecutive tri-modal governance bounded context
> manifestation.** Approaches threshold for character
> formalization. Per Phase 7.6/7.7 deferred-candidates
> discipline, **3 instances** across distinct bounded
> contexts may warrant Phase 8.6+ formalization audit.

**4. Origin Verification Surface NEW observation**

Default classification: 6f sub-element of Doctrine 6.
Alternative classification (independent candidate) requires
3+ cross-context instances with structurally distinct
character.

**5. Bridge Pattern round-trip variant observation**

Pre-this-chunk Bridge Pattern instances were one-way
(PHP→JS for translations / persistence dismissal). This
chunk's nonce REST flow is round-trip (PHP→JS→PHP).
Bridge Pattern may need sub-classification.

Status: **Observation only.** Bridge Pattern remains 3
instances × 2 contexts (Local). Round-trip variant
recognition deferred.

**6. Phase 8.5 first forward deployment validation PASSED**

Doctrine 6 vocabulary integrated cleanly into native forward
chunk authoring. Constitutional infrastructure synchronization
proven operationally effective.

### Constitutional capstone test rationale (META framing)

This chunk's strategic role per user direction:

> **plugin-dev: security trio completion + Doctrine 6 first
> forward deployment + Mediation re-audit viability advancement**

**Verdict: SUCCESS.** plugin-dev security trio complete.
Doctrine 6 NEW 6f sub-element. Mediation 5-context
cross-context PRESENCE + cross-character-category expansion.
Tri-modal governance 3rd manifestation. Origin Verification
Surface NEW observation. Phase 8.5 deployment validated.

### KB-wide pattern recurrence updates

**Origin-verification debt = 13th debt-pattern instance:**

| chunk | debt name | bounded context |
|---|---|---|
| security-boundaries | security debt | plugin-dev |
| block-filters | interception debt | editor-customization |
| slotfills | topology debt | editor-customization |
| editor-hooks | reactive debt | editor-customization |
| settings-api | settings debt | admin-ui |
| admin-menus | navigation debt | admin-ui |
| template-hierarchy | resolution debt | site-building |
| block-pattern-resolution | pattern resolution debt | site-building |
| gettext-functions | translation debt | i18n |
| script-translations | cross-runtime translation debt | i18n |
| locale-switching | locale governance debt | i18n |
| notices | attention debt | admin-ui |
| **nonces** | **origin-verification debt** | **plugin-dev** |

13 instances × 7 bounded contexts. Governance debt
meta-pattern continues; now spans **request-origin governance**
(1st origin-authenticity debt; security-domain expansion).

**Authority Mediation Surface (5-context cross-context PRESENCE; 6 gating forms; cross-character-category expansion)**:
admin-ui (3-form: 6a+6b+6c) + editor-customization (6d) +
i18n (6e) + **plugin-dev (6f, Authority federation category)**.

**Doctrine 6 sub-element count**: 5 → 6 (6a-6f).

**Tri-modal governance bounded context observations**: 3
instances (editor-customization + admin-ui + plugin-dev).

**plugin-dev intra-context density**: 8 chunks total
(register-block-bindings-source, register-meta, register-rest-route,
security-boundaries, register-post-type, register-taxonomy,
capabilities-and-roles, **nonces**) — strongest plugin-dev
density to date.

### KB self-evaluation against spec criteria (Phase 8.5 patched)

- ✅ Accuracy — describes documented wp_create_nonce +
  wp_verify_nonce + check_*_referer + wp_nonce_field +
  wp_nonce_url APIs.
- ✅ Structural fit — 3rd plugin-dev security trio chunk;
  first forward Doctrine 6 deployment.
- ✅ Reusability — uses authority ontology glossary +
  Phase 8.5 vocabulary (Doctrine 6 / 6f sub-element /
  origin-authenticity-gated mediation / cross-character-
  category expansion).
- ✅ Phase fit — strategic plugin-dev security trio
  completion + Mediation re-audit viability advancement.
- ✅ Doctrine respect — declaration ≠ exposure 4-form;
  Epistemic Integrity preserved (Q10 NEW observation
  classified as default 6f sub-element rather than forcing
  independent candidate; Bridge Pattern round-trip variant
  observed but NOT promoted).
- ✅ **Q8 explicit answer**: Doctrine 6 NEW 6f sub-element
  (CRITICAL TEST PASSED); Mediation STRENGTHENED (5-context
  PRESENCE; 6 gating forms); Resolution Confirm (cross-context
  PRESENCE plugin-dev 2nd direct); Federation Confirm
  (8th-context manifestation); Bridge Pattern Divergent
  (round-trip variant observation); Origin Verification
  Surface NEW observation (Surfaced; default 6f); Tri-modal
  governance 3rd manifestation.
- ✅ **Q9 explicit answer**: YES — register-rest-route +
  directive-protocol + editor-hooks + notices + persistence
  + Application Passwords / OAuth Q9 retro candidates.
- ✅ **Q10 explicit answer**: YES — Origin Verification
  Surface NEW observation surfaced (default classification
  6f sub-element of Doctrine 6; cross-context verification
  path documented).
- ✅ **Q11 N/A** (per-audit invocation; this is forward
  chunk, not audit).

### Status: `stable`

Nonce APIs mature since WP 2.0.4+ (added 2007); HMAC
algorithm stable; check_*_referer + wp_nonce_field stable.
Verification-needed catalog covers behaviors but core APIs
are settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission

> **Do WordPress nonces merely verify requests, or**
> **constitutionally mediate action legitimacy itself?**

**Verdict: They constitutionally mediate action legitimacy.**
Nonce verification is **6f sub-element of Doctrine 6**:
origin-authenticity-gated mediation. plugin-dev becomes
**5th bounded context with direct Doctrine 6 manifestation**
+ **Authority federation category** (cross-character-category
expansion).

### plugin-dev bounded context — security trio CLOSURE STATUS

| chunk | dimension | constitutional contribution |
|---|---|---|
| capabilities-and-roles | authorization (WHO) | Doctrine 6a (capability-gated mediation) infrastructure; Distributed Resolution variant; 3rd PROMOTION EVENT (Q9 retro) |
| security-boundaries | adjudication (WHAT) | governance doctrine synthesis |
| **nonces** | **origin verification (FROM WHOM)** | **Doctrine 6f (origin-authenticity-gated mediation) NEW; Mediation 5-context cross-context PRESENCE; 5th forward Doctrine 6 chunk; cross-character-category expansion** |

**plugin-dev security trio: COMPLETE.**
**plugin-dev bounded context status: SECURITY TRIO CLOSURE
READY** (broader plugin-dev closure pending: register-* family
+ infrastructure chunks).

### Anticipated next chunks (priority)

1. **`plugin-dev.register-rest-route` Q9 retro** — REST API
   authentication chain Doctrine 6 manifestation; HIGHEST
   PRIORITY (advances Mediation criterion 5 partial gap;
   strengthens plugin-dev Doctrine 6 intra-context density
   to 2 chunks).

2. **`interactivity.directive-protocol` Q9 retro** — Bridge
   Pattern Recurring (cross-context) verification + potential
   Doctrine 6 interactivity manifestation.

3. **Mediation re-audit prep** — once Q9 retros executed,
   Phase 8.6 Mediation re-audit viability assessment for
   KB-Wide LAW promotion path.

4. **`data-layer.entity-resolution` Q9 retro** —
   `switch_to_blog` mediation parallel from locale-switching
   chunk; potential Doctrine 6 data-layer manifestation.

5. **`block-authoring.dynamic-rendering` Q9 retro** —
   Continuity-Governance pairing observation from locale-
   switching chunk; potential bounded-context-level pattern
   verification.

Recommended: **`plugin-dev.register-rest-route` Q9 retro** —
HIGHEST PRIORITY. This retro would (a) strengthen Doctrine 6
intra-plugin-dev density to 2 chunks (sub-element 6f +
related REST authentication mediation form), (b) directly
advance Mediation criterion 5 partial gap (forward + retro
both contributing), (c) potentially surface 7th Doctrine 6
sub-element (REST-permission-callback-gated mediation?), and
(d) prepare ground for Phase 8.6 Mediation re-audit.
