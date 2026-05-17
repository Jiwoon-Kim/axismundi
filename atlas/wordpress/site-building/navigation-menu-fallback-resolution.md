---
rule_id: site-building.navigation-menu-fallback-resolution
domain: site-building
topic: composition-resolution
field_cluster: navigation-menu
wp_min: "3.0"
wp_recommended: "6.0+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/wp_nav_menu/
    section: "wp_nav_menu() — function reference + fallback_cb parameter"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_page_menu/
    section: "wp_page_menu() — default fallback callback"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/themes/functionality/navigation-menus/
    section: "Theme handbook — Navigation Menus + theme_location registration"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/core-blocks/#navigation
    section: "core/navigation block — fallback resolution behavior"
    captured: 2026-05-10
related:
  - site-building.template-hierarchy-and-resolution     # parallel resolution pattern (template fallback ↔ menu fallback)
  - site-building.block-pattern-resolution-and-precedence  # adjacent resolution pattern (pattern selection)
  - theme-config.templateParts                           # block-based navigation often lives in templateParts
  - block.dynamic-rendering                              # navigation block is dynamically rendered
  - plugin-dev.capabilities-and-roles                    # menu management requires `edit_theme_options` capability
---

# RULE — Navigation Menu Fallback Resolution — multi-source menu candidate arbitration

## WHEN

Rendering navigation in a theme or block template and need
to ensure menu output exists even when:
- No menu has been assigned to a theme location
- The named menu doesn't exist
- The site is freshly installed without configured menus
- The block editor renders a navigation block with no menu
  reference

Use this knowledge when:
- Implementing `wp_nav_menu()` in a classic theme template
- Configuring navigation block fallback behavior in
  block-based themes
- Customizing fallback presentation (custom callback or
  custom block fallback)
- Debugging navigation that "doesn't appear" or shows
  unexpected pages list

This is **3rd site-building chunk** documenting a third
distinct resolution-surface manifestation in this bounded
context (after `template-hierarchy` + `block-pattern-
resolution`).

## SHAPE

### A. Classic API — `wp_nav_menu()` resolution chain

```php
// In theme template (header.php / template-parts/navigation.php)
wp_nav_menu( array(
    'theme_location' => 'primary',         // theme location
    'menu'           => '',                // specific menu (name/ID/slug)
    'container'      => 'nav',
    'container_class'=> 'main-navigation',
    'menu_class'     => 'menu',
    'fallback_cb'    => 'wp_page_menu',    // fallback if no menu found (default)
    'depth'          => 0,
    'walker'         => '',
    'echo'           => true,
) );
```

| arg | role |
|---|---|
| `theme_location` | named slot registered via `register_nav_menus` |
| `menu` | specific menu (name / ID / slug) — bypasses theme_location |
| `fallback_cb` | callback when no menu found; default `wp_page_menu`; pass `false` to render nothing |
| `container` | wrapping element tag (default `div`); `false` = no wrapper |
| `walker` | custom Walker class for menu HTML generation |
| `depth` | maximum nesting depth (0 = unlimited) |
| `echo` | echo (true) or return string (false) |

### B. Resolution chain (5-stage candidate arbitration)

```
Stage 1: explicit menu argument
   IF $args['menu'] is provided AND menu exists
   → use that menu; SKIP remaining stages

Stage 2: theme_location lookup
   ELSE IF $args['theme_location'] is provided
   → look up menu assigned to that location via
     get_theme_mod( 'nav_menu_locations' )[ $location ]
   → IF assignment exists AND assigned menu exists
   → use that menu; SKIP remaining stages

Stage 3: filter intervention
   apply_filters( 'wp_nav_menu_args', $args )
   → plugins/themes may inject menu args at this point
   → IF filter resolution produces menu → use; SKIP remaining

Stage 4: fallback callback invocation
   ELSE invoke $args['fallback_cb'] with $args
   → default: wp_page_menu( $args )
   → IF fallback_cb === false → render nothing

Stage 5: render output
   Walker class generates HTML;
   container wrapping applied;
   wp_nav_menu_items filter applied to final HTML
```

| stage | character |
|---|---|
| 1 — Explicit menu | direct candidate selection |
| 2 — Theme location | indirect candidate via theme_location → menu assignment |
| 3 — Filter intervention | plugin/theme arbitration override |
| 4 — Fallback callback | default-output candidate |
| 5 — Walker rendering | candidate → final output |

