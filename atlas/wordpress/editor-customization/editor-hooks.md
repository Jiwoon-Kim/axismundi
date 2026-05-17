---
rule_id: editor-customization.editor-hooks
domain: editor-customization
topic: reactive-state-mediation
field_cluster: governance-surfaces
wp_min: "5.5"
wp_recommended: "5.5+"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/
    section: "@wordpress/data — store registry, useSelect, useDispatch"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/data/
    section: "Data Module — core/editor / core/block-editor / core/data stores"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-element/
    section: "@wordpress/element — React hooks integration"
    captured: 2026-05-09
related:
  - editor-customization.block-filters             # 1st modality (lifecycle interception)
  - editor-customization.slotfills                 # 2nd modality (topology interception)
  - data-layer.entity-resolution                   # editor stores extend entity-resolution substrate
  - data-layer.persistence                         # useDispatch invokes persistence reconciliation
  - plugin-dev.register-block-bindings-source      # cross-context: federation pattern (createReduxStore is similar shape)
  - plugin-dev.security-boundaries                 # capability checks orthogonal to subscription / dispatch
  - _meta.structural-patterns                      # constitutional law layer — modality adjudication event
---

# RULE — editor hooks — reactive state mediation (modality adjudication chunk)

## WHEN

A plugin or theme needs to **read editor state** (current post,
selected block, editor mode, current user permissions in editor
context) or **dispatch editor actions** (insert blocks, update
attributes, save post, change selection) within React
components rendered inside the editor.

Use editor hooks when:
- Building custom block UI that depends on editor state.
- Implementing slot-fill content that adapts to current
  context (selected block, post status, etc.).
- Triggering editor actions from custom UI (save, navigate,
  modify content).
- Subscribing to entity / store changes for reactive UI.
- Registering custom data stores (editor-side state extension).

This is the **third chunk in editor-customization** and the
**third deployment of the constitutional protocol**. It is
positioned as a **modality adjudication chunk** rather than a
recurrence test.

The doctrinal extension this chunk attempts to establish:

> **Block filters intercept lifecycle authority.**
> **SlotFill intercepts topology authority.**
> **Editor hooks test whether reactive state authority is**
> **likewise intercepted — or operates through a structurally**
> **distinct governance modality.**

This chunk's primary work is **honest ontological adjudication**:
does reactive state governance fit the Authority Interception
Surface candidate, or does it surface structurally distinct
governance? The methodological commitment:

> **This chunk should not prove a theory. It should adjudicate**
> **ontology under evidence.**

## SHAPE

### A. Store topology

The editor exposes named stores via `@wordpress/data` registry:

```
Editor data stores (selected core stores):

core                 ── canonical entity data (data-layer.entity-resolution)
core/editor          ── post-editor specific state (current post,
                       autosave, lock, editor preferences)
core/block-editor    ── block-tree state (insertion, selection,
                       drag, multi-select)
core/edit-post       ── post-editor UI state (sidebar visibility,
                       editor mode, panel state)
core/edit-site       ── site-editor UI state (template editing,
                       global styles)
core/notices         ── editor notice queue
core/preferences     ── user preferences persisted server-side
```

Each store is a **named state surface** with selectors (read
contracts) and actions (write contracts).

Plugins may **register custom stores** via `createReduxStore`:

```js
import { createReduxStore, register } from '@wordpress/data';

const store = createReduxStore( 'my-plugin/my-store', {
    reducer: ( state = {}, action ) => { /* ... */ },
    actions: { /* ... */ },
    selectors: { /* ... */ },
} );

register( store );
```

⚠ Note: createReduxStore registration is **structurally a
federation operation** (parallels plugin-dev's
register_block_bindings_source / register_meta — declares NEW
authority origin). It is NOT interception. This is one of three
ontological modes editor-hooks exhibits (see SHAPE C).

### B. Hook mechanics

