# v3.6.24 Core Block Style Guide Full Spec - Phase 1 Report

Status: Phase 1 read-only diagnostic  
Date: 2026-05-24  
Route recommendation: Route D + M3  
Write scope in this phase: none

## 1. Verdict

Phase 1 recommends **Route D - Source + Mirror Full Spec** with **M3 publish-tooling regeneration**.

```txt
Recommended Phase 2:
  - edit products/reference-implementations/axismundi-lab/style-guide-blocks.html
  - run npm run publish:styleguide
  - keep intended styleguide/blocks.html output
  - do not edit CSS
  - do not edit validators
  - do not edit Pilot or distributable surfaces

Expected Phase 2 output:
  - fuller category catalog
  - added specimens where current local evidence supports them
  - explicit gap/reference rows where support is not ready
  - existing validator anchors preserved
```

The cycle should stay in the catalog layer. Distributable skeleton work remains routed to v3.6.25 or later.

## 2. Read-Only Lock

Phase 1 preserved read-only scope.

```txt
catalog source edits:       0
generated mirror edits:     0
CSS edits:                  0
validator edits:            0
Pilot edits:                0
distributable edits:        0
release-seal edits:         0
BACKLOG edits:              0
```

## 3. Source Inputs Read

### Local Sources

```txt
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-0-PLAN.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-1-REPORT.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-3-VERIFICATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-5-CLOSE.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-2-DECISION.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md

products/reference-implementations/axismundi-lab/style-guide-blocks.html
styleguide/blocks.html
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/assets/styles/blocks.css
styleguide/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
assets/media/README.md
tools/generators/publish_styleguide.py
tools/validators/validate_pilot_computed_styles.js
package.json
```

### External Sources

```txt
WordPress Blocks List:
  https://wordpress.org/documentation/article/blocks-list/

Gutenberg block-library:
  https://github.com/WordPress/gutenberg/tree/trunk/packages/block-library
  v3.6.23 frozen snapshot: f0a5c0cd5fa957170608692721b18da336e55328

Auto-generated handbook docs proposal:
  https://make.wordpress.org/core/2026/05/05/proposal-auto-generate-block-editor-handbook-docs-from-block-json/

Design tools roster:
  https://make.wordpress.org/core/2026/04/22/roster-of-design-tools-per-block-wordpress-7-0/

Pattern Overrides:
  https://make.wordpress.org/core/2026/03/16/pattern-overrides-in-wp-7-0-support-for-custom-blocks/

Block Bindings:
  https://make.wordpress.org/core/2025/11/12/block-bindings-improvements-in-wordpress-6-9/
```

External source interpretation:

```txt
Blocks List:
  taxonomy/reference authority for the six Block Inserter categories.

Gutenberg block-library snapshot:
  block.json source-of-truth, but frozen at v3.6.23 for this cycle.

Auto-generated docs proposal:
  methodology reference; confirms block.json-centered documentation direction.

Design tools roster:
  WP 7.0 delta/support matrix reference.

Pattern Overrides / Block Bindings:
  reference-only; implementation belongs to BACKLOG #21 / future plugin territory.
```

## 4. Findings

### F1 - v3.6.23 Shell Is Correct but Not Full Spec

Current source catalog sections:

```txt
Text:
  blocks-paragraph
  blocks-quote
  blocks-code
  blocks-list
  blocks-table

Media:
  blocks-media

Design:
  blocks-alignment
  blocks-separator
  blocks-group
  blocks-button

Widgets:
  blocks-search

Theme:
  blocks-theme
```

Evidence:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  line 317: blocks-paragraph
  line 345: blocks-quote
  line 377: blocks-code
  line 419: blocks-list
  line 470: blocks-table
  line 625: blocks-media
  line 658: blocks-alignment
  line 695: blocks-separator
  line 721: blocks-group
  line 783: blocks-button
  line 815: blocks-search
  line 839: blocks-theme
