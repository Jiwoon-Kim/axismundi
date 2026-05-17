---
rule_id: admin-ui.notices
domain: admin-ui
topic: signaling-governance
field_cluster: administrative-attention
wp_min: "2.5"
wp_recommended: "6.4+"
status: stable
language: php-and-javascript
sources:
  - url: https://developer.wordpress.org/reference/hooks/admin_notices/
    section: "admin_notices action — function reference"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/wp_admin_notice/
    section: "wp_admin_notice() — WP 6.4+ standardized notice rendering"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/add_settings_error/
    section: "add_settings_error() / settings_errors() — settings notice queue"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-notices/
    section: "core/notices store — block editor notice API"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/classes/wp_screen/
    section: "WP_Screen — current screen context for notice gating"
    captured: 2026-05-09
related:
  - admin-ui.settings-api                           # Settings errors notice subsystem
  - admin-ui.admin-menus                            # Screen context (current_screen)
  - editor-customization.slotfills                  # core/notices block editor surface
  - plugin-dev.capabilities-and-roles               # capability gating for notices
  - i18n.gettext-functions                          # notice text translation
  - _meta.structural-patterns                       # Phase 7.5/7.6/7.7 patched spec
  - _meta.kb-consolidation-phase7-8                 # Constitution v1 reference
---

# RULE — admin notices — administrative signaling through conditional governance exposure surfaces (admin-ui depth completion)

## WHEN

A plugin or theme needs to communicate transient or persistent
administrative state to users in WP admin context — operation
results, validation errors, deprecation warnings, license
issues, update prompts, configuration recommendations, security
warnings, or any state that requires user acknowledgment or
attention.

Use admin notice mechanisms when:
- Action result feedback (settings saved, plugin activated,
  data imported, error encountered).
- Persistent state requiring attention (license expired,
  required configuration missing, security update available).
- Screen-specific guidance (notices only on plugin's settings
  page, not site-wide).
- Capability-gated signaling (notices only for users with
  specific roles).
- Block editor inline feedback (post saved, block error,
  publish action result).
- Settings form validation errors (Settings API integration).
- Network-wide vs site-specific signaling in multisite.

This is **3rd chunk in admin-ui bounded context** AND
**admin-ui tri-modal governance test chunk**. Following
settings-api (persistence governance) and admin-menus
(routing governance), this chunk tests whether admin-ui
constitutes a **third governance modality**: signaling
governance.

The doctrinal capstone this chunk establishes:

> **admin-ui is tri-modal governance bounded context:**
> **persistence (settings-api) + routing (admin-menus) +**
> **signaling (notices). Each modality is mediation under**
> **distinct gating mechanism. Together they constitute**
> **complete administrative authority surface area.**

Constitutional question this chunk answers:

> Does admin-ui govern only persistence + routing, or also
> govern administrative attention itself?

