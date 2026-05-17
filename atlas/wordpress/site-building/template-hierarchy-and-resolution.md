---
rule_id: site-building.template-hierarchy-and-resolution
domain: site-building
topic: structural-resolution
field_cluster: composition-runtime
wp_min: "5.9"
wp_recommended: "6.0+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/themes/basics/template-hierarchy/
    section: "Template Hierarchy — query-type to template resolution"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/themes/block-themes/templates-and-template-parts/
    section: "Block themes — templates/ + parts/ directory + DB overrides"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/hooks/template_hierarchy/
    section: "template_hierarchy filter — runtime hierarchy modification"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/reference/functions/get_query_template/
    section: "get_query_template() — resolution mechanism"
    captured: 2026-05-09
related:
  - theme-config.json-templateParts                 # registration-side counterpart (site-building resolves what theme-config registers)
  - theme-config.json-customTemplates               # custom templates registered there; selected/resolved here
  - theme-config.json-patterns                      # patterns registered; composition resolved here at runtime
  - block.dynamic-rendering                         # template files render through dynamic-rendering pipeline
  - data-layer.entity-resolution                    # database-stored template overrides resolve through entity layer
  - plugin-dev.security-boundaries                  # capability checks for Site Editor template editing
  - _meta.structural-patterns                       # Phase 7.5 patched spec applied; "Resolution Surface" candidate surfaced
---

# RULE — template hierarchy and resolution — runtime structural arbitration

## WHEN

Asking how WordPress determines which template renders a given
URL request, or how block themes resolve template inclusion at
runtime. Use this chunk to understand:

- The classic PHP template hierarchy (index.php → singular →
  single → single-{post-type} → etc.).
- Block theme template resolution (templates/ filesystem +
  database-stored overrides via Site Editor).
- Template part inclusion resolution at runtime
  (`<!-- wp:template-part -->` block resolution).
- The `template_hierarchy` filter and its arbitration role.
- Why template hierarchy is NOT file lookup but **runtime
  authority arbitration**.

This is the **first chunk in site-building bounded context**
and the **third substantive chunk under Phase 7.5 patched spec**
(after admin-ui.settings-api + admin-ui.admin-menus).

Strategic role: **breadth expansion for candidate pattern
testing**. After admin-ui validated multi-pattern bounded context
doctrine in 2nd context, site-building tests whether existing
candidates (Mediation / Interception / Federation) and laws
(Arbitration / Entity-Relationship / Compiler-Runtime) **survive
in a composition-native bounded context** — or whether
site-building surfaces a NEW candidate pattern.

The doctrinal backbone for site-building (established here):

> **Theme-config declares structural possibilities.**
> **Site-building resolves structural actuality.**
> **Template hierarchy is not file lookup — it is runtime**
> **authority arbitration through hierarchical structural**
> **resolution.**

The KB-level question shift:

> Plugin-dev extends authority.
> Editor and admin GOVERN authority.
> Site-building **RESOLVES authority into lived structure**.

## SHAPE

### A. Resolution topology — query-type to template

WordPress's template hierarchy is a **directed resolution graph**
from query-type to candidate template list:

```
Request URL
   ↓
Query parsing (WP_Query)
   ↓
Conditional checks (is_404, is_singular, is_archive, etc.)
   ↓
Template hierarchy generation (per query type)
   ↓
get_query_template() iterates candidates
   ↓
First existing template wins
   ↓
Template renders (PHP template OR block template)
```

**Hierarchy examples**:

```
Singular post:
   single-{post-type}-{slug}.html (or .php)
   ↓
   single-{post-type}.html
   ↓
   single.html
   ↓
   singular.html
   ↓
   index.html

Category archive:
   category-{slug}.html
   ↓
   category-{id}.html
   ↓
   category.html
   ↓
   archive.html
   ↓
   index.html

404:
   404.html
   ↓
   index.html
```

Each query type has its own hierarchy ladder. **The hierarchy is
declared by WordPress core** based on conditional context;
themes provide the candidate templates.

### B. Arbitration mechanics — multiple resolution sources

Multiple sources contribute to template resolution:

| source | contribution |
|---|---|
| **Theme filesystem** (templates/ directory) | candidate templates registered |
| **Theme template parts** (parts/ directory) | partial composition pieces |
| **Database overrides** (wp_template / wp_template_part posts) | Site Editor user customizations |
| **Custom templates** (theme.json customTemplates) | user-selectable alternatives |
| **template_hierarchy filter** | runtime modification of candidate list |
| **child theme** | overrides parent theme templates |

**Arbitration order** (typical, per WordPress core):
1. Database overrides (Site Editor edits) win over filesystem
2. Child theme filesystem wins over parent theme filesystem
3. More specific filename wins (single-product-foo > single-product > single)
4. template_hierarchy filter modifies candidate order

This is **multi-source authority arbitration** — exactly the
structural pattern documented in Law 4 (Arbitration Compiler).

### C. Composition runtime — block template resolution

Block themes (WP 5.9+) extend resolution to **block-level
composition**:

```html
<!-- templates/single.html (filesystem) -->
<!-- wp:template-part {"slug":"header","theme":"my-theme"} /-->
<main>
    <!-- wp:post-content /-->
</main>
<!-- wp:template-part {"slug":"footer","theme":"my-theme"} /-->
```

Resolution recursion:
- `single.html` resolved as primary template.
- Inner `<!-- wp:template-part -->` blocks reference
  `parts/header.html` etc.
- Each template part also resolves through database override
  → child theme → parent theme priority.
- Patterns embedded via `<!-- wp:pattern -->` blocks also
  resolve.

This creates a **multi-level resolution graph** — top template +
recursive template parts + embedded patterns each independently
resolved.

### D. Governance + continuity — Site Editor + capability boundaries

```
Template authority lifecycle:

1. Theme ships templates in /templates/ + /parts/ filesystem.
2. User edits template in Site Editor (capability: edit_theme_options).
3. Edits saved as wp_template / wp_template_part post-type
   entries in database.
4. Future requests: database override wins over filesystem.
5. Reset to filesystem: delete database post; resolution falls
   back to filesystem.
```

**Capability boundaries** (per security-boundaries doctrine):
- `edit_theme_options` for Site Editor access
- `manage_options` for some advanced operations
- Custom templates `postTypes` array gates which post types may
  select

**Authority continuity across deactivation**:
- Theme deactivation: filesystem templates disappear; database
  overrides persist (orphaned references)
- Plugin-registered templates: similar persistence concern
- Site Editor edits: persistent across theme switches (database
  overrides apply to template SLUGS, may match new theme's
  templates with same slug)

### E. Failure surfaces — template / override / resolution debt

```
Resolution failure modes:

- template debt: theme accumulates templates past their relevance
- override debt: database overrides persist after templates
  removed from filesystem (orphaned overrides)
- resolution debt: complex template_hierarchy filter chains
  obscure resolution path
- inheritance fragility: child theme depends on parent template
  structure; parent updates break child overrides
- pattern collision: theme registers pattern with same slug as
  registered theme-bundled pattern
- 404 cascade: missing templates cascade to 404 unexpectedly
- block parser errors in template files: breaks template
  resolution silently
- template part recursion: template parts referencing each
  other infinitely
```

**Resolution debt** (7th debt-pattern instance in KB; first in
site-building):

| debt mode | symptom |
|---|---|
| template accumulation | unused templates persist in filesystem |
| override orphans | DB overrides reference removed filesystem templates |
| resolution opacity | filter chains obscure actual selection logic |
| inheritance fragility | child/parent updates break overrides |
| pattern slug collisions | multiple sources compete for slug |

7-instance debt recurrence × 4 bounded contexts. "Governance
debt" continues strengthening as anticipated meta-pattern.

## REQUIRES

- WP 5.9+ for block themes (templates/ + parts/ directories).
- Block theme uses HTML files (.html) for templates;
  classic theme uses PHP files (.php) — WordPress resolves both
  formats via template hierarchy.
- For Site Editor edits: capability `edit_theme_options`
  required.
- template_hierarchy filter must run early enough to affect
  resolution (default WordPress hooks).
