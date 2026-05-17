---
name: Axismundi KB project — full vision (Knowledge OS for WP dev)
description: The KB is NOT a prose transcription of WP handbook. It is a Prompt DSL / Knowledge OS that extracts actionable rules from WP docs for LLM consumption. Every chunk approach must respect this.
type: project
originSessionId: 906565b5-2dd0-41e7-96c6-ace8de371cb8
---
The Axismundi knowledge base project is **NOT** a re-formatted WP handbook.
It is a "WordPress development LLM-only Knowledge OS" — a refinery that
converts human-oriented WP documentation into LLM-actionable rules.

**Pipeline:**
WP Developer Docs → LLM-friendly lib → Prompt DSL → Domain-Driven Design

**Layers:**
- handbook (설명, human explanation)
- DSL (규칙, machine-actionable rules)
- LLM 실행 (자동화, automated execution)

**4 system layers:**
- **Source:** WP Core, Gutenberg repo, Developer Docs
- **Refinery:** Markdown + Frontmatter (metadata filtering)
- **Brain:** Domain Ontology (DDD bounded contexts)
- **Output:** Prompt DSL (actionable snippets)

**4 phases:**
- Phase 1: Knowledge Extraction (extraction + refinement)
- Phase 2: Domain-Driven Modeling (bounded contexts for WP domain)
- Phase 3: Prompt DSL Design (language design)
- Phase 4: Filtering & Maintenance

**Filter criteria (hard rules):**
- NO legacy / deprecated content
- Latest version only (currently WP 6.9.4; WP 7.0 imminent)
- Skip human-only content (tutorials, "Welcome to..." intros, FAQ pedagogy)
- Patternize for LLM consumption

**Why:** WP handbook is HUGE (~3MB just for block-editor section), too noisy
for LLMs, doesn't fit in a single context. LLM training data is also stale.
The KB serves as both the LLM's memory (what's true now) AND its programming
layer (how to act on that knowledge).

**Why this matters for chunk authoring:**
- Verbatim transcription is wrong. Extract rules instead.
- Every chunk needs frontmatter for filtering (domain, context, wp_version,
  status, etc.).
- Folder structure should reflect bounded contexts (DDD), not just the
  WP handbook hierarchy.
- A chunk should look like a programming language reference (rules, patterns,
  invariants, antipatterns, related), not like a tutorial.
- Pages like "Welcome to Block Editor Handbook" intro / Quick Start Guide /
  Tutorial: Build your first block / FAQ → likely SKIPPED. They're pedagogy.

**Architectural evolution observation (2026-05-09, updated):**
The KB has evolved past "Handbook → chunks". After registration + block.json
spike + supports.color stress test + attributes/context/hierarchy substrate
phase, the chunks are now modeling:
- parser behavior (block delimiter ↔ attribute extraction)
- editor behavior (UI generation from supports flags)
- serialization behavior (delimiter + class + inline style)
- style engine behavior (preset → CSS custom property)
- context propagation (providesContext / usesContext as DI)
- block tree runtime model (hierarchy + context = topology + propagation)

User framing as of 2026-05-09: **"WordPress execution semantics map"** —
the KB is no longer a documentation system. It's becoming an operational
ontology of how WordPress runtime works.

**Key ontology insights surfaced through chunk authoring:**
- **attributes = behavior graph hub** — not just schema; producer/consumer
  contract for edit/save/parser/markup-IR/deprecation/migration
- **hierarchy + context = same graph, two views** — topology vs propagation
- **deprecation = compatibility runtime ON the parser layer** — not just
  "old schema migration" but "how the current parser interprets old
  serialized IR". Depends on markup-representation IR + edit/save contract.
- **markup-representation = Gutenberg's hybrid IR** — HTML + delimiters +
  JSON attrs as one canonical document format. The "weirdest" part of
  Gutenberg.

**Anticipated remaining inflection points:**
- `query` source (ongoing) — declarative parser DSL nested in attributes
- InnerBlocks composition — nested IR, serialization, template locking
- Interactivity API + bindings + core-data — declarative reactive runtime
  layer where WordPress stops being a CMS and becomes more like a
  state-graph platform

**KB ontology trajectory (observed 2026-05-09 after substrate phase):**
The KB is naturally moving in this layer order:
- `block.json schema` → `parser semantics` → `rendering semantics` → `composition semantics` → `capability execution semantics` → `container/layout semantics`

Effectively bottom-up reconstruction of Gutenberg architecture. After
markup-representation + deprecation chunks, blocks now appear in the KB
as "**serialized executable semantic units**", not as CMS handbook entries.

**Capability system 3-layer ontology** (surfaced through
typography spike, 2026-05-09):

Gutenberg's capability system is NOT a single integrated feature but a
**3-layer architecture** that the supports flags only partially expose:

| Layer | Mechanism | Purpose |
|---|---|---|
| **Exposure** | `block.json` `supports.*` flags | Block declares which UI controls / attribute injections to opt into for THIS block type |
| **Configuration** | `theme.json` `settings.*` | Theme declares the token vocabulary, default values, gating switches that apply globally |
| **Execution** | Style engine + render pipeline | At runtime, supports + settings combine to produce CSS classes, inline styles, fluid clamp() formulas, preset resolution |

Critical implication: a single capability (e.g., typography) may be
**asymmetrically split** across the layers. supports.typography exposes
only fontSize / lineHeight / textAlign as block-level controls, but the
broader typography vocabulary (fontFamily, fontWeight, fluid, etc.)
lives in theme.json settings.typography. Future capability chunks must
explicitly delineate which layer each concern lives in.

**Phase boundary observed (entering supports.spacing):**
- **Content semantics** (color, typography) — affects rendered text/visual properties of the block's own content
- **Container semantics** (spacing, dimensions, layout) — affects the block as a positioned element in a parent flow, including parent-controlled inter-child spacing (blockGap)

The boundary is NOT a "next step" — it's an ontological shift. Content-
semantic supports flags rarely pull in layout / flow / composition
references. Container-semantic flags will couple to `block.inner-blocks`,
layout primitives (flex / grid), and document flow semantics.

**Supports family — 3-family classification (refined 2026-05-09 with shadow spike):**

| Family | Capability examples | Core semantic |
|---|---|---|
| **Content** | color, typography | Content appearance — modifies how the block's own text/inline content renders |
| **Container** | spacing, dimensions | Flow / sizing — modifies how the block sits in parent flow, may control children flow (blockGap) |
| **Surface treatment** | shadow, background, filter | Rendered surface effects — visual treatments applied to the block's outer wrapper, mostly preset-driven |

**5-layer authority/capability model (refined 2026-05-09 with shadow + appearanceTools):**

| Layer | Authority | Mechanism |
|---|---|---|
| **Exposure** | block author | `supports.*` (block.json) — "Can THIS BLOCK expose this capability?" |
| **Configuration** | theme | `theme.json settings.*` — "Can the THEME configure this capability space?" |
| **Execution** | runtime | style engine + render pipeline — "How do values emit into runtime CSS?" |
| **Composition** | runtime | layout / InnerBlocks — "How do blocks compose nested?" |
| **Governance** | theme aggregation | `appearanceTools` / theme mediation — "Which capability bundles are exposed under one umbrella?" |

The `theme.json styles.*` cascade is part of Execution authority (resolution
of values to CSS), not a separate layer.

**Capability governance topology** (4 patterns observed across capabilities):

| Governance type | Capability examples | Pattern |
|---|---|---|
| **Fully block-owned** | shadow | `supports` exposure + own theme.json settings, NOT in appearanceTools bundle |
| **Theme-mediated** | border | NO `supports` exposure layer, only theme.json settings, IN appearanceTools |
| **Hybrid** | spacing, typography | `supports` exposure + theme.json settings + partial appearanceTools coverage (lineHeight, blockGap/margin/padding) |
| **Runtime-coupled** | layout | `supports` declaration + composition runtime + style engine emission, "subsystem-tier" |

**Perceptual vs ontology complexity inversion** (shadow vs spacing):
- shadow: high perceptual complexity, **low ontology complexity** (boolean only)
- spacing: low perceptual complexity, **high ontology complexity** (3 props with ownership split + layout coupling)

The surface-treatment family tends toward this inversion; container family
tends toward direct correlation.

**Critical insight from border discovery (2026-05-09):**

`border` has **NO supports exposure layer** — only theme.json settings +
styles. This is NOT a documentation gap; it's a deliberate design
choice by Gutenberg. Border is judged as **theme authority territory**,
not block-author. Block authors cannot opt their block into border
controls per-block; the theme decides globally via
`appearanceTools` aggregation.

This means **Gutenberg does NOT uniformly model capabilities**. Each
capability has its own authority distribution per the 4-pattern
governance topology above. The KB has shifted from documenting
"block capabilities" to mapping an **authority distribution system** —
or more precisely, an **authority-aware semantic runtime map**.

**Anticipated additional layer (from layout spike, when it happens):**
Document flow authority — how blocks participate in / control parent
flow contexts (block layout, alignments, intrinsic sizing). May not
fit cleanly in the 5-layer model.

**Capability family extended to 6 (post-position spike, 2026-05-09):**
- Content (color, typography)
- Container (spacing, dimensions)
- Surface treatment (shadow, background)
- **Visual transformation** (filter) — render-stage transformation pipeline
- **Composition** (layout, position) — governance-dominated, runtime-coupled
- **Governance** (anticipated batch: align, anchor, ariaLabel, className,
  customClassName, contentRole, html, inserter, listView, lock, multiple,
  renaming, reusable, splitting, visibility) — execution surface = editor

**Critical ontology shift introduced by Governance family:**

Prior families all had this pattern: `token/state → wrapper/style emission`.
Governance family is the first to introduce **"Capabilities without
rendering"**:
- editor-only effect for many flags (inserter, lock, multiple, visibility, etc.)
- no front-end emission for editor-governance subgroup
- serialization minimal (boolean state only)
- runtime surface lives in the EDITOR, not in the rendered HTML

This means the supports family is no longer purely about
"what the block can render" but also about
"what the block author / editor user can DO with the block".
Authority distribution model gets a new authority dimension:
**editor-affordance authority** (separate from styling authority).

**Anticipated subdivision within Governance family** (based on rendering-impact):
- **Render-affecting governance**: align, alignWide, anchor, className,
  customClassName, contentRole, splitting (these affect the rendered
  markup or wrapper class)
- **Editor-only governance**: inserter, lock, multiple, visibility,
  listView, renaming, reusable, html (these only affect editor UI)

If single chunk reveals this internal split is too sharp, natural split:
- `governance-editor-affordances.md`
- `governance-render-affordances.md`

**Capability sub-pattern: "Visual transformation pipeline" (anticipated
via filter):**
The standard supports cascade is `token/state → wrapper/style emission`.
But `filter.duotone` introduces a different sub-pattern:
`media/render transformation` — operating on the rendered surface
itself, not just emitting CSS. Likely involves CSS variable + SVG
filter id + render markup. Surface-treatment family but "execution-
heavy" — the runtime authority is materially more complex than the
exposure layer suggests.

**Ontology repair node pattern (filter case):**
The migration `color.__experimentalDuotone → filter.duotone` is NOT
a rename. It is Gutenberg explicitly reclassifying: "this wasn't
color ontology". A KB chunk for filter therefore carries triple
duty: capability description + migration history + ontology
correction. This pattern likely recurs for other API redesigns;
treat as a recognized chunk archetype.

**Editor governance batch abstraction (anticipated for minor flags):**
Many remaining supports flags (align, anchor, html, inserter,
reusable, visibility, listView, multiple, lock, renaming) are
**editor-governance toggles** — uniform pattern of boolean controls
that scope editor / inserter / lock behavior, not styling. These
should batch as a single "editor-governance family" abstraction
chunk OR as a tightly-scoped multi-flag chunk, NOT as individual
spike chunks. Different from capability family — these are NOT
about exposing styling capabilities to users; they're about
constraining editor behavior.

**Bounded context jump signal (interactivity):**
The Interactivity API will likely be a **bounded context jump** from
block-authoring to client-runtime ontology. Not just a new family
within block-authoring. Plan a separate phase: settle supports
closure, then transforms/variations as final block-authoring
chunks, then enter interactivity as new bounded context.

**Transforms ontology — semantic conversion layer (anticipated 2026-05-09):**

`block.transforms` is NOT just a "block conversion API". It is the
**semantic translation runtime that pairs with deprecation as
compatibility runtime** — together they form the conversion authority
matrix:

| Layer | deprecation | transforms |
|---|---|---|
| Identity | preserved (same block type) | changed (block type → block type) |
| Goal | backward compatibility | semantic conversion |
| Trigger | invalid/current mismatch (automatic) | user/editor action (manual) |
| Direction | historical evolution (linear) | lateral reinterpretation (graph) |
| Runtime | parser validation path | editor conversion path |
| Canonicality | preserve existing block | create new block |
| Relation | migration | translation |
| Authority | parser/runtime | editor/user |

Transforms forces new ontology questions never raised before in the KB:
- "Same meaning?" — when is paragraph→heading semantically equivalent?
- "Compatible meaning?" — when is partial conversion acceptable?
- "Lossy conversion?" — table→paragraph drops structure; how is this
  handled?
- Block identity becomes **relativized** — types are not absolute but
  convertible nodes in a semantic graph
- `innerBlocks` topology rewrite — group→columns reshapes the tree

This is the chunk that makes Gutenberg ontologically a
**"mutable semantic graph editor"**, not just an AST manipulator.

**Transforms-specific split triggers (predicted, A' approach):**
- transform type taxonomy ≥ 5 distinct types → ontology divergence
- algorithmic flow section > 40 lines → deprecation-tier runtime
- innerBlocks handling dominates → topology engine should split
- parsing-time transforms mixed with editor-time transforms →
  runtime domain split
- bidirectional semantics inconsistent across types → graph
  semantics needs its own chunk

Approach: A' (single chunk + post-write audit, layout-style).

**Identity ontology triangle (closing 2026-05-09 with variations):**

After deprecation + transforms + variations, Gutenberg's block identity
ontology has 3 complementary axes:

| Axis | Identity relation | Mechanism |
|---|---|---|
| **deprecation** | same identity through TIME | parser-time compat for evolved schemas |
| **transforms** | different identities through SEMANTIC ADJACENCY | editor-time conversion between types |
| **variations** | same identity through ROLE | inserter-time projection of one block into multiple semantic roles |

Together these close the Gutenberg block identity model. After
variations is written, **block-authoring ontology is essentially
closed**. Remaining work moves to bounded contexts:
- theme-config (theme.json deeper)
- editor-runtime (data stores, slot fills, editor APIs)
- interactivity (client runtime — new authority axis)
- style-engine (CSS emission deep dive)
- data-layer (REST, core-data)

**Variations = "identity projection system":**
Same implementation, different semantic role. The canonical example:
`core/embed` is a single block, but `YouTube embed`, `Twitter embed`,
`Spotify embed` are variations representing distinct semantic roles
within the same implementation identity. Variations are presets at
the inserter / discovery layer, not new types.

**Variation vs style distinction (predicted):**
- styles = visual projection (same block, different appearance)
- variations = semantic projection (same block, different role)

**Variations chunk archetype prediction:**
Substrate-flat (like block.json-context, NOT capability H3 like color).
Character: declarative registry + semantic metadata + editor discovery
+ identity projection. Lower density expected than transforms.

**Anticipated frontend runtime authority (interactivity phase, when
it happens):**
Current authority types in KB: editor, parser, theme, block-author,
appearanceTools/governance. Interactivity adds **client runtime
authority** — actions running in the browser after page load,
state machines on the rendered DOM. New ontology axis.

**theme-config phase entry (next phase 2026-05-09):**

theme-config is NOT just "the next document area". It is the
**"global authority substrate"** — half of block-authoring's chunks
already implicitly depend on it via cross-references:
- color → settings.color.* presets
- typography → fluid + presets + font scale
- spacing → spacing scale + blockGap settings
- dimensions → sizing controls gates
- background → appearanceTools mediation
- filter → duotone presets (legacy color.duotone namespace)
- layout → layout defaults + constrained type widths
- shadow → shadow presets
- governance → alignWide coupling
- wrapper-attributes → style engine emission
- dynamic-rendering → runtime resolution
- markup-representation → serialized artifact vs runtime styling

The 3-layer capability ontology (exposure/configuration/execution)
established earlier becomes operational here:
- block-authoring = mostly exposure layer
- **theme-config = configuration authority layer**
- (style-engine future phase = execution layer)

**Larger 2-level ontology emerging:**
- `supports.*` = LOCAL exposure declarations (per-block opt-in)
- `theme.json` = GLOBAL capability governance (theme-wide
  configuration)

**4-layer architecture explicitly named (refined 2026-05-09 entering theme-config):**

| Layer | Authority | Mechanism | Role |
|---|---|---|---|
| `supports.*` | block author | block.json | local affordance declaration |
| `appearanceTools` | theme author | theme.json (governance switch) | theme-global governance bundle |
| `settings.*` | theme author | theme.json (configuration) | authority substrate (presets + gates) — **design token registries** |
| `styles.*` | theme author | theme.json (resolution) | value realization layer |

**KB framing: "Theme JSON = distributed design token operating system"**
(emerging 2026-05-09 with settings.color):

theme.json is no longer just configuration. It functions as:
- **palette / gradient / duotone / shadow / fontSize registries** (settings)
- **default-preset policy** (defaultPalette, defaultGradients, etc.)
- **custom-value governance** (custom, customGradient, etc.) =
  user creative authority gates
- **namespace stability anchors** (preset namespaces are NOT purely
  semantic — they are historically stable authority anchors,
  preserved across capability reclassifications like
  filter.duotone keeping settings.color.duotone)
- **CSS custom property generation source** (--wp--preset--{capability}--{slug})
- **editor UI population source** (preset pickers populate from these)

**Vertical pipeline (revealed by color settings):**
```
settings.color.palette
  → preset registry creation
  → CSS custom property emission (--wp--preset--color--{slug})
  → editor UI population (color picker)
  → supports.color exposure (block opt-in)
  → style.color serialization (per-instance attribute)
  → wrapper emission (has-{slug}-color class OR inline style)
```

This pipeline retroactively organizes everything block-authoring
chunks documented as scattered "execution artifacts" into one
authority-driven flow.

**Critical invariant emerging: namespace stability ≠ semantic truth.**
Source: filter.duotone (capability reclassified) but
settings.color.duotone (preset namespace) preserved. Implication:
theme.json namespaces are **ecosystem stability anchors**, not
purely semantic categories. Future migrations should expect this
asymmetry as a deliberate design choice.

**Settings layer internal bifurcation (anticipated 2026-05-09 with typography fluid):**

The "settings = registry + governance, NOT realization" invariant
established in `settings.color` may have its FIRST counterexample in
`settings.typography.fluid`. Categories within settings:

| Category | Example | Pattern |
|---|---|---|
| **Static token registry** | settings.color.palette | Declarative — list of slug+value entries |
| **Governance gate** | settings.color.custom, defaultPalette | Boolean toggle for user authority / core defaults |
| **Computational token policy** | **settings.typography.fluid** | Algorithm parameters: min/max viewport, min font size, clamp interpolation rules |
| **Asset declaration registry** | settings.typography.fontFamilies | Token + asset references (fontFace[], src, weight/style) |
| **Realization leakage** | fluid's clamp() generation | Settings field that PARTIALLY specifies how realization computes |

**Computational token substrate** = settings.typography (vs
settings.color = pure design token authority substrate).

**Implication:** "theme.json is declarative configuration" is too
narrow. With fluid, theme.json becomes **declarative computation
specification** — a hybrid where some fields declare values,
others declare algorithms / policies for synthesizing values at
render time. The 4-layer architecture's "settings = authority
substrate" framing must accommodate this.

**KB framing extension:** Theme JSON is not just a "design token
operating system". For typography (and likely future capabilities
like animations, scroll-driven effects), it is also a
**responsive rendering algorithm specification language**.

**Settings layer 4-subtype taxonomy (refined 2026-05-09 with spacing prep):**

| Subtype | Representative | Character | Realization leakage |
|---|---|---|---|
| **Registry substrate** | settings.color | Static token declaration (palette / gradients / duotone) | None — pure declarative |
| **Computational substrate** | settings.typography | Runtime interpolation policy (fluid → clamp synthesis) | Partial — algorithm parameters, viewport-driven continuous interpolation |
| **Composition substrate** | settings.layout | Cross-capability authority (wideSize double-duty for alignment + fluid typography) | Indirect — values consumed by other capabilities |
| **Generative substrate** | settings.spacing | Algorithmic preset generation (spacingScale → discrete multiplicative progression) | Strong — theme.json carries token synthesis algorithm policy |

**Computational pattern divergence within settings layer:**
- **typography.fluid** = `f(viewport) → clamp()` — continuous interpolation
- **spacingScale** = `base × ratio^n` — discrete multiplicative progression

These are NOT the same kind of computation. Settings supports MULTIPLE
computational paradigms, not a single "fluid pattern".

**Complete escalation ladder anticipated (next chunk closes it):**
```
color       → registry
typography  → computational registry (continuous policy)
layout      → cross-capability authority
spacing     → generative registry (discrete algorithm)
styles      → actual realization layer (NEXT phase)
```

After spacing, `theme-config/settings/` chapter has covered the
4 main subtype representatives. Remaining settings.* fields (border,
dimensions, shadow, background, position, lightbox, custom,
useRootPaddingAwareAlignments) are likely simpler instances of these
4 subtypes — likely batchable.

**spacingScale as "mini token generator DSL":**
spacingScale's `operator + increment + steps + mediumStep` parameters
together specify an algorithm that GENERATES preset values, not just
declares them. Settings becomes **procedural synthesis policy**, not
just configuration. This is the strongest "realization leakage" case
in settings before reaching the styles realization layer.

**`units` field — governance over dimensional language:**
The settings.spacing.units field (likely `["px", "rem", "vw"]`-style
array) is underrated. It governs **what dimensional vocabulary users
are allowed to think in** — deeper governance than customColor/
customFontSize (which gate WHETHER user can input custom values).
units gates HOW custom values may be expressed.

**Phase 5 entry: styles.* layer — realization substrate (2026-05-09):**

Major axis shift in KB. Previous phases (supports / appearanceTools /
settings) all operated in the **declarative authority** axis:
*"what is allowed"*. The styles.* phase operates in the
**realization** axis: *"how values materialize as CSS / wrapper /
runtime output"*.

| Layer | Core question |
|---|---|
| supports | What can THIS BLOCK expose? |
| appearanceTools | What does the THEME bundle-open? |
| settings | What token / authority system EXISTS? |
| **styles** | **Which actual values land WHERE?** |

**Settings ↔ styles relationship**:
- settings.color declares `palette: [...]` (registry).
- styles.color declares `text: "var:preset|color|primary"`
  (resolution graph entry).

**3 ontology dimensions newly surfacing in styles phase:**

1. **Resolution graph** — consumer-side ontology. Where settings
   was a registry, styles is a token-CONSUMPTION graph:
   ```
   styles.color.text
     → var:preset|color|primary
     → CSS variable resolution
     → wrapper emission
   ```

2. **Selector authority** — *where does the value land?* New
   concerns: element selectors (`styles.elements.*`), block
   selectors (`styles.blocks.{name}.*`), per-feature selectors
   (block.json `selectors` field), inheritance / cascade order.

3. **Realization itself** (not leakage). settings's "realization
   leakage" was a tension — a declarative layer reaching into
   realization. styles INVERTS this: realization is the layer
   itself, attempting to be declaratively described. Different
   philosophy.

**KB phase evolution timeline (acknowledged 2026-05-09):**

| Phase | Theme | Status |
|---|---|---|
| 1. block registration | procedural identity | ✓ done |
| 2. supports | capability ontology | ✓ done |
| 3. semantic graph | transforms / variations / deprecation | ✓ done |
| 4. theme-config settings | authority substrate | ✓ representative chunks done |
| **5. styles** | **realization substrate** | **← entering now** |
| (anticipated) 6. style-engine | CSS emission rules | future |
| (anticipated) 7. interactivity | client runtime | future bounded context |

**Framing shift after Phase 5 entry:** KB moves from
*"schema knowledge base"* → *"rendering systems knowledge base"*.

**styles.color as first chunk — 3 reasons:**
1. Closes the color vertical slice (palette → CSS var → editor →
   supports → style attribute → wrapper) by adding the styles
   consumer step.
2. Filter duotone namespace stability (legacy color.duotone)
   gets second test: does it persist into styles.color.* too?
3. `var:preset|color|{slug}` reference grammar surfaces as
   first-class ontology — preset reference syntax / resolution
   semantics / fallback behavior.

**Critical ontology inversion at Phase 5: leakage → ownership (2026-05-09):**

settings.typography's `fluid` was characterized as "realization
leakage" — a tension where the declarative settings layer reached
into realization specifics. When the same conceptual material
appears in styles.typography:

| Layer | Same content, different framing |
|---|---|
| settings.typography.fluid | **"realization leakage"** — tension: why is settings holding computation policy? |
| styles.typography.fontSize (preset reference resolving to clamp) | **"realization ownership"** — natural: styles is the realization layer |

This is an **axis inversion**, not a continuation. Same computational
content (clamp synthesis from fluid), but in settings it was
philosophically anomalous; in styles it is philosophically central.

**styles internal subtype taxonomy (refined 2026-05-09 after layout-absence finding):**

| Subtype | Representative | Pattern |
|---|---|---|
| Token consumption | styles.color | preset → CSS variable → property |
| Computational realization | **styles.typography** | preset → clamp / responsive computed value → property |
| Generated-token realization | **styles.spacing** | spacingScale-generated preset → property + blockGap variable propagation |
| ~~Composition realization~~ | **NOT in styles schema** | layout realization escapes to: supports.layout + wrapper attrs + style-engine + wp-container-* runtime selectors + layout engine |

**CRITICAL CORRECTION (2026-05-09): "styles = value-bearing realization
SUBSET", not "realization layer 전체"**

User originally predicted styles 4-subtype taxonomy with layout as 4th
subtype. Source verification revealed `styles.layout` does NOT exist
in theme.json schema — settings has layout, styles does not.

User's ontology intuition was CORRECT (layout IS distinct from
color/typography/spacing in realization character) — but the schema
boundary prediction was wrong. Layout realization happens OUTSIDE
the theme.json styles schema.

**Two realization paths now distinguished:**

| Realization path | Mechanism | Schema home |
|---|---|---|
| **Value realization** | theme.json `styles.*` → CSS vars / declarations → emitted CSS | theme.json schema |
| **Composition realization** | `supports.layout` → wrapper attrs → runtime layout classes → wp-container-* → style engine → layout engine | distributed across runtime layers |

This is structural: styles schema is INTENTIONALLY CONSTRAINED to
property-value declarations. Composition / topology / orchestration
cannot be fully captured declaratively in JSON, so it escapes to
runtime systems.

**styles schema entries (10) ≠ settings schema entries (12):**
- Only in settings: useRootPaddingAwareAlignments, appearanceTools,
  layout, lightbox, position, custom (6 entries)
- Only in styles: css, filter, outline (3 entries)
- Common: background, border, color, dimensions, shadow, spacing,
  typography (7 entries)

Settings ↔ styles is NOT 1:1 mapping or asymmetric mirror — it is
**structural divergence with shared core**. Each layer has fields
the other doesn't, reflecting different concerns.

