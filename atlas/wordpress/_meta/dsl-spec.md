# Knowledge OS — Chunk DSL Spec

This document defines the schema and conventions for all chunks under
`./knowledge/wordpress/`. Every chunk is a single rule expressed in a
6-slot structured format with YAML frontmatter for filtering metadata.
This is a Prompt DSL — chunks are designed for LLM consumption, not human
prose reading.

**KB self-definition (as of 2026-05-09)**: This KB is no longer a
documentation summary system. It is an **operational ontology atlas of
WordPress/Gutenberg's authority architecture**. Specs in this document
exist to keep the atlas internally coherent as it grows.

## File naming

```
./knowledge/wordpress/{bounded-context}/{topic-area}/{rule-slug}.md
```

- `bounded-context` — one of the 11 contexts (see below)
- `topic-area` — optional sub-folder grouping related rules (e.g., `registration/`)
- `rule-slug` — kebab-case, matches the second half of `rule_id`

Filename `register-via-block-json.md` ↔ `rule_id: block.register-via-block-json`.

## Bounded contexts (DDD)

WP domain partitioned into 11 bounded contexts. Each becomes a top-level
folder under `./knowledge/wordpress/`:

| Context | Scope |
|---|---|
| `block-authoring` | block.json, registration, edit/save, attributes, supports, variations, transforms, deprecation, bindings |
| `theme-config` | theme.json (settings/styles), templates, template-parts, patterns, style-variations |
| `style-engine` | runtime CSS synthesis (generated selectors, variable emission, preset materialization, cascade aggregation) |
| `editor-customization` | block filters, editor hooks, slotfills, curating editor experience |
| `data-layer` | core-data stores, useSelect/useDispatch, REST API endpoints, entity resolution, persistence |
| `site-building` | templates vs patterns vs parts, global styles, site editor |
| `interactivity` | Interactivity API directives, runtime state, hydration, server-side processing |
| `plugin-dev` | lifecycle, hooks, security (nonces / sanitize / escape / capabilities), settings API, CPT/taxonomy |
| `i18n` | gettext, localization, POT/PO/MO |
| `build-tooling` | wp-scripts, wp-env, create-block |
| `admin-ui` | admin menus, settings pages, meta boxes, notices |

`style-engine` was discovered as a bounded context during KB authoring
(post-block-authoring). The original 10 didn't include it; runtime CSS
synthesis surfaced as a distinct authority layer requiring its own
context.

Cross-cutting topics live in their primary context. Cross-references go
through the `related:` frontmatter field — explicitly allowed and
encouraged across bounded contexts.

## Frontmatter schema

```yaml
---
rule_id: <bounded-context>.<rule-slug>     # required, globally unique
domain: <bounded-context>                   # required, must match folder
topic: <topic-area>                         # required, sub-area within context
wp_min: "<x.y>"                             # required, minimum WP compatible
wp_recommended: "<x.y>"                     # optional, current best-practice version
status: <stable | evolving | experimental | deprecated>  # required, filter key
language: <php | js | css | json | html | mixed>  # required, primary code lang
sources:                                    # required, at least 1
  - url: <fully-qualified URL>
    section: <heading text from source>
    captured: YYYY-MM-DD                    # date the source was last verified
field_cluster: <cluster-name>               # optional, for schema-field chunks
                                            # (e.g., identity, assets, capabilities)
related:                                    # optional, list of sibling rule_ids
  - <rule-id>                               # cross-context refs ALLOWED
  - <rule-id>
deprecates:                                 # optional, rule_ids this supersedes
  - <rule-id>
deprecated_by:                              # optional, rule_id that supersedes this
  - <rule-id>
---
```

### Frontmatter field semantics

#### `status` — epistemic stability classification

