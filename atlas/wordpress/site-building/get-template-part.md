---
rule_id: site-building.get-template-part
domain: site-building
topic: composition-inclusion
field_cluster: template-partial-resolution
wp_min: "3.0"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/get_template_part/
    section: "get_template_part() — function reference + $args (5.5+)"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/locate_template/
    section: "locate_template() — child→parent search mechanism"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/themes/basics/template-files/#template-partials
    section: "Theme handbook — template partials"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/get_template_part/
    section: "get_template_part action hook"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/get_template_part_slug/
    section: "get_template_part_{slug} action hook"
    captured: 2026-05-10
related:
  - site-building.template-hierarchy-and-resolution     # outer hierarchy resolution; get_template_part operates inside the chosen template
  - site-building.navigation-menu-fallback-resolution   # adjacent fallback pattern (different surface)
  - site-building.block-pattern-resolution-and-precedence  # adjacent composition-resolution pattern
  - block.dynamic-rendering                              # render.php is conceptually a block-era template partial
  - theme-config.templateParts                           # block-theme equivalent declaration surface
---

# RULE — `get_template_part()` — partial template inclusion with child→parent override

## WHEN

You are inside a classic PHP template (or a hybrid theme PHP
file) and need to include a reusable template fragment with:

- Specific-then-generic name resolution
  (`content-page.php` → `content.php`).
- Child-theme override capability without modifying the parent.
- A documented variable-scope contract for the included file.
- Hookable inclusion (themes/plugins can react before the
  partial loads).

Use this chunk when:

- Authoring a classic theme that decomposes templates into
  reusable partials.
- Maintaining a hybrid theme whose PHP templates still call
  `get_template_part()` for layout fragments.
- Migrating a classic theme toward block-based template parts
  and need to know what `get_template_part()` does that
  template-part *blocks* do not (and vice versa).
- Diagnosing why a partial "isn't loading" — the cause is
  almost always a child/parent precedence misunderstanding or
  a variable-scope mistake.

This chunk does **not** cover:

- The outer template hierarchy that selects the *primary*
  template file (covered by
  `site-building.template-hierarchy-and-resolution`).
- The block-editor `core/template-part` block (covered under
  `theme-config.templateParts`).

## SHAPE

### A. Signature and the two-name resolution

```php
get_template_part( string $slug, string $name = null, array $args = [] ): void|false
```

The function returns `void` on success, `false` on failure
(WP 5.2+). It does not return the included content — the
partial is `require`'d into output position.

The two-name pattern produces a deterministic, ordered
candidate list:

```
$slug = 'content';
$name = 'page';

// Resolution candidate order (first match wins):
//   1. content-page.php   ← specific
//   2. content.php        ← generic
```

If `$name` is omitted (or `null`), only `{slug}.php` is
attempted.

The pattern is the entire "fallback ladder" for this API.
Unlike navigation-menu fallback (5 stages) or template
hierarchy (dozens of candidates), `get_template_part()`
arbitrates over **at most two filenames** in the lookup
phase. The *child→parent* dimension is layered on top of
that (next section).

### B. The locate mechanism — child-theme precedence

Internally, `get_template_part()` delegates to
`locate_template()`:

```php
// Simplified — actual core code does more bookkeeping.
$templates = [];
if ( $name !== null ) {
    $templates[] = "{$slug}-{$name}.php";
}
$templates[] = "{$slug}.php";

locate_template( $templates, true, false, $args );
```

`locate_template()` searches each candidate in this order
of directories:

1. **Child theme** — `STYLESHEETPATH` /
   `get_stylesheet_directory()`.
2. **Parent theme** — `TEMPLATEPATH` /
   `get_template_directory()`.

The first existing file wins — for each candidate, in order.
The full effective search matrix for
`get_template_part('content', 'page')` in a child-theme
context is therefore:

```
1. {child}/content-page.php
2. {parent}/content-page.php
3. {child}/content.php
4. {parent}/content.php
```

