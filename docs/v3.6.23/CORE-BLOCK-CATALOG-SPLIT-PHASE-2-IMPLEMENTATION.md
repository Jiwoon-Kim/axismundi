# v3.6.23 Core Block Catalog Split - Phase 2 Implementation

Status: IMPLEMENTED FOR REVIEW
Date: 2026-05-24
Cycle: v3.6.23
Phase: 2 - implementation
Route: E + M3

## Verdict

Phase 2 implemented the category-aware WordPress Block Catalog split using
Route E + M3:

```txt
Route E:
  catalog split now; deeper Media / Theme / switcher work routed forward

M3:
  edit the lab source, run publish tooling, keep only the intended generated
  blocks mirror output
```

The resulting catalog order is:

```txt
Text:
  Paragraph
  Quote / Pullquote
  Code / Preformatted / Poetry
  List
  Table

Media:
  Image / Gallery

Design:
  Alignment helpers
  Separator
  Columns / Group / Card
  Buttons

Widgets:
  Search

Theme:
  Theme blocks route note
```

Embeds remain excluded.

## Write Scope

Source edit:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
```

Generated mirror kept from `npm run publish:styleguide`:

```txt
styleguide/blocks.html
```

Phase 2 documentation:

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
```

Generated churn restored:

```txt
styleguide/README.md
styleguide/index.html
styleguide/prose.html
styleguide/stylesheets/**
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

## Non-Goals Preserved

```txt
Pilot theme edits:                 0
distributable theme edits:         0
D-layer edits:                     0
theme.json / functions.php edits:  0
asset edits:                       0
Pattern Overrides implementation:  0
Block Bindings implementation:     0
Embeds implementation:             0
theme-switcher shell added:        no
style-guide-prose.html edits:      0
```

## Implementation Details

### Source Catalog Split

The sidebar navigation now uses the official Block Inserter category frame:

```txt
Text
Media
Design
Widgets
Theme
```

The main section order was also changed to match the category frame:

```txt
blocks-paragraph
blocks-quote
blocks-code
blocks-list
blocks-table
blocks-media
blocks-alignment
blocks-separator
blocks-group
blocks-button
blocks-search
blocks-theme
```

### Text Category

The Text category now includes Table and preserves the validator anchor:

```txt
id="blocks-table"
```

The Verse specimen is labeled:

```txt
core/verse - Poetry
```

This records the upstream title change without reopening v3.6.2 Tier 1
classification.

### Media Category

Image and Gallery moved to Media by category label and navigation placement.

No new audio/video/file/media-text specimens were added.

### Design Category

Alignment helpers are preserved as:

```txt
Design - local layout helper
```

Separator, Columns / Group / Card, and Buttons remain Design.

### Widgets Category

Search was added as the only Widgets specimen:

```txt
core/search
is-style-filled-search
```

Reason:

```txt
blocks.css already defines is-style-filled-search
Pilot fixtures already carry the same block style
v3.6.18 routed Search to Widgets as a catalog follow-on
```

This does not open broader widget coverage.

### Theme Category

Theme blocks are represented by a route note only.

No FSE/template behavior was implemented. Navigation, Query, Post Template,
Site Logo, and Breadcrumbs remain future Theme/FSE/template follow-ons.

### Theme Switcher

No switcher shell was added.

If a later shell consistency cycle adds a switcher to block/prose catalog
surfaces, it should inherit v3.6.21:

```txt
selector:    .sg-theme
attribute:   data-theme-button
handler:     style-guide.js
storage key: axismundi.theme
```

Do not use:

```txt
.ax-theme-switcher
data-theme-set
axismundi-pilot-theme
```

## M3 Mirror Handling

`npm run publish:styleguide` was executed and passed.

The generator rewrites the full publish surface. Phase 2 kept the intended
`styleguide/blocks.html` mirror output and restored unrelated generated churn.

Reason:

```txt
v3.6.23 changes only the block catalog source and its block catalog mirror.
Generated README/index/prose/stylesheets churn is not part of this route.
```

Post-implementation hashes:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  sha256: C2947C91D0730FAA0A7A3A9E4AB7E82ADFF66BA0A44B02841D17F241C1D74F44
  lines: 881
  CR bytes: 0

styleguide/blocks.html
  sha256: 2799E2E895AEBE525123C78DF10D540B98AB649EC7FB19B509E71E304AD2C99C
  lines: 888
  CR bytes: 0
```

