---
rule_id: interactivity.directive-protocol
domain: interactivity
topic: reactive-grammar
field_cluster: directive-surface
wp_min: "6.5"
wp_recommended: "6.5+"
status: evolving
language: html
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-interactivity/
    section: "@wordpress/interactivity — directive reference + runtime model"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/news/2024/04/03/getting-started-with-the-interactivity-api/
    section: "Getting Started with the Interactivity API (WP 6.5)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/
    section: "Interactivity API reference — store, context, directives, processor"
    captured: 2026-05-09
related:
  - block-authoring.block-json.bindings        # bindings = attribute ↔ source; directives = DOM ↔ runtime graph (extension)
  - data-layer.entity-resolution               # entity state participates in interactivity reactive graph
  - data-layer.persistence                     # directive-triggered actions may flow through persistence
  - block.dynamic-rendering                    # SSR HTML carries directives; PHP processor consumes them
  - style-engine.cascade-aggregation           # style-engine compiles authorities into cascade; interactivity compiles into reactive runtime — symmetric pair
  - block.json-context                         # block context propagation pairs with directive context propagation
  - (planned) interactivity.runtime-state      # store / actions / callbacks ontology — built on this directive surface
  - (planned) interactivity.hydration          # server-initial → client-mount boundary — separate chunk
---

# RULE — directive protocol — reactive authority grammar attached to HTML

## WHEN

Reading rendered Gutenberg HTML and encountering attributes
prefixed `data-wp-*` (or seeing them produced by block render
output / theme template). Use this chunk to understand:

- Why HTML attributes can declare reactive behavior (bindings,
  event handlers, conditional classes, iteration) without
  separate JavaScript code.
- The architectural role of directives as the **bridge between
  server-rendered HTML and client-side reactive runtime**.
- How directives differ from React/Vue templates (DOM-proximal
  vs VDOM ownership).
- Why this is the entry point of the interactivity bounded
  context (the syntactic surface that runtime-state and
  hydration build on).
- The symmetry with style-engine: both are declarative grammars
  compiled into runtime graphs interpreted by browser engines.

This is the **interactivity bounded context entry chunk**.
After Phase 7 substrate (bindings + entity-resolution +
persistence) was laid, interactivity can now enter as the
**reactive orchestration grammar layer** with concrete substrate
underneath.

## SHAPE

### Directive grammar surface

```html
<div
  data-wp-interactive="myPlugin"
  data-wp-context='{ "isOpen": false, "count": 0 }'
>
  <button
    data-wp-on--click="actions.toggle"
    data-wp-bind--aria-expanded="state.isOpen"
  >
    Toggle
  </button>

  <div
    data-wp-class--is-visible="context.isOpen"
    data-wp-bind--hidden="!context.isOpen"
  >
    <span data-wp-text="state.message"></span>
  </div>
</div>
```

**Directive prefix convention:** `data-wp-{kind}` (single value)
or `data-wp-{kind}--{target}` (target-scoped, e.g.,
`data-wp-bind--aria-expanded` binds to the `aria-expanded`
attribute specifically).

**Value evaluation:** strings interpreted as references into
the reactive graph (state / context / actions / callbacks) of
the declared interactive namespace. Simple expressions
supported (`!context.isOpen`); complex logic belongs in
actions / callbacks (TypeScript / JavaScript).

### Documented directive families

| family | example | role |
|---|---|---|
| **scope declaration** | `data-wp-interactive="namespace"` | declare interactive region + namespace binding |
| **local state** | `data-wp-context='{...}'` | declare scoped reactive context (subtree-local state) |
| **attribute binding** | `data-wp-bind--{attr}="state.x"` | bind DOM attribute to reactive value |
| **class binding** | `data-wp-class--{className}="state.x"` | conditionally apply class |
| **style binding** | `data-wp-style--{prop}="state.x"` | conditionally apply inline style |
| **text content** | `data-wp-text="state.x"` | bind element text to reactive value |
| **event handlers** | `data-wp-on--{event}="actions.x"` | attach event handler from action |
| **side effects** | `data-wp-watch="callbacks.x"` | run callback when reactive deps change |
| **initialization** | `data-wp-init="callbacks.x"` | run callback on mount |
| **iteration** | `data-wp-each="state.list"` | render template per list item |
| **identity hint** | `data-wp-key="item.id"` | keyed reconciliation hint for iteration |
| **imperative access** | `data-wp-run="callbacks.x"` | imperative reactive access during render |

(⚠ Specific directive set evolves per WP version; complete
current list requires reference to `@wordpress/interactivity`
documentation at consultation time.)

### Server-side directive processing

```
PHP-rendered HTML containing data-wp-* attributes
   ↓
WP Interactivity API processor (PHP)
   → reads directives during page render
   → may pre-evaluate certain directives server-side
     (e.g., apply server-known state to bindings)
   → emits HTML with initial state baked in
   ↓
HTML transmitted to browser
   (already in correct initial state — no flash of unhydrated content)
```

Server processing means: **the page is functional / visible
BEFORE JavaScript runs** in many cases. Bindings that depend
only on server-known state render with correct values
immediately.

### Client-side runtime attachment

```
Page DOM with data-wp-* attributes (already correct initial state)
   ↓
@wordpress/interactivity runtime loads
   ↓
Scans for data-wp-interactive="namespace" regions
   ↓
Resolves the registered store for each namespace
   (via store('namespace', {...}) calls in JS)
   ↓
Attaches reactive subscriptions per directive
   → DOM mutations react to state changes
   → event handlers wire to actions
   → side effects subscribe to dependency changes
   ↓
Page becomes interactive
```

The runtime ATTACHES to existing DOM rather than mounting /
constructing it. This is the DOM-proximal model (vs VDOM
ownership models).

### What this is NOT

- NOT a templating language. Templates produce DOM; directives
  ANNOTATE existing DOM.
- NOT a virtual DOM framework. The runtime does not own a
  virtual tree it diffs against; it observes / mutates the
  real DOM directly.
- NOT a replacement for React in the editor. Editor uses React;
  Interactivity is for FRONTEND interactive blocks (and select
  editor surfaces). Different runtimes for different concerns.
- NOT a state management library. State management is a
  separate concern documented in the future
  `interactivity.runtime-state` chunk; directives are the
  binding surface, not the state model.
- NOT browser-only. Server processing (PHP) means many
  directives have server-side semantics in addition to
  client-side reactivity.

## REQUIRES

- WP 6.5+ (Interactivity API stable release).
- `@wordpress/interactivity` runtime loaded on the page (core
  loads it when blocks declare interactivity; manual loading
  also possible).
- A registered store for each `data-wp-interactive="namespace"`
  region (via `store('namespace', { state, actions, callbacks })`).
- Server-side processor enabled (default in WP 6.5+ block
  rendering).
