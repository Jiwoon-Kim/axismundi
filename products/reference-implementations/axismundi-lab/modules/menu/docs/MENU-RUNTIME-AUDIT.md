# Menu Runtime Audit

Status: v3.6.9 Phase 2 lab implementation.

## Runtime Contract

Menu has no local runtime file in Route A.

```txt
No lab-menu.js
```

Live behavior is delegated to existing providers:

- `../popover/lab-popover.js`
- `../ripple/lab-ripple.js`

## Popover Consumption

The pattern page uses:

```txt
[data-popover-trigger]
aria-controls="<menu-id>"
.ax-menu[data-popover-wired]
```

The existing popover provider owns:

- anchoring
- positioning
- open/close state
- `aria-expanded`
- first enabled item focus
- ArrowUp / ArrowDown / Home / End navigation
- Escape close
- outside pointerdown close
- item-click close
- focus restoration
- forbidden-ancestor bail-out

## Pre-Existing Provider Note

The popover provider already contains menu-local keyboard behavior and
`.ax-menu__item` selectors. v3.6.9 accepts that as the factual existing
provider contract for Route A, while preserving the normative boundary that
future provider work must not absorb additional Menu-specific semantics.

Future hygiene may consider extracting menu-item-class logic into a clearer
Menu runtime helper, but that is out of scope for v3.6.9.

## Ripple Consumption

Enabled Menu item hosts use:

```html
data-ax-ripple="bounded"
```

Disabled Menu item hosts do not include a ripple attribute. This keeps BACKLOG
#46 separate from Menu closure.

## Submenu

Interactive submenu is explicitly deferred. The pattern page includes a
documented non-interactive defer note only.
