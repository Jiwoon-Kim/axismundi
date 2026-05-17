---
rule_id: site-building.locate-template
domain: site-building
topic: substrate-resolution
field_cluster: template-resolution-substrate
wp_min: "3.0"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/locate_template/
    section: "locate_template() — function reference + parameters + return"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/load_template/
    section: "load_template() — actual file inclusion mechanism"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/get_query_template/
    section: "get_query_template() — typed wrapper that builds a candidate list and calls locate_template()"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/type_template_hierarchy/
    section: "{$type}_template_hierarchy filter — candidate-array mutation point"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/type_template/
    section: "{$type}_template filter — chosen-path mutation point"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/template_include/
    section: "template_include filter — last word on which file the loader includes"
    captured: 2026-05-10
related:
  - site-building.get-template-part                       # ergonomic wrapper that delegates to locate_template
  - site-building.template-hierarchy-and-resolution       # hierarchy → candidate-array source that feeds locate_template
  - site-building.navigation-menu-fallback-resolution     # adjacent fallback pattern (different surface)
  - site-building.block-pattern-resolution-and-precedence # adjacent composition-resolution pattern
  - block.dynamic-rendering                               # render.php inclusion is a separate, non-locate_template path
  - theme-config.templateParts                            # FSE-era template-part resolution (not locate_template)
---

# RULE — `locate_template()` — substrate-level template resolution across child→parent theme directories

## WHEN

You are looking *under* a higher-level template API
(`get_template_part()`, `get_header()`, `get_query_template()`,
`get_search_form()`, …) and want to understand the actual
mechanism that decides "given a list of candidate filenames,
which file in which theme directory ends up being included."

Use this chunk when:

- A higher-level API is "not finding" the file you expect, and
  you need to understand the search order it applied.
- You are writing a custom resolver — for example, a plugin
  that wants to allow theme override of plugin-shipped
  templates, or a theme framework that synthesizes its own
  candidate list and asks core to find the first existing
  one.
- You are reading the template loader and want to follow the
  call chain `template-loader.php` →
  `get_{type}_template()` → `get_query_template()` →
  `locate_template()` → `load_template()`.
- You need to know the difference between the *returned-path*
  variant (default) and the *load-on-find* variant (`$load = true`).

This chunk does **not** cover:

- The query-type-to-candidate-array mapping that builds the
  hierarchy WordPress passes to `locate_template()`. That is
  in `site-building.template-hierarchy-and-resolution`.
- The block-theme template resolver
  (`templates/*.html` discovery). That is a separate path
  and is covered under `theme-config.templateParts` and the
  block template loader.
- The `get_template_part()` ergonomic wrapper. That is in
  `site-building.get-template-part`.

## SHAPE

### A. Core contract — signature, search order, return shape

```php
locate_template(
    array|string $template_names,
    bool         $load       = false,
    bool         $load_once  = true,
    array        $args       = []
): string
```

**Inputs.** A list of candidate template filenames (relative
to the theme root), plus three behavior flags.

**Per-candidate search order.** For each name in
`$template_names`, in array order, `locate_template()`
tests two absolute paths in this order:

```
1. STYLESHEETPATH . '/' . $template_name   // child theme
2. TEMPLATEPATH   . '/' . $template_name   // parent theme
```

The **first existing file** for the **first matching
candidate** wins. The function returns its absolute path
as a string. If no candidate exists in either directory,
it returns an empty string `''`.

**Two-axis arbitration.** The full effective search matrix
for a 3-element candidate array in a child-theme context
is:

```
candidate[0]: {child}/c0.php → {parent}/c0.php
candidate[1]: {child}/c1.php → {parent}/c1.php
candidate[2]: {child}/c2.php → {parent}/c2.php
```

The **outer axis is candidate specificity** (caller's
ordering — typically specific-first). The **inner axis is
theme authority** (child overrides parent). The two axes do
not interact: the child theme's authority to override
applies *per candidate*, not across the whole hierarchy.

This is important. A child theme that ships only
`c2.php` does **not** beat a parent theme that ships
`c0.php`. Caller-supplied specificity ordering is
preserved; child override is honored only within a fixed
candidate slot.

### B. The two flags — `$load` and `$load_once`

`locate_template()` has two operating modes:

| `$load` | Behavior                                              |
| ------- | ----------------------------------------------------- |
| `false` (default) | Returns the path. Caller decides what to do with it. |
| `true`  | Calls `load_template( $path, $load_once, $args )` which `require`s the file inline. |

`$load_once` only matters when `$load = true`. It chooses
between `require_once` (default) and plain `require`. The
default is sensible for templates: re-including the same
template file in one request usually indicates a bug.

