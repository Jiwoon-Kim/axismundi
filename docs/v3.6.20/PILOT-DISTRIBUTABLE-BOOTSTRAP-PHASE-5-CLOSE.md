# v3.6.20 Pilot vs Distributable Bootstrap - Phase 5 Close

## Verdict

v3.6.20 is ready to close as a no-code Pilot vs distributable bootstrap
boundary decision.

The cycle adopts:

```txt
Route A = no-code decision execution shape
Route D = split-track Pilot / distributable policy content
```

No distributable skeleton was created.

No product, asset, template, `theme.json`, runtime, D-layer, release-seal, or
root meta-doc file changed in this cycle.

## Documents

Cycle documents:

```txt
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-0-PLAN.md
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-1-REPORT.md
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-2-DECISION.md
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-3-VERIFICATION.md
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-5-CLOSE.md
```

No cross-cutting non-cycle document was added in v3.6.20.

## Closed In v3.6.20

Closed:

- Pilot vs distributable boundary classification.
- First-distributable default candidate decision.
- `axismundi-microblog` stale-as-first / deferred-as-future classification.
- Pilot `readme.txt` / `screenshot.png` classification.
- Release-seal dependency route.
- Theme Switcher Contract route.
- Asset-copy policy gate.

Not closed:

- Distributable skeleton creation.
- User confirmation of first distributable slug / product name.
- `products/distributables/themes/README.md` stale wording cleanup.
- `LICENSE-MATRIX.md` / `docs/ASSET-SURFACE-INDEX.md` future path update.
- Release-seal derivative generation.
- Theme Switcher Contract implementation.
- Core Block Catalog split.
- Root meta-doc catch-up after v3.6.19 / v3.6.20.

## Decision Summary

v3.6.20 decides:

1. `products/reference-implementations/axismundi-pilot/` remains a probe /
   reference implementation.
2. A distributable theme must live under
   `products/distributables/themes/<slug>/`.
3. The default first-distributable slug candidate is `axismundi`, aligned with
   `LICENSE-MATRIX.md`.
4. The old `axismundi-microblog` note is stale as first-distributable guidance.
5. `axismundi-microblog` remains deferred as a possible future ActivityPub /
   microblog product option.
6. User slug / product-name confirmation is required before skeleton creation.
7. Existing Pilot `readme.txt` and `screenshot.png` are probe artifacts.
8. Release-seal derivatives remain blocked until product context exists.
9. Distributable asset-copy policy must be decided before product-local
   binaries are moved or copied.
10. Theme Switcher Contract remains follow-on C.
11. Core Block Catalog split remains follow-on D.

Written-material workflow ontology remains a separate parallel candidate.

## Pilot Track

Pilot remains:

```txt
products/reference-implementations/axismundi-pilot/
```

Pilot role:

- reference implementation;
- validation target;
- real WordPress block-theme probe;
- consumer of lab / binding / token evidence;
- mutable when ontology or binding contracts change.

Pilot does not become:

- a distributable;
- a WordPress.org submission package;
- a release-seal context;
- a custom-block package;
- an HCT runtime package.

Anti-collapse evidence:

- `functions.php` uses `axismundi_pilot_*` function names.
- `functions.php` defines `AXISMUNDI_PILOT_VERSION`.
- `style.css` declares `Axismundi Pilot`, `axismundi-pilot`, and
  `0.1.0-pilot`.
- Pilot `readme.txt` says it is not a WordPress.org submission package.
- `readme.txt` / `screenshot.png` were already treated as draft/placeholder in
  v3.6.0 Phase 1.

Therefore a distributable cannot be produced by renaming Pilot.

## Distributable Track

Future distributable themes live under:

```txt
products/distributables/themes/<slug>/
```

Default candidate:

```txt
products/distributables/themes/axismundi/
```

Reason:

- `LICENSE-MATRIX.md` Section 1 already records this path.
- Project brand assets are complete Axismundi identity sources.
- Current product context is broader than the old microblog-only note.

But the path was not created in v3.6.20 because user slug / product-name
confirmation is required first.

