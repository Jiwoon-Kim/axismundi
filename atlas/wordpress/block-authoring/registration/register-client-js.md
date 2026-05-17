---
rule_id: block.register-client-js
domain: block-authoring
topic: registration
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/
    section: "Registering a block with JavaScript (client-side)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-blocks/#registerblocktype
    section: "@wordpress/blocks registerBlockType"
related:
  - block.register-via-block-json          # PHP server-side counterpart
  - block.register-collection-php          # WP 6.8+ multi-block PHP
  - block.register-auto-php                # PHP-only alternative (no JS needed)
  - block.json-schema
  - block.edit-save-components
---

# RULE — Register a block on the client with `registerBlockType()`

## WHEN

- Block already registered server-side via PHP (typical case), and you
  need to declare the editor-side `edit` (and optionally `save`)
  components.
- OR you intentionally want a client-only block (rare; loses Dynamic
  Rendering, Block Supports, Block Hooks, Style Variations, theme.json
  per-block styling).
- Build pipeline can import `block.json` into JS (e.g., `wp-scripts`).

## SHAPE

Function signature:

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `blockNameOrMetadata` | `string \| Object` | yes | Block name (`vendor/slug`) OR an object containing block metadata (typically the imported `block.json`). |
| `settings` | `Object` | yes | Client-side settings: `edit`, `save`, plus other optional client properties. |

**Returns:** registered `WPBlock` on success, `undefined` on failure.

Minimal form (block already registered on server, just declaring `edit`):

```js
import { registerBlockType } from '@wordpress/blocks';

registerBlockType( 'my-plugin/notice', {
    edit: Edit,
    // ...other client-side settings
} );
```

Idiomatic form with `block.json` import (build process required):

```js
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';

const Edit = () => <p { ...useBlockProps() }>Hello World - Block Editor</p>;
const save = () => <p { ...useBlockProps.save() }>Hello World - Frontend</p>;

registerBlockType( metadata.name, {
    edit: Edit,
    save,
} );
```

## REQUIRES

- `@wordpress/blocks` package available in the bundle (provided by
  WordPress when running inside the editor).
- Block name passed to `registerBlockType()` MUST match the `name` in
  `block.json` (when paired with server-side registration).
- A build process that can import `.json` files into JS (any modern
  bundler — `wp-scripts` provides this out of the box).
- The two most important `settings` keys:
  - `edit` — the React component rendered in the editor.
  - `save` — the function returning static HTML serialized to the
    database. Omit / set to `null` for dynamic blocks (skips block
    markup validation on parse).

## INVARIANTS

- The `name` MUST match between PHP `register_block_type()` and JS
  `registerBlockType()` for the dual registration to bind to one block.
- `save` returning different markup across builds without using
  `deprecated` causes "block validation" errors when the editor loads
  posts saved with the old shape. For dynamic blocks, set `save: () => null`.
- `edit` MUST be a React component (function or class). It receives
  `attributes`, `setAttributes`, `isSelected`, etc. via props.
- `metadata.name` from a `block.json` import equals the string form
  exactly — prefer the import to avoid string drift.
- ⚠ **Minimum WP version unknown.** Source docs describe the API but
  do not state when `registerBlockType` was introduced. Frontmatter
  `wp_min` is `"verification-needed"`. (Block editor was introduced in
  WP 5.0 but specific API stability for this signature is unverified.)

## ANTIPATTERNS

- ❌ Registering client-only without server-side counterpart unless the
  block truly needs no server features. Loses Dynamic Rendering, Block
  Supports, Block Hooks, Style Variations, and `theme.json` per-block
  styling. Source: *"Without server-side registration, these
  functionalities will not operate correctly."*
- ❌ Hardcoding the block name as a string in both PHP and JS. Drift
  risk. Import from `block.json` on both sides where possible.
- ❌ Returning markup from `save` for a block whose content is
  generated server-side. The `save` output gets serialized to the
  database and overrides the dynamic `render_callback` output for
  cached / pre-rendered cases. Use `save: () => null` for dynamic blocks.
- ❌ Calling `registerBlockType()` outside of a properly enqueued
  editor script. The script must be loaded via `enqueue_block_editor_assets`
  or block.json's `editorScript` field.

