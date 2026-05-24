# v3.6.19 Asset Surface Audit + Cross-Reference Index - Phase 2 Decision

## Verdict

Phase 2 implements Route B - Narrow Documentation Hygiene.

The asset audit found no reason to consolidate asset paths, move assets, delete
assets, or change runtime references. The correct fix is a cross-cutting asset
surface index plus small stale-document corrections.

## Files Changed

```txt
docs/ASSET-SURFACE-INDEX.md
core/design-systems/material3/assets/README.md
NOTICE.md
LICENSE-MATRIX.md
assets/brand/README.md
docs/v3.6.19/ASSET-SURFACE-AUDIT-PHASE-2-DECISION.md
```

Implementation files changed:

```txt
0
```

Asset binaries changed:

```txt
0
```

Runtime / template / theme files changed:

```txt
0
```

## Decision 1 - Add Cross-Cutting Asset Surface Index

Added:

```txt
docs/ASSET-SURFACE-INDEX.md
```

Status:

This is a cross-cutting project document, not a v3.6.19 cycle artifact. It
survives the cycle and may be referenced by future asset, theme bootstrap,
catalog, and distributable packaging work.

Purpose:

- Index asset surfaces without consolidating them.
- Preserve the policy encoded in path location.
- Give future cycles one place to check authority, ownership/license class,
  shipping posture, reference pattern, local README, and follow-on status.

The index uses the Phase 1 Q8c structure:

```txt
Surface
Authority
Ownership / license class
Ships where
Consumer / reference pattern
Policy notes
Canonical local README
Known drift / follow-on
```

Surfaces covered:

```txt
assets/brand/
assets/media/
compare/brand-assets-research/
core/design-systems/material3/assets/
products/reference-implementations/axismundi-pilot/assets/
products/reference-implementations/ontology-theme-pilot/assets/
styleguide/
```

## Decision 2 - Preserve Material Symbols Three-Part Policy

Updated:

```txt
core/design-systems/material3/assets/README.md
```

The stale claim that Material Symbols Outlined and Sharp are not included was
replaced with the three-part policy:

```txt
(a) Stored binaries:
    core/design-systems/material3/assets/icons/ stores Outlined, Rounded, and
    Sharp Material Symbols WOFF2 files.

(b) Registered runtime:
    Current lab / Pilot / styleguide runtime CSS registers Rounded only.

(c) Outlined / Sharp route:
    Outlined and Sharp remain plugin / future variation territory until a
    future cycle explicitly enables them in runtime CSS.
```

This Phase 2 does not register Outlined or Sharp in runtime CSS.

## Decision 3 - Correct Root Audio Placeholder Wording

Updated:

```txt
NOTICE.md
LICENSE-MATRIX.md
```

Token-level corrections only:

```txt
NOTICE.md:
  "Opus/Ogg conversion" -> "Opus conversion"

LICENSE-MATRIX.md:
  "Opus/Ogg derivative included" ->
  "MP3 source/reference plus Opus derivative included"
```

No broader license-matrix or notice restructuring was performed.

## Decision 4 - Clarify Brand Source vs Release Seal

Updated:

```txt
assets/brand/README.md
```

The ambiguous "canonical draft" wording was replaced with:

```txt
assets/brand/*.svg = complete project identity source assets
```

Deployment-derivative artifacts remain unlocked:

```txt
favicon
512 / 1024 PNG exports
screenshot.png
README hero
plugin icon
WordPress.org assets
```

This preserves the release-seal framing: derivative lock timing is separate
from symbol source completion.

## Decision 5 - Preserve Path-As-Policy Boundaries

No consolidation happened.

Preserved:

- `compare/brand-assets-research/` remains DO-NOT-SHIP third-party
  brand/trademark research.
- `core/design-systems/material3/assets/` remains Material 3 runtime asset
  authority.
- `assets/brand/` remains project identity source assets.
- `assets/media/` remains mixed-provenance placeholder media with per-file
  license records.
- `products/reference-implementations/axismundi-pilot/assets/` remains a Pilot
  product-local copy surface.
- `products/reference-implementations/ontology-theme-pilot/assets/` remains a
  legacy E-layer reference surface.
- `styleguide/` remains a generated publish mirror, not an authoring authority.

## Non-Goals Confirmed

Phase 2 did not:

- move assets;
- delete assets;
- edit SVG, WOFF2, MP3, Opus, WebP, or WebM files;
- edit runtime CSS;
- edit PHP;
- edit `theme.json`;
- edit templates, pages, or patterns;
- edit `styleguide/`;
- regenerate publish mirrors;
- edit D-layer files;
- implement Pilot vs distributable bootstrap;
- implement media catalog work;
- implement release-seal derivative generation.

## Drift Closure

| Finding | Phase 2 action | Status |
|---|---|---|
| D1 Material Symbols top-level README stale | Updated M3 asset README with all-three-stored / Rounded-runtime / Outlined-Sharp-routed policy | Closed |
| D2 Root Opus/Ogg stale wording | Updated `NOTICE.md` and `LICENSE-MATRIX.md` token-level wording | Closed |
| D3 Pilot copy vs relative-reference confusion | Documented in `docs/ASSET-SURFACE-INDEX.md` | Closed as indexed policy |
| D4 Brand source vs release-seal wording | Updated `assets/brand/README.md` | Closed |
| D5 Third-party brand research isolation | Documented in `docs/ASSET-SURFACE-INDEX.md` | Preserved |
| D6 Root media per-file provenance | Documented in `docs/ASSET-SURFACE-INDEX.md`; no local correction needed | Preserved |

## Phase 3 Validation Plan

Run the standard docs-hygiene validation set:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

Restore validator-generated report churn before Phase 3 close, matching
v3.6.17-v3.6.18 hygiene.

## Lock Compliance

Lock 1:

- Preserved. No `wp-custom` relocation.

Lock 2:

- Preserved. No `md-sys` / `md-ref` edits.

Lock 3:

- Preserved. No `core/button` route change.

Lock 4:

- Preserved. Asset-surface authority is explicit before any visual/catalog or
  runtime action.

Lock 5:

- Preserved. Phase 1 diagnostic preceded Phase 2 docs hygiene.
- If Phase 3 validation passes and this route closes as-is, v3.6.19 should be
  recorded as the ninth clean Lock 5 self-application overall and the sixth
  implementation-cycle application.

## Phase 2 Review Request

Please review:

1. P1: Any blocker to the Route B docs hygiene implementation?
2. P2: Did the Material Symbols three-part policy remain intact?
3. P3: Is `docs/ASSET-SURFACE-INDEX.md` correctly scoped as a cross-cutting
   project doc rather than a cycle artifact?

Phase 3 must wait for Opus verdict and explicit user execution GO.
