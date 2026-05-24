# v3.6.20 Pilot vs Distributable Bootstrap - Phase 2 Decision

## Verdict

v3.6.20 adopts a layered no-code decision:

```txt
Route A = execution shape
  no-code decision report

Route D = decision content
  split-track policy for Pilot vs distributable theme bootstrap
```

No distributable skeleton is created in Phase 2.

No product, asset, template, `theme.json`, runtime, or meta-doc files change in
Phase 2.

## Decision Summary

v3.6.20 decides:

1. `products/reference-implementations/axismundi-pilot/` remains a probe /
   reference implementation.
2. A distributable theme must live under
   `products/distributables/themes/<slug>/`.
3. The default first-distributable slug candidate is `axismundi`, because
   `LICENSE-MATRIX.md` already records
   `products/distributables/themes/axismundi/` as the future distributable
   path.
4. The old `axismundi-microblog` note is stale as a first-distributable route,
   but not extinct as a future ActivityPub / microblog product option.
5. User slug / product-name confirmation is required before skeleton creation.
6. Existing Pilot `readme.txt` and `screenshot.png` are probe artifacts.
7. Release-seal derivatives remain blocked until theme/plugin context exists.
8. Distributable asset-copy policy remains open and must be decided before
   product-local binaries are moved or copied.
9. Theme Switcher Contract remains follow-on C after this boundary decision.
10. Core Block Catalog split remains follow-on D after Pilot/distributable
    context is stable.

Written-material workflow ontology remains a separate parallel candidate, not
part of this decision content.

## What This Decision Does Not Do

This decision does not:

- create `products/distributables/themes/axismundi/`;
- create any other theme skeleton;
- rename `axismundi-pilot`;
- rewrite Pilot namespaces, constants, text domain, pattern categories, or
  theme metadata;
- rewrite `readme.txt`;
- replace `screenshot.png`;
- generate favicon, PNG exports, README hero, plugin icon, WordPress.org assets,
  or any release-seal derivative;
- move, copy, delete, or rewrite assets;
- implement theme switcher;
- edit D-layer binding files;
- edit lab catalog files;
- edit `styleguide/`;
- update root handoff/meta docs.

## Pilot Track

Pilot remains:

```txt
products/reference-implementations/axismundi-pilot/
```

Role:

- reference implementation;
- validation target;
- real WordPress block-theme probe;
- consumer of lab / binding / token evidence;
- allowed to change when ontology or binding contracts change.

Pilot does not become:

- the first distributable;
- a WordPress.org submission package;
- a release-seal context;
- a custom block package;
- a runtime HCT package.

Anti-collapse evidence from Phase 1:

- `functions.php` uses `axismundi_pilot_*` function names.
- `functions.php` defines `AXISMUNDI_PILOT_VERSION`.
- `style.css` declares theme name `Axismundi Pilot`, text domain
  `axismundi-pilot`, and version `0.1.0-pilot`.
- pattern categories use `axismundi-*` category slugs but are registered inside
  Pilot code.
- `readme.txt` says: "This is a pilot theme, not a WordPress.org submission
  package."

Therefore the Pilot cannot become a distributable by rename. A distributable
requires a product contract.

## Distributable Track

Distributables live under:

```txt
products/distributables/
```

`products/distributables/README.md` defines them as:

- production-grade;
- semver-versioned;
- stable contract products;
- suitable for real WordPress sites.

Future theme location:

```txt
products/distributables/themes/<slug>/
```

Default slug candidate:

```txt
axismundi
```

Reason:

- `LICENSE-MATRIX.md` Section 1 already records
  `products/distributables/themes/axismundi/`.
- Root brand assets are Axismundi project identity sources.
- The current first-product context is broader than a microblog-only theme.

But no directory is created yet because the user has not explicitly confirmed
the first distributable slug / product name inside this cycle.

## Slug Authority Chain

Current authority chain:

```txt
LICENSE-MATRIX.md Section 1
  = current future distributable path reference
  = products/distributables/themes/axismundi/

v3.6.20 Phase 2 decision
  = confirms `axismundi` as default candidate aligned with LICENSE-MATRIX
  = does not create the path

products/distributables/themes/README.md
  = contains stale historical `axismundi-microblog` wording
  = should be corrected in a future docs hygiene or skeleton cycle

Actual skeleton creation
  = requires explicit user slug / product-name GO
```

