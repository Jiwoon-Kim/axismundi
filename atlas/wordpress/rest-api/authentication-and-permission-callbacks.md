---
rule_id: rest-api.authentication-and-permission-callbacks
domain: rest-api
topic: access-mediation-substrate
field_cluster: rest-auth-and-permission
wp_min: "5.5"
wp_recommended: "6.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
    section: "Adding custom endpoints — permission_callback requirement"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
    section: "REST API Authentication — cookie+nonce, application passwords"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/register_rest_route/
    section: "register_rest_route() — permission_callback parameter"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2020/07/22/rest-api-changes-in-5-5/
    section: "WP 5.5 — permission_callback enforcement"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/
    section: "Application Passwords (WP 5.6+) — REST authentication mechanism"
    captured: 2026-05-10
related:
  - plugin-dev.register-rest-route                # the substrate this chunk's authority layer sits on
  - plugin-dev.capabilities-and-roles             # the capability system permission callbacks consume
  - plugin-dev.nonces                              # nonce mechanism used by cookie auth
  - plugin-dev.security-boundaries                 # broader security trio chunk
  - admin-ui.screen-options                        # preference vs permission boundary (the inverse)
  - editor-customization.editor-preferences-store # preference vs capability boundary (parallel)
---

# RULE — REST API authentication + `permission_callback` — true access mediation substrate

## WHEN

You are reasoning about how a WordPress REST
API request is *authenticated* (which user
the request is acting as) and *authorized*
(whether that user is permitted to perform
the action). Use this knowledge when:

- Adding a custom REST endpoint and writing
  its `permission_callback`.
- Choosing between cookie + nonce
  authentication, application passwords,
  or a custom authentication mechanism.
- Diagnosing 401 (unauthenticated) vs 403
  (unauthorized) responses on REST
  endpoints.
- Reading core REST controller code to
  follow the request → user → permission →
  callback lifecycle.
- Implementing endpoint security correctly
  — knowing what the framework gates and
  what the application must gate.

This chunk does **not** cover:

- Route registration mechanics in detail
  — covered in `plugin-dev.register-rest-route`.
  This chunk pairs with that one as the
  *substrate (registration) → authority
  (permission gating)* pair.
- The capability system (`current_user_can`,
  role definitions) — covered in
  `plugin-dev.capabilities-and-roles`.
  This chunk's permission callbacks
  *consume* that capability system; the
  capability system itself is that
  chunk's territory.
- Nonce internals — covered in
  `plugin-dev.nonces`. Cookie+nonce auth
  uses nonces; the nonce mechanism is
  that chunk's topic.
- The full security trio (nonces +
  capabilities + sanitization) — covered
  in `plugin-dev.security-boundaries`.