- Custom templates declared in theme.json `customTemplates`
  field MUST have corresponding /templates/{name}.html files.
- ⚠ Specific behaviors: filter chain ordering, classic
  theme/block theme hybrid resolution, theme switch with
  database overrides, multisite theme inheritance, performance
  with deep template_hierarchy filter chains —
  verification-needed.

## INVARIANTS

### 1. Template hierarchy is runtime authority arbitration, NOT file lookup

The load-bearing reframing:

> Template hierarchy is NOT "WordPress looks up which file to
> use." It is **runtime authority arbitration** — competing
> template authority sources (filesystem theme, child theme,
> database overrides, custom templates, hierarchy filter
> modifications) are resolved via deterministic precedence
> rules into a SINGLE template selection.

Reading template hierarchy as "file lookup" obscures the
ARBITRATION character. The hierarchy IS the arbitration
algorithm; resolution IS the arbitration outcome.

### 2. Site-building resolves what theme-config registers — cross-context relationship

Bounded-context complementarity:

| context | role |
|---|---|
| **theme-config** | declaration / registration substrate (templateParts, customTemplates, patterns metadata) |
| **site-building (this chunk)** | runtime composition / structural resolution (which template ACTUALLY renders) |

theme-config answers "what may exist?" Site-building answers
"what actually resolves under runtime conditions?"

This **registration-substrate / runtime-resolver split** mirrors:
- data-layer.entity-resolution (registry → reactive resolution)
- style-engine pipeline (theme.json declarations → emission +
  cascade aggregation)
- plugin-dev federation + governance (registration → enforcement)

KB now has **multiple bounded contexts** exhibiting this
substrate-vs-resolver split. Pattern recurrence noted.

### 3. Multi-source arbitration confirms Arbitration Compiler beyond governance domains

Arbitration Compiler instances in KB before this chunk:
1. style-engine cascade-aggregation (CSS authority)
2. plugin-dev capabilities-and-roles map_meta_cap (capability
   authority)
3. block-filters priority (lifecycle interception authority)
4. admin-menus position + filters (admin navigation authority)
5. **THIS CHUNK: template hierarchy resolution
   (composition authority)**

5+ instances now span 4 bounded contexts. **Arbitration
Compiler escapes governance-heavy zones** — manifests in
composition-native domain.

This is a **major KB validation**: Arbitration Compiler is
genuinely KB-Wide, not governance-domain artifact.

### 4. Resolution graph is structurally entity-relationship-centric (Law 5)

Template resolution exhibits relationship-centric ontology:

```
Resolution graph entities:
   - Template files (filesystem entities)
   - Template post entries (database entities)
   - Template parts (composition entities)
   - Patterns (composition entities)
   - Custom template registrations (entities)

Resolution graph relationships:
   - Template → fallback hierarchy (precedence relationship)
   - Template → template parts (composition relationship)
   - Template part → template part (recursive composition)
   - Filesystem ↔ database (override relationship)
   - Child ↔ parent theme (inheritance relationship)
   - Custom template ↔ post type (registration relationship)
```

Resolution is FUNDAMENTALLY about navigating these
relationships at runtime. Reading hierarchy as "list of files"
misses relationship structure.

This **strengthens Law 5 manifestation** to 6+ contexts.

### 5. Compiler ↔ Runtime split present (Law 6)

```
Compile-time / runtime split for templates:

DECLARATIVE SOURCE:
   /templates/*.html files (block themes)
   /templates/*.php files (classic themes)
   /parts/*.html files (template parts)
   theme.json customTemplates declarations
   wp_template / wp_template_part post entries (DB)
   ↓
COMPILER (runtime resolution):
   get_query_template() + template hierarchy
   template_hierarchy filter modifications
   override priority resolution
   block parser for block themes
   ↓
RUNTIME EXECUTION:
   PHP template execution (classic) OR
   block template render (block themes)
   recursive template part rendering
   pattern composition
   ↓
OUTPUT:
   rendered HTML
```

WordPress core IS a template compiler at request time. This is
yet another instance of Law 6's compiler/runtime split.

### 6. Multi-form Declaration ≠ Exposure (Law 1) for templates

