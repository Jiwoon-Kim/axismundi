# v3.6.21 Theme Switcher Contract - Phase 0 Plan

## User Request Log

User trigger:

```txt
메모리 저장하고 v3.6.21 Phase 0 GO 하면될듯
```

Interpretation:

- Promote the two v3.6.20 memory candidates into this cycle's standing
  guardrails.
- Start v3.6.21 candidate C: Theme Switcher Contract.
- Do not enter implementation before Phase 1 diagnostic and review.

## Promoted Guardrails

M1:

```txt
Pilot probe != distributable; rename is not promotion.
```

Meaning:

- `products/reference-implementations/axismundi-pilot/` remains a probe.
- Theme Switcher work must not turn Pilot into a distributable.
- Any distributable path still needs explicit user slug / product-name GO.

M2:

```txt
Distributable skeleton creation requires naming, namespace/text-domain,
asset-copy, release-seal, submission, and template-surface prerequisites.
```

Meaning:

- Theme Switcher work must not create a distributable skeleton.
- It may produce evidence for a future skeleton, but not the skeleton itself.

M3 remains watch-only:

```txt
Boundary context != product context.
```

Use it when useful, but do not promote it as a stable memory until it repeats in
the skeleton / release-seal cycle.

## Current Repo State

Ground truth from local git:

```txt
HEAD:   aefb384 Close v3.6.20 pilot vs distributable bootstrap
Branch: main...origin/main = 0/0
Status: clean before this Phase 0 document
```

Recent lineage:

```txt
aefb384 Close v3.6.20 pilot vs distributable bootstrap
663b62c Close v3.6.19 asset surface audit + index
b4ab619 Close v3.6.18 core block mapping audit
4bec70d Remove redundant OGG placeholder audio
6a6d27b Add Opus placeholder audio asset
1eed48a Import placeholder media assets
```

Root meta-doc note:

- `NEXT-SESSION.md`, `CURRENT-STATE.md`, `CHANGELOG.md`, and `ROADMAP.md`
  remain stale past v3.6.18 / v3.6.19 / v3.6.20.
- Do not catch them up inside this Phase 0.
- Root meta-doc catch-up remains a separate maintenance candidate unless the
  user explicitly routes it into a later close.

## Cycle Frame

v3.6.21 asks:

```txt
What is the correct Theme Switcher Contract across lab, Pilot, styleguide,
future distributable theme, and future editor/plugin surfaces?
```

This cycle starts because v3.6.20 closed the Pilot/distributable boundary and
recorded a small but meaningful drift:

```txt
lab theme.js:
  canonical selector in comment = .sg-theme
  .ax-theme-switcher = legacy/archive compatibility
  syncSwitchers() accepts both .sg-theme and .ax-theme-switcher

Pilot header:
  active markup uses .ax-theme-switcher
```

The cycle should decide the contract before touching runtime, templates, or
editor/plugin surfaces.

## Source Inputs

Phase 1 should read:

```txt
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-5-CLOSE.md
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-2-DECISION.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-1-PLAN.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-5-CLOSE.md
docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-3-VISUAL-QA.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
BACKLOG.md #21
BACKLOG.md #22
BACKLOG.md closed #8 / #9 / #12 theme-switcher cohort fixes
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
products/reference-implementations/axismundi-pilot/parts/header.html
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.light.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.dark.css
products/reference-implementations/axismundi-pilot/assets/styles/wp-preset.bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/wp-custom.bridge.css
```

Optional read-only context:

```txt
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js
products/_archive/axismundi-prototype/
```

Only for historical / mirror comparison. Do not edit generated or archived
surfaces.

## Preliminary Evidence

`theme.js` declares:

```txt
§8 Theme switcher (light / dark / auto)
localStorage key: ax-theme
applies [data-theme] on <html>, or removes it for auto
```

`theme.js` comment currently says:

```txt
Canonical:
  <div class="sg-theme" role="radiogroup" aria-label="Theme">
    <button data-theme-set="light" ...>
    <button data-theme-set="dark" ...>
    <button data-theme-set="auto" ...>

Legacy:
  .ax-theme-switcher = archive/axismundi-prototype only
```

Runtime implementation currently accepts:

```txt
.sg-theme, .ax-theme-switcher
```

Pilot active header currently uses:

