---
rule_id: style-engine.cascade-aggregation
domain: style-engine
topic: compiler-pipeline
field_cluster: cascade-arbitration
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: css
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-style-engine/
    section: "@wordpress/style-engine — aggregation, deduplication, output strategies"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles/
    section: "Global Settings & Styles — output composition, override layers"
    captured: 2026-05-09
  - url: https://make.wordpress.org/core/2022/11/14/registering-block-styles-server-side-in-wordpress-6-1/
    section: "Server-side block styles registration & lazy loading (load_separate_block_assets)"
    captured: 2026-05-09
related:
  - style-engine.preset-materialization        # stages 1-5 of materialization pipeline; aggregation = stage 6
  - style-engine.css-variable-emission         # variables participate in aggregation as scoped/global declarations
  - style-engine.generated-selectors           # per-instance scoped selectors that aggregation orders
  - theme-config.json-settings-residual-governance  # residual authorities that converge here
  - theme-config.json-styles-css               # escape hatch that bypasses aggregation
  - theme-config.json-settings-color (custom)  # settings.custom = third namespace participating in cascade
---

# RULE — cascade-aggregation — authority arbitration into a negotiated cascade graph

## WHEN

Asking how multiple style authorities (theme.json globals, per-block
supports, generated runtime selectors, inline per-instance styles,
escape-hatch CSS, user customizations, core block library defaults)
combine into the final stylesheet a browser receives. Use this
chunk to understand:

- Why "stylesheet order" is the wrong frame (aggregation is
  authority arbitration, not concatenation).
- How multiple partially-autonomous authorities compete for
  realization ownership of any given property at any given
  rendered element.
- Why CSS specificity in Gutenberg is best read as an
  **authority negotiation protocol**, not a presentation detail.
- Why generated selectors / inline styles / escape hatches each
  exist (they encode different authority arbitration roles).
- How the style engine's role as a compiler delegating to the
  browser cascade engine determines what aggregation can and
  cannot decide.

This is the **capstone chunk** of the style-engine bounded
context. After this + (planned) wrapper-attributes retro patch,
the bounded context's structural backbone is complete:
declaration → registration → emission → resolution → binding →
**aggregation** → browser computation.

## SHAPE

### Multi-origin CSS authority sources (NOT a single stylesheet system)

```
authority source              emission mode                     scope
---------------------------------------------------------------------
core block library            baseline default rules            global
theme.json globals            aggregated stylesheet             global → scoped
per-block supports            scoped emitted rules              per-block-type
generated selectors           runtime synthesized rules          per-instance
per-instance inline styles    direct attachment to element       single element
styles.css escape hatch       author CSS at scoped position      varies
settings.custom variables     CSS variable declarations          scope of declaration site
user customizations           late override layer (Site Editor)  global, highest theme priority
plugin-registered styles      plugin stylesheet                  varies
```

Gutenberg is NOT a single-stylesheet system — it is **multi-origin
CSS graph orchestration**. Each authority source emits CSS through
its own pathway with its own scoping rules; aggregation is the
arbitration of these competing emissions into a final cascade.

### Authority arbitration ordering (cascade hierarchy)

The cascade orders authorities roughly by escalation:

```
1. Browser default styles                    (lowest)
2. Core block library defaults
3. Theme stylesheet (style.css)
4. Theme.json compiled styles
5. Block supports emitted styles
6. Generated selector rules (.wp-container-*, .wp-elements-*)
7. Settings.custom variable declarations (scoped)
8. Styles.css escape hatch
9. User customizations (Site Editor)
10. Per-instance inline styles                (highest)
```

This ordering is achieved through a combination of source order,
selector specificity, and scoping. ⚠ Exact ordering details
(particularly user-customizations vs styles.css priority,
plugin-registered styles position, lazy-loaded block CSS
insertion point) require source verification.

### What aggregation produces

Final output to browser typically combines:
- A single compiled stylesheet (theme.json + supports + generated
  rules + escape hatches + user overrides) emitted in `<head>` or
  similar early position.