```js
import { useSelect, useDispatch } from '@wordpress/data';

function MyComponent() {
    // Read state via subscription
    const { post, isSaving } = useSelect( ( select ) => ( {
        post:     select( 'core/editor' ).getCurrentPost(),
        isSaving: select( 'core/editor' ).isSavingPost(),
    } ), [] );

    // Get action dispatchers
    const { savePost, editPost } = useDispatch( 'core/editor' );

    return (
        <button onClick={ () => savePost() } disabled={ isSaving }>
            { isSaving ? 'Saving...' : 'Save' }
        </button>
    );
}
```

| API | semantics |
|---|---|
| `useSelect( fn, deps )` | subscribes to store; re-renders component when selector output changes |
| `useDispatch( store )` | returns object of action dispatchers for the store |
| `select( store )` | imperative selector access (non-reactive; use sparingly) |
| `dispatch( store )` | imperative action dispatch (outside hook context) |

### C. Governance character — three distinct ontological modes

Editor hooks exhibit **three distinct usage modes** with
different ontological characters. This is the chunk's central
finding:

#### Mode 1: Direct hook usage (subscription / dispatch)

```js
function MyPanel() {
    const post = useSelect( s => s('core/editor').getCurrentPost() );
    const { savePost } = useDispatch( 'core/editor' );
    return <div>...</div>;
}
```

**Ontological character: MEDIATION, NOT interception.**
- The component does NOT mutate store state authority.
- The component does NOT inject behavior into the store's
  lifecycle.
- The component CONSUMES (read) and DELEGATES (dispatch action)
  through controlled access channels.

This is **controlled access mediation** — fundamentally
different from intercepting (block filters) or injecting
(SlotFill).

#### Mode 2: HOC pattern with hook usage

```js
const withCurrentPost = ( BlockEdit ) => ( props ) => {
    const post = useSelect( s => s('core/editor').getCurrentPost() );
    return <BlockEdit { ...props } currentPost={ post } />;
};

addFilter( 'editor.BlockEdit', 'my-plugin/x',
    createHigherOrderComponent( withCurrentPost, 'withCurrentPost' )
);
```

**Ontological character: INTERCEPTION (inherited via HOC
boundary).**
- The HOC pattern wraps BlockEdit (interception per block-
  filters chunk).
- The hook usage WITHIN the HOC is mediation, but the OUTER
  HOC is interception.
- Composite character: interception layer + mediation
  inside.

This mode confirms Authority Interception Surface candidate
applicability to a SUBSET of editor-hooks usage.

#### Mode 3: Custom store registration (createReduxStore)

```js
const store = createReduxStore( 'my-plugin/my-store', { ... } );
register( store );
```

**Ontological character: FEDERATION (cross-context recurrence
with plugin-dev pattern).**
- Declares NEW state authority origin in the data registry.
- Structurally parallels plugin-dev's register_block_bindings_source
  (authority origin federation).
- NOT interception, NOT mediation — it is **federation** of
  state authority surfaces.

This mode reveals that **plugin-dev's federation pattern recurs
inside editor-customization** at the state authority layer.

### D. Responsibility distribution + reactive debt

Asymmetric (per established debt-pattern recurrence):

| actor | provides |
|---|---|
| **Core** | store registry, hook infrastructure, core stores |
| **Plugins / Themes** | hook usage in components, custom stores, dependency arrays, render performance |
| **Editor users** | downstream UI experience |

**Reactive debt** (4th debt-pattern instance in KB after
security / interception / topology):

| debt mode | symptom |
|---|---|
| **over-selection debt** | useSelect returns large objects; re-renders on any change to any property |
| **stale subscriptions** | dependency array missing values; selector closes over stale state |
| **render churn** | high-frequency state changes trigger excessive re-renders |
| **dependency drift** | hook dependencies diverge from selector dependencies; bugs surface |
| **store coupling** | components couple tightly to specific store APIs; refactoring becomes brittle |
| **capability assumption** | hook usage assumes editor capabilities present; doesn't gate by current_user_can |

The debt-pattern recurrence (4 instances now) suggests
"governance debt" itself is a recurring meta-pattern worth
explicit naming. Surfaced for future audit; not promoted to
candidate yet.