The `get_template_part()` wrapper passes `$load = true,
$load_once = false` — partials are intentionally
re-includable (you might call `get_template_part('content')`
inside a loop and want each iteration to actually run the
file).

For "I just want to know what file would be picked":

```php
$path = locate_template( [ 'archive-product.php', 'archive.php' ] );
if ( $path ) {
    // $path is the chosen file; do something with it.
} else {
    // No candidate exists in either theme directory.
}
```

### C. The substrate's role — candidate-array arbitration, not just two-name fallback

`get_template_part()` always passes a small, fixed-shape
array (one or two names). `locate_template()` itself
imposes no such limit. Real callers in core regularly hand
it candidate arrays of arbitrary length — most notably the
template hierarchy resolver.

Concretely, `get_query_template( 'single' )` builds a list
that may include (depending on the queried object):

```
[
    'single-{post_type}-{slug}.php',
    'single-{post_type}.php',
    'single.php',
    'singular.php',
    'index.php',
]
```

…and hands the whole list to `locate_template()`. The
result is that `locate_template()` is the **substrate**
where the *entire WordPress template hierarchy* — for the
classic-PHP path — actually runs. The candidate list is
generated by the hierarchy logic; `locate_template()` is
what walks it and picks the winner.

This is why this chunk treats `locate_template()` as the
substrate and `get_template_part()` as one ergonomic wrapper
on top: the hierarchy-driven `get_query_template()` path is
just as legitimate a caller, and a far more frequent one in
practice.

### D. Standalone consumers and the API triad

Three core APIs sit on top of `locate_template()` with
distinct purposes:

| API | What it is | What it owns |
| --- | --- | --- |
| `get_template_part()` | Ergonomic inclusion helper for partials | Slug-name candidate construction + inclusion |
| `locate_template()`   | Resolution substrate                       | Candidate-array search across theme dirs |
| `get_query_template()` | Hierarchy-fed arbitration for query types | Candidate-array *generation* per query type |

The clean reading is:

- `get_template_part()` is **caller-shaped** — the caller
  knows which two names to try.
- `get_query_template()` is **query-shaped** — the queried
  object generates the candidate list.
- `locate_template()` is **path-shaped** — given any list,
  find a file.

Almost every other "template lookup" feature in classic
WordPress reduces to one of these three, plus the filter
surfaces in Section E.

Notable callers worth knowing:

- **`get_query_template($type)`** — the per-type wrapper
  used by the template loader. Has a typed sibling for each
  hierarchy type (`get_404_template()`,
  `get_archive_template()`, `get_single_template()`,
  `get_page_template()`, `get_taxonomy_template()`, …).
  All of them ultimately call `get_query_template()`,
  which calls `locate_template()`.
- **`get_search_form( $args )`** — calls
  `locate_template( 'searchform.php' )`; if nothing is
  found, falls back to a hard-coded core form.
- **`comments_template()`** — calls `locate_template()` for
  `comments.php` (and for theme-specified alternate paths).
- **`get_template_part()`** — already covered.
- **Plugin override patterns.** Many plugins implement
  "themes can override our templates" by calling
  `locate_template( "myplugin/{$slug}.php" )` first, and
  falling back to the plugin's own templates when it
  returns empty. This is the canonical pattern; it works
  precisely because `locate_template()` knows nothing about
  *who* shipped the file — only where to look. (The
  WooCommerce-style `wc_get_template()` helper is a
  conceptual analog: it generalizes this same override
  flow for plugin templates.)

### E. Filter surfaces — where the candidate array can be modified

Three filters form the documented mutation points around
the resolution substrate. They fire at different stages
and have different powers.

```
get_query_template( $type, $templates )
        │
        ├─ apply_filters( "{$type}_template_hierarchy", $templates )    ← E1
        │
        ▼
locate_template( $templates )  →  $path
        │
        ▼
apply_filters( "{$type}_template", $path )                               ← E2
        │
        ▼
returned to template-loader.php
        │
        ▼
apply_filters( 'template_include', $path )                               ← E3
        │
        ▼
include $path
```

**E1 — `{$type}_template_hierarchy` (5.5+).** Mutates the
*candidate array before resolution*. This is where to inject
new candidates or reorder existing ones. Example:

```php
add_filter( 'archive_template_hierarchy', function( $templates ) {
    // Try a holiday-themed archive template before the normal candidates.
    array_unshift( $templates, 'archive-holiday.php' );
    return $templates;
} );
```

The injected file still needs to exist somewhere in
child-or-parent theme to be picked. The filter changes the
*shape of the question*, not the *answer*.

**E2 — `{$type}_template`.** Mutates the *chosen path
after resolution*. Use this to swap to a completely
different file (for example, a plugin-shipped template
outside the theme directory). Useful when the desired file
isn't reachable through child→parent search at all.

