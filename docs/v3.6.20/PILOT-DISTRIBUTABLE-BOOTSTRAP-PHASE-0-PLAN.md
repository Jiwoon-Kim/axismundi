# v3.6.20 Pilot vs Distributable Bootstrap - Phase 0 Plan

## User Request Log

User trigger:

```txt
go
```

Context carried from the post-v3.6.19 strategy review:

```txt
v3.6.20 candidate A:
  Pilot vs distributable theme bootstrap

Recommended dependency order:
  A -> C -> D

Where:
  A = Pilot vs distributable theme bootstrap
  C = Theme switcher contract
  D = Core Block Catalog 6-category split

B = Webdesign-craftsman workflow ontology can run in parallel, but is not this
cycle's primary route.
```

Interpretation:

- `go` is treated as approval to start v3.6.20 Phase 0 for candidate A.
- This Phase 0 plan does not authorize implementation.
- Phase 1 must be diagnostic-first.
- Phase 2 requires Opus Phase 1 verdict and explicit user execution GO.

## Current Repo State

Ground truth from local git:

```txt
HEAD:   663b62c Close v3.6.19 asset surface audit + index
Branch: main...origin/main = 0/0
Status: clean before this Phase 0 document
```

Recent lineage:

```txt
663b62c Close v3.6.19 asset surface audit + index
b4ab619 Close v3.6.18 core block mapping audit
4bec70d Remove redundant OGG placeholder audio
6a6d27b Add Opus placeholder audio asset
1eed48a Import placeholder media assets
8345734 Normalize lab user-select prefixes
26d2325 Close v3.6.17 WP ripple runtime packaging
0142dbb Close v3.6.16 lab a11y diagnostics fix
```

Stale root handoff note:

- `NEXT-SESSION.md`, `CURRENT-STATE.md`, `CHANGELOG.md`, and `ROADMAP.md`
  still describe v3.6.18-era state in several places.
- v3.6.19 close is authoritative through commit `663b62c` and
  `docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-5-CLOSE.md`.
- Do not "fix" stale root meta-docs during Phase 0. Route any meta-doc update
  to Phase 5 close if the cycle proceeds.

## Cycle Frame

v3.6.20 asks:

```txt
What is the next correct theme-bootstrap move after v3.6.18 mapped the block
surface and v3.6.19 indexed asset surfaces?
```

The candidate implementation direction is not assumed. Phase 1 must diagnose
whether the next move should be:

- harden the existing `axismundi-pilot` probe;
- start a distributable theme skeleton under `products/distributables/themes/`;
- keep Pilot and distributable tracks separate but define a build-copy path;
- or stop at a decision report if evidence shows the bootstrap boundary is not
  ready for implementation.

This is an E-layer product architecture cycle. It is not a design refresh, not
a release-seal derivative cycle, not a theme switcher implementation cycle, and
not a core-block catalog split cycle.

## Why This Comes First

The user clarified the brand-source / release-seal distinction:

```txt
assets/brand/*.svg:
  complete project identity source assets

release seal:
  deployment derivative lock timing only
  favicon / PNG exports / screenshot.png / README hero / plugin icon /
  WordPress.org assets
```

Those derivatives depend on knowing the theme/plugin context:

- Pilot probe or distributable theme?
- Theme slug and namespace?
- WordPress.org theme submission shape?
- Plugin icon surface, if BACKLOG #21 later produces a plugin?
- Screenshot content and template context?

Therefore theme-bootstrap context must be decided before release-seal
derivatives are locked.

## Source Inputs

Phase 1 should read:

```txt
PROJECT-CONTEXT.md
CONSTITUTION.md
BACKLOG.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/ASSET-SURFACE-INDEX.md
docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-5-CLOSE.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-5-CLOSE.md
bindings/wordpress-material3/binding_map.json
products/distributables/README.md
products/distributables/themes/README.md
products/reference-implementations/axismundi-pilot/README.md
products/reference-implementations/axismundi-pilot/style.css
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/templates/
products/reference-implementations/axismundi-pilot/parts/
products/reference-implementations/axismundi-pilot/patterns/
products/reference-implementations/axismundi-pilot/assets/
assets/brand/README.md
assets/media/README.md
assets/LICENSES.md
```

Phase 1 may also inspect:

```txt
tools/generators/build_pilot_assets.py
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
```

Only for dependency context. Do not route this cycle into catalog or prose work.

## Preliminary Inventory

Observed before drafting this plan:

```txt
products/distributables/
  README.md
  plugins/README.md
  themes/README.md
```

`products/distributables/themes/` exists but has no theme entry yet.

`products/distributables/themes/README.md` currently says:

```txt
first entry planned: axismundi-microblog based on theme-pilot after binding
stabilization in v3.2+
```

`products/reference-implementations/axismundi-pilot/` already contains:

```txt
style.css
theme.json
functions.php
README.md
readme.txt
screenshot.png
templates/index.html
templates/page.html
templates/single.html
parts/header.html
parts/footer.html
patterns/button-actions.php
patterns/card-list.php
patterns/hero.php
patterns/prose-sample.php
patterns/search-section.php
assets/
```

Pilot asset surface size at Phase 0 observation:

```txt
42 files, 18,896,051 bytes
```

Existing Pilot claims:

- `axismundi-pilot` is a proof / probe.
- It validates the Material 3 public surface in a real WordPress theme.
- It does not absorb plugin/runtime work into the theme.
- It does not implement custom blocks.
- It does not implement M3 Interpreter Plugin / HCT color panel.
- It is not v4.0 distributable theme graduation.

Existing Pilot header already contains an `.ax-theme-switcher` HTML surface.
This cycle must treat it as inventory evidence, not as authorization to
implement the Theme Switcher Contract follow-on.

## In Scope

Phase 1 diagnostic:

1. Inventory the current Pilot theme skeleton.
2. Inventory the empty distributable theme surface.
3. Compare Pilot probe obligations against distributable obligations.
4. Identify which Pilot files are reusable as source material and which are
   probe-only.
5. Identify bootstrap prerequisites for a distributable theme entry.
6. Decide what asset placement question remains after v3.6.19.
7. Decide which release-seal derivatives are blocked by missing theme context.
8. Decide whether Phase 2 should be no-code, Pilot hardening, distributable
   skeleton bootstrap, or split-track policy.

Potential Phase 2, only after Phase 1 review and user GO:

- decision report only; or
- narrow documentation / README hygiene; or
- narrowly scoped skeleton creation under `products/distributables/themes/`;
  only if Phase 1 evidence and Opus verdict support it.

## Out Of Scope / Non-Goals

This cycle does not:

- generate release-seal derivatives;
- create favicon / PNG exports / screenshot derivative / README hero /
  plugin icon / WordPress.org assets;
- edit `assets/brand/*.svg`;
- edit binary media;
- decide final third-party video isolation beyond recording dependency;
- implement Theme Switcher Contract;
- synchronize front-end visitor preference with editor preview state;
- implement runtime HCT;
- edit BACKLOG #21 Interpreter Plugin implementation;
- implement Core Block Catalog split;
- edit `style-guide-blocks.html`;
- edit `style-guide-prose.html`;
- implement Media catalog work;
- reopen Embeds;
- add Tier 1 blocks beyond v3.6.18 mapping decisions;
- edit D-layer binding files;
- edit `styleguide/` generated mirror;
- register Material Symbols Outlined / Sharp in runtime CSS;
- collapse asset surfaces into one path;
- reopen BACKLOG #41;
- implement BACKLOG #44, #46, or #47.

## Active Guardrails

Release seal:

- Source SVG assets are complete.
- Deployment derivatives remain unlocked until theme/plugin context exists.

Theme switcher separation:

- Visitor preference is not author preview.
- Front-end switcher and editor preview toggle must not share persisted state.
- They may share token architecture only.
- Runtime HCT remains BACKLOG #21 / Interpreter Plugin territory.

Written-material ontology:

- Written craft materials are workflow ontology source, not direct page layout
  source.
- They do not authorize page/template design during this cycle.

Asset path policy:

- `docs/ASSET-SURFACE-INDEX.md` remains authoritative for current asset-surface
  separation.
- This cycle may reference the index.
- Do not consolidate paths without a future architecture cycle that explicitly
  reopens path-as-policy.

## Phase 1 Diagnostic Questions

Q1. What is the current Pilot skeleton?

- Which templates, parts, patterns, assets, and runtime hooks exist?
- Which are probe-only?
- Which are plausible seed material for a distributable theme?

Q2. What does "Pilot is not a distributable" currently mean in repo terms?

- Is it only a status label?
- Or does it imply concrete differences in compatibility, changelog,
  backwards-compatibility guarantees, asset copy policy, naming, readme, and
  WordPress.org packaging?

Q3. What is the current distributable theme surface?