```

This is a good category shell, but several upstream/category surfaces remain only implicit.

### F2 - M9 Source-of-Authority Inventory

M9 grep ran first.

Active source/mirror contract:

```txt
source authority:
  products/reference-implementations/axismundi-lab/style-guide-blocks.html

generated mirror:
  styleguide/blocks.html

generator:
  tools/generators/publish_styleguide.py

npm script:
  npm run publish:styleguide
```

Phase 0 listed `tools/publish_styleguide.py`, but the actual script is:

```txt
tools/generators/publish_styleguide.py
```

The generator declares:

```txt
SOURCE  = products/reference-implementations/axismundi-lab
PUBLISH = styleguide
STYLE_GUIDES includes:
  style-guide.html        -> index.html
  style-guide-blocks.html -> blocks.html
  style-guide-prose.html  -> prose.html
```

It inserts a publish-mirror banner and rewrites links. Therefore source and mirror HTML are intentionally not byte-identical.

Baseline hashes:

```txt
style-guide-blocks.html: C2947C91D0730FAA0A7A3A9E4AB7E82ADFF66BA0A44B02841D17F241C1D74F44
styleguide/blocks.html:  2799E2E895AEBE525123C78DF10D540B98AB649EC7FB19B509E71E304AD2C99C
```

### F3 - Validator Anchor Dependencies Are Narrow

Validator dependency found:

```txt
tools/validators/validate_pilot_computed_styles.js
  line 336: loads styleguide/blocks.html#blocks-table
  line 340: #blocks-table .wp-block-table thead
  line 341: #blocks-table .wp-block-table.is-style-stripes
  line 342: #blocks-table .wp-block-table:not(.is-style-stripes) ...
  line 343: #blocks-table .wp-block-table.is-style-stripes tbody tr:nth-child(odd)
  line 344: #blocks-table .wp-block-table.is-style-stripes tbody tr:nth-child(even)
```

No validator dependency was found for `#blocks-search` or `#blocks-theme`. They should still be preserved because v3.6.23 made them category anchors, but they do not currently require validator updates.

Phase 2 implication:

```txt
Preserve:
  #blocks-table
  #blocks-search
  #blocks-theme

Do not update validator unless Phase 2 intentionally adds new computed-style coverage.
```

### F4 - CSS Support Is Strong for Existing Styled Surfaces

Tracked `blocks.css` copies are byte-identical:

```txt
products/reference-implementations/axismundi-lab/stylesheets/blocks.css:   A09F541B4508A2825197D3A51DB13FD7BAEA64790D6B584B46C9989330C71593
products/reference-implementations/axismundi-pilot/assets/styles/blocks.css: A09F541B4508A2825197D3A51DB13FD7BAEA64790D6B584B46C9989330C71593
styleguide/stylesheets/blocks.css:                                           A09F541B4508A2825197D3A51DB13FD7BAEA64790D6B584B46C9989330C71593
```

Local block styling exists for:

```txt
list
quote
pullquote
code
preformatted
verse
separator
table
image
gallery
columns
group/card variants
buttons/button variants
search filled
segmented list
```

No `blocks.css` support was found for:

```txt
heading
cover
media-text
audio
video
file
accordion
icon
breadcrumbs
navigation/query/site-logo theme blocks
```

Heading is still viable because `base.css` owns semantic heading mapping. It does not need a `blocks.css` rule.

### F5 - v3.6.2 Tier 1 Supports Heading Restoration

v3.6.2 Tier 1 classification includes:

```txt
heading-h1
heading-h2
heading-h3
```

The Pilot specimen wall also has `data-ax-specimen-id="core-heading"` at line 23.

Phase 2 implication:

```txt
Add a Heading specimen to Text.
Do not reopen v3.6.2 Tier 1.
Use existing heading evidence as already-classified support.
```

### F6 - Pilot Fixture Evidence Supports Current Variants

Pilot fixture evidence:

```txt
core-paragraph
core-heading
core-list
list-segmented
core-quote
core-code
core-table
table-default
table-stripes
table-footer
core-buttons
button-fill
button-outline
button-tonal
button-elevated
button-text
core-search
core-separator
separator-default
separator-inset
separator-middle-inset
core-group
group-card-filled
group-card-elevated
group-card-outlined
core-columns
```

