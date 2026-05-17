---
rule_id: editor-customization.slotfills
domain: editor-customization
topic: topology-interception
field_cluster: governance-surfaces
wp_min: "5.0"
wp_recommended: "5.0+"
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/slotfills/
    section: "SlotFills — registerPlugin + named slot extensions"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-plugins/
    section: "@wordpress/plugins — registerPlugin / getPlugin / unregisterPlugin"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/components/slot-fill/
    section: "@wordpress/components — Slot / Fill / SlotFillProvider primitives"
    captured: 2026-05-09
related:
  - editor-customization.block-filters             # paired chunk — both intercept authority but at different modalities
  - block-authoring.edit-save-components           # editor block UI is the topology slotfills inject into
  - plugin-dev.security-boundaries                 # slotfills expand attack surface (topology debt)
  - plugin-dev.capabilities-and-roles              # fill registration may be capability-gated
  - _meta.structural-patterns                      # constitutional law layer — promotion event documented here
  - (planned) editor-customization.editor-hooks    # third governance modality (reactive subscriptions)
---

# RULE — SlotFill — interface topology governance

## WHEN

A plugin or theme needs to **inject UI** into named extension
points within the WordPress block editor — sidebars, document
panels, more-menu items, pre-publish dialogs, block-settings
menus — without owning the editor's component tree.

Use SlotFill when:
- Adding a custom sidebar to the editor
  (PluginSidebar / PluginSidebarMoreMenuItem).
- Adding panels to the document settings
  (PluginDocumentSettingPanel).
- Adding items to editor menus
  (PluginMoreMenuItem / PluginBlockSettingsMenuItem).
- Adding pre-publish or post-publish review panels
  (PluginPrePublishPanel / PluginPostPublishPanel).
- Adding components above the post status indicator
  (PluginPostStatusInfo).

This is the **second chunk in editor-customization** and the
**second deployment of the constitutional protocol**. It is
positioned deliberately as a **recurrence test** for the
"Authority Interception Surface" candidate pattern surfaced in
block-filters. The chunk's success demonstrates whether the
candidate recurs across editor-customization mechanisms or
remains a block-filters-local insight.

The doctrinal extension for editor-customization (deepened
here):

> **Block filters proved authority interception exists;**
> **SlotFill proves that interception recurs through multiple**
> **governance modalities.**

Where block filters intercept the **block lifecycle**
(registration / edit / save), SlotFill intercepts the **editor
interface topology** (named insertion points in the editor
component tree). Two different modalities; same authority
interception ontology.

## SHAPE

### A. SlotFill topology — named injection surfaces

The editor exposes **named slots** at predetermined topology
points. Plugins register **fills** that render inside specific
slots:

```
Editor component tree (selected slot points):

PluginSidebar              ── opens new sidebar pane in editor
PluginSidebarMoreMenuItem  ── adds menu item that toggles a sidebar
PluginDocumentSettingPanel ── adds panel to document settings sidebar
PluginPostStatusInfo       ── inserts above post status indicator
PluginMoreMenuItem         ── adds item to editor's more (⋮) menu
PluginBlockSettingsMenuItem── adds item to selected block's settings menu
PluginPrePublishPanel      ── adds panel before publish action
PluginPostPublishPanel     ── adds panel after publish action
```

Each is a **named topology surface** — declared by core at a
specific position in the editor component tree, addressable by
plugin code via React component import.

(⚠ Specific slot list evolves per WP version; complete current
list requires reference to `@wordpress/edit-post` /
`@wordpress/edit-site` slot exports at consultation time.)

### B. Registry mechanics

```js
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem }
    from '@wordpress/edit-post';
import { PanelBody } from '@wordpress/components';

const MyPlugin = () => (
    <>
        <PluginSidebarMoreMenuItem target="my-sidebar">
            { __( 'My Sidebar', 'my-plugin' ) }
        </PluginSidebarMoreMenuItem>
        <PluginSidebar
            name="my-sidebar"
            title={ __( 'My Sidebar', 'my-plugin' ) }
        >
            <PanelBody>...sidebar contents...</PanelBody>
        </PluginSidebar>
    </>
);

registerPlugin( 'my-plugin/my-sidebar', {
    icon: 'admin-plugins',
    render: MyPlugin,
    scope: 'my-plugin',  // optional namespace
} );
```

