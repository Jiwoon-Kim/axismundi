# v3.6.13 Wave 2B-4 Actions Consumers - Phase 2 Report

Date: 2026-05-22

Phase: 2 - Route A Implementation

## Verdict

Route A implemented:

```txt
FAB menu #5
Split button #7
Toolbar #8
```

All implementation is lab-scoped. No provider, baseline, WordPress/Pilot,
styleguide, global runtime, validator, generator, AGENTS.md, or CLAUDE.md file
was edited.

## Files Added

```txt
fab-menu/       7 files
split-button/   7 files
toolbar/        7 files
phase report    1 file
```

Total: 22 files, matching Phase 1 write scope.

## Runtime Isolation

| File | Persistent document/window listener | Notes |
|---|---:|---|
| `lab-fab-menu.js` | 0 | one `DOMContentLoaded` setup only; listeners attach to FAB menu hosts/controls |
| `lab-split-button.js` | 0 | one `DOMContentLoaded` setup only; popover owns menu open/close |
| `lab-toolbar.js` | 0 | one `DOMContentLoaded` setup only; listeners attach under `.lab-toolbar-demo` |

No shared provider or global runtime was created.

## Split Button Trigger Placement

Primary segment:

```txt
data-popover-trigger: 0
aria-haspopup:        0
aria-controls:        0
```

Trailing segment:

```txt
data-popover-trigger: present
aria-haspopup="menu": present
aria-expanded:        present
aria-controls:        present
```

This preserves the Phase 1 primary-action vs menu-open semantic boundary.

## Ripple Counts

Authoring counts in the Route A pattern pages:

| Module | Host | Ripple |
|---|---|---:|
| FAB menu | close buttons | `data-ax-ripple="unbounded"` x2 |
| FAB menu | enabled action rows | `data-ax-ripple="bounded"` x4 |
| FAB menu | disabled action rows | 0 |
| Split button | enabled primary segments | `data-ax-ripple="bounded"` x2 |
| Split button | enabled trailing segments | `data-ax-ripple="bounded"` x2 |
| Split button | enabled menu items | `data-ax-ripple="bounded"` x4 |
| Split button | disabled menu rows | 0 |
| Toolbar | icon buttons | `data-ax-ripple="unbounded"` x7 |
| Toolbar | text buttons | `data-ax-ripple="bounded"` x1 |
| Toolbar | spacer/separator | 0 |

The counts follow the Phase 1 per-host ripple matrix. BACKLOG #46 is not
entered.

## Provider Boundaries

```txt
popover/:      unchanged; consumed only by Split button trailing menus
ripple/:       unchanged; consumed through data-ax-ripple attributes
icon-system/:  unchanged; consumed through Material Symbols policy
```

FAB menu does not load or consume `popover/`.

Toolbar does not load `scripts/theme.js`.

## Phase 1 Findings Absorbed

- P3-1 ripple matrix: implemented as counted above.
- P3-2 FAB menu role: native button-list; no role=menu/menuitem.
- P3-3 Toolbar collision: pattern page omits `scripts/theme.js`; local runtime.
- P3-4 Split button contract: parent role=group, primary plain button,
  trailing popover trigger.
- P3-5 #41/#44/#46/#47 stay-out: preserved.

## Validation Run

```txt
node --check lab-fab-menu.js
node --check lab-split-button.js
node --check lab-toolbar.js
wp-env run cli wp core version
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
npm run validate:computed
npm run publish:styleguide
git diff --check
```

All commands PASS. `wp-env` reported WordPress 7.0, `npm test` reported Axis
A-G all 1.000, and `publish:styleguide` completed with generated mirror
artifacts restored afterward.

## Phase 3 Expected QA

Verify:

- 3 modules x desktop/mobile x light/dark visual matrix.
- FAB menu open/close, Escape, activation output, disabled no activation.
- Split button primary action distinct from trailing menu trigger.
- Split button popover open/close, Escape, outside click, focus restore.
- Toolbar aria-pressed + is-selected toggle without `scripts/theme.js`.
- Ripple actual DOM creation on approved hosts.
- Disabled hosts and spacers produce no ripple.
- All providers and fences remain unchanged.
