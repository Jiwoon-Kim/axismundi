---
rule_id: _meta.structural-patterns
domain: kb-meta
topic: constitutional-laws
field_cluster: ontology-governance
status: stable
scope: kb-wide
language: meta
sources:
  - internal: _meta.kb-audit-phase7 (verification artifact)
  - internal: _meta.dsl-spec (operating system)
  - internal: 64 substantive chunks (empirical basis)
related:
  - _meta.dsl-spec
  - _meta.kb-audit-phase7
---

# KB Structural Patterns — constitutional law layer

> The KB is no longer just documenting WordPress architecture —
> it is now **formalizing the constitutional laws** by which
> that architecture can be interpreted, extended, and predicted.

This document formalizes the six **KB-WIDE LAWS** verified in
the Phase 7 closure audit + the **6 constitutional doctrines**
elaborated through Phase 7.5 → 8.5 governance development +
the **Law sub-pattern architecture** introduced through Phase
8.13 → 8.14 hierarchy restructuring. Each law is a **predictive
architectural invariant** — not a theme, not a heuristic, not
a metaphor. Constitutional laws have predictive force: when a
new bounded context is entered, these laws are expected to
appear; when they don't, the discrepancy is itself an
ontological finding worth documenting.

Law sub-patterns (introduced Phase 8.14, KB Constitution v2)
are STRUCTURAL SPECIALIZATIONS within an existing law's
invariant. They preserve KB-Wide LAW count integrity (still 6)
while expanding Law-layer architectural sophistication. First
formalized: Law 3b — Cross-Runtime Authority Continuity Bridge.

**Analytical Tier (Section X; introduced Phase 8.19)** is
KB's first NON-CONSTITUTIONAL formalization tier — formal
analytical infrastructure structurally ADJACENT to (NOT
vertically integrated with) the constitutional hierarchy.
Observatory not Government. First formalized: Civilization
Archetypes historiographic analytical framework. KB
Constitution v2 stability preserved through this
infrastructure-additive (not layer-additive) expansion.

Doctrines elaborate operational mechanisms within / beyond
laws: paired operations (Doctrine 5), authority access
mediation (Doctrine 6), epistemic governance (Doctrine 3),
and others. Doctrines are constitutionally available for
chunk-level reference but do NOT inflate KB-Wide LAW count.

> **Constitutional structure (post-Phase-8.19; KB Constitution v2)**:
> - **6 KB-Wide Laws** (stable since Phase 7; 5 audit
>   refusals reinforce — including Phase 8.18 archetype
>   admissibility test)
> - **Law sub-patterns** (NEW v2 layer): 1 formalized (Law
>   3b — Cross-Runtime Authority Continuity Bridge; 5
>   sub-characters: 3b-static / 3b-asym / 3b-round / 3b-react /
>   3b-ident)
> - **6 Doctrines** (1: Multi-pattern bounded context /
>   2: Candidate structural complement / 3: Epistemic
>   Integrity / 4: Anticipated triad / 5: Arbitration ↔
>   Resolution Paired Operations / 6: Authority Access
>   Mediation)
> - **Doctrine 5 architectural variants**: 3 formalized
>   (5a Integrated / 5b Distributed / 5c Hybridized + 5c
>   sub-pattern Selection from Candidates)
> - **Doctrine 6 architectural variants**: 2 formalized
>   Phase 8.10 (6-HARD: 7 sub-elements 6a-6g / 6-SOFT: 2
>   sub-elements 6h-6i)
> - **Doctrine 6 sub-elements**: 9 (6a-6i; 18+ underlying
>   mechanism instances across 5 bounded contexts)
> - **3 Audit gate classes** (Phase 8.14): Standard (5
>   criteria) + Governance-intensive (7 criteria) + Law
>   Sub-pattern Gate (4 criteria L1-L4)
> - **Q11 5-outcome model** (Phase 8.14): Promote / Refuse /
>   Retain / Refine / Law sub-pattern formalization
>
> **Parallel infrastructure (Phase 8.19; structurally
> adjacent, NOT vertical)**:
> - **Section X — Analytical Tier**: Non-constitutional
>   formalization tier (Observatory not Government). 1
>   historiographic analytical framework formalized:
>   Civilization Archetypes (4 archetypes — Governance-heavy /
>   Security-heavy / Semantic-heavy / Computational-heavy).
>   V1-V4 predictive validation criteria for future
>   constitutional re-audit pathway.
>
> **Three civilizational functions formally separated
> (Phase 8.19)**:
> - Constitutional Governance (Sections A-D)
> - Constitutional Historiography (META + Phase epoch
>   documents)
> - Constitutional Sociology (Section X Analytical Tier)

> **These laws are not themes.**
> **They are predictive architectural invariants.**

This document is **prescriptive**, complementing two adjacent
KB infrastructure documents:

| document | role | character |
|---|---|---|
| `_meta.dsl-spec` | KB operating system | how chunks are structured |
| `_meta.kb-audit-phase7` | verification artifact | DO patterns exist? (descriptive) |
| `_meta.structural-patterns` (this) | constitutional law layer | how SHOULD KB use these patterns? (prescriptive) |

The dsl-spec defines structure; the audit verifies patterns;
this document formalizes the patterns into reusable constitutional
laws that future chunk authoring can invoke.

---

## SECTION A — Law classification ontology

> **Phase 7.5 Constitutional Refinement Patch (2026-05-09):**
> The original 3-tier ladder (Local → Recurring → KB-Wide)
> proved insufficient for honest adjudication after the
> editor-customization triad (block-filters / slotfills /
> editor-hooks) generated:
> (a) a candidate observed only at first observation (Surfaced
> tier needed);
> (b) recurrence within a single bounded context but not yet
> across contexts (intra-context vs cross-context distinction
> needed).
>
> The expanded **5-tier ladder** below replaces the original
> 3-tier model.

Patterns observed in KB authoring fall into 5 tiers based on
recurrence breadth + scope:

| tier | criterion | KB role |
|---|---|---|
| **Surfaced** | first observation in a single chunk | candidate noted; "surfaced, not constitutionalized" |
| **Local Pattern** | observed within ONE bounded context (1+ chunk) | useful for that context's chunks; not generalized |
| **Recurring (intra-context)** | observed across MULTIPLE chunks within the SAME bounded context | candidate strengthened; pending cross-context verification |
| **Recurring (cross-context)** | observed in 2+ bounded contexts | strong candidate for constitutional law |
| **KB-WIDE LAW** | observed across multiple bounded contexts AND verified through audit | formalized constitutional invariant; predictive force |

**Promotion criteria** (Surfaced → Local → Recurring intra → Recurring cross → KB-Wide):
- Surfaced → Local: requires confirmation of the pattern as a
  pattern (more than incidental observation); typically
  acknowledged in the same or next chunk.
- Local → Recurring (intra-context): requires explicit
  recurrence across multiple chunks within the same bounded
  context (e.g., 2 mechanisms within editor-customization).
- Recurring (intra-context) → Recurring (cross-context):
  requires explicit recurrence in a 2nd bounded context
  (NOT just "similar-sounding" usage; actual ontological
  match).
- Recurring (cross-context) → KB-Wide: requires audit
  verification that the pattern is not coincidence, applies
  across multiple authority domains, and has explanatory
  power beyond coincidence.

**Demotion criteria** (KB-Wide → Recurring → Local → Surfaced):
- KB-Wide → Recurring (cross-context): subsequent audit
  reveals the pattern was misidentified or applies to fewer
  contexts than claimed.
- Recurring (cross-context) → Recurring (intra-context):
  cross-context evidence proves to be coincidental rather
  than ontological.
- Recurring (intra-context) → Local: intra-context evidence
  proves insufficient.
- Local → Surfaced: pattern reverts to single-observation
  status.

**The 6 KB-Wide laws** below remain at KB-Wide tier as of the
Phase 7 closure audit (2026-05-09). This refinement patch
adds finer granularity for candidate patterns BELOW KB-Wide
tier, which is where most active adjudication occurs.

> **Phase 7.6 Patch Note (2026-05-09)** — Promotion paths
> may arise via TWO methodologically valid routes:
>
> 1. **Forward density**: a candidate is explicitly named in
>    chunk N, then recurs in subsequent chunks (N+1, N+2)
>    with structurally clear manifestation.
> 2. **Retroactive verification**: a candidate named in chunk
>    N is retroactively verified as latently present in
>    earlier chunks (1..N-1) via RETROACTIVE REFRAMING work.
>
> Both routes produce evidence-based promotion when applied
> with epistemic discipline. Resolution Surface candidate
> reached Recurring (cross-context) status via retroactive
> verification (cascade-aggregation + capabilities-and-roles
> retros, 2026-05-09). See META Phase 7.6 chronology +
> Section D Q9 diagnostic for methodology.

> **Phase 7.7 Patch Note (2026-05-09)** — Evidence has TWO
> independent dimensions:
>
> - **Depth (intra-context recurrence)**: pattern manifests
>   across MULTIPLE chunks within the SAME bounded context.
>   Tier ladder positions reflect depth.
> - **Breadth (cross-context PRESENCE)**: pattern manifests in
>   2+ bounded contexts even with single-chunk evidence in
>   each. Cross-context PRESENCE is a STATUS NOTATION
>   orthogonal to depth tiers.
>
> A candidate may have:
> - Local + cross-context PRESENCE (depth 1; breadth 2+)
> - Recurring (intra-context) + cross-context PRESENCE
>   (depth ≥2 in one context; breadth 2+)
> - Recurring (cross-context) — when both depth ≥2 in
>   2+ bounded contexts
>
> **Cross-context PRESENCE is NOT a separate ladder tier.**
> It is an orthogonal annotation. Treating it as a tier
> linearizes orthogonal evidence dimensions and obscures
> structural distinction between breadth and depth.
>
> Examples (as of 2026-05-09):
> - Authority Mediation Surface: Recurring (intra-context,
>   admin-ui) + cross-context PRESENCE (editor-customization
>   single-instance presence)
> - Resolution Surface: Recurring (cross-context) — depth ≥2
>   in 2 bounded contexts (site-building + block-authoring)
>
> The notation distinguishes "breadth-only" from "breadth +
> depth" evidence states cleanly.

### Pattern verdict taxonomy

(NEW — Phase 7.5 patch)

When a chunk evaluates a candidate pattern manifestation, the
verdict falls into one of 5 classes:

| verdict | meaning |
|---|---|
| **Confirmed** | pattern manifests in this chunk's domain matching the candidate's structural core |
| **Divergent** | the chunk's domain does NOT fit the candidate; surfaces ontological branching rather than weakening |
| **Hybridized** | pattern manifests in some usage modes but not others; chunk's domain is multi-modal |
| **Surfaced** | NEW candidate pattern emerges from the chunk's analysis (1st observation) |
| **Deferred** | evidence insufficient for verdict; verification deferred to future chunks |

The taxonomy replaces the earlier "Confirmed / Partial /
Deferred" used in block-filters' Constitutional Field Test.
"Partial" was too blunt — `editor-hooks` proved that a
candidate can BRANCH ontologically (Divergent) or
HYBRIDIZE (Hybridized), not merely weaken (Partial).

Both Tables A (Universal Law Manifestation) and Table B
(Pattern Recurrence/Divergence Verification) in Constitutional
Field Test should use this 5-class taxonomy.

---

## SECTION B — Six verified KB-Wide Laws

Laws are grouped into 3 categories by character:

| category | laws |
|---|---|
| **Governance / Doctrine** | Declaration ≠ Exposure / HTML Primacy |
| **Structural** | Entity → Relationship Pivot / Compiler ↔ Runtime Split |
| **Operational** | Authority Continuity / Arbitration Compiler |

Each law is documented with:
- Definition + core question
- First explicit emergence in KB
- Verified bounded contexts
- Known variants
- Anti-confusions
- Predictive use heuristic

---

### Law 1 — Declaration ≠ Exposure

**Category**: Governance / Doctrine
**Status**: KB-WIDE LAW (most-recurring; 8+ verified instances)

#### Definition

> Declaring a capability, surface, or authority does NOT
> automatically expose it to consumers, users, or runtime
> systems. Declaration creates the SURFACE; exposure is
> governed independently.

#### Core question

> Does this mechanism have separate surfaces for "X exists" vs
> "X is selectable / accessible / visible / reachable"?

#### First explicit emergence

`block-authoring.block-json.supports-field` (capability
exposure pattern). Formalized as ontology axis in
`theme-config.json-settings-color` (presets exist as registry
vs custom/default gates control selection).

#### Verified bounded contexts

| context | instance |
|---|---|
| block-authoring | supports flags + appearanceTools mediation; bindings (declared but exposure depends on consumer) |
| theme-config | presets (registry) vs custom/default (exposure gates); customTemplates postTypes gating |
| style-engine | selectors (declaration) vs generated-selectors (runtime synthesis exposure) |
| data-layer | meta registration (declaration) vs show_in_rest (transport exposure) vs auth_callback (access exposure) |
| interactivity | data-wp-interactive declaration vs hydration scope vs runtime store registration |
| plugin-dev | every federation API has declaration ≠ exposure ≠ trust separation |

#### Known variants

- Two-surface form: declaration / exposure (most common)
- Three-surface form: declaration / exposure / trust
  (plugin-dev REST routes, register-meta)
- Multi-dimensional form: CPT exposure flags
  (public / publicly_queryable / show_ui / show_in_menu /
  show_in_rest / show_in_nav_menus / etc.)

#### Anti-confusions

- ❌ Hidden UI = denied access (UI hide is hint; enforcement
  is server-side capability check)
- ❌ Registered = secured (registration declares; security
  requires explicit doctrine implementation)
- ❌ Public = safe (public_queryable is exposure dimension,
  not security guarantee)

#### Predictive use heuristic

> When entering a new API or registration mechanism, ASK
> EXPLICITLY:
> 1. What is the declaration surface? (which call creates the
>    capability)
> 2. What is the exposure surface? (which gate determines
>    selectability/visibility)
> 3. What is the trust/permission surface? (which check governs
>    actual access)
>
> If only one surface is identified, audit assumption — most
> APIs in WordPress have at least two.

---

### Law 2 — HTML Primacy

**Category**: Governance / Doctrine
**Status**: KB-WIDE LAW (formalized as DSL spec doctrine)

#### Definition

> Gutenberg's architectural sophistication does NOT abandon
> HTML primacy. HTML's role expanded from "output format" to
> **universal continuity substrate** — but it remained
> foundational. WordPress is structurally distinct from SPA
> frameworks that treat HTML as intermediate render output
> subordinate to virtual trees.

#### Core question

> Does this mechanism work WITH HTML as primary reality, or
> AGAINST it (treating HTML as ephemeral intermediate
> representation)?

#### First explicit emergence

`block.markup-representation` chunk (block delimiters as
HTML-comment-based IR). Formalized in retroactive section
(post-Phase-7-capstone). Doctrine stated in `_meta.dsl-spec`.

#### Verified bounded contexts

| context | instance |
|---|---|
| block-authoring | block delimiters in HTML comments; wrapper-attributes as authority transport surface |
| style-engine | CSS attaches to HTML elements (NOT virtual trees) |
| data-layer | render output is HTML; entity values project through HTML |
| interactivity | `data-wp-*` directives live in HTML attributes; runtime ATTACHES to existing DOM (not VDOM ownership) |
| plugin-dev | render_callback emits HTML; sources project values into HTML attribute consumption |

#### Known variants

- Server-rendered HTML primacy (most common)
- Block markup HTML primacy (delimiter + body composition)
- Reactive HTML primacy (Interactivity API directives in HTML
  rather than templated render output)

#### Anti-confusions

- ❌ HTML primacy = "no JavaScript" (it's ABOUT HTML role,
  not JS rejection)
- ❌ HTML primacy = anti-React (React is used in editor; the
  doctrine governs FRONTEND runtime architecture)
- ❌ Server-side rendering = HTML primacy (SSR is one expression
  of the doctrine; not the only one)

#### Predictive use heuristic

> When designing a runtime mechanism in WordPress (chunk or
> code), ASK:
> 1. Does the mechanism preserve HTML as primary deliverable?
> 2. Does the mechanism enhance HTML, or replace HTML with
>    a virtual representation?
> 3. Does the mechanism work without JS (degrades gracefully)?
>
> Mechanisms that ONLY work with JS active and OWN the DOM are
> HTML-primacy violations — flag and reconsider.

---

### Law 3 — Authority Continuity

**Category**: Operational
**Status**: KB-WIDE LAW (formalized in DSL spec glossary; capstone
in interactivity.hydration)

#### Definition

> Authority remains identifiable as it crosses execution,
> serialization, network, and persistence boundaries. Each
> boundary is a continuity point requiring explicit
> reconciliation; authority does NOT automatically survive
> boundary crossing without governance.

#### Core question

> When authority crosses this boundary, what mechanism
> preserves identity / state / governance / trust across the
> crossing?

#### First explicit emergence

`block.dynamic-rendering` (server → frontend continuity via
render_callback). Formalized in `interactivity.hydration` as
Phase 7 capstone.

#### Verified bounded contexts

| context | continuity boundary |
|---|---|
| block-authoring | save() → parser → editor (round-trip continuity); render_callback → frontend (server-to-frontend) |
| style-engine | compiler → browser cascade VM (compiler → runtime continuity) |
| data-layer | edit buffer → REST → DB → reconciliation (multi-stage continuity) |
| interactivity | server-rendered HTML → client runtime hydration (compiler-runtime linkage) |
| plugin-dev | bindings + meta + REST traverse same authority pipeline |

#### Known variants

- Round-trip continuity (parser ↔ serializer)
- Compiler → runtime continuity (style-engine, interactivity)
- Persistence continuity (data-layer reconciliation)
- Server → client continuity (hydration, dynamic rendering)
- Process continuity (REST request boundaries)

#### Anti-confusions

- ❌ Continuity = real-time sync (continuity tolerates delay;
  reconciliation handles divergence)
- ❌ Continuity = no failure (continuity DESIGNS for boundary
  failure modes — they are structural, not exceptional)
- ❌ Continuity = stateless (continuity often requires explicit
  state preservation mechanism)

#### Predictive use heuristic

> When a mechanism crosses any execution boundary in WordPress
> (PHP → network / DB → memory / server → browser / sync →
> async), ASK:
> 1. What state crosses the boundary?
> 2. What identity is preserved?
> 3. How is conflict / divergence reconciled?
> 4. What happens on failure to cross?
>
> Mechanisms without explicit answers to these questions are
> continuity vulnerabilities.

#### Law 3 sub-pattern architecture (Phase 8.14 patch — NEW constitutional layer)

> **Phase 8.14 Constitutional Hierarchy Sophistication Patch
> (2026-05-10):** Phase 8.13 audit (Bridge Pattern KB-Wide
> LAW promotion verification) refused KB-Wide LAW promotion
> (100% Law 3 dependence) AND confirmed Law 3 sub-pattern
> formalization (1st LAW-tier sub-pattern in KB). Phase 8.14
> introduces **NEW constitutional layer**: Law sub-patterns,
> distinct from Doctrine sub-patterns (Doctrine 5c) by
> operating at LAW tier rather than DOCTRINE tier.

> **Critical disclaimer**: Law sub-patterns are NOT new laws.
> Law sub-patterns are STRUCTURAL SPECIALIZATIONS within an
> existing law's invariant. They preserve KB-Wide LAW count
> integrity (still 6) while expanding Law-layer architectural
> sophistication.

##### Law 3b — Cross-Runtime Authority Continuity Bridge (1st LAW-tier sub-pattern)

> **Core formulation**:
> Authority preserved across PHP↔JS runtime boundary via
> **PHP-initiated → HTML/transport-mediated → JS-consumed**
> directional bridge mechanism. Specialization of Law 3
> Authority Continuity invariant operating specifically at
> cross-runtime boundary class.

**Constitutional positioning**:

| element | scope |
|---|---|
| **Law 3 (general invariant)** | Authority Continuity across ALL boundary types (time / space / process / runtime / etc.) |
| **Law 3b (NEW sub-pattern)** | Authority Continuity specialized to PHP↔JS runtime boundary with PHP-initiated direction + HTML/transport medium |

**Verified instances** (5 instances × 4 bounded contexts):

| chunk | bounded context | sub-character |
|---|---|---|
| i18n.script-translations | i18n | static-data Bridge |
| i18n.locale-switching | i18n | asymmetric Bridge |
| admin-ui.notices | admin-ui | round-trip Bridge |
| interactivity.directive-protocol (Q9 retro) | interactivity | reactive-subscription Bridge |
| block-authoring.register-client-js (Q9 retro) | block-authoring | identity-binding Bridge |

**Architectural ubiquity**: 4 character categories
(Semantic substrate + Governance modulation +
Computational-architectural + Schema authority).

**5 sub-characters** (registry under Law 3b):

###### 3b-static — Static-data Bridge sub-character

One-time runtime data transfer (e.g., locale_data
transmission via wp_set_script_translations).

###### 3b-asym — Asymmetric Bridge sub-character

Static-only coverage with re-injection requirement during
context mutation (e.g., locale-switching cross-runtime
re-dispatch).

###### 3b-round — Round-trip Bridge sub-character

Event-driven persistence cycle (PHP→JS→AJAX→PHP, e.g.,
admin notice persistent dismissal).

###### 3b-react — Reactive-subscription Bridge sub-character

Continuous reactive topology establishment (e.g., directive
declarations parsed by interactivity runtime).

###### 3b-ident — Identity-binding Bridge sub-character

Block name + capability declarations binding across PHP
register_block_type and JS registerBlockType (e.g., block
registration dual binding).

##### Law 3 sub-pattern naming convention

Law sub-patterns use **{law-number}{letter}** convention:
- 3b = Law 3 second formalized sub-pattern
- (3a reserved for general Law 3 manifestation OR future
  formalization)
- Future Law 3 sub-patterns: 3c, 3d, etc.

Other law sub-pattern slots (currently NOT formalized;
PRESERVED for future):
- Law 1b, Law 1c, etc. (Declaration ≠ Exposure
  specializations)
- Law 6b, Law 6c, etc. (Compiler ↔ Runtime Split
  specializations)
- Etc.

> **Discipline**: Per Phase 8.14 conservative principle,
> ONLY Law 3 currently has formal sub-pattern architecture.
> v2 = law-layer architecture INTRODUCED, not universally
> populated. Other laws may eventually acquire sub-pattern
> architecture if evidence accumulates AND audit confirms.

##### Law sub-pattern vs Doctrine sub-pattern distinction

| dimension | Law sub-pattern (Law 3b) | Doctrine sub-pattern (Doctrine 5c) |
|---|---|---|
| Tier | LAW (top-tier invariant specialization) | DOCTRINE (operational pattern within doctrine variant) |
| Promotion threshold | 100% parent-law dependence + strong cross-context breadth + structural specificity | 3+ instances within doctrine variant |
| Formalization context | Audit-driven (Phase 8.13 precedent) | Spec patch (Phase 7.7 precedent) |
| Constitutional weight | Architectural invariant specialization | Operational pattern recurrence |
| Examples | Law 3b (Cross-Runtime Bridge) | Selection from Candidates (within Doctrine 5 Hybridized) |

> **Critical methodological discipline**: Law sub-patterns
> are RARER than doctrine sub-patterns AND require STRICTER
> formalization criteria. Law sub-pattern formalization MUST
> survive audit-tier scrutiny; doctrine sub-pattern
> formalization may proceed via spec patch.

#### Why Law 3b matters

Without Law 3b formalization:
- Bridge Pattern remains "candidate" forever despite 5/5
  audit gate met + 4/4 evaluation dimensions support
