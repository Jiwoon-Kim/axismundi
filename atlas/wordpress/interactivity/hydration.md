---
rule_id: interactivity.hydration
domain: interactivity
topic: authority-continuity
field_cluster: runtime-boundaries
wp_min: "6.5"
wp_recommended: "6.5+"
status: evolving
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/
    section: "Interactivity API — server processing + client runtime hydration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-interactivity/
    section: "@wordpress/interactivity — runtime initialization, store discovery, directive attachment"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/news/2024/04/03/getting-started-with-the-interactivity-api/
    section: "Server processor pre-evaluation; initial state continuity"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/wp_interactivity_state/
    section: "wp_interactivity_state() — PHP API for seeding initial reactive state"
    captured: 2026-05-09
related:
  - interactivity.directive-protocol           # the grammar that hydration activates
  - interactivity.runtime-state                # the substrate hydration bootstraps
  - data-layer.entity-resolution               # entity state may seed initial reactive state
  - data-layer.persistence                     # mid-hydration entity mutation = race surface
  - block-authoring.block-json.bindings        # bindings + interactivity may both consume entity state during hydration
  - block.dynamic-rendering                    # SSR HTML production stage (precedes hydration)
  - style-engine.cascade-aggregation           # symmetric counterpart — CSS cascade is "hydrated" by browser CSS engine
---

# RULE — hydration — authority continuity across execution boundaries

## WHEN

Asking how server-rendered Gutenberg interactive HTML transitions
to a live, reactive client-side runtime — and what survives /
breaks / reconciles across that transition. Use this chunk to
understand:

- The transition from PHP-rendered HTML (with directives baked
  in + initial state serialized) to a fully reactive client
  runtime.
- Why "hydration" in Interactivity API is structurally different
  from React/Vue hydration (no VDOM construction; runtime
  attaches to existing DOM).
- How initial state crosses the server→client boundary
  (`wp_interactivity_state()` + serialized context payloads).
- Why hydration is selective per directive subtree, not global
  per page.
- The failure modes when server-known state diverges from
  client-live state.
- The KB-level question pivot at this layer: from "what is
  authority?" to "**how does authority cross execution
  boundaries?**"

This is the **Phase 7 capstone** chunk. After this, interactivity
bounded context backbone is sealed; almost every prior KB framing
(compiler/runtime split, SSR-first, delayed reconciliation,
runtime attachments, bindings, entity resolution, persistence
latency) converges here at the boundary where execution
environments meet.

## SHAPE

### 1. Boundary topology — what crosses where

```
┌─────────────────── SERVER (PHP) ────────────────────┐
│  Block render → emits HTML + data-wp-* directives   │
│  Interactivity processor pre-evaluates              │
│    (server-known state baked into HTML)             │
│  wp_interactivity_state() seeds JSON-serialized     │
│    initial state per namespace                      │
│  data-wp-context serializes initial subtree state   │
└──────────────────────┬──────────────────────────────┘
                       │ (HTML transmitted)
                       ▼
┌─────────────────── CLIENT (BROWSER) ────────────────┐
│  HTML parsed → DOM tree exists                      │
│    (page already visible, content correct)          │
│  @wordpress/interactivity runtime loads             │
│  Runtime scans for data-wp-interactive regions      │
│  For each region:                                   │
│    discovers registered store(namespace, ...)       │
│    consumes serialized initial state                │
│    parses directives in subtree                     │
│    creates subscriptions per directive              │
│    attaches event handlers per data-wp-on--*        │
│  Reactive continuity established                    │
└─────────────────────────────────────────────────────┘
```

Critical observation: **the DOM itself is not reconstructed**.
The HTML the browser parsed at first byte arrival IS the same
DOM the runtime attaches to. No virtual tree is built; no
diff/reconcile algorithm runs against server output.

### 2. Server-known state injection mechanisms

Multiple paths carry state across the boundary:

| mechanism | carries | scope |
|---|---|---|
| HTML attribute values | server-evaluated bindings (e.g., aria-expanded already correct) | per-element |
| `data-wp-context='{...}'` JSON | initial subtree-local state | DOM subtree |
| `wp_interactivity_state()` PHP | namespace-level reactive state | per-namespace |
| serialized entity payloads | server-fetched entity values | per-binding consumer |
| action references | directive references to actions/callbacks | per-directive |

The server's job during render: ensure all server-known state
is **pre-baked** so the page is correct at first paint AND the
client runtime can pick up exactly where the server left off
without re-fetching or re-computing.

### 3. Selective / progressive hydration

The runtime activates **per directive subtree**, not per page:

```
Page DOM:
   <div>                                          ← NOT hydrated
     <article data-wp-interactive="myPlugin">    ← HYDRATED region
       ...
     </article>
     <aside>                                      ← NOT hydrated (no directive)
       ...
     </aside>
     <article data-wp-interactive="otherPlugin"> ← HYDRATED region
       ...
     </article>
   </div>
```

Implications:
- No global VDOM ownership / takeover.
- Non-interactive page sections never engage the runtime
  (zero reactive overhead).
- Multiple namespaces hydrate independently.
- A heavy interactive widget in one corner of the page does
  NOT delay reactivity for an interactive widget in another
  corner.

This is **directive-topology-driven hydration** — the runtime
graph is shaped by the directives present, not by an
application root.

### 4. Hydration lifecycle (8-stage)

```
1. Server render
   PHP renders block output; processor pre-evaluates directives;
   wp_interactivity_state() seeds initial state.

2. Directive emission
   HTML serialized with data-wp-* attributes + state JSON +
   namespace declarations.

3. HTML parse
   Browser parses transmitted HTML; DOM tree constructed;
   page visible / interactive at the HTML level (links work,
   forms submit traditionally).

4. Runtime bootstrap
   @wordpress/interactivity package loads (typically deferred /
   module script); core runtime initializes.

5. Directive discovery
   Runtime scans DOM for data-wp-interactive regions; per-region
   processing begins.

6. Store / context registration consumption
   Runtime consumes registered store(namespace, ...) calls
   (must be registered BEFORE runtime processes that namespace's
   directives); initial state from wp_interactivity_state()
   merged with store's defaults.

7. Subscription wiring
   Per directive, runtime creates the appropriate reactive edge
   (binding subscription / event handler / watch dependency / etc.).

8. Reactive continuity established
   The interactive region is now live; user interactions trigger
   actions; state mutations propagate to DOM.
```

