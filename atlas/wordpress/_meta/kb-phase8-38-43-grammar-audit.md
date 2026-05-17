---
doc_id: meta.phase8-38-43-grammar-audit
type: structural-audit
scope: doctrinal-grammar-observation
status: bounded-observation
domain: kb-methodology
not_constitutional: true
not_section_x: true
no_structural_patch: true
captured: 2026-05-10
related:
  - _meta.kb-phase8-27-37a-structural-audit
  - _meta.kb-phase8-27-site-building-forward-composure
  - _meta.structural-patterns
---

# Phase 8.M2 — Grammar Audit (Phase 8.38 → 8.43)

A bounded structural observation note.
Phase 8.M1 (the first audit) catalogued the
*toolkits* the KB had developed across 23
chunks. Eight chunks later (8.38–8.43, plus the
two retroactive observations introduced
through them), the catalog is bigger but the
more interesting development is **how the
toolkits themselves have grammar-sharpened**:
new criteria for membership, refined family
shapes, finer units of analysis.

This audit asks one question: **what has the
KB's interpretive precision become, beyond
pattern recognition?**

**This note is not a constitutional event.**
No new law, sub-pattern, doctrine, or Section X
element is introduced or modified.
`structural-patterns.md` is not amended. The
note records observations about the *grammar
of fit*, not about the *constitutional
patterns themselves*.

## A. The 8 chunks since M1

Phase 8.M1 closed at 8.37a (23 chunks). Since
then:

| Phase | Chunk                                    | Terrain                              |
| ----- | ---------------------------------------- | ------------------------------------ |
| 8.38  | `server-side-render-component`           | block-authoring (preview transport)  |
| 8.38a | `data-wp-watch-and-init`                 | interactivity (reactive callbacks)   |
| 8.39  | `dashboard-widgets`                      | admin-ui (institutional modularity)  |
| 8.40  | `rewrite-rules-and-pattern-resolution`   | plugin-dev (pattern arbitration)     |
| 8.41  | `query-vars-and-main-query-resolution`   | site-building (semantic execution)   |
| 8.42  | `block-styles-registration`              | block-authoring (operator variants)  |
| 8.43  | `format-types-registration`              | editor-customization (inline formats) |

Eight chunks; six terrains. Coverage continued
to broaden — but the audit's interest is
elsewhere. The interpretive precision changes
are what Section B–E document.

## B. Toolkit deltas since M1

Each of the four toolkits established at M1
grew, and one new analytical pattern emerged:

### ◆ Anti-Law-4 toolkit — *8 → 10 members*

New members since M1:
- *Hook priority ≠ candidate arbitration*
  (8.36, hooks lifecycle).
- *Scheduled queue ≠ candidate arbitration*
  (8.36a, cron).
- *Version branch ≠ candidate arbitration*
  (8.37a, apiVersion).
- *Operator-selected variant ≠ candidate
  arbitration* (8.42, block styles).
- *Compound application ≠ candidate
  arbitration* (8.43, format types).

(Some of these were members of the inventory
at M1's point-in-time count of "8"; the
labels above name the chunks where they were
formalized.)

### ● Existence-vs-operation toolkit — *13 → 21 members*

The biggest growth. New members include:
- *Scheduled ≠ executed* (cron).
- *Bit-exact match ≠ semantic equivalence*
  (validation).
- *Platform versioning ≠ content versioning*
  (apiVersion).
- *Embedded ≠ activated, Reactive binding ≠
  executed action* (interactivity).
- *Registered widget ≠ rendered presence*
  (dashboard widgets).
- *Top rule ≠ active rule* (rewrite rules).
- *Matched route ≠ resolved query* (query vars).
- *Available style ≠ active style*,
  *Available format ≠ applied format*
  (operator-selected variant chunks).

### △ Anti-Law-3b inventory — *3 → 4 members*

One addition:
- *REST request-response transport ≠
  cross-runtime authority continuity*
  (ServerSideRender component).

### Federation refinement — *single observation → 3-variant family*

M1 noted *"multiplicity ≠ federation"* as a
single distinction. Since then:

- 8.39 introduced **structured-placement
  federation** (dashboard widgets).
