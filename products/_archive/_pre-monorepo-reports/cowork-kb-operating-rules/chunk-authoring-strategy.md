---
name: chunk authoring strategy — schema atomization + spike workflow
description: For Axismundi KB (DSL chunks), schema-heavy areas need field-cluster atomization not monster chunks; new sub-areas need spike-then-batch workflow not mass batches; 6-slot DSL may need extension for ontology-heavy areas.
type: feedback
originSessionId: 906565b5-2dd0-41e7-96c6-ace8de371cb8
---
Strategic guidance from user (2026-05-09) after first 5 registration chunks
validated. Applies to all future chunk authoring.

## Rule 1 — Schema atomization (field-cluster, not monster)

For schema-heavy areas (`block.json`, `theme.json`, `supports`, `attributes`),
**do NOT write a single `*-schema.md` reference monster**. Atomize by
**field cluster**:

❌ Bad: `block.json-schema.md` (one chunk covering all fields)
✓ Good:
- `block.json-basic-metadata.md` (name, title, description, category, icon)
- `block.json-assets.md` (editorScript, viewScript, style, etc.)
- `block.json-supports.md` (supports field)
- `block.json-context.md` (providesContext, usesContext)
- `block.json-rendering.md` (render, render_callback)
- `block.json-style-assets.md` (style/editorStyle/viewStyle)
- `block.json-api-version.md` (apiVersion)

**Why:** Without atomization, the chunk regresses to handbook prose
monster — defeats the DSL refinement purpose.

## Rule 2 — Sub-area spike, then batch

For schema-heavy / ontology-heavy areas, the safe workflow is:
1. **Spike**: write 2-3 sample chunks
2. **Adapt DSL**: verify 6-slot holds, or extend if needed
3. **Batch**: only after pattern validates, expand

**Don't do**: mass batches (5+ chunks at once) before DSL is proven for
the area's complexity profile. Procedural areas (e.g., registration)
work fine with batches; ontology areas (supports, attributes,
theme.json) need spike validation first.

## Rule 3 — DSL may need extension for ontology areas

The current 6-slot DSL (WHEN / SHAPE / REQUIRES / INVARIANTS /
ANTIPATTERNS / RELATED) is sufficient for **procedural rules**
(registration, function calls, hook patterns).

**It may NOT be sufficient for capability semantics** like `supports`,
which involves: capability → editor behavior → serialization → wrapper
attributes → theme.json interaction → preset resolution.

For such areas, DSL might need slots like:
- `EDITOR EFFECTS` (UI generated)
- `SERIALIZATION EFFECTS` (DB / markup output)
- `THEME.JSON INTERACTION`
- `STYLE ENGINE EFFECTS`

**Decision rule**: write the spike chunk first with 6 slots. If the SHAPE
or INVARIANTS slots become heterogeneous bags of mixed concerns,
extension is justified. Document the new slot in `dsl-spec.md` AND
mark the chunk's frontmatter (e.g., `dsl_extension: editor-effects`).

## Rule 4 — block-authoring substrate-first ordering

**Revised after `block.supports.color` validated (2026-05-09):** supports
is a *behavior layer ON TOP of attributes*. Without attributes substrate
locked in, every supports flag chunk re-poses the same unanswered
questions (where do attributes get added? what shape do they serialize?
how do defaults merge? etc.). Continue with the substrate first:

1. `registration/` — DONE (5 chunks)
2. `block-json/basic-metadata`, `block-json/assets`,
   `block-json/supports-field` — DONE (3 chunks)
3. `block-json/attributes` — **NEXT, substrate priority**.
   Likely splits into 2-3 chunks (core schema vs source taxonomy vs
   deprecated sources). Watch:
   - source taxonomy explosion (attribute / text / html / query / meta /
     children) — `query` is a mini-parser DSL on its own
   - serialization contract begins connecting edit / save / parser /
     validation / deprecated migrations
   - "schema vs extraction" separation: `{type, default}` is state
     schema, `{source, selector}` is DOM extraction rule — different
     ontology layers. Try single chunk first; split if pressure
4. `block-json/context` — providesContext / usesContext.
   Underrated: this is **block-tree-scoped dependency injection**, not
   just "parent → child data passing". DSL'ing this well unlocks future
   chunks for Interactivity API, data layer, template inheritance.
5. `block-json/hierarchy-constraints` — parent + ancestor + allowedBlocks
   bundled. All 3 are "insertion constraints" — same ontology family.
   Don't atomize yet; spike as one chunk first.