⚠ Exact ordering, parallelism, error recovery — verification-
needed; behavior may vary per WP version + page complexity.

### 5. Failure & reconciliation surfaces

Hydration is RECONCILIATION, not just activation. Failure modes:

| scenario | symptom | reconciliation path |
|---|---|---|
| **stale server state** | server rendered with old data; entity changed before client receives | client may display stale momentarily; refetch via data-layer eventually corrects |
| **mid-hydration entity mutation** | another tab / process modifies entity during runtime bootstrap | client store may be inconsistent until next entity refetch |
| **delayed runtime init** | JS load delay; user interacts with non-reactive page | events trigger HTML defaults (form submit, link nav) NOT reactive actions |
| **store registration race** | directives processed before store(...) call executes | references to actions/state resolve to undefined; warning / silent skip |
| **partial subtree hydration** | runtime hydrates one region, hits error in another | hydrated regions interactive; failed region falls back to HTML defaults |
| **action/state name mismatch** | directive references state.x; store has no state.x | reactive binding resolves to undefined; UI may show empty |
| **persistence race** | edit dispatched before hydration completes | edit may be lost if dispatched against unhydrated store |
| **context payload malformation** | data-wp-context JSON is malformed | runtime may skip the region or treat as empty context |

These are **structural surfaces**, not edge cases. Hydration
operates across an inherently unreliable boundary (network,
async, multi-process); failure paths must be designed in.

### What this is NOT

- NOT React-style hydration. React builds a virtual tree from
  server HTML, then uses it for subsequent renders.
  Interactivity does NOT construct a virtual tree.
- NOT a single atomic event. Hydration is per-region, per-stage,
  with potential failure at each stage.
- NOT mandatory for the page to work. Non-interactive HTML is
  fully functional without hydration; reactivity is additive.
- NOT a takeover. The runtime never owns the DOM exclusively;
  it cooperates with browser-native HTML behaviors (form
  submit, link nav, focus, etc.) until directives override
  specific aspects.
- NOT versioned by API contract surface. Hydration internals
  evolve; the public contract (directives + store API) is
  stable, but timing / ordering / failure semantics shift per
  WP version.

## REQUIRES

- WP 6.5+ (Interactivity API + server processor).
- Block render output containing data-wp-* directives.
- For each interactive namespace: a registered store via
  `store('namespace', {...})` in JS, loaded before runtime
  processes that namespace's directives.
- For server-state seeding: `wp_interactivity_state()` PHP
  calls during render OR data-wp-context with JSON-serialized
  initial values.
- Browser support for ES modules + the Interactivity runtime's
  baseline (modern browsers; older browser support
  verification-needed).
- ⚠ Specific load ordering between Interactivity runtime and
  store-registering JS — verification-needed.

## INVARIANTS

### 1. Hydration is authority continuity, NOT DOM reconstruction

The load-bearing backbone invariant:

> Hydration in Interactivity API is **a continuation of authority**
> — server-rendered DOM remains the reality; client-side runtime
> attaches reactive subscriptions onto it. The DOM is NOT
> reconstructed, replaced, or virtualized.

This contrasts sharply with React/Vue hydration:
- React: server HTML is "input"; runtime constructs VDOM,
  reconciles to DOM, then OWNS the DOM for subsequent renders.
- Interactivity: server HTML is "reality"; runtime traverses
  it, attaches subscriptions, COOPERATES with the DOM
  thereafter.

Reading hydration as "client takes over rendering" misses
entirely. Hydration is the moment when SUBSCRIPTIONS attach,
not when ownership transfers.

### 2. Server-rendered HTML is the FIRST reactive frame, NOT a fallback

The HTML the server emits is **already in the correct reactive
state**:
- Bindings with server-known values are pre-evaluated
  (aria-expanded matches state.isOpen for the initial state).
- Context attribute carries initial reactive context.
- Element classes/styles reflect initial state.

When the client runtime hydrates, it does NOT recompute initial
state. It picks up from where the server left off.

This is structurally different from "SSR fallback" patterns
(SSR for SEO; client re-renders from scratch on hydration).
In Interactivity API, server render IS the first reactive
frame; client hydration is the SECOND and subsequent frames'
preparation.

### 3. DOM remains stable while authority attachments become reactive

A core operational invariant:

> Before hydration: DOM exists with correct visual state; events
> trigger HTML defaults (form submit, link nav).
> After hydration: SAME DOM exists; events ALSO trigger directive
> actions; bindings make selected attributes reactive.
> The DOM topology, content, and structural attributes
> are UNCHANGED.

Reactivity is ADDITIVE to the DOM, not REPLACEMENT of it.

This explains why progressive enhancement works structurally:
non-JS clients see the HTML and can interact with HTML defaults;
JS-enabled clients ALSO get reactive enhancement. The two are
not branches of separate codepaths — they are the same DOM
viewed at different stages of hydration.

### 4. Hydration attaches runtime semantics onto existing topology

The "attach not own" pattern from directive-protocol +
runtime-state finds its operational form here:

> Hydration WALKS the DOM and ATTACHES reactive semantics per
> directive. The walk is read-only with respect to DOM
> structure; it adds subscriptions, event handlers, and store
> bindings without modifying the tree.

Subsequent state mutations may modify specific bound
attributes / text / classes, but the DOM tree shape is owned
by HTML / server render / explicit author code, NOT by the
hydration process or the runtime.

This is the third instance of "attach not own" in KB:
- bindings: attribute hosts attachment to source (block-authoring layer)
- directives: DOM node hosts attachment to reactive graph (runtime layer)
- **hydration**: **runtime attaches semantics onto existing DOM (boundary layer)**

The pattern is structurally consistent across Phase 7.

### 5. Hydration granularity follows directive topology, NOT application roots

Hydration boundaries are defined by `data-wp-interactive`
regions:

```
Page (no global app root)
   ├─ Region A (hydrated independently)
   ├─ Non-interactive content (never engages runtime)
   ├─ Region B (hydrated independently)
   └─ Region C (hydrated independently)
```

This is structurally different from SPA frameworks where
hydration starts at a single application root and recursively
covers the entire tree. Interactivity API has **no
application root concept**; the page is a collection of
independent interactive regions interspersed with
non-interactive content.

Implications:
- Hydration cost is proportional to interactive surface, not
  page size.
