# v3.6.3 — WP Block Bridge Expansion — Phase 0 Plan

Date: 2026-05-20

## Verdict

v3.6.3 should begin as a plan-first implementation cycle for BACKLOG #41,
consuming v3.6.2 specimen-wall evidence without reopening BACKLOG #43.

The work is intentionally bounded: reset one confirmed core default leak,
bridge three visual parity gaps, and route two semantic decisions without
silently normalizing them into CSS-only fixes.

No implementation files should change until this Phase 0 plan receives review.

## User Request Log

Preserve these requirements as acceptance criteria. Do not abstract them into a
generic "block bridge" lane.

```txt
v3.6.3 — WP Block Bridge Expansion
```

```txt
Read NEXT-SESSION.md §0 order.
```

```txt
First housekeeping: remove/correct mistaken #41/#44 separator ↔ Material
Symbols font cross-link.
```

```txt
Commit housekeeping.
```

```txt
Start v3.6.3 — WP Block Bridge Expansion Phase 0 plan-first.
```

```txt
Do not edit implementation files yet.
```

```txt
After plan doc is written, report it for Opus review.
```

Charter §4 boundary input:

```txt
Theme can:
  - Style core blocks via `theme.json` and CSS.
  - Register block patterns.
  - Register block style variations.
  - Define template parts (header, footer, sidebar, single, archive).
  - Enqueue progressive interaction CSS/JS (ripple, theme toggle, search
    expansion).
  - Provide slots / containers (e.g. `.ax-toc-slot`) for plugin-rendered
    content.
  - Render baseline M3 glyph system via icon font.

Plugin should:
  - Register durable custom blocks (those whose `name` is saved into post
    content and would migrate across themes).
  - Host editor UI: toolbar items, sidebar inspectors, popovers, pickers,
    modals.
  - Persist non-WordPress schema (icon registry, theme settings beyond
    `theme.json`, federation actor records).
  - Parse content (heading extraction for TOC, link inventory, etc.).
  - Integrate external protocols (ActivityPub, IndieWeb, REST APIs).
```

Charter §4 custom-block invariant:

```txt
This is why `axismundi-pilot` (when it is built in v3.4.x) will register
zero custom blocks. Custom blocks are dispatched to `axismundi-icons`,
`axismundi-toc`, `axismundi-activitypub`, etc.
```

Cycle invariants:

```txt
custom block implementation drift = P1
semantic mismatch routing must stay in scope
```

v3.6.2 Phase 5 Methodology Finding framing:

```txt
The v3.6.1 token architecture substantially reaches Tier 1 surfaces; the
remaining work is mostly reset, semantic, visual bridge parity, and fixture
coverage.
```

## Cycle Framing

```txt
Cycle name:
  v3.6.3 — WP Block Bridge Expansion

Primary backlog:
  BACKLOG #41 — WordPress block bridge state and ripple enhancement

Inputs:
  BACKLOG #43 close evidence
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
  docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md

Mode:
  Reset / bridge implementation for confirmed #41 inputs
  Semantic-decision routing and documentation

Not mode:
  Custom block implementation
  Fixture expansion
  Editor compatibility rewrite
  Interpreter Plugin
```

The governing build direction remains:

```txt
Markdown / HTML defaults -> WordPress core block -> core reset -> bridge -> M3 mapping
```

## Evidence Lists

### Reset-only list

```txt
1. core/table
   Finding: table-footer-contrast
   Evidence: tfoot keeps 3px currentColor in light and dark.
   Expected lane: reset first, then verify table header/body/footer computed
   values still match the existing M3 table bridge.
```

### Bridgeable core-block list

```txt
1. core/search
   Finding: search-styleguide-delta
   Expected lane: compare Pilot core/search output with the validated lab
   Search bar module and bridge the visual delta where core/search can support
   it without new schema.

2. core/code
   Finding: code-long-line-overflow
   Expected lane: carry the proven prose.html long-line overflow behavior into
   the WordPress-rendered core/code bridge.

3. core/separator
   Finding: separator-variant-visibility
   Expected lane: map default / wide / dots / inset / middle-inset visibility
   as block style variation CSS. This is not a Material Symbols font issue
   unless later evidence proves a real font dependency.
   Phase 2 entry checkpoint: confirm in `functions.php` which separator block
   style variations are already registered. If a required variation is missing,
   decide explicitly whether Phase 2 may extend `functions.php` registration
   scope or whether v3.6.3 remains CSS-only over existing registered variants.
```