- Per-instance `style="..."` attributes on individual block
  wrapper elements.
- Possibly lazy-loaded per-block stylesheets (when
  `load_separate_block_assets` is enabled).
- Editor canvas (iframe) variant with editor-specific aggregation.

### What this is NOT

- NOT stylesheet concatenation. The order is authority-driven,
  not file-driven.
- NOT a single compiler output target. Multiple emission modes
  coexist (stylesheet / inline / variable / scoped class).
- NOT deterministic value resolution. The cascade is graph-level
  arbitration; the final per-element value is computed by the
  browser, not by Gutenberg.
- NOT user-extensible at the cascade ordering level. Plugins
  contribute via existing emission pathways; they cannot insert
  new cascade priority tiers.

## REQUIRES

- All upstream pipeline stages (declaration through binding) have
  produced their outputs.
- The browser CSS engine for final cascade resolution and
  computed-style derivation.
- For SSR: PHP-side style engine compilation runs before HTML
  emission.
- For editor: client-side style engine compilation runs in the
  editor canvas iframe.
- ⚠ Exact mechanisms (hooks, filters, output buffering, cache
  layers) — verification-needed.

## INVARIANTS

### 1. Aggregation = authority arbitration, NOT concatenation

The most load-bearing invariant of this chunk:

> aggregation ≠ concatenation
> aggregation = authority arbitration

Reading aggregation as "stylesheet order" misses the entire point.
Aggregation is **the negotiation between multiple partially-
autonomous authorities competing for realization ownership of
any given CSS property at any given rendered element**.

When two authorities both target `color` on the same element,
aggregation determines which wins via cascade rules (specificity,
source order, scoping). Reading this as "concatenation order
matters" obscures that:
- Specificity matters as much as order.
- Scoping (per-instance class) matters as much as both.
- Source authority intent matters because override mechanisms
  are designed around it (inline styles win to allow per-instance
  override; generated selectors scope to prevent global pollution;
  escape hatches bypass aggregation entirely).

### 2. Multi-origin CSS graph orchestration

Gutenberg is NOT a single-stylesheet system. The 9 authority
sources in SHAPE each have distinct:
- Emission pathway (stylesheet vs inline vs scoped class vs
  variable declaration)
- Scoping rule (global vs per-type vs per-instance vs per-element)
- Origin authority (core / theme / block / instance / user / plugin)

Aggregation reconciles all 9 sources into a coherent cascade.
The output is a multi-origin CSS graph, not a single linear
document. Treating it as one stylesheet defeats reasoning about
override behavior.

### 3. Aggregation converges previously distributed authorities

Earlier KB chunks documented authorities as distributed surfaces:
- settings.* (theme-config) — registry / governance
- supports.* (block-authoring) — per-block exposure
- styles.* (theme-config) — realization declarations
- generated selectors (style-engine) — runtime synthesis
- variable emission (style-engine) — value graph synthesis
- preset materialization (style-engine) — transformation lifecycle
- residual-governance (theme-config) — bridges and escape hatches

Aggregation is where these CONVERGE. Each authority's emission
arrives at the cascade via its own pathway; aggregation arbitrates
overlapping ownership claims. This chunk depends on those earlier
chunks having documented WHAT each authority emits and what it
governs.

### 4. Cascade ordering encodes authority hierarchy

The cascade order in SHAPE is not arbitrary — it encodes a
deliberate authority hierarchy:

| priority direction | encodes |
|---|---|
| browser defaults < core defaults | "core opinions override browser" |
| core < theme stylesheet < theme.json | "explicit theme intent overrides core" |
| theme < block supports | "per-block opt-in overrides theme baseline" |
| block supports < generated selectors | "per-instance scoping overrides per-type" |
| generated selectors < user customization | "user choice overrides theme" |
| user customization < per-instance inline | "explicit per-element override is final" |