| surface | controlled by |
|---|---|
| **declaration** (template registered in filesystem or theme.json customTemplates) | filesystem presence OR registration call |
| **eligibility** (template eligible for query type) | hierarchy logic + post type matching |
| **selection** (template actually selected for request) | resolution outcome (fallback chain) |
| **selectability** (user may choose custom template in editor) | customTemplates postTypes gating |
| **render execution** (template content actually executes) | template runs in PHP/block context |

5-form declaration ≠ exposure for templates. Matches admin-menus'
5-form depth — both contexts exhibit highest multidimensional
declaration ≠ exposure in KB.

### 7. NEW CANDIDATE — "Resolution Surface" surfaced (NOT promoted)

> **Resolution Surface**: governance through hierarchical
> structural resolution where authority becomes ACTUALIZED
> through deterministic precedence-based selection from
> competing candidates.

**Distinct from prior candidates:**
- Authority Interception Surface = mutate / inject existing
  authority
- Authority Mediation Surface = controlled access channels
- Administrative Routing Surface = navigable hierarchy
- **Resolution Surface = STRUCTURAL ACTUALIZATION through
  precedence-based candidate selection**

Resolution differs from Mediation: Mediation governs ACCESS to
existing authority; Resolution SELECTS authority from competing
candidates. Resolution differs from Routing: Routing makes
authority NAVIGABLE; Resolution makes authority ACTUAL.

Status: **Surfaced** (Local Pattern, 1st observation, this chunk).
"Surfaced, not constitutionalized."

**Modifier-free naming preserved** (per Phase 7.5 Doctrine 2):
- NOT "Composition Resolution Surface" (premature character
  commitment)
- NOT "Authority Resolution Surface" (premature character
  commitment)
- Just "Resolution Surface" — character determined post-recurrence

**Verification path**:
- Recurrence within site-building: block patterns resolution?
  navigation menu fallback? query loop variation precedence?
- Cross-context recurrence: style variation precedence
  (theme-config)? capability adjudication (which IS
  resolution-character actually — map_meta_cap selects from
  candidates)?

### 8. site-building = first composition-native bounded context

KB-level positioning:

> site-building is the **first composition-native bounded
> context** — its primary character is structural composition
> through resolution, NOT governance / mediation /
> interception.

KB now has explicit categorization of bounded contexts by
character:

| character | bounded contexts |
|---|---|
| **Schema authority** | block-authoring, theme-config |
| **Compiler/runtime** | style-engine, interactivity |
| **Authority federation** | plugin-dev (external) |
| **Governance modulation** | editor-customization, admin-ui |
| **Composition runtime (NEW)** | site-building (this chunk surfaces) |

This **bounded context character taxonomy** may eventually
become formalized in DSL spec (currently surfaced, not yet
formalized).

## VERIFICATION NEEDED

`status: stable` — Template hierarchy is mature WordPress
core architecture. Specific behaviors evolving / variable:

- Block theme template resolution edge cases with mixed PHP/HTML
  template files.
- template_hierarchy filter chain ordering when multiple plugins
  filter.
- Theme switch behavior with persistent database overrides.
- Multisite theme inheritance for templates.
- Performance with very deep template_hierarchy modifications.
- Block parser error handling in template files (silent skip
  vs error display).
- Custom post type templates without explicit hierarchy entries.
- Pattern slug collision resolution.
- Site Editor unsaved-changes interaction with database overrides.
- Network admin template management for multisite.
- Template loader debugging / introspection tools.

For practical decisions: empirical testing per scenario.

## ANTIPATTERNS

- ❌ **Template hierarchy = file lookup**. Hierarchy is
  arbitration; treating it as static lookup misses runtime
  modification surfaces (template_hierarchy filter, database
  overrides) and obscures debugging.
- ❌ **Site Editor edits = filesystem changes**. Site Editor
  edits create database overrides; filesystem unchanged. Theme
  reset (delete DB overrides) restores filesystem behavior.
- ❌ **Custom template = guaranteed user availability**.
  customTemplates registration declares; postTypes array gates
  which post types can select. Without postTypes match, user
  cannot select.
