---
rule_id: admin-ui.settings-api
domain: admin-ui
topic: persistence-mediation
field_cluster: governance-surfaces
wp_min: "4.7"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/plugins/settings/settings-api/
    section: "Settings API — register_setting / add_settings_section / add_settings_field"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/register_setting/
    section: "register_setting() — WP 4.7+ schema-aware registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/plugins/settings/options-api/
    section: "Options API — get_option / update_option / delete_option"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/plugins/administration-menus/
    section: "Administration Menus — add_options_page + capability gating"
    captured: 2026-05-09
related:
  - editor-customization.editor-hooks              # paired test — Authority Mediation Surface cross-context test
  - plugin-dev.security-boundaries                 # security doctrine directly applied (sanitize/capability/nonce trio)
  - plugin-dev.capabilities-and-roles              # capability schema for admin operations
  - plugin-dev.register-meta                       # parallel persistence registration pattern
  - data-layer.persistence                         # Settings API IS persistence reconciliation at admin layer
  - _meta.structural-patterns                      # Phase 7.5 patched spec — first chunk authored under patched framework
---

# RULE — Settings API — capability-gated persistence mediation

## WHEN

A plugin or theme needs to:
- Register persistent options that admins can configure via
  WordPress admin pages.
- Render admin settings pages with proper form handling, nonce
  protection, and capability gating.
- Schema-validate option values via sanitize callbacks.
- Expose options through REST API (modern integration).
- Group related options into pages, sections, and fields.

This is the **first chunk in admin-ui bounded context** AND the
**first substantive chunk authored under Phase 7.5 patched
constitutional framework**. It is positioned as the
**cross-context test for Authority Mediation Surface
candidate** (surfaced in editor-hooks).

The doctrinal backbone for admin-ui (established here):

> **Editor hooks surfaced authority mediation inside reactive**
> **governance; Settings API tests whether mediation recurs**
> **across administrative governance through capability-gated**
> **persistence orchestration.**

The chunk's primary work: honest cross-context evaluation —
does Authority Mediation Surface manifest in admin-ui (a
different bounded context with different concerns), or does
it remain editor-customization-local?

> **This chunk should determine whether administrative**
> **persistence systems merely expose settings — or mediate**
> **authority through governed persistence constitutions.**

## SHAPE

### A. Registration substrate

```php
register_setting(
    'my_plugin_options',                    // option group (form pairing)
    'my_plugin_setting',                    // option name (DB key)
    array(
        'type'              => 'string',
        'description'       => __( 'My setting description', 'my-plugin' ),
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => true,
        'default'           => '',
    )
);
```

| component | role |
|---|---|
| `$option_group` (1st arg) | binds setting to a form/page group; used in settings_fields() for nonce + form posting |
| `$option_name` (2nd arg) | wp_options table key; the persistence ABI surface |
| `type` | schema type contract |
| `sanitize_callback` | input filtering at persistence boundary |
| `show_in_rest` | REST API exposure (declaration ≠ exposure pattern) |
| `default` | value when option is unset |

### B. Administrative mediation pipeline

```
User → admin page request (admin_init)
   ↓
Capability check (current_user_can)
   ↓
Page rendered with settings_fields() + do_settings_sections()
   ↓ (forms include nonce automatically)
User submits form to options.php
   ↓
WordPress core handles options.php submission:
   - Verifies nonce
   - Checks capability
   - Iterates registered settings for the option_group
   - Calls sanitize_callback for each value
   - update_option() persists sanitized value
   ↓
Redirect with settings_errors() messages
   ↓
get_option('my_plugin_setting') reads back the persisted value
```

This is **NOT** a single function call — it is a **multi-stage
mediation pipeline** where authority crosses multiple boundaries:
- User → form (UI mediation)
- Form → options.php (transport mediation)
- options.php → sanitize_callback (governance mediation)
- sanitize_callback → update_option (persistence mediation)
- update_option → wp_options table (storage)
- get_option → consumers (read mediation)