### E. Failure surfaces

```
Hook failure modes:

- Selector errors (selector throws → component crashes)
- Dispatch errors (action throws → unhandled promise rejection)
- Subscription leaks (component unmounts but subscription persists)
- Stale closure bugs (deps array missing reactive values)
- Store unavailability (custom store registered late; useSelect
  returns undefined initially)
- Editor variant divergence (core/editor vs core/edit-site;
  using wrong store for current variant)
- Performance cascades (over-broad selectors → over-broad
  re-renders → propagation across UI)
- Authority assumption (assuming user has editor access;
  components rendering for unauthorized users → broken UI)
```

## REQUIRES

- @wordpress/data + @wordpress/element runtime loaded (default
  in editor environment).
- Components calling hooks must be rendered within React tree
  (rules-of-hooks: top-level only, not conditional).
- For custom stores: registration MUST happen before any
  useSelect/useDispatch references the store (typically at
  module load).
- Capability checks for hook-based UI: explicit
  `current_user_can` calls inside components OR via store
  selectors that gate based on user authority.
- Dependency arrays in useSelect MUST include all reactive
  dependencies (dependency drift causes stale closure bugs).
- ⚠ Specific behaviors: subscription performance with thousands
  of registered stores, hot-reload behavior, store registration
  ordering with multiple plugins, useSelect dependency tracking
  precision, action dispatch error propagation —
  verification-needed.

## INVARIANTS

### 1. Editor hooks span 3 distinct ontological modes; force-fitting them into one obscures the architecture

The load-bearing methodological invariant:

> Editor hooks are NOT a single governance mechanism. They span:
> - **Mode 1: Direct mediation** (useSelect / useDispatch as
>   controlled access channels)
> - **Mode 2: HOC interception** (hook used inside HOC pattern;
>   outer HOC is interception)
> - **Mode 3: Federation** (createReduxStore as state authority
>   origin declaration)
>
> Each mode has different ontological character. Treating
> editor-hooks as monolithically "interception" or
> monolithically "mediation" force-fits the evidence.

This invariant is the chunk's **methodological adjudication
result** — honest acknowledgment of ontological heterogeneity
within a single API surface.

### 2. useSelect = subscription mediation, NOT interception

Direct useSelect calls subscribe to existing store state. The
hook does NOT:
- Mutate state authority (read-only access).
- Inject behavior into store lifecycle (no interception).
- Wrap or modify other components' authority.

The hook DOES:
- Establish a **subscription relationship** with the store
  (reactive update channel).
- Provide **controlled access** to selector outputs
  (mediation through declared API).
- Enable **dependency-tracked re-rendering** (React-side
  reactivity protocol).

Reading useSelect as "interception" misclassifies it; it is
**access mediation** governed by the store's selector contract.

### 3. useDispatch = action delegation mediation, NOT interception

Symmetric for write side. useDispatch returns the store's
action dispatchers. The component INVOKES actions via the
store's API; the store OWNS state mutation logic.

Component does NOT mutate state directly; component DELEGATES
mutation to store-owned actions. This is **delegated invocation
mediation**, not interception.

### 4. HOC patterns inherit interception character from outer wrapper

When useSelect / useDispatch are used inside HOC patterns
(e.g., `createHigherOrderComponent` wrapping BlockEdit), the
OUTER HOC is interception (per block-filters chunk's
ontology); the INNER hook usage is mediation.

```
HOC pattern composition:
   addFilter + createHigherOrderComponent  ── INTERCEPTION (outer)
   useSelect / useDispatch inside          ── MEDIATION (inner)
                                              composite character
```

This composite character means hook usage WITHIN HOCs is part
of interception architecture; hook usage OUTSIDE HOCs (direct
in components) is mediation.

### 5. createReduxStore = federation pattern recurrence (cross-context)

```
createReduxStore ontology:
   Declares NEW state authority origin.
   Plugins extend the data registry with custom stores.
   Same structural shape as plugin-dev federation
   (register_block_bindings_source declares authority origins).
```

