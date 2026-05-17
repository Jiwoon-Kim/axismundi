---
doc_id: meta.phase8-p1-frontier-mapping
type: forward-strategic-map
scope: phase-9-problem-space-definition
status: bounded-decision-aid
domain: kb-methodology
not_constitutional: true
not_section_x: true
no_structural_patch: true
not_audit: true
captured: 2026-05-10
related:
  - _meta.kb-phase8-27-37a-structural-audit
  - _meta.kb-phase8-38-43-grammar-audit
  - _meta.kb-phase8-44-48-topology-audit
  - _meta.structural-patterns
---

# Phase 8.P1 — Frontier Mapping

A forward-strategic document, not an audit.
After three retrospective audits (M1
breadth, M2 grammar, M3 governance
geometry) and 35 forward chunks, the KB's
forward-authoring momentum has reached a
*natural decision point*. Continuing to
add chunks within already-saturated
terrains produces diminishing conceptual
returns; choosing a *new frontier* is now
the bottleneck.

This document does not pick the next
chunk. It maps the candidate frontiers,
analyzes their structural shapes, and
surfaces decision criteria. The
recommendation is for the user to make a
*deliberate strategic choice* about Phase
9's problem space rather than continue
forward authoring on momentum.

**This is not a constitutional document,
not an audit, and not a chunk.** It is a
strategic decision aid for choosing the
next frontier.

## A. Why frontier mapping now

The audit trio (M1 / M2 / M3) covered the
past:

- **M1**: how broad? (terrain transfer,
  toolkit catalog).
- **M2**: how precise? (grammar
  refinements, fit criteria).
- **M3**: at what structural scale?
  (governance geometry, jurisdictional
  dimensions).

After M3, three audit-shapes have
converged. Doctrine 6 reached grammar
parity with Law 4. Federation matured to
a 5-variant family. Doctrine 5 surfaced
its bifurcated form. The KB now reads
governance as geometry, not just gate
logic.