**blockGap framing strengthened (now bridge across realization paths):**

blockGap is the only documented capability spanning BOTH realization
paths:
- Value realization: `styles.spacing.blockGap` → CSS variable
- Composition realization: layout engine consumes the CSS variable as
  flex/grid `gap` property at runtime

This makes blockGap the **first cross-realization-path bridge
capability** in the KB.

**styles.css = "architecture confession" / "architectural escape hatch":**

The existence of `styles.css` (custom CSS escape hatch — present in
styles, NOT in settings) reads as Gutenberg explicitly acknowledging:
- theme.json schema is INTENTIONALLY INCOMPLETE
- "schema completeness is NOT a design goal"
- composition / runtime / topology cannot be fully declaratively
  captured
- escape hatch needed for capabilities the schema doesn't model

**Intent inference from existence:** if Gutenberg targeted full
declarative coverage, pseudo-selectors / combinators / media queries
/ complex conditions / layout-runtime selectors would all be schema-
modeled. The decision to instead provide a CSS escape hatch
documents a deliberate trade-off:
- 80-90% common semantics → schema
- Edge complexity → CSS escape hatch
This makes styles.css ontologically significant beyond its size.

**Grammar contrast within styles:**

| styles section | Grammar |
|---|---|
| color, typography, filter | semantic constrained grammar (string \| {ref}) |
| spacing | structured realization grammar (string\|{ref} + per-side objects) |
| **css** | **UNRESTRICTED CSS grammar** — only formal-schema escape in styles |

styles.css is the ONLY styles entry that breaks the
constrained-grammar pattern. Within the realization layer it is the
"manual override bridge" between schema KB and rendering systems KB.

**Anticipated finding: "scoped escape hatch":**

If style engine auto-scopes the css string contents to the field's
JSON path location (top-level → body selector; per-block →
block-scoped; per-element → element-scoped), then styles.css is NOT
"unrestricted CSS" but a **semi-governed imperative styling surface**
— the schema still asserts WHERE the CSS lands, just relinquishes
WHAT can be expressed. This distinction matters for the escape-hatch
ontology.

**Schema philosophy now articulable as:**
- Phase 1-4 (block-authoring + appearanceTools + settings): schema /
  authority / capability ontology
- Phase 5 (styles realization): rendering realization ontology
- **`styles.css`: explicit schema-system self-limit declaration**

It is the chunk that, retroactively, organizes earlier-discovered
patterns (realization leakage, namespace asymmetry, cross-capability
coupling, runtime-generated selectors, style-engine hidden authority)
under one mechanism: **the schema's "exception handler"**.

**KB-level identity emerging:**
KB is no longer "WordPress block editor docs" or "theme.json
reference". It now operates at:
- authority topology
- semantic identity  
- token systems
- computational realization
- runtime orchestration
- schema boundaries
- **escape-hatch theory** (NEW)

The "styles is NOT the rendering layer; it is only the
value-realization layer" distinction will be the analytical anchor
for future chunks on style-engine, wrapper runtime, container
orchestration, generated selectors, wp-container system.

**Copy vs Reference authority — KB-wide recurring axis (2026-05-09):**

User-surfaced realization: Gutenberg repeatedly distinguishes between
**copy semantics** (inserted/materialized as independent state) and
**reference semantics** (live-linked, propagation-aware) across many
mechanisms. This is one of the longest-lived ontology axes in the KB:

| System | Copy form | Reference form |
|---|---|---|
| patterns | **patterns** (inserted blocks become independent) | synced patterns / reusable blocks |
| template parts | static export | **template parts** (live-linked across uses) |
| transforms | new identity (different block type) | (no reference form) |
| variations | projected preset (same block, fresh state) | (no reference form) |
| deprecations | migrated copy (per-block schema upgrade) | preserved identity (same content, different schema) |

`patterns` and `templateParts` together formalize the
**copy ↔ reference distinction at the structural composition layer**.

**KB framing extension: "Schema surface ≠ actual system boundary":**

theme.json acts as a **federated authority graph projection** — its
fields point into authorities that live elsewhere:

| theme.json field | Schema surface | Actual system boundary |
|---|---|---|
| `styles.layout` (absent) | n/a | style-engine + runtime |
| `styles.css` | escape hatch field | author-managed CSS |
| `settings.color.duotone` | preset registry | legacy namespace anchor (filter exposure migrated) |
| `patterns` | slug array | Pattern Directory + PHP registry + filesystem |
| `templateParts` | metadata array | filesystem + block-template-parts loader + live propagation runtime |
| `customTemplates` | metadata array | filesystem + template hierarchy resolution |

theme.json is NOT a self-contained system — it is one
projection of a federated authority graph spanning filesystem +
PHP runtime + remote services + style engine.

**Anticipated overarching framing — "Gutenberg = semantic role
projection engine":**

Gutenberg repeatedly reuses the same SEMANTIC ROLE concept (header,
footer, content, link, button, heading) across multiple
projection layers:
- block.variations (block.json) — variation `category` /
  `keywords` for inserter discovery
- supports.color.{link, heading, button, caption} — element-scoped
  color realization
- styles.elements.{link, heading, button, ...} — element-scoped
  realization
- templateParts.area = "header" / "footer" — structural area
  semantic role
- block-variations for `header` / `footer` template parts
  (per source note)

This recurring "semantic role" pattern across multiple layers may
unify under a "**semantic role projection engine**" framing in
future chunks. Watch for additional confirming cases (especially in
editor-customization, interactivity).

**System-wide invariant: declaration ≠ exposure (2026-05-09):**

Gutenberg consistently separates "what is DECLARED to exist" from
"what is EXPOSED for user selection / use". This is one of the
KB's strongest cross-system patterns:

| Mechanism | Declaration | Exposure gate |
|---|---|---|
| capabilities (supports.*) | block.json supports declares | appearanceTools / settings gates expose |
| transforms | transform declared in transforms array | isMatch function gates availability |
| variations | variation registered | scope arrays + isActive gate visibility |
| presets | preset exists in registry | custom/default gates control selection |
| **templates (customTemplates)** | **template HTML exists in /templates/** | **postTypes array gates which post types may select it** |

The pattern is system-wide. Each authority layer has BOTH a
declaration mechanism AND an independent exposure mechanism. They
can drift independently — declaring a thing does NOT make it usable;
gating something does NOT erase it from existence. This duality
recurs at every scale (block / capability / template / preset /
transform / variation).

**KB at "authority-distribution cartography" level (2026-05-09):**

Documented ontology axes now include:
- copy vs reference (patterns vs templateParts; transforms vs deprecation)
- **declaration vs exposure** (NEW — system-wide invariant)
- registry vs realization (settings vs styles)
- governance vs capability (appearanceTools vs supports.*)
- semantic role projection (header/footer/button/link/heading recurrences)
- filesystem vs DB live state (templateParts authority layers)
- schema vs runtime (styles.layout absence; styles.css escape)
- value vs topology (settings/styles vs structural composition)
- document archetype vs composition unit (customTemplates vs patterns/templateParts)

KB has moved past "block editor docs" — operates as
**Gutenberg authority topology map**. Future chunks should add
NEW axes only if source material genuinely surfaces a new
distinction; otherwise reinforce / cross-validate existing axes.

**customTemplates as bridge to frontend routing (anticipated):**

customTemplates is the first KB chunk that touches WordPress's
template hierarchy / frontend routing system. Until now KB has
operated mostly in editor ontology (block authoring) + theme
ontology (settings/styles). customTemplates → template hierarchy
resolution → frontend routing brings in WordPress's CMS framing.
This may be a bridge to style-engine + interactivity phases.

**Phase 6 entry: style-engine bounded context (2026-05-09):**

After 46 chunks, KB has accumulated multiple **unresolved threads**
that all point to a single hidden execution layer:

| Discovered anomaly | Actual ownership |
|---|---|
| `styles.layout` absence | style-engine / runtime topology synthesis |
| `wp-container-*` selectors | generated runtime topology |
| `blockGap` full-stack capability | CSS variable synthesis |
| fluid typography `clamp()` output | computed realization |
| `styles.css` escape hatch | schema boundary confession |
| `useRootPaddingAwareAlignments` | wrapper/layout runtime |
| `alignwide`/`alignfull` classes | layout runtime emission |
| per-block selectors | style engine targeting |
| preset → CSS variables | token compiler |

These threads converge on the **style engine** — the runtime
authority concentrator that materializes schema declarations into
actual CSS / DOM output. KB ontology levels:

```
docs indexing
    ↓
schema ontology              (Phases 1-4: block-authoring, settings)
    ↓
authority mapping            (Phase 5: theme-config + styles)
    ↓
runtime cartography          (Phase 6: style-engine — entering)
    ↓
runtime semantics            (Phases 7+: interactivity, bindings)
```

**style-engine = "runtime authority concentrator":**

The pattern that recurs throughout KB is **schema declares; runtime
resolves**. Style-engine is the resolver layer for all
schema-declared visual / structural authority:

| Schema declaration | Runtime resolver |
|---|---|
| settings.color.palette | CSS variable emission |
| spacingScale | preset synthesis |
| typography.fluid | clamp() formula generation |
| blockGap | container gap variable propagation |
| styles.blocks.* / styles.elements.* | selector compilation + scoping |
| templateParts | runtime composition (wp:template-part block render) |

**Anticipated ontology inversion in Phase 6:**

Phases 1-5 were **capability-centric**: "what feature exists?".
Phase 6 will be **selector/topology-centric**: "where and how does
CSS authority ATTACH to the rendered DOM?". The shift is from
declarative grammar to runtime targeting.

**First style-engine chunk recommendation: `selectors` (block.json
field).**

`selectors` is structurally a block.json minor field but
ontologically a **style-engine attachment override surface**.
Reading it as a "block.json minor field" misses the depth; reading
it as the "style-engine targeting override" exposes its real role.
This makes it the natural bridge from block-authoring closure into
style-engine bounded context.

Folder placement decision: place in `block-authoring/block-json/`
(where the field structurally lives — consistent with other
block.json fields) but with explicit style-engine framing in body.
Future style-engine chunks (engine internals: generated selectors,
CSS variable compilation, preset materialization, cascade
resolution) live in `style-engine/` bounded context.

**Style-engine bounded context = NEW folder (added 2026-05-09):**

The original 10 bounded contexts didn't include style-engine —
its existence is an ontological discovery from KB authoring.
Add to `./knowledge/wordpress/style-engine/`. Future chunks:
- ~~generated selectors (wp-container-*, wp-elements-*)~~ ✓ DONE 2026-05-09
- CSS variable emission system
- preset compilation pipeline
- cascade / specificity mediation
- block / element / global style aggregation
- runtime style-injection mechanisms

**generated-selectors chunk written — Phase 6 anchor established (2026-05-09):**

First true runtime-native chunk completed. Key ontology framings
solidified through this chunk:

**1. Pipeline matrix (style-engine backbone):**
```
declaration → synthesis → realization → carrier
selectors    generated     emitted CSS    DOM wrappers
(block.json) (.wp-*-N)     (<style> tag)  (wrapper element)
```
This 4-stage pipeline is the style-engine bounded context backbone.
All subsequent style-engine chunks attach to specific stages.

**2. Two projection modes of a single synthesis engine:**
`.wp-container-*` and `.wp-elements-*` are NOT two features —
they are two projection modes of the same runtime attachment-
topology synthesizer:
- `.wp-container-*` = relational topology synthesis (layout-driven)
- `.wp-elements-*` = semantic projection synthesis (element-role-driven)

Both share: schema-authored input → runtime synthesis → per-instance
scoping → selector graph → companion CSS emission. Treating them
as the same mechanism is load-bearing.

**3. Three new invariants formalized:**
- **Selector identity is ephemeral infrastructure** — generated
  classes are NOT public API; plugins/themes hardcoding them break.
  Distinction: `slug ≠ identity`, `selector ≠ public API`.
- **Runtime synthesis replaces static selector predictability** —
  historic CSS architecture shift: pre-FSE = predictable global
  classes (author-controlled); FSE = scoped runtime-synthesized
  selectors (engine-controlled). Selectors block.json field (WP 6.3)
  arrived because the engine absorbed selector authority.
- **Style engine owns cascade topology (partially)** — the cascade
  is partially runtime-owned. Authors retain styles.* / styles.css /
  per-block CSS; engine owns per-instance generated selectors and
  their cascade ordering. styles.css and selectors.css escape hatches
  exist BECAUSE schema cannot fully describe a cascade the engine
  partially synthesizes at runtime. Escape hatches are structural
  complement to runtime cascade ownership.

**4. Meta-shift: Gutenberg = runtime style-graph compiler (not block
schema system):**

Phases 1-5 documented compiler INPUT surfaces (supports / settings /
styles / theme.json top-level). Phase 6 documents compiler INTERNALS
(selector synthesis, CSS variable emission, preset materialization,
cascade aggregation, wrapper topology).

**5. Three-source authority hierarchy for style-engine ontology:**

| source | authority |
|---|---|
| handbook | declarative intent, schema documentation |
| @wordpress/style-engine package source | actual ontology, synthesis logic |
| rendered runtime output | empirical verification |

Style-engine ontology is "a runtime-derived surface whose behavior
is only partially expressible through schema documentation."
Verification-needed appears more often in this bounded context
than in declarative-surface chunks. This is structural to the
ontology character, not a documentation gap.

**6. entity-centric → relationship-centric ontology pivot:**

Prior KB phases were almost entirely entity-centric ("what exists?":
blocks, variations, presets, templateParts). Style-engine introduces
relationship-centric ontology ("what topology is computed at
runtime?"): sibling spacing, nested gap propagation, parent layout
flow, constrained-width inheritance, scoped selector graphs. This
pivot is the load-bearing axis of the style-engine bounded context.

**Anticipated next style-engine chunks (priority order):**
1. ~~CSS variable emission system~~ ✓ DONE 2026-05-09 (style-engine.css-variable-emission)
2. ~~Preset materialization — registry → declaration → applied value
   transformation stages~~ ✓ DONE 2026-05-09 (style-engine.preset-materialization)
3. Cascade aggregation — final cascade ordering across theme.json
   output, generated selectors, styles.css, theme stylesheet
4. Wrapper topology — useBlockProps + style engine's wrapper class
   contributions (might overlap with already-written
   wrapper-attributes.md; review for re-frame opportunity)

**Style-engine bounded context — pipeline coverage (after preset-materialization, 2026-05-09):**

| stage | chunk | status |
|---|---|---|
| 1. Authority declaration | settings.* (theme-config) | ✓ |
| 2. Preset registration | preset-materialization | ✓ |
| 3. Variable emission | css-variable-emission | ✓ |
| 4. Reference resolution | preset-materialization | ✓ |
| 5. Selector binding | generated-selectors | ✓ |
| 6. Cascade participation | (planned) cascade-aggregation | ⏳ |
| 7. Browser computation | (out of scope — browser owns) | n/a |

Style-engine bounded context backbone is **mostly complete after
3 internal chunks** (generated-selectors, css-variable-emission,
preset-materialization). Only cascade-aggregation remains for
structural completeness; everything else is supplementary.

**preset-materialization key framings (added 2026-05-09):**

1. **Materialization pipeline = 7 stages, not atomic** —
   declaration → registration → emission → resolution → binding →
   cascade → browser computation. Each stage has its own concerns;
   conflating defeats diagnosis.

2. **References are deferred computation edges** — `var:preset|*|*`
   and `var(--wp--preset--*)` carry registry lookup edge + runtime
   resolution edge + cascade participation edge. NOT synonymous
   with literal values; substitution loses all three edges.

3. **References preserve authority mobility** — late-binding
   infrastructure for distributed authority changes (theme palette
   change / user customization / plugin extension / scheme
   switching). All propagate without rewriting consumers.

4. **Materialization preserves late-binding by structural design** —
   at every stage the engine has the option to inline (commit value)
   but chooses NOT to. Load-bearing design principle.

5. **A preset is a stable authority anchor, not a value** — slug
   IS identity, not label. Renaming = breaking change. Treat as
   API surface.

6. **Multiple materialization strategies coexist** — 4 paths:
   theme.json globals (full pipeline) / per-block supports
   (editor + serialization detour) / per-instance literal
   (bypasses preset) / styles.css escape hatch (bypasses entire
   graph). All converge at cascade.

7. **Browser computed style = FINAL realization boundary** —
   Gutenberg compiler is structurally a CSS-cascade-targeting
   compiler producing source for an execution engine it doesn't
   control. Cannot deterministically guarantee final value;
   browser cascade and computed-style derivation are out-of-scope
   for the engine.

8. **Materialization graph crosses bounded contexts** — pipeline
   spans theme-config (decl) + style-engine (registration through
   binding) + browser (final computation). Explains why earlier
   settings/styles chunks hand-waved to "engine resolves this" —
   they referenced stages 2-6 of this graph.

9. **Reference resolution = grammar rewrite, NOT value resolution** —
   stage 4 swaps `var:preset|*|*` for `var(--wp--preset--*)` but
   preserves indirection. Value resolution happens at stage 7
   (browser variable substitution). The two-grammar surface exists
   for portability across emission pathways.

10. **emission ≠ materialization** — variable-emission documents
    stage 3 output; materialization documents stages 1-7 lifecycle.
    Different scope; KB chunks tile the pipeline at different
    zoom levels.

**Phase progression observation (refined 2026-05-09):**

KB has progressed through ontology layers:

```
schema ontology         (block-authoring + settings declarations)
   ↓
realization ontology    (styles + appearanceTools)
   ↓
runtime synthesis ontology    (generated-selectors, css-variable-emission)
   ↓
compiler pipeline ontology    (preset-materialization)
   ↓
(planned) cascade ontology    (cascade-aggregation)
```

Compiler pipeline ontology is the deepest layer of the style-
engine bounded context. cascade-aggregation will be the final
"linker stage" chunk closing the bounded context backbone.

**cascade-aggregation deferred reasoning:**

cascade-aggregation will be near "WordPress CSS compiler linker
stage" complexity. Topics expected: specificity, ordering, inline
vs aggregated, user vs theme vs block precedence, global styles
override layers, style deduplication, SSR generation, per-block
style injection. High verification-needed density. Ship after
materialization momentum stabilizes; consider intermediate batch
chunks (settings remainder, block-authoring closure) before
attempting.

**Settings layer closure (2026-05-09 — settings.* schema fully
documented):**

residual-governance.md batch closes the settings.* schema.
6 fields covered (border / dimensions / lightbox / position /
custom / useRootPaddingAwareAlignments). Framed as
**"residual edge governance"** — bridge-oriented inter-system
adapters, NOT new ontology subtypes.

**Key framings established:**

1. **Residual settings are disproportionately bridge-oriented** —
   core settings (color/typography/layout/spacing) were authority
   substrates; residuals are inter-system adapters. Each bridges
   to: appearanceTools / runtime layout / editor features /
   block-side supports / style-engine variable namespace / wrapper
   synthesis.

2. **Settings can govern runtime behavior without owning
   realization** — useRootPaddingAwareAlignments / lightbox /
   position declare CAPABILITY AVAILABILITY but realization
   happens at runtime / editor / per-instance layers.

3. **3-layer escape pattern complete (settings.custom ↔ styles.css
   ↔ runtime synthesis):**

   | layer | escape hatch |
   |---|---|
   | settings (declaration) | `custom` |
   | styles (realization) | `css` |
   | runtime (synthesis) | generated selectors / aggregation |

   Together they confirm: Gutenberg = "partially-governed compiler
   ecosystem", NOT strict schema system. Three escape hatches at
   three authority layers — load-bearing design choice, not
   redundant overlapping safety valves.

4. **`settings.custom` = third CSS variable namespace:**

   | namespace | source | character |
   |---|---|---|
   | `--wp--preset--*` | settings.{cap}.{registry} | stable registry ABI |
   | `--wp--style--*` | styles.* with cross-rule consumption | synthesized runtime state |
   | `--wp--custom--*` | settings.custom arbitrary tree | author-defined token namespace |

   Custom = escape valve for the variable namespace itself; emits
   variables without preset registration / editor UI consumption.

5. **`useRootPaddingAwareAlignments` = declaration leaking runtime
   topology policy** — single boolean activates variable emission
   (`--wp--style--root--padding-*`) + wrapper class behavior +
   layout engine computation path + container generated selectors
   consumption. Cleanest example of "small declaration with
   disproportionate runtime impact" in settings.

6. **Structured settings mirror runtime topology complexity** —
   border (4 sub-properties) / dimensions (multi-axis + small
   registry) — structure reflects realization shape, not arbitrary.

7. **Boolean / scalar settings semantics are heterogeneous** —
   "boolean settings field" is NOT uniform pattern. Each governs
   different KIND of authority decision (UI exposure / feature
   gate / capability availability / runtime behavior activation /
   token value).

**Settings.* schema = CLOSED after this batch:**
- 4 core subtype representatives (color / typography / layout /
  spacing): substrate authority
- 6 residual fields (this chunk): bridge / governance / escape
  authority

NOT in settings.* (escapes elsewhere):
- Composition / topology authority → layout engine, generated
  selectors
- Cascade ordering → cascade-aggregation
- Editor-affordance authority → supports (block-authoring),
  not settings
- Per-block runtime values → block instance attributes

**Pre-cascade-aggregation positioning effect:**

Now that residual governance is documented, cascade-aggregation
can frame authorities as "previously distributed authorities
converging" rather than "suddenly appearing linker". Each
contributing authority's ownership + escape hatch + governance
character is pre-documented. cascade-aggregation will reference
back to residual-governance for: scoped variable declarations
(custom), runtime layout flags (rootPadding), per-instance
emission paths (position.sticky).

**Recommended sequence going forward:**
1. ✓ settings residual governance batch (DONE 2026-05-09)
2. ✓ cascade-aggregation (DONE 2026-05-09 — style-engine capstone)
3. wrapper-attributes retro patch (carrier layer retrospective
   after style-engine closes)
4. block-authoring residual closure (block.json minor fields:
   styles / example / blockHooks / version + bindings)
5. bindings/interactivity (paradigm jump to client runtime
   bounded context)

**Style-engine bounded context CLOSED (2026-05-09):**

Backbone structurally complete. 4 internal chunks:
- generated-selectors (selector synthesis output)
- css-variable-emission (variable synthesis output)
- preset-materialization (transformation lifecycle)
- cascade-aggregation (capstone — authority arbitration)

**KB-level framing payoff (this is the structural redefinition
the bounded context delivers):**

> Gutenberg is not a "block schema system."
> Gutenberg is a **schema-driven runtime style graph compiler**:
> distributed style compiler + cascade orchestrator delegating
> execution to the browser CSS engine.

Block-authoring + theme-config established the SCHEMA SURFACE.
Style-engine established the COMPILER / ORCHESTRATOR / VM SPLIT.
Together they describe Gutenberg as multi-layer authority-
arbitration system whose final execution target is a browser
cascade engine.

**cascade-aggregation key framings established:**

1. **aggregation = authority arbitration, NOT concatenation** —
   load-bearing backbone framing. Reading aggregation as
   "stylesheet order" misses the entire point.

2. **Multi-origin CSS graph orchestration** — Gutenberg is NOT a
   single-stylesheet system. 9 documented authority sources
   (core defaults / theme stylesheet / theme.json / supports /
   generated selectors / inline / styles.css / settings.custom /
   user customizations / plugin) each with distinct emission
   pathway + scoping rule + origin authority.

3. **Cascade ordering encodes authority hierarchy** — 10-tier
   cascade priority is deliberate authority statement, not
   arbitrary. Each tier transition encodes "X authority overrides
   Y authority."

4. **Specificity = authority-weight encoding system** — major
   ontology inversion. CSS specificity in Gutenberg is the
   **authority negotiation protocol** the engine uses to
   communicate authority relationships to the browser cascade
   engine. NOT a presentation detail.

5. **Inline styles = authority escalation paths** — when a value
   cannot be expressed as a generalizable rule (per-instance
   unique, must override generated selectors / theme defaults /
   user customizations), the engine ESCALATES to inline emission.
   Inline IS the appropriate emission for non-reducible authority.

6. **Generated selectors reduce global specificity pressure** —
   per-instance scoping naturally wins over global rules without
   `!important` escalation. Structural, not optimization. Without
   them every per-instance value would either escalate to inline
   or pollute global selector namespace.

7. **Escape hatches intentionally bypass aggregation layers** —
   3 escape hatches (settings.custom / styles.css /
   styles.blocks.{name}.css) emit at declared cascade position
   but opt out of authority-specific arbitration semantics.
   Confirms Gutenberg = "partially-governed compiler ecosystem".

8. **style-engine compiler ↔ browser cascade VM split:**

   | role | system |
   |---|---|
   | Compiler | style-engine (variable + selector + materialization + aggregation) |
   | Runtime VM | browser CSS cascade engine |
   | Source language | theme.json + supports + styles + attrs |
   | Target language | CSS (with custom property indirection) |
   | Linker | aggregation |
   | Execution | browser computation (out of scope) |

   Explains every style-engine chunk's verification-needed sections —
   many runtime behaviors are in the VM (browser), not compiler
   (style-engine).

9. **Aggregation is partial, not total — final value owned by
   browser** — even after aggregation, browser performs variable
   substitution / inheritance / unit normalization / specificity
   arbitration with browser defaults / !important precedence.
   Compiler/VM boundary is permeable in VM direction.

**Phase progression (style-engine bounded context complete):**

```
schema ontology         (block-authoring + settings declarations)
   ↓
realization ontology    (styles + appearanceTools)
   ↓
runtime synthesis ontology    (generated-selectors, css-variable-emission)
   ↓
compiler pipeline ontology    (preset-materialization)
   ↓
cascade arbitration ontology  (cascade-aggregation) ← STYLE-ENGINE BACKBONE COMPLETE
```

**Anticipated next phase: post-style-engine direction options**

Style-engine backbone closed. Next inflection points:

1. **wrapper-attributes retro patch** — light retroactive work,
   re-frame existing chunk through style-engine ontology. High
   coherence payoff for low effort. User originally deferred this
   to "after cascade-aggregation"; that point is now reached.

2. **block-authoring residual closure** — block.json minor fields
   (styles / example / blockHooks / version) batch + bindings
   (WP 6.5+) as separate spike. Closes block-authoring bounded
   context.

3. **interactivity bounded context** — paradigm jump to client
   runtime authority. NEW authority axis (frontend runtime
   authority, separate from editor / theme / block / instance).
   Major ontology shift.

4. **data-layer bounded context** — REST / core-data / dynamic
   blocks data sourcing. Couples to dynamic-rendering chunk
   already written.

5. **editor-customization / site-building / plugin-dev / i18n /
   build-tooling / admin-ui** — remaining 6 bounded contexts
   from original 10 (now 11 with style-engine added). Order
   to be discussed.

**Recommendation:** wrapper-attributes retro patch first (low
effort, structural coherence), then assess whether block-authoring
residual closure or interactivity entry comes next. interactivity
is the next major ontology pivot but requires preparation; minor
closure work can buy that prep time.

**wrapper-attributes RETROACTIVE REFRAMING completed (2026-05-09):**

First explicit RETROACTIVE REFRAMING section added to KB.
Reframes wrapper-attributes as "authority transport surface" /
"realization carrier multiplex" / "compiler-runtime handshake
membrane" / "ABI boundary" between style-engine compiler and
browser cascade VM.

**KB pattern established: RETROACTIVE REFRAMING:**

When a downstream bounded context closes and reframes the role
of an upstream chunk, the upstream chunk gains a layered
re-reading section — NOT a rewrite. Original content stays
accurate; retroactive section adds layered ontological
reinterpretation visible only after bounded context closure.

**6 retroactive invariants added (A-F):**
- A. Wrapper attributes are authority carriers, not mere markup
  metadata
- B. Wrapper surfaces form the compiler/runtime handshake
  membrane (compiler output surface ↔ browser input surface;
  ABI boundary)
- C. Generated selectors specialize wrapper-scoped authority
  projection (`.wp-container-*` / `.wp-elements-*` as
  wrapper-local synthesized namespaces)
- D. Inline styles are wrapper-bound escalation authorities
  (cascade-aggregation tier 10 physical materialization)
- E. CSS variables traverse the wrapper boundary as deferred
  realization carriers (3 variable-authority transactions per
  wrapper)
- F. Layout topology policies materialize through wrapper
  attachment (useRootPaddingAwareAlignments full chain)

**Realization carrier multiplex (10-tier carrier table):**

Wrapper carries simultaneously: block-type identity / preset
references / runtime topology synthesis / element projection /
layout topology / block-style variations / per-instance escalated
authority / scoped variable declarations / editor instance
identity / persistent anchors. One DOM element, multiple
authority transports.

**Pipeline closure achieved (block-authoring ↔ style-engine
physically sealed):**

```
block schema (block-authoring + theme-config)
   ↓
wrapper transport (wrapper-attributes — carrier role)
   ↓
style engine synthesis (generated-selectors + variable-emission)
   ↓
materialization lifecycle (preset-materialization)
   ↓
cascade arbitration (cascade-aggregation)
   ↓
browser execution (out of scope — VM)
```

Block-authoring and style-engine bounded contexts are now
**physically sealed**. Wrapper-attributes is the load-bearing
membrane connecting them.

**KB-level framing now complete:**

> Gutenberg is a **schema-driven runtime style graph compiler**
> with the WRAPPER ELEMENT as its physical compiler-runtime
> handshake surface.

Block-authoring established the SCHEMA. Style-engine established
the COMPILER. Wrapper-attributes (with retroactive reframing) is
the ABI BOUNDARY between compiler output and browser runtime
input.

**Anticipated retroactive reframing candidates (future):**
Other chunks that may benefit from similar retroactive sections
when their downstream bounded contexts close:
- block.markup-representation — when serialization deep-dive
  bounded context develops (may reframe IR role)
- block.dynamic-rendering — when interactivity bounded context
  closes (may reframe SSR vs CSR role)
- block.inner-blocks — when composition / template bounded
  context develops (may reframe nesting role)
- theme-config.styles.css — when escape hatch ecosystem matures
  (already partially reframed via residual-governance)

Pattern: only apply retroactive reframing when bounded context
closure causes genuine ontological reinterpretation, not mere
addition. The pattern is structural, not perfunctory.

**block-authoring residual closure 1차 — block.json residual
fields batch (2026-05-09):**

3 residual top-level block.json fields covered as
"coordination adapters" batch:
- `styles` — stylistic variation registry (parallel to
  block.variations' semantic projection)
- `example` — preview-oriented synthetic block state (editor
  simulation, NOT authored content NOR runtime output)
- `blockHooks` — declarative composition injection (third
  composition governance axis: constraint / edit / **insertion**)

`version` excluded (already in basic-metadata). `bindings`
deferred to separate spike (authority reattachment paradigm
bridge — too large for batch).

**block.json static schema CLOSED:**

| family | status |
|---|---|
| registration | ✓ |
| basic-metadata | ✓ (apiVersion, name, title, version, etc.) |
| supports.* | ✓ (10 chunks) |
| attributes | ✓ (3 chunks) |
| context | ✓ |
| hierarchy | ✓ (parent/ancestor/allowedBlocks) |
| assets | ✓ |
| selectors | ✓ |
| variations | ✓ |
| transforms | ✓ |
| deprecated | ✓ |
| residual coordination | ✓ (this batch) |

`bindings` is NOT in this closure — it's a separate paradigm
bridge spike (next chunk).

**Key framings established:**

1. **Coordination adapter character** — these 3 fields govern
   editor UX / preview / insertion orchestration, NOT
   capability declaration / style-engine bridge / composition
   constraint. None participate in style-engine compiler
   pipeline.

2. **`styles` = stylistic projection** parallel to
   `block.variations` = semantic projection. Both register
   pre-configured selectable presets at single-block scale,
   different projection axes:
   - variations: same block, different role
   - styles: same block, different visual variant

3. **`example` = synthetic state space** — first block.json
   field creating a state distinct from authored content / runtime
   output. Anticipates ontology categories (placeholder /
   optimistic / hydrated state) likely to appear in
   interactivity bounded context.

4. **`blockHooks` = third composition governance axis:**

   | axis | mechanism | type |
   |---|---|---|
   | constraint | parent/ancestor/allowedBlocks | reactive (gates user actions) |
   | edit | templateLock | restrictive (prevents user actions) |
   | **insertion** | **blockHooks** | **active (declares automatic placement)** |

   Insertion governance is qualitatively different — first
   declarative mechanism by which a block declares its own
   insertion behavior relative to other blocks. Plugin-friendly
   composition extension. Will connect to site-building /
   contextual composition bounded contexts.

5. **block.json schema is closed for static surfaces.**
   Remaining authority surfaces escape elsewhere: bindings →
   data-layer/interactivity, runtime state → block instance
   attributes / DB.

**Anticipated next: bindings spike (block-authoring residual
closure 2차):**

Per user framing — bindings is NOT a minor field. It opens:
- Authority reattachment surface (entity/provider resolution)
- Hydration semantics
- Client/server authority split
- Reactive updates
- Mutable external authority

**Paradigm shift signal: "Authority attachment is becoming
dynamic":**

Until now, KB authority surfaces were mostly compile-time
declaration:
- settings (compile-time registry)
- supports (compile-time exposure)
- styles (compile-time realization declaration)

bindings introduces runtime authority attachment:
- Runtime entity resolution
- Mutable external authority
- Post meta / entity field / query result attachment as
  authority source

KB framing extension anticipated:

> Gutenberg has been a "schema-driven runtime style graph
> compiler" through Phases 1-6. With bindings + interactivity,
> Gutenberg expands to **reactive authority graph runtime** —
> compile-time + runtime authority composition.

This will be the interactivity phase backbone framing.

**bindings → interactivity ontology axes anticipated:**
- Authority origin (compile-time vs runtime)
- Authority lifetime (static vs reactive)
- Authority binding direction (read vs write vs bidirectional)
- Authority subject (block instance vs entity vs query)
- Hydration boundary (server initial vs client mount)

bindings is the entry point; interactivity bounded context will
formalize the full reactive runtime authority ontology.

**block-authoring residual closure 2차 — bindings spike COMPLETED
(2026-05-09):**

`bindings.md` — phase transition chunk for KB ontology. The
structural bridge between Phases 1-6 (compile-time compiler) and
Phases 7+ (runtime authority orchestration).

**Status: `evolving`** (first non-stable chunk in KB) — WP 6.5+,
API still maturing.

**Block-authoring bounded context now SUBSTANTIVELY CLOSED:**

All major top-level block.json fields documented. All authority
axes covered (declaration / capability / composition / attribute
/ context / variation / transformation / deprecation /
**runtime attachment**). Future block-authoring chunks limited
to: deeper revisits, retro patches, new WP-version fields.

**KB-level phase transition formalized:**

```
Phase 1-6 (CLOSED):  schema-driven runtime style graph compiler
                     block-authoring → declaration authority
                     theme-config    → configuration authority
                     style-engine    → realization authority
                                      ↓
Phase 7+ (BEGINS):   reactive authority graph runtime
                     bindings        → runtime authority attachment
                     data-layer      → entity resolution
                     interactivity   → reactive orchestration
```

**KB framing extension:**

> Gutenberg's compiler architecture expands from
> **style realization** (Phases 1-6)
> to **runtime authority orchestration** (Phases 7+).
>
> Two-axis system: visual compilation + state orchestration.

**10 invariants established (key ones):**

1. **Bindings = authority attachments, NOT value declarations**
   (load-bearing) — attribute serialized value is placeholder;
   source is authoritative.

2. **Bindings introduce runtime authority into block-authoring**
   — first runtime authority pathway in block-authoring (all
   prior fields were compile-time).

3. **Attributes become attachment hosts rather than value owners**
   — culmination of KB-wide inversion: settings (registry → reference
   targets) → styles (rules → variable references) → variables
   (literals → computation edges) → selectors (authored → attachment
   declarations) → **bindings (owners → hosts)**.

4. **Bindings separate serialization from authority** — saved
   markup ≠ source of truth. Two state surfaces: serialized
   placeholder + source-resolved authoritative state.

5. **Source providers abstract authority origin** — Gutenberg's
   first authority resolver registry. Plugins can introduce
   new runtime authority sources.

6. **Bindings create reactive edges in block graph** — first
   reactive edge mechanism in block-authoring; runtime graph
   mutation propagation.

7. **Bidirectional bindings escalate synchronization complexity**
   — read-only = projection; writable = optimistic/pessimistic
   strategies, conflict resolution, async semantics. Qualitative
   shift, not incremental.

8. **Bindings blur editor / runtime / persisted boundaries** —
   pre-bindings: 3 separated state surfaces. Post-bindings: all
   3 converge through bindings as projection / persistence /
   consistency channel. Anticipates interactivity full reactive
   runtime.

9. **Bindings are structurally late-bound** — late-binding
   preservation extended to attribute authority. Now documented
   across 3 layers: variables / references / **bindings**.

10. **Bindings = bridge from style graph compiler to reactive
    authority graph runtime** (phase-transition invariant).

**DSL extension applicability extended (2026-05-09):**

VERIFICATION NEEDED + META sections were originally
style-engine-bounded-context-specific. With bindings, the
applicability now extends to:

> Chunks documenting **runtime / implementation-derived
> authority surfaces**, regardless of bounded context.

Future chunks meeting this criterion (interactivity, data-layer
runtime, dynamic-rendering deeper-dive) should use extended DSL.

**Status `evolving` introduced as KB convention:**

First non-stable chunk in KB. Indicates: WP-version-recent API +
implementation details still solidifying + ontology framing
likely to refine in subsequent WP versions. Distinct from
"stable" (well-documented, settled) and from "verification-needed"
frontmatter wp_min (specific facts unverified). `evolving`
applies when the API itself is changing.

**block.json closure complete + KB phase 7 entered:**

Block-authoring bounded context's major surface area is fully
documented. KB has structurally entered Phase 7 (reactive
authority graph runtime). Next major work:

1. **interactivity bounded context entry** — concrete chunks for
   the reactive runtime. Likely first chunk:
   `interactivity.runtime-state` or
   `interactivity.directive-protocol`.

2. **data-layer bounded context entry** — entity resolution,
   REST API, core-data store. Bindings consume entity sources;
   data-layer documents the source resolution mechanisms.

3. Other bounded contexts (editor-customization / site-building /
   plugin-dev / i18n / build-tooling / admin-ui) — order TBD.

Recommended next: assess interactivity vs data-layer ordering
based on user direction. Both are now properly framed by
bindings; either can serve as Phase 7 second chunk.

**Phase 7 second capstone: data-layer.entity-resolution
COMPLETED (2026-05-09):**

First chunk in NEW data-layer bounded context. Documents the
entity authority substrate that bindings depends on.

**Order rationale (data-layer before interactivity):**

bindings opened ontology to entity/store/runtime authority. Going
to interactivity directly would have left "what state is reactive?"
hanging. data-layer first = stable substrate; interactivity then
becomes "reactive orchestration layer on top." Sequence:

```
bindings (attribute attachment surface)
   ↓
data-layer (entity authority substrate) ← NOW
   ↓
interactivity (reactive orchestration grammar) ← NEXT phase
```

**10 invariants established (key ones):**

1. **Entities are runtime authority subjects** — qualitatively
   new authority kind. Settings/styles/supports were authority
   SURFACES; entities are authority SUBJECTS with state independent
   of consumers.

2. **Blocks project entity state rather than owning it** —
   bindings invariant #3 culmination. Block instances = projection
   surface; entities = authority owners; bindings = projection
   mechanism; core-data = projection substrate.

3. **Resolution is reactive, NOT transactional lookup** —
   `getEntityRecord(...)` is a SUBSCRIPTION + auto-trigger fetcher,
   not a property access. Components re-render on store mutation.

4. **Selectors are authority queries** (KB-wide selector symmetry):

   | layer | selector queries |
   |---|---|
   | style-engine | DOM attachment graph (where rules apply) |
   | data-layer | entity store graph (what authority is current) |

   Both are graph-traversal queries against authority attachment
   substrates. Both produce reactive results.

5. **core-data = runtime authority cache** — cache + buffer +
   subscription graph + resolver scheduler, NOT just "Redux store
   with WordPress data."

6. **Persistence is deferred and buffered** — edit semantics
   separate INTENT from PERSISTENCE. Save = explicit flush
   operation. Enables unsaved-changes indicators, atomic save,
   undo over edits, discard without REST round-trip.

7. **Entity edits exist before persistence — draft authority
   layer** — client-local draft sitting between user intent and
   persisted state. Other clients see persisted only; editing
   client sees edit buffer overlaid via getEditedEntityRecord().
   Crucial for collab editing semantics + multi-client conflict
   resolution.

8. **Resolution crosses server/client/persistence boundaries** —
   single useSelect() call traverses 6 boundaries (cache check
   → REST request → WP_REST_Server → DB → response → store
   insertion → notification).

9. **Bindings depend on entity resolution infrastructure** —
   structural phase linkage. Without entity resolution substrate,
   bindings has no substrate to attach to.

10. **Data-layer turns Gutenberg from compiler into stateful
    runtime** — Phase 7 second capstone framing. Two-axis
    system: compiler (visual realization) + stateful runtime
    (authority orchestration).

**Three-layer entity store ontology (per entity, per ID):**

| layer | content | source |
|---|---|---|
| persisted record | last server-confirmed state | REST GET response |
| edited record (buffer) | user edits not yet saved | editEntityRecord() |
| transient/UI state | selection, focus, undo history | editor-only, NOT in entity store |

**6-stage save pipeline:**

```
edit dispatch → edit buffer accumulation → save dispatch
   → REST request → response → persisted layer update +
   buffer clear + selector re-emit
```

Each stage has failure modes. "Save" is NOT a single transaction.

**KB-level framing extension:**

> Gutenberg's compiler architecture (Phases 1-6) handled
> visual realization. Gutenberg's stateful runtime (Phase 7+)
> handles authority orchestration.
>
> data-layer is the **state substrate** of the stateful
> runtime — the authoritative entity graph that all reactive
> consumers ultimately project from.

**Phase 7 substrate now in place:**

```
Phase 7 substrate (BEFORE interactivity entry):
   bindings              → attribute attachment surface
   data-layer.entity-res → entity authority substrate
   ↓
   interactivity         → reactive orchestration grammar
                           (still to come)
```

When interactivity bounded context begins, "what state becomes
reactive?" has a concrete answer:
- entity state from data-layer
- per-block instance state
- interactivity-introduced ephemeral state (UI mode,
  animations, etc.)

**KB framing — data-layer ↔ interactivity relationship:**

| layer | role |
|---|---|
| data-layer | state authority substrate |
| interactivity | reactive coordination/runtime grammar |

**Anticipated next chunks (priority):**

1. **`data-layer.persistence`** — save dispatch deeper dive.
   Currently summarized in entity-resolution. Consider when
   persistence-specific concerns (autosave / conflict handling /
   transactional semantics) need dedicated treatment.

2. **`data-layer.entity-types`** — per-entity-type behaviors.
   post lifecycle (draft/publish/scheduled), template hierarchy
   (wp_template/wp_template_part), user capability semantics.

3. **`interactivity.{first chunk}`** — third Phase 7 bounded
   context. Recommended only after data-layer is settled.
   Candidates: directive-protocol / runtime-state /
   hydration-boundary.

4. **`block.dynamic-rendering` retro patch** — dynamic-rendering
   pre-dates entity-resolution; may benefit from retro section
   linking PHP entity reads to JS data-layer entity reads
   (canonical state shared, different consumption APIs).

Recommended sequence: (1) data-layer.persistence to fully seal
data-layer substrate; (2) interactivity entry; (3) retro patches
as bounded contexts mature. Alternative: enter interactivity
sooner if reactive grammar feels more pressing than persistence
depth.

**data-layer.persistence COMPLETED (2026-05-09):**

Write/reconciliation substrate counterpart to entity-resolution.
Frames persistence as authority reconciliation protocol, NOT
storage operation. data-layer substrate now SEALED.

**10 invariants established (key ones):**

1. **Save is NOT a terminal event** — multi-stage authority
   synchronization (12+ stage lifecycle: edit → dispatch → REST →
   capability check → sanitization → DB → revision → response →
   reconciliation). Optimistic UI may show success before server
   confirms.

2. **Persistence = authority reconciliation protocol, NOT storage
   operation** — framing matters: "save = storage" implies atomic/
   terminal/deterministic; "save = reconciliation" is multi-stage/
   multi-actor/async/may fail/partial/conflict.

3. **Three concurrent authority layers** (persisted / edited /
   transient) — NOT pipeline stages, **concurrent state spaces**
   with different durability/scope/authority. Editor is structurally
   a multi-layer authority orchestrator.

4. **Edit graph exists independently of persistence** — first-class
   state space. User can make 50 edits without saving. Components
   reading getEditedEntityRecord see edit graph; getEntityRecord
   sees persisted. Both valid views of authority.

5. **Autosave + revisions = temporal authority snapshots, NOT
   backups** — autosave is recovery-only (per-user-per-post),
   revisions are history (per-post navigable). Encode WordPress
   cultural commitment to persistence-latency-tolerant authoring.

6. **Optimistic UI is structural, not optimization** — sync wait
   would freeze editor; UI must remain interactive during save;
   optimistic = structural complement to async persistence.

7. **Capability enforcement happens at persistence boundary, NOT
   at edit** — UI controls hide based on hints; actual enforcement
   is server-side at REST permission_callback / save_post hooks /
   meta auth_callback. Hidden UI ≠ secured data.

8. **Conflict resolution is necessary, not exceptional** — multi-
   client / multi-tab / async sources / cron. WordPress default =
   last-write-wins (deliberate simplification). Sophisticated
   resolution is plugin/theme territory.

9. **Persistence completion ≠ user-visible commit** — post
   lifecycle (draft / scheduled / pending / trashed / private /
   password-protected) governs visibility ON TOP of persistence.
   "Save" persists; what readers see is downstream lifecycle.

10. **Gutenberg = delayed-authority synchronization runtime** —
    Phase 7 capstone framing. Distinct from form-based CMS (sync
    submit), pure local-state UI (no persistence concern), real-
    time collab (push-based). Gutenberg = single-author async
    editing with multi-stage reconciliation, snapshot history,
    optimistic UI atop deferred persistence.

**WordPress cultural-historical observation:**

WordPress's persistence-latency-tolerant philosophy (autosave/
revisions/drafts/preview/scheduled/undo) is foundational. In
classic WP these were UX features. In Gutenberg data-layer they
are **ontology** — explicit authority layers with explicit
reconciliation protocols.

- Autosave = "your work is safer than your discipline"
- Revisions = "history matters, not just current state"
- Drafts = "publication is separate from persistence"
- Preview = "you can see before others see"
- Scheduled = "persist now, publish later"
- Undo = "mistakes are recoverable, locally and reversibly"

**Phase 7 substrate triangle COMPLETE:**

```
   bindings              → attribute attachment surface  ✓
   data-layer.entity-res → read substrate                ✓
   data-layer.persistence → write substrate              ✓
   ↓
   (NEXT) interactivity   → reactive orchestration grammar
                            built atop the substrate
```

**KB-level framing now complete (Phase 7 capstone):**

> Gutenberg's compiler architecture (Phases 1-6) handled
> visual realization through schema-driven static / compile-time
> authority.
>
> Gutenberg's stateful runtime (Phase 7) handles authority
> orchestration through asynchronous reconciliation between
> three concurrent authority layers (persisted / edited /
> transient).
>
> Together: Gutenberg is a **two-axis system** — schema-driven
> visual compiler + delayed-authority synchronization runtime.

**Anticipated next: interactivity bounded context entry.**

Substrate preparation is the explicit goal achieved. With
bindings (attachment surface) + entity-resolution (read) +
persistence (write) all in place, "what state becomes reactive?"
has a complete answer:
- entity state (data-layer)
- persistence reconciliation lifecycle (data-layer)
- edit buffer state (data-layer)
- per-block instance state (block-authoring)
- ephemeral UI state (interactivity-introduced)

interactivity now has a complete substrate. Recommended first
chunks (any order works, internal sequence per ontology weight):

1. **`interactivity.directive-protocol`** — `data-wp-*`
   directive grammar (syntactic surface)
2. **`interactivity.runtime-state`** — store / context / actions
   ontology (state model)
3. **`interactivity.hydration`** — server-initial → client-mount
   boundary

Recommended first: directive-protocol (concrete syntactic surface
gives reasoning anchor for runtime-state and hydration).

**interactivity.directive-protocol COMPLETED (2026-05-09):**

First chunk in NEW interactivity bounded context. Phase 7 entry
point. Documents `data-wp-*` directive grammar as reactive
authority grammar — the syntactic surface that runtime-state and
hydration build on.

**10 invariants established (key ones):**

1. **Directives are reactive authority grammar, not data
   attributes** — `data-wp-bind--aria-expanded="state.isOpen"`
   is an instruction to the Interactivity runtime, not a data
   attribute storing arbitrary data.

2. **HTML becomes executable authority topology** — pre-API,
   HTML was structure/style hooks/serialization. Post-API, HTML
   is ALSO reactive runtime declaration surface.

3. **Directives = reactive attachment operators** (bindings
   extension): bindings connected attribute ↔ source; directives
   connect DOM ↔ reactive graph. Both share attachment-not-
   ownership pattern at different layers.

4. **Interactivity runtime is DOM-proximal** — KEY differentiator
   from React/Vue. HTML primary reality; runtime ATTACHES to
   existing DOM (vs VDOM ownership). No "client takes over"
   moment; same DOM throughout. Aligns with WP server-first
   philosophy.

5. **Server-first rendering structurally preserved** — server-
   side directive processor pre-evaluates many directives;
   transmitted HTML carries correct initial state. No FOUC, SEO
   complete, pages work at first byte not first JS execution.
   Server-first reactive (distinct from purely client-side
   reactive frameworks).

6. **Style-engine ↔ interactivity symmetry — Gutenberg = dual-
   runtime declarative system:**

   | runtime | declarative surface | engine | output |
   |---|---|---|---|
   | CSS | selectors / theme.json / styles | browser CSS engine | visual cascade |
   | Reactive JS | directives (data-wp-*) | @wordpress/interactivity + JS engine | reactive DOM behavior |

   Both server-first / authored declaratively / runtime-
   interpreted / browser-executed / have escape hatches.

7. **Context propagation mirrors block tree topology** —
   `data-wp-context` declares scoped reactive context that
   descendants inherit. Mirrors block.json
   providesContext/usesContext at runtime DOM layer. Same
   composition axis recurs across runtimes.

8. **Directives declare subscriptions and actions** — each
   directive places DOM node into reactive graph as specific
   edge kind (subscriber / trigger / observer). Runtime
   maintains graph; directives populate it.

9. **Directive evaluation crosses PHP → HTML → JS boundaries** —
   boundary-crossing protocol, not just client-side framework.
   Each boundary has own semantics.

10. **Phase 7 capstone framing:**

    > Gutenberg evolved from
    > **schema-driven visual compilation**
    > into
    > **distributed reactive authority orchestration**
    > across CSS, entities, persistence, and DOM-attached
    > runtime grammars.

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

**KB total chunk count update (approximate):**

After directive-protocol, KB has ~58 chunks across:
- block-authoring (substantively closed): ~32
- theme-config (substantively closed): ~14
- style-engine (CLOSED): 4 + selectors (in block-json) + retro patches
- data-layer: 2 (entity-resolution + persistence)
- interactivity: 1 (directive-protocol entry)
- _meta: dsl-spec
- (deferred) editor-customization / site-building / plugin-dev /
  i18n / build-tooling / admin-ui: 0

KB structurally complete for the 4 most load-bearing bounded
contexts (block-authoring, theme-config, style-engine, data-layer
substrate + interactivity entry). Remaining bounded contexts are
additive; they don't change Phase 7's structural framing.

**Anticipated KB sequencing options going forward:**

1. **interactivity inner depth** — runtime-state then hydration
   to close interactivity backbone.

2. **dynamic-rendering retro patch** — pre-dates Phase 7;
   retroactive section connecting PHP entity reads + directive
   emission + Interactivity processor. High coherence payoff.

3. **data-layer.entity-types** — per-entity-type specialization.
   Substrate is sealed; specialization on demand.

4. **Other bounded contexts entry** — editor-customization /
   site-building / plugin-dev / i18n / build-tooling / admin-ui.
   These are additive to current KB structure.

Recommended: (1) interactivity.runtime-state to complete
interactivity ontology pair; (2) interactivity.hydration to seal
interactivity backbone; (3) retro patches; (4) other bounded
contexts as needed.

Alternative: pause Phase 7 deep-dive and survey remaining
bounded contexts for KB completeness; then return to
specialization.

**interactivity.runtime-state COMPLETED (2026-05-09):**

Substrate counterpart to directive-protocol. interactivity
bounded context core ontology pair now complete.

**10 invariants established (key ones):**

1. **Stores are authority localities** — per-namespace authority
   graphs, isolated by default. Comparable to entity locality
   at data-layer (per-record state).

2. **Actions are mutation protocols, not direct mutations** —
   intent declarations runtime executes within reactive graph.
   Sync + generator-async forms.

3. **Derived state = reactive projections** — getters auto-track
   dependencies; recurring pattern across KB (styles var:preset
   refs / data-layer selectors / React useMemo).

4. **Context propagation mirrors DOM topology (DOM-tree DI)** —
   data-wp-context = DOM-tree DI. Same DI pattern as block-tree
   providesContext/usesContext at different runtime layer.

5. **Runtime state may never persist — ephemeral by default** —
   key divergence from data-layer. Persistence opt-in via
   data-layer dispatches in actions.

6. **Ephemeral state is FIRST-CLASS** (NOT workaround) — UI
   state, coordination, performance, privacy — all legitimately
   ephemeral. Authority lifetimes ontology treats it alongside
   persistent state.

7. **Subscriptions create reactive edges** — runtime maintains
   store-DOM graph implicitly via proxy tracking. Authors
   don't manage subscriptions manually.

8. **Stores coordinate, NOT render — DOM ownership NOT in
   store** (load-bearing React divergence):

   | framework | owns | does |
   |---|---|---|
   | React | virtual tree | reconciles VDOM to DOM |
   | Vue | virtual tree | reconciles VDOM to DOM |
   | **Interactivity** | **state (subjects)** | **does NOT render; mutates existing DOM via subscriptions** |

   > **DOM is stable; authority flows through it.**

9. **Actions can cross persistence boundary into data-layer** —
   cross-substrate coordination. Actions inherit reconciliation
   lifecycle when invoking dispatches. Phase 7 substrate
   composition operational point.

10. **AUTHORITY LIFETIMES ONTOLOGY (5-layer KB-level finding):**

    ```
    entity state         ── data-layer; persisted, reconcilable, global
    block instance state ── block attributes (serialized in markup)
    context state        ── data-wp-context; DOM-scoped, ephemeral
    store state          ── store(...).state; namespace-scoped, ephemeral
    derived state        ── getters; computed from any of the above
    ```

    Each layer has distinct source / lifetime / persistence /
    scope / mutation paths. CLOSED set per current Gutenberg
    architecture; future additions (collab editing distributed
    state) would extend this ontology.

**Authority models comparison (KB now has authority models at
every layer Gutenberg operates):**

| KB layer | authority model |
|---|---|
| capability authority (block-authoring + theme-config) | what controls + who configures |
| realization authority (style-engine) | how values become CSS |
| reconciliation authority (data-layer) | how persisted state evolves |
| **runtime state authority (this chunk)** | **how ephemeral / reactive state coordinates** |

**KB-level framing extension (Phase 7 deepening):**

> Gutenberg's reactive runtime is **DOM-stable**:
> - DOM topology owned by server render + author markup
> - Authority flows through DOM via subscriptions
> - Stores coordinate state without owning DOM tree
>
> Structural difference from React/Vue/Angular. WordPress
> server-first philosophy structurally manifested in the state
> substrate.

**KB ontology atlas status acknowledged:**

KB has moved past handbook summarization to **WordPress/Gutenberg
ontology atlas**. Each chunk contributes to coherent map of:
- Authority distributions
- Composition axes
- Realization pipelines
- Runtime substrates
- Reactive coordination

Future chunks evaluated for both accuracy AND structural fit.

**Phase 7 (interactivity bounded context) status:**

```
interactivity:
   directive-protocol    → reactive authority grammar           ✓
   runtime-state         → reactive authority substrate         ✓
   ↓
   (NEXT) hydration      → server-initial → client-mount boundary
                            (closes bounded context backbone +
                             converges Phase 7 framings)
```

**Anticipated hydration chunk significance:**

hydration will likely converge multiple framings — compiler/
runtime split (style-engine), SSR-first (WP), delayed authority
reconciliation (data-layer), runtime attachments (directives +
store), bindings/entity resolution/persistence latency.

It's where Phase 7's full authority graph crystallizes at the
server↔client boundary. Likely the final structural backbone
chunk for the entire Phase 7 substrate.

**Recommended next:**

1. **`interactivity.hydration`** — closes interactivity backbone
   + Phase 7 structural completion.

2. **`block.dynamic-rendering` retro patch** — high coherence
   payoff, light work. After hydration may be optimal timing.

3. **Other bounded contexts** (additive) — editor-customization /
   site-building / plugin-dev / i18n / build-tooling / admin-ui.

**interactivity.hydration COMPLETED — Phase 7 capstone
(2026-05-09):**

interactivity bounded context backbone STRUCTURALLY SEALED.
KB has reached Phase 7 structural completion.

**10 invariants established (key ones):**

1. **Hydration is authority continuity, NOT DOM reconstruction**
   (load-bearing). Server DOM remains reality; runtime attaches
   subscriptions onto it. NO virtual tree construction.

2. **Server-rendered HTML = FIRST reactive frame** (NOT SSR
   fallback) — already in correct reactive state; client picks
   up where server left off.

3. **DOM remains stable while authority attachments become
   reactive** — DOM topology unchanged through hydration; only
   directive-bound aspects mutate per state changes.

4. **Hydration attaches runtime semantics onto existing
   topology** — third "attach not own" pattern instance in KB
   (bindings → directives → hydration).

5. **Hydration granularity follows directive topology, NOT
   application roots** — Gutenberg uniqueness. No global app
   root. Pages = collection of independent interactive regions.
   Hydration cost ∝ interactive surface, not page size.

6. **Directive processing creates runtime authority edges** —
   hydration is THE edge creation event. Pre-hydration:
   directives are inert markup; post-hydration: live graph
   members.

7. **Hydration reconciles server-known and client-live
   authority** — both have authority; default = server seeds,
   client takes over.

8. **Initial state injection preserves authority continuity
   across environments** — wp_interactivity_state() PHP +
   data-wp-context + pre-evaluated bindings + serialized
   entities all carry authority across server→client boundary.

9. **Selective hydration preserves WordPress server-first
   philosophy** — structural embodiment of WP historical
   principles (HTML primary deliverable, JS enhances not
   requires).

10. **Hydration completes Gutenberg's transition from compiler
    to distributed runtime orchestration system — Phase 7
    capstone:**

    > Gutenberg evolved from
    > **schema-driven visual compilation**
    > through
    > **runtime authority orchestration**
    > into
    > **a distributed authority continuity system**
    > spanning multiple execution environments (PHP server /
    > network / JS runtime / browser CSS / browser cascade)
    > with explicit reconciliation protocols at every boundary.

**KB-level question pivots (3-stage evolution documented):**

| KB era | dominant question |
|---|---|
| Phases 1-6 | What authority exists? |
| Phase 7 substrate | What authorities are attached to what? |
| **Phase 7 capstone (hydration)** | **How does authority cross execution boundaries?** |

**Hydration as compiler-runtime linkage (KB-wide symmetry):**

| layer | compiler | linker / loader | runtime VM |
|---|---|---|---|
| CSS | style-engine (variables, selectors, materialization, aggregation) | (browser ingestion of stylesheets) | browser CSS engine |
| Reactive JS | server-side block render + Interactivity processor | **hydration** | @wordpress/interactivity client runtime |

Hydration is the JS-side **linker / runtime loader** parallel
to browser CSS ingestion. Seals "Gutenberg = dual-runtime
declarative system" framing structurally (not just
declaratively).

**Operational ontology — page is convergent output of:**

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

**KB structural completion:**

After hydration, KB is structurally complete for documented
Gutenberg architecture (Phases 1-7). KB is now an
**operational ontology atlas of Gutenberg's authority
architecture**, not a documentation summary.

Going forward, the question for new chunks shifts from
"what does this field do?" to "how does this fit the existing
authority cartography?"

**Remaining work categories (post-Phase-7-capstone):**

1. **Specialization / depth** within existing bounded contexts:
   - interactivity.navigation (cross-page reactive state
     continuity)
   - interactivity.server-processing (PHP processor deeper dive)
   - data-layer.entity-types (per-type lifecycle: post / template
     / user)
   - per-directive-family deep-dives if needed

2. **Retro patches** — bring earlier chunks up to current
   ontology framing:
   - block.dynamic-rendering retro (pre-dates entire Phase 7)
   - block.markup-representation retro (pre-dates bindings +
     Phase 7; serialized markup as authority host)

3. **Other bounded contexts** (additive — extend coverage but
   do not change Phase 7 framing):
   - editor-customization
   - site-building
   - plugin-dev
   - i18n
   - build-tooling
   - admin-ui

4. **Cross-chunk coherence audit:**
   - dsl-spec update with status `evolving` convention + DSL
     extension applicability extension
   - cross-reference completeness audit
   - terminology consistency

**Recommended next decision points:**

A. **Retro patches first** — light work, high coherence payoff.
   block.dynamic-rendering most pressing (pre-dates all Phase 7).

B. **Other bounded contexts** — broaden coverage. Each additive.

C. **Coherence audit / DSL spec update** — pause production,
   review accumulated material.

D. **Specialization** — deepen Phase 7 areas where needed.

Recommended: **A (retro patches first)** — maximize coherence
payoff before additive work.

**dynamic-rendering RETROACTIVE REFRAMING completed (2026-05-09):**

Second RETROACTIVE REFRAMING section in KB (after wrapper-
attributes). Reframes dynamic-rendering through post-Phase-7
ontology. The original chunk (pre-Phase-7) reads dynamic-
rendering as SSR / render_callback / freshness; the retroactive
section reframes it as **server-side authority projection node**
in distributed authority continuity architecture.

**5 retroactive subsections (A-E):**

A. **Dynamic rendering = server-side authority projection** —
   serialized markup ≠ truth (post_content stores invocation
   topology); authority lives in runtime resolution. Direct
   connection to bindings invariant #4.

B. **Serialization no longer owns truth (dynamic was the
   precedent)** — dynamic blocks established this pattern years
   before bindings. Bindings extended principle to attribute
   layer; dynamic was the block-level proto-form.

C. **Dynamic rendering prefigures hydration continuity** — KEY
   coherence payoff. Interactivity API = NOT new paradigm,
   but **latent runtime architecture becoming explicit**.
   Dynamic-rendering already crossed execution boundaries
   (DB → PHP → REST → serialization → frontend); interactivity
   extends boundary crossings into reactive territory. Same
   lifecycle, two halves (server / client).

D. **render_callback = server-side runtime reconciliation
   boundary** — equivalent to data-layer's resolver pipeline at
   the server-side render layer. Reads entity state, resolves
   context, reconciles into projection HTML. Compiler/runtime
   linker symmetry: render_callback (server-side half) +
   interactivity hydration (client-side half).

E. **Server-rendered HTML as first reactive frame (retro
   linkage)** — render_callback PRODUCES the first reactive
   frame in distributed authority continuity. Connects directly
   to hydration invariant #2.

**Pipeline closure across Phase 7 documented:**

```
entity authority (data-layer)
   ↓
server-side projection (render_callback)
   ↓
HTML serialization (with directives if applicable)
   ↓
network transport
   ↓
browser parse
   ↓
directive attachment / hydration
   ↓
client reactive authority (runtime-state)
   ↓
persistence reconciliation (data-layer.persistence)
```

dynamic-rendering occupies stage 2-3 of the lifecycle. The
chain has been complete since WP 6.1 — Interactivity API just
made the client-side half explicit.

**KB-level coherence payoff (this is the structural insight):**

> **Architectural shifts in Gutenberg are often REVELATIONS
> OF LATENT STRUCTURE, not introductions of new paradigms.**
>
> Dynamic-rendering was already authority projection.
> Bindings made the projection mechanism declarative at
> attribute layer.
> Interactivity made it reactive at runtime layer.
> Hydration made it cross-environment continuous.
>
> The architecture was always there; the documentation
> ontology evolves to surface it.

This reframing produces structural narrative continuity in KB.
KB no longer reads as if Phase 7 was a sudden architectural
pivot — it reads as **architectural revelation** through
documentation maturation.

**KB pattern crystallized: RETROACTIVE REFRAMING:**

Now established as recurring KB pattern (2 instances in KB):
- wrapper-attributes (post-style-engine closure)
- dynamic-rendering (post-Phase-7-capstone)

Both follow the same pattern:
1. Original chunk preserved (NOT rewrite)
2. Status note + KB pattern note explicit
3. Retroactive section appended at end
4. Subsections numbered/labeled retroactive invariants
5. Pipeline closure / KB-level framing payoff

**Anticipated next retroactive candidate:**

`block.markup-representation` retro — pre-dates bindings + Phase
7. Likely reframes HTML serialization as "authority continuity
carrier" (vs current "serialization artifact" framing). Same
"latent → explicit" coherence payoff potential.

**Recommended next:**

1. **`block.markup-representation` retro patch** (3rd retro,
   completes Phase 7 backward propagation through
   block-authoring chunks).

2. **DSL spec update** — codify retroactive reframing pattern,
   evolving status, DSL extension applicability extension.
   Should happen before more chunks accumulate to keep spec
   in sync.

3. **Other bounded contexts entry** — editor-customization /
   site-building / plugin-dev / i18n / build-tooling /
   admin-ui. Now additive on solid ontology base.

**markup-representation RETROACTIVE REFRAMING completed
(2026-05-09):**

Third RETROACTIVE REFRAMING section in KB. Reframes block
markup grammar as **Gutenberg's universal continuity substrate**
(NOT serialization artifact).

**3-retro narrative arc complete:**

| retro chunk | reframed element | role revealed |
|---|---|---|
| wrapper-attributes (post-style-engine) | wrapper element | ABI boundary / authority transport surface |
| dynamic-rendering (post-Phase-7-capstone) | server-rendered HTML | authority projection node / first reactive frame producer |
| **markup-representation (post-Phase-7-capstone)** | **HTML grammar itself + delimiters + body** | **universal continuity substrate** |

**5 retroactive subsections (A-E):**

A. **HTML as authority continuity carrier** (backbone) —
   8-boundary table showing HTML mediates every Gutenberg
   authority crossing.

B. **Block delimiters as reconstruction anchors** — pre-Phase-7
   "serialization metadata" → post-Phase-7 "authority restoration
   points carrying identity + payload + bindings metadata +
   reconstruction parameters."

C. **Markup carries executable attachment topology** — HTML body
   hosts simultaneously: block-type identity / preset references
   / generated selector classes / element projection / variable
   consumption / interactivity scope / reactive context payload /
   directive bindings / event handlers / persistent anchors /
   accessibility hints. NOT "rendered content"; **"executable
   authority topology serialized as markup."**

D. **Serialization becomes partial projection** — post_content =
   resumable authority graph snapshot, NOT complete state.
   Generalizes dynamic-rendering retro framing to ALL bound /
   interactive blocks (not just dynamic). In all but simplest
   static cases: post_content is partial; authority lives
   elsewhere.

E. **HTML mediates between execution environments** — Phase 7
   capstone linkage. 6-environment-transition table showing
   HTML is the medium for every boundary crossing (PHP→network /
   network→browser parse / DOM→CSS engine / DOM→Interactivity /
   DOM→editor parse / Interactivity→DOM mutation).

**KB-level coherence payoff: HTML primacy framing**

> Gutenberg's architectural sophistication does NOT abandon
> HTML primacy. HTML's role expanded from "output format" to
> **"universal continuity substrate"** — but it remained
> foundational.
>
> Distinct from SPA frameworks (which treat HTML as intermediate
> render output subordinate to virtual trees). Gutenberg keeps
> HTML as PRIMARY REALITY through every layer of architectural
> complexity.
>
> HTML primacy = WordPress's architectural inheritance, honored
> through every modernization step.

**KB narrative closure (3-retro arc):**

> HTML in Gutenberg is not output, not artifact, not
> serialization format. It is the **operational substrate of
> distributed authority continuity** — the medium through which
> every architectural layer's authority transits, attaches,
> reconstructs, and synchronizes.
>
> The architecture sophisticated THROUGH HTML, not around it.

This closes the gap between WordPress's HTML-first historical
philosophy and Gutenberg's modern distributed authority
architecture.

**KB pattern crystallized: 3 retro instances:**

RETROACTIVE REFRAMING is now firmly established as KB pattern.
All three retros follow same structure:
1. Original chunk preserved (NOT rewrite)
2. Status note + KB pattern note explicit
3. Retroactive section appended
4. Subsections labeled retroactive invariants
5. Pipeline closure / KB-level framing payoff

Future retro candidates (when downstream contexts mature):
- block.inner-blocks (when composition / template bounded
  contexts develop)
- block.deprecation (already at compatibility runtime framing
  but may benefit from additional Phase 7 linkage)
- theme-config.styles.css (already partial reframing via
  residual-governance)
- block.json-context (DI pattern recurrence across bounded
  contexts may warrant retro)

**Phase 7 backward propagation through block-authoring COMPLETE.**

The 3 most-load-bearing block-authoring chunks (wrapper-attributes,
dynamic-rendering, markup-representation) have been retroactively
linked to Phase 7. Block-authoring chunks no longer "lag behind"
Phase 7 ontology.

**Recommended next:**

A. **DSL spec update** — codify retroactive reframing pattern,
   evolving status, DSL extension applicability extension.
   3 retros + 4 evolving chunks accumulated; spec sync timely.

B. **Other bounded contexts** entry — editor-customization /
   site-building / plugin-dev / i18n / build-tooling / admin-ui.
   Now additive on fully sealed Phase 7 + retro-completed
   block-authoring.

C. **Phase 7 specialization** — interactivity.navigation /
   server-processing / data-layer.entity-types.

Recommended: **A (DSL spec update)** — accumulated conventions
should be sync'd to spec before more chunks add divergence.
After spec update, choose between B (broaden coverage) or C
(deepen Phase 7).

**DSL spec sync COMPLETED (2026-05-09):**

Major dsl-spec.md update formalizing 6 axes accumulated through
KB authoring. KB has now self-formalized as
**"operational ontology atlas of WordPress/Gutenberg's authority
architecture"** (no longer a documentation summary system).

**6 axes formalized:**

1. **status ontology** — 4-value enum (stable / evolving /
   experimental / deprecated). Reframed as **epistemic
   stability classification**, NOT completeness indicator.
   `evolving` documented with explicit criteria + 6 current
   instances listed. Distinct from `verification-needed`
   (specific fact unknown) vs `evolving` (mechanism evolves).

2. **DSL extensions** (VERIFICATION NEEDED + META sections)
   formalized. Applicability **generalized** from style-engine
   bounded context to:

   > Chunks documenting runtime / implementation-derived
   > authority surfaces with **runtime authority
   > indeterminacy** — regardless of bounded context.

   3 trigger criteria documented. Correlated with `evolving`
   status but not identical.

3. **RETROACTIVE REFRAMING pattern** formalized as KB-native
   pattern with 3 established instances (wrapper-attributes /
   dynamic-rendering / markup-representation). Template
   structure documented. Philosophical framing: "KB is a
   progressive ontology revelation system, not static
   documentation."

4. **Authority ontology glossary** — 12 foundational terms +
   6 recurring axes. Vocabulary lock prevents drift across
   chunks. Foundational vocab: authority / ownership /
   attachment / realization / reconciliation / continuity /
   projection / substrate / runtime locality / escalation /
   reactive edge / orchestration. Recurring axes:
   declaration↔realization / declaration↔exposure / copy↔reference /
   compile-time↔runtime / persisted↔ephemeral↔derived /
   entity-centric↔relationship-centric.

5. **KB phase model** (Phases 1-6 + 7a/b/c + capstone)
   formalized with KB-level question pivots documented as
   3-stage evolution. Bounded context positions explicit.
   Used for: chunk positioning, retroactive timing, additive
   bounded context placement.

6. **HTML primacy doctrine** — load-bearing framing constraint
   documented at spec level. "Gutenberg sophisticated THROUGH
   HTML, not around it." 4 implications for chunk authoring.
   Distinct from anti-React polemic (React used in editor;
   Gutenberg's distinction is at FRONTEND runtime).

**Additional spec changes:**

- Bounded contexts: 10 → 11 (style-engine added — discovered
  during authoring, not in original DDD cut).
- Length norm relaxed for inflection-point chunks (8K-13K
  observed for Phase 7 capstones; framing weight justification).
- Reference exemplars updated with Phase 7 + retro chunks.
- KB self-definition closing note: 5 chunk evaluation criteria
  (accuracy / structural fit / reusability / phase fit /
  doctrine respect).

**Spec is now KB operating system:**

The DSL no longer just describes "how to format chunks." It
encodes:
- epistemic semantics (what we know vs what evolves)
- KB chronology (phases + question pivots)
- terminology lock (glossary)
- structural patterns (retroactive reframing)
- philosophical doctrine (HTML primacy)
- self-evaluation criteria

Future chunks reference the spec for vocabulary, framing, and
positioning — not just format.

**Anticipated next:**

Now that spec is sync'd, additive bounded contexts can enter
without ontology drift risk. Two natural directions:

A. **Other bounded contexts entry** (additive):
   - editor-customization (block filters, slotfills, editor
     hooks — likely "authoring environment governance" framing)
   - site-building (templates / patterns / parts at runtime —
     composition layer)
   - plugin-dev (extension authority surface — `register_*`
     APIs)
   - i18n (orthogonal cross-cutting substrate)
   - build-tooling (development authority surface)
   - admin-ui (administration authority surface)

B. **Phase 7 specialization**:
   - interactivity.navigation
   - interactivity.server-processing
   - data-layer.entity-types

Recommended next: any of the additive bounded contexts. With
spec sync done + Phase 7 capped + 3-retro arc complete, the
KB structural backbone is stable enough that additive work
won't disturb it.

User's likely priority signal will determine which bounded
context enters next. plugin-dev is high cross-ref leverage
(referenced by many existing chunks for `register_*` APIs);
editor-customization is high day-to-day relevance for theme/
plugin authors; i18n is foundational orthogonal substrate.

**plugin-dev bounded context ENTERED (2026-05-09):**

First plugin-dev chunk: register-block-bindings-source.md.
Positioned as Phase-7-native entry (NOT classic procedural API
entry). plugin-dev framed as "external authority architecture"
domain rather than "WordPress procedural APIs."

**Strategic framing established:**

> WordPress is not a closed authority architecture. It is a
> **governed extensibility ecosystem** where authority origins
> federate from multiple actors (core, plugins, themes).
> Plugin-dev bounded context documents the registration APIs
> that enable this federation.

**8 invariants established (key ones):**

1. **Plugin APIs register authority origins, NOT merely content
   types** — qualitative shift from old-school plugin-dev
   (CPT, taxonomy, hooks) to modern Gutenberg-native plugin-dev
   (authority extension).

2. **Bindings sources externalize Gutenberg's authority graph** —
   plugin-registered sources participate as first-class
   authority origins alongside core/post-meta etc.

3. **Source providers are authority federation nodes** —
   WordPress = governed extensibility ecosystem with federated
   authority graph.

4. **Plugin-dev enters at architecture extension, NOT surface
   customization** — KB-level positioning. Subsequent plugin-dev
   chunks should use architecture-extension framing, not
   "WordPress APIs" framing.

5. **Read/write directionality determines authority permeability**
   — set_value_callback presence is qualitative gate (writable
   sources inherit data-layer.persistence reconciliation
   complexity).

6. **Authority extensibility introduces trust / security
   boundaries** — FIRST EXPLICIT SECURITY FRAMING in KB.
   plugin-dev is the bounded context where security becomes
   structurally central (capability checks / trust /
   permission surfaces).

7. **Plugin APIs expose governance, NOT raw execution** —
   governed extensibility (plugins extend authority surfaces
   but don't get unconstrained execution power).

8. **Bindings source registration = plugin-dev's Phase-7-native
   entry point** — KB-level positioning rationale.

**plugin-dev family taxonomy anticipated:**

| family | ontology |
|---|---|
| bindings source | authority origin federation |
| register_meta | persistence substrate extension |
| REST route | transport boundary registration |
| block filters | authoring governance |
| CPT / taxonomy | entity schema extension |
| slotfills | UI governance |
| (cross-cutting) security boundaries | trust / capability / sanitization |

All read as **external authority architecture extensions**, NOT
"WordPress APIs." This taxonomy guides plugin-dev chunk authoring
going forward.

**Security ontology entry point:**

This chunk is the FIRST KB chunk to surface trust/security as
structural concern. Subsequent plugin-dev chunks (register_meta,
REST routes) will deepen security ontology. A dedicated
cross-cutting chunk (`plugin-dev.security-boundaries`) is
anticipated when 3+ plugin-dev chunks reference shared security
patterns.

**Anticipated next chunks (priority):**

1. **`plugin-dev.register-meta`** — parallel registration to
   bindings source as persistence substrate extension. Often
   composes (custom meta → custom binding source).

2. **`plugin-dev.rest-route-permission`** — permission_callback
   ontology. Deepens security framing.

3. **`plugin-dev.register-post-type`** — entity schema
   extension, reframed via Phase 7 ontology.

4. **`plugin-dev.security-boundaries`** — when 3+ chunks
   reference security patterns, formalize as cross-cutting
   chunk.

Recommended sequence: register-meta next (immediate
composition with bindings source, high cross-ref payoff with
data-layer.persistence). Then rest-route-permission to
formalize security ontology with at least 2 supporting
chunks. Then register-post-type to reframe classic API.

**plugin-dev.register-meta COMPLETED (2026-05-09):**

Second plugin-dev chunk. Persistence-side twin of
register_block_bindings_source. Frames register_meta as
**schema-governed persistence authority surface** rather than
arbitrary key/value storage.

**Authority federation stack now has 2 of 4 layers:**

| stack layer | API | role | status |
|---|---|---|---|
| **origin** | register_block_bindings_source | declares NEW authority origins | ✓ |
| **persistence** | register_meta | declares NEW authority persistence slots | ✓ |
| (NEXT) **transport** | register_rest_route | declares NEW transport boundaries | planned |
| (NEXT) **entity schema** | register_post_type | declares NEW entity kinds | planned |

**8 invariants established (key ones):**

1. **Meta registration declares persistence authority surfaces** —
   classic perception (opaque storage) vs modern framing
   (schema-governed). Unregistered meta still works but is
   outside schema-governed federation.

2. **Meta keys = schema-governed authority slots, NOT arbitrary
   storage** — typed slot with declared schema, not free-form
   storage location. Structurally similar to block.json
   attributes (typed schema) but for persistence.

3. **Declaration ≠ exposure ≠ accessibility** — KB-recurring
   axis at persistence layer. Three independent surfaces:
   `register_meta()` (declaration) / `show_in_rest`
   (exposure) / `auth_callback` (accessibility).

4. **Sanitization + authorization = authority legitimacy gates**
   — `sanitize_callback` governs authority shape (what shape
   may this take?); `auth_callback` governs authority access
   (who may exercise?). Second-tier security ontology
   surfacing in plugin-dev.

5. **Meta registration extends data-layer.persistence externally**
   — plugins inherit reconciliation lifecycle (edit buffer,
   save dispatch, REST request, capability check, sanitization,
   DB write, revision, response, store update). Don't
   reimplement persistence semantics.

6. **Bindings + meta = end-to-end custom authority pipeline**
   (composition invariant). Plugin-side primitives compose to
   form complete distributed authority pipeline: register meta
   + register binding source (or use core/post-meta) + block
   bindings declaration → block instance projects meta value
   → editor edits propagate → reconciliation → persisted.
   Plugin authors declare extensions; don't reimplement
   architecture.

7. **Meta = persistence ABI for plugin-defined authority** —
   parallel to css-variable-emission's runtime ABI. Meta key +
   type + single + default + sanitize + auth = declared
   contract other actors (REST consumers, editor UI, bindings,
   plugins) can rely on.

8. **Classic custom fields = legacy perception; registered meta
   = modern governance** — KB-level positioning. Two
   ontological readings of same DB rows. KB chunks reflect
   Gutenberg-native reading.

**Security ontology second surfacing:**

Pattern: each plugin-dev chunk surfaces security at the
specific boundary it governs.
- chunk 1 (bindings source): general trust/permission
  framing.
- **chunk 2 (register_meta): explicit `sanitize_callback` +
  `auth_callback` mechanisms.**
- chunk 3 (register_rest_route): permission_callback patterns.
  After this, formalize cross-cutting `plugin-dev.security-
  boundaries` chunk.

**plugin-dev domain identity now anchored:**

> Plugin-dev bounded context is the **external authority
> architecture** layer of WordPress. Registration APIs extend
> Gutenberg's authority federation in 4 dimensions: origin,
> persistence, transport, entity schema.
>
> Plugins are NOT procedural extensions to a closed system —
> they are federation participants in WordPress's authority
> architecture, governed by registration contracts at each
> dimension.

This framing now has 2 supporting chunks and is ready for
further extensions to use coherently.

**Anticipated next chunks (priority):**

1. **`plugin-dev.rest-route-permission`** — transport layer.
   Completes read/write/transport triangle. Third security
   ontology surfacing.

2. **`plugin-dev.register-post-type`** — entity schema layer.
   Closes 4-layer authority federation stack. Classic API
   reframed via KB ontology.

3. **`plugin-dev.security-boundaries`** — formalize when 3+
   chunks reference security patterns.

4. Other plugin-dev families (filters / slotfills / hooks) on
   demand.

Recommended sequence: register-rest-route next (transport
layer + third security surfacing → triggers security-
boundaries chunk). Then register-post-type (entity schema
closure). Then security-boundaries cross-cutting chunk.

**KB total chunk count update (post-register-meta):**

After plugin-dev.register-meta, KB has:
- block-authoring (substantively closed): ~32 + 3 retro
- theme-config (substantively closed): ~14
- style-engine (CLOSED): 4 + selectors
- data-layer (substrate sealed): 2
- interactivity (BACKBONE SEALED): 3
- plugin-dev (entered, 2 chunks): 2
- _meta: 1 (dsl-spec)
- 5 remaining additive bounded contexts: 0

Total ≈ 64 chunks across 6 of 11 bounded contexts.

**KB total chunk count update (approximate, post-plugin-dev
entry):**

After plugin-dev.register-block-bindings-source, KB has:
- block-authoring (substantively closed): ~32 + 3 retro patches
- theme-config (substantively closed): ~14
- style-engine (CLOSED): 4 + selectors
- data-layer (substrate sealed): 2
- interactivity (BACKBONE SEALED): 3
- plugin-dev (entered): 1
- _meta: 1 (dsl-spec — major sync update)
- 5 remaining additive bounded contexts (editor-customization /
  site-building / i18n / build-tooling / admin-ui): 0

KB now spans 6 of 11 bounded contexts with structural depth.
Remaining 5 bounded contexts are additive (won't disturb Phase
7 framing).

**theme.json top-level fields = NEW ontology pattern (2026-05-09):**

`customTemplates`, `templateParts`, `patterns` are **filesystem-
coupled metadata registries** — fundamentally different from
settings/styles' self-contained declarations:

| Field | What theme.json contains | What lives elsewhere |
|---|---|---|
| `customTemplates` | metadata: name, title, postTypes | template HTML in `/templates/{name}.html` |
| `templateParts` | metadata: name, title, area | part HTML in `/parts/{name}.html` |
| `patterns` | array of slug strings | pattern definitions in WordPress.org Pattern Directory |

**Implication:** theme.json is NOT a self-contained declaration
language for everything. For top-level structural fields, theme.json
acts as a **pointer / metadata extension** layer over external
content (filesystem files or remote registries). This is yet
another "schema boundary" reveal beyond styles.css's escape hatch.

**The user-framed broader ontology (semantic composition graph)
applies to these CONCEPTS but spans multiple layers:**
- patterns ontology = theme.json patterns (slug registration) +
  PHP `register_block_pattern()` + `/patterns/` folder convention
  + WordPress.org Pattern Directory remote source
- templateParts ontology = theme.json templateParts (metadata) +
  `/parts/{name}.html` filesystem + block-template-parts loader
- customTemplates ontology = theme.json customTemplates (metadata)
  + `/templates/{name}.html` filesystem + template hierarchy
  resolution

Each top-level field is just ONE entry point into the broader
multi-layer system. KB chunks should focus on what theme.json
specifically contributes; broader ontology requires separate
chunks per layer.

**Continuous vs discrete computational subtypes within realization
(refined 2026-05-09 anticipating spacing):**

| Capability | Computation type at realization |
|---|---|
| typography | continuous interpolation (clamp() formulas, viewport-driven) |
| spacing | discrete generation (spacingScale-multiplied preset list) |

Both are computational, but the realization output character differs.
typography emits one runtime-responsive value per preset (clamp);
spacing emits multiple static preset values from a single algorithm.

**blockGap = first "full-stack capability" anticipated:**

After settings.spacing.blockGap is realized in styles.spacing.blockGap,
blockGap will have appeared across **6 distinct layers** — the most
cross-coupled capability in the entire KB:

| Layer | blockGap role |
|---|---|
| `block.supports.spacing.blockGap` | per-block opt-in (declares the control) |
| `appearanceTools` (mediation) | bundled in appearanceTools |
| `settings.spacing.blockGap` | theme gate for `--wp--style--block-gap` generation |
| `styles.spacing.blockGap` (anticipated) | realization value source for the CSS variable |
| `block.supports.layout` | layout type consumes blockGap value as `gap` CSS |
| (style-engine, future) | actual CSS variable emission + gap CSS rule generation |

**styles layer identity emerging — "CSS generation semantics layer":**

Through styles.color (consumption) and styles.typography (computational
realization), styles is establishing a clear identity beyond
"realization layer" — it is the **CSS generation semantics layer**.
Concerns surfacing:
- preset materialization (var:preset|... → CSS variable resolution)
- selector authority (top-level / elements / blocks / instance cascade)
- value emission (literal vs reference, sanitization implied)
- runtime cascade (4-level specificity)

After spacing, the layer's identity should be fully established.

**`units` field — governance ↔ realization coupling
(styles-side anticipated):**

settings.spacing.units governs WHICH dimensional units users may
input. styles-side consequences (verification needed, but
anticipated):
- Values in styles.spacing.* presumably must conform to the units
  list (or are normalized?).
- Preset values stored under registries (spacingSizes / spacingScale)
  using disallowed units may emit warnings or be rejected.
- This makes settings.spacing.units NOT just an editor UI option
  but a **realization-layer constraint** — settings carrying
  realization policy is structurally similar to typography's fluid.

**KB long-term framing observation (acknowledged 2026-05-09):**

Gutenberg has revealed itself in the KB as not just "block editor"
but as a **multi-layer authority + semantic realization system**:
- authority distribution (border discovery, supports vs theme split)
- semantic identity (deprecation/transforms/variations triangle)
- realization ownership (settings → styles axis inversion)
- computational token systems (typography fluid, spacing scale)
- generative DSL (spacingScale operator/increment/steps)
- rendering cascades (4-level specificity hierarchy)

styles.spacing is anticipated to be the chunk where this multi-
layer system identity becomes most fully articulated.

**styles.typography element pressure (stronger than color):**

Typography differentiates into headings (h1-h6), body, buttons,
captions, links — each may need distinct typography. The
`styles.elements.{element}.typography.*` path becomes the dominant
realization surface for typography (more so than for color).
Anticipated 4-level specificity hierarchy:
```
styles.typography.*                        → site-wide / body
styles.elements.{element}.typography.*    → per-element type
styles.blocks.{name}.typography.*         → per-block-type
block instance style.typography.*          → per-instance
```

**settings ↔ styles asymmetry profile per capability:**

| Capability | settings fields | styles fields | Asymmetry |
|---|---|---|---|
| color | 14 | 3 | 4.7x — settings BROAD, styles NARROW (element styles fill the gap) |
| typography | 16 | ~12 | 1.3x — mostly 1:1 mirror (gate ↔ value pairs) |

Different per-capability asymmetry profiles imply different
realization architectures: color uses element scoping for the
"missing" gap; typography appears to be more directly mirrored.

**appearanceTools is a META-capability, NOT a capability.**
It is NOT a member of the 6 capability families (Content / Container /
Surface treatment / Visual transform / Composition / Governance).
It belongs to a separate **"capability governance topology"** layer.
Misclassifying it as a capability would be a category error.

**4 anomalies retroactively explained by appearanceTools:**
- `border` has no supports flag → bundle-only theme governance
- `position.sticky` standalone supports + bundle mediation
- `background.backgroundImage/backgroundSize` exposed via bundle
- `typography.lineHeight` bundled while `fontSize` is not
- `shadow` standalone (NOT bundled)
- `color`/`typography` bundle only specific subgroups

These prove: **`Capability existence ≠ Capability exposure ≠
Capability governance authority ≠ Capability execution`**.
Each capability has its own authority distribution; appearanceTools
is the governance multiplexing layer.

Border discovery's significance retroactively clarified: certain
capabilities are intentionally **removed from block-author authority
and centralized into theme authority**. Gutenberg's authority
distribution is hybrid centralized/decentralized — not uniform.

**5 anticipated theme-config phase axes:**
1. Global vs local authority distribution (every block-authoring
   capability has a counterpart here)
2. Preset system ontology (currently scattered references; will
   surface as unified preset namespace graph)
3. Style engine actualization (the executor that resolves preset
   slugs to CSS — currently deferred from every supports chunk)
4. appearanceTools reinterpretation (governance bundling — possibly
   warrants its own standalone chunk per user framing)
5. "Authority compression" pattern (many distributed block
   capabilities → centralized governance surface)

**Recommended theme-config sub-area order:**
1. settings.* (capability authority core) — biggest area, highest
   cross-ref demand
2. presets (cross-capability ontology — unified preset slug system)
3. styles.* (execution surface — actual property emission)
4. layout settings (composition authority)
5. appearanceTools (governance bundling — possibly standalone)
6. selectors (selector indirection system)

**Why minor block.json fields (selectors / styles / example /
blockHooks / version) wait until AFTER theme-config:**
Selectors specifically couples to style-engine + theme.json +
capability targeting. Writing it standalone now would miss
ontology depth. Same for block.json `styles` (block styles)
which interacts with variations + theme.json styling.

**Why interactivity waits until AFTER theme-config + style-engine:**
interactivity is a paradigm jump (server/editor → client runtime).
Need style/composition/theme authority ontology settled first
to provide foundation for "frontend runtime authority" as an
additive axis rather than a competing model.

**Critical insight from border discovery (2026-05-09):**

`border` has **NO supports exposure layer** — only theme.json settings +
styles. This is NOT a documentation gap; it's a deliberate design
choice by Gutenberg. Border is judged as **theme authority territory**,
not block-author. Block authors cannot opt their block into border
controls per-block; the theme decides globally via
`appearanceTools` aggregation.

This means **Gutenberg does NOT uniformly model capabilities**. Each
capability has its own authority distribution:
- `color`, `typography`, `spacing`, `dimensions`, `shadow` → exposure
  via supports (block-author authority)
- `border` → exposure SKIPPED (theme-only authority)
- (likely others to be discovered: layout has its own subsystem,
  position is partial, etc.)

The KB has shifted from documenting "block capabilities" to mapping
an **authority distribution system**.

**Possible additional ontology layer to watch (from shadow watchpoint C):**
`appearanceTools` — a single boolean in theme.json that bundles
multiple capability controls (border, text-color, link-color, etc.)
under one umbrella. If shadow's coupling to appearanceTools is strong,
**"capability bundles"** may be a 5th layer that overlaps with the
4-layer authority model.

**Substrate-gap closure principle (2026-05-09):**
Before adding new capability flags (supports.typography, supports.spacing,
etc.), close hidden substrate hubs that the existing chunks depend on
via "(planned)" cross-refs:
- `block.wrapper-attributes` — actually editor/runtime synchronization
  contract; the convergence point for supports classes, style engine
  output, data attributes, alignment, layout, ARIA, block identity,
  selection semantics. "block wrapper = runtime surface boundary".
- `block.dynamic-rendering` — completes `save: () => null` ontology by
  defining the canonical rendering authority for dynamic blocks. First
  "dual-runtime chunk" (editor runtime vs frontend runtime).
- `block.inner-blocks` — nested IR composition system, high ontology
  density expected (markup-representation / deprecation tier). Spike
  required, NO batching.

Adding capability flags (typography/spacing/layout/dimensions) before
closing these will keep accumulating "(planned)" cross-refs and
hand-waved descriptions of wrapper / runtime / nesting effects in
each flag chunk.

**How to apply:**
- Before writing any chunk, ask: "What actionable rule does this give the LLM?"
- If the answer is "explanation of context for humans" → skip or compress to a
  single 'context' frontmatter line.
- Frontmatter is the filter mechanism; populate it carefully so that future
  filtering ("show me only stable WP 6.6+ block-editor rules") works.
- Bounded contexts are not yet defined — proposal stage. Initial cut should
  be reviewed with user before mass chunk authoring begins.
- The previous "follow Chapters.md index order" plan was correct for
  *organizational structure* but not for *chunk content shape*. Chunks are
  DSL rules, not handbook page transcriptions.

---

**plugin-dev.register-rest-route COMPLETED (2026-05-09):**

Third plugin-dev chunk. Authority federation stack 3/4 layers
complete. REST routes framed as **transport constitution layer**
(NOT REST endpoints).

**Authority federation stack (3 of 4):**

| stack layer | API | role | status |
|---|---|---|---|
| origin | register_block_bindings_source | declares NEW authority origins | ✓ |
| persistence | register_meta | declares NEW authority persistence slots | ✓ |
| **transport** | **register_rest_route** | **declares NEW transport boundaries** | ✓ |
| (NEXT) entity | register_post_type | declares NEW entity kinds | planned |

**8 invariants established (key ones):**

1. REST routes declare transport authority boundaries (membrane
   character; NOT URLs).
2. Routes expose system permeability NOT merely accessible URLs
   (aggregate = permeability profile).
3. permission_callback governs authority permeability (3rd
   security ontology surfacing in plugin-dev).
4. Args schemas = transport ABI contracts (multi-boundary ABI:
   runtime / persistence / transport).
5. Namespaces = federated authority jurisdictions (vendor/v1).
6. REST = inter-system authority diplomacy (federation external).
7. Transport declaration ≠ exposure ≠ trust (KB-recurring axis
   at transport layer).
8. REST completes origin/persistence/transport triad
   (authority lifecycle closed before entity schema).

**Security ontology — 3 surfacings = cross-cutting chunk
JUSTIFIED:**

| chunk | security boundary | mechanism |
|---|---|---|
| register-block-bindings-source | trust (origin) | general framing |
| register-meta | legitimacy (shape + access) | sanitize_callback + auth_callback |
| **register-rest-route** | **permeability (transport)** | **permission_callback** |

3 surfaces compose into layered security model:
- **Trust** — does actor have legitimate origin authority?
- **Legitimacy** — is authority shape + access correct?
- **Permeability** — may authority cross this boundary?

**3-surfacing threshold MET.** `plugin-dev.security-boundaries`
cross-cutting chunk now structurally justified.

**Multi-boundary ABI architecture (plugin-dev domain identity
deepened):**

| boundary | ABI mechanism |
|---|---|
| runtime (cascade) | CSS variables (`--wp--preset--*`) |
| persistence | meta keys + schema (register_meta) |
| transport | route + args schema (register_rest_route) |

Plugin-dev = multi-boundary ABI architecture.

**KB-level framing extension (plugin-dev domain identity):**

> Plugin-dev bounded context is the **external authority
> architecture** layer of WordPress, structured as a
> **federation stack** (origin / persistence / transport /
> entity) with **multi-boundary ABI declarations**
> (runtime / persistence / transport contracts) and
> **layered security governance** (trust / legitimacy /
> permeability gates).

**Recommended next: `plugin-dev.security-boundaries`** (R1.5
per user strategic guidance):

User's strategic recommendation: write security-boundaries
BEFORE register_post_type:
- Reasons: tone lock plugin-dev (governed extensibility),
  formalize security doctrine before CPT, CPT then reads as
  governed entity schema (not classic procedural API).

Sequence:
1. **R1.5: plugin-dev.security-boundaries** ← NEXT
   (cross-cutting synthesis of 3 surfacings)
2. R2: plugin-dev.register-post-type (entity schema, federation
   4/4)
3. Other plugin-dev families on demand
4. Other bounded contexts (additive)

**KB total chunk count update (post-register-rest-route):**

After plugin-dev.register-rest-route, KB has:
- block-authoring (substantively closed): ~32 + 3 retro patches
- theme-config (substantively closed): ~14
- style-engine (CLOSED): 4 + selectors
- data-layer (substrate sealed): 2
- interactivity (BACKBONE SEALED): 3
- plugin-dev (3 chunks, federation 3/4): 3
- _meta: 1 (dsl-spec)
- 5 remaining additive bounded contexts: 0

Total ≈ 65 chunks across 6 of 11 bounded contexts.

---

**plugin-dev.security-boundaries COMPLETED — plugin-dev capstone
(2026-05-09):**

Plugin-dev bounded context capstone. Governance doctrine synthesis
(NOT an API chunk). First KB chunk using `status: stable` for
**doctrine** rather than for API.

**8 invariants (final order, escalation flow):**

1. Security governs authority across federation layers, NOT
   isolated APIs (macro doctrine)
2. Every new authority surface expands attack surface
   (extension cost)
3. Trust / legitimacy / permeability form distinct security
   questions (3-tier model formalized)
4. Registration is governance declaration, NOT security
   completion (registered ≠ secured)
5. Core supplies security membranes; plugins remain responsible
   for doctrine execution (hybrid responsibility constitution)
6. Schema, capability, and transport each enforce different
   security dimensions (mechanism-dimension orthogonality)
7. Security is cross-boundary authority arbitration (KB-wide
   capstone symmetry)
8. Governed extensibility requires distributed security
   literacy (architecture participation)

**KB capstone symmetry across 3 bounded contexts:**

| bounded context | capstone | character |
|---|---|---|
| style-engine | cascade-aggregation | authority arbitration |
| interactivity | hydration | authority continuity |
| **plugin-dev** | **security-boundaries** | **authority governance** |

Three bounded contexts, three capstones, three different
authority concerns at operational level. KB now has a
structural pattern: each major bounded context reaches a
capstone that synthesizes internal chunks into single
architectural doctrine.

**Stable doctrine / evolving mechanisms distinction:**

First chunk to introduce this distinction. The 3-tier security
model + federation governance scope + mechanism-dimension
orthogonality + asymmetric responsibility distribution = stable
doctrine. WordPress mechanisms evolve, but doctrine framework
remains constant.

This is significant for KB epistemic maturity. DSL spec (which
defined `evolving = mechanism-evolution`) accommodates: this
chunk's mechanisms are evolving (verification-needed catalog
enumerates), but doctrine is stable.

**Security debt + negative-space security framings established:**

- **Security debt**: when registered authority surface has
  weak/missing governance, the gap is architectural debt.
  Accumulates as surfaces multiply.
- **Negative-space security**: WordPress's defaults are often
  PERMISSIVE, not restrictive. Absence of doctrine is itself
  an architectural event with security consequences.

**Asymmetric responsibility distribution formalized:**

| actor | provides |
|---|---|
| Core | security membranes (REST infrastructure, capability model, nonce framework, sanitize/escape primitives, hook system) |
| Plugins / Themes | doctrine implementation (callbacks, sanitization logic, capability checks, intent declarations, output escaping) |
| Users / Roles | capability membership |

> Core provides INFRASTRUCTURE. Plugins/themes provide INTENT
> and ENFORCEMENT. Users/roles provide IDENTITY context.
> None of the three alone produces security; security emerges
> from correct composition.

**KB-level framing extension:**

> KB evolves from **"authority architecture atlas"** into
> **"authority + governance atlas"**.

> **In WordPress, extensibility without governance is not
> flexibility — it is unmanaged authority proliferation.**

This is plugin-dev bounded context's tone-lock. All subsequent
plugin-dev chunks reference this doctrine rather than
re-deriving security framing.

**CPT pre-framing payoff established:**

After this chunk, register_post_type reads as **"governed
entity authority schema"** rather than "content modeling API":
- capability_type + map_meta_cap = capability schema
- public + show_in_rest = exposure governance
- supports = authoring-access dimension
- CPT itself = new authority subject in federation

**Antipattern catalog (12 critical confusions documented):**

Including the user-suggested additions:
- show_in_rest ≠ safe public API (5th)
- nonce ≠ authorization (6th)
- security debt acceptance (12th)

**Recommended next: `plugin-dev.register-post-type`** —
closes federation stack 4/4 + immediately exercises this
chunk's doctrine on a classic API. The pre-framing payoff
makes CPT chunk much stronger than it would have been
without security-boundaries first.

**KB total chunk count update (post-security-boundaries):**

After plugin-dev.security-boundaries:
- block-authoring (substantively closed): ~32 + 3 retro
- theme-config (substantively closed): ~14
- style-engine (CLOSED): 4 + selectors
- data-layer (substrate sealed): 2
- interactivity (BACKBONE SEALED): 3
- **plugin-dev (capstone reached): 4** (3 federation chunks +
  1 doctrine capstone)
- _meta: 1

Total ≈ 66 chunks. plugin-dev now has 4 chunks; reaching
parity with interactivity (3 chunks) + style-engine internal
backbone (4 chunks).

**plugin-dev backbone status:**

```
plugin-dev (after security-boundaries):
   register-block-bindings-source  → trust / origin           ✓
   register-meta                   → legitimacy / persistence  ✓
   register-rest-route             → permeability / transport  ✓
   security-boundaries             → governance doctrine       ✓
   ↓
   (NEXT) register-post-type       → entity / federation 4/4
```

After register-post-type, plugin-dev federation stack is
fully complete. Subsequent plugin-dev chunks (capabilities,
nonces, taxonomy, settings, slotfills, hooks) extend within
the established doctrine.

**plugin-dev.register-post-type COMPLETED — federation stack
4/4 (2026-05-09):**

Plugin-dev federation stack STRUCTURALLY COMPLETE. CPT framed
as **entity constitution registration** — most classic WP API
modern-reframed via security doctrine.

**plugin-dev federation stack — FINAL state:**

```
plugin-dev (all federation layers complete):
   register-block-bindings-source  → trust / origin           ✓
   register-meta                   → legitimacy / persistence  ✓
   register-rest-route             → permeability / transport  ✓
   security-boundaries             → governance doctrine       ✓
   register-post-type              → entity / federation 4/4   ✓
```

**8 invariants established (key ones):**

1. CPTs declare new authority-bearing entity classes NOT
   content labels (load-bearing reframe).
2. Entity registration federates new subjects into authority
   graph (full inheritance: entity-resolution / persistence /
   bindings / REST).
3. Capability schemas govern entity-specific authority rights
   (capability_type + map_meta_cap = namespace + translation
   doctrine).
4. Exposure flags create multi-dimensional declaration ≠
   exposure (most-multidimensional KB instance of recurring
   axis).
5. Supports declare operational authority modules NOT cosmetic
   features (each support = specific authority module
   attachment with UI/capability/schema/REST implications).
6. Routing flags federate entities into public address space
   (public-facing authority declaration).
7. CPTs are governed constitutions NOT mere schema extensions
   (security doctrine direct application: trust / legitimacy /
   permeability projected onto CPT decisions).
8. register_post_type closes plugin-dev's authority federation
   stack.

**KB-level framing extension (plugin-dev complete identity):**

> If `register_meta` governs **what authority may persist**,
> and `register_rest_route` governs **how authority may travel**,
> then `register_post_type` governs **what kinds of authority-
> bearing subjects may exist**.

This three-API trio (with security-boundaries as cross-cutting
doctrine) constitutes plugin-dev's authority federation
extensibility surface.

**Classic API → modern reframing payoff achieved:**

Without security-boundaries written first, register_post_type
would have anchored plugin-dev as classic content modeling.
With doctrine-first sequence, CPT reads as **entity
constitution registration in federated authority architecture**.

The strategic value of security-boundaries-before-CPT sequence
(per user guidance) is now realized. plugin-dev bounded context
tone is locked.

**KB-wide ontology synthesis after plugin-dev closure:**

KB now documents:
- **Internal authority** (block-authoring + theme-config +
  style-engine substrate)
- **Runtime authority** (data-layer + interactivity)
- **External authority** (plugin-dev federation stack)

The ontology supports the question:
> "What does WordPress's authority architecture look like as a
> system, including how plugins extend it?"

Answer is now structurally complete.

**Recommended next chunks (priority):**

1. **`plugin-dev.register-taxonomy`** — classification
   federation hooks. Natural pair with CPT; immediate
   composition (CPT taxonomies array).

2. **`plugin-dev.capabilities-and-roles`** — capability model
   formalization. Referenced extensively in CPT chunk;
   warranted as dedicated chunk.

3. **`plugin-dev.nonces`** — CSRF mechanism deeper dive.

4. Other plugin-dev families (block filters / slotfills /
   hooks / settings).

5. Other bounded contexts (additive): editor-customization /
   site-building / i18n / build-tooling / admin-ui.

Recommended sequence: register-taxonomy (immediate composition,
closes entity-classification federation). Then choice between
deepening plugin-dev (capabilities / nonces / families) or
entering additive bounded contexts.

**KB total chunk count update (post-register-post-type):**

After plugin-dev.register-post-type:
- block-authoring (substantively closed): ~32 + 3 retro
- theme-config (substantively closed): ~14
- style-engine (CLOSED): 4 + selectors
- data-layer (substrate sealed): 2
- interactivity (BACKBONE SEALED): 3
- **plugin-dev (FEDERATION STACK CLOSED): 5** (4 federation
  chunks + 1 doctrine capstone)
- _meta: 1

Total ≈ 67 chunks across 6 of 11 bounded contexts.

**Achievement summary (post-plugin-dev-closure):**

- 6 bounded contexts with substantial structural depth (4 of
  them with full backbone closure: block-authoring, theme-
  config, style-engine, plugin-dev).
- 3 KB-wide capstones documented (cascade-aggregation /
  hydration / security-boundaries) with explicit symmetry.
- 3 retroactive reframings completed (wrapper-attributes /
  dynamic-rendering / markup-representation).
- DSL spec sync'd with all accumulated conventions.
- Authority ontology atlas substantially complete.

**plugin-dev.register-taxonomy COMPLETED — semantic federation
extension (2026-05-09):**

plugin-dev semantic federation extension. CPT + taxonomy = subject
+ semantic constitutions. plugin-dev domain identity reaches
final form.

**plugin-dev — extended federation + semantic stack:**

```
plugin-dev (after taxonomy):
   register-block-bindings-source  → trust / origin            ✓
   register-meta                   → legitimacy / persistence   ✓
   register-rest-route             → permeability / transport   ✓
   security-boundaries             → governance doctrine        ✓
   register-post-type              → entity / subject species   ✓
   register-taxonomy               → semantic / classification  ✓
```

**8 invariants (sequential framing — escalation):**

1. Taxonomies declare classification authority systems NOT
   labels (load-bearing reframe).
2. Taxonomies federate semantic order into authority graph
   (terms become entities; assignments become relationships).
3. Hierarchical vs non-hierarchical = distinct classification
   topologies (TREE vs FLAT MESH, doctrine NOT UI choice).
4. Object-type attachment = semantic permeability across entity
   classes (shared taxonomies = relationship-centric).
5. Taxonomy capabilities separate classification governance
   from assignment rights (assign_terms ≠ manage_terms — often
   missed by classic plugin-dev; load-bearing security
   distinction).
6. Taxonomy routing = semantic structures entering public
   addressability.
7. Taxonomies shift plugin-dev entity-centric → semantic graph
   federation (KB-WIDE PATTERN RECURRENCE with style-engine).
8. register_taxonomy extends federation stack to constitutional
   semantic systems.

**KB-level framing extension:**

> If custom post types govern **what authority-bearing subjects
> may exist**, then taxonomies govern **how those subjects may
> be semantically ordered, grouped, and traversed**.

**KB-WIDE PATTERN RECURRENCE: entity → relationship pivot**

The entity-centric → relationship-centric pivot now appears in
TWO bounded contexts:

| bounded context | entity-centric | relationship-centric |
|---|---|---|
| **style-engine** | block instance / preset / variable | generated selectors / cascade graph / topology |
| **plugin-dev** | CPT (subject species) | shared taxonomies / cross-entity semantic links |

The recurrence suggests **entity → relationship is a structural
pattern in WordPress's ontology evolution**, not isolated to
style-engine. Anticipated similar pivots:
- editor-customization (block trees → relationship hooks)
- site-building (templates → composition graphs)
- interactivity (per-namespace stores → cross-store
  coordination)

This pattern recognition is itself a KB-level framing
contribution — recurring structural pattern identified across
bounded contexts.

**plugin-dev domain identity — FINAL FORM:**

> Plugin-dev bounded context is the **external authority +
> semantic architecture** layer of WordPress, structured as a
> **federation + classification stack** (origin / persistence /
> transport / entity / semantic) with **multi-boundary ABI
> declarations** + **layered security governance** + **graph-
> federation expressiveness**.

Subsequent plugin-dev chunks (capabilities / nonces / hooks /
filters / slotfills / settings) extend WITHIN this structurally
complete identity.

**Recommended next chunks:**

1. **`plugin-dev.capabilities-and-roles`** — capability model
   formalization. NOW STRONGLY WARRANTED (CPT + taxonomy both
   reference capability model heavily). Doctrine-tier chunk
   parallel to security-boundaries.

2. **`plugin-dev.nonces`** — CSRF mechanism deeper dive.

3. **Other plugin-dev families** — block filters / slotfills /
   hooks / settings.

4. **Other bounded contexts** (additive): editor-customization /
   site-building / i18n / build-tooling / admin-ui.

5. **KB audit / cross-ref completeness** — at 68+ chunks,
   ontology audit warranted.

Recommended next: **capabilities-and-roles** (closes capability
model formalization with concrete CPT + taxonomy examples; the
mechanism is referenced enough across chunks that dedicated
treatment is timely).

**KB total chunk count update (post-register-taxonomy):**

After plugin-dev.register-taxonomy:
- block-authoring (substantively closed): ~32 + 3 retro
- theme-config (substantively closed): ~14
- style-engine (CLOSED): 4 + selectors
- data-layer (substrate sealed): 2
- interactivity (BACKBONE SEALED): 3
- plugin-dev (FEDERATION + SEMANTIC COMPLETE): 6
- _meta: 1

Total ≈ 68 chunks across 6 of 11 bounded contexts.

---

**plugin-dev.capabilities-and-roles COMPLETED — adjudication
constitution doctrine (2026-05-09):**

Second doctrine-tier chunk in plugin-dev (paired with
security-boundaries). plugin-dev = **first dual-doctrine
bounded context**. Frames capabilities-and-roles as
**adjudication constitution** (constitutional substrate by
which authority legitimacy is decided per request).

**4-layer authority constitutional model formalized:**

```
Layer 1: Primitive capabilities      ── atomic authority rights
Layer 2: Meta capabilities           ── contextual authority questions
Layer 3: Mapping (map_meta_cap)      ── adjudication compiler
Layer 4: Roles                        ── deployment defaults
Runtime adjudication                  ── EXECUTION SURFACE (NOT ontology layer)
```

Runtime adjudication explicitly distinguished from constitutional
layers: `current_user_can` etc. = constitutional execution
QUERIES, NOT the constitution itself.

**One-line backbone (KEY INVERSION, triple-surfaced):**

> **In WordPress, roles distribute authority defaults,**
> **but capabilities constitutionally adjudicate authority**
> **legitimacy.**

Surfaced in: WHEN opener + invariant #2 + META.

**8 invariants (load-bearing ones):**

1. Capabilities are primitive authority units, NOT user labels
2. **Roles aggregate authority bundles, NOT govern authority
   directly** (LOAD-BEARING INVERSION)
3. Meta capabilities express contextual authority questions
4. map_meta_cap = adjudication compiler (KB-wide arbitration
   compiler pattern: style-engine cascade-aggregation = CSS
   arbitration; **map_meta_cap = capability adjudication**)
5. Capability systems decouple WHO from WHAT (mediating
   through bundles + contextual translation)
6. Capability checks = architecture-level governance
   (NOT UI hints)
7. All plugin-dev APIs federate INTO capability constitution
8. Capabilities-and-roles = WordPress's authority adjudication
   substrate (cross-KB scope)

**Three-layer terminology hierarchy formalized in plugin-dev:**

| layer | concern | chunk |
|---|---|---|
| **Governance** | policy decisions about authority | security-boundaries |
| **Constitution** | rights framework defining authority | capabilities-and-roles |
| **Adjudication** | operational interpretation per request | runtime queries (current_user_can / permission_callback / etc.) |

**KB capstone symmetry — extended to 4 instances:**

| bounded context | doctrine / capstone | authority problem |
|---|---|---|
| style-engine | cascade-aggregation | arbitration |
| interactivity | hydration | continuity |
| **plugin-dev** | **security-boundaries** | **governance** |
| **plugin-dev** | **capabilities-and-roles** | **adjudication** |

**plugin-dev = first dual-doctrine bounded context.** Reflects
plugin-dev's role as external federation requiring BOTH
governance AND adjudication doctrines (internal contexts
operate within core's authority; plugin-dev introduces
external authority).

**KB-WIDE PATTERN — Arbitration Compiler:**

Second instance of "arbitration compilation" pattern across
KB:

| bounded context | arbitration compiler |
|---|---|
| style-engine | cascade-aggregation (CSS authority) |
| **plugin-dev** | **map_meta_cap (capability authority)** |

> **Arbitration compilation is a recurring structural solution
> in WordPress's authority architecture** — wherever multiple
> authority claims must be reconciled into a single decision,
> an arbitration compiler emerges.

This pattern recognition is itself a KB-level framing
contribution (parallel to entity → relationship pivot
recurrence noted in taxonomy chunk).

**Stable doctrine / evolving mechanisms distinction reinforced:**

Second chunk explicitly using this distinction (after security-
boundaries). KB epistemic vocabulary mature:
- `status: stable` for doctrine
- VERIFICATION NEEDED catalog for mechanism specifics
- Distinction documented in DSL spec

**plugin-dev domain identity — fully crystallized:**

> Plugin-dev bounded context = **external authority + semantic
> + adjudication architecture** layer of WordPress.
>
> Federation stack (origin / persistence / transport / entity /
> semantic) + paired doctrines (governance + adjudication
> constitution) + multi-boundary ABI declarations + cross-
> cutting capability substrate = the complete extensibility
> system through which external actors federate authority
> into WordPress under constitutional governance.

**Cross-KB constitutional substrate:**

This chunk's scope spans bounded contexts (capability checks
appear in block-authoring / theme-config / data-layer /
interactivity / plugin-dev / site-building / admin-ui).
Lives in plugin-dev folder for organizational fit but
semantics apply KB-wide.

**Recommended next chunks:**

1. **`plugin-dev.nonces`** — CSRF protection. Complementary
   security primitive; orthogonal to capabilities. Closes
   plugin-dev's core security primitive trio: capabilities +
   nonces + sanitize/escape.

2. **Other plugin-dev families** — block filters / slotfills /
   hooks / settings.

3. **Other bounded contexts** (additive): editor-customization /
   site-building / i18n / build-tooling / admin-ui. Now have
   complete plugin-dev foundation.

4. **KB audit / cross-ref completeness** — at 69+ chunks,
   ontology audit warranted.

Recommended next: **nonces** (closes core security primitive
trio) OR enter additive bounded context (editor-customization
or i18n). User direction.

**KB total chunk count update (post-capabilities-and-roles):**

After plugin-dev.capabilities-and-roles:
- block-authoring (substantively closed): ~32 + 3 retro
- theme-config (substantively closed): ~14
- style-engine (CLOSED): 4 + selectors
- data-layer (substrate sealed): 2
- interactivity (BACKBONE SEALED): 3
- **plugin-dev (DUAL-DOCTRINE COMPLETE): 7** (5 federation
  chunks + 2 doctrine chunks)
- _meta: 1

Total ≈ 69 chunks across 6 of 11 bounded contexts.

**Achievement summary update (post-dual-doctrine):**

- 6 bounded contexts substantial structural depth
- 4 KB-wide capstones (with plugin-dev paired doctrines)
- 3 retroactive reframings completed
- 2 KB-wide structural patterns recognized:
  - Entity → relationship pivot (style-engine + plugin-dev)
  - Arbitration compiler (style-engine + plugin-dev)
- DSL spec sync'd
- Authority ontology atlas + adjudication constitution complete

---

**KB AUDIT COMPLETED — Phase 7 closure (2026-05-09):**

`_meta/kb-audit-phase7.md` written. 5-section audit (A-E):
A. Bounded context closure matrix
B. Cross-reference integrity audit
C. Pattern verification (most valuable section)
D. DSL health
E. Expansion roadmap recalibration

**Key findings:**

**KB structural health: STRONG.**

**Pattern verification — 6 KB-WIDE LAWS confirmed** (initially
claimed 2; audit verified 6 universal patterns):

1. Declaration ≠ exposure (most-recurring; 8+ instances)
2. HTML primacy (spec doctrine)
3. Authority continuity (spec glossary + capstone)
4. Arbitration compiler (4+ instances; broader than initially
   claimed — also in data-layer.persistence + interactivity.
   hydration)
5. Entity → Relationship pivot (2+ explicit + spec)
6. Compiler ↔ Runtime split (symmetry table)

**Updated finding: 6 laws (not 2).** Audit revealed broader
recurrence than initially claimed — particularly Arbitration
compiler appears in 4+ chunks, not just style-engine +
plugin-dev.

**Status field health:**
- 9 evolving / 55 stable (~14% / 85%)
- Distribution matches spec criterion
- "stable doctrine / evolving mechanisms" distinction
  operationally enforced

**Cross-reference health:**
- All 7 post-DSL-sync chunks honor spec
- No orphaned chunks
- 1 minor maintenance: security-boundaries cross-refs (DONE
  during audit — register-post-type + capabilities-and-roles
  + register-taxonomy added without (planned) marker)

**Expansion roadmap recalibrated** (5 remaining additive
contexts):

| priority | context |
|---|---|
| 1 | editor-customization (medium-high ontology weight) |
| 2 | site-building (bridges into runtime perspective) |
| 3 | i18n (cross-cutting substrate, low risk) |
| 4 | admin-ui (practical with governance) |
| 5 | build-tooling (most self-contained) |

Optional intermixed:
- plugin-dev.nonces
- block.inner-blocks retro
- block.deprecation retro
- _meta/structural-patterns.md (HIGH LEVERAGE)

**Audit conclusion:**

> KB now operates as **constitutional ontology atlas** —
> matured from documentation system through ontology atlas
> to constitutionally coherent system with verified universal
> laws, paired doctrines, explicit pattern recurrence.

**Recommended next: structural-patterns.md** (formalize 6
verified KB-wide laws as shared vocabulary document — high
leverage for future bounded context entries).

**Total chunks post-audit:** 64 substantive + 2 _meta
(dsl-spec + kb-audit-phase7) = 66 .md files. Anticipated
structural-patterns.md adds 67th.

---

**KB CONSTITUTIONAL LAW LAYER COMPLETED — structural-patterns.md
(2026-05-09):**

Third _meta document. Formalizes 6 verified KB-WIDE LAWS into
**constitutional law layer** — PRESCRIPTIVE complement to
descriptive audit.

**KB infrastructure layer now COMPLETE:**

```
KB infrastructure (_meta):

   dsl-spec               → KB OPERATING SYSTEM
                            (how chunks are structured)
   kb-audit-phase7        → VERIFICATION ARTIFACT
                            (do patterns exist? — descriptive)
   structural-patterns    → CONSTITUTIONAL LAW LAYER
                            (how SHOULD KB use patterns? — prescriptive)
```

Three documents form KB's self-governance system. Substantive
chunks authored within their constraints.

**6 KB-WIDE LAWS canonicalized + grouped:**

| category | laws |
|---|---|
| **Governance / Doctrine** | Declaration ≠ Exposure / HTML Primacy |
| **Structural** | Entity → Relationship Pivot / Compiler ↔ Runtime Split |
| **Operational** | Authority Continuity / Arbitration Compiler |

Each law documented with: definition / core question / first
emergence / verified contexts / variants / anti-confusions /
predictive use heuristic.

**5 documented pattern interactions** (laws don't operate
alone):
1. Declaration ≠ Exposure × Security boundaries = governed
   declaration surfaces
2. Entity → Relationship × Arbitration Compiler = topology-scale
   authority systems
3. HTML Primacy × Authority Continuity = HTML as continuity
   substrate
4. Compiler ↔ Runtime × Authority Continuity = compiler-runtime
   authority handoff
5. Declaration ≠ Exposure × Entity → Relationship = relational
   governance

**7-question constitutional diagnostic checklist** for chunk
authoring:
1. Declaration surface? (Law 1)
2. Exposure surface? (Law 1)
3. Trust/permission surface? (Law 1)
4. Execution boundary? (Law 3)
5. Multi-source arbitration? (Law 4)
6. Entity-centric or relationship-centric? (Law 5)
7. Compiler/runtime split? (Law 6)

Plus implicit doctrine adherence (HTML primacy + authority
continuity).

**Predictive frontier section** — anticipated law manifestations
per untouched context:
- editor-customization: Laws 1, 5, 4, 3 predicted
- site-building: Laws 5, 6, 1 predicted
- i18n: Laws 3, 6 predicted
- admin-ui: Laws 1, 4, 2 predicted
- build-tooling: Law 6 predicted (less ontology-heavy)

**Constitutional protocol for new bounded contexts:**

> Each new bounded context becomes BOTH a documentation
> effort AND a constitutional law verification opportunity.
> Pre-entry: review predictive frontier. First chunk: structure
> through diagnostic checklist. Per-chunk: cite laws applied.
> Per-context closure: verify which laws manifested. Post-entry:
> update predictive frontier.

**KB-level framing extension (constitutional crystallization):**

> The KB is no longer just documenting WordPress architecture —
> it is now formalizing the constitutional laws by which that
> architecture can be interpreted, extended, and predicted.

**Stable doctrine / evolving applications distinction:**

Laws themselves are stable (constitutional invariants). Their
applications in specific bounded contexts may evolve as KB
understanding deepens. Structurally similar to "stable doctrine
/ evolving mechanisms" distinction (DSL spec /
capabilities-and-roles).

**Recommended next: editor-customization** (per user
strategic guidance — tests structural laws' predictive power
in new context):

User one-liner: "structural-patterns → editor-customization
(test predictive power)"

editor-customization is:
- Predicted to test Laws 1, 5, 4, 3
- Highest practical relevance among additive contexts
- Tests entity → relationship pivot in 3rd bounded context
  (would promote pivot from 2-instance to 3-instance verified
  recurrence)

**Total chunks post-constitutional-layer:** 64 substantive +
3 _meta (dsl-spec + kb-audit-phase7 + structural-patterns)
= 67 .md files.

**KB self-definition (final form):**

> KB = **operational ontology atlas + verification artifact +
> constitutional law layer** for WordPress/Gutenberg authority
> architecture.
>
> Substantive chunks document mechanisms; _meta layer governs
> the documentation. Together they form a self-governing
> ontology system.

---

**editor-customization.block-filters COMPLETED — constitutional
protocol first deployment (2026-05-09):**

First chunk in editor-customization bounded context AND first
chunk authored under structural-patterns constitutional protocol.

**Doctrinal backbone for editor-customization established:**

> **Plugin-dev extends authority outward;**
> **editor-customization intercepts authority inward.**

**plugin-dev (5 federation + 2 doctrines)** = external federation
**editor-customization (entry chunk)** = internal governance
modulation

The two bounded contexts close WordPress's extensibility
ontology: new authority creation + existing authority
modulation.

**Constitutional Field Test — VERDICT: SUCCESS:**

| Law | Prediction | Status |
|---|---|---|
| Law 1 — Declaration ≠ Exposure | Strong | **Confirmed (3rd-form)** |
| Law 4 — Arbitration Compiler | Strong | **Confirmed (3rd domain)** |
| Law 5 — Entity → Relationship Pivot | Moderate-Strong | **Confirmed (context-local; broader recurrence pending)** |
| Law 6 — Compiler ↔ Runtime Split | Strong | **Confirmed** |
| Law 2 — HTML Primacy | Implicit | **Confirmed (implicit doctrine adherence)** |
| Law 3 — Authority Continuity | Secondary | **Partial** |

**4 strongly-predicted laws (1, 4, 5, 6) all manifested.**
Predictive frontier section in structural-patterns correctly
anticipated editor-customization's constitutional character.

> **Constitutional protocol works.** structural-patterns has
> demonstrated predictive power for new bounded context
> entries.

**Hypothetical 7th law candidate surfaced:**

> "**Authority Interception Surface**" — mechanisms that
> intercept and reshape existing authority without owning it.

Status: **Local Pattern** (single bounded context observation).
Per Section A promotion criteria, requires recurrence in 2nd
bounded context to promote to Recurring; audit verification
to promote to KB-Wide.

Verification path:
- Test in editor-customization.slotfills (next chunk)
- Test in admin-ui (notices, settings filters)
- Test in site-building (template hierarchy filters)

**Promotion discipline preserved:** chunk did NOT promote the
candidate to law. Surfaced explicitly so future chunks can
test recurrence.

**Constitutional protocol observations:**

What worked:
- Pre-write predictions gave structural skeleton.
- Constitutional Field Test table forced explicit verification.
- Hypothetical law candidate process kept promotion discipline.

Refinement candidates for future protocol chunks:
- "Strength" rating per law (Strong / Moderate / Implicit)
- "Verification depth" notation (1-context observed /
  2-context confirmed / audit-verified)

May surface as future spec updates; not required for current
chunk validity.

**8 invariants established (key ones):**

1. Filters = authority interception surfaces (NOT cosmetic
   hooks)
2. Governance through interception, NOT ownership
3. Filter chains create governance relationships between
   actors and block definitions
4. Priority = governance arbitration (Law 4 manifestation)
5. Lifecycle boundary independence (registration / edit / save)
6. Customization is decomposable across boundaries
7. **Interception debt** (security-debt symmetry)
8. **Editor-customization tests Entity → Relationship Pivot
   3rd-context recurrence**

**Interception debt concept** — symmetry with security-debt:
each interception layer expands governance power AND
introduces divergence vectors. Mirrors plugin-dev's security
debt accumulation pattern.

**One-line backbone:**

> **Block filters do not create new authority — they intercept,**
> **arbitrate, and reshape existing authority across block**
> **lifecycle boundaries.**

**KB-level framing — full extensibility ontology:**

| pattern | character | example mechanisms |
|---|---|---|
| **External federation** (plugin-dev) | new authority surfaces | register_block_bindings_source / register_meta / register_post_type / register_taxonomy / register_rest_route |
| **Internal modulation** (editor-customization) | existing authority intercepted/reshaped | block filters (this), slotfills, editor hooks |

Both governed by capabilities-and-roles (adjudication) +
security-boundaries (doctrine); both can introduce
interception/security debt.

**KB total chunk count post-block-filters:**
- 65 substantive chunks
- 3 _meta documents (dsl-spec / kb-audit-phase7 /
  structural-patterns)
- = 68 .md files

Bounded contexts now: 7 of 11 with substantive structural
depth.

**Recommended next chunks (priority):**

1. **`editor-customization.slotfills`** — adjacent governance
   pattern. CRITICAL: first recurrence test for "authority
   interception surface" candidate. If it manifests here,
   candidate promotes from Local to Recurring.

2. **`editor-customization.editor-hooks`** — editor data
   store hooks. Closes editor-customization backbone.

3. **Other bounded contexts** (additive) — site-building /
   i18n / admin-ui / build-tooling. Constitutional protocol
   continues per chunk.

4. **`plugin-dev.nonces`** — completes plugin-dev security
   primitive trio. Self-contained; can be intermixed.

Recommended next: **`editor-customization.slotfills`** —
immediate constitutional protocol continuation + interception
surface candidate recurrence test.

---

**editor-customization.slotfills COMPLETED — FIRST PROMOTION
EVENT (2026-05-09):**

Second editor-customization chunk + 2nd constitutional protocol
deployment. **FIRST PROMOTION EVENT in KB structural-patterns
governance.**

**Doctrinal extension established:**

> **Block filters proved authority interception exists;**
> **SlotFill proves that interception recurs through multiple**
> **governance modalities.**

**8 invariants (key ones):**

1. SlotFill = named interface authority insertion points
   (NOT arbitrary UI extension)
2. SlotFills intercept editor topology through additive
   relationship governance
3. Slots declare governance surfaces; fills negotiate
   participation (two-step authority federation)
4. Registration ≠ rendered authority presence (Law 1 3-form)
5. **Editor UI = relationship-topological** (Law 5 4-context
   confirmation)
6. SlotFill extends inward governance through TOPOLOGY rather
   than LIFECYCLE mutation (modality differentiation)
7. **Topology debt** (3rd debt-pattern instance: security debt
   + interception debt + topology debt — recurring pattern)
8. **PROMOTION EVENT**: Authority Interception Surface elevated
   Local → Recurring (intra-context)

**Constitutional Field Test — DUAL TABLES introduced:**

Table A (Universal Law Manifestation, block-filters format):
| Law | Status |
|---|---|
| Law 5 — Entity → Relationship | Confirmed (4th-context, deepens) |
| Law 4 — Arbitration Compiler | Confirmed (slot-resolution variant) |
| Law 1 — Declaration ≠ Exposure | Confirmed (3-form: registration/rendering/visibility) |
| Law 6 — Compiler ↔ Runtime | Confirmed |
| Law 2 — HTML Primacy | Confirmed (implicit) |
| Law 3 — Authority Continuity | Partial |

Table B (Pattern Recurrence Verification, NEW format):
| Candidate | Prior status | Manifestation | Promotion |
|---|---|---|---|
| Authority Interception Surface | Local | Confirmed (different mechanism, same ontology) | **Promoted: Local → Recurring (intra-context)** |

**FIRST PROMOTION EVENT PROTOCOL:**
- Same structural core: mechanisms intercepting & reshaping
  existing authority without ownership
- Different governance modality: lifecycle (filters) vs
  topology (SlotFill)
- Recurrence within single bounded context
  (editor-customization)
- Promotion qualifier: "(intra-context)" — preserves
  discipline (KB-Wide requires cross-context)

**Spec refinement candidate documented:**

```
Proposed refined hierarchy (Local → KB-Wide):
   Local Pattern Surface
      ↓
   Recurring (intra-context)         ← FIRST PROMOTION HERE
      ↓
   Recurring (cross-context)
      ↓
   KB-Wide Law
```

**Recommended structural-patterns spec update**: Add
intra/cross-context distinction to Section A. Defer to user
direction.

**Subtype caution preserved:**

Current evidence suggests multiple interception modalities
(lifecycle / topology). Subtype formalization (Lifecycle
Interception / Topology Interception) **remains premature
pending broader recurrence**. If editor-hooks confirms third
modality (reactive subscriptions), 3-modality consideration
becomes timely. Until then: surface, do not formalize.

**KB-level framing extension:**

> **editor-customization is the first bounded context to not**
> **merely confirm existing constitutional laws, but to**
> **surface a plausible new governance pattern through repeated**
> **bounded-context-specific recurrence.**

editor-customization = **first law-generation capable bounded
context**.

**KB capability evolution:**
- Pre-editor-customization: KB descriptive (verifying
  external patterns)
- Post-editor-customization: KB **generative** (surfacing
  new candidate patterns through structured chunk authoring
  with promotion discipline)

**Recurring debt-pattern observation:**

3 debt instances now in KB:
- security debt (security-boundaries)
- interception debt (block-filters)
- topology debt (this chunk)

"Governance debt" itself may be recurring pattern worth
explicit naming in future audits. Documented but not promoted
to candidate yet.

**Methodological note:**

> This chunk should not just document SlotFill — it should
> demonstrate that the KB can responsibly discover, promote,
> and govern new structural patterns without collapsing its
> own evidentiary discipline.

**Verdict: SUCCESS.** KB demonstrated:
- Surface candidate patterns from chunk-level observation
- Promote within explicit discipline (Local → Recurring
  intra-context)
- Refuse premature taxonomization
- Document governance reasoning explicitly

**KB matured into governable ontology system.**

**KB total chunk count post-slotfills:**
- 66 substantive chunks
- 3 _meta documents
- = 69 .md files

**7 of 11 bounded contexts** with structural depth.
editor-customization now has 2 chunks.

**Recommended next chunks (priority):**

1. **`editor-customization.editor-hooks`** — third governance
   modality (reactive subscriptions). Tests if Authority
   Interception Surface candidate manifests in 3rd modality.
   Would close editor-customization triad (lifecycle /
   topology / reactive) and potentially trigger:
   - 3-modality consideration → subtype formalization timely
   - Spec refinement (intra/cross-context distinction)

2. **Cross-context recurrence test** — enter another bounded
   context (admin-ui notices / site-building filters) to test
   Authority Interception Surface in non-editor-customization.
   Promotion to Recurring (cross-context) depends on this.

3. **Constitutional spec update** (structural-patterns
   refinement) — add intra/cross-context distinction.

4. Other bounded contexts or plugin-dev.nonces as user
   direction determines.

Recommended sequence: `editor-customization.editor-hooks` →
constitutional spec update (if 3-modality emerges) → admin-ui
or site-building (cross-context test).

---

**editor-customization.editor-hooks COMPLETED — FIRST
MULTI-CANDIDATE ADJUDICATION EVENT (2026-05-09):**

Third editor-customization chunk + 3rd constitutional protocol
deployment + **FIRST MULTI-CANDIDATE ADJUDICATION EVENT in KB**.

**Honest hybrid finding (Possibility 3 confirmed):**

Editor-hooks span **3 distinct ontological modes**:
- **Mode 1: Direct usage** (useSelect / useDispatch) =
  **MEDIATION** (controlled access channels)
- **Mode 2: HOC patterns** (createHigherOrderComponent +
  hooks) = **INTERCEPTION** (inherited from outer HOC)
- **Mode 3: createReduxStore** = **FEDERATION** (cross-context
  recurrence with plugin-dev)

**3 candidates adjudicated simultaneously (FIRST in KB):**

| Candidate | Prior status | Editor-hooks manifestation | Outcome |
|---|---|---|---|
| Authority Interception Surface | Recurring (intra-context, 2-modality) | HOC subset CONFIRMED + direct hooks DIVERGENT | **Recurring (intra-context) MAINTAINED, NOT strengthened to 3-modality** |
| Authority Mediation Surface (NEW) | did not exist | Surfaced via direct useSelect / useDispatch | **Local Pattern Surface ("surfaced, not constitutionalized")** |
| Federation Pattern (KB-Wide, plugin-dev origin) | KB-Wide (plugin-dev) | createReduxStore = federation recurrence | **Cross-context recurrence noted (plugin-dev + editor-customization)** |

**Verdict classes used: Confirmed / Divergent / Surfaced /
Cross-context recurrence.** "Hybridized" implicit in
multi-candidate result.

**Methodological achievement:**

> **KB methodological maturity = ability to refuse clean**
> **stories when evidence demands complex ones.**

Chunk could have force-fit editor-hooks into Authority
Interception Surface (preserving 2-modality recurrence + tidy
3-modality triad). Instead documented honest divergence.

**Constitutional integrity > pattern preservation.**

**8 invariants (key ones):**

1. Editor hooks span 3 distinct ontological modes; force-fitting
   obscures architecture
2. useSelect = subscription mediation, NOT interception
3. useDispatch = action delegation mediation, NOT interception
4. HOC patterns inherit interception character from outer
   wrapper
5. createReduxStore = federation pattern recurrence
   (cross-context)
6. Reactive subscriptions create access channels distinct from
   interception
7. **Reactive debt = 4th debt-pattern instance** (governance
   debt as recurring meta-pattern, surfacing)
8. **First multi-candidate adjudication chunk in KB**
   (3 candidates evaluated simultaneously)

**Reactive debt completes 4-instance pattern recurrence:**

| chunk | debt name |
|---|---|
| security-boundaries | security debt |
| block-filters | interception debt |
| slotfills | topology debt |
| **editor-hooks** | **reactive debt** |

**"Governance debt" as recurring meta-pattern** — surfaced
across 4 chunks in 2 bounded contexts. Not promoted to
candidate yet (per discipline: surface, do not
constitutionalize).

**KB-level finding — editor-customization = MULTI-PATTERN
bounded context:**

> editor-customization is the first bounded context to host
> SIMULTANEOUSLY:
> - Pattern recurrence (Authority Interception Surface)
> - Pattern divergence (Authority Mediation Surface surfaced)
> - Pattern overlap (Federation pattern from plugin-dev
>   recurring here)
>
> This is **multi-pattern governance heterogeneity** within a
> single bounded context.

KB has explicit evidence that bounded contexts can be
ontologically rich (multiple patterns) rather than monothematic.

**Anticipated future triad (per user observation):**

If Authority Mediation Surface manifests in 2nd context
(data-layer selectors? admin-ui form hooks?), KB may
recognize:
- **Interception** = authority reshaping
- **Mediation** = authority access choreography
- **Federation** = authority origination

Three parallel governance classes. **Anticipated, NOT
constitutionalized** — verification path requires multiple
cross-context observations.

**Editor-customization triad COMPLETE:**

```
editor-customization (after this chunk):
   block-filters       → lifecycle interception        ✓
   slotfills           → topology interception         ✓
   editor-hooks        → reactive mediation +          ✓
                         HOC interception subset +
                         federation recurrence
   ↓
   BOUNDED CONTEXT TRIAD COMPLETE.
```

**Constitutional spec update NOW URGENT:**

Multi-candidate adjudication revealed structural-patterns
spec needs refinement:
1. Add intra/cross-context distinction to Section A (deferred
   since slotfills; **now urgent**)
2. Add "Surfaced" status alongside Local/Recurring promotions
3. Add "Hybridized" or "Multi-modal" verdict class
4. Document multi-pattern bounded context as recognized
   phenomenon

**Recommended sequence:**

1. **Constitutional spec update** (structural-patterns
   refinement) — accumulated 2-chunks of refinement candidates;
   multi-candidate adjudication requires formalization.

2. **Cross-context test for Authority Mediation Surface** —
   data-layer selectors / admin-ui form hooks. Could promote
   Mediation candidate to Recurring (cross-context).

3. **admin-ui** OR **site-building** entry (additive bounded
   context + cross-context mediation test).

4. **plugin-dev.nonces** as intermixing option.

Recommended next: **structural-patterns spec update FIRST** —
spec lag became urgent with multi-candidate adjudication; spec
formalization should precede further additive bounded context
entries.

**KB total chunk count post-editor-hooks:**
- 67 substantive chunks
- 3 _meta documents (dsl-spec / kb-audit-phase7 /
  structural-patterns)
- = 70 .md files

**7 of 11 bounded contexts** with substantive depth.
editor-customization triad complete (3 chunks).

**Achievement summary (post-multi-candidate-adjudication):**

- 7 bounded contexts substantial structural depth
- 4 KB-wide capstones + paired doctrines
- 3 retroactive reframings completed
- KB infrastructure layer complete (3 _meta docs)
- 2 structural patterns at law-candidate stage
  (Authority Interception Surface = Recurring intra-context;
  Authority Mediation Surface = Local Surfaced)
- 1 cross-context pattern recurrence noted (Federation in
  plugin-dev + editor-customization)
- 4-instance debt pattern recurrence surfaced
- editor-customization = first MULTI-PATTERN bounded context

**KB matured into: governable + heterogeneous + adjudicating
ontology system.**

---

**Phase 7.5 Constitutional Refinement Patch APPLIED to
structural-patterns.md (2026-05-09):**

After editor-customization triad's multi-candidate adjudication,
spec lag was urgent. 5 changes applied:

**1. Pattern maturity ladder (3-tier → 5-tier):**
Surfaced → Local → Recurring (intra-context) → Recurring
(cross-context) → KB-Wide

**2. Verdict taxonomy (3-class → 5-class):**
Confirmed / Divergent / Hybridized / Surfaced / Deferred

**3. New Section C.5 — 4 Constitutional doctrines:**
- Doctrine 1: Multi-pattern bounded context
- Doctrine 2: Candidate structural complement
- Doctrine 3: Epistemic Integrity
- Doctrine 4: Anticipated constitutional architecture
  (Interception/Mediation/Federation triad — anticipated NOT
  constitutionalized)

**4. Section D — Adjudication question Q8 added:**
> Does this chunk Confirm, Diverge from, Hybridize, or Surface
> a candidate law?

**5. META — Constitutional Refinement Patch chronology** added
with Phase 7.5 entry + Phase 7.6/7.7/8 anticipated patches
documented.

**Patch principle:** "Ontology evolved faster than constitution.
Phase 7.5 patch corrects the lag."

**Spec sync now CURRENT with editor-customization findings.**

**KB total: 70 .md files** (67 substantive + 3 _meta).

**Recommended next: admin-ui entry** (cross-context Mediation
test for Authority Mediation Surface candidate promotion).

---

**admin-ui.settings-api COMPLETED — Cross-context Mediation
test + Phase 7.5 first deployment (2026-05-09):**

First admin-ui chunk + first substantive chunk under Phase 7.5
patched constitutional framework. Cross-context test for
Authority Mediation Surface candidate.

**Doctrinal backbone established:**

> Editor hooks surfaced authority mediation inside reactive
> governance; Settings API tests whether mediation recurs
> across administrative governance through capability-gated
> persistence orchestration.

**Phase 7.5 patched framework FULL deployment (3 explicit
acknowledgments):**

1. ✅ Patched verdict taxonomy deployed (5-class)
2. ✅ Patched maturity ladder applied (5-tier with cross-context
   PRESENCE distinction)
3. ✅ Q8 adjudication doctrine operationalized

This chunk = **reference exemplar for Phase 7.5 patched spec
compliance**. Future chunks may reference this META structure.

**8 invariants (key ones):**

1. Settings API mediates authority through governed persistence
   channels NOT raw storage (load-bearing reframe)
2. Authority crosses multiple mediation boundaries per request
   (7-stage mediation pipeline)
3. register_setting = persistence ABI declaration (parallel to
   register_meta)
4. **4-form Declaration ≠ Exposure** (registration / menu / render
   / REST — most-multidimensional in admin-ui)
5. Settings API IS persistence reconciliation at admin layer
   (shares data-layer.persistence substrate)
6. Settings groups create relationship topology (Law 5
   admin-topology variant)
7. **Settings debt = 5th debt-pattern instance** (first outside
   editor/plugin-dev-heavy zones)
8. **Authority Mediation Surface CROSS-CONTEXT PRESENCE
   confirmed**

**Constitutional Field Test results:**

Table A (Universal Law Manifestation) — All 5 predicted laws
Confirmed:
- Law 1 — Declaration ≠ Exposure: Confirmed (most-multidimensional)
- Law 4 — Arbitration Compiler: Confirmed (admin-arbitration variant)
- Law 3 — Authority Continuity: Confirmed
- Law 6 — Compiler ↔ Runtime Split: Confirmed
- Law 5 — Entity → Relationship Pivot: Confirmed (admin-topology)
- Law 2 — HTML Primacy: Confirmed (implicit)

Table B (Pattern Recurrence / Divergence Verification):
| Candidate | Prior | Manifestation | Outcome |
|---|---|---|---|
| Authority Mediation Surface | Surfaced (editor-customization) | Strong (sanitize+capability+nonce+form pipeline) | **Local (admin-ui); CROSS-CONTEXT PRESENCE confirmed; NOT yet Recurring (cross-context)** |
| Authority Interception Surface | Recurring (intra-context, editor-customization) | Weak/secondary | **Divergent — admin-ui is mediation domain, not interception** |
| Federation Pattern | KB-Wide (plugin-dev) | Plugin settings registration | **Confirmed (cross-context recurrence continued)** |

**PROMOTION DISCIPLINE PRESERVED:**

> **Authority Mediation Surface NOT promoted to Recurring
> (cross-context).** Evidence base: 2 contexts × 1 chunk each
> = breadth without depth. Recurring (cross-context) requires
> structural density.

**Spec-grade doctrine articulated** (worth Phase 7.6 patch
consideration):

> **Presence across contexts is not yet recurrence across**
> **contexts unless recurrence exhibits structural density**
> **beyond isolated manifestation.**

**Phase 7.6 refinement candidate observed (NOT yet patched):**

```
Possible ladder nuance:
   Surfaced → Local → CROSS-CONTEXT PRESENCE (NEW tier?)
   → Recurring (intra-context) → Recurring (cross-context)
   → KB-Wide
```

Status: **Observed constitutional granularity gap.** Do NOT
patch yet — single observation; sustained pattern needed.

**KB-level framing extension (admin-ui domain identity):**

> admin-ui = administrative governance modulation bounded
> context. Analogous to editor-customization's internal
> governance modulation but operating on PERSISTENCE +
> CAPABILITY axes (vs editor-customization's lifecycle /
> topology / reactive axes).

Domain identity anchor: **capability-gated persistence
mediation.**

**Debt pattern broadens:**

5 debt instances × 3 bounded contexts (plugin-dev +
editor-customization + admin-ui). "Governance debt" continues
strengthening as anticipated meta-pattern (NOT yet promoted).

**KB total post-settings-api:**
- 68 substantive chunks
- 3 _meta documents (Phase 7.5 patched structural-patterns)
- = 71 .md files

**8 of 11 bounded contexts** with structural depth.

**Recommended next chunks:**

1. **`admin-ui.admin-menus`** — second admin-ui chunk.
   Mediation density test within admin-ui (could promote
   Mediation Local → Recurring intra-context if exhibits
   mediation character).

2. **`admin-ui.notices`** — admin_notices hook may exhibit
   interception OR mediation OR hybrid character (interesting
   test).

3. **`site-building`** — composition-heavy bounded context;
   different test surface.

4. **`plugin-dev.nonces`** — intermixing option.

Recommended sequence: **admin-ui.admin-menus** (continue
admin-ui density build for potential Mediation Recurring
intra-context promotion) → potentially admin-ui.notices →
then site-building or other contexts.

**Achievement summary post-cross-context-presence:**

- 8 bounded contexts substantial depth
- 4 KB-wide capstones + paired doctrines
- 3 retroactive reframings
- KB infrastructure complete with Phase 7.5 patch
- Authority Mediation Surface: cross-context PRESENCE confirmed
- 5 debt instances across 3 bounded contexts
- editor-customization + admin-ui both demonstrating
  governance modulation (mediation domain expanding)

**KB matured to: governance-modulation-aware ontology system.**

---

**admin-ui.admin-menus COMPLETED — SECOND PROMOTION EVENT +
Multi-pattern bounded context validation (2026-05-09):**

Second admin-ui chunk + Phase 7.5 framework second deployment.
**SECOND PROMOTION EVENT in KB.** Validates Multi-pattern
bounded context doctrine in 2nd context.

**Doctrinal extension:**

> Settings API governs administrative persistence mediation;
> Admin Menus also confirm administrative governance through
> capability-routed interface constitution (mediation +
> routing topology + arbitration overlap).

**SECOND PROMOTION EVENT — Authority Mediation Surface:**

```
Authority Mediation Surface candidate:
   Pre-this-chunk: Local (admin-ui, 1 chunk: settings-api)
                   + cross-context PRESENCE
   Post-this-chunk: PROMOTED Local → Recurring (intra-context)
                    within admin-ui
                    (2 chunks density: settings-api + admin-menus)
```

Promotion criteria met: 2 chunks within bounded context with
shared structural core (capability-gated authority access
channels). Phase 7.5 5-tier ladder operates correctly.

**NEW candidate surfaced: Administrative Routing Surface**

> Governance through hierarchical capability-gated navigation
> topology where authority becomes NAVIGABLE rather than INSERTED.

Status: **Surfaced** (Local Pattern, 1st observation).
"Surfaced, not constitutionalized."

Distinct from SlotFill's injection topology — admin menus is
ROUTING topology (where authority becomes navigable), not
INJECTION topology (where UI gets inserted).

**Spec-grade observation (Phase 7.6 patch consideration):**

> **Topology is insufficiently specific at current evidence**
> **density; governance character must distinguish injection**
> **from routing.**

Single observation; sustained pattern needed before patch.

**8 invariants (key ones):**

1. Admin menus = capability-routed administrative interface
   topology (NOT decorative navigation)
2. **Hybrid governance character** (Mediation + Topology +
   Arbitration overlap simultaneously)
3. Mediation density build within admin-ui (Settings API
   parallel)
4. Topology character STRUCTURALLY DISTINCT from SlotFill's
   injection topology
5. **5-form Declaration ≠ Exposure** (most multidimensional
   in KB)
6. Menu ordering = arbitration via priority + position +
   filter (5+ instances of arbitration compiler)
7. Plugin federation through admin menu registration
   (3rd-context Federation manifestation)
8. **admin-ui = SECOND multi-pattern bounded context**
   (validates Doctrine 1 beyond editor-customization)

**Constitutional Field Test results:**

Table A — All predicted laws Confirmed:
- Law 5: Confirmed (5-context manifestation depth)
- Law 4: Confirmed (5+ arbitration compiler instances)
- Law 1: Confirmed (5-form, most multidimensional in KB)
- Law 6, 3, 2: Confirmed

Table B (PROMOTION EVENT documented):
| Candidate | Outcome |
|---|---|
| Authority Mediation Surface | **PROMOTED Local → Recurring (intra-context)** within admin-ui |
| Administrative Routing Surface (NEW) | Surfaced (Local Pattern, 1st observation) |
| Authority Interception Surface | Divergent (admin-ui not interception domain) |
| Federation Pattern | Confirmed (3rd-context manifestation) |

**Multi-pattern bounded context doctrine VALIDATED:**

| bounded context | multi-pattern character |
|---|---|
| editor-customization | Interception + Mediation + Federation |
| **admin-ui** | **Mediation + Routing + Federation + Arbitration** |

2 bounded contexts confirmed multi-pattern. Doctrine 1
status reinforced.

**KB-wide pattern recurrence updates:**

Debt pattern (6 instances × 3 bounded contexts):
- security debt (plugin-dev)
- interception debt (editor-customization)
- topology debt (editor-customization)
- reactive debt (editor-customization)
- settings debt (admin-ui)
- **navigation debt (admin-ui)** ← NEW

Federation Pattern (3-context recurrence reinforced):
- plugin-dev origin
- editor-customization (createReduxStore)
- **admin-ui (plugin menu registration)**

KB-Wide Federation status reinforced.

**KB-level finding — second multi-pattern bounded context:**

> Multi-pattern character is not editor-customization-specific.
> KB has now observed it in 2 bounded contexts. Doctrine 1
> is validated as recurring structural reality.

**Topology character refinement (NOT yet patched):**

KB now distinguishes:
- Injection topology (SlotFill — UI insertion at named slots)
- Routing topology (Admin menus — capability-gated navigation)

Both surfaced as "topology" but governance character differs.
Phase 7.6 patch consideration noted; sustained pattern needed
before formalization.

**KB total post-admin-menus:**
- 69 substantive chunks
- 3 _meta documents
- = 72 .md files

**8 of 11 bounded contexts** with structural depth.
admin-ui now has 2 chunks (Mediation density established).

**Recommended next chunks:**

1. **`admin-ui.notices`** — third admin-ui mechanism. Tests
   whether admin notices exhibit interception OR mediation
   OR hybrid. Could test Routing Surface recurrence.

2. **`site-building`** — composition-heavy bounded context;
   different test surface.

3. **`plugin-dev.nonces`** — security trio completion.

4. **Phase 7.6 spec patch** consideration if topology
   distinction reaches sustained pattern.

Recommended sequence: **admin-ui.notices** (continue admin-ui
density + test Routing Surface recurrence + interesting
ontological character) OR **site-building** (horizontal
expansion + relationship-centric test surface).

**Achievement summary post-admin-menus:**

- 8 bounded contexts substantial depth
- 4 KB-wide capstones + paired doctrines
- 3 retroactive reframings
- KB infrastructure complete with Phase 7.5 patch
- **2 PROMOTION EVENTS** in KB:
  - Authority Interception Surface: Surfaced → Local (slotfills)
  - Authority Mediation Surface: Local → Recurring intra-context
    (admin-menus, this chunk)
- 2 NEW candidates surfaced (NOT promoted):
  - Authority Mediation Surface (editor-hooks)
  - Administrative Routing Surface (admin-menus)
- 6 debt instances across 3 bounded contexts
- 2 multi-pattern bounded contexts (editor-customization +
  admin-ui)
- Federation Pattern 3-context manifestation
- 5-form Declaration ≠ Exposure (most multidimensional in KB)

**KB matured into: multi-pattern, multi-promotion, governance-
adjudicating ontology system.**

---

**site-building.template-hierarchy-and-resolution COMPLETED —
NEW Resolution Surface candidate + Arbitration Compiler
universalization (2026-05-09):**

First site-building chunk + 3rd Phase 7.5 framework deployment.
Major KB validation event.

**Doctrinal backbone established:**

> Theme-config declares structural possibilities.
> Site-building resolves structural actuality.
> Template hierarchy is not file lookup — it is runtime
> authority arbitration through hierarchical structural
> resolution.

**Constitutional triad now complete:**

> Plugin-dev extends authority outward.
> Editor and admin govern authority inward.
> Site-building resolves authority into lived structure.

**MAJOR KB VALIDATION — Arbitration Compiler universalized:**

Pre-this-chunk Arbitration Compiler manifestations: 4 chunks
in 3 governance-heavy bounded contexts.

Post-this-chunk: **+ template hierarchy resolution
(composition authority)** — first manifestation in
composition-native bounded context.

> **Arbitration Compiler escapes governance-heavy zones.**
> **Confirmed architecture-general, NOT governance-domain**
> **artifact.** One of KB's biggest law validations.

5 instances × 4 bounded contexts.

**NEW candidate surfaced: Resolution Surface (modifier-free):**

> Governance through hierarchical structural resolution where
> authority becomes ACTUALIZED through deterministic
> precedence-based selection from competing candidates.

Status: **Surfaced** (Local Pattern, 1st observation).
"surfaced, not constitutionalized."

**Distinct from prior candidates:**

| candidate | character |
|---|---|
| Interception | mutate / inject existing authority |
| Mediation | controlled access channels |
| Routing | navigable hierarchy |
| **Resolution (NEW)** | **structural actualization via precedence-based selection** |

**Modifier-free naming preserved** per Phase 7.5 Doctrine 2 —
character determined post-recurrence (not pre-committed to
"Composition" or "Authority" Resolution).

**Verification candidates** (potential retroactive
recurrence):
- style-engine.cascade-aggregation = CSS cascade IS resolution
  (competing rules → applied value via specificity + order)
- plugin-dev.capabilities-and-roles map_meta_cap IS
  resolution-character (contextual cap → primitive selection)
- These could push Resolution candidate to cross-context
  recurrence directly via retroactive audit; defer to future
  explicit work

**8 invariants (key ones):**

1. Template hierarchy = runtime authority arbitration NOT
   file lookup
2. Site-building resolves what theme-config registers
   (cross-context substrate-vs-resolver split)
3. Multi-source arbitration confirms Arbitration Compiler
   beyond governance domains (KB validation)
4. Resolution graph = structurally entity-relationship-centric
   (Law 5 6+ context manifestation)
5. Compiler ↔ Runtime split present (template hierarchy IS
   WordPress's content-domain compiler)
6. 5-form Declaration ≠ Exposure for templates
7. **NEW Resolution Surface candidate surfaced (NOT promoted)**
8. **site-building = first composition-native bounded context**

**Constitutional Field Test results:**

Table A — All laws Confirmed (major validations):
- Law 4: Confirmed + escapes governance zones (5+ instances ×
  4 contexts; major KB validation)
- Law 5: Confirmed (6+ context manifestation depth)
- Law 6: Confirmed (template hierarchy as content-domain
  compiler)
- Law 1: 5-form Confirmed
- Law 3, 2: Confirmed

Table B (NEW Surfacing event):
| Candidate | Outcome |
|---|---|
| Resolution Surface (NEW) | Surfaced (Local Pattern) |
| Authority Mediation Surface | Divergent — site-building is resolution domain not mediation |
| Authority Interception Surface | Weak/secondary |
| Administrative Routing Surface | Not present |
| Federation Pattern | Confirmed (4th-context manifestation) |

**NEW KB-level finding — Bounded context character taxonomy:**

5 categories now identified:

| character | bounded contexts |
|---|---|
| **Schema authority** | block-authoring, theme-config |
| **Compiler/runtime** | style-engine, interactivity |
| **Authority federation** | plugin-dev (external) |
| **Governance modulation** | editor-customization, admin-ui |
| **Composition runtime (NEW)** | site-building |

This taxonomy is **observed, NOT yet spec-formalized** —
pending recurrence verification of each category's defining
character. May warrant Phase 7.6 spec patch consideration if
categories prove stable.

**KB-wide pattern recurrence updates:**

Debt pattern: 7 instances × 4 bounded contexts
(+ resolution debt added)

Federation Pattern: 4-context recurrence reinforced
(theme + child + DB + plugin federation in template authority)

Arbitration Compiler: 5+ instances × 4 contexts
(escapes governance-heavy zones — major)

**Strategic significance achieved:**

Site-building as breadth expansion validated:
- Tested existing candidates (Mediation Divergent /
  Interception Weak / Routing Not present)
- Confirmed laws survive in non-governance domain
  (Arbitration ESPECIALLY validates as universal)
- Surfaced NEW candidate from composition-native domain
  (Resolution)

> **KB now has architectural diversity in candidate pool:**
> Interception (lifecycle/topology) / Mediation (capability-
> gated access) / Routing (navigable hierarchy) / Resolution
> (precedence-based selection)

These 4 candidates may eventually cluster into governance
class family — anticipated, NOT constitutionalized.

**KB total post-template-hierarchy:**
- 70 substantive chunks
- 3 _meta documents (Phase 7.5 patched structural-patterns)
- = 73 .md files

**9 of 11 bounded contexts** with structural depth.
site-building entered as 9th.

**Recommended next chunks (priority):**

1. **Second site-building chunk** — Resolution Surface
   recurrence test within site-building. Candidates: block
   patterns runtime resolution / navigation menu fallback /
   query loop variation precedence.

2. **Retroactive Resolution Surface verification** —
   re-read cascade-aggregation + capabilities-and-roles
   through Resolution Surface lens. Could promote candidate
   to cross-context recurrence directly (skipping
   intra-context tier).

3. **`admin-ui.notices`** — admin-ui depth continuation;
   tests Routing recurrence + interception/mediation
   adjudication.

4. **`plugin-dev.nonces`** — security trio completion.

5. **Phase 7.6 spec patch consideration** — bounded context
   character taxonomy formalization (if 5-category split
   proves stable).

Recommended next: **Second site-building chunk** to build
Resolution Surface intra-context density (could promote
Surfaced → Local within site-building). OR **retroactive
verification** which would be lower work but produces
similar promotion outcome.

**Achievement summary post-resolution-surfacing:**

- 9 bounded contexts substantial depth
- 4 KB-wide capstones + paired doctrines
- 3 retroactive reframings completed
- KB infrastructure complete with Phase 7.5 patch
- **2 PROMOTION EVENTS** in KB:
  - Authority Interception Surface: Surfaced → Local
  - Authority Mediation Surface: Local → Recurring
    (intra-context)
- **3 NEW candidates surfaced** (NOT promoted):
  - Authority Mediation Surface
  - Administrative Routing Surface
  - **Resolution Surface (this chunk)**
- 7 debt instances × 4 bounded contexts
- 2 multi-pattern bounded contexts (editor + admin)
- 1 composition-native bounded context (site-building)
- Federation Pattern 4-context KB-Wide reinforcement
- **MAJOR: Arbitration Compiler universalized beyond
  governance**
- **NEW: Bounded context character taxonomy (5 categories)**

**KB matured into:**
> **architecturally diverse, universally arbitrating,**
> **multi-character governance ontology system.**

---

**cascade-aggregation RETROACTIVE Resolution Surface
verification COMPLETED — 4th retro patch in KB (2026-05-09):**

4th RETROACTIVE REFRAMING in KB. Verifies Resolution Surface
candidate against cascade-aggregation through retroactive lens.

**Verification result:**

> **Resolution Surface is LATENT, NOT novel.** Cascade-
> aggregation always exhibited paired arbitration + resolution
> operations; site-building chunk merely NAMED the structural
> pattern that was already operating in style-engine.

This mirrors KB's "discovery → retroactive revelation →
constitutional formalization" maturation pattern (parallel to
dynamic-rendering retro framing Interactivity API as "latent
runtime architecture becoming explicit").

**NEW doctrinal candidate surfaced (NOT yet formalized):**

> **Arbitration ↔ Resolution as PAIRED OPERATIONS**:
> - **Arbitration** = "How competing authorities are
>   EVALUATED" (selection logic, precedence, conflict
>   resolution)
> - **Resolution** = "How one authority becomes ACTUALIZED
>   from evaluated candidates" (downstream operationalization,
>   value materialization)

**Cross-mechanism verification (5 mechanisms exhibit both
stages):**

| mechanism | arbitration stage | resolution stage |
|---|---|---|
| CSS cascade | specificity + order + !important | variable substitution + inheritance + computed style |
| template hierarchy | hierarchy logic determines candidates | first existing wins → resolved render |
| map_meta_cap | meta cap → primitive caps + context | allow / deny adjudication |
| block filter chain | priority arbitrates execution | composed outputs → final settings |
| menu_position arbitration | numeric + filter ordering | rendered menu order |

**ALL 5 mechanisms exhibit both stages.** Strong evidence for
Arbitration ↔ Resolution as paired operations.

**Status**: candidate doctrinal refinement, **NOT yet patched**
to spec. Per Phase 7.5 Doctrine 2 (surfaced not
constitutionalized) — defer Phase 7.6 spec patch until 2nd
retro verification (capabilities-and-roles) confirms.

**Resolution Surface promotion path UPDATED:**

```
Resolution Surface candidate (post-cascade-aggregation retro):
   Pre-retro: Surfaced (1 chunk: site-building, explicit)
   Post-retro evidence: cascade-aggregation contains LATENT
                        resolution stage
   Updated status: Cross-context PRESENCE confirmed
                   (site-building explicit + style-engine latent)
   Recurring (cross-context) candidate, pending 2nd retro
   verification (capabilities-and-roles)
```

**NEW KB methodology established — Retroactive candidate
verification:**

```
Pattern structure (NEW):
   1. Candidate surfaced in chunk N
   2. Identify earlier chunks where pattern MAY be latent
   3. Retroactively verify presence (true/false per chunk)
   4. Update candidate status based on retro evidence
   5. If multiple retros confirm: candidate may directly reach
      Recurring (cross-context) without explicit forward
      recurrence
```

This methodology may warrant explicit DSL spec recognition
(Phase 7.6 candidate consideration).

**4 RETROACTIVE REFRAMING instances in KB:**

| chunk | retro trigger | finding |
|---|---|---|
| wrapper-attributes | post-style-engine closure | wrapper as authority transport surface |
| dynamic-rendering | post-Phase-7-capstone | server-side authority projection |
| markup-representation | post-Phase-7-capstone | HTML as universal continuity substrate |
| **cascade-aggregation** | **post-Resolution-Surface-surfacing** | **paired arbitration + resolution operations** |

Each retro reveals deeper structure that bounded-context
closure or candidate-surfacing made visible.

**Methodological discipline preserved:**

- Surfaced Arbitration ↔ Resolution distinction
- Did NOT promote to formalized doctrine
- Did NOT patch structural-patterns spec
- Documented anticipated Phase 7.6 patch consideration
- Refused to over-claim novelty for Resolution Surface
  (acknowledged latent character)

> **KB methodological maturity**: retroactive verification
> deepens existing chunks AND constrains promotion claims.
> Both strengthening and constraining are evidence-based;
> both serve constitutional integrity.

**KB total post-retro:**
- 70 substantive chunks (1 patched)
- 3 _meta documents
- = 73 .md files

**Recommended next chunks:**

1. **`plugin-dev/capabilities-and-roles` retro patch** —
   2nd Resolution Surface verification. Tests whether
   map_meta_cap also exhibits paired arbitration + resolution.
   If confirmed: Phase 7.6 spec patch becomes timely
   (Arbitration ↔ Resolution doctrine formalization).

2. **Second site-building chunk** — Resolution Surface
   intra-context recurrence test (block patterns / nav
   fallback / query loops).

3. **`admin-ui.notices`** — admin-ui depth + Routing recurrence.

4. **`plugin-dev.nonces`** — security trio completion.

5. **Phase 7.6 spec patch** — after capabilities-and-roles
   retro confirms.

**Recommended sequence: capabilities-and-roles retro → if
confirms → Phase 7.6 spec patch (Arbitration ↔ Resolution
formalization) → then continue site-building or other
contexts.**

This sequence treats current state as **pre-formalization
research phase** for Arbitration ↔ Resolution doctrine.
Methodological compression before further expansion.

---

**capabilities-and-roles RETROACTIVE Resolution Surface
verification (2nd retro) COMPLETED — DISTRIBUTED finding +
3rd PROMOTION EVENT (2026-05-09):**

5th RETROACTIVE REFRAMING in KB. 2nd Resolution Surface
verification. **3rd PROMOTION EVENT** in KB.

**Verification result: DISTRIBUTED (Outcome 2):**

> Capability adjudication exhibits Arbitration ↔ Resolution
> paired operations, but DISTRIBUTED across multiple functions
> (NOT integrated into single mechanism).

Architectural locations:
- **Arbitration**: map_meta_cap (meta cap → primitive caps +
  context derivation)
- **Resolution**: WP_User->has_cap iteration + user_has_cap
  filter + final boolean aggregation

This is **structurally different** from cascade-aggregation
(Integrated finding from 1st retro).

**NEW finding — Resolution Surface architectural variants:**

| pattern | example | character |
|---|---|---|
| **Integrated Resolution** | CSS cascade (cascade-aggregation) | both stages co-located in single mechanism |
| **Distributed Resolution** | Capabilities adjudication (this retro) | stages distributed across multiple functions |

**Cross-context PRESENCE updated (3 contexts):**

| context | manifestation | architecture |
|---|---|---|
| site-building (template hierarchy, explicit naming) | Resolution + Arbitration paired | Integrated |
| style-engine (cascade-aggregation, retro 1) | Resolution + Arbitration paired | Integrated |
| **plugin-dev (capabilities-and-roles, retro 2)** | **Resolution + Arbitration paired** | **DISTRIBUTED** |

3 bounded contexts × structurally clear manifestation +
**architectural diversity** (Integrated + Distributed).

**3rd PROMOTION EVENT in KB:**

```
Resolution Surface promotion event:
   Pre-this-retro: Surfaced (1 chunk explicit) +
                   cross-context PRESENCE confirmed (cascade
                   retro)
   Post-this-retro: 3-context PRESENCE +
                    architectural diversity (Integrated +
                    Distributed)
   PROMOTION: Surfaced → Recurring (cross-context)
              via retroactive verification methodology
```

**3 PROMOTION EVENTS in KB now:**
1. Authority Interception Surface: Surfaced → Local
   (slotfills, forward authoring)
2. Authority Mediation Surface: Local → Recurring
   (intra-context) (admin-menus, forward authoring)
3. **Resolution Surface: Surfaced → Recurring (cross-context)
   (capabilities retro, RETROACTIVE methodology)**

**Methodological precedent: retroactive verification is
first-class evidence.**

**Arbitration ↔ Resolution doctrine candidate STRENGTHENED:**

5 mechanisms × paired operations confirmed:

| mechanism | architecture |
|---|---|
| CSS cascade | Integrated |
| template hierarchy | Integrated |
| capability adjudication | Distributed |
| block filter chain | Integrated |
| menu_position arbitration | Integrated |

**Doctrinally precise formulation** (post-2-retro):

> **Arbitration and Resolution are paired operations.**
> **They may be co-located in a single mechanism**
> **(integrated architecture) or distributed across**
> **multiple mechanisms (distributed architecture).**

**Retroactive Candidate Verification methodology validated**
(2 honest applications):
- cascade-aggregation: confirmed integrated paired operations
- capabilities-and-roles: confirmed distributed paired
  operations

Both honest. Methodology distinguishes architectural variants
rather than forcing uniform conclusions.

**Phase 7.6 spec patch NOW TIMELY:**

Required spec updates:
1. Add Arbitration ↔ Resolution as documented paired
   operations doctrine (Section C / new doctrine)
2. Distinguish Integrated vs Distributed architectural
   variants
3. Document Retroactive Candidate Verification methodology
   (Section D adjudication question + new diagnostic)
4. Promote Resolution Surface to KB-Wide candidate (3-context
   recurrence + architectural diversity; pending audit
   verification for KB-Wide elevation)
5. Document promotion event chronology

Should follow Phase 7.5 patch chronology pattern: explicit
entry, 2-retro evidence, methodological discipline.

**5 RETROACTIVE REFRAMING instances in KB:**

| chunk | retro trigger | finding |
|---|---|---|
| wrapper-attributes | post-style-engine closure | wrapper as authority transport surface |
| dynamic-rendering | post-Phase-7-capstone | server-side authority projection |
| markup-representation | post-Phase-7-capstone | HTML as universal continuity substrate |
| cascade-aggregation | post-Resolution-Surface-surfacing | paired arbitration + resolution (Integrated) |
| **capabilities-and-roles** | **post-Resolution-Surface-surfacing** | **paired arbitration + resolution (Distributed) + 3rd promotion event** |

**KB-level coherence narrative:**

> Significant constitutional patterns are typically NOT
> discovered through novelty — they emerge through NAMING
> events that reveal latent structure already operating in
> earlier chunks. Discovery → naming → retroactive revelation
> → constitutional formalization.

This pattern now applies across:
- Interactivity API (latent runtime per dynamic-rendering retro)
- HTML primacy (latent substrate per markup-representation retro)
- **Resolution Surface (latent operationalization per cascade
  + capabilities retros)**

**Anticipated KB-level framing extension:**

> WordPress's authority architecture exhibits paired Arbitration
> ↔ Resolution operations across composition / governance /
> compilation domains. Whether integrated (single mechanism)
> or distributed (multi-function), the pairing is structurally
> universal.

**KB total post-retro:**
- 70 substantive chunks (2 patched: cascade-aggregation +
  capabilities-and-roles)
- 3 _meta documents
- = 73 .md files

**Recommended next: Phase 7.6 spec patch** —
2-retro confirmation provides sufficient evidence base.
Sequence:
1. Apply Phase 7.6 spec patch to structural-patterns.md
   (Arbitration ↔ Resolution doctrine + Integrated/Distributed
   variants + Retroactive Verification methodology + Resolution
   Surface promotion documented)
2. Then continue: site-building 2nd chunk OR admin-ui.notices
   OR plugin-dev.nonces

**Achievement summary post-3rd-promotion:**

- 3 PROMOTION EVENTS in KB (Interception / Mediation /
  Resolution)
- 5 RETROACTIVE REFRAMING instances
- 2 candidates at Recurring tier (Mediation intra-context;
  **Resolution cross-context**)
- 1 candidate at Surfaced tier (Routing)
- Resolution Surface architectural variant doctrine (Integrated
  vs Distributed) — NEW major refinement
- Retroactive Verification methodology validated as first-class
  evidence
- Phase 7.6 spec patch ready

**KB matured into:**
> **architecturally variant-aware, paired-operation-cognizant,**
> **retroactively-verifying ontology system.**

---

**Phase 7.6 Constitutional Expansion Patch APPLIED to
structural-patterns.md (2026-05-09):**

Spec patch following 2 retroactive Resolution Surface
verifications (cascade-aggregation Integrated +
capabilities-and-roles Distributed). Operational doctrine
expansion (NOT taxonomy expansion).

**Patch principle:**

> **Phase 7.6 did not expand the number of KB-Wide laws.**
> **It expanded the constitution's ability to describe HOW**
> **laws operationalize across architectural variants AND**
> **historical discovery.**

**5 mandatory changes applied:**

1. **Section A** — Promotion via forward density OR retroactive
   verification note added. Both are evidence-based promotion
   methodologies when applied with epistemic discipline.

2. **Section C.5 — Doctrine 5: Arbitration ↔ Resolution
   Paired Operations** added with embedded variants:
   - 5a Integrated architecture (CSS cascade / template
     hierarchy / block filters / menu_position)
   - 5b Distributed architecture (capability adjudication)
   - Architectural variant integrity principle

3. **Section D — Q9 Retroactive Verification Trigger** added
   as POST-write diagnostic alongside Q8:
   - Q8 = "what happened in THIS chunk?" (forward
     adjudication)
   - Q9 = "did THIS chunk reveal latent prior law elsewhere?"
     (retroactive constitutional expansion)
   - KB now governs both forward and backward ontology
     refinement

4. **Resolution Surface promotion documented** in META
   chronology (NOT in Section B):
   - Status: Recurring (cross-context); strong KB-Wide
     candidate
   - KB-Wide promotion pending audit verification

5. **Promotion event chronology** in META:
   - Event 1: Authority Interception Surface
     (Surfaced → Local) via slotfills (forward)
   - Event 2: Authority Mediation Surface
     (Local → Recurring intra-context) via admin-menus
     (forward)
   - Event 3: Resolution Surface (Surfaced → Recurring
     cross-context) via cascade + capabilities retros
     (RETROACTIVE)

**Constitutional maturity progression formalized:**

| phase | character |
|---|---|
| Phase 7 (audit) | descriptive verification |
| Phase 7.5 | epistemic governance refinement |
| **Phase 7.6** | **operational doctrine expansion** |

**Deferred Constitutional Candidates** (NOT applied; Phase
7.7 candidates):
- Cross-context PRESENCE tier (single observation; defer)
- Bounded context character taxonomy (5 categories observed;
  defer until stable)
- Topology subtype distinction (Injection vs Routing;
  2 observations insufficient)

These framed as "Constitutional Candidates" (preserves
discipline; not "future patches").

**KB infrastructure layer (post-Phase-7.6):**

```
_meta/dsl-spec.md               → KB OPERATING SYSTEM (unchanged)
_meta/kb-audit-phase7.md        → VERIFICATION ARTIFACT (unchanged)
_meta/structural-patterns.md    → CONSTITUTIONAL LAW LAYER
                                  (Phase 7.5 + Phase 7.6 patched)
```

**Spec sync now CURRENT with all retroactive verifications +
3rd promotion event.**

**KB constitutional maturity (post-Phase-7.6):**

KB is now:
- **law-aware** (6 KB-Wide Laws)
- **promotion-aware** (5-tier ladder + 5-class verdicts +
  3 promotion events)
- **retro-aware** (Q9 + 5 RETROACTIVE REFRAMING instances)
- **operationally variant-aware** (Doctrine 5 with
  Integrated / Distributed architectures)

> **Serious constitutional milestone reached.**

**KB total post-Phase-7.6:**
- 70 substantive chunks (2 patched in retros: cascade +
  capabilities)
- 3 _meta documents (structural-patterns Phase 7.5 + 7.6
  patched)
- = 73 .md files

**Recommended next:**

Per user sequence (after spec stabilization):
1. ✅ Phase 7.6 spec patch (DONE)
2. **Continue chunk authoring** with Q9 + Doctrine 5
   awareness:
   - Second site-building chunk (Resolution Surface
     intra-context recurrence test)
   - admin-ui.notices (Routing recurrence + interception/
     mediation adjudication)
   - plugin-dev.nonces (security trio completion)
3. Future Phase 7.7 patch when deferred candidates reach
   sustained pattern

Recommended next chunk: **second site-building chunk** OR
**admin-ui.notices** — both build out existing bounded
contexts. plugin-dev.nonces is self-contained intermixing
option.

**Phase 7.6 strategic significance:**

> **KB now a process-aware operational constitution** —
> shifted from static law catalog to constitutional system
> that governs its own evolution (forward authoring +
> retroactive verification, paired operational architectures,
> deferred candidate recognition with discipline).

---

**Phase 7.6 first live deployment chain COMPLETE
(2026-05-09):**

1. ✅ site-building.block-pattern-resolution-and-precedence
   (FIRST LIVE Phase 7.6 chunk + Q9 trigger)
2. ✅ variations retro patch (1st deliberate Q9 retro;
   Hybridized)
3. ✅ transforms retro patch (2nd deliberate Q9 retro;
   Hybridized — Selection from Candidates sub-pattern
   threshold reached)

**Achievement summary post-Phase-7.6-deployment:**

**Resolution Surface candidate status:**
- 4 bounded contexts × multiple chunks (site-building,
  style-engine, plugin-dev, block-authoring)
- 3 architectural variants (Integrated, Distributed,
  Hybridized)
- Intra-context density in 2 bounded contexts (site-building
  + block-authoring)
- 4 retroactive verifications + 1 forward authoring
- **Strongest non-KB-Wide candidate in KB**
- **Phase 7.8 KB-Wide audit verification candidacy
  CONFIRMED**

**NEW sub-pattern surfaced**:
- **"Selection from Candidates"** — mechanisms presenting
  selectable presets/conversions to user via inserter-or-
  equivalent UI exhibit Hybridized Doctrine 5 architecture
- 3-instance evidence (block patterns + variations +
  transforms; all Hybridized)
- Local Pattern with cross-context PRESENCE (site-building +
  block-authoring)
- **Phase 7.7 patch consideration candidate**

**RETROACTIVE REFRAMING instances: 7**
- wrapper-attributes
- dynamic-rendering
- markup-representation
- cascade-aggregation (Resolution Integrated)
- capabilities-and-roles (Resolution Distributed)
- variations (Resolution Hybridized — 1st deliberate Q9)
- **transforms (Resolution Hybridized — 2nd deliberate Q9)**

**Q9 methodology validation: 4 retros across 3 architectural
variants** (Integrated + Distributed + Hybridized).

**KB total post-deployment-chain:**
- 71 substantive chunks (4 patched: cascade + capabilities +
  variations + transforms)
- 3 _meta documents (structural-patterns Phase 7.5 + 7.6
  patched)
- = 74 .md files

**9 of 11 bounded contexts** with structural depth.
site-building now has 2 chunks (Resolution intra-context
density confirmed).

**Recommended next chunks:**

1. **Phase 7.7 spec patch consideration** — Selection from
   Candidates sub-pattern at 3-instance threshold; +
   accumulated deferred candidates if any reach threshold.

2. **Phase 7.8 audit verification** — Resolution Surface
   KB-Wide promotion via dedicated audit chunk.

3. **`site-building` 3rd chunk** — navigation menu fallback
   resolution (test 3rd Resolution stratification layer).

4. **`admin-ui.notices`** — admin-ui depth + Routing
   recurrence test.

5. **`plugin-dev.nonces`** — security trio completion
   (intermixing).

Recommended: **Phase 7.7 patch consideration** to formalize
"Selection from Candidates" sub-pattern + any other deferred
candidates reaching threshold (cross-context PRESENCE tier?
bounded context character taxonomy?). Spec sync prevents
drift before further chunk authoring.

**Constitutional maturity progression:**

| phase | character |
|---|---|
| Phase 7 | descriptive verification |
| Phase 7.5 | epistemic governance (5-tier ladder + 5-class verdicts + 4 doctrines) |
| Phase 7.6 | operational doctrine (Doctrine 5 paired operations + Q9) |
| **Phase 7.7 (anticipated)** | **sub-pattern formalization (Selection from Candidates) + deferred candidate review** |
| Phase 7.8 (anticipated) | Resolution Surface KB-Wide audit promotion |

**KB matured into:**
> **multi-variant-aware, sub-pattern-recognizing, audit-ready**
> **constitutional ontology system.**

---

**Phase 7.7 + Phase 7.8 COMPLETED (2026-05-09):**

**Phase 7.7 Constitutional Refinement Patch (sub-pattern
governance):**

4 changes applied to structural-patterns.md:
1. Section A — Cross-context PRESENCE notation orthogonal to
   depth tiers (breadth ≠ depth)
2. Section C — Doctrine 5c Recurring Sub-patterns within
   Architectural Variants + Selection from Candidates as
   1st sub-pattern
3. Section D — Q10 sub-pattern emergence diagnostic
   (post-write triad complete: Q8 forward / Q9 backward /
   Q10 sub-pattern)
4. META — Phase 7.7 chronology + Phase 7.8 audit gate
   criteria (5 conditions)

Constitution now governs **Law → Variant → Sub-pattern**
hierarchy.

**Phase 7.8 Constitutional Audit (Resolution Surface KB-Wide
verification) — REFUSED:**

Created: `_meta/kb-audit-phase8-resolution-surface.md`

5/5 audit gate criteria MET:
- 4 bounded contexts × 6 chunk-instances
- 3 architectural variants (Integrated/Distributed/Hybridized)
- 2 bounded contexts intra-context density
- Q10 completed via Phase 7.7 sub-pattern formalization
- Forward (2) + Retroactive (4) evidence both contributing

Structural invariance ESTABLISHED:
- 4/5 bounded context character categories exhibit Resolution
- Falsifiable predictive power
- Clear anti-confusion vs Arbitration Compiler (Law 4)
- Operational consequence (enrichment, not displacement)

**KB-Wide promotion: REFUSED.**

**Refusal rationale:**

> Structural meaning of Resolution is INHERENT to pairing
> with Arbitration. Doctrine 5 (Phase 7.6) already governs
> the pairing at doctrine layer. Promoting Resolution to
> KB-Wide LAW would duplicate Doctrine 5's governance.
> KB-Wide LAW tier reserved for INDEPENDENTLY meaningful
> structural invariants.

**FIRST EXPLICIT REFUSAL EVENT in KB.**

Constitutional principle introduced (Phase 7.8):

> Audit gate criteria establish ADMISSIBILITY for promotion
> consideration. Promotion DECISION requires additional
> structural fit analysis: is the candidate's meaning
> INHERENT to existing law/doctrine relationship, or
> INDEPENDENTLY meaningful at law tier?

**Resolution Surface status (post-Phase-7.8):**
- Recurring (cross-context) — UNCHANGED
- Doctrine 5 paired operations downstream stage — formalized
- Selection from Candidates sub-pattern parent — confirmed
- NOT KB-Wide LAW — refused

**Section B remains 6 KB-Wide Laws.**

**KB methodological maturity demonstration:**

> KB has reached the point where REFUSING promotion is as
> valuable as CONFIRMING it. Both outcomes serve
> constitutional integrity; both are evidence-based decisions.

**Constitutional progression formalized:**

| phase | function | outcome |
|---|---|---|
| Phase 7 | Audit | KB structural backbone |
| Phase 7.5 | Epistemic governance | 5-tier ladder + 5-class verdicts + 4 doctrines |
| Phase 7.6 | Operational doctrine | Doctrine 5 + Q9 |
| Phase 7.7 | Sub-pattern governance | Doctrine 5c + Q10 |
| **Phase 7.8** | **Law promotion audit** | **REFUSED — first explicit refusal event** |

**KB infrastructure layer (post-Phase-7.8):**

```
_meta/dsl-spec.md                              → KB OPERATING SYSTEM
_meta/kb-audit-phase7.md                       → Phase 7 verification
_meta/structural-patterns.md                   → CONSTITUTIONAL LAW LAYER
                                                  (Phase 7.5 + 7.6 + 7.7 patched +
                                                   Phase 7.8 chronology)
_meta/kb-audit-phase8-resolution-surface.md    → Phase 7.8 audit verdict
```

4 _meta documents now (added kb-audit-phase8).

**KB total post-Phase-7.8:**
- 71 substantive chunks (4 patched: cascade + capabilities +
  variations + transforms)
- 4 _meta documents
- = 75 .md files

**9 of 11 bounded contexts** with structural depth.

**Achievement summary post-Phase-7.8:**

- 6 KB-Wide Laws (UNCHANGED — Resolution refused promotion)
- Doctrine 5 (Phase 7.6) + 5a Integrated + 5b Distributed +
  5c Sub-patterns (Phase 7.7)
- 1st sub-pattern formalized: Selection from Candidates
- 3 PROMOTION EVENTS (1 retroactive cross-context, 2 forward)
- **1 REFUSAL EVENT (Resolution Surface KB-Wide audit)**
- 7 RETROACTIVE REFRAMING instances
- Q8/Q9/Q10 post-write diagnostic triad
- Phase 7.8 audit methodology validated

**KB matured into:**
> **constitutional ontology system with mature law/doctrine/**
> **sub-pattern hierarchy + audit-disciplined promotion AND**
> **refusal capability.**

**Recommended next chunks:**

1. **`site-building.{3rd chunk}`** — navigation menu fallback
   resolution. 3rd Resolution stratification layer test
   (vertical density continuation; Resolution remains
   below KB-Wide).

2. **`admin-ui.notices`** — admin-ui depth + Routing
   recurrence test + interception/mediation/hybrid
   evaluation.

3. **`plugin-dev.nonces`** — security trio completion
   (self-contained intermixing).

4. **Other bounded contexts** (i18n / build-tooling) —
   additive coverage; constitutional protocol per Phase
   7.5/7.6/7.7 patched spec.

5. **Phase 7.9 spec patch consideration** — if candidates
   reaching audit gate criteria + refusal pattern recurs,
   formalize structural-fit test (Q11) as anticipated.

Recommended next: **continue chunk authoring** with full
Phase 7.5/7.6/7.7 patched spec applied (Q8/Q9/Q10 diagnostic
suite). Spec is now stable; chunk authoring builds evidence
for future audit considerations.

**Key takeaway**: KB has reached **constitutional maturity
threshold** — refusal of premature promotion preserves
discipline; spec governance prevents drift; sub-pattern +
variant + paired-operations vocabulary handles structural
nuance honestly.

---

**KB Constitutional State Consolidation COMPLETED
(2026-05-09):**

`_meta/kb-consolidation-phase7-8.md` written — Constitutional
State Snapshot after Phase 7 → 7.8 cycle.

**5 _meta documents now form complete KB self-governance
system:**

```
_meta/dsl-spec.md                              → KB OPERATING SYSTEM
_meta/kb-audit-phase7.md                       → Phase 7 verification
_meta/structural-patterns.md                   → CONSTITUTIONAL LAW LAYER
                                                  (Phase 7.5/7.6/7.7 patched +
                                                   Phase 7.8 chronology)
_meta/kb-audit-phase8-resolution-surface.md    → Phase 7.8 refusal verdict
_meta/kb-consolidation-phase7-8.md             → CONSTITUTIONAL STATE SNAPSHOT
                                                  (this consolidation)
```

**6-section consolidation document structure:**

A. Constitutional maturity timeline (Phase 7 → 7.8 unified)
B. Hierarchy formalization (Law / Doctrine / Variant /
   Sub-pattern / Candidate / Promotion / Refusal / Status
   Notations)
C. Active candidates registry
D. Bounded context maturity map (4 closed + 5 active + 2
   untouched)
E. Constitutional governance capability (18 documented
   capabilities)
F. Phase 8+ frontier

**Constitutional principle established:**

> **Mature constitutions periodically consolidate before
> expanding.**

**KB Constitution v1 declaration:**

> KB Constitution v1 = 6 KB-Wide Laws + 5 Doctrines +
> Architectural Variants + Sub-patterns + Maturity ladder +
> Verdict taxonomy + Diagnostic triad + Audit gate criteria +
> Refusal discipline.

**Phase 7-8 cycle COMPLETE** — first full constitutional
maturity cycle traversed.

**KB total post-consolidation:**
- 71 substantive chunks (4 patched: cascade + capabilities +
  variations + transforms)
- 5 _meta documents (NEW: kb-consolidation-phase7-8)
- = 76 .md files

**KB state stable:**
- KB Constitution v1: STABLE
- Phase 7-8 cycle: COMPLETE
- Phase 8+ frontier: OPEN
- 9 of 11 bounded contexts with structural depth
- 4 active candidates tracked
- 3 promotion events + 1 refusal event documented
- 7 retroactive reframings + Q8/Q9/Q10 diagnostic triad
- Audit gate criteria validated (admissibility AND structural
  fit required)

**Recommended next chunks (post-consolidation):**

Per consolidation document Section F priorities:

1. **`i18n` entry** (recommended) — orthogonal substrate,
   low risk, framework portability test. Predicted laws:
   Law 3 + Law 6 Strong; Resolution Surface possibly
   Confirmed (translation key → candidate translations →
   selected actualized).

2. **`site-building.{3rd chunk}`** — navigation menu fallback
   resolution (3rd Resolution stratification layer test).

3. **`admin-ui.notices`** — admin-ui depth + Routing
   recurrence test.

4. **`plugin-dev.nonces`** — security trio completion.

5. **`build-tooling`** — lowest priority; defer indefinitely.

**Strategic recommendation per consolidation:**

> **i18n** — high methodological value (framework portability
> test) + low constitutional risk + orthogonal substrate.
> Constitutional protocol per Phase 7.5/7.6/7.7 patched spec
> applies cleanly; tests whether spec works for non-
> governance-heavy domain.

**KB matured into:**
> **constitutionally stable + audit-disciplined +**
> **process-aware + retroactive-verifying + sub-pattern-**
> **recognizing + refusal-capable ontology system.**
>
> **Phase 7-8 cycle: COMPLETE.**
> **KB Constitution v1: STABLE.**
> **Phase 8+ frontier: OPEN.**
