# v3.6.10 - Wave 2B Form - Phase 3 Visual / Interaction QA

Date: 2026-05-22

Phase: 3 - Visual / Interaction QA

## Verdict

PASS. Route B is ready for Phase 5 close.

Validated modules:

```txt
Checkbox #18
Radio #19
Switch #20
```

No implementation files were changed in Phase 3.

## Test Target

Repo-root localhost server:

```txt
http://127.0.0.1:8791
```

Pattern pages:

```txt
products/reference-implementations/axismundi-lab/modules/checkbox/lab-checkbox-pattern.html
products/reference-implementations/axismundi-lab/modules/radio/lab-radio-pattern.html
products/reference-implementations/axismundi-lab/modules/switch/lab-switch-pattern.html
```

Theme mode was set programmatically during QA because the pattern pages do not
load `../../scripts/theme.js`.

## Visual Matrix

All 12 cells passed:

```txt
3 modules x desktop/mobile x light/dark
```

| Module | Cell | Console errors | Overflow X | Body bg | Body color | Focus outline |
|---|---|---:|---:|---|---|---|
| Checkbox | desktop light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `rgb(98, 91, 113) solid 2px` |
| Checkbox | desktop dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `rgb(204, 194, 220) solid 2px` |
| Checkbox | mobile light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `rgb(98, 91, 113) solid 2px` |
| Checkbox | mobile dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `rgb(204, 194, 220) solid 2px` |
| Radio | desktop light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `rgb(98, 91, 113) solid 2px` |
| Radio | desktop dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `rgb(204, 194, 220) solid 2px` |
| Radio | mobile light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `rgb(98, 91, 113) solid 2px` |
| Radio | mobile dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `rgb(204, 194, 220)` `solid 2px` |
| Switch | desktop light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `rgb(98, 91, 113) solid 2px` |
| Switch | desktop dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `rgb(204, 194, 220) solid 2px` |
| Switch | mobile light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | `rgb(98, 91, 113) solid 2px` |
| Switch | mobile dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | `rgb(204, 194, 220) solid 2px` |

The light/dark body tuples match recent close evidence and preserve Lock 1/2
token expectations.

## Checkbox Interaction Matrix

| Check | Observed | Result |
|---|---|---|
| Input count | `10` | PASS |
| Error specimen count | `2` | PASS |
| Page-load indeterminate | `indeterminate=true`, `checked=false`, `aria-checked="mixed"` | PASS |
| Disabled indeterminate | `true` | PASS |
| Indeterminate click transition | `indeterminate=false`, `checked=true` | PASS |
| Label click | `false -> true` | PASS |
| Space key toggle | `false -> true` | PASS |
| Disabled no-toggle | `false -> false` | PASS |

Interpretation:

```txt
lab-checkbox.js correctly sets demo-only indeterminate state at load.
After user interaction, native browser checkbox behavior takes over and
indeterminate becomes false. This is expected transient behavior.
```

## Radio Interaction Matrix

| Check | Observed | Result |
|---|---|---|
| Fieldsets | `2` | PASS |
| Legends | `2` | PASS |
| Radio inputs | `6` | PASS |
| Disabled inputs | `1` | PASS |
| Initial selected cadence | `weekly` | PASS |
| Label click monthly | `weekly -> monthly` | PASS |
| ArrowRight from weekly | `weekly -> monthly` | PASS |
| ArrowLeft from monthly | `monthly -> weekly` | PASS |
| Disabled no-select | `false -> false` | PASS |

Radio remains JS-free and browser-native.

## Switch Interaction Matrix

| Check | Observed | Result |
|---|---|---|
| `role="switch"` inputs | `6` | PASS |
| `type="checkbox"` inputs | `6` | PASS |
| Disabled switch inputs | `2` | PASS |
| Initial FormData | `preferences=compact` | PASS |
| Label click reduced-motion | adds `preferences=reduced-motion` | PASS |
| Space key first press | adds `preferences=reduced-motion` | PASS |
| Space key second press | removes `preferences=reduced-motion` | PASS |
| Disabled no-toggle | `false -> false` | PASS |

This directly closes the Phase 2 review request:

```txt
<input type="checkbox" role="switch">
```

preserves native form participation while exposing switch semantics.

## Phase 2 P3 Absorption

### P3-1 - Switch FormData Participation

Resolved.

Evidence:

```txt
initial FormData:        preferences=compact
after Space on reduced:  preferences=compact, preferences=reduced-motion
after second Space:      preferences=compact
```

### P3-2 - Checkbox Indeterminate Transient Nature

Resolved.

Evidence:

```txt
page load:  indeterminate=true, checked=false
after click: indeterminate=false, checked=true
```

This is expected native behavior. The fixture setup presents initial mixed
state only; it does not persist mixed state after user interaction.

### P3-3 - Theme Switching Mechanism

Resolved.

The QA script set `document.documentElement.setAttribute("data-theme", "dark")`
programmatically because the module pattern pages omit `theme.js`.

### P3-4 - labCheckbox Global

Carried to Phase 5.

`window.labCheckbox = { init: setIndeterminate }` remains acceptable for Phase
3 because the script is fixture-only and no runtime regression was observed.
Phase 5 should decide whether to accept the fixture re-init API as-is or route
a future hygiene note for module-private fixture setup.

## Validation

| Command | Result |
|---|---|
| `wp-env run cli wp core version` | PASS - 7.0 |
| `python tools/generators/build_pilot_specimen_wall.py` | PASS - pages 29 and 41 updated |
| `npm run validate:specimen-wall` | PASS |
| `php -l products/reference-implementations/axismundi-pilot/functions.php` | PASS |
| `npm test` | PASS - overall 1.000, Axis A/B/C/D/E/F/G all 1.000 |
| `npm run validate:computed` | PASS |
| `npm run publish:styleguide` | PASS |
| `git diff --check` | PASS |

Validator-generated tracked reports and publish mirror files were restored
after validation. No validator or styleguide artifact churn remains in the
intended Phase 3 diff.

## Lock Status

### Lock 1

Preserved. Axis G remained `1.000`.

### Lock 2

Preserved. Axis E remained `1.000`.

### Lock 3

Preserved. `core/button` semantic route was not reopened.

### Lock 4

Preserved. Dialog / Sheet / Date+Time / Actions consumers remain explicitly
routed out of Route B.

### Lock 5

Not promoted in Phase 3. `AGENTS.md` and `CLAUDE.md` remain unchanged.

Phase 5 must make the explicit six-cycle promote-or-defer decision.

## Fences

Phase 3 made no implementation edits. The following remain out of scope:

```txt
AGENTS.md
CLAUDE.md
theme.json
functions.php
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
closed Wave 2A modules
existing form-adjacent modules
WordPress / Pilot bridge and fixtures
validators / generator
```

## Next

Proceed to Phase 5 close.

Expected close work:

```txt
docs/v3.6.10/WAVE-2B-FORM-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

Phase 5 should close Wave 2B-1 Checkbox / Radio / Switch, route Dialog/Sheet,
Date+Time, and Actions consumers forward, and decide Lock 5 promote/defer with
six-cycle evidence.
