# v3.6.19 Asset Surface Audit + Cross-Reference Index - Phase 1 Report

## Verdict

Phase 1 diagnostic is complete.

The audit confirms the user's instinct that asset surfaces are cognitively
scattered, but does not support single-folder consolidation.

Recommended Phase 2 route: **Route B - Narrow Documentation Hygiene**.

Expected Phase 2 write scope:

```txt
docs/ASSET-SURFACE-INDEX.md
core/design-systems/material3/assets/README.md
NOTICE.md
LICENSE-MATRIX.md
assets/brand/README.md
```

No asset files, runtime files, theme templates, Pilot code, D-layer files, or
publish mirrors should change.

## Read-Only Constraint

Phase 1 was read-only except for this report.

```txt
implementation files:      0 edits
asset file edits:          0 edits
README / NOTICE / LICENSE: 0 edits
source URL re-fetch:       0
Playwright probes:         0
```

Validation:

```txt
git status --short --branch  PASS
git diff --check             PASS
```

At report time the only working-tree change is the untracked
`docs/v3.6.19/` Phase 0/1 docs.

## Source Inputs Read

```txt
NEXT-SESSION.md
CURRENT-STATE.md
PROJECT-CONTEXT.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
docs/v3.6.18/CORE-BLOCK-MAPPING-AUDIT-PHASE-5-CLOSE.md
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
```

Commands used for inventory:

```txt
rg --files assets compare/brand-assets-research core/design-systems/material3/assets products/reference-implementations/axismundi-pilot/assets products/reference-implementations/ontology-theme-pilot/assets
Get-FileHash ... -Algorithm SHA256
rg -n "Rounded|Outlined|Sharp|Opus|Ogg|Pilot|reference|copy|assets|brand|DO-NOT-SHIP|release-locked" ...
git status --short --branch
git diff --check
```

## Surface Inventory

| Surface | Files | Bytes | Current role |
|---|---:|---:|---|
| `assets/` | 10 | 10,506,457 | Root project-owned brand / placeholder media surface |
| `assets/brand/` | 4 | 69,242 | Axismundi identity source SVGs + README |
| `assets/media/` | 5 | 10,434,792 | Placeholder image/audio/video with per-file provenance |
| `compare/brand-assets-research/` | 2 | 8,420 | Third-party brand / trademark research, DO-NOT-SHIP |
| `core/design-systems/material3/assets/` | 28 | 18,529,746 | Material 3 design-system runtime asset authority |
| `products/reference-implementations/axismundi-pilot/assets/` | 42 | 18,896,051 | Pilot runtime bundle / copied product surface |
| `products/reference-implementations/ontology-theme-pilot/assets/` | 3 | 76,906 | Legacy reference-pilot CSS asset surface |
| `styleguide/` | 35 | 740,952 | Generated publish mirror; references core assets, not an authoring authority |

Notes:

- `assets/` totals include the `assets/brand/` and `assets/media/` subtrees.
- `styleguide/` does not contain copied font/icon binaries; its stylesheet
  references point back to `core/design-systems/material3/assets/`.
- `ontology-theme-pilot/assets/` currently contains only three CSS files and
  should be treated as legacy reference surface unless a future cycle reopens
  it.

## Authority Classification

| Surface | Authority / layer | Distribution posture | Phase 1 finding |
|---|---|---|---|
| `assets/brand/` | Project identity source assets | Future theme/plugin identity use | Keep as root project-owned brand surface |
| `assets/media/` | Placeholder media slots | Future catalog/template/specimen use | Keep per-file provenance; do not treat as one license bucket |
| `compare/brand-assets-research/` | Research-only third-party brand/trademark seed | DO-NOT-SHIP | Keep isolated; do not merge into root assets or runtime |
| `core/design-systems/material3/assets/` | Material 3 runtime asset authority | Shared source for lab / styleguide / future distributables | Keep in design-system layer |
| `axismundi-pilot/assets/` | Pilot product bundle | Runtime copy used by theme.json/functions/assets CSS | Keep as copied bundle until build-copy policy exists |
| `ontology-theme-pilot/assets/` | Legacy reference-pilot surface | Historical / inactive | Freeze unless future cycle explicitly reopens |
| `styleguide/` | Generated mirror | Public publish artifact | Do not edit directly; no authoring authority |