### C. Governance boundaries

```php
// Page registration (capability-gated)
add_options_page(
    __( 'My Plugin Settings', 'my-plugin' ),
    __( 'My Plugin', 'my-plugin' ),
    'manage_options',                        // capability gate
    'my-plugin',
    'my_plugin_render_page'
);

// In render callback
function my_plugin_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die();                            // explicit re-check
    }
    ?>
    <form method="post" action="options.php">
        <?php settings_fields( 'my_plugin_options' ); // nonce + group ?>
        <?php do_settings_sections( 'my-plugin' ); ?>
        <?php submit_button(); ?>
    </form>
    <?php
}
```

| boundary | mechanism |
|---|---|
| menu visibility | `add_options_page` capability arg |
| page access | `current_user_can` re-check at render |
| form authenticity | `settings_fields` (nonce auto-included) |
| input shape | `sanitize_callback` per setting |
| persistence access | core options.php capability check |
| REST exposure | `show_in_rest` per setting |

Multiple capability check points: menu visibility + render
re-check + core options.php check + REST permission. Each is
independently necessary; collectively they enforce admin
authority at every transit boundary.

### D. Relationship topology — pages / sections / fields

```php
// Section
add_settings_section(
    'my_plugin_section',
    __( 'Section Title', 'my-plugin' ),
    'my_plugin_section_description_cb',
    'my-plugin'                              // page slug
);

// Field within section
add_settings_field(
    'my_plugin_setting',                    // field id
    __( 'Setting Label', 'my-plugin' ),
    'my_plugin_field_render_cb',
    'my-plugin',                             // page slug
    'my_plugin_section'                      // section id
);
```

The Settings API exposes a **3-level topology**:
- **Pages** — admin pages (add_options_page / add_menu_page)
- **Sections** — grouping within page (add_settings_section)
- **Fields** — individual setting controls (add_settings_field)

This topology is governance-relevant: page = capability gate;
section = visual grouping; field = persistence binding. The
relationships between these levels form the admin UI graph.

### E. Failure surfaces — settings debt

```
Settings governance failure modes:

- overexposure: show_in_rest exposes sensitive option to REST consumers
- sanitize-only assumption: sanitize_callback present but capability check missing → unauthorized writes
- capability drift: site adds custom roles; manage_options no longer matches intent
- settings debt: many registered settings accumulate; options table bloats
- admin illusion: setting visible in admin = authoritative ≠ true
- nonce omission: custom forms bypass settings_fields() → CSRF surface
- premature show_in_rest: REST exposure with weak permission_callback
- option_group mismatch: registered group ≠ form group → save fails silently
- read-without-default: get_option returns false; consumers may misinterpret
- legacy add_option vs register_setting: pre-WP 4.7 patterns lack schema
```

**Settings debt** (5th debt-pattern instance in KB; first
outside editor/plugin-dev-heavy zones):

| debt mode | symptom |
|---|---|
| accumulation | many registered settings persist past their feature lifecycle |
| sanitize-only | governance-incomplete options (sanitize without auth) |
| exposure overshoot | show_in_rest enabled by default-thinking, exposing internal state |
| capability drift | custom roles / capability changes leave settings unreachable |
| naming bloat | option_name namespace pollution in wp_options table |

The 5-instance debt-pattern recurrence (security / interception /
topology / reactive / settings) further reinforces "governance
debt" as recurring meta-pattern across bounded contexts.

## REQUIRES

- WP 4.7+ for `register_setting` schema-aware form
  (older `register_setting` exists since WP 2.7 but without
  schema args).
- Registration on `admin_init` action (or earlier appropriate
  hook).
- For show_in_rest: REST infrastructure available (default).
- Capability checks must run at multiple boundaries
  (menu / page render / REST / options.php) — core handles
  options.php; plugin handles menu + render.
- `option_group` in register_setting MUST match the
  `settings_fields()` argument in the form.
