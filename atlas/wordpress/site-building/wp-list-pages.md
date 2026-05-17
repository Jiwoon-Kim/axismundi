---
rule_id: site-building.wp-list-pages
domain: site-building
topic: hierarchical-output
field_cluster: page-topology-rendering
wp_min: "1.5"
wp_recommended: "5.0+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/wp_list_pages/
    section: "wp_list_pages() — function reference + arguments + defaults"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/get_pages/
    section: "get_pages() — page retrieval underlying wp_list_pages"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/classes/walker_page/
    section: "Walker_Page class — default rendering walker"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/classes/walker/
    section: "Walker base class — generic tree walker contract"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_page_menu/
    section: "wp_page_menu() — wrapper that delegates to wp_list_pages"
    captured: 2026-05-10
related:
  - site-building.navigation-menu-fallback-resolution     # nav fallback uses wp_page_menu, which uses this
  - site-building.template-hierarchy-and-resolution       # output rendered inside template chosen by hierarchy
  - block.dynamic-rendering                               # core/page-list is the FSE-era block analog
---

# RULE — `wp_list_pages()` — hierarchical page topology renderer

## WHEN

You need to render the site's pages as a hierarchical list,
where the rendered structure mirrors the **page tree as
declared by `post_parent` relationships** — not a curated
menu, not an arbitration ladder. Use this knowledge when:

- Outputting a sitemap-like list of all pages.
- Producing a sidebar list of pages under a section
  (`child_of`).
- Implementing a footer "all pages" list.
- Authoring a fallback when no navigation menu has been
  assigned (the `wp_page_menu()` path; see Section C).
- Reading core code that uses `Walker_Page` and need to
  understand what the walker is walking over.

This chunk does **not** cover:

- Curated navigation menus assigned to theme locations
  (covered in `site-building.navigation-menu-fallback-
  resolution`).
- The block-editor `core/page-list` block, which is the
  FSE-era analog with its own rendering pipeline.
- Custom-post-type hierarchical listings beyond what the
  `post_type` argument permits.

## SHAPE

### A. Core contract

```php
wp_list_pages( $args = '' ): string|void
```

Defaults (the parts most commonly touched, in the order
they matter):

| Argument        | Default                            | Effect                                                   |
| --------------- | ---------------------------------- | -------------------------------------------------------- |
| `depth`         | `0`                                | `0` = unlimited; `1` = top-level only; `-1` = all flat   |
| `child_of`      | `0`                                | Restrict to descendants of a page ID                     |
| `exclude`       | `''`                               | Page IDs to omit                                         |
| `include`       | `''`                               | Restrict to specific page IDs (overrides others)         |
| `title_li`      | `__( 'Pages' )`                    | Wraps the list in a labeled `<li>`; `''` to skip wrapper |
| `sort_column`   | `'menu_order, post_title'`         | One or more columns from the post table                  |
| `walker`        | `''` (defaults to `Walker_Page`)   | Inject a custom walker instance                          |
| `post_type`     | `'page'`                           | Any hierarchical post type works                         |
| `echo`          | `1`                                | `0` returns the string instead                           |

The function:

1. Normalizes `$args` (string-or-array, applies defaults).
2. Calls `get_pages()` with the relevant subset of args to
   retrieve a flat, sorted array of page objects.
3. Constructs a `Walker_Page` instance (or the supplied
   custom walker).
4. Calls `walk_page_tree()` to traverse the flat array as
   a tree, emitting nested `<li>` markup.
5. Optionally wraps the output in a `<li class="pagenav">`
   header (`title_li`).
6. Echoes or returns the resulting HTML.

The output anatomy:

```html
<li class="pagenav">Pages
  <ul>
    <li class="page_item page-item-2"><a href="…">About</a>
      <ul class="children">
        <li class="page_item page-item-5"><a href="…">Team</a></li>
      </ul>
    </li>
    <li class="page_item page-item-3 current_page_item">
      <a href="…">Contact</a>
    </li>
  </ul>
</li>
```

CSS hooks worth knowing:

- `page_item`, `page-item-{ID}` — every rendered item.
- `current_page_item` — the currently viewed page.
- `current_page_parent` — direct parent of the current page.
- `current_page_ancestor` — any ancestor of the current page.
- `children` — every nested `<ul>`.

These class hooks are how themes style "you are here"
breadcrumbs in page lists without computing ancestry
themselves.

