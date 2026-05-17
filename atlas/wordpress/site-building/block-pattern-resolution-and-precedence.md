---
rule_id: site-building.block-pattern-resolution-and-precedence
domain: site-building
topic: structural-resolution
field_cluster: composition-runtime
wp_min: "5.5"
wp_recommended: "6.4+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-patterns/
    section: "Block Patterns API — registration + categories + contextual suggestion"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/register_block_pattern/
    section: "register_block_pattern() — pattern registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/themes/patterns/
    section: "Theme patterns — /patterns/ folder convention + filesystem-coupled metadata"
    captured: 2026-05-09
  - url: https://make.wordpress.org/core/2022/05/02/exposing-theme-block-patterns-to-the-pattern-directory/
    section: "Theme patterns + Pattern Directory federation"
    captured: 2026-05-09
related:
  - site-building.template-hierarchy-and-resolution # paired chunk — Resolution density build within site-building
  - theme-config.json-patterns                      # registration substrate counterpart (theme.json patterns array)
  - block-authoring.variations                       # Q9 RETRO TRIGGER — likely latent Resolution
  - block-authoring.transforms                       # Q9 RETRO TRIGGER — likely latent Resolution
  - plugin-dev.register-block-bindings-source        # cross-context federation (plugin-registered patterns)
  - plugin-dev.security-boundaries                   # capability gates for pattern editing
  - _meta.structural-patterns                        # Phase 7.5 + 7.6 patched spec applied; Doctrine 5 first live deployment
---

# RULE — block pattern resolution and precedence — composition-module structural resolution

## WHEN

Asking how WordPress determines which block patterns appear in
the inserter, how they are categorized, contextually suggested,
and ultimately selected by users for composition. Use this
chunk to understand:

- Pattern registration sources (theme `/patterns/` folder /
  PHP `register_block_pattern()` / theme.json patterns array
  / Pattern Directory) and their composition.
- Pattern categorization + contextual suggestion mechanics.
- Inserter exposure precedence (which patterns are surfaced
  for which contexts).
- The user-selection moment as Resolution stage.
- Why pattern resolution is not "list of patterns" but
  **multi-stage compositional resolution**.

This is the **second chunk in site-building bounded context**
and **first live deployment chunk under Phase 7.6 patched spec**
(Doctrine 5 — Arbitration ↔ Resolution Paired Operations
applied directly, not retroactively).

Strategic role: **Resolution Surface intra-context density
test within site-building**. After site-building.template-
hierarchy-and-resolution surfaced Resolution Surface, this
chunk tests whether site-building exhibits Resolution at a
DIFFERENT compositional layer (pattern-level, distinct from
template-level) — confirming whether site-building is
**stratified Resolution** territory.

The doctrinal backbone for this chunk:

> **Template hierarchy resolves page-level structure.**
> **Block pattern resolution governs compositional structural**
> **modules.**
> **Together, site-building may prove Resolution Surface as a**
> **composition-native operational law operating at multiple**
> **structural layers.**

The KB-level question this chunk tests:

> Is Resolution Surface in site-building **singular**
> (template-only) or **structurally layered** (template +
> pattern + possibly navigation + component)?

## SHAPE

### A. Pattern registration sources — distributed authority origins

Block patterns enter the inserter from **multiple authority
sources**:

```
Pattern authority sources:

1. Theme /patterns/ folder (filesystem)
   → PHP file with header annotations (since WP 6.0)
   → auto-registered by core's pattern loader
   
2. PHP register_block_pattern() calls
   → typically in plugin / theme init
   → manual registration with $properties array

3. theme.json patterns array
   → slug references to WordPress.org Pattern Directory
   → remote pattern federation (since WP 6.0)
   
4. Pattern Directory (WordPress.org)
   → community-curated patterns
   → opt-in via theme.json patterns slugs

5. wp_block post type (synced patterns)
   → user-created reusable patterns
   → live-linked across all instances

6. Plugin-registered patterns
   → register_block_pattern() in plugin code
   → cross-context federation
```

