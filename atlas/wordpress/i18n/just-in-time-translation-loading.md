---
rule_id: i18n.just-in-time-translation-loading
domain: i18n
topic: semantic-availability-lifecycle
field_cluster: textdomain-load-substrate
wp_min: "4.6"
wp_recommended: "6.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/load_plugin_textdomain/
    section: "load_plugin_textdomain() — explicit registration; behavior since WP 4.6"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/_load_textdomain_just_in_time/
    section: "_load_textdomain_just_in_time() — JIT mechanism reference"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/is_textdomain_loaded/
    section: "is_textdomain_loaded() — load-state query surface"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/unload_textdomain/
    section: "unload_textdomain() — explicit unload + WP 6.1+ reloadable flag"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2024/02/27/i18n-improvements-6-5/
    section: "WP 6.5 — performance improvements + .l10n.php format"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/
    section: "WP 4.6 — original JIT loading rationale"
    captured: 2026-05-10
related:
  - i18n.gettext-functions                          # the gettext call that triggers activation
  - i18n.script-translations                        # JS-side parallel; wp_set_script_translations registration
  - i18n.locale-switching                           # switch_to_locale interaction with load state
  - data-layer.resolver-lifecycle                   # parallel uncertainty mechanism in a different terrain (state, not semantics)
  - plugin-dev.security-boundaries                  # capability checks happen elsewhere; not in this lifecycle
---

# RULE — just-in-time translation loading — when an available textdomain becomes activated

## WHEN

You are reasoning about *when* translation strings for a
plugin / theme / core textdomain become available in
memory, *who* triggers the load, and *what* happens when
the load fails. Use this knowledge when:

- Diagnosing why `__( 'string', 'mydomain' )` returns the
  English source even though a translation `.mo` exists.
- Choosing whether to call `load_plugin_textdomain()`
  explicitly or rely on JIT.
- Reading WP 6.5+ performance discussions about the
  `.l10n.php` format and how it changed loading
  behavior.
- Switching locale within a request (`switch_to_locale()`)
  and needing to understand which textdomains get
  reloaded.
- Implementing conditional behavior based on whether a
  textdomain has been loaded (rare, but legitimate).

This chunk does **not** cover:

- The gettext function family itself (`__()`, `_e()`,
  `_x()`, `_n()`, `esc_html__()`, etc.) — covered in
  `i18n.gettext-functions`.
- Script translation registration via
  `wp_set_script_translations` and the JS-side load
  flow — covered in `i18n.script-translations`.
- The `switch_to_locale()` mechanics in detail — covered
  in `i18n.locale-switching`.
- POT / PO / MO / JSON / `.l10n.php` *file format*
  details — this chunk only references the formats as
  inputs to the load mechanism.

The principle this chunk operates under: **A textdomain
moves through two lifecycle phases — *declared as
available* and *loaded as activated*. The two are
distinct, separated in time, and triggered by different
things.**

## SHAPE

### A. The two-phase lifecycle

For any given textdomain on a request:

```
Phase 1 — DECLARATION                 Phase 2 — ACTIVATION
─────────────────────                 ────────────────────
Either:                               First call to __( …, $domain )
  load_plugin_textdomain(…)           with $domain unloaded triggers:
  load_theme_textdomain(…)              _load_textdomain_just_in_time($domain)
  load_default_textdomain()
or implicit (WP.org-hosted plugin/    Catalog file is read; translations
 theme; conventional path lookup)     are merged into the in-memory
                                      MO_files registry.

Effect:                               Effect:
  Domain registered as JIT-loadable.    is_textdomain_loaded($domain) = true
  No file reads yet.                    Subsequent gettext calls hit memory.
  is_textdomain_loaded() = false        No further file reads for this
                                        (domain, locale) pair until
                                        locale switch or unload.
```

Two properties of this split worth pinning:

- **Declaration creates *capability*; activation creates
  *availability*.** A declared-but-not-yet-loaded
  textdomain is *prepared to be loaded* — it knows
  where to find its catalog. It does not have any
  translations in memory yet.
- **Activation is implicit and lazy.** Almost no code
  ever calls the activation function directly. It fires
  as a side effect of a gettext call against an
  unloaded domain. The mental model is "translations
  load themselves the first time they're asked for."

The asymmetry between phases is the central
characteristic of the mechanism. Treating them as one
phase ("the textdomain is loaded") is the most common
source of confusion in this terrain.

### B. Historical explicit loading

Before WP 4.6, plugins and themes were expected to load
their textdomains explicitly:

```php
// In a plugin's main file:
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain(
        'my-plugin',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
} );

// In a theme's functions.php:
add_action( 'after_setup_theme', function() {
    load_theme_textdomain(
        'my-theme',
        get_template_directory() . '/languages'
    );
} );
```

This was *eager*: the catalog file was read at hook
fire time, whether or not any string was ever
requested. On a small site with a few plugins this was
fine; on a site with 30+ plugins each loading a
catalog at `plugins_loaded`, the overhead was visible.

After WP 4.6, calling `load_plugin_textdomain()` no
longer eagerly loads in most cases. It registers the
path-for-domain mapping and lets JIT loading take over.
The function call still serves a purpose:

- It tells WordPress *where* the textdomain's catalog
  lives, in case the path doesn't follow conventions.
- It opts a non-WordPress.org-hosted plugin into JIT
  loading.
- It remains the canonical declaration call (any
  documentation example still shows it).

The shape is "still call it; it just doesn't do what
it used to."

### C. The WP 6.5 update — `.l10n.php` and tighter JIT

WP 6.5 introduced a new translation file format,
`.l10n.php`, and tightened the JIT pathway around it.
The changes that matter for this chunk:

- **PHP-based catalog files.** `.l10n.php` files are
  pre-computed PHP arrays (`return [ 'msgid' => 'msgstr', … ];`)
  rather than `.mo` binary blobs. Loading them is
  cheaper because PHP can `opcache` them like any
  other PHP file. Translation infrastructure
  (translate.wordpress.org) generates `.l10n.php`
  alongside `.mo` for hosted plugins/themes.
- **Loader chooses the cheapest available format.**
  When a JIT load runs, the loader checks for
  `.l10n.php` first, then `.mo`, then `.json` (for
  script translations). The first available is used.
- **No API change for plugins.** `load_plugin_textdomain`,
  `__()`, `is_textdomain_loaded()` — none of the
  public API changed. The performance optimization is
  internal to the load step.

The user-visible consequence is that on WP 6.5+ sites
with translation infrastructure that ships `.l10n.php`
files, the JIT load is meaningfully faster than the
pre-6.5 `.mo`-only path. The lifecycle remains
identical.

### D. The first gettext call as activation trigger

The activation step is invisible from a caller's
perspective:

```php
// In some template or function called during the request:
echo esc_html__( 'Welcome', 'my-plugin' );
//   │
//   ▼
// Internally:
//   1. translate( 'Welcome', 'my-plugin' )
//   2. get_translations_for_domain( 'my-plugin' )
//   3. If domain not loaded:
//        _load_textdomain_just_in_time( 'my-plugin' )
//        which reads the catalog file (.l10n.php / .mo / .json)
//        and registers translations in memory.
//   4. Look up 'Welcome' in the loaded catalog.
//   5. Return the translated string (or 'Welcome' if no match).
```

After step 3, the domain is loaded for the rest of
the request (or until `unload_textdomain` is called).
Subsequent gettext calls against the same domain do
not re-trigger JIT.

The activation point is fully implicit. Code does not
need to check whether the load happened, ask for it,
or wait for it. This is *unlike* the
`@wordpress/data` resolver lifecycle (covered in
`data-layer.resolver-lifecycle`), where the consumer
explicitly queries `hasFinishedResolution` and may
need to wait for completion. Translation activation
is *blocking and synchronous within the request* —
the gettext call returns the looked-up string with
the catalog already in memory.

### E. Load-state surfaces — `is_textdomain_loaded` and `unload_textdomain`

Two queryable / mutable surfaces around the load
state:

**`is_textdomain_loaded( $domain ): bool`**

Returns whether activation has happened for the given
domain in the current request. Almost all application
code can ignore this — gettext calls handle their own
activation. Legitimate uses:

- Logging / diagnostics: "this domain hasn't loaded
  yet; that's why translations are missing in this
  email."
- Conditional pre-loading at a known-safe point if
  there's a specific reason to control timing.
- Testing fixtures.

**`unload_textdomain( $domain, $reloadable = false ): bool`**

Removes the in-memory translations for the domain.
After unload, `is_textdomain_loaded` returns `false`;
the next gettext call against the domain triggers JIT
again. The `$reloadable` flag (WP 6.1+) controls
whether the path-for-domain registration is also
removed:

- `$reloadable = false` (default): unload everything,
  including registration. Subsequent JIT can only
  succeed if the domain is re-declared.
- `$reloadable = true`: unload the catalog but keep
  the registration. The next JIT load uses the same
  registered path with whatever the current locale is.

The reloadable form is what `switch_to_locale()` uses
internally (Section F). It is the right shape when the
*locale* needs to change but the *domain registration*
is still valid.