If a future cycle chooses a slug other than `axismundi`, that cycle must update
`LICENSE-MATRIX.md`, `products/distributables/themes/README.md`, and any asset
index references in one reviewed pass.

## `axismundi-microblog` Classification

Phase 2 classifies the old `axismundi-microblog` note as:

```txt
stale as first-distributable guidance
deferred as possible future ActivityPub / microblog product option
```

It is not:

- the v3.6.20 default first-distributable candidate;
- a deleted concept;
- authority for creating a directory in this cycle.

Future handling:

- remove "planned first entry" language from
  `products/distributables/themes/README.md` during a docs hygiene or skeleton
  cycle;
- preserve `axismundi-microblog` only as a possible later product if the
  ActivityPub / microblog route becomes active.

## Required Pre-Skeleton Decisions

Before any distributable skeleton is created, a future cycle must decide:

1. First distributable slug and product name.
2. Text domain / PHP namespace / constant prefix.
3. Asset-copy policy:
   - copy from `core/design-systems/material3/assets/` at build time;
   - or copy from Pilot as a one-time seed;
   - or create a new build-copy tool.
4. Whether product-local binaries enter the first skeleton commit.
5. Whether root placeholder media are excluded from the base theme.
6. Release-seal derivative timing.
7. Minimum template set for first public review:
   - `index.html`;
   - `front-page.html`;
   - `home.html`;
   - `single.html`;
   - `page.html`;
   - `archive.html`;
   - `404.html`.
8. `readme.txt` submission posture.
9. `screenshot.png` content source.
10. WordPress.org tag / screenshot / remote-resource compliance posture.

These are skeleton prerequisites, not v3.6.20 implementation tasks.

## `readme.txt` Decision

Pilot `readme.txt` remains a probe artifact.

Decision:

```txt
Distributable readme.txt rewrite is not docs hygiene.
It belongs to the skeleton bootstrap / submission-readiness cycle after user
slug GO.
```

Why:

- The current Pilot file explicitly says the theme is not a WordPress.org
  submission package.
- A distributable readme needs product metadata, tested version posture,
  description, installation, changelog, license posture, tags, and user-facing
  copy.
- Rewriting it now would imply a product identity before slug confirmation.

## `screenshot.png` Decision

Pilot `screenshot.png` remains a probe placeholder.

Observed:

```txt
1200x900
```

Decision:

- The dimensions match current WordPress screenshot guidance.
- Dimension compatibility does not make it release-seal locked.
- The content remains placeholder / probe-grade because release-seal context is
  not fixed.
- The future distributable `screenshot.png` should be generated or chosen after
  the theme context, templates, homepage / first-paint surface, and brand
  derivative policy are stable.

## Release-Seal Decision

Release-seal derivatives remain blocked:

```txt
favicon
512 / 1024 PNG exports
screenshot.png
README hero
plugin icon
WordPress.org assets
```

This preserves the v3.6.19 decision:

- `assets/brand/*.svg` are complete project identity source assets.
- Deployment derivatives are not locked until Pilot vs distributable context
  exists.

v3.6.20 creates the boundary context but does not yet create the product
context, because the skeleton slug is not confirmed.

## Asset-Copy Decision

Current asset policy remains:

```txt
core/design-systems/material3/assets/
  = design-system runtime asset authority

products/reference-implementations/axismundi-pilot/assets/
  = Pilot product-local copy evidence

products/distributables/themes/<slug>/assets/
  = future product-local copy, not yet created
```

Decision:

- Future distributables should not treat Pilot's copy as authority.
- The preferred authority is `core/design-systems/material3/assets/`.
- Whether a future distributable copies assets manually or through a build-copy
  pipeline remains open.
- `docs/ASSET-SURFACE-INDEX.md` should be updated only when a concrete
  distributable asset surface exists.

Root placeholder media:

- do not enter the base distributable theme by default;
- remain candidate inputs for catalog / specimen / demo contexts;
- Pixabay video isolation remains a separate follow-on.

## Theme Switcher Contract Follow-On

Theme Switcher Contract remains follow-on C.

Known pre-diagnostic items for that future cycle:

- lab `theme.js` currently treats `.ax-theme-switcher` as legacy/archive
  compatibility alongside `.sg-theme`;