| component | role |
|---|---|
| `registerPlugin( name, settings )` | registration entry point |
| `name` (1st arg) | required `vendor/identifier` — collision-avoidance + handle for unregister |
| `settings.icon` | menu icon (where applicable) |
| `settings.render` | React component returning fills |
| `settings.scope` | optional namespace (e.g., specific editor variant) |
| Plugin* fill components | bind fill content to specific named slots |

`unregisterPlugin( name )` removes the plugin's fills.

### C. Governance model — UI topology interception

SlotFill governance differs from block filter governance:

| concern | block filters | SlotFill |
|---|---|---|
| **interception modality** | lifecycle phase (registration/edit/save) | interface topology (named slot insertion) |
| **mutation surface** | block authority (settings, props, save output) | editor authority (component tree composition) |
| **composition** | priority-ordered chain | multiple fills per slot, rendered together |
| **scope** | block type | editor surface |
| **persistence impact** | save filters affect serialized markup | UI-only, no persistence impact (typically) |

Both are **authority interception**, but operate on different
authority kinds: filters intercept block authority; SlotFill
intercepts UI authority (editor component composition).

### D. Responsibility distribution + topology debt

Asymmetric (per security-boundaries / interception-debt
patterns):

| actor | provides |
|---|---|
| **Core editor** | named slot exposure, slot rendering infrastructure, lifecycle |
| **Plugins / Themes** | fills + intent + namespace + capability gating + UX coherence |
| **Editor users** | downstream UI composition recipients |