**E3 — `template_include`.** The last filter the loader
applies before `include`. The most aggressive override
point — anyone hooking it can return an absolute path to
*any* file. Used by page-builder plugins, "maintenance
mode" plugins, etc. Power and footgun in equal measure.

The natural reading is:

- E1 changes *what's asked*.
- E2 changes *what was answered*.
- E3 ignores the question entirely and dictates the answer.

Each is appropriate at a different scale of intervention.
Reach for the gentlest one that does the job.

### F. FSE-era position — substrate, not legacy

In a block theme, the *primary* template resolution path
goes through a separate resolver that searches
`templates/*.html` and database-stored template overrides.
That resolver lives in functions like
`get_block_template()` and the block template loader, and
does **not** call `locate_template()`.

But `locate_template()` is not retired. Two things keep it
load-bearing in the FSE era:

- **Classic-PHP shims.** Plugins, search forms, comment
  templates, and any classic-PHP template part still
  resolve through `locate_template()`. A pure block theme
  that wants to support a plugin's classic template
  override hook will still see `locate_template()` invoked.
- **Hybrid themes.** Themes that ship block templates
  alongside classic PHP fragments (e.g. a block-based
  homepage with a classic `comments.php`) keep
  `locate_template()` in the resolution loop for the
  classic side.

The post-FSE shape: `locate_template()` is the **classic
theme directory resolver**; the block template resolver is
its sibling for `.html` templates. Both feed the eventual
`template_include` filter at the loader level.

## WHY

### Why a substrate?

`locate_template()` exists because the question "where in
the theme stack does this file live?" is asked by enough
distinct callers — partials, hierarchy resolution, comments,
search form, plugin overrides — that giving each of them
its own search loop would mean five identical
implementations of child→parent precedence. Centralizing
the loop means: one place defines theme-stack semantics; one
place to filter-extend (well, mostly — `template_include`
is its own thing); one place that quietly absorbs the
introduction of new theme-stack layers if WordPress ever
adds them.

### Why "first match wins" rather than "most-specific wins"

The algorithm is naive — it walks candidates in the order
the caller supplied, and stops at the first hit. There is
no scoring, no specificity computation. The intelligence
about *which order* to try is delegated entirely to the
caller (or, for the hierarchy path, to the hierarchy
generator and its filter).

The benefit is that `locate_template()` itself remains
predictable and substitutable. The cost is that callers
must order their candidate arrays correctly: specific-first,
fallback-last. Get this wrong and the substrate will pick
the wrong file with no warning.

### Why filters at three stages, not one

The three filter stages (E1, E2, E3) exist because three
distinct intervention shapes are useful:

- *"Add a candidate."* You want your candidate considered
  alongside the hierarchy's, with the hierarchy's child→
  parent logic still applied to your file. → E1.
- *"Replace the chosen file."* The hierarchy picked
  something fine for normal users, but for a special case
  you want a different file entirely — possibly outside
  the theme directories. → E2.
- *"Hijack rendering."* Your plugin renders a maintenance
  page, a page-builder layout, a redirect. You don't care
  what the hierarchy chose. → E3.

A single filter at any one stage cannot cleanly express
all three intents.

## WHEN NOT

Skip `locate_template()` entirely if:

- You are inside a block theme and need to resolve a
  **block** template part. Use the block template resolver
  (or just author a `core/template-part` block); see
  `theme-config.templateParts`.
- You want to *include* a file from a plugin's own
  directory unconditionally. `locate_template()` only
  searches theme directories — you'd just `require` it
  yourself.
- You are inside a `render.php` for a dynamic block and
  want to render block content. Use `do_blocks()` on a
  block-grammar string; the block render path is not a
  `locate_template()` consumer.
- You want to inspect *all* matching files (e.g., for
  diagnostic tooling). `locate_template()` returns the
  first hit and stops; you'll need to walk
  `STYLESHEETPATH` and `TEMPLATEPATH` yourself.

## COUNTER-PATTERNS

### Anti-pattern 1 — Generic-first candidate ordering

```php
// Wrong: generic candidate listed before specific.
$path = locate_template( [ 'archive.php', 'archive-product.php' ] );
```

`locate_template()` is order-sensitive. The above will
prefer `archive.php` even when `archive-product.php`
exists, because the substrate doesn't reorder for you.
Always pass specific-first.

### Anti-pattern 2 — `template_include` as default override hook

```php
add_filter( 'template_include', function( $path ) {
    if ( is_singular( 'product' ) ) {
        return WP_PLUGIN_DIR . '/myplugin/single-product.php';
    }
    return $path;
} );
```