### C. Default fallback — `wp_page_menu()`

```php
// Default fallback callback signature
wp_page_menu( array(
    'sort_column'   => 'menu_order, post_title',
    'menu_class'    => 'menu',
    'include'       => '',
    'exclude'       => '',
    'echo'          => true,
    'show_home'     => false,
    'link_before'   => '',
    'link_after'    => '',
    'before'        => '<ul>',
    'after'         => '</ul>',
    'item_spacing'  => 'preserve',
) );
```

Lists site PAGES (not posts), respecting page hierarchy.
Often surprising for sites where users expected nothing OR
expected a specific menu but had assignment misconfigured.

### D. Theme location registration

```php
// In functions.php (or include file loaded on after_setup_theme)
function my_theme_register_nav_menus() {
    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'my-theme' ),
        'footer'  => __( 'Footer Menu', 'my-theme' ),
        'social'  => __( 'Social Links Menu', 'my-theme' ),
    ) );
}
add_action( 'after_setup_theme', 'my_theme_register_nav_menus' );
```

Registered locations appear in wp-admin → Appearance →
Menus → Manage Locations for assignment by site admins.

### E. Block-based navigation — different resolution model

```html
<!-- wp:navigation { "ref": 42 } /-->
```

Block-based navigation (`core/navigation` block) uses
**explicit reference** to menu ID:

| navigation block ref state | resolution behavior |
|---|---|
| `ref` attribute present + menu exists | render referenced navigation menu post |
| `ref` attribute present + menu DOES NOT exist | fallback behavior (typically: empty rendering OR placeholder) |
| `ref` attribute absent + block has inner blocks | render inner blocks as navigation items |
| `ref` attribute absent + block has no inner blocks | fallback creation: convert pages to navigation items + create new navigation post (one-time) |

> **Critical distinction**: classic `wp_nav_menu()`
> fallback is **render-time** (each request); block-based
> navigation fallback is **create-time** (creates persistent
> navigation post on first encounter).

This is a structurally different fallback model.

### F. Custom fallback callback patterns

```php
// Custom fallback: render nothing
wp_nav_menu( array(
    'theme_location' => 'primary',
    'fallback_cb'    => false,
) );

// Custom fallback: render link to menu admin (developer convenience)
wp_nav_menu( array(
    'theme_location' => 'primary',
    'fallback_cb'    => function( $args ) {
        if ( current_user_can( 'edit_theme_options' ) ) {
            $admin_url = admin_url( 'nav-menus.php' );
            echo '<a href="' . esc_url( $admin_url ) . '">'
                . esc_html__( 'Set up a menu', 'my-theme' )
                . '</a>';
        }
    },
) );

// Custom fallback: render specific page list with restrictions
wp_nav_menu( array(
    'theme_location' => 'primary',
    'fallback_cb'    => 'my_theme_simple_nav_fallback',
) );

function my_theme_simple_nav_fallback( $args ) {
    wp_page_menu( array(
        'show_home' => true,
        'depth'     => 1,  // top-level pages only
    ) );
}
```

## REQUIRES

- WP environment (nav menu functions are WP core).
- For `theme_location` resolution: theme MUST call
  `register_nav_menus` (typically in `after_setup_theme`).
- For block-based navigation: WP 5.9+ recommended; block
  themes; navigation post type registered.
- For custom fallback: callback function MUST be available
  at render time (not just declared in functions.php — must
  be loaded).
- For `current_user_can` check inside fallback: user
  context must be initialized (typically true during
  template render).
- ⚠ Specific behaviors: navigation block fallback creation
  semantics across WP versions, ref attribute behavior with
  deleted menus, theme switch impact on theme_location
  assignments — verification-needed.

## INVARIANTS

### 1. Fallback resolution is multi-source candidate arbitration

The 5-stage resolution chain (Section B) IS multi-source
candidate arbitration:
- Multiple candidate sources (explicit menu / theme_location /
  filter / fallback callback / no-output)
- Arbitration logic (priority order: explicit > location >
  filter > fallback)
- Single resolved output (one menu candidate wins)

This is structurally analogous to:
- `template-hierarchy-and-resolution` (template candidate
  arbitration)
- `block-pattern-resolution-and-precedence` (pattern
  candidate arbitration)