This is **plugin-dev's federation pattern recurring inside
editor-customization** at the state authority layer.

KB-level finding: a single bounded context can host MULTIPLE
constitutional patterns simultaneously — interception
(filters), topology interception (slotfills), mediation
(direct hooks), AND federation (custom stores).
editor-customization is the first bounded context to exhibit
this **multi-pattern governance heterogeneity**.

### 6. Reactive subscriptions create access channels distinct from interception

The recurring axis (Law 5 — Entity → Relationship Pivot)
manifests differently in mediation than in interception:

| pattern | relationship type |
|---|---|
| Block filters (interception) | filter author → block lifecycle phase (mutation relationship) |
| SlotFill (topology interception) | fill author → named slot (insertion relationship) |
| **useSelect (mediation)** | **component → store selector (subscription relationship)** |
| **useDispatch (mediation)** | **component → store action (delegation relationship)** |

All four are relationship-centric (Law 5 confirmed in 5+
manifestations now). But the relationship CHARACTER differs:
mutation / injection / subscription / delegation. The
relationship-centric reading unifies; the ontological character
diverges.

### 7. Reactive debt (4th debt-pattern instance) suggests "governance debt" as recurring meta-pattern

KB now has 4 documented debt instances:

| chunk | debt name | character |
|---|---|---|
| security-boundaries | security debt | governance gaps |
| block-filters | interception debt | lifecycle complexity |
| slotfills | topology debt | UI complexity |
| **editor-hooks (this)** | **reactive debt** | **subscription / dependency / performance complexity** |

4-instance recurrence across plugin-dev + editor-customization
(2 contexts, 4 chunks) suggests **"governance debt" itself may
be a recurring meta-pattern**. Each governance modality has
its own debt mode; debt accumulation is a structural pattern
across modalities.

Surfaced for future audit consideration. NOT promoted to
candidate yet (per discipline: surface, do not constitutionalize).

### 8. Editor-hooks adjudicates 3 modality candidates simultaneously — first multi-candidate adjudication chunk in KB

This invariant IS the constitutional adjudication event:

```
Editor-hooks adjudication results:

Authority Interception Surface candidate:
   Pre-this-chunk: Recurring (intra-context, 2-modality)
   Editor-hooks evidence: Confirmed (HOC subset only) +
                          DIVERGENT (direct hooks)
   Post-this-chunk: Recurring (intra-context) maintained;
                    NOT strengthened to 3-modality
                    (because direct hook mode diverges from
                    interception ontology)

Authority Mediation Surface candidate (NEW):
   Pre-this-chunk: did not exist
   Editor-hooks evidence: Surfaced via direct useSelect /
                          useDispatch (controlled access
                          channels distinct from interception)
   Post-this-chunk: Local Pattern Surface (1st observation,
                    editor-customization);
                    "surfaced, not constitutionalized"

Federation Pattern (KB-Wide, plugin-dev origin):
   Pre-this-chunk: documented in plugin-dev
   Editor-hooks evidence: createReduxStore = federation
                          recurrence inside editor-customization
   Post-this-chunk: Cross-context recurrence noted;
                    federation pattern reaches 2 bounded
                    contexts (plugin-dev + editor-customization)
```

This is the **first chunk in KB to adjudicate 3 candidate
patterns simultaneously**. The multi-candidate adjudication
demonstrates KB's methodological maturity — each candidate
gets honest evidence-based status without forcing unification.

## VERIFICATION NEEDED

`status: stable` — @wordpress/data + hooks API mature
(WP 5.5+). Specific behaviors evolving / variable:

- Subscription performance with thousands of components +
  multiple selectors per component.
- Selector memoization behavior with object/array returns.
- Hot-reload behavior with HMR enabled.
- Custom store registration ordering with multiple plugins.
- Editor variant store availability (core/editor vs
  core/edit-site selector overlap / divergence).
