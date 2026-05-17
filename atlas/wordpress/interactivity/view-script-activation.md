---
rule_id: interactivity.view-script-activation
domain: interactivity
topic: frontend-activation-contract
field_cluster: view-script-and-directive-staging
wp_min: "5.9"
wp_recommended: "6.5+"
status: stable
language: php-and-javascript
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
    section: "block.json viewScript / viewScriptModule field"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/
    section: "Interactivity API — view scripts, directives, store registration"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/
    section: "API reference — store(), getContext(), getElement(), directives"
    captured: 2026-05-10
  - url: https://make.wordpress.org/core/2024/03/04/interactivity-api-merged-in-6-5/
    section: "Interactivity API merge announcement — WP 6.5 baseline"
    captured: 2026-05-10
  - url: https://developer.wordpress.org/reference/functions/wp_enqueue_script/
    section: "wp_enqueue_script — selective enqueue for view scripts"
    captured: 2026-05-10
related:
  - interactivity.directive-protocol            # the directive grammar declared in HTML
  - interactivity.runtime-state                 # the store mechanism the view script registers
  - interactivity.hydration                     # the mechanism that makes declared directives live (next-layer concern)
  - block.json-assets                           # block.json field-level documentation for viewScript
  - block.dynamic-rendering                     # PHP render output that emits the directive-bearing HTML
  - build-tooling.wp-scripts                    # the build that produces view.js + view.asset.php
---

# RULE — block view-script and directive activation contract

## WHEN

You need to reason about *when* an interactivity-enabled
block becomes operational on the frontend — what triggers
the view script to load, what the JS does on first
execution, and the gap between *directive markup* in HTML
and *active behavior* in the browser. Use this knowledge
when:

- Authoring a new block that uses the Interactivity API
  (any block with `data-wp-*` directives in its rendered
  output).
- Configuring `block.json`'s `viewScript` /
  `viewScriptModule` field for a frontend script.
- Diagnosing "directives are in the HTML but nothing
  happens" — almost always one of: view script not
  enqueued, store not registered, or namespace
  mismatch.
- Understanding why some blocks ship view scripts that
  load on every page-with-the-block while others stay
  inert until interaction.
- Reading core code that walks the activation sequence
  (PHP enqueue → script tag → JS execution → directive
  hydration).

This chunk does **not** cover:

- The directive grammar itself (`data-wp-interactive`,
  `data-wp-bind--*`, `data-wp-on--*`, `data-wp-context`,
  etc.) — covered in `interactivity.directive-protocol`.
- The store mechanism (`store()`, `getContext()`,
  state shape, actions, callbacks) — covered in
  `interactivity.runtime-state`.
- The hydration mechanics that make declared directives
  reactive (the actual diff/patch loop, signal
  reactivity internals) — covered in
  `interactivity.hydration`.
- The build pipeline that produces view.js — covered in
  `build-tooling.wp-scripts` and
  `build-tooling.block-json-build-pipeline`.

The principle this chunk operates under: **A block that
ships interactivity moves through several activation
phases, each a distinct precondition for the next. The
HTML's `data-wp-*` directives are declarative markup
*before* the runtime activates them; behavior emerges
from the activation sequence, not from the markup alone.**

## SHAPE

### A. View script as the frontend entry point

A block declares its frontend script in `block.json`:

```json
{
  "name": "myplugin/counter",
  "render": "file:./render.php",
  "viewScript": "file:./view.js"
}
```

Two field forms exist:

- **`viewScript`** — classic script handle (loaded as
  `<script src="…">`). Available since WP 5.9.
- **`viewScriptModule`** — ES module handle (loaded as
  `<script type="module" src="…">`). Available in
  WP 6.5+; the recommended form for interactivity-API
  code that uses module imports.

Either form points (via `file:` resolution) at a JS
file in the build output. That JS file is the
*frontend* counterpart to `editorScript`'s editor-side
role:

- `editorScript` runs in the editor, registers the
  block's `edit` / `save` functions, mounts editor
  UI.
- `viewScript` runs on the public frontend, after the
  block is rendered, and is the place where
  interactivity stores get registered.

The two never both run at the same time on the same
page in the same role: the editor uses
`editorScript`; the frontend uses `viewScript`. (The
editor may render frontend previews using a separate
mechanism, but `viewScript` is not loaded into the
admin context as a block extension.)

### B. Selective enqueue — only when the block is present

The view script does not load on every frontend page.
Core enqueues it *conditionally*:

```
For each block type registered with a viewScript:
    If the rendered post contains an instance of this block,
        enqueue the view script.
    Else,
        do nothing.
```

The detection mechanism:

- For static blocks, core walks the parsed block tree
  (from `parse_blocks( $post_content )`) and matches
  on block name.
- For dynamic blocks, the block is "present" by
  virtue of having a render callback called during
  the request — the enqueue happens at render time.
- For block patterns / template parts / synced
  patterns, the same parse-and-match logic applies
  recursively.

This selective enqueue is the difference between an
interactivity ecosystem that scales (one plugin's
view script is irrelevant to pages that don't use
its blocks) and one that doesn't (every plugin
shipping a view script that loads everywhere).

The mechanism applies per-script-handle, not
per-block-instance. A page with five Counter blocks
loads `view.js` once, and the script's first
execution sets up the store; the runtime then
attaches to all five DOM instances.

### C. The directive declaration layer

The block's PHP-rendered HTML carries the directive
markup:

```html
<div
    data-wp-interactive="myplugin/counter"
    data-wp-context='{"count": 0}'
>
    <p data-wp-text="context.count">0</p>
    <button data-wp-on--click="actions.increment">+</button>
</div>
```

Three properties of this layer to pin:

- **The directives are static HTML attributes** at
  this point. The browser parses them like any
  other attributes; they do not yet do anything
  reactive.
- **Initial state is part of the markup.**
  `data-wp-context='{"count": 0}'` carries the
  initial count *as JSON inside the HTML*. The
  server already knew the starting state; the
  client doesn't need to fetch it.
- **The text content is also part of the markup.**
  The `<p>0</p>` is server-rendered. The
  `data-wp-text` directive will *take over* the
  text content once activated, but until then the
  user sees the server's "0" — no flash of empty
  content.

These three properties together mean the directive
markup is **declarative** — it describes the
intended interactive structure, with initial
appearance already correct, and waits for activation
to become responsive to user input.

### D. The activation sequence — five phases

For a single block instance to go from "rendered on
the page" to "fully reactive," it passes through:

```
PHASE 1 — REGISTRATION
   PHP: register_block_type( …, [ 'viewScript' => … ] )
   Effect: block type known; viewScript handle registered.

PHASE 2 — RENDER + ENQUEUE
   PHP: block instance is rendered (static or dynamic).
   Effect: directive-bearing HTML in output;
           view script handle marked for enqueue.

PHASE 3 — DELIVERY
   Browser receives HTML + <script> tag(s).
   Effect: HTML parsed; directives visible as attributes;
           script tag triggers download.

PHASE 4 — EXECUTION
   Browser loads view.js; JS runs.
   Effect: store() called, registering the namespace's
           state / actions / callbacks. Directives are
           still inert markup at this exact moment.

PHASE 5 — ACTIVATION (HYDRATION)
   Interactivity runtime walks the DOM, finds elements
   with data-wp-interactive matching a registered
   namespace, attaches reactive bindings.
   Effect: directives become live; clicks fire actions;
           text/class/style updates flow from store changes.
```

Each phase is a precondition for the next:

- Without Phase 1, there is no registered handle to
  enqueue.
- Without Phase 2, the script is not on the page.
- Without Phase 3, the JS doesn't reach the
  browser.
- Without Phase 4, no store is registered for the
  runtime to bind to.
- Without Phase 5, the markup remains static — the
  user sees correct initial appearance but
  interaction does nothing.

The conceptual point: **the directive markup is in
place by Phase 3, but the directives are not
*active* until Phase 5.** The gap between Phase 3
and Phase 5 is the activation gap. It is brief
(usually milliseconds), but it is a real distinct
state — and several failure modes (Section E) live
in that gap.

### E. Failure modes per phase

Each phase has its own failure shape:

- **Phase 1 fail.** Block not registered (or
  registered without `viewScript`). Result: no view
  script enqueued; HTML may render directives that
  no JS is loaded to activate. Symptom: directives
  in DOM, no script tag.