Two implications worth pinning:

- **Child more-specific beats parent less-specific.**
  If the child theme provides `content-page.php`, the
  parent's `content-page.php` *and* the parent's
  `content.php` are both bypassed.
- **A child theme can shadow a parent template by name
  alone — no registration required.** The override surface
  is the filesystem.

### C. Variable scope contract

A frequent source of bugs. The included file inherits the
*calling scope's* variables in the historical sense (because
PHP `require` runs in the caller's scope) but core has
introduced a more disciplined contract through `$args`.

**Pre-5.5 contract.** Variables defined in the calling
template file are visible inside the partial. Globals
(`$post`, `$wp_query`, etc.) are visible. There is no
formal API for passing scoped data — themes routinely
relied on `set_query_var()` / `get_query_var()` or on
ambient globals.

**5.5+ `$args` contract.** A third argument is passed to the
partial as a variable named exactly `$args`:

```php
// Caller:
get_template_part( 'content', 'card', [
    'heading' => __( 'Latest', 'mytheme' ),
    'count'   => 3,
] );

// content-card.php:
$heading = $args['heading'] ?? '';
$count   = (int) ( $args['count'] ?? 5 );
```

This is the **only** officially supported way to pass
scoped data into a partial. Two important properties:

- `$args` is *not* extracted to individual variables. Core
  refuses `extract()` here for security/clarity reasons —
  the partial must read `$args['key']` explicitly.
- Pre-5.5 themes that use `set_query_var()` still work,
  but `$args` is the recommended pattern for new code.

### D. Action hooks fired during inclusion

Three actions fire in sequence before the partial is
included:

```php
do_action( "get_template_part_{$slug}", $slug, $name, $templates, $args );
do_action( 'get_template_part',          $slug, $name, $templates, $args );
// then locate_template() runs and require()s the chosen file
```

Note the subtlety: there is no
`get_template_part_{$slug}_{$name}` hook — only the
slug-scoped and the global hook fire. Themes wanting
finer-grained reaction must inspect `$name` inside the
slug-scoped handler.

These hooks are pre-inclusion observation points. They do
**not** offer a return-channel that can prevent inclusion
or substitute a different file. To intercept the *file
choice*, use the lower-level `locate_template` filter chain
or, more commonly, override the partial in the child theme
(Section B).

### E. The convenience family — `get_header()` / `get_footer()` / `get_sidebar()`

These three (plus `get_search_form()`) are *named-target*
specializations of the same mechanism:

| Function           | Resolution                                 |
| ------------------ | ------------------------------------------ |
| `get_header($name)`  | `header-{$name}.php` → `header.php`        |
| `get_footer($name)`  | `footer-{$name}.php` → `footer.php`        |
| `get_sidebar($name)` | `sidebar-{$name}.php` → `sidebar.php`      |
| `get_search_form()`  | `searchform.php`, then core fallback HTML  |

They each route through `locate_template()` and so inherit
the child→parent override behavior. They differ from
`get_template_part()` in two practical ways:

- They fire dedicated action hooks (`get_header`,
  `get_footer`, etc.) rather than the `get_template_part`
  family.
- `get_search_form()` has a *built-in core fallback* (it
  emits a default `<form>` if no `searchform.php` exists),
  whereas `get_template_part()` simply does nothing when
  no candidate is found.

For new code, prefer `get_template_part('header', $variant)`
unless you specifically need the legacy hook. Both are
canonical and supported.

### F. Block-theme positioning

In a pure block theme, `get_template_part()` plays a
diminished but non-zero role:

- **Block templates** (`templates/*.html`) and **block
  template parts** (`parts/*.html`) are the primary
  composition mechanism. They are referenced through
  `<!-- wp:template-part {"slug":"header"} /-->` and
  resolved by the block-theme template-part resolver — *not*
  by `get_template_part()`.