If a future cycle chooses a slug other than `axismundi`, that cycle must update
`LICENSE-MATRIX.md`, `products/distributables/themes/README.md`, and any
`docs/ASSET-SURFACE-INDEX.md` references in one reviewed pass.

## `axismundi-microblog`

Classification:

```txt
stale as first-distributable guidance
deferred as possible future ActivityPub / microblog product option
```

Future docs hygiene should:

- remove "planned first entry" language from
  `products/distributables/themes/README.md`;
- preserve `axismundi-microblog` only as a possible later product if the
  ActivityPub / microblog route becomes active.

## `readme.txt` / `screenshot.png`

Pilot `readme.txt`:

- probe artifact;
- not submission-ready;
- should not be rewritten as docs hygiene;
- belongs to the skeleton bootstrap / submission-readiness cycle after user
  slug GO.

Pilot `screenshot.png`:

- 1200x900;
- dimension-compatible with current WordPress screenshot guidance;
- still a probe placeholder;
- not a release-seal derivative;
- content should be replaced or regenerated only after product context,
  templates, first-paint surface, and brand derivative policy are stable.

## Release Seal

Still blocked:

```txt
favicon
512 / 1024 PNG exports
screenshot.png
README hero
plugin icon
WordPress.org assets
```

v3.6.20 creates a boundary context but does not yet create a product context.

That distinction matters:

- boundary context = Pilot stays probe; distributable needs its own path;
- product context = actual slug, skeleton, template surface, copy policy, and
  release target.

Release-seal derivatives need product context.

## Asset-Copy Policy

Current authority chain remains:

```txt
core/design-systems/material3/assets/
  = design-system runtime asset authority

products/reference-implementations/axismundi-pilot/assets/
  = Pilot product-local copy evidence

products/distributables/themes/<slug>/assets/
  = future product-local copy candidate, not yet created
```

Future cycle must decide:

- manual copy vs build-time copy;
- whether product-local binaries enter the first skeleton commit;
- whether `docs/ASSET-SURFACE-INDEX.md` needs a new distributable row;
- whether root placeholder media are excluded from the base theme.

Root placeholder media remain catalog/specimen/demo candidates, not base-theme
assets by default.

## Theme Switcher Follow-On

Theme Switcher Contract remains follow-on C.

First diagnostics for that future cycle:

- lab `theme.js` treats `.ax-theme-switcher` as legacy/archive compatibility
  alongside `.sg-theme`;
- Pilot `parts/header.html` uses active `.ax-theme-switcher` markup;
- future cycle must decide canonical selector:
  - keep `.ax-theme-switcher`;
  - move Pilot to a new selector;
  - or formalize `.sg-theme` / `.ax-theme-switcher` compatibility boundaries;
- visitor preference must not sync with editor preview state;
- runtime HCT remains BACKLOG #21 / Interpreter Plugin territory;
- Lock 1 review is required before any `--wp--preset--*` CSS override route.

## Core Block Catalog Follow-On

Core Block Catalog split remains follow-on D.

v3.6.18 route preserved:

- split `style-guide-blocks.html` by Text, Media, Design, Widgets, and Theme;
- keep Embeds excluded until source/privacy/provider policy exists;
- keep `style-guide-prose.html` as Markdown / Custom HTML prose inheritance.

## Validation

Phase 3 full validation:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php PASS
npm test                                                           PASS
  Axis A/B/C/D/E/F/G all 1.000
python tools\generators\build_pilot_specimen_wall.py              PASS
npm run validate:specimen-wall                                     PASS
npm run validate:computed                                          PASS
git diff --check                                                   PASS
```

Generated artifact hygiene:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

`npm test` rewrote both files. They were restored before Phase 3 closed.

## Lock Compliance

Lock 1:

- Preserved. No `wp-custom` relocation and no `--wp--preset--*` CSS override.

Lock 2:

- Preserved. No `md-sys` / `md-ref` route change.

Lock 3:

- Preserved. v3.6.3 `core/button` semantic route not reopened.

Lock 4:

- Preserved. Pilot/distributable mismatch is routed through decision, not file
  moves.

Lock 5:

- Preserved. Phase 1 diagnostic preceded Phase 2 decision and Phase 3
  verification.

Count chain:

```txt
v3.6.17:
  7th clean Lock 5 self-application overall
  implementation-cycle count = 5
  no-code decision variant