> **3rd site-building chunk documenting a Resolution
> Surface manifestation**. site-building's intra-context
> Resolution density continues building (per Phase 8.5+
> Resolution Surface candidate observations).

### 2. fallback_cb default behavior surprises users

Default fallback `wp_page_menu` lists ALL published pages.
This often produces UNEXPECTED output:
- New sites with auto-published "Sample Page" → menu shows
  "Sample Page"
- Sites with many pages (e.g., legal pages) → menu becomes
  long page list
- Sites that just removed menu assignment → page list
  appears

> **Default fallback is OPTIMISTIC behavior** — assumes
> page list is preferable to empty navigation. For some
> sites this is wrong; explicit `fallback_cb => false`
> needed.

### 3. theme_location is INDIRECT reference, not direct menu identity

`theme_location` is a NAMED SLOT, not a menu identity:
- Theme registers location ('primary') via
  `register_nav_menus`
- Site admin assigns menu to location via wp-admin →
  Menus → Manage Locations
- Assignment stored in `theme_mod` (per-theme; lost on
  theme switch UNLESS imported)

Implication: switching themes BREAKS menu assignments
unless the new theme registers same location names.

### 4. Block-based navigation resolution differs structurally

Classic vs block-based navigation fallback character:

| dimension | classic `wp_nav_menu()` | block `core/navigation` |
|---|---|---|
| Resolution timing | each request (render-time) | first-encounter (create-time when ref absent) |
| Menu storage | `nav_menu_item` post type + `taxonomy = nav_menu` | `wp_navigation` post type (single navigation entity) |
| Fallback semantics | output substitution per request | persistent fallback creation (one-time) |
| Reference model | indirect (theme_location → menu) | direct (ref attribute → navigation post ID) |
| Walker customization | Walker class + filters | block render filters + InnerBlocks |
| theme_location concept | YES | NO (block-based themes don't use theme_locations for navigation) |

This is structurally significant: block-based navigation
fallback is **persistence event**, not **render decision**.

### 5. Resolution composes with capability gating

Menu management requires capability:
- `edit_theme_options` for menu editing in classic admin
- `manage_options` for theme location assignment
- Block-based navigation editing: capability checked per
  navigation post

Fallback callbacks may incorporate capability checks (e.g.,
showing "Set up menu" link only to capable users — see
SHAPE Section F custom fallback example).

This is Doctrine 6 6a (Capability-gated mediation) at
fallback-callback layer — secondary, not central to
fallback resolution itself.

### 6. Filter intervention enables programmatic menu arbitration

`wp_nav_menu_args` filter (Stage 3) allows plugins/themes
to override menu resolution programmatically:

```php
add_filter( 'wp_nav_menu_args', function( $args ) {
    if ( is_user_logged_in() && empty( $args['menu'] ) ) {
        $args['menu'] = 'logged-in-menu';
    }
    return $args;
} );
```

This is **arbitration override surface** — context-aware
menu selection (per-user / per-page / per-condition).

### 7. Walker classes provide rendering arbitration

Once menu candidate resolved (Stage 4 complete), Walker
class arbitrates HTML output:
- Default Walker: `Walker_Nav_Menu`
- Custom Walker can override `start_lvl` / `end_lvl` /
  `start_el` / `end_el` methods
- Filters at each Walker stage allow inline modification

Walker is **rendering-stage arbitration** within
already-resolved menu data.

### 8. Empty navigation is legitimate UX state

Per modern web conventions, **empty navigation is acceptable**
when:
- Site is in development / setup
- Single-page site without navigation needs
- Navigation moved to sidebar / footer / hamburger menu
- Editorial decision to hide navigation on specific pages

Setting `fallback_cb => false` makes this explicit. Themes
should NOT assume default page-list fallback is universally
appropriate.

## VERIFICATION NEEDED

`status: stable`. Items requiring verification:

- Navigation block fallback creation semantics across WP
  versions (when does it create new navigation post vs
  use existing?)
- `ref` attribute behavior when referenced menu is deleted
  (404? empty? auto-create new?)
- Theme switch impact on theme_location assignments
  (preserved? cleared? imported?)
- Walker class compatibility across WP versions (deprecated
  methods? new hooks?)
- Block-based navigation fallback in FSE vs classic theme
  contexts.
- Performance implications of `wp_page_menu` fallback on
  sites with many pages (caching? lazy loading?).
- Multilingual plugins (Polylang / WPML) impact on menu
  resolution (per-language menus?).
- `wp_nav_menu_objects` filter timing relative to fallback
  invocation.
- Rest API navigation menu endpoints (read/write semantics).
- AMP plugin compatibility with custom Walker classes.

For practical decisions: empirical testing per theme +
plugin combination + WP version.

## ANTIPATTERNS

- ❌ **Assuming default fallback is appropriate**. Default
  `wp_page_menu` lists ALL pages — often surprising. Set
  `fallback_cb => false` explicitly when no menu = no
  navigation.
- ❌ **Using both `menu` and `theme_location` arguments**.
  `menu` arg takes precedence and bypasses
  `theme_location`. Pick one resolution strategy per
  invocation.
- ❌ **Forgetting to register theme locations**. Theme
  locations MUST be registered via `register_nav_menus`
  before they can be used by `wp_nav_menu()`.
- ❌ **Custom fallback without capability check**. Showing
  "Set up menu" UI to unauthenticated users = noise. Wrap
  with `current_user_can( 'edit_theme_options' )`.
- ❌ **Walker class without escaping**. Custom Walker
  output MUST escape attributes + URLs + text. Failure =
  XSS surface.
- ❌ **Block-based navigation without ref OR inner blocks**.
  Empty navigation block produces fallback creation event
  (may create unexpected navigation post). Be explicit.
- ❌ **Hardcoding menu IDs in theme code**. Menu IDs vary
  per site. Use slug or theme_location for portability.
- ❌ **Calling `wp_nav_menu()` outside theme template
  context**. Function expects template render context for
  some defaults; calling from arbitrary actions may produce
  unexpected behavior.
- ❌ **Customizing fallback to silently fail**. Empty
  fallback that LOGS but doesn't render anywhere = invisible
  failure. Either render visible fallback OR set
  `fallback_cb => false` explicitly.
- ❌ **Confusing classic and block-based navigation
  paradigms**. Classic theme location concept does NOT
  apply to block themes; navigation block does NOT use
  `wp_nav_menu()`. Choose paradigm per theme architecture.
- ❌ **Ignoring `wp_nav_menu_args` filter side effects**.
  Plugin filters may modify args; theme code that assumes
  unmodified args = brittle. Filter awareness matters.

## RELATED

- `site-building.template-hierarchy-and-resolution` —
  parallel resolution pattern (template candidate
  arbitration); shares site-building bounded context's
  Resolution Surface character.
- `site-building.block-pattern-resolution-and-precedence` —
  adjacent resolution pattern (pattern candidate
  arbitration); 2nd Resolution Surface manifestation in
  site-building.
- `theme-config.templateParts` — block-based themes
  organize navigation via template parts (often `header`
  template part contains navigation block).
- `block.dynamic-rendering` — navigation block is
  dynamically rendered server-side; render_callback
  produces final navigation HTML.
- `plugin-dev.capabilities-and-roles` — menu management
  capabilities (`edit_theme_options` / `manage_options`);
  fallback callbacks may incorporate capability checks.

## META

**3rd site-building chunk; navigation menu fallback
resolution.**

### Doctrinal classification (natural reference; per Phase
8.27 doctrine "Reference when clarifying. Omit when
unnecessary.")

This chunk's resolution chain (5-stage candidate
arbitration) is direct **Doctrine 5 (Arbitration ↔
Resolution Paired Operations)** manifestation:
- Arbitration stage: 5-stage chain (explicit / location /
  filter / fallback / no-output candidates evaluated)
