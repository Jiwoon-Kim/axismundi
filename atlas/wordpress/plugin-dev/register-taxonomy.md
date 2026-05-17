---
rule_id: plugin-dev.register-taxonomy
domain: plugin-dev
topic: extensibility
field_cluster: semantic-federation
wp_min: "2.3"
wp_recommended: "5.0+"
status: stable
language: php
sources:
  - url: https://developer.wordpress.org/reference/functions/register_taxonomy/
    section: "register_taxonomy() — taxonomy registration"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/plugins/taxonomies/
    section: "Plugin Handbook — Custom Taxonomies"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/plugins/taxonomies/working-with-custom-taxonomies/
    section: "Working with Custom Taxonomies — terms / capabilities / queries"
    captured: 2026-05-09
related:
  - plugin-dev.register-post-type              # subject constitution counterpart — CPT + taxonomy = subject + semantic
  - plugin-dev.security-boundaries             # doctrine framework applied to semantic governance
  - plugin-dev.register-meta                   # term meta also registerable via register_meta with object_type='term'
  - plugin-dev.register-rest-route             # show_in_rest hooks default REST routes for taxonomy
  - data-layer.entity-resolution               # taxonomies expose terms as entities (kind 'taxonomy')
  - block-authoring.block-json.bindings        # bindings can target term meta via custom sources
  - (planned) plugin-dev.capabilities-and-roles # capability model deeper dive (taxonomy adds assign_terms distinction)
  - style-engine.generated-selectors           # entity→relationship pivot symmetry (semantic federation = relationship-centric)
---

# RULE — `register_taxonomy()` — semantic classification federation

## WHEN

A plugin or theme needs to introduce a new way of CLASSIFYING /
GROUPING / ORDERING entities beyond core's category, post_tag,
nav_menu, link_category, post_format taxonomies. Use this API
when:

- Entities need a new categorization vocabulary (e.g., product
  categories, article topics, location regions).
- Multiple entity types need a shared classification system
  (cross-entity tagging / grouping).
- Hierarchical (tree-structured) classifications are needed.
- Term archives / term-based URL routing are part of the
  design.
- REST API exposure of classification structure is required.

This is the **fifth plugin-dev chunk** in KB. With CPT
(register_post_type) declaring entity species, this chunk
declares **classification topology**:

```
plugin-dev (after this chunk):
   register-block-bindings-source  → trust / origin            ✓
   register-meta                   → legitimacy / persistence   ✓
   register-rest-route             → permeability / transport   ✓
   security-boundaries             → governance doctrine        ✓
   register-post-type              → entity / subject species   ✓
   register-taxonomy               → semantic / classification  ✓ (THIS)
```

CPT was "what new authority-bearing subjects may exist?"
Taxonomy is qualitatively different: **"how may
authority-bearing subjects be semantically ordered, grouped,
and traversed?"**

This shifts plugin-dev from **entity federation** (subject
species) toward **semantic graph federation** (classification
topology). The KB-level question pivot:

> "How are custom entities categorized?"
> reframes to:
> "**How does WordPress federate semantic classification
> authority across entity systems?**"

This pivot mirrors the entity-centric → relationship-centric
shift documented in style-engine (cascade-aggregation +
generated-selectors). plugin-dev now reaches the same pivot
at the entity layer: subjects + relationships between subjects.

## SHAPE

### A. Classification identity

```php
register_taxonomy(
    'product_category',                       // taxonomy slug
    array( 'product' ),                       // object_type(s) it attaches to
    array(
        'label'  => __( 'Product Categories', 'myplugin' ),
        'labels' => array(
            'name'              => __( 'Product Categories', 'myplugin' ),
            'singular_name'     => __( 'Product Category', 'myplugin' ),
            'search_items'      => __( 'Search Categories', 'myplugin' ),
            'all_items'         => __( 'All Categories', 'myplugin' ),
            'parent_item'       => __( 'Parent Category', 'myplugin' ),
            'edit_item'         => __( 'Edit Category', 'myplugin' ),
            'add_new_item'      => __( 'Add New Category', 'myplugin' ),
            // ... many more label variants
        ),
        'description'  => __( 'Categorize products', 'myplugin' ),
        'hierarchical' => true,               // category-like (vs tag-like)
    )
);
```