## Drift Findings

### D1 - Material Symbols Top-Level README Is Stale

`core/design-systems/material3/assets/README.md` says:

```txt
Material Symbols Outlined and Sharp are NOT included here — they are
plugin territory (see ROADMAP). The theme ships only Rounded.
```

Actual files:

```txt
core/design-systems/material3/assets/icons/material-symbols-outlined/
core/design-systems/material3/assets/icons/material-symbols-rounded/
core/design-systems/material3/assets/icons/material-symbols-sharp/
```

Each set contains:

```txt
LICENSE.txt
material-symbols-<style>.woff2
source.txt
```

Counter-evidence shows the actual repo policy is more nuanced:

- `core/design-systems/material3/assets/icons/README.md` documents all three
  style sets.
- `LICENSE-MATRIX.md` documents all three style sets.
- `NOTICE.md` documents all three style sets.
- Runtime `fonts.css` registers only `Material Symbols Rounded`.
- Runtime `icons.css` says Outlined / Sharp remain plugin territory.

Conclusion:

This is stale top-level README prose, not binary asset drift. The correct
policy appears to be:

```txt
core design-system asset authority may store all three Material Symbols style
sets; current theme runtime registers only Rounded; Outlined / Sharp remain
plugin or future variation territory until explicitly enabled.
```

### D2 - Root Media Notice Still Mentions Ogg

`NOTICE.md` says:

```txt
assets/media/audio/ contains project-author supplied Suno-generated demo audio
and an Opus/Ogg conversion for theme demo use.
```

`LICENSE-MATRIX.md` says:

```txt
Audio placeholder | Project-author supplied AI-generated Suno demo audio;
Opus/Ogg derivative included
```

Actual files:

```txt
assets/media/audio/audio-placeholder-jazzy-lofi.mp3
assets/media/audio/audio-placeholder-jazzy-lofi.opus
```

No `.ogg` file remains after:

```txt
4bec70d Remove redundant OGG placeholder audio
```

`assets/LICENSES.md` is already correct: it lists MP3 and Opus only.

Conclusion:

This is stale root documentation text. Phase 2 should update `NOTICE.md` and
`LICENSE-MATRIX.md` to say MP3 source/reference plus Opus derivative, not
Opus/Ogg.

### D3 - Pilot Assets Are Byte-Identical Copies, Not Relative References

`core/design-systems/material3/assets/README.md` claims active lab references
core assets by relative path and future distributables copy at build time.

Observed current state:

- `styleguide/stylesheets/fonts.css` references
  `../../core/design-systems/material3/assets/...`.
- Pilot `theme.json` and `functions.php` reference theme-local
  `assets/fonts/...`.
- Pilot `assets/styles/fonts.css` references `../fonts/...`.
- Pilot carries its own copied font/icon binaries.

Full WOFF2 payload comparison:

| File | Same? | SHA256 prefix |
|---|---|---|
| `axismundi-roboto-flex.woff2` | yes | `7B949602F09C` |
| `axismundi-roboto-serif.woff2` | yes | `A371C6F3AC64` |
| `axismundi-roboto-mono.woff2` | yes | `5F88CB0C30FE` |
| `axismundi-roboto-mono-italic.woff2` | yes | `D217CBB659F2` |
| `axismundi-noto-sans-kr.woff2` | yes | `E30A216C624C` |
| `axismundi-noto-serif-kr.woff2` | yes | `6755F05A0837` |
| `material-symbols-rounded.woff2` | yes | `267B2D44D765` |
| `material-symbols-outlined.woff2` | yes | `59F972B1C0DD` |
| `material-symbols-sharp.woff2` | yes | `E1534DEDD465` |

