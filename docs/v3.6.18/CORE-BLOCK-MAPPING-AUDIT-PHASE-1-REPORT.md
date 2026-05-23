# v3.6.18 - Core Block Mapping Audit - Phase 1 Report

Date: 2026-05-23
Status: Phase 1 read-only diagnostic complete
Primary candidate: Core Block Mapping Audit
Phase 2 recommendation: Layered decision report, no implementation

## Verdict

Phase 1 confirms that v3.6.18 should stay a read-only mapping audit and move to
a no-code Phase 2 decision report.

The current core block map is coherent but split across four authority layers:

```txt
1. v3.6.2 Tier 1 specimen classification
2. v3.6.3-v3.6.7 close evidence
3. bindings/wordpress-material3 D-layer binding rules
4. lab presentation surfaces and Pilot fixtures
```

No implementation defect requires immediate patching inside Phase 1. The main
finding is taxonomy and surface drift: `style-guide-blocks.html` predates the
current WordPress Block Inserter category framing and should later split the
catalog by Text / Media / Design / Widgets / Theme, with Embeds excluded for
now. `style-guide-prose.html` should remain the long-form `.prose` surface for
Markdown and Custom HTML style application, not a block-catalog section.

## Read-Only Constraint

Implementation files edited:

```txt
none
```

Playwright probes:

```txt
not rerun
```

Pilot fixture evidence was reused from v3.6.7 and v3.6.17 close docs, per the
Phase 0 amendment.

## Source Inputs Read

Core block lineage:

```txt
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-5-CLOSE.md
```

D-layer / WordPress binding evidence:

```txt
bindings/wordpress-material3/binding_map.json
bindings/wordpress-material3/block_component_rules.json
bindings/wordpress-material3/binding_summary.md
bindings/wordpress-material3/gap_report.md
bindings/wordpress-material3/taxonomy.md
bindings/wordpress-material3/confidence_matrix.json
bindings/wordpress-material3/legitimacy_audit.json
```

Lab and Pilot presentation evidence:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
products/reference-implementations/axismundi-pilot/fixtures/core-block-editor-smoke.html
```

External taxonomy reference:

```txt
https://wordpress.org/documentation/article/blocks-list/
https://wordpress.org/documentation/category/text-blocks/
https://wordpress.org/documentation/category/media-blocks/
https://wordpress.org/documentation/category/design-blocks/
https://wordpress.org/documentation/category/widget-blocks/
https://wordpress.org/documentation/category/theme-blocks/
```

## Baseline Evidence

### v3.6.2 Tier 1 Classification

v3.6.2 classified all 26 Tier 1 specimen entries:

```txt
Tier 1 block families represented: 11 / 11
Tier 1 entries classified:         26 / 26
Unclassified entries:               0

no-action:          20
reset:               1
bridge:              0
semantic-decision:   5
backlog:             0
```

The only reset candidate was `core/table` footer border. The semantic-decision
set was the five `core/button` variants.

### Accumulated Close Evidence

```txt
v3.6.3:
  closed table-footer reset
  closed search filled bridge
  closed code / preformatted long-line overflow
  closed separator visibility variants
  named core/button anchor route
  named quote / pullquote split route

v3.6.4:
  closed button link affordance cleanup after route
  closed quote selector narrowing
  closed pullquote distinct surface bridge

v3.6.5:
  closed editor md-sys light token parity

v3.6.6:
  closed current editor CSS state-layer parity for core/button
  kept animated ripple Pilot-only

v3.6.7:
  split front-end specimen wall from editor-valid smoke fixture
  kept #44 open only for coverage / validator follow-ons

v3.6.17:
  closed #41 shared ripple runtime packaging decision
  future shared animated WordPress ripple runtime is plugin/custom-binding or
  dedicated runtime territory if pursued
```

## WordPress Category Taxonomy

The WordPress Blocks list identifies six Block Inserter categories:

```txt
Text
Media
Design
Widgets
Theme
Embeds
```

v3.6.18 uses these as taxonomy evidence, not as an implementation mandate.
Embeds are excluded for now.

Phase 1 category implications:

```txt
Text:
  paragraph, heading, list, quote, code, preformatted, pullquote, table,
  poetry / verse, details, classic

Media:
  image, gallery, audio, cover, file, media & text, video, icon

Design:
  buttons, columns, group, row, stack, grid, separator, spacer, more,
  page break, accordion

Widgets:
  search, custom html, archives, calendar, categories, latest posts,
  latest comments, page list, RSS, shortcode, social icons, tag cloud

Theme:
  navigation, site identity, query loop, post template / content / metadata,
  comments, template part, archive / search title and related FSE blocks

Embeds:
  excluded for now
```

## D-Layer Inventory

The D-layer binding map is internally coherent and remains read-only in this
cycle.

`binding_summary.md` records:

```txt
Tier 1 token bindings:     6
Tier 2 component bindings: 32 in-scope

