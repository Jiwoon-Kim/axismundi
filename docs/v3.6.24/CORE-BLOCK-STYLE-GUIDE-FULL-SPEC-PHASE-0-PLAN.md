# v3.6.24 Core Block Style Guide Full Spec - Phase 0 Plan

Status: Phase 0 plan  
Date: 2026-05-24  
Route: Core Block Style Guide Full Spec, after v3.6.23 6-category shell  
Expected next gate: Phase 1 read-only diagnostic

## 1. Standing Ground Truth

Current repository state before this plan:

```txt
HEAD              : 2e95804 Close v3.6.23 core block catalog 6-category split
main...origin/main: 0/0
working tree      : clean before v3.6.24 Phase 0
v3.6.23 result    : 6-category Core Block Catalog shell implemented
```

v3.6.23 closed the lab catalog shell, not the full Core Block Style Guide spec. The implemented catalog now has the WordPress-facing category structure:

```txt
Text    : Paragraph / Quote / Code-Preformatted-Poetry / List / Table
Media   : Image / Gallery
Design  : Alignment / Separator / Group-Columns-Card / Buttons
Widgets : Search
Theme   : route note only
Embeds  : excluded
```

That shell is useful, but it is not yet enough to feed distributable templates without rework. v3.6.24 therefore remains in the catalog layer. It does not begin the distributable skeleton.

## 2. Strategic Framing

User reframing after v3.6.23 Phase 3 supersedes the earlier next-step recommendation:

```txt
Previous sequence:
  v3.6.23 catalog shell
  v3.6.24 distributable skeleton

Revised sequence:
  v3.6.23 catalog shell
  v3.6.24 Core Block Style Guide full spec
  v3.6.25 distributable skeleton
  v3.6.26 templates / patterns
  v3.6.27+ release seal / wp.org readiness
```

Reason: going straight to a distributable skeleton would push unfinished catalog questions into product and template cycles. v3.6.24 should close the visual/specimen contract first: which blocks have specimens, which blocks are explicit gaps, and which upstream features are routed forward.

Layer separation:

```txt
Catalog layer      : v3.6.23 / v3.6.24
Product layer      : distributable skeleton, future v3.6.25 candidate
Page/template layer: templates and patterns, future v3.6.26 candidate
Release layer      : derivatives, wp.org readiness, future v3.6.27+ candidate
```

## 3. Definition of "Full Spec"

In v3.6.24, "full spec" does not mean "implement every WordPress core block." It means the catalog becomes explicit enough that future product/template cycles can rely on it.

For each supported category, the catalog should classify relevant blocks into one of these states:

```txt
Implemented specimen:
  A visible Axismundi specimen exists and is intentionally styled or accepted.

Reference / gap row:
  The upstream block exists, but Axismundi does not implement it yet.
  The catalog names the gap and routes it forward without pretending support.

External prerequisite:
  The block needs media, source, privacy, provider, or product-context decisions.

Out of scope:
  The block belongs to prose, embeds, theme/FSE, #21 plugin territory, or a later cycle.
```

The goal is a reliable visual contract, not a broader ontology layer and not a wp.org submission package.

## 4. Source Inputs

### Internal Cycle Sources

```txt
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-0-PLAN.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-1-REPORT.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-2-IMPLEMENTATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-3-VERIFICATION.md
docs/v3.6.23/CORE-BLOCK-CATALOG-SPLIT-PHASE-5-CLOSE.md

docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-2-DECISION.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-5-CLOSE.md

docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-5-CLOSE.md
```

### Local Implementation Sources

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
styleguide/blocks.html
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/assets/styles/blocks.css
styleguide/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
tools/validators/validate_pilot_computed_styles.js
tools/publish_styleguide.py
```

### Memory Guardrails

```txt
project-axismundi-source-of-authority-inventory
project-axismundi-tracked-copy-mirror-handling-framework
project-axismundi-multi-surface-implementation-prerequisites
project-axismundi-theme-switcher-selector-ownership
project-axismundi-theme-switcher-separation
```

### External WordPress Sources

Inherited from v3.6.23:

```txt
Blocks list:
  https://wordpress.org/documentation/article/blocks-list/

Gutenberg block-library snapshot:
  https://github.com/WordPress/gutenberg/tree/trunk/packages/block-library
  v3.6.23 snapshot commit: f0a5c0cd5fa957170608692721b18da336e55328
  captured: 2026-05-24

