---
rule_id: i18n.translation-context-and-plurals
domain: i18n
topic: semantic-differentiation
field_cluster: disambiguation-and-plural-grammar
wp_min: "2.8"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/_x/
    section: "_x() — translator-context disambiguation"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/_n/
    section: "_n() — singular/plural variant selection by count"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/_nx/
    section: "_nx() — combined context + plural"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#disambiguate-by-context
    section: "Plugin handbook — disambiguation by context + plurals + translator comments"
    captured: 2026-05-10
  - url: https://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html
    section: "GNU gettext manual — Plural-Forms header + formula syntax"
    captured: 2026-05-10
  - url: https://translate.wordpress.org/projects/wp-plugins/
    section: "translate.wordpress.org — translator-facing context surface"
    captured: 2026-05-10
related:
  - i18n.gettext-functions                          # base translation API; this chunk extends with context + plural variants
  - i18n.just-in-time-translation-loading           # the load lifecycle; this chunk operates after activation
  - i18n.locale-switching                           # locale change reloads translations including plural-form definitions
---

# RULE — `_x()`, `_n()`, `_nx()` — translator context and plural form differentiation

## WHEN

You are working with translatable strings where:

- The same English source has **two or more meanings** in
  context (a noun vs verb, a label vs an action, a
  location-name vs a position-on-page, etc.) and
  translators in some languages need to render them
  differently.
- The string contains a **count** that affects grammar
  (one comment vs many comments, and in some languages
  more grammatical variants depending on the number).
- Both at once — a count-bearing string whose meaning
  also needs translator disambiguation.

Use this knowledge when:

- Authoring a new translatable string and choosing
  between `__()`, `_x()`, `_n()`, `_nx()`.
- Reviewing a `_n()` call that produces grammatically
  wrong output in non-English locales.
- Reading a POT file and trying to understand why
  apparently identical msgids appear as separate
  entries.
- Debugging a "translation works in some locales,
  not others" report involving plural strings.

This chunk does **not** cover:

- The base gettext API (`__()`, `_e()`,
  `esc_html__()`, etc.) — covered in
  `i18n.gettext-functions`.
- The textdomain *load* lifecycle — covered in
  `i18n.just-in-time-translation-loading`. This chunk
  assumes the textdomain has activated.
- The PO/MO/JSON file format internals — only the
  *header field* `Plural-Forms` is referenced here, as
  the runtime input to the formula evaluation.

The principle this chunk operates under: **A source
string is not a meaning. Disambiguation grammar
(`_x`) and plural grammar (`_n`) are how the i18n
layer lets one source surface multiple distinct
meanings without colliding.**

## SHAPE

### A. The disambiguation problem — `_x( $text, $context, $domain )`

Two valid meanings of the English word "Post":

```php
$post_action = _x( 'Post', 'verb',  'my-plugin' );  // "submit a post"
$post_object = _x( 'Post', 'noun',  'my-plugin' );  // "a post entity"
```

Both source strings are spelled "Post". Both will be
translated. In English the translation is the same
word; in many other languages the verb form and the
noun form are different words.

The `$context` argument is a translator-facing
disambiguator. At lookup time it becomes part of the
cache key:

```
Key for translation lookup:
  "{$context}\x04{$text}"
  e.g. "verb\x04Post" and "noun\x04Post"
```

The `\x04` (EOT character) separates context from
source. Two `_x()` calls with the same `$text` but
different `$context` are **independent translatable
entries** — POT file shows them as separate
`msgctxt`/`msgid` pairs, translator UIs show them as
separate items, the runtime cache stores them
independently.

Three properties to pin:

- **Context is invisible to end users.** It does not
  appear in any rendered output. Its only audience is
  the translator and the lookup mechanism.
- **Context is part of identity.** Two strings with
  the same source but different contexts are *not* the
  same translatable entry. They never collapse.
- **Wrong context = missed translation.** If a `_x()`
  call uses a context that doesn't match what the
  translator translated against, lookup misses and the
  source string is returned.

### B. The variation problem — `_n( $single, $plural, $number, $domain )`

A string with a count needs to vary by count for
grammatical correctness:

```php
printf(
    /* translators: %d is the comment count */
    _n(
        '%d comment',
        '%d comments',
        $count,
        'my-plugin'
    ),
    $count
);
```

In English, two forms suffice — one for `n == 1`,
another for everything else. In many languages this is
not enough:

- **Russian**: 3 plural forms.
  - `1, 21, 31, …` (one)
  - `2-4, 22-24, …` (few)
  - `0, 5-20, 25-30, …` (many)
- **Arabic**: 6 plural forms (zero, one, two, few,
  many, other).
- **Japanese, Chinese, Korean**: 1 plural form (no
  count-based variation).

`_n()` accepts only `$single` and `$plural` from the
PHP caller — but the *translation* in PO files can
provide as many forms as the locale's
`Plural-Forms` header declares. The runtime selects
which plural form to return based on `$number` and
the locale's plural formula (Section D / E).

For English-locale runtime, `_n()` collapses to:

```
return $number == 1 ? translated_single : translated_plural;
```

For Russian-locale runtime, with three translations
provided in PO, `_n()` evaluates the Russian plural
formula on `$number` to get an index 0/1/2 and
returns the matching translation.

The PHP source only knows about two forms; the
locale-aware runtime can reach more forms when the
translator provided them.

### C. The combination — `_nx( $single, $plural, $number, $context, $domain )`

A count-bearing string that also needs disambiguation:

```php
_nx(
    '%d view',
    '%d views',
    $count,
    'page-statistics',
    'my-plugin'
);
```

Combines the cache-key disambiguation of `_x()` with
the count-driven plural selection of `_n()`. POT
entry shows `msgctxt "page-statistics"` plus
`msgid "%d view"` plus `msgid_plural "%d views"`.
Runtime lookup keys by context + msgid, then
formula-evaluates plural index, then returns the
chosen translated form.

There are also `_ex()` (echoing `_x`),
`esc_html_x()`, `esc_attr_x()`, `_n_noop()` /
`_nx_noop()` for late-translated plural pairs, etc.
The pattern they all share: context is a key
component, plural is a runtime computation. The
extras are escaping and timing variants of the same
core mechanism.

### D. `Plural-Forms` header — locale-bound grammar declaration

A PO file's header includes:

```
"Plural-Forms: nplurals=2; plural=(n != 1);\n"        # English
"Plural-Forms: nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);\n"   # Russian
"Plural-Forms: nplurals=1; plural=0;\n"                # Japanese
"Plural-Forms: nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);\n"   # Arabic
```

Two parts:

- **`nplurals`** — how many distinct plural forms the
  locale uses. Tells translator tooling how many
  translation slots to provide per `_n()`/`_nx()`
  entry. Tells the runtime how many indices the
  formula can return.
- **`plural`** — a C-style expression on the variable
  `n` that returns the plural-form index for that
  count. Standardized in the GNU gettext manual; the
  set of formulas for known locales is itself
  effectively a small reference table.

The header is **per-locale**, not per-call site.
Every `_n()` / `_nx()` call against a given locale's
catalog uses that locale's formula. The PHP source
code declares no part of this; declaration of plural
grammar is a translator / locale-data concern.

### E. Formula evaluation — deterministic computation, not arbitration

When `_n( $single, $plural, $count, $domain )` runs
under a non-English locale at runtime:

```
1. Look up the translation entry for $single in the loaded catalog.
   The entry contains an array of N translated forms (N = nplurals).
2. Evaluate the locale's plural formula with n = $count.
   The formula returns an integer index in [0, N-1].
3. Return translations[ index ].
4. If lookup misses (no translation entry), fall back:
   $count == 1 ? $single : $plural.
```

The formula evaluation in step 2 is a single
deterministic computation. There are no candidates
being compared, no priority order being walked, no
fallback being tried after a "miss." The formula
takes a number, returns an integer; the integer is
the index. Done.

This is the most important shape distinction in this
chunk: **selection here is computation, not
arbitration.** Even though the surface vocabulary
("plural form selection," "the chosen form")
suggests options being weighed, what actually
happens is `f(n) → index`. Every plural form is
*structurally* available; the runtime simply asks
the formula which one applies for *this* count.

### F. Translator-facing metadata — comments and POT capture

Two devices help translators understand context that
isn't expressible through `$context` alone:

**Translator comments.** A `/* translators: ... */`
comment immediately before a gettext call is captured
by `make-pot` and surfaces in the PO file as `#.`:

```php
/* translators: %s is the user's display name */
printf( __( 'Welcome, %s', 'my-plugin' ), $name );
```

PO file:
```
#. translators: %s is the user's display name
msgid "Welcome, %s"
msgstr ""
```

This is unstructured text aimed at the human
translator. It does not affect lookup (no key
participation, no runtime behavior). Use it
liberally for placeholders, technical terms, brand
names, or any string whose intent isn't obvious from
the source.

**Context vs comments — when to use which.**

| Need                                                 | Use            |
| ---------------------------------------------------- | -------------- |
| Same source must produce *different* translations    | `_x()` context |
| Same source needs *clarification* for one translation | translator comment |

Context partitions the entry; comments annotate it.
They are not interchangeable.

### G. Failure modes

Three distinct failure shapes:

- **Missing translation entry.** Source string returned
  unchanged (English fallback). Same as plain `__()`.
  No error.
- **Wrong context spelling.** `_x( 'Post', 'verbs', … )`
  with a translation against context `'verb'` misses
  the cache. Source returned. Looks identical to
  "missing translation."
- **Missing plural forms in catalog.** If the
  translation provided fewer forms than the locale's
  `nplurals` requires, the runtime indexes into a
  short array and may return `null`/empty. WordPress
  then falls back to `$count == 1 ? $single : $plural`.
  Output is grammatically wrong for the locale but
  not broken.
- **Missing `Plural-Forms` header.** Catalog without
  the header is treated as if the formula were
  English's (`n != 1`). Plural-rich languages render
  incorrectly.

None of these throw. All produce visibly
suboptimal-but-functional rendering. As with
just-in-time loading (`i18n.just-in-time-translation-loading`),
i18n's design preference is silent fallback over
rendering failure.

## WHY

### Why context as a hidden key component

The alternative — making context a visible part of the
source string (`__( 'Post (verb)' )` vs `__( 'Post (noun)' )`)
— would solve disambiguation but at the cost of
polluting English output. Hidden context preserves the
clean source while still partitioning the translation
entries.

The cost is that translators *must* see context
through their tooling (Poedit, GlotPress, etc.) for
the mechanism to help them. WordPress's translator
infrastructure is built to surface context; ad-hoc
translation workflows that lose the context produce
worse translations.

### Why declarative plural forms instead of branching in PHP

A naive design would let the PHP author write:

```php
// hypothetical bad design
if ( $count == 1 ) {
    $text = __( '%d comment', 'my-plugin' );
} else {
    $text = __( '%d comments', 'my-plugin' );
}
```

This works for English. It fails for Russian (3
forms), Arabic (6 forms), Japanese (no plural at
all), because the PHP source code's branching can't
predict how many forms the runtime locale will need.

`_n()` defers the form-count decision to the
*translator* (who knows their locale) and the
*runtime* (which evaluates the formula). The PHP
source only declares that *this string varies by
count*; the rest is the i18n layer's responsibility.

### Why formula evaluation rather than per-form lookup keys

A design where each plural form has its own lookup key
(`'%d comments_one'`, `'%d comments_few'`, etc.) was
also possible. The chosen design — one entry with N
slots indexed by formula evaluation — is more compact,
matches the GNU gettext convention developers and
translators already know, and treats the formula as
*locale data* that can be updated centrally without
modifying source code.

The cost is the indirection: understanding `_n()` at
runtime requires reading the PO `Plural-Forms`
header. The benefit is that source code never needs
to change when a new locale gains support.

## WHEN NOT

Skip the context / plural variants if:

- The string has **only one meaning** in any conceivable
  context. Use `__()`. Adding context would create a
  partition no translator needs.
- The string has **no count-dependent grammar**. Use
  `__()`. `_n()` for non-counting strings is
  semantic noise.
- You are translating **a non-translatable concept**
  (a vendor name, a code identifier, a value that
  should be the same in every locale). Don't wrap it
  in any gettext function.
