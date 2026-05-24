# v3.6.23 Core Block Catalog Split - Phase 1 Diagnostic Report

Status: APPROVED FOR REVIEW
Date: 2026-05-24
Cycle: v3.6.23
Phase: 1 - diagnostic inventory
Route under evaluation: Core Block Catalog 6-category split

## Verdict

Phase 1 recommends **Route E + publish-tooling mirror handling**:

```txt
Route E:
  split the catalog now, route deeper Media / Theme / switcher work forward

Mirror handling:
  M3 publish-tooling route, not hand-edited generated mirror

Expected Phase 2 implementation:
  - restructure the source lab catalog:
      products/reference-implementations/axismundi-lab/style-guide-blocks.html
  - regenerate the published mirror through:
      npm run publish:styleguide
  - keep style-guide-prose.html unchanged unless Phase 2 needs a narrow link note
  - preserve validator anchors, especially #blocks-table
```

Phase 1 does **not** recommend adding the theme-switcher shell in v3.6.23.
If a later catalog shell consistency cycle adds a switcher, it should inherit
the v3.6.21 contract: `.sg-theme`, `data-theme-button`, `style-guide.js`, and
`axismundi.theme`.

## Read-Only Lock

Phase 1 preserved the diagnostic lock:

```txt
implementation edits:        0
lab HTML edits:              0
generated mirror edits:      0
Pilot edits:                 0
distributable edits:         0
D-layer edits:               0
theme.json / functions.php:  0
asset edits:                 0
```

The working tree contains only `docs/v3.6.23/` cycle artifacts.

## Source Inputs

Local source inputs:

```txt
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-1-REPORT.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-2-DECISION.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-5-CLOSE.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-5-CLOSE.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-0-PLAN.md
docs/ASSET-SURFACE-INDEX.md
products/reference-implementations/axismundi-lab/README.md
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
styleguide/blocks.html
styleguide/prose.html
tools/generators/publish_styleguide.py
tools/validators/validate_pilot_computed_styles.js
package.json
```

External source inputs:

```txt
WordPress Blocks List
  https://wordpress.org/documentation/article/blocks-list/

WordPress Gutenberg block-library snapshot
  https://github.com/WordPress/gutenberg/tree/trunk/packages/block-library
  snapshot commit: f0a5c0cd5fa957170608692721b18da336e55328
  snapshot date: 2026-05-24

Auto-generated handbook docs proposal
  https://make.wordpress.org/core/2026/05/05/proposal-auto-generate-block-editor-handbook-docs-from-block-json/

Design tools roster for WordPress 7.0
  https://make.wordpress.org/core/2026/04/22/roster-of-design-tools-per-block-wordpress-7-0/

Pattern Overrides in WordPress 7.0
  https://make.wordpress.org/core/2026/03/16/pattern-overrides-in-wp-7-0-support-for-custom-blocks/

Block Bindings improvements in WordPress 6.9
  https://make.wordpress.org/core/2025/11/12/block-bindings-improvements-in-wordpress-6-9/
```

## F1 - v3.6.18 Lineage Confirmed

v3.6.23 is not a new catalog idea. It is the execution candidate for the
v3.6.18 Layer 3 routed-forward decision:

```txt
style-guide-blocks.html should become a category-aware lab catalog
split across Text, Media, Design, Widgets, and Theme.

Embeds remain excluded until explicit source/privacy/provider policy exists.

style-guide-prose.html remains the Markdown / Custom HTML / long-form prose
surface, not the block-category catalog.
```

Phase 1 therefore treats v3.6.23 as a deferred execution cycle, not a new
architecture track.

## F2 - M9 Source-of-Authority Inventory

M9 source-of-authority grep ran first, before implementation planning.

The relevant source / mirror model is:

```txt
source authority:
  products/reference-implementations/axismundi-lab/style-guide-blocks.html
  products/reference-implementations/axismundi-lab/style-guide-prose.html

generated mirrors:
  styleguide/blocks.html
  styleguide/prose.html

publish tooling:
  tools/generators/publish_styleguide.py

npm script:
  npm run publish:styleguide
```

Evidence:

```txt
tools/generators/publish_styleguide.py:
  Per Constitution Article 12: publishing surfaces are mirrors, not authorities.
  STYLE_GUIDES maps:
    style-guide-blocks.html -> blocks.html
    style-guide-prose.html  -> prose.html

styleguide/blocks.html:
  Publishing surface - DO NOT EDIT DIRECTLY.
  Source of truth:
    products/reference-implementations/axismundi-lab/style-guide-blocks.html

styleguide/prose.html:
  Publishing surface - DO NOT EDIT DIRECTLY.
  Source of truth:
    products/reference-implementations/axismundi-lab/style-guide-prose.html

products/reference-implementations/axismundi-lab/README.md:
  style-guide-blocks.html canonical, mirrored to /styleguide/blocks.html
  style-guide-prose.html canonical, mirrored to /styleguide/prose.html
```

Source and mirror HTML files are not byte-identical by design. The generator
adds a publish-surface banner and rewrites links.

Hash / line-count snapshot:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  lines: 824
  sha256: EF9876F0DCAE7BF0D445B2B2D9B47B5C2850AE8A65739A0F61A0C9C94B5F5F33

styleguide/blocks.html
  lines: 831
  sha256: 3886A380428AB5791899AF3E21539CFFAE06DADBCC6CA7EFAA021DBE60D7B3A3

products/reference-implementations/axismundi-lab/style-guide-prose.html
  lines: 499
  sha256: 2C8BE96A0C40E931D78FD2EDB43F8D57D3BC0C49DBDE9F7A67C8962C26AC1A60

styleguide/prose.html
  lines: 506
  sha256: 4A6F58B965CB1A54D77C3FD223E632907F50F24659C9D50D57274ED71BFFC421
```

Phase 2 should therefore avoid hand-editing `styleguide/blocks.html`. Use
publish tooling after source edits.

## F3 - Current Lab Catalog IA

Current `style-guide-blocks.html` sidebar groups:

```txt
Layout:
  #blocks-alignment      Alignment

Text:
  #blocks-paragraph      Paragraph
  #blocks-quote          Quote / Pullquote
  #blocks-code           Code / Verse
  #blocks-list           List

Design:
  #blocks-separator      Separator
  #blocks-table          Table
  #blocks-media          Image / Gallery
  #blocks-group          Columns / Group / Card
  #blocks-button         Buttons
```

Current section IDs:

```txt
blocks-alignment
blocks-paragraph
blocks-quote
blocks-code
blocks-list
blocks-separator
blocks-table
blocks-media
blocks-group
blocks-button
```

Taxonomy drift:

```txt
core/table       currently under Design, should be Text
core/image       currently under Design, should be Media
core/gallery     currently under Design, should be Media
alignment helper currently top-level Layout, should be local Design/Layout helper
core/search      absent, should be Widgets if added
Theme blocks     absent, should be scaffolded / routed without FSE implementation
Embeds           absent, should remain excluded
```

## F4 - Validator Anchor Constraint

`tools/validators/validate_pilot_computed_styles.js` opens:

```txt
styleguide/blocks.html#blocks-table
```

It snapshots:

```txt
#blocks-table .wp-block-table thead
#blocks-table .wp-block-table.is-style-stripes
#blocks-table .wp-block-table:not(.is-style-stripes) tbody tr:first-child td:first-child
#blocks-table .wp-block-table.is-style-stripes tbody tr:nth-child(odd)
#blocks-table .wp-block-table.is-style-stripes tbody tr:nth-child(even)
```

Phase 2 should preserve `#blocks-table` even if the Table section moves from
Design to Text. If Phase 2 changes this anchor, it must update the validator in
the same reviewed pass, which would expand the cycle surface. Phase 1
recommends preserving the anchor.

## F5 - v3.6.2 Tier 1 Cross-Check

v3.6.2 Tier 1 classification remains closed. v3.6.23 must not reopen it.

Tier 1 entries extracted from v3.6.2 evidence:

```txt
core/paragraph
core/heading
core/list
core/quote
core/code
core/table
core/buttons + core/button
core/search
core/separator
core/group
core/columns + core/column
```

Verse / Poetry:

```txt
core/verse appears in the current lab catalog.
The Gutenberg snapshot still uses slug verse but title Poetry.
v3.6.2 Tier 1 evidence does not require reopening.
Phase 2 should label as Code / Preformatted / Poetry (core/verse) or record
the rename in a local note, without treating it as a Tier 1 decision change.
```

WP 7.0 additions:

```txt
Accordion     category design
Icon          category media
Math          category text
Breadcrumbs   category theme
```

These are reference-only in v3.6.23. Do not implement them as new specimens
unless a future cycle explicitly expands catalog coverage.

## F6 - Gutenberg Snapshot Inventory

The Gutenberg block-library snapshot at:

```txt
f0a5c0cd5fa957170608692721b18da336e55328
```

contains 121 `block.json` files under `packages/block-library/src/*/block.json`.

Category counts from the snapshot:

```txt
design:   25
embed:    1
media:   10
reusable: 1
text:    15
theme:   51
widgets: 18
```

Selected block categories:

```txt
Text:
  code
  heading
  list
  math
  paragraph
  preformatted
  pullquote
  quote
  table
  verse (title Poetry)

Media:
  audio
  cover
  file
  gallery
  icon
  image
  media-text
  video

Design:
  accordion
  button
  buttons
  column
  columns
  group
  separator

Widgets:
  html
  search

Theme:
  breadcrumbs
  navigation
  post-template
  query
  site-logo
```

The snapshot is a moving-target freeze. Future upstream changes do not
invalidate v3.6.23 decisions; they require a future refresh cycle.

## F7 - Design Tools and WP 7.0 / 6.9 Feature Routing

The design tools roster is useful as support-matrix context, not as
implementation authorization.

Pattern Overrides in WP 7.0:

```txt
role: reference input only
v3.6.23 action: no implementation
routing: BACKLOG #21 / future custom-block or plugin territory
```

Block Bindings improvements in WP 6.9:

```txt
role: reference input only
v3.6.23 action: no PHP runtime hook implementation
routing: BACKLOG #21 / future plugin territory
```

These inputs may inform future editor/plugin work, but v3.6.23 remains a lab
catalog split cycle.

## F8 - Media and Theme Handling

Media:

```txt
Current lab catalog has Image and Gallery specimens.
v3.6.18 confirmed those can move to Media.
Do not add audio/video/file/media-text specimens in v3.6.23 unless existing
source assets and specimen markup are already proven ready.
Media depth remains a follow-on after source/provenance decisions.
```

Theme:

```txt
Theme blocks are mostly FSE/template surfaces.
Navigation / Query / Post Template / Site Logo / Breadcrumbs should not become
Pilot or distributable templates in v3.6.23.
Phase 2 may add a Theme category route note / placeholder section, but should
not implement FSE behavior.
```

Widgets:

```txt
Search has prior D-layer / Pilot evidence and is absent from the current lab
catalog.
Custom HTML remains routed to style-guide-prose.html, not the block catalog.
Phase 2 may add a Widgets category with Search, or record it as a follow-on if
the implementation surface would expand.
```

## F9 - Prose Surface Remains Separate

`style-guide-prose.html` remains the long-form `.prose` and Markdown-inheritance
surface. It includes prose article structure, headings, mixed scripts, task
lists, blockquotes, code blocks, tables, images/figures, and HR.

Phase 2 should not fold Markdown / Custom HTML / long-form prose cases into the
WordPress Block Catalog split.

## F10 - Theme Switcher Shell

`style-guide-blocks.html` and `style-guide-prose.html` currently have no active
theme-switcher markup.

Phase 1 does not recommend adding a switcher shell in v3.6.23:

```txt
reason:
  category split is already a catalog IA implementation
  switcher shell consistency is a separate UI/shell concern
  adding it would expand the cycle from catalog IA to shell implementation

if reopened later:
  styleguide/lab surface selector: .sg-theme
  attribute vocabulary: data-theme-button
  handler/storage owner: style-guide.js / axismundi.theme
  do not use .ax-theme-switcher / data-theme-set / axismundi-pilot-theme
```

## Q1 - Current Information Architecture

