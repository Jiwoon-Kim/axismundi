---
rule_id: template-tags.render-context
domain: template-tags
topic: content-render-pipeline
field_cluster: filter-chain-substrate
wp_min: "2.0"
wp_recommended: "5.5+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/the_content/
    section: "the_content() — function reference + filter triggering"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/hooks/the_content/
    section: "the_content filter — default callbacks, priority"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/do_blocks/
    section: "do_blocks() — block rendering within the_content pipeline"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/the_excerpt/
    section: "the_excerpt() — different filter chain from the_content"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wpautop/
    section: "wpautop() — paragraph wrapping; default callback"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/themes/basics/template-tags/
    section: "Template Tags handbook — overview of the family"
    captured: 2026-05-10
related:
  - plugin-dev.hooks-lifecycle-and-priority         # the filter system the_content sits on
  - block.dynamic-rendering                         # do_blocks stage in this pipeline
  - block.markup-representation                     # post_content's IR before do_blocks runs
  - site-building.query-vars-and-main-query-resolution  # main query selects which post; this chunk renders it
  - data-layer.persistence                          # post_content storage upstream of this pipeline
---

# RULE — `the_content` and the content render pipeline — staged transformation from stored post body to rendered experience

## WHEN

You are reasoning about how a post's stored
`post_content` becomes the HTML a visitor
actually sees, or how to intervene at a
specific stage of that transformation. Use
this knowledge when:

- Adding a callback to `the_content` filter
  to modify rendered output (sanitize,
  inject, wrap, transform).
- Diagnosing "my filter doesn't seem to fire"
  or "my modification appears in some places
  but not others" (excerpt vs full content vs
  REST vs feed).
- Understanding why `do_blocks` is part of
  `the_content` rather than a separate
  function call.
- Choosing between hooking `the_content`,
  `the_excerpt`, `the_content_feed`, or
  REST-context filters depending on which
  render surfaces should be affected.
- Reading core's filter-chain assembly in
  `default-filters.php` and following which
  callbacks run at which priority.
- Implementing a custom template tag that
  follows the same get-then-filter-then-output
  shape as `the_content`.

This chunk does **not** cover:

- Block rendering internals (`render_callback`
  per block, dynamic block mechanics) —
  covered in `block.dynamic-rendering`.
  `do_blocks` is a *stage* in this chunk's
  pipeline; the per-block render is that
  chunk's territory.
- The hook system itself
  (`add_filter` / `apply_filters`) — covered
  in `plugin-dev.hooks-lifecycle-and-priority`.
  This chunk uses hooks; the mechanism is
  documented there.
- The block grammar that `do_blocks` parses
  — covered in `block.markup-representation`.
  This chunk operates above the parser
  level.
- The full template-tag catalog
  (`the_author()`, `the_date()`,
  `wp_list_pages()`, etc.). This chunk
  focuses on the *content-render filter
  chain* family; other template tags have
  their own filter shapes.

The principle this chunk operates under: **A
post's `post_content` is *source material*,
not lived experience. Between stored content
and rendered HTML sits a multi-stage filter
chain composed of dozens of callbacks (core
defaults + plugin contributions) that
transform, expand, and decorate. The
mechanism is *staged composition under
filter discipline* — and a single source can
flow through *multiple distinct chains*
producing structurally different outputs
(`the_content` vs `the_excerpt` vs REST
vs feed).**

## SHAPE

### A. The 3-state content lifecycle

```
Stage 1 — STORED (database)
   post_content as authored — block delimiters,
   shortcode strings, raw HTML, embedded URLs.
   The "source material."
              │
              ▼
Stage 2 — FILTERED (PHP transformation chain)
   apply_filters('the_content', $raw_content)
   runs many callbacks in priority order.
   Default callbacks: wptexturize, convert_smilies,
   convert_chars, wpautop, shortcode_unautop,
   prepend_attachment, wp_filter_content_tags,
   do_blocks, do_shortcode, ...
   Plus all plugin-added callbacks.
              │
              ▼
Stage 3 — RENDERED (HTML output)
   The resulting string echoed/returned for
   inclusion in the page response.
   Browser parses; rendered as a page region.
```