- No constitutional vocabulary distinguishes "Law 3 in
  general" from "Law 3 specialized for cross-runtime"
- Cross-runtime architectural patterns lack precise
  constitutional reference
- Law-layer architecture remains flat (no internal
  sophistication possible)

With Law 3b formalization:
- Bridge Pattern receives constitutional home (sub-pattern
  tier)
- Cross-runtime architectural reasoning gains precise
  vocabulary
- Law-layer architecture acquires sophistication potential
- KB Constitution v2 trigger met (hierarchy restructuring,
  not law expansion)
- Future Law 3 sub-pattern formalizations (HMAC-binding,
  stack-disciplined, etc.) become possible following Law 3b
  precedent

#### Law 3b operational vocabulary

Future chunks reasoning through Law 3b:
- Identify Bridge sub-character (3b-static / 3b-asym /
  3b-round / 3b-react / 3b-ident)
- Reference Law 3b explicitly when cross-runtime PHP↔JS
  authority preservation is structural concern
- Distinguish Law 3 (general) vs Law 3b (cross-runtime
  bridge specialization) when boundary type matters

#### Status (post-Phase-8.14)

- **Law 3b** = formalized constitutional law sub-pattern
  (Phase 8.14 patch); first LAW-tier sub-pattern in KB
- **5 sub-characters (3b-static / 3b-asym / 3b-round /
  3b-react / 3b-ident)** = documented Bridge instances with
  chunk-level manifestation evidence
- **Constitutional layer**: NEW (Law sub-patterns introduced
  as architectural layer between KB-Wide Laws and Doctrines)
- **KB Constitution v2 trigger**: MET via hierarchy
  restructuring (NEW Law-tier sub-pattern layer creation)

> **Phase 8.14 introduces NEW constitutional layer (Law
> sub-patterns) without expanding KB-Wide LAW count. Law
> count remains 6. Doctrine count remains 6. Law sub-pattern
> count: 1 (Law 3b). Constitutional sophistication advances
> through HIERARCHY RESTRUCTURING.**

---

### Law 4 — Arbitration Compiler

**Category**: Operational
**Status**: KB-WIDE LAW (broader recurrence than initially
recognized; 4+ instances)

#### Definition

> Wherever multiple authority claims must be reconciled into a
> single decision, an **arbitration compiler** emerges. The
> compiler transforms multi-source authority claims (registry
> entries, declarations, runtime contributions) into a resolved
> output via deterministic precedence + composition rules.

#### Core question

> Does this mechanism reconcile multiple authority sources
> into a single decision? If so, what is the compilation
> algorithm and where is it documented?

#### First explicit emergence

`style-engine.cascade-aggregation` (CSS authority arbitration).
Recurrence formally recognized in `plugin-dev.security-boundaries`
META; explicitly documented in `plugin-dev.capabilities-and-roles`
(map_meta_cap as adjudication compiler).

#### Verified bounded contexts

| context | arbitration compiler instance |
|---|---|
| style-engine | cascade-aggregation: competing CSS authorities → cascade graph |
| plugin-dev | map_meta_cap: contextual capability requests → primitive checks + ownership |
| data-layer (implicit) | persistence reconciliation: edit buffer + persisted + concurrent updates → reconciled state |
| interactivity (implicit) | hydration: server-known + client-live authority → unified runtime |

#### Known variants

- **Cascade-style** (style-engine): precedence by source order
  + selector specificity + scope
- **Adjudication-style** (plugin-dev capabilities): meta caps
  → primitive checks + contextual conditions
- **Reconciliation-style** (data-layer): conflict resolution
  with last-write-wins default
- **Continuity-style** (interactivity): server seed + client
  takeover with negotiated handoff

The four variants share the underlying pattern: **multi-source
authority → deterministic decision via documented compilation
rules**.

#### Anti-confusions

- ❌ Arbitration compiler = "voting" (it's deterministic, not
  democratic)
- ❌ Arbitration = conflict resolution at runtime (arbitration
  designs the decision logic; runtime applies it)
- ❌ Compiler = build-time only (arbitration compilation often
  runs at request time)

#### Predictive use heuristic

> When a mechanism receives authority claims from multiple
> sources, ASK:
> 1. Are there multiple sources contributing authority claims?
> 2. What deterministic logic produces the single decision?
> 3. Is the decision logic documented (algorithm + precedence
>    rules)?
> 4. Where can authors observe / influence the decision?
>
> Mechanisms with multiple sources but undocumented arbitration
> are unstable — surface the compilation logic explicitly.

---

### Law 5 — Entity → Relationship Pivot

**Category**: Structural
**Status**: KB-WIDE LAW (verified in 2 bounded contexts +
spec; anticipated in more)

#### Definition