| key | role |
|---|---|
| `$taxonomy` (1st) | unique slug for the classification system |
| `$object_type` (2nd) | array of post type slugs this taxonomy attaches to |
| `hierarchical` | true = tree-structured (categories); false = flat (tags) |

### B. Semantic topology — hierarchical vs non-hierarchical

**Hierarchical taxonomies** (tree-structured, category-like):
- Terms have parent-child relationships.
- Term assignment uses checkbox UI (multiple selectable).
- Implies controlled vocabulary (curated terms).
- Example: site sections, product categories, geographic regions.

**Non-hierarchical taxonomies** (flat mesh, tag-like):
- Terms are peers; no parent-child structure.
- Term assignment uses free-text UI (typeable).
- Implies open / folksonomy vocabulary.
- Example: tags, keywords, free-form labels.

The choice is **classification topology doctrine**, not a UI
preference. Hierarchical = curated tree; non-hierarchical =
flat semantic mesh. Each topology implies different governance,
discovery, and growth patterns.

### C. Governance — capability schema

```php
register_taxonomy( 'product_category', array( 'product' ), array(
    'capabilities' => array(
        'manage_terms' => 'manage_product_categories',
        'edit_terms'   => 'edit_product_categories',
        'delete_terms' => 'delete_product_categories',
        'assign_terms' => 'assign_product_categories',
    ),
) );
```

Four-capability schema with critical distinction:

| capability | governs |
|---|---|
| `manage_terms` | view / list terms in taxonomy admin |
| `edit_terms` | create / modify terms |
| `delete_terms` | remove terms from taxonomy |
| `assign_terms` | attach existing terms to entities |

**Critical: assign_terms ≠ manage_terms.** A user may have
permission to ASSIGN existing terms to their content without
having permission to CREATE / MODIFY / DELETE terms in the
taxonomy. This separation is structurally distinct from CPT
capabilities (which conflate edit/manage at the entity level).

### D. Exposure + routing — semantic permeability

```php
register_taxonomy( 'product_category', array( 'product' ), array(
    'public'             => true,
    'publicly_queryable' => true,
    'show_ui'            => true,
    'show_in_menu'       => true,
    'show_in_nav_menus'  => true,
    'show_in_rest'       => true,
    'show_admin_column'  => true,            // column in CPT list table
    'show_tagcloud'      => true,
    'show_in_quick_edit' => true,
    'rewrite'            => array(
        'slug'         => 'product-category',
        'with_front'   => false,
        'hierarchical' => true,              // /product-category/parent/child/
    ),
    'query_var'          => true,
) );
```

Same exposure governance pattern as CPT (multi-dimensional
declaration ≠ exposure axis): each flag governs a different
visibility dimension. Routing flags federate term archives
into the public URL space (`/product-category/{term-slug}/`).

### E. Entity linkage — cross-entity semantic governance

```php
// Single-entity taxonomy
register_taxonomy( 'product_category', 'product', $args );

// Cross-entity (shared) taxonomy — same taxonomy attached to multiple types
register_taxonomy( 'topic', array( 'post', 'product', 'event' ), $args );
```

`object_type` array determines which entity classes this
classification system attaches to:
- **Single-type taxonomy**: classification scoped to one entity
  type.
- **Shared taxonomy** (cross-type): same vocabulary applies
  across multiple entity types — terms are semantic structures
  spanning entity boundaries.

Shared taxonomies are **structurally relationship-centric**:
they declare that certain semantic distinctions cut across
multiple entity classes. A term in a shared taxonomy can be
attached to instances of different entity types, creating
semantic links between them.

Adding object types after registration:

```php
register_taxonomy_for_object_type( 'topic', 'event' );
```

### Default behavior + reserved taxonomies

If `$args` is empty, defaults are restrictive (similar to CPT
defaults). Reserved taxonomy slugs to avoid include `category`,
`post_tag`, `nav_menu`, `link_category`, `post_format`,
`language`, `wp_theme`, `wp_template_part_area`, plus other
core-reserved terms.

## REQUIRES