## RELATED

- `block.register-via-block-json` — PHP server-side counterpart;
  pair with this rule for full dual registration.
- `block.register-collection-php` — WP 6.8+ multi-block PHP batch
  registration; this JS rule still applies per-block on the client side.
- `block.register-auto-php` — PHP-only alternative when no custom JS
  `edit` is needed (avoids writing this JS at all).
- `block.json-schema` — `block.json` field reference (fields like
  `editorScript` that load this JS).
- `block.edit-save-components` — `edit` and `save` component contract,
  props, and lifecycle.

---

## Q9 RETROACTIVE PATCH — Phase 8.12.5 Dual-Lens Bridge Pattern Frontier Test (2026-05-10)

> **Retroactive verification triggered by**:
> Phase 8.12 directive-protocol Q9 retro PROMOTED Bridge
> Pattern Local → Recurring (cross-context); Phase 8.13
> Bridge Pattern audit consideration became viable but
> requires 4th bounded context Bridge instance to fully
> meet Standard audit gate Criterion 1 (Context PRESENCE
> ≥ 4). User strategic guidance: target strongest
> registration-chain chunk (per hierarchy-constraints
> precedent: "evidence-rich chunk > nominally matching
> filename"); register-client-js identified as stronger
> candidate than register-via-block-json for Bridge
> evidence.
>
> **Strategic role**: Bridge Pattern audit prerequisite
> + Law 3 (Authority Continuity) dependence stress test.
> Phase 8.12.5 (mid-cycle retro between Phase 8.12 and
> Phase 8.13 audit consideration).
>
> **Methodological framing**: This retro applies **dual-lens
> analysis** with explicit Law 3 dependence test:
> - **Lens A**: Bridge Pattern confirmation (4th context
>   instance? what sub-character?)
> - **Lens B**: Law 3 dependence stress test ("Is Bridge an
>   independent invariant or substructure of Law 3 Authority
>   Continuity?")
>
> **Critical disclaimer**: Per Phase 8.10 Law 1 trap
> precedent + user warning: **"'Cross-runtime exists' ≠
> law-tier significance."** Honest screening must
> distinguish "boundary-crossing" from "constitutionally
> meaningful continuity bridge."
>
> **Q9 retro discipline**: Confirm / Distributed / Divergent /
> Additive verdict per Phase 7.6+ retroactive verification
> protocol. Each lens may produce distinct verdict.

### Retro context

This chunk was authored 2026-05-09 (Phase 7-native), pre-
Bridge-Pattern-surfacing. Original analysis describes
client-side block registration with explicit DUAL REGISTRATION
framing:
- "Block already registered server-side via PHP (typical
  case), and you need to declare the editor-side `edit`
  components"
- "`name` MUST match between PHP `register_block_type()` and
  JS `registerBlockType()` for the dual registration to bind
  to one block"
- "Hardcoding the block name as a string in both PHP and JS.
  Drift risk. Import from `block.json` on both sides where
  possible."

Pre-formalization "dual registration" + "binding" + "drift
risk" language is potentially Bridge-Pattern-adjacent.

The retro questions:
- **Q1 (Lens A)**: Does block registration constitute a
  4th Bridge Pattern instance qualifying for Phase 8.13
  audit?
- **Q2 (Lens B)**: Is Bridge Pattern an independent
  constitutional invariant OR substructure of Law 3
  (Authority Continuity)?

### LENS A — Bridge Pattern verification

#### Bridge Pattern character signature (post-Phase-8.12)

Bridge Pattern's defining characteristics (from 4 instances:
script-translations + locale-switching + notices + directive-
protocol):

1. **Cross-runtime authority/data preservation** (PHP ↔ JS)
2. **Direction**: PHP-initiated → HTML/transport-mediated → JS-consumed
3. **Medium**: HTML attributes / inline `<script>` / data
   attributes / AJAX
4. **Function**: preserves identity / state / authority /
   instructions across runtime boundary

#### Block registration mechanism analysis

Block registration involves multiple potentially-bridge-like
mechanisms:

| mechanism | Bridge Pattern character? |
|---|---|
| **block.json shared filesystem source** | ⚠ ADJACENT — both PHP and JS independently READ same file; not "PHP transmits to JS" |
| **PHP register_block_type → editorScript enqueue → JS execution** | ✅ CLEAN BRIDGE — PHP-initiated script enqueue, HTML-mediated `<script>` tag, JS-consumed via runtime execution + registerBlockType call |
| **Block name identity binding (dual registration)** | ✅ CLEAN BRIDGE — name preserved across PHP+JS; "binds to one block" is identity continuity |
| **block.json `editorScript: file:./index.js` resolution** | ✅ CLEAN BRIDGE — PHP-side declarative reference resolved into HTML-mediated script load |
| **Auto-bridging mechanisms (textdomain → wp_set_script_translations, etc.)** | ✅ CLEAN BRIDGE — already captured in script-translations chunk; not new evidence here |

Critical screening per user's guidance ("'Cross-runtime
exists' ≠ law-tier significance"):

| consideration | Bridge significance? |
|---|---|
| Is block registration STRUCTURALLY NECESSARY (load-bearing) or merely IMPLEMENTATION CONVENIENCE? | **STRUCTURALLY NECESSARY** per chunk's own anti-pattern: "Without server-side registration, these functionalities will not operate correctly." Dual registration enables Dynamic Rendering, Block Supports, Block Hooks, Style Variations, theme.json per-block styling. |
| Could block registration work without bridge? | THEORETICALLY (separate metadata sources) — but loses architectural integration. Bridge IS load-bearing for full functionality. |

#### Bridge sub-character analysis (NEW)

Comparing block registration Bridge to prior 4 Bridge
instances reveals a **NEW Bridge sub-character**:

| instance | sub-character | what bridges? |
|---|---|---|
| script-translations | data Bridge | locale_data (runtime data) |
| locale-switching | static-asymmetric Bridge | locale-data (re-injection for context switch) |
| notices | round-trip Bridge | notice ID + dismissal state |
| directive-protocol | reactive-subscription Bridge | reactive subscription topology |
| **register-client-js** | **identity-binding Bridge (NEW)** | **block name + capability declarations** |

> **NEW Bridge sub-character observation**: identity-binding
> Bridge — bridges block IDENTITY (name) + capability
> DECLARATIONS (supports, attributes, etc.) rather than
> runtime DATA or BEHAVIOR.

This extends the Phase 8.12 sub-character observation to
**5 distinguishable sub-characters**: data / static-
asymmetric / round-trip / reactive-subscription / **identity-
binding**.

#### Bridge Pattern manifestation degree analysis

Block registration exhibits Bridge Pattern in MULTIPLE
mechanisms simultaneously:

```
Block registration Bridge composite manifestation:

   1. block.json shared-source convergence  (FEDERATION-LIKE; not pure Bridge)
   2. PHP register_block_type (server initiation)
   3. block.json editorScript declaration
   4. WP enqueues script via HTML <script> tag (Bridge mediation)
   5. Browser loads + executes script
   6. JS registerBlockType() called (Bridge consumption)
   7. Server + client block instances bind via shared name (Bridge identity)
```

This is a **composite Bridge manifestation** with 5+
underlying mechanisms (similar to REST route Phase 8.5+
composite Doctrine 6 manifestation precedent).

#### Q9 verdict (Lens A) — ADDITIVE WITH CAVEATS

| verdict | applicability for Bridge Pattern |
|---|---|
| Confirm | Bridge manifestation matches existing 4 instances exactly | NO — sub-character is NEW (identity-binding vs data/reactive) |
| Distributed | Single Bridge distributed across multiple mechanisms | YES — composite manifestation present |
| Divergent | Structurally different from Bridge | NO — clear Bridge character (PHP→HTML→JS direction confirmed) |
| **Additive** | **Adds 4th-context Bridge instance + NEW identity-binding sub-character** | **YES — primary verdict (with caveats)** |

> **Lens A verdict: ADDITIVE WITH CAVEATS.**
>
> Block registration constitutes 4th bounded context Bridge
> Pattern instance (block-authoring) with NEW identity-
> binding sub-character. **CAVEATS**:
> - block.json shared-source mechanism is FEDERATION-LIKE
>   adjacent (not pure Bridge); only PHP→script-enqueue→JS
>   path is clean Bridge
> - identity-binding sub-character is structurally novel
>   (bridges declarations not runtime data)
> - composite manifestation requires multi-mechanism
>   characterization