Three states. The chunk's central
observation: **most "WordPress content
mysteries" live between Stages 1 and 2**.
Stored content is what the editor saved;
rendered content is what the visitor
sees; the pipeline between is where every
plugin and theme that ever hooked
`the_content` contributes.

The framing parallels Phase 8.44's *configured
source ≠ persisted override ≠ resolved
cascade* but for content rather than style:
*persisted post body ≠ filtered content ≠
rendered experience*.

### B. `the_content` is a filter chain, not a function

The `the_content()` function looks simple:

```php
function the_content( $more_link_text = null, $strip_teaser = false ) {
    $content = get_the_content( $more_link_text, $strip_teaser );
    $content = apply_filters( 'the_content', $content );
    $content = str_replace( ']]>', ']]&gt;', $content );
    echo $content;
}
```

Three lines do the work. The middle line —
`apply_filters('the_content', $content)` —
is where the transformation happens. The
filter has *many* registered callbacks; each
one transforms the content in turn; the
final value reflects the cumulative effect of
every callback.

The function is not the mechanism. The filter
is the mechanism. Reading `the_content` as a
function obscures the layered transformation
that gives it its actual behavior.

### C. Default callbacks and priority ordering

Core registers these callbacks on
`the_content` by default (priorities are
approximate; check `wp-includes/default-filters.php`
for current values):

| Priority | Callback                       | What it does                                        |
| -------- | ------------------------------ | --------------------------------------------------- |
| 9        | `do_blocks`                    | Block rendering — parses delimiters, runs render_callbacks |
| 10       | `wptexturize`                  | Smart quotes, dashes, ellipses                      |
| 10       | `convert_smilies`              | Text smileys → image tags                           |
| 10       | `convert_chars`                | Misc HTML entity conversions                        |
| 10       | `wpautop`                      | Paragraph and line-break wrapping                   |
| 10       | `shortcode_unautop`            | Undo wpautop's wrapping around shortcodes           |
| 10       | `prepend_attachment`           | For attachment posts: prepend the attachment        |
| 10       | `wp_filter_content_tags`       | Responsive images (`srcset`, lazy loading, decoding) |
| 11       | `do_shortcode`                 | Expand shortcodes (after blocks already ran)        |

**Key ordering observations**:

- **`do_blocks` runs at priority 9** — *before*
  most other callbacks. This means by the
  time other callbacks see the content,
  blocks have already been expanded into
  HTML. A callback at priority 10+ sees
  rendered block output, not block
  delimiters.
- **`wpautop` and `do_blocks` interplay**:
  `do_blocks` runs first; the resulting
  block markup is *intentionally not*
  wrapped in `<p>` tags by core blocks
  themselves; `wpautop` then runs and might
  wrap miscellaneous text outside block
  markup. The interplay is delicate; many
  plugins hook here.
- **`do_shortcode` runs at priority 11** —
  *after* `do_blocks`. Shortcodes inside
  block content are expanded after the
  block markup is in place.

Plugins typically hook `the_content` at
priorities adjacent to these defaults
depending on whether they need to operate
on raw stored content (priority < 9), on
post-block markup (priority 10), or on
post-shortcode content (priority > 11).

### D. `do_blocks` as nested arbitration within composition

`do_blocks` deserves separate attention:
*it's a single callback within `the_content`'s
filter composition, but internally it
performs candidate-style dispatch*.

```
do_blocks($content) flow:
1. Parse $content for <!-- wp:* --> delimiters.
2. For each parsed block:
   a. Look up the block type by name in WP_Block_Type_Registry.
   b. If the block has a render_callback (dynamic):
      - Invoke render_callback with the block's attributes.
      - Use returned HTML.
   c. If the block is static (save returned HTML):
      - Use the inner content from the delimiters as-is.
3. Concatenate the results, replacing each block's
   raw delimiter+content with its rendered HTML.
4. Return the assembled output.
```