The current catalog is a compact pre-taxonomy catalog with Layout, Text, and
Design group labels. It has 10 section IDs and no Widgets or Theme group.

Answer:

```txt
The source file is ready for category split, but current labels do not match
the official WordPress Block Inserter categories.
```

## Q2 - Generated Mirror Status

Answer:

```txt
styleguide/blocks.html is generated from style-guide-blocks.html.
styleguide/prose.html is generated from style-guide-prose.html.
The mirror is not byte-identical by design because publish_styleguide.py adds
banner comments and rewrites links.

Use M3:
  edit source, then run npm run publish:styleguide.
```

## Q3 - Official Category Target

Answer:

```txt
Text:
  paragraph, heading, list, quote, pullquote, code, preformatted, verse/Poetry,
  table, math reference-only

Media:
  image, gallery, icon reference-only, audio/video/file/media-text follow-on

Design:
  separator, buttons, group, columns, accordion reference-only, alignment helper
  as local layout helper

Widgets:
  search candidate, Custom HTML routed to prose

Theme:
  route note / scaffold only, no FSE implementation

Embeds:
  excluded
```

## Q4 - Current Specimen Reclassification

Answer:

```txt
Paragraph               -> Text
Quote / Pullquote       -> Text
Code / Preformatted     -> Text
Verse / Poetry          -> Text, rename note
List                    -> Text
Table                   -> Text, preserve #blocks-table
Image / Gallery         -> Media
Separator               -> Design
Columns / Group / Card  -> Design
Buttons                 -> Design
Alignment helpers       -> Design local layout helper, not a block category
Search                  -> Widgets candidate, absent in current catalog
Theme blocks            -> Theme route note / future FSE follow-on
```

## Q5 - WP 7.0 Deltas

Q5.1: Is Verse in v3.6.2 Tier 1?

```txt
No evidence that Verse was part of the closed 26-entry v3.6.2 Tier 1 table.
It appears in the current lab catalog.
```

Q5.2: How should Verse -> Poetry be handled?

```txt
Use the current block slug `core/verse` with title note Poetry.
Do not reopen v3.6.2 Tier 1.
```

Q5.3: If Verse is not Tier 1, what happens?

```txt
Keep it as an existing lab catalog specimen under Text.
Record the upstream naming note.
```

Q5.4: How should Accordion / Icon / Math / Breadcrumbs be handled?

```txt
Reference-only in v3.6.23.
Do not implement new specimens.
Route future implementation separately if needed.
```

## Q6 - Media Readiness

Answer:

```txt
Image and Gallery can move to Media immediately.
Do not expand Media into audio/video/file/media-text in this cycle.
Those require source/provenance/specimen readiness and may depend on v3.6.18 /
v3.6.19 media policy follow-ons.
```

## Q7 - Widgets and Theme Handling

Answer:

```txt
Widgets:
  Search is the only strong candidate for v3.6.23 expansion because prior
  Axismundi evidence exists. Custom HTML stays with prose.

Theme:
  Add a category route note or empty scaffold only if useful.
  Do not implement Navigation / Query / Post Template / Site Logo / Breadcrumbs
  as FSE/template behavior in v3.6.23.
```

## Q8 - Prose Boundary

Answer:

```txt
style-guide-prose.html remains separate.
Markdown, Custom HTML, long-form prose inheritance, and prose element styling
do not move into style-guide-blocks.html.
```

## Q9 - Theme Switcher Shell

Answer:

```txt
Do not add the shell in v3.6.23.
If future shell consistency work adds it, inherit v3.6.21:
  .sg-theme
  data-theme-button
  style-guide.js
  axismundi.theme
```

## Q10 - Safest Phase 2 Route

Route evaluation:

```txt
A - no-code decision only
  Not recommended. Phase 1 found enough evidence to execute the split.

B - source-only lab catalog split
  Not recommended. It would leave the published mirror stale.

C - source + generated mirror catalog split
  Acceptable only if generated mirror is updated through publish tooling, not
  manual direct edits.

D - source + mirror + switcher shell
  Not recommended. Switcher shell is separate shell consistency work.

E - split route: catalog now, media/theme/switcher follow-ons
  Recommended. Pair with M3 publish tooling for the mirror.
```