This evidence supports completing catalog specimens for Text and Design without touching Pilot.

### F7 - Media Assets Exist, but Not All Media Blocks Are Ready

Local media assets:

```txt
assets/media/image/image-placeholder-mogu-1024.webp
assets/media/audio/audio-placeholder-jazzy-lofi.ogg
assets/media/video/video-placeholder-gwangan-720p.webm
```

`assets/media/README.md` states:

```txt
Image and audio assets are project-author supplied.
The video placeholder is Pixabay-sourced and remains under Pixabay Content License.
Embed block fixtures are not included yet.
```

Phase 2 implication:

```txt
Image/Gallery:
  already implemented.

Cover:
  specimen candidate using existing image placeholder or existing visual placeholder.

Media & Text:
  specimen candidate using existing image placeholder plus text.

Audio:
  gap/external-prerequisite row is safer for v3.6.24 unless Phase 2 explicitly chooses
  native audio specimen with project-author asset.

Video:
  external prerequisite / gap row because Pixabay isolation is unresolved.

File:
  external prerequisite / gap row because no placeholder file asset was found.

Icon:
  WP 7.0 reference-only / gap row.
```

### F8 - WordPress Blocks List Is the Taxonomy Authority

The WordPress Blocks List currently presents six Block Inserter categories:

```txt
Text
Media
Design
Widgets
Theme
Embeds
```

Relevant current list items:

```txt
Text includes: Paragraph, Heading, List, Quote, Code, Details, Math,
Preformatted, Pullquote, Table, Poetry, Classic.

Media includes: Image, Gallery, Audio, Cover, File, Media & Text, Video, Icon.

Design includes: Accordion, Buttons, Columns, Group, Row, Stack, Grid,
More, Page Break, Separator, Spacer.

Widgets includes: Archives, Calendar, Terms List, Categories List,
Custom HTML, Latest Comments, Latest Posts, Page List, RSS, Search,
Shortcode, Social Icons, Tag Cloud.

Theme includes broad FSE/template/query surfaces including Navigation,
Site Logo, Query Loop, Post Template, Template Part, Breadcrumbs, and others.
```

Embeds remain excluded by Axismundi policy.

### F9 - WP 7.0 / WP 6.9 Features Are Reference-Only Here

The design tools roster records WP 7.0 changes:

```txt
Verse renamed to Poetry
New blocks:
  Accordion
  Breadcrumbs
  Icon
  Math
  Post Time to Read
  Term Query / Term Template / Term Count / Term Name
```

Pattern Overrides and Block Bindings require runtime/editor decisions:

```txt
Pattern Overrides:
  server-side block_bindings_supported_attributes filter is the opt-in path.

Block Bindings:
  WordPress 6.9 adds server/editor APIs for supported attributes and source UI.
```

Phase 2 implication:

```txt
Do not implement these features in v3.6.24.
Represent relevant new blocks as reference/gap rows.
Route runtime/editor work to BACKLOG #21.
```

### F10 - Theme/FSE Should Stay Gap Rows

Local evidence found Theme block usage only in theme templates:

```txt
products/reference-implementations/axismundi-pilot/templates/index.html
products/reference-implementations/ontology-theme-pilot/templates/index.html
```

v3.6.24 must not edit templates or imply FSE readiness.

Phase 2 implication:

```txt
Theme section should remain scaffold/gap rows only.
Suggested gap rows:
  Navigation
  Query Loop / Post Template
  Site Logo / Site Title / Site Tagline
  Template Part
  Breadcrumbs
```

## 5. Four-State Completeness Matrix

### Text

