# v3.6.14 Wave 3 Closure - Inputs / Feedback Final - Phase 3 Visual / Interaction QA

Status: GO for Phase 5 review, with WP-env-dependent validation blocked by
local Docker availability.

This phase validates the Route A implementation for Slider, Loading, and
Progress. The test target was the repo-root localhost server:

```txt
http://127.0.0.1:8796/products/reference-implementations/axismundi-lab/modules/...
```

The repo-root server convention preserves self-hosted font and token paths.

## Phase 2 Review Findings Absorbed

P3-1 reduced-motion evidence:

```txt
Captured with Chrome DevTools Protocol Emulation.setEmulatedMedia:
features=[{ name: "prefers-reduced-motion", value: "reduce" }]
```

P3-2 Progress indeterminate labels:

```txt
linear indeterminate:   aria-label="Sync in progress"
circular indeterminate: aria-label="Background task in progress"
```

P3-3 theme evidence path:

```txt
scripts/theme.js no-load verified.
Light / dark evidence used programmatic data-theme switching, matching the
v3.6.13 module-page convention.
```

Forbidden ancestor:

```txt
N/A. Slider, Loading, and Progress are self-contained Component Full-Spec rows
with no provider-host nesting or forbidden-ancestor bail-out behavior.
```

## Visual Matrix

3 modules x desktop/mobile x light/dark = 12 cells.

| Module | Viewport | Theme | Console errors | 4xx assets | Overflow X | Body bg | Body color | theme.js loaded |
|---|---|---|---:|---:|---:|---|---|---|
| Slider | desktop 1280x720 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Slider | desktop 1280x720 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Slider | mobile 390x844 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Slider | mobile 390x844 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Loading | desktop 1280x720 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Loading | desktop 1280x720 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Loading | mobile 390x844 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Loading | mobile 390x844 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Progress | desktop 1280x720 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Progress | desktop 1280x720 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |
| Progress | mobile 390x844 | light | 0 | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | no |
| Progress | mobile 390x844 | dark | 0 | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | no |

Screenshots were captured under:

```txt
tmp/v3.6.14-phase3/
```

These are local QA artifacts and are not tracked.

## Slider Evidence

Static structure:

```txt
range inputs:        4
labelled inputs:     4
disabled inputs:     1
visible outputs:     40 / 75 / 3 / 30
window.labSlider:    object
```

Keyboard / value sync:

```txt
target:              #slider-volume
ArrowRight result:   value 41
Home result:         value 0
final --_value:      0.00%
final output text:   0
aria-labelledby:     slider-volume-label
label text:          Volume
```

Verdict:

```txt
PASS. Native range value changes, accessible labeling, visible output sync, and
lab-local --_value sync all hold.
```

## Loading Evidence

Static structure:

```txt
role=status count:       2
status names:            Loading content / Loading panel
aria-hidden inline:      1
window.labLoading:       undefined
```

Reduced motion with CDP emulation:

```txt
matchMedia reduce:       true
.ax-loading__svg:        animation-name none
.ax-loading__circle:     animation-name ax-loading-pulse
```

Verdict:

```txt
PASS. Standalone status semantics, decorative inline semantics, JS-free
boundary, and reduced-motion fallback all hold.
```

## Progress Evidence

Static structure:

```txt
role=progressbar count:          8
determinate count:               6
indeterminate count:             2
indeterminate aria-valuenow:     0
window.labProgress:              undefined
```

Accessible names:

```txt
Upload progress 25 percent
Upload progress 60 percent
Upload progress 90 percent
Sync in progress
Sync progress 25 percent
Sync progress 60 percent
Sync progress 90 percent
Background task in progress
```

Reduced motion with CDP emulation:

```txt
matchMedia reduce:                     true
linear ::before animation-name:         ax-progress-linear-pulse
linear ::after display:                 none
circular svg animation-name:            none
circular active animation-name:         ax-progress-circular-pulse
```

Verdict:

```txt
PASS. Determinate / indeterminate ARIA separation, label specificity, JS-free
boundary, and reduced-motion fallback all hold.
```

## Fences

Preserved:

```txt
AGENTS.md / CLAUDE.md
NEXT-SESSION.md / CURRENT-STATE.md / CHANGELOG.md / ROADMAP.md / BACKLOG.md
components.css / style-guide.html
scripts/style-guide.js / scripts/theme.js
popover/ ripple/ icon-system/
products/reference-implementations/axismundi-pilot/
tools/validators/
tools/generators/
```

`npm run publish:styleguide` was run as a validation command. It regenerated
generated mirror artifacts, and those generated-output changes were restored
after validation. No generated mirror file remains modified.

## Validation

Passed:

```txt
node --check products/reference-implementations/axismundi-lab/modules/slider/lab-slider.js
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
npm run publish:styleguide
git diff --check
```

`npm test` result:

```txt
Overall: 1.000 PASS
Axis A schema:  1.000
Axis B theme:   1.000
Axis C css:     1.000
Axis D runtime: 1.000
Axis E tokens:  1.000
Axis F bridge:  1.000
Axis G custom:  1.000
```

Blocked by local Docker / wp-env availability:

```txt
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
```

Failure reason:

```txt
npx wp-env start
  wp-env is offline
  Docker Desktop Linux engine pipe missing:
  //./pipe/dockerDesktopLinuxEngine
```

The initial `validate:specimen-wall` and `validate:computed` attempts failed
because `http://localhost:8888` refused connections. This is environment
availability, not a v3.6.14 implementation failure.

`git diff --check` still reports the pre-existing `NEXT-SESSION.md` CRLF/LF
warning, but no whitespace errors.

## Phase 5 Carry-Forward

If reviewer accepts Phase 3, Phase 5 should:

- update release mechanics docs;
- record `Slider #21`, `Loading #30`, and `Progress #31` as DONE in current
  state authority;
- explicitly choose the v3.6.x matrix policy: leave
  `docs/v3.5.0/MODULE-STATUS-MATRIX.md` as baseline/stale status source and
  keep `CURRENT-STATE.md` + latest close docs as current authority;
- route styleguide integration as a follow-on candidate, because v3.6.14 kept
  `style-guide.html` unchanged;
- route VS Code diagnostics sweep as the next good post-module-coverage
  candidate.