This is **Federation Pattern manifestation** at composition
layer (5+ context recurrence reinforced).

### B. Registration mechanics

```php
register_block_pattern(
    'my-plugin/hero-section',                // name (vendor/slug)
    array(
        'title'         => __( 'Hero Section', 'my-plugin' ),
        'description'   => __( 'Large hero with CTA', 'my-plugin' ),
        'content'       => '<!-- wp:cover --> ... <!-- /wp:cover -->',
        'categories'    => array( 'featured', 'header' ),
        'keywords'      => array( 'hero', 'cta', 'banner' ),
        'blockTypes'    => array( 'core/post-content' ),
        'postTypes'     => array( 'page', 'product' ),
        'viewportWidth' => 1200,
        'source'        => 'plugin',
    )
);
```

| property | role |
|---|---|
| `name` | unique vendor/slug identifier |
| `title` | display title in inserter |
| `description` | inserter description |
| `content` | block markup (the actual pattern) |
| `categories` | inserter category placement |
| `keywords` | search match terms |
| `blockTypes` | contextual suggestion (when these blocks selected) |
| `postTypes` | exposure restriction by post type |
| `viewportWidth` | preview rendering width |
| `source` | origin classification (theme/plugin/directory/etc.) |

### C. Resolution pipeline — Arbitration ↔ Resolution paired operations

This is the **Doctrine 5 first live deployment**:

```
Block pattern Resolution pipeline (per Doctrine 5):

ARBITRATION STAGE (distributed across multiple mechanisms):

   1. Source aggregation
      → all registered patterns from all sources collected
      → Federation Pattern manifestation
   
   2. Category organization
      → patterns grouped by categories array
      → category authority hierarchies arbitrate placement
   
   3. blockTypes contextual suggestion
      → when user selects core/heading, patterns with
        blockTypes: ['core/heading'] suggested
      → context-aware filtering
   
   4. postTypes exposure gating
      → patterns hidden for non-matching post types
      → exposure governance (Law 1 — Declaration ≠ Exposure)
   
   5. Inserter search arbitration
      → keyword search matches arbitrate visibility
      → user query produces candidate ranking

RESOLUTION STAGE (integrated at insertion site):

   6. User selection event
      → user clicks pattern in inserter
      → SELECTION ACTUALIZED INTO BLOCK TREE
      → composition becomes operational at this moment
```

**Architectural variant evaluation (Doctrine 5b):**

> **Block pattern resolution exhibits HYBRIDIZED architecture:**
> - **Distributed Arbitration**: source aggregation +
>   categorization + contextual suggestion + exposure gating +
>   search arbitration are STRUCTURALLY SEPARATED across
>   multiple mechanisms (registration handler, category
>   resolver, contextual filter, postType gate, search
>   matcher).
> - **Integrated Resolution**: user selection + insertion
>   into block tree happens as SINGLE ACTUALIZATION event
>   (selection IS resolution into operational composition).

This is **the first Hybridized Doctrine 5 instance documented
in KB**. Block pattern resolution is neither purely Integrated
(like CSS cascade) nor purely Distributed (like capabilities
adjudication) — it spans both architectures depending on
which stage is examined.

### D. Pattern Directory + theme bundled + plugin federation

```
Multi-source pattern federation (operational):

Pattern Directory (remote, opt-in via theme.json patterns slugs)
   ↓ + 
Theme /patterns/ filesystem (auto-registered)
   ↓ +
Plugin register_block_pattern() (programmatic)
   ↓ +
Synced patterns (wp_block post-type, user-created)
   ↓ +
theme.json patterns slugs (federation declarations)
   ↓
Single pattern registry → categorization → contextual suggestion → inserter exposure → user selection → insertion
```

The merge is **multi-source federation**. Plugin patterns
coexist with theme patterns coexist with directory patterns
coexist with synced patterns. No source dominates by default;
collisions resolve by registration name (last registration
wins for same name; namespaces prevent collisions).

