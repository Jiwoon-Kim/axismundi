# v3.6.20 Pilot vs Distributable Bootstrap - Phase 1 Report

## Verdict

Phase 1 diagnostic is complete.

Recommended Phase 2 route:

```txt
Layered Route A + D:
  Route A = no-code decision report execution shape
  Route D = split-track policy content

No distributable skeleton should be created in Phase 2 unless the user first
confirms the first distributable theme slug / product name.
```

Rationale:

- The current Pilot already works as a block-theme probe and has the minimum
  WordPress-recognition skeleton.
- Repo policy repeatedly states Pilot is not a distributable.
- `products/distributables/themes/` exists but has no concrete theme entry.
- The old `axismundi-microblog` note is stale against current v3.6.20 context.
- `LICENSE-MATRIX.md` points to future `products/distributables/themes/axismundi/`,
  but that has not been user-confirmed as the first distributable slug.
- Release-seal derivatives remain blocked by that missing product context.

Phase 2 should therefore record the split-track policy and route the actual
skeleton creation to a follow-on after naming / slug confirmation.

## Read-Only Constraint

Preserved:

```txt
implementation file edits: 0
asset file edits: 0
template edits: 0
theme.json edits: 0
release-seal derivative generation: 0
```

Only this report is added in Phase 1.

## Commands / Evidence

Local repo:

```txt
git status --short --branch
  ## main...origin/main
  ?? docs/v3.6.20/

HEAD:
  663b62c Close v3.6.19 asset surface audit + index
```

Pilot root files:

```txt
functions.php   11,660 bytes
README.md        1,144 bytes
readme.txt         869 bytes
screenshot.png  21,229 bytes
style.css          601 bytes
theme.json      12,448 bytes
```

Pilot template / part / pattern surface:

```txt
templates/index.html        2,339 bytes
templates/page.html           354 bytes
templates/single.html       1,105 bytes
parts/header.html             802 bytes
parts/footer.html             484 bytes
patterns/button-actions.php 1,227 bytes
patterns/card-list.php      2,070 bytes
patterns/hero.php           1,163 bytes
patterns/prose-sample.php   2,500 bytes
patterns/search-section.php   803 bytes
```

Pilot assets:

```txt
42 files, 18,896,051 bytes

.css      14 files,    364,161 bytes
.js        1 file,       3,923 bytes
.md        2 files,     12,583 bytes
.txt      16 files,     58,628 bytes
.woff2     9 files, 18,456,756 bytes
```

Theme JSON summary:

```txt
palette entries:       24
font families:          5
font sizes:            15
spacing sizes:          6
custom.axismundi:       present
styles:                 present
```

Screenshot:

```txt
products/reference-implementations/axismundi-pilot/screenshot.png
  1200x900
```

Official WordPress sources checked because submission rules are current-policy
surface:

- WordPress Theme Handbook, "Theme Structure":
  https://developer.wordpress.org/themes/core-concepts/theme-structure/
- WordPress Theme Handbook, "Required Theme Files":
  https://developer.wordpress.org/themes/releasing-your-theme/required-theme-files/
- Make WordPress Themes, "Required":
  https://make.wordpress.org/themes/handbook/review/required/

Key current official facts used:

- A block theme is recognized by `style.css` and `templates/index.html`.
- `README.txt`, `functions.php`, `screenshot.png`, and `theme.json` are
  optional for WordPress recognition but relevant for packaging / submission.
- `readme.txt` is required for official theme directory submission.
- Screenshot guidance is 1200 x 900 / 4:3 and not larger than 1200 x 900.
- Theme Review requirements say block themes include `style.css`,
  `readme.txt`, `theme.json`, and `templates/index.html`.
- Theme packages should avoid remote resources without user consent and should
  include resources in the theme zip, with stated exceptions.

