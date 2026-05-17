---
rule_id: theme-config.global-styles-user-persistence
domain: theme-config
topic: cross-context-persistence-lifecycle
field_cluster: user-customization-substrate
wp_min: "5.9"
wp_recommended: "6.5+"
status: stable
language: js-and-php
sources:
  - url: https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/
    section: "Global Settings & Styles — user customizations layer"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/classes/wp_theme_json_resolver/get_user_data/
    section: "WP_Theme_JSON_Resolver::get_user_data() — user-layer reading"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/rest-api/reference/global-styles/
    section: "REST API — Global Styles endpoints (read/write)"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2022/01/04/improvements-to-the-creation-and-management-of-global-styles-revisions/
    section: "Make Core — Global styles persistence + revisions"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/themes/global-settings-and-styles/styles/style-variations/
    section: "Style Variations — theme-shipped alternate global styles"
    captured: 2026-05-10
related:
  - style-engine.theme-json-source-layering         # the merge mechanism this chunk's user layer participates in
  - style-engine.per-block-style-attribution        # downstream realization at per-block level
  - admin-ui.settings-api                           # adjacent admin governance surface (different mechanism)
  - plugin-dev.register-rest-route                  # the REST infrastructure this chunk's persistence consumes
  - plugin-dev.capabilities-and-roles               # edit_theme_options is the capability gating user-layer writes
---

# RULE — Global Styles user persistence — the cross-context lifecycle from UI mutation to resolved cascade

## WHEN

You need to reason about how user customizations
made through the Site Editor's Global Styles UI
become *lived style reality* — what storage
they hit, how they survive theme switching,
how they re-enter the resolver on the next
request, and how the lifecycle interacts with
theme-shipped variations. Use this knowledge
when:

- Diagnosing "my Global Styles change saved but
  doesn't appear on the front end" — almost
  always: cache, resolver call timing, or
  per-theme post lookup.
- Building a plugin that programmatically
  modifies global styles (e.g., a
  preset-pack importer).
- Implementing custom style variations in a
  theme.
- Reading core's `WP_Theme_JSON_Resolver::get_user_data()`
  and following the `wp_global_styles` post
  lookup.
- Switching themes and reasoning about which
  customizations persist (per-theme) vs
  which are lost.
- Evaluating cross-context performance:
  REST roundtrips, resolver caching, generated
  stylesheet caching.

This chunk does **not** cover:

- The 3-layer source merge itself — covered in
  `style-engine.theme-json-source-layering`.
  This chunk is the *user-layer persistence
  lifecycle* that participates in that merge;
  the merge mechanics are that chunk's
  territory. Section H pins the distinction
  explicitly.
- Per-block style attribution (how block
  attributes consume the resolved cascade) —
  covered in
  `style-engine.per-block-style-attribution`.
- Style engine CSS generation — covered in
  `style-engine.css-variable-emission` and
  related.
- Block-level style customization (per-block-
  instance attributes like `backgroundColor`
  on a single button). Those flow through
  per-block attribution, not through this
  chunk's `wp_global_styles` mechanism.

The principle this chunk operates under: **A
user customization saved through the Site
Editor's Global Styles UI moves through
seven distinct lifecycle stages between *click
in editor* and *rendered CSS on the front
end*. The mechanism is *cross-context
persistence with stage-level doctrinal
diversity* — different stages have different
runtime contexts, different doctrinal fits,
and different failure modes.**

## SHAPE

### A. The 7-stage user-customization lifecycle

