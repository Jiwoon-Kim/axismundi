---
rule_id: block.json-hierarchy-constraints
domain: block-authoring
topic: block-json
field_cluster: composition
wp_min: "verification-needed"
wp_recommended: "6.5"
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#parent
    section: "Metadata — Parent"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#ancestor
    section: "Metadata — Ancestor (Since WP 6.0)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#allowed-blocks
    section: "Metadata — Allowed Blocks (Since WP 6.5)"
    captured: 2026-05-09
related:
  - block.json-context              # context propagates along the tree topology this chunk defines
  - block.inner-blocks              # runtime composition mechanism; allowedBlocks prop may complement
  - block.json-attributes-core      # different concern: data schema vs structural constraints
  - block.deprecation               # constraint changes may affect existing blocks
  - editor.block-locking            # different mechanism (UI lock) for restricting editing
---

# RULE — Insertion constraints — `parent` / `ancestor` / `allowedBlocks`

## WHEN

You need to control the **structural composition** of where a block
type may appear in the block tree, OR which block types may appear
inside it. Three fields cover three distinct cases:

- **`parent`** — *this block can be inserted only as a direct child
  of one of the named blocks.* Used for tightly-coupled child blocks
  (e.g., "Add to Cart" inside "Product").
- **`ancestor`** — *this block can be inserted anywhere within the
  subtree of one of the named blocks.* Used for descendants that may
  be nested arbitrarily deep (e.g., "Comment Content" inside any
  position under "Comment Template").
- **`allowedBlocks`** — *only these block types may be inserted as
  direct children of this block.* The inverse direction: this block
  restricts what can go inside it.

All three are **block-type-name** constraints declared at registration
time. They affect what the editor's inserter offers and where blocks
may be moved.

## SHAPE

```json
{
  "parent":        [ "my-plugin/product" ],
  "ancestor":      [ "core/comment-template" ],
  "allowedBlocks": [ "my-plugin/list-item" ]
}
```

### Field map

| Field | Type | Direction | Strictness | Since |
|---|---|---|---|---|
| `parent` | `string[]` | constrains where THIS block may be inserted | **strict**: must be DIRECT child of a named block | original |
| `ancestor` | `string[]` | constrains where THIS block may be inserted | **loose**: must be SOMEWHERE in the subtree of a named block | WP 6.0 |
| `allowedBlocks` | `string[]` | constrains what may be inserted INSIDE this block | direct children only | WP 6.5 |

### Concrete examples

```json
// Add to Cart can ONLY appear directly inside Product:
{ "name": "my-plugin/add-to-cart", "parent": [ "my-plugin/product" ] }
```

```json
// Comment Content can appear anywhere inside the Comment Template subtree
// (nested in Columns, Groups, etc.):
{ "name": "core/comment-content", "ancestor": [ "core/comment-template" ] }
```

```json
// List block accepts ONLY List Item children:
{ "name": "core/list", "allowedBlocks": [ "core/list-item" ] }
```

## REQUIRES

- Each entry in `parent` / `ancestor` / `allowedBlocks` MUST be a
  fully-qualified block name (`vendor/slug`) — same format as
  `block.json` `name`.
- For `parent` / `ancestor`, the named block types should exist on
  the site (they may be in plugins/core that aren't yet registered;
  in that case the constraint silently does nothing meaningful until
  the named block is registered).
- These constraints are **schema-level** declarations. They are
  evaluated by the editor's inserter logic at insert time, not at
  block registration time. Block registration succeeds regardless of
  whether the constraint is satisfiable.
- For `allowedBlocks`, the block typically also implements
  `<InnerBlocks />` (or equivalent) to actually contain children.
  Declaring `allowedBlocks` without an InnerBlocks slot has no
  visible effect.

## INVARIANTS

- **`parent` is stricter than `ancestor`.** `parent: ["X"]` means
  "must be a DIRECT child of X". `ancestor: ["X"]` means "must be
  somewhere within the subtree of X (any depth)". A block declaring
  `parent` is automatically also constrained by ancestor (the parent
  IS an ancestor); declaring both with the same name is redundant.
- A block declaring `parent` is **only available in the inserter when
  the cursor is positioned inside one of the named blocks**.
  Otherwise the block is hidden from inserter results.
