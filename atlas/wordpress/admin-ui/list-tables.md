---
rule_id: admin-ui.list-tables
domain: admin-ui
topic: institutional-display-substrate
field_cluster: operator-facing-table-rendering
wp_min: "3.1"
wp_recommended: "5.5+"
status: stable-but-marked-private
language: php
sources:
  - url: https://developer.wordpress.org/reference/classes/wp_list_table/
    section: "WP_List_Table — class reference, marked 'private' but widely used"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/plugins/administration-menus/
    section: "Administration Menus — capability-gated admin page registration"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/add_screen_option/
    section: "add_screen_option() — per-user per-screen preferences"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/classes/wp_screen/
    section: "WP_Screen — current admin screen context, hidden columns, options"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/get_hidden_columns/
    section: "get_hidden_columns() — per-user column-visibility preference"
    captured: 2026-05-10
related:
  - admin-ui.settings-api                          # adjacent admin governance surface (form-based settings)
  - admin-ui.admin-menus                           # the registration that places a list-table page in the menu
  - admin-ui.notices                               # the admin-message surface bulk-action results often surface in
  - plugin-dev.capabilities-and-roles              # capability schema this chunk's reachability gates depend on
  - plugin-dev.security-boundaries                 # nonce/sanitize discipline applied to bulk-action handling
---

# RULE — `WP_List_Table` — operator-facing institutional table substrate

## WHEN

You are building a wp-admin screen that displays a
collection of items (custom post types, plugin-owned
records, log entries, etc.) and need the standard
WordPress admin table look-and-feel: sortable columns,
bulk actions, search, filters, pagination, and per-user
screen preferences. Use this knowledge when:

- Subclassing `WP_List_Table` for a custom admin page.
- Reading how core's own list tables (`WP_Posts_List_Table`,
  `WP_Users_List_Table`, etc.) work in wp-admin/.
- Diagnosing "the column doesn't appear" — almost always
  one of: `get_columns()` mismatch, hidden via Screen
  Options, capability gate, or render method missing.
- Implementing bulk actions and reasoning about their
  capability / nonce / dispatch flow.
- Choosing between a custom rendered table and
  subclassing `WP_List_Table` (the latter buys WordPress
  conventions; the former buys complete control at the
  cost of every standard affordance).

This chunk does **not** cover:

- The Settings API (form-based settings registration) —
  covered in `admin-ui.settings-api`.
- Admin menu registration that places a page in the
  sidebar — covered in `admin-ui.admin-menus`.
- The Options API mechanics underlying option storage —
  list-table-rendered data is usually queried directly,
  not via `get_option`.
- The capability system itself — covered in
  `plugin-dev.capabilities-and-roles`. Reachability
  here uses capabilities; their definition lives there.
- React-based admin tables (custom screens built on
  `@wordpress/components`'s DataViews or similar). This
  chunk is the classic-admin counterpart.

The principle this chunk operates under: **A `WP_List_Table`
declares many surfaces (columns, bulk actions, sortable
columns, filters); each declared surface passes through
multiple reachability gates before an operator can
actually use it. Declaration alone does not produce
reachable governance.**

## SHAPE

### A. `WP_List_Table` as institutional display substrate

`WP_List_Table` is an abstract base class in
`wp-admin/includes/class-wp-list-table.php`. Subclasses
must implement at minimum:

```php
class My_Items_List_Table extends WP_List_Table {

    public function get_columns() {
        return array(
            'cb'    => '<input type="checkbox" />',  // bulk select
            'title' => __( 'Title', 'my-plugin' ),
            'date'  => __( 'Date',  'my-plugin' ),
        );
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = get_hidden_columns( get_current_screen() );
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        // Build query, set $this->items, set pagination.
        $this->items = $this->fetch_items();
        $this->set_pagination_args( array(
            'total_items' => $total_count,
            'per_page'    => $per_page,
        ) );
    }

    protected function column_default( $item, $column_name ) {
        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '—';
    }

    public function get_sortable_columns() {
        return array(
            'title' => array( 'title', false ),  // [orderby_param, default_descending]
            'date'  => array( 'date',  true  ),
        );
    }

    public function get_bulk_actions() {
        return array(
            'delete' => __( 'Delete', 'my-plugin' ),
        );
    }
}
```

The standard rendering call from an admin page handler:

```php
function render_my_items_page() {
    $list_table = new My_Items_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'My Items', 'my-plugin' ); ?></h1>
        <form method="post">
            <?php
            $list_table->search_box( __( 'Search', 'my-plugin' ), 'my-items-search' );
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}
```

The class's official status: **marked private** in
WordPress's API documentation (the docblock includes
`@access private`). In practice it is widely used by
plugins and considered de facto stable, but theoretical
breakage is allowed. Treat it as "stable in practice,
not stable in contract."