- Action error propagation patterns (sync vs async actions).
- useSelect dependency array tracking precision.
- Behavior when subscribing to unregistered store.
- Multi-version coexistence (multiple WP versions with
  different store schemas).
- Garbage collection of subscriptions on component unmount.
- Editor / non-editor context behavior (hooks in admin
  pages vs editor canvas).

For practical decisions: empirical testing per scenario
(React DevTools subscription view + console performance
profiling).

## ANTIPATTERNS

- ❌ **useSelect for one-time read**. Use `select()` (imperative)
  for one-time reads. useSelect establishes subscription;
  unnecessary subscriptions are reactive debt.
- ❌ **Selector returns whole object** when only one property
  is needed. Re-renders on any property change. Select
  specific properties.
- ❌ **Missing dependency array values**. Selector closes over
  stale state; bugs surface as "doesn't update when expected."
- ❌ **Skipping capability checks for hook-based UI**. Hooks
  return state; UI rendering them is exposure surface.
  Capability check + null render OR fallback UI for
  unauthorized actors.
- ❌ **createReduxStore without namespace**. Custom stores
  need `vendor/identifier` namespace; collisions with other
  plugins / core stores.
- ❌ **Imperative dispatch outside React lifecycle**. dispatch
  outside component lifecycle bypasses React's batching;
  unintended re-render cascades.
- ❌ **Hook usage in conditional or loop branches**. Violates
  rules-of-hooks. React enforces but errors are confusing.
- ❌ **Direct store mutation bypassing actions**. Custom stores
  expose state via selectors but expect mutation via actions.
  Direct mutation breaks subscription propagation.
- ❌ **Forcing all reactive governance into interception**
  because prior chunks established interception as
  editor-customization pattern. Reactive subscriptions are
  ontologically distinct from lifecycle / topology
  interception. Force-fitting obscures architecture.
- ❌ **Treating hook API similarity as ontological sameness**.
  useSelect resembles addFilter syntactically (both register
  callbacks); they are ontologically different (subscription
  mediation vs lifecycle interception). API shape ≠ governance
  character.
- ❌ **Treating Mode 1 / Mode 2 / Mode 3 as interchangeable**.
  Each mode has different ontological character; choice
  affects performance, debugging, and architectural fit.

## RELATED

- `editor-customization.block-filters` — 1st modality
  (lifecycle interception). Editor-hooks Mode 2 (HOC pattern)
  inherits interception character from this chunk's ontology.
- `editor-customization.slotfills` — 2nd modality (topology
  interception). Editor-hooks differ from SlotFill: SlotFill
  injects UI topology; hooks subscribe to / dispatch state.
- `data-layer.entity-resolution` — editor stores extend the
  entity-resolution substrate. core store IS data-layer's
  surface; core/editor + core/block-editor are editor-specific
  state surfaces.
- `data-layer.persistence` — useDispatch invocations of
  saveEntityRecord etc. invoke data-layer.persistence's
  reconciliation lifecycle.
- `plugin-dev.register-block-bindings-source` — cross-context:
  createReduxStore is structurally parallel to bindings source
  registration (federation pattern). This chunk documents the
  cross-context recurrence.
- `plugin-dev.security-boundaries` — capability checks
  orthogonal to subscription / dispatch; hook-based UI
  requires explicit capability gating.
- `_meta.structural-patterns` — constitutional law layer.
  This chunk produces the first multi-candidate adjudication
  event (3 candidates evaluated simultaneously).

## META

**editor-customization bounded context — third chunk; third
constitutional protocol deployment; first MULTI-CANDIDATE
ADJUDICATION event in KB.**

### Doctrinal extension established