### B. Hierarchy as topology, not arbitration

This is the central conceptual point of the function.

The hierarchy here is **declared by data, not selected by
algorithm**. Every page carries a `post_parent` value. The
tree shape is fixed in the database before
`wp_list_pages()` runs. The function's job is to render a
*known* topology, not to *choose between candidates*.

Concretely:

- `get_pages()` returns a flat array sorted by
  `sort_column`.
- `walk_page_tree()` reads `post_parent` on each row and
  re-arranges the flat array into a parent-child tree at
  walk time.
- Each page is included exactly once (modulo `exclude` /
  `include` / `depth` filters).
- There is no "first match wins," no specificity ordering,
  no candidate compiler.

The same physical concept — *a hierarchy* — can play very
different roles in different APIs. Hierarchy as
*arbitration ladder* (template hierarchy → first-existing-
file wins) is one role. Hierarchy as *rendered topology*
(every node in the tree gets emitted in tree order) is a
different role. `wp_list_pages()` is the second.

This distinction is worth holding onto when reading
WordPress code: the presence of nested data structure does
not by itself indicate an arbitration mechanism.

### C. Output vs resolution — the page-rendering family

Three site-building APIs sit near each other and are
frequently confused. They do different jobs:

| Function | What it is | What it produces |
| --- | --- | --- |
| `wp_nav_menu()`   | Assigned-menu output     | Renders a *curated* menu (admin-managed) for a theme location |
| `wp_page_menu()`  | Page-list wrapper       | Wraps `wp_list_pages()` plus an optional "Home" link; the default `fallback_cb` for `wp_nav_menu()` |
| `wp_list_pages()` | Page topology renderer  | Renders the page tree as it exists in the database |

Reading from this:

- `wp_nav_menu()` answers *"what did the site owner
  curate?"* The data source is the menu admin UI.
- `wp_list_pages()` answers *"what pages exist, and how
  are they nested?"* The data source is the page table.
- `wp_page_menu()` is glue: it makes "every page in the
  database, with a home link" usable as a menu fallback
  without forcing themes to author the bridge.

Misreading `wp_list_pages()` as "the old menu system" is
a common mistake — it predates the menu admin UI, but it
was never a *menu* in the curated sense. It is a topology
renderer that *can* be used for navigation when no curated
menu exists.

### D. The walker layer

Rendering is delegated to a `Walker_Page` instance — a
subclass of the abstract `Walker` class that emits markup
during a tree traversal. Four method hooks define the
output:

| Method        | Called when                    | Default emits      |
| ------------- | ------------------------------ | ------------------ |
| `start_lvl()` | Entering a deeper level        | `<ul class="children">` |
| `end_lvl()`   | Leaving a deeper level         | `</ul>`            |
| `start_el()`  | Entering a node                | `<li …><a href>…`  |
| `end_el()`    | Leaving a node                 | `</li>`            |

Custom walker example (skeleton):

```php
class My_Page_Walker extends Walker_Page {
    public function start_el( &$output, $page, $depth = 0, $args = [], $current_page = 0 ) {
        // Override markup for the opening of each item.
        $output .= '<li class="my-item"><a href="' . esc_url( get_permalink( $page->ID ) ) . '">';
        $output .= esc_html( $page->post_title ) . '</a>';
    }
}

wp_list_pages( [
    'walker' => new My_Page_Walker(),
] );
```

Two practical notes:

- The `Walker` family is shared infrastructure.
  `Walker_Nav_Menu`, `Walker_Comment`, `Walker_Category`
  all extend the same base. Authoring a custom page walker
  teaches a pattern that applies to those siblings too.
- Walker output methods append to `$output` *by reference*.
  Forget the `&` and your custom walker silently emits
  nothing.

The walker layer is where presentational customization
lives. The data assembly (`get_pages()`) and the traversal
order (`walk_page_tree()`) are not customizable through
the walker — they are decided before the walker is invoked.

### E. Modern position — specialized topology utility, not legacy

In a block theme, the user-facing equivalent is the
`core/page-list` block. It performs the same job
(hierarchical rendering of pages), exposes a similar set
of options through block editor controls, and produces
similar markup.

`wp_list_pages()` is not retired by this. Its remaining
lanes:

- **Inside `render_callback` for dynamic blocks** that need
  to enumerate pages without going through the
  `core/page-list` block specifically.