### B. The declaration layer

A subclass declares several distinct surfaces. Each is
a *capability declaration*, not a *reachable
operation*:

- **Columns** — `get_columns()` returns the full set of
  columns this table *can* render. Returning a column
  here doesn't mean operators see it (Section C).
- **Sortable columns** — `get_sortable_columns()`
  returns column slug → `[ orderby_param,
  default_descending ]`. Declares which columns can
  be sorted; doesn't mean any sort is actually applied
  unless the subclass's `prepare_items()` reads the
  `orderby` query var and modifies the query
  accordingly.
- **Bulk actions** — `get_bulk_actions()` returns
  action slug → label. Declares which bulk operations
  are *offered in the UI*; doesn't mean any operation
  has been performed unless `process_bulk_action()`
  has been wired.
- **Filters** — `extra_tablenav( $which )` (called
  with `'top'` or `'bottom'`) lets the subclass
  render filter dropdowns. Declares filter UI; doesn't
  apply filters unless the subclass reads the
  filter's submitted values.
- **Search** — `search_box()` renders the input;
  `prepare_items()` must read `$_REQUEST['s']` to
  actually search.

Each of these is the *declaration half* of a
declaration-then-action pair. The class layer
provides the UI; the subclass's `prepare_items()`
has to *act on* the operator's choices to produce
the corresponding query behavior.

### C. The reachability layer — multiple gates between declaration and operation

A column declared in `get_columns()` does not
automatically appear to a given operator. Several
gates sit between *declared* and *actually visible
and usable*:

| Gate                          | Mechanism                                               |
| ----------------------------- | ------------------------------------------------------- |
| **Capability**                | The admin page itself is gated by `current_user_can()` (or `manage_options`, `edit_posts`, etc., as registered in the page handler). No capability → no page → no table. |
| **Hidden columns**            | `get_hidden_columns( $screen )` returns the list of columns the *current operator* has hidden via Screen Options. Hidden columns are still in markup but visually suppressed. |
| **Per-user pagination**       | Each operator's "items per page" preference is stored as user meta and read in `prepare_items()` via `get_user_meta( …, '<screen>_per_page', true )`. |
| **Search**                    | The operator's search query (`$_REQUEST['s']`) further filters which items appear in `$this->items`. |
| **Sort selection**            | The operator's `?orderby=…&order=…` selection (or the table's default) determines result ordering. |
| **Pagination cursor**         | `?paged=N` selects which page of items the operator currently views. |
| **Bulk action capability**    | Even with the bulk action UI visible, the action's handler typically re-checks capabilities (`current_user_can('delete_post', $id)`) per item before executing. |

Reading from declared to reached:

```
get_columns() declares      → 8 possible columns
Hidden columns filter       → 5 columns visible to this operator
Per-user pagination         → 20 items shown
Search filter               → 12 items match search
Sort + page                 → ordered, sliced to current page
Bulk capability per item    → applies only to items operator can act on
```

The seven gates *compose*. The set of {column, item,
action} triples actually reachable for an operator is
the intersection of what `get_columns()` declared,
what the operator's preferences allow, what their
capabilities permit, and what their query parameters
selected.

### D. The operational flow — `prepare_items()` and bulk dispatch

`prepare_items()` is the central method. Its
responsibilities:

```
1. Read query parameters:
     $orderby   = $_REQUEST['orderby'] ?? 'default';
     $order     = $_REQUEST['order']   ?? 'desc';
     $search    = $_REQUEST['s']       ?? '';
     $paged     = $this->get_pagenum();   // helper for ?paged

2. Read screen-options preferences:
     $per_page  = $this->get_items_per_page( '<screen>_per_page' );

3. Read column metadata:
     $columns   = $this->get_columns();
     $hidden    = get_hidden_columns( get_current_screen() );
     $sortable  = $this->get_sortable_columns();
     $this->_column_headers = array( $columns, $hidden, $sortable );

4. Process bulk action FIRST (if posted):
     $this->process_bulk_action();
     // dispatch by current_action(); do work; redirect to clean URL.

5. Build query, set $this->items:
     $this->items = $this->fetch_items( $orderby, $order, $search, $per_page, $paged );

6. Tell the table about pagination:
     $this->set_pagination_args( array( … ) );
}
```

**Bulk action dispatch** typically lives in
`process_bulk_action()`:

```php
protected function process_bulk_action() {
    $action = $this->current_action();  // null if no action submitted
    if ( ! $action ) {
        return;
    }
    check_admin_referer( 'bulk-' . $this->_args['plural'] );  // nonce

    $ids = isset( $_REQUEST['my_items'] ) ? array_map( 'absint', (array) $_REQUEST['my_items'] ) : array();

    if ( 'delete' === $action ) {
        foreach ( $ids as $id ) {
            if ( current_user_can( 'delete_my_item', $id ) ) {
                $this->delete_item( $id );
            }
        }
        // Redirect to clean URL to avoid resubmission on refresh.
    }
}
```

Three properties to pin:

- **Bulk processing happens *before* item fetching.**
  Otherwise the table would render the pre-action
  state right after the action.
- **Nonce verification is the operator's job, not the
  framework's.** `check_admin_referer()` is a
  required step; omitting it leaves the bulk endpoint
  open to CSRF.
- **Per-item capability check is the operator's job,
  not the framework's.** The bulk action UI shows the
  action regardless of which items the operator can
  actually act on; the handler must enforce per-item
  permission.

### E. The class's "private but used" status

`WP_List_Table` is documented with `@access private`
and the warning "this class is meant for internal use
only." In practice every major plugin and theme that
ships an admin table uses it, and core's own admin
tables extend it. Three things this status implies in
practice:

- **No backwards-incompatibility commitment.** The
  class's signature can change between WP releases.
  Plugins that subclass it should test against new
  WP versions.
- **Some core-only methods are protected/private.**
  Subclass overrides are limited to the documented
  extension points (`prepare_items`, `get_columns`,
  the per-column render methods, `get_sortable_columns`,
  `get_bulk_actions`, `extra_tablenav`,
  `process_bulk_action`).
- **No alternative is fully ready.** The newer
  React-based DataViews (in `@wordpress/dataviews`)
  is the modern direction for *block-editor-adjacent*
  admin screens, but not a drop-in replacement for
  classic-admin tables. For now, `WP_List_Table` is
  the canonical operator-facing table substrate.

The documentation states this status; plugin authors
generally accept the trade-off because the
alternative (rebuilding the table look-and-feel +
bulk action plumbing + screen options integration
from scratch) is much costlier than occasional
adaptation when WP changes.

## WHY

### Why a separate "private" class rather than a public API

`WP_List_Table` was developed inside core to power
wp-admin's own list screens. Promoting it to a fully
public, contract-stable API would have meant freezing
its shape — including some choices that core might
later reconsider (the `_column_headers` shape, the
`process_bulk_action` flow, the screen options
integration). Marking it private kept core's options
open while letting plugins use the working
implementation.

The result is a Schrodinger's API: stable in practice
because changing it breaks too much; theoretically
unstable so core retains revision rights. Plugin
authors live with this; the alternative (a frozen
public API) might have meant *no* sharable substrate
at all.

### Why the multiple reachability gates

Each gate exists for a different reason:

- **Capability** — security and role separation.
- **Hidden columns** — operator preference; some
  operators want a denser view.
- **Per-user pagination** — accommodates different
  monitor sizes and workflow styles.
- **Search / sort / filter / pagination** —
  query-shape control.
- **Per-item capability** — fine-grained permission
  beyond per-screen access.

