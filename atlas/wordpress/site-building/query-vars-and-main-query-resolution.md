---
rule_id: site-building.query-vars-and-main-query-resolution
domain: site-building
topic: request-semantic-execution
field_cluster: query-var-lifecycle-substrate
wp_min: "2.0"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/classes/wp/parse_request/
    section: "WP::parse_request() — query var population from rewrite match"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/query_vars/
    section: "query_vars filter — public query var whitelist"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/pre_get_posts/
    section: "pre_get_posts action — main query mutation"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/classes/wp_query/
    section: "WP_Query class — query construction and execution"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/is_main_query/
    section: "is_main_query() — distinguishing main query from secondary queries"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/themes/basics/conditional-tags/
    section: "Conditional Tags — downstream signals of main query resolution"
    captured: 2026-05-10
related:
  - plugin-dev.rewrite-rules-and-pattern-resolution    # the upstream arbitration that produces the matched query template
  - site-building.template-hierarchy-and-resolution    # the downstream selection that uses query result for template choice
  - plugin-dev.hooks-lifecycle-and-priority            # pre_get_posts is a hook; many participants compose
  - plugin-dev.register-post-type                       # custom post types contribute query var semantics
---

# RULE — query vars and main query resolution — staged semantic execution from matched route to lived content

## WHEN

You need to reason about *what happens after a
URL matches a rewrite rule* — how the matched
query template becomes a populated `WP_Query`,
who can modify it before SQL runs, and how
conditional tags surface the result. Use this
knowledge when:

- A custom rewrite rule matches but the query
  doesn't return what you expected.
- Adding a custom query var that the URL needs
  to carry through to template logic.
- Modifying the main query (e.g., changing
  posts-per-page on archive pages, adding meta
  query conditions globally) via
  `pre_get_posts`.
- Using conditional tags (`is_single()`,
  `is_archive()`, etc.) and reasoning about
  *when* they become reliable to call.
- Diagnosing "my custom var is in the URL but
  `get_query_var()` returns empty" — almost
  always: missing `query_vars` filter
  registration.
- Distinguishing main-query mutations from
  secondary-query mutations when a hook fires
  for both.

This chunk does **not** cover:

- The rewrite rule matching itself (covered in
  `plugin-dev.rewrite-rules-and-pattern-resolution`).
  Rewrite rules choose which query template
  fires; this chunk is what happens *to* that
  template's output.
- Template selection (covered in
  `site-building.template-hierarchy-and-resolution`).
  Template hierarchy runs *after* main query
  resolution; conditional tags surfaced by
  this chunk are what hierarchy reads.
- Custom WP_Query construction in theme code
  (`new WP_Query(...)`). Those are *secondary
  queries*; this chunk focuses on the *main*
  query that the URL produced.
- The full conditional tag catalog (`is_X` for
  every X). The handbook reference covers each
  individually.

The principle this chunk operates under: **The
matched rewrite rule names *intent* (which query
template should fire). The main query is what
turns intent into *lived content reality* —
through several mutation stages, plugin
participation points, and a final SQL execution.
Matched route ≠ resolved query; the gap is
where most "WordPress query mysteries" live.**

## SHAPE

### A. The query var lifecycle — from URL to rendered loop

The lifecycle from URL to rendered content
spans roughly 10 distinct stages:

```
1. URL arrives at WordPress.
2. WP::parse_request() walks rewrite rules (covered in 8.40).
3. Matched rule's query template parsed → raw query vars array.
4. Public query vars whitelist filters the raw vars.
5. The 'request' filter fires (one mutation point).
6. WP::query_vars finalized.
7. WP_Query instantiated as the "main query."
8. The 'pre_get_posts' action fires (the principal mutation point).
9. Main query runs SQL → populated $wp_query->posts.
10. Conditional tags become reliable; template_redirect fires;
    template hierarchy → file → render loop.
```

Five stages of variable handling (3-7), one
explicit mutation point on the query object
(8), and the actual execution + downstream
resolution (9-10).

The chunk's central observation: there are
*at least four observable transformations*
between "URL matched a rule" and "lived
content rendered":