| Block / Surface | State | Evidence | Phase 2 Recommendation |
|---|---|---|---|
| Paragraph | Implemented specimen | current catalog + v3.6.2 Tier 1 | Keep |
| Heading | Implemented specimen candidate | v3.6.2 Tier 1 + Pilot fixture + base.css heading mapping | Add specimen |
| List | Implemented specimen | current catalog + segmented style | Keep |
| Quote / Pullquote | Implemented specimen | current catalog + blocks.css | Keep |
| Code / Preformatted | Implemented specimen | current catalog + blocks.css | Keep |
| Poetry / Verse | Implemented specimen | current catalog uses core/verse with Poetry title | Keep slug, keep Poetry note |
| Table | Implemented specimen | current catalog + validator anchor | Keep #blocks-table |
| Details | Reference / gap row | WP Blocks List item, no local support | Add gap row |
| Math | Reference / gap row | WP 7.0 new block | Add gap row |
| Classic | Out of scope | legacy/editor block | Add gap/out-of-scope note if useful |

### Media

| Block / Surface | State | Evidence | Phase 2 Recommendation |
|---|---|---|---|
| Image | Implemented specimen | current catalog + blocks.css | Keep |
| Gallery | Implemented specimen | current catalog + blocks.css | Keep |
| Cover | Implemented specimen candidate | image asset/placeholder available, no CSS needed if simple | Add specimen if bounded |
| Media & Text | Implemented specimen candidate | image + text composition possible | Add specimen if bounded |
| Audio | External prerequisite or bounded specimen | project-author audio exists, but native control policy not settled | Prefer gap row unless Phase 2 explicitly accepts native specimen |
| Video | External prerequisite | Pixabay isolation unresolved | Gap row |
| File | External prerequisite | no file placeholder asset found | Gap row |
| Icon | Reference / gap row | WP 7.0 new block | Gap row |

### Design

| Block / Surface | State | Evidence | Phase 2 Recommendation |
|---|---|---|---|
| Buttons / Button | Implemented specimen | current catalog + v3.6.2 Tier 1 | Keep |
| Columns | Implemented specimen | current catalog + Pilot fixture | Keep |
| Group / Card variants | Implemented specimen | current catalog + blocks.css | Keep |
| Separator | Implemented specimen | current catalog + blocks.css | Keep |
| Alignment helper | Local helper | current catalog | Keep as local design helper, not WP category authority |
| Accordion | Reference / gap row | WP 7.0 new block | Gap row |
| Row / Stack / Grid | Reference / gap row | WP Blocks List, no local specimen | Gap row |
| More / Page Break / Spacer | Reference / gap row | WP Blocks List, no local specimen | Gap row |

### Widgets

| Block / Surface | State | Evidence | Phase 2 Recommendation |
|---|---|---|---|
| Search | Implemented specimen | current catalog + v3.6.2 Tier 1 + blocks.css | Keep #blocks-search |
| Custom HTML | Out of scope for block catalog | v3.6.18 routes to prose | Add route note to prose |
| Archives / Calendar / Terms / Categories / Latest / RSS / Social / Tag Cloud | Reference / gap row | WP Blocks List, no local support | Gap rows or compact reference list |
| Shortcode | Out of scope / legacy | no local support | Gap/out-of-scope row |

### Theme

| Block / Surface | State | Evidence | Phase 2 Recommendation |
|---|---|---|---|
| Navigation | Reference / gap row | WP Blocks List, template/FSE surface | Gap row |
| Query Loop / Post Template | Reference / gap row | local template evidence exists, but not catalog support | Gap row |
| Site Logo / Site Title / Site Tagline | Reference / gap row | theme context required | Gap row |
| Template Part | Reference / gap row | FSE context required | Gap row |
| Breadcrumbs | Reference / gap row | WP 7.0 new block | Gap row |
| Comments / Terms / Post metadata family | Reference / gap row | theme context required | Compact gap row |

### Embeds

| Block / Surface | State | Evidence | Phase 2 Recommendation |
|---|---|---|---|
| Embed provider blocks | Out of scope | v3.6.18 exclusion + no provider/privacy policy | Keep excluded |

## 6. Media Decision Tree Result

