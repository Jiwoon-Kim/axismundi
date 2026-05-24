# v3.6.23 Core Block Catalog 6-Category Split - Phase 0 Plan

## User Request

User trigger:

```txt
v3.6.23 Phase 0 GO - Core Block Catalog 6-category split
```

User supplied WordPress source links for the cycle:

```txt
https://make.wordpress.org/core/2026/05/05/proposal-auto-generate-block-editor-handbook-docs-from-block-json/
https://make.wordpress.org/core/2026/04/22/roster-of-design-tools-per-block-wordpress-7-0/
https://github.com/WordPress/gutenberg/tree/trunk/packages/block-library
https://wordpress.org/documentation/article/blocks-list/
https://make.wordpress.org/core/2026/03/16/pattern-overrides-in-wp-7-0-support-for-custom-blocks/
https://make.wordpress.org/core/2025/11/12/block-bindings-improvements-in-wordpress-6-9/
```

User framing:

```txt
v3.6.23 should start the Core Block Catalog 6-category split.
This is part of the direct route back toward:
  G1 - implementing the style guide into the Pilot / future theme path
  G2 - preparing eventual release / wp.org submission
```

## Current Repo State

```txt
HEAD:   9add6e7 Close v3.6.22 explicit data-theme auto state
Branch: main
Remote: origin/main synchronized at Phase 0 start
Working tree before Phase 0: clean
```

Recent lineage:

```txt
9add6e7 Close v3.6.22 explicit data-theme auto state
de106ab Update handoff docs to v3.6.21
0b629d9 Close v3.6.21 theme switcher contract
aefb384 Close v3.6.20 pilot vs distributable bootstrap
663b62c Close v3.6.19 asset surface audit + index
b4ab619 Close v3.6.18 core block mapping audit
```

## Cycle Name

```txt
v3.6.23 Core Block Catalog 6-Category Split
```

Primary Phase 0 artifact:

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-0-PLAN.md
```

Expected later artifacts:

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-1-REPORT.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-3-VERIFICATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-5-CLOSE.md
```

The Phase 2 artifact may be a decision report instead of implementation if
Phase 1 finds scope, source, or generated-mirror risk.

## Lineage

v3.6.23 directly executes the v3.6.18 Layer 3 routed-forward decision.

v3.6.18 Phase 2 Layer 3 decision:

```txt
style-guide-blocks.html should later split the WordPress Block Catalog by the
official Block Inserter categories, minus Embeds for now.
```

v3.6.18 Phase 5 close:

```txt
style-guide-blocks.html should become a category-aware lab catalog in a
follow-on cycle, not inside the audit.
```

v3.6.23 is that follow-on execution candidate.

It is not a new exploratory architecture cycle. It is a plan-first execution
cycle for the lab catalog route already recorded in v3.6.18.

## Strategic Position

v3.6.23 is the first step in the recommended Option C route:

```txt
v3.6.23 - Core Block Catalog 6-category split
v3.6.24 - Distributable skeleton bootstrap
v3.6.25 - Templates / pages / patterns
then release-seal derivatives and wp.org readiness
```

Purpose:

```txt
Convert mapping evidence into a usable catalog surface.
Do not add a new philosophy layer.
Do not start distributable theme work yet.
```

Goal alignment:

```txt
G1 - Style guide -> Pilot/theme implementation:
  direct progress, because the block catalog becomes category-aware and
  ready to feed template / pattern choices.

G2 - Theme release / wp.org submission:
  indirect prerequisite, because page templates and release assets need a
  clear block catalog input.
```

## Source Inputs

### Internal Cycle Sources

```txt
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-2-DECISION.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-5-CLOSE.md

docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md

docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-5-CLOSE.md

docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/ASSET-SURFACE-INDEX.md
BACKLOG.md
```

### Local Implementation / Presentation Sources

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
styleguide/blocks.html
styleguide/prose.html