- Failure in one region does not cascade to others.
- Multiple plugins / themes can register their own namespaces
  without coordination.
- Pages can be 95% non-interactive content + 5% interactive
  widgets without paying SPA overhead.

This is one of Interactivity API's structural differentiators.

### 6. Directive processing creates runtime authority edges (closure of directive-protocol)

directive-protocol introduced directives as edge declarations.
Hydration is when those declarations BECOME edges in the
runtime authority graph:

| stage | edge state |
|---|---|
| serialization (server) | declarations as HTML attributes |
| parsing (client) | declarations as DOM properties |
| hydration discovery (client) | declarations identified |
| **hydration attachment (client)** | **EDGES IN RUNTIME GRAPH** |
| post-hydration | edges propagate per state mutations |

Hydration is the **edge creation event**. Before hydration,
directives are inert markup; after hydration, they are live
graph members.

### 7. Hydration reconciles server-known and client-live authority

Both server and client may have authority over the same state:
- Server: knows entity state at render time, knows user
  identity, knows server-side computed values.
- Client: knows interaction history (hover, focus, scroll),
  knows local edits, knows runtime mutations since hydration.

Hydration must RECONCILE these. The default reconciliation is:
- Server-known state seeds the client store.
- Client-live state takes over as user interacts.

⚠ Conflict scenarios (entity changed during transit, server
state stale by hydration time, client side-effects clashing
with server-evaluated bindings) — these are reconciliation
challenges similar to data-layer's conflict surfaces.

### 8. Initial state injection preserves authority continuity across environments

The mechanisms in SHAPE section 2 (`wp_interactivity_state()`,
`data-wp-context`, pre-evaluated bindings, serialized entities)
exist to **preserve authority continuity across the
server→client environment shift**:

```
authority before transition (server side):
   PHP context, current_user_can(), entity state, computed values
   ↓ (serialization layer crossing)
authority after transition (client side):
   JS context, runtime store, hydrated entities, computed via getters
```

Without these injection mechanisms, the client would START
authority FROM ZERO (initial defaults only) and would have to
re-fetch / re-compute everything. With them, authority
CONTINUES smoothly across the boundary.

This is why Interactivity API's hydration is "continuity"
rather than "bootstrap from scratch."

### 9. Selective hydration preserves WordPress server-first philosophy

The decision to hydrate per-directive-subtree (rather than
per-page) is structurally aligned with WordPress's
server-first philosophy:

WordPress historical principles:
- HTML is the primary deliverable.
- Server renders the canonical document.
- JS enhances; it does not require.
- Progressive enhancement is a value, not a fallback.

Interactivity API's hydration model is the structural
embodiment:
- HTML is the primary deliverable (rendered server-side).
- Reactive runtime enhances (per-region, opt-in via
  directives).
- Pages work without JS for non-reactive paths.
- Runtime additions are progressive (no mandatory SPA
  takeover).

This makes Interactivity API a **structurally faithful
modern reactive runtime for the WordPress philosophy** —
distinct from frameworks that require app-root takeover.

### 10. Hydration completes Gutenberg's transition from compiler to distributed runtime orchestration system — Phase 7 capstone

The Phase 7 capstone framing converges here:

```
KB ontology evolution through Phases:

Phase 1-3 (block-authoring):
   declaration ontology
Phase 4 (theme-config):
   configuration / authority substrate ontology
Phase 5-6 (style-engine):
   compiler ontology (visual realization)
Phase 7a (bindings):
   runtime authority attachment
Phase 7b (data-layer):
   runtime authority substrate (entity / persistence)
Phase 7c (interactivity directive + runtime-state):
   reactive grammar + ephemeral coordination substrate
Phase 7d (THIS CHUNK — hydration):
   AUTHORITY CONTINUITY across execution boundaries
```

The KB-level question pivots one more time:

| KB phase | dominant question |
|---|---|
| Phases 1-6 | **What authority exists?** |
| Phase 7 substrate (bindings, data-layer, runtime-state) | **What authorities are attached to what?** |
| **Phase 7 capstone (hydration)** | **How does authority cross execution boundaries?** |

After this chunk, KB has structurally completed Gutenberg's
transformation from "block schema + visual compiler" to
**"distributed authority orchestration system"** spanning:
- multiple authority kinds (entity / block / directive / store
  / derived)
- multiple execution environments (PHP server / network / JS
  runtime / browser CSS / browser cascade)
- multiple lifetimes (persistent / draft / ephemeral)
- multiple coordination protocols (cascade arbitration /
  reactive subscription / persistence reconciliation /
  hydration continuity)

## VERIFICATION NEEDED

`status: evolving`. Hydration has high implementation density;
verification items dense:

- Exact runtime initialization order — when does `store(...)`
  registration take effect relative to directive scanning?
- Lazy hydration semantics — is there support for hydrating
  regions only when they enter viewport / become visible?
- Directive scan timing — is scanning eager (entire DOM at
  once) or lazy (per region as discovered)?
- Async entity fetch DURING hydration — does the runtime wait
  for resolution before activating directives?
- SSR/client mismatch handling — what if HTML attribute differs
  from client-state-computed value (race during transit)?
- DOM mutation assumptions — what happens if the page mutates
  the DOM (e.g., via vanilla JS) during hydration?
- Interaction with View Transitions / navigation API.
- Behavior when @wordpress/interactivity loads before vs after
  the DOMContentLoaded event.
- Multiple version coexistence — older Interactivity API
  versions with newer or vice versa.
- Plugin extension points — can plugins intercept hydration?
- Editor integration — do interactive blocks hydrate during
  editor preview, and if so when?
- Performance characteristics — large pages with many
  interactive regions.
- Memory characteristics — does hydration clean up after
  detached DOM nodes?
- Partial hydration — can portions of a region be excluded
  from hydration explicitly?

For practical decisions: empirical observation
(`@wordpress/interactivity` source + browser DevTools) is
authoritative; documented behavior captures the contract
surface but not all implementation nuances.

## ANTIPATTERNS

- ❌ Treating hydration as a single atomic event. It is per-region,
  multi-stage, with failure paths at each stage.
- ❌ Assuming hydration is instant after page load. Runtime
  loads after HTML parse + may be deferred; user can interact
  with HTML defaults before reactivity activates.
- ❌ Building UX that REQUIRES reactivity to function. Pages
  must work at the HTML level for the pre-hydration window
  AND for JS-disabled clients.