6. **Return to `supports/`** — typography, spacing, layout, etc. By
   then, every "where does this attribute land?" question is already
   answered by attributes substrate; supports chunks become much
   leaner.
7. Later: `block.json/` remaining fields (selectors, styles, example,
   variations, block-hooks, render, internationalization, version),
   then `edit-save/`, `block-wrapper/`, `variations/`, `transforms/`,
   `deprecation/`.

**Hard rule:** do not continue supports/ batch until attributes / context
/ hierarchy substrates are written.

## Rule 6 — Operational semantic density > historical completeness

The KB's purity principle: **active substrate gets first-class chunks;
deprecated APIs are absorbed as ANTIPATTERNS in adjacent chunks**, not
preserved as their own chunks.

**Why:** Making deprecated APIs (e.g., `meta` source, `__experimentalDuotone`,
`register_block_type_from_metadata()`) into separate chunks reverts the
KB to handbook archivalism — completeness over operational utility.
The user's 2026-05-09 confirmation: *"deprecated/meta source를 chunk
안 만든 결정 잘한 선택입니다 ... 'historical completeness'보다 'operational
semantic density'가 KB purity 유지에 훨씬 좋아요."*

**How to apply:**
- New deprecated API encountered → add to ANTIPATTERN slot of the
  closest active substrate chunk
- Deprecated API replaced by a successor → mention in successor's
  ANTIPATTERN ("X is deprecated, use this rule instead") AND in
  successor's `deprecates: [X]` frontmatter field
- Only create a deprecated-status chunk if a downstream chunk needs
  to reference its semantics (e.g., a migration rule that explains
  the old → new mapping)

## Rule 7 — KB evolution layer order (observed, not prescribed)

The current KB has organically evolved in this layer order:
1. **Syntax layer** — block.json fields, registration calls, signature shapes
2. **Behavior layer** — supports cascade, attribute extraction
3. **Runtime layer** — markup-representation IR, parser recovery,
   serialization round-trip
4. **Graph layer** — context propagation, hierarchy constraints,
   InnerBlocks composition
5. **Design-token execution layer** (entering 2026-05-09 with
   supports.typography) — preset/custom/fluid token emission, style
   engine integration, theme.json ↔ supports cascade resolution

Future expected inflection: **Interactivity API + bindings + core-data**
will pull the KB into a "declarative reactive runtime" model where
chunks describe state machines, not procedures.

This isn't a prescribed order to follow rigidly — it's an observation
that the substrate-first approach naturally surfaces this layering.
Future area planning should respect this gravitational pull rather than
forcing chunks into earlier layers prematurely.

## Rule 8 — Phase shift (post-substrate): capability family abstraction

After substrate closure (2026-05-09 — registration / block-json /
edit-save / wrapper-attributes / dynamic-rendering / inner-blocks /
markup-representation / deprecation all written), the validation target
for new chunks is no longer "does the 6-slot DSL hold?". The DSL has
been proven across 20 chunks. The new target is:

> **"How reusable is the capability ontology archetype across
> sibling supports flags?"**

The first capability chunk (`block.supports.color`) established a 5-layer
H3 sub-section convention: Editor effects / Attribute effects / Wrapper
effects / Serialization effects / theme.json interaction / General
invariants. The typography spike is the validation test for whether
this skeleton is reusable.

**Two possible outcomes from supports.typography spike:**
- CASE A: H3 skeleton transfers cleanly. Subsequent supports flags
  (spacing, dimensions, border, shadow) become a **semi-batchable**
  family with shared archetype. Each new chunk fills the same 5
  sub-sections with flag-specific content.
- CASE B: typography-specific concerns (fluid typography, font loading,
  preset resolution) blow past the skeleton. Supports family needs
  reclassification — perhaps splitting "purely-cascading flags" from
  "runtime-emitting flags" (fluid typography being the latter).

**Premature optimization to avoid:** maintenance audit work
(cross-ref bidirectionality check, planned-link cleanup, etc.) until
the growth phase truly stops. With variations / transforms / styles /
layout / interactivity still ahead, the graph topology will keep
shifting; auditing now would re-audit later.

## Rule 9 — DSL pressure pattern (validated through 6 supports chunks)

After 6 supports chunks (color / typography / spacing / dimensions /
shadow / background) all maintained the 6 H2 + 6 H3 skeleton, the
empirical pattern is:

> **Ontology complexity increases → INVARIANTS density increases.
> DSL skeleton does NOT collapse.**