- For arrays / objects in options: `sanitize_callback` must
  handle nested validation; default sanitization handles
  scalars only.
- ⚠ Specific behaviors: `register_setting` deferred
  evaluation, multisite network options vs site options,
  REST schema generation completeness, capability cache
  with custom roles, options.php submission behavior with
  malformed groups — verification-needed.

## INVARIANTS

### 1. Settings API mediates authority through governed persistence channels, NOT raw storage

The load-bearing reframing:

> Settings API is NOT "a way to store options for plugins."
> Settings API is **mediation through governed persistence
> channels** — values transit through capability gates +
> sanitization + nonce verification + REST exposure governance
> on their way to / from wp_options table.

Reading Settings API as "storage convenience" misses the
mediation ontology. Each registered setting is a **mediation
contract** (sanitize / auth / exposure) over an underlying
persistence slot.

### 2. Authority crosses multiple mediation boundaries per request

A single setting save traverses:

1. Form (UI mediation — render + nonce)
2. options.php (transport mediation — POST handling)
3. Capability check (governance mediation — current_user_can)
4. Sanitize callback (input shape mediation)
5. update_option (persistence mediation)
6. wp_options write (storage)
7. settings_errors (UX feedback mediation)

Each boundary is an independent mediation surface. Failure at
any boundary halts the chain. Skipping any boundary creates
governance gaps.

This is **mediation pipeline** — qualitatively different from
single-call APIs. Settings API IS the pipeline; consumers
participate by registering at the mediation surfaces.

### 3. register_setting is persistence ABI declaration; sanitize_callback governs ABI shape integrity

KB-recurring pattern (persistence ABI from register_meta):

| API | persistence ABI |
|---|---|
| register_meta | meta key + type + single + sanitize + auth + show_in_rest |
| **register_setting** | **option_name + type + sanitize + show_in_rest + default** |

Both are persistence ABI declarations. Settings API differs
from register_meta by:
- option_group (form pairing) — Settings API specific
- sanitize_callback (vs register_meta's auth_callback +
  sanitize_callback split)
- options.php submission protocol — UI-coupled

The shared ABI pattern reinforces persistence ABI as a
recurring KB structure (CSS variables / register_meta /
register_setting).

### 4. Capability gating at multiple boundaries (Law 1 — Declaration ≠ Exposure 4-form)

Settings API exhibits **4-form declaration ≠ exposure**:

| surface | controlled by |
|---|---|
| **registration** (setting exists) | register_setting() |
| **menu exposure** (menu item visible) | add_options_page capability arg |
| **page exposure** (page rendered) | current_user_can re-check at render |
| **REST exposure** (option in REST response) | show_in_rest argument |

A setting can be REGISTERED but no menu / no page / no REST
(internal-only). A setting can be REGISTERED + REST-exposed
but NOT in admin menu (headless / API-driven). Each surface
is independently governed.

This is the **most-multidimensional declaration ≠ exposure
manifestation** in admin-ui domain — exceeding even CPT's
exposure flag count.

### 5. Settings API IS persistence reconciliation at admin layer

```
Cross-bounded-context substrate sharing:

data-layer.persistence    → 6-stage reconciliation pipeline (entity edit → save)
   ↑
   |  same substrate
   ↓
admin-ui.settings-api    → multi-boundary mediation pipeline (form → save)
```

Settings API is data-layer.persistence's pattern manifesting
at the admin-ui layer. The substrate is shared (wp_options
is a persistence target like wp_posts is for entities). The
SURFACE differs (admin form vs editor entity).

This is **substrate sharing across bounded contexts** —
admin-ui builds on data-layer's persistence reconciliation
without re-implementing it.

### 6. Settings groups create relationship topology

Pages / sections / fields form an **administrative governance
topology**:

```
Page (capability-gated entry)
   ├─ Section A (visual grouping)
   │    ├─ Field 1 (binds to setting_1)
   │    └─ Field 2 (binds to setting_2)
   └─ Section B
        └─ Field 3 (binds to setting_3)
```