- You are working in **JavaScript** rather than PHP.
  `@wordpress/i18n` provides JS-side equivalents
  (`_x`, `_n`, `_nx`, `sprintf`); the same conceptual
  shape applies but the loading pathway is the
  script-translation flow
  (`i18n.script-translations`).

## COUNTER-PATTERNS

### Anti-pattern 1 — Reusing the same source for different meanings without context

```php
$noun = __( 'Post', 'my-plugin' );
$verb = __( 'Post', 'my-plugin' );
```

Both calls hit the same translation entry. The
translator must pick one rendering that fits both
uses, which is impossible in many languages. Use
`_x()` to partition:

```php
$noun = _x( 'Post', 'noun', 'my-plugin' );
$verb = _x( 'Post', 'verb', 'my-plugin' );
```

### Anti-pattern 2 — Using `_n()` without `$count` substitution

```php
echo _n( 'comment', 'comments', $count, 'my-plugin' );
// Just prints 'comment' or 'comments' with no number.
```

`_n()` returns the *form*; the count is for
*selection*, not insertion. To include the number in
output, use `sprintf` with `%d`:

```php
echo sprintf(
    _n( '%d comment', '%d comments', $count, 'my-plugin' ),
    $count
);
```

Or `number_format_i18n` for locale-aware
thousands-separators.

### Anti-pattern 3 — English-only plural-form thinking

```php
$text = sprintf(
    _n( 'You have %d new message', 'You have %d new messages', $count, 'my-plugin' ),
    $count
);
```

This is correct, but reviewers should not be
surprised when Russian translation provides three
forms while the PHP only specifies two — that's the
whole point. Trust `_n()` with the actual count;
let the catalog provide the forms.

### Anti-pattern 4 — Inventing a context that's actually a comment

```php
_x( 'Save', 'the button label in the inspector panel above the toolbar', 'my-plugin' );
```

The context is meant to be a short, stable
disambiguator (one or two words). Long natural-language
explanations belong in `/* translators: ... */`
comments. Long contexts also clutter every
translator's view of the entry.

### Anti-pattern 5 — Building plural-form formulas in source code

```php
// Wrong: trying to handle plural rules manually.
$index = ( $count % 10 == 1 && $count % 100 != 11 ) ? 0 : 1;
$forms = __( 'message|messages|messages', 'my-plugin' );
$parts = explode( '|', $forms );
echo $parts[ $index ];
```

This re-implements a piece of the i18n mechanism
incorrectly (it's English-shaped, won't work for
languages with more forms). Use `_n()` and let the
runtime + catalog handle it.

### Anti-pattern 6 — Using `_nx` to inject runtime data into context

```php
_nx( '%d item', '%d items', $count, $current_view_name, 'my-plugin' );
```

The context must be a literal string (or at least
a value known at extraction time), because POT
extraction reads the source statically. A dynamic
context produces an entry the translator can never
see in their tooling. Choose a stable string.

## OPERATIONAL NOTES

The grammar's interpretive shape, in proportional
v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the central
  fit, in a *partition-aware* form. Each `_x()` call
  *declares* a distinct translatable entry by
  contributing to the cache key; the *exposure* is
  the runtime substitution of one specific entry's
  translation. Each `_n()` call declares that a
  string varies by count; the exposure is the
  formula-driven selection of one form. Naming Law 1
  here is genuinely clarifying because the
  distinguishing trick of these APIs is that
  declaration shapes the *identity* of what gets
  exposed.
- **Doctrine 5 (Authority Continuity)** applies in
  the *grammatical* reading. The same `$context`
  string persists as the disambiguation surface
  across declaration, POT extraction, translator
  workflow, runtime cache key, and lookup. The
  same `Plural-Forms` formula persists as the
  locale's plural authority across every `_n()` call
  during the request. Continuity here is *per-locale,
  per-context grammar* surviving across phases —
  similar in shape to the textdomain-name semantic
  continuity from `i18n.just-in-time-translation-loading`,
  applied at the per-call grammar level rather than
  the per-domain level.