v3.6.18:
  8th clean Lock 5 self-application overall
  implementation-cycle count = 5
  no-code decision variant

v3.6.19:
  9th clean Lock 5 self-application overall
  implementation-cycle count = 6
  narrow docs hygiene implementation

v3.6.20:
  10th clean Lock 5 self-application overall
  implementation-cycle count = 6
  no-code decision variant
```

## Non-Goals Confirmed

v3.6.20 did not:

- create a distributable skeleton;
- rename Pilot;
- edit Pilot implementation files;
- edit Pilot templates / parts / patterns;
- edit Pilot `theme.json`;
- edit assets;
- generate release-seal derivatives;
- edit D-layer binding files;
- edit lab catalog files;
- edit `styleguide/`;
- implement Theme Switcher Contract;
- implement Core Block Catalog split;
- implement BACKLOG #21, #44, #46, or #47;
- update root meta-docs.

## Routed Forward

Candidate follow-ons remain plan-first:

- Distributable theme skeleton bootstrap:
  - requires explicit user slug / product-name GO;
  - default candidate remains `axismundi`.
- Root meta-doc maintenance:
  - bring `NEXT-SESSION.md`, `CURRENT-STATE.md`, `CHANGELOG.md`, and
    `ROADMAP.md` current after v3.6.19 / v3.6.20;
  - keep separate from this strict no-code close commit unless user explicitly
    chooses a combined maintenance close.
- Release-seal derivative generation:
  - favicon;
  - 512 / 1024 PNG exports;
  - distributable `screenshot.png`;
  - README hero;
  - plugin icon;
  - WordPress.org assets.
- Distributable build-copy pipeline:
  - manual copy vs build-time copy;
  - product-local binary policy;
  - future `docs/ASSET-SURFACE-INDEX.md` row.
- Theme Switcher Contract:
  - lab/Pilot `.ax-theme-switcher` drift;
  - front-end/editor separation;
  - Lock 1 route for any preset override.
- Core Block Catalog split.
- Webdesign-craftsman workflow ontology:
  - corpus / atlas / core subtree route;
  - not direct page layout source.
- Media catalog implementation.
- Pixabay video isolation decision.
- `ontology-theme-pilot/assets/` modernization or explicit freeze, if reopened.
- BACKLOG #21 Interpreter Plugin strategy.
- BACKLOG #44 specimen coverage, especially long-line code and deep pullquote.
- BACKLOG #46 disabled ripple host hygiene.
- BACKLOG #47 popover provider hygiene.
- v3.6.15-v3.6.17 diagnostics policy follow-ons.

## Memory Promotion Notes

Candidate M1:

```txt
Pilot probe != distributable; rename is not promotion.
```

Evidence:

- Phase 1 Q2 Pilot vs distributable matrix.
- Phase 2 anti-collapse concrete evidence.
- v3.6.20 close decision.

Recommendation:

```txt
Promote to project memory after close, if user agrees.
```

Candidate M2:

```txt
Distributable skeleton creation requires naming, namespace/text-domain,
asset-copy, release-seal, submission, and template-surface prerequisites.
```

Evidence:

- Phase 2 Required Pre-Skeleton Decisions.
- Phase 5 Routed Forward.

Recommendation:

```txt
Promote to project memory after close, if user agrees.
```

Candidate M3:

```txt
Boundary context != product context.
```

Recommendation:

```txt
Watch. Promote only if repeated in the next skeleton / release-seal cycle.
```

## Phase 4

Phase 4 was intentionally unused.

Reason:

- Phase 1 / Phase 2 resolved the boundary as a no-code decision.
- Full Phase 3 validation passed.
- No deeper architecture audit need was discovered.

## Close Verdict

v3.6.20 is ready for close review.

Commit / push should wait for Opus Phase 5 verdict and explicit user commit GO.