Within `do_blocks`, the **block name → render
callback** lookup is a registry dispatch
(name to function). Multiple blocks of the
same type render with the same callback;
different types render with different
callbacks; there's no candidate ladder —
just direct lookup.

This is *one stage in the composition*
(filter chain) that *internally uses
dispatch* (registry lookup). Not arbitration
in the Law 4 sense (no first-match-wins; the
block name *is* the lookup key directly).

This is a small, clean example of the
composite-mechanism observation from Phase
8.M2: **stages within larger pipelines can
have different doctrinal profiles**. The
filter chain is composition; `do_blocks`
within it is dispatch. The same `the_content`
call traverses both.

### E. Multiple render contexts — same source, different chains

A single post's `post_content` can flow
through **several distinct filter chains**
depending on the rendering context:

| Render context        | Filter family                                  | Typical use                              |
| --------------------- | ---------------------------------------------- | ---------------------------------------- |
| Singular post page    | `the_content`                                  | Full content render in single.php        |
| Archive listing       | `the_excerpt` (different chain entirely)       | Trimmed preview                          |
| RSS / Atom feed       | `the_content_feed`, `the_excerpt_rss`          | Feed-specific filters                    |
| REST API              | `the_content` with `?context=view|edit|embed`  | API consumers; context-aware             |
| Email digest, etc.    | Custom filter applications                     | Plugin-defined                           |

The same `post_content` produces structurally
different outputs through these chains:

- `the_content` runs the full pipeline:
  blocks expand, shortcodes run, paragraphs
  wrap, responsive images applied.
- `the_excerpt` runs a *much shorter*
  pipeline: typically truncates, may strip
  block markup, doesn't run shortcodes by
  default.
- `the_content_feed` adds feed-specific
  sanitization (e.g., strip JavaScript,
  convert relative URLs).
- REST `view` context runs `the_content`
  + may apply additional REST-specific
  transformations.

**The same source, many lived experiences**.
Hooking `the_content` doesn't affect
excerpts. Hooking `the_excerpt` doesn't
affect feeds. Each context's chain is
independently composed; participation in one
chain doesn't imply participation in others.

This is the chunk's second central
observation: **content identity is preserved
across render contexts (Doctrine 5), but
each render context *constructs its own
exposure* via its own filter chain (Law 1
multiplicity)**.

### F. Other content template tags and their filter shapes

The `the_content` family extends to other
template tags with similar (but distinct)
filter chains:

| Template tag        | Filter                | Default callbacks (sample)                              |
| ------------------- | --------------------- | ------------------------------------------------------- |
| `the_title()`       | `the_title`           | `wptexturize`, `convert_chars`, `trim`                  |
| `the_excerpt()`     | `the_excerpt`         | `wp_trim_excerpt`, `wptexturize`, `wpautop`             |
| `the_author()`      | `the_author`          | (typically empty by default; plugins extend)            |
| `the_date()`        | `the_date`            | `(date filtering)`                                      |
| `comment_text()`    | `comment_text`        | `wptexturize`, `convert_chars`, `make_clickable`        |
| `the_meta()`        | (per-meta-key filters) | `get_post_metadata`, sanitization filters             |

Each template tag follows the same shape:
*get the source value, apply filters, output
the result*. Different template tags have
different filter chains because their use
cases differ — titles need less
transformation than content; excerpts need
trimming; author names need plain text.

The template-tag family is unified by this
*get-then-filter-then-output* pattern, even
though the specific filters differ per tag.

### G. The composite-mechanism observation

The content render pipeline exhibits
**stage-level doctrinal diversity** in a
particularly rich form:

| Stage / sub-mechanism            | Doctrinal shape                                            |
| -------------------------------- | ---------------------------------------------------------- |
| Filter chain composition          | Composition (all callbacks fire; output combines)         |
| Within `do_blocks`: name → callback | Direct registry dispatch (anti-arbitration; just lookup) |
| Within block render callbacks     | Block-specific (varies; some pure, some side-effecting)   |
| Within `do_shortcode`: tag → handler | Direct registry dispatch                                |
| Within `wp_filter_content_tags`: tag-name handlers | Per-tag dispatch                        |