### E. Failure surfaces — pattern resolution debt

```
Pattern resolution failure modes:

- pattern sprawl: too many registered patterns clutter inserter
- category collision: multiple plugins register same category
- contextual suggestion overload: blockTypes broad → suggestion noise
- postTypes restriction confusion: pattern visible/invisible
  unexpectedly per post type
- Pattern Directory dependency: theme.json patterns slug
  references depend on remote availability
- synced pattern fragility: editing synced pattern affects
  all instances; user surprise possible
- naming collision: vendor/slug prefix omission causes
  registration overrides
- pattern content invalidation: pattern content using
  block schema that has since changed
- block parser errors in pattern content: silent insertion
  failures
```

**Pattern resolution debt** (8th debt-pattern instance in KB;
2nd in site-building):

| debt mode | symptom |
|---|---|
| pattern sprawl | accumulated patterns clutter inserter |
| categorization collision | category slug overlaps cause confusion |
| contextual noise | over-broad blockTypes suggest patterns inappropriately |
| federation fragility | Pattern Directory remote unavailability |
| synced pattern surprise | editing one instance affects all |
| naming collisions | non-namespaced patterns override unpredictably |

8 instances × 4 bounded contexts. Governance debt continues
strengthening as anticipated meta-pattern.

## REQUIRES

- WP 5.5+ for `register_block_pattern()`.
- WP 6.0+ for `/patterns/` folder filesystem auto-registration.
- WP 6.4+ for theme.json patterns array (Pattern Directory
  slug federation).
- For theme-bundled patterns: `/patterns/{name}.php` with
  proper header annotations.
- For Pattern Directory access: site has internet connectivity
  + Pattern Directory available.
- Pattern names must be `vendor/slug` namespaced.
- For synced patterns: `wp_block` post type registered (default
  in WordPress core).
- Category registration via `register_block_pattern_category()`
  (separate registration step).
- ⚠ Specific behaviors: pattern caching, Pattern Directory
  fallback when offline, pattern visibility with custom roles,
  contextual suggestion algorithm details, search match
  ranking — verification-needed.

## INVARIANTS

### 1. Block pattern resolution is multi-stage compositional resolution, NOT simple pattern listing

The load-bearing reframing:

> Block pattern resolution is NOT "WordPress shows registered
> patterns in the inserter." It is **multi-stage
> compositional resolution** — patterns flow through
> registration → categorization → contextual suggestion →
> exposure gating → user selection → insertion as a structural
> Resolution pipeline.

Reading pattern resolution as "pattern listing" misses the
Doctrine 5 paired operations character.

### 2. Pattern resolution exhibits Hybridized Doctrine 5 architecture (FIRST in KB)

**FIRST documented Hybridized architecture** (per Doctrine 5b
discipline):

| stage | location | character |
|---|---|---|
| Source aggregation | core pattern loader | Distributed |
| Category organization | category resolver | Distributed |
| Contextual suggestion | inserter filter logic | Distributed |
| Exposure gating | postTypes matcher | Distributed |
| Search arbitration | inserter search | Distributed |
| **User selection + insertion** | **inserter UI + block tree mutation** | **Integrated** |

**Arbitration**: distributed across multiple mechanisms
(source/category/context/exposure/search).
**Resolution**: integrated at insertion site (selection IS
actualization).

This is **architecturally distinct** from prior Doctrine 5
manifestations:
- CSS cascade (Integrated both stages)
- Template hierarchy (Integrated both stages)
- Capability adjudication (Distributed both stages)
- **Block pattern resolution (Hybridized: Distributed
  arbitration + Integrated resolution)**

> **Doctrine 5 architectural variants now confirmed at 3**
> **patterns: Integrated, Distributed, AND Hybridized.**
> **Doctrine 5b architectural integrity principle**
> **strengthened.**