- ❌ Manipulating DOM via vanilla JS in regions that have
  directives. Runtime owns the directive-bound aspects;
  vanilla mutations conflict.
- ❌ Calling store actions before hydration completes for that
  region. The store may not be initialized; calls may silently
  fail or queue unpredictably.
- ❌ Placing store registration JS LATER in load order than
  the Interactivity runtime expects. Registration must happen
  before that namespace's directives are processed.
- ❌ Treating `wp_interactivity_state()` as the only state
  injection mechanism. data-wp-context, pre-evaluated
  bindings, and entity payloads also carry state across the
  boundary.
- ❌ Assuming server-rendered HTML and post-hydration DOM are
  ALWAYS visually identical. State changes (entity updated
  between transit and hydration) can produce momentary
  divergence.
- ❌ Using hydration for one-time initialization side effects.
  Use `data-wp-init` directive (which runs once on hydration);
  arbitrary side effects in store registration violate
  expected semantics.
- ❌ Expecting hydration to "fix" a broken server render.
  Hydration ATTACHES; if the HTML is wrong, hydration won't
  correct it.
- ❌ Assuming all non-interactive content is excluded from
  hydration cost. Runtime still scans the DOM looking for
  interactive regions; very large pages may have non-trivial
  scan cost.
- ❌ Reading hydration as React hydration semantically. They
  share the term but their behavior is structurally different
  (no VDOM construction; no ownership transfer).

## RELATED

- `interactivity.directive-protocol` — the grammar that
  hydration activates. Directives are inert markup until
  hydration creates edges from them.
- `interactivity.runtime-state` — the substrate hydration
  bootstraps. Stores must be registered; initial state seeded;
  subscriptions wired.
- `data-layer.entity-resolution` — entity state may seed
  initial reactive state via wp_interactivity_state() PHP +
  serialized payloads. Entities consumed during hydration
  cross the same server→client boundary.
- `data-layer.persistence` — mid-hydration entity mutation is
  a race surface. The reconciliation lifecycle from
  data-layer.persistence applies when persisted state shifts
  during hydration window.
- `block-authoring.block-json.bindings` — bindings + directive
  + interactivity store can all consume entity state during
  hydration; they must coordinate to avoid duplicate fetches /
  conflicting reads.
- `block.dynamic-rendering` — the SSR HTML production stage
  that precedes hydration. Dynamic blocks emit
  directive-annotated markup that hydration consumes.
- `style-engine.cascade-aggregation` — symmetric counterpart.
  CSS cascade is "hydrated" by the browser CSS engine
  (cascade resolution, computed style derivation); reactive
  hydration is the JS equivalent at a different runtime layer.
- (planned) `interactivity.navigation` — when client-side
  navigation is added (cross-page state continuity), additional
  hydration concerns surface.
- (planned) `interactivity.server-processing` — deeper dive
  into the PHP processor's pre-evaluation rules.

## META

**Phase 7 capstone — interactivity bounded context backbone
sealed.**

```
interactivity bounded context (after this chunk):
   directive-protocol    → reactive authority grammar           ✓
   runtime-state         → reactive authority substrate         ✓
   hydration             → authority continuity across          ✓
                           execution boundaries
   ↓
   BACKBONE STRUCTURALLY COMPLETE.

   Future chunks (navigation / server-processing / per-directive
   deep-dives) extend the structure; do not change framing.
```

**KB-level question pivots completed (3 documented across KB):**

| KB era | dominant question |
|---|---|
| Phases 1-6 | What authority exists? |
| Phase 7 substrate | What authorities are attached to what? |
| **Phase 7 capstone (hydration)** | **How does authority cross execution boundaries?** |

After hydration, the authority cartography KB has been
completed: ALL of Gutenberg's authority architecture is
documented at every layer — declaration / configuration /
realization / reconciliation / coordination / continuity.

**Hydration as compiler-runtime linkage:**

Drawing the symmetry with style-engine explicitly:

| layer | compiler | linker / loader | runtime VM |
|---|---|---|---|
| **CSS** | style-engine (variables, selectors, materialization, aggregation) | (browser ingestion of stylesheets) | browser CSS engine |
| **Reactive JS** | server-side block render + Interactivity processor | **hydration** | @wordpress/interactivity client runtime |

Hydration is the **JS-side linker / runtime loader** that
parallels the implicit linker/loader role of the browser's
CSS ingestion. Both wire compiled output into a runtime VM;
both create live execution graphs from declarative emission.

This symmetry seals the "Gutenberg = dual-runtime declarative
system" framing established in directive-protocol — the
runtimes are not just declaratively similar, they are
structurally similar (both have compilers + linkers + VMs).

**KB-level framing — full Phase 7 capstone statement:**

> Gutenberg evolved from
> **schema-driven visual compilation**
> through
> **runtime authority orchestration**
> into
> **a distributed authority continuity system**
> spanning multiple execution environments (PHP server / network /
> JS runtime / browser CSS / browser cascade) with explicit
> reconciliation protocols at every boundary.
>
> The page a user sees is the convergent output of:
> - schema-declared structure
> - configured tokens
> - compiled visual graph
> - resolved persistent entities
> - hydrated reactive subscriptions
> - browser-executed cascade
>
> Each layer has its own authority kind, lifetime, and
> reconciliation semantics. Together they form Gutenberg's
> **operational ontology**.

**KB structural completion observation:**

After this chunk, KB's structural backbone is complete for the
documented Gutenberg architecture (Phases 1-7). Remaining
work is:

1. **Specialization / depth** — per-area deeper chunks where
   needed (e.g., interactivity.server-processing,
   interactivity.navigation, data-layer.entity-types,
   per-directive-family chunks).

2. **Retro patches** — bringing earlier chunks (especially
   block.dynamic-rendering) up to current ontology framing.

3. **Other bounded contexts** — editor-customization /
   site-building / plugin-dev / i18n / build-tooling /
   admin-ui. These are ADDITIVE; they extend coverage but do
   not change the Phase 7 framing.

4. **Cross-chunk coherence audit** — KB has reached a
   density where cross-references and framing consistency
   may benefit from explicit review.

The KB is now structurally an **operational ontology atlas of
Gutenberg's authority architecture**, not a documentation
summary. Going forward, the question for new chunks shifts
from "what does this field do?" to "how does this fit the
existing authority cartography?"