### F. Locale switch interactions

Within a single request, `switch_to_locale( $locale )`
changes the active locale. Loaded textdomains held
catalogs for the *previous* locale; after the switch
they are stale. The interaction:

```
1. switch_to_locale( 'fr_FR' )
        │
        ▼
2. WordPress unloads currently-loaded textdomains
   (using unload_textdomain( $domain, $reloadable = true ))
   so the next gettext call re-JITs against the new locale.
        │
        ▼
3. Locale change applied. Subsequent gettext calls
   look up paths for the French catalogs and load them
   on first request.
        │
        ▼
4. restore_previous_locale() reverses the change;
   the loaded catalogs are again invalidated and the
   prior-locale catalogs JIT on first call.
```

Two practical implications:

- **The first gettext call after a locale switch
  pays the JIT load cost again.** Locale-switched
  rendering is meaningfully slower per textdomain
  than non-switched rendering.
- **Code between switch and restore must be
  self-contained translation-wise.** Don't
  cache the result of `__()` in a variable across a
  switch and expect it to reflect the right locale —
  the call needs to happen in the switched scope.

### G. The failure path — missing catalog as silent pass-through

If JIT loading runs and finds no catalog file (no
`.l10n.php`, no `.mo`, no `.json` for the
domain/locale combination), the load *silently* fails:

- No error.
- No warning.
- No exception.
- `is_textdomain_loaded( $domain )` may briefly become
  true (with an empty catalog) or remain false; behavior
  here is not part of the public contract.
- The gettext call returns the **original source
  string** as if no translation had been requested.

The design rationale is non-negotiable: i18n must
*never* break rendering. A missing French translation
for a single string should not produce a fatal error
on a French-locale page; the page should render with
the English fallback for that string.

Two consequences:

- **Diagnostic feedback for missing translations is
  not the gettext system's job.** Tooling that reports
  "this string is untranslated" must use external
  inspection of the catalog files, not runtime
  detection.
- **A wrong textdomain name is indistinguishable from
  a missing translation.** `__( 'Hello', 'mt-plugin' )`
  (typo for `'my-plugin'`) silently returns `'Hello'`.
  Linting against the registered domains is the
  way to catch this; runtime won't.

## WHY

### Why split declaration from activation

The split exists for a single dominant reason:
*almost all sites do not need almost all of their
textdomains.* A typical request renders maybe one
admin page or one frontend template; it touches a
small subset of the installed plugins' translation
strings. Eagerly loading every plugin's catalog at
`plugins_loaded` would mean reading dozens of files
on every request, most of whose contents are never
queried.

JIT loading aligns load cost with use: a plugin whose
strings are never asked about pays nothing. A plugin
whose strings are asked about loads its catalog
exactly once.

The cost of the split is conceptual — developers must
hold the two phases distinct. The benefit is large:
load work scales with what the request actually needs,
not with what is installed.

### Why the activation function is private

`_load_textdomain_just_in_time()` (note the leading
underscore) is documented but conventionally private.
The reason: callers should not need to know it
exists. The mechanism is supposed to be invisible
behind `__()`. Exposing it as a first-class API would
invite "load this textdomain for me explicitly"
patterns, which would re-create the eager-load
problem the JIT solved.

The private naming is a soft contract: WordPress
reserves the right to change the function's
signature, internal behavior, or even its existence
(if a better mechanism replaces it) without
considering it a backwards-incompatible change.

### Why missing catalogs fail silently

Translation is *substitution infrastructure*, not
*assertion infrastructure*. The contract a gettext
call makes is "if a translation exists, return it;
otherwise return the source." This contract fits the
domain: human language is forgiving of mixed-locale
text in a way that, say, type systems are not. A
French page with one English-language fallback string
is still a usable French page.

A failure mode that threw on missing translations
would force every plugin to ship a translation for
every string in every locale to avoid breaking
sites — which is impossible. The silent-fallback
design accepts incomplete translation as a normal
operating state.

## WHEN NOT

Skip JIT-aware reasoning if:

- You are working **entirely with the JS-side
  translation pathway**. JS translations (loaded via
  `wp_set_script_translations` + JSON catalogs) have
  their own loading model, covered in
  `i18n.script-translations`. The PHP JIT mechanism
  doesn't apply.
- You are working with the **default textdomain**
  (core itself). Core's textdomain handling is
  internal and not configured by application code;
  the lifecycle described here is about
  plugin/theme/non-core textdomains.