This topology is **relationship-centric** (Law 5 manifestation
in admin-ui):
- Pages relate to capabilities
- Sections relate to pages
- Fields relate to sections AND to options
- Options relate to forms (option_group binding)

Each relationship is a governance edge. Modifying one without
considering others breaks composition.

### 7. Settings debt is the 5th debt-pattern instance — "governance debt" pattern broadens beyond editor/plugin-dev-heavy zones

KB now has 5 documented debt instances across 3 bounded
contexts:

| chunk | debt name | bounded context |
|---|---|---|
| security-boundaries | security debt | plugin-dev |
| block-filters | interception debt | editor-customization |
| slotfills | topology debt | editor-customization |
| editor-hooks | reactive debt | editor-customization |
| **settings-api (this)** | **settings debt** | **admin-ui** |

5-instance recurrence across 3 bounded contexts strengthens
"governance debt" as **anticipated meta-pattern**. The
recurrence is broader than initially observed (no longer
editor/plugin-dev-only).

Surfaced for future audit consideration. NOT promoted to
candidate yet (per Doctrine 2: surface, do not constitutionalize
prematurely).

### 8. Authority Mediation Surface candidate manifests in admin-ui — cross-context PRESENCE confirmed

> **This invariant IS the cross-context test result for the
> Authority Mediation Surface candidate** surfaced in
> editor-hooks.

**Manifestation evidence in Settings API:**

| Settings API element | mediation character |
|---|---|
| sanitize_callback | input shape mediation through governed channel |
| capability checks (multi-boundary) | access mediation through capability constitution |
| options.php submission protocol | transport mediation between form and DB |
| settings_errors | feedback mediation |
| get_option / update_option | read/write mediation through Options API |

Settings API is **structurally mediation** — values transit
through governed channels rather than entities being created
or behavior being intercepted. This **confirms** Authority
Mediation Surface as a structural pattern, NOT an
editor-customization-local phenomenon.

**Promotion result (per Phase 7.5 patched ladder)**:

```
Authority Mediation Surface:
   editor-hooks (editor-customization)  → Surfaced (1 chunk, 1 context)
   settings-api (admin-ui, this chunk)  → Local (1 chunk, 2nd context)
   ↓
   Cross-context PRESENCE confirmed (2 contexts × 1 chunk each)
   ↓
   NOT yet Recurring (cross-context):
      requires structural density (multiple chunks per context
      or multiple chunks across contexts demonstrating recurrence
      density beyond isolated manifestation)
```

**Promotion discipline preserved:** the chunk does NOT promote
Mediation to Recurring (cross-context) prematurely. Cross-
context PRESENCE is established; full Recurring (cross-context)
status requires additional structural density.

## VERIFICATION NEEDED

`status: stable` — Settings API mature (WP 2.7+); schema-aware
registration mature (WP 4.7+). Specific behaviors evolving /
variable:

- Behavior with arrays / objects in options + complex
  sanitize_callback semantics.
- REST schema generation completeness for show_in_rest with
  custom types.
- Multisite network options behavior vs site options.
- Capability cache invalidation when custom roles change.
- options.php submission behavior with malformed option_group.
- Behavior when sanitize_callback throws.
- Coexistence with classic `add_option` / `update_option`
  pre-Settings-API patterns.
- Settings save during editor save (mixed editor + admin
  contexts).
- Performance with hundreds of registered settings.
- Plugin deactivation behavior — registered settings persist
  in wp_options indefinitely.

For practical decisions: empirical testing per scenario.

## ANTIPATTERNS

- ❌ **register_setting = secure by default**. Registration
  declares the persistence ABI; security requires explicit
  sanitize_callback + capability checks at multiple boundaries.
- ❌ **sanitize_callback = authorization**. Sanitize governs
  input shape; authorization is separate (capability checks).
  Both required.