**DSL extension applied:** VERIFICATION NEEDED + META, per
runtime/implementation-derived applicability. Hydration is
particularly dense in verification-needed because boundary-
crossing semantics are inherently implementation-derived.

**Status `evolving`** — hydration internals evolve significantly
per WP version (server processor improvements, runtime
optimizations, new directive handling). The structural framing
documented here is stable; specific behaviors should be
verified per WP version when relied upon.

**Anticipated next chunks (priority assessment):**

1. **`block.dynamic-rendering` retro patch** — pre-dates entire
   Phase 7. Retroactive section reframing dynamic-rendering
   through current ontology (entity reads / directive emission /
   Interactivity processor coordination). Light work, high
   coherence payoff.

2. **`block.markup-representation` retro patch** — also pre-dates
   bindings + Phase 7. May benefit from retro section on
   serialized markup as authority host (bindings metadata +
   directive attributes inhabiting same comment-delimiter +
   element-attribute IR).

3. **Other bounded contexts entry** — editor-customization /
   site-building / plugin-dev / i18n / build-tooling /
   admin-ui. Additive; choose based on user direction.

4. **Specialization within Phase 7** — interactivity.navigation
   (cross-page reactive state continuity),
   interactivity.server-processing (PHP processor deeper dive),
   data-layer.entity-types (per-type lifecycle).

Recommendation after Phase 7 capstone: pause for cross-chunk
coherence audit OR proceed with retro patches to maximize
coherence payoff before additive bounded contexts.

---

## Q9 RETROACTIVE PATCH — Phase 8.16b v2-Deployment Validation: Lifecycle Completion (2026-05-10)

> **Retroactive verification triggered by**:
> Phase 8.15 KB Constitution v2 Epoch Snapshot operational
> guidance + Phase 8.16b strategic posture continuation:
> "v2 must prove it can govern reconstitution." This is the
> **3rd v2-deployment Q9 retro** in the interactivity
> bounded context Q9 retro arc (directive-protocol Phase
> 8.12 + runtime-state Phase 8.16 + **hydration Phase
> 8.16b**).
>
> **Strategic role**: Constitutional deployment validation
> at LIFECYCLE COMPLETION stage. Per user's framing:
> - Directive protocol = **initiation**
> - Runtime-state = **maintenance**
> - **Hydration = reconstitution**
>
> If hydration aligns with v2 vocabulary, interactivity
> bounded context becomes **first fully v2-governed bounded
> context** (Phase 8.16c closure adjudication enabled).
>
> **Methodological framing**: Apply v2 vocabulary (Law 3b
> reactive-subscription Bridge sub-character + Computational-
> architectural character + Doctrine 5 + Federation +
> Doctrine 6 falsification) to Phase 7-native chunk
> authored 2026-05-09 (pre-v2).
>
> **Critical discipline (per user explicit warning)**:
> **Do NOT seek Law 3c.** Hydration should first test
> whether it is **merely 3b-react lifecycle completion**.
> Most likely: yes. And that would be **excellent** — would
> deepen Law 3b lifecycle breadth without inflating it.
>
> **Q9 retro discipline**: Per Phase 8.16 deployment posture,
> classify first, speculate later. Honest evaluation refuses
> premature constitutional invention.

### Retro context

This chunk was authored 2026-05-09 (Phase 7-native; Phase 7
capstone) as interactivity bounded context backbone closure.
Original analysis explicitly frames hydration as **"authority
continuity across execution boundaries"** (chunk frontmatter
topic) and **"completion of compiler→runtime orchestration
system"** (Invariant 10).

This pre-v2 framing is PROPHETIC — it anticipates the Law 3
authority continuity formalization that Phase 8.13/8.14 audit
+ patch produced. The chunk's explicit "authority continuity"
language predicts Law 3b reactive-subscription Bridge
sub-character lifecycle completion role.

The retro question (per Phase 8.16b primary lens):

> **Primary**: Does hydration COMPLETE Law 3b-react, or
> REVEAL adjacent Law 3 specialization?
>
> **Most likely answer (per user prediction)**: Hydration
> completes 3b-react lifecycle (initiation → maintenance →
> reconstitution).

### LENS A — Law 3b 3b-react lifecycle completion classification

#### Hydration as lifecycle reconstitution stage

Per user's "directive protocol = initiation / runtime-state =
maintenance / hydration = reconstitution" framing:

| 3b-react lifecycle stage | bounded context chunk | character |
|---|---|---|
| **Initiation** | directive-protocol (Phase 7-native + Phase 8.12 retro) | server-side directive emission + initial state baking + HTML transport |
| **Maintenance** | runtime-state (Phase 7-native + Phase 8.16 retro) | continuous reactive subscription substrate; ongoing state mutations propagate |
| **Reconstitution** | **hydration (Phase 7-native + THIS retro)** | **client runtime activates server-baked subscriptions onto existing DOM; authority transition from server-known to client-live** |

> **Lifecycle structural finding**: Hydration is **the
> reconstitution stage** of Law 3b 3b-react reactive-
> subscription Bridge sub-character lifecycle.

This is structurally the moment when:
- Server-baked authority becomes client runtime authority
- Inert directive markup becomes live reactive edges
- Initial state JSON becomes Proxy-wrapped reactive substrate
- Subscriptions wire up between DOM and store

All of these ARE 3b-react Bridge SUB-CHARACTER MECHANISMS at
their LIFECYCLE COMPLETION stage. Original chunk's Invariants
1, 4, 6, 7 explicitly describe this:
- Invariant 1: "Hydration is authority continuity, NOT DOM
  reconstruction"
- Invariant 4: "Hydration attaches runtime semantics onto
  existing topology"
- Invariant 6: "Directive processing creates runtime
  authority edges (closure of directive-protocol)"
- Invariant 7: "Hydration reconciles server-known and
  client-live authority"

#### Latent Law 3b explicit evidence in original chunk

Re-reading the original chunk through Law 3b lens reveals
**direct lifecycle completion mechanisms**:

| original chunk element | Law 3b 3b-react lifecycle reading |
|---|---|
| Frontmatter `topic: authority-continuity` | Direct Law 3 (Authority Continuity) classification |
| 8-stage hydration lifecycle | Lifecycle completion stages of 3b-react Bridge sub-character |
| "Server render IS the first reactive frame" (Invariant 2) | Bridge initiation continuity from server side |
| "DOM remains stable while authority attachments become reactive" (Invariant 3) | DOM-stable authority continuity (3b-react character preservation) |
| "Selective hydration follows directive topology" (Invariant 5) | Per-region 3b-react lifecycle activation; bounded by directive scope |
| "Initial state injection preserves authority continuity across environments" (Invariant 8) | Direct Law 3 + Law 3b 3b-react manifestation language |
| "Hydration reconciles server-known and client-live authority" (Invariant 7) | Reconstitution = reconciliation across runtime boundary |
| "Compiler / Linker / Runtime VM" symmetry (META) | Law 6 + Law 3b: hydration is JS-side linker stage of 3b-react Bridge |

> **Latent finding**: Hydration's "authority continuity"
> framing IS Law 3b 3b-react reactive-subscription Bridge
> SUB-CHARACTER LIFECYCLE COMPLETION. The Phase 7-native
> chunk used "authority continuity" language that Phase
> 8.13/8.14 formalized as Law 3 + Law 3b vocabulary.

### LENS B — Law 3 specialization stress test (DO NOT seek Law 3c)

Per user's explicit warning + Phase 8.10 Law 1 trap principle
+ Phase 8.14 Law sub-pattern conservation principle:

#### Could hydration warrant NEW Law 3 sub-pattern?

Honest evaluation (refusing premature inflation):

| Law 3c candidate hypothesis | screening |
|---|---|
| "Reconstitution Bridge" as NEW Law 3 sub-pattern? | NO — reconstitution is LIFECYCLE STAGE of existing 3b-react, not structurally distinct sub-pattern |
| "Boundary-Crossing-Continuity" as Law 3 sub-pattern? | NO — Law 3b already captures cross-runtime boundary; "reconstitution" is the lifecycle event WITHIN 3b-react |
| "Reconciliation" as Law 3 sub-pattern? | NO — reconciliation is OPERATIONAL CHARACTER of 3b-react reconstitution stage; not constitutional structure |
| "Authority transition" as new sub-pattern? | NO — transition IS continuity; restating doesn't add structure |

Per Phase 8.14 Law Sub-pattern Gate criteria:
- L1 100% parent-law dependence: would be MET (always Law 3)
- L2 Strong cross-context breadth: NOT MET (single bounded
  context manifestation)
- L3 Structural specificity beyond parent law's general
  invariant: WEAK (specificity is lifecycle stage, not
  direction/medium/boundary type)
- L4 Sub-character coherence: would be MET if existed

**Law Sub-pattern Gate: 1/4 fully MET; 1/4 weak; 2/4 not met.**

→ Honest verdict: **Hydration does NOT warrant new Law 3
sub-pattern**. Insufficient structural distinction from
Law 3b 3b-react.

> **NO Law 3c proposed.** Phase 8.16b discipline maintained.
> User's prediction validated: hydration is merely 3b-react
> lifecycle completion.

#### What hydration DOES contribute to Law 3b architecture

Per user's "Law 3b sub-character maturity" framing
(observation only):

> **Observation only (NOT formalization)**: Law 3b 3b-react
> sub-character may have **lifecycle breadth** spanning:
> - **Initiation** (directive-protocol)
> - **Maintenance** (runtime-state)
> - **Reconstitution** (hydration)

This is **lifecycle breadth observation**, not formalization.
Future patches may consider whether to formalize 3b-react
lifecycle stages as documented sub-character maturity. For
now: surfaced observation per Phase 8.16 conservative
discipline.

### LENS C — Computational-architectural character classification

Original chunk's META framing parallels style-engine +
prior interactivity chunks (computational-architectural):

> "Hydration is the **JS-side linker / runtime loader** that
> parallels the implicit linker/loader role of the browser's
> CSS ingestion. Both wire compiled output into a runtime
> VM; both create live execution graphs from declarative
> emission."

| character dimension | hydration evidence |
|---|---|
| Declarative authoring | block render output (declarative directive emission) |
| Compiled into runtime graph | hydration creates runtime authority edges from directive declarations |
| Browser-executed | @wordpress/interactivity package + JS runtime |
| Result in DOM behavior | reactive subscriptions wired into existing DOM |
| Server-first | HTML rendered server-side BEFORE hydration; functional without JS |
| Has escape hatches | actions/callbacks with arbitrary JS |
| **Linker stage character** | **Hydration explicitly identified as JS-side linker** (parallel to style-engine compiler/linker/runtime triad) |

> **Classification finding**: Hydration confirms Computational-
> architectural character for interactivity bounded context.
> **3rd direct chunk evidence** after directive-protocol
> Phase 8.12 + runtime-state Phase 8.16.

> **interactivity bounded context Computational-architectural
> classification: TRIPLY CONFIRMED via 3 intra-context
> chunks**. Cross-context verification status (vs style-
> engine):

| character category | bounded context | chunk count |
|---|---|---|
| Computational-architectural | style-engine | multiple (preset-materialization Phase 8.9 P1 confirmed) |
| Computational-architectural | **interactivity** | **3 (directive-protocol + runtime-state + hydration)** |

This is **2nd computational-architectural bounded context
with intra-context density** (style-engine being the 1st).
Cross-context verification approaches threshold for
character taxonomy formalization (Phase 8.17+ candidate per
Phase 8.15 frontier map P3).

### LENS D — Doctrine 6 (Authority Access Mediation) falsification

Per Phase 8.10 Law 1 trap principle + Phase 8.16 explicit
warning:

| Doctrine 6 candidate screening | result |
|---|---|
| Does hydration gate authority access? | NO — hydration ESTABLISHES authority continuity, not gates access |
| Does store discovery during hydration gate access? | NO — discovery is reactive substrate establishment, not access mediation |
| Does selective hydration topology gate access? | WEAK — topology determines WHICH regions hydrate, but this is BOUNDARY definition not access gating |
| Does directive subscription wiring gate access? | NO — wiring is reactive edge establishment, not gating choreography |

> **Classification finding**: Doctrine 6 is **NOT PRESENT**
> in hydration. Consistent with Computational-architectural
> classification (Doctrine 6 manifests in governance-
> architectural bounded contexts only).

> **No Doctrine 6 force-fitting.** Constitutional restraint
> demonstrated for 3rd consecutive interactivity chunk.

### LENS E — Doctrine 5 + Federation classifications