The principle this chunk operates under:
**A REST request passes through two
distinct gates: *authentication*
(determining which user the request acts
as) and *authorization* (determining
whether that user may perform the
endpoint's action). The two gates answer
different questions, run at different
points, and use different mechanisms.
This is one of the **canonical Doctrine 6
positive-fit** terrains in WordPress —
true access mediation, not personalization-
shaped surface vocabulary.**

## SHAPE

### A. Authentication vs authorization — the chunk's central distinction

The two questions:

| Question                              | Answered by                            |
| ------------------------------------- | -------------------------------------- |
| **Authentication**: *Who are you?*    | The request's authentication mechanism (cookie, app password, custom auth) |
| **Authorization**: *Can you do this?* | The endpoint's `permission_callback`   |

The two are structurally distinct:

- **Authentication identifies an actor.** It
  determines `wp_get_current_user()` for the
  request. Anonymous requests are
  authenticated *as user_id 0*.
- **Authorization gates an action.** It
  decides whether the (authenticated)
  identity is permitted to perform the
  specific endpoint's operation.

A user can be:

- **Authenticated and authorized**:
  identified, and the endpoint allows them
  to act. Action proceeds.
- **Authenticated but unauthorized**:
  identified, but the endpoint refuses
  this action for this user (e.g., a
  contributor trying to delete others'
  posts). Response: 403.
- **Unauthenticated** and the endpoint
  requires authentication: no identity
  established, action refused. Response:
  401.
- **Unauthenticated** and the endpoint
  permits anonymous access: anonymous
  identity (user_id 0) is accepted; action
  proceeds.

The two gates *compose*: authentication
runs first (establishing identity);
authorization runs second (deciding what
that identity can do).

### B. Authentication mechanisms

WordPress REST API supports several built-in
authentication mechanisms; plugins can
register additional ones via the
`determine_current_user` filter.

#### Cookie + Nonce (default for logged-in users in same browser)

The dominant mechanism for authenticated
requests originating from the WordPress
admin / front-end. The flow:

```
1. User logs into WordPress (cookie set).
2. Code in the same browser session reads
   wpApiSettings.nonce (provided by
   wp_localize_script for wp-api script).
3. Code sends REST request with X-WP-Nonce
   header containing the nonce.
4. WP server: cookie identifies user; nonce
   validates request origin (CSRF protection).
5. Request proceeds with authenticated identity.
```

Used by: block editor, admin UIs, theme
JavaScript that runs in authenticated
sessions.

Cookie + nonce gives both **authentication**
(cookie → user) and **CSRF protection**
(nonce → request originated from authorized
session). Both must succeed for the request
to authenticate.

#### Application Passwords (WP 5.6+)

Per-user, app-specific credentials sent as
HTTP Basic Auth:

```
GET /wp-json/wp/v2/posts HTTP/1.1
Authorization: Basic {base64(username:app-password)}
```

The flow:

```
1. User generates an application password in
   their user profile (WP 5.6+ Users screen).
2. External application stores the credentials.
3. Application sends Basic Auth header on
   each REST request.
4. WP validates credentials against stored
   app-password hashes.
5. If valid: request authenticates as that user.
```

Used by: external integrations, mobile
apps, server-to-server scripts. Each app
has its own named password; revocable
independently.

#### Custom authentication via filter

Plugins can hook `determine_current_user` to
implement custom auth (OAuth, JWT, etc.):

```php
add_filter( 'determine_current_user', function( $user_id ) {
    // Inspect headers, validate token, return user ID or false.
    if ( $token = $_SERVER['HTTP_AUTHORIZATION'] ?? null ) {
        $verified_user_id = my_validate_token( $token );
        if ( $verified_user_id ) {
            return $verified_user_id;
        }
    }
    return $user_id;  // unchanged if our auth doesn't apply
} );
```

The filter runs early; whatever returns a
user ID (other than the fallback) wins.

#### Combined fallback chain

WordPress tries authentication mechanisms in
order:

1. Custom auth filters (plugins).
2. Application passwords.
3. Cookie authentication.

The first to produce a user ID establishes
the identity. None of them succeeding leaves
the request as anonymous (user_id 0).

### C. `permission_callback` — the authorization gate

Every endpoint registered via
`register_rest_route` must declare a
`permission_callback`:

```php
register_rest_route( 'myplugin/v1', '/items/(?P<id>\d+)', array(
    'methods'             => WP_REST_Server::EDITABLE,
    'callback'            => 'myplugin_update_item',
    'permission_callback' => 'myplugin_can_update_item',
) );

function myplugin_can_update_item( $request ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error(
            'rest_forbidden',
            __( 'You cannot edit items.', 'myplugin' ),
            array( 'status' => 403 )
        );
    }
    return true;
}
```

The callback's contract:

- **Receives the request object.** Can
  inspect the request URL, body, headers
  for context-dependent decisions.
- **Returns**:
  - `true` → permission granted; main
    callback proceeds.
  - `false` → permission denied; 401 if
    unauthenticated, 403 if authenticated
    but insufficient.
  - `WP_Error` → custom error response with
    explicit status code and message.

The permission callback is **gate-shaped**:
it decides *whether* the action is allowed,
not *what* the action does. The main
callback runs only when the permission
callback returns true (or a non-error
truthy value).

### D. The full request lifecycle

```
1. REST request arrives at /wp-json/...
   ↓
2. WordPress bootstraps, REST server initializes.
   ↓
3. Authentication chain runs:
   - determine_current_user filters
   - Application passwords
   - Cookie + nonce
   ↓
4. wp_get_current_user() now returns identified
   user (or user_id 0 if anonymous).
   ↓
5. REST server matches the URL to a registered route.
   ↓
6. permission_callback fires.
   ↓
7a. If permission_callback returns truthy:
       → main callback runs
       → response generated
       → returned with HTTP 200/201/etc.
7b. If permission_callback returns false/WP_Error:
       → main callback NOT run
       → error response returned (401 or 403 or custom)
```

The permission gate is **between** step 5
(routing) and step 7a (action). It is the
mandatory checkpoint that every action must
pass.

### E. Common permission patterns

| Intent                                      | Callback shape                                    |
| ------------------------------------------- | ------------------------------------------------- |
| **Public endpoint** (any visitor)           | `'permission_callback' => '__return_true'`        |
| **Authenticated only** (any logged-in user) | `'permission_callback' => function() { return is_user_logged_in(); }` |
| **Capability-gated**                        | `'permission_callback' => function() { return current_user_can( 'edit_posts' ); }` |
| **Per-resource capability**                 | `'permission_callback' => function( $req ) { return current_user_can( 'edit_post', $req['id'] ); }` |
| **Combined check**                          | `'permission_callback' => function( $req ) { … combined logic … }` |

The `__return_true` pattern is required for
explicitly-public endpoints. WordPress treats
**omission of permission_callback** as an
error (Section F); explicit `__return_true`
documents intent: "yes, public is the
correct decision here."

### F. Why `permission_callback` is required (WP 5.5+)

Before WP 5.5, `permission_callback` was
optional. Endpoints could be registered
without it, defaulting to public access.
This led to many plugins inadvertently
exposing private operations.

WP 5.5 made `permission_callback` mandatory.
Omitting it triggers `_doing_it_wrong()`:

```
PHP Notice: register_rest_route was called incorrectly.
The REST API route definition for myplugin/v1/items
is missing the required permission_callback argument.
For REST API routes that are intended to be public,
use __return_true as the permission callback.
```

The intent of the change: force endpoint
authors to **make an explicit decision**
about access. Public endpoints aren't wrong
— but they must be *deliberately public*,
not accidentally exposed by omission.

This is the audit-resistant version of the
authorization layer: every endpoint declares
its access intent in code; there is no
silent default that lets endpoints drift to
"public" by neglect.

## WHY

### Why authentication and authorization are separate

Conflating them would mean:

- "Logged in" would imply "permitted to
  do everything" — broken security model.
- Public endpoints would have to either
  reject all authenticated users or accept
  all (no middle ground for "public read,
  authenticated write").
- Per-resource permissions (this user can
  edit *this* post but not *that* one)
  would require unrolling identity checks
  inside identity logic.

The separation lets each concern be
expressed clearly:

- Authentication: "your identity is X"
  (mechanism varies; outcome is a user ID).
- Authorization: "user X is permitted to do
  action Y on resource Z" (mechanism is the
  capability system + per-endpoint logic).

### Why permission callbacks are mandatory

A default of "public if not specified" is
the failure mode of many web frameworks.
Plugin authors forget to add auth to a new
endpoint; the endpoint is silently public;
data leaks.

A default of "private if not specified"
breaks public endpoints (REST API for
unauthenticated content reading is a core
use case).

A *required explicit decision* (post-WP-5.5)
forces the question to be answered at code-
write time. The author can't accidentally
expose data; they can't accidentally
restrict public reads. They must declare
intent.

The cost is one extra line per endpoint
(the `permission_callback` parameter); the
benefit is no silent-default security
class of bug.

### Why the auth chain has fallback ordering

Different applications need different
authentication mechanisms:

- Browser-based admin UIs: cookie + nonce.
- External apps: application passwords or
  OAuth.
- Server-to-server: app passwords or
  custom auth.

A single mechanism would force one approach
for all use cases. The fallback chain lets
each mechanism try in turn; whichever
succeeds wins. Mechanisms can coexist on
the same site without explicit per-endpoint
configuration.

### Why permission callbacks receive the request

A permission callback that only checked
"can the user do X?" without context could
not implement per-resource permissions.
"Can edit_post 42" requires knowing the
post ID — which comes from the request URL.

Passing the request to the callback lets
permission logic depend on:

- URL path parameters (resource IDs).
- Query parameters (filtering scope).
- Request body (proposed mutations).
- Headers (additional context).

The callback can make capability decisions
informed by the full request shape.

## WHEN NOT

Skip the REST permission system if:

- You are **not building a REST endpoint**.
  Permission callbacks apply only to REST
  routes registered via
  `register_rest_route`.
- The request is **server-internal** (not
  via HTTP). Direct PHP function calls
  don't go through the REST stack;
  permission checks happen wherever you
  put them.
- The endpoint is part of a **non-WordPress
  REST framework** in the same plugin
  (e.g., a separate routing system).
  Use that framework's authorization
  mechanism.
- The data is **unconditionally public**
  (e.g., a static feed of public posts).
  Use `__return_true` for the callback;
  don't omit it.

## COUNTER-PATTERNS

### Anti-pattern 1 — Omitting `permission_callback`

```php
register_rest_route( 'myplugin/v1', '/sensitive', array(
    'methods'  => 'GET',
    'callback' => 'myplugin_get_sensitive',
    // Missing permission_callback!
) );
```

Triggers `_doing_it_wrong` notice. Some WP
versions might silently default to public
access (legacy behavior); newer versions
log the warning. Either way, the omission
is a security smell — explicit decision is
required:

```php
'permission_callback' => function() {
    return current_user_can( 'manage_options' );
},
```

### Anti-pattern 2 — Conflating authentication and authorization

```php
'permission_callback' => function() {
    return is_user_logged_in();  // any logged-in user
},
'callback' => function( $req ) {
    delete_post( $req['id'] );  // assumes any logged-in user can delete any post
},
```

Logged-in ≠ authorized to delete posts.
The callback assumes auth implies authz.
Add the capability check:

```php
'permission_callback' => function( $req ) {
    return current_user_can( 'delete_post', $req['id'] );
},
```

### Anti-pattern 3 — Permission check inside the main callback

```php
'permission_callback' => '__return_true',
'callback' => function( $req ) {
    if ( ! current_user_can( 'edit_post', $req['id'] ) ) {
        return new WP_Error( 'forbidden', '', array( 'status' => 403 ) );
    }
    // ... actual work
},
```

This works mechanically but defeats the
gate's purpose. The framework's REST
handling makes assumptions about
`__return_true` (e.g., for OPTIONS
preflight, for documentation generation,
for auth introspection). Putting the real
check inside the callback hides the
endpoint's actual security model.

Use `permission_callback` for the gate;
let `callback` focus on the work.

### Anti-pattern 4 — Authentication-only check passing for unauthenticated

```php
'permission_callback' => function() {
    if ( wp_get_current_user()->ID > 0 ) {
        return true;
    }
    return false;
},
```

Mechanically equivalent to
`is_user_logged_in()` but typed
incorrectly. Use the standard idiom; it's
clearer and less likely to drift.

### Anti-pattern 5 — Returning `false` when you mean `WP_Error`

```php
'permission_callback' => function() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return false;
    }
    return true;
},
```

Returning `false` produces a generic
401/403; the user / consumer doesn't know
*why* they're forbidden. Returning
`WP_Error` lets you provide diagnostic
detail:

```php
'permission_callback' => function() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error(
            'rest_cannot_edit',
            __( 'You cannot edit items.', 'myplugin' ),
            array( 'status' => 403 )
        );
    }
    return true;
},
```

### Anti-pattern 6 — Treating cookie auth as available for all REST consumers

```js
// External app's JavaScript trying to use cookie auth:
fetch( '/wp-json/myplugin/v1/items', {
    method: 'POST',
    headers: { 'X-WP-Nonce': someValue },
} );
```

Cookie + nonce works for *same-browser-
session* requests originating from
WordPress-rendered pages. External apps
(different browser, no cookie, can't read
WordPress's nonce) can't use it. They need
application passwords or custom auth.

## OPERATIONAL NOTES

The REST authentication and permission
substrate's interpretive shape, in
proportional v2 vocabulary:

- **Doctrine 6 (Authority Mediation)** is
  the **central PRIMARY POSITIVE FIT** —
  the chunk's principal doctrinal
  contribution. Permission callbacks are
  the canonical Doctrine 6 implementation
  in WordPress's REST layer:
  - **Mediates access**: the callback runs
    *between* request routing and action
    execution.
  - **Decides permission**: based on
    identity (authentication outcome) +
    context (request data).
  - **Terminates on denial**: if permission
    is denied, the action does not run;
    the request gets an error response.
  - **Cannot be bypassed by the requesting
    party**: the operator cannot grant
    themselves more capability through any
    UI choice; the gate is server-side and
    server-enforced.
  This contrasts sharply with the *false-
  positive Doctrine 6* terrains the recent
  KB has documented:
  - **Preferences** (8.34a / 8.46): no
    capability evaluation; operator can
    flip state freely; no gating.
  - **Block styles** (8.42): no permission
    check; operator picks variant; no
    enforcement.
  - **Hooks** (8.36) and **dashboard
    widgets** (8.39): registration is
    open; no access decision in the
    mechanism itself.
  - **Wrappers** (block-controls,
    inspector-controls): write-channel
    governance "soft mention"; not full
    mediation.
  Permission callbacks are different in
  kind: they evaluate, they decide, they
  terminate, they bind. This is true
  Doctrine 6.
- **Law 1 (Declaration ≠ Exposure)** is
  **PRIMARY** alongside Doctrine 6 (rare
  to have both at this strength,
  paralleling the rewrite-rules chunk's
  Law 1 + Law 4 double-PRIMARY at 8.40).
  The endpoint is *registered* (declared)
  via `register_rest_route`; it is
  *reachable as a successful action*
  (exposed) only when authentication
  succeeds AND authorization succeeds.
  Most registered endpoints are never
  successfully invoked by most requests —
  the gates filter heavily.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** The
  authentication chain has fallback
  ordering (custom filters → app
  passwords → cookies), which *looks*
  arbitration-shaped. But once one
  mechanism establishes identity, the
  chain stops — closer to Law 4 (terminal,
  discarding) than to composition. Worth
  noting as **adjacent positive Law 4
  fit** in the auth chain specifically.
  However, this chunk's *primary* topic
  is authorization (permission_callback),
  which is *not* Law 4 — each endpoint's
  permission callback is one decision
  point, not a candidate ladder.
- **Law 3b (Cross-Runtime Authority
  Continuity Bridge).** *Adjacent and
  explicitly non-fit* — but with a
  meaningful nuance. REST is cross-
  runtime (client → server); the
  *authentication* mechanism *does*
  preserve identity across the boundary
  (cookie carries the session;
  application password carries
  credentials). This is actually closer
  to Law 3b's authority-continuity
  framing than the prior anti-Law-3b
  inventory members. *But*: the
  preservation is *of identity for
  authentication*, not *of authority for
  action*. The action is gated by a
  fresh authorization decision on the
  server. So the authentication mechanism
  bridges identity; the authorization
  mechanism is server-local. This is a
  **partial Law 3b adjacency**: identity
  bridges; authority does not. Worth
  pinning as a more nuanced anti-Law-3b
  shape than the inventory's earlier
  members (which had no authority
  preservation at all).
- **Federation.** Multiple plugins
  register multiple routes; each route
  has its own permission callback. The
  registration is open (Federation
  shape), but each route's authorization
  is independent (no composition). This
  is *federation-with-per-route-
  independent-authorization* — a variant
  worth noting but not heavily
  elaborated.
- **Law 6 (Compiler ↔ Runtime Split).**
  All in PHP request runtime. Omitted.
- **Section X archetypes.** A REST auth
  / authorization substrate is not a
  "civilization." Same framework-
  omission discipline. Omitted.

### Doctrine 6 fit specification (parallel to Law 4 fit criterion at 8.40)

The Law 4 fit specification at 8.40 named
three criteria for true positive fit:
*ordered + terminal + discarding*. This
chunk's parallel contribution: a Doctrine 6
fit specification for true access mediation:

> **Mediates + Decides + Terminates +
> Binds = true Doctrine 6 access
> mediation.**
>
> - **Mediates**: the mechanism stands
>   *between* request and action; it is
>   not on the side, not after the fact,
>   not a logging hook.
> - **Decides**: the mechanism evaluates
>   identity + context against capability
>   to produce an explicit yes/no.
> - **Terminates on denial**: a denied
>   request does not proceed to the
>   action. There is no "tried, allowed
>   anyway" path.
> - **Binds**: the requesting party
>   cannot bypass the gate through any UI
>   choice, preference, or unilateral
>   action. The gate is server-enforced;
>   operators can't grant themselves more
>   than they have.

Mechanisms that lack one or more of these
are *not* full Doctrine 6 fits:

- Preferences mediate UI rendering, but
  don't decide capability or terminate
  action — they're personalization.
- `setAttributes` is a write-channel
  with governance flavor (8.30a soft
  mediation) but doesn't terminate or
  bind — operator UI freely produces
  attribute values.
- Capability registration declares
  capabilities but doesn't itself
  mediate action — that's the
  capability *system*, not access
  *gating*.

Permission callbacks satisfy all four
criteria. They are the canonical Doctrine
6 implementation in WordPress's REST
layer.

### Updated Doctrine 6 balance (parallel to Phase 8.M2's Law 4 balance update)

Before this chunk, the Doctrine 6 inventory
in the Phase 8.27+ KB looked like:

| Side                    | Members |
| ----------------------- | ------- |
| Doctrine 6 false-positive (anti-) | 7 (preferences / styles / variations / hooks / dashboard widgets / format types / write-channel softs) |
| Doctrine 6 positive anchors | 1 (capabilities-and-roles, Phase 7-era) |

After this chunk:

| Side                    | Members |
| ----------------------- | ------- |
| Doctrine 6 false-positive (anti-) | 7 (unchanged) |
| Doctrine 6 positive anchors | **2** (capabilities-and-roles + REST permission callbacks) |
| **+** | **Fit criterion specified** (Mediates + Decides + Terminates + Binds) |

The asymmetry remains (7:2), but it's now
a *coherent asymmetry* — the false
positives lack identifiable criteria the
positives satisfy. This parallels the Phase
8.M2 Law 4 balance update.

## Three literacy contributions worth pinning

> *Authentication ≠ authorization.* A
> mechanism that determines *who is making
> the request* is structurally distinct
> from a mechanism that decides *whether
> that party may perform the action*.
> Authentication produces an identity;
> authorization decides what that identity
> can do. Conflating them produces broken
> security models — every logged-in user
> permitted to do everything, or every
> public consumer blocked from anything.

This contribution names a foundational
distinction that recurs throughout web
security but is particularly clear in
WordPress's REST auth + permission
architecture. It's also a *parallel* to
the Phase 8.42/8.46 *preference ≠
permission* literacy: in both cases, two
governance-shaped concepts that look
similar are structurally distinct
mechanisms.

> *Endpoint registration ≠ action
> permission.* A REST route that is
> registered with `register_rest_route`
> is not the same as a route that the
> current request may successfully invoke.
> Registration places the route in the
> server's route table; permission
> callbacks decide who may use it. Most
> registered routes are denied to most
> requesters — that's the gate's job.

This contribution adds another
*registered-X ≠ effective-X* form to the
existence-vs-operation toolkit. The
pattern: *something is registered* is not
the same as *something is operationally
available to a particular party in a
particular context*. The KB now has many
forms of this (registered widget,
registered surface, registered format,
registered route, registered preference);
each is the same underlying pattern in a
different terrain.

> *Mediates + Decides + Terminates +
> Binds = true Doctrine 6 access
> mediation.* The criterion specification
> for positive Doctrine 6 fit, parallel
> to the Phase 8.40 Law 4 criterion
> (ordered + terminal + discarding). A
> mechanism that satisfies all four
> criteria is genuine access mediation;
> mechanisms that lack one or more are
> personalization, write-channel
> governance, or registration without
> gating — adjacent shapes, different
> mechanisms.

This contribution sharpens Doctrine 6's
boundary. The recent KB has been good at
recognizing *false positives* (Doctrine 6
non-fits in personalization terrain); this
chunk adds the inverse — recognizing what
Doctrine 6 *is* when it genuinely
appears. Together, the two sides form the
Doctrine 6 grammar:

- 7 false-positive members + 2 positive
  anchors + the criterion specification.
- Same grammar shape as Law 4's 10
  false-positive members + 5 positive
  anchors + 3-criterion specification
  (ordered + terminal + discarding).

The KB's doctrinal grammar continues to
mature: Doctrine 6 now joins Law 4 as a
doctrine with both *anti* inventory and
*positive* fit criterion, each side
sharpening the other.

## CHECKLIST

When working with REST authentication
and permission:

- [ ] Always declare `permission_callback`
      explicitly — even for public
      endpoints (use `__return_true`).
- [ ] Distinguish authentication from
      authorization: the two are separate
      mechanisms answering different
      questions.
- [ ] For per-resource permissions
      (`edit_post $id`), pass the request
      to the callback so it can read
      resource identifiers.
- [ ] Return `WP_Error` instead of `false`
      from permission callbacks for
      diagnostic clarity.
- [ ] Don't put authorization checks
      inside the main callback when they
      could go in the permission callback
      — the framework expects the gate
      pattern.
- [ ] Match the authentication mechanism
      to the consumer:
      cookie + nonce for browser-session
      requests; application passwords for
      external apps; custom filters for
      special integrations.
- [ ] When debugging "401 / 403," walk
      the lifecycle: was authentication
      successful? Was authorization
      called? What did it return?

## REFERENCES

- Adding custom REST endpoints handbook —
  documents `permission_callback`
  requirement.
  https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- REST API Authentication handbook —
  cookie + nonce, application passwords.
  https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- `register_rest_route()` reference —
  the `permission_callback` parameter.
  https://developer.wordpress.org/reference/functions/register_rest_route/
- Make WordPress Core — REST API changes
  in 5.5 (permission_callback enforcement).
  https://make.wordpress.org/core/2020/07/22/rest-api-changes-in-5-5/
- Application Passwords integration guide
  (WP 5.6+).
  https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/

Cross-context:

- `plugin-dev.register-rest-route` — the
  *substrate* this chunk's authority
  layer sits on. Together: route
  registration (substrate) + permission
  callbacks (authority gate) =
  complete REST endpoint authorship.
- `plugin-dev.capabilities-and-roles` —
  the capability system permission
  callbacks consume via
  `current_user_can`.
- `plugin-dev.nonces` — the nonce
  mechanism cookie auth uses for CSRF
  protection.
- `plugin-dev.security-boundaries` —
  the broader security trio chunk.
- `admin-ui.screen-options` and
  `editor-customization.editor-preferences-store`
  — the *opposite* shape: governance-
  looking surfaces that are personalization,
  not access mediation. This chunk's
  Doctrine 6 positive fit pairs with their
  Doctrine 6 explicit non-fit; together
  they form the Doctrine 6 boundary
  family.
