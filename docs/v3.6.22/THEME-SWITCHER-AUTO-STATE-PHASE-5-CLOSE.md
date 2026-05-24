# v3.6.22 Theme Switcher Explicit Auto State - Phase 5 Close

## Verdict

v3.6.22 is closed as a narrow implementation cycle for BACKLOG #22.

Closed implementation:

```txt
JS root state:      auto writes data-theme="auto"
CSS dark-auto:      absent legacy state + explicit data-theme="auto"
Pilot root default: front-end data-theme="auto" via Pilot-only language_attributes filter
inline head script: deferred to future product-context / BACKLOG #21-adjacent decision
```

BACKLOG #22 is resolved for the current Axismundi surfaces.

## Documents

Cycle docs:

```txt
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-0-PLAN.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-1-REPORT.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-2-IMPLEMENTATION.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-3-VERIFICATION.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-5-CLOSE.md
```

Cross-cutting docs:

```txt
None added.
```

Root meta-docs:

```txt
BACKLOG.md
```

was updated because BACKLOG #22 closes in this implementation cycle. The
handoff meta-doc set (`NEXT-SESSION.md`, `CURRENT-STATE.md`, `CHANGELOG.md`,
`ROADMAP.md`) was not updated in this close.

## Closed Decisions

1. `auto` is now an explicit root state, not the absence of `data-theme`.
2. JS switchers write `data-theme="auto"` instead of removing the attribute.
3. Dark-auto CSS now targets only absent legacy state and explicit `auto`.
4. Future variant values such as `sepia`, `dim`, or `high-contrast` no longer
   fall through into the dark-auto branch.
5. Pilot front-end HTML receives default `data-theme="auto"` through a
   Pilot-only `language_attributes` filter.
6. The Pilot PHP filter is not a distributable inheritance decision.
7. `.sg-theme` / `.ax-theme-switcher` selector ownership remains unchanged.
8. `data-theme-button` / `data-theme-set` attribute ownership remains
   unchanged.
9. Storage keys remain owner-specific and unchanged.
10. v3.6.17 Pilot bridge source/copy byte-identical contract is restored and
    preserved.
11. Inline first-paint / FOUC script policy remains deferred.
12. BACKLOG #21 remains the owner for Interpreter Plugin, HCT, editor UI,
    Global Styles sync, and product-context runtime expansion.
13. BACKLOG #22 is closed for current Axismundi surfaces.

## Implementation Surface

Single close commit scope is expected to include 19 files:

```txt
5 cycle docs
1 BACKLOG.md update
6 JS files
6 CSS files
1 Pilot functions.php
```

Implementation files:

```txt
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js

products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.dark.css
products/reference-implementations/axismundi-lab/stylesheets/base.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.dark.css
products/reference-implementations/axismundi-pilot/assets/styles/base.css
styleguide/stylesheets/tokens.sys.dark.css
styleguide/stylesheets/base.css

products/reference-implementations/axismundi-pilot/functions.php
BACKLOG.md
```

## Phase 2 Amend Reflection

Phase 2 originally updated the enqueued Pilot bridge copy:

```txt
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

but missed the v3.6.17-declared authoritative source:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
```

Opus review caught the gap as a P1 blocker. Phase 2 was amended to update both
files and restore the v3.6.17 byte-identical contract.

Root cause:

```txt
Phase 1 inventory counted the active JS mutation surfaces but did not search
prior v3.6.x cycle docs for source-of-authority / byte-identical declarations.
```

Resolution:

```txt
source: products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
copy:   products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
hash:   E18539729212C96C5F912653B04AC8C31D0A5BC7E257CDBF9D614BEC69925713
```

Lesson:

```txt
Implementation-cycle Phase 1 inventory should grep prior v3.6.x docs for
source-of-authority / authoritative / byte-identical contracts when touching
mirrored or generated surfaces.
```

## Validation

Phase 3 ran full validation:

| Command | Result |
|---|---|
| `php -l products\reference-implementations\axismundi-pilot\functions.php` | PASS |
| `node --check` on 6 theme-switcher JS files | PASS |
| `npm test` | PASS - Overall 1.000, Axis A-G all 1.000 |
| `python tools\generators\build_pilot_specimen_wall.py` | PASS - page 13 / 14 updated |
| `npm run validate:specimen-wall` | PASS |
| `npm run validate:computed` | PASS |
| `git diff --check` | PASS |

Generated artifact restore drill executed:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

Both were restored after `npm test`.

## Browser / Runtime Verification

Phase 3 Playwright verification:

| Surface | Initial root state | Click path | Result |
|---|---|---|---|
| Lab style-guide catalog | `auto` | `auto->auto`, `light->light`, `dark->dark` | PASS |
| Lab module pattern (`icon-system`) | `auto` | `auto->auto`, `light->light`, `dark->dark` | PASS |
| Generated styleguide | `auto` | `auto->auto`, `light->light`, `dark->dark` | PASS |
| Pilot front-end | `auto` | `auto->auto`, `light->light`, `dark->dark` | PASS |

Console / page errors:

```txt
0 errors
```

## Editor Canvas Scope

v3.6.22 did not perform a direct WordPress admin/editor Playwright check.

Reason:

```txt
The PHP language_attributes filter is front-end scoped with is_admin().
Editor preview UI, editor persisted state, HCT, Global Styles sync, and
PluginSidebar behavior remain BACKLOG #21 / Interpreter Plugin territory.
```

Evidence:

```txt
functions.php line 84: is_admin() guard returns original language attributes
computed-style audit: PASS
```

Therefore v3.6.22 does not claim editor preview implementation completion. A
future editor-preview / Interpreter Plugin cycle must run direct editor canvas
verification if it changes that surface.

## BACKLOG #22 Close Eligibility

Close criteria:

| Criterion | Evidence | Result |
|---|---|---|
| JS writes explicit `data-theme="auto"` | 6 JS files updated and `node --check` PASS | PASS |
| CSS no longer absorbs future variants through `:not([data-theme="light"])` | 6 CSS files updated; old pattern scan PASS | PASS |
| Pilot front-end root defaults to `auto` | Pilot `language_attributes` filter + browser initial state `auto` | PASS |
| Lab / styleguide / Pilot click paths work | 4 runtime surfaces, 3 modes each | PASS |
| v3.6.17 Pilot bridge contract preserved | source/copy SHA256 identical | PASS |
| D-layer / validation axes unaffected | `npm test` Axis A-G all 1.000 | PASS |
| #21 territory not entered | inline script/editor UI/HCT deferred | PASS |

BACKLOG #22 is closed.

## Lock Compliance

Lock 1:

- Preserved. No `wp-custom`, `settings.custom.axismundi.*`, or `theme.json`
  source-route changes.

Lock 2:

- Preserved. Token values and md-sys / md-ref mappings are unchanged. This
  cycle changed selector routing only.

Lock 3:

- Preserved. Core/button semantic route not reopened.

Lock 4:

- Preserved. The semantic mismatch was routed through v3.6.21 contract
  decision, then v3.6.22 implementation.

Lock 5:

- Preserved. Phase 1 diagnostic preceded Phase 2 implementation, Phase 2 amend
  resolved a review blocker, and Phase 3 verification closed the evidence loop.

Count chain:

| Cycle | Overall Lock 5 self-application | Implementation-cycle count | Variant |
|---|---:|---:|---|
| v3.6.17 | 7th | 5th | no-code packaging decision |
| v3.6.18 | 8th | 5th | no-code mapping audit decision |
| v3.6.19 | 9th | 6th | narrow docs hygiene |
| v3.6.20 | 10th | 6th | no-code boundary decision |
| v3.6.21 | 11th | 6th | no-code contract decision |
| v3.6.22 | 12th | 7th | narrow implementation |