### Semantic-decision list

```txt
1. core/button / core/buttons
   Finding: button-anchor-semantics
   Decision required: link-compatible M3 button bridge vs core/buttons style
   extension boundaries vs plugin/custom-block territory. Mechanical underline
   and user-select leakage may be fixed only after the semantic route is named.

2. core/quote + core/pullquote
   Finding: quote-pullquote-semantics
   Decision required: styling split for blockquote vs figure-wrapped blockquote
   structures, with no silent collapse of quote and pullquote semantics.
```

### Future semantic block candidates

```txt
core/button
core/quote
core/pullquote
core/navigation
core/details
core/media-text
core/cover
core/query / post-template / query-pagination
```

These candidates are not v3.6.3 close blockers unless the implementation phase
needs one of them to preserve a current #41 decision.

## Sub-phase Partition

### Phase 1 — Reset Patch

Scope:

```txt
Reset lane count: 1

1. table-footer-contrast
   - Inventory the current core/table reset and bridge rules.
   - Patch only the footer default leak.
   - Verify default, stripes, header, and footer table computed values in light
     and dark.
```

Exit criteria:

```txt
- table-footer-contrast no longer shows 3px currentColor.
- Existing table default/stripes/header behavior does not regress.
- Axis E/F/G remain PASS.
- validate:specimen-wall remains PASS.
```

### Phase 2 — Bridge Patches

Scope:

```txt
Bridge lane count: 3

1. search-styleguide-delta
2. code-long-line-overflow
3. separator-variant-visibility
```

Expected work:

```txt
- Read current Pilot bridge CSS and lab source surfaces before patching.
- Apply the narrowest selectors that match WordPress core block output.
- Preserve the downstream token graph; no new literal token sources.
- Keep separator visibility independent from Material Symbols font routing.
```

Exit criteria:

```txt
- Each bridge finding has before/after computed or visual evidence.
- Light and dark modes pass on the specimen wall.
- validate:specimen-wall and validate:computed remain PASS.
```

### Phase 3 — Semantic Decisions

Scope:

```txt
Semantic-decision lane count: 2

1. button-anchor-semantics
2. quote-pullquote-semantics
```

Expected work:

```txt
- Create or update a v3.6.3 semantic decision report.
- Decide only what can be decided in theme territory:
  core block style, block pattern, bridge CSS, and progressive theme runtime.
- Route plugin/custom-block requirements explicitly instead of implementing
  them.
- Do not silently ignore semantic mismatches after visual fixes.
```

Exit criteria:

```txt
- core/button has an explicit route before any link-affordance fix is accepted.
- core/quote and core/pullquote have an explicit route before any shared quote
  styling is accepted.
- docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md exists and
  records explicit routes for button-anchor-semantics and
  quote-pullquote-semantics.
- Any plugin/custom-block need is routed to backlog, not implemented.
```

## Files To Read In Phase 1+

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
  Current source bridge for WordPress core blocks.

products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
  Generated/copied Pilot asset bridge; compare after source edits.

products/reference-implementations/axismundi-pilot/functions.php
  Current block style registrations and enqueue order.

products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
  Stable Tier 1 fixture source from v3.6.2.

products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/stylesheets/prose.css
products/reference-implementations/axismundi-lab/modules/search-bar/
  Lab/source references for code, separator, prose, and Search bar parity.

tools/validators/validate_pilot_specimen_wall.js
tools/validators/validate_pilot_computed_styles.js
tools/validators/validate_theme_pilot.py
  Existing gates and Axis E/F/G lock shape.

