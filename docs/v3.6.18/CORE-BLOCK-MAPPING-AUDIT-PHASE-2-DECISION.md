# v3.6.18 - Core Block Mapping Audit - Phase 2 Decision

Date: 2026-05-23
Status: Phase 2 no-code decision report
Primary candidate: Core Block Mapping Audit
Route: Layered mapping decision, no implementation

## Verdict

v3.6.18 Phase 2 accepts the Phase 1 diagnostic and records a no-code layered
decision.

```txt
Implementation in Phase 2: no
Mapping decision:          yes
Lab catalog edit:          no
Pilot fixture edit:        no
D-layer edit:              no
```

The audited WordPress core block map is coherent enough to proceed to future
template / catalog work, but the current state should be understood as a
layered route rather than one universal block-to-component mapping.

## Source Decision

The authoritative source stack remains:

```txt
Component-side authority:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md

Block-side authority:
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
  docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md

Accumulated close authority:
  docs/v3.6.3-v3.6.7 close docs
  docs/v3.6.17 close docs for ripple/runtime boundary

D-layer binding authority:
  bindings/wordpress-material3/binding_map.json
  bindings/wordpress-material3/block_component_rules.json
  bindings/wordpress-material3/taxonomy.md
```

v3.6.18 does not replace these authorities. It records the current crosswalk
between them.

## Layer 1 - Tier 1 Status

Decision:

```txt
v3.6.2 Tier 1 core block mapping status is closed / routed for the audited
set. No v3.6.18 implementation patch is needed.
```

Evidence:

```txt
v3.6.2:
  Tier 1 block families represented: 11 / 11
  Tier 1 entries classified:         26 / 26
  Unclassified entries:               0

v3.6.3:
  table-footer reset closed
  search filled bridge closed
  code / preformatted long-line overflow closed
  separator variants closed
  core/button route named
  quote / pullquote route named

v3.6.4:
  core/button mechanical cleanup closed
  quote / pullquote selector split closed

v3.6.5:
  editor md-sys light token parity closed

v3.6.6:
  current core/button editor CSS state-layer parity closed

v3.6.7:
  front-end wall and editor-valid smoke fixture split closed

v3.6.17:
  shared WordPress ripple runtime packaging decision closed
```

Tier 1 crosswalk result:

```txt
Text blocks:
  paragraph, heading, list, quote, pullquote, code, preformatted, table are
  closed or routed as CSS/prose/theme bridge surfaces.

Design blocks:
  buttons, button, separator, group/card, columns are closed or routed as
  D-layer direct / theme bridge / CSS layout surfaces.

Widgets:
  search is closed as D-layer direct + theme bridge, but missing from the lab
  catalog presentation.

Media / Theme:
  mostly outside v3.6.2 Tier 1; handled as follow-on catalog/template routes.
```

## Layer 2 - Ownership

Decision:

```txt
Core block mapping uses category-specific ownership.
Do not force every WordPress block into an M3 component binding.
```

Ownership buckets:

```txt
Text:
  CSS/prose and theme typography surfaces.
  Examples: paragraph, heading, quote, pullquote, code, preformatted, table.

Media:
  catalog follow-on after user-provided media source assets.
  Examples: image, gallery, audio, cover, file, media & text, video, icon.

Design:
  mixed D-layer direct bindings and layout/theme bridge surfaces.
  Examples: buttons/button, group/card, separator, columns.

Widgets:
  mixed D-layer direct, prose/custom-html, and catalog follow-on.
  Examples: search direct; Custom HTML routes to prose-style evidence.

Theme:
  template/FSE follow-on after web design / page context is supplied.
  Examples: navigation, site identity, query loop, post template/content/meta.

Embeds:
  excluded for now.
```

## Layer 3 - Lab Catalog Route

Decision:

```txt
`style-guide-blocks.html` should later split the WordPress Block Catalog by the
official Block Inserter categories, minus Embeds for now.
```

Future target categories:

```txt
Text
Media
Design
Widgets
Theme
Embeds excluded
```

Current drift recorded by Phase 1:

```txt
Image & Gallery is under Design but should become Media.
Search is not represented in the lab catalog even though it is a Widgets block
and has D-layer / Pilot fixture evidence.
Theme blocks are absent.
Widgets are absent except prose-adjacent Custom HTML routing.
Alignment helpers are a layout/design helper surface, not a WordPress category.
```

Implementation decision:

```txt
Do not edit style-guide-blocks.html in v3.6.18.
Route catalog restructuring to a follow-on cycle after v3.6.18 closes.
```

Media prerequisite:

```txt
Media category implementation depends on user-provided temporary media-library
source assets.
```

Note:

```txt
Commit 1eed48a imported placeholder media assets before v3.6.18 closed.
Those files do not become v3.6.18 mapping evidence and do not authorize catalog
implementation inside this decision report.
```

## Layer 4 - Prose Route

Decision:

```txt
Markdown and Custom HTML style application belongs to
`style-guide-prose.html`, not to the WordPress Block Catalog split.
```

Reason:

```txt
`style-guide-prose.html` is the long-form `.prose` target for element styling:
  emphasis / inline
  lists and markdown task lists
  blockquote
  code blocks
  tables
  images / figures
  heading depth
  hr
```

Implication:

```txt
Future catalog work may reference prose behavior, but should not collapse the
prose long-form sample into a block category page.
```

## Layer 5 - D-Layer Route

Decision:

```txt
`bindings/wordpress-material3/*` remains read-only in v3.6.18.
Any D-layer update belongs to a later #21 / ontology / Interpreter Plugin
planning cycle.
```

Reason:

```txt
The D-layer is a component binding map, not a complete WordPress block catalog.
It correctly maps only blocks that behave like M3 component bindings or
template/FSE composites.
```

D-layer current shape:

```txt
Direct.CoreBlockStyle:     14
Direct.CustomBlock:        11
Composite.TemplatePart:     7
Compositional.BlockPattern: 1
OutOfScope.Handoff:         9
RuntimeOnly.ThemeJS:        6
```

Important current mappings:

```txt
core/button, core/buttons:
  D-layer direct + v3.6.3-v3.6.6 theme bridge evidence.

core/group:
  D-layer direct Card variants.

core/list:
  D-layer direct plain / segmented list.

core/separator:
  D-layer direct Divider.

core/search:
  D-layer direct Search / Search field.

core/navigation:
  D-layer template/FSE composite, with plugin/custom menu edge.

core/gallery:
  D-layer plugin/custom Carousel edge; not a theme catalog implementation in
  v3.6.18.
```

## Layered Crosswalk Summary

| WordPress category | Current v3.6.18 decision | Owner | Follow-on |
|---|---|---|---|
| Text | Closed/routed for audited Tier 1 text surfaces | CSS/prose + theme bridge | Poetry/Verse naming; #44 coverage for long-line code/deep pullquote |
| Media | Not implemented; catalog route recorded | Catalog follow-on | Needs user-provided media-library source assets |
| Design | Closed/routed for audited buttons/group/separator/columns surfaces | D-layer direct + theme bridge + CSS layout | Catalog split follow-on |
| Widgets | Search closed; Custom HTML routes to prose; rest follow-on | D-layer direct + CSS/prose + catalog | Widgets catalog section |
| Theme | Not implemented; template/FSE route recorded | Template/FSE follow-on | Wait for web design/page context |
| Embeds | Excluded | None | Needs explicit source/privacy plan before reopening |

## Out Of Scope

v3.6.18 Phase 2 does not authorize:

```txt
No bindings/wordpress-material3 edits.
No style-guide-blocks.html edits.
No style-guide-prose.html edits.
No style-guide.html edits.
No Pilot fixture edits.
No Pilot template / page / pattern revision.
No theme.json edits.
No functions.php edits.
No pilot-block-bridge.css/js edits.
No lab blocks.css edits.
No validator or generator edits.
No specimen additions.
No #44 implementation.
No #21 Interpreter Plugin implementation or strategy report.
No #46 / #47 provider hygiene.
No #41 shared ripple runtime reopening.
No Embed implementation.
No media catalog implementation.
```

### Asset Surface Note

Commit `1eed48a Import placeholder media assets` is outside the v3.6.18 mapping
audit decision.

Asset surface:

```txt
assets/brand/
assets/media/
assets/LICENSES.md
LICENSE-MATRIX.md
NOTICE.md
.gitattributes
```

Classification:

```txt
post-v3.6.18 brand-slot / placeholder-media micro-commit
not v3.6.18 mapping evidence
not a catalog implementation
not a Pilot/distributable theme placement decision
```

Future cycle decisions still required:

```txt
Pilot vs distributable theme asset placement.
Pixabay video isolation pattern.
MP3 original retention vs OGG/Opus-only distributable policy.
Final brand symbol design / release seal.
Distributable theme asset reference policy.
```

## Phase 3 Recommendation

Phase 3 should be light verification only:

```txt
1. Confirm no implementation files changed for v3.6.18.
2. Confirm docs/v3.6.18 contains Phase 0/1/2 docs only.
3. Run git diff --check.
4. Optionally run standard no-code validations:
   php -l products/reference-implementations/axismundi-pilot/functions.php
   npm test
   npm run validate:computed
```

Do not rerun Playwright probes for mapping evidence.

## Phase 4

Phase 4 remains intentionally unused.

Reason:

```txt
Phase 1 produced enough evidence for the layered no-code decision, and Phase 2
does not discover a deeper architecture-audit need.
```

## Lock Compliance

```txt
Lock 1 - wp-custom downstream-only:
  preserved; no theme.json or wp-custom source changes.

Lock 2 - md-sys color maps to md-ref:
  preserved; no token source changes.

Lock 3 - core/button semantic route before visual cleanup:
  preserved; v3.6.3 core/button route remains source evidence.

Lock 4 - semantic mismatch handling rule:
  preserved; ownership / route buckets are named before any future visual work.

Lock 5 - diagnostic-first before implementation:
  preserved; Phase 1 diagnostic preceded Phase 2 decision.
```

Lock 5 count note for Phase 5:

```txt
If v3.6.18 closes as this no-code decision cycle, it is the eighth clean Lock 5
self-application overall. Implementation-cycle count remains unchanged.

Commit 1eed48a is an out-of-cycle asset micro-commit and should not be counted
as the v3.6.18 implementation-cycle application.
```

## Phase 2 Review Request

Opus review should answer:

```txt
P1: Any blockers to Phase 3 light verification?
P2: Does the five-layer decision correctly preserve Phase 1 evidence and
    separate the interleaved 1eed48a asset commit?
P3: Any wording changes needed before Phase 3 / Phase 5, especially around
    Media assets, Embeds exclusion, prose routing, D-layer read-only routing,
    or asset lineage?
```
