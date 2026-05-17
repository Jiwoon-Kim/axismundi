---
rule_id: block.server-side-render-component
domain: block-authoring
topic: editor-preview-transport
field_cluster: dynamic-block-preview-substrate
wp_min: "5.3"
wp_recommended: "6.0+"
package_min: "@wordpress/server-side-render@^4"
status: stable
language: js-and-php
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/
    section: "@wordpress/server-side-render — component reference"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/rest-api/reference/block-renderer/
    section: "REST API — Block Renderer endpoint reference"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
    section: "Block registration — render_callback for dynamic blocks"
    captured: 2026-05-10
  - url: https://github.com/WordPress/gutenberg/blob/trunk/packages/server-side-render/README.md
    section: "@wordpress/server-side-render README — placeholder props, debouncing"
    captured: 2026-05-10
related:
  - block.dynamic-rendering                       # the broader dynamic-block render mechanism this preview transport sits on
  - block.edit-and-save-contracts                 # save: () => null is what makes the editor need this preview
  - plugin-dev.register-rest-route                # the REST infrastructure ServerSideRender consumes
  - data-layer.resolver-lifecycle                 # adjacent async-fetch pattern; ServerSideRender uses similar request lifecycle
  - style-engine.per-block-style-attribution      # parallel realization pattern reference (contract-shared, not bridged)
---

# RULE — `<ServerSideRender>` — editor-side preview of dynamic-block PHP rendering via REST roundtrip

## WHEN

You are authoring or debugging a *dynamic block*
(`save: () => null`) and need the editor to show
what the block will actually render on the front
end. Use this knowledge when:

- Wrapping a dynamic block's `edit` return in
  `<ServerSideRender>` to show a live preview
  of the PHP-generated output.
- Configuring placeholder components (loading,
  empty, error) for the preview lifecycle.
- Diagnosing slow editor previews that repeatedly
  re-fetch on every keystroke (almost always:
  missing debouncing, or unstable attributes
  prop reference).
- Reading core block code that uses
  `<ServerSideRender>` for built-in dynamic
  blocks (latest-posts, archives, etc.).
- Debugging "preview shows different output than
  the front-end" complaints — almost always:
  `render_callback` reading request context the
  REST request doesn't have, or attributes
  differing between preview-time and save-time.

This chunk does **not** cover:

- The dynamic-block rendering mechanism itself
  (`render_callback`, `render.php`, three
  rendering modes) — covered in
  `block.dynamic-rendering`. This chunk is the
  *editor preview transport* layered on top.
- Building custom REST routes — covered in
  `plugin-dev.register-rest-route`. The
  Block Renderer endpoint is core-provided;
  this chunk consumes it, not builds new ones.
- The `save` / `edit` contract that produces
  `save: () => null` for dynamic blocks —
  covered in `block.edit-and-save-contracts`.

The principle this chunk operates under:
**`<ServerSideRender>` transports the same
`render_callback` output the front end will
produce, fetched via REST, into the editor for
preview. The transport crosses a runtime
boundary (editor JS → REST → PHP) but does not
*bridge runtime authority*. It is request-response
parallel realization, not cross-runtime
continuity.**

## SHAPE

### A. The component — what it does

`<ServerSideRender>` is a React component from
`@wordpress/server-side-render`. It takes a block
name and attributes, makes a REST request to
have the block's PHP `render_callback` produce
HTML, and renders that HTML into the editor.

```js
import ServerSideRender from '@wordpress/server-side-render';

const Edit = ( { attributes } ) => {
    return (
        <ServerSideRender
            block="myplugin/latest-news"
            attributes={ attributes }
        />
    );
};
```

Three properties of this rendering:

- **The HTML rendered is the result of the same
  `render_callback` the front end uses.** Not a
  separate "preview render"; the actual render
  function, called with the actual attributes,
  in a request context that mimics the front
  end as closely as the REST endpoint can.
- **The render runs at *editor-render time*, not
  at *front-end-load time*.** Site context
  (current post, current user) at the time of
  preview is the request's context — which is
  the editor session's context, not the
  eventual reader's context.
- **The component re-runs the request whenever
  its props change.** Changing the attributes
  in the editor triggers a new REST request and
  a new render.

### B. The REST endpoint

The transport is a single core-provided REST
endpoint:

```
POST /wp/v2/block-renderer/{block-name}
   Body: { attributes: { … }, post_id: <int> }
   Auth: Cookie + REST nonce
   Capability: per-block-type's `render_callback`-related capability
                (typically the block's REST permissions)
   Response: { rendered: "<html>…</html>" }
```