- 8.40 introduced **registration-arbitration
  hybrid** (rewrite rules: open registration,
  one wins).
- 8.41 introduced **registration-composition
  hybrid** (query vars: open registration, all
  contribute to single shared object).
- 8.43 added a **user-triggered composition
  refinement** to the third variant (format
  types: same shape but per-user-action
  rather than automatic).

Federation has become a *pattern family with
substructure*, not a single shape.

### Newly emerged: composite-mechanism observation

8.41 introduced a previously-implicit
analytical pattern: **doctrinal fit can
attach at finer granularity than chunk-level**.
Within one request lifecycle, the rewrite
stage is true Law 4 arbitration; the
post-rewrite query var lifecycle is composition.
Same pipeline, different doctrinal profile per
stage.

8.42 extended this with the parallel
within-bounded-context observation: adjacent
mechanisms in the same broad terrain can have
opposite Law 4 fit (block styles
operator-selected vs block variations
algorithm-matched).

8.43 expanded that observation to *three*
adjacent mechanisms: format types
(text-selection scope, anti-Law-4) + block
styles (block-instance scope, anti-Law-4) +
block variations (block-instance scope,
Law-4-adjacent).

The pattern: **the unit at which doctrinal
fit is interesting can be smaller than the
chunk** — stages within mechanisms; adjacent
mechanisms within bounded contexts.

## C. Law 4 grammar — definitional refinement + balance

The most consequential grammar refinement of
the post-M1 phase: 8.40 (rewrite rules)
**defined what makes Law 4 positively fit**.
Before 8.40, Law 4 was largely characterized
by what *non-fits* lacked. After 8.40, the
positive criterion is named explicitly:

> *Ordered + terminal + discarding = true
> Law 4 arbitration.*
>
> - **Ordered**: candidates are evaluated in
>   a defined sequence.
> - **Terminal**: the search stops at first
>   success.
> - **Discarding**: unselected candidates are
>   *structurally inert* for this resolution
>   (not deferred; not deprioritized; just
>   not applied).

This three-criterion specification sharpens
both sides of the inventory. Anti-Law-4
members lack at least one criterion (typically
"terminal" or "discarding" — they may be
ordered, but they don't terminate or discard).
Positive members satisfy all three.

### Updated balance

| Side          | Members |
| ------------- | ------- |
| Anti-Law-4    | 10      |
| Positive Law 4 anchors | 5 (template hierarchy / locate_template / nav menu fallback / block deprecation matching / rewrite rules) |

The ratio has shifted from 8:1 (M1 noted as
asymmetric) to 10:5 (substantively closer).
The positive side gained an anchor (rewrite
rules) and gained the definitional criterion
that makes the existing positive anchors
*coherently positive*. The asymmetry remains
but is no longer single-anchored or
terrain-narrow.

This audit's central observation about Law 4:
**the inventory's growth has been accompanied
by the definitional sharpening that makes both
sides legible**. Recognition of false fit is
not skepticism for skepticism's sake; it is
specification of what fit *requires*.

## D. Federation grammar — variant matrix

Federation has progressed from a single
distinction (M1: *multiplicity ≠ federation*)
to a family of variants, each with its own
combination of registration-side and
resolution-side semantics:

| Variant                          | Registration shape       | Resolution shape                         | Example                       |
| -------------------------------- | ------------------------ | ---------------------------------------- | ----------------------------- |
| **Pure federation**              | Open, ordered            | All execute in priority order            | Hooks (`add_action`)          |
| **Structured-placement**         | Open, with context slots | All render in placement-aware structure  | Dashboard widgets             |
| **Registration-arbitration hybrid** | Open, ordered          | First match wins; others discarded       | Rewrite rules                 |
| **Registration-composition hybrid** | Open, with mutation API | All contribute to single shared object   | `query_vars`, `pre_get_posts` |

Plus an **operator-triggered composition**
refinement (format types, block styles): the
registration-composition shape but with the
composition triggered by *user actions* rather
than automatic mutation.

The matrix axes are:

- **Registration shape**: how participants
  register (always open in federation; the
  variant lies in *what structure constraints
  registration carries* — none, slots, position,
  etc.).
- **Resolution shape**: what happens when the
  registry's content is consumed (parallel
  execution, structured render, terminal
  selection, composed mutation, user-driven
  composition).