- The domain has been **manually pre-loaded** for a
  specific reason (e.g., translating strings inside
  an early-firing hook before any normal gettext call
  would have triggered JIT). In that case the
  declaration *was* the activation, and the lifecycle
  collapsed.

## COUNTER-PATTERNS

### Anti-pattern 1 — Translating strings before declaration is possible

```php
// In a plugin's main file, top level:
$greeting = __( 'Hello', 'my-plugin' );
// Plugin's load_plugin_textdomain hasn't fired yet.
// Even with JIT, the conventional path lookup may not
// resolve. $greeting is likely the source string.
```

Translation calls should happen inside hooks that
fire after `plugins_loaded` (or `after_setup_theme`
for themes). Top-level translation calls in plugin
files are a frequent source of "translations not
working" reports.

### Anti-pattern 2 — Calling `_load_textdomain_just_in_time` directly

```php
_load_textdomain_just_in_time( 'my-plugin' );  // private API
```

The function is private. Use `__( '', 'my-plugin' )`
to trigger the same load through the public surface
if you specifically need pre-loading at a known
point. Better still, restructure so the natural
gettext call timing handles it.

### Anti-pattern 3 — Caching translated strings across locale switches

```php
$cached_label = __( 'Save', 'my-plugin' );

switch_to_locale( 'fr_FR' );
echo esc_html( $cached_label );  // Still 'Save', not 'Enregistrer'.
restore_previous_locale();
```

The `__()` call returned a string at the original
locale and stored it. The locale switch doesn't
mutate previously-returned strings. If output should
follow locale, perform the gettext call inside the
switched scope.

### Anti-pattern 4 — Treating `is_textdomain_loaded` as a precondition

```php
if ( is_textdomain_loaded( 'my-plugin' ) ) {
    echo esc_html__( 'Hello', 'my-plugin' );
} else {
    echo 'Hello';
}
```

The gettext call would handle this case itself — if
not loaded, JIT loads; if no catalog, falls back to
source. The conditional adds no value and obscures
intent. Just call `__()`.

### Anti-pattern 5 — Eager `load_plugin_textdomain` without need

```php
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'my-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );
```

For plugins hosted on WordPress.org, conventional
paths are detected and JIT works without any
explicit `load_plugin_textdomain` call. The above is
not wrong, but it is also not necessary. Keep it if
your plugin ships custom languages directories that
don't follow conventions, or if you target very old
WordPress versions; remove it if neither applies.

### Anti-pattern 6 — Catching missing-translation as an error condition

```php
$translation = __( 'Welcome', 'my-plugin' );
if ( $translation === 'Welcome' ) {
    error_log( 'Translation missing!' );
}
```

The source-equals-result check is unreliable: a
translator may legitimately have decided to keep
'Welcome' untranslated for a particular locale. Use
external tooling (`wp i18n`, translation-checker
plugins) for translation-completeness reporting.

## OPERATIONAL NOTES

The lifecycle's interpretive shape, in proportional
v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the central
  fit, in a refined two-phase form. The textdomain is
  *declared* (registered as JIT-loadable) at one
  moment; its translations are *exposed* (loaded
  into memory) at a separate, later moment driven by
  the first gettext call. Naming Law 1 here is
  genuinely clarifying because the *temporal
  separation* between the two phases is exactly what
  the mechanism exists to enable. The framing
  "declared meaning is not loaded meaning" captures
  the lifecycle in a sentence.