```html
<div class="ax-theme-switcher" role="radiogroup" aria-label="Theme">
  <button type="button" data-theme-set="light" role="radio" aria-checked="false">Light</button>
  <button type="button" data-theme-set="dark" role="radio" aria-checked="false">Dark</button>
  <button type="button" data-theme-set="auto" role="radio" aria-checked="true">Auto</button>
</div>
```

BACKLOG #22 proposes an explicit 3-state model:

```txt
data-theme="auto"   -> follow OS preference
data-theme="light"  -> force light
data-theme="dark"   -> force dark
```

Current `theme.js` auto behavior removes the `data-theme` attribute rather than
leaving `data-theme="auto"` visible on `<html>`.

## In Scope

Phase 1 diagnostic:

1. Inventory current switcher markup across lab, styleguide source pages, Pilot,
   generated mirror, and archive reference where useful.
2. Inventory current runtime behavior:
   - selector contract;
   - `data-theme-set`;
   - `aria-checked`;
   - `is-selected`;
   - `localStorage("ax-theme")`;
   - root `[data-theme]` mutation;
   - `prefers-color-scheme` handling.
3. Diagnose `.sg-theme` vs `.ax-theme-switcher` contract drift.
4. Diagnose whether BACKLOG #22 explicit `data-theme="auto"` belongs in this
   cycle, a follow-on, or BACKLOG #21 plugin runtime.
5. Separate front-end visitor preference from editor preview toggle.
6. Identify what belongs to theme-only mode and what belongs to Interpreter
   Plugin territory.
7. Check Lock 1 / Lock 2 implications for any `--wp--preset--*` or token route.
8. Recommend Phase 2 route.

Potential Phase 2, only after Phase 1 review and user GO:

- no-code contract decision;
- narrow docs/comment hygiene;
- narrow selector/markup alignment;
- explicit auto-state implementation;
- or route all implementation to a later plugin/theme cycle.

## Out Of Scope / Non-Goals

This cycle does not:

- implement BACKLOG #21 Interpreter Plugin;
- implement runtime HCT;
- add Material Color Utilities;
- implement PluginSidebar / editor preview toggle;
- sync visitor preference with editor preview state;
- create a distributable skeleton;
- generate release-seal derivatives;
- edit brand assets;
- edit root media assets;
- implement Core Block Catalog split;
- edit D-layer binding files;
- edit generated `styleguide/` mirror directly;
- edit archived prototype files;
- add high-contrast, sepia, dim, or other new variants;
- change WordPress Customizer integration;
- rewrite `theme.json` palette/custom settings without Lock 1 analysis;
- modify `wp-preset` / `wp-custom` bridge direction before Phase 1 evidence;
- update root meta-docs.

## Standing Contract Principles

Visitor preference != author preview:

- Front-end visitor theme preference and editor preview mode must not sync
  persisted state.
- They may share token semantics and mode labels only.

Theme-only mode:

- `settings.color.custom = false` remains the safe default.
- M3 token graph remains upstream in strict M3 mode.
- `theme.json` remains a Gutenberg compatibility contract, not token source.

Plugin territory:

- User-driven color regeneration / HCT belongs to BACKLOG #21.
- Editor sidebar / inspector preview UI belongs to plugin or future editor
  integration, not opportunistic theme code.

Data-theme model:

- `light`, `dark`, and `auto` are the intended mode vocabulary.
- Phase 1 must decide whether the DOM root should visibly carry
  `data-theme="auto"` in theme-only mode now.

Tracked-copy impact:

- Switcher-related CSS / markup contracts may affect lab, Pilot, and generated
  styleguide mirror surfaces.
- Generated styleguide mirror files remain publish-tooling output and must not
  be edited directly.
- If Phase 2 proposes selector, markup, or auto-state changes, it must evaluate
  all affected tracked surfaces before choosing an implementation route.

## Phase 1 Diagnostic Questions

Q1. Where does theme-switcher markup exist today?

- lab source pages;
- module pattern pages;
- style-guide blocks/prose pages;
- generated styleguide mirror;
- Pilot header;
- archive references.

Q1.1. Where does active `.sg-theme` markup exist, if anywhere?

- `style-guide.html`;
- `style-guide-blocks.html`;
- `style-guide-prose.html`;
- module pattern pages;
- generated styleguide mirror.

If `.sg-theme` has no active lab markup, Phase 1 should treat the current
`theme.js` "canonical" comment as a possible stale contract.

Q2. What selector should be canonical?