The matrix makes federation legible as a
*spectrum of resolution semantics*, not a
binary "is or isn't federation." The label is
no longer the question; the resolution
semantics are. *Federation* names a
registration shape; *resolution semantics*
specify how the registry is realized.

## E. Analytical units — where doctrinal fit attaches

M1 implicitly assumed doctrinal fit attaches
at *chunk-level* — each chunk's Operational
Notes named what fit and what didn't. The
post-M1 chunks made finer units explicit:

| Unit                                | Where surfaced                                               |
| ----------------------------------- | ------------------------------------------------------------ |
| **Chunk-level fit**                 | The default; M1 documented every chunk this way              |
| **Stage-level fit within one mechanism** | 8.41 (query vars): rewrite stage is Law 4, post-rewrite stages are composition |
| **Within-bounded-context asymmetry** | 8.42 (block styles vs variations) and 8.43 (3-way: format types / styles / variations) |

The pattern: **larger structures (request
lifecycle, bounded context) can contain
sub-units with different doctrinal profiles**.
The audit's observation: doctrinal-fit
analysis is finer-grained than the chunk
boundary — it attaches to the actual
mechanism whose shape is being characterized,
which can be smaller than a chunk.

This refines what audits *do*. M1 audited
the chunk inventory; M2 observes that the
analytical unit itself is variable. Future
chunks describing complex multi-stage
mechanisms can now name stage-level fit
directly without reaching for the whole-chunk
classification.

## F. Saturation map — what's covered, what's weak

Updated from M1's Section G:

### Saturated (well-covered, low marginal value of additional chunks)