```
Stage 1 — DECLARATION (theme source)
   theme.json (+ optional variations in styles/*.json)
   declared by the theme author at theme-build time.
              │
              ▼
Stage 2 — UI INTERACTION (editor JS runtime)
   User opens Site Editor's Global Styles panel.
   Editor loads current resolved state via REST.
   User edits color, typography, spacing, etc.
   Editor maintains pending state in core/edit-site store.
              │
              ▼
Stage 3 — SAVE (cross-runtime transport)
   User clicks Save. Editor JS POSTs to REST endpoint
   /wp/v2/global-styles/{id} with the user-layer data.
              │
              ▼
Stage 4 — PERSISTENCE (PHP, DB write)
   Server validates capability (edit_theme_options).
   Updates wp_global_styles post (post_content holds JSON).
   Optional: revision saved.
              │
              ▼
Stage 5 — RESOLVER MERGE (PHP, next request)
   On next request, WP_Theme_JSON_Resolver runs:
     - get_core_data()  → core defaults
     - get_theme_data() → theme.json + variation
     - get_user_data()  → reads updated wp_global_styles post
     - get_merged_data() → composes all three
              │
              ▼
Stage 6 — CSS GENERATION (style engine)
   wp_get_global_stylesheet() emits CSS from merged data.
   Custom properties for presets; element/block style rules.
   Cached after first generation.
              │
              ▼
Stage 7 — RENDERING (front end)
   Browser receives HTML with the generated stylesheet.
   Block instances consuming preset slugs (via class)
   resolve to current values from the cascade.
```

Seven stages spanning **three runtime contexts**
(editor JS, REST/PHP, frontend rendering),
**two storage layers** (transient editor
state + persistent `wp_global_styles` post),
and **two distinct merging operations** (the
3-layer resolver merge in Stage 5 + the CSS
cascade at the browser).

The chunk's central observation: **most
"Global Styles isn't saving" or "saved but not
showing" issues live between Stages 3 and 6**.
The diagnostic question is which transition
failed: REST authentication, capability check,
post update, resolver cache, stylesheet cache,
or browser cache.

### B. The `wp_global_styles` post type

User customizations are stored as a single
custom post per theme:

| Field           | Content                                             |
| --------------- | --------------------------------------------------- |
| `post_type`     | `wp_global_styles`                                  |
| `post_status`   | `publish`                                           |
| `post_name`     | `wp-global-styles-{theme-slug}`                     |
| `post_title`    | Auto-generated, theme-display-name based             |
| `post_content`  | JSON string with theme.json-shaped structure (settings + styles + version) |
| `tax_input`     | (terms identifying the theme)                       |

Three properties to pin:

- **One post per theme.** Switching themes
  doesn't lose customizations — each theme
  has its own `wp_global_styles` post,
  identified by the post_name pattern. Going
  back to a previous theme restores its
  customizations.
- **`post_content` is JSON, not HTML.** Stored
  as a string; parsed on every read. The
  shape mirrors `theme.json` (settings,
  styles, version, etc.) but contains
  *only* the user's deltas, not a full
  theme.json copy.
- **Revisions optional but supported.**
  WordPress can save revisions of the
  `wp_global_styles` post (admin UI exposes
  this). Each revision is a snapshot of the
  user's customizations at save time.

### C. The Site Editor's Global Styles UI as governance surface

The UI lives in the Site Editor (
`wp-admin/site-editor.php`) under the "Styles"
sidebar. It exposes:

- Color palette editing (extending the
  theme's palette, modifying preset values).
- Typography (font families, sizes, weights).
- Layout / spacing preset adjustments.
- Per-element styling (e.g., default heading
  color, link color).
- Per-block style overrides (each block type
  can have its own user-layer styling).

The UI is **governance input**, not the
final styling. User actions in the UI:

- Update a *pending* in-memory state (in the
  `core/edit-site` data store).
- Trigger live preview rendering in the
  editor canvas.
- Don't persist until the user clicks Save.

Until Save, the entire lifecycle (Stages 4-7)
hasn't happened. The UI's pending state is
ephemeral — closing the browser without
saving discards it.

This is a Law-1-shape worth pinning explicitly:
*UI interaction is governance input;
persistence is what makes the input lived
reality*. The two stages are observably
distinct.

### D. The REST persistence layer

The Save action POSTs to a REST endpoint:

```
POST /wp/v2/global-styles/{id}
   Body: { settings: {...}, styles: {...}, ... }
   Auth: Cookie + REST nonce
   Capability: edit_theme_options (typically administrators)
   Response: updated post object
```

The endpoint:

1. Validates the request (nonce, capability).
2. Sanitizes the JSON payload.
3. Updates the `wp_global_styles` post
   (`wp_update_post` with the new
   `post_content`).
4. Optionally creates a revision.
5. Returns the updated representation.

Failures at this stage have specific
diagnostics:

- **401**: cookie/nonce expired (user logged
  out mid-edit, or session timeout).
- **403**: user lacks `edit_theme_options`
  capability.
- **400/422**: payload validation failed
  (malformed JSON, schema violation).
- **5xx**: server-side write error.

The REST endpoint is the *persistence
mechanism*; it's also the only sanctioned
write path for the user layer. Direct DB
writes to the `wp_global_styles` post bypass
schema validation and capability checks —
fragile.

### E. Theme switching and per-theme persistence

When a user activates a different theme:

```
1. WP detects theme activation.
2. WP looks for an existing wp_global_styles post
   with post_name = wp-global-styles-{new-theme-slug}.
3. If found: that post becomes the active user-layer.
   Old theme's wp_global_styles post is unchanged
   (still in DB, not active).
4. If not found: WP creates a new wp_global_styles
   post for the new theme (with empty post_content,
   meaning no user customizations yet).
```

Three properties:

- **Customizations are theme-scoped.**
  Switching to a new theme starts with that
  theme's defaults plus any prior
  customizations *for that theme* (or empty
  if first activation).
- **Switching back restores.** Returning to a
  previously-customized theme picks up its
  `wp_global_styles` post — customizations
  return verbatim.
- **Posts persist regardless of theme
  state.** Inactive themes' `wp_global_styles`
  posts remain in the DB. They take minimal
  space and can be re-activated by switching
  back to that theme. Cleanup is rarely
  necessary.

### F. Style variations — theme-shipped alternates

A theme can ship multiple "variations" — files
in `styles/*.json` that act as alternative
styling presets:

```
my-theme/
  theme.json           # default styles
  styles/
    minimal.json       # alternate variation
    bold.json          # another alternate
    seasonal.json      # another
```

When the user selects a variation in the
Global Styles UI:

1. The variation's JSON is read.
2. Its values are *copied into* the
   `wp_global_styles` post's `post_content`
   (effectively becoming user customizations).
3. The user can then further modify on top
   of the variation's values.

The variation files are **read-only sources**.
The selection process *materializes* a
variation's values into the user-layer
post; from then on, those values are
"the user's customizations" (even if the
user didn't manually edit them).

Two implications:

- **Selecting a variation is a destructive
  action**: it overwrites whatever was in the
  user layer with the variation's values
  (with optional confirmation).
- **The variation file itself is never the
  active layer.** The user layer
  (`wp_global_styles` post) carries copied
  values. Variation files exist only to
  provide initial-state alternatives.

### G. Re-entry: next-request resolution flow

After a save in Stages 3-4, the next request:

```
1. Browser requests a frontend page.
2. WordPress initialization runs.
3. wp_get_global_styles() (or wp_get_global_stylesheet())
   is called by the rendering pipeline.
4. WP_Theme_JSON_Resolver:
   a. get_core_data() — loads cached core defaults.
   b. get_theme_data() — reads theme.json (cached after first read).
   c. get_user_data() — reads wp_global_styles post for active theme.
   d. get_merged_data() — composes all three with merge semantics
      (covered in style-engine.theme-json-source-layering).
5. Style engine generates CSS from merged data
   (cached as transient).
6. Page rendered with the generated stylesheet.
```

Stage 5 is **the same merge mechanism** as
Stage 5 was for the editor's load (when the
user *opened* Global Styles UI). The user-
layer data the editor loaded then is the
same user-layer data the front end loads now
— except the user-layer post may have been
updated between editor-open and frontend-
request.

The merge runs *every request* (modulo cache).
The user customization is consulted every time
the resolved styles are needed. Cache
invalidation on Save flushes both the resolver
cache and the generated-stylesheet cache so
the next request sees the updated state.

### H. Distinction from `style-engine.theme-json-source-layering`

This chunk and the source-layering chunk are
adjacent but distinct:

| Aspect           | source-layering (8.35)                            | This chunk (8.44)                                |
| ---------------- | ------------------------------------------------- | ------------------------------------------------ |
| Topic            | Where layers exist, how they merge                | How user layer is created / stored / re-entered |
| Scope            | The merge mechanism itself                        | The full lifecycle including UI + REST + DB     |
| Stage focus      | Stage 5 (resolver merge) of this chunk's pipeline | Stages 1-7 of the lifecycle                     |
| Key question     | "What does the resolver do with N layers?"        | "How does a user layer come to exist?"           |

The two chunks together cover the *complete*
user-customization story: this chunk
documents *how the user layer enters and
persists*; that chunk documents *how it
merges with the other layers*. Read
together, they form the *user customization
+ source layering* pair across editor-
runtime and PHP-runtime.

## WHY

### Why a custom post type for user customizations

Storage alternatives — option, custom DB
table, file system — were considered. The
custom post type approach was chosen because:

- **Built-in revision support.** WordPress's
  post revision system applies; users get
  history of their customization changes.
- **Integration with existing capability
  system.** Posts naturally support per-user
  permissions, drafts, autosave, etc.
- **Theme switching becomes natural.** One
  post per theme, identified by post_name
  pattern, leverages the existing post lookup
  infrastructure.
- **REST API integration is cheap.** Custom
  post types automatically get REST endpoints
  with little additional code.

The cost is `wp_global_styles` posts visible
in some admin queries that don't filter post
type; the benefit is leveraging the entire
posts-infrastructure ecosystem rather than
inventing storage.

### Why per-theme persistence rather than global

A site might switch themes for redesigns,
seasonal changes, or A/B testing. If
customizations were global, switching themes
would discard them; switching back would
require redoing all the work.

Per-theme persistence preserves work. Each
theme has its own customization slate; users
can experiment with multiple themes without
losing their preferences for any of them.

The cost is some DB clutter from inactive
themes' posts; the benefit is *user trust* —
operators don't fear theme-switching costs
because they know customizations won't be
lost.

### Why variations copy values into user layer rather than chaining

A four-layer chain (core / theme / variation
/ user) was considered. Two-layer composition
(theme / user) with variations as
*initial-state copies* was chosen because:

- **Conceptually simpler.** The user layer
  always represents "the user's intent"; if
  the user picked a variation as a starting
  point, the variation's values *are* now
  the user's intent.
- **Modifiability.** After picking a
  variation, the user can edit individual
  values. If variations were a separate
  layer, every edit would require
  understanding which layer to modify.
- **Theme-author intent preservation.** The
  theme's variation files remain pristine
  reference points (read-only). The user's
  copy diverges from there but the originals
  stay available.

### Why the resolver runs every request

Caching the merged result aggressively (e.g.,
hour-long transients) was considered. The
chosen approach — cache per-request, with
invalidation on save — fits because:

- **Save invalidation is reliable.** Saving
  user customizations is the only common way
  the resolved styles change. Hooking
  invalidation into the save action is
  precise.
- **Per-request caching avoids stale state.**
  An hour-long transient could mean a saved
  customization doesn't appear for an hour
  — bad user experience.
- **The merge is cheap once cached.** The
  per-request cache (computed once, reused
  many times within the request) handles the
  N-callers-per-request case.

## WHEN NOT

Skip the user-layer persistence reasoning if:

- The plugin / theme writes styles **directly
  to theme.json** (theme-author scope, not
  user customization). User-layer mechanisms
  don't apply.
- The customization is **per-block-instance**
  (like a single button's color). That flows
  through per-block style attribution, not
  through user-layer Global Styles.
- The plugin needs **per-page or per-section
  styling** that varies within a single
  request. User layer is site-wide; per-page
  styling needs different mechanisms.
- The customization is **operator-runtime
  preference** (welcome guide visibility,
  panel collapse states). Those are editor
  preferences, not Global Styles.

## COUNTER-PATTERNS

### Anti-pattern 1 — Direct DB writes to `wp_global_styles`

```php
$wpdb->update(
    $wpdb->posts,
    array( 'post_content' => json_encode( $styles ) ),
    array( 'ID' => $global_styles_post_id )
);
```

Bypasses capability validation, schema
sanitization, revision creation, cache
invalidation. Use the REST endpoint or
WordPress core APIs (`wp_update_post` after
the proper validation flow):

```php
wp_update_post( array(
    'ID'           => $global_styles_post_id,
    'post_content' => wp_json_encode( $styles ),
) );
// Then trigger cache invalidation hooks if needed.
```

…but typically the REST API is the right
surface for code that mimics user actions.

### Anti-pattern 2 — Caching resolved styles longer than the lifecycle

```php
$cached = get_transient( 'my_styles' );
if ( ! $cached ) {
    $cached = wp_get_global_stylesheet();
    set_transient( 'my_styles', $cached, DAY_IN_SECONDS );
}
echo $cached;
```

Saving Global Styles invalidates core's own
cache; your cache outlives the invalidation
and serves stale styles. Either don't cache
on top of the core resolver, or hook into
the save action to invalidate your cache too.

### Anti-pattern 3 — Assuming the resolver returns a static value

```php
add_action( 'init', function() {
    $styles = wp_get_global_styles();
    // Use $styles for the rest of the request, ignoring later resolver state.
} );
```

`init` runs early; some plugins or themes
modify the user layer mid-request via
filters. The cached result from init might
not reflect later mutations. For
request-final styles, defer to a later hook
or call the resolver freshly when needed.

### Anti-pattern 4 — Treating variations as live layers

```php
// Reading a variation file directly to "use" it as styling.
$variation = json_decode( file_get_contents( get_template_directory() . '/styles/bold.json' ), true );
// Apply to current request rendering.
```

Variations are *initial-state sources*, not
active layers. They become "active" only
when copied into the user layer via
selection. Reading a variation file
directly bypasses the user-customization
flow.

### Anti-pattern 5 — Not handling per-theme post creation

```php
$post = get_posts( array(
    'post_type' => 'wp_global_styles',
    'name'      => 'wp-global-styles-my-theme',
) );
$styles = json_decode( $post[0]->post_content, true );
// Crashes if no such post exists yet.
```

A freshly-activated theme may not have a
`wp_global_styles` post yet. Use the
resolver, which handles missing posts
gracefully:

```php
$user_data = WP_Theme_JSON_Resolver::get_user_data();
$styles = $user_data->get_data();  // empty array structure if no post
```

### Anti-pattern 6 — Treating cleanup as critical

```php
register_uninstall_hook( __FILE__, function() {
    // Plugin uninstall: delete all wp_global_styles posts.
    $posts = get_posts( array( 'post_type' => 'wp_global_styles', 'numberposts' => -1 ) );
    foreach ( $posts as $post ) {
        wp_delete_post( $post->ID, true );
    }
} );
```

Even if the plugin somehow contributes to
those posts (rare), deleting all global
styles posts on uninstall destroys *every*
theme's customizations. Operators expect
their customization data to survive plugin
uninstall. Don't sweep aggressively; let
inactive theme posts persist harmlessly.

## OPERATIONAL NOTES

The user-customization lifecycle's
interpretive shape, in proportional v2
vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *7-stage cascade* form.
  The customization is *declared* multiply:
  the theme declares default values, the
  user *declares* a customization in the UI,
  the REST endpoint *declares* the new post
  content, the resolver *declares* the
  merged result, the style engine *declares*
  the CSS, the rendered page *declares* the
  applied result. Each stage is an
  exposure; the next stage's input is the
  prior stage's exposure. Naming Law 1 here
  is genuinely clarifying because the *gap*
  between "user clicked save" and "front
  end shows the change" is exactly the
  staged transformation chain. The framing
  *configured source ≠ persisted override ≠
  resolved cascade* names three of the
  seven exposures.
- **Doctrine 5 (Authority Continuity)** is
  **very strong**, in a *cross-context
  identity* form. The style token name
  (e.g., `color.primary`) persists as the
  identity surface across all seven
  stages: theme.json declaration → variation
  copy → UI form input → REST payload →
  post content → resolver merge → CSS
  variable → block instance reference. The
  same string identifies "the same logical
  thing" across editor JS, REST PHP,
  database, resolver PHP, style engine, and
  browser. This is one of the strongest
  Doctrine 5 terrains in the KB — *cross-
  context, cross-storage, cross-runtime
  identity preservation*.
- **Doctrine 6 (Authority Mediation)**
  appears *softly*. The REST endpoint
  enforces `edit_theme_options` capability
  before write; this is genuine access
  mediation at the persistence boundary.
  Same softness pattern as the surrounding
  admin / REST chunks — the mediation lives
  in REST endpoint infrastructure, not in
  the lifecycle mechanism per se. Worth one
  mention; not a section.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit, inheriting from
  `style-engine.theme-json-source-layering`'s
  precedent.* Layer precedence (theme vs
  user) is deterministic merge, not
  arbitration; both layers participate in
  the resolved output. Same `Layer
  precedence ≠ candidate arbitration`
  literacy applies. Not re-derived in
  detail; cross-referenced.
- **Law 3b (Cross-Runtime Authority
  Continuity Bridge).** *Explicit non-fit.*
  The lifecycle crosses runtime boundaries
  (editor JS → REST → PHP → DB → PHP → CSS
  → frontend), but no runtime authority is
  preserved across them. What flows is
  *data* (settings JSON in, resolved styles
  out) and *artifacts* (CSS strings); no
  state, identity, or capability persists
  from one runtime to another. Same family
  of non-fits as `block.json-build-pipeline`
  (file copy ≠ bridge),
  `data-layer.resolver-lifecycle` (async
  fetch ≠ bridge),
  `style-engine.per-block-style-attribution`
  (parallel realization ≠ bridge),
  `block.server-side-render-component`
  (REST roundtrip ≠ bridge). This chunk is
  the **fifth** member of the false-bridge
  inventory and structurally similar to the
  fourth (ServerSideRender) — both have
  REST roundtrips with editor-PHP transport.
  The shape: editor sends data; PHP
  processes and stores; later request
  reads the stored data. No authority
  bridging.
- **Federation.** The 3-layer source merge
  is *layered governance*, not federation
  (covered in 8.35). This chunk inherits
  the same non-fit; not re-derived.
- **Law 6 (Compiler ↔ Runtime Split).**
  Some cross-runtime work happens (editor JS
  → PHP), but not in the build/runtime split
  sense — both are runtime contexts. Style
  engine CSS generation has cache
  pre-computation flavor but isn't a build
  step. Omitted.
- **Section X archetypes.** A user-
  customization lifecycle is not a
  "civilization." Same framework-omission
  discipline. Omitted.

### Composite-mechanism observation (per Phase 8.M2 grammar)

The 7-stage lifecycle exhibits **stage-level
doctrinal diversity** in a particularly clean
form:

| Stage range | Runtime context        | Dominant doctrinal shape                      |
| ----------- | ---------------------- | --------------------------------------------- |
| 1-2         | Editor JS              | Law 1 declaration, UI mutation                |
| 3-4         | REST/PHP transport+DB | Doctrine 6 soft (capability), persistence    |
| 5-6         | PHP resolver + style engine | Doctrine 5 continuity, deterministic merge (anti-Law-4) |
| 7           | Browser rendering      | Law 1 exposure                                |

Same overall mechanism (user customization →
lived style); different doctrinal profile per
stage. Stages 5-6 inherit the Phase 8.35
anti-Law-4 framing; stages 3-4 host the
Doctrine 6 soft mediation; stages 1-2 are
classic Law 1 declaration. No single
doctrinal label fits the whole lifecycle —
which is exactly what M2's composite-
mechanism observation predicted: **finer
analytical units (stage-level) reveal
diversity that whole-mechanism labels would
flatten**.

This chunk is also a *post-audit stress
test*: the M2 grammar refinements (Law 4 fit
criterion, Federation variant matrix,
composite-mechanism analytical unit) all
apply cleanly. The cross-context complexity
that motivated M2's recommendation didn't
overwhelm the grammar; it instead confirmed
the grammar's interpretive precision.

## Three literacy contributions worth pinning

> *Configured source ≠ persisted override ≠
> resolved cascade.* Three distinct exposures
> of the user-customization lifecycle, each a
> structurally separate state. The theme's
> theme.json is *configured source*; the
> wp_global_styles post is *persisted
> override*; the merged output is *resolved
> cascade*. None reduces to another;
> diagnosis of "my style change isn't
> showing" requires identifying which of the
> three is misaligned.

This contribution adds a *3-state lifecycle
distinction* to the existence-vs-operation
toolkit, parallel to the 4-step interactivity
ladder and the 4-stage cron temporal ladder.
The pattern: *staged transitions in
multi-context mechanisms benefit from
explicit state-naming*.

> *UI mutation is governance input; REST
> persistence is storage; resolver merge is
> lived authority.* Three distinct roles
> across the lifecycle. The UI is where
> *user intent enters*; REST + DB are where
> *intent becomes durable*; the resolver is
> where *durable intent becomes effective
> reality*. Conflating any pair of these —
> treating UI state as authoritative,
> treating stored data as immediately
> effective, or treating resolved styles as
> the input to UI editing — produces
> diagnostic confusion.

This contribution names the *role
distribution* across the lifecycle. It's a
prose-level reading lens for thinking about
cross-context mechanisms in general:
identify which stage owns which role.

> *Variation file ≠ active layer.* A theme-
> shipped style variation is not the same
> as a layer in the resolver merge. The
> variation file is a *read-only initial-
> state source*; selecting a variation
> *copies* its values into the user layer
> (the wp_global_styles post). After
> selection, the variation file is
> structurally inert — what's "active" is
> the copied values in the user layer. The
> variation file remains as a reference
> point but doesn't participate in the
> resolver merge directly.

This contribution clarifies a subtle
mechanism distinction. Future chunks
discussing "alternative initial states" can
reference this distinction without
re-deriving it: *initial-state source ≠
active participant*.

## CHECKLIST

When reasoning about Global Styles user
persistence:

- [ ] Use the REST endpoint
      (`/wp/v2/global-styles/{id}`) for
      programmatic writes; bypass
      direct DB writes.
- [ ] Read resolved values via
      `wp_get_global_styles()` /
      `wp_get_global_settings()` — they
      handle the merge correctly.
- [ ] Don't cache resolved styles longer
      than the resolver's own cache;
      hook save action invalidation if
      you must add custom caching.
- [ ] Per-theme persistence is automatic;
      switching themes doesn't lose
      customizations. Don't try to
      "transfer" customizations across
      themes mechanically — different
      themes may have different schemas.
- [ ] Style variations are initial-state
      sources, not active layers.
      Selection materializes their values
      into the user layer.
- [ ] Capability gating
      (`edit_theme_options`) for writes
      is enforced at the REST endpoint;
      respect it for any wrapper code
      that mimics user actions.
- [ ] When debugging "my change saved
      but isn't showing," walk the
      7-stage lifecycle to find which
      transition failed.

## REFERENCES

- Global Settings & Styles handbook —
  user customizations layer.
  https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/
- `WP_Theme_JSON_Resolver::get_user_data()`
  reference. The user-layer reading
  function.
  https://developer.wordpress.org/reference/classes/wp_theme_json_resolver/get_user_data/
- REST API Global Styles reference. Read /
  write endpoints.
  https://developer.wordpress.org/rest-api/reference/global-styles/
- Make WordPress Core — Global styles
  persistence and revisions.
  https://make.wordpress.org/core/2022/01/04/improvements-to-the-creation-and-management-of-global-styles-revisions/
- Style Variations handbook. Theme-shipped
  alternate global styles.
  https://developer.wordpress.org/themes/global-settings-and-styles/styles/style-variations/

Cross-context:

- `style-engine.theme-json-source-layering`
  — the *upstream* merge mechanism the
  user-layer participates in. Together
  the two chunks form the *user
  customization lifecycle + source
  layering* pair — the full story from
  UI click to rendered cascade.
- `style-engine.per-block-style-attribution`
  — downstream realization at per-block
  level. The resolved cascade from this
  chunk is what per-block attribution
  consumes.
- `admin-ui.settings-api` — adjacent
  admin governance mechanism. Distinct
  from this chunk's lifecycle (Settings
  API stores in `wp_options`, not in a
  custom post type).
- `plugin-dev.register-rest-route` — the
  REST infrastructure this chunk's
  persistence layer (Stage 3-4) consumes.
- `plugin-dev.capabilities-and-roles` —
  `edit_theme_options` is the capability
  gating user-layer writes.
