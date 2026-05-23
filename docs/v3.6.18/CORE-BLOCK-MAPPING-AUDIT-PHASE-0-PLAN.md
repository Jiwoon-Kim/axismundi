# v3.6.18 - Core Block Mapping Audit - Phase 0 Plan

Date: 2026-05-23
Status: Phase 0 plan
Primary candidate: Core Block Mapping Audit
Priority / route: P1 / GO candidate for Phase 1 diagnostic only

## User Request Log

User routed the next cycle away from Pilot theme revision for now:

```txt
어차피 템플릿이랑 페이지는 들어가기 전에 웹디자인개발 관련 정보 한번
주입하고 시작할거라 일단 코어블록 매핑부터 보는게 낫겠는데
```

Follow-up advisory also identified that these lab presentation aggregators
should be revisited as read-only evidence before any Pilot template / page
revision:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
```

User then clarified the expected WordPress Block Catalog direction:

```txt
style-guide-blocks.html WordPress Block Catalog should split into:
  Text
  Media
  Design
  Widgets
  Theme
  Embeds

Embeds should be excluded for now.
Media blocks will need temporary source assets in the media library; user will
source those before implementation.

style-guide-prose.html is the prose surface for Markdown and Custom HTML block
style application.
```

Operational frame:

```txt
Codex: execution / documentation after gates
Opus: review only; no file edits
Verdict format: P1/P2/P3 + GO/NO-GO/APPROVE WITH NOTES
Lock 5 remains active
Phase 1 diagnostic before any Phase 2 decision / implementation
Local git status is authoritative for mount-staleness cases
New/modified files should remain LF
```

## Current Repo State

Local status at Phase 0 entry:

```txt
HEAD:   8345734 Normalize lab user-select prefixes
Branch: main == origin/main
Push:   no local commits ahead of origin/main
Tree:   clean
```

Lineage note:

```txt
26d2325 Close v3.6.17 WP ripple runtime packaging
8345734 Normalize lab user-select prefixes
```

The `8345734` micro-commit was a mechanical drift cleanup after the v3.6.17
close. It normalized WebKit `user-select` prefix handling in lab Button and
Carousel CSS only. This used the Lock 5 tiny mechanical edit exception and does
not change v3.6.17 close scope.

Current matrix snapshot remains:

```txt
DONE       31
PARTIAL     0
TODO        0
RECORD      3
```

## Cycle Frame

v3.6.18 is a diagnostic-first audit cycle.

The goal is not to redesign Pilot, rewrite the block bridge, or reclassify the
v3.6.2 specimen wall from scratch. The goal is to consolidate accumulated core
block mapping evidence after v3.6.2 through v3.6.17 and produce a current
ownership / status map before template, page, or Pilot theme revision work.

Expected question:

```txt
For each audited core block or block family, what is its current mapping
status and owner?

Possible ownership / route buckets:
  closed / no-action
  theme bridge
  CSS-only presentation bridge
  semantic-decision route already named
  specimen coverage follow-on
  plugin/custom-block or Interpreter Plugin territory
  record-only / not a component surface
  unmapped / needs later decision
```

Phase 1 is the core deliverable. Phase 2 may be a layered decision report if
Phase 1 finds enough evidence to close the audit without implementation.

## Source Inputs

Core block lineage:

```txt
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-5-CLOSE.md
```

D-layer / WordPress binding evidence, read-only:

```txt
bindings/wordpress-material3/binding_map.json
bindings/wordpress-material3/block_component_rules.json
bindings/wordpress-material3/confidence_matrix.json
bindings/wordpress-material3/gap_report.md
bindings/wordpress-material3/taxonomy.md
bindings/wordpress-material3/binding_summary.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
```

External WordPress taxonomy reference:

```txt
https://wordpress.org/documentation/customization/
https://wordpress.org/documentation/article/blocks-list/
https://wordpress.org/documentation/category/design-blocks/
https://wordpress.org/documentation/category/media-blocks/
https://wordpress.org/documentation/category/text-blocks/
https://wordpress.org/documentation/category/theme-blocks/
https://wordpress.org/documentation/category/widget-blocks/
```

Current WordPress docs identify six Block Inserter categories:

```txt
Text
Media
Design
Widgets
Theme
Embeds
```

Ontology / component-side authority:

```txt
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
PROJECT-CONTEXT.md
CURRENT-STATE.md
BACKLOG.md
```

Lab presentation aggregator evidence, read-only:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
```