## Source Inputs Read

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
products/reference-implementations/axismundi-pilot/readme.txt
products/reference-implementations/axismundi-pilot/templates/
products/reference-implementations/axismundi-pilot/parts/
products/reference-implementations/axismundi-pilot/patterns/
products/reference-implementations/axismundi-pilot/assets/
assets/brand/README.md
assets/media/README.md
assets/LICENSES.md
LICENSE-MATRIX.md
products/README.md
products/reference-implementations/README.md
core/design-systems/material3/assets/README.md
```

## Important Prior Decisions

`PROJECT-CONTEXT.md`:

- Axismundi is not merely a WordPress theme; it is an ontology-driven layered
  architecture for WordPress block themes against interchangeable design
  systems.
- E-layer `products/` contains reference implementations and distributables.

`products/distributables/README.md`:

- Distributables are production-grade, semver-versioned products with stable
  contracts.
- They are suitable for installation in real WordPress sites.
- They consume binding ontology but provide stable extension points.

`products/reference-implementations/README.md`:

- Reference implementations are validation targets, not distributable products.
- They may be torn down and regenerated when ontology shifts.

`products/reference-implementations/axismundi-pilot/README.md`:

- `axismundi-pilot` validates the public surface in a real WordPress theme.
- It does not absorb plugin/runtime work into the theme.
- It does not implement custom blocks.
- It does not implement M3 Interpreter Plugin / HCT color panel.
- It does not implement v4.0 distributable theme graduation.

`docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-1-PLAN.md`:

- `readme.txt` / `screenshot.png` scope was explicitly unclear.
- They were to be treated as draft / placeholder, not WP.org submission
  artifacts.

`docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md`:

- Pilot was explicitly "not a complete distributable theme".
- Token architecture refactor, full block bridge expansion, and full core block
  specimen wall were routed to later work. Those later routes have since
  progressed, but the Pilot/distributable split was not closed.

`docs/ASSET-SURFACE-INDEX.md`:

- `assets/brand/` stores complete project identity source assets.
- Deployment derivatives remain unlocked until Pilot vs distributable context.
- Pilot has product-local copies of M3 assets.
- Future distributable build-copy policy should decide how copies are produced.

`LICENSE-MATRIX.md`:

- Future distributable path is recorded as
  `products/distributables/themes/axismundi/`.
- `readme.txt`, screenshot, and theme tags are deferred to later
  WordPress.org submission prep.

## Q1. Current Pilot Skeleton

The current Pilot skeleton is functional as a WordPress block-theme probe:

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
patterns/*.php
assets/styles/*.css
assets/scripts/pilot-block-bridge.js
assets/fonts/**
assets/icons/**
```

Runtime hooks in `functions.php`:

- theme support: `wp-block-styles`, `editor-styles`, `post-thumbnails`,
  `responsive-embeds`, `html5`;
- `add_editor_style()` for copied Pilot CSS assets when present;
- front-end enqueue stack for fonts, token CSS, base CSS, icons, components,
  blocks, prose, and Pilot bridge CSS;
- `pilot-block-bridge.js` enqueue when present;
- WordPress Font Library collection registration;
- core block style variants for button, group, list, separator, and search;
- three pattern categories:
  - `axismundi-showcase`;
  - `axismundi-composition`;
  - `axismundi-prose`.

Current core block style registrations:

```txt
core/button:
  tonal
  elevated
  text

core/group:
  card-filled
  card-elevated
  card-outlined

core/list:
  list-segmented

core/separator:
  divider-inset
  divider-middle-inset

core/search:
  filled-search
```

### Pilot File Classification Matrix