products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-lab/scripts/theme.js
styleguide/scripts/style-guide.js
styleguide/scripts/theme.js
```

Phase 1 should also check whether publish tooling declares `styleguide/blocks.html`
and `styleguide/prose.html` as generated mirrors.

### D-Layer / Binding Sources

Read-only:

```txt
bindings/wordpress-material3/binding_map.json
bindings/wordpress-material3/block_component_rules.json
bindings/wordpress-material3/taxonomy.md
bindings/wordpress-material3/gap_report.md
bindings/wordpress-material3/binding_summary.md
bindings/wordpress-material3/confidence_matrix.json
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
```

No D-layer edit is authorized by this Phase 0 plan.

### Memory Guardrails

Codex promoted memory at Phase 0 start:

```txt
project-axismundi-theme-switcher-separation
project-axismundi-theme-switcher-selector-ownership
project-axismundi-multi-surface-implementation-prerequisites
project-axismundi-source-of-authority-inventory
```

Reviewer-side additional guardrails also remain active:

```txt
feedback-scope-discipline
project-axismundi-phase-workflow
project-axismundi-tracked-copies
project-axismundi-role-division
feedback-mount-staleness
project-axismundi-release-seal
project-axismundi-written-material-ontology
project-axismundi-pilot-not-distributable
project-axismundi-distributable-skeleton-prerequisites
```

### External WordPress Sources

1. WordPress.org Blocks List:

```txt
https://wordpress.org/documentation/article/blocks-list/
```

Role:

```txt
WP 6-category taxonomy authority:
Text / Media / Design / Widgets / Theme / Embeds.
```

2. Gutenberg block-library trunk:

```txt
https://github.com/WordPress/gutenberg/tree/trunk/packages/block-library
```

Phase 0 snapshot:

```txt
trunk commit: f0a5c0cd5fa957170608692721b18da336e55328
captured: 2026-05-24
```

Role:

```txt
block.json source-of-truth candidate for current / emerging block metadata.
```

Because `trunk` is a moving target, Phase 1 evidence must be frozen to this
commit or to a newly recorded Phase 1 snapshot if it refreshes the hash.

3. Auto-generated block docs proposal:

```txt
https://make.wordpress.org/core/2026/05/05/proposal-auto-generate-block-editor-handbook-docs-from-block-json/
```

Role:

```txt
methodology reference for treating block.json as a per-block documentation
source.
```

This is a proposal / process discussion, not an Axismundi implementation
authority.

4. Design tools roster for WordPress 7.0:

```txt
https://make.wordpress.org/core/2026/04/22/roster-of-design-tools-per-block-wordpress-7-0/
```

Role:

```txt
block-support / design-tools matrix authority.
```

Phase 1 should use it to classify support surfaces such as typography, color,
dimensions, border, layout, gradient, duotone, shadow, and related design tools.

5. Pattern Overrides in WordPress 7.0:

```txt
https://make.wordpress.org/core/2026/03/16/pattern-overrides-in-wp-7-0-support-for-custom-blocks/
```

Role:

```txt
custom block / pattern override awareness reference.
```

Scope:

```txt
reference only for v3.6.23.
No pattern override implementation.
No custom block support implementation.
Route binding / pattern override runtime work to BACKLOG #21 or future plugin
territory.
```

6. Block Bindings improvements in WordPress 6.9:

```txt
https://make.wordpress.org/core/2025/11/12/block-bindings-improvements-in-wordpress-6-9/
```

Role:

```txt
bindings / pattern-overrides routing context.
```

Scope:

```txt
reference only for v3.6.23.
No block_bindings_supported_attributes PHP filter implementation.
No editor binding UI implementation.
Route runtime / editor / plugin behavior to BACKLOG #21.
```

## Authority Model

v3.6.23 uses layered authority:

```txt
WordPress taxonomy authority:
  WordPress.org Blocks List

Current / emerging block metadata:
  Gutenberg block-library block.json snapshot

Design support authority:
  WordPress 7.0 design tools roster

Axismundi block mapping authority:
  v3.6.18 Layer 1 / Layer 2 mapping audit
  v3.6.2 / v3.6.3 / v3.6.7 close evidence

Axismundi presentation authority:
  style-guide-blocks.html current lab catalog
  style-guide-prose.html prose route
  generated styleguide/ mirrors, if active

Theme switcher shell authority:
  v3.6.21 selector ownership
  v3.6.22 explicit root-state close