The endpoint:

- Looks up the registered block type by name.
- Validates the attributes against the block's
  declared `attributes` schema.
- Invokes the block's `render_callback` with the
  attributes and a `WP_Block` instance.
- Returns the resulting HTML string in the
  `rendered` property of the JSON response.

The endpoint requires authentication (the
editor's cookie + nonce) and respects per-block
capability requirements. A block whose
`render_callback` is gated by capability won't
render previews for users who lack that
capability — the request returns an error,
which the component renders via its
`ErrorResponsePlaceholder`.

### C. Component props — the placeholder lifecycle

Beyond `block` and `attributes`, the component
accepts placeholder components for the request
lifecycle states:

```js
<ServerSideRender
    block="myplugin/latest-news"
    attributes={ attributes }
    LoadingResponsePlaceholder={ () => <Spinner /> }
    EmptyResponsePlaceholder={ () => <p>No content yet.</p> }
    ErrorResponsePlaceholder={ ( { response } ) => <p>Error: { response.errorMsg }</p> }
    httpMethod="POST"
    skipBlockSupportAttributes={ false }
/>
```

| Prop                              | Purpose                                                       |
| --------------------------------- | ------------------------------------------------------------- |
| `block`                           | Block type name (required)                                    |
| `attributes`                      | Attributes object passed to PHP (required)                    |
| `urlQueryArgs`                    | Additional query args appended to the REST URL                |
| `LoadingResponsePlaceholder`      | Component shown while a request is in flight                  |
| `EmptyResponsePlaceholder`        | Component shown when render returns empty HTML                |
| `ErrorResponsePlaceholder`        | Component shown when the request errors                       |
| `httpMethod`                      | `"POST"` (default) or `"GET"` (for short attribute payloads)  |
| `skipBlockSupportAttributes`      | Whether to omit auto-generated wrapper attributes from sent attributes |

The three placeholder slots cover the three
non-success states. Defaults exist; for
production-quality preview UX, custom
placeholders that match the editor's design
language are typical.

### D. Equivalence vs front-end render

The component's output is *the same* HTML the
front end will produce — *if the
`render_callback` is deterministic from
attributes*. Two cases where preview can
diverge from front-end output:

- **Render reads request context.** A
  `render_callback` that branches on
  `is_admin()`, `current_user_can()`, the
  current post's status, or the queried object
  may produce different output during a REST
  request (admin context, preview-author user,
  the post being edited) than on a front-end
  page load (frontend context, anonymous user
  perhaps, the actual published post).
- **Render reads global state.** A
  `render_callback` that consults globals,
  cached request data, or theme-loaded context
  may differ between preview and production
  because the REST request doesn't run the same
  template loader the front end runs.

The right discipline: write `render_callback`s
that are deterministic from explicit attributes
and the queried-object data, treating
preview-time context as a useful approximation
of front-end-time context but not as identical.

### E. Performance — debouncing and request churn

Every change to `attributes` triggers a new REST
request. A user typing in an attribute-controlled
text input could fire dozens of requests per
second.

`<ServerSideRender>` debounces internally (the
component delays request firing for a brief
window after the last change). For most cases
this is enough; for extremely fast-typing UI or
expensive `render_callback`s, the debouncing
window may need to be longer.

If the calling `Edit` component re-creates its
`attributes` object every render (e.g., spreading
into a new object), the prop appears unstable
even when its values haven't changed — the
component fires unnecessary requests. Memoize
the attributes object reference when stability
matters:

```js
const stableAttributes = useMemo(
    () => attributes,
    [ attributes.foo, attributes.bar ]
);

<ServerSideRender block="…" attributes={ stableAttributes } />
```

### F. Failure modes — what each placeholder catches

The lifecycle and its visible states:

```
1. attributes change ─→ debounced timer
2. timer fires ─→ REST request goes out
                          │
                          ▼
                  LoadingResponsePlaceholder
                          │
       ┌──────────────────┼──────────────────┐
       ▼                  ▼                  ▼
   Success           Empty response       Error response
       │                  │                  │
       ▼                  ▼                  ▼
   Render HTML       EmptyResponse        ErrorResponse
   into editor       Placeholder          Placeholder
                                              │
                                              ▼
                                        Diagnostic surface
                                        (status code, error message)
```

The error placeholder receives a `response`
prop with the error details — useful for
distinguishing 401 (auth) from 403 (capability)
from 500 (render_callback throw) from network
failure.

## WHY

### Why a separate REST endpoint rather than direct PHP eval

The block editor runs in JavaScript. PHP
rendering needs to happen in PHP. The two are
in different runtimes; some transport between
them is required.

The choices were:

- A REST endpoint that JS calls (chosen).
- An in-page `iframe` that bootstraps PHP
  internally (heavyweight, complex).
- A pre-rendered cache that JS displays
  (works for static cases; fails when
  attributes change).

REST is the lightest-weight, most-flexible
option. It uses WordPress's existing REST
infrastructure (auth, nonces, capability
gating); it's testable like any other REST
endpoint; and it keeps the PHP render running
in a real PHP request lifecycle (so plugins
that hook into the request can participate).

### Why the editor preview can differ from the front-end render

The REST request runs in its own request
context: admin-side authentication, the
editor session's user, the post being edited.
The front-end render runs in the actual visitor's
request: front-end authentication state,
whatever user is browsing, the post in its
published context.

A `render_callback` that reads any of these
contextual signals will produce different
output. The framework cannot prevent this
divergence without removing useful capabilities
(reading request context is sometimes the right
thing for a render to do).

The discipline shifts to the block author:
write renders that are deterministic where
possible, and accept that contextual differences
are a fact of preview vs production for the
contextually-sensitive cases.

### Why placeholders rather than auto-generated UI

Different blocks need different visual
languages for "loading," "empty," "error"
states. A latest-posts block's "no posts" state
should look different from a custom feed
widget's. Hardcoded defaults would force a
single look; the placeholder slots let each
block provide its own.

The cost is more code per usage; the benefit is
a preview UI that fits the editor's overall
design.

## WHEN NOT

Skip `<ServerSideRender>` if:

- The block is **static** (`save` returns real
  HTML). The static save *is* the preview;
  rendering the same HTML in the editor matches
  what the front end will show.
- The render is **trivial enough to inline**.
  If `render_callback` is "echo this string,"
  the preview can reproduce it in JS without
  the REST roundtrip.
- The block's preview is **not visually
  important** — e.g., the block is a backend
  data shuttle that doesn't render for users.
  Showing a placeholder in the editor without
  a live preview is acceptable.
- The block needs a **fundamentally different
  preview** (e.g., a settings UI rather than a
  rendered output). Use a custom React preview
  in `edit`; ServerSideRender is for "show the
  PHP render output."

## COUNTER-PATTERNS

### Anti-pattern 1 — Reading editor-only state in `render_callback`

```php
function myplugin_render( $attributes ) {
    if ( is_admin() ) {
        return '<p>Admin preview</p>';
    }
    return '<p>Front-end render</p>';
}
```

The REST endpoint runs in admin context, so
`is_admin()` returns true during ServerSideRender's
preview request — but false on actual front-end
load. The preview shows one thing; the
production render shows another. Diagnose this
by reading the REST request lifecycle: it's a
real request with real context.

If branching is needed, branch on attributes
(set explicitly by the editor) or on
queried-object state, not on admin-vs-frontend
detection.

### Anti-pattern 2 — Unstable attributes object causing churn

```js
const Edit = ( { attributes, setAttributes } ) => {
    return (
        <ServerSideRender
            block="myplugin/foo"
            attributes={ { ...attributes } }  // new object every render
        />
    );
};
```

The spread creates a new object reference even
when the values are unchanged. The component
sees the prop as new and fires a new REST
request. Pass `attributes` directly:

```js
<ServerSideRender block="myplugin/foo" attributes={ attributes } />
```

### Anti-pattern 3 — Treating ServerSideRender as a runtime bridge

```js
// Mental model: "ServerSideRender connects the editor's React state to PHP."
```

It does not. Each REST call is a discrete
request-response. Editor state is sent as
attributes; PHP returns HTML; nothing else
flows. There is no persistent connection, no
shared session beyond the cookie auth, no
runtime state passed between calls. The next
call starts fresh.

The practical consequence: don't try to "tell"
PHP about editor state through anything other
than the attributes payload. There is no other
channel.

### Anti-pattern 4 — Heavy `render_callback` without debouncing or caching

```php
function myplugin_render( $attributes ) {
    sleep( 2 );  // simulating expensive work
    return '<p>Done.</p>';
}
```

Every editor edit fires this. Even with
ServerSideRender's debouncing, the cumulative
cost during active editing is significant.

For expensive renders:

- Cache the result by attributes hash (option,
  transient, or external cache).
- Provide a fast preview path (a simpler
  computation that approximates the real
  render) gated on whether the request is from
  the REST preview endpoint.
- Or accept that editor preview will be slow
  and adjust the placeholder UX to communicate
  that.

### Anti-pattern 5 — Skipping placeholder components for production blocks

```js
<ServerSideRender block="myplugin/foo" attributes={ attributes } />
```

…with no loading/error/empty placeholders. The
default placeholders are functional but bland.
A polished block provides custom placeholders
that match the editor's design and the block's
visual identity.

### Anti-pattern 6 — Sending REST request manually instead of using the component

```js
// Hand-rolled fetch:
const Edit = ( { attributes } ) => {
    const [ html, setHtml ] = useState( '' );
    useEffect( () => {
        apiFetch( {
            path: '/wp/v2/block-renderer/myplugin/foo?context=edit',
            method: 'POST',
            data: { attributes },
        } ).then( ( r ) => setHtml( r.rendered ) );
    }, [ attributes ] );
    return <div dangerouslySetInnerHTML={ { __html: html } } />;
};
```

This re-implements ServerSideRender (badly):
no debouncing, no placeholder lifecycle, no
error handling, race conditions if attributes
change while a request is in flight. Use the
component; it handles all of these.

## OPERATIONAL NOTES

The preview transport's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *transport-mediated* form.
  The block's attributes are the *declared*
  truth (set by the editor's `setAttributes`);
  the rendered HTML is *one exposure* of those
  attributes (via the REST roundtrip → PHP
  render → returned HTML); the front-end render
  is *another exposure* of the same attributes
  (via direct PHP rendering at page load). Two
  exposures, one declaration, separated by
  transport mechanism. Naming Law 1 here is
  genuinely clarifying because the *gap*
  between "the editor has these attributes" and
  "the editor shows this preview" is exactly
  the REST roundtrip.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly*, in a *render-output equivalence*
  form. The same `(block name, attributes)`
  identity should produce equivalent output
  across the preview path and the front-end
  path — assuming the `render_callback` is
  deterministic from attributes (Section D).
  Worth one mention; not a section.
- **Doctrine 6 (Authority Mediation)** appears
  *softly, adjacent*. The REST endpoint
  enforces authentication and per-block
  capability checks at the boundary. But this
  is *endpoint-level mediation*, the same
  pattern as any other REST endpoint —
  *not* a property of the
  `<ServerSideRender>` mechanism itself. The
  component would work the same way over a
  hypothetical permission-free transport (the
  authorization layer is REST infrastructure,
  not preview infrastructure). Worth one
  mention; not a section.

What this chunk is **not** about:

- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** *The most important non-fit to
  name precisely* in this terrain — and the
  chunk's central conceptual move. The pattern
  "editor JS → REST → PHP render → editor
  display" looks **strongly bridge-shaped**.
  It is not.
  - The REST request is *stateless from PHP's
    perspective*. Each call is a discrete
    request-response; PHP has no persistent
    state between calls.
  - The editor doesn't *maintain authority*
    over the PHP process. The PHP runtime
    starts fresh, runs the render, returns
    HTML, exits. Nothing carries forward.
  - What flows across the boundary is *data*
    (attributes in, HTML out), not *authority*
    (state, identity, capability across
    runtime contexts).
  This is the same family of non-fits the
  KB has previously named in
  `block-json-build-pipeline` (file copy ≠
  bridge), `resolver-lifecycle` (async fetch
  ≠ bridge), and
  `per-block-style-attribution` (parallel
  realization ≠ bridge). ServerSideRender is
  the **fourth** member of the false-bridge
  inventory and the *most surface-tempting*:
  the architecture literally has the editor
  reaching across to invoke PHP. Naming Law 3b
  here would dilute its meaning where it
  actually applies (interactivity hydration's
  state preservation across server-render →
  client-hydrate). The phrasing worth pinning:
  *REST request-response transport ≠
  cross-runtime authority continuity; transport
  carries data, bridge carries authority*.
- **Law 4 (Arbitration Compiler).** No
  candidate selection. One block name, one
  registered render_callback, one render per
  request. Omitted.
- **Federation.** Single block type, single
  PHP render. Not federation. Omitted.
- **Law 6 (Compiler ↔ Runtime Split).** Both
  sides of the transport are runtime contexts
  (editor JS request runtime, PHP render
  runtime). Build pipeline is upstream and not
  involved. Omitted.
- **Section X archetypes.** A preview-fetch
  component is not a "civilization." Same
  framework-omission discipline as the
  surrounding chunks. Omitted.

Two literacy contributions worth pinning:

> *Preview ≠ production runtime.* A render
> performed for editor preview, in a REST
> request context that approximates but does
> not equal a front-end page-load context, is
> not the same as the actual front-end render.
> The same `render_callback` produces the
> output in both cases — but the *context* the
> callback runs in differs (admin-side auth,
> editor user, edited-post context vs
> front-end context, anonymous-or-visitor user,
> published-post context). Equivalent output
> is the goal; bit-equivalence is not
> guaranteed when the callback reads context.

This contribution adds a *preview-vs-production*
form to the existence-vs-operation toolkit:
where prior toolkit members distinguished
states *within* one rendering context, this
one distinguishes the *same render's output*
across two different request contexts.

> *REST request-response transport ≠
> cross-runtime authority continuity.* A
> mechanism that has the editor send data to
> PHP, receive HTML back, and display it is
> not the same shape as a mechanism that
> preserves runtime authority across a context
> boundary. Both span runtimes; only one
> *bridges* in the Law 3b sense (state /
> identity / capability persistence across the
> boundary). Transport carries data; bridge
> carries authority. Different mechanisms;
> different shapes; different consequences for
> what the framework can guarantee.

This contribution adds a fourth distinct
example to the anti-Law-3b inventory:

- *File copy across phases* (block.json build
  pipeline) — file is artifact, not authority.
- *Async fetch* (resolver lifecycle) — server
  is source, not runtime context preservation.
- *Parallel realization* (per-block style
  attribution) — contract-shared
  implementations, not runtime bridging.
- *REST request-response* (this chunk) —
  data transport, not authority continuity.

Four distinct mechanisms whose surface
vocabulary tempts a Law 3b reading without
sharing the underlying mechanism. The pattern
across all four: the boundary is real, but
what crosses it is *data / artifact /
contract / request-response*, not *runtime
authority*.

The fourth case is the most surface-tempting
because it most directly looks like "editor
reaches into PHP and gets a result." Resisting
the temptation here is what proves the
discipline carries beyond its earlier
applications.

## CHECKLIST

When using `<ServerSideRender>`:

- [ ] Use it for dynamic blocks
      (`save: () => null`) where editor preview
      should reflect the actual front-end
      render.
- [ ] Pass the `attributes` prop as a stable
      reference; don't spread into a new
      object every render.
- [ ] Provide custom placeholder components
      (`Loading`, `Empty`, `Error`) for
      production-quality preview UX.
- [ ] Write `render_callback` to be
      deterministic from attributes wherever
      possible. Avoid branching on
      `is_admin()`, current user, or other
      request-context signals that differ
      between preview and front-end.
- [ ] If the `render_callback` is expensive,
      consider attribute-hash caching to keep
      preview responsive.
- [ ] Don't hand-roll the REST request. The
      component handles debouncing, lifecycle,
      and race conditions.
- [ ] Treat preview-vs-production divergences
      as `render_callback` correctness issues,
      not as ServerSideRender bugs. The
      component faithfully shows what the
      callback returns for the request
      context.

## REFERENCES

- `@wordpress/server-side-render` package
  reference. Documents component props,
  placeholder lifecycle, debouncing.
  https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/
- REST API — Block Renderer endpoint reference.
  Documents the `/wp/v2/block-renderer/{name}`
  endpoint shape.
  https://developer.wordpress.org/rest-api/reference/block-renderer/
- Block registration handbook — `render_callback`
  for dynamic blocks. The PHP function the
  REST endpoint invokes.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
- `@wordpress/server-side-render` README on
  GitHub. Most up-to-date prop documentation.
  https://github.com/WordPress/gutenberg/blob/trunk/packages/server-side-render/README.md

Cross-context:

- `block.dynamic-rendering` — the broader
  dynamic-block render mechanism.
  ServerSideRender is the editor-side preview
  layered on top of that PHP rendering
  pathway.
- `block.edit-and-save-contracts` — `save: () => null`
  is what makes the editor need this preview
  in the first place.
- `plugin-dev.register-rest-route` — the REST
  infrastructure ServerSideRender consumes.
  This chunk uses the core-provided endpoint;
  understanding REST registration in general
  is that chunk's territory.
- `data-layer.resolver-lifecycle` — adjacent
  async-fetch pattern with the same anti-Law-3b
  framing. Both are *async data fetches that
  look bridge-shaped but aren't*.
- `style-engine.per-block-style-attribution`
  — *parallel realization* anti-Law-3b
  reference. The same family of "spans
  runtimes but doesn't bridge" non-fits.