The pressure point is **chunk boundary** ("where does this ontology
end?"), not **slot sufficiency** ("do we need new slots?").

Implication: DSL extension is NOT the right response to growing
chunk complexity. Two correct responses instead:
1. Let INVARIANTS density grow within the H3 sub-section convention
2. If chunk becomes too large to manage, split by **ontology
   boundary** (e.g., layout-core vs layout-governance) rather than by
   slot category

## Rule 10 — Layout spike audit (subsystem-tier capability)

`block.supports.layout` is the first capability that is NOT a
"styling capability + cascade" but a **structural governance
subsystem**. Prior capabilities all converged to one execution
surface (CSS emission); layout includes child governance / insertion
topology / flow orchestration / inheritance / composition contracts
BEFORE emission.

**Spike approach (A'): single chunk + post-write ontology audit.**

Audit metrics (NOT just size):
- sub-property clustering (do allow* flags form a coherent cluster?)
- governance-vs-styling ratio (how much chunk is policy vs CSS?)
- Editor-effects density (Editor effects sub-section line count)
- layout↔spacing authority overlap (does blockGap discussion shift
  rendering authority from spacing to layout?)
- child-governance emergence (new ontology axis for child controls)

**Split-trigger criteria (2+ → consider 2-way split: `layout-core` +
`layout-governance`):**
- INVARIANTS > 180 lines
- Editor effects sub-section alone > 50 lines
- allow* cluster occupies ≥ 35% of chunk
- RELATED > 12 entries
- styling vocabulary and governance vocabulary diverge inside the
  chunk (different terms describing related things)
- "Wrapper effects" sub-section smaller than implicit "composition
  policy" content

## Rule 11 — Governance batch audit (2026-05-09)

The governance batch chunk (16 minor flags: align, anchor, ariaLabel,
className, customClassName, contentRole, html, inserter, listView, lock,
multiple, renaming, reusable, splitting, visibility, alignWide) needs
**ontology-based** audit, not size-based.

**Governance-specific split trigger** (2+ → consider 2-way split into
`governance-editor-affordances` + `governance-render-affordances`):
- rendering-affecting flags ≥ 40% of total flags in chunk
- Wrapper effects sub-section > Editor effects sub-section
- theme.json interaction documented for some flags only (mixed)
- Serialization semantics concentrated in a sub-cluster (not uniform)
- Editor-only vocabulary and markup/render vocabulary diverge

**Audit dimensions for governance flags** (categorize each flag):
- editor-only vs render-affecting
- lifecycle governance (multiple, reusable) vs presentation governance
  (align, className) vs identity governance (anchor, ariaLabel)
- discoverability (inserter, visibility, listView) vs mutability (lock,
  renaming) vs identity (anchor, ariaLabel, contentRole) vs alignment
  (align, alignWide) vs class (className, customClassName) vs editing
  (html, splitting) vs reuse (reusable, multiple)

**The spike's true validation:**
> "Does the Governance family have a coherent single ontology, OR
> does it bifurcate into editor-affordance vs render-affordance
> subtypes?"

If the answer is "single ontology with internal subtype taxonomy",
single chunk works with internal H4 sub-clustering. If "two distinct
ontologies", physical split is justified.

**align is a special case**: likely a **layout satellite** rather than
pure governance. wide/full alignment couples to layout (constrained
type's content/wide width). Cross-family flag.

**The layout spike's true validation is:**
> "Can layout remain a single ontology, OR does it bifurcate into
> styling + governance subsystems?"

Not "does the DSL hold?" — that's already validated. The new question
is whether ontology atomization principles (Rule 1) need to expand
beyond schema-field-cluster to capability-bifurcation (style vs
governance).

## Rule 5 — Stay in block-authoring before crossing contexts

**Don't move to other bounded contexts** (`theme-config`, `plugin-dev`,
etc.) until block-authoring is complete. Reason: cross-context jumping
before pattern is locked causes "DSL register/voice" drift. Each
bounded context has its own complexity profile; lock one first as the
exemplar, then the others are easier to align.

## Why this matters

User said directly: *"솔직히 말하면, 지금부터가 진짜 어려운 구간이에요.
registration은 procedural rule이라 DSL화가 쉬운 편입니다. 근데 attributes,
supports, theme.json, Interactivity API, data stores부터는 거의 'WordPress
ontology engineering' 영역으로 들어갑니다. 여기서 KB 품질이 갈립니다."*

Translation: registration was easy mode. Schema/ontology areas are
where KB quality is decided. Don't ship sloppy DSL into those areas.