```

No single source overrides all others. Phase 1 must identify disagreements and
route them instead of flattening them.

## In Scope

Phase 1 diagnostic:

1. Search prior cycle docs for source-of-authority / authoritative /
   byte-identical / lockstep / source-copy contracts before any catalog
   implementation route is selected.
2. Verify `styleguide/blocks.html` and `styleguide/prose.html` generated mirror
   ownership, including publish / mirror generator scripts under `tools/`.
3. Inventory the current `style-guide-blocks.html` structure and section list.
4. Confirm `style-guide-prose.html` / `styleguide/prose.html` remain the prose
   route for Markdown and Custom HTML.
5. Build a WordPress 6-category catalog target:
   - Text
   - Media
   - Design
   - Widgets
   - Theme
   - Embeds excluded
6. Cross-check v3.6.2 Tier 1 entries against current WP categories.
7. Identify WP 7.0 rename / new block deltas:
   - Verse / Poetry naming;
   - Accordion;
   - Icon;
   - Math;
   - Breadcrumbs;
   - other blocks discovered from the snapshot.
8. Classify WP 7.0 new blocks as reference-only unless Phase 1 proves they are
   already represented by local Axismundi surfaces.
9. Record design-tool support evidence per category where useful.
10. Check whether Media category implementation is blocked by source assets.
11. Check whether a theme switcher shell should be added to `style-guide-blocks.html`.
12. If a switcher is added, inherit v3.6.21:
    - `.sg-theme`;
    - `data-theme-button`;
    - `style-guide.js`;
    - `axismundi.theme` catalog-local key.
13. Decide generated mirror handling route:
    - source only;
    - source + mirror direct edit;
    - publish tooling;
    - no mirror edit.

Potential Phase 2 implementation, only after Opus Phase 1 verdict and user GO:

1. Restructure `style-guide-blocks.html` into WP category sections.
2. Optionally update generated `styleguide/blocks.html` if Phase 1 selects
   a mirror route that requires it.
3. Optionally add `.sg-theme` catalog switcher shell if Phase 1 selects it.
4. Keep `style-guide-prose.html` separate, with at most cross-link / route note
   changes if Phase 1 proves they are needed.

## Out Of Scope / Non-Goals

v3.6.23 does not authorize:

```txt
No Pilot template / page / pattern work.
No distributable skeleton creation.
No release-seal derivative generation.
No wp.org submission prep.
No theme.json edits.
No functions.php edits.
No D-layer binding file edits.
No BACKLOG #21 implementation.
No block bindings PHP filter implementation.
No Pattern Overrides implementation.
No custom block support expansion.
No Embeds implementation.
No third-party provider / iframe / privacy policy implementation.
No Media source fetch unless user explicitly supplies / authorizes assets.
No new WP 7.0 block implementation merely because a block exists upstream.
No v3.6.2 Tier 1 reopen.
No v3.6.3 semantic route reopen.
No #44 specimen coverage implementation.
No #46 / #47 provider hygiene.
No switcher selector contract rewrite.
No visitor/editor storage sync.
No root meta-doc maintenance unless user separately requests it.
```

## Embeds Policy

Embeds stay excluded.

Carry-forward reasons from v3.6.18:

```txt
third-party network calls
iframe sandbox / referrer / clipboard policy
privacy / consent flow
oEmbed provider whitelist / cache policy
provider-specific responsive aspect ratios
```

v3.6.23 may list Embeds as a category from WordPress taxonomy but must mark it:

```txt
Excluded / not implemented / requires explicit source-privacy plan.
```

## Media Policy

Media category is in scope as catalog taxonomy, but implementation depth depends
on source readiness.

Current available assets may be referenced as placeholder inventory:

```txt
assets/media/image/
assets/media/audio/
assets/media/video/
```

Phase 1 must decide:

```txt
Can Media category be represented with current project-owned / licensed
placeholder assets?

Or should Media category be scaffolded with "source pending" notes only?
```

No external media download is authorized by this Phase 0 plan.

## WP 7.0 / WP 6.9 Feature Policy

v3.6.23 may record current WordPress feature context:

```txt
design tool roster per block
block.json generated docs proposal
pattern overrides for custom blocks
block bindings UI / server filters
new / renamed block names
```

But these are reference inputs, not implementation authorization.

Routing:

```txt
New block names:
  catalog reference / future route

Design support matrix:
  catalog support column / audit evidence

Pattern Overrides:
  BACKLOG #21 / future plugin territory

Block Bindings filters:
  BACKLOG #21 / future plugin territory

Custom blocks:
  future custom-block / plugin cycle
```

## Tracked Copy / Mirror Policy

Potential tracked surfaces:

```txt
source: products/reference-implementations/axismundi-lab/style-guide-blocks.html
mirror: styleguide/blocks.html

source: products/reference-implementations/axismundi-lab/style-guide-prose.html
mirror: styleguide/prose.html
```

Phase 1 must verify whether these are generated mirrors, hand-maintained copies,
or one-time publish artifacts.

Mirror route options:

```txt
M1 source-only:
  edit lab source, defer mirror regeneration.

M2 source + mirror direct edit:
  edit both in one reviewed pass.

M3 publish tooling:
  edit source, run publish tooling, accept generated output only if clean.

M4 mirror non-authoritative:
  leave mirror unchanged if Phase 1 proves it is not an active target.