One pipeline; many sub-mechanisms; different
doctrinal profiles per sub-mechanism.

This is the third deployment (after 8.41
query vars and 8.44 user persistence) of the
M2 composite-mechanism observation. The
pattern is now broadly applicable: when a
mechanism contains *nested mechanisms*, the
outer composition can host inner dispatches,
inner arbitrations, inner directly-stored
operations — each with its own doctrinal
profile.

The audit-recommended grammar is now
operationally normal across cross-context
chunks.

## WHY

### Why a filter chain rather than a function

A function would lock the transformation
shape at core-author time. The filter chain
lets every plugin and theme participate in
the transformation:

- A plugin can add image lazy-loading.
- A plugin can inject related-post links.
- A plugin can sanitize against XSS at a
  specific stage.
- A plugin can replace shortcodes with
  block equivalents during transition.

None of these would be possible without the
filter chain. The cost is the chain's
behavior is *the cumulative effect of every
hooked callback*, which can be hard to
reason about; the benefit is unbounded
extensibility without core changes.

### Why `do_blocks` is part of `the_content` rather than separate

A separate `do_blocks()` call before
`apply_filters('the_content', ...)` would
mean every plugin hooking `the_content`
would have to choose: do they want to see
raw block delimiters or rendered block
HTML? That choice would have to be
communicated globally; the ecosystem would
need conventions for which is "the right"
form to filter.

Putting `do_blocks` *inside* the chain (at
priority 9) settles the question
deterministically: by priority 10+, blocks
have rendered. Most plugins want to operate
on rendered HTML; the default ordering
gives them that.

The cost is plugins that need to operate
on raw delimiters must hook at priority < 9
(or use a different filter entirely);
the benefit is the dominant case is
ergonomic.

### Why multiple render contexts have distinct filter chains

If `the_content` were the only filter, it
would have to handle every render context's
needs simultaneously. A callback that adds
page navigation links to full-content
display would also add them to feed items
(making feeds bloated) and to excerpts
(breaking the truncation).

Per-context filters let each context evolve
its transformation independently. Plugins
opt into the contexts they want to affect:

```php
// Affect only the singular page render:
add_filter( 'the_content', 'my_callback' );

// Affect only excerpts:
add_filter( 'the_excerpt', 'my_callback' );

// Affect only feeds:
add_filter( 'the_content_feed', 'my_callback' );

// Affect everything:
add_filter( 'the_content', 'my_callback' );
add_filter( 'the_excerpt', 'my_callback' );
add_filter( 'the_content_feed', 'my_callback' );
```

The cost is plugins must explicitly opt
into multiple contexts; the benefit is no
unintended cross-context bleed.

### Why the same `post_content` is treated as one identity

Across all render contexts, a single
post's `post_content` is *the same source
material*. It just gets exposed through
different transformation chains. The
identity is at the *post* level (post ID,
post_content stored value); the *exposure*
varies per context.

This is why Doctrine 5 (Authority
Continuity) applies strongly: the post's
identity persists; the rendered forms
diverge. *Same source; many surfaces.*

## WHEN NOT

Skip the content render pipeline if:

- You are working with **block-internal
  rendering** for one specific block. Use
  `register_block_type`'s `render_callback`
  directly; the_content runs your callback
  via `do_blocks` automatically.
- You want **HTML manipulation post-render**
  (e.g., a JavaScript modification of
  rendered content). That's frontend
  territory; the_content's pipeline is
  PHP-only.
- You need **content-creation-time
  transformation** (when the user is
  editing). That's editor territory
  (`block-filters` etc.); `the_content`
  runs at *render* time, after content is
  saved.
- You need **per-paragraph or per-element
  manipulation** without affecting the
  whole filter pipeline. Consider direct
  string manipulation at a higher level,
  or hook a more specific filter
  (block-render filters,
  `the_content_more_link`, etc.).

## COUNTER-PATTERNS

### Anti-pattern 1 — Treating `the_content` filter as if it sees raw delimiters