The de106ab root meta-doc maintenance commit remains outside the Lock 5 count
chain.

## Phase 4

Phase 4 intentionally unused.

Reason:

```txt
Phase 1 diagnostic identified the bounded multi-surface implementation scope,
Phase 2 implemented Route D + E + M2, Phase 2 amend restored the missed
source-of-authority contract, and Phase 3 verified runtime, validation,
source/copy, and editor-scope boundaries without finding a deeper architecture
audit need.
```

Recent intentionally-unused chain:

```txt
v3.6.5
v3.6.6
v3.6.9
v3.6.14
v3.6.16
v3.6.17
v3.6.18
v3.6.19
v3.6.20
v3.6.21
v3.6.22
```

## Memory Promotion Notes

M6 - promote:

```txt
BACKLOG #22 explicit auto-state close prerequisites:
JS mutation + CSS cascade review + Pilot PHP root default + multi-path runtime verification.
```

Recommendation:

```txt
PROMOTE after v3.6.22 close + push if user triggers "기억해".
```

M9 - promote candidate:

```txt
implementation-cycle source-of-authority inventory check:
grep prior cycle docs for source-of-authority / authoritative / byte-identical
contracts before implementing mirrored surfaces.
```

Recommendation:

```txt
PROMOTE after v3.6.22 close + push if user triggers "기억해".
```

M7 - watch:

```txt
tracked-copy / mirror handling framework (M1/M2/M3/M4).
```

Reason:

```txt
M2 succeeded in v3.6.22, but one use case is not enough to promote the full
framework.
```

M8 - watch:

```txt
maintenance commit vs cycle commit separation.
```

Reason:

```txt
de106ab remains one successful case; more maintenance commits should reuse the
pattern before promotion.
```

M10 - watch:

```txt
trust-but-verify implementation review pattern.
```

Reason:

```txt
v3.6.22 had two useful review outcomes: the Phase 2 source-of-authority P1 and
the Phase 2 amend mount-staleness false alarm. Promote only after more
implementation-cycle reuse.
```

## Routed Forward

Future entry points:

1. Inline first-paint / FOUC policy:
   - only if product context requires it;
   - likely product-context / BACKLOG #21-adjacent;
   - not part of BACKLOG #22 close.
2. Distributable skeleton bootstrap root default:
   - decide future product `language_attributes` policy separately;
   - do not inherit the Pilot PHP filter automatically.
3. Core Block Catalog split shell consistency:
   - use v3.6.21 selector ownership;
   - default catalog selector remains `.sg-theme`.
4. Publish / mirror drift monitoring:
   - current M2 dual edit is byte-identical;
   - if publish tooling later rewrites mirrors differently, route as a
     publish/mirror drift follow-on.
5. BACKLOG #21 Interpreter Plugin strategy:
   - editor preview UI;
   - HCT / Material Color Utilities;
   - Global Styles sync;
   - PluginSidebar behavior.
6. Release-seal derivative generation after product context exists.
7. Distributable build-copy pipeline.
8. Webdesign-craftsman workflow ontology.
9. Media catalog implementation.
10. Pixabay video isolation.
11. `ontology-theme-pilot/assets/` modernization or freeze.
12. BACKLOG #44 specimen coverage.
13. BACKLOG #46 disabled ripple host hygiene.
14. BACKLOG #47 popover provider hygiene.
15. v3.6.15-v3.6.17 diagnostics policy follow-ons.

## Close Status

```txt
v3.6.22 Theme Switcher Explicit Auto State: CLOSED
BACKLOG #22: RESOLVED
recommended commit message: Close v3.6.22 explicit data-theme auto state
recommended commit shape: single 19-file close commit
```

## Review Request

```txt
P1: Any blocker to commit + push?
P2: Is BACKLOG #22 correctly closed while #21/editor/product-context work remains routed forward?
P3: Should M6 and M9 be promoted after close + push?
```

Commit + push must wait for Opus Phase 5 close verdict and explicit user
commit + push GO.