> WordPress's ontology evolution recurrently shifts from
> ENTITY-CENTRIC perspectives ("what kinds of things exist?")
> to RELATIONSHIP-CENTRIC perspectives ("how do things relate
> / connect / compose?"). The pivot is structural — when a
> bounded context matures sufficiently, relationship-centric
> chunks become necessary alongside entity-centric ones.

#### Core question

> Is the current chunk / mechanism describing entities (kinds
> of things) or relationships (how things connect)? Is the
> bounded context likely to need both?

#### First explicit emergence

`style-engine.generated-selectors` (entity-centric block
ontology → relationship-centric runtime topology synthesis).
Formalized in `plugin-dev.register-taxonomy` META as
KB-pattern recurrence.

#### Verified bounded contexts

| context | entity-centric | relationship-centric |
|---|---|---|
| style-engine | block instance / preset / variable | generated selectors / cascade graph / topology |
| plugin-dev | CPT (subject species) | shared taxonomies / cross-entity semantic links |

#### Anticipated bounded contexts (PREDICTIVE FRONTIER)

| context | predicted entity-centric chunks | predicted relationship-centric chunks |
|---|---|---|
| editor-customization | block tree / component | block hooks / filter relationships / slotfill compositions |
| site-building | templates / patterns / parts | template composition graph / inclusion topology |
| interactivity | per-namespace stores | cross-store coordination / store dependencies |
| data-layer (extension) | individual entities | entity relationships / graph queries |

#### Known variants

- **Topology variant** (style-engine): blocks → spatial /
  cascade relationships
- **Federation variant** (plugin-dev): subjects → semantic
  groupings (taxonomies)
- **Composition variant** (anticipated site-building):
  templates → composition graphs

#### Anti-confusions

- ❌ Entity-centric = "objects only" (entities can have
  relationships AS attributes)
- ❌ Relationship-centric = "graph database thinking"
  (it's about ontological framing, not implementation choice)
- ❌ Pivot = chronological sequence (a bounded context may
  need both perspectives concurrently from the start)

#### Predictive use heuristic

> When entering a new bounded context or designing a chunk,
> ASK:
> 1. Is this chunk describing kinds of things (entity-centric)?
> 2. Is this chunk describing how things relate
>    (relationship-centric)?
> 3. Does the bounded context have BOTH perspectives covered?
>
> A bounded context with only entity-centric chunks is
> incomplete — relationship-centric chunks reveal the system's
> coordination architecture.

---

### Law 6 — Compiler ↔ Runtime Split

**Category**: Structural
**Status**: KB-WIDE LAW (documented in directive-protocol META
symmetry table; spec glossary)

#### Definition

> WordPress consistently structures its operational systems as
> **compiler/runtime splits**: declarative source surfaces are
> compiled into intermediate representations that runtime VMs
> (browser CSS engine, Interactivity runtime, PHP rendering
> pipeline) interpret at execution time. The split is structural,
> not implementation incidental.

#### Core question

> Does this mechanism have a compile-time stage that produces
> output for a runtime stage? Where is the compiler/runtime
> boundary?

#### First explicit emergence

`style-engine.preset-materialization` (compiler ↔ browser CSS
runtime split). Formalized as 3-runtime symmetry in
`interactivity.directive-protocol` META.

#### Verified bounded contexts

| layer | compiler | linker / loader | runtime VM |
|---|---|---|---|
| **CSS** | style-engine (variables, selectors, materialization, aggregation) | (browser CSS ingestion) | browser CSS engine |
| **Reactive JS** | server-side block render + Interactivity processor | hydration | @wordpress/interactivity client runtime |
| **PHP rendering** | render_callback / template hierarchy | (PHP → HTML emission) | (browser parses HTML) |

#### Known variants

- **Static compilation** (style-engine output baked into
  emitted CSS at request time)
- **Runtime compilation** (Interactivity directive scanning at
  hydration)
- **Hybrid compilation** (server pre-evaluates directives;
  client completes hydration)

#### Anti-confusions

- ❌ Compiler = build-time tool (in WordPress, compilers often
  run at request time, not build time)
- ❌ Runtime = client-side only (runtime can be PHP, JS,
  browser CSS engine, all "runtimes" relative to compilation)
- ❌ Compiler/runtime split = fancy framework concept (it's
  observable in WordPress core architecture for at least 3
  systems)

#### Predictive use heuristic

> When a mechanism has both a "declared form" and an "executed
> form," ASK:
> 1. What is the declared form (source surface)?
> 2. What compilation produces the intermediate?
> 3. What runtime executes the intermediate?
> 4. Where is the linker/loader between them?
>
> If a mechanism's "declared form = executed form" without
> intermediate, it likely has compiler/runtime structure
> hidden in the runtime side (e.g., direct DOM manipulation
> still has the browser DOM engine as runtime VM).

---

## SECTION C — Pattern interactions

Constitutional laws do NOT operate in isolation. Real KB
chunks frequently invoke MULTIPLE laws simultaneously. The
interactions are themselves structural patterns worth
documenting.

### Interaction 1 — Declaration ≠ Exposure × Security boundaries

**Composition**: governed declaration surfaces

When a mechanism has both declaration and exposure surfaces,
SECURITY governs the exposure separately from the declaration.
plugin-dev embodies this:
- bindings source = declared (origin trust); exposure separately
  governed by who registers
- meta = declared via register_meta; exposure governed by
  show_in_rest + auth_callback
- REST route = declared via register_rest_route; exposure
  governed by permission_callback + show_in_index

The compositional rule: **declaring authority does NOT
authorize it; exposure surfaces have separate governance**.

### Interaction 2 — Entity → Relationship × Arbitration Compiler

**Composition**: topology-scale authority systems

When relationship-centric ontology emerges (Law 5), the
relationships themselves often need arbitration (Law 4):
- style-engine: relationship-centric cascade graph requires
  cascade-aggregation arbitration compiler
- plugin-dev: shared taxonomies (relationship-centric) need
  capability arbitration via map_meta_cap

The compositional rule: **relationship-centric ontology
typically requires arbitration compilation when relationships
carry conflicting authority claims**.

### Interaction 3 — HTML Primacy × Authority Continuity

**Composition**: HTML as continuity substrate

HTML primacy (Law 2) and authority continuity (Law 3) compose
explicitly:
- HTML is the medium across boundaries (PHP → network →
  browser)
- Authority continuity is preserved THROUGH HTML
  (block delimiters, directive attributes, wrapper classes
  all carry continuity across crossings)
- markup-representation retro framed HTML as "operational
  substrate of distributed authority continuity"

The compositional rule: **HTML primacy is the carrier
mechanism enabling authority continuity across WordPress
execution boundaries**.

### Interaction 4 — Compiler ↔ Runtime Split × Authority Continuity

**Composition**: compiler-runtime authority handoff

The compiler/runtime split (Law 6) requires authority
continuity (Law 3) at the linker boundary:
- style-engine compiler emits CSS; browser CSS engine consumes
  (continuity at stylesheet ingestion)
- Interactivity processor emits directive HTML; client runtime
  hydrates (continuity at hydration boundary)
- render_callback emits HTML; browser parses (continuity at
  network transport)

The compositional rule: **every compiler/runtime split has a
linker/loader stage that requires explicit authority continuity
mechanism**.

### Interaction 5 — Declaration ≠ Exposure × Entity → Relationship

**Composition**: relational governance

Multi-dimensional exposure (Law 1) often expresses different
aspects of relationship topology (Law 5):
- CPT public family flags = entity exposure dimensions
- Taxonomy capability schema = entity-relationship governance
  separation (manage_terms vs assign_terms — declaration of
  classification system separated from declaration of
  attachment)

The compositional rule: **relationship-centric mechanisms
often require multi-dimensional exposure governance because
relationships have multiple visibility/access dimensions
beyond entity visibility**.

---

## SECTION C.5 — Constitutional doctrines (Phase 7.5 patch)

> **Phase 7.5 Constitutional Refinement Patch (2026-05-09):**
> The editor-customization triad surfaced phenomena requiring
> explicit constitutional doctrine beyond the 6 laws. Three
> doctrines added here address governance situations the
> original spec under-specified.

### Doctrine 1 — Multi-pattern bounded context

> **A bounded context may simultaneously host pattern**
> **recurrence, divergence, overlap, and complementarity.**
> **Bounded contexts are not required to be ontologically**
> **monothematic.**

**Origin**: editor-customization (block-filters / slotfills /
editor-hooks triad).

**Operationally**: editor-customization simultaneously hosts:
- Pattern recurrence (Authority Interception Surface across
  block-filters + slotfills)
- Pattern divergence (Authority Mediation Surface diverging
  from interception in editor-hooks direct usage)
- Pattern overlap (Federation pattern from plugin-dev
  recurring inside editor-customization via createReduxStore)

**Implication for chunk authoring**: when documenting a
bounded context, do NOT force unification when evidence shows
heterogeneity. Multi-pattern character is a legitimate
finding, not a documentation gap.

**Predictive use**: when entering an under-explored bounded
context, REMAIN OPEN to multi-pattern character. Force-fitting
single-pattern frame produces ontology drift.

### Doctrine 2 — Candidate structural complement

> **A candidate pattern that emerges as structurally distinct**
> **from existing candidates should be treated as a**
> **STRUCTURAL COMPLEMENT, not as a competing law.**
> **Marked: "surfaced, not constitutionalized."**

**Origin**: editor-hooks chunk (Authority Mediation Surface
surfaced as distinct from Authority Interception Surface).

**Operationally**:
- New candidate at Surfaced tier does NOT compete with existing
  Recurring / KB-Wide patterns.
- New candidate may eventually become a complementary structural
  governance class (e.g., the anticipated
  Interception/Mediation/Federation triad).
- "Surfaced, not constitutionalized" is the correct governance
  language for a 1st-observation candidate.

**Implication for chunk authoring**: when a chunk surfaces a
new candidate pattern:
1. Document it explicitly with "Surfaced" status.
2. DO NOT promote it to Local in the same chunk (premature).
3. DO NOT frame it as competing with existing laws.
4. DO mark verification path for promotion.

**Predictive use**: future chunks may test whether the
Surfaced candidate manifests in 2nd context. Successful
recurrence promotes Surfaced → Local → Recurring (intra-
context).

### Doctrine 3 — Epistemic Integrity

> **Constitutional coherence requires willingness to preserve**
> **divergence when evidence refuses simplification.**

**Origin**: editor-hooks chunk's hybrid finding (force-fitting
hooks into Authority Interception Surface was possible but
would have obscured architecture).

**Operationally**:
- KB methodology prioritizes accurate ontology over tidy
  pattern unification.
- When candidate evaluations produce mixed results
  (Hybridized verdict), document the heterogeneity rather
  than smooth it over.
- "Constitutional integrity > pattern preservation."

**Implication for chunk authoring**:
- Honest divergence findings are first-class results, not
  failures.
- When evidence supports multi-modal manifestation
  (Confirmed for some modes + Divergent for others), the
  appropriate verdict is Hybridized, not forced Confirmed
  or forced Divergent.
- Anti-doctrine flag: chunks that produce suspiciously clean
  unification despite mixed evidence may be force-fitting.
  Audit for over-simplification.

**Predictive use**: as more bounded contexts are entered,
expect honest divergence findings. The KB matures by
adjudicating well, not by predicting universally.

### Doctrine 4 — Anticipated constitutional architecture (Predictive Candidate)

> **Patterns and patterns may eventually compose into**
> **predictive candidate architectures — frameworks of**
> **mutually-related governance classes that have NOT yet**
> **been constitutionalized as KB-Wide laws.**

**Currently anticipated** (based on editor-hooks adjudication):

> **Interception / Mediation / Federation** triad —
> three potentially-parallel governance classes:
> - **Interception** = authority reshaping (Authority
>   Interception Surface candidate, Recurring intra-context)
> - **Mediation** = authority access choreography (Authority
>   Mediation Surface candidate, Surfaced)
> - **Federation** = authority origination (KB-Wide via
>   plugin-dev; cross-context recurrence in editor-
>   customization noted)

**Status**: **anticipated, not constitutionalized.**

This triad is NOT a law. It is a predictive framework
indicating where future audit may find structural
relationships between governance classes. Verification
path:
- Authority Mediation Surface promotion to Recurring
  (intra-context) requires recurrence within editor-
  customization (e.g., editor preferences API as another
  mediation instance).
- Promotion to Recurring (cross-context) requires
  manifestation in 2nd bounded context (data-layer
  selectors are mediation candidates; admin-ui form data
  hooks similarly).
- Triad formalization requires all three classes to reach
  cross-context recurrence + audit verification.

**Implication for chunk authoring**: chunks may NOTICE
manifestations consistent with the anticipated triad;
chunks may NOT promote the triad to constitutional
status. Predictive candidate architectures are spec-level
acknowledgments of potential structure, not yet structure
itself.

### Doctrine 5 — Arbitration ↔ Resolution Paired Operations (Phase 7.6 patch)

> **Phase 7.6 Constitutional Expansion (2026-05-09):**
> After 2 retroactive verifications (cascade-aggregation +
> capabilities-and-roles, both 2026-05-09) confirmed the
> Arbitration ↔ Resolution paired-operations pattern across
> 3 bounded contexts with architectural diversity, the
> doctrine warrants formalization. This is operational
> doctrine expansion, NOT taxonomy expansion — Phase 7.6
> does not add new KB-Wide laws; it formalizes how existing
> laws operationalize.

#### Core formulation

> **Arbitration determines how competing candidate authorities**
> **are EVALUATED.**
> **Resolution determines how one evaluated authority becomes**
> **OPERATIONALLY ACTUALIZED.**
> **WordPress architectures may implement these stages as**
> **integrated systems (single mechanism) or distributed**
> **systems (multiple mechanisms).**

#### Why paired operations?

Arbitration and Resolution were observed independently:
- **Arbitration Compiler** (KB-WIDE LAW, Law 4) — emerged
  through style-engine cascade-aggregation, capabilities-and-
  roles, block-filters, admin-menus, template hierarchy.
- **Resolution Surface** (Recurring cross-context candidate)
  — surfaced explicitly in site-building.template-hierarchy-
  and-resolution; retroactively verified in cascade-
  aggregation (integrated) and capabilities-and-roles
  (distributed).

Both were initially understood as separate concepts. Phase 7.6
recognition: they are **paired stages of a single operational
pattern** — wherever multi-source authority claims must be
reconciled into a single decision (Arbitration), there is also
a downstream stage where the winning claim becomes operational
(Resolution).

The pairing is structurally universal across 4 KB bounded
contexts. The pairing's **architectural variant** is what
varies, not its presence.

#### 5a — Integrated Architecture

> **Both Arbitration and Resolution stages co-located within**
> **a single mechanism.**

Examples:

| mechanism | location | how integrated |
|---|---|---|
| CSS cascade (cascade-aggregation) | browser CSS engine | specificity + order arbitration AND variable substitution + computed style derivation in single cascade engine |
| Template hierarchy (site-building) | get_query_template() | hierarchy logic generates candidates AND first-existing-wins selection in single function call |
| Block filter chain (block-filters) | @wordpress/hooks | priority arbitrates execution order AND composed filter outputs produce final settings in single chain invocation |
| Menu_position arbitration (admin-menus) | WordPress core admin | numeric position + filter ordering arbitration AND rendered menu order in single render path |

**Character**: Integrated architectures are typically simpler
to reason about (single location for both stages) but harder
to extend at one stage independently.

#### 5b — Distributed Architecture

> **Arbitration and Resolution stages distributed across**
> **multiple mechanisms / functions / locations.**

Examples:

| mechanism | arbitration location | resolution location |
|---|---|---|
| Capability adjudication (capabilities-and-roles) | map_meta_cap (meta cap → primitive caps + context derivation) | WP_User->has_cap iteration + user_has_cap filter + final boolean aggregation |

⚠ Capability adjudication is currently the **only documented
distributed instance**. Additional distributed instances may
emerge through future chunks; verification ongoing.

**Character**: Distributed architectures separate concerns
(arbitration logic isolated from resolution logic) but require
multi-function reasoning to understand the full operational
behavior.

#### Architectural variant integrity

> **Both Integrated and Distributed are STRUCTURALLY VALID**
> **architectures for paired operations. Force-fitting one**
> **variant onto a mechanism that exhibits the other obscures**
> **the architecture.**

This integrity principle was established through honest
retroactive verification (cascade = integrated; capabilities
= distributed). Both honest findings; both legitimate
architectural choices.

#### Implication for chunk authoring

When evaluating a mechanism that may exhibit paired operations:

1. Identify the Arbitration stage — what evaluates candidates?
2. Identify the Resolution stage — what actualizes the winning
   candidate?
3. Identify the architectural variant — are both stages in one
   location (integrated) or distributed across multiple
   locations?
4. Document the variant explicitly; do NOT force-fit.

#### Relationship to existing laws

| existing law / candidate | relationship to Doctrine 5 |
|---|---|
| Law 4 — Arbitration Compiler (KB-WIDE) | The Arbitration stage of the paired operation; existing law confirmed |
| Resolution Surface (Recurring cross-context, candidate) | The Resolution stage; recognized via retroactive verification |
| Law 1 — Declaration ≠ Exposure | Often determines what authorities QUALIFY for arbitration (multi-form exposure surfaces feed candidate generation) |
| Law 6 — Compiler ↔ Runtime Split | Arbitration may be compile-time; resolution may be runtime (e.g., CSS cascade — engine compiles, browser resolves) |

#### Status

- **Doctrine 5** = formalized constitutional doctrine (Phase
  7.6).
- **Arbitration Compiler (Law 4)** = KB-Wide Law (unchanged).
- **Resolution Surface candidate** = Recurring (cross-context),
  strong KB-Wide candidate; KB-Wide promotion pending audit
  verification.

> **Phase 7.6 is operational doctrine expansion, NOT new**
> **KB-Wide law promotion. The number of KB-Wide laws remains**
> **6. The constitution's ability to describe how laws**
> **operationalize across architectural variants and historical**
> **discovery has been expanded.**

### Doctrine 5c — Recurring Sub-patterns within Architectural Variants (Phase 7.7 patch)

> **Phase 7.7 Constitutional Refinement (2026-05-09):**
> After 3-instance Hybridized Doctrine 5 recurrence within
> "selection-from-candidates" mechanisms (block patterns +
> variations + transforms), a structural sub-pattern within
> the Hybridized variant warrants formalization. This is
> sub-pattern governance expansion — Doctrine 5 now governs
> Law → Variant → Sub-pattern hierarchy.

#### Core formulation

> **Recurring sub-patterns may emerge WITHIN an architectural**
> **variant of an established doctrine. Such sub-patterns are**
> **structural sub-elements, NOT independent laws nor**
> **standalone candidates.**

Sub-patterns:
- Reside WITHIN an existing law/variant (not parallel to it)
- Have their own recurrence evidence (typically requires
  3+ instances within the variant)
- Use existing maturity ladder (Surfaced / Local / Recurring
  intra/cross-context) but ANNOTATED as "sub-pattern of
  {Doctrine X variant Y}"
- Do NOT inflate KB-Wide law count

#### First formalized sub-pattern: Selection from Candidates

> **Selection from Candidates** (sub-pattern of Doctrine 5
> Hybridized variant):
> Mechanisms presenting selectable presets/conversions from
> a candidate pool to user via inserter-or-equivalent UI
> exhibit Hybridized Doctrine 5 architecture (distributed
> arbitration pipeline + integrated actualization through
> user-facing candidate choice surface).

**Verified instances** (3-instance threshold met):

| chunk | mechanism | evidence date |
|---|---|---|
| site-building.block-pattern-resolution-and-precedence | block patterns inserter | 2026-05-09 |
| block-authoring.variations (Q9 retro) | variation selection | 2026-05-09 |
| block-authoring.transforms (Q9 retro) | transform menu | 2026-05-09 |

**Status**: Recurring (cross-context, sub-pattern of
Doctrine 5 Hybridized variant) — bounded contexts:
site-building + block-authoring.

**Promotion path for sub-patterns**:
- Local (1 chunk) → Recurring (intra-context) (2+ chunks
  same bounded context) → Recurring (cross-context) (2+
  bounded contexts) → no further promotion (sub-patterns
  do NOT become KB-Wide; they remain sub-elements of their
  parent doctrine)

#### Why sub-pattern governance matters

Without explicit sub-pattern recognition:
- Recurring structural patterns within variants would either
  inflate the candidate list (false-novelty) OR be invisible
  (false-uniformity).
- Spec would lack vocabulary for "structurally meaningful but
  not law-grade" patterns.

With Doctrine 5c:
- Recurring within-variant patterns get explicit names
- Sub-patterns documented at appropriate constitutional level
- Discovery → naming → governance pipeline complete for
  sub-element scale

#### Q10 diagnostic relationship

Sub-pattern recognition is operationalized via Q10 (added
in Phase 7.7 — see Section D). Future chunks asking Q10
trigger sub-pattern verification work parallel to Q9's
retroactive law verification.

#### Status

- **Doctrine 5c** = formalized constitutional doctrine
  (Phase 7.7).
- **Selection from Candidates** = first formalized sub-pattern
  (Recurring cross-context, Doctrine 5 Hybridized variant
  sub-pattern).
- Future sub-patterns may be added under Doctrine 5 (or other
  doctrines) as evidence accumulates.

> **Phase 7.7 expands constitution to govern Law → Variant**
> **→ Sub-pattern hierarchy. The number of KB-Wide laws**
> **remains 6.**

---

### Doctrine 6 — Authority Access Mediation Doctrine (Phase 8.5 patch)

> **Phase 8.5 Constitutional Synchronization Patch (2026-05-10):**
> Following Phase 8.x audit of Authority Mediation Surface
> candidate (5 chunks × 4 bounded contexts × 5 distinct gating
> mechanisms), KB-Wide LAW promotion was REFUSED on
> architectural-concentration grounds (3/5 character
> categories; criterion 5 partial; non-trivial Law 1
> relationship), AND Doctrine-tier promotion was CONFIRMED.
> Doctrine 6 is the **first audit-driven doctrine creation**
> in KB. Its formal insertion synchronizes constitution with
> documented governance reality.

#### Core formulation

> **Authority Access Mediation:**
> **Governance through structurally gated authority-access**
> **choreography independent of authority origination.**
> **Mediation governs WHO can access an authority resource**
> **through what gating mechanism, distinct from how**
> **authority is operationalized (Doctrine 5: Arbitration ↔**
> **Resolution Paired Operations).**

Authority Access Mediation is structurally distinct from
adjacent constitutional elements:

| element | concern |
|---|---|
| **Doctrine 5 (Arbitration ↔ Resolution Paired Operations)** | How evaluated authority becomes operational |
| **Doctrine 6 (Authority Access Mediation)** | How subjects gain governed access to authority surfaces |
| **Law 1 (Declaration ≠ Exposure)** | The structural GAP between declaration and exposure |
| **Law 4 (Arbitration Compiler)** | How competing authorities are evaluated |

Doctrine 6 governs **access before participation** — the
upstream stage of authority lifecycle, distinct from
arbitration (competition stage) and resolution
(actualization stage).

> **Constitutional positioning**:
> - Doctrine 5 = authority operationalization
> - Doctrine 6 = authority accessibility governance

This distinction is structurally significant: not all
authority access leads to arbitration (capability check
gates options access without arbitration; locale switch
reassigns context without arbitration). Doctrine 6 is
operationally INDEPENDENT of Doctrine 5.

#### 5 documented gating mechanisms (sub-elements)

Per Phase 8.x audit evidence, Doctrine 6 manifests through
5 distinct gating mechanisms verified across 4 bounded
contexts:

##### 6a — Capability-gated mediation

Authority access controlled via user-capability check
(`current_user_can()` or equivalent).

| chunk | manifestation | bounded context |
|---|---|---|
| admin-ui.settings-api | options access via capability check | admin-ui |
| plugin-dev.capabilities-and-roles | foundational capability infrastructure | plugin-dev (indirect) |

**Character**: gating mechanism evaluates user attribute
against required capability; binary access decision.

##### 6b — Routing-gated mediation

Authority access controlled via navigation topology;
capability + screen + parent-menu hierarchy compose to
determine routing eligibility.

| chunk | manifestation | bounded context |
|---|---|---|
| admin-ui.admin-menus | hierarchical capability-gated navigation topology | admin-ui |

**Character**: gating mechanism evaluates routing eligibility
across multi-attribute composition (capability + parent +
screen).

##### 6c — Cognitive-surface-gated mediation

Authority access controlled via multi-axis attention
governance (capability + screen context + persistence state +
scope + priority).

| chunk | manifestation | bounded context |
|---|---|---|
| admin-ui.notices | conditional state-bearing exposure to user attention | admin-ui |

**Character**: gating mechanism is multi-axis (5+ axes);
authority resource being mediated is administrative
attention itself.

##### 6d — Authority-subscription-gated mediation

Authority access controlled via subscribe/dispatch
choreography; subscriber registration is the gating event.

| chunk | manifestation | bounded context |
|---|---|---|
| editor-customization.editor-hooks | direct subscribe/dispatch authority access | editor-customization |

**Character**: gating mechanism is subscription registration;
post-subscription, authority changes flow to subscriber
without further gating.

##### 6e — Context-reassignment-gated mediation

Authority access controlled via runtime context mutation;
gating mechanism is context-stack discipline (push/pop
choreography).

| chunk | manifestation | bounded context |
|---|---|---|
| i18n.locale-switching | runtime locale stack mediation | i18n |

**Character**: gating mechanism is context-mutation
choreography; access to authority varies based on context
stack state.

##### 6f — Origin-authenticity-gated mediation (Phase 8.5+ addition)

Authority access controlled via cryptographic / hash-based
binding to legitimate request-origin context.

| chunk | manifestation | bounded context |
|---|---|---|
| plugin-dev.nonces | request-origin HMAC verification | plugin-dev |

**Character**: gating mechanism is HMAC-bound origin verification;
request-origin authenticity determines action execution
eligibility.

##### 6g — Endpoint-permission-gated mediation (Phase 8.5+ addition via Q9 retro)

Authority access controlled via per-request endpoint-specific
permission evaluation at REST/transport boundary.

| chunk | manifestation | bounded context |
|---|---|---|
| plugin-dev.register-rest-route (Q9 retro) | per-request permission_callback evaluation at REST endpoint | plugin-dev |

**Character**: gating mechanism is permission_callback
per-request invocation; endpoint access determined per call,
not per session.

> **Composite manifestation observation**: REST route
> registration exhibits multi-form Doctrine 6 character (6g
> primary + visibility gating + input gating + namespace
> jurisdictional + method-axis). First documented composite
> Doctrine 6 manifestation. Status: observation only;
> cross-context verification needed before formalization.

##### 6h — Structural-participation-gated mediation (Phase 8.5+ addition via Q9 retro)

Authority access (structural participation) controlled via
declarative-time gating constraint enforced at editor inserter
UI layer.

| chunk | manifestation | bounded context |
|---|---|---|
| block-authoring.hierarchy-constraints (Q9 retro) | parent / ancestor / allowedBlocks declaration enforced at editor inserter | block-authoring |

**Character**: gating mechanism is registration-time
declaration; enforcement is UI-layer (editor inserter
filter); content-layer is bypassable via programmatic
insertion. **6-SOFT variant** (per Phase 8.10 formalization
below).

##### 6i — Editor-affordance-gated mediation (Phase 8.10+ addition via Q9 retro)

Authority access (editor feature exposure) controlled via
declarative-time governance flags enforced at editor UI layer.

| chunk | manifestation | bounded context |
|---|---|---|
| block-authoring.supports-field / governance-toggles (Q9 retro) | inserter / multiple / lock / html / reusable / renaming / visibility flags governing per-block editor affordance availability | block-authoring |

**Character**: gating mechanism is registration-time
declaration; enforcement is editor UI layer; bypassable via
direct content manipulation or attribute editing. **6-SOFT
variant** (per Phase 8.10 formalization below).

**Composite manifestation**: 7+ underlying flag instances
form coherent SOFT-mode subtype within single sub-element.
Default-direction split (most opt-OUT default-true) is
internal sub-pattern observation deferred to Phase 8.11+
adjudication.

#### Doctrine 6 sub-element promotion path

Sub-elements (6a-6i) follow Doctrine 5c sub-pattern promotion
path:
- Local (1 chunk) → Recurring (intra-context) (2+ chunks
  same bounded context) → Recurring (cross-context) (2+
  bounded contexts) → no further promotion (sub-elements
  do NOT become independent doctrines or laws; they remain
  sub-elements of Doctrine 6)

Future-discovered gating mechanisms become sub-elements
6j, 6k, etc. as evidence accumulates.

#### Doctrine 6 architectural variants (Phase 8.10 formalization)

> **Phase 8.10 Doctrine Architectural Formalization Patch
> (2026-05-10):** Phase 8.6 audit surfaced HARD/SOFT mode
> observation; Phase 8.9 retros (P1 DIVERGENT confirming
> ceiling + P3 ADDITIVE/CONFIRM adding 6i) decisively met
> SOFT-mode formalization threshold (≥2 SOFT sub-elements;
> 10+ underlying mechanism instances). Phase 8.10 promotes
> HARD/SOFT from observation → **formalized architectural
> variants** of Doctrine 6.

> **This is doctrine-tier architectural maturation, NOT
> doctrine count expansion.** Doctrine count remains 6.
> Variant layer expands within Doctrine 6 (parallel to
> Doctrine 5's Integrated/Distributed/Hybridized variant
> structure).

##### 6-HARD Variant (Access-Control Mode)

**Character**: Runtime per-access evaluation; non-bypassable;
enforces access boundaries; access-control function.

**Operational signature**: gate evaluated at every access
attempt; gate denial blocks operation; bypass requires
either elevated authority OR architectural exception.

**Sub-elements**: 6a, 6b, 6c, 6d, 6e, 6f, 6g (7 sub-elements)

**Underlying mechanism instances**: 7

**Bounded context manifestation**: admin-ui (3-form intra-
context: 6a + 6b + 6c) + editor-customization (6d) + i18n
(6e) + plugin-dev (2-form intra-context: 6f + 6g)

**Predicted debt class**: access-control debt (capability-
bypass / route-mismatch / origin-forgery / endpoint-
unauthorized / etc.)

##### 6-SOFT Variant (UX-Guidance Mode)

**Character**: Registration-time declaration; UI-layer
enforcement; bypassable via non-UI paths; UX-guidance
function.

**Operational signature**: gate declared at registration;
enforcement applied at UI layer (editor inserter, controls,
options); bypass possible via programmatic content
manipulation, direct attribute editing, or non-UI insertion
paths.

**Sub-elements**: 6h, 6i (2 sub-elements)

**Underlying mechanism instances**: 10+ (3 from 6h + 7+
from 6i)

**Bounded context manifestation**: block-authoring (2-form
intra-context: 6h + 6i)

**Predicted debt class**: UX-guidance debt (unintended
inserter exposure / attribute-bypass content states / lock-
UI absence with attributes-set lock / etc.)

##### Variant comparison

| dimension | 6-HARD | 6-SOFT |
|---|---|---|
| Evaluation timing | runtime per-access | registration-time declaration |
| Enforcement layer | runtime engine | editor UI |
| Bypass possible? | NO (or requires elevated authority) | YES (programmatic / attribute-direct) |
| Operational function | access-control | UX-guidance |
| Failure consequence | denial of operation | UX inconsistency / unintended exposure |
| Debt class | access-control debt | UX-guidance debt |
| Sub-elements | 7 (6a-6g) | 2 (6h-6i) |
| Underlying instances | 7 | 10+ |
| Primary bounded context category | governance modulation + authority federation + semantic substrate | schema authority |

**Variant manifestation pattern observation**:
- 6-HARD concentrates in **governance-architectural** bounded
  contexts (admin-ui, editor-customization, plugin-dev, i18n)
- 6-SOFT concentrates in **schema authority** bounded context
  (block-authoring)
- This may eventually formalize as bounded-context-character /
  variant correspondence pattern (currently observation only)

> **Constitutional symmetry achieved**: Doctrine 5 governs
> operationalization with 3 architectural variants
> (Integrated/Distributed/Hybridized + 5c sub-pattern);
> Doctrine 6 governs accessibility with 2 architectural
> variants (6-HARD/6-SOFT). Both doctrines now exhibit
> variant-layer architectural sophistication.

#### Doctrine 6 architectural variant promotion path

Variants follow Doctrine 5 variant promotion path precedent:
- Observation (audit-layer) → Formalization (≥2 sub-elements
  per variant; doctrine-layer patch) → Sub-pattern recurrence
  within variants
- Per Phase 8.10 patch: 6-HARD + 6-SOFT FORMALIZED
- Future variants may emerge if additional architectural
  modes manifest (currently only HARD/SOFT identified)

#### Capability declaration vs Mediation distinction (Phase 8.10 addition — Law 1 trap warning)

> **Phase 8.9 retro (supports-field) revealed Law 1 trap
> risk in Doctrine 6 application**: many declarative-time
> declarations are CAPABILITY DECLARATIONS (NOT mediation),
> not Doctrine 6 manifestations.

Distinction:

| character | function | example | Doctrine 6? |
|---|---|---|---|
| **Capability declaration** | exposes / enables / declares feature availability | `supports.color`, `supports.typography`, `supports.anchor` | NO — capability declaration |
| **Mediation gating** | gates / restricts / controls access to feature/authority | `supports.inserter`, `supports.multiple`, `supports.lock` | YES — Doctrine 6 (likely 6-SOFT) |

**Screening test for ambiguous flags**:
> Setting flag to OPT-OUT value (false for opt-out defaults):
> does this REMOVE access (gating, Doctrine 6) or DISABLE
> declaration (capability)?

**Constitutional discipline**:
- Capability declarations may exhibit Law 1 character
  (declaration ≠ exposure) WITHOUT Doctrine 6 character
- Doctrine 6 requires GATED ACCESSIBILITY CHOREOGRAPHY,
  not mere declaration-exposure gap
- Honest screening avoids force-fitting Doctrine 6 onto
  capability surfaces

> **Law 1 trap principle**: "Declaration ≠ Mediation
> automatically." Future chunk authoring + Q9 retros must
> screen each declarative flag for genuine gating character
> before classifying as Doctrine 6 sub-element.

#### Why Doctrine 6 matters

Without Doctrine 6:
- Mediation evidence (5 chunks × 5 mechanisms) lacks formal
  constitutional home
- Future chunks reasoning through mediation operate without
  shared vocabulary
- Conceptual drift between ontology evolution + constitution
  layer (Phase 7.5-style ontology > constitution mismatch)

With Doctrine 6:
- Mediation acquires Doctrine-level explanatory power
- Future chunks reference Doctrine 6 vocabulary natively
- 5 documented gating mechanisms provide pre-vocabulary for
  future mediation manifestations
- Constitutional synchronization with documented governance
  reality

#### Doctrine 6 vs Law 1 relationship

Doctrine 6 has non-trivial relationship with Law 1
(Declaration ≠ Exposure):
- Law 1 describes the GAP between declaration and exposure
- Doctrine 6 (in some forms) describes the MECHANISM that
  creates the gap

But Doctrine 6 is NOT subsumed by Law 1:
- Subscription-gated mediation (6d) does not necessarily
  implement Law 1 gap
- Context-reassignment-gated mediation (6e) creates context
  mutation without necessarily creating new Law 1 gap

> **Doctrine 6 elaborates Law 1's gating mechanisms in some
> forms but extends beyond Law 1 in others.** This is
> doctrine-appropriate relationship: doctrines elaborate
> law mechanisms while extending into independent territory.

#### Doctrine 6 KB-Wide LAW promotion pathway (preserved)

Phase 8.x audit refused KB-Wide LAW promotion on:
1. Architectural concentration (3/5 character categories)
2. Criterion 5 partial (no retroactive verification)
3. Non-trivial Law 1 relationship

Future re-audit triggers for KB-Wide LAW promotion:
- Cross-context expansion to 5+ bounded contexts
- Mediation manifestation in Schema authority OR Compiler/
  runtime OR Composition runtime category
- Retroactive Q9 verification completion (≥1 retro chunk)
- Demonstration of independence from Law 1 across all 5
  gating mechanisms

If/when these triggers met, Phase 8.x re-audit may produce
KB-Wide LAW promotion verdict. Doctrine 6 → Law 7
transition pathway preserved.

#### Status (post-Phase-8.10)

- **Doctrine 6** = formalized constitutional doctrine
  (Phase 8.5 patch); first audit-driven doctrine creation
- **9 sub-elements (6a-6i)** = documented gating mechanisms
  with chunk-level manifestation evidence (5 from Phase 8.5;
  6f from Phase 8.5+ nonces forward; 6g from Phase 8.5+ REST
  Q9 retro; 6h from Phase 8.5+ Schema Q9 retro; 6i from
  Phase 8.9 supports-field Q9 retro)
- **2 architectural variants (6-HARD + 6-SOFT)** = formalized
  Phase 8.10 (was observation only; promoted via Phase 8.9
  retros meeting ≥2 SOFT instance threshold)
- **Capability declaration vs Mediation distinction** =
  formalized Phase 8.10 (Law 1 trap screening principle)
- **18+ underlying mechanism instances** = 7 HARD + 3 6h +
  7+ 6i across 5 bounded contexts
- **3-context intra-context Doctrine 6 density** = admin-ui
  (3-form: 6a + 6b + 6c) + plugin-dev (2-form: 6f + 6g) +
  block-authoring (2-form: 6h + 6i)
- **KB-Wide LAW promotion** = REFUSED Phase 8.x AND
  Phase 8.6 re-audit; growth path SHIFTED from breadth
  (architectural ubiquity expansion) to depth (internal
  architectural differentiation per Phase 8.10 formalization);
  KB-Wide LAW pathway preserved but Doctrine-tier richness
  is primary growth direction

> **Phase 8.5 → 8.10 expand constitution to formally govern**
> **authority access mediation as Doctrine 6 with 9**
> **sub-elements + 2 architectural variants (6-HARD/6-SOFT)**
> **+ Law 1 trap distinction. The number of KB-Wide laws**
> **remains 6. The number of doctrines remains 6. Doctrine 6**
> **internal architecture matures via variant formalization.**

---

## SECTION D — Constitutional diagnostics (chunk authoring checklist)

When authoring a new chunk, run through this checklist to
verify constitutional alignment. Each question maps to one or
more laws.

### Pre-write diagnostic (7 questions)

| # | question | law triggered |
|---|---|---|
| 1 | What is the **declaration surface**? | Law 1 |
| 2 | What is the **exposure surface**? | Law 1 |
| 3 | What is the **trust / permission surface**? | Law 1 (3rd-form) |
| 4 | Does this cross any **execution boundary**? | Law 3 |
| 5 | Does this involve **multi-source arbitration**? | Law 4 |
| 6 | Is this **entity-centric** or **relationship-centric**? | Law 5 |
| 7 | What is the **compiler/runtime split** (if any)? | Law 6 |

Plus implicit doctrine adherence:
- **HTML primacy**: does the mechanism preserve HTML primacy?
  (Law 2; flag if it abandons HTML as primary)
- **Authority continuity**: does authority survive boundary
  crossings explicitly? (Law 3 reinforcement)

### Post-write diagnostic (Phase 7.5 patch — adjudication question)

After writing the SHAPE / INVARIANTS, ASK:

| # | adjudication question |
|---|---|
| 8 | **Does this chunk Confirm, Diverge from, Hybridize, or Surface a candidate law?** |

The answer determines:
- **Confirm**: chunk's domain matches existing candidate's
  structural core. Document in Table A (Universal Law
  Manifestation) or Table B (Pattern Recurrence/Divergence
  Verification).
- **Diverge**: chunk's domain shows ontological branching from
  candidate. Mark Divergent verdict; do NOT force-fit.
- **Hybridize**: chunk's domain has multi-modal character
  (some modes confirm, others diverge). Mark Hybridized
  verdict; document each mode's character.
- **Surface**: NEW candidate emerges from chunk's analysis.
  Mark Surfaced status; "surfaced, not constitutionalized";
  document verification path.

This adjudication question is required for chunks operating
on candidate-tier patterns (Surfaced / Local / Recurring).
KB-Wide laws are typically Confirmed in new chunks unless
contradicted, in which case the laws themselves require
audit revision.

### Post-write diagnostic (Phase 7.6 patch — retroactive verification trigger)

After Q8 evaluation, ASK:

| # | retroactive verification question |
|---|---|
| 9 | **Does this chunk reveal previously uncodified but latent law in earlier chunks?** |

If YES:
- Identify which earlier chunks may exhibit the candidate
  pattern latently.
- Schedule RETROACTIVE REFRAMING work for those chunks
  (per established 5-instance KB pattern: wrapper-attributes
  / dynamic-rendering / markup-representation /
  cascade-aggregation / capabilities-and-roles).
- Document the latent presence honestly: latent verification
  may CONFIRM the candidate's recurrence OR may reveal
  ARCHITECTURAL VARIANTS (per Doctrine 5 — Integrated /
  Distributed).
- Retroactive verification is **first-class promotion
  evidence** when applied with epistemic discipline.

If NO:
- Chunk operates within established patterns; no retroactive
  work triggered.

**Q9 distinguishes from Q8**:
- Q8 = "what happened in THIS chunk?" (current authorship)
- Q9 = "did THIS chunk reveal latent prior law elsewhere?"
  (retroactive constitutional expansion)

Together: KB now governs both **forward** (Q8) and **backward**
(Q9) ontology refinement.

**Q9 trigger examples** (historical):
- Resolution Surface surfaced in site-building.template-
  hierarchy-and-resolution → triggered Q9 → led to
  cascade-aggregation + capabilities-and-roles retroactive
  verifications → 3rd promotion event in KB.

**Discipline reminder**: retroactive verification produces
honest findings (latent confirmation OR architectural
divergence). Forcing all retros to confirm violates Phase 7.5
Doctrine 3 (Epistemic Integrity).

### Post-write diagnostic (Phase 7.7 patch — sub-pattern emergence)

After Q8 + Q9 evaluations, ASK:

| # | sub-pattern emergence question |
|---|---|
| 10 | **Does this chunk reveal recurring sub-structure within an existing law/variant?** |

If YES:
- Identify which law/variant the sub-structure resides within
  (typically Doctrine 5 variants or one of the 6 KB-Wide
  Laws).
- Check if the sub-pattern has prior instances (1st observation
  = Local sub-pattern; 2nd within same context = Recurring
  intra-context sub-pattern; 2nd cross-context = Recurring
  cross-context sub-pattern).
- Document the sub-pattern with explicit annotation:
  "sub-pattern of {Doctrine X variant Y}".
- Sub-patterns reside WITHIN parent doctrine; they do NOT
  become independent candidates.

If NO:
- Chunk operates within established structural patterns; no
  sub-pattern work triggered.

**Q10 distinguishes from Q8 and Q9**:
- Q8 = "what happened in THIS chunk?" (forward adjudication)
- Q9 = "did THIS chunk reveal latent prior law elsewhere?"
  (backward retroactive trigger)
- Q10 = "did THIS chunk reveal recurring SUB-structure within
  an existing law/variant?" (sub-pattern emergence)

Together: KB now governs:
- **Forward** discovery (Q8)
- **Backward** discovery (Q9)
- **Internal structural refinement** (Q10)

**Q10 historical example** (post-hoc classification):
- block-pattern-resolution-and-precedence chunk surfaced
  Selection from Candidates observation
- variations + transforms retros confirmed 3-instance
  recurrence
- Phase 7.7 patch formalized as Doctrine 5c sub-pattern

Going forward, Q10 is asked at chunk authoring time to detect
sub-pattern emergence early in evidence accumulation rather
than retrospectively.

**Discipline reminder**: sub-patterns require evidence
(typically 3-instance recurrence within a variant). Forcing
sub-pattern declarations on single-instance observations
violates Phase 7.5 Doctrine 2 (Candidate Structural
Complement) at the sub-pattern scale.

### Audit-time diagnostic (Phase 8.5 patch — structural tier classification)

When CONDUCTING a constitutional promotion audit (NOT during
per-chunk authoring), ASK:

| # | structural tier classification question |
|---|---|
| 11 | **Does audit evidence indicate this candidate is better constitutionalized as Doctrine-tier rather than KB-Wide LAW tier?** |

If YES (Doctrine-tier appropriate):
- Promote to Doctrine status (next available Doctrine number)
- Document sub-elements (variants / mechanisms within doctrine)
- Preserve KB-Wide LAW promotion pathway for future re-audit
- Spec change: Section C.5 expansion (NOT Section B)

If NO (KB-Wide LAW tier appropriate AND structural fit
established):
- Promote to KB-Wide LAW status (Law 7+)
- Spec change: Section B expansion + structural integration
  with adjacent laws

If NEITHER (refusal/conservation):
- Maintain Recurring (cross-context) candidate status
- Document refusal rationale + future re-audit pathway
- Spec change: NONE (META chronology entry only)

If candidate has 100% parent-law dependence + sub-character
coherence + structural specificity (Q11 outcome (e), Phase
8.14):
- Apply Law Sub-pattern Gate (4 criteria L1-L4)
- IF Law Sub-pattern Gate MET: promote to LAW SUB-PATTERN
  status (parent-law sub-pattern, e.g., Law 3b)
- Spec change: Section B parent-law expansion (sub-pattern
  architecture)
- Constitutional layer: NEW (Law sub-pattern layer if first;
  otherwise extends existing)

**Q11 distinguishes from Q8/Q9/Q10**:
- Q8/Q9/Q10 = per-chunk authoring diagnostics (forward
  adjudication / backward retroactive trigger / sub-pattern
  emergence)
- Q11 = AUDIT-TIME diagnostic (structural tier classification
  during formal promotion adjudication)

Q11 is invoked ONCE PER AUDIT, not per chunk.

**Q11 historical examples**:
- Phase 7.8 Resolution Surface audit: Q11 implicit verdict
  → Recurring (cross-context) maintained; refused KB-Wide
  AND Doctrine-tier (Resolution structurally absorbed by
  Doctrine 5 paired operations)
- Phase 8.x Mediation Surface audit: Q11 explicit verdict
  → Doctrine-tier promoted (Doctrine 6); refused KB-Wide
  on architectural-concentration grounds; preserved future
  KB-Wide pathway

**Constitutional principle introduced (Phase 8.5)**:

> **Promotion is NOT binary.** Audit may produce:
> (a) KB-Wide LAW promotion
> (b) Doctrine-tier formal promotion
> (c) Recurring (cross-context) maintenance / refusal
>
> Tier selection is structurally-fit-based, NOT
> evidence-quantity-based. Substantial evidence may warrant
> Doctrine-tier without warranting KB-Wide LAW.

Q11 makes this 3-outcome adjudication explicit; without
Q11, audits risk binary "promote or refuse" framing that
forecloses doctrine-tier formal classification.

**Q11 refinement (Phase 8.7 patch — re-audit precedent)**:

> **Re-audit may PRESERVE TIER while REFINING doctrine
> ARCHITECTURE.** A 4th outcome enters Q11's adjudication
> space:
> (d) Re-audit at increased evidence may RETAIN existing
>     tier while surfacing architectural observations
>     (modes / variants / sub-elements) that strengthen
>     doctrine without elevating tier.

Phase 8.6 audit established this precedent: Mediation
re-audit produced Doctrine-tier RETENTION + HARD/SOFT
architectural mode observation. Tier unchanged; doctrine
ARCHITECTURE refined.

> **Re-audit principle (Phase 8.7 codification)**:
> Audit may strengthen constitutional precision (architecture,
> sub-elements, mode observations) without changing tier.
> "Same tier, deeper structure" is valid audit outcome.

This refines the 3-outcome model into a **3-outcome + 1
re-audit refinement** model. Audits may now:
- Promote (tier elevation)
- Refuse (tier denied)
- Retain (tier maintained)
- Refine (tier maintained + architecture deepened, via
  re-audit)

Re-audits specifically may produce outcome (d). Initial
audits typically produce (a) / (b) / (c).

**Q11 extension (Phase 8.14 patch — Law-tier branching jurisprudence)**:

> **Phase 8.13 audit established LAW SUB-PATTERN as new
> formalization outcome.** Q11's adjudication space expands
> from 3-outcome + 1 refinement model to **5-outcome model**:

> Q11 outcome (e) **Law sub-pattern formalization**: Audit
> may produce LAW-TIER SUB-PATTERN promotion (specialization
> WITHIN existing law) when:
> - All candidate instances manifest existing law (100%
>   parent-law dependence)
> - Candidate has structural specificity (direction / medium /
>   boundary type) beyond parent law's general invariant
> - Candidate has cross-context breadth (≥3-4 categories)
> - Candidate has sub-character coherence (multiple instances
>   share specialization invariant)

Phase 8.13 audit established this precedent: Bridge Pattern
KB-Wide LAW REFUSED + LAW SUB-PATTERN FORMALIZED (Law 3b).
Tier preserved at law layer; architecture deepened via
sub-pattern.

> **Law-tier branching jurisprudence principle (Phase 8.14
> codification)**: KB-Wide LAW tier may acquire INTERNAL
> SUB-PATTERN ARCHITECTURE through audit-driven
> specialization formalization. Law sub-patterns are
> distinct from Doctrine sub-patterns (Doctrine 5c) by
> operating at LAW tier rather than DOCTRINE tier.

This refines Q11's adjudication space into **5-outcome
model**:
- (a) Promote (KB-Wide LAW tier elevation)
- (b) Refuse (tier denied entirely)
- (c) Retain (tier maintained at current level)
- (d) Refine (tier maintained + architecture deepened via
  re-audit)
- (e) **Law sub-pattern formalization** (NEW Phase 8.14;
  KB-Wide LAW tier preserved + sub-pattern layer
  introduced/expanded)

Re-audits typically produce (d). Initial audits may produce
(a)/(b)/(c). Law sub-pattern outcome (e) emerges when
candidate has 100% parent-law dependence + sub-character
coherence; surfaced via Phase 8.13 audit precedent.

> **Doctrine maturation principle (Phase 8.10)** + **Law-tier
> branching jurisprudence principle (Phase 8.14)** =
> constitutional sophistication may proceed through ANY layer's
> internal architectural differentiation (doctrine variants,
> law sub-patterns, etc.) rather than tier ascension.

**Q11 extension (Phase 8.10 patch — re-audit + retro
operationalization precedent)**:

> **Re-audit + retro arc may DEEPEN doctrine architecture
> WITHOUT tier shift via formalization patch sequence.**
> Phase 8.6 re-audit produced HARD/SOFT mode observation;
> Phase 8.9 retros (P1 + P3) produced formalization-eligible
> evidence; Phase 8.10 patch FORMALIZED HARD/SOFT as
> architectural variants. This is **operationalized
> tier-preserving refinement** spanning multiple phases.

> **Re-audit + retro operationalization principle (Phase
> 8.10 codification)**: Re-audit-derived observations may
> mature through retro evidence accumulation into
> formalization-patch promotions WITHOUT tier shift.
> Doctrine architecture may evolve through multi-phase
> arcs (audit → observation → retro → formalization) at
> doctrine-tier without ever requiring KB-Wide LAW promotion.

This further refines Q11's adjudication space:
- (d-1) Re-audit refinement (tier preserved + observation
  surfaced) — Phase 8.7 codification
- (d-2) Re-audit + retro arc (tier preserved + observation
  matures to formalized architectural variant via subsequent
  patch) — Phase 8.10 codification

> **Doctrine maturation principle (Phase 8.10)**:
> A doctrine may mature primarily through internal
> architectural differentiation (variant formalization +
> sub-element accumulation + sub-pattern recognition) rather
> than tier ascension. This is a valid mature constitutional
> growth pathway.

This may become one of KB's most important long-term
governance insights: KB Constitution v2 may not require new
KB-Wide Laws — it may emerge through doctrine architecture
sophistication alone.

### Audit gate criteria — Standard vs Governance-intensive (Phase 8.5 bifurcation)

> **Phase 8.5 bifurcation rationale**: The Phase 7.7-defined
> 5 audit gate criteria were calibrated against Resolution
> Surface candidate (operational pattern within paired
> operations). Mediation candidate revealed that
> governance-mechanism candidates require additional
> criteria (gating abstraction independence + structural
> consequence) for honest evaluation. Phase 8.5 formalizes
> the bifurcation.

**Standard audit gate (5 criteria)** — applies to general
constitutional candidates:

| # | criterion |
|---|---|
| 1 | Bounded context PRESENCE ≥ 4 |
| 2 | Architectural variants ≥ 2 |
| 3 | Intra-context density ≥ 1 |
| 4 | Q10 sub-pattern check completed |
| 5 | Forward + retroactive evidence both contributing |

Use for: Resolution Surface, Bridge Pattern, Federation
Pattern, Selection from Candidates sub-pattern, anticipated
triad members (Interception / Mediation / Federation as
generic patterns).

**Governance-intensive audit gate (5 standard + 2 specific
= 7 criteria)** — applies to candidates whose primary
character is governance-mechanism (gating, mediation,
authority access choreography):

| # | criterion |
|---|---|
| 1 | Bounded context PRESENCE ≥ 4 |
| 2 | Architectural variants ≥ 2 (OR equivalent mechanism diversity) |
| 3 | Intra-context density ≥ 1 |
| 4 | Q10 sub-pattern check completed |
| 5 | Forward + retroactive evidence both contributing |
| 6 | **Gating abstraction independence** — candidate definable independent of specific underlying authority systems (test: 1-sentence abstract definition without reference to specific WP idioms) |
| 7 | **Structural consequence** — candidate predicts failure / debt classes across contexts (test: chunks reasoning through candidate produce structurally different debt classifications) |

Use for: Authority Mediation Surface, Authority Interception
Surface (when audited), Authority Subscription patterns,
future governance-mechanism candidates.

**Bifurcation purpose**: prevents OVERFITTING Resolution-
calibrated criteria to governance-domain structures, which
would either (a) reject valid governance candidates that
don't match Doctrine 5 variant taxonomy, OR (b) admit
governance candidates without verifying their abstract
independence + operational consequence.

**Selection between gate types** (audit conductor decision):

| candidate primary character | gate type |
|---|---|
| Operational pattern (selection/actualization/composition) | Standard |
| Governance mechanism (gating/mediation/access control) | Governance-intensive |
| Hybrid character | Apply BOTH; honor stricter criteria |

**Historical application**:
- Phase 7.8 Resolution audit: Standard gate (operational
  pattern); 5/5 met → REFUSED on structural fit
- Phase 8.x Mediation audit: Governance-intensive gate
  (governance mechanism); 6/7 fully MET + 1 PARTIALLY MET
  → Doctrine-tier promoted
- Phase 8.13 Bridge audit: Standard gate (operational
  pattern); 5/5 met → REFUSED + LAW SUB-PATTERN FORMALIZED
  (Law 3b)

### Law Sub-pattern Gate Criteria (Phase 8.14 patch — NEW audit gate class)

> **Phase 8.14 introduces THIRD audit gate class**: Law
> Sub-pattern Gate, applied IN ADDITION TO Standard or
> Governance-intensive gate when Law sub-pattern formalization
> is candidate adjudication outcome (Q11 outcome (e)).

**Law Sub-pattern Gate Criteria (4 criteria; Phase 8.14
formalization)**:

| # | criterion | rationale |
|---|---|---|
| **L1** | **100% parent-law dependence** — ALL candidate instances must manifest the parent law (NOT majority; NOT predominantly) | Sub-pattern is SPECIALIZATION within parent law; partial dependence indicates independent invariant candidacy instead |
| **L2** | **Strong cross-context breadth** — candidate must manifest in ≥3-4 character categories | Demonstrates sub-pattern is broadly-recurring within parent law's scope, not single-domain artifact |
| **L3** | **Structural specificity beyond parent law's general invariant** — direction / medium / boundary type / etc. specificity must be structurally meaningful | Distinguishes sub-pattern from generic parent-law manifestation |
| **L4** | **Sub-character coherence** — multiple instances share specialization invariant (sub-character diversity supported by single coherent specialization) | Validates sub-pattern as unified specialization, not fragmented patterns |

**Why Law Sub-pattern Gate exists**:
- Law sub-patterns are RARER than doctrine sub-patterns
- Law-tier formalization commits constitutional architecture
- Without explicit gate, Law sub-pattern proliferation risk
  rises (every Law 3 manifestation form would warrant
  formalization)
- Gate ensures Law sub-pattern formalization preserves
  Law-tier integrity

**Selection rule (audit conductor)**:
- Apply Standard or Governance-intensive gate per candidate
  primary character (admissibility evaluation)
- IF candidate has 100% parent-law dependence (D1 finding)
  AND audit indicates Q11 outcome (e) (Law sub-pattern
  formalization), apply Law Sub-pattern Gate (additional
  formalization criteria)
- BOTH gates must be met for Law sub-pattern formalization

**Historical application**:
- Phase 8.13 Bridge Pattern audit: Standard gate 5/5 MET
  (admissibility) + Law Sub-pattern Gate 4/4 MET
  (formalization) → Law 3b formalized

| Bridge Phase 8.13 Law Sub-pattern Gate evaluation | result |
|---|---|
| L1 100% parent-law dependence | ✅ 5/5 instances manifest Law 3 |
| L2 Strong cross-context breadth | ✅ 4 character categories |
| L3 Structural specificity beyond parent law | ✅ PHP→HTML→JS direction + HTML medium specificity |
| L4 Sub-character coherence | ✅ 5 sub-characters share Bridge invariant |

**Constitutional discipline**:
- Law Sub-pattern Gate is STRICTER than Standard or
  Governance-intensive gates
- Law sub-pattern formalization is HIGH-COMMITMENT
  constitutional event (alters law-tier architecture)
- Phase 8.14 conservative principle: ONLY candidates meeting
  ALL Law Sub-pattern Gate criteria warrant Law sub-pattern
  formalization
- Future Law sub-pattern candidates (Law 1b, Law 6b, etc.)
  must survive same gate scrutiny

> **Constitutional purpose**: Law Sub-pattern Gate prevents
> law-layer inflation. Law sub-patterns require stricter
> evidence than KB-Wide LAW promotion (because they alter
> law-tier architecture). Without strict gate, "every
> recurring pattern manifesting a law" would inflate to law
> sub-pattern formalization.

### Post-write diagnostic suite (full, post-Phase-8.5)

Complete diagnostic suite:

| # | question | function | invocation |
|---|---|---|---|
| 8 | Confirm/Diverge/Hybridize/Surface? | forward adjudication (Phase 7.5) | per chunk |
| 9 | Reveal latent prior law? | backward retroactive trigger (Phase 7.6) | per chunk |
| 10 | Reveal recurring sub-structure? | sub-pattern emergence (Phase 7.7) | per chunk |
| 11 | Doctrine-tier vs KB-Wide LAW tier? | structural tier classification (Phase 8.5) | per audit |

**Per-chunk protocol**: apply Q8 → Q9 → Q10 in sequence.
Each may trigger different follow-up work:
- Q8 outcomes → Tables A/B documentation
- Q9 YES → schedule retroactive verification
- Q10 YES → document sub-pattern; check existing instances

**Per-audit protocol**: Q11 invoked ONCE during formal
constitutional promotion audit. Q11 outcome determines tier
(KB-Wide LAW / Doctrine-tier / Recurring-cross-context
maintenance). Audit gate type (Standard vs Governance-
intensive) selected per candidate's primary character before
Q11 evaluation.

### Post-write diagnostic (5 criteria, from DSL spec)

| # | criterion |
|---|---|
| 1 | Accuracy — chunk correctly describes its target |
| 2 | Structural fit — extends ontology coherently |
| 3 | Reusability — uses authority ontology glossary + structural-patterns vocabulary |
| 4 | Phase fit — positions correctly relative to KB phase model |
| 5 | Doctrine respect — honors HTML primacy + other doctrines where applicable |

### Anti-doctrine flags

If a chunk being written exhibits:
- Single-surface declaration (no separate exposure consideration) →
  pause; verify Law 1 applies / doesn't
- Treats HTML as ephemeral output → likely Law 2 violation
- "Magic" runtime resolution without identifying compiler →
  surface the compiler/runtime split (Law 6) explicitly
- Conflict resolution as "edge case" → reconsider as
  arbitration compilation (Law 4)
- Entity descriptions only with no relationship coverage →
  consider Law 5 application
- Boundary crossings without explicit reconciliation →
  Law 3 violation

---

## SECTION E — Predictive frontier

Constitutional laws should be tested in untouched bounded
contexts. The following predictions are anticipated; explicit
verification will occur as chunks are written.

### editor-customization (predicted)

**Predicted laws to manifest:**
- Law 1 (Declaration ≠ Exposure): heavy. block filters,
  slotfills register; exposure governed by editor context /
  capability gates.
- Law 5 (Entity → Relationship): block tree → block hooks /
  filters (relationships).
- Law 4 (Arbitration Compiler): possibly — if multiple
  filters / hooks compose into single editor render output.
- Law 3 (Authority Continuity): editor state ↔ entity store
  reconciliation.

**Anticipated first chunk:** `editor-customization.block-filters`
(tests Laws 1 + 5).

### site-building (predicted)

**Predicted laws to manifest:**
- Law 5 (Entity → Relationship): templates / parts / patterns
  composition graph (relationship).
- Law 6 (Compiler ↔ Runtime Split): template hierarchy
  resolution as runtime compilation of static template files.
- Law 1 (Declaration ≠ Exposure): templates declared in
  filesystem; exposure / selection governed by user editing +
  postType matching.

### i18n (predicted)

**Predicted laws to manifest:**
- Law 3 (Authority Continuity): translation continuity across
  server PHP gettext + client JS translations.
- Law 6 (Compiler ↔ Runtime Split): POT → PO → MO compilation;
  translation runtime loading.
- Anticipated: orthogonal cross-cutting; may not strongly
  exercise relationship-centric laws.

### admin-ui (predicted)

**Predicted laws to manifest:**
- Law 1 (Declaration ≠ Exposure): admin menus declared;
  capability gates governance exposure.
- Law 4 (Arbitration Compiler): possibly — admin notice
  display arbitration with multiple sources.
- Likely Law 2 (HTML Primacy) variant: admin pages are
  HTML-rendered (server-first).

### build-tooling (predicted)

**Predicted laws to manifest:**
- Law 6 (Compiler ↔ Runtime Split): wp-scripts is a build-time
  compiler producing runtime artifacts.
- Lower likelihood of relationship-centric or arbitration
  laws (more procedural).

### Predictive frontier discipline

When entering each bounded context:
1. Open the chunk by checking which laws are predicted to
   manifest.
2. As chunks are written, verify or revise the predictions.
3. If a predicted law fails to appear, that absence is itself
   a documentable finding.
4. If an unpredicted law appears, audit whether it should have
   been predicted (may reveal omission).

---

## SECTION X — Analytical Tier (Non-Constitutional Infrastructure; Phase 8.19 patch)

> **CRITICAL ARCHITECTURAL DISCLAIMER**:
>
> Section X is **NOT Layer 12** of the constitutional
> hierarchy. Section X is **structurally adjacent** to the
> constitutional hierarchy, NOT vertically integrated into
> it. This distinction is load-bearing for KB Constitution
> v2 integrity.
>
> **Constitutional hierarchy (Sections A-E)** = AUTHORITY
> ARCHITECTURE
> **Section X (this section)** = INTERPRETIVE ARCHITECTURE
>
> These are PARALLEL infrastructures, NOT hierarchical
> tiers. Treating Section X as a constitutional layer
> extension would collapse the analytical/constitutional
> distinction — exactly what Phase 8.18 audit refused.

> **Implementation principle (Phase 8.19 patch)**:
> **"Formal but non-sovereign"** — Analytical Tier provides
> systematic vocabulary without constitutional commitment.
>
> **Operational metaphor (per Phase 8.19 framing)**:
> **Observatory, NOT Government**. Section X observes and
> classifies; Sections A-E govern.

### Section X.1 — Constitutional Meta-Architecture (NEW, Phase 8.19)

KB Constitution v2 now operates **two parallel formal
infrastructures**:

```
┌───────────────────────────────────────────────────────────┐
│ CONSTITUTIONAL HIERARCHY (Sections A-E; Authority         │
│ Architecture)                                             │
│                                                           │
│ Layer 1 — KB-Wide Laws                                   │
│ Layer 2 — Law Sub-patterns                               │
│ Layer 3 — Law Sub-characters                             │
│ Layer 4 — Doctrines                                      │
│ Layer 5 — Doctrine Sub-elements                          │
│ Layer 6 — Doctrine Architectural Variants                │
│ Layer 7 — Doctrine Sub-patterns                          │
│ Layer 8 — Active Candidates                              │
│ Layer 9 — Deferred Candidates                            │
│ Layer 10 — Status Notations                              │
│ Layer 11 — Event Registries                              │
│                                                           │
│ Function: governs WHAT WordPress/Gutenberg architecture  │
│           must invariantly express                       │
└───────────────────────────────────────────────────────────┘

         ←—————— PARALLEL (NOT vertical) ——————→

┌───────────────────────────────────────────────────────────┐
│ ANALYTICAL TIER (Section X; Interpretive Architecture)    │
│                                                           │
│ Historiographic Analytical Frameworks                     │
│   - Civilization Archetypes (Phase 8.18 formalized)       │
│                                                           │
│ Function: provides vocabulary for ANALYZING WordPress/    │
│           Gutenberg architecture without governing it     │
└───────────────────────────────────────────────────────────┘
```

**Three civilizational functions formally separated**:

| function | KB infrastructure | character |
|---|---|---|
| **Constitutional Governance** | Sections A-D (Laws / Doctrines / Diagnostics) | What GOVERNS architecture |
| **Constitutional Historiography** | META chronology + Phase epoch documents | What EVOLVED in constitutional development |
| **Constitutional Sociology** | Section X (Analytical Tier) | What CLUSTERS observationally without governing |

> **Phase 8.18 + 8.19 jurisprudential principle**: A mature
> constitution distinguishes between WHAT GOVERNS, WHAT
> EVOLVED, and WHAT CLUSTERS. KB now operates these
> distinctions through dedicated formal infrastructures.

### Section X.2 — Boundary Architecture (Constitutional vs Analytical)

> **Critical methodological discipline**: The boundary between
> Constitutional Hierarchy and Analytical Tier is
> JURISPRUDENTIAL, not merely organizational.

#### Boundary criteria

A pattern / classification / observation belongs to
**Constitutional Hierarchy** when:
- ✅ Has CONSTITUTIONAL INDEPENDENCE (not merely clustering
  of existing constitutional elements)
- ✅ Has PREDICTIVE VALUE for governance decisions
- ✅ Meets relevant audit gate criteria (Standard /
  Governance-Intensive / Law Sub-pattern Gate)
- ✅ Survives independence stress test (NOT subsumed by
  existing law/doctrine)

A pattern / classification / observation belongs to
**Analytical Tier** when:
- ✅ Has STRONG INTERNAL COHERENCE
- ✅ Provides systematic comparative vocabulary
- ❌ Does NOT establish constitutional independence
- ❌ Does NOT yet have validated predictive value
- ✅ Useful for analysis WITHOUT constitutional commitment

> **Boundary load-bearing principle**: Patterns may move
> from Analytical Tier to Constitutional Hierarchy ONLY
> through formal re-audit demonstrating predictive
> validation + constitutional independence.

#### Cross-boundary movement rules

| movement | requires | precedent |
|---|---|---|
| Analytical → Constitutional | Re-audit demonstrating V1-V4 validation + independence | Phase 8.20+ pathway preserved |
| Constitutional → Analytical | Re-audit demonstrating constitutional independence is NOT established | (no precedent yet; possible if existing constitutional element is found to be analytical clustering) |
| New addition to Analytical | Strong internal coherence + systematic methodology | Civilization archetypes precedent |
| New addition to Constitutional | Standard / Governance-Intensive / Law Sub-pattern Gate satisfaction | Existing audit gate precedents |

### Section X.3 — Civilization Archetypes (Historiographic Analytical Framework, Phase 8.18 formalized)

> **Status**: Historiographic Analytical Framework
> (analytical, NOT constitutional). Formalized Phase 8.18
> audit + Phase 8.19 spec patch.

**Definition**: Civilization Archetypes are analytical
classifications of bounded context constitutional element
profiles, providing systematic comparative vocabulary for
KB analysis WITHOUT constitutional independence claim.

#### 4 Civilization Archetypes (analytical formalization)

##### X.3.A — Governance-heavy archetype

**Constitutional element profile signature**:
- Doctrine 6 multi-form sub-element density (3+ sub-elements
  intra-context)
- Predominantly 6-HARD architectural variant
- Authority Mediation Surface intra-context density
- Tri-modal governance organizational pattern

**Currently observed in**: admin-ui

**Predictive hypothesis (UNVALIDATED)**: future bounded
contexts exhibiting 3+ Doctrine 6 sub-elements + 6-HARD
mode dominance + tri-modal organization may classify as
Governance-heavy.

##### X.3.B — Security-heavy archetype

**Constitutional element profile signature**:
- Doctrine 6 multi-form security sub-elements (capability +
  origin-authenticity + endpoint-permission character)
- Federation Pattern density
- Authority security infrastructure organization
- Multi-chunk security trio organization

**Currently observed in**: plugin-dev

**Predictive hypothesis (UNVALIDATED)**: future bounded
contexts exhibiting Doctrine 6 6f-equivalent (origin) +
6g-equivalent (endpoint) + capability infrastructure may
classify as Security-heavy.

##### X.3.C — Semantic-heavy archetype

**Constitutional element profile signature**:
- Doctrine 5 Hybridized variant density
- Doctrine 6 6e (Context-reassignment-gated) presence
- Federation Pattern (semantic federation form)
- Semantic substrate character (deferred-category
  observation)

**Currently observed in**: i18n

**Predictive hypothesis (UNVALIDATED)**: future bounded
contexts exhibiting semantic continuity + Doctrine 5
Hybridized + Federation may classify as Semantic-heavy.

##### X.3.D — Computational-heavy archetype

**Constitutional element profile signature**:
- Law 3b 3b-react sub-character lifecycle-completeness OR
  equivalent Law sub-pattern density
- Computational-architectural character (compiler-runtime
  pipeline)
- Doctrine 5 Resolution as continuous pattern
- UNIFORM Doctrine 6 absence (or near-absence)

**Currently observed in**: interactivity (style-engine
partial — Computational-architectural confirmed but Law 3b
density not present)

**Predictive hypothesis (UNVALIDATED)**: future bounded
contexts exhibiting Law sub-pattern lifecycle density +
Computational-architectural + Doctrine 6 absence may
classify as Computational-heavy.

#### Civilization Archetype usage rules (analytical, not constitutional)

Per Phase 8.19 implementation discipline:

1. **Archetype classification is ANALYTICAL VOCABULARY**:
   chunks may reference archetype as analytical context;
   chunks should NOT treat archetype as constitutional
   element.

2. **No archetype-driven constitutional inflation**:
   archetype classification does NOT warrant new
   constitutional structure surfacing. Constitutional
   surfacing requires constitutional analysis (Q8/Q9/Q10),
   not archetype analysis.

3. **Predictive use is HYPOTHETICAL**: archetype-based
   predictions about future bounded contexts are
   HYPOTHESES (V1-V4 validation criteria), not
   constitutional expectations.

4. **Constitutional re-audit pathway preserved** (V1-V4):
   future evidence (predictive validation + cross-context
   breadth) may warrant constitutional layer re-audit
   formalization.

5. **Analytical tier additions** follow precedent: future
   historiographic analytical frameworks may be formalized
   at Section X following civilization archetypes precedent.

### Section X.4 — Predictive Validation Criteria (V1-V4, Phase 8.18 formalized)

Phase 8.20+ constitutional layer re-audit pathway requires:

#### V1 — Cross-context generalization

**Test**: Apply archetype classification to additional
bounded contexts beyond current 4.

**Threshold**: Archetype classification must apply
meaningfully to ≥7 total bounded contexts (current 4 +
3 additional). If pure-archetype classification fails for
mixed-archetype contexts, refine framework BEFORE re-audit.

#### V2 — Predictive accuracy

**Test**: Forecast a bounded context's closure profile
BEFORE the context closes. Verify prediction accuracy
post-closure.

**Threshold**: ≥3 predictions with ≥70% accuracy across
predictive dimensions (inflation resistance + dominant
element density + closure ceremony character).

#### V3 — Constitutional independence

**Test**: Demonstrate that archetype classification ADDS
INSIGHT beyond constitutional element profile reading.

**Threshold**: Identify ≥3 cases where archetype-aware
analysis produces CONCLUSIONS NOT obtainable from
element-profile-only analysis.

#### V4 — Inflation resistance preservation

**Test**: Demonstrate that archetype formalization does NOT
trigger typology proliferation.

**Threshold**: Stable archetype count (≤6 archetypes) across
12+ bounded contexts.

> **Currently: 0/4 V1-V4 validation criteria met**.
> Constitutional layer re-audit pathway PRESERVED but
> UNTRIGGERED.

### Section X.5 — Q12 (anticipated diagnostic; Phase 8.19 surfaced)

> **Phase 8.19 surfaces Q12 candidate** for future
> formalization: analytical vs constitutional formalization
> adjudication question.

#### Q12 (anticipated; per-audit invocation)

> **"Does this candidate warrant CONSTITUTIONAL formalization
> (laws / doctrines / sub-patterns) or ANALYTICAL
> formalization (Section X / Historiographic Analytical
> Framework)?"**

If candidate has:
- Strong internal coherence + systematic methodology +
  CONSTITUTIONAL INDEPENDENCE + PREDICTIVE VALUE: →
  **Constitutional formalization** (apply Q11 5-outcome
  model)
- Strong internal coherence + systematic methodology + WEAK
  constitutional independence + UNVALIDATED predictive value:
  → **Analytical formalization** (Section X)
- Insufficient coherence: → observation only

> **Q12 status: SURFACED (Phase 8.19); NOT FORMALIZED
> pending second use case** (per Phase 8.5 precedent: Q11
> formalized after Phase 8.x established Doctrine-tier
> precedent; Q12 may formalize after second analytical
> framework precedent).

### Section X.6 — Predictive Modality Refinement (Phase 8.21 patch)

> **Phase 8.20 V2 falsifiability pilot finding**:
> Section X archetypes predict ABSENCE more reliably than
> DOMINANCE. Phase 8.21 codifies this distinction without
> adding new archetypes.

#### Two predictive modalities (X.3 refinement)

Section X.3 archetype usage now distinguishes two distinct
predictive modalities:

##### X.3a — Dominant Archetype Prediction (weaker modality)

**Predictive question**: "Which archetype will dominate
this bounded context?"

**Phase 8.20 evidence**: PARTIAL predictive value.
- editor-customization: predicted Governance-heavy (actual:
  HYBRID Governance + Interception; Authority Interception
  Surface dominant — MISSED)
- data-layer: predicted Hybrid (actual: Hybrid confirmed;
  Law 5 dominance — MISSED)

**Reliability**: MODERATE-WEAK. Hybridization + novel
archetype emergence reduce prediction accuracy.

**Recommended use**: Hypothesis generation only; explicit
acknowledgment of dominance prediction limits.

##### X.3b — Constraint / Absence Prediction (stronger modality)

**Predictive question**: "Which constitutional elements
will systematically be ABSENT in this bounded context?"

**Phase 8.20 evidence**: STRONG predictive value.
- editor-customization: Law 3b 3b-react absence + Computational-
  architectural absence + 6h absence — ALL CORRECTLY
  PREDICTED
- data-layer: Doctrine 6 multi-form density absence + Law 3b
  3b-react absence + Pure Computational partial absence —
  ALL CORRECTLY PREDICTED

**Reliability**: STRONG. Negative constitutional space is
more stable than positive constitutional density.

**Recommended use**: Pre-closure expectation setting +
governance debt prevention (knowing what WON'T manifest
helps avoid false-positive analysis).

#### Predictive modality principle (Phase 8.21 codification)

> **Negative constitutional space is more predictive than
> positive constitutional density.** This may approach
> CONSTITUTIONAL CONSTRAINT SCIENCE — analogous to
> "forbidden states" in physics, "viability constraints"
> in biology, "impossible forms" in linguistics.

> **Section X's predictive power is ARCHETYPE-BOUNDED
> EXCLUSION FORECASTING, not DOMINANT ARCHETYPE ASSIGNMENT.**
> This is more modest but more robust.

#### Hybrid archetype documentation (Phase 8.20 emergent finding)

Per Phase 8.20 pilot, bounded contexts may exhibit HYBRID
archetype profiles (NOT pure single-archetype fit).

**Documented hybrid (Phase 8.20)**:
- **editor-customization**: Governance-heavy + Interception-
  heavy hybrid (Doctrine 6 6d + Authority Interception
  Surface 3-form intra-context density)

**Hybrid documentation principle**: Hybrid is descriptive,
NOT a new archetype. Multiple archetypes may co-manifest
within single bounded context. Hybrid composition is
analytical refinement, not typology expansion.

#### Novel archetype surfacing discipline (Phase 8.20 V4 warning)

Per Phase 8.20 V4 inflation pressure observation (1 new
archetype candidate from 2-context pilot):

**Novel archetype surfacing requires**:
- Repeated failure of existing archetypes across MULTIPLE
  contexts (NOT single-context observation)
- Pre-registration falsifiability (predict NEW archetype's
  features in advance + verify)
- Inflation resistance check (does new archetype add
  predictive value beyond hybrid description?)

**Currently SURFACED but NOT promoted**:
- "Entity-substrate-heavy" / "Law-5-substrate-heavy"
  candidate from data-layer Phase 8.20 pilot — single-
  context observation; insufficient for promotion

> **Archetype scarcity discipline**: Constitutional scarcity
> protected Sections A-E. Analytical scarcity must protect
> Section X. Same philosophy, different tier.

**Future gate consideration (NOT yet formalize)**:
**Archetype Expansion Gate (A1-A4)** may eventually be
formalized when novel archetype candidates accumulate
sufficient evidence. Per Phase 8.21 conservative discipline:
SURFACED as anticipated future infrastructure; NOT
formalized at this time.

#### Predictive scope clarification (Phase 8.21 documentation)

Section X archetype usage clarification (per Phase 8.20 V2
findings):

**STRONG analytical use**:
- ✅ Pre-closure absence prediction (which elements WON'T
  dominate)
- ✅ Bounded context character description (post-hoc)
- ✅ Cross-context comparative analysis (Phase 8.17 pattern)
- ✅ Hybrid identification (multi-archetype manifestation)

**WEAK analytical use**:
- ⚠ Pre-closure dominant element prediction (PARTIAL
  reliability; expect surprises)
- ⚠ Pre-closure exact archetype assignment (HYBRID risks
  + novel emergence risks)

**INAPPROPRIATE use**:
- ❌ Constitutional layer reference (Section X is NOT
  constitutional)
- ❌ Forced single-archetype assignment when hybrid character
  observed
- ❌ Premature novel archetype formalization on single-context
  observation

#### X.6 empirical validation note (Phase 8.23 small patch)

> **Phase 8.20 + 8.22 cumulative pilot evidence**: X.3a/X.3b
> modality split EMPIRICALLY VALIDATED across 2 pilots
> (3 contexts total).

| pilot | X.3a Dominance | X.3b Absence |
|---|---|---|
| Phase 8.20 (editor-customization) | PARTIAL (HYBRID + missed dominant Authority Interception) | STRONG (3/3 absences correctly predicted) |
| Phase 8.20 (data-layer) | PARTIAL (HYBRID OK; missed Law 5 dominance + novel archetype emergence) | STRONG (3/3 absences correctly predicted) |
| Phase 8.22 (build-tooling) | GOOD (HYBRID-first hypothesis at LOW confidence) | STRONG (6/7 full + 1/7 partial) |

> **Cumulative empirical pattern**: X.3b absence prediction
> is STRONG across all pilots; X.3a dominance prediction is
> WEAK to MODERATE / GOOD only with HYBRID-first low-
> confidence framing.

**Operational Tier framework (post-Phase-8.23)**:

| analytical tier | reliability | recommended use |
|---|---|---|
| **Tier 1: Constraint / Absence forecasting (X.3b)** | STRONG (validated 2 pilots × 3 contexts) | Primary predictive engine |
| **Tier 2: Dominance hypothesis (X.3a)** | WEAK to MODERATE (HYBRID-first only) | Secondary / exploratory |
| **Tier 3: Novel archetype emergence** | HIGH CAUTION | Tertiary; per Hybrid-before-Proliferation discipline |

> **Section X is strongest when it constrains
> interpretation without claiming governance.** Section X
> answers "What is this probably NOT?" more reliably than
> "What IS this?".

#### X.6 Hybrid-before-Proliferation operational confirmation (Phase 8.23 small patch)

> **Hybrid-before-Proliferation principle: 2 disciplined
> non-promotions documented**.

| novel archetype candidate | source | discipline applied | outcome |
|---|---|---|---|
| Entity-substrate-heavy / Law-5-substrate-heavy | data-layer (Phase 8.20) | Hybrid-first; single-context observation | NOT promoted |
| Infrastructure-heavy / Compiler-substrate-heavy / Pipeline-static-heavy | build-tooling (Phase 8.22) | Hybrid-first; single-context observation | NOT promoted |

> **Operational pattern**: Each novel archetype candidate
> emerged from boundary terrain pilot, was characterized
> as HYBRID-first, and was NOT promoted to Section X
> archetype tier. Discipline maintained through 2
> consecutive opportunities for typology inflation.

**Boundary anomaly observation (Phase 8.23 surfaced; NOT
formalized)**:

> Per Phase 8.22 user-strategic guidance: "1 context = noise;
> 3 contexts = maybe signal." Boundary anomaly observations
> require **multiple boundary terrain confirmations** before
> archetype candidacy consideration. This may eventually
> formalize as **Boundary Anomaly Replication Threshold**
> rule, but Phase 8.23 conservative discipline:
> **SURFACED ONLY**.

#### X.6 boundary-terrain success note (Phase 8.23 small patch)

> **Phase 8.22 boundary terrain methodology validated**:
> Untouched bounded contexts (build-tooling) provide
> STRONGER falsifiability evidence than partially-analyzed
> contexts (Phase 8.20 pilots). Future predictive utility
> pilots should consider boundary terrain × mid-spectrum
> terrain rotation per user strategic recommendation.

**Suggested future pilot sequence pattern**:
- Boundary terrain (build-tooling-equivalent) →
- Mid-spectrum terrain (theme-config-equivalent) →
- Another boundary terrain
- AVOIDS overfitting Section X around edge cases

> **Status: SUGGESTION ONLY (NOT formalized as protocol).**
> Phase 8.23 conservative discipline; future pilots may
> follow this rotation if appropriate.

### Section X — Closing principle

> **A mature constitution is one that knows what it should
> NOT govern.**
>
> Section X (Analytical Tier) is the formal expression of
> this principle in KB Constitution v2. KB now systematically
> describes realities it explicitly refuses to govern.
> This is **civilization-grade restraint**.

> **Phase 8.19 jurisprudential thesis**:
> **Formalization ≠ Constitutionalization.** Formal structure
> can exist without constitutional sovereignty. KB Section X
> establishes this distinction operationally.

---

## META

### KB-level framing

> WordPress/Gutenberg's architecture is sufficiently coherent
> that recurring structural laws can be **extracted, reused
> predictively, and applied constitutionally** across bounded
> contexts.

This document operationalizes that coherence. The 6 laws
above are not editorial choices about how to describe
WordPress — they are observed structural invariants that
emerged across 64+ chunks of empirical investigation, verified
by audit.

### Document role within KB infrastructure

```
KB infrastructure layer (_meta):

   _meta.dsl-spec               → KB OPERATING SYSTEM
                                  (how chunks are structured)
   _meta.kb-audit-phase7        → VERIFICATION ARTIFACT
                                  (do patterns exist? — descriptive)
   _meta.structural-patterns    → CONSTITUTIONAL LAW LAYER
                                  (how SHOULD KB use patterns? — prescriptive)

These three documents form the KB's self-governance system.
Substantive chunks are authored within their constraints.
```

### Stable doctrine / evolving applications distinction

Laws themselves are **stable** (constitutional invariants).
Their **applications** in specific bounded contexts may
evolve as KB understanding deepens. This is structurally
similar to the "stable doctrine / evolving mechanisms"
distinction (DSL spec, capabilities-and-roles).

### Anticipated revisions

Laws may be revised under these conditions:
- Audit reveals a 7th law (currently not anticipated; would
  require explicit recurrence verification).
- Existing law's classification changes (e.g., Recurring → KB-Wide
  if a previously-Recurring pattern verifies in a 3rd context).
