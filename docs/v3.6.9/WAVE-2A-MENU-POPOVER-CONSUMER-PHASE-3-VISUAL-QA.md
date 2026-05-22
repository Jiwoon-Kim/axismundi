# v3.6.9 - Wave 2A-2 Menu / Popover Consumer - Phase 3 Visual QA

Status: Phase 3 visual and interaction QA.

Verdict:

```txt
GO for Phase 5 close.
```

Route A remains valid. The Menu module consumes `popover/` and `ripple/`
unchanged, has no `lab-menu.js`, and keeps interactive submenu deferred.

## Test Target

The pattern page was served from the repository root so self-hosted font and
Material Symbols paths resolve correctly:

```txt
http://127.0.0.1:8790/products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
```

Direct `file://` navigation was not used for final evidence because the
automation browser blocks local file URLs by policy. A first local server rooted
at `axismundi-lab/` also showed three expected 404s for repository-root font
assets; final evidence uses the repo-root server and has console errors `0`.

## Visual Matrix

| Cell | Viewport | Theme | Console errors | Overflow X | Body background | Body color | Triggers | Wired surfaces | Static open surfaces | All menu surfaces |
|---|---:|---|---:|---:|---|---|---:|---:|---:|---:|
| desktop/light | 1280x800 | light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | 3 | 3 | 1 | 4 |
| desktop/dark | 1280x800 | dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | 3 | 3 | 1 | 4 |
| mobile/light | 390x900 | light | 0 | 0 | `rgb(254, 247, 255)` | `rgb(29, 27, 32)` | 3 | 3 | 1 | 4 |
| mobile/dark | 390x900 | dark | 0 | 0 | `rgb(20, 18, 24)` | `rgb(230, 224, 233)` | 3 | 3 | 1 | 4 |

Submenu defer note was visible in all cells.

## Focus Evidence

Keyboard-open path:

```txt
focus trigger -> ArrowDown
```

Observed:

```txt
expanded:              true
open:                  true
active text:           add New document Ctrl+N
matches :focus-visible true
outline:               3px solid rgb(98, 91, 113)
outline-offset:        -3px
::before opacity:      0.1
```

Mouse-click open intentionally does not match `:focus-visible`, which is
expected browser behavior. The keyboard-open path confirms the visible focus
indicator.

## Live Interaction Matrix

Surface:

```txt
button[aria-controls="menu-command-actions"]
#menu-command-actions
```

| Check | Observed | Result |
|---|---|---|
| trigger click opens menu | `aria-expanded="true"`, menu `.is-open=true`, `openMenuId="menu-command-actions"` | PASS |
| first enabled item focus | `add New document Ctrl+N` | PASS |
| ArrowDown | `folder_open Open Ctrl+O` | PASS |
| ArrowDown again | `save Autosave on check` | PASS |
| ArrowDown skips disabled item | `delete Delete draft` | PASS |
| ArrowUp | `save Autosave on check` | PASS |
| End | `delete Delete draft` | PASS |
| Home | `add New document Ctrl+N` | PASS |
| Escape closes | `aria-expanded="false"`, menu `.is-open=false`, focus restored to trigger with `aria-controls="menu-command-actions"` | PASS |
| outside pointerdown closes | `aria-expanded="false"`, menu `.is-open=false` | PASS |
| item click closes | `aria-expanded="false"`, menu `.is-open=false`, focus restored to trigger | PASS |

Secondary wired surface:

```txt
button[aria-controls="menu-account-actions"]
#menu-account-actions
```

Observed:

```txt
aria-expanded="true"
menu .is-open=true
first focused item: person Profile @axismundi
```

Result: PASS.

## Forbidden-Ancestor Negative Probe

Surface:

```txt
button[aria-controls="menu-forbidden-actions"] inside .prose
#menu-forbidden-actions
```

Observed after click:

```txt
aria-expanded: false
menu .is-open: false
visibility:    hidden
opacity:       0
console errors: 0
```

Result: PASS. The existing `popover/` forbidden-ancestor bail-out remains
effective for Menu.

## Ripple Evidence

Observed:

```txt
enabled data-ax-ripple hosts:   10
disabled item hosts:            2
disabled data-ax-ripple hosts:  0
.ax-ripple nodes after click:   1
command menu .ax-ripple nodes:  1
```

Result: PASS.

Enabled Menu item hosts consume bounded Ripple v2. Disabled Menu item hosts do
not carry a ripple attribute, preserving BACKLOG #46 as a separate authoring
hygiene item.

## Static Surface Separation

Observed:

```txt
popover-wired surfaces:         3
static open surfaces:           1
static surface text:            Structure label Leading icon Shortcut Ctrl+K Disabled without ripple attribute
```

The static open surface is a structure specimen only. It is not part of the
open/close interaction matrix.

## Submenu Defer Check

Observed:

```txt
defer note visible:             true
interactive submenu triggers:   0
```

Defer note text:

```txt
Interactive submenu remains deferred in v3.6.9. Nested anchored surfaces
require a separate provider-contract review for parent-child dismissal,
focus containment, and viewport collision.
```

Result: PASS.

## Phase 2 Review P3 Handling

### P3-1 - Forbidden-Ancestor Bail-Out

Resolved in this report. The `.prose` trigger did not open its menu and did not
change `aria-expanded`.

### P3-2 - Menu-Local Keyboard Nav Evidence

Resolved in this report. ArrowUp / ArrowDown / Home / End / Escape all behaved
as the factual existing `popover/` provider contract describes, including
disabled-item skip.

### P3-3 - Static vs Live Surface Separation

Resolved in this report. The three `data-popover-wired` surfaces are the live
test targets; the one `.ax-menu.is-open:not([data-popover-wired])` surface is a
static structure specimen.

### P3-4 - BACKLOG #47 Hygiene Routing

Carry to Phase 5. Phase 5 should decide whether to add a BACKLOG item for
popover provider menu-item-class logic extraction hygiene.

## Fence Check

Phase 3 did not edit implementation files. Expected unchanged:

```txt
AGENTS.md
CLAUDE.md
theme.json
functions.php
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/fixtures/*
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
products/reference-implementations/axismundi-lab/modules/app-bar/*
products/reference-implementations/axismundi-lab/modules/nav-bar/*
products/reference-implementations/axismundi-lab/modules/nav-rail/*
products/reference-implementations/axismundi-lab/modules/tabs/*
products/reference-implementations/axismundi-lab/modules/menu/*
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
```

## Validation

Commands re-run for close evidence:

```txt
wp-env run cli wp core version                         PASS - 7.0
python tools/generators/build_pilot_specimen_wall.py   PASS - pages 29 and 41 updated
npm run validate:specimen-wall                         PASS
php -l products/reference-implementations/axismundi-pilot/functions.php
                                                        PASS
npm test                                                PASS - overall 1.000
  Axis A schema                                         1.000
  Axis B theme                                          1.000
  Axis C css                                            1.000
  Axis D runtime                                        1.000
  Axis E tokens                                         1.000
  Axis F bridge                                         1.000
  Axis G custom                                         1.000
npm run validate:computed                              PASS
npm run publish:styleguide                             PASS
git diff --check                                       PASS
```

Generated validator reports and publish mirror files were restored after
validation. No validator or styleguide artifact churn remains in the intended
Phase 3 diff.

## Next

Submit this report for review. If approved, Phase 5 may close v3.6.9 and update
BACKLOG / CHANGELOG / ROADMAP / CURRENT-STATE / NEXT-SESSION.