- *Raw* (rule's query template result).
- *Whitelisted* (public vars only; custom
  vars registered, others stripped).
- *Mutated* (after `request` filter).
- *Executed* (after `pre_get_posts` modifications
  + SQL).

Each transformation is observable; each can be
intervened in. The lifecycle is *staged
governance*, not a single dispatch.

### B. The public query vars whitelist

WordPress maintains a list of *public* query
vars (`WP::$public_query_vars`). This includes
the built-in ones — `page_id`, `post_type`,
`name`, `category_name`, `tag`, `s`, `paged`,
`year`, `monthnum`, etc.

When the rewrite rule's query template
produces vars, they are filtered against this
whitelist:

- Vars in the whitelist → kept on
  `WP::query_vars`.
- Vars not in the whitelist → silently
  stripped.

The stripping is *silent*. There is no error,
no warning, no log entry. A rewrite rule that
produces `index.php?my_custom_var=widget` will
end up with `query_vars` *not containing*
`my_custom_var` unless the var has been
registered.

### C. Custom query var registration

To extend the whitelist, plugins use the
`query_vars` filter:

```php
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'my_custom_var';
    $vars[] = 'product_filter';
    return $vars;
} );
```

The filter fires during `WP::parse_request`,
before the public-var whitelist is applied to
the rewrite output.

Two important properties:

- **Registration is per-name, not per-rule.**
  Once `my_custom_var` is registered, *any*
  rule whose template includes it can use it.
  No coupling between rule registration and
  var registration.
- **Registered vars are queryable but not
  yet acted on.** Registration only ensures
  the var survives the whitelist. What the
  var *means* (what query behavior it
  implies) is the application's responsibility
  — typically via `pre_get_posts` (Section E).

### D. The main query — `WP_Query` construction

After whitelisting and the `request` filter,
WordPress instantiates the main query:

```
$wp_query = new WP_Query( $wp->query_vars );
```

This is a singleton at the WordPress lifecycle
level — the `$wp_query` global, the same
instance that conditional tags consult, the
same instance the loop reads. It is *the*
main query.

`WP_Query::__construct` translates query vars
into the query specification:

- `post_type` → which post types to query.
- `name` / `p` / `page_id` → single-post
  lookup.
- `category_name` / `tag` → taxonomy filter.
- `paged` → pagination offset.
- `meta_query` / `tax_query` (when set
  programmatically) → SQL JOIN structure.
- (Many more.)

The construction is *deterministic*: given the
same query vars, the same `WP_Query` shape
results. The variability comes from
`pre_get_posts` (next section).

### E. `pre_get_posts` — the main mutation point

`pre_get_posts` is an action hook that fires
*after* `WP_Query::__construct` populates the
query but *before* `WP_Query::get_posts` runs
the SQL. The handler receives the `WP_Query`
instance by reference and may modify it in
place:

```php
add_action( 'pre_get_posts', function( $query ) {
    if ( ! $query->is_main_query() ) {
        return;  // crucial — see Section G
    }
    if ( $query->is_post_type_archive( 'product' ) ) {
        $query->set( 'posts_per_page', 12 );
        $query->set( 'orderby', 'menu_order' );
    }
} );
```

Three properties to pin:

- **`pre_get_posts` is the principal place to
  mutate the main query.** It runs late enough
  that the query knows its context; it runs
  early enough that mutations affect the SQL.
- **It fires for *every* `WP_Query`**, including
  secondary queries created by theme code or
  widgets. The `is_main_query()` check is
  almost always required (Section G).
- **Multiple plugins can hook it**, and they
  all fire (composition, not arbitration). The
  resulting main query is the cumulative effect
  of every handler's mutations. Order matters
  because later handlers see earlier handlers'
  mutations.

### F. Conditional tags as downstream signals

`is_single()`, `is_page()`, `is_archive()`,
`is_home()`, `is_front_page()`,
`is_post_type_archive()`, etc. — these are
*outputs* of main query resolution, not
configurations of it.

The conditional tags read from the main query
(`$wp_query`) and answer "what kind of request
is this?":

| Tag                          | True when                                              |
| ---------------------------- | ------------------------------------------------------ |
| `is_single()`                | The main query resolved to a single post              |
| `is_page()`                  | The main query resolved to a single page              |
| `is_archive()`               | The main query resolved to an archive (any kind)      |
| `is_post_type_archive()`     | The main query resolved to a post type archive        |
| `is_home()`                  | The main query resolved to the blog posts index       |
| `is_front_page()`            | The main query resolved to the configured front page  |

Two important properties:

- **They become reliable only after main query
  is set up.** Calling `is_single()` from very
  early hooks (`plugins_loaded`, `init`)
  returns wrong answers because the main query
  hasn't been constructed yet.
- **They reflect the main query specifically.**
  Inside a custom `WP_Query` loop or a widget
  query, `is_single()` still answers about the
  *outer* main query, not the inner one. Use
  `$query->is_single()` (instance method) for
  inner-query questions.

The conditional tags are how *the result of
the staged execution* becomes accessible to
template code, theme functions, plugin
display logic, etc. They are the visible
edge of the query lifecycle.

### G. Main vs secondary queries

A WordPress request typically has *one main
query* and *zero or more secondary queries*:

- **Main query**: produced from the URL
  through stages 1-9 above. Stored as
  `$wp_query` global. The conditional tags
  read this. The Loop iterates this by
  default.
- **Secondary queries**: any `new WP_Query(...)`
  the theme or a plugin creates. Used for
  related-posts widgets, custom feeds,
  shortcodes, etc.

The two share `WP_Query` infrastructure but
differ structurally:

| Aspect                  | Main query                              | Secondary query                     |
| ----------------------- | --------------------------------------- | ----------------------------------- |
| How constructed         | Auto, from URL (stages 1-9)             | Explicit `new WP_Query(...)` call    |
| `$wp_query` global      | This is `$wp_query`                     | Not assigned to global (unless explicit) |
| `is_main_query()`       | true                                    | false                               |
| Affects conditional tags| Yes                                     | No (unless `setup_postdata`)       |
| Multiple per request    | Exactly one                             | Zero or more                        |

The `pre_get_posts` action fires for both
kinds. Forgetting `is_main_query()` is the
canonical bug: a `pre_get_posts` handler
intended for archives changes the
posts-per-page on every related-posts widget,
every footer recent-posts list, every
shortcode-driven query.

## WHY

### Why staged transformations rather than direct URL→content

A direct mapping (URL pattern → posts list)
would be efficient but inflexible. The staged
model lets:

- Themes change posts-per-page on archives.
- Plugins add taxonomy filters globally.
- Search modify what counts as searchable.
- Custom post types contribute query var
  semantics.
- Multiple participants compose their
  contributions before SQL runs.

The cost is the lifecycle is non-trivial; "why
isn't my query returning X?" requires
understanding which stage to debug. The
benefit is that the same request lifecycle
serves countless customization scenarios
without each scenario requiring its own
parsing path.

### Why the public query var whitelist exists

Without filtering, *any* query var the URL
contained would land on `WP::query_vars`.
That would mean:

- Arbitrary URL parameters could become query
  conditions, opening abuse surface (a URL
  with `?meta_value=1 OR 1=1` could attempt
  injection).
- Plugins couldn't reliably know which vars
  are "their" vars vs which are stray noise.
- Query vars would have unbounded growth as
  random URL params accumulated.

The whitelist makes vars *opt-in*. A var
exists in the query layer only if some code
explicitly registered it (or it's one of the
core built-ins). Everything else is silently
stripped.

### Why `pre_get_posts` rather than just letting plugins use the `request` filter

The `request` filter (Stage 5) operates on
the raw query vars before `WP_Query` is
constructed. Mutations there are
text-shape-only — you change the var values,
then `WP_Query` translates them.

`pre_get_posts` operates on the constructed
`WP_Query` object. Mutations there have
access to:

- The query's parsed shape (you know what
  kind of query it is — archive, single,
  search, etc.).