- **block-authoring**: 7-layer continuity
  stack closed (8.M1's observation) + Phase
  8.42 + 8.43 added operator-variant
  mechanisms. Extension space mostly named.
- **plugin-dev**: federation substrate
  (hooks) + temporal extension (cron) +
  routing (rewrite rules) + earlier security
  trio. Phase-7-era chunks still v1-framed but
  topic-covered.
- **interactivity**: trio complete
  (activation / event-execution /
  reactive-callbacks). Beyond this is
  internals territory.
- **admin-ui**: 5 chunks across
  registration / display / dismissal /
  modular composition. Coverage is
  comprehensive for typical operator-facing
  governance.
- **site-building**: 7 chunks span resolution
  (template / locate / nav / list / pattern /
  query) + composition-runtime grammar.
- **style-engine**: governance + embodiment
  pair (8.35 / 8.35a) on top of Phase 7
  4-chunk substrate.

### Lightly covered or weak

- **theme-config user-persistence**: the
  Site Editor's Global Styles UI saves to
  `wp_global_styles` post type; this
  mechanism is partly covered in 8.35 (source
  layering) but not as standalone chunk.
- **editor preferences store**
  (`@wordpress/preferences`): editor-runtime
  preference state distinct from admin-side
  screen options. Adjacent to 8.34a but
  separate mechanism.
- **template tags / render context**
  (`the_content` filter chain, `the_title`,
  `get_post_meta` with filters): the broad
  filter ecosystem around template-time data
  reads. Partial coverage via existing v1-era
  chunks; v2-native deployment would cover
  the *filter-chain composition* aspect.
- **multisite / network admin governance**:
  unaddressed entirely. Adjacent terrain to
  admin-ui but with its own structural
  shapes.
- **REST API authentication / cookie nonce
  handling deep mechanics**: existing
  `register-rest-route` and `nonces` are
  Phase 7-era; modern WP authentication
  details (application passwords, etc.)
  aren't covered.

### v1-era fossils (acknowledged as is)

The redundancy hits during forward authoring
(nonces, settings-api, deprecation,
template-hierarchy, slotfills) revealed that
multiple Phase 7-era chunks cover topics
that would be natural Phase 8.27+ choices.
M1 noted this; M2 confirms it as a stable
pattern. These chunks remain authoritative
for their topics; their constitutional-case-
study framing is honest history.

## G. What this note does not claim

To preserve anti-triumphalism discipline:

- It does **not** claim 30 chunks demonstrate
  general-purpose maturity or completeness.
  Coverage is broader than M1's 23 but still
  bounded.
- It does **not** elevate any toolkit or
  refinement to constitutional rule. The
  Federation variant matrix, the Law 4 fit
  criterion, and the analytical-unit
  observations all live at prose-level craft.
  `structural-patterns.md` is not amended.
- It does **not** treat the v1-era fossils as
  errors or technical debt requiring rewrite.
  The Phase 8.27+ doctrine and the Phase 7-era
  chunks coexist — the former for new
  authoring, the latter as historical record.
- It does **not** predict that the
  saturation map captures all uncovered
  ground. It names what the audit *can see*
  as weak; other gaps may exist that haven't
  surfaced.
- It does **not** advise specific next
  chunks. Section F's "lightly covered or
  weak" list provides material for strategic
  choice, not prescription.

The 8-chunk sample since M1 is small. The
grammar refinements emerged within that
sample; their generality is a question for
further deployment, not a claim this audit
makes.

## H. One-line thesis

> *The KB's maturity is no longer primarily
> pattern recognition, but pattern-boundary
> grammar: not merely identifying mechanisms,
> but specifying where fit attaches, where it
> breaks, and how adjacent mechanisms
> diverge.*

Phase 8.M1's audit observation was *false-
structural-analogy resistance* — the KB had
become good at refusing wrong-fit
classifications. M2's observation extends
that one step further: the KB has begun to
**specify the grammar of fit itself**. What
makes something Law 4? What makes something
federation? What unit does fit attach to?

These are not constitutional questions; the
constitutional patterns haven't changed. They
are questions about *how the framework is
read* — interpretive precision, not framework
expansion.

## I. Forward implication

Three observations carry forward:

- **Continued forward authoring** remains the
  default; further synthesis without further
  deployment risks the audit-becoming-
  curation pattern.
- **Section F's weak zones** are now legible.
  Section F's most strategic candidate, given
  that the post-audit window is a stress-test
  opportunity, is
  **theme-config/global-styles-user-persistence**
  — it bridges admin-ui + style-engine +
  persistence and would test whether the
  grammar refinements survive cross-context
  complexity.
- **The grammar refinements** (Federation
  variant matrix, Law 4 fit criterion,
  analytical-unit observations) are
  available as prose-level reading lenses for
  future chunks. They don't change the
  constitution; they change *how new chunks
  are written*.

The audit does not prescribe the next chunk.
It records what has accumulated as
interpretive infrastructure that the next
chunk will inherit whether it knows or not.

## REFERENCES

The 8 chunks audited (since 8.M1):

- `block.server-side-render-component` (8.38)
- `interactivity.data-wp-watch-and-init`
  (8.38a)
- `admin-ui.dashboard-widgets` (8.39)
- `plugin-dev.rewrite-rules-and-pattern-resolution`
  (8.40)
- `site-building.query-vars-and-main-query-resolution`
  (8.41)
- `block.block-styles-registration` (8.42)
- `editor-customization.format-types-registration`
  (8.43)

(8.38a counts as the second of an
interactivity pair; 8.42 and 8.43 are
adjacent operator-variant chunks. The chunk
count of "8" treats 8.M1 as an audit boundary
rather than a chunk.)

Adjacent meta-notes:

- `_meta.kb-phase8-27-37a-structural-audit`
  (Phase 8.M1) — the prior audit. M2 builds
  on its toolkit catalog and audits the
  *grammar* of those toolkits.
- `_meta.kb-phase8-27-site-building-forward-composure`
  — the very first micro-synthesis, four
  chunks into the Phase 8.27 doctrine. M2's
  audit-of-audit shape parallels that
  early bounded observation.
- `_meta.structural-patterns` — the
  constitutional document. M2 observes how
  the constitutional vocabulary's
  *application grammar* has refined; it
  does not amend the constitutional patterns
  themselves.

This note does **not** replace, supersede,
or amend any of the above. It is a structural
observation at the audit-level, parallel to
M1's chunk-catalog audit but focused on
grammar-of-fit instead of pattern-recognition.