- A block declaring `ancestor` is **only available in the inserter
  when the cursor is positioned anywhere within the subtree of one of
  the named blocks**.
- `allowedBlocks` operates inversely: it filters the inserter when
  the cursor is INSIDE the block declaring it, restricting which
  block types appear in the inserter.
- All three constraints use **OR semantics across the array**:
  multiple entries widen, not narrow. `parent: ["A", "B"]` means "may
  be child of A OR B".
- These are **insertion-time** constraints on the editor UI, not
  runtime validators. Programmatic insertion (e.g., via patterns,
  templates, or `wp:insertBlock` action dispatch) MAY bypass these
  checks — the constraint primarily filters what the user can pick
  from the inserter, not what can technically exist in saved content.
- ⚠ **Interaction with InnerBlocks `allowedBlocks` prop is not
  documented in the metadata reference.** The runtime `<InnerBlocks
  allowedBlocks={[...]} />` prop may override or complement the
  schema-level `block.json` `allowedBlocks`. Verify behavior before
  relying on schema-level declaration alone for runtime restriction.
- ⚠ **Interaction with template-locking and patterns is unspecified
  here.** Templates / template-locks / patterns have their own
  composition rules that may interact with these constraints.
- ⚠ **Minimum WP versions:** `parent` is original; `ancestor` is
  WP 6.0; `allowedBlocks` is WP 6.5. A block using all three
  effectively requires `wp_min: 6.5`. Frontmatter `wp_min` is
  `"verification-needed"` because the parent field is original and
  unspecified.

## ANTIPATTERNS

- ❌ Declaring both `parent: ["X"]` and `ancestor: ["X"]` for the
  same name. `parent` already implies `ancestor`. Pick the strictness
  level you actually need.
- ❌ Using `parent` when the block could legitimately appear nested
  (e.g., wrapped in a Group). `parent` is too strict; use `ancestor`
  for "must be in the subtree of X" semantics.
- ❌ Relying on `allowedBlocks` to enforce content rules for
  end-user safety. It's an **inserter-UX filter**, not a security
  boundary. A user with raw HTML access can place arbitrary blocks
  via direct content editing or `wp:` delimiter manipulation.
- ❌ Using bare block slugs without the namespace
  (e.g., `parent: [ "product" ]` instead of `parent: [ "my-plugin/product" ]`).
  Block name format requires `vendor/slug`.
- ❌ Declaring `allowedBlocks` without implementing `<InnerBlocks />`
  (or equivalent). The block has nothing to contain children — the
  declaration is a no-op.
- ❌ Mixing `allowedBlocks` declaration in both `block.json` AND the
  `<InnerBlocks allowedBlocks={...} />` prop without understanding
  precedence. Pick one source of truth or verify the resolution
  rules empirically.
- ❌ Using these fields to model **editor UI restrictions** (locking
  block movement / removal). Use the block locking API for that —
  these fields gate insertion, not editing of placed blocks.

## RELATED

- `block.json-context` — context propagates along the same tree
  topology these constraints define. `parent` / `ancestor` declare
  the topology; `providesContext` / `usesContext` declare the
  propagation across it.
- `block.inner-blocks` — runtime composition mechanism via
  `<InnerBlocks />`. The runtime `allowedBlocks` prop on this
  component may overlap with the schema-level `block.json`
  `allowedBlocks` field; relationship between the two is
  verification-needed.
- `block.json-attributes-core` — different ontological concern:
  attributes define DATA schema; these fields define COMPOSITION
  schema. Both are part of `block.json` but address different layers.
- `block.deprecation` — changing `parent` / `ancestor` /
  `allowedBlocks` may invalidate existing saved trees that no longer
  satisfy the new constraint. Consider deprecation if updates affect
  consumer blocks.
- `editor.block-locking` (planned, different bounded context) —
  Block Locking API restricts editing/movement of already-placed
  blocks; complementary to insertion-time constraints here.

---

## Q9 RETROACTIVE PATCH — Phase 8.5+ Schema Authority Doctrine 6 Verification (2026-05-10)