- Pilot `parts/header.html` uses active `.ax-theme-switcher` markup;
- future cycle must decide the canonical selector:
  - keep `.ax-theme-switcher`;
  - move Pilot to a new selector;
  - or formalize `.sg-theme` / `.ax-theme-switcher` compatibility boundaries;
- front-end visitor preference must not sync with editor preview state;
- runtime HCT remains BACKLOG #21 / Interpreter Plugin territory;
- Lock 1 review is required before any `--wp--preset--*` CSS override route.

No selector, script, or template changes occur in v3.6.20.

## Core Block Catalog Follow-On

Core Block Catalog split remains follow-on D.

v3.6.18 already routed:

- `style-guide-blocks.html` to a category-aware lab catalog split;
- Text / Media / Design / Widgets / Theme categories;
- Embeds excluded until explicit source/privacy/provider policy.

v3.6.20 does not alter that route.

Reason:

- Pilot/distributable context should be stable before lab catalog work is used
  to feed product templates.

## wp.org Submission Readiness Gate

Future skeleton cycle should reuse this gate:

| Axis | Required future decision |
|---|---|
| `style.css` | product name, slug, version, text domain, tags |
| `templates/index.html` | required fallback |
| `theme.json` | preserve Lock 1 / Lock 2, product path assets |
| `readme.txt` | rewrite as submission-facing product doc |
| `screenshot.png` | release-seal derivative content |
| Prefix | PHP namespace, constants, pattern category slugs |
| Remote resources | no remote dependency without policy / consent |
| Completeness | product surface, not probe |
| Templates | front-page / home / archive / 404 risk classification |
| Plugin functionality | keep HCT / custom blocks in plugin territory |

This gate is a checklist for a future skeleton cycle, not a v3.6.20
implementation list.

## Phase 2 Write Scope

Actual Phase 2 write scope:

```txt
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-2-DECISION.md
```

No optional docs hygiene is included in this Phase 2 execution.

Deferred optional docs hygiene:

```txt
products/distributables/themes/README.md
products/distributables/README.md
LICENSE-MATRIX.md
docs/ASSET-SURFACE-INDEX.md
```

These should be edited only in a future docs hygiene / skeleton cycle with an
explicit write scope.

## Phase 3 Recommendation

Because Phase 2 is no-code:

```txt
git status --short --branch
git diff --check
```

Full validation suite is optional but not required unless Phase 3 reviewer wants
v3.6.17-v3.6.19 evidence-shape parity.

If full validation is run, restore any validator-generated report churn before
Phase 3 closes.

## Lock Compliance

Lock 1:

- Preserved. No `wp-custom` relocation and no `--wp--preset--*` CSS override.

Lock 2:

- Preserved. No `md-sys` / `md-ref` route change.

Lock 3:

- Preserved. v3.6.3 `core/button` semantic route not reopened.

Lock 4:

- Preserved. Pilot/distributable mismatch is routed through a decision report,
  not opportunistic file moves.

Lock 5:

- Preserved. Phase 1 diagnostic preceded Phase 2 decision.
- If v3.6.20 stays no-code through close, it will be the 10th clean Lock 5
  self-application overall and the implementation-cycle count remains 6.

## Non-Goals Confirmed

v3.6.20 Phase 2 did not:

- create a distributable skeleton;
- edit Pilot;
- edit `products/distributables/`;
- edit `LICENSE-MATRIX.md`;
- edit `docs/ASSET-SURFACE-INDEX.md`;
- edit root meta-docs;
- edit assets;
- generate release-seal derivatives;
- implement Theme Switcher Contract;
- implement Core Block Catalog split;
- implement BACKLOG #21, #44, #46, or #47.

## Phase 4

Phase 4 is intentionally unused unless Phase 3 discovers a deeper architecture
audit need.

Current evidence does not require Phase 4.

## Review Request

Opus review requested:

```txt
P1: Any blockers to this no-code split-track decision?
P2: Are the no-skeleton, slug authority, readme/screenshot, release-seal,
    asset-copy, and theme-switcher follow-on decisions sufficiently explicit?
P3: Should Phase 3 run only git status / diff check, or the full v3.6.17-v3.6.19
    validation suite for evidence-shape parity?
```

Phase 3 must wait for Opus Phase 2 verdict and explicit user execution GO.
