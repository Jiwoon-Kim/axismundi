# v3.6.19 Asset Surface Audit + Cross-Reference Index - Phase 0 Plan

## User Request

User raised that these asset-related paths may need one management surface:

```txt
axismundi/compare/brand-assets-research/WordPress-logotype-wmark.svg
axismundi/core/design-systems/material3/assets
```

Immediate framing:

- Do not collapse every asset into one directory.
- Treat this as an asset-surface authority and cross-reference problem.
- Keep the completed Axismundi symbol SVGs as complete source assets.
- Keep "release seal" as a derivative-lock timing concept: favicon, PNG
  exports, screenshot, README hero, plugin icon, and WordPress.org assets are
  not the same as symbol source completion.

## Current Repo State

```txt
HEAD:        b4ab619 Close v3.6.18 core block mapping audit
Branch:      main
Push state:  main...origin/main = 0/0
Worktree:    clean at Phase 0 entry
```

Recent lineage:

```txt
b4ab619 Close v3.6.18 core block mapping audit
4bec70d Remove redundant OGG placeholder audio
6a6d27b Add Opus placeholder audio asset
1eed48a Import placeholder media assets
8345734 Normalize lab user-select prefixes
26d2325 Close v3.6.17 WP ripple runtime packaging
```

## Cycle Frame

v3.6.19 is a diagnostic-first asset-surface audit.

The goal is not "move assets into one folder." The goal is to decide how the
repository names and cross-references asset authorities across:

- project-owned brand / media slots;
- third-party brand research;
- Material 3 runtime fonts / icons;
- Pilot copied assets;
- older reference-pilot assets;
- root license / notice documents.

Expected outcome:

- Phase 1 diagnostic inventory.
- Phase 2 no-code or light-doc decision.
- A future cross-reference index may be routed, but Phase 0 does not authorize
  moving assets, deleting assets, or rewriting runtime references.

## Source Inputs

Primary repo docs:

```txt
NEXT-SESSION.md
CURRENT-STATE.md
PROJECT-CONTEXT.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-5-CLOSE.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-2-DECISION.md
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-5-CLOSE.md
docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-1-CLOSE.md
docs/v3.3.0/* if asset relocation lineage is needed
```

Asset-policy docs:

```txt
LICENSE-MATRIX.md
NOTICE.md
assets/LICENSES.md
assets/brand/README.md
assets/media/README.md
compare/brand-assets-research/README.md
core/design-systems/material3/assets/README.md
core/design-systems/material3/assets/fonts/README.md
core/design-systems/material3/assets/icons/README.md
products/reference-implementations/axismundi-pilot/assets/fonts/README.md
products/reference-implementations/axismundi-pilot/assets/icons/README.md
products/reference-implementations/axismundi-lab/modules/icon-system/docs/SVG-ICON-POLICY.md
products/reference-implementations/axismundi-lab/docs/ARCHITECTURE-BOUNDARIES.md
```

Asset surfaces to inventory:

```txt
assets/
assets/brand/
assets/media/
compare/brand-assets-research/
core/design-systems/material3/assets/
products/reference-implementations/axismundi-pilot/assets/
products/reference-implementations/ontology-theme-pilot/assets/
styleguide/ if publish-mirror asset references are relevant
```

## In Scope

1. Inventory each asset surface by path, file type, ownership, license,
   authority layer, consumer, and distribution status.
2. Distinguish asset-source authority from copied / mirrored / generated /
   publish surfaces.
3. Verify whether documented policies match actual files.
4. Diagnose the Material Symbols style-set situation:
   - `core/design-systems/material3/assets/README.md` says Rounded only.
   - Actual `core/design-systems/material3/assets/icons/` includes Outlined,
     Rounded, and Sharp.
   - Pilot assets also include Outlined, Rounded, and Sharp.
5. Diagnose root placeholder-media notice drift:
   - `audio-placeholder-jazzy-lofi.opus` exists.
   - `audio-placeholder-gwangan-jazzy-lofi.ogg` was removed in `4bec70d`.
   - `NOTICE.md` still says "Opus/Ogg conversion".
6. Decide whether the right follow-on is:
   - an asset index document;
   - README / notice hygiene;
   - Pilot mirror policy;
   - future distributable build-copy policy;
   - or status quo with explicit cross-references.