```

Use:

```txt
project-axismundi-multi-surface-implementation-prerequisites
project-axismundi-source-of-authority-inventory
project-axismundi-tracked-copies
```

before selecting a Phase 2 implementation route.

## Theme Switcher Contract

If v3.6.23 adds a switcher to `style-guide-blocks.html`, it must inherit the
v3.6.21 lab/styleguide contract:

```txt
selector:   .sg-theme
attribute:  data-theme-button
runtime:    style-guide.js
storage:    axismundi.theme
```

Do not use:

```txt
.ax-theme-switcher
data-theme-set
axismundi-pilot-theme
```

for a lab catalog surface unless a new contract review explicitly reopens the
selector / attribute / storage boundary.

## Diagnostic Questions

Q1. What is the current `style-guide-blocks.html` information architecture?

```txt
Which sections exist?
Which sections are WP block categories?
Which are local helper / layout / specimen categories?
Which sections are stale relative to v3.6.18?
```

Q2. What is the generated mirror status?

```txt
Is styleguide/blocks.html generated from style-guide-blocks.html?
Is styleguide/prose.html generated from style-guide-prose.html?
Which publish tooling owns them?
Are source/mirror files byte-identical where expected?
```

Q3. What is the target 6-category split?

```txt
Text
Media
Design
Widgets
Theme
Embeds excluded
```

Q4. Which v3.6.2 Tier 1 entries map cleanly into this split?

```txt
Do not reopen Tier 1.
Record only category / presentation placement.
```

Q5. What WP 7.0 deltas matter?

```txt
Verse -> Poetry naming
Accordion / Icon / Math / Breadcrumbs and other new blocks
deprecated or moved blocks
blocks represented only by upstream reference
```

Sub-questions:

```txt
Q5.1 Does the v3.6.2 Tier 1 classification include Verse?

Q5.2 If yes, how should the WP 7.0 Poetry rename be represented?
      - reference-only in the catalog;
      - routed follow-on;
      - no Tier 1 semantic reopen.

Q5.3 If Verse is not in Tier 1, should Poetry appear only in a WP 7.0
      reference appendix?

Q5.4 For Accordion / Icon / Math / Breadcrumbs and other new blocks:
      - classify as v3.6.2 Tier 1 absent;
      - keep reference-only unless local Axismundi evidence already exists;
      - do not implement just because upstream contains the block.
```

Q6. How should design-tool support be displayed?

```txt
category-level note
per-block support table
appendix only
omitted from implementation but recorded in Phase 1
```

Q7. How should Media be handled?

```txt
full examples with current assets
placeholder notes only
defer implementation
```

Q8. How should Widgets and Theme blocks be handled?

```txt
Search direct evidence
Custom HTML -> prose route
Navigation / Query / Post template -> FSE/template follow-on
```

Q9. Should `style-guide-blocks.html` gain the lab catalog switcher shell?

```txt
If yes, inherit .sg-theme / data-theme-button.
If no, record why shell consistency is deferred.
```

Q10. What Phase 2 route is safest?

```txt
A - no-code decision only
B - source-only lab catalog split
C - source + generated mirror catalog split
D - source + mirror + switcher shell
E - split route: catalog now, media/theme/switcher follow-ons
```

Q11. What closes v3.6.23?

```txt
category-aware catalog implemented
or category-aware catalog decision recorded
or blocked by upstream/source/mirror evidence
```

## Phase 2 Route Decision Tree

Phase 1 should recommend one route:

```txt
if mirror ownership is unclear:
  Route A or B only

elif source + mirror are confirmed active tracked copies:
  Route C or D possible

elif switcher shell is low risk and inherits v3.6.21 contract:
  Route D possible

elif Media assets are not ready:
  Route E, with Media scaffold / source-pending note

elif WP 7.0 deltas are too large:
  Route A, with follow-on implementation plan
```

Preferred direction if evidence is clean:

```txt
Route C or E:
  implement category-aware source + mirror catalog split, preserve prose route,
  exclude Embeds, and route Media depth according to available assets.
```

## Expected Write Scope

Phase 0:

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-0-PLAN.md
```