- Does `products/distributables/themes/` contain enough policy to host a first
  theme?
- Classify the old `axismundi-microblog` note as current, stale, or pending
  decision.
- If stale or pending, list first-distributable naming options:
  - `axismundi`;
  - `axismundi-microblog`;
  - `axismundi-pilot-derivative`;
  - deferred decision.
- Identify WordPress.org slug / submission implications for each viable option.

Q4. What is the minimum safe distributable skeleton?

Candidate inventory:

```txt
style.css
theme.json
functions.php
templates/index.html
templates/front-page.html
templates/home.html
templates/single.html
templates/page.html
templates/archive.html
templates/404.html
parts/header.html
parts/footer.html
patterns/
assets/
README/readme.txt
screenshot policy
```

Phase 1 must distinguish required minimum from desirable later work.

Phase 1 must also compare:

```txt
Pilot currently has:
  templates/index.html
  templates/page.html
  templates/single.html

Distributable additional candidates:
  templates/front-page.html
  templates/home.html
  templates/archive.html
  templates/404.html
```

Each additional template must be classified as minimum required, submission /
first-paint risk, or desirable later work.

Q5. What asset policy is needed before bootstrap?

- Should a distributable copy assets from Pilot?
- Should it copy from `core/design-systems/material3/assets/` at build time?
- Should root `assets/brand/` remain source-only?
- Are placeholder media allowed in a distributable, or catalog/specimen only?

Q6. What release-seal derivatives are blocked?

- favicon;
- 512 / 1024 PNG;
- screenshot.png;
- README hero;
- plugin icon;
- WordPress.org assets.

Phase 1 must classify existing Pilot submission-like files:

```txt
products/reference-implementations/axismundi-pilot/screenshot.png
products/reference-implementations/axismundi-pilot/readme.txt
```

Classification options:

- probe placeholder;
- provisional release-seal candidate;
- distribution-ready artifact;
- undecided / needs user decision.

If either file is treated as a release-seal candidate, Phase 1 must identify
whether that conflicts with the current release-seal guardrail.

Q7. What Theme Switcher Contract facts are merely dependencies?

- Existing Pilot header has `.ax-theme-switcher`.
- Front-end/editor state separation is already a guardrail.
- This cycle should record what bootstrap needs, not implement the contract.

Q8. What validation gates would a skeleton need?

Potential evidence:

```txt
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

If Phase 2 creates a distributable skeleton, Phase 3 should add any applicable
theme-specific checks discovered in Phase 1.

Q9. What should happen to stale root meta-docs?

- `NEXT-SESSION.md` / `CURRENT-STATE.md` / `CHANGELOG.md` / `ROADMAP.md`
  currently lag v3.6.19.
- Phase 1 should record that drift.
- Phase 2 should not opportunistically update them unless the route is a
  documentation-only close.
- Phase 5 should update handoff / current state if the cycle closes.

Q10. Which route should Phase 2 take?

Candidate routes are below.

## Candidate Routes

Route A - No-Code Boundary Decision:

- Write a decision report only.
- Decide Pilot vs distributable split without creating files.
- Best if Phase 1 finds unresolved naming, licensing, or packaging questions.

Route B - Pilot Hardening First:

- Keep Pilot as the only active theme surface.
- Make narrow Pilot docs or skeleton hygiene edits.
- Defer distributable creation.
- Risk: postpones release-seal context.

Route C - Distributable Skeleton Bootstrap:

- Create the first narrow distributable theme skeleton under
  `products/distributables/themes/`.
- Keep Pilot as probe source material.
- No release-seal derivatives.
- No theme switcher implementation.
- Risk: naming and asset copy policy must be settled first.

Route D - Split-Track Policy + Minimal Bootstrap:

- Preserve Pilot as reference implementation.
- Create or document a distributable path and build-copy boundary.
- May include minimal skeleton only if Phase 1 proves it can be done without
  visual/design decisions.
- Candidate to evaluate against Q2-Q6.

Route E - Release Seal First:

- Rejected for this cycle.
- Derivatives depend on theme/plugin context and must not lead bootstrap.

Route F - Core Block Catalog First:

- Rejected for this cycle.
- v3.6.18 routed catalog split after mapping, but Pilot/distributable context
  is a prerequisite to avoid rework.

Route G - Theme Switcher First:

- Rejected for this cycle.
- Theme switcher requires the Pilot/distributable boundary and Lock 1 review.

## Expected Write Scope

Phase 0:

```txt
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-0-PLAN.md
```

Phase 1:

```txt
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-1-REPORT.md
```

Potential Phase 2, route-dependent:

```txt
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-2-DECISION.md
```

Implementation files are not expected before Phase 2 receives:

```txt
Phase 1 report
Opus Phase 1 verdict
explicit user Phase 2 execution GO
```

If Phase 2 is no-code, no product files should change.

If Phase 2 is narrow implementation, the write scope must be declared in the
Phase 2 decision before edits begin.

## Files Not Expected To Change In Phase 1

```txt
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
- Do not add CSS overrides for `--wp--preset--*` without explicit Lock 1
  review.