The files are not byte-identical by design because the publish generator adds
the mirror banner and rewrites links.

## Validator Anchor Preservation

Phase 1 found that `validate_pilot_computed_styles.js` depends on:

```txt
styleguide/blocks.html#blocks-table
```

Phase 2 preserved:

```txt
source:
  products/reference-implementations/axismundi-lab/style-guide-blocks.html
  id="blocks-table"

mirror:
  styleguide/blocks.html
  id="blocks-table"
```

Verification grep also found:

```txt
source:
  #blocks-table
  #blocks-search
  #blocks-theme

mirror:
  #blocks-table
  #blocks-search
  #blocks-theme
```

## Validation

Commands run:

```txt
npm run publish:styleguide
npm run validate:computed
npm run validate:specimen-wall
npm test
git diff --check
```

Results:

```txt
npm run publish:styleguide       PASS
npm run validate:computed        PASS
npm run validate:specimen-wall   PASS
npm test                         PASS, Overall 1.000
  A schema:  1.000
  B theme:   1.000
  C css:     1.000
  D runtime: 1.000
  E tokens:  1.000
  F bridge:  1.000
  G custom:  1.000
git diff --check                 PASS
```

Generated artifacts from `npm test` were restored:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

## Browser Smoke

Local static server:

```txt
http://127.0.0.1:8765/styleguide/blocks.html
```

Browser smoke result:

```txt
error logs: 0
horizontal overflow: false
nav groups:
  Text
  Media
  Design
  Widgets
  Theme

anchors present:
  blocks-table:  true
  blocks-search: true
  blocks-theme:  true
```

Observed section order:

```txt
Text     blocks-paragraph   Paragraph
Text     blocks-quote       Quote & Pullquote
Text     blocks-code        Code / Preformatted / Poetry
Text     blocks-list        List
Text     blocks-table       Table
Media    blocks-media       Image & Gallery
Design   blocks-alignment   Alignment helpers
Design   blocks-separator   Separator - 5 styles
Design   blocks-group       Columns / Group / Card variants
Design   blocks-button      Buttons - 5 style variants
Widgets  blocks-search      Search
Theme    blocks-theme       Theme blocks route
```

## Close Criteria Check

```txt
1. Text / Media / Design / Widgets / Theme split implemented          PASS
2. Embeds excluded                                                     PASS
3. Table moved to Text, #blocks-table preserved                        PASS
4. Image / Gallery moved to Media                                      PASS
5. Existing specimens preserved                                        PASS
6. styleguide/blocks.html regenerated through publish tooling          PASS
7. style-guide-prose.html remains separate                             PASS
8. No Pilot / distributable / D-layer / theme.json / functions edits   PASS
9. WP 7.0 / 6.9 features reference-only / routed forward               PASS
10. Validation passes, including computed-style #blocks-table checks   PASS
```

## Lock 5 Count Branch

Phase 2 selected a narrow implementation branch:

```txt
v3.6.23 expected close:
  overall Lock 5 self-application: 13th
  implementation-cycle count:     8th

reason:
  source lab HTML and generated publish mirror changed in one reviewed pass
```

## Routed Forward

```txt
Embeds provider/source/privacy/responsive-token policy
deeper Media specimens beyond Image / Gallery
Theme/FSE template catalog implementation
Pattern Overrides / Block Bindings / custom-block territory (BACKLOG #21)
style-guide-blocks.html / style-guide-prose.html switcher shell consistency
publish/mirror drift monitoring if future generator output differs
WP upstream snapshot refresh cycle
```