> **Retroactive verification triggered by**:
> User-directed Q9 retro on `block-authoring/registration`
> family for Schema authority category Doctrine 6 manifestation
> test (post-Phase-8.5; post-`plugin-dev/register-rest-route`
> retro). This chunk (`hierarchy-constraints`) is the
> registration family member with strongest structural-
> participation gating evidence; sibling registration chunks
> (`register-via-block-json` etc.) focus on registration
> mechanics rather than gating.
>
> **Strategic role**: Mediation KB-Wide LAW re-audit
> architectural ubiquity prerequisite test. Pre-this-retro:
> Doctrine 6 manifested in 3/5 character categories
> (Governance modulation, Semantic substrate, Authority
> federation). This retro tests **Schema authority**
> category — a critical falsification test for Doctrine 6's
> claim to architectural-general status (vs governance-
> domain concentration).
>
> **Q9 retro discipline**: Confirm / Distributed / Divergent /
> Additive verdict per Phase 7.6+ retroactive verification
> protocol.
>
> **Methodological framing**: This retro is
> **category-boundary falsification test**, not promotion
> advocacy. Negative evidence (Divergent verdict) is equally
> useful — it would establish Doctrine 6 as governance-
> domain-concentrated rather than architecture-general.

### Retro context

This chunk was authored 2026-05-09 (Phase 7-native), pre-
Doctrine-6-formalization. Original analysis described
`parent` / `ancestor` / `allowedBlocks` as **structural
composition constraints** + **insertion-time editor UI
filters**. No mediation vocabulary used.

The retro question:
> Does block hierarchy constraint declaration constitute
> Doctrine 6 manifestation in Schema authority bounded
> context character category, or is it pure schema declaration
> divergent from Doctrine 6 character?

### Latent Doctrine 6 evidence in original chunk

Re-reading the original chunk through Doctrine 6 lens reveals
**direct structural-participation gating mechanisms**:

| original chunk element | Doctrine 6 retroactive reading |
|---|---|
| **`parent`**: "constrains where THIS block may be inserted" | Direct gating mechanism: structural-participation gate (strict, parent-direct) |
| **`ancestor`**: "must be SOMEWHERE in the subtree" | Direct gating mechanism: structural-participation gate (loose, subtree) |
| **`allowedBlocks`**: "constrains what may be inserted INSIDE" | Direct gating mechanism: inverse structural-participation gate (children) |
| **"OR semantics across array"** | Multi-candidate gating arbitration |
| **"only available in inserter when cursor positioned inside named blocks"** | Per-context gating evaluation (UI-layer per-access) |
| **"All three are block-type-name constraints declared at registration time"** | Declarative registration gating character |
| **"evaluated by the editor's inserter logic at insert time, not at block registration time"** | Runtime enforcement of declarative gates |

> **Latent evidence**: Doctrine 6 character IS present — gating
> mechanisms governing structural participation are explicit.
> Mediation language was absent (Phase 7-native vocabulary)
> but mediation structure was substantive.

### Critical caveats — divergent character observations

The original chunk explicitly identifies caveats that make
this Doctrine 6 manifestation **structurally distinct from
prior 6a-6g sub-elements**:

| caveat (original chunk) | Doctrine 6 character implication |
|---|---|
| "These are **insertion-time constraints on the editor UI**, not runtime validators" | Soft gating (UI-layer) vs hard gating (runtime enforcement). 6a-6g operate at runtime per-access; 6h operates at UI insertion-time |
| "Programmatic insertion (e.g., via patterns, templates, or `wp:insertBlock` action dispatch) MAY bypass these checks" | Boundary porousness: gates are bypassable via non-UI paths. 6a-6g typically enforce universally |
| "It's an **inserter-UX filter**, not a security boundary" | Operational character: UX guidance vs access control. 6a-6g are access control; 6h is UX guidance |
| "the constraint primarily filters what the user can pick from the inserter, not what can technically exist in saved content" | Content-layer porousness: saved content can violate constraints. 6a-6g constrain action-execution; 6h constrains action-discovery |

> **CRITICAL DIVERGENCE OBSERVATION**: Schema authority
> Doctrine 6 manifestation is structurally **SOFT GATING**
> (UX-guidance, declarative, UI-layer enforcement, content-
> bypassable) vs prior 6a-6g **HARD GATING** (access-control,
> imperative, runtime enforcement, non-bypassable).