Possible Phase 2 write scope, route-dependent:

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
products/reference-implementations/axismundi-lab/style-guide-blocks.html
styleguide/blocks.html
possibly products/reference-implementations/axismundi-lab/style-guide-prose.html
possibly styleguide/prose.html
```

Phase 2 should avoid touching `style-guide-prose.html` unless Phase 1 proves a
cross-link or route note is necessary.

Files not expected to change:

```txt
products/reference-implementations/axismundi-pilot/**
products/distributables/**
bindings/wordpress-material3/**
tools/**
theme.json
functions.php
assets/**
```

## Validation Plan

Phase 1:

```txt
git status --short --branch
git diff --check
rg inventory:
  style-guide-blocks.html sections
  styleguide/blocks.html mirror markers
  data-theme-button / data-theme-set
  .sg-theme / .ax-theme-switcher
  source-of-authority / byte-identical / generated mirror
```

Phase 2 / 3 if implementation occurs:

```txt
git diff --check
HTML parse / smoke inspection for edited catalog pages
browser visual smoke for style-guide-blocks.html and styleguide/blocks.html
full validation suite if generated mirror / scripts / CSS / tooling are touched:
  php -l products\reference-implementations\axismundi-pilot\functions.php
  npm test
  python tools\generators\build_pilot_specimen_wall.py
  npm run validate:specimen-wall
  npm run validate:computed
generated artifact restore if needed
```

If Phase 2 is source-only HTML without runtime/CSS/Pilot changes, Phase 3 may
still run the full suite for evidence-shape parity if reviewer requests it.

## Risks

R1 - Scope expansion into WP 7.0 implementation.

Control:

```txt
New / renamed blocks are reference-first. Implementation requires separate
route decision.
```

R2 - Embeds creep.

Control:

```txt
Embeds category remains excluded, with v3.6.18 privacy/source reasons.
```

R3 - Media asset uncertainty.

Control:

```txt
No external asset fetch. Current assets only, or source-pending note.
```

R4 - Prose collapse.

Control:

```txt
Markdown and Custom HTML stay routed to style-guide-prose.html.
```

R5 - D-layer rewrite creep.

Control:

```txt
bindings/wordpress-material3 read-only.
```

R6 - Selector contract drift.

Control:

```txt
Catalog surface uses .sg-theme / data-theme-button if switcher is added.
```

R7 - Generated mirror drift.

Control:

```txt
Phase 1 must choose M1/M2/M3/M4 before Phase 2 implementation.
```

R8 - Upstream trunk moving target.

Control:

```txt
Record Gutenberg trunk commit hash and date.
```

R9 - v3.6.2 Tier 1 reopen.

Control:

```txt
Cross-check category placement only. Do not reopen semantic classification.
```

R10 - Pattern Overrides / Block Bindings runtime creep.

Control:

```txt
Reference only. Runtime/editor hooks route to BACKLOG #21.
```

R11 - Pilot/distributable creep.

Control:

```txt
No Pilot templates, no distributable skeleton, no release-seal derivatives.
```

R12 - Source-of-authority miss.

Control:

```txt
Use M9 source-of-authority inventory check before implementation.
```

## Lock Compliance

Lock 1 - wp-custom downstream-only:

```txt
Preserved. No theme.json / wp-custom source route changes.
```

Lock 2 - md-sys maps to md-ref:

```txt
Preserved. No token values or md-sys / md-ref mappings change.
```

Lock 3 - core/button semantic route:

```txt
Preserved. v3.6.3 core/button route not reopened.
```

Lock 4 - semantic mismatch routing:

```txt
Preserved. Catalog category / ownership decisions are named before edits.
```

Lock 5 - diagnostic-first:

```txt
Preserved. Phase 1 diagnostic must precede any Phase 2 implementation.
```

Expected count if cycle closes:

```txt
no-code decision:
  13th overall / 7th implementation-cycle unchanged

narrow docs hygiene:
  13th overall / 7th implementation-cycle unchanged

narrow implementation:
  13th overall / 8th implementation-cycle
```

Phase 5 must choose the count based on the actual Phase 2 route.

## Phase Cadence

```txt
Phase 0 - this plan
Phase 1 - read-only diagnostic
Phase 2 - decision or implementation after Opus verdict + user GO
Phase 3 - verification
Phase 4 - intentionally unused unless Phase 1/2 finds deeper architecture risk
Phase 5 - close + commit/push after review
```

Phase 1 read-only lock:

```txt
implementation files: 0 edits
generated mirror files: 0 edits
Pilot files: 0 edits
D-layer files: 0 edits
external downloads: 0
```

## Review Request

Opus should answer:

```txt
P1: Is v3.6.23 correctly framed as v3.6.18 Layer 3 follow-on execution?
P2: Are the six WordPress source links correctly scoped as taxonomy /
    snapshot / methodology / support / reference-only inputs?
P3: Are the fences sufficient for Embeds, Media assets, WP 7.0 new blocks,
    Pattern Overrides, Block Bindings, selector inheritance, and generated
    mirror handling?
```

Phase 1 must wait for Opus Phase 0 verdict and explicit user Phase 1 execution
GO.