- However, **`render.php` files for dynamic blocks** still
  use plain PHP. Inside `render.php` you may call
  `get_template_part()` to factor out reusable PHP
  fragments. There is no block-era equivalent inside
  `render.php` itself.
- **Hybrid themes** (block templates + classic PHP fragments)
  routinely call `get_template_part()` from within block
  templates' associated PHP rendering logic.

The block-era equivalent for *user-visible* template
fragmentation is the `core/template-part` block, configured
through `theme.json`'s `templateParts` registration. That
is a separate resolution surface and is not interchangeable
with `get_template_part()`.

## WHY

### File-as-declaration, inclusion-as-resolution

The function embodies a small but clean separation:

> The **filesystem declares** what fragments *can* be
> included. The **call site resolves** which fragment *is*
> included for this rendering.

A theme can ship `content.php`, `content-page.php`,
`content-single.php`, `content-aside.php`, … and only the
ones a particular template references via
`get_template_part()` are loaded. File presence does not
imply inclusion. This is the same declaration-vs-exposure
asymmetry visible elsewhere in WordPress — the file is a
*candidate*, not a *commitment*.

### Why child→parent precedence is filesystem-shaped, not registration-shaped

Almost every other WordPress override surface requires
*registration* — a function call that says "I am taking
over this thing." Template parts do not. The override
surface is the filesystem itself, and precedence is
implicit in the search-order constants `STYLESHEETPATH` and
`TEMPLATEPATH`.

This is deliberate. It means a child theme author who has
never read documentation can copy `content-page.php` from
the parent theme into the child, edit it, and it just
works. The function's design optimizes for this discovery
path. The cost: there is no central registry of "what
partials exist" — themes must document their own partial
surface.

### Why the slug→slug-name ordering is "specific first"

`{slug}-{name}.php` is searched before `{slug}.php`. This
is the opposite of CSS-class fallback intuition (where
generic styles cascade *first* and specific ones *override*).
For template partials, the specific candidate is the
*intent* — the caller passed a `$name` because they want
the named variant *if available*; the bare slug is only the
fallback when the named variant isn't shipped.

This makes the call site readable as a soft request:
*"give me content-page if you have it, otherwise content."*

## WHEN NOT

Skip `get_template_part()` and use a different mechanism if:

- You need to **return** the rendered output as a string
  (for buffering, for use as a function argument, etc.).
  `get_template_part()` only echoes; wrap it in
  `ob_start()` / `ob_get_clean()` if you must, or extract
  the rendering into a returning function.
- You are inside a **block render callback** (`render.php`
  or a `render_callback` PHP function) and want to include
  a *block*, not a PHP fragment. Use `do_blocks()` on a
  block-grammar string instead.
- You want to compose a **block-based** template-part
  fragment. Author it as a `parts/*.html` block template
  part and reference it through the `core/template-part`
  block.
- You are passing **secret or trusted data** to the partial.
  `$args` is fine as a value-passing channel, but the
  partial shares request scope; treat the partial as part
  of the calling template's trust boundary, not a
  sandbox.

## COUNTER-PATTERNS

### Anti-pattern 1 — Variable extraction inside the partial

```php
// content-card.php — DON'T
extract( $args ); // creates $heading, $count, …
echo esc_html( $heading );
```

This silently overrides any same-named caller variable
(`$heading` in the outer scope is now clobbered) and
defeats static analysis. Read explicitly:

```php
$heading = (string) ( $args['heading'] ?? '' );
$count   = (int)    ( $args['count']   ?? 5 );
```

### Anti-pattern 2 — Reading `$args` defensively in pre-5.5 partials

If your minimum supported WordPress is below 5.5, `$args`
will not be set. Either bump `wp_min` to 5.5+ in your
theme's `style.css` `Requires at least` header, or guard
the read:

```php
$args = isset( $args ) && is_array( $args ) ? $args : [];
```

