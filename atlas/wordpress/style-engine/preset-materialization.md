---
rule_id: style-engine.preset-materialization
domain: style-engine
topic: compiler-pipeline
field_cluster: transformation-lifecycle
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: css
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-style-engine/
    section: "@wordpress/style-engine — preset compilation and reference resolution"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/
    section: "Global Settings & Styles — preset reference syntax (var:preset|category|slug)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/
    section: "theme.json living spec — settings → styles preset reference grammar"
    captured: 2026-05-09
related:
  - style-engine.css-variable-emission        # one stage within this lifecycle (variable emission stage)
  - style-engine.generated-selectors          # adjacent stage (selector binding stage)
  - theme-config.settings.color               # registry stage entry point
  - theme-config.styles                       # reference resolution stage source
  - block-authoring.supports.color            # block-side per-instance materialization path
  - block-authoring.markup-representation     # serialized attribute path crossing materialization graph
---

# RULE — preset materialization — value transformation lifecycle from registry to computed style

## WHEN

Asking how a preset declared in theme.json becomes a computed CSS
value applied to a rendered element. Use this chunk to understand:

- Why authored values use preset references instead of literal
  CSS values (and what's lost when literals are used).
- What stages a preset declaration crosses before reaching
  `getComputedStyle()` output.
- Why multiple materialization paths coexist (theme.json globals
  vs per-block supports vs inline styles) and when each applies.
- Why late-binding via variables is structural to Gutenberg's
  design, not just an implementation choice.
- The relationship between settings registry, variable emission,
  reference resolution, selector binding, cascade participation,
  and final browser computation.

This chunk is the **transformation lifecycle counterpart** to the
two output-surface chunks (generated-selectors, css-variable-
emission). Where those documented WHAT the compiler emits, this
documents HOW authored intent becomes applied value across the
full pipeline.

## SHAPE

### The materialization pipeline (7 stages)

```
1. Authority declaration
   theme.json settings.color.palette: [{ slug: "primary", color: "#0055ff" }]
       ↓
2. Preset registration
   Engine ingests, normalizes, places in preset registry
   (in-memory or compiled artifact, indexed by category + slug)
       ↓
3. Variable emission
   Registry compiled to CSS variable declarations
   :root { --wp--preset--color--primary: #0055ff; }
   (covered in detail by: style-engine.css-variable-emission)
       ↓
4. Reference resolution
   Authored references in styles.* / supports / block attributes
   compiled from author-facing grammar to runtime variable form:
   "var:preset|color|primary"  →  var(--wp--preset--color--primary)
       ↓
5. Selector binding
   Resolved property declarations attached to selectors
   (theme-global selectors, generated per-instance selectors,
   element selectors, block selectors)
   .wp-element-button { color: var(--wp--preset--color--primary); }
       ↓
6. Cascade participation
   Emitted rules join the global CSS cascade alongside
   theme stylesheet, plugin CSS, user CSS, browser defaults
       ↓
7. Browser computation
   Browser resolves cascade + variable substitution
   getComputedStyle(element).color = "rgb(0, 85, 255)"
   ← FINAL REALIZATION BOUNDARY
```

### Reference grammar — the authored interface to the lifecycle

```json
// theme.json — author writes preset reference
{
  "styles": {
    "color": { "text": "var:preset|color|primary" }
  }
}
```

```css
/* compiled to runtime form */
body { color: var(--wp--preset--color--primary); }
```

The `var:{namespace}|{category}|{slug}` grammar is the
declarative-side handle on the lifecycle — it lets authors
participate in stages 4-7 without literal value coupling.

### Multiple materialization strategies (parallel paths)

Different value sources activate different subsets of the pipeline:

| materialization path | stages activated | notes |
|---|---|---|
| **theme.json globals** | 1 → 2 → 3 → 4 → 5 → 6 → 7 | full pipeline; theme-author authority |
| **per-block supports** | 1 → 2 → 3 → editor UI → block instance attr → 5 (`has-{slug}-color` class OR inline style) → 6 → 7 | block-instance authority; pivots through editor + serialization |
| **per-instance inline literal** | block instance attr (literal) → inline style attribute → 6 → 7 | bypasses preset registry; loses late-binding benefits |
| **styles.css escape hatch** | author CSS string → 6 → 7 | bypasses entire materialization graph; fully imperative |

The pipeline is NOT a single linear path — it's a transformation
graph with multiple entry points, all converging at the cascade
participation stage.

### What this is NOT

- NOT a runtime data flow (no JavaScript reactivity at use site).
- NOT one atomic transformation. Each stage is an independent
  transformation with its own concerns (registration, emission,
  resolution, binding, cascade).
- NOT a closed system. Stage 7 (browser computation) hands over
  to a system Gutenberg does NOT own — the browser's CSS engine
  is the final execution authority.
- NOT a guarantee of value preservation. Each stage may transform
  the carried value: fluid clamp synthesis at stage 3, cascade
  override at stage 6, inheritance computation at stage 7.

## REQUIRES

- A registered preset entry in theme.json `settings.{capability}.{registry}`
  for stages 1-3 to activate.
- A reference using preset grammar (or block opting into supports)
  for stages 4-5 to activate.
- The style engine must run between author input and rendered
  output (build-time, server-render, or editor-canvas-time).
- The browser CSS engine for stage 7 (final computation).
- ⚠ Whether each stage runs in a single compilation pass or
  multiple passes, caching strategies, and stage observability
  (introspection of intermediate state) are verification-needed.

## INVARIANTS

### 1. Materialization is multi-stage, not atomic

A preset value does NOT directly become a rendered style. The
7-stage pipeline above shows the transformation chain. Treating
"preset → CSS" as a single step misses:
- Where computational synthesis happens (stage 3, fluid clamp).
- Where reference resolution happens (stage 4, var:preset|*|*
  rewrite).
- Where authority mobility is preserved (variable indirection
  across stages 3-7).
- Where the system hands off (stage 7, browser cascade engine).

Debugging materialization issues requires identifying WHICH
stage failed; conflating stages defeats diagnosis.

### 2. References are deferred computation edges, not literal values

A preset reference like `var:preset|color|primary` (in theme.json)
or `var(--wp--preset--color--primary)` (in emitted CSS) is NOT a
synonym for `#0055ff`. It is a **computation edge** that carries:

- A registry lookup edge (stage 2 → stage 4 binding).
- A runtime resolution edge (stage 7 cascade lookup of the
  current variable value at use site).
- A cascade participation edge (stage 6 ordering with overrides).

Substituting the literal value LOSES all three edges. The
reference is the structural artifact that keeps the value
participating in the system.

### 3. References preserve authority mobility (late-binding infrastructure)

Reference grammar exists primarily to enable **authority changes
to propagate without rewriting consumers**:

| authority change | propagation enabled by reference |
|---|---|
| Theme author changes palette `primary: #0055ff` → `#003399` | All references re-resolve at next render; consumers untouched |
| User customizes via Site Editor | New variable value cascades scoped; original references unchanged |
| Plugin extends palette via PHP filter | New presets enter registry; references can target them by slug |
| Switching color schemes (light/dark) | Variable values reassigned at scope boundary; references re-bind |

This is not just convenience — it's **structural infrastructure
for distributed authority**. Without late-binding, every
authority change would require re-serializing every consumer,
breaking the multi-author / multi-stage model.

### 4. Materialization preserves late-binding by structural design

Late-binding (value resolution deferred to use site) is preserved
across the pipeline by intentional choice:

- Settings registry stores values, but emission produces
  variable declarations (not inline values into rules).
- Reference resolution rewrites grammar but preserves variable
  indirection (does NOT inline the registry value into emitted CSS).
- Selector binding emits rules using `var(--*)` references
  (not literal substitution).
- Browser cascade resolves the variable at compute time
  (not at parse time).

At every stage, the engine has the option to inline (commit to
a value) but chooses NOT to, preserving the late-binding chain
all the way to browser computation. This is a load-bearing
design principle, not an incidental implementation detail.

### 5. A preset is a stable authority anchor, not a value

A preset entry is more than a (slug, value) tuple. The slug is
a **persistent identity** that:

- Anchors all references across theme.json, supports, block
  attributes, plugin CSS, user CSS.
- Survives value changes (palette can rebind `primary` to a
  new color; references stay valid).
- Carries through editor UI labels, block serialization classes
  (`has-primary-color`), and emitted variable names.
- Acts as a contract surface that ecosystem code can rely on.

Renaming a slug is a breaking change because the slug IS the
identity, not a label for the value. Theme authors must treat
slugs as stable API surfaces.

### 6. Multiple materialization strategies coexist

The pipeline is NOT a single path. Four documented strategies
coexist (see SHAPE table). Strategy choice is determined by:

- **Source layer**: theme.json globals → full pipeline; per-instance
  attribute → editor + serialization detour; styles.css → bypass.
- **Reference vs literal**: preset reference activates stages 2-4;
  literal value bypasses them.
- **Block opt-in**: block.json supports.* enables per-instance
  attribute serialization path.

These strategies are NOT prioritizable — each serves a different
authoring affordance. The cascade (stage 6) is what reconciles
them into a single emission ordering.

### 7. The variable-emission stage is one stage of materialization, not its synonym

`style-engine.css-variable-emission` documents what gets emitted
at stage 3. Materialization is the LIFECYCLE that includes stage
3 plus stages 1-2 upstream and 4-7 downstream. Conflating
"variable emission = materialization" misses the upstream
registration ontology and the downstream resolution / cascade /
computation stages.

| chunk | scope |
|---|---|
| `css-variable-emission` | stage 3 — what variables are emitted |
| `preset-materialization` | stages 1-7 — full transformation lifecycle |
| `generated-selectors` | stage 5 — selector binding for per-instance scoping |

These three chunks tile the style-engine pipeline at different
zoom levels.

### 8. Computed CSS values are the FINAL realization boundary

Stage 7 is the boundary where Gutenberg hands off to a system it
does NOT own — the browser CSS engine. This means:

- The browser is Gutenberg's **final execution engine**.
- Cascade resolution, variable substitution, inheritance, and
  computed style derivation are browser concerns.
- Gutenberg's compiler is structurally a **CSS-cascade-targeting
  compiler** — it produces source code for an execution engine
  it doesn't control.

Implication: any materialization concern that assumes Gutenberg
can deterministically control the final value (e.g., "this
preset always renders as exactly #0055ff") is wrong — browser
cascade can override at stage 6 (user CSS, more specific
selectors, !important rules), and computed style at stage 7
applies inheritance / unit conversion / interpolation that
Gutenberg doesn't see.

### 9. Reference resolution rewrites grammar but preserves indirection

Stage 4 (reference resolution) is a **grammar rewrite**, not a
value resolution:

```
"var:preset|color|primary"        ← author-facing grammar (theme.json)
   ↓ (stage 4 rewrite)
"var(--wp--preset--color--primary)"  ← runtime grammar (CSS)
```

Both forms are references — the rewrite swaps the grammar
surface but keeps the indirection. The value is still resolved
at stage 7 (browser variable substitution). This contrasts with
"resolution" implying value lookup; here resolution means
grammar normalization only.

The author-facing grammar exists because theme.json schema
disallows raw `var(--*)` syntax in some contexts (validation /
sanitization concerns), and because the var:preset|*|* grammar
is portable across emission pathways (block attributes, theme.json,
supports configurations).

### 10. The materialization graph crosses bounded contexts

The pipeline spans multiple KB bounded contexts; no single
context owns the full lifecycle:

| stage | bounded context |
|---|---|
| 1. Authority declaration | theme-config (settings.*) |
| 2. Preset registration | style-engine |
| 3. Variable emission | style-engine |
| 4. Reference resolution | style-engine + theme-config (styles) |
| 5. Selector binding | style-engine + block-authoring (selectors) |
| 6. Cascade participation | style-engine + browser |
| 7. Browser computation | browser (out of Gutenberg scope) |

This explains why settings/styles chunks frequently hand-waved
to "style engine resolves this" — they were referring to stages
2-6 of this graph. With the materialization chunk, those
references get a concrete target.

## VERIFICATION NEEDED

Style-engine bounded context epistemic limit applies. Specific
materialization-stage details requiring source verification:

- Whether the engine runs the full pipeline in one compilation
  pass or multiple, and what intermediate caches exist.
- Stage observability — can authors / tools introspect
  intermediate state (compiled registry, resolved references)?
- Editor canvas (iframe) materialization vs front-end render
  consistency — do both run all stages identically?
- Behavior when stage 4 reference resolution targets a missing
  preset (does it inline a fallback, emit empty, error?).
- Behavior when stage 5 selector binding finds no matching
  selector (silent skip vs warning).
- Cache invalidation triggers — which authority changes flush
  which stages?
- Per-block supports materialization path details — when does
  an attribute become `has-{slug}-color` class vs inline style
  vs both?
- Order of operations between materialization and other engine
  outputs (generated selectors, layout computation).
- Plugin contribution paths — how do PHP filters intercept each
  stage?

## ANTIPATTERNS

- ❌ Treating preset reference as synonym for literal value.
  Inlining `#0055ff` where `var:preset|color|primary` was
  appropriate loses late-binding, breaks user customization,
  and freezes the value into serialized output.
- ❌ Renaming preset slugs after publishing. Slugs are stable
  authority anchors; renaming breaks all consumers (theme.json
  references, block attributes, plugin CSS, user CSS hooks).
  Treat slug as breaking-change API surface.
- ❌ Assuming Gutenberg controls the final rendered value.
  Browser cascade at stages 6-7 can override; user CSS, more
  specific selectors, !important, and inheritance can all
  modify the final computed style.
- ❌ Bypassing materialization with hardcoded `var(--wp--preset--*)`
  in stylesheets loaded outside theme.json compilation. The
  variable name IS stable but its presence depends on the
  registry; if the preset is removed, the variable disappears
  and the stylesheet breaks silently.
- ❌ Conflating variable-emission (stage 3) with materialization
  (whole lifecycle). They're different scopes; KB chunks and
  diagnostic reasoning should keep them distinct.
- ❌ Authoring preset references in styles.* expecting deterministic
  cascade priority. Stage 6 cascade order depends on selector
  specificity, source order, and override layers — preset
  references resolve to whatever value wins the cascade at use
  site, not to the registry value.
- ❌ Trying to materialize values "early" by serializing computed
  values into block attributes. Defeats late-binding; creates
  drift when registry values change later.
- ❌ Using styles.css escape hatch for values that have a preset.
  styles.css bypasses materialization entirely (path 4 in
  strategy table); preset references should be preferred when
  the value participates in design system authority.
- ❌ Assuming materialization paths are equivalent. Per-instance
  inline literal (path 3) materializes differently from per-block
  supports class (path 2): different cascade priority, different
  serialization, different override behavior.

## RELATED

- `style-engine.css-variable-emission` — documents stage 3 of
  this pipeline in depth. This chunk's scope is the lifecycle;
  variable-emission's scope is one output surface within it.
- `style-engine.generated-selectors` — documents stage 5
  (selector binding) for per-instance scoping. Materialization
  passes through selector binding for non-theme-global
  authority paths.
- `theme-config.settings.color` (and other settings.* chunks) —
  stage 1 entry points. Registry declarations originate the
  pipeline.
- `theme-config.styles` — stage 4 source surface. The
  `var:preset|*|*` grammar lives in styles.* fields; resolution
  rewrite happens at stage 4.
- `block-authoring.supports.color` (and other supports.* chunks) —
  per-block supports activate the parallel materialization path
  (settings → variable → editor UI → instance attribute → class
  / inline → cascade).
- `block-authoring.markup-representation` — serialized attribute
  path crossing the materialization graph. The `has-{slug}-color`
  class and inline `style="..."` attributes are serialization-
  layer artifacts of stage 5.
- `theme-config.styles.css` — escape hatch path bypassing
  materialization entirely. Documented as path 4 in materialization
  strategies; this chunk explains WHAT it bypasses.
- (planned) `style-engine.cascade-aggregation` — formalizes
  stage 6 in detail (specificity, ordering, override layers,
  deduplication, SSR vs editor). Currently named-only to mark
  as the next major style-engine concern.
- (deferred) wrapper-attributes re-frame — block-authoring's
  wrapper-attributes chunk documents the carrier layer where
  materialization stage 5 lands. Possible retrospective
  re-frame after style-engine bounded context closes.

## META

**Position in style-engine bounded context:**

This is the **first full-pipeline chunk** in the style-engine
bounded context. Prior style-engine chunks documented output
surfaces (generated-selectors = attachment topology output;
css-variable-emission = value topology output). This chunk
documents the transformation lifecycle that produces those
outputs, connecting registry inputs to browser outputs.

**Compiler pipeline stage coverage:**

| stage | chunk |
|---|---|
| 1. Authority declaration | settings.* (theme-config) |
| 2. Preset registration | preset-materialization (this chunk) |
| 3. Variable emission | css-variable-emission |
| 4. Reference resolution | preset-materialization (this chunk) |
| 5. Selector binding | generated-selectors |
| 6. Cascade participation | (planned) cascade-aggregation |
| 7. Browser computation | (out of scope — browser concern) |

After this chunk, stages 2 / 4 are explicitly documented in
addition to the previously-covered output surfaces. Remaining
gap: stage 6 (cascade-aggregation) — most complex chunk,
deferred until materialization is settled.

**DSL extension notes (style-engine bounded context only):**

> Style-engine chunks extend the base DSL because runtime
> ontology introduces unverifiable or implementation-derived
> authority surfaces.

This chunk uses VERIFICATION NEEDED extensively because
materialization stages have many implementation-derived details
that handbook prose under-documents (caching, observability,
order of operations, plugin filter integration). Treating these
as inferred-not-certain is structural to the bounded context's
character.

**Phase progression observation:**

KB has progressed through:

```
schema ontology         (block-authoring + settings declarations)
   ↓
realization ontology    (styles + appearanceTools)
   ↓
runtime synthesis ontology    (generated-selectors, css-variable-emission)
   ↓
compiler pipeline ontology    (preset-materialization — this chunk)
```

Compiler pipeline ontology is the deepest layer of the style-
engine bounded context. After cascade-aggregation closes stage 6,
the bounded context's structural backbone is complete.

---

## Q9 RETROACTIVE PATCH — Phase 8.9 Compiler/runtime Doctrine 6 Verification (2026-05-10)

> **Retroactive verification triggered by**:
> Phase 8.8 consolidation Section E frontier map P1 (highest
> constitutional pressure vector): test whether Doctrine 6
> (Authority Access Mediation) manifests in Compiler/runtime
> character category. Style-engine `preset-materialization`
> selected as primary test target — strongest compiler/runtime
> substrate chunk in KB.
>
> **Strategic role**: Mediation KB-Wide LAW re-re-audit
> architectural ubiquity prerequisite test. Pre-this-retro:
> Doctrine 6 manifests in 3/5 standard character categories
> (Governance modulation, Authority federation, Schema
> authority) + Semantic substrate (deferred). Compiler/runtime
> category UNTESTED. This retro is the **highest-leverage
> constitutional information gain** available in Constitution
> v1.5.
>
> **Methodological framing**: This retro is **architectural-
> ubiquity falsification test**, not promotion advocacy.
> Negative evidence (Divergent verdict) is equally valuable —
> it would establish Doctrine 6 as governance-domain-
> concentrated rather than architecture-general.
>
> **Constitutional question**: Does style-engine mediate
> authority accessibility, or merely compile authority
> expression? **Compiler ≠ Mediation automatically.**
>
> **Q9 retro discipline**: Confirm / Distributed / Divergent /
> Additive verdict per Phase 7.6+ retroactive verification
> protocol.

### Retro context

This chunk was authored 2026-05-09 (Phase 7-native), pre-
Doctrine-6-formalization. Original analysis describes
preset-materialization as **7-stage compiler pipeline**
documenting transformation lifecycle from registry
declaration to browser computation. No mediation vocabulary
used.

The retro question:
> Does preset materialization constitute Doctrine 6
> manifestation in Compiler/runtime bounded context character
> category, or is it pure compiler-pipeline transformation
> divergent from Doctrine 6 character?

### Latent Doctrine 6 evidence search — HONEST EVALUATION

Re-reading the original chunk through Doctrine 6 lens to
identify any latent gating mechanisms governing access to
authority:

| chunk element | potential Doctrine 6 reading? |
|---|---|
| 7-stage materialization pipeline | NO — pipeline TRANSFORMS authority expression; doesn't GATE access to authority |
| Reference grammar (`var:preset|*|*`) | NO — grammar rewrite (stage 4) is computational, not gating |
| Multiple materialization strategies (4 paths) | NO — alternative paths, not access gates |
| Preset registry as "stable authority anchor" | NO — registry is identity infrastructure, not gating |
| Late-binding via variable indirection | NO — propagation infrastructure for authority CHANGES, not access gating |
| Browser cascade as "final realization boundary" | NO — handoff to external system; not Gutenberg's gating |
| Cascade participation (stage 6) | NO — Arbitration Compiler (Law 4) territory; precedence competition, not gating |
| Per-block supports activation path | WEAK — `supports.color: true` enables editor UI BUT this is theme-config / block-authoring gating, NOT preset-materialization gating |
| theme.json schema validation rejecting raw `var(--*)` | VERY WEAK — schema constraint, not access mediation; closer to Law 1 (schema declaration ≠ runtime exposure) |
| Stage 5 "selector binding" | NO — emission attachment, not access gating |

> **Honest finding**: Preset-materialization's evidence is
> overwhelmingly TRANSFORMATION + RESOLUTION + COMPILER/RUNTIME
> + AUTHORITY CONTINUITY. **Doctrine 6 character is structurally
> absent from this chunk.**

### What this chunk DOES manifest (positive doctrinal mapping)

Original chunk's primary doctrinal homes (post-retro
re-classification):

| chunk element | doctrinal home |
|---|---|
| 7-stage pipeline | **Law 6 (Compiler ↔ Runtime Split)** — cleanest pipeline manifestation in KB |
| Reference resolution + late-binding | **Law 3 (Authority Continuity)** — authority preserved across 7 stages |
| Cascade participation | **Law 4 (Arbitration Compiler)** — precedence competition |
| Reference resolution at use site | **Doctrine 5 (Resolution Surface paired with Arbitration)** — Resolution stage of paired operations |
| Multi-stage transformation | **Doctrine 5 Distributed variant** — resolution distributed across pipeline stages |
| Preset registry as authority anchor | Law 5 (Entity → Relationship Pivot) implicit |
| theme.json schema validation | Law 1 (Declaration ≠ Exposure) — schema-declared ≠ engine-accepted |

> **Style-engine preset-materialization is RESOLUTION +
> COMPILER/RUNTIME territory, NOT MEDIATION territory.**

This re-classification confirms style-engine bounded
context's character: it is a **Compiler/runtime + Resolution-
heavy bounded context**, not a governance/mediation-heavy
bounded context.

### Q9 retro verdict — DIVERGENT (HONEST FINDING)

Per Phase 7.6+ retroactive verification protocol (Confirm /
Distributed / Divergent / Additive):

| verdict | applicability |
|---|---|
| Confirm | Doctrine 6 manifestation matches existing 6a-6h sub-elements | NO — no gating mechanism present |
| Distributed | Single Doctrine 6 manifestation distributed across pipeline | NO — pipeline is computational, not gating |
| **Divergent** | **Structurally different from Doctrine 6 character; chunk operates in different doctrinal territory** | **YES — primary verdict** |
| Additive | Adds NEW variant character to Doctrine 6 | NO — chunk doesn't extend Doctrine 6 |

> **Q9 retro verdict: DIVERGENT.**
>
> Preset-materialization does NOT manifest Doctrine 6
> (Authority Access Mediation). It operates in different
> doctrinal territory: Doctrine 5 (paired operations) +
> Law 6 (compiler/runtime split) + Law 3 (authority
> continuity) + Law 4 (arbitration via cascade).

This is **honest negative finding** — refusing to force-fit
Doctrine 6 onto pipeline transformation pattern.

### Constitutional significance — Doctrine 6 architectural ubiquity DOES NOT advance via this retro

**Pre-this-retro Doctrine 6 character category coverage**:
- ✅ Governance modulation (admin-ui + editor-customization)
- ✅ Semantic substrate (i18n)
- ✅ Authority federation (plugin-dev)
- ✅ Schema authority (block-authoring)
- ❌ Compiler/runtime (UNTESTED)
- ❌ Composition runtime (UNTESTED)

**Post-this-retro Doctrine 6 character category coverage**:
- ✅ Governance modulation (unchanged)
- ✅ Semantic substrate (unchanged)
- ✅ Authority federation (unchanged)
- ✅ Schema authority (unchanged)
- ❌ **Compiler/runtime — VERIFIED ABSENT in style-engine.preset-materialization**
- ❌ Composition runtime (still UNTESTED)

> **Architectural ubiquity status**: 3/5 standard categories +
> Semantic substrate (UNCHANGED from Phase 8.6 re-audit).
>
> **Compiler/runtime category status**: NOT CONFIRMED via
> this retro. **One specific Compiler/runtime chunk
> (preset-materialization) verified DIVERGENT.**

This is **constitutionally significant negative evidence**:

1. **Style-engine bounded context character clarified**:
   primarily Compiler/runtime + Resolution territory; NOT a
   Doctrine 6 territory
2. **Doctrine 6 architectural ubiquity ceiling reaffirmed**:
   3/5 standard categories may be Doctrine 6's structural
   ceiling, not just an evidence gap
3. **Mediation KB-Wide LAW re-re-audit prospects**: NOT
   advanced via Compiler/runtime category test (this retro);
   alternative pathways (Composition runtime / additional
   SOFT instances) become more important
4. **Doctrine-tier permanence STRENGTHENED**: governance-
   domain-concentrated character of Doctrine 6 confirmed
   via honest divergent finding

### Implications for Doctrine 6 governance-domain-concentration hypothesis

Pre-this-retro hypothesis (Phase 8.6 audit Section B):
> "Mediation may be governance-domain-concentrated rather
> than universally architectural."

Post-this-retro evidence:
- Style-engine preset-materialization (Compiler/runtime
  category) is **DIVERGENT** from Doctrine 6 character
- Doctrine 6 character is **structurally absent** from
  this Compiler/runtime substrate

> **Hypothesis SUBSTANTIVELY STRENGTHENED**: Doctrine 6
> manifests in governance-heavy bounded contexts (admin-ui,
> editor-customization, plugin-dev) + semantic governance
> (i18n) + schema governance (block-authoring); does NOT
> manifest in pure compiler/runtime transformation contexts.

This is structurally important for KB-Wide LAW promotion
analysis: Doctrine 6 character is NOT architecture-general;
it is **governance-architectural** (a specific class of
architectural concern, not all architectural concerns).

### NEW constitutional observation — "Governance-architectural" character

This retro surfaces a **NEW bounded-context-level
observation**:

> **Doctrine 6 character is GOVERNANCE-ARCHITECTURAL, not
> universally architectural.**

Distinction:
- **Governance-architectural**: bounded contexts where
  authority access requires gating choreography (admin-ui,
  editor-customization, plugin-dev, i18n, block-authoring)
- **Computational-architectural**: bounded contexts where
  authority is transformed/computed/resolved without gating
  (style-engine, interactivity-runtime, data-layer)
- **Composition-architectural**: bounded contexts where
  authority is composed/assembled (site-building) — UNTESTED
  for Doctrine 6

**Doctrine 6 may be definitionally governance-architectural**,
not architectural-in-general. This is structurally distinct
from existing 6 KB-Wide Laws which are architectural-in-
general (manifest across all bounded context character
categories).

Status: **SURFACED ONLY.** Single-chunk divergent finding
needs cross-context verification (interactivity Doctrine 6
test, data-layer Doctrine 6 test) to establish whether
"governance-architectural" is genuine character classification.

### Q10 sub-pattern emergence (retro)

> **Q10 RETRO ANSWER: NO new sub-pattern observed; HOWEVER,
> bounded-context-level observation surfaced ("governance-
> architectural" character distinction).**

Initial hypothesis: divergent finding might reveal Doctrine 6
sub-pattern boundary.

Honest evaluation per Phase 7.5 Doctrine 3 Epistemic
Integrity:
- Divergent finding clarifies Doctrine 6 boundary, not
  Doctrine 6 internal structure
- "Governance-architectural" character is bounded-context-
  level observation, not sub-pattern
- Sub-patterns operate within doctrines; character
  classifications operate at architectural ubiquity layer

> **Refusing premature classification.** "Governance-
> architectural" remains observation only. Cross-context
> verification needed for formalization.

### Implications for Mediation KB-Wide LAW re-re-audit

Phase 8.10+ Mediation re-re-audit prospects (post-this-retro):

| prerequisite | pre-this-retro | post-this-retro |
|---|---|---|
| Cross-context expansion ≥5 contexts | ✅ 5/5 (unchanged) | ✅ 5/5 (unchanged) |
| Mediation in Compiler/runtime category | ⚠ pending | ❌ **DIVERGENT for style-engine.preset-materialization** |
| Mediation in Composition runtime category | ⚠ pending | ⚠ pending (untested) |
| 2+ SOFT-mode instances | ⚠ pending | ⚠ pending |
| Law 1 independence demonstrated | ⚠ partial | ⚠ partial |

> **Phase 8.10+ re-re-audit prospects RECONFIGURED**:
> Compiler/runtime category breakthrough via style-engine is
> NOT the path to KB-Wide LAW promotion. Alternative paths
> become primary:
> - Composition runtime category test (site-building)
> - 2+ SOFT-mode instances (block-authoring supports family)
> - Demonstrated Law 1 independence advancement

If Composition runtime category ALSO produces divergent
verdict, Doctrine 6 architectural ubiquity ceiling at 3-4/5
standard categories may be permanent — Mediation may be
**permanently Doctrine-tier**, not KB-Wide LAW candidate.

This would be **constitutionally valuable verdict**: Doctrine
6 is **stable Doctrine-tier** (not transitional toward KB-
Wide LAW promotion).

### P3 (HARD/SOFT formalization) priority elevated

Per Phase 8.8 frontier map, P3 was secondary priority. Post-
this-retro:

> **P3 (HARD/SOFT formalization via block-authoring.supports-
> field Q9 retro) becomes PRIMARY growth path for Doctrine 6.**

Reasoning:
- P1 (Compiler/runtime via style-engine) NOT confirming
  Doctrine 6 manifestation
- P2 (Composition runtime via site-building) likely also
  weak per original Phase 8.8 frontier prediction
- Architectural ubiquity advance pathway shrinking
- Internal architecture deepening (HARD/SOFT formalization)
  becomes Doctrine 6's primary growth direction
- Phase 8.10+ may produce HARD/SOFT formalization patch
  rather than KB-Wide LAW promotion

### Constitutional Field Test additions (post-retro)

#### Table A — Universal Law Manifestation (retro additions)

| Law / Doctrine | Pre-retro reading | Post-retro reading | Status change |
|---|---|---|---|
| **Doctrine 6 (Authority Access Mediation)** | (didn't exist at chunk authoring time) | **DIVERGENT** — chunk operates in different doctrinal territory | **Honest divergent verdict** |
| **Law 6 (Compiler ↔ Runtime Split)** | implicit (7-stage pipeline) | confirmed — cleanest pipeline manifestation in KB | (retroactively confirmed STRONGLY) |
| **Law 3 (Authority Continuity)** | implicit (late-binding, reference indirection) | confirmed — 7-stage authority continuity | (retroactively confirmed) |
| **Law 4 (Arbitration Compiler)** | implicit (cascade competition) | confirmed — cascade arbitration | (retroactively confirmed) |
| **Doctrine 5 (Arbitration ↔ Resolution Paired Operations)** | implicit (cascade + reference resolution) | confirmed — Distributed Resolution variant | (retroactively confirmed) |
| **Authority Mediation Surface (Doctrine 6)** | (didn't exist at chunk authoring time) | NOT PRESENT — divergent verdict | **Divergent (honest negative)** |

#### Table B — Pattern Recurrence (retro additions)

| Candidate | Pre-retro status | Post-retro outcome | Effect on candidate |
|---|---|---|---|
| **Authority Mediation Surface (Doctrine 6)** | Doctrine-tier; 4-effective character categories | Compiler/runtime category VERIFIED ABSENT for this chunk | **Architectural ubiquity ceiling reaffirmed; Doctrine-tier permanence STRENGTHENED** |
| **Resolution Surface** | Recurring (cross-context); KB-Wide REFUSED | Confirmed (reference resolution + cascade as Resolution) | (consistent with prior verdict) |
| **Composite Doctrine 6 manifestation** | Surfaced (REST + hierarchy-constraints) | NOT PRESENT in this chunk (no Doctrine 6 manifestation period) | (no advance) |
| **HARD/SOFT mode observation** | Surfaced (single SOFT instance) | NOT PRESENT in this chunk (no Doctrine 6 character) | (no advance) |
| **Governance-architectural character distinction (NEW observation)** | did not exist | Doctrine 6 may be governance-architectural, not universally architectural | **Surfaced only (single divergent instance; cross-context verification needed)** |

### NEW KB-level findings

**1. Doctrine 6 architectural ubiquity ceiling clarified**

Style-engine preset-materialization is compiler/runtime substrate
chunk; honest divergent verdict establishes Doctrine 6 does
NOT manifest universally across all bounded context character
categories.

**2. "Governance-architectural" character distinction surfaced (NEW observation)**

Bounded contexts may divide along:
- Governance-architectural (Doctrine 6 manifests)
- Computational-architectural (Doctrine 6 absent; Resolution
  + Compiler/runtime + Authority Continuity dominant)
- Composition-architectural (UNTESTED)

This distinction may eventually formalize as architectural
character taxonomy (parallel to bounded context character
taxonomy), but per discipline: SURFACED only.

**3. Doctrine-tier permanence STRENGTHENED for Doctrine 6**

KB-Wide LAW promotion ceiling reaffirmed. Doctrine 6 may
permanently remain Doctrine-tier (not transitional). This is
constitutionally valuable — clarifies Doctrine 6's
constitutional identity.

**4. P3 priority elevated to PRIMARY**

HARD/SOFT formalization (P3) becomes primary Doctrine 6
growth path. P1 (style-engine) verdict eliminates one
candidate ubiquity-advancement pathway.

**5. Style-engine bounded context character clarified**

Style-engine is **Compiler/runtime + Resolution-heavy bounded
context**, not Doctrine 6 territory. This clarifies KB
bounded context character mapping.

### Mediation audit criterion impact (post-this-retro)

Post-this-retro Mediation criteria status:

| criterion | pre-this-retro (Phase 8.6) | post-this-retro | improved? |
|---|---|---|---|
| 1 — Context PRESENCE ≥ 4 | 5 contexts | 5 contexts (unchanged) | (no) |
| 2 — Architectural variants ≥ 2 | 8 mechanisms + HARD/SOFT | 8 mechanisms + HARD/SOFT (unchanged) | (no) |
| 3 — Intra-context density ≥ 1 | 2 contexts | 2 contexts (unchanged) | (no) |
| 4 — Q10 sub-pattern check | architectural mode + composite observed | + governance-architectural observation | (refined) |
| 5 — Forward + retro both | 6F + 2R | **6F + 3R** (this retro adds — but DIVERGENT, not confirming) | (formally improved; substantively neutral) |
| 6 — Gating abstraction independence | 8 mechanisms | 8 mechanisms (unchanged) | (no) |
| 7 — Structural consequence | 8 debt classes + composite | + governance-architectural distinction | (refined) |

> **Mediation evidence post-this-retro**: Forward + retro count
> increases (6F + 3R), but retro is DIVERGENT not confirming.
> Substantively, this retro REINFORCES Doctrine-tier
> appropriateness rather than advancing KB-Wide LAW
> candidacy.

> **Phase 8.10+ re-re-audit prospects**: NOT ADVANCED via
> Compiler/runtime test. Alternative pathways (P3 SOFT
> instances, P2 Composition runtime, Law 1 independence)
> become primary.

### KB-wide pattern recurrence updates (retro additions)

**Doctrine 6 architectural ubiquity**: 3/5 standard categories
**unchanged + Compiler/runtime category VERIFIED ABSENT**
in style-engine.preset-materialization (1 specific chunk).

**Style-engine bounded context Doctrine 6 character**:
DIVERGENT (1 chunk verified). Style-engine character:
Compiler/runtime + Resolution-heavy.

**Governance-architectural character distinction**: NEW
observation surfaced. Cross-context verification candidates:
- interactivity bounded context (likely Computational-
  architectural)
- data-layer bounded context (likely Computational-
  architectural)
- site-building bounded context (potentially Composition-
  architectural)

### Constitutional principle (retro-derived)

> **Honest divergent verdicts strengthen constitutional
> integrity.** Refusing to force-fit a doctrine onto a chunk
> where it doesn't structurally apply is METHODOLOGICALLY
> SUPERIOR to confirming weak/spurious manifestations. Negative
> evidence clarifies doctrinal boundaries; clarified
> boundaries enable precise future audits.

This audit demonstrates KB's **divergent-verdict discipline**:
honest "Doctrine 6 doesn't apply here" is more constitutionally
valuable than forced "Doctrine 6 applies weakly here."

### Comparison: REST retro vs Schema retro vs this retro

| dimension | REST retro (2026-05-10) | Schema retro (2026-05-10) | this retro (2026-05-10) |
|---|---|---|---|
| Verdict | ADDITIVE (NEW 6g sub-element) | HYBRID/ADDITIVE (NEW 6h SOFT sub-element) | **DIVERGENT** |
| Doctrine 6 evidence | strong (composite manifestation) | strong (SOFT mode observation) | **absent** |
| Mediation criterion impact | criterion 5 RESOLVED | criterion 1 + 5 + 6 advanced | criterion 5 formally only (substantively neutral) |
| Doctrine 6 character coverage | Authority federation (advanced) | Schema authority (advanced) | Compiler/runtime (VERIFIED ABSENT) |
| Constitutional value | promotion advancement | architectural ubiquity advancement | **boundary clarification** |
| Honest finding | confirming + extending | confirming + new mode | **divergent (honest negative)** |

**3 retros × 3 outcome types**: ADDITIVE / HYBRID/ADDITIVE /
DIVERGENT. KB demonstrates retro discipline produces full
verdict spectrum, not uniformly-confirming results.

### Anticipated next chunks (post-this-retro)

1. **`block-authoring.supports-field` Q9 retro (P3)** — NOW
   ELEVATED TO PRIMARY PRIORITY. HARD/SOFT formalization is
   Doctrine 6's primary growth path post-this-retro.

2. **`interactivity.directive-protocol` Q9 retro (P4)** —
   Bridge Pattern Recurring (cross-context) verification +
   potential governance-architectural character verification
   (likely Computational-architectural, parallel to
   style-engine).

3. **`site-building` chunks Q9 retro (P2)** — Composition
   runtime category test; if also DIVERGENT, Doctrine 6
   architectural ubiquity ceiling at 3-4/5 confirmed
   permanent.

4. **Governance-architectural character cross-context
   verification** — explicit test across multiple bounded
   contexts to determine whether character distinction warrants
   formalization.

5. **Mediation Doctrine 6 KB-Wide LAW re-re-audit (Phase
   8.10+)** — DEFERRED until P3 + P2 results clarify whether
   ubiquity advance possible.

Recommended: **`block-authoring.supports-field` Q9 retro
(P3 elevated to PRIMARY)** — single retro produces (a) HARD/
SOFT formalization potential via 2nd-3rd SOFT instances, (b)
Doctrine 6 internal architecture deepening, (c) Phase 8.10+
re-re-audit alternative pathway advancement.

### Status updates

- This file's overall `status` remains `stable` (original
  evaluation preserved).
- Retro patch adds Q9 retro DIVERGENT verdict + governance-
  architectural character observation + Mediation criterion
  impact assessment + Doctrine-tier permanence
  strengthening.
- Original chunk content (lines 1-491) UNCHANGED; this retro
  is purely additive at end of file.