Recommended Phase 2:

```txt
Route E + M3:
  - implement category split in style-guide-blocks.html
  - run npm run publish:styleguide
  - preserve #blocks-table
  - keep style-guide-prose.html separate
  - keep Embeds excluded
  - route Pattern Overrides / Block Bindings to BACKLOG #21
  - route deeper Media / Theme / switcher shell work forward
```

## Q11 - Close Criteria

v3.6.23 should close if Phase 2 and Phase 3 can prove:

```txt
1. style-guide-blocks.html is split by Text / Media / Design / Widgets / Theme.
2. Embeds remains excluded with source/privacy/provider rationale preserved.
3. Table moves to Text while #blocks-table remains stable.
4. Image / Gallery move to Media.
5. Existing specimens keep visual/runtime behavior.
6. styleguide/blocks.html is regenerated through publish tooling.
7. style-guide-prose.html remains the prose route.
8. No Pilot / distributable / D-layer / theme.json / functions.php edits.
9. WordPress 7.0 / 6.9 features are reference-only or routed forward.
10. Validation passes, including publish mirror and computed-style checks.
```

## Phase 2 Recommendation

Phase 1 recommends:

```txt
Route:          E - split route
Mirror:         M3 - publish-tooling regeneration
Switcher:       defer
Prose:          unchanged unless narrow link note becomes necessary
Media:          Image / Gallery now; deeper media follow-on
Widgets:        Search candidate if scoped; Custom HTML stays prose
Theme:          route note / scaffold only; no FSE implementation
Embeds:         excluded
WP 7.0/6.9:     reference-only / BACKLOG #21 routing
Validator:      preserve #blocks-table
Lock 5 branch:  likely narrow implementation if source + generated mirror change
```

Expected Phase 2 write surface:

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
products/reference-implementations/axismundi-lab/style-guide-blocks.html
styleguide/blocks.html          (generated by npm run publish:styleguide)
possibly styleguide/README.md   (only if publish tooling rewrites it)
```

Expected no-edit surface:

```txt
products/reference-implementations/axismundi-lab/style-guide-prose.html
styleguide/prose.html
products/reference-implementations/axismundi-pilot/**
products/distributables/**
bindings/wordpress-material3/**
theme.json
functions.php
assets/**
```

If Phase 2 adds a Search specimen under Widgets, it should document why prior
Search evidence is enough and avoid expanding into new widget coverage beyond
Search.

## Validation Recommendation

Phase 2 / Phase 3 should run:

```txt
npm run publish:styleguide
npm run validate:computed
npm run validate:specimen-wall
npm test
git diff --check
```

Recommended visual smoke:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
styleguide/blocks.html

desktop + mobile:
  no blank page
  sidebar anchors work
  #blocks-table remains reachable
  Text / Media / Design / Widgets / Theme headings are visible
  no incoherent overlap
```

## Memory Candidates

M11 - WordPress upstream snapshot freeze pattern:

```txt
status: watch
evidence: Gutenberg trunk commit hash + snapshot date used for v3.6.23
promotion condition: reuse in another upstream-referenced cycle
```

M12 - WordPress category catalog presentation contract:

```txt
status: watch
evidence: pending Phase 2 implementation
promotion condition: if category split becomes a reusable catalog rule
```

M7 - mirror handling framework:

```txt
status: stronger watch
evidence: v3.6.23 recommends M3 after v3.6.22 used M2
promotion condition: another tracked-copy / mirror cycle confirms reuse
```

## Read-Only Validation

Commands:

```txt
git status --short --branch
rg "#blocks-table|blocks-table|style-guide-blocks|blocks\.html|publish:styleguide" -S .
rg "source-of-authority|source of authority|authoritative|byte-identical|generated mirror|publishing surface|DO NOT EDIT|style-guide-blocks|style-guide-prose" docs products styleguide tools -S
git diff --check
```

Result:

```txt
git status:
  ## main...origin/main
  ?? docs/v3.6.23/

git diff --check:
  PASS
```

No implementation files were changed during Phase 1.