Candidate answers:

- `.sg-theme` remains lab/styleguide canonical; Pilot changes later.
- `.ax-theme-switcher` becomes product/theme canonical; `.sg-theme` remains
  styleguide-only.
- both are formalized as different surface contracts.
- introduce a new selector and deprecate both old meanings.

Q2.1. Is the `theme.js` "legacy" classification still valid after v3.6.20?

Evaluate three hypotheses:

- A: The comment is outdated; `.ax-theme-switcher` is an active Pilot / future
  product surface.
- B: The comment is valid; Pilot currently uses the wrong selector and needs
  alignment.
- C: Both selectors are active contracts with different owners:
  `.sg-theme` for lab/styleguide, `.ax-theme-switcher` for Pilot / product
  surfaces.

Q3. What is the `data-theme-set` contract?

- Is it stable across lab / Pilot?
- Does it conflict with any old `data-theme-button` surfaces?
- Does keyboard radiogroup behavior remain generic and separate?

Q4. What is the root `[data-theme]` contract?

- Current auto = remove attribute.
- BACKLOG #22 proposed auto = explicit `data-theme="auto"`.
- Which route is safer for current token CSS and validators?

Q5. What storage contract is allowed?

- front-end visitor: localStorage `ax-theme`;
- logged-in user meta: future possibility;
- editor preview: separate state, no sync;
- plugin runtime: future.

Q6. What belongs in theme-only mode vs Interpreter Plugin?

- Theme-only: static light/dark/auto switching?
- Plugin: HCT, custom palette regeneration, editor sidebar, global styles sync?

Q7. Does any route touch Lock 1 or Lock 2?

- `wp-custom` downstream-only;
- `md-sys -> md-ref`;
- no circular `--md-sys <-> --wp--preset` binding.

Q8. Is this an implementation cycle or a decision cycle?

- No-code contract report?
- docs/comment hygiene?
- selector/markup alignment?
- explicit auto-state implementation?

Q9. What validation is required for each route?

- static grep/inventory only;
- PHP lint;
- npm test / computed validation;
- browser/Playwright click checks;
- generated artifact restore.

Q10. Should BACKLOG #22 be closed, narrowed, or kept open?

- If explicit auto-state is not implemented, BACKLOG #22 remains open.
- If only selector contract is decided, BACKLOG #22 remains routed.
- If explicit auto-state lands, close/narrow rules must be recorded.

## Candidate Routes

Route A - No-Code Contract Decision:

- Produce a Theme Switcher Contract decision report only.
- Best if selector/storage/root-state choices require user or Opus review before
  implementation.

Route B - Docs / Comment Hygiene:

- Align comments and docs to current reality without changing behavior.
- Could clarify `.ax-theme-switcher` is active Pilot markup, not archive-only.
- No runtime behavior change.

Route C - Selector / Markup Alignment:

- Decide and apply a canonical selector contract.
- Potentially change Pilot markup or lab comments.
- Requires Phase 1 evidence that behavior stays stable.

Route D - Explicit Auto-State Implementation:

- Implement BACKLOG #22 light/dark/auto root contract in theme-only mode.
- Potentially changes root `[data-theme]` behavior and validators.
- Requires strongest validation and Lock 1/2 review.

Route E - Plugin Boundary Decision:

- No theme runtime change.
- Record that editor preview / HCT / customizable mode belongs to BACKLOG #21.
- Could be layered with Route A.

Route F - Full Interpreter Plugin:

- Rejected for this cycle.
- BACKLOG #21 is explicitly out of scope.

Route G - Distributable / Release Seal:

- Rejected for this cycle.
- v3.6.20 M1/M2 guardrails block skeleton/release-seal scope creep.

## Expected Write Scope

Phase 0:

```txt
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-0-PLAN.md
```

Phase 1:

```txt
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-1-REPORT.md
```

Potential Phase 2:

```txt
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-2-DECISION.md
```

Implementation files are not expected before:

```txt
Phase 1 report
Opus Phase 1 verdict
explicit user Phase 2 execution GO
```

If Phase 2 implementation is approved, write scope must be explicitly declared
before edits.

## Files Not Expected To Change In Phase 1

```txt
products/reference-implementations/axismundi-lab/**
products/reference-implementations/axismundi-pilot/**
products/distributables/**
assets/**
core/**
bindings/**
styleguide/**
tools/**
CHANGELOG.md
ROADMAP.md
BACKLOG.md
CURRENT-STATE.md
NEXT-SESSION.md
```