#### Doctrine 5 Resolution manifestation

Hydration's reconciliation between server-known and client-
live authority IS Doctrine 5 Resolution character at boundary
crossing:

| reconciliation aspect | Doctrine 5 Resolution mapping |
|---|---|
| Server-known state vs client-live state arbitration | Resolution between candidate authority sources |
| Initial state seeding | Resolution at boundary establishment |
| Mid-hydration entity mutation handling | Resolution under contention |

> **Classification finding**: Hydration's reconciliation IS
> Doctrine 5 Resolution manifestation at boundary crossing.
> Resolution Surface (Recurring cross-context, KB-Wide
> REFUSED Phase 7.8) further confirmed in interactivity.

#### Federation Pattern manifestation

Hydration explicitly preserves namespace federation per
Invariant 5 ("Selective hydration"):

> "A heavy interactive widget in one corner of the page does
> NOT delay reactivity for an interactive widget in another
> corner."

> **Classification finding**: Hydration confirms Federation
> Pattern manifestation in interactivity bounded context
> through namespace-isolated hydration topology. Federation
> continues 9-context KB-Wide-equivalent recurrence (no new
> context).

### Combined multi-lens verdict synthesis

| lens | verdict | constitutional impact |
|---|---|---|
| **A — Law 3b 3b-react lifecycle completion** | **CONFIRM** — hydration IS reconstitution stage of 3b-react lifecycle | Law 3b lifecycle breadth observation (NOT formalization) |
| **B — Law 3c sub-pattern stress test** | **DIVERGENT** — no Law 3c warranted; insufficient structural distinction from 3b-react | Constitutional restraint demonstrated; user's prediction validated |
| **C — Computational-architectural** | **CONFIRM** — 3rd intra-context evidence | Cross-context verification approaching threshold |
| **D — Doctrine 6 falsification** | **DIVERGENT** — Doctrine 6 absent | Computational-architectural classification reinforced (3rd consecutive) |
| **E — Doctrine 5 + Federation** | **CONFIRM** — both present | No new candidates surfaced |

> **Combined Phase 8.16b verdict: CONFIRM (Outcome A — most
> desirable per Phase 8.16b strategic framing).**
>
> Hydration cleanly classified under existing v2 vocabulary.
> Hydration completes Law 3b 3b-react lifecycle without
> warranting new Law 3 sub-pattern. **Constitutional
> restraint demonstrated for 2nd consecutive Phase 8.16
> retro.**

This is the **most desirable Phase 8.16b outcome** per user's
framing:
> "Hydration confirms: Law 3b has lifecycle breadth:
> Initiation / Runtime continuity / Reconstitution. That
> would deepen Law 3b without inflating it."

### Constitutional restraint demonstration (3rd consecutive)

3 interactivity chunks × 3 v2-deployment retros × NO new
candidates surfaced:

| chunk | retro phase | new candidates surfaced? |
|---|---|---|
| directive-protocol (multi-lens) | 8.12 | YES (Bridge sub-character + governance-architectural taxonomy + multi-lens methodology — pre-v2) |
| runtime-state (deployment-validation) | 8.16 | NO (clean classification under existing v2) |
| **hydration (deployment-validation, lifecycle completion)** | **8.16b** | **NO (clean classification under existing v2; Law 3c explicitly refused)** |

> **2 consecutive deployment-validation retros × 0 new
> candidates surfaced.** v2 vocabulary cleanly governs
> ordinary cases without expansion pressure. **Constitutional
> usability VALIDATED across 3 chunks in single bounded
> context.**

### Q10 sub-pattern emergence (retro)

> **Q10 RETRO ANSWER: NO new sub-pattern observed.**

Initial hypothesis screening:
- Could 8-stage hydration lifecycle be NEW formalized
  sub-pattern? Honest evaluation: **observation only**;
  lifecycle stages are operational character, not constitutional
  pattern.
- Could "compiler/linker/runtime VM" triad be NEW sub-pattern?
  Honest evaluation: **already captured by Law 6 + Computational-
  architectural classification**.
- Could reconciliation surfaces be NEW Law 3 sub-pattern?
  Honest evaluation: **Law 3b 3b-react already covers this
  reconstitution character**.

Per Phase 8.16 "classify first, speculate later" discipline:
**No premature inflation.** Lifecycle observation noted; no
formalization proposed.

### Phase 8.16 + 8.16b outcome assessment

| outcome | Phase 8.16 (runtime-state) | Phase 8.16b (hydration) — THIS |
|---|---|---|
| **A (Most desirable)**: strong validation | ✅ | ✅ |
| B: insufficiency reveal | ❌ | ❌ |
| C: existing classifications cover (restraint test) | ✅ partial | ✅ partial |

**Phase 8.16 + 8.16b v2-deployment validation: SUCCESS
(Outcome A across both retros).**

### Phase 8.16c readiness — Interactivity bounded context closure adjudication

Per Phase 8.16 strategic sequence, Phase 8.16c (interactivity
bounded context closure adjudication) becomes viable AFTER
Phase 8.16b.

**Pre-Phase-8.16c interactivity bounded context state**:
- 3 chunks (directive-protocol + runtime-state + hydration)
- 3 v2-deployment validation retros executed (Phase 8.12 +
  8.16 + 8.16b)
- All 3 chunks classified under v2 vocabulary cleanly
- Constitutional usability validated across full bounded
  context

**Phase 8.16c readiness verdict**: **READY**.

Interactivity bounded context closure adjudication may now
proceed as **first full bounded context closed under
Constitution v2 framework** — historically distinct from
pre-v2 closures (plugin-dev / admin-ui / i18n).

### NEW KB-level findings (CONSERVATIVE — Phase 8.16 discipline)

**Single KB-level finding** (per Phase 8.16 discipline):

**1. Law 3b 3b-react lifecycle breadth observation (NOT formalization)**

Law 3b 3b-react reactive-subscription Bridge sub-character
manifests across **3 lifecycle stages** within interactivity
bounded context:
- **Initiation** (directive-protocol)
- **Maintenance** (runtime-state)
- **Reconstitution** (hydration)

This is **lifecycle breadth observation** — Law 3b sub-
character has structural completeness across server-to-client
lifecycle stages.