### 3. Pattern resolution confirms Resolution Surface intra-context density within site-building

Resolution Surface density evidence within site-building:

| chunk | resolution layer | architectural variant |
|---|---|---|
| template-hierarchy-and-resolution | template-level (page structure) | Integrated |
| **block-pattern-resolution-and-precedence (this)** | **pattern-level (composition modules)** | **Hybridized** |

**Resolution Surface intra-context recurrence within
site-building CONFIRMED.** Two chunks within site-building
exhibit Resolution at different compositional layers with
different architectural variants.

> **Resolution Surface promotion status update:**
>
> Pre-this-chunk: Recurring (cross-context) via 3-context
> retroactive verification.
> Post-this-chunk: Recurring (cross-context) **+ site-building
> intra-context density (2 chunks, distinct Resolution layers,
> architectural variant diversity)**.
>
> This is **NOT a new promotion tier**, but it materially
> strengthens Resolution Surface as **leading KB-Wide
> promotion candidate** (audit verification → Phase 7.8
> potential).

### 4. Site-building exhibits VERTICAL RESOLUTION DENSITY (observation only, NOT candidate)

> **Observation**: Site-building demonstrates Resolution
> operating at MULTIPLE compositional layers within the
> bounded context:
>
> | layer | mechanism | status |
> |---|---|---|
> | Template-level | Template hierarchy | Documented |
> | Pattern-level | Block pattern precedence | Documented (this) |
> | Navigation-level | Anticipated | Not yet documented |
> | Component-level | Anticipated | Deferred |