- ❌ **template_hierarchy filter without considering
  ecosystem**. Multiple plugins filtering create unpredictable
  precedence; ecosystem hygiene requires explicit priority
  declaration.
- ❌ **Block parser errors = template rendering failure**.
  Errors may silently fall through hierarchy to fallback;
  visible debugging requires WP_DEBUG.
- ❌ **Theme deactivation cleanup ignored**. Database template
  overrides persist after theme switch; orphaned overrides
  reference filesystem templates that no longer exist.
- ❌ **Child theme = simple parent override**. Child themes
  override at file level; database overrides win over both.
  Inheritance / override / hierarchy interactions are complex.
- ❌ **Pattern slug = arbitrary identifier**. Slug collisions
  between theme-bundled, plugin-registered, and core patterns
  create resolution ambiguity.
- ❌ **Template part recursion uncontrolled**. Template parts
  referencing each other infinitely cause stack overflow at
  render time.
- ❌ **Capability checks for template editing skipped**. Site
  Editor template editing requires `edit_theme_options`;
  custom Site Editor extensions must respect capability
  boundaries.

## RELATED

- `theme-config.json-templateParts` — registration-side
  counterpart. Template parts METADATA registered there;
  RUNTIME RESOLUTION here. Cross-context complementarity.
- `theme-config.json-customTemplates` — custom templates
  REGISTERED there; SELECTED + RESOLVED here. Custom templates
  postTypes argument gates user selection.
- `theme-config.json-patterns` — patterns REGISTERED there;
  COMPOSITION RESOLUTION here at runtime when patterns embedded.
- `block.dynamic-rendering` — template files render through
  dynamic-rendering pipeline. Block templates evaluate as block
  trees; PHP templates execute as PHP code.
- `data-layer.entity-resolution` — wp_template /
  wp_template_part posts ARE entities resolvable through
  entity-resolution layer. Database overrides project through
  data-layer substrate.
- `plugin-dev.security-boundaries` — Site Editor template
  editing capability gates per security-boundaries doctrine.
- `_meta.structural-patterns` — **Phase 7.5 patched spec
  applied; Resolution Surface candidate surfaced**.

## META

**site-building bounded context — first chunk; third
substantive chunk under Phase 7.5 patched spec; first
composition-native bounded context.**

### Phase 7.5 patched framework deployment

Per established settings-api / admin-menus precedent:

1. ✅ **Patched verdict taxonomy deployed** (5-class
   Confirmed/Divergent/Hybridized/Surfaced/Deferred).
2. ✅ **Patched maturity ladder applied** (5-tier).
   Resolution Surface: Surfaced (Local pattern surface, 1st
   observation).
3. ✅ **Q8 adjudication doctrine operationalized**: Mediation
   = Divergent (resolution ≠ mediation); Interception = Weak;
   Federation = Confirmed; Resolution = Surfaced (NEW).

### Doctrinal backbone established

> **Theme-config declares structural possibilities.**
> **Site-building resolves structural actuality.**
> **Template hierarchy is not file lookup — it is runtime**
> **authority arbitration through hierarchical structural**
> **resolution.**

### Constitutional Field Test (Table A — Universal Law Manifestation)