```php
add_filter( 'the_content', function( $content ) {
    if ( strpos( $content, '<!-- wp:my/block' ) !== false ) {
        // Hoping to find raw block delimiters at priority 10.
    }
    return $content;
} );
```

By default priority 10, `do_blocks` (priority
9) has already expanded delimiters into
rendered HTML. The delimiter strings are
gone. To see raw delimiters, hook earlier:

```php
add_filter( 'the_content', function( $content ) {
    // ... at priority 5, blocks haven't rendered yet
    return $content;
}, 5 );
```

…or operate on `post_content` before
`apply_filters('the_content', ...)` runs at
all (e.g., from `the_post` action).

### Anti-pattern 2 — Adding to `the_content` and expecting excerpts to update

```php
add_filter( 'the_content', function( $content ) {
    return $content . '<p>Subscribe to our newsletter!</p>';
} );
// Excerpts in archives don't get the newsletter pitch.
```

`the_excerpt` is a separate filter chain;
adding to `the_content` doesn't affect it.
For multi-context coverage, hook each
relevant filter:

```php
add_filter( 'the_content', 'my_append_subscribe' );
add_filter( 'the_excerpt', 'my_append_subscribe' );
add_filter( 'the_content_feed', 'my_append_subscribe' );
```

### Anti-pattern 3 — Echo-ing inside the filter

```php
add_filter( 'the_content', function( $content ) {
    echo '<p>Side note</p>';  // wrong — breaks chain
    return $content;
} );
```

Filters are pure transformations: take a
value, return a value. Side-effect output
breaks the chain semantically (other
callbacks may not expect echoed content)
and produces output in the wrong place
(before the actual content). Always return
the modified value:

```php
add_filter( 'the_content', function( $content ) {
    return '<p>Side note</p>' . $content;
} );
```

### Anti-pattern 4 — Heavy work on every filter call

```php
add_filter( 'the_content', function( $content ) {
    $analytics_data = expensive_analytics_query();  // every call
    return $content . render_analytics( $analytics_data );
} );
```

`the_content` fires once per loop iteration
on archive pages — meaning the expensive
work runs many times per page. Cache
between calls or move work outside the
filter:

```php
$analytics_data = null;
add_filter( 'the_content', function( $content ) {
    global $analytics_data;
    if ( $analytics_data === null ) {
        $analytics_data = expensive_analytics_query();
    }
    return $content . render_analytics( $analytics_data );
} );
```

### Anti-pattern 5 — Modifying inside `do_blocks`'s direct stage

```php
// Plugin filters at priority 9.5 — between do_blocks and other filters
add_filter( 'the_content', function( $content ) {
    // What runs here? Blocks done, but other filters not yet.
    // Operating on rendered block HTML before wpautop, etc.
    return preg_replace( '/<p>/', '<p class="my-class">', $content );
}, 9.5 );  // float priority — works but unusual
```

Float priorities work but indicate
fragility. The 9.5 between core's 9 and 10
is a window the plugin claims; another
plugin doing the same gets a race. Stick
to integer priorities and accept that
"between blocks and wpautop" is a
contested space.

### Anti-pattern 6 — Hooking `the_content` for non-content purposes

```php
add_filter( 'the_content', function( $content ) {
    track_post_view( get_the_ID() );  // analytics tracking
    return $content;
} );
```

`the_content` may fire multiple times per
request (if a template renders the same
post multiple times). View-tracking belongs
in `the_post` action or in
`template_redirect` — not in the content
filter chain.

## OPERATIONAL NOTES

The content render pipeline's interpretive
shape, in proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *3-state cascade* form
  parallel to 8.44's Global Styles
  lifecycle. The `post_content` is *stored
  source* (declaration); the filter chain
  produces *rendered HTML* (exposure); the
  visible page region is *lived experience*
  (final exposure). Three states; gaps
  between them are diagnostic surfaces.
  Naming Law 1 here is genuinely clarifying
  because the gap between "what the editor
  saved" and "what the visitor sees" is
  exactly the staged transformation chain.