- Resolution stage: single menu candidate selected;
  Walker renders final output

This is **3rd intra-site-building Resolution Surface
manifestation** (after template-hierarchy + block-pattern-
resolution). Site-building's intra-context Resolution
Surface density continues building (per Phase 8.5+
candidate observations).

### Constitutional element profile

- **Doctrine 5** STRONG — multi-stage arbitration +
  single-output resolution (canonical)
- **Law 1 (Declaration ≠ Exposure)** PARTIAL — theme
  registers location ≠ admin assigns menu ≠ runtime
  resolves ≠ Walker renders (4-form gap)
- **Law 4 (Arbitration Compiler)** STRONG — explicit
  precedence rules (priority order across 5 stages)
- **Doctrine 6 6a (Capability-gated mediation)** weak/
  adjacent — fallback callbacks may include capability
  checks but not central to resolution
- **Bridge Pattern** absent
- **Federation Pattern** partial — multiple plugins/themes
  may register locations + filter args

### Section X archetype-aware reference (natural; not exhaustive)

Site-building bounded context has not been explicitly
archetype-classified. Per Phase 8.27 deployment doctrine,
Section X reference is OPTIONAL for chunks where
classification is not actively informing the analysis.

For this chunk: site-building exhibits **Composition-
runtime character** (per Phase 7 audit bounded context
character taxonomy observation) — distinct from
governance-architectural and computational-architectural
character categories.