- Block.json `supports.interactivity: true` for blocks declaring
  interactive behavior (signals to core that the runtime should
  load).
- ⚠ Specific PHP processor behavior, server-side directive
  evaluation rules, and runtime hydration timing —
  verification-needed.

## INVARIANTS

### 1. Directives are reactive authority grammar, not data attributes

`data-wp-*` attributes are syntactically HTML data attributes
(per the HTML spec, any `data-*` attribute is freely defined by
authors). But Gutenberg interprets them as a **reactive
authority grammar**:

> `data-wp-bind--aria-expanded="state.isOpen"` is NOT
> "a data attribute named bind". It IS "an instruction to the
> Interactivity runtime: bind the aria-expanded DOM attribute
> to the reactive state.isOpen value, re-applying on every
> change."

Reading directives as plain data attributes misses entirely.
They are a **declarative reactive coordination protocol**
piggybacking on HTML's data-attribute namespace.

### 2. HTML becomes executable authority topology

Pre-Interactivity-API, HTML in WordPress was:
- Document structure (semantic markup)
- Visual presentation surface (CSS targeting)
- Persistent serialization (saved post content)

Post-Interactivity-API, HTML additionally is:
- **Reactive runtime declaration surface** — directives encode
  subscriptions, actions, and topology that the runtime
  realizes.

A page's HTML is no longer just a snapshot of content; it is
ALSO **executable authority topology** — declarations of how
the runtime should observe state, propagate changes, and
respond to events. The same document carries content + style
hooks + reactive instructions.

### 3. Directives are reactive attachment operators (bindings extension)

Bindings (block-authoring.block-json.bindings) connected:
- Block ATTRIBUTE ↔ AUTHORITY SOURCE

Directives connect:
- DOM NODE ↔ REACTIVE GRAPH

The two mechanisms share an ontological pattern:

| layer | attachment |
|---|---|
| bindings | attribute hosts attachment to source |
| **directives** | **DOM node hosts attachment to reactive graph** |

Directives are the **runtime / DOM extension** of bindings'
attachment-not-ownership pattern. Together they shift Gutenberg
from value-ownership ontology to attachment-ontology across
both the schema (block attributes) and runtime (DOM nodes)
layers.

### 4. Interactivity runtime is DOM-proximal — HTML remains primary reality

This is the key differentiator from React / Vue / Angular:

| model | HTML role | runtime role |
|---|---|---|
| VDOM (React) | runtime owns; HTML is render output | constructs and mutates virtual tree, reconciles to DOM |
| **DOM-proximal (Interactivity API)** | **HTML is primary reality** | **observes and mutates real DOM directly via attribute annotations** |

Implications:
- The page is a complete document at server render. Runtime
  loads to ATTACH behavior, not to RENDER content.
- No "client takes over" moment where runtime owns the DOM
  exclusively. Server-rendered HTML and runtime-mutated DOM
  are the SAME DOM throughout.
- Runtime can be small (no VDOM overhead, no diff/reconcile
  algorithm needed at the same scale as React).
- Progressive enhancement is structural — pages work without
  JS for non-interactive paths; JS adds interactivity onto
  working pages.

This aligns with WordPress's deep historical commitment to
server-first rendering.

### 5. Server-first rendering is structurally preserved

Server-side directive processing is NOT optional optimization;
it is structural to the architecture:

- Many directives can be PRE-EVALUATED server-side using
  server-known state (the post's data, the user's context).
- The transmitted HTML contains correct initial state baked in.
- Hydration on the client is incremental — the runtime attaches
  reactivity but doesn't re-render the document.

Result:
- No "flash of unhydrated content" (FOUC equivalent for
  reactive frameworks).
- SEO indexers see complete content (no "JS required to render"
  trap).
- Pages work at first byte arrival, not at first JS execution.

This is why Interactivity API is **server-first reactive** —
distinct from purely client-side reactive frameworks where
server pre-rendering is an add-on (SSR, SSG) rather than
foundational.

### 6. Style-engine ↔ interactivity symmetry — Gutenberg as dual-runtime declarative system

Two bounded contexts produce comparable outputs from
declarative inputs, both interpreted by browser engines:

| bounded context | declarative surface | runtime output | browser engine |
|---|---|---|---|
| style-engine | selectors / theme.json / styles | CSS graph (cascade) | CSS engine (compositor / layout / paint) |
| **interactivity** | **directives (data-wp-*)** | **reactive runtime graph** | **JS engine + interactivity package + DOM** |

Both:
- Authored declaratively.
- Compiled / runtime-interpreted.
- Browser-executed (different engines, both in browser).
- Result in DOM behavior (visual styling vs reactive behavior).

Gutenberg is therefore a **dual-runtime declarative system**:
- CSS runtime (style-engine output → browser CSS engine).
- Reactive JS runtime (directives → interactivity package).

Both runtimes attach to the same DOM. Both are server-first
(server-emitted CSS + server-emitted directive HTML). Both
have escape hatches (styles.css; arbitrary JS in actions /
callbacks).

### 7. Context propagation mirrors block tree topology

`data-wp-context='{...}'` declares scoped reactive context
that descendants inherit. This mirrors block.json's
`providesContext` / `usesContext` (block context propagation):

| layer | mechanism | propagation |
|---|---|---|
| block tree | providesContext / usesContext (block.json) | block-tree DI |
| **directive runtime** | **data-wp-context** | **DOM-tree reactive DI** |

The composition axis (parent provides, descendants consume)
recurs. Block context operates at editor / SSR layer; directive
context operates at runtime DOM layer. Same shape, different
runtime.

This is one of multiple "composition axis recurrences" the KB
has documented; directive context is the runtime-DOM instance.

### 8. Directives declare subscriptions and actions — reactive graph membership

A directive is more than "do this when X" — it declares
**membership in the reactive graph**:

- `data-wp-bind--{attr}="state.x"` declares: "this DOM
  attribute is a SUBSCRIBER to state.x; re-evaluate on every
  state.x change."
- `data-wp-on--click="actions.x"` declares: "this DOM node is
  an ACTION TRIGGER source for actions.x; user click invokes
  the action."
- `data-wp-watch="callbacks.x"` declares: "this scope is a
  SIDE-EFFECT participant; callbacks.x runs when its
  dependencies change."

Each directive places the DOM node into the reactive graph as
a specific kind of edge (subscriber / trigger / observer). The
Interactivity runtime maintains the graph; directives are the
declarations that populate it.

### 9. Directive evaluation crosses PHP → HTML → JS boundaries

The full lifecycle of a directive crosses runtime boundaries:

```
PHP (server) — block render emits HTML with directives
   ↓
PHP — Interactivity processor inspects directives, may pre-evaluate
   ↓
HTML (network transport) — directives serialized as attributes
   ↓
JS (client) — Interactivity runtime parses directives
   ↓
JS — runtime resolves namespace stores, attaches subscriptions
   ↓
DOM (browser) — runtime observes / mutates per directive semantics
```