- **Doctrine 5 (Authority Continuity)** is
  **strong**, in a *content-identity-across-
  contexts* form. The post's identity (its
  ID, its `post_content`) persists; the
  rendered forms diverge per context.
  `the_content` produces one rendering;
  `the_excerpt` produces another;
  `the_content_feed` produces a third —
  all from the same source. Same content
  identity; many lived surfaces. This is
  another strong Doctrine 5 anchor
  alongside the JIT translations
  (semantic continuity), source layering
  (token continuity), and Global Styles
  user persistence (cross-context style
  identity) chunks.
- **Federation** appears in a **strong
  composition-hybrid** form. Many
  participants register filter callbacks;
  all callbacks fire; the resulting
  content reflects the cumulative effect.
  Same shape as the query_vars chunk's
  *registration-federation +
  composed-singular-output* (Phase 8.41)
  — but `the_content` is the **dominant
  practical example** in the WordPress
  ecosystem. Plugins live on this filter;
  the federation pattern's
  composition-hybrid variant has its
  canonical anchor here.

What this chunk is **not** about:

- **Law 4 (Arbitration Compiler).** *Explicit
  non-fit, reinforcing the hooks pattern
  from 8.36.* Filter callback priorities
  are ordered, but all callbacks fire (no
  candidate selection; no first-match-wins;
  no discarded callbacks). This is the
  specific application of the hooks
  composition pattern in content-rendering
  terrain. The literacy already established
  for hook priority — *priority orders for
  composition, arbitration orders for
  selection* — applies here directly. This
  chunk doesn't add a *new* anti-Law-4
  inventory member because the mechanism is
  *the same hook system* as 8.36;
  just a particularly busy filter on it.
  Cross-reference: hook priority anti-Law-4
  treatment at 8.36 covers the structural
  case; this chunk illustrates one famous
  application.
- **Doctrine 6 (Authority Mediation).** No
  access mediation in the render filters
  themselves. Capability checks for
  reading posts happen elsewhere
  (`current_user_can('read_post', $id)`,
  visibility filters at the query level).
  Render-filter callbacks operate on
  content already determined to be
  visible. Omitted.
- **Law 3b (Cross-Runtime Authority
  Continuity Bridge).** All content rendering
  runs in PHP request runtime. No
  cross-runtime authority preservation.
  Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** No
  build / runtime split. Omitted.
- **Section X archetypes.** A content
  render pipeline is not a "civilization."
  Same framework-omission discipline as
  the surrounding chunks. Omitted.

### Composite-mechanism observation (continued M2 grammar deployment)

The pipeline exhibits the now-familiar
stage-level doctrinal diversity:

| Stage / sub-mechanism             | Doctrinal shape                                |
| --------------------------------- | ---------------------------------------------- |
| Filter chain composition           | Composition (anti-Law-4)                      |
| `do_blocks` registry dispatch      | Direct lookup (anti-arbitration)              |
| Per-block render callback          | Block-specific (varies)                       |
| `do_shortcode` registry dispatch   | Direct lookup                                 |
| Multi-context filter selection     | Per-context independent chains (anti-arbitration) |

Same overall pipeline; multiple sub-mechanisms;
distinct doctrinal profiles. This is the
third post-M2 deployment (8.41, 8.44, 8.45)
of the composite-mechanism observation. The
pattern's reach across cross-context
chunks is now well-established.

## Two literacy contributions worth pinning

> *Persisted post body ≠ filtered content ≠
> rendered experience.* Three distinct
> exposures of the content lifecycle, each a
> structurally separate state. The
> `post_content` in the database is
> *persisted source*; the filter chain
> output is *filtered content*; the visible
> page region is *rendered experience*. None
> reduces to another; diagnosis of "my
> content change isn't appearing" requires
> identifying which of the three is
> misaligned — or which filter chain is
> being hooked vs which is being rendered.

This contribution adds another *3-state
lifecycle distinction* to the existence-vs-
operation toolkit, parallel to 8.44's
*configured source ≠ persisted override ≠
resolved cascade*. The two together
demonstrate that *3-state lifecycles are a
recurring pattern in cross-context
mechanisms* — style and content both
exhibit it.