This is the audit's central honest finding: Doctrine 6
manifests in Schema authority category, BUT character is
structurally distinct (soft vs hard).

### Q9 retro verdict — HYBRID/ADDITIVE (as predicted)

Per Phase 7.6+ retroactive verification protocol (Confirm /
Distributed / Divergent / Additive):

| verdict | applicability |
|---|---|
| Confirm | Doctrine 6 manifestation matches existing 6a-6g sub-elements exactly | NO — character is structurally distinct (soft vs hard gating) |
| Distributed | Single Doctrine 6 manifestation distributed across multiple mechanisms | PARTIAL — parent/ancestor/allowedBlocks form composite gating |
| Divergent | Structurally different from Doctrine 6 character | PARTIAL — soft-gating character is divergent from prior 6a-6g hard-gating |
| **Additive** | **Adds NEW variant character to Doctrine 6** | **YES — soft gating + declarative registration is new variant** |

> **Q9 retro verdict: HYBRID/ADDITIVE with critical caveat.**
>
> Schema authority category DOES exhibit Doctrine 6 character
> (gating mechanisms govern structural participation), BUT
> with structurally distinct **soft-gating, declarative-
> registration, UI-layer-enforced** character that diverges
> from prior 6a-6g **hard-gating, runtime, access-control**
> character.

This honest dual finding (additive + divergent caveats) is
**epistemically richer than pure confirm or refuse** — it
identifies Doctrine 6 as broader than prior manifestations
WHILE marking the broadening as character-distinct.

### NEW sub-element: 6h Structural-participation-gated mediation (with HARD/SOFT distinction)

> **Phase 8.5+ Doctrine 6 sub-element addition (this retro)**:

| sub-element | bounded context | gating mechanism | character |
|---|---|---|---|
| 6a Capability-gated | admin-ui (settings-api) | user capability check | HARD (runtime, access-control) |
| 6b Routing-gated | admin-ui (admin-menus) | navigation topology | HARD (runtime, access-control) |
| 6c Cognitive-surface-gated | admin-ui (notices) | multi-axis attention | HARD (runtime, access-control) |
| 6d Subscription-gated | editor-customization (editor-hooks) | subscribe/dispatch | HARD (runtime, access-control) |
| 6e Context-reassignment-gated | i18n (locale-switching) | runtime context mutation | HARD (runtime, access-control) |
| 6f Origin-authenticity-gated | plugin-dev (nonces) | request-origin HMAC | HARD (runtime, access-control) |
| 6g Endpoint-permission-gated | plugin-dev (REST) | per-request permission_callback | HARD (runtime, access-control) |
| **6h Structural-participation-gated (NEW)** | **block-authoring (hierarchy-constraints)** | **parent/ancestor/allowedBlocks declaration enforced at editor inserter** | **SOFT (declarative, UI-layer, UX-guidance)** |

**Doctrine 6 sub-element count**: 7 → 8.

**6h distinguishing character**:
- Declared at **registration time** (vs runtime evaluation
  for 6a-6g)
- Enforced at **editor inserter UI layer** (vs request/access
  evaluation for 6a-6g)
- **Bypassable** via programmatic insertion (vs typically
  non-bypassable for 6a-6g)
- **UX-guidance** function (vs access-control function for
  6a-6g)
- Composite manifestation: parent + ancestor + allowedBlocks
  collectively form structural-participation gating
  triangulation

### NEW Doctrine 6 architectural distinction — HARD GATING vs SOFT GATING

> **MAJOR Phase 8.5+ Doctrine 6 architectural finding (NEW)**:

This retro surfaces an **internal Doctrine 6 architectural
distinction** that did not exist pre-this-retro:

| Doctrine 6 architectural mode | character | sub-elements |
|---|---|---|
| **HARD GATING (access-control mode)** | Runtime per-access evaluation; non-bypassable; enforces access boundaries | 6a, 6b, 6c, 6d, 6e, 6f, 6g (7 sub-elements) |
| **SOFT GATING (UX-guidance mode)** | Registration-time declaration; UI-layer enforcement; bypassable via non-UI paths; guides usage | 6h (1 sub-element) |

> **NEW architectural observation**: Doctrine 6 may have TWO
> distinct architectural modes (hard / soft), not a single
> uniform character. This is structurally significant for
> KB-Wide LAW candidacy evaluation.