`status` is **NOT a completeness indicator** ("how done is this chunk?").
It is an **epistemic stability classification** ("how stable is the
underlying authority surface this chunk documents?").

Four documented values:

| status | meaning |
|---|---|
| `stable` | underlying authority surface is well-documented, settled, unlikely to shift in subsequent WP versions |
| `evolving` | runtime / implementation-derived / recent-API authority surface — semantics may refine per WP version even though the contract surface is documented |
| `experimental` | feature flag / Gutenberg-only / not yet in core |
| `deprecated` | works but newer alternative exists; chunk should `deprecated_by` the replacement |

**`evolving` ≠ "incomplete" or "less authoritative."** It signals that
the chunk documents a target whose runtime semantics are themselves
moving. Examples currently using `evolving`:
- `block-authoring.block-json.bindings` (WP 6.5+, API still maturing)
- `data-layer.entity-resolution` (mature core, evolving editor
  integration)
- `data-layer.persistence` (stable APIs, evolving conflict /
  reconciliation semantics)
- `interactivity.directive-protocol` (stable WP 6.5 release,
  ongoing additions per version)
- `interactivity.runtime-state` (stable core, evolving reactivity
  internals)
- `interactivity.hydration` (high implementation density, evolving
  server-processing rules)

The criterion: if the underlying mechanism is implementation-derived
(visible only by reading source code or runtime inspection) and may
shift behavior across WP versions without explicit deprecation, mark
`evolving`. If the authority surface is fully documented and contract-
stable, mark `stable`.

#### Other frontmatter fields

- `wp_min` is the SDK floor — using this rule below this version will fail
- `wp_recommended` is when the stable form became preferred — older
  versions may have working alternatives
- `captured` date matters because WP docs change; stale captures need
  re-verification
- `field_cluster` (optional) — used for schema-field chunks under areas
  like `block.json/`. Names a logical grouping of related fields:
  `identity` (name/title/etc.), `assets` (script/style/render),
  `capabilities` (supports flag), `data` (attributes), `hierarchy`
  (parent/ancestor/allowedBlocks), `context` (providesContext/usesContext),
  `bindings` (runtime authority attachment), `directive-surface`,
  `store-actions-state`, `runtime-boundaries`, `entity-graph`,
  `write-substrate`, etc. Helps batch-filter "show me all identity-
  cluster fields" type queries.
- **`verification-needed` value convention** — when source docs do not
  disclose a fact (typically `wp_min`), use the literal string
  `"verification-needed"` as the field value. This is distinct from
  `status: evolving`: `verification-needed` says "I don't know this
  specific fact"; `evolving` says "the underlying authority surface
  evolves."

Filter examples for KB consumers:
- "show only stable rules for WP 6.6+" → `status==stable AND wp_min<=6.6`
- "exclude all deprecated" → `status!=deprecated`
- "JS-only rules" → `language==js`
- "include evolving runtime authority chunks" → `status==stable OR status==evolving`

## Body structure — 6 slots

Every chunk has these 6 H2 sections, in this order:

### 1. `## WHEN`
Conditions under which this rule applies. Frame as decision criteria, not
narrative. The reader should be able to answer "does this rule fit my
current task?" from this slot alone.

### 2. `## SHAPE`
The actionable form of the rule. Function signatures, code shapes,
configuration structures. Code blocks with language tags. If parameters
matter, use a table. Keep prose minimal — the code IS the answer.

### 3. `## REQUIRES`
Prerequisites, preconditions, environment requirements. What must already
be true for the SHAPE to work. File paths, hook timing, minimum versions
of related libraries.

### 4. `## INVARIANTS`
Things that MUST hold during/after applying the rule. Often format,
uniqueness, ordering, or cardinality constraints.

This slot also carries `⚠ verification-needed` notes inline — facts the
source did not disclose. Format: lead the bullet with `⚠`, name the
missing fact, suggest a runtime workaround (e.g., feature detection).

#### INVARIANTS sub-heading convention (capability semantics)

For capability-semantics chunks (e.g., `block.supports.{flag}`,
`theme.json.styles.{prop}`) where the rule triggers a **cascade of
system-wide effects**, structure INVARIANTS with H3 sub-headings drawn
from this controlled vocabulary:

```md
## INVARIANTS

### Editor effects
### Attribute effects
### Wrapper effects
### Serialization effects
### theme.json interaction
### General invariants
```

Use ONLY these 6 sub-heading names; do not invent new ones per chunk.
Procedural-rule chunks keep INVARIANTS as flat bullet list.

### 5. `## ANTIPATTERNS`
What NOT to do. Common mistakes, deprecated alternatives, scope misuse.
Each item should explain *why* it's wrong, not just label it.

### 6. `## RELATED`
Sibling / parent / variant rules. List `rule_id` references with one-line
descriptions. Cross-context refs are explicitly allowed and encouraged.

## DSL extensions (for runtime/implementation-derived chunks)

Some chunks document **runtime / implementation-derived authority
surfaces** rather than declarative schema. These chunks have epistemic
characteristics that the base 6-slot DSL doesn't fully accommodate:

- Many behaviors are observable only through runtime inspection.
- Handbook prose under-documents implementation specifics.
- Behaviors may vary across WP versions without explicit API change.
- The chunk's role is partly to expose ontology, not just contract.

Such chunks may add **two extension sections** between the base 6 slots:

### Extension: `## VERIFICATION NEEDED`

Inserted after `## INVARIANTS`, before `## ANTIPATTERNS`. Lists
implementation-derived items the chunk did not authoritatively verify.
Distinct from inline `⚠ verification-needed` (which marks individual
facts within INVARIANTS).

This separate section is appropriate when the chunk has **enough
verification-needed items** that listing them inline would clutter
INVARIANTS. Density signals epistemic character of the bounded context,
not a documentation gap.

### Extension: `## META`

Inserted after `## RELATED`, at the end of the chunk. Documents:
- KB-level positioning (phase, bounded context status, capstone role).
- KB-level framing extensions this chunk contributes.
- Cross-chunk pipeline / framing relationships.
- Anticipated next chunks / dependencies / future retro candidates.

### Applicability

Originally these extensions were `style-engine`-bounded-context-specific.
After Phase 7 entry (bindings + data-layer + interactivity), the
applicability **generalized**:

> **The DSL extensions apply to chunks documenting**
> **runtime / implementation-derived authority surfaces with**
> **runtime authority indeterminacy — regardless of bounded context.**

Trigger criteria for using the extensions:
1. Underlying mechanism is partially implementation-derived
   (handbook documents the contract surface but not all runtime
   semantics).
2. Behavior may vary across WP versions or environments
   (editor vs frontend, server vs client).
3. The chunk needs to surface ontology, not just contract.

Chunks meeting these criteria typically also use `status: evolving`.

The two are correlated but not identical: `evolving` is a status
classification; DSL extensions are body-structure additions.

## RETROACTIVE REFRAMING pattern

A KB-native pattern that emerged during authoring. Established with
3 instances (as of 2026-05-09):
1. `block.wrapper-attributes` (post-style-engine closure)
2. `block.dynamic-rendering` (post-Phase-7-capstone)
3. `block.markup-representation` (post-Phase-7-capstone)

### When to apply

When a downstream bounded context closes and reframes the role of an
upstream chunk that was written before the reframing was visible. The
upstream chunk gains a layered re-reading — NOT a rewrite.

### When NOT to apply

If the original chunk is INACCURATE (not just incompletely framed),
update it normally — don't use the retroactive pattern as cover for
correction. Retroactive reframing is for **structural revelation**
(latent ontology surfacing), not for fixing errors.

### Pattern structure

```md
## RETROACTIVE REFRAMING (post-{trigger-context-closure})

**Status note**: ...explains when added and why...

**KB pattern**: ...references the broader pattern + other instances...

### Reframing — {one-line summary of the ontological shift}

{prose paragraph explaining pre-reframe vs post-reframe ontology}

### RETROACTIVE INVARIANTS

#### A. {invariant title}
{body — uses concrete patterns from downstream bounded contexts}

#### B. ...
...

### Pipeline closure / KB-level framing payoff

{shows how this retro contributes to KB narrative coherence}
```

### Philosophical framing

The pattern signals that the KB is not a static documentation system —
it is a **progressive ontology revelation system**. Architecture often
exists in code before it has been formalized in documentation; retros
formalize what was always operationally present.

This is structurally different from a "documentation update" — the
underlying mechanism didn't change; the KB's ability to perceive it
matured.

## Authority ontology glossary

KB-wide vocabulary lock. These terms recur across many chunks; their
semantics are fixed here to prevent drift.

### Foundational vocabulary

| term | meaning |
|---|---|
| **authority** | the locus that determines a value, behavior, or state. Authority can be declared (compile-time) or attached (runtime). Example: theme.json declares authority over the color palette; entities hold authority over post content. |
| **ownership** | exclusive control over a value. Distinct from authority: a block may have authority over its attributes WITHOUT owning the values (bindings can host the values from elsewhere). |
| **attachment** | the relationship between a host (block, attribute, DOM node) and an authority source. The host hosts the attachment; the authority source provides the value/behavior. |
| **realization** | the process of turning declarative authority into observable output. Style-engine realizes theme.json declarations into CSS; render_callback realizes entity state into HTML. |
| **reconciliation** | the protocol for resolving conflicting / partial / async authority states. Persistence reconciles edited state with persisted state; cascade reconciles competing style declarations. |
| **continuity** | the property of authority remaining identifiable as it crosses execution / serialization / network boundaries. Hydration preserves authority continuity from server render to client runtime. |
| **projection** | a partial / temporary view of authority. Block instances project entity state; static post_content projects current invocation state; rendered DOM projects compiled style state. |
| **substrate** | the structural layer in which a kind of authority lives. Entity authority substrate (data-layer); reactive coordination substrate (interactivity store); attachment substrate (HTML markup). |
| **runtime locality** | the scope within which a piece of state has meaning. Per-namespace store locality; per-entity record locality; per-instance generated-selector locality. |
| **escalation** | promoting an authority to a higher-priority emission path when normal expression is insufficient. Inline styles escalate per-instance authority above generated-selector specificity. |
| **reactive edge** | a subscription / dependency relationship in the runtime graph. Directives create reactive edges between DOM nodes and store state. |
| **orchestration** | coordination across multiple authority kinds / substrates / lifetimes. Interactivity orchestrates ephemeral state, entity state, and persistence reconciliation. |

### Recurring axes

| axis | poles |
|---|---|
| **declaration vs realization** | what is declared (settings/supports) vs what materializes (styles/wrapper/cascade) |
| **declaration vs exposure** | what exists (registry) vs what is selectable (gates) |
| **copy vs reference** | independent state on insertion (patterns) vs live-linked (template parts) |
| **compile-time vs runtime authority** | static declarations (Phases 1-6) vs runtime attachment (Phase 7) |
| **persisted vs ephemeral vs derived** | three lifetimes in the authority lifetimes ontology |
| **entity-centric vs relationship-centric** | what exists (entities, blocks) vs how they relate (generated selectors, layout topology) |

These axes recur across chunks; reuse them rather than reinventing
synonymous distinctions per chunk.

## KB phase model

The KB has accumulated an internal chronology that organizes chunk
positioning and retroactive reinterpretation. Documented here for
explicit reference.

| phase | identity | bounded contexts |
|---|---|---|
| **1-3** (block-authoring) | declaration authority | block-authoring (substantively closed) |
| **4** (theme-config) | configuration authority | theme-config (substantively closed) |
| **5-6** (style-engine) | realization / compiler authority | style-engine (CLOSED) |
| **7a** (bindings) | runtime authority attachment | block-authoring.bindings (entry) |
| **7b** (data-layer) | runtime authority substrate (read + write) | data-layer (substrate sealed) |
| **7c** (interactivity) | reactive coordination grammar + substrate | interactivity (backbone sealed) |
| **7-capstone** (hydration) | distributed authority continuity across execution boundaries | interactivity.hydration |

### KB-level question pivots

Each phase boundary corresponds to a question pivot:

| phase | dominant question |
|---|---|
| Phases 1-6 | What authority exists? |
| Phase 7 substrate | What authorities are attached to what? |
| Phase 7 capstone | How does authority cross execution boundaries? |

### How to use the phase model

When writing a new chunk:
1. Identify which phase the underlying mechanism belongs to.
2. Reference appropriate substrate from earlier phases as cross-refs.
3. If the chunk is in a later phase, check whether earlier-phase
   chunks would benefit from a retroactive reframing section once
   this chunk is written.

When entering a new bounded context (the 6 additive contexts:
editor-customization / site-building / plugin-dev / i18n / build-tooling /
admin-ui):
1. Identify the phase fit (most likely Phase 7 substrate or
   later — these are additive on a closed structural backbone).
2. Reference the appropriate phase's authority models as foundation.
3. Avoid re-inventing terminology; use the authority ontology glossary.

## HTML primacy doctrine

Established as KB-level structural framing in the markup-representation
retro (post-Phase-7-capstone). Documented here for spec-level reference.

> **Gutenberg sophisticated THROUGH HTML, not around it.**
>
> As Gutenberg evolved through Phases 1-7 (schema → compiler →
> runtime → reactive orchestration), HTML's role expanded from
> "output format" to **"universal continuity substrate"** — but
> it remained foundational.
>
> This is structurally distinct from SPA frameworks that treat
> HTML as "intermediate render output" subordinate to virtual trees /
> component models. Gutenberg keeps HTML as PRIMARY REALITY through
> every layer of architectural complexity.

### Implications for chunk authoring

When documenting a Gutenberg mechanism that interacts with markup:

1. **Default framing**: HTML is authority continuity carrier, not output.
2. **Avoid SPA framings**: don't describe Gutenberg mechanisms as if
   HTML were an intermediate render product subordinate to JS state.
3. **Acknowledge HTML dual role**: HTML carries content AND executable
   attachment topology (classes, attributes, directives, delimiter
   metadata).
4. **Cross-runtime mediation**: when a mechanism crosses execution
   boundaries (PHP↔browser, server↔client, parser↔editor), expect HTML
   to be the medium.

### What this is NOT

- NOT a prescription that all chunks must invoke "HTML primacy."
- NOT a statement that HTML is the only valid representation in
  Gutenberg (block tree is in-memory; entities live in DB).
- NOT an invitation to anti-React polemic — React is used in the
  editor; Gutenberg's distinction is at the FRONTEND runtime where
  Interactivity API is HTML-primary.

The doctrine is a **load-bearing framing constraint** that keeps KB
chunks aligned with WordPress's architectural inheritance.

## What chunks must NOT contain

- ❌ Project-specific content (Axismundi codebase paths, project file
  references, project-internal decisions). Project-layer content lives
  under `./knowledge/axismundi/` (planned, currently deferred).
- ❌ Verbatim handbook prose ("Welcome to..." / "This guide will...").
  The DSL extracts rules; pedagogy is filtered out.
- ❌ Deprecated APIs as primary content. Deprecated APIs appear only in
  ANTIPATTERNS slots of stable rules, OR as standalone chunks with
  `status: deprecated` and `deprecated_by` pointing to the replacement.
- ❌ Korean prose. Frontmatter, code, and English body only.
- ❌ Multiple rules per chunk. One chunk = one `rule_id` = one rule.

## Length

Soft target: 2000-5000 chars per chunk (English).

**Capstone / phase-transition / bounded-context-entry chunks** may run
longer (8000-13000 chars observed for Phase 7 chunks). When a chunk
sits at a structural inflection point (entering a new bounded context,
introducing a new authority kind, capping a phase), the additional length
serves the framing weight rather than indicating the chunk should split.

When a chunk is genuinely covering multiple sub-rules with separable
concerns, split. When a chunk's length is driven by ontological depth
of a single rule, keep unified.

## Updates

When a chunk is updated:
1. Re-verify `sources[].url` content; update `captured` date.
2. If WP version moved past `wp_recommended`, consider whether the rule
   itself needs updating.
3. Increment file's mtime via Edit; no version field in frontmatter
   needed (git history is the version).
4. If a downstream bounded context closure has revealed new ontological
   role, consider RETROACTIVE REFRAMING section addition rather than
   rewrite.

## Reference exemplars

- `./knowledge/wordpress/block-authoring/registration/register-via-block-json.md` —
  first validated procedural-rule chunk.
- `./knowledge/wordpress/block-authoring/block-json/basic-metadata.md` —
  schema-field cluster chunk (identity).
- `./knowledge/wordpress/block-authoring/block-json/supports-field.md` —
  capability gateway chunk (5-layer cascade in INVARIANTS).
- `./knowledge/wordpress/block-authoring/block-json/bindings.md` —
  phase transition chunk (uses `evolving` status + DSL extensions).
- `./knowledge/wordpress/style-engine/cascade-aggregation.md` —
  bounded-context capstone chunk (extensive VERIFICATION NEEDED + META).
- `./knowledge/wordpress/interactivity/hydration.md` —
  Phase 7 capstone chunk (DSL extensions + KB-level framing payoff).
- `./knowledge/wordpress/block-authoring/wrapper-attributes/wrapper-attributes.md` —
  RETROACTIVE REFRAMING pattern (first instance).
- `./knowledge/wordpress/block-authoring/markup-representation/block-grammar-and-ir.md` —
  RETROACTIVE REFRAMING pattern (third instance, KB narrative arc closure).

New chunks should match the closest exemplar's shape.

## Known DSL pressure points (verified by spike)

**INVARIANTS slot becoming heterogeneous** in capability-semantics
chunks. The `block.json-supports-field` spike compressed 5 cascade
layers into INVARIANTS as 5 sub-bullets. Worked, but borderline.
Resolved with the H3 sub-heading convention.

**Per-flag chunks** repeated the 5-layer cascade with flag-specific
details. INVARIANTS H3 sub-heading convention proved sufficient; no
slot extension needed.

**Runtime ontology chunks** (style-engine, Phase 7) introduced epistemic
density that warranted DSL extensions (VERIFICATION NEEDED + META).
The extensions are bounded-context-aware (apply to runtime ontology
chunks, not declarative-schema chunks).

**Phase transition chunks** (bindings, hydration) and bounded-context
capstones (cascade-aggregation, hydration) developed length / framing
weight beyond the 2000-5000 char target. Length norm relaxed for
inflection-point chunks.

**Retroactive reframing** emerged organically as a pattern; formalized
here. Three instances established the pattern's structure; future
instances should follow the formalized template.

## KB self-definition (closing note)

The KB is now operating at:
- **operational ontology atlas** (not documentation summary)
- **authority cartography** (not field reference)
- **progressive ontology revelation system** (not static doc set)

Chunks should be evaluated on:
1. **Accuracy** — does the chunk correctly describe its target?
2. **Structural fit** — does the chunk extend the ontology coherently?
3. **Reusability** — does the chunk use established vocabulary
   (authority ontology glossary) rather than inventing synonyms?
4. **Phase fit** — does the chunk position itself correctly relative
   to KB phase model?
5. **Doctrine respect** — does the chunk honor HTML primacy and other
   established framings where applicable?

When all five criteria align, the chunk extends the atlas. When they
diverge, surface the tension explicitly (often a sign that ontology
is shifting and the spec itself may need revision).