> *Same content source, many render
> contexts.* A single post's
> `post_content` is one identity; its
> *exposures* are many. The same source
> flows through `the_content` for
> singular rendering, `the_excerpt` for
> archive previews, `the_content_feed`
> for RSS, REST API context-aware
> filters for headless consumers. Each
> context has its own filter chain;
> participation in one doesn't imply
> participation in others. *Identity
> persists; exposure multiplies.*

This contribution names the **multi-
exposure-per-source** pattern explicitly.
It refines the existence-vs-operation
toolkit by adding the dimension of
*multiple parallel exposures of the same
thing*. The earlier toolkit members
distinguished states *along a temporal
or causal axis* (declared / activated /
executed); this one distinguishes
exposures *along a context axis* (singular
/ excerpt / feed / REST). Different
dimension; same underlying pattern of
"declaration ≠ exposure" with *exposure
multiplied per context*.

This pairs with Doctrine 5's
content-identity-across-contexts framing
naturally: identity (Doctrine 5) is
preserved across the contexts that produce
multiple exposures (Law 1's per-context
multiplication).

## CHECKLIST

When working with the content render
pipeline:

- [ ] Use `the_content` filter for
      modifications that should appear in
      singular post rendering. For other
      contexts (excerpt, feed, REST), use
      the appropriate parallel filter.
- [ ] Filter callbacks must return the
      modified value; don't echo inside
      filters.
- [ ] Choose priority based on what stage
      you need — before `do_blocks`
      (priority < 9) for raw delimiters,
      after `do_blocks` (priority > 9) for
      rendered block HTML, after most
      defaults (priority 11+) for late-
      stage transformations.
- [ ] For multi-context coverage, hook
      each relevant filter explicitly;
      don't expect cross-context bleed.
- [ ] Cache expensive computation
      between calls; `the_content` may
      fire many times per page on archives.
- [ ] Don't put non-content side effects
      (analytics tracking, etc.) in
      content filters — use `the_post` or
      `template_redirect`.
- [ ] When debugging "my filter doesn't
      seem to work," confirm: am I hooking
      the right filter for the render
      context? Am I hooking at the right
      priority for the stage I want? Is
      another callback stripping my
      modification?

## REFERENCES

- `the_content()` function reference.
  Documents the function and its filter
  trigger.
  https://developer.wordpress.org/reference/functions/the_content/
- `the_content` filter reference. Documents
  default callbacks and priority.
  https://developer.wordpress.org/reference/hooks/the_content/
- `do_blocks()` reference. The block-rendering
  callback within `the_content`.
  https://developer.wordpress.org/reference/functions/do_blocks/
- `the_excerpt()` reference. The parallel
  filter chain for excerpts.
  https://developer.wordpress.org/reference/functions/the_excerpt/
- `wpautop()` reference. The paragraph-
  wrapping callback's mechanics.
  https://developer.wordpress.org/reference/functions/wpautop/
- Template Tags handbook. Overview of the
  template-tag family.
  https://developer.wordpress.org/themes/basics/template-tags/

Cross-context:

- `plugin-dev.hooks-lifecycle-and-priority`
  — the filter system this chunk's
  pipeline sits on. The composition (not
  arbitration) framing for hook priorities
  applies directly to `the_content`'s
  filter chain.
- `block.dynamic-rendering` — the
  `render_callback` mechanism each dynamic
  block exposes. `do_blocks` (a stage in
  this chunk's pipeline) invokes those
  callbacks.
- `block.markup-representation` — the
  block grammar in `post_content` that
  `do_blocks` parses.
- `site-building.query-vars-and-main-query-resolution`
  — selects *which* post is rendered. This
  chunk handles *how* that post's content
  becomes HTML. Together: Phase 8.41 +
  this chunk = the complete request →
  rendered-experience pipeline for content.
- `data-layer.persistence` — the
  `post_content` storage upstream of this
  chunk's pipeline.