**Verdict (per chunk's findings)**: admin-ui governs
**administrative attention as 3rd governance modality**.
Notice signaling IS mediation surface (conditional gating
under capability/screen/persistence dimensions).
**admin-ui = first KB tri-modal governance bounded context**
(parallel to editor-customization's lifecycle/topology/reactive
tri-modal authority surface).

## SHAPE

### A. Notice hook surface (PHP-side action hooks)

```php
// Site admin notices (default admin context)
add_action( 'admin_notices', function () {
    echo '<div class="notice notice-success is-dismissible"><p>'
        . esc_html__( 'Settings saved.', 'my-plugin' )
        . '</p></div>';
} );

// Network admin notices (multisite super-admin context)
add_action( 'network_admin_notices', $callback );

// User admin notices (multisite user dashboard)
add_action( 'user_admin_notices', $callback );

// Catch-all: fires for site + network + user admin
add_action( 'all_admin_notices', $callback );
```

| hook | context | scope |
|---|---|---|
| `admin_notices` | site admin (`/wp-admin/`) | per-site |
| `network_admin_notices` | network admin (`/wp-admin/network/`) | network-wide |
| `user_admin_notices` | user admin (`/wp-admin/user/`) | per-user |
| `all_admin_notices` | all admin contexts | universal |

Hook context selection IS **first signaling gating dimension**
— signal scope determines which admin surface receives the
notice.

### B. Notice HTML schema (CSS class taxonomy)

```html
<div class="notice notice-{type} {is-dismissible}">
    <p>{message}</p>
</div>
```

| CSS class | role | visual |
|---|---|---|
| `notice` | base notice class (required) | left border + padding |
| `notice-success` | success state | green left border |
| `notice-warning` | warning state | yellow/orange left border |
| `notice-error` | error state | red left border |
| `notice-info` | informational | blue left border |
| `notice-alt` | alternate styling | grey background variant |
| `is-dismissible` | dismiss button enabled | adds × close button |
| `inline` | inline within content | no margin |
| `hide-if-js` | shown only when JS disabled | progressive enhancement |

```html
<!-- Examples -->
<div class="notice notice-success is-dismissible">
    <p><strong>Settings saved.</strong></p>
</div>

<div class="notice notice-error">
    <p>API key invalid. Please reconfigure.</p>
</div>

<div class="notice notice-warning notice-alt inline">
    <p>This setting will be deprecated in version 2.0.</p>
</div>
```

### C. `wp_admin_notice()` standardized rendering (WP 6.4+)

```php
// WP 6.4+ standardized notice rendering function
wp_admin_notice(
    'Settings saved.',                        // $message
    [
        'type'               => 'success',    // success|warning|error|info
        'dismissible'        => true,         // adds is-dismissible class
        'id'                 => 'my-notice',  // notice ID for targeting
        'additional_classes' => [ 'inline' ], // extra CSS classes
        'attributes'         => [],           // arbitrary HTML attributes
        'paragraph_wrap'     => true,         // wrap message in <p>
    ]
);

// Get HTML without echoing
$html = wp_get_admin_notice( $message, $args );
```

| arg | role |
|---|---|
| `type` | maps to notice-{type} CSS class |
| `dismissible` | maps to is-dismissible CSS class |
| `id` | HTML id attribute (for JS targeting / dismissal tracking) |
| `additional_classes` | array of extra CSS classes |
| `attributes` | array of HTML attributes (data-*, role, etc.) |
| `paragraph_wrap` | whether to wrap $message in <p> tag |

`wp_admin_notice` IS **declaration ≠ exposure** mediation
function: standardizes HTML structure (declaration) but
output still requires hook handler invocation (exposure).

### D. Settings API notice subsystem (queue-based signaling)

```php
// Register a settings error/notice into queue
add_settings_error(
    'my_setting',                              // $setting (slug)
    'invalid_value',                           // $code (error code)
    __( 'Value must be numeric.', 'my-plugin' ), // $message
    'error'                                    // $type: error|success|warning|info
);

// Get queued notices
$errors = get_settings_errors( $setting = '', $sanitize = false );

// Render queued notices (typically called in settings page header)
settings_errors( $setting = '', $sanitize = false, $hide_on_update = false );
```

| function | role |
|---|---|
| `add_settings_error` | enqueue notice into transient queue |
| `get_settings_errors` | retrieve queued notices |
| `settings_errors` | render queued notices as HTML |

Settings notices are **queued + transient** — survive
single redirect (via transient or `$wp_settings_errors`
global), then consumed. Different lifecycle from
`admin_notices` hook (immediate render).

```
Settings error lifecycle:

1. Validation callback adds error
   add_settings_error('opt', 'code', 'msg', 'error')
   ↓
2. Stored in $wp_settings_errors global + transient
   (transient survives redirect post-form-submit)
   ↓
3. Settings page loads, calls settings_errors()
   ↓
4. Notices rendered, transient cleared
   ↓
5. Subsequent loads: notices gone (consumed)
```

Settings errors API is **deferred-rendering signaling
mediation** — separates declaration (validation time)
from exposure (rendering time + redirect boundary).

### E. Block editor notices (`core/notices` store)

```javascript
// JS-side notice dispatch in block editor context
import { dispatch } from '@wordpress/data';

// Generic notice
dispatch( 'core/notices' ).createNotice(
    'success',                                 // status: success|warning|info|error
    'Post updated.',                          // content
    {
        id: 'my-notice',                      // unique notice ID
        isDismissible: true,                  // user can dismiss
        type: 'snackbar',                     // snackbar (transient) | default (persistent)
        actions: [
            {
                label: 'Undo',
                onClick: () => { /* ... */ },
                url: undefined,
            },
        ],
        speak: true,                          // announce via screen reader
        explicitDismiss: false,               // require explicit user dismissal
    }
);

// Convenience methods (status-typed)
dispatch( 'core/notices' ).createSuccessNotice( 'Post saved.', { /* options */ } );
dispatch( 'core/notices' ).createInfoNotice( '...', {} );
dispatch( 'core/notices' ).createWarningNotice( '...', {} );
dispatch( 'core/notices' ).createErrorNotice( '...', {} );

// Dismiss by ID
dispatch( 'core/notices' ).removeNotice( 'my-notice' );

// Read current notices
const notices = select( 'core/notices' ).getNotices();
```

| function | role |
|---|---|
| `createNotice` | dispatch notice into editor notice store |
| `createSuccessNotice` etc. | status-typed convenience wrappers |
| `removeNotice` | remove notice by ID |
| `getNotices` | read current notices array |

Block editor notices use **Redux-store-mediated dispatch**
— different mediation mechanism from PHP `admin_notices`
(hook-mediated). Two parallel signaling pipelines:
PHP-rendered (admin_notices hook) + JS-rendered (core/notices
store).

### F. Conditional gating dimensions (multi-axis mediation)

Notices employ **multi-axis conditional gating**:

```php
add_action( 'admin_notices', function () {
    // Axis 1: Capability gating
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Axis 2: Screen context gating
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'settings_page_my-plugin' ) {
        return;
    }
    
    // Axis 3: User-specific dismissal state
    $dismissed = get_user_meta( get_current_user_id(), 'my_notice_dismissed', true );
    if ( $dismissed ) {
        return;
    }
    
    // Axis 4: Conditional state (e.g., transient flag)
    if ( ! get_transient( 'my_plugin_show_notice' ) ) {
        return;
    }
    
    // Axis 5: Persistence/dismissal across sessions
    // (handled via user meta or option storage)
    
    echo '<div class="notice notice-warning"><p>'
        . esc_html__( 'Action required.', 'my-plugin' )
        . '</p></div>';
} );
```

| gating axis | mechanism | mediation form |
|---|---|---|
| Capability | `current_user_can()` | capability-gated mediation |
| Screen context | `get_current_screen()` | context-gated mediation |
| User dismissal state | `get_user_meta()` | per-user state mediation |
| Conditional flag | transient / option | runtime state mediation |
| Hook context | `admin_notices` vs `network_admin_notices` etc. | scope-gated mediation |
| Notice persistence | transient vs option vs user meta | persistence-tier mediation |

**5+ gating axes operate per notice** — notices are the
**most multi-axis gated mediation surface in admin-ui
bounded context.**

### G. Persistent dismissal pattern

```php
// Notice with AJAX-dismissal tracking
add_action( 'admin_notices', function () {
    if ( get_user_meta( get_current_user_id(), 'my_notice_dismissed', true ) ) {
        return;
    }
    
    ?>
    <div class="notice notice-warning is-dismissible" data-notice-id="my-notice">
        <p><?php esc_html_e( 'Important notice.', 'my-plugin' ); ?></p>
    </div>
    <script>
    jQuery( '[data-notice-id="my-notice"]' ).on( 'click', '.notice-dismiss', function () {
        jQuery.post( ajaxurl, {
            action: 'my_dismiss_notice',
            nonce: '<?php echo esc_js( wp_create_nonce( 'my_dismiss_notice' ) ); ?>',
        } );
    } );
    </script>
    <?php
} );

add_action( 'wp_ajax_my_dismiss_notice', function () {
    check_ajax_referer( 'my_dismiss_notice', 'nonce' );
    update_user_meta( get_current_user_id(), 'my_notice_dismissed', true );
    wp_send_json_success();
} );
```

Persistent dismissal pattern crosses **3 governance
boundaries**:
1. PHP-side notice rendering
2. JS-side dismissal event capture
3. AJAX/REST persistence layer (user meta storage)

This is **bridge mediation pattern**: signaling state
crosses runtime + persistence boundaries.

### H. Failure surfaces — attention debt

```
Notice failure modes:

- Notice without capability check → wrong-role users see
  irrelevant warnings → noise
- Notice without screen check → notice appears on every
  admin page → context pollution
- Hardcoded notice without translation → non-English users
  see English text
- Missing escaping in notice message → XSS surface
- Persistent notice without dismissal mechanism → permanent
  noise / nag-screen behavior
- Dismissal without persistence → re-appears every page
  load → user frustration
- High-priority notice queued behind low-priority → critical
  signal buried
- Notice for non-issue → cry-wolf desensitization
- Inline JS in notice → CSP violations
- Notice rendered in wrong hook context → multisite scope
  errors
- Settings_errors() never called → validation errors
  silently lost
- core/notices dispatch without ID → duplicate notices
  accumulate
- Snackbar timing too short for screen reader → a11y
  failure
- Notice action without nonce → CSRF surface
- Notice that requires action but no clear next-step → UX
  dead-end
```

**Attention debt** (12th debt-pattern instance in KB; first
**cognitive surface debt**):

| debt mode | symptom |
|---|---|
| context pollution | notice on irrelevant screens |
| capability bypass | wrong-role users see notice |
| nag-screen pattern | persistent without dismissal |
| dismissal not persisted | re-appears every load |
| priority inversion | critical buried by trivial |
| cry-wolf | non-issues become noise |
| translation gap | hardcoded text |
| escape gap | XSS in notice message |
| dispatch duplication | core/notices without unique ID |
| queue starvation | settings_errors never called |
| dead-end signaling | notice with no actionable next-step |

12 instances × 7 bounded contexts. Governance debt
meta-pattern continues; now spans **cognitive surface
governance** dimension (administrative attention as
governance resource).

## REQUIRES

- WP environment (admin notice hooks are WP core, available
  always since WP 2.5+).
- WP 6.4+ recommended for `wp_admin_notice()` /
  `wp_get_admin_notice()` standardized rendering.
- Hook handler MUST execute capability check + screen check
  (notices have no built-in gating).
- Notice messages MUST be escaped (`esc_html__`,
  `esc_attr__`, etc.) — translator content is XSS surface.
- For persistent dismissal: AJAX/REST endpoint + user meta
  + nonce verification.
- For Settings API integration: `settings_errors()` must be
  called explicitly in settings page render.
- For block editor: `@wordpress/notices` package + `core/notices`
  store available in editor context.
- Multisite: choose hook (`admin_notices` vs
  `network_admin_notices`) per scope.
- ⚠ Specific behaviors: notice ordering / priority semantics,
  Settings_Errors transient lifecycle edge cases, snackbar
  vs default notice timing, core/notices store persistence
  across editor reloads, screen reader announcement behavior
  — verification-needed.

## INVARIANTS

### 1. Notice signaling IS conditional governance exposure (NOT mere display)

The constitutional capstone finding for admin-ui bounded
context:

> Admin notices are NOT "just display HTML messages." They
> are **administrative attention governance** — conditional
> exposure of state-bearing signals to specific user
> populations under multi-axis gating discipline (capability +
> screen + persistence + scope + priority).

Reading notices as "just display" misses the **governance
dimension**. Notices are mediation surface; visibility is
the mediated authority resource.

> **Authority resource being mediated**: administrative
> attention. Notices declare intent to claim attention;
> gating decides whether claim is exposed.

### 2. Authority Mediation Surface — STRENGTHENED to 4-context cross-context PRESENCE

> **MAJOR Phase 8.x candidate evidence — Mediation
> approaches Recurring (cross-context) promotion threshold.**

Pre-this-chunk Authority Mediation Surface status:
- Recurring (intra-context, admin-ui — 2 chunks: settings-api +
  admin-menus)
- Cross-context PRESENCE (editor-customization, i18n)

Post-this-chunk Authority Mediation Surface status:
- Recurring (intra-context, admin-ui — **3 chunks** with notices)
- Cross-context PRESENCE (editor-customization, i18n)
- **NEW mediation form**: cognitive-surface-gated mediation

Mediation taxonomy now spans **4 distinct gating mechanisms**:

| mediation form | bounded context | gating mechanism |
|---|---|---|
| Capability-gated | admin-ui (settings-api) | user capability |
| Routing-gated | admin-ui (admin-menus) | navigation topology |
| Authority subscription | editor-customization (editor-hooks) | direct subscribe/dispatch |
| Context-gated | i18n (locale-switching) | runtime context reassignment |
| **Cognitive-surface-gated (NEW)** | **admin-ui (notices)** | **multi-axis attention gating** |

Mediation candidate **gains 5th distinct gating mechanism**.
admin-ui itself now demonstrates **3 distinct mediation forms
intra-context** (capability + routing + cognitive-surface).
Promotion path to **Recurring (cross-context)** substantively
advanced.

### 3. admin-ui = first tri-modal governance bounded context

This chunk completes admin-ui's **3rd governance modality**:

```
admin-ui bounded context governance modalities:

   settings-api:   PERSISTENCE governance
                   (option storage + capability-gated mutation)
   
   admin-menus:    ROUTING governance
                   (capability-gated navigation topology)
   
   notices (this): SIGNALING governance
                   (multi-axis conditional attention exposure)
```

> **admin-ui = first KB tri-modal governance bounded context.**

Symmetric to editor-customization's tri-modal authority
character:

| bounded context | modality 1 | modality 2 | modality 3 |
|---|---|---|---|
| editor-customization | LIFECYCLE (block-filters) | TOPOLOGY (slotfills) | REACTIVE (editor-hooks) |
| **admin-ui** | **PERSISTENCE (settings-api)** | **ROUTING (admin-menus)** | **SIGNALING (notices)** |

> **NEW KB-LEVEL OBSERVATION**: Tri-modal governance
> bounded contexts may be a recurring KB-Wide pattern.

This is **bounded-context-level pattern observation**
(same class as i18n's Continuity-Governance pairing
observation). Status: **Surfaced only**. Cross-context
verification (other tri-modal candidates: site-building?)
required for promotion.

### 4. Declaration ≠ Exposure (Law 1) at strongest manifestation in KB

> **Law 1 reaches its STRONGEST manifestation in this chunk.**

Notice lifecycle exhibits **5-form declaration-exposure gap**:

| stage | character |
|---|---|
| Notice DECLARED (hook handler attached) | declaration |
| Notice GENERATED (handler invoked) | partial exposure (HTML produced) |
| Notice GATED (capability + screen + state checks) | mediation |
| Notice RENDERED (HTML emitted to admin page) | exposure |
| Notice ACKNOWLEDGED (user sees + dismisses) | reception |

**5 distinct stages, each with independent failure mode.**
Notice may be declared but never generated (hook never fires);
generated but gated out (capability fails); rendered but
not acknowledged (user misses); acknowledged but not
persisted (re-appears).

> **Law 1 manifests as 5-form declaration-exposure gap in
> notices**, the strongest Law 1 manifestation in KB to date.

This refines Law 1 understanding:

> **Declaration-exposure gap may have 2-form (basic), 3-form
> (settings/script-translations), 4-form (locale-switching),
> or 5-form (notices) manifestations.** Form count correlates
> with mediation axis count.

### 5. Authority Interception Surface manifests in admin_notices hook chain (cross-context PRESENCE — NEW for admin-ui)

> **Q9-significant finding: Interception expands beyond
> editor-customization.**

The `admin_notices` hook is a **lifecycle interception
surface** — multiple plugins/themes hook the same action;
each can:
- Add new notices (additive)
- Read DOM context (no direct interception)
- Influence ordering via `add_action` priority

This is **NOT** strict interception (each handler runs
independently; no chain mutation). But priority ordering
+ shared output stream is **signaling-stream interception
character**.

| candidate | admin-ui mediation form | interception character |
|---|---|---|
| Authority Interception Surface | partial — priority-ordered shared signaling stream | weak interception |

Status: **Cross-context PRESENCE for Authority Interception
Surface (NEW)** — admin-ui demonstrates weak interception
character. Pre-this-chunk Interception was Recurring
(intra-context, editor-customization). Post-this-chunk,
admin-ui adds 2nd-context PRESENCE.

> **Status update**: Authority Interception Surface = Recurring
> (intra-context, editor-customization) + cross-context
> PRESENCE (admin-ui weak interception form).

### 6. Doctrine 5 Hybridized variant — 7th instance (admin-ui internal Hybridized density)

Notice signaling is Doctrine 5 Hybridized:

| stage | location | character |
|---|---|---|
| Notice generation pipeline (hook chain + Settings_Errors queue + core/notices store) | Multiple distinct mechanisms | **Distributed** |
| Notice rendering (per-handler echo OR settings_errors() OR JS createNotice) | Per-mechanism single-call | **Integrated** |

**7th Hybridized variant in KB** (after block patterns +
variations + transforms + gettext-functions + script-translations
+ locale-switching). Hybridized variant continues robust
recurrence.

| Hybridized instance | bounded context |
|---|---|
| Block patterns | site-building |
| Variations | block-authoring |
| Transforms | block-authoring |
| Gettext functions | i18n |
| Script translations | i18n |
| Locale switching | i18n |
| **Notices (this chunk)** | **admin-ui** |

7 instances × 4 bounded contexts. **admin-ui's 1st
Hybridized variant**.

### 7. Arbitration Compiler (Law 4) — priority-ordered queue manifestation

Notices exhibit **priority-ordered queue arbitration**:

```
Notice queue arbitration:

Multiple plugins hook admin_notices:
   Priority 5:  Plugin A notice
   Priority 10: Plugin B notice (default)
   Priority 10: Plugin C notice (default, registered later)
   Priority 20: Plugin D notice
   ↓
WP fires admin_notices action; handlers invoked in priority order
   ↓
Output stream sequence:
   Plugin A (5) → Plugin B (10) → Plugin C (10, FIFO at same priority) → Plugin D (20)
   ↓
Settings_Errors API: separate queue with own ordering
   ↓
core/notices store: Redux-managed array order

3 parallel arbitration mechanisms:
- Hook priority (PHP)
- Settings_Errors transient queue (PHP, deferred)
- core/notices store array (JS, dispatch order)
```

> **Law 4 manifests as TRIPLE-PIPELINE arbitration in
> admin notices**: hook priority + Settings_Errors queue +
> core/notices store. 3 parallel arbitration mechanisms
> coexist.

This is **NEW Law 4 manifestation form**: parallel-pipeline
arbitration. Different from prior Law 4 manifestations
(template hierarchy single-pipeline cascade).

### 8. Bridge Pattern recurrence — 3rd observation (cognitive surface bridge)

Persistent dismissal pattern reveals **3rd Bridge Pattern
observation in KB**:

```
Persistent dismissal bridge:

PHP (notice rendered)
   ↓ inline JS dispatched
HTML/JS bridge (DOM event listener)
   ↓ AJAX request
JS → PHP bridge (admin-ajax handler)
   ↓ user meta update
PHP persistence (user meta storage)
   ↓ subsequent page load reads meta
PHP (notice gated by meta state)
```

Bridge Pattern observations now:
1. `wp_set_script_translations` (script-translations) —
   PHP-initiated, HTML-mediated, JS-consumed
2. `i18n.locale-switching` (cross-runtime gap refinement) —
   asymmetric coverage observation
3. **`admin-ui.notices` persistent dismissal (this chunk)**
   — bidirectional PHP↔JS bridge with persistence

> **Bridge Pattern reaches 3-instance threshold across 2
> bounded contexts (i18n + admin-ui).**

Per Phase 7.6+7.7 discipline, 3-instance recurrence is
**Local promotion threshold**. Bridge Pattern status
changes:

> **Bridge Pattern status update**: Surfaced → **Local**
> (3 instances within KB; 2 bounded contexts).

This is **4th PROMOTION EVENT in KB** (after slotfills
Authority Interception, admin-menus Mediation, capabilities
Resolution Distributed retro).

### 9. Administrative Routing Surface — NOT manifest in notices (Divergent)

Per Phase 7.7 sub-pattern discipline test:

Notices have **no navigation topology** — they appear
in-place (admin context) without hierarchical navigation
structure.

| candidate | notice manifestation | outcome |
|---|---|---|
| Administrative Routing Surface | NOT present (notices are in-place signaling, not navigation) | **Divergent** |

This confirms Routing Surface is **navigation-topology-
specific**, not generic admin-ui character. Routing remains
**Surfaced** (admin-menus only); not strengthened by this
chunk. Healthy divergence — refusing premature inflation.

### 10. NEW observation — Administrative Signaling Surface (surfaced only)

This chunk surfaces a **potential new candidate**:
**Administrative Signaling Surface**.

Character:
- Conditional state-bearing exposure to user attention
- Multi-axis gating (capability + screen + persistence +
  scope + priority)
- Distinct from Mediation parent (signaling is Mediation
  sub-form? or independent class?)
- Distinct from Routing (in-place, not navigational)
- Distinct from Interception (additive, not chain-mutating)

Q-question: Is Administrative Signaling Surface:
- (a) Sub-form of Authority Mediation Surface (cognitive-
  surface-gated mediation form)?
- (b) Independent candidate (4th candidate beside
  Interception/Mediation/Routing)?

> **Honest evaluation per Phase 7.5 Doctrine 3 Epistemic
> Integrity**: Single instance (admin-ui only) is INSUFFICIENT
> evidence to determine sub-form vs independent candidate.

Status: **SURFACED ONLY — observation, NOT promoted.**
Per Phase 7.6+7.7 "surface, do not constitutionalize"
discipline.

Cross-context verification candidates:
- Block editor `core/notices` snackbar UI (already in this
  chunk; same bounded context character)
- Site frontend signaling (admin bar messaging? customizer
  notices?)
- Multisite super-admin signaling distinctness
- Plugin update signaling (shipped plugin notice mechanism?)

### 11. KB Constitution v1 — admin-ui tri-modal validation: PASSED

> **3rd KB-level validation in admin-ui bounded context.**

Pre-this-chunk admin-ui Constitution v1 validations:
- settings-api: persistence governance (Mediation 1st intra-
  context)
- admin-menus: routing governance (Mediation 2nd intra-context;
  Routing NEW candidate)

This chunk's validation:
- notices: signaling governance (Mediation 3rd intra-context;
  Signaling NEW observation; tri-modal completeness)

Post-this-chunk evidence:
- Law 1 (Declaration ≠ Exposure): VERY STRONG (5-form gap;
  strongest manifestation in KB)
- Law 4 (Arbitration Compiler): STRONG (triple-pipeline
  arbitration NEW form)
- Doctrine 5 (Hybridized variant): Confirmed (7th instance;
  1st intra-admin-ui Hybridized)
- Authority Mediation Surface: STRENGTHENED (4-context
  cross-context PRESENCE; 5 distinct gating mechanisms;
  admin-ui 3-form intra-context density)
- Authority Interception Surface: STRENGTHENED (cross-context
  PRESENCE in admin-ui; weak interception form)
- Bridge Pattern: PROMOTED (3 instances → Local status; 4th
  KB promotion event)
- Administrative Signaling Surface: NEW observation (surfaced
  only)

> **Verdict: KB Constitution v1 admin-ui tri-modal validation**
> **PASSED.** admin-ui demonstrates Constitution v1's
> applicability across 3 distinct governance modalities.

### 12. admin-ui bounded context — CLOSURE READY

This chunk completes admin-ui bounded context constitutional
density:

| chunk | modality | constitutional contribution |
|---|---|---|
| settings-api | persistence | Cross-context Mediation test; Phase 7.5 reference exemplar |
| admin-menus | routing | 2nd PROMOTION EVENT (Mediation Local→Recurring intra); Routing NEW candidate |
| **notices** | **signaling** | **Mediation 4-context cross-context PRESENCE; Bridge Pattern PROMOTED to Local; Signaling NEW observation; tri-modal completeness** |

Bounded context closure criteria:
- ✅ Multi-chunk density (3 chunks)
- ✅ Tri-modal governance coverage (persistence + routing +
  signaling)
- ✅ Cross-context candidate manifestations (Mediation 4-context
  PRESENCE)
- ✅ Constitutional law manifestation (Laws 1, 4, 5; Doctrine 5)
- ✅ NEW candidate observations (Routing surfaced; Signaling
  surfaced)
- ✅ Q9 retro candidates identified
- ✅ Q10 disciplined negative findings (consistent sub-pattern
  governance)
- ✅ Bridge Pattern PROMOTION via this chunk

> **admin-ui bounded context status: CLOSURE READY.**
> Future admin-ui chunks (admin bar, dashboard widgets,
> contextual help) would be optional enrichment, not
> closure-blocking.

## VERIFICATION NEEDED

`status: stable`. Items requiring verification:

- Notice priority ordering edge cases (same-priority FIFO
  guarantees).
- Settings_Errors transient lifecycle (timeout, multisite
  scope, redirect boundary semantics).
- `wp_admin_notice` (WP 6.4+) backward compatibility for
  raw HTML notices in older plugins.
- core/notices store persistence across editor reloads.
- Snackbar timing semantics + a11y screen reader
  announcement.
- Notice visibility under Gutenberg full-site editing
  context.
- Multisite super-admin notice scope / network_admin_notices
  vs admin_notices precedence.
- Block editor notice deduplication (same ID dispatched
  twice).
- Notice action button rendering in PHP-side notices (custom
  HTML vs standardized form).
- AJAX dismissal + caching layer interaction.
- `is-dismissible` + JS-disabled fallback behavior.
- Customizer-context notices (separate hook surface?).
- WP_Error → admin_notice rendering convention.
- WP-CLI notice output (does CLI honor admin_notices?).

For practical decisions: empirical testing per WP version +
multisite config + plugin ecosystem.

## ANTIPATTERNS

- ❌ **Notice without capability check**. `add_action(
  'admin_notices', $cb )` shows notice to ALL admin users
  regardless of role. Always gate with
  `current_user_can()`.
- ❌ **Notice without screen check**. Notice appears on
  every admin page → context pollution. Use
  `get_current_screen()` to scope.
- ❌ **Hardcoded notice text**. Wrap in `__()` /
  `esc_html__()` for translation + escaping.
- ❌ **Persistent notice without dismissal**. Nag-screen
  pattern; users develop notice blindness. Always provide
  dismissal mechanism for persistent notices.
- ❌ **Dismissal without persistence**. Notice re-appears
  every page load → user frustration. Persist dismissal
  state (user meta, transient, option).
- ❌ **Inline JS in notice without nonce**. CSRF surface;
  always nonce AJAX dismissal endpoints.
- ❌ **Critical notice at low priority**. High-priority
  signal buried by trivial notices. Use priority parameter
  to elevate critical notices.
- ❌ **Cry-wolf signaling**. Showing notices for non-issues
  → desensitization. Reserve notices for actionable state.
- ❌ **Settings_errors() never called**. Validation errors
  enqueued but never rendered. Always call `settings_errors()`
  in settings page header.
- ❌ **core/notices dispatch without ID**. Duplicate
  notices accumulate on repeated action. Always provide
  unique `id` parameter.
- ❌ **Wrong hook context in multisite**. Using
  `admin_notices` for network-wide signal = misses network
  admin context. Use `network_admin_notices` for
  super-admin signals.
- ❌ **HTML in `wp_admin_notice` $message without escape**.
  Argument is treated as HTML; must escape user-derived
  content.
- ❌ **Snackbar for critical errors**. Snackbar auto-dismisses
  in 4s; critical errors need persistent notice.
- ❌ **Notice with action but no clear next-step**. Dead-end
  signaling; always pair notice with clear actionable
  guidance.
- ❌ **Mixing PHP admin_notices with block editor core/notices
  duplicately**. Same condition triggering both → user sees
  duplicate. Choose one channel per condition.

## RELATED

- `admin-ui.settings-api` — Settings_Errors notice subsystem
  (settings page validation feedback).
- `admin-ui.admin-menus` — screen context (`get_current_screen`)
  used for notice gating; routing-signaling parallel.
- `editor-customization.slotfills` — `core/notices` block
  editor surface; SlotFill rendering parallel for notices.
- `plugin-dev.capabilities-and-roles` — capability gating
  (`current_user_can`) for notice visibility.
- `i18n.gettext-functions` — notice text translation
  (`esc_html__` / `esc_attr__`).
- `data-layer.persistence` — user meta persistence for
  dismissal state.
- `_meta.structural-patterns` — Phase 7.5/7.6/7.7 patched
  spec applied; Bridge Pattern PROMOTION; tri-modal
  governance bounded context observation.
- `_meta.kb-consolidation-phase7-8` — Constitution v1
  reference (this chunk validates tri-modal completeness).

## META

**admin-ui bounded context — 3rd chunk; tri-modal governance
completeness + Authority Mediation Surface 4-context cross-
context PRESENCE + Bridge Pattern PROMOTION (4th KB
PROMOTION EVENT).**

### Phase 7.5/7.6/7.7 patched framework deployment

Per established post-Phase-7.5+ chunk pattern:

1. ✅ **Patched verdict taxonomy deployed** (5-class).
2. ✅ **Patched maturity ladder applied** (5-tier).
3. ✅ **Q8 adjudication doctrine operationalized**.
4. ✅ **Doctrine 5 (Arbitration ↔ Resolution Paired
   Operations) directly applied** — verdict: Hybridized
   architecture (7th Hybridized in KB; 1st intra-admin-ui).
5. ✅ **Q9 retroactive verification trigger applied**.
6. ✅ **Q10 sub-pattern emergence diagnostic applied** —
   verdict: Administrative Signaling Surface NEW observation
   (surfaced only; sub-form vs independent candidate
   undetermined).

### Doctrinal capstone established

> **admin-ui is tri-modal governance bounded context:**
> **persistence (settings-api) + routing (admin-menus) +**
> **signaling (notices). Each modality is mediation under**
> **distinct gating mechanism. Together they constitute**
> **complete administrative authority surface area.**

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 1 — Declaration ≠ Exposure** | VERY STRONG | 5-form declaration-exposure gap (declared / generated / gated / rendered / acknowledged) | **Confirmed (STRONGEST manifestation in KB; 5-form gap)** |
| **Law 4 — Arbitration Compiler** | STRONG | Triple-pipeline arbitration (hook priority + Settings_Errors queue + core/notices store) | **Confirmed (NEW Law 4 form: parallel-pipeline arbitration)** |
| **Law 3 — Authority Continuity** | Moderate | Notice persistence across redirect (Settings_Errors transient); user meta dismissal continuity | **Confirmed (continuity through transient + user meta)** |
| **Doctrine 5 — Hybridized variant** | Strong | Distributed pipeline (hook chain + queue + store) + Integrated rendering (per-handler) | **Confirmed (7th Hybridized in KB; 1st intra-admin-ui)** |
| **Authority Mediation Surface (candidate)** | STRONG | Multi-axis conditional gating (capability + screen + persistence + scope + priority); cognitive-surface-gated mediation NEW form | **STRENGTHENED (4-context cross-context PRESENCE; 5 distinct gating mechanisms; admin-ui 3-form intra-context density; promotion path substantively advanced)** |
| **Authority Interception Surface (candidate)** | Moderate | admin_notices hook chain priority-ordered shared signaling stream | **Cross-context PRESENCE NEW (admin-ui weak interception form)** |
| **Routing Surface (candidate)** | Weak-Moderate | NOT present (notices in-place, not navigational) | **Divergent (Routing remains navigation-topology-specific)** |
| **Bridge Pattern (observation)** | Moderate | Persistent dismissal: PHP notice → JS event → AJAX → PHP persistence (bidirectional bridge) | **PROMOTED Surfaced → Local (3 instances threshold reached; 4th KB PROMOTION EVENT)** |
| **Administrative Signaling Surface (NEW observation)** | (test) | Cognitive-surface-gated conditional state exposure | **Surfaced only (single bounded-context observation; sub-form vs independent candidate undetermined)** |
| **Tri-modal governance bounded context (NEW observation)** | (test) | admin-ui = persistence + routing + signaling (3 governance modalities); symmetric to editor-customization tri-modal authority | **Surfaced only (bounded-context-level pattern; cross-context verification candidates: site-building?)** |

**Universal law manifestation: SUCCESS — major validations:**
- **Law 1** STRONGEST manifestation in KB (5-form gap)
- **Law 4** NEW form (parallel-pipeline arbitration)
- **Authority Mediation Surface** STRENGTHENED (4-context
  PRESENCE; 5 gating forms)
- **Bridge Pattern** PROMOTED Surfaced → Local (4th KB
  promotion event)
- **Administrative Signaling Surface** NEW observation
- **Tri-modal governance** NEW bounded-context-level
  observation

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | notices manifestation | Outcome |
|---|---|---|---|
| **Authority Mediation Surface** | Recurring (intra-context, admin-ui 2 chunks) + cross-context PRESENCE (editor-customization, i18n) | STRONG: 5-axis conditional gating; cognitive-surface-gated mediation NEW form; admin-ui 3-form intra-context density | **STRENGTHENED (4-context cross-context PRESENCE; 5 distinct gating mechanisms; promotion to Recurring (cross-context) substantively advanced)** |
| **Authority Interception Surface** | Recurring (intra-context, editor-customization) | Partial: admin_notices hook chain priority-ordered shared signaling stream (weak interception form) | **Cross-context PRESENCE NEW (admin-ui weak interception form; 2nd-context PRESENCE)** |
| **Administrative Routing Surface** | Surfaced (admin-ui only) | NOT present (notices in-place, not navigational) | **Divergent (Routing remains navigation-topology-specific; not strengthened)** |
| **Resolution Surface** | Recurring (cross-context); KB-Wide REFUSED (Phase 7.8) | Implicit: notice queue resolution (priority + screen + capability → display candidates → rendered notice) | **Confirmed implicitly (refusal verdict undisturbed)** |
| **Selection from Candidates (sub-pattern)** | Recurring (cross-context, sub-pattern of Doctrine 5 Hybridized) | DIVERGENT — notice queue resolution is non-user-facing arbitration | **Divergent (consistent with non-user-facing arbitration pattern)** |
| **Federation Pattern** | KB-Wide-equivalent (7-context recurrence) | Notice federation: multiple plugins/themes contribute notices to single admin signaling stream; per-domain independent | **Confirmed (notice federation per-handler independent; KB-Wide further reinforced)** |
| **Bridge Pattern (observation)** | Surfaced (script-translations + locale-switching refinement) | STRONG: persistent dismissal bridge (PHP → JS → AJAX → PHP persistence) bidirectional bridge with persistence | **PROMOTED Surfaced → Local (3 instances; 2 bounded contexts; 4th KB PROMOTION EVENT)** |
| **Administrative Signaling Surface (NEW observation)** | did not exist | Cognitive-surface-gated conditional state exposure (multi-axis gating) | **Surfaced only ("surface, do not constitutionalize"; sub-form vs independent undetermined)** |
| **Tri-modal governance bounded context (NEW observation)** | did not exist | admin-ui = persistence + routing + signaling completeness; symmetric to editor-customization | **Surfaced only (cross-context verification candidates required)** |

### Q9 Retroactive Verification Triggered

> **Q9 ANSWER: YES — this chunk reveals (a) Authority
> Interception Surface manifestations may exist latently in
> other hook-chain mechanisms beyond editor-customization,
> (b) Bridge Pattern PROMOTED to Local triggers retroactive
> verification of other PHP↔JS bridges, (c) tri-modal
> governance pattern may exist in other governance-heavy
> bounded contexts.**

**Q9 candidates triggered**:
1. **`plugin-dev` hook chains** — REST authentication chains,
   hook priority arbitration; potential weak interception
   form parallel
2. **`block-authoring.dynamic-rendering`** — render_callback
   chain + pre-render hooks; potential interception form
3. **`interactivity.directive-protocol`** — directive
   serialization PHP→JS bridge; Local Bridge Pattern test
4. **`block-authoring.registration`** (block.json registration
   family) — block.json PHP↔JS auto-bridge; Local Bridge
   Pattern test
5. **`style-engine.preset-materialization`** — preset PHP→
   CSS→JS bridge; Local Bridge Pattern test
6. **`site-building`** — does it exhibit tri-modal governance?
   (template + pattern + ?)
7. **(specific) admin bar, dashboard widgets, contextual help** —
   admin-ui depth chunks for further tri-modal density
   verification

These are Q9 trigger flags; future chunks will execute retros
if Bridge Pattern reaches Recurring (cross-context) or
Interception Surface reaches Recurring (cross-context).

### Q10 Sub-pattern Emergence (NEGATIVE finding documented; consistent with broader KB discipline)

> **Q10 ANSWER: NO new stable sub-pattern observed.**
> **Administrative Signaling Surface NEW observation —**
> **sub-form vs independent candidate UNDETERMINED.**

Initial hypothesis: Administrative Signaling Surface might
be sub-pattern of Authority Mediation Surface (cognitive-
surface-gated mediation form).

Honest evaluation per Phase 7.5 Doctrine 3 Epistemic
Integrity:

> Single-bounded-context manifestation is INSUFFICIENT
> evidence to determine whether Signaling is:
> (a) Sub-form of Mediation (cognitive-surface-gated mediation)
> (b) Independent candidate (4th candidate beside
>     Interception/Mediation/Routing)

Both classifications are structurally plausible. Distinguishing
them requires cross-context verification.

> **Refusing premature classification per Phase 7.5 Doctrine 3
> + Phase 7.7 Doctrine 5c discipline.** "Surface, do not
> constitutionalize" — observation status only.

This is **disciplined Q10 negative finding** —
methodologically important demonstration of refusing
premature classification.

### NEW KB-level findings

**1. Authority Mediation Surface — 4-context cross-context PRESENCE; 5 distinct gating forms**

| context | mediation form | gating mechanism |
|---|---|---|
| admin-ui (settings-api) | capability-gated | user capability check |
| admin-ui (admin-menus) | routing-gated | navigation topology |
| admin-ui (notices) | **cognitive-surface-gated NEW** | **multi-axis attention gating** |
| editor-customization (editor-hooks) | authority subscription | direct subscribe/dispatch |
| i18n (locale-switching) | context-gated | runtime context reassignment |

> **Mediation candidate substantively approaches Recurring
> (cross-context) promotion threshold.** 4-context cross-
> context PRESENCE + 5 distinct gating mechanisms + 3-form
> intra-context density (admin-ui).

**2. Authority Interception Surface — 2nd-context cross-context PRESENCE**

| context | interception form | mechanism |
|---|---|---|
| editor-customization | strong interception (block-filters lifecycle, slotfills topology, editor-hooks HOC) | direct chain mutation |
| **admin-ui (NEW)** | **weak interception (admin_notices priority-ordered shared signaling stream)** | **shared output stream** |

> **Interception cross-context PRESENCE expands.** Promotion
> path to Recurring (cross-context) advanced.

**3. Bridge Pattern PROMOTED Surfaced → Local (4th KB PROMOTION EVENT)**

| Bridge instance | bounded context |
|---|---|
| `wp_set_script_translations` (script-translations) | i18n |
| `i18n.locale-switching` (asymmetric coverage refinement) | i18n |
| **`admin-ui.notices` persistent dismissal bridge** | **admin-ui** |

3 instances × 2 bounded contexts. Bridge Pattern reaches
**Local promotion threshold**.

> **Bridge Pattern status update**: Surfaced → **Local**.
> 4th KB PROMOTION EVENT.

Promotion path:
- Local → Recurring (intra-context): not applicable
  (cross-context already)
- Local → Recurring (cross-context): requires sustained
  pattern across 3+ bounded contexts
- Q9 retro candidates ALREADY identified for Recurring
  promotion verification

**4. Administrative Signaling Surface (NEW observation, surfaced only)**

Character: cognitive-surface-gated conditional state
exposure with multi-axis gating (capability + screen +
persistence + scope + priority).

Status: **SURFACED ONLY.** Sub-form vs independent
candidate undetermined. Cross-context verification candidates:
- Block editor `core/notices` snackbar (same bounded context)
- Site frontend admin bar messaging
- Customizer notices
- Plugin update signaling
- Multisite super-admin signaling

**5. Tri-modal governance bounded context (NEW observation)**

| bounded context | modality 1 | modality 2 | modality 3 |
|---|---|---|---|
| editor-customization | LIFECYCLE | TOPOLOGY | REACTIVE |
| **admin-ui** | **PERSISTENCE** | **ROUTING** | **SIGNALING** |

Symmetric tri-modal governance character across 2 bounded
contexts. Status: **SURFACED ONLY.** Cross-context
verification candidates: site-building (template + pattern +
?), plugin-dev (registration + capabilities + ?), data-layer
(entity + persistence + ?).

**6. Law 1 (Declaration ≠ Exposure) — STRONGEST manifestation in KB (5-form gap)**

Law 1 manifests with progressively richer forms across KB:
- 2-form (basic): declaration + exposure
- 3-form (gettext, settings): declaration + intermediate + exposure
- 4-form (locale-switching): switched + reloaded + dispatched + rendered
- **5-form (notices)**: declared + generated + gated +
  rendered + acknowledged

> **Law 1 form count correlates with mediation axis count.**
> Notices (5-axis gating) exhibit 5-form gap; this is the
> strongest Law 1 manifestation in KB to date.

### Constitutional capstone test rationale (META framing)

This chunk's strategic role per user direction:

> **admin-ui: tri-modal governance test + Mediation
> promotion leverage**

**Verdict: SUCCESS.** admin-ui bounded context CLOSURE READY.
Authority Mediation Surface 4-context PRESENCE + 5 gating
forms. Bridge Pattern PROMOTED to Local (4th KB promotion
event). Administrative Signaling Surface NEW observation.
Tri-modal governance NEW bounded-context-level observation.

### KB-wide pattern recurrence updates

**Attention debt = 12th debt-pattern instance:**

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
| **notices** | **attention debt** | **admin-ui** |

12 instances × 7 bounded contexts. Governance debt
meta-pattern continues; now spans **cognitive surface
governance** (administrative attention as governance
resource — first cognitive-dimension debt).

**Authority Mediation Surface (4-context cross-context PRESENCE; 5 distinct gating forms)**:
admin-ui (3-form intra-context: capability + routing +
cognitive-surface) + editor-customization + i18n.

**Authority Interception Surface (cross-context PRESENCE NEW)**:
editor-customization (Recurring intra-context, strong
interception) + admin-ui (weak interception form).

**Bridge Pattern (PROMOTED Surfaced → Local; 4th KB promotion event)**:
i18n (script-translations + locale-switching refinement) +
admin-ui (notices persistent dismissal). 3 instances × 2
contexts.

**Doctrine 5 Hybridized (7 instances × 4 contexts)**:
block patterns + variations + transforms + gettext-functions +
script-translations + locale-switching + **notices**. **1st
intra-admin-ui Hybridized.**

### KB self-evaluation against spec criteria (Phase 7.5/7.6/7.7 patched)

- ✅ Accuracy — describes documented admin_notices +
  wp_admin_notice + Settings_Errors + core/notices APIs.
- ✅ Structural fit — 3rd admin-ui chunk; tri-modal governance
  completeness + Mediation promotion leverage.
- ✅ Reusability — uses authority ontology glossary + Phase
  7.5/7.6/7.7 vocabulary (mediation / cognitive-surface-gated /
  attention debt / signaling / tri-modal).
- ✅ Phase fit — strategic admin-ui depth completion role.
- ✅ Doctrine respect — declaration ≠ exposure 5-form (strongest
  in KB); Epistemic Integrity preserved (Q10 negative finding
  refused premature Signaling classification; tri-modal
  surfaced not promoted).
- ✅ **Q8 explicit answer**: Mediation STRENGTHENED (4-context
  PRESENCE; 5 gating forms); Interception cross-context
  PRESENCE NEW (admin-ui weak form); Routing Divergent
  (navigation-topology-specific); Signaling Surface NEW
  observation (Surfaced); tri-modal governance NEW
  observation; Bridge Pattern PROMOTED to Local (4th KB
  PROMOTION EVENT).
- ✅ **Q9 explicit answer**: YES — plugin-dev hook chains +
  dynamic-rendering + interactivity directive-protocol +
  block.json registration + preset-materialization + tri-
  modal site-building/plugin-dev/data-layer verification
  candidates.
- ✅ **Q10 explicit answer**: NO new sub-pattern;
  Administrative Signaling Surface sub-form vs independent
  candidate UNDETERMINED (single-context evidence
  insufficient); refused premature classification.

### Status: `stable`

Admin notices APIs mature since WP 2.5+ (`admin_notices`
hook); Settings_Errors API mature since WP 3.0; `wp_admin_notice`
WP 6.4+; core/notices store mature since WP 5.0. Verification-
needed catalog covers behaviors but core APIs are settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission

> **Does admin-ui govern only persistence + routing, or**
> **also govern administrative attention itself?**

**Verdict: BOTH + administrative attention.** admin-ui is
**tri-modal governance bounded context**: persistence +
routing + signaling. Authority Mediation Surface gains
4-context cross-context PRESENCE with NEW cognitive-surface-
gated form. Bridge Pattern PROMOTED to Local. Administrative
Signaling Surface NEW observation. Tri-modal governance NEW
bounded-context-level observation.

### admin-ui bounded context — CLOSURE STATUS

| chunk | modality | constitutional contribution |
|---|---|---|
| settings-api | persistence | Cross-context Mediation test; Phase 7.5 reference exemplar |
| admin-menus | routing | 2nd PROMOTION EVENT (Mediation Local→Recurring intra); Routing NEW candidate |
| **notices** | **signaling** | **Mediation 4-context cross-context PRESENCE; Bridge Pattern PROMOTED Surfaced→Local (4th KB PROMOTION); Signaling NEW observation; tri-modal completeness; Law 1 strongest (5-form gap)** |

**admin-ui bounded context status: CLOSURE READY.**
Recommended for declaration as **substantively closed** in
next consolidation document update.

### Anticipated next chunks (priority)

1. **Mediation candidate audit / Phase 8.x promotion** —
   with 4-context PRESENCE + 5 gating mechanisms + 3-form
   intra-context density, Mediation has substantial promotion
   evidence. Audit chunk to determine Recurring (cross-
   context) promotion verdict.

2. **Q9 retro: `interactivity.directive-protocol`** —
   Bridge Pattern Local promotion verification; potential
   3rd-context Bridge instance for Recurring (cross-context)
   path.

3. **`plugin-dev.nonces`** — security trio completion +
   potential weak Interception manifestation in plugin-dev
   (CSRF chain).

4. **`data-layer.entity-resolution` Q9 retro** —
   `switch_to_blog` mediation parallel; potential Mediation
   semantic-domain density extension.

5. **`site-building` 3rd chunk** — tri-modal governance
   verification candidate for site-building (template +
   pattern + ?).

Recommended: **Mediation candidate audit / Phase 8.x
promotion verification chunk** — high-leverage moment;
Mediation evidence has accumulated to substantial threshold;
audit chunk parallels Phase 7.8 Resolution Surface audit
pattern (forward audit BEFORE more forward chunks).