- ❌ **settings page visibility = edit legitimacy**. Page
  visible (passed menu capability) does NOT mean every visitor
  can save. Page render must re-check capability;
  options.php verifies again.
- ❌ **admin-only = governance complete**. Admin restriction is
  UI-level; REST exposure (show_in_rest) bypasses admin gate.
  REST permission requires separate consideration.
- ❌ **options table = arbitrary storage**. wp_options is a
  persistence substrate with naming conventions (autoload,
  option_name uniqueness, network options); abuse degrades
  performance.
- ❌ **Settings page visibility = authority legitimacy**.
  Visibility is UX hint; authority is determined by capability
  checks at request boundaries.
- ❌ **`manage_options` capability = setting-specific
  legitimacy**. manage_options is general site-options
  capability; setting-specific gates may need finer
  capabilities or custom checks.
- ❌ **sanitize = governance complete**. Sanitization filters
  input shape; doesn't verify actor intent (nonce) or actor
  authority (capability). All three required.
- ❌ **register_setting without sanitize_callback**. Default
  sanitization (none beyond core's basic handling) is
  insufficient for non-trivial values; XSS/injection surface.
- ❌ **show_in_rest: true reflexively**. REST exposure adds
  attack surface; ensure permission_callback (auto-derived
  from capability or explicitly set) is appropriate.
- ❌ **Custom forms bypassing settings_fields()**. The helper
  includes nonce + option_group registration; custom forms
  must replicate or risk CSRF / save failures.
- ❌ **Plugin deactivation cleanup ignored**. Settings persist
  in wp_options indefinitely; uninstall hook should
  delete_option for cleanup.

## RELATED

- `editor-customization.editor-hooks` — paired test chunk.
  Authority Mediation Surface surfaced there; cross-context
  test confirmed here. The two chunks together establish
  Mediation as cross-context PRESENCE.
- `plugin-dev.security-boundaries` — security doctrine
  directly applied. Settings API exemplifies the 3-tier
  security model (trust at registration / legitimacy via
  sanitize / permeability via capability + nonce).
- `plugin-dev.capabilities-and-roles` — capability constitution
  applied at multiple Settings API boundaries (menu / render /
  options.php / REST). manage_options is the typical default
  capability.
- `plugin-dev.register-meta` — parallel persistence
  registration pattern. register_meta + register_setting share
  persistence ABI character (typed schema + sanitize +
  show_in_rest); apply to different storage targets (postmeta
  vs options).
- `data-layer.persistence` — Settings API is persistence
  reconciliation at admin layer. Same substrate (wp_options
  ⊂ persistence pipeline); different surface (admin form vs
  editor entity).
- `_meta.structural-patterns` — **Phase 7.5 patched
  constitutional framework deployed in this chunk**.

## META

**admin-ui bounded context — first chunk; first substantive
chunk authored under Phase 7.5 patched constitutional
framework.**

### Phase 7.5 patched framework deployment (3 explicit
acknowledgments)

**1. Patched verdict taxonomy deployed** (5-class
Confirmed/Divergent/Hybridized/Surfaced/Deferred):
Used in Tables A & B below.

**2. Patched maturity ladder applied** (5-tier Surfaced/Local/
Recurring intra-context/Recurring cross-context/KB-Wide):
Used in Table B promotion column with cross-context
PRESENCE distinction.

**3. Q8 adjudication doctrine operationalized**: chunk
explicitly evaluated as Confirm / Diverge / Hybridize /
Surface against existing candidates. Result documented
in Table B.

This chunk serves as the **reference exemplar for Phase 7.5
patched spec** — future chunks may reference this chunk's
META structure for patched-spec compliance.

### Doctrinal backbone