Pilot / fixture evidence, read-only:

```txt
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
products/reference-implementations/axismundi-pilot/fixtures/core-block-editor-smoke.html
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
```

## In Scope

Phase 1 diagnostic:

```txt
1. Inventory v3.6.2 Tier 1 block-family classification and later close results.
2. Identify which v3.6.2 semantic/reset inputs were closed by v3.6.3-v3.6.7.
3. Compare current D-layer mapping files against the accumulated close docs.
4. Compare lab presentation aggregators against current mapping reality.
5. Compare Pilot specimen / editor fixture surfaces against current mapping
   reality by reusing v3.6.7 + v3.6.17 close evidence; do not rerun
   Playwright probes in Phase 1.
6. Produce a current block mapping table with status, owner, evidence, and
   next-route recommendation.
7. Identify drift or unmapped entries without editing implementation files.
8. Classify audited blocks against the WordPress Block Inserter categories:
   Text, Media, Design, Widgets, Theme, Embeds.
9. Record `style-guide-blocks.html` revision implications as catalog taxonomy
   drift, not as implementation.
10. Record `style-guide-prose.html` as the prose-style surface for Markdown and
    Custom HTML style application.
```

Expected Phase 1 output shape:

```txt
core block / family
WordPress category
v3.6.2 bucket
latest close evidence
D-layer mapping status
lab presentation status
Pilot fixture status
owner / route bucket
next action or no-action
```

Phase 2 may choose a layered report:

```txt
Layer 1: block status
Layer 2: ownership / route
Layer 3: next-cycle candidate
Layer 4: lab presentation drift routed to follow-on
```

## Out Of Scope / Non-Goals

Strong fences:

```txt
No edits to bindings/wordpress-material3/* D-layer files.
No edits to style-guide-blocks.html or style-guide-prose.html in this audit.
No edits to baseline style-guide.html.
No Pilot template, page, or pattern revision.
No Pilot theme revision.
No theme.json edits.
No functions.php edits.
No pilot-block-bridge.css/js edits.
No lab blocks.css edits.
No media-library asset sourcing inside this cycle unless the user explicitly
provides sources and a later reviewed implementation phase allows it.
No Embed block catalog implementation; Embeds are excluded for now.
No validators or generators edited.
No specimen additions or fixture expansion.
No #44 specimen coverage / validator hardening implementation.
No #21 Interpreter Plugin strategy or implementation.
No #46 disabled ripple host hygiene.
No #47 popover provider hygiene.
No #41 shared ripple runtime reopening.
No #43 specimen wall close reopening.
No no-inline-styles / compat-api/css / Edge Tools / webhint policy work.
```

If Phase 1 finds that lab catalog pages are stale, the finding should be routed
to a follow-on lab catalog revision or Pilot / lab block surface revision cycle.

If Phase 1 finds that the D-layer mapping files are stale, the finding should
be routed to a future D-layer / Interpreter Plugin planning cycle, not patched
inside Phase 1.

## Authority Model

Component-side authority:

```txt
docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

Block-side authority:

```txt
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
```

Accumulated close authority:

```txt
v3.6.3-v3.6.7 close docs
v3.6.17 close docs for ripple/runtime boundary
```

Audit output should not replace these authorities. It should produce a current
crosswalk that says how the authorities align today.

## Risks To Control

### R1 - Reclassification Drift

v3.6.2 already classified Tier 1 entries and v3.6.3 already routed semantic
decisions. v3.6.18 must not pretend those decisions do not exist.

Control:

```txt
Treat v3.6.2/v3.6.3 as source evidence.
Audit accumulated status and ownership, not from-scratch block taxonomy.
```

### R2 - Scope Expansion

Core blocks can expand into many block families and many visual states.

Control:

```txt
Phase 1 starts with Tier 1 plus blocks already touched by v3.6.3-v3.6.7 and
known D-layer entries. Tier 2 expansion is a follow-on unless Phase 1 proves a
small read-only addition is required for consistency.
```

### R3 - #44 Specimen Coverage Boundary

Mapping gaps may imply specimen coverage gaps.

Control:

```txt
Record specimen gaps as #44 or follow-on candidates.
Do not add specimens, fixtures, or validators in v3.6.18 Phase 1.
```

### R4 - #21 Interpreter Plugin Boundary

D-layer files are tempting implementation surfaces.

Control:

```txt
Read bindings/wordpress-material3/* only.
Do not edit binding_map.json, block_component_rules.json, taxonomy.md, or
related generated reports.
```

### R5 - Pilot Theme Revision Boundary

The user intends to inject web design / development context before template and
page work.

Control:

```txt
No template, page, visual redesign, copy, or Pilot theme revision decisions in
this cycle. This audit prepares the map for that later work.
```

### R6 - Ontology Alignment

Component rows and WordPress block families are different coordinate systems.

Control:

```txt
Declare component-side and block-side authorities separately.
Use the audit report as a crosswalk, not as a replacement authority.
```

### R7 - Layered Route Output

The result may not be one route.

Control:

```txt
Allow Phase 2 to record layered status / ownership / next-route findings.
Do not force mutually exclusive route framing if evidence is multi-layered.
```

### R8 - WordPress Category Drift

`style-guide-blocks.html` currently predates the latest WordPress Block
Inserter category framing. The official category split is Text / Media /
Design / Widgets / Theme / Embeds, while the current lab catalog is a compact
presentation surface with Text / Design and layout helpers.

Control:

```txt
Use WordPress documentation as taxonomy evidence.
Record category drift and recommended catalog split in Phase 1 / Phase 2.
Do not restructure style-guide-blocks.html during Phase 1.
```

### R9 - Media Assets And Embeds Scope

Media block catalog work needs temporary media-library source assets. Embed
blocks introduce remote-provider and privacy / availability concerns.

Control:

```txt
Record Media as a category that needs user-provided source assets before any
catalog implementation.
Exclude Embeds for now; do not create embed fixtures or remote-provider tests
inside v3.6.18.
```

### R10 - Prose Surface Confusion

`style-guide-prose.html` is not the same surface as the block catalog. It is the
long-form `.prose` target where Markdown and Custom HTML output can inherit
prose typography and element styling.

Control:

```txt
Map Markdown / Custom HTML style application to the prose surface.
Do not collapse prose long-form evidence into the block catalog taxonomy.
```

## Expected Write Scope

Before Phase 5:

```txt
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-0-PLAN.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-1-REPORT.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-2-DECISION.md, if Phase 1 supports it
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-3-VERIFICATION.md, if needed
```

Phase 5, if the cycle closes:

```txt
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-5-CLOSE.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md, only if a backlog route/status line needs close/reroute text
CURRENT-STATE.md
NEXT-SESSION.md
```

Files not expected to change:

```txt
bindings/wordpress-material3/*
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/*
tools/*
styleguide/*
```

## Phase Cadence

```txt
Phase 0 - plan and review gate
Phase 1 - read-only diagnostic inventory and mapping audit
Phase 2 - decision report; any implementation routes to a follow-on cycle,
          decided after Opus verdict and user execution GO
Phase 3 - verification appropriate to Phase 2 scope
Phase 4 - intentionally unused unless Phase 1 discovers deeper architecture
          audit need
Phase 5 - close docs, validation, commit, push
```

## Active Locks

```txt
Lock 1 - wp-custom downstream-only:
  preserved; no theme.json or wp-custom literal changes expected.

Lock 2 - md-sys color maps to md-ref:
  preserved; no token source changes expected.

Lock 3 - core/button semantic route before visual cleanup:
  preserved; v3.6.3 button route is source evidence, not reopened casually.

Lock 4 - semantic mismatch handling rule:
  active; mismatches should be routed by owner before any future visual patch.

Lock 5 - diagnostic-first before implementation:
  active; Phase 1 mapping diagnostic is mandatory before Phase 2.
```

If v3.6.18 proceeds cleanly, it will be the eighth clean Lock 5
self-application overall. Because the expected route is audit / decision-first,
implementation-cycle count should be recorded only if Phase 2 performs an
actual implementation.

## Phase 0 Review Request

Opus review should answer:

```txt
P1: Is v3.6.18 Core Block Mapping Audit the right next primary route?
P2: Are source inputs and fences sufficient, especially for style-guide-blocks,
    style-guide-prose, #44, #21, Pilot revision, and D-layer files?
P3: Are the WordPress category taxonomy, Embed exclusion, Media asset
    prerequisite, and prose Markdown / Custom HTML routing framed correctly
    before Phase 1 diagnostic?
```

## Next

Submit this Phase 0 plan for Opus review.

Do not enter Phase 1 until:

```txt
1. Opus returns Phase 0 verdict.
2. User gives explicit Phase 1 execution GO.
```