```txt
Audio:
  asset readiness: yes, project-author supplied
  implementation readiness: partial
  recommendation: gap row or optional native specimen only if Phase 2 keeps it simple

Video:
  asset readiness: yes
  policy readiness: no, Pixabay isolation unresolved
  recommendation: external prerequisite gap row

File:
  asset readiness: no placeholder file found
  recommendation: external prerequisite gap row

Media & Text:
  asset readiness: yes, image placeholder available
  implementation readiness: yes if simple
  recommendation: specimen candidate

Cover:
  asset readiness: yes, image placeholder available
  implementation readiness: yes if simple
  recommendation: specimen candidate

Icon:
  upstream status: WP 7.0 new block
  implementation readiness: no
  recommendation: reference-only gap row
```

## 7. M7 Mirror Route Decision

Recommendation: **M3 publish-tooling regeneration**.

Reason:

```txt
v3.6.23 already selected M3 for this source/mirror pair.
tools/generators/publish_styleguide.py is the declared generator.
styleguide/blocks.html is intentionally not byte-identical.
The generator adds banner/link rewrites and regenerates a broad publish surface.
```

Phase 2 should:

```txt
1. Edit source catalog only.
2. Run npm run publish:styleguide.
3. Keep intended styleguide/blocks.html output.
4. Restore unrelated generated churn.
5. Record source/mirror hashes.
```

## 8. Validator Strategy

Recommendation: no validator edit in Phase 2.

Required preservation:

```txt
#blocks-table:
  validator-dependent, must preserve.

#blocks-search:
  category anchor, preserve.

#blocks-theme:
  category anchor, preserve.
```

New anchors may be added for specimens/gap rows, but they should not be added to computed-style validation in v3.6.24 unless Phase 2 explicitly chooses Route E.

## 9. Phase Route Evaluation

| Route | Verdict | Reason |
|---|---|---|
| A - no-code decision | Reject | Phase 1 found bounded implementation path |
| B - gap matrix only | Acceptable fallback | Useful if Phase 2 wants minimum risk, but under-delivers user full-spec intent |
| C - source only | Reject | v3.6.23 proved M3 is the correct mirror path |
| D - source + mirror full spec | Recommended | Best scope/benefit balance |
| E - source + mirror + validator | Defer | No current evidence requiring validator update |
| F - source + mirror + CSS tracked copies | Defer | Existing CSS is enough for recommended specimens/gaps |
| G - product/template collapse | Reject | Violates catalog layer boundary |
| H - all WP blocks implementation | Reject | Violates full-spec definition |

## 10. Diagnostic Questions

### Q1. Which contracts apply?

Active:

```txt
source authority:
  products/reference-implementations/axismundi-lab/style-guide-blocks.html

generated mirror:
  styleguide/blocks.html

validator anchor:
  #blocks-table

tracked CSS copy:
  lab / Pilot / styleguide blocks.css are byte-identical
```

### Q2. Does v3.6.23's mirror route remain valid?

Yes. The actual generator is `tools/generators/publish_styleguide.py`, exposed by `npm run publish:styleguide`. M3 remains the correct default.

### Q3. Which current specimens are complete enough?

Complete enough:

```txt
Paragraph
Quote / Pullquote
Code / Preformatted / Poetry
List
Table
Image / Gallery
Separator
Group / Columns / Card
Buttons
Search
```

Heading is missing from the catalog but supported by v3.6.2 Tier 1 and local heading styles.

### Q4. Which Text blocks need action?

```txt
Add specimen:
  Heading

Keep:
  Paragraph, Quote/Pullquote, Code/Preformatted/Poetry, List, Table

Add gap/reference:
  Details, Math, Classic
```

### Q5. Which Media blocks are ready?

```txt
Specimen candidates:
  Cover
  Media & Text

Already implemented:
  Image
  Gallery

Gap/prerequisite:
  Audio
  Video
  File
  Icon
```

### Q6. Which Design blocks need action?

```txt
Keep implemented:
  Buttons
  Columns
  Group/Card
  Separator
  Alignment helper

Gap/reference:
  Accordion
  Row
  Stack
  Grid
  More
  Page Break
  Spacer
```