| File / surface | Probe-only | Reusable seed | Distribution-blocking | Note |
|---|---:|---:|---:|---|
| `style.css` |  | yes | partial | Usable structure, but theme name, version, text domain, tags, URI, and slug need distributable decision |
| `theme.json` |  | yes | partial | Strong seed; must preserve Lock 1 / Lock 2 and review distributable preset posture |
| `functions.php` |  | yes | partial | Good seed; namespaced to `axismundi_pilot_*` and `AXISMUNDI_PILOT_VERSION`, so not directly shippable |
| `README.md` | yes | partial | no | Pilot instructions only; not a distributable README |
| `readme.txt` | yes | partial | yes | Explicitly says not a WordPress.org submission package |
| `screenshot.png` | yes | partial | yes | 1200x900 but historically classified as placeholder / not submission artifact |
| `templates/index.html` |  | yes | no | Required block-theme fallback exists |
| `templates/page.html` |  | yes | no | Seed; currently minimal |
| `templates/single.html` |  | yes | no | Seed; needs final content / comments / metadata posture |
| `templates/front-page.html` | n/a | no | partial | Missing; not recognition-required, but first-paint / product-shape risk |
| `templates/home.html` | n/a | no | partial | Missing; blog-index route undecided |
| `templates/archive.html` | n/a | no | partial | Missing; archive route quality risk |
| `templates/404.html` | n/a | no | partial | Missing; expected product polish |
| `parts/header.html` | partial | partial | partial | Has `.ax-theme-switcher`; switcher route must not be implemented in this cycle |
| `parts/footer.html` |  | yes | no | Seed, subject to identity / credit policy |
| `patterns/*.php` | partial | yes | partial | Good seed; categories and text domain need distributable naming |
| `assets/styles/*.css` |  | yes | partial | Product-local copies; future distributable copy/build policy required |
| `assets/fonts/**` |  | yes | partial | Byte-identical policy recorded by v3.6.19; copy source must be decided |
| `assets/icons/**` |  | yes | partial | Rounded runtime only; Outlined / Sharp stay future/plugin route |
| `assets/scripts/pilot-block-bridge.js` | partial | partial | partial | Pilot bridge evidence, not automatic distributable runtime authority |

## Q2. Meaning Of "Pilot Is Not A Distributable"

It is more than a label.

Current repo meaning:

| Axis | Pilot | Distributable |
|---|---|---|
| Layer role | E-layer reference implementation | E-layer production product |
| Stability | May change with ontology/bindings | Semver-versioned, stable contracts |
| Path | `products/reference-implementations/axismundi-pilot/` | `products/distributables/themes/<slug>/` |
| Purpose | Validate lab / binding / WP integration | Installable theme for real sites |
| Naming | `axismundi-pilot`, explicitly probe | Product slug, not probe suffix |
| Assets | Product-local copied evidence | Build/package copy policy required |
| README | wp-env / probe usage | install / submission / user-facing |
| `readme.txt` | says not WordPress.org submission | must be valid submission-facing if targeting directory |
| Screenshot | placeholder / probe artifact | release-seal derivative |
| Changelog | phase/probe lineage | semver product changelog |
| Custom blocks | none | still none unless plugin route opens |
| Runtime HCT | excluded | still excluded; plugin territory |

Therefore a distributable cannot be produced by renaming Pilot. It needs a
declared product contract, slug, copy policy, submission posture, and release
seal timing.

## Q3. Distributable Theme Surface And Naming

Current surface:

```txt
products/distributables/
  README.md
  plugins/README.md
  themes/README.md
```

No actual theme directory exists.

`products/distributables/themes/README.md` says:

```txt
first entry planned: axismundi-microblog based on theme-pilot after binding
stabilization in v3.2+
```

Classification:

```txt
axismundi-microblog note: stale / historical
```

Why:

- It references `theme-pilot`, not the current `axismundi-pilot`.
- It says "after binding stabilization in v3.2+", but current work is v3.6.20.
- Current brand/source policy points to Axismundi identity, not necessarily a
  microblog-only first theme.
- `LICENSE-MATRIX.md` records future path
  `products/distributables/themes/axismundi/`.

Naming options:

| Option | Phase 1 read | WordPress.org / product implication |
|---|---|---|
| `axismundi` | Best default for base theme, aligns `LICENSE-MATRIX.md` and brand source | Cleanest first product slug, but user should confirm before directory creation |
| `axismundi-microblog` | Historical/stale note; still plausible later if ActivityPub/microblog product becomes first public theme | Narrows the theme promise too early; conflicts with current broader block-theme bootstrap |
| `axismundi-pilot-derivative` | Reject as public slug | Leaks internal probe lineage into product identity |
| deferred decision | Safest for Phase 2 if user has not confirmed | Blocks skeleton creation but allows split-track decision |