- **Federation** appears very lightly. Each plugin
  contributes its own translatable entries, all
  federated under the same per-locale plural formula
  (the locale's formula is shared across all
  catalogs for that locale). Mentioned for
  cross-reference; not elaborated.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *The highest-risk
  non-fit to name precisely*, on par with the
  resolver lifecycle's "resolution ≠ arbitration"
  warning. Plural form *selection* invites the
  reading "the runtime arbitrates between candidate
  plural forms." It does not. The mechanism is
  *formula evaluation*: the locale's plural formula
  is a deterministic computation `f(n) → index`,
  and that index is used directly. Every plural form
  is structurally *available*; no candidate is being
  *chosen over another*; no fallback ladder runs.
  The shape is fundamentally different from the
  template hierarchy or `locate_template` ladder
  walks where Law 4 actually applies. *Same surface
  word ("selection"), different mechanism.* Naming
  Law 4 here would dilute its meaning where it does
  apply, and would obscure the fact that plural
  selection's correctness depends entirely on the
  formula being right — not on any arbitration
  policy.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** All evaluation happens in the same PHP
  runtime as the calling code. No runtime boundary,
  no authority preservation across contexts.
  Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** Catalog
  generation (POT → PO → MO / `.l10n.php`) is
  upstream pipeline territory that this chunk does
  not enter. Omitted.
- **Doctrine 6 (Authority Mediation).** No
  capability or access mediation surface. Gettext
  substitution is open. Omitted.
- **Section X archetypes.** A pair of grammar
  helpers is not a "civilization." Same
  framework-omission discipline as the surrounding
  chunks. Omitted.

A small literacy contribution worth pinning:

> *Formula-driven selection ≠ candidate arbitration.*
> A mechanism that computes an index from inputs and
> uses that index to pick from a structurally
> available array is not the same shape as a
> mechanism that walks an ordered candidate list
> looking for the first match. Both look like
> "selection"; only the second is arbitration. The
> first is *computation directly applied*.

This pairs with the resolver lifecycle's *"need
fulfillment ≠ option arbitration"* and the JIT-loading
chunk's *"availability ≠ activation"*. Together the
three contributions form a small toolkit for
recognizing when surface vocabulary about
"resolution," "selection," "loading" tempts the
reader toward Law 4 or Law 3b in terrains where the
underlying mechanism is something else entirely.

## CHECKLIST

When choosing between `__()`, `_x()`, `_n()`, `_nx()`:

- [ ] Plain source, single meaning, no count → `__()`.
- [ ] Same source needs different translations in
      different uses → `_x()` with a short, stable
      context.
- [ ] String contains a count and grammar varies by
      count → `_n()`. Always `sprintf` the count
      back in.
- [ ] Both context disambiguation and count grammar →
      `_nx()`.
- [ ] Long explanation for translators? Use
      `/* translators: ... */` comment, not a long
      `$context`.
- [ ] Contexts are literals, never dynamic
      expressions. POT extraction is static.
- [ ] Don't rebuild plural rules in PHP. Trust
      `_n()` and let the locale's `Plural-Forms`
      handle it.
- [ ] Treat missing translations as silent fallback,
      not as error conditions (consistent with the
      i18n design across all gettext functions).

## REFERENCES

- `_x()` — function reference. Documents the
  context parameter and its lookup-key role.
  https://developer.wordpress.org/reference/functions/_x/
- `_n()` — function reference. Documents the
  count-driven plural selection.
  https://developer.wordpress.org/reference/functions/_n/
- `_nx()` — function reference. Combined context +
  plural.
  https://developer.wordpress.org/reference/functions/_nx/
- WordPress plugin handbook — internationalization
  guide, including disambiguation and plurals.
  https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#disambiguate-by-context
- GNU gettext manual — `Plural-Forms` header syntax
  and the canonical formula list per locale.
  https://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html
- translate.wordpress.org — the production
  translator-facing surface where `$context` and
  translator comments are displayed alongside
  msgids.
  https://translate.wordpress.org/projects/wp-plugins/

Cross-context:

- `i18n.gettext-functions` — base translation API.
  This chunk extends it; that one establishes the
  shared lookup mechanism and escaping family.
- `i18n.just-in-time-translation-loading` — the load
  lifecycle that makes the catalog available.
  Disambiguation and plural formulas only matter
  *after* activation has populated the catalog.
- `i18n.locale-switching` — locale change reloads
  catalogs, which reloads `Plural-Forms` headers,
  which changes the formula `_n()` evaluates.