Direct.CoreBlockStyle:     14
Direct.CustomBlock:        11
Composite.TemplatePart:     7
OutOfScope.Handoff:         9
RuntimeOnly.ThemeJS:        6
Compositional.BlockPattern: 1
```

Core block references in `block_component_rules.json`:

```txt
core/button:
  Button filled / tonal / elevated / outlined / text
  Direct.CoreBlockStyle

core/buttons:
  Button group
  Direct.CoreBlockStyle

core/group:
  Card filled / elevated / outlined
  Direct.CoreBlockStyle

core/list:
  List plain / segmented
  Direct.CoreBlockStyle

core/group + core/group:
  List item with leading / trailing
  Compositional.BlockPattern

core/separator:
  Divider inset / middle-inset
  Direct.CoreBlockStyle

core/search:
  Search field / Search
  Direct.CoreBlockStyle

core/navigation:
  App bar top, navigation bar, navigation rail
  Composite.TemplatePart

core/navigation + m3/menu:
  Menu dropdown
  Direct.CustomBlock

core/gallery + m3/carousel:
  Carousel
  Direct.CustomBlock
```

Important implication:

```txt
The D-layer does not claim every WordPress core block is an M3 component.
Many Text and Theme blocks remain theme typography / content surfaces rather
than component bindings.
```

## Lab Presentation Inventory

`style-guide-blocks.html` currently exposes one compact WordPress Block Catalog
surface:

```txt
blocks-alignment: Layout / Alignment helpers
blocks-paragraph: Text / Paragraph
blocks-quote:     Text / Quote & Pullquote
blocks-code:      Text / Code / Preformatted / Verse
blocks-list:      Text / List
blocks-separator: Design / Separator
blocks-table:     Design / Table
blocks-media:     Design / Image & Gallery
blocks-group:     Design / Columns / Group / Card variants
blocks-button:    Design / Buttons
```

Drift:

```txt
Image & Gallery should be Media, not Design.
Alignment helpers are a layout/design helper surface, not a WordPress category.
Widgets and Theme categories are absent.
Embeds are absent, which is acceptable because Embeds are excluded for now.
```

`style-guide-prose.html` is a distinct long-form `.prose` surface:

```txt
article.sg-article.prose
prose.css coverage:
  emphasis / inline
  unordered / ordered / task lists
  blockquote
  code blocks
  tables
  images / figures
  heading depth
  hr
```

Decision implication:

```txt
Markdown and Custom HTML style application should route to prose evidence.
Do not fold prose into the WordPress Block Catalog split.
```

## Pilot Fixture Inventory

Phase 1 did not rerun Playwright.

Reused v3.6.7 close evidence:

```txt
Front-end specimen wall:
  stable data-ax anchors for computed-style evidence
  Tier 1 anchors: 11 / 11
  console/page errors: 0

Editor-valid smoke fixture:
  WordPress-save-compatible core block editor smoke
  editor console/page errors: 0
  block validation errors: 0
```

Current fixture source confirms the split:

```txt
core-block-specimen-wall.html:
  data-ax-specimen-id anchors for paragraph, heading, list, quote, code,
  table, buttons, search, separator, group, columns

core-block-editor-smoke.html:
  editor-valid smoke coverage for paragraph, list, quote, code, table,
  buttons, search, separator, columns
```

Fixture implication:

```txt
The current Pilot fixture set is sufficient evidence for v3.6.18 mapping
audit. Media / Widgets / Theme expansion belongs to follow-on catalog or
specimen coverage work, not Phase 1.
```

## Mapping Crosswalk

Legend:

```txt
Status:
  closed        Evidence and route are current.
  routed        Owner is named, future work possible.
  follow-on     Needs later catalog/specimen/decision work.
  excluded      Out of current v3.6.18 scope.

Owner:
  theme bridge
  CSS/prose
  D-layer direct
  D-layer plugin/custom
  template/FSE
  specimen follow-on
  catalog follow-on