Lock 2:

- Preserve `md-sys -> md-ref` color route.
- No runtime HCT calculation in theme code.

Lock 3:

- Preserve v3.6.3 `core/button` semantic route.

Lock 4:

- Route semantic mismatches through documented boundary decisions, not
  opportunistic template edits.

Lock 5:

- Phase 1 diagnostic must precede implementation.
- Phase 2 requires Opus verdict and user execution GO.

Close-time count framework:

```txt
If Phase 2 outcome = no-code decision only:
  10th clean Lock 5 self-application overall
  implementation-cycle application count: 6 unchanged

If Phase 2 outcome = narrow implementation / docs hygiene:
  10th clean Lock 5 self-application overall
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

Phase 2 / Phase 3, if code or product files change:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

If a new distributable theme skeleton is created, Phase 1 or Phase 2 must add
any extra validation required by that route before Phase 3 closes.

## Phase Cadence

Phase 0 - this plan.

Phase 1 - diagnostic inventory, read-only:

```txt
implementation files: 0
asset file edits: 0
template edits: 0
theme.json edits: 0
release-seal derivative generation: 0
```

Phase 2 - decision report or narrow implementation only after Opus verdict and
explicit user execution GO.

Phase 3 - verification matched to Phase 2 route.

Phase 4 - intentionally unused unless Phase 1 discovers a deeper architecture
audit need that cannot be handled by Phase 2 decision.

Phase 5 - close report, meta-doc updates, commit / push only after review and
explicit user GO.

## Risks

R1 - Pilot/distributable collapse:

- Risk: treating the Pilot probe as a distributable by renaming it.
- Control: Phase 1 must list concrete differences in obligations.

R2 - Release-seal premature lock:

- Risk: generating favicon / PNG / screenshot assets before theme context.
- Control: release-seal derivatives are out of scope.

R3 - Theme switcher scope creep:

- Risk: existing `.ax-theme-switcher` markup invites implementation.
- Control: record as dependency only; route to Theme Switcher Contract.

R4 - Asset copy policy ambiguity:

- Risk: copying Pilot assets into distributable without build-copy policy.
- Control: Phase 1 Q5 must decide route before file edits.

R5 - WordPress.org packaging ambiguity:

- Risk: skeleton choices imply submission shape too early.
- Control: Phase 1 must identify which submission assets are blocked.

R6 - Core Block Catalog rework:

- Risk: catalog split starts before theme context.
- Control: catalog work remains a routed follow-on.

R7 - Stale root handoff docs:

- Risk: older root docs override v3.6.19 close.
- Control: local git / v3.6.19 close docs are authoritative.

R8 - Lock 1 wp-preset override confusion:

- Risk: theme switcher or skeleton work writes `--wp--preset--*` directly in CSS.
- Control: no such edit before explicit Lock 1 analysis.

R9 - `axismundi-microblog` naming drift:

- Risk: existing distributables README names a first theme without current
  validation.
- Control: Phase 1 must classify it as current, stale, or undecided.

R10 - Overlarge Phase 2:

- Risk: bootstrap expands into templates, catalog, media, switcher, and release
  branding all at once.
- Control: Phase 2 write scope must be route-specific and review-gated.

## Review Request

Opus review requested:

```txt
P1: Is v3.6.20 Pilot vs distributable theme bootstrap the correct primary route?
P2: Are Phase 1 diagnostic questions sufficient for Pilot/distributable boundary,
    asset policy, release-seal dependencies, and stale root-doc handling?
P3: Are candidate routes / non-goals / Lock 5 count framing acceptable before
    Phase 1 execution?
```

Phase 1 execution must wait for explicit user GO after review.

## Next

Submit this Phase 0 plan for Opus review.

Do not edit product, asset, template, theme, or meta-doc files until Phase 0
receives GO and the user explicitly authorizes Phase 1 execution.