Each boundary crossing has its own semantics:
- Server-side evaluation may differ from client-side (server
  knows context client doesn't and vice versa).
- HTML serialization preserves directive declarations for client
  consumption.
- Client-side parsing must handle malformed declarations
  gracefully.

This makes Interactivity API a **boundary-crossing protocol**,
not just a client-side framework.

### 10. Phase 7 capstone — distributed reactive authority orchestration

Bringing together bindings + entity-resolution + persistence +
this chunk:

> **Gutenberg is no longer a schema-driven visual compiler.**
> **Gutenberg is now distributed reactive authority orchestration**
> **across CSS, entities, persistence, and DOM-attached runtime grammars.**

The Phase 7 framing is now structurally complete:

| substrate / surface | role |
|---|---|
| bindings | block attribute attachment surface |
| entity-resolution | runtime authority substrate (read) |
| persistence | runtime authority substrate (write/reconciliation) |
| **directive-protocol** | **reactive coordination grammar** |

Together: the editor + frontend become a **reactive authority
graph** with multiple participants (entities, bindings,
directives, components) coordinating via reactive subscriptions
and async reconciliation.

This is the structural payoff of the interactivity bounded
context entry. Subsequent chunks (runtime-state, hydration,
others) will deepen the model; this chunk establishes the
syntactic surface and its ontological role.

## VERIFICATION NEEDED

`status: evolving` — Interactivity API is stable as of WP 6.5
but specific directives and behaviors evolve. Items requiring
verification:

- Complete current directive set (new directives added
  per WP version).
- Server-side processor semantics — which directives are
  pre-evaluated, which are passed through verbatim?
- Client-side runtime hydration timing relative to other
  asset loads.
- Behavior when a directive references undefined state /
  actions (silent skip? warning? error?).
- Multiple `data-wp-interactive` namespaces on the same page —
  isolation guarantees, store sharing semantics.
- Iteration directive (`data-wp-each`) reconciliation algorithm
  details.
- Performance characteristics under heavy directive density.
- Reactivity granularity — fine-grained signal propagation vs
  scope-level invalidation.
- Editor preview integration — do directives execute in editor
  preview or only frontend?
- Block.json `supports.interactivity` exact semantics and
  required values.
- Coexistence with other JS frameworks on the same page (React,
  Vue, jQuery legacy).
- Asset bundling — directives reference store names; how does
  the runtime ensure the registering JS has loaded before
  attempting hydration?
- Error boundaries — what happens when an action throws?
- Plugin extension paths — custom directive registration
  (verification-needed if supported).

For practical decisions: trust runtime observation
(browser DevTools) over inferred behavior; the API surface is
documented but subtle semantics often surface only at use.

## ANTIPATTERNS

- ❌ Treating `data-wp-*` as plain data attributes for arbitrary
  data storage. Conflicts with Interactivity runtime's parser;
  may cause unexpected behavior.
- ❌ Manipulating directive-bound DOM nodes via vanilla DOM API
  (e.g., `element.setAttribute('aria-expanded', 'true')`). The
  runtime owns these attributes; manual mutation will be
  overwritten on next reactive update.
- ❌ Using directives without registering a corresponding
  `store('namespace', {...})`. Directives reference store
  members; without a store the references resolve to nothing.
- ❌ Putting complex logic in directive value strings.
  Directives are bindings, not script. Logic belongs in actions
  / callbacks (real JS).
- ❌ Assuming directives execute server-side in all cases.
  Some directives are pure client-side (event handlers,
  reactive subscriptions). Server-side processing is a partial
  pre-evaluation, not full execution.
- ❌ Mixing React component state (editor) with directive state
  (frontend) expecting they share. Editor uses React;
  Interactivity is a separate runtime. State must be passed
  via shared substrate (entity / context propagation), not
  cross-runtime sharing.
- ❌ Using directives for editor-only behavior. Interactivity
  is frontend-focused (and select editor surfaces); editor
  block UI uses React + WordPress data layer.
- ❌ Hardcoding namespace strings across many blocks. Each
  interactive region needs its namespace; conflict with another
  plugin's namespace causes store collisions.
- ❌ Building progressively-enhanced UX assuming JS always
  loads. Directives DEGRADE on JS-disabled clients (the HTML
  is correct; reactivity is absent). UI patterns expecting
  reactivity must consider the JS-off case.
- ❌ Assuming directive parsing handles arbitrary HTML
  whitespace. Be conservative with attribute formatting;
  parsing may have edge cases.
- ❌ Using directives as a templating system. They annotate
  existing DOM; they don't generate it. For dynamic HTML
  generation, server-side render or use iteration directives
  with templates.

## RELATED

- `block-authoring.block-json.bindings` — bindings opened the
  attachment-not-ownership pattern at attribute layer; directives
  extend it to DOM layer. The two are the schema-side and
  runtime-side of the same shift.
- `data-layer.entity-resolution` — entity state participates
  in interactivity reactive graph. Stores can subscribe to
  entity selectors, propagating entity changes to directive-
  bound DOM.
- `data-layer.persistence` — directive-triggered actions may
  invoke persistence (`saveEditedEntityRecord`, etc.). The
  reconciliation lifecycle from data-layer.persistence applies
  when directives mutate entities.
- `block.dynamic-rendering` — SSR HTML carries directives;
  dynamic blocks emit directive-annotated markup, the
  Interactivity processor consumes it server-side, then the
  client runtime hydrates.
- `style-engine.cascade-aggregation` — symmetric counterpart.
  Both bounded contexts produce runtime graphs from declarative
  surfaces; together they form Gutenberg's dual-runtime
  declarative system (CSS + reactive JS).
- `block.json-context` — block context propagation pairs with
  directive context propagation (`data-wp-context`). Same
  composition axis at different runtime layers.
- (planned) `interactivity.runtime-state` — store / actions /
  callbacks ontology. Built on this directive surface.
  Recommended next interactivity chunk.
- (planned) `interactivity.hydration` — server-initial →
  client-mount boundary. Specific lifecycle details abstracted
  in this chunk's SHAPE section 4.
- (planned) `interactivity.directive-families-deep` — when
  individual directive families warrant deeper treatment
  (iteration semantics, watch dependency tracking, event handler
  patterns).

## META

**Interactivity bounded context entry chunk.**

This is to interactivity what `selectors` was to style-engine —
the syntactic surface that reasoning anchors on. Subsequent
interactivity chunks (runtime-state, hydration, families) build
on the directive grammar established here.

**Phase 7 substrate + entry point now in place:**

```
Phase 7 (reactive authority graph runtime):
   bindings              → attribute attachment surface          ✓
   entity-resolution     → read substrate                        ✓
   persistence           → write/reconciliation substrate        ✓
   directive-protocol    → reactive coordination grammar         ✓
   ↓
   (NEXT) interactivity.runtime-state    → store/actions/callbacks ontology
   (NEXT) interactivity.hydration        → server-initial → client-mount boundary
```

**KB-level framing complete (Phase 7 capstone):**

> Gutenberg evolved from
> **schema-driven visual compilation**
> into
> **distributed reactive authority orchestration**
> across CSS, entities, persistence, and DOM-attached runtime
> grammars.
>
> Two-axis system across Phases 1-6 (visual compiler) and
> Phase 7 (reactive runtime) is now structurally complete at
> the substrate level. Specialization chunks (entity-types,
> directive-families, hydration deep-dives) extend this
> structure; the structural backbone is in place.

**Dual-runtime declarative system framing now articulable:**

| runtime | declarative surface | engine | output |
|---|---|---|---|
| CSS runtime | selectors / theme.json / styles | browser CSS engine | visual cascade |
| Reactive JS runtime | directives (data-wp-*) | @wordpress/interactivity + JS engine | reactive DOM behavior |

Both runtimes:
- Server-first rendered (CSS injection at SSR; directive HTML
  at SSR with processor pre-evaluation).
- Author declaratively, runtime interprets.
- Browser executes via dedicated engine.
- Have escape hatches (styles.css for CSS; actions/callbacks
  with arbitrary JS for reactivity).

**DSL extension applied:** VERIFICATION NEEDED + META, per
runtime/implementation-derived applicability.

**Status `evolving`** — Interactivity API is stable as of
WP 6.5 but actively evolving (new directives, semantic
refinements per WP version).

**Anticipated next chunks (priority):**

1. **`interactivity.runtime-state`** — store / actions /
   callbacks ontology. Built on directive protocol; documents
   the state model the directives reference. Most natural
   next chunk.

2. **`interactivity.hydration`** — server-initial → client-mount
   boundary. Specific lifecycle / timing / failure-mode
   ontology. May reveal additional KB-level invariants about
   compiler / runtime boundary.

3. **`block.dynamic-rendering` retro patch** — connect dynamic
   render output to directive emission + interactivity runtime.
   dynamic-rendering pre-dates Interactivity API; retro patch
   reframes it through current ontology.

Recommended sequence: (1) runtime-state to complete
interactivity's basic ontology pair; (2) hydration to close
the bounded context backbone; (3) retro patches as needed.

---

## Q9 RETROACTIVE PATCH — Phase 8.12 Multi-Lens Frontier Retro (2026-05-10)

> **Retroactive verification triggered by**:
> Phase 8.10 KB Constitution v1.6 declaration + Phase 8.8
> consolidation Section E frontier map P4 (Bridge Pattern
> Recurring cross-context audit pathway). After Phase 8.9
> retro arc (P1 DIVERGENT + P3 ADDITIVE/CONFIRM) and Phase
> 8.10 HARD/SOFT formalization, directive-protocol becomes
> highest-leverage frontier test under THREE simultaneous
> lenses.
>
> **Strategic role**: Triple-frontier stress test —
> (a) Bridge Pattern 3rd-context verification (Recurring
> cross-context threshold), (b) Computational-architectural
> character category cross-context verification, (c) Doctrine
> 6 falsification (Phase 8.10 Law 1 trap discipline).
>
> **Methodological framing**: This retro applies **multi-lens
> analysis** with explicit lens prioritization:
> - **PRIMARY**: Bridge Pattern (likely confirm path)
> - **SECONDARY**: Governance vs Computational taxonomy
>   (likely strengthen path)
> - **TERTIARY**: Doctrine 6 falsification (likely divergent
>   path; "Syntax ≠ Mediation" trap warning)
>
> **Q9 retro discipline**: Confirm / Distributed / Divergent /
> Additive verdict per Phase 7.6+ retroactive verification
> protocol. Each lens may produce distinct verdict.

### Retro context

This chunk was authored 2026-05-09 (Phase 7-native), pre-
Doctrine-6-formalization, pre-Bridge-Pattern-surfacing.
Original analysis describes directive-protocol as **reactive
authority grammar** with **dual-runtime declarative system**
framing (interactivity ↔ style-engine symmetry). Critically,
the chunk's own line 42-43 explicitly self-identifies:

> "the architectural role of directives as the **bridge
> between server-rendered HTML and client-side reactive
> runtime**"

This is **explicit pre-formalization Bridge Pattern
language** in original chunk.

The retro questions:
- **Q1 (Bridge)**: Is directive-protocol a 3rd Bridge Pattern
  instance qualifying for Recurring (cross-context)
  promotion?
- **Q2 (Taxonomy)**: Does interactivity constitute
  Computational-architectural character (parallel to
  style-engine) OR governance-architectural crossover?
- **Q3 (Doctrine 6)**: Does directive-protocol exhibit
  genuine Doctrine 6 mediation character, OR is "directive
  gating" merely execution syntax (Law 1 trap)?

### LENS 1 (PRIMARY) — Bridge Pattern verification

#### Latent Bridge Pattern evidence

Pre-Phase-8.5 Bridge Pattern instances:
1. script-translations (i18n): PHP-initiated + HTML-mediated
   (inline `<script>` setLocaleData) + JS-consumed
   (wp.i18n)
2. locale-switching (i18n): asymmetric coverage refinement
   (static vs dynamic Bridge)
3. notices (admin-ui): round-trip PHP→JS→AJAX→PHP

Post-Phase-8.10 Bridge Pattern: Local (3 instances × 2
bounded contexts).

Re-reading directive-protocol through Bridge Pattern lens:

| original chunk element | Bridge Pattern reading |
|---|---|
| "the architectural role of directives as the **bridge between server-rendered HTML and client-side reactive runtime**" (line 42-43) | EXPLICIT bridge self-identification |
| Invariant 9: "Directive evaluation crosses PHP → HTML → JS boundaries" | EXPLICIT 3-runtime boundary crossing |
| "PHP (server) — block render emits HTML with directives" | Bridge **initiation** at PHP runtime |
| "PHP — Interactivity processor inspects directives, may pre-evaluate" | Server-side preprocessing (parallel to wp_set_script_translations) |
| "HTML (network transport) — directives serialized as attributes" | HTML-layer **mediation** (parallel to inline `<script>` for nonces/translations) |
| "JS (client) — Interactivity runtime parses directives" | JS runtime **consumption** |
| "JS — runtime resolves namespace stores, attaches subscriptions" | JS-side state attachment (parallel to wp.i18n.setLocaleData) |
| "DOM (browser) — runtime observes / mutates per directive semantics" | Final destination (DOM behavior) |
| "Interactivity API a **boundary-crossing protocol**" (line 388-389) | EXPLICIT boundary-crossing classification |
| "Server-first rendering is structurally preserved" (Invariant 5) | Same Bridge character as script-translations static dispatch |

> **Latent finding**: directive-protocol IS Bridge Pattern
> manifestation in cleanest form documented in KB. Original
> chunk used "bridge" + "boundary-crossing protocol" language
> 6 months before Bridge Pattern was surfaced as candidate.

#### Bridge Pattern character match analysis

| dimension | script-translations | notices | **directive-protocol** |
|---|---|---|---|
| Initiation | PHP (wp_set_script_translations) | PHP (admin_notices hook) | **PHP (block render + processor)** |
| Mediation | HTML (`<script>setLocaleData`) | HTML+AJAX (notice + dismissal endpoint) | **HTML (data-wp-* attributes)** |
| Consumption | JS (wp.i18n) | JS (event listener + AJAX) | **JS (@wordpress/interactivity runtime)** |
| Direction | one-way (PHP→JS) | round-trip (PHP→JS→PHP) | **one-way (PHP→JS) with ongoing reactive subscription** |
| Ongoing? | static (one-time data transfer) | event-driven (dismissal action) | **continuous (reactive subscription topology)** |
| Server-side preprocessing? | yes (per-handle JSON generation) | minimal | **yes (Interactivity processor pre-evaluation)** |

> **directive-protocol Bridge Pattern character**: Most
> closely matches script-translations (one-way PHP→HTML→JS
> with server-side preprocessing) BUT with continuous
> reactive subscription dimension (vs script-translations
> one-time data transfer).

This may surface a **Bridge Pattern sub-character distinction**
(static-data vs reactive-subscription) — observation only,
defer to future formalization.

#### Q9 verdict (Lens 1) — ADDITIVE + CONFIRM (STRONG)

Per Phase 7.6+ retroactive verification protocol:

| verdict | applicability for Bridge Pattern |
|---|---|
| Confirm | Bridge Pattern manifestation matches existing 3 instances | YES — matches script-translations character closely |
| Distributed | Bridge distributed across multiple mechanisms | PARTIAL — composite directive families form one Bridge |
| Divergent | Structurally different from Bridge | NO — explicit self-identification as bridge |
| **Additive** | **Adds 3rd-bounded-context Bridge instance + reactive-subscription character extension** | **YES — primary verdict** |

> **Lens 1 verdict: ADDITIVE + CONFIRM (STRONG).**
>
> Bridge Pattern manifests in directive-protocol as 3rd
> bounded context instance (interactivity NEW, after i18n +
> admin-ui). Bridge Pattern Recurring (cross-context)
> promotion threshold (≥3 bounded contexts) DECISIVELY MET.

### Bridge Pattern PROMOTION — Local → Recurring (cross-context)

> **5th KB PROMOTION EVENT (post-Phase-8.10)**:

Pre-this-retro Bridge Pattern status:
- **Local** (Phase 8.5+ promotion via notices)
- 3 instances × 2 bounded contexts (i18n + admin-ui)
- Recurring (cross-context) pathway open (needs 3rd bounded
  context)

Post-this-retro Bridge Pattern status:
- **Recurring (cross-context)** — PROMOTED via this retro
- 4 instances × 3 bounded contexts (i18n + admin-ui + **interactivity**)
- KB-Wide LAW promotion pathway: 5th KB candidate-tier
  promotion event; audit consideration becomes viable

> **Bridge Pattern PROMOTED Surfaced→Local→Recurring
> cross-context.** This is **5th KB PROMOTION EVENT**:
> 1. slotfills (Authority Interception Surface, Surfaced→Local)
> 2. admin-menus (Mediation, Local→Recurring intra-context)
> 3. capabilities-and-roles Q9 retro (Resolution Distributed)
> 4. notices (Bridge Pattern, Surfaced→Local)
> 5. **directive-protocol Q9 retro (Bridge Pattern, Local→Recurring cross-context)**

### LENS 2 (SECONDARY) — Computational vs Governance taxonomy

#### Phase 8.9 P1 retro observation context

P1 (style-engine.preset-materialization) DIVERGENT verdict
surfaced "governance-architectural" vs "computational-
architectural" character distinction:

- **Governance-architectural**: bounded contexts where
  Doctrine 6 manifests (admin-ui, editor-customization,
  plugin-dev, i18n, block-authoring)
- **Computational-architectural**: bounded contexts where
  Doctrine 6 absent; Resolution + Compiler/runtime +
  Authority Continuity dominant (style-engine confirmed; 1
  instance)

#### Interactivity bounded context character analysis

Original chunk's INVARIANT 6 directly states:

> "Style-engine ↔ interactivity symmetry — Gutenberg as
> dual-runtime declarative system"

| dimension | style-engine | interactivity |
|---|---|---|
| Declarative surface | selectors / theme.json / styles | directives (data-wp-*) |
| Runtime output | CSS graph (cascade) | reactive runtime graph |
| Browser engine | CSS engine | JS engine + interactivity |
| Server-first | yes | yes |
| Authored declaratively | yes | yes |
| Browser-executed | yes | yes |
| Has escape hatches | styles.css | actions/callbacks JS |

**Original chunk EXPLICITLY positions interactivity as
PARALLEL to style-engine** (Invariant 6 + META framing):
"Gutenberg is therefore a **dual-runtime declarative
system**".

Per Phase 8.9 P1 finding (style-engine = Computational-
architectural):
- If interactivity is PARALLEL to style-engine
- And style-engine is Computational-architectural
- Then interactivity is likely also Computational-architectural

#### Q9 verdict (Lens 2) — STRENGTHENED (Computational-architectural)

| candidate character | applicability for interactivity |
|---|---|
| Computational-architectural | YES — original chunk explicitly positions interactivity as parallel to style-engine; both produce runtime graphs from declarative surfaces |
| Governance-architectural | NO — directive grammar is reactive coordination, not access gating |
| Hybrid character | Possible BUT not strong — could be tested via runtime-state + hydration chunks |

> **Lens 2 verdict: Computational-architectural character
> CONFIRMED for interactivity bounded context.**
>
> Computational-architectural character category gains
> 2nd-context manifestation (style-engine + interactivity).
> Cross-context verification advances.

#### Bounded context character category coverage update

**Pre-this-retro character category coverage**:

| character category | Doctrine 6 manifests? | bounded contexts |
|---|---|---|
| Schema authority | YES | block-authoring |
| Authority federation | YES | plugin-dev |
| Governance modulation | YES | admin-ui + editor-customization |
| Composition runtime | UNTESTED | (site-building untested) |
| Compiler/runtime | NO (DIVERGENT P1) | (style-engine: computational-architectural) |
| Semantic substrate | YES (deferred-category) | i18n |

**Post-this-retro character category coverage**:

| character category | Doctrine 6 manifests? | bounded contexts |
|---|---|---|
| Schema authority | YES | block-authoring |
| Authority federation | YES | plugin-dev |
| Governance modulation | YES | admin-ui + editor-customization |
| Composition runtime | UNTESTED | (site-building untested) |
| Compiler/runtime | NO (DIVERGENT) | style-engine + **interactivity** (both computational-architectural) |
| Semantic substrate | YES (deferred-category) | i18n |

> **Computational-architectural classification has 2nd
> instance**. Cross-context verification threshold
> (typically 2-3 instances) approached for character
> taxonomy formalization candidate.

#### NEW observation — Bounded context character bifurcation pattern

This retro surfaces a **bounded-context-level pattern
observation**:

> **Bounded contexts may bifurcate by Doctrine 6
> manifestation pattern**:
> - **Governance-architectural**: Doctrine 6 manifests; access
>   gating choreography is structurally central
> - **Computational-architectural**: Doctrine 6 absent;
>   declarative surfaces compile to runtime graphs without
>   access gating

Examples:
- Governance-architectural: admin-ui, editor-customization,
  plugin-dev, i18n, block-authoring
- Computational-architectural: style-engine, interactivity

> **Status: SURFACED ONLY.** 5 governance-architectural + 2
> computational-architectural instances observed; cross-
> context verification approaching threshold (Composition
> runtime + Schema authority secondary categories untested
> for full bifurcation).

Phase 8.14+ candidate spec patch: bounded context character
taxonomy formalization with bifurcation as foundational
distinction.

### LENS 3 (TERTIARY) — Doctrine 6 falsification

#### Critical methodological discipline — "Syntax ≠ Mediation" trap warning

Per Phase 8.10 patch's Law 1 trap principle + Phase 8.12
strategic guidance:

> "Do NOT force 6-SOFT where 'directive gating' is merely
> execution syntax."

Each directive family must be screened: does it GATE access
to authority/feature, or does it merely DECLARE reactive
behavior (execution syntax)?

#### Directive family screening

| directive family | screening result | Doctrine 6? |
|---|---|---|
| `data-wp-interactive` | declares interactive REGION + namespace | ❌ scope declaration (not access gating) |
| `data-wp-context` | declares scoped reactive context (subtree-local state) | ❌ data scoping (not access gating) |
| `data-wp-bind--{attr}` | binds DOM attribute to reactive value | ❌ reactive attachment (not access gating) |
| `data-wp-class--{class}` | conditionally apply class | ❌ conditional execution (not access gating) |
| `data-wp-style--{prop}` | conditionally apply inline style | ❌ conditional execution (not access gating) |
| `data-wp-text` | bind element text to reactive value | ❌ reactive attachment (not access gating) |
| `data-wp-on--{event}` | attach event handler from action | ❌ handler attachment (not access gating) |
| `data-wp-watch` | run callback on dep change | ❌ callback attachment (not access gating) |
| `data-wp-init` | run callback on mount | ❌ lifecycle attachment (not access gating) |
| `data-wp-each` | render template per list item | ❌ iteration syntax (not access gating) |
| `data-wp-key` | keyed reconciliation hint | ❌ reconciliation metadata (not access gating) |
| `data-wp-run` | imperative reactive access | ❌ imperative attachment (not access gating) |

> **All 12 directive families are REACTIVE GRAMMAR / EXECUTION
> SYNTAX, NOT access gating.**

What about indirect Doctrine 6 candidates?

| candidate | screening | Doctrine 6? |
|---|---|---|
| `block.json supports.interactivity: true` | block opt-IN to interactivity capability | ❌ capability declaration (not access gating) |
| Server-side processor "may pre-evaluate" | execution control (when to evaluate) | ❌ execution timing (not access gating) |
| Namespace isolation between `data-wp-interactive` regions | scope-level isolation | ⚠ moderate (boundary character but not gating) |

> **Honest finding**: directive-protocol's evidence is
> overwhelmingly REACTIVE GRAMMAR + BOUNDARY-CROSSING
> PROTOCOL. **Doctrine 6 character is structurally absent
> from this chunk.**

#### Q9 verdict (Lens 3) — DIVERGENT (consistent with Phase 8.9 P1 + computational-architectural classification)

| verdict | applicability for Doctrine 6 |
|---|---|
| Confirm | Doctrine 6 manifestation matches existing 6a-6i sub-elements | NO — no gating mechanism present |
| Distributed | Single Doctrine 6 distributed across directive families | NO — directives are reactive grammar, not gating |
| **Divergent** | **Structurally different from Doctrine 6 character** | **YES — primary verdict** |
| Additive | Adds NEW Doctrine 6 sub-element | NO — chunk doesn't extend Doctrine 6 |

> **Lens 3 verdict: DIVERGENT.**
>
> directive-protocol does NOT manifest Doctrine 6 (Authority
> Access Mediation). Reactive grammar ≠ access gating.
> "Syntax ≠ Mediation" trap successfully avoided.

This **CONFIRMS Phase 8.10 Law 1 trap warning operational
discipline** — honest screening identifies execution syntax
without force-fitting Doctrine 6.

### Combined multi-lens verdict synthesis

| lens | verdict | constitutional impact |
|---|---|---|
| **PRIMARY (Bridge)** | **ADDITIVE + CONFIRM (STRONG)** | **Bridge Pattern PROMOTED Local→Recurring (cross-context); 5th KB PROMOTION EVENT** |
| **SECONDARY (Taxonomy)** | **STRENGTHENED (Computational-architectural)** | 2nd computational-architectural bounded context instance; character taxonomy formalization pressure increased |
| **TERTIARY (Doctrine 6)** | **DIVERGENT** | "Syntax ≠ Mediation" trap successfully avoided; computational-architectural classification reinforced via Doctrine 6 absence |

> **Combined Phase 8.12 verdict**: Triple-frontier stress
> test produces 3 distinct constitutional outcomes
> (promotion + taxonomy strengthening + boundary clarification).
> Maximum constitutional information gain.

### Q10 sub-pattern emergence (retro)

> **Q10 RETRO ANSWER: YES — Bridge Pattern sub-character
> distinction observation surfaced (static-data vs
> reactive-subscription).**

Comparing Bridge Pattern instances:

| instance | bounded context | sub-character |
|---|---|---|
| script-translations | i18n | static-data Bridge (one-time data transfer) |
| locale-switching | i18n | asymmetric Bridge (static-only coverage) |
| notices | admin-ui | round-trip Bridge (event-driven persistence) |
| **directive-protocol** | **interactivity** | **reactive-subscription Bridge (continuous reactive topology)** |

> **NEW Bridge Pattern sub-character observation**: Bridge
> Pattern may have multiple sub-characters (static-data /
> asymmetric / round-trip / reactive-subscription) per
> directional + temporal character.

Honest evaluation per Phase 7.5 Doctrine 3 Epistemic
Integrity:
- Bridge Pattern just promoted to Recurring (cross-context)
- Sub-character formalization needs Bridge Pattern audit
  consideration first
- Per discipline: surface observation, defer formalization

> **Sub-character observation surfaced; formalization
> deferred to Phase 8.13+ Bridge Pattern audit.**

### Bridge Pattern audit consideration (Phase 8.13+)

Bridge Pattern is now **Recurring (cross-context)** —
qualifies for audit consideration per established Phase 7.8
Resolution + Phase 8.x/8.6 Mediation precedent.

**Pre-audit evaluation against Standard 5-criteria gate**:

| criterion | status | notes |
|---|---|---|
| 1 — Context PRESENCE ≥ 4 | ⚠ 3 contexts (i18n + admin-ui + interactivity) | needs 4th context |
| 2 — Architectural variants ≥ 2 | ✅ 4 sub-characters (static-data / asymmetric / round-trip / reactive-subscription) | met if sub-characters count as variants |
| 3 — Intra-context density ≥ 1 | ✅ i18n (script-translations + locale-switching) | met |
| 4 — Q10 sub-pattern check | ⚠ sub-character observation surfaced this retro | needs explicit evaluation |
| 5 — Forward + retro both | ✅ forward (script-translations + notices) + retro (directive-protocol Q9) | met |

> **Bridge Pattern audit gate readiness: 3-4/5 criteria met.**
> Phase 8.13+ Bridge Pattern audit becomes viable IF 4th
> bounded context Bridge instance discovered (data-layer
> persistence? block-authoring registration? etc.).

Q9 retro candidates for 4th Bridge instance:
- `block-authoring.registration` family Q9 retro (block.json
  PHP register_block_type → JS registerBlockType auto-bridge)
- `data-layer.persistence` Q9 retro (REST mutations + AJAX
  parallel to notices dismissal)
- `style-engine.preset-materialization` Q9 retro (PHP theme.json
  → CSS variables → JS computed style)

Most likely 4th instance: `block-authoring.registration`
family (block.json explicit Bridge mechanism).

### Constitutional Field Test additions (post-retro)

#### Table A — Universal Law Manifestation (retro additions)

| Law / Doctrine | Pre-retro reading | Post-retro reading | Status change |
|---|---|---|---|
| **Bridge Pattern (candidate)** | (didn't exist at chunk authoring time) | EXPLICIT bridge self-identification (line 42-43) + 4-stage boundary crossing protocol | **Strong retroactive confirmation; PROMOTED to Recurring (cross-context)** |
| **Doctrine 6 (Authority Access Mediation)** | (didn't exist at chunk authoring time) | DIVERGENT — reactive grammar ≠ access gating | **Honest divergent verdict; computational-architectural classification reinforced** |
| **Law 3 (Authority Continuity)** | implicit (boundary crossing protocol) | confirmed — 4-stage authority continuity (PHP→HTML→JS→DOM) | (retroactively confirmed) |
| **Law 6 (Compiler ↔ Runtime Split)** | implicit (server-first + client runtime) | confirmed STRONG — dual-runtime declarative system | (retroactively confirmed) |
| **Law 5 (Entity → Relationship Pivot)** | implicit (DOM ↔ reactive graph attachment) | confirmed — directives create reactive graph membership relationships | (retroactively confirmed) |
| **Doctrine 5 (Arbitration ↔ Resolution Paired Operations)** | implicit (directive resolution at runtime) | weak — present but secondary character | (retroactively noted) |

#### Table B — Pattern Recurrence (retro additions)

| Candidate | Pre-retro status | Post-retro outcome | Effect on candidate |
|---|---|---|---|
| **Bridge Pattern** | Local (3 instances × 2 contexts) | 4 instances × 3 contexts (interactivity NEW) | **PROMOTED Local→Recurring (cross-context); 5th KB PROMOTION EVENT; Phase 8.13+ audit consideration viable** |
| **Authority Mediation Surface (Doctrine 6)** | Doctrine-tier; 9 sub-elements; v1.6 formalized variants | Compiler/runtime category 2nd-instance VERIFIED ABSENT | **Computational-architectural classification reinforced; Doctrine-tier permanence STRENGTHENED** |
| **Computational-architectural character (NEW observation)** | Surfaced (single instance: style-engine) | 2nd instance (interactivity) | **STRENGTHENED toward formalization-eligible (cross-context verification approaching threshold)** |
| **Bounded context character bifurcation pattern (NEW observation)** | did not exist | Governance-architectural (5 contexts) vs Computational-architectural (2 contexts) bifurcation | **Surfaced (Phase 8.14+ formalization candidate)** |
| **Bridge Pattern sub-character distinction (NEW observation)** | did not exist | Static-data / asymmetric / round-trip / reactive-subscription sub-characters | **Surfaced (Phase 8.13+ Bridge Pattern audit may evaluate)** |

### NEW KB-level findings

**1. Bridge Pattern PROMOTED Local → Recurring (cross-context)**

| dimension | pre-this-retro | post-this-retro |
|---|---|---|
| Bridge Pattern status | Local | **Recurring (cross-context)** |
| Bounded context coverage | 2 (i18n + admin-ui) | **3 (+ interactivity)** |
| Underlying Bridge instances | 3 | **4** |
| Promotion event count | 4 | **5** (5th KB promotion event) |
| Audit consideration | not viable | **Phase 8.13+ viable (3-4/5 criteria met)** |

**2. Computational-architectural character — 2nd instance manifestation**

| character category | pre-this-retro | post-this-retro |
|---|---|---|
| Computational-architectural | 1 instance (style-engine) | **2 instances (style-engine + interactivity)** |
| Governance-architectural | 5 contexts | 5 contexts (unchanged) |

> **Cross-context verification approaching threshold**.
> Bounded context character bifurcation pattern moves toward
> formalization eligibility.

**3. Bridge Pattern sub-character observation (NEW)**

4 sub-characters identifiable across 4 Bridge instances:
- Static-data Bridge (script-translations)
- Asymmetric Bridge (locale-switching)
- Round-trip Bridge (notices)
- **Reactive-subscription Bridge (directive-protocol)** — NEW

Phase 8.13+ Bridge Pattern audit may evaluate sub-character
formalization (parallel to Doctrine 5 architectural
variants).

**4. Phase 8.10 Law 1 trap discipline VALIDATED operationally**

> "Syntax ≠ Mediation" trap successfully avoided.

Honest screening of 12 directive families produced uniform
divergent verdict — refused force-fitting Doctrine 6 onto
reactive grammar. This validates Phase 8.10 spec patch's
Law 1 trap principle as operational guidance.

**5. Multi-lens retro methodology — NEW Q9 retro pattern**

Phase 8.12 retro applied 3 simultaneous lenses producing 3
distinct verdicts (ADDITIVE + STRENGTHENED + DIVERGENT).
This is the first explicit multi-lens retro in KB.

> **Multi-lens retro principle (NEW observation)**: Q9 retro
> may apply multiple constitutional lenses simultaneously
> when chunk has cross-frontier relevance. Each lens produces
> distinct verdict; combined synthesis maximizes constitutional
> information gain.

Status: **Surfaced only.** May warrant Phase 8.15+ Q9 retro
methodology spec patch if multi-lens approach recurs.

### Mediation audit criterion impact

> **Note**: Phase 8.12 retro produces NO Mediation criterion
> advance (Lens 3 DIVERGENT). Doctrine 6's KB-Wide LAW
> promotion path remains BLOCKED via this retro.

Post-this-retro Doctrine 6 status (UNCHANGED from Phase 8.10):
- 9 sub-elements (6a-6i)
- 2 architectural variants (6-HARD/6-SOFT)
- KB Constitution v1.6 doctrine architectural sophistication
- Computational-architectural classification REINFORCED
- KB-Wide LAW pathway: still blocked by architectural
  ubiquity ceiling

### Phase 8.13+ frontier shift

Pre-Phase-8.12 frontier:
- P1 (style-engine compiler/runtime test): ✅ executed (DIVERGENT)
- P3 (HARD/SOFT formalization): ✅ executed (ADDITIVE)
- P4 (Bridge Pattern + governance taxonomy): ⚠ pending

Post-Phase-8.12 frontier (now):
- P4 ✅ executed (Bridge ADDITIVE; governance-vs-computational
  STRENGTHENED; Doctrine 6 DIVERGENT)
- **NEW: Bridge Pattern audit consideration (Phase 8.13+)**
- **NEW: Bounded context character bifurcation formalization
  consideration (Phase 8.14+)**
- **NEW: Bridge Pattern sub-character formalization
  consideration (within Phase 8.13+ audit)**

> **Frontier shift**: From "Doctrine 6 variant maturation"
> (Phase 8.5-8.10) to **"Bridge Pattern + character taxonomy
> formalization"** (Phase 8.13-8.14+). Constitutional
> development continues at candidate-tier + character-taxonomy
> layers.

### KB-wide pattern recurrence updates

**Bridge Pattern**: Local → **Recurring (cross-context)**
- 4 instances × 3 bounded contexts (i18n + admin-ui +
  interactivity)
- 5th KB PROMOTION EVENT
- Phase 8.13+ audit consideration viable

**Computational-architectural character category**: 1 → 2
instances (style-engine + interactivity).

**Bounded context character bifurcation observation**: NEW
observation (Phase 8.14+ formalization candidate).

**Bridge Pattern sub-character observation**: NEW observation
(Phase 8.13+ audit may evaluate; static-data / asymmetric /
round-trip / reactive-subscription sub-characters).

**Multi-lens retro methodology observation**: NEW
methodological pattern (Phase 8.15+ may formalize).

**Doctrine 6 architectural ubiquity**: 3/5 standard categories
(unchanged; computational-architectural reinforced as Doctrine 6
absence territory).

**Interactivity bounded context Doctrine 6 character**:
DIVERGENT (1 chunk verified; computational-architectural
classification).

### Constitutional principle (retro-derived)

> **Multi-lens retros maximize constitutional information
> gain.** Single-lens retros produce single-axis verdicts;
> multi-lens retros produce orthogonal verdicts across
> multiple constitutional axes. When chunks have cross-
> frontier relevance, multi-lens approach produces
> structurally richer constitutional outcomes.

This refines Phase 8.9 retro arc lesson (P1 + P3 produced
complementary outcomes via single-lens approach each); Phase
8.12 demonstrates multi-lens approach within single retro
producing 3 outcomes simultaneously.

### Comparison: Phase 8.9 + 8.12 retro arcs

| dimension | P1 (style-engine) | P3 (supports-field) | **P4 (directive-protocol) — THIS** |
|---|---|---|---|
| Verdict | DIVERGENT | ADDITIVE + CONFIRM | **ADDITIVE + STRENGTHENED + DIVERGENT (3 lenses)** |
| Lens count | 1 (Doctrine 6) | 1 (Doctrine 6 SOFT) | **3 (Bridge + Taxonomy + Doctrine 6)** |
| Constitutional impact | breadth ceiling | depth advance | **promotion event + character taxonomy + ubiquity reinforcement** |
| Constitutional value type | boundary clarification | architecture deepening | **promotion + taxonomy + reinforcement (triple)** |
| Methodology | single-lens screening | single-lens formalization | **multi-lens analysis (NEW)** |

**3 retros × 3 distinct outcome types** + **NEW
multi-lens retro methodology**. Phase 8.9-8.12 retro arc
demonstrates KB's Q9 retro discipline at full sophistication.

### Anticipated next chunks (post-this-retro)

1. **Phase 8.13 Bridge Pattern Audit (anticipated)** — 5th KB
   PROMOTION EVENT triggered viable audit consideration.
   Pre-audit prep needs 4th bounded context Bridge instance
   confirmation (e.g., `block-authoring.registration` Q9
   retro).

2. **Phase 8.14 Bounded context character bifurcation
   formalization (anticipated)** — Governance-architectural
   vs Computational-architectural distinction approaching
   formalization threshold (5 vs 2 instances). Spec patch
   may formalize as bounded context character taxonomy
   expansion.

3. **`block-authoring.registration` family Q9 retro** —
   PRIMARY 4th-Bridge-instance candidate; would advance
   Bridge Pattern audit prerequisites.

4. **`interactivity.runtime-state` Q9 retro (or forward
   chunk)** — interactivity bounded context further
   exploration; may surface additional Bridge sub-character
   evidence + computational-architectural character density.

5. **Phase 8.15+ Multi-lens retro methodology spec patch
   (anticipated)** — if multi-lens retro pattern recurs in
   future Q9 work.

Recommended: **`block-authoring.registration` Q9 retro** —
HIGHEST PRIORITY for advancing Bridge Pattern audit
prerequisites (4th bounded context Bridge instance). After
4th Bridge instance confirmed, Phase 8.13 Bridge Pattern
audit becomes fully viable.

### Status updates

- This file's overall `status` remains `evolving` (original
  evaluation preserved).
- Retro patch adds Q9 multi-lens verdict (ADDITIVE +
  STRENGTHENED + DIVERGENT) + Bridge Pattern PROMOTION to
  Recurring (cross-context) + Computational-architectural
  character 2nd-instance + Bridge Pattern sub-character
  observation + multi-lens retro methodology observation.
- Original chunk content (lines 1-613) UNCHANGED; this retro
  is purely additive at end of file.