7. Preserve compare/brand-assets-research as a third-party trademark /
   research-only surface unless Phase 1 finds direct evidence requiring a
   policy correction.

## Out Of Scope / Non-Goals

- No asset moves.
- No asset deletes.
- No SVG source edits.
- No favicon, PNG, screenshot, README hero, plugin icon, or WordPress.org
  derivative generation.
- No release-seal locking.
- No Pilot vs distributable theme bootstrap implementation.
- No theme template / page / pattern work.
- No runtime reference rewrites.
- No `styleguide/` publish regeneration.
- No D-layer binding edits.
- No BACKLOG #21 Interpreter Plugin implementation.
- No BACKLOG #44 specimen coverage.
- No BACKLOG #46 / #47 provider hygiene.
- No reopening BACKLOG #41.
- No external asset re-fetch unless Phase 1 finds a local record that cannot be
  interpreted without checking a source URL.

## Authority Model

Asset surfaces are not interchangeable:

```txt
assets/
  project-owned brand/media slots plus third-party placeholder media records

compare/brand-assets-research/
  third-party brand/trademark research, DO-NOT-SHIP by path policy

core/design-systems/material3/assets/
  design-system runtime authority for Material fonts/icons

products/reference-implementations/axismundi-pilot/assets/
  Pilot product copy / runtime bundle surface

products/reference-implementations/ontology-theme-pilot/assets/
  older reference-pilot product asset surface

styleguide/
  generated publish mirror, not an authoring authority
```

Phase 1 should test this model against the actual repo rather than assuming it
is already true.

## Phase 1 Diagnostic Questions

Q1. What asset surfaces exist, and what files do they currently contain?

Q2. For each surface, what is the authority layer: A/B/C/D/E/F, research,
runtime, copied product bundle, generated mirror, or placeholder slot?

Q3. Which files are project-owned, third-party permissive assets, third-party
trademark/reference assets, or generated/copy artifacts?

Q4. Which README / LICENSE / NOTICE statements are already correct?

Q5. Which statements are stale or contradictory?

Q6. Are Material Symbols Outlined and Sharp intentionally present, or are they
drift from an older broader copy policy?

Q7. Does Pilot reference core Material 3 assets by relative path, keep its own
copy, or do both patterns exist?

Q8a. Is a single repo-wide asset index document warranted?

Q8b. If yes, where should it live?

Q8c. If yes, what should it index?

Q9. Should Phase 2 be no-code decision only, or a narrow documentation hygiene
patch?

Q10. What must remain routed to future cycles: release seal derivatives, Pilot
vs distributable bootstrap, media catalog implementation, and third-party
video isolation?

## Candidate Routes

Route A - No-Code Decision Report:

- Phase 2 only records the authority/index decision.
- Any documentation hygiene routes forward.
- Best if Phase 1 finds broad policy questions or multiple competing routes.

Route B - Narrow Documentation Hygiene:

- Phase 2 may add or update a repo asset index and small README / NOTICE
  corrections.
- No file moves or runtime reference changes.
- Best if Phase 1 finds simple stale text with obvious correction.

Route C - Pilot Mirror Policy Decision:

- Phase 2 records how Pilot copied fonts/icons should relate to
  `core/design-systems/material3/assets/`.
- Implementation still routes forward.
- Best if Phase 1 finds the main drift is mirror/copy policy.

Route D - Defer To Theme Bootstrap:

- Phase 2 records that asset placement cannot be decided before the Pilot vs
  distributable theme bootstrap.
- Best if Phase 1 finds source docs deliberately leave placement unresolved.

Rejected at Phase 0:

- Single-folder asset consolidation.
- Moving third-party brand research into runtime or root project assets.
- Moving M3 runtime assets into root `assets/`.
- Treating completed symbol SVGs as unfinished just because derivatives are not
  release-locked.

## Risks And Controls

R1 - False consolidation:

- Risk: treating all asset paths as equivalent and collapsing policy surfaces.
- Control: Phase 1 must classify authority and distribution status before any
  route recommendation.

R2 - Third-party trademark contamination:

- Risk: WordPress wmark research enters a theme/runtime path.
- Control: compare/brand-assets-research remains DO-NOT-SHIP unless a later
  cycle explicitly changes policy.

R3 - Material Symbols policy drift:

- Risk: README says Rounded-only while actual files include three style sets.
- Control: Phase 1 inventories actual file sets and all references before
  deciding whether this is stale prose or asset drift.