- The `WP_Query` API (`$query->set('foo',
  'bar')` is more readable than mutating
  raw query vars).
- Conditional methods (`$query->is_main_query()`,
  `$query->is_archive()`, etc.) for branching.

The two filters serve different needs.
`pre_get_posts` is overwhelmingly more common
because its API is more ergonomic and its
intent is clearer.

### Why conditional tags read from the main query specifically

Conditional tags need *one source of truth*
about "what is this request?" If they
consulted the *currently active* `WP_Query`
inside any context, calling `is_single()`
inside a related-posts widget loop would
return information about the related-posts
query, not the page being viewed.

Anchoring to the main query (via the
`$wp_query` global) gives conditional tags a
stable answer: they always describe the
request, regardless of what secondary
queries happen to be running.

The cost is that distinguishing
"main-query-is-X" from "active-loop-is-X"
requires using instance methods on the
specific `WP_Query` object. The benefit is
that the dominant case ("describe the
request") is one function call away.

## WHEN NOT

Skip the main-query mutation pathway if:

- You need a **custom query for theme code**
  (related posts, sidebar widgets, archive
  inserts). Use a secondary query
  (`new WP_Query(...)`); don't mutate the
  main query for cases the main query isn't
  serving.
- The query change is **request-context-
  specific** rather than URL-pattern-driven.
  Theme template code can shape its own
  queries; `pre_get_posts` is for changes
  that should affect every matching request.
- You only need to **read** what the main
  query resolved to. Use conditional tags
  (`is_X`) directly; no mutation needed.
- The query is **completely outside
  WordPress's content model** (custom DB
  table, external API). Use `$wpdb` or
  `wp_remote_get` directly.