### Q7. Which Widgets blocks need action?

```txt
Keep:
  Search

Route to prose:
  Custom HTML

Gap/reference:
  Archives, Calendar, Terms List, Categories List, Latest Comments,
  Latest Posts, Page List, RSS, Shortcode, Social Icons, Tag Cloud
```

### Q8. Which Theme/FSE blocks should be notes?

Theme should remain route-note / gap-row only. Suggested representatives:

```txt
Navigation
Query Loop / Post Template
Site Logo / Site Title / Site Tagline
Template Part
Breadcrumbs
Post metadata / comments / terms family
```

### Q9. How should Verse / Poetry be handled?

Keep `core/verse` slug and the visible "Poetry" naming note. Do not rename local classes or infer a new implementation.

### Q10. Are Pattern Overrides and Block Bindings only references?

Yes. Both involve runtime/editor surfaces and server-side filter/API decisions. Route to BACKLOG #21 / future plugin territory.

### Q11. Which Phase 2 route is safest?

Route D + M3.

Close criteria:

```txt
1. catalog source updated
2. styleguide/blocks.html regenerated through publish tooling
3. #blocks-table preserved
4. #blocks-search preserved
5. #blocks-theme preserved
6. no CSS edits
7. no validator edits
8. no Pilot/distributable/template edits
9. generated churn restored outside intended mirror output
10. full validation passes
```

## 11. Phase 2 Recommended Implementation Surface

Expected files:

```txt
added:
  docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-2-IMPLEMENTATION.md

modified:
  products/reference-implementations/axismundi-lab/style-guide-blocks.html
  styleguide/blocks.html
```

Possible but not recommended for Phase 2:

```txt
tools/validators/validate_pilot_computed_styles.js
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/assets/styles/blocks.css
styleguide/stylesheets/blocks.css
```

## 12. Validation Plan

Phase 2/3 should run:

```txt
npm run publish:styleguide
npm run validate:computed
npm run validate:specimen-wall
npm test
git diff --check
browser/runtime smoke against styleguide/blocks.html
```

Phase 3 should also verify:

```txt
#blocks-table exists in source and mirror
#blocks-search exists in source and mirror
#blocks-theme exists in source and mirror
source/mirror hash relationship recorded
unrelated generated churn restored
no console errors
no horizontal overflow
```

## 13. Lock 5 Branch

Recommended branch:

```txt
v3.6.24 overall:    14th self-application
v3.6.24 impl-cycle: 9th
variant:            narrow implementation
```

Reason: Phase 2 is expected to modify source catalog and generated mirror, but not CSS/JS/PHP/validator/Pilot.

If Phase 2 falls back to Route B, impl-cycle remains 8th unchanged.

## 14. Phase 4 Assessment

Phase 4 is not currently recommended.

No Phase 1 finding requires deeper architecture audit because:

```txt
Media risk can be contained through gap/prerequisite rows.
Theme/FSE risk can be contained through route notes.
Validator risk can be contained by preserving existing anchors.
CSS tracked-copy risk can be avoided by not editing CSS.
```

If Phase 2 attempts video isolation, CSS tracked-copy edits, or Theme/FSE implementation, Phase 4 should reopen.

## 15. Memory Candidates

```txt
M7 tracked-copy mirror handling:
  strengthened by a second consecutive catalog cycle using M3.

M11 upstream snapshot freeze:
  watch. v3.6.24 reuses v3.6.23 frozen snapshot and live Blocks List reference.

M12 six-category catalog presentation:
  watch. Full spec may make this stronger after Phase 2/5.

M13 validator anchor preservation:
  folded into M9 already; #blocks-table remains active evidence.

M14 goal-direct pressure vs framework completeness:
  active risk guardrail through R12.
```

## 16. Read-Only Validation

Phase 1 validation commands:

```txt
git status --short --branch
git diff --check
```

Expected status after writing this report:

```txt
## main...origin/main
?? docs/v3.6.24/
```

No implementation file should be modified by Phase 1.