- Registration on the `init` action (or sufficiently early hook).
- Taxonomy slug:
  - Must be 1-32 characters.
  - Must NOT collide with reserved terms (see above).
  - Should namespace to avoid plugin collisions
    (`myplugin_topic` rather than `topic`).
- Referenced post types in `$object_type` should be registered
  (or will be — order doesn't strictly matter for registration,
  but matters for runtime queries).
- For custom capabilities: capability membership must be
  granted to roles via `add_cap()`; registration alone does NOT
  grant capabilities.
- For show_in_rest: the post type's REST endpoint must support
  taxonomy exposure (default for public CPTs with show_in_rest).
- ⚠ Specific behaviors for: term meta + REST exposure, shared
  taxonomy edge cases, hierarchical rewrite URL generation,
  capability cache behavior, multilingual plugin interactions —
  verification-needed.

## INVARIANTS

### 1. Taxonomies declare classification authority systems, NOT labels

The load-bearing reframing:

> A taxonomy is NOT a label vocabulary. It is **a classification
> authority system** with its own:
> - term existence (terms are persisted entities)
> - term hierarchy (parent-child OR flat semantic mesh)
> - assignment governance (who may attach which terms to which
>   entities)
> - query surfaces (terms participate in WP_Query, REST queries,
>   archive resolution)
> - routing (term archives federated into URL space)
> - capability schema (4 distinct capabilities)
> - REST integration (terms are entities of kind 'taxonomy')

Reading taxonomy as "categorization labels" misses the
authority ontology. The taxonomy IS a classification authority
system; terms are individual classification subjects within it.

### 2. Taxonomies federate semantic order into the authority graph

> register_taxonomy extends WordPress's authority graph with a
> NEW SEMANTIC LAYER. Where register_post_type added entity
> kinds (subjects), register_taxonomy adds **semantic structures
> across subjects** (relationships, groupings, orderings).

Implications:
- Terms become entity-resolution targets (kind 'taxonomy', name
  taxonomy slug, term ID).
- Term assignment becomes part of entity persistence
  (set_object_terms / wp_set_object_terms).
- Taxonomy queries integrate with WP_Query (tax_query).
- Term archives integrate with template hierarchy
  (taxonomy.html / taxonomy-{slug}.html / etc.).
- Bindings can resolve term values for blocks (via custom
  bindings sources reading term meta).

The taxonomy doesn't request these integrations — it INHERITS
them by virtue of being registered through the
classification-federation API.

### 3. Hierarchical and non-hierarchical taxonomies encode distinct classification topologies

This is **classification topology doctrine** — not a UI choice:

| topology | structure | semantic implication |
|---|---|---|
| **hierarchical** (`hierarchical: true`) | tree (parent-child) | curated controlled vocabulary; structural relationships between terms |
| **non-hierarchical** (`hierarchical: false`) | flat mesh | open / folksonomy; terms are peers; assignment = pure labeling |

Choice consequences:
- Hierarchical implies governance over tree structure (who may
  reparent terms?).
- Non-hierarchical implies open growth (new terms appear via
  assignment).
- Hierarchical provides structural query power (descendant
  inclusion in queries).
- Non-hierarchical provides flexibility but no structural
  reasoning.

Topology choice should be DELIBERATE — not "category looks like
this taxonomy" comparison.

### 4. Object-type attachment governs semantic permeability across entity classes

`object_type` declares **semantic permeability** — which entity
classes the classification system can permeate:

| pattern | semantic implication |
|---|---|
| `array('product')` | classification specific to one entity kind |
| `array('product', 'event')` | shared semantic system; terms span multiple entity classes |
| (later) `register_taxonomy_for_object_type` | extending permeability post-registration |

Shared taxonomies are particularly significant — they declare
**semantic distinctions that transcend entity boundaries**.
A term in a shared "Topic" taxonomy attached to both posts
and events asserts that the SAME semantic distinction applies
to both kinds of entities (posts about "Architecture" + events
about "Architecture" share semantic identity).

This makes taxonomies STRUCTURALLY relationship-centric in a
way CPTs are not. CPTs federate subjects independently;
shared taxonomies federate **relationships across subjects**.

### 5. Taxonomy capabilities separate classification governance from assignment rights

A load-bearing distinction often missed by classic plugin-dev:

| capability | what it permits |
|---|---|
| `manage_terms` + `edit_terms` + `delete_terms` | **classification governance** — modifying the taxonomy's term vocabulary |
| `assign_terms` | **assignment rights** — attaching existing terms to entities |

These are **different security dimensions** (per
security-boundaries doctrine):
- A user may have ASSIGNMENT rights on a controlled vocabulary
  without having governance rights on the vocabulary itself.
- Editorial workflows often grant assign_terms to authors but
  reserve manage/edit/delete to editors / admins.
- Conflating the two (giving everyone manage_terms because
  they need assign_terms) is privilege escalation.

This separation is structurally cleaner than CPT capabilities,
which often conflate management and content rights. Taxonomies
illustrate the **mechanism-dimension orthogonality** invariant
from security-boundaries especially clearly.

### 6. Taxonomy routing federates semantic structures into public addressability

Taxonomy archive URLs (`/product-category/{term-slug}/`) declare
**semantic structures as public address-space citizens**:

```
/product-category/                            ── taxonomy archive (root)
/product-category/electronics/                ── term archive
/product-category/electronics/laptops/        ── nested term archive (hierarchical)
```

Each term archive becomes a public entity in the URL graph,
queryable via main_query, addressable via permalinks,
integrated with template hierarchy (taxonomy template
resolution).

This is qualitatively significant: not only entities (CPT
posts) but also classification structures themselves enter
public addressability. The semantic graph becomes
URL-traversable.

### 7. Taxonomies shift plugin-dev from entity federation toward semantic graph federation

KB-level positioning shift:

```
plugin-dev evolution within this chunk:

   subject federation   ── CPT (register_post_type)
                          "what subjects may exist?"
                          ENTITY-CENTRIC
   ↓
   semantic federation  ── taxonomy (register_taxonomy)
                          "how may subjects be semantically related?"
                          RELATIONSHIP-CENTRIC
```