Each tier is a deliberate authority statement. Misordering
breaks the authority model — e.g., putting per-instance below
user customization would prevent users from overriding their
own per-block settings.

### 5. Specificity is governance, not presentation

CSS specificity in Gutenberg is best read as an **authority
negotiation protocol**:

- High-specificity selectors (`.wp-block-button .wp-element-button`)
  encode "this scope's value wins over generic body-level
  defaults."
- Per-instance generated selectors (`.wp-container-7a3b1c2d`)
  encode "this instance has authority over its sibling instances."
- `:root`-scoped variables encode "this is a theme-wide token
  contract."
- Inline `style=""` attributes encode "this element's authority
  is final regardless of selector specificity."

Specificity is NOT just a CSS calculation rule — it is the
**encoding format Gutenberg uses to communicate authority
relationships to the browser cascade engine**. Authoring CSS
that fights specificity is fighting the authority encoding,
which usually means the wrong authority is being expressed.

### 6. Inline styles are authority escalation paths

Inline `style="..."` attributes on block wrapper elements are
NOT just "performance shortcut" or "easier emission". They
serve a specific authority role:

> When a value cannot be expressed as a generalizable rule
> (because it's per-instance unique, or because it must
> definitively override generated selectors / theme defaults /
> user customizations), the engine ESCALATES to inline emission.

Inline styles are the highest authority tier because they
encode "this exact element, this exact value, no negotiation."
Reading them as "the engine couldn't be bothered to make a
class" misses that inline IS the appropriate emission for
authority that cannot be reduced to a class scope.

### 7. Generated selectors reduce global specificity pressure

Per-instance generated selectors (`.wp-container-{id}` /
`.wp-elements-{uuid}`) serve an authority-arbitration role
beyond simple scoping:

- Without per-instance scoping: per-instance values would
  require either `!important` (escalation in a different
  direction) or inline styles (heaviest emission) to override
  global rules.
- With per-instance scoping: the generated selector's high
  specificity naturally wins over global rules without
  escalation.

This **reduces global specificity pressure** — the engine
doesn't need to inflate global rule specificity to hold ground
against per-instance overrides; instead, per-instance overrides
get their own scoped namespace. The global cascade stays
tractable.

This is structural, not optimization. Without generated
selectors, every per-instance value would either escalate to
inline or pollute the global selector namespace.

### 8. Escape hatches intentionally bypass aggregation layers

Three documented escape hatches across KB:

| escape hatch | bypasses |
|---|---|
| `settings.custom` (custom variable namespace) | preset registry + editor UI consumption |
| `styles.css` (literal CSS string) | constrained-grammar realization |
| `styles.blocks.{name}.css` (per-block CSS) | per-block constrained realization |

These do NOT integrate with the aggregation arbitration in the
same way managed authorities do. They emit at their declared
position in the cascade order but **opt out of authority-
specific arbitration semantics** (e.g., styles.css can target
selectors generated rules cannot reach; settings.custom can
introduce variables outside preset categories).

Escape hatches confirm that Gutenberg is **partially-governed**
— it provides arbitration for managed authorities AND emission
pathways for unmanaged author intent. This is by design.

### 9. Cascade is the execution engine — style-engine compiler ↔ browser cascade VM

This invariant elevates an earlier framing (preset-materialization
established "browser computed style = FINAL realization boundary")
to its full structural form:

> Gutenberg does not execute styling logic directly.
> Style-engine compiles competing authorities into a graph
> delegated to the browser cascade engine for execution.

| role | system |
|---|---|
| Compiler | style-engine (variable emission + selector synthesis + materialization + aggregation) |
| Runtime VM | browser CSS cascade engine |
| Source language | theme.json + supports + styles.* + block attributes |
| Target language | CSS (with custom property indirection) |
| Linker | aggregation (this chunk) |
| Execution | browser computation (out of scope) |

This compiler/VM split explains why every style-engine chunk
has had verification-needed sections: many runtime behaviors
are in the VM (browser), not the compiler (style-engine).
Gutenberg's authority ends at emission; everything past
aggregation is browser-owned.