> **Editor hooks surfaced authority mediation inside reactive**
> **governance; Settings API tests whether mediation recurs**
> **across administrative governance through capability-gated**
> **persistence orchestration.**

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 1 — Declaration ≠ Exposure** | Very Strong | 4-form: registration / menu / render / REST | **Confirmed (most-multidimensional in admin-ui domain)** |
| **Law 4 — Arbitration Compiler** | Strong | options.php submission arbitrates form data through registered settings + sanitize callbacks | **Confirmed (admin-arbitration variant)** |
| **Law 3 — Authority Continuity** | Strong | Persisted option lifecycle survives across requests; Options API maintains continuity | **Confirmed** |
| **Law 6 — Compiler ↔ Runtime Split** | Strong | Registration (compile-time declaration) ↔ admin render (runtime composition) ↔ options.php (runtime processing) | **Confirmed** |
| **Law 5 — Entity → Relationship Pivot** | Moderate | Pages/sections/fields topology + capability/setting relationships | **Confirmed (admin-topology variant)** |
| **Law 2 — HTML Primacy** | Implicit | Admin pages render as HTML; doctrine respected | **Confirmed (implicit)** |

**Universal law manifestation: SUCCESS.** All predicted laws
manifested at predicted strengths.

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | Settings-API manifestation | Outcome |
|---|---|---|---|
| **Authority Mediation Surface** | Surfaced (editor-customization, 1 chunk) | Strong manifestation: sanitize_callback + capability + nonce + form transport = mediation pipeline | **Local (admin-ui, 1st chunk in 2nd context); CROSS-CONTEXT PRESENCE confirmed; NOT yet Recurring (cross-context)** |
| **Authority Interception Surface** | Recurring (intra-context, editor-customization 2-modality) | Weak/secondary (sanitize_callback could be read as interception of input shape, but the chunk's primary character is mediation, not interception) | **Divergent — admin-ui is mediation domain, not interception domain** |
| **Federation Pattern** | KB-Wide (plugin-dev origin; cross-context recurrence in editor-customization) | Plugin settings registration extends admin authority federation | **Confirmed (plugin-registered settings federate into admin authority architecture)** |

**Promotion event:**

```
Authority Mediation Surface candidate:
   Pre-this-chunk: Surfaced (editor-customization)
   Post-this-chunk: Local (admin-ui) + cross-context PRESENCE
   ↓
   Promotion: Surfaced → Local (in admin-ui context)
   Plus: cross-context PRESENCE acknowledged (2 bounded contexts × 1 chunk each)
   ↓
   NOT promoted to Recurring (cross-context):
      requires structural density (multiple chunks per context
      OR multiple chunks across contexts)
```

### Promotion Discipline Note

> **Why Authority Mediation Surface was NOT promoted to
> Recurring (cross-context) in this chunk:**
>
> Phase 7.5 patched maturity ladder distinguishes Recurring
> (intra-context) from Recurring (cross-context). The current
> evidence base is:
> - editor-customization: 1 chunk (editor-hooks) Surfaced the
>   pattern
> - admin-ui: 1 chunk (this) confirms Local manifestation
>
> Total: 2 contexts × 1 chunk each = **breadth without depth**.
>
> Recurring (cross-context) requires **structural density** —
> evidence that the pattern recurs WITHIN each context (not
> just one isolated manifestation per context). This chunk is
> Mediation's first manifestation in admin-ui; subsequent
> admin-ui mediation chunks (admin notices? admin AJAX
> endpoints? user/role admin?) would establish density.
>
> **Spec-grade doctrine** (worth formalizing in Phase 7.6
> patch consideration):
>
> > **Presence across contexts is not yet recurrence across**
> > **contexts unless recurrence exhibits structural density**
> > **beyond isolated manifestation.**

This discipline preservation is a critical KB integrity
behavior. Premature promotion of Mediation would compromise
the patched ladder's epistemic value.

### Phase 7.6 refinement candidate (observed but NOT applied)

The cross-context PRESENCE distinction may warrant explicit
spec recognition in a future Phase 7.6 patch:

```
Possible ladder nuance:
   Surfaced → Local → CROSS-CONTEXT PRESENCE (NEW)
   → Recurring (intra-context) → Recurring (cross-context)
   → KB-Wide
```

**Status: Observed constitutional granularity gap.** Do NOT
patch spec yet — this chunk is the first observation of the
gap; spec patches require sustained observation pattern, not
single-instance reaction. If subsequent cross-context tests
also encounter the breadth-without-density situation, Phase
7.6 patch becomes timely.

### KB-level framing extension (admin-ui domain identity)

> admin-ui is the **administrative governance modulation**
> bounded context — analogous to editor-customization's
> internal governance modulation but for ADMIN authority
> rather than editor authority. Both contexts mediate
> existing authority through governed channels, but admin-ui
> operates on persistence + capability axes while
> editor-customization operates on lifecycle + topology +
> reactive axes.

Admin-ui domain identity (anchored by this chunk):
**capability-gated persistence mediation.**

Future admin-ui chunks (admin menus / notices / admin AJAX /
user admin) extend within this identity and may further
strengthen Mediation candidate density.

### KB-wide pattern recurrence updates

**Debt pattern (5-instance recurrence)** — broadens beyond
editor/plugin-dev:

| chunk | debt name | bounded context |
|---|---|---|
| security-boundaries | security debt | plugin-dev |
| block-filters | interception debt | editor-customization |
| slotfills | topology debt | editor-customization |
| editor-hooks | reactive debt | editor-customization |
| **settings-api** | **settings debt** | **admin-ui** |

5 instances × 3 bounded contexts. "Governance debt" continues
broadening as anticipated meta-pattern. NOT yet promoted.

### KB self-evaluation against spec criteria (Phase 7.5 patched)

- ✅ Accuracy — describes documented Settings API.
- ✅ Structural fit — first admin-ui chunk; tests Mediation
  cross-context presence with discipline.
- ✅ Reusability — uses authority ontology glossary +
  Phase 7.5 vocabulary (mediation / cross-context PRESENCE /
  promotion discipline).
- ✅ Phase fit — first chunk under Phase 7.5 patched spec;
  references all relevant predecessor chunks.
- ✅ Doctrine respect — HTML primacy implicit; declaration ≠
  exposure 4-form invoked; Epistemic Integrity preserved
  (NOT over-promoted Mediation).
- ✅ **Q8 adjudication explicitly answered**: Mediation =
  Confirm (admin-ui mediation manifestation); Interception =
  Diverge (admin-ui is not interception domain); Federation =
  Confirm (plugin settings federate).

### Status: `stable`

Settings API mature (WP 2.7+); schema-aware register_setting
mature (WP 4.7+). Verification-needed catalog covers
behaviors but core API is settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission

> **This chunk should determine whether administrative**
> **persistence systems merely expose settings — or mediate**
> **authority through governed persistence constitutions.**

Verdict: **Mediation confirmed.** Settings API IS governed
persistence mediation, not raw storage exposure.

### Anticipated next chunks (priority)

1. **`admin-ui.admin-menus`** — second admin-ui chunk.
   Tests Mediation candidate density within admin-ui (could
   promote Mediation Local → Recurring intra-context if
   admin-menus also exhibits mediation character).

2. **`admin-ui.notices`** — third admin-ui mechanism.
   admin_notices hook may exhibit interception OR
   mediation OR hybrid character (interesting test).

3. **`site-building`** entry — composition-heavy bounded
   context. Different test surface (less mediation, more
   relationship-centric).

4. **`plugin-dev.nonces`** — security primitive trio
   completion (intermixing).

5. **Phase 7.6 spec patch** (anticipated) — if subsequent
   chunks encounter cross-context PRESENCE distinction
   repeatedly, formalize as ladder tier.

Recommended sequence: `admin-ui.admin-menus` (Mediation
density test within admin-ui — could trigger Local →
Recurring intra-context promotion) → either continue
admin-ui or branch to site-building.
