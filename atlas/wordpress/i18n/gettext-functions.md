---
rule_id: i18n.gettext-functions
domain: i18n
topic: semantic-continuity
field_cluster: translation-runtime
wp_min: "1.0"
wp_recommended: "5.0+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/apis/internationalization/
    section: "Internationalization — gettext functions overview"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/themes/functionality/internationalization/
    section: "Theme i18n — translation functions + textdomain loading"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/cli/commands/i18n/make-pot/
    section: "wp i18n make-pot — POT extraction tooling"
    captured: 2026-05-09
related:
  - plugin-dev.security-boundaries                  # esc_*__ functions bridge i18n + security (HTML primacy)
  - block.dynamic-rendering                         # render_callback output uses translation functions
  - data-layer.persistence                          # translation files persist via locale loading
  - (planned) i18n.script-translations              # JS-side semantic continuity (cross-runtime bridge)
  - (planned) i18n.locale-switching                 # context authority continuity within request
  - _meta.structural-patterns                       # Phase 7.5/7.6/7.7 patched spec; KB Constitution v1 portability test
  - _meta.kb-consolidation-phase7-8                 # Constitution v1 reference
---

# RULE — gettext functions — semantic continuity through domain-scoped translation authority

## WHEN

A plugin or theme needs to internationalize user-facing strings
— display translatable text in the editor, admin pages,
frontend output, error messages, labels — using WordPress's
gettext-based translation API.

Use gettext functions when:
- Outputting user-facing text that must support locale-specific
  translations.
- Text appears in plurals (1 item / 2 items / etc.).
- Same source string needs different translations in different
  contexts (e.g., "Order" as noun vs verb).
- Output requires HTML/attribute escaping in addition to
  translation.

This is the **first chunk in i18n bounded context** AND **KB
Constitution v1 portability test** — first chunk authored to
verify whether KB's constitutional framework (developed
predominantly through governance-heavy bounded contexts) ports
cleanly to a **semantic substrate** domain.

The doctrinal backbone for i18n (established here):

> **WordPress internationalization is not string substitution.**
> **It is semantic continuity architecture through compile-time**
> **extraction, domain-scoped translation governance, and**
> **runtime locale resolution.**

Constitutional question this chunk answers:

> Is translation merely substitution, or semantic authority
> continuity?