### 10. Aggregation is partial, not total — final value owned by browser

Even after aggregation, the browser cascade engine performs:
- Variable substitution (`var(--wp--preset--*)` resolution at
  use site)
- Inheritance computation
- Unit normalization (px / rem / em → computed pixels)
- Specificity arbitration with browser defaults / user agent
  stylesheets
- !important precedence
- Author-loaded stylesheet (e.g., user-installed extensions)

Gutenberg does NOT control these. The aggregation output is the
**input** to a system Gutenberg doesn't own. Practical
implications:
- "This preset always renders as exactly #0055ff" is wrong; user
  CSS or browser overrides can change it.
- !important in user CSS will defeat all Gutenberg authorities.
- Inheritance from non-Gutenberg ancestors can leak into
  blocks.

The compiler/VM boundary is permeable in the VM direction — the
VM can be influenced by sources outside the compiler's awareness.

## VERIFICATION NEEDED

Style-engine bounded context epistemic limit applies, with
particular density in this chunk:

- Editor canvas (iframe) vs frontend ordering — are aggregation
  ordering rules identical?
- SSR vs client hydration — does the compiled stylesheet match
  what the editor canvas would synthesize for the same content?
- Style deduplication — when the same rule could be emitted by
  multiple authorities, which wins / does the engine deduplicate?
- Lazy-loaded block CSS (`load_separate_block_assets`) insertion
  point in the cascade — does it preserve aggregation ordering?
- Global styles cache invalidation — when does the aggregated
  output regenerate?
- Block supports inline escalation heuristics — when does an
  emitted value go inline vs scoped class?
- Theme.json merge precedence — when child + parent themes both
  declare, exact merge rules.
- Style engine batching — does the engine emit per-block or
  aggregate at request scope?
- Plugin-registered stylesheet position — where in the cascade
  do plugins land?
- User customization emission — is the "global styles" post-type
  output a separate stylesheet or merged into theme.json
  compilation?
- Editor preview vs frontend behavior — does the editor see a
  different cascade than the frontend renders?

For practical decisions: trust empirical observation
(`getComputedStyle` in editor + frontend) over inferred rules
when authority arbitration matters.

## ANTIPATTERNS

- ❌ Reading aggregation as "stylesheet load order matters."
  This is the framing trap. Aggregation is authority arbitration;
  load order is one factor among specificity / scoping / origin.
- ❌ Trying to override Gutenberg styles with `!important` as
  default approach. Defeats specificity arbitration; use
  appropriate authority pathway (theme.json for globals,
  styles.css for escape, per-instance for overrides).
- ❌ Authoring CSS that targets generated selector names
  (`.wp-container-7`). Defeats per-instance scoping's purpose;
  also breaks across renders since generated names are ephemeral.
- ❌ Emitting per-block defaults via plugin CSS expecting them
  to override theme.json. Plugin CSS lands at a specific cascade
  tier (verification-needed for exact position); overriding
  theme intent requires theme.json filter or styles.css.
- ❌ Treating editor canvas styles and frontend styles as
  guaranteed-equivalent. They share aggregation logic but the
  canvas iframe + editor-only adjustments may shift outcomes.
  Always test in both contexts.
- ❌ Conflating "the engine compiled it" with "the browser will
  apply it". Compiler emits; browser executes. Browser-side
  factors (user CSS, browser defaults, inheritance) can override.
- ❌ Using inline styles as the default emission for
  per-instance values you intend to be theme-overridable.
  Inline is the highest authority — putting overridable values
  there defeats the user's expected ability to customize.
- ❌ Adding new authority sources via plugin CSS when an existing
  emission pathway exists. Plugins should hook into supports /
  styles / settings registration mechanisms; bypassing creates
  cascade conflicts that aggregation cannot arbitrate cleanly.