Status: **Surfaced only.** Single soft-gating instance is
insufficient to establish two-mode architecture; cross-
context verification needed (other soft-gating mechanisms?
candidates: theme-config patterns visibility / blockHooks
suggestion / supports.inserter / supports.multiple).

### Schema authority character category — Doctrine 6 manifestation CONFIRMED

> **MAJOR finding for Mediation KB-Wide LAW re-audit**:

Pre-this-retro Doctrine 6 character category coverage:
- ✅ Governance modulation (admin-ui, editor-customization)
- ✅ Semantic substrate (i18n)
- ✅ Authority federation (plugin-dev)
- ❌ Schema authority (UNTESTED)
- ❌ Compiler/runtime (UNTESTED)

Post-this-retro Doctrine 6 character category coverage:
- ✅ Governance modulation (3-form intra-context density)
- ✅ Semantic substrate (1 form)
- ✅ Authority federation (2 forms intra-context density)
- ✅ **Schema authority (1 form, soft-gating mode) — NEW**
- ❌ Compiler/runtime (still UNTESTED)

> **Architectural ubiquity advances from 3/5 → 4/5 character
> categories.** This was the primary unresolved question for
> Mediation KB-Wide LAW re-audit viability.

### Implications for Mediation KB-Wide LAW re-audit

Phase 8.6 Mediation re-audit prerequisites (post-this-retro):

| prerequisite | status |
|---|---|
| Cross-context expansion ≥5 contexts | ✅ 5 contexts (unchanged) |
| Mediation manifestation in Schema authority category | ✅ **NEW (this retro)** |
| Retroactive Q9 verification (≥1 retro chunk) | ✅ 2 retros (REST + this) |
| Demonstration of independence from Law 1 | ⚠ partial (soft-gating mode increases independence — not Law 1 gap mechanism in same way) |

> **Phase 8.6 Mediation re-audit viability ADVANCED to 3/4
> prerequisites fully met** (was 2/4). KB-Wide LAW promotion
> verdict probability substantially increased.

But honest caveat: HARD/SOFT distinction (NEW finding) may
COMPLICATE rather than simplify KB-Wide LAW promotion.
Re-audit must address whether Doctrine 6 should be:
- Single KB-Wide LAW with hard/soft architectural modes
  (analogous to Doctrine 5 Integrated/Distributed/Hybridized
  variants)
- Two distinct doctrines (hard mediation vs soft mediation)
- Single doctrine with sub-element-level character distinction

### Constitutional Field Test additions (post-retro)

#### Table A — Universal Law Manifestation (retro additions)