Recommendation:

```txt
Do not create products/distributables/themes/<slug>/ in v3.6.20 Phase 2 unless
the user confirms the first distributable slug.

Default candidate to ask/record:
  products/distributables/themes/axismundi/
```

## Q4. Minimum Safe Distributable Skeleton

Official WordPress recognition minimum for a block theme:

```txt
style.css
templates/index.html
```

Theme Review / directory-facing block-theme minimum adds:

```txt
readme.txt
theme.json
templates/index.html
style.css
```

Practical Axismundi distributable seed:

| File / folder | Status | Classification |
|---|---|---|
| `style.css` | Pilot has it | minimum required, but needs distributable metadata |
| `theme.json` | Pilot has it | directory-facing / functional requirement |
| `functions.php` | Pilot has it | needed for enqueue, fonts, styles, patterns |
| `templates/index.html` | Pilot has it | minimum required |
| `templates/page.html` | Pilot has it | reusable seed |
| `templates/single.html` | Pilot has it | reusable seed |
| `templates/front-page.html` | missing | first-paint risk / desirable before public product |
| `templates/home.html` | missing | blog-index route / desirable |
| `templates/archive.html` | missing | archive quality risk / desirable |
| `templates/404.html` | missing | product polish risk / desirable |
| `parts/header.html` | Pilot has it | reusable with switcher deferral |
| `parts/footer.html` | Pilot has it | reusable seed |
| `patterns/` | Pilot has 5 PHP patterns | reusable with naming/text-domain updates |
| `assets/` | Pilot has product-local assets | copy policy needed before distributable |
| `readme.txt` | Pilot has probe readme | must be rewritten for distributable |
| `screenshot.png` | Pilot has 1200x900 image | release-seal derivative, not locked |

Conclusion:

- Pilot already exceeds WordPress recognition minimum.
- It does not meet Axismundi distributable readiness.
- It is missing product-shape templates that matter for first impression and
  submission quality, even if not strictly recognition-required.

## Q5. Asset Policy Needed Before Bootstrap

v3.6.19 closed asset path policy as index, not consolidation.

Current facts:

- `core/design-systems/material3/assets/` is M3 runtime asset authority.
- Pilot has product-local runtime copies.
- Future distributables should bundle copies at build/package time.
- Root `assets/brand/` stores complete source SVG identity assets.
- Root `assets/media/` stores placeholder media with mixed provenance.

Recommendations for the future distributable:

```txt
M3 fonts/icons:
  Copy from core/design-systems/material3/assets/ at build/package time, or
  document the manual copy policy before first skeleton commit.

Pilot assets:
  May be used as evidence / seed, but should not be the named authority.

Brand SVG:
  Reference source only; do not lock derivatives until theme context.

Placeholder media:
  Do not ship in the base distributable theme by default.
  Use only in catalog/specimen/demo contexts after provenance and third-party
  video isolation decisions.
```

Open asset questions:

- Should a future distributable skeleton initially include no binary assets and
  rely on a build-copy step?
- Or should it include a product-local copy immediately, matching Pilot?
- If immediate copy, should Phase 2 also add a manifest entry to
  `docs/ASSET-SURFACE-INDEX.md`?

These are sufficient to block skeleton creation until Phase 2 decision or user
confirmation.

## Q6. Release-Seal Derivative Classification

Blocked derivatives:

```txt
favicon
512 / 1024 PNG exports
screenshot.png
README hero
plugin icon
WordPress.org assets
```

Existing Pilot files:

| File | Current classification | Evidence |
|---|---|---|
| `products/reference-implementations/axismundi-pilot/screenshot.png` | probe placeholder | v3.6.0 Phase 1 classified screenshot as placeholder; v3.6.19 release-seal policy says deployment derivatives remain unlocked |
| `products/reference-implementations/axismundi-pilot/readme.txt` | probe readme, not submission-ready | file says "This is a pilot theme, not a WordPress.org submission package." |

