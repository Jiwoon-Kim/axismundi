---
rule_id: i18n.locale-switching
domain: i18n
topic: semantic-governance
field_cluster: translation-runtime
wp_min: "4.7"
wp_recommended: "5.6+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/switch_to_locale/
    section: "switch_to_locale() — function reference"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/restore_previous_locale/
    section: "restore_previous_locale() / restore_current_locale() — function reference"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/classes/wp_locale_switcher/
    section: "WP_Locale_Switcher class — locale stack management"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/determine_locale/
    section: "determine_locale() — locale resolution algorithm"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/get_user_locale/
    section: "get_user_locale() — user-specific locale"
    captured: 2026-05-09
related:
  - i18n.gettext-functions                          # PHP-side semantic continuity (sibling)
  - i18n.script-translations                        # cross-runtime continuity (sibling)
  - plugin-dev.capabilities-and-roles               # mediation pattern parallel
  - admin-ui.settings-api                           # mediation pattern parallel
  - data-layer.persistence                          # locale persistence parallel
  - _meta.structural-patterns                       # Phase 7.5/7.6/7.7 patched spec
  - _meta.kb-consolidation-phase7-8                 # Constitution v1 reference
---

# RULE — locale switching — semantic continuity under dynamic authority reassignment (i18n capstone)

## WHEN

A plugin or theme needs to operate under a locale OTHER
than the current request locale within a single request
lifecycle — sending emails to users in their preferred
locale, generating localized content for non-current users,
producing multi-locale outputs in batch, REST API responses
honoring `Accept-Language`, scheduled tasks producing
localized notifications.