Conclusion:

Pilot currently owns a product-local runtime copy. That is not a defect for a
WordPress theme probe, but it needs to be documented as copy-surface policy
until a future distributable build-copy pipeline exists.

### D4 - Brand Symbol Source Is Complete, Release Seal Is Not

`assets/brand/README.md` says:

```txt
axismundi-symbol.svg is the canonical draft symbol
```

User clarification after v3.6.18:

```txt
The symbol SVG is complete. "Release seal" means derivative lock timing, not
source SVG incompletion.
```

Conclusion:

The README wording should be updated carefully:

- Source SVGs are complete / canonical project identity source assets.
- Release-seal derivatives remain unlocked:
  - favicon;
  - 512 / 1024 PNG exports;
  - screenshot;
  - README hero;
  - plugin icon;
  - WordPress.org assets.

This is a small docs hygiene item, not brand-design work.

### D5 - Third-Party Brand Research Is Correctly Isolated

`compare/brand-assets-research/README.md` is explicit:

```txt
Frozen reference workspace. NOT theme content. NOT for distribution.
```

It also states:

```txt
Third-party brand, social, and wordmark assets must NOT be shipped in the theme
core.
```

The only file is:

```txt
WordPress-logotype-wmark.svg
```

Conclusion:

No move or consolidation should happen here. The correct action is to reference
this surface from a repo-wide asset index, not to relocate it.

### D6 - Root `assets/media/` Correctly Separates Ownership Locally

`assets/LICENSES.md` correctly distinguishes:

- project-owned brand assets;
- WordPress Photo Directory CC0 image by Jiwoon Kim;
- Suno-generated project-author demo audio;
- Pixabay video not project-owned.

`assets/media/README.md` also correctly says:

```txt
They are not a claim that all media assets share one license.
```

Conclusion:

Root media surface is mostly healthy. The stale text is in root NOTICE /
LICENSE-MATRIX, not in `assets/LICENSES.md`.

## Q1-Q10 Answers

### Q1. What asset surfaces exist, and what files do they contain?

Seven meaningful surfaces exist:

1. `assets/brand/` - 3 SVGs + README.
2. `assets/media/` - image, MP3, Opus, video, README.
3. `compare/brand-assets-research/` - README + WordPress wmark seed.
4. `core/design-systems/material3/assets/` - fonts/icons authority, 28 files.
5. `products/reference-implementations/axismundi-pilot/assets/` - copied Pilot
   product bundle, 42 files.
6. `products/reference-implementations/ontology-theme-pilot/assets/` - legacy
   CSS-only surface, 3 files.
7. `styleguide/` - generated publish mirror, 35 files, references core assets.

### Q2. What is the authority layer for each surface?

| Surface | Authority |
|---|---|
| `assets/brand/` | Project identity source |
| `assets/media/` | Project placeholder media + third-party placeholder records |
| `compare/brand-assets-research/` | Research-only / DO-NOT-SHIP |
| `core/design-systems/material3/assets/` | C-layer design-system runtime asset authority |
| `axismundi-pilot/assets/` | E-layer product runtime copy |
| `ontology-theme-pilot/assets/` | Legacy E-layer reference surface |
| `styleguide/` | Generated mirror / Article 12 publish artifact |

### Q3. Which files are project-owned, third-party, trademark/reference, or copies?

Project-owned:

- `assets/brand/*.svg`
- `assets/media/audio/audio-placeholder-jazzy-lofi.mp3`
- `assets/media/audio/audio-placeholder-jazzy-lofi.opus`

Project-author supplied CC0:

- `assets/media/image/image-placeholder-mogu-1024.webp`

Third-party permissive / upstream:

- Material Symbols fonts, Apache 2.0.
- Roboto / Noto fonts, OFL 1.1.
- Pixabay video placeholder, Pixabay Content License.

Third-party trademark/reference:

- `compare/brand-assets-research/WordPress-logotype-wmark.svg`.

Copies / mirrors:

- Pilot font/icon binaries are byte-identical copies of core design-system
  binaries.
- `styleguide/` is a generated publish mirror and references core assets rather
  than copying binaries.

### Q4. Which statements are already correct?

Correct:

- `assets/LICENSES.md` per-file provenance.
- `assets/media/README.md` ownership warning.
- `compare/brand-assets-research/README.md` DO-NOT-SHIP policy.
- `core/design-systems/material3/assets/icons/README.md` all-three-style-set
  inventory.
- `core/design-systems/material3/assets/fonts/README.md` font strategy.
- `NOTICE.md` and `LICENSE-MATRIX.md` Material Symbols all-three-style
  attribution.

### Q5. Which statements are stale or contradictory?

Stale / contradictory:

1. `core/design-systems/material3/assets/README.md` says Outlined / Sharp are
   not included, but they are included.
2. `NOTICE.md` says Opus/Ogg conversion, but only Opus remains.
3. `LICENSE-MATRIX.md` says Opus/Ogg derivative, but only Opus remains.
4. `assets/brand/README.md` says canonical draft; user has clarified source
   SVGs are complete and only derivative release-seal assets remain pending.

### Q6. Are Material Symbols Outlined and Sharp intentional or drift?

Phase 1 cannot prove original intent without rewriting history, but current
evidence says the binaries are now intentional design-system authority files:

- all three are documented in `assets/icons/README.md`;
- all three are listed in `LICENSE-MATRIX.md`;
- all three are listed in `NOTICE.md`;
- all three are copied into Pilot and byte-identical.

The drift is stale top-level README wording and runtime comments that can be
made more precise:

```txt
core stores all three; current theme runtime registers Rounded only; Outlined
and Sharp remain plugin / future variation territory.
```

### Q7. Does Pilot reference core assets or keep a copy?

Pilot keeps a copy.

Evidence:

- `theme.json` uses `file:./assets/fonts/...`.
- `functions.php` uses `get_theme_file_uri( 'assets/fonts/...' )`.
- `assets/styles/fonts.css` uses `../fonts/...` and `../icons/...`.
- copied WOFF2 files are byte-identical to core design-system WOFF2 files.

### Q8a. Is a single repo-wide asset index warranted?

Yes.

Reasons:

- at least seven meaningful asset surfaces exist;
- three small documentation drifts are now confirmed;
- asset authority is distributed across root docs, local READMEs, and cycle
  close docs;
- future theme bootstrap and media catalog work need a quick surface map.

### Q8b. Where should it live?

Recommended:

```txt
docs/ASSET-SURFACE-INDEX.md
```

Rationale:

- It is cross-cutting project documentation, not a root `assets/` README.
- It must describe `compare/`, `core/`, `products/`, and `styleguide/`.
- It should not imply root `assets/` owns every asset.
- It should be stable enough to appear in future reading orders if useful.

Rejected locations:

- `assets/README.md`: too local; would imply root assets are the umbrella.
- `NOTICE.md`: attribution document, not architecture map.
- `PROJECT-CONTEXT.md`: too stable/high-level for an operational surface index.

### Q8c. What should the index contain?

Recommended columns:

```txt
Surface
Authority
Ownership/license class
Ships where
Consumer/reference pattern
Policy notes
Canonical local README
Known drift / follow-on
```

Scope:

- summarize surfaces, not every file;
- link to local READMEs and license sources;
- explicitly mark DO-NOT-SHIP and generated mirror surfaces.

### Q9. Should Phase 2 be no-code decision only or narrow documentation hygiene?

Recommend narrow documentation hygiene.

Reason:

The evidence found small, concrete, low-risk documentation drift with no runtime
or asset movement required. A decision-only report would leave known stale text
in place and force the next cycle to rediscover the same issue.

Recommended Route B writes:

```txt
docs/ASSET-SURFACE-INDEX.md
core/design-systems/material3/assets/README.md
NOTICE.md
LICENSE-MATRIX.md
assets/brand/README.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-2-DECISION.md
```

### Q10. What remains routed to future cycles?

Routed forward:

- release-seal derivatives;
- Pilot vs distributable theme bootstrap;
- media catalog implementation;
- third-party video isolation decision;
- final distributable build-copy policy;
- plugin icon / WordPress.org assets;
- Material Symbols Outlined / Sharp runtime enablement, if ever desired;
- `ontology-theme-pilot/assets/` modernization or cleanup, if ever reopened.

## Route Recommendation

Recommended: **Route B - Narrow Documentation Hygiene**.

Allowed Phase 2 implementation:

- Add `docs/ASSET-SURFACE-INDEX.md`.
- Correct stale M3 top-level asset README wording.
- Correct Opus/Ogg wording in `NOTICE.md` and `LICENSE-MATRIX.md`.
- Correct `assets/brand/README.md` from "canonical draft" to complete source
  asset + release-seal derivative pending.
- Write Phase 2 decision doc.

Forbidden Phase 2 implementation:

- moving any asset;
- deleting any asset;
- editing SVGs, WOFF2s, MP3, Opus, WebP, WebM;
- changing runtime CSS / PHP / theme.json references;
- changing `styleguide/`;
- implementing Pilot vs distributable bootstrap.

Rejected route: single-folder consolidation.

Reason:

Each surface's path carries policy. Moving everything into one folder would
erase the distinction between source identity assets, DO-NOT-SHIP research,
design-system runtime authority, product copies, and generated mirrors.

## Phase 2 Draft Plan

If Opus approves and user gives Phase 2 GO:

1. Create `docs/ASSET-SURFACE-INDEX.md`.
2. Amend `core/design-systems/material3/assets/README.md`:
   - document all three stored Material Symbols style sets;
   - preserve current runtime statement that only Rounded is registered by the
     theme;
   - route Outlined / Sharp runtime enablement to plugin / future variation.
3. Amend `NOTICE.md`:
   - "Opus/Ogg conversion" -> "Opus conversion".
4. Amend `LICENSE-MATRIX.md`:
   - "Opus/Ogg derivative included" -> "MP3 source/reference plus Opus
     derivative included" or equivalent.
5. Amend `assets/brand/README.md`:
   - source SVGs complete;
   - release-seal derivatives pending.
6. Write `docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-2-DECISION.md`.

## Validation Recommendation For Phase 2

Because Phase 2 would be docs-only hygiene:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

Restore validator-generated report churn before Phase 3 / Phase 5, as in
v3.6.17-v3.6.18.

## Lock Compliance

Lock 1:

- Preserved. No `wp-custom` or token relocation.

Lock 2:

- Preserved. No `md-sys` / `md-ref` edits.

Lock 3:

- Preserved. No `core/button` semantic route changes.

Lock 4:

- Preserved. Asset-surface mismatches are routed through authority/index
  decisions before any visual/catalog work.

Lock 5:

- Preserved. Phase 1 diagnostic preceded the Route B recommendation.
- If Phase 2 remains docs hygiene, v3.6.19 should close as the ninth clean Lock
  5 self-application overall and sixth implementation-cycle application.

## Phase 1 Review Request

Please review:

1. P1: Any blockers to Route B narrow documentation hygiene?
2. P2: Are the drift findings complete enough for Phase 2?
3. P3: Should `docs/ASSET-SURFACE-INDEX.md` be the index location, or should
   Phase 2 choose a different surface?

Phase 2 must wait for Opus verdict and explicit user execution GO.