R4 - Pilot copy vs reference confusion:

- Risk: future distributable bootstrap inherits an unclear copy policy.
- Control: Phase 1 compares core assets, Pilot assets, and publish references.

R5 - Release seal scope creep:

- Risk: asset audit turns into favicon / PNG / screenshot / plugin icon work.
- Control: derivatives are out of scope; symbol source completion is separate
  from release-seal derivative locking.

R6 - Placeholder media ownership mixing:

- Risk: project-owned audio/image and third-party video are treated as one
  license bucket.
- Control: Phase 1 keeps per-file provenance and routes third-party video
  isolation as a follow-on decision if needed.

R7 - v3.6.18 mapping audit bleed:

- Risk: media catalog implementation sneaks into asset audit.
- Control: v3.6.19 may decide asset authority/index; it does not implement
  `style-guide-blocks.html` or media catalog pages.

R8 - Notice/license churn:

- Risk: fixing stale notice text before Phase 1 evidence is complete.
- Control: Phase 1 names stale statements; Phase 2 decides whether narrow docs
  hygiene is authorized.

## Expected Write Scope

Phase 0:

```txt
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-0-PLAN.md
```

Phase 1:

```txt
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-1-REPORT.md
```

Possible Phase 2, only after Opus verdict and user GO:

```txt
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-2-DECISION.md
```

Possible narrow docs hygiene, only if Phase 1 + review approve:

```txt
assets/README.md or docs/ASSET-SURFACE-INDEX.md
NOTICE.md
LICENSE-MATRIX.md
surface-local README files
```

Files not expected to change in v3.6.19:

```txt
assets/brand/*.svg
assets/media/**/*
compare/brand-assets-research/*.svg
core/design-systems/material3/assets/**/*
products/reference-implementations/**/assets/**/* binary/font/media files
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/functions.php
styleguide/**/*
```

## Validation Plan

Phase 0:

```txt
git diff --check
```

Phase 1:

```txt
rg --files assets compare/brand-assets-research core/design-systems/material3/assets products/reference-implementations/axismundi-pilot/assets products/reference-implementations/ontology-theme-pilot/assets
git status --short --branch
git diff --check
```

Phase 2 / 3, if documentation-only:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

Generated validator/report churn must be restored before close, matching
v3.6.17-v3.6.18 hygiene.

## Lock Compliance

Lock 1:

- No `wp-custom` relocation.

Lock 2:

- No `md-sys` / `md-ref` edits.

Lock 3:

- No `core/button` semantic route changes.

Lock 4:

- Asset surface authority is routed explicitly before any visual/catalog work.

Lock 5:

- Phase 1 diagnostic must precede Phase 2 decision or docs hygiene.
- Close-time count framework:
  - If Phase 2 outcome is no-code decision only, v3.6.19 is the ninth clean
    Lock 5 self-application overall and the implementation-cycle count remains
    5, matching the v3.6.17 / v3.6.18 no-code variants.
  - If Phase 2 outcome is narrow docs hygiene, v3.6.19 is the ninth clean Lock
    5 self-application overall and the implementation-cycle count advances to
    6.

## Phase Cadence

```txt
Phase 0 - plan
Phase 1 - diagnostic inventory (read-only)
          implementation files: 0
          asset file edits: 0
          README / NOTICE / LICENSE edits: 0
          source URL re-fetch: 0 unless local record is uninterpretable
Phase 2 - decision report or narrow docs hygiene, only after Opus verdict and
          user execution GO
Phase 3 - light verification if Phase 2 changes docs; otherwise verification
          report may be minimal
Phase 4 - intentionally unused unless Phase 1 discovers deeper architecture
          audit need
Phase 5 - close, if the cycle proceeds past Phase 2/3
```

## Review Request

Please review:

1. P1: Is v3.6.19 correctly framed as asset surface audit / cross-reference
   index, not asset consolidation?
2. P2: Are the source inputs and non-goals sufficient to protect
   compare/brand-assets-research, M3 runtime assets, and root project assets?
3. P3: Should Phase 2 allow narrow docs hygiene, or should it be forced to
   no-code decision only?

## Next

Submit this Phase 0 plan for Opus review.

Do not begin Phase 1 diagnostic until Phase 0 receives an Opus verdict and the
user gives explicit Phase 1 execution GO.