- **Inside classic theme PHP** (header, sidebar, footer)
  that hasn't migrated.
- **As `wp_nav_menu()`'s default `fallback_cb`** target
  via `wp_page_menu()`. This is the path documented in
  `site-building.navigation-menu-fallback-resolution`.
- **In utility contexts** (sitemap shortcodes, plugin
  widgets) where the desired output is page topology and
  the consumer is not a block.

A useful one-line shape: this is a **specialized topology
utility with reduced centrality** — the same shape as
`get_template_part()`'s position in the FSE era.
Diminished, not extinct.

## WHY

### Why a dedicated function instead of a `WP_Query` loop

`wp_list_pages()` exists because rendering a hierarchical
list is awkward to compose from `WP_Query`. `WP_Query`
returns a flat result set in query order; building a tree
from `post_parent` relationships and emitting nested `<li>`
markup with depth control is non-trivial. Centralizing
that into one function with a walker hook means themes
get correct nesting, current-page CSS classes, and depth
limiting without each theme reimplementing tree walking.

### Why the walker is separate from the data assembly

The split between `get_pages()` (data) and walker
(presentation) lets you keep the same data assembly while
varying markup. A theme that wants a `<nav>` element
instead of a `<ul>`, or a flat sitemap with breadcrumbs,
swaps the walker without changing the page selection
logic. This is the common motivation for the `Walker`
family across WordPress: *data is shared; markup varies*.

### Why hierarchy here does not arbitrate

The chunk's central WHY: not every WordPress hierarchy is
an arbitration mechanism. The page tree is **declared
state** — the site editor set parent relationships through
the page editor — and the rendering function honors that
declaration verbatim. There is no "first parent wins" or
"more specific page hides the less specific" semantics.
Each page is an independent node, present or absent, in
the rendered topology. This is the difference between a
*tree of authority* (template hierarchy: ancestor wins
when descendant is missing) and a *tree of existence*
(page tree: every node is rendered if it exists).

## WHEN NOT

Skip `wp_list_pages()` if:

- You want a **curated** navigation. Use a navigation menu
  through `wp_nav_menu()` (classic) or the
  `core/navigation` block (FSE).
- You need **non-hierarchical** post type output. Use
  `WP_Query` or `get_posts()` directly.
- You are inside a **block theme** and want the standard
  page-tree presentation. Use the `core/page-list` block;
  users get editor controls and don't need PHP.
- You want **arbitrary tree rendering** of data that
  isn't post-shaped. Use the `Walker` base class with
  your own data, not `wp_list_pages()`.

## COUNTER-PATTERNS

### Anti-pattern 1 — Treating it as a menu API

```php
// Wrong mental model: using wp_list_pages() as a primary navigation
// because "it produces a menu-looking <ul>".
wp_list_pages( [ 'title_li' => '' ] );
```

This will work mechanically, but the moment the site
owner adds a curated menu in the admin, the output will
not reflect it — `wp_list_pages()` has no concept of
assigned menus. Use `wp_nav_menu()` for curated
navigation; reach for `wp_list_pages()` only when *every
page topology* is what you actually want.

### Anti-pattern 2 — Chasing an unbounded tree on every page load

```php
wp_list_pages(); // depth=0 by default = unlimited
```

On a site with thousands of pages, the rendered tree can
be enormous and slow to walk. Set `depth` explicitly when
the use case is bounded (utility footer list, sidebar
section), and consider caching the rendered HTML.

### Anti-pattern 3 — Forgetting `title_li` defaults to a label

```php
wp_list_pages(); // wraps everything in <li class="pagenav">Pages…</li>
```

Most modern themes don't want the auto-generated "Pages"
label `<li>`. Pass `title_li => ''` to suppress the
wrapper unless you actually want it.

### Anti-pattern 4 — Passing a walker class name instead of an instance

```php
// Wrong:
wp_list_pages( [ 'walker' => 'My_Page_Walker' ] );

// Right:
wp_list_pages( [ 'walker' => new My_Page_Walker() ] );
```

The argument must be an instance. Passing a class name
silently falls through to the default walker.

### Anti-pattern 5 — Forgetting `&$output` in custom walker methods

```php
class Bad_Walker extends Walker_Page {
    // Missing the reference — local $output never reaches the caller.
    public function start_el( $output, $page, $depth = 0, $args = [], $current_page = 0 ) {
        $output .= '<li>…';
    }
}
```