| Law / Doctrine | Pre-retro reading | Post-retro reading | Status change |
|---|---|---|---|
| **Doctrine 6** | (didn't exist at chunk authoring time) | NEW 6h sub-element + HARD/SOFT architectural distinction | **Newly identified manifestation + architectural finding** |
| **Law 1 — Declaration ≠ Exposure** | implicit (registration ≠ inserter availability) | confirmed (declared constraint ≠ inserter visibility ≠ enforced content boundary) | (retroactively confirmed; 3-form gap) |
| **Law 4 — Arbitration Compiler** | implicit (OR semantics across array) | confirmed (multi-candidate constraint arbitration; OR semantics is arbitration) | (retroactively confirmed) |
| **Law 5 — Entity → Relationship Pivot** | implicit (constraints govern parent-child relationships) | confirmed (parent/ancestor/allowedBlocks define block tree relationship topology) | (retroactively confirmed) |

#### Table B — Pattern Recurrence (retro additions)

| Candidate | Pre-retro status | Post-retro outcome | Effect on candidate |
|---|---|---|---|
| **Authority Mediation Surface (Doctrine 6)** | Doctrine-tier; 5-context cross-context PRESENCE; 7 sub-elements; 3/5 character categories | NEW 6h sub-element; **4/5 character categories**; HARD/SOFT architectural distinction observation | **MAJOR ADVANCE (Schema authority category breakthrough; KB-Wide LAW re-audit substantively viable)** |
| **HARD/SOFT gating architectural distinction (NEW observation)** | did not exist | Soft gating (declarative, UI-layer, bypassable) vs Hard gating (runtime, access-control, non-bypassable) | **Surfaced only (single soft-gating instance; cross-context verification needed)** |
| **Block tree topology gating (potential)** | did not exist | Composite parent/ancestor/allowedBlocks form topology gating | **Surfaced only (sub-pattern of 6h?)** |
| **Composite Doctrine 6 manifestation** | Surfaced (REST retro) | Reinforced — hierarchy-constraints exhibits parent+ancestor+allowedBlocks composite | **STRENGTHENED (2nd composite instance)** |

### Q10 sub-pattern emergence (retro)

> **Q10 RETRO ANSWER: NO new stable sub-pattern observed**
> **WITHIN Doctrine 6 sub-element layer; HOWEVER, observation-
> level finding at Doctrine 6 architectural layer (HARD/SOFT
> distinction).**

Initial hypothesis: HARD/SOFT distinction might be sub-pattern
of Doctrine 6.

Honest evaluation per Phase 7.5 Doctrine 3 Epistemic Integrity:
- HARD/SOFT distinction operates at **architectural mode
  layer** (across sub-elements), NOT within sub-elements
- Sub-patterns operate WITHIN single variants/sub-elements
- HARD/SOFT is doctrine-level architectural observation, not
  sub-pattern

> **Refusing premature classification.** HARD/SOFT distinction
> remains observation only. Cross-context verification required
> for formalization as Doctrine 6 architectural variant.

This is consistent with prior Phase 8.5+ Q10 discipline:
NEW observations classified at appropriate constitutional
layer, NOT inflated to sub-pattern unless evidence warrants.

### Cross-chunk evidence — registration family Doctrine 6 manifestations

This retro focuses on `hierarchy-constraints` because that's
where Doctrine 6 evidence is structurally richest. But sibling
registration-family chunks also contain latent Doctrine 6
evidence (potential 6h sub-element extensions):

| chunk | latent Doctrine 6 evidence | character |
|---|---|---|
| `block-json/supports-field` | `supports.inserter` (visibility gating) | SOFT |
| `block-json/supports-field` | `supports.multiple` (instantiation gating) | SOFT |
| `block-json/supports-field` | `supports.lock` (locking-permission gating) | SOFT-adjacent |
| `block-json/basic-metadata` | `category` (categorization, mild gating) | SOFT (very weak) |
| `block-authoring/inner-blocks` | runtime `allowedBlocks` prop (vs declarative) | SOFT-runtime hybrid |

Sibling-chunk SOFT-gating manifestations strengthen 6h sub-
element evidence base. Future Q9 retros on these chunks may
formalize SOFT-gating mode + identify additional Schema
authority category Doctrine 6 manifestations.

### Mediation audit criterion impact (full re-assessment, post-this-retro)

Post-this-retro Mediation criteria status:

| criterion | pre-this-retro | post-this-retro | improved? |
|---|---|---|---|
| 1 — Context PRESENCE ≥ 4 | 5 contexts | **6 contexts** (block-authoring NEW) | ✅ |
| 2 — Architectural variants ≥ 2 | 7 mechanisms | **8 mechanisms** (NEW 6h) + HARD/SOFT distinction | ✅ |
| 3 — Intra-context density ≥ 1 | 2 contexts (admin-ui + plugin-dev) | 2 contexts (unchanged) | (no) |
| 4 — Q10 sub-pattern check | composite noted | + HARD/SOFT architectural observation | (refined) |
| 5 — Forward + retro both | 6F + 1R | **6F + 2R** | ✅ |
| 6 — Gating abstraction independence | 7 mechanisms (composite) | **8 mechanisms (composite + HARD/SOFT)** | ✅ |
| 7 — Structural consequence | predicts debt + composite | + SOFT gating consequence (UX guidance vs access control) | ✅ |

> **Mediation evidence post-this-retro**: 6 contexts × 8 gating
> mechanisms × 4/5 character categories × 2-context intra-
> context density × 6 forward + 2 retro × HARD/SOFT
> architectural observation.

This is **substantively stronger evidence base than Phase 8.x
Mediation audit** when KB-Wide LAW promotion was REFUSED.
Phase 8.6 re-audit should produce different verdict on
multiple criteria.

### Phase 8.6 Mediation re-audit prerequisite reassessment

Phase 8.6 Mediation re-audit prerequisites (post-this-retro):

| prerequisite | pre-this-retro | post-this-retro |
|---|---|---|
| Cross-context expansion ≥5 contexts | ✅ 5/5 | ✅ **6/5 (exceeds threshold)** |
| Mediation manifestation in Schema authority OR Compiler/runtime OR Composition runtime category | ⚠ pending | ✅ **Schema authority CONFIRMED (this retro)** |
| Retroactive Q9 verification (≥1 retro chunk) | ✅ 1/1 | ✅ **2 retros (REST + this)** |
| Demonstration of independence from Law 1 | ⚠ partial | ⚠ partial → improving (SOFT gating less Law 1-coupled) |

> **3/4 prerequisites fully met; 1/4 partial improving.**
> Phase 8.6 Mediation re-audit substantively viable.

But the HARD/SOFT distinction (NEW finding) introduces an
additional re-audit consideration: should Doctrine 6 be
promoted as single law with two architectural modes, or as
two distinct mediation patterns? This question may push
toward Doctrine-tier preservation OR may justify KB-Wide
elevation with explicit two-mode architecture.

### KB-wide pattern recurrence updates (retro additions)

**Doctrine 6 sub-element count**: 7 → 8 (this retro adds 6h).

**Doctrine 6 cross-context PRESENCE**: 5 → **6 contexts**
(block-authoring NEW).

**Doctrine 6 character category coverage**: 3/5 → **4/5**
(Schema authority NEW).

**HARD/SOFT architectural distinction observation**: NEW
(Surfaced only; cross-context verification needed).

**Block-authoring intra-context Doctrine 6 density**: 1 chunk
documented (this chunk via retro). Sibling chunks (supports-field,
basic-metadata, inner-blocks) may strengthen via future retros.

### Constitutional principle (retro-derived)

> **Architectural ubiquity does not require character
> uniformity.** Doctrine 6 manifests in Schema authority
> category with structurally distinct character (soft-gating)
> from prior categories (hard-gating). Constitutional invariants
> may have multiple architectural modes; KB-Wide LAW
> classification should accommodate mode diversity, not
> require character uniformity.

This refines Phase 7.8 Resolution refusal precedent:
- Resolution refused because structurally INHERENT to
  Arbitration pairing (Doctrine 5 already governs)
- Doctrine 6 may merit KB-Wide LAW promotion despite mode
  diversity if architectural ubiquity is genuine and
  characterization is precise

### Anticipated next chunks (post-this-retro)

1. **Phase 8.6 Mediation Re-audit** — sufficient evidence
   accumulated; re-audit should produce verdict on KB-Wide
   LAW promotion vs Doctrine-tier preservation vs additional
   evidence required.

2. **`style-engine.preset-materialization` Q9 retro** —
   Compiler/runtime category test (5/5 character category
   completeness if confirmed).

3. **`block-authoring.supports-field` Q9 retro** — sibling
   chunk; potential additional 6h SOFT-gating sub-element
   manifestations (supports.inserter, supports.multiple,
   supports.lock).

4. **`block-authoring.inner-blocks` Q9 retro** — runtime
   `allowedBlocks` prop vs declarative `block.json`
   `allowedBlocks` field; HARD/SOFT hybrid potential.

5. **`interactivity.directive-protocol` Q9 retro** — Bridge
   Pattern Recurring (cross-context) verification.

Recommended: **Phase 8.6 Mediation Re-audit** OR **`style-
engine.preset-materialization` retro first**. Strategic
choice depends on whether (a) immediate re-audit with 4/5
category coverage maximizes KB-Wide promotion probability,
or (b) achieving 5/5 category coverage first is more
disciplined. Per Phase 7.8 conservative discipline (additional
evidence before audit), (b) may be preferred — but the HARD/
SOFT distinction means re-audit also has new analytical
dimension to evaluate, so (a) may also be productive.

### Status updates

- This file's overall `status` remains `stable` (original
  evaluation preserved).
- Retro patch adds Q9/Q10 retro verdicts + Doctrine 6 6h
  sub-element identification + HARD/SOFT architectural
  distinction observation + Mediation criterion impact
  assessment.
- Original chunk content (lines 1-189) UNCHANGED; this retro
  is purely additive at end of file.
