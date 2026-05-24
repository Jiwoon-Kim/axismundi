# v3.6.23 Core Block Catalog Split - Phase 5 Close

Status: CLOSED FOR REVIEW
Date: 2026-05-24
Cycle: v3.6.23
Phase: 5 - close
Route closed: E + M3

## Verdict

v3.6.23 closes the **Core Block Catalog 6-category shell split**.

It does **not** close the full Core Block Style Guide specification.

```txt
Closed:
  - category-aware shell for Text / Media / Design / Widgets / Theme
  - source + generated mirror update through M3 publish tooling
  - #blocks-table validator anchor preservation
  - v3.6.18 Layer 3 follow-on execution baseline

Not closed:
  - full specimen completeness
  - deeper Media coverage
  - Theme/FSE specimen coverage
  - distributable skeleton
  - templates / patterns
  - release seal / wp.org readiness
```

## Cycle Documents

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-0-PLAN.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-1-REPORT.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-3-VERIFICATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-5-CLOSE.md
```

No cross-cutting non-cycle document was added in v3.6.23.

## Implementation Surface

Modified implementation / generated mirror files:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
styleguide/blocks.html
```

Added cycle docs:

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-0-PLAN.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-1-REPORT.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-3-VERIFICATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-5-CLOSE.md
```

Expected close commit scope:

```txt
7 files total
commit message:
  Close v3.6.23 core block catalog 6-category split
```

## Closed Decisions

1. `style-guide-blocks.html` now follows the WordPress Block Inserter category
   frame:

   ```txt
   Text / Media / Design / Widgets / Theme
   ```

2. The main section order now matches the category frame:

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
     Theme blocks route
   ```

3. Table moved from Design to Text while preserving the validator anchor:

   ```txt
   #blocks-table
   ```

4. Image / Gallery moved from Design to Media.

5. Search was added as the sole Widgets specimen because `blocks.css` already
   defines `is-style-filled-search` and Pilot fixtures already carry the same
   block style.

6. Theme category remains a route-note surface only. No FSE/template behavior
   was implemented.

7. Verse was relabeled as Poetry while preserving the upstream block slug:

   ```txt
   core/verse
   ```

8. Embeds remain excluded pending source, privacy, provider, and responsive
   token policy.

9. Pattern Overrides and Block Bindings remain reference-only inputs routed to
   BACKLOG #21 / future plugin territory.

10. No theme-switcher shell was added. Future shell consistency work must
    inherit the v3.6.21 lab/styleguide contract:

    ```txt
    .sg-theme
    data-theme-button
    style-guide.js
    axismundi.theme
    ```

11. Generated `styleguide/blocks.html` was updated through M3 publish tooling,
    not by treating the mirror as source authority.

12. Unrelated generated publish-surface churn was restored.

## M3 Publish Tooling Close Evidence

`npm run publish:styleguide` rewrites the full publish surface:

```txt
styleguide/README.md
styleguide/index.html
styleguide/prose.html
styleguide/blocks.html
styleguide/stylesheets/**
styleguide/scripts/**
```

v3.6.23 retained only the intended mirror output:

```txt
styleguide/blocks.html
```

Restored unrelated generated churn:

```txt
styleguide/README.md
styleguide/index.html
styleguide/prose.html
styleguide/stylesheets/**
```

This proves the M3 discipline for a narrow-scope cycle inside a wide-scope
generator:

```txt
edit source
run generator
keep intended generated mirror
restore unrelated generated output
verify source/mirror alignment
```

## Source / Mirror Final Evidence

Final hashes:

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

The files are intentionally not byte-identical. The publish mirror includes
the generator banner and rewritten links.

## Validator Anchor Preservation

Phase 1 found that `tools/validators/validate_pilot_computed_styles.js`
depends on:

```txt
styleguide/blocks.html#blocks-table
```

Phase 2 preserved the anchor in source and mirror.

Phase 3 verified:

```txt
source:
  #blocks-table
  #blocks-search
  #blocks-theme

mirror:
  #blocks-table
  #blocks-search
  #blocks-theme

npm run validate:computed:
  PASS
```

This is the v3.6.23 evidence for folding validator-anchor checks into the M9
source-of-authority inventory framework.

## Validation Recap

Phase 3 re-ran the full validation set:

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

Generated D-layer artifacts were restored:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

Browser smoke:

```txt
URL:                 http://127.0.0.1:8765/styleguide/blocks.html
console errors:      0
horizontal overflow: false
nav groups:          Text / Media / Design / Widgets / Theme
anchors:             blocks-table / blocks-search / blocks-theme present
```

## Scope Verification

| Scope item | Status |
|---|---|
| v3.6.18 Layer 3 follow-on executed | PASS |
| Text / Media / Design / Widgets / Theme shell implemented | PASS |
| `#blocks-table` preserved | PASS |
| Search added as only Widgets specimen | PASS |
| Theme route note only | PASS |
| Embeds excluded | PASS |
| Pattern Overrides / Block Bindings not implemented | PASS |
| Theme switcher shell not added | PASS |
| `style-guide-prose.html` unchanged | PASS |
| Pilot files unchanged | PASS |
| Distributable files unchanged | PASS |
| D-layer generated reports restored | PASS |
| `git diff --check` clean | PASS |

## User Reframing After Phase 3

After Phase 3, the user clarified the next strategic step:

```txt
Core Block Style Guide should be completed as a full spec before
distributable skeleton work.
```

This supersedes the earlier reviewer suggestion to move directly from the
v3.6.23 shell split into distributable skeleton bootstrap.

Reason:

```txt
v3.6.23 created the 6-category shell.
It did not finish specimen completeness.

If distributable skeleton starts now, page-template work will absorb unresolved
block specimen and visual-contract decisions.

Adding a full-spec catalog cycle first keeps:
  - catalog decisions in the catalog layer
  - distributable skeleton decisions in the product layer
  - template decisions in the page/pattern layer
```

## Goal Alignment

G1 - style guide to Pilot/theme implementation:

```txt
v3.6.22 close: roughly 20-25%
v3.6.23 close: roughly 30%

reason:
  v3.6.23 gives the block catalog a correct category shell, but the full
  specimen and visual-contract spec is still incomplete.
```

G2 - theme release / wp.org submission:

```txt
v3.6.22 close: roughly 5-10%
v3.6.23 close: roughly 10%

reason:
  v3.6.23 is still an indirect prerequisite. Direct wp.org progress begins
  with distributable skeleton bootstrap after the full block spec closes.
```

## Routed Forward

Priority order after user reframing:

1. **Core Block Style Guide Full Spec** - v3.6.24 candidate, next.

   ```txt
   - specimen completeness across Text / Media / Design / Widgets / Theme
   - variant / gap rows
   - WP 7.0 reference-only handling
   - Media readiness diagnostics
   - validator-anchor preservation
   - no Pilot / distributable / release-seal work
   ```

2. **Distributable skeleton bootstrap** - v3.6.25 candidate.

   Requires user slug GO and the M2 skeleton prerequisite set.

3. **Templates / patterns** - v3.6.26 candidate.

   Uses the full block spec and distributable skeleton as inputs.

4. **Release seal derivatives / wp.org readiness** - v3.6.27+ candidate.

   Includes screenshot, readme, PNG/favicon/plugin/directory assets, and
   submission-readiness review.

Additional routed-forward items:

```txt
Embeds provider/source/privacy/responsive-token policy
deeper Media source/provenance decisions
Theme/FSE template catalog implementation
Pattern Overrides / Block Bindings / custom-block territory (BACKLOG #21)
style-guide-blocks.html / style-guide-prose.html switcher shell consistency
publish/mirror drift monitoring if future generator output differs
WP upstream snapshot refresh cycle
BACKLOG #44 specimen coverage
BACKLOG #46 disabled ripple host hygiene
BACKLOG #47 popover provider hygiene
v3.6.15-v3.6.17 diagnostics policy follow-ons
root handoff meta-doc catchup after v3.6.23 if desired
```

## v3.6.24 Entry Fence

Recommended next trigger:

```txt
v3.6.24 Phase 0 GO - Core Block Style Guide Full Spec
```

Expected IN:

```txt
specimen completeness
variants / explicit gap rows
WP 7.0 reference-only handling
Media readiness diagnostics
validator-anchor preservation
style-guide-blocks.html + generated styleguide/blocks.html
```

Expected OUT:

```txt
Pilot templates
distributable skeleton
release seal derivatives
wp.org submission files
Pattern Overrides / Block Bindings runtime
Embeds provider implementation
theme switcher shell unless explicitly scoped
```

Expected Lock 5 branch:

```txt
likely narrow implementation:
  overall self-application: 14th
  implementation-cycle:     9th
```

## Lock 5 Count Chain

v3.6.23 is the thirteenth clean Lock 5 self-application overall and the eighth
implementation-cycle application.

| Cycle | Overall self-application | Implementation-cycle count | Variant |
|---|---:|---:|---|
| v3.6.17 | 7th | 5th | no-code packaging decision |
| v3.6.18 | 8th | 5th | no-code mapping audit decision |
| v3.6.19 | 9th | 6th | narrow docs hygiene |
| v3.6.20 | 10th | 6th | no-code boundary decision |
| v3.6.21 | 11th | 6th | no-code contract decision |
| v3.6.22 | 12th | 7th | narrow implementation |
| v3.6.23 | 13th | 8th | narrow implementation |

The `de106ab` handoff-doc maintenance commit remains outside the Lock 5 count
chain.

## Phase 4

Phase 4 was intentionally unused.

The recent Phase 4 unused chain is now twelve cycles:

```txt
v3.6.5 / v3.6.6 / v3.6.9 / v3.6.14 / v3.6.16 /
v3.6.17 / v3.6.18 / v3.6.19 / v3.6.20 / v3.6.21 /
v3.6.22 / v3.6.23
```

## Memory Promotion Notes

M7 - tracked copy / mirror handling framework:

```txt
status: PROMOTE candidate, strong
reason:
  v3.6.22 selected M2 for byte-identical tracked copies
  v3.6.23 selected M3 for generator-mediated publish output
  two cycles used two different framework options successfully

candidate memory:
  project-axismundi-tracked-copy-mirror-handling-framework
```

M13 - validator-anchor preservation:

```txt
status: fold into M9 recommended
reason:
  validator anchors are a source-of-authority sub-case

M9 update should add:
  - grep tools/validators for DOM anchor dependencies
  - preserve validator-referenced anchors during IA restructuring
  - include anchor preservation in Phase 3 verification gates
```

M11 - WordPress upstream snapshot freeze pattern:

```txt
status: WATCH
```

M12 - WordPress category catalog presentation contract:

```txt
status: WATCH
```

M10 - trust-but-verify implementation review pattern:

```txt
status: WATCH
evidence added:
  Phase 2 review verified source/mirror sample lines and anchor state.
```

M14 - goal-direct progress vs framework completeness balance:

```txt
status: WATCH
evidence:
  User reframed v3.6.24 from distributable skeleton to full block spec after
  recognizing v3.6.23 as category shell, not full spec.
```

## Close Status

v3.6.23 is ready for a single close commit:

```txt
Close v3.6.23 core block catalog 6-category split
```

Expected commit scope:

```txt
5 cycle docs
1 lab source HTML
1 generated mirror HTML
```