The walker contract requires the first argument by
reference. Missing the `&` produces silent empty output.

## OPERATIONAL NOTES

This chunk uses unusually little v2 vocabulary, on
purpose. The function's mechanics are not principally
about arbitration, mediation, runtime split, or authority
brokerage — they are about reading declared topology and
emitting it. Activating framework where it doesn't fit
would obscure rather than clarify.

What the function *is* lightly about:

- **Doctrine 5 (Authority Continuity), in a topological
  reading.** Page parentage is the authority structure
  the site owner declared in the editor; the renderer
  honors that structure as-given. There is nothing to
  resolve, only something to traverse. This is the
  *declared* form of the same authority-relationships
  doctrine that elsewhere appears as *runtime
  continuity*.
- **Law 1 (Declaration ≠ Exposure), faintly.** Pages exist
  in the database whether or not any template renders
  them; `wp_list_pages()` is the exposure act for one
  particular shape of presentation. Worth a single line of
  awareness, not more.

What the function is **not** about, despite surface
resemblance:

- **Law 4 (Arbitration Compiler).** Despite involving a
  hierarchy, this is not an arbitration substrate.
  Hierarchy here is rendered topology, not a candidate
  ladder where one node wins over another. Naming Law 4
  would be a category error and would dilute the pattern's
  meaning in chunks where it really does fit
  (`locate_template`, navigation fallback). The presence
  of nested data does not by itself imply Law 4.
- **Doctrine 6 (Authority Mediation).** No access
  mediation surface. The function reads pages and emits
  markup; capability checks happen elsewhere.
- **Law 3b (Cross-Runtime Bridge).** Pure PHP path; no
  runtime split.

This is a deliberate framework-omission moment. The
clearer this chunk is about what *doesn't* apply, the
sharper the framework remains in chunks where things
*do* apply.

## CHECKLIST

When using `wp_list_pages()` in new code:

- [ ] Confirm the requirement is "render the page topology
      as it exists in data," not "render a curated menu."
      If it's the second, switch to `wp_nav_menu()` /
      `core/navigation`.
- [ ] Set `depth` explicitly. Unlimited is rarely what
      you want for performance and visual reasons.
- [ ] Set `title_li` to `''` unless you actually want the
      auto-generated wrapper label.
- [ ] Use `child_of` to scope the rendered subtree when
      you only need a section, not the whole site's pages.
- [ ] If you need custom markup, write a `Walker_Page`
      subclass and pass an *instance*, not a class name.
      Remember the `&$output` reference in method
      signatures.
- [ ] In a block theme, prefer `core/page-list` for
      user-facing presentation; reserve
      `wp_list_pages()` for fallbacks, dynamic-block
      `render.php`, and utility output.

## REFERENCES

- `wp_list_pages()` — function reference, full argument
  list, defaults, return semantics.
  https://developer.wordpress.org/reference/functions/wp_list_pages/
- `get_pages()` — the underlying retrieval call.
  Documents `child_of`, `sort_column`, `exclude`,
  `include`, and the page-object shape returned.
  https://developer.wordpress.org/reference/functions/get_pages/
- `Walker_Page` — default rendering walker. Documents
  `start_lvl` / `end_lvl` / `start_el` / `end_el` method
  contracts and the markup emitted by each.
  https://developer.wordpress.org/reference/classes/walker_page/
- `Walker` — the base class. Useful for understanding the
  shared traversal contract across `Walker_Page`,
  `Walker_Nav_Menu`, `Walker_Comment`, `Walker_Category`.
  https://developer.wordpress.org/reference/classes/walker/
- `wp_page_menu()` — the wrapper that combines
  `wp_list_pages()` with an optional Home link; the
  default fallback callback for `wp_nav_menu()`.
  https://developer.wordpress.org/reference/functions/wp_page_menu/

Cross-context:

- `site-building.navigation-menu-fallback-resolution` —
  documents the path by which `wp_list_pages()` becomes
  navigation output (via `wp_page_menu()` as
  `fallback_cb`).
- `site-building.template-hierarchy-and-resolution` —
  describes the *outer* template the page-list output
  ends up rendered inside.
- `block.dynamic-rendering` — `core/page-list` is the
  block-era counterpart and is implemented as a dynamic
  block whose render callback walks pages similarly.