| Law | Prediction | Observation | Status |
|---|---|---|---|
| **Law 4 — Arbitration Compiler** | Very Strong | Multi-source template arbitration (filesystem / DB / child theme / customTemplates / hierarchy filter) | **Confirmed (5+ instances now span 4 bounded contexts; ESCAPES GOVERNANCE-HEAVY ZONES; major KB validation)** |
| **Law 5 — Entity → Relationship Pivot** | Very Strong | Resolution graph: templates / parts / patterns / overrides + hierarchy / composition / inheritance / override relationships | **Confirmed (6+ context manifestation; relationship-centric ontology beyond governance domains)** |
| **Law 6 — Compiler ↔ Runtime Split** | Very Strong | Declarative templates → resolution compiler → PHP/block runtime | **Confirmed (template hierarchy IS WordPress's content-domain compiler)** |
| **Law 1 — Declaration ≠ Exposure** | Strong | 5-form: declaration / eligibility / selection / selectability / render execution | **Confirmed (5-form, parallel to admin-menus' depth)** |
| **Law 3 — Authority Continuity** | Moderate | Templates persist across requests; database overrides preserve customizations | **Confirmed (substrate persistence across runtime cycles)** |
| **Law 2 — HTML Primacy** | Implicit | Templates render to HTML | **Confirmed (implicit)** |

**Universal law manifestation: SUCCESS — major validations:**
- **Arbitration Compiler escapes governance-heavy zones**;
  manifests in composition-native domain. Confirms KB-Wide
  status genuinely architecture-general, NOT governance
  artifact.
- **Law 5 Entity → Relationship 6+ context manifestation**.
- **Law 6 Compiler ↔ Runtime template hierarchy as core
  WordPress compiler**.

### Constitutional Field Test (Table B — Pattern Recurrence / Divergence Verification)

| Candidate | Prior status | Site-building manifestation | Outcome |
|---|---|---|---|
| **Resolution Surface** (NEW) | did not exist | Hierarchical runtime structural arbitration through deterministic precedence-based candidate selection | **Surfaced (Local Pattern, 1st observation); "surfaced, not constitutionalized"** |
| **Authority Mediation Surface** | Recurring (intra-context, admin-ui) + cross-context PRESENCE | Weak/secondary — user-selected templates have minor mediation character but resolution is the primary mode | **Divergent — site-building is resolution domain, not mediation** |
| **Authority Interception Surface** | Recurring (intra-context, editor-customization) | Weak — template_hierarchy filter is interception-shaped but resolution dominates | **Weak/secondary** |
| **Administrative Routing Surface** | Surfaced (admin-ui) | Not present — site-building has resolution graph, not navigation routing | **Not present** |
| **Federation Pattern** | KB-Wide (3-context now) | Theme + child theme + plugin + DB + core all federate template authority | **Confirmed (4th-context manifestation; KB-Wide reinforced)** |

**Promotion event: Resolution Surface NEW Surfacing.**

### NEW candidate — Resolution Surface (modifier-free)

**Definition**:
> Governance through hierarchical structural resolution where
> authority becomes ACTUALIZED through deterministic
> precedence-based selection from competing candidates.

**Distinguished from prior candidates:**

| candidate | character |
|---|---|
| Interception | mutate / inject existing authority |
| Mediation | controlled access channels |
| Routing | navigable hierarchy |
| **Resolution (NEW)** | **structural actualization via precedence-based selection** |

**Promotion path**:
- Local: 1 observation (this chunk)
- Recurring (intra-context): would require 2nd site-building
  resolution instance (block pattern resolution? navigation
  menu fallback? query loop variation precedence?)
- Recurring (cross-context): would require resolution in
  another bounded context (style variation precedence in
  theme-config? capability adjudication in plugin-dev which
  IS resolution-character via map_meta_cap?)
- KB-Wide: audit verification

**Verification candidates**:
- `style-engine.cascade-aggregation` — CSS cascade IS
  resolution (competing rules → applied value via specificity
  + order). Already documented; can be retroactively read as
  Resolution Surface manifestation.
- `plugin-dev.capabilities-and-roles` — map_meta_cap IS
  resolution-character (contextual cap → primitive cap selection).
- These observations may push Resolution candidate to
  Recurring (cross-context) directly through retroactive
  audit; defer to future explicit verification work.

### Bounded context character taxonomy (NEW KB-level finding)

KB now has explicit categorization of bounded contexts by
ontological character:

| character | bounded contexts | primary mechanism |
|---|---|---|
| **Schema authority** | block-authoring, theme-config | declarative registration |
| **Compiler/runtime** | style-engine, interactivity | declarative source → runtime VM |
| **Authority federation** | plugin-dev (external) | new authority surface declaration |
| **Governance modulation** | editor-customization, admin-ui | existing authority interception/mediation |
| **Composition runtime (NEW)** | **site-building** | **structural resolution from candidate sources** |

5 categories now identified. This taxonomy is **observed, not
yet spec-formalized** — pending recurrence verification of
each category's defining character. May warrant Phase 7.6
spec consideration if categories prove stable.

### Major KB validation — Arbitration Compiler beyond governance

**Pre-this-chunk** Arbitration Compiler manifestation count:
- style-engine cascade-aggregation (CSS authority)
- plugin-dev capabilities-and-roles (capability authority)
- block-filters (lifecycle interception authority)
- admin-menus (navigation authority)

These 4 chunks all involved governance-heavy domains.

**Post-this-chunk**:
- **+ template hierarchy resolution (composition authority)**

5 instances × 4 bounded contexts (style-engine,
plugin-dev, editor-customization, admin-ui, site-building).
**Composition-native bounded context (site-building) confirms
arbitration**. Arbitration Compiler is genuinely
**architecture-general**, not governance-domain artifact.

This is one of KB's biggest law validations.

### KB-wide pattern recurrence updates

**Debt pattern (7-instance recurrence)**:

| chunk | debt name | bounded context |
|---|---|---|
| security-boundaries | security debt | plugin-dev |
| block-filters | interception debt | editor-customization |
| slotfills | topology debt | editor-customization |
| editor-hooks | reactive debt | editor-customization |
| settings-api | settings debt | admin-ui |
| admin-menus | navigation debt | admin-ui |
| **template-hierarchy-and-resolution** | **resolution debt** | **site-building** |

7 instances × 4 bounded contexts. Governance debt continues
broadening.

**Federation Pattern (4-context recurrence)**:
- plugin-dev (origin)
- editor-customization (createReduxStore)
- admin-ui (plugin menu registration)
- **site-building (theme + child theme + DB + plugin template
  federation)**

KB-Wide Federation status reinforced via 4-context
manifestation.

### KB self-evaluation against spec criteria (Phase 7.5 patched)

- ✅ Accuracy — describes documented template hierarchy.
- ✅ Structural fit — first site-building chunk; tests
  candidates' architectural transferability; surfaces NEW
  Resolution candidate.
- ✅ Reusability — uses authority ontology glossary +
  Phase 7.5 vocabulary (resolution / arbitration / debt /
  composition).
- ✅ Phase fit — Phase 7.5 patched spec applied; references
  cross-context relationships explicitly.
- ✅ Doctrine respect — HTML primacy implicit; declaration ≠
  exposure 5-form; Epistemic Integrity preserved (Resolution
  Surface surfaced separately, not force-fit into existing
  candidates).
- ✅ **Q8 adjudication explicitly answered**: Mediation =
  Divergent; Interception = Weak; Routing = Not present;
  Federation = Confirm; Resolution = Surface (NEW).

### Status: `stable`

Template hierarchy is mature WordPress core (template
hierarchy concept since WP 1.5+; block themes since WP 5.9+).
Verification-needed catalog covers behaviors but core
architecture is settled.

### DSL extensions applied: VERIFICATION NEEDED + META

Per runtime/implementation-derived applicability rule.

### One-line constitutional mission

> **Plugin-dev extends authority.**
> **Editor and admin govern authority.**
> **Site-building resolves authority into lived structure.**

Verdict: **Resolution-as-arbitration confirmed.** Template
hierarchy IS runtime structural arbitration, not file lookup.

### Anticipated next chunks (priority)

1. **`site-building.{2nd chunk}`** — for Resolution Surface
   recurrence test within site-building. Candidates: block
   patterns runtime resolution / navigation menu fallback /
   query loop variation precedence.

2. **Retroactive Resolution Surface verification** — re-read
   style-engine.cascade-aggregation + plugin-dev.capabilities-
   and-roles through Resolution Surface lens. Could
   retroactively promote candidate to cross-context
   recurrence directly (skipping intra-context tier).

3. **`admin-ui.notices`** — third admin-ui chunk; tests
   admin-ui depth (Routing recurrence + interception/mediation
   adjudication).

4. **`plugin-dev.nonces`** — security trio completion.

5. **Phase 7.6 spec patch consideration** — bounded context
   character taxonomy formalization if categories prove
   stable.

Recommended sequence: **second site-building chunk OR
retroactive Resolution Surface verification** (both deepen
Resolution candidate before lateral expansion). Then
admin-ui.notices or other contexts.