### Bridge Pattern audit gate readiness UPDATE (post-this-retro)

| criterion | pre-this-retro | post-this-retro |
|---|---|---|
| 1 — Context PRESENCE ≥ 4 | 3 (i18n + admin-ui + interactivity) | **4 (+ block-authoring)** ✅ |
| 2 — Architectural variants ≥ 2 | 4 sub-characters | **5 sub-characters** ✅ |
| 3 — Intra-context density ≥ 1 | i18n (2 chunks) | i18n (2 chunks) ✅ |
| 4 — Q10 sub-pattern check | sub-character observation surfaced Phase 8.12 | + identity-binding sub-character (Phase 8.12.5) ✅ |
| 5 — Forward + retro both | 2F + 1R | **2F + 2R** ✅ |

> **Bridge Pattern audit gate criteria: 5/5 FULLY MET.**
> Phase 8.13 Bridge Pattern audit fully viable.

### LENS B — Law 3 (Authority Continuity) dependence stress test

> **Critical methodological discipline (Phase 8.12.5
> introduction)**: Per user's strategic guidance + Phase 7.8
> Resolution refusal precedent — the question now shifts
> from "Does Bridge recur?" to "Is Bridge its own law?"

#### Law 3 manifestation across all Bridge Pattern instances

Law 3 = Authority Continuity (authority preserved across
boundary crossings).

| Bridge instance | Law 3 manifestation? |
|---|---|
| script-translations | YES — msgid identity preserved across PHP+JS runtime boundary |
| locale-switching | YES — locale state preserved through stack-discipline + cross-runtime re-injection |
| notices | YES — notice ID + dismissal state preserved across PHP+JS+AJAX |
| directive-protocol | YES — directive declarations preserved across PHP→HTML→JS+DOM |
| **register-client-js** | **YES — block name identity preserved across PHP+JS via shared-source + script-enqueue** |

> **CRITICAL FINDING**: ALL 5 Bridge Pattern instances
> manifest Law 3 (Authority Continuity).

This is structurally analogous to Resolution Surface's
Phase 7.8 problem: ALL Resolution Surface instances were
paired with Arbitration via Doctrine 5.

#### Bridge Pattern character analysis vs Law 3

Bridge Pattern's constitutive characteristics:

| characteristic | Law 3 character? |
|---|---|
| Cross-runtime authority/data preservation | YES — Law 3's core function |
| PHP-initiated → HTML-mediated → JS-consumed direction | NEW — specific direction beyond Law 3's general boundary-crossing |
| HTML attributes / inline `<script>` / AJAX medium | NEW — specific medium beyond Law 3's general continuity contract |
| Identity/data preservation across boundary | YES — Law 3's core function |

> **Bridge Pattern is essentially: Law 3 + specific
> direction (PHP→HTML→JS) + specific medium (HTML/script
> attributes).**

This makes Bridge Pattern structurally LIKELY:
- (a) **Sub-pattern of Law 3** — Bridge as one specific
  manifestation form of Authority Continuity
- (b) **Independent law** — Bridge has structural depth
  (sub-characters + composite manifestations + cross-runtime
  specificity) sufficient for independent law tier

#### Phase 8.13 audit hypothesis space (Law 3 dependence preview)