Collapsing them into fewer gates would conflate
distinct concerns. An operator who can *see* a
screen but cannot *delete* a particular item needs
both gates to be independent: the screen's capability
admits them, the per-item capability forbids the
specific operation.

### Why bulk processing fires before item fetching

If items were fetched first and bulk processing
fired second, the table would render the
pre-deletion state in the same request after a
successful delete bulk — confusing because the
just-deleted items would still appear in the table.
Processing first ensures the items reflect the
post-action state. The redirect-to-clean-URL pattern
after a successful bulk dispatch handles the
"refresh shouldn't resubmit" concern.

### Why search / sort / filter live in `$_REQUEST` rather than POST state

Admin tables live at GET URLs (mostly) because:

- URL-bookmarkable views (an operator can save the
  link to "all draft posts sorted by date").
- Pagination naturally works as `?paged=N`.
- Browser back/forward navigates between table
  states.

POST is reserved for actions that change state
(bulk dispatch, form submission). The
declaration-action distinction maps cleanly: GET for
viewing the table in different shapes; POST for
operator actions on items.

## WHEN NOT

Skip `WP_List_Table` if:

- You need a **fully custom design** that doesn't
  fit the wp-admin table look-and-feel. Build your
  own; lose the standard affordances but gain
  freedom.
- The screen is **block-editor adjacent** and the
  React DataViews component family fits. The
  modern surface for some admin screens is
  React-based; classic `WP_List_Table` may feel
  visually out of step.
- The dataset is **trivially small** (a few items,
  fixed shape). A simple `<table>` you render
  directly is less code than subclassing.
- You need **real-time updates** (WebSocket-driven,
  live-reloading). `WP_List_Table` is page-based;
  live updates require a different approach
  layered on top.

## COUNTER-PATTERNS

### Anti-pattern 1 — Rendering items in `__construct`

```php
public function __construct() {
    parent::__construct();
    $this->items = $this->fetch_items();  // wrong
}
```

`prepare_items()` is the canonical place. Items
fetched in the constructor are computed before
query parameters are read; pagination, sort, and
search will be wrong.

### Anti-pattern 2 — Forgetting `_column_headers`

```php
public function prepare_items() {
    $this->items = $this->fetch_items();
    // Missing $this->_column_headers assignment.
}
```

Without `_column_headers`, the table renders no
column headers and no sortable links. Always set:

```php
$this->_column_headers = array(
    $this->get_columns(),
    get_hidden_columns( get_current_screen() ),
    $this->get_sortable_columns(),
);
```

### Anti-pattern 3 — Bulk action without nonce verification

```php
protected function process_bulk_action() {
    if ( 'delete' === $this->current_action() ) {
        foreach ( $_REQUEST['my_items'] as $id ) {
            $this->delete_item( $id );
        }
    }
}
```

Missing nonce, missing capability check, missing
input sanitization. CSRF-able. The canonical guard:

```php
check_admin_referer( 'bulk-' . $this->_args['plural'] );
$ids = array_map( 'absint', (array) ( $_REQUEST['my_items'] ?? [] ) );
foreach ( $ids as $id ) {
    if ( current_user_can( 'delete_my_item', $id ) ) {
        $this->delete_item( $id );
    }
}
```

### Anti-pattern 4 — Treating Screen Options column-toggle as authoritative permission

```php
public function column_secret( $item ) {
    if ( ! in_array( 'secret', get_hidden_columns( get_current_screen() ), true ) ) {
        return $item['secret'];  // expose only if not hidden
    }
    return '';
}
```

Hidden columns is a *display preference*, not a
*security control*. The column markup is still in
the HTML (with CSS hiding it). Don't conflate
operator preference with operator authority. If a
column should be conditionally rendered for
security, gate by capability, not by Screen
Options.

### Anti-pattern 5 — Sorting by a column that wasn't declared sortable

```php
public function get_sortable_columns() {
    return array( 'title' => array( 'title', false ) );
    // 'date' not sortable.
}

public function prepare_items() {
    $orderby = $_REQUEST['orderby'] ?? 'title';
    // No validation; user could pass orderby=date and break the query.
}
```

Validate `orderby` against declared sortable
columns:

```php
$valid = array_keys( $this->get_sortable_columns() );
$orderby = in_array( $_REQUEST['orderby'] ?? '', $valid, true )
    ? $_REQUEST['orderby']
    : 'title';
```

### Anti-pattern 6 — Custom render path that bypasses `display()`

```php
public function display() {
    foreach ( $this->items as $item ) {
        echo '<div>' . esc_html( $item['title'] ) . '</div>';
    }
}
```

Overriding `display()` skips the bulk-action UI,
the search box, the pagination markup, the column
headers, and screen-options integration. If you
want a custom rendering, render outside
`WP_List_Table` entirely; if you want the framework's
affordances, override only the documented extension
points.

## OPERATIONAL NOTES

The list-table substrate's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *layered* form. Each subclass
  declaration (`get_columns`, `get_sortable_columns`,
  `get_bulk_actions`, filter UI) declares one
  surface; the operator's screen-options /
  capabilities / search / sort / pagination then
  filter through several gates to determine what is
  *reachable*. Naming Law 1 here is genuinely
  clarifying because the *gap* between "the column
  is declared" and "the operator can act on the
  column for this row" is exactly the multi-gate
  reachability story this chunk maps. The class's
  own "private API but widely used" status is
  itself a Law 1 instance: declared private,
  exposed in practice.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly*. Column slugs persist across
  declaration → render → sort URL parameter →
  query orderby. Bulk action slugs persist across
  declaration → checkbox value → form post →
  dispatch handler. Slug-based identity is the
  continuity surface. Worth one mention; not a
  section.
- **Doctrine 6 (Authority Mediation)** appears
  *softly*, in a layered form. Capability checks
  gate the screen, gate per-item bulk operations,
  and (in well-written subclasses) gate per-column
  data exposure for sensitive fields. This is
  *operational mediation* — the table itself is a
  rendering substrate; mediation happens at the
  capability check points, which the subclass wires
  up. Same softer expression as the surrounding
  chunks' write-channel mediation; not a full
  access-control framing. Worth one mention; not a
  section.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit, on par with the resolver lifecycle and
  plural-form chunks.* Three superficially Law-4-
  shaped features are not arbitration:
  - **Sorting.** A sortable column produces a query
    parameterization (`?orderby=col&order=asc`).
    The query then runs with that ordering. There
    is no candidate ladder; the operator's URL
    parameter (or default) directly drives the
    single sort. Multiple sortable columns don't
    "compete" — only one orderby applies at a
    time, picked by the operator. *Operator-
    selected ordering ≠ candidate arbitration.*
  - **Bulk action selection.** The dropdown lets
    the operator pick *one* action; the handler
    runs *that* action. No ladder, no fallback.
    User-selected option, not arbitrated choice.
  - **Filters.** A filter dropdown adds a query
    criterion. The query criterion narrows the
    result set; it doesn't pick winners from a
    ladder.
  Naming Law 4 here would be the same word-overlap
  category error as in the resolver and plural
  chunks. *Same surface vocabulary ("ordering,"
  "selection," "filter"), different mechanism
  (query parameterization vs candidate
  arbitration).*
- **Federation.** Despite the screen-options
  surface and the per-user preferences, this is
  not federation. The screen options + user meta
  storage is *single-system per-user state*, not
  multiple participants federated around a shared
  registry. Per-user customization is a different
  axis from cross-plugin co-existence; the
  `WP_List_Table` substrate does not federate
  multiple participants in any meaningful sense.
  Omitted to preserve federation's clarity where
  it does apply.
- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** All list-table code runs in the same
  PHP runtime as the admin page handler. No
  runtime boundary, no authority preservation
  across contexts. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** No build /
  runtime split inherent to the table mechanism.
  Omitted.
- **Section X archetypes.** A list-table substrate
  is not a "civilization." The
  Governance-heavy archetype is a frame for whole
  bounded contexts, not for a single rendering
  class. Omitted.

Two small literacy contributions worth pinning:

> *Registered surface ≠ reachable surface.* A
> column that exists in `get_columns()` is not
> the same as a column an operator can see and
> act on. Multiple gates — capability, screen
> options, search, sort, pagination, per-item
> permission — sit between declaration and
> reach. The composition of gates determines
> what *this operator at this moment* can
> actually do. Counting the reachable
> {operator, column, item, action} tuples is
> rarely the same as counting the declared ones.

This contribution extends the existence-vs-
operation toolkit (resolver lifecycle / JIT
translations / inspector controls / view-script
activation / data-wp-on actions) into
*multi-gate reachability*. The earlier
contributions distinguished two states (existence
vs operation) or three (existence → operation →
behavior); this one names the shape when the
intermediate state has *multiple independent
filters*.

> *Operator-selected ordering ≠ candidate
> arbitration.* A sortable column is a query
> parameter the operator can set; the query runs
> with that parameter. The mechanism is
> *parameterization* — the query is shaped by an
> input — not *arbitration* — there is no
> candidate ladder, no priority order, no
> first-match-wins. Both look like "ordering";
> only one is arbitration. The other is just
> sorted output of a parameterized query.

This contribution adds a fourth distinct anti-Law-4
example to the toolkit:

- *Need fulfillment ≠ option arbitration*
  (resolver lifecycle).
- *Availability ≠ activation*'s implicit anti-
  Law-4 (JIT translations).
- *Formula-driven selection ≠ candidate
  arbitration* (plural forms).
- *Operator-selected ordering ≠ candidate
  arbitration* (this chunk, list tables).

Together they form a small inventory of mechanisms
whose surface vocabulary tempts a Law 4 reading
but whose underlying shape is something else. The
inventory is prose-level literacy; not constitutional.

## CHECKLIST

When subclassing `WP_List_Table`:

- [ ] Implement `get_columns()`, `prepare_items()`,
      and at least one `column_*` render method
      (or `column_default()`).
- [ ] In `prepare_items()`, set
      `$this->_column_headers = array( $columns, $hidden, $sortable )`
      so headers and sortable links render.
- [ ] Process bulk actions *before* fetching
      items, then redirect to a clean URL after
      successful processing.
- [ ] Verify nonce in `process_bulk_action()`
      with `check_admin_referer( 'bulk-' . $this->_args['plural'] )`.
- [ ] Re-check capabilities per-item in bulk
      handlers; the bulk UI doesn't enforce
      per-item permission.
- [ ] Validate `$_REQUEST['orderby']` against
      declared sortable columns before passing it
      to a query.
- [ ] Don't conflate Screen Options column
      visibility with security gating. Use
      capabilities for security; Screen Options for
      preference.
- [ ] Accept that the class is `@access private`
      in core's docblock — test against new WP
      versions.

## REFERENCES

- `WP_List_Table` class reference. Documents the
  full extension surface (with the "private"
  caveat).
  https://developer.wordpress.org/reference/classes/wp_list_table/
- Administration menus handbook. Documents
  capability-gated admin page registration.
  https://developer.wordpress.org/plugins/administration-menus/
- `add_screen_option()` reference. Documents the
  per-user preferences surface (per-page, hidden
  columns) that drives reachability.
  https://developer.wordpress.org/reference/functions/add_screen_option/
- `WP_Screen` reference. Documents the current
  admin screen context that hidden-column queries
  read against.
  https://developer.wordpress.org/reference/classes/wp_screen/
- `get_hidden_columns()` reference. The function
  that returns the operator's hidden-column
  preference for a given screen.
  https://developer.wordpress.org/reference/functions/get_hidden_columns/

Cross-context:

- `admin-ui.settings-api` — adjacent admin
  surface (form-based settings registration). The
  two chunks together cover most classic-admin
  governance terrain: `settings-api` for
  configuration, `list-tables` for collection
  management.
- `admin-ui.admin-menus` — the registration that
  *places* a list-table page in the menu.
  Together they form the
  registration → display → action pipeline.
- `admin-ui.notices` — bulk-action results
  typically surface as admin notices after the
  redirect.
- `plugin-dev.capabilities-and-roles` — the
  capability schema this chunk's reachability
  gates depend on.
- `plugin-dev.security-boundaries` — the
  nonce / sanitize / capability discipline that
  bulk-action handlers must apply.