## COUNTER-PATTERNS

### Anti-pattern 1 — Forgetting `is_main_query()` in `pre_get_posts`

```php
add_action( 'pre_get_posts', function( $query ) {
    $query->set( 'posts_per_page', 12 );  // affects EVERY query, main or secondary
} );
```

Every related-posts widget, every shortcode
query, every WP_Query in theme code now
returns 12 posts. Add the check:

```php
add_action( 'pre_get_posts', function( $query ) {
    if ( ! $query->is_main_query() ) {
        return;
    }
    if ( ! is_admin() && $query->is_post_type_archive( 'product' ) ) {
        $query->set( 'posts_per_page', 12 );
    }
} );
```

### Anti-pattern 2 — Custom query var without `query_vars` filter

```php
add_rewrite_rule( '^foo/(.+)/?$', 'index.php?my_var=$matches[1]', 'top' );
flush_rewrite_rules();
// URL hits the rule. Rule produces my_var. But:
get_query_var( 'my_var' );  // returns ''
```

The rule fired; the var was in the rule's
output; the whitelist stripped it because
`my_var` wasn't registered. Add:

```php
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'my_var';
    return $vars;
} );
```

### Anti-pattern 3 — Calling conditional tags too early

```php
add_action( 'init', function() {
    if ( is_single() ) {  // unreliable — main query not set yet
        // ...
    }
} );
```

`init` fires before `parse_request`; the main
query hasn't been constructed yet. The
conditional tag returns false (or worse,
returns wrong values inconsistently).

Wait for `template_redirect` (or any later
hook) when the main query is reliably set
up:

```php
add_action( 'template_redirect', function() {
    if ( is_single() ) {
        // main query is now reliable
    }
} );
```

### Anti-pattern 4 — Mutating the main query directly outside `pre_get_posts`

```php
add_action( 'wp', function() {
    global $wp_query;
    $wp_query->set( 'posts_per_page', 12 );
    $wp_query->get_posts();  // re-runs SQL
} );
```

By `wp`, the SQL has already run. Mutating
the query and re-running disposes of the
first result; effectively double-querying.
Use `pre_get_posts`.

### Anti-pattern 5 — Treating secondary query results as conditional-tag context

```php
$products = new WP_Query( array( 'post_type' => 'product' ) );
while ( $products->have_posts() ) {
    $products->the_post();
    if ( is_single() ) { … }  // is_single() reads MAIN query, not $products
}
```

`is_single()` doesn't switch to "the
currently active query." Use the instance
method:

```php
if ( $products->is_single() ) { … }
```

(In context of the loop above, you probably
mean "is this product a single post type
result" — but `is_single()` is rarely the
right test inside a secondary loop. Reach
for the right intent.)

### Anti-pattern 6 — Re-registering a query var on every request without idempotence concerns

```php
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'product_filter';  // appended every time the filter runs
    return $vars;
} );
```