Screenshot dimensions:

```txt
1200x900
```

This is compatible with current WordPress screenshot size / ratio guidance, but
dimension compatibility does not make it release-seal locked.

Conclusion:

- Existing Pilot `screenshot.png` is not a release-seal derivative.
- Existing Pilot `readme.txt` is not a WordPress.org submission artifact.
- No conflict with release-seal memory as long as Phase 2 records this
  explicitly.

## Q7. Theme Switcher Contract Dependency

Current Pilot `parts/header.html` includes:

```html
<div class="ax-theme-switcher" role="radiogroup" aria-label="Theme">
```

Phase 1 classification:

```txt
existing markup: dependency evidence
implementation authority: no
```

Why:

- Theme switcher separation is already a guardrail:
  visitor preference != author preview.
- Front-end switcher and editor preview toggle must not sync state.
- Runtime HCT belongs BACKLOG #21 / Interpreter Plugin.
- Phase 0 Route G rejected theme switcher first.
- Lock 1 review is needed before any `--wp--preset--*` override route.

Additional local observation:

- Lab `theme.js` treats `.ax-theme-switcher` as a legacy/archive-compatible
  selector alongside `.sg-theme`.
- Pilot contains `.ax-theme-switcher` markup, but this cycle must not decide or
  repair that mismatch.

Route:

```txt
Theme Switcher Contract remains follow-on C after Pilot/distributable boundary.
```

## Q8. Validation Gates For Skeleton

If Phase 2 stays no-code:

```txt
git status --short --branch
git diff --check
```

If Phase 2 performs docs hygiene only:

```txt
git diff --check
```

If Phase 2 creates or modifies product/theme files:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

If a new distributable theme skeleton is created, add at minimum:

```txt
JSON parse for new theme.json
PHP lint for new functions.php
file-existence check for style.css and templates/index.html
no CRLF check for new files
asset path existence check for any copied/linked assets
```

No Phase 1 evidence supports creating the skeleton without naming confirmation.

## Q9. Stale Root Meta Docs

Current root meta-doc state:

| File | Observed latest state | Classification |
|---|---|---|
| `CURRENT-STATE.md` | last updated v3.6.18 closed | stale after v3.6.19 |
| `NEXT-SESSION.md` | Post-v3.6.18 handoff | stale after v3.6.19 |
| `CHANGELOG.md` | latest entry v3.6.18 | stale after v3.6.19 |
| `ROADMAP.md` | latest next candidate text around v3.6.18 | stale after v3.6.19 |

Phase 1 recommendation:

- Do not opportunistically update root meta-docs in Phase 2 unless Phase 2 is
  explicitly a docs-hygiene route.
- Phase 5 should update them if v3.6.20 closes.
- Phase 2 decision should record that `663b62c` and v3.6.19 close docs are
  authoritative over stale root handoff docs.

## Q10. Phase 2 Route Recommendation

Recommended:

```txt
Layered Route A + D
```

Meaning:

```txt
Route A execution shape:
  no-code decision report

Route D decision content:
  split-track policy for Pilot vs distributable
```

Phase 2 should decide / record:

1. Pilot remains a reference implementation probe.
2. Distributable theme must live under `products/distributables/themes/<slug>/`.
3. First distributable default candidate is `axismundi`, but skeleton creation
   waits for user slug confirmation.
4. `axismundi-microblog` note is stale and should not drive first skeleton.
5. Existing Pilot `readme.txt` and `screenshot.png` are probe artifacts.
6. Release-seal derivatives remain blocked.
7. Future skeleton must choose asset copy/build policy before binaries move.
8. Theme Switcher Contract remains follow-on C.
9. Core Block Catalog split remains follow-on D.
10. Written-material workflow ontology remains separate candidate B.

Do not create a distributable skeleton in Phase 2 unless the user changes the
route with explicit naming / skeleton GO.

## Route Evaluation