The block theme floor (WP 5.9+) puts you safely inside the
`$args`-supported range; classic themes targeting older
sites need the guard.

### Anti-pattern 3 — Trying to return content with `get_template_part()`

```php
// Doesn't work — get_template_part() echoes, returns void.
$markup = get_template_part( 'content', 'card' );
```

Wrap with output buffering or — better — refactor the
fragment into a function that returns its markup:

```php
ob_start();
get_template_part( 'content', 'card' );
$markup = ob_get_clean();
```

### Anti-pattern 4 — Treating `get_template_part_{slug}` as an interceptor

```php
add_action( 'get_template_part_content', function( $slug, $name, $templates, $args ) {
    // Cannot prevent inclusion, cannot substitute a different file.
    // Can only observe / side-effect.
}, 10, 4 );
```

This is an observation hook, not a filter. To swap the
chosen file, override at the filesystem layer (child
theme) or filter `locate_template`'s candidate list
elsewhere.

## OPERATIONAL NOTES

The function's interpretive shape, briefly named in v2
vocabulary where it adds clarity:

- The two-name resolution is a small **arbitration ladder**
  (Law 4) — but a *minimal* one, two stops long. This is
  not the dramatic 5-stage ladder of nav-menu fallback;
  it is the smallest non-trivial form of the same pattern.
  Worth naming once for the cross-reference, not worth
  belaboring.
- The child→parent search order is the **authority
  continuity** (Doctrine 5) of theme inheritance: the
  child theme's authority to override is honored without
  the parent having to declare extension points.
- The filesystem-as-declaration / inclusion-as-resolution
  split is the same **Declaration ≠ Exposure** (Law 1)
  asymmetry visible across the platform. Files exist to
  be *available*, not to be *included*.

No new candidate patterns surface here. The chunk does not
introduce new vocabulary. The mechanism is small enough
that v2 vocabulary clarifies what it *is similar to*
without absorbing it.

## CHECKLIST

When using `get_template_part()` in new code:

- [ ] Pick a slug-name pair that reads as a soft request:
      "give me X-Y if you have it, else X."
- [ ] Pass scoped data through `$args`, never via globals
      (unless the global is conventional like `$post`).
- [ ] Read `$args` keys explicitly inside the partial; do
      not `extract()`.
- [ ] If the partial must work for child themes, place the
      parent version in `{parent}/` and document the
      override path for child theme authors.
- [ ] If you need a return value, do not use
      `get_template_part()` — refactor to a returning
      function or buffer with `ob_*`.
- [ ] If targeting block themes only, confirm whether the
      fragment should be a `parts/*.html` block template
      part instead — `get_template_part()` is a classic-PHP
      inclusion, not a block composition.

## REFERENCES

- `get_template_part()` — function reference (parameters,
  return values, hooks fired, $args contract).
  https://developer.wordpress.org/reference/functions/get_template_part/
- `locate_template()` — the underlying child→parent search
  mechanism. Documents the search order against
  `STYLESHEETPATH` and `TEMPLATEPATH`.
  https://developer.wordpress.org/reference/functions/locate_template/
- Theme handbook — Template Files (template partials
  section). Describes the conventional partials surface
  (`content-{post-type}.php`, etc.).
  https://developer.wordpress.org/themes/basics/template-files/#template-partials
- `get_template_part` action hook reference.
  https://developer.wordpress.org/reference/hooks/get_template_part/
- `get_template_part_{slug}` dynamic action hook reference.
  https://developer.wordpress.org/reference/hooks/get_template_part_slug/

Cross-context:

- `site-building.template-hierarchy-and-resolution` —
  outer hierarchy chooses the primary template;
  `get_template_part()` operates *inside* that chosen
  template.
- `theme-config.templateParts` — block-theme analog. The
  two mechanisms coexist; they are not interchangeable.
- `block.dynamic-rendering` — `render.php` is the
  block-era place where `get_template_part()` may still
  appear naturally.