- ❌ Believing aggregation produces a single canonical stylesheet
  every browser sees. Multiple emission modes coexist; inline
  styles, lazy-loaded block CSS, editor canvas variants all
  coexist with the main aggregated output.
- ❌ Reading specificity battles as bugs. They are usually
  symptoms of fighting an authority encoding — the fix is
  emitting at the appropriate authority tier, not winning the
  specificity arms race.

## RELATED

- `style-engine.preset-materialization` — stages 1-5 of the
  materialization pipeline. Aggregation is stage 6 of the same
  pipeline. This chunk depends on materialization having
  established the per-stage transformations whose outputs are
  what aggregation arbitrates.
- `style-engine.css-variable-emission` — variable declarations
  participate in aggregation as scoped or global declarations.
  Variable scope rules interact with cascade arbitration at this
  layer.
- `style-engine.generated-selectors` — per-instance scoped
  selectors are an authority-arbitration mechanism (invariant 7
  here). Generated-selectors documents what they ARE; this chunk
  documents what they DO in cascade arbitration.
- `theme-config.json-settings-residual-governance` — residual
  authorities (settings.custom variable namespace, useRoot-
  PaddingAwareAlignments runtime flag, position/lightbox
  governance) all converge here. The residual-governance batch
  pre-documented their authority surfaces; this chunk explains
  where they land.
- `theme-config.json-styles-css` — primary escape hatch
  bypassing managed aggregation. Documented in styles.css chunk;
  this chunk frames its cascade role (escape from arbitration).
- `block-authoring.markup-representation` — serialized attributes
  carrying per-instance values that aggregate as inline styles
  or scoped selector emissions.
- `block-authoring.wrapper-attributes` — the carrier element
  receiving aggregation outputs (classes from supports, inline
  styles from per-instance, generated class from selector
  synthesis). (planned retro patch may re-frame this chunk
  through the now-complete style-engine ontology.)

## META

**Capstone chunk for style-engine bounded context.**

After this chunk, the style-engine bounded context backbone is
structurally complete:

| stage | chunk | status |
|---|---|---|
| 1. Authority declaration | settings.* / supports.* / styles.* | ✓ (theme-config + block-authoring) |
| 2. Preset registration | preset-materialization | ✓ |
| 3. Variable emission | css-variable-emission | ✓ |
| 4. Reference resolution | preset-materialization | ✓ |
| 5. Selector binding | generated-selectors | ✓ |
| 6. **Cascade aggregation** | **cascade-aggregation (this chunk)** | **✓** |
| 7. Browser computation | (out of scope — browser owns) | n/a |

**Bounded context closure framing:**

After this chunk + (planned) wrapper-attributes retro patch:

```
wrapper-attributes
  → generated-selectors
  → variable-emission
  → materialization
  → aggregation
```

The above forms a coherent runtime compiler system. The KB-level
framing this chunk completes:

> Gutenberg is not a "block schema system."
> Gutenberg is a **schema-driven runtime style graph compiler**:
> distributed style compiler + cascade orchestrator delegating
> execution to the browser CSS engine.

This re-definition is the structural payoff of the style-engine
bounded context. Block-authoring + theme-config established
the SCHEMA SURFACE; style-engine established the COMPILER /
ORCHESTRATOR / VM SPLIT. Together they describe Gutenberg as
a multi-layer authority-arbitration system whose final execution
target is a browser cascade engine.

**DSL extension justification (style-engine bounded context):**

This chunk is the densest VERIFICATION NEEDED case in KB. The
density is structural to the chunk's character — aggregation
sits at the compiler-VM boundary where many behaviors are
implementation-derived. Treating verification-needed as
structural rather than as documentation gap is the appropriate
epistemic stance for the boundary.

**Anticipated next chunks (post-style-engine):**
- wrapper-attributes retro patch (re-frame through completed
  style-engine ontology — light work, high coherence payoff)
- block-authoring residual closure (block.json minor fields:
  styles / example / blockHooks / version + bindings as
  separate spike)