| Route | Phase 1 verdict | Reason |
|---|---|---|
| A - No-code Boundary Decision | GO | Safest execution shape; evidence resolves boundary but not slug |
| B - Pilot Hardening First | NO-GO as primary | Pilot is already valid as probe; hardening delays distributable boundary |
| C - Distributable Skeleton Bootstrap | NO-GO without user slug confirmation | Would lock path/name and asset policy too early |
| D - Split-Track Policy + Minimal Bootstrap | GO for policy, NO-GO for skeleton | Correct content route, but implementation part not yet safe |
| E - Release Seal First | NO-GO | Derivatives depend on theme/plugin context |
| F - Core Block Catalog First | NO-GO | v3.6.18 routed catalog after mapping, but Pilot/distributable context is prerequisite |
| G - Theme Switcher First | NO-GO | Needs boundary + Lock 1 review |

## wp.org Submission Readiness Matrix

| Axis | Current Pilot | Distributable requirement / risk |
|---|---|---|
| `style.css` | present, pilot metadata | needs product name/slug/version/text-domain |
| `templates/index.html` | present | usable seed |
| `theme.json` | present | usable seed, Lock 1/2 review |
| `readme.txt` | present but explicitly not submission package | rewrite required |
| `screenshot.png` | 1200x900 placeholder | release-seal derivative, not locked |
| Prefix | `axismundi_pilot_*`, `AXISMUNDI_PILOT_VERSION` | distributable prefix / constants need slug decision |
| Remote resources | local assets only in Pilot runtime | preserve; third-party media not base-theme default |
| Completeness | probe-complete | product completeness still missing |
| Templates | index/page/single | front-page/home/archive/404 are quality risks |
| Plugin functionality | no custom blocks / no HCT | preserve; plugin territory |

## Phase 2 Proposed Write Scope

Preferred Phase 2 write scope:

```txt
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-2-DECISION.md
```

Optional docs hygiene, only if user and Opus approve:

```txt
products/distributables/themes/README.md
products/distributables/README.md
LICENSE-MATRIX.md
docs/ASSET-SURFACE-INDEX.md
```

Potential docs hygiene content:

- mark `axismundi-microblog` as historical / stale;
- record `axismundi` as default candidate path but not created;
- record that future distributable build-copy policy remains open;
- add future distributable row to asset index only when a path exists, not now.

No product files should change in the default Phase 2 route.

## Non-Goals Confirmed

Phase 1 did not and Phase 2 default route should not:

- create `products/distributables/themes/axismundi/`;
- rename Pilot;
- edit Pilot templates;
- edit Pilot `theme.json`;
- edit Pilot `functions.php`;
- edit Pilot assets;
- generate release-seal derivatives;
- edit brand SVG;
- implement theme switcher;
- edit D-layer binding files;
- edit lab catalog files;
- edit `styleguide/`;
- implement BACKLOG #21, #44, #46, or #47.

## Lock Compliance

Lock 1:

- Preserved. No `wp-custom` relocation or `--wp--preset--*` CSS override.

Lock 2:

- Preserved. No `md-sys` / `md-ref` color route changes.

Lock 3:

- Preserved. v3.6.3 `core/button` semantic route not reopened.

Lock 4:

- Preserved. Pilot/distributable mismatch is routed through decision, not
  opportunistic skeleton edits.

Lock 5:

- Preserved. Phase 1 diagnostic precedes Phase 2.
- If Phase 2 remains no-code: v3.6.20 becomes 10th clean self-application
  overall, implementation-cycle count remains 6.
- If Phase 2 performs narrow docs hygiene / implementation: v3.6.20 becomes
  10th clean self-application overall and 7th implementation-cycle application.

## Review Request

Opus review requested:

```txt
P1: Any blockers to layered Route A + D with no skeleton in Phase 2?
P2: Are naming, release-seal, screenshot/readme, and asset-copy findings
    sufficient for a Phase 2 decision report?
P3: Should Phase 2 allow optional docs hygiene, or remain decision-doc only?
```

Phase 2 must wait for:

```txt
Opus Phase 1 verdict
explicit user Phase 2 execution GO
```