docs/v3.6.2/*
BACKLOG.md #41 / #44 / #14
  Evidence and routing authority.
```

## Files Expected To Change After GO

Exact filenames may change after implementation discovery, but the expected
write scope is:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
  Source reset/bridge patches for core/table, core/search, core/code, and
  core/separator.

products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
  Regenerated/copied bridge asset after source edits.

tools/validators/validate_pilot_specimen_wall.js
  Additive expected-value checks only if they follow the existing forward-proof
  validator shape and do not weaken the render gate.

docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
  Evidence, decisions, and QA records.
```

Avoid unless explicitly approved:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
styleguide/
```

## Dependency Assumptions

```txt
wp-env:
  Required for actual WordPress rendering.

WordPress version:
  .wp-env.json currently pins WordPress/WordPress#6.9.4. v3.6.3 validates that
  environment only.

Pilot theme:
  axismundi-pilot remains a theme-only proof. No custom block registration.

Token graph:
  md-ref -> md-sys -> wp-preset / wp-custom / comp -> consumers
  remains downstream-only.

Specimen wall:
  v3.6.2 Tier 1 fixture is the input. Coverage expansion belongs to #44 unless
  explicitly promoted.
```

## Applicable Gates

From `docs/v3.5.0/PROMOTION-CRITERIA.md`, applied by analogy to the Pilot
bridge surface:

```txt
G1:
  validate_theme_pilot.py must remain 1.000 PASS.

G2:
  Baseline component surfaces stay untouched unless explicitly approved.

G4:
  Phase reports and semantic decision records must exist for the work actually
  performed.

G6:
  Light/dark visual QA required for the specimen wall after reset/bridge work.

G10:
  Findings and decisions must be recorded in docs, not left in chat.

G15:
  WordPress mapping claims require actual WP-rendered evidence.

G20:
  Existing regression gates must remain PASS.
```

## Validator Strategy

Existing locks are mandatory:

```txt
Axis E:
  md-sys color roles must map to md-ref palette tokens.

Axis F:
  WordPress bridge entries must remain downstream single-var projections with
  existing upstream references.

Axis G:
  theme.json settings.custom.axismundi.* must remain downstream-only var()
  leaves.

Specimen render gate:
  npm run validate:specimen-wall must remain PASS.
```

Any new axis must follow the Axis E/F/G forward-proof shape:

```txt
Axis H candidate — WP block bridge expected-value correspondence
  - additive only
  - source-of-truth list is explicit
  - every checked value resolves through existing md-sys / md-ref / comp /
    wp-preset / wp-custom tokens
  - failures identify the block id, property, expected token route, and
    computed value
  - no weakening of Axis E/F/G or validate:computed
```

Validation commands after implementation begins:

```powershell
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
git diff --check
```

Phase 0 validation:

```powershell
git diff --check
```

## Non-Goals

```txt
- Custom block implementation 안 함.
- Do not register custom blocks.
- Do not implement Interpreter Plugin behavior.
- Do not reopen BACKLOG #43.
- Do not expand the specimen fixture for #44 unless explicitly promoted.
- Do not route separator variant visibility through Material Symbols font
  constraints without new evidence.
- Do not silently ignore semantic mismatches.
- Do not collapse core/button anchor semantics into a CSS-only visual patch.
- Do not collapse core/quote and core/pullquote semantics into one generic
  quote style without documenting the route.
- Do not weaken Axis E/F/G, validate:computed, or validate:specimen-wall.
- Do not edit implementation files before this Phase 0 plan receives review.
```

## Risks

```txt
R1 — Semantic drift:
  Button and quote fixes may look mechanical but carry markup/a11y meaning.
  Mitigation: semantic-decision lane stays separate and documented.

R2 — Custom block temptation:
  core/button can make custom block implementation look attractive.
  Mitigation: custom block implementation drift is P1; plugin territory is
  routed, not implemented.

R3 — Reset overreach:
  Table footer reset could regress table header/body/stripes.
  Mitigation: verify the whole table family after the footer patch.

R4 — Bridge/source drift:
  Pilot source bridge and copied asset bridge can diverge.
  Mitigation: regenerate/copy the bridge asset after source CSS edits and use a
  fresh browser context or hard reload for QA.

R5 — Validator overreach:
  Adding expected M3 assertions too broadly could turn #41 into another
  discovery cycle.
  Mitigation: keep any Axis H candidate additive and explicit.
```

## Lesson-lock Candidates

```txt
1. Button semantic decision:
   core/button anchor markup must be routed explicitly before visual fixes are
   accepted as complete.

2. Semantic mismatch handling rule:
   when a WP core block visually maps to M3 but carries divergent markup,
   interaction, or accessibility semantics, the mismatch must be routed as
   semantic-decision or plugin territory; it must not be silently ignored.
```

## Phase 0 Exit Criteria

```txt
- Housekeeping #41/#44/#14 cross-link correction is committed.
- This plan exists under docs/v3.6.3/.
- No implementation files changed during Phase 0.
- Opus review returns GO or all P1 findings are resolved.
```