This pivot mirrors the **entity-centric → relationship-centric
shift** documented in style-engine (generated-selectors,
cascade-aggregation): pre-style-engine KB was entity-centric
("what blocks / settings / styles exist?"); style-engine
introduced relationship-centric ontology ("how do nodes relate
in cascade graph?").

plugin-dev now reaches the same pivot at the federation layer:
- CPT = entity-centric authority extension.
- Taxonomy = relationship-centric authority extension
  (especially shared taxonomies — relationships across entity
  classes).

KB now has parallel pivots in two bounded contexts (style-
engine + plugin-dev). The recurrence suggests **entity →
relationship is a structural pattern in WordPress's ontology
evolution**, not an isolated style-engine quirk.

### 8. register_taxonomy extends the federation stack from constitutional entities to constitutional semantic systems

KB-level closure:

```
plugin-dev federation stack — extended classification surface:

   register-post-type      → entity constitution
                            "what kinds of subjects?"
                            (federation 4/4 closed in CPT chunk)

   register-taxonomy       → semantic constitution
                            "what kinds of relationships across subjects?"
                            (federation surface extended with semantic axis)
```

The federation stack is no longer just "subject extensibility
+ governance"; it includes **semantic extensibility +
governance**. Plugin authors can introduce new semantic systems
that span existing or new entity types.

After this chunk, plugin-dev's authority federation surface
covers:
- WHO provides authority (origin: bindings sources)
- WHERE authority lives (persistence: meta)
- HOW authority moves (transport: REST)
- WHO governs authority (doctrine: security-boundaries)
- WHAT subjects exist (entity: CPT)
- **WHAT semantic structures connect subjects (semantic:
  taxonomy)**

The federation is structurally more complete than CPT alone
provided. Subject + relationship = full federation graph.

## VERIFICATION NEEDED

`status: stable` — register_taxonomy API is mature (WP 2.3+).
Specific behaviors and integrations evolve / vary:

- Term meta REST exposure (register_meta with object_type='term'
  + show_in_rest behavior).
- Shared taxonomy edge cases when one of the object types is
  unregistered later.
- Hierarchical rewrite URL generation rules
  (with_front interaction, ep_mask behavior).
- Default term behavior (`default_term` argument) when
  unregistered roles assign.
- Capability cache invalidation when role caps change
  mid-session.
- Term-meta capability checks (separate from term governance
  capabilities).
- Multilingual plugin interactions (WPML, Polylang) with
  shared taxonomies.
- Pre-WP-6.0 vs post-WP-6.0 REST controller differences for
  custom taxonomies.
- Taxonomy-to-block-template assignment in block themes.
- WP-CLI taxonomy operations vs REST operations capability
  boundaries.
- Performance with large term counts in hierarchical taxonomies.

For practical decisions: empirical testing per scenario over
inferred behavior.

## ANTIPATTERNS

- ❌ **Treating taxonomy as labels**. Taxonomies are
  classification authority systems. Skipping capabilities,
  exposure governance, topology decisions produces
  misconfigured semantic systems.
- ❌ **"Tags vs categories" = UX choice only**. Hierarchical
  vs non-hierarchical is classification topology doctrine —
  determines query power, growth patterns, governance model,
  semantic implications. Decide based on classification
  semantics, not UI familiarity.
- ❌ **Conflating assign_terms with manage_terms**. Granting
  manage_terms when only assign_terms is needed is privilege
  escalation. Authors typically need assign; editors need
  manage. Use the distinction.
- ❌ **`hierarchical: true` for UI nesting only**. If terms
  don't have meaningful parent-child structure, use flat
  taxonomy. Hierarchical implies controlled vocabulary +
  structural reasoning + descendant query inclusion.
- ❌ **Shared taxonomy without considering cross-entity
  implications**. Sharing a taxonomy across CPTs declares
  semantic equivalence between those entity kinds for the
  classification dimension. Audit whether the equivalence is
  intentional.
- ❌ **`show_in_rest: true` = safe semantic exposure**.
  Exposes terms via REST; assignment via REST may have
  capability implications. Audit assign_terms capability
  enforcement.
- ❌ **`rewrite: ...` = SEO only**. Rewrite slugs participate
  in URL space; collisions break navigation. `with_front`
  and `hierarchical` interact with permalink structure.
- ❌ **Reserved taxonomy slug usage**. Using `category`,
  `post_tag`, `nav_menu`, `link_category`, `post_format`,
  `language`, etc. causes registration failure or unpredictable
  collision.
- ❌ **Not namespacing custom taxonomies**. `topic` collides
  with other plugins. `myplugin_topic` is good ecosystem
  hygiene.
- ❌ **Empty `$object_type` array expecting "all"**. The empty
  array attaches to NO entity types (taxonomy exists but
  unattached). Use specific type slugs.
- ❌ **Using `register_taxonomy_for_object_type` as substitute
  for proper registration**. The function ADDS post types to
  existing taxonomy registration; doesn't replace primary
  registration design.
- ❌ **Calling `flush_rewrite_rules()` on every request**.
  Heavy operation; only call on activation hook.
- ❌ **Ignoring `default_term` for required classifications**.
  When entities should always have at least one term, declare
  default_term in registration to avoid orphaned-classification
  states.

## RELATED

- `plugin-dev.register-post-type` — subject constitution
  counterpart. CPT + taxonomy = subject + semantic constitutions
  (federation closure beyond entity-only).
- `plugin-dev.security-boundaries` — doctrine framework
  applied to semantic governance. Taxonomy capability schema
  exemplifies mechanism-dimension orthogonality (manage vs
  assign separation).
- `plugin-dev.register-meta` — term meta is registerable via
  register_meta with object_type='term'. Same persistence
  substrate extension applies to terms as to posts.
- `plugin-dev.register-rest-route` — show_in_rest auto-creates
  default REST routes for taxonomy. Custom routes can extend
  / replace via rest_controller_class.
- `data-layer.entity-resolution` — registered taxonomy enables
  term entity resolution: getEntityRecords('taxonomy',
  'product_category') etc.
- `block-authoring.block-json.bindings` — bindings can target
  term data via custom sources reading term meta or term
  relationships.
- (planned) `plugin-dev.capabilities-and-roles` — capability
  model deeper dive. Taxonomy adds the assign_terms
  distinction; capability chunk benefits from concrete CPT +
  taxonomy examples.
- `style-engine.generated-selectors` — entity → relationship
  pivot symmetry. Style-engine documented this pivot at the
  CSS layer; taxonomy chunk documents the parallel pivot at
  the entity-federation layer.

## META

**plugin-dev bounded context — semantic federation extension
(post-CPT-closure).**

```
plugin-dev (after this chunk):
   register-block-bindings-source  → trust / origin            ✓
   register-meta                   → legitimacy / persistence   ✓
   register-rest-route             → permeability / transport   ✓
   security-boundaries             → governance doctrine        ✓
   register-post-type              → entity / subject species   ✓
   register-taxonomy               → semantic / classification  ✓
   ↓
   FEDERATION + SEMANTIC STACK COMPLETE.
```

Federation stack (4 layers) was closed by CPT chunk; this
chunk adds the **semantic axis** that gives plugin-dev full
graph-federation expressiveness (subjects + relationships).

**KB-level framing extension:**

> If custom post types govern **what authority-bearing
> subjects may exist**, then taxonomies govern **how those
> subjects may be semantically ordered, grouped, and
> traversed**.

CPT + taxonomy together constitute plugin-dev's full
authority + semantic federation surface. Subsequent
plugin-dev chunks (capabilities, nonces, hooks, filters,
slotfills, settings) extend within this structurally complete
foundation.

**KB-wide ontology pattern recurrence:**

The entity → relationship pivot now appears in TWO bounded
contexts:

| bounded context | entity-centric | relationship-centric |
|---|---|---|
| **style-engine** | block instance / preset / variable | generated selectors / cascade graph / topology |
| **plugin-dev** | CPT (subject species) | shared taxonomies / cross-entity semantic links |

The recurrence suggests **entity → relationship is a structural
pattern in WordPress's ontology evolution**, not isolated to
style-engine. Similar pivots may surface in:
- editor-customization (block trees → block relationship hooks)
- site-building (templates → template-part composition graphs)
- interactivity (per-namespace stores → cross-store coordination)

This pattern recognition is itself a KB-level framing
contribution.

**plugin-dev domain identity — final form:**

> Plugin-dev bounded context is the **external authority +
> semantic architecture** layer of WordPress, structured as a
> **federation + classification stack** (origin / persistence /
> transport / entity / semantic) with **multi-boundary ABI
> declarations** + **layered security governance** + **graph-
> federation expressiveness**.

Subsequent plugin-dev chunks extend WITHIN this structurally
complete identity.

**Status `stable`:**

Taxonomy API is mature; doctrine framing is stable. Specific
behaviors are verification-needed (cataloged) but core API
contract is settled.

**DSL extensions applied:** VERIFICATION NEEDED + META.

**Anticipated next chunks (priority):**

1. **`plugin-dev.capabilities-and-roles`** — capability model
   formalization. Now strongly warranted: CPT capability_type
   + taxonomy 4-capability schema both reference capability
   model. Doctrine-tier chunk parallel to security-boundaries.

2. **`plugin-dev.nonces`** — CSRF mechanism deeper dive.
   Referenced in security-boundaries.

3. **Other plugin-dev families** — block filters / slotfills /
   hooks / settings / register_block_pattern_category.

4. **Other bounded contexts** (additive): editor-customization /
   site-building / i18n / build-tooling / admin-ui.

5. **KB audit / cross-ref completeness** — at 67+ chunks,
   ontology audit warranted for cross-ref integrity + spec
   evolution.

Recommended next: capabilities-and-roles (closes capability
model formalization with concrete CPT + taxonomy
examples). After that: choice between deepening plugin-dev or
entering additive bounded contexts.

**KB self-evaluation against spec criteria:**

- ✅ Accuracy — describes documented API contract.
- ✅ Structural fit — adds semantic axis to plugin-dev
  federation stack; surfaces entity → relationship pattern
  recurrence.
- ✅ Reusability — uses authority ontology glossary
  (federation / classification / semantic / subject /
  permeability / topology).
- ✅ Phase fit — natural composition with CPT chunk;
  references all relevant federation layers.
- ✅ Doctrine respect — directly applies security-boundaries
  (manage vs assign distinction); HTML primacy implicit
  (term archives render as HTML pages via template hierarchy).