Use locale switching APIs when:
- Sending an email to a specific user in their locale
  (regardless of currently logged-in user's locale).
- Generating PDFs / receipts / notifications targeting
  user-specific locales.
- REST API endpoint must respect `Accept-Language` header.
- Cron tasks producing user-locale-specific outputs.
- Network-wide output where per-site locale differs from
  current site context.
- Translating content for preview in a non-default locale.
- Multilingual plugin generating outputs in multiple locales
  within one request.

This is **3rd chunk in i18n bounded context** AND **i18n
bounded context capstone + closure adjudication chunk**.
Following gettext-functions (semantic continuity substrate
entry) and script-translations (cross-runtime continuity
bridge), this chunk tests whether i18n is merely **semantic
continuity** or whether it is **semantic governance**.

The doctrinal capstone this chunk establishes:

> **i18n is BOTH semantic continuity AND semantic**
> **governance.** Translation files + lookup mechanisms**
> **are semantic continuity. Locale stack + switching**
> **APIs are semantic governance — capability-like access**
> **mediation to translation authority under dynamic**
> **context reassignment.**

Constitutional question this chunk answers:

> Is i18n merely semantic continuity, or semantic
> governance?

**Verdict (per chunk's findings)**: BOTH. i18n exhibits
**continuity-governance hybrid character**. Locale switching
is the governance dimension; gettext/script-translations
are the continuity dimension. Doctrine 5 Hybridized variant
strengthens with **5th doctrinal pairing**: continuity
operations + governance operations.

## SHAPE

### A. Locale switching API surface

```php
// Switch to a specific locale within current request
switch_to_locale( 'ko_KR' );                     // returns bool

// Switch to a user's preferred locale
switch_to_user_locale( $user_id );               // WP 6.2+

// Restore previous locale (pop one off stack)
restore_previous_locale();                        // returns string|false

// Restore initial locale (unwind entire stack)
restore_current_locale();                         // returns string|false

// Detect if locale has been switched
is_locale_switched();                             // returns bool

// Get current locale (post-switch if switched)
get_locale();                                     // returns string
get_user_locale( $user_id_or_object );            // returns string
determine_locale();                               // resolves locale per request
```

| function | role |
|---|---|
| `switch_to_locale` | push new locale onto stack; reload textdomains for new locale |
| `switch_to_user_locale` | switch_to_locale wrapper resolving user's locale (WP 6.2+) |
| `restore_previous_locale` | pop stack (LIFO); restore previous locale |
| `restore_current_locale` | unwind entire stack; restore initial locale |
| `is_locale_switched` | check whether any switch is active |
| `get_locale` | return current locale (top of stack OR initial) |
| `get_user_locale` | return locale for specific user (user_locale meta or site locale) |
| `determine_locale` | runtime resolution algorithm (admin → user → site → default) |

### B. Locale stack discipline (LIFO mediation choreography)

```php
// Initial state
get_locale(); // 'en_US' (site default)
is_locale_switched(); // false

// Push first switch
switch_to_locale( 'ko_KR' );
get_locale(); // 'ko_KR'
is_locale_switched(); // true

  // Nested switch (push second)
  switch_to_locale( 'ja_JP' );
  get_locale(); // 'ja_JP'

  // Pop nested
  restore_previous_locale();
  get_locale(); // 'ko_KR' (back to first switch)

// Pop first
restore_previous_locale();
get_locale(); // 'en_US' (back to initial)
is_locale_switched(); // false

// Alternative: full unwind from any depth
switch_to_locale( 'ko_KR' );
switch_to_locale( 'ja_JP' );
switch_to_locale( 'fr_FR' );
restore_current_locale();   // unwind ALL
get_locale(); // 'en_US'
```

| stack operation | semantic |
|---|---|
| `switch_to_locale` | push (semantic authority reassignment, additive) |
| `restore_previous_locale` | pop (LIFO restoration; one step) |
| `restore_current_locale` | unwind (full restoration to initial state) |

The stack is managed by **`WP_Locale_Switcher` class** (one
of WordPress's few stack-discipline classes). Stack discipline
is **mediation choreography pattern** — controlled access to
semantic authority through stack-disciplined push/pop
operations.

### C. Side effects of switching (what actually changes)

```
On switch_to_locale($locale):

1. Locale value updated
   - get_locale() returns new locale
   - Filter 'locale' may modify (filtered)
   
2. Translations reloaded
   - load_default_textdomain($locale) reloads core
   - Per-domain reload via 'change_locale' action
   - Plugins/themes that hook 'change_locale' reload their
     textdomains for new locale
   
3. JS-side dispatch (does NOT auto-update)
   - wp_set_script_translations was called at enqueue time
   - JSON locale_data already injected for original locale
   - JS-side does NOT automatically re-dispatch on PHP switch
   - Manual re-injection via inline script + wp.i18n.setLocaleData
     required for cross-runtime locale switch
   
4. Locale-derived globals updated
   - $wp_locale (date/time formatting) may update
   - is_rtl() may change
   - Number/date format may change

On restore_previous_locale() / restore_current_locale():

5. Symmetric reverse: pop from stack, fire change_locale
   for restored locale, reload textdomains for restored
   locale
```

**Critical asymmetry**: PHP-side switch is
**semantic-runtime-complete** (textdomains reload). JS-side
dispatch is **NOT auto-re-dispatched**. This is a
**cross-runtime governance gap** — bridge mechanism from
script-translations does NOT extend to dynamic switching.

### D. Locale resolution algorithm — `determine_locale`

```php
// determine_locale() resolution priority (per WP source):

1. is_admin()
   ↓ YES → user locale (get_user_locale of current user)
   ↓ NO  → continue
   
2. REST API request?
   ↓ YES → user locale (if authenticated) OR site locale
   ↓ NO  → continue
   
3. Site locale (get_locale option / WPLANG constant)
   ↓ Default fallback
   
4. Filter 'determine_locale' applied to result
   ↓ Plugins can override final resolution
```

| stage | character | mediation level |
|---|---|---|
| `pre_determine_locale` filter | early override | strong mediation |
| Admin context check | structural | structural mediation |
| User locale lookup | per-user authority | individual authority |
| REST context check | runtime context | request-context mediation |
| Site locale fallback | default authority | base authority |
| `determine_locale` filter | final override | post-resolution mediation |

`determine_locale` IS **multi-stage authority mediation
algorithm** — capability-context-source-aware locale
authority resolution.

### E. Critical hooks for locale governance

```php
// Fire before locale change
do_action( 'change_locale', $locale );

// Filter the resolved locale at request initialization
$locale = apply_filters( 'pre_determine_locale', null );
$locale = apply_filters( 'determine_locale', $locale );

// Filter locale function returns
$locale = apply_filters( 'locale', $locale );
$user_locale = apply_filters( 'user_locale', $locale, $user_id );

// Plugin/theme reload pattern
add_action( 'change_locale', function( $new_locale ) {
    unload_textdomain( 'my-plugin' );
    load_plugin_textdomain( 'my-plugin', false, '/languages' );
} );
```

| hook | role | mediation character |
|---|---|---|
| `change_locale` | action fired on switch/restore | reactive coordination point |
| `pre_determine_locale` | early filter override | strong mediation |
| `determine_locale` | final filter override | post-resolution mediation |
| `locale` | get_locale return filter | continuous mediation |
| `user_locale` | get_user_locale return filter | per-user mediation |

### F. Use case patterns

```php
// Pattern 1: Send email in user's locale
function send_localized_email( $user_id, $subject_key, $body_key ) {
    switch_to_user_locale( $user_id );
    
    $subject = __( $subject_key, 'my-plugin' );
    $body    = __( $body_key, 'my-plugin' );
    wp_mail( get_userdata( $user_id )->user_email, $subject, $body );
    
    restore_previous_locale();
}

// Pattern 2: REST API Accept-Language honoring
add_action( 'rest_api_init', function() {
    add_filter( 'determine_locale', function( $locale ) {
        if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            $requested = parse_accept_language( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
            if ( in_array( $requested, get_available_languages(), true ) ) {
                return $requested;
            }
        }
        return $locale;
    } );
} );

// Pattern 3: Batch multi-locale generation
foreach ( $locales as $locale ) {
    switch_to_locale( $locale );
    $output[ $locale ] = generate_localized_content();
    restore_previous_locale();
}

// Pattern 4: Try/finally for safe restoration
switch_to_locale( 'ko_KR' );
try {
    do_locale_sensitive_work();
} finally {
    restore_previous_locale();
}
```

### G. Failure surfaces — locale governance debt

```
Locale switching failure modes:

- Forgetting restore_previous_locale → stack leak; subsequent
  request output in wrong locale
- Asymmetric switch/restore counts → stack imbalance; output
  locale unpredictable
- Switching during plugin/theme load (pre-change_locale
  hookability) → textdomains may not reload correctly
- Hook 'change_locale' but no unload + reload → stale
  textdomain remains active
- JS-side locale_data NOT re-dispatched after PHP switch →
  cross-runtime divergence (PHP shows ko_KR, JS shows en_US)
- Switch in unsafe context (mid-output buffer) → mixed-locale
  output
- determine_locale filter side effects → unintended global
  locale change
- get_user_locale called for non-existent user → fallback
  to site locale (silent)
- Switch to unavailable locale (no MO file) → returns false;
  silent fallback to current locale
- Network-activated plugins switching site locale on subsite
  → multisite governance violations
- Switching during cron without authentication context →
  determine_locale resolution unexpected
```

**Locale governance debt** (11th debt-pattern instance in
KB; first locale-governance debt):

| debt mode | symptom |
|---|---|
| stack leak | restore omitted; later output wrong locale |
| stack imbalance | mismatched switch/restore counts |
| cross-runtime divergence | PHP switch + JS unchanged |
| premature switch | textdomain reload before plugins hook change_locale |
| unsafe-context switch | mid-output mixed locale |
| filter side effects | determine_locale filter unintentionally global |

11 instances × 6 bounded contexts. Governance debt
meta-pattern continues; now spans **runtime locale
governance** dimension.

## REQUIRES

- WP 4.7+ for `switch_to_locale` / `restore_previous_locale`
  / `WP_Locale_Switcher` (added 4.7).
- WP 6.2+ for `switch_to_user_locale` (convenience wrapper).
- For each switched-to locale: corresponding MO file present
  (and JSON if cross-runtime needed).
- Plugins/themes hooking `change_locale` to reload their
  textdomains for new locale.
- Symmetric switch/restore discipline (every `switch_to_locale`
  paired with `restore_previous_locale`).
- For REST/cron: explicit `determine_locale` filter or
  switch_to_locale call (no implicit user-locale resolution
  in unauthenticated contexts).
- Cross-runtime switching: manual JS-side
  `wp.i18n.setLocaleData` re-injection (no auto-bridge from
  PHP switch).
- ⚠ Specific behaviors: `change_locale` hook timing during
  request initialization, multisite locale switching
  semantics, locale switching in REST nested requests,
  JS-side cross-runtime re-dispatch patterns —
  verification-needed.

## INVARIANTS

### 1. Locale switching IS semantic governance (NOT mere resolution)

The constitutional capstone finding for i18n bounded context:

> Locale switching is NOT "look up translation in different
> table." It is **semantic authority access mediation under
> dynamic context reassignment**. The locale stack is
> capability-like access discipline; switch_to_locale is
> structural mediation choreography.

Reading locale switching as "just another resolution step"
misses the **governance dimension**. Resolution selects from
candidates; mediation orchestrates ACCESS to candidate sets.

| dimension | character |
|---|---|
| Resolution (msgid → msgstr lookup) | continuity dimension |
| Mediation (locale stack → translation authority access) | governance dimension |

This is **i18n's bidimensional character**. gettext +
script-translations occupy the continuity dimension; locale
switching occupies the governance dimension.

### 2. Authority Mediation Surface manifests in semantic substrate (cross-context PRESENCE — NEW for i18n)

> **Major Phase 8.1 candidate evidence.**

Pre-this-chunk Authority Mediation Surface status:
- Recurring (intra-context, admin-ui)
- Cross-context PRESENCE (editor-customization)

Post-this-chunk Authority Mediation Surface status:
- Recurring (intra-context, admin-ui)
- Cross-context PRESENCE (editor-customization + **i18n**)

Evidence in this chunk:
- `switch_to_locale` IS authority access reassignment
  (NOT capability-gated, but context-gated)
- `WP_Locale_Switcher` stack IS mediation choreography
  discipline
- `determine_locale` algorithm IS multi-stage authority
  resolution mediation
- `change_locale` hook IS reactive coordination point
- `pre_determine_locale` / `determine_locale` filters ARE
  mediation override surfaces

> **Mediation character is BROADER than capability-gating.**
> Mediation = controlled access reassignment to authority
> resource through structural choreography. Capability-gating
> (admin-ui) is one mediation form; context-gating (i18n) is
> another.

This is a **mediation taxonomy expansion observation**:

| mediation form | bounded context | gating mechanism |
|---|---|---|
| Capability-gated mediation | admin-ui (settings-api, admin-menus) | user capability check |
| Authority subscription mediation | editor-customization (editor-hooks) | direct subscribe/dispatch |
| **Context-gated mediation (NEW)** | **i18n (locale-switching)** | **runtime context reassignment** |

Mediation candidate **gains semantic-substrate cross-context
PRESENCE** via i18n. Promotion path to **Recurring (cross-
context)** advanced.

### 3. Doctrine 5 — i18n exhibits Continuity-Governance Hybrid character (NEW pairing observation)

This chunk reveals i18n's **bidimensional Doctrine 5
character**:

```
i18n bounded context Doctrine 5 manifestations:

   gettext-functions:        Hybridized (Distributed + Integrated)
                              continuity dimension only
   
   script-translations:       Hybridized (Distributed + Integrated)
                              cross-runtime continuity
   
   locale-switching (this):   Hybridized (Distributed + Integrated)
                              governance dimension only
                              + Mediation pairing observation
```

**6th Hybridized variant in KB** (after block patterns +
variations + transforms + gettext-functions + script-translations).

But more important: this is **3rd consecutive Hybridized
within single bounded context**. i18n is now confirmed
**Hybridized-dense bounded context**.

> **NEW DOCTRINAL OBSERVATION**: Within Hybridized variant,
> a sub-pairing emerges: **Continuity operations + Governance
> operations** as paired Hybridized layers within same
> bounded context.

This is NOT a new sub-pattern (per Q10 discipline) — it is
a **bounded-context-level pattern observation**. i18n
exhibits both continuity AND governance via Hybridized
operations. Q10 verdict: **observation only, NOT promoted**.

### 4. Authority Continuity (Law 3) survives dynamic context reassignment

This chunk's key Law 3 finding:

```
Authority Continuity under context mutation chain:

Initial state:
   get_locale() → 'en_US'
   __('Save', 'my-plugin') → 'Save' (or English msgstr)

switch_to_locale('ko_KR'):
   1. Push 'ko_KR' onto WP_Locale_Switcher stack
   2. Fire change_locale('ko_KR')
   3. Plugins/themes reload textdomains for ko_KR
   4. Initial 'en_US' textdomain registry preserved
      in switcher's restoration data
   
   ↓ semantic authority reassignment, BUT continuity
     of original authority preserved in stack
   
   __('Save', 'my-plugin') → '저장' (Korean msgstr)

restore_previous_locale():
   1. Pop ko_KR off stack
   2. Fire change_locale('en_US')
   3. Restore textdomain registry from switcher data
   
   ↓ original authority restored exactly
   
   __('Save', 'my-plugin') → 'Save' (back to original)
```

**Continuity preserved across reassignment.** The stack
discipline IS the continuity preservation mechanism. Without
the stack, switching would be destructive (overwrite); with
stack, switching is non-destructive (push/pop).

> **Law 3 manifests as STACK-DISCIPLINED CONTINUITY** in
> locale switching. Stack-discipline is the continuity
> contract for context-mutating authority reassignment.

This is a **NEW Law 3 manifestation form**: continuity
through stack discipline (vs continuity through immutable
identity in gettext-functions, vs continuity through
runtime-boundary linker in script-translations).

### 5. Compiler ↔ Runtime Split (Law 6) — compiled resources persist, runtime context fluid

Law 6 manifests asymmetrically in locale switching:

| dimension | character |
|---|---|
| Compiler resources (MO files, JSON files) | **Persistent** across switches |
| Runtime context (active locale, loaded textdomain registry) | **Fluid** under switching |
| Linker (load_*_textdomain, wp_set_script_translations) | **Re-invocable** during switch |

Locale switching does NOT recompile; it re-links. The
compiler/linker/runtime triad is preserved, but linker
becomes **re-invocable runtime operation** during switching.

> **Constitutional refinement of Law 6**: linker stage may
> be re-invoked at runtime under context-mutation operations.
> Compiler stays stable; linker becomes dynamic; runtime
> reflects new linkage.

This is a **NEW Law 6 nuance** observed in i18n bounded
context.

### 6. Cross-runtime governance gap (asymmetric Bridge Pattern coverage)

Critical finding for KB:

> **Bridge Pattern (script-translations chunk surfacing)**
> **does NOT auto-extend to dynamic switching.**

PHP-side switch reloads textdomains; JS-side
`wp.i18n` locale_data remains at original locale unless
manually re-injected.

```php
// Cross-runtime switch pattern (manual)
switch_to_locale( 'ko_KR' );

// Manually re-inject JS locale data
$json_path = '/path/to/my-plugin-ko_KR-handle.json';
$json_data = json_decode( file_get_contents( $json_path ), true );

wp_add_inline_script(
    'my-plugin-block',
    sprintf(
        'wp.i18n.setLocaleData( %s, %s );',
        wp_json_encode( $json_data['locale_data']['messages'] ),
        wp_json_encode( 'my-plugin' )
    ),
    'before'
);
```

**This is cross-runtime governance gap** — Bridge Pattern
asymmetry under dynamic conditions. Bridge Pattern (surfaced
in script-translations) supports **STATIC** dispatch but
NOT **DYNAMIC** re-dispatch.

> **Bridge Pattern observation refinement**: Bridge Pattern
> exists in static dispatch dimension; under dynamic context
> mutation, bridge requires manual re-invocation. Bridge is
> NOT runtime-mutation-transparent.

Q9 retro candidate: directive-protocol re-dispatch under
dynamic state changes — same gap may exist.

### 7. Semantic substrate bounded context character — formalization threshold reached

> **3rd consecutive i18n chunk consistent with semantic
> substrate character.**

Per Phase 7.6/7.7 deferred candidate "Bounded context
character taxonomy", semantic substrate observation
reached consistency threshold:

| chunk | semantic substrate evidence |
|---|---|
| gettext-functions | semantic continuity through compile-time extraction + runtime resolution |
| script-translations | semantic continuity bridges runtime boundaries |
| **locale-switching** | semantic governance through dynamic authority reassignment |

3 consistent intra-context manifestations of semantic
substrate character in i18n.

> **Formalization recommendation (status: SURFACED, not
> promoted)**: Bounded context character taxonomy expansion
> from 5 to 6 categories warranted IF cross-context
> verification reveals other semantic-substrate-character
> contexts (candidate: shortcode parsing, block name
> resolution, preset slug resolution).

Per Phase 7.6+7.7 "surface, do not constitutionalize"
discipline, taxonomy expansion **deferred until cross-
context verification**. Single-bounded-context manifestation
(even with 3 consistent chunks) insufficient for taxonomy
expansion.

### 8. KB Constitution v1 — i18n capstone validation: PASSED

> **3rd KB-level validation in i18n bounded context.**

Pre-this-chunk Constitution v1 i18n validations:
- Validation 1 (gettext-functions): ports into semantic
  substrate
- Validation 2 (script-translations): ports across runtime
  boundaries

This chunk's validation:
- Validation 3 (locale-switching): **ports into semantic
  governance dimension**

Post-this-chunk evidence:
- Law 3 (Authority Continuity): VERY STRONG (stack-disciplined
  continuity NEW form)
- Law 6 (Compiler ↔ Runtime): STRONG (linker re-invocability
  refinement)
- Law 4 (Arbitration Compiler): Confirmed (locale resolution
  algorithm IS arbitration)
- Law 1 (Declaration ≠ Exposure): Confirmed (locale switched
  ≠ JS dispatched ≠ output rendered)
- Doctrine 5 (Hybridized variant): Confirmed (6th instance;
  Continuity-Governance Hybrid pairing observation)
- Authority Mediation Surface: **STRENGTHENED**
  (cross-context PRESENCE in i18n; NEW context-gated
  mediation form)
- Resolution Surface: implicit (locale stack mediates access
  to Resolution authority)
- Bridge Pattern: refined (asymmetric coverage observation)
- Semantic substrate character: 3rd consistent observation
  (formalization threshold reached, but cross-context
  verification deferred)

> **Verdict: KB Constitution v1 capstone validation in i18n**
> **PASSED.** i18n bounded context demonstrates Constitution
> v1's full applicability across continuity AND governance
> dimensions of semantic substrate.

### 9. i18n bounded context — CLOSURE READY

This chunk completes i18n bounded context constitutional
density:

| chunk | dimension | constitutional contribution |
|---|---|---|
| gettext-functions | continuity (PHP) | Constitution v1 portability test |
| script-translations | continuity (cross-runtime) | runtime boundary as continuity class |
| **locale-switching** | **governance** | **Mediation cross-context PRESENCE; semantic governance** |

Bounded context closure criteria:
- ✅ Multi-chunk density (3 chunks)
- ✅ Bidimensional coverage (continuity + governance)
- ✅ Cross-context candidate manifestations (Resolution,
  Mediation, Federation, Bridge)
- ✅ Constitutional law manifestation (Laws 1, 3, 4, 6)
- ✅ Q9 retro candidates identified (locale-related retros)
- ✅ Q10 disciplined negative findings (consistent sub-pattern
  governance)

> **i18n bounded context status: CLOSURE READY.**
> Future i18n chunks (multilingual plugin specifics, RTL
> handling specifics, etc.) would be optional enrichment,
> not closure-blocking.

## VERIFICATION NEEDED

`status: stable`. Items requiring verification:

- `change_locale` hook timing during request initialization
  (before vs after plugin/theme load).
- Multisite locale switching semantics (network admin context,
  switch_to_blog interaction).
- Locale switching in REST nested requests (request inside
  request).
- JS-side cross-runtime re-dispatch patterns + WP-recommended
  conventions.
- `WP_Locale_Switcher` stack depth limits (none documented
  but practical limits?).
- `restore_current_locale` behavior when stack is empty.
- Filter precedence between `pre_determine_locale` and
  `determine_locale` in nested switch contexts.
- `get_user_locale` behavior for users with no `locale` user
  meta (falls back how?).
- Concurrent switch interaction with object cache.
- Locale switching impact on `$wp_locale` global (date/time
  formatting).
- RTL detection (`is_rtl()`) behavior under locale switch.
- Performance impact of frequent switching (textdomain reload
  overhead).
- Behavior when MO file for switched-to locale doesn't exist.
- Cron / WP-CLI / unauthenticated context locale resolution
  defaults.

For practical decisions: empirical testing per use case +
WP version + multisite config.

## ANTIPATTERNS

- ❌ **Forgetting `restore_previous_locale`**. Stack leaks
  → subsequent code (or even subsequent requests if cache
  affected) operates in wrong locale. Always pair switch
  with restore.
- ❌ **Asymmetric switch/restore counts**. Multiple switches
  with single restore = stack imbalance. Use try/finally
  for safety.
- ❌ **Switching mid-output**. echo / wp_print_scripts
  before restore = mixed-locale output buffer. Switch BEFORE
  output generation; restore AFTER.
- ❌ **Manual `setlocale()` calls**. Use WP's
  switch_to_locale (manages stack + textdomains); raw
  setlocale bypasses governance + textdomain reload.
- ❌ **Assuming JS auto-updates on PHP switch**. JS-side
  locale_data does NOT auto-re-dispatch. Manual re-injection
  required for cross-runtime switching.
- ❌ **Hooking `change_locale` without unload/reload pattern**.
  Hooking but not calling unload_textdomain + load_*_textdomain
  = stale translations.
- ❌ **Switching during plugin/theme bootstrap**. Switch
  before `init` or before plugins hook `change_locale` =
  textdomains may not reload correctly.
- ❌ **`determine_locale` filter side effects**. Filter
  modifying global state = unintended effects across all
  requests. Filter should be pure: input → output.
- ❌ **Switching for non-translatable operations**. Switch
  to locale solely to format dates/numbers = misuse; use
  `wp_date()` or `number_format_i18n()` directly.
- ❌ **Network-scope switching from subsite context**.
  Multisite governance violation; use `switch_to_blog` for
  cross-site, `switch_to_locale` for cross-locale within
  current site.
- ❌ **Switching to unverified locale**. Always verify locale
  available (`get_available_languages()`) before switching;
  switch to unavailable locale silently fails.
- ❌ **Storing switched-state across request boundaries**.
  Locale switches are request-scoped; do not assume state
  persists across requests.

## RELATED

- `i18n.gettext-functions` — PHP-side semantic continuity
  (sibling; locale switching reloads gettext textdomains).
- `i18n.script-translations` — cross-runtime continuity
  (sibling; locale switching does NOT auto-re-dispatch JS
  locale_data).
- `plugin-dev.capabilities-and-roles` — mediation pattern
  parallel (capability-gated mediation vs context-gated
  mediation).
- `admin-ui.settings-api` — mediation pattern parallel
  (capability-gated mediation reference).
- `data-layer.persistence` — locale persistence
  (`user_locale` meta, options table site locale).
- `_meta.structural-patterns` — Phase 7.5/7.6/7.7 patched
  spec applied; mediation taxonomy expansion observation.
- `_meta.kb-consolidation-phase7-8` — Constitution v1
  capstone validation.

## META

**i18n bounded context — 3rd chunk; capstone + closure
adjudication + Authority Mediation Surface cross-context
PRESENCE deployment.**

### Phase 7.5/7.6/7.7 patched framework deployment

Per established post-Phase-7.5+ chunk pattern:

1. ✅ **Patched verdict taxonomy deployed** (5-class).
2. ✅ **Patched maturity ladder applied** (5-tier).
3. ✅ **Q8 adjudication doctrine operationalized**.
4. ✅ **Doctrine 5 (Arbitration ↔ Resolution Paired
   Operations) directly applied** — verdict: Hybridized
   architecture (6th Hybridized in KB; Continuity-Governance
   Hybrid pairing observation).
5. ✅ **Q9 retroactive verification trigger applied**.
6. ✅ **Q10 sub-pattern emergence diagnostic applied** —
   verdict: NO new sub-pattern (Continuity-Governance
   pairing is bounded-context-level observation, not sub-
   pattern).

### Doctrinal capstone established

> **i18n is BOTH semantic continuity AND semantic**
> **governance.** Translation files + lookup mechanisms**
> **are semantic continuity. Locale stack + switching APIs**
> **are semantic governance — capability-like access**
> **mediation to translation authority under dynamic**
> **context reassignment.**

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 3 — Authority Continuity** | VERY STRONG | Stack-disciplined continuity (NEW form): switch is non-destructive push, restore is non-destructive pop; original authority preserved in stack | **Confirmed (NEW Law 3 manifestation form: stack-disciplined continuity)** |
| **Law 6 — Compiler ↔ Runtime Split** | STRONG | Compiled resources persist; linker re-invocable at runtime under context mutation; runtime fluid | **Confirmed (NEW Law 6 nuance: linker re-invocability under context mutation)** |
| **Law 4 — Arbitration Compiler** | Moderate | determine_locale algorithm IS multi-stage authority resolution arbitration | **Confirmed (locale resolution is arbitration)** |
| **Law 1 — Declaration ≠ Exposure** | Moderate | locale switched ≠ textdomain reloaded ≠ JS dispatched ≠ output rendered | **Confirmed (4-form gap: switched / reloaded / dispatched / rendered)** |
| **Doctrine 5 — Hybridized variant** | Moderate-Strong | Distributed (compile-time pipeline + linker) + Integrated (runtime lookup); 6th Hybridized | **Confirmed (6th instance; 3rd consecutive intra-i18n; Continuity-Governance pairing observation)** |
| **Authority Mediation Surface (candidate)** | CRITICAL TEST | switch_to_locale + WP_Locale_Switcher stack + determine_locale + change_locale = full mediation surface | **Confirmed (cross-context PRESENCE in semantic substrate; NEW context-gated mediation form; promotion path advanced)** |
| **Resolution Surface (candidate)** | STRONG | locale stack mediates access to Resolution authority; locale → translation candidates → resolved msgstr | **Confirmed implicitly (mediation to Resolution; refusal verdict undisturbed)** |
| **Bridge Pattern (observation)** | Moderate | PHP switch + JS dispatch asymmetry; Bridge does NOT auto-extend to dynamic switching | **Refined (asymmetric coverage observation; NOT runtime-mutation-transparent)** |
| **Authority Interception Surface (candidate)** | Weak | locale switching doesn't intercept; it reassigns | **Divergent** |
| **Federation Pattern** | Implicit | text domain federation under switching: each domain reloads independently for new locale | **Confirmed (federation preserved across switching; per-domain independent reload)** |

**Universal law manifestation: SUCCESS — major validations:**
- **Law 3** NEW manifestation form (stack-disciplined
  continuity)
- **Law 6** NEW nuance (linker re-invocability)
- **Authority Mediation Surface** CRITICAL TEST PASSED
  (cross-context PRESENCE in semantic substrate; NEW context-
  gated mediation form)
- **Doctrine 5** 6th Hybridized; Continuity-Governance Hybrid
  pairing observation
- **Bridge Pattern** refinement (asymmetric coverage)

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | locale-switching manifestation | Outcome |
|---|---|---|---|
| **Authority Mediation Surface** | Recurring (intra-context, admin-ui) + cross-context PRESENCE (editor-customization) | CRITICAL: switch_to_locale + WP_Locale_Switcher + determine_locale + change_locale = full mediation surface in semantic substrate; NEW context-gated mediation form | **Confirmed (cross-context PRESENCE strengthened to 3 contexts: admin-ui + editor-customization + i18n; promotion path to Recurring (cross-context) approached)** |
| **Resolution Surface** | Recurring (cross-context); KB-Wide REFUSED (Phase 7.8) | Implicit: locale stack mediates access to Resolution authority; locale resolution algorithm IS Resolution character | **Confirmed implicitly (refusal verdict undisturbed; pattern recurrence confirmed)** |
| **Selection from Candidates (sub-pattern)** | Recurring (cross-context, sub-pattern of Doctrine 5 Hybridized) | DIVERGENT — locale resolution is non-user-facing arbitration; deterministic algorithm | **Divergent (consistent with i18n's 3-chunk pattern: sub-pattern requires user-facing UI selection)** |
| **Authority Interception Surface** | Recurring (intra-context, editor-customization) | Not present — locale switching doesn't intercept | **Not present** |
| **Federation Pattern** | KB-Wide-equivalent (7-context recurrence; runtime-transparent) | Strong: text domain federation reloads independently per domain on switch; federation preserved across switching | **Confirmed (federation switching-transparent; KB-Wide further reinforced)** |
| **Bridge Pattern (observation)** | Surfaced (script-translations only) | Refined: PHP switch + JS dispatch asymmetry reveals Bridge Pattern is NOT runtime-mutation-transparent | **Refined (NEW observation: asymmetric coverage; static-only Bridge)** |
| **Administrative Routing Surface (candidate)** | Surfaced (admin-ui only) | Not present — i18n has no navigation topology | **Not present** |
| **Semantic substrate (potential bounded context character)** | Strengthened (2 i18n instances) | 3rd consistent intra-i18n observation; bidimensional (continuity + governance) | **Threshold reached (3 consistent) — but cross-context verification deferred per discipline** |
| **Continuity-Governance Hybrid pairing (NEW observation)** | did not exist | i18n exhibits both continuity (gettext + script-translations) AND governance (locale-switching) Hybridized variants | **Surfaced only ("surface, do not constitutionalize"; bounded-context-level observation, not sub-pattern)** |

### Q9 Retroactive Verification Triggered

> **Q9 ANSWER: YES — this chunk reveals (a) Authority
> Mediation Surface manifestations in semantic substrate
> may exist latently in other context-mutation mechanisms,
> (b) Bridge Pattern static-vs-dynamic asymmetry may exist
> in other bridges, (c) Continuity-Governance Hybrid pairing
> may exist in other Hybridized-dense bounded contexts.**

**Q9 candidates triggered**:
1. **`plugin-dev.capabilities-and-roles`** — capability
   switching (`current_user_can` context vs `wp_set_current_user`
   context) potential mediation choreography parallel
2. **`interactivity.directive-protocol`** — directive state
   mutation under client interaction; potential Bridge Pattern
   asymmetry parallel
3. **`data-layer.entity-resolution`** — entity context
   switching (switch_to_blog parallel) potential Mediation
   parallel
4. **`block-authoring.dynamic-rendering`** — render context
   under preview / save / autosave; potential Continuity-
   Governance pairing
5. (specific) **`switch_to_blog`** mechanism (data-layer
   context) — direct Mediation parallel to switch_to_locale

These are Q9 trigger flags; future chunks will execute retros
if candidates reach Local status or Mediation cross-context
PRESENCE strengthens further.

### Q10 Sub-pattern Emergence (NEGATIVE finding documented; consistent with i18n pattern)

> **Q10 ANSWER: NO new stable sub-pattern observed.**

Initial hypothesis: Continuity-Governance Hybrid pairing
might be sub-pattern of Doctrine 5.

Honest evaluation: Continuity-Governance pairing is
**bounded-context-level observation**, NOT sub-pattern of
Doctrine 5 Hybridized variant. Sub-patterns operate WITHIN
Hybridized variant (per Phase 7.7); Continuity-Governance
operates ACROSS Hybridized instances (multi-chunk pairing
within bounded context).

> **Refusing premature sub-pattern inflation per Phase 7.5
> Doctrine 2 + Phase 7.7 Doctrine 5c discipline.**
> Bounded-context-level observations are NOT sub-patterns;
> they are bounded-context character observations.

This is **3rd consecutive Q10 negative finding in i18n**.
Q10 discipline holding strong — refusing premature
inflation across all 3 i18n chunks.

### NEW KB-level findings

**1. Authority Mediation Surface advances to 3-context cross-context PRESENCE**

| context | mediation form | gating mechanism |
|---|---|---|
| admin-ui | capability-gated | user capability check |
| editor-customization | authority subscription | direct subscribe/dispatch |
| **i18n (NEW)** | **context-gated** | **runtime context reassignment** |

> **Promotion path to Recurring (cross-context) substantively
> advanced.** Mediation now demonstrates **3 distinct gating
> mechanisms across 3 bounded contexts**. Promotion threshold
> requires sustained cross-context recurrence (audit will
> determine).

**2. Mediation taxonomy expansion observation (NEW)**

Mediation forms now documented:
- Capability-gated (admin-ui)
- Authority subscription (editor-customization)
- **Context-gated (i18n) — NEW**

Mediation is **broader than capability-gating**. Definition
refinement candidate for next constitutional patch.

**3. Bridge Pattern asymmetric coverage refinement (NEW)**

Bridge Pattern (surfaced in script-translations):
- ✅ Static dispatch coverage (PHP register → JS receive)
- ❌ Dynamic re-dispatch coverage (PHP switch → JS does NOT auto-update)

> **Bridge Pattern is static-only.** Dynamic Bridge variant
> (or Bridge re-invocation pattern) requires explicit
> mechanism. NOT runtime-mutation-transparent.

**4. Continuity-Governance Hybrid pairing (NEW bounded-context-level observation)**

i18n exhibits both continuity AND governance dimensions
through Hybridized variants:
- Continuity dimension: gettext-functions + script-translations
- Governance dimension: locale-switching

This is a **bounded-context-level pattern observation**
(NOT sub-pattern; NOT KB-Wide candidate). Other bounded
contexts MAY exhibit similar bidimensional Hybridized
character — verification candidate.

**5. Semantic substrate character — formalization threshold reached**

3rd consistent i18n manifestation reaches Phase 7.6/7.7
threshold for **observation-to-candidate progression**.
Per discipline:
- Threshold: ✅ 3 consistent intra-context manifestations
- Required for promotion: cross-context verification
  (other semantic-substrate-character contexts)

> **Status: SURFACED at observation level (formalization
> threshold reached intra-context); promotion to candidate
> deferred until cross-context verification.**

Cross-context verification candidates:
- Shortcode parsing (semantic name → handler resolution)
- Block name resolution (semantic identifier → block type)
- Preset slug resolution (semantic preset → CSS variable)
- Hook tag resolution (semantic action/filter name → callbacks)

### Constitutional capstone test rationale (META framing)

This chunk's strategic role per `_meta.kb-consolidation-phase7-8`
Section F + i18n bounded context closure:

> **i18n: capstone + closure adjudication** (does i18n
> exhaust constitutional density? Does Mediation gain
> semantic-substrate cross-context PRESENCE?)

**Verdict: SUCCESS.** i18n bounded context CLOSURE READY.
Authority Mediation Surface gains cross-context PRESENCE
in semantic substrate. NEW mediation taxonomy form
(context-gated) documented.

### KB-wide pattern recurrence updates

**Locale governance debt = 11th debt-pattern instance:**

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
| **locale-switching** | **locale governance debt** | **i18n** |

11 instances × 6 bounded contexts. Governance debt
meta-pattern continues; now spans **runtime locale
governance** dimension.

**Authority Mediation Surface (3-context cross-context PRESENCE)**:
admin-ui (Recurring intra-context) + editor-customization
(cross-context PRESENCE) + **i18n (cross-context PRESENCE +
NEW context-gated form)**.

**Doctrine 5 Hybridized (6 instances × 3 contexts)**:
block patterns + variations + transforms + gettext-functions +
script-translations + **locale-switching**. **3rd
consecutive intra-i18n Hybridized.**

**Federation Pattern (7-context manifestation; runtime-
transparent + switching-transparent)**: plugin-dev +
editor-customization + admin-ui + site-building +
i18n.gettext + i18n.script-translations + **i18n.locale-
switching (federation switching-transparent; per-domain
independent reload)**.

### KB self-evaluation against spec criteria (Phase 7.5/7.6/7.7 patched)

- ✅ Accuracy — describes documented switch_to_locale +
  restore_previous_locale + WP_Locale_Switcher API.
- ✅ Structural fit — 3rd i18n chunk; capstone + closure
  adjudication.
- ✅ Reusability — uses authority ontology glossary + Phase
  7.5/7.6/7.7 vocabulary (mediation / context-gated / stack-
  disciplined continuity / Continuity-Governance pairing).
- ✅ Phase fit — capstone role per consolidation document
  Section F.
- ✅ Doctrine respect — declaration ≠ exposure 4-form;
  Epistemic Integrity preserved (Q10 negative finding refused
  premature sub-pattern inflation; Continuity-Governance
  surfaced not promoted; semantic substrate threshold reached
  but promotion deferred).
- ✅ **Q8 explicit answer**: Mediation Confirm (CRITICAL
  TEST PASSED; cross-context PRESENCE); Resolution Confirm
  implicit; Federation Confirm; Bridge Pattern Refine
  (asymmetric coverage); Continuity-Governance pairing
  Surface (observation only); semantic substrate threshold
  reached but defer promotion.
- ✅ **Q9 explicit answer**: YES — capabilities-and-roles +
  directive-protocol + entity-resolution + dynamic-rendering
  + switch_to_blog mechanism Q9 retro candidates.
- ✅ **Q10 explicit answer**: NO new sub-pattern; Continuity-
  Governance pairing is bounded-context-level observation,
  NOT sub-pattern of Doctrine 5; refused premature inflation.

### Status: `stable`

Locale switching API mature since WP 4.7 (added 2016);
WP_Locale_Switcher class stable; switch_to_user_locale
WP 6.2+. Verification-needed catalog covers behaviors but
core API is settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission

> **Is i18n merely semantic continuity, or semantic**
> **governance?**

**Verdict: BOTH.** i18n is bidimensional — continuity
(gettext + script-translations) AND governance (locale-
switching). Authority Mediation Surface gains semantic-
substrate cross-context PRESENCE via NEW context-gated
mediation form.

### i18n bounded context — CLOSURE STATUS

| chunk | dimension | constitutional contribution |
|---|---|---|
| gettext-functions | continuity (PHP) | Constitution v1 portability test PASSED |
| script-translations | continuity (cross-runtime) | runtime boundary as continuity class; Bridge Pattern surfaced |
| locale-switching | governance | Mediation cross-context PRESENCE; bidimensional bounded context character |

**i18n bounded context status: CLOSURE READY.**
Recommended for declaration as **substantively closed** in
next consolidation document update.

### Anticipated next chunks (priority)

1. **`admin-ui.notices`** — admin-ui depth completion +
   potential 3rd-instance Mediation manifestation (intra-
   context); critical for Mediation candidate promotion path.

2. **`plugin-dev.nonces`** — security trio completion +
   potential Mediation parallel.

3. **`data-layer.entity-resolution`** — Q9 retro candidate
   from this chunk (switch_to_blog parallel; entity context
   mediation).

4. **`interactivity.directive-protocol` Q9 retro** — Bridge
   Pattern asymmetry verification (dynamic re-dispatch).

5. **`block-authoring.registration` Q9 retro** — Bridge
   Pattern + auto-bridging mechanism verification.

Recommended: **`admin-ui.notices`** (advance Mediation
candidate toward Recurring (cross-context) promotion;
admin-ui 3rd Mediation instance + cross-context test for
notice-style mediation patterns).