> Block filters intercept lifecycle authority.
> SlotFill intercepts topology authority.
> Editor hooks ADJUDICATE the modality boundaries — confirming
> interception in HOC subset, surfacing mediation as distinct
> candidate, recognizing federation pattern recurrence.

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 4 — Arbitration Compiler** | Very Strong | Selector / store mediation; useSelect dependency tracking; render arbitration | **Confirmed (subscription-arbitration variant)** |
| **Law 6 — Compiler ↔ Runtime Split** | Very Strong | Hook declaration (compile-time React) vs runtime subscription (state propagation) | **Confirmed (declaration-runtime split)** |
| **Law 5 — Entity → Relationship Pivot** | Moderate | Subscription/dispatch as relationship channels; component-store relationships | **Confirmed (5th-context manifestation; relationship character differs from interception)** |
| **Law 3 — Authority Continuity** | Strong | Live editor state survives component re-renders + remounts via subscription continuity | **Confirmed (subscription-continuity variant)** |
| **Law 1 — Declaration ≠ Exposure** | Moderate | Registered store ≠ surfaced hook usage; selector available ≠ component using it | **Confirmed (3-form: registration / availability / usage)** |
| **Law 2 — HTML Primacy** | Implicit | Hook outputs render through React → HTML | **Confirmed (implicit doctrine adherence)** |

**Universal law manifestation: SUCCESS.** All predicted laws
manifested. Law 5 reaches 5th-context manifestation
(strengthens KB-Wide status further).

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | Editor-hooks manifestation | Outcome |
|---|---|---|---|
| **Authority Interception Surface** | Recurring (intra-context, 2-modality: lifecycle + topology) | HOC subset CONFIRMED (Mode 2); direct hooks DIVERGENT (Modes 1, 3 do NOT fit interception ontology) | **Recurring (intra-context) maintained; NOT strengthened to 3-modality** |
| **Authority Mediation Surface** (NEW) | did not exist | Surfaced via direct useSelect / useDispatch (controlled access channels distinct from interception) | **Local Pattern Surface (1st observation); "surfaced, not constitutionalized"** |
| **Federation Pattern** (cross-context) | KB-Wide (plugin-dev origin) | createReduxStore = federation pattern recurrence inside editor-customization | **Cross-context recurrence noted; federation reaches 2 bounded contexts** |

**Verdict classes used: Confirmed / Divergent / Surfaced /
Cross-context recurrence.** The "Hybridized" verdict mentioned
in user methodological framing is implicit in the
multi-candidate result (editor-hooks exhibits mixed character).

### Epistemic Integrity Note

> **This chunk prioritizes constitutional accuracy over prior**
> **pattern preservation. Divergence is treated as signal,**
> **not failure.**

The chunk's primary methodological achievement is honest
adjudication: it would have been easier to force-fit
editor-hooks into Authority Interception Surface (preserving
prior 2-modality recurrence and creating a tidy 3-modality
triad). Instead, the chunk documents:

- HOC-pattern hook usage CONFIRMS interception (subset
  recurrence).
- Direct hook usage DIVERGES toward mediation (new candidate
  surfaced, NOT promoted).
- Custom store registration FEDERATES (cross-context recurrence
  with plugin-dev).

This produces a more complex but more accurate ontological
picture. **KB methodological maturity = ability to refuse
clean stories when evidence demands complex ones.**

### Structural-Patterns Governance Note

Three governance observations:

1. **Recurring (intra-context) status preserved, NOT
   strengthened.** The Authority Interception Surface candidate
   does NOT reach 3-modality status because direct hook usage
   doesn't fit the interception ontology. Promotion path
   remains:
   ```
   Recurring (intra-context, 2-modality)
      ↓ requires cross-context recurrence
   Recurring (cross-context)
      ↓ requires audit verification
   KB-Wide Law
   ```

2. **NEW candidate surfaced — Authority Mediation Surface.**
   Defined: governance through controlled access channels to
   existing authority (read via selector / write via delegated
   action). Status: Local Pattern Surface (1st observation).
   "surfaced, not constitutionalized." Test for recurrence
   in future contexts (admin-ui form-data hooks? data-layer
   selectors? — both are mediation candidates).

3. **Federation pattern recurrence (cross-context).**
   plugin-dev's federation pattern (register_*) recurs in
   editor-customization via createReduxStore. This is
   structurally significant: a bounded context can HOST
   patterns that originated in other bounded contexts.
   editor-customization becomes a **multi-pattern bounded
   context**.