Auto-generated block handbook proposal:
  https://make.wordpress.org/core/2026/05/05/proposal-auto-generate-block-editor-handbook-docs-from-block-json/

Design tools roster:
  https://make.wordpress.org/core/2026/04/22/roster-of-design-tools-per-block-wordpress-7-0/

Pattern Overrides:
  https://make.wordpress.org/core/2026/03/16/pattern-overrides-in-wp-7-0-support-for-custom-blocks/

Block Bindings:
  https://make.wordpress.org/core/2025/11/12/block-bindings-improvements-in-wordpress-6-9/
```

Phase 1 must decide whether to reuse the v3.6.23 Gutenberg snapshot or freeze a new snapshot. Trunk is a moving target; v3.6.24 evidence must be based on an explicit frozen snapshot.

## 5. Phase 1 Diagnostic Scope

Phase 1 is read-only. It must not edit the catalog, mirrors, CSS, validators, Pilot, distributable theme, or backlog.

Required diagnostic steps:

```txt
1. Apply M9 first:
   - grep prior v3.6.x docs for source-of-authority / authoritative /
     byte-identical / generated mirror / lockstep contracts
   - grep validators for DOM-anchor dependencies
   - confirm #blocks-table, #blocks-search, and #blocks-theme preservation requirements
   - recommended first-pass commands:

```powershell
rg -i "source.{0,10}authority|authoritative|byte.identical|generated mirror|lockstep|publishing surface" docs/
rg -i "id=`"blocks-|#blocks-|querySelector.*blocks" tools/validators/ products/reference-implementations/axismundi-lab/
```

   - cross-reference v3.6.17-v3.6.23 close docs before Phase 2 route selection

2. Confirm generated mirror ownership:
   - inspect tools/publish_styleguide.py
   - confirm source -> styleguide/blocks.html mirror path
   - identify generated churn surfaces from publish tooling

3. Inventory current catalog coverage:
   - current sections
   - current specimens
   - current reference/gap notes
   - category label consistency

4. Inventory supported local styling:
   - blocks.css selectors and variants
   - tracked copy implications if CSS needs edits

5. Inventory Pilot specimen evidence:
   - core-block-specimen-wall fixture
   - pattern/template examples usable as catalog specimen references
   - no Pilot edits

6. Build a category completeness matrix:
   - Text
   - Media
   - Design
   - Widgets
   - Theme
   - Embeds excluded

7. Diagnose Media readiness:
   - image/gallery already present
   - audio/video/file/media-text/cover/icon readiness
   - placeholder asset availability
   - video/Pixabay isolation risk
   - media-source/provenance prerequisites

8. Diagnose Theme/FSE handling:
   - navigation/query/post-template/site-logo/breadcrumbs as reference/gap rows
   - no template implementation
   - no FSE behavior claims

9. Handle WP 7.0 / WP 6.9 deltas:
   - Verse slug vs Poetry title
   - Accordion / Icon / Math / Breadcrumbs reference-only or gap rows
   - Pattern Overrides routed to #21
   - Block Bindings routed to #21

10. Decide validator strategy:
    - preserve existing anchors
    - decide whether new anchors need validator coverage
    - if validator update is needed, Phase 2 surface expands

11. Decide M7 mirror route:
    - M1 source-only
    - M2 dual edit
    - M3 publish-tooling regeneration
    - M4 mirror non-authoritative
    - default expectation: M3, inherited from the v3.6.23 catalog shell cycle
    - M1, M2, or M4 require explicit Phase 1 evidence
    - if M3 is selected, reuse the generated-churn restoration discipline from v3.6.23
```

## 6. Phase 2 Candidate Scope

Phase 2 is gated by Phase 1 findings and user GO.

Likely Phase 2 implementation surface if Route D/E is chosen:

```txt
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-2-IMPLEMENTATION.md
products/reference-implementations/axismundi-lab/style-guide-blocks.html
styleguide/blocks.html
```

Possible additional files if Phase 1 requires them:

```txt
tools/validators/validate_pilot_computed_styles.js
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-pilot/assets/styles/blocks.css
styleguide/stylesheets/blocks.css
```

Phase 2 must preserve:

```txt
#blocks-table
#blocks-search
#blocks-theme
```

Phase 2 must not treat generated mirrors as hand-authored source unless Phase 1 explicitly selects M2 and justifies it.

## 7. Non-Goals

v3.6.24 must not:

```txt
1. Create a distributable skeleton.
2. Choose the distributable slug.
3. Modify Pilot templates, patterns, functions.php, theme.json, or block bridge code.
4. Generate release-seal derivatives.
5. Create wp.org submission files.
6. Implement Pattern Overrides runtime behavior.
7. Implement Block Bindings runtime behavior.
8. Implement BACKLOG #21 interpreter/plugin/editor UI work.
9. Add a theme switcher shell to style-guide-blocks.html unless Phase 1 explicitly routes it.
10. Collapse .sg-theme and .ax-theme-switcher selector ownership.
11. Use .ax-theme-switcher, data-theme-set, or axismundi-pilot-theme in lab catalog surfaces.
12. Reopen v3.6.2 Tier 1 classification.
13. Implement all WP 7.0 new blocks.
14. Add Embeds provider specimens.
15. Fetch remote media assets.
16. Decide Pixabay video isolation.
17. Merge style-guide-prose.html into the block catalog.
18. Move Custom HTML out of the prose route without a separate decision.
19. Edit D-layer generated validation artifacts as source.
20. Update root handoff meta-docs.
21. Add new philosophy or workflow ontology layers.
22. Treat block-library trunk as current truth without a frozen snapshot.
23. Claim wp.org readiness.
```

## 8. Category Starting Matrix

Phase 1 must refine this matrix. It is not yet a Phase 2 implementation order.

### Text

Current:

```txt
Paragraph
Quote / Pullquote
Code / Preformatted / Poetry
List
Table
```

Phase 1 questions:

```txt
- Should Heading be restored as a catalog specimen from v3.6.2 / Pilot evidence?
- Should Math be reference-only as WP 7.0 new block?
- Should Verse remain slug core/verse with visible Poetry title note?
- Are all existing table variants adequately represented?
```

### Media

Current:

```txt
Image / Gallery
```

Phase 1 questions:

```txt
- Can Cover be represented with existing local image assets?
- Can Media & Text be represented without new CSS?
- Are Audio and Video blocked by media/provenance policy?
- Is File blocked by missing placeholder asset?
- Is Icon WP 7.0 reference-only?
```

Media decision tree:

```txt
Audio:
  if local audio placeholder is ready and no unresolved source/provenance issue exists
    -> specimen candidate
  else
    -> gap row + external prerequisite

Video:
  if Pixabay / third-party isolation remains unresolved
    -> gap row + external prerequisite
  v3.6.24 must not decide video isolation.

File:
  if no local placeholder file exists
    -> gap row + external prerequisite
  else
    -> specimen candidate only if no new asset policy is needed

Media & Text:
  if existing image placeholder plus text is enough
    -> specimen candidate
  else
    -> gap row

Cover:
  if existing image placeholder plus overlay text is enough
    -> specimen candidate
  else
    -> gap row

Icon:
  WP 7.0 new block
    -> reference-only / gap row, no implementation
```

### Design

Current:

```txt
Alignment
Separator
Group / Columns / Card
Buttons
```

Phase 1 questions:

```txt
- Are Columns and Group variants complete enough for templates?
- Should Accordion be reference-only as WP 7.0 new block?
- Are Button variants complete against v3.6.2 Tier 1?
- Are Separator variants complete against local CSS and Pilot fixtures?
```

### Widgets

Current:

```txt
Search
```

Phase 1 questions:

```txt
- Is Search complete enough with default and filled forms?
- Should Custom HTML stay routed to style-guide-prose.html?
- Are other widget blocks explicit gap rows or out of scope?
```

### Theme

Current:

```txt
Theme route note only
```

Phase 1 questions:

```txt
- Should Theme remain route-note only?
- Should Navigation / Query / Post Template / Site Logo / Breadcrumbs appear as gap rows?
- How to avoid implying FSE or template support?
```

### Embeds

Current:

```txt
Excluded
```

Phase 1 question:

```txt
- Confirm exclusion remains correct until provider/privacy/source policy exists.
```

## 9. Phase Routes

### Route A - No-Code Decision

Phase 2 writes a decision doc only. No catalog implementation.

Use if Phase 1 finds upstream/category/media/validator conflicts too large for a bounded implementation.

### Route B - Gap Matrix Only

Add explicit reference/gap rows but no new visual specimens.

Use if current local CSS and assets are insufficient for new specimens, but the catalog can still become explicit.

### Route C - Source Catalog Only

Edit `style-guide-blocks.html` only and defer generated mirror publication.

This is usually not preferred because v3.6.23 proved publish tooling can regenerate the mirror.

### Route D - Source + Mirror Full Spec

Edit source catalog, run publish tooling, keep intended `styleguide/blocks.html` output.