**Verdict (per chunk's findings)**: Semantic authority
continuity. KB Constitution v1 ports successfully into semantic
substrate.

## SHAPE

### A. Translation function family (full taxonomy)

```php
// Basic translation
__( $text, $domain )                      // translate, return
_e( $text, $domain )                      // translate, echo

// Context-disambiguated
_x( $text, $context, $domain )            // translate w/ context, return
_ex( $text, $context, $domain )           // translate w/ context, echo

// Plural forms
_n( $singular, $plural, $number, $domain ) // translate plural, return
_nx( $singular, $plural, $number, $context, $domain ) // plural + context

// Late-translation registration
_n_noop( $singular, $plural, $domain )    // register for later translation
_nx_noop( $singular, $plural, $context, $domain ) // noop + context
translate_nooped_plural( $nooped, $count, $domain ) // translate noop later

// Translation + HTML escaping
esc_html__( $text, $domain )              // translate + esc_html
esc_html_e( $text, $domain )              // translate + esc_html + echo
esc_html_x( $text, $context, $domain )    // translate + esc_html + context

// Translation + attribute escaping
esc_attr__( $text, $domain )              // translate + esc_attr
esc_attr_e( $text, $domain )              // translate + esc_attr + echo
esc_attr_x( $text, $context, $domain )    // translate + esc_attr + context
```

| function class | role |
|---|---|
| `__` / `_e` | basic translation (return / echo) |
| `_x` / `_ex` | context-disambiguated translation |
| `_n` / `_nx` | plural-aware translation |
| `_*_noop` / `translate_nooped_plural` | late-binding plural registration |
| `esc_html_*` / `esc_attr_*` | translation + output context escaping |

### B. Translation pipeline (Doctrine 5 paired operations)

The full i18n pipeline traverses **distributed compile-time
stages + integrated runtime resolution**:

```
COMPILE-TIME (Distributed across pipeline stages):

   1. Source authoring
      developer writes __('Hello', 'my-plugin')
   
   2. POT extraction
      wp i18n make-pot scans source, extracts msgids
      Output: my-plugin.pot file (translation template)
   
   3. PO authoring
      Translators provide msgstr per locale (GlotPress / Poedit)
      Output: my-plugin-{locale}.po per language
   
   4. MO compilation
      msgfmt or wp i18n make-mo compiles PO → binary MO
      Output: my-plugin-{locale}.mo
   
   5. Locale loading (runtime trigger)
      load_plugin_textdomain('my-plugin', false, '/languages')
      WP loads MO file matching current locale

RUNTIME (Integrated within gettext lookup):

   6. Translation function call
      __('Hello', 'my-plugin') invokes lookup
   
   7. Hash table lookup
      Locate msgid in loaded MO; resolve to msgstr
      Apply context + plural rules per Plural-Forms header
   
   8. Fallback resolution
      If no translation found: return msgid (original string)
   
   9. Output
      Returned/echoed translated string
      (Optionally escaped if esc_html_* / esc_attr_* variant)
```

**Architectural variant evaluation (Doctrine 5b)**:

> **Hybridized architecture.**
> - **Distributed compile-time pipeline** (POT extraction →
>   PO authoring → MO compilation → locale loading)
> - **Integrated runtime resolution** (gettext lookup +
>   context + plural + fallback within single function call)

This is **4th Hybridized instance documented in KB** (after
block patterns + variations + transforms).

### C. Domain scoping — semantic namespace governance

```php
// Plugin loads its text domain
add_action( 'init', function () {
    load_plugin_textdomain(
        'my-plugin',                       // domain (must match function calls)
        false,                             // deprecated
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
} );

// Theme equivalent
load_theme_textdomain( 'my-theme', get_template_directory() . '/languages' );
```

| concept | role |
|---|---|
| **text domain** | semantic namespace prevents translation collisions across plugins/themes |
| **load_plugin_textdomain** | binds domain to MO files at specific filesystem location |
| **default domain** | core WP strings use 'default' domain (built-in) |
| **domain in function calls** | last parameter of __ / _e / _x / etc.; MUST match loaded domain |

Domain scoping is **semantic federation governance** —
multiple plugins/themes contribute translations to single
runtime; domain prevents collisions.

### D. Context disambiguation

```php
// Same msgid, different translations needed per usage
echo _x( 'Order', 'noun: customer order', 'my-plugin' );
echo _x( 'Order', 'verb: place an order', 'my-plugin' );

// Translators see context; produce different msgstr per usage
// In Korean PO file:
//   msgctxt "noun: customer order"
//   msgid "Order"
//   msgstr "주문"
//
//   msgctxt "verb: place an order"  
//   msgid "Order"
//   msgstr "주문하기"
```

Context is **arbitration metadata** — same msgid + different
context = different translation candidate qualifies for
selection.

### E. HTML/attribute escaping integration

```php
// Translation + HTML context escape
echo esc_html__( '<strong>Important:</strong> read this', 'my-plugin' );
// HTML tags rendered as text (not parsed); text translatable

// Translation + attribute context escape
?>
<input title="<?php echo esc_attr__( 'Click here', 'my-plugin' ); ?>" />
<?php

// Translation + URL context escape
echo esc_url( __( 'https://example.com/help', 'my-plugin' ) );
```

Translation + escaping integration honors **Law 2 (HTML
Primacy)** — translation output enters HTML context; escaping
prevents XSS in translated content.

The composite functions (`esc_html__`, `esc_attr__`, etc.)
exist BECAUSE translation + escaping are typically paired.
Translation alone without escaping is XSS surface; escaping
alone without translation breaks i18n.

### F. Failure surfaces — translation debt

```
Translation failure modes:

- Missing text domain in function call → string not translatable
- Domain mismatch between function call and load → translation
  not found
- Late textdomain loading (after string output) → first uses
  return msgid fallback
- POT extraction misses dynamic strings (variables) → silently
  untranslatable
- Plural-Forms header mismatch with locale conventions →
  incorrect plural selection
- Missing context for ambiguous msgid → translators guess wrong
- Translation file path incorrect → silent fallback to original
- Plugin/theme deactivation → translation files orphaned
- Locale switch mid-request → translation cache stale
- Hardcoded strings bypass i18n → not translatable
```

**Translation debt** (8th debt-pattern instance in KB; first
in semantic substrate):

| debt mode | symptom |
|---|---|
| domain mismatch | translations silently not applied |
| late textdomain loading | first-use fallback to msgid |
| dynamic string extraction failure | strings invisible to POT scanner |
| context omission | translator ambiguity → wrong translations |
| plural form mismatch | incorrect singular/plural selection |
| hardcoded strings | i18n bypass |

8 instances × 5 bounded contexts. "Governance debt" continues
strengthening as anticipated meta-pattern (now spans semantic
substrate).

## REQUIRES

- WP environment (gettext functions are WP core, available
  always).
- Text domain MUST match between function calls + load_*_textdomain
  calls.
- For plural forms: locale's Plural-Forms header must be
  correct; varies per language.
- For dynamic strings: variables in __() calls MUST appear as
  literal-substring patterns scanner can extract.
- esc_*__ variants for output requiring HTML/attribute
  escaping.
- ⚠ Specific behaviors: plural form complexity per locale
  (Arabic 6 forms, Russian 3 forms, etc.), late-loading
  textdomain warnings (WP 6.7+), translation cache invalidation
  on locale switch — verification-needed.

## INVARIANTS

### 1. Translation is semantic continuity, NOT string substitution

The load-bearing reframing for this chunk + KB Constitution
v1 portability test:

> Translation is NOT "replace string A with string B based on
> locale." It is **semantic continuity** — the source string's
> SEMANTIC IDENTITY (meaning + context + domain + plurality)
> persists across compile-time extraction → translation
> authoring → runtime resolution → output rendering.

Reading translation as "lookup + substitute" misses the
semantic continuity ontology. Translation preserves SEMANTIC
AUTHORITY across boundaries; substitution merely changes
surface bytes.

### 2. Translation pipeline is Hybridized Doctrine 5 paired operations (4th Hybridized instance in KB)

Per Doctrine 5b architectural variant evaluation:

| stage | location | character |
|---|---|---|
| Compile-time pipeline (POT → PO → MO → locale loading) | Multiple distinct mechanisms / tools / files | **Distributed** |
| Runtime lookup (gettext function execution) | Single function call (__/etc.) | **Integrated** |

This is **4th Hybridized variant documented in KB** (after
block patterns + variations + transforms).

**Important distinction from Selection from Candidates
sub-pattern**: gettext lookup is NOT user-facing inserter
selection. The Hybridized character is structurally similar
(distributed pipeline + integrated actualization) but the
sub-pattern character (Selection from Candidates per
Doctrine 5c) requires user-facing UI selection. Gettext
arbitrates + resolves DETERMINISTICALLY at runtime; no user
choice involved.

> **Hybridized variant DOES NOT imply Selection from
> Candidates sub-pattern.** Sub-patterns are CHARACTER-
> specific within variants.

### 3. Authority Continuity (Law 3) manifests definitively in i18n

This is **one of strongest Law 3 manifestations in KB**:

```
Semantic authority continuity chain:

Source code: __('Hello', 'my-plugin')
   ↓ POT extraction (continuity boundary 1)
my-plugin.pot: msgid "Hello"
   ↓ Translation authoring (continuity boundary 2)
my-plugin-ko_KR.po: msgid "Hello" / msgstr "안녕하세요"
   ↓ MO compilation (continuity boundary 3)
my-plugin-ko_KR.mo: binary lookup table
   ↓ Locale loading (continuity boundary 4)
WP runtime: domain table loaded
   ↓ Runtime lookup (continuity boundary 5)
__('Hello', 'my-plugin') returns "안녕하세요" (or "Hello" fallback)
   ↓ Output rendering (continuity boundary 6)
esc_html__() emits escaped to HTML context
```

**6 continuity boundaries × semantic authority preserved
across each.** Each boundary has failure modes; each requires
explicit reconciliation mechanism.

i18n is **arguably the cleanest Authority Continuity
manifestation in KB** — semantic identity (msgid + context +
domain) is the LITERAL continuity contract, traversing time
(compile-time → runtime) + space (filesystem → memory) +
process (developer → translator → user) boundaries.

### 4. Compiler ↔ Runtime Split (Law 6) is structurally explicit in i18n

i18n is one of WordPress's most explicit compiler/runtime
splits:

| stage | character |
|---|---|
| Compiler (POT extraction → PO → MO compilation) | Build-time / translator-time tooling |
| Linker (load_plugin_textdomain) | Request-time MO file loading |
| Runtime (gettext function execution) | Per-call lookup + resolution |

The split mirrors style-engine (compiler ↔ browser CSS engine)
and interactivity (server compiler ↔ client runtime) — same
structural pattern in semantic domain.

> **Constitutional portability evidence**: Law 6 manifests
> identically in i18n's semantic domain as in style-engine's
> visual domain and interactivity's reactive domain. Law 6
> is genuinely architecture-general, not visual/reactive
> specific.

### 5. HTML Primacy (Law 2) honored via esc_html_* / esc_attr_* integration

Translation + escaping composite functions exist BECAUSE
translation output enters HTML context. The composite forms
encode the structural recognition that translation alone is
INSUFFICIENT for safe output:

```
Translation alone:  __('<script>alert("xss")</script>', 'domain')
                    → returns translatable string;
                    → if echoed without escaping: XSS
Translation + esc:  esc_html__('<script>alert("xss")</script>', 'domain')
                    → returns translated + HTML-escaped string;
                    → safe to echo
```

**HTML Primacy (Law 2) doctrine respect**: i18n explicitly
acknowledges HTML as primary output context; provides
composite functions to ensure translated content remains
HTML-safe.

### 6. Domain scoping is semantic federation governance

Text domain is **semantic federation governance**:

```
Multiple plugins/themes registering:
   Plugin A: load_plugin_textdomain('plugin-a', ...) → translations for 'plugin-a' domain
   Plugin B: load_plugin_textdomain('plugin-b', ...) → translations for 'plugin-b' domain
   Theme: load_theme_textdomain('my-theme', ...) → translations for 'my-theme' domain
   Core: 'default' domain (built-in)
   ↓
Single runtime translation registry
Domain-scoped lookup prevents collisions
   ↓
__('Save', 'plugin-a') and __('Save', 'plugin-b') resolve INDEPENDENTLY
```

This is **federation pattern recurrence in semantic domain** —
mirrors plugin-dev's authority federation, editor-customization's
state federation, admin-ui's navigation federation.

Federation Pattern reaches **6-context manifestation**
(plugin-dev + editor-customization + admin-ui + site-building +
**i18n**). KB-Wide Federation status further reinforced.

### 7. Resolution Surface manifests in semantic substrate (5th-context PRESENCE; refusal verdict undisturbed)

> Per Phase 7.8 audit, Resolution Surface KB-Wide promotion
> was REFUSED (Doctrine 5 layer is structural home).

This chunk's evidence: gettext lookup IS Resolution character
(msgid + context + domain → translation candidates → resolved
msgstr or fallback msgid).

**5-context PRESENCE** for Resolution Surface (post-this-chunk):
- site-building (template hierarchy + block patterns —
  intra-context density)
- style-engine (cascade-aggregation, retro)
- plugin-dev (capabilities-and-roles, retro)
- block-authoring (variations + transforms, retros)
- **i18n (this chunk, semantic substrate)**

> **Resolution Surface candidate status**: Recurring
> (cross-context) — UNCHANGED (Phase 7.8 KB-Wide refusal
> stands). 5-context PRESENCE strengthens Doctrine 5 paired
> operations breadth (since Resolution is paired with
> Arbitration per Doctrine 5).

### 8. KB Constitution v1 portability test: PASSED

> **This invariant IS the KB-level finding from this chunk.**

Pre-this-chunk hypothesis: KB Constitution v1 (developed
through governance-heavy bounded contexts: plugin-dev /
editor-customization / admin-ui / site-building) MAY be
domain-biased. Test: does it port to semantic substrate?

Post-this-chunk evidence:
- Law 3 (Authority Continuity): Confirmed VERY STRONG
- Law 6 (Compiler ↔ Runtime): Confirmed VERY STRONG
- Law 2 (HTML Primacy): Confirmed STRONG
- Law 1 (Declaration ≠ Exposure): Confirmed
- Doctrine 5 (Hybridized variant): Confirmed
- Resolution Surface: Confirmed (5th-context PRESENCE)
- Federation Pattern: Confirmed (6-context recurrence)

**5 of 6 KB-Wide Laws + 1 Doctrine + Resolution Surface
candidate ALL manifest in semantic substrate.**

> **Verdict: KB Constitution v1 portability test PASSED.**
> **Constitutional laws are genuinely architectural**
> **(domain-general), NOT governance-domain artifacts.**

This is a major KB-level validation. Constitution v1 is now
demonstrably portable beyond governance/composition into
semantic continuity.

## VERIFICATION NEEDED

`status: stable`. Items requiring verification:

- Plural form complexity per locale (Arabic 6 forms, Russian
  3 forms, etc.).
- Late-loading textdomain warnings (WP 6.7+ introduced).
- Translation cache invalidation on locale switch.
- POT extraction tool variations (wp-cli vs other extractors).
- Context disambiguation behavior with empty or whitespace
  contexts.
- Performance characteristics with very large translation
  tables.
- Multisite locale handling (per-site vs network-wide).
- Behavior when MO file is corrupted or version-mismatched.
- Translation file fallback chain (locale → language → default).
- Right-to-left language handling considerations.
- Emoji / non-BMP character support in translations.

For practical decisions: empirical testing per locale +
language conventions.

## ANTIPATTERNS

- ❌ **Translation = string substitution**. Translation is
  semantic continuity; substitution is surface bytes change.
  Reading as substitution misses domain / context / plural /
  escaping ontology.
- ❌ **Hardcoded strings bypassing i18n**. Strings outside
  __() / _e() / etc. are NOT translatable; ecosystem
  invisibility.
- ❌ **Skipping text domain argument**. Without domain,
  function uses 'default' domain; plugin/theme strings won't
  be translated correctly.
- ❌ **Domain mismatch between calls and load**. If
  `__('text', 'plugin-a')` but `load_plugin_textdomain('plugin_a', ...)`
  (underscore vs hyphen), translations silently fail to load.
- ❌ **Late textdomain loading**. Loading textdomain AFTER
  first translation function call → first uses return msgid
  fallback. Load early (init action).
- ❌ **Dynamic strings in __()**. `__("Hello $name", 'domain')`
  scanner doesn't extract; string never appears in POT. Use
  printf-style: `sprintf( __('Hello %s', 'domain'), $name )`.
- ❌ **Translation without escape for HTML output**. echo
  __('text') without escaping = XSS surface for translator-
  controlled HTML. Use esc_html__().
- ❌ **Wrong escape variant for context**. esc_html__() in
  attribute = wrong escape; use esc_attr__(). Each context
  has appropriate escape function.
- ❌ **Plural without _n()**. `_n('1 item', '$count items', $count, 'domain')`
  → `_n` handles plural rules; __ + concatenation does NOT.
- ❌ **Context omission for ambiguous msgid**. Same msgid in
  different uses without _x context = translator ambiguity.
  Use _x to disambiguate.
- ❌ **Mixing translation and HTML construction**.
  `__('Click <a href="' . $url . '">here</a>', 'domain')` =
  unmaintainable. Use placeholders + sprintf separately from
  translation.

## RELATED

- `plugin-dev.security-boundaries` — esc_*__ functions bridge
  i18n + security. HTML primacy doctrine respect via
  composite escaping.
- `block.dynamic-rendering` — render_callback output uses
  translation functions; semantic continuity through PHP
  rendering.
- `data-layer.persistence` — translation files (.mo) persist
  via filesystem; locale-loading is persistence-style
  reconciliation.
- (planned) `i18n.script-translations` — JS-side semantic
  continuity (cross-runtime bridge from PHP gettext to JS
  wp.i18n).
- (planned) `i18n.locale-switching` — switch_to_locale +
  context authority continuity within request lifecycle.
- `_meta.structural-patterns` — Phase 7.5/7.6/7.7 patched
  spec applied; KB Constitution v1 portability test.
- `_meta.kb-consolidation-phase7-8` — Constitution v1
  reference (this chunk validates portability).

## META

**i18n bounded context — first chunk; KB Constitution v1
portability test deployment.**

### Phase 7.5/7.6/7.7 patched framework deployment

Per established post-Phase-7.5+ chunk pattern:

1. ✅ **Patched verdict taxonomy deployed** (5-class).
2. ✅ **Patched maturity ladder applied** (5-tier).
3. ✅ **Q8 adjudication doctrine operationalized**.
4. ✅ **Doctrine 5 (Arbitration ↔ Resolution Paired
   Operations) directly applied** — verdict: Hybridized
   architecture (4th Hybridized in KB).
5. ✅ **Q9 retroactive verification trigger applied**.
6. ✅ **Q10 sub-pattern emergence diagnostic applied** —
   verdict: NO new sub-pattern (semantic resolution differs
   from Selection from Candidates).

### Doctrinal backbone established

> **WordPress internationalization is not string substitution.**
> **It is semantic continuity architecture through compile-time**
> **extraction, domain-scoped translation governance, and**
> **runtime locale resolution.**

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 3 — Authority Continuity** | VERY STRONG | 6-boundary semantic authority continuity chain (source → POT → PO → MO → locale → runtime) | **Confirmed (one of strongest manifestations in KB)** |
| **Law 6 — Compiler ↔ Runtime Split** | VERY STRONG | Compile-time pipeline (POT/PO/MO) + locale loading linker + runtime gettext lookup | **Confirmed (cleanest example: explicit compiler/linker/runtime triad)** |
| **Law 2 — HTML Primacy** | STRONG | esc_html__/esc_attr__/esc_*_x/esc_*_e composite functions; translation + escaping integration | **Confirmed (HTML primacy explicitly recognized in API design)** |
| **Law 1 — Declaration ≠ Exposure** | Moderate | text domain registration ≠ translation file presence ≠ runtime applicability | **Confirmed (3-form: declared / loaded / matched)** |
| **Doctrine 5 — Arbitration ↔ Resolution Paired Operations** | (test) | Distributed pipeline + Integrated runtime lookup | **Confirmed (Hybridized variant; 4th Hybridized in KB)** |
| **Resolution Surface (candidate)** | Moderate-Strong | msgid + context + domain → translation candidates → resolved msgstr | **Confirmed (5th-context PRESENCE; refusal verdict from Phase 7.8 unchanged)** |
| **Authority Mediation Surface (candidate)** | Weak | gettext is not capability-gated access | **Divergent — i18n is continuity domain, not mediation** |
| **Authority Interception Surface (candidate)** | Weak | gettext doesn't intercept; it resolves | **Divergent** |
| **Administrative Routing Surface (candidate)** | Divergent | i18n has no navigation topology | **Not present** |

**Universal law manifestation: SUCCESS — major validations:**
- **Law 3 (Authority Continuity)** at one of clearest
  manifestations in KB; semantic identity continuity is
  literal contract
- **Law 6 (Compiler ↔ Runtime)** at cleanest example
  (explicit triad: compiler / linker / runtime)
- **Law 2 (HTML Primacy)** explicitly designed into API
- **Doctrine 5 Hybridized** (4th instance — pattern
  consolidation strengthened)

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | i18n manifestation | Outcome |
|---|---|---|---|
| **Resolution Surface** | Recurring (cross-context); KB-Wide REFUSED (Phase 7.8) | Strong: msgid → translation candidates → resolved msgstr (Hybridized architecture) | **Confirmed (5th-context PRESENCE; refusal verdict undisturbed; doctrine 5 breadth strengthened)** |
| **Selection from Candidates (sub-pattern)** | Recurring (cross-context, sub-pattern of Doctrine 5 Hybridized) | DIVERGENT — gettext is non-user-facing semantic candidate arbitration; deterministic key lookup, NOT user-facing inserter selection | **Divergent — sub-pattern requires user-facing UI selection; gettext lacks this character** |
| **Authority Mediation Surface** | Recurring (intra-context, admin-ui) + cross-context PRESENCE | Not present — gettext is continuity, not capability-gated access | **Not present** |
| **Authority Interception Surface** | Recurring (intra-context, editor-customization) | Not present — gettext doesn't intercept | **Not present** |
| **Federation Pattern** | KB-Wide-equivalent (5-context recurrence) | Strong: text domain federation across plugins/themes/core | **Confirmed (6-context recurrence; KB-Wide further reinforced)** |
| **Semantic substrate (potential bounded context character category)** | did not exist | i18n exhibits semantic continuity character distinct from existing 5 categories | **Observed only ("surfaced, not constitutionalized" per Phase 7.6+7.7 deferred candidates discipline)** |

### Q9 Retroactive Verification Triggered

> **Q9 ANSWER: YES — this chunk reveals semantic-domain
> manifestations of Resolution Surface that may also exist
> latently in other chunks documenting semantic operations.**

**Q9 candidates triggered**:
1. (planned) `i18n.script-translations` — JS-side semantic
   continuity (next chunk; cross-runtime bridge)
2. (planned) `i18n.locale-switching` — context authority
   continuity within request
3. (anticipated) Other semantic-substrate mechanisms (preset
   slug system / shortcode parsing / block name resolution)
   may exhibit latent semantic Resolution character

These are Q9 trigger flags; future chunks will execute retros
if needed.

### Q10 Sub-pattern Emergence (NEGATIVE finding documented)

> **Q10 ANSWER: NO new stable sub-pattern observed.**

Initial hypothesis: gettext lookup might exhibit "Selection
from Candidates" sub-pattern character.

Honest evaluation: gettext is **non-user-facing semantic
candidate arbitration** — deterministic key lookup, NOT
user-facing inserter selection. This is structurally
DIFFERENT from Selection from Candidates sub-pattern
(which requires user-facing UI selection per Doctrine 5c).

> **Refusing premature sub-pattern inflation per Phase 7.5
> Doctrine 2 + Phase 7.7 Doctrine 5c discipline.**
> Same Hybridized variant character does NOT imply same
> sub-pattern.

This is **explicit Q10 negative finding** — important
methodological discipline demonstration.

### NEW KB-level findings

**1. KB Constitution v1 portability test: PASSED**

Constitutional laws + Doctrine 5 + Resolution Surface
candidate ALL manifest in semantic substrate. Constitution
is genuinely architectural (domain-general), not
governance-domain artifact.

**2. i18n exhibits "Semantic substrate" character (observed only)**

Bounded context character taxonomy (Phase 7.6/7.7 deferred
candidate) may eventually need 6th category:

| character | bounded contexts |
|---|---|
| Schema authority | block-authoring, theme-config |
| Compiler/runtime | style-engine, interactivity |
| Authority federation | plugin-dev (external) |
| Governance modulation | editor-customization, admin-ui |
| Composition runtime | site-building |
| **Semantic substrate (NEW observation)** | **i18n** |

Status: **Observation only** (single instance; per discipline,
defer until cross-context verification).

**3. Doctrine 5 Hybridized variant strengthened (4th instance)**

| Hybridized instance | bounded context |
|---|---|
| Block patterns | site-building |
| Variations | block-authoring |
| Transforms | block-authoring |
| **Gettext (this chunk)** | **i18n** |

4 instances × 3 bounded contexts. Hybridized variant is
robustly recurring; not an edge case.

### Constitutional portability test rationale (META framing)

This chunk's strategic role per `_meta.kb-consolidation-phase7-8`
Section F:

> **i18n: framework portability test** (does Phase 7.5/7.6/7.7
> patched spec apply cleanly to non-governance-heavy domain?)

**Verdict: SUCCESS.** Phase 7.5/7.6/7.7 patched spec applied
cleanly. KB Constitution v1 is portable beyond governance
domains.

### KB-wide pattern recurrence updates

**Translation debt = 8th debt-pattern instance:**

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
| **gettext-functions** | **translation debt** | **i18n** |

Wait — re-counting: 9 instances × 5 bounded contexts.
Governance debt continues strengthening as anticipated
meta-pattern; now spans semantic substrate.

**Federation Pattern (6-context recurrence)**:
plugin-dev + editor-customization + admin-ui + site-building
+ **i18n** (text domain federation).

**Resolution Surface (5-context PRESENCE)**:
site-building + style-engine + plugin-dev + block-authoring +
**i18n**.

### KB self-evaluation against spec criteria (Phase 7.5/7.6/7.7 patched)

- ✅ Accuracy — describes documented gettext API.
- ✅ Structural fit — first i18n chunk; tests Constitution v1
  portability into semantic substrate.
- ✅ Reusability — uses authority ontology glossary +
  Phase 7.5/7.6/7.7 vocabulary (semantic continuity / Doctrine
  5 Hybridized / Resolution / federation / debt).
- ✅ Phase fit — first chunk per consolidation document
  Section F priority recommendation.
- ✅ Doctrine respect — HTML primacy explicitly invoked
  (esc_*__ functions); declaration ≠ exposure 3-form;
  Epistemic Integrity preserved (Q10 negative finding refused
  premature sub-pattern inflation).
- ✅ **Q8 explicit answer**: Resolution Confirm; Selection from
  Candidates Diverge; Mediation/Interception/Routing Diverge
  or Not present; Federation Confirm; Semantic substrate
  Surface (observation only).
- ✅ **Q9 explicit answer**: YES — i18n.script-translations +
  locale-switching + other semantic mechanisms candidate retros.
- ✅ **Q10 explicit answer**: NO new sub-pattern; gettext is
  non-user-facing arbitration (Divergent from Selection from
  Candidates).

### Status: `stable`

Gettext API is mature WordPress core (since WP 1.0+).
Verification-needed catalog covers behaviors but core API is
settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission

> **Is translation merely substitution, or semantic authority**
> **continuity?**

**Verdict: Semantic authority continuity.** WordPress i18n is
semantic continuity architecture. KB Constitution v1 ports
cleanly into semantic substrate.

### Anticipated next chunks (priority)

1. **`i18n.script-translations`** — JS-side semantic
   continuity bridge. Tests semantic substrate density within
   i18n + cross-runtime continuity (PHP gettext ↔ JS wp.i18n).

2. **`i18n.locale-switching`** — switch_to_locale +
   context authority continuity within request lifecycle.

3. **`site-building.{3rd chunk}`** — navigation menu fallback
   resolution (3rd Resolution stratification layer).

4. **`admin-ui.notices`** — admin-ui depth + Routing
   recurrence.

5. **`plugin-dev.nonces`** — security trio completion.

Recommended: **`i18n.script-translations`** (continue i18n
density build + test semantic continuity across PHP/JS
runtime boundary; potential semantic interactivity bridge).
