# Menu Spec Audit

Status: v3.6.9 Phase 2 lab implementation.

Index note: `#15` in this cycle means the Component Coverage Map and Module
Status Matrix index, not a BACKLOG.md item number.

## Scope

- Adds a Menu lab component module as a consumer of the existing `popover/`
  runtime.
- Uses existing `.ax-menu` and `.ax-menu__item*` primitives from
  `stylesheets/components.css`.
- Demonstrates section labels, dividers, leading icons, trailing shortcuts,
  supporting text, selected state, disabled state, destructive item tone, and
  a documented submenu defer.
- Uses bounded animated ripple only on enabled Menu item hosts.

## Out Of Scope

- No changes to `components.css`.
- No changes to `popover/`, `ripple/`, or `icon-system/`.
- No `lab-menu.js` in Route A.
- No interactive submenu runtime in v3.6.9.
- No WordPress block binding.

## Boundary

Menu owns item semantics and authoring structure. `popover/` owns anchoring,
open/close, outside dismissal, Escape dismissal, focus restoration, and viewport
placement. This module consumes that provider unchanged.

## Acceptance Notes

- Enabled menu items may declare `data-ax-ripple="bounded"`.
- Disabled menu items intentionally do not declare `data-ax-ripple` in this
  cycle, keeping BACKLOG #46 open as a separate hygiene question.
- Destructive item color uses existing `--md-sys-color-error` tokens inside
  lab-scoped CSS only. No new color route or baseline token is introduced.