- bindings (WP 6.5+ — paradigm bridge into interactivity
  bounded context)
- interactivity API (paradigm jump to client runtime
  authority — separate bounded context)

## RETROACTIVE REFRAMING (post-Resolution-Surface-surfacing)

**Status note**: This section was added 2026-05-09 after
site-building.template-hierarchy-and-resolution surfaced
"Resolution Surface" as a NEW candidate structural pattern.
The original chunk above frames cascade-aggregation as
"authority arbitration into a negotiated cascade graph."
Post-surfacing, the same mechanism reads as **simultaneous
arbitration AND resolution** — and the relationship between
these two concepts becomes structurally important.

The original chunk is preserved; this section adds the
post-Resolution-Surface ontological re-reading.

**KB pattern**: Fourth explicit RETROACTIVE REFRAMING section
in KB:
- wrapper-attributes (post-style-engine closure)
- dynamic-rendering (post-Phase-7-capstone)
- markup-representation (post-Phase-7-capstone)
- **cascade-aggregation (post-Resolution-Surface-surfacing —
  THIS SECTION)**

**Methodological note** (per user direction): retroactive
verification of NEW candidates (Resolution Surface here) is
KB methodology for distinguishing **novel discovery** from
**latent revelation** — patterns named in one chunk may
already have been operating in earlier chunks under different
vocabulary.

> **Was Resolution Surface a NEW discovery, or did**
> **site-building merely name a substrate that was already**
> **here?**

This section evaluates honestly.

### Reframing — cascade-aggregation through Resolution Surface lens

Pre-Resolution-Surface reading: cascade-aggregation describes
"authority arbitration into a negotiated cascade graph" — the
MECHANISM of multi-source CSS reconciliation.

Post-Resolution-Surface reading:

> The CSS cascade exhibits BOTH **arbitration** (how competing
> authorities are EVALUATED) AND **resolution** (how one
> authority becomes ACTUALIZED in the rendered DOM as
> computed style). These are STRUCTURALLY DIFFERENT
> operations even though they appear in the same mechanism.

The cascade is not "just" arbitration or "just" resolution —
it is **arbitration FOLLOWED BY resolution**:

```
Multiple competing authorities:
  - core block defaults
  - theme.json compiled rules
  - per-block supports emissions
  - generated selectors
  - inline styles
  - styles.css escape hatch
  - user customizations
   ↓
ARBITRATION (which competing authority WINS per element/property?):
  - source order
  - selector specificity
  - !important precedence
  - cascade origin layers
   ↓
RESOLUTION (winning authority ACTUALIZED into computed style):
  - variable substitution
  - inheritance computation
  - unit normalization
  - browser CSS engine final value derivation
   ↓
Computed style applied to rendered DOM
```

**The cascade is a 2-stage operation**: arbitration produces
a winning declaration; resolution actualizes it into computed
DOM state. Both stages are present; calling cascade-aggregation
"arbitration" only is partially accurate.

### RETROACTIVE INVARIANTS

#### A. Arbitration and Resolution are structurally distinct operations within cascade-aggregation

The original chunk's "authority arbitration" framing is
accurate but **structurally incomplete**. Post-Resolution-
Surface analysis:

| operation | character | example in cascade |
|---|---|---|
| **Arbitration** | how competing authorities are EVALUATED | source order + selector specificity + !important determine WINNER |
| **Resolution** | how winner is ACTUALIZED into DOM | variable substitution + inheritance + unit normalization produce COMPUTED STYLE |

These are **paired operations**, not synonymous. The cascade
involves both. Reading cascade-aggregation as
"arbitration only" obscures resolution; reading it as
"resolution only" obscures arbitration.

#### B. Resolution Surface manifests in cascade-aggregation (Recurring promotion candidate)

> **Resolution Surface candidate** (surfaced in
> site-building.template-hierarchy-and-resolution):
> "structural actualization via precedence-based selection
> from competing candidates"