> **Section X note**: site-building may eventually exhibit
> distinct civilization archetype (composition-runtime
> character profile), but per Hybrid-before-Proliferation
> discipline + ~50% pure-fit / hybrid distribution
> observed across analyzed contexts: classification deferred
> to natural emergence during forward authoring; no
> active classification work.

### Q8 — forward adjudication

| classification candidate | verdict |
|---|---|
| Doctrine 5 (Arbitration ↔ Resolution Paired Operations) | **Confirm** — 5-stage arbitration + single resolution canonical |
| Resolution Surface (Recurring cross-context candidate; KB-Wide REFUSED Phase 7.8) | **Confirm** — 3rd intra-site-building manifestation |
| Law 4 (Arbitration Compiler) | **Confirm** — explicit precedence rules |
| Law 1 (Declaration ≠ Exposure) | **Confirm** — 4-form gap (registered / assigned / resolved / rendered) |
| Doctrine 6 (Authority Access Mediation) | **Diverge** — capability checks in fallbacks are adjacent, not central |
| Federation Pattern | **Confirm** — partial (multi-plugin location/filter federation) |
| Bridge Pattern (Law 3b) | **Diverge** — not present |

### Q9 — backward retroactive trigger

> **Q9 ANSWER: NO** — no latent uncodified law revealed by
> this chunk. Resolution Surface character was already
> established Phase 8.5+ via cascade-aggregation Q9 retro
> + variations/transforms Q9 retros + this chunk continues
> the established pattern.

### Q10 — sub-pattern emergence

> **Q10 ANSWER: NO** — no NEW sub-pattern observed within
> existing law/variant/sub-pattern. The 5-stage resolution
> chain is operational pattern of Doctrine 5; not new
> sub-pattern.

### Q11 — N/A (per-audit invocation; this is forward chunk)

### Constitutional restraint demonstration

This chunk references v2 vocabulary (Doctrine 5 + Law 1 +
Law 4 + Resolution Surface) where it CLARIFIES the chunk's
structural role. Section X reference is light (single
"Composition-runtime character" mention; explicit deferral
of active classification work).

> **No new candidates surfaced.** No new sub-patterns. No
> new sub-elements. v2 vocabulary used AS REFERENCE
> WHERE CLARIFYING; OMITTED WHERE UNNECESSARY.

This demonstrates Phase 8.27 deployment doctrine in
practice: "Reference when clarifying. Omit when
unnecessary. Deploy naturally."

### KB-wide pattern recurrence updates

**Resolution Surface intra-context density**: site-building
3rd manifestation (template-hierarchy + block-pattern-
resolution + this chunk). Continues established intra-
context density.

**Doctrine 5 Hybridized variant manifestations**: this
chunk's 5-stage arbitration + single resolution is
canonical Doctrine 5 paired operations character.

### Status: `stable`

`wp_nav_menu()` API mature since WP 3.0 (added 2010);
`fallback_cb` parameter stable; theme_location concept
stable. Block-based navigation evolves more rapidly (WP
5.9+); core fallback semantics settled.

### Anticipated next chunks (priority)

Per Phase 8.27 deployment doctrine ("Does this framework
quietly improve chunk quality?" — answered YES; deploy
naturally):

1. **Additional site-building chunks** (block-based
   navigation block deeper analysis; site editor template
   composition; etc.) as natural curriculum continues.

2. **Untouched bounded context entries** (build-tooling
   wp-scripts overview chunk; etc.) when curriculum
   scope reaches them.

3. **Q9 retros** opportunistically if new chunks reveal
   latent patterns in earlier material.

Forward authoring under v2 + Section X archetype-aware
framework as analytical reference. Section X classification
work deferred to natural emergence during continued
deployment.