> **Status: SURFACED ONLY.** Per Phase 8.16 conservative
> discipline + Phase 8.14 Law sub-pattern conservation
> principle: lifecycle breadth observation does NOT warrant
> new sub-pattern formalization. Law 3b 3b-react adequately
> captures this character.

Future consideration (deferred): Phase 8.20+ may evaluate
whether 3b-react sub-character lifecycle stages warrant
explicit lifecycle annotation within Law 3b architecture.
Per user's "Law 3b sub-character maturity" framing —
observation only.

### Macro distinction observation (per user "constitutional civilization archetypes" framing)

Per user's Phase 8.16b strategic guidance:
> "Admin-ui = Doctrine 6 civilization
> Plugin-dev = Doctrine 6 security civilization
> **Interactivity = Law 3b computational civilization**
> If this holds, you may be approaching: **Constitutional
> civilization archetypes**."

Phase 8.16b post-retro evidence:
- Interactivity bounded context: 3 chunks × Law 3b 3b-react
  lifecycle completion + Computational-architectural × 3
  consecutive deployment-validation retros confirming
- vs Admin-ui (Doctrine 6 governance density)
- vs Plugin-dev (Doctrine 6 security trio)

> **Observation only (NOT formalization)**: Bounded contexts
> may exhibit constitutional civilization archetypes —
> distinct constitutional character profiles per bounded
> context. Interactivity = Law 3b 3b-react computational
> civilization.

Per user's explicit warning ("Not formalize yet — but watch
carefully") + Phase 8.16 discipline: SURFACED ONLY. Future
Phase 8.18+ may evaluate constitutional civilization
archetypes formalization candidate.

### Mediation audit criterion impact

> **Note**: Phase 8.16b retro produces NO Mediation criterion
> advance (Doctrine 6 explicitly absent in this chunk).

### KB-wide pattern recurrence updates (CONSERVATIVE)

**Law 3b 3b-react sub-character intra-context density**:
2 chunks (directive-protocol + runtime-state) → **3 chunks
(+ hydration this retro)**. Interactivity bounded context
now has **3 3b-react manifestations across full lifecycle**.

**Computational-architectural character intra-context
density**: 2 chunks → **3 chunks**. Interactivity bounded
context Computational-architectural classification triply
confirmed.

**Interactivity Law 3b lifecycle observation**: NEW (3-stage
lifecycle breadth observed; Phase 8.20+ formalization
candidate).

**Constitutional civilization archetypes observation**: NEW
(per user framing; surfaced only; Phase 8.18+ formalization
candidate).

**Bridge Pattern instances**: 5 (unchanged; hydration extends
3b-react sub-character intra-context density via lifecycle
completion, not new Bridge instance).

### Constitutional principle (retro-derived; per user framing)

> **A constitution survives ordinary life when existing
> classifications explain sophisticated reality better than
> novelty.**

Phase 8.16 + 8.16b cumulatively validated this principle:
- Runtime-state (Phase 8.16): existing v2 vocabulary cleanly
  classified maintenance stage
- Hydration (Phase 8.16b): existing v2 vocabulary cleanly
  classified reconstitution stage
- Both retros: NO new candidates required to govern
  sophisticated material

> **v2 has now proven it can govern continuity, maintenance,
> AND reconstitution.** Per user's one-line strategic
> backbone: this completes constitutional usability
> validation across the full bridge lifecycle.

### Phase 8.9-8.16b retro arc summary

| retro phase | verdict | methodology |
|---|---|---|
| P1 (style-engine, 8.9) | DIVERGENT | single-lens screening |
| P3 (supports-field, 8.9) | ADDITIVE + CONFIRM | single-lens formalization |
| P4 (directive-protocol, 8.12) | ADDITIVE + STRENGTHENED + DIVERGENT | multi-lens analysis |
| P5 (register-client-js, 8.12.5) | ADDITIVE WITH CAVEATS + Law 3 dependence STRONG | dual-lens audit-gate satisfaction |
| P6 (runtime-state, 8.16) | CONFIRM (Outcome A) | deployment-validation |
| **P7 (hydration, 8.16b) — THIS** | **CONFIRM (Outcome A — lifecycle completion)** | **deployment-validation (lifecycle stage)** |

**7 retros × 5 distinct outcome types** + **deployment-
validation methodology refined to lifecycle completion
character**. Phase 8.9-8.16b retro arc demonstrates KB's
Q9 retro discipline at full sophistication across discovery /
audit-prep / multi-lens / audit-gate / deployment-validation
modes.

### Anticipated next chunks (post-this-retro)

Per Phase 8.16 strategic sequence:

1. **Phase 8.16c — Interactivity bounded context closure
   adjudication** — first full bounded context closure
   under Constitution v2 framework. Major comparison study
   with pre-v2 bounded context closures (plugin-dev /
   admin-ui / i18n). May surface "constitutional civilization
   archetypes" observation explicitly.

2. **Phase 8.17 — Pre-v2 vs v2 bounded context closure
   comparative study** — first constitutional historiographic
   comparative study (per Phase 8.15 frontier map).

3. **Phase 8.18+ — Constitutional civilization archetypes
   adjudication** — IF Phase 8.16c reveals strong archetypal
   distinctions; observation surfaced this retro.

4. **Forward chunk authoring in untouched bounded contexts**
   — build-tooling, additional data-layer chunks, etc. v2-
   native forward authoring (where chunks don't yet exist).

5. **Phase 8.20+ — Law 3b lifecycle breadth formalization
   adjudication** — IF lifecycle breadth observation matures;
   currently observation only.

Recommended: **Phase 8.16c — Interactivity bounded context
closure adjudication**. Triple v2-deployment validation
(directive-protocol Phase 8.12 + runtime-state Phase 8.16 +
hydration Phase 8.16b) creates ideal foundation for first
full v2-governed bounded context closure document. This
would be **first bounded context closed under constitutional
civilization stage III (Hierarchy Sophistication Era)** —
historically distinct from pre-v2 closures.

### Status updates

- This file's overall `status` remains `evolving` (original
  evaluation preserved).
- Retro patch adds Q9 v2-deployment validation verdict
  (CONFIRM Outcome A — lifecycle completion) + Law 3b 3b-
  react lifecycle breadth observation (NOT formalization) +
  Computational-architectural 3rd confirmation +
  constitutional civilization archetype observation +
  constitutional restraint demonstration.
- Original chunk content (lines 1-733) UNCHANGED; this retro
  is purely additive at end of file.