Likely route if Phase 1 confirms no validator or CSS changes are needed.

### Route E - Source + Mirror + Validator Anchor Update

Route D plus validator updates.

Use only if Phase 1 finds new anchors must be included in computed-style validation.

### Route F - Source + Mirror + CSS Tracked Copies

Route D/E plus CSS changes across tracked copies.

Use only if Phase 1 proves existing CSS cannot represent needed specimens and the added visual contract is worth expanding the surface.

### Rejected Route G - Product/Template Collapse

Do not combine catalog full spec with distributable skeleton, Pilot templates, wp.org release prep, or release-seal assets.

### Rejected Route H - All Core Blocks Implementation

Do not attempt to implement every upstream core block. Unsupported blocks get explicit reference/gap routing.

## 10. Diagnostic Questions

Phase 1 must answer:

```txt
Q1. Which source-of-authority, mirror, byte-identical, or validator-anchor contracts apply?

Q2. Does v3.6.23's source/mirror publishing route remain valid for v3.6.24?

Q3. Which current specimens are complete enough to become full-spec anchors?

Q4. Which Text blocks need added specimens, gap rows, or routing?

Q5. Which Media blocks are ready now, and which are blocked by assets/provenance?

Q6. Which Design blocks are ready now, and which are WP 7.0 reference-only?

Q7. Which Widgets blocks are ready now, and how is Custom HTML kept in prose?

Q8. Which Theme/FSE blocks should be route notes or gap rows without implementation?

Q9. How should Verse / Poetry and other WP 7.0 name deltas be represented?

Q10. Are Pattern Overrides and Block Bindings only referenced and routed to #21?

Q11. Which Phase 2 route is safest, and what closes the cycle?
```

## 11. Risk Register

```txt
R1. "Full spec" expands into "all WP blocks."
R2. Media specimens require unresolved asset/source/provenance decisions.
R3. Video specimen pulls in Pixabay isolation.
R4. Theme/FSE gap rows imply template/FSE support.
R5. Pattern Overrides / Block Bindings creep into #21 implementation.
R6. Validator anchors drift during IA restructuring.
R7. Publish tooling rewrites more files than intended.
R8. CSS changes trigger tracked-copy expansion.
R9. Gutenberg trunk changes mid-cycle.
R10. v3.6.2 Tier 1 classification gets reopened.
R11. Theme switcher shell is added while catalog full spec is still the focus.
R12. Goal-direct pressure pushes premature distributable skeleton work.
```

## 12. Validation Expectations

If Phase 2 implements catalog changes, Phase 3 should run:

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
- source/mirror hash relationship
- generated churn restored outside intended output
- #blocks-table preserved
- #blocks-search preserved
- #blocks-theme preserved
- category nav works
- no console errors
- no horizontal overflow
```

## 13. Lock 5 Conditional Count

v3.6.24 is expected to be the 14th Lock 5 self-application overall.

Conditional branch:

```txt
No-code decision:
  overall    : 14th
  impl-cycle : 8th unchanged

Narrow docs hygiene:
  overall    : 14th
  impl-cycle : 8th unchanged

Narrow implementation, source + mirror:
  overall    : 14th
  impl-cycle : 9th

Broader implementation, source + mirror + validator/CSS:
  overall    : 14th
  impl-cycle : 9th
  note       : larger surface, stronger validation required
```

v3.6.23 closed as 13th overall / 8th implementation-cycle.

## 14. Phase 4 Policy

Phase 4 remains available if Phase 1 finds architecture-level risk:

```txt
- Media readiness requires third-party asset policy.
- Theme/FSE handling cannot be represented as simple gap rows.
- Validator update changes computed-style contract.
- CSS tracked-copy expansion crosses too many surfaces.
```

Otherwise Phase 4 should remain unused, extending the recent unused chain to 13 consecutive cycles.

## 15. Expected Phase 1 Output

Phase 1 should produce:

```txt
docs/v3.6.24/CORE-BLOCK-STYLE-GUIDE-FULL-SPEC-PHASE-1-REPORT.md
```

Required contents:

```txt
- M9 source-of-authority and validator-anchor inventory
- M7 mirror handling recommendation
- current catalog completeness matrix
- blocks.css support matrix
- Pilot fixture/specimen evidence matrix
- Media readiness matrix
- Theme/FSE gap-row recommendation
- WP 7.0 / WP 6.9 reference-only routing
- Phase 2 route recommendation
- validation plan
```

Phase 1 must end read-only.