Phase 1 is read-only except for its report.

## Lock Compliance Plan

Lock 1:

- Preserve `wp-custom` downstream-only semantics.
- Do not alter `settings.custom.axismundi.*` or `--wp--preset--*` routes before
  Phase 1 evidence.

Lock 2:

- Preserve `md-sys -> md-ref`.
- Do not introduce runtime HCT or literal sys-color values.

Lock 3:

- Preserve v3.6.3 `core/button` semantic route.

Lock 4:

- Route surface mismatches through a decision report, not opportunistic
  selector edits.

Lock 5:

- Phase 1 diagnostic must precede implementation.
- Phase 2 requires Opus verdict and user execution GO.

Close-time count framework:

```txt
If Phase 2 outcome = no-code decision only:
  11th clean Lock 5 self-application overall
  implementation-cycle application count: 6 unchanged

If Phase 2 outcome = narrow implementation / docs hygiene:
  11th clean Lock 5 self-application overall
  implementation-cycle application count: 7
```

## Validation Plan

Phase 0:

```txt
git diff --check
```

Phase 1:

```txt
read-only inventory
git status --short --branch
git diff --check
```

Phase 2 / Phase 3, route-dependent:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

If selector/runtime behavior changes, add browser or Playwright click checks for:

- lab/styleguide switcher if in scope;
- Pilot front-end switcher if in scope;
- `localStorage("ax-theme")`;
- root `[data-theme]`;
- `aria-checked` / `.is-selected`.

## Phase Cadence

Phase 0 - this plan.

Phase 1 - diagnostic inventory, read-only:

```txt
implementation files: 0
asset file edits: 0
template edits: 0
runtime edits: 0
theme.json edits: 0
plugin files: 0
```

Phase 2 - decision report or narrow implementation only after Opus verdict and
explicit user GO.

Phase 3 - verification matched to Phase 2 route.

Phase 4 - intentionally unused unless Phase 1 discovers deeper architecture
audit need.

Phase 5 - close report, optional commit / push only after review and explicit
user GO.

## Risks

R1 - Visitor/editor state collapse:

- Risk: syncing front-end visitor preference with editor preview.
- Control: preserve separation principle.

R2 - BACKLOG #21 creep:

- Risk: theme switcher becomes Interpreter Plugin / HCT work.
- Control: plugin territory explicitly out of scope.

R3 - Selector whiplash:

- Risk: `.sg-theme` / `.ax-theme-switcher` renamed without surface inventory.
- Control: Phase 1 inventory first.

R4 - Explicit auto-state breakage:

- Risk: `data-theme="auto"` changes CSS selectors or computed validator output.
- Control: no auto-state implementation without Phase 1 token/runtime evidence.

R5 - Generated mirror edit:

- Risk: editing `styleguide/` directly.
- Control: generated mirror remains read-only.

R6 - Pilot/distributable creep:

- Risk: Pilot switcher work implies distributable skeleton.
- Control: M1 / M2 guardrails active.

R7 - Lock 1 circular binding:

- Risk: route writes `--wp--preset--*` or `--md-sys-*` in the wrong direction.
- Control: explicit Lock 1 / Lock 2 review.

R8 - Meta-doc catch-up creep:

- Risk: root handoff maintenance enters Phase 0/1.
- Control: separate maintenance candidate.

R9 - Overclosing BACKLOG #22:

- Risk: selector decision is mistaken for explicit 3-state close.
- Control: Phase 1 must classify #22 close/narrow/open.

R10 - Browser validation under-specified:

- Risk: runtime route closes without click/root-state verification.
- Control: add browser/Playwright checks if runtime changes.

## Review Request

Opus review requested:

```txt
P1: Is v3.6.21 Theme Switcher Contract the correct next route after v3.6.20?
P2: Are Phase 1 diagnostic questions sufficient for selector, root-state,
    storage, visitor/editor separation, and plugin-boundary decisions?
P3: Are candidate routes / non-goals / Lock 5 count framing acceptable before
    Phase 1 execution?
```

Phase 1 execution must wait for explicit user GO after review.

## Next

Submit this Phase 0 plan for Opus review.

Do not edit runtime, template, token, plugin, generated mirror, or meta-doc
files until Phase 0 receives GO and the user explicitly authorizes Phase 1
execution.