This works, but it's a hammer. A gentler form: use the
`single_template` filter (E2), or — gentler still — let
the theme override your plugin file by checking
`locate_template()` first inside `single_product.php`. Save
`template_include` for cases where you really do mean
"replace whatever the loader picked."

### Anti-pattern 3 — Misreading "no file found" as failure

`locate_template()` returning `''` is informational, not
exceptional. The right caller-side handling is to provide
a fallback path or render core's default. Treating empty
return as a fatal error breaks themes that legitimately
omit optional files.

### Anti-pattern 4 — Calling `locate_template()` with `$load = true` from a non-template context

```php
// Inside an admin AJAX handler:
locate_template( [ 'parts/sidebar.php' ], true );
```

If the located template assumes the main query is set up,
or assumes WordPress's standard template context, it will
likely error or output stray markup into the AJAX response.
Use `$load = false` and `require` the path yourself only
in contexts where the template's assumptions hold.

## OPERATIONAL NOTES

The substrate's interpretive shape, briefly:

- This is one of **Law 4 (Arbitration Compiler)**'s cleaner
  substrate manifestations — a generic candidate-array
  walker with a deterministic first-match rule. The
  caller-supplied order encodes the intent; the substrate
  enforces the search. Naming Law 4 here is genuinely
  clarifying because it lets later chunks point at this
  function as the *reference shape* for "WordPress's
  ordinary candidate-arbitration mechanism."
- The child→parent search dimension is the inner-axis
  expression of **Doctrine 5 (Authority Continuity)** —
  child-theme authority is honored without the parent
  declaring extension points; the override surface is the
  filesystem.
- The path-based / file-based resolution is the same
  filesystem-as-declaration / inclusion-as-resolution
  asymmetry from `get_template_part()`. Worth a line, not
  a section. Files exist as candidates; the substrate
  decides which is realized.

The triad — `get_template_part()` (ergonomic helper) /
`locate_template()` (resolution substrate) /
`get_query_template()` (hierarchy-fed arbitration) — is
useful architectural literacy for site-building work, even
without elevating it to a named pattern.

No new candidate patterns surface here. No sub-pattern
modulation. The chunk uses Law 4 prominently because the
function's job is exactly the bare form of that pattern;
it does not extend Law 4 into anything new.

## CHECKLIST

When using `locate_template()` directly:

- [ ] Order the candidate array specific-first,
      fallback-last. The substrate does not reorder.
- [ ] Decide intentionally between `$load = false`
      (return-path mode, more flexible) and `$load = true`
      (fire-and-forget, ergonomic).
- [ ] If `$load = true`, set `$load_once` based on whether
      the template is meant to be re-includable
      (`get_template_part()` style: `false`).
- [ ] Treat `''` return as the legitimate "nothing found"
      signal. Provide a fallback path or render a default;
      do not assume failure.
- [ ] If you are writing a plugin that ships templates and
      wants theme override, call `locate_template()` first
      with your plugin-prefixed candidate names, then fall
      back to your own files — this is the canonical
      override pattern.
- [ ] If you are filtering, prefer the gentlest filter that
      does the job: `{$type}_template_hierarchy` for
      "add a candidate," `{$type}_template` for "swap the
      answer," `template_include` for "ignore the question."

## REFERENCES

- `locate_template()` — function reference. Documents the
  child→parent search order, the `$load` and `$load_once`
  flags, and the empty-string return contract.
  https://developer.wordpress.org/reference/functions/locate_template/
- `load_template()` — the underlying inclusion mechanism
  that runs when `locate_template()` is called with
  `$load = true`. Documents `$args` propagation.
  https://developer.wordpress.org/reference/functions/load_template/
- `get_query_template()` — the typed wrapper that builds
  hierarchy-driven candidate arrays and calls
  `locate_template()`. Documents the
  `{$type}_template_hierarchy` and `{$type}_template`
  filter points.
  https://developer.wordpress.org/reference/functions/get_query_template/
- `{$type}_template_hierarchy` filter — pre-resolution
  candidate-array mutation point.
  https://developer.wordpress.org/reference/hooks/type_template_hierarchy/
- `{$type}_template` filter — post-resolution path
  mutation point.
  https://developer.wordpress.org/reference/hooks/type_template/
- `template_include` filter — final loader-level override
  point.
  https://developer.wordpress.org/reference/hooks/template_include/

Cross-context:

- `site-building.template-hierarchy-and-resolution` —
  describes how the candidate arrays that
  `locate_template()` walks are *generated* per query type.
- `site-building.get-template-part` — the most common
  ergonomic wrapper on top of `locate_template()`. The two
  chunks together document the wrapper / substrate split.
- `theme-config.templateParts` — the FSE-era resolver for
  block template parts. Sibling to `locate_template()` in
  the post-FSE template loader, not a wrapper.