This describes **the resolution stage of cascade**:
- Competing CSS rules = candidates
- Specificity + order = precedence
- Computed style = actualization

CSS cascade resolution IS a Resolution Surface manifestation.
The mechanism was always present; site-building chunk merely
named the structural pattern.

**Verification result**: Resolution Surface is **NOT a NEW
candidate**. It is **LATENT structure** that was operating in
cascade-aggregation but lacked explicit naming until
site-building surfaced it.

#### C. Arbitration ↔ Resolution distinction is a candidate doctrinal refinement (NOT yet patched)

The retro analysis suggests:

> **Arbitration Compiler and Resolution Surface may be**
> **STRUCTURALLY PAIRED operations**, not competing
> candidates:
>
> - **Arbitration** = "How competing authorities are
>   EVALUATED" (selection logic, precedence rules,
>   conflict resolution)
> - **Resolution** = "How one authority becomes ACTUALIZED
>   from evaluated candidates" (downstream operationalization,
>   value materialization)

If this distinction holds, Resolution Surface is **downstream
operationalization** of Arbitration Compiler outputs —
mechanisms exhibiting Arbitration may also exhibit Resolution
as the actualization step.

**Cross-mechanism verification**:

| mechanism | arbitration stage | resolution stage |
|---|---|---|
| **CSS cascade (this chunk)** | specificity + order + !important determine winner | variable substitution + inheritance + unit normalization → computed style |
| **template hierarchy** (site-building) | hierarchy logic determines candidate template list | first existing template wins → resolved template renders |
| **map_meta_cap** (capabilities-and-roles) | meta cap → primitive caps + contextual conditions | allow / deny adjudication outcome |
| **block filter chain** | priority arbitrates filter execution order | composed filter outputs → final block settings |
| **menu_position arbitration** | numeric position + filters arbitrate menu order | ordered menu rendered |

In ALL 5 mechanisms, both stages are present. This suggests
**Arbitration ↔ Resolution as PAIRED operations** is a
candidate KB-level doctrinal refinement.

**Status**: candidate doctrinal refinement, **NOT yet
patched** to structural-patterns spec. Per Phase 7.5
Doctrine 2 (surfaced not constitutionalized) and methodology
of "patch only after multiple verifications": defer spec
update until 2nd retro verification (capabilities-and-roles
retro candidate) confirms the distinction.

#### D. Cascade-aggregation framing accuracy: enrichment, not contradiction

The original chunk's framing ("authority arbitration into a
negotiated cascade graph") is NOT WRONG. Post-Resolution-
Surface analysis ENRICHES the framing rather than
contradicting it:

- Arbitration framing emphasizes the EVALUATION stage
- Resolution framing emphasizes the ACTUALIZATION stage
- Cascade-aggregation involves BOTH stages

This is constitutional enrichment, not correction. The
original chunk remains accurate; the retro adds additional
structural visibility that the surfacing event made possible.

This is the standard RETROACTIVE REFRAMING pattern:
**bounded-context closure / candidate-surfacing reveals
deeper structure that was always present**. Compare:
- wrapper-attributes retro: revealed wrapper as
  authority transport surface
- dynamic-rendering retro: revealed render_callback as
  server-side authority projection
- markup-representation retro: revealed HTML as universal
  continuity substrate
- **cascade-aggregation retro (this)**: reveals cascade as
  paired Arbitration + Resolution operation

### Constitutional implications

**1. Resolution Surface promotion path adjusted**:

```
Resolution Surface candidate (verification update post-retro):
   Previous status: Surfaced (1 chunk: site-building)
   Retro evidence: cascade-aggregation contains LATENT
                   resolution stage (always present, newly
                   visible)
   Updated status: Recurring (cross-context PRESENCE confirmed)
                   - site-building (explicit)
                   - style-engine (latent, retro-verified)
   Promotion: Surfaced → Local (admin-ui side density needed)
              OR direct cross-context PRESENCE acknowledgment
              given retro evidence is structurally clear
```