This is **stratified Resolution within a bounded context** —
distinct from Resolution recurring ACROSS bounded contexts
(which is the Resolution Surface candidate's character).

**Status: OBSERVATION, NOT CANDIDATE.** Per Phase 7.6
deferred candidates discipline:
- Single-context observation insufficient for candidate
  promotion.
- Phase 7.6 patch principle: "Phase 7.6 = operational doctrine
  patch, NOT taxonomy patch."
- Cross-context analog needed before "Stratified Resolution"
  becomes candidate (e.g., does plugin-dev exhibit similar
  layer stratification across origin/persistence/transport/
  entity?).

Documented for future audit consideration; explicitly not
promoted to candidate status in this chunk.

### 5. Federation Pattern 5-context manifestation (KB-Wide reinforced)

Pattern resolution federates from 5 distinct sources:
- Theme /patterns/ filesystem
- PHP register_block_pattern()
- theme.json patterns array (Pattern Directory federation)
- Plugin-registered patterns
- Synced patterns (wp_block post-type)

Federation Pattern recurrence count:
- plugin-dev (origin) — register_post_type / register_meta /
  register_rest_route / register_taxonomy / bindings sources
- editor-customization (createReduxStore federates state
  authority)
- admin-ui (plugin menu registration federates navigation
  authority)
- site-building (template hierarchy: theme + child + DB +
  plugin federation)
- **site-building (this chunk: 5-source pattern federation)**

5+ contexts × multiple federation manifestations.
**Federation Pattern KB-Wide status reinforced** with
strongest cross-context evidence yet.

### 6. Block pattern bridges 3 bounded contexts (block-authoring + theme-config + site-building)

Pattern resolution sits at a **3-bounded-context bridge**:
- **block-authoring**: pattern content IS block markup
  (authored block trees)
- **theme-config**: theme.json patterns array declares slug
  federation
- **site-building**: pattern resolution + selection +
  insertion is composition runtime (this chunk)

This is one of KB's strongest **3-bounded-context bridge
mechanisms** — patterns simultaneously inhabit declaration
(block-authoring authored content), registration (theme-config
metadata), AND runtime composition (site-building).

Bridge evidence: pattern's existence requires all 3 contexts'
participation; failure in any breaks pattern resolution.

### 7. Pattern resolution categorical structure manifests Law 5 (Entity → Relationship Pivot)

Pattern relationships:

| entity | relationship |
|---|---|
| Pattern | belongs to → categories (1-to-many) |
| Pattern | suggested for → blockTypes (1-to-many) |
| Pattern | restricted to → postTypes (1-to-many) |
| Category | groups → patterns (1-to-many) |
| Source | provides → patterns (1-to-many) |
| User | selects → pattern (relationship at selection event) |

Patterns operate **structurally as relationship hubs** —
each pattern's identity is largely defined by its
relationships to categories / blockTypes / postTypes / source.

Law 5 manifestation depth: **7+ context manifestation now**.
Entity-Relationship Pivot continues KB-Wide reinforcement
across composition-native domains.

### 8. Q9 retroactive verification triggered (FIRST live application post-Phase-7.6)

> Per Phase 7.6 patched Section D Q9: "Does this chunk reveal
> previously uncodified but latent law in earlier chunks?"

**ANSWER: YES — multiple candidates identified.**

This chunk reveals that block patterns' Resolution character
may also manifest in earlier chunks documenting
selection-from-candidates mechanisms. Specifically:

**Q9 candidates triggered**:

1. **`block.variations`** — variation selection at inserter
   IS Resolution-character. Variations register as
   selectable identity projections; user selects from
   candidates; selection actualizes block instance creation.
   Possibly Hybridized architecture (Distributed registration
   + Integrated selection).

2. **`block.transforms`** — transform selection at editor
   menu IS Resolution-character. Transforms register as
   conversion candidates; isMatch arbitrates availability;
   user selects from menu; selection actualizes block type
   conversion.

3. **(observed but lower priority)** — block patterns'
   relationship to **`block.json-context`** (providesContext/
   usesContext) may also exhibit Resolution character at
   context value resolution; defer.

**Q9 outcome**: 2 RETROACTIVE REFRAMING work items scheduled
for future chunks (block.variations + block.transforms).

This is the **FIRST live operationalization of Q9** since
Phase 7.6 patch (2026-05-09).

## VERIFICATION NEEDED

`status: stable`. Items requiring verification:

- Pattern caching behavior across requests.
- Pattern Directory fallback when network unavailable.
- Contextual suggestion algorithm specifics (exact match
  scoring, ranking).
- Pattern visibility with custom user roles.
- Search match algorithm details.
- Pattern content invalidation handling when block schema
  changes.
- Synced pattern wp_block post-type performance with many
  instances.
- Cross-source pattern collision resolution edge cases.
- Pattern preview rendering performance for complex patterns.
- Multisite pattern visibility across network sites.
- Pattern category hierarchy (does WP support nested
  categories?).
- Plugin deactivation cleanup of registered patterns.

For practical decisions: empirical testing per scenario.

## ANTIPATTERNS

- ❌ **Pattern = static markup snippet**. Patterns flow
  through registration → categorization → contextual
  suggestion → exposure → selection → insertion pipeline.
  Treating as static markup misses Resolution character.
- ❌ **Pattern Directory always available**. Remote
  Directory may be unavailable; theme.json patterns slug
  references should degrade gracefully.
- ❌ **Synced pattern = identical to static pattern**.
  Synced (wp_block) patterns are LIVE-LINKED — editing
  affects all instances. Static patterns are COPY-on-insert.
  Choose semantics explicitly.
- ❌ **Skipping namespace in pattern name**. Patterns need
  vendor/slug; collisions silently override.
- ❌ **Over-broad `blockTypes`**. Suggesting pattern for
  too many block types creates inserter noise.
- ❌ **Under-restrictive `postTypes`**. Pattern visible for
  inappropriate post types confuses authors.
- ❌ **Hardcoded category names**. Categories should be
  registered separately via register_block_pattern_category;
  hardcoded names without registration produce orphaned
  references.
- ❌ **Pattern content with deprecated block schemas**.
  Block parser may fail on outdated schemas; pattern
  insertion silently broken.
- ❌ **Skipping pattern preview testing**. Patterns render
  in inserter; complex patterns may render poorly at preview
  width.
- ❌ **Plugin deactivation orphaning patterns**. Registered
  patterns persist via posts using them; plugin-registered
  patterns disappear from inserter on deactivation but
  existing posts reference them.
- ❌ **Treating Pattern Directory federation as plugin
  dependency**. theme.json patterns slugs depend on remote
  Directory; not a plugin/theme code dependency but a
  network/availability dependency.

## RELATED

- `site-building.template-hierarchy-and-resolution` — paired
  Resolution chunk within site-building. Template hierarchy
  resolves page-level structure; this chunk resolves
  composition-module structure. Together build Resolution
  Surface intra-context density.
- `theme-config.json-patterns` — registration substrate
  counterpart. theme.json patterns array declares Pattern
  Directory slug federation; this chunk's runtime resolution
  includes that federation.
- `block-authoring.variations` — **Q9 RETRO TRIGGER**
  (likely latent Resolution Surface manifestation at
  variation selection).
- `block-authoring.transforms` — **Q9 RETRO TRIGGER**
  (likely latent Resolution Surface manifestation at
  transform menu selection).
- `plugin-dev.register-block-bindings-source` — cross-context
  Federation Pattern (plugin-registered patterns federate
  pattern authority).
- `plugin-dev.security-boundaries` — capability gates for
  pattern editing (synced pattern editing requires
  capability).
- `_meta.structural-patterns` — **Phase 7.6 patched spec
  applied; FIRST LIVE Doctrine 5 deployment + Q9 first live
  use**.

## META

**site-building bounded context — second chunk; first chunk
authored under Phase 7.6 patched spec; FIRST LIVE Doctrine 5
+ Q9 deployment in KB.**

### Phase 7.6 patched framework deployment

Per established settings-api / admin-menus / template-
hierarchy precedent, plus NEW Phase 7.6 elements:

1. ✅ **Patched verdict taxonomy deployed** (5-class).
2. ✅ **Patched maturity ladder applied** (5-tier).
3. ✅ **Q8 adjudication doctrine operationalized**.
4. ✅ **Doctrine 5 (Arbitration ↔ Resolution Paired
   Operations) directly applied** — verdict: HYBRIDIZED
   architecture (FIRST documented Hybridized in KB).
5. ✅ **Q9 retroactive verification trigger answered**:
   YES — 2 RETROACTIVE REFRAMING work items identified
   (block.variations + block.transforms).

### Doctrinal backbone established

> **Template hierarchy resolves page-level structure.**
> **Block pattern resolution governs compositional structural**
> **modules.**
> **Together, site-building demonstrates Resolution Surface as**
> **a composition-native operational law operating at multiple**
> **structural layers.**

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Doctrine 5 — Arbitration ↔ Resolution Paired Operations** | Very Strong (FIRST live test) | Distributed arbitration pipeline + Integrated insertion resolution | **Confirmed + HYBRIDIZED variant (FIRST in KB)** |
| **Law 4 — Arbitration Compiler** | Confirmed | Multi-source arbitration through registration / categorization / contextual suggestion | **Confirmed (Doctrine 5 Arbitration stage manifestation)** |
| **Law 6 — Compiler ↔ Runtime Split** | Confirmed | Pattern registration (compile-time) vs inserter rendering (runtime) | **Confirmed** |
| **Law 1 — Declaration ≠ Exposure** | Confirmed | registered ≠ surfaced ≠ contextually suggested ≠ inserted (4-form) | **Confirmed** |
| **Law 5 — Entity → Relationship Pivot** | Confirmed | Patterns as relationship hubs (categories / blockTypes / postTypes / source) | **Confirmed (7+ context depth)** |
| **Law 3 — Authority Continuity** | Implicit | Pattern registration persists; selected patterns become permanent block tree | **Confirmed (implicit)** |
| **Law 2 — HTML Primacy** | Implicit | Patterns are block markup (HTML-rooted) | **Confirmed (implicit)** |

**Universal law manifestation: SUCCESS.** Doctrine 5 first
live deployment confirms Hybridized variant — strengthens
architectural integrity principle.

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | Block-pattern manifestation | Outcome |
|---|---|---|---|
| **Resolution Surface** | Recurring (cross-context, via retro verification) | Strong manifestation: Hybridized architecture; site-building intra-context recurrence (2nd chunk) | **Confirmed + STRENGTHENED (intra-context density within site-building)** |
| **Stratified Resolution** (NEW observation) | did not exist | Site-building exhibits Resolution at multiple compositional layers (template + pattern + anticipated navigation) | **OBSERVED ONLY (NOT candidate; defer per Phase 7.6 deferred candidates discipline)** |
| **Authority Mediation Surface** | Recurring (intra-context, admin-ui) | Weak/secondary — pattern selection has minor mediation character but resolution dominates | **Divergent — site-building is resolution domain** |
| **Authority Interception Surface** | Recurring (intra-context, editor-customization) | Not present — patterns don't intercept; they aggregate | **Not present** |
| **Administrative Routing Surface** | Surfaced (admin-ui) | Not present — pattern resolution is composition, not navigation | **Not present** |
| **Federation Pattern** | KB-Wide (4-context) | Strong: 5-source pattern federation (theme/PHP/Directory/plugin/synced) | **Confirmed (5-context KB-Wide reinforced)** |

### Q9 Retroactive Verification Triggered (FIRST live use post-Phase-7.6)

> **Q9 ANSWER: YES — this chunk reveals previously
> uncodified but latent Resolution Surface manifestation in
> earlier chunks.**

**RETROACTIVE REFRAMING work items scheduled:**

1. **`block.variations` retro patch**: variation selection
   at inserter is Resolution-character. Possibly Hybridized
   (Distributed registration + Integrated selection event).

2. **`block.transforms` retro patch**: transform selection
   at editor menu is Resolution-character. isMatch
   arbitration + selection + actualization pipeline.

These are **Q9 trigger flags**, NOT yet executed retros.
Future chunks should execute the retros to verify.

### NEW KB-level findings

**1. Hybridized Doctrine 5 architecture (FIRST in KB):**

Block pattern resolution is the FIRST documented Hybridized
architecture per Doctrine 5b. Doctrine 5 architectural
variant taxonomy now has 3 instances:
- Integrated (CSS cascade, template hierarchy)
- Distributed (capability adjudication)
- **Hybridized (block pattern resolution — this chunk)**

This strengthens Doctrine 5b architectural integrity
principle: variant character genuinely varies; force-fitting
variants obscures architecture.

**2. Stratified Resolution within bounded context (observation,
NOT candidate):**

Site-building demonstrates Resolution operating at multiple
compositional layers (template + pattern + anticipated
navigation + component). This is **vertical Resolution
density** distinct from Resolution Surface's horizontal
cross-context recurrence.

**Status: OBSERVATION ONLY** — single-bounded-context
manifestation; cross-context analog needed for candidate
promotion (e.g., plugin-dev's federation stack 5 layers may
exhibit similar stratification — but defer to future
verification).

**3. 3-bounded-context bridge mechanism:**

Block patterns sit at intersection of block-authoring (block
markup) + theme-config (registration) + site-building
(runtime resolution). One of KB's strongest 3-context bridge
mechanisms.

**4. Resolution Surface promotion status update:**

```
Resolution Surface candidate status (post-this-chunk):
   Pre: Recurring (cross-context, via 3-context retro
        verification)
   Post: Recurring (cross-context) + site-building
         intra-context density (2 distinct compositional
         layers + architectural variant diversity within
         bounded context)
   Tier change: NONE (no new tier promotion)
   Strength: SIGNIFICANTLY STRENGTHENED
   KB-Wide candidacy: Strongest active non-KB-Wide candidate
                      in KB; Phase 7.8 audit verification
                      pending
```

### Site-building bounded context character (post-2-chunks)

Site-building character clarifying:

| dimension | observation |
|---|---|
| Primary modality | Composition runtime (resolution-native) |
| Resolution density | Vertical (template + pattern layers documented; navigation + component anticipated) |
| Federation participation | 5+ source pattern federation (composition layer) + 4-source template federation |
| Multi-pattern character | Resolution dominant; secondary characters minimal |

Per bounded context character taxonomy (Phase 7.6 deferred
candidate), site-building is **composition-native** — distinct
from governance modulation contexts (editor-customization /
admin-ui) and federation/governance (plugin-dev).

### KB-wide pattern recurrence updates

**Pattern resolution debt = 8th debt-pattern instance:**

| chunk | debt name | bounded context |
|---|---|---|
| security-boundaries | security debt | plugin-dev |
| block-filters | interception debt | editor-customization |
| slotfills | topology debt | editor-customization |
| editor-hooks | reactive debt | editor-customization |
| settings-api | settings debt | admin-ui |
| admin-menus | navigation debt | admin-ui |
| template-hierarchy-and-resolution | resolution debt | site-building |
| **block-pattern-resolution-and-precedence** | **pattern resolution debt** | **site-building** |

8 instances × 4 bounded contexts. Governance debt continues
strengthening as anticipated meta-pattern (still NOT
promoted to candidate).

### KB self-evaluation against spec criteria (Phase 7.5 + 7.6 patched)

- ✅ Accuracy — describes documented block pattern resolution.
- ✅ Structural fit — second site-building chunk; tests
  Resolution intra-context density; FIRST LIVE Doctrine 5
  + Q9 deployment.
- ✅ Reusability — uses authority ontology glossary +
  Phase 7.5/7.6 vocabulary (resolution / arbitration / paired
  operations / hybridized / Q9).
- ✅ Phase fit — Phase 7.6 patched spec applied; references
  cross-bounded-context relationships explicitly.
- ✅ Doctrine respect — HTML primacy implicit; declaration ≠
  exposure 4-form; Doctrine 5 architectural variant integrity
  preserved (NOT force-fit Hybridized into Integrated or
  Distributed).
- ✅ **Q8 adjudication explicitly answered**: Resolution
  Confirm + strengthened; Stratified Resolution Observe;
  Mediation Diverge; Federation Confirm.
- ✅ **Q9 retroactive trigger answered**: YES — 2 RETROACTIVE
  REFRAMING work items scheduled (variations + transforms).

### Status: `stable`

Block patterns API mature (WP 5.5+); patterns folder
auto-registration mature (WP 6.0+); Pattern Directory
federation mature (WP 6.4+).

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission

> **Determine whether Resolution Surface in site-building is**
> **singular (template-only) or structurally layered (template**
> **+ pattern + possibly navigation).**

**Verdict: STRUCTURALLY LAYERED (observationally).** Resolution
Surface exhibits vertical density within site-building;
stratification observed but not yet promoted to candidate.

### Anticipated next chunks (priority)

1. **`block-authoring.variations` RETRO PATCH** — Q9
   triggered. Verify latent Resolution Surface in variation
   selection mechanism. Could materially strengthen Resolution
   candidate toward KB-Wide audit threshold.

2. **`block-authoring.transforms` RETRO PATCH** — Q9
   triggered. Verify latent Resolution in transform menu
   selection.

3. **`site-building.{3rd chunk}`** — 3rd Resolution layer
   (navigation menu fallback?) to test stratification
   stability.

4. **`admin-ui.notices`** — admin-ui depth continuation.

5. **`plugin-dev.nonces`** — security trio completion.

6. **Phase 7.8 audit** — when Resolution Surface accumulates
   sufficient evidence for KB-Wide audit verification.

Recommended next: **`block-authoring.variations` retro
patch** (honor Q9 while fresh; methodological discipline per
Phase 7.6).