### Anticipated future patterns (predictive frontier)

If Authority Mediation Surface manifests in 2nd context:
- Recurring (intra-context) for editor-customization +
  data-layer? (data-layer selectors are mediation by similar
  ontology).
- May surface FUTURE TRIAD framing (per user observation):
  - **Interception** = authority reshaping
  - **Mediation** = authority access choreography
  - **Federation** = authority origination
  - These three may eventually be recognized as parallel
    governance classes in KB.

This triad is **anticipated, not constitutionalized**.
Verification path requires multiple cross-context observations.

### KB-level framing extension

> editor-customization is the first bounded context to host
> **simultaneously**:
> - Pattern recurrence (Authority Interception Surface)
> - Pattern divergence (Authority Mediation Surface surfaced)
> - Pattern overlap (Federation pattern from plugin-dev
>   recurring here)
>
> This is **multi-pattern governance heterogeneity** within a
> single bounded context.

editor-customization is therefore not just law-generation
capable (per slotfills chunk) — it is **pattern-heterogeneous**
at the bounded context level. KB now has explicit evidence that
bounded contexts can be ontologically rich (multiple patterns)
rather than monothematic.

### Editor-customization bounded context — TRIAD COMPLETE

```
editor-customization (after this chunk):
   block-filters       → lifecycle interception        ✓
   slotfills           → topology interception         ✓
   editor-hooks        → reactive mediation +
                         HOC interception subset +
                         federation recurrence         ✓ (this)
   ↓
   BOUNDED CONTEXT TRIAD COMPLETE.
```

Editor-customization closes its core triad. Future chunks
within editor-customization (e.g., editor preferences API,
specific slot deep-dives) extend WITHIN this established
governance heterogeneity.

### KB self-evaluation against spec criteria

- ✅ Accuracy — describes documented hooks API.
- ✅ Structural fit — completes editor-customization triad;
  documents multi-candidate adjudication; surfaces new
  candidate without overreach.
- ✅ Reusability — uses authority ontology glossary
  (mediation / interception / federation / debt /
  arbitration).
- ✅ Phase fit — references all editor-customization peers +
  data-layer + plugin-dev (cross-context federation pattern).
- ✅ Doctrine respect — HTML primacy implicit; declaration ≠
  exposure explicitly invoked; epistemic integrity prioritized
  over pattern preservation.

### Status: `stable`

@wordpress/data + hook APIs are mature (WP 5.5+).
Verification-needed catalog covers behaviors but core API is
settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission (chunk-level)

> This chunk should not prove a theory.
> It should adjudicate ontology under evidence.

### One-line backbone (KB-level)

> Editor hooks adjudicate the modality boundaries — confirming
> interception in HOC subset, surfacing mediation as distinct
> candidate, recognizing federation pattern recurrence.

### Anticipated next chunks (priority)

1. **Constitutional spec update** (structural-patterns
   refinement) — the multi-candidate adjudication revealed
   needs:
   - Add intra/cross-context distinction to Section A
     (deferred since slotfills; now urgent).
   - Add "Surfaced" status alongside Local/Recurring
     promotions (this chunk surfaced new candidate).
   - Add "Hybridized" or "Multi-modal" verdict class to
     candidate evaluation.
   - Document multi-pattern bounded context as recognized
     phenomenon.

2. **Cross-context test** for Authority Mediation Surface —
   data-layer selectors (already documented as mediation in
   character) + admin-ui form data hooks. Could promote
   Mediation candidate to Recurring (cross-context).

3. **Other bounded contexts** (additive) — site-building /
   i18n / admin-ui / build-tooling.

4. **`plugin-dev.nonces`** — plugin-dev security trio
   completion.

Recommended sequence: **Constitutional spec update first**
(structural-patterns has accumulated 2 chunks of refinement
candidates; multi-candidate adjudication requires spec
formalization). Then admin-ui or site-building (cross-context
mediation test). Then nonces.