⚠ **Promotion discipline question**: does retroactive
verification count toward Recurring promotion, or does
recurrence require chunks AUTHORED with the candidate in
mind?

**Recommendation** (per Phase 7.5 Doctrine 3 — Epistemic
Integrity): retroactive verification IS valid evidence if
the structural pattern was genuinely present in the original
chunk's content. Forcing chunks to be re-authored with the
new candidate would be over-constraint.

**Therefore**: Resolution Surface promotion path:
- Surfaced (site-building, explicit naming) +
  cascade-aggregation (latent, retro-verified) =
  **2-context cross-context PRESENCE confirmed via retro
  verification**
- Recurring (cross-context) status candidate, pending
  capabilities-and-roles retro verification (2nd retro)

**2. Arbitration ↔ Resolution paired-operations refinement**:

This candidate refinement, if confirmed by capabilities-and-
roles retro, may warrant Phase 7.6 spec patch:

```
Possible Phase 7.6 refinement:
   Arbitration Compiler (KB-Wide LAW) +
   Resolution Surface (candidate; possibly downstream
                       operationalization of Arbitration)
   = Paired operations
```

NOT YET PATCHED. Defer until 2nd retro verification.

**3. Methodological observation — RETROACTIVE candidate
verification as KB technique**:

This retro establishes a NEW KB methodology pattern:
**retroactive verification of NEW candidates against earlier
chunks**. When a candidate surfaces in chunk N, retro
verification of chunks 1..(N-1) can determine if the
candidate is genuinely novel or latent.

Pattern structure:
1. Candidate surfaced in chunk N
2. Identify earlier chunks where the pattern MAY be present
3. Retroactively verify presence (true/false per chunk)
4. Update candidate status based on retro evidence
5. If multiple retros confirm: candidate may directly reach
   Recurring (cross-context) without explicit forward
   recurrence

This methodology may warrant explicit DSL spec recognition
(Phase 7.6 candidate consideration).

### KB-level coherence payoff

This retro produces structural narrative:

```
Resolution Surface origin story:

1. site-building.template-hierarchy-and-resolution chunk
   (2026-05-09): Resolution Surface candidate surfaced from
   composition-native bounded context.
2. cascade-aggregation retro (2026-05-09): Resolution Surface
   verified as LATENT in style-engine — cascade has always
   been arbitration + resolution paired operation.
3. (anticipated) capabilities-and-roles retro: would test
   whether map_meta_cap also exhibits paired arbitration +
   resolution character.
4. (anticipated) Phase 7.6 spec patch: if both retros confirm,
   formalize Arbitration ↔ Resolution as paired operations
   doctrine.

Result: Resolution Surface NOT NEW; it is latent structure
that became NAMEABLE after site-building surfaced the
vocabulary.
```

This mirrors KB's "discovery → retroactive revelation →
constitutional patch" maturation pattern (per
dynamic-rendering retro's framing of Interactivity API as
"latent runtime architecture becoming explicit").

**Anticipated KB-level framing extension** (deferred until
2nd retro confirms):

> **KB pattern**: significant constitutional patterns are
> often NOT discovered fresh — they are NAMED after surfacing
> in one chunk reveals their latent presence in earlier
> chunks. Discovery → naming → retroactive revelation →
> constitutional formalization.

### Methodological discipline preserved

This retro:
- Surfaced the Arbitration ↔ Resolution distinction (NEW
  doctrinal candidate)
- Did NOT promote it to formalized doctrine (insufficient
  verification)
- Did NOT patch structural-patterns spec
- Documented anticipated Phase 7.6 patch consideration
- Refused to over-claim novelty for Resolution Surface
  (acknowledged latent character)

> **KB methodological maturity**: retroactive verification
> deepens existing chunks AND constrains promotion claims.
> The same evidence that strengthens a candidate's recurrence
> also constrains its novelty claim. Both strengthening and
> constraining are evidence-based; both serve constitutional
> integrity.