- **Phase 2 fail.** Block instance not detected
  (often: dynamic block whose render callback
  doesn't fire, or content delivered through a
  pathway core doesn't parse). Result: view script
  not enqueued for this request. Symptom: HTML
  contains directives but no `<script>` tag for
  that view script.
- **Phase 3 fail.** Network error / 404 on the
  script URL / wrong build path. Result: browser
  can't load the JS. Symptom: console error;
  directives never activate.
- **Phase 4 fail.** JS error during execution
  (syntax error, store() call missing, namespace
  string typo). Result: store not registered, or
  registered under wrong namespace. Symptom:
  console error or silent
  namespace-mismatch (the runtime walks the DOM,
  finds `data-wp-interactive="myplugin/counter"`,
  searches the registry for `"myplugin/counter"`,
  and finds nothing).
- **Phase 5 fail.** Hydration runtime error, or
  hydration disabled (rare). Result: store
  registered but DOM not bound. Symptom: store
  callable from the console; clicks don't fire
  actions.

The asymmetric diagnostic shape: *every phase has
distinct symptoms.* "It's not working" usually
narrows down quickly by checking which phase
produced the visible failure marker.

### F. Where this chunk hands off — adjacent concerns

The activation contract sits at the threshold of
three sibling chunks:

- **`interactivity.directive-protocol`** — what the
  directives *are* (syntax, semantic categories,
  scoped lookup).
- **`interactivity.runtime-state`** — what
  `store()` registers, how state / actions /
  callbacks compose, the per-namespace surface.
- **`interactivity.hydration`** — what happens
  *during Phase 5*: how the runtime walks the DOM,
  how directives bind to store paths, how the
  reactive update loop drives DOM changes.

This chunk's job is to make the *staging*
visible — to name the phases, identify the
preconditions, and locate where each sibling chunk
takes over. It does not duplicate any of them.

## WHY

### Why selective enqueue rather than always-load

The interactivity ecosystem is open-ended: any
plugin can ship blocks with view scripts. If every
view script loaded on every page, a single page
might pull dozens of unrelated scripts — most of
which would never have a matching block to
hydrate. The selective enqueue keeps page weight
proportional to actual block usage.

The cost is the parse-and-match step (cheap when
done as part of normal block parsing). The benefit
is that an interactivity-rich plugin doesn't tax
pages that don't use its features.

### Why declarative directives in HTML rather than imperative bootstrapping

A pre-Interactivity-API equivalent would have been:

```js
// Hypothetical bad pattern
document.addEventListener( 'DOMContentLoaded', () => {
    document.querySelectorAll( '.myplugin-counter' ).forEach( el => {
        const display = el.querySelector( '.count' );
        const button  = el.querySelector( '.increment' );
        let count = parseInt( el.dataset.initial, 10 );
        button.addEventListener( 'click', () => {
            count += 1;
            display.textContent = count;
        } );
    } );
} );
```

This works but produces several tax points:

- Every plugin reimplements the "find my elements,
  wire them up" pattern.
- The relationship between HTML structure and JS
  behavior lives in two places (the markup and the
  JS).
- Server-rendered initial state must be re-read by
  the JS (data attributes, `JSON.parse`,
  reconstruction).

The directive layer encodes the binding contract
*in the HTML* (using attributes), with initial
state co-located. The JS only registers the
namespace's behavior; the runtime handles the
"find and wire" loop generically.

### Why an activation sequence rather than one-step setup

The five-phase split exists because each phase
runs in a different operational context:

- Phase 1 runs at PHP plugin init (per-process).
- Phase 2 runs during request rendering
  (per-request).
- Phase 3 runs in the browser's network /parse
  layer (per-page-load).
- Phase 4 runs in the browser's JS execution layer
  (per-page-load).
- Phase 5 runs in the interactivity runtime's
  hydration step (per-page-load, after Phase 4).

Collapsing them would require running everything
in one context — impractical because PHP doesn't
run in the browser and the browser doesn't run
during PHP rendering. The phasing is the natural
shape of a frontend interactivity story; the value
of naming it explicitly is that diagnostics
(Section E) and code organization (which phase
owns what) become straightforward.

## WHEN NOT

Skip the activation sequence reasoning if:

- The block has **no frontend interactivity** — a
  static block that renders text/markup and never
  responds to user interaction. No `viewScript`
  needed; the activation sequence does not apply.
- You are working on **editor-side behavior** only.
  Editor scripts use `editorScript` and run in the
  editor runtime; the activation sequence
  documented here is frontend-only.
- The interactivity is **client-side-only** with no
  declared store (e.g., a vanilla JS one-off
  attached via traditional event listeners).
  Possible but loses the framework's benefits;
  this chunk does not cover that pathway.
- You are doing **frontend rendering through a
  non-WordPress system** (decoupled / headless).
  The activation contract here assumes WordPress
  is the renderer. Headless setups own their own
  activation story.

## COUNTER-PATTERNS

### Anti-pattern 1 — Calling `store()` outside the view script

```js
// In editorScript:
import { store } from '@wordpress/interactivity';
store( 'myplugin/counter', { state: { count: 0 } } );
```

The store registration belongs in the **view
script**. The editor script runs in the editor
context where the interactivity runtime is not
active for the rendered preview. Editor and
frontend are separate runtimes; don't conflate
their script lanes.

### Anti-pattern 2 — Namespace mismatch between HTML and JS

```html
<div data-wp-interactive="myplugin/counter">…</div>
```

```js
store( 'myplugin/Counter', { … } );  // capital C
```

The lookup is exact-string. Mismatch = directives
present, runtime walks DOM, finds nothing
matching, no error, no warning. Lint your
namespace strings and match them exactly between
HTML and JS.

### Anti-pattern 3 — Forgetting `viewScript` in `block.json`

```json
{
  "name": "myplugin/counter",
  "render": "file:./render.php"
  // viewScript missing
}
```

Without `viewScript`, no JS is enqueued. HTML
ships with directives that have no runtime to
activate them. Add the field and re-run
`wp-scripts build`.

### Anti-pattern 4 — Trying to use directives in editor-only contexts

```html
<div data-wp-interactive="…" data-wp-on--click="…">…</div>
```

…inside an editor preview that doesn't load the
view script. The interactivity runtime is not
active in the editor canvas (the editor does its
own React rendering of `edit`). Directives in
editor markup don't run.

### Anti-pattern 5 — Eagerly enqueuing the view script on every page

```php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_script( 'myplugin-counter-view', … );
} );
```

This bypasses selective enqueue. The script loads
on every frontend page whether the block is
present or not. For development, register through
`block.json`'s `viewScript` and let core handle
enqueue conditionally.

### Anti-pattern 6 — Treating Phase 3 markup as already-active

```js
// In some onload script that runs after HTML is parsed:
document.querySelector( '[data-wp-on--click]' ).click();
// Hopes that data-wp-on--click handler is wired up.
```

The directive is in markup at Phase 3, but the
runtime hasn't activated it yet (Phase 5). A
synthetic click here will not fire any
`actions.increment`. If you genuinely need to
trigger an action programmatically, do it from
inside another action (where the store is in
scope), not from external code racing the
hydration.

## OPERATIONAL NOTES

The activation contract's interpretive shape, in
proportional v2 vocabulary:

- **Law 1 (Declaration ≠ Exposure)** is the
  central fit, in a *staged* form. The view script
  handle is *declared* in `block.json`; it is
  *exposed* (enqueued) only when a block instance
  is detected on the page; the directives are
  *declared* in the rendered HTML; they are
  *exposed* (made reactive) only after Phase 5
  hydration. Two layered Law 1 instances —
  registration → enqueue, and markup → activation
  — composed into one activation sequence. Naming
  Law 1 here is genuinely clarifying because the
  whole concept of "declared interactivity ≠
  active behavior" is exactly Law 1's asymmetry
  applied across the activation gap.
- **Law 6 (Compiler ↔ Runtime Split)** appears in
  a *spanning* form. The HTML emission happens in
  the PHP runtime; the JS execution happens in the
  browser runtime. The view script bundle
  (build-time output of `build-tooling.wp-scripts`)
  is the artifact that crosses from build context
  through PHP enqueue into browser execution. The
  *split* this chunk surfaces is between PHP-side
  rendering context and browser-side activation
  context. Worth one section reference; not the
  central frame.
- **Doctrine 5 (Authority Continuity)** appears
  *lightly*. The namespace string
  (`"myplugin/counter"`) is the continuity surface
  — same namespace identifies the block in PHP
  registration, in the directive markup, and in the
  JS `store()` call. Continuity by name across
  rendering contexts. Worth one mention; not a
  section.
- **Federation** appears very lightly: many
  plugins each declare their own view scripts and
  namespaces; all federate around the single
  interactivity runtime registry. Same federation
  shape pinned across the JS layer; not
  re-elaborated.

What this chunk is **not** about:

- **Law 3b (Cross-Runtime Authority Continuity
  Bridge).** *The most important non-fit to clarify
  precisely.* Law 3b applies to the *hydration*
  step — Phase 5 — where server-rendered state
  (`data-wp-context='{"count":0}'`) is preserved
  into the client runtime as authoritative initial
  state. **That mechanism is not this chunk.** This
  chunk is the *staging* — the activation
  sequence's preconditions that *make the hydration
  step possible*. The chunk that owns Law 3b's
  application here is `interactivity.hydration`.
  The boundary is: this chunk ends at "the runtime
  attaches reactive bindings"; the bridge mechanism
  *inside* that attachment is the hydration
  chunk's territory. Conflating activation with
  hydration would dilute Law 3b's specific
  mechanism (state preservation across runtime
  boundary) into the broader "frontend code runs"
  story this chunk tells.
- **Law 4 (Arbitration Compiler).** No candidate
  selection. There is one view script per block
  type, one store per namespace. The selective
  enqueue is a Boolean cache lookup ("does the page
  contain this block?"), not a candidate ladder.
  Omitted.
- **Doctrine 6 (Authority Mediation).** No access
  mediation. Frontend rendering and JS execution
  are not capability-checked here. Omitted.
- **Section X archetypes.** A view script
  activation contract is not a "civilization."
  Same framework-omission discipline as the
  surrounding chunks. Omitted.

A small literacy contribution worth pinning:

> *Embedded capability ≠ activated behavior.*
> Markup that *describes* an interactive structure
> (directive attributes carrying initial state and
> binding instructions) is structurally distinct
> from runtime that *executes* that structure
> (the activation sequence completing through
> hydration). The directives are *legible to the
> browser* the moment they arrive in HTML; they
> are *reactive in the application sense* only
> after the runtime has loaded, registered the
> namespace, and walked the DOM. The gap between
> these two states is brief but real — a window
> in which the interface is visually correct but
> not yet interactive.

This pairs with the surrounding precision tools:

- *Availability ≠ activation* (i18n textdomains)
- *Configuration surface ≠ execution surface*
  (block-authoring control surfaces)
- *Need fulfillment ≠ option arbitration* (data
  layer resolvers)

Together these build a small toolkit for
recognizing when a system separates "the thing
exists" from "the thing is doing." The toolkit is
prose-level literacy, not constitutional
extension.

## CHECKLIST

When working with the activation contract:

- [ ] Declare the view script in `block.json`'s
      `viewScript` (or `viewScriptModule` for ES
      modules) field. Don't `wp_enqueue_script`
      manually unless you have a specific reason.
- [ ] Match the namespace string exactly between
      `data-wp-interactive` in HTML and `store()`
      call in JS. Lint these as paired strings.
- [ ] Encode initial state in the rendered HTML
      via `data-wp-context` so the server's
      initial render is correct without needing
      JS to populate it.
- [ ] When diagnosing "directives don't work,"
      walk the phase ladder: HTML present? script
      tag emitted? script loaded successfully? JS
      executed without error? namespace registered
      under exact name? Each phase has distinct
      symptoms.
- [ ] Don't run interaction-dependent code before
      hydration (Phase 5) completes. If you need
      to coordinate with hydration timing, do it
      from inside an action (where the store is
      already set up).
- [ ] Treat editor-side and frontend-side as
      separate activation lanes. `editorScript`
      runs in the editor; `viewScript` runs on
      the frontend. Don't put `store()` calls in
      editor scripts.

## REFERENCES

- `block.json` viewScript / viewScriptModule
  reference. Documents the field syntax and
  WP-version availability.
  https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
- Interactivity API reference. Documents `store()`,
  `getContext()`, `getElement()`, the directive
  family.
  https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/
- Interactivity API API reference (the deeper
  surface). Useful when wiring stores and
  callbacks.
  https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/
- Make WordPress Core — Interactivity API merge
  announcement (WP 6.5). The baseline this chunk
  targets.
  https://make.wordpress.org/core/2024/03/04/interactivity-api-merged-in-6-5/
- `wp_enqueue_script` reference. The mechanism
  that selective enqueue ultimately calls into.
  https://developer.wordpress.org/reference/functions/wp_enqueue_script/

Cross-context:

- `interactivity.directive-protocol` — the
  directive grammar (`data-wp-*` syntax,
  semantics) declared in HTML during Phase 3 and
  activated during Phase 5.
- `interactivity.runtime-state` — the store
  mechanism the view script registers in Phase 4.
- `interactivity.hydration` — the Phase 5
  mechanism, including the Law 3b state-bridge
  application that this chunk explicitly defers
  to.
- `block.json-assets` — field-level documentation
  for `viewScript` and other asset paths.
- `block.dynamic-rendering` — the PHP rendering
  pathway that emits the directive-bearing HTML.
- `build-tooling.wp-scripts` — the build pipeline
  that produces `view.js` and its `*.asset.php`.