```

| Core block / family | WP category | v3.6.2 bucket | Latest close evidence | D-layer status | Lab presentation status | Pilot fixture status | Owner / route | Next action |
|---|---|---|---|---|---|---|---|---|
| `core/paragraph` | Text | no-action | v3.6.2 no native conflict | No component binding claim | Present in Paragraph; prose also covers p | Wall + editor smoke | CSS/prose | No-action; catalog category split later |
| `core/heading` | Text | no-action | v3.6.2 typography stable | Token typography binding only | Prose heading depth; no block catalog section except specimen headings | Wall + editor smoke | CSS/prose | No-action; prose authority |
| `core/list` | Text | no-action | v3.6.2 plain / ordered / segmented stable | Direct.CoreBlockStyle for plain and segmented | Present in List, includes task-list markdown sample | Wall + editor smoke | D-layer direct + CSS/prose | Closed; #44 may add coverage polish only |
| `core/quote` | Text | no-action | v3.6.3 route named; v3.6.4 selector narrowing closed | No direct D-layer component rule | Present with pullquote; prose blockquote also covers generic prose | Wall + editor smoke | theme bridge + CSS/prose | Closed |
| `core/pullquote` | Text | semantic route from v3.6.3 evidence | v3.6.4 distinct pullquote surface closed | No direct D-layer component rule | Present with quote | Not in current editor smoke; front-end catalog only | theme bridge | Closed; #44 deep pullquote coverage remains follow-on |
| `core/code` | Text | no-action | v3.6.3 long-line overflow closed | No component binding claim | Present in Code / Preformatted / Verse; prose code covers long form | Wall + editor smoke | CSS/prose | Closed; #44 long-line coverage follow-on |
| `core/preformatted` | Text | no-action via code family | v3.6.3 post-content pre overflow closed | No component binding claim | Present in Code / Preformatted / Verse | Front-end wall grouped through code evidence | CSS/prose | Closed; #44 long-line coverage follow-on |
| `core/verse` / Poetry | Text | not separate in v3.6.2 table | No later implementation close | No component binding claim | Present as `core/verse`; WP docs now label Poetry | Not in Pilot wall | CSS/prose | Follow-on naming decision: Verse vs Poetry |
| `core/table` | Text | reset for footer; otherwise no-action | v3.6.3 footer reset closed | No component binding claim | Present in Table, currently under Design | Wall | theme bridge + CSS/prose | Closed; catalog should move to Text |
| `core/buttons` | Design | semantic-decision family | v3.6.3 route named; v3.6.4 cleanup; v3.6.6 state parity | Direct.CoreBlockStyle for Button group | Present in Buttons | Wall + editor smoke | D-layer direct + theme bridge | Closed |
| `core/button` | Design | semantic-decision | v3.6.3 anchor route; v3.6.4 cleanup; v3.6.17 ripple packaging closed | Direct.CoreBlockStyle for five styles | Present in Buttons | Wall + editor smoke; Pilot-only ripple markers from v3.6.17 | D-layer direct + theme bridge | Closed; do not reopen #41 |
| `core/separator` | Design | no-action | v3.6.3 visibility variants closed | Direct.CoreBlockStyle as Divider | Present in Separator | Wall + editor smoke | D-layer direct | Closed |
| `core/group` | Design | no-action | v3.6.2 card variants stable | Direct.CoreBlockStyle for Card variants; also compositional list pattern | Present in Group / Card | Wall | D-layer direct + block pattern | Closed; catalog category split later |
| `core/columns` / `core/column` | Design | no-action | v3.6.2 layout stable | No direct D-layer component claim | Present in Group section | Wall + editor smoke | CSS layout / catalog | No-action |
| `core/image` | Media | not in v3.6.2 Tier 1 table | No close evidence after v3.6.1 | No D-layer direct claim | Present in Image & Gallery, mislabeled Design | Not in Pilot wall | catalog follow-on | Needs media asset prerequisite before implementation |
| `core/gallery` | Media | not in v3.6.2 Tier 1 table | Component matrix routes Carousel as distinct but coupled | Direct.CustomBlock with `m3/carousel` + `core/gallery` | Present in Image & Gallery, mislabeled Design | Not in Pilot wall | D-layer plugin/custom + catalog follow-on | Needs media source assets; no implementation now |
| `core/search` | Widgets | no-action | v3.6.3 filled search bridge closed | Direct.CoreBlockStyle for Search / Search field | Not in style-guide-blocks; present in Pilot fixtures | Wall + editor smoke | D-layer direct + theme bridge | Catalog follow-on: Widgets section |
| `core/navigation` | Theme | not in v3.6.2 Tier 1 table | Later Wave nav components exist outside this audit | Composite.TemplatePart; menu dropdown has plugin/custom edge | Absent from style-guide-blocks | Not in Pilot wall | template/FSE + D-layer plugin/custom edge | Theme category follow-on, no Pilot revision now |
| Theme identity / query / post meta blocks | Theme | not in v3.6.2 Tier 1 table | No v3.6.x block mapping close | D-layer mostly not mapped | Absent | Absent | template/FSE / TBD | Follow-on after template/page design input |
| Widget blocks other than search | Widgets | not in v3.6.2 Tier 1 table | No v3.6.x block mapping close | No direct D-layer claim except Custom HTML route to prose | Absent | Absent | catalog/prose follow-on | Widgets section later |
| Markdown / Custom HTML output | Widgets / authoring surface | not in v3.6.2 Tier 1 table | User routed prose style application | No D-layer component claim | `style-guide-prose.html` is correct surface | Not a Pilot fixture target | CSS/prose | Phase 2 should record prose route |
| Embed blocks | Embeds | excluded | Excluded by user framing | No current D-layer claim | Absent | Absent | excluded | Keep excluded until explicit source/privacy plan |

## Findings

### F1 - Core Tier 1 Status Is Mostly Closed

The v3.6.2 Tier 1 reset and semantic-decision inputs were consumed by v3.6.3
through v3.6.7. No Tier 1 block in the audited set needs a Phase 2 patch.

Phase 2 should record this as:

```txt
Tier 1 core mapping status: closed / routed
Implementation in v3.6.18: no
```

### F2 - D-Layer Is A Component Binding Map, Not A Complete Block Catalog

The D-layer correctly maps only WordPress blocks that behave like M3 component
bindings or template/FSE composites. It does not attempt to classify every
WordPress core block as a component.

This is healthy:

```txt
Text blocks mostly remain CSS/prose surfaces.
Design blocks include both D-layer direct bindings and layout wrappers.
Widgets / Theme blocks need catalog/template routing, not automatic M3
component claims.
```

### F3 - `style-guide-blocks.html` Has Taxonomy Drift

The lab catalog currently groups sections by local presentation labels:

```txt
Layout
Text
Design
```

The desired WordPress Block Catalog split is:

```txt
Text
Media
Design
Widgets
Theme
Embeds excluded for now
```

Concrete drift:

```txt
Image & Gallery is under Design but should become Media.
Search is not represented in the lab catalog even though it is a Widgets block
and has D-layer / Pilot fixture evidence.
Theme blocks are absent.
Widgets are absent except prose-adjacent Custom HTML routing.
```

### F4 - Media Category Needs User-Provided Assets Before Implementation

`core/image` and `core/gallery` already have a lab catalog placeholder surface,
but media block implementation needs real temporary media-library sources.

Phase 2 should not authorize media implementation. It should record:

```txt
Media catalog split: yes, future route
Media source assets: user prerequisite
Embeds: excluded
```

### F5 - `style-guide-prose.html` Should Remain Separate

`style-guide-prose.html` is the right surface for `.prose` typography and
element styling. It should absorb Markdown and Custom HTML style-application
evidence, but should not become a WordPress Block Catalog category page.

### F6 - Pilot Fixtures Are Sufficient For This Audit

The v3.6.7 fixture split is still the right boundary:

```txt
front-end wall = stable computed-style anchors
editor smoke = WordPress-save-compatible editor evidence
```

No Phase 1 probe rerun was needed, and no fixture expansion belongs in this
cycle.

## Phase 2 Recommendation

Proceed to Phase 2 as a no-code layered decision report.

Suggested layers:

```txt
Layer 1 - Tier 1 status:
  closed / routed, no implementation