Three structural options for Phase 8.13 Bridge audit (per
user's outcome anticipation):

**Option A — KB-Wide LAW promotion (Law 7)**:
- IF Bridge has structural independence from Law 3
- IF cross-character-category breadth (governance + semantic
  + reactive runtime + schema/registration = 4 categories)
  is sufficient
- IF continuity necessity transcends mere transport
- Constitutional consequence: KB Constitution v2 trigger

**Option B — Law 3 sub-pattern formalization (Law 3b)**:
- IF Bridge is structurally inherent to Law 3 character
- IF Bridge manifestations all manifest Law 3
- IF Bridge specificity (PHP→HTML→JS direction) is one
  dimension of Law 3's general boundary-crossing
- Constitutional consequence: Law 3 enrichment patch (Law
  3a/3b sub-form formalization analogous to Doctrine 6
  6-HARD/6-SOFT variants)

**Option C — Refusal**:
- IF Bridge is implementation transport without structural
  invariance
- IF cross-runtime mechanisms are organic to specific use
  cases (not generalizable invariant)
- Constitutional consequence: Bridge stays Recurring (cross-
  context); no formalization

> **My honest preview prediction**: **Option B (Law 3
> sub-pattern formalization) most likely**.
>
> Reasoning:
> - All 5 Bridge instances manifest Law 3 — this is
>   structurally analogous to Resolution's Doctrine 5
>   pairing
> - Bridge specificity (PHP→HTML→JS direction + HTML medium)
>   is a SPECIALIZATION of Law 3, not independent invariant
> - Phase 7.8 precedent: structurally inherent patterns
>   warrant lower-tier classification, not KB-Wide LAW

But this is **PREVIEW HYPOTHESIS only**. Phase 8.13 audit
will conduct full structural analysis with all 7 audit
gate criteria + 3 audit-specific evaluation dimensions.

#### Q9 verdict (Lens B) — Law 3 dependence STRONG

> **Lens B verdict: Law 3 dependence is STRONG.**
>
> Bridge Pattern is likely a Law 3 specialization (sub-
> pattern), not independent invariant. Phase 8.13 audit
> may produce Law 3b sub-pattern formalization rather than
> KB-Wide LAW promotion.

This finding mirrors Phase 7.8 Resolution refusal pattern
(Resolution refused because structurally inherent to
Arbitration via Doctrine 5).

> **Important constitutional precedent**: When all instances
> of a candidate ALSO manifest an existing law, the candidate
> may be a SUB-PATTERN of that law rather than independent
> invariant. This is the "doctrinal dependence" structural
> fit pattern.

### Combined dual-lens verdict synthesis

| lens | verdict | constitutional impact |
|---|---|---|
| **Lens A (Bridge Pattern)** | **ADDITIVE WITH CAVEATS** | 4th-context Bridge instance + NEW identity-binding sub-character; Bridge Pattern audit gate FULLY MET (5/5) |
| **Lens B (Law 3 dependence)** | **Law 3 dependence STRONG** | Bridge likely Law 3 sub-pattern (Law 3b) rather than independent KB-Wide LAW |

> **Combined Phase 8.12.5 verdict**: Bridge Pattern audit
> FULLY VIABLE (Phase 8.13 ready), BUT audit's likely
> outcome is Law 3 sub-pattern formalization rather than
> KB-Wide LAW promotion.

### Constitutional precedents — Phase 8.13 audit preparation

Per Phase 8.5 audit gate bifurcation, Bridge Pattern audit
should use **Standard audit gate** (5 criteria) — Bridge is
operational pattern (not governance mechanism specifically).

**5/5 Standard criteria MET via this retro.**

Phase 8.13 audit additional considerations (from prior
audit precedents):
- Phase 7.8 Resolution audit added 3 evaluation dimensions
  (Architectural ubiquity / Predictive power / Anti-confusion
  clarity / Operational consequence / Independent
  meaningfulness)
- Phase 8.x Mediation audit added 2 governance-specific
  criteria (Gating abstraction independence / Structural
  consequence)
- Phase 8.6 Mediation re-audit added 3 dimensions (HARD/SOFT
  coherence / Law 1 independence / Structural necessity)

Phase 8.13 Bridge audit may add:
- **Law 3 independence stress test** (analogous to Phase 8.6
  D2 Law 1 independence test) — does Bridge have meaning
  independent of Law 3?
- **Cross-character-category coverage analysis** (governance
  + semantic + reactive + schema = 4 categories)
- **Sub-character coherence test** (do 5 Bridge sub-
  characters constitute single invariant or multiple
  patterns?)

### NEW shared-source convergence pattern observation (Phase 8.12.5)

This retro additionally surfaces a **NEW pattern observation
adjacent to Bridge Pattern**:

> **Shared-source convergence pattern**: Multiple runtime
> participants independently READ same authoritative source
> (filesystem file, registry, etc.) WITHOUT one initiating
> transmission to the other.

Examples observed:
- block.json (read by PHP register_block_type AND JS
  registerBlockType import)
- theme.json (read by style-engine AND multiple block-
  authoring/theme-config consumers)
- (potential) wp-config.php constants (read by core +
  plugins independently)

Distinction from Bridge Pattern:
- **Bridge**: PHP-initiated transmission → HTML-mediated →
  JS-consumed
- **Shared-source convergence**: independent consumers
  CONVERGING on shared filesystem source

This is structurally distinct from:
- Bridge Pattern (transmission-based)
- Federation Pattern (multiple participants registering with
  shared registry)

Status: **SURFACED ONLY.** Single-instance observation
(block.json); cross-context verification needed before
formalization. Phase 8.14+ candidate.

### Q10 sub-pattern emergence (retro)

> **Q10 RETRO ANSWER: YES — NEW Bridge sub-character
> observation (identity-binding Bridge) + NEW shared-source
> convergence pattern observation.**

Bridge Pattern sub-character formalization may warrant
Phase 8.13 audit consideration:
- 5 sub-characters: data / static-asymmetric / round-trip /
  reactive-subscription / identity-binding
- Audit may evaluate sub-character formalization as Bridge
  variant structure (analogous to Doctrine 5 Integrated/
  Distributed/Hybridized variants)

Per Phase 8.7 conservative discipline: defer formalization
to Phase 8.13 audit decision.

### Constitutional Field Test additions (post-retro)

#### Table A — Universal Law Manifestation (retro additions)

| Law / Doctrine | Pre-retro reading | Post-retro reading | Status change |
|---|---|---|---|
| **Bridge Pattern (candidate)** | (didn't exist at chunk authoring time) | 4th-context manifestation + NEW identity-binding sub-character + Law 3 dependence STRONG | **Bridge Pattern audit gate 5/5 MET; audit-time Law 3 dependence test required** |
| **Law 3 (Authority Continuity)** | implicit (block name continuity) | confirmed STRONG — block name identity preserved across PHP+JS via shared-source + script-enqueue | (retroactively confirmed) |
| **Federation Pattern** | implicit (block.json as registry) | confirmed — multiple block plugins federate via block.json + WP block registry | (retroactively confirmed) |
| **Doctrine 6 (Authority Access Mediation)** | (didn't exist at chunk authoring time) | NOT PRESENT in registration mechanism | (consistent with computational-architectural framing for registration) |

#### Table B — Pattern Recurrence (retro additions)

| Candidate | Pre-retro status | Post-retro outcome | Effect on candidate |
|---|---|---|---|
| **Bridge Pattern** | Recurring (cross-context); 3 contexts (i18n + admin-ui + interactivity) | 4 contexts (+ block-authoring); NEW identity-binding sub-character; audit gate FULLY MET | **Phase 8.13 audit FULLY VIABLE; Law 3 dependence stress test required** |
| **Bridge Pattern sub-character variants (NEW observation Phase 8.12)** | 4 sub-characters | **5 sub-characters (+ identity-binding)** | **STRENGTHENED toward audit-time formalization consideration** |
| **Shared-source convergence pattern (NEW observation)** | did not exist | block.json mechanism observed | **Surfaced (single-instance; cross-context verification needed)** |
| **Composite Bridge manifestation (NEW observation)** | did not exist | block registration exhibits 5+ underlying mechanisms | **Surfaced (parallel to REST + hierarchy-constraints composite Doctrine 6 manifestations)** |
| **Law 3 sub-pattern formalization candidate (NEW)** | did not exist | All 5 Bridge instances manifest Law 3; Bridge may be Law 3 specialization | **Surfaced (Phase 8.13 audit may formalize)** |

### NEW KB-level findings

**1. Bridge Pattern audit gate FULLY MET (5/5 Standard criteria)**

| criterion | status |
|---|---|
| 1 — Context PRESENCE ≥ 4 | ✅ 4 contexts |
| 2 — Architectural variants ≥ 2 | ✅ 5 sub-characters |
| 3 — Intra-context density ≥ 1 | ✅ i18n (2 chunks) |
| 4 — Q10 sub-pattern check | ✅ sub-character + composite observations |
| 5 — Forward + retro both | ✅ 2F + 2R |

> **Phase 8.13 Bridge Pattern audit FULLY VIABLE.**
> Audit may proceed when constitutional schedule permits.

**2. Law 3 dependence STRONG — Phase 8.13 audit critical analytical work**

All 5 Bridge instances manifest Law 3 (Authority Continuity).
This is structurally analogous to Resolution Surface's
Phase 7.8 problem (all Resolution instances paired with
Arbitration via Doctrine 5).

> **Phase 8.13 audit's PIVOTAL question**: Is Bridge Pattern
> structurally INDEPENDENT of Law 3, or a SPECIALIZATION
> (sub-pattern) of Law 3?

Per Phase 7.8 precedent + Phase 8.6 D2 Law independence test:
"inherent structural meaning warrants lower-tier
classification."

**3. NEW Bridge sub-character: identity-binding Bridge**

5 sub-characters now identifiable:
- Static-data Bridge (script-translations)
- Asymmetric Bridge (locale-switching)
- Round-trip Bridge (notices)
- Reactive-subscription Bridge (directive-protocol)
- **Identity-binding Bridge (block registration)** — NEW

**4. NEW shared-source convergence pattern observation**

Distinct from Bridge Pattern (transmission-based) and
Federation Pattern (registry-based). Multiple runtime
participants independently CONVERGING on shared filesystem
authority source. Single-instance observation; cross-context
verification needed before formalization.

**5. NEW composite Bridge manifestation observation**

Block registration exhibits 5+ underlying Bridge mechanisms
simultaneously (parallel to REST route Phase 8.5+ composite
Doctrine 6 manifestation + hierarchy-constraints composite
Doctrine 6 manifestation). Composite manifestation pattern
recurs across distinct candidates (Doctrine 6 + Bridge).

> **Composite manifestation pattern observation**:
> Constitutional candidates may exhibit composite
> manifestations (multi-mechanism single-instance)
> independent of variant structure. This is a meta-pattern
> across Doctrine 6 + Bridge Pattern. Phase 8.14+ may
> formalize composite manifestation as constitutional
> observation category.

### Phase 8.13 Bridge Pattern Audit preparation summary

**Audit subject**: Bridge Pattern KB-Wide LAW promotion
candidacy.

**Audit gate type**: Standard (5 criteria) + Phase 8.13
audit-specific dimensions (likely):
- Architectural ubiquity (cross-character-category coverage:
  governance + semantic + reactive + schema = 4 categories)
- Predictive power
- Anti-confusion clarity vs Law 3
- Operational consequence
- **Law 3 independence stress test (KEY)** — analogous to
  Phase 8.6 D2 Law 1 independence test
- **Sub-character coherence test** — do 5 Bridge sub-
  characters constitute single invariant or multiple
  patterns?
- **Composite manifestation analysis**

**Pre-audit hypothesis** (per this retro Lens B finding):
- KB-Wide LAW promotion: UNLIKELY (Law 3 dependence STRONG)
- **Law 3 sub-pattern formalization (Law 3b)**: LIKELY
- Refusal: POSSIBLE (if Bridge is implementation transport
  without structural invariance beyond Law 3)

**Constitutional precedents to apply**:
- Phase 7.8 Resolution refusal (structurally inherent
  patterns warrant lower-tier classification)
- Phase 8.x Mediation Doctrine-tier promotion (audit-driven
  doctrine creation precedent — analogous candidate could be
  Law 3b sub-pattern creation)
- Phase 8.6 Mediation re-audit (re-audit discipline)

### Mediation audit criterion impact (post-this-retro)

> **Note**: Phase 8.12.5 retro produces NO Mediation
> criterion advance (Lens A confirmed Bridge; not Doctrine
> 6). Doctrine 6 status UNCHANGED from Phase 8.10.

### KB-wide pattern recurrence updates

**Bridge Pattern**: 3 contexts → **4 contexts** (i18n +
admin-ui + interactivity + block-authoring).

**Bridge Pattern instances**: 4 → **5** (block registration
NEW).

**Bridge Pattern sub-characters**: 4 → **5** (+ identity-
binding).

**Bridge Pattern audit readiness**: 3-4/5 criteria → **5/5
criteria MET**.

**Composite manifestation observations**: 3 (REST +
hierarchy-constraints + 6i within governance-toggles) → **4
(+ block registration Bridge composite)**.

**Shared-source convergence pattern observation**: NEW (1
instance; cross-context verification needed).

**Law 3 sub-pattern formalization candidate**: NEW (Phase
8.13 audit may evaluate).

### Constitutional principle (retro-derived)

> **The maturation question shifts from "Does X recur?" to
> "Is X its own law?"** Phase 8.12.5 demonstrates this
> jurisprudential evolution: Bridge Pattern recurrence is
> CONFIRMED (5 instances × 4 contexts), but Law 3 dependence
> stress test reveals deeper question: structural independence
> warrants KB-Wide LAW; structural dependence warrants sub-
> pattern formalization.

This refines Phase 7.8 Resolution refusal precedent:
constitutional independence test is now the load-bearing
criterion for KB-Wide LAW promotion, not just evidence
quantity or breadth.

### Comparison: Phase 8.9 + 8.12 + 8.12.5 retro arc

| dimension | P1 (style-engine) | P3 (supports-field) | P4 (directive-protocol) | **P5 (block registration) — THIS** |
|---|---|---|---|---|
| Verdict | DIVERGENT | ADDITIVE + CONFIRM | ADDITIVE + STRENGTHENED + DIVERGENT | **ADDITIVE WITH CAVEATS + Law 3 dependence STRONG** |
| Lens count | 1 | 1 | 3 | **2 (with explicit dependence test)** |
| Constitutional impact | breadth ceiling | depth advance | promotion + taxonomy + reinforcement | **audit gate readiness + Law 3 dependence finding** |
| Methodology | single-lens | single-lens | multi-lens | **dual-lens with dependence test** |

**4 retros × 4 distinct outcome types** + **NEW dual-lens
dependence test methodology**. Phase 8.9-8.12.5 retro arc
demonstrates KB's Q9 retro discipline at expanding sophistication.

### Anticipated next chunks (post-this-retro)

1. **Phase 8.13 Bridge Pattern Audit** — FULLY VIABLE
   post-this-retro. Likely audit produces Law 3 sub-pattern
   formalization (Law 3b) rather than KB-Wide LAW promotion.

2. **Phase 8.14 Bounded context character bifurcation
   formalization** — governance-architectural vs
   computational-architectural distinction (Phase 8.12 P4
   surfaced). Independent of Bridge Pattern audit outcome.

3. **`data-layer.persistence` Q9 retro (alternative)** —
   could provide additional Bridge Pattern evidence (REST +
   AJAX persistence cycle parallels notices round-trip
   Bridge); not strictly necessary post-Phase-8.12.5 since
   audit gate already met.

4. **`style-engine.preset-materialization` Bridge Pattern
   re-evaluation** — Phase 8.9 P1 already verified DIVERGENT
   for Doctrine 6, but did NOT specifically test Bridge
   Pattern character. Style-engine CSS variable PHP→HTML→JS
   computed style flow may exhibit weak Bridge.

5. **Forward authoring resumption (deferred)** — interactivity
   runtime-state, hydration, etc. Constitutional development
   may proceed at Bridge Pattern audit + character taxonomy
   layers before forward chunks.

Recommended: **Phase 8.13 Bridge Pattern Audit** — audit
prerequisites met; structural analytical work pending. Phase
8.13 is likely the most consequential audit since Phase 7
(per user's strategic framing) — Bridge may produce KB-Wide
LAW promotion (rare) OR Law 3b sub-pattern formalization
(likely) OR refusal (possible). All three outcomes
constitutionally valuable.

### Status updates

- This file's overall `status` remains `stable` (original
  evaluation preserved).
- Retro patch adds Q9 dual-lens verdict (ADDITIVE WITH
  CAVEATS + Law 3 dependence STRONG) + Bridge Pattern audit
  gate FULL READINESS + NEW identity-binding sub-character +
  NEW shared-source convergence pattern observation +
  composite Bridge manifestation observation.
- Original chunk content (lines 1-131) UNCHANGED; this retro
  is purely additive at end of file.