- **Doctrine 5 (Authority Continuity)** applies
  *strongly* in a semantic reading. The textdomain
  name is the continuity surface — it persists as the
  identifier across explicit declaration, JIT load,
  locale switch, unload, and re-load. The same domain
  name resolves to different concrete catalog files
  in different locales while still meaning *the same
  source-string vocabulary*. This is *semantic
  continuity*, distinct from *runtime continuity*
  (Doctrine 5's most common framing) and from
  *registry continuity* (the recurring distinction
  pinned in `data-layer.wp-data-registry`).
- **Federation** appears lightly: each plugin / theme
  / core component declares its own textdomain; all
  federate around the singleton translation registry.
  Same federation shape pinned in plugin-dev,
  `wp-scripts`, and `wp-data-registry`. Worth one
  cross-reference; not re-elaborated.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** The most
  important non-fit to name precisely. "Load when
  needed" sounds arbitration-flavored, but there are
  no candidates. There is one textdomain → one
  catalog path → at most three format files
  (`.l10n.php`, `.mo`, `.json`) tried in a fixed
  order purely as a cost-optimization, not as a
  semantic ladder. The "is loaded?" check is a
  Boolean cache lookup. Naming Law 4 here would be a
  category error of the same family the resolver
  lifecycle chunk warned about — *word overlap*
  (resolution / loading / lookup) does not imply
  pattern fit. JIT is *availability fulfillment*,
  not *option arbitration*.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** No runtime boundary is crossed. The
  load happens in the same PHP runtime as the
  gettext call. The catalog files are static disk
  reads, not runtime-context handoffs. No authority
  is being preserved across a boundary. Same shape
  family as the build → runtime artifact handoffs
  named non-fit elsewhere; same resolution.
- **Law 6 (Compiler ↔ Runtime Split).** There is no
  compiler / runtime split inside this lifecycle.
  Catalog *generation* (POT → PO → MO / `.l10n.php`)
  is build-pipeline territory; that pipeline is
  upstream and not part of this chunk's mechanism.
  Omitted.
- **Doctrine 6 (Authority Mediation).** No access
  mediation. The gettext substitution pipeline is
  not capability-checked. Omitted.
- **Section X archetypes.** A textdomain load
  lifecycle is not a "civilization." The same
  framework-omission discipline applied to
  resolver-lifecycle and the build-tooling chunks
  applies here. Omitted.

A small literacy contribution worth pinning, parallel
to but distinct from "need fulfillment ≠ option
arbitration":

> *Availability ≠ activation.* A capability that has
> been *registered as loadable* is not the same as a
> capability that has been *loaded into memory*.
> Translation infrastructure makes this split
> first-class: declaration creates loadability;
> activation creates availability of the actual
> values. The two phases are temporally distinct,
> triggered by different events, and observably
> separable through `is_textdomain_loaded`.

This pairs naturally with the resolver lifecycle's
contribution. Both are mechanisms for managing
*uncertainty about availability* — but they manage
different kinds of availability. The resolver
manages availability of *fetched data*, mutable and
authoritative. JIT manages availability of *static
substitution catalogs*, immutable and reference-only.
**Unresolved state is not the same problem as
untranslated semantics.** Both have lifecycles; the
lifecycles differ in shape because the underlying
substrates differ in nature.

## CHECKLIST

When working with textdomain loading:

- [ ] Don't translate at top level of plugin / theme
      files. Wrap gettext calls in hooks that fire
      at or after `plugins_loaded` /
      `after_setup_theme`.
- [ ] If the plugin is hosted on WordPress.org and
      ships translations via the official
      infrastructure, you can usually omit
      `load_plugin_textdomain` entirely.
- [ ] If you ship a custom languages directory that
      doesn't follow conventions, keep
      `load_plugin_textdomain` to register the path.
- [ ] Don't call `_load_textdomain_just_in_time`
      directly. Use `__('', $domain)` to force a load
      through the public surface if there's a
      legitimate reason.
- [ ] Re-call `__()` inside switched-locale scopes;
      don't cache translated strings across switches.
- [ ] Treat missing-translation cases as silent
      fallback, not as errors. Use external tooling
      for translation-completeness reporting.
- [ ] Don't gate translation calls on
      `is_textdomain_loaded`. The gettext call
      handles loading itself.

## REFERENCES

- `load_plugin_textdomain()` — function reference.
  Documents path resolution and the registration
  semantics that JIT consumes.
  https://developer.wordpress.org/reference/functions/load_plugin_textdomain/
- `_load_textdomain_just_in_time()` — JIT mechanism
  reference. Marked private; documented for
  transparency.
  https://developer.wordpress.org/reference/functions/_load_textdomain_just_in_time/
- `is_textdomain_loaded()` — load-state query.
  https://developer.wordpress.org/reference/functions/is_textdomain_loaded/
- `unload_textdomain()` — explicit unload, including
  the `$reloadable` flag (WP 6.1+) used by
  `switch_to_locale()`.
  https://developer.wordpress.org/reference/functions/unload_textdomain/
- WP 6.5 i18n improvements — describes the
  `.l10n.php` format and the loader changes that
  tightened JIT.
  https://make.wordpress.org/core/2024/02/27/i18n-improvements-6-5/
- WP 4.6 i18n improvements — original JIT loading
  rationale.
  https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/

Cross-context:

- `i18n.gettext-functions` — the gettext call family
  whose first invocation triggers activation.
- `i18n.script-translations` — the JS-side
  translation pathway. Different runtime, different
  load model; do not conflate.
- `i18n.locale-switching` — `switch_to_locale()` and
  the unload + reload interaction described in
  Section F.
- `data-layer.resolver-lifecycle` — parallel
  uncertainty mechanism in a different terrain.
  Compare and contrast: both manage availability,
  but for different kinds of substrate (mutable
  state vs static substitution catalog).
