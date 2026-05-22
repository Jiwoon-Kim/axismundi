# v3.6.9 - Wave 2A-2 Menu / Popover Consumer - Phase 2 Report

Status: Route A implementation.

Verdict:

```txt
Route A implemented: Menu Consumer Closure, Provider Unchanged.
```

Phase 2 added a Menu lab module as a consumer of existing `popover/` and
`ripple/` providers. It did not add `lab-menu.js`.

## Files Added

```txt
products/reference-implementations/axismundi-lab/modules/menu/lab-menu.css
products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-RUNTIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/menu/docs/MENU-WP-MAPPING.md
```

No `lab-menu.js` was added.

## Phase 1 P3 Handling

### P3-1 - Pre-Existing Popover Menu-Item Logic

Documented in `MENU-RUNTIME-AUDIT.md`.

v3.6.9 accepts the pre-existing popover provider's menu-local keyboard logic as
the factual provider contract for Route A. The normative responsibility map
still distinguishes Menu-owned semantics from provider-owned anchored-surface
mechanics. No provider edit was made.

Phase 5 should decide whether to add a BACKLOG hygiene item for extracting
menu-item-class logic from `popover/` into a clearer Menu helper.

### P3-2 - Destructive Item Color Route

The destructive specimen uses existing system tokens only:

```txt
--md-sys-color-error
```

The rule is lab-scoped:

```txt
.lab-menu-demo .lab-menu-item--danger
```

No new token, no baseline color route, and no `components.css` edit were
introduced.

### P3-3 - Source Input Pattern

Phase 2 continued the Phase 0/1 source-of-truth model: repo docs are authority,
and focused reads were limited to files directly affected by Route A.

## Route A Decisions

### Provider

```txt
popover/ unchanged
ripple/ unchanged
icon-system/ unchanged
```

Menu consumes `popover/` with:

```txt
[data-popover-trigger]
aria-controls
.ax-menu[data-popover-wired]
```

The pattern page loads:

```txt
../popover/lab-popover.css
../popover/lab-popover.js
../ripple/lab-ripple.css
../ripple/lab-ripple.js
```

### Runtime

```txt
lab-menu.js: not added
```

All live behavior is provider-owned or baseline CSS-owned.

### Ripple

Enabled Menu item hosts use:

```txt
data-ax-ripple="bounded"
```

Disabled Menu item hosts use:

```txt
no data-ax-ripple attribute
```

This keeps BACKLOG #46 open and separate.

### Submenu

Interactive submenu is deferred. The pattern page includes a documented
non-interactive defer note only.

## Authoring Counts

Expected from the Phase 2 files:

```txt
lab-menu.js files:             0
data-popover-trigger hosts:    3
ax-menu surfaces:              4
enabled data-ax-ripple hosts:  10
disabled item hosts:           2
disabled data-ax-ripple hosts: 0
material-symbols-rounded:      13
translate="no":                13
```

The forbidden-ancestor menu is present as a negative probe. Its trigger is
inside `.prose` and must not open.

## Fence Check

Expected unchanged:

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
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
```

Manual `style-guide.html` edits remain out of scope.

## Validation

Commands run:

```txt
wp-env run cli wp core version
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
npm run validate:computed
npm run publish:styleguide
git diff --check
```

Results:

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
Phase 2 diff.

## Next

Phase 3 should perform visual and interaction QA on:

```txt
file:///C:/Users/thaum/dev/axismundi/products/reference-implementations/axismundi-lab/modules/menu/lab-menu-pattern.html
```

Required checks:

```txt
desktop light / desktop dark / mobile light / mobile dark
console errors: 0
horizontal overflow at 390px: 0
open/close behavior
Escape close and focus restoration
outside pointerdown close
item-click close
ArrowUp / ArrowDown / Home / End navigation
forbidden-ancestor non-open
enabled item ripple creation
disabled item no ripple attribute and no ripple creation
submenu remains non-interactive/deferred
```