**Topology debt** (parallel to block-filters' interception debt
+ security-boundaries' security debt):

| debt mode | symptom |
|---|---|
| **clutter** | too many fills target the same slot; UI becomes overwhelming |
| **conflicting fills** | multiple plugins inject competing UI in the same slot |
| **discoverability debt** | fill-injected UI is hard for users to find / understand context |
| **governance debt** | fills bypass capability checks; admin-only UI exposed broadly |
| **UX fragmentation** | each plugin injects its own panel style; editor coherence degrades |

Unlike interception-debt (which corrupts lifecycle output),
topology debt corrupts UX coherence. Different debt mode; same
accumulation pattern.

### E. Constitutional recurrence test

This chunk's primary purpose beyond documenting SlotFill:
**verify whether "Authority Interception Surface" candidate
pattern recurs**.

- **Block filters**: lifecycle interception → existing block
  authority intercepted at lifecycle phases.
- **SlotFill**: topology interception → existing editor
  authority intercepted at named UI slots.

Both:
- Intercept existing authority without ownership.
- Use registration via @wordpress/* package.
- Require namespace (vendor/identifier).
- Compose from multiple sources without owning the host.
- Accumulate debt when not retired.

The structural core matches: **mechanisms that intercept and
reshape existing authority without owning it**. The mechanism
differs (lifecycle hooks vs named slots); the ontology
matches.

**Recurrence verification result**: see Constitutional Field
Test section in META.

## REQUIRES

- @wordpress/plugins runtime loaded (default in editor
  environment).
- Plugin component imported from correct
  `@wordpress/edit-post` / `@wordpress/edit-site` /
  `@wordpress/edit-widgets` package (slots are package-scoped).
- registerPlugin called BEFORE the editor renders the slot
  (typically before DOMContentLoaded).
- Namespace MUST follow `vendor/identifier` format.
- For accessibility: fill content must include appropriate
  ARIA labels, focus management, etc. — UI authority
  inheritance does NOT include accessibility automatically.
- Capability gating (e.g., `current_user_can( ... )`) applied
  inside the render function for fills that should be
  restricted.
- ⚠ Specific behaviors: slot rendering order with multiple
  fills, fill conditional rendering performance, scope
  semantics for cross-editor reuse, hot-reload behavior in
  development — verification-needed.

## INVARIANTS

### 1. SlotFill surfaces are named interface authority insertion points, NOT arbitrary UI extension

The load-bearing reframing:

> SlotFill is NOT "a way to add UI to the editor."
> SlotFill is **named interface authority insertion** —
> core declares specific topology points where UI authority
> may be injected; plugins fill those points without owning
> the surrounding editor structure.

Reading SlotFill as "open UI canvas" misses the named-slot
discipline. Each slot is a deliberate exposure point with
specific contextual semantics (sidebar = lateral panel;
DocumentSettingPanel = settings sidebar; PluginPostStatusInfo
= adjacent to post status).

### 2. SlotFills intercept editor topology through additive relationship governance

Filters intercept lifecycle phases; SlotFills intercept editor
topology:

| approach | intercepts |
|---|---|
| **block filters** (lifecycle interception) | block lifecycle phases (registration / edit / save) |
| **SlotFill** (topology interception) | editor component tree at named slot points |

Both create governance relationships between actors and
existing authority surfaces. Filters create relationships
through lifecycle mutation; SlotFills through topology
injection. Different mechanism; same governance ontology.

### 3. Slots declare governance surfaces; fills negotiate participation

Two-step authority federation:

```
Core editor:
   declares slots (governance surface declaration)
   ↓
Plugin author:
   registers fill targeting slot (participation negotiation)
   ↓
Editor render:
   composes registered fills at the slot position
```

Slots are **declarative governance surfaces** authored by core.
Fills are **negotiated participations** authored by plugins.
Neither owns the other; the editor mediates.

This is structurally similar to plugin-dev's federation
pattern (core supplies surfaces; plugins federate into them)
but operates at the UI authority layer rather than entity /
persistence / transport / governance layers.

### 4. Registration ≠ rendered authority presence

KB-recurring axis (Law 1 — Declaration ≠ Exposure) at the
SlotFill layer:

| surface | controlled by |
|---|---|
| **registration** (fill is registered against slot) | `registerPlugin()` call |
| **rendering** (fill is actually displayed) | slot existence in current editor + render conditions |
| **visibility** (user sees the fill) | UI navigation + capability + screen context |

A registered fill may not render (slot doesn't exist in current
editor variant — e.g., a slot is in @wordpress/edit-post but
not @wordpress/edit-site). A rendered fill may not be
user-visible (sidebar collapsed; user lacks capability). Each
surface is independently governed.

### 5. Editor UI becomes relationship-topological, not merely component-additive

KB-recurring axis (Law 5 — Entity → Relationship Pivot) at
the editor UI layer:

Pre-SlotFill reading: "the editor has a fixed UI; plugins add
components to it."

Post-SlotFill reading:

> The editor UI is a **topology of named relationships**
> between core-declared slots and plugin-registered fills.
> Composition is multiplexed (multiple fills per slot) and
> conditional (slots may exist or not per editor variant /
> screen context).

Editor UI is a **relationship graph**, not a static component
hierarchy. Each slot is a relationship anchor; each fill is a
relationship edge.

This **manifests Entity → Relationship Pivot in
editor-customization at a 4th-context confirmation level**
(after style-engine + plugin-dev + block-filters). The
recurring pattern is reaffirmed.

### 6. SlotFill extends inward governance through topology rather than lifecycle mutation

This invariant explicitly differentiates SlotFill from
block-filters:

| editor-customization mechanism | interception modality |
|---|---|
| block filters | **lifecycle interception** (mutate authority at lifecycle phases) |
| **SlotFill** | **topology interception** (inject authority at named topology points) |

Both are "authority interception" (same candidate pattern);
the modality differs.

> Current evidence suggests multiple interception modalities
> (lifecycle / topology), but subtype formalization remains
> premature pending broader recurrence.

A potential future pattern split (Lifecycle Interception /
Topology Interception) is surfaced here but NOT formalized.
Good science: detect the pattern before taxonomizing subtypes.

### 7. UI injection introduces topology debt

Each fill registered expands UI authority + introduces
divergence vectors:

- **Clutter accumulation**: each fill increases UI complexity
  permanently unless retired.
- **Conflict surface**: multiple fills targeting the same slot
  may visually compete or contradict.
- **Discoverability degradation**: fill-injected UI may be hard
  for users to locate.
- **Governance bypass**: fills without capability checks expose
  UI to unauthorized actors.
- **UX fragmentation**: each plugin's fill style may diverge
  from editor design language.

This is **topology debt** — the UI complexity accumulated by
topology interception that is not retired or audited.
Symmetric with:
- block-filters' **interception debt** (lifecycle complexity)
- security-boundaries' **security debt** (governance gaps)

The debt-pattern recurrence across 3 KB chunks suggests
"governance debt" is itself a structural pattern worth
explicit naming in future audits.

### 8. SlotFill recurrence elevates "Authority Interception Surface" from Local to Recurring (intra-context)

> This invariant IS the constitutional promotion event predicted
> in `_meta/structural-patterns.md` Section A.

The promotion event:

```
Authority Interception Surface candidate:
   Pre-this-chunk:  Local Pattern (block-filters only)
   Post-this-chunk: Recurring (intra-context) — pending
                    cross-context verification for broader
                    structural classification

Promotion path remaining:
   Recurring (intra-context) → Recurring (cross-context)
   → KB-Wide Law

Cross-context verification candidates:
   - admin-ui filters / notices
   - site-building template hierarchy filters
   - data-layer entity filters
```

**Promoted: Local → Recurring (intra-context).**

The promotion is qualified ("intra-context") because
recurrence so far is within a single bounded context
(editor-customization). KB-Wide promotion requires recurrence
across multiple bounded contexts (per structural-patterns
Section A).

This is the **first promotion event in KB** under the
structural-patterns governance model. The promotion is
discipline-preserving (matches Section A criteria, refuses
overreach).

## VERIFICATION NEEDED

`status: stable` — SlotFill API mature (WP 5.0+). Specific
behaviors evolving / variable:

- Slot rendering order with multiple fills targeting same slot
  (FIFO? LIFO? registration-time? deterministic?).
- Fill conditional rendering performance with many registered
  plugins.
- `scope` semantics for cross-editor reuse
  (edit-post / edit-site / edit-widgets).
- Hot-reload behavior in development with HMR enabled.
- Slot deprecation / removal behavior across WP versions
  (fills targeting removed slots — silent no-op or warning?).
- Memory characteristics of registered plugin components.
- Editor variant detection within fill render functions.
- Accessibility integration (focus management when fills
  include interactive elements).
- Slot rendering during editor initial mount vs post-mount.
- Behavior when fill targets non-existent slot name.
- Plugin registration timing relative to editor initialization.

For practical decisions: empirical testing per scenario
(register plugin, observe rendering in editor + DevTools
component tree).

## ANTIPATTERNS

- ❌ **SlotFill = free UI space**. Slots are core-declared
  governance surfaces with specific contextual semantics,
  NOT arbitrary canvas. Misuse degrades editor UX coherence.
- ❌ **registerPlugin = guaranteed visibility**. Registration
  declares the fill; rendering depends on slot existence in
  current editor + screen context. A registered fill may
  never appear if its target slot isn't present.
- ❌ **Sidebar = harmless extension**. Adding a sidebar adds
  permanent UI complexity. Each sidebar should justify its
  existence by user value; testing-only sidebars left in
  production = clutter debt.
- ❌ **UI presence = authority**. Fill renders UI but does NOT
  grant the actor capability to perform actions. Capability
  checks must run inside fill render OR in handler functions.
- ❌ **More panels = better UX**. Each PluginDocumentSettingPanel
  added clutters the document settings sidebar. Audit whether
  the panel actually serves frequently-used workflows.
- ❌ **Fill without governance cost consideration**. Fills
  expand attack surface (capability bypass risk), UI clutter,
  and discoverability cost. Each fill = topology debt.
- ❌ **Slot naming = cosmetic**. Plugin namespace
  (`vendor/identifier` in registerPlugin name) determines
  unregister capability and ecosystem coexistence. Without
  proper naming, removal is impossible.
- ❌ **Additive = conflict-free**. Multiple fills targeting
  the same slot may visually compete. Coordinate with
  ecosystem (or scope to single-plugin slots) when possible.
- ❌ **Fills survive plugin deactivation cleanly**. If user
  customized state via fill UI, that state may persist after
  deactivation (UI gone, state stranded). Consider cleanup
  patterns.
- ❌ **Skipping accessibility in fill render**. Fills inherit
  the editor's accessibility framework but do NOT
  automatically meet WCAG. ARIA labels, focus management,
  keyboard navigation are fill-author responsibilities.

## RELATED

- `editor-customization.block-filters` — paired chunk (other
  governance modality). Both intercept authority but at
  different modalities (lifecycle vs topology). Together they
  surface "Authority Interception Surface" as candidate
  pattern with intra-context recurrence.
- `block-authoring.edit-save-components` — editor block UI is
  the topology slotfills inject into. Fills appear adjacent
  to (not replacing) BlockEdit-rendered output.
- `plugin-dev.security-boundaries` — slotfills expand attack
  surface (topology debt). Filters that mutate fill rendering
  cross security doctrine.
- `plugin-dev.capabilities-and-roles` — fill registration may
  be capability-gated. fill render functions should check
  current_user_can for sensitive UI / actions.
- `_meta.structural-patterns` — **constitutional law layer
  with first promotion event documented here**. Authority
  Interception Surface candidate promoted from Local to
  Recurring (intra-context).
- (planned) `editor-customization.editor-hooks` — third
  governance modality (reactive subscriptions). May test
  whether interception surface candidate manifests in a 3rd
  modality (lifecycle / topology / **reactive**).

## META

**editor-customization bounded context — second chunk; second
constitutional protocol deployment.**

**Doctrinal extension:**

> **Block filters proved authority interception exists;**
> **SlotFill proves that interception recurs through multiple**
> **governance modalities.**

**Final constitutional backbone:**

> **Block filters mutate lifecycle authority;**
> **SlotFill governs interface topology authority.**

### Constitutional Field Test (Table A — Universal Law Manifestation)

Standard law manifestation verification (per block-filters'
established format):

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 5 — Entity → Relationship Pivot** | Very Strong | Editor UI as named-slot relationship topology; fills as relationship edges; composition multiplexed | **Confirmed (4th-context manifestation, deepens recurrence beyond block-filters)** |
| **Law 4 — Arbitration Compiler** | Moderate | Multiple fills per slot composed in render order; slot resolution arbitrates participation | **Confirmed (slot-resolution variant of arbitration pattern)** |
| **Law 1 — Declaration ≠ Exposure** | Strong | Registration / rendering / visibility = 3 independent surfaces (slot existence, screen context, capability) | **Confirmed (3-form variant of Law 1)** |
| **Law 6 — Compiler ↔ Runtime Split** | Moderate-Strong | registerPlugin (registration time) ↔ slot rendering (runtime composition) | **Confirmed (registration-runtime split)** |
| **Law 2 — HTML Primacy** | Implicit | Fills render as React components → HTML; doctrine respected | **Confirmed (implicit doctrine adherence)** |
| **Law 3 — Authority Continuity** | Secondary | Slot/fill registration survives editor re-renders; UI authority continuity within editor session | **Partial** |

**Constitutional protocol verdict (laws): SUCCESS.** All
strongly-predicted laws (5, 4, 1, 6) manifested. Law 5 deepens
recurrence to 4-context confirmation level.

### Constitutional Field Test (Table B — Pattern Recurrence Verification)

Candidate law promotion verification (NEW table format
introduced for promotion event chunks):

| Candidate | Prior status | SlotFill manifestation | Promotion |
|---|---|---|---|
| **Authority Interception Surface** | Local (block-filters only) | Confirmed via topology-interception mode (different mechanism, same ontology) | **Promoted: Local → Recurring (intra-context)** |

**Promotion details:**
- Same structural core: mechanisms that intercept and reshape
  existing authority without owning it.
- Different governance modality: lifecycle interception
  (block filters) vs topology interception (SlotFill).
- Recurrence within single bounded context (editor-customization).
- Promotion qualifier: "(intra-context)" — KB-Wide promotion
  requires cross-bounded-context verification.

**Promotion path remaining:**
- Recurring (intra-context) → Recurring (cross-context):
  requires verification in admin-ui (notices, settings filters)
  / site-building (template hierarchy filters) / data-layer
  (entity filters) or similar mechanism in non-editor-
  customization context.
- Recurring (cross-context) → KB-Wide Law: requires audit
  verification.

### Structural-Patterns Governance Note

Two governance observations from this chunk's authoring:

1. **Spec refinement candidate** — The promotion path
   structural-patterns.md Section A documented (Local →
   Recurring → KB-Wide) may benefit from finer granularity:

   ```
   Proposed refined hierarchy:
      Local Pattern Surface
         ↓
      Recurring (intra-context)         ← FIRST PROMOTION HERE
         ↓
      Recurring (cross-context)
         ↓
      KB-Wide Law
   ```

   The "intra-context" qualifier preserves promotion
   discipline (recurrence is real but bounded). Cross-context
   recurrence is required for full Recurring status.

   **Recommended spec update**: Add intra/cross-context
   distinction to structural-patterns.md Section A. Defer to
   user direction.

2. **Subtype caution** — Current evidence suggests multiple
   interception modalities (lifecycle / topology), but
   subtype formalization remains premature pending broader
   recurrence. If editor-hooks chunk also manifests authority
   interception via reactive-subscription modality, three
   modalities (lifecycle / topology / reactive) may justify
   subtype consideration. Until then: surface, do not
   formalize.

3. **Wording discipline** — "candidate structural pattern"
   preferred over "candidate 7th law" — the latter overcommits
   to numbering before promotion path completes.

### KB-level framing extension

> **editor-customization is the first bounded context to not**
> **merely confirm existing constitutional laws, but to**
> **surface a plausible new governance pattern through repeated**
> **bounded-context-specific recurrence.**

This positions editor-customization as the **first
law-generation capable bounded context**. Block-authoring,
theme-config, style-engine, data-layer, interactivity,
plugin-dev all confirmed existing patterns; editor-customization
(via 2 chunks: block-filters + slotfills) generates a
candidate pattern internal to its scope.

This generation capability is significant: KB is no longer
purely descriptive (verifying patterns observed elsewhere) —
it can be **generative** (surfacing new candidate patterns
through structured chunk authoring).

### KB self-evaluation against spec criteria

- ✅ Accuracy — describes documented SlotFill API.
- ✅ Structural fit — completes editor-customization
  governance modality pair (lifecycle + topology); promotes
  Authority Interception Surface candidate with discipline.
- ✅ Reusability — uses authority ontology glossary
  (interception / topology / governance / arbitration /
  topology debt).
- ✅ Phase fit — editor-customization second chunk;
  references constitutional protocol from structural-patterns.
- ✅ Doctrine respect — HTML primacy implicit (React → HTML
  emission); declaration ≠ exposure explicitly invoked.

### Status: `stable`

SlotFill API + @wordpress/plugins are mature (WP 5.0+).
Verification-needed catalog covers behaviors (rendering
order, performance, scope semantics) but core API is settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line backbone

> **Block filters proved authority interception exists;**
> **SlotFill proves that interception recurs through multiple**
> **governance modalities.**

### Anticipated next chunks (priority)

1. **`editor-customization.editor-hooks`** — third governance
   modality (reactive subscriptions). Will test whether
   "Authority Interception Surface" candidate manifests in
   reactive modality. If yes: 3-modality candidate (lifecycle /
   topology / reactive) — significant for subtype consideration.

2. **Constitutional spec update**: structural-patterns Section A
   refinement (intra/cross-context distinction). Trigger:
   if editor-hooks confirms intra-context recurrence further,
   spec update is timely.

3. **Cross-context recurrence test**: enter another bounded
   context (admin-ui or site-building) and test whether
   authority interception manifests outside editor-customization.
   Promotion to Recurring (cross-context) depends on this
   verification.

4. **Other plugin-dev or additive contexts** as user direction
   determines.

Recommended next: **editor-customization.editor-hooks**
(closes editor-customization triad: lifecycle / topology /
reactive) → potentially triggers spec refinement +
3-modality consideration.

### Methodological note

> This chunk should not just document SlotFill — it should
> demonstrate that the KB can responsibly discover, promote,
> and govern new structural patterns without collapsing its
> own evidentiary discipline.

Successful execution (this chunk) demonstrates KB has
methodology to:
- Surface candidate patterns from chunk-level observation
- Promote candidates within explicit discipline (Local →
  Recurring intra-context; further promotion requires
  additional evidence)
- Refuse premature taxonomization (subtype split deferred
  pending evidence)
- Document governance reasoning explicitly
  (Structural-Patterns Governance Note)

The KB has matured into a **governable ontology system**.