But: the past being well-audited does
not tell us what to write next. The
forward-authoring discipline (Phase 8.27
doctrine: *"Reference when clarifying.
Omit when unnecessary. Deploy
naturally."*) needs a *substrate to
deploy on*. When the most-saturated
terrains exhaust their natural next
chunks, deploy-where-natural becomes
"deploy-where?".

**Frontier mapping answers that
'where?'** — not by deciding, but by
making the candidate frontiers legible
enough to choose among.

## B. Current saturation state

Approximate coverage as of Phase 8.48:

### Heavily saturated terrains

- **block-authoring** — registration,
  block.json fields, supports, edit/save
  contracts, validation, deprecation,
  apiVersion, dynamic rendering, server-
  side render, block styles, variations,
  transforms, inner blocks, wrapper
  attributes, attribute schemas. ~25+
  chunks across Phase 7 + Phase 8
  contributions.
- **site-building** — template hierarchy,
  resolution, locate_template,
  get_template_part, navigation fallback,
  block patterns, query vars + main query
  resolution, page topology, content
  render context. ~9 chunks.
- **plugin-dev** — registration calls,
  hooks lifecycle, cron, REST routes,
  rewrite rules, capabilities, security
  boundaries, post types, taxonomies.
  ~10 chunks.
- **theme-config** — settings schemas
  (color/typography/layout/spacing),
  styles realization, patterns, templates,
  source layering, user persistence.
  ~15+ chunks.
- **style-engine** — selectors, CSS
  emission, preset materialization,
  cascade aggregation, source layering,
  per-block attribution. ~6 chunks.
- **interactivity** — directive protocol,
  runtime state, hydration, view-script
  activation, watch/init, event actions.
  ~6 chunks.
- **admin-ui** — settings API, admin
  menus, notices, list tables, screen
  options, dashboard widgets. ~6 chunks.
- **editor-customization** — block
  filters, slotfills, editor hooks,
  inspector controls, block controls,
  format types, editor preferences.
  ~7 chunks.
- **data-layer** — entity resolution,
  persistence, registry, resolver
  lifecycle. ~4 chunks.
- **i18n** — gettext functions, script
  translations, locale switching, JIT
  loading, contexts and plurals. ~5
  chunks.
- **build-tooling** — wp-scripts,
  block.json build pipeline. ~2 chunks.
- **rest-api** — authentication +
  permission callbacks. ~1 chunk.
- **multisite** — network and site
  governance. ~1 chunk.
- **template-tags** — render context. ~1
  chunk.

### Sparse but covered

`rest-api`, `multisite`, `template-tags`,
`build-tooling` are recently established
new top-level folders with 1–2 chunks
each. There's room to expand them, but
their *strategic question* has been
articulated (REST auth = Doctrine 6
positive anchor; multisite = jurisdictional
dimension; build-tooling = compiler/runtime
split anchor; template-tags = content
render pipeline).

### Genuinely uncovered (within current scope)

Looking at WordPress's surface area, what
remains genuinely uncovered *within the
single-installation* scope:

- Customizer API (legacy theme
  customization; declining relevance).
- Heartbeat API (background polling
  mechanism).
- Embeds / oEmbed handling.
- Site Health API (admin diagnostics).
- Application metadata / plugin update
  handshake.
- Specific block-editor packages not yet
  decomposed (e.g., `@wordpress/blocks`
  internals beyond what's been covered).
- Theme.json schema details for less-
  common fields.

These are real gaps but mostly **incremental
coverage**, not new doctrinal terrain.

### Current doctrine grammar status

| Doctrine | Anti inventory | Positive anchors | Fit criterion | Dimensions |
| -------- | -------------- | ---------------- | ------------- | ---------- |
| **Law 1** | (broadly applicable; no false-positive inventory needed) | Pervasive | (implicit) | (multiple application forms) |
| **Law 4** | 10 members | 5 anchors | ordered + terminal + discarding (8.40) | — |
| **Law 3b** | 5 members | (handful: hydration, etc.) | (none yet specified) | — |
| **Doctrine 5** | (limited false-positive cases) | 5 variant family | (implicit) | bifurcated continuity (8.48) |
| **Doctrine 6** | 7 members | 3 anchors | mediates + decides + terminates + binds (8.47) | jurisdictional (8.48) |
| **Federation** | (variant-shaped, not anti) | 5 variants | (variant matrix) | scale-sensitive |
| **Section X** | (resisted in every chunk from 8.27c) | 0 | (refused as ontological inflation) | — |

The pattern: **doctrines that started as
underspecified are now grammar-mature**
(Law 4, Doctrine 6, Federation). Doctrines
that haven't yet faced enough
false-positive pressure remain at recognition
maturity (Doctrine 5, Law 3b). Section X
has been consistently refused.

## C. Three frontier candidates

The user's strategic verdict identified
three candidate paths. This section makes
each candidate legible.

### Path 1 — Cross-installation / ecosystem scale

**What it is:**

Expand from "one WordPress install" (with
its multisite extension at 8.48) to
*ecosystem*: how WordPress installs
participate in larger systems. Examples:

- WordPress.org update channels (how
  installs receive updates).
- Plugin repository governance (who can
  publish, what gets reviewed, trust
  signals).
- Auto-update trust chain (signature
  verification, gradual rollout).
- WordPress.com vs self-hosted divergence
  (managed-host policies overriding
  install-level governance).
- VIP-style enterprise environments
  (institutional policies above
  network admin).

**Doctrinal pressure it would create:**

- **Doctrine 6** at *ecosystem scale*:
  who governs *across installations*?
- **Federation** beyond intra-install:
  installs federating around shared
  package distribution.
- **Doctrine 5** at *update boundaries*:
  what persists when an install updates?
- New analytical unit: *cross-installation
  asymmetry* (extending the 5-level
  granularity ladder M3 documented).

**Strategic fit:**

- Continues the governance-geometry arc
  cleanly.
- New scale = new doctrinal pressure;
  past patterns may need extension.
- Risk: ecosystem-scale concerns can
  drift into operations / business policy
  rather than mechanism — careful framing
  needed.
- Risk: documentation of ecosystem
  governance is sparser than
  installation-level mechanisms.

### Path 2 — External federation scale

**What it is:**

Expand to mechanisms where WordPress
participates in *non-WordPress-native*
federations. Primary example:

- **ActivityPub plugin** (and its broader
  federation model — Mastodon-compatible,
  cross-server identity, actor authority).
- Identity portability across systems
  (importing/exporting users, content,
  authority).
- WebMention / IndieWeb mechanisms.
- Custom federation through WordPress
  REST endpoints (server-to-server
  integration).
- Headless WordPress → external front-end
  governance handoff.

**Doctrinal pressure it would create:**

- **Federation** beyond intra-WordPress:
  *inter-system* federation. The 5-variant
  family may need a 6th variant or a
  *cross-system* dimension.
- **Doctrine 6** at *cross-jurisdictional
  boundaries*: WordPress site authority
  vs ActivityPub actor authority — same
  identity, two governance systems.
- **Law 3b** genuine fit potentially:
  ActivityPub's actor signing carries
  authority across runtime boundaries
  more meaningfully than the false-bridge
  patterns the KB has been refusing.
- New analytical unit: *trans-system
  asymmetry* (extending again).

**Strategic fit:**

- Highest doctrinal yield potential.
- Genuinely new constitutional terrain
  (the KB hasn't tested *inter-system*
  governance).
- Risk: ActivityPub plugin is one of many
  federation implementations; chunk
  authority is plugin-specific not
  WordPress-canonical.
- Risk: the terrain is rapidly evolving;
  chunks may age faster.

### Path 3 — Retro-synthesis / canonical cartography

**What it is:**

Step back from forward authoring entirely.
Synthesize the existing 35 chunks + 3
audits into a *navigable constitutional
atlas*. Examples:

- A doctrine-by-terrain matrix (which
  doctrines apply where, with what fit
  profile).
- A scale-by-mechanism matrix (Section
  C governance ladder applied to all
  chunks).
- A cross-reference graph (which chunks
  cite which others, where the family
  resemblances cluster).
- A "v2 constitution as taught"
  walkthrough (the constitutional patterns
  + how they've been applied across
  chunks, as pedagogy rather than
  reference).

**Doctrinal pressure it would create:**

- Probably none new. This path is
  *consolidation*, not *expansion*.
- Possibly: cross-chunk patterns invisible
  to individual chunks become visible
  (similar to how M3 surfaced governance
  geometry).

**Strategic fit:**

- Lowest novelty; highest navigability
  payoff.
- Risk: easily becomes "victory lap" —
  the very anti-pattern Phase 8 has
  consistently warned against.
- Risk: cartography ages with the
  underlying chunks; rebuilding required
  if forward authoring continues.
- Benefit: makes the KB *usable* as a
  reference resource by future authors
  (or future selves) who need to find
  things.

## D. Decision criteria

Three criteria the user might weigh in
choosing among the paths:

### Criterion 1 — Doctrinal yield

How much *new doctrinal grammar* is each
path likely to surface?

- **Path 1 (ecosystem)**: medium-high.
  New scale, similar doctrinal shapes
  with new dimensions.
- **Path 2 (external federation)**:
  high. Genuinely new terrain;
  potentially the first true Law 3b
  positive fit.
- **Path 3 (cartography)**: low. Mostly
  consolidation; new patterns rare.

### Criterion 2 — Practical usability

How much does each path *make the KB
more useful for actual work*?

- **Path 1**: medium. Ecosystem-scale
  governance affects deployment decisions;
  most authors won't encounter it
  daily.
- **Path 2**: medium. Federation is
  niche but growing; ActivityPub-aware
  authors increasing.
- **Path 3**: high. Existing 35 chunks
  become substantially more navigable;
  improves use of *current* knowledge
  rather than adding more.

### Criterion 3 — Risk profile

How likely is each path to drift into
patterns the discipline has resisted?

- **Path 1**: low-medium. Ecosystem
  governance is genuinely new terrain;
  drift risk is "this becomes business
  policy not mechanism."
- **Path 2**: medium. External federation
  has rapidly-evolving substrates;
  documentation may date quickly. But
  doctrinally rich.
- **Path 3**: high. Cartography is *one
  step* from victory-lap consolidation;
  the audit trio (M1/M2/M3) already
  records what's been done; adding more
  retrospective documentation risks the
  *narrating-maturity-more-than-exercising-
  it* pattern.

### Combined assessment

| Path | Doctrinal yield | Usability | Risk profile |
| ---- | --------------- | --------- | ------------ |
| 1 — Ecosystem  | Medium-high  | Medium  | Low-medium |
| 2 — External federation | High | Medium | Medium |
| 3 — Cartography | Low | High | High (drift) |

No path dominates on all criteria. The
choice depends on which criterion
matters most to current strategic intent.

## E. Hybrid possibilities

The three paths aren't mutually
exclusive. Two practical hybrids:

### Hybrid A — Path 2 first, then small Path 3

Start with external federation
(highest doctrinal yield); after a few
chunks, do a small focused cartography
chunk (e.g., the doctrine-by-terrain
matrix only) without expanding into a
full atlas.

Captures the doctrinal yield while
deferring the risk of full
cartography drift.

### Hybrid B — Path 1 + Path 2 deferred

Stay within the WordPress universe
(Path 1 ecosystem) before crossing to
non-WordPress federations (Path 2).
Keeps doctrinal pressure on the
existing constitution; defers the
"true Law 3b test" until the
ecosystem path has been explored.

Lower risk; may miss the most
productive doctrinal frontier.

## F. What this document does not claim

To preserve discipline:

- It does **not** prescribe a path. The
  three candidates and the criteria are
  decision aids; the choice belongs to
  the user.
- It does **not** claim the three paths
  are exhaustive. Other frontiers exist
  (e.g., comparative WordPress vs
  Drupal/Ghost/Static Site Generators
  for cross-platform doctrine
  validation; or research-style work
  comparing WordPress's patterns to
  general PHP framework patterns).
  These were not surfaced because the
  user explicitly named the three.
- It does **not** assess the strategic
  weight of each criterion. Whether
  doctrinal yield > usability >
  risk-aversion is the right ordering
  is itself a strategic question, not a
  technical one.
- It does **not** treat M3's audit
  outputs as deliverables. The audits
  observed; this document chooses
  *what to observe next*.
- It does **not** require an immediate
  decision. Pausing here without
  choosing is also a valid response —
  reflective time before commitment.

The document's role: make the
candidates legible. The user's role:
choose deliberately.

## G. One-line forward question

> *Given the constitutional patterns
> have grammar-matured across
> single-installation governance
> geometry, where does the next
> genuinely-new scale of mechanism
> begin? Cross-installation? Cross-
> system? Or — first — does the
> existing knowledge need cartographic
> consolidation before further
> expansion?*

This is the question the user can
answer. The document offers no answer
beyond making the question precise.

## H. Practical immediate options

Whatever path is chosen (or even if no
path is chosen yet), four practical
immediate options:

1. **Choose a path; begin its first
   chunk.** Active forward motion
   resumes; the chosen frontier
   accumulates evidence.
2. **Choose a hybrid; begin Path A's
   first chunk.** Same as 1, with
   later transition planned.
3. **Pause without choosing.** Reflect
   on which criterion matters most;
   return when intentional choice is
   ready.
4. **Continue saturating an existing
   terrain** (e.g., one of the sparse
   terrains: rest-api, multisite,
   template-tags). Lower-doctrinal-
   yield but maintains forward motion
   without committing to a frontier.

Option 4 is *possible* but worth
naming: it would be *expansion within
the current scale*, not frontier
choice. It defers the strategic
question rather than answering it.

## REFERENCES

The 35 forward chunks, 3 audits, and
adjacent meta-notes referenced
throughout this document live at:

- `_meta.kb-phase8-27-37a-structural-audit`
  (M1)
- `_meta.kb-phase8-38-43-grammar-audit`
  (M2)
- `_meta.kb-phase8-44-48-topology-audit`
  (M3)
- `_meta.kb-phase8-27-site-building-forward-composure`
  (the very first micro-synthesis)
- `_meta.structural-patterns` (the
  constitutional document)
- All chunks in
  `knowledge/wordpress/...` (catalogued
  in M1 and M2; counts updated in M3)

This document does **not** replace,
supersede, or amend any of the above.
It is a *forward strategic map*,
parallel to the M1/M2/M3 audit trio in
its anti-triumphalism discipline but
opposite in temporal direction:
**audits look back at what was;
frontier mapping looks forward at what
could be — without prescribing which
of the could-bes is best**.

The audit trio plus this frontier map
together close the Phase 8 series with
both *retrospective accounting* (what
the KB has become) and *prospective
options* (what it might next become).
The transition to Phase 9 is the
user's deliberate choice, made with
both lenses in hand.
