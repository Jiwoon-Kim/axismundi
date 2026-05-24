# v3.6.24 Core Block Style Guide Full Spec - Phase 2 Implementation

Status: Phase 2 implementation  
Date: 2026-05-24  
Route: D - Source + Mirror Full Spec  
Mirror handling: M3 publish-tooling regeneration

## 1. Verdict

Implemented the v3.6.24 catalog full-spec pass as a bounded catalog-layer change.

```txt
Changed:
  - products/reference-implementations/axismundi-lab/style-guide-blocks.html
  - styleguide/blocks.html

Added:
  - docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-2-IMPLEMENTATION.md

Not changed:
  - validators
  - blocks.css tracked copies
  - Pilot theme files
  - distributable theme files
  - BACKLOG.md
  - root handoff meta-docs
```

## 2. Phase 2 Surface Decision

The Phase 1 recommendation held:

```txt
Route D:
  source catalog + generated mirror

M3:
  edit source, run publish tooling, keep intended mirror output,
  restore unrelated generated churn
```

Explicitly excluded surfaces:

```txt
tools/validators/validate_pilot_computed_styles.js
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/assets/styles/blocks.css
styleguide/stylesheets/blocks.css
```

Decision tree applied during implementation:

```txt
If one specimen needed CSS:
  route as a gap row and keep v3.6.24 Route D.

If one new anchor needed validator coverage:
  stop and consider Route E / Phase 4.

If no CSS/validator need appeared:
  keep Route D.
```

No CSS or validator need appeared.

## 3. Implementation Summary

### Text

Added:

```txt
#blocks-heading
  Implemented specimen.
  Uses H1/H2/H3 semantic heading output backed by v3.6.2 Tier 1 and base.css.

#blocks-text-gaps
  Reference/gap rows:
    - Details
    - Math
    - Classic
```

Kept:

```txt
#blocks-paragraph
#blocks-quote
#blocks-code
#blocks-list
#blocks-table
```

### Media

Expanded `#blocks-media` from Image/Gallery to a bounded Media specimen section:

```txt
Implemented specimens:
  - Image
  - Gallery
  - Cover
  - Media & Text

Gap / external prerequisite rows:
  - Audio
  - Video
  - File
  - Icon
```

Rationale:

```txt
Cover and Media & Text can be represented by existing local image placeholder
surfaces without CSS changes.

Audio, Video, File, and Icon remain outside implementation:
  - Audio native-control contract not closed.
  - Video depends on unresolved Pixabay isolation.
  - File has no local placeholder file asset.
  - Icon is WP 7.0 reference-only in this catalog cycle.
```

### Design

Added:

```txt
#blocks-design-gaps
  Reference/gap rows:
    - Accordion
    - Row / Stack / Grid
    - More / Page Break / Spacer
```

Kept:

```txt
#blocks-alignment
#blocks-separator
#blocks-group
#blocks-button
```

### Widgets

Kept:

```txt
#blocks-search
```

Added inside `#blocks-search`:

```txt
Widget gap rows:
  - Custom HTML routed to style-guide-prose.html
  - Archives / Calendar / Terms List / Categories List
  - Latest Comments / Latest Posts / Page List / RSS
  - Shortcode / Social Icons / Tag Cloud
```

### Theme

Kept:

```txt
#blocks-theme
```

Added:

```txt
Theme gap rows:
  - Navigation
  - Query Loop / Post Template
  - Site Logo / Site Title / Site Tagline
  - Template Part
  - Breadcrumbs
  - Comments / terms / post metadata family
```

No Theme/FSE implementation was added.

## 4. Four-State Classification Applied

DOM pattern applied:

```txt
Implemented specimen:
  <section class="sg-section" id="blocks-{surface}">
  with visible specimen markup.

Reference / gap row:
  <ul> row inside a category section or gap section.

External prerequisite:
  gap row naming the unresolved prerequisite.

Out of scope:
  gap/out-of-scope row or route note.
```

Approximate implementation counts:

```txt
new specimen sections:
  - #blocks-heading

new bounded specimens inside existing Media:
  - core/cover
  - core/media-text

new gap sections:
  - #blocks-text-gaps
  - #blocks-design-gaps

expanded gap rows:
  - Media
  - Widgets
  - Theme
```

## 5. Validator Anchor Preservation

Preserved in source and mirror:

```txt
#blocks-table
#blocks-search
#blocks-theme
```

Post-implementation evidence:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  #blocks-table       line 497
  #blocks-search      line 925
  #blocks-theme       line 961

styleguide/blocks.html
  #blocks-table       line 504
  #blocks-search      line 932
  #blocks-theme       line 968
```

New anchors added:

```txt
#blocks-heading
#blocks-text-gaps
#blocks-design-gaps
```

No validator dependency was added for the new anchors.

## 6. M3 Publish Tooling

Command run:

```powershell
npm run publish:styleguide
```

Result:

```txt
stylesheets/ copied/generated: 45 files
scripts copied:                style-guide.js, theme.js
HTML generated:
  style-guide.html        -> index.html
  style-guide-blocks.html -> blocks.html
  style-guide-prose.html  -> prose.html
README generated
publish surface total: 53 files
```

Unrelated generated churn was restored. Kept generated output:

```txt
styleguide/blocks.html
```

Restored generated output:

```txt
styleguide/README.md
styleguide/index.html
styleguide/prose.html
styleguide/stylesheets/*
```

Removed untracked generated module stylesheets created by the publish run.

## 7. Source / Mirror Hashes

Post-implementation hashes:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
  SHA256 80BBB6F732FA61C630372BAFDAE991C72DA08E0E6CB6C912B7159471B1EB30EA

styleguide/blocks.html
  SHA256 E4C4B81652821F071ED4F4CA9839435443477B73461F1215F8BA727847D7271E
```

The files are intentionally not byte-identical. The mirror contains the publish banner and link rewrites generated by `tools/generators/publish_styleguide.py`.

## 8. Line Endings

`styleguide/blocks.html` was normalized to LF after publish-tooling generation.

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html: CR=0
styleguide/blocks.html: CR=0
```

## 9. Validation Run

Implementation-time validation:

```txt
npm run validate:computed       PASS
npm run validate:specimen-wall  PASS
npm test                        PASS, Axis A-G 1.000
git diff --check                PASS
```

Generated artifact restore:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json restored
bindings/wordpress-material3/pilot_validation_report.md restored
```

Browser/runtime smoke:

```txt
Attempted with the in-app Browser against file:///C:/Users/thaum/dev/axismundi/styleguide/blocks.html.
Browser policy blocked direct file:// navigation.
No browser workaround was used.
Phase 3 should run browser smoke through an allowed local HTTP target if needed.
```

## 10. Lock 5 Branch

Phase 2 selects the narrow implementation branch:

```txt
overall:    14th
impl-cycle: 9th
variant:    narrow implementation
```

Reason:

```txt
HTML source catalog changed.
Generated mirror changed through publish tooling.
CSS/JS/PHP/validator/Pilot surfaces did not change.
```

## 11. Phase 3 Recommendations

Phase 3 should verify:

```txt
1. full validation suite PASS
2. source/mirror anchor preservation
3. source/mirror hash relationship
4. generated churn restoration
5. browser smoke:
   - 0 console errors
   - no horizontal overflow
   - nav links for Heading / Text gaps / Media / Design gaps / Search / Theme
   - anchors work for #blocks-table, #blocks-search, #blocks-theme
6. no CSS/validator/Pilot/distributable files modified
```