- Anti-confusion list expansion as new common errors surface.
- Predictive frontier updates as untouched contexts are
  entered.

Revisions are appropriate when audit produces explicit evidence;
they are NOT appropriate as theoretical refinements without
empirical basis.

### Constitutional Refinement Patch chronology

**Phase 7.5 Patch (2026-05-09)** — applied after
editor-customization triad (block-filters / slotfills /
editor-hooks) generated multi-candidate adjudication that
exceeded the original 3-tier ladder + 3-class verdict
taxonomy.

**5 changes applied:**

1. **Section A — Pattern maturity ladder expanded**
   (3-tier → 5-tier).
   - Original: Local → Recurring → KB-Wide
   - Patched: Surfaced → Local → Recurring (intra-context)
     → Recurring (cross-context) → KB-Wide

2. **Section A — Pattern verdict taxonomy added**
   (5 classes).
   - Confirmed / Divergent / Hybridized / Surfaced / Deferred
   - Replaces original "Confirmed / Partial / Deferred" used
     in block-filters Constitutional Field Test.

3. **Section C.5 added — Constitutional doctrines (NEW
   section)**:
   - Doctrine 1: Multi-pattern bounded context
   - Doctrine 2: Candidate structural complement ("surfaced,
     not constitutionalized")
   - Doctrine 3: Epistemic Integrity ("constitutional
     integrity > pattern preservation")
   - Doctrine 4: Anticipated constitutional architecture
     (Interception/Mediation/Federation triad — anticipated,
     NOT constitutionalized)

4. **Section D — Adjudication question added** (8th
   pre-write diagnostic).
   - Q8: Does this chunk Confirm, Diverge from, Hybridize,
     or Surface a candidate law?

5. **Cross-document propagation** (operational):
   - Existing chunks (block-filters, slotfills, editor-hooks)
     used Tables A & B in their META sections; the new
     5-class taxonomy retroactively applies to those tables'
     verdict columns. Future chunks should follow the patched
     taxonomy explicitly.

**Patch trigger:**

> editor-hooks chunk surfaced 3 candidates simultaneously
> (Authority Interception Surface confirmed in HOC subset +
> Authority Mediation Surface surfaced + Federation pattern
> cross-context recurrence). The original spec could not
> express this multi-candidate state. Spec evolution caught
> up to ontology evolution.

**Patch principle:**

> **Ontology evolved faster than constitution.**
> **Phase 7.5 patch corrects the lag.**

### Phase 7.6 Constitutional Expansion Patch (2026-05-09)

**Patch trigger:**

> Resolution Surface candidate (surfaced in
> site-building.template-hierarchy-and-resolution, 2026-05-09)
> retroactively verified across 2 prior chunks
> (cascade-aggregation + capabilities-and-roles) revealing
> architectural diversity (Integrated vs Distributed paired
> operations). 3rd promotion event in KB triggered via
> retroactive verification methodology. Spec required
> formalization of: (a) paired operational doctrine, (b)
> architectural variants, (c) retroactive verification
> diagnostic, (d) promotion via retroactive evidence.

**Patch principle:**

> **Phase 7.6 did not expand the number of KB-Wide laws.**
> **It expanded the constitution's ability to describe HOW**
> **laws operationalize across architectural variants AND**
> **historical discovery.**

The number of KB-Wide laws remains 6. The constitution's
operational expressiveness has been expanded.

**5 changes applied:**

1. **Section A — Promotion via forward density OR retroactive
   verification** (note added).
   - Both forward density and retroactive verification
     produce evidence-based promotion when applied with
     epistemic discipline.
   - Resolution Surface reached Recurring (cross-context)
     status via retroactive verification.

2. **Section C.5 — Doctrine 5 added** (Arbitration ↔
   Resolution Paired Operations).
   - Core: Arbitration evaluates competing candidates;
     Resolution actualizes the winner.
   - 5a Integrated architecture (single mechanism)
   - 5b Distributed architecture (multiple mechanisms)
   - Architectural variant integrity principle (do not
     force-fit one variant onto a mechanism exhibiting
     the other).

3. **Section D — Q9 added** (Retroactive Verification
   Trigger).
   - Q9: Does this chunk reveal previously uncodified but
     latent law in earlier chunks?
   - Distinguishes from Q8 (forward adjudication).
   - KB now governs both forward and backward ontology
     refinement.

4. **Resolution Surface promotion documented** (in META
   chronology, NOT in Section B).
   - Status: Recurring (cross-context); strong KB-Wide
     candidate.
   - KB-Wide promotion pending audit verification.

5. **Promotion event chronology** (in META).
   - Promotion Event 1: Authority Interception Surface
     (Surfaced → Local) via slotfills (forward).
   - Promotion Event 2: Authority Mediation Surface
     (Local → Recurring intra-context) via admin-menus
     (forward).
   - Promotion Event 3: Resolution Surface (Surfaced →
     Recurring cross-context) via cascade-aggregation +
     capabilities-and-roles retros (RETROACTIVE).

**Constitutional maturity progression:**

| phase | character |
|---|---|
| Phase 7 (audit) | descriptive verification (do patterns exist?) |
| Phase 7.5 (patch) | epistemic governance (5-tier ladder + 5-class verdicts + 4 doctrines) |
| **Phase 7.6 (patch)** | **operational doctrine (paired operations + architectural variants + retroactive verification)** |

> Phase 7.5 expanded constitution's epistemic governance
> capability.
> **Phase 7.6 expands constitution's operational doctrine
> capability.**

**Deferred Constitutional Candidates** (NOT applied in 7.6):

- **Cross-context PRESENCE tier** (observed in
  admin-ui.settings-api): would refine 5-tier ladder.
  Defer until additional cross-context-PRESENCE situations
  arise (sustained pattern needed; current single
  observation).
- **Bounded context character taxonomy** (5 categories
  observed in site-building): would formalize bounded
  context categorization.
  Defer until categories prove stable across more
  bounded contexts.
- **Topology subtype distinction** (Injection vs Routing,
  observed in slotfills + admin-menus): would refine
  topology pattern.
  Defer until topology character observed in more
  contexts (current 2 observations insufficient).

These three are recognized as **constitutional candidates**;
specifically NOT promoted in 7.6 because evidence density
for each is currently single-instance or near-single-
instance. Phase 7.7 may address one or more if additional
evidence accumulates.

**Patch principle (re-stated for 7.6 specifically):**

> **Phase 7.6 = operational doctrine patch, NOT taxonomy**
> **patch.**

**Cross-document propagation (Phase 7.6):**

- Existing chunks (cascade-aggregation, capabilities-and-roles)
  now have RETROACTIVE REFRAMING sections invoking Doctrine 5
  retroactively.
- Future chunks operating on mechanisms with paired
  operations should explicitly classify variant
  (Integrated / Distributed) per Doctrine 5b.
- Q9 diagnostic should be applied to all future chunks
  (post-Q8 evaluation).

### Constitutional maturity track (post-Phase-7.6)

```
KB constitutional maturity:

   Phase 7 (audit, 2026-05-09)
      ↓ epistemic governance refinement
   Phase 7.5 (patch, 2026-05-09)
      ↓ operational doctrine expansion
   Phase 7.6 (patch, 2026-05-09)
      ↓ ?
   Phase 7.7 (anticipated)
```

After Phase 7.6, KB is:
- **law-aware** (6 KB-Wide Laws documented)
- **promotion-aware** (5-tier ladder + 5-class verdicts + 3
  promotion events documented)
- **retro-aware** (Q9 + 5 RETROACTIVE REFRAMING instances)
- **operationally variant-aware** (Doctrine 5 with Integrated
  / Distributed)

> **This is a serious constitutional milestone.**

### Phase 7.7 Constitutional Refinement Patch (2026-05-09)

**Patch trigger:**

> 3-instance Hybridized Doctrine 5 recurrence within
> "selection-from-candidates" mechanisms (block patterns +
> variations + transforms, all 2026-05-09) revealed structural
> sub-pattern within Doctrine 5 Hybridized variant. Existing
> spec lacked vocabulary for "structurally meaningful but not
> law-grade" patterns. Cross-context PRESENCE state (Resolution
> + Mediation) also lacked explicit notation. Sub-pattern
> governance + breadth/depth distinction required formalization.

**Patch principle:**

> **Phase 7.7 expands constitution to govern Law → Variant**
> **→ Sub-pattern hierarchy. The number of KB-Wide laws**
> **remains 6.**

**4 changes applied:**

1. **Section A — Cross-context PRESENCE notation** added
   (orthogonal to depth tiers).
   - Breadth (cross-context PRESENCE) ≠ Depth (intra-context
     recurrence)
   - 5-tier ladder unchanged; PRESENCE is annotation
   - Examples: "Local + cross-context PRESENCE",
     "Recurring (intra-context) + cross-context PRESENCE",
     "Recurring (cross-context)"

2. **Section C — Doctrine 5c added** (Recurring Sub-patterns
   within Architectural Variants).
   - First formalized sub-pattern: Selection from Candidates
   - Sub-patterns reside WITHIN parent doctrine
   - Promotion path uses existing ladder + sub-pattern
     annotation
   - Sub-patterns do NOT become KB-Wide laws

3. **Section D — Q10 added** (Sub-pattern Emergence post-write
   diagnostic).
   - Q10: Does this chunk reveal recurring sub-structure
     within an existing law/variant?
   - Completes post-write diagnostic triad: Q8 forward / Q9
     retroactive / Q10 sub-pattern
   - KB now governs forward + backward + internal structural
     refinement

4. **META — Phase 7.7 chronology + Phase 7.8 audit gate
   criteria** added.

**Constitutional maturity progression:**

| phase | character |
|---|---|
| Phase 7 (audit) | descriptive verification |
| Phase 7.5 (patch) | epistemic governance |
| Phase 7.6 (patch) | operational doctrine |
| **Phase 7.7 (patch)** | **sub-pattern governance** |

> Phase 7.5 governs HOW evidence behaves.
> Phase 7.6 governs HOW laws operationalize.
> **Phase 7.7 governs HOW laws internally differentiate.**

**Deferred Constitutional Candidates** (NOT applied in 7.7):

- **Bounded context character taxonomy** (5 categories
  observed in site-building): would formalize bounded context
  categorization. Defer until categories prove stable across
  more bounded contexts.
- **Topology subtype distinction** (Injection vs Routing):
  Routing single-context manifestation (admin-ui only).
  Defer until cross-context routing observed.

These remain "Constitutional Candidates"; specifically NOT
promoted in 7.7 because evidence density for each is
single-context observation. Future patches may address.

### Phase 7.8 Audit Gate Criteria (Phase 7.7 patch defines)

**KB-Wide audit verification** is required for any candidate
seeking promotion to KB-Wide LAW status. Audit gate criteria
(ALL 5 conditions required):

| # | criterion |
|---|---|
| 1 | **Bounded context PRESENCE ≥ 4** — candidate observed in 4+ bounded contexts |
| 2 | **Architectural variants ≥ 2** — candidate manifests across multiple Doctrine 5 architectural variants (Integrated / Distributed / Hybridized) OR equivalent variant taxonomy |
| 3 | **Intra-context density ≥ 1** — candidate sustained pattern within at least one bounded context (multiple chunks per context) |
| 4 | **Q10 sub-pattern check completed** — no contradictory sub-patterns identified that would invalidate candidate's law-grade status |
| 5 | **Forward + retroactive evidence both contributing** — candidate evidence base includes both forward authoring + retroactive verification (not single-method evidence) |

**Audit gate purpose**: prevent arbitrary promotion + ensure
candidates have **multi-dimensional evidence** before law-
grade elevation. KB-Wide LAW status is constitutional
commitment; criteria enforce evidentiary discipline.

**Candidates currently meeting criteria** (as of 2026-05-09,
post-Phase-7.7):

| candidate | criteria met | audit ready |
|---|---|---|
| **Resolution Surface** | 5/5 (4-context PRESENCE + 3 variants + intra-context density × 2 + Q10 completed via Phase 7.7 sub-pattern formalization + 1 forward + 4 retros) | **YES — Phase 7.8 audit candidate** |
| Authority Mediation Surface | 3/5 (2-context PRESENCE + 1 variant + intra-context density × 1 + Q10 N/A + forward only) | NO — insufficient evidence |
| Authority Interception Surface | 2/5 (1-context PRESENCE + 1 variant + intra-context density × 1) | NO — insufficient evidence |
| Administrative Routing Surface | 1/5 (single-context Surfaced) | NO — insufficient evidence |

**Resolution Surface** is the ONLY current candidate meeting
all 5 audit gate criteria. Phase 7.8 audit verification
recommended next.

### Phase 7.8 Constitutional Audit (2026-05-09)

**Audit subject**: Resolution Surface KB-Wide LAW promotion
candidacy.

**Audit gate verification**: 5/5 criteria MET
(see `_meta/kb-audit-phase8-resolution-surface.md`).

**Audit verdict**: **REFUSED** — KB-Wide promotion declined.

**Refusal rationale**:
- Structural invariance ESTABLISHED (4/5 bounded context
  character categories + falsifiable predictions + clear
  anti-confusion + operational consequence)
- BUT structural relationship to Arbitration Compiler
  (Law 4) is best governed at DOCTRINE LAYER (Doctrine 5,
  Phase 7.6 patch) rather than independent law layer
- Promoting would duplicate Doctrine 5's pairing governance
- KB-Wide LAW tier reserved for INDEPENDENTLY meaningful
  structural invariants; Resolution's meaning is INHERENT
  to pairing with Arbitration

**Constitutional significance**:
- **First explicit refusal event in KB**
- Audit gate criteria validated as **admissibility (NOT
  promotion mandate)**
- Demonstrates KB capability to refuse promotion despite
  criteria fulfillment when structural fit warrants
  lower-tier classification
- Refusal as constitutional integrity: both confirmation
  AND refusal serve KB methodological maturity

**Spec changes**: Section B remains 6 KB-Wide Laws.
Doctrine 5 (Phase 7.6 patch) continues to govern
Arbitration ↔ Resolution paired operations. Resolution
Surface retains Recurring (cross-context) status +
Selection from Candidates sub-pattern parent role.

**Constitutional principle introduced (Phase 7.8)**:

> **Audit gate criteria establish ADMISSIBILITY for promotion**
> **consideration. Promotion DECISION requires additional**
> **structural fit analysis: is the candidate's meaning**
> **INHERENT to existing law/doctrine relationship, or**
> **INDEPENDENTLY meaningful at law tier?**
> **Inherent meaning warrants lower-tier classification;**
> **independent meaning warrants KB-Wide promotion.**

May warrant Phase 7.9 patch as Q11 (post-audit structural
relationship test) if refusal pattern recurs.
**(Note: Q11 formalized in Phase 8.5 patch — see below.)**

### Phase 8.x Constitutional Audit — Authority Mediation Surface (2026-05-10)

**Audit subject**: Authority Mediation Surface constitutional
promotion candidacy (5 chunks × 4 bounded contexts × 5
distinct gating mechanisms).

**Audit gate type**: Governance-intensive (5 standard + 2
specific = 7 criteria; first deployment of bifurcated gate).

**Audit gate verification**: 6/7 criteria fully MET; 1
PARTIALLY MET (criterion 5: forward + retroactive evidence
both contributing — Mediation has 5 forward + 0 retro).
See `_meta/kb-audit-phase8-mediation-surface.md`.

**Audit verdict**: **Doctrine-tier promotion CONFIRMED;
KB-Wide LAW promotion REFUSED.**

**Doctrine 6 created**: Authority Access Mediation Doctrine
with 5 sub-elements (6a Capability-gated / 6b Routing-gated /
6c Cognitive-surface-gated / 6d Subscription-gated / 6e
Context-reassignment-gated mediation).

**KB-Wide LAW refusal rationale**:
- Architectural concentration (3/5 character categories;
  governance-domain-heavy)
- Criterion 5 partial (no retroactive verification)
- Non-trivial relationship with Law 1 (Declaration ≠
  Exposure)
- Predictions for Schema authority + Compiler/runtime
  categories WEAK

**Doctrine-tier promotion rationale**:
- 5 distinct gating mechanisms = strong structural diversity
- Mediation is independent of Doctrine 5 (does NOT consistently
  precede Arbitration)
- 3-form admin-ui intra-context density (strongest in KB)
- Substantial evidence warrants formal constitutional home
- Doctrine-tier preserves KB-Wide LAW tier integrity AND
  preserves future re-audit pathway

**Constitutional significance**:
- **Second refusal event in KB** (parallel to Phase 7.8
  Resolution refusal)
- **First Doctrine-tier formal promotion event in KB**
  (prior 4 promotions were candidate-tier)
- **First audit-driven doctrine creation**
- Validates 3-outcome adjudication: KB-Wide LAW / Doctrine-
  tier / Recurring-cross-context maintenance
- Validates governance-intensive audit gate bifurcation

### Phase 8.5 Constitutional Synchronization Patch (2026-05-10)

**Trigger**: Phase 8.x audit verdict produced Doctrine 6
formal promotion AND introduced governance-intensive audit
gate criteria. Constitution layer (`structural-patterns.md`)
required synchronization with audit's documented governance
reality. Failure to synchronize would create ontology >
constitution mismatch (Phase 7.5-style drift) and increase
retro patch burden for future chunks.

**4 changes applied to structural-patterns.md**:

1. **Section C.5 expansion**: Doctrine 6 (Authority Access
   Mediation Doctrine) inserted after Doctrine 5c with:
   - Core formulation (governance through structurally gated
     authority-access choreography)
   - Constitutional positioning vs Doctrine 5 / Law 1 / Law 4
   - 5 documented sub-elements (6a-6e) with chunk-level
     manifestation evidence
   - Sub-element promotion path (parallel to Doctrine 5c)
   - Doctrine 6 vs Law 1 relationship analysis
   - Future KB-Wide LAW promotion pathway preserved

2. **Section D Q11 added**: Audit-time structural tier
   classification question
   - "Does audit evidence indicate this candidate is better
     constitutionalized as Doctrine-tier rather than KB-Wide
     LAW tier?"
   - Distinguishes from Q8/Q9/Q10 (per-chunk vs per-audit
     invocation)
   - Constitutional principle: promotion is NOT binary;
     3-outcome adjudication explicit

3. **Section D audit gate bifurcation**: Standard gate (5
   criteria) for general candidates + Governance-intensive
   gate (5 standard + 2 specific = 7 criteria) for
   governance-mechanism candidates
   - Criterion 6: Gating abstraction independence
   - Criterion 7: Structural consequence
   - Selection between gate types per candidate's primary
     character
   - Prevents overfitting Resolution-calibrated criteria to
     governance-domain structures

4. **META chronology entry** (this section): Phase 8.x audit
   verdict + Phase 8.5 patch documentation

**Constitutional principle introduced (Phase 8.5)**:

> **Constitutional promotion is NOT binary.** Audit may
> produce KB-Wide LAW promotion, Doctrine-tier formal
> promotion, OR Recurring-cross-context maintenance/refusal.
> Tier selection is structurally-fit-based, NOT
> evidence-quantity-based.

> **Audit gate criteria are calibration-sensitive.**
> Resolution-calibrated 5 criteria don't fully evaluate
> governance-mechanism candidates; bifurcated gate ensures
> tier-appropriate evaluation.

> **Constitutional infrastructure must synchronize with
> documented governance reality.** Ontology > constitution
> drift creates conceptual debt; periodic synchronization
> patches (Phase 7.5/7.6/7.7/8.5) preserve KB integrity.

**Doctrine count update**: 5 → 6 doctrines.
**Law count update**: 6 → 6 laws (UNCHANGED; KB-Wide LAW
tier stable since Phase 7).

**KB constitutional progression**:

| Phase | Function |
|---|---|
| 7 | Verification (descriptive audit) |
| 7.5 | Governance refinement (5-tier ladder + 5-class verdicts + 4 doctrines) |
| 7.6 | Operational doctrine (Doctrine 5: Arbitration ↔ Resolution paired operations) |
| 7.7 | Sub-pattern governance (Doctrine 5c: Selection from Candidates) |
| 7.8 | Refusal discipline (1st refusal event: Resolution KB-Wide REFUSED) |
| 8.x | Doctrine-tier adjudication (1st Doctrine-tier formal promotion: Doctrine 6) |
| 8.5 | Infrastructure synchronization (constitution catches up to documented governance reality) |
| **8.6** | **Re-audit discipline (1st re-audit event: Doctrine 6 KB-Wide RE-REFUSED + HARD/SOFT mode observation)** |
| **8.7** | **Doctrine architectural synchronization (HARD/SOFT mode + sub-elements 6f-6h sync to constitution layer)** |
| **8.8** | **Constitutional consolidation (KB Constitution v1.5 declaration; Phase 7→8.7 snapshot)** |
| **8.9** | **Frontier retro arc (P1 DIVERGENT style-engine + P3 ADDITIVE/CONFIRM supports-field; HARD/SOFT formalization threshold MET)** |
| **8.10** | **Doctrine architectural formalization (HARD/SOFT formalized as 6-HARD/6-SOFT variants; 6i sub-element added; capability vs mediation distinction; KB Constitution v1.6 declaration)** |
| **8.12** | **Multi-lens frontier retro (directive-protocol; Bridge Pattern PROMOTED Local→Recurring cross-context; Computational-architectural 2nd instance; multi-lens retro methodology surfaced)** |
| **8.12.5** | **Dual-lens Bridge Pattern frontier test (register-client-js; 4th Bridge bounded context + identity-binding sub-character; Law 3 dependence STRONG observed; Bridge audit gate 5/5 MET)** |
| **8.13** | **Bridge Pattern KB-Wide LAW audit (REFUSED + LAW SUB-PATTERN FORMALIZED Law 3b; 1st LAW-tier sub-pattern formalization; KB Constitution v2 trigger via hierarchy restructuring)** |
| **8.14** | **Constitutional hierarchy sophistication (Law 3b + 5 sub-characters formalized; Q11 5-outcome model; Law Sub-pattern Gate Criteria; KB Constitution v2 declaration)** |
| **8.15** | **Constitutional epoch snapshot (KB Constitution v2 epochally established; 3 epochs documented; first constitutional historiography document)** |
| **8.16** | **v2-deployment validation (runtime-state Q9 retro; Outcome A; constitutional restraint)** |
| **8.16b** | **v2-deployment lifecycle completion (hydration Q9 retro; Outcome A; Law 3b 3b-react lifecycle breadth observation)** |
| **8.16c** | **First v2-native bounded context closure ceremony (interactivity; sophistication > inflation validated)** |
| **8.17** | **First cross-era closure comparative study (constitutional ecology hypothesis surfaced; ecological closure verdict)** |
| **8.18** | **Civilization archetype constitutional admissibility test (5th constitutional formalization REFUSED; 1st non-constitutional analytical formalization)** |
| **8.19** | **Constitutional Meta-Architecture (Section X Analytical Tier formalized; constitutional vs analytical boundary architecture; Observatory not Government principle)** |
| **8.20** | **V2 falsifiability pilot (1st predictive utility test; pre-registered hypothesis methodology; MODERATE V2 → REFINEMENT branch; constitutional re-audit pathway preserved but UNTRIGGERED)** |
| **8.21** | **Section X predictive modality refinement (X.3a/X.3b dominant vs constraint prediction split; absence-prediction stronger than dominance-prediction; constitutional constraint science framing surfaced)** |
| **8.22** | **Boundary terrain V2 pilot (build-tooling; HYBRID-first pre-registration; X.3b STRONG empirically validated; 2nd novel archetype candidate disciplined non-promotion; boundary-terrain methodology validated)** |
| **8.23** | **Section X.6 small validation patch (X.3a/X.3b empirical validation note + Hybrid-before-Proliferation operational confirmation + boundary-terrain success note + boundary anomaly replication observation surfaced)** |

**Deferred to future patches** (not addressed in 8.5):
- Mediation criterion 5 partial gap (retro verification
  pending: directive-protocol / switch_to_blog / nonces)
- Bridge Pattern audit (currently Local; needs Recurring
  cross-context status before audit)
- Semantic substrate bounded context character formalization
  (single-bounded-context observation; cross-context
  verification pending)
- Tri-modal governance bounded context formalization
  (admin-ui + editor-customization observation; cross-context
  verification pending)
- Administrative Signaling Surface candidate adjudication
  (sub-form vs independent UNDETERMINED)

Per Phase 8.5 deferred-candidates discipline, these remain
"Constitutional Candidates"; future patches may address as
evidence accumulates.

**Anticipated future patches:**

- **Phase 8.6 (anticipated)**: Mediation Q9 retro batch
  results may strengthen Mediation toward KB-Wide LAW
  re-audit (cross-context expansion + retro verification).
- **Phase 8.7 (anticipated)**: Bridge Pattern Recurring
  (cross-context) audit if Q9 retros confirm 3+ bounded
  context Bridge instances.
- **Phase 9 (anticipated)**: KB Constitution v2 hypothesis
  re-evaluation if cumulative doctrine-tier expansion
  warrants Constitution-level restructuring.

Each future patch should follow the established pattern:
explicit chronology entry, trigger documentation, principle
articulation, deferred candidates list.

**Note**: Phase 7.9 / 7.10 anticipations from Phase 7.8
chronology entry have been SUPERSEDED by Phase 8.x + 8.5.
Q11 formalized in Phase 8.5 (not 7.9); audit gate refinement
formalized in Phase 8.5 bifurcation (not 7.10).

**Note**: Phase 8.8 consolidation produced KB Constitution
v1.5 declaration (separate document:
`_meta/kb-consolidation-phase7-8-8.md`); Phase 8.10 (this
section's later sub-section) declares KB Constitution v1.6.

### Phase 8.6 Constitutional Re-audit — Authority Mediation Surface (2026-05-10)

**Audit subject**: Authority Mediation Surface (Doctrine 6)
re-audit at increased evidence (post-REST retro + Schema retro
+ nonces forward; Phase 8.5+ accumulation).

**Audit gate type**: Governance-intensive (7 criteria; 2nd
deployment of bifurcated gate).

**Audit gate verification**: **7/7 criteria FULLY MET** (vs
Phase 8.x: 6/7 with 1 PARTIAL).

**Phase 8.6 NEW evaluation dimensions** (3 added beyond
standard 7 criteria):
- D1: HARD/SOFT coherence — Observation only; insufficient
  evidence for formalization
- D2: Law 1 independence stress test — Partial (3/8
  sub-elements independent; 5/8 instantiate Law 1)
- D3: Structural necessity — Necessary but flows from Law 1
  invariance

**Audit verdict**: **KB-Wide LAW promotion REFUSED;
Doctrine 6 status RETAINED with HARD/SOFT mode observation.**

**Refusal rationale**:
- Architectural ubiquity 3/5 standard character categories
  (BELOW Resolution Phase 7.8 benchmark of 4/5)
- Compiler/runtime + Composition runtime categories STILL
  UNTESTED
- D2 Law 1 independence is PARTIAL (5/8 sub-elements
  instantiate Law 1)
- D1 HARD/SOFT distinction is single-soft-instance (6h only)
- D3 structural necessity flows from Law 1 invariance, not
  separate invariance

**Constitutional significance**:
- **3rd refusal event in KB** (after Phase 7.8 Resolution +
  Phase 8.x Mediation)
- **1st RE-AUDIT EVENT in KB** (re-evaluation of Phase 8.x
  Mediation candidacy at increased evidence)
- **HARD/SOFT mode observation surfaced** (Phase 8.7 patch
  documents)
- Validates re-audit principle: increased evidence does NOT
  mandate tier elevation if structural fit unchanged
- Validates Q11 4th outcome (re-audit refinement; tier
  preservation + architecture deepening)
- KB Constitution v1 stability extended through 3 audit events

**Spec changes deferred to Phase 8.7 synchronization patch**.

### Phase 8.7 Constitutional Synchronization Patch — Doctrine Architecture (2026-05-10)

**Trigger**: Phase 8.6 re-audit verdict produced HARD/SOFT
architectural mode observation + 3 new sub-elements (6f, 6g,
6h) requiring constitution-layer synchronization. Without
synchronization, Doctrine 6 would be misread as monolithic
(missing HARD/SOFT distinction) AND would not include 6f-6h
sub-elements documented in audit + Q9 retro work. Phase
7.5-style ontology > constitution drift risk.

**Critical disclaimer**: Phase 8.7 is **synchronization
ONLY**, NOT doctrinal inflation. HARD/SOFT distinction is
documented as **observation** (NOT formalized as Doctrine 6a
/ 6b split, NOT formalized as architectural variants
analogous to Doctrine 5 Integrated/Distributed/Hybridized).

**4 changes applied to structural-patterns.md**:

1. **Section C.5 Doctrine 6 sub-element expansion**: 5
   sub-elements (6a-6e) → **8 sub-elements (6a-6h)**.
   Added:
   - 6f Origin-authenticity-gated mediation (plugin-dev.nonces;
     Phase 8.5+ forward)
   - 6g Endpoint-permission-gated mediation
     (plugin-dev.register-rest-route Q9 retro; Phase 8.5+)
   - 6h Structural-participation-gated mediation
     (block-authoring.hierarchy-constraints Q9 retro;
     Phase 8.5+; first SOFT-mode instance)
   - Composite manifestation observation (REST route)
     surfaced

2. **Section C.5 HARD/SOFT architectural mode observation**:
   Doctrine 6 sub-elements classified as HARD (7 sub-elements:
   6a-6g) or SOFT (1 sub-element: 6h). Critical disclaimer:
   observation only; not formalized as architectural variants
   pending evidence threshold (≥2 SOFT instances). Future
   chunk authoring should classify gating mechanism as HARD
   or SOFT when applying Doctrine 6 vocabulary.

3. **Section D Q11 refinement (4th outcome added)**:
   Re-audit may PRESERVE TIER while REFINING doctrine
   ARCHITECTURE. "Same tier, deeper structure" precedent
   codified.
   - Original Q11 outcomes: Promote / Refuse / Retain
   - Phase 8.7 added: **Refine** (re-audit refinement;
     tier maintained + architecture deepened)

4. **META chronology entries** (this section): Phase 8.6
   audit verdict + Phase 8.7 patch documentation + KB phase
   progression table extension (8.6 + 8.7 rows).

**Constitutional principle introduced (Phase 8.7)**:

> **Audit may strengthen constitutional precision without
> changing tier.** Constitutional sophistication grows
> through 3 audit-outcome modes: PROMOTE / REFUSE / REFINE.
> Refinement is the audit-outcome mode where tier is
> preserved but doctrine architecture is deepened
> (sub-elements / modes / compositions).

> **Constitutional infrastructure must synchronize with
> documented audit findings.** Audit-derived architectural
> observations (HARD/SOFT modes, composite manifestations,
> sub-element additions) must reach constitution layer to
> prevent ontology > constitution drift.

> **Synchronization patches preserve constitutional
> integrity at infrastructure layer; they do NOT inflate
> doctrines/laws.** Phase 8.7 adds 3 sub-elements and 1
> mode observation while keeping doctrine count at 6 and
> law count at 6.

**Doctrine count update**: 6 → 6 doctrines (UNCHANGED;
Doctrine 6 strengthened in-place).
**Law count update**: 6 → 6 laws (UNCHANGED; KB-Wide LAW
tier stable since Phase 7).
**Doctrine 6 sub-element count update**: 5 → 8 sub-elements.
**Architectural mode observations**: 0 → 1 (HARD/SOFT under
Doctrine 6, observation only).

**Promotion / refusal registry update** (governance evolution
documentation):

| event class | count | events |
|---|---|---|
| Candidate-tier promotions | 5 | slotfills (Interception); admin-menus (Mediation); capabilities-and-roles Q9 (Resolution Distributed); notices (Bridge Pattern Local); REST retro + Schema retro (Doctrine 6 sub-elements 6g + 6h) |
| Doctrine-tier promotions | 1 | Phase 8.x Doctrine 6 creation |
| KB-Wide LAW promotions | 0 | (none — KB Constitution v1 stable) |
| Refusal events | 3 | Phase 7.8 Resolution; Phase 8.x Mediation initial; Phase 8.6 Mediation re-audit |
| Re-audit events | 1 | Phase 8.6 Mediation re-audit |
| Synchronization patches | 2 | Phase 8.5 (Doctrine 6 formalization); Phase 8.7 (Doctrine 6 architectural sync) |

> **Constitutional governance evolution principle**:
> Constitutional maturity depends NOT just on promotions,
> but on **promotion + refusal + re-audit + synchronization
> + refinement** events collectively. KB now operates
> across all 5 event classes.

**Explicitly DEFERRED to future patches** (Phase 8.7
discipline):
- HARD/SOFT formalization as Doctrine 6 architectural
  variants (requires ≥2 SOFT instances)
- Tri-modal governance bounded context character
  formalization (3 instances observed; needs explicit
  patch)
- Semantic substrate bounded context character category
  addition to standard taxonomy (single bounded context
  manifestation)
- Bridge Pattern Recurring (cross-context) audit (currently
  Local at 3 instances × 2 contexts; needs 3rd context
  before audit)
- Composite Doctrine 6 manifestation pattern formalization
  (2 instances observed; needs additional verification)
- Mediation KB-Wide LAW re-re-audit (Phase 8.8+ if 4/5
  standard category coverage achieved)

Per Phase 8.7 conservative discipline, these remain
"Constitutional Candidates"; future patches may address
as evidence accumulates.

**Anticipated future patches:**

- **Phase 8.8 (anticipated)**: KB Constitutional State
  Consolidation update (Phase 7-8.6 → Phase 7-8.7+ snapshot).
  Should occur AFTER Phase 8.7 sync; consolidation snapshots
  synchronized constitution.
- **Phase 8.9+ (anticipated)**: Forward chunk authoring
  resumption under Phase 8.7 patched spec; chunks should
  classify Doctrine 6 manifestations as HARD/SOFT.
- **Phase 8.10+ (anticipated)**: Mediation re-re-audit if
  Compiler/runtime OR Composition runtime category
  Mediation manifestation confirmed via additional retros
  (style-engine / site-building candidates).

Each future patch should follow established Phase 7.5 / 7.6 /
7.7 / 8.5 / 8.7 patterns: explicit chronology entry, trigger
documentation, principle articulation, deferred candidates
list.

### Phase 8.9 Frontier Retro Arc (2026-05-10)

**Trigger**: Phase 8.8 consolidation Section E frontier map
identified P1-P7 constitutional pressure vectors; P1 (Compiler/
runtime Doctrine 6 test) + P3 (HARD/SOFT formalization test)
elevated as primary pressure vectors after Phase 8.6 re-audit.

**Methodology**: 2 Q9 retros conducted in sequence at
Doctrine 6 frontier:
1. P1: `style-engine.preset-materialization` Q9 retro
2. P3: `block-authoring.supports-field` Q9 retro

**Phase 8.9 retro arc results**:

**P1 (style-engine.preset-materialization) — verdict:
DIVERGENT**

- Honest negative finding: Doctrine 6 does NOT manifest in
  style-engine preset-materialization
- Compiler/runtime character category VERIFIED ABSENT (1
  specific chunk)
- Doctrine 6 architectural ubiquity ceiling (3/5 standard
  categories) REAFFIRMED
- NEW observation surfaced: "governance-architectural"
  character distinction (Doctrine 6 manifests in governance-
  architectural bounded contexts but not computational-
  architectural)
- Constitutional value: boundary clarification

**P3 (block-authoring.supports-field) — verdict:
ADDITIVE + CONFIRM**

- 7+ clean SOFT-mode mediation candidates surfaced
  (inserter, multiple, lock, html, reusable, renaming,
  visibility) within governance-toggles editor-only subgroup
- 14+ flags screened as capability declarations (NOT
  mediation; Law 1 trap avoided)
- NEW Doctrine 6 sub-element 6i (Editor-affordance-gated
  mediation, SOFT) added with 7+ instance evidence
- HARD/SOFT formalization threshold (≥2 SOFT instances)
  DECISIVELY MET
- NEW sub-pattern observation: default-direction split
  within 6i (7-instance recurrence)
- Constitutional value: internal architecture depth advance

**Constitutional significance (Phase 8.9 arc as whole)**:
- 2 retros × 2 distinct outcomes (divergent boundary
  clarification + additive depth advance) demonstrate
  retro discipline produces COMPLEMENTARY constitutional
  information types
- Doctrine 6 growth trajectory SHIFTED from breadth (KB-Wide
  LAW pressure) to depth (HARD/SOFT formalization)
- Phase 8.10 formalization patch BECAME VIABLE via P3
  threshold satisfaction
- KB Constitution v1.5 (declared Phase 8.8) operationally
  validated via Phase 8.9 retros

**Spec changes deferred to Phase 8.10 formalization patch**.

### Phase 8.10 Doctrine Architectural Formalization Patch — HARD/SOFT Variants (2026-05-10)

**Trigger**: Phase 8.9 retro arc (P1 DIVERGENT confirming
breadth ceiling + P3 ADDITIVE/CONFIRM meeting SOFT-mode
formalization threshold) produced sufficient evidence for
HARD/SOFT formalization. Phase 8.7 documented HARD/SOFT as
observation only; Phase 8.10 PROMOTES from observation →
formalized architectural variants.

**Critical disclaimer**: Phase 8.10 is **doctrine-tier
architectural formalization**, NOT doctrine count expansion,
NOT KB-Wide LAW promotion. Doctrine count remains 6.
Variant layer expands within Doctrine 6 (parallel to
Doctrine 5's Integrated/Distributed/Hybridized variant
structure).

**4 changes applied to structural-patterns.md (mandatory
patch set)**:

1. **Section C.5 6-HARD / 6-SOFT variant formalization**:
   HARD/SOFT promoted from observation → formalized
   architectural variants of Doctrine 6.
   - 6-HARD Variant (Access-Control Mode): runtime per-access;
     non-bypassable; access-control function; sub-elements
     6a-6g (7)
   - 6-SOFT Variant (UX-Guidance Mode): registration-time
     declaration; UI-layer enforcement; bypassable; UX-
     guidance function; sub-elements 6h-6i (2)
   - Variant comparison table + bounded-context-character
     correspondence observation

2. **Section C.5 6i sub-element formal addition**: Editor-
   affordance-gated mediation (block-authoring/supports-field
   + governance-toggles) formally documented with 7+
   underlying flag instances + composite manifestation
   character note.

3. **Section C.5 Capability declaration vs Mediation
   distinction (Law 1 trap warning)**: Phase 8.9 P3 retro
   exposed Law 1 trap risk in Doctrine 6 application;
   formal screening test documented (capability declaration
   vs gated accessibility choreography).

4. **Section D Q11 extension (Phase 8.10 codification)**:
   Re-audit + retro arc may DEEPEN doctrine architecture
   WITHOUT tier shift via formalization patch sequence.
   Q11 4th outcome (Refine) further refined into:
   - (d-1) Re-audit refinement (Phase 8.7 codification)
   - (d-2) Re-audit + retro arc operationalization (Phase
     8.10 codification)

**Deferred to Phase 8.11+** (NOT formalized in 8.10):
- Default-direction split sub-pattern formalization (Phase
  8.11 candidate; 7-instance recurrence within 6i)
- Composite SOFT subtype taxonomy (cross-context
  verification needed)
- Full governance-architectural character taxonomy (cross-
  context verification needed)
- Law 1 independence stress test refinement under variant
  formalization

**Constitutional principles introduced (Phase 8.10)**:

> **A doctrine may mature primarily through internal
> architectural differentiation (variant formalization +
> sub-element accumulation + sub-pattern recognition)
> rather than tier ascension.** This is a valid mature
> constitutional growth pathway.

> **Constitutional symmetry achieved**: Doctrine 5 governs
> operationalization with 3 architectural variants
> (Integrated/Distributed/Hybridized + 5c sub-pattern);
> Doctrine 6 governs accessibility with 2 architectural
> variants (6-HARD/6-SOFT). Both doctrines now exhibit
> variant-layer architectural sophistication.

> **Law 1 trap principle**: "Declaration ≠ Mediation
> automatically." Capability declarations may exhibit Law 1
> character without Doctrine 6 character. Honest screening
> required to distinguish capability surfaces from gating
> surfaces.

> **KB Constitution v2 hypothesis refinement**: v2 may not
> require new KB-Wide Laws. v2 may emerge through doctrine
> architecture sophistication alone. Hierarchy depth may
> substitute for tier expansion.

**Doctrine 6 sub-element count update**: 8 → 9 (6a-6i).
**Doctrine 6 architectural variants**: 0 formalized → 2
(6-HARD + 6-SOFT).
**Doctrine count**: 6 → 6 (UNCHANGED).
**Law count**: 6 → 6 (UNCHANGED).

### KB Constitution v1.6 declaration (Phase 8.10)

This patch declares **KB Constitution v1.6** as the current
operating constitutional version.

| Version | Meaning |
|---|---|
| v1 | Phase 7-8 foundational constitution |
| v1.5 | Phase 8.5-8.7 doctrine-tier + re-audit + synchronization maturity (declared Phase 8.8) |
| **v1.6** | **Phase 8.10 doctrine architectural sophistication (HARD/SOFT variants formalized; 6i added; capability vs mediation distinction)** |
| v2 | (RESERVED) First successful new KB-Wide Law OR hierarchy restructuring |

**v1.5 → v1.6 distinction (semantic significance)**:
- v1.5 = governance sophistication (audit infrastructure,
  re-audit discipline, synchronization mechanisms)
- v1.6 = **doctrine architectural sophistication** (variant
  formalization within doctrines, architectural depth via
  internal differentiation)
- Different categories of constitutional advance; warrant
  distinct version designation

**v1.5 → v1.6 advance principle**:
> Constitutional version advancement may occur through
> doctrine architectural sophistication (within-doctrine
> variant formalization) without law-tier expansion. v1.6
> represents this category of mature constitutional
> development.

**Versioning progression updated**:

| Version | Trigger | Status |
|---|---|---|
| v1 | Phase 7-8 KB-Wide Laws + 5 doctrines establishment | STABLE |
| v1.5 | Phase 8.5-8.7 governance sophistication | STABLE (Phase 8.8 declaration) |
| **v1.6** | **Phase 8.10 doctrine architectural sophistication** | **CURRENT (this patch declaration)** |
| v2 | First KB-Wide LAW expansion OR hierarchy restructuring | RESERVED |

**Anticipated future patches:**

- **Phase 8.11 (anticipated; superseded)**: Default-direction
  sub-pattern formalization deferred; Phase 8.13 audit
  superseded as priority frontier.
- **Phase 8.12 (executed)**: directive-protocol Q9 retro
  (multi-lens); Bridge Pattern PROMOTED Local→Recurring
  cross-context.
- **Phase 8.13 (executed)**: Bridge Pattern audit; LAW
  SUB-PATTERN FORMALIZED (Law 3b).
- **Phase 8.16+ (anticipated)**: Constitutional consolidation
  update (v2 declaration if Phase 8.14 patch successful).

### Phase 8.13 Constitutional Audit — Bridge Pattern (2026-05-10)

**Audit subject**: Bridge Pattern KB-Wide LAW promotion
candidacy.

**Audit gate type**: Standard (5 criteria) + 4 NEW Phase 8.13
evaluation dimensions (Law 3 independence stress test /
direction-medium specificity / cross-character-category
breadth / sub-character coherence).

**Audit gate verification**: 5/5 Standard criteria FULLY MET
(post-Phase-8.12.5 register-client-js Q9 retro).

**Audit verdict**: **KB-Wide LAW (Law 7) promotion REFUSED;
LAW SUB-PATTERN formalization CONFIRMED (Law 3b — Cross-
Runtime Authority Continuity Bridge).**

**Refusal rationale (Law 7)**:
- D1 Law 3 independence: NOT INDEPENDENT (100% Law 3
  dependence; 5/5 instances)
- D2 Direction/medium specificity: NOT SUFFICIENT for
  independence (modality, not invariant)
- All 5 Bridge instances manifest Law 3 (structural
  inherence)
- Phase 7.8 + Phase 8.6 precedents apply (inherent structure
  warrants lower-tier classification)

**Sub-pattern formalization rationale (Law 3b)**:
- D3 cross-character-category breadth STRONG (4 categories)
- D4 sub-character coherence STRONG (5 sub-characters share
  invariant)
- Constitutional independence test: NOT independent of Law 3
  → sub-pattern appropriate
- 5 sub-characters documented (3b-static / 3b-asym / 3b-round /
  3b-react / 3b-ident)

**Constitutional significance**:
- **4th refusal event in KB** (after Phase 7.8 Resolution +
  Phase 8.x Mediation + Phase 8.6 Mediation re-audit)
- **1st LAW-tier sub-pattern formalization in KB** (Law 3b)
- **NEW constitutional layer creation**: Law sub-patterns
  (distinct from Doctrine sub-patterns; operate at LAW tier)
- **3 NEW constitutional precedents established**:
  - Precedent A: Recurring candidates may mature into LAW
    SUB-PATTERN rather than NEW LAW
  - Precedent B: Constitutional evolution may proceed through
    HIERARCHY RESTRUCTURING rather than tier ascension
  - Precedent C: Constitutional independence (NOT evidence
    quantity, NOT cross-context breadth) is load-bearing
    criterion for KB-Wide LAW promotion
- **KB Constitution v2 trigger condition met via hierarchy
  restructuring** (NEW Law sub-pattern layer creation)

**Spec changes deferred to Phase 8.14 synchronization patch**.

### Phase 8.14 Constitutional Hierarchy Sophistication Patch — Law 3b + KB Constitution v2 (2026-05-10)

**Trigger**: Phase 8.13 audit verdict produced LAW SUB-PATTERN
formalization (Law 3b) — first LAW-tier sub-pattern in KB.
Constitution layer required hierarchy restructuring
synchronization. Without synchronization, Bridge Pattern
audit verdict would remain unformalized + future Law sub-
pattern adjudications would lack constitutional precedent.

**Critical disclaimer**: Phase 8.14 introduces NEW
CONSTITUTIONAL LAYER (Law sub-patterns) — qualitatively
different from prior synchronization patches (Phase 8.5 /
8.7 / 8.10 worked WITHIN existing layers). This is
**hierarchy restructuring**, not mere refinement.

**5 changes applied to structural-patterns.md**:

1. **Section B Law 3 expansion**: Law 3 acquires sub-pattern
   architecture. Law 3b — Cross-Runtime Authority Continuity
   Bridge formalized with 5 sub-characters (3b-static /
   3b-asym / 3b-round / 3b-react / 3b-ident). Naming
   convention established ({law-number}{letter}). Other Law
   sub-pattern slots (Law 1b, Law 6b, etc.) PRESERVED for
   future BUT NOT FORMALIZED per discipline.

2. **Section C.5 Doctrine 6 status note**: NO direct change
   to Doctrine 6 (count remains 6); but constitutional
   structure now distinguishes Law sub-patterns (Law 3b)
   from Doctrine sub-patterns (Doctrine 5c) with explicit
   methodology distinction documented.

3. **Section D Q11 5-outcome model**: Q11 adjudication space
   expanded from 4-outcome (Promote / Refuse / Retain /
   Refine) to **5-outcome model** with NEW outcome (e):
   Law sub-pattern formalization. Q11 outcome (e) gates
   law-tier branching jurisprudence.

4. **Section D Law Sub-pattern Gate Criteria (NEW audit gate
   class)**: 4 criteria (L1 100% parent-law dependence + L2
   strong cross-context breadth + L3 structural specificity +
   L4 sub-character coherence). Strictest audit gate yet
   introduced; prevents law-layer inflation.

5. **META chronology entries** (this section): Phase 8.13
   audit verdict + Phase 8.14 patch documentation + KB phase
   progression table extension (8.13 + 8.14 rows) + KB
   Constitution v2 declaration.

**Constitutional principles introduced (Phase 8.14)**:

> **Law-tier branching jurisprudence principle (Phase 8.14
> codification)**: KB-Wide LAW tier may acquire INTERNAL
> SUB-PATTERN ARCHITECTURE through audit-driven specialization
> formalization. Law sub-patterns are distinct from Doctrine
> sub-patterns by operating at LAW tier rather than DOCTRINE
> tier.

> **Hierarchy sophistication principle**: Constitutional
> evolution may proceed through HIERARCHY RESTRUCTURING
> (introducing NEW constitutional layers) rather than tier
> ascension. Hierarchy depth substitutes for tier expansion.
> This may become the defining principle of KB Constitution
> v2.

> **Law sub-pattern conservation principle (Phase 8.14
> discipline)**: Only candidates meeting ALL Law Sub-pattern
> Gate criteria (L1-L4) warrant Law sub-pattern
> formalization. Law sub-patterns are RARER than doctrine
> sub-patterns AND require STRICTER formalization criteria.
> Law-layer inflation risk requires gate discipline.

> **Hierarchy expansion vs population distinction**: Phase
> 8.14 INTRODUCES Law sub-pattern architecture (one
> instance: Law 3b) but does NOT POPULATE other laws with
> sub-patterns. Law sub-pattern architecture is an
> AVAILABLE constitutional structure for future evidence,
> not a mandatory expansion of all laws.

**Doctrine count update**: 6 → 6 (UNCHANGED).
**Law count update**: 6 → 6 (UNCHANGED — Phase 8.14 does
NOT add new laws).
**Law sub-pattern count**: 0 → 1 (Law 3b NEW).
**Constitutional hierarchy layer count**: 10 → **11** (Law
sub-pattern layer NEW).
**Audit gate class count**: 2 (Standard + Governance-intensive)
→ 3 (+ NEW Law Sub-pattern Gate).
**Q11 outcome count**: 4 → 5 (+ Law sub-pattern formalization).

### KB Constitution v2 declaration (Phase 8.14)

This patch declares **KB Constitution v2** as the current
operating constitutional version.

| Version | Meaning |
|---|---|
| v1 | Phase 7-8 foundational constitution (6 KB-Wide Laws + 5 Doctrines + flat law-tier) |
| v1.5 | Phase 8.5-8.7 governance sophistication (audit infrastructure + re-audit + synchronization) |
| v1.6 | Phase 8.10 doctrine architectural sophistication (Doctrine 6 HARD/SOFT variants formalized) |
| **v2** | **Phase 8.14 LAW-LAYER hierarchy sophistication (NEW Law sub-pattern constitutional layer; Law 3b first formalized; hierarchy restructuring without law expansion)** |

**v2 declaration rationale**:
- v2 trigger conditions (per Phase 8.8 declaration): "First
  successful KB-Wide LAW expansion OR hierarchy
  restructuring"
- KB-Wide LAW expansion: NOT met (laws remain at 6)
- Hierarchy restructuring: **MET** (NEW Law sub-pattern
  layer introduced; Law 3 acquires internal architectural
  specialization)
- v1.6 (doctrine architectural sophistication) → v2
  (LAW-LAYER architectural sophistication) is qualitatively
  different category of constitutional advance

**v2 distinguishing characteristics**:
- v1, v1.5, v1.6: operated WITHIN existing constitutional
  structure
- **v2: introduces NEW constitutional layer (Law sub-patterns)
  altering law-tier architecture itself**

> **v2 declaration principle (Phase 8.14)**: Constitutional
> version advancement to v2 requires hierarchy-altering
> change (NEW constitutional layer creation OR existing
> law-tier expansion). Phase 8.14 meets this via Law
> sub-pattern layer introduction.

**v2 conservative principle**:
- v2 = law-layer architecture INTRODUCED
- v2 ≠ law-layer architecture UNIVERSALLY POPULATED
- Only Law 3 currently has formal sub-pattern architecture
- Other laws (Law 1, Law 2, Law 4, Law 5, Law 6) RETAIN
  flat top-tier structure
- Future law sub-pattern formalizations may emerge IF
  evidence + audit support; otherwise law-tier flat structure
  remains for those laws

**Versioning progression updated**:

| Version | Trigger | Status |
|---|---|---|
| v1 | Phase 7-8 KB-Wide Laws + 5 doctrines establishment | STABLE |
| v1.5 | Phase 8.5-8.7 governance sophistication | STABLE (Phase 8.8 declaration) |
| v1.6 | Phase 8.10 doctrine architectural sophistication | STABLE (Phase 8.10 declaration) |
| **v2** | **Phase 8.14 LAW-LAYER hierarchy sophistication (NEW Law sub-pattern layer)** | **CURRENT (this patch declaration)** |
| v3 | (RESERVED) Future trigger: KB-Wide LAW expansion OR additional hierarchy restructuring | Not yet triggered |

**v2 → v3 reservation**:
- v3 trigger may eventually emerge from:
  - First successful KB-Wide LAW expansion (7th law)
  - Additional NEW constitutional layers (e.g., bounded
    context character taxonomy formalization at law-class
    layer)
  - Major hierarchy restructuring beyond Law sub-pattern
    architecture
- Currently NO v3 trigger conditions met

**Anticipated future patches:**

- **Phase 8.15 (anticipated)**: Constitutional consolidation
  update (KB Constitution v2 declaration → v2 operating
  snapshot document; analogous to Phase 8.8 v1.5 consolidation
  precedent). Phase 7-8.14 cycle snapshot.
- **Phase 8.16+ (anticipated)**: Forward chunk authoring
  resumption under v2 patched spec; chunks should reference
  Law 3b explicitly when cross-runtime PHP↔JS authority
  preservation is structural concern.
- **Phase 8.17+ (anticipated)**: Bounded context character
  bifurcation formalization (governance-architectural vs
  computational-architectural distinction; Phase 8.9 + 8.12
  surfaced) — independent from Law sub-pattern architecture.
- **Phase 8.20+ (anticipated)**: Additional Law 3 sub-pattern
  formalization candidates (HMAC-binding / stack-disciplined
  / etc.) IF evidence accumulates AND Law Sub-pattern Gate
  satisfied.

Each future patch should follow established Phase 7.5 / 7.6 /
7.7 / 8.5 / 8.7 / 8.10 / 8.14 patterns: explicit chronology
entry, trigger documentation, principle articulation,
deferred candidates list.

### Phase 8.18 Constitutional Audit — Civilization Archetypes (2026-05-10)

**Audit subject**: Civilization archetypes constitutional
formalization candidacy.

**Audit type**: Constitutional admissibility test (NOT
formalization attempt).

**Audit gate type**: 5 archetype-specific admissibility
criteria (A: Cross-context breadth / B: Internal coherence /
C: Predictive value / D: Constitutional independence /
E: Inflation resistance).

**Audit gate verification**: 1/5 criteria fully MET (B
internal coherence STRONG); 4/5 NOT MET (breadth WEAK +
predictive WEAK + independence WEAK to NONE + inflation
resistance BORDERLINE).

**Audit verdict**: **Constitutional formalization REFUSED;
Historiographic Analytical Framework formalization
CONFIRMED.**

**Refusal rationale (constitutional formalization)**:
- Constitutional independence WEAK to NONE (clustering of
  existing constitutional elements)
- Predictive value UNVALIDATED + tautological
- Cross-context breadth WEAK (4 contexts × 1:1 mapping)
- Phase 7.8 + 8.6 + 8.13 refusal precedents apply
  (independence load-bearing)

**Analytical formalization rationale**:
- Internal coherence STRONG (4 archetypes structurally
  stable)
- 4 observations + Phase 8.17 comparative scaffolding
  warrant analytical formalization
- Per Phase 8.18 framing best-case outcome

**Constitutional significance**:
- **5th constitutional formalization refusal in KB** (after
  Phase 7.8 Resolution + Phase 8.x Mediation + Phase 8.6
  Mediation re-audit + Phase 8.13 Bridge)
- **1st non-constitutional analytical formalization in KB**
  (Historiographic Analytical Framework tier introduced)
- **3 NEW constitutional precedents** (Precedent A: NEW
  non-constitutional formalization tier; Precedent B:
  constitutional independence load-bearing across all
  formalization tiers; Precedent C: predictive validation
  as future re-audit trigger)
- **V1-V4 predictive validation criteria** established for
  future constitutional layer re-audit

**Spec changes deferred to Phase 8.19 patch**.

### Phase 8.19 Constitutional Meta-Architecture Patch — Section X Analytical Tier (2026-05-10)

**Trigger**: Phase 8.18 audit verdict produced
Historiographic Analytical Framework formalization (NEW
non-constitutional formalization tier). Constitution layer
required hierarchy expansion to accommodate analytical
tier — but CRITICALLY without constitutional inflation.
Phase 8.19 architects boundary between constitutional
hierarchy and analytical infrastructure.

**Critical disclaimer**: Phase 8.19 introduces NEW
**non-constitutional** infrastructure (Section X), distinct
from Phase 8.14 NEW constitutional layer (Law sub-patterns).
Phase 8.14 was hierarchy-altering; Phase 8.19 is
infrastructure-additive at parallel (NOT vertical) tier.

**5 changes applied to structural-patterns.md**:

1. **NEW Section X — Analytical Tier**: Formalized
   non-constitutional formalization tier, structurally
   adjacent (NOT vertical) to constitutional hierarchy
   Sections A-E. Contains:
   - Section X.1: Constitutional Meta-Architecture
     (parallel infrastructure documentation)
   - Section X.2: Boundary Architecture (constitutional vs
     analytical)
   - Section X.3: Civilization Archetypes (4 archetypes
     formalized as historiographic analytical framework)
   - Section X.4: Predictive Validation Criteria (V1-V4
     for future constitutional re-audit)
   - Section X.5: Q12 (anticipated; SURFACED but NOT
     formalized)

2. **Constitutional Meta-Architecture documentation**:
   3 civilizational functions formally separated
   (Constitutional Governance / Constitutional Historiography
   / Constitutional Sociology). Each with dedicated
   infrastructure (Sections A-D / META chronology / Section X).

3. **Boundary Architecture rules**: Constitutional vs
   Analytical tier criteria documented; cross-boundary
   movement rules established (Analytical → Constitutional
   via re-audit; Constitutional → Analytical via
   independence loss).

4. **Civilization Archetypes formal documentation**: 4
   archetypes (Governance-heavy / Security-heavy /
   Semantic-heavy / Computational-heavy) documented with
   constitutional element profile signatures + observed
   contexts + predictive hypotheses (UNVALIDATED).

5. **META chronology entries** (this section): Phase 8.18
   audit verdict + Phase 8.19 patch documentation + KB
   phase progression table extension (8.15-8.19).

**Constitutional principles introduced (Phase 8.19)**:

> **Formalization ≠ Constitutionalization (Phase 8.19
> codification)**: Formal structure can exist without
> constitutional sovereignty. KB may systematically
> describe realities it explicitly refuses to govern.

> **Constitutional Meta-Architecture principle**: A mature
> constitution operates parallel infrastructures
> (constitutional hierarchy + analytical tier) with explicit
> boundary architecture. Authority architecture and
> interpretive architecture are PARALLEL, not vertical.

> **Observatory not Government principle**: Section X
> observes and classifies; Sections A-E govern. Mixing the
> two collapses the analytical/constitutional distinction.

> **Constitutional scarcity preservation principle**:
> Constitutional formalization requires constitutional
> independence. Analytical formalization is appropriate for
> patterns lacking independence but exhibiting strong
> internal coherence.

> **Civilizational restraint principle**: Mature constitution
> distinguishes "what governs" / "what evolved" / "what
> clusters" — formalizing each at appropriate tier without
> conflation.

**Constitutional layer count update**: 11 → 11 (UNCHANGED;
Section X is NOT Layer 12).
**Analytical tier introduction**: 0 → 1 (Section X
formalized).
**Historiographic analytical frameworks**: 0 → 1
(Civilization Archetypes formalized).
**Diagnostic questions**: Q1-Q11 (unchanged); Q12 SURFACED
(NOT formalized; pending second analytical framework
precedent).

**KB Constitution version impact**: v2 stability preserved
(NO new constitutional layer; analytical infrastructure
expansion is INFRASTRUCTURE-ADDITIVE not LAYER-ADDITIVE).

**Anticipated future patches:**

- **Phase 8.20 (anticipated)**: Predictive utility pilot —
  test V1-V4 criteria via untouched bounded context
  analysis. Could enable Phase 8.21+ archetype
  constitutional re-audit OR confirm analytical-only
  classification.
- **Phase 8.21+ (anticipated)**: Analytical maturity branch
  OR constitutional candidacy branch (depending on Phase
  8.20 outcome).
- **Phase 8.22+ (anticipated)**: Forward chunk authoring
  resumption under v2 + civilization-archetype-aware
  framework (analytical reference, not constitutional
  governance).

Each future patch should follow established Phase 7.5 / 7.6 /
7.7 / 8.5 / 8.7 / 8.10 / 8.14 / 8.19 patterns: explicit
chronology entry, trigger documentation, principle
articulation, deferred candidates list.

### Phase 8.20 Predictive Utility Pilot — V2 Falsifiability Test (2026-05-10)

**Pilot subject**: Section X civilization archetypes V2
predictive accuracy validation.

**Methodology**: Pre-registered hypothesis testing +
4-dimension prediction scoring + branch decision framework.

**Pilot targets**: editor-customization (HIGH-CONFIDENCE
prediction) + data-layer (LOW-CONFIDENCE prediction).

**V2 outcome**: **MODERATE** — non-tautological gain but
structural limitations.

**Key findings**:
- Archetypes predict ABSENCE more reliably than DOMINANCE
- editor-customization: HYBRID Governance + Interception
  (NOT pure Governance-heavy as predicted)
- data-layer: NEW potential archetype emergence (Entity-
  substrate-heavy / Law-5-substrate-heavy candidate;
  surfaced only)
- 4-dimension scoring: 1 full-match + 7 partial across 2
  contexts × 4 dimensions

**V1-V4 validation status update (post-pilot)**:
- V1 Cross-context generalization: PARTIAL (4/6 fit;
  2/6 hybrid or novel)
- V2 Predictive accuracy: MODERATE (structural limitations)
- V3 Constitutional independence: WEAK (perhaps weaker)
- V4 Inflation resistance: BORDERLINE-WEAKER (1 new
  archetype candidate emerged)

**0/4 STRONG validation criteria met.** Constitutional
layer re-audit pathway: NOT TRIGGERED.

**Phase 8.21 branch verdict**: REFINEMENT.

**Constitutional significance**:
- 1st explicit predictive utility pilot for analytical
  framework
- 1st pre-registered archetype hypothesis falsifiability
  test
- Pre-registration methodology established as KB
  precedent
- Section X analytical formalization JUSTIFIED via
  partial predictive validation

**Spec changes deferred to Phase 8.21 patch**.

### Phase 8.21 Section X Predictive Modality Refinement (2026-05-10)

**Trigger**: Phase 8.20 V2 pilot finding that archetypes
predict ABSENCE better than DOMINANCE warranted Section X
predictive modality refinement. Per Phase 8.21 LIGHTWEIGHT
discipline: small refinement, NOT theory-heavy expansion.

**Critical disclaimer**: Phase 8.21 is **LIGHTWEIGHT
refinement patch** — refines predictive modality scope
WITHOUT adding new archetypes WITHOUT changing constitutional
layers WITHOUT inflating analytical structure. Section X
expanded with sub-section X.6 documenting predictive
modality refinement.

**5 changes applied to structural-patterns.md (Phase 8.21
lightweight patch)**:

1. **Section X.6 NEW**: Predictive Modality Refinement
   documenting:
   - X.3a Dominant Archetype Prediction (weaker modality;
     PARTIAL reliability)
   - X.3b Constraint / Absence Prediction (stronger
     modality; STRONG reliability)
   - Hybrid archetype documentation (editor-customization
     HYBRID Governance + Interception)
   - Novel archetype surfacing discipline (1-context
     observation insufficient for promotion)
   - Predictive scope clarification (STRONG vs WEAK vs
     INAPPROPRIATE uses)

2. **Constitutional constraint science framing surfaced**:
   negative constitutional space more predictive than
   positive constitutional density (analogous to physics
   forbidden states / biology viability constraints /
   linguistics impossible forms).

3. **Archetype Expansion Gate (A1-A4) consideration**:
   surfaced as anticipated future infrastructure (NOT
   yet formalized; analytical scarcity discipline).

4. **META chronology entries** (this section): Phase 8.20
   pilot + Phase 8.21 refinement documentation.

5. **KB phase progression table extension** (8.20 + 8.21
   rows).

**Constitutional principles introduced (Phase 8.21)**:

> **Negative constitutional space is more predictive than
> positive constitutional density.** Section X's predictive
> power is ARCHETYPE-BOUNDED EXCLUSION FORECASTING, not
> DOMINANT ARCHETYPE ASSIGNMENT.

> **Constitutional constraint science framing**: KB Section
> X may approach constitutional constraint science —
> systematic prediction of WHAT WILL NOT MANIFEST in given
> bounded context character profile.

> **Analytical scarcity discipline**: Constitutional
> scarcity protected Sections A-E. Analytical scarcity must
> protect Section X. Same philosophy, different tier.

> **Lightweight refinement principle**: Mature systems
> refine analytical frameworks through SMALL targeted
> patches based on falsifiability evidence; AVOID major
> theory-heavy expansions that risk constitutional
> scholasticism.

**Section X structure update**:
- Section X.1: Constitutional Meta-Architecture (Phase 8.19)
- Section X.2: Boundary Architecture (Phase 8.19)
- Section X.3: Civilization Archetypes (Phase 8.18 + 8.19)
- Section X.4: Predictive Validation Criteria (V1-V4;
  Phase 8.18)
- Section X.5: Q12 (anticipated; Phase 8.19)
- Section X.6: **Predictive Modality Refinement (Phase 8.21
  NEW)**
- Section X — Closing principle

**Constitutional infrastructure unchanged**:
- KB-Wide Laws: 6 (unchanged)
- Doctrines: 6 (unchanged)
- Law sub-patterns: 1 (Law 3b; unchanged)
- Constitutional layers: 11 (unchanged)
- Doctrine 6 sub-elements: 9 (unchanged)
- Audit gate classes: 3 (unchanged)
- Q11 outcome model: 5-outcome (unchanged)

**Analytical infrastructure refinement**:
- Section X.3a/X.3b predictive modality split (NEW)
- Hybrid archetype documentation pattern (NEW)
- Constitutional constraint science framing (surfaced)
- Archetype Expansion Gate (anticipated; NOT formalized)

**Anticipated future patches:**

- **Phase 8.22+ (executed)**: Build-tooling boundary terrain
  predictive pilot (Phase 8.22) + Section X.6 small
  validation patch (Phase 8.23).
- **Phase 8.24+ (anticipated)**: Forward chunk authoring
  resumption with deployment breadth focus (per Phase 8.22
  user recommendation: theme-config + deeper data-layer
  preferred over additional boundary terrain).
- **Phase 8.30+ (deferred)**: Constitutional layer re-audit
  IF V1-V4 substantively improve (currently 0/4 STRONG;
  pathway preserved).

Each future patch should follow established Phase 7.5 / 7.6 /
7.7 / 8.5 / 8.7 / 8.10 / 8.14 / 8.19 / 8.21 / 8.23 patterns:
explicit chronology entry, trigger documentation, principle
articulation, deferred candidates list.

### Phase 8.22 Build-Tooling Predictive Utility Pilot — Boundary Terrain V2 Test (2026-05-10)

**Pilot subject**: Section X civilization archetypes V2
predictive accuracy validation in BOUNDARY TERRAIN.

**Pilot target**: build-tooling (untouched bounded context;
infrastructural domain).

**Methodology**: HYBRID-first pre-registration (LOW-MODERATE
confidence) + X.3a/X.3b modality split + Hybrid-before-
Proliferation discipline observance + confidence-calibrated
predictions.

**V2 outcome**: **STRONG (X.3b absence prediction) / GOOD
(X.3a HYBRID-first dominance) → BIFURCATED V2 EMPIRICALLY
VALIDATED**.

**Key findings**:
- X.3b absence prediction: 6/7 full + 1/7 partial (STRONG)
- X.3a dominance prediction: HYBRID-first hypothesis
  CONFIRMED at LOW confidence
- Manifestation prediction: 4/4 correct
- Build-tooling profile: HYBRID Computational-heavy
  adjacent + Infrastructural; LOW-FIT to existing 4
  archetypes
- 2nd potential novel archetype candidate (Infrastructure-
  heavy / Compiler-substrate-heavy) SURFACED but NOT
  promoted per Hybrid-before-Proliferation discipline

**V1-V4 validation status update (post-Phase-8.22)**:
- V1 Cross-context generalization: PARTIAL (4/7; ~57%)
- V2 Predictive accuracy: BIFURCATED (X.3b STRONG; X.3a
  WEAK-MODERATE / GOOD with HYBRID-first)
- V3 Constitutional independence: WEAK (unchanged)
- V4 Inflation resistance: BORDERLINE (with discipline
  operating)

**Constitutional significance**:
- 2nd predictive utility pilot (boundary terrain
  methodology validated)
- X.3a/X.3b refinement EMPIRICALLY EFFECTIVE
- Hybrid-before-Proliferation principle: 2 disciplined
  non-promotions documented
- "Section X is right ENOUGH to be useful while wrong
  ENOUGH to remain analytical"

**Phase 8.23 branch verdict**: CONTINUED REFINEMENT.

**Spec changes deferred to Phase 8.23 small patch**.

### Phase 8.23 Section X.6 Small Validation Patch (2026-05-10)

**Trigger**: Phase 8.22 boundary terrain pilot empirically
validated Phase 8.21 X.3a/X.3b refinement. Per Phase 8.23
LIGHTWEIGHT discipline: small validation note patch, NOT
new gate / archetype / audit class.

**Critical disclaimer**: Phase 8.23 is **MAINTENANCE-SCALE
patch** — Section X.6 documentation update only. NO
constitutional changes. NO new analytical structures. NO
formalization promotions.

**3 changes applied to structural-patterns.md (Phase 8.23
small patch)**:

1. **Section X.6 empirical validation note**: Cumulative
   Phase 8.20 + 8.22 evidence table documented; X.3a/X.3b
   modality split EMPIRICALLY VALIDATED; Operational Tier
   framework (Tier 1: Constraint forecasting STRONG;
   Tier 2: Dominance hypothesis WEAK-MODERATE; Tier 3:
   Novel archetype emergence HIGH CAUTION).

2. **Section X.6 Hybrid-before-Proliferation operational
   confirmation**: 2 disciplined non-promotions documented
   (data-layer Entity-substrate candidate + build-tooling
   Infrastructure-heavy candidate). Boundary anomaly
   replication observation SURFACED (NOT formalized as
   rule).

3. **Section X.6 boundary-terrain success note**: Phase 8.22
   boundary terrain methodology validated; future pilot
   sequence pattern SUGGESTED (boundary → mid-spectrum →
   boundary rotation; NOT formalized as protocol).

**Constitutional principles introduced (Phase 8.23)**:

> **Section X is strongest when it constrains
> interpretation without claiming governance** (user-
> sharpened Phase 8.22 thesis).

> **Boundary anomaly ≠ archetype until replicated**
> (surfaced principle; NOT formalized; "1 context = noise;
> 3 contexts = maybe signal").

> **Predictive humility itself improved predictive quality**
> (Phase 8.22 confidence calibration evidence: LOW
> confidence for high uncertainty + HIGH confidence for
> strong absences = better utility than uniform
> moderate confidence).

> **Patch lightly. Deploy broadly. Inflate slowly. Let
> repeated failure, not novelty, drive category evolution**
> (Phase 8.23 operational doctrine for analytical
> framework maintenance).

**Constitutional infrastructure UNCHANGED**:
- KB-Wide Laws: 6 (unchanged)
- Doctrines: 6 (unchanged)
- Law sub-patterns: 1 (Law 3b; unchanged)
- Constitutional layers: 11 (unchanged)
- Audit gate classes: 3 (unchanged)
- Q11 outcome model: 5-outcome (unchanged)
- Section X archetypes: 4 (unchanged; 2 disciplined non-
  promotions)

**Analytical infrastructure refinement** (Phase 8.23 only):
- Section X.6 empirical validation note (NEW)
- Section X.6 Hybrid-before-Proliferation operational
  confirmation (NEW)
- Section X.6 boundary-terrain success note (NEW)
- Boundary anomaly replication observation (surfaced; NOT
  formalized)
- Operational Tier framework documentation (3-tier within
  X.6)

**Anticipated future patches:**

- **Phase 8.24+ (anticipated; PRIMARY recommendation)**:
  Forward chunk authoring resumption with **deployment
  breadth focus**. Per Phase 8.22 user recommendation:
  prioritize theme-config + deeper data-layer (mid-
  spectrum terrain) over additional boundary terrain.
  Section X archetype-aware framework natural deployment
  during forward authoring.

- **Phase 8.30+ (deferred)**: Constitutional layer re-audit
  IF V1-V4 substantively improve (currently 0/4 STRONG;
  V3 remains constitutional independence blocker; pathway
  preserved).

- **Phase 8.40+ (deferred)**: Boundary Anomaly Replication
  Threshold rule formalization IF 3+ boundary terrain
  pilots produce repeated low-fit + similar absence
  profiles (currently 1 boundary terrain instance: build-
  tooling).

Each future patch should follow established Phase 7.5 / 7.6 /
7.7 / 8.5 / 8.7 / 8.10 / 8.14 / 8.19 / 8.21 / 8.23 patterns:
explicit chronology entry, trigger documentation, principle
articulation, deferred candidates list.

### Use by future bounded context entries

When a new bounded context (editor-customization, site-building,
i18n, admin-ui, build-tooling) is entered:

1. **Pre-entry**: review predictive frontier section for
   anticipated law manifestations.
2. **First chunk**: structure WHEN/SHAPE/INVARIANTS through
   the constitutional diagnostic checklist.
3. **Per-chunk**: cite specific laws when they apply
   (e.g., "applies Law 1 declaration ≠ exposure at the X
   surface").
4. **Per-context closure**: verify which laws manifested,
   document any unexpected presences/absences.
5. **Post-entry**: update predictive frontier section based
   on findings.

This protocol turns each new bounded context into BOTH a
documentation effort AND a constitutional law verification
opportunity.

### One-line backbone

> The KB is no longer just documenting WordPress architecture —
> it is now formalizing the constitutional laws by which that
> architecture can be interpreted, extended, and predicted.

### Caution — what this document is NOT

- NOT an encyclopedia of all WordPress patterns
- NOT a glossary of terminology (see DSL spec glossary for
  that)
- NOT a developer's how-to guide
- NOT a complete theory of WordPress architecture
- NOT immutable scripture (revisions are appropriate when
  audit-grounded)

The role is **universal law formalization**: 6 verified
invariants documented with predictive force, plus the
discipline for using them.