This is fine in practice — duplicates in the
vars array don't cause issues, and the
filter only runs once per `parse_request`.
But conceptually treating registration as
side-effecting can hide other bugs. Prefer
`array_unique` if accumulation matters.

## OPERATIONAL NOTES

The query var lifecycle's interpretive shape,
in proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *multi-stage* form. The
  query var's identity persists across
  several stages — registration (whitelist),
  matching (rewrite output), parsing
  (`request` filter), construction
  (`WP_Query`), mutation (`pre_get_posts`),
  execution (SQL) — but at each stage *what
  the var means* and *whether it has effect*
  shifts. A var registered but never matched
  has no effect; a var matched but never
  filtered into the query has no effect; a
  var mutated by `pre_get_posts` has
  different effect than its raw value
  declared. Naming Law 1 here is genuinely
  clarifying because the *gap* between any
  two stages is a real diagnostic surface
  ("at which stage did the var lose its
  effect?").
- **Doctrine 5 (Authority Continuity)** is
  **strong**. The query var name is the
  identity surface that persists through all
  stages. Same string identifies the var in
  rule template, whitelist, request filter,
  WP_Query parameter, conditional tag
  internals. The continuity is what makes
  the staged pipeline coherent — you can
  trace a single var by name through the
  whole lifecycle.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Mostly
  non-fit, with composite-mechanism note.*
  The *upstream* rewrite rule selection
  (covered in 8.40) IS true Law 4
  arbitration. After the rewrite stage, the
  query var lifecycle is *not* arbitration:
  - The whitelist is a deterministic filter
    (each var either passes or doesn't; no
    candidates compete).
  - The `request` filter is composition
    (multiple handlers all fire; mutations
    accumulate).
  - `pre_get_posts` is composition (multiple
    handlers all fire; the resulting query
    is the cumulative effect).
  - Conditional tags are deterministic
    boolean computations from query state.
  None of these is arbitration. **The same
  request lifecycle contains both Law 4
  positive (rewrite) and Law 4 non-fit
  (post-rewrite stages)** — a useful
  observation: the doctrinal fit changes
  *within* one request, depending on which
  stage you're examining. Sub-mechanisms in
  one larger pipeline can have different
  Law-4 fit profiles. Naming Law 4 across
  the whole pipeline would conflate the
  arbitration step with the composition
  steps.
- **Federation** appears in a **federation-
  registration + composed-singular-output**
  variant. The `query_vars` filter and the
  `pre_get_posts` action are both open
  federations: many participants register;
  every participant's contribution affects
  the outcome. But the resulting main query
  is *one shared object*, not many parallel
  outputs (unlike hooks where outputs are
  side-effects and there's nothing to
  combine; or unlike data-registry where
  each plugin owns its own slice). Worth
  pinning as another federation variant
  alongside structured-placement
  (8.39 dashboard widgets) and registration-
  arbitration-hybrid (8.40 rewrite rules).
- **Doctrine 6 (Authority Mediation).** No
  access mediation in the query lifecycle
  itself. Capability checks for what posts
  the user can see happen later (in
  `WP_Query` itself, in `the_posts` filter,
  in template logic) — not in the var
  registration / parsing / mutation layers.
  Omitted.
- **Law 3b (Cross-Runtime Bridge).** All in
  PHP request runtime. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** No
  build / runtime split. Omitted.
- **Section X archetypes.** A query var
  lifecycle is not a "civilization." Same
  framework-omission discipline as the
  surrounding chunks. Omitted.

Three literacy contributions worth pinning:

> *Matched route ≠ resolved query.* A URL
> that matched a rewrite rule is not the
> same as a request whose content has been
> determined. The matched rule chooses
> *which query template fires*; the query
> template produces *raw query vars*; the
> whitelist filters them; the `request`
> filter mutates them; `WP_Query`
> constructs from them; `pre_get_posts`
> mutates the constructed query; SQL
> executes; conditional tags reflect the
> result. The route is the entry of the
> pipeline; the resolved query is the
> exit. Most "WordPress query mysteries"
> live between those two ends.

This contribution adds another *staged-
execution* form to the existence-vs-operation
toolkit — extending it into request lifecycle
terrain where the staging is *between
arbitration (entry) and composition (mutation)*
in one request's scope.

> *Composite-mechanism observation: Law 4
> fit can vary within one request lifecycle.*
> The same request that rests on a true
> Law 4 arbitration (rewrite rule selection,
> covered in 8.40) immediately enters a
> non-Law-4 composition stage (`request`
> filter, `pre_get_posts` mutation). The
> two stages are part of one continuous
> request flow, but they have *opposite*
> Law 4 fit profiles. This is a useful
> educational observation: doctrinal fit
> attaches to *mechanism stages*, not to
> *whole pipelines*. A pipeline with mixed
> stage types has mixed doctrinal fit
> across its length.

This contribution refines doctrinal-fit
analysis: previously the inventory pinned
non-fits and positive fits as
chunk-level observations; this one notes
that the unit of analysis can be *finer*
than a chunk — within one mechanism, stages
can independently fit or not fit a given
doctrine. Useful for future chunks that
cover multi-stage pipelines.

> *Federation-registration + composed-
> singular-output is a third Federation
> variant.* Open registration (federation
> shape on the input side) with all
> participants contributing to one shared
> result (composition shape on the output
> side) is not the same as either pure
> federation (where outputs don't combine)
> or arbitration-hybrid registration (where
> one participant wins). The
> `query_vars` filter and `pre_get_posts`
> action both exhibit this pattern: many
> registrations, all contribute, but the
> result is a single shared object (the
> query) rather than parallel outputs.

This adds the **third Federation variant**
to the family established by 8.39
(structured-placement) and 8.40
(registration-arbitration-hybrid). The
Federation pattern continues to refine as
the KB encounters new substrates: it's not
"federation or not federation," but
"federation with which output semantics."

## CHECKLIST

When working with query vars and main query:

- [ ] Register custom query vars via the
      `query_vars` filter; without it, the
      var is silently stripped.
- [ ] Use `pre_get_posts` for main-query
      mutations, with `is_main_query()`
      as the first guard.
- [ ] Don't call conditional tags before
      the main query is set up; wait for
      `template_redirect` or later.
- [ ] Use instance methods
      (`$query->is_X()`) when reasoning about
      a specific `WP_Query`; the global
      `is_X()` always reads the main query.
- [ ] For non-main custom queries, create a
      `new WP_Query(...)`; don't mutate the
      main query for cases it doesn't
      serve.
- [ ] When debugging "query returned wrong
      results," walk the lifecycle stages
      to find where the deviation entered.
      Each stage has distinct symptoms.
- [ ] Capability checks for what posts a
      user can see happen *outside* this
      pipeline; don't rely on the var/query
      lifecycle to enforce access.

## REFERENCES

- `WP::parse_request()` reference. The entry
  point that walks rewrite rules and
  populates query vars.
  https://developer.wordpress.org/reference/classes/wp/parse_request/
- `query_vars` filter reference. The
  whitelist extension point.
  https://developer.wordpress.org/reference/hooks/query_vars/
- `pre_get_posts` action reference. The
  principal mutation point.
  https://developer.wordpress.org/reference/hooks/pre_get_posts/
- `WP_Query` class reference. The query
  construction and execution machinery.
  https://developer.wordpress.org/reference/classes/wp_query/
- `is_main_query()` reference. The
  distinguishing function for main vs
  secondary queries.
  https://developer.wordpress.org/reference/functions/is_main_query/
- Conditional Tags handbook. Catalog of
  `is_X` functions and their resolution.
  https://developer.wordpress.org/themes/basics/conditional-tags/

Cross-context:

- `plugin-dev.rewrite-rules-and-pattern-resolution`
  — the *upstream* arbitration that
  produces the matched query template
  this chunk's lifecycle starts from.
  Together: rewrite (true Law 4) + query
  resolution (multi-stage composition) =
  full URL-to-content pipeline.
- `site-building.template-hierarchy-and-resolution`
  — the *downstream* selection. Conditional
  tags surfaced here are what hierarchy
  reads to choose the template file.
- `plugin-dev.hooks-lifecycle-and-priority`
  — `pre_get_posts` is a hook; the
  composition semantics this chunk's
  mutation stage relies on are documented
  there.
- `plugin-dev.register-post-type` — custom
  post types contribute query var
  semantics (`post_type` query var, type-
  specific archive queries).