Layer 2 - Ownership:
  Text -> CSS/prose
  Media -> catalog follow-on after assets
  Design -> mixed D-layer direct + layout/theme bridge
  Widgets -> search direct + prose/custom-html + catalog follow-on
  Theme -> template/FSE follow-on after web design / page context
  Embeds -> excluded

Layer 3 - Lab catalog route:
  style-guide-blocks.html should later split by WordPress categories.

Layer 4 - Prose route:
  Markdown / Custom HTML style application belongs to style-guide-prose.html.

Layer 5 - D-layer route:
  bindings/wordpress-material3 remains read-only in v3.6.18; any D-layer
  update belongs to a later #21 / ontology cycle.
```

Expected Phase 2 write scope:

```txt
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-2-DECISION.md
```

Implementation routes should be follow-on cycles, not Phase 2 patches.

## Non-Goals Confirmed

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
No media asset sourcing.
```

## Lock Compliance

```txt
Lock 1 - wp-custom downstream-only:
  preserved; no theme.json or wp-custom source changes.

Lock 2 - md-sys color maps to md-ref:
  preserved; no token source changes.

Lock 3 - core/button semantic route before visual cleanup:
  preserved; v3.6.3 route is source evidence, not reopened.

Lock 4 - semantic mismatch handling rule:
  preserved; ownership / route buckets are named before any future visual work.

Lock 5 - diagnostic-first before implementation:
  preserved; Phase 1 completed before Phase 2 decision.
```

## Validation

Phase 1 validation was documentation hygiene only:

```txt
implementation file edits: none
Playwright probes:         not rerun
```

Final whitespace/status checks should be run after this report is written:

```txt
git diff --check
git status --short --branch
```

## Phase 1 Review Request

Opus review should answer:

```txt
P1: Any blockers to Phase 2 as a no-code layered decision report?
P2: Is the mapping crosswalk complete enough for Tier 1 + touched block
    families + WordPress category drift?
P3: Should any wording change before Phase 2, especially Media assets,
    Embeds exclusion, prose routing, or D-layer read-only routing?
```
